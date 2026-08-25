# Implementation Plan: File-Manager Allowlists + Redactor

**Branch**: `092-file-manager-allowlists-redactor` | **Date**: 2026-08-25 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/092-file-manager-allowlists-redactor/spec.md`

## Summary

Add three admin-controlled settings for the `file-manager/*` ability set — a write allowlist (default `['wp-content']`), a read allowlist (default `[]` / unrestricted sentinel), and a secret-redaction pattern set (8 built-in patterns + custom literals). Ship as a new "File Manager" tab at `admin.php?page=acrossai-settings` with three React-driven panels. Two new utility classes (`Path_Allowlist_Guard`, `Secret_Redactor`) provide the enforcement primitives. 10 file-manager ability classes get one guard call each (8 write abilities) or a guard call + redactor call + schema update (2 content-read abilities). `read-file`'s existing `PROTECTED_FILES` refusal for wp-config.php / .htaccess is removed — those files are now readable + redacted. Everything else (Ability_Definition, File_Mods_Guard, Wp_Filesystem_Init, all zip abilities, list-directory, file-info, wp-config wrappers) is untouched.

Full design table lives in the approved planning doc at `/Users/raftaar1191/.claude/plans/cryptic-plotting-summit.md`.

## Technical Context

**Language/Version**: PHP 8.1+, JS via `@wordpress/scripts` + React 18.
**Primary Dependencies**: WordPress 6.9+, `Wp_Filesystem_Init` from feature 091, `File_Mods_Guard` from earlier features, `Ability_Definition` base class. Frontend: `@wordpress/element`, `@wordpress/data`, `@wordpress/api-fetch`, `@wordpress/components`.
**Storage**: Three new `wp_options` entries (write allowlist, read allowlist, redaction config). No custom tables.
**Testing**: PHPUnit structural-inspection style (matches Test_Feature_089/090/091). PHPStan level 8. PHPCS WPCS strict.
**Target Platform**: WordPress 6.9+ single-site and multisite on PHP 8.1+. Admin UI in wp-admin.
**Project Type**: WordPress plugin.
**Performance**: Guards run per-ability-call and are O(number of allowlist entries) — small in practice. Redactor is single-pass over content; regex library is compiled once per request. No new DB queries in the hot path (options are cached).
**Constraints**: `manage_options` capability on every ability + REST route. Nonce verification via `X-WP-Nonce`. All input paths resolved through `realpath()` + ABSPATH prefix check before use. `Ability_Definition` MUST NOT be touched (sibling plugin `acrossai-buddyboss` compatibility).
**Scale/Scope**: 2 new PHP utility classes; 10 ability class files edited; 1 new tab registrar; 1 new REST controller; 1 new webpack entry + React root with 4 components; 1 activator update. ~2500 lines of code net.

## Constitution Check

| Principle | Verdict | Notes |
|-----------|---------|-------|
| I. Modular Architecture | ✅ | New utilities live in `includes/Abilities/Utilities/`; new tab class in `admin/Partials/`; new REST controller in `includes/Modules/Settings/REST/`. Peer to existing pieces, no cross-module coupling. |
| II. WordPress Standards | ✅ | Uses core options API, core REST controller pattern, `@wordpress/*` packages only. No new PHPCS suppressions. |
| III. User-Centric Design | ✅ | New admin tab uses `@wordpress/components` (Constitution allows either DataViews or Components for admin UI; DataViews isn't a natural fit for a tree/checkbox settings panel). |
| IV. Security First (NON-NEGOTIABLE) | ✅ | Every ability keeps its existing guards; new guard chains after them. REST controller enforces `manage_options` + nonce. Input paths resolved via `realpath` under ABSPATH before use. `wp-config.php` write refusal preserved on all write abilities. Redactor is defence-in-depth. |
| V. Extensibility Without Core Modification | ✅ | No third-party plugin modified. |
| VI. Reusability & DRY | ✅ | Two utilities centralize logic. React panels share a common tree component. |
| VII. Definition of Done | ✅ | Structural PHPUnit tests planned; PHPStan level 8; PHPCS WPCS strict. |

No violations. Complexity Tracking intentionally empty.

## Project Structure

```text
includes/Abilities/
├── FileManager/
│   ├── {8 write ability class files}         # add Path_Allowlist_Guard call
│   ├── Read_File.php                         # drop PROTECTED_FILES; add allowlist + redactor
│   └── Read_Debug_Log.php                    # add allowlist + redactor
├── Utilities/
│   ├── File_Mods_Guard.php                   # UNCHANGED
│   ├── Wp_Filesystem_Init.php                # UNCHANGED
│   ├── Path_Allowlist_Guard.php              # NEW
│   └── Secret_Redactor.php                   # NEW
└── AcrossAI_Core_Abilities_Bootstrap.php     # UNCHANGED

includes/Modules/Settings/REST/
└── File_Manager_Settings_Controller.php      # NEW (write / read / redaction sub-routes)

includes/Modules/Library/
└── Ability_Definition.php                    # UNCHANGED (protects acrossai-buddyboss)

includes/AcrossAI_Activator.php               # add three add_option() calls
includes/Main.php                             # wire the new tab class

admin/Partials/
├── SettingsMenu.php                          # UNCHANGED
├── Core_Settings_Menu.php                    # UNCHANGED
└── File_Manager_Settings_Menu.php            # NEW

src/js/file-manager-settings/
├── index.js                                  # NEW (React root)
├── components/
│   ├── FileManagerSettings.jsx               # NEW (orchestrator)
│   ├── WriteAllowlistPanel.jsx               # NEW
│   ├── ReadAllowlistPanel.jsx                # NEW
│   └── RedactionPanel.jsx                    # NEW
└── store/index.js                            # NEW (wp.data store for the 3 settings)

src/scss/file-manager-settings/admin.scss     # NEW
webpack.config.js                             # add file-manager-settings entry

tests/phpunit/abilities/
├── Test_Feature_089_File_Consolidation.php   # update read-file assertions
├── Test_Feature_091_Wp_Filesystem_Migration.php  # update guard-chain assertions
└── Test_Feature_092_Allowlists_And_Redactor.php  # NEW

phpunit.xml.dist                              # add feature-092-unit testsuite
docs/abilities-inventory.md                   # footer note
README.txt                                    # Unreleased entry
```

**Structure Decision**: Existing layout preserved. All new files live in the module directories they logically belong to.

## Complexity Tracking

*None. Constitution Check passed without violations.*
