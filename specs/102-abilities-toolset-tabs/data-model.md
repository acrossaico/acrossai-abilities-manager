# Phase 1 Data Model: One Access Model for Abilities

**Feature**: 102-abilities-toolset-tabs · **Date**: 2026-09-12

## Entities

### Ability (existing — one field added)

The merged registry+override record returned by `GET /acrossai/v1/abilities`.

| Field | Source | Change |
|---|---|---|
| `ability_slug`, `label`, `description`, `category`, `provider`, `source` | registry ∪ override | — |
| `status` | forced `publish` for registry rows; real value for DB rows | now **filterable** (FR-015a) |
| `site_allowed` | override row | **becomes the sole availability control** |
| `show_in_rest`, `show_in_mcp`, `mcp_type` | registry ∪ override | — |
| `has_override`, `_override`, `_registry` | merger | — |
| **`tab_group`** | `meta.acrossai.tab_group` | **NEW in the response.** `''` for DB-created abilities |

**Validation**: `tab_group` is read-only for this feature. The MCP tool catalogue derives from it
(`DEC-ABILITY-GROUP-IDENTIFIER-LOAD-BEARING`), so re-tagging an ability silently moves it between MCP
tools. Nothing in this feature writes it.

### Toolset (existing concept, newly surfaced)

| Attribute | Value |
|---|---|
| Identity | the `tab_group` value (e.g. `content`, `cache`, `rank-math`) |
| Display | `toolset/<id>` in the Toolset column; title-cased label in the strip |
| Membership | `AcrossAI_Ability_Group::member_names()` — excludes protected slugs |
| Count | `AcrossAI_Ability_Group::counts()`, localised into `window.acrossaiAbilitiesManager` |
| Cardinality | 13 is a **ceiling** — each group is an always-loaded MCP tool (`DEC-ABILITY-GROUP-TAXONOMY`) |
| Lifecycle | exists only while it has abilities; a toolset whose host plugin is inactive has no entry |

An ability belongs to at most one toolset. Abilities belonging to none appear only under "All".

### Access setting (existing — scope widened)

Tri-state on the override row: inherit (default) · Force Allow · Force Block. After this feature it is
the **only** control over availability; the category gate that previously preceded it is gone.

### Integration opt-in (relocated)

| Attribute | Before | After |
|---|---|---|
| Store | `acrossai_library_config[<slug>].enabled` (site option, shared with category config) | `acrossai_integrations[<slug>]` (site option, dedicated) |
| Absent value | `false` | `false` (unchanged, FR-021) |
| Reader | `AcrossAI_Ability_Library_Config::is_integration_enabled()` | `AcrossAI_Integration_Settings::is_enabled()` |
| Writer | Integrations page → REST | Settings API section on `acrossai-settings` |
| Capability | single filtered `current_user_can()` | unchanged shape (`PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY`) |

Not an access setting: it asks a third-party plugin to register its abilities at all.

## Retiring store — `acrossai_library_config`

```
{ "<category-or-integration-slug>": { "enabled": bool, "mode": "all"|"specific", "sub_keys": {"<slug>": bool} } }
```

**Two opposite defaults live in this one store** — an absent *category* means permitted, an absent
*integration* means disabled. Dispatch on `card_variant` **before** applying any default, or one of the two
is mis-translated (`BUG-SPARSE-STORAGE-UNIFORM-DEFAULT-ASSUMPTION`).

`MAX_KEYS`/`MAX_SUB_KEYS = 50` truncated `sub_keys` on the three categories exceeding 50 abilities
(`acrossai-block` 87, `acrossai-elementor` 62, `acrossai-rank-math` 61). **The stored pick list may
therefore be incomplete**; resolve category membership from `get_definitions()`, never from `sub_keys`,
and treat anything not explicitly `true` as unpicked.

## Translation rules

| Config state | Outcome |
|---|---|
| Category absent | nothing (was already permitted) |
| `enabled: false` | `site_allowed = false` for every ability in the category |
| `mode: "specific"` | `site_allowed = false` for every ability **not** `sub_keys[slug] === true` |
| `enabled: true`, `mode: "all"` | nothing |
| `card_variant: "integration"` | **not** translated — copied to `acrossai_integrations`, OR-monotonic |

Invariants: never overwrite an existing override row (FR-008); idempotent (FR-009); membership from the
definitions registry, not `wp_get_abilities()` and not `sub_keys` (FR-011).

## State — translation progress

| Key | Scope | Purpose |
|---|---|---|
| `acrossai_library_gate_migration_done` | **per site** (`add_option` claim, then `get_option`) | one run per site; claimed **before** the work so concurrent requests cannot both translate (SEC-008) |
| `acrossai_library_config` | network (`get_site_option`) | source; deleted on single-site only |

Per-site, because the source is network-wide while the target override table is per-site
(`$global = false`) — see research.md R1.
