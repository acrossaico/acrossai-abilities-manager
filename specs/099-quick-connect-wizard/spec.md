# Feature Specification: Quick Connect Onboarding Wizard

**Feature Branch**: `099-quick-connect-wizard`
**Created**: 2026-09-07
**Status**: Draft
**Input**: User description: "Add a Quick Connect onboarding wizard to AcrossAI Abilities Manager, ported from the Quick Connect wizard in the sibling AcrossAI MCP Manager plugin. Seven guided screens plus a completion summary: ability count, how to edit an ability (video), bulk actions (video), integrations showcase, connect a transport (AcrossAI MCP Manager recommended, or MCP Adapter), MCP Adapter install instructions, enabling abilities for the MCP Adapter server. Opens automatically on activation only when AcrossAI MCP Manager is not already present. Visual parity with the sibling wizard — same logo, same pulsing load effect, same styling — is a hard requirement."

## Clarifications

### Session 2026-09-07

- Q: Should an already-active alternative transport (MCP Adapter) also suppress the wizard's automatic opening? → A: No — only the recommended transport suppresses it. Sites running the alternative transport still get the wizard automatically, so they are introduced to the recommended one.
- Q: What does the transport screen show to an administrator who already has the alternative transport active? → A: Keep recommending the recommended transport (still marked and preselected), but let them choose the alternative and continue. When the alternative is already installed, skip its installation instructions and go straight to the walkthrough for reaching abilities through its default server.
- Q: What happens when the wizard cannot detect the alternative transport after the administrator reports installing it? → A: Detect it by whether its code is actually loaded on the site rather than by whether it appears as a separately installed plugin — this recognises copies bundled inside another plugin. Once detected, the administrator may advance.
- Q: What happens when an embedded walkthrough cannot load (no external connectivity, privacy blocker, regional block)? → A: Keep the inline embed for visual parity, and always show an accompanying link to watch it externally so the walkthrough remains reachable when the embed is blocked.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Connect a transport so abilities become usable (Priority: P1)

A site administrator installs AcrossAI Abilities Manager and is taken straight into a guided
setup. They see how many abilities the plugin just gave their site, then reach a screen that
explains the plugin needs a transport to be useful and offers two options, with the recommended
one already selected. They choose the recommended option, click one button, and the transport
plugin is installed, activated, and they are handed directly into its own setup flow.

**Why this priority**: This is the whole reason the wizard exists. Abilities do nothing until a
transport connects them to an AI client. Today a new user lands on a table of abilities with no
indication that anything further is required, and silently gets no value from the plugin. Every
other screen is education layered on top of this one outcome.

**Independent Test**: Install and activate the plugin on a site with no transport present.
Verify the wizard opens on its own, the ability count is shown, the transport screen offers both
options with the recommended one pre-selected, and the single primary action installs the
transport and lands the user in the transport's own setup. Delivers a working end-to-end
connection without the user ever visiting the Plugins screen.

**Acceptance Scenarios**:

1. **Given** a site where no transport plugin is installed, **When** the administrator activates
   AcrossAI Abilities Manager, **Then** they are taken to the first wizard screen automatically.
2. **Given** the administrator is on the transport screen with the recommended option selected,
   **When** they trigger the primary action, **Then** the recommended transport is installed and
   activated without leaving the wizard, and they are handed off to that transport's own setup.
3. **Given** the recommended transport is already active on the site, **When** the administrator
   reaches the transport screen, **Then** it reports the site is already connected and offers to
   finish rather than offering to install anything.
4. **Given** the recommended transport is already active on the site, **When** the administrator
   activates AcrossAI Abilities Manager, **Then** no automatic redirect occurs and their work is
   not interrupted.
5. **Given** the install action fails (network failure, filesystem permissions, plugin directory
   unreachable), **When** the administrator triggers it, **Then** a plain-language error is shown
   with a way to install manually, and the wizard stays on the transport screen.

