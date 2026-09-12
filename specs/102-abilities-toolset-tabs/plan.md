# Implementation Plan: One Access Model for Abilities

**Branch**: `102-abilities-toolset-tabs` | **Date**: 2026-09-12 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/102-abilities-toolset-tabs/spec.md`

## Summary

Retire the ability registration gate so every first-party ability always registers, and make the
per-ability `site_allowed` override the only thing that governs availability. Whatever the old
configuration blocked is translated once into explicit Force Block overrides. The Integrations screen's
groupings survive as a Toolset filter over the existing abilities list, fed by `meta.acrossai.tab_group`
newly carried through the REST record; the screen itself, its REST namespace, its bundle and its
stylesheet are deleted. Third-party integration opt-ins — which cause a third-party plugin to register at
all, and so cannot be replaced by an override — move to the shared settings page.

The plan's centre of gravity is not the UI. It is the translation: it runs unattended, reports nothing
(spec Clarifications Q1), is attempted once with no rollback (Q2), and — per Phase 0 R1 — must run
**per site**, because the configuration it reads is network-wide while the overrides it writes are not.

## Technical Context

**Language/Version**: PHP 8.1+ (WordPress 6.9+), JavaScript ES2021 / React 18
**Primary Dependencies**: WordPress Abilities API, BerlinDB 3.0, `@wordpress/scripts`, `@wordpress/element`,
`@wordpress/api-fetch`, `@wordpress/data`; `acrossai-co/main-menu` (shared admin menu, external)
**Storage**: Per-site custom table `{prefix}acrossai_abilities` (override rows, BerlinDB, `$global = false`);
network-wide site options `acrossai_library_config` (retiring) and `acrossai_integrations` (new)
**Testing**: PHPUnit (`tests/phpunit/`, enumerated in `phpunit.xml.dist`), Jest (`tests/jest/`), PHPCS (WPCS
strict), PHPStan level 8, ESLint, WordPress Plugin Check
**Target Platform**: WordPress admin, single-site **and multisite** (Constitution §II)
**Project Type**: WordPress plugin — PHP backend + React admin bundle, single repository
**Performance Goals**: No regression on the abilities list; this feature **removes** the ~451-row
definitions payload (with full JSON Schema) previously inlined at `admin/Main.php:294`, so first paint
should improve. Record before/after `strlen( wp_json_encode( … ) )` in the commit.
**Constraints**: Translation must be idempotent and must never overwrite an administrator's explicit
override; the integration opt-in copy must additionally be OR-monotonic; `tab_group` is read-only for this
feature because the MCP tool catalogue derives from it
**Scale/Scope**: ~451 registered abilities across 13 toolsets; 4 of 9 `Modules/Library` PHP files removed,
all 5 `src/js/ability-library` files removed, 1 SCSS file removed, 11 of 12 Jest files removed

No `NEEDS CLARIFICATION` remains — five were resolved in `/speckit-clarify`, seven more in
[research.md](./research.md).

## Constitution Check

*GATE: evaluated before Phase 0 and re-evaluated after Phase 1 design. Both passes recorded.*

| Principle | Pre-Phase 0 | Post-Phase 1 | Notes |
|---|---|---|---|
| **I. Modular Architecture** | PASS | **PASS after remediation** | Work stays within `Modules/Abilities`, the absorbed `Modules/Library` tier (`DEC-ABSORBED-CODE-INCLUDES-TIER`) and `admin/Partials/`. New admin classes are page/redirect concerns, not a sixth module (`DEC-ADMIN-UI-NOT-MODULE`). **The architecture review found the gate translation calling `Modules\Library\AcrossAI_Ability_Library_Registry` directly** — Module Contract #3 forbids sibling-module reach-through, and the called class's own docblock says so. Remediated: the Library module now publishes `acrossai_ability_library_definitions` (Module Contract #4) and the translation reads that. Guarded by `Test_Library_Gate_Migration_No_Request_Input::test_does_not_reach_into_the_library_module`. Two residual deviations accepted below. |
| **II. WordPress Standards** | PASS | PASS | PHPCS/PHPStan 8/ESLint/Plugin Check gates unchanged. **Multisite compatibility is the live risk** — see R1 and Complexity Tracking. No raw SQL: the translation writes through BerlinDB. |
| **III. User-Centric Design** *(NON-NEGOTIABLE)* | **VIOLATION — justified** | **VIOLATION — justified** | The abilities list is hand-rolled, not DataViews, and this feature extends it. Covered by the standing Active decision `DEC-DESIGN-OVERRIDES-DATAVIEWS` with precedent `DEV-099-1`. See Complexity Tracking. |
| **IV. Security First** *(NON-NEGOTIABLE)* | PASS | PASS | `tab_group` sanitised with `sanitize_key`; redirect target always built from `admin_url()`; settings save keeps nonce (Settings API) **and** the single filtered capability check. |
| **V. Extensibility** | PASS | PASS | Integrations stay optional and degrade gracefully; the ACF opt-in and its filter survive the move. |
| **VI. Reusability & DRY** | PASS | PASS | Reuses `AcrossAI_Ability_Group`, `AcrossAI_Ability_Registry_Query`, `AcrossAI_Abilities_Formatter`, `AcrossAI_Abilities_Query`. Filtering lands in the query layer (`AC-QUERY-LAYER-FILTERING`), not the controller. |
| **VII. Definition of Done** | PASS | PASS | Same gates; the DataViews checklist item inherits the §III justification. |
| **Boot Flow Rule** | PASS | PASS | Both resolved to named variables in `Main.php` before the Loader. The translation runs on all paths → `define_public_hooks()`; the redirect is admin-only → `define_admin_hooks()`. `AcrossAI_Integration_Ability_Base`'s constructor hooks remain an accepted deviation (`DEC-ABILITY-DEFINITION-CTOR-HOOKS`). |
| **REST Controller Pattern** | PASS | PASS | `acrossai-abilities-library/v1` is deleted wholesale; `acrossai/v1` gains one list argument and stays split. |
| **Database** | PASS | PASS | No new table, no direct SQL. |

## Project Structure

### Documentation (this feature)

```text
specs/102-abilities-toolset-tabs/
├── plan.md                  # This file
├── spec.md                  # Clarified specification (5 answers recorded)
├── research.md              # Phase 0 — 7 findings, R1 is load-bearing
├── data-model.md            # Phase 1
├── quickstart.md            # Phase 1
├── contracts/
│   ├── abilities-list.md    # GET /acrossai/v1/abilities — record + args
│   └── removed-surface.md   # What is withdrawn, and its replacement
├── memory-synthesis.md      # Durable-memory constraints applied
└── checklists/
    └── requirements.md      # Spec quality checklist (16/16)
