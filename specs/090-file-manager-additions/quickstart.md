# Quickstart: File-Manager Additions (090)

End-to-end verification for feature 090. Working directory: `/Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/plugins/acrossai-abilities-manager`.

## Prerequisites

- WordPress 6.9+ install at `/Users/raftaar1191/local-sites/wordpress-7-0/app/public`.
- Plugin activated on that install.
- WP-CLI available (`wp --info` succeeds).
- MCP connector `claude.ai acrosai` reachable (for the optional MCP steps).

## Step 1 — Confirm the four new abilities are registered

```bash
wp ability list --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  | grep -E '^file-manager/(append-file|create-directory|delete-directory|file-info)$'
```

Expect four rows. Empty output means bootstrap wiring is missing.

## Step 2 — Create a scratch directory tree

```bash
wp ability call file-manager/create-directory \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-content/uploads/acrossai-test-090/nested/deeper"}'
```

Expect `{success:true, created:true}`. Repeat the call — expect `{success:true, created:false}`.

## Step 3 — Inspect the new directory

```bash
wp ability call file-manager/file-info \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-content/uploads/acrossai-test-090/nested"}'
```

Expect `{success:true, type:"dir", mode_octal:"0755", readable:true, writable:true, ...}`.

## Step 4 — Create a file and append to it

```bash
wp ability call file-manager/create-file \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-content/uploads/acrossai-test-090/log.txt","content":"line 1\n"}'

wp ability call file-manager/append-file \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-content/uploads/acrossai-test-090/log.txt","content":"line 2\n"}'

wp ability call file-manager/append-file \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-content/uploads/acrossai-test-090/log.txt","content":"line 0\n","prepend":true}'

wp ability call file-manager/read-file \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-content/uploads/acrossai-test-090/log.txt"}'
```

Expect the last read to return `content:"line 0\nline 1\nline 2\n"`.

## Step 5 — Confirm append-file refuses missing files and protected files

```bash
wp ability call file-manager/append-file \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-content/uploads/does-not-exist.txt","content":"x"}'

wp ability call file-manager/append-file \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-config.php","content":"x"}'
```

First call: `{success:false, blocked_reason:"source_not_found"}`. Second call: `{success:false, blocked_reason:"protected_write"}`.

## Step 6 — Delete the scratch tree, recursive-off then recursive-on

```bash
wp ability call file-manager/delete-directory \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-content/uploads/acrossai-test-090","confirm":true}'

wp ability call file-manager/delete-directory \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-content/uploads/acrossai-test-090","confirm":true,"recursive":true}'

wp ability call file-manager/delete-directory \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-content/uploads/acrossai-test-090","confirm":true,"recursive":true}'
```

First call: `{success:false, blocked_reason:"not_empty"}` (tree still has files). Second call: `{success:true, entries_removed:N}`. Third call: `{success:true, entries_removed:0}` (idempotent re-run).

## Step 7 — Confirm delete-directory refuses critical paths

```bash
wp ability call file-manager/delete-directory \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-content","confirm":true,"recursive":true}'

wp ability call file-manager/delete-directory \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-admin","confirm":true,"recursive":true}'
```

Both: `{success:false, blocked_reason:"protected_directory"}`.

## Step 8 — Confirm delete-directory requires confirm:true

```bash
wp ability call file-manager/delete-directory \
  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public \
  '{"path":"wp-content/uploads/some-empty-dir"}'
```

Expect `{success:false, blocked_reason:"confirmation_required"}`.

## Step 9 — MCP live check (optional)

Via the `claude.ai acrosai` connector:
1. `mcp-adapter-discover-abilities` returns the four new slugs.
2. Call `file-manager/create-directory` on a fresh path.
3. Call `file-manager/append-file` on an existing text file.
4. Call `file-manager/file-info` on any path.
5. Call `file-manager/delete-directory` with `confirm:true` on the empty dir from step 2.

## Step 10 — Static + unit gates

```bash
composer test          # PHPUnit — full suite green, +N new tests
vendor/bin/phpstan analyse  # level 8 clean
vendor/bin/phpcs             # WPCS clean
```

All three must pass with zero errors and no delta on the pre-existing warning baseline.
