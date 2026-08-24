---
description: "Task list for Feature 061 — Debugging Abilities: Conflict Testing"
---

# Tasks: Debugging Abilities — Conflict Testing

**Input**: Design documents from `/specs/061-debugging-abilities-conflict-testing/`
**Prerequisites**: plan.md ✓, spec.md ✓, research.md ✓, data-model.md ✓, contracts/ ✓

**Tests**: PHPUnit coverage lands for the two shared helpers only (per Constitution §VII deviation documented in `plan.md`'s Complexity Tracking — the seven ability classes remain uncovered as thin dispatchers). This satisfies §VII's "unit tests written and passing for all new logic" while matching the sibling `FileManager/` domain convention of no PHPUnit for thin ability wrappers.

**Organization**: Tasks are grouped by user story. `US1` and `US2` are both P1 (MVP together — Story 1 is the product, Story 2 makes it work). `US3` and `US4` are P2. `US5` was dropped during `/speckit-clarify` (Q1 sole-writer decision).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no incomplete dependencies)
- **[Story]**: `[US1]` / `[US2]` / `[US3]` / `[US4]` — maps to spec.md user stories
- Every task includes an exact file path

## Path Conventions

Single-project WordPress plugin layout:

- Source: `includes/Abilities/Debugging/` (mirrors sibling `includes/Abilities/FileManager/`)
- Tests: `tests/phpunit/abilities/debugging/`
- Bootstrap wiring: `includes/Modules/Library/AcrossAI_Core_Abilities_Bootstrap.php`
- Bundled mu-plugin asset: `includes/Abilities/Debugging/assets/wp-conflict-tester.php`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Create the directory scaffolding. No behavioural code yet.

