# Feature Specification: Toolset Abilities

**Feature Branch**: `100-toolset-abilities`
**Created**: 2026-09-10
**Status**: Draft
**Input**: User description: "Add one Toolset dispatcher ability per ability family. A Toolset is a single ability that takes an `action` of discover | info | execute and dispatches to the abilities in its own family, so an AI assistant works through ~450 abilities one family at a time instead of receiving the whole catalogue in one response."

> **Grouping key changed from category to family (2026-09-10).** This specification originally grouped
> Toolsets by ability *category* — 25 of them. Feature 101 introduced **families**: 13 task-shaped
> groups assigned per ability, which are what the Integrations screen's tabs now show. Toolsets follow
> families, so there are 13, not 25. That is not a cosmetic swap; see *What the change to families
> settles* below, where four requirements collapse into one.

## User Scenarios & Testing *(mandatory)*

Today an AI assistant connected to this site sees three generic entry points. One of them lists
abilities, accepts **no input whatsoever**, and returns the site's entire catalogue — roughly 450
entries — in a single response. There is no way to ask for one subject area, no search, and no
paging. The structure that makes the Integrations screen navigable for a human never reaches the
assistant.

A **Toolset** is one entry point per family. It answers three questions about its own family only:
what is in here, what does this one need, and run this one.

### User Story 1 - An assistant works within one subject area (Priority: P1)

A site owner asks their assistant to do something with content. The assistant opens the Content
Toolset, asks what it contains, and receives that family's abilities — not four hundred and fifty. For
a large family it can narrow by search term, by the card an ability belongs to, or by sub-group, and
results arrive in pages rather than all at once.

**Why this priority**: This is the feature. Everything else supports it. Delivered alone it is already
useful — the assistant can locate the right ability by name and still invoke it through the existing
generic entry point.

**Independent Test**: Add the Content Toolset to a connected assistant. Ask it what content abilities
exist. It returns that family's abilities and nothing from any other family.

**Acceptance Scenarios**:

1. **Given** a family holding seven abilities, **When** an assistant requests its contents, **Then**
   it receives those seven and no ability belonging to another family.
2. **Given** a family holding seventy-one abilities and no page size specified, **When** an assistant
   requests its contents, **Then** it receives the first fifty, along with the total count, the number
   returned, the offset used, and an indication that more remain.
3. **Given** the same family, **When** an assistant supplies a search term, **Then** it receives only
   entries whose name, label or description contains it, case-insensitively.
4. **Given** a family drawing on several cards, **When** an assistant supplies a card name, **Then**
   it receives only entries from that card.
5. **Given** a family whose abilities carry sub-groups, **When** an assistant supplies a sub-group,
   **Then** it receives only entries in that sub-group.
6. **Given** any listing request, **When** the response is returned, **Then** it contains no input
   schema, no output schema, no annotations, and no suggested-abilities or suggested-plugins data.
7. **Given** a listing request naming a set of fields to include, **When** the response is returned,
   **Then** only those fields plus the ability name appear, and any unrecognised field name is ignored
   rather than treated as an error.

---

### User Story 2 - An assistant inspects an ability before invoking it (Priority: P2)

Having found a candidate, the assistant asks the Toolset what that ability requires — its input shape,
its output shape, and its behavioural annotations — so it can construct a valid call instead of
guessing. It can ask about several abilities in one request rather than one at a time.

**Why this priority**: Without it an assistant must guess parameters, and failed calls with wrong
arguments are worse than no call. It depends on Story 1 having identified a candidate.

**Independent Test**: Ask a Toolset about one ability in its family by name. It returns that ability's
input schema, output schema and annotations. Ask about several at once and it returns each, plus a
list of the ones it could not find.

**Acceptance Scenarios**:

1. **Given** an ability in the Toolset's family, **When** an assistant asks about it by name, **Then**
   it receives that ability's input schema, output schema and annotations.
2. **Given** a request naming up to twenty abilities, **When** it is answered, **Then** each found
   ability is described and every unfound name is reported separately.
3. **Given** a request naming an ability that exists but belongs to a different family, **When** it is
   answered, **Then** the request is refused with a machine-readable reason distinguishing "not in
   this family" from "does not exist".
4. **Given** a request naming an ability hidden from this Toolset, **When** it is answered, **Then**
   no schema is disclosed — the same gate that governs running an ability governs describing it.

---

### User Story 3 - An assistant runs an ability through its Toolset (Priority: P2)

