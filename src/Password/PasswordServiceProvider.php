<?php

declare(strict_types=1);

namespace Yard\Warden\Password;

use WP_Error;
use WP_User;

/**
 * Exit when accessed directly.
 */
if (! defined('ABSPATH')) {
	exit;
}

class PasswordServiceProvider
{
	private StrengthValidator $validator;

	public function __construct(StrengthValidator $validator)
	{
		$this->validator = $validator;
	}

	public function register(): void
	{
		add_action('user_profile_update_errors', [$this, 'handleProfileUpdateErrors'], 10, 3);
		add_action('validate_password_reset', [$this, 'handlePasswordReset'], 10, 2);
	}

	public function handleProfileUpdateErrors(WP_Error $errors, bool $update, $user): void
	{
		unset($update);

		$this->maybeAddValidationError($errors, $user);
	}

	public function handlePasswordReset(WP_Error $errors, $user): void
	{
		$this->maybeAddValidationError($errors, $user);
	}

	private function maybeAddValidationError(WP_Error $errors, $user): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- fires from user_profile_update_errors/validate_password_reset, only invoked by WP core after its own nonce check; sanitizing would mutate the value so the strength check would run against a different string than the password WP actually sets, and it's never stored or output as-is.
		$password = isset($_POST['pass1']) ? (string) wp_unslash($_POST['pass1']) : '';

		if ('' === $password) {
			return;
		}

		$wpUser = $user instanceof WP_User ? $user : null;
		$error = $this->validator->validate($password, $wpUser);

		if ($error instanceof WP_Error) {
			$errors->add($error->get_error_code(), $error->get_error_message());
		}
	}
}
