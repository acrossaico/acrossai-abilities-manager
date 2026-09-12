# Pre-change Baseline — wordpress-7-0.local

> ## ⚠️ This baseline no longer describes the live site
>
> On 2026-09-12 a hook-timing bug in the gate migration (wired at `plugins_loaded` P1, before
> definitions are collected at `init` P99) caused it to run against an empty registry, translate
> nothing, and then **delete `acrossai_library_config`**. The configuration recorded below is gone
> from that site. The operator is rebuilding it by hand.
>
> **The done flag is still set.** After rebuilding the config, clear it or the migration will not run:
>
> ```bash
> wp --exec="define('DB_HOST', 'localhost:$SOCK');" option delete acrossai_library_gate_migration_done
> ```
>
> The numbers below remain valid as a record of what the configuration *was*. The 52 figure applied to
> that config and no longer applies.
>
> **The operator rebuilt a config and T040a then passed against it**: `acrossai-settings` off (11
> definitions), `acrossai-cache` off (7), `acrossai-options` specific with 2 of 7 ticked → predicted 23
> blocks, wrote exactly 23, and none of the 23 remained in `wp_get_abilities()`. That is the live
> verification this file previously lacked.

**Captured**: 2026-09-12 · **Task**: T001 · **Site**: `http://wordpress-7-0.local`

## Reaching this site from the CLI

Local runs MySQL on a per-site socket that system `wp` cannot find, so `wp` reports
*"Error establishing a database connection"* against a perfectly healthy site. Override `DB_HOST` at
load time rather than editing `wp-config.php`:

```bash
SOCK="/Users/raftaar1191/Library/Application Support/Local/run/t739Logms/mysql/mysqld.sock"
wp --exec="define('DB_HOST', 'localhost:$SOCK');" eval '…'
```

Find the socket id with `find ~/Library/Application\ Support/Local/run -name mysqld.sock`.

> **`wp eval` undercounts registered abilities.** WordPress only accepts `wp_register_ability()` during
> the `wp_abilities_api_init` action — calling it outside gives
> *"Abilities must be registered on the wp_abilities_api_init action"* and returns `null`. In a CLI
> session that action has already fired, so `count( wp_get_abilities() )` reports only what other
> plugins registered during boot (34 here: `core/`, `mcp-tracker/`, `rank-math/`). **This is not a site
> defect.** Measure the registry and the gate instead, as below, or use a real HTTP request.

## State at capture

| Measure | Value |
|---|---|
| Definitions in the library registry | **389** |
| Distinct definition categories | 24 — **all 24 pre-registered** (38 categories registered overall) |
| **Gate permits** | **337** |
| **Gate blocks** | **52** |
| `acrossai_library_config` entries | 4 |

### The configuration this site actually carries

| Category | enabled | mode | ticked slugs |
|---|---|---|---|
| `acrossai-abilities-manager-content` | true | `specific` | 3 |
| `acrossai-content` | true | `specific` | 4 |
| `acrossai-settings` | true | `specific` | 2 |
| `acrossai-database` | **false** | `all` | 0 |

This is a genuine operator-produced configuration, not an invented fixture, and it exercises **both**
translation rules: one switched-off category and three in Specific mode. `acrossai-database` being off is
the exact condition behind the defect that motivated Feature 102 — searching the abilities list for
`database` returns nothing because those abilities never register.

## The number to verify the migration against

**The Feature 102 translation must produce exactly 52 `site_allowed = false` overrides on this site** —
assuming no ability already carries an explicit override, which FR-008 requires it to leave alone.

Derivation: every ability in `acrossai-database` (switched off), plus every ability in the three
Specific-mode categories that is not among their 3 + 4 + 2 ticked slugs.

Re-derive at any time without running the migration:

```bash
wp --exec="define('DB_HOST', 'localhost:$SOCK');" eval '
$defs = \AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry::instance()->get_definitions();
$cfg  = (array) get_site_option( "acrossai_library_config", array() );
$blocked = 0;
foreach ( $defs as $d ) {
  $c = $d["category"] ?? ""; $s = $d["slug"] ?? "";
  if ( ! isset( $cfg[$c] ) ) { continue; }
  $e = $cfg[$c];
  if ( ! ( isset($e["enabled"]) ? (bool)$e["enabled"] : true ) ) { $blocked++; continue; }
  if ( ( $e["mode"] ?? "all" ) !== "specific" ) { continue; }
  if ( empty( $e["sub_keys"][$s] ) ) { $blocked++; }
}
echo $blocked, "\n";'
```

## Environment notes

- `acrossai-abilities-manager` was **inactive** and was activated (with approval) to take this baseline.
  Activation ran `AcrossAI_Activator::activate()` — two BerlinDB table upgrades, the category-slug
  migration, File Manager seeding, and the Quick Connect redirect flag.
- `disable-ai` and `turn-off-ai-features` were active at first capture and suppressed abilities
  site-wide; **both have since been deactivated**, and the admin screen now lists abilities normally.
- **This is a valid single-site rehearsal environment** for quickstart §2. It is *not* a multisite
  network, so the R1 per-site translation path (quickstart §3) still needs a separate network to prove.

## Live verification of the Phase 2 REST work (same session)

| Check | Result |
|---|---|
| `GET /acrossai/v1/abilities/toolsets` | 200 |
| `GET /acrossai/v1/abilities` record shape | 200, `tab_group` key present |
| Core ability `tab_group` | `""` — correct, core carries no `meta.acrossai` |
| `tab_group=no-such-group` | 200, 0 rows — empty, not an error |
| Either route without a nonce | 403 `rest_forbidden` |
| Route order | `toolsets` index 0, slug wildcard index 4 |

## T080 — the inline admin payload, before and after

Measured on this site with the same `wp eval` socket workaround as above, reconstructing the exact
array that `admin/Main.php` inlined at line 294:

| | Rows | `strlen( wp_json_encode( … ) )` |
|---|---|---|
| Before (T001 shape, re-measured) | 389 definitions | **584,016 bytes (570.3 KB)** |
| After (T074) | — | **0** — the payload and its enqueue branch are deleted |

Every definition carried its full `input_schema` and `output_schema`, which is where the weight was.
It was injected via `wp_add_inline_script( …, 'before' )` on the Integrations screen, so it was parsed
on every load of that page. The replacement needs none of it: the toolset strip reads counts from
`GET /acrossai/v1/abilities/toolsets` and the column reads `tab_group` off the ability rows the table
was already fetching.

389 rows rather than the ~451 the planning doc estimated — this site has some optional host plugins
inactive, so their definitions never register. The removal is the same either way.
