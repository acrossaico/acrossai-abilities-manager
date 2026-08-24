# Implementation Plan: Ability-level suggested-plugins framework

**Branch**: `088-ability-suggested-plugins-framework` | **Date**: 2026-08-24 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/088-ability-suggested-plugins-framework/spec.md`

## Summary

Add an optional `suggested_plugins()` template method to the existing `Ability_Definition` base class so any ability may declare 1+ external WordPress plugins the site admin can consider installing. The list flows through the existing meta payload into REST + MCP + Library UI consumers. A single admin kill-switch on the AcrossAI settings page hides all suggestions site-wide (opt-out, default off — suggestions shown). Purely additive framework; no ability changes behaviour in this feature. Retrofit of the 4 search-replace abilities is a deferred follow-up (Feature 089).

## Technical Context

**Language/Version**: PHP 8.1+ (per Constitution §II) + JavaScript ES2020+ (JSX for Library UI)
**Primary Dependencies**: WordPress 6.9+ core APIs (`is_plugin_active()`, WP Settings API, `wp_register_ability()`); `@wordpress/*` packages (Tier 1) already in use by the Library UI
**Storage**: One new WordPress option key `acrossai_disable_plugin_suggestions` (bool). No new DB tables. Ability metadata rides on the existing in-memory `meta.acrossai.*` payload — no persistence.
**Testing**: PHPUnit source-inspection suite (new `feature-088-unit` testsuite, matching the pattern established by Features 081–087). PHPStan level 8. WordPress Coding Standards via PHPCS. JS covered by existing ESLint config for `src/js/ability-library/*`.
**Target Platform**: WordPress 6.9+ / PHP 8.1+ single-site and multisite
**Project Type**: WordPress plugin (single-project layout — no separate frontend/backend split; PHP + built JSX under `src/`)
**Performance Goals**: Ability payload emission overhead MUST NOT exceed 5 ms per ability at read time (option lookup + `is_plugin_active()` per suggestion, cached in request via WP's option cache). Library UI additional render time MUST NOT exceed 50 ms per card with 3+ suggestions.
**Constraints**: 100% additive backwards compatibility — existing 500+ abilities and all payload consumers MUST see zero change when they don't declare `suggested_plugins()`. No new capabilities, no new endpoints, no new async work.
**Scale/Scope**: ~500 existing abilities untouched. Framework code lives in 2 PHP files + 1 JSX file + 1 settings-page addition + 1 uninstall gate + 1 new PHPUnit test file. Estimated ~250 lines of net-new PHP + ~80 lines of net-new JSX.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Evaluated against `.specify/memory/constitution.md` (v1.4.8):

| Principle | Gate | Verdict | Notes |
|---|---|---|---|
| **I. Modular Architecture** | Feature lives in one clear module (`includes/Modules/Library/`); no cross-module bleed | ✅ PASS | Framework extension of `Ability_Definition` (Library module); no new module created; per-ability metadata is a leaf field, not shared logic |
| **II. WordPress Standards Compliance** | WPCS strict, PHPStan L8, ESLint zero, Plugin Check clean, PHP 8.1+, multisite-compatible | ✅ PASS | All new code will use existing standards + tests; no forbidden functions; no SQL (option access via `get_option()` / WP Settings API only) |
| **III. User-Centric Design (NON-NEGOTIABLE)** | Admin UIs use DataForm / DataViews | ✅ PASS | The Library-card UI addition is a display-only section (no form). The new admin checkbox is a single scalar field on the existing settings page — WP Settings API accepted per `DEC-SETTINGS-API-DEVIATION` (in memory) |
| **IV. Security First (NON-NEGOTIABLE)** | Sanitize input, escape output, nonces, capability gates | ✅ PASS | Checkbox uses `absint()` sanitize; setting saved via WP Settings API (nonce built-in); read via `get_option()`; escaped at render (`esc_html()` on suggestion strings, `esc_url()` on install links); `manage_options` gate inherited from existing settings page |
| **V. Extensibility Without Core Modification** | New feature via hooks/extension points; graceful degradation | ✅ PASS | Purely additive template method + additive meta field. `AcrossAI_Integration_Ability_Base` pattern (Feature 060) already sets precedent for optional framework surfaces. Abilities without overrides are 100% unchanged. |
| **VI. Reusability & DRY Principle** | Reuse existing utilities; extract shared logic before second use | ✅ PASS | Enrichment (`is_active`) lives in one place — `format_merged_ability()`. UI card component reused; new "Consider also" section is a small addition, not a duplicate render path |
| **VII. Definition of Done** | PHPCS zero, PHPStan L8 zero, ESLint zero, security review, PHPUnit, DataForm/DataViews compliance, `acrossai_` prefix, AGENTS.md standards | ✅ PASS (planned) | All 11 gates satisfied by verification steps in this plan |

**Result**: All gates PASS. No violations to justify; Complexity Tracking section not required.

## Project Structure

### Documentation (this feature)

```text
specs/088-ability-suggested-plugins-framework/
├── plan.md              # This file (/speckit-plan output)
├── research.md          # Phase 0 output (/speckit-plan)
├── data-model.md        # Phase 1 output (/speckit-plan)
├── quickstart.md        # Phase 1 output (/speckit-plan)
├── contracts/           # Phase 1 output (/speckit-plan)
│   ├── ability_definition_template_method.md
│   ├── meta_suggested_plugins_field.md
│   └── admin_setting_kill_switch.md
├── checklists/
│   └── requirements.md  # /speckit-specify output (already exists)
└── tasks.md             # /speckit-tasks output (NOT created here)
```

### Source Code (repository root)

Single-project WordPress-plugin layout — the existing structure is used, no new top-level directories are introduced:

```text
includes/
└── Modules/Library/
    ├── Ability_Definition.php                       # MODIFIED — new suggested_plugins() template method + auto-inject in push_definition()
    └── AcrossAI_Ability_Library_Registry.php        # MODIFIED — kill-switch + is_active enrichment in format_merged_ability()

src/js/ability-library/
└── components/
    └── LibraryPage.js                               # MODIFIED — new "Consider also" section on ability cards (or per-card sub-component)

admin/Partials/                                      # OR existing settings-page file (locate via grep during implementation)
└── <existing-settings-page>.php                     # MODIFIED — register new WP Settings API field "Disable the Plugin suggestion"

tests/phpunit/abilities/
└── Test_Feature_088_Suggested_Plugins_Framework.php # NEW — data-provider-driven source-inspection suite

phpunit.xml.dist                                     # MODIFIED — add feature-088-unit testsuite
uninstall.php                                        # MODIFIED — delete acrossai_disable_plugin_suggestions inside $acrossai_delete_data gate
docs/planning/088-ability-suggested-plugins-framework.md   # EXISTS (planning brief for this feature)
```

**Structure Decision**: Single-project WordPress plugin. Framework code lives in the existing `includes/Modules/Library/` module (extends the base + registry); UI code lives in `src/js/ability-library/`; settings + uninstall are one-line additions to existing files. No new module directory required.

## Complexity Tracking

*Not applicable — Constitution Check passes without violations.*
