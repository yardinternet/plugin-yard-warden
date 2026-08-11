=== Yard | Warden ===
Contributors: yarddigitalagency
Tags: security, password, login, brute force, multisite
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enhanced password and login security for WordPress.

== Description ==

Yard Warden hardens core WordPress password and login flows:

* **Password strength** via [zxcvbn-php](https://github.com/bjeavons/zxcvbn-php). Scores passwords 0-4 (default 4) based on real guessability, not arbitrary character-class rules. User inputs (login, email, display name) are fed to zxcvbn so passwords containing them score lower.
* **Minimum length** enforcement (default 16) on top of the zxcvbn score.
* **Generic login errors** to prevent username enumeration on the wp-login form. Always on, no toggle. Only applies to authentication failures; password-reset and profile-update validation errors remain verbose so users can correct their input.
* **Safer multisite onboarding.** Auto-activates new signups server-side so no activation link is emailed and the wp-activate.php landing page (which would print username + plaintext password) is never reached. The welcome email is rewritten to contain a one-time password-reset link instead of a generated password.
* **Login limiting** via transient-based counters across three dimensions (IP+Username, IP, Username). Locks out brute-force attempts. Admin can clear all counters via Settings > Yard Warden.

= Filters =

* `yard::warden/password/min-length` (default `16`) - Minimum character count.
* `yard::warden/password/min-score` (default `4`) - Minimum zxcvbn score (0-4).
* `yard::warden/login/generic-error` (default `Invalid credentials.`) - Replacement login error message.
* `yard::warden/login/leaky-error-codes` (default `['invalid_username', 'invalid_email', 'incorrect_password']`) - WP_Error codes to rewrite.
* `yard::warden/onboarding/welcome-subject` (default `Welcome to <site>!`) - Multisite welcome email subject.
* `yard::warden/onboarding/welcome-body` (default reset-link body) - Full welcome email body. Receives `$user`, `$resetUrl`, original body.
* `yard::warden/limit-login/enabled` (default `true`) - Set to `false` to disable login limiting entirely.
* `yard::warden/limit-login/client-ip` (default `REMOTE_ADDR`) - Override IP detection (e.g. for reverse proxies).
* `yard::warden/limit-login/error-message` (default `Too many failed login attempts...`) - Lockout error shown to the user.
* `yard::warden/limit-login/skip-error-codes` (default `['expired_session']`) - WP_Error codes that do not count as failed attempts.
* `yard::warden/limit-login/threshold/{dimension}` (default `5` / `50` / `3`) - Attempts before lockout per dimension (`ip_user` / `ip` / `username`).
* `yard::warden/limit-login/window/{dimension}` (default `300` / `3600` / `1500`) - Counting window in seconds per dimension.
* `yard::warden/limit-login/lockout/{dimension}` (default `300` / `3600` / `1500`) - Lockout duration in seconds per dimension.

= Policy override example =

`
add_filter('yard::warden/password/min-score', function (int $score) {
    return max($score, 4);
});
`

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/yard-warden`, or install through the Plugins screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Configure login-limit thresholds via Settings > Yard Warden, or override any default via the filters above.

== Frequently Asked Questions ==

= Does this add password expiry or forced rotation? =

No. Forced password rotation is not implemented; it pushes users toward weaker, predictable passwords rather than improving security.

= Can I disable login limiting? =

Yes, via the `yard::warden/limit-login/enabled` filter.

== Changelog ==

= 1.0.3 =
* Minimum WordPress version raised to 6.3.
* Bundled Dutch translations removed; translations are now served through translate.wordpress.org.
* WP_Error codes prefixed with yard_warden_ to avoid collisions with other plugins.
* Login-limit transient keys prefixed with yard_warden_ll_.
* Welcome-email opt-out query flag renamed to yard_warden_disable_welcome_email.

= 1.0.2 =
* Text domain adjusted to yard-warden.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.3 =
Login attempt counters and active lockouts are reset on upgrade. If you use the disable_welcome_email query flag, rename it to yard_warden_disable_welcome_email.

= 1.0.2 =
Text domain change only, no action required.
