# Phase 0 — Research: Ability-level suggested-plugins framework

**Feature**: 088 | **Date**: 2026-08-24 | **Status**: Complete (no NEEDS CLARIFICATION remaining)

## Purpose

Resolve open technical questions before Phase 1 design. No NEEDS CLARIFICATION markers remain in `spec.md`; this document consolidates decisions made during the multi-turn planning session (documented in `/Users/raftaar1191/.claude/plans/dynamic-munching-robin.md`) and grounds them in the current codebase.

---

## R-001 — Where does the new template method live?

**Decision**: Extend the existing `AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition` class with one new optional `protected function suggested_plugins(): array` method. Modify the existing `push_definition()` filter callback to auto-merge the result into `$args['meta']['acrossai']['suggested_plugins']` when non-empty.

**Rationale**:
- `Ability_Definition` is the single abstract base every ability subclass (500+) already extends
- Its existing `push_definition()` callback is exactly the injection point for cross-cutting meta additions — it runs at `init P99` via `acrossai_abilities_api_init` before WP core `wp_register_ability()` sees the payload
- Subclasses that don't override see zero change (default returns `[]`, auto-inject is guarded by `if ( ! empty( ... ) )`)
- No new file, no new class hierarchy

**Alternatives considered**:
- **Trait mixed into every subclass** — rejected: 500+ subclasses would need edits; the base-class method with default `[]` gives universal opt-in without touching subclasses
- **New wrapper class or decorator** — rejected: added indirection with no gain; base-class hook is the WordPress-native pattern
- **Filter-only extension point** (`acrossai_ability_suggested_plugins`) — considered as v2 follow-up; not required for v1 because ability authors own their own class
- **Meta field authored inline by each subclass** — rejected: boilerplate + easy to forget; template method + auto-inject is DRY

---

## R-002 — Where does the payload enrichment (install status + kill-switch) live?

**Decision**: `AcrossAI_Ability_Library_Registry::format_merged_ability()`. Two responsibilities in one function, in this order:

1. **Kill-switch first** — read the site option `acrossai_disable_plugin_suggestions` (bool, default `false`). If `true`, replace `suggested_plugins` with an empty array and return early on that field.
2. **Enrichment second** (only when the kill-switch is off) — for each entry, compute `is_active = is_plugin_active( "{slug}/{slug}.php" )` and attach it to the entry.

