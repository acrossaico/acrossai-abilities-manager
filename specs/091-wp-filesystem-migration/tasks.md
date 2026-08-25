---
description: "Task list for feature 091 — WP_Filesystem Migration"
---

# Tasks: WP_Filesystem Migration

**Input**: Design documents from `specs/091-wp-filesystem-migration/`
**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅, data-model.md ✅, contracts/ ✅, quickstart.md ✅
**Tests**: INCLUDED per Constitution §VII.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup

- [ ] T001 Baseline gates on branch tip before diff. Record: PHPUnit test count, warning count, PHPStan clean, PHPCS clean.

## Phase 2: Foundational — shared init helper

- [ ] T010 Create `includes/Abilities/Utilities/Wp_Filesystem_Init.php` per contract `contracts/wp-filesystem-init.json`. Two static methods: `get()` returns `WP_Filesystem_Base|WP_Error`, `blocked_response()` returns ability envelope or null. Copy the init idiom from `mcp-abilities-filesystem.php:73-78` (`function_exists('WP_Filesystem')` → `require_once ABSPATH . 'wp-admin/includes/file.php'` → `WP_Filesystem()` → check `$wp_filesystem instanceof WP_Filesystem_Base`).

**Checkpoint**: helper exists and can be imported.

## Phase 3: US1 — migrate the 14 straight-mapping ability classes

Each task in this phase: import `Wp_Filesystem_Init`, call `blocked_response()` after `File_Mods_Guard::blocked_response()` in write-capable abilities (or standalone in read-only ones), obtain `$fs = Wp_Filesystem_Init::get()`, replace every native call per the mapping table in `research.md §1`, remove PHPCS suppressions, widen `output_schema` `blocked_reason` enum to include `filesystem_unavailable`.

