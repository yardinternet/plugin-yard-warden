<?php

declare(strict_types=1);

namespace Yard\Warden\Login;

use WP_Error;

/**
 * Exit when accessed directly.
 */
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Hardens login by replacing enumeration-friendly errors with a generic message.
 *
 * Only authentication errors are rewritten. Password-reset (rp) and
 * profile-update errors flow through unchanged so weak-password or other
 * validation messages remain visible to the user.
 */
class LoginServiceProvider
{
	public const LEAKY_ERROR_CODES_FILTER = 'yard::warden/login/leaky-error-codes';
	public const GENERIC_ERROR_FILTER = 'yard::warden/login/generic-error';

	private const DEFAULT_LEAKY_CODES = [
		'invalid_username',
		'invalid_email',
		'incorrect_password',
	];

	public function register(): void
	{
		add_filter('authenticate', [$this, 'filterAuthenticateErrors'], 30, 2);
	}

	/**
	 * @param WP_Error|\WP_User|null $user
	 * @param string $username
	 *
	 * @return WP_Error|\WP_User|null
	 */
	public function filterAuthenticateErrors($user, $username)
	{
		if (! $user instanceof WP_Error) {
			return $user;
		}

		if (! $this->isLeakyCode($user->get_error_code())) {
			return $user;
		}

		return new WP_Error('invalid_credentials', $this->genericErrorMessage());
	}

	private function isLeakyCode(string $code): bool
	{
		$leakyCodes = (array) apply_filters(self::LEAKY_ERROR_CODES_FILTER, self::DEFAULT_LEAKY_CODES);

		return in_array($code, $leakyCodes, true);
	}

	private function genericErrorMessage(): string
	{
		return (string) apply_filters(
			self::GENERIC_ERROR_FILTER,
			__('Invalid credentials.', 'yard-warden')
		);
	}
}
