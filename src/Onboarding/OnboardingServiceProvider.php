<?php

declare(strict_types=1);

namespace Yard\Warden\Onboarding;

use WP_Error;
use WP_User;
use Yard\Logging\Log;

/**
 * Exit when accessed directly.
 */
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Removes plaintext passwords from the multisite user-onboarding flow.
 *
 *   1. Activate new signups server-side immediately so the user never receives
 *      an activation link and never lands on wp-activate.php (which would
 *      otherwise render username + plaintext password on screen).
 *   2. Suppress the default signup notification email.
 *   3. Rewrite the welcome email to contain a one-time password-reset link
 *      instead of a generated password.
 */
class OnboardingServiceProvider
{
	public const FILTER_WELCOME_SUBJECT = 'yard::warden/onboarding/welcome-subject';
	public const FILTER_WELCOME_BODY = 'yard::warden/onboarding/welcome-body';
	public const DISABLE_WELCOME_REQUEST_KEY = 'disable_welcome_email';

	public function register(): void
	{
		add_filter('wpmu_signup_user_notification', [$this, 'handleUserSignup'], 10, 4);
		add_filter('wpmu_welcome_user_notification', [$this, 'handleWelcomeEmail'], 10, 3);
		add_filter('update_welcome_user_subject', [$this, 'filterWelcomeSubject'], 10, 1);
		add_filter('update_welcome_user_email', [$this, 'filterWelcomeBody'], 10, 4);
	}

	/**
	 * Auto-activate new signups so no activation link is emailed.
	 *
	 * Returning `false` from this filter suppresses the default signup email.
	 * The welcome email (wpmu_welcome_user_notification) still fires after
	 * activation and is rewritten by filterWelcomeBody().
	 *
	 * @param string               $user_login
	 * @param string               $user_email
	 * @param string               $key
	 * @param array<string, mixed> $meta
	 */
	public function handleUserSignup($user_login, $user_email, $key, $meta): bool
	{
		Log::debug(sprintf('Yard Warden: auto-activating signup for %s', (string) $user_login));

		$result = wpmu_activate_signup((string) $key);

		if ($result instanceof WP_Error) {
			Log::warning(sprintf(
				'Yard Warden: signup activation failed for %s: %s',
				(string) $user_login,
				$result->get_error_message()
			));
		}

		return false;
	}

	/**
	 * Suppress the welcome email entirely when the admin ticked the
	 * "Skip Confirmation Email" checkbox on the network "Add User" form.
	 *
	 * @param int                  $user_id
	 * @param string               $password
	 * @param array<string, mixed> $meta
	 */
	public function handleWelcomeEmail($user_id, $password, $meta): bool
	{
		if ($this->welcomeEmailDisabled()) {
			Log::debug(sprintf('Yard Warden: welcome email disabled for user ID %d', (int) $user_id));

			return false;
		}

		return true;
	}

	/**
	 * @param string $subject
	 */
	public function filterWelcomeSubject($subject): string
	{
		if ($this->welcomeEmailDisabled()) {
			return (string) $subject;
		}

		$siteName = (string) get_bloginfo('name');

		$custom = sprintf(
			/* translators: %s: site name */
			__('Welcome to %s!', 'yard-warden'),
			$siteName
		);

		return (string) apply_filters(self::FILTER_WELCOME_SUBJECT, $custom, $siteName, $subject);
	}

	/**
	 * Replace the welcome email body with a password-reset invite.
	 *
	 * @param string               $welcome_email
	 * @param int                  $user_id
	 * @param string               $password
	 * @param array<string, mixed> $meta
	 */
	public function filterWelcomeBody($welcome_email, $user_id, $password, $meta): string
	{
		if ($this->welcomeEmailDisabled()) {
			return (string) $welcome_email;
		}

		$user = get_userdata((int) $user_id);

		if (! $user instanceof WP_User) {
			return (string) $welcome_email;
		}

		$resetKey = $this->getPasswordResetKey($user);

		if ('' === $resetKey) {
			Log::warning(sprintf(
				'Yard Warden: could not generate reset key for user %d; falling back to default welcome email',
				(int) $user_id
			));

			return (string) $welcome_email;
		}

		$resetUrl = wp_login_url(sprintf(
			'?action=rp&key=%s&login=%s',
			rawurlencode($resetKey),
			rawurlencode($user->user_login)
		));

		$body = $this->composeBody($user->user_login, $resetUrl);

		return (string) apply_filters(
			self::FILTER_WELCOME_BODY,
			$body,
			$user,
			$resetUrl,
			$welcome_email
		);
	}

	private function welcomeEmailDisabled(): bool
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only opt-out
		// flag (e.g. a bulk-import query param), doesn't change any state itself.
		return isset($_REQUEST[self::DISABLE_WELCOME_REQUEST_KEY]) && '' !== $_REQUEST[self::DISABLE_WELCOME_REQUEST_KEY];
	}

	private function getPasswordResetKey(WP_User $user): string
	{
		$key = get_password_reset_key($user);

		if ($key instanceof WP_Error) {
			Log::warning(sprintf(
				'Yard Warden: get_password_reset_key error for %s: %s',
				$user->user_login,
				$key->get_error_message()
			));

			return '';
		}

		return (string) $key;
	}

	private function composeBody(string $userLogin, string $resetUrl): string
	{
		$lines = [
			sprintf(
				/* translators: %s: user login */
				__('Welcome %s,', 'yard-warden'),
				$userLogin
			),
			'',
			__('Your account has been activated.', 'yard-warden'),
			'',
			__('Use the link below to set a password and log in:', 'yard-warden'),
			$resetUrl,
			'',
			__('For security reasons, this link is valid for a limited time only.', 'yard-warden'),
			'',
			__('Thanks!', 'yard-warden'),
		];

		return implode("\n", $lines);
	}
}
