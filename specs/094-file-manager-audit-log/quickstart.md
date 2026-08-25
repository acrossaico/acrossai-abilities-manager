# Quickstart — Feature 094 verification

Per-FR live probes for the audit + backup harness. Every recipe uses `options/update-option` for setup and MCP-adapter execute-ability calls for the mutations.

Prerequisites: plugin active, feature 094 shipped.

---

## Setup (one-time)

```bash
# Confirm the four options are seeded (from PR #144)
wp option list --search='acrossai_file_manager_%' --path=/path/to/wp | grep -E 'backup|audit'

# Prep probe workspace
wp ability call file-manager/create-directory '{"path":"wp-content/uploads/probe-094"}'
wp ability call file-manager/create-file       '{"path":"wp-content/uploads/probe-094/existing.txt","content":"seed content for backup probes"}'
```

---

## FR-002, FR-003, FR-004 · Backup writer

```bash
# Enable backups
wp option update acrossai_file_manager_backup_enabled 1 --format=json

# Edit an existing file → backup MUST appear
wp ability call file-manager/edit-file '{"path":"wp-content/uploads/probe-094/existing.txt","content":"new content"}'

# Verify: backup file exists in today's dir
ls -la wp-content/acrossai-file-manager-backups/$(date -u +%Y-%m-%d)/
# Expect: existing.txt.bak.<HHMMSS>, content == "seed content for backup probes"

# Create a NEW file → no backup (nothing to preserve)
wp ability call file-manager/create-file '{"path":"wp-content/uploads/probe-094/brand-new.txt","content":"first version"}'

# Verify: no brand-new.txt.bak.* in today's dir
ls wp-content/acrossai-file-manager-backups/$(date -u +%Y-%m-%d)/ | grep brand-new
# Expect: no output
```

---

## FR-005, FR-006, FR-007 · Log entries

```bash
# Enable log
wp option update acrossai_file_manager_audit_log_enabled 1 --format=json

# Trigger one mutation with a context
wp ability call file-manager/edit-file '{"path":"wp-content/uploads/probe-094/existing.txt","content":"third version","context":"issue #456 fix"}'

# Verify: log entry exists with the expected shape
cat wp-content/acrossai-file-manager-logs/acrossai-file-manager.log
# Expected: entry with EDIT operation, absolute path, admin user + IP,
# Size: 11 -> 13 bytes, Backup: <path>, Context: issue #456 fix
```

**Log dir + .htaccess check**:

```bash
ls -la wp-content/acrossai-file-manager-logs/
# Expect: .htaccess (0644) + acrossai-file-manager.log
cat wp-content/acrossai-file-manager-logs/.htaccess
# Expect: Deny from all
```

---

## FR-008, FR-009, FR-014 · get-changelog ability

```bash
# Tail the last 5 entries
wp ability call file-manager/get-changelog '{"lines":5}'
# Expect: {success:true, log:"...", path:"...", lines_returned:5, total_lines:N, message:"Showing last 5 lines of N total."}

# Boundary: max 500
wp ability call file-manager/get-changelog '{"lines":500}'
# Expect: capped at 500 lines_returned

# Empty log path: pretend log doesn't exist (tighten read allowlist to exclude wp-content)
wp option update acrossai_file_manager_read_allowlist '["wp-content/uploads"]' --format=json
wp ability call file-manager/get-changelog '{}'
# Expect: blocked_reason:"path_not_allowed_for_read"

# Restore unrestricted read
wp option update acrossai_file_manager_read_allowlist '[]' --format=json
```

---

## FR-010 · Cleanup

Cleanup is probabilistic (1-in-10). To force a firing without noise:

```bash
# Prime a stale backup dir
mkdir -p wp-content/acrossai-file-manager-backups/2026-08-01
touch wp-content/acrossai-file-manager-backups/2026-08-01/stale.bak.000000

# Tighten retention to 1 day
wp option update acrossai_file_manager_backup_retention_days 1 --format=json

# Run ~50 mutations to virtually guarantee cleanup fires
for i in $(seq 1 50); do
  wp ability call file-manager/edit-file '{"path":"wp-content/uploads/probe-094/existing.txt","content":"'"cleanup ping $i"'"}'
done

# Verify: stale dir is gone
ls wp-content/acrossai-file-manager-backups/2026-08-01/ 2>&1
# Expect: No such file or directory
```

Log retention trim: prime the log with an old entry manually, run 50 mutations, expect the old entry to be gone from the file.

---

## FR-011 · Third-party action hook

