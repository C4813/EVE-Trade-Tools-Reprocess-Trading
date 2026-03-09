<?php
/*
Plugin Name: EVE Trade Tools Reprocess Trading
Description: EVE Online SSO + Skills & Standings display for reprocess trading.
Version: 1.1.0
Author: C4813
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: ett-price-helper
*/

if (!defined('ABSPATH')) exit;

define('ETT_RT_VERSION', '1.1.0');
define('ETT_RT_PATH', plugin_dir_path(__FILE__));
define('ETT_RT_URL', plugin_dir_url(__FILE__));

/**
 * Block activation entirely if ETT Price Helper is not active.
 * is_plugin_active() requires pluggable.php which is not yet loaded at
 * register_activation_hook time, so we check for the defining class instead.
 */
register_activation_hook(__FILE__, function () {
    if (!class_exists('ETT_Admin') || !class_exists('ETT_ExternalDB') || !class_exists('ETT_Crypto')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            '<p><strong>EVE Trade Tools Reprocess Trading</strong> requires '
            . '<strong>EVE Trade Tools Price Helper</strong> to be installed and active before it can be activated.</p>'
            . '<p><a href="' . esc_url(admin_url('plugins.php')) . '">&larr; Back to Plugins</a></p>',
            'Activation failed',
            ['back_link' => false]
        );
    }
});

/**
 * Show a runtime admin notice if Price Helper is deactivated after RT is already active.
 * This covers the edge case where Price Helper is disabled post-activation.
 */
add_action('admin_notices', function () {
    if (class_exists('ETT_Admin')) return;
    if (!current_user_can('activate_plugins')) return;
    echo '<div class="notice notice-error">'
       . '<p><strong>EVE Trade Tools Reprocess Trading</strong> requires '
       . '<strong>EVE Trade Tools Price Helper</strong> to be installed and activated. '
       . 'Reprocess Trading has no settings page and cannot connect EVE characters '
       . 'until Price Helper is active. '
       . '<a href="' . esc_url(admin_url('plugins.php')) . '">Manage plugins &rarr;</a>'
       . '</p></div>';
});

require_once ETT_RT_PATH . 'includes/class-ett-rt.php';
require_once ETT_RT_PATH . 'includes/class-ett-rt-extdb.php';
require_once ETT_RT_PATH . 'includes/class-ett-rt-oauth.php';
require_once ETT_RT_PATH . 'includes/class-ett-rt-render.php';
require_once ETT_RT_PATH . 'includes/class-ett-rt-admin.php';

add_action('plugins_loaded', function () {
    ETT_RT::init();
    ETT_RT_ExtDB::init();
    ETT_RT_OAuth::init();
    ETT_RT_Admin::init();
});