---

### User Story 2 - Learn how to manage abilities (Priority: P2)

Before being asked to connect anything, the administrator is shown what they now have and how to
work with it: a walkthrough of editing a single ability, and a walkthrough of acting on many
abilities at once.

**Why this priority**: Reduces the support burden and abandonment that comes from users not
knowing the plugin is configurable. Valuable, but the plugin still functions if these screens are
skipped, so it ranks below actually connecting a transport.

**Independent Test**: Walk the wizard from the first screen through the two instructional screens
without connecting anything. Verify each screen presents its walkthrough and that Back and
Continue move between them correctly.

**Acceptance Scenarios**:

1. **Given** the administrator is on the first screen, **When** they continue, **Then** they see
   a walkthrough covering how to edit an individual ability.
2. **Given** the administrator is on the editing screen, **When** they continue, **Then** they see
   a walkthrough covering how to apply bulk actions across abilities.
3. **Given** any instructional screen, **When** it loads, **Then** the walkthrough does not begin
   playing on its own.

---

### User Story 3 - See the breadth of what is available (Priority: P2)

The administrator is shown the catalogue of integration groups their site now has abilities for,
with a count per group, so they understand the scope of what was installed.

**Why this priority**: Communicates value and sets expectations before the transport ask. Purely
informational, so it can ship after the core connection flow.

**Independent Test**: Reach the integrations screen and confirm the groups and counts shown match
what the Integrations admin page reports for the same site.

**Acceptance Scenarios**:

1. **Given** a site with abilities registered across several groups, **When** the administrator
   reaches the integrations screen, **Then** each group is listed with its ability count.
2. **Given** a site where an optional third-party integration is inactive, **When** the
   administrator reaches the integrations screen, **Then** that integration's group is not listed.
3. **Given** the integrations screen, **When** the administrator interacts with it, **Then** no
   configuration is changed — the screen is informational only.

---

### User Story 4 - Use the alternative transport (Priority: P3)

An administrator who prefers the standalone WordPress MCP Adapter selects it instead, receives
instructions for obtaining and installing it, confirms once installed, and is then shown how to
enable abilities for it.

**Why this priority**: Serves a minority of users and cannot be automated (the adapter is not
distributable through the plugin directory), so it is the least-used path with the most manual
effort. The wizard is still complete and useful without it.

**Independent Test**: Choose the alternative transport on the transport screen and verify the
instruction screen and the follow-up walkthrough both appear, and that the wizard reaches its
completion summary.

**Acceptance Scenarios**:

1. **Given** the administrator selects the alternative transport, **When** they continue, **Then**
   they receive instructions for obtaining and installing it, including a link to its source.
2. **Given** the administrator has installed the alternative transport in another tab, **When**
   they return and confirm, **Then** the wizard recognises it is present and lets them proceed.
2a. **Given** the alternative transport is bundled inside another plugin rather than installed on
   its own, **When** the wizard checks for it, **Then** it is still recognised as present.
3. **Given** the administrator completes the alternative transport path, **When** they continue
   past the final instructional screen, **Then** they reach the completion summary.
4. **Given** the alternative transport is already active on the site, **When** the administrator
   selects it on the transport screen and continues, **Then** the installation instructions are
   skipped entirely and they go straight to the walkthrough for reaching abilities through its
   default server.
5. **Given** the alternative transport is already active, **When** the administrator reaches the
   transport screen, **Then** that option is marked as already active while the recommended
   transport remains marked and preselected.

---

### User Story 5 - Return to the wizard later (Priority: P3)

An administrator who exited the wizard, or who never saw it because a transport was already
present, can re-open it at any time.

**Why this priority**: A safety net. Without it, exiting once permanently loses the onboarding,
but the primary flows all work without it.

**Independent Test**: Exit the wizard, then re-open it from each documented entry point and
confirm it starts from the first screen.

**Acceptance Scenarios**:

