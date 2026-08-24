---
description: "Task list for feature 090 — File-Manager Additions"
---

# Tasks: File-Manager Additions

**Input**: Design documents from `specs/090-file-manager-additions/`
**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅, data-model.md ✅, contracts/ ✅, quickstart.md ✅
**Tests**: INCLUDED — Constitution §VII mandates PHPUnit tests for all new logic.

**Organization**: Tasks are grouped by user story. All four stories are P1 and fully independent — each ability can be implemented in isolation.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup

- [ ] T001 Confirm `composer test`, `vendor/bin/phpstan analyse`, and `vendor/bin/phpcs` all pass on `main` before this feature adds diff. Baseline noted in spec SC-005.

## Phase 2: Foundational

Skip. Reused utilities (`File_Mods_Guard`, `Ability_Definition`, `PROTECTED_FILES` pattern) already exist and were validated by feature 089.

**Checkpoint**: Foundation ready — user story implementation can begin in parallel.

## Phase 3: User Story 4 — File-info (Priority: P1) 🎯 MVP-first pick

**Why first**: Read-only, no `File_Mods_Guard` needed, no protected-file guard, no confirm flag. Smallest surface. Ship it first as a warm-up + smoke-test the module wiring.

- [ ] T010 [P] [US4] Create `includes/Abilities/FileManager/File_Info.php`. Extend `Ability_Definition`. Namespace `AcrossAI_Abilities_Manager\Includes\Abilities\FileManager`. Slug `file-manager/file-info`, category `acrossai-abilities-manager-file-manager`. Ability spec per contract `specs/090-file-manager-additions/contracts/file-manager-file-info.json`. Execute: `manage_options` check → ABSPATH-scope check (mirror `Read_File.php:114-123`) → `path_not_found` if `!file_exists && !is_link` → `stat($abs)` → derive `type` (`is_link` first, else `is_dir` → `"dir"`, else `"file"`) → `mode_octal` = `substr(decoct($stat['mode']), -4)` → conditional POSIX name resolution guarded by `function_exists('posix_getpwuid')` / `posix_getgrgid`. Annotations `readonly:true, idempotent:true`.

**Checkpoint**: US4 complete — file-info registers, gates, returns stat + optional POSIX names.

## Phase 4: User Story 2 — Create-directory (Priority: P1)

- [ ] T020 [P] [US2] Create `includes/Abilities/FileManager/Create_Directory.php`. Extend `Ability_Definition`. Slug `file-manager/create-directory`. Contract: `contracts/file-manager-create-directory.json`. Execute: `manage_options` → `File_Mods_Guard::blocked_response()` → resolve `$abs = base + rel` → parent-must-be-inside-ABSPATH check → if `is_dir($abs)` return `{success:true, created:false, message:"Directory already exists."}` → if `file_exists($abs)` (not dir) return `blocked_reason:'path_is_file'` → if `recursive` → `wp_mkdir_p($abs)`; else → `mkdir($abs)` and if false + parent missing return `blocked_reason:'parent_missing'`. Annotations `readonly:false, destructive:false, idempotent:true`.

**Checkpoint**: US2 complete — create-directory recursive default, idempotent.

## Phase 5: User Story 1 — Append-file (Priority: P1)

- [ ] T030 [P] [US1] Create `includes/Abilities/FileManager/Append_File.php`. Extend `Ability_Definition`. Slug `file-manager/append-file`. Contract: `contracts/file-manager-append-file.json`. Declare `PROTECTED_FILES = ['wp-config.php', '.htaccess']`. Execute: `manage_options` → `File_Mods_Guard::blocked_response()` → sanitize `path` + read `content` (string, `''` valid) + `prepend` bool → ABSPATH-scope check → protected-file refusal (basename at ABSPATH root) → `is_file($abs)` guard, else `blocked_reason:'source_not_found'` → if `prepend` → read existing + concat + `file_put_contents($abs, $joined, LOCK_EX)`; else → `file_put_contents($abs, $content, FILE_APPEND | LOCK_EX)` → report `bytes_written = strlen($content)`, `new_size = filesize($abs)`, `prepended = $prepend`. Annotations `readonly:false, destructive:false, idempotent:false`.

**Checkpoint**: US1 complete — append + prepend, protected-file refusal, file-must-exist.

## Phase 6: User Story 3 — Delete-directory (Priority: P1)

