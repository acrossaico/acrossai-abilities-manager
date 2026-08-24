# Architecture

Last reviewed: 2026-05-22

## System Overview

AcrossAI Abilities Manager is a WordPress plugin that adds a management UI
and runtime enforcement layer on top of the WordPress Abilities API
(WP 6.9+). Admins configure per-ability overrides in the Manager UI; those
overrides are applied at request boot time on all non-Manager requests.

The plugin follows the WordPress Plugin Boilerplate PSR-4 layout with a
central Loader/Main.php hook surface, BerlinDB for override persistence,
and a REST-API-first admin UI backed by @wordpress/dataviews.

## Major Components

- **`includes/Main.php`**: Sole hook-registration surface. All Loader
  wiring lives here. No other class calls `add_action` / `add_filter`
  through the Loader (exception: ARCH-ADV-001 in boot()).
- **`includes/Modules/Abilities/AcrossAI_Ability_Override_Processor`**:
  Static runtime processor. Bridges DB overrides into WP ability
  registrations at `plugins_loaded P20` via PATH A/B branching. Wires
  `wp_register_ability_args`, `wp_abilities_api_init`, and
  `mcp_adapter_expose_ability` directly in `boot()` (ARCH-ADV-001 deviation).
- **`includes/Modules/Abilities/Database/`**: Self-contained BerlinDB classes —
  `AcrossAI_Abilities_Table`, `AcrossAI_Abilities_Schema`, `AcrossAI_Abilities_Row`,
  and `AcrossAI_Abilities_Query`. `AcrossAI_Abilities_Query` is the single entry
  point for all DB reads/writes. JSON field decoding happens in
  `AcrossAI_Abilities_Row::__construct()` via `get_json_fields()`. Supersedes the
  Sitewide DB classes (Feature 012).
- **`includes/Modules/Abilities/AcrossAI_Abilities_Access_Control`**: Wraps
  `wpb-access-control` library for per-ability rule storage and permission
  callback injection.
- **`includes/Utilities/`**: Shared sanitization (`AcrossAI_Sanitizer`),
  field utilities.

## Boundaries

- **Manager REST namespace** (`acrossai/v1`): PATH A —
  override injection is skipped entirely. All Manager UI reads see pure
  WP registry values, never merged override values.
- **All other requests** (PATH B): override injection fires at boot; blocked
  abilities are unregistered after all registrations complete.
- **BerlinDB layer**: `AcrossAI_Abilities_Query` is the only entry point for
  DB reads/writes. No direct `$wpdb` SQL in module or REST classes.
- **Hook surface**: Only `includes/Main.php` wires hooks through the Loader.
  `boot()` in the processor is the only approved exception (ARCH-ADV-001).

## Integrations

- **WordPress Abilities API** (WP 6.9+): `wp_register_ability_args`,
  `wp_abilities_api_init`, `wp_unregister_ability`.
- **MCP Adapter** (external plugin): `mcp_adapter_expose_ability` filter
  for per-server allowlist enforcement (PATH B only, `accepted_args = 3`).
- **`wpb-access-control` library**: Per-ability permission rules. Injected
  at `$args['permission_callback']` time. Fails open when library absent.
- **(Removed in Feature 034)** The `wpb-mcp-servers-list` library was used until
  Feature 034 (2026-06-14) to collect registered MCP server IDs for the deleted
  Allowed Servers admin dropdown. The library has been excised; no replacement.
  Any plugin needing MCP-server enumeration chooses its own mechanism via the
  5-hook extension surface from `specs/034-.../contracts/extension-hooks.md`.
- **Action Scheduler**: Not currently used; prefer for any future async jobs.

## Risks / Complexity Hotspots

- **Override cache TTL (12h transient)**: Stale cache after delete/reset is
  mitigated by direct `bust_cache()` calls in Override + Bulk controllers.
  Any new write path must fire one of `acrossai_abilities_after_create`,
  `acrossai_abilities_after_update`, or `acrossai_abilities_after_delete`; if none
  apply, call `bust_cache()` directly (W-001 pattern — see DECISIONS.md).
- **PATH A detection is a performance hint, not a security gate**: If the
  Manager REST namespace constant is misconfigured, override injection may
  fire on Manager requests. REST routes remain protected by `check_permission()`
  independently of PATH detection.
- **`mcp_adapter_expose_ability` accepted_args = 3**: Fail-open when
  `$server_id` is empty (MCP adapter passes fewer than 3 args). Update
  `accepted_args` if MCP adapter contract changes.
- **PHPUnit blocked**: No WP test bootstrap in project. All PHPUnit test
  files exist but cannot be run until `phpunit.xml.dist` + WP bootstrap
  shim are added (T014, pre-existing gap).


## AC-QUERY-LAYER-FILTERING

All list-endpoint filtering (search, sort, pagination, field filtering) MUST occur in the query builder layer (`AcrossAI_Ability_Registry_Query`), not in the REST controller. Pagination headers (`X-WP-Total`, `X-WP-TotalPages`) MUST reflect the filtered results.

**Rationale**: Query layer is the single source of truth for "what items exist in the result set". Filtering at query layer ensures pagination counts are accurate, search/sort/filter operations treat filtered items as non-existent, and REST controller doesn't duplicate filtering logic.

**Pattern**: In query builder loop, before adding to results: `if ( condition ) { continue; }` to skip filtered items and prevent them from being added to result set.

**Example**: `AcrossAI_Ability_Registry_Query::query()` excludes protected abilities at line 67–70 before appending to `$results[]`.

**Reference**: Feature 005 (commit `62d25ad`), plan.md FR-001, FR-003.

---

## PATTERN-SINGLE-SOURCE-UTILITY

When a logical concept is used in multiple places (query layer + REST controller), extract it to a single utility class with public static methods. Call the utility from both locations instead of duplicating logic.

**Benefits**:
- DRY principle enforced (Constitution §VI)
- Single edit point (fix once, applies everywhere)
- Easier to test in isolation
- Self-documenting (utility name = concept name)

**Structure**:
```php
// includes/Utilities/AcrossAI_SingleSourceTruth.php
class AcrossAI_SingleSourceTruth {
    public static function get_items(): array { /* return list */ }
    public static function is_member( string $id ): bool {
        return in_array( $id, self::get_items(), true );
    }
}

// Usage location 1: Query layer
if ( AcrossAI_SingleSourceTruth::is_member( $item_id ) ) { ... }

// Usage location 2: REST controller
if ( AcrossAI_SingleSourceTruth::is_member( $slug ) ) { ... }
```

**When to Apply**: Logic used in 2+ locations, small enough for `Utilities/`, stateless (only static methods).

**Reference**: `AcrossAI_Protected_Abilities` (feature 005, commit `62d25ad`), called from query layer + REST controller.

## PATTERN-STAGE-NAMING

> **Forward-pointer note (Feature 040, 2026-07-01)**: The Logger was the establishing consumer for this pattern (raw event → formatted → validated → stored). The Logger module was removed in Feature 040 but the pattern remains conceptually valid — it applies to any multi-stage data transformation surface (e.g., ability payload sanitization, library registry normalization).

In modules with multi-stage data transformations (raw → processed → formatted → stored), use distinct variable names for each transformation stage. This improves code clarity and prevents accidental overwrites.

**Pattern**:
```php
// Stage 1: Raw extraction
$output_value = $result;

// Status detection based on raw value
if ( is_wp_error( $result ) ) {
    $output_value = $result->get_error_message();
}

// Stage 2: Formatting/truncation
$formatted_output = AcrossAI_Logger_Formatter::format_value( $output_value );

// Stage 3: Storage
$entry['output'] = $formatted_output;
```

**Why this matters**:
- Reader immediately sees which stage each variable represents
- Prevents conditional overwrites from affecting later logic
- Self-documents the transformation pipeline
- Easier to debug (set breakpoints at each stage)

**When to Apply**: Any class processing data through 3+ stages (raw, validated, transformed, formatted, stored).

**Reference**: `AcrossAI_Ability_Logger::finish_pending_entry()` (feature 006, lines 195–220), where `$output_value` (raw) vs. `$formatted_output` (stage 2) enables clear status detection logic without confusion.

**Evidence**:
Feature 006 (2026-05-20): Refactored logger to use `$output_value` (raw), `$formatted_output` (formatted). Code review confirmed improved readability. PHPCS 0 errors.

---

## PATTERN-FEATURE-ASSET-SEPARATION

> **Forward-pointer note (Feature 040, 2026-07-01)**: The Logger's separate `js/logger` and `css/logger` webpack entries were the establishing example for this pattern. Feature 040 removed those entries alongside the module. The pattern remains valid for the surviving `js/abilities`, `js/ability-library`, and future module-specific bundles — feature-specific asset handles + independent rebuild/versioning is the right shape whenever a module owns its own UI surface.

When a feature module has its own admin UI, separate its assets from the main manager assets. Use feature-specific asset handles instead of generic names to prevent coupling and enable independent rebuild/versioning.

**Pattern**:
```
build/
  css/
    index.css              # main manager assets
    logger.css             # feature-specific: Feature 006
  js/
    index.js               # main manager assets
    logger.js              # feature-specific: Feature 006
```

**In Admin/Main.php**:
```php
public function enqueue_styles( string $hook_suffix ) {
    $on_abilities = false !== strpos( $hook_suffix, 'acrossai-abilities-manager' );
    $on_logs      = false !== strpos( $hook_suffix, 'acrossai-abilities-logs' );
    
    if ( ! $on_abilities && ! $on_logs ) {
        return;
    }
    
    // Main assets
    if ( $on_abilities ) {
        wp_enqueue_style( 'acrossai-abilities-manager', ... );
    }
    
    // Feature-specific assets
    if ( $on_logs && $this->logger_asset_file ) {
        wp_enqueue_style( 'acrossai-abilities-logger', ... );
    }
}
```

**Why this matters**:
- Each feature can be built/deployed independently
- No cross-feature asset conflicts
- Clear ownership of which CSS/JS belongs to which feature
- `webpack.config.js` can define separate entry points

**When to Apply**: When a feature module adds new admin pages or tabs with dedicated UI.

**Reference**: Feature 006 logger (2026-05-20): Assets named `logger.css`, `logger.js`, `logger.asset.php` (not `index.*`). Admin/Main.php extended hook suffix detection to load logger assets only on `acrossai-abilities-logs` page.

**Evidence**:
Old pattern: `build/js/index.css` + `build/js/index.js` used for all admin UI (coupled).
New pattern: `build/css/logger.css` + `build/js/logger.js` isolated to logger tab (decoupled).
Admin/Main.php enqueue_scripts() now checks both `acrossai-abilities-manager` and `acrossai-abilities-logs` hook suffixes before enqueueing.


## Keep Here
- stable system boundaries (PATH A/B, Manager namespace, BerlinDB layer)
- ownership lines between modules or services
- integration constraints that affect many features (ARCH-ADV-001, W-001)

## Never Store Here
- step-by-step implementation plans
- one-off feature details
- stale diagrams without current boundaries

Update the review date when boundaries, ownership, or integrations materially change.

## AC-FILE-HEADER-PATTERN

All PHP files must follow a standardized file header pattern. This ensures consistency across the codebase and enables automated tooling.

**Exact pattern**:
```php
<?php
/**
 * Brief description (one line).
 *
 * Longer description (optional, 1-2 sentences).
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Logger
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Logger;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
```

**Key rules**:
- `@package`: Always `AcrossAI_Abilities_Manager` (underscores, not backslashes)
- `@subpackage`: Full PSR-4 path starting with `AcrossAI_Abilities_Manager`, e.g., `AcrossAI_Abilities_Manager/includes/Modules/Logger`
- `@since`: Always `0.1.0` (not `1.0.0`; 0.1.0 represents initial plugin release)
- ABSPATH check: Use `defined( 'ABSPATH' ) || exit;` format (modern guard, single line with `||`)
- Namespace: Matches @subpackage with backslashes and follows underscore convention

**Reference file**:
`includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` demonstrates the correct pattern.

**Evidence**:
Feature 006 (2026-05-19): Fixed file headers in 3 logger files to match this pattern. All changed from old-style `if ( ! defined( 'ABSPATH' ) ) { exit; }` to modern guard. All changed from `@package AcrossAI\Abilities\Logger` to `@package AcrossAI_Abilities_Manager`. PHPCS 0 errors, PHPStan L8 exit 0.

**Why this is durable**:
New developers copy-paste headers from existing files. If all files follow one pattern, copy-paste stays consistent. If files vary, inconsistency spreads. This constraint prevents drift.

---

## 2026-05-20 — Enable dependency upgrades without plugin code changes (ARCH-ZERO-CODE-DEPENDENCY-UPGRADE)

**Pattern**: Architecture that allows dependency upgrades (composer constraint changes only) without modifying plugin code

**Conditions** (all required):
1. **Singleton-based service integration** — Services are accessed via `::instance()` static factory, not direct instantiation
2. **Interface-based dependency injection** — Integration points use service locators or abstract interfaces, not concrete class dependencies
3. **No breaking API changes** — Pre-validated via pre-update audit (changelog, API signature review, security scan)
4. **Clean separation of concerns** — Library is isolated from plugin hooks, Main.php, and core architecture

