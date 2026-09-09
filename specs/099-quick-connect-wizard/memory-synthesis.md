# Memory Synthesis

## Current Scope

Feature 099 — Quick Connect onboarding wizard. A full-screen React wizard (7 screens + completion)
ported from the sibling `acrossai-mcp-manager`, opened automatically after activation when the
recommended transport is absent. Affected surfaces: `admin/Partials/` (page render, activation
redirect, admin-bar entry, menu hijack), a REST read + plugin-install endpoint, a new JS/SCSS bundle,
`includes/Main.php`, `AcrossAI_Activator`, `webpack.config.js`, `assets/`. No schema, no new settings.

## Relevant Decisions

- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason: the spec mandates visual parity with a supplied
  reference UI, which §III would otherwise forbid; Status: Active, Source: DECISIONS.md) — a
  user-supplied design outranks the DataViews/DataForm mandate. Governs this feature's custom radio
  cards and showcase grid, **but requires recording the deviation in `tasks.md` and `INDEX.md`**.
- **DEC-MENU-HOOK-SUFFIX** + **DEC-MENU-HOOK-SUFFIX-SUBMENU-DERIVATION** (Reason: the wizard must
  decide when to load its bundle; Status: Active, Source: DECISIONS.md) — hardcode hook suffixes,
  never couple to `get_hook_suffix()`; submenu suffixes derive from `sanitize_title(parent_title)`
  and are fragile. Supports the spec's decision to gate on the request, not the suffix.
- **DEC-NODE-20-BUILD-REQUIRED** (Reason: adds a webpack entry; Status: Active) — `npm run build`
  requires Node ≥ 20; older Node fails silently.
- **DEC-UTILITY-STATIC-ONLY** (Reason: transport detection is new shared logic; Status: Active) —
  utilities are 100% static; only orchestrators use singletons.
- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR-SHARED-MENU** (Reason: the wizard hangs off the shared `acrossai`
  parent menu owned by an external package; Status: Active) — entry-file P0 bootstrap is sanctioned
  for that menu.

## Active Architecture Constraints

- **Boot Flow Rule** (Source: CONSTITUTION.md) — `includes/Main.php` is the single source of hook
  registration; singletons resolve to a **named variable** before being passed to the Loader.
- **Admin Partials Rule** (Source: CONSTITUTION.md) — any class rendering admin HTML or enqueuing
  admin assets must live in `admin/Partials/`; `includes/` stays context-neutral.
- **REST Controller Pattern + permission_callback return type** (Source: CONSTITUTION.md) —
  sub-controllers live in `Rest/`, reference the orchestrator's `check_permission`, and register no
  hooks themselves; every `permission_callback` returns only `true|false|WP_Error` (returning a
  response object is a critical security defect).
- **PATTERN-ENQUEUE-PAGE-GUARD** (Source: ARCHITECTURE.md) — enqueue gating uses dedicated
  `is_*_page()` boolean helpers with Yoda `===`; never inline `strpos` variables.
- **PATTERN-ACTIVATION-HOOK-EARLY-PRIORITY** (Source: ARCHITECTURE.md) — vendor-precondition
  activation guards run at priority 1; anything depending on them registers later.

## Accepted Deviations

- **ARCH-ADV-001** (Status: Accepted-Deviation) — conditional hook wiring outside the Boot Flow
  Rule, scoped to the Override Processor only. Not a licence to self-wire here.
- **DEC-SETTINGS-API-DEVIATION** (Status: Accepted-Deviation) — precedent that a non-DataForm UI is
  acceptable for constrained admin surfaces.
- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** (Status: Accepted-Deviation) — external package constructors
  may bypass the Loader with a `class_exists()` guard.

## Relevant Security Constraints

- **SEC-04 Strict Type Comparison** (Reason: the install endpoint whitelists exactly one plugin
  slug; Source: security-constraints.md) — membership checks must use `in_array( $x, $y, true )`.
  A loose check on the slug allowlist would be an authorization bypass.
