# Quickstart: Verifying Feature 102

**Feature**: 102-abilities-toolset-tabs · Local site: `http://wordpress-7-0.local/wp-admin/`

## Gates

```bash
composer phpcs && composer phpstan     # zero errors
npx wp-scripts test-unit-js                                # all green
npm run build                           # clean
npm run validate-packages               # Tier hierarchy (Constitution VI)
```

`tests/phpunit/abilities/AbilitiesReadControllerTest.php` is a `WP_UnitTestCase` deliberately excluded
from `phpunit.xml.dist` — run the new `tab_group` coverage under wp-env, not CI.

## 1. The reported defect

The bug that motivated the feature: with the Database card switched off, searching the abilities list for
`database` returned "No abilities found", because those abilities were never registered.

1. Before upgrading, on a site with the Database category switched off, record the effective set:
   `wp eval 'print_r( array_keys( wp_get_abilities() ) );' > /tmp/before.txt`
2. Upgrade.
3. `wp eval 'print_r( array_keys( wp_get_abilities() ) );' > /tmp/after.txt`
4. Open the abilities list and search `database`.

**Expect**: the 17 `database/*` abilities now appear, each reading **Force Block**. `after.txt` is a
superset of `before.txt`; every newly present slug is Force Blocked, so the *usable* set is unchanged
(SC-003).

## 2. Translation correctness — the only irreversible step

> **On `wordpress-7-0.local` the expected result is exactly 52 Force Block overrides.** That site carries
> a real operator configuration — `acrossai-database` switched off, plus three Specific-mode categories
> with 3 / 4 / 2 ticked slugs — against 389 definitions, of which the gate permits 337 and blocks 52.
> See [baseline.md](./baseline.md) for the derivation and a command to re-check it without running the
> migration. A result other than 52 means the translation is wrong, and this is the cheapest place to
> find that out.

On a copy of a site with (a) a category switched off, (b) a category set to Specific with a few picks,
(c) an untouched category, and (d) at least one ability the administrator had explicitly Force Allowed:

| Check | Expect |
|---|---|
| Switched-off category | every ability Force Blocked |
| Specific category | exactly the unpicked abilities Force Blocked |
| Untouched category | no overrides written |
| Pre-existing explicit override | **unchanged** (FR-008) |
| Run the translation again | nothing changes (FR-009) |
| Category with >50 abilities (`acrossai-block`, 87) | all 87 resolved from the registry, not from the truncated `sub_keys` |

## 2b. Concurrency (SEC-008)

The translation is claimed with `add_option()` before it runs, so only one request can perform it.

```bash
# Freshly upgraded site, flag not yet set. Fire concurrent requests at the front end.
for i in $(seq 1 20); do curl -s -o /dev/null http://wordpress-7-0.local/ & done; wait
wp option get acrossai_library_gate_migration_done
```

**Expect**: the flag exists, the override rows are written exactly once, and no duplicate-key or deadlock
warnings appear in the debug log. A second full translation running concurrently is the regression.

## 3. Multisite — the case the plan exists for

The configuration is network-wide; the override table is per-site. On a network with ≥3 sites, at least
two of which have categories switched off:

1. Upgrade the network.
2. **Do not open wp-admin on one of the sites** — hit its REST API or an MCP client instead.
3. On every site, including that one, confirm its previously blocked abilities read Force Block.

**Expect**: each site translates on its own first request of any kind, guarded by its own done flag. The
site you never opened in wp-admin must be translated too; if it is not, SEC-001 has regressed.
`acrossai_library_config` is **still present** on multisite — that is intended, not a leak
(research.md R1).

**Failure signature**: a site whose blocked abilities are reachable means its translation never ran.
There is no notice and no log (spec Q1/Q2), so this check is the only detection.

## 4. Toolsets

At `?page=acrossai-abilities-manager`:

- The strip shows `All` plus one entry per toolset with counts; Rank Math appears, Elementor does not
  unless its plugin is active.
- Selecting a toolset narrows the table; the row count matches the strip count — **including on a site
  where a whole toolset was switched off before upgrading**, where the count must be 17, not 0 (SEC-002).
- The **Toolset** column shows `toolset/content` etc., and is empty for abilities you created yourself.
- The 13 dispatcher abilities do **not** appear inside their own toolsets.
- `?tab=cache` deep-links. Changing toolset resets to page 1 and clears any selection.
- Each entry is a real link: reachable by Tab, opened with Enter, middle-clickable into a new browser tab.
- Filtering by status **changes the result set** (SC-010) — previously it did nothing here.
- Back from an ability edit returns to the toolset you were on.

## 5. Retired surface

- `?page=acrossai-abilities-integrations&tab=elementor` → **301** to the abilities list, Elementor selected.
- The same URL with a nonsense tab → abilities list showing All, no error.
- Settings → Integrations shows the ACF switch **in its pre-upgrade state**. Switching it off stops ACF
  registering its abilities; switching it on restores them.
- `curl` the retired REST namespace → 404.
- `GET /acrossai/v1/abilities/toolsets` returns the counts object — **not** a 404 for an ability named
  "toolsets". A 404 here means the literal route registered after the wildcard
  (`BUG-REST-ROUTE-ORDER-LITERAL-BEFORE-WILDCARD`).

## 6. Payload

Record `strlen( wp_json_encode( … ) )` for the inline admin payload before and after. The ~451-row
definitions blob (with full JSON Schema) leaves the page entirely; put the numbers in the commit message.

## 7. MCP

`mcp-adapter-discover-abilities` returns the same set before and after the upgrade. If it grows, the
translation missed something — that is SC-003 failing.