- [X] T001 Create the source directory `includes/Abilities/Debugging/` and its `assets/` sub-directory
- [X] T002 [P] Create the PHPUnit directory `tests/phpunit/abilities/debugging/`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The mu-plugin source asset, the two shared helpers, the category registrar, and the category-registration wiring. Every user story depends on some subset of this — the helpers and category MUST exist before any ability can be written.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T003 [P] Copy the mu-plugin source **byte-identically** from `/Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/local-wordpress-supercharged/src/features/conflict-test/wp-conflict-tester.php` (33 lines) to `includes/Abilities/Debugging/assets/wp-conflict-tester.php`. **Do NOT modify the file** — no added guards, no re-formatting, no whitespace normalisation. `Overrides_Store::mu_plugin_status()` (T005) hash-compares against these exact bytes, so any local change would break `mu_plugin_status = 'deployed'` on a freshly-deployed site.
- [X] T004 [P] Create the category registrar at `includes/Abilities/Debugging/Category_Registrar.php` — `final class` singleton (`protected static $_instance = null;` + `public static function instance(): self` + `private function __construct()`), namespace `AcrossAI_Abilities_Manager\Includes\Abilities\Debugging`, `defined( 'ABSPATH' ) || exit;` guard. Method `register()` calls `wp_register_ability_category( 'acrossai-abilities-manager-debugging', 'Acrossai Abilities Manager — Debugging' )`. See sibling `includes/Abilities/FileManager/Category_Registrar.php` for the exact pattern.
- [X] T005 Create the overrides store helper at `includes/Abilities/Debugging/Overrides_Store.php` — singleton. Path constants: `PATH = WP_CONTENT_DIR . '/conflict-test-overrides.json'`; `MU_PLUGIN_PATH = WPMU_PLUGIN_DIR . '/wp-conflict-tester.php'`; `BUNDLED_MU_SOURCE_PATH = <plugin_path>/includes/Abilities/Debugging/assets/wp-conflict-tester.php`. Methods: `read(): array` (returns `[ 'overrides' => array<file,bool>, 'parse_error' => ?string ]`, auto-prunes orphans via `get_plugins()`, rewrites-or-deletes on shrink per FR-021 + FR-012); `write_one( string $file, bool $active ): array` (loads via `read()`, drops-if-matches-DB per FR-011, writes via atomic `tempnam()` + `rename()` per R2, deletes file if resulting map empty per FR-012); `write_many( array<string,bool> $entries ): array` (batched variant for bulk callers — one atomic write regardless of `count( $entries )`); `clear(): array` (unlink the file if it exists); `mu_plugin_status(): string` (returns `'deployed'|'missing'|'stale'` via `hash_file( 'sha256', … )`).
- [X] T006 [P] Create the dependency resolver helper at `includes/Abilities/Debugging/Dependency_Resolver.php` — singleton. Methods: `dependents_of( string $plugin_file ): array<string>` (transitive closure of every plugin whose parsed `Requires Plugins:` header names `$plugin_file`, BFS with visited set); `requirements_of( string $plugin_file ): array<string>` (transitive closure of everything `$plugin_file` declares as required). Uses `get_plugins()` internally and parses the `RequiresPlugins` field via `array_map( 'trim', explode( ',', … ) )`.
- [X] T007 [P] Create PHPUnit test at `tests/phpunit/abilities/debugging/OverridesStoreTest.php` — covers (a) `read()` on absent file returns empty map + `parse_error: null`; (b) `read()` on malformed JSON returns empty map + parse_error message per FR-019; (c) `write_one` with requested state matching DB state drops the entry per FR-011; (d) `write_one` that empties the map deletes the file per FR-012; (e) atomic write leaves no `.tmp*` sibling on success (temp path is renamed, not copied); (f) `read()` prunes orphaned entries per FR-021 and rewrites the file with the smaller map; (g) `mu_plugin_status()` returns `missing` / `deployed` / `stale` for the three cases (deleted / hash-match / hash-mismatch).
- [X] T008 [P] Create PHPUnit test at `tests/phpunit/abilities/debugging/DependencyResolverTest.php` — covers (a) `dependents_of` returns direct dependents from a two-plugin fixture (B requires A → dependents_of(A) = [B]); (b) transitive closure through a chain (C requires B, B requires A → dependents_of(A) = [B, C]); (c) `requirements_of` symmetric case; (d) cycle guard (B requires A, A requires B → BFS terminates and does not infinite-loop); (e) unknown target returns empty array; (f) diamond dependency (D requires B and C; B requires A; C requires A → dependents_of(A) = [B, C, D] with no duplicates).
- [X] T009 Wire the category registrar into `includes/Modules/Library/AcrossAI_Core_Abilities_Bootstrap.php::register_category_callbacks()`. Add one line matching the existing pattern for sibling categories: named variable first (`$debugging_category_registrar = \AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\Category_Registrar::instance();`), then `$loader->add_action( 'wp_abilities_api_categories_init', $debugging_category_registrar, 'register' );`. Per Constitution Boot Flow Rule — no inline `::instance()` in `add_action`.

**Checkpoint**: Foundation ready — every ability class can now consume `Overrides_Store::instance()`, `Dependency_Resolver::instance()`, and the registered category.

---

## Phase 3: User Story 1 — Reproduce a plugin conflict (Priority: P1) 🎯 MVP

**Goal**: A site administrator can list installed plugins, mark one as effectively inactive (with optional dependency cascade), verify the site renders as if that plugin were deactivated, then clear the override and verify the site returns to its exact prior state — all without modifying `wp_options.active_plugins`.

**Independent Test**: On a site with Hello Dolly active, issue `list-plugins` → `set-override(hello-dolly/hello.php, active=false)` → reload admin → confirm Hello Dolly's quote is gone AND `SELECT option_value FROM wp_options WHERE option_name='active_plugins'` byte-matches the pre-call value → issue `clear-overrides` → confirm Hello Dolly runs again. See `quickstart.md` steps 3–6 for the full sequence.

**Implementation for User Story 1**

