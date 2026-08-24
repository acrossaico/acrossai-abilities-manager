# Feature Specification: File-Manager Additions — Append, mkdir, rmdir, File-Info

**Feature Branch**: `090-file-manager-additions`
**Created**: 2026-08-25
**Status**: Draft
**Input**: Cross-check against `mcp-abilities-filesystem` (see feature-090 planning notes) surfaced four capabilities the consolidated `file-manager/*` surface lacks. Add them as small, focused abilities; skip defense-in-depth hardening (audit log, polyglot PHP scan, .htaccess allowlist) for a later feature.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Append or prepend to an existing file (Priority: P1)

An operator working through an MCP client needs to add a line to `wp-content/debug.log`, a config file, or a helper script without loading and rewriting the entire file. Today they must call `read-file`, concatenate the new content in their own memory, and call `edit-file` to write the whole thing back. That's slow, uses more tokens, and races if the file changes between the two calls.

**Why this priority**: This is the biggest daily papercut with the current surface. Append is the primary need; prepend is a small extra that costs nothing to support.

**Independent Test**: Create a text file at `wp-content/uploads/test-090.txt` containing `"line 1\n"`. Call `file-manager/append-file` with `content: "line 2\n"`. Read the file back — it must contain exactly `"line 1\nline 2\n"`. Call with `prepend:true` and `content: "line 0\n"` — result must be `"line 0\nline 1\nline 2\n"`.

**Acceptance Scenarios**:

1. **Given** the file exists, **When** the operator calls `file-manager/append-file` with a `content` string and no `prepend` flag, **Then** the response is `{success:true, bytes_written: N, new_size: M}` and the file's tail is the new content.
2. **Given** the file exists, **When** the operator calls with `prepend:true`, **Then** the file's head is the new content and the tail is unchanged.
3. **Given** the file does not exist, **When** the operator calls `file-manager/append-file`, **Then** the response is `{success:false}` with a message directing the caller to `file-manager/create-file` — this ability MUST NOT auto-create.
4. **Given** the path resolves to `wp-config.php` or `.htaccess` at ABSPATH root, **When** any append/prepend call is made, **Then** the response returns `{success:false, blocked_reason:"protected_write"}`.
5. **Given** `DISALLOW_FILE_MODS` is defined truthy, **When** the operator calls the ability, **Then** the response reports the file-mods lockout with the same envelope used by other write abilities.

---

### User Story 2 — Create a directory anywhere under ABSPATH (Priority: P1)

An operator needs to prepare a destination folder before copying files, staging an upload, or scaffolding a plugin. Today the consolidated `file-manager/*` surface has no way to create a directory — the operator has to trigger it by side-effect (e.g., `create-file` with `create_dirs:true`), which is awkward and leaves an unwanted stub file.

**Why this priority**: Standalone directory creation is a primitive that copy/move/upload workflows all depend on. Without it, callers hack around the gap.

**Independent Test**: With no existing directory at `wp-content/uploads/acrossai-test-090/nested/deeper/`, call `file-manager/create-directory` on that path. Response is `{success:true, created:true}`. The directory tree now exists. Call again — response is `{success:true, created:false, message:"Directory already exists."}` (idempotent).

**Acceptance Scenarios**:

1. **Given** the parent directory chain does not exist and `recursive:true` (default), **When** the operator calls the ability, **Then** every missing parent is created and the response returns `{success:true, created:true}`.
2. **Given** the parent chain does not exist and `recursive:false`, **When** the operator calls the ability, **Then** the response returns `{success:false}` with a message indicating the parent must exist first.
3. **Given** the target path already exists as a directory, **When** the operator calls the ability, **Then** the response returns `{success:true, created:false}` — safe to re-run.
4. **Given** the target path exists as a file, **When** the operator calls the ability, **Then** the response returns `{success:false}` with a message identifying the collision.
5. **Given** the requested path escapes ABSPATH via `..` or a symlink, **When** the operator calls the ability, **Then** the response refuses with an invalid-path error.

---

### User Story 3 — Delete a directory (empty-only by default, opt-in recursive) (Priority: P1)

An operator has just moved the last file out of a scratch directory and needs to remove the empty parent. Or, less often, they need to wipe an entire scratch tree that they created. Today the consolidated `file-manager/*` surface has no directory-delete at all.

**Why this priority**: Symmetric with `create-directory`. Without it, scratch dirs pile up and there's no clean way to reverse a `create-directory` call.

**Independent Test**: Create `wp-content/uploads/acrossai-test-090/scratch/` with one file inside. Call `file-manager/delete-directory` with `confirm:true` — response is `{success:false}` because the directory is not empty. Call with `recursive:true, confirm:true` — response is `{success:true, entries_removed: 2}` (the file + the directory). Call again — response is `{success:true, entries_removed: 0, message:"Directory does not exist."}` (safe re-run).

**Acceptance Scenarios**:

