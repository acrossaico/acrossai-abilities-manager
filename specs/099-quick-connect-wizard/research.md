# Phase 0 Research: Quick Connect Onboarding Wizard (Feature 099)

Resolves every `NEEDS CLARIFICATION` in Technical Context and the four conflicts raised in
`memory-synthesis.md`. Each decision below is binding for Phase 1 design.

---

## R1 — Module placement (resolves HARD conflict #1)

**Decision**: Do **not** create `includes/Modules/QuickConnect/`. Place the admin classes in
`admin/Partials/QuickConnect/` and the REST sub-controller in the existing Abilities module at
`includes/Modules/Abilities/Rest/AcrossAI_Quick_Connect_Controller.php`. The transport-detection
helper is a static utility at `includes/Utilities/AcrossAI_Mcp_Transport_Detector.php`.

**Rationale**: Constitution §I enumerates exactly five active feature areas and the Directory Layout
lists exactly five module directories. Adding a sixth requires a constitution PATCH amendment
(precedent: v1.4.5 for AbilityAPI). This wizard is onboarding **UI over existing capability** — it
registers no abilities, owns no data, and introduces no domain. It therefore fails the §I test of
"a feature area", and the Admin Partials Rule already dictates where admin-rendering classes live.
Avoiding the amendment also avoids the sync-impact-report overhead for something that is not a new
capability.

**Alternatives considered**:
- *New `QuickConnect` module + constitution PATCH bump* — honest about size, but amends the
  constitution for a UI flow and creates a module with no domain logic, violating §I's "singular
  purpose" intent.
- *Everything inside `admin/Partials/`, including REST* — rejected: the Admin Partials Rule says
  `includes/` classes are context-neutral, and REST controllers are context-neutral by nature.

---

## R2 — Asset enqueue ownership (resolves HARD conflict #2)

**Decision**: `QuickConnectPage` owns its own `enqueue_assets()`, wired from `includes/Main.php` via
`$this->loader->add_action( 'admin_enqueue_scripts', $quick_connect_page, 'enqueue_assets' )`.

