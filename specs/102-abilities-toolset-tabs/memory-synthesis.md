# Memory Synthesis

## Current Scope

Feature 102 retires the ability registration gate (`AcrossAI_Ability_Library_Processor::is_permitted()`),
converts what it blocked into per-ability `site_allowed` overrides via a one-time migration, folds the
Integrations screen's groupings into the abilities list as a Toolset filter column, relocates third-party
integration opt-ins to the shared settings page, and deletes the Integrations page, its REST namespace and
its bundles. Modules touched: `Modules/Library` (4 of 9 PHP files removed), `Modules/Abilities`
REST/Merger/Formatter, `AcrossAI_Ability_Registry_Query`, `admin/Partials`, `src/js/abilities`,
`src/js/ability-library` (deleted).

## Relevant Decisions

- **DEC-ABILITY-GROUP-IDENTIFIER-LOAD-BEARING** — `meta.acrossai.tab_group` is *not* display-only; the MCP
  tool catalogue is derived from it. (Reason: this feature surfaces `tab_group` as the Toolset column and
  filter. Status: Active. Source: DECISIONS.md)
- **DEC-ABILITY-GROUP-TAXONOMY** — thirteen groups is a **ceiling**, because Feature 100 makes each group an
  always-loaded MCP tool. (Reason: tabs are data-driven; adding a group now has MCP cost. Status: Active)
- **DEC-ABILITIES-DUAL-MODE-LIST** — `GET /abilities` branches `source=db` → DB query, else → registry
  merge; `format_merged_ability()` normalises shape. (Reason: both the `tab_group` filter and the Status
  filter fix live on this branch. Status: Active)
- **DEC-TOOLSET-SLUG-NAMESPACE** — `toolset/` is reserved for abilities acting on the ability catalogue
  itself. (Reason: the Toolset column renders `toolset/<group>`. Status: Active)
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** — the design prototype overrides the Constitution §III DataViews /
  DataForm mandate. (Reason: authorises the hand-rolled table and link strip. Status: Active)

## Active Architecture Constraints

- **AC-QUERY-LAYER-FILTERING** — list filtering belongs in the query builder, not the REST controller.
  (Reason: governs both `tab_group` and the FR-015a Status filter fix. Source: ARCHITECTURE.md)
- **AC-REGISTRY-QUERY** — filter/sort/paginate only via `AcrossAI_Ability_Registry_Query::query()`.
- **AC-REST-SPLIT** — split a REST controller past 400 lines; orchestrator + sub-controllers in `Rest/`.
- **ARCH-UNIFIED-ABILITIES-STORAGE** — the Abilities module owns the unified table; override rows are
  identified by source semantics. (Reason: the migration writes override rows here, not to a new store.)
- **AC-ENQUEUE-ADMIN** — enqueue may live in any `admin/Partials/` page class, wired from `Main.php`.
  (Reason: the new integrations settings section follows this, not `Admin\Main`.)

## Accepted Deviations

- **DEC-ABILITY-DEFINITION-CTOR-HOOKS** — ability-definition base classes may wire hooks in the constructor
  (deviation from §I Boot Flow Rule); explicitly formalises `AcrossAI_Integration_Ability_Base`.
- **ARCH-ADV-001** — `boot()` conditional hook wiring, scope limited to the Override Processor.
- **DEV-099-1** — custom controls instead of DataForm/DataViews where the surface has no search/sort needs.

## Relevant Security Constraints

- **SEC-01** — sanitize every slug arriving at a REST endpoint, max 255 chars. (Applies to the new
  `tab_group` argument and the `tab` value carried through the retirement redirect.)
- **SEC-04** — strict type comparison in access-control checks. (The migration reads `enabled` / `mode`
  and writes `site_allowed`; loose comparison here mis-translates.)
- **PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY** — a single filtered `current_user_can()` is inherently
  raise-only. (FR-023 moves `acrossai_integration_toggle_capability` to the settings save path; keep the
  single-check shape or it stops being raise-only.)

## Related Historical Lessons

- **PATTERN-OPTION-KEY-MIGRATION-OR-MONOTONIC** — idempotent, OR-monotonic option migration: never
  overwrite manager edits, **never demote a truthy opt-in**.
- **BUG-SPARSE-STORAGE-UNIFORM-DEFAULT-ASSUMPTION** — a sparse store whose keys do not share one default
  silently drops values when read with a uniform-default assumption.
- **BUG-INVENTORY-GREP-MISS** — removal task lists must run exhaustive `grep -rEn` across
  `includes/ src/ tests/ admin/` *before* approval; Feature 034's inventory missed four PHP files.
- **PATTERN-ASSET-DECOMMISSION-ORDER** — remove the PHP include first, then the webpack entry and sources,
  then clean `build/`.
- Feature 052 (2026-07-13) built the tab-scoped bulk enable/disable and `useLibraryTabSync` this feature
  removes; Feature 041 (2026-07-03) is the precedent for a hard-cut `meta.acrossai` migration.

## Conflict Warnings

1. **Soft — inverted defaults in one store.** `is_permitted()` treats an **absent** category as *permitted*;
   `is_integration_enabled()` treats an absent integration as *disabled*. The migration reads both from
   `acrossai_library_config`. Reading it with one default mis-translates one of the two
   (BUG-SPARSE-STORAGE-UNIFORM-DEFAULT-ASSUMPTION). Spec FR-006/FR-011 are correct as written; the plan must
   keep the two key types on separate paths.
2. **Soft — Q2 vs. "never demote a truthy opt-in."** The accepted risk (one attempt, no retry, no rollback)
   collides with PATTERN-OPTION-KEY-MIGRATION-OR-MONOTONIC for the ACF opt-in copy specifically: a failed
   copy demotes an enabled integration to off, contradicting FR-022. Recommend the opt-in copy be
   OR-monotonic and separately idempotent even though the override translation is not.
3. **Soft — synthetic integration rows after gate removal.** `push_definition()` gates only on
   `is_plugin_active()`, and `is_permitted()` already returns true for an absent entry, so these rows
   *already* reach `wp_register_ability()` and are rejected by design. Removing the gate makes that
   universal rather than introducing it — but BUG-WP-CORE-ABILITY-CATEGORY-PRE-REGISTRATION notes a
   `_doing_it_wrong` accompanies the rejection. Verify debug-notice volume; no spec change implied.
4. **Advisory — `tab_group` is an MCP boundary, not a label.** The spec's Toolset entity describes grouping
   only. Nothing in this feature may retag an ability, because that silently moves it between MCP tools.

No hard conflicts. Planning may proceed.

## Retrieval Notes

- Config absent → defaults (`docs/memory`, `specs`, synthesis gate on, optimizer off → markdown-only).
- Constitution present but 25 KB — not "small"; indexed via DEC-/AC- entries rather than read whole.
- `specs/102-abilities-toolset-tabs/memory.md` absent.
- INDEX.md (315 lines) read; ~20 entries considered. Selected 5 decisions, 5 constraints, 3 deviations,
  3 security constraints, 3 bug patterns, 2 worklog items — at budget.
- Two source claims verified against code rather than accepted from the index:
  `AcrossAI_Integration_Ability_Base::push_definition()` gating, and `is_permitted()`'s absent-key default.
- No durable memory file read in full. Within the 900-word synthesis budget.
