# Tasks: Ability Suggestions Framework

**Input**: Design documents from `/specs/095-suggested-abilities-framework/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Included — the spec's Success Criteria explicitly require test coverage for framework + kill-switch (SC-004, SC-005).

**Organization**: Grouped by user story. Each story is independently deliverable.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Different file, no dependency on incomplete tasks — safe to run in parallel
- **[Story]**: US1 / US2 / US3 traceability tag
- Every task lists the exact file path

## Path Conventions

Single-project WordPress plugin at repo root. All paths are relative to `/Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/plugins/acrossai-abilities-manager/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization.

_No tasks — no new Composer dependencies, no scaffolding, no build-tool changes. The plugin's existing structure is sufficient._

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Framework method + injection wiring. Every user story reads from this.

- [X] T001 Add `protected function suggested_abilities(): array` default return `array()` to `includes/Modules/Library/Ability_Definition.php`, and add an injection block in `push_definition()` (parallel to the existing `suggested_plugins()` block at lines 96–98 and 118–127) that writes non-empty returns to `args.meta.acrossai.suggested_abilities`. Empty returns MUST NOT write the key.

**Checkpoint**: US1 and US3 can now proceed. US2 can proceed once T001 lands (Registry decoration in Phase 4 reads what T001 injects).

---

## Phase 3: User Story 1 - AI caller sees cheaper alternatives (Priority: P1) 🎯 MVP

**Goal**: An AI caller inspecting an ability with declared suggestions sees them under `meta.acrossai.suggested_abilities` in the details response.

**Independent Test**: Live MCP call `mcp-adapter-get-ability-info` for `content/update-page` returns a `meta.acrossai.suggested_abilities` array containing `blocks/outline-post-blocks` with a non-empty `reason`.

### Tests for User Story 1 (write first — must fail before T001 lands)

- [X] T002 [P] [US1] Write `tests/phpunit/abilities/Test_Ability_Suggestions_Framework.php` covering: (a) `Ability_Definition::suggested_abilities()` exists as protected instance method returning `array`, (b) default return is `[]`, (c) an ability overriding with 2 entries yields `args.meta.acrossai.suggested_abilities` with those 2 entries preserving order, (d) an ability overriding to `[]` yields NO `suggested_abilities` key in the returned `args`, (e) source-inspection sweep over the 4 initial-batch abilities asserts each declares non-empty `slug` and non-empty `reason` for every entry.
- [X] T003 [US1] Register `Test_Ability_Suggestions_Framework.php` in `phpunit.xml.dist` under the `abilities-unit` testsuite block.

### Implementation for User Story 1

- [X] T004 [P] [US1] Add `suggested_abilities()` override to `includes/Abilities/Content/Update_Page.php` declaring `blocks/outline-post-blocks` and `blocks/update-post-block` with clear reasons and a `saves` hint on the first entry (~29K tokens for narrow edits on a 97 KB page).
- [X] T005 [P] [US1] Add `suggested_abilities()` override to `includes/Abilities/Content/Update_Post.php` — same two suggestions, same shape.
- [X] T006 [P] [US1] Add `suggested_abilities()` override to `includes/Abilities/Content/Update_Cpt_Item.php` — same two suggestions.
- [X] T007 [P] [US1] Add `suggested_abilities()` override to `includes/Abilities/Content/Get_Post_Blocks.php` declaring `blocks/outline-post-blocks` as the cheap-lookup alternative when the caller only needs paths.

**Checkpoint**: US1 fully functional. Framework injects declared suggestions into ability payloads on registration; the four flagship abilities all carry suggestions; structural tests green.

---

## Phase 4: User Story 2 - Admin toggle disables suggestions site-wide (Priority: P2)

**Goal**: A site admin ticking "Disable ability suggestions" on the Abilities settings tab strips `suggested_abilities` from every ability's payload on the next request. Untick restores. Uninstall (with delete-data on) removes the setting.

**Independent Test**: With T004 landed, `AcrossAI_Ability_Library_Registry::instance()->get_definitions()` returns `content/update-page` with the field present when option `acrossai_disable_ability_suggestions = 0`, and without the field when `= 1`. Every other `meta.acrossai.*` key survives the strip.

