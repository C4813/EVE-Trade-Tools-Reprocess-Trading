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
        add_shortcode('ett_trading_howto', [__CLASS__, 'shortcode_trading_howto']);
        add_action('wp_ajax_ett_rt_generate_list',        [__CLASS__, 'ajax_generate_list']);
        add_action('wp_ajax_nopriv_ett_rt_generate_list', [__CLASS__, 'ajax_generate_list']);
        add_action('wp_ajax_ett_rt_get_characters',       [__CLASS__, 'ajax_get_characters']);
        add_action('wp_ajax_ett_rt_get_hubs',             [__CLASS__, 'ajax_get_hubs']);
        add_action('wp_ajax_nopriv_ett_rt_get_hubs',      [__CLASS__, 'ajax_get_hubs']);
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
        if (has_shortcode($content, 'ett_esi_reprocess_profile') || has_shortcode($content, 'ett_trading_tool') || has_shortcode($content, 'ett_trading_howto')) {
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
        if (!has_shortcode($content, 'ett_esi_reprocess_profile') && !has_shortcode($content, 'ett_trading_tool') && !has_shortcode($content, 'ett_trading_howto')) return;

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

    /**
     * Shortcode: [ett_trading_howto]
     * Renders a collapsible "How to use the trading tool" guide.
     */
    public static function shortcode_trading_howto(): string {
        ob_start();
        ?>
        <div class="ett-howto-wrap">
            <div class="ett-character">
                <div class="ett-character-header ett-howto-header">
                    <strong>&#9432; How to Use the Reprocessing Trading Tool</strong>
                    <span class="ett-howto-toggle">&#9660;</span>
                </div>
                <div class="ett-character-body ett-howto-body">
                    <div class="ett-howto-content" style="text-align:left; max-width:680px; margin:0 auto; line-height:1.6;">

                        <h4 style="margin-top:0; display:flex; align-items:center; flex-wrap:wrap; gap:8px;">Overview
                            <a href="https://github.com/C4813/EVE-Trade-Tools-Reprocess-Trading" target="_blank" rel="noopener noreferrer" style="font-size:0.78em;font-weight:600;padding:2px 9px;background:#24292e;color:#fff;border-radius:3px;text-decoration:none;line-height:1.6;">GitHub</a>
                            <button type="button" id="ett-changelog-btn" style="font-size:0.78em;font-weight:600;padding:2px 9px;background:#f0f0f0;color:#333;border:1px solid #ccc;border-radius:3px;cursor:pointer;line-height:1.6;">Changelog</button>
                        </h4>
                        <div id="ett-changelog-panel" style="display:none;margin:-4px 0 14px;padding:10px 14px;background:#f8f8f8;border:1px solid #ddd;border-radius:4px;font-size:0.88em;max-height:260px;overflow-y:auto;">
                            <?php echo self::parse_changelog(); ?>
                        </div>
                        <script>
                        (function(){
                            var btn   = document.getElementById('ett-changelog-btn');
                            var panel = document.getElementById('ett-changelog-panel');
                            if (!btn || !panel) return;
                            btn.addEventListener('click', function () {
                                var open = panel.style.display !== 'none';
                                panel.style.display = open ? 'none' : 'block';
                                btn.textContent = open ? 'Changelog' : 'Hide Changelog';
                            });
                        })();
                        </script>

                        <p>This tool identifies EVE Online market items that are profitable to <strong>buy on the market and reprocess</strong> into raw minerals/materials, which are then sold. It factors in your character&rsquo;s skills, standings, and brokerage fees to give you accurate per-item profit estimates.</p>

                        <h4>Step 1 &mdash; Authenticate a Character</h4>
                        <p>Before using the tool, at least one EVE Online character must be authenticated via the <em>Connect with EVE Online</em> button on the character profile page. Your character&rsquo;s <strong>Scrapmetal Processing</strong> skill level, standings, and fees are used to calculate accurate reprocessing yields and tax rates.</p>

                        <h4>Step 2 &mdash; Filter Options</h4>
                        <ul>
                            <li><strong>Trade Hub:</strong> The market station to pull buy prices from (e.g. Jita 4-4).</li>
                            <li><strong>Reprocess Character:</strong> The character whose skills &amp; standings determine your reprocessing yield and tax. Characters are sorted by most favourable first. The format shown is: <code>Name (Scrp: X | Tax: Y% | Fee: Z%)</code>.</li>
                            <li><strong>Filter Market Group:</strong> The category of items to scan (e.g. Ship Equipment, Drones).</li>
                            <li><strong>Exclude Capital-Sized?:</strong> When set to <em>Yes</em>, filters out capital-sized modules, ship hulls, and fighters &mdash; these typically require specialist buyers and large capital to process.</li>
                            <li><strong>Meta Only?:</strong> When set to <em>Yes</em>, only shows <em>Meta</em> tier items (faction-originated T1 items dropped by NPCs). These are often the most reprocessing-profitable since they are bought cheaply but yield the same minerals as T1 originals.</li>
                        </ul>

                        <h4>Step 3 &mdash; Pricing Options</h4>
                        <ul>
                            <li><strong>Sell To:</strong> Whether to sell the reprocessed materials to <em>Buy Orders</em> (instant sale) or via <em>Sell Orders</em> (you list them; sales tax and broker fee apply). Sell Orders are usually more profitable but require patience.</li>
                            <li><strong>Minimum / Maximum Margin %:</strong> Only items within this profit margin range will appear. Margin is calculated as <code>((Reprocess Value &minus; Buy Cost) / Buy Cost) &times; 100</code>.</li>
                            <li><strong>Minimum Daily Volume:</strong> Filters out items with very low trade activity. Higher volume means easier buying and selling.</li>
                            <li><strong>Stack Size:</strong> How many of an item you intend to buy and reprocess in one batch. Note: items have a <em>portion size</em> (the minimum reprocessable quantity). For example, ammunition has a portion size of 100, so a stack size of 100 represents exactly 1 reprocessing batch (100 physical items). A stack of 1000 = 10 batches of 100 ammo each. Profit is calculated per physical item based on whole batches; any remainder below a full portion size is discarded.</li>
                        </ul>

                        <h4>Step 4 &mdash; Advanced Options</h4>
                        <ul>
                            <li><strong>Buy Order QTY Recommendation?:</strong> When set to <em>Yes</em>, the displayed quantity in the item list is scaled to the <em>% of Daily Volume</em> you set, giving you a recommended buy order size.</li>
                            <li><strong>% of Daily Volume:</strong> Only active when Buy Order QTY is enabled. Sets what fraction of average daily volume to recommend (e.g. 10% means buy up to 10% of daily traded volume).</li>
                            <li><strong>Re-list Brokerage Fees?:</strong> When set to <em>Yes</em>, the tool factors in the cost of modifying your buy orders a set number of times. Each update costs a fraction of the broker fee.</li>
                            <li><strong>Order Updates:</strong> Only active when Re-list is enabled. Enter how many times you expect to update each buy order before it fills.</li>
                        </ul>

                        <h4>Step 5 &mdash; Reading the Results</h4>
                        <p>After clicking <strong>Generate List</strong>, results appear below. The green summary box shows the <strong>Potential Daily Profit</strong> along with the age of the price data used.</p>
                        <p>Each line in the item list reads:</p>
                        <p style="font-family:monospace; background:#f4f4f4; padding:6px 10px; border-radius:3px;">Item Name &nbsp;[ Buy Price &nbsp;/ &nbsp;Reprocess Value &nbsp;/ &nbsp;Qty &nbsp;/ &nbsp;Margin% ]</p>
                        <ul>
                            <li><strong>Buy Price:</strong> The highest current buy order for that item at the selected hub (ISK per item).</li>
                            <li><strong>Reprocess Value:</strong> Estimated ISK returned per item after reprocessing, applying your skill yield, reprocessing tax, sales tax, and broker fees.</li>
                            <li><strong>Qty:</strong> Recommended quantity to buy (either based on your stack size, or scaled to % of daily volume if enabled).</li>
                            <li><strong>Margin%:</strong> Profit margin as a percentage of the total buy cost including broker fee.</li>
                        </ul>

                        <h4>Step 6 &mdash; Market Quickbar</h4>
                        <p>After the list is generated, the button changes to <strong>Generate Market Quickbar</strong>. Clicking it copies a formatted list to your clipboard that can be pasted directly into the EVE Online Market Quickbar. Open the Market window in-game, click the Quickbar import icon, and paste.</p>

                        <h4>Tips</h4>
                        <ul>
                            <li>Price data is fetched periodically &mdash; check the <em>Oldest price data</em> timestamp (shown in EVE Time / UTC) to know how fresh your results are.</li>
                            <li>High-volume items with moderate margins (&gt;5%) are usually more consistently profitable than low-volume high-margin items.</li>
                            <li>Meta modules from NPCs (i.e. modules which cannot be manufactured / do not have a blueprint) are often excellent reprocessing targets.</li>
                            <li>Changing any setting automatically clears the results &mdash; simply click the button again to regenerate with the updated parameters.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function ajax_generate_list(): void {
        check_ajax_referer('ett_rt_generate_list', 'nonce');

        $slug            = sanitize_key((string) ($_POST['market_group']    ?? 'ship_equipment'));
        $hub_key         = sanitize_key((string) ($_POST['trade_hub']       ?? 'jita'));
        $exclude_capital = (sanitize_key(wp_unslash($_POST['exclude_capital'] ?? 'yes')) === 'yes');
        $meta_only       = (sanitize_key(wp_unslash($_POST['meta_only']       ?? 'yes')) === 'yes');

        $valid_hubs = ['jita', 'amarr', 'rens', 'dodixie', 'hek'];
        // Note: no early fallback here — DB-based validation below handles all keys.

        $group_name = self::trading_group_name($slug);
        if ($group_name === '') {
            wp_send_json_error('Invalid market group.', 400);
        }

        $db = ETT_RT_ExtDB::get_db();
        if (is_wp_error($db)) {
            wp_send_json_error($db->get_error_message(), 500);
        }

        // Re-validate hub_key against what actually exists in the price database.
        // This allows newly added hubs from Price Helper to be used automatically.
        $db_hubs = $db->get_col('SELECT DISTINCT hub_key FROM ett_prices');
        $db_hubs = is_array($db_hubs) ? array_map('sanitize_key', $db_hubs) : [];
        if (!empty($db_hubs) && !in_array($hub_key, $db_hubs, true)) {
            // Prefer jita if available, otherwise first in list
            $hub_key = in_array('jita', $db_hubs, true) ? 'jita' : $db_hubs[0];
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

        // Exclude capital market groups and ALL their descendants.
        // We first find the root capital group IDs by name, then recursively expand each
        // so that sub-groups like Dreadnoughts > [specific hull tiers] are also removed.
        if ($exclude_capital && !empty($all_group_ids)) {
            $safe = implode(',', array_map('intval', $all_group_ids));
            // Seed groups: anything named Capital*, known capital hull categories, fighters.
            $seed_capital_ids = $db->get_col(
                "SELECT market_group_id FROM ett_invMarketGroups
                  WHERE market_group_id IN ($safe)
                    AND (
                        name LIKE 'Capital%'
                        OR name LIKE '% XL'
                        OR name LIKE '% XL %'
                        OR name IN (
                            'Dreadnoughts',
                            'Carriers',
                            'Force Auxiliaries',
                            'Supercarriers',
                            'Titans',
                            'Freighters',
                            'Jump Freighters',
                            'Capital Industrial Ships',
                            'Industrial Command Ships',
                            'Fighters',
                            'Light Fighters',
                            'Support Fighters',
                            'Heavy Fighters',
                            'Standup Fighters',
                            'Standup Light Fighters',
                            'Standup Support Fighters',
                            'Standup Heavy Fighters'
                        )
                    )"
            );
            $seed_capital_ids = array_map('intval', $seed_capital_ids ?: []);

            // For each seed group, expand to include ALL descendants so that child groups
            // (e.g. specific hull tiers under Capital Ships) are also removed.
            $all_capital_to_remove = [];
            foreach ($seed_capital_ids as $cap_root) {
                $descendants = self::expand_market_groups($db, $cap_root);
                foreach ($descendants as $d) {
                    $all_capital_to_remove[$d] = true;
                }
            }

            $all_group_ids = array_values(array_diff($all_group_ids, array_keys($all_capital_to_remove)));
        }

        if (empty($all_group_ids)) {
            wp_send_json_success(['items' => []]);
            return;
        }

        $safe = implode(',', array_map('intval', $all_group_ids));

        // Belt-and-suspenders: also filter at the item-name level.
        // Capital-sized items all carry a "Capital " prefix in their name; XL ammo
        // (fired by dreads) contains " XL" either as a suffix ("Carbonized Lead XL")
        // or mid-string ("Guristas Inferno XL Torpedo"). Match both with two clauses.
        // This catches any items that slipped through the market-group-level exclusion above.
        $capital_name_clause = $exclude_capital
            ? " AND t.name NOT LIKE 'Capital %' AND t.name NOT LIKE '% XL' AND t.name NOT LIKE '% XL %'"
            : '';

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
               $capital_name_clause
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

        // Override broker fee: if active, replaces the character-derived broker_fee.
        // Input is a percentage (e.g. 3.00 = 3%). Minimum allowed is 1% (0.01).
        // When override is active the minimum per-item buy fee = max(rate × price, 100 ISK).
        $override_broker     = sanitize_key((string) ($_POST['override_broker'] ?? 'no')) === 'yes';
        $override_broker_pct = floatval(wp_unslash($_POST['override_broker_pct'] ?? 3.0));
        if ($override_broker) {
            // Clamp: must be at least 0.5%, no more than 100%
            $override_broker_pct = max(0.5, min(100.0, round($override_broker_pct, 2)));
            $broker_fee          = $override_broker_pct / 100.0;
        }
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

                // Deduct relist fees per item if enabled.
                // The 100 ISK minimum is per *order*, not per item. Calculate the order-level
                // cost first, then divide by the number of items in the order so the per-item
                // reprocess value is adjusted correctly.
                if ($relist_fees && $order_updates > 0 && $reprocess_value !== null && $buy_price !== null) {
                    $discount                  = 1.0 - (0.50 + 0.06 * $adv_broker_rel);
                    $order_value               = $buy_price * $items_reprocessed;
                    $fee_per_update_order      = max($discount * $broker_fee * $order_value, 100.0);
                    $total_relist_cost_order   = $fee_per_update_order * $order_updates;
                    $reprocess_value          -= $total_relist_cost_order / $items_reprocessed;
                }
            }

            // Margin: cost basis includes brokerage fee on the buy, display price does not.
            // When override is active: effective buy fee = max(rate × price, 100 ISK) per item.
            $margin = null;
            if ($buy_price !== null && $reprocess_value !== null && $buy_price > 0.0) {
                if ($override_broker) {
                    $buy_fee_isk   = max($broker_fee * $buy_price, 100.0);
                    $cost_with_fee = $buy_price + $buy_fee_isk;
                } else {
                    $cost_with_fee = $buy_price * (1.0 + $broker_fee);
                }
                $margin = (($reprocess_value - $cost_with_fee) / $cost_with_fee) * 100.0;
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
        // No hardcoded hub list — standings are calculated for any key that exists in hub_entities();
        // unknown hubs simply receive default broker/reproc values (hub_entities returns null for unknowns).

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

    /** Returns human-readable display name for a hub key. Fallback: ucfirst of key. */
    private static function hub_labels(): array {
        return [
            'jita'    => 'Jita',
            'amarr'   => 'Amarr',
            'rens'    => 'Rens',
            'dodixie' => 'Dodixie',
            'hek'     => 'Hek',
        ];
    }

    /**
     * Parses readme.txt and returns the == Changelog == section as HTML.
     * Lines starting with = X.Y.Z = become version headings;
     * lines starting with * become list items.
     */
    private static function parse_changelog(): string {
        $path = self::dir() . 'readme.txt';
        if (!file_exists($path)) return '<p>Changelog not available.</p>';
        $text = (string) file_get_contents($path);
        if (!preg_match('/== Changelog ==\r?\n(.*?)(?:== |\z)/s', $text, $m)) {
            return '<p>Changelog not available.</p>';
        }
        $lines  = preg_split('/\r?\n/', trim($m[1]));
        $html   = '';
        $in_ul  = false;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                if ($in_ul) { $html .= '</ul>'; $in_ul = false; }
                continue;
            }
            if (preg_match('/^= (.+) =\s*$/', $line, $vm)) {
                if ($in_ul) { $html .= '</ul>'; $in_ul = false; }
                $html .= '<p style="margin:10px 0 4px;font-weight:700;">' . esc_html($vm[1]) . '</p>';
            } elseif (str_starts_with($line, '* ')) {
                if (!$in_ul) { $html .= '<ul style="margin:0 0 4px 18px;">'; $in_ul = true; }
                $html .= '<li>' . esc_html(substr($line, 2)) . '</li>';
            } else {
                if ($in_ul) { $html .= '</ul>'; $in_ul = false; }
                $html .= '<p style="margin:4px 0;">' . esc_html($line) . '</p>';
            }
        }
        if ($in_ul) $html .= '</ul>';
        return $html;
    }

    /**
     * AJAX: returns the list of trade hubs available in the external price database.
     * Reads DISTINCT hub_key values from ett_prices and maps them to display labels.
     */
    public static function ajax_get_hubs(): void {
        check_ajax_referer('ett_rt_generate_list', 'nonce');

        $db = ETT_RT_ExtDB::get_db();
        if (is_wp_error($db)) {
            wp_send_json_error($db->get_error_message(), 500);
        }

        $keys = $db->get_col('SELECT DISTINCT hub_key FROM ett_prices ORDER BY hub_key ASC');
        if (!is_array($keys) || empty($keys)) {
            wp_send_json_error('No trade hubs found in the price database.', 404);
        }

        $labels = self::hub_labels();

        // For keys not in the static map, look up the canonical in-game name from
        // ett_mapSolarSystems (populated by the SDE import in Price Helper).
        // sanitize_key() lowercases everything, so the key stored in ett_prices for
        // e.g. C-N4OD is 'c-n4od'. The SDE table stores the canonical cased name.
        $unknown_keys = [];
        foreach ($keys as $key) {
            if (!isset($labels[(string) $key])) {
                $unknown_keys[] = (string) $key;
            }
        }
        if (!empty($unknown_keys)) {
            // Build a LIKE-based lookup: match by lowercased name = key (sanitize_key lowercases).
            // Safer than LOWER() which may not be available; use a PHP post-filter instead.
            $all_names = $db->get_results(
                'SELECT name FROM ett_mapSolarSystems',
                ARRAY_A
            );
            if (is_array($all_names)) {
                $name_by_lower = []; // lowercase → canonical
                foreach ($all_names as $row) {
                    $name_by_lower[strtolower((string) $row['name'])] = (string) $row['name'];
                }
                foreach ($unknown_keys as $key) {
                    if (isset($name_by_lower[$key])) {
                        $labels[$key] = $name_by_lower[$key];
                    }
                }
            }
        }

        $hubs   = [];
        foreach ($keys as $key) {
            $key    = (string) $key;
            // Use canonical label if found; as a last resort show the key as-is
            // (preserves whatever capitalisation the user entered when creating the hub).
            $hubs[] = [
                'key'   => $key,
                'label' => $labels[$key] ?? $key,
            ];
        }

        // Sort: preferred order first (jita, amarr, rens, dodixie, hek), then alphabetical
        $order = ['jita', 'amarr', 'rens', 'dodixie', 'hek'];
        usort($hubs, function ($a, $b) use ($order) {
            $ai = array_search($a['key'], $order, true);
            $bi = array_search($b['key'], $order, true);
            if ($ai !== false && $bi !== false) return $ai <=> $bi;
            if ($ai !== false) return -1;
            if ($bi !== false) return 1;
            return strcmp($a['key'], $b['key']);
        });

        wp_send_json_success(['hubs' => $hubs]);
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
