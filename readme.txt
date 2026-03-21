=== EVE Trade Tools Reprocess Trading ===
Contributors: C4813
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.2.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: ett-price-helper

EVE Online character SSO and reprocessing margin calculator for WordPress, driven by two shortcodes.

== Description ==

EVE Trade Tools Reprocess Trading lets your site's users authenticate their EVE Online characters via SSO, then use a shortcode-driven trading tool that calculates reprocessing margins based on their actual character skills and standings.

This plugin depends on EVE Trade Tools Price Helper for SSO credentials and the external EVE market database. It adds a "Reprocess Trading" tab to the Price Helper admin page. It has no options of its own.

**Shortcodes**

* `[ett_esi_reprocess_profile]` — displays the logged-in user's connected EVE characters with their skill and standings summary, and a button to connect additional characters via EVE SSO. The connect button is shown above the character list for easier access.
* `[ett_trading_tool]` — interactive reprocessing margin calculator. Requires at least one authenticated character. Both shortcode pages are automatically excluded from page caching.
* `[ett_trading_howto]` — collapsible how-to guide explaining all options in the trading tool. Can be placed on any page, independently of the trading tool.

**Trading tool**

The trading tool queries the external EVE market database populated by Price Helper and calculates reprocessing margins for items in a chosen market group. Calculations account for:

* **Scrapmetal Processing** skill — NPC station base yield is 50%, with 2% added per skill level (55% at level 5).
* **Sales tax** — 7.5% base, reduced by 11% per level of Accounting, floored at 3.37%.
* **Broker fee** — 3% base, reduced by 0.3% per level of Broker Relations, with further reductions from faction and corporation standing at the chosen trade hub. Can be overridden manually with a user-entered percentage (minimum 0.5%) that replaces the character-derived fee; minimum effective fee is always 100 ISK or 0.5%, whichever is greater.
* **Reprocessing tax** — 5% of adjusted output value, reduced to zero once effective corporation standing reaches 6.67. Connections (positive standing) or Diplomacy (negative standing) improve effective standing.
* **Relist brokerage** — optional; accounts for order-modification fees based on Advanced Broker Relations level and the number of expected order updates.
* **portionSize** — items are reprocessed in whole batches; the tool uses the correct batch size per item type.

Results are filtered by margin range, minimum daily volume, and optionally restricted to Meta-tier items or non-capital items. Capital exclusion recursively removes all descendant market groups, correctly filtering capital ship hulls, fighters (including Standup variants), and their sub-tiers. The Meta Only filter is only available for the Ship Equipment group. The Exclude Capital-Sized filter is disabled automatically when the Implants group is selected.

If multiple characters are authenticated, the tool recommends the one with the lowest reprocessing tax and broker fee for the selected hub by default, but the user can override this.

The Generate List button transforms into the Generate Market Quickbar button after results are loaded — only one button is ever shown. Changing any filter or option automatically clears the results and resets the button to Regenerate List.

All timestamps for price data age are displayed in EVE time (UTC).

**Supported trade hubs:** Determined dynamically from the Price Helper external database. Any hub present in the `ett_prices` table is available. Default fallback list: Jita, Amarr, Rens, Dodixie, Hek.

**Supported market groups:** Ammunition & Charges, Drones, Implants, Ships, Ship Equipment, Ship and Module Modifications

**ESI scopes requested:** `esi-skills.read_skills.v1`, `esi-characters.read_standings.v1`

**Skills read from ESI:** Accounting, Broker Relations, Advanced Broker Relations, Connections, Diplomacy, Scrapmetal Processing

ESI skill and standings data is cached per character for one hour via WordPress transients to avoid repeated ESI calls during a session.

== Installation ==

1. Install and activate EVE Trade Tools Price Helper first.
2. Configure the SSO Client ID and Client Secret under the Price Helper admin page.
3. Configure the external database connection under the Price Helper admin page.
4. Upload the `ett-reprocess-trading` folder to `/wp-content/plugins/` and activate the plugin.
5. Place `[ett_esi_reprocess_profile]` on a page where users can connect their characters.
6. Place `[ett_trading_tool]` on the page where you want the trading tool to appear.
7. Optionally place `[ett_trading_howto]` on any page to display the usage guide.

Both shortcode pages require users to be logged in to WordPress.

== Frequently Asked Questions ==

= Does this plugin have its own settings page? =

No. It registers a "Reprocess Trading" tab on the EVE Trade Tools Price Helper admin page, where you can verify the external database connection. All SSO credentials are configured in Price Helper.

= Where does the market data come from? =

