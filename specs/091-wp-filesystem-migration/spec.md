# Feature Specification: WP_Filesystem Migration for `file-manager/*` Abilities

**Feature Branch**: `091-wp-filesystem-migration`
**Created**: 2026-08-25
**Status**: Draft
**Input**: All 22 `file-manager/*` abilities currently use native PHP filesystem functions. Migrate the 19 straightforward ones to WordPress's `WP_Filesystem` abstraction so they work on hosts where `FS_METHOD` is `ftpext` / `ftpsockets` / `ssh2`. Defer the 3 ZipArchive-based abilities to a follow-up feature. Also drop the unavailable `ctime` / `atime` fields from `file-manager/file-info` (breaking for programmatic callers).

## User Scenarios & Testing *(mandatory)*

### User Story 1 — File-manager abilities work on FTP/SSH-transported hosts (Priority: P1)

An operator running an MCP client against a WordPress site on shared hosting (or any environment where the web server can't write directly and WordPress prompts for FTP credentials) needs to read a plugin file, edit `wp-config.php`, or clear `debug.log`. Today those calls fail silently with permission errors. After this migration they succeed via the same transport WordPress core's file editor uses.

**Why this priority**: This is the core value of the migration. Every other outcome (docs, tests, CHANGELOG entry) supports this one.

**Independent Test**: On a WordPress site configured with `FS_METHOD='ftpsockets'` (or any non-`direct` value) and valid `FTP_*` credentials in `wp-config.php`, call `file-manager/edit-file` targeting a plugin file. It succeeds. On a site with the same setup but *missing* credentials, the same call returns `{success:false, blocked_reason:"filesystem_unavailable"}` with a message identifying the credential requirement.

**Acceptance Scenarios**:

1. **Given** the site has `FS_METHOD='direct'`, **When** any migrated ability is called with valid inputs, **Then** it returns the same response envelope shape as before this feature (except `file-info` — see US4).
2. **Given** the site has `FS_METHOD='ftpsockets'` with valid FTP credentials in `wp-config.php`, **When** a migrated write ability is called, **Then** the write succeeds and returns `{success:true, ...}`.
3. **Given** the site has `FS_METHOD='ftpsockets'` with NO credentials configured, **When** any migrated ability is called, **Then** it returns `{success:false, blocked_reason:"filesystem_unavailable"}` with a message explaining the credential requirement.
4. **Given** any transport, **When** a caller invokes any migrated ability, **Then** the ability's `manage_options` capability check, `File_Mods_Guard` lockout check, and `PROTECTED_FILES` / `PROTECTED_DIRS` guards still apply and take precedence over transport concerns.

---

### User Story 2 — `wp-config.php` and `debug.log` abilities benefit most (Priority: P1)

Because `wp-config.php` and `wp-content/debug.log` are the paths most likely to have restrictive ownership (owned by the SSH user, not the web-server user), the four abilities that touch them see the biggest benefit: `file-manager/read-wp-config`, `file-manager/edit-wp-config`, `file-manager/read-debug-log`, `file-manager/clear-debug-log`.

**Why this priority**: These are the "fix a broken site from an MCP client" abilities. If they don't work over FTP, the operator has no recourse.

**Independent Test**: On an FTP-transported site, call `file-manager/edit-wp-config` to toggle `WP_DEBUG` from `false` to `true`. The write succeeds; the constant is updated on the filesystem; subsequent `file-manager/read-wp-config` reflects the new value.

**Acceptance Scenarios**:

1. **Given** `FS_METHOD` is any supported transport and credentials are configured, **When** `file-manager/edit-wp-config` is invoked to change a non-sensitive constant, **Then** the write completes and the change is visible on disk.
2. **Given** the same setup, **When** `file-manager/clear-debug-log` is invoked, **Then** the log is truncated to zero bytes (not deleted) and WordPress continues appending on the next log event.
3. **Given** existing guards (secret redaction on read, protected-constant allowlist on edit), **When** either wp-config ability is invoked, **Then** the guards run exactly as they did before this feature.

---

### User Story 3 — Zip-backup abilities keep working on `direct` transport (Priority: P2)

`file-manager/create-zip-backup`, `file-manager/extract-zip-backup`, `file-manager/upload-zip-backup` are not migrated in this feature (deferred to feature 092 because `ZipArchive` and chunked `fopen`-based uploads have no `WP_Filesystem` equivalent). On `direct`-transport hosts they continue working exactly as before. On non-`direct` transports they were already broken; this feature does not fix them, but it also does not regress them.

**Why this priority**: Preserves an explicit non-regression contract. Any client calling these three today on a `direct` host must keep working.

**Independent Test**: On a `direct`-transport site, `file-manager/create-zip-backup` still produces a zip in `wp-content/uploads/acrossai-backups/`. Response shape, guards, and behaviour are identical to the pre-migration baseline.

---

### User Story 4 — `file-info` schema shrinks to what `WP_Filesystem` can provide (Priority: P1, BREAKING)

`file-manager/file-info` currently returns `size`, `mtime`, `ctime`, `atime`, `mode_octal`, and ownership fields. `WP_Filesystem_Base` does not expose `ctime` or `atime`. After this migration those two fields are removed from the response schema.

**Why this priority**: One-time breaking change; needs to ship together with the migration so the shape doesn't wobble. Callers that programmatically read `ctime` / `atime` will need to update.

**Independent Test**: Call `file-manager/file-info` on any path. Response contains `size`, `mtime`, `mode_octal`, `owner_uid`, `group_gid`, `readable`, `writable`, `is_link`. Response does NOT contain `ctime` or `atime`.

**Acceptance Scenarios**:

1. **Given** any transport, **When** `file-manager/file-info` is called on an existing file, **Then** the response omits `ctime` and `atime` from the top-level fields.
2. **Given** the CHANGELOG entry, **When** a first-time reader looks at the Unreleased section, **Then** the removal is called out as BREAKING with the affected field names.

---

### Edge Cases

- `WP_Filesystem()` initialisation succeeds but returns a transport object that fails on the first `->put_contents()` call. Response returns the underlying transport error via the ability's normal error path.
- The site defines `FS_METHOD` to an unknown value. `WP_Filesystem()` returns false; every ability responds `blocked_reason:'filesystem_unavailable'`.
- A recursive `file-manager/list-directory` walk encounters a directory that `$fs->dirlist()` returns as empty due to permissions. The walk continues with the entries it did collect; response reports what it saw and does NOT throw.
- `file-manager/file-info` is called on `wp-config.php` living above ABSPATH (WordPress's security-hardened layout). Response returns the file's metadata regardless of location.
- The three deferred zip abilities are called on a non-`direct` host. Behaviour is unchanged from today (they may fail with a permission error). CHANGELOG documents the deferral.

## Requirements *(mandatory)*

### Functional Requirements

**Transport migration**

- **FR-001**: The plugin MUST include a shared utility that initialises `WP_Filesystem`, returns the resulting `WP_Filesystem_Base` object or a `WP_Error`, and offers a convenience method returning the ability response envelope when initialisation fails.
- **FR-002**: Every migrated ability MUST call the initialisation utility before any filesystem operation and MUST return `{success:false, blocked_reason:"filesystem_unavailable", message:<string>}` when initialisation fails.
- **FR-003**: Every migrated ability MUST perform all filesystem reads, writes, deletions, moves, copies, and metadata lookups through the initialised `WP_Filesystem_Base` object rather than native PHP filesystem functions.
- **FR-004**: The migration MUST cover these 19 ability classes: `Read_File`, `Create_File`, `Edit_File`, `Delete_File`, `Copy_File`, `Move_File`, `Append_File`, `Create_Directory`, `Delete_Directory`, `List_Directory`, `File_Info`, `Read_Wp_Config`, `Edit_Wp_Config`, `Get_Wp_Config_Constant`, `Read_Debug_Log`, `Clear_Debug_Log`, `Download_Zip_Backup`, `List_Zip_Backups`, `Delete_Zip_Backup`.
- **FR-005**: The migration MUST NOT touch `Create_Zip_Backup`, `Extract_Zip_Backup`, `Upload_Zip_Backup` (deferred to a follow-up feature); each of these MUST carry a source-level comment identifying the deferral and the follow-up feature number.

**Preserved behaviour**

- **FR-006**: Every ability's slug, input schema, and `manage_options` capability check MUST remain unchanged.
- **FR-007**: Every ability's guards (`File_Mods_Guard`, `PROTECTED_FILES`, `PROTECTED_DIRS`, `confirm:true` gates) MUST continue to apply and MUST run before the filesystem operation.
- **FR-008**: `File_Mods_Guard` at `includes/Abilities/Utilities/File_Mods_Guard.php` MUST NOT be modified.
- **FR-009**: `Ability_Definition` at `includes/Modules/Library/Ability_Definition.php` MUST NOT be modified. Its public and protected surface MUST remain exactly as-is so that sibling AcrossAI plugins (e.g. `acrossai-buddyboss`) that extend it are unaffected.

**Response envelope changes**

- **FR-010**: Every migrated ability's `output_schema` MUST widen its `blocked_reason` enum to include `filesystem_unavailable`.
- **FR-011**: The `file-manager/file-info` ability's response MUST NOT include `ctime` or `atime` fields after this feature. Its `output_schema` MUST be updated to reflect the removal.
- **FR-012**: No other ability's response envelope shape MUST change.

**Recursive-walk refactor**

- **FR-013**: `file-manager/list-directory` MUST replace its `RecursiveIteratorIterator` walk with a recursive walk driven by `$wp_filesystem->dirlist()`. The existing `max_depth`, `max_entries`, `truncated`, and symlink-skip semantics MUST be preserved.
- **FR-014**: `file-manager/delete-directory` MUST replace its `RecursiveIteratorIterator CHILD_FIRST` walk with a recursive `dirlist()` walk that deletes contents bottom-up. The existing `entries_removed` count, `confirm:true` gate, `PROTECTED_DIRS` refusal, and symlink-skip semantics MUST be preserved.

**Append-file semantics**

- **FR-015**: `file-manager/append-file` MUST implement append by reading current contents via `$wp_filesystem->get_contents()`, concatenating in memory, and writing via `$wp_filesystem->put_contents()`. The ability description MUST document that this pattern is non-atomic (a concurrent writer could win). Prepend semantics remain unchanged.

**Test + tooling hygiene**

- **FR-016**: The plugin MUST include a new PHPUnit test class that asserts every migrated ability contains the initialisation call, contains at least one `$wp_filesystem->` invocation, and does NOT contain the disallowed native filesystem functions.
- **FR-017**: The test class MUST also assert that the three deferred zip abilities STILL contain native calls (so they don't get accidentally migrated without the full design).
- **FR-018**: Every PHPCS suppression of the form `WordPress.WP.AlternativeFunctions.*` in migrated files MUST be removed. Suppressions in the three deferred files MAY remain.

### Key Entities

- **`WP_Filesystem_Base`** — WordPress's abstract filesystem class. Every migrated ability holds an instance for the duration of its `execute()` call.
- **`Wp_Filesystem_Init`** — new plugin utility that centralises the `WP_Filesystem()` bootstrap and provides the ability-response envelope on failure.
- **Migrated ability set** — the 19 file-manager ability classes converted by this feature. After the migration, they all share the same transport-init pattern.
- **Deferred ability set** — the 3 zip-backup abilities that keep native PHP for now, marked with source-level TODOs pointing at the follow-up feature.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After this feature ships, every migrated ability responds successfully on both `FS_METHOD='direct'` and `FS_METHOD='ftpsockets'` (with credentials) transports.
- **SC-002**: An operator on an FTP-transported WordPress host can call `file-manager/edit-wp-config` to change a non-sensitive constant, and the write completes successfully.
- **SC-003**: When `WP_Filesystem()` initialisation fails, every migrated ability returns `{success:false, blocked_reason:"filesystem_unavailable"}` — never a native PHP warning, never a silent success.
- **SC-004**: `grep -rn "phpcs:ignore WordPress.WP.AlternativeFunctions" includes/Abilities/FileManager/` returns matches only in `Create_Zip_Backup.php`, `Extract_Zip_Backup.php`, `Upload_Zip_Backup.php` — zero matches in the 19 migrated files.
- **SC-005**: `file-manager/file-info` response objects contain no `ctime` or `atime` fields on any transport.
- **SC-006**: PHPUnit full suite green; PHPStan level 8 clean; PHPCS WPCS strict clean. No regression on the 6-warning pre-existing baseline.
- **SC-007**: Sibling plugin `acrossai-buddyboss` continues to load and register its abilities without error — evidence that `Ability_Definition` was not touched.

## Assumptions

- Reference plugin `mcp-abilities-filesystem` at `/Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/plugins/mcp-abilities-filesystem/mcp-abilities-filesystem.php` provides validated WP_Filesystem idioms and may be consulted when the standard mapping table is unclear.
- The pre-existing `File_Mods_Guard` utility is unchanged and continues to enforce `DISALLOW_FILE_MODS` / `DISALLOW_FILE_EDIT` before the filesystem-init step.
- Existing tests (`Test_Feature_089_File_Consolidation.php`, `Test_Feature_090_File_Manager_Additions.php`) use source-string inspection rather than runtime execution. Their assertion strings will need small updates to match the new idioms; assertion counts remain roughly equivalent.
- The three deferred zip abilities are already broken on non-`direct` transports today; this feature does not regress them and does not fix them.
- The `ctime` / `atime` fields in `file-manager/file-info` are not load-bearing for any known caller. If a caller exists, they migrate to reading `mtime` instead or accept the loss.