1. **Given** the administrator previously exited the wizard, **When** they open it from any
   documented entry point, **Then** it starts again from the first screen.
2. **Given** the wizard is open, **When** the administrator chooses to exit, **Then** they are
   returned to the plugin's main screen with no changes saved.

---

### Edge Cases

- **Screens that no longer apply**: If an administrator arrives directly at a screen that does not
  apply to their chosen path (via a bookmark, a shared link, or the browser Back button), the
  wizard moves them forward to the next applicable screen rather than showing a dead end.
- **Browser navigation**: Using Back and Forward moves between wizard screens consistently with
  the wizard's own Back and Continue controls; the screen shown always matches the address bar.
- **Insufficient permissions**: An administrator without permission to install plugins can still
  read every screen but cannot trigger an install; the wizard tells them to ask a site
  administrator rather than presenting a control that will fail.
- **Multi-plugin activation**: Activating several plugins at once does not hijack the browser into
  this wizard.
- **Network-wide (multisite) activation**: Does not trigger the automatic redirect.
- **Activation without a following page view** (for example via command line): The pending
  redirect expires rather than surprising the operator on some unrelated page later.
- **Repeated or interrupted install attempts**: Triggering the install action twice, or navigating
  away mid-install, does not produce a duplicate install or a stuck screen.
- **Scripting unavailable**: A browser with scripting disabled sees an explanatory message rather
  than a blank page.
- **Zero abilities registered**: The first screen still renders coherently rather than showing an
  empty or broken statistic.
- **Walkthrough cannot be displayed**: When the inline player is blocked, the screen still explains
  its topic and offers a working link to view the walkthrough externally, rather than leaving an
  empty area with no explanation.
- **Alternative transport bundled rather than installed separately**: It is still recognised as
  present, so the administrator is not asked to install something they already have.

## Requirements *(mandatory)*

### Functional Requirements

**Entry and gating**

- **FR-001**: The system MUST open the wizard automatically the first time an administrator views
  an admin page after activating the plugin.
- **FR-002**: The system MUST suppress that automatic opening when the **recommended** transport is
  already installed and active on the site.
- **FR-002a**: The system MUST still open the wizard automatically when only the **alternative**
  transport is present, so those administrators are introduced to the recommended one. Presence of
  the alternative transport is not a reason to skip onboarding.
- **FR-003**: The system MUST suppress that automatic opening for bulk plugin activations,
  network-wide activation, and users lacking site-management permission.
- **FR-004**: The system MUST discard a pending automatic opening if no admin page is viewed
  shortly after activation.
- **FR-005**: The system MUST open the wizard at most once per activation, even if the first
  attempt does not complete.
- **FR-006**: Users MUST be able to open the wizard on demand from at least three places: the
  plugin's admin menu, the site's admin toolbar, and the plugin's entry on the Plugins screen.
- **FR-007**: The system MUST restrict the wizard and all of its data to users with
  site-management permission.

**Screen flow**

- **FR-008**: The system MUST present the wizard as an ordered sequence of screens with Back and
  Continue controls and a visible progress indicator.
- **FR-009**: The progress indicator MUST reflect only the screens that apply to the
  administrator's current path, so the reported total never counts screens they will not see.
- **FR-010**: The system MUST show, in order: an ability overview, an ability-editing walkthrough,
  a bulk-actions walkthrough, an integrations overview, and a transport selection screen.
- **FR-011**: The system MUST show an enablement walkthrough — how to reach abilities through the
  alternative transport's default server — whenever the administrator selects the alternative
  transport.
- **FR-011a**: The system MUST show the alternative transport's installation instructions only when
  it is not already present on the site. When it is already present, the administrator goes
  straight from the transport screen to the enablement walkthrough.
- **FR-012**: The system MUST end with a completion summary offering onward destinations.
- **FR-013**: The system MUST forward the administrator past any screen that does not apply to
  their path when they arrive at it directly.
