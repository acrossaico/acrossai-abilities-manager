# Feature Specification: Ability-level suggested-plugins framework

**Feature Branch**: `088-ability-suggested-plugins-framework`
**Created**: 2026-08-24
**Status**: Draft
**Input**: User description: "Framework-level mechanism where any ability can advertise suggested external WordPress plugins the site admin might install as alternatives or specialists in that ability's scope. The 4 search-replace abilities will be the first consumers (in a separate follow-up feature); this feature ships the framework only. Includes a single admin kill-switch to disable all suggestions site-wide (opt-out, default on). Suggested-plugin entries include ability-source flags indicating whether installing the suggested plugin contributes native abilities, enables AcrossAI integration abilities, both, or neither."

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Site admin discovers a suggested specialist plugin while browsing the Library (Priority: P1)

A site administrator opens the AcrossAI Abilities Library page and reviews the abilities on offer. On some ability cards they see a small "Consider also" section listing one or two external WordPress plugins recommended for that ability's scope, each with a one-line reason. When a suggested plugin is already installed and active, its entry shows an "Active" badge; otherwise the entry shows an "Install" link taking the admin to the plugin's page on wordpress.org.

**Why this priority**: This is the whole point of the feature — surface informed recommendations to the admin exactly where they're already thinking about the ability. Without this, none of the downstream metadata matters. Every other slice reinforces this experience.

**Independent Test**: With the framework in place and one test ability declaring a suggestion for a known-installed plugin, load the Library page and confirm the suggestion appears on that card with the correct "Active" or "Install" badge. Cards for abilities that declare no suggestions render exactly as before.

**Acceptance Scenarios**:

1. **Given** an ability that declares one suggested plugin and the suggested plugin is active on the site, **When** the site admin loads the Library page, **Then** the ability card shows a "Consider also" section listing the plugin name, its one-line reason, and an "Active" status badge.
2. **Given** an ability that declares one suggested plugin and the suggested plugin is NOT installed, **When** the site admin loads the Library page, **Then** the same section shows an "Install" link that navigates to `https://wordpress.org/plugins/{slug}/`.
3. **Given** an ability that declares no suggestions, **When** the site admin loads the Library page, **Then** that ability card renders exactly as it did before this feature (no empty section, no visual change).

---

### User Story 2 — Site admin turns off all plugin suggestions site-wide (Priority: P2)

A site administrator prefers a clean Library page without any external-plugin advocacy. They visit the AcrossAI settings page and check a new "Disable the Plugin suggestion" option. After saving, no ability card anywhere in the plugin shows a "Consider also" section, and no suggested-plugin metadata is emitted to external consumers (MCP agents, REST clients). Unchecking the option restores the previous behaviour.

**Why this priority**: The framework must be governable in one place. A single opt-out gives admins full control without needing to touch each ability's declaration. This is a small addition to an existing settings page, but the trust it establishes matters.

**Independent Test**: With the framework in place and at least one ability declaring a suggestion, toggle the settings checkbox on and off. Confirm the "Consider also" section disappears/reappears on the ability card on next page load, and confirm the ability payload served over REST no longer/again includes the suggestion metadata.

**Acceptance Scenarios**:

1. **Given** the setting is unchecked (default) and an ability declares a suggestion, **When** the site admin loads the Library page, **Then** the "Consider also" section is visible on that card.
2. **Given** the setting is checked and the same ability declares the same suggestion, **When** the site admin loads the Library page, **Then** the "Consider also" section is absent from every card, regardless of what individual abilities declare.
3. **Given** the plugin data-removal option is enabled at uninstall time, **When** the site admin uninstalls the plugin, **Then** the setting is removed from the database along with other plugin data.
4. **Given** the plugin data-removal option is NOT enabled at uninstall time, **When** the site admin uninstalls and later reinstalls the plugin, **Then** the setting retains its previous value (checked or unchecked).

---

