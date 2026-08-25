# Planning: File Manager Hardening — enforcement pass (Feature 093)

Wire the ten already-stored option keys from PR #144 into the six File Manager
abilities that should consult them. The scaffold shipped without enforcement so
the admin UI could ship first; this feature closes the loop by making every
knob actually gate the abilities its label promises.

Zero new option keys, zero new admin UI panels, zero new REST endpoints —
this is a pure runtime enforcement pass over abilities that already exist.
When it ships, the "scaffold only" notice-warning at the top of the two
scaffold panels goes away.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "093-file-manager-hardening"

# 2. Specify
/speckit.specify "Wire the ten already-stored hardening option keys from the
PR #144 scaffold into the six File Manager abilities that should consult them.
Every knob in the Content Filters panel and one toggle in the Backup & Audit
panel is currently persisted but never read by any ability — this feature
closes that gap.

Ability → option mapping (all options live under acrossai_file_manager_* in
wp_options and are read via Hardening_Settings::get_content_filters()):

  file-manager/create-file:
    - dangerous_extensions      → refuse when target extension is in the list
    - block_double_extensions   → refuse when basename matches /\\.(php|phtml|phar)\\.[^.]+\$/i
    - htaccess_directive_scan   → refuse when basename == .htaccess AND content contains
                                  any of AddType, SetHandler, php_value, php_flag,
                                  auto_prepend, auto_append
    - sanitize_filename_check   → refuse when sanitize_file_name(basename) !== basename
    - write_max_bytes           → refuse when strlen(content) > cap
    - strict_filename_filter    → refuse when basename contains any of c99, r57, wso,
                                  b374k, weevely, shell, alfa, bypass, backdoor
    - mime_type_check           → refuse when wp_check_filetype(basename) has empty
                                  'type' AND extension not in an always-allowed list
                                  (php, txt, log, json, xml, css, js, md, html, htm,
                                  htaccess)

  file-manager/edit-file:
    - Same seven checks as create-file (target may exist or be created).

  file-manager/append-file:
    - dangerous_extensions, block_double_extensions, sanitize_filename_check,
      strict_filename_filter, mime_type_check — same rules on the target basename.
    - htaccess_directive_scan — check the APPENDED content only (not the existing
      file content), since append doesn't overwrite the existing directives.
    - write_max_bytes — check new_size (existing + appended), not just appended
      bytes, so a series of small appends still caps at the same limit.

  file-manager/copy-file:
    - Same seven checks as create-file, applied to the DESTINATION basename.
    - Content check for htaccess_directive_scan reads the source file content
      (since copy preserves content byte-for-byte).
    - write_max_bytes checked against source file size.

  file-manager/move-file:
    - Same seven checks as copy-file, applied to the DESTINATION basename.
    - htaccess_directive_scan reads source content (preserved by move).
    - write_max_bytes checked against source file size.

  file-manager/read-file:
    - sensitive_read_denylist → refuse when basename matches any list entry.
                                Entries are either literal basenames (case-sensitive
                                match: .env, id_rsa, authorized_keys) or *.EXT globs
                                (*.key, *.pem, *.p12, *.pfx, *.crt).
                                Runs AFTER the existing read allowlist check —
                                sensitive-read is an additional deny, not a bypass.
                                Applies to Read_File only; Read_Debug_Log's target
                                is fixed (wp-content/debug.log) and can never match
                                a sensitive-read entry.

Every refusal follows the existing blocked_reason envelope shape used by
Path_Allowlist_Guard: {success:false, blocked_reason:'<reason>', path:'<abs>',
message:'<i18n>', ...option_context}. Each new blocked_reason value is
declared in the ability's output_schema and included in the human-readable
message so admins can look up what happened without reading server logs.

New blocked_reason values (one per check):
  extension_blocked            (context: {extension: 'exe'})
  double_extension_blocked     (context: {basename: 'foo.php.jpg'})
  htaccess_directive_blocked   (context: {directive: 'php_value'})
  filename_sanitize_failed     (context: {input: 'weird name', sanitized: 'weird-name'})
  write_size_exceeded          (context: {size: 12345678, max_bytes: 10485760})
  filename_strict_blocked      (context: {marker: 'c99'})
  mime_type_blocked            (context: {extension: 'xyz'})
  sensitive_read_blocked       (context: {basename: '.env', matched_pattern: '.env'})

Also update the two Backup & Audit panels' scaffold banner and the two Content
Filters panels' scaffold banner. The Backup & Audit knobs remain scaffold-only
(feature 094 covers those); update its banner to reference 094 more explicitly.

