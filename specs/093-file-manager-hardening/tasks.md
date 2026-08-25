---
description: "Task list for feature 093 — File Manager Hardening (enforcement pass)"
---

# Tasks: File Manager Hardening (Enforcement Pass)

**Input**: Design documents from `/specs/093-file-manager-hardening/`
**Prerequisites**: plan.md (loaded), spec.md (5 user stories, P1×2 / P2×2 / P3), research.md (3 decisions), data-model.md (ability × check matrix), contracts/blocked-reason-envelopes.md, quickstart.md

**Tests**: PHPUnit test coverage is REQUESTED (FR-017 mandates it and Constitution §VII lists "Unit tests written and passing" as a gate).

**Organization**: Tasks are grouped by user story. Every check the enforcer runs corresponds to one user story so each story lands with its own tests and can be validated independently.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel — different files, no dependency on incomplete tasks
- **[Story]**: US1..US5 map to the five user stories in spec.md; Setup, Foundational, and Polish carry no story label
- All PHP paths are relative to repo root

## Path Conventions

- `includes/Abilities/Utilities/` — utility classes (`Hardening_Enforcer` lands here)
- `includes/Abilities/FileManager/` — the six ability classes touched
- `includes/Abilities/Rest/` — REST controller update
- `src/js/file-manager-settings/components/` — React panel edits
- `tests/phpunit/abilities/` — new PHPUnit test file
- `build/{js,css}/file-manager-settings.*` — rebuilt bundle

---

## Phase 1: Setup

**Purpose**: Confirm branch state and reserve the CHANGELOG entry before any file edits land.

