---
description: "Task list for feature 092 — File-Manager Allowlists + Redactor"
---

# Tasks: File-Manager Allowlists + Redactor

**Input**: Design documents from `specs/092-file-manager-allowlists-redactor/`

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup

- [ ] T001 Baseline gates on branch tip: PHPUnit test count, warning count, PHPStan clean, PHPCS clean.

## Phase 2: Foundational — utilities

- [ ] T010 Create `includes/Abilities/Utilities/Path_Allowlist_Guard.php`. One class with `OPTION_WRITE` + `OPTION_READ` constants, `get_write_paths/set_write_paths/get_read_paths/set_read_paths` accessors, `check_write/check_read` returning `true|WP_Error`, `blocked_write_response/blocked_read_response` returning `array|null`. Prefix-match semantics. Read allowlist empty = unrestricted sentinel. Write allowlist empty = deny all.
- [ ] T011 Create `includes/Abilities/Utilities/Secret_Redactor.php`. `OPTION` constant, hardcoded 8-pattern regex map, `scrub($content):array{text,redacted,redaction_count}`, `get_config/set_config`, `available_patterns()`. Custom literals treated as literal string matches (no regex). Skips patterns whose config value is falsy.

## Phase 3: Activation

- [ ] T020 Update `includes/AcrossAI_Activator.php`: three `add_option()` calls on activation for write allowlist (default `['wp-content']`), read allowlist (default `[]`), redaction config (default map from plan §3). Idempotent.

## Phase 4: US1 — 8 write ability edits

- [ ] T030 [P] [US1] Edit `Create_File.php` — add `Path_Allowlist_Guard::blocked_write_response($abs_path)` after existing guards; widen `blocked_reason` enum.
- [ ] T031 [P] [US1] Edit `Edit_File.php` — same.
- [ ] T032 [P] [US1] Edit `Delete_File.php` — same.
- [ ] T033 [P] [US1] Edit `Copy_File.php` — call guard TWICE (source `$src_real`, destination `$dest_abs`).
- [ ] T034 [P] [US1] Edit `Move_File.php` — same twice-called pattern.
- [ ] T035 [P] [US1] Edit `Append_File.php` — add guard call.
- [ ] T036 [P] [US1] Edit `Create_Directory.php` — same.
- [ ] T037 [P] [US1] Edit `Delete_Directory.php` — same.

## Phase 5: US2 + US3 — 2 content-read ability edits

- [ ] T040 [US2/US3] Edit `Read_File.php`. Remove `PROTECTED_FILES` constant + `protected_read` guard block. Add `Path_Allowlist_Guard::blocked_read_response($abs_path)` after realpath scope check. Add `Secret_Redactor::scrub()` call on `$content` before response assembly, only when `binary === false`. Add `redacted` + `redaction_count` fields to response envelope and `output_schema`. Widen `blocked_reason` enum.
- [ ] T041 [US2/US3] Edit `Read_Debug_Log.php`. Add read-allowlist check for `WP_CONTENT_DIR . '/debug.log'`. Apply `Secret_Redactor::scrub()` on content. Add `redacted` + `redaction_count` fields. Widen `blocked_reason` enum.

## Phase 6: Admin tab + REST controller

- [ ] T050 Create `admin/Partials/File_Manager_Settings_Menu.php`. Class registers a `File Manager` tab (slug `file-manager`) via the `acrossai_settings_tabs` filter (same pattern as `SettingsMenu.php`). Tab callback renders `<div id="acrossai-file-manager-settings-root"></div>` + enqueues the new React bundle. Wired from `includes/Main.php::define_admin_hooks()`.
- [ ] T051 Create `includes/Modules/Settings/REST/File_Manager_Settings_Controller.php`. Namespace `acrossai/v1`. Routes: `GET/POST /file-manager-settings/write-allowlist`, `GET/POST /file-manager-settings/read-allowlist`, `GET/POST /file-manager-settings/redaction`. `permission_callback` requires `manage_options` + `X-WP-Nonce`. GET responses include enumeration data (core dirs via `scandir(ABSPATH)`, plugins via `get_plugins()`, themes via `wp_get_themes()`). POST responses validate + persist via the two utility classes' setter methods. Return type strictly `true|WP_Error`.

## Phase 7: React admin UI

