# Implementation Plan: File-Manager Additions

**Branch**: `090-file-manager-additions` | **Date**: 2026-08-25 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/090-file-manager-additions/spec.md`

## Summary

Add four new abilities to the `file-manager/*` namespace: `append-file`, `create-directory`, `delete-directory`, `file-info`. All four are thin wrappers over WordPress core / PHP built-ins (`wp_mkdir_p`, `mkdir`, `rmdir`, `unlink`, `file_put_contents(..., FILE_APPEND)`, `RecursiveDirectoryIterator`, `stat`). Each mirrors the shape of an existing peer under `includes/Abilities/FileManager/`, reuses the same guard patterns (`File_Mods_Guard`, ABSPATH scope check, `PROTECTED_FILES` refusal), and requires no new utility classes or Composer dependencies.

Cross-check against two reference plugins was completed during planning: (a) `mcp-abilities-filesystem` (single-file, MIT-flavour, `filesystem/*` slugs) surfaced the four gaps; (b) `wp-file-manager` (elFinder-based, GPLv2, v8.0.4) was evaluated for library reuse — verdict: don't vendor elFinder, it's a 10+ MB volume-abstraction library that's overkill for four thin ability wrappers. Use WordPress core primitives, keep the module coherent with peer abilities from feature 089.

## Technical Context

**Language/Version**: PHP 8.1+ (per Constitution §II).
**Primary Dependencies**: WordPress 6.9+, `Ability_Definition` base class (`AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition`), `File_Mods_Guard` utility (`includes/Abilities/Utilities/File_Mods_Guard.php`), WordPress core `wp_register_ability()` API, `wp_mkdir_p()`. No new Composer or npm packages.
**Storage**: Filesystem under ABSPATH only. No database schema, options, meta, transients, or cron.
**Testing**: PHPUnit (`phpunit.xml.dist`) under `tests/phpunit/abilities/` following the existing `Test_Feature_089_File_Consolidation.php` structural-inspection convention. Static analysis via PHPStan level 8 (`phpstan.neon.dist`), lint via PHPCS WPCS strict (`phpcs.xml.dist`).
**Target Platform**: WordPress 6.9+ single-site and multisite installations on PHP 8.1+. Consumed by MCP clients through the abilities API and via WP-CLI (`wp ability call`).
**Project Type**: WordPress plugin (single project layout, module directory pattern per Constitution §Architecture).
**Performance Goals**: Every operation completes within one HTTP request timeout. `append` uses `FILE_APPEND | LOCK_EX` and returns immediately. `create-directory` is a bounded `wp_mkdir_p` call. `delete-directory` recursive walk uses the same `RecursiveDirectoryIterator` idiom as feature 089's `list-directory` (bounded to what the caller supplies) and reports partial state on error rather than throwing. `file-info` is a single `stat()` call.
**Constraints**: Every ability requires `manage_options` capability. Every path resolves under `realpath(ABSPATH)` via the parent-scope pattern from `Read_File.php:114-123`. Every write-capable ability honours `DISALLOW_FILE_MODS` / `DISALLOW_FILE_EDIT` via `File_Mods_Guard::blocked_response()`. Response envelopes match the shape used by existing `file-manager/*` peers so MCP clients handle them uniformly.
**Scale/Scope**: Four new PHP ability classes; one new bootstrap block (four `new FileManager\<Class>()` lines); one new PHPUnit test class; one new testsuite entry in `phpunit.xml.dist`; two edits to `docs/abilities-inventory.md` (counts + rows); one edit to `README.txt` (bullet under `= Unreleased =`). No existing behaviour changes.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Verdict | Notes |
|-----------|---------|-------|
| I. Modular Architecture | ✅ Pass | All changes contained to `includes/Abilities/FileManager/` (four new files) plus the shared bootstrap. Reuses existing `File_Mods_Guard`; introduces no cross-module coupling. |
| II. WordPress Standards Compliance | ✅ Pass | PHP 8.1+; new classes follow the PHPCS-clean pattern of `Read_File.php`, `Create_File.php`, `List_Directory.php` etc. Plugin Check compatible (no `eval`, no `extract`, no shell). No deprecated WP functions. |
| III. User-Centric Design | ✅ N/A | No admin UI change; DataForm/DataViews not touched. |
| IV. Security First (NON-NEGOTIABLE) | ✅ Pass | `manage_options` on every ability. Every path resolved via `realpath()` under ABSPATH. `append-file` refuses `wp-config.php` / `.htaccess` via `PROTECTED_FILES` (same shape as `Read_File`, `Delete_File`, and the four hardened/added abilities from feature 089). `delete-directory` refuses a hardcoded `PROTECTED_DIRS` list plus honours `DISALLOW_FILE_MODS`. Symlink descent is refused during recursive delete. `file-info` is read-only and requires the same capability. |
| V. Extensibility Without Core Modification | ✅ Pass | Internal additions to this plugin only; no third-party integration touched. |
| VI. Reusability & DRY | ✅ Pass | Reuses `File_Mods_Guard`, `Ability_Definition`, the `PROTECTED_FILES` guard pattern from `Delete_File.php:131-138`, and the ABSPATH parent-scope check from `Read_File.php:114-123`. No new utilities introduced. |
| VII. Definition of Done | ✅ Achievable | PHPUnit test class planned (structural, mirrors feature 089's convention). PHPStan level 8 target. PHPCS clean via peer-consistent style. Class naming follows the FileManager module convention (unprefixed class names, namespace-based prefixing). |

**No violations require justification.** Complexity Tracking section intentionally empty.

## Project Structure

### Documentation (this feature)

```text
specs/090-file-manager-additions/
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
│   ├── {all existing feature-089 files}     # unchanged
│   ├── Append_File.php                       # NEW
│   ├── Create_Directory.php                  # NEW
│   ├── Delete_Directory.php                  # NEW
│   └── File_Info.php                         # NEW
├── Utilities/
│   └── File_Mods_Guard.php                   # unchanged (reused)
└── AcrossAI_Core_Abilities_Bootstrap.php     # MODIFIED — four new instantiations

tests/phpunit/abilities/
└── Test_Feature_090_File_Manager_Additions.php  # NEW

phpunit.xml.dist                               # MODIFIED — add feature-090-unit testsuite
docs/abilities-inventory.md                    # MODIFIED — bump counts, add four rows
README.txt                                     # MODIFIED — append bullet under = Unreleased =
```

**Structure Decision**: Existing plugin layout preserved. All new PHP classes live under `includes/Abilities/FileManager/` with namespace `AcrossAI_Abilities_Manager\Includes\Abilities\FileManager`.

## Complexity Tracking

*None. Constitution Check passed without violations.*
