![Yard | Warden](.wordpress-org/banner-772x250.png)

# Yard | Warden

[![Code Style](https://github.com/yardinternet/plugin-yard-warden/actions/workflows/format-php.yml/badge.svg?no-cache)](https://github.com/yardinternet/plugin-yard-warden/actions/workflows/format-php.yml)

Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.4
License: EUPL-1.2
License URI: https://eupl.eu/1.2/en/

Enhanced password and login security for WordPress.

## Features

- **Password strength** via [`bjeavons/zxcvbn-php`](https://github.com/bjeavons/zxcvbn-php). Scores passwords 0–4 (default 4) based on real guessability, not arbitrary character-class rules. User inputs (login, email, display name) are fed to zxcvbn so passwords containing them score lower.
- **Minimum length** enforcement (default 16) on top of the zxcvbn score.
- **Generic login errors** to prevent username enumeration on the wp-login form. Always on — no toggle. Only applies to authentication failures; password-reset and profile-update validation errors remain verbose so users can correct their input.
- **Safer multisite onboarding.** Auto-activates new signups server-side so no activation link is emailed and the `wp-activate.php` landing page (which would print username + plaintext password) is never reached. The welcome email is rewritten to contain a one-time password-reset link instead of a generated password.
- **Login limiting** via transient-based counters across three dimensions (IP+Username, IP, Username). Locks out brute-force attempts. Admin can clear all counters via Settings > Yard Warden.

## Installation

Install from the [WordPress plugin directory](https://wordpress.org/plugins/yard-warden/), or with Composer:

```sh
composer require plugin/yard-warden
```

## Hooks

### Filters

| Filter | Default | Purpose |
| --- | --- | --- |
| `yard::warden/password/min-length` | `16` | Minimum character count. |
| `yard::warden/password/min-score` | `4` | Minimum zxcvbn score (0–4). |
| `yard::warden/login/generic-error` | `Invalid credentials.` | Replacement login error message. |
| `yard::warden/login/leaky-error-codes` | `['invalid_username', 'invalid_email', 'incorrect_password']` | WP_Error codes to rewrite. |
| `yard::warden/onboarding/welcome-subject` | `Welcome to <site>!` | Multisite welcome email subject. |
| `yard::warden/onboarding/welcome-body` | reset-link body | Full welcome email body. Receives `$user`, `$resetUrl`, original body. |
| `yard::warden/limit-login/enabled` | `true` | Set to `false` to disable login limiting entirely. |
| `yard::warden/limit-login/client-ip` | `REMOTE_ADDR` | Override IP detection (e.g. for reverse proxies). |
| `yard::warden/limit-login/error-message` | `Too many failed login attempts…` | Lockout error shown to the user. |
| `yard::warden/limit-login/skip-error-codes` | `['expired_session']` | WP_Error codes that do not count as failed attempts. |
| `yard::warden/limit-login/threshold/{dimension}` | `5` / `50` / `3` | Attempts before lockout per dimension (`ip_user` / `ip` / `username`). |
| `yard::warden/limit-login/window/{dimension}` | `300` / `3600` / `1500` | Counting window in seconds per dimension. |
| `yard::warden/limit-login/lockout/{dimension}` | `300` / `3600` / `1500` | Lockout duration in seconds per dimension. |

## Policy override example

```php
add_filter('yard::warden/password/min-score', function (int $score) {
    return max($score, 4);
});
```

## About us

[![banner](https://raw.githubusercontent.com/yardinternet/.github/refs/heads/main/profile/assets/small-banner-github.svg)](https://www.yard.nl/werken-bij/)