```

### Source Code (repository root)

```text
includes/
├── Main.php                                    # wire migration + redirect; drop library menu & config REST
├── AcrossAI_Activator.php                      # call the translation for the activating site
├── Utilities/
│   ├── AcrossAI_Ability_Group.php              # MOVED from Modules/Library (Constitution §I/§VI)
│   ├── AcrossAI_Ability_Merger.php             # carry meta.acrossai.tab_group
│   ├── AcrossAI_Abilities_Formatter.php        # emit tab_group from both formatters
│   └── AcrossAI_Ability_Registry_Query.php     # new `slugs` + `status` filters (query layer)
└── Modules/
    ├── Abilities/
    │   ├── AcrossAI_Library_Gate_Migration.php      # NEW — per-site translation (writes THIS module's rows)
    │   └── Rest/
    │       └── AcrossAI_Abilities_Read_Controller.php   # `tab_group` arg; forward `status`
    └── Library/
        ├── AcrossAI_Integration_Settings.php        # NEW — acrossai_integrations reader
        │                                            # (AcrossAI_Ability_Group moved to Utilities/)
        ├── AcrossAI_Ability_Library_Processor.php   # is_permitted() deleted
        ├── Ability_Definition.php                   # drop bulk-toggle helpers
        ├── AcrossAI_Ability_Library_Config.php      # DELETED (after translation reads it)
        └── Rest/                                    # DELETED — both controllers, whole namespace

