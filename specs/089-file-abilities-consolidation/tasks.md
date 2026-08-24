---
description: "Task list for feature 089 — File Abilities Consolidation"
---

# Tasks: File Abilities Consolidation

**Input**: Design documents from `specs/089-file-abilities-consolidation/`
**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅, data-model.md ✅, contracts/ ✅, quickstart.md ✅
**Tests**: INCLUDED — Constitution §VII (Definition of Done) mandates PHPUnit tests for all new logic.

**Organization**: Tasks are grouped by user story. All three stories are P1 and can be sequenced or partly parallelised (US1 and US3 are independent; US2 depends on US1 shipping first).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: User story tag from spec.md (US1, US2, US3)

## Path Conventions

WordPress plugin single-project layout. All paths relative to plugin root: `/Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/plugins/acrossai-abilities-manager/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm the tooling gates the constitution requires are runnable before we start editing.

- [ ] T001 Verify `composer test` (PHPUnit), `vendor/bin/phpstan analyse`, and `vendor/bin/phpcs` all execute cleanly on `main` before this feature adds diff. Baseline: PHPUnit green, PHPStan level 8 clean, PHPCS clean.
- [ ] T002 Confirm `File_Mods_Guard` (`includes/Abilities/Utilities/File_Mods_Guard.php`) and `Ability_Definition` (`includes/Modules/Library/Ability_Definition.php`) are present and used by the reference class `includes/Abilities/FileManager/Read_File.php`.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: None required. `File_Mods_Guard`, `Ability_Definition`, and the `PROTECTED_FILES` guard pattern are already established. Skip to user stories.

**Checkpoint**: Foundation ready — user story implementation can now begin.

---

## Phase 3: User Story 1 — One consolidated file-manager surface (Priority: P1) 🎯 MVP

**Goal**: Add three new abilities (`file-manager/list-directory`, `file-manager/copy-file`, `file-manager/move-file`) so file-manager can fully replace the theme/plugin-scoped read/write/list/copy/move abilities.

**Independent Test**: With only US1 shipped, an MCP client can list a plugin directory, copy a file inside `wp-content/plugins/`, and move a file — all through `file-manager/*` — without touching the theme/plugin-scoped abilities.

### Tests for User Story 1

- [ ] T010 [P] [US1] Create PHPUnit test class `tests/Unit/Abilities/FileManager/List_Directory_Test.php`. Cover: happy path with fixture directory, `max_depth` cap, `max_entries` cap with `truncated:true`, `invalid_path` refusal (path outside ABSPATH), `not_a_directory` refusal (path exists but is a file), symlink-not-followed behaviour.
- [ ] T011 [P] [US1] Create PHPUnit test class `tests/Unit/Abilities/FileManager/Copy_File_Test.php`. Cover: happy path within `wp-content/plugins/`, `destination_exists` refusal without `overwrite`, `overwrite:true` succeeds and reports `overwritten:true`, `source_not_found` refusal, `invalid_path` refusal for source or destination outside ABSPATH, `file_mods_disabled` refusal when `File_Mods_Guard` blocks, `protected_write` refusal when destination resolves to `wp-config.php` or `.htaccess` even with `overwrite:true`.
- [ ] T012 [P] [US1] Create PHPUnit test class `tests/Unit/Abilities/FileManager/Move_File_Test.php`. Cover the same matrix as Copy_File_Test plus: source disappears on success, `protected_write` refusal when source resolves to `wp-config.php` or `.htaccess`.

### Implementation for User Story 1

- [ ] T013 [P] [US1] Create `includes/Abilities/FileManager/List_Directory.php`. Extend `Ability_Definition`. Namespace `AcrossAI_Abilities_Manager\Includes\Abilities\FileManager`. Ability spec matches `specs/089-file-abilities-consolidation/contracts/file-manager-list-directory.json` (name `file-manager/list-directory`, label "List Directory", annotations `readonly:true, idempotent:true`). Implement `execute_callback`: (a) `manage_options` capability check, (b) `realpath()` parent-scope check against `realpath(ABSPATH)`, (c) `is_dir()` guard, (d) `RecursiveDirectoryIterator` + `RecursiveIteratorIterator` walk with `SKIP_DOTS` and NO symlink following, (e) depth cap via `$iterator->getDepth()`, (f) entry cap with `truncated:true` when reached, (g) return envelope per data-model.md §2.
- [ ] T014 [P] [US1] Create `includes/Abilities/FileManager/Copy_File.php`. Extend `Ability_Definition`. Namespace `AcrossAI_Abilities_Manager\Includes\Abilities\FileManager`. Ability spec matches `specs/089-file-abilities-consolidation/contracts/file-manager-copy-file.json` (name `file-manager/copy-file`, annotations `destructive:false, idempotent:false`). Define `PROTECTED_FILES = ['wp-config.php', '.htaccess']`. Implement `execute_callback`: (a) `manage_options`, (b) `File_Mods_Guard::check('edit')`, (c) `realpath` scope check for source and destination-parent, (d) source-is-file guard, (e) protected-destination guard (basename-at-ABSPATH-root), (f) destination-exists guard unless `overwrite:true`, (g) `copy()` call, (h) return envelope per data-model.md §3.
- [ ] T015 [P] [US1] Create `includes/Abilities/FileManager/Move_File.php`. Extend `Ability_Definition`. Namespace `AcrossAI_Abilities_Manager\Includes\Abilities\FileManager`. Ability spec matches `specs/089-file-abilities-consolidation/contracts/file-manager-move-file.json` (annotations `destructive:true, idempotent:false`). Same guard chain as Copy_File plus protected-source guard. Use `rename()` for the move.
- [ ] T016 [US1] Register the three new abilities in `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php`. Add `List_Directory::instance()`, `Copy_File::instance()`, `Move_File::instance()` (or the module's actual instantiation pattern — mirror how `Read_File`, `Create_File`, etc. are wired at ~lines 194-199). Verify the category assignment happens automatically via `Category_Registrar` or add explicit assignment if the module requires it.
- [ ] T017 [US1] Run `composer test -- --filter FileManager` — all three new test classes green. Run `vendor/bin/phpstan analyse includes/Abilities/FileManager/List_Directory.php includes/Abilities/FileManager/Copy_File.php includes/Abilities/FileManager/Move_File.php` — level 8 clean. Run `vendor/bin/phpcs includes/Abilities/FileManager/List_Directory.php includes/Abilities/FileManager/Copy_File.php includes/Abilities/FileManager/Move_File.php` — clean.
- [ ] T018 [US1] Execute quickstart steps 1, 3, 4 from `specs/089-file-abilities-consolidation/quickstart.md` against the local WordPress install. All three new slugs appear in `wp ability list` and each call returns the expected `success:true` payload.

**Checkpoint**: US1 complete — the three new file-manager abilities exist and work end-to-end. US2 (removal) is now unblocked because callers have documented replacements.

---

## Phase 4: User Story 3 — Close the protected-write guard gap (Priority: P1)

**Goal**: Add the `PROTECTED_FILES` refusal (parity with `Read_File` / `Delete_File`) to `Create_File.php` and `Edit_File.php` so generic file-manager writes cannot overwrite `wp-config.php` or `.htaccess`.

**Independent Test**: With only US3 shipped, calling `file-manager/create-file` or `file-manager/edit-file` against `wp-config.php` or `.htaccess` returns `{success:false, blocked_reason:"protected_write"}`; before this task both would silently succeed.

**Note**: US3 is independent of US1 — it can be developed in parallel. It is listed after US1 only because US1 is the load-bearing scope; US3 is a small hardening.

### Tests for User Story 3

- [ ] T020 [P] [US3] Create or extend `tests/Unit/Abilities/FileManager/Create_File_Test.php` to cover: refuses `path=wp-config.php` with `blocked_reason:protected_write`, refuses `path=.htaccess` with the same, still succeeds for a normal path in `wp-content/uploads/`.
- [ ] T021 [P] [US3] Create or extend `tests/Unit/Abilities/FileManager/Edit_File_Test.php` with the same three cases as Create_File_Test.

### Implementation for User Story 3

- [ ] T022 [P] [US3] Edit `includes/Abilities/FileManager/Create_File.php`: add a `private const PROTECTED_FILES = array( 'wp-config.php', '.htaccess' );` at the class top matching the pattern in `Read_File.php:29-32`. In `execute_callback` (after `realpath` scope check, before `file_exists` guard), when `basename($real_target) === 'wp-config.php'` or `'.htaccess'` and the parent resolves to `realpath(ABSPATH)`, return the same `blocked_reason:protected_write` envelope used by `Delete_File.php:135-138`. Update the ability `description` to state the refusal.
- [ ] T023 [P] [US3] Edit `includes/Abilities/FileManager/Edit_File.php`: apply the identical change as T022. Same constant, same guard, same envelope. Update `description`.
- [ ] T024 [US3] Run PHPUnit for the two extended test classes: `composer test -- --filter 'Create_File_Test|Edit_File_Test'`. Both green. PHPStan + PHPCS clean on the two edited files.
- [ ] T025 [US3] Execute quickstart step 6 against the local install: `wp ability call file-manager/edit-file '{"path":"wp-config.php","content":"x"}'` returns `blocked_reason:protected_write`. Same for `.htaccess`. Same for `create-file` on `.htaccess`.

**Checkpoint**: US3 complete — the generic write path can no longer overwrite `wp-config.php` or `.htaccess`. Consolidation can now proceed without widening the attack surface.

---

## Phase 5: User Story 2 — Remove the six duplicate scoped abilities (Priority: P1)

**Goal**: Delete `themes/read-theme-code`, `themes/edit-theme-file`, `themes/read-theme-structure`, `plugins/read-plugin-code`, `plugins/read-plugin-structure`, `plugins/manage-plugin-files` (classes + bootstrap wiring + category-registrar assignments + docs). Callers using the removed slugs see an "unknown ability" error and the CHANGELOG points them to the `file-manager/*` replacement.

**Independent Test**: After US2 ships, `wp ability list` returns none of the six removed slugs; calling any of them returns an "unknown ability" error.

**Depends on**: US1 (need `list-directory`, `copy-file`, `move-file` registered so integrators have replacements documented).

### Implementation for User Story 2

- [ ] T030 [US2] Delete file `includes/Abilities/Themes/Read_Theme_Code.php`.
- [ ] T031 [US2] Delete file `includes/Abilities/Themes/Edit_Theme_File.php`.
- [ ] T032 [US2] Delete file `includes/Abilities/Themes/Read_Theme_Structure.php`.
- [ ] T033 [US2] Delete file `includes/Abilities/Plugins/Read_Plugin_Code.php`.
- [ ] T034 [US2] Delete file `includes/Abilities/Plugins/Read_Plugin_Structure.php`.
- [ ] T035 [US2] Delete file `includes/Abilities/Plugins/Manage_Plugin_Files.php`.
- [ ] T036 [US2] Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php`: remove the six `use` statements and the six `::instance()` calls for the deleted classes (~lines 194-199 per exploration notes). Verify no other file references these six classes via `grep -rn "Read_Theme_Code\|Read_Theme_Structure\|Edit_Theme_File\|Read_Plugin_Code\|Read_Plugin_Structure\|Manage_Plugin_Files" includes/ tests/`; expect zero hits.
- [ ] T037 [US2] Edit `includes/Abilities/Themes/Category_Registrar.php` — if it explicitly enumerates the three removed theme abilities, drop those entries. If the registrar only defines the category itself and picks up abilities from `wp_register_ability` at runtime, leave it unchanged.
- [ ] T038 [US2] Edit `includes/Abilities/Plugins/Category_Registrar.php` — same treatment as T037 for the three removed plugin abilities.
- [ ] T039 [US2] Edit `docs/abilities-inventory.md`: remove the six rows for the deleted abilities. Add three rows for `file-manager/list-directory`, `file-manager/copy-file`, `file-manager/move-file` matching the format of existing FileManager rows.
- [ ] T040 [US2] Edit `README.md` and `README.txt`: add a CHANGELOG entry for the next release version labelled BREAKING that names each removed slug alongside its `file-manager/*` replacement. Use the mapping in `specs/089-file-abilities-consolidation/contracts/removed-abilities.json`.
- [ ] T041 [US2] Run `composer test` — full suite green (no lingering test asserts against the removed abilities). Run `vendor/bin/phpstan analyse` — level 8 clean (no dead-code warnings from stale references). Run `vendor/bin/phpcs` — clean.
- [ ] T042 [US2] Execute quickstart steps 2, 7, 8 against the local install. Step 2: none of the six removed slugs appear in `wp ability list`. Step 7: `read-wp-config` and `read-debug-log` still work. Step 8: `wp ability call themes/read-theme-code ...` returns an "unknown ability" error.

**Checkpoint**: US2 complete — the six duplicate slugs are gone, integrators have a documented migration path, no dead references remain.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final quality gates and verification.

- [ ] T050 [P] Run the WordPress Plugin Check tool against the production plugin surface (per Constitution §II). Zero errors, zero warnings. Reference the CI workflow at `.github/workflows/` if a `plugin-check` job exists there.
- [ ] T051 [P] Execute the full `specs/089-file-abilities-consolidation/quickstart.md` sequence (steps 1 through 10) against the local WordPress install. All steps pass.
- [ ] T052 [P] Optional live check via the `claude.ai acrosai` MCP connector (quickstart step 9): discover-abilities returns the new file-manager slugs, list-directory works on a plugin dir, copy-file works within `wp-content/plugins/`, edit-file on `wp-config.php` returns the refusal.
- [ ] T053 Verify no memory (`.claude/projects/.../memory/`) entries reference the six removed slugs or their classes; if any do, update or remove them.
- [ ] T054 Final commit sweep on branch `089-file-abilities-consolidation`. Squash trivial WIP commits into logically grouped commits (add, harden, remove). Push branch.
- [ ] T055 Open PR to `main` via `gh pr create` with title "feat(089): consolidate file abilities into file-manager namespace" and body summarising: (a) three new abilities added, (b) six duplicate abilities removed with slug → replacement mapping, (c) protected-write guard gap closed in create-file and edit-file, (d) verification steps run. Per memory: ship via PR, not direct merge to main.

---

## Dependencies

```
Setup (T001-T002)
     │
     ├─→ US1 (T010-T018) ─────┐
     │                         │
     ├─→ US3 (T020-T025) ──────┼─→ Polish (T050-T055)
     │                         │
     └─→ (blocked)  US2 (T030-T042) ─┘
                        (depends on US1 T016 landing so replacements exist and can be referenced in docs)
```

- **US1** and **US3** are fully independent — can be executed in parallel.
- **US2** must start after US1's bootstrap wiring lands (T016) so replacements are live before docs point to them, though the actual file deletions could technically happen sooner. Recommended: complete US1 fully before US2 to keep the branch always in a shippable state.
- **Polish** runs after all three user stories are green.

## Parallel Execution Examples

**Within US1** — the three ability classes are independent:
- T013 (List_Directory.php), T014 (Copy_File.php), T015 (Move_File.php) can be authored simultaneously.
- Their tests T010, T011, T012 can also be written in parallel.

**Within US3** — the two file edits are independent:
- T022 (Create_File.php) and T023 (Edit_File.php) touch different files.
- Tests T020 and T021 similarly.

**Across US1 and US3** — completely independent modules; can run truly concurrent.

**Within US2** — the six file deletions T030-T035 are all `[P]` in principle but the follow-up `grep` sanity check in T036 needs all six deletions plus the bootstrap edit together, so treat US2 as a serial phase for review clarity.

## Implementation Strategy

**MVP scope**: US1 alone is the MVP. It delivers the consolidated surface (list-directory, copy-file, move-file), which is the load-bearing user value. The plugin remains fully functional with no regression if US2 and US3 slip.

**Incremental delivery order**:
1. Ship US1 first — expands file-manager surface with zero removals. Fully backward compatible.
2. Ship US3 next — closes the write-path guard gap. Small, targeted, uncontroversial hardening.
3. Ship US2 last — the breaking change. Coming after US1 means integrators can migrate on their own timeline before the removals land.

If the team prefers a single PR (recommended for this feature — small enough to review as one unit), all three stories ship together and the CHANGELOG entry documents the combined scope.
