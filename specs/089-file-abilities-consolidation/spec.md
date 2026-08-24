# Feature Specification: File Abilities Consolidation

**Feature Branch**: `089-file-abilities-consolidation`
**Created**: 2026-08-25
**Status**: Draft
**Input**: Consolidate file-touching abilities so that a single `file-manager/*` surface handles every read/write/list/copy/move of files anywhere in the WordPress installation. Remove six theme- and plugin-scoped duplicates. Close a pre-existing security gap in the generic write path.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — One surface for reading, writing, and browsing any file (Priority: P1)

An operator working through an MCP client needs to inspect and edit files anywhere in the WordPress installation — including inside theme and plugin directories — without hunting for a differently-named ability per location. Today they must remember `themes/read-theme-code` for theme files, `plugins/read-plugin-code` for plugin files, and `file-manager/read-file` for anything else, all of which do the same thing. Consolidating gives them one predictable surface: pass a path under ABSPATH and the same ability handles it.

**Why this priority**: This is the whole point of the feature. Every subsequent scenario depends on the consolidated surface being usable end-to-end for reads, writes, and directory browsing.

**Independent Test**: On a fresh install, ask an MCP client to list a plugin directory, read one of its files, and edit one of its files, using only `file-manager/*` abilities. All three calls must succeed and produce the same results a caller would previously have gotten from the six theme/plugin-scoped abilities.

**Acceptance Scenarios**:

1. **Given** the plugin is active on a site with at least one non-active theme and one non-active plugin, **When** the operator calls `file-manager/list-directory` on a plugin folder path, **Then** the response returns the recursive contents of that plugin's directory with type, size, and modification time per entry.
2. **Given** the same site, **When** the operator calls `file-manager/read-file` with a path pointing at a file inside a theme, **Then** the response returns the file's contents subject to the same size and binary-detection rules that apply anywhere under ABSPATH.
3. **Given** the same site, **When** the operator calls `file-manager/edit-file` with a path pointing at a file inside a theme or plugin, **Then** the file is overwritten with the supplied content and no error is returned.
4. **Given** the same site, **When** the operator calls `file-manager/copy-file` or `file-manager/move-file` to move a file within `wp-content/plugins/`, **Then** the operation completes with the destination now containing the source's content and (for move) the source no longer existing.

---

### User Story 2 — Old scoped ability slugs no longer exist (Priority: P1)

The six theme/plugin-scoped file abilities are removed from the registry entirely. A caller that requests them by their old slug gets a "no such ability" error. The CHANGELOG documents the removal and the replacement so integrators can migrate.

**Why this priority**: Consolidation is only meaningful if the duplicates actually go away. Leaving them in place defeats the purpose of the feature and lets clients diverge in which slug they call.

**Independent Test**: Enumerate the site's ability registry (via WP-CLI or REST) and confirm none of the six removed slugs are present, then attempt to execute each one and confirm each attempt returns an error indicating the ability does not exist.

**Acceptance Scenarios**:

1. **Given** the release is installed, **When** an operator lists all registered abilities, **Then** none of `themes/read-theme-code`, `themes/edit-theme-file`, `themes/read-theme-structure`, `plugins/read-plugin-code`, `plugins/read-plugin-structure`, `plugins/manage-plugin-files` appears in the list.
2. **Given** an external MCP client that hardcoded one of the removed slugs, **When** it invokes that slug, **Then** it receives a clear "unknown ability" error rather than silent success or an unrelated response.
3. **Given** the release notes, **When** an integrator reads the CHANGELOG entry, **Then** the entry names each removed slug and identifies the `file-manager/*` replacement it should call instead.

---

### User Story 3 — Protected system files cannot be overwritten through the generic write path (Priority: P1)

Today `file-manager/create-file` and `file-manager/edit-file` will happily overwrite `wp-config.php` or `.htaccess`, even though `file-manager/read-file` and `file-manager/delete-file` refuse to touch them. Once the theme/plugin write abilities are gone, generic file-manager becomes the *only* write path, so it must consistently refuse these protected files. The dedicated `file-manager/edit-wp-config` ability (which allows narrow, per-constant edits with a secret-key allowlist) remains the only supported way to modify `wp-config.php`.

