# Phase 1 Data Model: Ability Suggestions Framework

## Entities

### 1. Suggestion Entry

A single hint declared by one ability's author about another ability.

| Field | Type | Required | Constraints | Notes |
|---|---|---|---|---|
| `slug` | string | ✅ | Non-empty | Target ability's registered name (e.g. `blocks/outline-post-blocks`). No shape validation (Decision 7). |
| `reason` | string | ✅ | Non-empty (by convention; framework does not enforce) | One-line English (or i18n'd) rationale the AI reads. |
| `saves` | string | ❌ | Free-form | Optional hint about savings, e.g. `"~29K tokens vs full page rewrite"`. Advisory, unparsed. |

**Validation rules (framework-level)**: The framework enforces no field-level validation — it accepts whatever the ability author returns. Tests SHOULD guard against empty `slug` / `reason` per-ability (spec Edge Cases).

**Ordering**: List position is significant. The Registry decoration preserves order via `array_values()` (Decision 3, mirroring Feature 088).

### 2. Suggestions List

An ordered list of `Suggestion Entry` items attached to a single ability, produced by `Ability_Definition::suggested_abilities()`.

- **Cardinality**: 0..N. Zero-length lists (empty return) are treated as "no suggestion field emitted" — the framework does not write an empty key.
- **Attachment point**: Injected by `push_definition()` into `args.meta.acrossai.suggested_abilities` on the ability's registration payload.
- **Deduplication**: None. If the author declares the same slug twice, both entries surface. Not a bug — allows two distinct reasons for the same target.

### 3. Suggestions Toggle

A single boolean option owned by the site administrator.

| Attribute | Value |
|---|---|
| WordPress option key | `acrossai_disable_ability_suggestions` |
| Default | `0` (feature enabled) |
| Storage type | Integer 0 or 1 (WordPress convention for checkboxes) |
| Read path | `AcrossAI_Ability_Library_Registry::get_definitions()` |
| Registered via | `register_setting()` on `admin_init` in `admin/Partials/SettingsMenu.php` |
| Sanitize callback | `sanitize_disable_ability_suggestions( $value ): int` — coerces truthy values to `1`, everything else to `0`. Parallel to Feature 088's `sanitize_disable_plugin_suggestions()` at line 298. |
| Cleanup | `delete_option( 'acrossai_disable_ability_suggestions' )` in `uninstall.php`, gated by `acrossai_abilities_uninstall_delete_data` (existing uninstall data-cleanup guard). |

## Relationships

```
Ability (declares) ────► SuggestionsList (of 0..N) ────► SuggestionEntry
                                                             │
                                                             └── points at another ability by slug (no FK enforcement)

Admin ────► SuggestionsToggle ────► (globally strips SuggestionsList from all Ability payloads when on)
```

## State transitions

Only one entity has state: `SuggestionsToggle`.

| From | Event | To | Effect on Ability payloads |
|---|---|---|---|
| unset / `0` | admin ticks checkbox | `1` | Every subsequent `get_definitions()` call strips `args.meta.acrossai.suggested_abilities` from every row. Ability's canonical `args` unchanged. |
| `1` | admin unticks checkbox | `0` | Suggestions immediately reappear in `get_definitions()` output. |
| any | plugin uninstall with `acrossai_abilities_uninstall_delete_data = 1` | option row deleted | Next fresh install starts at "unset" which is treated as `0` — default enabled. |
