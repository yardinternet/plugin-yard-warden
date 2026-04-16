<?php

declare(strict_types=1);

namespace Yard\Warden\LimitLogin;

use Yard\Logging\Log;

/**
 * Registers a native WordPress admin page under Settings > Yard Warden
 * with a button to clear all login-limit transients.
 */
class LimitLoginAdminPage
{
    private const NONCE_ACTION = 'yard_warden_clear_limit_login';
    private const MENU_SLUG = 'yard-warden';

    /** @var Limiter */
    private $limiter;

    public function __construct(Limiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerPage']);
        add_action('admin_init', [$this, 'handleClearRequest']);
    }

    public function registerPage(): void
    {
        add_options_page(
            __('Yard Warden', YARD_WARDEN_TEXT_DOMAIN),
            __('Yard Warden', YARD_WARDEN_TEXT_DOMAIN),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    public function handleClearRequest(): void
    {
        if (! isset($_POST['yard_warden_clear_limit_login'])) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        check_admin_referer(self::NONCE_ACTION);

        $deleted = $this->limiter->clearAll();

        Log::warning(sprintf(
            'Yard Warden: admin cleared all login limits (%d transient rows removed)',
            $deleted
        ));

        add_settings_error(
            self::MENU_SLUG,
            'limits_cleared',
            __('All login limits have been cleared.', YARD_WARDEN_TEXT_DOMAIN),
            'success'
        );

        set_transient('settings_errors', get_settings_errors(), 30);

        wp_safe_redirect(add_query_arg('settings-updated', 'true', menu_page_url(self::MENU_SLUG, false)));
        exit;
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['settings-updated'])) {
            settings_errors(self::MENU_SLUG);
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Yard Warden', YARD_WARDEN_TEXT_DOMAIN); ?></h1>

            <div class="card" style="max-width: 600px;">
                <h2><?php esc_html_e('Login Limiting', YARD_WARDEN_TEXT_DOMAIN); ?></h2>
                <p><?php esc_html_e('If a legitimate user has been locked out due to too many failed login attempts, you can clear all login limits below.', YARD_WARDEN_TEXT_DOMAIN); ?></p>

                <form method="post">
                    <?php wp_nonce_field(self::NONCE_ACTION); ?>
                    <?php submit_button(
                        __('Clear All Login Limits', YARD_WARDEN_TEXT_DOMAIN),
                        'secondary',
                        'yard_warden_clear_limit_login',
                        false
                    ); ?>
                </form>
            </div>
        </div>
        <?php
    }
}