Success criteria: every enabled option produces the expected refusal envelope
in a live probe; every disabled option is a no-op; the seven happy-path
scenarios for each of the six abilities still succeed."

# 3. Plan / Tasks / Implement (run in order as needed)
/speckit.plan
/speckit.tasks
/speckit.implement
```

---

## Background — what is already done; do NOT redo it

| # | Fact | How to verify |
|---|------|---------------|
| B-1 | Twelve `acrossai_file_manager_*` option keys and their defaults were seeded in `AcrossAI_Activator::seed_file_manager_settings()` by PR #144 | `grep -c 'add_option( Hardening_Settings::' includes/AcrossAI_Activator.php` → 12 |
| B-2 | `Hardening_Settings` utility class exists with typed getters (`get_content_filters()`, `get_backup_audit()`) and sanitising setters (`set_content_filters()`, `set_backup_audit()`). Reason strings for skipped entries: `not_a_string`, `invalid_format`, `path_segment_not_allowed`, `control_char`, `duplicate`, `list_cap_reached` | `ls includes/Abilities/Utilities/Hardening_Settings.php` |
| B-3 | REST routes `/acrossai/v1/file-manager-settings/content-filters` and `/acrossai/v1/file-manager-settings/backup-audit` (GET + POST) are live. POST responses include `{config, skipped, scaffold_only:true, follow_up_spec, limits}` | `grep '/content-filters\|/backup-audit' includes/Abilities/Rest/File_Manager_Settings_Controller.php` |
| B-4 | React panels `ContentFiltersPanel.jsx` and `BackupAuditPanel.jsx` render alongside the three live panels; each wears a `notice-warning` scaffold banner. The Content Filters panel surfaces per-entry rejection reasons after Save via the `skipped` array | `ls src/js/file-manager-settings/components/{ContentFiltersPanel,BackupAuditPanel}.jsx` |
| B-5 | Read allowlist enforcement is handled by `Path_Allowlist_Guard::blocked_read_response()`; write allowlist by `blocked_write_response()`. Both are already called by the ten guard-consuming abilities via `use ... Utilities\\Path_Allowlist_Guard`. This feature adds enforcement AFTER the allowlist gate — sensitive-read is a further deny, not a replacement | `grep -rn 'Path_Allowlist_Guard::' includes/Abilities/FileManager/` |
| B-6 | Item 19 (Create_Zip_Backup File_Mods_Guard) is landing in PR #145 (or already merged by the time 093 starts) — this feature does NOT re-touch that file | `git log --all --oneline --grep 'create-zip-backup'` |
| B-7 | Existing output_schemas for the six affected abilities already declare `blocked_reason:string`, `path:string`, and `allowed_roots:array` (from the PR #143 fix commit). New context keys must be added per new blocked_reason value | `grep 'allowed_roots' includes/Abilities/FileManager/Create_File.php` |

---

## Constraints

### Semantic
- **Sensitive-read denylist runs AFTER read allowlist.** If a path is refused by the allowlist, that refusal wins (returns `path_not_allowed_for_read`). Only if the allowlist would permit the read does the denylist get a chance to refuse it (returns `sensitive_read_blocked`).
- **Case sensitivity.** Literal denylist entries are matched case-sensitively against the target basename. `*.EXT` glob entries case-insensitively match the extension. So `id_rsa` blocks `id_rsa` but not `ID_RSA` — matching Unix filesystem conventions where secret keys are stored with predictable exact names.
- **`htaccess_directive_scan` and `append-file`.** Scan the APPENDED content only. If the existing file already contains a dangerous directive, the ability should NOT refuse — otherwise every append to a WordPress-installed `.htaccess` would fail forever.
- **`write_max_bytes` and `append-file`.** Cap `new_size = current_size + strlen(appended)`, not `strlen(appended)` alone.
- **`mime_type_check` "always allowed" list.** Even when the check is on, refuse only when `wp_check_filetype()` returns empty AND the extension isn't a well-known code/text extension. Config-only files (php, txt, log, json, xml, css, js, md, html, htm, htaccess) should never be gated by MIME. This matches the reference plugin's behaviour and prevents `mime_type_check` from breaking the write allowlist's mu-plugins deploy use case.
- **Bool-flag toggles are read-once per ability call.** No caching between calls — an admin flipping the toggle takes effect on the next call.

### Compatibility
- **No breaking change to existing envelopes.** New `blocked_reason` values are added; existing `path_not_allowed_for_write` / `protected_write` behaviours are unchanged.
- **When a new option is disabled, no code path changes.** An empty `dangerous_extensions` list is a no-op; `block_double_extensions:false` is a no-op. This is important so admins upgrading with defaults get zero behaviour change beyond what defaults dictate.
- **Default values ship as they were seeded.** No changes to `DEFAULT_*` constants in `Hardening_Settings`.

### UI update
- **Content Filters panel** — remove the `notice-warning` scaffold banner (`data.scaffold_only` will still be `true` in the REST response for one release cycle; the panel can simply stop rendering it). Add a `notice-info` next to the extension list saying "This list now gates create-file / edit-file / append-file / copy-file / move-file."
- **Backup & Audit panel** — keep the scaffold banner but update its text to make clear that only THIS panel remains scaffold, not the whole tab. Point at spec 094 for enforcement.
- **REST GET response** — flip `scaffold_only:true` → `scaffold_only:false` in the content-filters endpoint. Keep it `true` in backup-audit until 094 ships.

---

## Non-goals

- **No new Media namespace changes.** The Upload Media Abilities section (feature 046 absorption) is unrelated — Mime_Types_Store is not merged into these checks.
- **No changes to Path_Allowlist_Guard.** Sensitive-read denylist is a separate check that runs AFTER the allowlist, not a modification to it.
- **No changes to Delete_File / Delete_Directory / Create_Directory / File_Info.** Deletion doesn't accept content and doesn't have a filename an admin would author (target already exists). Directory operations don't have file contents. File_Info is read-only stat metadata. These four abilities remain untouched.
- **No changes to `Read_Debug_Log`.** Its target is fixed at `wp-content/debug.log`; no sensitive-read match possible.
- **No changes to `Read_Wp_Config` / `Get_Wp_Config_Constant`.** These have their own protected-constant list and don't return raw file contents.
- **No changes to any zip-related ability.** Zip flows go through `Backups_Storage` / `Zip_Target_Resolver` — different concern from single-file writes.
- **No enforcement of the four Backup & Audit options.** Those are spec 094 scope.
- **No new tests for the sanitizers** — the PR #144 verbose sanitizers already ship with drop-reason coverage. New PHPUnit added by this feature is per-ability enforcement coverage only.

---

## Test approach (for the /speckit.plan phase to expand)

Two test tiers:

1. **PHPUnit** — one `Test_Feature_093_Hardening_Enforcement.php` file with per-ability sections:
   - Positive: default settings (all extension blocks on, size cap at 10 MB, no denylist) → happy path succeeds
   - Negative per option: one refusal test per new blocked_reason value against each of the six abilities that consume it

2. **Live MCP probes** — one round-trip per option via `options/update-option` + a matching file-manager call, confirming the blocked_reason envelope. Same pattern as the live verification that shipped with PR #143. Not automated — run manually before merge.

---

## Recommended sequence (for /speckit.tasks to expand)

1. Add each new blocked_reason to the six affected abilities' `output_schema`. Do this first so refusal envelopes validate cleanly.
2. Add a small `Hardening_Enforcer` utility class (or hook into an existing utility) that centralises the seven check functions. Every ability calls `Hardening_Enforcer::check_write($path, $content)` and `check_read($path)` and gets back `null` or a `{blocked_reason, message, ...}` array. Keeps per-ability call sites tiny.
3. Wire the enforcer into each of the six abilities, after the existing Path_Allowlist_Guard call.
4. Update the Content Filters panel + REST GET to drop the scaffold banner.
5. Update the Backup & Audit panel banner to reference 094.
6. PHPUnit + live probes.

---

## Open questions

- **Should `strict_filename_filter` match on substring or on word boundary?** Reference plugin uses substring (`stripos($name, 'shell') !== false` would block `shell.php` AND `hellshellman.txt`). Word-boundary would reduce false positives but breaks the "strict = more aggressive" spirit. Recommend: keep substring, since the toggle is off by default and documented as "may produce false positives."
- **Should `mime_type_check` apply to `append-file`?** Appending to an existing file arguably shouldn't need a MIME re-check — the extension didn't change. Recommend: apply to create/edit/copy/move only, skip on append.
- **What context should the enforcer log when the audit-log system lands in 094?** Recommend: leave a `// TODO(feature-094): log this refusal` comment at each refusal point rather than pre-emptively hooking a not-yet-existent logger.
