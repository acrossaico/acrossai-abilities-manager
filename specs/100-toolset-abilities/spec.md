# Feature Specification: Toolset Abilities

**Feature Branch**: `100-toolset-abilities`
**Created**: 2026-09-10
**Status**: Draft
**Input**: User description: "Add one Toolset dispatcher ability per ability category. A Toolset is a single ability that takes an `action` of discover | info | execute and dispatches to the abilities registered in its own category, so an AI assistant works through ~450 abilities one category at a time instead of receiving the whole catalogue in one response."

## User Scenarios & Testing *(mandatory)*

Today an AI assistant connected to this site sees three generic entry points. One of them lists
abilities, accepts **no input whatsoever**, and returns the site's entire catalogue — roughly 450
entries — in a single response. There is no way to ask for one subject area, no search, and no
paging. The category structure that makes the Integrations screen navigable for a human never
reaches the assistant.

A **Toolset** is one entry point per ability category. It answers three questions about its own
category only: what is in here, what does this one need, and run this one.

### User Story 1 - An assistant works within one subject area (Priority: P1)

A site owner asks their assistant to do something with media. The assistant opens the Media Toolset,
asks what it contains, and receives the eleven Media abilities — not four hundred and fifty. For a
large category it can narrow by search term or sub-group, and results arrive in pages rather than
all at once.

**Why this priority**: This is the feature. Everything else supports it. Delivered alone it is
already useful — the assistant can locate the right ability by name and still invoke it through the
existing generic entry point.

**Independent Test**: Add the Media Toolset to a connected assistant. Ask it what Media abilities
exist. It returns exactly the eleven registered Media abilities with names, labels, descriptions and
sub-groups, and nothing from any other category.

**Acceptance Scenarios**:

1. **Given** a category with eleven registered abilities, **When** an assistant requests its
   contents, **Then** it receives those eleven entries and no ability belonging to another category.
2. **Given** a category with seventy-nine registered abilities and no page size specified, **When**
   an assistant requests its contents, **Then** it receives the first fifty, along with the total
   count, the number returned, the offset used, and an indication that more remain.
3. **Given** the same category, **When** an assistant supplies a search term, **Then** it receives
   only entries whose name, label or description contains that term, case-insensitively.
4. **Given** a category whose abilities carry sub-groups, **When** an assistant supplies a sub-group,
   **Then** it receives only entries in that sub-group.
5. **Given** any listing request, **When** the response is returned, **Then** it contains no input
   schema, no output schema, no annotations, and no suggested-abilities or suggested-plugins data.
6. **Given** a listing request naming a set of fields to include, **When** the response is returned,
   **Then** only those fields plus the ability name appear, and any unrecognised field name is
   ignored rather than treated as an error.

---

### User Story 2 - An assistant inspects an ability before invoking it (Priority: P2)

Having found a candidate, the assistant asks the Toolset what that ability requires — its input
shape, its output shape, and its behavioural annotations — so it can construct a valid call instead
of guessing. It can ask about several abilities in one request rather than one at a time.

**Why this priority**: Without it an assistant must guess parameters, and failed calls with wrong
arguments are worse than no call. It depends on Story 1 having identified a candidate.

**Independent Test**: Ask a Toolset about one ability in its category by name. It returns that
ability's input schema, output schema and annotations. Ask about several at once and it returns each,
plus a list of the ones it could not find.

**Acceptance Scenarios**:

1. **Given** an ability in the Toolset's category, **When** an assistant asks about it by name,
   **Then** it receives that ability's input schema, output schema and annotations.
2. **Given** a request naming up to twenty abilities, **When** it is answered, **Then** each found
   ability is described and every unfound name is reported separately.
3. **Given** a request naming an ability that exists but belongs to a different category, **When** it
   is answered, **Then** the request is refused with a machine-readable reason distinguishing "not in
   this category" from "does not exist".
4. **Given** a request naming an ability hidden from this Toolset, **When** it is answered, **Then**
   no schema is disclosed — the same gate that governs running an ability governs describing it.

---

### User Story 3 - An assistant runs an ability through its Toolset (Priority: P2)

The assistant invokes the chosen ability through its Toolset, passing that ability's own parameters.
The ability behaves exactly as it would if invoked directly: same permission checks, same input
validation, same output validation, same side effects, same audit trail.

**Why this priority**: Completes the loop, and is what allows an operator to retire the generic
entry points. It depends on Stories 1 and 2 to be useful.