**Rationale**:
- `format_merged_ability()` is the documented formatter for cross-cutting payload transformations (`DEC-ABILITIES-DUAL-MODE-LIST` in memory)
- Single-point enrichment covers all consumers (REST, MCP, Library UI) simultaneously — no risk of a UI-only bypass leaking suggestions into MCP payloads
- Reads `get_option()` once per request (WP's option cache short-circuits repeats within the request lifecycle)
- `is_plugin_active()` is a fast core function; typical ability card shows 1–2 suggestions, so per-request overhead is negligible

**Alternatives considered**:
- **UI-only kill-switch** (JS reads the option, hides suggestions in DOM) — rejected: leaves MCP + REST consumers exposed, breaking FR-007
- **Per-subclass gate check in `suggested_plugins()` itself** — rejected: violates DRY, every subclass would need to know about the option
- **REST-controller-level filter** — considered; rejected because the Registry formatter is the earlier, more central point

---

## R-003 — How is the admin setting registered?

**Decision**: WP Settings API on the existing AcrossAI settings page (`admin.php?page=acrossai-settings`). One new `register_setting()` + `add_settings_field()` pair. Sanitize callback per `PATTERN-CHECKBOX-SANITIZE` — a named public method that returns `1` when the field is present in the POST payload and `0` when absent.

**Rationale**:
- Existing page already uses WP Settings API (per `DEC-SETTINGS-API-DEVIATION` in memory — scalar-field single-page pattern is accepted, no DataForm required)
- Nonce handling is built into WP Settings API — no custom nonce code needed
- Capability gate (`manage_options`) inherited from the page's own `add_menu_page()` registration
- Single scalar checkbox — the simplest possible admin surface

**Alternatives considered**:
- **New dedicated sub-page** — rejected: excessive UX for a single checkbox
- **DataForm-based form** — rejected: DataForm shines for multi-field dynamic UIs; overkill for a single boolean and violates SC-006 (30-second locate + toggle)
- **REST-only toggle (no admin UI)** — rejected: admin needs a visible, discoverable control per FR-006

---

## R-004 — How is the setting cleaned up on uninstall?

**Decision**: Add a single `delete_option( 'acrossai_disable_plugin_suggestions' )` line inside the existing `$acrossai_delete_data` guard in `uninstall.php`, following `PATTERN-UNINSTALL-DATA-GATE`.

**Rationale**:
- `uninstall.php` already opts-in to data removal via the `$acrossai_delete_data` gate — one more `delete_option` inside the same guard is the established pattern
- Preserves the option when the admin uninstalls without opting to delete plugin data (satisfies AC #4 of User Story 2)
- Zero risk to existing uninstall logic (single line, guarded)

**Alternatives considered**:
- **Always delete on uninstall** — rejected: violates `PATTERN-UNINSTALL-DATA-GATE`; user preference is preserved for reinstall
- **Explicit deactivation-time cleanup** — rejected: WP convention is to keep data on deactivate, remove only on uninstall

---

## R-005 — How does the Library UI render the new section?

**Decision**: Extend `src/js/ability-library/components/LibraryPage.js` (or its per-card sub-component if extracted) to destructure `suggested_plugins` from `meta.acrossai` and render a "Consider also" section only when the array is non-empty. Each entry shows:

- Name + one-line reason
- Install-status badge — `Active` if `is_active`, otherwise `Install` link to `entry.url` (falling back to `https://wordpress.org/plugins/{slug}/`)
- Ability-source pill derived from `plugin_provides_abilities` + `acrossai_provides_integration` (4-way label per spec)

Visual style: low-key, grey-toned, below the existing card body.

**Rationale**:
- The Library UI already destructures `meta.acrossai.tab_group` and `meta.acrossai.sub_group` in the same file — the new field slots in with the same pattern
- No new JSX component needed; a sub-render block inside the existing card is enough
- No JS-side kill-switch check required (Registry has already emptied the list when the option is on)

**Alternatives considered**:
- **Dedicated new React component** — rejected: overkill for ~30 lines of markup; adding a component increases test surface without value
- **Modal / drawer for details** — rejected: violates "low-key advice" intent; suggestion should be scan-and-move-on

---

## R-006 — How to test the framework without touching the 500+ existing abilities?

**Decision**: In `Test_Feature_088_Suggested_Plugins_Framework.php`, define one or two test-only fixture classes inline that extend `Ability_Definition` and override `suggested_plugins()`. Assert against the payload produced by their `ability()` method after `push_definition()` runs. For install-status enrichment, use Better Search Replace (already installed on the dev site per prior sessions) as a real-fixture plugin with a stable slug.

**Rationale**:
- Source-inspection tests (per 081–087 pattern) can only assert against declared class source, but we need runtime assertion of the auto-inject + enrichment behaviour. In-file fixture classes give us runtime assertions without polluting the ability inventory.
- Using BSR as an `is_active` fixture avoids mocking `is_plugin_active()` — a real WordPress function against a real installed plugin gives an honest signal.
- Existing 500+ abilities can be regression-tested by running the full PHPUnit suite and confirming zero payload change (SC-001).

**Alternatives considered**:
- **Mock `is_plugin_active()` globally** — rejected: fragile, requires global-state teardown; real-plugin fixture is simpler
- **Retrofit one production ability as the test target** — rejected: contaminates Feature 088 with 089's retrofit scope; the two features should stay separable

---

## R-007 — Performance impact on payload emission

**Decision**: Accept the per-request overhead of:
- One `get_option( 'acrossai_disable_plugin_suggestions' )` call (cached by WP; effectively zero after first hit)
- One `is_plugin_active()` call per suggestion entry per ability (typical: 0–2 calls per ability that declares suggestions)

Expected: <5 ms total per ability payload emission (per Technical Context). No caching layer needed for v1.

**Rationale**:
- `is_plugin_active()` reads a WP option (`active_plugins`) which is cached by WP core
- No abilities declare suggestions in this feature (framework only) → zero real-world overhead until 089+ retrofits opt in
- Even at scale (all 500 abilities each declaring 2 suggestions) the overhead is bounded to 1000 option reads per full-list REST call — well within request budget

**Alternatives considered**:
- **Pre-compute a site-transient of active-plugin lookups** — deferred to a later optimisation pass if scale warrants; unnecessary for v1

---

## Summary

| ID | Question | Decision |
|---|---|---|
| R-001 | Template method location | Extend `Ability_Definition::push_definition()` |
| R-002 | Enrichment + kill-switch location | `AcrossAI_Ability_Library_Registry::format_merged_ability()` |
| R-003 | Admin setting registration | WP Settings API on existing settings page + `PATTERN-CHECKBOX-SANITIZE` |
| R-004 | Uninstall cleanup | `delete_option()` inside `$acrossai_delete_data` gate |
| R-005 | Library UI rendering | Extend `LibraryPage.js` — destructure + low-key "Consider also" section |
| R-006 | Testing without touching 500 abilities | In-file fixture subclasses + BSR as real `is_active` fixture |
| R-007 | Performance | Accepted; <5 ms/ability; no caching layer needed for v1 |

All open technical questions resolved. Phase 1 design proceeds.
