=== EVE Trade Tools Reprocess Trading ===
Contributors: C4813
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: ett-price-helper

EVE Online character SSO and reprocessing margin calculator for WordPress, driven by two shortcodes.

== Description ==

EVE Trade Tools Reprocess Trading lets your site's users authenticate their EVE Online characters via SSO, then use a shortcode-driven trading tool that calculates reprocessing margins based on their actual character skills and standings.

This plugin depends on EVE Trade Tools Price Helper for SSO credentials and the external EVE market database. It adds a "Reprocess Trading" tab to the Price Helper admin page. It has no options of its own.

**Shortcodes**

* `[ett_esi_reprocess_profile]` — displays the logged-in user's connected EVE characters with their skill and standings summary, and a button to connect additional characters via EVE SSO.
* `[ett_trading_tool]` — interactive reprocessing margin calculator. Requires at least one authenticated character. Both shortcode pages are automatically excluded from page caching.

**Trading tool**

The trading tool queries the external EVE market database populated by Price Helper and calculates reprocessing margins for items in a chosen market group. Calculations account for:

* **Scrapmetal Processing** skill — NPC station base yield is 50%, with 2% added per skill level (55% at level 5).
* **Sales tax** — 7.5% base, reduced by 11% per level of Accounting, floored at 3.37%.
* **Broker fee** — 3% base, reduced by 0.3% per level of Broker Relations, with further reductions from faction and corporation standing at the chosen trade hub.
* **Reprocessing tax** — 5% of adjusted output value, reduced to zero once effective corporation standing reaches 6.67. Connections (positive standing) or Diplomacy (negative standing) improve effective standing.
* **Relist brokerage** — optional; accounts for order-modification fees based on Advanced Broker Relations level and the number of expected order updates.
* **portionSize** — items are reprocessed in whole batches; the tool uses the correct batch size per item type.

Results are filtered by margin range, minimum daily volume, and optionally restricted to Meta-tier items or non-capital items. If multiple characters are authenticated, the tool recommends the one with the lowest reprocessing tax and broker fee for the selected hub.

**Supported trade hubs:** Jita, Amarr, Rens, Dodixie, Hek

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

Pages containing either shortcode are automatically marked as non-cacheable on each request using standard WordPress no-cache headers and the `DONOTCACHEPAGE` constant. LiteSpeed Cache is also explicitly disabled on those pages via the `X-LiteSpeed-Cache-Control` header.

= What is removed on uninstall? =

All WordPress-side data created by this plugin: the `ett_rt_characters` user meta entry for every user, OAuth state transients, and ESI character cache transients. The external database managed by Price Helper is never modified.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.