- **FR-014**: The system MUST keep the address bar and the displayed screen in agreement so any
  screen can be linked to or reached with browser navigation.
- **FR-015**: The system MUST NOT store wizard progress on the server; leaving and returning starts
  the wizard fresh.

**Screen content**

- **FR-016**: The ability overview MUST state how many abilities are available on this site,
  matching the count reported by the plugin's own abilities screen.
- **FR-017**: The integrations overview MUST list each integration group present on the site with
  its ability count, determined at view time so groups from inactive optional integrations are
  absent.
- **FR-018**: The integrations overview MUST be read-only and change no configuration.
- **FR-019**: Instructional screens MUST present their walkthrough without beginning playback
  automatically.
- **FR-019a**: Every instructional screen MUST also offer a persistent link to view its walkthrough
  externally, so the content stays reachable on sites where the inline player is blocked by lack of
  external connectivity, a privacy tool, or regional restriction.
- **FR-020**: The transport screen MUST present exactly two options, marking the recommended one
  and selecting it by default — including for administrators who already have the alternative
  transport active.
- **FR-020a**: The transport screen MUST indicate, on each option, whether that transport is
  already active on the site, so the wizard never appears unaware of the administrator's setup.
- **FR-020b**: Administrators MUST be able to choose the alternative transport and continue, even
  when the recommended one is preselected.
- **FR-021**: The transport screen MUST state that the plugin works with both supported transports.
- **FR-022**: The system MUST NOT present the transport dependency message before the transport
  screen.

**Connecting a transport**

- **FR-023**: The system MUST install and activate the recommended transport in place, without the
  administrator leaving the wizard or visiting the Plugins screen.
- **FR-024**: The system MUST hand the administrator directly into the recommended transport's own
  setup flow once it is active.
- **FR-025**: The system MUST report an "already connected" state, with a path to finish, when the
  recommended transport is already active.
- **FR-026**: The system MUST provide obtaining-and-installing instructions for the alternative
  transport, including a link to its source, because it cannot be installed automatically.
- **FR-027**: The system MUST let the administrator confirm after installing the alternative
  transport manually, and MUST determine its presence by whether its code is actually loaded on the
  site — not by whether it appears as a separately installed plugin — so a copy bundled inside
  another plugin is correctly recognised.
- **FR-027a**: The system MUST allow the administrator to advance to the enablement walkthrough as
  soon as the alternative transport is detected as present. Until it is detected, the system MUST
  keep them on the instructions screen with a clear "not detected yet" message and a way to
  re-check.
- **FR-028**: The system MUST restrict in-place installation to the single recommended transport
  and reject any other install request.
- **FR-029**: The system MUST require plugin-installation permission before performing an install.
- **FR-030**: The system MUST show plain-language failure messages that never expose server paths
  or internal diagnostics, and MUST record technical detail to the server log instead.
- **FR-031**: The system MUST prevent a second install from starting while one is in progress.

**Presentation**

- **FR-032**: The wizard MUST be visually indistinguishable from the AcrossAI MCP Manager wizard —
  identical brand mark, loading animation, colour palette, typography, spacing, progress
  indicator, and control styling — so both read as one product.
- **FR-033**: The wizard MUST reuse the existing AcrossAI brand assets unmodified.
- **FR-034**: The wizard MUST display the brand icon with the same pulsing animation as the
  sibling wizard while it is loading and while an action is in progress.
- **FR-035**: The wizard MUST show a single progress indication covering the whole screen while an
  action is in progress, and MUST disable its controls for the duration.
- **FR-036**: The wizard MUST occupy the full screen, hiding surrounding admin navigation and
  suppressing unrelated admin notices for the duration.
- **FR-037**: The wizard MUST remain usable on small screens.
- **FR-038**: The wizard MUST announce screen changes to assistive technology and move keyboard
  focus into each new screen.
- **FR-039**: The wizard MUST explain itself when browser scripting is unavailable.

