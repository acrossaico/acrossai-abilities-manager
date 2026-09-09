# Implementation Plan: Quick Connect Onboarding Wizard

**Branch**: `099-quick-connect-wizard` | **Date**: 2026-09-08 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/099-quick-connect-wizard/spec.md`

## Summary

A full-screen React onboarding wizard that opens automatically after activation when AcrossAI MCP
Manager is absent, teaches the plugin across four screens (ability count, editing walkthrough, bulk
actions walkthrough, integrations showcase), then connects a transport — installing MCP Manager
in place and handing off to its own wizard, or guiding a manual MCP Adapter install and showing how
to reach abilities through its default server.

Technical approach: port the proven wizard shell from the sibling `acrossai-mcp-manager` (URL-driven
router, guard context, step-visibility table, single busy overlay) rather than reinventing it, and
mount it by hijacking the existing manager page on a query flag — **no new admin page, no new
module, no new database schema, no new stored settings**. Two REST routes only: a read for wizard
state and a hardened single-slug plugin installer.

## Technical Context

**Language/Version**: PHP 8.1+ · JavaScript ES2020+ with JSX
**Primary Dependencies**: WordPress 6.9+; `@wordpress/element`, `@wordpress/api-fetch`,
`@wordpress/i18n`, `@wordpress/url`, `@wordpress/components` (Tier 1 only — no new Composer or npm
dependencies)
**Storage**: N/A — no schema, no options. One 30-second activation transient
(`acrossai_abilities_quick_connect_do_redirect`) is the only persisted artifact.
**Testing**: PHPUnit (existing WP-less bootstrap at `tests/bootstrap.php`) + Jest
**Target Platform**: WordPress 6.9+ admin, PHP 8.1–8.5; single-site for automatic opening
**Project Type**: WordPress plugin — admin UI + REST endpoints
**Performance Goals**: Wizard bundle loads only on the wizard request (SC-010); one `GET /state`
call per visit; no blocking work during admin page render
**Constraints**: Visual parity with the sibling wizard is a hard acceptance criterion (SC-005);
no new stored settings; brand assets must stay byte-identical; build requires Node ≥ 20
**Scale/Scope**: 8 screens (7 + completion), ~440 registered abilities, ~17 integration groups

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

Evaluated against `.specify/memory/CONSTITUTION.md` v1.4.8.

| Principle | Verdict | Evidence |
|---|---|---|
| **I. Modular Architecture** | ✅ PASS | No sixth module created (R1). Admin classes in `admin/Partials/QuickConnect/`; REST sub-controller joins the existing Abilities module; shared detection logic in `includes/Utilities/`. No duplication — the callback/permission machinery is reused, not copied. |
| **II. WordPress Standards** | ✅ PASS | PHP 8.1+/WP 6.9+ targets; PHPCS/PHPStan L8/ESLint in the DoD gate; no SQL introduced; no forbidden functions; Plugin Check surface unaffected (`src/`, `specs/`, `tests/` already excluded). Multisite: automatic opening is explicitly single-site scoped with justification recorded in the spec Assumptions — permitted by §II. |
| **III. User-Centric Design** | ⚠️ DEVIATION (accepted) | Custom `RadioCard` + static grid instead of DataForm/DataViews. Governed by `DEC-DESIGN-OVERRIDES-DATAVIEWS` (Active). Neither surface duplicates DataForm/DataViews capability (R5). Must be recorded in `tasks.md` + `INDEX.md`. |
| **IV. Security First** | ✅ PASS | Nonce + capability on both routes; `install_plugins` **and** `activate_plugins` for the installer; strict `in_array(..., true)` slug allowlist (SEC-04); `permission_callback` returns only `true\|false\|WP_Error`; all `$_GET` reads sanitized; no raw error leakage (R9). |
| **V. Extensibility Without Core Modification** | ✅ PASS | Both transports are optional and detected defensively; the wizard degrades to informational screens when neither is present. `Menu.php` gains one delegating branch — no rewrite. No blocking auto-discovery during page render. |
| **VI. Reusability & DRY** | ✅ PASS | Reuses the existing REST orchestrator's `check_permission`, the existing Registry for counts, and the existing `AcrossAI_Protected_Abilities` exclusion utility. The tab-label rule is extracted to a shared utility rather than re-implemented (R6). Tier 1 packages only. |
| **VII. Definition of Done** | ⏳ DEFERRED to implementation | All gates carried into `tasks.md`; the two DataViews/DataForm checkboxes are satisfied by the recorded §III deviation. |

**Boot Flow Rule**: ✅ every hook registers in `includes/Main.php` via the Loader, variable-first.
**Admin Partials Rule**: ✅ all rendering/enqueuing classes live in `admin/Partials/`.
**REST Controller Pattern**: ✅ sub-controller in `Rest/`, reuses the orchestrator's
`check_permission`, registers no hooks itself.

**Gate result: PASS** — one accepted, documented deviation (§III); no unjustified violations.

## Project Structure

### Documentation (this feature)

```text
specs/099-quick-connect-wizard/
├── plan.md              # This file
├── spec.md              # Feature specification (48 FRs, 12 SCs)
├── research.md          # Phase 0 — conflict resolutions R1–R10
├── data-model.md        # Phase 1 — entities and state
├── quickstart.md        # Phase 1 — build/run/verify
├── memory-synthesis.md  # Durable-memory context
├── contracts/           # Phase 1 — REST + wizard router/state contracts
├── checklists/          # Spec quality checklist
└── tasks.md             # Phase 2 output (/speckit-tasks — NOT created here)
```

### Source Code (repository root)

```text
admin/
├── Main.php                                  # + plugin_action_links entry
└── Partials/
    ├── Menu.php                              # + quick-connect render branch, + submenu link
    └── QuickConnect/
        ├── QuickConnectPage.php              # mount point, self-enqueue, body class, notice suppression
        ├── ActivationRedirect.php            # 30s transient consumer, 6 ordered guards
        └── AdminBarEntry.php                 # re-entry point