### Tests for User Story 2

- [X] T008 [US2] Write `tests/phpunit/abilities/Test_Ability_Suggestions_Kill_Switch.php` covering: (a) with option unset AND with option `= 0`, `get_definitions()` output includes `args.meta.acrossai.suggested_abilities` on a fixture ability with declared suggestions, (b) with option `= 1`, the field is stripped from every row, (c) the strip only affects `suggested_abilities` — a control assertion confirms sibling `sub_group`, `tab_group`, and `suggested_plugins` keys survive on the same fixture row.
- [X] T009 [US2] Register `Test_Ability_Suggestions_Kill_Switch.php` in `phpunit.xml.dist` under `abilities-unit`.

### Implementation for User Story 2

- [X] T010 [US2] Add `apply_suggested_abilities_decoration()` static helper to `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php` mirroring the shape of `apply_suggested_plugins_decoration()` at line 168, and call it in `get_definitions()` alongside the existing plugin-suggestions decoration. Reads `get_option( 'acrossai_disable_ability_suggestions', 0 )`; when truthy, unsets `$row['args']['meta']['acrossai']['suggested_abilities']` on every row.
- [X] T011 [US2] Add a new settings section to `admin/Partials/SettingsMenu.php`: `register_setting()` call for `acrossai_disable_ability_suggestions` with `sanitize_disable_ability_suggestions` callback (checkbox → 0/1), `add_settings_section( 'acrossai_ability_suggestions_settings_section' )`, `add_settings_field( 'acrossai_disable_ability_suggestions' )` targeting that section, plus `render_ability_suggestions_section()` and `render_ability_suggestions_field()` methods. Section priority chosen to land BETWEEN "Plugin Suggestions" and "Uninstall Settings" (register with `admin_init` priority 10 same as siblings; declaration order in the file controls display order).
- [X] T012 [US2] Add `delete_option( 'acrossai_disable_ability_suggestions' );` to `uninstall.php` at line 76 area, alongside the existing Feature 088 delete, gated by the existing `acrossai_abilities_uninstall_delete_data` uninstall-data-cleanup guard.

**Checkpoint**: US1 AND US2 both work. Admin sees the new toggle; toggling it strips the field site-wide; unticking restores it. Uninstall cleans up.

---

## Phase 5: User Story 3 - Ability author declares alternatives inline (Priority: P3)

**Goal**: A developer adding a suggestion to a new ability class touches ONE file — their ability's own class — and sees the entry appear in the payload with no other changes.

**Independent Test**: Diff of the four initial-batch overrides (T004–T007) shows each is a self-contained single-file addition of one method. `Test_Ability_Suggestions_Framework.php` includes this as a source-inspection assertion.

### Implementation for User Story 3

- [X] T013 [US3] Verify `specs/095-suggested-abilities-framework/quickstart.md` "For ability authors" section matches the shipped API exactly (method name, return shape, entry keys). Fix any drift between the doc and what T001/T004 actually implement.

