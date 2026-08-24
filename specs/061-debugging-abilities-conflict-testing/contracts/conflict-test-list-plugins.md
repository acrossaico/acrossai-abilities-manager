# Ability Contract — `acrossai/conflict-test-list-plugins`

**Class**: `AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\List_Plugins`
**Auto-exposed REST route**: `POST /wp-json/wp-abilities/v1/abilities/acrossai/conflict-test-list-plugins/run`
**Annotations**: `readonly: true`, `idempotent: true`, `destructive: false`

## Purpose

Return every installed plugin with the fields needed to drive conflict testing: identifier, human name, version, DB-recorded active state, and any plugins declared as required. Replaces the Local addon's `wp plugin list` + `wp eval` invocations.

## Permission

`current_user_can( 'manage_options' )`.

## Input (JSON body)

```json
{}
```

No input fields.

## Output (success)

```json
{
  "plugins": [
    {
      "plugin_file":      "hello-dolly/hello.php",
      "name":             "Hello Dolly",
      "version":          "1.7.2",
      "status":           "active",
      "requires_plugins": []
    },
    {
      "plugin_file":      "some-plugin/some-plugin.php",
      "name":             "Some Plugin",
      "version":          "2.1.0",
      "status":           "inactive",
      "requires_plugins": ["another-plugin/another-plugin.php"]
    }
  ]
}
```

- `status` is derived from `get_option( 'active_plugins' )` — the **DB-recorded** state. Any active/inactive flip written by a Conflict Testing override is **not** reflected here (this ability describes the underlying reality, not the mu-plugin-filtered view).
- `requires_plugins` is the parsed `Requires Plugins:` header (WP 6.5+), split on comma and trimmed. Empty array for plugins that declare no dependencies.

## Output (error)

Standard WP Abilities API `WP_Error` envelope. Only surface here is capability failure — `manage_options` denied returns HTTP 403.

## Side effects

None. This ability is `readonly`.

## Backing implementation notes

- Loads `wp-admin/includes/plugin.php` if `get_plugins()` isn't available yet (mirrors the pattern in existing `Plugin_Helpers`).
- Sort order = `get_plugins()` key order (which is filesystem-order-ish; not guaranteed alphabetical). Consumers that want stable order should sort client-side.
