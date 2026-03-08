<?php
if (!defined('ABSPATH')) exit;

final class ETT_RT_ExtDB {

    public static function init(): void {
        add_action('wp_ajax_ett_rt_test_extdb', [__CLASS__, 'ajax_test_connection']);
    }

    /**
     * Whether the ETT Price Helper external DB is configured.
     * Returns false (not just unconfigured) if Price Helper is not active.
     */
    public static function is_configured(): bool {
        if (!class_exists('ETT_ExternalDB')) return false;
        return ETT_ExternalDB::is_configured();
    }

    /**
     * Get a wpdb connection using ETT Price Helper's stored credentials.
     * Returns WP_Error if Price Helper is not active or DB is not configured.
     */
    public static function get_db() {
        if (!class_exists('ETT_ExternalDB') || !class_exists('ETT_Crypto')) {
            return new \WP_Error(
                'ett_rt_price_helper_missing',
                'ETT Price Helper plugin is not active. Please install and activate it.'
            );
        }

        if (!ETT_ExternalDB::is_configured()) {
            return new \WP_Error(
                'ett_rt_extdb_not_configured',
                'External DB is not configured in ETT Price Helper settings.'
            );
        }

        $s = ETT_ExternalDB::get();

        // Decrypt the password stored by Price Helper
        $pass = ETT_Crypto::decrypt_triplet(
            (string) ($s['pass_enc'] ?? ''),
            (string) ($s['pass_iv']  ?? ''),
            (string) ($s['pass_mac'] ?? '')
        );

        $host = (string) $s['host'];
        $port = trim((string) ($s['port'] ?? '3306'));
        if ($port !== '' && $port !== '3306') {
            $host .= ':' . $port;
        }

        $prev = $GLOBALS['wpdb']->show_errors;
        $GLOBALS['wpdb']->show_errors = false;

        $db = new \wpdb((string) $s['user'], $pass, (string) $s['dbname'], $host);
        $db->show_errors(false);
        $db->suppress_errors(true);

        $GLOBALS['wpdb']->show_errors = $prev;

        if (!empty($db->error)) {
            return new \WP_Error('ett_rt_extdb_connect_failed', $db->error);
        }

        return $db;
    }

    /** AJAX: test the Price Helper external DB connection (returns JSON: ok, message). */
    public static function ajax_test_connection(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json(['ok' => false, 'message' => 'Forbidden'], 403);
        }
        check_ajax_referer('ett_rt_test_extdb', 'nonce');

        $db = self::get_db();
        if (is_wp_error($db)) {
            wp_send_json(['ok' => false, 'message' => $db->get_error_message()]);
        }

        try {
            $v = $db->get_var('SELECT 1');
            if ((string) $v === '1') {
                wp_send_json(['ok' => true, 'message' => 'Connected']);
            }
            wp_send_json(['ok' => false, 'message' => 'Connected but test query failed']);
        } catch (\Throwable $e) {
            wp_send_json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }
}
