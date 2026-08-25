# Data Model — Feature 094

Two on-disk entities (backup file, log file), one in-memory entity (parsed log entry), plus the `context` input-schema addition and the new `backup_path` response field.

---

## On-disk — Backup file

**Path**: `{WP_CONTENT_DIR}/acrossai-file-manager-backups/{YYYY-MM-DD}/{basename}.bak.{HHMMSS}[.N]`

| Field | Type | Value / Range |
|---|---|---|
| Directory | `string` | `WP_CONTENT_DIR . '/acrossai-file-manager-backups/' . gmdate('Y-m-d')` |
| Filename | `string` | `basename($absolute_path) . '.bak.' . gmdate('His')` |
| Collision suffix | `.1` – `.100` | Incremented until unique on same-second same-basename collision. Cap at `.100` returns `false`. |
| File mode | `int` | `FS_CHMOD_FILE` (typically 0644) |
| Content | `bytes` | Exact byte-for-byte copy of the source pre-image at backup-time. |

**Guardrails**:
- Base dir has `.htaccess` (`Deny from all`) written on first backup-dir creation.
- No backup for `create-file` when target didn't exist (returns `null`).
- No backup at all when `backup_enabled=false`.

---

## On-disk — Log file

**Path**: `{WP_CONTENT_DIR}/acrossai-file-manager-logs/acrossai-file-manager.log`

| Field | Type | Value |
|---|---|---|
| Directory | `string` | `WP_CONTENT_DIR . '/acrossai-file-manager-logs'` |
| Filename | `string` | `acrossai-file-manager.log` |
| File mode | `int` | `FS_CHMOD_FILE` |
| Encoding | UTF-8 text | Append-only during normal operation; whole-file rewrite during retention trim. |
| Entry separator | `\n\n` | One blank line between entries. |

**Guardrails**:
- Directory has `.htaccess` (`Deny from all`) written on first log write.
- No log entry at all when `audit_log_enabled=false`.

---

## In-memory — LogEntry

The value fired via `do_action('acrossai_file_manager_log_entry', $entry)` and also the shape `get-changelog` returns per parsed line block.

| Field | Type | Presence | Notes |
|---|---|---|---|
| `timestamp_utc` | `string` (ISO 8601-ish) | always | Format: `YYYY-MM-DD HH:MM:SS UTC` (gmdate). |
| `operation` | `string` | always | Uppercase: `CREATE`, `EDIT`, `APPEND`, `COPY`, `MOVE`, `DELETE`, `MKDIR`, `RMDIR`, `EDIT_WP_CONFIG`, `CLEAR_DEBUG_LOG`. |
| `ability_slug` | `string` | always | `file-manager/<slug>` |
| `path` | `string` | always | Absolute path — the target of the mutation. |
| `user_email` | `string` | always | Current user's email; empty string if `wp_get_current_user()` returns no user. |
| `user_id` | `int` | always | Zero if no user. |
| `ip` | `string` | always | `$_SERVER['REMOTE_ADDR']` sanitised; `unknown` if absent. |
| `size_before` | `int|null` | always | `null` for MKDIR / RMDIR / operations where no meaningful "before" size. |
| `size_after` | `int|null` | always | `null` for DELETE / RMDIR / operations where no meaningful "after". |
| `destination` | `string|null` | move-file / copy-file only | Absolute destination path. |
| `backup_path` | `string|null` | when backup fired | Absolute path; `null` for SKIPPED / FAILED. |
| `backup_status` | `string` | always | One of `written` / `skipped` / `failed` / `disabled`. |
| `backup_reason` | `string` | when status ≠ written | e.g. `target did not exist`, `filesystem full`, `feature disabled`. |
| `context` | `string` | always | Empty string if caller didn't supply. Sanitised via `sanitize_text_field` then truncated to 500 chars. |

---

## Persisted options (read-only from this feature)

Already declared in `Hardening_Settings` (PR #144). This feature does not write to them.

| Option | Type | Default | Semantic |
|---|---|---|---|
| `acrossai_file_manager_backup_enabled` | bool | `false` | When true, pre-image backups are written for the 8 mutation abilities that have a pre-image. |
| `acrossai_file_manager_backup_retention_days` | int (1–90) | `7` | Backup dirs older than this many days are cleanup targets. |
| `acrossai_file_manager_audit_log_enabled` | bool | `false` | When true, every mutation via any of the 10 affected abilities appends a log entry. |
| `acrossai_file_manager_audit_log_retention_days` | int (1–90) | `7` | Log entries older than this many days are trimmed during cleanup. |

---

## Input schema addition — `context` (all 10 mutation abilities)

```php
'context' => array(
    'type'        => 'string',
    'maxLength'   => 2000,          // enforced at adapter layer
    'description' => __( 'Optional caller-supplied explanation of why this mutation is happening. Captured in the audit log for accountability. Truncated to 500 chars in the persisted log entry.', 'acrossai-abilities-manager' ),
),
```

Not required — absent field means empty context in the log.

---

## Output schema addition — `backup_path` (10 mutation abilities)

```php
'backup_path' => array(
    'type' => array( 'string', 'null' ),
),
```

Nullable. Present only when `backup_enabled=true` AND the ability's backup path fired successfully. Callers that want to inspect the pre-image after a mutation read this field.

---

## Ability × trail-action matrix

| Ability | Backs up | Logs | Notes |
|---|---|---|---|
| `file-manager/create-file` | ⚠ only if target already exists (overwrite semantics) | ✅ CREATE | New file → no backup; overwrite (via edit-file usually) hits the edit path. |
| `file-manager/edit-file` | ✅ | ✅ EDIT | Backup written before overwrite. |
| `file-manager/append-file` | ✅ | ✅ APPEND | Backup captures pre-append content. `size_after` = existing + appended. |
| `file-manager/copy-file` | ⚠ dest overwrite only | ✅ COPY | Backs up the destination pre-image if it existed; source is not touched. `destination` field populated. |
| `file-manager/move-file` | ✅ source | ✅ MOVE | Backs up the source pre-image (which will vanish). `destination` field populated. |
| `file-manager/delete-file` | ✅ source | ✅ DELETE | Replaces inline `.bak.<time>` scheme. `size_after` = null. |
| `file-manager/create-directory` | — | ✅ MKDIR | No content to back up. `size_before` = `size_after` = null. |
| `file-manager/delete-directory` | — | ✅ RMDIR | Contents may be many; no full-tree backup (out of scope). `entries_removed` also logged as detail. |
| `file-manager/edit-wp-config` | ✅ | ✅ EDIT_WP_CONFIG | Backs up wp-config.php pre-image before rewrite. |
| `file-manager/clear-debug-log` | ✅ (of debug.log itself) | ✅ CLEAR_DEBUG_LOG | Backs up the debug log's current content before truncation. |

Total: 10 abilities log; 8 also back up (create-file backs up conditionally, mkdir/rmdir don't).
