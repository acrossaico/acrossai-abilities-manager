# Quickstart — Debugging Abilities — Conflict Testing

End-to-end walkthrough for verifying the seven abilities work as designed. Covers the happy paths from all five user stories (Story 5 was dropped during `/speckit-clarify`) plus the two big edge cases (fatal-plugin guard, uninstalled-plugin auto-prune).

**Prerequisites**: an admin session (`manage_options`), a working REST endpoint, and `curl` (or any HTTP client — replace with your MCP client, WP-CLI, or `wp abilities run` invocation as appropriate). Adjust the base URL to match your local site.

```bash
BASE=https://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities
AUTH="-u admin:app-password"   # or an application password / cookie / X-WP-Nonce header
```

## 1. Confirm the seven abilities are registered

```bash
curl -s $AUTH "$BASE" | jq '.[] | select(.name | startswith("acrossai/conflict-test-")) | .name'
```

Expect all seven:

```
"acrossai/conflict-test-list-plugins"
"acrossai/conflict-test-get-overrides"
"acrossai/conflict-test-set-override"
"acrossai/conflict-test-bulk-set-overrides"
"acrossai/conflict-test-clear-overrides"
"acrossai/conflict-test-deploy-mu-plugin"
"acrossai/conflict-test-remove-mu-plugin"
```

## 2. Deploy the mechanism (User Story 2, first-deploy path)

```bash
curl -s $AUTH -X POST "$BASE/acrossai/conflict-test-deploy-mu-plugin/run" -H 'Content-Type: application/json' -d '{}' | jq
```

Expect:

```json
{
  "deployed":        true,
  "already_current": false,
  "path":            "/…/wp-content/mu-plugins/wp-conflict-tester.php"
}
```

Verify on disk:

```bash
ls -la wp-content/mu-plugins/wp-conflict-tester.php
```

Re-run the deploy — expect the idempotent path:

```json
{ "deployed": false, "already_current": true, "path": "…" }
```

Verify `mtime` is unchanged (SC-006).

## 3. Read initial state (User Story 1, step 1)

```bash
curl -s $AUTH -X POST "$BASE/acrossai/conflict-test-get-overrides/run" -H 'Content-Type: application/json' -d '{}' | jq
```

Expect an empty map plus `deployed`:

```json
{ "overrides": {}, "mu_plugin_status": "deployed", "parse_error": null }
```

## 4. List installed plugins (User Story 1, step 2)

```bash
curl -s $AUTH -X POST "$BASE/acrossai/conflict-test-list-plugins/run" -H 'Content-Type: application/json' -d '{}' | jq '.plugins[] | { plugin_file, status, requires_plugins }'
```

Confirm Hello Dolly is `active`:

```json
{ "plugin_file": "hello-dolly/hello.php", "status": "active", "requires_plugins": [] }
```

## 5. Turn Hello Dolly effectively OFF (User Story 1, step 3)

```bash
curl -s $AUTH -X POST "$BASE/acrossai/conflict-test-set-override/run" \
  -H 'Content-Type: application/json' \
  -d '{"plugin_file":"hello-dolly/hello.php","active":false}' | jq
```

Expect:

```json
{
  "plugin_file":      "hello-dolly/hello.php",
  "recorded":         true,
  "reason":           "override-applied",
  "cascade_applied":  []
}
```

Verify:

- Load any admin page in a browser — Hello Dolly's admin-header quote is gone.
- Inspect the DB row directly:

  ```bash
  wp option get active_plugins --format=json | jq
  ```

  `hello-dolly/hello.php` is **still** in the list. The DB row is unchanged (SC-002).

- Inspect the overrides file:

  ```bash
  cat wp-content/conflict-test-overrides.json
  ```

  Expect `{ "overrides": { "hello-dolly/hello.php": false } }`.

## 6. Clear the override (User Story 1, step 5)

```bash
curl -s $AUTH -X POST "$BASE/acrossai/conflict-test-clear-overrides/run" -H 'Content-Type: application/json' -d '{}' | jq
```

Expect:

```json
{ "cleared": true, "file_existed_before": true }
```

Verify the file is gone (FR-012):

```bash
ls wp-content/conflict-test-overrides.json 2>&1
# → "No such file or directory"
```

Reload the site — Hello Dolly's quote is back.

## 7. Bulk deactivate, then verify the applied/no-op/skipped report (User Story 3)

Given the site has Akismet inactive and Hello Dolly active:

```bash
curl -s $AUTH -X POST "$BASE/acrossai/conflict-test-bulk-set-overrides/run" \
  -H 'Content-Type: application/json' \
  -d '{"plugin_files":["akismet/akismet.php","hello-dolly/hello.php","typo/nope.php"],"active":false}' | jq
```

