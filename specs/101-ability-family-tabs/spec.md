# Feature Specification: Ability Group Tabs

**Feature Branch**: `101-ability-group-tabs`
**Created**: 2026-09-10
**Status**: Implemented
**Input**: User description: "Group all the abilities properly — the Integrations screen has 18 tabs and Core holds 105 of them. Divide into task groups, and abilities in a folder can belong to multiple groups."

> **Provenance.** This specification was written **after** implementation, from the approved plan and
> the shipped code, so that Feature 101 has the same artifact set as its neighbours. It describes what
> was built rather than predicting what would be. Requirements below are stated in the normal
> imperative form because they remain the contract going forward — but every one of them is already
> satisfied on this branch. Commits: `4984d23`, `5a8aed5`, `9614520`.

## User Scenarios & Testing *(mandatory)*

The Ability Integrations screen organises ~450 abilities into tabs. One tab, **Core**, held 105 of
them — nine whole folders plus parts of two more. It was not a subject; it was where abilities landed
when nobody chose. Fourteen of the remaining tabs mapped one-to-one onto a single card, so the tab
level bought them nothing at all.

### User Story 1 - A site owner finds abilities by what they are trying to do (Priority: P1)

A site owner opens the Integrations screen wanting to change something about their content. They look
at the tab bar and see a tab named for that job, containing only abilities relevant to it — rather
than a tab named after an internal grouping that happens to contain a third of everything.

**Why this priority**: This is the feature. Every tab must answer "what am I trying to do", and the
105-ability bucket is the reason the screen needed work at all.

**Independent Test**: Open the screen. No tab holds a disproportionate share of the catalogue, and
every tab name describes a task rather than a code location.

**Acceptance Scenarios**:

1. **Given** the Integrations screen, **When** it is opened, **Then** the tab bar shows thirteen
   groups and no tab named "Core".
2. **Given** any group tab, **When** it is selected, **Then** it shows only abilities belonging to
   that group, grouped into cards.
3. **Given** an integration whose host plugin is inactive, **When** the screen is opened, **Then**
   that integration's tab is absent.
4. **Given** any tab, **When** its ability count is compared with the others, **Then** no tab holds
   more than roughly a fifth of the registered catalogue.

---

### User Story 2 - An ability appears under the job it serves, not the folder it lives in (Priority: P1)

Some abilities do a job unrelated to most of their neighbours. An ability that changes what the whole
site accepts on upload is a configuration concern even though it lives beside the media library.
A site owner looking for it should find it under Configuration.

**Why this priority**: Equal to Story 1. Assigning groups per folder would have reproduced the
original problem — a folder is a code location, not a task.

**Independent Test**: Open Configuration. It contains abilities drawn from four different folders,
each because of what it does.

**Acceptance Scenarios**:

1. **Given** a category whose abilities serve two different jobs, **When** the screen is opened,
   **Then** its card appears under both groups, each time showing only the relevant abilities.
2. **Given** such a split card, **When** it is displayed, **Then** it names every group it belongs
   to, so the other half is discoverable rather than hidden.
3. **Given** the Configuration tab, **When** it is opened, **Then** it shows the permalink abilities
   from Settings, the upload-policy abilities from Media, and the rewrite-flush ability from Cache,
   alongside Options and Admin Menu.

---

### User Story 3 - A site owner's saved choices survive the change (Priority: P1)

A site owner who has disabled categories, or set some to expose only specific abilities, keeps those
choices. Custom abilities they created keep working.

**Why this priority**: The change alters an identifier that is also a stored key. Getting it wrong
does not throw — it makes the site owner's own abilities disappear.

**Independent Test**: With saved preferences and at least one custom ability in place, upgrade. Both
survive.

**Acceptance Scenarios**:

1. **Given** saved per-category preferences, **When** the plugin upgrades, **Then** every preference
   still applies to the same category.
2. **Given** an ability the site owner created, **When** the plugin upgrades, **Then** it still
   registers and still appears under its category.
