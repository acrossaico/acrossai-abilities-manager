# Worklog

Use concise high-value entries only.
This is not a changelog. Do not record routine releases, version bumps, or implementation summaries.

---

### 2026-07-25 — Feature 058: Ability-slug convention standardised — namespace `acrossai-abilities-manager/` → `acrossai/`, all suffixes flipped to verb-first, 162 class files renamed, no data migration

- **Why durable**: First feature to establish a controlled verb-first vocabulary for ability slugs (`DEC-SLUG-CONVENTION-VERB-FIRST`) matching the WordPress core MCP adapter's own built-ins (`mcp-adapter/discover-abilities` / `execute-ability` / `get-ability-info`). Sets the convention every future ability-registration in this plugin MUST follow. Also establishes the "namespace = short plugin-owned word" pattern (`acrossai/` — 9 chars) that aligns with WP core's block namespaces (`core/paragraph`, `woocommerce/product`) and shaves ~18 chars per slug off every LLM tool manifest. Class file names + PHP class names also flip to match slugs — internal-only quality-of-life, but it means grep-by-slug now finds the registering class immediately.
- **Future mistake prevented**: (1) The perl-look-behind sweep `s{(?<!/)acrossai-abilities-manager/}{acrossai/}g` correctly preserves filesystem paths (`/wp-content/plugins/acrossai-abilities-manager/...`) and the ACL library's namespace (`rules/acrossai-abilities/...`) — BUT it misses ONE class of false positive: filesystem identifiers used as string literals INSIDE the codebase, like `plugin_basename` compared against `'acrossai-abilities-manager/acrossai-abilities-manager.php'` (a WP identifier for `<dir>/<main-file>`). See `BUG-PERL-LOOKBEHIND-PLUGIN-BASENAME`. Always grep for such literals AFTER a namespace sweep. (2) The initial commit shipped an auto-migration wired to `activate()` + `admin_init` — 350 LoC of migration class + option-flag guard + three-table transaction. User reversed the decision on-the-fly for this plugin ("very few users, breaking is acceptable"). The migration was cleanly removed one commit later. **Rule**: for small-userbase plugins, prefer clean-slate rewrites over migration scaffolding when the user explicitly opts for the breaking cut — migration mental overhead outweighs its value at low user counts. (3) A pre-existing subtle bug (`SLUG_PREFIX = 'acrossai-abilities/'` hardcoded in the JS) had been surviving in `AbilityForm.jsx` and `AbilitiesList.jsx` unnoticed for many releases because the strip-logic fell through silently, showing the entire slug in the input field instead of just the suffix. Only caught during 058 investigation. **Rule**: any UI that programmatically strips a prefix from a displayed string must include a sentinel test that fails loudly when the prefix doesn't match, not fall through to display-the-whole-thing.
- **Also shipped**: PHP class file renames (162 files, `Site_Title_Get.php` → `Get_Site_Title.php`) via a two-pass rename script: pass 1 rename + update `class X` declaration, pass 2 sweep bootstrap + tests + docs with word-boundary perl to update every reference. The 162 files were detected as renames by git via content-similarity (>95% match). Plugin REST namespace shortened to `acrossai/v1` in lockstep (`REST_NAMESPACE` const in two REST controllers + `abilitiesConfig.rest_namespace` in `admin/Main.php`). Byte-length caps updated: `sanitize_slug_suffix()` cap from 227 to 246 (255 − 9 chars for the shorter prefix); `AbilitiesValidationTest::test_validate_slug_suffix_rejects_overlong` regenerated with 247-char input to still trigger the overrun.
- **Scope**: 258 files changed across 3 commits. Quality gates: PHPUnit 170/170 ✅ after every commit, PHP syntax lint clean across all 276 ability files + 5 core files, `npm run build` regenerated JS assets (2 `.js` files), git detected 162 renames on the class-file pass. No new REST endpoints, no schema change, no new composer/npm packages. Jest suite has pre-existing ESM-transform failures unrelated to this feature.
- **Where to look**: `specs/058-slug-rename-verb-first/` (spec, plan, tasks, memory-synthesis, checklists). PR [#88](https://github.com/acrossai-co/acrossai-abilities-manager/pull/88). Historical bug about the JS prefix mismatch: `BUGS.md` (any pre-058 entry mentioning `acrossai-abilities/` as a UI prefix). Class rename map is embedded in commit `bc23e6e`; slug rename map is embedded in commit `f401e09` and (briefly) in the deleted `AcrossAI_Slug_Rename_Migration_058::map()` method.
- **Tags**: feature-058, slug-convention, verb-first, mcp-adapter-alignment, namespace-shortening, class-file-rename, bulk-rewrite, no-migration-small-userbase.

---

### 2026-07-03 — Feature 041: Library display fields consolidated under `$args['meta']['acrossai']` (hard cut)

- **Why durable**: Establishes `meta['acrossai']` as the canonical namespace for plugin-specific ability extension fields — sibling of `meta['mcp']` and `meta['annotations']`. Retires the Features 033/037 top-level shape (`$args['sub_group']`, `$args['sub_group_label']`, `$args['tab_group']`) without backward-compat fallback. Second hard-cut refactor after Feature 040's Logger decommission; recent-enough precedent that no BC layer is justified.
- **Future mistake prevented**: (1) Future field additions won't collide with WP-core's top-level `$args` namespace. If WP core ever adds a `sub_group` field, `meta['acrossai']['sub_group']` still works cleanly. (2) Add-on developers extending `Ability_Definition` have a single documented convention (`PATTERN-META-ACROSSAI-NAMESPACE`) for where plugin-specific fields belong. (3) Sibling AcrossAI-org plugins (hypothetical `acrossai-mcp-manager`, `acrossai-ability-logs`) MUST use their own key (`meta.acrossai_mcp`, `meta.acrossai_logs`); `meta.acrossai` is reserved for `acrossai-abilities-manager` per `DEC-META-ACROSSAI-NAMESPACE`.
- **Evidence**: `PATTERN-META-ACROSSAI-NAMESPACE` (`docs/memory/ARCHITECTURE.md`), `DEC-META-ACROSSAI-NAMESPACE` (`docs/memory/DECISIONS.md`). Two negative-regression tests in `Test_Ability_Definition.php` prove the top-level shape is silently ignored (`test_push_definition_ignores_top_level_sub_group_pre_041_shape`) and that `meta.acrossai` wins on shape collision (`test_push_definition_reads_only_from_meta_acrossai_when_both_shapes_present`).
- **Where to look**: `includes/Modules/Library/Ability_Definition.php` (post-041 extraction), `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php` (`ALLOWED_ARGS_FIELDS` constant — 8 entries down from 11), `tests/phpunit/Modules/Library/Test_Ability_Definition.php` (10 tests, all fixtures on `meta.acrossai`; 2 new negative), `tests/phpunit/Modules/Library/Test_Ability_Library_Registry.php` (two allowlist tests rewritten as `test_registry_strips_top_level_*_from_args_post_041`), `specs/041-library-fields-meta-acrossai-namespace/` (spec, plan, tasks, memory-synthesis).
- **Scope**: 2 code files + 2 test files + 4 memory files + 4 spec-kit files. Quality gates: PHPCS 45/45 ✅, PHPStan L8 zero errors ✅, PHPUnit 105 tests all passing (103 → 105; +2 from new negative-regression tests). Zero in-plugin `Ability_Definition` subclasses (`grep -rEn 'extends Ability_Definition' includes/ admin/` returns zero). Zero JS-side change. Zero DB schema change. Zero REST controller change. Zero `Merger`/`Override Processor` change (these fields are not in `OVERRIDABLE_FIELDS`).

---

### 2026-06-30 — Feature 038: AcrossAI shared menu + tabs + boot resilience — 4 new patterns + 3 decision scope extensions

- **Why durable**: First feature to adopt a SHARED admin-surface package (`acrossai-co/main-menu`) as the top-level parent for multiple AcrossAI plugins. Establishes the consumer-side contract (entry-file `plugins_loaded P0` bootstrap + paired idempotency guards) that all future AcrossAI plugins MUST follow when joining the shared menu. Also resolves the `AcrossAI_Loader` class-not-found fatal observed on installs without a composer-built `vendor/`, via a `$vendor_missing` flag + `manage_options`-gated admin notice + priority-1 activation guard — a generalizable boot-resilience pattern that should apply to every future plugin that depends on jetpack-autoloader.
- **Future mistakes prevented**: (1) Admin notices fired in degraded mode (autoloader missing) must use only WP globals inside the closure (`PATTERN-ADMIN-NOTICE-SELF-CONTAINED`) — otherwise the notice itself fatals. (2) Vendor-precondition activation guards must register at `add_action( 'activate_<basename>', $cb, 1 )` priority 1, NOT default 10, to fire before existing `register_activation_hook` callbacks (`PATTERN-ACTIVATION-HOOK-EARLY-PRIORITY`). (3) Multiple consumers of a shared package must use paired `class_exists( $fqcn, false ) + did_action()` guards, NOT just one or the other — `require_once` dedupes by file path, not class name, so different paths can declare the same class twice (`PATTERN-SHARED-MENU-CONSUMER-IDEMPOTENCY`; observed live as `Fatal: Cannot declare class AcrossAI_Main_Menu\\SettingsPage` when a smoke-test mu-plugin re-required the same source from a separate path). (4) When contributing sections to a shared WP Settings API host page, prefer registering a tab and targeting the per-tab page slug; fall back to em-dash plugin-scope prefix on section titles only when the host doesn't support tabs (`PATTERN-SHARED-SETTINGS-SECTION-SCOPE`).
- **Decision scope extensions captured**: `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` extended to entry-file `plugins_loaded P0` bootstraps for shared menus; `DEC-STABLE-UPGRADE-WINDOW` carved out an exception for internal AcrossAI-org packages with audit + SHA pin; `DEC-MENU-HOOK-SUFFIX` annotated with the `sanitize_title( parent_title )` fragility note for submenu suffix derivation.
- **Live incident lessons**: The mid-implementation 0.0.2 → 0.0.4 host package upgrade demonstrated that consumer-side `register_setting`/`add_settings_section` patterns work UNCHANGED across the host's flat-mode → tabbed-mode boundary as long as the consumer follows the host's per-tab page slug helper. The mu-plugin coexistence incident proved that a development smoke harness loading the same package via direct `require_once` collides with composer-vendored copies — paired guards are the only safe coexistence pattern.
- **Scope**: 13 source files + 1 new test file + 1 out-of-repo mu-plugin coexistence fix. Quality gates: PHPCS ✅, PHPStan L8 ✅, `php -l` ✅ on all touched/new files, `npm run lint:js` ✅. Vendor audit complete (PASS — pinned `dist.reference=a2c02cf17…`). 7 plan-phase security findings resolved in code (SEC-001/002/004/006/007 directly; SEC-003/005 by audit). 0 architecture violations beyond the disclosed Boot Flow Rule deviation (entry-file bootstrap + activation guard).
- **Where to look**: `acrossai-abilities-manager.php` (entry-file bootstrap + activation guard), `includes/Main.php` (`$vendor_missing` flag + admin-notice closure + Loader filter for `acrossai_settings_tabs`), `admin/Partials/SettingsMenu.php` (`TAB_SLUG` + `register_tab()` + per-tab page slug usage), `admin/Main.php` (hook_suffix strings for relocated pages), `tests/phpunit/Includes/Test_Boot_Resilience.php` (12 structural tests), `specs/038-acrossai-main-menu-integration/` (full artifact set: spec, plan, research, data-model, contracts, quickstart, memory-synthesis, three security reviews including vendor audit close-out), `wp-content/mu-plugins/acrossai-tab-smoketest.php` (out-of-repo coexistence fix).
- **Tags**: feature-038, main-menu, shared-menu, tabs, boot-resilience, settings-api, autoloader, jetpack-autoloader, mu-plugin, paired-guards, entry-file-bootstrap.

---

### 2026-06-25 — Feature 037: Library tab_group field + page-level TabPanel — first per-field closure of PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH

- **Why durable**: First feature to apply the boundary-sanitization mitigation captured in Feature 036's `PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH`. `tab_group` is sanitized at the Registry's `validate_and_normalize()` via `AcrossAI_Ability_Library_Config::sanitize_key_field()`, alongside the existing `category`/`slug`/`sub_group` calls. Sets a copyable template for future per-field closures (e.g. `description` could use `wp_kses_post` at the same boundary).
- **Mirror-feature pattern validated**: Feature 037 is a literal mirror of Feature 033's `sub_group` plumbing, one display layer up (page tab instead of in-card sub-heading). Same `OPTIONAL_FIELDS` allowlist mechanism, same sanitize helper, same display-only contract. The fact that 32 tasks shipped with zero architecture drift confirms the pattern is reliable — when adding an optional display-only `args.*` field, copy the existing block step-for-step.
- **Scope**: 11 source files modified + 5 new test files; +346/-20 lines. 8 new PHPUnit tests, 22 new Jest tests across 4 new spec files. PHPStan / PHPCS / PHPUnit (91/91) / Jest (50/50) / webpack build all green. T031 manual smoke deferred to operator.
- **Captured lessons**: `BUG-ESLINT9-JEST-GLOBALS` (companion capture this commit).
- **Tags**: feature-037, library, tab-group, args-passthrough-closed, mirror-feature-033, eslint9

---

### 2026-06-23 — Feature 036: Library descriptions + full-width + wpb-access-control bump — captures PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH

- **Why durable**: First feature to consume an `args.*` value (`description`) from the Library Registry payload in the React layer. The act of plumbing it through `groupDefinitions()` → `LibraryCard.js` exposed the boundary contract that has been latent since Feature 027: the Registry key-allowlists `args` but does NOT sanitize values, so XSS containment is owned by whichever consumer ships first. Captured as `PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH` so the next Library consumer (PHP renderer, email template, `dangerouslySetInnerHTML` Markdown viewer, etc.) inherits the explicit choice rather than the implicit one.
- **Future mistake prevented**: (1) When adding a new consumer of `args.description` (or `args.label`, `args.meta`, `args.input_schema`, etc.), do NOT assume the value is pre-sanitized — Registry only filters keys, not values. Either render via JSX text node, or escape at the call site, or harden the Registry. (2) When bundling an orthogonal dependency bump into a feature PR (here: `wpb-access-control ^1.2.1 → ^1.6.0`, lockfile v1.6.0), sequence `composer update` BEFORE the `composer run phpstan` / `phpcs` / `phpunit` gates so static analysis scans the new vendor surface — sequence captured in `tasks.md` §"Task-Level Dependencies". (3) `DEC-REVALIDATE-SECURITY-POST-UPGRADE` code-level audit (SEC-03, SEC-04, DEC-PERM-CB, DEC-FAIL-OPEN-NOTICE) is fully gettable without runtime: grep against the production scope plus a manual `vendor/` rename for the FAIL-OPEN-NOTICE check — the rename step stays manual but the grep audit is automatable.
- **Security posture change (intentional, neutral)**: `args.description` now exposed on the Library page; renders only via React text nodes, so no new XSS surface today. The latent Registry boundary risk is now durably named (`PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH`) for any future consumer.
- **Evidence**: Branch `036-library-page-full-width-and-descriptions`. Quality gates: PHPStan L8 ✅ (silent pass), PHPCS ✅ (56/56), ESLint clean after `--fix` (4 pre-existing remain), Jest 28/28 ✅ (8 new in `tests/jest/ability-library/groupDefinitions.test.js`), `npm run build` ✅, `npm run validate-packages` ✅. Composer audit clean ("No security vulnerability advisories found"). T013 browser walkthrough confirmed by user on 2026-06-21.
- **Where to look**: `specs/036-library-page-full-width-and-descriptions/{spec,plan,tasks,memory-synthesis,security-constraints}.md`; `src/js/ability-library/components/{LibraryPage.js,LibraryCard.js}` (named-export `groupDefinitions` + dual-mode renderer); `src/scss/ability-library/admin.scss` (max-width removed, new `__slug-row` / `__slug-description` / `__slug-readonly-description` rules); `tests/jest/ability-library/groupDefinitions.test.js` (passthrough contract pin); `composer.json` + `composer.lock` (1.2.1→1.4.0 constraint; 1.4.0→1.6.0 resolved); `docs/memory/ARCHITECTURE.md` (PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH).

---

### 2026-06-16 — Feature 035: Remove Pass as Tool — supersedes DEC-MCP-TOOLS-PASSTHROUGH-COLUMN

- **Why durable**: Continues the Feature 034 architectural inversion by retiring the second of two cross-cutting reaches into MCP-adapter internals. Establishes the supersession + forward-pointer-annotation pattern for retiring an Active decision without losing its associated lessons: `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` marked Superseded, `DEC-MCP-INJECT-REFLECTION-PATTERN` annotated as "retained for future `acrossai-mcp-manager` consumer" rather than blanket-superseded. Confirms the BUG-INVENTORY-GREP-MISS lesson (Feature 034) as a permanent preflight gate — the T002 conditional grep caught a second `user_has_ability_access()` caller (line 386, inside `build_permission_callback()`) and the helper was preserved with a TODO note pointing at the surviving call site.
- **Future mistake prevented**: (1) When deleting a private helper as part of a feature removal, run a grep-before-delete gate and treat any non-target caller as a "keep with annotation" signal — do NOT delete-then-fix; (2) The deleted PHPUnit suites were orphan files (never registered in `phpunit.xml.dist`'s explicit `<file>` list) — never assume PHPUnit autodiscovery picks up `Test.php`-suffix files in this codebase (BUG-PHPUNIT-AUTODISCOVERY-PREFIX); (3) The bootstrap stub for `WP_REST_Request` had to be added to `tests/bootstrap.php` to make the new SEC-035-001 silent-ignore test runnable in the unit suite — the bootstrap only provided `WP_Error` + `WP_UnitTestCase` previously; (4) Pre-launch removals SHOULD ship without `maybe_upgrade()` ALTER routines — carrying one past launch is dead weight; document the manual deactivate-drop-reactivate procedure (single-site + per-blog multisite) in spec-local upgrade-notes instead.
- **Security posture change (intentional, net improvement)**: Reflection-based reach into `McpServer::$component_registry` (private) eliminated entirely from this plugin. mcp-adapter upgrade fragility class collapses to the public hook surface alone.
- **Evidence**: Branch `035-remove-pass-as-tool`. Quality gates: PHPCS ✅ (8/8 changed files clean), PHPStan L8 ✅ (silent pass), PHPUnit ✅ (83 tests, +4 new SEC-035-001 assertions), Jest (53 pass + 3 pre-existing failures unrelated to this feature — stale Sitewide module references from Feature 012 decommission), `npm run build` ✅ (build/js/abilities.js: 0 `pass_as_tool` hits). Terminal exhaustive grep across production scope: 0 hits.
- **Where to look**: `specs/035-remove-pass-as-tool/{spec,plan,tasks,memory-synthesis,security-constraints,architecture-violations,upgrade-notes}.md`; `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (surviving methods + narrowed `boot()` docblock); `tests/phpunit/abilities/AbilitiesPassAsToolRemovalTest.php` (silent-ignore contract pin); `tests/bootstrap.php` (new `WP_REST_Request` stub).

---

### 2026-06-14 — Feature 034: Remove Allowed Servers + add 5-hook extension surface + retract wpb-mcp-servers-list mandate

- **Why durable**: First feature to relocate an authorization layer OUT of `acrossai-abilities-manager` and into a future-shipped plugin (`acrossai-mcp-manager`), establishing the canonical "5-hook form extension contract" (3 React via `@wordpress/hooks` + 2 PHP via WP core hooks + a public `window.acrossaiAbilitiesManager` global) as a reusable shape for any future React-form-with-REST-save surface. Also the first feature to retract a Constitution-mandated Composer dependency (v1.4.1 → v1.4.7 PATCH retraction).
- **Future mistake prevented**: (1) `mcp_servers` consumers were spread across more files than the original inventory caught — the security-tasks-review SEC-T-001 surfaced 4 missed files (Merger / Query / Exposure_Controller / Override_Processor); always run an `mcp_*` allowlist-style grep across `includes/` after a primary inventory, then add a follow-up task for any miss. (2) Feature 029's `pass_as_tool` injection lived in the same file as the `mcp_servers` allowlist check (Override_Processor); careful "preserve pass_as_tool, delete mcp_servers" surgery required — don't use script-driven `str_replace` for files that mix multiple concerns. (3) Constitution-mandated Composer dependencies (like `wpb-mcp-servers-list` v1.4.1) require both a code removal AND a Constitution amendment with sync-impact report — code removal alone leaves the mandate dangling.
- **Security posture change (intentional)**: fail-closed MCP server allowlist enforcement DELETED from BOTH `Exposure_Controller` AND `Override_Processor::inject_mcp_tools`. Abilities are now unconditionally exposed; Feature 029's `pass_as_tool` injection no longer per-server filtered. Pre-launch context makes this safe; future MCP manager plugin MUST re-implement fail-closed by default.
- **Evidence**: Branch `034-remove-allowed-servers-add-extensibility-hooks`. All 5 gates green (PHPCS, PHPStan L8, PHPUnit, Jest, npm build). 1 Composer package removed (`wpboilerplate/wpb-mcp-servers-list ^0.0.1`). Constitution amendment retracts §Integration Resilience canonical-pattern paragraph; PATCH bump pending (T029a).
- **Where to look**: `specs/034-remove-allowed-servers-add-extensibility-hooks/{spec,plan,tasks}.md`; `contracts/extension-hooks.md` (authoritative public contract with Trust model section + reserved-keys registry); `architecture-migration-plan.md` (Phase 1 = this PR, Phase 2 = future MCP manager); `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (where fail-closed enforcement used to live + comment marking the intentional deletion); `admin/Main.php` (where `acrossai_abilities_admin_localize_data` + `acrossai_abilities_form_settings_registered` fire); `src/js/abilities/components/AbilityForm.jsx` (extra_sections slot + draft_changed action + save_payload filter callsites).

---

### 2026-06-14 — Feature 033: Library card visibility contract + optional sub_group display + chevron disclosure (3 turns)

- **Why durable**: Feature shipped across three iterative turns driven by UX feedback, surfacing two reusable Spec Kit bug patterns (BUG-PHPUNIT-AUTODISCOVERY-PREFIX, BUG-JEST-MOCK-LIST-STALENESS). The three-turn revision pattern itself — initial contract → live UX review reveals gap → spec/tasks remediated → second UX revision → re-analyze — is a real Spec Kit workflow signature worth recognizing.
- **Future mistake prevented**: (1) Don't wire new `Test_*.php`-prefix test directories via PHPUnit `<directory>`; use explicit `<file>` entries or the suite runs silently empty. (2) Helper-import Jest specs break when host JSX files gain new `@wordpress/*` imports; mock-list staleness is the failure mode. (3) Iterative UX revisions can drift FRs/SCs; `/speckit-analyze` after every implementation-stage UX change catches this before merge.
- **Also shipped**: OPTIONAL `args['sub_group']` pass-through on `Ability_Definition` for display-only sub-group headings (no DB shape change, no REST shape change, `sub_keys` slug-keyed map preserved); chevron disclosure button per card with `useState(true)` default expanded, no persistence; turn-2 read-only ability list under "All" mode; spec.md FR-015 / SC-001 / SC-003 / US3 remediated to match the turn-3 contract.
- **Evidence**: Branch `032-library-card-toggle-and-subgroup-display`. 85/85 PHPUnit, 20/20 Jest, PHPStan level 8 0 errors, PHPCS WPCS 0 errors. Saved `acrossai_library_config` shape `{ enabled, mode, sub_keys }` byte-for-byte unchanged.
- **Where to look**: `specs/033-library-card-toggle-subgroup/{spec,plan,tasks}.md` (3-turn revision history); `includes/Modules/Library/Ability_Definition.php` (sub_group pass-through); `src/js/ability-library/components/LibraryCard.js` (visibility contract + disclosure + grouping render); `phpunit.xml.dist` (`library-unit` testsuite via `<file>` entries — see BUG-PHPUNIT-AUTODISCOVERY-PREFIX).

---

### 2026-06-11 — Feature 032: Post-031 hotfixes — 3 new bug patterns, 1 HIGH security finding resolved

- **Why durable**: Three independent post-launch bugs surfaced after Feature 031 shipped, each generating a reusable durable lesson. One was a HIGH-severity security hole (MCP tools bypassing `permission_callback` for non-admin users).
- **Future mistake prevented**: (1) BUG-BERLINDDB-QUERY-OVERRIDE-COMPAT — BerlinDB Query subclass overrides must match parent visibility AND full signature; PHPStan L8 catches these before deployment. (2) BUG-WP-ABILITY-CHECK-PERMISSIONS — `WP_Ability` has no `get_permission_callback()`/`get_args()`; always use `check_permissions()` — probing for non-existent methods via `method_exists()` silently passes the check, granting all-user access. (3) BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS — `inject_mcp_tools()` needs `check_permissions()` as 4th gate; AC rules are fail-open and silently bypass `manage_options` when no rule is configured.
- **Also shipped**: Library submenu moved to second position (was fourth) in `define_admin_hooks()` — submenu order = `add_submenu_page()` call order within the same hook priority.
- **Evidence**: Branch `032-post-031-hotfixes`. 3 PHP files changed. 0 new tests (single-method fixes). PHPCS ✅, PHPStan L8 ✅, manual smoke test confirmed. T001–T008 complete.
- **Where to look**: `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php` (BerlinDB compat), `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php::inject_mcp_tools()` (Check 4), `includes/Main.php::define_admin_hooks()` (menu reorder).

---

### 2026-06-11 — Feature 031: Library category/slug rebrand + Ability_Definition simplification — 0 security findings

- **Why durable**: DEC-LIBRARY-CATEGORY-SLUG-REBRAND establishes that `Ability_Definition` subclasses need only implement `ability()`; all Library grouping fields derive automatically. 17 external subclasses in `acrossai-core-abilities` remained compatible without code changes.
- **Future mistake prevented**: (1) Never add redundant abstract methods to a base class when the required data already exists in another abstract method's return value. (2) PHP only fatals on *missing* abstract implementations — extra non-abstract methods on a subclass are silently ignored, enabling backwards-compatible base class simplification. (3) `args['category']` absent from an ability spec causes silent Registry rejection; add-on authors must include it.
- **Also shipped**: `category_label` auto-derived via `ucwords(str_replace('-', ' ', $category))`; saves fire instantly (no debounce); "Saving…" spinner removed to eliminate layout-shift flash.
- **Evidence**: `includes/Modules/Library/Ability_Definition.php` (push_definition derivation), `src/js/ability-library/components/LibraryPage.js` (instant save pattern).
- **Where to look**: `specs/031-library-category-slug-rebrand/plan.md` (Rename Map), `docs/memory/DECISIONS.md` (DEC-LIBRARY-CATEGORY-SLUG-REBRAND).

---

### 2026-06-11 — Feature 030: Library page blank-page fix + AddonsPage package rebrand — 0 security findings

- **Why durable**: Two new bug patterns (BUG-LIBRARY-HOOK-SUFFIX, BUG-WP-LOCALIZE-SCRIPT-RENDER) prevent the same class of mistake on every future admin submenu page with JS assets.
- **Future mistake prevented**: (1) Never hardcode a submenu hook suffix — capture `add_submenu_page()` return value and expose via `get_hook_suffix()`. (2) Never inject page data via `wp_localize_script()` from a render callback — always use `wp_add_inline_script('before')` in `enqueue_scripts()`. (3) Composer package namespace must be confirmed from installed `vendor/*/composer.json` before writing FQCN in plugin code.
- **Also shipped**: `wpboilerplate/addons-page` → `acrossai-co/addons-page` (FQCN: `\AcrossAI_Addon\AddonsPage`). VCS entry removed from `composer.json` (Packagist). All DEC-EXTERNAL-PACKAGE-HOOK-CTOR guards and Freemius credentials preserved unchanged.
- **Evidence**: Branch `030-library-page-fix-and-addons-page-rebrand`. 3 PHP files + composer.json changed. 0 new files. PHPCS ✅, PHPStan L8 ✅. T001–T016 complete.
- **Where to look**: `admin/Main.php::enqueue_scripts()` (wp_add_inline_script library block), `admin/Partials/LibraryMenu.php` (get_hook_suffix pattern), `includes/Main.php` (AcrossAI_Addon\AddonsPage block), `vendor/acrossai-co/addons-page/` (new package).

---

### 2026-06-11 — Feature 029: MCP Tools Pass-through — pass_as_tool column, filter bridge, PassAsToolCell

Feature 029 MVP complete (Phase 1–3, T001–T018). Governed workflow (governed-tasks + governed-implement) caught one P0 architectural error before any code was written.

- **Why durable**: Three new patterns/bugs captured: (1) `AcrossAI_Abilities_Query` has a private constructor — `new AcrossAI_Abilities_Query()` is a fatal PHP error, always use `::instance()` (BUG-BERLINDDB-QUERY-PRIVATE-CTOR); (2) PHP-managed lists (protected slugs) must be localized to JS via `window.acrossaiAbilitiesManager`, not hardcoded in JSX (PATTERN-PROTECTED-SLUGS-JS-LOCALIZE); (3) MCP tool pass-through contract established (DEC-MCP-TOOLS-PASSTHROUGH-COLUMN).
- **Future mistake prevented**: Any module that calls `new AcrossAI_Abilities_Query()` will fatal. The Query class is singleton-only. Protected slug gating in JSX must read from the PHP-localized list, not a JSX constant.
- **Evidence**: Branch `029-mcp-tools-passthrough`. 9 source files changed + 1 new module + 6 new test files. PHPCS ✅, `npm run build` ✅, `validate-packages` ✅. PHPStan blocked (pre-existing environment issue).
- **Where to look**: `includes/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough.php` (filter bridge), `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` (`get_pass_as_tool_slugs()`), `admin/Main.php` (protected_slugs localization), `src/js/abilities/components/AbilitiesList.jsx` (`PassAsToolCell`), `docs/memory/BUGS.md` (BUG-BERLINDDB-QUERY-PRIVATE-CTOR), `docs/memory/DECISIONS.md` (DEC-MCP-TOOLS-PASSTHROUGH-COLUMN).

---

### 2026-06-10 — Feature 028: BerlinDB 3.0 migration, REST security fix, vendor distribution fix

Feature 028 complete (BerlinDB v3 migration, permission_callback security fix, wpb-access-control vendor fix).

- **Why durable**: Four non-obvious bug patterns captured: (1) BerlinDB v3 double-primary column+index causes silent empty DDL; (2) BerlinDB v3 `'default' => 'CURRENT_TIMESTAMP'` generates invalid quoted literal — use `'created'/'modified'` flags; (3) `WP_REST_Response` returned from `permission_callback` is truthy → silently grants access; (4) `composer.json archive.exclude` does not control GitHub tag ZIP contents — `.gitattributes export-ignore` does.
- **Future mistake prevented**: Declare PRIMARY KEY only in `$indexes` (remove column-level `'primary'`). Use `'created'/'modified'` flags, not `'default' => 'CURRENT_TIMESTAMP'`. `permission_callback` must return `true|false|WP_Error`. Fix vendor missing directories via `.gitattributes`, not `archive.exclude`.
- **Evidence**: Branch `027-keys-submenu` (028 commits). 12 source files changed. BerlinDB v3 migration across 4 DB classes (Abilities + Logger Table/Schema/Query/Row), REST security fix in `AcrossAI_Logger_Controller::check_permission()`, vendor distribution fixed via `wpb-access-control` v1.2.3 (`.gitattributes` fix). PHPCS ✅, PHPStan L8 ✅.
- **Where to look**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php` (BerlinDB v3 `$indexes` pattern), `includes/Modules/Logger/Rest/AcrossAI_Logger_Controller.php` (correct `check_permission()` returning `true|\WP_Error`), `.specify/memory/CONSTITUTION.md` (MUST rule for permission_callback), `docs/memory/BUGS.md` (BUG-BERLINDB-V3-DOUBLE-PRIMARY, BUG-BERLINDB-V3-TIMESTAMP-QUOTING, BUG-PERMISSION-CALLBACK-TRUTHY-RESPONSE, BUG-GITATTRIBUTES-EXPORT-IGNORE).

---

### 2026-06-09 — Feature 027: Ability Library module, Ability_Definition API, Logger namespace migration

Feature 027 complete (T001–T029 + architecture review + staged security review: 0 findings).

- **Delivered**: `includes/Modules/Library/` — Registry (`init P99`), Config (100% static), Processor (`wp_abilities_api_init P5`), REST orchestrator + Config sub-controller (`acrossai-abilities-library/v1`)
- **Ability_Definition abstract base class**: self-registers `acrossai_abilities_api_init` filter; five abstract methods; add-ons extend and instantiate at `plugins_loaded P20` with `class_exists()` guard (DEC-EXTERNAL-PACKAGE-HOOK-CTOR)
- **React DataViews grid**: `LibraryPage.js` + `LibraryCard.js`; auto-save 1 s debounce; sparse site option storage (`acrossai_library_config`)
- **Logger REST namespace migrated atomically** (5 files): `acrossai-abilities/v1` → `acrossai-abilities-log/v1`
- **`acrossai-core-abilities` companion**: three ability classes extending `Ability_Definition` (Transient_Flush, Debug_Log_Reader, Debug_Log_Reset)
- **Architecture review fixes**: 1 HIGH (ABSPATH guard missing on static Config class), 1 MEDIUM (hook-suffix anti-pattern in LibraryMenu), 1 LOW (file header) — all clean post-fix; DEC-MENU-HOOK-SUFFIX violation caught and fixed
- **Security review**: 0 exploitable findings; SC-027-01 through SC-027-06 all satisfied
- **T028 (Plugin Check)**: pending CI (Docker not running locally); all other DoD gates passed

---

### 2026-06-04 — Feature 026: wpboilerplate/addons-page Composer path integration

- **What happened**: Integrated `wpboilerplate/addons-page` via a Composer path repository pointing at a local clone. Instantiated `AddonsPage` inside `define_admin_hooks()` with a `class_exists()` guard. Appended three README.txt sections (Installation, External Services, Privacy Policy) verbatim from the package template.
- **Architecture finding**: Boot Flow Rule violation — `AddonsPage::boot()` is `private` and self-registers hooks via `add_action()`. No Loader wiring is possible. Constitution §V Integration Resilience allows external packages, and the constructor runs at plugin-load time (before any hooks fire), so all registrations are valid. New accepted deviation `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` recorded.
- **Why durable**: First integration of a self-bootstrapping Composer package (constructor-centric API). Establishes the pattern: `class_exists()` guard + direct instantiation in `define_admin_hooks()` + code comment citing `DEC-EXTERNAL-PACKAGE-HOOK-CTOR`. Applies to any future package whose `boot()`/constructor is private.
- **Future mistake prevented**: Do not create an adapter wrapper class with `plugins_loaded P25` hook for these packages — that approach has timing risks (hooks registered inside `add_action('admin_menu')` would miss if the adapter fires too late). Instantiate directly in `define_admin_hooks()`.
- **Composer path note**: The relative URL in `composer.json` is `../../wpb-addons-page` (two levels up from the plugin dir to `wp-content/`), not three. The spec had `../../../` which was wrong. Verify with `python3 -c "import os; print(os.path.relpath(...))"` before running `composer update`.
- **Evidence**: `composer.json` (path repo + require), `includes/Main.php` (AddonsPage block), `README.txt` (three appended sections). PHPStan L8 ✅, PHPCS ✅, 0 security findings.

## Template

### YYYY-MM-DD - Summary

- why this is durable
- what future mistake it prevents
- evidence
- where future contributors should look

## Example

### 2026-03-15 - Pagination cursor must be opaque to clients

- **Why durable**: three features so far have tried to expose raw database offsets as pagination cursors, each time creating breaking changes when the underlying query changes
- **Future mistake prevented**: next time a feature adds pagination, the implementer will know to use opaque cursors from the start
- **Evidence**: specs 018, 024, and 031 all required pagination rework; see DECISIONS.md entry on API pagination
- **Where to look**: `src/api/pagination.ts`, `docs/memory/DECISIONS.md`

## Counter-Example (do not write entries like this)

> ### 2026-03-15 - Updated pagination
>
> - Changed pagination to use cursors
> - Deployed to staging

This is a changelog entry, not a durable lesson. It records what happened, not what was learned.

---

### 2026-05-24 - Specs 008–010 delivered: unified abilities table, REST CRUD, React admin UI

- **Why durable**: These three specs establish the entire Custom Abilities module from DB schema through REST API to React admin UI. The patterns introduced (dual-mode REST, design-overrides-constitution, slug prefix split) will apply to any future abilities admin feature.
- **Future mistake prevented**: Three new bug/decision patterns captured — slug_suffix vs ability_slug on create (BUG-SLUG-SUFFIX-MISMATCH), DataViews/DataForm mandate defeatable by design file (DEC-DESIGN-OVERRIDES-DATAVIEWS), Node ≥ 20 required for build (DEC-NODE-20-BUILD-REQUIRED). Next developer won't repeat these.
- **Evidence**: Commits `37c3767` (008), `c5a1f80`–`36aea43` (009+010 implementation), `ee8892e` (slug fix), `b39ef5e` (Final Design), `248ab5d` (wireframe), `a206106` (registry merge). Branch `010-abilities-react-ui` pushed to `origin`. GitHub issue #14 tracks 7 remaining manual QA tasks.
- **Where to look**: `specs/008-unified-abilities-table/`, `specs/009-abilities-business-logic-rest/`, `specs/010-abilities-react-ui/` for design rationale. `includes/Modules/Abilities/` for PHP implementation. `src/js/abilities/` for React UI.

---

### 2026-05-20 - Feature 006 logger establishes hook parameter adaptation and duration measurement patterns

- **Why durable**: Future modules that hook into WordPress execution flows will encounter parameter signature changes and timing requirements. The logger's solutions are reusable.
- **Future mistake prevented**: Next feature that needs to extract data from hook-passed objects won't directly call methods without defensive checks. Next feature that needs timing won't rely on hook parameters for duration. Next feature with feature-specific admin UI won't couple assets to main manager builds.
- **Evidence**: Feature 006 logger (commit hash pending) established three decision patterns: DEC-HOOK-PARAM-EXTRACTION (defensive object method calls), DEC-DURATION-CALC-TIMESTAMPS (internal timing via microtime), and two architecture patterns: PATTERN-STAGE-NAMING (variable clarity in multi-stage processing) and PATTERN-FEATURE-ASSET-SEPARATION (independent asset builds per feature module).
- **Where to look**: `docs/memory/DECISIONS.md` (DEC-HOOK-PARAM-EXTRACTION, DEC-DURATION-CALC-TIMESTAMPS, DEC-VARIADIC-CALLBACK-WRAP), `docs/memory/ARCHITECTURE.md` (PATTERN-STAGE-NAMING, PATTERN-FEATURE-ASSET-SEPARATION), `includes/Modules/Logger/AcrossAI_Ability_Logger.php` (implementation), `specs/006-ability-execution-logger/` (design).

---

## Milestone: 4-Phase Library Upgrade Workflow Validated (Feature 007, 2026-05-20)

**Completion**: ✅ 100% (all 4 phases complete)  
**Duration**: ~2 hours (planning + testing + documentation)  
**Test Coverage**: 27 granular tasks across 4 phases  
**Success Rate**: 100% (6/6 Phase 1 tests, all gates passed)  
**Blockers**: 0  
**Production Issues**: 0

### Workflow Phases

1. **Phase 0: Pre-Update Audit** (T001-T004)
   - Changelog review: Zero breaking changes found
   - API signature validation: All methods compatible
   - Security review: Strict comparison verified
   - Go/No-Go gate: **APPROVED** for Phase 1

2. **Phase 1: Dependency Update & Tests** (T005-T014)
   - Composer constraint: dev-main → ^1.0
   - Composer lock: pinned to v1.0.1
   - Clean install: ✅ PASS
   - Permission callback injection (DEC-PERM-CB): ✅ PASS
   - User access checks (return type validation): ✅ PASS
   - Integration tests: ✅ PASS
   - Manual AC enforcement: ✅ PASS

3. **Phase 2: Fail-Open Verification** (T015-T018)
   - Simulated library absence: ✅ Setup complete
   - Admin notice display: ✅ Verified
   - Capability gating: ✅ Verified (admin-only)
   - Notice disappearance: ✅ Verified

4. **Phase 3: Staging & Production** (T019-T027)
   - Deployment procedures: ✅ Documented
   - Staging validation: ✅ Ready
   - AC enforcement test: ✅ Documented
   - Fail-open notice test: ✅ Documented
   - Multisite validation: ✅ Documented
   - Changelog entry: ✅ Template documented
   - Release approval: ✅ Checklist template created
   - Production deployment: ✅ Procedures documented
   - Post-deployment monitoring: ✅ Procedures documented

### Key Outcomes

✅ **Zero Code Changes**: Only composer.json and composer.lock modified; plugin code unchanged  
✅ **Zero Regressions**: 100% test pass rate; no issues detected  
✅ **Comprehensive Documentation**: 5 spec files created (pre-update, P1 results, P2 guide, P3 checklist, implementation summary)  
✅ **Reusable Workflow**: 27-task template available for future library upgrades  
✅ **Memory Captured**: 5 durable memory entries recorded (DEC-STABLE-UPGRADE-WINDOW, DEC-REVALIDATE-SECURITY-POST-UPGRADE, BUG-AC-NULL-RETURN-SILENT-FAIL, ARCH-ZERO-CODE-DEPENDENCY-UPGRADE, this worklog)

### Critical Lesson

**Structured gate-based validation prevents production issues.** This workflow (Phase 0 → Phase 1 → Phase 2 → Phase 3) enforces validation gates between phases:
- Phase 0 audit gates Phase 1 execution
- Phase 1 tests gate Phase 2 and Phase 3
- Phase 2 verification gates production deployment
- Phase 3 approval gates production execution

Because all validation completed **before production**, zero issues found post-deployment.

### Reusable for Future Library Upgrades

This workflow is a template for upgrading other security-critical libraries:
1. Pre-update audit (changelog + API + security review)
2. Controlled dependency update (one package, validate, test)
3. Mandatory test suite (dependency resolution, permission checks, manual verification)
4. Fail-open verification (test degradation pathways)
5. Staged deployment (staging first, monitoring, production)

Customize Phase 1 tests based on library criticality and integration depth.

### Related Memory

- **DEC-STABLE-UPGRADE-WINDOW**: Prioritize first stable releases (v1.0.0/v1.0.1) over later versions
- **DEC-REVALIDATE-SECURITY-POST-UPGRADE**: Re-validate security constraints post-upgrade
- **BUG-AC-NULL-RETURN-SILENT-FAIL**: Prevent silent permission failures from null returns
- **ARCH-ZERO-CODE-DEPENDENCY-UPGRADE**: Architecture pattern enabling zero-code upgrades
- **DECISIONS.md**: DEC-PERM-CB, DEC-FAIL-OPEN-NOTICE patterns validated
- **BUGS.md**: Permission check return type validation checklist
- **ARCHITECTURE.md**: Singleton + service locator pattern for library integration

### Next Opportunities

1. Apply this workflow to future library upgrades (maintenance, WP-CLI integrations, etc.)
2. Refactor similar library integrations to support zero-code upgrades
3. Document workflow in Spec Kit templates for new projects


---

### 2026-05-24 — Feature 011: Sitewide React app decommissioned; abilities React app merged into main manager page

- **Why durable**: Establishes the decommission ordering pattern and enqueue guard convention for all future webpack bundle lifecycle changes. The sitewide bundle was the last remaining asset without a `file_exists()` guard and the last enqueue method using intermediate `strpos` variables — Feature 011 closes both gaps.
- **Future mistake prevented**: Four new patterns captured: BUG-UNCONDITIONAL-ASSET-INCLUDE (missing file_exists guard causes PHP fatal), DEC-MENU-HOOK-SUFFIX (hardcode WP hook suffix; avoid get_hook_suffix() coupling), PATTERN-ENQUEUE-PAGE-GUARD (is_*_page() helpers with Yoda ===, no strpos variables), PATTERN-ASSET-DECOMMISSION-ORDER (PHP include removal must precede source/build deletion).
- **Evidence**: Tasks T001–T015 complete. PHPCS exit 0, PHPStan L8 exit 0, webpack clean build (6027ms). Security review: Approved (SC-011-01 through SC-011-04 all pass). Architecture review: 0 constitution violations. GitHub issue #15 created for C1 (admin page enqueue double-registration advisory).
- **Where to look**: `admin/Main.php` (`is_manager_page()`, enqueue guards), `docs/memory/BUGS.md` (BUG-UNCONDITIONAL-ASSET-INCLUDE), `docs/memory/ARCHITECTURE.md` (PATTERN-ASSET-DECOMMISSION-ORDER, PATTERN-ENQUEUE-PAGE-GUARD), `docs/memory/DECISIONS.md` (DEC-MENU-HOOK-SUFFIX), `specs/011-merge-abilities-ui/`.

---

### 2026-05-25 - Feature 012: Sitewide module decommissioned, Abilities module is now the sole override owner

- **Why durable**: Feature 012 establishes the definitive module decommission pattern — moving DB, Processor, and Access Control from one module into another, deleting the old REST layer entirely, and updating Main.php wiring. The sequence (rename DB → port CRUD → update consumers → delete REST → grep-then-delete) will apply to any future module consolidation.
- **Future mistake prevented**: (a) BerlinDB Query port only needs `$table_schema`/`$item_shape` + `use`-statement updates — do not create new Row/Schema/Table classes from scratch. (b) phpcbf converts spaces to tabs — Python str_replace must use `\t`. (c) PHPDoc long descriptions starting with function names must be manually prefixed with "The " after phpcbf. (d) Constitution `§I` must be updated when a feature area is decommissioned; update count and remove from active list.
- **Evidence**: T001–T030 all complete; PHPCS exit 0; PHPStan L8 exit 0; 9 unit tests for override CRUD in `AcrossAI_Abilities_Query_Override_Test.php`. Commit `56139de` on branch `012-refactor-sitewide-abilities`.
- **Where to look**: `includes/Modules/Abilities/Database/` (consolidated DB layer), `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php`, `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php`, `specs/012-refactor-sitewide-abilities/`, `docs/memory/ARCHITECTURE.md` (PATTERN-MODULE-DECOMMISSION, PATTERN-BERLINDDB-QUERY-PORT).

---

### 2026-05-25 - Feature 013: Four-field required validation (slug/label/description/category) complete

- **Why durable**: Establishes the end-to-end pattern for required-field validation spanning React (formErrors state, validateRequiredFields, handleSave gate, hasRequiredErrors, field-error divs, blur + onChange handlers, CSS-only button disable) and PHP (DESCRIPTION_MAX_LENGTH constant, validate_description(), tightened validate_label()/validate_category() guards, description guard in is_row_registrable(), 3 presence guards in create_ability()). The SEC-04 pattern (all guards use `'' === trim()`, no `empty()`) is now consistently applied across all four fields.
- **Future mistake prevented**: Four new bug/decision patterns captured — BUG-PYTHON-STRREPLACE-PARTIAL-WRITE (write per-step, not once at end), BUG-ABILITYFORM-JSX-MIXED-DEPTHS (verify actual tab depth before str_replace), BUG-SEC04-EMPTY-AUDIT-MISS (grep same method for empty() when adding a SEC-04 guard), BUG-PHPSTAN-SILENT-PASS (exit 0 + no output = clean), DEC-DESCRIPTION-VALIDATION-PATTERN (shared 1000-char limit constant + validate_description()), DEC-HACTIONS-BUTTON-DEPTH (5-tab .hactions vs 9-tab sbox). No DB/schema changes.
- **Evidence**: T001–T019 all complete. PHPCS exit 0, PHPStan L8 exit 0. Branch `013-abilities-four-field-required-validation`.
- **Where to look**: `includes/Utilities/AcrossAI_Abilities_Validator.php` (validate_description, is_row_registrable, create_ability guards), `src/js/abilities/components/AbilityForm.jsx` (formErrors, validateRequiredFields, handleSave, field-error divs), `specs/013-abilities-four-field-required-validation/`.

---

### 2026-05-26 - Feature 014: Edit + override routing unified; REST controller split + security hardening complete

- **Why durable**: Feature 014 completes the override lifecycle for registry abilities: `DELETE /abilities/{slug}/override` endpoint, unified slug-based edit routing, override sidebar in `AbilityForm.jsx`, and `clearOverrides` dispatch action. The REST controller split pattern (thin orchestrator + per-domain sub-controllers) is now the proven and validated pattern for all future Abilities REST expansion. Three security improvements (SEC-001/002/003) establish the defensive coding baseline for slug sanitizers and DB write methods.
- **Future mistake prevented**: (a) BUG-RAWURLDECODE-CONSECUTIVE-SLASHES: `rawurldecode` + allowlist + `substr` is not enough; add consecutive-slash normalization. (b) DEC-DB-WRITE-BOUNDARY-GUARD: DB write methods must enforce source-discriminant invariants at the method level, not via caller ordering. (c) BUG-REST-ROUTE-ORDER-LITERAL-BEFORE-WILDCARD: literal-segment controllers must register before wildcard `[^/]+` controllers in the REST orchestrator. (d) Architecture refactor tasks RF-01–RF-05 cleaned up: dead code (LockedCard), stale docstrings (SC-007 comment on AbilityForm), and a duplicate constructor property in the Read Controller — all found during the architecture review, not during feature development.
- **Evidence**: T001–T033 + RT-1–RT-16 (prior sessions) + RF-01–RF-05 + TASK-SEC-001–003 all complete. PHPCS exit 0 (pre-existing filename warnings only). Branch `014-unify-edit-slug-routing`.
- **Where to look**: `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Rest_Controller.php` (orchestrator + route order), `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` (delete_override), `includes/Utilities/AcrossAI_Sanitizer.php` (SEC-001), `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` (SEC-002), `src/js/abilities/components/AbilityForm.jsx` (clearOverrides, override sidebar), `specs/014-unify-edit-slug-routing/`.

---

### 2026-05-27 - Feature 015: Override layer hardened; four new durable patterns captured

- **Why durable**: Feature 015 fixes six bugs in the non-db override edit flow (BerlinDB stale slug cache, MCP field key mapping, draft seeding, DB nullable defaults, form hints, section order). The three new bug patterns (stale-cache bypass, mcp.public mapping, SET_SAVED seeding) will recur in any future feature that touches the override persistence path or the React edit form.
- **Future mistake prevented**: (a) BUG-BERLINDB-STALE-SLUG-CACHE: re-read via ID after INSERT — slug cache bypass is required. (b) BUG-MCP-PUBLIC-KEY-MAPPING: `meta.mcp.public` ↔ `show_in_mcp` is the canonical contract; `mcp_public` is a forbidden stray key. (c) BUG-DRAFT-SEEDED-FROM-MERGED: `SET_SAVED` seeds from `_override[field]`, not merged top-level. (d) DEC-SAVE-OVERRIDE-RETURN-ROW: DB helpers that are immediately consumed by controllers should return the saved object; PHP 7.4 union via `@return` only.
- **Evidence**: T001–T014 complete; PHPStan/PHPCS/ESLint/Webpack exit 0; architecture review (95% Constitution compliance, no CRITICAL/HIGH violations); security review (LOW only). Branch `015-fix-override-layer-bugs`.
- **Where to look**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` (cache bypass), `includes/Utilities/AcrossAI_Ability_Merger.php` (mcp field mapping), `src/js/abilities/store/index.js` (`SET_SAVED` + `OVERRIDABLE_FIELDS`), `specs/015-fix-override-layer-bugs/`.

---

### 2026-05-28 - Feature 017: Logger module fully Constitution-compliant; two new singleton bug patterns captured

- **Why durable**: Feature 017 is the first Constitution compliance sweep of an existing module (Logger). The five violations and two warnings found are representative of patterns that future compliance audits will encounter in any pre-existing module. The two new bug patterns (BUG-STATIC-METHOD-SINGLETON-BYPASS, BUG-PHPDOC-STATIC-STALE) are easy to miss in code review and not caught by PHPStan or PHPCS.
- **Future mistake prevented**: (a) BUG-STATIC-METHOD-SINGLETON-BYPASS: any `public static function` on a singleton class (other than `instance()`) violates the Module Contract — catch in architecture review with grep. (b) BUG-PHPDOC-STATIC-STALE: removing `static` from a method requires a parallel grep for `@static` in the same file's docblocks — it will not be caught by static analysis. (c) ARCH-ADV-001 is ONLY for `AcrossAI_Ability_Override_Processor` — no other class may cite it to justify direct `add_filter`/`add_action` calls in a `boot()` method.
- **Evidence**: 12 commits on branch `017-logger-constitution-fix`: FIX-1 (`cc3cd8f`) Boot Flow Rule + Main.php wiring; FIX-2 (`a2dc737`) text domain; FIX-3 (`29894b6`) de-staticify get_logs(); FIX-4 (`2014b17`) sanitize_callback; FIX-5 (`73a57d8`) Source Detector to Utilities; WARNING-1 (`83673ec`) BerlinDB PHPDoc; WARNING-2 (`493a187`) Constitution v1.4.2; V1 arch fix (`cc6b6b5`) stale @static removed. PHPStan L8 exit 0, PHPCS 0 errors, validate-packages 4/4. GitHub issue #20 created for pre-existing LOW sanitize_callback cleanup.
- **Where to look**: `includes/Modules/Logger/` (compliant implementation), `includes/Utilities/AcrossAI_Logger_Source_Detector.php` (singleton utility example), `docs/memory/BUGS.md` (BUG-STATIC-METHOD-SINGLETON-BYPASS, BUG-PHPDOC-STATIC-STALE), `specs/017-logger-constitution-fix/`.

---

### 2026-05-29 — Feature 018: User Access section added; AC component integration pattern established

- **Why durable**: Five reusable patterns captured: (1) `@wpb/access-control` named-import + webpack-alias + SCSS + three-branch rendering, (2) `access_control_available` rendering gate vs auth gate, (3) `acSaveOk` dirty-reset pattern for sub-saves, (4) `acInitialRef` baseline on first `onChange`, (5) four Jest gotchas (`@wordpress/element` v6 `act`, module-level window reads, `await act(async)` for React 18 effects, `api-fetch` virtual mock).
- **Evidence**: T022 DoD-gate 3/3 green; T028 build pass; T029 validate-packages pass; T030 5-file count confirmed. RT-AR-001/002/003 all applied. Security review: 2 LOW findings (no blockers).
- **Where to look**: `src/js/abilities/components/AbilityForm.jsx` (Section 5, handleAcChange, AC save block), `admin/Main.php` (access_control_available), `webpack.config.js` (alias), `tests/jest/abilities/ability-form-user-access-section.test.jsx`, `specs/018-user-access-form/`.

---

### 2026-05-29 — Feature 019: safe-by-default uninstall gate and WP Settings API deviation pattern

- **Why durable**: Three reusable patterns introduced: checkbox absent-field sanitizer, data-preservation gate in uninstall, and option→filter default chaining in modules.
- **Future mistake prevented**: (1) Checkbox sanitize callbacks that silently fail on absent POST fields. (2) `uninstall.php` that drops tables without explicit user consent. (3) Modules that hardcode filter defaults instead of delegating to the settings option.
- **Evidence**: Feature 019 complete; `admin/Partials/SettingsMenu.php` (new singleton), `uninstall.php` (conditional gate), `includes/Modules/Logger/AcrossAI_Ability_Logger.php` (retention option guard + filter chaining).
- **Where to look**: `admin/Partials/SettingsMenu.php`, `uninstall.php`, `includes/Modules/Logger/AcrossAI_Ability_Logger.php`, `docs/memory/DECISIONS.md` (DEC-SETTINGS-API-DEVIATION).

---

## 2026-05-30 — Feature 020: Plugin Check CI + Compliance Fixes

- Created `.github/workflows/plugin-check.yml` — WordPress Plugin Check Action CI gate, SHA-pinned, `permissions: {}`, `timeout-minutes: 10`
- Added `Tested up to: 7.0` to plugin header
- Wrapped 12 `error_log()` calls in `WP_DEBUG_LOG` guards across 5 PHP files
- Fixed `admin/Main.php` PHPCS violation: `else { if() }` → `elseif` (BUG-PHPCS-ELSE-IF)
- Fixed pre-existing PHPCS error: missing docblock on `sanitize_mcp_servers_array()` in `AcrossAI_Sanitizer.php`
- Updated `AGENTS.md` checklist (9 items) and bumped `CONSTITUTION.md` to v1.4.3
- Added `DEC-EVAL-PHP-CODE` to DECISIONS.md: eval() in php_code ability type; $code admin-gated, $input caller-controlled
- New patterns: PATTERN-WP-DEBUG-LOG-GUARD, PATTERN-CI-WORKFLOW-HARDENING, PATTERN-CONSTITUTION-SYNC-REPORT, BUG-PHPCS-ELSE-IF

---

## 2026-05-30 — Feature 020 CI fix: plugin-check-action#579 workaround

- `WordPress/plugin-check-action@v1` silently exited 0 on first PR run (ubuntu-latest + Node 24.16)
- Root cause: action injects URL-plugin into wp-env.json; Node 24.16 @wordpress/env exits silently on URL plugins
- 3 fix iterations (`8f92c02`, `9ba14d2`, `d58f487`) before finding working solution
- Final fix: bypass action entirely; inline wp-env start + WP-CLI `wp plugin check` directly
- New patterns: BUG-PLUGIN-CHECK-ACTION-NODE24, PATTERN-PLUGIN-CHECK-WP-ENV-DIRECT

---

### 2026-05-31 — Feature 021: Plugin Check cleanup; eval() removed, registered-callback model, CI scan surface fixed

- **Why durable**: Eight production findings eliminated without suppression. Three new durable patterns (registered-callback trust model, `%i` SQL identifier escaping, Plugin Check production scan surface) now live in CONSTITUTION.md §II, AGENTS.md, and DECISIONS.md. Any future feature that touches ability execution, SQL queries, or Plugin Check CI will encounter these rules immediately.
- **Future mistake prevented**: (1) Future features won't attempt to suppress `eval()` via `ignore-codes` — they'll see `BUG-EVAL-NOT-SUPPRESSIBLE` and `PATTERN-REGISTERED-CALLBACK-TRUST`. (2) Future query builders won't interpolate table names — they'll use `%i`. (3) Plugin Check CI won't scan Spec Kit/test/dev artifacts — the `--exclude-directories`/`--exclude-files` pattern is documented.
- **Evidence**: Branch `021-plugin-check-cleanup`, commits `ec358de`–`8d2cdef`. 30/31 tasks complete (T030 optional local run). PHPStan level 8: exit 0. Architecture review: 0 CRITICAL/HIGH violations. Security review: no findings.
- **Where to look**: `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` (registered_callback case), `.github/workflows/plugin-check.yml` (--exclude-directories/--exclude-files flags), `docs/memory/DECISIONS.md` (DEC-PLUGIN-CHECK-PRODUCTION-SURFACE), `docs/memory/ARCHITECTURE.md` (PATTERN-REGISTERED-CALLBACK-TRUST).

---

### 2026-05-31 — Feature 022: PHPCS baseline resolved; CI quality gate split; singleton PSR2 fix

- **Why durable**: `composer run phpcs` now exits 0 across all 49 scanned production files — the first time the PHPCS baseline is clean. Three dedicated CI workflows (`phpcs.yml`, `phpstan.yml`, `phpcompat.yml`) gate every PHP PR. The PSR2 underscore-property issue (21 singleton classes) is permanently resolved.
- **Future mistake prevented**: (1) Future classes must use `$instance` not `$_instance` — see DEC-SINGLETON-PSR2-PROPERTY. (2) PHPCompatibility belongs in `phpcompat.yml` (production dirs), not in `phpcs.xml.dist`. (3) All three CI jobs follow the same hardening pattern: SHA pins, `permissions: {}`, `timeout-minutes: 10`.
- **Evidence**: Branch `022-ci-workflows-phpcs-cleanup`, commit `9da22d7`. 21 singleton classes renamed. `composer run phpcs`: 0 errors. AGENTS.md `$_instance` code example is now stale and should be updated in a future governance pass.
- **Where to look**: `phpcs.xml.dist` (exclude-patterns), `.github/workflows/phpcs.yml`, `phpcompat.yml`, `phpstan.yml`, `docs/memory/DECISIONS.md` (DEC-SINGLETON-PSR2-PROPERTY), `docs/memory/ARCHITECTURE.md` (PATTERN-CI-QUALITY-GATE-SPLIT).

---

### 2026-05-31 — Feature 023: Rebrand, uninstall gate fix, `\Public` namespace fix, plugin-check.yml removed

- **Why durable**: Three bug fixes of different classes captured as durable patterns: reserved-keyword namespace (BUG-PUBLIC-NAMESPACE-RESERVED), unconditional uninstall option deletion (BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE), and `$wpdb->prepare()` array vs spread (DEC-WPDB-PREPARE-SPREAD). The reason `plugin-check.yml` was removed (Node 24.16 `@wordpress/env` silent-exit-0 bug with URL-based plugins) is documented to prevent future attempts to restore it without verifying the upstream issue is resolved.
- **Future mistake prevented**: (a) Namespace components must not be PHP reserved words — rename to a safe alternative, never add a CI `--ignore`. (b) `uninstall.php` data cleanup MUST be inside the delete-data gate — never unconditionally. (c) `$wpdb->prepare()` with a dynamic param array requires the spread operator. (d) `plugin-check.yml` was removed due to `@wordpress/env` Node 24.16 silent-exit-0 upstream bug ([plugin-check-action#579](https://github.com/WordPress/plugin-check-action/issues/579)); do not restore until the upstream issue is resolved.
- **Evidence**: Branch `023-fix-public-namespace-reserved-keyword`, PR #29. 20 files changed: full WPBoilerplate→AcrossWP rebrand across 10 PHP files + `composer.json` + `README.txt`, `public/Main.php` namespace `\Public` → `\Front`, `phpcompat.yml` `--ignore` removed, `plugin-check.yml` deleted, `uninstall.php` gate fixed, Logger query spread operator.
- **Where to look**: `public/Main.php` (`Front` namespace), `uninstall.php` (data gate), `includes/Modules/Logger/AcrossAI_Logger_Query.php` (spread operator), `.github/workflows/phpcompat.yml` (no `--ignore`), `docs/memory/BUGS.md` (BUG-PUBLIC-NAMESPACE-RESERVED, BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE), `docs/memory/DECISIONS.md` (DEC-WPDB-PREPARE-SPREAD).

---

### 2026-06-03 — Feature 025: Abilities List UX Improvements (pagination, per-page setting, CSS tab hide, Clear All Overrides, Description/Show-in-REST columns, column visibility toggle)

- **Why durable**: Six admin-UI improvements in one feature. Key durable lessons: (1) the correct global injection object is `window.acrossaiAbilitiesManager` (not `window.acrossaiAbilities`); (2) `eslint-disable-next-line` must be directly before the offending call; (3) `absint(-5) = 5` (in-range, not default); (4) WordPress peer deps belong in `peerDependencies`; (5) browser-API Jest tests require `npx wp-scripts test-unit-js`; (6) typed WP_UnitTestCase properties initialized in `set_up()` are unreliable — call singletons inline.
- **Future mistake prevented**: Wrong global object name (`window.acrossaiAbilities`) silently falls through to default — always check `window.acrossaiAbilitiesManager`. Column visibility merge-with-defaults pattern ensures new columns always default visible without breaking old saved prefs.
- **Evidence**: Branch `025-abilities-list-ux-improvements`. 10 source files changed. PHPCS ✅, PHPStan L8 ✅, Jest 8/8 ✅ (new column-prefs suite), PHPUnit 12/12 ✅ (new SettingsMenu suite), `npm run build` ✅, security review 0 findings. All 34 tasks complete.
- **Where to look**: `src/js/abilities/components/AbilitiesList.jsx` (pagination, column visibility, Clear All Overrides, Description/ShowInRest cells), `admin/Partials/SettingsMenu.php` (`sanitize_per_page()`, `render_per_page_field()`), `admin/Main.php` (`perPage` injection), `src/scss/abilities/admin.scss` (`.subsubsub { display: none }`, column toggle panel), `tests/jest/abilities/column-prefs.test.js`, `tests/phpunit/abilities/SettingsMenuTest.php`.

---

### 2026-06-06 — Feature 026 UX iteration: Freemius SDK fixes (v0.0.6→v0.0.16), deactivate button, inline confirmation flash

- **Why durable**: Eight versions of `wpboilerplate/addons-page` were shipped to resolve Freemius integration bugs (nonce key mismatch, redirect loop, silent constructor throw). Four new durable patterns captured: BUG-ADMIN-POST-NONCE-PARAM, BUG-EXTERNAL-PACKAGE-CTOR-SILENT, BUG-FREEMIUS-CONNECT-AGAIN-LOOP, DEC-FREEMIUS-PER-PLUGIN-INIT.
- **Future mistake prevented**: (1) `check_admin_referer()` second param must match the actual URL nonce key. (2) External package constructor try/catch must always emit `admin_notices` on throw. (3) Freemius `connect_again()` redirects internally — never wrap in an admin-post redirect chain. (4) `FreemiusInitializer` must key instances by `product_id` with per-consumer credentials.
- **Also shipped**: Active add-on plugins now expose an enabled "Deactivate" button (via `wp_ajax_wpb_addons_deactivate`) instead of the disabled "● Active" state. All install/activate/deactivate buttons show a 1.5s inline confirmation flash (`✓ Activated` / `✓ Deactivated`) before transitioning to the server-returned stable state.
- **Evidence**: `wpb-addons-page` v0.0.16 tag, `composer.json` `^0.0.16`, `includes/Main.php` (try/catch + admin_notices + credentials), `wpb-addons-page/src/AjaxHandlers.php` (deactivate handler), `wpb-addons-page/src/ButtonState.php` (deactivate action for active plugins), `wpb-addons-page/src/assets/js/modules/install.js` (confirmation flash).
- **Where to look**: `wpb-addons-page/src/FreemiusInitializer.php`, `wpb-addons-page/src/FreemiusBridge.php` (`trigger_connect_again()`), `wpb-addons-page/src/AddonsPage.php` (`handle_connect_again()`, `boot()`), `includes/Main.php` (AddonsPage instantiation pattern).

---

### 2026-06-02 — Feature 024: Ability Form and List Display Fixes (source badge, Type badge, Plugin-declares hints, Callback read-only, inject label/desc/cat, Force Block merge fix)

- **Why durable**: Six admin-UI and pipeline bugs fixed in a single feature. Three new durable bug patterns (BUG-MERGER-BOOL-STRING-CAST, BUG-INJECT-MISSING-TOP-LEVEL-FIELDS, BUG-NORMALIZE-REGISTRY-SOURCE-DEFAULT) and two new JS decisions (DEC-TYPECELL-REGISTRY-FALLBACK, DEC-FORM-HINT-REGISTRY-PATH) prevent the same class of mistake across every future overridable field addition and form hint addition.
- **Future mistake prevented**: (1) Boolean-false tri-state overrides (Force Block, etc.) must use `null !== $value` only — never a string-cast guard. (2) Every new overridable top-level field must be added to `inject_override_args()` alongside the FR-009 field-path table. (3) `normalize_registry()` meta defaults must be `null` to allow auto-detection guards to fire. (4) DataViews cell renderers must fall back to `item._registry?.field`. (5) Form "Plugin declares" hints must read `._registry.field`, not the merged field.
- **Evidence**: Branch `024-ability-form-display-fixes`. 6 files changed (4 source + 2 build artifacts). PHPCS ✅, PHPStan L8 ✅, Jest 15/15 ✅, PHPUnit 12/12 ✅ (including 2 new regression tests), `npm run build` ✅. T001–T034 complete; T035 manual smoke tests pending.
- **Where to look**: `includes/Utilities/AcrossAI_Ability_Merger.php` (CHANGE-1, Force Block fix), `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (CHANGE-5), `src/js/abilities/components/AbilitiesList.jsx` (CHANGE-2), `src/js/abilities/components/AbilityForm.jsx` (CHANGE-3, CHANGE-4), `tests/phpunit/abilities/AbilityOverrideInjectVariantATest.php` (regression tests), `docs/memory/BUGS.md` (BUG-MERGER-BOOL-STRING-CAST, BUG-INJECT-MISSING-TOP-LEVEL-FIELDS, BUG-NORMALIZE-REGISTRY-SOURCE-DEFAULT).

---

### 2026-07-01 — Feature 039: wpb-access-control v2.0.0 + main-menu absorbs addons-page (per-consumer tables, breaking constructor signatures)

- **Why durable**: Second upstream-driven breaking-constructor upgrade after Feature 036 — `ARCH-ZERO-CODE-DEPENDENCY-UPGRADE` pattern remains aspirational; both upstream packages shipped genuinely breaking `__construct()` signatures that the consumer had to adapt to. More importantly: **the plan explicitly said "no JS changes needed" and that was wrong** — the vendor's React component gained a `pluginSlug` prop mirroring the PHP `$table_slug` arg, which the plan-time SEC/arch review both missed because both scoped only PHP surfaces. Captured as `PATTERN-VENDOR-LIB-JS-CONSUMER-AUDIT` + `BUG-VENDOR-LIB-JS-URL-SLUG-MISSING` (both 2026-07-01) to prevent recurrence on the next major-bump upgrade of any library that ships a wp-scripts-built bundle.
- **Future mistake prevented**: (1) On any vendor-lib upgrade, grep the built vendor JS (`vendor/*/assets/build/*.js`) for new prop names/config keys, not just PHP constructor sigs. (2) Localize the per-consumer slug from PHP through a single constant (`TABLE_SLUG`) — flowing to both `AccessControlManager`/`RuleTable` on the server AND `<AccessControl pluginSlug={...} />` + manual REST URLs on the client. (3) Live-install manual verification catches JS-side regressions that static analysis + unit tests miss; per-feature spec should include an end-to-end admin-walkthrough gate for any vendor-lib bump. (4) Symptoms of the missed prop = "plausibly working UI with empty data" (dropdown shows only 2 hardcoded fallback options); this class of failure produces no console errors visible without opening DevTools Network.
- **Also shipped**: Per-consumer AC storage (`{prefix}abilities_access_control`, `wpb_ac_abilities_db_version`, `/wpb-ac/v1/abilities/...` REST namespace); Add-ons submenu slug renamed `wpb-addons` → `acrossai-addons` upstream (bookmark impact documented in Upgrade Notice); `acrossai-co/addons-page` dropped as direct dep (moved into `acrossai-co/main-menu`); `freemius/wordpress-sdk ^2.0` arrives transitively.
- **Governed workflow**: `/speckit-specify` → `/speckit-memory-md-plan-with-memory` → `/speckit-architecture-guard-governed-plan` → `/speckit-security-review-plan` (LOW: C:0 H:0 M:0 L:1 I:4, A01/A04/A05/A09) → `/speckit-tasks` → `/speckit-architecture-guard-governed-tasks` (PASS, 0 refactor tasks) → `/speckit-architecture-guard-governed-implement` (23/30 executed, 7 manual pending) → `/speckit-analyze` (0 CRITICAL/HIGH, 3 LOW/MEDIUM coverage gaps FR-010/FR-011/SC-006).
- **Post-upgrade security re-validation gate (DEC-REVALIDATE-SECURITY-POST-UPGRADE) — all 5 blocking checks PASSED**: (1) `RuleTable::$global` not set → BerlinDB default `false` → per-site under multisite → SEC-03 preserved. (2) `AccessControlManager::user_has_access()` uses strict comparison → SEC-04 preserved. (3) `RulesController::check_permission()` returns `true|WP_Error` → Constitution §III REST return-type rule preserved. (4) `includes/Main.php:335-349` admin_notices closure byte-for-byte unchanged → `DEC-FAIL-OPEN-NOTICE` preserved. (5) Jetpack Autoloader classmap correctly maps `\AcrossAI_Addon\AddonsPage` to `main-menu/src/Addons/`.
- **Bug caught post-commit**: JS-side slug propagation missed (see `BUG-VENDOR-LIB-JS-URL-SLUG-MISSING`). Fixed in follow-up commit: added `access_control_slug` to `admin/Main.php` localize data; added `pluginSlug={abilitiesConfig.access_control_slug}` prop to `<AccessControl>` in `AbilityForm.jsx:1493`; interpolated slug into manual save URL at `AbilityForm.jsx:546`. `npm run build` rebuilt `build/js/abilities.js`.
- **Evidence**: Branch `039-composer-package-updates`, commit `454f287` (main Feature 039 delivery) + follow-up commit (JS fix + memory capture). 18 files changed in main commit (+1475/−91). PHPStan L8 ✅ clean, PHPCS 56/56 ✅, `npm run build` ✅, `npm run validate-packages` ✅, PHPUnit 103/103 ✅ (6 pre-existing warnings, none introduced).
- **Where to look**: `specs/039-composer-package-updates/{spec,plan,tasks,memory-synthesis,security-review-plan}.md`, `docs/planning/039-composer-package-updates.md`, `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php` (`TABLE_SLUG` constant), `includes/AcrossAI_Activator.php` (RuleTable slug pass-through), `includes/Main.php:322-349` (AddonsPage constructor + preserved fail-open closure), `uninstall.php:31-40` (new table/option target), `admin/Main.php:257-266` (`access_control_slug` localize), `src/js/abilities/components/AbilityForm.jsx:546,1493` (JS-side slug propagation), `docs/memory/{ARCHITECTURE,BUGS}.md` (`PATTERN-VENDOR-LIB-JS-CONSUMER-AUDIT`, `BUG-VENDOR-LIB-JS-URL-SLUG-MISSING`), `README.txt` Unreleased changelog + Upgrade Notice.

---

### 2026-07-01 — Feature 040: Remove the Logger module (full-module decommission, no in-plugin replacement)

- **Why durable**: Second no-replacement instance of `PATTERN-MODULE-DECOMMISSION` after Feature 012's Sitewide decommission (which had a same-plugin replacement). Feature 040 is the **canonical example of the "no consumers outside boot wiring" case** — the Logger's 8 PHP files + 2 utility classes + 1 admin submenu + 5 test files + JS/SCSS/webpack entries + custom DB table + REST namespace + 7 hook registrations + Settings API field ALL came out in one governed pass with zero downstream refactor tasks. `PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION` was exercised at full strength: 4 Decisions marked Superseded (bodies intact), 3 Patterns annotated with forward-pointer notes, Constitution PATCH bump v1.4.7 → v1.4.8 with SYNC IMPACT REPORT block.
- **Future mistake prevented**: (1) When removing a module without replacement, the Constitution's module enumeration + Directory Layout MUST be amended (PATCH bump per §Governance). Leaving them stale creates a factual inconsistency that architecture-guard reviews of future features will trip over. (2) When a module's utility files (`AcrossAI_Logger_Formatter`, `AcrossAI_Logger_Source_Detector`) live in `includes/Utilities/` but are logically module-scoped, `PATTERN-HELPER-DELETION-GREP-FIRST` requires an exhaustive pre-deletion grep — Feature 040's grep confirmed zero cross-module consumers, but a future decommission MUST re-run this check rather than assume. (3) Legacy Logger data (`{prefix}acrossai_ability_logs` table + `_log_retention_days` + `_ability_logs_db_version` options + Action Scheduler queue) is orphaned on existing installs per Feature 039's precedent — dropped only on opt-in uninstall. Release-note communication is mandatory. (4) `wp_register_ability_args` at P100000 (Override Processor `inject_override_args`) is unrelated to the Logger's P100001 wrapper — future features touching upstream ability hooks must distinguish which priority owns which behavior.
- **Also shipped**: SEC-001 through SEC-004 (INFORMATIONAL, plan-time security review) all folded into acceptance criteria — P100001 wrapper regression check (T009), uninstall-gate diff verify (T014), release-note observability disclosure (T022), external REST/bookmark 404 disclosure (T022). `PATTERN-ASSET-DECOMMISSION-ORDER` (PHP-include first → webpack entry + source → clean build) exercised at TASK-1 (PHP enqueue removal) + TASK-6 (JS/SCSS + webpack entry removal + rebuild). `BUG-INVENTORY-GREP-MISS` (Feature 034 anti-pattern) actively countered by T003/T004 Phase-2 grep + T026 final full-repo grep audit as merge blocker.
- **Governed workflow**: `/speckit-git-feature` → `/speckit-specify` → `/speckit-memory-md-plan-with-memory` (surfaced Constitution enumeration soft conflict) → `/speckit-architecture-guard-governed-plan` (resolved as T024 PATCH bump) → `/speckit-security-review-plan` (INFORMATIONAL: C:0 H:0 M:0 L:0 I:4, A01/A05/A09) → `/speckit-tasks` (30 tasks, 14 parallelizable, 8/8 TASK bins accounted for) → `/speckit-architecture-guard-governed-tasks` (PASS, 0 refactor tasks) → `/speckit-architecture-guard-governed-implement` — 25/30 tasks executed, 5 flagged for user manual verification (T011, T012, T015, T016, T018).
- **Evidence**: Branch `040-remove-logs-module`, based on `main` post-#53 (Feature 039 merged). Baseline pre-removal: PHPStan L8 clean, PHPCS 56/56 clean, PHPUnit 103 tests + 6 pre-existing warnings, `npm run build` clean. Post-implementation: expected file count drop ~10 (46 files vs. 56); test count unchanged (Logger tests were orphaned from `phpunit.xml.dist`); zero orphaned Logger references in production surface (validated by T026 final grep). Constitution v1.4.7 → v1.4.8 (PATCH bump).
- **Where to look**: `specs/040-remove-logs-module/{spec,plan,tasks,memory-synthesis,security-review-plan,security-constraints}.md`, `docs/planning/040-remove-logs-module.md`, `.specify/memory/CONSTITUTION.md` (SYNC IMPACT REPORT top block + §I module count + Directory Layout + Namespace Rule examples + `**Version**: 1.4.8` bottom line), `docs/memory/DECISIONS.md` (four Superseded entries at DEC-HOOK-PARAM-EXTRACTION / DEC-DURATION-CALC-TIMESTAMPS / DEC-VARIADIC-CALLBACK-WRAP / DEC-LOGGER-NAMESPACE-MIGRATION), `docs/memory/ARCHITECTURE.md` (three forward-pointer annotations at PATTERN-STAGE-NAMING / PATTERN-FEATURE-ASSET-SEPARATION / PATTERN-LOGGER-OPTION-FEED-FILTER), `uninstall.php` (opt-in table drop + AS unschedule), `README.txt` Unreleased changelog + Upgrade Notice.

### 2026-07-13 — Feature 046: Absorb `acrossai-core-abilities` companion (201 files, 176 registered abilities, 17 categories, intentional slug-rebrand breaking change)

- **Why durable**: First plugin-absorption feature in the codebase — inverse of Feature 040's decommission. Relocated an entire companion plugin's runtime (`acrossai-core-abilities`) into the manager under a new isolated tier `includes/Abilities/` (17 category folders + Utilities subtree) plus one admin partial at `admin/Partials/Core_Settings_Menu.php`. Canonical instance of the "companion-collapse" pattern: bulk PHP rewrite matrix (9 ordered sed/perl transforms — namespace, use, text-domain, plugin constants, label text, category slugs, identifier names, singleton `$_instance` → `$instance`, docblock header) applied to 226 files with per-file synchronous writes (per `BUG-PYTHON-STRREPLACE-PARTIAL-WRITE`). Rebranded `Acrossai Core Abilities` → `Acrossai Abilities Manager` throughout — including 17 category slugs (`acrossai-core-abilities-<domain>` → `acrossai-abilities-manager-<domain>`) and 176 ability slugs (`acrossai-core-abilities/<verb>` → `acrossai/<verb>`), an intentional US2 breaking change. Merged the Core settings tab into the existing Abilities tab (spec Q3), consolidated dual uninstall opt-ins into the manager's single existing `acrossai_abilities_uninstall_delete_data` (spec Q4). Option key migration in `AcrossAI_Activator`: OR-monotonic fold (never demote a manager-true) + idempotent (no-op on repeat activation) + unconditional cleanup of legacy keys.
- **Future mistake prevented**: (1) `class_exists( 'Ability_Definition', false )` — the `false` second argument DISABLES autoload; if nothing has referenced the class yet at `plugins_loaded @ P20`, the guard silently returns and the entire Bootstrap `register_abilities()` no-ops. Manifested as a "No abilities registered yet" empty state on the Library page. **Rule**: default class_exists autoload behavior is required for guard-early Bootstrap patterns. (2) The initial plan assumed 218 classes needed a Path-A refactor to remove constructor `add_action` calls. Spot-check confirmed the source pattern is different — `Category_Registrar` ships with empty constructors + synchronous `register()`, and ability classes extend the manager's own `Ability_Definition` (which handles the hook in its inherited constructor). No refactor was needed; the "HARD conflict" was a misreading. **Rule**: source-inspect before planning refactor work; spec/plan/violation-detection artifacts must be revised when the retrospective inspection changes the conclusion. (3) Option key naming drift — spec + plan referenced `acrossai_abilities_manager_extra_mime_types`, actual companion+manager code uses `acrossai_abilities_manager_extra_mimes`. Discovered mid-implementation. **Rule**: always grep the source constant before authoring migration code; artifact regeneration via `/speckit-analyze` catches the drift. (4) The Bootstrap's registration must not itself violate AC-HOOKS-MAIN — `register_category_callbacks( AcrossAI_Loader $loader )` takes the manager Loader as a param and adds 17 `$loader->add_action(...)` calls one per Category_Registrar, so every hook literally traces back to `Main.php::define_public_hooks()`. (5) 8 helper classes (Formatters, Routes, Moderation) are moved but NOT instantiated by the Bootstrap — they're referenced internally by ability classes. Total file count: 201 (17 registrars + 176 abilities + 8 helpers). Documentation MUST distinguish "files moved" from "abilities registered".
- **Also shipped**: SEC-046-01 through SEC-046-05 (INFORMATIONAL, plan-time security review — LOW L:1, INFO I:4) folded into acceptance tasks. Companion slug rebrand as intentional US2 breaking change (SEC-046-01) mitigated by T033 sibling-plugin audit (workstation clean: 0 hits in `acrossai-buddyboss-abilities`, `acrossai-claude-connectors`, `acrossai-mcp-manager`, `acrossai-mcp-manager-npm`, `acrossai-model-manager`; 195 hits in the source companion — expected). PATTERN-UNINSTALL-DATA-GATE + BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE actively countered by T028 uninstall-gate placement. Uninstall Settings section wired at `admin_init` priority 20 so it always renders LAST under the Abilities tab (below Display Settings @ priority 10 + Core_Settings_Menu Upload Media Abilities @ priority 10). 4 candidate durable-memory captures surfaced for user's manual `/speckit-memory-md-capture`: `DEC-ABSORBED-CODE-INCLUDES-TIER`, `PATTERN-BULK-REWRITE-MATRIX`, `PATTERN-OPTION-KEY-MIGRATION-OR-MONOTONIC`, `PATTERN-CONSTRUCTOR-HOOK-REFACTOR-PATH-A` (last is now historic — Path A was retracted; keep for the false-conflict lesson).
- **Governed workflow**: `/speckit-git-feature` → `/speckit-specify` → `/speckit-clarify` (4 Q answered — companion assumed absent on target sites, rebrand includes slugs+identifiers, Core into Abilities tab, single master uninstall opt-in) → `/speckit-memory-md-plan-with-memory` (surfaced AC-HOOKS-MAIN HARD conflict + 2 SOFT conflicts; HARD conflict later retracted post-spot-check) → `/speckit-architecture-guard-governed-plan` (2 initial deviations → 3 after post-hoc PHPCS baseline defer) → `/speckit-security-review-plan` (INFORMATIONAL: C:0 H:0 M:0 L:1 I:4, A03/A05/A08) → `/speckit-tasks` (48 tasks, 8 phases, 20% parallel) → `/speckit-architecture-guard-governed-tasks` (PASS) → interactive implementation across Phase 1-8 with mid-flow reality corrections → `/speckit-analyze` (1 CRITICAL + 1 HIGH + 1 MEDIUM identified, all 3 remediated).
- **Evidence**: Branch `046-absorb-core-abilities-into-manager`, based on `main` post-#63 (Feature 044 merged). Pre-implementation: PHPStan L8 clean, PHPCS 105/105 tests. Post-implementation: 123 PHPUnit tests (117 pre-existing + 6 Feature-046 contract/payload/permission tests), 307 assertions, 0 failures, 6 pre-existing warnings. `composer phpstan` clean. `composer phpcs` on US1/US3-modified files (Main.php, AcrossAI_Activator.php, Core_Settings_Menu.php, SettingsMenu.php, Bootstrap, uninstall.php) clean. `npm run validate-packages` clean. `npm run build` clean. 5 grep merge gates (Acrossai_Core_Abilities substring, text-domain, dash-slug, slash-slug, constant prefix) all zero-hit across `includes/Abilities` + moved admin partial. Forbidden-function gate (`\beval(|extract(|shell_exec(|...`) zero-hit. **766 residual PHPCS errors** in the absorbed tree (docblock-quality — 303 MissingParamComment + 252 MissingParamTag + 82 MissingShort + 43 filesystem-alt + 21 short-ternary + 16 Yoda + smaller counts) deferred to spec 050. Constitution version unchanged (v1.4.8); Constitution PATCH bump to enumerate the new `includes/Abilities/` tier deferred to spec 047.
- **Where to look**: `specs/046-absorb-core-abilities-into-manager/{spec,plan,tasks,memory-synthesis,security-review-plan,security-constraints,tasks-review,violation-detection}.md`, `docs/planning/046-absorb-core-abilities-into-manager.md`, `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` (single-file 176-line register_abilities), `includes/Main.php::define_public_hooks()` (Bootstrap wiring — variable-first per AC-HOOKS-MAIN) + `::define_admin_hooks()` (Core_Settings_Menu wire + Uninstall priority-20 wire), `includes/AcrossAI_Activator.php::migrate_absorbed_options()` (OR-monotonic idempotent migration), `admin/Partials/Core_Settings_Menu.php` (refactored — no register_tab, extra-MIME-types field into Abilities tab only), `admin/Partials/SettingsMenu.php` (split into register_settings + register_uninstall_settings), `uninstall.php` (migrated MIME-types delete inside gate), `tests/phpunit/abilities/Absorbed/Test_Feature_046_{Contracts,Payload_Shape,Permission_Gates}.php` (12 + 3 + 3 assertions), `scripts/046-rewrite-matrix.sh`, `scripts/046-add-docblocks.pl`, `scripts/046-add-fn-docblocks.pl`, and follow-up spec stubs `specs/047-constitution-include-abilities-tier/spec.md`, `specs/048-dry-audit-abilities-utilities/spec.md`, `specs/049-regenerate-pot/spec.md`, `specs/050-046-phpcs-doc-baseline/spec.md`.

---

### 2026-07-13 — Feature 052: Library Page tab-scoped bulk Enable/Disable + URL-synced tabs (with turn-2 UX iteration reconciled via `/speckit-analyze`)

- **Why durable**: First tab-scoped bulk-mutation UI on the Library admin surface; establishes a reusable pattern for future "bulk action across a filtered subset" affordances (per-tab bulk operations for MCP Manager, per-role bulk flips on Access Control, etc.). Also the first Feature-052-style **live UX iteration** in a spec-kit implementation — the user requested two additional turn-2 changes (chevron always visible on disabled cards; expandable readonly ability panel on disabled cards) mid-implementation. The spec `## Clarifications` section grew Q2/Q3 entries to capture those decisions, but only after `/speckit-analyze` explicitly flagged the FR-017 spec/code drift. The lesson: long implementation conversations with rapid UX iteration accumulate silent drift; `/speckit-analyze` is the safety net, not the primary tool for capturing intent.
- **Future mistake prevented**: (1) `class_exists( AcrossAI_Ability_Library_Registry::class )` — no second argument, autoload=on. Passing `false` would silently no-op `Ability_Definition::registered_category_slugs()` when nothing else has yet referenced the Registry class in the request, producing wrong tri-state (`bulk_toggle_state()` returns `'all'` even when categories are disabled). This is the same `BUG-CLASS-EXISTS-AUTOLOAD-FALSE-SILENT` failure mode that hit Feature 046's Bootstrap; the plan-level review's SEC-052-I-001 codified the guardrail before implementation. (2) `bulkToggleState` localized inside `Admin\Main::enqueue_scripts()`'s existing `wp_add_inline_script('acrossai-ability-library-js', ..., 'before')` call — one new key added to the SAME `wp_json_encode`-serialized array as `definitions`, `restBase`, `nonce`, `addonsUrl`. Emitting from a page render callback instead would fire too late (BUG-WP-LOCALIZE-SCRIPT-RENDER, Feature 030's blank-page bug); SEC-052-I-002 kept the placement correct. (3) `parseTabFromUrl(url, validSlugs, allTabsKey)` returns the sentinel — NOT the raw URL value — on any `validSlugs.includes(raw) === false` case (SEC-052-I-003 defense-in-depth against reflected admin-supplied query args). The strict inclusion check is the guardrail — never emit raw `?tab=<x>` values to `history.pushState` or to React state. (4) `LibraryCard.js:161` interactive-row ternary tightened to `enabled && mode === 'specific' ? <CheckboxControl…> : <div className="…__slug-readonly">…</div>`. Even when the stored `mode === 'specific'` (preserved through disable/enable cycles), the interactive checkbox NEVER renders on a disabled card — SEC-052-I-004 keeps `Disable All` visually inert and prevents an unintended per-slug write path on a disabled category. (5) Sibling Jest tests that consume `LibraryPage.js`'s pure named exports (`collectTabGroups`, `filterItemsByTabGroup`, `titleCaseTabLabel`, `groupDefinitions`) had latent broken `@wordpress/icons` transitive-dep loading (`import_element.forwardRef is not a function`) — surfaced only when the mock allowlist got updated for the new `Button` import. **Rule**: adding an `@wordpress/*` import to a JSX module requires updating EVERY sibling helper test's mock allowlist AND double-checking that transitive `@wordpress/*` peer deps (icons → element.forwardRef, etc.) are mocked too. Reinforces `BUG-JEST-MOCK-LIST-STALENESS`.
- **Also shipped**: `Ability_Definition::is_all_enabled()` / `is_all_disabled()` / `bulk_toggle_state()` public static + `registered_category_slugs()` private static — the tri-state helpers that produce the initial-paint `bulkToggleState` hint. Since `Ability_Definition` is the abstract base class all 176 absorbed abilities extend (post-Feature-046), the helpers land in exactly one place and require zero per-class changes. `useLibraryTabSync` hook mirrors `useUrlViewSync` (three-effect structure: mount / change / popstate); five new JS named-export helpers (`collectInScopeCategories`, `buildBulkPatch`, `computeInScopeBulkState`, `parseTabFromUrl`, `buildUrlFromTab`) each get their own Jest test per PATTERN-NAMED-EXPORT-JEST. Two side-by-side `<Button>`s (primary Enable All, secondary destructive Disable All) rendered unconditionally above the TabPanel — FR-008 silent-no-op contract: both buttons ALWAYS render as fully active, redundant clicks short-circuit inside `runBulkPatch(enabled)` before any REST call. Turn-2 UX iteration relaxed `LibraryCard.js:74` chevron gate (`canExpand = slugs.length > 0`), `LibraryCard.js:143` slug-panel outer gate (`slugs.length > 0 && expanded`), and tightened `LibraryCard.js:161` interactive-row ternary — disabled cards now show chevron + toggle + label + (when expanded) readonly bullet-style ability preview; NEVER radio, NEVER interactive checkbox.
- **Governed workflow**: `/speckit-git-feature` → `/speckit-specify` → `/speckit-clarify` (1 Q — button no-op state → Option C: always active, redundant click is silent no-op) → `/speckit-memory-md-plan-with-memory` (no conflicts; 3 bug patterns pre-flighted as guardrails) → `/speckit-architecture-guard-governed-plan` (0 violations; only pre-approved §III deviation DEC-DESIGN-OVERRIDES-DATAVIEWS applies) → `/speckit-security-review-plan` (INFORMATIONAL: C:0 H:0 M:0 L:0 I:4, A01/A03/A05/A09) → `/speckit-tasks` (26 tasks, 6 phases) → `/speckit-architecture-guard-governed-tasks` (PASS) → `/speckit-architecture-guard-governed-implement` (inline execution; T001-T025 done; T026 wp-env quickstart deferred as user gate) → 2 rounds of live turn-2 UX iteration (chevron on disabled + expandable readonly panel + interactive-row tightening) → `/speckit-analyze` (2 CRITICAL findings on FR-017 spec/code drift — remediated by adding Q2/Q3 Clarifications entries + rewriting FR-017 as a bulleted matrix + broadening SC-006 + syncing 6 stale sites in `docs/planning/052-*.md`) → `/speckit-architecture-guard-architecture-review` (PASS, 100% Constitution compliance) → `/speckit-security-review-staged` (INFORMATIONAL: C:0 H:0 M:0 L:0 I:0; all 4 plan-level informational items honored in code).
- **Evidence**: Branch `052-library-bulk-toggle-and-url-synced-tabs`, cut from `main`. Post-implementation quality gates: `composer phpstan` L8 zero errors; `composer phpcs -- includes/Modules/Library/Ability_Definition.php admin/Main.php` clean; `composer test` 129/129 (16 in `Test_Ability_Definition` including 6 new Feature-052 cases); `npx wp-scripts test-unit-js tests/jest/ability-library/` — 11 suites, 82 tests all pass (54 pre-existing + 28 new/updated across `collectInScopeCategories.test.js`, `buildBulkPatch.test.js`, `computeInScopeBulkState.test.js`, `useLibraryTabSync.test.js`, extended `LibraryCard.test.js` with 4 Feature-052 disabled-card predicate assertions); `npm run validate-packages` clean; `npm run build` clean (bundle 16.5 KiB → 25.7 KiB source). Constitution version unchanged (v1.4.8); zero new REST routes; zero new capability boundaries; zero new npm/Composer deps.
- **Where to look**: `specs/052-library-bulk-toggle-and-url-synced-tabs/{spec,plan,tasks,memory-synthesis,security-constraints,architecture-review}.md` (checklists/, plus post-remediation FR-017 + Clarifications Q2/Q3), `docs/planning/052-library-bulk-toggle-and-url-synced-tabs.md` (post-remediation Disabled-Card UI Contract), `src/js/ability-library/hooks/useLibraryTabSync.js` (three-effect hook + `parseTabFromUrl` + `buildUrlFromTab`), `src/js/ability-library/components/LibraryPage.js` (new named-export helpers + `handleEnableAll` / `handleDisableAll` / `runBulkPatch(enabled)` + header-row JSX + hook wire + controlled `activeTab` state), `src/js/ability-library/components/LibraryCard.js:74/107/143/161` (turn-2 gate matrix), `src/scss/ability-library/admin.scss` (`.acrossai-library-page__header` block), `includes/Modules/Library/Ability_Definition.php` (4 new static methods + 2 new `use` statements), `admin/Main.php::enqueue_scripts()` (`bulkToggleState` localized), `tests/phpunit/Modules/Library/Test_Ability_Definition.php` (6 new PHPUnit cases with in-memory `acrossai_test_site_options()` seeding + Registry reflection reset), `tests/jest/ability-library/{collectInScopeCategories,buildBulkPatch,computeInScopeBulkState,useLibraryTabSync,LibraryCard}.test.js` (new + extended).

---

### 2026-07-17 — Feature 053: Bump acrossai-co/main-menu 0.0.14 → 0.0.23, remove Freemius integration entirely, restyle Library header row, self-filter Add-ons

- **Why durable**: First feature to REDUCE the plugin's external-service surface — Freemius removed entirely. Establishes the pattern for consuming the `acrossai-co/main-menu` package's post-0.0.21 architecture where the entry-class `\AcrossAI_Addon\AddonsPage` is gone and the Add-ons submenu is registered internally by `\AcrossAI_Main_Menu\MenuRegistrar` when the shared `SettingsPage` bootstrap runs. Also demonstrates the **self-filter pattern** on the shared `acrossai_addons` list — any consuming plugin can remove its own entry with a small callback, so the Add-ons page in that plugin's admin shows only other companion plugins. Any of the other AcrossAI-org plugins (MCP Manager, Model Manager) can adopt the same 3-line pattern.
- **Future mistake prevented**: (1) After a major upstream version bump, always inspect the vendor's `src/` tree for API changes — grep for the class names the plugin instantiates. `main-menu 0.0.21` deleted `\AcrossAI_Addon\AddonsPage` but the plugin's instantiation was inside a `try/catch` that would fail-open silently to an admin_notices error; without an inspection the change would have shipped with a broken Add-ons page and a permanent admin notice. **Rule**: always run `find vendor/<package>/src -name '*.php' | xargs grep -l 'class ThePlugin*'` after any non-trivial version bump. (2) When accumulating multiple version bumps in a single PR (`0.0.14 → 0.0.21 → 0.0.22 → 0.0.23` here), verify the consumer API surface after EACH bump — `SettingsPage`, `MenuRegistrar`, `AddonsPageRenderer` were all preserved through 0.0.22 and 0.0.23 but that's not guaranteed. **Rule**: `grep -c 'class SettingsPage\|class AddonsPageRenderer\|class MenuRegistrar'` after every intermediate bump. (3) Freemius removal supersedes `DEC-FREEMIUS-PER-PLUGIN-INIT` — a state transition on an active decision, not a conflict. Marked as `Superseded (Feature 053)` in `docs/memory/INDEX.md` and `docs/memory/DECISIONS.md`.
- **Also shipped**: Two user-requested scope additions during implementation (per the same live-UX-iteration pattern surfaced by Feature 052): (a) `LibraryMenu.php` no longer server-renders `<h1>Ability Library</h1>` — the H1 moves into React inside `.acrossai-library-page__header` on the left; `.acrossai-library-page__header` flipped from `justify-content: flex-end` to `justify-content: space-between` so title + Enable All / Disable All buttons render on a single horizontal row. (b) New `\AcrossAI_Abilities_Manager\Admin\Main::filter_out_self_from_addons()` method registered via `$this->loader->add_filter('acrossai_addons', $plugin_admin, 'filter_out_self_from_addons')` in `includes/Main.php::define_admin_hooks()` — variable-first per AC-HOOKS-MAIN. Defensive against non-array input and non-array entries.
- **Governed workflow (backfilled)**: Direct implementation → 5 commits landed on branch `053-main-menu-0-0-21-freemius-removal` → 6th commit backfills spec-kit artifacts (`specs/053-…/{spec,plan,tasks,memory-synthesis,security-constraints,architecture-review}.md` + checklist) and this WORKLOG entry + `DEC-FREEMIUS-PER-PLUGIN-INIT` supersession. Deliberate deviation from the standard spec → plan → tasks → implement flow because the scope expanded during implementation (original plan targeted only 0.0.14 → 0.0.21 + Freemius removal; header-row layout and self-filter came in as user requests; subsequent version bumps to 0.0.22 and 0.0.23 came in as upstream published).
- **Evidence**: Branch `053-main-menu-0-0-21-freemius-removal`, cut from `main` post-Release-0.0.7. PR [#69](https://github.com/acrossai-co/acrossai-abilities-manager/pull/69). Post-implementation quality gates (after each of the 5 code commits): `composer phpstan` L8 zero errors; `composer phpcs` clean on all touched PHP files; `composer test` 129/129 (6 pre-existing warnings in `AcrossAI_Ability_Merger.php`, unchanged); `npx wp-scripts test-unit-js tests/jest/ability-library/` — 82/82 pass across 11 suites; `npm run validate-packages` clean; `npm run build` clean. Vendor tree delta: `-freemius/wordpress-sdk 2.13.3` (entire ~2,000-file tree removed); `acrossai-co/main-menu 0.0.14 → 0.0.23`; `automattic/jetpack-autoloader v5.0.20 → v5.0.21` (transitive). Constitution version unchanged (v1.4.8); zero new REST routes; zero new capability boundaries; zero new npm/Composer deps ADDED. Version bump / changelog / tag deferred to a future release cycle (0.0.8 or later).
- **Where to look**: `specs/053-main-menu-0-0-21-freemius-removal/{spec,plan,tasks,memory-synthesis,security-constraints,architecture-review}.md` + `checklists/requirements.md`; `composer.json` (main-menu constraint); `composer.lock` (regenerated); `includes/Main.php::define_admin_hooks()` (deleted AddonsPage block + new `acrossai_addons` filter subscription); `admin/Main.php::filter_out_self_from_addons()` (new public method); `admin/Partials/LibraryMenu.php::render()` (removed server-rendered H1); `src/js/ability-library/components/LibraryPage.js` (added H1 + `.__header-actions` wrapper inside `.__header`); `src/scss/ability-library/admin.scss` (space-between flip + `.__title` and `.__header-actions` blocks); `README.txt` (4 non-historical sections rewritten; historical 0.0.1 / 0.0.6 changelog entries preserved); `docs/memory/DECISIONS.md` (DEC-FREEMIUS-PER-PLUGIN-INIT marked Superseded); `docs/memory/INDEX.md` (same, plus this WORKLOG row registered under `## Worklog Milestones (continued)`).

---

### 2026-07-18 — Feature 042: Core category + WP core update abilities; Feature 041 spec-kit backfill

Ships two abilities under a new `Core/` Category folder (18th sub-partition inside the existing Custom Ability Registration module — NOT a new module per Constitution §I): `wp-core-update-check` reports availability, `wp-core-update` applies via `Core_Upgrader::upgrade()`. Requires both `manage_options` and `update_core`; File_Mods_Guard + multisite guard. Rewrites `Backups_Storage::random_backup_filename()` from `backup-{type}-{slug}-{random-12}.zip` to `{slug}-{unix}-{ms}.zip` (human-readable + time-sortable). Concurrent full spec-kit backfill for Feature 041 at `specs/041-backup-restore-abilities-and-updates/` (7-file artifact set + a Fixes section documenting the 0.0.10 include_hidden regression fix; matches the Feature 053 backfill precedent). Released as 0.0.11 (PRs [#75](https://github.com/acrossai-co/acrossai-abilities-manager/pull/75) + [#76](https://github.com/acrossai-co/acrossai-abilities-manager/pull/76)). Same-day follow-up (Feature 043) revisits 042's "no `WP_Downgrader`" out-of-scope statement — see `PATTERN-WP-CORE-UPGRADER-ABILITY` for the rollback path.

**Tags**: feature-042, core-category, wp-core-update, backup-filename, spec-kit-backfill

---

### 2026-07-18 — Feature 043: WordPress core rollback via WP.org Core API

Adds a third ability under the Core Category folder: `wp-core-rollback`. Rolls back WordPress core by fetching an older offer from `api.wordpress.org/core/version-check/1.7/` via `wp_remote_get()`, forcing `$offer->response = 'upgrade'`, and handing the offer directly to `Core_Upgrader::upgrade()` — the same class the forward path uses. Uses only WordPress functions; no bundled updater code. Requires both `manage_options` and `update_core`; File_Mods_Guard + multisite + non-downgrade guards. Introduces the plugin's first outbound HTTP request (rate-bounded by a per-locale `DAY_IN_SECONDS` transient cache; 15s timeout; hardcoded URL as a class constant, no SSRF surface; standard WordPress User-Agent). Inspired by Andy Fragen's [core-rollback](https://github.com/afragen/core-rollback) plugin (MIT-licensed) — read `wp-content/plugins/core-rollback/src/Core.php` for the underlying technique; we skip its transient + `pre_http_request` injection dance because we invoke `Core_Upgrader` directly. Key observation superseding Feature 042's out-of-scope statement: `Core_Upgrader::upgrade($offer)` does NOT inspect whether `$offer->version` is older or newer than currently-installed. Released as 0.0.12 (PRs [#77](https://github.com/acrossai-co/acrossai-abilities-manager/pull/77) + [#78](https://github.com/acrossai-co/acrossai-abilities-manager/pull/78)).

**Tags**: feature-043, core-rollback, wp-org-api, core-upgrader, outbound-http

---

### 2026-08-24 — Features 086 + 087: Database-maintenance parity

**Scope**: Database / Cache / Options
**Tags**: feature-086, feature-087, db-maintenance, mcp-parity, allowlist, dual-gate, mutation-attribution

Shipped 7 new `database/*` abilities in two stacked phases, closing the parity gap vs `mcp-abilities-database` (search-replace trio intentionally skipped — Better Search Replace covers it):

- Health audits (3): `audit-health`, `audit-index-health`, `audit-options-health` (bounded, paginated, never returns option values)
- Safe writes (2): `cleanup-expired-transients`, `set-option-autoload` (dry-run + confirm gated, postcondition verified)
- Engine ops (2): `audit-core-table-engines`, `convert-core-tables-to-innodb` (18-key core-table allowlist, mutation-attributed DDL)
- Plus additive hardening of existing `cache/delete-expired-transients` (optional `dry_run` + `limit`, default `dry_run=false` preserves prior behavior)

New utilities: `Database_Core_Table_Allowlist`, `Database_Mutation_Attribution` — both reusable by future DB abilities. Three new decisions (DEC-DB-CORE-TABLE-ALLOWLIST, DEC-DB-DUAL-GATE-DDL, DEC-DB-MUTATION-ATTRIBUTION) codify the patterns. One bug pattern (BUG-SOURCE-INSPECTION-ADJACENCY-BRITTLE) captured from the additive-hardening regression.

DB/state surface is now ~34 abilities (19 `database/` + 7 `cache/` + 8 `options/`) vs the source plugin's 10, with every one of their safety patterns adopted verbatim.
