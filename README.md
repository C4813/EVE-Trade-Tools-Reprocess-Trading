# EVE Trade Tools Reprocess Trading

A WordPress plugin that lets your site's users authenticate their EVE Online characters via SSO and use a shortcode-driven reprocessing margin calculator powered by live market data.

Part of the EVE Trade Tools suite. Requires **[EVE Trade Tools Price Helper](https://github.com/C4813/EVE-Trade-Tools-Price-Helper)** to be installed and active.

---

## Features

- **EVE SSO authentication** — users connect one or more EVE characters to their WordPress account; tokens are refreshed automatically
- **Reprocessing margin calculator** — calculates buy-to-reprocess margins per item using character skills and standings, with all relevant fees accounted for
- **Per-character fee modelling** — Scrapmetal Processing yield, sales tax (Accounting), broker fee (Broker Relations + standings), and reprocessing tax (Connections/Diplomacy + standings) are all read from ESI and applied correctly
- **portionSize aware** — reprocessing is calculated in whole batches; items with a batch size greater than 1 (e.g. ammunition at 100) are handled correctly
- **Multi-character support** — multiple characters per WordPress user; the tool automatically recommends the character with the lowest reprocessing tax and broker fee for the selected hub
- **Relist brokerage** — optional cost model for order modifications, based on Advanced Broker Relations level and expected update count
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

Displays the logged-in user's connected EVE characters — name, Scrapmetal Processing level, and a standings summary — along with a button to connect additional characters via EVE SSO.

### `[ett_trading_tool]`

Renders the interactive reprocessing margin calculator. Requires the user to be logged in and have at least one authenticated EVE character.

**Filters and options exposed in the UI:**

| Option | Description |
|---|---|
| Market group | Ammunition & Charges, Drones, Implants, Ships, Ship Equipment, Ship and Module Modifications |
| Trade hub | Jita, Amarr, Rens, Dodixie, Hek |
| Sell materials to | Sell orders or buy orders |
| Meta filter | Restrict to Meta-tier items only |
| Exclude capitals | Strip capital sub-groups from results |
| Stack size | Number of items to reprocess (must be ≥ one batch) |
| Min/max margin | Filter by reprocessing margin percentage |
| Min daily volume | Filter by average daily trade volume at the hub |
| Relist brokerage | Account for order-modification fees |
| Expected order updates | Number of order updates to price in per item |
| Character | Select which authenticated character's skills and standings to use |

Both shortcode pages require the user to be logged in. Unauthenticated visitors see an explanatory notice with a login link.

---

## Margin Calculation

Margins are calculated as:
```
margin = ((reprocess_value - cost_with_fee) / cost_with_fee) × 100
```

Where `cost_with_fee` is the buy price plus the broker fee on the buy order, and `reprocess_value` is the net ISK received after selling output materials, deducting sales tax, sell-side broker fee, and reprocessing tax.

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

Faction and corporation standing are read from ESI for the entity that controls the selected trade hub.

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
| `ett_invMarketGroups` | Market group hierarchy traversal |
| `ett_invTypes` | Item names, published flag, market group, portionSize |
| `ett_invMetaTypes` / `ett_invMetaGroups` | Meta tier classification (T1, T2, Meta, Faction, etc.) |
| `ett_industryActivityProducts` | Identifying blueprint-manufacturable items |
| `ett_invTypeMaterials` | Reprocessing output materials and quantities per batch |
| `ett_prices` | Buy/sell prices per type per hub |
| `ett_adjusted_prices` | CCP adjusted prices for reprocessing tax calculation |
| `ett_market_history` | Average daily volume per type per hub |

---

## License

GPLv2 or later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)
