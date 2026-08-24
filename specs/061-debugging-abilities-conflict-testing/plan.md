# Implementation Plan: Debugging Abilities — Conflict Testing

**Branch**: `061-debugging-abilities-conflict-testing` | **Date**: 2026-08-02 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/061-debugging-abilities-conflict-testing/spec.md`

## Summary

Add seven new WP Abilities API abilities to acrossai-abilities-manager under a new **Debugging** category with a **Conflict Testing** sub-group. Every ability wraps operations on:

- a per-site on-disk JSON overrides map at `WP_CONTENT_DIR . '/conflict-test-overrides.json'`
- a must-use plugin at `WPMU_PLUGIN_DIR . '/wp-conflict-tester.php'` that filters `option_active_plugins` on every request

Plus two shared helpers (`Overrides_Store`, `Dependency_Resolver`) and one verbatim mu-plugin source asset copied from Local by Flywheel's existing implementation.

Per the four `/speckit-clarify` decisions:

1. **Sole writer.** This plugin is the only writer of the overrides file on any given site. Local's IPC path is not expected to write the same file at the same time.
2. **Bulk = best-effort with a report.** Bulk-set records each named plugin under *applied*, *no-op*, or *skipped* with reason; unknowns don't abort the call.
3. **Auto-prune orphans on read.** Every read silently drops entries whose plugin is no longer installed and, if the map shrunk, rewrites the on-disk document (or deletes it if the map is now empty).
4. **Sandbox-scrape probe before writing an *active* override.** Mirrors WP core's `plugin_sandbox_scrape` — `include_once` the plugin file before recording an `active=true` override. If PHP fatals, the write never happens and the site's runtime behaviour is unchanged.

## Technical Context

**Language/Version**: PHP 8.1+
**Primary Dependencies**: WP core 6.9+; WP Abilities API (`wp_register_ability` / `wp_register_ability_category`); this plugin's `Ability_Definition` base class; `File_Mods_Guard`; the plugin's `Loader` singleton and PSR-4 autoloader.
**Storage**: On-disk JSON at `WP_CONTENT_DIR . '/conflict-test-overrides.json'`; on-disk PHP at `WPMU_PLUGIN_DIR . '/wp-conflict-tester.php'`. **No** database tables. **No** WP options.
**Testing**: PHPUnit ^10.5 (existing `tests/phpunit/` suite). New tests scoped to the two non-trivial helpers.
**Target Platform**: WP admin + REST via WP Abilities API endpoints under `/wp-json/wp-abilities/v1/abilities/`. Every ability auto-exposed by the WP Abilities API; **zero** custom REST-controller code.
**Project Type**: WordPress plugin (single project).
**Performance Goals**: Interactive admin latency. Single-plugin write path: `include_once`-bounded (dominated by target plugin's own boot cost). Read path: O(N) in installed plugins for `list-plugins`; O(N) for `get-overrides` orphan-prune walk. No formal SLA.
**Constraints**:
- Atomic write for overrides file via **temp + rename** — even with sole writer, protects concurrent readers from torn JSON (FR-019 safety net still applies as belt-and-suspenders).
- `WP_SANDBOX_SCRAPING`-guarded include probe on every `active=true` write path, mirroring core's `plugin_sandbox_scrape` (FR-022).
- Fixed system-owned paths for both the JSON file and mu-plugin (FR-014).
- `File_Mods_Guard::blocked_response()` gate on deploy/remove of the mu-plugin (FR-013).

**Scale/Scope**: 7 abilities + 1 category + 2 helpers + 1 mu-plugin asset ≈ 800 LOC PHP + ~200 LOC PHPUnit for helper coverage. Zero new REST endpoints. Zero new DB tables. Zero new npm packages. Zero new Composer packages.

## Constitution Check

Evaluated against Constitution v1.4.8. All principles checked; two deviations documented under Complexity Tracking.

- **§I Modular Architecture — PASS.** The new `includes/Abilities/Debugging/` directory is a new **ability domain** (sibling of `FileManager/`, `Cache/`, `Database/`, etc.) inside the existing "Custom Ability Registration" feature area. It is **not** a new module. The five active feature areas listed in §I remain unchanged.
- **§II WordPress Standards Compliance — PASS.** PHP 8.1+ target ✓, WP 6.9+ target ✓, PHPStan L8 target (all new code type-annotated), WPCS strict target, Plugin Check clean surface. No forbidden functions (no `eval` / `extract` / shell). No deprecated APIs. Multisite-compatible (JSON file lives in per-site `WP_CONTENT_DIR`, so each site in a network has its own overrides).
- **§III User-Centric Design — N/A.** Backend-only feature. No admin UI, no DataForm, no DataViews.
- **§IV Security First — PASS.** `manage_options` capability check on every ability's `permission_callback`, returning `true` or `\WP_Error` only. No user-supplied filesystem paths — both target paths are class-level constants derived from `WP_CONTENT_DIR` and `WPMU_PLUGIN_DIR`. Plugin-file identifier input is validated via `validate_plugin()` / `get_plugins()` before use. `File_Mods_Guard::blocked_response()` on deploy/remove-mu-plugin. JSON output via `wp_json_encode()`; JSON read via `json_decode( …, true )` with `JSON_THROW_ON_ERROR` and an exception-to-empty-map fallback.
- **§V Extensibility Without Core Modification — PASS.** Every new ability registers through the WP Abilities API and its existing `Ability_Definition` base class. The only edit to a pre-existing plugin file is two additions to `includes/Modules/Library/AcrossAI_Core_Abilities_Bootstrap.php` — one category registrar and seven ability instantiations, matching the existing pattern for FileManager and every other ability domain. No monkey-patching, no core-plugin file rewrites.
- **§VI Reusability & DRY — DEVIATION (see Complexity Tracking).** Two helpers (`Overrides_Store`, `Dependency_Resolver`) could theoretically live under `includes/Utilities/` per §VI. They stay under `includes/Abilities/Debugging/` because they have exactly one consumer today.
- **§VII Definition of Done — DEVIATION (see Complexity Tracking).** Spec Assumption ("no PHPUnit tests in the initial commit — matches FileManager convention") contradicts §VII's mandate that "Unit tests written and passing for all new logic." Compromise: PHPUnit coverage for the two non-trivial helpers; the seven ability classes stay untested (thin dispatchers over the tested helpers).

## Project Structure

### Documentation (this feature)

```text
specs/061-debugging-abilities-conflict-testing/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output — one file per ability, ability I/O contract
│   ├── conflict-test-list-plugins.md
│   ├── conflict-test-get-overrides.md
│   ├── conflict-test-set-override.md
│   ├── conflict-test-bulk-set-overrides.md
│   ├── conflict-test-clear-overrides.md
│   ├── conflict-test-deploy-mu-plugin.md
│   └── conflict-test-remove-mu-plugin.md
├── checklists/
│   └── requirements.md
├── spec.md
└── tasks.md             # Phase 2 output (/speckit-tasks)
```

### Source Code (repository root)

```text
includes/Abilities/Debugging/
├── Category_Registrar.php                    # final singleton; hooks wp_abilities_api_categories_init
├── List_Plugins.php                          # ability 1: acrossai/conflict-test-list-plugins
├── Get_Overrides.php                         # ability 2: acrossai/conflict-test-get-overrides
├── Set_Override.php                          # ability 3: acrossai/conflict-test-set-override
├── Bulk_Set_Overrides.php                    # ability 4: acrossai/conflict-test-bulk-set-overrides
├── Clear_Overrides.php                       # ability 5: acrossai/conflict-test-clear-overrides
├── Deploy_Mu_Plugin.php                      # ability 6: acrossai/conflict-test-deploy-mu-plugin
├── Remove_Mu_Plugin.php                      # ability 7: acrossai/conflict-test-remove-mu-plugin
├── Overrides_Store.php                       # helper: JSON read/write + atomic rename + orphan prune + mu-status
├── Dependency_Resolver.php                   # helper: transitive closure over `Requires Plugins`
└── assets/
    └── wp-conflict-tester.php                # verbatim 33-line mu-plugin source from Local addon (pasted by user before /speckit-implement)