The assistant invokes the chosen ability through its Toolset, passing that ability's own parameters.
The ability behaves exactly as it would if invoked directly: same permission checks, same input
validation, same output validation, same side effects, same audit trail.

**Why this priority**: Completes the loop, and is what allows a site owner to retire the generic entry
points. It depends on Stories 1 and 2 to be useful.

**Independent Test**: Run a read-only ability through its Toolset and compare the result with invoking
it directly. Then run one the current user is not permitted to use and confirm it is refused.

**Acceptance Scenarios**:

1. **Given** an ability in the Toolset's family and valid parameters, **When** an assistant runs it
   through the Toolset, **Then** the result is identical to invoking that ability directly.
2. **Given** an ability the current user lacks permission for, **When** an assistant runs it through
   the Toolset, **Then** it is refused with an authorisation failure, the refusal originates from the
   target ability's own permission check, and the ability does not execute.
3. **Given** an ability that accepts no input, **When** an assistant runs it through the Toolset
   passing an empty parameter object, **Then** it executes successfully.
4. **Given** any run through a Toolset, **When** the ability executes, **Then** every lifecycle event,
   override and access-control rule that applies to a direct invocation also applies here.
5. **Given** a request naming an ability outside the Toolset's family, **When** it is answered,
   **Then** the ability does not execute and the response carries a machine-readable reason.
6. **Given** a request naming another Toolset as its target, **When** it is answered, **Then** the
   request is refused — a Toolset never dispatches to a Toolset.

---

### User Story 4 - A site owner controls what an assistant can reach (Priority: P3)

The site owner's existing per-card switches on the Integrations screen remain the control. Switching a
card off removes its abilities from every Toolset that drew on them. An extension point exists for
narrowing further — per connection, or on any basis a policy chooses — without modifying this feature.

**Why this priority**: The controls already exist and already work; this story is about them
continuing to hold rather than being bypassed. Important, but nothing new is exposed to the site
owner.

**Independent Test**: Switch a card off on the Integrations screen. Its abilities become unreachable
through every Toolset, and a Toolset whose every card is off disappears entirely.

**Acceptance Scenarios**:

1. **Given** a card switched off on the Integrations screen, **When** an assistant opens a Toolset
   that drew on it, **Then** that card's abilities are absent from the listing.
2. **Given** a family whose every contributing card is switched off, **When** an assistant lists
   available tools, **Then** that family's Toolset is absent.
3. **Given** a card set to expose only specific abilities, **When** an assistant opens a Toolset that
   draws on it, **Then** only the specifically-enabled abilities appear.
4. **Given** an extension that marks certain abilities as hidden, **When** an assistant lists,
   describes or runs through a Toolset, **Then** those abilities are absent from listings and refused
   for description and execution alike.
5. **Given** no extension is present, **When** an assistant opens a Toolset, **Then** it sees every
   registered ability in that family.

---

### User Story 5 - Families added later are never silently missing (Priority: P3)

When an integration is activated, its family gains a Toolset without anyone editing this feature.
Families with a purpose-written Toolset use it; anything else falls back to one generated from the
family's own name.

**Why this priority**: Protects the feature from decaying as the catalogue grows. Not needed for the
first release to be useful, but the absence of it produces a silent gap rather than a visible error.

**Independent Test**: Activate an integration. Its Toolset appears without any code change. Deactivate
it and the Toolset disappears.

**Acceptance Scenarios**:

1. **Given** an integration is activated whose abilities form their own family, **When** an assistant
   lists available tools, **Then** a Toolset for that family is present.
2. **Given** that integration is deactivated, **When** an assistant lists available tools, **Then**
   that Toolset is absent.
3. **Given** a family with no purpose-written Toolset, **When** its fallback Toolset is created,
   **Then** it takes its name from that family's own identifier.
4. **Given** any family running on the fallback, **When** the site is in debug mode, **Then** a
   diagnostic names that family so the gap remains visible.

---

### Edge Cases

- **A family has no registered abilities.** No Toolset is created. An entry point that can only ever
  return an empty list is noise in the catalogue and misleads an assistant into thinking the subject
  area exists.
- **A family has abilities, but all are hidden.** The Toolset exists and returns an empty list with an
  explanatory message — not an error. Existence is decided by registration; contents by visibility.
- **A requested ability does not exist anywhere.** Reported as not found, distinctly from "exists but
  not in this family" and from "exists here but hidden".
- **A requested page starts past the end of the results.** An empty list with the true total and no
  indication of further results — not an error.
