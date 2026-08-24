# Contract: `meta.acrossai.suggested_plugins[]` payload field

**Feature**: 088 | **Location**: `$args['meta']['acrossai']['suggested_plugins']` in every ability payload

## Presence rules

The field is present in the payload if AND ONLY IF ALL of the following hold:

1. The ability's `suggested_plugins()` template method returned a non-empty array, AND
2. The site option `acrossai_disable_plugin_suggestions` is `false` (default), AND
3. `AcrossAI_Ability_Library_Registry::format_merged_ability()` has run (i.e. we are serving a REST / MCP / Library payload — not calling the ability directly)

If any of the three fails, the field is absent from the payload entirely (not present-as-empty-array).

## Entry shape (per item)

```json
{
  "slug":                          "wordpress-org-slug",       // string, required
  "name":                          "Display Name",             // string, required
  "reason":                        "One-line why this plugin", // string, required
  "url":                           "https://...",              // string, optional; default: https://wordpress.org/plugins/{slug}/
  "covers":                        "Short scope summary",      // string, optional; default: ""
  "plugin_provides_abilities":     false,                       // bool, optional; default: false
  "acrossai_provides_integration": false,                       // bool, optional; default: false
  "is_active":                     true                         // bool, enriched by Registry — never author-provided
}
```

## Consumer contracts

### REST consumer

- `GET /wp-json/{namespace}/abilities` and any single-ability endpoint MUST return the field as documented above (including `is_active`) when the field is present.
- No new REST route is added. Field rides on the existing ability response body under `args.meta.acrossai.suggested_plugins`.

### MCP consumer

- `discover-abilities` tool result includes the field in each ability's meta when present.
- No MCP-side stripping: consumers see exactly what REST sees.

### Library UI consumer

- `LibraryPage.js` reads `meta.acrossai.suggested_plugins` and renders the "Consider also" section only when the array is present AND non-empty.
- The `is_active` flag drives the badge (Active vs Install link).
- The `plugin_provides_abilities` + `acrossai_provides_integration` flags drive the source label pill (4-way per FR-010).

## Enrichment contract (`format_merged_ability()`)

1. Read `get_option( 'acrossai_disable_plugin_suggestions', false )`
   - If truthy → strip `suggested_plugins` entirely from the outgoing meta; return
2. Otherwise, for each entry in `$meta['acrossai']['suggested_plugins']`:
   - Compute `$is_active = function_exists( 'is_plugin_active' ) && is_plugin_active( sprintf( '%s/%s.php', $entry['slug'], $entry['slug'] ) )`
   - Attach `$is_active` to the entry
3. Return the updated meta

## Validation + safety contract

- The enrichment step MUST NOT fatal on:
  - Missing required keys in a declared entry (drop the entry silently)
  - Missing optional keys (fall back to defaults defined in data-model.md)
  - `is_plugin_active()` not yet available at the point of call (guard via `function_exists()`)
  - Non-array `suggested_plugins` value (coerce to `[]`)
- All string fields serving into the Library UI MUST be escaped at the render boundary (`esc_html()`, `esc_url()`), not at declaration
- Entry order is preserved: authors declare intent, Registry does not re-sort

## Test coverage expectations

- Golden: with kill-switch off and a fixture ability declaring one entry → REST payload contains the entry with `is_active` set correctly
- Kill-switch on: same fixture → REST payload has NO `suggested_plugins` field on any ability
- Author omits `plugin_provides_abilities` + `acrossai_provides_integration` → payload contains both as `false`
- Author declares a malformed entry (missing `slug`) alongside a valid entry → payload contains only the valid entry
- Entry with slug pointing to an active plugin → `is_active: true`; entry with slug pointing to an inactive or missing plugin → `is_active: false`
