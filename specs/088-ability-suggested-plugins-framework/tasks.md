---

description: "Task list for Feature 088 — ability-level suggested-plugins framework"
---

# Tasks: Ability-level suggested-plugins framework

**Input**: Design documents from `/specs/088-ability-suggested-plugins-framework/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Included — the plan calls out `Test_Feature_088_Suggested_Plugins_Framework.php` and gates the Definition of Done on unit-test presence. Tests are woven into each phase, not deferred.

**Organization**: Tasks are grouped by user story so each story can be implemented, tested, and merged independently. Foundational work (base class + Registry) is a blocking prerequisite for all three user stories.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3). Setup / Foundational / Polish tasks have no story label.
- Every task includes an exact file path.

## Path Conventions

Single-project WordPress plugin layout (per plan.md):

- PHP framework code: `includes/Modules/Library/`
- PHP settings + uninstall: existing files (located during implementation)
- JSX Library UI: `src/js/ability-library/components/`
- Tests: `tests/phpunit/abilities/`
- PHPUnit config: `phpunit.xml.dist`
- Uninstall: `uninstall.php`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm branch + spec-kit artifacts are ready before any code changes.

- [ ] T001 Confirm working on branch `088-ability-suggested-plugins-framework` (already created off `main` earlier this session)
- [ ] T002 Confirm the spec-kit artifacts committed to the branch: `specs/088-ability-suggested-plugins-framework/{spec.md,plan.md,research.md,data-model.md,quickstart.md,contracts/,checklists/}` and `docs/planning/088-ability-suggested-plugins-framework.md`
- [ ] T003 Grep + locate the existing AcrossAI settings page file (path to be recorded here for later phases): `grep -rn "acrossai-settings" admin/ includes/ | head`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Extend the base class + registry so that ANY ability that overrides `suggested_plugins()` produces a well-formed payload with kill-switch + install-status enrichment. Once this phase is done, US1 / US2 / US3 can proceed in parallel.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

### Base class extension

- [ ] T004 Add `protected function suggested_plugins(): array` template method to `includes/Modules/Library/Ability_Definition.php`, defaulting to `array()`. Match existing docblock style (PHPCS-clean).
- [ ] T005 Modify `push_definition()` in `includes/Modules/Library/Ability_Definition.php` to call `$this->suggested_plugins()` and, when non-empty, merge the result into `$args['meta']['acrossai']['suggested_plugins']`. Guard with `if ( ! empty( ... ) )` so no key is added for the empty case.

### Registry — kill-switch + is_active enrichment

- [ ] T006 In `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php::format_merged_ability()`, add a read of `get_option( 'acrossai_disable_plugin_suggestions', false )`. When truthy, remove `$meta['acrossai']['suggested_plugins']` from the outgoing payload entirely.
- [ ] T007 Also in `format_merged_ability()`, when the kill-switch is off and `$meta['acrossai']['suggested_plugins']` is a non-empty array, iterate each entry and attach `is_active = ( function_exists( 'is_plugin_active' ) && is_plugin_active( "{slug}/{slug}.php" ) )`. Preserve entry order.

### Test suite bootstrap

- [ ] T008 [P] Add a `<testsuite name="feature-088-unit">` block to `phpunit.xml.dist` pointing at `tests/phpunit/abilities/Test_Feature_088_Suggested_Plugins_Framework.php` (follow the pattern used for `feature-086-unit` / `feature-087-unit`).
- [ ] T009 [P] Create the test file `tests/phpunit/abilities/Test_Feature_088_Suggested_Plugins_Framework.php` with two in-file fixture classes: one that overrides `suggested_plugins()` with a single Better-Search-Replace entry, and one that does NOT override (regression fixture).
- [ ] T010 In the same test file, add data-provider-driven assertions covering all foundational behaviours:
  - Default `suggested_plugins()` returns `[]` on the no-override fixture
  - `push_definition()` auto-inject writes to `meta.acrossai.suggested_plugins` on the override fixture
  - Auto-inject does NOT add the key when the method returns `[]`
  - `format_merged_ability()` enriches each entry with `is_active` (fixture: BSR is installed on the dev site — matches `is_plugin_active( 'better-search-replace/better-search-replace.php' )`)
  - When the site option `acrossai_disable_plugin_suggestions` is set to `1`, `format_merged_ability()` strips the field from the payload regardless of override
  - Malformed entries (missing `slug`, `name`, or `reason`) are silently dropped by enrichment — never fatal
  - Author-omitted `plugin_provides_abilities` / `acrossai_provides_integration` default to `false` in the enriched payload

**Checkpoint**: Foundation complete. `vendor/bin/phpunit --testsuite feature-088-unit` MUST pass before proceeding.

---

## Phase 3: User Story 1 — Site admin discovers suggested plugins on Library cards (Priority: P1) 🎯 MVP

**Goal**: Render a "Consider also" section on each ability card whose payload contains a non-empty `meta.acrossai.suggested_plugins[]` list. Show install-status badge + source-label pill per entry.

**Independent Test**: With Phase 2 in place, temporarily add a `suggested_plugins()` override to any one existing ability (e.g., in a test branch), load the Library page, and confirm the card renders the new section correctly. Cards for abilities without an override remain visually unchanged.

### UI rendering

- [ ] T011 [US1] In `src/js/ability-library/components/LibraryPage.js` (or the per-card sub-component if extracted), destructure `suggested_plugins` from the ability's `meta.acrossai`. Guard so the section renders only when the array is present AND non-empty.
- [ ] T012 [US1] Add the "Consider also" section markup below the existing card body. Iterate entries and render name (`esc_html`), one-line reason (`esc_html`), and install-status badge — "Active" pill when `is_active === true`, otherwise "Install" link pointing at `entry.url || 'https://wordpress.org/plugins/' + entry.slug + '/'` (escape via URL helper).
- [ ] T013 [US1] Add source-label pill derived from the two booleans per the four-way table in spec.md FR-010:
  - `plugin_provides_abilities: true, acrossai_provides_integration: false` → "Native abilities"
  - `false / true` → "AcrossAI integration"
  - `true / true` → "Native + AcrossAI"
  - `false / false` → "UI-only" (grey, low-emphasis)
- [ ] T014 [US1] Add SCSS/CSS for the new section — grey-toned, low-emphasis, matches existing card style. If SCSS lives at `src/scss/ability-library/`, add there; otherwise inline via existing card stylesheet.
- [ ] T015 [P] [US1] Run `npm run lint -- src/js/ability-library/` and confirm zero warnings/errors on the modified files.
- [ ] T016 [P] [US1] Run `npm run build` and confirm the built JSX passes without errors; verify the bundle produced under `build/` is refreshed.

### Manual verification (US1 acceptance scenarios)

- [ ] T017 [US1] With a fixture override added temporarily to a real ability + BSR active on the dev site, load `admin.php?page=acrossai-abilities-library`. Confirm: (a) target card shows "Consider also" section with the entry, (b) "Active" badge appears, (c) other cards render unchanged.
- [ ] T018 [US1] Deactivate BSR. Reload the Library page. Confirm the badge switches to "Install" with the correct wordpress.org URL. Reactivate BSR.
- [ ] T019 [US1] Remove the temporary fixture override. Reload. Confirm the card renders exactly as before the override was added.

**Checkpoint**: US1 delivers the MVP admin experience. Merging Phase 2 + Phase 3 alone would be a shippable v1 (kill-switch is not yet exposed but suggestions render correctly).

---

## Phase 4: User Story 2 — Site admin toggles all suggestions off/on via a single setting (Priority: P2)

**Goal**: Add a "Disable the Plugin suggestion" checkbox to the AcrossAI settings page. Default unchecked (suggestions shown). Saving takes effect on next page load. Uninstall removes the option only when the plugin's data-removal gate is enabled.

**Independent Test**: With US1 rendering, visit the settings page, toggle the checkbox, save, reload the Library page — the "Consider also" section disappears/reappears accordingly. REST inspection confirms the field is stripped when the setting is on.

### Settings page addition

- [ ] T020 [US2] In the settings page file located in T003, register the new setting using WP Settings API: `register_setting( '<existing-page-slug>', 'acrossai_disable_plugin_suggestions', [ 'type' => 'boolean', 'default' => false, 'sanitize_callback' => [ __CLASS__, 'sanitize_disable_plugin_suggestions' ] ] )`
- [ ] T021 [US2] Add the named public sanitize callback `sanitize_disable_plugin_suggestions( $input ): int` — returns `0` when the value is empty, `1` otherwise. Per `PATTERN-CHECKBOX-SANITIZE`.
- [ ] T022 [US2] Add `add_settings_field()` for the checkbox on the AcrossAI settings page. Label: "Disable the Plugin suggestion". Description: exact text per contracts/admin_setting_kill_switch.md. Render callback outputs a single `<input type="checkbox" name="acrossai_disable_plugin_suggestions" value="1" ... />` with `checked( get_option( 'acrossai_disable_plugin_suggestions', 0 ), 1 )`.
- [ ] T023 [P] [US2] Ensure the render callback escapes all output with `esc_attr()` / `esc_html()` per Constitution §IV.

### Uninstall gate

- [ ] T024 [US2] In `uninstall.php`, add `delete_option( 'acrossai_disable_plugin_suggestions' )` inside the existing `if ( $acrossai_delete_data ) { ... }` block. Follow `PATTERN-UNINSTALL-DATA-GATE`.

### Tests

- [ ] T025 [US2] Extend `Test_Feature_088_Suggested_Plugins_Framework.php` with assertions on the sanitize callback: empty input → `0`; non-empty input → `1`.
- [ ] T026 [US2] Add a regression assertion: `uninstall.php` contains the new `delete_option()` call INSIDE the `$acrossai_delete_data` guard, not outside.

### Manual verification (US2 acceptance scenarios)

- [ ] T027 [US2] Visit `admin.php?page=acrossai-settings` on the dev site. Confirm the "Disable the Plugin suggestion" checkbox appears and is unchecked by default.
- [ ] T028 [US2] Check the box + save. Reload the Library page. Confirm the "Consider also" section is gone on every card. Confirm the ability payload via REST no longer contains `suggested_plugins`.
- [ ] T029 [US2] Uncheck + save. Reload. Confirm the section reappears and REST payload includes the field again.

**Checkpoint**: US2 gives admins governance over the framework. Merging Phase 2 + 3 + 4 delivers the full admin-facing feature.

---

## Phase 5: User Story 3 — MCP agent receives suggestions in ability discovery (Priority: P3)

**Goal**: Verify the MCP `discover-abilities` payload includes `suggested_plugins` (with `is_active` enrichment) when the kill-switch is off, and omits it when on. No new code — this story is delivered by Phase 2's Registry work. This phase is verification only.

**Independent Test**: Invoke the MCP `discover-abilities` tool on the dev site (via the `claude.ai acrosai` connector per user memory) with a fixture override in place. Payload for that ability contains `suggested_plugins[]` with the expected entries.

### Verification

- [ ] T030 [US3] With a fixture override in place and the kill-switch off, call the MCP `discover-abilities` tool via the `claude.ai acrosai` connector. Confirm the tool result for the fixture ability includes `meta.acrossai.suggested_plugins[]` with the declared entries and correct `is_active` values.
- [ ] T031 [US3] Enable the kill-switch via the settings page. Re-invoke MCP `discover-abilities`. Confirm the field is absent from every ability's payload (not present-as-empty-array).
- [ ] T032 [US3] Disable the kill-switch. Confirm field returns on next MCP call.

### Documentation

- [ ] T033 [US3] Add a one-line entry to `AGENTS.md` (or the plugin's contributor doc) documenting that ability authors can override `Ability_Definition::suggested_plugins()` to surface plugin recommendations, and pointing at `specs/088-*/contracts/` for the entry shape.

**Checkpoint**: US3 rounds out the framework — agents and admins both see the same suggestions.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final quality gates, regression checks, and PR prep.

- [ ] T034 Run `php -l` on every modified PHP file: `Ability_Definition.php`, `AcrossAI_Ability_Library_Registry.php`, the settings page file, `uninstall.php`, `Test_Feature_088_Suggested_Plugins_Framework.php`. Expect zero syntax errors.
- [ ] T035 Run `vendor/bin/phpcs includes/Modules/Library/ tests/phpunit/abilities/Test_Feature_088_Suggested_Plugins_Framework.php uninstall.php` — expect zero WPCS errors/warnings (Constitution §II).
- [ ] T036 Run `vendor/bin/phpstan analyse` on the modified paths at level 8 — expect zero errors (Constitution §II).
- [ ] T037 Run `vendor/bin/phpunit --testsuite feature-088-unit` — expect all tests pass.
- [ ] T038 Run `vendor/bin/phpunit` full suite — expect no regressions on any of the existing 500+ ability tests (SC-001).
- [ ] T039 Run `npm run lint` — expect zero errors/warnings on JS side.
- [ ] T040 [P] Run `npm run validate-packages` — expect pass (Constitution §VI, Definition of Done).
- [ ] T041 Update `readme.txt` changelog under a new version bump entry documenting the new `Ability_Definition::suggested_plugins()` template method + new settings-page kill-switch. No breaking-change note (100% additive).
- [ ] T042 Remove any temporary fixture overrides that were added to real ability classes during manual verification (US1 / US3).
- [ ] T043 Confirm `git status` is clean except for the intended feature files. Commit with `feat(088): add suggested-plugins framework + admin kill-switch`. Push to `origin/088-ability-suggested-plugins-framework`.
- [ ] T044 Open PR from `088-ability-suggested-plugins-framework` → `main`. PR body: link to `docs/planning/088-*.md` + `specs/088-*/spec.md`; confirm CI green; request review.

---

## Dependencies

- **Phase 1 (Setup)** blocks Phase 2
- **Phase 2 (Foundational)** blocks Phases 3, 4, and 5 (every user story reads the payload the framework produces)
- **Phase 3 (US1)**, **Phase 4 (US2)**, **Phase 5 (US3)** are independent of each other once Phase 2 is done — can be tackled in parallel by separate contributors
- **Phase 6 (Polish)** runs after all user story phases are complete

## Parallel execution examples

Within a phase, tasks marked `[P]` can run in parallel because they touch different files:

- Phase 2: T008 (phpunit.xml.dist) and T009 (Test_Feature_088_*.php) can be scaffolded in parallel; both must complete before T010 assertions can run
- Phase 3: T015 (ESLint) and T016 (npm build) can run in parallel after T011–T014 land
- Phase 4: T023 (escaping audit) can run alongside T024 (uninstall edit) — different files
- Phase 6: T040 (validate-packages) can run alongside T037 (feature-088 tests)

## Implementation strategy

**MVP scope** = Phase 1 + Phase 2 + Phase 3 (US1). Ships the admin-visible "Consider also" section. Kill-switch and MCP verification are follow-ups that can land in the same PR OR be split.

**Recommended order for a single-contributor PR**:
1. Complete Phase 1 (setup checks)
2. Complete Phase 2 (foundational + tests) — first checkpoint
3. Complete Phase 3 (US1 UI) — MVP is shippable here
4. Complete Phase 4 (US2 kill-switch) — admin governance
5. Complete Phase 5 (US3 verification) — MCP coverage
6. Complete Phase 6 (polish + commit + push + PR)

**If splitting across multiple contributors**: freeze T004–T010 first (foundational), then let Phase 3, 4, 5 proceed in parallel branches merged back to `088-ability-suggested-plugins-framework`.

## Task count summary

- Phase 1 (Setup): 3 tasks (T001–T003)
- Phase 2 (Foundational): 7 tasks (T004–T010)
- Phase 3 (US1): 9 tasks (T011–T019)
- Phase 4 (US2): 10 tasks (T020–T029)
- Phase 5 (US3): 4 tasks (T030–T033)
- Phase 6 (Polish): 11 tasks (T034–T044)

**Total: 44 tasks**. All follow the `- [ ] TXXX [P?] [Story?] Description with file path` format. Parallel opportunities: 6 (T008, T009, T015, T016, T023, T040).
