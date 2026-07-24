<?php

declare(strict_types=1);

namespace Yard\Warden\Password;

/**
 * Replaces WordPress's default password hint, strength indicator and
 * "Confirm use of weak password" checkbox on login / profile screens with a
 * zxcvbn-driven UI that mirrors the server-side StrengthValidator.
 */
class PasswordUIServiceProvider
{
	private const SCRIPT_HANDLE = 'yard-warden-password-ui';

	public function register(): void
	{
		add_action('login_enqueue_scripts', [$this, 'enqueue']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue']);
	}

	public function enqueue(): void
	{
		// WP bundles zxcvbn via the `zxcvbn-async` loader script.
		wp_enqueue_script('zxcvbn-async');
		wp_enqueue_script('password-strength-meter');

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			YARD_WARDEN_PLUGIN_URL . 'resources/js/password-ui.js',
			['zxcvbn-async', 'password-strength-meter'],
			YARD_WARDEN_VERSION,
			true
		);

		$css = '.pw-weak, p.pw-weak { display: none !important; }'
			. ' .user-pass1-wrap .description,'
			. ' .password-input-wrapper + .description,'
			. ' p.description.indicator-hint,'
			. ' #pass-strength-result {'
			. '   max-width: 100%;'
			. '   box-sizing: border-box;'
			. '   overflow-wrap: break-word;'
			. '   word-wrap: break-word;'
			. '   white-space: normal;'
			. '   line-height: 1.5;'
			. ' }';

		wp_add_inline_style('login', $css);
		wp_add_inline_style('wp-admin', $css);

		$minLength = (int) apply_filters('yard::warden/password/min-length', StrengthValidator::DEFAULT_MIN_LENGTH);
		$minScore = (int) apply_filters('yard::warden/password/min-score', StrengthValidator::DEFAULT_MIN_SCORE);

		wp_localize_script(self::SCRIPT_HANDLE, 'YardWarden', [
			'minLength' => $minLength,
			'minScore' => $minScore,
			'strings' => [
				'hint' => sprintf(
					__('Use at least %1$d characters and a strength of %2$d/%3$d.', 'yard-warden'),
					$minLength,
					$minScore,
					4
				),
				'tooShort' => __('Too short', 'yard-warden'),
				'requiredScore' => sprintf(
					__('Minimum strength: %1$d/%2$d.', 'yard-warden'),
					$minScore,
					4
				),
				'scoreLabels' => [
					__('Very weak', 'yard-warden'),
					__('Weak', 'yard-warden'),
					__('Fair', 'yard-warden'),
					__('Strong', 'yard-warden'),
					__('Very strong', 'yard-warden'),
				],
			],
			'zxcvbn' => $this->zxcvbnTranslations(),
		]);
	}

	/**
	 * English-keyed lookup of zxcvbn warning/suggestion strings. JS looks up
	 * the English text returned by zxcvbn and swaps in the translated value.
	 *
	 * @return array{warnings: array<string,string>, suggestions: array<string,string>}
	 */
	private function zxcvbnTranslations(): array
	{
		return [
			'warnings' => [
				'Straight rows of keys are easy to guess' =>
					__('Straight rows of keys are easy to guess', 'yard-warden'),
				'Short keyboard patterns are easy to guess' =>
					__('Short keyboard patterns are easy to guess', 'yard-warden'),
				'Use a longer keyboard pattern with more turns' =>
					__('Use a longer keyboard pattern with more turns', 'yard-warden'),
				'Repeats like "aaa" are easy to guess' =>
					__('Repeats like "aaa" are easy to guess', 'yard-warden'),
				'Repeats like "abcabcabc" are only slightly harder to guess than "abc"' =>
					__('Repeats like "abcabcabc" are only slightly harder to guess than "abc"', 'yard-warden'),
				'Sequences like abc or 6543 are easy to guess' =>
					__('Sequences like abc or 6543 are easy to guess', 'yard-warden'),
				'Recent years are easy to guess' =>
					__('Recent years are easy to guess', 'yard-warden'),
				'Dates are often easy to guess' =>
					__('Dates are often easy to guess', 'yard-warden'),
				'This is a top-10 common password' =>
					__('This is a top-10 common password', 'yard-warden'),
				'This is a top-100 common password' =>
					__('This is a top-100 common password', 'yard-warden'),
				'This is a very common password' =>
					__('This is a very common password', 'yard-warden'),
				'This is similar to a commonly used password' =>
					__('This is similar to a commonly used password', 'yard-warden'),
				'A word by itself is easy to guess' =>
					__('A word by itself is easy to guess', 'yard-warden'),
				'Names and surnames by themselves are easy to guess' =>
					__('Names and surnames by themselves are easy to guess', 'yard-warden'),
				'Common names and surnames are easy to guess' =>
					__('Common names and surnames are easy to guess', 'yard-warden'),
			],
			'suggestions' => [
				'Use a few words, avoid common phrases' =>
					__('Use a few words, avoid common phrases', 'yard-warden'),
				'No need for symbols, digits, or uppercase letters' =>
					__('No need for symbols, digits, or uppercase letters', 'yard-warden'),
				'Add another word or two. Uncommon words are better.' =>
					__('Add another word or two. Uncommon words are better.', 'yard-warden'),
				'Use a longer keyboard pattern with more turns' =>
					__('Use a longer keyboard pattern with more turns', 'yard-warden'),
				'Avoid repeated words and characters' =>
					__('Avoid repeated words and characters', 'yard-warden'),
				'Avoid sequences' =>
					__('Avoid sequences', 'yard-warden'),
				'Avoid recent years' =>
					__('Avoid recent years', 'yard-warden'),
				'Avoid years that are associated with you' =>
					__('Avoid years that are associated with you', 'yard-warden'),
				'Avoid dates and years that are associated with you' =>
					__('Avoid dates and years that are associated with you', 'yard-warden'),
				"Capitalization doesn't help very much" =>
					__("Capitalization doesn't help very much", 'yard-warden'),
				'All-uppercase is almost as easy to guess as all-lowercase' =>
					__('All-uppercase is almost as easy to guess as all-lowercase', 'yard-warden'),
				"Reversed words aren't much harder to guess" =>
					__("Reversed words aren't much harder to guess", 'yard-warden'),
				"Predictable substitutions like '@' instead of 'a' don't help very much" =>
					__("Predictable substitutions like '@' instead of 'a' don't help very much", 'yard-warden'),
			],
		];
	}
}
