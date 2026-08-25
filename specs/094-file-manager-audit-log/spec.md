# Feature Specification: File Manager Audit Log + Backup Harness

**Feature Branch**: `094-file-manager-audit-log`
**Created**: 2026-08-26
**Status**: Draft
**Input**: User description — see `docs/planning/094-file-manager-audit-log.md` for the full briefing. Summary: consume the four Backup & Audit option keys shipped as scaffold in PR #144. Every file-manager mutation writes a pre-image backup (per admin toggle) into `wp-content/acrossai-file-manager-backups/<YYYY-MM-DD>/` and appends an entry to `wp-content/acrossai-file-manager-logs/acrossai-file-manager.log` (also toggle-gated). New `file-manager/get-changelog` ability tails the log via MCP. New optional `context` input field on every write ability is captured in the log for accountability.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Admin recovers from an AI mistake using a pre-image backup (Priority: P1)

An admin has enabled the Backup toggle. An AI assistant edits `wp-config.php` (or any other file inside the write allowlist) with a bad value. Before the assistant's write lands, the plugin copies the pre-image into today's backup dir (`wp-content/acrossai-file-manager-backups/2026-08-26/wp-config.php.bak.143022`). The admin, discovering the site is broken, uses `filesystem/get-changelog` to identify the mutation, reads the referenced backup file, and restores it via `file-manager/copy-file`. Site works again.

**Why this priority**: This is the core value. Without pre-image backups, an AI-driven regression is either irreversible or requires host-level backups the admin may not have. This story unlocks safe experimentation.

**Independent Test**: Enable `backup_enabled=true`. Edit an existing file via `file-manager/edit-file`. Confirm a backup file appears at `wp-content/acrossai-file-manager-backups/<today>/<basename>.bak.<HHMMSS>`, bytes match the pre-edit content exactly.

**Acceptance Scenarios**:

1. **Given** `backup_enabled=true`, **When** `edit-file` overwrites an existing 500-byte file, **Then** a backup file appears in today's backup dir containing the exact 500-byte pre-image and the response includes `backup_path:'wp-content/acrossai-file-manager-backups/<date>/<basename>.bak.<time>'`.
2. **Given** `backup_enabled=true`, **When** `create-file` writes a NEW file (target doesn't exist), **Then** no backup is written (nothing to preserve) and the response omits `backup_path` or sets it to `null`.
3. **Given** `backup_enabled=false`, **When** any mutation runs, **Then** no backup file is written and the response omits `backup_path`.
4. **Given** `backup_enabled=true` and today's backup dir already contains a file with the same basename, **When** a second mutation on the same second targets that basename, **Then** the new backup filename uses a `.1` counter suffix (or `.2`, `.3`, ...) to avoid collision.
5. **Given** `backup_enabled=true` and the filesystem is full, **When** any mutation runs, **Then** the backup step returns false, the log entry records `Backup: FAILED (...)`, but the primary write still proceeds.

---

### User Story 2 — Admin traces who changed what and when (Priority: P1)

An admin has enabled the Audit Log toggle. An AI assistant creates, edits, moves, and deletes a series of files across an afternoon. Later the admin runs `file-manager/get-changelog` and sees one entry per mutation: timestamp, ability slug, absolute path, user email + ID + IP, size delta, backup path (or `SKIPPED`/`FAILED`), and the caller-supplied `context` string. From the log alone the admin can reconstruct the entire session without shell access.

**Why this priority**: Without a log, an admin has no way to know what an AI did after the fact. With it, they get accountability for free — even sessions the admin didn't personally supervise.

**Independent Test**: Enable `audit_log_enabled=true`. Run `create-file`, `edit-file`, `move-file`, `delete-file` in sequence. Call `file-manager/get-changelog` — expect 4 well-formed entries in reverse-chronological order (or chronological — see FR-014).

**Acceptance Scenarios**:

1. **Given** `audit_log_enabled=true`, **When** `create-file wp-content/uploads/foo.txt` succeeds, **Then** one entry appears in `wp-content/acrossai-file-manager-logs/acrossai-file-manager.log` with `CREATE` operation, the absolute path, the current user's email + ID, the request IP, `Size: 0 -> N bytes`, and `Backup: SKIPPED (target did not exist)`.
2. **Given** `audit_log_enabled=true` and a caller supplies `context:'fixing #123'`, **When** any mutation runs, **Then** the log entry's Context line contains exactly `fixing #123`.
3. **Given** `audit_log_enabled=true` and a caller supplies no `context`, **Then** the log entry's Context line is empty (`Context: `).
4. **Given** `audit_log_enabled=false`, **When** any mutation runs, **Then** no log entry is written and the log file may not even exist.
5. **Given** the log doesn't exist yet, **When** `file-manager/get-changelog` is called, **Then** the response is `{success:true, log:'', total_lines:0, message:'No filesystem operations have been logged yet.'}` — friendly empty-state, not an error.

---

### User Story 3 — Admin tightens context field discipline (Priority: P2)

An admin reviewing recent AI activity finds entries with 2000-char stack-trace-in-context blobs that clutter the log. The `context` field enforces sane limits: input schema caps at 2000 chars (validated by the ability adapter), and the log writer truncates to 500 chars in the persisted entry to keep log lines readable. Neither cap breaks any caller; oversize inputs are truncated deterministically.

**Why this priority**: Cleanliness — logs stay grep-able and small. Less critical than P1 stories but cheap to add.

**Independent Test**: Send a `context` value with 3000 chars. Expect the input schema to refuse at 2000 (before the write even runs); send exactly 2000; expect the log entry's Context line to contain the first 500 chars only.

**Acceptance Scenarios**:

1. **Given** a caller sends `context` with 3000 chars, **When** the ability's input schema validates, **Then** the adapter rejects the call with a schema error naming the max length.
2. **Given** a caller sends `context` with exactly 2000 chars and `audit_log_enabled=true`, **When** the log entry is written, **Then** the Context line contains the first 500 chars of the input (verbatim, then truncated).
3. **Given** `context` contains HTML tags or control characters, **When** the log entry is written, **Then** the persisted value is passed through `sanitize_text_field` (tags stripped, control chars removed) before storage.

---

### User Story 4 — Admin sees storage stay bounded via retention windows (Priority: P2)

An admin sets `backup_retention_days=3` and `audit_log_retention_days=7`. As mutations continue, the plugin probabilistically triggers cleanup (1-in-10 per write). Backup dirs older than 3 days get deleted; log entries older than 7 days get trimmed from the log file. Storage stays bounded regardless of mutation rate.

**Why this priority**: Prevents the backup dir + log file from growing unboundedly. Auto-cleanup is essential for long-lived sites.

**Independent Test**: Prime the backup dir with a dummy `YYYY-MM-DD` folder 30 days old. Prime the log file with entries dated 30 days ago. Enable the toggles and run 30 mutations. Assert (with ≥90% probability across three separate 30-mutation runs) the 30-day-old backup dir is gone AND the 30-day-old log entries are gone.

**Acceptance Scenarios**:

1. **Given** `backup_retention_days=3` and a backup dir dated 10 days ago exists, **When** the cleanup fires (probabilistic; may take multiple writes), **Then** that dir is deleted along with all its files.
2. **Given** `audit_log_retention_days=7` and the log contains a 30-day-old entry, **When** the retention trim fires, **Then** the log file is rewritten without that entry.
3. **Given** `backup_retention_days=7` and today's backup dir exists, **When** cleanup fires, **Then** today's dir is preserved.

---

### User Story 5 — Admin sees the panel drop its scaffold banner (Priority: P3)

After this feature ships, the Backup & Audit panel on the File Manager settings tab drops its yellow "Scaffold only — feature 094" `notice-warning`. A small `notice-info` under the toggles shows the log path, total-lines count, and total backup dir size — populated via a new `/backup-audit-stats` REST endpoint. Admins get at-a-glance visibility into how much the harness has captured.

**Why this priority**: Signals enforcement is live and gives admins visibility. Non-blocking UX polish.

**Independent Test**: Load `admin.php?page=acrossai-settings&tab=file-manager`. The Backup & Audit panel shows `notice-info` (not `notice-warning`) and includes log/backup-dir stats.

**Acceptance Scenarios**:

1. **Given** the feature has shipped, **When** an admin loads the tab, **Then** the Backup & Audit panel shows a `notice-info` with the log-file path and current line count.
2. **Given** a fresh install with zero mutations, **When** the panel loads, **Then** the info line shows "No entries yet" gracefully instead of `0 lines`.

---

### Edge Cases

- **Target does not exist when backup runs.** `create-file` on a new path has no pre-image. Backup writer returns `null` (not an error) and the log entry records `Backup: SKIPPED (target did not exist)`.
- **Filesystem write failure during backup.** Backup returns `false`, log records `Backup: FAILED (<reason>)`, primary write STILL PROCEEDS. Backup is best-effort by design — a full-disk situation MUST NOT block a legitimate delete or edit.
- **Log file write failure.** Silent — never surfaces to the caller. Documented in the feature spec as a known limitation. On a filesystem that can no longer be written, the log fails silently and mutations continue.
- **Concurrent log writes.** Two mutations in flight may result in one entry being lost (read-then-concat-then-write is not atomic). Matches `Append_File` precedent. Documented, not fixed.
- **`Delete_File` inline `.bak.<time>` behaviour.** REPLACED. When `backup_enabled=true` the backup goes to the centralised dir; when `false` no backup is written at all. BREAKING for callers that read `response.backup` — direct them to the new `response.backup_path` (present on all ten abilities when a backup was written).
- **Backup dir `.htaccess`.** On first backup-dir creation, a `.htaccess` with `Deny from all` is written into the base dir so the pre-image files aren't listable via HTTP.
- **Log dir `.htaccess`.** Similarly, `wp-content/acrossai-file-manager-logs/.htaccess` with `Deny from all` protects the log file from direct HTTP access.
- **Backup dir base created lazily.** `wp_mkdir_p` runs on first write; the base dir doesn't exist on a fresh install with `backup_enabled=false`.
- **Cleanup during long-running write.** Cleanup fires at the END of the log-write path (after the primary write + log entry). A concurrent write during cleanup may see the just-created dir deleted a moment later — cleanup respects the date parse, so today's dir is always safe from today's cleanup.
- **`context` field on chunked uploads.** The upload-zip-backup chunked protocol only accepts `context` on the FINAL call (the completion). Mid-chunk `context` values are ignored (nothing to log yet).
- **`clear-debug-log` backup.** Not written — spec Q6 recommendation: respect the admin's opt-out uniformly. Admins who need to preserve debug logs before clearing enable the whole backup subsystem OR copy the file manually first.
- **`edit-wp-config` backup.** Same — respects the toggle.
- **Uninstall behaviour.** When the plugin-scoped uninstall opt-in is on (`acrossai_abilities_uninstall_delete_data=1`), the backup dir + log dir are deleted on plugin uninstall. When off, both are preserved so admins keep the audit trail.
- **Log file grows past 10 MB.** The panel's stats info line shows a soft warning. No automatic rotation (retention trim handles growth over time).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST introduce a new utility `Audit_Trail` in `includes/Abilities/Utilities/` with public static methods: `write_backup(string $absolute_path, array $opts): string|false|null` (`null` when target doesn't exist, `string` when backup written, `false` on failure), `write_log(string $operation, string $absolute_path, array $details): void`, `maybe_cleanup(): void`, `backup_base_dir(): string`, `log_path(): string`.
- **FR-002**: System MUST write pre-image backups to `wp-content/acrossai-file-manager-backups/<YYYY-MM-DD>/<basename>.bak.<HHMMSS>[.N]` when `backup_enabled=true` AND the target file exists AND the ability is one of `create-file` (with `overwrite:true` on existing file), `edit-file`, `append-file`, `copy-file` (source is the backup subject only when dest overwrite), `move-file` (source is backup subject), `delete-file`. Directory created lazily via `wp_mkdir_p` on first use. Filename collisions within one second increment `.1`, `.2`, ... up to `.100` before giving up.
- **FR-003**: System MUST use `WP_Filesystem::copy(source, dest, true, FS_CHMOD_FILE)` for all backup copies. Native `copy()` or `file_put_contents()` are forbidden (per Feature 091 migration).
- **FR-004**: System MUST NOT block the primary write when backup fails. `Audit_Trail::write_backup()` returns `false` on I/O failure; the calling ability captures the return value, passes it to the log writer as `SKIPPED` / `FAILED`, and proceeds with the primary write.
- **FR-005**: System MUST append an audit log entry to `wp-content/acrossai-file-manager-logs/acrossai-file-manager.log` when `audit_log_enabled=true` for every successful mutation via any of the ten affected abilities. Entry format:

  ```
  [YYYY-MM-DD HH:MM:SS UTC] <OPERATION>
    Ability: file-manager/<slug>
    File: <abs path>
    User: <email> (ID:<user_id>) IP:<ip>
    Size: <before> -> <after> bytes
    Destination: <abs path>       (only for move-file / copy-file)
    Backup: <abs path | SKIPPED (<reason>) | FAILED (<reason>)>
    Context: <sanitised context or empty>
  ```

  Entries separated by exactly one blank line. Timestamp in UTC via `gmdate`.