- [ ] T040 [P] [US3] Create `includes/Abilities/FileManager/Delete_Directory.php`. Extend `Ability_Definition`. Slug `file-manager/delete-directory`. Contract: `contracts/file-manager-delete-directory.json`. Declare `PROTECTED_DIRS` constant (nine entries per data-model §6). Execute: `confirm:true` gate first (mirror `Delete_File.php:104-111`) → `File_Mods_Guard::blocked_response()` → sanitize `path` + read `recursive` bool → resolve `$abs` → if `!is_dir($abs)` and `file_exists($abs)` return `blocked_reason:'not_a_directory'`; if not exists return `{success:true, entries_removed:0, message:"Directory does not exist."}` → ABSPATH-scope check → protected-directory refusal (compare `realpath($abs)` against `realpath(ABSPATH . <entry>)` for each `PROTECTED_DIRS` member) → if `!recursive` → `rmdir($abs)`, fail with `blocked_reason:'not_empty'` on failure; if `recursive` → `RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST)`, for each entry skip `isLink()`, then `unlink` file / `rmdir` dir, increment `$entries_removed`, capture first failure and return partial `success:false`. Finally `rmdir($abs)` on the top directory, increment on success. Annotations `readonly:false, destructive:true, idempotent:true`.

**Checkpoint**: US3 complete — delete-directory with recursive off default, protected-dirs guard, symlink skip.

## Phase 7: Wiring + Tests + Docs

- [ ] T050 Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php`. Under the existing `// Feature 089 — consolidated file surface` block, add a `// Feature 090 — directory + append + file-info` block with four lines: `new FileManager\Append_File();`, `new FileManager\Create_Directory();`, `new FileManager\Delete_Directory();`, `new FileManager\File_Info();`.
- [ ] T051 Create `tests/phpunit/abilities/Test_Feature_090_File_Manager_Additions.php` following the structural-inspection pattern of `Test_Feature_089_File_Consolidation.php`. Cover per-class: exists + extends `Ability_Definition`, slug + category strings present, `current_user_can('manage_options')` regex, guard invocation (`File_Mods_Guard::blocked_response()` in write-capable classes, absent in `File_Info`), key implementation calls (`FILE_APPEND | LOCK_EX`, `wp_mkdir_p`, `mkdir`, `RecursiveIteratorIterator` + `CHILD_FIRST`, `SKIP_DOTS`, `->isLink()`, `stat(`, `function_exists( 'posix_getpwuid' )`, `posix_getgrgid`, `decoct`), `PROTECTED_FILES` / `PROTECTED_DIRS` constant declarations with all expected members. Bootstrap wires all four. Set `$this->plugin_root = dirname( __DIR__, 3 );`.
- [ ] T052 Edit `phpunit.xml.dist`. Insert after the `feature-089-unit` testsuite block:
  ```xml
  <!-- Feature 090 — file-manager small pick. -->
  <testsuite name="feature-090-unit">
      <file>tests/phpunit/abilities/Test_Feature_090_File_Manager_Additions.php</file>
  </testsuite>
  ```
- [ ] T053 Edit `docs/abilities-inventory.md`: bump total `385 → 389`; bump `| file-manager/ | 18 |` row to `22`; insert four rows under the file-manager `files` sub-group, alphabetical:
  - `| file-manager/ | file-manager/append-file | file-manager | files | Append to File |`
  - `| file-manager/ | file-manager/create-directory | file-manager | files | Create Directory |`
  - `| file-manager/ | file-manager/delete-directory | file-manager | files | Delete Directory |`
  - `| file-manager/ | file-manager/file-info | file-manager | files | Get File Info |`
- [ ] T054 Edit `README.txt`. Under `= Unreleased =`, append a paragraph naming the four new slugs and their intent. Do NOT bump `Stable tag`.

## Phase 8: Gates + Delivery

- [ ] T060 Run `composer test -- --testsuite feature-090-unit` → new suite green. Then `composer test` → full suite green with delta only on new tests + assertions.
- [ ] T061 Run `vendor/bin/phpstan analyse` → level 8 clean.
- [ ] T062 Run `vendor/bin/phpcs` on the four new class files + bootstrap + test → WPCS clean.
- [ ] T063 Execute quickstart steps 1–8 against the local WordPress install.
- [ ] T064 Commit implementation on branch `090-file-manager-additions`. Push. Open PR via `gh pr create` (per memory: ship via PR, not direct merge to main). Wait for CI green.

## Dependencies

```
Setup (T001)
     │
     ├─→ US4 (T010) ─┐
     ├─→ US2 (T020) ─┤
     ├─→ US1 (T030) ─┤
     └─→ US3 (T040) ─┴─→ Wiring (T050-T054) ─→ Gates + Delivery (T060-T064)
```

The four abilities are fully independent. Wiring / tests / docs consolidate them. Gates finalise.

## Implementation Strategy

**MVP scope**: any single one of US1–US4 is a viable increment. US4 (file-info) is the smallest and safest — ship it first if breaking the feature into multiple PRs. If shipping as one PR (recommended for this feature — 4 small classes, one CI run), all four together.

## Parallel Execution

- T010, T020, T030, T040 are independent — different files. Can be authored simultaneously.
- Wiring tasks T050–T054 depend on all four class files existing.
- Gates run after wiring.
