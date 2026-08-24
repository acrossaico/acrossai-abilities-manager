# Data Model: WP_Filesystem Migration (091)

No persistent data model. This feature is a transport-layer refactor; it changes how filesystem calls execute, not what they represent. The two shape-relevant deltas below are the only externally-visible changes.

## 1. New `blocked_reason` value on every migrated ability

Every migrated ability's `output_schema` gains `filesystem_unavailable` to its `blocked_reason` enum. Response shape when init fails:

```json
{
  "success": false,
  "blocked_reason": "filesystem_unavailable",
  "message": "WordPress could not initialise its filesystem transport. …"
}
```

## 2. `file-manager/file-info` schema shrink (BREAKING)

Fields removed: `ctime`, `atime`.

Full post-migration schema:

| Field | Type | Notes |
|---|---|---|
| `success` | boolean | |
| `path` | string | ABSPATH-relative (or absolute for wp-config-above-root). |
| `type` | enum `file` \| `dir` \| `link` | |
| `size` | integer | bytes; 0 for directories. |
| `mtime` | integer | Unix epoch seconds. |
| `mode_octal` | string | 4-char octal, e.g. `"0644"`. |
| `owner_uid` | integer | Numeric UID (may be numeric-string coerced on FTP transports). |
| `owner_name` | string \| absent | Present when POSIX name resolution succeeds OR when `$fs->owner()` returns a non-numeric string. |
| `group_gid` | integer | |
| `group_name` | string \| absent | |
| `readable` | boolean | `$fs->is_readable()`. |
| `writable` | boolean | `$fs->is_writable()`. |
| `is_link` | boolean | `$fs->is_link()`. |
| `message` | string | Human-readable status. |
| `blocked_reason` | string \| absent | `invalid_path`, `path_not_found`, `filesystem_unavailable`. |

**Removed vs pre-migration**: `ctime` (integer), `atime` (integer).

## 3. `Wp_Filesystem_Init` utility contract (in-memory)

Not persisted. Contract:

| Method | Signature | Behaviour |
|---|---|---|
| `Wp_Filesystem_Init::get()` | `(): \WP_Filesystem_Base\|\WP_Error` | Bootstraps `$wp_filesystem` if not already present. Returns the object on success; returns `WP_Error(code='filesystem_unavailable')` on failure. Idempotent within a request — re-uses `$wp_filesystem` if already initialised. |
| `Wp_Filesystem_Init::blocked_response()` | `(): array\|null` | Returns the ability response envelope when init fails, or `null` when the caller may proceed. Envelope shape: `{success:false, blocked_reason:'filesystem_unavailable', message:<string>}`. |

## 4. Ability-registration record (in-memory)

No changes. Every ability slug remains registered exactly as before this feature. No slugs added or removed.

## 5. Validation rules

- Every input schema stays the same. `additionalProperties: false` still enforced.
- Every output schema widens its `blocked_reason` enum to include `filesystem_unavailable`. This is a schema *extension*, not a shrink, so clients that whitelist enum values need to add the new value; clients that just read the string are unaffected.
- `file-manager/file-info` `output_schema` drops `ctime` and `atime`. Existing schemas that permit unknown top-level fields under `additionalProperties: false` will need the removals reflected — the schema itself will reject the old fields.