- **A requested page size exceeds the maximum.** Rejected by input validation rather than silently
  clamped, so the caller learns the real bound.
- **A field-selection list contains only unrecognised names.** The ability name is still returned.
- **A batch description request names more than the permitted maximum.** Rejected by input validation.
- **Two products claim the same Toolset name.** The later registration is skipped rather than
  overwriting the earlier one, and the collision is observable.
- **A card contributes to two families.** Its abilities appear in whichever Toolset matches each
  ability's own family, never in both.
- **A site owner changes card settings mid-session.** The next request reflects the change; an
  assistant may need to refresh its tool list to see a Toolset appear or disappear.
- **An ability is added or removed while a request is in flight.** Each request resolves its own
  contents, so no stale set is ever served.
- **Caching is unavailable or disabled.** Every listing still returns the correct members; the only
  difference is that the list is derived each time.
- **A cached entry survives an upgrade that changed its shape.** The version stamp makes it a miss, so
  a stale shape is never read back.
- **An extension narrows visibility per connection.** Two connections configured to expose different
  sets receive different listings from the same cached member list, because visibility is applied after
  the cache, never inside it.

## Requirements *(mandatory)*

### Functional Requirements

**Existence and identity**

- **FR-001**: The system MUST provide exactly one Toolset per family that has at least one registered
  ability.
- **FR-002**: The system MUST NOT provide a Toolset for a family with no registered abilities.
- **FR-003**: Each Toolset MUST be identified by a name derived from its family, unique across the
  site, and stable across requests.
- **FR-004**: The system MUST skip creating a Toolset whose name is already claimed, and MUST make
  that collision observable rather than silently overwriting.
- **FR-005**: Each Toolset MUST carry a human-readable label and a description stating which family it
  covers and which actions it supports.
- **FR-006**: The system MUST provide a purpose-written Toolset for each family shipped with this
  product, and MUST generate a fallback Toolset from the family's own identifier for any other family.
- **FR-007**: The system MUST emit a diagnostic, when the site is in debug mode, naming every family
  served by a fallback Toolset.
- **FR-008**: Every Toolset MUST itself declare a category, because the platform requires one and
  refuses to register an ability whose category is unknown. That category MUST be distinct from any
  category holding real abilities.

**Shared behaviour lives in one place**

- **FR-008a**: Every behaviour common to Toolsets — the request and response shapes, the three-action
  dispatch, member resolution, search, card and sub-group filtering, pagination, and every permission
  check — MUST live in **exactly one abstract definition that all Toolsets extend**. There MUST NOT be
  a second place where any of it is implemented.
- **FR-008b**: An individual Toolset MUST declare only its own identity: which family it covers, its
  name, its label and its description. It MUST NOT carry behaviour. Anything appearing in one Toolset
  that could apply to another belongs in the shared definition instead.
- **FR-008c**: The registration of the Toolsets' shared category, the published catalogue of Toolsets,
  and the fallback that covers a family with no purpose-written Toolset MUST also live with the shared
  definition rather than in separate components, so that adding a family means adding one declaration
  and nothing else.

**Grouping**

- **FR-009**: The system MUST group abilities into Toolsets by **family**, the per-ability grouping the
  Integrations screen's tabs display.
- **FR-010**: The system MUST NOT group Toolsets by ability category. A family may draw on several
  cards, and several families may draw on one card.
- **FR-011**: The system MUST NOT alter the Integrations screen's existing tabs, cards or grouping.

**Discovering**

- **FR-012**: A Toolset MUST, on request, list the abilities in its family, returning for each the
  name, label, description, card and sub-group.
- **FR-013**: A listing MUST NOT include input schemas, output schemas, annotations, suggested
  abilities or suggested plugins.
- **FR-014**: A listing MUST accept a search term and return only entries whose name, label or
  description contains it, case-insensitively.
- **FR-015**: A listing MUST accept a card name and return only entries drawn from that card. This is
  the primary narrowing axis for a large family, which may span five cards.
- **FR-016**: A listing MUST accept a sub-group and return only entries within it.
- **FR-017**: A listing MUST be paged, defaulting to fifty entries, accepting a caller-supplied size up
  to two hundred and a caller-supplied starting offset.
- **FR-018**: A listing MUST report the total number of matching entries, the number returned, the
  offset used, and whether further entries remain.
- **FR-019**: A listing or description MUST accept a list of field names and return only those fields,
  always including the ability name, ignoring unrecognised names rather than failing.

**Describing**