- [X] T010 [P] [US1] Create the list-plugins ability at `includes/Abilities/Debugging/List_Plugins.php` — extends `AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition`. `ability()` returns the array conforming to `contracts/conflict-test-list-plugins.md` (slug `acrossai/conflict-test-list-plugins`, category `acrossai-abilities-manager-debugging`, `meta.acrossai.tab_group = 'debugging'`, `sub_group = 'conflict-testing'`, `sub_group_label = 'Conflict Testing'`, `meta.show_in_rest = true`, `meta.mcp = [ 'public' => false, 'type' => 'tool' ]`, annotations `[ 'readonly' => true, 'idempotent' => true, 'destructive' => false ]`, `permission_callback` static closure returning `current_user_can( 'manage_options' )`). `execute()` loads `wp-admin/includes/plugin.php` if needed, calls `get_plugins()`, cross-references `get_option( 'active_plugins' )`, parses `RequiresPlugins`, and returns `[ 'plugins' => [ …array of Plugin envelopes from data-model.md… ] ]`.
- [X] T011 [P] [US1] Create the get-overrides ability at `includes/Abilities/Debugging/Get_Overrides.php` — extends `Ability_Definition`. Contract per `contracts/conflict-test-get-overrides.md`. `execute()` returns `[ 'overrides' => $store->read()['overrides'], 'mu_plugin_status' => $store->mu_plugin_status(), 'parse_error' => $store->read()['parse_error'] ]`. Read triggers FR-021 auto-prune.
- [X] T012 [US1] Create the set-override ability at `includes/Abilities/Debugging/Set_Override.php` — extends `Ability_Definition`. Contract per `contracts/conflict-test-set-override.md`. Depends on T005 (Overrides_Store) and T006 (Dependency_Resolver). Input schema: `{ plugin_file: string, active: bool, cascade?: bool = true }`. `execute()`: (1) validate `plugin_file` resolves in `get_plugins()` — if not, return `WP_Error( 'plugin-not-installed', … )` per FR-018 single-plugin path; (2) if `active === true`, call the class-level helper `Set_Override::sandbox_scrape( $plugin_file )` which sets `WP_SANDBOX_SCRAPING`, calls `wp_register_plugin_realpath()`, then `include_once WP_PLUGIN_DIR . '/' . $plugin_file` — per FR-022 / research R1 the include is uncaught so a fatal terminates the request before the write; (3) call `Overrides_Store::write_one()` and capture the result; (4) if `cascade === true`, walk `Dependency_Resolver::dependents_of()` (for `active: false`) or `Dependency_Resolver::requirements_of()` (for `active: true`), running the same sandbox-scrape + write path for each; (5) return the envelope from `contracts/conflict-test-set-override.md`.
- [X] T013 [P] [US1] Create the clear-overrides ability at `includes/Abilities/Debugging/Clear_Overrides.php` — extends `Ability_Definition`. Contract per `contracts/conflict-test-clear-overrides.md`. `execute()` records whether the file existed pre-call, calls `Overrides_Store::clear()`, returns `[ 'cleared' => true, 'file_existed_before' => (bool) $existed ]`.
- [X] T014 [US1] Register the four US1 abilities in `includes/Modules/Library/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()`. Append four instantiations matching the existing sibling pattern: `new \AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\List_Plugins()` and equivalents for Get_Overrides, Set_Override, Clear_Overrides. Each `Ability_Definition` subclass self-registers on `plugins_loaded @ P20` per its base-class contract — no manual `wp_register_ability` call needed here.

**Checkpoint**: User Story 1 is fully functional — assuming User Story 2 has also landed to deploy the mu-plugin. The abilities themselves can be listed via `/wp-json/wp-abilities/v1/abilities/` after this phase completes.

---

## Phase 4: User Story 2 — Turn conflict testing on and off site-wide (Priority: P1)

**Goal**: A site administrator can install the underlying override mechanism (mu-plugin) with a single command, or remove it with a single command (optionally wiping the overrides file in the same call). Deploy is idempotent — re-running against a current mechanism is a zero-write no-op.