**Implementation Pattern**:
```php
// ✅ Singleton + Service Locator (supports zero-code upgrades)
class AcrossAI_Sitewide_Access_Control {
    private static $_instance = null;
    
    public static function instance() {
        if ( null === self::$_instance ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    private function __construct() {}
    
    public function get_manager() {
        // Service locator pattern — library is encapsulated
        $ac = new wpboilerplate\AccessControlLibrary();
        return $ac->get_manager();
    }
}

// Usage: always via instance()
$ac = AcrossAI_Sitewide_Access_Control::instance();
$manager = $ac->get_manager(); // Works regardless of library version
```

**Benefit**: Allows upgrades to ^X.Y constraints with zero plugin code changes; only composer.json and composer.lock are modified.

**Validation**: All Phase 1 tests must pass without plugin code changes. If code changes are required, the upgrade is NOT zero-code; refactor architecture or escalate as Feature X-N (separate task).

**Evidence**:
Feature 007 (2026-05-20): Upgraded wpb-access-control dev-main → ^1.0 with:
- **0 plugin files modified** (only composer.json, composer.lock)
- **0 code changes to AcrossAI_Sitewide_Access_Control** (pre-existing singleton pattern worked as-is)
- **100% Phase 1 test pass rate** (6/6 tests, no code adaptation needed)
- **All security constraints validated** (DEC-PERM-CB, SEC-04, SEC-03, DEC-FAIL-OPEN-NOTICE)

**Counter-Example** (do NOT do this):
```php
// ❌ Direct instantiation (breaks with API changes)
public function get_manager() {
    return new wpboilerplate\AccessControlManager(); // If constructor signature changes, breaks
}

// ❌ Static method coupling (hard to version)
$manager = AccessControlManager::get_instance(); // Hardcoded class name

// ❌ Concrete class properties (prevents upgrades)
private AccessControlManager $manager; // If interface changes, code breaks
```

**Where to Look Next**:
- `includes/Modules/Sitewide/AcrossAI_Sitewide_Access_Control.php` (singleton + service locator pattern)
- `specs/007-upgrade-access-control/` (zero-code upgrade example)
- `.specify/memory/CONSTITUTION.md` (singleton pattern requirement)

**Maintenance Rule**:
When adding new library integrations, architect using singleton + service locator pattern to enable future zero-code upgrades. Document public API contracts in code comments. Test integration points with multiple library versions (if available) before locking to a specific constraint.


---

## PATTERN-ENQUEUE-PAGE-GUARD

Page detection in `enqueue_styles()` and `enqueue_scripts()` MUST use dedicated `is_*_page()` boolean helper methods. Never use intermediate `strpos` variables (`$on_abilities`, `$on_logs`). Each helper uses Yoda `===` strict comparison against a hardcoded WP hook suffix string.

**Required pattern**:
```php
public function enqueue_scripts( string $hook_suffix ) {
    if ( ! $this->is_manager_page( $hook_suffix ) && ! $this->is_logs_page( $hook_suffix ) ) {
        return;
    }
    // ...
}

private function is_manager_page( string $hook_suffix ): bool {
    return 'toplevel_page_acrossai-abilities-manager' === $hook_suffix;
}
```

**Forbidden pattern**:
```php
$on_abilities = false !== strpos( $hook_suffix, 'acrossai-abilities-manager' ); // strpos variable
$on_logs      = false !== strpos( $hook_suffix, 'acrossai-abilities-logs' );    // strpos variable
if ( ! $on_abilities && ! $on_logs ) { return; }
```

**Why this matters**:
- `strpos` intermediate variables are architecture violations (V1/V2 flagged in Feature 011 review)
- `===` strict comparison prevents type-coercion bypass (SC-011-04)
- Named helpers are self-documenting and reusable across enqueue methods
- Extends AC-ENQUEUE-ADMIN constraint; see also DEC-MENU-HOOK-SUFFIX

**Evidence**: Feature 011 (2026-05-24) — V1/V2 architecture violations resolved in T011. PHPCS exit 0, PHPStan L8 exit 0.

**Reference**: `admin/Main.php` (`is_manager_page()`, `is_logs_page()` — canonical implementations).

---

## PATTERN-ASSET-DECOMMISSION-ORDER

When decommissioning a webpack bundle, the PHP constructor `include` MUST be removed before deleting source files or build artifacts. Removing the built file before the PHP include causes a PHP fatal error on the next page load.

**Correct order**:
1. Remove PHP constructor `include` and associated property (T008 pattern)
2. Remove webpack entry from `webpack.config.js`
3. Delete source files (`src/js/bundle/`, `src/scss/bundle/`)
4. Run a clean build (`npm run build`)

**Wrong order** (causes PHP fatal):
- Delete `build/js/bundle.asset.php` ← triggers fatal immediately
- Then try to remove the PHP include

**Why this matters**:
- WordPress boots PHP before any build step; a missing `include` target is a fatal error
- The fatal is hard to diagnose because the asset file is simply absent with no PHP warning at the include site
- This ordering risk was documented as RISK-001 in Feature 011 `tasks.md` before implementation began

**Evidence**: Feature 011 (2026-05-24) — PLAN-SEC-003 flagged the ordering risk in `specs/011-merge-abilities-ui/security-constraints.md`. Task order enforced: T008 (PHP) → T002/T003 (sources) → T001 (webpack) → T015 (build). Zero PHP fatals during implementation.

**Reference**: `specs/011-merge-abilities-ui/tasks.md` (T008, RISK-001), `admin/Main.php` constructor (correct `file_exists()` guard pattern).

---

## PATTERN-MODULE-DECOMMISSION

When decommissioning a module and merging it into a target module, follow this ordered sequence:

1. Create renamed DB layer classes (Table, Schema, Row) in the target module directory.
2. Update target Query's `$table_schema`/`$item_shape` + all `use` statements and return types.
3. Port CRUD methods from old Query into target Query (apply `sanitize_ability_slug()` as first statement per SEC-01).
4. Update all consumers (Processor, Access Control, admin classes) to import from the target module.
5. Remove old module bootstrap wiring from `Main.php`.
6. Delete old REST controllers entirely — no porting needed if the target module already has REST coverage for the same data.
7. Pre-deletion grep: confirm zero references to old class names before deleting any files.
8. Delete the old module directory.

**Never delete files before step 7 passes cleanly.**

**Reference**: Feature 012 — Sitewide module decommission into Abilities module; tasks T002–T021 (commit `56139de`).

---

## PATTERN-BERLINDDB-QUERY-PORT

When porting a BerlinDB Query class to a renamed module, do not create new Row/Schema/Table classes from scratch. Only update:

- (a) the two `use` statements pointing to the old Row/Schema
- (b) `$table_schema = NewSchema::class` and `$item_shape = NewRow::class`
- (c) all method return types and closure parameter types: `OldRow` → `NewRow`

The renamed DB classes are sufficient; no logic changes needed beyond the above three areas.

**Reference**: Feature 012 T005 — `AcrossAI_Abilities_Query.php` ported from Sitewide to Abilities without new Row/Schema classes (commit `56139de`).

---

## PATTERN-NAMED-EXPORT-JEST

To unit-test a pure helper embedded in a React component module without rendering the component, export the helper as a named `export function` alongside the default component export. Jest imports it with `const { helper } = require('../path/Component.jsx')`. The default export is unaffected.

**Rules**:
- The helper must be side-effect free and stateless (no hook calls, no closure over component state).
- Named exports from JSX component files are approved for testability only — not for shared business logic (use `src/js/utilities/` for that).
- The Jest test file must mock all `@wordpress/*` imports used by the module (`@wordpress/i18n`, `@wordpress/data`, etc.).

**Example**:
```js
// AbilityForm.jsx
export function validateRequiredFields(ability, slugSuffix) { /* pure logic */ }
export default function AbilityForm(props) { /* component */ }

// validateRequiredFields.test.js
const { validateRequiredFields } = require('../../../src/js/abilities/components/AbilityForm.jsx');
```

**Reference**: Feature 013 — `export function validateRequiredFields(ability, slugSuffix)` in `src/js/abilities/components/AbilityForm.jsx`, tested in `tests/jest/abilities/validateRequiredFields.test.js` (15 tests). Commit `35e9003`.

---

## ARCH-SANITIZER-TWO-CLASS

The sanitization layer has two classes. Always use the correct one.

- **`AcrossAI_Sanitizer`** (`includes/Utilities/AcrossAI_Sanitizer.php`): Base class. Owns `sanitize_mcp_servers_array()` and the `MAX_MCP_SERVERS` / `MAX_SERVER_ID_LENGTH` constants. FQCN: `\AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer`.
- **`AcrossAI_Abilities_Sanitizer`** (`includes/Utilities/AcrossAI_Abilities_Sanitizer.php`): Thin wrapper. Owns `sanitize_mcp_servers()` which delegates to the base class.

**Rule**: PHPUnit tests for MCP-server sanitization MUST use `AcrossAI_Sanitizer::sanitize_mcp_servers_array()` via its FQCN. Using `AcrossAI_Abilities_Sanitizer` is valid at call sites but the method signature differs; tests targeting boundary constants must target the base class.

**Evidence**: Feature 016 (2026-05-27) — `AbilitiesValidationTest.php` T017 tests use `\AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer::sanitize_mcp_servers_array()` directly. All 6 pass.

---

## ARCH-PHPUNIT-BOOTSTRAP

PHPUnit bootstrap for this plugin requires two specific preconditions:

1. **ABSPATH before autoloader**: `define('ABSPATH', dirname(__DIR__) . '/')` must appear in `tests/bootstrap.php` before `require_once vendor/autoload.php`. The `defined('ABSPATH') || exit` guard in every plugin file will silently exit and produce 0 tests if this order is wrong.
2. **Narrow `phpunit.xml.dist` scope**: Only include test files that do NOT transitively load BerlinDB Table subclasses. BerlinDB Table constructors call `add_action()` / `get_option()` — functions absent from stub bootstrap. DB-dependent test files (`AbilitiesQueryTest`, `AbilitiesWriteControllerTest`, `AbilitiesReadControllerTest`, `AbilitiesProcessorTest`, `AbilitiesExposureControllerTest`) require a real WP environment and must be excluded from the stub-bootstrap suite.

**Reference**: `tests/bootstrap.php`, `phpunit.xml.dist`.

---

## ARCH-ABILITYFORM-SECTION-ORDER

The canonical `AbilityForm.jsx` section DOM order is:

| # | Section | Variant |
|---|---------|---------|
| 1 | Identity | A (db) only |
| 2 | Site Permission | B (non-db) only |
| 3 | MCP Exposure (+ extension slot) | A + B |
| 4 | Annotation Overrides | A + B |
| **5** | **User Access** | **A + B** |
| 6 | Callback | A (db) only |
| 7 | Schema | A (db) only |

Numbers are global across both variants; sections not applicable to a variant simply do not render. All seven `.sect` divs must be children of a single `.panel` div. **As of Feature 034 (2026-06-14)**, the tail of Section 3 (MCP Exposure) renders the public extension slot `applyFilters( 'acrossai_abilities.form.extra_sections', [], { abilityId, slug, draft, isNonDb } )` at the location formerly occupied by the deleted Allowed Servers checkbox list. The slot is part of the public contract per FR-010 of Feature 034. New features adding sections must insert between Annotation Overrides (4) and User Access (5), or after Schema (7) — never outside the `.panel`. New features adding extension hook callsites should follow the dot-notation JS naming convention established by Feature 034 (`acrossai_abilities.form.*`).

**Evidence**: Feature 016 (2026-05-27) — commits `5de0307`, `161d1d4`, `e341d1a` restored order, corrected numbers 1–6. Feature 018 (2026-05-29) — inserted User Access as Section 5, renumbered Callback → 6, Schema → 7. Feature 034 (2026-06-14) — Section 3's Allowed Servers content replaced with `extra_sections` extension slot.

---

## PATTERN-AC-COMPONENT-INTEGRATION

Integrating the `@wpb/access-control` (`wpb-access-control`) vendor library into a React form:

1. **Named import only**: `import { AccessControl } from '@wpb/access-control'` — never default import.
2. **Webpack alias → `AccessControl.js`, not `index.js`**: `index.js` imports `AccessControl.scss`, which would extract CSS into the JS bundle's CSS sidecar. Point the alias directly at `AccessControl.js` to avoid SCSS double-extraction.
3. **SCSS import in the feature's SCSS entry**: `@import '../../../vendor/wpboilerplate/wpb-access-control/js/AccessControl';` appended to `src/scss/abilities/admin.scss` — never imported in JS.
4. **Module-level `abilitiesConfig`**: `const abilitiesConfig = window.acrossaiAbilitiesManager || {}` declared outside the component function (stable for the page lifetime).
5. **Three-branch rendering gate**:
   - `isCreate` → placeholder `<p>`
   - `!isCreate && !abilitiesConfig.access_control_available` → warning notice
   - `!isCreate && savedAbility?.ability_slug && abilitiesConfig.access_control_available` → `<AccessControl namespace="acrossai-abilities" resourceKey={savedAbility.ability_slug} restApiRoot={abilitiesConfig.rest_url || '/wp-json'} nonce={abilitiesConfig.nonce || ''} hideHeader hideSaveButton onChange={handleAcChange} />`
6. **No `onSave` prop**: the component manages its own save lifecycle; never integrate it with the main form save flow.
7. **`acSaveOk` dirty-reset pattern**: see `DEC-AC-SAVE-FLOW-PATTERN` in `DECISIONS.md`.
8. **`acInitialRef` baseline pattern**: see `DEC-ACINITIAL-REF-BASELINE` in `DECISIONS.md`.

