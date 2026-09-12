# Feature Specification: One Access Model for Abilities

**Feature Branch**: `102-abilities-toolset-tabs`
**Created**: 2026-09-12
**Status**: Draft
**Input**: User description: "Remove the ability registration gate from the Integrations screen, and fold that screen's toolset tabs into the Custom Abilities table as a filter."

## Overview

The site has two screens that both claim to control which abilities are available, and one silently
overrules the other. The Integrations screen's per-category switch does not *hide* abilities — it stops
them being created at all, so they disappear from the abilities list with no explanation. An
administrator searching the abilities list for something they switched off elsewhere is told it does not
exist.

This feature reduces that to one screen and one access model. Every ability the site provides is always
present in the list; whether it can be used is decided only by the per-ability access setting that the
list already offers. The toolset grouping from the Integrations screen survives as a way to filter
the list.

## Clarifications

### Session 2026-09-12

- Q: What evidence should the one-time upgrade translation produce for an administrator? → A: No record — correctness is established by automated tests before release, not reported afterwards.
- Q: Must the translation complete before previously-blocked abilities become reachable? → A: No — it is attempted once at upgrade; if it fails, the site continues with those abilities unblocked.
- Q: What is the grouping called in the interface? → A: Toolset — the column is labelled Toolset and shows values such as `toolset/content`.
- Q: Is the toolset strip a set of tabs or a set of navigation links? → A: Navigation links — each toolset is a real link, keyboard-reachable in sequence and openable in a new browser tab.
- Q: What happens to the Status filter, which currently has no effect on most abilities? → A: Fix it in scope — it applies to every ability in the list.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - An administrator can always see every ability the site provides (Priority: P1)

An administrator opens the abilities list and searches for an ability by name. It is there, together with
its current access setting, regardless of how the site has been configured. If it has been blocked, the
list says so on the row rather than omitting the row.

**Why this priority**: This is the defect that motivated the feature. Today an ability that has been
switched off at the category level is absent from the list entirely, so the administrator cannot see it,
cannot search for it, and has no route to turn it back on from the screen that appears to govern it.

**Independent Test**: On a site where a whole category has previously been switched off, search the
abilities list for one of that category's abilities. The row appears, showing that access is blocked.

**Acceptance Scenarios**:

1. **Given** a category that was previously switched off, **When** the administrator searches the
   abilities list for one of its abilities, **Then** the ability appears in the results with its access
   shown as blocked.
2. **Given** any ability in the list, **When** the administrator changes its access setting, **Then** that
   setting alone determines whether the ability can be used — no other screen can override it.
3. **Given** an ability whose access is blocked, **When** the administrator clears the block, **Then** the
   ability becomes usable without visiting any other screen.
4. **Given** the abilities list, **When** the administrator counts the rows across all pages, **Then** the
   total equals every ability the site provides.

---

### User Story 2 - Upgrading does not change what anyone can do (Priority: P1)

An administrator who has switched categories off, or restricted a category to a hand-picked set of
abilities, upgrades the plugin. Afterwards the same abilities are usable and the same abilities are not.
Nothing they blocked becomes reachable, and nothing they allowed stops working.

**Why this priority**: Equal to Story 1, and the only irreversible part of the feature. Because the old
configuration prevented abilities existing while the new model blocks abilities that exist, an upgrade
that does not translate one into the other would silently expose every ability the administrator had
switched off — including abilities that modify the database and the filesystem.

**Independent Test**: On a site with categories switched off and at least one category restricted to a
subset, record which abilities are usable. Upgrade. Compare. The two sets are identical.

**Acceptance Scenarios**:

1. **Given** a category that was switched off, **When** the upgrade completes, **Then** every ability in
   that category is present in the list and blocked.
2. **Given** a category restricted to a hand-picked set, **When** the upgrade completes, **Then** exactly
   the abilities that were not picked are blocked, and the picked ones are not.
3. **Given** an ability whose access the administrator had already set explicitly, **When** the upgrade
   completes, **Then** that explicit setting is preserved and not replaced.
4. **Given** a category that was never restricted, **When** the upgrade completes, **Then** none of its
   abilities are blocked.
5. **Given** a completed upgrade, **When** the upgrade process runs again, **Then** nothing changes.
6. **Given** a category containing more abilities than the old configuration could store, **When** the
   upgrade completes, **Then** every one of its abilities is translated, not only the first stored batch.

