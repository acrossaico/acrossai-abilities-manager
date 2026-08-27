# Feature Specification: Ability Suggestions Framework

**Feature Branch**: `095-suggested-abilities-framework`
**Created**: 2026-08-28
**Status**: Draft
**Input**: User description: "Add a suggested_abilities() framework that mirrors Feature 088's suggested_plugins — token-saving hint mechanism for AI callers with admin kill-switch and 4 initial ability overrides."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - AI caller sees cheaper alternatives when inspecting an ability (Priority: P1)

An AI caller has connected to this WordPress site through MCP and wants to make a small edit to a page. When it inspects the ability that most obviously fits its intent (say, "update page"), the details include a short list of other abilities that could accomplish a narrow edit at a fraction of the token cost — with a one-line reason for each. The AI reads these hints as part of its normal decision process and either continues with the original ability or switches. Nothing about the original ability changes; suggestions are advisory.

**Why this priority**: This is the entire point of the feature — reducing wasted LLM tokens across every content-editing interaction with the site. Without this, all subsequent stories are unnecessary.

**Independent Test**: An AI caller (or any MCP client) fetches details for an ability that has declared suggestions; the response includes a `suggested_abilities` list under the ability's metadata with at least one entry, each carrying a target ability slug and a human-readable reason.

**Acceptance Scenarios**:

1. **Given** an ability that has declared two suggested alternatives, **When** an MCP client fetches that ability's details, **Then** the response includes both alternatives with their reasons attached, and the ability's own behavior is unchanged.
2. **Given** an ability that has NOT declared any suggestions, **When** an MCP client fetches that ability's details, **Then** the response contains no `suggested_abilities` key at all (no empty list, no phantom entry).
3. **Given** an AI caller browsing the full ability list, **When** it queries `discover-abilities`, **Then** the listing pages are unchanged in size and content — suggestions never appear in listings, only in per-ability details.

---

### User Story 2 - Site administrator can turn off suggestions site-wide (Priority: P2)

The site owner opens the plugin's settings, sees a new "Ability Suggestions" section on the abilities tab, and can tick a single checkbox to disable the entire suggestion mechanism. From that moment on, no AI caller inspecting any ability sees any suggestions in the response. Unticking restores them. Nothing else about the site or any ability changes when the toggle flips.

**Why this priority**: A single kill-switch protects a nervous admin who worries about "extra noise" reaching their AI callers, without forcing them to edit any code or rewrite any ability declaration. Second priority because default-on covers the common case.

**Independent Test**: Toggle the checkbox on; any subsequent AI-facing ability-details response omits `suggested_abilities`. Toggle it off; the field returns as before.

**Acceptance Scenarios**:

1. **Given** the "Disable ability suggestions" checkbox is unchecked (default state), **When** any AI caller fetches ability details, **Then** any declared suggestions are present in the response.
2. **Given** the checkbox is checked, **When** any AI caller fetches details for the same ability, **Then** the response has no `suggested_abilities` field, but every other piece of ability metadata is preserved intact.
3. **Given** the checkbox is checked, **When** the admin unchecks it, **Then** suggestions reappear immediately on the next request — no reload or cache invalidation required.
4. **Given** the plugin is being uninstalled with "delete all data" enabled, **When** the uninstall runs, **Then** the suggestions toggle setting is deleted alongside every other plugin option.

---

### User Story 3 - Ability author declares alternatives inline in the ability class (Priority: P3)

A developer writing a new content-editing ability wants to point AI callers at a cheaper alternative when appropriate. They add one method to their ability's PHP class returning a short list of `{slug, reason}` entries. No filter registration, no wiring elsewhere, no build step. The declaration lives in the same file as the ability itself, so a code reviewer sees the two in the same diff.

**Why this priority**: This is the framework's authoring ergonomics story — separate from the P1 payload-visibility story because the framework can ship without any ability adopting it yet, and each ability can adopt it independently over time.

**Independent Test**: A developer adds a `suggested_abilities()` override to an existing ability class, deploys it, and immediately sees the entries appear under that ability's `meta.acrossai.suggested_abilities` in an MCP details fetch — with no other files changed.

**Acceptance Scenarios**:

1. **Given** a new ability class with no `suggested_abilities()` override, **When** the ability registers, **Then** its payload contains no `suggested_abilities` field (silent default).
2. **Given** an ability class overriding `suggested_abilities()` with a list of two entries, **When** the ability registers, **Then** those entries appear under `meta.acrossai.suggested_abilities` in the ability's payload, preserving order and preserving each entry's `slug` and `reason` fields exactly.
3. **Given** an ability class overriding `suggested_abilities()` to return an empty array, **When** the ability registers, **Then** the payload contains no `suggested_abilities` field (empty return is treated identically to no override).

---

### Edge Cases