**Independent Test**: Run a read-only ability through its Toolset and compare the result with
invoking it directly. Then run one the current user is not permitted to use and confirm it is
refused.

**Acceptance Scenarios**:

1. **Given** an ability in the Toolset's category and valid parameters, **When** an assistant runs it
   through the Toolset, **Then** the result is identical to invoking that ability directly.
2. **Given** an ability the current user lacks permission for, **When** an assistant runs it through
   the Toolset, **Then** it is refused with an authorisation failure, the refusal originates from the
   target ability's own permission check, and the ability does not execute.
3. **Given** an ability that accepts no input, **When** an assistant runs it through the Toolset
   passing an empty parameter object, **Then** it executes successfully.
4. **Given** any run through a Toolset, **When** the ability executes, **Then** every lifecycle
   event, override and access-control rule that applies to a direct invocation also applies here.
5. **Given** a request naming an ability outside the Toolset's category, **When** it is answered,
   **Then** the ability does not execute and the response carries a machine-readable reason.
6. **Given** a request naming another Toolset as its target, **When** it is answered, **Then** the
   request is refused — a Toolset never dispatches to a Toolset.

---

### User Story 4 - A site owner controls what an assistant can reach (Priority: P3)

The site owner's existing per-category switches on the Integrations screen continue to be the single
control. Turning a category off removes both its abilities and its Toolset. An extension point exists
for narrowing further — per role, per connection — without modifying this feature.

**Why this priority**: The controls already exist and already work; this story is about them
continuing to hold rather than being bypassed. Important, but nothing new is exposed to the site
owner.

**Independent Test**: Disable a category on the Integrations screen. Its Toolset disappears from the
assistant's available tools, and its abilities are unreachable through every Toolset.

**Acceptance Scenarios**:

1. **Given** a category disabled on the Integrations screen, **When** an assistant lists available
   tools, **Then** that category's Toolset is absent.
2. **Given** a category set to expose only specific abilities, **When** an assistant opens that
   category's Toolset, **Then** the Toolset is present and lists only the specifically-enabled
   abilities.
3. **Given** an extension that marks certain abilities as hidden, **When** an assistant lists,
   describes or runs through a Toolset, **Then** those abilities are absent from listings and refused
   for description and execution alike.
4. **Given** no extension is present, **When** an assistant opens a Toolset, **Then** it sees every
   registered ability in that category.
5. **Given** an integration category the site owner has never explicitly enabled, **When** an
   assistant lists available tools, **Then** that integration's Toolset is absent — a never-touched
   integration is off, not on.
6. **Given** that integration is then enabled on the Integrations screen, **When** an assistant lists
   available tools, **Then** its Toolset is present.

---

### User Story 5 - Categories added later are never silently missing (Priority: P3)

When an integration is activated, or a companion product adds its own category, that category gets a
Toolset without anyone editing this feature. Categories with a purpose-written Toolset use it;
anything else falls back to one generated from the category's own registered name and description.

**Why this priority**: Protects the feature from decaying as the catalogue grows. Not needed for the
first release to be useful, but the absence of it produces a silent gap rather than a visible error.

**Independent Test**: Activate an integration that registers its own category. Its Toolset appears
without any code change. Deactivate it and the Toolset disappears.

**Acceptance Scenarios**:

1. **Given** an integration is activated that registers its own ability category, **When** an
   assistant lists available tools, **Then** a Toolset for that category is present.
2. **Given** that integration is deactivated, **When** an assistant lists available tools, **Then**
   that Toolset is absent.
3. **Given** a category with no purpose-written Toolset, **When** its fallback Toolset is created,
   **Then** it takes its name and description from that category's own registration.
4. **Given** any category running on the fallback, **When** the site is in debug mode, **Then** a
   diagnostic names that category so the gap remains visible.

---

### Edge Cases

- **A category has no abilities at all.** No Toolset is created. An entry point that can only ever
  return an empty list is noise in the catalogue and misleads an assistant into thinking the subject
  area exists.
- **A category has abilities, but all are hidden.** The Toolset exists and returns an empty list with
  an explanatory message — not an error. Existence is decided by registration; contents by
  visibility.
- **A requested ability does not exist anywhere.** Reported as not found, distinctly from "exists but
  not in this category" and from "exists here but hidden".
- **A requested page starts past the end of the results.** An empty list with the true total and no
  indication of further results — not an error.
- **A requested page size exceeds the maximum.** Rejected by input validation rather than silently
  clamped, so the caller learns the real bound.
