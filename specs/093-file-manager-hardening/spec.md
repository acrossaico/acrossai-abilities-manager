# Feature Specification: File Manager Hardening (Enforcement Pass)

**Feature Branch**: `093-file-manager-hardening`
**Created**: 2026-08-26
**Status**: Draft
**Input**: User description — see `docs/planning/093-file-manager-hardening.md` for the full briefing. Summary: wire the ten hardening option keys shipped as UI scaffold in PR #144 into the six File Manager abilities that should consult them (create-file, edit-file, append-file, copy-file, move-file, read-file). No new options, no new admin UI panels, no new REST endpoints — this is a pure runtime enforcement pass.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Site admin disables specific extensions and gets refusals (Priority: P1)

An admin operating an AcrossAI-enabled WordPress site tightens the Content Filters panel to block `.exe`, `.sh`, and `.bat` writes. The next time an AI client attempts to write a file with any of those extensions through a file-manager ability, the ability returns a structured refusal envelope naming the blocked extension. The admin sees the refusal in whatever log or dashboard their client surfaces and understands exactly what was denied.

**Why this priority**: This is the core value of the feature. The whole point of PR #144 was to let admins express these policies; today those policies are stored but never consulted. Without this story, the settings tab is a lie.

**Independent Test**: On a live site, POST to the File Manager settings tab to set `dangerous_extensions:['exe']`, then invoke `file-manager/create-file` with `path:'wp-content/uploads/probe.exe'`. Expect `{success:false, blocked_reason:'extension_blocked', extension:'exe', path:'…', message:'…'}`. Invoke again with `path:'wp-content/uploads/probe.txt'` and expect success.

**Acceptance Scenarios**:

1. **Given** an admin has added `exe` to the dangerous-extensions list, **When** an AI client calls `create-file` targeting `probe.exe`, **Then** the ability returns `blocked_reason:'extension_blocked'` with the extension in the response payload and the file is NOT written to disk.
2. **Given** the same setting, **When** the client calls `create-file` targeting `probe.txt`, **Then** the file is written normally with no refusal.
3. **Given** the setting is later removed, **When** the client retries `create-file` on `probe.exe`, **Then** the file is written normally — bool-flag toggles are read on each ability call, no cache.

---

### User Story 2 — Admin blocks reads of secrets even inside the read allowlist (Priority: P1)

An admin sets the Read allowlist to permit reads under `wp-content/` (so an AI can inspect debug logs, uploads, plugin READMEs, etc.), but adds `.env`, `id_rsa`, `*.key`, `*.pem` to the sensitive-read denylist. When the AI later tries to read a stray `.env` file that a plugin dropped into `wp-content/`, the ability refuses even though the allowlist would otherwise permit it. The admin never has to worry about accidentally exposing a secret via a permissive allowlist.

**Why this priority**: Without this story, admins choose between "unrestricted reads" (leaks any stray secret) and "narrow allowlist" (too painful to use). The denylist unlocks safe permissive allowlisting.

**Independent Test**: With `acrossai_file_manager_read_allowlist=['wp-content']` and `acrossai_file_manager_sensitive_read_denylist=['.env']`, drop a file at `wp-content/.env` and invoke `file-manager/read-file` with `path:'wp-content/.env'`. Expect `{success:false, blocked_reason:'sensitive_read_blocked', basename:'.env', matched_pattern:'.env', path:'…'}`.

**Acceptance Scenarios**:

1. **Given** the read allowlist permits `wp-content/` and the sensitive-read denylist contains `.env`, **When** the AI reads `wp-content/.env`, **Then** the ability returns `blocked_reason:'sensitive_read_blocked'` with `matched_pattern:'.env'`.
2. **Given** the read allowlist does NOT permit `wp-content/` (empty allowlist or narrower entry), **When** the AI reads `wp-content/.env`, **Then** the ability returns `blocked_reason:'path_not_allowed_for_read'` — the allowlist refusal wins because it fires first.
3. **Given** the denylist contains `*.key`, **When** the AI reads `wp-content/uploads/backup.key`, **Then** the ability refuses with `matched_pattern:'*.key'`.
4. **Given** the denylist contains `id_rsa`, **When** the AI reads `wp-content/ID_RSA`, **Then** the ability succeeds — literal entries are case-sensitive.