- What happens when an ability author declares a suggestion pointing at an ability slug that does not exist on this site? The framework surfaces the entry as declared; the AI caller sees the suggestion, discovers the target is unavailable, and moves on. The framework does not validate slug existence — that would be a runtime check every registration and would break third-party ability plugins that ship suggestions pointing at plugins their user has not yet installed.
- What happens when a suggestion's `reason` is an empty string? The framework accepts it as declared. Ability tests SHOULD guard against empty reasons, but the framework itself does not fail-shut on them.
- What happens when both this feature's toggle AND Feature 088's plugin-suggestions toggle are both disabled? Each toggle strips only its own field. Turning off one has no effect on the other's payload.
- What happens when the admin toggles the setting mid-request? WordPress option updates take effect on the next request; in-flight requests continue with the value they read at start. No consistency guarantees within a single request.
- What happens on multisite? Same behavior as Feature 088 — the setting is per-site (options table, not sitemeta). Deferred to the same follow-up feature that handles multisite for Feature 088.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The framework MUST provide an authoring surface that lets each ability class declare zero or more suggested alternative abilities in one method call, without touching any other file.
- **FR-002**: When an AI caller fetches an ability's details, the response MUST include the declared suggestions under the ability's metadata (unless the site-wide toggle disables them).
- **FR-003**: When an AI caller fetches the ability listing (discovery), suggestions MUST NOT appear in the listing payload — the surface is details-only.
- **FR-004**: Each suggestion entry MUST include at minimum a target ability slug and a human-readable reason.
- **FR-005**: Site administrators MUST have a single toggle setting that disables the entire suggestion mechanism across every ability at once.
- **FR-006**: The toggle setting MUST default to "enabled" (suggestions visible).
- **FR-007**: When the toggle is set to "disabled", no ability's payload MUST include a `suggested_abilities` field.
- **FR-008**: When the toggle is set to "disabled", no other ability metadata MUST be affected — every other field remains identical to the enabled state.
- **FR-009**: An ability that has not declared any suggestions MUST behave identically before and after this feature ships — no new field, no schema drift.
- **FR-010**: When the plugin is uninstalled with the "delete all data" option enabled, the toggle setting MUST be removed from the options store.
- **FR-011**: The mechanism MUST be advisory only — no ability's own execution or return value depends on the presence or absence of suggestions, or on what the AI does or does not do with them.
- **FR-012**: The toggle setting MUST be discoverable in the plugin's settings area alongside the existing plugin-suggestions setting and the uninstall-data setting.

### Key Entities

- **Suggestion Entry**: A single hint that one ability's author declares about another ability. Carries a target ability slug (identifying the alternative), a reason (why the AI might consider it), and optionally a saves-hint (a free-form indicator of expected token savings).
- **Suggestions List**: An ordered list of suggestion entries attached to a single ability. Order matters — first entry is treated by the AI as the primary alternative, then the next, and so on.
- **Suggestions Toggle**: A boolean site setting owned by the administrator, controlling whether suggestion lists are surfaced in ability-details payloads. When disabled, no ability's payload includes any suggestion list.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An AI caller that inspects an ability with declared suggestions sees them in the details response without any additional request or roundtrip.
- **SC-002**: A site administrator toggling the "Disable ability suggestions" setting sees the effect on the very next AI request — no cache invalidation, no plugin reload, no page refresh required on the AI side.
- **SC-003**: An ability author can add a new suggestion in one file change — the ability's own class — and see the entry appear in that ability's payload with no other file touches.
- **SC-004**: An ability that has not declared any suggestions produces a byte-identical payload to what it produced before this feature shipped (no schema drift, no phantom empty list).
- **SC-005**: A site with the toggle disabled produces byte-identical ability-details payloads to what it produced before this feature shipped — the toggle is a true kill-switch, not a partial disable.
- **SC-006**: The initial batch of four ability overrides ships in the same release as the framework, so the admin who enables the plugin on day one already sees the token-saving alternatives on the four most valuable abilities.

## Assumptions

- Suggestions are author-curated per ability, not derived by some auto-analysis of registered abilities. This matches the Feature 088 authoring model and keeps the mechanism free of runtime discovery cost.
- The `meta.acrossai.*` metadata namespace is a stable public surface for third-party ability authors — this feature places its data there because Feature 088 established the precedent.
- The AI caller's own runtime cost model determines whether a suggestion is worth taking. This feature does not attempt to quantify or enforce cost thresholds — it only surfaces the author's intent.
- Ability details and ability listing are two separate query surfaces exposed by the MCP adapter (get-ability-info vs discover-abilities). Suggestions live only in the former.
- The plugin's existing settings page already has a mechanism for adding new toggle sections (Feature 088 established the pattern). This feature reuses that mechanism rather than introducing a new one.
- On multisite, the toggle behaves per-site (options table, not sitemeta) — matching Feature 088's current behavior. Cross-site defaults handling is deferred to whichever future feature addresses multisite for suggestion-family settings uniformly.
