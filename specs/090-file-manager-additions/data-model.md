# Data Model: File-Manager Additions (090)

No persistent data model. This feature touches the WordPress ability registry (in-memory) and the filesystem under ABSPATH. The "entities" below describe the runtime shapes exchanged over the abilities API.

## 1. Append/prepend envelope

Response of `file-manager/append-file`.

| Field | Type | Description |
|---|---|---|
| `success` | boolean | `true` on completion. `false` on refusal or error. |
| `path` | string | Echoed ABSPATH-relative path. |
| `bytes_written` | integer | Bytes added by this call. Equals `strlen($content)` on success. |
| `new_size` | integer | File size after the write. |
| `prepended` | boolean | `true` iff the caller passed `prepend:true` and the write succeeded. |
| `message` | string | Human-readable status. |
| `blocked_reason` | string \| absent | Present only on refusal. Values: `invalid_path`, `source_not_found`, `protected_write`, `file_mods_disabled`. |

## 2. Create-directory envelope

Response of `file-manager/create-directory`.

| Field | Type | Description |
|---|---|---|
| `success` | boolean | `true` on completion or when directory already existed. |
| `path` | string | Echoed ABSPATH-relative path. |
| `created` | boolean | `true` when a new directory was created; `false` when it already existed. |
| `message` | string | Human-readable status. |
| `blocked_reason` | string \| absent | Values: `invalid_path`, `path_is_file`, `parent_missing`, `file_mods_disabled`. |

## 3. Delete-directory envelope

Response of `file-manager/delete-directory`.

| Field | Type | Description |
|---|---|---|
| `success` | boolean | `true` on completion or when directory did not exist. |
| `path` | string | Echoed ABSPATH-relative path. |
| `entries_removed` | integer | Total count of files + directories removed. `0` for a missing target. `1` for an empty-directory removal. |
| `message` | string | Human-readable status. |
| `blocked_reason` | string \| absent | Values: `confirmation_required`, `invalid_path`, `not_a_directory`, `protected_directory`, `not_empty`, `file_mods_disabled`. |

## 4. File-info envelope

Response of `file-manager/file-info`.

| Field | Type | Description |
|---|---|---|
| `success` | boolean | `true` on a resolvable path. `false` on missing path or invalid input. |
| `path` | string | Echoed ABSPATH-relative path. |
| `type` | enum `file` \| `dir` \| `link` | Kind of filesystem entry. `link` only when the target is a symlink AND its target does not resolve. |
| `size` | integer | Bytes for files; `0` for directories. |
| `mtime` | integer | Unix epoch seconds. |
| `ctime` | integer | Unix epoch seconds. |
| `atime` | integer | Unix epoch seconds. |
| `mode_octal` | string | Last four octal digits, e.g. `"0644"`. |
| `owner_uid` | integer | Numeric uid from `stat()`. |
| `owner_name` | string \| absent | Present only when `function_exists('posix_getpwuid')`. |
| `group_gid` | integer | Numeric gid from `stat()`. |
| `group_name` | string \| absent | Present only when `function_exists('posix_getgrgid')`. |
| `readable` | boolean | `is_readable()` result. |
| `writable` | boolean | `is_writable()` result. |
| `is_link` | boolean | `is_link()` result. |
| `message` | string | Human-readable status. |
| `blocked_reason` | string \| absent | Values: `invalid_path`, `path_not_found`. |

## 5. Ability-registration record (in-memory)

Every ability the plugin registers is keyed by slug in the WordPress ability registry. Feature 090's operations:

| Slug | Operation |
|---|---|
| `file-manager/append-file` | **Register** (new). |
| `file-manager/create-directory` | **Register** (new). |
| `file-manager/delete-directory` | **Register** (new). |
| `file-manager/file-info` | **Register** (new). |

Every other ability remains registered unchanged.

## 6. Guard constants (per-class)

| Class | Constant | Members | Behaviour |
|---|---|---|---|
| `Append_File` | `PROTECTED_FILES` | `wp-config.php`, `.htaccess` | Refuse writes at ABSPATH root. |
| `Delete_Directory` | `PROTECTED_DIRS` | `ABSPATH`, `wp-admin`, `wp-includes`, `wp-content`, `wp-content/plugins`, `wp-content/themes`, `wp-content/mu-plugins`, `wp-content/uploads`, `wp-content/plugins/acrossai-abilities-manager` | Refuse recursive delete regardless of `confirm:true`. |

Constants are duplicated per class rather than extracted to a shared helper — see `research.md §3` for rationale (matches feature 089 convention).

## Validation rules

- `path` MUST be a non-empty string resolvable under `realpath(ABSPATH)`.
- `content` (`append-file`) MUST be a string. Empty string is a valid no-op.
- `prepend` (`append-file`) MUST be boolean; default `false`.
- `recursive` (`create-directory`) MUST be boolean; default `true`.
- `recursive` (`delete-directory`) MUST be boolean; default `false`.
- `confirm` (`delete-directory`) MUST be boolean; MUST be exactly `true` to proceed.
- Every ability rejects unknown properties per JSON Schema `additionalProperties: false` (matches existing convention).
