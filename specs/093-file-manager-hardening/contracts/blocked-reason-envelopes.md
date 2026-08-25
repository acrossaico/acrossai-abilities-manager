# Contract — new `blocked_reason` envelopes

For each affected ability, this file lists the exact `output_schema.properties` additions and the runtime envelope shape returned by `Hardening_Enforcer`. The values here are the contract between the enforcer and the ability adapter's schema validator; every new field MUST be declared before enforcement lands or the ability call will fail with "invalid output".

---

## `file-manager/create-file`, `edit-file`, `append-file`, `copy-file`, `move-file`

**`output_schema.properties` additions** (already present: `success`, `path`, `message`, `blocked_reason`, `allowed_roots`, plus per-ability success keys):

```php
'extension'   => array( 'type' => 'string' ),
'basename'    => array( 'type' => 'string' ),
'directive'   => array( 'type' => 'string' ),
'input'       => array( 'type' => 'string' ),
'sanitized'   => array( 'type' => 'string' ),
'size'        => array( 'type' => 'integer' ),
'max_bytes'   => array( 'type' => 'integer' ),
'marker'      => array( 'type' => 'string' ),
```

Some abilities already declare `size` (Read_File) or `basename` — the write-side additions are a superset. `additionalProperties: false` stays.

**Runtime envelopes** (one example per `blocked_reason`):

```json
{ "success": false, "blocked_reason": "extension_blocked",
  "path": "/abs/wp-content/uploads/probe.exe",
  "message": "File extension \"exe\" is blocked by the Content Filters extension list.",
  "extension": "exe" }
```

```json
{ "success": false, "blocked_reason": "double_extension_blocked",
  "path": "/abs/wp-content/uploads/foo.php.jpg",
  "message": "File basename \"foo.php.jpg\" uses a blocked double extension.",
  "basename": "foo.php.jpg" }
```

```json
{ "success": false, "blocked_reason": "htaccess_directive_blocked",
  "path": "/abs/.htaccess",
  "message": "Refused .htaccess write: content contains the blocked directive \"php_value\".",
  "directive": "php_value" }
```

```json
{ "success": false, "blocked_reason": "filename_sanitize_failed",
  "path": "/abs/wp-content/uploads/weird name.txt",
  "message": "Filename fails the sanitize-filename check. WordPress would rename \"weird name.txt\" to \"weird-name.txt\".",
  "input": "weird name.txt",
  "sanitized": "weird-name.txt" }
```

```json
{ "success": false, "blocked_reason": "write_size_exceeded",
  "path": "/abs/wp-content/uploads/probe.txt",
  "message": "Write size 12582912 bytes exceeds the configured cap of 10485760 bytes.",
  "size": 12582912,
  "max_bytes": 10485760 }
```

```json
{ "success": false, "blocked_reason": "filename_strict_blocked",
  "path": "/abs/wp-content/uploads/c99-shell.txt",
  "message": "Filename contains the blocked marker \"c99\". Disable Strict filename filter in Settings → File Manager to allow.",
  "marker": "c99" }
```

```json
{ "success": false, "blocked_reason": "mime_type_blocked",
  "path": "/abs/wp-content/uploads/probe.xyz",
  "message": "Extension \"xyz\" is not in WordPress's allowed MIME types.",
  "extension": "xyz" }
```

---

## `file-manager/read-file`

**`output_schema.properties` additions** (already present: `success`, `content`, `path`, `size`, `binary`, `redacted`, `redaction_count`, `max_bytes`, `blocked_reason`, `allowed_roots`, `message`):

```php
'basename'         => array( 'type' => 'string' ),
'matched_pattern'  => array( 'type' => 'string' ),
```

**Runtime envelope**:

```json
{ "success": false, "blocked_reason": "sensitive_read_blocked",
  "path": "/abs/wp-content/.env",
  "message": "Reads of \".env\" are refused by the Content Filters sensitive-read denylist.",
  "basename": ".env",
  "matched_pattern": ".env" }
```

For a `*.EXT` glob match:

```json
{ "success": false, "blocked_reason": "sensitive_read_blocked",
  "path": "/abs/wp-content/uploads/backup.key",
  "message": "Reads of \"backup.key\" are refused by the Content Filters sensitive-read denylist (matched pattern *.key).",
  "basename": "backup.key",
  "matched_pattern": "*.key" }
```

---

## Ordering contract

Within each ability, the enforcer's returned envelope MUST be produced only when all the following earlier gates have passed:

1. `File_Mods_Guard::blocked_response(...)` returns `null`
2. `Wp_Filesystem_Init::blocked_response()` returns `null`
3. Ability-specific static protections (`PROTECTED_FILES`, `PROTECTED_DIRS`, confirm-required, target-exists / target-missing checks) pass
4. `Path_Allowlist_Guard::blocked_write_response()` / `blocked_read_response()` returns `null`

Any earlier-gate refusal envelope takes precedence and is returned as-is. This means `write_size_exceeded` cannot mask `path_not_allowed_for_write`, and `sensitive_read_blocked` cannot mask `path_not_allowed_for_read`.

---

## REST controller response contract updates

**GET/POST `/acrossai/v1/file-manager-settings/content-filters`**:

```diff
- "scaffold_only": true,
- "follow_up_spec": "093-file-manager-hardening",
+ "scaffold_only": false,
+ "follow_up_spec": null,
```

**GET/POST `/acrossai/v1/file-manager-settings/backup-audit`**:

No shape change; the `follow_up_spec` value is already `"094-file-manager-audit-log"` — verify it does not accidentally change to something else during the panel-text edit.
