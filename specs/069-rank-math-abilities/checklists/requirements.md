# Requirements Checklist: Rank Math Ability Suite

**Feature**: 069-rank-math-abilities

Tick as each batch lands. A batch is not done until its rows are ticked.

## Coverage discipline

- [ ] No ability duplicates any of Rank Math core's 13 baseline abilities (FR-005)
- [ ] Every slug is `rank-math/<verb>-<noun>`, verb-first (FR-001)
- [ ] 61 abilities registered; the count matches `contracts/abilities.md`
- [ ] No raw Rank Math option read/write ability exists (superseded by the plugin's generic option abilities)
- [ ] No bulk role-capability writer exists (superseded by `users/add-role-capability` / `remove-role-capability`)
- [ ] No `.htaccess`, version-rollback or beta-optin ability exists (out of scope by decision)

## Registration and gating

- [ ] `Category_Registrar::register()` returns early when `! class_exists( '\RankMath\Helper' )` (FR-003)
- [ ] No ability class is instantiated when Rank Math is absent (FR-003)
- [ ] Every `execute()` re-asserts availability as defence in depth (FR-004)
- [ ] Every ability carries `meta.acrossai.tab_group = 'rank-math'` (FR-002)
- [ ] Every ability carries `category = 'acrossai-abilities-manager-rank-math'`
- [ ] Every ability carries `show_in_rest => true` and `mcp => ['public' => false, 'type' => 'tool']`
- [ ] `ability()` emits only the eight Registry-allowlisted arg keys
- [ ] Entitlement-backed abilities (#39–#44) register unconditionally and gate at runtime (FR-013)
- [ ] The bootstrap comment states that the runtime gating is deliberate and unlike `register_elementor_pro_abilities()`

## Settings safety

- [ ] All settings writes route through `Option_Center::save_settings()` (FR-006)
- [ ] Every writable field has an explicit type in `Settings_Registry`; no field reaches the sanitizer untyped (FR-006)
- [ ] `Settings_Writer` rejects any field absent from the requested panel, without partial application (FR-007)
- [ ] All six `DENIED_KEYS` are rejected, and stripped again before the `save_settings()` call (FR-007)
- [ ] Every `TYPE_GROUP` value is `array_values()`-reindexed before the write (FR-008)
- [ ] `$updated` is always an array, never `null` (`check_updated_fields()` TypeErrors on null)
- [ ] `$is_reset` is always `false`
- [ ] Writes return only the fields touched, never the whole option blob
- [ ] Every panel entry carries a `source` file:line citation for re-diffing

## Destructive operations

- [ ] All 12 destructive abilities declare `annotations.destructive = true` (FR-009)
- [ ] All 12 accept `confirm` in `input_schema` and return `confirmation_required` without it (FR-009)
- [ ] `change-redirection-status` is **not** destructive — its four transitions are reversible (FR-010)
- [ ] Async tools set `async: true` and a `poll_hint` (FR-011)
- [ ] `import-settings` returns the created backup key so the caller can undo

## Security

- [ ] Every `permission_callback` composes the house floor with the mapped `rank_math_*` cap (FR-012)
- [ ] The capability map in `contracts/abilities.md` matches the code
- [ ] `acrossai_abilities_manager_rank_math_permission` is evaluated inside `Rank_Math_Guard::can()` so all abilities honour it (FR-012)
- [ ] `get-settings-panel` re-checks the panel-specific cap inside `run()` and this is documented as deliberate
- [ ] The four post-scoped abilities perform a per-object `current_user_can( 'edit_post', $id )` inside `run()`
- [ ] Credit-consuming abilities verify balance before any remote request (FR-014)
- [ ] All input is sanitized before reaching Rank Math; no unsanitized value is passed through
- [ ] No direct SQL; all data access goes through Rank Math's `DB` / `Helper` classes
- [ ] `wp_remote_get()` used for the loopback, never `file_get_contents()`

## Architecture

- [ ] No ability class references a `\RankMath\*` symbol (FR-015), asserted by test
- [ ] All 12 helper classes are `final` and 100% static — no singletons
- [ ] `Base_Rank_Math_Ability` is the sole assembler of `ability()`
- [ ] `Base_Rank_Math_Ability::execute()` is the sole enforcer of guard ordering
- [ ] `Settings_Writer` is the sole caller of `Option_Center::save_settings()`
- [ ] `Maintenance_Tools` uses a static `[class, method]` dispatch map, not `apply_filters` (research F3)
- [ ] `get-rendered-head` uses an HTTP loopback, never in-process (FR-016, research F4)
- [ ] `Analytics_Repository` calls `set_date_range()` explicitly and never caches the `Stats` instance
- [ ] `list-redirections` supports `status=trashed` (FR-017)
- [ ] module state changes refresh rewrite rules and fire `rank_math/module_changed` (FR-018) — satisfied by Rank Math's own `set-module-status` since 1.0.277; ours was retired

## Quality gates

- [ ] PHPStan level 8 passes with no new `ignoreErrors` entries
- [ ] `feature-069-unit` testsuite block lists every new test file explicitly in `phpunit.xml.dist`
- [ ] All source-inspection tests pass without Rank Math installed
- [ ] `Settings_Registry`, the redirection serializers and `Maintenance_Tools::normalize_result()` have behavioural unit tests
- [ ] Integration checks 1–9 documented in `quickstart.md` and skipped rather than faked in CI
- [ ] Admin UI verified at Batches 1, 2 and 7 (tab renders; absent without Rank Math)
- [ ] Security review completed
- [ ] `README.txt` changelog, `Stable tag` and plugin-header `Version` bumped together
- [ ] POT regenerated

## Success criteria

- [ ] SC-001 — 61 abilities under a Rank Math tab; tab absent without Rank Math
- [ ] SC-002 — single-field write leaves the rest of the blob byte-identical
- [ ] SC-003 — multi-line value round-trips intact
- [ ] SC-004 — no destructive ability acts without `confirm: true`
- [ ] SC-005 — envelope on every path; no fatal, no raw `WP_Error`, including with Rank Math deactivated mid-session
- [ ] SC-006 — PHPStan level 8 and the feature suite pass
- [ ] SC-007 — zero-credit call makes no remote request
- [ ] SC-008 — module toggle leaves no stale rewrite rules