---

### User Story 3 - An administrator finds abilities by the job they do (Priority: P2)

An administrator wants to review everything the site can do to its content, or its files, or its users.
They pick that toolset from a strip of tabs above the abilities list and see only those abilities. A
column on each row names the toolset it belongs to, so the grouping is still visible when no filter is
applied.

**Why this priority**: The toolset grouping is the one genuinely useful thing the retired screen
offered. Preserving it keeps a ~450-row list navigable. It is P2 because the list remains usable through
search alone if this slips.

**Independent Test**: Open the abilities list, choose a toolset tab, and confirm the rows shown are
exactly that toolset's abilities and the count beside the tab matches.

**Acceptance Scenarios**:

1. **Given** the abilities list, **When** it loads, **Then** a strip of tabs shows "All" plus one tab per
   toolset, each with the number of abilities it contains.
2. **Given** a toolset tab, **When** the administrator selects it, **Then** the list shows only that
   toolset's abilities and the result count matches the number on the tab.
3. **Given** a toolset whose host plugin is not active, **When** the list loads, **Then** that toolset
   has no tab.
4. **Given** any tab, **When** the administrator searches, filters, sorts, selects rows or applies a bulk
   action, **Then** those controls behave exactly as they do on "All".
5. **Given** an ability belonging to no toolset, **When** it appears in the list, **Then** its toolset
   column is empty rather than showing a fabricated value.
6. **Given** a selected tab, **When** the administrator switches to another tab, **Then** the list returns
   to the first page and any row selection is cleared.
7. **Given** a toolset tab, **When** the administrator copies the page address and opens it again,
   **Then** the same tab is active.
8. **Given** an administrator navigating by keyboard, **When** they move through the strip, **Then** each
   toolset is reached in sequence and opened with Enter, with no arrow-key-only behaviour required.
9. **Given** any toolset in the strip, **When** the administrator opens it in a new browser tab, **Then**
   that tab loads the abilities list already filtered to that toolset.
10. **Given** any tab, **When** the administrator filters by status, **Then** the filter applies to every
    ability shown, not only to abilities they created on the site.

---

### User Story 4 - Third-party integrations stay off until asked for (Priority: P3)

An administrator who wants a third-party plugin's abilities enables that integration from the AcrossAI
settings screen. Until they do, the third-party plugin contributes nothing.

**Why this priority**: This switch looks like the ones being removed but is not one of them — it asks a
third-party plugin to provide its abilities in the first place, so nothing in the abilities list can
replace it. It is P3 only because it is a relocation rather than new capability.

**Independent Test**: With the integration off, confirm the third-party plugin's abilities are absent.
Switch it on from settings, confirm they appear in the abilities list.

**Acceptance Scenarios**:

1. **Given** an integration that has never been enabled, **When** the administrator opens settings,
   **Then** it is shown as off.
2. **Given** an integration that was enabled before the upgrade, **When** the upgrade completes, **Then**
   it is still enabled.
3. **Given** an administrator without permission to change integrations, **When** they attempt to change
   one, **Then** the change is refused.
4. **Given** an enabled integration, **When** the administrator switches it off, **Then** the third-party
   plugin's abilities stop appearing in the abilities list.

---

### User Story 5 - Existing links to the retired screen still work (Priority: P3)

Someone follows a link to the Integrations screen — from documentation, a bookmark, or a colleague — and
lands on the abilities list, on the toolset the link named.

**Why this priority**: The address is referenced by external documentation. Cheap to honour, confusing to
omit.

**Independent Test**: Open the old address with a toolset in it and confirm it lands on the abilities
list with that toolset selected.

**Acceptance Scenarios**:

1. **Given** the old screen's address, **When** it is opened, **Then** the browser is permanently
   redirected to the abilities list.
2. **Given** the old address naming a toolset, **When** it is opened, **Then** the abilities list
   opens with that toolset selected.
3. **Given** the old address naming a toolset that no longer exists, **When** it is opened, **Then** the
   abilities list opens showing all abilities rather than an error.

---

### Edge Cases

- **An ability belongs to no toolset.** Its toolset column is empty and it appears only under "All".
- **An administrator had already blocked an ability that the old configuration also blocked.** The
  existing explicit setting is kept; the upgrade does not overwrite it.