**Why this priority**: Consolidation must not silently widen the attack surface. Leaving the generic write path unguarded while removing the scoped alternatives would give a caller more power than they had before.

**Independent Test**: Call `file-manager/create-file` and `file-manager/edit-file` against `wp-config.php` and `.htaccess`. Both must return a blocked-write response. Confirm that `file-manager/edit-wp-config` still succeeds for a non-sensitive constant on the same site.

**Acceptance Scenarios**:

1. **Given** the release is installed, **When** an operator calls `file-manager/edit-file` with a path resolving to `wp-config.php`, **Then** the response indicates the operation is refused and identifies the file as protected.
2. **Given** the same site, **When** an operator calls `file-manager/create-file` with a path resolving to `.htaccess`, **Then** the response indicates the operation is refused and no file is written.
3. **Given** the same site, **When** an operator calls `file-manager/edit-wp-config` to change a non-sensitive constant, **Then** the operation succeeds — confirming the specialized wrapper is still the supported path.

---

### Edge Cases

- `file-manager/list-directory` is asked to walk a very large directory tree. The response must cap the number of entries returned and indicate that the results were truncated so the caller knows more entries exist.
- `file-manager/list-directory` is asked to walk a path that does not exist or is not a directory. The response must return an error rather than an empty successful result.
- `file-manager/copy-file` and `file-manager/move-file` are called with a source or destination that resolves (via symlink or `..`) to a path outside ABSPATH. The operation must be refused.
- `file-manager/copy-file` and `file-manager/move-file` are called with a destination that already exists. The default must be to refuse; an explicit "allow overwrite" input is required to proceed.
- `file-manager/copy-file` and `file-manager/move-file` are called with the destination pointing at `wp-config.php` or `.htaccess`. The operation must be refused even when the caller sets "allow overwrite".
- `file-manager/move-file` is called on `wp-config.php` as the source (moving *out of* it). The operation must be refused.
- An external MCP client calls one of the removed slugs after upgrading. The plugin does not crash; the ability API returns a normal "unknown ability" response.
- The `DISALLOW_FILE_MODS` (or `DISALLOW_FILE_EDIT`) constant is true. New write-capable abilities (`copy-file`, `move-file`) refuse the operation with the same shape used by existing write abilities.

## Requirements *(mandatory)*

### Functional Requirements

**Adding the consolidated surface**

- **FR-001**: The plugin MUST register a new ability that returns a recursive listing of the entries under any given path within the WordPress installation, with each entry carrying its path, type (file or directory), size, and modification time.
- **FR-002**: The directory-listing ability MUST enforce a bounded recursion depth and a bounded total entry count. When either bound is reached, the ability MUST return the collected entries plus an indication that the results are truncated.
- **FR-003**: The plugin MUST register a new ability that copies a file from a source path to a destination path within the WordPress installation.
- **FR-004**: The plugin MUST register a new ability that moves a file from a source path to a destination path within the WordPress installation.
- **FR-005**: The copy and move abilities MUST default to refusing when the destination already exists, and MUST accept an explicit input that permits overwriting.
- **FR-006**: The three new abilities MUST reject any source or destination path that resolves outside the WordPress installation root, including paths that reach outside via symlinks or `..` traversal.
- **FR-007**: The copy and move abilities MUST honour the site's file-modification lockout constants (equivalent to the guard already applied by existing write abilities) and refuse when file modifications are disabled.

**Closing the write-path guard gap**

- **FR-008**: The generic create-file and edit-file abilities MUST refuse to write to `wp-config.php` (at the WordPress installation root) with a response shape consistent with the existing refusal returned by the read-file and delete-file abilities for the same paths.
- **FR-009**: The generic create-file and edit-file abilities MUST refuse to write to `.htaccess` (at the WordPress installation root) with the same response shape as FR-008.
- **FR-010**: The copy and move abilities MUST refuse when the destination path resolves to `wp-config.php` or `.htaccess`, regardless of any "allow overwrite" input.
- **FR-011**: The move ability MUST refuse when the source path resolves to `wp-config.php` or `.htaccess`.

**Removing the duplicate scoped abilities**