**Scope boundaries**

- **FR-040**: The wizard MUST NOT alter which abilities are enabled or change any existing setting.
- **FR-041**: The wizard MUST NOT introduce a new top-level admin destination.
- **FR-042**: The wizard's assets MUST NOT load on any other admin screen.

### Key Entities

- **Ability catalogue summary**: The count of abilities available on the site and their grouping
  into integration groups with per-group counts. Derived at view time; never stored.
- **Transport option**: One of the two supported connection methods, each with a display name,
  description, whether it is recommended, whether it can be installed automatically, and its
  current presence on the site (absent / present but inactive / active).
- **Wizard position**: The administrator's current screen and their selected transport option.
  Held in the address bar for the duration of the visit; never persisted server-side.
- **Pending-opening marker**: A short-lived, single-use signal created at activation indicating the
  wizard should open on the next admin page view. Expires on its own.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A new administrator can go from activating the plugin to a fully connected transport
  in under two minutes, without visiting the Plugins screen or reading external documentation.
- **SC-002**: Connecting the recommended transport takes exactly one deliberate action from the
  transport screen.
- **SC-003**: 100% of administrators who activate the plugin without the recommended transport
  present are shown the wizard automatically — including those already running the alternative
  transport; 0% of administrators who already have the recommended transport are interrupted.
- **SC-004**: The ability count and integration group counts shown in the wizard match the plugin's
  own abilities and integrations screens exactly, on every site tested.
- **SC-005**: Reviewers comparing the wizard side by side with the AcrossAI MCP Manager wizard
  cannot identify styling differences other than screen content, at both desktop and small-screen
  widths.
- **SC-006**: Every wizard screen is reachable by direct link and by browser Back/Forward without
  producing a blank screen, a dead end, or a mismatch between the address bar and what is shown.
- **SC-007**: An administrator can complete the entire wizard using only the keyboard, with each
  screen change announced by a screen reader.
- **SC-008**: Failed installations always produce an actionable message and a manual alternative;
  no failure leaves the wizard unresponsive or shows internal technical detail.
- **SC-009**: Users without installation permission are never shown a control that would fail for
  them.
- **SC-010**: Opening any other plugin admin screen loads none of the wizard's assets.
- **SC-011**: On a site with no external connectivity, every instructional screen still explains its
  topic and offers a working route to its walkthrough — no screen presents an unexplained empty
  area.
- **SC-012**: An administrator whose alternative transport is bundled inside another plugin is never
  asked to install it again.

## Assumptions

- **Recommended transport is distributable**: AcrossAI MCP Manager is available from the WordPress
  plugin directory, so it can be installed in place. The alternative transport (the WordPress MCP
  Adapter) is distributed only from its source repository and therefore cannot be, which is why
  those two paths differ.
- **Placeholder walkthrough content**: All three instructional screens currently point at the same
  walkthrough recording. Purpose-specific recordings are expected to replace it later; the wizard
  treats the recording reference as content, not structure, so swapping it requires no redesign.
- **Visual parity is defined by the sibling wizard as it exists today**: The AcrossAI MCP Manager
  wizard is the reference. If it changes later, matching it again is a separate piece of work.
- **Brand assets are already present**: The required brand icon and logo already exist in this
  plugin, byte-identical to the sibling's, and need only be relocated and committed — not
  redesigned or re-exported.
- **Single site**: Network-wide (multisite) activation is explicitly out of scope for automatic
  opening; the wizard remains manually reachable per site.
- **No new stored settings**: The wizard reads existing state and installs a plugin. It introduces
  no lasting configuration, so uninstalling the plugin needs no additional cleanup.
- **One-time onboarding, repeatable on demand**: There is no "already completed" flag. The wizard
  can be re-run any number of times and always starts fresh, which also keeps it usable as a
  reference.
- **Reading the wizard is safe for any administrator**: Every screen is informational except the
  install action, so permission differences affect only that one control.