- **A field-selection list contains only unrecognised names.** The ability name is still returned.
- **A batch description request names more than the permitted maximum.** Rejected by input validation.
- **Two products claim the same Toolset name.** The later registration is skipped rather than
  overwriting the earlier one, and the collision is observable.
- **A site owner changes category settings mid-session.** The next request reflects the change; an
  assistant may need to refresh its tool list to see a Toolset appear or disappear.
- **An ability is added or removed while a request is in flight.** Each request resolves its own
  contents, so no stale set is ever served.

## Requirements *(mandatory)*

### Functional Requirements

**Existence and identity**

- **FR-001**: The system MUST provide exactly one Toolset per ability category that has at least one
  registered ability.
- **FR-002**: The system MUST NOT provide a Toolset for a category with no registered abilities.
- **FR-003**: Each Toolset MUST be identified by a name derived from its category, unique across the
  site, and stable across requests.
- **FR-004**: The system MUST skip creating a Toolset whose name is already claimed, and MUST make
  that collision observable rather than silently overwriting.
- **FR-005**: Each Toolset MUST carry a human-readable label and a description stating which category
  it covers and which actions it supports.
- **FR-006**: The system MUST provide a purpose-written Toolset for each category shipped with this
  product, and MUST generate a fallback Toolset from the category's own registered label and
  description for any other category.
- **FR-007**: The system MUST emit a diagnostic, when the site is in debug mode, naming every
  category served by a fallback Toolset.

**Grouping**

- **FR-008**: The system MUST group abilities into Toolsets by ability category, and MUST NOT use the
  Integrations screen's tab grouping for this purpose.
- **FR-009**: The system MUST NOT alter the Integrations screen's existing tabs, cards or grouping.

**Discovering**

- **FR-010**: A Toolset MUST, on request, list the abilities in its category, returning for each the
  name, label, description and sub-group.
- **FR-011**: A listing MUST NOT include input schemas, output schemas, annotations, suggested
  abilities or suggested plugins.
- **FR-012**: A listing MUST accept a search term and return only entries whose name, label or
  description contains it, case-insensitively.
- **FR-013**: A listing MUST accept a sub-group and return only entries within it.
- **FR-014**: A listing MUST be paged, defaulting to fifty entries, accepting a caller-supplied size
  up to two hundred and a caller-supplied starting offset.
- **FR-015**: A listing MUST report the total number of matching entries, the number returned, the
  offset used, and whether further entries remain.
- **FR-016**: A listing or description MUST accept a list of field names and return only those fields,
  always including the ability name, ignoring unrecognised names rather than failing.

**Describing**

- **FR-017**: A Toolset MUST, on request, return a named ability's input schema, output schema and
  annotations.
- **FR-018**: A description request MUST accept up to twenty ability names in one call and MUST report
  which of them were not found.
- **FR-019**: Description MUST apply the same category-membership and visibility rules as execution.
  An ability that may not be run through a Toolset MUST NOT have its schema disclosed by it.

**Executing**

- **FR-020**: A Toolset MUST, on request, run a named ability in its category with caller-supplied
  parameters and return its result.
- **FR-021**: Execution through a Toolset MUST produce behaviour identical to invoking that ability
  directly — the same permission checks, input validation, output validation, lifecycle events,
  overrides, access-control rules and side effects.
- **FR-022**: The system MUST pass only the caller's declared parameters to the target ability, and
  MUST NOT forward any part of the Toolset's own request envelope.
- **FR-023**: The system MUST treat an omitted or empty parameter object as valid for abilities that
  accept no input.
- **FR-024**: A Toolset MUST refuse to target another Toolset.

**Actions and responses**

- **FR-025**: Every Toolset MUST expose the same three actions — list, describe and run — under one
  consistent request shape, and MUST require the caller to state which action is intended.
- **FR-026**: The system MUST NOT infer an action when none is supplied. A caller that omits it MUST
  receive an error, never a default action's result.
- **FR-027**: The system MUST reject request fields it does not recognise.
- **FR-028**: Recoverable failures — unknown ability, ability outside this category, hidden ability,
  unrecognised action, missing ability name — MUST be returned as an unsuccessful result carrying a
  distinct machine-readable reason code, so a caller can correct itself.
- **FR-029**: Authorisation failures MUST be returned as genuine authorisation errors, distinguishable
  from the recoverable failures in FR-028.
- **FR-030**: Every response MUST identify the action it answers and whether it succeeded.

**Membership and visibility**

