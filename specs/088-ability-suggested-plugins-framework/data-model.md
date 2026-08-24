# Phase 1 — Data Model: Ability-level suggested-plugins framework

**Feature**: 088 | **Date**: 2026-08-24

## Entities

### 1. Suggested Plugin Entry

Represents one external WordPress plugin an ability author recommends for the ability's scope. Attached to an ability at declaration time by overriding `suggested_plugins()`; propagated through payload consumers as an item inside `meta.acrossai.suggested_plugins[]`.

**Fields** (all string/bool primitives — no nested structures):

| Field | Type | Required | Default | Description |
|---|---|---|---|---|
| `slug` | string | ✅ | — | wordpress.org plugin slug. Used for install-status detection (`is_plugin_active( "{slug}/{slug}.php" )`) and as fallback in URL construction. Sanitised at output via `sanitize_key()`. |
| `name` | string | ✅ | — | Display name. Escaped at output via `esc_html()`. |
| `reason` | string | ✅ | — | One-line explanation of why this plugin fits this ability's scope. Escaped at output via `esc_html()`. |
| `url` | string | ❌ | `https://wordpress.org/plugins/{slug}/` | Override link target. Escaped at output via `esc_url()`. |
| `covers` | string | ❌ | `''` | Short scope summary (shown as tooltip). Escaped via `esc_html()`. |
| `plugin_provides_abilities` | bool | ❌ | `false` | Does the suggested plugin register its own `wp_register_ability()` abilities? Used to compute the source-label pill in the UI. |
| `acrossai_provides_integration` | bool | ❌ | `false` | Does our own plugin ship an `AcrossAI_Integration_Ability_Base` subclass (or equivalent) that exposes abilities when this plugin is active? |
| `is_active` | bool | (enriched at runtime) | — | Computed by `AcrossAI_Ability_Library_Registry::format_merged_ability()`. Not persisted; not author-provided. |

**Validation rules** (enforced at Registry enrichment time, not at declaration):
- `slug`, `name`, `reason` MUST all be non-empty strings; malformed entries are dropped silently (FR-009).
- All string fields MUST be < 500 chars — over-long fields are truncated at output with an ellipsis (defensive UI, not a hard rule at declaration).
- Boolean fields default to `false` when the author omits them (per FR-010 four-way pill).

**State transitions**: none. Entries are immutable metadata; the only runtime mutation is Registry enrichment adding `is_active`.

**Relationships**:
- Each entry belongs to exactly one ability (via containment in that ability's `meta.acrossai.suggested_plugins[]` array)
- Each ability may have zero or more entries

### 2. Plugin Suggestion Kill-switch

A site-wide boolean setting owned by the AcrossAI settings page. When enabled, all suggestion metadata is stripped from every payload consumer.

**Storage**: WordPress option, key `acrossai_disable_plugin_suggestions`.

**Fields**:

| Field | Type | Default | Description |
|---|---|---|---|
| value | bool | `false` | `true` = disable suggestions site-wide; `false` = show suggestions (opt-out model per FR-006) |

**Validation rules**:
- Sanitise callback: absent form field → `0`; present form field → `1` (per `PATTERN-CHECKBOX-SANITIZE`)
- Never accept other values from the settings page save handler

**State transitions**:

```
[default: false]
  ↓ admin checks the checkbox + saves
[true: suggestions hidden site-wide]
  ↓ admin unchecks + saves
[false: suggestions visible]

Also:
[any state]
  ↓ plugin uninstall + $acrossai_delete_data = true
[option removed from database]
```

**Relationships**: none — single scalar, no foreign references. Applied globally to every ability payload emission.

### 3. Install Status (computed)

A per-request, per-entry boolean flag indicating whether the suggested plugin is currently installed AND active on this WordPress site. Not stored; derived from WP core's `active_plugins` option at read time.

**Fields**:

| Field | Type | Description |
|---|---|---|
| `is_active` | bool | Result of `is_plugin_active( "{slug}/{slug}.php" )` for the entry's slug |

**Validation rules**:
- If the plugin file path doesn't match the standard `{slug}/{slug}.php` convention, `is_active` is `false` (silent — no error surfaced)
- Computation happens exactly once per entry per request

**State transitions**: none — computed fresh on every payload emission.

**Relationships**: attached to a Suggested Plugin Entry at Registry-enrichment time.

## Payload shape (canonical)

The composite object as it appears in an ability payload (REST / MCP / Library UI):

```json
{
  "name": "example/some-ability",
  "args": {
    "label": "Some Ability",
    "meta": {
      "acrossai": {
        "tab_group": "example",
        "sub_group": "example-sub",
        "sub_group_label": "Example Sub",
        "suggested_plugins": [
          {
            "slug": "better-search-replace",
            "name": "Better Search Replace",
            "reason": "Batched pagination and site-URL deferred handling — recommended for large-scale migrations.",
            "url": "https://wordpress.org/plugins/better-search-replace/",
            "covers": "Database-wide replace with stronger serialization safety",
            "plugin_provides_abilities": false,
            "acrossai_provides_integration": false,
            "is_active": true
          }
        ]
      }
    }
  }
}
```

When the kill-switch is on OR the ability declares no suggestions, `meta.acrossai.suggested_plugins` is absent from the payload entirely (not present-as-empty-array). This keeps existing 500+ ability payloads byte-identical to pre-feature.

## Ecosystem placement

- **New field**: `meta.acrossai.suggested_plugins[]` — joins the existing `tab_group`, `sub_group`, `sub_group_label` triplet under the `PATTERN-META-ACROSSAI-NAMESPACE` convention (Feature 041).
- **New option key**: `acrossai_disable_plugin_suggestions` — joins the existing options in the plugin's namespace (no autoload flag needed; read only at ability payload time).
- **No new tables, no new REST routes, no new REST fields at top level** — everything rides on existing structures.
