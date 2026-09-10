# Implementation Plan: Ability Group Tabs

**Branch**: `101-ability-group-tabs` | **Date**: 2026-09-10 | **Spec**: [spec.md](./spec.md)
**Status**: Implemented — commits `4984d23`, `5a8aed5`, `9614520`

> **Provenance.** Written after implementation to complete the artifact set, and describing what was
> built. The technical decisions below were made during planning and are recorded here in the form
> they were argued, including the alternatives rejected and why.

## Summary

Replace the Integrations screen's 18 tabs — one of which held 105 of ~450 abilities — with thirteen
task groups, assigning `tab_group` per ability rather than per folder. Shorten category slugs from
`acrossai-abilities-manager-*` to `acrossai-*` behind a migration, because that value is a persistence
key. Fix a pre-existing bug found during verification: the Debugging category was never registered, so
its seven abilities had never existed at runtime.

## Technical Context

**Language**: PHP 8.1+ · JavaScript (`@wordpress/*`)
**Storage**: `acrossai_library_config` site option (keyed by category); `{prefix}acrossai_abilities.category`
**Testing**: PHPUnit against the stub bootstrap in `tests/bootstrap.php`; Jest via `wp-scripts`
**Scale**: ~450 abilities across 25 categories in 26 folders; 229 `tab_group` declarations reassigned; 485 files touched by the slug rename

**Load-bearing platform behaviour** (verified, not assumed):

| Behaviour | Consequence |
|---|---|
| `tab_group` has no allow-list, no server-side validation, and is never persisted | Regrouping is free; a wrong value fails silently |
| `WP_Abilities_Registry::register()` rejects an unregistered category with `_doing_it_wrong()` + `return null` | Renaming categories without migrating is destructive; an unwired registrar deletes its abilities |
| `WP_Ability` accepts exactly nine properties and discards other top-level args | `meta` is the only extension point; per-ability fields must nest there |
| Tab labels derive from the identifier; no separate label field exists (spec 037 FR-007) | Identifiers must title-case readably — no ampersands |
| `LibraryCard` renders "Tab membership" chips (Feature 055) | Cards spanning groups are a designed behaviour |

## Constitution Check

| Principle | Assessment |
|---|---|
| I. Modular Architecture | Pass — one new class in the existing Library module; no cross-module reach |
| II. WordPress Standards | Pass — PHPCS clean. `/scripts` excluded: dev tooling, already in `.distignore`, and WPCS rules for runtime code (`file_put_contents`, escaping `echo`) do not apply to a CLI generator |
| III. User-Centric Design | Pass — tabs named for tasks; split cards name every group they belong to |
| IV. Security First | Pass — no new input surface. The migration's one direct query is prepared with exact-match `WHERE`, driven by an explicit allow-list rather than a prefix `LIKE` |
| V. Extensibility Without Core Modification | Pass — no ability class was edited beyond its own declaration |
| VI. Reusability & DRY | Pass — the group map lives once, in the test that pins it; the migration's owned list lives once and is verified against source |
| VII. Definition of Done | Pass — PHPCS 0, PHPStan level 8 0, tests written and passing, all classes prefixed |

## Project Structure

```
includes/
  Abilities/
    <Folder>/*.php                    229 tab_group values reassigned
    Block/{8 files}                   moved from Content/, category + sub_group updated
    AcrossAI_Core_Abilities_Bootstrap.php   Debugging registrar wired; 8 instantiations repointed
  Modules/Library/
    AcrossAI_Category_Slug_Migration.php    new — rewrites both stored value sites
  AcrossAI_Activator.php              migration called on activation
  Main.php                            migration wired to admin_init P1

src/js/ability-library/components/LibraryPage.js   pinned-first tab: core → content

scripts/generate-abilities-inventory.php           new — inventory generated, not hand-kept

tests/phpunit/
  Modules/Library/Test_Ability_Group_Map.php        new — the canonical map
  Modules/Library/Test_Category_Slug_Migration.php   new — allow-list, ordering, idempotency
  abilities/Test_Category_Registrar_Wiring.php       new — both-direction wiring guard
```

## Key decisions

### Group assignment is per ability, not per category

Per-category was considered first and rejected. It would have forced whole categories into one group,
reproducing the original problem: Settings is seven branding abilities plus four permalink abilities,
and either home is wrong for half of it. Per-ability assignment is only viable because the card
component already displays each card's full group membership — without that, half a card would appear
in a tab with nothing indicating the rest existed, which was precisely the pre-existing Settings and
Site Health defect.

**Cost, accepted:** the enable toggle is keyed by category, so a card spanning two groups shows one
shared control in both places. Turning `Block` off from Appearance also removes its authoring
abilities from Blocks. Removing this would require splitting those five categories in two, which is a
config migration and was left out of scope.

### One file move, under an objective test

Eight abilities in `Content/` registered as `blocks/*` while declaring the `content` category — the
identifier and the folder contradicted each other. They moved to `Block/`, keeping their identifiers,
so no client was affected.

Three superficially similar candidates were rejected under the same test: `media/*-upload-mime-types`,
`cache/flush-rewrite-rules` and `database/cleanup-expired-transients` all have identifiers matching
their folders. Moving them would either relocate the inconsistency or force an identifier rename,
which `README.txt` records as breaking with no compatibility alias. They were handled by group
assignment instead — which is what per-ability assignment is for.

### The slug rename is a migration, not a sweep

The category value keys the saved preferences and is recorded against operator-created abilities. Two
stores, rewritten from both activation and `admin_init` — an in-place upgrade never re-activates, so
activation alone would miss most real upgrades.

The rewrite is driven by an explicit list of 25 owned identifiers. Thirty distinct strings share the
retired prefix; five are not categories at all — two asset handles, a style name, a docblock naming a
dropped category, and a test fixture. A prefix regex would have corrupted all five, which is the
failure mode Feature 058 already recorded. `content` also shadows `content-search`, so replacements
are applied longest-first.

### Every rule carries a test that was verified by breaking it

The 105-ability bucket formed one copy-paste at a time *despite* a documented pattern warning about
that exact failure. A rule with no failing test is a suggestion. Each guard here was confirmed by
introducing the fault it exists to catch — misfiling an ability, un-wiring the Debugging registrar,
and checking the migration's coverage scan was not passing against an empty set. It was: it found 24
of 25, because one category is declared as a constant without quotes.

## Complexity Tracking

| Deviation | Why | Alternative rejected |
|---|---|---|
| A direct database query in the migration | One-off bulk column rewrite on a plugin-owned table; no query-layer method exists for it | Loading and re-saving every row — slower, and no safer given the prepared exact-match `WHERE` |
| `/scripts` excluded from PHPCS | Dev tooling, never shipped (already in `.distignore`); WPCS filesystem and escaping rules target runtime code | Contorting a CLI generator to satisfy rules written for a different context |
| A bug fix inside a refactor branch | Found while verifying this work, and it made two of this feature's own artifacts wrong — the inventory counted seven abilities that did not exist | Deferring, at the cost of shipping an inventory known to be inaccurate |