### User Story 3 — MCP-connected agent receives suggested-plugin context alongside ability discovery (Priority: P3)

An MCP client (external agent) queries the site's ability discovery endpoint. For each ability, the agent receives the standard fields plus, when relevant, a `suggested_plugins` list containing each suggestion's slug, name, reason, install status, and two boolean signals indicating whether installing that plugin would add native abilities, unlock built-in integration abilities, both, or neither. The agent can relay these suggestions to a human operator or present them as follow-up install options.

**Why this priority**: Agents should have the same informed-choice context the admin has. Without this, agent-driven workflows silently miss the value proposition of installing a suggested specialist. Lower priority than P1/P2 because the framework's admin-facing value must land first; MCP payloads are a downstream automatic benefit once P1 is real.

**Independent Test**: With the framework in place, the kill-switch OFF, and an ability declaring a suggestion, invoke the MCP `discover-abilities` tool and confirm the response for that ability includes the `suggested_plugins` list with all fields populated correctly, including the resolved install-status flag.

**Acceptance Scenarios**:

1. **Given** an ability declares one suggestion and the kill-switch is off, **When** an MCP agent calls ability discovery, **Then** the payload for that ability includes `suggested_plugins` with the declared entries and a resolved install-status flag on each.
2. **Given** an ability declares one suggestion and the kill-switch is on, **When** an MCP agent calls ability discovery, **Then** the payload for that ability includes no `suggested_plugins` field (or an empty list).
3. **Given** a suggestion whose author flagged both "native abilities" and "AcrossAI integration" as true, **When** the agent receives the payload, **Then** both boolean flags are present and true on that entry.

---

### Edge Cases

- **Ability declares suggestions but omits optional fields** (e.g. no `url`, no `covers`, missing both ability-source booleans): the system falls back to sensible defaults (`url` defaults to `https://wordpress.org/plugins/{slug}/`; missing booleans default to `false` — "UI-only" pill label).
- **Ability declares a suggestion for a plugin slug that does not exist on wordpress.org**: the "Install" link still points to the constructed URL; wordpress.org shows its own 404. The system does not validate slug existence.
- **Suggested plugin is installed but not active**: the entry shows an "Install" link (same as not installed); "Active" is shown only when both installed AND activated.
- **The kill-switch is on and an ability declares suggestions**: the suggestions are consistently absent from all consumers (Library UI, MCP payloads, REST payloads) — the gate is one-place, not UI-only.
- **Ability declaration returns an empty suggestions array**: identical to omitting the declaration entirely — no visual or payload change.
- **Suggestion entry is malformed** (missing `slug`, `name`, or `reason`): the system tolerates malformed entries gracefully — either skipping them silently or exposing them with placeholder text — without crashing the ability card or the payload response.
- **Existing abilities are untouched**: no existing ability declares suggestions in this feature; the whole framework is dormant except during test fixtures until the follow-up retrofit ships.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Any ability MAY declare one or more suggested external plugins as optional metadata; abilities that do not declare suggestions MUST behave and render exactly as they did before this feature.
- **FR-002**: Each suggested-plugin entry MUST include a wordpress.org plugin slug, a display name, and a one-line reason; each entry MAY include an override URL, a short scope summary, and two boolean flags indicating (a) whether the suggested plugin ships its own agent-discoverable abilities and (b) whether the AcrossAI plugin ships integration abilities for it.
- **FR-003**: When an ability declares suggestions and the site-wide kill-switch is off, every ability payload consumer (Library UI, MCP ability discovery, REST ability list) MUST receive the suggestion metadata as part of the ability's public fields.
- **FR-004**: The Library UI MUST render a "Consider also" section on an ability card only when that ability has at least one suggestion in its payload; the section MUST show each suggestion's name, reason, install-status indicator (active vs install link), and a source label derived from the two boolean flags.
- **FR-005**: The system MUST expose install status for each suggestion — indicating whether the suggested plugin is currently installed AND activated on the site.
- **FR-006**: The site admin MUST be able to disable all plugin suggestions site-wide via a single boolean setting on the AcrossAI settings page; the setting MUST default to enabled (suggestions shown), be persisted across sessions, and take effect on the next page load or API call after saving.
- **FR-007**: When the site-wide kill-switch is on, no consumer of ability payloads MUST receive suggestion metadata, regardless of what individual abilities declare.
- **FR-008**: When the plugin is uninstalled with the plugin-data-removal option enabled, the kill-switch setting MUST be removed from the database; when the plugin-data-removal option is not enabled, the setting MUST be preserved for re-activation.
- **FR-009**: Malformed or partially-declared suggestion entries MUST NOT crash the ability card, the ability payload, or the settings page; the system MUST degrade gracefully to sensible defaults.
- **FR-010**: The framework MUST support all four combinations of the two ability-source boolean flags (both false, one true and one false in either direction, both true) without visual or payload ambiguity.

