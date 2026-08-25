# Contract — file-manager/get-changelog ability

New ability that tails the audit log. Modeled after `mcp-abilities-filesystem.php:408-476` but adapted to our namespace and existing patterns.

---

## Ability spec

- **Slug**: `file-manager/get-changelog`
- **Category**: `acrossai-abilities-manager-file-manager`
- **Permission**: `manage_options`
- **Annotations**: `readonly:true, destructive:false, idempotent:true`
- **Sub-group**: `audit`
- **Sub-group label**: `Audit`

## Input schema

```php
array(
    'type'                 => 'object',
    'properties'           => array(
        'lines' => array(
            'type'        => 'integer',
            'default'     => 100,
            'minimum'     => 1,
            'maximum'     => 500,
            'description' => __( 'Number of most-recent log entries to return. Default 100, max 500.', 'acrossai-abilities-manager' ),
        ),
    ),
    'required'             => array(),
    'additionalProperties' => false,
),
```

## Output schema

```php
array(
    'type'                 => 'object',
    'properties'           => array(
        'success'         => array( 'type' => 'boolean' ),
        'log'             => array( 'type' => 'string' ),
        'path'            => array( 'type' => 'string' ),
        'lines_returned'  => array( 'type' => 'integer' ),
        'total_lines'     => array( 'type' => 'integer' ),
        'message'         => array( 'type' => 'string' ),
        'blocked_reason'  => array( 'type' => 'string' ),
        'allowed_roots'   => array( 'type' => 'array' ),
    ),
    'required'             => array( 'success' ),
    'additionalProperties' => false,
),
```

## Return shapes

**Success — log exists and has content**:

```json
{
  "success": true,
  "log": "[2026-08-26 14:30:22 UTC] CREATE\n  Ability: file-manager/create-file\n  ...\n\n[2026-08-26 14:30:45 UTC] EDIT\n  ...",
  "path": "/var/www/html/wp-content/acrossai-file-manager-logs/acrossai-file-manager.log",
  "lines_returned": 42,
  "total_lines": 137,
  "message": "Showing last 42 lines of 137 total."
}
```

**Success — log file doesn't exist yet (fresh install)**:

```json
{
  "success": true,
  "log": "",
  "path": "/var/www/html/wp-content/acrossai-file-manager-logs/acrossai-file-manager.log",
  "lines_returned": 0,
  "total_lines": 0,
  "message": "No filesystem operations have been logged yet."
}
```

**Success — log file exists but empty**:

Same shape as the "doesn't exist yet" case but with `message: "Log file exists but contains no entries."`.

**Blocked — read allowlist refuses**:

```json
{
  "success": false,
  "blocked_reason": "path_not_allowed_for_read",
  "path": "/var/www/html/wp-content/acrossai-file-manager-logs/acrossai-file-manager.log",
  "message": "Path is outside the read allowlist. Allowed roots: wp-content/uploads.",
  "allowed_roots": ["wp-content/uploads"]
}
```

**Blocked — permission failure** (adapter-level, not reachable here):

The `manage_options` check runs at the ability adapter layer; the ability body never sees an unauthorised call.

## Execution contract

1. Compute `$log_path = Audit_Trail::log_path()`.
2. Run `Path_Allowlist_Guard::blocked_read_response($log_path)`. Non-null → return as-is.
3. If `! $fs->exists($log_path)` → return success/empty payload with message.
4. Read via `$fs->get_contents($log_path)`. On false → success/empty (log file unreadable is not a caller error).
5. Split content on `\n\n`, take last `$lines` blocks, rejoin with `\n\n`, return.
6. `total_lines` = total blocks (parse the whole file once). `lines_returned` = actual returned count (may be < requested if the log has fewer entries).

## Order relative to other file-manager reads

`get-changelog` participates in the read-side ordering set for `read-file` + `read-debug-log`:

1. `File_Mods_Guard` does not apply — reads don't mutate.
2. `Wp_Filesystem_Init::blocked_response()` — filesystem must be available.
3. `Path_Allowlist_Guard::blocked_read_response()` — respects the read allowlist. Log lives inside `wp-content/`; default `[]` (unrestricted) permits; tight allowlist may refuse.
4. **This ability does NOT run `Hardening_Enforcer::check_read()`.** The log file is not a caller-supplied path — it's a fixed path owned by the plugin. Sensitive-read denylist doesn't apply.
5. Body reads via `WP_Filesystem`.

## Not covered by this ability

- No search / filter / grep parameters. Tail-only.
- No date-range filter. Retention trim handles staleness.
- No JSON output. Callers who need structure parse per `contracts/log-entry-format.md`.
- No auth beyond `manage_options`. Non-admins cannot read the log via this ability.