- **FR-031**: A Toolset's contents MUST be resolved at the moment of each request, never fixed at
  start-up, so that changes to registration or settings are reflected on the next request.
- **FR-032**: A Toolset MUST include only abilities in its own category that are intended as callable
  tools, excluding those published as read-only resources or as prompts.
- **FR-033**: A Toolset MUST exclude other Toolsets and any ability marked as protected system
  infrastructure.
- **FR-034**: The system MUST provide an extension point that can hide an individual ability from an
  individual Toolset, applied identically to listing, describing and running, and receiving enough
  context to distinguish those three cases.
- **FR-035**: With no extension present, a Toolset MUST include every registered, callable ability in
  its category.
- **FR-036**: The system MUST omit hidden abilities from listings by default, and MUST offer an
  opt-in under which they are instead reported as unavailable with a reason.
- **FR-037**: The system MUST publish a catalogue of the Toolsets it has created — category, label,
  name and member count — so other components can consume it without inspecting internals.

**Interaction with existing controls**

- **FR-038**: A Toolset MUST NOT be registered for a category the site owner has disabled on the
  Integrations screen.
- **FR-038a**: The Integrations screen holds two kinds of card with **opposite defaults**, and a
  Toolset MUST honour whichever applies to its own category. An ordinary category with no saved
  setting is enabled; an integration category with no saved setting is **disabled**, because enabling
  an integration is required to be a deliberate act by the site owner. A Toolset for an integration
  category therefore MUST NOT exist until the site owner has explicitly turned that integration on.
- **FR-038b**: A Toolset MUST determine an integration category's state through the single existing
  helper that owns that inverted default, and MUST NOT read the stored setting directly. The
  asymmetry is deliberately confined to one place; a Toolset reading around it would apply the
  ordinary enabled-by-default rule to an integration and expose it before it was ever switched on.
- **FR-039**: A Toolset's existence MUST depend only on whether its category is enabled. Whether that
  category exposes all of its abilities or only specific ones MUST have no bearing on it. A Toolset is
  infrastructure for its category, not a member of it, and is never a candidate for per-ability
  selection.
- **FR-040**: Toolsets MUST be hidden from this product's ability-management screen and from the
  interfaces that list and edit individual abilities, consistent with how existing system entry points
  are treated there.
- **FR-040a**: Toolsets MUST NOT appear as selectable entries within their category's card on the
  Integrations screen. That card lists the abilities a site owner chooses between; a Toolset is the
  route to them, not one of them, and showing it would let a site owner remove the category's entry
  point with no indication of what was lost.
- **FR-040b**: A Toolset MUST NOT be published into the catalogue that builds the Integrations
  screen's category cards. Excluding it at the source satisfies FR-040a by construction, rather than
  relying on every present and future consumer of that catalogue to filter it out.
- **FR-041**: Toolsets MUST remain selectable in a connected transport's tool-composition interface,
  so a site owner can add or remove them per connection.
- **FR-042**: Toolsets MUST NOT be marked for automatic exposure on a transport's default entry point.
  Adding this feature MUST NOT change what any existing connection already sees.

**Scope boundaries**

- **FR-043**: This feature MUST NOT create, configure or modify any connection endpoint, and MUST NOT
  modify any other component's list of exposed tools.
- **FR-044**: This feature MUST NOT alter, remove or hide the three existing generic entry points.
  Retiring them is a site owner's choice, made through existing controls.

### Key Entities

- **Toolset**: One entry point representing a single ability category. Has a stable name derived from
  its category, a label, a description, and three actions. Owns no data; its contents are derived on
  demand.
- **Ability Category**: The existing grouping every ability already declares. Carries a registered
  label and description, and is already the unit the site owner enables or disables. One category
  yields at most one Toolset.
- **Member Ability**: A registered, callable ability whose category matches a Toolset's, which is not
  itself a Toolset, is not protected system infrastructure, and is not hidden by an extension.
- **Visibility Decision**: A per-request judgement about one ability, one Toolset and one action —
  listing, describing or running. Defaults to visible; extensions may narrow it. Governs disclosure
  as strictly as it governs execution.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An assistant can retrieve the abilities for any single subject area without receiving
  any ability from another subject area.
- **SC-002**: No single listing response returns more than two hundred entries, and the default
  response returns at most fifty, regardless of how large the underlying category is.
- **SC-003**: Listing the largest category returns at most fifty entries by default, against the
  roughly four hundred and fifty an assistant receives from the current catalogue-wide listing — a
  reduction of at least 85% in entries returned per request.
