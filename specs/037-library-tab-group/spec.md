# Feature Specification: Library Tab Group

**Feature Branch**: `037-library-tab-group`
**Created**: 2026-06-25
**Status**: Draft
**Input**: User description: "Add a tab bar to the Library admin page driven by a new optional 'tab_group' field on Ability_Definition return values. Mirrors the existing display-only sub_group pattern but at the page-tab level. A built-in 'All' tab always shows every ability; one additional tab is rendered per unique tab_group. tab_group doubles as both slug and display label (title-cased). Display-only — never persisted, never affects execution or REST."

## Clarifications

### Session 2026-06-25

- Q: How should tabs after the built-in 'All' tab be ordered? → A: Alphabetical by sanitized identifier (deterministic, environment-independent).
- Q: When exactly one add-on declares a tab_group (so the bar would show 'All' + one group), should the tab bar still render? → A: Yes — render the tab bar whenever ≥1 group exists. Setting `tab_group` always produces a visible tab; no hidden threshold.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Add-on author groups their abilities under a named tab (Priority: P1)

An add-on author who registers several related abilities (e.g. all CRM-related abilities) wants those abilities to live under a single tab on the Library admin page so site administrators can scan them as a coherent set, instead of being scattered into the flat category list with abilities from other add-ons.

**Why this priority**: Without grouping at the tab level, the Library page becomes harder to scan as more add-ons register abilities. This is the core value of the feature.

**Independent Test**: An add-on author adds a single optional field to the value their `Ability_Definition` subclass already returns. Without any other change, the Library page renders a new tab labeled after that value, and the author's abilities appear inside it.

**Acceptance Scenarios**:

1. **Given** an add-on declares two abilities with the same group identifier on its `Ability_Definition` subclass, **When** an admin opens the Library page, **Then** a tab appears with the identifier (title-cased) as its label, and clicking it shows only those two abilities.
2. **Given** two different add-ons each declare a distinct group identifier on their own abilities, **When** an admin opens the Library page, **Then** one tab appears per distinct identifier, in deterministic order.
3. **Given** an add-on later removes the group identifier from one of its abilities, **When** the page is reloaded, **Then** that ability no longer appears in the group's tab, but still appears in the default tab.

---

### User Story 2 — Admin sees the full ability set in one place by default (Priority: P1)

A site administrator opening the Library page wants to see every registered ability — regardless of which add-on registered it or whether the add-on author opted into tab grouping — so they can scan the complete catalog without having to click through every tab.

**Why this priority**: Without a default "show everything" view, admins would lose the ability to discover abilities that no one bothered to group, and the page would feel fragmented. This anchors trust in the page as a complete inventory.

**Independent Test**: With a mix of tab-grouped and ungrouped abilities installed, the admin opens the Library page and immediately sees every ability without selecting a tab.

**Acceptance Scenarios**:

1. **Given** the Library page is open with no tab explicitly selected, **When** the page first renders, **Then** the default tab is active and every registered ability is visible.
2. **Given** an ability is tagged with a group identifier, **When** the admin views the default tab, **Then** that ability appears in the default tab AND in its own group tab — appearing in a group tab does not remove it from the default view.
3. **Given** an ability has no group identifier, **When** the admin opens any non-default tab, **Then** that ability is not visible in that tab.

---

### User Story 3 — Page renders unchanged when no add-on opts in (Priority: P2)

An administrator on a site where no add-on has declared a group identifier should see the Library page render exactly as it does today — no tab bar, no extra chrome. The tab feature must be invisible until someone uses it.

**Why this priority**: This is a regression-prevention requirement. Existing add-ons that have not been updated for this feature must not have their Library appearance changed.

**Independent Test**: Disable every add-on that declares a group identifier (or test in a fresh site with only the manager plugin). Open the Library page and confirm it looks identical to the prior release.

**Acceptance Scenarios**:

1. **Given** zero abilities declare a group identifier, **When** an admin opens the Library page, **Then** no tab bar is rendered and the page layout matches the prior release exactly.
2. **Given** zero abilities declare a group identifier, **When** the page renders, **Then** the existing per-card behaviors (toggle, mode radio, save/load) work identically to today.

---

### User Story 4 — Admin's existing toggles keep working across tabs (Priority: P2)

When an administrator switches to a non-default tab and toggles an ability or changes a card's mode, those changes must persist exactly as they do today. The tab feature must not break the existing save/load flow.

**Why this priority**: The save flow already works in the current release. Adding tabs that silently break it would create a much worse experience than no tabs at all.

**Independent Test**: With at least one group tab present, switch to it, toggle a card's enabled state, reload the page, and confirm the saved state is restored — both in the group tab and in the default tab.

**Acceptance Scenarios**:

1. **Given** the admin is on a non-default tab, **When** they toggle a card's enabled state, **Then** the change is saved.
2. **Given** the admin reloads the page after step 1, **When** the page renders, **Then** the saved state is restored regardless of which tab is active first.

---

### Edge Cases

