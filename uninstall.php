<?php
/**
 * Uninstall — EVE Trade Tools Reprocess Trading
 *
 * Runs when the plugin is deleted via WP Admin → Plugins.
 * Removes all WordPress-side data owned by this plugin:
 *   - Per-user EVE character data (tokens, names) stored in user meta
 *   - OAuth state transients created during the character-connect flow
 *   - Cached ESI character data (skills/standings) stored as transients
 *
 * This plugin stores no options of its own — it reads SSO credentials
 * that are owned and managed by ETT Price Helper.
 *
 * Nothing outside WordPress (e.g. the external database managed by
 * ETT Price Helper) is ever touched here.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

// ── User meta ─────────────────────────────────────────────────────────────────
// Each user who connected an EVE character has an 'ett_rt_characters' entry
// containing their character names, OAuth tokens, and cached skill/standings.

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- direct deletion required in uninstall; caching irrelevant
$wpdb->delete($wpdb->usermeta, ['meta_key' => 'ett_rt_characters'], ['%s']);

// ── Transients ────────────────────────────────────────────────────────────────
// Two families of transients:
//   ett_rt_state_{state}    — short-lived OAuth state tokens (10 min)
//   ett_rt_char_{char_id}   — ESI skills/standings cache per character (1 hr)

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- direct deletion required in uninstall; caching irrelevant
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '\_transient\_ett\_rt\_%'
        OR option_name LIKE '\_transient\_timeout\_ett\_rt\_%'"
);