---

### User Story 3 — Admin caps write size to prevent runaway writes (Priority: P2)

An admin sets `write_max_bytes` to 5 MiB (below the 10 MiB default) because the site is on constrained shared hosting. When an AI client attempts to write a 12 MiB file, the ability refuses cleanly before any bytes reach disk. The admin sees exactly what was refused and by how much they exceeded the cap.

**Why this priority**: Protects the site from accidental payload floods (an LLM streaming a 400 MiB debug dump into a file, for example). Less critical than P1 stories because size errors are recoverable and rare, but the cap is cheap to implement and admins will thank us the first time it saves them a disk-full incident.

**Independent Test**: Set `acrossai_file_manager_write_max_bytes=5242880`, then invoke `file-manager/create-file` with `content` containing 6 MiB of ASCII. Expect `{success:false, blocked_reason:'write_size_exceeded', size:6291456, max_bytes:5242880}`.

**Acceptance Scenarios**:

1. **Given** the cap is 5 MiB, **When** the AI writes 6 MiB via `create-file`, **Then** the ability refuses with `size` and `max_bytes` in the response and no file is created.
2. **Given** the cap is 5 MiB and an existing file is 4 MiB, **When** the AI appends 2 MiB via `append-file`, **Then** the ability refuses (`new_size = 6 MiB > cap`), NOT `size = 2 MiB < cap`.
3. **Given** the cap is 5 MiB, **When** the AI copies a 6 MiB file via `copy-file`, **Then** the ability refuses using the source-file size as the cap check.

---

### User Story 4 — Admin gets consistent refusals across every write ability (Priority: P2)

The same seven content-filter checks (dangerous extensions, double extensions, sanitize-name, htaccess-directive, strict-filename, mime-type, write-size) apply consistently across `create-file`, `edit-file`, `append-file`, `copy-file`, `move-file`. An admin does not need to remember which ability is gated by which check — the answer is "all of them, all the checks that make sense for that operation".

**Why this priority**: Consistency dramatically simplifies the admin's mental model. If any single ability had a different set of checks, admins would need to memorise a matrix. With uniform enforcement the answer is always the same.

**Independent Test**: For each of the five write abilities, set one enabled filter (say `dangerous_extensions:['exe']`), invoke the ability with a `.exe` target, and expect the same `blocked_reason:'extension_blocked'` envelope. Verify the destination-basename semantics for copy-file / move-file (checks apply to destination, not source).

**Acceptance Scenarios**:

1. **Given** `dangerous_extensions:['exe']`, **When** `edit-file` targets `probe.exe`, **Then** it refuses with `extension_blocked`.
2. **Given** `dangerous_extensions:['exe']`, **When** `copy-file` copies `probe.txt → probe.exe`, **Then** it refuses (destination basename triggers the check).
3. **Given** `dangerous_extensions:['exe']`, **When** `move-file` moves `probe.txt → probe.exe`, **Then** it refuses.
4. **Given** `dangerous_extensions:['exe']`, **When** `append-file` targets an existing `probe.exe`, **Then** it refuses.
5. **Given** `block_double_extensions:true`, **When** `create-file` targets `foo.php.jpg`, **Then** it refuses with `double_extension_blocked`.

---

### User Story 5 — Admin sees the panel stop wearing the "scaffold only" banner (Priority: P3)

After this feature ships, the Content Filters panel on the File Manager tab no longer shows the yellow "Scaffold only — values save but nothing enforces them yet" banner. Instead it shows a small info note under the extension list confirming that saved values now gate the six affected abilities. The Backup & Audit panel keeps its banner but references feature 094 explicitly.

