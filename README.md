# EVE Trade Tools Reprocess Trading

A WordPress plugin that lets your site's users authenticate their EVE Online characters via SSO and use a shortcode-driven reprocessing margin calculator powered by live market data.

Part of the EVE Trade Tools suite. Requires **[EVE Trade Tools Price Helper](https://github.com/C4813/EVE-Trade-Tools-Price-Helper)** to be installed and active.

---

## Features

- **EVE SSO authentication** — users connect one or more EVE characters to their WordPress account; tokens are refreshed automatically
- **Reprocessing margin calculator** — calculates buy-to-reprocess margins per item using character skills and standings, with all relevant fees accounted for
- **Per-character fee modelling** — Scrapmetal Processing yield, sales tax (Accounting), broker fee (Broker Relations + standings), and reprocessing tax (Connections/Diplomacy + standings) are all read from ESI and applied correctly
- **Broker fee override** — optionally replace the character-derived broker fee with a manually entered percentage; minimum effective fee is always 100 ISK or 1%, whichever is greater
- **portionSize aware** — reprocessing is calculated in whole batches; items with a batch size greater than 1 (e.g. ammunition at 100) are handled correctly
- **Multi-character support** — multiple characters per WordPress user; the tool automatically recommends the character with the lowest reprocessing tax and broker fee for the selected hub
- **Relist brokerage** — optional cost model for order modifications, based on Advanced Broker Relations level and expected update count
- **Capital exclusion** — recursively removes all capital-sized market groups and their descendants, including capital ship hulls, fighters, and Standup fighters
- **In-page usage guide** — optional `[ett_trading_howto]` shortcode renders a collapsible how-to guide alongside the tool
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
| Trade hub | Jita, Amarr, Rens, Dodixie, Hek |
| Sell materials to | Sell orders or buy orders |
| Meta filter | Restrict to Meta-tier items only (Ship Equipment group only) |
| Exclude capitals | Recursively strip all capital-sized sub-groups from results (disabled automatically for Implants) |
| Stack size | Number of items to reprocess (must be ≥ one batch; minimum 1) |
| Min/max margin | Filter by reprocessing margin percentage |
| Min daily volume | Filter by average daily trade volume at the hub |
| Buy order QTY recommendation | Scale displayed quantity to a percentage of average daily volume |
| % of daily volume | Fraction of daily volume to use for QTY recommendation (active when above is Yes) |
| Relist brokerage | Account for order-modification fees |
| Expected order updates | Number of order updates to price in per item (active when relist is Yes) |
| Override brokerage fee | Replace character-derived broker fee with a manually entered percentage (min 1%, max 2 decimal places) |
| Brokerage fee % | The override percentage to use (active when override is Yes; floor of 100 ISK or 1% applies) |
| Character | Select which authenticated character's skills and standings to use |

After results are generated, the **Generate List** button becomes **Generate Market Quickbar**, which copies a formatted quickbar import string to the clipboard. Changing any filter or option clears the results and resets the button to **Regenerate List** — only one button is ever shown at a time.

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

When **Override Brokerage Fee** is active, the buy-side fee is:
```
buy_fee = max(override_rate × buy_price, 100 ISK)
cost_with_fee = buy_price + buy_fee
```

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

Faction and corporation standing are read from ESI for the entity that controls the selected trade hub. This value is replaced entirely when the Override Brokerage Fee option is active.

### Reprocessing tax

Based on effective corporation standing at the reprocessing station's owning corporation:
```
effective_standing = corp_standing + (10 − corp_standing) × (0.04 × skill_level)
```

Where `skill_level` is Connections (positive standing) or Diplomacy (negative standing). The reprocessing tax is 5% of adjusted output value, scaling linearly to 0% at effective standing 6.67 or above.

### Relist brokerage (optional)
```
fee_per_update = max((1 − (0.50 + 0.06 × adv_broker_relations)) × broker_fee × buy_price, 100 ISK)
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