**Independent Test**: On a fresh site with no mu-plugin, issue `deploy-mu-plugin` → verify the file appears in `wp-content/mu-plugins/wp-conflict-tester.php` and byte-matches the bundled reference. Re-run — verify `mtime` is unchanged (SC-006). Issue `remove-mu-plugin` → verify the file is gone. See `quickstart.md` step 2.

**Implementation for User Story 2**

- [X] T015 [P] [US2] Create the deploy-mu-plugin ability at `includes/Abilities/Debugging/Deploy_Mu_Plugin.php` — extends `Ability_Definition`. Contract per `contracts/conflict-test-deploy-mu-plugin.md`. `execute()`: (1) `\AcrossAI_Abilities_Manager\Includes\Utilities\File_Mods_Guard::blocked_response()` gate per FR-013 — return the guard's `WP_Error` immediately if file mods are disabled; (2) compare SHA-256 of on-disk target (if present) to bundled source — return `{ deployed: false, already_current: true, path }` if match; (3) ensure `WPMU_PLUGIN_DIR` exists via `wp_mkdir_p()`; (4) atomic write via `tempnam( WPMU_PLUGIN_DIR, 'wctester-' )` + `file_put_contents` + `rename()` into the target; (5) return `{ deployed: true, already_current: false, path }`.
- [X] T016 [P] [US2] Create the remove-mu-plugin ability at `includes/Abilities/Debugging/Remove_Mu_Plugin.php` — extends `Ability_Definition`. Contract per `contracts/conflict-test-remove-mu-plugin.md`. Input schema: `{ also_clear_overrides?: bool = false }`. `execute()`: (1) `File_Mods_Guard::blocked_response()` gate per FR-013; (2) `unlink()` the mu-plugin file if it exists; (3) if `also_clear_overrides === true`, delegate to `Overrides_Store::clear()`; (4) return `{ removed: true, file_existed_before, overrides_cleared }`.
- [X] T017 [US2] Register the two US2 abilities in `includes/Modules/Library/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()`. Append two more instantiations matching T014's pattern.

**Checkpoint**: The MVP (User Stories 1 AND 2) is complete. Every step in `quickstart.md` §§1–6 is now verifiable end-to-end.

---

## Phase 5: User Story 3 — Test multiple plugins together in one operation (Priority: P2)

**Goal**: A site administrator can name a set of plugins in one call and have them all switched to the same effective state. Best-effort semantics with a per-plugin `applied` / `no_op` / `skipped` report.

**Independent Test**: With five installed plugins (two DB-active, three DB-inactive), issue one bulk call listing all five with `active: false`. Verify the response's `applied` array contains the two active ones, `no_op` array contains the three inactive ones (matches-db-state), and the overrides file contains exactly two entries. See `quickstart.md` step 7.

**Implementation for User Story 3**

- [X] T018 [US3] Create the bulk-set-overrides ability at `includes/Abilities/Debugging/Bulk_Set_Overrides.php` — extends `Ability_Definition`. Contract per `contracts/conflict-test-bulk-set-overrides.md`. Input schema: `{ plugin_files: array<string>, active: bool }`. `execute()`: (1) initialise three empty result arrays `$applied`, `$no_op`, `$skipped`; (2) iterate `$plugin_files`, classifying each per the contract — unknown → `skipped: plugin-not-installed` (FR-018 bulk path); `active: true` triggering caught `\Throwable` on sandbox-scrape → `skipped: plugin-fatal-on-load` (FR-022 bulk path); matches-DB → `no_op: matches-db-state`; otherwise buffered into a pending-write map; (3) after iteration, one call to `Overrides_Store::write_many( $pending )` — single atomic rename regardless of input size (R2); (4) return the three-array response envelope. Re-uses `Set_Override::sandbox_scrape()` as a shared static helper — no cascade in bulk per FR-010.
- [X] T019 [US3] Register the bulk-set-overrides ability in `includes/Modules/Library/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()` — one more line matching T014/T017's pattern.

