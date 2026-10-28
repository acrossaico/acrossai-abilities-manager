# Implementation Plan: Rank Math Ability Suite

**Feature Branch**: `069-rank-math-abilities`
**Spec**: [spec.md](./spec.md)
**Research**: [research.md](./research.md)
**Constitution**: v1.4.8

## Summary

Register 61 abilities that expose the Rank Math functionality its own 13 abilities do not cover,
following the Feature 067 (Elementor) ability-suite pattern exactly: one directory of
`Ability_Definition` subclasses, one `Category_Registrar`, a static-only helper layer holding every
third-party reference, and two lines of wiring in `AcrossAI_Core_Abilities_Bootstrap`.

The distinguishing constraint is that Rank Math's settings sanitizer silently destroys multi-line
content for any field it has not been told the type of (research F2). The design answer is a
declarative field-spec registry plus a single write path, so that a mis-typed field is impossible by
construction rather than caught by review.

## Technical Context

- **Language**: PHP 8.1+ (dev environment runs the Local-bundled PHP 8.2.29)
- **WordPress**: 6.9+, tested to 7.0
- **Target plugin**: `seo-by-rank-math` — detected via `class_exists( '\RankMath\Helper' )`
- **Testing**: PHPUnit 10.5, source-inspection pattern (no Rank Math needed in CI)
- **Static analysis**: PHPStan level 8 — baseline verified clean, and no `ignoreErrors` entry is
  needed for `\RankMath\*` (research: host-plugin facts)
- **PHPCS**: `includes/Abilities/*` remains excluded per the Feature 046 baseline; match the compact
  array formatting used in `includes/Abilities/Elementor/`

## Constitution Check

| Principle | Status | Notes |
|---|---|---|
| I. Modular Architecture | PASS | One directory per domain (`includes/Abilities/RankMath/`), static-only helpers in `includes/Abilities/Utilities/RankMath/`. No new module orchestrator, no `includes/Base/`. Hook wiring stays in the existing bootstrap, which is already the single registration point for ability suites. |
| II. WordPress Standards Compliance | PASS | `wp_remote_get()` for the loopback (never `file_get_contents`), Action Scheduler respected rather than reimplemented, no direct SQL — all data access goes through Rank Math's own `DB`/`Helper` classes. Text domain `acrossai-abilities-manager` on every string, `/* translators: */` before every `sprintf`. |
| III. User-Centric Design | PASS | Abilities are discoverable and self-describing: `get-settings-panel` returns field types and defaults so a client never has to guess a payload shape; `get-status(panel=tools)` returns the live tool catalogue; every error carries a distinct `error_code` and an actionable message naming the flag, setting, or module required. |
| IV. Security First | PASS | Uniform `manage_options` floor `AND` Rank Math's granular `rank_math_*` cap — strictly tighter than either model alone. Per-object `edit_post` / `edit_user` / `edit_terms` checks inside `run()` as defence in depth. Six protected settings keys hard-denied. Destructive operations require explicit `confirm: true`. All input sanitized at the registry boundary before reaching Rank Math. |
| V. Extensibility Without Core Modification | PASS | No Rank Math file is modified. Where a needed method is `private`, it is re-implemented in our helper layer with an `@see` citation (research F5) rather than accessed by Reflection. One documented filter, `acrossai_abilities_manager_rank_math_permission`, lets site owners relax the capability policy. |
| VI. Reusability & DRY | PASS | Two abstract bases carry the shared contract: `Base_Rank_Math_Ability` is the sole assembler of `ability()` and the sole enforcer of `execute()` ordering; `Base_Settings_Write_Ability` drives the three panel writers. Twelve helper classes are the only place a `\RankMath\*` symbol appears. Enum consolidation replaced ~19 would-be duplicate classes. |
| VII. Definition of Done | PASS | PHPCS (scoped), PHPStan level 8, the `feature-069-unit` suite, a security review, and admin-UI verification at Batches 1, 2 and 7. Integration checks that genuinely need Rank Math installed are documented in `quickstart.md` and skipped in CI rather than faked. |

## Architecture

### Registration chain

1. `Category_Registrar::register()` on `wp_abilities_api_categories_init`, guarded on
   `class_exists( '\RankMath\Helper' )`.
2. `AcrossAI_Core_Abilities_Bootstrap::register_abilities()` instantiates the 61 classes inside a
   `class_exists` gate.
3. Each constructor (inherited from `Ability_Definition`) hooks `acrossai_abilities_api_init`.
4. Registry collects at `init` P99; Processor calls `wp_register_ability()` at
   `wp_abilities_api_init` P5.

**Deliberate divergence from Elementor**: entitlement-backed abilities (Content AI, AI Visibility) are
registered *unconditionally* whenever Rank Math is present and gated at runtime, unlike
`register_elementor_pro_abilities()` which does not register at all when Pro is absent. The bootstrap
comment must state this, or a future maintainer will "fix" it into the Elementor shape.

### Architecture rule — call Rank Math's API, never its database

Rank Math owns its storage format. Every read and write in this suite goes through its
PHP API; nothing touches its tables. Reading the database directly would give up its
sanitization, its side-effect hooks (rewrite flushing, `rank_math/module_changed`) and
its caching, and would break silently whenever it changes a column or a serialisation
format.

Concretely: no `$wpdb` and no SQL anywhere in the suite; module state derives from
`Helper::is_module_active()` rather than the `rank_math_modules` option, so a stale
slug from a removed module is not reported as active; settings write through
`Option_Center::save_settings()`.