**Evidence**: Feature 018 (2026-05-29). `src/js/abilities/components/AbilityForm.jsx`, `webpack.config.js`, `src/scss/abilities/admin.scss`.

---

## PATTERN-JEST-SECTION-SCOPE

When multiple `.sect` divs in `AbilityForm.jsx` share the same CSS class names
(e.g., `p.desc`, `p.notice-warning`), scope test assertions to the target section
by finding the `.sect` whose `sect-num` text matches:

```js
const getSection5 = () =>
    Array.from(container.querySelectorAll('.sect')).find(
        (sect) => sect.querySelector('.sect-num')?.textContent.trim() === '5'
    );
const sect5 = getSection5();
const placeholder = sect5.querySelector('p.desc'); // scoped — not global
```

Always scope to the target section. Global `container.querySelector('p.desc')` will
find the first match across all sections, producing false positives.

**Evidence**: Feature 018 T022 (2026-05-29) — `p.desc` from Section 3 ("No MCP servers registered yet.") was falsely matching Section 5 assertion.

---

## PATTERN-CHECKBOX-SANITIZE (2026-05-29, Feature 019)

Checkbox `register_setting()` sanitize callbacks MUST handle absent values. Browsers do not transmit unchecked checkbox inputs, so the callback receives `null`/`''` when the box is unchecked. Use `empty($value) ? 0 : 1` in a named public method (`sanitize_*_flag()`), not an inline closure (Settings API cannot serialize closures). Pass it as `array( $this, 'sanitize_*_flag' )`.

**Canonical example**: `SettingsMenu::sanitize_uninstall_flag()` in `admin/Partials/SettingsMenu.php`.

---

## PATTERN-UNINSTALL-DATA-GATE (2026-05-29, Feature 019)

`uninstall.php` MUST guard all destructive SQL inside `if ( (bool) get_option('acrossai_abilities_uninstall_delete_data', 0) )`. Tables and data are **preserved by default** (option default `0`). Plugin-owned settings options (config, not data) are always removed unconditionally.

**Rationale**: Prevents accidental data loss on plugin reinstall cycles or test-env uninstall triggers.

---

## PATTERN-LOGGER-OPTION-FEED-FILTER (2026-05-29, Feature 019)

> **Forward-pointer note (Feature 040, 2026-07-01)**: The original consumer (`AcrossAI_Ability_Logger::schedule_cleanup()` + `cleanup_old_logs()`) was removed in Feature 040 alongside the Logger module. **The pattern still applies to any Settings API option that feeds an `apply_filters()` default**, including future settings that gate scheduled work — the "option 0 = never schedule" short-circuit remains a useful convention. The canonical example below has been retained for reference despite the code no longer existing on disk.

When a module reads a settings option AND exposes an `apply_filters()` hook, feed the option value as the filter default: `apply_filters( 'hook', get_option( 'key', 0 ) )`. The scheduling guard short-circuits when the effective value is `0` (never schedule). This decouples "should I schedule?" from execution and preserves filter-based override for testing/programmatic control.

**Canonical example (historical — removed in Feature 040)**: `AcrossAI_Ability_Logger::schedule_cleanup()` + `cleanup_old_logs()` in `includes/Modules/Logger/AcrossAI_Ability_Logger.php`.

---

## PATTERN-WP-DEBUG-LOG-GUARD (2026-05-30, Feature 020)

Wrap every `error_log()` call in a `WP_DEBUG_LOG` conditional guard for Plugin Check compliance. Never suppress or remove `error_log()` calls — guard them so they only fire when debug logging is explicitly enabled by the site owner.

**Canonical pattern** (identical for all call sites; must be exact):
```php
if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    error_log( '...' );
}
```

Key rules:
- `defined()` check BEFORE boolean evaluation — avoids PHP notice on undefined constant
- `phpcs:ignore` moves INSIDE the guard, on the line immediately before `error_log()`
- Never use `WP_DEBUG` alone; never use `ini_get`; never vary the pattern across call sites

**Evidence**: Feature 020 — 12 `error_log()` calls guarded across 5 PHP files; Plugin Check CI passes with zero non-suppressed errors.

---

## PATTERN-CI-WORKFLOW-HARDENING (2026-05-30, Feature 020)

GitHub Actions CI workflows must apply three hardening measures:
1. **SHA-pin every `uses:` reference** to an immutable commit hash with the mutable tag as a comment: `uses: actions/checkout@<sha> # v4`. Prevents supply-chain substitution if an upstream tag is moved.
2. **Declare `permissions: {}` at the workflow level** (before `jobs:`), with specific permissions granted only at the job level. Prevents future jobs from inheriting broader token grants.
3. **Set `timeout-minutes` on every job** to fail fast and prevent unbounded runner consumption. Use `15` minimum for jobs that start Docker containers (e.g. `wp-env start`); `10` is sufficient for jobs with no container startup.

**Canonical example**: `.github/workflows/plugin-check.yml` — permissions: {}, timeout-minutes: 10, three SHA-pinned actions.

**Evidence**: Feature 020 — architecture review V1–V3 findings; all three applied.

---

## PATTERN-CONSTITUTION-SYNC-REPORT (2026-05-30, Feature 020)

Every `CONSTITUTION.md` version bump (MAJOR, MINOR, or PATCH) must also update the `<!-- SYNC IMPACT REPORT -->` HTML comment at the very top of the file. The comment must list: version change (e.g. `1.4.2 → 1.4.3`), modified sections, rationale, templates reviewed, and any deferred TODOs.

**Why**: The sync report is the primary audit trail for architecture governance changes. An unupdated sync report will mislead architecture reviewers about what changed and when.

**Evidence**: Feature 019 (1.4.1 → 1.4.2), Feature 020 (1.4.2 → 1.4.3).

---

## PATTERN-PLUGIN-CHECK-WP-ENV-DIRECT (2026-05-30, Feature 020)

**Do NOT use `WordPress/plugin-check-action@v1` directly** — it has a silent exit-0 bug on Node 24.16 (`ubuntu-latest` ≥ 2026-05-25). See `BUG-PLUGIN-CHECK-ACTION-NODE24`.

The canonical CI pattern for running Plugin Check is to inline the steps manually:

```yaml
- name: Install @wordpress/env
  run: npm install -g --no-fund @wordpress/env

- name: Create wp-env config
  run: |
    cat > .wp-env.json << 'EOF'
    {
      "core": null,
      "plugins": [],
      "testsEnvironment": false,
      "mappings": {
        "wp-content/plugins/<your-slug>": "."
      }
    }
    EOF

- name: Start WordPress environment
  run: wp-env start

- name: Install Plugin Check
  run: wp-env run cli wp plugin install plugin-check --activate

- name: Run Plugin Check
  run: |
    wp-env run cli wp plugin activate <your-slug>
    wp-env run cli wp plugin check <your-slug> \
      --ignore-codes=<phpcs_error_codes> \
      --include-experimental
```

**Key rules**:
- `"plugins": []` in `.wp-env.json` — never add URL-based plugins here; install them via WP-CLI post-boot
- `"testsEnvironment": false` — starts one environment instead of two (faster, avoids Docker resource issues)
- `--ignore-warnings` does NOT exist on `wp plugin check` CLI — use `--ignore-codes` only
- `timeout-minutes: 15` minimum — Docker container startup adds significant time vs plain CI steps

**Evidence**: `.github/workflows/plugin-check.yml` at commit `d58f487` on branch `020-plugin-check-ci`.

---

### PATTERN-REGISTERED-CALLBACK-TRUST (Feature 021, 2026-05-31)

**When to use**: Any time an ability or plugin feature needs to execute a callable that was previously stored as PHP code in the database.

**Pattern**:
1. DB row stores a `sanitize_key()` callback key string only — never executable PHP.
2. Version-controlled plugin/theme code registers callables via `apply_filters('acrossai_abilities_registered_callbacks', array())`.
3. At execution time: retrieve the allow-list, apply `sanitize_key()` to the stored key, check `isset($callbacks[$key]) && is_callable($callbacks[$key])`, then dispatch via `call_user_func($callbacks[$key], $input)`.
4. Any missing or unregistered key returns `WP_Error('unsupported_callback_type')` — never a silent no-op.
5. Existing `php_code` DB rows must fail closed (step 4); they must never be silently mapped or executed.

**Why**: Eliminates `eval()` (OWASP A03) while preserving extensibility. Trust boundary is version control, not the database.

**Evidence**: `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` `registered_callback` case; `includes/Utilities/AcrossAI_Abilities_Sanitizer.php`; `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`; Feature 021 commits `ec358de`–`8d2cdef`.

---

### PATTERN-CI-QUALITY-GATE-SPLIT (Feature 022, 2026-05-31)

**Pattern**: Split PHP quality gates into three dedicated CI workflows, each with a single concern:

| Workflow | Standard | Scope |
|----------|----------|-------|
| `phpcs.yml` | WPCS (`phpcs.xml.dist`) | All PHP paths scanned by `phpcs.xml.dist` |
| `phpstan.yml` | PHPStan level 8 | All PHP paths in `phpstan.neon.dist` |
| `phpcompat.yml` | PHPCompatibility `testVersion 7.4-` | Production dirs only: `acrossai-abilities-manager.php`, `uninstall.php`, `includes/`, `admin/`, `public/` |

**Key rules**:
- PHPCompatibility is in `phpcompat.yml` only — it was removed from `phpcs.xml.dist` to prevent double-counting and allow different scan scopes.
- All three workflows use `permissions: {}` at workflow level, `permissions: contents: read` at job level, SHA-pinned `actions/checkout` and `shivammathur/setup-php`, and `timeout-minutes: 10`.
- `phpcs.xml.dist` excludes `tests/`, `.specify/`, `docs/`, `specs/`, `src/`, `.claude/`, `.agents/`, `.github/` via `<exclude-pattern>` entries.

**Why**: One workflow per concern means failures are immediately attributable; PHPCompatibility scoped to production dirs avoids false positives from test stubs.

**Evidence**: `.github/workflows/phpcs.yml`, `phpstan.yml`, `phpcompat.yml`; commit `9da22d7` on branch `022-ci-workflows-phpcs-cleanup`.

---

### PATTERN-WORDPRESS-PEER-DEPENDENCIES (Feature 025, 2026-06-03)

**Pattern**: `@wordpress/data`, `@wordpress/element`, and `@wordpress/i18n` are WordPress script globals — they are provided by WordPress at runtime via `wp_enqueue_script` dependency handles, not bundled. Declare them in `peerDependencies` in `package.json` to prevent the ESLint `import/no-extraneous-dependencies` rule from flagging import statements for these packages.

**Key rules**:
- Add all `@wordpress/*` packages that are imported in source files but not bundled (i.e., provided by WordPress at runtime) to the `peerDependencies` object with version `"*"`.
- Do NOT add them to `devDependencies` — doing so would cause ESLint to treat them as local packages and potentially conflict with the `import/no-extraneous-dependencies` peer-vs-dev distinction.
- Packages that ARE bundled (e.g., `@wordpress/dataviews`, `@wordpress/icons`) belong in `dependencies` or `devDependencies` depending on whether they are production or build-only.

**Why**: ESLint's `import/no-extraneous-dependencies` rule raises an error for any import whose package is not listed in `dependencies`, `devDependencies`, or `peerDependencies`. WordPress globals are neither dependencies (not bundled) nor devDependencies (not test-only); `peerDependencies` is the semantically correct category.

**Evidence**: `package.json` `peerDependencies` block added in Feature 025 for `@wordpress/data`, `@wordpress/element`, `@wordpress/i18n`. ESLint reported 3× `import/no-extraneous-dependencies` errors before the fix; 0 after.

---

### PATTERN-JESTENV-WPSCRIPTS (Feature 025, 2026-06-03)

**Pattern**: Any Jest test that depends on browser APIs (`localStorage`, `sessionStorage`, `window`, `document`, etc.) must be run via `npx wp-scripts test-unit-js`, not plain `npx jest`. The `@wordpress/scripts` test runner uses `@wordpress/jest-preset-default` which sets `testEnvironment: 'jsdom'`.

**Key rules**:
- Use `npx wp-scripts test-unit-js` as the canonical test runner for all Jest tests in this project.
- Plain `npx jest` defaults to a Node environment where `localStorage` is `undefined`, causing `ReferenceError: localStorage is not defined` at module evaluation time.
- If a test file uses `jest.mock()` for `@wordpress/*` packages, those mocks must still cover all named exports the module under test imports — the jsdom environment does not change mock requirements.

**Why**: `@wordpress/jest-preset-default` configures jsdom and also sets up `@wordpress/jest-console` and other WP-specific test utilities. Running outside `wp-scripts` loses all of this setup.

**Evidence**: `tests/jest/abilities/column-prefs.test.js` — 8 tests fail with `ReferenceError: localStorage is not defined` under plain `npx jest`; all 8 pass under `npx wp-scripts test-unit-js`. Feature 025.

---

### 2026-06-06 — Add-on registration filters MUST fire at init P99, not early priority (PATTERN-ADDON-FILTER-LATE-INIT)

**Status**: Active

