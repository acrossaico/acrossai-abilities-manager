# Feature 087 — Core-table engine audit & InnoDB conversion

**Status**: input brief for `/speckit-specify`. Written 2026-08-24.

## Problem

Modern MySQL/MariaDB defaults to InnoDB, but WordPress sites migrated from older MySQL versions (or restored from very old backups) sometimes still contain MyISAM tables on core WordPress tables (`wp_posts`, `wp_options`, `wp_users`, etc.). MyISAM lacks transactions, per-row locking, and modern crash recovery — its presence on write-heavy core tables is a real operational risk.

Today we have no ability to:

1. **Report** which core tables are on which engine (`database/list-db-tables` reports engines but for every table on the schema, mixing core + plugin + custom tables).
2. **Convert** a MyISAM core table to InnoDB safely. Doing it manually via SQL requires shell/DB access and skips the "did the mutation actually happen?" verification.

This is a small, focused ability set: one read + one gated DDL write. It's isolated in its own phase (087) so the DDL surface is reviewed independently.

## Proposed abilities

Slug convention per verb-first (Feature 058) under the `database/` namespace.

### 2 abilities — under `Database/` category, sub_group `engine`

| Slug | Purpose | Core APIs |
|---|---|---|
| `database/audit-core-table-engines` | Report the storage engine (InnoDB / MyISAM / other), data + index bytes, and existence for each of the 18 core WP tables. Accepts an optional list of core-table keys; defaults to all when omitted. Read-only. | `information_schema.TABLES` filtered by `TABLE_SCHEMA = DB_NAME` and `TABLE_NAME = <resolved physical>`. Physical name always resolved from `Database_Core_Table_Allowlist::resolve()` (`$wpdb->posts`, `$wpdb->options`, etc.). |
| `database/convert-core-tables-to-innodb` | Convert specified core tables to InnoDB via `ALTER TABLE %i ENGINE = InnoDB`. Live writes require `dry_run=false` AND `confirm=true`. Postcondition verified (engine re-read); mutation attribution separates statement outcome from postcondition. | `$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ENGINE = InnoDB', $physical ) )` with pre/post engine snapshot via `information_schema.TABLES`. Attributes each per-table outcome via `Database_Mutation_Attribution::classify()` and aggregates via `::aggregate()`. |

## Reused utilities

- **New from 086**: `Includes\Abilities\Utilities\Database_Core_Table_Allowlist` — 18-key logical → `$wpdb` property resolver. Both new abilities delegate all identifier resolution to this class; neither ever accepts a raw physical name from the caller.
- **New in this phase**: `Includes\Abilities\Utilities\Database_Mutation_Attribution` — encapsulates the tri-state (statement outcome / postcondition / mutation outcome) plus aggregation across per-table outcomes. Reusable by any future irreversible DDL ability.

## Common shape (both abilities)

- Namespace: `AcrossAI_Abilities_Manager\Includes\Abilities\Database`.
- Category slug: `acrossai-abilities-manager-database`.
- `audit-core-table-engines`: `readonly: true, destructive: false, idempotent: true`.
- `convert-core-tables-to-innodb`: `readonly: false, destructive: true, idempotent: true` — running it twice on the same table set is a no-op after the first successful run.
- Dual-gate: live writes require **both** `dry_run=false` AND `confirm=true`.
- Table names always resolved from `$wpdb` via the 18-key allowlist. Invalid keys are returned in a separate `invalid_tables` array; execution proceeds on the valid subset.
- All queries use `$wpdb->prepare()` with `%s` (schema/table names in `information_schema`) or `%i` (physical identifiers in `ALTER TABLE`).
- `meta.show_in_rest = true`, `meta.mcp = { public: false, type: 'tool' }`.

## Bootstrap wiring

Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()`:

- Add 2 new `new Database\<Class>();` lines adjacent to the Feature 086 block.

## Testing

Under `tests/phpunit/abilities/`:

- One suite-contract file `Test_Feature_087_Suite_Contract.php`:
  - Data-provider driven: correct slug + category, extends `Ability_Definition`, `manage_options` gate.
  - Audit ability is `readonly: true`; conversion is `destructive: true` + `idempotent: true`.
  - Conversion has `dry_run` + `confirm` inputs and defaults `dry_run: true`.
  - Conversion uses the `%i` identifier placeholder for `ALTER TABLE`.
  - Both abilities resolve identifiers via `Database_Core_Table_Allowlist`.
  - Conversion uses `Database_Mutation_Attribution::classify()` and `::aggregate()`.
  - Conversion re-reads `information_schema.TABLES` for postcondition verification.
  - Bootstrap registers both classes.
  - `Database_Mutation_Attribution` utility exists with the expected public API (constants + classify + aggregate).

Target: ~15 tests / ~30 assertions.

## Delivery

Feature branch `087-database-engine-conversion` off `086-database-health-and-safe-writes` (stacked pattern proven with 080–085).
