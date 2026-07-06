# EVE Trade Tools Reprocess Trading

A WordPress plugin that lets your site's users authenticate their EVE Online characters via SSO and use a shortcode-driven reprocessing margin calculator powered by live market data.

Part of the EVE Trade Tools suite. Requires **[EVE Trade Tools Price Helper](https://github.com/C4813/EVE-Trade-Tools-Price-Helper)** to be installed and active.

---

## Features

- **EVE SSO authentication** — users connect one or more EVE characters to their WordPress account; tokens are refreshed automatically
- **Reprocessing margin calculator** — calculates buy-to-reprocess margins per item using character skills and standings, with all relevant fees accounted for
- **Per-character fee modelling** — Scrapmetal Processing yield, sales tax (Accounting), broker fee (Broker Relations + standings), and reprocessing tax (Connections/Diplomacy + standings) are all read from ESI and applied correctly
- **Broker fee override** — optionally replace the character-derived broker fee with a manually entered percentage, independently for the buy side, sell side, or both (e.g. buying in an Upwell structure while selling minerals at an NPC station); minimum effective fee is always 100 ISK or 0.5%, whichever is greater
- **portionSize aware** — reprocessing is calculated in whole batches; items with a batch size greater than 1 (e.g. ammunition at 100) are handled correctly
- **Multi-character support** — multiple characters per WordPress user; the tool automatically recommends the character with the lowest reprocessing tax and broker fee for the selected hub
- **Relist brokerage** — optional cost model for order modifications, based on Advanced Broker Relations level and expected update count
- **Capital exclusion** — recursively removes all capital-sized market groups and their descendants, including capital ship hulls, fighters, and Standup fighters
- **In-page usage guide** — optional `[ett_trading_howto]` shortcode renders a collapsible how-to guide alongside the tool
- **Market Quickbar export** — copies a ready-to-paste EVE Online Market Quickbar import string, using each item's Minimum Margin Value so you know the ceiling price to bid without eroding your target margin
- **Cache-safe** — pages using the shortcodes are automatically excluded from WordPress page caches and LiteSpeed Cache

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 6.0 or later |
| PHP | 8.0 or later |
| EVE Trade Tools Price Helper | latest |

Price Helper must be installed and active before this plugin can be activated. It supplies the SSO credentials, the external database connection, and the encryption/decryption utilities used to store OAuth tokens securely.

---

## Installation

1. Install and activate **EVE Trade Tools Price Helper**
2. Enter your EVE SSO application's Client ID and Client Secret under the Price Helper admin page
3. Configure the external database connection under the Price Helper admin page
4. Upload `ett-reprocess-trading` to `/wp-content/plugins/` and activate

---

## Shortcodes

### `[ett_esi_reprocess_profile]`

Displays the logged-in user's connected EVE characters — name, Scrapmetal Processing level, and a standings summary — along with a button to connect additional characters via EVE SSO. The connect button appears above the character list for easier access.

### `[ett_trading_tool]`

Renders the interactive reprocessing margin calculator. Requires the user to be logged in and have at least one authenticated EVE character.

**Filters and options exposed in the UI:**

| Option | Description |
|---|---|
| Market group | Ammunition & Charges, Drones, Implants, Ships, Ship Equipment, Ship and Module Modifications |
| Trade hub | Dynamically populated from Price Helper's price database — any hub with price data appears automatically (e.g. Jita, Amarr, Rens, Dodixie, Hek, and any private structure hubs you track) |
| Sell materials to | Sell orders or buy orders |
| Meta filter | Restrict to Meta-tier items only (Ship Equipment group only) |
| Exclude capitals | Recursively strip all capital-sized sub-groups from results (disabled automatically for Implants) |
| Stack size | Number of items to reprocess (must be ≥ one batch; minimum 1) |
| Min/max margin | Filter by reprocessing margin percentage |
| Min daily volume | Filter by average daily trade volume at the hub |
| Buy order QTY recommendation | Scale displayed quantity to a percentage of average daily volume (always shows a minimum of 1) |
| % of daily volume | Fraction of daily volume to use for QTY recommendation (active when above is Yes) |
| Relist brokerage | Account for order-modification fees |
| Expected order updates | Number of order updates to price in per item (active when relist is Yes) |
| Override brokerage fee | Four-mode selector — No, Buy Fee, Sell Fee, or Buy & Sell Fee — allowing the buy-side and sell-side broker fees to be overridden independently, e.g. for buying in an Upwell structure while selling at an NPC station |
| Brokerage fee % | The override percentage to use for the selected mode(s) (min 0.5%, max 2 decimal places; a 100 ISK per-item floor applies to the buy side) |
| Trader | Select which authenticated character's skills and standings to use, shown as `Name (RTax: X% \| NPC BFee: Y%)` |

After results are generated, the **Generate List** button becomes **Generate Market Quickbar**, which copies a formatted quickbar import string to the clipboard. Each line reads `Item Name [ Minimum Margin Value / Qty / Margin% ]`, where Minimum Margin Value is the highest price you should pay for that item to still lock in at least your Minimum Margin % — the reprocessed value scaled down by that margin — so you can stop bidding there instead of chasing the price up toward full Reprocess Value. The note portion is capped at 25 characters to fit EVE's quickbar note limit, so values may be truncated on high-margin items. Changing any filter or option clears the results and resets the button to **Regenerate List** — only one button is ever shown at a time.

All price data age timestamps are displayed in EVE time (UTC).

Both shortcode pages require the user to be logged in. Unauthenticated visitors see an explanatory notice with a login link.

### `[ett_trading_howto]`

Renders a collapsible how-to guide explaining every option in the trading tool, how to read results, and tips for finding profitable items. Can be placed on any page independently of `[ett_trading_tool]`.

---

## Margin Calculation

Margins are calculated as:
```
margin = ((reprocess_value - cost_with_fee) / cost_with_fee) × 100
```

Where `cost_with_fee` is the buy price plus the broker fee on the buy order, and `reprocess_value` is the net ISK received after selling output materials, deducting sales tax, sell-side broker fee, and reprocessing tax.

When **Override Brokerage Fee** applies to the buy side (Buy Fee or Buy & Sell Fee mode), the buy-side fee is:
```
buy_fee = max(override_rate × buy_price, 100 ISK)
cost_with_fee = buy_price + buy_fee
```

Otherwise `cost_with_fee = buy_price × (1 + buy_broker_fee)`, using either the character-derived fee or the override rate depending on which mode is selected.

### Minimum Margin Value

The highest buy price you should pay for an item to still lock in at least your Minimum Margin %, shown in the Market Quickbar export in place of the raw Reprocess Value:
```
cost_with_fee_target = reprocess_value / (1 + min_margin / 100)
min_margin_value      = cost_with_fee_target / (1 + buy_broker_fee)
```

If the buy side is overridden and the flat-rate fee would fall under the 100 ISK floor, the 100 ISK floor is subtracted instead of the percentage rate. This value is excluded from the on-screen results list, which continues to show the full Reprocess Value.

### Reprocessing yield
```
yield = 0.50 × (1 + scrapmetal_level × 0.02)
```

NPC station base is 50%; Scrapmetal Processing adds 2% per level (55% at level 5).

### Sales tax
```
sales_tax = max(0.0337, 0.075 × 0.89^accounting_level)
```

Each level of Accounting reduces the rate by 11%, floored at 3.37%.

### Broker fee
```
broker_fee = max(0.01, 0.03 − (0.003 × broker_relations) − (0.0003 × faction_standing) − (0.0002 × corp_standing))
```

Faction and corporation standing are read from ESI for the entity that controls the selected trade hub. This is the character-derived fee used for whichever side (buy/sell) isn't set to an override mode. When Override Brokerage Fee is active for a side, that side's fee is replaced by the manually entered percentage instead (minimum 0.5%, with a 100 ISK per-item floor on the buy side).

### Reprocessing tax

Based on effective corporation standing at the reprocessing station's owning corporation:
```
effective_standing = corp_standing + (10 − corp_standing) × (0.04 × skill_level)
```

Where `skill_level` is Connections (positive standing) or Diplomacy (negative standing). The reprocessing tax is 5% of adjusted output value, scaling linearly to 0% at effective standing 6.67 or above.

### Relist brokerage (optional)
```
fee_per_update = max((1 − (0.50 + 0.06 × adv_broker_relations)) × buy_broker_fee × buy_price, 100 ISK)
total_relist_cost = fee_per_update × order_updates
```

---

## Capital Exclusion

When **Exclude Capital-Sized** is set to Yes, the plugin identifies capital seed groups by name (`Capital*`, known hull categories, fighters) and then **recursively expands each to include all descendants**. This ensures that sub-tiers such as specific hull classes under Capital Ships are also removed, regardless of their individual names.

Groups always excluded when the option is active:

- All groups matching `Capital*`
- Dreadnoughts, Carriers, Force Auxiliaries, Supercarriers, Titans
- Freighters, Jump Freighters, Capital Industrial Ships, Industrial Command Ships
- Fighters, Light Fighters, Support Fighters, Heavy Fighters
- Standup Fighters, Standup Light Fighters, Standup Support Fighters, Standup Heavy Fighters

The option is disabled automatically (and defaulted to No) when the **Implants** market group is selected, as that group contains no capital-sized items.

XL-sized ammunition and capital-sized modules are also caught by a belt-and-suspenders item-name filter (matching an "XL " prefix or known capital module names), in case a specific item isn't excluded at the market-group level.

---

## ESI Scopes

| Scope | Purpose |
|---|---|
| `esi-skills.read_skills.v1` | Read character skill levels |
| `esi-characters.read_standings.v1` | Read character standings |

ESI data is cached per character for **one hour** via WordPress transients to avoid repeated API calls within a session.

### Skills used

Accounting, Advanced Broker Relations, Broker Relations, Connections, Diplomacy, Scrapmetal Processing

---

## Data Storage

This plugin stores no options of its own. All persistent data lives in WordPress user meta:

| Key | Content |
|---|---|
| `ett_rt_characters` | Per-user map of character IDs → name, access token, refresh token, token expiry |

OAuth state transients (`ett_rt_state_*`) and ESI cache transients (`ett_rt_char_*`) are also written to the WordPress options table and are automatically expired.

### On uninstall

- `ett_rt_characters` user meta is deleted for all users
- All `ett_rt_*` transients are deleted

The external database managed by Price Helper is never modified by this plugin.

---

## Admin

Adds a **Reprocess Trading** tab to the EVE Trade Tools admin page (provided by Price Helper). From this tab you can verify that the external database connection is reachable. There are no configurable settings — all credentials and database connection details are managed in Price Helper.

---

## Database Tables Read

All tables are created and maintained by **EVE Trade Tools Price Helper**.

| Table | Used for |
|---|---|
| `ett_invMarketGroups` | Market group hierarchy traversal and capital exclusion |
| `ett_invTypes` | Item names, published flag, market group, portionSize |
| `ett_invMetaTypes` / `ett_invMetaGroups` | Meta tier classification (T1, T2, Meta, Faction, etc.) |
| `ett_industryActivityProducts` | Identifying blueprint-manufacturable items |
| `ett_invTypeMaterials` | Reprocessing output materials and quantities per batch |
| `ett_prices` | Buy/sell prices per type per hub |
| `ett_adjusted_prices` | CCP adjusted prices for reprocessing tax calculation |
| `ett_market_history` | Average daily volume per type per hub |

---

## Changelog

### 1.2.2
- Market Quickbar note now shows Minimum Margin Value instead of Reprocess Value — the highest buy price you should pay for an item to still lock in at least your Minimum Margin %, calculated as the reprocessed value scaled down by that margin (net of broker fees). Lets you stop bidding at a guaranteed-margin price instead of chasing values up toward full Reprocess Value. The on-screen results list and its Reprocess Value column are unchanged.

### 1.2.1
- Buy Order QTY Recommendation now shows a minimum of 1 when the set percentage of daily volume calculates to less than 1
- Fixed private hub keys (e.g. `c-n4od`) being overwritten to `jita` before database re-validation ran, causing private hub price lookups to incorrectly return Jita prices
- Fixed private hub display names showing with only the first character capitalised (e.g. `C-n4od`); the trade hub dropdown now queries `ett_mapSolarSystems` for the canonical in-game system name for any hub key not in the static label map
- Fixed the changelog renderer stopping after the first `==` found anywhere in the content, including mid-line substrings; the section-boundary check now requires `==` at the start of a line
- Override Brokerage Fee replaced with a four-mode selector — No, Buy Fee, Sell Fee, Buy & Sell Fee — allowing buy-side and sell-side broker fees to be overridden independently
- Character dropdown label format changed from `Name (Scrp: X | Tax: Y% | Fee: Z%)` to `Name (RTax: X% | NPC BFee: Y%)`; Scrapmetal Processing level removed from the label
- "Reprocess Character" field renamed to "Trader"
- How-To guide updated throughout to match: Steps 2 and 3 swapped to reflect UI order, Trader label and format documented, Buy Order QTY minimum of 1 noted, Override Brokerage Fee section covers all four modes, Step 5 Qty description corrected to regional 30-day volume, Step 6 now shows the exact quickbar line format with the 25-character note cap
- Added an "Include 0.5% Upwell SCC Surcharge" hint below the Brokerage Fee % input when an override mode is active

### 1.2.0
- Trade hub list is now generated dynamically from the Price Helper external database rather than hardcoded; any hub with price data automatically appears in the Trade Hub dropdown
- Added GitHub repository button and Changelog toggle button to the How-To guide
- Fixed capital-sized ammo (XL-sized ammunition) not being excluded when Exclude Capital-Sized is set to Yes
- Fixed capital-sized modules (e.g. Capital Armor Plates) not being excluded when Meta Only is set to No
- Override Brokerage Fee minimum lowered from 1% to 0.5% to accommodate highly optimised standings and skills

### 1.1.2
- Fixed relist brokerage fee calculation: the 100 ISK minimum order-modification fee was being applied per item instead of per order, overstating relist costs for cheap high-volume items (e.g. ammunition) and sometimes filtering out profitable items entirely. The fee is now calculated at order level and divided across the items in the stack.

### 1.1.1
- Fixed excessive spacing above and below card dividers in the Pricing and Advanced Options cards

### 1.1.0
- Added `[ett_trading_howto]` shortcode — collapsible in-page usage guide
- Added Override Brokerage Fee option with 100 ISK / 1% floor
- UI restructured with grouped cards and section dividers
- Filter Options section repositioned below Pricing and Advanced Options
- Generate List / Generate Market Quickbar unified into a single state-changing button
- Capital exclusion now recursively removes all descendant market groups
- Fighters and Standup Fighters added to the capital-sized exclusion list
- Meta Only filter greys out and defaults to No for non-Ship Equipment groups
- Exclude Capital-Sized greys out and defaults to No for the Implants group
- Price data age displayed as EVE time (UTC)
- Reprocess Character and Filter Market Group dropdowns auto-widen to content
- Result legend added to daily profit box
- Connect with EVE Online button moved above the character list
- Numeric inputs now enforce minimums; zero and negative values rejected

### 1.0.0
- Initial release

---

## License

GPLv2 or later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)