**Checkpoint**: Bulk operation available and independently testable. Six of seven abilities registered.

---

## Phase 6: User Story 4 — Automatically handle plugin dependency chains during a conflict test (Priority: P2)

**Goal**: When a set-override call runs with the default `cascade: true`, the system walks the transitive closure of dependents (on deactivate) or requirements (on activate) via WordPress 6.5+'s `Requires Plugins:` header and writes an override entry for every plugin in the chain — so a single call produces a coherent conflict-test state.

**Independent Test**: Install two plugins where plugin B declares `Requires Plugins: plugin-a`. Both active. Issue `set-override(plugin-a/main.php, active=false, cascade=true)` → verify the returned `cascade_applied` array contains `plugin-b/main.php` and re-read overrides shows both A and B as `false`. Repeat with `cascade: false` → verify only A is overridden.

**Implementation for User Story 4**

The core cascade implementation lives in `Dependency_Resolver` (T006) and is invoked from `Set_Override` (T012). No new source file is needed — this phase is a **validation phase** that confirms cascade acceptance scenarios pass end-to-end.

- [X] T020 [US4] Extend `tests/phpunit/abilities/debugging/DependencyResolverTest.php` (from T008) with the four spec Story 4 acceptance scenarios: (a) two-plugin B-requires-A chain, `active: false` cascade produces `[A, B]`; (b) same chain, `active: true` cascade produces `[A, B]`; (c) three-plugin chain C→B→A with `active: false` on A produces `[A, B, C]`; (d) same chain with `cascade: false` produces `[A]`. Test uses a fixture that stubs `get_plugins()` return value (existing pattern in `Test_Integration_Ability_Base.php`).
- [ ] T021 [US4] Manual verification: run `quickstart.md` step 8 against a two-plugin fixture on a real WP install. Confirm the overrides file contains both A and B when `cascade: true` and only A when `cascade: false`.

**Checkpoint**: Cascade is proven to work in isolation (unit tests via T020) and end-to-end (manual verification via T021). All 20 functional requirements (FR-001…FR-022) are now realised.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Constitution §II / §IV / §VII gates + release plumbing.

