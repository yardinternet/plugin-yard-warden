<?php

declare(strict_types=1);

/**
 * @package Yard_Warden
 *
 * @author  Yard | Digital Agency
 *
 * Plugin Name: Yard | Warden
 * Description: Enhanced password and login security for WordPress.
 * Version: 1.0.2
 * Author: Yard | Digital Agency
 * Author URI: https://www.yard.nl
 * License: GPLv2 or later
 * Text Domain: yard_warden
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

use Yard\Logging\Log;

/**
 * If this file is called directly, abort.
 */
if (! defined('ABSPATH')) {
	exit;
}

define('YARD_WARDEN_VERSION', '1.0.2');
define('YARD_WARDEN_REQUIRED_WP_VERSION', '6.0');
define('YARD_WARDEN_PLUGIN_NAME', basename(__DIR__));
define('YARD_WARDEN_PLUGIN_FILE', __FILE__);
define('YARD_WARDEN_PLUGIN_URL', plugins_url('/', YARD_WARDEN_PLUGIN_FILE));
define('YARD_WARDEN_PLUGIN_DIR_PATH', plugin_dir_path(YARD_WARDEN_PLUGIN_FILE));
define('YARD_WARDEN_TEXT_DOMAIN', 'yard_warden');

require_once __DIR__ . '/src/Bootstrap.php';

add_action('plugins_loaded', [Yard\Warden\Bootstrap::class, 'bootstrap']);

// Fetch logger when it gets pushed from the theme
add_action(Log::WP_ACTION_SET_LOGGER, [Log::class, 'setLogger']);
