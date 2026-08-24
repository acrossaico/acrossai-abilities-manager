# Quickstart: File Abilities Consolidation (089)

End-to-end verification for feature 089. Run each step on a fresh install with the plugin activated. All commands assume the working directory is `/Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/plugins/acrossai-abilities-manager`.

## Prerequisites

- WordPress 6.9+ install at `/Users/raftaar1191/local-sites/wordpress-7-0/app/public` (Local by Flywheel).
- Plugin installed and network-inactive (or single-site activated).
- WP-CLI available (`wp --info` succeeds).
- MCP connector `claude.ai acrosai` reachable (for the optional MCP steps).

## Step 1 — Confirm the added abilities are registered

```bash
wp ability list --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  | grep -E '^file-manager/(list-directory|copy-file|move-file)'
```

Expect three lines, one per new ability. Empty output means bootstrap wiring is missing.

## Step 2 — Confirm the removed abilities are gone

```bash
wp ability list --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  | grep -E '^(themes/(read-theme-code|edit-theme-file|read-theme-structure)|plugins/(read-plugin-code|read-plugin-structure|manage-plugin-files))$'
```

Expect **no output**. Any hit is a bootstrap-wiring bug.

## Step 3 — Directory listing works on a plugin folder

```bash
wp ability call file-manager/list-directory \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-content/plugins/hello-dolly","max_depth":2}'
```

Expect `{success: true, entries: [...], truncated: false}` with at least the entry for `hello.php`.

## Step 4 — Copy and move inside plugins directory

```bash
# Copy
wp ability call file-manager/copy-file \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"source":"wp-content/plugins/hello-dolly/hello.php","destination":"wp-content/plugins/hello-dolly/hello.copy.php"}'

# Move it to a new name
wp ability call file-manager/move-file \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"source":"wp-content/plugins/hello-dolly/hello.copy.php","destination":"wp-content/plugins/hello-dolly/hello.moved.php"}'

# Clean up
wp ability call file-manager/delete-file \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-content/plugins/hello-dolly/hello.moved.php","confirm":true}'
```

Expect three `{success: true}` responses. If step 3's copy already exists from a prior run, add `"overwrite":true`.

## Step 5 — Refuse copy/move onto protected files

```bash
wp ability call file-manager/copy-file \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"source":"wp-content/plugins/hello-dolly/hello.php","destination":"wp-config.php","overwrite":true}'
```

Expect `{success: false, blocked_reason: "protected_write"}`.

## Step 6 — Refuse generic edit-file / create-file on wp-config.php and .htaccess (the guard-gap fix)

```bash
# edit-file — wp-config.php
wp ability call file-manager/edit-file \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-config.php","content":"malicious"}'

# create-file — .htaccess (should refuse whether or not the file exists)
wp ability call file-manager/create-file \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":".htaccess","content":"malicious","create_dirs":false}'
```

Both must return `{success: false, blocked_reason: "protected_write"}`. Before this feature, both would have silently succeeded — that's the security gap being closed.

## Step 7 — Specialized wrappers still work

```bash
# Read wp-config summary (redacted)
wp ability call file-manager/read-wp-config \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public '{}'

# Read tail of debug log
wp ability call file-manager/read-debug-log \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public '{"lines":10}'
```

Both must return normal successful responses — confirming the specialized safety wrappers were not affected.

## Step 8 — Old slugs return "unknown ability"

```bash
wp ability call themes/read-theme-code \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"theme_slug":"twentytwentyfour","file_path":"functions.php"}'
```

Expect WP-CLI error indicating the ability is not registered. Repeat for each of the six removed slugs.

## Step 9 — MCP client (optional live check)

Using the `claude.ai acrosai` MCP connector:

1. Discover abilities and confirm the six old slugs are absent, the three new slugs are present.
2. Call `file-manager/list-directory` on a plugin directory.
3. Call `file-manager/copy-file` inside `wp-content/plugins/`.
4. Call `file-manager/edit-file` targeting `wp-config.php` and confirm the refusal.

## Step 10 — Static + unit gates

```bash
composer test         # PHPUnit — new List_Directory_Test, Copy_File_Test, Move_File_Test, Create_File_Test, Edit_File_Test all pass
vendor/bin/phpstan analyse  # level 8 clean
vendor/bin/phpcs             # WPCS clean
```

All three must pass with zero errors.
