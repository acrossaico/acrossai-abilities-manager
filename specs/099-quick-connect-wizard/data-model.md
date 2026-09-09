# Phase 1 Data Model: Quick Connect Onboarding Wizard

**No database schema.** This feature adds no tables, columns, or options. Everything below is either
derived at read time or held in the URL for the duration of a visit. `uninstall.php` needs no changes.

---

## 1. Ability Catalogue Summary *(derived, read-only)*

Computed per `GET /quick-connect/state`; never stored.

| Field | Type | Derivation | Rules |
|---|---|---|---|
| `total` | int ≥ 0 | `wp_get_abilities()` minus protected slugs | Excludes the three `mcp-adapter/*` slugs via `AcrossAI_Protected_Abilities::is_protected()` (strict comparison, SEC-04). Must equal the Abilities screen count (SC-004). |
| `tabGroups[]` | array | `AcrossAI_Ability_Library_Registry::get_definitions()` grouped by `tab_group` | Definitions with an empty `tab_group` are skipped. Order: count desc, then key asc, for stable rendering. |
| `tabGroups[].key` | string | the raw `tab_group` value | `sanitize_key()`-shaped; e.g. `content-search` |
| `tabGroups[].label` | string | `ucwords( str_replace( '-', ' ', key ) )` | Must match the JS `titleCaseTabLabel` rule character-for-character — pinned by paired PHPUnit/Jest tests (R6) |
| `tabGroups[].count` | int ≥ 1 | number of definitions in the group | Groups from inactive optional integrations are absent entirely (FR-017) |

**Validation**: `total` of `0` is legal and must render coherently (spec Edge Cases); an empty
`tabGroups` array is legal and renders an empty-but-explained showcase.

---

## 2. Transport Option *(derived, read-only)*

Two fixed options. Presence is computed per request by
`AcrossAI_Mcp_Transport_Detector::detect()`.

| Field | Type | Notes |
|---|---|---|
| `key` | enum `mcp-manager` \| `mcp-adapter` | Stable identifier; also the `?method=` URL value |
| `state` | enum `missing` \| `inactive` \| `active` | See detection rules below |
| `recommended` | bool | `true` only for `mcp-manager` (FR-020) |
| `installable` | bool | `true` only for `mcp-manager` — the alternative is not distributed through the plugin directory (FR-026) |

**Detection rules (R7)**

| Option | Method |
|---|---|
| `mcp-manager` | Plugin basename `acrossai-mcp-manager/acrossai-mcp-manager.php` via `is_plugin_active()` / `get_plugins()` |
| `mcp-adapter` | **Class-presence probe first** (catches copies bundled inside another plugin), falling back to a candidate basename check. Never basename-only — that yields a false negative for the bundled case (FR-027). |

**State transitions**: only ever `missing → active` or `inactive → active`, and only as a result of
the install action or a manual install performed outside the wizard. The wizard never downgrades a
state.

---

## 3. Wizard Position *(URL-held, per visit)*

Held entirely in query parameters. Not persisted server-side (FR-015).

| Param | Type | Default | Rules |
|---|---|---|---|
| `quick-connect` | literal `1` | absent | Presence is what activates the wizard; gates render, assets, body class, notice suppression (R3) |
| `step` | `1`–`7` \| `done` | `1` | Unknown value → redirect to `1`. Inapplicable value → auto-skip forward (FR-013) |
| `method` | enum `mcp-manager` \| `mcp-adapter` | `mcp-manager` | Defaulting to the recommended option is what preselects it (FR-020) |

**Derived, not stored**

| Value | Rule |
|---|---|
| `skipAdapterInstall` | `method !== 'mcp-adapter'` **or** the alternative transport is already present (FR-011a) |
| `skipAdapterAbilities` | `method !== 'mcp-adapter'` |
| `totalSteps` | count of non-skipped rows in the step-visibility table |
| `displayIndex` | ordinal of the current step among non-skipped rows (FR-009) |

Resulting path lengths: **5 screens** on the recommended path, **7** on the alternative path when it
must be installed, **6** when it is already present.

---

## 4. Pending-Opening Marker *(transient, single-use)*

| Property | Value |
|---|---|
| Key | `acrossai_abilities_quick_connect_do_redirect` |
| Value | `'1'` |
| TTL | 30 seconds |
| Written by | `AcrossAI_Activator::activate()` |
| Consumed by | `ActivationRedirect::maybe_redirect()` on `admin_init` priority 5 |

**Lifecycle**: written at activation → **deleted first thing** on the next admin request
(idempotency, so a failed hand-off cannot loop) → guards evaluated → redirect or discard. Expiry is
the designed outcome for CLI activation with no following page view (FR-004).

**Guard order** (all must pass; FR-001 – FR-005, FR-002a):

1. transient present
2. transient deleted *before* any further evaluation
3. not `activate-multi`
4. not network admin
5. `current_user_can( 'manage_options' )`
6. recommended transport **not** `active` — note two things are deliberately **not** suppressors:
   the alternative transport's presence (FR-002a), and the recommended transport being merely
   installed but switched off (FR-002 says "installed **and** active"; an inactive copy connects
   nothing, so that administrator still needs onboarding)

---

## 5. Install Request / Result *(transient, in-flight only)*

| Direction | Field | Rules |
|---|---|---|
| Request | `slug` | Must be exactly `acrossai-mcp-manager`; checked with `in_array( $slug, $allowed, true )` (SEC-04). Anything else → 400 (FR-028) |
| Result | `installed`, `active`, `plugin` | Returned on success only |
| Failure | `WP_Error` | Hand-authored message; technical detail goes to `error_log()` and never to the client (FR-030) |

**Concurrency**: the client disables all controls while the request is in flight (FR-031); the
operation is naturally idempotent because an already-installed plugin skips straight to activation.

---

## Entity Relationships

```text
Wizard Position (URL)
   ├── selects ──> Transport Option (2 fixed)
   │                    └── state from ──> Transport Detector (per request)
   ├── gates ────> step visibility ──> progress indicator
   └── reads ────> Ability Catalogue Summary (per request)

Pending-Opening Marker ──(one-shot)──> Wizard Position(step=1)
Install Request ──(on success)──> Transport Option.state = active ──> hand-off to sibling wizard
```

No entity outlives a single request except the 30-second marker.
