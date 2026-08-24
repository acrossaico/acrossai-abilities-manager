# Feature 086 — Database health audits & safe writes

**Status**: input brief for `/speckit-specify`. Written 2026-08-24.

## Problem

Our current `database/*` surface exposes raw SQL primitives (select / insert / update / delete rows, extract schema, list tables, optimize, get stats, search-replace) and `cache/*` covers transient CRUD, but two operational surfaces are missing:

1. **Health diagnostics** — no bounded, single-call read that reports the overall database state (storage totals, engine mix, index issue counts, options bloat, expired-transient count). Site owners running maintenance workflows have to hand-compose SQL against `information_schema` to answer "is my DB healthy?".
2. **Safe bounded writes** — the existing `cache/delete-expired-transients` runs to completion with no dry-run, no cap, and no confirm gate. There is no ability that toggles the `autoload` flag on specific options — the only path today is `options/update-option`, which requires re-writing the value and doesn't distinguish autoload changes from value changes.

Autoload sprawl (bloated `alloptions`) and stale-transient rows are two of the most common causes of slow WordPress admin pages. Automations should be able to inspect and remediate these safely.

This feature adds 5 new abilities filling both gaps, plus a small additive hardening on the existing `cache/delete-expired-transients` so callers can opt into dry-run + limit without a rename.

## Proposed abilities

Slug convention per verb-first (Feature 058) under the `database/` namespace.

### Health diagnostics (3) — read-only, bounded, never returns values

| Slug | Purpose | Core APIs |
|---|---|---|
| `database/audit-health` | One-call orientation read combining storage totals, engine mix, index issue count, and options rollup. Delegates detail to the two below. | Internally invokes `audit-index-health` (limit=1) + `audit-options-health` (limit=5). No new SQL. |
| `database/audit-index-health` | Bounded paginated snapshot of table + index metadata for the current site: engine, row estimate, data/index/free bytes, index shape (name, unique, columns, cardinality). Missing-PRIMARY-KEY issue detection. | `information_schema.TABLES` + `STATISTICS` filtered by `TABLE_SCHEMA = DB_NAME` and `TABLE_NAME LIKE $wpdb->prefix%`. Multisite: rejects sibling-blog tables (`wp_2_*`) when `prefix === base_prefix`. Limit 1–100, offset paging. |
| `database/audit-options-health` | Aggregate options-table diagnostics: option count, total value bytes, autoload count/bytes, oversized autoload count (>256KB per option), transient row count, expired transient count, top-N autoloaded options by byte size. **Never returns option values.** | Aggregated `COUNT/SUM/OCTET_LENGTH` over `$wpdb->options` with autoload allowlist (`yes|on|auto|auto-on`); transient identification by name pattern. Limit 1–50 for top-N. |

### Safe writes (2) — dry-run first, confirm-gated, postcondition verified

| Slug | Purpose | Core APIs |
|---|---|---|
| `database/cleanup-expired-transients` | Delete expired transient timeout + value rows in a bounded batch. **Never returns transient names or values** — counts only. Live writes require `dry_run=false` AND `confirm=true`. | Selects up to `limit` (default 100, max 500) expired timeout rows from `$wpdb->options`, then per-row deletes the timeout + companion value via `delete_option()`. Pre/post expired-count snapshot. |
| `database/set-option-autoload` | Toggle the `autoload` flag on up to 25 explicit option names. **Never reads or writes option values** — updates only the `autoload` column. Rejects transient names, control chars, names > 191 chars. Live writes require `dry_run=false` AND `confirm=true`. | Per-name `$wpdb->update()` targeting only the `autoload` column, followed by re-read for postcondition verification. |

### Additive hardening — existing `cache/delete-expired-transients`

Add optional `dry_run` (default `false` — preserves current behavior) and `limit` (default `0` — unlimited) params. Non-breaking: no rename, no schema removal, existing callers unaffected.

## Reused utilities

- **`Ability_Definition`** parent class.
- **`Update_Option::BLOCKED_OPTIONS`** — pattern reference for the autoload rejection list.
- **`cache/list-transients`** cap pattern — mirror for options / index audit `limit` shape.
- **New: `Includes\Abilities\Utilities\Database_Core_Table_Allowlist`** — 18-key logical → physical name allowlist used by 086 index-audit multisite scoping and future engine ops (087). Prevents arbitrary identifier injection at the schema level.

## Common shape (all 5 new abilities)

- Namespace: `AcrossAI_Abilities_Manager\Includes\Abilities\Database`.
- Category slug: `acrossai-abilities-manager-database`.
- Reads (`audit-health`, `audit-index-health`, `audit-options-health`): `readonly: true, destructive: false, idempotent: true`.
- Writes (`cleanup-expired-transients`, `set-option-autoload`): `readonly: false, destructive: true, idempotent: true`.
- All writes are dry-run by default; live writes require **both** `dry_run=false` AND `confirm=true`.
- All string inputs sanitized; option-name inputs validated against control chars and name-length cap (191 chars).
- All queries use `$wpdb->prepare()` with proper placeholders; interpolated identifiers are `$wpdb->options` / `$wpdb->{key}` (never caller-supplied).
- Multisite awareness in index audit: filters to current-site prefix, rejects sibling-blog tables.
- Options / autoload audits + cleanup return **metadata only** (names optional, values never).
- `meta.show_in_rest = true`, `meta.mcp = { public: false, type: 'tool' }`.

## Bootstrap wiring

Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()`:

- Add 5 new `new Database\<Class>();` lines adjacent to the existing Feature 063 Database block (`Get_Db_Prefix`).

## Testing

Under `tests/phpunit/abilities/`:

- One suite-contract file `Test_Feature_086_Suite_Contract.php` (matches the 081–085 pattern):
  - Data-provider driven per-class assertions: correct slug + category, extends `Ability_Definition`, `manage_options` gate.
  - Audit abilities all `readonly: true`.
  - Safe-write abilities have `dry_run` + `confirm` inputs and default `dry_run: true`.
  - Bounded limits: index-audit ≤ 100, options-audit ≤ 50, transient cleanup ≤ 500, autoload names ≤ 25.
  - Options audit / transient cleanup output schemas do not expose values or names.
  - `set-option-autoload` rejects transient names.
  - Bootstrap registers all 5 new classes.
  - Utility class `Database_Core_Table_Allowlist` exists with the expected public API.
  - `cache/delete-expired-transients` gains `dry_run` + `limit` params additively (default `dry_run=false`).

Target: ~24 tests / ~55 assertions.

## Delivery

Feature branch `086-database-health-and-safe-writes` off `main`. Stacked pattern proven with 080–085.