- [ ] T020 [P] [US1] Migrate `includes/Abilities/FileManager/Read_File.php`.
- [ ] T021 [P] [US1] Migrate `includes/Abilities/FileManager/Create_File.php`.
- [ ] T022 [P] [US1] Migrate `includes/Abilities/FileManager/Edit_File.php`.
- [ ] T023 [P] [US1] Migrate `includes/Abilities/FileManager/Delete_File.php` (retain `opcache_invalidate` — not filesystem I/O).
- [ ] T024 [P] [US1] Migrate `includes/Abilities/FileManager/Copy_File.php`.
- [ ] T025 [P] [US1] Migrate `includes/Abilities/FileManager/Move_File.php` (`rename` → `$fs->move`).
- [ ] T026 [P] [US1] Migrate `includes/Abilities/FileManager/Append_File.php` (append becomes read+concat+put; document non-atomic in description).
- [ ] T027 [P] [US1] Migrate `includes/Abilities/FileManager/Create_Directory.php` (`wp_mkdir_p` stays; `mkdir` → `$fs->mkdir`).
- [ ] T028 [P] [US1] Migrate `includes/Abilities/FileManager/Download_Zip_Backup.php` (metadata-only ability; small).
- [ ] T029 [P] [US1] Migrate `includes/Abilities/FileManager/List_Zip_Backups.php` (delegates to a helper class — verify the helper's filesystem calls, migrate them together).
- [ ] T030 [P] [US1] Migrate `includes/Abilities/FileManager/Delete_Zip_Backup.php`.
- [ ] T031 [P] [US1] Migrate `includes/Abilities/FileManager/Get_Wp_Config_Constant.php` — verify no filesystem I/O; add code comment stating so; no functional change.

## Phase 4: US2 — migrate the 4 wp-config + debug-log abilities

- [ ] T040 [P] [US2] Migrate `includes/Abilities/FileManager/Read_Wp_Config.php`. Preserve secret redaction after read.
- [ ] T041 [P] [US2] Migrate `includes/Abilities/FileManager/Edit_Wp_Config.php`. Preserve protected-constant allowlist before write.
- [ ] T042 [P] [US2] Migrate `includes/Abilities/FileManager/Read_Debug_Log.php`. Preserve `lines` tail-truncation after read.
- [ ] T043 [P] [US2] Migrate `includes/Abilities/FileManager/Clear_Debug_Log.php`. Preserve truncate (not delete) semantics.

## Phase 5: US1 recursive-walk refactors

- [ ] T050 [US1] Migrate `includes/Abilities/FileManager/List_Directory.php`. Replace `RecursiveDirectoryIterator` + `RecursiveIteratorIterator` with a private recursive method driven by `$fs->dirlist($path)`. Preserve `max_depth`, `max_entries`, `truncated`, symlink skip (`$info['type'] === 'l'` continue).
- [ ] T051 [US1] Migrate `includes/Abilities/FileManager/Delete_Directory.php`. Replace `RecursiveIteratorIterator CHILD_FIRST` with a private recursive method that walks `$fs->dirlist()` and deletes bottom-up. Preserve `entries_removed`, `confirm:true`, `PROTECTED_DIRS`, symlink-unlink-but-don't-follow.

## Phase 6: US4 — file-info schema shrink

- [ ] T060 [US4] Migrate `includes/Abilities/FileManager/File_Info.php`. Compose stat from `$fs->size/mtime/owner/group/getchmod/is_link/is_readable/is_writable/is_dir/exists`. **Drop `ctime` and `atime`** from the response and from `output_schema`. Keep `posix_getpwuid` / `posix_getgrgid` conditional POSIX name-resolution as-is (behind `function_exists`).

## Phase 7: US3 — mark deferred zip abilities

- [ ] T070 [P] [US3] Add `// TODO(feature-092): migrate to WP_Filesystem` header comment to `includes/Abilities/FileManager/Create_Zip_Backup.php`.
- [ ] T071 [P] [US3] Same for `includes/Abilities/FileManager/Extract_Zip_Backup.php`.
- [ ] T072 [P] [US3] Same for `includes/Abilities/FileManager/Upload_Zip_Backup.php`.

## Phase 8: Bootstrap + tests + docs

- [ ] T080 Update `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php`. No wiring change — just a comment near the FileManager block noting that filesystem I/O now routes through WP_Filesystem (with the three deferred exceptions listed).
- [ ] T081 Update `tests/phpunit/abilities/Test_Feature_089_File_Consolidation.php`. For each migrated class assertion, change native-call assertions to `$wp_filesystem->` assertions. Keep guard-invocation assertions (they test the guards, not the I/O). Add assertions that `Wp_Filesystem_Init::blocked_response` is called.
- [ ] T082 Update `tests/phpunit/abilities/Test_Feature_090_File_Manager_Additions.php`. Same treatment as T081.
- [ ] T083 Create `tests/phpunit/abilities/Test_Feature_091_Wp_Filesystem_Migration.php`. Cross-cutting assertions per spec FR-016 and FR-017: every migrated file contains `Wp_Filesystem_Init::blocked_response`; every migrated file contains at least one `$wp_filesystem->` or `$fs->` invocation; every migrated file contains ZERO native filesystem functions (regex negative assertions on `file_put_contents(`, `file_get_contents(`, `\bunlink(`, `\brmdir(`, `\bmkdir(`, `\bcopy(`, `\brename(`, `\bis_file(`, `\bis_dir(`, `\bis_readable(`, `\bis_writable(`, `\bfile_exists(`, `\bfilesize(`, `\bfilemtime(`) — carefully excluding matches inside `wp_delete_file` (kept-out helper) or PHPDoc; the three deferred files retain native calls. Also assert `File_Info.php` does NOT contain `'ctime'` or `'atime'`.
- [ ] T084 Add `feature-091-unit` testsuite entry to `phpunit.xml.dist` after the `feature-090-unit` block.
- [ ] T085 Update `docs/abilities-inventory.md` — add a footer note (below the ability tables) documenting that all `file-manager/*` abilities route through `WP_Filesystem`, with the three deferred exceptions.
- [ ] T086 Update `README.txt`. Append a new paragraph under the existing `= Unreleased =` block naming (a) the transport-portability improvement, (b) the `file-info` schema shrink as BREAKING, (c) the three deferred zip abilities and where feature 092 will pick them up.

## Phase 9: Gates + delivery

- [ ] T090 Run `composer test -- --testsuite feature-091-unit` → new suite green.
- [ ] T091 Run `composer test` → full suite green; delta only on new suite + updated 089/090 assertion strings; no new warnings.
- [ ] T092 Run `vendor/bin/phpstan analyse --memory-limit=1G` → level 8 clean.
- [ ] T093 Run `vendor/bin/phpcs` on `includes/Abilities/FileManager/` and `includes/Abilities/Utilities/` and `tests/phpunit/abilities/Test_Feature_091_*` → WPCS clean. Also verify `grep -rn "phpcs:ignore WordPress.WP.AlternativeFunctions" includes/Abilities/FileManager/ | grep -v -E '(Create_Zip|Extract_Zip|Upload_Zip)_Backup\.php'` returns NO hits.
- [ ] T094 Execute quickstart steps 1–7 (baseline `direct` transport) against the local WordPress install. Every ability responds as specified.
- [ ] T095 Verify `wp plugin is-active acrossai-buddyboss` still reports active and its ability count is unchanged (SC-007).
- [ ] T096 Commit implementation on branch `091-wp-filesystem-migration`. Push. Open PR via `gh pr create`. Wait for CI green.

## Dependencies

```
Setup (T001)
  └─→ Foundational (T010) — Wp_Filesystem_Init helper
        ├─→ US1 straight (T020-T031) — 12 migrations
        ├─→ US2 wp-config/debug (T040-T043) — 4 migrations
        ├─→ US1 walks (T050-T051) — 2 migrations
        ├─→ US4 file-info (T060) — 1 migration + schema shrink
        └─→ US3 zip deferrals (T070-T072) — 3 comment-only
              └─→ Bootstrap/tests/docs (T080-T086)
                    └─→ Gates + delivery (T090-T096)
```

## Parallel Execution

All 19 ability migrations (T020–T060) are independent — different files, no shared state. Can be authored in any order or in parallel. The Utilities helper (T010) is the only prerequisite.

## Implementation Strategy

Ship as a single PR. The module needs to be coherent — no half-migrated state on `main`. If the diff feels too big for review, split by phase: PR 1 = helper + 12 straight migrations; PR 2 = wp-config/debug + walks + file-info + tests + docs. But default is one PR.
