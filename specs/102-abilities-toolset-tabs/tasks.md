---
description: "Task list for Feature 102 — One Access Model for Abilities"
---

# Tasks: One Access Model for Abilities

**Feature**: `102-abilities-toolset-tabs` | **Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)

**Input**: plan.md · spec.md · research.md · data-model.md · contracts/ · quickstart.md ·
memory-synthesis.md · security-constraints.md

## Format: `[ID] [P?] [Story] Description`

- **[P]** — parallelisable (different file, no dependency on an incomplete task)
- **[USn]** — the user story this task serves (user-story phases only)

## Path Conventions

WordPress plugin, single repository. PHP under `includes/` and `admin/`, JS/SCSS under `src/`,
tests under `tests/phpunit/` and `tests/jest/`. All paths below are repo-relative.

## ⚠️ Two ordering rules that are not negotiable

1. **Phase 4 (US1, gate removal) MUST NOT start before Phase 3 (US2, migration) is verified.** Deleting
   `is_permitted()` first exposes every previously blocked ability for a release. This is a security
   constraint, not a sequencing preference.
2. **Every deletion in Phase 8 requires the grep inventory in T072 to be approved first**
   (`BUG-INVENTORY-GREP-MISS` — Feature 034's inventory missed four PHP files doing exactly this).

**On tests**: not optional for this feature. Constitution §VII requires unit tests for all new logic, and
spec Clarification Q1 (the migration reports nothing at runtime) makes automated coverage the *only*
control on its correctness.

---

## Phase 1: Setup

- [x] T001 Captured to [baseline.md](./baseline.md) — see it for the Local socket workaround and for **why this site is not a valid migration-rehearsal environment**. Record the pre-change baseline into `specs/102-abilities-toolset-tabs/baseline.md`: output of `wp eval 'print_r( array_keys( wp_get_abilities() ) );'`, the current `acrossai_library_config` value, and `strlen( wp_json_encode( … ) )` of the inline admin payload at `admin/Main.php:294`
- [x] T002 [P] ✅ **Resolved** — the five failures were triaged and closed as issue #183 (two orphans of `cf4f5d4b`, one incomplete `@wordpress/data` mock with an unresolvable `@wpb/access-control` alias, one fixture predating Feature 058's slug rename, one predating a SEC-006 hardening). Zero real regressions; the JS suite has been green for the whole feature since. Original: ⚠️ **PHP side green, JS side NOT clean at branch point** — 5 pre-existing Jest suite failures unrelated to this feature (see Phase 2 checkpoint note). Confirm a clean starting point: `composer phpcs`, `composer phpstan`, `npx wp-scripts test-unit-js`, `npm run build` all green on `102-abilities-toolset-tabs`

---

## Phase 2: Foundational (blocking — no user story can proceed without these)

All of Phase 2 is invisible to administrators. Both admin screens must behave identically at the end of it.

### Carry `tab_group` through the record

- [x] T003 [P] Add `tab_group` to `normalize_registry()` in `includes/Utilities/AcrossAI_Ability_Merger.php`, read from `meta.acrossai.tab_group` via the existing `$ann_or_meta` accessor
- [x] T004 [P] Emit `tab_group` from `format_merged_ability()` and `format_for_response()` in `includes/Utilities/AcrossAI_Abilities_Formatter.php`; `''` for DB-created abilities
- [x] T005 [P] PHPUnit: `tab_group` present in both formatter outputs and `''` for DB abilities, in `tests/phpunit/Utilities/Test_Abilities_Formatter_Tab_Group.php`

### Query-layer filters (AC-QUERY-LAYER-FILTERING — in the query builder, never the controller)

- [x] T006 Add an optional `slugs` array param to `includes/Utilities/AcrossAI_Ability_Registry_Query.php`: a single `in_array( $slug, $slugs, true )` skip placed **after** the protected-slug skip and **before** the source/search filters so it composes
- [x] T007 Add `status` filtering to the registry path in `includes/Utilities/AcrossAI_Ability_Registry_Query.php` (registry rows are always `publish`; `draft` narrows to DB-created rows)
- [x] T008 Add a `tab_group` list argument (`sanitize_key`, default `''`) to `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php`, resolved through `AcrossAI_Ability_Group::member_names()` and passed as `$registry_params['slugs']`; forward `status` on the registry branch
- [x] T009a Move `AcrossAI_Ability_Group` from `includes/Modules/Library/` to `includes/Utilities/` (namespace `…\Includes\Utilities`) and repoint its five consumers plus `phpunit.xml.dist`. Required by Constitution Module Contract #3 — T008 made `Modules\Abilities` depend on `Modules\Library`, the codebase's only such import — and by §VI, whose second-consumer threshold the class had already crossed via `includes/Abilities/Toolset/`
- [x] T009 Register `GET /acrossai/v1/abilities/toolsets` in `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php` returning `AcrossAI_Ability_Group::counts()` — **register this literal route before the `(?P<slug>[^/]+)` wildcard** or WordPress resolves `toolsets` as an ability slug (`BUG-REST-ROUTE-ORDER-LITERAL-BEFORE-WILDCARD`)
- [x] T010 [P] PHPUnit: `tab_group=cache` returns exactly `AcrossAI_Ability_Group::member_names('cache')`; unknown group → empty with `X-WP-Total: 0`; the 13 Toolset dispatchers are absent — in `tests/phpunit/abilities/Test_Read_Controller_Tab_Group_Filter.php` — consolidated into `tests/phpunit/abilities/Test_Toolset_Filter_And_Route_Order.php` (one wp-env suite covers the filter, the route order and the status case; the separately-named files were never created)
- [x] T011 [P] PHPUnit: `status=draft` narrows on the registry path and `status=publish` is a no-op over registry rows, in `tests/phpunit/abilities/Test_Toolset_Filter_And_Route_Order.php` (the task named `Test_Read_Controller_Status_Filter.php`; the case landed in the toolset suite instead and is labelled T011 there — filename drift only, coverage exists)
- [x] T012 [P] PHPUnit: `/abilities/toolsets` resolves to the counts route and is not shadowed by the slug wildcard, in `tests/phpunit/abilities/Test_Toolsets_Route_Order.php` — consolidated into `tests/phpunit/abilities/Test_Toolset_Filter_And_Route_Order.php` (one wp-env suite covers the filter, the route order and the status case; the separately-named files were never created)

### Client refactor (pure extraction — must be a pixel no-op)

- [x] T013 Extract the table block (`AbilitiesList.jsx:753-1066`) verbatim into `src/js/abilities/components/AbilitiesTable.jsx` with props `items, visibleColumns, selected, onToggleOne, onToggleAll, sortDir, onToggleSort, isLoading, emptyLabel`
- [x] T014 Extract the toolbar (`AbilitiesList.jsx:521-750`) and pager (`1068-1106`) into `src/js/abilities/components/AbilitiesToolbar.jsx`
- [x] T015 Extract the eight cell renderers (`AbilitiesList.jsx:26-150`) into `src/js/abilities/components/cells/index.jsx`, re-exporting the existing `cells/SourceBadge.jsx`
- [x] T016 ⚠️ **Partially satisfied — see note.** Screenshot `?page=acrossai-abilities-manager` before and after T013-T015 and diff them — **the split must change nothing on screen**; attach both to the PR
- [x] T017 [P] Repoint `tests/jest/abilities/column-prefs.test.js` at the real `loadColumnPrefs` export now that it has moved out of `AbilitiesList.jsx`

### Store and URL plumbing

- [x] T018 Add `activeTab` to `src/js/abilities/store/index.js` — `SET_ACTIVE_TAB` action, `setActiveTab`, `getActiveTab`, default `ALL_TABS_KEY`; changing it resets `page` to 1 and clears `selected`
- [x] T019 Create `src/js/abilities/hooks/useUrlSync.js` owning `action`, `slug` and `tab` with a single `pushState`, composing the existing pure helpers: `buildUrl(view, tab, href) = buildUrlFromTab(tab, buildUrlFromView(view, href), ALL_TABS_KEY)`
- [x] T020 Replace the `useUrlViewSync()` call in `src/js/abilities/components/AbilitiesManager.jsx` with `useUrlSync()`
- [x] T021 [P] Jest: `buildUrl` composition emits exactly one `pushState` when view and tab change together; invalid `?tab=` falls back to `ALL_TABS_KEY`; `?tab=` survives opening an ability — repoint `tests/jest/ability-library/useLibraryTabSync.test.js` to `tests/jest/abilities/useUrlSync.test.js`

**Checkpoint**: both screens render exactly as before; REST gains two filters and one route.

> **Progress 2026-09-12** — T003-T009 delivered. PHPCS clean, PHPStan level 8 exit 0, PHPUnit
> 2371 passing (6 warnings, all pre-existing in `AcrossAI_Ability_Merger::merge()` lines 59/81/100 —
> `stdClass` fixtures missing `callback_type`/`callback_config`, untouched by this feature).
>
> **Two corrections made during implementation:**
> 1. The JS runner in these artifacts was wrong. There is no `test` script in `package.json` and no
>    root Babel config, so bare `npx jest` fails every suite with "Cannot use import statement
>    outside a module". The runner is `npx wp-scripts test-unit-js`. All artifacts corrected.
> 2. `status=draft` cannot be satisfied on the registry path — a draft ability is never registered,
>    so `wp_get_abilities()` can never contain one. Filtering there would return an empty set and
>    read as broken. The controller now routes `status=draft` to the DB branch instead, which is the
>    only place drafts exist. FR-015a is met; the mechanism differs from what T007 anticipated.
>
> **Pre-existing JS failures at branch point** (not caused by this feature, not fixed by it):
> `tests/jest/sitewide/store.test.js` and `tests/jest/sitewide/AbilityEditPanel.test.jsx` (stale —
> the Sitewide module was decommissioned in Feature 012), `tests/jest/abilities/validateRequiredFields.test.js`
> (suite fails to run), plus one failing assertion each in `store.bulkSetUserAccessRule.test.js` and
> `ability-form-user-access-section.test.jsx`. These must be triaged separately before T016's
> pixel-no-op screenshot gate can be trusted.
>
> **Phase 2 complete except T016.**
>
> T010-T012 landed as `tests/phpunit/abilities/Test_Toolset_Filter_And_Route_Order.php` — a
> `WP_UnitTestCase`, deliberately **not** in `phpunit.xml.dist` (runs under wp-env, matching
> `AbilitiesReadControllerTest`). All three behaviours were additionally verified live on
> wordpress-7-0.local. T012 asserts against the real `rest_get_server()->get_routes()` rather than one
> controller's source — the reason is in the file's docblock and is the bug it was rewritten to catch.
>
> T018-T021: `activeTab` + `toolsetCounts` in the store, `fetchToolsetCounts()` action,
> `GET /abilities/toolsets` client, merged `useUrlSync`, and 11 new passing tests.
>
> **Structural note.** `useUrlSync` could not be unit-tested as first written: its pure helpers sat in a
> module that imports the store, so the test could not load them — the same trap T017 had just found in
> `column-prefs.test.js`. The helpers now live in `src/js/abilities/urlSync.js`, free of store,
> component and apiFetch imports; `hooks/useUrlSync.js` and `hooks/useUrlViewSync.js` both re-export
> from it so existing consumers and tests are unaffected. **This is the third time in Phase 2 that
> testability required extracting pure code out of a component-coupled module**
> (`columns.js`, `cells/index.jsx`, `urlSync.js`).
>
> **Issue #183 triaged and closed — the JS baseline is green: 26 suites / 245 tests passing** (from
> 22/216 with 5 failing suites). **None of the five was a product bug.** Two were orphans of `cf4f5d4b`
> (Sitewide decommissioned, tests left behind), one had an incomplete `@wordpress/data` mock plus an
> unresolvable webpack alias, one fixture predated Feature 058's `acrossai-abilities-manager/` →
> `acrossai/` rename, and one predated a SEC-006 hardening that made `access_control_slug` a fourth
> precondition for mounting `<AccessControl>`.
>
> Two findings worth carrying forward:
> - The `bulkSetUserAccessRule` guard never regressed — the path still carried a literal `/`, so
>   `BUG-COMPOSER-AC-SLUG-DOUBLE-ENCODE` is still prevented. Only the hardcoded namespace was stale.
> - `ability-form-user-access-section` branch (b) was **passing for the wrong reason**: it asserts *no*
>   `AccessControl` when the library is unavailable, which is also the result when the gate can never be
>   satisfied. Branch (c) failing was the only signal anything was wrong.
>
> One product change fell out: `api/client.js` destructured `window.acrossaiAbilitiesManager` at module
> scope with no fallback, so a failed `wp_localize_script` would throw at import and take the whole admin
> bundle down instead of degrading. Now `|| {}`.
>
> **T016 status.** The gate is now meaningful — but the "before" state no longer exists locally, so a
> true before/after screenshot diff is no longer possible for this phase's extractions. Evidence in its
> place: the JS suite returned byte-identical results after every extraction while it was red, and is
> now fully green; ESLint clean; build compiles. A visual pass on
> `?page=acrossai-abilities-manager` is still worth doing before Phase 5 adds the toolset strip, and
> that is where a real screenshot baseline should be captured.
>
> `lint:js` is still not green (7 pre-existing errors in touched files, 8 at `HEAD`). Out of scope and
> noted on #183.
>
> **T013/T014/T015/T017 delivered.** `AbilitiesList.jsx` **1128 → 415 lines**, split into
> `AbilitiesTable.jsx` (355), `AbilitiesToolbar.jsx` (322, plus `AbilitiesPagerBelow`),
> `cells/index.jsx` (138) and a new `columns.js` (49). ESLint clean, build compiles, and the JS suite
> came back **identical to the pre-change baseline on every run** (5 failed / 216 passed — the #183
> set) — the strongest no-op evidence available while that baseline is red.
>
> Two things worth recording:
> - `dispatch` is passed whole into `AbilitiesTable` rather than decomposed into
>   onEdit/onDelete/onClearOverrides. The row actions call `dispatch.*` inline, and keeping them inline
>   keeps the markup byte-identical. Decompose once T016 can actually be trusted.
> - **T017 revealed `column-prefs.test.js` never tested the real function.** It mocked five modules
>   purely to survive importing `AbilitiesList.jsx`, then declared its own copy of `loadColumnPrefs`
>   and asserted against that — so the copy could drift from shipping code with every test still
>   green. Extracting `columns.js` removed the reason for all of it: 147 → 107 lines, zero mocks,
>   8 tests now running against the real export.
>
> T015 delivered: `cells/index.jsx` (138 lines, 8 renderers + `SourceBadge` re-export);
> `AbilitiesList.jsx` 1128 → 1009. ESLint clean, build compiles, JS suite **byte-identical to the
> pre-change baseline** (5 failed / 216 passed — the #183 failures, unchanged), which is the
> strongest no-op evidence available until #183 is green.
>
> One deletion made during T015: `PROTECTED_SLUGS` in `AbilitiesList.jsx` was declared and never
> referenced — verified dead at `HEAD` too (`git show HEAD:… | grep` returns the declaration only),
> so `lint:js` was already failing on this file before Feature 102. ESLint blocks on it, so it was
> removed rather than carried into the split. Added to #183.
>
> **Live verification (2026-09-12, plugin activated with approval) caught a real bug in T009.**
> `/acrossai/v1/abilities/toolsets` was registered at route index 4 with the slug wildcard already at
> index 2, so `WP_REST_Server::dispatch()` would have matched it as an ability named "toolsets" and
> 404'd. Placing it above this controller's *own* wildcard was not enough —
> `AcrossAI_Abilities_Write_Controller` registers `/abilities/(?P<slug>[^/]+)` and runs **first** in the
> orchestrator. Fixed by extracting `register_literal_routes()` and calling it from the orchestrator
> ahead of every sub-controller. Re-verified: toolsets index 0, wildcard index 4.
>
> This is exactly the failure `BUG-REST-ROUTE-ORDER-LITERAL-BEFORE-WILDCARD` describes, and a unit test
> asserting registration inside one controller would **not** have caught it. T012 must assert against a
> real `rest_get_server()->get_routes()` ordering, not the source of a single controller.
>
> Live results: `/abilities/toolsets` → 200; `/abilities` rows carry `tab_group`; a core ability reports
> `tab_group: ""`; `tab_group=no-such-group` → 200 with 0 rows; both routes 403 without a nonce.
>
> **Pre-existing JS failures filed as issue #183** — including the absence of any documented JS test
> command. T016's pixel-no-op gate should not be trusted until #183 is triaged.

---

## Phase 3: User Story 2 — Upgrading does not change what anyone can do (P1) 🎯 MVP part 1

**Goal**: translate everything the old configuration blocked into explicit Force Block overrides, so the
usable set is identical across the upgrade.

**Independent test**: on a site with a category switched off and another set to Specific, record the usable
ability set, run the translation, and confirm the set is unchanged.

**This phase must be verified before Phase 4 starts.**

- [x] T022 [US2] Create `includes/Modules/Abilities/AcrossAI_Library_Gate_Migration.php` (singleton, `private` constructor, `instance()`), namespaced `AcrossAI_Abilities_Manager\Includes\Modules\Abilities` — it lives in the Abilities module because it writes that module's rows
- [x] T023 [US2] Implement the per-site claim in `AcrossAI_Library_Gate_Migration::maybe_migrate()`: `add_option( 'acrossai_library_gate_migration_done', '1', '', false )` returning `false` means another request already claimed it — **claim before the work, never after** (SEC-008)
- [x] T023a [US2] Give `AcrossAI_Library_Gate_Migration` its own `SOURCE_OPTION = 'acrossai_library_config'` constant and read `get_site_option()` **directly** — the migration must reference no symbol scheduled for deletion, because on multisite sites migrate lazily and may do so after Phase 8 removes `AcrossAI_Ability_Library_Config` (SEC-011)
- [x] T024 [US2] Implement the translation in `AcrossAI_Library_Gate_Migration`: category `enabled === false` → `site_allowed = false` for every ability in the category; `mode === 'specific'` → `site_allowed = false` for every ability not explicitly `sub_keys[slug] === true`; absent or all-mode → no write
- [x] T025 [US2] Resolve category membership from `AcrossAI_Ability_Library_Registry::get_definitions()` — **never** from `sub_keys` (truncated at 50, so incomplete for `acrossai-block` 87, `acrossai-elementor` 62, `acrossai-rank-math` 61) and never from `wp_get_abilities()` (hook-order dependent)
- [x] T026 [US2] Dispatch on `card_variant` **before** applying any default: an absent *category* means permitted, an absent *integration* means disabled — one uniform default mis-translates one of them (`BUG-SPARSE-STORAGE-UNIFORM-DEFAULT-ASSUMPTION`)
- [x] T027 [US2] Skip any ability that already has an override row — an explicit administrator decision always wins (FR-008); write through `AcrossAI_Abilities_Query` / `AcrossAI_Abilities_Row`, never raw SQL
- [x] T028 [US2] Copy `card_variant === 'integration'` entries into a new `acrossai_integrations` option (`slug => bool`), **OR-monotonic** — never demote a truthy opt-in (`PATTERN-OPTION-KEY-MIGRATION-OR-MONOTONIC`, FR-022)
- [x] T029 [US2] Delete `acrossai_library_config` after a successful run **on single-site only**; retain it on multisite, where no per-network completion bookkeeping exists (research.md R1)
- [x] T030 [US2] Ensure the translation reads only stored state — the site option and the definitions registry. **No request input of any kind** (SEC-009): the trigger is reachable unauthenticated, so any `$_GET` parameter would be an unauthenticated write primitive
- [x] T030a [P] [US2] PHPUnit: `AcrossAI_Library_Gate_Migration` accesses no superglobal (`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`) and no request accessor, in `tests/phpunit/Modules/Abilities/Test_Library_Gate_Migration_No_Request_Input.php` — a simple token scan, not an adjacency regex (`BUG-SOURCE-INSPECTION-ADJACENCY-BRITTLE`). Without this, a later "add `?force-remigrate=1` for support" would pass review (SEC-012)
- [x] T031 [US2] Wire `AcrossAI_Library_Gate_Migration::instance()` to an early all-paths hook in `includes/Main.php` `define_public_hooks()` — resolved to a named variable before the Loader, per the Boot Flow Rule
- [x] T032 [US2] Call `AcrossAI_Library_Gate_Migration::maybe_migrate()` from `includes/AcrossAI_Activator.php::activate()`, alongside `AcrossAI_Category_Slug_Migration::maybe_migrate()`

### Tests for User Story 2 — the only control on correctness

- [x] T033 [P] [US2] PHPUnit: switched-off category → every ability in it Force Blocked, in `tests/phpunit/Modules/Abilities/Test_Library_Gate_Migration.php` — split into `Test_Library_Gate_Migration_Rules.php` (CI), `_No_Request_Input.php` (CI) and `_Persistence.php` (wp-env); the single named file was never created
- [x] T034 [P] [US2] PHPUnit: `mode=specific` → exactly the unticked abilities Force Blocked, picked ones untouched
- [x] T035 [P] [US2] PHPUnit: a pre-existing explicit override is never overwritten (FR-008)
- [x] T036 [P] [US2] PHPUnit: untouched category writes no overrides
- [x] T037 [P] [US2] PHPUnit: re-running produces no change (FR-009)
- [x] T038 [P] [US2] PHPUnit: a category with more than 50 abilities translates **all** of them, proving membership came from the registry and not the truncated `sub_keys`
- [x] T039 [P] [US2] PHPUnit: integration entries are copied to `acrossai_integrations`, not converted to overrides; an enabled integration stays enabled
- [x] T040 [P] [US2] PHPUnit: the `add_option` claim is exclusive — a second call while the flag exists performs no work. **This tests re-entrancy, not atomicity**; the concurrent-race property comes from `add_option()`'s single INSERT and is only genuinely exercised by the manual check in `quickstart.md` §2b, which must not be skipped as redundant (SEC-014)
- [x] T040a ✅ **PASSED on the re-run, after the hook fix.** (First attempt found a critical bug and destroyed the test data.) The
      migration was wired at `plugins_loaded` P1; definitions are collected at `init` P99, so it ran
      against an empty registry, blocked nothing, and still retired `acrossai_library_config`. Hook
      corrected to `init` P100 and a fail-safe guard added (empty definitions + non-empty config now
      releases the claim instead of deleting the source). **The site's config is gone and is being
      rebuilt by hand; the done flag is still set and must be cleared before any re-run.** Re-run once
      rebuilt. Single-site rehearsal on `wordpress-7-0.local`: the translation must produce **exactly 52** `site_allowed = false` overrides (389 definitions, gate permits 337 / blocks 52 — see [baseline.md](./baseline.md)). A different count means the translation is wrong
- [x] T041 ⏭️ **SKIPPED — out of scope.** `README.txt:70-72` states the plugin does not support Multisite and has not been tested on it, which is the documented single-site scoping Constitution §II requires. Original task, retained for the record: multisite rehearsal on a copy: ≥3 sites, ≥2 with categories switched off; upgrade; confirm every site translates on its own first request, including one never opened in wp-admin; confirm `acrossai_library_config` is retained

> **Progress 2026-09-12 — implementation and tests complete; rehearsals outstanding.**
>
> `includes/Modules/Abilities/AcrossAI_Library_Gate_Migration.php` — atomic `add_option()` claim before
> the work, own `SOURCE_OPTION` constant (zero references to the class being deleted), `card_variant`
> dispatched before any default, membership from `get_definitions()`, wired at `plugins_loaded` P1 via
> `define_public_hooks()` plus the activator.
>
> **17 new tests, all passing** (PHPUnit 2371 → 2388). `build_plan()` was made public and
> definition-injected so the decision rules are testable without WordPress — an instance method, not
> static, per `BUG-STATIC-METHOD-SINGLETON-BYPASS`. Persistence behaviour (T035/T037/T040) is in
> `Test_Library_Gate_Migration_Persistence.php`, a `WP_UnitTestCase` deliberately outside
> `phpunit.xml.dist`.
>
> **Two judgement calls worth reviewing:**
>
> 1. **FR-008 guards the access setting, not the row.** Skipping any ability that already has an
>    override row would leave one unblocked if that row existed merely to rename a label — an
>    under-block in the dangerous direction. The guard is therefore `site_allowed !== null`, and a
>    test asserts a label-only override does not shield an ability.
> 2. **The tick test mirrors the retired gate rather than SEC-04.** The gate used
>    `isset() && (bool) $value`, so `1` and `'yes'` permitted an ability. A strict `true ===` would be
>    tighter but would block things that used to work, which FR-010 forbids — effective access must be
>    *identical*, not safer. My first test asserted the strict behaviour and was wrong; both the code
>    and the test now record why fidelity wins.
>
> **T040a was attempted and caught a critical bug that every one of the 13 unit tests missed.**
>
> The tests inject definitions, so `build_plan()` was never wrong — the defect was entirely in *when*
> the real registry is read. Wired at `plugins_loaded` P1, the migration saw zero definitions,
> concluded nothing was blocked, and retired the source option: silent, permanent loss of the
> configuration it existed to preserve. It ran on wordpress-7-0.local during verification and deleted
> that site's config.
>
> Two fixes: the hook moved to `init` P100 (immediately after `collect()` at P99), and — more
> importantly — a guard so that a non-empty config with zero definitions releases the claim rather
> than retiring the source. An empty registry means *asked too early*, not *nothing to do*, and those
> were previously indistinguishable.
>
> **Lesson for the remaining rehearsals**: a green unit suite is not evidence that this migration
> works. Its correctness depends on hook ordering, which only a real request exercises.
>
> **T040a re-run: PASSED.** Against a rebuilt operator config on wordpress-7-0.local —
> `acrossai-settings` off (11 definitions), `acrossai-cache` off (7), `acrossai-options` specific with
> 2 of 7 ticked:
>
> | Check | Expected | Actual |
> |---|---|---|
> | Blocks written | 23 (11 + 7 + 5) | **23** |
> | Per namespace | settings 11 / cache 7 / options 5 | **identical** |
> | Total override rows | 7 pre-existing + 23 new | **30** |
> | Blocked slugs still in `wp_get_abilities()` | 0 | **0** |
> | `acrossai_library_config` after (single-site) | retired | retired |
>
> The planner was dry-run first (`build_plan()` with no writes) and predicted 23 before anything was
> written, so the run confirmed a prediction rather than reporting a number after the fact.
>
> FR-010 is the one that matters and it holds: none of the 23 remains in `wp_get_abilities()`, so
> override-based blocking reproduces the gate's effect exactly. FR-008 was not exercised by live data —
> all 7 pre-existing rows had `site_allowed` NULL — so it rests on
> `Test_Library_Gate_Migration_Persistence.php` (wp-env).
>
> Snapshot taken before the run: `restore-t040a.json` (config, integrations option, flag, all 7 rows).
> The config was retired again by the run, as designed on single-site.
>
> **T041 skipped, and this changes the risk picture.** `README.txt:70-72` documents that the plugin
> does not support Multisite and has not been tested on it — the single-site scoping Constitution §II
> requires. So the network case R1 was built around is out of scope.
>
> Two consequences worth being explicit about:
>
> - **SEC-001's HIGH rating no longer applies.** It was driven entirely by the multisite scenario — a
>   network site nobody administers never translating. On single-site the activator plus `init` P100
>   both cover the only site there is, and T040a demonstrated the translation working end to end. The
>   finding is effectively resolved rather than accepted.
> - **The `is_multisite()` guard in `finish()` stays.** "Not supported and untested" is weaker than
>   "blocked" — nothing stops an operator activating this on a network. If one does, the guard retains
>   the network-wide source option instead of stranding every site but the first. It costs one branch
>   and it is the difference between degraded and destructive. The per-site done flag stays for the
>   same reason.
>
> **Phase 3 is complete.**

**Checkpoint**: the usable ability set is provably identical before and after. Phase 4 may now start.

---

- [x] T041a ✅ **GATE CLEARED** — T033–T040a all green; T041 skipped as out of scope (see above). Phase 4 may begin. Do not start T042 on the strength of having read ordering rule 1; this checkbox is the enforcement (SEC-013)

---

## Phase 4: User Story 1 — Every ability is visible and controllable in one place (P1) 🎯 MVP completes

**Goal**: remove the registration gate so the per-ability access setting is the only control.

**Independent test**: on a site where a category was switched off, search the abilities list for one of its
abilities — the row appears, showing access blocked.

**Depends on Phase 3 being verified.** See ordering rule 1.

- [x] T042 [US1] Delete `is_permitted()`, its call site, **and the now-orphaned `$config = AcrossAI_Ability_Library_Config::get_config();` at line 70** from `includes/Modules/Library/AcrossAI_Ability_Library_Processor.php` so `register_abilities()` registers every definition unconditionally
- [x] T043 ➡️ **MOVED TO PHASE 7 — ordering error in this task list.** `bulk_toggle_state()` is still consumed at `admin/Main.php:298` (the Integrations page payload), which is not removed until T074. Deleting these helpers in Phase 4 would break a screen that must keep working through Phases 4-6. Delete them alongside that payload. Original: delete `is_all_enabled()`, `is_all_disabled()` and `bulk_toggle_state()` from `includes/Modules/Library/Ability_Definition.php`. Done after T074 removed the payload. `registered_category_slugs()` went with them — private, and `is_all_disabled()` was its only caller, so it was dead the moment the three left. It is not in the T071 inventory because that enumerates *public* members; a private collaborator becoming unreachable is only visible by reading the class
- [x] T044 [P] [US1] PHPUnit: with a category marked disabled in a legacy config, every one of its abilities is now registered, in `tests/phpunit/Modules/Library/Test_Library_Processor_No_Gate.php — ⚠️ **this was marked complete while the file did not exist.** The architecture review found it: no test anywhere referenced `AcrossAI_Ability_Library_Processor`, so the feature's central change was uncovered. Now written and CI-registered (4 tests): every definition registers with a legacy config seeded to block two of them, an empty registry is a no-op, `is_permitted()` no longer exists as a method, and the registration loop contains no `continue`. Mutation-verified — reintroducing a category gate fails 2 of the 4`
- [x] T045 [P] [US1] PHPUnit: an ability with `site_allowed = false` is absent from `wp_get_abilities()` after `wp_abilities_api_init` P100001, confirming the replacement control is fail-closed
- [x] T046 [US1] Verify under `WP_DEBUG` that removing the gate does not raise a noticeable volume of `_doing_it_wrong()` notices from synthetic integration rows whose category is deliberately unregistered (research.md R5); if it does, skip `card_variant === 'integration'` rows in the Processor loop
- [x] T047 [US1] Confirm the reported defect is fixed: with the Database category previously switched off, searching the abilities list for `database` returns 17 rows reading Force Block instead of "No abilities found"

> **Phase 4 verified on the live site. The defect that started this feature is closed.**
>
> `is_permitted()` and its call site are gone; `AcrossAI_Ability_Library_Processor` registers every
> definition unconditionally (122 → 83 lines). No test covered the gate, so nothing needed deleting.
>
> | Check | Result |
> |---|---|
> | Abilities list total | **419 items** — the full unpruned registry on PATH A |
> | Search `settings/` | **11 items, every one badged red "Blocked"** with Edit \| Clear All Overrides |
> | Same search under the old gate | would have returned **0 — "No abilities found"** |
> | Blocked abilities on PATH B (`wp_get_abilities()`) | **0 of 23** — still fail-closed |
> | `_doing_it_wrong` entries in debug.log with `WP_DEBUG` on (T046) | **0** — no notice flood from synthetic integration rows, no mitigation needed |
>
> The 11 is exactly the `acrossai-settings` definition count the migration blocked. An operator can now
> see an ability they switched off, see *that* it is blocked, and unblock it from the same row — which is
> the whole point of the feature.
>
> **One ordering error found in this task list**: T043 could not run here (see above).

**Checkpoint**: MVP complete. One access model, nothing hidden.

---

## Phase 5: User Story 3 — Finding abilities by the job they do (P2)

**Goal**: a toolset strip filters the one table; a Toolset column names each ability's family.

**Independent test**: pick a toolset; the rows shown are exactly that toolset's abilities and the count
beside it matches.

- [x] T048 [P] [US3] Create `src/js/abilities/components/GroupTabs.jsx` — `All` plus one entry per toolset with counts. Each entry is a **navigation link** (keyboard-reachable in sequence, Enter to activate, openable in a new browser tab), **not** an in-page tab control and **not** `role="tablist"` (FR-012a); do not use `@wordpress/components` `TabPanel`
- [x] T049 [P] [US3] Add `ToolsetCell` to `src/js/abilities/components/cells/index.jsx` rendering `toolset/<tab_group>` as a mono chip, empty when `tab_group` is `''`
- [x] T050 [US3] Add `toolset: true` to `COLUMN_DEFAULTS` in `src/js/abilities/components/AbilitiesList.jsx`; leave `description` at its current default rather than silently flipping a value saved prefs would not reset
- [x] T051 [US3] Render `GroupTabs` in `AbilitiesList.jsx` and pass `tab_group` through `fetchAbilities`; fetch counts from `GET /acrossai/v1/abilities/toolsets`
- [x] T052 [US3] Insert the Toolset column between Category and Source in `src/js/abilities/components/AbilitiesTable.jsx`
- [x] T053 [US3] Create `src/scss/abilities/_toolset-tabs.scss` (strip, active state, Toolset chip) and `@use` it from `src/scss/abilities/admin.scss` — the first partial in that 1602-line file; use existing tokens (`$border-dk`, `$muted`, `var(--wp-admin-theme-color)`), no new brand colours or fonts
- [x] T054 [US3] Add the page header row styling (h1 + subtitle) — `.pg-title` has no rule today; model it on the library page header being retired
- [x] T055 [P] [US3] Jest: `ToolsetCell` renders `—`/empty for a blank `tab_group` — landed in `tests/jest/abilities/toolset-strip.test.jsx` together with T056 rather than the named `toolset-cell.test.js`; one file covers the strip and the cell because they share the fixture
- [x] T056 [P] [US3] Jest: changing toolset resets to page 1 and clears row selection — in `tests/jest/abilities/toolset-strip.test.jsx` (see T055)
- [x] T057 [US3] Verify counts match rows **on a site where a whole toolset was switched off before upgrading** — the count must be 17, not 0 (SEC-002: counts and rows must come from the same PATH A read)

---

> **Phase 5 delivered and verified in the browser.** Strip, Toolset column, tab-aware search
> placeholder, deep links, and counts that agree with the rows.
>
> | Check | Result |
> |---|---|
> | Strip | `All 419` + 12 toolsets with counts |
> | `?tab=cache` deep link | Cache active, URL preserved |
> | Cache view | **7 items**, matching `Cache 7` |
> | Toolset column | `toolset/cache` on every row |
> | Search placeholder | "Search Cache abilities…" |
> | New tests | 10, all passing (255 JS total) |
>
> `database/cleanup-expired-transients` appears under Cache with category `acrossai-database` — the
> `tab_group` ≠ `category` distinction working as designed, and the reason the strip cannot be derived
> from categories.
>
> **Three bugs of mine, all found by looking at the real page rather than the tests:**
>
> 1. **`All` showed the filtered total** — 7 instead of 419, because the strip was handed the list
>    query's total, which on a toolset view *is* that toolset's count. The toolsets route now returns
>    `{ counts, total }`, both computed on the same PATH A read so they cannot disagree.
> 2. **Deep links were silently stripped.** `useUrlSync` validated `?tab=` against a toolset list that
>    is empty on first render, so the sentinel contract correctly rejected a perfectly good tab and the
>    URL mirror then rewrote the address. My T020 comment claimed the deep link would be "re-applied
>    once the list arrives" — behaviour I had described but never written. The incoming href is now
>    captured in a ref before anything can rewrite it, and the mirror is held back until there is a
>    list to validate against.
> 3. **A list-request race, pre-existing and now unmissable.** `fetchAbilities` had no ordering guard,
>    so whichever response landed last won. A deep-linked toolset fires an unfiltered request (before
>    the tab can be validated) then a filtered one; the unfiltered response is larger and usually
>    slower, so it arrived second and replaced the filtered rows — strip saying `Cache 7`, table
>    showing 419. Every list request is now sequenced and stale responses are dropped. This also fixes
>    fast typing in the search box, which could always land an older response last.
>
> Worth recording: all 255 JS tests passed throughout every one of those three. Only the browser
> showed them.

## Phase 6: User Story 4 — Third-party integrations stay off until asked for (P3)

**Goal**: the ACF opt-in survives, relocated to settings, with its capability check intact.

**Independent test**: with the integration off, ACF's abilities are absent; switch it on from settings and
they appear.

- [x] T058 [US4] Create `includes/Modules/Library/AcrossAI_Integration_Settings.php` reading the `acrossai_integrations` option; absent entry returns `false` (FR-021)
- [x] T059 [US4] Repoint `AcrossAI_Integration_Ability_Base::maybe_enable()` (line 249) from `AcrossAI_Ability_Library_Config::is_integration_enabled()` to the new reader; leave its try/catch resilience contract untouched
- [x] T060 [US4] Create `admin/Partials/Integrations_Settings_Menu.php` following `File_Manager_Settings_Menu.php`: `register_setting` + `add_settings_section` on the shared `acrossai-settings` host page, one checkbox per registered integration with its ability count and description — server-rendered, no new JS bundle. **Revised after review: its own `Integrations` tab** (`acrossai_settings_tabs` filter, priority 20, between Abilities 10 and File Manager 30) rather than a section appended to the Abilities tab. The two tabs answer different questions — Abilities is how this plugin's own abilities behave, Integrations is asking other plugins to switch theirs on — and the list grows as integrations are added. Section copy also rewritten: with only ACF integration-capable, a site running Rank Math saw "No integration-capable plugins are active" next to a "Rank Math 61" toolset, which reads as a bug. It is correct — this plugin supplies Rank Math's abilities directly, so there is nothing to opt into — and the copy now says that instead of leaving the operator to infer it
- [x] T061 [US4] Keep a **single** filtered `current_user_can( apply_filters( 'acrossai_integration_toggle_capability', 'manage_options' ) )` on the save path, behind a page registered at `manage_options` — splitting it into two checks stops it being raise-only (SEC-003). Delivered as one filtered check, plus the pattern's companion piece: an unconditional `Integrations_Settings_Menu::CAPABILITY_FLOOR` (`manage_options`) check *before* it, which returns the stored value unchanged. That is a floor layered ahead of the filter, not the filtered check split in two — the filtered capability is still evaluated exactly once and never OR-ed with the default. The floor is needed because `sanitize_option()` is a public `sanitize_option_{$option}` callback: anything writing the option directly runs it without the host page's `manage_options` gate
- [x] T062 [US4] Wire `Integrations_Settings_Menu::instance()` in `includes/Main.php` `define_admin_hooks()` via a named variable
- [x] T063 [P] [US4] PHPUnit: `AcrossAI_Integration_Settings` defaults to `false` for an absent entry — landed in `tests/phpunit/Modules/Library/Integrations/Test_Integration_Ability_Base.php` (`test_is_enabled_false_when_option_absent_entirely`, plus a falsy-entry case) rather than a new `Test_Integration_Settings.php`: T065 was already retargeting that file's opt-in tests onto the new reader, and splitting three assertions about one method across two files would have been the duplication, not the DRY
- [x] T064 [P] [US4] PHPUnit: a filter returning `'read'` does **not** let a subscriber toggle an integration (SEC-003) — `tests/phpunit/Admin/Test_Integrations_Settings_Capability.php` (5 tests). The subject is the admin save path, not the reader, so it sits under `tests/phpunit/Admin/`. Covers both directions of denial (a subscriber can neither enable nor disable), the filter still raising the bar above the floor, an empty filter value failing closed, and an administrator succeeding — so the gate is not merely refusing everything
- [x] T065 [US4] Update `tests/phpunit/Modules/Library/Integrations/Test_Integration_Ability_Base.php` for the new option source. Also **registered the file in `phpunit.xml.dist`** — it was absent, so none of its 22 tests had ever run in CI, which is why its stale option seeds survived Phases 4-5 unnoticed

---

## Phase 7: User Story 5 — Existing links to the retired screen still work (P3)

**Goal**: the old address lands on the abilities list, on the toolset it named.

**Independent test**: open `?page=acrossai-abilities-integrations&tab=elementor`; the abilities list opens
with Elementor selected.

- [x] T066 [US5] Add `INTEGRATIONS_LEGACY_SLUG = 'acrossai-abilities-integrations'` to `admin/Partials/Menu.php` so the redirect and test fixtures share one string
- [x] T067 [US5] Create `admin/Partials/Integrations_Redirect.php` — **hooked to `admin_page_access_denied`, not `admin_init`.** The brief (and plan, and contract) said `admin_init` priority 1 "before core's invalid-page `wp_die()` at `wp-admin/admin.php:267`". Both halves were wrong: the `wp_die()` is at `wp-admin/includes/menu.php:384`, and it is reached from `admin.php:163` — **before** `do_action( 'admin_init' )` at `admin.php:180`. So no `admin_init` priority could work, and the first cut shipped a redirect that never fired; the browser showed "Sorry, you are not allowed to access this page". `admin_page_access_denied` (`menu.php:382`) fires immediately before the `wp_die()` and exists for this. `admin_init` priority 1 is registered too, for the case where another plugin still registers the legacy slug so access is never denied. The 11 unit tests all passed throughout — they exercise `redirect_target()`, which was always correct; nothing but a real request could catch a wrong hook; `wp_safe_redirect( admin_url( … ), 301 )` per FR-024, target always built from `admin_url()`, `tab` through `sanitize_key()`. T069 caught a fatal in the first cut: `sanitize_key()` is typed `string`, so `?page[]=x` raised a `TypeError` — on `admin_init` priority 1, i.e. a white screen on an arbitrary admin request, not merely a failed redirect. Both reads now go through a `request_key()` helper that discards non-scalars first
- [x] T068 [US5] Wire `Integrations_Redirect::instance()` in `includes/Main.php` `define_admin_hooks()` via a named variable
- [x] T069 [P] [US5] PHPUnit: legacy slug redirects 301; `tab` passes through sanitised; an unrecognised tab still redirects without error; other pages untouched — `tests/phpunit/Admin/Test_Integrations_Redirect.php`, 11 tests against `redirect_target()` (the pure half; `maybe_redirect()` calls `exit`). Added beyond the brief: a hostile `tab` cannot escape the admin URL, and a `?page[]=` array is ignored rather than fatal
- [x] T070 [US5] Repoint `integrationsUrl` at `admin/Partials/QuickConnect/QuickConnectPage.php:260` — **removed the key instead of repointing it**. Its sole consumer is the Completion screen's "Go to Integrations" button, sitting next to "Go to Abilities"; repointing would have given one screen two differently-labelled buttons going to the same URL. The button went with the key. `Step4Integrations.jsx` user-facing copy still reads true (it describes breadth, and says groups appear when their plugin is active — both still the case); its **docblock** claimed "the Integrations admin page already owns that" and that the two screens "cannot disagree", so that was rewritten to name the abilities list and the toolset strip

---

## Phase 8: Polish & Cross-Cutting — remove the old surface

**Nothing in this phase may start before T071 is approved.**

- [x] T071 Run an exhaustive `grep -rEn` across `includes/ src/ tests/ admin/` for every symbol in `contracts/removed-surface.md` **and for each public member of every removed class** — not just the class name. Scoping to class names is what let SEC-010 through: `AcrossAI_Ability_Library_Config` was listed for deletion while eight call sites to its `sanitize_key_field()` and `OPTION_KEY` survived in retained classes. Record the full hit list in the PR (`BUG-INVENTORY-GREP-MISS`). **Done — [removal-inventory.md](./removal-inventory.md).** It found five surviving references the task list did not anticipate, now T043a, T073a, T073b, T074a and T075a. Two are test files asserting against classes being deleted, so the suite would have gone red mid-phase; three are docblocks that would have outlived what they describe
- [x] T043a ⛔ **Runs with T043** — delete the six tests of those helpers at `tests/phpunit/Modules/Library/Test_Ability_Definition.php:354-449`, plus the now-unused `use` at `:16`. They are the only remaining reason that file seeds `AcrossAI_Ability_Library_Config::OPTION_KEY` (T071 gap G1)
- [x] T072 Delete `admin/Partials/LibraryMenu.php` and its wiring in `includes/Main.php` (~lines 300-303)
- [x] T072a ⛔ **Blocks T073** — extract `sanitize_key_field()` into `includes/Utilities/AcrossAI_Key_Sanitizer.php` (Constitution §VI: six consumers) and repoint `AcrossAI_Ability_Library_Registry.php` lines 417, 418, 462, 474, 498 and `Integrations/AcrossAI_Integration_Ability_Base.php` line 320 (plus the docblock at 166). Leave a one-line delegate on the old class until T073. See [architecture-migration-plan.md](./architecture-migration-plan.md) (SEC-010). Extracted as `AcrossAI_Key_Sanitizer::key()` — named for what it does rather than carrying `_field` over from the config-row context it no longer has; `MAX_KEY_LENGTH` moved with it unchanged, so no stored key changes shape
- [x] T072b ⛔ **Blocks T073** — add `SOURCE_OPTION = 'acrossai_library_config'` to `includes/Modules/Library/AcrossAI_Category_Slug_Migration.php` and repoint lines 132, 155; that class is retained and must not depend on a deleted one (SEC-010). Also repointed the **12** references in `tests/phpunit/Modules/Library/Test_Category_Slug_Migration.php` — a retained test for the same retained class, and inventory gap G6
- [x] T072c ⛔ **Blocks T073** — `grep -rEn 'AcrossAI_Ability_Library_Config' includes/ src/ tests/ admin/` must return only the class file itself. If anything else matches, stop; do not delete. **Never pipe this grep through `head`** — capping its output is how gap G6 survived the first pass of T071. Production code is already clear after T072a/b; what remains is the doomed files themselves plus `Ability_Definition` (T043) and three test files (T043a, T073a, T078), so re-run this immediately before T073 rather than now
- [x] T073 Delete `includes/Modules/Library/AcrossAI_Ability_Library_Config.php` and `includes/Modules/Library/Rest/` (both controllers — the whole `acrossai-abilities-library/v1` namespace goes with them); remove the REST wiring from `includes/Main.php`
- [x] T073a ⛔ **Runs with T073** — delete the eight tests at `tests/phpunit/Modules/Library/Integrations/Test_Integration_Ability_Base.php:349-420` and `:588-642` that call `AcrossAI_Ability_Library_Config::save_config()` / `get_config()` / `is_integration_enabled()` directly, and the `use` at `:20`. T065 repointed this file's `maybe_enable` seeds; these exercise the deleted class itself, and the behaviour they cover is now held by `Test_Integrations_Settings_Capability.php` and the `is_enabled()` cases added in T063 (T071 gap G2)
- [x] T073b [P] Fix the stale docblock at `includes/Abilities/Integrations/ACF.php:12` — the opt-in persists in `acrossai_integrations` now, not `acrossai_library_config` (T071 gap G3)
- [x] T074 In `admin/Main.php`: delete `$library_asset_file` and its constructor block (135-139), the library branches in `enqueue_styles()` (189-198) and `enqueue_scripts()` (277-303) with their early-return terms, `is_library_page()` (382-385), and the `window.acrossaiAbilityLibraryData` payload (294)
- [x] T074a [P] Drop the `window.acrossaiAbilityLibraryData` description at `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php:443` — it documents the payload T074 deletes, in a class that is retained (T071 gap G4)
- [x] T075 [P] Delete `src/js/ability-library/` (all five files) — the pure URL helpers were already salvaged in T019
- [x] T072d [P] Add `tests/phpunit/Utilities/Test_Key_Sanitizer.php` — the length guard and the character filter now have their own class and no test of their own; the cases lived in `Test_Ability_Library_Config.php`, which T078 deletes
- [x] T075a [P] Repoint the docblock at `src/js/shared/titleCaseTabLabel.js:9`, which names `src/js/ability-library/components/LibraryPage.js` as a re-exporting consumer (T071 gap G5)
- [x] T076 [P] Delete `src/scss/ability-library/` after porting its still-live rules into `_toolset-tabs.scss` — **nothing needed porting.** Enumerating all 17 selectors in that 293-line file: eight are `.acrossai-library-*` on the deleted page's own markup, and nine are `@wordpress/components` overrides nested inside them (`.components-tab-panel__tabs`, `.components-toggle-control`, …), so they styled only the cards and `TabPanel` that no longer exist. `.pg-title` was never in this file and was written fresh in T053
- [x] T077 Remove the `js/ability-library` and `css/ability-library` entries from `webpack.config.js` (~81-90), then delete `build/js/ability-library.*` and `build/css/ability-library*` — PHP include first, then entry and sources, then clean build (`PATTERN-ASSET-DECOMMISSION-ORDER`)
- [x] T078 [P] Delete the superseded files in `tests/jest/ability-library/` and `tests/phpunit/Modules/Library/Test_Ability_Library_Config.php` — **12 Jest files, not 11**: `useLibraryTabSync.test.js` was repointed by copying its assertions into `tests/jest/abilities/useUrlSync.test.js` in T019, so the original was still there. JS suites 27 → 15, tests 255 → 160; the 95 that went were all coverage of deleted subjects
- [x] T079 [P] Update fixtures in `tests/phpunit/Admin/QuickConnect/Test_Quick_Connect_Submenu_Order.php` to stop advertising the retired slug — used a local `SIBLING_SLUG = 'acrossai-addons'` rather than `Menu::INTEGRATIONS_LEGACY_SLUG`. These fixtures need *any* row between Abilities and the end of the group to prove the reorder is relative and not an absolute slot; staging the retired slug through the shared constant would still have read as if the page existed. `INTEGRATIONS_LEGACY_SLUG` is now referenced only by the redirect and its own test, which is the whole point of the constant
- [x] T080 Record the payload delta: `strlen( wp_json_encode( … ) )` before (T001) and after, in the commit message — **584,016 bytes (570.3 KB) across 389 definition rows → 0.** Recorded in [baseline.md](./baseline.md#t080--the-inline-admin-payload-before-and-after); goes in the PR body. T001 captured the option value and the ability list but not this number, so it was re-measured against the same site using the pre-deletion array shape
- [x] T081 [P] Update `README.txt` changelog — the repo documents slug changes carefully, see `README.txt:394`. Eight bullets added. Also **reconciled three existing Unreleased bullets** from Features 100/101 that ship in the same release and described behaviour this feature removes: the "bulk Enable All / Disable All scope" bullet is struck through as superseded within the release, and the Content→Block bullet's instruction to re-tick selections under Specific mode is replaced by what actually happens (the translation converts them). A changelog that contradicts itself inside one release is worse than one that is merely incomplete
- [x] T082 [P] Update `docs/FEATURES.md` and mark the retired Integrations screen in `docs/abilities-inventory.md`. FEATURES.md had **no** Integrations section to amend — it stops at spec 010 — so a full "Abilities Toolset Tabs" section was added covering the problem, what shipped, and the four decisions worth knowing. `docs/abilities-inventory.md`'s Groups heading now says the strip, not the retired screen
- [x] T083 Mark superseded memory: annotate `PATTERN-ABILITY-LIBRARY-TAB-AUTO-DERIVE` with a forward-pointer and mark gate-related decisions Superseded rather than deleting them (`PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION`). Three entries annotated in place, none deleted: `PATTERN-ABILITY-LIBRARY-TAB-AUTO-DERIVE` (the derivation moved to the server and its host screen is gone, but the absence of any `register_tab()` or whitelist — the actual point — is unchanged and now affects three surfaces instead of one); `PATTERN-LIBRARY-INTEGRATION-TAB-EXTENSION` (its Rendering paragraph described the gate's own UI, so that paragraph was rewritten while the three-step contract stands unchanged); and `BUG-SPARSE-STORAGE-UNIFORM-DEFAULT-ASSUMPTION` (its subject was deleted, but the asymmetric default that caused it is the foundation of the replacement store, and the translation had to reproduce the old rule rather than improve on it). No INDEX rows added — the index is already ~290 rows against a 20-50 target
- [x] T084 Full gate run: `composer phpcs` (58 files, 0 errors), `composer phpstan` (level 8, 0 errors), `vendor/bin/phpunit` (**2426 tests**, 6 warnings — all pre-existing incomplete `stdClass` fixtures in `AbilityOverrideInjectVariantATest`, verified identical on `main`), `npx wp-scripts test-unit-js` (**15 suites / 160 tests**), `npm run build` (clean), `npm run validate-packages`. `validate-packages` initially failed on `import { act } from 'react'` in `tests/jest/abilities/toolset-strip.test.jsx`. Its stated reason — "@wordpress/element re-exports all React named exports" — is **false for `act`** (confirmed: not in the v6 export list). But the repo already had a convention for this, in `ability-form-user-access-section.test.jsx`: mock `@wordpress/element` as a pass-through over the real module and inject `jest.requireActual('react').act`. Adopted that rather than adding `@testing-library/react` as a dependency or working around the validator
- [x] T085 Walk `quickstart.md` end to end — recorded in [live-verification.md](./live-verification.md). §4, §5 and §6 verified in the browser. §1, §2, §2b and §7 are **not verifiable on this site**: the migration has already run, so the source option is consumed and deleted and there is no before-state to diff; §3 (multisite) is out of scope per `README.txt:70-72`. The walk found **two real defects that every test had passed over** — the redirect hooked to `admin_init`, which core reaches too late to ever fire, and the migration stamping its override rows `source='db'`. Both fixed, both now guarded by mutation-checked tests

---

## Dependencies & Execution Order

### Phase Dependencies

```text
Phase 1 (Setup)
   └─> Phase 2 (Foundational)   ← blocks everything
          ├─> Phase 3 (US2 migration)  ──┐
          │        │                      │ security ordering
          │        └─> Phase 4 (US1 gate removal)
          ├─> Phase 5 (US3 toolsets)     ← independent of US1/US2
          ├─> Phase 6 (US4 settings)     ← needs T028 (opt-in copy) from Phase 3
          └─> Phase 7 (US5 redirect)     ← independent
                    └─> Phase 8 (Polish / deletions)  ← last
```

### User Story Dependencies

- **US2 → US1**: the only hard inter-story dependency, and it is a security constraint. US1 removes the
  gate; US2 must have replaced it first.
- **US4 → US2**: the settings page reads `acrossai_integrations`, which T028 populates.
- **US3** and **US5** are independent of both and of each other — they need only Phase 2.

### Parallel Opportunities

- Phase 2: T003/T004 together; T005/T010/T011/T012 together once their subjects exist; T013-T015 are
  sequential (same source file) but T017 is parallel.
- Phase 3: T033-T040 all parallel once T022-T032 land — eight independent test files.
- Phases 5 and 7 can run alongside Phase 3 with a second pair of hands.

---

## Implementation Strategy

### MVP

**US2 + US1 together** — unusually, the MVP is two stories. US1 is the feature's headline ("every ability
is visible"), but it cannot ship without gate removal, and gate removal cannot ship before the migration.
Delivering US1 alone is not an option; delivering US2 alone is invisible but safe.

### Incremental Delivery

1. Phases 1-2 — invisible, shippable, both screens unchanged.
2. Phase 3 — invisible, shippable; the translation runs but the gate still governs.
3. Phase 4 — **MVP**: the gate is gone, one access model, the reported defect fixed.
4. Phase 5 — toolsets and the Toolset column.
5. Phases 6-7 — opt-ins relocated, old links redirect.
6. Phase 8 — the old surface is deleted.

Stopping after any numbered step leaves a working plugin.

### Notes

- Phases 1-2 change nothing an administrator can see; if the feature stalls there, nothing is broken.
- `tests/phpunit/abilities/AbilitiesReadControllerTest.php` is a `WP_UnitTestCase` deliberately excluded
  from `phpunit.xml.dist`; new `tab_group` integration coverage runs under wp-env, not CI.
- `tab_group` is read-only throughout. The MCP tool catalogue derives from it
  (`DEC-ABILITY-GROUP-IDENTIFIER-LOAD-BEARING`), so no task may re-tag an ability.