- **FR-020**: A Toolset MUST, on request, return a named ability's input schema, output schema and
  annotations.
- **FR-021**: A description request MUST accept up to twenty ability names in one call and MUST report
  which of them were not found.
- **FR-022**: Description MUST apply the same family-membership and visibility rules as execution. An
  ability that may not be run through a Toolset MUST NOT have its schema disclosed by it.

**Executing**

- **FR-023**: A Toolset MUST, on request, run a named ability in its family with caller-supplied
  parameters and return its result.
- **FR-024**: Execution through a Toolset MUST produce behaviour identical to invoking that ability
  directly — the same permission checks, input validation, output validation, lifecycle events,
  overrides, access-control rules and side effects.
- **FR-025**: The system MUST pass only the caller's declared parameters to the target ability, and
  MUST NOT forward any part of the Toolset's own request envelope.
- **FR-026**: The system MUST treat an omitted or empty parameter object as valid for abilities that
  accept no input.
- **FR-027**: A Toolset MUST refuse to target another Toolset.

**Actions and responses**

- **FR-028**: Every Toolset MUST expose the same three actions — list, describe and run — under one
  consistent request shape, and MUST require the caller to state which action is intended.
- **FR-029**: The system MUST NOT infer an action when none is supplied. A caller that omits it MUST
  receive an error, never a default action's result.
- **FR-030**: The system MUST reject request fields it does not recognise.
- **FR-031**: Recoverable failures — unknown ability, ability outside this family, hidden ability,
  unrecognised action, missing ability name — MUST be returned as an unsuccessful result carrying a
  distinct machine-readable reason code, so a caller can correct itself.
- **FR-032**: Authorisation failures MUST be returned as genuine authorisation errors, distinguishable
  from the recoverable failures in FR-031.
- **FR-033**: Every response MUST identify the action it answers and whether it succeeded.

**Membership and visibility**

- **FR-034**: A Toolset's contents MUST be resolved at the moment of each request, never fixed at
  start-up, so that changes to registration or settings are reflected on the next request.
- **FR-035**: A Toolset MUST include only abilities in its own family that are intended as callable
  tools, excluding those published as read-only resources or as prompts.
- **FR-036**: A Toolset MUST exclude other Toolsets and any ability marked as protected system
  infrastructure.
- **FR-037**: The system MUST provide an extension point that can hide an individual ability from an
  individual Toolset, applied identically to listing, describing and running, and receiving enough
  context to distinguish those three cases.
- **FR-038**: With no extension present, a Toolset MUST include every registered, callable ability in
  its family.
- **FR-039**: The system MUST omit hidden abilities from listings by default, and MUST offer an opt-in
  under which they are instead reported as unavailable with a reason.
- **FR-040**: The system MUST publish a catalogue of the Toolsets it has created — family, label, name
  and member count — so other components can consume it without inspecting internals.

**Caching the member list**

- **FR-040a**: The system MUST cache each family's resolved member list, so that repeated listings do
  not re-derive it from the whole catalogue on every request.
- **FR-040b**: Only facts derived from **registration** may be cached — which abilities exist, which
  family and card each belongs to, and whether each is a callable tool. **The system MUST NOT cache
  anything that can vary between requests**: visibility decisions, permission outcomes, or any value an
  extension resolves from the context of the call. Caching such a value would serve one request's view
  of the catalogue to another, which is a disclosure failure rather than a stale cache.

  This is not hypothetical. The equivalent extension point already shipped in the connected transport
  varies by **which connection is handling the request** — two connections on the same site can be
  configured to expose different sets, and the value is resolved from request-scoped state. A cached
  post-filter listing would hand one connection's view to another.
- **FR-040c**: Every cached entry MUST be discarded, and the list re-derived, when any of the following
  occurs:
  - this plugin is activated or deactivated;
  - **any** plugin is activated or deactivated, since an integration's abilities appear and disappear
    with their host;
  - an update completes for a plugin, theme or the platform itself;
  - the site owner saves the Integrations settings, including a card being switched on or off, moved
    between all and specific, or having its selection changed;
  - an ability is created, updated or deleted through this product's own screens;
  - an ability's stored overrides change;
  - the active site changes, on installations hosting more than one.
- **FR-040d**: The system MUST publish an action allowing any other component to discard the cache, so
  a product that changes what registers is not forced to guess at internals.
- **FR-040e**: The cached entry MUST carry a version, and a version mismatch MUST be treated as a miss.
  An upgrade that changes the shape of what is stored MUST NOT be able to serve an entry written by a
  previous version.
