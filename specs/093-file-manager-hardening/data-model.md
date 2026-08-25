# Data Model — Feature 093

No new persisted entities. This feature reads the twelve `acrossai_file_manager_*` option keys already seeded by PR #144. The runtime shape of the read-path snapshot and the ability-response envelopes are documented below for reference.

---

## Runtime entity — `HardeningSnapshot` (in-memory, per-call)

The value returned by `Hardening_Settings::get_content_filters()` is treated as a value object for the duration of one ability call. Shape:

| Field | Type | Range / values | Semantic |
|---|---|---|---|
| `dangerous_extensions` | `string[]` | ≤100 entries, each `[a-z0-9]{1,16}` | Extension blocklist; case-insensitive match against target extension |
| `block_double_extensions` | `bool` | true/false | Refuse target basenames matching `/\.(php\|phtml\|phar)\.[^.]+$/i` |
| `htaccess_directive_scan` | `bool` | true/false | Refuse `.htaccess` writes whose content contains any of the six directive names (case-insensitive substring) |
| `sanitize_filename_check` | `bool` | true/false | Refuse when `sanitize_file_name($basename) !== $basename` |
| `write_max_bytes` | `int` | 1024 ≤ n ≤ 104857600 | Cap on write size (bytes) |
| `sensitive_read_denylist` | `string[]` | ≤200 entries, basenames only | Literal or `*.EXT` glob; deny reads |
| `strict_filename_filter` | `bool` | true/false | Refuse basenames containing any of nine webshell markers |
| `mime_type_check` | `bool` | true/false | Refuse when `wp_check_filetype` returns empty type AND extension not in always-allowed set |

The full snapshot also carries the four backup+audit fields (`audit_log_enabled`, `audit_log_retention_days`, `backup_enabled`, `backup_retention_days`) but this feature does not read them; feature 094 will.

---

## Response envelope — `RefusalEnvelope` (returned by abilities)

All eight new `blocked_reason` values share a common shape. Existing envelopes (`path_not_allowed_for_write`, `path_not_allowed_for_read`, `protected_write`, `file_mods_disabled`, etc.) are unchanged and continue to take precedence.

Base fields (present on every refusal):

| Field | Type | Value |
|---|---|---|
| `success` | `bool` | Always `false` |
| `blocked_reason` | `string` | One of the eight new values below |
| `path` | `string` | Absolute path of the target (or destination for copy/move) |
| `message` | `string` | i18n human-readable refusal message |

Per-reason context fields (added on top of the base fields):

| `blocked_reason` | Extra fields | Applies to abilities |
|---|---|---|
| `extension_blocked` | `extension: string` | create-file, edit-file, append-file, copy-file, move-file |
| `double_extension_blocked` | `basename: string` | create-file, edit-file, append-file, copy-file, move-file |
| `htaccess_directive_blocked` | `directive: string` | create-file, edit-file, append-file, copy-file, move-file |
| `filename_sanitize_failed` | `input: string, sanitized: string` | create-file, edit-file, append-file, copy-file, move-file |
| `write_size_exceeded` | `size: int, max_bytes: int` | create-file, edit-file, append-file, copy-file, move-file |
| `filename_strict_blocked` | `marker: string` | create-file, edit-file, append-file, copy-file, move-file |
| `mime_type_blocked` | `extension: string` | create-file, edit-file, append-file, copy-file, move-file |
| `sensitive_read_blocked` | `basename: string, matched_pattern: string` | read-file |

Each field is declared in the affected ability's `output_schema.properties` so the ability adapter's schema validator accepts the refusal envelope. See `contracts/blocked-reason-envelopes.md` for the full schema patch per ability.

---

## Ability × check matrix (informational)

For each of the six affected abilities, this is the ordered list of checks the enforcer runs, after `File_Mods_Guard` and `Path_Allowlist_Guard` have already passed:

| Check → Ability | create-file | edit-file | append-file | copy-file | move-file | read-file |
|---|---|---|---|---|---|---|
| dangerous_extensions | ✅ target | ✅ target | ✅ target | ✅ dest | ✅ dest | — |
| block_double_extensions | ✅ target | ✅ target | ✅ target | ✅ dest | ✅ dest | — |
| htaccess_directive_scan | ✅ target + content | ✅ target + content | ✅ target + APPENDED content only | ✅ dest + source content | ✅ dest + source content | — |
| sanitize_filename_check | ✅ target | ✅ target | ✅ target | ✅ dest | ✅ dest | — |
| write_max_bytes | ✅ strlen(content) | ✅ strlen(content) | ✅ current + appended | ✅ source size | ✅ source size | — |
| strict_filename_filter | ✅ target | ✅ target | ✅ target | ✅ dest | ✅ dest | — |
| mime_type_check | ✅ target | ✅ target | ⚠ skip | ✅ dest | ✅ dest | — |
| sensitive_read_denylist | — | — | — | — | — | ✅ target |

⚠ `mime_type_check` is skipped on `append-file` because appending doesn't change the extension — the target already exists with a known extension and re-checking it would either fire on every append to a legitimate custom-mime file or produce false negatives if `wp_check_filetype` reports differently for empty content vs. existing content.
