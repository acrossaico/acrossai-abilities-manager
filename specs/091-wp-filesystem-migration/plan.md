# Implementation Plan: WP_Filesystem Migration

**Branch**: `091-wp-filesystem-migration` | **Date**: 2026-08-25 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/091-wp-filesystem-migration/spec.md`

## Summary

Convert 19 `file-manager/*` ability classes from native PHP filesystem calls to `WP_Filesystem`. Add a shared `Wp_Filesystem_Init` utility. Refactor two recursive walks (`List_Directory`, `Delete_Directory`) to use `$fs->dirlist()` instead of `RecursiveIteratorIterator`. Shrink `file-manager/file-info` schema by removing `ctime` and `atime` (BREAKING). Defer three `ZipArchive`-based abilities to a follow-up feature 092. Do not touch `Ability_Definition` or `File_Mods_Guard`. Update structural tests in Test_Feature_089 and Test_Feature_090. Add Test_Feature_091 with cross-cutting assertions. Remove ~20 `phpcs:ignore WordPress.WP.AlternativeFunctions` suppressions.

The full design (native-to-WP_Filesystem mapping, per-file walkthrough, special cases, reference plugin idioms) lives in the approved planning doc at `/Users/raftaar1191/.claude/plans/cryptic-plotting-summit.md`.

## Technical Context

**Language/Version**: PHP 8.1+ (per Constitution §II).
**Primary Dependencies**: WordPress 6.9+ (`WP_Filesystem_Base` and its four transport implementations shipped in core), existing `Ability_Definition` base class, existing `File_Mods_Guard` utility. No new Composer / npm packages.
**Storage**: Filesystem under ABSPATH (plus `dirname(ABSPATH)` for the wp-config.php-above-root case). No database changes.
**Testing**: PHPUnit structural-inspection style (matches Test_Feature_089 and Test_Feature_090). PHPStan level 8. PHPCS WPCS strict.
**Target Platform**: WordPress 6.9+ on PHP 8.1+, both single-site and multisite. Every `FS_METHOD` transport supported by WordPress core (`direct`, `ftpext`, `ftpsockets`, `ssh2`).
**Project Type**: WordPress plugin (single project).
**Performance Goals**: On `direct` transport, migrated abilities perform within ±10% of the pre-migration native calls (WP_Filesystem_Direct is a thin wrapper). On FTP/SSH transports, latency is dominated by network round-trips; abilities target completion within one HTTP request timeout for the common single-file operations.
**Constraints**: `manage_options` capability on every ability. `File_Mods_Guard` runs before filesystem init. `PROTECTED_FILES` / `PROTECTED_DIRS` refusals happen before writes. `Ability_Definition` and sibling AcrossAI plugin `acrossai-buddyboss` MUST NOT break.
**Scale/Scope**: 19 ability class files rewritten (execute() bodies only), 1 new utility class, 1 new test class, 2 test classes updated for new idioms, 4 support-file edits.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Verdict | Notes |
|-----------|---------|-------|
| I. Modular Architecture | ✅ Pass | Contained to `includes/Abilities/FileManager/` + one new utility. Reduces duplication by centralising the WP_Filesystem init pattern. |
| II. WordPress Standards | ✅ Pass | Uses the WordPress-canonical filesystem API. Removes ~20 PHPCS suppressions for `WordPress.WP.AlternativeFunctions` — the migration actively closes long-standing standards violations. |
| III. User-Centric Design | ✅ N/A | No admin UI change. |
| IV. Security First (NON-NEGOTIABLE) | ✅ Pass — feature closes a portability gap | All existing guards (`manage_options`, `File_Mods_Guard`, `PROTECTED_FILES`, `PROTECTED_DIRS`, `confirm:true`, ABSPATH scoping) run BEFORE the filesystem transport is engaged. Adding `WP_Filesystem` doesn't weaken any guard; on non-`direct` transports it strengthens overall behaviour by preventing silent write failures. |
| V. Extensibility Without Core Modification | ✅ Pass | Internal refactor. |
| VI. Reusability & DRY | ✅ Pass | The new `Wp_Filesystem_Init` utility exists precisely to avoid duplicating the boilerplate across 19 ability classes. |
| VII. Definition of Done | ✅ Achievable | PHPUnit + PHPStan level 8 + PHPCS gates all achievable; class naming follows module convention. |

**No violations require justification.** Complexity Tracking section intentionally empty.

## Project Structure

### Documentation (this feature)

```text
specs/091-wp-filesystem-migration/
├── plan.md              # This file
├── spec.md              # Written by /speckit-specify
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
├── checklists/
│   └── requirements.md  # Written by /speckit-specify
└── tasks.md             # Phase 2 output (/speckit-tasks)
```

### Source Code (repository root)

```text
includes/Abilities/
├── FileManager/
│   ├── {19 migrated ability class files}         # execute() bodies rewritten
│   ├── Create_Zip_Backup.php                     # UNCHANGED (deferred to feature 092)
│   ├── Extract_Zip_Backup.php                    # UNCHANGED (deferred)
│   ├── Upload_Zip_Backup.php                     # UNCHANGED (deferred)
│   └── Category_Registrar.php                    # UNCHANGED
├── Utilities/
│   ├── File_Mods_Guard.php                       # UNCHANGED (reused)
│   └── Wp_Filesystem_Init.php                    # NEW (shared init helper)
└── AcrossAI_Core_Abilities_Bootstrap.php         # comment-only edit

includes/Modules/Library/
└── Ability_Definition.php                        # UNCHANGED (protects acrossai-buddyboss)

tests/phpunit/abilities/
├── Test_Feature_089_File_Consolidation.php       # assertion strings updated
├── Test_Feature_090_File_Manager_Additions.php   # assertion strings updated
└── Test_Feature_091_Wp_Filesystem_Migration.php  # NEW

phpunit.xml.dist                                   # add feature-091-unit testsuite
docs/abilities-inventory.md                        # footer note on transport
README.txt                                         # append under = Unreleased =
```

**Structure Decision**: Existing plugin layout preserved. The one new source file is a Utilities helper, matching the pattern of `File_Mods_Guard`.

## Complexity Tracking

*None. Constitution Check passed without violations.*