- **FR-012**: The plugin MUST NOT register any of the following ability slugs after this feature ships: `themes/read-theme-code`, `themes/edit-theme-file`, `themes/read-theme-structure`, `plugins/read-plugin-code`, `plugins/read-plugin-structure`, `plugins/manage-plugin-files`.
- **FR-013**: The plugin MUST remove the PHP classes that implement the removed abilities from the codebase, so that the deletion is not merely a de-registration.
- **FR-014**: The plugin MUST update its bootstrap and category registrars so no code path attempts to instantiate or register a removed ability class.
- **FR-015**: The plugin MUST update its documentation (README, docs, and changelog) to (a) remove all references to the removed abilities and (b) describe the migration path from each removed slug to the `file-manager/*` replacement.

**Preserved behaviour (do not change)**

- **FR-016**: The specialized `file-manager/read-wp-config`, `edit-wp-config`, `get-wp-config-constant`, `read-debug-log`, and `clear-debug-log` abilities MUST remain registered and behave as they did before this feature.
- **FR-017**: The `recovery/list-recent-fatal-errors` ability MUST remain registered and behave as it did before this feature.
- **FR-018**: All theme, plugin, and core lifecycle abilities (install, activate, deactivate, update, delete-theme, lifecycle-context, checksums, and similar) MUST remain registered and behave as they did before this feature.
- **FR-019**: The response envelope and blocked-reason strings used by the new abilities MUST match the conventions used by the existing `file-manager/*` abilities so that clients can handle them uniformly.

### Key Entities

- **File-manager ability set**: The set of abilities under the `file-manager/*` slug namespace. After this feature it is the sole owner of raw file I/O across the WordPress installation. Its surface grows by three (list-directory, copy-file, move-file) and its existing write abilities gain a protected-file refusal.
- **Removed scoped abilities**: The six theme- and plugin-scoped file abilities that this feature deletes. Their capabilities are subsumed by the file-manager set.
- **Protected files**: `wp-config.php` and `.htaccess` at the WordPress installation root. All generic file-manager write paths must refuse them.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An operator can read, write, list, copy, and move any file under the WordPress installation using only `file-manager/*` abilities, without needing to know whether the target is inside a theme, a plugin, or elsewhere.
- **SC-002**: After the release is installed, the six removed ability slugs are absent from the ability registry, and each attempt to invoke one by name returns an "unknown ability" error rather than executing.
- **SC-003**: No call to `file-manager/create-file`, `file-manager/edit-file`, `file-manager/copy-file`, or `file-manager/move-file` can write to `wp-config.php` or `.htaccess`, regardless of inputs.
- **SC-004**: The change reduces the number of file-touching ability slugs surfaced to MCP clients by six (from the current count down by six), while preserving every distinct capability that the removed slugs previously provided.
- **SC-005**: A first-time reader of the CHANGELOG and updated documentation can identify, for every removed slug, the `file-manager/*` replacement to call instead — without having to read source code.

## Assumptions

- The audience for this feature is operators driving the plugin through an MCP client (including Claude) and integrators reading the ability registry programmatically. It is not a UI-facing change.
- The theme- and plugin-scoped file abilities being removed are not part of a public stability contract at this stage of the plugin's life. External clients that hardcoded those slugs will need to migrate to `file-manager/*`; a hard break at this release is acceptable and preferred over a maintenance-heavy shim.
- The dedicated `file-manager/edit-wp-config` ability remains the only supported way to modify `wp-config.php`. Its narrow, per-constant surface with a secret-key allowlist is intentionally stricter than the generic write path.
- The existing `file-manager/*` guards (ABSPATH scoping, path-traversal rejection, `manage_options` capability requirement, `DISALLOW_FILE_MODS`/`DISALLOW_FILE_EDIT` honouring, response envelope shape) are the reference behaviour for the three new abilities.
- Directory-listing performance under a bounded entry count is acceptable for the operator workflows in scope; if the bound is hit, callers narrow their path or accept truncation.
- No existing PHPUnit test exercises the six removed abilities (confirmed during Phase 1 exploration); no admin UI reads their slugs; no filter hook aliases them. Deleting the classes is safe within the plugin's own code.
