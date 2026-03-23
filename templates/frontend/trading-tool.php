<?php
if (!defined('ABSPATH')) exit;
?>

<div class="ett-trading-tool">

    <!-- ── Options: outer card, two columns, each a single combined card ── -->
    <div class="ett-card-outer">
        <div class="ett-trading-options">

            <!-- Left: Pricing Options + Margin in one card with a divider -->
            <div class="ett-options-col">
                <div class="ett-card">
                    <h4>Pricing Options</h4>

                    <div class="ett-field">
                        <label for="ett-sell-to">Sell To</label>
                        <select id="ett-sell-to" name="ett_sell_to">
                            <option value="buy_orders">Buy Orders</option>
                            <option value="sell_orders" selected>Sell Orders</option>
                        </select>
                    </div>

                    <div class="ett-field">
                        <label for="ett-min-daily-vol">Minimum Daily Volume</label>
                        <input type="number" id="ett-min-daily-vol" name="ett_min_daily_vol"
                               min="1" step="1" value="1" />
                    </div>

                    <div class="ett-field">
                        <label for="ett-stack-size">Stack Size</label>
                        <input type="number" id="ett-stack-size" name="ett_stack_size"
                               min="1" step="1" value="100" />
                    </div>

                    <hr class="ett-card-divider" />

                    <div class="ett-field">
                        <label for="ett-min-margin">Minimum Margin %</label>
                        <input type="number" id="ett-min-margin" name="ett_min_margin"
                               min="0" step="0.01" value="2" />
                    </div>

                    <div class="ett-field">
                        <label for="ett-max-margin">Maximum Margin %</label>
                        <input type="number" id="ett-max-margin" name="ett_max_margin"
                               min="0" step="0.01" value="1000" />
                    </div>
                </div>
            </div>

            <!-- Right: Advanced Options + Brokerage in one card with a divider -->
            <div class="ett-options-col">
                <div class="ett-card">
                    <h4>Advanced Options</h4>

                    <div class="ett-field">
                        <label for="ett-buy-qty">Buy Order QTY Recommendation?</label>
                        <select id="ett-buy-qty" name="ett_buy_qty">
                            <option value="yes">Yes</option>
                            <option value="no" selected>No</option>
                        </select>
                    </div>

                    <div class="ett-field">
                        <label for="ett-pct-daily-vol">% of Daily Volume</label>
                        <input type="number" id="ett-pct-daily-vol" name="ett_pct_daily_vol"
                               min="0" step="0.01" value="10" />
                    </div>

                    <hr class="ett-card-divider" />

                    <div class="ett-field">
                        <label for="ett-relist-brokerage">Re-list Brokerage Fees?</label>
                        <select id="ett-relist-brokerage" name="ett_relist_brokerage">
                            <option value="yes">Yes</option>
                            <option value="no" selected>No</option>
                        </select>
                    </div>

                    <div class="ett-field">
                        <label for="ett-order-updates">Order Updates</label>
                        <input type="number" id="ett-order-updates" name="ett_order_updates"
                               min="0" step="1" value="5" />
                    </div>

                    <hr class="ett-card-divider" />

                    <div class="ett-field">
                        <label for="ett-override-broker">Override Brokerage Fee?</label>
                        <select id="ett-override-broker" name="ett_override_broker">
                            <option value="no" selected>No</option>
                            <option value="buy">Buy Fee</option>
                            <option value="sell">Sell Fee</option>
                            <option value="both">Buy &amp; Sell Fee</option>
                        </select>
                    </div>

                    <div class="ett-field" id="ett-override-broker-pct-field">
                        <label for="ett-override-broker-pct">Brokerage Fee %</label>
                        <input type="number" id="ett-override-broker-pct" name="ett_override_broker_pct"
                               min="0.5" max="100" step="0.01" value="1.00" />
                        <div class="ett-char-rec-text">Include 0.5% Upwell SCC Surcharge</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ── Filter Options (below pricing/advanced) ── -->
    <div class="ett-card-full">
        <h4 class="ett-heading-full">Filter Options</h4>
        <div class="ett-trading-filters">

            <!-- Left: Trade Hub + Trader -->
            <div class="ett-filter-col">

                <div class="ett-field">
                    <label for="ett-trade-hub">Trade Hub</label>
                    <select id="ett-trade-hub" name="ett_trade_hub">
                        <option value="">&#8212; Loading hubs&hellip; &#8212;</option>
                    </select>
                </div>

                <div class="ett-field" id="ett-char-rec-field">
                    <label for="ett-character">Trader</label>
                    <select id="ett-character" name="ett_character" class="ett-select-wide">
                        <option value="">&#8212; Loading characters&#8230; &#8212;</option>
                    </select>
                    <div id="ett-char-rec-text" class="ett-char-rec-text"></div>
                </div>

            </div>

            <!-- Right: Market Group + Capital/Meta nested card -->
            <div class="ett-filter-col">

                <div class="ett-field">
                    <label for="ett-market-group">Filter Market Group</label>
                    <select id="ett-market-group" name="ett_market_group" class="ett-select-wide">
                        <option value="ammunition">Ammunition &amp; Charges</option>
                        <option value="drones">Drones</option>
                        <option value="implants">Implants</option>
                        <option value="ships">Ships</option>
                        <option value="ship_equipment" selected>Ship Equipment</option>
                        <option value="ship_module_mod">Ship and Module Modifications</option>
                    </select>
                </div>

                <div class="ett-card ett-card-inline">
                    <div class="ett-field" id="ett-exclude-capital-field">
                        <label for="ett-exclude-capital">Exclude Capital-Sized?</label>
                        <select id="ett-exclude-capital" name="ett_exclude_capital">
                            <option value="yes" selected>Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>

                    <div class="ett-field" id="ett-meta-only-field">
                        <label for="ett-meta-only">Meta Only?</label>
                        <select id="ett-meta-only" name="ett_meta_only">
                            <option value="yes" selected>Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <button type="button" class="ett-trading-btn" id="ett-btn-generate-list" data-mode="generate">Generate List</button>

    <div id="ett-trading-results"></div>

</div>