- [X] T022 [P] Run `./vendor/bin/phpcs --standard=phpcs.xml.dist includes/Abilities/Debugging/ tests/phpunit/abilities/debugging/` — expect zero errors, zero warnings (Constitution §II).
- [X] T023 [P] Run `./vendor/bin/phpstan analyse --level=8 includes/Abilities/Debugging/` — expect zero errors (Constitution §II).
- [X] T024 [P] Run `./vendor/bin/phpunit --testsuite=abilities-unit --filter Debugging` — expect the two helper test classes (T007, T008, T020) green (Constitution §VII).
- [X] T025 [P] Add a `= 0.0.21 =` entry to `README.txt` Changelog and Upgrade Notice describing the new Debugging category and seven Conflict Testing abilities. Reference `FR-016` (category growth affordance), `FR-022` (fatal-safety mirroring `plugin_sandbox_scrape`), and the two Constitution deviations from `plan.md` Complexity Tracking.
- [X] T026 [P] Bump plugin version 0.0.20 → 0.0.21 in three places: `acrossai-abilities-manager.php` header line, `ACROSSAI_ABILITIES_MANAGER_VERSION` constant in `includes/Main.php`, and `Stable tag:` in `README.txt`.
- [X] T027 Run `composer dump-autoload` in the plugin root — verifies the new `Debugging\` sub-namespace is discoverable via PSR-4. (No `composer.json` change is required; this is a hygiene step.)
- [ ] T028 Run `quickstart.md` steps 1–11 end-to-end against a real WordPress install — every step must produce the documented output. Special attention: step 9 (fatal-plugin refuse) and step 10 (orphan auto-prune) are the two non-obvious paths. **Status**: deferred — requires an authenticated admin session against a live REST endpoint. Verified programmatically via unit-test coverage of the underlying helpers (T007, T008, T020) and syntax/static-analysis coverage of the ability wrappers (T022, T023). Owner runs this pass before shipping v0.0.21.
- [ ] T029 [P] Manually verify SC-005 — set `define( 'DISALLOW_FILE_MODS', true );` in `wp-config.php`, then run `deploy-mu-plugin` and `remove-mu-plugin` and confirm both return the `file-mods-disabled` error envelope with `status: 409` and no on-disk changes. **Status**: deferred — requires a live REST session with the constant flipped in `wp-config.php`. Guard code is code-inspection verifiable: `Deploy_Mu_Plugin::execute()` and `Remove_Mu_Plugin::execute()` both call `File_Mods_Guard::blocked_response( 'install' )` as their first non-input operation and return immediately when it returns non-null (matching sibling `FileManager\Edit_File` verbatim).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — can start immediately.
- **Phase 2 (Foundational)**: Depends on Phase 1. Blocks every user story phase.
  - T003 (mu-plugin asset paste) depends on user providing the source.
  - T005 (Overrides_Store) depends on T003 being complete for the `mu_plugin_status()` hash-compare method.
  - T007 depends on T005; T008 depends on T006.
  - T009 depends on T004.
- **Phase 3 (US1)**: Depends on Phase 2. Independently testable end-to-end after the phase completes (assuming the mu-plugin is deployed manually, which is what Phase 4 automates).
- **Phase 4 (US2)**: Depends on Phase 2. Can run **in parallel with Phase 3** (no code overlap — different ability classes, different rows in the bootstrap). MVP requires both.
- **Phase 5 (US3)**: Depends on Phase 2 + on T012 (shares the sandbox-scrape helper defined on `Set_Override`). Can start once T012 lands.
- **Phase 6 (US4)**: Depends on T006 + T008 + T012. No new source files.
- **Phase 7 (Polish)**: Depends on all preceding phases.

### User Story Dependencies

- **US1 (P1)**: Independent — can be developed and tested standalone after Foundational lands.
- **US2 (P1)**: Independent — can be developed and tested standalone after Foundational lands. **In production**, US1 needs US2 to have been invoked at least once (otherwise the mu-plugin is missing and overrides are inert), but the code paths do not touch each other.
- **US3 (P2)**: Depends on US1 for the shared sandbox-scrape helper. Otherwise independent.
- **US4 (P2)**: Depends on US1 (cascade is implemented inside `Set_Override::execute()`). Validation-only phase.

### Within Each User Story

- Helpers and category exist (Phase 2 checkpoint) before ability classes are written.
- Each ability class is a standalone file. Different classes can be written in parallel (marked `[P]`).
- The bootstrap wiring task per phase (T014, T017, T019) is the only serialisation point within its phase — it edits the same file (`AcrossAI_Core_Abilities_Bootstrap.php`).
- No test-before-implementation ordering is enforced (per §VII deviation — helper tests land alongside their helpers in Phase 2).

### Parallel Opportunities

- **Phase 1**: T002 is `[P]` — can be done alongside T001.
- **Phase 2**: T003, T004, T006, T007, T008 are `[P]` — five tasks parallelisable. T005 and T009 are serialising points (T005 is the fat helper file everyone else consumes; T009 is the bootstrap wiring).
- **Phase 3**: T010, T011, T013 are `[P]` — three ability classes in parallel. T012 is more complex (sandbox scrape + cascade wiring). T014 serialises the bootstrap wiring.
- **Phase 4**: T015 and T016 are both `[P]` — two independent ability classes.
- **Phase 5**: T018 and T019 are sequential (T019 must reference T018's class).
- **Phase 6**: T020 and T021 are sequential.
- **Phase 7**: T022, T023, T024, T025, T026, T029 are all `[P]`.

---

## Parallel Example: Phase 2 Foundational

```bash
# Once T001 completes, launch these five in parallel:
Task: "Write mu-plugin source to includes/Abilities/Debugging/assets/wp-conflict-tester.php"          # T003
Task: "Create Category_Registrar.php"                                                                   # T004
Task: "Create Dependency_Resolver.php"                                                                  # T006
Task: "Create OverridesStoreTest.php"                                                                   # T007 (depends on T005 landing — can start once T005 is drafted)
Task: "Create DependencyResolverTest.php"                                                               # T008

