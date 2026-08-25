---
description: "Task list for feature 094 — File Manager Audit Log + Backup Harness"
---

# Tasks: File Manager Audit Log + Backup Harness

**Input**: Design documents from `/specs/094-file-manager-audit-log/`
**Prerequisites**: plan.md, spec.md (5 user stories: P1×2, P2×2, P3), research.md, data-model.md, contracts/, quickstart.md

**Tests**: PHPUnit coverage IS requested (spec FR-019 mandates + Constitution §VII).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel — different files, no incomplete-task dependency
- **[Story]**: US1..US5 map to spec user stories

---

## Phase 1: Setup

- [ ] T001 Confirm branch `094-file-manager-audit-log` + working tree clean apart from spec-kit docs
- [ ] T002 Reserve CHANGELOG stub in `README.txt` Unreleased section (bullets filled in T045)

---

## Phase 2: Foundational (blocking prerequisites)

- [ ] T003 Create `includes/Abilities/Utilities/Audit_Trail.php` with skeleton: public static methods `write_backup(string $absolute_path, array $opts = []): string|false|null`, `write_log(string $operation, string $absolute_path, array $details = []): void`, `maybe_cleanup(): void`, `stats(): array`, `backup_base_dir(): string`, `log_path(): string`. All return sensible no-op values (`null`, `void`, empty array) so consumers can wire calls before implementations land.
- [ ] T004 [P] Add `Audit_Trail::write_backup()` + `write_log()` call sites to `includes/Abilities/FileManager/Create_File.php`. Enforcer stays; new calls run AFTER the primary write for the log, BEFORE for the backup. Pass `mode:'create'` in opts.
- [ ] T005 [P] Same in `Edit_File.php`. `mode:'edit'`.
- [ ] T006 [P] Same in `Append_File.php`. `mode:'append'`, pass `existing_size` from `$fs->size($real)`.
- [ ] T007 [P] Same in `Copy_File.php`. `mode:'copy'`, backup target is `$dest_abs` (destination pre-image if it existed). Log entry includes `destination`.
- [ ] T008 [P] Same in `Move_File.php`. `mode:'move'`, backup target is `$src_real` (source, about to be moved away). Log entry includes `destination`.
- [ ] T009 [P] Replace inline `.bak.<time>` in `Delete_File.php:166` with `Audit_Trail::write_backup($real)`. Preserve `data.backup` field populated with the new path (transition compat). Add `data.backup_path` as canonical field.
- [ ] T010 [P] Add `Audit_Trail::write_log()` call to `Create_Directory.php`. Operation `MKDIR`. No backup call.
- [ ] T011 [P] Same to `Delete_Directory.php`. Operation `RMDIR`. No backup call. Include `entries_removed` in log details.
- [ ] T012 [P] Add both calls to `Edit_Wp_Config.php`. Backup subject: `wp-config.php` pre-image. Operation `EDIT_WP_CONFIG`.
- [ ] T013 [P] Add both calls to `Clear_Debug_Log.php`. Backup subject: `debug.log` pre-image. Operation `CLEAR_DEBUG_LOG`.
- [ ] T014 [P] Add `context:string, maxLength:2000` optional field to `input_schema.properties` in each of the 10 ability files (`Create_File`, `Edit_File`, `Append_File`, `Copy_File`, `Move_File`, `Delete_File`, `Create_Directory`, `Delete_Directory`, `Edit_Wp_Config`, `Clear_Debug_Log`). Extract in `execute()` and forward to `Audit_Trail::write_log($op, $path, ['context' => $input['context'] ?? ''])`.
- [ ] T015 [P] Add `backup_path:['string','null']` to `output_schema.properties` in the same 10 files.
- [ ] T016 Create `includes/Abilities/FileManager/Get_Changelog.php` — skeleton ability class implementing the contract in `contracts/get-changelog-ability.md`. Empty execute body returning `success:true, log:'', total_lines:0, message:'stub'`.
- [ ] T017 Register `Get_Changelog` in `includes/Abilities/FileManager/Category_Registrar.php` alongside the existing file-manager abilities so it appears in `wp_register_ability` discovery.
- [ ] T018 Create `tests/phpunit/abilities/Test_Feature_094_Audit_Log_And_Backups.php` — skeleton class extending `WP_UnitTestCase`, `setUp`/`tearDown` reset every `acrossai_file_manager_*` option + wipe the test backup dir + test log dir. Empty test methods for each FR.
- [ ] T019 Add the new test file to `phpunit.xml.dist` under `<testsuite name="abilities-unit">`.
- [ ] T020 Regenerate Composer autoload map (`composer dump-autoload -o`) so `Audit_Trail` and `Get_Changelog` are resolvable.

**Checkpoint**: Every mutation ability now calls the trail utility (no-op stubs — no behaviour change), the new ability is registered, tests are wired.

---

## Phase 3: User Story 1 — Pre-image backup on mutation (Priority: P1) 🎯 MVP

