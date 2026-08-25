# Contract — Audit log entry format

The wire format for entries in `wp-content/acrossai-file-manager-logs/acrossai-file-manager.log`. Third-party parsers and the `file-manager/get-changelog` ability both depend on this being stable.

---

## Entry format

```
[YYYY-MM-DD HH:MM:SS UTC] <OPERATION>
  Ability: file-manager/<slug>
  File: <absolute path>
  User: <email> (ID:<user_id>) IP:<ip>
  Size: <before> -> <after> bytes
  Destination: <absolute path>          (only present for COPY, MOVE)
  Backup: <status>
  Context: <sanitised context or empty>
```

Entries are separated by exactly one blank line (`\n\n`). The first entry starts at file offset 0 with no leading blank line.

### Field-by-field contract

| Line | Format | Notes |
|---|---|---|
| Header | `[YYYY-MM-DD HH:MM:SS UTC] <OPERATION>` | Timestamp UTC via `gmdate('Y-m-d H:i:s')`. Operation uppercase. |
| `  Ability:` | `file-manager/<slug>` | Two-space indent. `<slug>` is the ability's namespace-local name. |
| `  File:` | `<absolute path>` | Two-space indent. Absolute filesystem path of the mutation target. |
| `  User:` | `<email> (ID:<int>) IP:<ipv4-or-ipv6-or-"unknown">` | Two-space indent. Empty email → `<> (ID:0)`. |
| `  Size:` | `<int> -> <int> bytes` OR `<n/a> -> <int> bytes` (create) OR `<int> -> <n/a> bytes` (delete/clear) | Two-space indent. `<n/a>` when the delta doesn't make sense. |
| `  Destination:` | `<absolute path>` | Two-space indent. **Omitted entirely** for operations without a destination (all except COPY / MOVE). |
| `  Backup:` | `<abs path>` OR `SKIPPED (<reason>)` OR `FAILED (<reason>)` OR `DISABLED` | Two-space indent. Exactly one line — never multi-line. |
| `  Context:` | `<text>` | Two-space indent. Empty string if caller didn't supply. Never contains newlines (sanitize_text_field strips them). Max 500 chars. |

### Operation enum

Every entry uses one of:

| Operation | Emitted by | Backup? |
|---|---|---|
| `CREATE` | `create-file` when target was new | No (nothing to back up) |
| `CREATE` | `create-file` when target existed (overwrite path) | Yes |
| `EDIT` | `edit-file` | Yes (if existed) |
| `APPEND` | `append-file` | Yes |
| `COPY` | `copy-file` | Only if destination existed (overwrite) |
| `MOVE` | `move-file` | Yes (source) |
| `DELETE` | `delete-file` | Yes (source) |
| `MKDIR` | `create-directory` | No |
| `RMDIR` | `delete-directory` | No |
| `EDIT_WP_CONFIG` | `edit-wp-config` | Yes (wp-config.php pre-image) |
| `CLEAR_DEBUG_LOG` | `clear-debug-log` | Yes (debug.log pre-image) |

---

## Example entries

**Create (new file, no backup)**:

```
[2026-08-26 14:30:22 UTC] CREATE
  Ability: file-manager/create-file
  File: /var/www/html/wp-content/uploads/foo.txt
  User: admin@example.com (ID:1) IP:203.0.113.42
  Size: n/a -> 24 bytes
  Backup: SKIPPED (target did not exist)
  Context: 
```

**Move (with backup and context)**:

```
[2026-08-26 14:30:45 UTC] MOVE
  Ability: file-manager/move-file
  File: /var/www/html/wp-content/uploads/foo.txt
  User: admin@example.com (ID:1) IP:203.0.113.42
  Size: 24 -> 24 bytes
  Destination: /var/www/html/wp-content/uploads/renamed.txt
  Backup: /var/www/html/wp-content/acrossai-file-manager-backups/2026-08-26/foo.txt.bak.143045
  Context: renaming per issue #123
```

**Delete with backup disabled**:

```
[2026-08-26 14:31:02 UTC] DELETE
  Ability: file-manager/delete-file
  File: /var/www/html/wp-content/uploads/renamed.txt
  User: admin@example.com (ID:1) IP:203.0.113.42
  Size: 24 -> n/a bytes
  Backup: DISABLED
  Context: 
```

**Failed backup, primary write still happened**:

```
[2026-08-26 14:31:15 UTC] EDIT
  Ability: file-manager/edit-file
  File: /var/www/html/wp-content/uploads/bar.txt
  User: admin@example.com (ID:1) IP:203.0.113.42
  Size: 100 -> 200 bytes
  Backup: FAILED (Could not write backup file)
  Context: 
```

---

## Parser guidance

- Split file on `\n\n` boundaries. Each block is one entry.
- Header line matches `/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} UTC)\] ([A-Z_]+)$/`.
- All other lines match `/^  ([A-Z][a-z_]*): (.*)$/`. Key is capture group 1, value is capture group 2. Note: `Destination` may be absent — check field presence, not position.
- `Size` value matches `/^(n\/a|\d+) -> (n\/a|\d+) bytes$/`. Convert non-`n/a` to int.
- `Backup` value forms: bare absolute path, `SKIPPED (...)`, `FAILED (...)`, or `DISABLED`. Extract via `/^(SKIPPED|FAILED) \((.+)\)$/` or match `DISABLED` literal or treat as path.
