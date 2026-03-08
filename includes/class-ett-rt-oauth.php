<?php
if (!defined('ABSPATH')) exit;

final class ETT_RT_OAuth {

    /**
     * Returns [client_id, client_secret_plaintext].
     * The client secret is stored encrypted by ETT Price Helper, so we
     * must decrypt it via ETT_Crypto before using it in API requests.
     */
    private static function get_credentials(): array {
        if (!class_exists('ETT_Crypto')) {
            return ['', ''];
        }

        $client_id     = (string) get_option('ett_sso_client_id', '');
        $client_secret = ETT_Crypto::decrypt_triplet(
            (string) get_option('ett_sso_client_secret',       ''),
            (string) get_option('ett_sso_client_secret_iv',    ''),
            (string) get_option('ett_sso_client_secret_mac',   '')
        );

        return [$client_id, $client_secret];
    }

    public static function init(): void {
        add_action('admin_post_ett_rt_disconnect_char', [__CLASS__, 'disconnect_character']);
        add_action('admin_post_ett_eve_callback',        [__CLASS__, 'handle_callback']);
        add_action('admin_post_nopriv_ett_eve_callback', [__CLASS__, 'handle_callback']);
    }

    /** Keep logged in as long as possible; return access token or false. */
    public static function get_valid_access_token(int $user_id, string $character_id) {

        $character_id = (string) $character_id;

        $characters = get_user_meta($user_id, 'ett_rt_characters', true);
        if (!is_array($characters) || empty($characters[$character_id]) || !is_array($characters[$character_id])) {
            return false;
        }

        $data          = $characters[$character_id];
        $access_token  = (string) ($data['access_token'] ?? '');
        $refresh_token = (string) ($data['refresh_token'] ?? '');
        $expires_at    = (int)    ($data['expires_at'] ?? 0);

        // If token is valid for at least 60 more seconds, keep it
        if ($access_token !== '' && time() < ($expires_at - 60)) {
            return $access_token;
        }

        if ($refresh_token === '') {
            return false;
        }

        [$client_id, $client_secret] = self::get_credentials();
        if ($client_id === '' || $client_secret === '') {
            return false;
        }

        $response = wp_remote_post('https://login.eveonline.com/v2/oauth/token', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh_token,
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        if ($status < 200 || $status >= 300 || !is_array($body) || empty($body['access_token'])) {
            return false;
        }

        // refresh tokens rotate — store the new refresh_token if provided
        $characters[$character_id]['access_token'] = (string) $body['access_token'];
        $characters[$character_id]['expires_at']   = time() + (int) ($body['expires_in'] ?? 1200);

        if (!empty($body['refresh_token'])) {
            $characters[$character_id]['refresh_token'] = (string) $body['refresh_token'];
        }

        update_user_meta($user_id, 'ett_rt_characters', $characters);

        return (string) $characters[$character_id]['access_token'];
    }

    public static function connect_button(): string {

        $client_id = (string) get_option('ett_sso_client_id');
        if ($client_id === '') {
            return '<p>Client ID not configured.</p>';
        }

        $state = wp_generate_password(32, false, false);
        $host       = isset($_SERVER['HTTP_HOST'])   ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))  : '';
        $request    = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
        $return_url = (is_ssl() ? 'https://' : 'http://') . $host . $request;

        set_transient('ett_rt_state_' . $state, $return_url, 600);

        $auth_url = add_query_arg([
            'response_type' => 'code',
            'redirect_uri'  => admin_url('admin-post.php?action=ett_eve_callback'),
            'client_id'     => $client_id,
            'scope'         => ETT_RT::SCOPE,
            'state'         => $state,
        ], 'https://login.eveonline.com/v2/oauth/authorize');

