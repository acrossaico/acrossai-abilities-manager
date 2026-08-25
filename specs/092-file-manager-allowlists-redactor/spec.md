# Feature Specification: File-Manager Path Allowlists (Read + Write) + Configurable Secret Redaction

**Feature Branch**: `092-file-manager-allowlists-redactor`
**Created**: 2026-08-25
**Status**: Draft
**Input**: Site admins need per-folder control over what the AI can read and modify via `file-manager/*`. Reads default to unrestricted (with automatic secret scrubbing). Writes default to `wp-content` only. Redaction pattern set is admin-configurable. All settings live under a new "File Manager" tab at `admin.php?page=acrossai-settings`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Restrict AI writes to a specific plugin folder (Priority: P1)

An admin wants the MCP client to only be able to edit files inside `wp-content/plugins/hello-dolly`. Everything else — other plugins, themes, wp-admin, wp-includes, wp-content root — should be write-refused.

**Why this priority**: Core value of the feature. Everything else supports this workflow.

**Independent Test**: In the admin UI, uncheck `wp-content`, check only `wp-content/plugins/hello-dolly`, save. Call `file-manager/edit-file` on `wp-content/plugins/hello-dolly/hello.php` → success. Call the same on any other path → `{success:false, blocked_reason:"path_not_allowed_for_write"}`.

**Acceptance Scenarios**:

1. **Given** the write allowlist contains only `wp-content/plugins/hello-dolly`, **When** the AI calls any write ability on a path inside that folder, **Then** the operation succeeds.
2. **Given** the same allowlist, **When** the AI calls any write ability on a path outside, **Then** the response is `{success:false, blocked_reason:"path_not_allowed_for_write"}` and the disk is unchanged.
3. **Given** an empty write allowlist, **When** the AI calls any write ability, **Then** the response is refused regardless of path.
4. **Given** `copy-file` or `move-file` with source inside the allowlist and destination outside, **When** invoked, **Then** the response is refused (destination check fails); no file is copied/moved.

---

### User Story 2 — Read access remains open by default, secrets scrubbed (Priority: P1)

An admin has not touched the read allowlist (default: unrestricted). The AI reads `wp-config.php`. The response comes back with the file contents but every credential (DB_PASSWORD, DB_USER, auth keys, salts) is replaced with `***REDACTED***`.

**Why this priority**: Removes the current "refuse wp-config entirely" behaviour in favour of "return + scrub", which is what the user asked for.

**Independent Test**: On a fresh install with default settings, call `file-manager/read-file` with `path: "wp-config.php"`. Response contains file content; every quoted value inside `define('DB_PASSWORD', …)` and each of the 8 auth keys/salts is replaced with `***REDACTED***`. Response includes `redacted:true` and `redaction_count:N`.

**Acceptance Scenarios**:

1. **Given** default settings, **When** the AI reads any file under ABSPATH (including wp-config.php), **Then** the response returns the content and the response includes `redacted:bool` + `redaction_count:int`.
2. **Given** the file contains a WP credential inside a `define()` construct, **When** read, **Then** the credential value is replaced with `***REDACTED***` but the constant name is preserved.
3. **Given** the file contains a well-formed Stripe/AWS/OpenAI/Anthropic/GitHub/SendGrid API key anywhere in the content, **When** read, **Then** that pattern is replaced with a class-prefixed `***REDACTED***`.
4. **Given** the file contains no known-secret patterns, **When** read, **Then** the response returns unchanged content with `redacted:false` and `redaction_count:0`.

---

### User Story 3 — Admin narrows read access to specific folders (Priority: P2)

An admin flips the "Restrict reads to specific folders" toggle in the Read Access panel and picks `wp-content` only. The AI can now only read files under `wp-content`; any read outside returns refused.

**Why this priority**: Symmetric with the write allowlist for admins who want tighter control.

**Independent Test**: Toggle the restriction on, select `wp-content`, save. Call `file-manager/read-file` on `wp-content/plugins/hello-dolly/hello.php` → success. Call on `wp-admin/index.php` → `{success:false, blocked_reason:"path_not_allowed_for_read"}`.

**Acceptance Scenarios**:

1. **Given** the read allowlist is empty (unrestricted), **When** the AI reads any file, **Then** the read succeeds (subject to existing size/binary limits and redaction).
2. **Given** the read allowlist contains only `wp-content`, **When** the AI reads a file inside, **Then** success. Outside → refused.
3. **Given** any allowlist state, `list-directory` and `file-info` are unaffected (metadata only, not gated).

---

### User Story 4 — Admin adds a custom secret to the redaction list (Priority: P2)

