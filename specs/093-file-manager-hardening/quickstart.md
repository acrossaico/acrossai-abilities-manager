# Quickstart — Feature 093 verification

Reproducible verification of every FR in the spec. Runs against a local WordPress site with the plugin active and the twelve `acrossai_file_manager_*` options seeded (activator does this automatically on first activation after PR #144).

Every probe below uses either the WP-CLI `wp ability call` command or the MCP adapter `execute-ability` tool. Pick whichever is more convenient.

---

## Setup (one-time)

```bash
# 1. Verify the twelve option keys are seeded
wp option list --search='acrossai_file_manager_*' --path=/path/to/wp

# Expected: 12 rows returned. If fewer, deactivate + reactivate the plugin
# to trigger the activator's seed_file_manager_settings() again.

# 2. Prepare a probe workspace under the (default) write allowlist
wp ability call file-manager/create-directory '{"path":"wp-content/uploads/acrossai-probe"}'
```

---

## FR-002 · `extension_blocked` — every write ability

```bash
# Tighten the dangerous-extensions list to just "exe"
wp option update acrossai_file_manager_dangerous_extensions '["exe"]' --format=json

# Expect a refusal envelope on each of the five write abilities
wp ability call file-manager/create-file '{"path":"wp-content/uploads/acrossai-probe/foo.exe","content":"x"}'
wp ability call file-manager/edit-file   '{"path":"wp-content/uploads/acrossai-probe/foo.exe","content":"x"}'
wp ability call file-manager/append-file '{"path":"wp-content/uploads/acrossai-probe/foo.exe","content":"x"}'
wp ability call file-manager/copy-file   '{"source":"wp-content/uploads/acrossai-probe/hello.txt","destination":"wp-content/uploads/acrossai-probe/hello.exe"}'
wp ability call file-manager/move-file   '{"source":"wp-content/uploads/acrossai-probe/hello.txt","destination":"wp-content/uploads/acrossai-probe/hello.exe"}'

# Expected on each: success:false, blocked_reason:"extension_blocked", extension:"exe"
```

---

## FR-003 · `double_extension_blocked`

```bash
wp option update acrossai_file_manager_block_double_extensions 1
wp ability call file-manager/create-file '{"path":"wp-content/uploads/acrossai-probe/foo.php.jpg","content":"x"}'
# Expected: success:false, blocked_reason:"double_extension_blocked", basename:"foo.php.jpg"
```

---

## FR-004 · `htaccess_directive_blocked` — create-file, edit-file, append-file semantics

```bash
wp option update acrossai_file_manager_htaccess_directive_scan 1
# Note: write allowlist default is ["wp-content"], so a plain wp-content/.htaccess
# is inside the allowlist and enforceable.

wp ability call file-manager/create-file '{"path":"wp-content/.htaccess","content":"AddType text/plain .foo"}'
# Expected: blocked_reason:"htaccess_directive_blocked", directive:"AddType"

# Confirm append-file scans APPENDED content only:
wp option update acrossai_file_manager_htaccess_directive_scan 0
wp ability call file-manager/create-file '{"path":"wp-content/.htaccess","content":"# existing SetHandler already here\n"}'
wp option update acrossai_file_manager_htaccess_directive_scan 1
wp ability call file-manager/append-file '{"path":"wp-content/.htaccess","content":"# harmless comment\n"}'
# Expected: success:true — the appended content is clean; existing SetHandler ignored.
wp ability call file-manager/append-file '{"path":"wp-content/.htaccess","content":"php_value display_errors on\n"}'
# Expected: blocked_reason:"htaccess_directive_blocked", directive:"php_value"
```

---

## FR-005 · `filename_sanitize_failed`

```bash
wp option update acrossai_file_manager_sanitize_filename_check 1
wp ability call file-manager/create-file '{"path":"wp-content/uploads/acrossai-probe/weird name.txt","content":"x"}'
# Expected: blocked_reason:"filename_sanitize_failed", input:"weird name.txt", sanitized:"weird-name.txt"
```

---

## FR-006 · `write_size_exceeded` — including append-file's new_size semantics

```bash
wp option update acrossai_file_manager_write_max_bytes 5242880  # 5 MiB

# Six-MiB write refused
wp ability call file-manager/create-file '{"path":"wp-content/uploads/acrossai-probe/big.txt","content":"'"$(head -c 6291456 /dev/urandom | base64 | head -c 6291456)"'"}'
# Expected: blocked_reason:"write_size_exceeded", size:6291456, max_bytes:5242880

# 4 MiB existing + 2 MiB appended = refused (new_size accounting)
# Reset cap to allow the setup write:
wp option update acrossai_file_manager_write_max_bytes 104857600
wp ability call file-manager/create-file '{"path":"wp-content/uploads/acrossai-probe/big.txt","content":"'"$(head -c 4194304 /dev/urandom | base64 | head -c 4194304)"'"}'
wp option update acrossai_file_manager_write_max_bytes 5242880
wp ability call file-manager/append-file '{"path":"wp-content/uploads/acrossai-probe/big.txt","content":"'"$(head -c 2097152 /dev/urandom | base64 | head -c 2097152)"'"}'
# Expected: blocked_reason:"write_size_exceeded", size:6291456, max_bytes:5242880
```

---

## FR-007 · `filename_strict_blocked`

```bash
wp option update acrossai_file_manager_strict_filename_filter 1
wp ability call file-manager/create-file '{"path":"wp-content/uploads/acrossai-probe/c99-report.txt","content":"x"}'
# Expected: blocked_reason:"filename_strict_blocked", marker:"c99"
```

---

## FR-008 · `mime_type_blocked` — including always-allowed list

```bash
wp option update acrossai_file_manager_mime_type_check 1

# Unknown extension → refused
wp ability call file-manager/create-file '{"path":"wp-content/uploads/acrossai-probe/x.xyz","content":"x"}'
# Expected: blocked_reason:"mime_type_blocked", extension:"xyz"

# Always-allowed extension → succeeds even with check on
wp ability call file-manager/create-file '{"path":"wp-content/uploads/acrossai-probe/notes.md","content":"# hi"}'
# Expected: success:true
```

---

## FR-010, FR-011 · `sensitive_read_blocked` — including ordering vs. read allowlist

```bash
# Setup: unrestricted read allowlist (default), denylist contains .env and *.key
wp option update acrossai_file_manager_read_allowlist '[]' --format=json
wp option update acrossai_file_manager_sensitive_read_denylist '[".env","id_rsa","*.key","*.pem"]' --format=json

# Drop probe files
wp ability call file-manager/create-file '{"path":"wp-content/uploads/acrossai-probe/.env","content":"SECRET=xxx"}'
wp ability call file-manager/create-file '{"path":"wp-content/uploads/acrossai-probe/backup.key","content":"-----BEGIN"}'

# Refused by denylist
wp ability call file-manager/read-file '{"path":"wp-content/uploads/acrossai-probe/.env"}'
# Expected: blocked_reason:"sensitive_read_blocked", basename:".env", matched_pattern:".env"

wp ability call file-manager/read-file '{"path":"wp-content/uploads/acrossai-probe/backup.key"}'
# Expected: blocked_reason:"sensitive_read_blocked", basename:"backup.key", matched_pattern:"*.key"

# Allowlist wins when both would refuse
wp option update acrossai_file_manager_read_allowlist '["wp-content/plugins"]' --format=json
wp ability call file-manager/read-file '{"path":"wp-content/uploads/acrossai-probe/.env"}'
# Expected: blocked_reason:"path_not_allowed_for_read" — allowlist refusal wins, not denylist
```

---

## FR-012 · Untouched abilities behave identically

```bash
# read-debug-log, read-wp-config, delete-file — all should behave exactly as before
wp ability call file-manager/read-debug-log '{}'
wp ability call file-manager/read-wp-config '{}'
# Expected: same output as pre-093
```

---

## FR-015, FR-016 · REST + panel banner updates

Load `admin.php?page=acrossai-settings&tab=file-manager` in the browser:

- Content Filters panel: yellow `notice-warning` banner is GONE. A small `notice-info` under the extension list reads "This list now gates create-file / edit-file / append-file / copy-file / move-file."
- Backup & Audit panel: yellow banner remains, text references "094-file-manager-audit-log".
- Inspect REST GET `/wp-json/acrossai/v1/file-manager-settings/content-filters` → `scaffold_only: false`.
- Inspect REST GET `/wp-json/acrossai/v1/file-manager-settings/backup-audit` → `scaffold_only: true`, `follow_up_spec: "094-file-manager-audit-log"`.

---

## Cleanup

```bash
# Restore defaults
wp option update acrossai_file_manager_dangerous_extensions '["exe","sh","bat","cmd","com","scr","cgi","pl","py","phar"]' --format=json
wp option update acrossai_file_manager_block_double_extensions 1
wp option update acrossai_file_manager_htaccess_directive_scan 1
wp option update acrossai_file_manager_sanitize_filename_check 1
wp option update acrossai_file_manager_write_max_bytes 10485760
wp option update acrossai_file_manager_sensitive_read_denylist '[".env",".env.local",".env.production",".env.development","id_rsa","id_dsa","authorized_keys","*.key","*.pem","*.p12","*.pfx","*.crt"]' --format=json
wp option update acrossai_file_manager_strict_filename_filter 0
wp option update acrossai_file_manager_mime_type_check 0
wp option update acrossai_file_manager_read_allowlist '[]' --format=json

# Wipe probe workspace
wp ability call file-manager/delete-directory '{"path":"wp-content/uploads/acrossai-probe","recursive":true,"confirm":true}'
```

---

## Automated verification

- `./vendor/bin/phpunit --filter Test_Feature_093_Hardening_Enforcement` — per-ability PHPUnit coverage
- `./vendor/bin/phpunit` — full suite, expect 1806+ passing (baseline preserved)
- `./vendor/bin/phpcs -n includes/Abilities/Utilities/Hardening_Enforcer.php includes/Abilities/FileManager/{Create_File,Edit_File,Append_File,Copy_File,Move_File,Read_File}.php` — PHPCS
- `./vendor/bin/phpstan analyse --no-progress includes/Abilities/Utilities/Hardening_Enforcer.php includes/Abilities/FileManager/{Create_File,Edit_File,Append_File,Copy_File,Move_File,Read_File}.php` — PHPStan level 8
- `npm run build` — rebuild the file-manager-settings JS bundle to pick up the panel banner edits
