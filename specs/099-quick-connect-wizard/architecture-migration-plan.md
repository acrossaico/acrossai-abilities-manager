# Library Tab-Group Exposure Migration Plan

Scope: one boundary — how consumers outside the `Library` module obtain integration tab-group
summaries. This is deliberately narrow. No rewrite of the Library module, the Registry, or the
Integrations page is proposed.

## Current State

```text
includes/Modules/Library/AcrossAI_Ability_Library_Registry.php
        │  get_definitions()  (public, returns ~440 full definition rows)
        │
        ├── includes/Main.php            (entry layer)      ── allowed
        ├── admin/Main.php               (entry layer)      ── allowed
        ├── Library's own classes                           ── intra-module
        └── includes/Abilities/RankMath/Base_Rank_Math_Ability.php

Planned (Feature 099) — NOT yet written:
        └── includes/Modules/Abilities/Rest/AcrossAI_Quick_Connect_Controller.php  ── ✗ sibling module
```

### Problems

- The planned controller sits in the `Abilities` module and would import a `Library` module class
  directly. `grep -rn 'Modules\\Library' includes/Modules/Abilities/` returns zero matches, so this
  would be **the first sibling-module reach-through in the codebase**.
- It breaches Constitution Module Contract #3: "Depend only on shared utilities from
  `includes/Utilities/` — never on sibling modules directly."
- `get_definitions()` returns full definition rows (~585 KB serialized). A consumer needing ~17
  integer counts would pull the entire structure and reduce it, coupling itself to the definition
  shape as well as the module.
- Tab-group labelling would exist twice — once in the Library page's JS (`titleCaseTabLabel`) and
  once server-side — with nothing keeping them identical, risking SC-004 drift.

## Target State

```text
includes/Modules/Library/AcrossAI_Ability_Library_Registry.php
        │  get_definitions()                        (unchanged, intra-module + entry layer)
        │  apply_filters( 'acrossai_ability_library_tab_group_summary', [] )   ← new seam
        │        returns [{ key, label, count }]
        │
        └── any consumer, including other modules  ── allowed via filter (Module Contract #4)

includes/Utilities/AcrossAI_Tab_Group_Label.php    ← single labelling rule, shared by PHP + pinned to JS
```

### Benefits

- No cross-module import; the dependency becomes a published integration point rather than a
  reach-through, satisfying Module Contract #3 **and** #4.
- Consumers receive ~17 small rows instead of ~440 full definitions, and are decoupled from the
  definition shape.
- One labelling rule, testable on both sides, removes the SC-004 drift risk.
- The seam is reusable — any future surface needing a catalogue summary uses the same filter.

## Migration Phases

### Phase 1: Introduce the seam (Estimated: 0.5 day)

**Goal**: The filter exists and is correct, with nothing yet depending on it.

- **Task 1.1**: Add `AcrossAI_Tab_Group_Label::format( string $key ): string` in
  `includes/Utilities/`, implementing `ucwords( str_replace( '-', ' ', $key ) )` (tasks.md T009).
- **Task 1.2**: In `AcrossAI_Ability_Library_Registry`, build the summary from the definitions it
  already holds and return it through `apply_filters( 'acrossai_ability_library_tab_group_summary', $summary )`
  (tasks.md T010).
- **Task 1.3**: Add paired PHPUnit/Jest fixtures asserting the PHP and JS labelling rules produce
  identical output (tasks.md T012).

**Coexistence**: `get_definitions()` is untouched and every existing caller keeps working. The
filter is purely additive — if nothing consumes it, behaviour is unchanged.

### Phase 2: Consume the seam (Estimated: 0.25 day)

**Goal**: The wizard's `/state` endpoint sources counts through the filter only.

- **Task 2.1**: `AcrossAI_Quick_Connect_Controller::get_state()` calls the filter; it must not
  reference any `Modules\Library` class (tasks.md T021).
- **Task 2.2**: Assert group presence and ordering match the Integrations page (tasks.md T055).

**Coexistence**: Not applicable in the usual sense — the consumer is new code, so there is no old
path to retire. This is why the change is cheap now and expensive later: once the direct import
ships, removing it becomes a real migration.

### Phase 3 (optional, deferred): Migrate the Integrations page (Estimated: 1 day)

**Goal**: The Library admin page derives its tabs from the same summary rather than recomputing in
JS.

- **Task 3.1**: Have the page consume the summary for tab labels and counts, keeping client-side
  filtering for the ability lists.

**Coexistence**: The page keeps its current JS derivation until this phase runs; the two agree
because Phase 1.3 pins the rule. **Explicitly out of scope for Feature 099** — listed only so the
seam's longer-term value is visible.

## Coexistence Strategy

**Why coexistence?** The Library module has four existing consumers of `get_definitions()`, none of
which need to change. A big-bang replacement of that method would touch the Integrations page, the
localization payload, and an ability base class for no benefit.

**How**:
- `get_definitions()` remains the intra-module and entry-layer accessor, unchanged.
- The new filter is the cross-module accessor, additive and independently testable.
- New code (the wizard) uses the filter immediately; old code is never forced to migrate.
- Phase 3 is available if the Integrations page later benefits, but is not required for correctness.

## Rollback Plan

Phase 1 is additive: removing the `apply_filters()` line and the utility restores the prior state
with no consumer impact. Phase 2 rollback means pointing `/state` at a direct `get_definitions()`
call — which is precisely the violation, so it should be treated as a knowing, recorded deviation
rather than a quiet fallback.

## Success Criteria

- [ ] `grep -rn 'Modules\\Library' includes/Modules/Abilities/` returns zero matches after
      implementation.
- [ ] `GET /quick-connect/state` group counts equal the Integrations page tabs (SC-004).
- [ ] Paired PHP/JS label tests pass on the same fixture list.
- [ ] Existing `get_definitions()` callers are unmodified.
- [ ] No performance regression: the summary is derived from definitions already loaded in the
      request; no extra query or file read.