Drop a mu-plugin to subscribe:

```php
// wp-content/mu-plugins/acrossai-audit-log-listener.php
add_action( 'acrossai_file_manager_log_entry', function( $entry ) {
    // Send to Slack / Datadog / SIEM
    error_log( 'AUDIT: ' . wp_json_encode( $entry ) );
}, 10, 1 );
```

Then trigger any mutation and check `wp-content/debug.log` for the AUDIT line.

---

## FR-012 · Delete_File BREAKING migration

```bash
# With backup_enabled=true, delete-file writes to centralised dir
wp ability call file-manager/delete-file '{"path":"wp-content/uploads/probe-094/existing.txt","confirm":true}'
# Expect response: {"success":true,"backup_path":"...acrossai-file-manager-backups/<today>/existing.txt.bak.<HHMMSS>","backup":"...<same>"}
# The `backup` field is populated for backwards compatibility this release.

# With backup_enabled=false, no backup at all
wp option update acrossai_file_manager_backup_enabled 0 --format=json
wp ability call file-manager/create-file '{"path":"wp-content/uploads/probe-094/again.txt","content":"x"}'
wp ability call file-manager/delete-file '{"path":"wp-content/uploads/probe-094/again.txt","confirm":true}'
# Expect response: {"success":true} — no backup_path, no backup
```

Confirm no `.bak.<time>` file appears next to the source path (the inline scheme is gone).

---

## FR-015, FR-016, FR-017 · REST + panel

```bash
# GET /file-manager-settings/backup-audit should now show scaffold_only:false
curl -s http://wordpress-7-0.local/wp-json/acrossai/v1/file-manager-settings/backup-audit \
     -H "X-WP-Nonce: $(wp eval 'echo wp_create_nonce("wp_rest");')"
# Expect: {"config":{...},"scaffold_only":false,"follow_up_spec":null,"limits":{...}}

# GET /file-manager-settings/backup-audit-stats
curl -s http://wordpress-7-0.local/wp-json/acrossai/v1/file-manager-settings/backup-audit-stats \
     -H "X-WP-Nonce: $(wp eval 'echo wp_create_nonce("wp_rest");')"
# Expect: {log_path,log_total_lines,log_size_bytes,log_last_entry_timestamp,
#          backup_base_dir,backup_days_present,backup_total_size_bytes}
```

Browser check: load `admin.php?page=acrossai-settings&tab=file-manager`. Backup & Audit panel shows `notice-info` (not `notice-warning`) with the log stats line.

---

## FR-018 · Uninstall

```bash
# Set the plugin-scoped uninstall opt-in
wp option update acrossai_abilities_uninstall_delete_data 1 --format=json

# Deactivate + delete plugin (via wp-admin or wp-cli)
wp plugin deactivate acrossai-abilities-manager
wp plugin delete acrossai-abilities-manager

# Verify: backup dir + log dir are gone
ls wp-content/acrossai-file-manager-backups 2>&1
ls wp-content/acrossai-file-manager-logs 2>&1
# Expect: No such file or directory (both)
```

With `acrossai_abilities_uninstall_delete_data=0`, both dirs are preserved so admins retain the audit trail.

---

## Cleanup

```bash
wp ability call file-manager/delete-directory '{"path":"wp-content/uploads/probe-094","recursive":true,"confirm":true}'
wp option update acrossai_file_manager_backup_enabled 0 --format=json
wp option update acrossai_file_manager_audit_log_enabled 0 --format=json
wp option update acrossai_file_manager_backup_retention_days 7 --format=json
wp option update acrossai_file_manager_audit_log_retention_days 7 --format=json
```

---

## Automated verification

- `./vendor/bin/phpunit --filter Test_Feature_094_Audit_Log_And_Backups` — per-FR PHPUnit coverage
- `./vendor/bin/phpunit` — full suite, baseline preserved
- `./vendor/bin/phpcs -n includes/Abilities/Utilities/Audit_Trail.php includes/Abilities/FileManager/Get_Changelog.php includes/Abilities/FileManager/{Create,Edit,Append,Copy,Move,Delete}_File.php includes/Abilities/FileManager/{Create,Delete}_Directory.php includes/Abilities/FileManager/{Edit_Wp_Config,Clear_Debug_Log}.php includes/Abilities/Rest/File_Manager_Settings_Controller.php` — PHPCS
- `./vendor/bin/phpstan analyse --no-progress <same file list>` — PHPStan level 8
- `npm run build` — rebuild the file-manager-settings bundle
