# Data Model: File Abilities Consolidation (089)

This feature has no persistent data model. It touches the WordPress ability registry (in-memory), the filesystem under ABSPATH, and the plugin's own PHP class files. The "entities" below describe the runtime shapes exchanged over the abilities API.

## 1. Directory-entry record

Emitted by `file-manager/list-directory` for every enumerated path under the requested root.

| Field | Type | Description |
|---|---|---|
| `path` | string | ABSPATH-relative path (POSIX separators). Directories have no trailing slash. |
| `type` | enum `file` \| `dir` | Kind of filesystem entry. |
| `size` | integer (bytes) | For files: byte length. For directories: `0`. |
| `mtime` | integer (Unix epoch seconds) | `filemtime()` result. |

Ordering: deterministic, depth-first, alphabetical within a directory. Order matters for reproducible client-side diffs against previous walks.

## 2. Directory-listing envelope

Response shape of `file-manager/list-directory`.

| Field | Type | Description |
|---|---|---|
| `success` | boolean | `true` on any successful walk (even if empty or truncated). `false` on error. |
| `path` | string | Echoed ABSPATH-relative root the walk started from. |
| `entries` | array of directory-entry records | Collected results, up to `max_entries`. |
| `truncated` | boolean | `true` iff the walk hit `max_depth` or `max_entries` before exhausting the tree. |
| `message` | string | Human-readable status ("Listed N entries.", "No such directory.", etc.). |
| `blocked_reason` | string \| absent | Present only on failure; matches existing FileManager convention (`invalid_path`, `not_a_directory`, `protected_read`). |

## 3. Copy/move envelope

Response shape of `file-manager/copy-file` and `file-manager/move-file`.

| Field | Type | Description |
|---|---|---|
| `success` | boolean | `true` on completion; `false` on refusal or error. |
| `source` | string | Echoed ABSPATH-relative source path. |
| `destination` | string | Echoed ABSPATH-relative destination path. |
| `overwritten` | boolean | `true` iff the destination existed and was replaced. Only meaningful when `success:true` and the caller sent `overwrite:true`. |
| `message` | string | Human-readable status. |
| `blocked_reason` | string \| absent | Present only on refusal. Values: `invalid_path`, `source_not_found`, `destination_exists`, `protected_write`, `file_mods_disabled`. |

## 4. Ability-registration record (in-memory)

Not a data schema per se — captured here because it drives FR-012, FR-013, FR-014.

Every ability the plugin registers is uniquely keyed by its **slug** (`namespace/name`), tracked by the WordPress ability registry initialised at `wp_abilities_api_init` priority 5. This feature's operations on that registry:

| Slug | Operation |
|---|---|
| `file-manager/list-directory` | **Register** (new). |
| `file-manager/copy-file` | **Register** (new). |
| `file-manager/move-file` | **Register** (new). |
| `themes/read-theme-code` | **Unregister** (delete class + bootstrap line). |
| `themes/edit-theme-file` | **Unregister**. |
| `themes/read-theme-structure` | **Unregister**. |
| `plugins/read-plugin-code` | **Unregister**. |
| `plugins/read-plugin-structure` | **Unregister**. |
| `plugins/manage-plugin-files` | **Unregister**. |

Every other file-touching ability slug remains registered unchanged (see spec FR-016 through FR-018 for the preservation list).

## 5. Protected-file guard (constant, per-class)

| Class | Constant | Members | Behaviour |
|---|---|---|---|
| `Read_File` | `PROTECTED_FILES` (existing) | `wp-config.php`, `.htaccess` | Refuse reads. |
| `Delete_File` | `PROTECTED_FILES` (existing) | `wp-config.php`, `.htaccess` | Refuse deletes. |
| `Create_File` | `PROTECTED_FILES` (new — added by this feature) | `wp-config.php`, `.htaccess` | Refuse creates. |
| `Edit_File` | `PROTECTED_FILES` (new — added by this feature) | `wp-config.php`, `.htaccess` | Refuse overwrites. |
| `Copy_File` | `PROTECTED_FILES` (new) | `wp-config.php`, `.htaccess` | Refuse copies **into** a protected file (regardless of `overwrite`). |
| `Move_File` | `PROTECTED_FILES` (new) | `wp-config.php`, `.htaccess` | Refuse moves **from** or **into** a protected file. |

The two-element list is duplicated per class rather than extracted to a shared constant — see research.md §3 for rationale.

## Validation rules

- `path` (all abilities) MUST be a non-empty string, resolvable to a location under `realpath(ABSPATH)`.
- `max_depth` (list-directory) MUST be a positive integer 1–20, default 5.
- `max_entries` (list-directory) MUST be a positive integer 1–5000, default 1000.
- `source` and `destination` (copy/move) MUST both be non-empty strings resolving under ABSPATH.
- `overwrite` (copy/move) MUST be boolean, default `false`.
- Every ability rejects unknown properties per JSON Schema `additionalProperties: false` (matches existing FileManager convention).