- **SC-004**: An assistant can locate a named capability within a category in one request by search
  term, without paging through the full category.
- **SC-005**: 100% of abilities produce identical results, permission outcomes and side effects
  whether run directly or through a Toolset.
- **SC-006**: Zero abilities become reachable through a Toolset that the site owner has disabled on
  the Integrations screen.
- **SC-007**: Zero abilities can have their schemas retrieved through a Toolset that could not be run
  through it by the same caller.
- **SC-008**: Existing connections see no change to the tools available to them until a site owner
  deliberately adds a Toolset.
- **SC-009**: Every ability category present on the site is covered by exactly one Toolset, with no
  category left without one and no category served by two.
- **SC-010**: Activating or deactivating an integration changes the set of available Toolsets with no
  code change and no configuration step.
- **SC-011**: The combined size of the tool catalogue an assistant receives is recorded before and
  after this change, so the trade between more entry points and smaller listings is measured rather
  than assumed.

## Assumptions

- **Categories are the right grouping, and this is a decision not an inference.** Ability categories
  map one-to-one onto the product's subject areas. The Integrations screen's tab grouping was
  considered and rejected: it merges nine subject areas plus parts of two more into a single
  105-ability group with no describable purpose — the exact problem this feature exists to solve —
  and its underlying data has already drifted, with one subject area holding eleven abilities of
  which only one is tagged to its own tab. Category is declared by each ability itself, cannot drift,
  already carries a registered label and description, and is already the unit the site owner
  enables and disables.
- **A Toolset exists for an enabled category even when that category exposes only specific
  abilities** (FR-039), and this is a consequence of FR-040b rather than an exception carved out for
  Toolsets. The per-category setting decides between abilities *offered on the card*; a Toolset is
  never published to that card, so it is never one of the things being chosen between, and the
  all-versus-specific setting simply does not apply to it. It reads one thing only: is this category
  enabled. Had a Toolset instead been published to the card and then suppressed in the interface, it
  would have needed a genuine exemption — and anything not selected in specific mode is off, so
  forgetting that exemption would silently remove the Toolset from every category in that mode, with
  no way for the site owner to restore it.
- **"Enabled" also means two different things on the same screen.** Ordinary categories are enabled
  unless switched off; integration categories are disabled unless switched on. A Toolset that read the
  stored setting directly would silently apply the ordinary rule to both, and every integration's
  Toolset would appear the moment the integration's plugin was active — before the site owner had
  agreed to anything (FR-038a, FR-038b). Because a Toolset no longer rides the shared registration
  path (FR-040b), this check is written rather than inherited, which makes it something that can be
  omitted. It is called out here, given its own acceptance scenarios, and belongs among the
  merge-blocking tests.
- **"Hidden" means two different things on two different screens.** The existing system entry points
  are hidden only from the ability-management screen; they are registered by a separate component and
  never reach this product's category cards at all, so the one measure was sufficient for them. A
  Toolset is registered by this product, so it needs both: exclusion from the ability-management
  screen by the same mechanism (FR-040), and exclusion at the source from the catalogue that builds
  the category cards (FR-040b). Applying only the familiar treatment would leave a Toolset row visible
  inside every category card.
- **The default is that a Toolset sees everything registered in its category.** This is broader than
  the current generic entry points, which show only abilities individually marked for exposure and in
  practice show none. It is a deliberate choice, and the safeguards are: the site owner's category
  switches already prevented disabled categories from registering at all; exposure is not
  authorisation, so every run still passes the target ability's own permission check and most
  abilities require administrator rights; and reaching a Toolset requires an authenticated session
  against a connection a site owner explicitly configured. In effect a Toolset exposes approximately
  what the signed-in user could already do through the admin interface. FR-034 is the single
  extension point for any site wanting it narrower.
- **Retiring the three existing generic entry points is the site owner's action, not this feature's.**
  This feature only makes the alternative available (FR-044).
- **Descriptions are written by hand for the categories shipped with this product** because the
  description is the primary signal an assistant uses to choose an entry point, and generated text
  would be least specific exactly where precision matters most. Existing category labels and
  descriptions become assistant-facing as a result and are audited as part of this work — at least
  one is known stale, describing a narrow subject while its category spans a much wider one.
- **The connected transport's tool-composition interface lists abilities by their registration**, so
  Toolsets appear there for selection without any change to that component (FR-041).
- **Numbers cited are current live counts** — roughly 450 abilities across 25 categories, the largest
  holding 79 — and are expected to grow. No requirement depends on a specific count.