- **FR-006**: System MUST protect both storage locations with `.htaccess` files (`Deny from all`) written on first creation. Backup dir base: `wp-content/acrossai-file-manager-backups/.htaccess`. Log dir: `wp-content/acrossai-file-manager-logs/.htaccess`. Both files are 0644.
- **FR-007**: System MUST introduce a new optional input field `context:string` on all ten mutation abilities. Input schema declares `type:string, maxLength:2000` (adapter refuses > 2000). The log writer sanitises via `sanitize_text_field` then truncates to 500 chars before storage.
- **FR-008**: System MUST introduce a new ability `file-manager/get-changelog` with input `{lines:integer, default:100, min:1, max:500}` and output `{success:bool, log:string, path:string, lines_returned:int, total_lines:int, message:string, blocked_reason:string, allowed_roots:array}`. Permission callback: `manage_options`. Reads via `WP_Filesystem::get_contents`, returns the last N lines joined by `\n`. Empty log returns success with informative message. Missing file also returns success (first-install state).
- **FR-009**: System MUST run `get-changelog` through the existing `Path_Allowlist_Guard::blocked_read_response()` on the log file path. Default read allowlist (`[]`, unrestricted) permits it; an admin who tightens the read allowlist to exclude `wp-content` gets `path_not_allowed_for_read`.
- **FR-010**: System MUST trigger cleanup probabilistically via `wp_rand(1, 10) === 1` at the END of every successful log-write. Cleanup work: delete backup dirs whose date parses as older than `backup_retention_days`; trim log entries older than `audit_log_retention_days` from the log file.
- **FR-011**: System MUST fire the WordPress action `do_action('acrossai_file_manager_log_entry', array $entry)` after every successful log write. `$entry` is the parsed-entry associative array (`operation`, `ability_slug`, `path`, `user_email`, `user_id`, `ip`, `size_before`, `size_after`, `destination`, `backup_path`, `context`, `timestamp_utc`). Third-party integrations subscribe here for Slack/Datadog/SIEM ingestion.
- **FR-012**: System MUST replace `Delete_File.php`'s inline `<real>.bak.<time>` writing (currently at line 166) with the centralised `Audit_Trail::write_backup()` call. When `backup_enabled=false` no backup is written at all. Response envelope's `backup` field is deprecated in favour of `backup_path`; both are populated during a transition release with `backup` set to `backup_path` for backwards compat.
- **FR-013**: System MUST add a `backup_path:string` (nullable) property to the `output_schema.properties` of all ten affected abilities so the ability adapter's schema validator accepts responses that include the backup path.
- **FR-014**: `get-changelog` MUST return entries in the order they appear in the log file (chronological — newest at bottom). This matches the append-only nature of the log.
- **FR-015**: System MUST flip the `scaffold_only` field in the REST `/file-manager-settings/backup-audit` GET response from `true` to `false` and set `follow_up_spec` to `null`.
- **FR-016**: System MUST introduce a new REST endpoint `/file-manager-settings/backup-audit-stats` (GET only, `manage_options`) returning `{log_path, log_total_lines, log_size_bytes, log_last_entry_timestamp, backup_base_dir, backup_days_present, backup_total_size_bytes}`. Values reflect current disk state at read time.
- **FR-017**: System MUST update `BackupAuditPanel.jsx` to drop the yellow `notice-warning` scaffold banner and replace it with a `notice-info` line populated from the new stats endpoint showing log path + line count + backup dir size.
- **FR-018**: System MUST honour the plugin-scoped uninstall opt-in (`acrossai_abilities_uninstall_delete_data`). When ON, plugin uninstall deletes the backup dir + log dir recursively. When OFF, both are preserved.
- **FR-019**: System MUST leave all existing tests green AND add coverage for `Audit_Trail` unit behaviour, per-ability integration (backup + log per each of the ten abilities), and the new `get-changelog` ability (success, empty, disabled, permission).

