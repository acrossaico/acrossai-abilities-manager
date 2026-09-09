# Contract: Quick Connect REST API

Namespace `acrossai/v1`, route prefix `/quick-connect`. Registered by
`AcrossAI_Quick_Connect_Controller` (sub-controller) and delegated from the existing
`AcrossAI_Abilities_Rest_Controller` orchestrator. The sub-controller registers **no** WordPress
hooks of its own — only the orchestrator is wired in `includes/Main.php`.

Both routes are admin-only and require a valid `X-WP-Nonce` (`wp_rest`).

---

## Shared permission callback

Reused from the orchestrator: `array( AcrossAI_Abilities_Rest_Controller::instance(), 'check_permission' )`.

**Return type is `true|false|WP_Error` only.** Returning a `WP_REST_Response` is a critical security
defect — it is truthy, so WordPress grants access regardless of the status code inside it.

| Condition | Response |
|---|---|
| Not `manage_options` | `403` `rest_forbidden` |
| Missing or invalid `X-WP-Nonce` | `403` `rest_forbidden` |
| Otherwise | `true` |

---

## `GET /acrossai/v1/quick-connect/state`

Read-only. Returns everything the wizard needs to render all screens.

**Permission**: shared `check_permission`.
**Parameters**: none.

### 200 response

```json
{
  "abilities": {
    "total": 436,
    "tabGroups": [
      { "key": "core",           "label": "Core",           "count": 105 },
      { "key": "blocks",         "label": "Blocks",         "count": 79 },
      { "key": "content-search", "label": "Content Search", "count": 11 }
    ]
  },
  "plugins": {
    "mcpManager": "missing",
    "mcpAdapter": "missing",
    "mcpManagerWizardUrl": "https://example.test/wp-admin/admin.php?page=acrossai_mcp_manager&quick-connect=1&step=1&server=1"
  }
}
```

| Field | Type | Notes |
|---|---|---|
| `abilities.total` | int ≥ 0 | Registered abilities minus the three protected `mcp-adapter/*` slugs. Must equal the Abilities screen count (SC-004). `0` is legal. |
| `abilities.tabGroups[]` | array | May be empty. Sorted count desc, then key asc. Groups from inactive optional integrations are absent. |
| `plugins.mcpManager` | `missing`\|`inactive`\|`active` | Basename detection |
| `plugins.mcpAdapter` | `missing`\|`inactive`\|`active` | **Class-presence probe first**, basename fallback — a bundled copy must report `active` |
| `plugins.mcpManagerWizardUrl` | string (URL) | Built server-side with `admin_url()`. Hand-off target after a successful install. |

**Caching**: none. The client refetches on window focus and after an install so an out-of-band
install is picked up without polling.

---

## `POST /acrossai/v1/quick-connect/install-plugin`

Installs and activates the recommended transport in place.

**Permission**: `current_user_can( 'install_plugins' ) && current_user_can( 'activate_plugins' )` —
stricter than the shared callback, in addition to the nonce check.

### Request

```json
{ "slug": "acrossai-mcp-manager" }
```

| Field | Rules |
|---|---|
| `slug` | Required. Allowlist of exactly one value, compared with `in_array( $slug, $allowed, true )` (SEC-04). Any other value → `400`. |

### 200 response

```json
{ "installed": true, "active": true, "plugin": "acrossai-mcp-manager/acrossai-mcp-manager.php" }
```

### Behaviour

1. Validate the slug against the allowlist (strict). Reject otherwise.
2. If not already installed: `plugins_api( 'plugin_information' )` → `Plugin_Upgrader` with
   `WP_Ajax_Upgrader_Skin` → install.
3. If not already active: `activate_plugin()`.
4. Both steps are idempotent — an already-installed-and-active plugin returns `200` unchanged.

### Error responses

| Status | Code | Client message | Server-side |
|---|---|---|---|
| `400` | `acrossai_quick_connect_invalid_plugin` | "That plugin cannot be installed from here." | — |
| `403` | `rest_forbidden` | Capability or nonce failure | — |
| `409` | `acrossai_quick_connect_install_in_progress` | "An installation is already running. Wait for it to finish before trying again." | In-flight lock held (SEC-006 / FR-031). Taken after slug validation, released in a `finally`, 120s TTL so a killed request cannot bolt the endpoint shut. |
| `500` | `acrossai_quick_connect_install_failed` | "Installation failed. Try installing manually from Plugins → Add New." | Raw `Plugin_Upgrader` message → `error_log()` |
| `500` | `acrossai_quick_connect_activate_failed` | "Activation failed. Try activating from Plugins." | Raw `activate_plugin()` error → `error_log()` |
| `502` | `acrossai_quick_connect_install_failed` | "Could not find that plugin on WordPress.org. Try installing it manually from Plugins → Add New." | Raw `plugins_api` error → `error_log()` |

**Error hygiene (FR-030)**: no response body may contain a filesystem path, vendor string, or raw
upgrader output. Client-facing strings are hand-authored and translatable.

---

## Deliberately absent routes

`POST /step` and `POST /complete` from the sibling implementation are **not** ported, and no
per-user scratchpad transient is created. Screens 1–4 are read-only and the transport choice lives
in the URL, so there is no server-side wizard state to persist (FR-015) and nothing to clean up on
uninstall.

---

## Contract tests

| Test | Expectation |
|---|---|
| `GET /state` as Editor | `403` |
| `GET /state` without nonce | `403` |
| `GET /state` totals | Equals `wp_get_abilities()` count minus protected slugs |
| `GET /state` tab groups | Match the Integrations page groups and counts exactly |
| `POST /install-plugin` with `acrossai-pro` | `400` |
| `POST /install-plugin` with `mcp-adapter` | `400` |
| `POST /install-plugin` lacking `install_plugins` | `403` |
| `POST /install-plugin` lacking `activate_plugins` | `403` |
| `POST /install-plugin` while another install holds the lock | `409` |
| `POST /install-plugin` with a rejected slug while no lock is held | `400`, and the lock is **not** taken |
| Any failure path | Response body contains no filesystem path |
| `permission_callback` return | Only `true`, `false`, or `WP_Error` — never a response object |