**Checkpoint**: All three user stories are independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T014 [P] Append CHANGELOG entry to `README.txt` Unreleased section describing the new framework, the four initial-batch overrides, the admin toggle option key, and the "no schema drift when unused" guarantee.
- [X] T015 Run `composer dump-autoload -o` to refresh the classmap (no new classes here, but ensures autoload sees any renames if T011 introduces private helper methods that get moved).
- [X] T016 Run `./vendor/bin/phpunit --filter Test_Ability_Suggestions` — both new suites green.
- [X] T017 Run `./vendor/bin/phpunit` — full suite passes, no Feature 088 regressions, count remains ≥2123 (currently 2123 on `main`).
- [X] T018 Run `./vendor/bin/phpcs` on every changed file: `includes/Modules/Library/Ability_Definition.php`, `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php`, `admin/Partials/SettingsMenu.php`, `uninstall.php`, all four `includes/Abilities/Content/Update_*.php` and `Get_Post_Blocks.php`, both test files.
- [X] T019 Run `./vendor/bin/phpstan analyse --no-progress` on the same file set. Zero errors expected at level 8.
- [X] T020 Live MCP smoke against `wordpress-7-0-default-mcp-server`: (a) call `mcp-adapter-get-ability-info` for `content/update-page` — assert response includes `meta.acrossai.suggested_abilities` with `blocks/outline-post-blocks`; (b) admin visits `?page=acrossai-settings&tab=abilities`, ticks "Disable ability suggestions", saves; re-call — assert field absent; (c) untick + re-call — assert field returns.
- [X] T021 Commit staged changes on branch `095-suggested-abilities-framework`. Push to origin. Open PR against `main` with body summarizing framework + 4 overrides + kill-switch. Wait for CI (8 checks). Merge with `gh pr merge --squash --delete-branch` once green. — shipped via PR #155 (`ce76d9c`); follow-up PR #156 (`d6ceaf1`) added 10 more overrides on the hint catalog.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: N/A
- **Phase 2 (Foundational)**: T001 alone. Blocks every user story.
- **Phase 3 (US1)**: T002 first (fails), then T003–T007 in parallel where marked, then T008 for wiring closure.
- **Phase 4 (US2)**: Can start after Phase 2. T008 (test) first (fails until T010 lands), then T009 for wiring; then T010–T012 in strict order because T010 and T011 both touch closely coupled surfaces but different files.
- **Phase 5 (US3)**: Verification-only after Phase 3 lands.
- **Phase 6 (Polish)**: Sequential; must complete before PR ships.

### User Story Dependencies

- **US1**: Depends only on Phase 2. Deliverable-on-its-own MVP.
- **US2**: Depends on Phase 2. Can proceed in parallel with US1 for a two-person team, though single-dev flow will typically finish US1 then US2 because the kill-switch test uses a fixture from US1's overrides.
- **US3**: Purely documentation-verification; runs after US1.

### Parallel Opportunities

- T004, T005, T006, T007 all touch different ability files — full parallel fan-out safe.
- T014 (CHANGELOG) can start any time in Phase 6 alongside quality gates.

---

## Parallel Example: User Story 1 implementation

```bash
# After T001 lands and T002/T003 test wiring is done:
# Launch these four ability overrides in parallel (different files, no shared state):
Task: "Add suggested_abilities() override to includes/Abilities/Content/Update_Page.php"
Task: "Add suggested_abilities() override to includes/Abilities/Content/Update_Post.php"
Task: "Add suggested_abilities() override to includes/Abilities/Content/Update_Cpt_Item.php"
Task: "Add suggested_abilities() override to includes/Abilities/Content/Get_Post_Blocks.php"
```

---

## Implementation Strategy

### MVP (US1 only)

1. Phase 2 (T001).
2. Phase 3 (T002–T007).
3. STOP; validate: an MCP `get-ability-info` call for `content/update-page` returns suggestions in the response.
4. Ship as-is if the site owner never needs to disable them.

### Full delivery (recommended for this feature)

1. Phase 2 → 3 → 4 → 5 → 6 sequentially.
2. Bundle in one PR — the four ability overrides need the framework to be useful, the kill-switch needs at least one ability with declared suggestions to be verifiable, and the settings UI without a kill-switch to gate is pointless. Per the user's established preference for bundled PRs on tightly-coupled changes (memory `feedback_ship_via_pr_not_direct_merge` — deliver via PR, and internal preference for bundled PRs is documented in past sessions).

---

## Notes

- [P] tasks touch different files with no shared state.
- Every task's file path is absolute-relative-to-repo-root and unambiguous.
- The two test files (T002, T008) both live in `tests/phpunit/abilities/` and can be worked on in parallel by different developers; each covers a distinct concern.
- The kill-switch test (T008) intentionally uses a fixture built by the T004-T007 overrides. If following the strict TDD ordering, write T008 against a synthetic in-test fixture ability rather than a real one — then the test is meaningful even before T004–T007 land.
- Commit per phase, not per task, to keep the git history readable. One PR at the end (T021).