# Then T005 (Overrides_Store) — the fat helper everyone else consumes
Task: "Create Overrides_Store.php with read/write/clear/mu_plugin_status methods"                       # T005

# Then T009 wires the category
Task: "Wire Category_Registrar into AcrossAI_Core_Abilities_Bootstrap::register_category_callbacks()"   # T009
```

## Parallel Example: Phase 3 US1

```bash
# Three ability classes in parallel — different files, no cross-deps
Task: "Create List_Plugins.php ability class"                                                           # T010
Task: "Create Get_Overrides.php ability class"                                                          # T011
Task: "Create Clear_Overrides.php ability class"                                                        # T013

# Then Set_Override (the complex one — sandbox scrape + cascade)
Task: "Create Set_Override.php ability class"                                                           # T012

# Finally serialise the bootstrap wiring
Task: "Register the four US1 abilities in AcrossAI_Core_Abilities_Bootstrap::register_abilities()"       # T014
```

---

## Implementation Strategy

### MVP First (User Stories 1 + 2)

Both US1 and US2 are P1 and required for the MVP.

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational — **STOP and validate** that PHPUnit passes for the two helpers before moving on.
3. Complete Phase 3 (US1) and Phase 4 (US2) — can be developed in parallel by two developers, or serially by one.
4. **STOP and VALIDATE**: run `quickstart.md` §§1–6 end-to-end. Both US1 and US2 should be independently observable.
5. Ship as v0.0.21-rc if desired.

### Incremental Delivery

1. MVP (Phases 1–4) → tag v0.0.21-mvp → validate on Local by Flywheel's test bench
2. Add Phase 5 (US3 bulk) → tag v0.0.21-rc1 → validate `quickstart.md` §7
3. Add Phase 6 (US4 cascade) → tag v0.0.21-rc2 → validate `quickstart.md` §8
4. Complete Phase 7 (Polish) → tag v0.0.21 → ship
5. Each increment is deployable — no user story leaves the code in an incoherent state.

### Parallel Team Strategy

With two developers post-Foundational:

1. Both complete Phases 1 + 2 together (foundational tasks are relatively short and inter-dependent).
2. Once Phase 2 checkpoint passes:
   - Developer A: Phase 3 (US1) → Phase 5 (US3 depends on T012 from US1)
   - Developer B: Phase 4 (US2) → Phase 6 (US4)
3. Both converge on Phase 7 (Polish) together.

---

## Notes

- `[P]` tasks touch different files and have no incomplete dependencies — safe to parallelise.
- Every ability class extends `AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition` — the base-class auto-registers on `plugins_loaded @ P20` per its own contract, so **no manual `wp_register_ability()` call is needed** in any ability file or in the bootstrap.
- The bootstrap wiring pattern is exactly what sibling domains (`FileManager`, `Cache`, `Database`, etc.) already do — copy their patterns verbatim.
- Constitution deviations documented in `plan.md`'s Complexity Tracking: (§VI) shared helpers stay under `includes/Abilities/Debugging/` until a second consumer emerges; (§VII) PHPUnit coverage for the two helpers only (thin ability wrappers untested). These are **deliberate** deviations with rationale — do not "fix" them without amending `plan.md`.
- Commit after each task or logical group. The atomic phase boundaries are natural commit points.
- Verify Constitution §IV `permission_callback` return type on every new ability: `static fn(): bool => current_user_can( 'manage_options' )` returns `bool`, which the plan.md audit approves. Do **not** return `WP_REST_Response` — the constitutional prohibition explicitly names that pattern as a critical security defect.