**Rationale**: The `AC-ENQUEUE-ADMIN` index entry ("`wp_enqueue_script/style` ONLY in
`Admin\Main::enqueue_scripts/styles`", citing CONSTITUTION §I) is **factually stale**. Verified
against the codebase: `admin/Partials/File_Manager_Settings_Menu.php` calls `wp_enqueue_script()`
and `wp_enqueue_style()` directly and is wired at `includes/Main.php:337`. The current constitution
text contains no such restriction — its Admin Partials Rule only requires that enqueuing classes
*live in* `admin/Partials/`, which this satisfies. Following the established precedent also keeps
`admin/Main.php::enqueue_scripts()` from growing a fourth page branch, which §VI (DRY) and the
existing `PATTERN-FEATURE-ASSET-SEPARATION` both favour.

**Action required**: correct the `AC-ENQUEUE-ADMIN` row in `docs/memory/INDEX.md` — carried into
Step 6 memory capture, not silently edited here.

**Alternatives considered**:
- *Add a branch to `admin/Main.php`* — matches the stale index entry, but contradicts
  `PATTERN-FEATURE-ASSET-SEPARATION` and couples an unrelated class to this feature.

---

## R3 — Request gating (satisfies PATTERN-ENQUEUE-PAGE-GUARD + DEC-MENU-HOOK-SUFFIX)

**Decision**: A single private helper `is_quick_connect_request(): bool` performing a Yoda strict
comparison on the sanitized query flag. Every gate — asset enqueue, body class, notice suppression,
page hijack — calls that one helper. No `strpos` variables, no reliance on the runtime hook suffix.

**Rationale**: `PATTERN-ENQUEUE-PAGE-GUARD` requires a dedicated boolean helper with Yoda `===` and
forbids inline `strpos`; `DEC-MENU-HOOK-SUFFIX` and `DEC-MENU-HOOK-SUFFIX-SUBMENU-DERIVATION` warn
that submenu hook suffixes derive from `sanitize_title( parent_menu_title )` and are fragile —
exactly the fragility already recorded as `BUG-LIBRARY-HOOK-SUFFIX` on this parent menu. Gating on
the request satisfies both: one helper, one comparison, no suffix coupling. Input is read through
`sanitize_key( wp_unslash( ... ) )` per §IV before comparison.

---

## R4 — Busy-overlay treatment (resolves SOFT conflict #3)

**Decision**: Port the sibling's pulsing brand-icon overlay (FR-034, FR-035). Record as an
**accepted deviation** from `PATTERN-BULK-BUSY-OVERLAY-WP-NATIVE-SPINNER`.

**Rationale**: That pattern is explicitly scoped to "bulk client-side operations" in the abilities
list; the wizard is a distinct surface whose defining requirement (FR-032, SC-005) is being
visually indistinguishable from the sibling wizard. `DEC-DESIGN-OVERRIDES-DATAVIEWS` establishes
that a supplied design outranks an internal UI mandate. Both overlays keep the same accessibility
contract (`role="status"` / `aria-live`, pointer-blocking, full-screen), so nothing is lost but the
spinner glyph.

**Alternatives considered**:
- *Use the WP-native spinner* — internally consistent, but breaks SC-005 outright, which is the
  feature's highest-profile acceptance criterion.

---

## R5 — DataViews / DataForm (§III)

**Decision**: Custom `RadioCard` selection and a static integrations grid, **not** DataForm/DataViews.
Recorded as an accepted deviation under `DEC-DESIGN-OVERRIDES-DATAVIEWS`.

**Rationale**: The transport picker has no validation, no submission state, and two fixed options;
the integrations grid has no search, sort, pagination, or filtering. Neither duplicates
DataForm/DataViews functionality, so §III's "no custom rendering that duplicates" clause is not
triggered. Where it is arguably triggered, the supplied design governs. Consistent with the existing
`DEV1` and `DEC-SETTINGS-API-DEVIATION` precedents.

**Action required**: record in `tasks.md` and `docs/memory/INDEX.md` per that decision's own terms.

---

## R6 — Integration group counts (resolves SOFT conflict #4)

**Decision**: Derive groups server-side in `GET /quick-connect/state` from
`AcrossAI_Ability_Library_Registry::instance()->get_definitions()`, grouping on each definition's
`tab_group` and labelling with `ucwords( str_replace( '-', ' ', $key ) )`. Extract that labelling
into a shared utility and add a Jest/PHPUnit pair asserting it matches the JS `titleCaseTabLabel`
rule character-for-character.

**Rationale**: `PATTERN-ABILITY-LIBRARY-TAB-AUTO-DERIVE` records that the Integrations page derives
tabs **in JS at render time** with no server-side whitelist, and warns that a wrong `tab_group`
silently misplaces an ability. SC-004 requires the wizard's counts to match that page exactly, so
two independent derivations are a drift risk. Both sides already read the same
`get_definitions()` output, so pinning the shared labelling rule with a test closes the gap without
restructuring the Library page.

**Alternatives considered**:
- *Compute in JS from a definitions payload* — perfect parity, but ships the full definitions array
  (~585 KB serialized) to a screen that needs only ~17 counts.
- *Hardcode the group list* — rejected outright: FR-017 requires runtime derivation, and
  Elementor/RankMath/MailerPress groups appear only when those plugins are active.

---

## R7 — Transport detection

**Decision**: `AcrossAI_Mcp_Transport_Detector::detect( string $transport ): string` returning
`missing|inactive|active`. Recommended transport → plugin basename
`acrossai-mcp-manager/acrossai-mcp-manager.php` (the existing convention at `admin/Main.php:242`).
Alternative transport → **class-presence probe first**, falling back to a candidate basename check.

**Rationale**: Per the spec clarification (FR-027), the alternative transport must be detected by
whether its code is actually loaded, because it can arrive bundled inside another plugin rather than
installed standalone. A basename-only check produces a false negative in that case and would strand
the administrator on the instructions screen. Strict comparison throughout per **SEC-04**.

---

## R8 — Wizard state transport

**Decision**: URL query parameters only (`step`, `method`). No server-side scratchpad, no `/step` or
`/complete` routes, no new options.

**Rationale**: Screens 1–4 are read-only and screen 5's choice is a single enum. FR-015 requires no
server-side progress. This also means `uninstall.php` needs no additions, and the 30-second
activation transient is the feature's only persisted artifact.

---

## R9 — Install endpoint hardening

**Decision**: Port the sibling's `handle_install_plugin()` with: a one-entry slug allowlist checked
via `in_array( $slug, $allowed, true )`; `install_plugins` **and** `activate_plugins` capability
checks; `plugins_api()` → `Plugin_Upgrader` → `activate_plugin()`; raw upgrader/API messages to
`error_log()` only; hand-authored `WP_Error` to the client. `permission_callback` returns strictly
`true|false|WP_Error`.

**Rationale**: **SEC-04** (strict membership), Constitution §IV (nonce + capability), and the
constitution's explicit warning that returning a `WP_REST_Response` from a permission callback is a
critical security defect because it is truthy. FR-030 forbids leaking paths.

---

## R10 — Toolchain

**Decision**: PHP 8.1+, WordPress 6.9+, no new Composer dependencies, no new npm dependencies
(`@wordpress/*` Tier 1 only). Two new webpack entries: `js/quick-connect` and `css/quick-connect`,
with SCSS registered as its own entry per this repo's convention. Build requires **Node ≥ 20**
(`DEC-NODE-20-BUILD-REQUIRED`). Asset manifest loaded behind a `file_exists()` guard
(`BUG-UNCONDITIONAL-ASSET-INCLUDE`). Tests: PHPUnit (WP-less bootstrap) + Jest, with pure helpers
exported per `PATTERN-NAMED-EXPORT-JEST`.

---

## Resolved unknowns

| Unknown | Resolution |
|---|---|
| Where does the module live? | R1 — no new module; `admin/Partials/QuickConnect/` + Abilities `Rest/` |
| Who enqueues assets? | R2 — the page class, wired from `Main.php` |
| How is loading gated? | R3 — one Yoda helper on the request flag |
| Which busy overlay? | R4 — sibling's pulsing icon, accepted deviation |
| DataViews/DataForm required? | R5 — no, accepted deviation |
| Where do group counts come from? | R6 — server-side, shared labelling rule pinned by tests |
| How is the alternative transport detected? | R7 — class-presence probe, basename fallback |
| Where does wizard state live? | R8 — URL only |
| New dependencies? | R10 — none |
