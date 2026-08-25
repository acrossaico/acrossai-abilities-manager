# Planning: File Manager Audit Log + Backup Harness (Feature 094)

Consume the four Backup & Audit option keys shipped as UI scaffold in PR #144
and enforced-toggle-only in PR #146. Every file-manager mutation gets a
pre-image backup (optional, per admin toggle) and appends an entry to a
centralised audit log (also toggle-gated). A new `filesystem/get-changelog`
ability tails that log via MCP so the admin — or the next LLM session — can
answer *"what did we change and when?"* without shell access.

Also introduces a `context` input field on every write ability so callers
can explain *why* a mutation happened; the value is captured in the log
entry alongside user, IP, timestamp, size delta, and backup path.

Reference implementation: `wp-content/plugins/mcp-abilities-filesystem/mcp-abilities-filesystem.php`
lines 54–188 (backup dir, backup writer, cleanup, log writer) and lines
405–476 (`filesystem/get-changelog` ability). This spec ports the pattern
adapted to our naming conventions and existing `Hardening_Settings` +
`Hardening_Enforcer` structure.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "094-file-manager-audit-log"

# 2. Specify
/speckit.specify "Consume the four Backup & Audit option keys already seeded
in wp_options and read via Hardening_Settings::get_backup_audit(). Every
file-manager mutation writes a pre-image backup (when backup_enabled) into
wp-content/acrossai-file-manager-backups/<YYYY-MM-DD>/ and appends one log
entry (when audit_log_enabled) to wp-content/acrossai-file-manager.log.

Six abilities in the file-content-write set gain both backup + log:
  file-manager/create-file, edit-file, append-file, copy-file, move-file,
  delete-file.

Four additional abilities gain log-only (no source content to back up):
  file-manager/create-directory, delete-directory, edit-wp-config,
  clear-debug-log.

