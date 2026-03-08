<?php
if (!defined('ABSPATH')) exit;

final class ETT_RT {

    public const SCOPE = 'esi-skills.read_skills.v1 esi-characters.read_standings.v1';

    public static function init(): void {
        // Cache bypass for shortcode pages + OAuth callback
        add_action('wp', [__CLASS__, 'maybe_bypass_cache'], 1);

        // Frontend assets + shortcode
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
        add_shortcode('ett_esi_reprocess_profile', [__CLASS__, 'shortcode_profile']);
        add_shortcode('ett_trading_tool', [__CLASS__, 'shortcode_trading_tool']);
        add_action('wp_ajax_ett_rt_generate_list',        [__CLASS__, 'ajax_generate_list']);
        add_action('wp_ajax_nopriv_ett_rt_generate_list', [__CLASS__, 'ajax_generate_list']);
        add_action('wp_ajax_ett_rt_get_characters',       [__CLASS__, 'ajax_get_characters']);
    }

    public static function dir(): string {
        return (string) ETT_RT_PATH;
    }

    public static function url(): string {
        return (string) ETT_RT_URL;
    }

    /** @return array<int,string> */
    public static function skill_ids(): array {
        return [
            16622 => 'Accounting',
            3446  => 'Broker Relations',
            16597 => 'Advanced Broker Relations',
            3359  => 'Connections',
            3357  => 'Diplomacy',
            12196 => 'Scrapmetal Processing',
        ];
    }

    /** @return array<int,string> */
    public static function entities(): array {
        return [
            // Factions
            500001  => 'Caldari State',
            500003  => 'Amarr Empire',
            500002  => 'Minmatar Republic',
            500004  => 'Gallente Federation',

            // Corporations
            1000035 => 'Caldari Navy',
            1000086 => 'Emperor Family',
            1000049 => 'Brutor Tribe',
            1000057 => 'Boundless Creations',
            1000120 => 'Federation Navy',
        ];
    }

    public static function maybe_bypass_cache(): void {

        // Bypass cache for the OAuth callback handler (admin-post should already be uncached, but be explicit)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing parameter, not form data
        if (!empty($_GET['action']) && $_GET['action'] === 'ett_eve_callback') {
            self::nocache_headers();
            self::litespeed_nocache();
            return;
        }

        if (!is_singular()) return;

        global $post;
        if (!($post instanceof WP_Post)) return;

        $content = (string) $post->post_content;

        // If page contains our shortcode, make it non-cacheable
        if (has_shortcode($content, 'ett_esi_reprocess_profile') || has_shortcode($content, 'ett_trading_tool')) {
            self::nocache_headers();
            self::litespeed_nocache();
        }
    }

    public static function nocache_headers(): void {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (!defined('DONOTCACHEDB')) define('DONOTCACHEDB', true);
        if (!defined('DONOTCACHEOBJECT')) define('DONOTCACHEOBJECT', true);
        nocache_headers();
    }

    public static function litespeed_nocache(): void {
        if (!defined('LSCACHE_NO_CACHE')) define('LSCACHE_NO_CACHE', true);
        @header('X-LiteSpeed-Cache-Control: no-cache');
        @header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        @header('Pragma: no-cache');
    }