- **A category held more abilities than the old configuration could record.** The old store silently
  stopped recording after a fixed number, so the stored picks may be incomplete. The upgrade must
  translate the category's full membership, and must not treat unrecorded abilities as allowed.
- **The upgrade runs more than once.** The result is the same as running it once.
- **The upgrade translation fails partway.** It is attempted once, with no automatic retry and no rollback.
  Whatever it translated stands; the remainder is left untranslated, and those abilities are usable,
  because the mechanism that previously made them unavailable no longer exists. Combined with the decision
  that the translation reports nothing, a failed translation is indistinguishable from a site that had
  nothing to translate.
- **A toolset's host plugin is deactivated** after abilities were configured. The toolset has no tab,
  but any access settings for its abilities survive and apply again if the plugin returns.
- **An address names a toolset that does not exist.** The list falls back to showing all abilities.
- **A bulk action is applied while a toolset filter is active.** It affects only the rows the
  administrator selected, never the whole toolset implicitly.

## Requirements *(mandatory)*

### Functional Requirements

**Single access model**

- **FR-001**: The system MUST make every ability the site provides available to the abilities list,
  regardless of any category-level configuration.
- **FR-002**: The system MUST determine whether an ability can be used solely from that ability's own
  access setting.
- **FR-003**: The system MUST remove the category-level on/off switch, the category-level "all versus
  hand-picked" mode, and the per-category list of picked abilities.
- **FR-004**: The system MUST remove the screen that presented those controls, and the stored
  configuration behind them.
- **FR-005**: The system MUST remove the bulk "enable everything" and "disable everything" buttons.
  Administrators change many abilities at once by selecting rows and applying a bulk action, which
  requires explicit confirmation.

**Preserving existing configuration**

- **FR-006**: On upgrade, the system MUST translate every ability that the previous configuration
  prevented from being available into an explicitly blocked access setting for that ability.
- **FR-007**: The translation MUST cover both whole categories that were switched off and individual
  abilities left unpicked in a hand-picked category.
- **FR-008**: The translation MUST NOT change an access setting an administrator had already set
  explicitly.
- **FR-009**: The translation MUST produce the same result whether it runs once or many times. It is
  attempted once automatically at upgrade and is not retried after a failure (see Edge Cases); this
  requirement exists so that invoking it again causes no harm, not to imply it will be invoked again.
- **FR-010**: The set of abilities usable on a site MUST be identical immediately before and immediately
  after the upgrade.
- **FR-011**: The translation MUST determine a category's membership from the site's own catalogue of
  abilities rather than from the previous configuration's stored list, which may be incomplete.

**Browsing by toolset**

- **FR-012**: The abilities list MUST present a strip comprising "All" plus one entry for each toolset that
  has abilities on the site, each showing that toolset's ability count.
- **FR-012a**: Each entry in the strip MUST behave as a navigation link to that toolset's view — reachable
  in sequence by keyboard, activated by Enter, and openable in a new browser tab like any other link —
  rather than as an in-page tab control with its own focus handling.
- **FR-013**: Selecting a toolset tab MUST restrict the list to that toolset's abilities.
- **FR-014**: The list MUST show each ability's toolset in its own column, labelled **Toolset**, showing
  the toolset's identifier (for example `toolset/content`) and left empty for abilities belonging to none.
- **FR-015**: Every other control on the list — search, filters, sorting, the column picker, row
  selection, bulk actions and paging — MUST behave identically on every tab.
- **FR-015a**: The Status filter MUST apply to every ability in the list, not only to abilities an
  administrator created on the site. Abilities the site provides are always published; only abilities
  created on the site can be drafts, so filtering for drafts narrows the list to those.
- **FR-016**: Changing tab MUST return the list to its first page and clear any row selection.
- **FR-017**: The selected tab MUST be reflected in the page address so it can be bookmarked and shared,
  and MUST survive opening and closing an individual ability without being lost.
- **FR-018**: An address naming an unrecognised toolset MUST fall back to showing all abilities.

**Third-party integrations**

- **FR-019**: The system MUST continue to let administrators opt in to third-party integrations, since
  that opt-in is what causes the third-party plugin to provide its abilities at all.