**Why this is durable**
When the plugin exposes a filter that add-ons use to register definitions (e.g., `acrossai_abilities_api_init`), the filter must be applied late in `init` (P99) — not early (P10–P20). Add-ons typically hook at the default `init` priority (10). If the plugin fires the collection filter at P20, any add-on that hooks at P21–P99 silently misses registration with no error.

**Decision**
Plugin-defined filters whose purpose is to collect definitions or registrations from add-ons MUST be fired at `init` priority 99 (or the highest feasible late priority). Applying the filter early forces add-on developers to know the plugin's internal priority and hook earlier — an undocumented contract violation. Late firing gives add-ons the full `init` window.

**Pattern**
```php
// In the Registry::collect() method, wired via Loader at init P99:
public function collect(): void {
    if ( ! is_null( self::$definitions ) ) return; // idempotent
    $raw = apply_filters( 'acrossai_abilities_api_init', array() );
    self::$definitions = self::validate_and_normalize( $raw );
}
// In Main.php define_public_hooks():
$keys_registry = AcrossAI_Keys_Registry::instance();
$this->loader->add_action( 'init', $keys_registry, 'collect', 99 );
```

**Tradeoffs**
- Gained: add-ons can hook at any `init` priority ≤ 98 without coordination.
- Made harder: nothing meaningful — the Registry's static cache makes subsequent calls idempotent regardless of when collection runs.
- Reconsider: if a downstream consumer needs definitions before `init P99` completes (it does not — `wp_abilities_api_init` fires after `init`).

**Future mistake prevented**
Do not pick an early `init` priority (P10–P20) for collection filters. A future developer writing a new add-on who hooks at default P10 would appear to work, then break for any add-on hooking at P21+, creating silent, hard-to-diagnose missed registrations.

**Evidence**
Feature 027: plan.md initially proposed `init P20`; corrected to P99 by Security Review finding SEC-004 / SC-027-04 (specs/027-keys-submenu/security-constraints.md).

### PATTERN-PROTECTED-SLUGS-JS-LOCALIZE

When a PHP-managed list needs to gate UI behavior in JSX, expose it from PHP via
the `window.acrossaiAbilitiesManager` inline script in `admin/Main.php` rather
than hardcoding it in JSX.

**Pattern**
```php
// admin/Main.php — inline script
'protected_slugs' => \AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Protected_Abilities::get_protected_slugs(),
```
```jsx
// AbilitiesList.jsx
const PROTECTED_SLUGS = window.acrossaiAbilitiesManager?.protected_slugs || [];
```

**Why this is durable**
`AcrossAI_Protected_Abilities::get_protected_slugs()` is the single source of
truth for protected slugs (DEC-PROTECTED-SLUGS-PATTERN). Hardcoding the slug list
in JSX would duplicate it and create drift when the PHP list changes (e.g. when
a new mcp-adapter system tool is added via the filter). Discovered in Feature 029
when the plan referenced a non-existent `PROTECTED_SLUGS` JS constant.

**Future mistake prevented**
Do not define a `PROTECTED_SLUGS` array literal in JSX. Always read from
`window.acrossaiAbilitiesManager?.protected_slugs`. Apply this pattern to any
future PHP-managed list that gates admin UI behavior.

**Where to look next**
`admin/Main.php` (inline script localization, Feature 029 addition),
`src/js/abilities/components/AbilitiesList.jsx` (`PROTECTED_SLUGS` constant),
`includes/Utilities/AcrossAI_Protected_Abilities.php` (PHP source of truth),
DEC-PROTECTED-SLUGS-PATTERN (PHP-side centralization decision).

---

### 2026-06-11 — PATTERN-VENDOR-ASSET-FAMILY-HANDLE

**Pattern**
When registering a vendor library's CSS/JS asset via `wp_register_style()` /
`wp_register_script()`, the handle MUST carry the consuming plugin's prefix —
NOT the vendor library's own name. The vendor's name is for filesystem paths
and Composer; the WordPress handle is in the global asset registry and must
satisfy Plugin Check's 4+ character unique-prefix rule.

For a **family of plugins** that all bundle the same vendor library via
Jetpack Autoloader (Jetpack picks the highest PHP version across the family but
does not manage asset URLs), all plugins MUST register the asset under the
**same family-level handle**. WordPress's `WP_Dependencies::add()` silently
returns false on duplicate handle registration, so first-to-register wins and
the CSS loads exactly once site-wide — no duplicate `<link>` tags, no
conflicts, no `wp_style_is()` guard required.

**Convention for the AcrossAI family**:
- Handle format: `acrossai-<vendor-package-name>` (e.g.
  `acrossai-wpb-access-control` for `wpboilerplate/wpb-access-control`)
- Carries the 8-char `acrossai-` prefix (Plugin Check satisfied)
- Vendor name is preserved in the suffix for debuggability (DOM inspection
  reveals which library shipped the CSS)
- Same handle in every AcrossAI add-on that bundles the same library

**Anti-patterns to avoid**:
- Registering with the vendor's bare name (`'wpb-access-control'`) — fails
  Plugin Check; also race-prone if another vendor coincidentally uses the
  same handle.
- Per-plugin-scoped handles (`'acrossai-abma-wpb-access-control'`,
  `'acrossai-cora-wpb-access-control'`) — loads the same CSS twice from
  different vendor/ paths; wasteful.
- Renaming the upstream Composer package to "fix" the prefix — doesn't fix
  the handle (which is what Plugin Check checks); breaks every other
  consumer of the package.

**Limitation worth knowing**:
First-loaded plugin wins the URL, so its `vendor/` copy of the CSS is what
gets served — even if Jetpack Autoloader selected a different plugin's
higher-version PHP. The cleanest long-term fix is for the vendor library
itself to expose an `Assets::register()` method called from the Jetpack-
Autoloader-selected copy, so PHP version and CSS version stay in sync.
Until then, the family-level handle is the right pragmatic answer.

**Evidence**
`admin/Main.php:179, 188` — handle renamed from `'wpb-access-control'` to
`'acrossai-wpb-access-control'`. Plugin Check finding "Looks like there is
an element not using common prefixes" resolved with two single-line edits;
36 acrossai-prefixed elements become 37.

---

## PATTERN-REQUIRED-FIELD-MULTI-LAYER-AUDIT

**Status**: Active (established Feature 034)

When auditing the claim "every field the form treats as required has matching
4xx-on-missing server-side enforcement", check **all three** WordPress validation
layers, not just one. A field enforced at any one layer is technically secure, but
the audit must verify which layer enforces it — a JS-hook attack surface (e.g.,
`acrossai_abilities.form.save_payload`) lets subscribers strip fields from the REST
payload before serialization, and the auditor must trace which server-side layer
catches that case.

**The three layers**:

1. **REST `args` schema** — `'required' => true`, `'sanitize_callback'`,
   `'validate_callback'`. Lives in the route-registration block of the relevant
   REST controller (e.g., `AcrossAI_Abilities_Write_Controller::register_routes()`).
2. **Sanitizer presence guards** — early-return WP_Error with explicit `missing_X`
   codes inside `sanitize_create_request()` / inside the controller's main handler
   (e.g., `AcrossAI_Abilities_Write_Controller::create_ability()` lines 196-204:
   `missing_label`, `missing_description`, `missing_category`).
3. **Validator** — deeper presence + format checks returning WP_Error 400 with
   codes like `invalid_slug` (e.g., `AcrossAI_Abilities_Validator::validate_ability()`
   lines 412-414).

**Audit procedure**:

For each field the JS form treats as required (find via `validateRequiredFields` or
the form's explicit required-attribute set):
1. Grep the REST controller's `register_routes()` for the field name — is it in the
   `args` schema with `'required' => true`?
2. Grep the sanitizer / controller handler for an early-return guard returning
   `missing_<field>` or equivalent 400.
3. Grep the validator for a guard returning `invalid_<field>` / `missing_<field>` /
   equivalent 400 with empty-check.

A field that passes only at layer (3) is still secure but produces less specific
error codes for the client. A field that passes only at layer (1) may be bypassed
by a JS-hook filter that strips it — the request reaches the server with the field
absent, layer (1) coerces silently, and you discover the gap only by tracing layers
(2) and (3).

**Reference**: Feature 034 SEC-001 audit (pre-implementation, `specs/034-.../security-constraints.md`)
verified `label`/`description`/`category` at layer (2). Feature 034 F2 finding
(post-implementation, via `/speckit-analyze`) discovered `slug_suffix` was NOT
enforced at layer (2) but WAS enforced at layer (3) — without the layer (3) check,
F2 would have falsely reported a vulnerability. Without layer (2) enforcement of
`slug_suffix`, a malicious `acrossai_abilities.form.save_payload` subscriber
stripping it would have produced an ambiguous `invalid_slug` error instead of a
clean 400-with-`missing_slug_suffix`. The audit is now a three-step procedure;
documented here so future security reviews don't repeat the layer-1-only
false-positive risk.

---

## PATTERN-JS-HOOK-CADENCE-SPEC

**Status**: Active (established Feature 034)

When exposing a JS action or filter via `@wordpress/hooks` to extension subscribers,
the spec entry MUST include **three** things, not two:

1. **Hook name** — canonical, versioned per the contract's deprecation cycle.
2. **Payload shape** — typed context object, every key listed and versioned.
3. **Reference-equality and firing-cadence semantics** — exactly when the hook
   fires, with three sub-questions answered:
   - Per-React-commit (selector reference changed) vs per-Redux-dispatch (store
     updated) vs per-event (DOM event)?
   - Is internal debouncing applied? (Default and recommended: "no — subscribers
     own their own debounce/throttle policy".)
   - Which value-equality function gates the fire (`Object.is`, `===`, deep-equal,
     hash)?

**Why item 3 matters**:

Item 3 is invisible by default. Spec authors usually write items 1 and 2 and assume
item 3 is "obvious"; it isn't. Different subscribers will subscribe assuming
different cadences, and only one of them will be right.

- Subscriber A assumes "fires per keystroke" and writes a 250ms debounce.
- Subscriber B assumes "fires per dispatched Redux action" and writes no debounce.
- Subscriber C assumes "fires once per save" and writes blocking logic.

Without an explicit contract, all three will ship and one (or all three) will be
wrong about which is right. Pin it in the spec; the implementation will then match
because it's now contractual — and reviewers can catch implementation drift early.

**Spec wording template**:

> The `{hook_name}` action fires on every React commit where the value returned by
> the Redux `{selector_name}` selector inside a `useEffect([value])` block has
> changed reference. Reference-equality uses `Object.is` (React's default). The
> plugin applies no internal debouncing — subscribers MUST own their own
> debounce/throttle policy. Subscribers MUST design against per-React-commit
> cadence, NOT per-Redux-dispatch cadence (which may be more or less frequent if
> intermediate states are batched out by re-render scheduling).

Apply equivalent wording for any cadence semantics (per-event, per-mount, etc.).
The key is naming the trigger, the equality semantics, and the subscriber's
obligation.

**Reference**: Feature 034 FR-005 (`acrossai_abilities.form.draft_changed`) shipped
with a partial cadence spec ("fires on every React commit where the form's draft
state reference has changed") that didn't pin which draft reference. F3 finding
caught this; spec amended to specify the Redux `getDraftAbility` selector + the
`useEffect([draft])` block. Implementation at
`src/js/abilities/components/AbilityForm.jsx:318` already matched the new wording —
the gap was in spec, not code. For any future JS extension hook this plugin or a
sibling plugin exposes, lead with the three-item template above.

---

### 2026-06-16 — PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION

**Pattern**
When a feature retires an Active decision: (1) mark the retired decision `**Status**: Superseded
by <new-DEC-NAME> (<date>)` and **keep the original entry intact** as historical context; (2) if
a *related canonical pattern* loses its current in-plugin consumer but remains conceptually valid
for future consumers, **annotate with a forward-pointer note** rather than marking Superseded —
e.g. *"Consumer removed in Feature N; pattern retained for the future X plugin which is expected
to be the next consumer."* Do not silently delete decision entries.

**Rationale**
Preserves the decision audit trail and prevents future Spec Kit synthesis from silently
regenerating retired features. Distinguishes "this decision no longer applies" (Supersede) from
"this pattern is correct but lives elsewhere now" (Annotate). Feature 035 applied both:
`DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` was Superseded by `DEC-PASS-AS-TOOL-REMOVED`;
`DEC-MCP-INJECT-REFLECTION-PATTERN` was annotated with a forward pointer to the future
`acrossai-mcp-manager` plugin.

**Evidence**
`docs/memory/DECISIONS.md` — `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` (Superseded) vs
`DEC-MCP-INJECT-REFLECTION-PATTERN` (annotated). Established by Feature 035.

---

### 2026-06-16 — PATTERN-HELPER-DELETION-GREP-FIRST

**Pattern**
When removing a private helper as part of a feature deletion, ALWAYS run
`grep -rEn '<helper_name>' includes/ src/ tests/` BEFORE the deletion task starts. If the grep
returns ANY hit outside the helper itself and the file you are deleting:
1. KEEP the helper in place.
2. Add a TODO note above the method enumerating the surviving caller(s).
3. If the helper carries a load-bearing partner-gate lesson (e.g. AC-rule fail-open paired with
   `WP_Ability::check_permissions()`), lift the lesson from BUGS.md into the helper's docblock so
   any future maintainer encountering the helper sees the constraint at the call site.

**Rationale**
A helper that "appears" to be deletion-target-private may have been adopted by an unrelated
surviving consumer between the original feature and the removal. Deleting unconditionally produces
a fatal class-load error or a silent permission bypass. Feature 035 caught
`user_has_ability_access()` had a second caller at `build_permission_callback()` line 386 — the
helper was preserved and the partner-gate guidance was lifted from
`BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS` into the helper's docblock.

This pattern is narrower than `BUG-INVENTORY-GREP-MISS` (which covers general feature-removal
inventory grep) — it specifically targets helper deletion gates and the docblock-lift step.

**Evidence**
`includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` — `user_has_ability_access()`
preserved with updated docblock; `specs/035-remove-pass-as-tool/user_has_ability_access-callers.txt`
preflight grep result.

---

### 2026-06-23 — PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH

> **Forward-pointer note (Feature 041, 2026-07-03)**: The `ALLOWED_ARGS_FIELDS` allowlist changed — `sub_group`, `sub_group_label`, and `tab_group` are no longer top-level allowlist entries; their canonical location is `$args['meta']['acrossai']['<field>']` (passed through opaquely via the `'meta'` allowlist entry). The Registry-boundary sanitize-vs-passthrough discipline described below is UNCHANGED; only the location of these specific fields inside `$args` moved. See `PATTERN-META-ACROSSAI-NAMESPACE`.

**Pattern**
`AcrossAI_Ability_Library_Registry::validate_and_normalize()` applies `wp_kses_post()` to
`category_label` and `slug_label`, but only **key-allowlist-filters** the `args` sub-array
(`array_intersect_key( $item['args'], array_flip( ALLOWED_ARGS_FIELDS ) )`). Every `args` *value* —
`description`, `label`, `sub_group_label`, `meta`, `input_schema`, `output_schema`, etc. — reaches
`window.acrossaiAbilityLibraryData.definitions[i].args.*` as the raw string (or array) the add-on
author registered. When you add a NEW consumer of any `args.*` value, you MUST choose one of:

1. **React text-node consumer** (`<p>{value}</p>`, `<span>{value}</span>`) — safe. JSX escapes by
   default. This is Feature 036's choice for `description` rendering (`LibraryCard.js` lines 178-181
   and 192-196).
2. **`dangerouslySetInnerHTML`, PHP server-rendered output, email template, or any non-JSX
   consumer** — MUST escape at the call site (`esc_html()`, `wp_kses_post()`, equivalent) OR add
   `wp_kses_post()` at the Registry boundary to harden it for all current and future consumers.

Never assume `args.*` values are pre-sanitized. The key allowlist proves a field is *permitted*; it
does NOT prove the contents are safe.

**Rationale**
The Library Registry intentionally trades server-side sanitization for forwards-compatible field
shapes (so `meta` / `input_schema` can stay structurally valid JSON, etc.). The consequence is that
XSS containment for `args.*` lives with the *first* consumer to ship. Today that contract is "React
text-node escape." Adding a second consumer that bypasses JSX would silently inherit XSS surface
that nobody owns. Feature 036's plan-level security review captured this as `SEC-001` and chose
defense option (c) — rely on FR-006 ("description MUST be rendered as plain text … the browser MUST
NOT interpret them as markup") as the forward guardrail. This entry elevates that guardrail from
feature-local to durable.

**Evidence**
`includes/Modules/Library/AcrossAI_Ability_Library_Registry.php:188` (the `array_intersect_key`
allowlist gate, no value sanitization); `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php:205-207`
(contrast: `category_label` / `slug_label` ARE `wp_kses_post`'d at the same depth);
`src/js/ability-library/components/LibraryCard.js:178-196` (current safe JSX text-node consumer);
`specs/036-library-page-full-width-and-descriptions/security-constraints.md` (`SEC-001` finding
that motivated this entry).

---

## PATTERN-ADMIN-NOTICE-SELF-CONTAINED — degraded-mode admin notices must reference only WP globals

**Captured**: 2026-06-30 (Feature 038)

**Rule**
Degraded-mode admin notices (the ones that fire when the plugin can't fully load — e.g. composer
autoloader missing, required vendor class absent, host package unavailable) MUST use only
globally-available WordPress functions inside the closure body. Allowed: `current_user_can`,
`printf`, `esc_html`, `esc_html__`, `_e`, `__`, `_x`, and a captured static message string.
FORBIDDEN inside the closure: `$this->`, `self::`, `static::`, `use ( $this )`, or any FQCN
under the plugin's own namespace. Always gate on `current_user_can( 'manage_options' )` per
`DEC-FAIL-OPEN-NOTICE`. Use `static function ()` (not `function () use ($this)`) to make the
self-containment contract self-evident at the registration site.

**Why durable**
The notice's purpose is to make a degraded state visible. If the closure body references a
plugin-namespaced symbol, the autoloader must resolve it — but the autoloader is by definition
absent in the very state the notice is meant to surface, so the notice itself fatals. A pure
WP-globals closure survives the degraded state and produces the actionable admin message that
operators need.

**Canonical implementations**
- `includes/Main.php:286-298` — the existing AddonsPage try/catch admin-notice pattern
  (`use ( $error_message )`, capability gate, `printf` + `esc_html`).
- `includes/Main.php` `__construct()` vendor-missing block — Feature 038's vendor-autoloader-
  missing notice, structurally enforced by
  `tests/phpunit/Includes/Test_Boot_Resilience.php::test_admin_notice_closure_is_self_contained`.

**Evidence**
- 30-Jun-2026 PHP Fatal trace at `includes/Main.php:228` (`AcrossAI_Loader` class-not-found) —
  the exact failure mode this pattern prevents.
- `specs/038-acrossai-main-menu-integration/security-review-plan.md` SEC-001.

---

## PATTERN-ACTIVATION-HOOK-EARLY-PRIORITY — vendor-precondition activation guards register at priority 1

**Captured**: 2026-06-30 (Feature 038)

**Rule**
Activation-time guards that check for vendor prerequisites (composer autoloader present,
required class autoloadable, required directory exists) MUST register via
`add_action( 'activate_' . plugin_basename( __FILE__ ), $callback, 1 )` — priority **1**, NOT
the default 10. Plain `register_activation_hook( __FILE__, $cb )` registers at priority 10. If
an existing default-priority activation callback transitively requires autoloaded classes that
aren't available, IT will fatal before a priority-10 guard fires. The precondition guard MUST
execute first so it can `wp_die` with an actionable message. Always wrap the `wp_die` message in
`esc_html__()` with the plugin's text domain.

**Why durable**
`register_activation_hook` translates internally to `add_action( 'activate_<plugin>', $cb, 10, 1 )`.
Order at the same priority is registration order — but order across priorities is priority order.
A priority-1 guard always runs before any priority-10 callback regardless of registration order.
This is the only correct way to ensure the guard fires first when an existing
`register_activation_hook` call is already in the entry file.

**Canonical implementation**
`acrossai-abilities-manager.php` lines ~75-95 — the vendor-autoloader-presence guard placed at
priority 1, alongside the existing `register_activation_hook` pair at lines 68-69 (default
priority 10).

**Evidence**
- `specs/038-acrossai-main-menu-integration/security-review-plan.md` SEC-002.
- Structural test:
  `tests/phpunit/Includes/Test_Boot_Resilience.php::test_activation_guard_registered_at_priority_one`.

---

## PATTERN-SHARED-MENU-CONSUMER-IDEMPOTENCY — paired class_exists($fqcn, false) + did_action() guards

**Captured**: 2026-06-30 (Feature 038)

**Rule**
When multiple plugins consume a shared external package by direct instantiation (e.g. multiple
AcrossAI plugins all bootstrapping `\AcrossAI_Main_Menu\SettingsPage`), each consumer's bootstrap
MUST use **both** of these guards together:

1. `class_exists( $fqcn, false )` — the `false` second argument explicitly disables autoload
   during the check. Critical when another consumer hard-`require_once`d the same source from a
   different file path (development mu-plugin pointing at `wp-content/main-menu/src`, alternate
   vendored copy, etc.). `require_once` dedupes by file path, NOT class name; two paths declaring
   the same class fatal with "Cannot declare class". A bare `class_exists($fqcn)` would trigger
   autoload and could itself fatal mid-check.
2. `did_action( '<scoped_bootstrap_action>' )` — a project-scoped action like
   `acrossai_main_menu_bootstrapped`. Short-circuits when any consumer has already fired the
   canonical signal.

After successful instantiation, the consumer MUST fire `do_action( '<scoped_bootstrap_action>' )`
so future consumers loaded later in the request lifecycle short-circuit cleanly.

A pure `class_exists` check is necessary but not sufficient — it covers the "class already
declared" failure mode but not the "menu already wired" failure mode (constructor side effects
that don't dedupe themselves).

**Why durable**
Real failure mode observed in Feature 038: a smoke-test mu-plugin at
`wp-content/mu-plugins/acrossai-tab-smoketest.php` was hard-`require_once`-ing source files
from `wp-content/main-menu/src/` while the consumer plugin loaded the same class via
jetpack-autoloader's PSR-4 map. The two paths fataled with
`Fatal error: Cannot declare class AcrossAI_Main_Menu\SettingsPage`. Fix: add paired guards to
both sides. The pattern generalizes to ANY shared external package consumed by multiple plugins.

**Canonical implementations**
- Consumer plugin: `acrossai-abilities-manager.php` `plugins_loaded` priority-0 callback (lines
  ~100-129) — paired guards + `do_action` fire.
- Smoke-test mu-plugin: `wp-content/mu-plugins/acrossai-tab-smoketest.php` lines 12-40 — same
  paired guards + same `do_action` fire.

**Evidence**
- `specs/038-acrossai-main-menu-integration/security-review-plan.md` SEC-004.
- Live incident reproduction: see Feature 038 governance summaries for the diagnosis path
  (composer-update-not-run → mu-plugin coexistence fatal → paired guards).

---

## PATTERN-SHARED-SETTINGS-SECTION-SCOPE — sections on a shared Settings API host carry plugin scope

**Captured**: 2026-06-30 (Feature 038)

**Rule**
When a plugin contributes sections (`add_settings_section()` + `add_settings_field()`) to a
shared WP Settings API host page provided by another plugin or package (e.g.
`acrossai-co/main-menu`'s `acrossai-settings` page), the plugin MUST scope its sections so admins
can tell which plugin owns what. Two modes — prefer the first:

1. **Tabbed mode (preferred)** — register a tab via the host's tabs filter (e.g.
   `add_filter( 'acrossai_settings_tabs', $cb )`) and target the per-tab page slug returned by
   the host's helper (e.g. `\AcrossAI_Main_Menu\SettingsPage::tab_page_slug( 'my-tab' )` →
   `'acrossai-settings-my-tab'`). The tab title carries the plugin scope; individual section
   titles stay plain ("Display Settings", "Log Settings", etc.).
2. **Flat-mode fallback** — when the host doesn't support tabs (e.g. running against host
   < 0.0.4) or a plugin can't register one for some other reason, prefix every section title
   with the plugin scope using an em-dash:
   `__( 'Abilities — Display Settings', '<text-domain>' )`. Required because the host renders
   sections in registration order with no visual separator between plugins.

**Invariant across both modes**: `option_group` stays the shared slug (e.g. `'acrossai-settings'`)
in `register_setting()` and `settings_fields()` — only the `$page` argument to
`add_settings_section`/`add_settings_field` changes per tab. The shared option_group is what makes
the form submission, the nonce, and the save flow work regardless of which tab a section lives
under.

**Why durable**
Surfaced post-implementation by user feedback on a live install with multiple AcrossAI plugins:
"add Abilities words as this whole setting is for abilities and if there is any other plugin
using this settings page it will add MCP or something else". The host's render path
deliberately makes sections from multiple plugins indistinguishable to give plugins control
over their own scoping. Without an explicit scope rule, every new consumer plugin re-invents
this convention and the host page becomes visually chaotic.

**Canonical implementations**
- Tabbed: `admin/Partials/SettingsMenu.php::register_tab()` + `register_settings()` using
  `\AcrossAI_Main_Menu\SettingsPage::tab_page_slug( self::TAB_SLUG )`.
- Flat-mode reference (briefly used before tabs landed in 0.0.4): same file at the
  pre-tab state had `'Abilities — Display Settings'` / `'Abilities — Log Settings'` /
  `'Abilities — Uninstall Settings'` as section titles before the tab landed.

**Evidence**
- v0.0.4 host README: *"once any plugin registers a tab, sections still attached to the bare
  'acrossai-settings' slug are not rendered — migrate them under a tab"* — confirms the
  tabbed-mode preference.
- Feature 038 tasks.md T032 candidate (d).

---

### 2026-07-01 — PATTERN-VENDOR-LIB-JS-CONSUMER-AUDIT

**Why durable**
When a vendor PHP library ships with a bundled React (or other JS) component that consumers embed, an upgrade that adds a new PHP constructor arg often mirrors the change with a matching JS prop or `wpbAcConfig`-style global. PHP-only planning misses this because:
- PHP grep (`grep 'new ClassName(' ...`) finds the constructor call sites but not the React `<Component ... />` mount points.
- Composer's autoloader silently picks the newest vendor across all installed plugins, so the wrong JS bundle version is masked as "works on my machine" until a live install exercises the new REST route shape.
- The built vendor JS is opaque — grep hits are minified variable names, not readable prop names.

The audit protocol below turns this from "surprise post-deploy" into a plan-time checklist item.

**Canonical audit steps (per vendor-library major-bump)**
1. **Read the vendor's README for the new version** — look for any React config example (`wp_localize_script(..., 'wpbAcConfig', [...])`) or component prop signature (`<Component prop1=... prop2=... />`). New keys are the smoking gun.
2. **Grep the built bundle for the new identifier** — `grep -oE '<new-key>|<new-prop>' vendor/<pkg>/assets/build/*.js`. Even minified, the identifier survives as a property access.
3. **Grep the consumer plugin's JSX for existing component mount points** — `grep -rn '<VendorComponent' src/`. Every hit must be audited for the new prop.
4. **Grep the consumer plugin's `wp_localize_script` calls** — any `wpbAcConfig`-shaped array needs the new key added. Source the value from a single PHP constant so PHP + JS stay in sync (Constitution §VI DRY).
5. **Rebuild the JS bundle** (`npm run build`) and verify the built JS contains the new key/prop by grepping the output.

**Evidence**
- **Feature 039 (this feature)** — introduced the bug (planned "no JS changes needed"), then fixed as a follow-up. Vendor: `wpboilerplate/wpb-access-control` v1→v2. Missed prop: `pluginSlug`. Missed URL segment: `/wpb-ac/v1/{slug}/...`. See `BUG-VENDOR-LIB-JS-URL-SLUG-MISSING` (BUGS.md, 2026-07-01).
- **Feature 036** — `wpb-access-control 1.2.1→1.6.0` bump; JS bundle path stayed the same → no consumer-side JS changes needed. This is why the "PHP-only" heuristic felt safe until v2.

**Applies to**
Any vendor Composer library that ships a `wp-scripts`-built bundle and is consumed by an in-plugin React tree. Especially high-risk on `major` version bumps of libraries that expose a `wp_localize_script`-style config object.

**Tags**: vendor, react, upstream-upgrade, localize-script, wpbAcConfig, prop-audit, upgrade-checklist, phase-1-research

---

### 2026-07-01 — PATTERN-LOCALIZE-KEY-VERSIONED-GUARD

**Why durable**
`wp_localize_script` and `wp_add_inline_script('before')` inject data into a JS
global whose shape evolves independently of the JS bundle. When a new plugin
release adds a NEW key to that data (e.g. Feature 039 added
`access_control_slug`), the following cache-skew scenario becomes possible:

1. Admin loads WP-Admin page → HTML cached in browser with pre-release localize
   data (missing the new key).
2. Plugin deploys the new release → JS bundle version bumps, browser fetches
   the new JS.
3. Browser back-navigates or serves stale HTML → JS reads
   `window.acrossaiAbilitiesManager.new_key` → `undefined`.

If the JS uses that value in a template literal (e.g. `` `.../${config.new_key}/...` ``),
the URL becomes `.../undefined/...` → typically 404 → `apiFetch(...).catch(()=>{})`
swallows the error → user sees "plausibly working but empty" UI with no on-screen
error. This is exactly the failure mode captured in
`BUG-VENDOR-LIB-JS-URL-SLUG-MISSING`.

Prevention: for every NEW localize key introduced by a release, every JS
consumer must guard the key with a truthiness check before interpolation.
Existing keys stable across releases don't need guards (they're contract).

**Canonical implementations**
- **Save-path guard** (early return): `src/js/abilities/components/AbilityForm.jsx:545-555` —
  extends the AC-save gate condition with `&& abilitiesConfig.access_control_slug`.
  Silently skip the sub-operation; the parent operation already succeeded.
- **Render guard** (conditional mount): `src/js/abilities/components/AbilityForm.jsx:1489-1497` —
  extends the mount JSX condition with `&& abilitiesConfig.access_control_slug`.
  Component doesn't mount; degraded-mode fallback (empty/hidden section) is
  automatically what the user sees.

**Evidence**
- Feature 039 commit `2a6d8c5` shipped the new `access_control_slug` localize key
  and its two JS consumers without guards.
- User feedback ("I am not see the whole value 5") surfaced
  `BUG-VENDOR-LIB-JS-URL-SLUG-MISSING` at runtime.
- Commit `b82fd39` added the two guards described above, closing SEC-006 from
  `specs/039-composer-package-updates/security-review-staged.md`.

**Applies to**
Every new localize key introduced by a release. Refactoring an existing key
(rename, reshape) is a breaking change requiring a bundle-version bump and
does NOT need a guard — it needs a compat shim, which is a different pattern.

**Tags**: localize-script, wp_add_inline_script, browser-cache-skew, defensive-coding, release-versioning, jsx-conditional, undefined-guard, deploy-hazard

---

### 2026-07-02 — PATTERN-GREP-AUDIT-VS-MANDATED-STRINGS

**Status**: Active

**Why durable**
Full-repo audit greps that enforce "zero matches for identifier X" MUST exclude files that a functional requirement (FR-###) explicitly mandates references to X. When FR-N requires `uninstall.php` to reference the very strings a "removal audit" grep forbids plugin-wide, the audit task will always fail on a compliant implementation — the task cannot pass unless the mandate is violated. This self-contradiction is easy to introduce during removal-feature spec/task authoring: the FR-authoring reviewer thinks "we drop the table here" and the audit-authoring reviewer thinks "no references anywhere post-removal" — both are correct individually; together they collide.

**Repo evidence**
- **Feature 040** — `tasks.md:T026` (final full-repo grep audit merge blocker) scoped to `includes/ admin/ src/ tests/ acrossai-abilities-manager.php uninstall.php webpack.config.js` with pattern `Logger|acrossai_ability_logs|acrossai-abilities-log|acrossai_ability_logger|LogsTable|LogsMenu|is_logs_page|logger_asset_file|log_retention`. Simultaneously `spec.md:FR-007` mandates `uninstall.php` MUST reference `acrossai_ability_logs` (DROP TABLE), `acrossai_ability_logs_db_version` (delete_option), `acrossai_ability_logger_cleanup` (as_unschedule_all_actions), and `acrossai_abilities_log_retention_days` (delete_option) inside the opt-in delete-data gate. Running T026 against a compliant implementation returned 7 matches, all inside the FR-007 cleanup block at `uninstall.php:44-63`.
- Surfaced by `/speckit-architecture-guard-architecture-review` in the same session as Feature 040 implementation.

**Prevention**
At spec/task-authoring time, execute the proposed audit grep pattern against the FR-mandated files. If it returns non-zero matches on the expected compliant implementation, the audit is misdesigned. Fix via one of:
1. **File exclusion**: narrow the audit's file list to exclude the mandate's target file (e.g. `--exclude=uninstall.php` for cleanup patterns).
2. **Pattern split**: separate "runtime identifiers that must be absent everywhere" (class names, admin URLs, JS globals) from "data-cleanup identifiers that must exist in uninstall.php only" (table names, option keys, hook names). Two greps, each with the right scope.
3. **Explicit expected count**: `[uninstall.php grep matches = N with the current implementation; anywhere else = 0]`. Least preferred — brittle against implementation changes.

**Rule of thumb**
For every mandate-audit pair, the audit pattern MUST be strictly stronger than the mandate's negation. I.e. `audit_pattern ⊂ ¬mandate_pattern` when scoped to the same files. Test this at authoring time with `grep -c` on the expected post-implementation tree.

**Applies to**
Every removal / decommission / rebrand feature that pairs a "zero references" acceptance criterion with a functional requirement that mandates references in a specific file. Common in: uninstall cleanup, deprecation shims, backwards-compat removal, security-hardening rip-outs.

**Tags**: grep-audit, spec-authoring, self-contradiction, removal-feature, uninstall, mandate-audit-pair, merge-blocker, task-design

---

### 2026-07-03 — PATTERN-META-ACROSSAI-NAMESPACE

**Status**: Active

**Why durable**
Plugin-specific ability extension fields — the ones this plugin owns and adds to the ability shape beyond what WordPress core defines — MUST live under `$args['meta']['acrossai'][<field>]`. Sibling of `$args['meta']['mcp']` (this plugin's MCP integration fields) and `$args['meta']['annotations']` (WP-core-owned annotations). Never at the top level of `$args`.

**Rationale**
The top level of `$args` is WordPress core's namespace. Placing plugin-specific fields there mixes ownership: WP core may add a `sub_group` field in a future release, colliding with the plugin's field. The `meta` sub-namespace convention is WordPress's established extension point, and this plugin already uses it consistently for MCP fields. Feature 041 corrects the Features 033/037 oversight (which placed `sub_group`, `sub_group_label`, `tab_group` at top level) and formalizes the convention.

**Repo evidence**
Feature 041 (2026-07-03) — refactored `sub_group`, `sub_group_label`, `tab_group` from top-level `$args` into `$args['meta']['acrossai']`. Hard-cut migration; top-level shape no longer read by `Ability_Definition::push_definition()`. `AcrossAI_Ability_Library_Registry::ALLOWED_ARGS_FIELDS` no longer contains those three top-level entries; `meta` remains and carries the nested shape through the allowlist.

**Canonical example (post-041)**
```php
// includes/Modules/Library/Ability_Definition.php:65+ (push_definition)
$meta_acrossai = ( isset( $args['meta']['acrossai'] ) && is_array( $args['meta']['acrossai'] ) )
    ? $args['meta']['acrossai']
    : array();

$sub_group = isset( $meta_acrossai['sub_group'] ) ? (string) $meta_acrossai['sub_group'] : '';
$tab_group = isset( $meta_acrossai['tab_group'] ) ? (string) $meta_acrossai['tab_group'] : '';
```

Add-on subclass:
```php
return array(
    'name' => 'my-plugin/my-ability',
    'args' => array(
        'label' => 'My Ability',
        'meta'  => array(
            'acrossai' => array(
                'sub_group'       => 'debug-log',
                'sub_group_label' => 'Debug Log',   // optional
                'tab_group'       => 'sales',
            ),
        ),
    ),
);
```

**Applies to**
Every future field added to the ability shape that is specific to `acrossai-abilities-manager`'s UI, Library, or non-execution behavior. Excludes WP-core-owned fields (annotations, show_in_rest) which stay at their canonical WP-core locations. Excludes MCP-related fields (continue using `meta['mcp']`). Reserved namespace — sibling AcrossAI-org plugins (e.g. a hypothetical `acrossai-mcp-manager`) MUST use their own key (`meta.acrossai_mcp` or similar); `meta.acrossai` belongs exclusively to `acrossai-abilities-manager`.

**Sibling patterns**
- `PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH` — Registry `ALLOWED_ARGS_FIELDS` allowlist strips unknown keys; the `'meta'` allowlist entry passes nested sub-arrays through opaquely. Feature 041 relies on this passthrough for the `meta.acrossai.*` sub-keys.
- `DEC-META-ACROSSAI-NAMESPACE` — the hard-cut decision behind this pattern.

**Tags**: meta, namespace, extension-fields, library, acrossai, plugin-specific, sibling-mcp, sibling-annotations, feature-041

---

### 2026-07-13 — PATTERN-BULK-REWRITE-MATRIX

**Status**
Active

**Why this is durable**
When absorbing a sibling plugin's PHP tree into this manager (Feature 046 pattern), the bulk rewrite must apply an ordered set of transforms per file. Order matters: exact-string transforms (namespace decl, `use` imports, text-domain string, plugin constants) MUST run before partial-match transforms (visible label text, category slugs, identifier names) so manager-branded strings emitted by earlier passes are never re-rewritten. Discovered via Feature 046 slash-form ability slug hit that leaked past the initial dash-suffix rule.

**Pattern**
9-step ordered PHP rewrite per file, in this order: (5a) namespace decl; (5b) `use` imports + FQCN refs; (5c) text-domain string; (5d) plugin constants (URL/PATH/BASENAME/FILE/NAME_SLUG/NAME/VERSION); (5e) visible label text (`Foo Bar` → `Baz Qux`); (5f) category/ability slug prefixes (both `-` and `/` separators); (5g) class/function/method/constant/variable names; (5h) singleton property `$_instance` → `$instance` (per DEC-SINGLETON-PSR2-PROPERTY); (5i) docblock `@package`/`@subpackage`/`@since` header. Use `sed`/`perl` per-file with synchronous writes (per BUG-PYTHON-STRREPLACE-PARTIAL-WRITE). Run `phpcbf` after the matrix to normalize whitespace before PHPCS.

**Tradeoffs / Prevention**
- Gained: deterministic, order-safe bulk rebrand of 200+ file trees in one shell script (`scripts/046-rewrite-matrix.sh` reference).
- Reconsider: hand-editing is still required for docblock `@param` descriptions and code-level rules (short-ternary, Yoda). Automate those in a follow-up if a future absorption is planned.

**Tags**: rewrite, absorption, sed, perl, phpcbf, order, feature-046

---

### 2026-07-13 — PATTERN-OPTION-KEY-MIGRATION-OR-MONOTONIC

**Status**
Active

**Why this is durable**
Activation-time option-key migrations must combine two properties to be safe: **idempotent** on repeated activation, and **OR-monotonic** when folding a legacy boolean into a still-live target. Missing either property causes silent data-integrity regressions (Feature 046 activation migration would have overwritten manual manager edits with stale companion values if the null-check was omitted, or silently disabled an admin's prior "delete on uninstall" choice if the OR-fold demoted a true).

**Pattern**
For a value-copy migration: read the legacy option; if it is not null, THEN read the target key; only `update_option( target, legacy )` if the target key is null (never overwrite manual manager edits); THEN unconditionally `delete_option( legacy )`. For a boolean opt-in fold: read the legacy option; if it is truthy AND the target opt-in is falsy, `update_option( target, 1 )` — never demote. THEN unconditionally `delete_option( legacy )`. Both properties yield a no-op on repeat activation.

**Tradeoffs / Prevention**
- Gained: safe upgrade path from a companion/sibling plugin without data loss and without silent config reset.
- Reconsider: when the target key is expected to differ semantically (not just a rename), a full merge step must be added before the delete.

**Tags**: activation, wp_options, migration, idempotent, monotonic, feature-046

---

### 2026-07-18 — PATTERN-WP-CORE-UPGRADER-ABILITY

**Status**: Active

**Pattern**
Canonical wrap of a WP core upgrader (`Core_Upgrader::upgrade()`, `Plugin_Upgrader::install()/bulk_upgrade()`, `Theme_Upgrader::install()/bulk_upgrade()`, future `Language_Pack_Upgrader`) inside an Abilities-API ability:

1. `require_once` block: `wp-admin/includes/update.php` + `class-wp-upgrader.php` + `misc.php` + `file.php`.
2. `permission_callback` ANDs `manage_options` with the WP core capability for the action (`update_core` / `update_plugins` / `update_themes`).
3. `execute()` begins with `File_Mods_Guard::blocked_response('install')` short-circuit.
4. For core specifically: multisite guard bails cleanly if `is_multisite() && ! current_user_can('update_core')`.
5. Fetch the update object via WP core (`get_core_updates()` / `find_core_update()` / `get_plugin_updates()` / `get_theme_updates()`).
6. Reject cleanly (return success + `updated=false`) when the offer is null, false, or has `response !== 'upgrade'`.
7. Instantiate `WP_Ajax_Upgrader_Skin` (the same silent skin `Install_Plugin.php` uses).
8. Call `$upgrader->upgrade($update)` (or `->install()` / `->bulk_upgrade()`).
9. Result-interpretation ladder: `WP_Error` → failure with message; `null`/`false` → drain `$skin->get_errors()` for message; anything else → success. Read `get_bloginfo('version')` before + after for `from_version`/`to_version` (core).

**Rollback path (Feature 043 extension)**
`Core_Upgrader::upgrade($offer)` does NOT check whether `$offer->version` is older or newer than currently-installed — it installs whatever the offer describes. WP core does NOT need a `WP_Downgrader`; feed the upgrader an older offer and it's a downgrade. Older offers aren't in the WP core update transient (that only holds the latest), so fetch them directly from the WP.org Core API 1.7 endpoint (`https://api.wordpress.org/core/version-check/1.7/?locale={locale}`) via `wp_remote_get()`. Force `$offer->response = 'upgrade'` before invoking the upgrader (shape parity with `get_core_updates()`). Cache the per-locale offer list in a site transient with `DAY_IN_SECONDS` TTL. Add a `version_compare($target, get_bloginfo('version'), '>=')` guardrail refusing non-downgrade requests (steer callers to `wp-core-update` for the forward path). See sibling pattern `PATTERN-WP-CORE-ROLLBACK-VIA-API-OFFER` for the API-offer manipulation flow.

**Why this is durable**
Every future Abilities-API-facing WP core upgrade — forward or backward — wraps this recipe. Deviating from any step introduces a subtle bug (missing require = fatal at runtime, missing skin = swallowed errors, missing multisite guard = unauthorized network upgrade, forward-path assumptions that block rollback). Complements `PATTERN-WP-CORE-ROLLBACK-VIA-API-OFFER` (rollback specifics) and the shared `File_Mods_Guard` gate.

**Where to look**
- `includes/Abilities/Core/Update_Wp_Core.php` (canonical forward-path implementation).
- `includes/Abilities/Core/Rollback_Wp_Core.php` (rollback-path implementation).
- `includes/Abilities/Plugins/Update_Plugin.php` + `Plugins/Install_Plugin.php`.
- `includes/Abilities/Themes/Update_Theme.php` + `Themes/Install_Theme.php`.
- `specs/042-core-category-and-wp-core-update/plan.md` §"WP core update flow (mirrors wp-admin/update-core.php)".

**Tags**: wp-core, upgrader, canonical, plugin-update, theme-update, wp-core-update, wp-core-rollback, ability-pattern, feature-042, feature-043

---

### 2026-07-18 — PATTERN-WP-CORE-ROLLBACK-VIA-API-OFFER

**Status**: Active

**Pattern**
Rolling WordPress core back to an earlier version via the Abilities API — WP-core-only, no bundled updater code:

1. Fetch `https://api.wordpress.org/core/version-check/1.7/?locale={locale}` via `wp_remote_get()`. WP.org returns an `offers` array with every version it still exposes.
2. Cache the per-locale offer list in a site transient (`acrossai_abilities_manager_core_offers_{locale}` in this codebase) with `DAY_IN_SECONDS` TTL — matches the reference `core-rollback` plugin and the WP.org API's own cache posture.
3. Look up the caller's requested version in the cache. On miss, refresh once; still miss → clean "not in offer list" error.
4. Manipulate two shape-parity fields on the offer: `$offer->response = 'upgrade'`; `$offer->current = $offer->version`. Do NOT modify `$offer->download`, `$offer->packages`, or `$offer->version` — those come from WP.org and are the bytes/checksums Core_Upgrader will verify.
5. Hand the offer directly to `Core_Upgrader::upgrade($offer)`. The upgrader is version-direction-agnostic.
6. Interpret the result with the shared ladder from `PATTERN-WP-CORE-UPGRADER-ABILITY`.

**Why this is durable**
The technique isn't obvious from `Core_Upgrader`'s code alone — you have to know WP.org's API returns older offers too, and that Core_Upgrader is direction-agnostic. Andy Fragen's `core-rollback` plugin proved the technique with WP admin UI wiring; the Abilities-API version simplifies by calling the upgrader directly (skipping the `pre_http_request` + `site_transient_update_core` injection dance the dashboard flow requires).

**Alternative path considered and rejected**
Manipulating `site_transient_update_core` + hooking `pre_http_request` on the WP core update-check URL (the `core-rollback` plugin's approach). Justified for that plugin because it funnels through `update-core.php?action=do-core-reinstall`. Overkill for a headless Abilities API — direct `Core_Upgrader::upgrade()` invocation is simpler and has fewer moving parts.

**Tradeoffs / Prevention**
- Gained: no `WP_Downgrader` needed; reuse WP core's signed-tarball verification via the WP.org offer.
- Prevention: always run the `version_compare('>=' )` guardrail so a caller confusing upgrade/rollback doesn't overwrite a newer install with an older one. Always force `$offer->response = 'upgrade'` — `Core_Upgrader` inspects `->download`/`->packages`/`->version` but shape parity with `get_core_updates()` future-proofs against WP core adding a response check.
- Reconsider IF: WP.org changes its API contract (endpoint bump beyond 1.7, offer schema change, or removes the `offers[]` array). Cache-poisoning: `set_site_transient` is per-locale, so a hostile locale value can only affect its own cache row (`sanitize_key` on the transient suffix).

**Where to look**
- `includes/Abilities/Core/Rollback_Wp_Core.php::fetch_offer()` + `fetch_all_offers()`.
- `wp-content/plugins/core-rollback/src/Core.php` (external MIT-licensed reference).
- `specs/043-wp-core-rollback-via-wporg-api/spec.md`.
- `specs/043-wp-core-rollback-via-wporg-api/security-constraints.md` (13-constraint list).

**Tags**: wp-core, rollback, downgrade, wp-org-api, core-upgrader, direction-agnostic, feature-043

---

## PATTERN-CLIENT-SIDE-BULK-THUNK

Client-side bulk operations (Redux-style thunks that loop a per-slug REST call for a checkbox-selected set) MUST mirror the reference `bulkUpdateStatus` shape at `src/js/abilities/store/index.js`:

1. **SET_SAVING bracket**: dispatch `{ type: SET_SAVING, isSaving: true }` first; the corresponding `false` dispatch goes in a `finally` block (not the try-body) so it fires on both success and failure paths.
2. **`Promise.all(perSlug)` in the try body**: unbounded parallelism — same behaviour the reference shape has never exceeded in production. Do NOT add a chunker without evidence of server-side load pressure.
3. **`dispatch(actions.fetchAbilities())` after resolve**: on success only, before the finally. Refetches the list so every affected row reflects the new state.
4. **`SET_SAVE_ERROR + throw` in catch**: dispatch `{ type: SET_SAVE_ERROR, error: err.message }` THEN `throw err;`. Re-throw is critical — it lets the calling component's `handleBulkApply` catch, keep the selection intact for retry, and skip the "clear selection on success" step. Without the re-throw, SEC-001 partial-failure discipline breaks silently.
5. **For endpoints that resolve with `null` on soft failure**: after `Promise.all`, filter results for `null`/`undefined` and throw a synthetic `Error(`${failedCount} of ${slugs.length} …`)`. Guards `BUG-AC-NULL-RETURN-SILENT-FAIL` + `BUG-COMPOSER-AC-SLUG-DOUBLE-ENCODE`. Required for composer PUT paths; may be required for other external integrations too.

Reference implementations (Feature 056): `bulkUpdateTristate`, `bulkClearOverrides`, `bulkSetUserAccessRule` in `src/js/abilities/store/index.js`. Jest coverage template: `tests/jest/abilities/store.bulkUpdateTristate.test.js` (payload discipline + partial-failure re-throw + SET_SAVING bracket).

**Tags**: store, bulk, thunk, promise-all, sec-001, feature-056

---

## PATTERN-BULK-BUSY-OVERLAY-WP-NATIVE-SPINNER

Full-screen busy overlay for bulk client-side operations. Consistent with WP-admin visual language, zero new npm deps, ~35 SCSS lines.

**Composition**:

1. **Shared component**: `src/js/abilities/components/BulkBusyOverlay.jsx` (~35 LoC). Takes one prop: `label` (also used as `aria-label`). Consumers mount conditionally on their own `busy` state.
2. **WP-native spinner**: `<span class="spinner is-active">` — the same spinner WP admin uses next to Save Draft. No bundled GIF asset; the browser fetches `wp-admin/images/spinner.gif` automatically.
3. **Scale override**: WP's default spinner is 20×20 with `float: right` + margins. For a centered fullscreen overlay, override to `width/height: 48px; background-size: 48px 48px; float: none; margin: 0; opacity: 1;` via a scoped selector (`.acrossai-abilities-bulk-busy-overlay__spinner.spinner`).
4. **Backdrop-blur overlay**: `position: fixed; inset: 0; background: rgba(255, 255, 255, 0.35); backdrop-filter: blur(4px); z-index: 100001; cursor: progress;` + centered flex layout.
5. **A11y**: `role="status"` + `aria-live="polite"` + `aria-label={label}` — announced to screen readers as the busy state changes.
6. **Body scroll-lock**: consumers pair with `useEffect(() => { document.body.style.overflow = "hidden"; return () => { document.body.style.overflow = prev; }; }, [busyFlag])`. Restored on cleanup; nested lifecycles compose safely if each guarding component saves/restores its own `prevOverflow`.
7. **Modal integration**: modals that mount their own overlay pair also render `<BulkBusyOverlay>` inside when their internal `busy` state is true. Escape-to-dismiss on the wrapping modal MUST be suppressed while busy (prevents half-applied state on the underlying multi-slug write).

Reference: Feature 056 (2026-07-21) — `src/js/abilities/components/{AbilitiesList,UserAccessBulkModal,BulkBusyOverlay}.jsx` + `.acrossai-abilities-bulk-busy-overlay*` rules in `src/scss/abilities/admin.scss`.

**Tags**: bulk, overlay, spinner, wp-native, backdrop-filter, scroll-lock, a11y, feature-056

---

### 2026-07-27 — Library third-party integration base class pattern (PATTERN-LIBRARY-INTEGRATION-BASE)

**Scope**: Library / Abilities / Extensibility

**Pattern**
To add a third-party plugin integration (toggle card + tab) to the Ability Library page:

1. Create `includes/Abilities/Integrations/<Vendor>.php` extending `AcrossAI_Integration_Ability_Base`.
2. Implement 5 abstract methods:
   - `slug()` — used as BOTH category AND tab_group.
   - `label()` — display label for the card/tab.
   - `is_plugin_active()` — per-request predicate; use a compound check on stable public symbols of the target plugin (a single-symbol check is spoofable — see security-review-plan.md SEC-002).
   - `enable_filter()` — attaches the target plugin's own AI-enable filter (e.g. ACF calls `add_filter('acf/settings/enable_acf_ai', '__return_true')`).
   - `abilities()` — fixed readonly display list `[{slug, label, description?}, ...]` shown inside the card.
3. Publish `public const TAB_GROUP = '<slug>'` on the subclass — Feature 060 FR-017. Third-party add-on plugins reference this constant instead of hardcoding the slug.
4. Instantiate once in `Main::define_public_hooks()` alongside the Core Abilities Bootstrap (`new My_Integration();`). The base class constructor auto-registers both hooks per `DEC-ABILITY-DEFINITION-CTOR-HOOKS`.

**Base class behaviour**
- Hooks `plugins_loaded @ P20` → `maybe_enable()` — reads config via `AcrossAI_Ability_Library_Config::is_integration_enabled()` (inverted default: missing = OFF per FR-008), calls subclass `enable_filter()` inside a try/catch (Constitution §V Integration Resilience; caught throwable is logged behind `WP_DEBUG_LOG` guard).
- Hooks `acrossai_abilities_api_init @ P10` → `push_definition()` — emits one synthetic display row per subclass-declared ability, all sharing the same `category`/`category_label`/`tab_group` and `card_variant='integration'`.
- Both callbacks call `is_plugin_active()` FIRST and early-return on false. Result: deactivated target plugin never renders a card, tab, or filter attachment.

**Storage**
- Toggle state lives in the existing `acrossai_library_config` option (site-wide via `get_site_option`/`update_site_option` — network-wide on multisite; per-site `is_plugin_active()` gates visibility).
- Sparse storage in `AcrossAI_Ability_Library_Config::save_config()` uses per-category defaults: regular categories strip `enabled=true`, integration categories strip `enabled=false` (matching their inverted default). Registry publishes the integration slug list via `get_integration_slugs()`.

**JS render**
- `card_variant='integration'` flows from the definition row through the Registry (sanitised at boundary via `sanitize_key_field`, mirrors Feature 037's `tab_group` pattern) into `LibraryCard`.
- LibraryCard suppresses the All/Specific radio and defaults `enabled` to `false` when the entry is missing.

**Synthetic-row lifecycle** (subtle but critical)
- Synthetic rows carry the integration slug as their `category` (e.g. `'acf'`). That slug is intentionally NEVER registered as a WP ability category on `wp_abilities_api_categories_init`, so WP core silently rejects them at `wp_register_ability()` — see `BUG-WP-CORE-ABILITY-CATEGORY-PRE-REGISTRATION`. This is DESIRED — the rows exist solely to drive the Library UI card (Registry-driven).
- Their `execute_callback` returns `WP_Error` and `permission_callback` returns `false` as defence-in-depth if that WP-core rule ever changes.
- Do NOT "fix" the WP-core rejection by pre-registering the integration slug as a category — that would leak N fail-closed abilities per integration into `wp_get_abilities()`.

**Reference implementation & tests**
- `includes/Abilities/Integrations/ACF.php` (Feature 060 first concrete subclass).
- `includes/Modules/Library/Integrations/AcrossAI_Integration_Ability_Base.php` (base class with full docblock).
- `tests/phpunit/Modules/Library/Integrations/Test_Integration_Ability_Base.php` — 20 tests covering all 5 user stories.
- `specs/060-library-third-party-integration-toggles/` — spec, plan, tasks, security-review-plan, memory-synthesis, quickstart.

**Tags**: library, integration, tab_group, card_variant, is_plugin_active, feature-060

---

### 2026-07-27 — Third-party plugin extending an integration's tab (PATTERN-LIBRARY-INTEGRATION-TAB-EXTENSION)

**Scope**: Library / Extensibility

**Pattern**
Any WordPress plugin (separate from `acrossai-abilities-manager`) can add REGULAR ability cards to an integration's tab (e.g. add three custom ACF-related cards next to the ACF integration toggle card). The pattern requires THREE things, all of them:

1. **Register the ability CATEGORY** on `wp_abilities_api_categories_init` via `wp_register_ability_category( $slug, [ 'label' => ..., 'description' => ... ] )`. Skipping this = silent rejection by WP core — see `BUG-WP-CORE-ABILITY-CATEGORY-PRE-REGISTRATION`. The Library UI card still renders (Registry-driven), but the underlying ability never enters `wp_get_abilities()` — so it's absent from the Custom Abilities table and MCP `discover-abilities`.
2. **Extend** `AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition` and implement `ability()` returning a full ability spec.
3. **Set** `meta.acrossai.tab_group` on `args.meta.acrossai` to the integration's published `TAB_GROUP` constant (e.g. `ACF::TAB_GROUP`), NOT a hardcoded string.

**Category slug guidance**
Use a DISTINCT category slug per add-on plugin (e.g. `'my-plugin-acf'`). Reusing the integration's own slug (`'acf'`) would (a) fail WP core's category check on the add-on side (integration slugs are never pre-registered — see synthetic-row lifecycle in `PATTERN-LIBRARY-INTEGRATION-BASE`) and (b) attempt to merge abilities into the integration's read-only card — neither is intended.

**Rendering**
Add-on cards render as REGULAR library cards: toggle + expand chevron + All/Specific radio + per-ability checkboxes. Styled identically to the "Core" cards on other tabs. The integration's own toggle card (`card_variant='integration'`) sits alongside them on the same tab.

**Worked example**
See `wp-content/mu-plugins/060-acf-tab-extension-demo.php` (Feature 060 quickstart Step 7) — registers two demo categories on `wp_abilities_api_categories_init` and instantiates three anonymous `Ability_Definition` subclasses targeting `ACF::TAB_GROUP`. Result: 3 extra cards on the ACF tab, 3 extra rows in the Custom Abilities table (12 total instead of 9).

**Test coverage**
`test_third_party_ability_definition_can_target_integration_tab` in `tests/phpunit/Modules/Library/Integrations/Test_Integration_Ability_Base.php` asserts that chaining an integration's `push_definition()` with a third-party `Ability_Definition`'s `push_definition()` produces two rows on the same tab.

**Tags**: library, extension, tab_group, TAB_GROUP, ability-definition, third-party, feature-060

---

### 2026-07-27 — Filterable capability that can only raise, never lower (PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY)

**Scope**: Extension points / Authorization

**Pattern**
When exposing a filter that lets a site override a required WordPress capability for a mutation endpoint (e.g. `apply_filters( 'my_plugin_x_capability', 'manage_options', $context )`), enforce the returned cap via a SINGLE `current_user_can( $filtered_cap )` check — NEVER as `current_user_can( $filtered_cap ) || current_user_can( $default_cap )`.

The single-check shape is inherently one-way (raise-only) because:
- If a site returns a STRICTER cap (e.g. `manage_network_options`), the user must hold that cap → access is tightened.
- If a bad filter returns garbage or an empty string, `current_user_can( '' )` returns false → fail-closed.
- The filter CANNOT lower the effective requirement below what WordPress's own cap model enforces — a returned "weaker" cap string still requires the user to actually hold it.

Companion pieces:
- Layer the pre-existing (unconditional) cap floor BEFORE the filter check as belt-and-suspenders. Feature 060 keeps the REST controller's `manage_options` floor from `check_permission` AND adds the filtered check on the mutation handler on top — both must pass.
- Pass POST-SANITISATION context to the filter (Feature 060 SEC-005): sanitise the discriminator (e.g. the integration slug) BEFORE `apply_filters(...)` so hook implementers receive canonical values, not raw request data.
- Fire an accompanying `_denied` action (e.g. `do_action( 'acrossai_integration_toggle_denied', $context, $required_cap, $user_id )`) so sites can wire audit logging without amending core code.

**Anti-pattern to avoid**
```php
// WRONG — allows the filter to LOWER the effective cap by returning a weaker one.
if ( current_user_can( $filtered_cap ) || current_user_can( $default_cap ) ) {
    // proceed
}
```
The OR breaks the raise-only property: a site returning `read` as the filter value would grant access to any logged-in user (because the second clause still holds for admins). Always use the single check.

**Reference**
Feature 060 `AcrossAI_Ability_Library_Config_Controller::save_config()` implements this for the `acrossai_integration_toggle_capability` filter (FR-016). Manual verification via quickstart Step 6 — a mu-plugin raises the required cap to `manage_network_options` and a `manage_options`-only user gets HTTP 403.

**Tags**: capability, filter, extension-point, authorization, raise-only, current_user_can, feature-060

---

### 2026-08-14 — Ability Integrations page auto-derives tabs from distinct `tab_group` values (PATTERN-ABILITY-LIBRARY-TAB-AUTO-DERIVE)

**Scope**: Ability Library / Ability Integrations admin page, JS-side tab rendering

**Pattern**
The admin page's tab strip is auto-derived at render time from the set of unique `meta.acrossai.tab_group` strings across all registered abilities. `collectTabGroups()` (`src/js/ability-library/components/LibraryPage.js`) walks every ability, extracts the `tab_group` field, dedupes, sorts, and pins `'core'` first. `titleCaseTabLabel(value)` converts the raw identifier to a display label via `ucwords(str_replace('-', ' ', value))` — so `elementor` → "Elementor", `site-health` → "Site Health", `database` → "Database", etc.

There is **no PHP-side `register_tab()` call**, no admin-registered whitelist, and no server-side validation of tab_group values. The tab strip is a pure JS derivation from ability data.

**Consequence — silent misplacement**
Setting the wrong `tab_group` value on a new ability is silently accepted — the ability just lands in whichever bucket its string names. Copy-paste inheritance of `'core'` from an unrelated template misplaces the ability into the Core tab with no warning, no error, no test failure. This is exactly what happened to 88 Elementor abilities before PR #128: every one of them declared `'tab_group' => 'core'` (inherited from a Core-tab template used as scaffolding) and the entire Elementor suite silently shipped under the Core tab for weeks. The fix was a mechanical `sed -i "s/'tab_group' *=> *'core'/'tab_group' => 'elementor'/g"` across 63 files — no other change needed to make an "Elementor" tab appear.

**When you add a new ability**
1. Decide which existing tab it belongs to — Core, Database, Elementor, Site Health, etc. Grep for `'tab_group' *=>` across the closest sibling abilities to check.
2. Set `meta.acrossai.tab_group` to the matching kebab-case string.
3. If the ability genuinely belongs to a new integration bucket, no separate tab registration is needed — the tab appears automatically the moment the first ability declares the new `tab_group` string. Its display label comes from `titleCaseTabLabel()`; if the auto-derived label is wrong (e.g. `SiteHealth` becoming "Sitehealth"), use kebab-case (`site-health`) so `ucwords` produces the desired output.
4. If many similar abilities share a base class (e.g. `Base_Audit_Ability` driving 25 audit subclasses via inheritance), remember to change the base — changing subclasses alone misses inherited declarations.

**Cross-references**
- Distinct from `PATTERN-LIBRARY-INTEGRATION-TAB-EXTENSION` — that pattern covers third-party plugins REGISTERING a new tab via `<Integration>::TAB_GROUP` const with the 3-step contract. This pattern is about correctly landing FIRST-party abilities in the RIGHT existing tab.
- Related: `DEC-META-ACROSSAI-NAMESPACE` — establishes `meta.acrossai.tab_group` as the canonical field. This pattern documents the runtime consequence of that decision.

**Reference**
- `src/js/ability-library/components/LibraryPage.js::collectTabGroups()` and `titleCaseTabLabel()` — the auto-derivation logic.
- PR #128 (2026-08-14) — flipped 63 Elementor ability files from `'core'` to `'elementor'`; sole change needed to produce a working "Elementor" tab.

**Tags**: ability-library, ability-integrations, tab-derivation, meta-acrossai, tab_group, silent-misplacement, first-party, jsx-runtime-derivation

---

### 2026-08-24 — PATTERN-ABILITY-BASE-OPTIONAL-TEMPLATE-AUTO-INJECT — extend Ability_Definition with an optional template method + guarded auto-inject to meta

**Status**: Active
**Scope**: Ability_Definition / Framework extension pattern
**Tags**: template-method, base-class, backwards-compatible, auto-inject, feature-088, framework-extension

**Pattern**: When adding a new optional per-ability metadata field to the plugin's ability surface, follow this shape:

1. Add a `protected function xxx(): array` (or scalar) template method to `Ability_Definition` that defaults to an empty value.
2. Inside the existing `push_definition()` callback, call `$this->xxx()` and merge the non-empty result into `$args['meta']['acrossai']['xxx']` behind a `! empty()` guard.
3. Do NOT inject when the method returns empty — abilities that don't override see zero payload change.
4. Runtime enrichment or gating of the field lives in `AcrossAI_Ability_Library_Registry::get_definitions()` (single-point decoration for all consumers), not in per-subclass code.
5. If admin-facing kill-switching is needed, expose it as a WP option consumed at the same Registry decoration point (single-point gate for REST + MCP + Library UI).

**Why this is durable**: 100% backwards-compatible way to extend the framework surface without touching 500+ subclasses. Alternatives (traits mixed into every subclass, decorators, new base classes, filter-only extension points) either require subclass edits or add unnecessary indirection. The pattern was proven by Feature 088's suggested_plugins framework which added a new field to every ability's payload contract while the full regression suite (1658 tests / 5344 assertions) passed on the first run.

**How to apply**: reach for this pattern whenever a new field should be available to every ability but only meaningful for a subset (e.g. future badges, health flags, deprecation notices, related-abilities links, per-ability required-capability hints). If the field is REQUIRED for every ability, use the abstract `ability()` return array instead. This pattern is for OPTIONAL cross-cutting metadata.

**References**:
- `includes/Modules/Library/Ability_Definition.php::suggested_plugins()` — canonical example (Feature 088)
- `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php::apply_suggested_plugins_decoration()` — Registry-side decoration example
- `docs/memory/DECISIONS.md#DEC-ABILITY-SUGGESTED-PLUGINS-CONTRACT` — the field this pattern first delivered
- `PATTERN-META-ACROSSAI-NAMESPACE` (Feature 041) — the namespace the pattern writes into
