# Feature 088 — Ability-level suggested-plugins framework

**Status**: input brief for `/speckit-specify`. Written 2026-08-24.

## Problem

Every ability our plugin ships is a self-contained programmatic capability, but many abilities are functional *complements* to (or lesser versions of) specialised WordPress plugins the site admin might prefer to install. For example:

- `database/search-replace` covers programmatic DB-wide replacements safely, but Better Search Replace / Search Regex offer a richer admin UI + batched pagination + backup workflows that a human operator will find more comfortable for a one-off site migration.
- `elementor/replace-urls` handles Elementor URL migration correctly (including document-cache invalidation), but "Better Find and Replace" bakes in the same cache-regeneration automatically.
- Our SEO/forms/commerce categories (when we ship them) will overlap with Rank Math, WPForms, WooCommerce — plugins that themselves ship native `wp_register_ability()` abilities.

Today the plugin has **no way to say to the admin**: "for this scope, consider installing plugin X". The admin has to know the ecosystem on their own. And AI agents driving our abilities have no signal to relay a suggestion either.

This feature adds a framework-level, opt-out surface that any ability can use to advertise 1+ external WordPress plugins the admin may want. It also introduces a single admin-side kill-switch to disable suggestions site-wide.

**No ability declares suggestions yet** — this feature ships the framework only. A follow-up feature (089, deferred) retrofits the 4 search-replace abilities as the first consumers.

## Design

### 1. `Ability_Definition` base class — new optional template method

Add exactly one new optional method to `includes/Modules/Library/Ability_Definition.php`:

```php
/**
 * Return external WordPress plugins the site admin may consider as
 * alternatives or specialists for this ability's scope. Optional; default
 * is an empty list.
 *
 * Each entry: [ 'slug' => string, 'name' => string, 'reason' => string,
 *               'url'? => string, 'covers'? => string,
 *               'plugin_provides_abilities'? => bool,
 *               'acrossai_provides_integration'? => bool ]
 *
 * @return array<int,array<string,mixed>>
 */
protected function suggested_plugins(): array {
    return array();
}
```

Modify the existing `push_definition()` filter callback to auto-merge the result into `$args['meta']['acrossai']['suggested_plugins']` **only when non-empty**. Subclasses that don't override see zero payload change; subclasses that override see the list surface in every consumer (REST + MCP + Library UI) with no per-subclass boilerplate.

### 2. Suggested-plugin entry shape

```json
{
  "slug":                          "better-search-replace",
  "name":                          "Better Search Replace",
  "reason":                        "Batched pagination (20 000 rows/page) and site-URL deferred handling — recommended for large-scale migrations.",
  "url":                           "https://wordpress.org/plugins/better-search-replace/",
  "covers":                        "Database-wide replace with stronger serialization safety",
  "plugin_provides_abilities":     false,
  "acrossai_provides_integration": false
}
```

| Field | Type | Notes |
|---|---|---|
| `slug` | string | wp.org plugin slug — used for install detection via `is_plugin_active( "{slug}/{slug}.php" )` |
| `name` | string | Display name |
| `reason` | string | One-line "why this plugin for this ability" |
| `url` | string, optional | Falls back to `https://wordpress.org/plugins/{slug}/` if omitted |
| `covers` | string, optional | Short scope summary shown as tooltip |
| `plugin_provides_abilities` | bool, defaults false | Does the suggested plugin itself register `wp_register_ability()` abilities that light up agent discovery once installed? |
| `acrossai_provides_integration` | bool, defaults false | Does our own plugin ship an integration (via `AcrossAI_Integration_Ability_Base` or a category folder) that exposes `{plugin}/*` abilities when the plugin is active? |

The two booleans give the UI + agent 4 combinations:

| plugin_provides_abilities | acrossai_provides_integration | Label |
|---|---|---|
| `true`  | `false` | "Adds native abilities to discovery" |
| `false` | `true`  | "AcrossAI exposes abilities when installed" |
| `true`  | `true`  | "Native + AcrossAI integration abilities" |
| `false` | `false` | "UI-only — no agent abilities added" |

Author-curated at declaration time (not runtime-detected) — same pattern as `reason` and `covers`. Fields default to `false` when omitted so terse entries stay terse.

### 3. Registry enrichment + admin kill-switch

Modify `AcrossAI_Ability_Library_Registry::format_merged_ability()`:

- **Kill-switch first**: read the new site option `acrossai_disable_plugin_suggestions` (bool, default `false`). When `true`, return `suggested_plugins => []` regardless of what the ability declared. This one gate covers REST + MCP + Library UI.
- **Enrichment second** (only when the kill-switch is off): enrich each `suggested_plugins[i]` with `is_active: bool` computed once per request via `is_plugin_active( "{slug}/{slug}.php" )`.

Centralising both in the formatter means no per-subclass logic and no UI-only gating (an admin toggling off suggestions also removes them from MCP tool discovery — intent stays consistent across every consumer).

### 4. Admin settings — new "Disable the Plugin suggestion" checkbox

Extend the existing AcrossAI settings page at `admin.php?page=acrossai-settings`:

- **Setting name**: "Disable the Plugin suggestion"
- **Description**: "When enabled, no suggested-plugin cards appear on the Library page and no `suggested_plugins` field is emitted in ability payloads (REST + MCP). Ability behaviour is unaffected."
- **Storage**: WordPress option `acrossai_disable_plugin_suggestions` (bool)
- **Default**: unchecked — suggestions are shown (opt-out model)
- **Sanitize callback**: `absent → 0, present → 1` per `PATTERN-CHECKBOX-SANITIZE` (named public method, not closure)
- **Framework**: WP Settings API per `DEC-SETTINGS-API-DEVIATION` (scalar single-field additions to existing settings pages are accepted)
- **Uninstall**: delete the option inside the existing `$acrossai_delete_data` gate in `uninstall.php` per `PATTERN-UNINSTALL-DATA-GATE`

### 5. Library UI card section

Modify `src/js/ability-library/components/LibraryPage.js` (and any per-card sub-component):

- Destructure `suggested_plugins` from `meta.acrossai`
- Render a "Consider also" section below the existing card body **only when the list is non-empty**
- Each entry shows:
  - Name + one-line reason
  - Install-status badge — `Active` if `is_active`, otherwise `Install` link (uses `url` from entry, falling back to `https://wordpress.org/plugins/{slug}/`)
  - Ability-source pill derived from the two booleans per the label table above
- Keep visually low-key — this is advice, not action-required

No separate UI-side kill-switch check needed — the Registry has already emptied the list at payload time when the option is on.

## Reused utilities / patterns

- **`Ability_Definition`** — extended with one optional method + one auto-injection line in the existing `push_definition()` callback (no new class hierarchy, no new file)
- **`AcrossAI_Ability_Library_Registry::format_merged_ability()`** — the enrichment point for computed fields per `DEC-ABILITIES-DUAL-MODE-LIST`
- **`meta.acrossai.*`** namespace — new `suggested_plugins` field sits alongside existing `tab_group` / `sub_group` per `PATTERN-META-ACROSSAI-NAMESPACE` (Feature 041)
- **`is_plugin_active()`** — WP core; used for `is_active` enrichment
- **JSX card component pattern** — mirror existing ability-card sections
- **WP Settings API + `PATTERN-CHECKBOX-SANITIZE`** — for the new admin checkbox
- **`PATTERN-UNINSTALL-DATA-GATE`** — for the uninstall option delete
- **Data-provider-driven suite-contract tests** — same shape as Test_Feature_086_Suite_Contract etc.

## Common shape / defaults

- All new PHP is in `includes/Modules/Library/*` (framework extension) — no new `Abilities/` files in this feature
- New option key `acrossai_disable_plugin_suggestions` — no autoload flag needed (only read on ability-list responses; option size is 1 byte)
- All string inputs sanitized via WP core sanitizers (settings page uses `absint()` for the checkbox)
- All new user-facing strings wrapped in `__( '...', 'acrossai-abilities-manager' )`
- New meta field visible in `meta.show_in_rest = true` payloads (inherited from the parent meta object)
- No new capabilities — same `manage_options` gate as the settings page itself

## Bootstrap wiring

No new ability instantiations. Base class extension is invisible to `AcrossAI_Core_Abilities_Bootstrap.php`. Settings page file (located during implementation via `grep -rn "acrossai-settings"`) gains one Settings API registration for the new field.

## Testing

Under `tests/phpunit/abilities/`:

- **New**: `Test_Feature_088_Suggested_Plugins_Framework.php` — data-provider-driven source-inspection suite mirroring 081–087 pattern:
  - Default `suggested_plugins()` returns `[]`
  - Auto-inject inside `push_definition()` writes to `meta.acrossai.suggested_plugins` when non-empty
  - Auto-inject skipped when the method returns `[]`
  - Registry `format_merged_ability()` reads the `acrossai_disable_plugin_suggestions` option and empties the list when true
  - Registry enriches each entry with `is_active` (fixture: BSR is known-installed)
  - Ability-source booleans (`plugin_provides_abilities`, `acrossai_provides_integration`) round-trip and default to `false` when omitted
  - Existing abilities without overrides remain unchanged (regression check against a fixture ability)
  - Settings sanitize callback: `absent → 0, present → 1`
  - Uninstall option deletion is gated by `$acrossai_delete_data`

Target: ~12 golden-path tests + ~5 regression assertions.

## Delivery

Feature branch `088-ability-suggested-plugins-framework` off `main`. Spec-kit-driven per memory `feedback_use_speckit_pattern`:

1. **Now**: this planning brief committed to the branch.
2. **Next (user)**: `/speckit-specify docs/planning/088-*.md` → commits `specs/088-*/spec.md`.
3. **Then (user)**: `/speckit-plan` → commits `specs/088-*/plan.md` + `research.md` + `data-model.md` + `quickstart.md` + `contracts/`.
4. **Then (user)**: `/speckit-tasks` → commits `specs/088-*/tasks.md`.
5. **Then (optionally, user)**: `/speckit-checklist` → commits `specs/088-*/checklists/*.md`.
6. **Then (implementation)**: PHP + JSX + PHPUnit + settings + uninstall changes.
7. **Verify**: `php -l`, `vendor/bin/phpstan analyse`, `vendor/bin/phpunit --testsuite feature-088-unit`, full suite regression.
8. **Merge**: fast-forward to `main`, push origin. Same stacked-branch pattern as 080–087.

The final PR bundles the planning brief + full `specs/088-*/` directory + PHP + JSX + tests + settings + uninstall + phpunit config.
