# Tasks: Ability Group Tabs

**Branch**: `101-ability-group-tabs` | **Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)
**Status**: All complete — commits `4984d23`, `5a8aed5`, `9614520`

> **Provenance.** Reconstructed after implementation to complete the artifact set. The ordering below
> is the order the work was actually done, and every item is checked because every item shipped.

## Format: `[ID] [P?] [Story] Description`

- `[P]` — could run in parallel (different files, no dependency)
- `[US1]`…`[US4]` — the user story from spec.md this serves

## Path Conventions

Plugin root is the repository root. `includes/` is PHP, `src/js/` is JavaScript, `tests/phpunit/` and
`tests/jest/` are tests.

---

## Phase 1: Establish the map

- [x] **T001** `[US1]` Enumerate every `tab_group` declaration and its folder, to size the change and
  find folders declaring more than one value. Found two: `Settings/` at 10 `core` + 1 `settings`, and
  `SiteHealth/` at 3 + 3.
- [x] **T002** `[US1]` Read every ability's slug, label and sub-group to assign groups by job rather
  than folder name. This is what moved Cron out of a proposed "Performance" group — all sixteen of
  its abilities manage scheduled tasks, which is automation, not speed.
- [x] **T003** `[US2]` Identify categories serving more than one job. Found five: Block, Settings,
  Media, Cache, Database.
- [x] **T004** `[US1]` Verify the platform imposes no constraint on group identifiers — no
  allow-list, no validation, never persisted — and confirm the label is derived from the identifier,
  so no identifier may contain an ampersand.

## Phase 2: Foundational — the one file move

**Blocking**: T005 must precede the group sweep so the moved files are swept in their new home.

- [x] **T005** `[US2]` Move eight abilities from `includes/Abilities/Content/` to
  `includes/Abilities/Block/`, updating namespace, category and sub-group. Identifiers unchanged.
  Justified by the objective test: their identifier already contradicted their folder.
- [x] **T006** `[US2]` Repoint the eight instantiations in `AcrossAI_Core_Abilities_Bootstrap.php`.
- [x] **T007** `[US2]` Repoint the one namespaced import in `Test_Outline_Post_Blocks_Behavioural.php`.
- [x] **T008** `[US2]` Repoint hardcoded source paths in ten test files. **This step was not in the
  original plan** — the impact analysis had been scoped to `tab_group`, which is unpersisted and
  unasserted, and was silent about the file move added later. 89 tests failed before this was done.

## Phase 3: User Story 1 — thirteen groups

- [x] **T009** `[US1]` Reassign 229 `tab_group` declarations, driven per folder with per-ability
  exceptions for the five split categories. Dry-run first; counts matched the map exactly before
  applying.
- [x] **T010** `[US1]` Repoint the pinned-first tab from `core` to `content` in `LibraryPage.js`, and
  rewrite the surrounding comment, which described the retired value.
- [x] **T011** `[US1]` Update the Jest tests pinning the old pinned tab, and add one asserting `core`
  is no longer special.
- [x] **T012** `[US1]` Extend the shared PHP/JS label fixture with all thirteen group identifiers, so
  a future group whose name renders badly fails a test.
- [x] **T013** `[US1]` Rebuild JavaScript assets.

## Phase 4: User Story 3 — stored values survive

- [x] **T014** `[US3]` Enumerate every distinct string sharing the retired prefix. Found thirty; only
  25 are categories. The other five — two asset handles, a style name, a docblock, a fixture — would
  have been corrupted by a prefix regex.
- [x] **T015** `[US3]` Rewrite the 25 owned identifiers across 485 files, longest-first so
  `content-search` is not shadowed by `content`.
- [x] **T016** `[US3]` Add `AcrossAI_Category_Slug_Migration`, rewriting both the saved preferences
  and the categories recorded against operator-created abilities, behind a completion flag.
- [x] **T017** `[US3]` Wire it into activation **and** `admin_init` priority 1, because an in-place
  upgrade never re-activates.
- [x] **T018** `[P]` `[US3]` Regenerate the inventory from source.

## Phase 5: User Story 4 — guards

- [x] **T019** `[US4]` Add `Test_Ability_Group_Map`: the canonical map, `core` retirement, group
  sizes, label-safety, and the moved files' consistency.
- [x] **T020** `[US4]` Verify it by deliberately misfiling `media/upload-media` and confirming the
  failure names the ability, the expected group and the declared one.
- [x] **T021** `[P]` `[US4]` Add `Test_Category_Slug_Migration`: allow-list correctness, the
  `content`/`content-search` shadowing case, non-category strings left alone, idempotency, and that a
  newer preference is never overwritten by an older one.
- [x] **T022** `[US4]` Fix the coverage test in T021, which passed vacuously — it scanned one
  declaration shape and found 24 of 25 categories, so its diff was empty. Now scans both shapes and
  asserts a minimum discovery count.

## Phase 6: The bug found while verifying

- [x] **T023** Investigate 399 platform notices in the debug log. Cause: `Debugging/Category_Registrar.php`
  shipped with Feature 061 and was never wired — 24 registrars wired, 25 exist. Its seven abilities had
  never existed at runtime.
- [x] **T024** Wire the Debugging category registrar.
- [x] **T025** `[US4]` Add `Test_Category_Registrar_Wiring`, asserting both directions — every
  registrar is wired, and every folder whose abilities are instantiated has a registrar — with a
  minimum-discovery count so a broken scan cannot pass.
- [x] **T026** `[US4]` Verify it by reverting the fix and confirming it names `Debugging`.

## Phase 7: Documentation and verification

- [x] **T027** `[P]` Add a changelog entry covering the regrouping, the fixed half-cards, the file
  move, the changed bulk-action scope, and the ten deep links that stop resolving.
- [x] **T028** `[P]` Rewrite the tab-selection playbook to say *pick the group by the job, not the
  folder*, and record the file-relocation test.
- [x] **T029** `[P]` Record the group taxonomy as a decision, including why thirteen is a ceiling.
- [x] **T030** `[P]` Record the durable lessons, including that the impact analysis in T008 was scoped
  to the field rather than the operation.
- [x] **T031** Verify in the running site: thirteen tabs, Content first, no Core; Configuration shows
  19 abilities across five cards with three split cards showing correct halves; a retired deep link
  falls back to the default view and strips the stale value.
- [x] **T032** Confirm zero platform notices after an authenticated page load, against 399 before.
- [x] **T033** Full suite: PHPUnit 2304 tests / 7558 assertions, PHPStan level 8, PHPCS, Jest.
  Confirmed the five failing Jest suites and six PHPUnit warnings are pre-existing by checking out
  `main`, rather than assuming.

## Dependencies

```
T001-T004  (understand)
    ↓
T005-T008  (move files) ──── blocking: the sweep must see files in their final home
    ↓
T009-T013  (groups)
    ↓
T014-T018  (rename + migration)
    ↓
T019-T022  (guards)          T023-T026 (bug, found during verification)
    ↓                              ↓
T027-T033  (document, verify) ─────┘
```

## Parallel opportunities

T018, T021, T027–T030 are independent of their neighbours. Everything else is sequential, because the
file move must precede the group sweep and the rename must precede its own guards.