- **Group identifier contains characters that cannot be used as an identifier** (spaces, punctuation, mixed case): The system sanitizes the identifier using the same rules that already apply to category and sub-group identifiers. If sanitization yields an empty string, the ability is treated as ungrouped (only appears in the default tab).
- **Two abilities declare the same group identifier with different casing or separator style** (e.g. `Sales-Ops` vs `sales-ops`): After sanitization they collapse to the same identifier and share a tab.
- **The same ability is registered twice** (one declaration with a group, one without): The current Library page already de-duplicates abilities by their slug; that behavior is unchanged. The first occurrence wins.
- **A group has exactly one ability**: That tab still renders and shows that one ability.
- **The active tab is selected, then the underlying definitions change** (e.g. an add-on is deactivated in another browser tab): On next reload the active tab resets to the default tab; no persistence of selection across reloads.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST allow an add-on author to attach an optional group identifier to each ability they register through the existing ability-definition contract — without requiring any new class, hook, or registration call.
- **FR-002**: The Library admin page MUST render a tab navigation bar above the existing ability cards whenever at least one registered ability declares a group identifier.
- **FR-003**: The tab navigation bar MUST include a default tab that is always present and selected by default. The default tab MUST show every registered ability regardless of group.
- **FR-004**: For every distinct group identifier present across all registered abilities, the tab navigation bar MUST render one additional tab whose label is derived from that identifier.
- **FR-005**: When a non-default tab is active, the Library page MUST display only the abilities whose group identifier matches that tab. Category cards that have no matching abilities under the active tab MUST be hidden.
- **FR-006**: When no registered ability declares a group identifier, the system MUST NOT render the tab navigation bar; the page MUST render identically to the prior release.
- **FR-007**: The group identifier MUST be a single field — there is no separate display-label field. The displayed tab label is derived from the identifier using the same title-casing rule already used elsewhere on the Library page (replacing `-` with space, capitalizing each word).
- **FR-008**: The group identifier MUST be sanitized using the same rules already applied to other Library identifiers (category, slug, sub-group). Values that sanitize to an empty string MUST be treated as if the field was not provided.
- **FR-009**: The group identifier MUST be display-only. It MUST NOT be written to saved configuration, MUST NOT affect ability execution, and MUST NOT change the REST API surface.

  > **Partially superseded, 2026-09-10 — see `DEC-ABILITY-GROUP-IDENTIFIER-LOAD-BEARING`.** This
  > requirement was correct while the identifier only chose a tab. Feature 101 made it the product's
  > organising concept and Feature 100 derives the MCP tool catalogue from it. The first two clauses
  > still hold: nothing persists it, and an ability invoked directly behaves identically. The third
  > clause does not — the set of MCP tools is determined by these values — and neither does SC-005,
  > since a tool's response names the group it answers for. **Retagging an ability is therefore no
  > longer a cosmetic edit.**
- **FR-010**: Switching between tabs MUST NOT affect saved configuration. Toggles and mode changes performed under any tab MUST persist via the existing save flow.
- **FR-011**: The active tab selection MUST NOT persist across page reloads. The default tab MUST be active on every fresh page load.
- **FR-012**: Per-card behaviors (enable toggle, All/Specific mode radio, sub-group sub-headings, descriptions) MUST behave identically inside any tab as they do today on the flat page.
- **FR-013**: Tabs after the default 'All' tab MUST be ordered alphabetically by sanitized group identifier (case-insensitive). Ordering MUST NOT depend on registration order, filter priority, or any other runtime-variable input — the same set of group identifiers MUST produce the same tab order on every site and every page load.

### Key Entities

- **Ability definition**: An add-on's declaration of a single ability. Already carries display fields (category, slug, label, description) and an optional sub-group. This feature adds one more optional display field: a group identifier used for tab placement.
- **Tab**: A page-level filter rendered on the Library admin page. Built-in default tab is always present. Additional tabs are derived dynamically from the set of group identifiers declared across all registered abilities. Tabs have no persistent state — they exist only for the duration of the page view.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An add-on author can place all of their abilities under a single named tab by adding one field to each ability declaration — no new file, class, hook, or registration call required.
- **SC-002**: With three add-ons each declaring a distinct group, a site administrator can locate any one add-on's abilities by clicking exactly one tab — no scrolling required even when the total ability count exceeds 30.
- **SC-003**: On a site where no add-on declares a group identifier, the Library page visually matches the prior release exactly (no tab bar, no spacing shift, no behavioral change).
- **SC-004**: Existing save/load behavior is preserved 100% — every action that worked on the prior Library page (toggle, mode switch, sub-group display) continues to work identically inside every tab.
- **SC-005**: The group identifier never appears in any persisted store (saved configuration, REST response body, database row) — it lives only in the in-memory definition exposed to the admin page renderer.

---

## Assumptions

- Add-on authors update their ability declarations voluntarily; the manager plugin makes the field optional and does not require existing add-ons to change anything for the page to keep working.
- The set of group identifiers is small enough (single-digit to low double-digit count per site) that they fit on one row of tabs without horizontal scrolling. Wrapping or overflow handling for very large counts is out of scope.
- Tab labels are displayed in the WordPress admin's primary language. Per-locale translation of group identifiers is the add-on author's responsibility (same convention as category and sub-group labels today).
- The default tab's label is "All". This is the same word the existing per-card "All vs Specific" mode uses; the two controls are conceptually distinct (page-level filter vs per-card behavior) and the reuse of the word is acceptable.
- Add-on authors can influence tab order only by choosing identifiers (which sort alphabetically per FR-013). The manager plugin does not expose a separate ordering control such as a `tab_order` field or a priority hook.