**Why this priority**: Purely a UX signal to communicate that the setting is live. Non-blocking — the enforcement is what matters — but shipping enforcement without updating the panel leaves admins confused about whether their save actually did anything.

**Independent Test**: Reload the File Manager tab in wp-admin after this feature ships. Confirm the Content Filters panel shows a `notice-info` (not `notice-warning`) and the Backup & Audit panel mentions "feature 094" in its scaffold banner.

**Acceptance Scenarios**:

1. **Given** the feature has shipped, **When** an admin loads `admin.php?page=acrossai-settings&tab=file-manager`, **Then** the Content Filters panel shows `notice-info` with text "This list now gates create-file / edit-file / append-file / copy-file / move-file."
2. **Given** the same state, **When** the admin scrolls to Backup & Audit, **Then** the yellow scaffold banner remains but its text references `094-file-manager-audit-log` (not `093`).

---

### Edge Cases

- **Empty option → no-op.** Every filter is opt-in. An empty `dangerous_extensions` list is a no-op (no extensions blocked); `block_double_extensions:false` is a no-op; empty `sensitive_read_denylist` is a no-op. When ALL filters are disabled the abilities behave identically to today.
- **Existing `.htaccess` with dangerous directives during `append-file`.** The scanner checks the APPENDED content only, never re-scans existing file content. A WordPress-installed `.htaccess` that already contains `SetHandler` directives does not fail forever.
- **`.htaccess` scan and comments.** The scanner uses case-insensitive substring match for each directive name. `AddType` inside a `# comment` is still refused (opt-out via comment would require a real parser and admins can just disable the toggle).
- **Sensitive-read denylist with a path segment.** Entries are basenames only. The sanitizer already rejects entries containing `/` or `\` before they reach persistence, so the runtime never sees a path-segment entry.
- **`mime_type_check` "always allowed" list.** Even with the check on, extensions in `{php, txt, log, json, xml, css, js, md, html, htm, htaccess}` are never refused by MIME. Prevents the check from breaking mu-plugins deploy use cases the write allowlist explicitly permits.
- **`filename_sanitize_failed` and `create_dirs`.** When `create-file` is called with `create_dirs:true` and any intermediate directory name would fail sanitize_file_name, that intermediate directory name triggers the refusal (not the leaf file). Response includes `input` (the failing segment) and `sanitized` (what WordPress would rename it to).
- **`write_size_exceeded` and `edit-file` overwrite.** The cap check uses the NEW content size, not the existing file size — an admin can overwrite a 20 MiB file with a 1 KiB replacement even when cap is 5 MiB.
- **`copy-file` / `move-file` when source is above cap.** Source-size cap check runs BEFORE the actual copy/move — the destination never gets a partially-written file.
- **`sanitize_filename_check` and WP-adjacent dotfiles.** `.htaccess`, `.htpasswd`, `.user.ini` are exempted from the roundtrip check so admins can write them. Other dotfiles (`.gitignore`, `.env`, `.gitattributes`, etc.) are still refused when the check is on — admins who need those disable the check.
- **`sensitive_read_blocked` for a symlinked path.** Basename matching uses the original request path, not any post-realpath resolved target. An admin who symlinks `wp-content/uploads/report.txt → /etc/passwd` cannot read via the report.txt name if the allowlist permits it — but this is the read allowlist's realpath scope check's job, not the denylist's.
- **All five write abilities with `filename_strict_blocked`.** Substring match (not word-boundary) for consistency with the reference plugin. A file called `alfa-testing.txt` gets blocked when the toggle is on — this is documented in the panel description ("may produce false positives; off by default").
- **DISALLOW_FILE_MODS wins.** File_Mods_Guard fires before any content-filter check. A site with `DISALLOW_FILE_MODS` gets its existing refusal, not a hardening refusal.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST read the eight content-filter options via the existing `Hardening_Settings` utility on every write-ability call. No values are cached across calls.
- **FR-002**: System MUST refuse a write when the target basename's lowercase extension appears in `dangerous_extensions`, returning `blocked_reason:'extension_blocked'` with the offending `extension` in the response.
- **FR-003**: System MUST refuse a write when `block_double_extensions` is true AND the target basename matches `/\.(php|phtml|phar)\.[^.]+$/i`, returning `blocked_reason:'double_extension_blocked'` with the offending `basename` in the response.
- **FR-004**: System MUST refuse a write when `htaccess_directive_scan` is true AND the target basename is `.htaccess` AND the content contains any of `AddType`, `SetHandler`, `php_value`, `php_flag`, `auto_prepend`, `auto_append` (case-insensitive substring match), returning `blocked_reason:'htaccess_directive_blocked'` with the offending `directive` in the response.
- **FR-005**: System MUST refuse a write when `sanitize_filename_check` is true AND `sanitize_file_name(basename) !== basename`, returning `blocked_reason:'filename_sanitize_failed'` with the original `input` and WordPress-`sanitized` variant in the response. **Exception**: legitimate WordPress-adjacent dotfiles (`.htaccess`, `.htpasswd`, `.user.ini`) are exempted from the roundtrip check. Real WP's `sanitize_file_name()` strips leading dots via `trim($filename, '.-_')`, so applying the check literally would refuse every valid dotfile and starve the FR-004 htaccess-directive scan of any target. Mirrors the reference plugin's `$allowed_dotfiles` carve-out.
- **FR-006**: System MUST refuse a write when `strlen(content)` (or `current + appended` on append-file, or source file size on copy/move) exceeds `write_max_bytes`, returning `blocked_reason:'write_size_exceeded'` with `size` and `max_bytes` in the response.
- **FR-007**: System MUST refuse a write when `strict_filename_filter` is true AND the target basename contains any of `c99`, `r57`, `wso`, `b374k`, `weevely`, `shell`, `alfa`, `bypass`, `backdoor` (case-insensitive substring), returning `blocked_reason:'filename_strict_blocked'` with the offending `marker` in the response.
- **FR-008**: System MUST refuse a write when `mime_type_check` is true AND `wp_check_filetype(basename)['type']` is empty AND the extension is not in the always-allowed set `{php, txt, log, json, xml, css, js, md, html, htm, htaccess}`, returning `blocked_reason:'mime_type_blocked'` with the offending `extension` in the response.
- **FR-009**: System MUST apply FR-002 through FR-008 uniformly across `file-manager/create-file`, `file-manager/edit-file`, `file-manager/append-file`, `file-manager/copy-file`, `file-manager/move-file` with the ability-specific content and target-basename rules documented in the briefing (append-file scans appended content only for htaccess-directive; copy/move check destination basename; write-size on append uses current+appended).
- **FR-010**: System MUST refuse a read via `file-manager/read-file` when the target basename matches any `sensitive_read_denylist` entry — literal basenames case-sensitively, `*.EXT` globs case-insensitively — returning `blocked_reason:'sensitive_read_blocked'` with `basename` and `matched_pattern` in the response.
- **FR-011**: System MUST run the sensitive-read denylist check AFTER the existing read-allowlist check. When both would refuse, the allowlist refusal (`path_not_allowed_for_read`) is returned. The denylist only fires when the allowlist would permit.
- **FR-012**: System MUST NOT apply content-filter or sensitive-read checks to `file-manager/read-debug-log`, `file-manager/read-wp-config`, `file-manager/get-wp-config-constant`, or any zip-related ability. These continue to behave exactly as they do today.
- **FR-013**: System MUST declare every new `blocked_reason` value AND its accompanying context keys in the affected ability's `output_schema` so the ability-adapter's schema validator accepts the refusal envelope.
- **FR-014**: System MUST run every content-filter check AFTER the existing `File_Mods_Guard` and `Path_Allowlist_Guard::blocked_write_response()` checks. Existing refusal reasons (`file_mods_disabled`, `file_edit_disabled`, `path_not_allowed_for_write`, `protected_write`) MUST take precedence.
- **FR-015**: System MUST flip the `scaffold_only` field in the `/acrossai/v1/file-manager-settings/content-filters` GET/POST responses from `true` to `false`. The `/backup-audit` endpoint keeps `scaffold_only:true` and its `follow_up_spec` value updates to `094-file-manager-audit-log`.
- **FR-016**: System MUST remove the yellow `notice-warning` scaffold banner from the Content Filters React panel and replace it with a small `notice-info` reading "This list now gates create-file / edit-file / append-file / copy-file / move-file." The Backup & Audit panel keeps its yellow banner but the text references `094-file-manager-audit-log`.
- **FR-017**: System MUST leave all existing tests green (PHPUnit 1806+ pass) AND add per-ability enforcement coverage for every FR-002 through FR-011 rule.

### Key Entities

- **Hardening_Settings** (existing utility, PR #144): source of truth for the ten option values. This feature reads from it; no changes to the utility itself except adding a small helper method surface if the enforcer needs one.
- **Blocked-reason envelope** (existing shape, PR #143 + #144): `{success:false, blocked_reason:string, path:string, message:string, ...option_context}`. This feature adds eight new `blocked_reason` values that follow the same shape.
- **File Manager settings tab** (existing UI, PR #144): the two React panels update to reflect that the Content Filters knobs are now live.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Every one of the eight enabled content-filter options produces the correct refusal envelope in a live probe. Verified by manual MCP probe run before merge.
- **SC-002**: Every one of the eight options, when disabled (empty list or false toggle), causes the ability to behave exactly as it did before this feature — zero regressions in happy-path behaviour.
- **SC-003**: All five affected write abilities and the read ability show consistent enforcement — the same seven checks fire in the same order across `create-file`, `edit-file`, `append-file`, `copy-file`, `move-file`; the sensitive-read denylist fires after the read allowlist on `read-file`.
- **SC-004**: PHPUnit test count stays at 1806+ passing. New `Test_Feature_093_Hardening_Enforcement` suite adds at least 40 assertions covering FR-002 through FR-011.
- **SC-005**: PHPCS + PHPStan clean on every changed file. No new warnings introduced across the whole suite.
- **SC-006**: An admin who saved values into the Content Filters panel before this feature landed sees those values take effect on the first ability call after upgrade — no re-save required.
- **SC-007**: The Content Filters panel shows a `notice-info` (not `notice-warning`) confirming enforcement, and the Backup & Audit panel references feature 094 explicitly.

## Assumptions

- The ten option keys are already seeded by the activator (PR #144). This feature does NOT reseed them — read-then-fall-back-to-default via `Hardening_Settings::get_content_filters()` is the pattern.
- Item 19 (`Create_Zip_Backup` File_Mods_Guard fix) is landing separately in PR #145 and does not overlap with any file this feature touches.
- The existing `Path_Allowlist_Guard::blocked_read_response()` / `blocked_write_response()` shape is stable — this feature adds NEW `blocked_reason` values but does not modify existing ones.
- The `add_settings_error`-style per-entry rejection surface introduced in PR #144 remains the single UX pattern for save-time drops. No new drop reasons are introduced by this feature (the sanitizers already cover all cases; this feature is pure runtime enforcement).
- No new dependencies (Composer packages or JS libraries). Every check uses WordPress core functions (`sanitize_file_name`, `wp_check_filetype`) or built-in PHP (`strpos`, `preg_match`, `strlen`).
- The follow-up spec `094-file-manager-audit-log` will consume the four Backup & Audit options separately. This feature does NOT touch those keys.