An admin knows a specific token is present in the site's files and wants it always scrubbed from AI-visible reads. They open the Redaction panel, paste the token into the "Custom literals" textarea, save.

**Why this priority**: Every site has its own vocabulary of secrets. A hardcoded pattern set can't cover them all.

**Independent Test**: Add `my-secret-abc` to the custom literals. Write "hello my-secret-abc world" to a scratch file. Read it back — response shows "hello ***REDACTED*** world" with `redacted:true, redaction_count:1`.

**Acceptance Scenarios**:

1. **Given** the custom-literals list contains `my-secret-abc`, **When** the AI reads a file containing that exact string, **Then** every occurrence is replaced with `***REDACTED***`.
2. **Given** the admin toggles the `stripe` built-in pattern off, **When** the AI reads a file containing a Stripe key, **Then** the key is returned unredacted (the toggle-off is respected).
3. **Given** the admin toggles `jwt` on (off by default), **When** the AI reads a file containing a JWT, **Then** the JWT is redacted.

---

### Edge Cases

- Empty write allowlist → every write ability refuses regardless of path.
- Empty read allowlist → all reads permitted (sentinel).
- Both allowlists contain the same path → each is evaluated independently against its own list.
- The `copy-file` or `move-file` source is inside the write allowlist but destination isn't → refused (destination check fails).
- The read allowlist contains a path that no longer exists on disk → matches only fail (the file that doesn't exist can't be read anyway); no crash.
- Custom literal contains an empty string → ignored (would otherwise match everywhere).
- Custom literal contains a regex metacharacter → treated as a literal string (no regex parsing).
- Binary file read → redactor skipped (existing binary detection path in `read-file` short-circuits before content is processed).
- `wp-config.php` lives above ABSPATH (security-hardened install) → still readable + redacted; write refusal still applies via existing `PROTECTED_FILES` guards.

## Requirements *(mandatory)*

### Functional Requirements

**Write allowlist**

- **FR-001**: The plugin MUST store a write allowlist as an array of ABSPATH-relative paths in a WordPress option.
- **FR-002**: The plugin MUST default the write allowlist to `['wp-content']` on activation (via `add_option`, idempotent).
- **FR-003**: The 8 write-capable file-manager abilities (`create-file`, `edit-file`, `delete-file`, `copy-file`, `move-file`, `append-file`, `create-directory`, `delete-directory`) MUST refuse operations whose target path resolves outside every entry in the write allowlist.
- **FR-004**: An empty write allowlist MUST refuse all writes.
- **FR-005**: `copy-file` and `move-file` MUST check the write allowlist against BOTH the source and the destination path.
- **FR-006**: The refusal response envelope MUST use `blocked_reason: "path_not_allowed_for_write"` and include the current `allowed_roots` array for caller diagnostics.

**Read allowlist**

- **FR-007**: The plugin MUST store a read allowlist as an array of ABSPATH-relative paths in a WordPress option.
- **FR-008**: The plugin MUST default the read allowlist to `[]` (unrestricted sentinel) on activation.
- **FR-009**: The 2 content-reading file-manager abilities (`read-file`, `read-debug-log`) MUST refuse operations whose target path resolves outside every entry in a non-empty read allowlist. An empty read allowlist MUST allow all reads.
- **FR-010**: `list-directory` and `file-info` MUST NOT check the read allowlist (metadata only, no content leak).
- **FR-011**: The refusal response envelope MUST use `blocked_reason: "path_not_allowed_for_read"`.

**Secret redactor**

- **FR-012**: The plugin MUST store a redaction configuration as an option containing `patterns` (map of pattern-id → boolean enabled) and `custom_literals` (array of strings).
- **FR-013**: The plugin MUST ship 8 built-in pattern classes: `wp_credentials`, `stripe`, `aws_access_key`, `openai`, `anthropic`, `github`, `sendgrid`, `jwt`. All except `jwt` MUST be enabled by default.
- **FR-014**: `file-manager/read-file` MUST route response `content` through `Secret_Redactor::scrub()` before returning, when content is text (binary content skipped).
- **FR-015**: `file-manager/read-debug-log` MUST route response `content` through `Secret_Redactor::scrub()`.
- **FR-016**: Redacted responses MUST include `redacted: bool` and `redaction_count: int` fields.
- **FR-017**: The redactor MUST apply only patterns whose config value is `true`.
- **FR-018**: The redactor MUST apply every custom literal as a case-sensitive literal-string match (no regex parsing), replacing occurrences with `***REDACTED***`.
- **FR-019**: The `wp_credentials` pattern MUST preserve the constant name in `define('NAME', 'value')` and replace only the quoted value.