- **FR-040f**: The cache MUST carry an expiry as a backstop, so that a trigger nobody anticipated
  produces stale results for a bounded time rather than indefinitely.
- **FR-040g**: A miss, an expiry or a discarded entry MUST be indistinguishable in behaviour from a
  hit. Correctness MUST NOT depend on the cache being warm, and the feature MUST work identically with
  caching unavailable.
- **FR-040h**: Saving the Integrations settings MUST announce itself so that FR-040c can be satisfied.
  No such announcement exists today, which is why this is a requirement rather than an assumption.

**Interaction with existing controls**

- **FR-041**: A Toolset MUST reflect the site owner's per-card settings without consulting them
  directly. A card switched off, or set to expose only specific abilities, already prevents the
  affected abilities from registering; a Toolset that resolves its members from what is registered
  therefore inherits every such setting, including the inverted default that leaves an untouched
  integration card switched off.
- **FR-042**: Toolsets MUST be hidden from this product's ability-management screen and from the
  interfaces that list and edit individual abilities.
- **FR-043**: Toolsets MUST NOT appear as selectable entries within any card on the Integrations
  screen, and MUST NOT be published into the catalogue that builds those cards. Excluding them at the
  source satisfies this by construction rather than relying on every consumer to filter them out.
- **FR-044**: Toolsets MUST remain selectable in a connected transport's tool-composition interface,
  so a site owner can add or remove them per connection.
- **FR-045**: Toolsets MUST NOT be marked for automatic exposure on a transport's default entry point.
  Adding this feature MUST NOT change what any existing connection already sees.

**Scope boundaries**

- **FR-046**: This feature MUST NOT create, configure or modify any connection endpoint, and MUST NOT
  modify any other component's list of exposed tools.
- **FR-047**: This feature MUST NOT alter, remove or hide the three existing generic entry points.
  Retiring them is a site owner's choice, made through existing controls.

### Key Entities

- **Toolset**: One entry point representing a single family. Has a stable name derived from its
  family, a label, a description, and three actions. Owns no data; its contents are derived on demand.
- **Family**: The per-ability grouping introduced by Feature 101 and displayed as the Integrations
  screen's tabs. Thirteen exist; two appear only while their integration is active. One family yields
  at most one Toolset.
- **Card**: The per-folder grouping a family draws on, and the unit a site owner enables or disables.
  A family may span several cards; a card may contribute to two families. Serves as a narrowing axis
  inside a listing.
- **Member Ability**: A registered, callable ability whose family matches a Toolset's, which is not
  itself a Toolset, is not protected system infrastructure, and is not hidden by an extension.
- **Visibility Decision**: A per-request judgement about one ability, one Toolset and one action —
  listing, describing or running. Defaults to visible; extensions may narrow it. Governs disclosure as
  strictly as it governs execution.

## What the change to families settles

Grouping by family rather than category is not a rename. It removes a problem the category-based draft
had to legislate around.

**Four requirements collapse into one.** The earlier draft needed separate rules for: not registering a
Toolset for a disabled category; honouring the opposite enable-defaults of ordinary and integration
cards; reading that inverted default only through the single helper that owns it; and exempting a
Toolset from the per-ability selection its own category applied in "specific" mode. All four existed
because a Toolset shared a category with the abilities it dispatched to, so the site owner's setting
for that category applied to the Toolset itself.

A family Toolset shares a category with nothing. It declares its own (FR-008), and resolves its members
from what is registered (FR-034). A card that is switched off, or set to expose only some abilities,
already prevents those abilities from registering — so the Toolset inherits every setting without
asking about any of them. That is FR-041, and it replaces the four.

**Thirteen instead of twenty-five.** Tool-selection accuracy is documented to degrade past 30–50
available tools, with measurements placing smaller models below 90% between 10 and 15. Twenty-five was
past one of those thresholds and close to the other; thirteen is comfortably inside both.

**The card becomes a narrowing axis rather than a boundary.** The largest family holds seventy-one
abilities across five cards. FR-015 lets an assistant ask for one card's worth — "just the comments
ones" — which is the granularity the category-based design would have given as separate tools, without
spending a tool slot on each.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An assistant can retrieve the abilities for any single subject area without receiving any
  ability from another subject area.
- **SC-002**: No single listing response returns more than two hundred entries, and the default
  response returns at most fifty, regardless of how large the family is.
- **SC-003**: Listing the largest family returns at most fifty entries by default, against the roughly
  four hundred and fifty an assistant receives from the current catalogue-wide listing — a reduction of
  at least 85% in entries returned per request.