3. **Given** an upgrade performed without re-activating the plugin, **When** the next administrative
   page is loaded, **Then** the stored values are brought up to date.
4. **Given** an already-upgraded site, **When** the upgrade path runs again, **Then** nothing changes
   and no newer preference is overwritten by an older one.

---

### User Story 4 - A misfiled ability is caught before release (Priority: P2)

A contributor adds an ability and gives it the wrong group, or adds a folder and forgets to register
its category. The test suite fails and names the problem.

**Why this priority**: Nothing in the runtime validates any of this. The 105-ability bucket formed
one copy-paste at a time *despite* a documented warning about that exact failure, which is evidence
that documentation alone does not hold.

**Independent Test**: Deliberately misfile an ability. The suite fails and names it.

**Acceptance Scenarios**:

1. **Given** an ability declaring a group other than the one the map assigns it, **When** the tests
   run, **Then** they fail and name the ability, the expected group and the declared one.
2. **Given** a folder shipping a category registrar that is not registered at start-up, **When** the
   tests run, **Then** they fail and name the folder.
3. **Given** a group whose name would not render as a readable label, **When** the tests run,
   **Then** they fail.
4. **Given** a scan that finds nothing because it was written wrongly, **When** the tests run,
   **Then** they fail rather than reporting success against an empty set.

---

### Edge Cases

- **A retired tab is deep-linked.** Ten identifiers stop resolving. Each falls back to the default
  view silently, with no error — the pre-existing contract for unrecognised tab values.
- **A category is enabled but exposes only specific abilities.** Its card still appears in every
  group it belongs to, showing whichever of its selected abilities fall in that group.
- **A category spans two groups and is switched off.** It disappears from both. The enable control
  is one setting shown in two places; the membership labels are what make that visible.
- **An integration is activated mid-session.** Its group appears on the next page load.
- **An ability is created with a category that no longer exists.** WordPress refuses to register it,
  silently. This is why the stored-value update exists.
- **A site upgrades several versions at once.** The stored-value update is idempotent and runs once.
- **A string resembles an owned identifier but is not one.** Asset handles and style names that share
  the retired prefix are left untouched.

## Requirements *(mandatory)*

### Functional Requirements

**Groups**

- **FR-001**: The system MUST organise abilities into thirteen groups, each named for a task a site
  owner performs rather than for a code location.
- **FR-002**: The system MUST NOT retain a general-purpose group that accumulates abilities having no
  other home.
- **FR-003**: Each group's displayed name MUST be derived from its identifier, since no separate
  label field exists. Identifiers MUST therefore be chosen so their derived name is readable.
- **FR-004**: Groups whose abilities come from an optional integration MUST appear only while that
  integration is active.
- **FR-005**: The most frequently used group MUST appear first; the remainder MUST be ordered
  deterministically so that the same set produces the same order on every site.

**Assignment**

- **FR-006**: Group membership MUST be assigned per ability, not per folder.
- **FR-007**: A category whose abilities serve more than one job MUST be able to contribute to more
  than one group.
- **FR-008**: A card appearing in more than one group MUST show, in each, only the abilities
  belonging to that group.
- **FR-009**: Such a card MUST name every group it belongs to, so no portion of it is hidden.
- **FR-010**: An ability's file MUST be relocated between folders only when its own published
  identifier already contradicts its folder. Relocation MUST NOT change that identifier.

**Stored values**

- **FR-011**: The system MUST bring stored category identifiers up to date on upgrade, covering both
  the site owner's saved per-category preferences and the categories recorded against abilities they
  created.
- **FR-012**: The update MUST run both when the plugin is activated and on the next administrative
  page load, because an upgrade performed in place never re-activates.
- **FR-013**: The update MUST be safe to repeat and MUST NOT replace a newer stored preference with
  an older one.
- **FR-014**: The update MUST act only on identifiers this plugin owns, enumerated explicitly. It MUST
  NOT act on identifiers belonging to other plugins, nor on unrelated values that merely share a
  prefix.