includes/Modules/Library/AcrossAI_Core_Abilities_Bootstrap.php
    # +1 line in register_category_callbacks()  — Debugging\Category_Registrar
    # +7 lines in register_abilities()          — one per Debugging\<ClassName>

tests/phpunit/abilities/debugging/
├── OverridesStoreTest.php                    # sparse-map cleanup, atomic write, orphan prune, mu-status computation
└── DependencyResolverTest.php                # transitive closure both directions, cycles, unknown-target
```

**Structure Decision**: Single-project layout, adopting the existing `includes/Abilities/<Domain>/` sibling pattern. All new PHP code lives under `includes/Abilities/Debugging/`. The PSR-4 autoloader (already configured at `AcrossAI_Abilities_Manager\Includes\ → includes/`) covers the new sub-namespace automatically — no `composer.json` change required. Tests live under `tests/phpunit/abilities/debugging/` alongside the existing `tests/phpunit/abilities/` layout.

## Complexity Tracking

Two deviations from the Constitution documented and justified:

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|--------------------------------------|
| **§VI — Shared helpers (`Overrides_Store`, `Dependency_Resolver`) live under `includes/Abilities/Debugging/` instead of `includes/Utilities/`.** | Both helpers have exactly **one** consumer today: the seven conflict-testing abilities. `Overrides_Store` is semantically tied to the conflict-test JSON file layout — a different domain would need a different store. `Dependency_Resolver` reads `Requires Plugins` — a WP-core-provided concept — but its transitive-closure semantics are shaped by the conflict-test cascade rule (dependents-on-deactivate, requirements-on-activate) that no other current caller wants. Elevating them to `includes/Utilities/` before a second consumer emerges would encode a premature abstraction. | Placing the code inline in the seven ability classes would duplicate the JSON I/O and cascade walk logic seven times. Placing them in `includes/Utilities/` today satisfies the letter of §VI but not its intent (extract *shared* logic). We keep them close to the only consumer and commit to promotion on the first cross-domain reuse — same policy Feature 060's `AcrossAI_Integration_Ability_Base` followed. |
| **§VII — PHPUnit coverage lands only for the two helpers, not for the seven ability classes.** | The seven ability classes are thin dispatchers: they validate input, delegate to `Overrides_Store` or `Dependency_Resolver`, and format a response. All non-trivial logic (JSON I/O, atomic write, orphan prune, mu-status computation, dependency closure) lives in the two helpers. Testing the helpers to the boundary and treating the ability classes as trivial glue is a defensible interpretation of §VII's "Unit tests written and passing for all new logic." | Adding a PHPUnit test file per ability (7 more test classes) would duplicate the helper tests through a thin wrapper. Following the existing FileManager sibling convention (zero PHPUnit for any of its 9 ability classes) would be less costly but does not meet §VII at all. The compromise satisfies §VII's intent (non-trivial logic is tested) while acknowledging the sibling convention (thin wrappers are not). |