From the external database managed by EVE Trade Tools Price Helper. The Reprocess Trading plugin never writes to that database — it reads prices, adjusted prices, market history, market groups, type definitions, and reprocessing materials from it.

= What happens if a user is not logged in? =

Both shortcodes display an explanatory notice and do not render any EVE data. The trading tool notice includes a link to the WordPress login page.

= Can users connect more than one character? =

Yes. Multiple characters can be authenticated per WordPress user. The trading tool selects the most advantageous character for the chosen hub by default (lowest reprocessing tax, then lowest broker fee), but the user can override this selection.

= Does this work with page caching plugins? =

Pages containing any of the plugin shortcodes are automatically marked as non-cacheable on each request using standard WordPress no-cache headers and the `DONOTCACHEPAGE` constant. LiteSpeed Cache is also explicitly disabled on those pages via the `X-LiteSpeed-Cache-Control` header.

= What is removed on uninstall? =

All WordPress-side data created by this plugin: the `ett_rt_characters` user meta entry for every user, OAuth state transients, and ESI character cache transients. The external database managed by Price Helper is never modified.

== Changelog ==

= 1.2.0 =
* Trade hub list is now generated dynamically from the Price Helper external database (`ett_prices` table) rather than being hardcoded. Any hub present in the database automatically appears in the Trade Hub dropdown; only hubs with price data can be selected.
* Added GitHub repository button and Changelog toggle button to the How-To guide next to the Overview heading.
* Fixed capital-sized ammo (XL-sized ammunition) not being excluded when "Exclude Capital-Sized" is set to Yes. XL ammo market groups and items with an "XL " name prefix are now correctly filtered.
* Fixed capital-sized modules (e.g. Capital Armor Plates) not being excluded when "Meta Only" is set to No. A belt-and-suspenders item-name filter now catches any capital items that slipped through the market-group-level exclusion.
* Override Brokerage Fee minimum lowered from 1% to 0.5% to accommodate 0.0% Upwell markets. (A 0.5% SCC Surcharge always applies if tax is <0.5%).

= 1.1.2 =
* Fixed relist brokerage fee calculation: the 100 ISK minimum order-modification fee was incorrectly applied per item rather than per order. For cheap high-volume items (e.g. ammunition) this caused relist costs to be overstated by orders of magnitude, making profitable items appear unprofitable or filter them out of results entirely. The fee is now calculated at order level and divided across the items in the stack.

= 1.1.1 =
* Fixed excessive spacing above and below card dividers in the Pricing and Advanced Options cards — spacing is now consistent with the gap between all other option fields.

= 1.1.0 =
* Added `[ett_trading_howto]` shortcode — collapsible in-page usage guide covering all tool options, readable results format, and tips.
* Trading tool UI restructured with grouped cards for visual separation of Pricing Options, Advanced Options, margin filters, and brokerage settings.
* Filter Options section repositioned below Pricing and Advanced Options.
* Generate List and Generate Market Quickbar are now a single button that changes state; only one button is ever visible at a time.
* Capital exclusion now recursively removes all descendant market groups, fixing incorrect inclusion of capital ship hulls and their sub-tiers when the Ships group was selected.
* Fighters and Standup Fighters (all variants: Light, Support, Heavy) added to the capital-sized exclusion list.
* Meta Only filter now greys out and defaults to No when any market group other than Ship Equipment is selected.
* Exclude Capital-Sized filter now greys out and defaults to No when the Implants group is selected.
* Added Override Brokerage Fee option — allows a manual percentage entry (minimum 1%) that replaces the character-derived broker fee; minimum effective fee is 100 ISK or 1%, whichever is greater.
* Oldest price data timestamp now displayed as EVE time (UTC).
* Reprocess Character and Filter Market Group dropdowns now auto-widen to fit their longest option.
* Result legend added to the daily profit box clarifying the column order.
* Connect with EVE Online button moved to above the character list.
* Stack size and all numeric inputs now enforce a minimum of 1 or 0 as appropriate; zero and negative values are rejected.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.2 =
Bug fix: relist brokerage fee was applied per item instead of per order, causing cheap items (e.g. ammunition) to appear far less profitable than they are when Re-list Brokerage Fees was enabled. No database changes.

= 1.1.1 =
Minor visual fix for card divider spacing. No functional or database changes.

= 1.1.0 =
UI overhaul, capital exclusion fix for ship hulls, new Override Brokerage Fee option, fighters added to capital filter, and a new how-to guide shortcode. No database changes. No configuration required after update.

= 1.0.0 =
Initial release.