- **FR-015**: After the update, every ability the site owner created MUST still register.

**Correctness guarantees**

- **FR-016**: The system MUST fail its tests when any ability declares a group other than the one the
  map assigns it, naming the ability and both groups.
- **FR-017**: The system MUST fail its tests when a folder ships a category registrar that is never
  registered at start-up.
- **FR-018**: The system MUST fail its tests when abilities are created from a folder that has no
  category registrar.
- **FR-019**: Every test that scans the source MUST fail when its scan finds nothing, rather than
  reporting success against an empty result.
- **FR-020**: Every category the plugin registers MUST be covered by the stored-value update, verified
  by test rather than by inspection.

**Compatibility**

- **FR-021**: Abilities' published identifiers MUST NOT change, so no connected client is affected.
- **FR-022**: An unrecognised tab identifier MUST resolve to the default view without an error.
- **FR-023**: The generated inventory of abilities MUST be produced from source rather than
  maintained by hand.

### Key Entities

- **Group**: A named group of abilities sharing a task. Identified by a single value that is also its
  displayed name. Thirteen exist; two appear conditionally.
- **Category**: The existing per-folder grouping. Supplies the cards within a group, and is the unit
  a site owner enables or disables. May contribute abilities to more than one group.
- **Group assignment**: A per-ability association with exactly one group, derived from what the
  ability does.
- **Stored category identifier**: The same value as a category, persisted in the site owner's saved
  preferences and against abilities they created. Renaming it is a data migration.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: No group holds more than a fifth of the registered catalogue, against 23% concentrated
  in a single tab before this change.
- **SC-002**: Every group is describable in one sentence naming the task it serves.
- **SC-003**: Zero categories appear in two tabs showing different halves of themselves without
  naming both, against two before this change.
- **SC-004**: 100% of a site owner's saved per-category preferences survive the upgrade.
- **SC-005**: 100% of abilities a site owner created continue to register after the upgrade.
- **SC-006**: Zero abilities change their published identifier, so zero connected clients are
  affected.
- **SC-007**: A deliberately misfiled ability causes a test failure that names it — verified by
  introducing one, not asserted.
- **SC-008**: Every ability the plugin defines registers successfully, with zero rejections recorded
  by the platform, against 399 accumulated before this change.
- **SC-009**: The count of abilities recorded in the generated inventory equals the count that
  actually register.
- **SC-010**: The number of groups stays at or below the threshold beyond which an assistant's tool
  selection is known to degrade.

## Assumptions

- **Thirteen groups is a ceiling, not an outcome.** Feature 100 exposes one assistant-facing tool
  per group, and tool-selection accuracy is documented to degrade beyond 30–50 available tools, with
  measurements placing smaller models below 90% between 10 and 15. Adding a fourteenth group is cheap
  in the admin interface and expensive in an assistant, so it is a decision rather than a reflex.
- **Per-ability assignment is the intended design, not a workaround.** The interface has displayed
  each card's group membership since Feature 055, so a card spanning groups was always anticipated.
  What was wrong beforehand was not the mechanism but that nobody had chosen the assignments — two
  categories were split along lines that followed no distinction anyone intended.
- **A category identifier is a persistence key, not a label.** It keys the site owner's saved
  preferences and is recorded against abilities they create, and the platform refuses to register an
  ability whose category is unknown. Any future rename is therefore a data migration.
- **Relocating a file is justified only by a contradiction, never by taste.** The published identifier
  is the contract; the folder is not. Eight abilities qualified because their identifier already said
  they belonged elsewhere. Three superficially similar candidates did not, because relocating them
  would have required changing an identifier — a breaking change with no compatibility alias.
- **Documentation does not hold on its own.** A warning about this exact failure mode already existed
  when the 105-ability bucket formed. Every rule introduced here is therefore accompanied by a test
  that fails when it is broken, and each of those tests was verified by breaking the rule on purpose.
- **The Integrations screen's structure was already correct.** Tabs come from one grouping and cards
  from another; the two-level shape needed applying consistently, not replacing.