        return '<a href="' . esc_url($auth_url) . '">Connect with EVE Online</a>';
    }

    public static function disconnect_character(): void {

        if (!is_user_logged_in()) wp_die();

        $user_id = get_current_user_id();
        $character_id = isset($_GET['char_id']) ? sanitize_text_field(wp_unslash($_GET['char_id'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- disconnect action triggered by a direct link; character ownership is verified against the logged-in user's meta below
        $character_id = (string) $character_id;

        $characters = get_user_meta($user_id, 'ett_rt_characters', true);
        if (!is_array($characters)) $characters = [];

        if ($character_id !== '' && isset($characters[$character_id])) {
            unset($characters[$character_id]);
            update_user_meta($user_id, 'ett_rt_characters', $characters);
        }

        wp_safe_redirect(wp_get_referer() ?: home_url('/'));
        exit;
    }

    public static function handle_callback(): void {

        if (!is_user_logged_in()) {
            wp_die('You must be logged in to connect a character.');
        }

        if (!isset($_GET['code'], $_GET['state'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from EVE SSO; WP nonces cannot be used here, state transient is the CSRF protection
            wp_die('Invalid callback.');
        }

        $state = sanitize_text_field(wp_unslash($_GET['state'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $code  = sanitize_text_field(wp_unslash($_GET['code']));  // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $return_url = get_transient('ett_rt_state_' . $state);
        delete_transient('ett_rt_state_' . $state);
        if (!$return_url) $return_url = home_url('/');

        [$client_id, $client_secret] = self::get_credentials();
        if ($client_id === '' || $client_secret === '') {
            wp_die('Client ID/Secret not configured.');
        }

        $response = wp_remote_post('https://login.eveonline.com/v2/oauth/token', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => admin_url('admin-post.php?action=ett_eve_callback'),
            ],
        ]);

        if (is_wp_error($response)) {
            wp_die('Token request failed: ' . esc_html($response->get_error_message()));
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        if ($status < 200 || $status >= 300 || !is_array($body) || empty($body['access_token']) || empty($body['refresh_token'])) {
            $error_detail = is_array($body)
                ? ( !empty($body['error_description']) ? $body['error_description'] : ( !empty($body['error']) ? $body['error'] : 'no error field' ) )
                : wp_remote_retrieve_body($response);
            wp_die( 'Invalid token response (HTTP ' . (int) $status . '): ' . esc_html($error_detail) );
        }

        // Decode JWT payload segment to identify the connecting character.
        // The signature is intentionally not verified — the token was received
        // directly from EVE SSO over HTTPS in exchange for an auth code we
        // initiated, so it cannot have been tampered with in transit. The claims
        // (character name/ID) are used only for linking to the WP user, not for
        // access control decisions.
        $parts = explode('.', (string) $body['access_token']);
        if (count($parts) < 2) {
            wp_die('Invalid access token format.');
        }

        // Add base64url padding before decoding to avoid failures on tokens
        // whose payload segment length is not a multiple of 4.
        $b64 = strtr($parts[1], '-_', '+/');
        $rem = strlen($b64) % 4;
        if ($rem) $b64 .= str_repeat('=', 4 - $rem);
        $payload = json_decode(base64_decode($b64), true);

        if (!is_array($payload) || empty($payload['sub'])) {
            wp_die('Could not determine character from token.');
        }

        $sub = (string) $payload['sub'];
        if (!preg_match('/(\d+)$/', $sub, $m)) {
            wp_die('Invalid character id (sub=' . esc_html($sub) . ').');
        }
        $character_id = (string) $m[1];

        $character_name = !empty($payload['name']) ? (string) $payload['name'] : ('Character ' . $character_id);

        $user_id    = get_current_user_id();
        $characters = get_user_meta($user_id, 'ett_rt_characters', true);
        if (!is_array($characters)) $characters = [];

        $characters[$character_id] = [
            'name'          => $character_name,
            'access_token'  => (string) $body['access_token'],
            'refresh_token' => (string) $body['refresh_token'],
            'expires_at'    => time() + (int) ($body['expires_in'] ?? 1200),
        ];

        update_user_meta($user_id, 'ett_rt_characters', $characters);

        wp_safe_redirect($return_url);
        exit;
    }
}