**Goal**: Fill in `Audit_Trail::write_backup()` so the 8 backup-capable abilities produce real pre-image backups.

### Tests for US1

- [ ] T021 [US1] PHPUnit: `Audit_Trail::write_backup()` unit — target-exists produces file with byte-identical content; target-missing returns null; filesystem-failure returns false; second-collision-suffix produces `.1`, `.2`; `.htaccess` guard written on first backup-dir creation.
- [ ] T022 [US1] PHPUnit: per-ability integration for each of 8 backup abilities — mutation produces backup file, response includes `backup_path`.

### Implementation for US1

- [ ] T023 [US1] Fill `Audit_Trail::write_backup()`: read `backup_enabled` snapshot; if false return null (no backup at all); compute today's dir (`gmdate('Y-m-d')`); `wp_mkdir_p` if missing + write `.htaccess` on first-create; build filename via `basename($path) . '.bak.' . gmdate('His')`; collision-loop with `.N` up to 100; call `$fs->copy(source, dest, true, FS_CHMOD_FILE)`. Return the destination path on success, `false` on I/O failure, `null` when target didn't exist (create-file with new target).
- [ ] T024 [US1] Fill `Audit_Trail::backup_base_dir()`: return `WP_CONTENT_DIR . '/acrossai-file-manager-backups'`.
- [ ] T025 [US1] Ensure every ability's response includes `backup_path:string|null` — populated from the `write_backup()` return.

**Checkpoint**: US1 shipped. `backup_enabled=true` produces real backups; MVP done.

---

## Phase 4: User Story 2 — Audit log of every mutation (Priority: P1)

**Goal**: Fill in `Audit_Trail::write_log()` + `Get_Changelog` ability body so admins can read the trail.

### Tests for US2

- [ ] T026 [US2] PHPUnit: `Audit_Trail::write_log()` unit — writes well-formed entry per contracts/log-entry-format.md; `.htaccess` guard on log dir; disabled toggle → no file written; append preserves prior entries; fires `acrossai_file_manager_log_entry` action.
- [ ] T027 [US2] PHPUnit: per-ability integration for each of 10 abilities — mutation produces one log entry with correct operation, path, user, size, backup, context.
- [ ] T028 [US2] PHPUnit: `Get_Changelog` unit — tail-N-lines happy path; empty-log friendly message; missing-file friendly message; boundary min=1, max=500 clamping; blocked by read allowlist when tightened.

### Implementation for US2

- [ ] T029 [US2] Fill `Audit_Trail::log_path()`: return `WP_CONTENT_DIR . '/acrossai-file-manager-logs/acrossai-file-manager.log'`.
- [ ] T030 [US2] Fill `Audit_Trail::write_log()`: read `audit_log_enabled` snapshot; if false return; ensure log dir exists + `.htaccess` guard; build entry per format contract (UTC timestamp, operation, ability, file, user email + ID + IP, size before/after, destination if present, backup path/status, context sanitised + truncated to 500); read existing log, concat, write via `$fs->put_contents($log, $existing . $entry . "\n", FS_CHMOD_FILE)`; fire `do_action('acrossai_file_manager_log_entry', $parsed_entry)`; call `maybe_cleanup()`.
- [ ] T031 [US2] Fill `Get_Changelog::execute()`: honour read allowlist (`Path_Allowlist_Guard::blocked_read_response($log_path)`); handle missing-file / empty-file paths; read + split on `\n\n` + tail last N + rejoin; return per contract shape.

**Checkpoint**: US2 shipped. Both P1 stories done.

---

## Phase 5: User Story 3 — Context field discipline (Priority: P2)

**Goal**: Enforce the 2000-char schema max + 500-char store truncation for the `context` input field. Adapter and log writer both honour their limits.

### Tests for US3

- [ ] T032 [US3] PHPUnit: adapter rejects `context` > 2000 chars (schema violation); adapter accepts exactly 2000; log writer stores first 500 verbatim; log writer strips HTML tags + control chars via sanitize_text_field.

### Implementation for US3

- [ ] T033 [US3] Verify `maxLength:2000` present in every ability's `context` input schema (from T014). Adjust the WP-less bootstrap stub for `sanitize_text_field` in `tests/bootstrap.php` if needed so it produces WP-equivalent behaviour for the tests.
- [ ] T034 [US3] Inside `Audit_Trail::write_log()`, sanitise + truncate: `substr( sanitize_text_field( $context ), 0, 500 )`.

**Checkpoint**: US3 shipped. Context field bounded and clean.

---

## Phase 6: User Story 4 — Retention (Priority: P2)

**Goal**: Fill in `Audit_Trail::maybe_cleanup()` + amortised trigger. Backup dirs older than `backup_retention_days` deleted; log entries older than `audit_log_retention_days` trimmed.

### Tests for US4