Every one of those ten abilities also gains an optional \"context\" string
input field. When provided, it is captured verbatim in the log entry's
Context line. Callers use it to record why a change was made (e.g. \"fixing
issue #123\", \"pre-deploy backup before schema migration\", \"nightly
housekeeping\"). Sanitised via sanitize_text_field. Max 500 chars — longer
inputs truncated. Optional — omitting leaves the log entry's Context line
empty.

BACKUP behaviour:
- Pre-image only. Backup runs BEFORE the ability's WP_Filesystem write.
  If the target does not exist yet (create-file, mkdir semantics), no
  backup is written (nothing to preserve).
- Storage layout: wp-content/acrossai-file-manager-backups/<YYYY-MM-DD>/<basename>.bak.<HHMMSS>[.N]
  Directory created lazily on first write per day via wp_mkdir_p.
  Second-collision counter (.N) starts at .1 and increments until unique.
- Copy is via WP_Filesystem::copy(source, dest, true /*overwrite*/, FS_CHMOD_FILE).
- Success returns the ABSPATH-relative backup path; failure returns false
  and MUST NOT block the primary write (backup is best-effort by design —
  a full-disk situation shouldn't block a legitimate delete or edit).
  When backup returns false the log entry records \"Backup: FAILED (<reason>)\".
- Deletes the inline <path>.bak.<time> behaviour Delete_File.php currently
  writes at line 166. That inline scheme is replaced by the centralised
  dir. When backup_enabled is FALSE, Delete_File writes NO backup at all
  (this is a BREAKING behaviour change for Delete_File — callers who
  relied on the inline .bak return value get null when backup_enabled=false).

CLEANUP behaviour:
- Trigger: 1-in-10 chance on every write that emits a log entry
  (wp_rand(1, 10) === 1). Cheap amortisation, no separate cron. Matches
  reference plugin.
- Scan: wp-content/acrossai-file-manager-backups/, list immediate children
  matching the pattern YYYY-MM-DD, parse the date, delete the whole dir
  (and its files) when older than backup_retention_days.
- Failures during cleanup are logged (single log entry per cleanup run)
  but never surface to the caller.

LOG behaviour:
- File location: wp-content/acrossai-file-manager.log (world-writable
  directory but the log file itself is 0644 owned by the web server).
- Format (one entry per event; entries separated by a blank line):

    [YYYY-MM-DD HH:MM:SS UTC] <OPERATION>
      Ability: file-manager/<slug>
      File: <abs path>
      User: <email> (ID:<user_id>) IP:<ip>
      Size: <before> -> <after> bytes
      Destination: <abs path>         (only present for move-file / copy-file)
      Backup: <abs backup path or FAILED reason or SKIPPED>
      Context: <caller-supplied text, empty if not provided>

  Timestamp is UTC (gmdate) for consistency across timezones. Operation
  is uppercase (CREATE, EDIT, APPEND, COPY, MOVE, DELETE, MKDIR, RMDIR,
  EDIT_WP_CONFIG, CLEAR_DEBUG_LOG).
- Write via WP_Filesystem::put_contents(existing + entry, FS_CHMOD_FILE) —
  read-then-concat-then-write, same pattern as Append_File.php. Not
  atomic; two concurrent writes may lose one entry. Acceptable trade-off
  per Append_File precedent — MCP-driven ability calls are serialised
  per client and log-write is small.
- No log rotation via size. Cleanup section handles time-based trim below.

LOG RETENTION (audit_log_retention_days):
- Triggered by the same 1-in-10 wp_rand as backup cleanup.
- Read the log, split by the blank-line delimiter, drop entries whose
  timestamp is older than audit_log_retention_days, rewrite the file.
- If the file doesn't exist or is empty, no-op.

NEW ABILITY: filesystem/get-changelog
- Slug: file-manager/get-changelog (matches existing file-manager namespace).
- Input schema: { \"lines\": integer, default 100, min 1, max 500 }.
- Output schema: { success:bool, log:string, path:string, lines_returned:int,
  total_lines:int, message:string, blocked_reason:string }.
- Permission: manage_options (same as other file-manager abilities).
- Behaviour: read the last N lines of the log file via WP_Filesystem;
  return them joined by \\n plus metadata. If the file doesn't exist,
  return { success:true, log:'', total_lines:0, message:'No filesystem
  operations have been logged yet.' } — not an error, expected state on
  first install.
- ALSO honours the write allowlist for the log file itself: since the log
  lives at wp-content/acrossai-file-manager.log, reads always succeed on
  the default read allowlist ([] = unrestricted). If an admin tightens
  the read allowlist to exclude wp-content, get-changelog returns
  path_not_allowed_for_read like any other read.

CONTEXT INPUT FIELD:
- Added to the input_schema of all ten mutation abilities as an optional
  \"context\" string. Not required (no impact on existing callers).
- Sanitised via sanitize_text_field. Truncated at 500 chars.
- Only used by the log emitter — if audit_log_enabled is false, the field
  is accepted but discarded.

SIDE EFFECTS:
- Delete_File.php loses its inline .bak.<time> scheme (replaced by
  centralised backup when backup_enabled OR no backup when disabled).
  BREAKING for callers reading the response's 'backup' field.
- All ten ability output_schemas gain 'backup_path:string' when a backup
  was written. Callers can inspect it if they want to inspect the
  pre-image afterwards.

PANEL:
- Backup & Audit panel drops its 'notice-warning' scaffold banner. Adds
  a small 'notice-info' with the log path and total-lines summary
  populated via a new GET /file-manager-settings/backup-audit-stats
  endpoint that returns { log_path, log_total_lines, log_size_bytes,
  backup_dir, backup_days_present, backup_total_size }. Small — for
  admin insight only; not consumed by the enforcer.
- REST /file-manager-settings/backup-audit endpoint flips
  scaffold_only:true -> false and follow_up_spec becomes null.

NON-GOALS:
- No signed / tamper-proof log — this is an operational trail, not
  cryptographic audit. Admins who need that use a dedicated SIEM.
- No structured JSON log format — human-readable text mirrors reference
  plugin. Callers who want to parse it use the get-changelog ability +
  their own parsing.
- No log ingestion to external systems (Slack, Datadog, etc.). Third
  parties can subscribe via the new 'acrossai_file_manager_log_entry'
  action hook fired after every entry write.
- No per-user log filtering, log search, or log admin UI beyond the
  stats summary. Callers use get-changelog + grep-style filtering.
- No changes to zip abilities. Their backups already live in
  wp-content/uploads/acrossai-backups/ and follow a different lifecycle.
- No enforcement of the Content Filters options (feature 093 handled).
  No changes to the Path_Allowlist_Guard, Secret_Redactor, or
  Hardening_Enforcer runtime behaviour.

SUCCESS CRITERIA:
- Every mutation via any of the ten affected abilities produces one log
  entry when audit_log_enabled=true; zero when audit_log_enabled=false.
- Every file-content-mutation via the six file-write abilities produces
  a backup file in wp-content/acrossai-file-manager-backups/<YYYY-MM-DD>/
  when backup_enabled=true AND target exists; zero backups otherwise.
- Backup + log failures NEVER block the primary write. Silent failure
  MUST be captured in the log entry's Backup: line (\"FAILED (...)\").
- Cleanup fires probabilistically; after 100 writes (~10 runs) any
  YYYY-MM-DD directory older than backup_retention_days is gone.
- get-changelog ability returns the last N lines and total count; empty
  log returns success with informative message.
- Panel and REST scaffold_only flip to false; stats endpoint returns
  accurate counts.
- Full PHPUnit suite passes. New test file covers each of the ten
  abilities' backup + log paths, cleanup logic, get-changelog outputs,
  and the log's format contract."

# 3. Plan / Tasks / Implement
/speckit.plan
/speckit.tasks
/speckit.implement
```

---

## Background — what is already done; do NOT redo it

| # | Fact | How to verify |
|---|------|---------------|
| B-1 | Four backup/audit option keys are seeded by `AcrossAI_Activator::seed_file_manager_settings()`: `acrossai_file_manager_audit_log_enabled` (bool, default false), `acrossai_file_manager_audit_log_retention_days` (int, default 7), `acrossai_file_manager_backup_enabled` (bool, default false), `acrossai_file_manager_backup_retention_days` (int, default 7). | `grep -c 'audit_log\|backup_enabled\|backup_retention' includes/AcrossAI_Activator.php` → 4 |
| B-2 | `Hardening_Settings::get_backup_audit()` returns a typed snapshot with all four fields clamped/coerced. `set_backup_audit()` persists the same shape via `update_option`. | `grep get_backup_audit\|set_backup_audit includes/Abilities/Utilities/Hardening_Settings.php` |
| B-3 | REST endpoint `/acrossai/v1/file-manager-settings/backup-audit` (GET + POST) is live. GET returns `{config, scaffold_only:true, follow_up_spec:'094-file-manager-audit-log', limits:{retention_days_min:1, retention_days_max:90}}`. | `grep '/backup-audit' includes/Abilities/Rest/File_Manager_Settings_Controller.php` |
| B-4 | React panel `BackupAuditPanel.jsx` renders alongside the other four panels; it wears a yellow `notice-warning` scaffold banner referencing feature 094. Fields: `backup_enabled` (checkbox), `backup_retention_days` (number, disabled when backup_enabled=false), `audit_log_enabled` (checkbox), `audit_log_retention_days` (number, disabled when audit_log_enabled=false). | `ls src/js/file-manager-settings/components/BackupAuditPanel.jsx` |
| B-5 | `Hardening_Enforcer` (from feature 093) is the runtime consumer for Content Filters. Feature 094 introduces a SEPARATE utility (`Backup_Log_Writer` or similar) — do not muddle the two responsibilities. Enforcer decides IF a write proceeds; the audit writer records WHAT proceeded. | `ls includes/Abilities/Utilities/Hardening_Enforcer.php` |
| B-6 | `Delete_File.php:166` currently writes a `<real>.bak.<time>` inline in the source directory, returned as the `backup` field of the response envelope. This feature REPLACES that with the centralised backup dir (when backup_enabled) or nothing (when disabled). Callers reading `data.backup` in delete-file responses experience a BREAKING change and must switch to `data.backup_path` — document in CHANGELOG. | `grep -n 'bak\.' includes/Abilities/FileManager/Delete_File.php` |
| B-7 | Reference plugin source-of-truth: `/Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/plugins/mcp-abilities-filesystem/mcp-abilities-filesystem.php` — read lines 54–188 (backup + log + cleanup) and 405–476 (get-changelog) before implementing. Adapt naming (`mcp-backups/` → `acrossai-file-manager-backups/`; `mcp-filesystem.log` → `acrossai-file-manager.log`) but keep the semantic shape. | `head -200 /Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/plugins/mcp-abilities-filesystem/mcp-abilities-filesystem.php` |
| B-8 | Feature 093's `Hardening_Enforcer` runs BEFORE the primary write. Feature 094's backup + log runs BETWEEN the enforcer and the primary write (backup) or AFTER the primary write (log). Order: `File_Mods_Guard → Path_Allowlist_Guard → Hardening_Enforcer → Backup_Log_Writer::backup() → primary WP_Filesystem write → Backup_Log_Writer::log()`. | Read `includes/Abilities/FileManager/Create_File.php` execute() body |

---

## Constraints

### Filesystem
- **WP_Filesystem for everything.** Backup copy via `$fs->copy()`, log append via `$fs->get_contents() + concat + $fs->put_contents()`, cleanup via `$fs->rmdir()` + iterated `wp_delete_file()`. Native `file_put_contents` / `unlink` are forbidden (feature 091 migrated the plugin off native ops).
- **Backup dir path.** Absolute path is `WP_CONTENT_DIR . '/acrossai-file-manager-backups/' . gmdate('Y-m-d')`. `wp_mkdir_p` on first use per day. `.htaccess` with `deny from all` written into the base dir on first creation (like `wp-content/uploads/acrossai-backups/` does today) so the backup files aren't browsable via HTTP.
- **Log file path.** `WP_CONTENT_DIR . '/acrossai-file-manager.log'`. Deliberately NOT under `uploads/` because it's plugin operational data, not user content. `.htaccess` with `deny from all` written to `wp-content/` is NOT feasible (would collide with WP's own rewrite rules) — instead, on log creation the plugin writes a sibling `.htaccess.acrossai` file that admins can hand-inspect. Better: place the log inside a dedicated dir `wp-content/acrossai-file-manager-logs/` with its own `.htaccess`. **Decision to make in /clarify.**
- **File modes.** All files created via `FS_CHMOD_FILE` (0644 by default). Directories via `FS_CHMOD_DIR` (0755).

### Concurrency
- **Log append is not atomic.** Two concurrent writes may lose one entry. Same trade-off Append_File.php accepts. Document in CHANGELOG.
- **Backup filename collisions within a second.** Use a counter suffix (`.1`, `.2`, ...) up to N attempts (say 100) before giving up. Reference plugin uses the same approach.

### Performance
- **Cleanup is amortised.** `wp_rand(1, 10) === 1` per write triggers cleanup. On a busy site this runs frequently enough; on a quiet site it may not fire for days — that's fine, worst case is a slightly-larger backup dir.
- **Log-retention trim reads the whole log.** For sites with high mutation rates the log may grow to MB scale. Truncating requires reading + parsing + rewriting. The 1-in-10 amortisation keeps this cost bounded per-request but total cost scales with log size. **Document a "log size warning" if the log exceeds 10 MB** — surface in the panel's stats summary.
- **Backup file size cap.** If the source file being backed up is > `write_max_bytes` (feature 093's cap), the backup step should skip and log `Backup: SKIPPED (source_too_large)`. Prevents a runaway file from filling the backup dir.

### Security
- **Log entries capture user email + IP.** GDPR-relevant. Document in privacy notes: "the audit log stores actor identity so an admin can trace who caused a change; opt-out is per-site via `audit_log_enabled=false`; log is deleted on plugin uninstall when the plugin-scoped uninstall opt-in is on."
- **Context field is caller-controlled.** Sanitise via `sanitize_text_field` (strips tags, control chars, invalid UTF-8) before persisting. Never eval or interpret.
- **`.htaccess` on backup dir.** MUST be written on first backup-dir creation to prevent browsers listing the pre-image files.
- **Log file itself is never returned to unauthenticated callers.** `get-changelog` requires `manage_options`. Same for the stats REST endpoint.

### Backwards compatibility
- **Delete_File response `backup` field.** Currently `<real>.bak.<time>`. After 094: absent when `backup_enabled=false`; `<centralised path>` when true. BREAKING for callers reading `data.backup` — direct them to `data.backup_path` (new field, present on all ten abilities when a backup was written).
- **`context` input field.** Additive. Existing calls without `context` continue to work; the field defaults to empty.
- **Existing `.bak.<time>` files** already on disk are untouched. This feature does NOT sweep them; admins can manually delete or leave for their normal cleanup process.

---

## Non-goals

- **No structured (JSON) log format.** Human-readable text mirrors the reference plugin and is easier to grep / tail / audit visually. JSON export is a follow-up.
- **No cryptographic audit signing.** Ops trail, not tamper-proof evidence.
- **No log ingestion to Slack / Datadog / SIEM.** Third parties subscribe via the `acrossai_file_manager_log_entry` action hook.
- **No admin log-viewer UI.** `get-changelog` ability + admin's own shell tools cover the read path. A panel-embedded viewer is a follow-up.
- **No search / filter / pagination in `get-changelog`.** Returns tail-N-lines only. Complex queries → parse the file yourself.
- **No per-ability toggle** (e.g. "log Create_File but not Read_File"). Single audit_log_enabled toggle governs all ten abilities uniformly.
- **No log-format migration.** If the format changes in a future release, old entries remain and new entries follow the new format. Parsers must handle both.
- **No zip-ability integration.** `create-zip-backup` / `extract-zip-backup` / `delete-zip-backup` continue to use `wp-content/uploads/acrossai-backups/`. They are backups themselves; nested-backup makes no sense.
- **No user opt-out at the caller level.** Admin controls audit_log_enabled site-wide; individual callers cannot request "don't log this one call". Callers who need that must ask the admin to disable the log.
- **No modifications to `Hardening_Enforcer`.** Backup + log run AFTER the enforcer approves the write; they don't influence enforcement decisions.

---

## Test approach (for the /speckit.plan phase to expand)

Three test tiers:

1. **PHPUnit — `Backup_Log_Writer` (or equivalent) unit tests.** Direct calls into the new utility's public entrypoints. Verify:
   - Backup writer creates the day-dir on first call
   - Backup filename uses `<basename>.bak.<HHMMSS>` and collides gracefully with `.1`, `.2` counters
   - Backup returns false on filesystem failure and the caller MUST NOT throw
   - Log writer emits a well-formed entry, appends without corrupting existing content, silently no-ops when disabled
   - Cleanup deletes dirs older than N days, leaves newer dirs alone
   - Log-retention trim drops old entries by parsing the blank-line-separated format
   - `.htaccess` is written to the backup base dir on first use
2. **PHPUnit — per-ability integration.** For each of the ten affected abilities:
   - With backup_enabled=true, a mutation writes a backup file at the expected path (existence + non-empty + matches source pre-image byte-for-byte)
   - With backup_enabled=false, no backup file is written
   - With audit_log_enabled=true, one log entry appears with the expected operation/file/user/size/backup fields
   - The `context` field, when supplied, appears verbatim in the log entry's Context line (after sanitisation)
   - Missing `context` produces a log entry with an empty Context line
   - Deleting a file when backup_enabled=false leaves NO backup (the current inline .bak scheme is truly gone)
3. **PHPUnit — `filesystem/get-changelog` ability.** Positive (returns the last N lines), boundary (min=1, max=500 clamping), empty-log path (returns friendly message not error), permission (non-admin gets refused). Also a source-inspection test asserting the ability is registered under the file-manager namespace and appears in `docs/abilities-inventory.md`.
4. **Live MCP probes.** After enabling both toggles, run one mutation through each of the ten abilities, confirm a backup + log entry appear on disk, then flip toggles off and confirm no backup / no log. Also confirm the panel banner drops its scaffold state.

---

## Recommended sequence (for /speckit.tasks to expand)

1. **Foundational.** Add the `Backup_Log_Writer` (or `Audit_Trail`) utility skeleton with public entrypoints: `write_backup(string $absolute_path, array $opts): string|false`, `write_log(string $operation, string $absolute_path, array $details): void`, `maybe_cleanup(): void`, `default_backup_dir(): string`, `default_log_path(): string`. Empty bodies, direct callable. Also add `filesystem/get-changelog` ability class with no-op body.
2. **Call-site wiring.** Add the `Audit_Trail::write_backup()` + `write_log()` calls to each of the ten mutation abilities in the correct position (backup before primary write, log after). Read `context` from `$input` and pass through to `write_log`. Add `context:string` to each ability's `input_schema`.
3. **Delete_File migration.** Replace the inline `.bak.<time>` scheme with the new writer. Backwards-compatibility note in the CHANGELOG.
4. **Backup subsystem.** Implement `write_backup` + directory management + `.htaccess` guard + collision counter + failure-tolerant path.
5. **Log subsystem.** Implement `write_log` + entry format + WP_Filesystem-safe append.
6. **Cleanup subsystem.** Implement `maybe_cleanup` (1-in-10 gate) + backup-dir date-parse + delete + log-retention trim.
7. **`filesystem/get-changelog` ability.** Fill in body, wire to inventory.
8. **REST + panel updates.** Flip `scaffold_only`, add `/backup-audit-stats` endpoint, add `notice-info` line to `BackupAuditPanel.jsx`.
9. **CHANGELOG entry.** Prominent BREAKING note about `Delete_File.backup` → `.backup_path`.
10. **Tests.** Unit + integration + get-changelog + live probes. Target: 60+ new PHPUnit cases.
11. **Uninstall integration.** When the plugin-scoped uninstall opt-in is on, delete the backup dir + log file on `plugin_uninstall`. Do NOT delete when the opt-in is off — the log is an audit trail admins may want to preserve.

---

## Open questions

Answer these before running `/speckit.specify` (or handle in `/speckit.clarify` after):

1. **Log file location.** `wp-content/acrossai-file-manager.log` (bare file — simpler) vs `wp-content/acrossai-file-manager-logs/log` (dedicated dir with own `.htaccess` — safer). **Recommend: dedicated dir**, matches how the backup dir works and centralises `.htaccess` protection.
2. **Cleanup trigger.** 1-in-10 amortisation per write (cheap, no cron dependency) vs WP-Cron event `acrossai_file_manager_daily_cleanup` (predictable, adds cron surface). **Recommend: keep the 1-in-10 pattern** for parity with the reference plugin; move to WP-Cron only if the log-retention trim becomes a hotspot.
3. **`context` field limit.** 500 chars is arbitrary. Log lines with a 2000-char context become hard to read. Truncation at 500 is a reasonable ceiling; also enforce hard upper bound at 2000 in the input schema. **Recommend: schema max 2000 + truncate-store 500**, so callers see the truncation happen deterministically.
4. **Log rotation.** No rotation vs size-triggered rotation (e.g. > 10 MB spins the current log to `.1` and starts fresh). Retention trim already caps age; rotation caps size. **Recommend: no rotation for v1**, add if log growth becomes a real problem.
5. **`edit-wp-config` backup.** wp-config.php is small and critical. Should the backup fire even when `backup_enabled=false`, as a safety net? **Recommend: no — respect the admin's opt-out uniformly**. Admins who want a wp-config safety net enable the whole backup subsystem.
6. **`clear-debug-log` backup.** Same question — the log being cleared may contain valuable diagnostics. **Recommend: no** — same reason. Admins who need to preserve diagnostics enable backups.
7. **Backup dir + log dir at plugin uninstall.** Delete unconditionally? Delete only when the uninstall opt-in is on? **Recommend: obey the uninstall opt-in** — matches existing behaviour for other plugin data. Callers who want to preserve the audit trail after uninstall can leave the opt-in off, then move the files elsewhere themselves.
8. **`acrossai_file_manager_log_entry` action hook.** Fire after every entry write, passing the parsed entry as an assoc array. Docs for third-party integrations. **Recommend: yes** — cheap, opt-in, decouples log ingestion from this plugin.
