/**
 * Yard Warden — password UI.
 *
 * Replaces WP's default password hint and strength indicator with zxcvbn-backed
 * feedback that matches the server-side StrengthValidator.
 */
(function () {
	'use strict';

	if (typeof window.YardWarden === 'undefined') {
		return;
	}

	const cfg = window.YardWarden;
	const HINT_SELECTORS = [
		'.user-pass1-wrap .description',
		'.password-input-wrapper + .description',
		'p.description.indicator-hint',
	];
	const PASSWORD_SELECTORS = ['#pass1', '#pass1-text', '#user_pass'];
	const USER_INPUT_SELECTORS = [
		'#user_login',
		'input[name="user_login"]',
		'#rp_login',
		'input[name="rp_login"]',
		'#email',
		'#user_email',
		'#display_name',
		'#first_name',
		'#last_name',
	];

	function collectUserInputs() {
		return [
			...new Set(
				USER_INPUT_SELECTORS.map((sel) => document.querySelector(sel))
					.filter((el) => el && el.value)
					.map((el) => el.value),
			),
		];
	}

	function translate(dictKey, text) {
		if (!text) {
			return '';
		}
		const dict = (cfg.zxcvbn && cfg.zxcvbn[dictKey]) || {};
		return dict[text] || text;
	}

	function scoreClass(score, length) {
		if (length < cfg.minLength) {
			return 'short';
		}
		if (score >= cfg.minScore) {
			return 'strong';
		}
		if (score >= 2) {
			return 'good';
		}
		return 'bad';
	}

	function formatFeedback(result, password) {
		const length = password.length;

		if (length === 0) {
			return '';
		}
		if (length < cfg.minLength) {
			return `${cfg.strings.tooShort} (${length}/${cfg.minLength}) - ${cfg.strings.hint}`;
		}

		const label = cfg.strings.scoreLabels[result.score] || '';
		const parts = [`${label} (${result.score}/${cfg.maxScore})`];

		if (result.score < cfg.minScore) {
			parts.push(cfg.strings.requiredScore);
		}

		if (result.feedback && result.feedback.warning) {
			parts.push(translate('warnings', result.feedback.warning));
		}

		return parts.join(' — ');
	}

	function updateHint() {
		const selector = HINT_SELECTORS.join(', ');
		document.querySelectorAll(selector).forEach((el) => {
			el.textContent = cfg.strings.hint;
		});
	}

	function findPasswordField() {
		for (const sel of PASSWORD_SELECTORS) {
			const el = document.querySelector(sel);
			if (el) {
				return el;
			}
		}
		return null;
	}

	function setResultState(resultEl, className, text) {
		resultEl.classList.remove('short', 'bad', 'good', 'strong', 'empty');
		if (className) {
			resultEl.classList.add(className);
		}
		resultEl.textContent = text;
	}

	function bindMeter() {
		const passwordEl = findPasswordField();
		const resultEl = document.getElementById('pass-strength-result');

		if (!passwordEl || !resultEl || typeof window.zxcvbn !== 'function') {
			return false;
		}

		const render = () => {
			const value = passwordEl.value || '';

			if (value === '') {
				setResultState(resultEl, 'empty', '');
				return;
			}

			const result = window.zxcvbn(value, collectUserInputs());
			setResultState(
				resultEl,
				scoreClass(result.score, value.length),
				formatFeedback(result, value),
			);
		};

		['input', 'keyup', 'change'].forEach((evt) => {
			passwordEl.addEventListener(evt, render);
		});
		render();

		return true;
	}

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
			return;
		}
		document.addEventListener('DOMContentLoaded', fn, { once: true });
	}

	ready(() => {
		updateHint();

		if (bindMeter()) {
			return;
		}

		// The meter DOM + zxcvbn global can arrive after DOMContentLoaded on the
		// wp-login reset screen. Retry briefly until both are present.
		let attempts = 0;
		const timer = setInterval(() => {
			attempts += 1;
			if (bindMeter() || attempts > 20) {
				clearInterval(timer);
			}
		}, 250);
	});
})();
