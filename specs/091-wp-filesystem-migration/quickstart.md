# Quickstart: WP_Filesystem Migration (091)

End-to-end verification for feature 091. Working directory: `/Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/plugins/acrossai-abilities-manager`. Site: `/Users/raftaar1191/local-sites/wordpress-7-0/app/public`.

## Prerequisites

- WP-CLI available (`wp --info` succeeds).
- Plugin activated.
- On Local by Flywheel — `FS_METHOD` defaults to `direct`. That's the baseline transport for steps 1–7.
- Optional (steps 8–9): configure `FS_METHOD='ftpsockets'` + `FTP_HOST`/`FTP_USER`/`FTP_PASS` for the transport-portability check.

## Step 1 — Confirm active filesystem method

```bash
wp eval 'echo get_filesystem_method(), "\n";' --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
```

Expect `direct` on Local.

## Step 2 — Every migrated ability responds `success:true` on `direct`

```bash
wp ability call file-manager/read-file        '{"path":"index.php"}'                             --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
wp ability call file-manager/list-directory   '{"path":"wp-content/plugins","max_depth":1}'      --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
wp ability call file-manager/file-info        '{"path":"wp-config.php"}'                         --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
wp ability call file-manager/read-wp-config   '{}'                                               --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
wp ability call file-manager/read-debug-log   '{"lines":10}'                                     --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
```

Each returns `success:true`. `file-info` response has NO `ctime` and NO `atime` fields.

## Step 3 — Create / append / delete-directory round-trip via WP_Filesystem

```bash
wp ability call file-manager/create-directory '{"path":"wp-content/uploads/acrossai-test-091"}'                                        --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
wp ability call file-manager/create-file      '{"path":"wp-content/uploads/acrossai-test-091/x.txt","content":"line 1\n"}'            --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
wp ability call file-manager/append-file      '{"path":"wp-content/uploads/acrossai-test-091/x.txt","content":"line 2\n"}'            --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
wp ability call file-manager/read-file        '{"path":"wp-content/uploads/acrossai-test-091/x.txt"}'                                  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
wp ability call file-manager/delete-directory '{"path":"wp-content/uploads/acrossai-test-091","confirm":true,"recursive":true}'        --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
```

Read returns `content:"line 1\nline 2\n"`. Delete returns `entries_removed:2` (file + dir).

## Step 4 — Existing guards still fire

```bash
wp ability call file-manager/edit-file        '{"path":"wp-config.php","content":"x"}'   --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
wp ability call file-manager/delete-directory '{"path":"wp-content","confirm":true,"recursive":true}'  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
```

First: `{success:false, blocked_reason:"protected_write"}`. Second: `{success:false, blocked_reason:"protected_directory"}`.

## Step 5 — Zip abilities on `direct` transport unchanged (non-regression)

```bash
wp ability call file-manager/create-zip-backup   '{"kind":"plugin","target":"hello-dolly"}'  --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
wp ability call file-manager/list-zip-backups    '{}'                                         --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
wp ability call file-manager/download-zip-backup '{"path":"<result-from-list>"}'              --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
wp ability call file-manager/delete-zip-backup   '{"path":"<result-from-list>"}'              --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
```

Every response identical to pre-migration behaviour.

## Step 6 — PHPCS suppression audit

```bash
grep -rn "phpcs:ignore WordPress.WP.AlternativeFunctions" includes/Abilities/FileManager/ | grep -v -E '(Create_Zip_Backup|Extract_Zip_Backup|Upload_Zip_Backup)\.php'
```

Expect NO output. Any hit is a missed migration.

## Step 7 — Static + unit gates

```bash
composer test          # full suite green, +new feature-091 assertions
vendor/bin/phpstan analyse  # level 8 clean
vendor/bin/phpcs             # WPCS clean
```

## Step 8 — Sibling plugin non-regression

```bash
wp plugin is-active acrossai-buddyboss --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public
wp ability list --path=/Users/raftaar1191/local-sites/wordpress-7-0/app/public | grep -c 'acrossai-buddyboss/'
```

Expect the plugin active AND the count of its abilities unchanged from pre-migration baseline.

## Step 9 — FTP transport simulation (optional, most valuable)

In the Local site's `wp-config.php`:

```php
define( 'FS_METHOD', 'ftpsockets' );
define( 'FTP_HOST', '127.0.0.1' );
define( 'FTP_USER', '<local-ssh-user>' );
define( 'FTP_PASS', '<password>' );
define( 'FTP_SSL',  false );
```

Then re-run Step 2 + Step 3. Every migrated ability should succeed via the FTP transport. If any fails with `filesystem_unavailable`, that's a credentials issue, not a migration bug. If any fails with a different error, that's a migration bug.

Optionally clear the credentials (leave `FS_METHOD='ftpsockets'` but comment out `FTP_USER`/`FTP_PASS`) — every migrated ability should return `{success:false, blocked_reason:"filesystem_unavailable"}` cleanly, never a PHP warning or silent success.

## Step 10 — MCP live check (optional)

Via `claude.ai acrosai`: exercise 4–5 representative migrated abilities (read, write, list, copy, delete, file-info). Confirm response shapes match this quickstart's expectations. Confirm `file-info` has no `ctime` / `atime`.
