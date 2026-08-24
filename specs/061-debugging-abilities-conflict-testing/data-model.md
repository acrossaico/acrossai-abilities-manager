# Phase 1 Data Model — Debugging Abilities — Conflict Testing

## Entities

### Plugin

A WordPress plugin installed on the site. Read-only from this feature's perspective — the plugin metadata is derived from `get_plugins()` (WP core, `wp-admin/includes/plugin.php`) plus `get_option( 'active_plugins' )`.

| Field | Type | Source | Notes |
|-------|------|--------|-------|
| `plugin_file` | `string` (identifier) | key from `get_plugins()` | Relative path from `WP_PLUGIN_DIR`, e.g. `hello-dolly/hello.php`. Serves as the canonical identifier throughout every ability I/O. Matches the format WP core uses in `active_plugins`. |
| `name` | `string` | `get_plugins()[$file]['Name']` | Human-readable plugin name from the plugin header. |
| `version` | `string` | `get_plugins()[$file]['Version']` | Semver-ish version string from the header. May be empty. |
| `status` | `enum: 'active' \| 'inactive'` | derived from `in_array( $file, get_option('active_plugins'), true )` | **DB-recorded** state, never the effective state under the mu-plugin filter. |
| `requires_plugins` | `array<string>` | `get_plugins()[$file]['RequiresPlugins']` split on comma | WP 6.5+ `Requires Plugins:` header, parsed and trimmed. Empty array for plugins that declare no dependencies. |

**Identity**: `plugin_file`. Unique across the site.
**Uniqueness rule**: WP core guarantees one entry per plugin file in `get_plugins()`. This feature never mutates that data.

### Override Entry

A single per-plugin decision that the plugin's *effective* active state should differ from its *DB-recorded* state. Persisted inside the Override Map.

| Field | Type | Notes |
|-------|------|-------|
| `plugin_file` | `string` (identifier) | Foreign key to `Plugin.plugin_file`. FR-018 requires the file to resolve at write time (or the entry is refused / skipped). FR-021 requires the entry to be pruned on read if the file no longer resolves. |
| `active` | `boolean` | `true` means "add this plugin to the effective active list regardless of the DB row"; `false` means "remove this plugin from the effective active list regardless of the DB row". |

**Identity**: `plugin_file`. Two entries for the same file are impossible — the on-disk representation is a JSON object with `plugin_file` as the key.

**Invariants**:

- **FR-011**: an entry whose `active` value matches the plugin's current DB-recorded state MUST NOT be recorded. So `Override Entry { file: X, active: true }` where `X` is currently DB-active is dropped at write time; symmetrically for `active: false` and DB-inactive.
- **FR-021**: an entry whose `plugin_file` no longer resolves against `get_plugins()` MUST be pruned on read.

### Override Map

The full collection of Override Entries for the current site. Persisted as a single JSON document at a fixed system-owned path.

| Aspect | Value |
|--------|-------|
| **On-disk path** | `WP_CONTENT_DIR . '/conflict-test-overrides.json'` (fixed; not configurable). |
| **On-disk shape** | `{ "overrides": { "<plugin_file>": <bool>, ... } }` (see `research.md` R3). |
| **Absent-file semantics** | Semantically equivalent to `{ overrides: {} }` — no active overrides. |
| **Empty-map semantics** | If a write leaves the map empty, the on-disk document MUST be deleted (per FR-012), so `has entries` ⇔ `file exists`. |
| **Malformed-file semantics** | On read, treated as `{ overrides: {} }` plus a `parse_error` indicator in the response (per FR-019); on write, overwritten with a well-formed document. |

**State transitions**:

```
     no file (empty map)
     ↓ set-override(X, ¬DBstate)                     [write]
     one-entry file
     ↓ set-override(Y, ¬DBstate)                     [write]
     multi-entry file
     ↓ set-override(X, DBstate) or plugin X uninstalled   [entry dropped / pruned]
     multi-entry file  (X gone)
     ↓ (repeat until every entry cancels or prunes)
     empty map → file deleted
     ↓
     no file (empty map)
```

Every transition is idempotent — running the same write twice produces the same on-disk result.

### Mechanism

The must-use plugin file at `WPMU_PLUGIN_DIR . '/wp-conflict-tester.php'` that filters `option_active_plugins` on every request. Not a data-model entity in the storage sense — it's a **file** whose presence and byte-content constitute a runtime state.