includes/
├── Main.php                                  # all hook wiring (variable-first)
├── AcrossAI_Activator.php                    # + 30s redirect transient
├── Utilities/
│   ├── AcrossAI_Mcp_Transport_Detector.php   # missing|inactive|active per transport
│   └── AcrossAI_Tab_Group_Label.php          # shared label rule, pinned by tests
└── Modules/Abilities/Rest/
    └── AcrossAI_Quick_Connect_Controller.php # GET /state, POST /install-plugin

src/
├── js/quick-connect/
│   ├── index.js  App.jsx  StepLayout.jsx
│   ├── hooks/{useWizardRouter,useWizardState,useAdvanceGuard}.js
│   ├── components/{RadioCard,Notice,VideoEmbed,icons}.jsx
│   └── steps/Step1…Step7 + Completion.jsx
└── scss/quick-connect/admin.scss

assets/quick-connect/                          # renamed from assets/quick-setup/, git add
├── acrossai-logo.svg                          # 28px header + gate-card mark
└── icon.svg                                   # 96px pulsing loading/busy icon

tests/
├── phpunit/Admin/QuickConnect/*.php
├── phpunit/Utilities/*.php
└── jest/quick-connect/*.test.js

webpack.config.js                              # + js/quick-connect, css/quick-connect
```

**Structure Decision**: No new module directory. Per R1, the wizard is admin UI over existing
capability, so it maps onto the existing Admin Partials + Utilities + Abilities-REST surfaces. This
keeps Constitution §I's five-module enumeration intact and avoids a constitution amendment for a
feature that owns no domain data.

## Phase 1 Design Highlights

- **Screen flow**: seven screens plus completion, driven by a single `stepVisibilityTable`; the
  progress indicator counts only applicable screens (FR-009). Two skip predicates —
  `skipAdapterInstall` (alternative selected **and** already present) and `skipAdapterAbilities`
  (alternative not selected).
- **Navigation is state-driven**: steps never call `advance()` imperatively; they mutate state,
  refetch, a predicate flips, and the auto-skip effect moves the user. This is the sibling's
  hard-won fix for double-jump races and must survive the port.
- **Two REST routes only**; contracts in `contracts/`.
- **Detection**: class-presence probe for the alternative transport, basename for the recommended
  one (R7).

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| §III DataForm/DataViews not used | Visual parity with the sibling wizard is the feature's headline acceptance criterion (SC-005); the design is the supplied source of truth per `DEC-DESIGN-OVERRIDES-DATAVIEWS` | DataForm/DataViews would change the rendered output and fail SC-005. Neither surface duplicates their capability: the picker has two fixed options with no validation or submission state; the grid has no search, sort, pagination, or filter. |
| Busy overlay diverges from `PATTERN-BULK-BUSY-OVERLAY-WP-NATIVE-SPINNER` | Same SC-005 parity requirement; the existing pattern is explicitly scoped to bulk list operations | Using the WP-native spinner would visibly differ from the sibling wizard on the one screen most likely to be compared. Accessibility contract is preserved either way. |