admin/
├── Main.php                                    # drop library enqueue, payload, is_library_page()
└── Partials/
    ├── LibraryMenu.php                         # DELETED
    ├── Menu.php                                # + INTEGRATIONS_LEGACY_SLUG
    ├── Integrations_Redirect.php               # NEW — admin_init P1, 301 with ?tab= preserved
    └── Integrations_Settings_Menu.php          # NEW — Settings API section on acrossai-settings

src/
├── js/
│   ├── abilities/
│   │   ├── components/{AbilitiesList,AbilitiesTable,AbilitiesToolbar,GroupTabs}.jsx
│   │   ├── components/cells/index.jsx          # + ToolsetCell
│   │   ├── hooks/useUrlSync.js                 # NEW — merges the two URL hooks
│   │   └── store/index.js                      # + activeTab
│   └── ability-library/                        # DELETED (all 5 files)
└── scss/
    ├── abilities/{admin.scss,_toolset-tabs.scss}
    └── ability-library/                        # DELETED

tests/
├── phpunit/Modules/Library/                    # + gate-migration tests; − config tests
└── jest/{abilities,ability-library}/           # − 11 files; useLibraryTabSync repointed
```

**Structure Decision**: No new module. The feature is a subtraction inside two existing areas plus two
admin page classes, which is why `Modules/Library` keeps its registry and processor (they register the
whole ~451-ability catalogue) while losing its configuration, REST and UI.

## Implementation Phases

| # | Phase | Visible? | Gate to proceed |
|---|---|---|---|
| 1 | Carry `tab_group` through Merger + Formatter | No | Both formatters emit it; `''` for DB abilities |
| 2 | `slugs` + `status` filters in the query layer; `tab_group` arg; counts via `GET /acrossai/v1/abilities/toolsets` | No | Filter returns exactly `member_names()`; dispatchers absent; counts match row counts for a site with blocked abilities |
| 3 | Split `AbilitiesList.jsx` → container + table + toolbar + cells | No | **Screenshot diff: pixel no-op** |
| 4 | `GroupTabs` (links), `ToolsetCell`, `activeTab`, merged `useUrlSync` | Yes | Deep links work; tab change resets page + clears selection |
| 5 | Per-site translation (early, **all request paths**) + integrations settings page | Yes | Rehearsed on a multisite copy with categories switched off, including a site never opened in wp-admin |
| 6 | Delete `is_permitted()` | Yes | **Only after 5 is proven** |
| 7 | Delete the old surface (menu, bundles, SCSS, build artefacts, REST namespace) | Yes | Exhaustive `grep -rEn` inventory approved first |
| 8 | Docs — `README.txt` changelog, `docs/FEATURES.md`, `docs/memory/` | No | — |

Phases 1–4 are individually shippable and invisible to administrators. **Phase 6 must not land before
Phase 5 is verified**; reversing them exposes every previously blocked ability for a release.

## Security Constraints Applied

Plan-stage review ([security-constraints.md](./security-constraints.md)) changed two design decisions.
Both are folded in above rather than left as findings.

- **SEC-001 (HIGH) — the translation must not be admin-only.** `admin_init` fires only on wp-admin loads,
  but the exposure is via REST and MCP, which never touch wp-admin. On a network, a site nobody opens in
  wp-admin would serve every previously blocked ability indefinitely, with no report (Q1) and no retry (Q2).
  The trigger moves to an early **all-paths** hook (`plugins_loaded`/`init`), still guarded by the per-site
  done flag — one cache-backed `get_option()` per request.
- **SEC-002 (MEDIUM) — toolset counts must come from the same read as the rows.**
  `AcrossAI_Ability_Override_Processor` runs PATH B (unregister blocked abilities) on ordinary requests and
  PATH A (no pruning) on this plugin's own REST namespace. Computing counts during admin page render is
  PATH B, so after migration a switched-off toolset would report `0` while its tab lists 17 Force Blocked
  rows — breaking SC-006 and hiding exactly what the feature exists to surface. Counts are therefore served
  from `acrossai/v1` alongside the rows, never computed at page render.

- **SEC-008 (MEDIUM) — the all-paths trigger must claim atomically.** The SEC-001 fix changed the
  concurrency profile: `admin_init` was effectively serialised, `plugins_loaded` on a public site is not.
  Concurrent visitors on a freshly upgraded site would all read an unguarded `get_option()` flag as unset
  and all begin translating, for the full duration of the run. The flag is therefore **claimed before the
  work** with `add_option( $flag, '1', '', false )` — a single INSERT that returns `false` when the row
  already exists, so exactly one request wins. `wp_cache_add()` is not a substitute; it is unreliable
  without a persistent object cache.
  *Trade taken deliberately*: claiming first means a request dying mid-translation leaves the site marked
  done and partially translated — which is exactly what spec Q2 already accepts. Claiming afterwards would
  guarantee repeated concurrent full runs on every busy site, which is strictly worse.
- **SEC-009 (LOW) — the trigger is now unauthenticated, so the translation reads only stored state.** An
  anonymous front-end request can reach it, which is intended (a capability check would reinstate SEC-001).
  Every input therefore comes from the site option and the definitions registry. **No request input, ever**
  — no `$_GET` short-circuit, no force-re-run parameter, no request-derived site or category selection.
  With an unauthenticated trigger, any such parameter is an unauthenticated write primitive.

SEC-003 (settings page must keep `manage_options` so the filtered capability stays raise-only) and SEC-004
(state the translation window as a bounded threat assumption) are test-and-document items; they do not
change the design. SEC-005 (301 vs 302) is **closed by the spec** — FR-024 requires a permanent redirect,
so 301 is mandated and the slug is permanently retired.

**Threat assumption, bounded**: previously blocked abilities are reachable between the code swap and the
first successful translation — on single-site, one activation; on multisite, until each site's first
qualifying request. This is the accepted consequence of spec Clarification Q2.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|--------------------------------------|
| **§III / §VII — hand-rolled table and link strip instead of DataViews** | The approved design specifies a classic WP-admin table with a Toolset column and a link strip; the surrounding screen is already hand-rolled end to end. Governed by the standing Active decision `DEC-DESIGN-OVERRIDES-DATAVIEWS`, precedent `DEV-099-1`. | Rebuilding the list on DataViews is a separate, larger feature. Introducing DataViews for the strip alone would mix two table idioms on one screen and inject `components-tab-panel__*` focus behaviour into markup that uses none of it. |
| **Two migration safety models in one release** | The override translation is one-shot with no rollback (spec Q2, an accepted risk). The integration opt-in copy is OR-monotonic and separately idempotent. | A single model cannot satisfy both: `PATTERN-OPTION-KEY-MIGRATION-OR-MONOTONIC` forbids demoting a truthy opt-in, and FR-022 requires the opt-in to survive — while Q2 explicitly accepts the translation failing. |
| **`acrossai_library_config` retained on multisite** | Deleting it is only safe once every site has translated, and no per-network bookkeeping exists (R1). | A network-wide done flag marks the network complete after one site translates — exactly the failure mode. Looping `get_sites()` at activation is unbounded and still misses later sites. |


### Accepted deviations added after the architecture review (2026-09-12)

| # | Deviation | Why accepted | What was done instead |
|---|---|---|---|
| DEV-102-1 | `admin/Partials/Integrations_Settings_Menu` imports `Includes\Modules\Library\AcrossAI_Integration_Settings`. It is the only `admin/Partials/` class importing `Includes\Modules\*`. | Module Contract #3 is written for *feature classes*; this is the entry-boundary UI for that module's own setting. Routing a settings screen's read of its own option through a filter is ceremony that buys no decoupling — the screen exists only because the module does. | Left as-is and recorded. If strictness is preferred, the fix is a second published filter mirroring `acrossai_ability_library_definitions`. |
| DEV-102-2 | `Includes\Modules\Library\AcrossAI_Integration_Settings` is static, with no `instance()` (Module Contract #1/#2). | It holds no state and has no dependencies to reach, which is the entire purpose of the singleton rule. It cannot move to `includes/Utilities/` either: `discover()` reads the Library definition registry, so relocating would put a module reference inside Utilities — a worse violation than the one being cured. The class it replaces (`AcrossAI_Ability_Library_Config`) was static for the same reason. | Sealed with a private constructor so the static intent is enforced rather than implied, and documented in the class docblock. |
