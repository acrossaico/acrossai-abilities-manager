# Implementation Plan: File Manager Hardening (Enforcement Pass)

**Branch**: `093-file-manager-hardening` | **Date**: 2026-08-26 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification at `specs/093-file-manager-hardening/spec.md`

## Summary

Wire the ten hardening option keys shipped as UI scaffold in PR #144 into the six File Manager abilities that should consult them (`create-file`, `edit-file`, `append-file`, `copy-file`, `move-file`, `read-file`). No new options, no new admin UI panels, no new REST endpoints. Every check reads from the existing `Hardening_Settings::get_content_filters()` on each ability call (no caching) and returns a structured `blocked_reason` envelope when a rule triggers. All seven write-side checks share a new single-purpose enforcer utility (`Hardening_Enforcer`) that centralises the per-check logic and keeps per-ability call sites tiny. Sensitive-read denylist runs after the existing read allowlist, never before, so allowlist refusals continue to take precedence. Panel banners update to signal that enforcement is now live for Content Filters; Backup & Audit remains scaffold-only until feature 094 lands.

## Technical Context

**Language/Version**: PHP 8.1+ (existing plugin floor per Constitution §II)
**Primary Dependencies**: WordPress 6.9+ core (`sanitize_file_name`, `wp_check_filetype`, `get_allowed_mime_types`), `Hardening_Settings` utility (PR #144), `Path_Allowlist_Guard` utility (PR #143), `File_Mods_Guard` utility (feature 042)
**Storage**: `wp_options` — all ten keys already seeded by activator, read via existing accessors
**Testing**: PHPUnit 10.5 (existing bootstrap under `tests/bootstrap.php`), live MCP probes via `mcp__wordpress-7-0-default-mcp-server__mcp-adapter-execute-ability`
**Target Platform**: WordPress plugin, PHP 8.1–8.5 CI matrix
**Project Type**: WordPress plugin (existing single-project layout — no new module boundary)
**Performance Goals**: Zero-cost when all filters disabled (option reads short-circuit). One `get_option` call per ability invocation (already the pattern for allowlists).
**Constraints**: Every existing PHPUnit test MUST remain green; PHPCS + PHPStan MUST stay clean; new blocked-reason envelopes MUST validate against the ability adapter's output schema; no breaking change to existing envelope shapes.
**Scale/Scope**: Six ability class files touched; two React panel files touched; one REST controller file touched; one new PHP utility class (`Hardening_Enforcer`); one new PHPUnit test file (`Test_Feature_093_Hardening_Enforcement`); one CHANGELOG entry.

## Constitution Check

Constitution version: **1.4.8** (as of 2026-08-26).

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Pass/Note |
|---|---|
| **I. Modular Architecture** | ✅ Adds one new utility (`includes/Abilities/Utilities/Hardening_Enforcer.php`) that owns the check functions. All six ability classes call the enforcer — no duplication. |
| **II. WordPress Standards Compliance** | ✅ PHPCS + PHPStan clean gates enforced. No new SQL. No new deprecated functions. Multisite-compatible (no site-scoped state). |
| **III. User-Centric Design** | ⚠️ Not applicable — this feature updates two existing React panels' banner text only. No new form or table rendering; no DataForm/DataViews surface added. Consistent with the scaffold panels shipped in PR #144 (also non-DataForm). |
| **IV. Security First (NON-NEGOTIABLE)** | ✅ Purely additive security — narrows the surface for AI-driven writes. All refusals use existing WP capability + nonce checks (`Hardening_Settings` reads run inside `manage_options` REST context; ability calls run through the existing MCP adapter's permission check). No new input entry points. |
| **V. Extensibility Without Core Modification** | ✅ Adds new checks; does not modify `Path_Allowlist_Guard`, `File_Mods_Guard`, or `Secret_Redactor`. |
| **VI. Reusability & DRY Principle** | ✅ New `Hardening_Enforcer` utility exists specifically to prevent per-ability duplication. Every write ability calls the same `check_write($path, $content, ...)` and `check_read($path)` entrypoints. |
| **VII. Definition of Done** | ✅ All seven gates addressed in Phase 1 quickstart. |

**Gate result**: PASS. No violations require justification. `Complexity Tracking` section left empty.

## Project Structure

### Documentation (this feature)

```text
specs/093-file-manager-hardening/
├── plan.md                    # This file (/speckit-plan output)
├── spec.md                    # /speckit-specify output
├── research.md                # Phase 0 output (this /speckit-plan run)
├── data-model.md              # Phase 1 output (this /speckit-plan run)
├── quickstart.md              # Phase 1 output (this /speckit-plan run)
├── contracts/
│   └── blocked-reason-envelopes.md   # New blocked_reason values + response contexts
├── checklists/
│   └── requirements.md        # Spec quality checklist (already exists)
└── tasks.md                   # Phase 2 output (/speckit-tasks — NOT created here)
```

### Source Code (repository root)

```text
includes/
├── Abilities/
│   ├── Utilities/
│   │   ├── Hardening_Settings.php         # UNCHANGED (PR #144)
│   │   ├── Hardening_Enforcer.php         # NEW — centralises all seven write checks + one read check
│   │   ├── Path_Allowlist_Guard.php       # UNCHANGED (PR #143)
│   │   ├── File_Mods_Guard.php            # UNCHANGED
│   │   └── Secret_Redactor.php            # UNCHANGED
│   ├── FileManager/
│   │   ├── Create_File.php                # MODIFIED — add Hardening_Enforcer::check_write() call + output_schema keys
│   │   ├── Edit_File.php                  # MODIFIED — same
│   │   ├── Append_File.php                # MODIFIED — same, plus new_size accounting
│   │   ├── Copy_File.php                  # MODIFIED — same, applied to destination basename
│   │   ├── Move_File.php                  # MODIFIED — same, applied to destination basename
│   │   ├── Read_File.php                  # MODIFIED — add Hardening_Enforcer::check_read() call after allowlist gate
│   │   ├── Read_Debug_Log.php             # UNCHANGED (fixed target — never matches denylist)
│   │   ├── Read_Wp_Config.php             # UNCHANGED (own protected-constant list)
│   │   ├── Get_Wp_Config_Constant.php     # UNCHANGED (own protected-constant list)
│   │   ├── Create_Zip_Backup.php          # UNCHANGED (PR #145 touches this separately)
│   │   ├── Extract_Zip_Backup.php         # UNCHANGED
│   │   ├── Upload_Zip_Backup.php          # UNCHANGED
│   │   ├── Download_Zip_Backup.php        # UNCHANGED
│   │   ├── List_Zip_Backups.php           # UNCHANGED
│   │   ├── Delete_Zip_Backup.php          # UNCHANGED
│   │   ├── Delete_File.php                # UNCHANGED (no content check applies to delete)
│   │   ├── Delete_Directory.php           # UNCHANGED
│   │   ├── Create_Directory.php           # UNCHANGED
│   │   ├── List_Directory.php             # UNCHANGED
│   │   ├── File_Info.php                  # UNCHANGED
│   │   ├── Edit_Wp_Config.php             # UNCHANGED
│   │   ├── Clear_Debug_Log.php            # UNCHANGED
│   │   └── Category_Registrar.php         # UNCHANGED
│   └── Rest/
│       └── File_Manager_Settings_Controller.php   # MODIFIED — flip scaffold_only:true → false for /content-filters; update /backup-audit follow_up_spec
└── AcrossAI_Activator.php                 # UNCHANGED (all twelve options already seeded in PR #144)

src/js/file-manager-settings/components/
├── ContentFiltersPanel.jsx                # MODIFIED — remove notice-warning scaffold banner; add small notice-info under extension list
└── BackupAuditPanel.jsx                   # MODIFIED — update banner text to reference "094-file-manager-audit-log"

tests/phpunit/abilities/
└── Test_Feature_093_Hardening_Enforcement.php   # NEW — per-ability enforcement coverage

build/
├── css/file-manager-settings.css          # REBUILT by npm run build
└── js/file-manager-settings.js            # REBUILT by npm run build

CHANGELOG.md                               # MODIFIED — Unreleased section entry
```

**Structure Decision**: Single-project WordPress plugin layout (existing). No new module boundary; the new utility class lives in the established `includes/Abilities/Utilities/` shared-utility directory alongside `Hardening_Settings` (its natural pair).

## Complexity Tracking

*Not required — Constitution Check passed with no violations.*
