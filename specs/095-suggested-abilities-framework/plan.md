# Implementation Plan: Ability Suggestions Framework

**Branch**: `095-suggested-abilities-framework` | **Date**: 2026-08-28 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/095-suggested-abilities-framework/spec.md`

## Summary

Add a per-ability `suggested_abilities()` declaration mechanism so ability authors can point AI callers at cheaper alternative abilities (e.g. `content/update-page` → `blocks/outline-post-blocks` + `blocks/update-post-block` for narrow edits). Declarations surface under `meta.acrossai.suggested_abilities` on `mcp-adapter-get-ability-info` and the plugin's Library REST endpoint. An admin kill-switch strips the field site-wide. The framework is a one-for-one mirror of Feature 088's `suggested_plugins` — same authoring surface, same Registry decoration, same negative-framing toggle convention — so the codebase has one convention instead of two.

## Technical Context

**Language/Version**: PHP 8.1+ (constitution §II line 152)
**Primary Dependencies**: WordPress 6.9+ core (Settings API, `wp_register_ability` via companion plugin); no new Composer or npm packages
**Storage**: WordPress `wp_options` (one new key: `acrossai_disable_ability_suggestions`, boolean stored as `0`/`1`)
**Testing**: PHPUnit 10 via `./vendor/bin/phpunit`; WP-less bootstrap (`tests/bootstrap.php`); mirrors existing Feature 088 test patterns
**Target Platform**: WordPress single-site (multisite deferred to future feature — matches Feature 088's current scope)
**Project Type**: WordPress plugin (single-project layout)
**Performance Goals**: O(1) per-ability decoration cost; zero I/O in the hot path (option read is WP core-cached)
**Constraints**: Payload byte-identical when framework produces no suggestions (spec SC-004); byte-identical when toggle disabled (spec SC-005)
**Scale/Scope**: ~325 registered abilities today. Initial batch of 4 overrides ships in this release. Framework must not add ANY per-ability payload weight when no override is declared.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Against `.specify/memory/constitution.md`:

| Principle | Verdict | Notes |
|---|---|---|
| I. Modular Architecture | ✅ | New logic contained to `Ability_Definition` + `AcrossAI_Ability_Library_Registry` + one settings section. No cross-module changes. |
| II. WordPress Standards | ✅ | PHPCS + PHPStan level 8 gates enforced. All new code in `AcrossAI_Abilities_Manager\` namespace with `acrossai_` prefixed hooks/options. |
| III. User-Centric Design | ✅ | Settings section uses WordPress Settings API (`register_setting` / `add_settings_field`) — identical pattern to Feature 088's "Plugin Suggestions" section. No custom form rendering. |
| IV. Security First | ✅ | Sanitize callback on the toggle (checkbox 0/1); capability check inherited from Settings page's `manage_options` gate. No AJAX, no form handlers to nonce. `esc_html` / `esc_attr` on every rendered value. |
| V. Extensibility Without Core Modification | ✅ | Ability author overrides a protected method — no filter registration, no editing of the Registry's own code. The Registry gains a new decoration STEP, but the sibling decoration (Feature 088's) is untouched. |
| VI. Reusability & DRY | ✅ | Framework is a structural mirror of Feature 088. `Ability_Definition::push_definition()` gets ~10 lines paralleling the existing `suggested_plugins` block. `AcrossAI_Ability_Library_Registry::apply_suggested_abilities_decoration()` copies the shape of `apply_suggested_plugins_decoration()`. No logic duplication — each does its own field. |
| VII. Definition of Done | ✅ (planned) | PHPUnit tests, PHPCS+PHPStan clean, security review inherent (Settings API + capability gate), no JS in this feature so ESLint N/A. |

**Result**: No violations. Proceeding to Phase 0.

## Project Structure

### Documentation (this feature)

```text
specs/095-suggested-abilities-framework/
├── plan.md              # This file
├── research.md          # Phase 0 output — Feature 088 patterns to mirror
├── data-model.md        # Phase 1 output — suggestion entry shape
├── quickstart.md        # Phase 1 output — how to add a suggestion to an ability
├── contracts/           # Phase 1 output — payload shape contracts
└── tasks.md             # Phase 2 output (created by /speckit-tasks)
```

### Source Code (repository root)

```text
includes/
├── Modules/Library/
│   ├── Ability_Definition.php                    # NEW method: suggested_abilities()
│   ├── AcrossAI_Ability_Library_Registry.php     # NEW decoration: apply_suggested_abilities_decoration()
│   └── AcrossAI_Ability_Library_Config.php       # (unchanged)
└── Abilities/Content/
    ├── Update_Page.php                           # NEW override: suggested_abilities()
    ├── Update_Post.php                           # NEW override
    ├── Update_Cpt_Item.php                       # NEW override
    └── Get_Post_Blocks.php                       # NEW override

admin/Partials/
└── SettingsMenu.php                              # NEW section: Ability Suggestions

uninstall.php                                     # NEW delete_option()

tests/phpunit/abilities/
├── Test_Ability_Suggestions_Framework.php        # NEW: structural + source-inspection
└── Test_Ability_Suggestions_Kill_Switch.php      # NEW: behavioural, toggles $__acrossai_test_options

phpunit.xml.dist                                   # Register the two new test files
README.txt                                         # CHANGELOG entry in Unreleased
```

**Structure Decision**: Single-project layout. This is a WordPress plugin — no split into apps/packages. Every file above already exists under this project root; changes are strictly additive per-file except `Ability_Definition.php`, `AcrossAI_Ability_Library_Registry.php`, `SettingsMenu.php`, and `uninstall.php` which get small localized additions.

## Complexity Tracking

*No Constitution violations to justify.*