**Behaviour changes (BREAKING)**

- **FR-020**: `file-manager/read-file` MUST NO LONGER return `blocked_reason: "protected_read"` for `wp-config.php` / `.htaccess`. Those files are now readable; secrets are redacted per FR-014.

**Admin UI**

- **FR-021**: The plugin MUST register a new "File Manager" tab at `admin.php?page=acrossai-settings` via the existing `acrossai_settings_tabs` filter.
- **FR-022**: The tab MUST expose three panels — Write access, Read access, Secret redaction — via a React admin bundle.
- **FR-023**: The Write access panel MUST render a two-level tree (`wp-admin`, `wp-content`, `wp-includes` at top; `wp-content` immediate children below), an installed-plugins checkbox list, an installed-themes checkbox list, and a custom-paths textarea. Selection state persists to the write allowlist option.
- **FR-024**: The Read access panel MUST render the same UI plus a "Restrict reads to specific folders" toggle. When off (default), the tree is disabled and empty read allowlist is persisted.
- **FR-025**: The Secret redaction panel MUST render a checkbox list of built-in patterns and a textarea for custom literals. Selection state persists to the redaction config option.

**REST API**

- **FR-026**: The plugin MUST expose GET/POST endpoints for each of the three settings under `acrossai/v1`. `permission_callback` MUST require `manage_options` and validate the `X-WP-Nonce` header. Return type MUST be `true | WP_Error`.
- **FR-027**: The GET endpoints MUST include enumeration data (core directories under ABSPATH, `get_plugins()` output, `wp_get_themes()` output) alongside current state.
- **FR-028**: POST endpoints MUST sanitize input paths (strip leading/trailing slashes, reject `..` traversal, reject paths that escape ABSPATH after `realpath`).

**Preservation**

- **FR-029**: `Ability_Definition`, `File_Mods_Guard`, `Wp_Filesystem_Init` MUST NOT be modified.
- **FR-030**: `Read_Wp_Config`, `Edit_Wp_Config`, `Get_Wp_Config_Constant`, `list-directory`, `file-info`, and the 6 zip-backup abilities MUST NOT be modified by this feature.
- **FR-031**: Write-side `PROTECTED_FILES` guards on `Create_File`, `Edit_File`, `Delete_File`, `Copy_File`, `Move_File`, `Append_File` MUST remain in place.

### Key Entities

- **Write allowlist** — array of ABSPATH-relative paths. Empty = deny all writes.
- **Read allowlist** — array of ABSPATH-relative paths. Empty = allow all reads (sentinel).
- **Redaction config** — `{ patterns: {id → bool}, custom_literals: string[] }`.
- **`Path_Allowlist_Guard`** — new utility class exposing both allowlists.
- **`Secret_Redactor`** — new utility class with 8 hardcoded regex patterns + admin-controlled toggle map + admin-controlled literal list.
- **"File Manager" admin tab** — new tab class registered via `acrossai_settings_tabs`.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After installation with defaults, `file-manager/edit-file` on any path outside `wp-content` returns `blocked_reason: "path_not_allowed_for_write"`.
- **SC-002**: After installation with defaults, `file-manager/read-file` on `wp-config.php` returns `success:true` with `redacted:true` and `redaction_count >= 10` (assuming a standard wp-config has DB_PASSWORD + 8 salts + at least 1 additional).
- **SC-003**: `wp option get acrossai_file_manager_write_allowlist` returns `["wp-content"]` after activation; `_read_allowlist` returns `[]`; `_redaction_config` returns the default map.
- **SC-004**: The "File Manager" tab appears at `admin.php?page=acrossai-settings` alongside existing tabs; each of its three panels persists changes to the correct option via the REST endpoints.
- **SC-005**: PHPUnit full suite green (delta +N new tests, no warnings on the 6-baseline count); PHPStan level 8 clean; PHPCS WPCS strict clean.
- **SC-006**: Sibling plugin `acrossai-buddyboss` continues to load without error (evidence that `Ability_Definition` was not touched).

## Assumptions

- The redactor's built-in pattern regexes are stable enough that hardcoding them is acceptable for MVP; user-configurable custom regexes are deferred.
- Two-level tree UI is sufficient for the common case; a full recursive explorer is out of scope.
- `list-directory` and `file-info` remain ungated because they return metadata only, not file contents.
- Sibling `acrossai-buddyboss` and other plugins that call `file-manager/*` abilities directly will see the new refusals if they operate outside the allowlist. Their integration continues to work if they only touch `wp-content`.