### Key Entities

- **Suggested Plugin Entry**: An author-curated description of an external WordPress plugin recommended for a specific ability's scope. Attributes: wordpress.org slug (identifier); display name; one-line reason (why this plugin for this ability); optional override URL and scope summary; two boolean flags describing what installing the plugin contributes to the agent-discoverable ability surface. Each entry is attached to exactly one ability; each ability may attach zero or more entries.
- **Plugin Suggestion Kill-switch**: A single site-wide boolean setting that, when enabled, hides all suggestion metadata from every consumer. Default: disabled (suggestions shown). Owned by the AcrossAI settings page; removed on plugin uninstall when the admin has opted to remove plugin data.
- **Install Status**: A computed, per-request flag on each suggested-plugin entry indicating whether the suggested plugin is installed AND active on the site. Not persisted — resolved fresh each time the ability payload is served.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of ability cards render exactly as they did before this feature when their ability declares no suggestions — no visual change on any of the 500+ existing abilities.
- **SC-002**: An ability card that declares one or more suggestions renders its "Consider also" section within a single page load, with correct install-status badges on every entry.
- **SC-003**: Toggling the kill-switch on and off changes the visibility of the "Consider also" section on affected cards on the next page load — no manual cache flush required.
- **SC-004**: An MCP agent querying ability discovery with the kill-switch off receives the `suggested_plugins` metadata on abilities that declare it; with the kill-switch on, receives no such metadata on any ability.
- **SC-005**: Uninstalling the plugin with plugin-data-removal enabled removes the kill-switch setting from the database; uninstalling without that option preserves the setting so a reinstall respects the previous choice.
- **SC-006**: The site admin can locate and toggle the kill-switch in under 30 seconds starting from the WordPress admin dashboard (single visible checkbox on the existing AcrossAI settings page).
- **SC-007**: A malformed or partially-declared suggestion entry never causes a fatal error, blank card, or dropped payload response.

## Assumptions

- The existing Library UI card layout is the correct surface for surfacing suggestions; a small "Consider also" section fits without redesigning the card.
- The existing AcrossAI settings page is the correct surface for the kill-switch; a single visible checkbox is enough (no dedicated sub-page).
- Suggested-plugin metadata (including the two source-flag booleans) is curated by the ability author at declaration time; the system does not runtime-detect whether a suggested plugin ships its own abilities or whether AcrossAI has an integration for it.
- Site admins will decide independently whether to install a suggested plugin. The framework does not auto-install or auto-activate anything.
- Existing 500+ abilities are untouched by this feature. No ability declares suggestions yet; the framework is dormant except during test fixtures until a follow-up retrofit feature (out of scope here) opts specific abilities in.
- The wordpress.org URL for a plugin follows the standard `https://wordpress.org/plugins/{slug}/` pattern. The system does not validate that the slug corresponds to a live wordpress.org plugin listing.
- The "Install status" flag reflects only whether the plugin is currently active on this WordPress site; it does not track version compatibility or health.