| Field | Type | Notes |
|-------|------|-------|
| `path` | `string` | Fixed at `WPMU_PLUGIN_DIR . '/wp-conflict-tester.php'`. Not configurable. |
| `status` | `enum: 'deployed' \| 'missing' \| 'stale'` | Computed by comparing SHA-256 of the on-disk file to the SHA-256 of the bundled reference at `includes/Abilities/Debugging/assets/wp-conflict-tester.php`. `deployed` = present and hash-matches. `missing` = file absent. `stale` = present but hash-mismatches. |

**State transitions**:

```
     missing
     ↓ conflict-test-deploy-mu-plugin
     deployed
     ↓ (bundled reference changes in a future release of this plugin)
     stale
     ↓ conflict-test-deploy-mu-plugin (rewrites to current bytes)
     deployed
     ↓ conflict-test-remove-mu-plugin
     missing
```

Redeploying when `status = deployed` is a no-op — the file's mtime is unchanged (SC-006).

### Debugging Category (metadata)

Not a runtime entity — this is the WP Abilities API category registered on `wp_abilities_api_categories_init`.

| Field | Value |
|-------|-------|
| `slug` | `acrossai-abilities-manager-debugging` |
| `label` | `Acrossai Abilities Manager — Debugging` |
| `tab_group` | `debugging` |
| Grows via | additional sub-groups (e.g. `log-tail`, `transient-inspection`, `query-monitor-toggle`) sharing the same category slug (FR-016 / SC-007). |

## Relationships

```
Plugin  1 ─── 0..1  Override Entry
                       │ (identified by plugin_file)
                       │
                       └── belongs to → Override Map (1 per site)

Mechanism  1 per site (independent of Override Map — presence controls whether the map is honoured at request time)

Debugging Category  1 (feature-wide singleton) ─── * Ability (7 in this feature; more in future features)
```

## Response envelopes (informal)

Not strict schemas — see `contracts/*.md` for each ability's I/O contract.

### `list-plugins` response

```json
{
  "plugins": [
    { "plugin_file": "hello-dolly/hello.php", "name": "Hello Dolly", "version": "1.7.2",
      "status": "active", "requires_plugins": [] },
    { "plugin_file": "akismet/akismet.php", "name": "Akismet Anti-Spam", "version": "5.3",
      "status": "inactive", "requires_plugins": [] }
  ]
}
```

### `get-overrides` response

```json
{
  "overrides": {
    "hello-dolly/hello.php": false
  },
  "mu_plugin_status": "deployed",
  "parse_error": null
}
```

`parse_error` is `null` when the file was empty/absent or parsed cleanly; a string message when FR-019 tripped.

### `set-override` response

```json
{
  "plugin_file": "hello-dolly/hello.php",
  "recorded": true,
  "reason": "override-applied",
  "cascade_applied": [
    { "plugin_file": "another/plugin.php", "active": false }
  ]
}
```

`recorded: false` with `reason: "no-op-matches-db"` covers the FR-011 skip case. `cascade_applied` lists the transitive dependents/requirements walked when `cascade: true`.

### `bulk-set-overrides` response

```json
{
  "applied": [
    { "plugin_file": "akismet/akismet.php", "active": false },
    { "plugin_file": "wp-super-cache/wp-cache.php", "active": false }
  ],
  "no_op": [
    { "plugin_file": "hello-dolly/hello.php", "reason": "matches-db-state" }
  ],
  "skipped": [
    { "plugin_file": "typo-plugin/tpyo.php", "reason": "plugin-not-installed" },
    { "plugin_file": "broken/main.php",     "reason": "plugin-fatal-on-load" }
  ]
}
```

### `clear-overrides` response

```json
{
  "cleared": true,
  "file_existed_before": true
}
```

### `deploy-mu-plugin` response

```json
{
  "deployed": true,
  "already_current": false,
  "path": "/absolute/path/to/wp-content/mu-plugins/wp-conflict-tester.php"
}
```

`already_current: true` with `deployed: false` covers the idempotent redeploy case (FR-006 + SC-006).

### `remove-mu-plugin` response

```json
{
  "removed": true,
  "file_existed_before": true,
  "overrides_cleared": false
}
```

`overrides_cleared: true` when the input flag `also_clear_overrides: true` and the JSON file was present.
