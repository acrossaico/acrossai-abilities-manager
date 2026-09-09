---
description: "Task list for Feature 099 — Quick Connect Onboarding Wizard"
---

# Tasks: Quick Connect Onboarding Wizard

**Input**: Design documents from `/specs/099-quick-connect-wizard/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md,
memory-synthesis.md, security-review-plan.md

**Tests**: REQUIRED. Constitution §VII Definition of Done mandates "Unit tests written and passing
for all new logic" — tests are a project gate here, not an option.

**Organization**: Grouped by user story so each can be implemented, tested, and shipped independently.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1–US5)

## Path Conventions

WordPress plugin at repo root: `admin/`, `includes/`, `src/js/`, `src/scss/`, `assets/`, `tests/`.

---

## Accepted Deviations (recorded per `DEC-DESIGN-OVERRIDES-DATAVIEWS`)

`DEC-DESIGN-OVERRIDES-DATAVIEWS` requires deviations be recorded in this file. Two apply:

| ID | Deviation | Justification |
|---|---|---|
| DEV-099-1 | Custom `RadioCard` + static integrations grid instead of DataForm/DataViews (Constitution §III) | Visual parity with the sibling wizard is SC-005, the feature's headline criterion. Neither surface duplicates DataForm/DataViews capability — the picker has two fixed options with no validation or submission state; the grid has no search, sort, pagination, or filter. |
| DEV-099-2 | Pulsing brand-icon busy overlay instead of `PATTERN-BULK-BUSY-OVERLAY-WP-NATIVE-SPINNER` | Same SC-005 parity requirement. The existing pattern is explicitly scoped to bulk list operations. Accessibility contract (`role="status"`, `aria-live`, pointer-blocking) is preserved. |
| DEV-099-3 | A QuickConnect-domain REST controller housed in the `Abilities` module | The REST Controller Pattern expects sub-controllers to split a module's *own* handlers. Placement here is the deliberate trade that avoids amending Constitution §I's five-area enumeration, governed by `DEC-ADMIN-UI-NOT-MODULE`. **Review trigger**: if this surface later acquires persisted data, promote it to a real module and amend §I. |

---

## Phase 1: Setup (Shared Infrastructure) — ✅ COMPLETE (2026-09-08)

**Purpose**: Assets, build wiring, and scaffolding.

> **Sequencing defect found and fixed during execution.** T004 adds a webpack entry pointing at
> `src/js/quick-connect/index.js`, but that file was not scheduled until T024 in Phase 2 — leaving
> `npm run build` broken for everyone in between. A minimal, inert entry skeleton was created in
> Phase 1 so the repo stays buildable at every phase boundary. **T024 replaces its body** with the
> real `createRoot` + nonce-middleware bootstrap; it is not additional scope.
>
> Verified: `npm run build` compiles clean — `build/css/quick-connect.css` (13.2 KiB, plus the RTL
> variant) and `build/js/quick-connect.{js,asset.php}` all emit.

- [x] T001 Rename `assets/quick-setup/` to `assets/quick-connect/` and `git add` it — the directory is currently untracked and would otherwise ship missing
- [x] T002 Verify brand assets are byte-identical to the sibling: `diff assets/quick-connect/acrossai-logo.svg ../acrossai-mcp-manager/assets/quick-connect/acrossai-logo.svg` and the same for `icon.svg`; do not re-export or restyle
- [x] T003 Confirm Node ≥ 20 before any build (`DEC-NODE-20-BUILD-REQUIRED` — older Node fails silently)
- [x] T004 Add `js/quick-connect` and `css/quick-connect` entry points to `webpack.config.js`, registering SCSS as its own entry per this repo's convention
- [x] T005 [P] Create `src/scss/quick-connect/admin.scss` with the sibling's token block ported verbatim (`$qs-primary: #3858e9` … `$qs-font-body`), scoped under `.acrossai-quick-connect-wrap`
- [x] T006 [P] Port the full-page-takeover body rules and `qs__*` / `qs-btn` / `qs-card` / `qs-notice` class vocabulary into `src/scss/quick-connect/admin.scss`, including the `html.wp-toolbar:has(body.acrossai-quick-connect-fullpage)` rule
- [x] T007 [P] Port the pulsing-icon keyframes into `src/scss/quick-connect/admin.scss`: `qs__initial-loading-pulse` (1.6s ease-in-out infinite, opacity 0.65→1, scale 0.96→1) and `qs__initial-loading-fade-in` (150ms), plus `.qs__initial-loading--overlay` (rgba(255,255,255,0.92), blur(2px), z-index 999999)
- [x] T052 [P] **[moved from Phase 4 — SEC-T05]** Amend the recorded embed URL in `docs/planning/099-quick-connect-onboarding-wizard.md` and the spec clarification to the privacy host required by `DEC-ADMIN-THIRD-PARTY-EMBED`, so implementers read correct reference docs from Phase 1 onward

