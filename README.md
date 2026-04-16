# Yard Warden

Enhanced password and login security for WordPress.

## Features

- **Password strength** via [`bjeavons/zxcvbn-php`](https://github.com/bjeavons/zxcvbn-php). Scores passwords 0–4 (default 4) based on real guessability, not arbitrary character-class rules. User inputs (login, email, display name) are fed to zxcvbn so passwords containing them score lower.
- **Minimum length** enforcement (default 16) on top of the zxcvbn score.
- **Generic login errors** to prevent username enumeration on the wp-login form. Always on — no toggle. Only applies to authentication failures; password-reset and profile-update validation errors remain verbose so users can correct their input.
- **Safer multisite onboarding.** Auto-activates new signups server-side so no activation link is emailed and the `wp-activate.php` landing page (which would print username + plaintext password) is never reached. The welcome email is rewritten to contain a one-time password-reset link instead of a generated password.
- **Login limiting** via transient-based counters across three dimensions (IP+Username, IP, Username). Locks out brute-force attempts. Admin can clear all counters via Settings > Yard Warden.

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

## Policy override example

```php
add_filter('yard::warden/password/min-score', function (int $score) {
    return max($score, 4);
});
```
