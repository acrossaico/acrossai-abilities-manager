# Implementation Plan: File Abilities Consolidation

**Branch**: `089-file-abilities-consolidation` | **Date**: 2026-08-25 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/089-file-abilities-consolidation/spec.md`

## Summary

Consolidate raw file I/O onto the `file-manager/*` slug namespace by (1) adding `list-directory`, `copy-file`, `move-file` so file-manager can fully replace the theme/plugin-scoped read/write/list/copy/move abilities; (2) closing a pre-existing security gap where `file-manager/create-file` and `file-manager/edit-file` do not refuse `wp-config.php` / `.htaccess` (unlike `read-file` and `delete-file`); (3) hard-deleting six duplicate ability classes under `themes/*` and `plugins/*` along with their bootstrap and category-registrar wiring, and updating docs/CHANGELOG so integrators can migrate.

## Technical Context

**Language/Version**: PHP 8.1+ (per Constitution §II)
**Primary Dependencies**: WordPress 6.9+, `Ability_Definition` base class (`AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition`), `File_Mods_Guard` utility (`includes/Abilities/Utilities/File_Mods_Guard.php`), WordPress core `wp_register_ability_category()` + `wp_register_ability()` API. No new Composer or npm packages.
**Storage**: Filesystem only — no database schema, no options, no meta. Reads/writes go through PHP native `file_get_contents`, `file_put_contents`, `copy`, `rename`, `RecursiveDirectoryIterator`, `RecursiveIteratorIterator` under ABSPATH.
**Testing**: PHPUnit (`phpunit.xml.dist`) under `tests/Unit/Abilities/FileManager/` following the existing convention for `Read_File`, `Create_File`, `Edit_File`, `Delete_File` if such tests exist; new WP_UnitTestCase-style tests otherwise. Static analysis via PHPStan level 8 (`phpstan.neon.dist`), lint via PHPCS (`phpcs.xml.dist`).
**Target Platform**: WordPress 6.9+ single-site and multisite installations on PHP 8.1+, invoked via the AcrossAI Abilities Manager plugin. No frontend surface; consumed by MCP clients through the abilities API and by WP-CLI via `wp ability`.
**Project Type**: WordPress plugin (single project layout, module directory pattern per Constitution §Architecture).
**Performance Goals**: Directory listings return within one HTTP request timeout even for the deepest plugin/theme trees typically found on WP installs, enforced by a bounded entry count (default 1000, capped at 5000) and depth (default 5). Copy/move complete within the same request for individual files up to the ABSPATH-wide `Read_File` cap of 5 MB used for reference; no chunking required.
**Constraints**: Every write MUST honour `DISALLOW_FILE_MODS` and `DISALLOW_FILE_EDIT` via `File_Mods_Guard::check('edit')`. Every path MUST resolve inside ABSPATH via `realpath()`-parent check. Every write path MUST refuse `wp-config.php` and `.htaccess` at ABSPATH root. `manage_options` capability required for every ability. Response envelopes MUST mirror the shape used by existing `file-manager/*` abilities so MCP clients can handle them uniformly.
**Scale/Scope**: Three new PHP ability classes added; six deleted; two existing classes hardened. Bootstrap changes (~10 lines net). Category registrars trimmed of dead assignments. Docs updated in three files (README, README.txt, docs/abilities-inventory.md) plus CHANGELOG. Tests: three new test classes for the added abilities plus two extended for the guard fix.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Verdict | Notes |
|-----------|---------|-------|
| I. Modular Architecture | ✅ Pass | Changes are contained to `includes/Abilities/FileManager/` (add three), `includes/Abilities/Themes/` + `includes/Abilities/Plugins/` (delete six), and the shared bootstrap. Explicitly *reduces* cross-module duplication — aligns with "No code duplication between modules is permitted". |
| II. WordPress Standards Compliance | ✅ Pass | PHP 8.1+ target; new classes follow existing PHPCS-clean pattern of `Read_File.php` etc.; PHPStan level 8 clean via same type annotations. No forbidden functions. Plugin Check compatible (no `eval`, no `extract`, no shell execution). |
| III. User-Centric Design | ✅ N/A | No admin UI change; DataForm/DataViews not touched. |
| IV. Security First (NON-NEGOTIABLE) | ✅ Pass — feature *closes* a gap | `manage_options` capability enforced on every new ability. All paths resolved via `realpath()` under ABSPATH. `wp-config.php` + `.htaccess` refused on all write paths (fixes the pre-existing hole in `create-file`/`edit-file`). File-mod lockout constants honoured via existing `File_Mods_Guard`. No new nonce surfaces (abilities API handles auth outside REST routes). |
| V. Extensibility Without Core Modification | ✅ Pass | Internal cleanup; no third-party integration touched. |
| VI. Reusability & DRY | ✅ Pass — feature *is* a DRY refactor | Reuses `File_Mods_Guard`, `Ability_Definition`, the `PROTECTED_FILES` constant pattern, and the ABSPATH-scoping logic from `Read_File`. Eliminates six duplicate ability classes. |
| VII. Definition of Done | ✅ Achievable | PHPUnit tests planned; PHPStan level 8 target; PHPCS clean; class prefixing follows existing FileManager convention (namespace-based `AcrossAI_Abilities_Manager\Includes\Abilities\FileManager` with unprefixed class names — matches `Read_File`, `Edit_File`, etc.); `AGENTS.md` standards met. |

**Naming note (VII gate refinement)**: Constitution §Code Quality requires `acrossai_` prefix on "PHP functions, hooks, filters, and class names". The existing FileManager ability classes (`Read_File`, `Create_File`, `Edit_File`, `Delete_File`) use unprefixed class names and rely on the `AcrossAI_Abilities_Manager\` namespace root for prefixing. This is the module's established convention; new classes follow suit. If the constitution is later interpreted to require literal `acrossai_` class-name prefixing, that would be a module-wide rename affecting existing ability classes and is out of scope for this feature.

**No violations require justification.** Complexity Tracking section is intentionally empty.

## Project Structure

### Documentation (this feature)

```text
specs/089-file-abilities-consolidation/
├── plan.md              # This file
├── spec.md              # Written by /speckit-specify
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (ability schemas)
├── checklists/
│   └── requirements.md  # Written by /speckit-specify
└── tasks.md             # Phase 2 output (/speckit-tasks)
```

### Source Code (repository root)

```text
includes/Abilities/
├── FileManager/
│   ├── Category_Registrar.php          # unchanged
│   ├── Read_File.php                    # unchanged (reference: PROTECTED_FILES pattern)
│   ├── Create_File.php                  # MODIFIED — add PROTECTED_FILES guard
│   ├── Edit_File.php                    # MODIFIED — add PROTECTED_FILES guard
│   ├── Delete_File.php                  # unchanged (reference: PROTECTED_FILES pattern)
│   ├── Read_Wp_Config.php               # unchanged (specialised wrapper — keep)
│   ├── Edit_Wp_Config.php               # unchanged (specialised wrapper — keep)
│   ├── Get_Wp_Config_Constant.php       # unchanged (specialised wrapper — keep)
│   ├── Read_Debug_Log.php               # unchanged (specialised wrapper — keep)
│   ├── Clear_Debug_Log.php              # unchanged (specialised wrapper — keep)
│   ├── {existing zip backup abilities}  # unchanged
│   ├── List_Directory.php               # NEW
│   ├── Copy_File.php                    # NEW
│   └── Move_File.php                    # NEW
├── Themes/
│   ├── Read_Theme_Code.php              # DELETED
│   ├── Read_Theme_Structure.php         # DELETED
│   ├── Edit_Theme_File.php              # DELETED
│   └── {other theme lifecycle files}    # unchanged
├── Plugins/
│   ├── Read_Plugin_Code.php             # DELETED
│   ├── Read_Plugin_Structure.php        # DELETED
│   ├── Manage_Plugin_Files.php          # DELETED
│   └── {other plugin lifecycle files}   # unchanged
├── Utilities/
│   └── File_Mods_Guard.php              # unchanged (reused)
└── AcrossAI_Core_Abilities_Bootstrap.php  # MODIFIED — swap 6 removed for 3 added

tests/Unit/Abilities/FileManager/
├── List_Directory_Test.php              # NEW
├── Copy_File_Test.php                   # NEW
├── Move_File_Test.php                   # NEW
├── Create_File_Test.php                 # NEW or EXTENDED — protected-file refusal
└── Edit_File_Test.php                   # NEW or EXTENDED — protected-file refusal

docs/abilities-inventory.md              # MODIFIED — remove 6 rows, add 3 rows
README.md, README.txt                    # MODIFIED — CHANGELOG + migration table
```

**Structure Decision**: Existing plugin layout is preserved. All new PHP classes live under `includes/Abilities/FileManager/` with namespace `AcrossAI_Abilities_Manager\Includes\Abilities\FileManager`. All deletions are file-level removals plus corresponding lines in `AcrossAI_Core_Abilities_Bootstrap.php`.

## Complexity Tracking

*None. Constitution Check passed without violations.*
