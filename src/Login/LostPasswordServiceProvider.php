<?php

declare(strict_types=1);

namespace Yard\Warden\Login;

use WP_Error;
use WP_User;

/**
 * Exit when accessed directly.
 */
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Prevents username enumeration via the lost-password form.
 *
 * When a non-existent username or email is submitted, WordPress normally
 * displays an error that reveals the account does not exist. This provider
 * short-circuits the flow and redirects to the standard "check your email"
 * confirmation page, making valid and invalid requests indistinguishable.
 */
class LostPasswordServiceProvider
{
	public function register(): void
	{
		add_action('lostpassword_post', [$this, 'suppressUserNotFoundLeak'], 10, 2);
		add_filter('wp_login_errors', [$this, 'ambiguousConfirmMessage'], 99);
	}

	/**
	 * Redirect to the "check your email" page when the submitted user does
	 * not exist, so the response is identical to a valid reset request.
	 *
	 * Empty-input validation is left to WordPress so the user still sees
	 * the "please enter a username or email" prompt.
	 *
	 * @param WP_Error      $errors
	 * @param WP_User|false $user_data
	 */
	public function suppressUserNotFoundLeak(WP_Error $errors, $user_data): void
	{
		if ($user_data instanceof WP_User && wp_is_password_reset_allowed_for_user($user_data)) {
			return;
		}

		if ($errors->get_error_message('empty_username')) {
			return;
		}

		// User does not exist — redirect to the same confirmation page
		// WordPress shows after a successful reset request.
		wp_safe_redirect(
			add_query_arg('checkemail', 'confirm', wp_login_url())
		);
		exit;
	}

	/**
	 * Replace WordPress's "Check your email" confirmation with ambiguous
	 * wording that doesn't imply an account definitely exists.
	 */
	public function ambiguousConfirmMessage(WP_Error $errors): WP_Error
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect flag from wp_safe_redirect() above, not a form submission; no nonce to check.
		if (! isset($_GET['checkemail']) || 'confirm' !== $_GET['checkemail']) {
			return $errors;
		}

		if (! $errors->get_error_messages('confirm')) {
			return $errors;
		}

		$errors->remove('confirm');
		$errors->add(
			'confirm',
			__('If an account with this username or email address exists, you will receive an email with a link to reset your password.', 'yard-warden'),
			'message'
		);

		return $errors;
	}
}