- [ ] T060 Add webpack entry for `file-manager-settings` bundle in `webpack.config.js` (mirror the `abilities` bundle pattern).
- [ ] T061 Create `src/js/file-manager-settings/index.js`. `createRoot` mount on `#acrossai-file-manager-settings-root`. Nonce-middleware via `@wordpress/api-fetch`. Registers wp.data store `acrossai/file-manager-settings`.
- [ ] T062 Create `src/js/file-manager-settings/store/index.js` — wp.data store: state = `{ write, read, redaction, available }`; actions = `updateWrite/updateRead/updateRedaction`; resolvers hit the three GET endpoints; save actions hit POST.
- [ ] T063 Create `src/js/file-manager-settings/components/FileManagerSettings.jsx` — orchestrator: loads settings via `useSelect`, renders the three panels below in `TabPanel` or plain sections.
- [ ] T064 Create `WriteAllowlistPanel.jsx` — checkboxes for `wp-admin` / `wp-content` / `wp-includes` at top; expandable `wp-content` immediate children; installed-plugins checkbox list; installed-themes checkbox list; custom-paths textarea; Save button.
- [ ] T065 Create `ReadAllowlistPanel.jsx` — "Restrict reads to specific folders" toggle; when off, tree disabled + option persisted as `[]`; when on, same UI as WriteAllowlistPanel.
- [ ] T066 Create `RedactionPanel.jsx` — checkbox list built from `available.patterns`; custom-literals textarea (one per line); Save button.
- [ ] T067 Create `src/scss/file-manager-settings/admin.scss` — minimal styling for panel headers, indentation of expanded tree children, textarea sizing.

## Phase 8: Tests + docs

- [ ] T080 Update `tests/phpunit/abilities/Test_Feature_089_File_Consolidation.php`: read-file assertions. Remove the `PROTECTED_FILES` / `protected_read` checks. Add assertions that `Path_Allowlist_Guard::blocked_read_response` is called and `Secret_Redactor::scrub` is called on the content path.
- [ ] T081 Update `tests/phpunit/abilities/Test_Feature_091_Wp_Filesystem_Migration.php`: the 8 write abilities now each contain `Path_Allowlist_Guard::blocked_write_response(` — either the negative-native-call assertions still pass (they don't forbid the new call), or add an explicit positive assertion per write ability.
- [ ] T082 Create `tests/phpunit/abilities/Test_Feature_092_Allowlists_And_Redactor.php`. Cover: (a) both utility classes exist + declare expected methods/constants, (b) each of the 8 write abilities contains `Path_Allowlist_Guard::blocked_write_response(`, (c) `Copy_File` + `Move_File` contain the call twice, (d) `Read_File` no longer contains `PROTECTED_FILES` or `protected_read`, does contain `Path_Allowlist_Guard::blocked_read_response(` + `Secret_Redactor::scrub(`, (e) `Read_Debug_Log` similarly, (f) `File_Manager_Settings_Menu` registers via `acrossai_settings_tabs` filter, (g) REST controller declares expected routes.
- [ ] T083 Register `feature-092-unit` testsuite in `phpunit.xml.dist`.
- [ ] T084 Update `docs/abilities-inventory.md` — footer note about both allowlists + redactor.
- [ ] T085 Update `README.txt` — new bullet under `= Unreleased =` naming (a) the two allowlists + defaults, (b) the configurable redactor, (c) the BREAKING change to `read-file`.

## Phase 9: Gates + delivery

- [ ] T090 Run `composer test -- --testsuite feature-092-unit` → new suite green.
- [ ] T091 Run `composer test` → full suite green with only expected deltas.
- [ ] T092 Run `vendor/bin/phpstan analyse` → level 8 clean.
- [ ] T093 Run `vendor/bin/phpcs` → WPCS strict clean.
- [ ] T094 Run `npm run build` → webpack builds the new bundle without errors.
- [ ] T095 Execute quickstart against local install: activation option shape + WP-CLI probes for write + read + redactor + tab presence.
- [ ] T096 Commit → push → `gh pr create`. Wait for CI.

## Dependencies

```
Setup (T001)
  └─→ Utilities (T010, T011) [parallel]
        ├─→ Activation (T020)
        ├─→ Write ability edits (T030-T037) [all parallel — different files]
        ├─→ Read ability edits (T040, T041) [parallel]
        └─→ Tab + REST (T050, T051)
              └─→ React UI (T060-T067)
                    └─→ Tests + docs (T080-T085)
                          └─→ Gates + delivery (T090-T096)
```

## Parallel Execution

Phases 4 and 5 are fully parallelisable across their tasks — different files each. Phase 7 (React UI) can be built after T060-T062 as a chain, or all components can be scaffolded in parallel then connected via the store.

## Implementation Strategy

Ship as one PR. Backend + frontend land together to keep the "File Manager" tab useful from day one.
