<?php

declare(strict_types=1);

namespace Yard\Warden\Password;

use WP_Error;
use WP_User;
use ZxcvbnPhp\Zxcvbn;

/**
 * Exit when accessed directly.
 */
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Validates password strength using zxcvbn-php https://github.com/bjeavons/zxcvbn-php.
 */
class StrengthValidator
{
	public const DEFAULT_MIN_LENGTH = 16;
	public const DEFAULT_MIN_SCORE = 4;
	public const MAX_SCORE = 4;

	protected Zxcvbn $zxcvbn;

	public function __construct(?Zxcvbn $zxcvbn = null)
	{
		$this->zxcvbn = $zxcvbn ?? new Zxcvbn();
	}

	public function validate(string $password, ?WP_User $user = null): ?WP_Error
	{
		if ('' === $password) {
			return null;
		}

		$minLength = (int) apply_filters('yard::warden/password/min-length', self::DEFAULT_MIN_LENGTH);

		if (strlen($password) < $minLength) {
			return new WP_Error(
				'weak_password_length',
				sprintf(
					/* translators: %d: minimum password length */
					__('Password must be at least %d characters.', 'yard-warden'),
					$minLength
				)
			);
		}

		$minScore = (int) apply_filters('yard::warden/password/min-score', self::DEFAULT_MIN_SCORE);
		$result = $this->zxcvbn->passwordStrength($password, $this->userInputs($user));

		if ((int) ($result['score'] ?? 0) < $minScore) {
			$feedback = $result['feedback']['warning'] ?? __('This password is too easy to guess.', 'yard-warden');

			return new WP_Error('weak_password_score', (string) $feedback);
		}

		return null;
	}

	/**
	 * @return string[]
	 */
	protected function userInputs(?WP_User $user): array
	{
		if (! $user instanceof WP_User) {
			return [];
		}

		return array_values(array_filter([
			$user->user_login,
			$user->user_email,
			$user->display_name,
			$user->first_name,
			$user->last_name,
		]));
	}
}