1. **Given** the directory is empty and `confirm:true` is passed, **When** the operator calls the ability, **Then** the directory is removed and the response returns `{success:true, entries_removed:1}`.
2. **Given** the directory contains entries and `recursive:false` (default), **When** the operator calls the ability with `confirm:true`, **Then** the response returns `{success:false}` with a message identifying the non-empty state.
3. **Given** the directory contains entries and `recursive:true, confirm:true`, **When** the operator calls the ability, **Then** every entry is removed bottom-up and the response reports the total count via `entries_removed`.
4. **Given** `confirm:true` is missing or false, **When** the operator calls the ability, **Then** the response refuses with `{success:false, blocked_reason:"confirmation_required"}` — mirrors `file-manager/delete-file`.
5. **Given** the target path is a critical WordPress directory (`ABSPATH` itself, `wp-admin`, `wp-includes`, `wp-content`, `wp-content/plugins`, `wp-content/themes`, `wp-content/mu-plugins`, `wp-content/uploads`, or this plugin's own directory), **When** the operator calls the ability with `confirm:true, recursive:true`, **Then** the response refuses with `{success:false, blocked_reason:"protected_directory"}`.
6. **Given** the path resolves outside ABSPATH, **When** the operator calls the ability, **Then** the response refuses with an invalid-path error.

---

### User Story 4 — Inspect a file or directory's metadata (Priority: P1)

An operator wants to know how big a file is, when it was last modified, its mode/permissions, and whether the current process can read/write it — without opening it. Right now the only way to learn this is to call `read-file` (which loads content) or `list-directory` on the parent (which returns every sibling too).

**Why this priority**: Cheap read-only primitive that unlocks smarter workflows (e.g., decide whether to skip an unmodified file, or check writability before attempting an edit). Independent of the three write abilities — could be shipped first as a warm-up.

**Independent Test**: Call `file-manager/file-info` with `path:"wp-content/uploads/acrossai-test-090.txt"` (an existing text file). Response includes `type:"file"`, correct `size`, plausible `mtime`/`ctime`/`atime` (Unix epoch integers), 4-char octal `mode_octal` (e.g., `"0644"`), boolean `readable:true`, and boolean `writable:true`. Call on a directory — `type:"dir"`. Call on a symlink to a file — `is_link:true`.

**Acceptance Scenarios**:

1. **Given** the path resolves to a regular file under ABSPATH, **When** the operator calls the ability, **Then** the response returns the file's stat fields (`type:"file"`, `size`, `mtime`, `ctime`, `atime`, `mode_octal`, `readable`, `writable`, `is_link:false`).
2. **Given** the path resolves to a directory, **When** the operator calls the ability, **Then** the response returns `type:"dir"` with the same stat fields.
3. **Given** the path resolves to a symlink and symlinks are followed by default, **When** the operator calls the ability, **Then** the response includes `is_link:true` and the target's stat fields.
4. **Given** the path does not exist, **When** the operator calls the ability, **Then** the response returns `{success:false}` with a "path not found" message.
5. **Given** the path escapes ABSPATH via `..` or a symlink, **When** the operator calls the ability, **Then** the response refuses with an invalid-path error.
6. **Given** the operating system exposes POSIX name-resolution functions (`posix_getpwuid` / `posix_getgrgid`), **When** the operator calls the ability, **Then** the response includes `owner_name` and `group_name` in addition to `owner_uid` and `group_gid`. If POSIX functions are absent, the response omits the name fields.

---

### Edge Cases

- `append-file` is called with an empty `content` string. The response returns `{success:true, bytes_written:0}` — a no-op is not an error.
- `append-file` is called on a very large existing file with `prepend:true`. The whole file must be loaded and rewritten (documented as a caveat in the ability description); large-file operations may exceed PHP memory limits. Acceptable — the ability is intended for small config/log/text files.
- `create-directory` is called with `recursive:true` but every intermediate directory already exists. Response returns `{success:true, created:false}` — no error.
- `delete-directory` is called with a path that is actually a regular file. Response returns `{success:false}` with a message indicating the type mismatch — do NOT silently delete the file.
- `delete-directory` recursive walk encounters a symlink. The symlink itself is unlinked (removes the reference); the target is never followed.
- `delete-directory` walk permission-fails on one child. The partial state is reported: `entries_removed` counts what did complete; `success:false` with a message naming the child that blocked.
- `file-info` is called on a broken symlink (target doesn't exist). Response returns `{success:true, type:"link", is_link:true}` with the size/mtime of the symlink itself, and `readable:false, writable:false`.
- `file-info` is called on wp-config.php. The response returns the file's stat fields — metadata is not protected the way content is. This is intentional (owner/perms of wp-config.php are visible to anyone with shell access anyway).

## Requirements *(mandatory)*

### Functional Requirements

**Adding the four abilities**

- **FR-001**: The plugin MUST register a new ability that appends or prepends caller-supplied bytes to an existing file under the WordPress installation.
- **FR-002**: The append/prepend ability MUST default to append; a boolean input opts in to prepend semantics.
- **FR-003**: The append/prepend ability MUST refuse when the target file does not exist and MUST NOT auto-create.
- **FR-004**: The plugin MUST register a new ability that creates a directory under the WordPress installation, with a boolean input controlling whether missing parent directories are created (default: yes).
- **FR-005**: The create-directory ability MUST be idempotent when the target already exists as a directory (returns success with a flag indicating no creation occurred).
- **FR-006**: The create-directory ability MUST refuse when the target path exists as a file.
- **FR-007**: The plugin MUST register a new ability that removes a directory under the WordPress installation.
- **FR-008**: The delete-directory ability MUST default to refusing non-empty directories; a boolean input opts in to recursive deletion.
- **FR-009**: The delete-directory ability MUST require `confirm:true` (parity with the existing `file-manager/delete-file` ability).
- **FR-010**: The delete-directory ability MUST refuse a hardcoded list of critical WordPress directories: the installation root, `wp-admin`, `wp-includes`, `wp-content`, `wp-content/plugins`, `wp-content/themes`, `wp-content/mu-plugins`, `wp-content/uploads`, and this plugin's own directory.
- **FR-011**: The delete-directory ability MUST NOT follow symlinks during recursive walks.
- **FR-012**: The plugin MUST register a new ability that returns metadata for any file or directory under the WordPress installation (type, size, timestamps, mode/permissions, ownership, readability, writability, symlink flag) without returning the file's content.
- **FR-013**: The file-info ability MUST include POSIX owner/group name resolution when the operating system provides `posix_getpwuid` / `posix_getgrgid`, and MUST degrade gracefully (omit name fields) when those functions are absent.

**Guards (apply to all four abilities as noted)**

- **FR-014**: Every ability MUST require the `manage_options` capability (parity with existing `file-manager/*` abilities).
- **FR-015**: Every ability MUST reject any path that resolves outside the WordPress installation root, including paths that reach outside via `..` traversal or symlinks.
- **FR-016**: The three write-capable abilities (append, create-directory, delete-directory) MUST route through the same file-modification lockout guard used by existing write abilities and refuse when the lockout is active.
- **FR-017**: The append/prepend ability MUST refuse writes to `wp-config.php` or `.htaccess` at the WordPress installation root, using the same `PROTECTED_FILES` guard shape as existing write abilities.
- **FR-018**: The response envelope and blocked-reason strings MUST match the conventions used by the existing `file-manager/*` abilities.

**Not in scope for this feature**

- **FR-019**: This feature MUST NOT ship an audit log, backup directory, polyglot PHP content scan, or `.htaccess` directive allowlist. Those may be added by a later feature but are explicitly deferred.
- **FR-020**: This feature MUST NOT change any existing `file-manager/*` ability's behaviour.

### Key Entities

- **File-manager ability set** — the ability collection under the `file-manager/*` namespace. After this feature it grows from 18 to 22 members. All new members follow the same registration + response conventions as their peers.
- **Protected directories** — the hardcoded list of critical WordPress directories that `delete-directory` refuses regardless of recursive/confirm inputs. Symmetric to the existing `PROTECTED_FILES` list used by write abilities.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After the release is installed, `wp ability list` returns exactly four new file-manager slugs — `file-manager/append-file`, `file-manager/create-directory`, `file-manager/delete-directory`, `file-manager/file-info` — and every previously-registered ability remains registered unchanged.
- **SC-002**: An operator can create, append to, inspect, and remove a scratch directory tree under `wp-content/uploads/` using only these four new abilities plus the existing `create-file` / `delete-file` — no unsupported gap remains in the file-management workflow.
- **SC-003**: No call to any of the four new abilities can write to or delete `wp-config.php`, `.htaccess`, or any of the nine protected directories, regardless of inputs.
- **SC-004**: The change adds four ability slugs, extends the `file-manager/*` namespace from 18 to 22 members, and preserves every guard convention already used by peer abilities (capability, ABSPATH scoping, protected-target refusal, file-mods lockout, response envelope).
- **SC-005**: PHPUnit remains fully green after the addition; PHPStan level 8 clean; PHPCS WPCS strict clean; no new warnings on the pre-existing baseline (baseline as of feature 089 merge: 6 warnings, none from feature-089 code).

## Assumptions

- The audience for this feature is the same operators driving the plugin through MCP clients (including Claude) — not a UI change.
- Symmetry with the existing `file-manager/*` conventions is the primary design constraint. New abilities do not introduce novel shapes or new utility classes.
- Defense-in-depth extras from `mcp-abilities-filesystem` (audit log, polyglot PHP scan, backup infrastructure, `.htaccess` directive allowlist) are valuable but are explicitly out of scope; they may become feature 091 if evidence justifies them.
- Large-file prepend is not chunked. Operators are expected to use these abilities on small config / log / text files. The ability description warns about the memory cost of prepend on large inputs.
- Directory operations use raw PHP built-ins (`wp_mkdir_p`, `mkdir`, `rmdir`, `unlink`, `RecursiveDirectoryIterator`) rather than the `WP_Filesystem` abstraction — consistent with the existing `file-manager/*` abilities which do the same for `read-file`, `create-file`, `edit-file`, `delete-file`, `copy-file`, `move-file`.
