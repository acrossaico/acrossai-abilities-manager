# Implementation Plan: File Manager Audit Log + Backup Harness

**Branch**: `094-file-manager-audit-log` | **Date**: 2026-08-26 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification at `specs/094-file-manager-audit-log/spec.md`

## Summary

Consume the four Backup & Audit option keys shipped as UI scaffold in PR #144 and enforced-toggle-only in PR #146. Every file-manager mutation writes a pre-image backup (per admin toggle) into `wp-content/acrossai-file-manager-backups/<YYYY-MM-DD>/` and appends an entry to `wp-content/acrossai-file-manager-logs/acrossai-file-manager.log` (also toggle-gated). A new `file-manager/get-changelog` ability tails the log via MCP. A new optional `context` input field on every mutation ability is captured in the log for accountability. Delete_File's inline `<path>.bak.<time>` scheme is retired in favour of the centralised backup dir (BREAKING for callers of `response.backup`).

All new behaviour lives behind admin toggles that default to false — zero I/O overhead when features are off. Cleanup is amortised via a 1-in-10 wp_rand gate; no separate WP-Cron event needed. Third-party integrations subscribe via a new `acrossai_file_manager_log_entry` action hook.

## Technical Context

**Language/Version**: PHP 8.1+ (existing plugin floor per Constitution §II)
**Primary Dependencies**: WordPress 6.9+ core (`WP_Filesystem`, `wp_mkdir_p`, `sanitize_text_field`, `wp_get_current_user`, `wp_rand`, `gmdate`, `do_action`); `Hardening_Settings` (PR #144); `Wp_Filesystem_Init` (feature 091)
**Storage**: `wp_options` (four keys already seeded); disk (`wp-content/acrossai-file-manager-backups/`, `wp-content/acrossai-file-manager-logs/`)
**Testing**: PHPUnit 10.5 with the WP-less bootstrap; live MCP probes via the `wordpress-7-0-default-mcp-server`
**Target Platform**: WordPress plugin; PHP 8.1–8.5 CI matrix
**Project Type**: WordPress plugin — single-project layout
**Performance Goals**: When both toggles are false, zero disk I/O beyond what the ability already does. When on, one `wp_mkdir_p` per new day + one `WP_Filesystem::copy` per backup + one read+concat+write per log entry. Cleanup amortised 1-in-10 per write.
**Constraints**: Backup MUST NOT block primary write (best-effort). Log write MUST NOT surface errors to the caller (silent failure documented). Concurrent log appends may race — acceptable per Append_File precedent. No new JS/CSS assets; only React panel text changes.
**Scale/Scope**: One new PHP utility class (`Audit_Trail`); one new ability class (`Get_Changelog`); 10 ability classes touched for backup+log wiring + `context` input; 1 REST controller extended with `/backup-audit-stats`; 1 React panel updated; 1 uninstall path extended; 1 new PHPUnit test file (~60+ tests).

## Constitution Check

Constitution version: **1.4.8** (as of 2026-08-26).

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Pass/Note |
|---|---|
| **I. Modular Architecture** | ✅ New `Audit_Trail` utility in `includes/Abilities/Utilities/` — same directory as `Hardening_Settings` (persistence), `Hardening_Enforcer` (content-filter runtime). No cross-module coupling. |
| **II. WordPress Standards Compliance** | ✅ PHPCS + PHPStan clean gates enforced. All new file I/O through `WP_Filesystem`. No new SQL. No deprecated functions. Multisite-compatible (options are site-scoped by default). |
| **III. User-Centric Design** | ⚠️ N/A — the panel edit is a text swap (yellow banner → blue info line). No new DataForm/DataViews surface. Consistent with existing panels. |
| **IV. Security First (NON-NEGOTIABLE)** | ✅ `.htaccess` guards on both storage locations. `get-changelog` requires `manage_options` + honours read allowlist. Context field sanitised via `sanitize_text_field` before storage. Log captures user email + IP — documented as GDPR-relevant in spec Constraints. No new input entry points bypassing existing gates. |
| **V. Extensibility Without Core Modification** | ✅ Third-party integrations subscribe via `acrossai_file_manager_log_entry` action hook — no core plugin fork needed. |
| **VI. Reusability & DRY Principle** | ✅ Single `Audit_Trail` utility owns backup + log + cleanup + stats. Every mutation ability calls the same two entrypoints (`Audit_Trail::write_backup()` + `Audit_Trail::write_log()`). No per-ability duplication. |
| **VII. Definition of Done** | ✅ All seven gates addressed in Phase 1 quickstart. |

**Gate result**: PASS. No violations require justification. `Complexity Tracking` section left empty.

## Project Structure

### Documentation (this feature)

```text
specs/094-file-manager-audit-log/
├── plan.md                            # This file
├── spec.md                            # /speckit-specify output
├── research.md                        # Phase 0 output (this run)
├── data-model.md                      # Phase 1 output (this run)
├── quickstart.md                      # Phase 1 output (this run)
├── contracts/
│   ├── log-entry-format.md            # FR-005 entry format contract
│   └── get-changelog-ability.md       # Ability I/O contract
├── checklists/
│   └── requirements.md                # Spec quality checklist
└── tasks.md                           # Phase 2 output (/speckit-tasks)
```

### Source Code

```text
includes/
├── Abilities/
│   ├── Utilities/
│   │   ├── Hardening_Settings.php     # UNCHANGED (PR #144 — this feature reads)
│   │   ├── Audit_Trail.php            # NEW — backup + log + cleanup + stats
│   │   ├── Hardening_Enforcer.php     # UNCHANGED (feature 093)
│   │   ├── Path_Allowlist_Guard.php   # UNCHANGED (PR #143)
│   │   └── Wp_Filesystem_Init.php     # UNCHANGED (feature 091)
│   ├── FileManager/
│   │   ├── Get_Changelog.php          # NEW — file-manager/get-changelog
│   │   ├── Create_File.php            # MODIFIED — Audit_Trail calls + context input + backup_path output
│   │   ├── Edit_File.php              # MODIFIED — same
│   │   ├── Append_File.php            # MODIFIED — same
│   │   ├── Copy_File.php              # MODIFIED — same, backup subject is source pre-image if dest overwrite
│   │   ├── Move_File.php              # MODIFIED — same, backup subject is source (moved-from)
│   │   ├── Delete_File.php            # MODIFIED — REPLACE inline .bak.<time> with Audit_Trail; deprecated `backup` field
│   │   ├── Create_Directory.php       # MODIFIED — log-only (MKDIR operation), no backup possible
│   │   ├── Delete_Directory.php       # MODIFIED — log-only (RMDIR), no backup (contents may be many)
│   │   ├── Edit_Wp_Config.php         # MODIFIED — log + backup of wp-config.php pre-image
│   │   ├── Clear_Debug_Log.php        # MODIFIED — log + backup of debug.log pre-image
│   │   └── Category_Registrar.php     # MODIFIED — register the new Get_Changelog ability
│   └── Rest/
│       └── File_Manager_Settings_Controller.php  # MODIFIED — flip scaffold_only + new /backup-audit-stats endpoint
├── AcrossAI_Deactivator.php            # UNCHANGED (activation-time only)
└── ...
uninstall.php                          # MODIFIED — delete backup + log dirs when opt-in is on

src/js/file-manager-settings/components/
└── BackupAuditPanel.jsx               # MODIFIED — drop notice-warning, add notice-info with stats

tests/phpunit/abilities/
└── Test_Feature_094_Audit_Log_And_Backups.php  # NEW — ~60+ tests

docs/abilities-inventory.md            # MODIFIED — +1 ability row for get-changelog

README.txt                             # MODIFIED — Unreleased CHANGELOG entry
```

**Structure Decision**: Single-project WordPress plugin layout (existing). `Audit_Trail` lives alongside `Hardening_Settings` and `Hardening_Enforcer` — the three-utility set forms the "content management" tier the file-manager namespace stands on.

## Complexity Tracking

*Not required — Constitution Check passed with no violations.*