Expect (Akismet is already DB-inactive → `no_op`; Hello Dolly gets an override → `applied`; typo doesn't exist → `skipped`):

```json
{
  "applied":  [ { "plugin_file": "hello-dolly/hello.php", "active": false } ],
  "no_op":    [ { "plugin_file": "akismet/akismet.php", "reason": "matches-db-state" } ],
  "skipped":  [ { "plugin_file": "typo/nope.php", "reason": "plugin-not-installed" } ]
}
```

## 8. Cascade through `Requires Plugins` (User Story 4)

Set up two test plugins where `plugin-b/main.php` declares `Requires Plugins: plugin-a`. Activate both. Then:

```bash
curl -s $AUTH -X POST "$BASE/acrossai/conflict-test-set-override/run" \
  -H 'Content-Type: application/json' \
  -d '{"plugin_file":"plugin-a/main.php","active":false,"cascade":true}' | jq
```

Expect the override on `plugin-a` plus a cascade entry on `plugin-b`:

```json
{
  "plugin_file":     "plugin-a/main.php",
  "recorded":        true,
  "reason":          "override-applied",
  "cascade_applied": [
    { "plugin_file": "plugin-b/main.php", "active": false, "reason": "override-applied" }
  ]
}
```

Re-read overrides and confirm both entries:

```bash
curl -s $AUTH -X POST "$BASE/acrossai/conflict-test-get-overrides/run" -H 'Content-Type: application/json' -d '{}' | jq '.overrides'
```

```json
{
  "plugin-a/main.php": false,
  "plugin-b/main.php": false
}
```

Now clear and reissue with `cascade: false`:

```bash
curl -s $AUTH -X POST "$BASE/acrossai/conflict-test-clear-overrides/run" -H 'Content-Type: application/json' -d '{}' > /dev/null
curl -s $AUTH -X POST "$BASE/acrossai/conflict-test-set-override/run" \
  -H 'Content-Type: application/json' \
  -d '{"plugin_file":"plugin-a/main.php","active":false,"cascade":false}' | jq
```

`cascade_applied: []` — only `plugin-a` is overridden.

## 9. Fatal-safety: refuse an override on a broken plugin (Story 1, AC-5)

Create a deliberately broken plugin:

```bash
mkdir -p wp-content/plugins/broken
cat > wp-content/plugins/broken/broken.php <<'PHP'
<?php
/* Plugin Name: Deliberately Broken */
throw new \RuntimeException( 'boom' );
PHP
```

Try to override it active:

```bash
curl -s $AUTH -X POST "$BASE/acrossai/conflict-test-set-override/run" \
  -H 'Content-Type: application/json' \
  -d '{"plugin_file":"broken/broken.php","active":true,"cascade":false}' | jq
```

Expect either a `plugin-fatal-on-load` error envelope (if PHP caught the `Throwable`) or an HTTP 500 (if the fatal was uncatchable). Either way:

```bash
cat wp-content/conflict-test-overrides.json 2>&1
# → "No such file or directory"   (nothing was written)
```

The site's next request renders normally — the `broken` plugin was **not** added to the effective active list (SC-008).

Also verify the bulk path handles the same case gracefully:

```bash
curl -s $AUTH -X POST "$BASE/acrossai/conflict-test-bulk-set-overrides/run" \
  -H 'Content-Type: application/json' \
  -d '{"plugin_files":["hello-dolly/hello.php","broken/broken.php"],"active":true}' | jq
```

Expect Hello Dolly to be classified (either `applied` or `no_op` depending on its current DB state) and `broken` under `skipped: plugin-fatal-on-load`.

## 10. Orphan auto-prune (edge case per FR-021 / Q3)

Set an override on a plugin, then uninstall the plugin:

```bash
curl -s $AUTH -X POST "$BASE/acrossai/conflict-test-set-override/run" \
  -H 'Content-Type: application/json' \
  -d '{"plugin_file":"hello-dolly/hello.php","active":false}' > /dev/null

# Now uninstall Hello Dolly via WP-CLI or the admin UI
wp plugin uninstall hello-dolly

# Re-read overrides
curl -s $AUTH -X POST "$BASE/acrossai/conflict-test-get-overrides/run" -H 'Content-Type: application/json' -d '{}' | jq '.overrides'
```

Expect an empty map — the orphan was silently pruned and the on-disk file was deleted (since the pruned map is now empty, per FR-012).

```bash
ls wp-content/conflict-test-overrides.json 2>&1
# → "No such file or directory"
```

## 11. Remove the mechanism (User Story 2, remove path)

```bash
curl -s $AUTH -X POST "$BASE/acrossai/conflict-test-remove-mu-plugin/run" \
  -H 'Content-Type: application/json' \
  -d '{"also_clear_overrides":true}' | jq
```

Expect:

```json
{ "removed": true, "file_existed_before": true, "overrides_cleared": false }
```

(`overrides_cleared: false` because the file was already gone from step 10.)

Verify the mu-plugin is removed:

```bash
ls wp-content/mu-plugins/wp-conflict-tester.php 2>&1
# → "No such file or directory"
```

The site is back to its exact stock state.

---

## Multisite note

The overrides file lives at `WP_CONTENT_DIR/conflict-test-overrides.json` — network-wide on multisite. Toggling a plugin off from any site in the network affects every site. This matches the mu-plugin's own network-wide scope (mu-plugins load for every request across every site).

If your workflow requires per-site scoping on multisite, run conflict tests from the network admin only, and be explicit with the team about which site is under test.

## Constitution §VII — testing

`OverridesStoreTest.php` and `DependencyResolverTest.php` (under `tests/phpunit/abilities/debugging/`) cover the non-trivial logic in the two shared helpers. Run:

```bash
composer install
./vendor/bin/phpunit --testsuite=abilities-unit --filter Debugging
```

Expect green.
