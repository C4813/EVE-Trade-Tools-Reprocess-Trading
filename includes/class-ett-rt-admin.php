<?php
if (!defined('ABSPATH')) exit;

final class ETT_RT_Admin {

    public static function init(): void {
        add_action('ett_admin_tabs',         [__CLASS__, 'register_tab']);
        add_action('admin_enqueue_scripts',  [__CLASS__, 'enqueue_assets']);
    }

    public static function register_tab(): void {
        if (!class_exists('ETT_Admin')) return;

        ETT_Admin::register_tab(
            'reprocess-trading',
            'Reprocess Trading',
            [__CLASS__, 'render_tab']
        );
    }

    public static function enqueue_assets(string $hook): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = sanitize_key(wp_unslash($_GET['page'] ?? ''));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab  = sanitize_key(wp_unslash($_GET['tab']  ?? ''));

        if ($hook !== 'toplevel_page_ett-price-helper') return;
        if ($tab !== 'reprocess-trading') return;

        $url = ETT_RT::url();

        wp_enqueue_style('ett-rt-admin', $url . 'assets/admin.css', [], ETT_RT_VERSION);
        wp_enqueue_script('ett-rt-admin', $url . 'assets/admin.js', ['jquery'], ETT_RT_VERSION, true);

        wp_localize_script('ett-rt-admin', 'ETT_RT_Admin', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('ett_rt_test_extdb'),
            'configured' => ETT_RT_ExtDB::is_configured() ? 1 : 0,
        ]);
    }

    public static function render_tab(): void {
        include ETT_RT::dir() . 'templates/admin/settings-page.php';
    }
}