- **SC-004**: An assistant can locate a named capability within a family in one request, by search term
  or by card, without paging through the whole family.
- **SC-005**: 100% of abilities produce identical results, permission outcomes and side effects whether
  run directly or through a Toolset.
- **SC-006**: Zero abilities become reachable through a Toolset that the site owner has switched off on
  the Integrations screen.
- **SC-007**: Zero abilities can have their schemas retrieved through a Toolset that could not be run
  through it by the same caller.
- **SC-008**: Existing connections see no change to the tools available to them until a site owner
  deliberately adds a Toolset.
- **SC-009**: Every family present on the site is covered by exactly one Toolset, with no family left
  without one and no family served by two.
- **SC-010**: The number of Toolsets stays at or below the threshold beyond which tool-selection
  accuracy is known to degrade.
- **SC-011**: Activating or deactivating an integration changes the set of available Toolsets with no
  code change and no configuration step.
- **SC-012**: The combined size of the tool catalogue an assistant receives is recorded before and
  after this change, so the trade between more entry points and smaller listings is measured rather
  than assumed.
- **SC-013**: A listing served from cache returns byte-identical members to the same listing derived
  from scratch.
- **SC-014**: Zero cached values vary by request context. Two connections configured to expose
  different sets receive listings that differ correctly, with no cached value shared between them that
  should not be.
- **SC-015**: Every trigger in FR-040c demonstrably discards the cache, verified per trigger rather
  than in aggregate.

## Assumptions

- **Families are the right grouping, and this is a decision rather than an inference.** Families are
  task-shaped: each answers "what am I trying to do". Categories are folder-shaped, and there are
  twenty-five of them — enough tools to sit past the threshold where an assistant's tool selection
  measurably degrades. Feature 101 established families for the admin interface; using the same
  taxonomy here means one concept serves both surfaces rather than two competing ones.
- **A Toolset needs a category of its own.** The platform requires every ability to declare a
  registered category and refuses to register one whose category is unknown. Giving Toolsets a
  dedicated category is what decouples them from the settings applying to the abilities they dispatch
  to.
- **Resolving members from what is registered is what makes the site owner's settings inherited rather
  than re-implemented.** Every enable, disable and specific-selection already decides whether an
  ability registers at all. A Toolset that asks "what is registered in my family" gets all of that for
  free, including the inverted default that leaves an untouched integration card off.
- **The default is that a Toolset sees everything registered in its family.** This is broader than the
  current generic entry points, which show only abilities individually marked for exposure and in
  practice show none. The safeguards are: the Integrations screen already prevented every switched-off
  card from registering; exposure is not authorisation, so every run still passes the target ability's
  own permission check and most abilities require administrator rights; and reaching a Toolset requires
  an authenticated session against a connection a site owner explicitly configured. In effect a Toolset
  exposes approximately what the signed-in user could already do through the admin interface.
- **Retiring the three existing generic entry points is the site owner's action, not this feature's.**
  This feature only makes the alternative available.
- **Descriptions are written by hand for the families shipped with this product**, because the
  description is the primary signal an assistant uses to choose an entry point, and generated text
  would be least specific exactly where precision matters most.
- **The connected transport's tool-composition interface lists abilities by their registration**, so
  Toolsets appear there for selection without any change to that component.
- **The cache is defensive rather than a performance fix.** Resolving a family means filtering a few
  hundred already-loaded ability objects, which is not expensive. The cache exists so that a listing
  does not repeat that work per request, and its real risk is staleness rather than cost — which is
  why FR-040c enumerates triggers rather than relying on expiry, and why FR-040g requires correctness
  to hold with the cache absent.
- **Numbers cited are current live counts** — thirteen families over roughly 450 abilities, the largest
  family holding 71 across five cards — and are expected to grow. No requirement depends on a specific
  count.

## Dependencies

- **Feature 101** (merged as `5cf50ec4`) established families, without which this feature has no
  grouping to use. It also fixed a category that had never been registered, which is why the ability
  count here is 451 rather than the 444 that actually registered before it.
- **A known defect in the connected transport blocks the end state.** Its call-time gate compares the
  sanitised tool name a client sends against raw ability identifiers, so every curated tool that is not
  one of the three hardcoded generic ones is refused. Toolsets will be refused by it until that
  comparison normalises both sides. Out of scope here, but scheduling this feature without scheduling
  that one produces a feature that ships and then does not work.