- [ ] T035 [US4] PHPUnit: backup-cleanup deletes old date-dirs, preserves fresh ones, handles non-YYYY-MM-DD entries gracefully.
- [ ] T036 [US4] PHPUnit: log-retention trim rewrites file without expired entries, preserves recent, handles malformed entries safely.
- [ ] T037 [US4] PHPUnit: `maybe_cleanup()` no-ops silently when both toggles are false.

### Implementation for US4

- [ ] T038 [US4] Fill `Audit_Trail::maybe_cleanup()`: read snapshot; scan `backup_base_dir()` for `YYYY-MM-DD` immediate children; parse date via `strtotime`; if older than `backup_retention_days` → delete each file via `wp_delete_file` then `$fs->rmdir($dir)`. Then: read log file, split on `\n\n`, parse each entry's timestamp header, drop entries older than `audit_log_retention_days`, rewrite the file.
- [ ] T039 [US4] Wire the 1-in-10 trigger inside `write_log()`: `if ( wp_rand( 1, 10 ) === 1 ) { self::maybe_cleanup(); }`.

**Checkpoint**: US4 shipped. Storage stays bounded.

---

## Phase 7: User Story 5 — Panel banner + stats (Priority: P3)

### Tests for US5

- [ ] T040 [US5] PHPUnit: REST GET `/backup-audit` returns `scaffold_only:false, follow_up_spec:null`. Structural check via file_get_contents.
- [ ] T041 [US5] PHPUnit: `/backup-audit-stats` endpoint registered; returns the six expected fields.
- [ ] T042 [US5] PHPUnit: `BackupAuditPanel.jsx` no longer references "Scaffold only"; contains `notice-info` string.

### Implementation for US5

- [ ] T043 [US5] In `File_Manager_Settings_Controller.php`, flip `scaffold_only:true → false` and `follow_up_spec` → `null` in `get_backup_audit()` and `save_backup_audit()`.
- [ ] T044 [US5] Add new REST route `/file-manager-settings/backup-audit-stats` (GET only) delegating to `Audit_Trail::stats()`. Fill `Audit_Trail::stats()` to compute log path + total lines + size + last-entry timestamp + backup base dir + days-present count + total bytes.
- [ ] T045 [US5] Update `src/js/file-manager-settings/components/BackupAuditPanel.jsx`: drop the yellow `notice-warning` scaffold block; add a small `notice-info` reading "Backup dir: {backup_base_dir} ({backup_total_size_bytes} bytes across {backup_days_present} days). Log: {log_path} ({log_total_lines} entries)." Load stats via the new endpoint alongside the existing config load.
- [ ] T046 [US5] `npm run build` to rebuild the bundle.

**Checkpoint**: UI signals enforcement is live.

---

## Phase 8: Polish

- [ ] T047 [P] Extend `uninstall.php` to delete `wp-content/acrossai-file-manager-backups/` and `wp-content/acrossai-file-manager-logs/` recursively when `acrossai_abilities_uninstall_delete_data=1`.
- [ ] T048 [P] Add row for `file-manager/get-changelog` to `docs/abilities-inventory.md`.
- [ ] T049 [P] Fill CHANGELOG entry in `README.txt` Unreleased section: backup harness, log format, get-changelog ability, context field, `.htaccess` guards, retention behaviour, BREAKING note for Delete_File `backup` → `backup_path`, action hook, uninstall behaviour.
- [ ] T050 Full PHPUnit run — expect 1890+ passing (baseline preserved).
- [ ] T051 [P] PHPCS clean on every changed PHP file (list per quickstart.md).
- [ ] T052 [P] PHPStan level 8 clean on every changed PHP file.
- [ ] T053 Live MCP probes per quickstart.md — every FR verified.
- [ ] T054 Commit + push + PR against main.

---

## Dependencies

- Setup (Phase 1) → Foundational (Phase 2) → all user stories can run in parallel.
- US1 (backup) + US2 (log) are independent of each other. US2 depends on `Audit_Trail::write_log()` which is only wired for real content in US2 — until then it no-ops.
- US3 (context) depends on the input-schema wiring from T014 (Foundational), not on any specific US.
- US4 (retention) depends on US1 + US2 both having landed so there's actual data to clean.
- US5 (panel + stats) depends on `Audit_Trail::stats()` implementation. Otherwise independent.
- Polish (Phase 8) depends on all US complete.

Parallel opportunities: T004–T015 (call sites + schemas in different files); T021–T028 (test writes in different sections of the same file — sequential); T047–T052 (uninstall + docs + PHPCS + PHPStan in parallel).

---

## Implementation Strategy

MVP scope: Phase 1 + 2 + Phase 3 (US1 alone). That gives working pre-image backups. Ship if scope pressure emerges.

Incremental delivery:
1. Setup + Foundational → wiring in place (no behaviour change).
2. US1 → backups live → MVP.
3. US2 → log + get-changelog live.
4. US3 → context field bounded.
5. US4 → retention active.
6. US5 → panel banner drops.
7. Polish → PR ready.