    public static function enqueue_frontend_assets(): void {
        if (!is_singular()) return;

        global $post;
        if (!($post instanceof WP_Post)) return;

        $content = (string) $post->post_content;
        if (!has_shortcode($content, 'ett_esi_reprocess_profile') && !has_shortcode($content, 'ett_trading_tool')) return;

        $url = self::url();
        wp_enqueue_style('ett-rt-frontend', $url . 'assets/frontend.css', [], ETT_RT_VERSION);
        wp_enqueue_script('ett-rt-frontend', $url . 'assets/frontend.js', [], ETT_RT_VERSION, true);
        wp_localize_script('ett-rt-frontend', 'ettRt', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ett_rt_generate_list'),
        ]);
    }

    public static function shortcode_profile(): string {

        if (!is_user_logged_in()) {
            return '<p>You must be logged in.</p>';
        }

        $user_id    = get_current_user_id();
        $characters = get_user_meta($user_id, 'ett_rt_characters', true);
        if (!is_array($characters)) $characters = [];

        $count = count($characters);

        ob_start();
        include self::dir() . 'templates/frontend/reprocess-profile.php';
        return (string) ob_get_clean();
    }

    public static function shortcode_trading_tool(): string {

        if (!is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink());
            return '<p class="ett-auth-notice">You must be <a href="' . esc_url($login_url) . '">logged in</a> and have at least one authenticated EVE character to use the trading tool. Character standings are required to calculate accurate reprocessing taxes and brokerage fees.</p>';
        }

        $characters = get_user_meta(get_current_user_id(), 'ett_rt_characters', true);
        if (empty($characters) || !is_array($characters)) {
            return '<p class="ett-auth-notice">No authenticated characters found. Please authenticate at least one EVE Online character before using the trading tool — character standings are required to calculate accurate reprocessing taxes and brokerage fees.</p>';
        }

        ob_start();
        include self::dir() . 'templates/frontend/trading-tool.php';
        return (string) ob_get_clean();
    }

    public static function ajax_generate_list(): void {
        check_ajax_referer('ett_rt_generate_list', 'nonce');

        $slug            = sanitize_key((string) ($_POST['market_group']    ?? 'ship_equipment'));
        $hub_key         = sanitize_key((string) ($_POST['trade_hub']       ?? 'jita'));
        $exclude_capital = (sanitize_key(wp_unslash($_POST['exclude_capital'] ?? 'yes')) === 'yes');
        $meta_only       = (sanitize_key(wp_unslash($_POST['meta_only']       ?? 'yes')) === 'yes');

        $valid_hubs = ['jita', 'amarr', 'rens', 'dodixie', 'hek'];
        if (!in_array($hub_key, $valid_hubs, true)) $hub_key = 'jita';

        $group_name = self::trading_group_name($slug);
        if ($group_name === '') {
            wp_send_json_error('Invalid market group.', 400);
        }

        $db = ETT_RT_ExtDB::get_db();
        if (is_wp_error($db)) {
            wp_send_json_error($db->get_error_message(), 500);
        }

        // Find root group ID by name
        $root_id = (int) $db->get_var(
            $db->prepare("SELECT market_group_id FROM ett_invMarketGroups WHERE name = %s LIMIT 1", $group_name)
        );

        if ($root_id === 0) {
            wp_send_json_error('Market group "' . $group_name . '" not found in database.', 404);
        }

        // Expand root into all descendant group IDs
        $all_group_ids = self::expand_market_groups($db, $root_id);

        // Exclude capital market groups by name prefix
        if ($exclude_capital && !empty($all_group_ids)) {
            $safe        = implode(',', array_map('intval', $all_group_ids));
            $capital_ids = $db->get_col(
                "SELECT market_group_id FROM ett_invMarketGroups WHERE market_group_id IN ($safe) AND name LIKE 'Capital%'"
            );
            $capital_ids   = array_map('intval', $capital_ids ?: []);
            $all_group_ids = array_values(array_diff($all_group_ids, $capital_ids));
        }

        if (empty($all_group_ids)) {
            wp_send_json_success(['items' => []]);
            return;
        }

        $safe = implode(',', array_map('intval', $all_group_ids));

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        $rows = $db->get_results(
            "SELECT t.type_id, t.name,
                CASE
                    WHEN mg.name = 'Tech II'                         THEN 'T2'
                    WHEN mg.name IN ('Faction','Deadspace','Officer','Storyline','Abyssal','Limited','Premium','Tech III','Structure Faction','Structure Tech I','Structure Tech II') THEN mg.name
                    WHEN mo.product_type_id IS NOT NULL               THEN 'T1'
                    WHEN mg.name = 'Tech I'                           THEN 'Meta'
                    ELSE 'Other'
                END AS meta_tier
             FROM ett_invTypes t
             LEFT JOIN ett_invMetaTypes mt ON mt.type_id = t.type_id
             LEFT JOIN ett_invMetaGroups mg ON mg.meta_group_id = mt.meta_group_id
             LEFT JOIN ett_industryActivityProducts mo ON mo.product_type_id = t.type_id
             WHERE t.published = 1
               AND t.market_group_id IN ($safe)
             ORDER BY t.name ASC",
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

        $rows = is_array($rows) ? $rows : [];

        $filtered = [];
        foreach ($rows as $r) {
            $tier = (string) ($r['meta_tier'] ?? 'Other');
            if ($meta_only && $tier !== 'Meta') continue;
            $filtered[] = $r;
        }

        $stack_size   = max(1, intval(wp_unslash($_POST['stack_size']    ?? 100)));
        $character_id = sanitize_text_field(wp_unslash((string) ($_POST['character_id'] ?? '')));
        $sell_to      = sanitize_key(wp_unslash((string) ($_POST['sell_to'] ?? 'sell_orders')));
        $min_margin   = floatval(wp_unslash($_POST['min_margin']   ?? 5));
        $max_margin   = floatval(wp_unslash($_POST['max_margin']   ?? 25));
        $min_volume   = floatval(wp_unslash($_POST['min_volume']   ?? 1));

        // Scrapmetal Processing skill level for yield calculation
        $scrapmetal_skill = 0;
        $sales_tax        = 0.075;
        $broker_fee       = 0.03;
        $reproc_tax       = 0.05; // default 5%; reduced by effective corp standing
        $adv_broker_rel   = 0;

        if ($character_id !== '' && is_user_logged_in()) {
            $char_data        = self::get_character_data($character_id);
            $skill_levels     = $char_data['skill_levels'];
            $standings        = $char_data['standings'];

            $scrapmetal_skill = (int) ($skill_levels[12196] ?? 0);
            $accounting       = (int) ($skill_levels[16622] ?? 0);
            $broker_rel       = (int) ($skill_levels[3446]  ?? 0);
            $adv_broker_rel   = (int) ($skill_levels[16597] ?? 0);
            $connections      = (int) ($skill_levels[3359]  ?? 0);
            $diplomacy        = (int) ($skill_levels[3357]  ?? 0);

            // Sales tax: 7.5% base, each Accounting level reduces by 11%, minimum 3.37%
            $sales_tax = max(0.0337, 0.075 * pow(0.89, $accounting));

            // Broker fee for this hub
            $hub_entities = self::hub_entities();
            $entities     = $hub_entities[$hub_key] ?? null;
            if ($entities) {
                $faction_id   = (int) $entities['faction'];
                $corp_id      = (int) $entities['corp'];
                $base_faction = max(-10.0, min(10.0, round((float) ($standings[$faction_id] ?? 0.0), 2)));
                $base_corp    = max(-10.0, min(10.0, round((float) ($standings[$corp_id]    ?? 0.0), 2)));
                $broker_fee   = max(0.01, round(
                    0.03 - (0.003 * $broker_rel) - (0.0003 * $base_faction) - (0.0002 * $base_corp),
                    4
                ));

                // Reprocessing tax: 5% of adjusted output value, reduced by effective corp standing
                if (abs($base_corp) < 0.00001) {
                    $reproc_tax = 0.05;
                } else {
                    $skill_for_tax = ($base_corp < 0.0) ? $diplomacy : $connections;
                    $eff = round($base_corp + (10 - $base_corp) * (0.04 * $skill_for_tax), 2);
                    if ($eff <= 0.0)     $reproc_tax = 0.05;
                    elseif ($eff < 6.67) $reproc_tax = round(0.05 * (1 - ($eff / 6.67)), 4);
                    else                 $reproc_tax = 0.0;
                }
            }
        }
        $relist_fees      = sanitize_key((string) ($_POST['relist_brokerage'] ?? 'no')) === 'yes';
        $order_updates    = max(0, intval(wp_unslash($_POST['order_updates'] ?? 0)));
        // Scrapmetal reprocessing yield: NPC station base is 50%, Scrapmetal Processing adds 2% per level.
        // Formula: 0.50 × (1 + skill × 0.02) → level 5 = 55%, level 0 = 50%.
        $yield_multiplier = 0.50 * (1.0 + ($scrapmetal_skill * 0.02));

        // Fetch buy prices and regional volume for filtered type_ids
        $prices  = [];
        $volumes = [];
        if (!empty($filtered)) {
            $type_ids = array_map(fn($r) => (int) $r['type_id'], $filtered);
            $safe_ids = implode(',', $type_ids);
            $safe_hub = $db->prepare('%s', $hub_key);

            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
            $price_rows = $db->get_results(
                "SELECT type_id, buy_max FROM ett_prices WHERE hub_key = $safe_hub AND type_id IN ($safe_ids)",
                ARRAY_A
            );

            $oldest_fetched_at = $db->get_var(
                "SELECT MIN(fetched_at) FROM ett_prices WHERE hub_key = $safe_hub AND type_id IN ($safe_ids)"
            );

            $volume_rows = $db->get_results(
                "SELECT type_id, avg_daily_volume FROM ett_market_history WHERE hub_key = $safe_hub AND type_id IN ($safe_ids)",
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

            foreach ($price_rows as $p) {
                $prices[(int) $p['type_id']] = $p['buy_max'];
            }
            foreach ($volume_rows as $v) {
                $volumes[(int) $v['type_id']] = $v['avg_daily_volume'];
            }
        }

        // Fetch reprocessing materials and portionSize for all filtered type_ids
        $materials_by_item = [];
        $all_material_ids  = [];
        $portion_sizes     = [];
        if (!empty($filtered)) {
            $type_ids = array_map(fn($r) => (int) $r['type_id'], $filtered);
            $safe_ids = implode(',', $type_ids);
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
            $mat_rows = $db->get_results(
                "SELECT type_id, material_type_id, quantity FROM ett_invTypeMaterials WHERE type_id IN ($safe_ids)",
                ARRAY_A
            );

            // portionSize: how many items constitute one reprocessing batch (e.g. 100 for ammo).
            // invTypeMaterials.quantity is the yield per batch, NOT per individual item.
            $portion_rows = $db->get_results(
                "SELECT type_id, portionSize FROM ett_invTypes WHERE type_id IN ($safe_ids)",
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

            foreach ($portion_rows as $p) {
                $portion_sizes[(int) $p['type_id']] = max(1, (int) $p['portionSize']);
            }

            foreach ($mat_rows as $m) {
                $tid = (int) $m['type_id'];
                $mid = (int) $m['material_type_id'];
                $materials_by_item[$tid][] = ['mid' => $mid, 'qty' => (int) $m['quantity']];
                $all_material_ids[$mid]    = true;
            }
        }

        // Fetch material prices (sell_min for sell orders, buy_max for buy orders)
        $material_prices   = [];
        $adjusted_prices   = [];
        if (!empty($all_material_ids)) {
            $safe_mat_ids = implode(',', array_keys($all_material_ids));
            $price_col    = ($sell_to === 'buy_orders') ? 'buy_max' : 'sell_min';
            $safe_hub     = $db->prepare('%s', $hub_key);
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
            $mat_price_rows = $db->get_results(
                "SELECT type_id, {$price_col} AS price FROM ett_prices WHERE hub_key = $safe_hub AND type_id IN ($safe_mat_ids)",
                ARRAY_A
            );

            // Adjusted prices for reprocessing tax calculation
            $adj_price_rows = $db->get_results(
                "SELECT type_id, adjusted_price FROM ett_adjusted_prices WHERE type_id IN ($safe_mat_ids)",
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
            foreach ($mat_price_rows as $p) {
                $material_prices[(int) $p['type_id']] = (float) $p['price'];
            }
            foreach ($adj_price_rows as $p) {
                $adjusted_prices[(int) $p['type_id']] = (float) $p['adjusted_price'];
            }
        }

        $items = [];
        foreach ($filtered as $r) {
            $type_id   = (int) $r['type_id'];
            $buy_price = isset($prices[$type_id])  ? (float) $prices[$type_id]  : null;
            $volume    = isset($volumes[$type_id]) ? (float) $volumes[$type_id] : null;

            // Calculate reprocessed value for stack_size items, then divide back to per-item.
            // invTypeMaterials.quantity is per reprocessing batch (portionSize items).
            // We can only reprocess whole batches, so num_batches = floor(stack_size / portionSize).
            $reprocess_value = null;
            $mats = $materials_by_item[$type_id] ?? [];
            if (!empty($mats)) {
                $portion_size = $portion_sizes[$type_id] ?? 1;
                $num_batches  = (int) floor($stack_size / $portion_size);

                // Can't reprocess if stack is smaller than one batch
                if ($num_batches < 1) continue;

                $stack_total          = 0.0;
                $stack_adjusted_value = 0.0;
                foreach ($mats as $mat) {
                    // mat['qty'] is the raw yield per batch; multiply by batches then apply skill yield
                    $base_qty  = $mat['qty'] * $num_batches;
                    $final_qty = floor($base_qty * $yield_multiplier);
                    $mat_price = $material_prices[$mat['mid']] ?? 0.0;
                    $stack_total += $final_qty * $mat_price;
                    // Adjusted value uses CCP's adjusted prices (for tax calculation)
                    $adj_price             = $adjusted_prices[$mat['mid']] ?? 0.0;
                    $stack_adjusted_value += $final_qty * $adj_price;
                }
                // Reprocessing tax: applied to adjusted value of output materials for the whole stack
                $stack_reproc_tax = $reproc_tax * $stack_adjusted_value;
                // Deduct market fees on sale proceeds, then subtract reproc tax (flat ISK cost)
                $tax_rate    = $sales_tax + ($sell_to === 'sell_orders' ? $broker_fee : 0.0);
                $stack_total = $stack_total * (1.0 - $tax_rate) - $stack_reproc_tax;
                // Divide by the number of items actually reprocessed (whole batches only)
                $items_reprocessed = $num_batches * $portion_size;
                $reprocess_value   = $stack_total / $items_reprocessed;

                // Deduct relist fees per item if enabled
                // Formula per update: max((1 - (0.50 + 0.06 × advBroker)) × brokerFee × buyPrice, 100)
                if ($relist_fees && $order_updates > 0 && $reprocess_value !== null && $buy_price !== null) {
                    $discount          = 1.0 - (0.50 + 0.06 * $adv_broker_rel);
                    $fee_per_update    = max($discount * $broker_fee * $buy_price, 100.0);
                    $total_relist_cost = $fee_per_update * $order_updates;
                    $reprocess_value  -= $total_relist_cost;
                }
            }

            // Margin: cost basis includes brokerage fee on the buy, display price does not
            $margin = null;
            if ($buy_price !== null && $reprocess_value !== null && $buy_price > 0.0) {
                $cost_with_fee = $buy_price * (1.0 + $broker_fee);
                $margin        = (($reprocess_value - $cost_with_fee) / $cost_with_fee) * 100.0;
            }

            // Apply margin and volume filters
            if ($margin === null)           continue;
            if ($margin < $min_margin)      continue;
            if ($margin > $max_margin)      continue;
            if ($volume === null)           continue;
            if ($volume < $min_volume)      continue;

            $items[] = [
                'type_id'         => $type_id,
                'name'            => (string) $r['name'],
                'meta_tier'       => (string) $r['meta_tier'],
                'buy_price'       => $buy_price,
                'reprocess_value' => $reprocess_value,
                'volume'          => $volume,
                'margin'          => $margin,
            ];
        }

        wp_send_json_success(['items' => $items, 'broker_fee' => $broker_fee, 'oldest_price_date' => $oldest_fetched_at ?? null]);
    }

    /** Maps the dropdown slug values to their names in ett_invMarketGroups. */
    private static function trading_group_name(string $slug): string {
        $map = [
            'ammunition'      => 'Ammunition & Charges',
            'drones'          => 'Drones',
            'implants'        => 'Implants',
            'ships'           => 'Ships',
            'ship_equipment'  => 'Ship Equipment',
            'ship_module_mod' => 'Ship and Module Modifications',
        ];
        return $map[$slug] ?? '';
    }

    public static function ajax_get_characters(): void {
        check_ajax_referer('ett_rt_generate_list', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_success(['characters' => [], 'recommended' => null]);
            return;
        }

        $hub_key    = sanitize_key((string) ($_POST['trade_hub'] ?? 'jita'));
        $valid_hubs = ['jita', 'amarr', 'rens', 'dodixie', 'hek'];
        if (!in_array($hub_key, $valid_hubs, true)) $hub_key = 'jita';

        $user_id    = get_current_user_id();
        $characters = get_user_meta($user_id, 'ett_rt_characters', true);
        if (!is_array($characters) || empty($characters)) {
            wp_send_json_success(['characters' => [], 'recommended' => null]);
            return;
        }

        $hub_map  = self::hub_entities();
        $entities = $hub_map[$hub_key] ?? null;

        $result = [];
        foreach ($characters as $char_id => $data) {
            $char_id   = (string) $char_id;
            $char_name = (is_array($data) && !empty($data['name'])) ? (string) $data['name'] : 'Character ' . $char_id;

            $char_data    = self::get_character_data($char_id);
            $skill_levels = $char_data['skill_levels'];
            $standings    = $char_data['standings'];

            $scrapmetal  = (int) ($skill_levels[12196] ?? 0);
            $connections = (int) ($skill_levels[3359]  ?? 0);
            $diplomacy   = (int) ($skill_levels[3357]  ?? 0);
            $broker_rel  = (int) ($skill_levels[3446]  ?? 0);

            $reproc_tax = 0.05;
            $broker_fee = 0.03;

            if ($entities) {
                $faction_id   = (int) $entities['faction'];
                $corp_id      = (int) $entities['corp'];
                $base_faction = max(-10.0, min(10.0, round((float) ($standings[$faction_id] ?? 0.0), 2)));
                $base_corp    = max(-10.0, min(10.0, round((float) ($standings[$corp_id]    ?? 0.0), 2)));

                $broker_fee = max(0.01, round(
                    0.03 - (0.003 * $broker_rel) - (0.0003 * $base_faction) - (0.0002 * $base_corp),
                    4
                ));

                if (abs($base_corp) < 0.00001) {
                    $reproc_tax = 0.05;
                } else {
                    $skill_for_tax = ($base_corp < 0.0) ? $diplomacy : $connections;
                    $eff = round($base_corp + (10 - $base_corp) * (0.04 * $skill_for_tax), 2);
                    if ($eff <= 0.0)     $reproc_tax = 0.05;
                    elseif ($eff < 6.67) $reproc_tax = round(0.05 * (1 - ($eff / 6.67)), 4);
                    else                 $reproc_tax = 0.0;
                }
            }

            $result[] = [
                'character_id' => $char_id,
                'name'         => $char_name,
                'scrapmetal'   => $scrapmetal,
                'reproc_tax'   => $reproc_tax,
                'broker_fee'   => $broker_fee,
            ];
        }

        // Sort: lowest reproc_tax first, then lowest broker_fee as tiebreaker
        usort($result, function ($a, $b) {
            if ($a['reproc_tax'] !== $b['reproc_tax']) return $a['reproc_tax'] <=> $b['reproc_tax'];
            return $a['broker_fee'] <=> $b['broker_fee'];
        });

        wp_send_json_success([
            'characters'  => $result,
            'recommended' => !empty($result) ? $result[0]['character_id'] : null,
        ]);
    }

    /** Returns faction and corp entity IDs per trade hub for standings calculations. */
    private static function hub_entities(): array {
        return [
            'jita'    => ['faction' => 500001, 'corp' => 1000035],
            'amarr'   => ['faction' => 500003, 'corp' => 1000086],
            'rens'    => ['faction' => 500002, 'corp' => 1000049],
            'dodixie' => ['faction' => 500004, 'corp' => 1000120],
            'hek'     => ['faction' => 500002, 'corp' => 1000057],
        ];
    }

    /**
     * Fetches and caches skills + standings for a character from ESI.
     * Cached per character for 1 hour to avoid repeated ESI calls.
     *
     * @return array{skill_levels:array<int,int>,standings:array<int,float>}
     */
    public static function get_character_data(string $character_id): array {
        $empty = ['skill_levels' => [], 'standings' => []];

        $cache_key = 'ett_rt_char_' . $character_id;
        $cached    = get_transient($cache_key);
        if ($cached !== false && is_array($cached)) return $cached;

        $user_id = get_current_user_id();
        $token   = ETT_RT_OAuth::get_valid_access_token($user_id, $character_id);
        if (!$token) return $empty;

        $skills = wp_remote_get("https://esi.evetech.net/latest/characters/{$character_id}/skills/", [
            'timeout' => 20,
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);

        $standings = wp_remote_get("https://esi.evetech.net/latest/characters/{$character_id}/standings/", [
            'timeout' => 20,
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);

        if (is_wp_error($skills) || is_wp_error($standings)) return $empty;

        $skills_data    = json_decode(wp_remote_retrieve_body($skills), true);
        $standings_data = json_decode(wp_remote_retrieve_body($standings), true);

        if (!isset($skills_data['skills']) || !is_array($standings_data)) return $empty;

        $wanted       = self::skill_ids();
        $skill_levels = [];
        foreach ($skills_data['skills'] as $skill) {
            $sid = $skill['skill_id'] ?? null;
            if ($sid !== null && isset($wanted[$sid])) {
                $skill_levels[(int) $sid] = (int) ($skill['active_skill_level'] ?? 0);
            }
        }

        $standings_lookup = [];
        foreach ($standings_data as $standing) {
            if (isset($standing['from_id'], $standing['standing'])) {
                $standings_lookup[(int) $standing['from_id']] = (float) $standing['standing'];
            }
        }

        $data = ['skill_levels' => $skill_levels, 'standings' => $standings_lookup];
        set_transient($cache_key, $data, HOUR_IN_SECONDS);
        return $data;
    }

    /** Recursively collects all descendant market group IDs, inclusive of root. */
    private static function expand_market_groups(\wpdb $db, int $root): array {
        $rows = $db->get_results(
            "SELECT market_group_id, parent_group_id FROM ett_invMarketGroups",
            ARRAY_A
        );

        $children = [];
        foreach ($rows as $r) {
            $pid = !empty($r['parent_group_id']) ? (int) $r['parent_group_id'] : 0;
            $children[$pid][] = (int) $r['market_group_id'];
        }

        $seen  = [];
        $queue = [$root];
        while (!empty($queue)) {
            $gid = array_shift($queue);
            if (isset($seen[$gid])) continue;
            $seen[$gid] = true;
            foreach ($children[$gid] ?? [] as $child) {
                if (!isset($seen[$child])) $queue[] = $child;
            }
        }

        return array_keys($seen);
    }
}