**One documented exception.** Instant Indexing is not in `save_settings()`'s internal
`$map`, so no API exists to call — Rank Math writes that blob with a bare
`update_option()` itself (`instant-indexing/class-api.php:379`). `Settings_Writer` does
the same, after validating and typing the payload. It is the only direct option write,
and the only file exempted from the rule.

Post-meta **writes** use `update_post_meta()` because Rank Math ships a getter
(`Helper::get_post_meta()`) but no setter — there is no counter to go to.

Enforced by `Test_Rank_Math_Architecture::test_no_direct_database_access()`,
`::test_rank_math_options_are_not_accessed_directly()` and
`::test_module_state_uses_the_rank_math_api()`.

### Helper layer — `includes/Abilities/Utilities/RankMath/` (12 static final classes)

| Class | Responsibility |
|---|---|
| `Rank_Math_Guard` | Availability, module, PRO, account, credit, console and confirmation guards; `has_cap()`; the `can()` permission factory; the `ok()`/`fail()`/`error()` envelope helpers |
| `Settings_Registry` | Declarative field-spec tables for 20 panels, `DENIED_KEYS`, `field_types_for()`, `validate()` |
| `Settings_Writer` | The only caller of `Option_Center::save_settings()` |
| `Instant_Indexing_Repository` | IndexNow settings, submit, log, key |
| `Redirections_Repository` | Update, status change, stats, list, find, create + the ported Apache/Nginx serializers |
| `Log_Repository` | The 404 log table |
| `Role_Capability_Repository` | Read and reset of Rank Math role capabilities |
| `Maintenance_Tools` | Catalogue, static dispatch map, response normalization |
| `Analytics_Repository` | Synthetic `WP_REST_Request`, explicit date-range setup, connection guards |
| `Module_Repository` | Validated module list, state change with rewrite flush |
| `Routes_Repository` | llms.txt / rewrite inspection and local preview |
| `Post_Meta_Repository` | `rank_math_*` postmeta, primary terms, schema deletion, the content-audit query |

### Ability contract

`Base_Rank_Math_Ability::execute()` enforces, structurally:

```
1 assert_available()      Rank Math present
2 assert_module()         required module active
3 assert_confirmed()      confirm:true for destructive ops
4 run( $input )           per-field validation + domain work
5 ok() / fail()           envelope
```

## Project Structure

```
includes/Abilities/RankMath/
├── Category_Registrar.php
├── Base_Rank_Math_Ability.php
├── Base_Settings_Write_Ability.php
└── <Verb>_<Noun>.php                   × 61

includes/Abilities/Utilities/RankMath/
└── <Repository|Registry|Guard>.php     × 12

tests/phpunit/abilities/
└── Test_Rank_Math_*.php                × 77
```

Edited: `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php`, `phpunit.xml.dist`,
`acrossai-abilities-manager.php`, `README.txt`. **Not** edited: `includes/Main.php` (Pattern A needs
no change there; the version-constant fix landed separately in `8cb5f18`), `phpstan.neon.dist`.

## Batches

| Batch | Content |
|---|---|
| 0 | PHPStan baseline (clean), version-desync fix (`8cb5f18`), spec-kit artifacts |
| 1 | `Category_Registrar`, `Rank_Math_Guard`, `Base_Rank_Math_Ability`, bootstrap wiring, testsuite block, `get-status` — then verify the tab in wp-admin |
| 2 | `Settings_Registry`, `Settings_Writer`, `Base_Settings_Write_Ability`, abilities 1–6 |
| 3 | Instant Indexing, modules, sitemap, routes |
| 4 | Redirections (incl. serializer port), 404 logs, role capabilities |
| 5 | Status, maintenance tools, backups, import/export, cached SEO analysis |
| 6 | Analytics, post-level content, schema status, rendered head |
| 7 | Content AI, AI Visibility, release 0.0.28 |

Hard ordering: Batch 1 before all; Batch 2 before any panel-reading ability; `Rank_Math_Guard` before
every `execute()`. Batches 3–7 are otherwise independent.

## Complexity Tracking

| Decision | Simpler alternative | Why rejected |
|---|---|---|
| `Settings_Registry` hand-mirrors Rank Math's CMB2 field definitions | Pass field types through from the caller | The caller cannot know them either, and omitting them silently destroys multi-line content (F2). Unknown-key rejection means a Rank Math field rename fails loudly instead of corrupting data |
| Enum consolidation (one ability with a `tool`/`panel`/`report` enum) | One ability per endpoint | ~19 extra classes differing only in which method they call. The enum members are asserted in tests so they cannot drift from the dispatch map |
| Ported Apache/Nginx serializers | Call Rank Math's exporter | Its formatters are `private` and the public entry point reads `$_GET`, calls `check_admin_referer()`, and `exit`s (F5) |
| HTTP loopback for the rendered head | Call the handler in-process | It calls `remove_all_actions()` and re-runs the main query, corrupting any later ability in the same request (F4) |
| Static dispatch map for maintenance tools | `apply_filters( 'rank_math/tools/{id}' )` | The filters are only registered during an actual `/toolsAction` REST request (F3) |
| Two abstract bases | One base, or none | `Base_Audit_Ability` precedent: 27 Elementor subclasses off one base. Centralising `ability()` assembly is what guarantees `tab_group` correctness — the Feature 078 regression class |
