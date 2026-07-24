<?php

declare(strict_types=1);

namespace Yard\Warden\LimitLogin;

use WP_Error;
use WP_User;
use Yard\Logging\Log;

/**
 * Wires the login attempt Limiter into WordPress's login lifecycle to enforce
 * brute-force protection across three dimensions (IP+Username, IP, Username).
 */
class LimitLoginServiceProvider
{
	public const FILTER_ENABLED = 'yard::warden/limit-login/enabled';
	public const FILTER_CLIENT_IP = 'yard::warden/limit-login/client-ip';
	public const FILTER_ERROR_MESSAGE = 'yard::warden/limit-login/error-message';
	public const FILTER_SKIP_ERROR_CODES = 'yard::warden/limit-login/skip-error-codes';

	public const ERROR_CODE = 'too_many_attempts';

	/** @var string[] */
	private const DEFAULT_SKIP_CODES = ['expired_session'];

	/** @var Limiter */
	private $limiter;

	public function __construct(Limiter $limiter)
	{
		$this->limiter = $limiter;
	}

	public function register(): void
	{
		// Priority 30: after core auth at 20, so a valid-credential WP_User gets
		// overwritten with our lockout error. Same priority as LoginServiceProvider
		// but registered after it — LoginServiceProvider only rewrites leaky codes,
		// not our too_many_attempts code, so our response is the final value.
		add_filter('authenticate', [$this, 'checkLockout'], 30, 2);
		add_action('wp_login_failed', [$this, 'recordFailedAttempt'], 10, 2);
		add_action('wp_login', [$this, 'onSuccessfulLogin'], 10, 1);
	}

	/**
	 * Block authentication if any dimension is locked out.
	 *
	 * @param WP_Error|WP_User|null $user
	 * @param string $username
	 *
	 * @return WP_Error|WP_User|null
	 */
	public function checkLockout($user, $username)
	{
		if (! $this->isEnabled()) {
			return $user;
		}

		if ('' === $username) {
			return $user;
		}

		$username = $this->sanitizeUsername($username);

		if ('' === $username) {
			return $user;
		}

		$ip = $this->clientIp();
		$dimensions = $this->lockoutDimensions($ip, $username);

		foreach ($dimensions as $dimension => $identifier) {
			if ($this->limiter->isLockedOut($dimension, $identifier)) {
				Log::debug(sprintf(
					'Yard Warden: login blocked — %s lockout active for %s',
					$dimension,
					'ip' === $dimension ? $ip : $username
				));

				if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST && ! headers_sent()) {
					header('HTTP/1.1 429 Too Many Requests');
					header('Retry-After: ' . $this->lockoutDuration($dimension, $ip, $username));
				}

				return new WP_Error(self::ERROR_CODE, $this->errorMessage());
			}
		}