### Key Entities

- **Audit_Trail** (new utility): Owns backup + log + cleanup + stats. Single class in `includes/Abilities/Utilities/`.
- **Backup file**: `<basename>.bak.<HHMMSS>[.N]` in `wp-content/acrossai-file-manager-backups/<YYYY-MM-DD>/`. Byte-for-byte copy of the pre-image.
- **Log entry**: Multi-line block in `wp-content/acrossai-file-manager-logs/acrossai-file-manager.log`. Human-readable text (not JSON). Separated by one blank line.
- **file-manager/get-changelog** (new ability): Reads the log file's tail. Mirrors the reference plugin's `filesystem/get-changelog` adapted to our namespace.
- **BackupAuditPanel** (existing, PR #144): Drops scaffold banner, gains stats info line.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Enabling `backup_enabled=true` and running one mutation via each of the six file-content abilities produces exactly six backup files in `wp-content/acrossai-file-manager-backups/<today>/`, each byte-for-byte identical to the pre-image.
- **SC-002**: Enabling `audit_log_enabled=true` and running one mutation via each of the ten mutation abilities produces exactly ten log entries in the log file, each with the FR-005 format.
- **SC-003**: Disabling both toggles produces zero backup files and zero log entries — no I/O overhead when features are off.
- **SC-004**: `file-manager/get-changelog {lines:5}` returns the last 5 entries; on an empty log it returns `success:true, log:'', total_lines:0` with a friendly message.
- **SC-005**: Retention: with `backup_retention_days=1`, a hand-primed 3-day-old backup dir is deleted after ≤50 mutations (99% probability under 1-in-10 cleanup trigger).
- **SC-006**: `context:'test-value'` supplied via any of the ten abilities appears verbatim in the corresponding log entry's Context line. `context` with 3000 chars is refused at the input-schema layer before the ability runs.
- **SC-007**: PHPUnit test count grows by ≥60 tests. Full suite (1890 baseline) remains green.
- **SC-008**: PHPCS + PHPStan clean on every changed file. No new global warnings.
- **SC-009**: `Delete_File` response `backup_path` field is present when `backup_enabled=true` and absent (or null) when `backup_enabled=false`. The legacy `backup` field is populated during the transition release with the same value as `backup_path`.
- **SC-010**: Third-party subscriber to `do_action('acrossai_file_manager_log_entry', $entry)` receives the parsed entry array after every log write.

## Assumptions

- The four Backup & Audit option keys were seeded by PR #144's activator and their getters/setters exist on `Hardening_Settings`. This feature reads them; no changes to persistence.
- `WP_Filesystem` is initialised via the existing `Wp_Filesystem_Init::get()` helper in every affected ability. No new filesystem plumbing needed.
- The plugin's uninstall path exists and honours the plugin-scoped opt-in. This feature adds two more paths to the delete-on-uninstall list.
- The reference plugin's semantics for backup + log + cleanup + get-changelog are the target; adapt naming but keep shape.
- No changes to `Hardening_Enforcer` (feature 093). Backup + log run AFTER the enforcer approves the write; they never influence the decision.
- No new JS libraries or Composer packages. `wp_mkdir_p`, `sanitize_text_field`, `wp_get_current_user`, `gmdate`, `wp_rand`, `WP_Filesystem` are all WordPress core.
- The action hook `acrossai_file_manager_log_entry` is opt-in for subscribers — no default subscribers ship with this plugin.
