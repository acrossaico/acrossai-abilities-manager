# Quickstart — Feature 088 (ability-level suggested-plugins framework)

**Audience**: developer implementing this feature or reviewing it in a PR.

## What you get after this feature lands

- **New optional template method** `Ability_Definition::suggested_plugins()`. Override it in any ability subclass to advertise one or more external WordPress plugins the site admin might consider installing.
- **New admin checkbox** on the AcrossAI settings page: "Disable the Plugin suggestion" (opt-out, default off). One toggle site-wide.
- **New Library UI section** "Consider also" on ability cards that declare suggestions. Shows plugin name + reason + install-status badge + source-label pill.
- **No behaviour change** on any of the 500+ existing abilities. They gain no suggestions until individually retrofitted in a follow-up feature (089).

## Try it in 5 minutes

### 1. Add a suggestion to an ability

Edit any ability class (e.g. one from `includes/Abilities/Content/`) and add:

```php
protected function suggested_plugins(): array {
    return array(
        array(
            'slug'                          => 'better-search-replace',
            'name'                          => 'Better Search Replace',
            'reason'                        => 'For UI-driven site migrations with batched pagination.',
            'plugin_provides_abilities'     => false,
            'acrossai_provides_integration' => false,
        ),
    );
}
```

### 2. Reload the Library page

Visit `admin.php?page=acrossai-abilities-library`. Find your ability card. You should see a new "Consider also" section listing Better Search Replace with:
- The one-line reason
- An **Active** badge (if BSR is currently active) or an **Install** link (if not)
- A **UI-only** pill (because both boolean flags are `false`)

### 3. Try the kill-switch

Visit `admin.php?page=acrossai-settings`. Find the new **"Disable the Plugin suggestion"** checkbox. Check it → save.

Reload the Library page. The "Consider also" section is gone on every card. Uncheck the setting → it comes back on next page load.

### 4. Inspect the REST payload

```bash
curl -s -u admin:app-password 'http://your-site.local/wp-json/{namespace}/abilities' \
  | jq '.[] | select(.name=="your/ability-slug") | .args.meta.acrossai.suggested_plugins'
```

With the kill-switch off, you should see the suggestion array with `is_active` populated. With the kill-switch on, the field is absent from the payload entirely.

## Where the code lives after implementation

| Concern | File | Purpose |
|---|---|---|
| Template method + auto-inject | `includes/Modules/Library/Ability_Definition.php` | Adds `suggested_plugins()` (default `[]`) and merges into `meta.acrossai.suggested_plugins` inside `push_definition()` |
| Kill-switch + `is_active` enrichment | `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php` | Reads the option, strips suggestions when disabled, enriches each entry with install status |
| Library UI section | `src/js/ability-library/components/LibraryPage.js` | Destructures `meta.acrossai.suggested_plugins` and renders the "Consider also" block |
| Settings page checkbox | Existing settings page file (locate via `grep -rn "acrossai-settings"`) | One `register_setting()` + `add_settings_field()` pair + named sanitize callback |
| Uninstall cleanup | `uninstall.php` | Adds one `delete_option()` line inside the existing `$acrossai_delete_data` gate |
| Test suite | `tests/phpunit/abilities/Test_Feature_088_Suggested_Plugins_Framework.php` | Data-provider-driven source-inspection + runtime assertions on fixtures |
| PHPUnit config | `phpunit.xml.dist` | New `feature-088-unit` testsuite entry |

## Backwards compatibility guarantee

Every ability that does NOT override `suggested_plugins()` produces a byte-identical payload before and after this feature. All 500+ existing abilities in the plugin fall into this category. If you see a `suggested_plugins` field appear anywhere unexpected, it's a bug.

## Verification commands

```bash
# 1. Syntax check
php -l includes/Modules/Library/Ability_Definition.php
php -l includes/Modules/Library/AcrossAI_Ability_Library_Registry.php

# 2. Static analysis
vendor/bin/phpstan analyse includes/Modules/Library/ --level=8

# 3. Feature-088 tests only
vendor/bin/phpunit --testsuite feature-088-unit

# 4. Full suite regression
vendor/bin/phpunit

# 5. Coding standards
vendor/bin/phpcs includes/Modules/Library/

# 6. JS lint (Library UI)
npm run lint -- src/js/ability-library/
```

## Where to go next

- Ship the framework via Feature 088 (this feature) merged to main.
- Retrofit the 4 search-replace abilities with `suggested_plugins()` overrides in Feature 089 (deferred, planned separately).
- Consider a `DEC-ABILITY-SUGGESTED-PLUGINS` durable memory entry documenting the `meta.acrossai.suggested_plugins[]` contract for third-party integrators.