- **FR-020**: Integration opt-ins MUST be presented on the AcrossAI settings screen.
- **FR-021**: An integration that has never been enabled MUST be off.
- **FR-022**: Integration opt-in states in effect before the upgrade MUST remain in effect after it.
- **FR-023**: Changing an integration opt-in MUST remain subject to the same permission check as before,
  including any site-specific customisation of that check.

**Retired address**

- **FR-024**: The retired screen's address MUST permanently redirect to the abilities list.
- **FR-025**: The redirect MUST carry across any toolset named in the original address.

### Key Entities

- **Ability**: Something the site can do on request. Has a name, a label, a category, a source, a toolset
  , exposure settings, and one access setting that decides whether it can be used.
- **Toolset** (formerly referred to as "task family"): A grouping of abilities by the job they serve,
  such as content, files or users. An
  ability belongs to at most one. Toolsets exist only while they have abilities present.
- **Access setting**: The per-ability decision — inherit the default, always allow, or always block. After
  this feature it is the only thing that governs availability.
- **Integration opt-in**: A per-third-party-plugin switch that asks that plugin to contribute its
  abilities. Off unless explicitly enabled. Not an access setting, and not replaceable by one.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Every ability the site provides appears in the abilities list — the list's total equals the
  site's ability count, on every site regardless of prior configuration.
- **SC-002**: Searching the abilities list for any ability by name returns it, including abilities an
  administrator has blocked. Zero abilities are unreachable through search.
- **SC-003**: Where the upgrade translation completes, the set of usable abilities is identical
  immediately before and after the upgrade, on sites with categories switched off, categories hand-picked,
  and categories untouched.
- **SC-004**: No access setting an administrator made by hand is altered by the upgrade.
- **SC-005**: An administrator can reach any ability's access setting from a single screen, with no
  intermediate screen able to override it.
- **SC-006**: Selecting a toolset reduces the list to that toolset's abilities, and the tab's count
  matches the number of rows returned.
- **SC-007**: An integration enabled before the upgrade is still enabled afterwards, on every site.
- **SC-008**: Every previously published link to the retired screen resolves to the equivalent view of the
  abilities list.
- **SC-009**: The number of screens an administrator must consult to answer "can this ability be used?"
  falls from two to one.
- **SC-010**: Every filter offered on the abilities list changes the result set when applied — no control
  is present that has no effect.

## Assumptions

- **Access blocking replaces access prevention without loss.** An ability that exists but is blocked is
  treated as equivalent, for the administrator's purposes, to one that was never created. The observable
  difference — that it is now visible and explains itself — is the point of the feature.
- **Visibility of blocked abilities is desirable, not a disclosure risk.** Listing an ability an
  administrator has blocked reveals only that the site could perform it, to an administrator who already
  has permission to unblock it.
- **The bulk-action flow is a sufficient replacement for the removed "disable everything" button.** It
  requires explicit row selection and confirmation, which the removed button did not.
- **Toolsets are derived from information the abilities already carry**, so no new classification
  work is required and toolsets stay correct as abilities are added.
- **A per-ability access setting already exists and is honoured everywhere**, including for abilities
  offered to external clients. This feature relies on it rather than introducing it.
- **Third-party integration opt-ins are few** — one at present — so a simple list of switches on the
  settings screen is adequate, with no need for search, grouping or paging.
- **Sites upgrade through the normal plugin upgrade path**, so a one-time translation running at upgrade
  reaches every site before an administrator next uses the abilities list.
- **The existing visual language of the plugin's admin screens is retained.** This feature changes what is
  on screen, not how it looks.
- **The upgrade translation is silent.** It shows the administrator nothing and writes no report; the
  only visible evidence that it ran is that the abilities list afterwards shows the same abilities
  blocked that were unavailable before. Because there is no after-the-fact signal, the correctness of the
  translation must be established by automated coverage before release rather than observed on a live
  site — SC-003 and SC-004 are the measures, and they have to be proven ahead of shipping, not audited
  afterwards.
- **A failed upgrade translation is an accepted risk.** The translation is attempted once, with no retry
  and no rollback, and any abilities it did not reach remain usable. This is accepted on the basis that
  its correctness is proven before release, so a failure in the field is treated as unlikely rather than
  as something to be recovered from at run time. The trade-off taken is availability over restriction:
  a failure leaves abilities reachable rather than leaving the site unusable.