- [ ] T001 Confirm current branch is `093-file-manager-hardening` (`git branch --show-current`) and working tree is clean apart from spec-kit docs
- [ ] T002 Add an Unreleased section stub for feature 093 to `CHANGELOG.md` (leave the bullet list empty; final wording goes in Polish task T036)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Land the enforcer skeleton, the ability-side call sites, and the output-schema additions once so each user story only needs to fill in a check body + tests.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T003 Create `includes/Abilities/Utilities/Hardening_Enforcer.php` with two public static methods `check_write(string $absolute_path, string $content = '', array $opts = []): ?array` and `check_read(string $absolute_path): ?array`. Both return `null` for now (empty-body stubs). Include the class-level docblock explaining that the enforcer centralises the seven write checks and one read check per data-model.md, reads a single `Hardening_Settings::get_content_filters()` snapshot per call, and returns a ready-made refusal envelope-or-null.
- [ ] T004 [P] Add `Hardening_Enforcer::check_write()` call site to `includes/Abilities/FileManager/Create_File.php` immediately after the existing `Path_Allowlist_Guard::blocked_write_response()` block. Include `use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Hardening_Enforcer;` at the top.
- [ ] T005 [P] Add `Hardening_Enforcer::check_write()` call site to `includes/Abilities/FileManager/Edit_File.php` in the same position + same `use` statement.
- [ ] T006 [P] Add `Hardening_Enforcer::check_write()` call site to `includes/Abilities/FileManager/Append_File.php` in the same position + same `use` statement. Pass `['mode' => 'append', 'existing_size' => (int) $fs->size($real)]` in `$opts` so the write-size check can compute `new_size` during US3.
- [ ] T007 [P] Add `Hardening_Enforcer::check_write()` call site to `includes/Abilities/FileManager/Copy_File.php` in the same position + same `use` statement. Pass `['mode' => 'copy', 'source_content_reader' => fn() => $fs->get_contents($src_real), 'source_size' => (int) $fs->size($src_real)]` in `$opts` so the htaccess-directive check can read source content and the write-size check can use source size.
- [ ] T008 [P] Add `Hardening_Enforcer::check_write()` call site to `includes/Abilities/FileManager/Move_File.php` in the same position + same `use` statement. Same `$opts` shape as T007 with `mode => 'move'`.
- [ ] T009 [P] Add `Hardening_Enforcer::check_read()` call site to `includes/Abilities/FileManager/Read_File.php` immediately after the existing `Path_Allowlist_Guard::blocked_read_response()` block. Return the envelope as-is when non-null. Include the `use` statement.
- [ ] T010 [P] Extend `output_schema.properties` in `Create_File.php` with the eight new context fields per `contracts/blocked-reason-envelopes.md`: `extension:string`, `basename:string`, `directive:string`, `input:string`, `sanitized:string`, `size:integer`, `max_bytes:integer`, `marker:string`. Keep `additionalProperties: false`.
- [ ] T011 [P] Same output_schema additions in `Edit_File.php`.
- [ ] T012 [P] Same output_schema additions in `Append_File.php`.
- [ ] T013 [P] Same output_schema additions in `Copy_File.php`.
- [ ] T014 [P] Same output_schema additions in `Move_File.php`.
- [ ] T015 [P] Extend `output_schema.properties` in `Read_File.php` with `basename:string` and `matched_pattern:string` (all other keys already declared from PR #143).
- [ ] T016 Create `tests/phpunit/abilities/Test_Feature_093_Hardening_Enforcement.php` with a bootstrap-only skeleton: WP_UnitTestCase subclass, `setUp` that resets every `acrossai_file_manager_*` option to the PR #144 default, `tearDown` that repeats the reset. Empty test methods for each FR — bodies added per user story below.
- [ ] T017 Regenerate the Composer autoload classmap (`composer dump-autoload -o`) so the new `Hardening_Enforcer` class is resolvable at runtime.

**Checkpoint**: Every write ability now calls the enforcer (which returns null → no behaviour change), every ability's output_schema accepts the new refusal keys, and the PHPUnit skeleton exists. All user stories can now be implemented in parallel.

---

## Phase 3: User Story 1 — `extension_blocked` refusals across every write ability (Priority: P1) 🎯 MVP

**Goal**: Enable the `dangerous_extensions` option to actually gate writes. This is the smallest end-to-end slice of the feature and proves the wiring works.

**Independent Test**: Set `acrossai_file_manager_dangerous_extensions=['exe']`, call `file-manager/create-file` on `wp-content/uploads/probe.exe`; expect `{success:false, blocked_reason:'extension_blocked', extension:'exe'}`. Retry on `probe.txt`; expect success.

### Tests for User Story 1

- [ ] T018 [US1] Add PHPUnit tests to `tests/phpunit/abilities/Test_Feature_093_Hardening_Enforcement.php` covering FR-002: five tests (one per write ability) asserting the refusal envelope when the target extension is in the list, plus one no-op test per ability asserting success when the list is empty. Also assert the response includes the `extension` context field.

### Implementation for User Story 1

- [ ] T019 [US1] In `Hardening_Enforcer::check_write()`, add the extension-blocklist check: extract `strtolower(pathinfo(basename($path), PATHINFO_EXTENSION))`, compare against the snapshot's `dangerous_extensions`, return the `extension_blocked` envelope when matched. Envelope shape per `contracts/blocked-reason-envelopes.md`.
- [ ] T020 [US1] For `copy-file` and `move-file`, ensure the extension is taken from the DESTINATION basename (spec User Story 4 acceptance scenario 2). Add an `$opts['target_basename_override']` path so the enforcer can use dest instead of the raw `$path` when set; wire it from Copy_File.php + Move_File.php.
- [ ] T021 [US1] Run PHPUnit for the new test class only (`./vendor/bin/phpunit --filter Test_Feature_093_Hardening_Enforcement`) — the 6 US1 tests must pass, all others still skipped or empty.

**Checkpoint**: `extension_blocked` works end-to-end. MVP shipped.

---

## Phase 4: User Story 2 — `sensitive_read_blocked` on top of read allowlist (Priority: P1)

**Goal**: Enable the `sensitive_read_denylist` option so admins can safely widen the read allowlist without leaking secrets. Runs AFTER the existing allowlist check (spec FR-011 — allowlist refusal wins).

**Independent Test**: Set `acrossai_file_manager_read_allowlist=[]` (unrestricted) and `acrossai_file_manager_sensitive_read_denylist=['.env']`, drop a file at `wp-content/uploads/.env`, call `file-manager/read-file`; expect `blocked_reason:'sensitive_read_blocked'`.

### Tests for User Story 2

- [ ] T022 [US2] Add PHPUnit tests to `Test_Feature_093_Hardening_Enforcement.php` covering FR-010 and FR-011:
  - Literal denylist entry matches basename case-sensitively (`.env` blocks `.env`, allows `.ENV`)
  - `*.EXT` glob matches extension case-insensitively (`*.key` blocks `backup.key` AND `BACKUP.KEY`)
  - Allowlist refusal wins when both would refuse (`path_not_allowed_for_read`, not `sensitive_read_blocked`)
  - No-op when denylist is empty
  - Confirms `read-debug-log` is unaffected (fixed target never matches — FR-012)

### Implementation for User Story 2

- [ ] T023 [US2] In `Hardening_Enforcer::check_read()`, add the sensitive-read denylist check: iterate snapshot's `sensitive_read_denylist`, match literals case-sensitively against `basename($path)`, match `*.EXT` globs case-insensitively against the extension. Return the `sensitive_read_blocked` envelope with `basename` and `matched_pattern` context on match.
- [ ] T024 [US2] Verify enforcement ordering by inspecting Read_File.php: `Path_Allowlist_Guard::blocked_read_response()` MUST run before `Hardening_Enforcer::check_read()`. Add a code comment naming FR-011 at the call site.
- [ ] T025 [US2] Run `./vendor/bin/phpunit --filter Test_Feature_093_Hardening_Enforcement` — US1 tests still green, all US2 tests newly green.

**Checkpoint**: Read-side enforcement complete. Both P1 stories delivered.

---

## Phase 5: User Story 3 — `write_size_exceeded` with append/copy/move semantics (Priority: P2)

**Goal**: Enable the `write_max_bytes` cap. Handles the three size semantics (create/edit: `strlen(content)`; append: `existing + appended`; copy/move: source file size).

**Independent Test**: Set `write_max_bytes=5242880` (5 MiB); write 6 MiB via `create-file` → refused; append 2 MiB to a 4 MiB existing file → refused; copy a 6 MiB file → refused.

### Tests for User Story 3

- [ ] T026 [US3] Add PHPUnit tests to `Test_Feature_093_Hardening_Enforcement.php` covering FR-006 across all three semantics: create/edit (strlen), append (new_size), copy/move (source size). Assert `size` and `max_bytes` context fields on each refusal. Include a boundary test at exactly `write_max_bytes` bytes (must succeed) and one at `write_max_bytes + 1` (must refuse).

### Implementation for User Story 3

- [ ] T027 [US3] In `Hardening_Enforcer::check_write()`, add the write-size check. Branch on `$opts['mode']`:
  - Default (`create` / `edit` — no mode): `$size = strlen($content)`
  - `append`: `$size = ($opts['existing_size'] ?? 0) + strlen($content)`
  - `copy` / `move`: `$size = $opts['source_size'] ?? 0`
  Return the `write_size_exceeded` envelope with `size` and `max_bytes` on refusal.
- [ ] T028 [US3] Run `./vendor/bin/phpunit --filter Test_Feature_093_Hardening_Enforcement` — US1 + US2 + US3 tests all green.

**Checkpoint**: Write size cap active with correct semantics for every write path.

---

## Phase 6: User Story 4 — remaining five checks with uniform enforcement (Priority: P2)

**Goal**: Wire the other five content filters (double-ext, htaccess-directive, sanitize-name, strict-filename, mime-type) so the panel's promised uniformity is real. Each check follows the same shape as the earlier three; append-file skips `mime_type_check` per data-model.md.

**Independent Test**: With each toggle flipped on individually, invoke every applicable write ability and get the matching `blocked_reason`. With all toggles off, every ability behaves exactly as before US1–US3.

### Tests for User Story 4

- [ ] T029 [US4] Add PHPUnit tests covering FR-003 (`double_extension_blocked`) across all five write abilities. Include positive tests for `.php.jpg`, `.phtml.png`, `.phar.gif` and negative for a plain `.jpg`.
- [ ] T030 [US4] Add tests covering FR-004 (`htaccess_directive_blocked`) — create/edit scan full content; append scans APPENDED content only (spec Edge Cases); copy/move scan source content. One test per directive name to prove case-insensitive substring matching.
- [ ] T031 [US4] Add tests covering FR-005 (`filename_sanitize_failed`) — a name with a space, a name with a colon, a Unicode name that sanitize_file_name normalises. Assert `input` and `sanitized` context.
- [ ] T032 [US4] Add tests covering FR-007 (`filename_strict_blocked`) — one test per marker (c99, r57, wso, b374k, weevely, shell, alfa, bypass, backdoor) confirming substring match. One negative test with toggle off.
- [ ] T033 [US4] Add tests covering FR-008 (`mime_type_blocked`) — unknown extension refused; each entry in the always-allowed set `{php, txt, log, json, xml, css, js, md, html, htm, htaccess}` succeeds even with the check on; append-file skips this check entirely.

### Implementation for User Story 4

- [ ] T034 [US4] In `Hardening_Enforcer::check_write()`, add the double-extension check (`block_double_extensions` bool + regex `/\.(php|phtml|phar)\.[^.]+$/i` on the target basename). Return `double_extension_blocked` on match.
- [ ] T035 [US4] Add the `.htaccess` directive scan. Only fires when basename is `.htaccess`. Determine which content to scan: default `$content`; `mode === 'append'` uses `$content` (already just the appended text); `mode === 'copy'` / `'move'` calls `$opts['source_content_reader']()`. Substring-search each of the six directive names (case-insensitive). Return `htaccess_directive_blocked` with the offending directive on match.
- [ ] T036 [US4] Add the filename-sanitize check (`sanitize_filename_check` bool + `sanitize_file_name($basename) !== $basename`). Return `filename_sanitize_failed` with `input` (original) and `sanitized` (WP's rewrite).
- [ ] T037 [US4] Add the strict-filename filter (`strict_filename_filter` bool + case-insensitive substring match against the nine markers). Return `filename_strict_blocked` with the matched marker.
- [ ] T038 [US4] Add the MIME-type check (`mime_type_check` bool + `wp_check_filetype($basename)['type']` empty AND extension NOT in the always-allowed set). Skip entirely when `$opts['mode'] === 'append'`. Return `mime_type_blocked` with the extension.
- [ ] T039 [US4] Run `./vendor/bin/phpunit --filter Test_Feature_093_Hardening_Enforcement` — all US1–US4 tests green.

**Checkpoint**: All eight content-filter checks are live. Content Filters panel now enforces every knob it displays.

---

## Phase 7: User Story 5 — Panel banner update (Priority: P3)

**Goal**: Communicate to admins that Content Filters enforcement is live. Backup & Audit remains scaffold-only (feature 094) with clearer wording.

**Independent Test**: Load `admin.php?page=acrossai-settings&tab=file-manager`. Content Filters panel shows `notice-info` (not `notice-warning`). Backup & Audit banner references "094-file-manager-audit-log".

### Implementation for User Story 5

- [ ] T040 [US5] In `includes/Abilities/Rest/File_Manager_Settings_Controller.php`, change `get_content_filters()` and `save_content_filters()` response to set `scaffold_only:false` and `follow_up_spec:null`. Leave the `/backup-audit` endpoint response alone — its `scaffold_only:true` and `follow_up_spec:'094-file-manager-audit-log'` are already correct.
- [ ] T041 [P] [US5] In `src/js/file-manager-settings/components/ContentFiltersPanel.jsx`, remove the `notice-warning` "Scaffold only" block entirely. Add a `notice-info` block under the extension list reading "This list now gates create-file / edit-file / append-file / copy-file / move-file." (i18n-wrapped).
- [ ] T042 [P] [US5] In `src/js/file-manager-settings/components/BackupAuditPanel.jsx`, update the `notice-warning` banner text to "Scaffold only. Values save but no backup is written and no log entry is emitted yet. Enforcement lands in feature 094-file-manager-audit-log." (i18n-wrapped).
- [ ] T043 [US5] Run `npm run build` to rebuild `build/js/file-manager-settings.js` + `build/css/file-manager-settings.css`. Confirm the bundle size increased only marginally (< 1 KiB delta expected — just banner text edits).

**Checkpoint**: UI reflects live enforcement. Feature is functionally complete.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Full-suite verification, docs, live probes, and PR prep.

- [ ] T044 Run full PHPUnit suite (`./vendor/bin/phpunit`). Expected: 1806+ passing (baseline preserved). No new warnings beyond the 6 pre-existing unrelated warnings in `AcrossAI_Ability_Merger`.
- [ ] T045 [P] Run PHPCS on every changed PHP file: `./vendor/bin/phpcs -n includes/Abilities/Utilities/Hardening_Enforcer.php includes/Abilities/FileManager/{Create_File,Edit_File,Append_File,Copy_File,Move_File,Read_File}.php includes/Abilities/Rest/File_Manager_Settings_Controller.php tests/phpunit/abilities/Test_Feature_093_Hardening_Enforcement.php` — expect exit 0.
- [ ] T046 [P] Run PHPStan on every changed PHP file: `./vendor/bin/phpstan analyse --no-progress <same file list>` — expect exit 0.
- [ ] T047 [P] Add PHPUnit test entry to `phpunit.xml.dist` — under `<testsuite name="abilities-unit">`, add `<file>tests/phpunit/abilities/Test_Feature_093_Hardening_Enforcement.php</file>` so the test file runs in the default suite invocation.
- [ ] T048 Write the CHANGELOG.md Unreleased entry (fills in T002's stub): summarise the eight new `blocked_reason` values, list the six abilities gated, note the panel banner change, confirm no breaking envelope changes, reference PR #144 as the scaffold prerequisite.
- [ ] T049 Live MCP verification: work through every recipe in `specs/093-file-manager-hardening/quickstart.md` FR-002 through FR-016 and FR-012 sections. Every expected refusal envelope MUST match exactly. Cleanup section MUST leave `wp_options` back at defaults and no probe files behind.
- [ ] T050 Stage + commit + push the branch, then open PR against `main` with a summary linking to spec.md and quickstart.md.

**Checkpoint**: Feature ready for CI + merge review.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — T001 + T002 can run immediately.
- **Foundational (Phase 2)**: T003 blocks T004–T009 (they add call sites to the class T003 creates). T017 must run after T003. T010–T015 are independent of T003 and can run in parallel with it. T016 is independent.
- **User Stories (Phase 3–7)**: All depend on Foundational (Phase 2) completion. Once Foundational is done, US1/US2/US3/US4 can proceed in parallel by different contributors. US5 has no dependency on US1–US4 (it's pure UI/REST wording) and can run in parallel with any of them.
- **Polish (Phase 8)**: Depends on all user stories complete.

### User Story Dependencies

- **US1**: Independent. Blocks nothing else. First to ship (MVP).
- **US2**: Independent of US1. Different check in a different method (`check_read` vs `check_write`).
- **US3**: Independent of US1 + US2. Different check in `check_write`; ability call sites already wired.
- **US4**: Independent of US1–US3. Adds five more branches inside `check_write` — the earlier US branches remain untouched.
- **US5**: Fully independent — pure REST wording + panel banner edits.

### Within Each User Story

- Tests written FIRST for each user story (spec FR-017 mandates coverage). Bodies should FAIL before the implementation task runs (T019, T023, T027, T034–T038).
- All check functions live in the single `Hardening_Enforcer` class → each US adds one or more `if (…) { return $refusal; }` blocks inside `check_write` or `check_read`. Ordering within the method: FR-014 says "hardening after allowlist"; within hardening, order is best-cheap-first: dangerous_extensions → double_extensions → sanitize_filename → strict_filename → mime_type → htaccess_directive → write_size. Enforcer only ever returns the FIRST refusal — order matters for what admins see.

### Parallel Opportunities

- Setup (T001, T002): T001 pure check, T002 CHANGELOG edit — can run in parallel.
- Foundational: T004–T009 (call sites in different ability files) can all run in parallel. T010–T015 (schema additions in different ability files) can all run in parallel. T016 + T017 independent of everything.
- Within US4: T034–T038 all edit the same `Hardening_Enforcer.php` — sequential, NOT parallel. T029–T033 all edit the same test file — sequential.
- Across US1–US5: after Foundational, all five stories can proceed by different people simultaneously.
- Polish: T045, T046, T047 all parallel-safe (touch different files or read-only).

---

## Parallel Example: Foundational Phase

```bash
# After T003 (Hardening_Enforcer skeleton) lands, launch these in parallel:
Task T004: "Add Hardening_Enforcer::check_write() call site to includes/Abilities/FileManager/Create_File.php"
Task T005: "Add Hardening_Enforcer::check_write() call site to includes/Abilities/FileManager/Edit_File.php"
Task T006: "Add Hardening_Enforcer::check_write() call site to includes/Abilities/FileManager/Append_File.php"
Task T007: "Add Hardening_Enforcer::check_write() call site to includes/Abilities/FileManager/Copy_File.php"
Task T008: "Add Hardening_Enforcer::check_write() call site to includes/Abilities/FileManager/Move_File.php"
Task T009: "Add Hardening_Enforcer::check_read() call site to includes/Abilities/FileManager/Read_File.php"

# In parallel with the above, six independent output_schema edits:
Task T010: "Extend output_schema in Create_File.php with eight new context fields"
Task T011: "Extend output_schema in Edit_File.php with eight new context fields"
Task T012: "Extend output_schema in Append_File.php with eight new context fields"
Task T013: "Extend output_schema in Copy_File.php with eight new context fields"
Task T014: "Extend output_schema in Move_File.php with eight new context fields"
Task T015: "Extend output_schema in Read_File.php with basename + matched_pattern"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001, T002)
2. Complete Phase 2: Foundational (T003–T017) — the enforcer + wiring + schema
3. Complete Phase 3: User Story 1 (T018–T021) — `extension_blocked` end-to-end
4. **STOP and VALIDATE**: Live-probe with `wp option update acrossai_file_manager_dangerous_extensions '["exe"]'` and confirm the refusal envelope. If the MVP works, everything else follows the same recipe.
5. Skippable-if-needed cutoff: if scope pressure emerges, this MVP alone is shippable as "Content Filters partial — dangerous_extensions enforced".

### Incremental Delivery

1. Setup + Foundational → foundation ready (no user-visible change yet).
2. US1 → `extension_blocked` live → **MVP**.
3. US2 → `sensitive_read_blocked` live → both P1 stories done.
4. US3 → `write_size_exceeded` live.
5. US4 → remaining 5 checks live → Content Filters panel fully enforced.
6. US5 → panel banners updated to reflect enforcement → feature complete.
7. Polish → verify + ship PR.

### Parallel Team Strategy

Foundational is small enough for one person. After it lands:
- Developer A: US1 (T018–T021) → US4 (T029–T039)  — write-side heavy
- Developer B: US2 (T022–T025) + US3 (T026–T028)   — read + size
- Developer C: US5 (T040–T043)                     — UI + REST wording

Each track can commit independently. Polish (Phase 8) is one person at the end.

---

## Notes

- [P] tasks = different files, no dependencies. All non-[P] tasks either share a file with an earlier task or depend on its output.
- Every user story adds only new code — no refactor of existing envelopes. FR-014 (order) is enforced by where the enforcer call sites land in Phase 2 (after allowlist, before I/O).
- Each check inside `Hardening_Enforcer::check_write()` is guarded by its own option toggle — when the option is disabled (empty list or false bool), the check returns without work. Zero cost when nothing is enabled (SC-002).
- The `Test_Feature_093_Hardening_Enforcement` bootstrap resets every option in setUp/tearDown so tests never leak state to each other (T016).
- Commit boundary suggestion: one commit per phase for clean review. Foundational may be split into "enforcer + schema" and "call sites" if easier.
- Avoid: touching `Path_Allowlist_Guard`, `File_Mods_Guard`, `Hardening_Settings`, or any zip/wp-config/delete/directory ability — spec FR-012 explicitly excludes those files.