---

## Phase 2: Foundational (Blocking Prerequisites) — ✅ COMPLETE (2026-09-08)

**Purpose**: Everything every user story needs. **No user story can start until this phase completes.**

### Utilities and cross-module boundary

- [x] T008 [P] Create `includes/Utilities/AcrossAI_Mcp_Transport_Detector.php` — static utility returning `missing|inactive|active`; recommended transport by basename `acrossai-mcp-manager/acrossai-mcp-manager.php`, alternative transport by **class-presence probe first** with basename fallback (R7, FR-027). Use strict comparison throughout (SEC-04)
- [x] T009 [P] Create `includes/Utilities/AcrossAI_Tab_Group_Label.php` — single source for `ucwords( str_replace( '-', ' ', $key ) )` labelling
- [x] T010 **[ARCH-HIGH]** Add a `acrossai_ability_library_tab_group_summary` filter to `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php` exposing `[{key,label,count}]`, so the wizard never imports Library classes across a module boundary (Module Contract #3/#4 — resolves the High architecture finding)
- [x] T011 [P] Unit test `tests/phpunit/Utilities/Test_Mcp_Transport_Detector.php` — all three states per transport, plus the bundled-copy case detected via class presence
- [x] T012 [P] Paired label tests: `tests/phpunit/Utilities/Test_Tab_Group_Label.php` and `tests/jest/quick-connect/titleCaseTabLabel.test.js` asserting PHP and JS produce identical output for the same fixture list (R6, SC-004)

### Admin surface

- [x] T013 Create `admin/Partials/QuickConnect/QuickConnectPage.php` — singleton; `render()` emits only the mount div plus `<noscript>`; **begin `render()` with an explicit `current_user_can( 'manage_options' )` check and `wp_die()` otherwise** (SEC-005)
- [x] T014 Add a private `is_quick_connect_request()` helper to `QuickConnectPage` — one Yoda `===` comparison on `sanitize_key( wp_unslash( $_GET['quick-connect'] ) )`; every gate calls this one helper, never the hook suffix (R3, `PATTERN-ENQUEUE-PAGE-GUARD`, `DEC-MENU-HOOK-SUFFIX`)
- [x] T015 Add `enqueue_assets()` to `QuickConnectPage` — self-enqueue per `PATTERN-PARTIALS-SELF-ENQUEUE`, gated by T014, with a `file_exists()` guard on `build/js/quick-connect.asset.php` (`BUG-UNCONDITIONAL-ASSET-INCLUDE`)
- [x] T016 Add `wp_localize_script( 'acrossai-quick-connect', 'acrossaiQuickConnect', … )` to `QuickConnectPage::enqueue_assets()` with `restUrl`, `restNonce`, `adminUrl`, `integrationsUrl`, `pluginInstallUrl`, `logoUrl`, `iconUrl`, `mcpAdapterRepoUrl`
- [x] T017 Add `add_body_class()` and `suppress_admin_notices()` to `QuickConnectPage` — the latter calling `remove_all_actions()` on `admin_notices`, `all_admin_notices`, `user_admin_notices`, `network_admin_notices`, gated by T014 so suppression is never global (SEC-007)
- [x] T018 Add the `?quick-connect=1` delegating branch to the render callback in `admin/Partials/Menu.php`, mirroring `acrossai-mcp-manager/admin/Partials/Settings.php:566`
- [x] T019 Wire all Quick Connect hooks in `includes/Main.php::define_admin_hooks()` using the variable-first pattern (`$quick_connect_page = QuickConnectPage::instance();` then `$this->loader->add_action(...)`) — Boot Flow Rule, `AC-HOOKS-MAIN`

### REST

- [x] T020 Create `includes/Modules/Abilities/Rest/AcrossAI_Quick_Connect_Controller.php` — singleton sub-controller, registers no hooks itself, reuses `array( AcrossAI_Abilities_Rest_Controller::instance(), 'check_permission' )`
- [x] T021 Implement `GET /acrossai/v1/quick-connect/state` in T020 — ability total via `wp_get_abilities()` minus `AcrossAI_Protected_Abilities::is_protected()`, tab groups via the T010 filter, plugin states via T008; response shape per `contracts/quick-connect-rest.md`
- [x] T022 Delegate the new sub-controller from `AcrossAI_Abilities_Rest_Controller::register_routes()` — only the orchestrator is wired in `Main.php` (REST Controller Pattern)
- [x] T023 Contract test `tests/phpunit/Rest/Test_Quick_Connect_State.php` — Editor → 403, missing nonce → 403, totals match `wp_get_abilities()` minus protected, `permission_callback` returns only `true|false|WP_Error`. **Sequenced immediately after T021, not parallel** (SEC-T03): the return-type assertion must be green before T022 delegates the route
- [x] T074 [P] **[SEC-T04]** Assertion in `tests/phpunit/Admin/QuickConnect/Test_Quick_Connect_Page.php` that `enqueue_assets()` registers no script or style when the request flag is absent — makes the FR-042/SC-010 boundary a gate rather than a Phase-8 observation

### React shell

- [x] T024 Create `src/js/quick-connect/index.js` — `createRoot` mount on `#acrossai-quick-connect-root`, `apiFetch.createNonceMiddleware`; do **not** add `createRootURLMiddleware`
- [x] T025 Port `src/js/quick-connect/hooks/useWizardRouter.js` — keep the synchronous history write **outside** the `setState` updater, the `useMemo` on the returned object, and the `acrossai-qc-nav` CustomEvent resync (all three fix documented bugs)
- [x] T026 **[SEC-004]** In `useWizardRouter.js`, validate `step` and `method` against closed allowlists on read, falling back to defaults; never render either value directly
- [x] T027 [P] Port `src/js/quick-connect/hooks/useWizardState.js` — fetch-only (no `saveStep`/`complete`); refetch on focus, `visibilitychange`, `popstate`; keep `hasHydratedOnce` so tab-return does not unmount the current step
- [x] T028 [P] Port `src/js/quick-connect/hooks/useAdvanceGuard.js` with `useFooterAction`, `useHideContinue`, `useBelowFooter`, `useWizardAdvance`; preserve the `setBeforeAdvance( () => fn )` thunk form
- [x] T029 Port `src/js/quick-connect/StepLayout.jsx` — sticky progress bar, header (logo `img` from `logoUrl`, muted title, `Free Consultations ↗` pill, `Exit setup`), footer, single busy overlay driven by `footerAction.isLoading`, focus move + live-region announcement (FR-038)
- [x] T030 Create `src/js/quick-connect/App.jsx` — step registry, `computeSkips`, `stepVisibilityTable`, auto-skip effect, deep-link guard, guard-context provider. **Navigation stays state-driven — no step may call `advance()` imperatively**
- [x] T031 [P] Create `src/js/quick-connect/components/RadioCard.jsx`, `Notice.jsx`, `icons.jsx` — `role="radio"` label wrapping a visually-hidden input, Enter/Space handling; **no `dangerouslySetInnerHTML` anywhere**
- [x] T032 [P] Jest tests `tests/jest/quick-connect/router.test.js` for the named exports `shouldSkip`, `computeSkips`, `computeTotalSteps`, `computeDisplayIndex` (`PATTERN-NAMED-EXPORT-JEST`)

**Checkpoint**: shell renders, `/state` responds, no screens yet.

---

## Phase 3: User Story 1 — Connect a transport (Priority: P1) 🎯 MVP — ✅ COMPLETE (2026-09-08)

**Goal**: Activate the plugin, see the ability count, connect MCP Manager in one action, land in its wizard.

**Independent test**: On a site with no transport, activate the plugin → wizard opens by itself →
count shown → transport screen with the recommended option preselected → one click installs,
activates, and hands off to MCP Manager's own Quick Connect.

- [x] T033 [US1] Add `set_transient( 'acrossai_abilities_quick_connect_do_redirect', '1', 30 )` to `includes/AcrossAI_Activator.php::activate()`, registered after the existing priority-1 vendor guard (`PATTERN-ACTIVATION-HOOK-EARLY-PRIORITY`)
- [x] T034 [US1] Create `admin/Partials/QuickConnect/ActivationRedirect.php` — `maybe_redirect()` on `admin_init` priority 5 with the six guards in order: transient present → **delete transient first** → not `activate-multi` → not network admin → `manage_options` → recommended transport not active **and** not installed (FR-002, FR-002a)
- [x] T035 [US1] Wire `ActivationRedirect` in `includes/Main.php::define_admin_hooks()` (variable-first)
- [x] T036 [P] [US1] PHPUnit guard matrix `tests/phpunit/Admin/QuickConnect/Test_Activation_Redirect.php` — each guard independently blocks; transient deleted before evaluation; **an active alternative transport does NOT suppress** (FR-002a)
- [x] T037 [P] [US1] Create `src/js/quick-connect/steps/Step1_AbilitiesOverview.jsx` — headline count in a `qs__gate-card`; renders coherently when the count is `0`
- [x] T038 [US1] Create `src/js/quick-connect/steps/Step5_ConnectTransport.jsx` — two `RadioCard`s, `RECOMMENDED` badge, recommended preselected, each card labelled with its current state (FR-020, FR-020a, FR-020b); subtitle carries the "works with MCP Adapter and AcrossAI MCP Manager" message
- [x] T039 [US1] Add the already-connected branch to `Step5_ConnectTransport.jsx` — when the recommended transport is active, report it and offer to finish rather than offering an install (FR-025)
- [x] T046a [US1] **[SEC-T01 — run BEFORE T040]** Write the negative contract assertions in `tests/phpunit/Rest/Test_Quick_Connect_Install.php` first: `acrossai-pro` → 400, `mcp-adapter` → 400, missing `install_plugins` → 403, missing `activate_plugins` → 403. These are signature-level assertions and need no implementation, so writing them first costs nothing and closes the untested-endpoint window
- [x] T040 [US1] Implement `POST /acrossai/v1/quick-connect/install-plugin` in `includes/Modules/Abilities/Rest/AcrossAI_Quick_Connect_Controller.php` — `install_plugins` **and** `activate_plugins`; slug allowlist checked with `in_array( $slug, $allowed, true )` (SEC-04); `plugins_api` → `Plugin_Upgrader` → `activate_plugin`
- [x] T041 [US1] **[SEC-002]** After `activate_plugin()`, assert the activated basename equals `acrossai-mcp-manager/acrossai-mcp-manager.php` before reporting success; log a warning if it differs
- [x] T042 [US1] **[SEC-008]** Route raw `Plugin_Upgrader` / `plugins_api` messages to `error_log()` gated on `WP_DEBUG_LOG`; return only hand-authored `WP_Error` strings with no filesystem paths (FR-030)
- [x] T043 [US1] Wire the install action into `Step5_ConnectTransport.jsx` as a footer action with `isLoading` — the shared overlay handles progress; disable controls while in flight (FR-031)
- [x] T044 [US1] **[SEC-003]** On install success, verify the hand-off URL's origin matches `window.location.origin` before assigning to `window.location.href` — or build it client-side from `bootstrap.adminUrl` and drop the field from the response
- [x] T073 [US1] **[SEC-T02 — closes SC-009]** Add `canInstall` to the `GET /state` response (same `install_plugins` **and** `activate_plugins` check as the install route), and in `src/js/quick-connect/steps/Step5_ConnectTransport.jsx` render an "Ask a site administrator" message in place of the install action when it is false. Without this, an administrator lacking `install_plugins` is shown a button guaranteed to 403
- [x] T045 [P] [US1] Create `src/js/quick-connect/steps/Completion.jsx` — summary plus onward CTAs (Go to Abilities, Go to Integrations, Exit); no footer, no Back
- [x] T046 [P] [US1] Contract tests `tests/phpunit/Rest/Test_Quick_Connect_Install.php` — `acrossai-pro` → 400, `mcp-adapter` → 400, missing `install_plugins` → 403, missing `activate_plugins` → 403, no failure body contains a filesystem path

**Checkpoint**: MVP complete and independently shippable.

---

## Phase 4: User Story 2 — Learn how to manage abilities (Priority: P2) — ✅ COMPLETE (2026-09-08)

**Goal**: Two walkthrough screens covering editing an ability and bulk actions.

**Independent test**: Walk screens 1→3 without connecting anything; both walkthroughs present and Back/Continue move correctly.

- [x] T047 [US2] Create `src/js/quick-connect/components/VideoEmbed.jsx` — responsive 16:9 wrapper (`padding-top: 56.25%`), **no autoplay** (FR-019)
- [x] T048 [US2] **[SEC-001 / DEC-ADMIN-THIRD-PARTY-EMBED]** Harden `VideoEmbed.jsx`: use `youtube-nocookie.com`, add `referrerpolicy="strict-origin-when-cross-origin"` (NOT `no-referrer` — that triggers YouTube Error 153) and `loading="lazy"`, and render a **click-to-load facade** backed by a locally-hosted still so the third-party request is user-initiated
- [x] T049 [US2] Add the persistent external "Watch on YouTube ↗" link to `VideoEmbed.jsx` so the walkthrough stays reachable when the embed is blocked (FR-019a)
- [x] T050 [P] [US2] Create `src/js/quick-connect/steps/Step2_EditAbility.jsx` using `VideoEmbed`
- [x] T051 [P] [US2] Create `src/js/quick-connect/steps/Step3_BulkActions.jsx` using `VideoEmbed`

---

## Phase 5: User Story 3 — See the breadth of what is available (Priority: P2) — ✅ COMPLETE (2026-09-08)

**Goal**: Read-only showcase of integration groups with per-group counts.

**Independent test**: Groups and counts match the Integrations admin page exactly.

- [x] T053 [US3] Create `src/js/quick-connect/steps/Step4_Integrations.jsx` — read-only grid of `{key,label,count}` from `/state`; changes no configuration (FR-018)
- [x] T054 [US3] Handle the empty-groups case in `Step4_Integrations.jsx` with an explained empty state rather than a blank area
- [x] T055 [P] [US3] Integration test `tests/phpunit/Rest/Test_Quick_Connect_Tab_Groups.php` — groups from inactive optional integrations are absent; ordering is count desc then key asc (SC-004)

---

## Phase 6: User Story 4 — Use the alternative transport (Priority: P3)

**Goal**: Guide a manual MCP Adapter install, then show how to reach abilities through its default server.

**Independent test**: Choose MCP Adapter on screen 5 → instructions → confirm → walkthrough → completion. When the adapter is already present, instructions are skipped.

**Depends on**: US1 (the transport screen must exist).

- [x] T056 [US4] Create `src/js/quick-connect/steps/Step6AdapterInstall.jsx` — GitHub obtaining/installing instructions with a link to `https://github.com/WordPress/mcp-adapter`, plus an "I installed it — reload" action (FR-026, FR-027)
- [x] T057 [US4] Add the "not detected yet" state and re-check affordance to `Step6_AdapterInstall.jsx`; advance only once the transport is detected as present (FR-027a)
- [x] T058 [P] [US4] Create `src/js/quick-connect/steps/Step7_AdapterAbilities.jsx` using `VideoEmbed` — how to enable abilities for the adapter's default server
- [x] T059 [US4] Implement `skipAdapterInstall` in `App.jsx` as `method !== 'mcp-adapter' || plugins.mcpAdapter === 'active'` so an already-present adapter skips straight to the walkthrough (FR-011a)
- [x] T060 [P] [US4] Jest test `tests/jest/quick-connect/adapter-path.test.js` — step counts are 5 / 7 / 6 for the three paths; deep link to step 6 without `method` auto-skips forward

---

## Phase 7: User Story 5 — Return to the wizard later (Priority: P3)

**Goal**: Three ways back into the wizard.

**Independent test**: Exit, then re-open from each entry point; the wizard restarts at screen 1.

- [x] T061 [P] [US5] Add a `Quick Connect` submenu entry in `admin/Partials/Menu.php` — URL-literal `menu_slug`, empty callback, position 0
- [x] T062 [P] [US5] Create `admin/Partials/QuickConnect/AdminBarEntry.php` — node on `admin_bar_menu` priority 100, gated on `manage_options`
- [x] T063 [P] [US5] Add a `Quick Connect` entry to `plugin_action_links()` in `admin/Main.php` beside the existing Settings link
- [x] T064 [US5] Wire `AdminBarEntry` in `includes/Main.php::define_admin_hooks()` (variable-first)

---

## Phase 8: Polish & Cross-Cutting Concerns

- [x] T065 [P] Register DEV-099-1, DEV-099-2, and DEV-099-3 in `docs/memory/INDEX.md` under `## Accepted Deviations` (required by `DEC-DESIGN-OVERRIDES-DATAVIEWS`; DEV-099-3 cites `DEC-ADMIN-UI-NOT-MODULE` and carries the promote-to-module review trigger)
- [ ] T066 [P] **[SEC-006, optional]** Add a short-lived in-flight transient guard to the install route returning `409` while an install is running, making FR-031 authoritative server-side
- [x] T067 [P] Add a `Development` note to `README.txt` describing the wizard and its re-entry points
- [x] T068 Run the full quality gate: `composer run phpstan` (level 8, zero errors), PHPCS on changed production files, `npx jest src/js/quick-connect`, `vendor/bin/phpunit --filter QuickConnect`
- [x] T069 Confirm asset gating (SC-010): load the Abilities, Settings, and Integrations pages and verify `quick-connect.js` loads on none of them
- [ ] T070 Side-by-side visual parity check against `admin.php?page=acrossai_mcp_manager&quick-connect=1&step=1` at desktop and below 640px — header, progress bar, typography, buttons, cards, notices (SC-005)
- [x] T071 Accessibility pass: complete the wizard keyboard-only, verify each step change is announced, and confirm focus moves into each new screen (SC-007)
- [ ] T072 Run the `quickstart.md` verification checklist end to end on the `wordpress-7-0` site

---

## Dependencies & Execution Order

```text
Phase 1 Setup
      ↓
Phase 2 Foundational  ← BLOCKS every user story
      ↓
Phase 3 US1 (P1) ── MVP, independently shippable
      ↓
      ├─ Phase 4 US2 (P2)  ─┐
      ├─ Phase 5 US3 (P2)  ─┤ independent of each other
      ├─ Phase 6 US4 (P3)  ─┤ (US4 needs US1's transport screen)
      └─ Phase 7 US5 (P3)  ─┘
      ↓
Phase 8 Polish
```

**Story independence**: US2, US3, and US5 are fully independent once Foundational is done. US4 is the
only story with a cross-story dependency (it extends US1's transport screen).

## Parallel Opportunities

| Phase | Parallel batch |
|---|---|
| 1 | T005, T006, T007 (separate SCSS concerns) |
| 2 | T008 + T009 (separate utilities); T011 + T012 (separate tests); T027 + T028 (separate hooks); T031 + T032 |
| 3 | T036, T037, T045, T046 |
| 4–7 | T050 + T051; T061 + T062 + T063 — and whole phases 4, 5, 7 can run concurrently on separate branches |
| 8 | T065, T066, T067 |

## Implementation Strategy

**MVP = Phase 1 + Phase 2 + Phase 3 (US1)** — 46 tasks. That alone delivers the feature's reason to
exist: a new user goes from activation to a working transport without visiting the Plugins screen.
Everything after it is education and convenience.

**Security tasks are embedded, not deferred.** SEC-001 through SEC-005 and SEC-008 sit inside the
phase where the code is written (T014, T026, T041, T042, T044, T048), because each is a few lines
when planned and awkward to retrofit. Only the optional SEC-006 is deferred to polish.

**T010 is the one architectural must-do.** It resolves the High finding from architecture review by
exposing the Library summary through a filter instead of a cross-module import. Skipping it means
the first sibling-module reach-through in this codebase.

## Task Summary

| Phase | Tasks | Count |
|---|---|---|
| 1 — Setup | T001–T007, T052 | 8 |
| 2 — Foundational | T008–T032, T074 | 26 |
| 3 — US1 (P1) | T033–T046, T046a, T073 | 16 |
| 4 — US2 (P2) | T047–T051 | 5 |
| 5 — US3 (P2) | T053–T055 | 3 |
| 6 — US4 (P3) | T056–T060 | 5 |
| 7 — US5 (P3) | T061–T064 | 4 |
| 8 — Polish | T065–T072 | 8 |
| **Total** | | **75** |

**Revised 2026-09-08 after task security review** (`security-review-tasks.md`): added T073
(capability-conditional install control, closes the previously untasked SC-009), T074 (enqueue-gating
assertion), and T046a (install negative tests before T040); moved T052 into Phase 1; de-parallelized
T023. MVP scope is now Phases 1–3 = **50 tasks**.