		return $user;
	}

	/**
	 * Record a failed login attempt and trigger lockout if thresholds exceeded.
	 *
	 * @param string $username
	 * @param WP_Error $error
	 */
	public function recordFailedAttempt($username, $error = null): void
	{
		if (! $this->isEnabled()) {
			return;
		}

		if ($error instanceof WP_Error && $this->shouldSkipError($error)) {
			return;
		}

		$ip = $this->clientIp();
		$username = $this->sanitizeUsername($username);

		if ('' === $ip || '' === $username) {
			return;
		}

		$dimensions = [
			'ip_user' => [
				'identifier' => $ip . '|' . $username,
				'threshold'  => $this->threshold('ip_user', $ip, $username),
				'window'     => $this->window('ip_user', $ip, $username),
				'lockout'    => $this->lockoutDuration('ip_user', $ip, $username),
			],
			'ip' => [
				'identifier' => $ip,
				'threshold'  => $this->threshold('ip', $ip, $username),
				'window'     => $this->window('ip', $ip, $username),
				'lockout'    => $this->lockoutDuration('ip', $ip, $username),
			],
			'username' => [
				'identifier' => $username,
				'threshold'  => $this->threshold('username', $ip, $username),
				'window'     => $this->window('username', $ip, $username),
				'lockout'    => $this->lockoutDuration('username', $ip, $username),
			],
		];

		foreach ($dimensions as $dimension => $config) {
			$count = $this->limiter->increment($dimension, $config['identifier'], $config['window']);

			if ($count >= $config['threshold']) {
				$this->limiter->setLockout($dimension, $config['identifier'], $config['lockout']);

				Log::warning(sprintf(
					'Yard Warden: login lockout triggered for %s — %s (locked for %ds)',
					$dimension,
					'username' === $dimension ? $username : $ip,
					$config['lockout']
				));
			}
		}

		Log::debug(sprintf(
			'Yard Warden: login attempt recorded for %s from %s',
			$username,
			$ip
		));
	}

	/**
	 * Clear all counters on successful login.
	 *
	 * @param string $username
	 */
	public function onSuccessfulLogin($username): void
	{
		if (! $this->isEnabled()) {
			return;
		}

		$ip = $this->clientIp();
		$username = $this->sanitizeUsername($username);

		if ('' === $ip || '' === $username) {
			return;
		}

		$this->limiter->clear('ip_user', $ip . '|' . $username);
		$this->limiter->clear('ip', $ip);
		$this->limiter->clear('username', $username);

		Log::debug(sprintf(
			'Yard Warden: login success, cleared ip_user, ip, and username counters for %s from %s',
			$username,
			$ip
		));
	}

	// ------------------------------------------------------------------
	// Filters
	// ------------------------------------------------------------------

	private function isEnabled(): bool
	{
		return (bool) apply_filters(self::FILTER_ENABLED, true);
	}

	private function clientIp(): string
	{
		$ip = isset($_SERVER['REMOTE_ADDR'])
			? filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, ['options' => ['default' => '']])
			: '';

		return (string) apply_filters(self::FILTER_CLIENT_IP, $ip);
	}

	private function errorMessage(): string
	{
		return (string) apply_filters(
			self::FILTER_ERROR_MESSAGE,
			__('Too many failed login attempts. Please try again later.', 'yard-warden')
		);
	}

	private function shouldSkipError(WP_Error $error): bool
	{
		$skipCodes = (array) apply_filters(self::FILTER_SKIP_ERROR_CODES, self::DEFAULT_SKIP_CODES);

		return in_array($error->get_error_code(), $skipCodes, true);
	}

	private function threshold(string $dimension, string $ip, string $username): int
	{
		$defaults = [
			'ip_user'  => Limiter::DEFAULT_THRESHOLD_IP_USER,
			'ip'       => Limiter::DEFAULT_THRESHOLD_IP,
			'username' => Limiter::DEFAULT_THRESHOLD_USERNAME,
		];

		return (int) apply_filters(
			'yard::warden/limit-login/threshold/' . $dimension,
			$defaults[$dimension] ?? $defaults['ip_user'],
			$ip,
			$username
		);
	}

	private function window(string $dimension, string $ip, string $username): int
	{
		$defaults = [
			'ip_user'  => Limiter::DEFAULT_WINDOW_IP_USER,
			'ip'       => Limiter::DEFAULT_WINDOW_IP,
			'username' => Limiter::DEFAULT_WINDOW_USERNAME,
		];

		return (int) apply_filters(
			'yard::warden/limit-login/window/' . $dimension,
			$defaults[$dimension] ?? $defaults['ip_user'],
			$ip,
			$username
		);
	}

	private function lockoutDuration(string $dimension, string $ip, string $username): int
	{
		$defaults = [
			'ip_user'  => Limiter::DEFAULT_LOCKOUT_IP_USER,
			'ip'       => Limiter::DEFAULT_LOCKOUT_IP,
			'username' => Limiter::DEFAULT_LOCKOUT_USERNAME,
		];

		return (int) apply_filters(
			'yard::warden/limit-login/lockout/' . $dimension,
			$defaults[$dimension] ?? $defaults['ip_user'],
			$ip,
			$username
		);
	}

	private function sanitizeUsername(string $username): string
	{
		if (is_email($username)) {
			return sanitize_email($username);
		}

		return sanitize_user($username, true);
	}

	/**
	 * @return array<string, string>
	 */
	private function lockoutDimensions(string $ip, string $username): array
	{
		return [
			'ip_user'  => $ip . '|' . $username,
			'ip'       => $ip,
			'username' => $username,
		];
	}
}
