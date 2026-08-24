# Ability Contract — `acrossai/conflict-test-set-override`

**Class**: `AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\Set_Override`
**Auto-exposed REST route**: `POST /wp-json/wp-abilities/v1/abilities/acrossai/conflict-test-set-override/run`
**Annotations**: `readonly: false`, `idempotent: true`, `destructive: false`

## Purpose

Set the effective active state of exactly one plugin without modifying `wp_options.active_plugins`. Default behaviour cascades through the `Requires Plugins:` dependency graph so dependents are deactivated together with a plugin, or requirements are activated together with a dependent.

## Permission

`current_user_can( 'manage_options' )`.

## Input (JSON body)

```json
{
  "plugin_file": "hello-dolly/hello.php",
  "active":      false,
  "cascade":     true
}
```

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| `plugin_file` | `string` | yes | — | Plugin file identifier as returned by `list-plugins`. Must match a currently-installed plugin (FR-018 single-plugin path). |
| `active` | `boolean` | yes | — | `true` = force effectively-active regardless of DB state; `false` = force effectively-inactive. |
| `cascade` | `boolean` | no | `true` | When `true` and `active: false`, transitively deactivate every plugin that declares `plugin_file` in its `Requires Plugins:` header. When `true` and `active: true`, transitively activate every plugin listed in `plugin_file`'s `Requires Plugins:` header. When `false`, only `plugin_file` is touched. |

## Output (success)

```json
{
  "plugin_file":      "hello-dolly/hello.php",
  "recorded":         true,
  "reason":           "override-applied",
  "cascade_applied": [
    { "plugin_file": "another/plugin.php", "active": false, "reason": "override-applied" }
  ]
}
```

- `recorded: true` — the override entry was written to the on-disk map.
- `reason: "override-applied"` — the write succeeded.
- `cascade_applied` — list of every additional plugin the cascade walk touched (empty array when `cascade: false` or when no dependents/requirements exist).

## Output (no-op, per FR-011)

```json
{
  "plugin_file":      "hello-dolly/hello.php",
  "recorded":         false,
  "reason":           "matches-db-state",
  "cascade_applied": []
}
```

Requested state already equals the DB-recorded state — no entry is written.

## Output (error paths)

- **Unknown plugin** (FR-018 single-plugin path):

  ```json
  { "code": "plugin-not-installed", "message": "The plugin file 'foo/bar.php' is not installed on this site.", "data": { "status": 400 } }
  ```

- **Fatal on include probe** (FR-022):

  ```json
  { "code": "plugin-fatal-on-load", "message": "Including <plugin_file> triggered a PHP fatal error; override refused.", "data": { "status": 500 } }
  ```

  In practice PHP dies mid-request; the client observes a 500 response body with a WP core stack trace or a blank body. The store is guaranteed not to have written the override because the include happens before the store call.

- **Capability failure** (§IV): standard HTTP 403 via `WP_Error`.

## Side effects

- **Sandbox-scrape probe on `active: true`** (FR-022): before writing, define `WP_SANDBOX_SCRAPING` if not defined, `wp_register_plugin_realpath()` the target, `include_once` its main file. If the include triggers a fatal, PHP dies and the store call is never reached.
- **Write on success**: `Overrides_Store::write_one( plugin_file, active )` — writes to a sibling temp file and atomic-renames into place (per R2). Auto-prunes orphans encountered during the read-modify-write cycle (per FR-021).
- **Cascade walk on `cascade: true`**: `Dependency_Resolver::dependents_of( plugin_file )` for `active: false`, or `Dependency_Resolver::requirements_of( plugin_file )` for `active: true`. Each transitively-reached plugin goes through the same write path (including sandbox-scrape when `active: true`).

## Backing implementation notes

- Load `wp-admin/includes/plugin.php` if `validate_plugin()` isn't yet available.
- The sandbox-scrape probe is defined once as a class-level helper `Set_Override::sandbox_scrape( $plugin_file )` and re-used by `Bulk_Set_Overrides`.
- Cascade walk uses breadth-first traversal with a visited set to guard against `Requires Plugins:` cycles (which WP itself should prevent, but defence in depth is cheap).