- **Constitution §IV** (Reason: the wizard adds a mutating endpoint) — nonce verification plus a
  capability check on every mutating path; install requires `install_plugins` **and**
  `activate_plugins`, above the `manage_options` floor.
- **DEC-REVALIDATE-SECURITY-POST-UPGRADE** (Reason: installs another plugin at runtime) — re-verify
  security constraints after dependency changes.

## Related Historical Lessons

- **BUG-UNCONDITIONAL-ASSET-INCLUDE** — including a `.asset.php` manifest without a `file_exists()`
  guard fatals the whole admin when the bundle is missing or unbuilt. The new bundle must be guarded.
- **BUG-JS-SLUG-PREFIX-FALLTHROUGH** — programmatic slug/prefix handling that silently falls through
  produces wrong UI state; prefer explicit sentinels plus a unit test over silent defaults.
- **PATTERN-NAMED-EXPORT-JEST** — export pure helpers alongside components so skip predicates and
  count derivation are testable without rendering.

## Conflict Warnings

*Refreshed 2026-09-08 after planning and memory capture. All four are now resolved or converted to
tasks; none blocks implementation.*

1. **Sixth module vs Constitution §I enumeration — RESOLVED.** Research R1 places the wizard in
   `admin/Partials/QuickConnect/` + `includes/Utilities/` + an Abilities `Rest/` sub-controller, so
   no module is added and no amendment is needed. Generalised into the new Active decision
   `DEC-ADMIN-UI-NOT-MODULE`.
2. **AC-ENQUEUE-ADMIN vs a self-enqueueing page class — RESOLVED (memory was wrong).** Verified
   against the codebase: `File_Manager_Settings_Menu` self-enqueues and is wired at
   `includes/Main.php:337`; CONSTITUTION v1.4.8 contains no "only in `Admin\Main`" rule. The stale
   index row has been corrected and `PATTERN-PARTIALS-SELF-ENQUEUE` records the verified precedent.
3. **Busy-overlay style — ACCEPTED DEVIATION.** Recorded as DEV-099-2 in `tasks.md`; T065 registers
   it in `INDEX.md`. The existing pattern remains scoped to bulk list operations.
4. **Integrations counts may drift — CONVERTED TO TASKS.** Research R6 pins a shared labelling rule;
   T009 extracts it, T012 asserts PHP/JS parity, T055 verifies group presence and ordering.

**New conflict raised during architecture review (open, converted to task):**

5. **Cross-module dependency (HIGH).** The Quick Connect controller sits in the `Abilities` module
   but needs the `Library` module's registry — the first sibling-module reach-through in this
   codebase (`grep` confirms no precedent), breaching Module Contract #3. **T010** resolves it by
   exposing the tab-group summary through a filter. Implementation must not bypass T010.

## Retrieval Notes

- Index-first, markdown-only (no memory-md `config.yml`; defaults applied, optimizer not enabled).
- ~20 INDEX.md entries considered; source sections read only for DEC-DESIGN-OVERRIDES-DATAVIEWS,
  PATTERN-BULK-BUSY-OVERLAY, PATTERN-ENQUEUE-PAGE-GUARD, BUG-UNCONDITIONAL-ASSET-INCLUDE.
  ARCHITECTURE/BUGS/DECISIONS/WORKLOG (470 KB combined) **not** read in full.
- Read in full (small): `security-constraints.md`, `PROJECT_CONTEXT.md`, `CONSTITUTION.md` (v1.4.8).
- **`PROJECT_CONTEXT.md` is still the unfilled template** — no product constraints or priorities
  recorded, so none informed this synthesis.
- No feature-local `memory.md` exists for 099.
- Budget: within limits (5 decisions, 5 constraints, 3 deviations, 3 security, 3 lessons, 0 worklog).
