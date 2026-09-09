# Specification Quality Checklist: Quick Connect Onboarding Wizard

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-09-07
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic (no implementation details)
- [x] All acceptance scenarios are defined
- [x] Edge cases are identified
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification

## Notes

### Validation history

**Iteration 1** — 4 items failed, all in Content Quality / technology-agnosticism. The source
feature description was written as an implementation brief (class names, hook names, REST routes,
SCSS tokens, CSS keyframes, file paths, priority numbers), and the first draft inherited that
vocabulary.

Issues found and corrected:

1. *No implementation details* — FRs named specific classes, transients, hooks, query parameters,
   and REST routes. Rewritten as capability statements: "opens automatically the first time an
   administrator views an admin page after activating" rather than naming the transient and the
   `admin_init` priority that implements it.
2. *Success criteria technology-agnostic* — draft criteria referenced bundle filenames and route
   status codes. Replaced with user-observable outcomes (SC-001 time-to-connected, SC-005
   side-by-side indistinguishability, SC-010 assets absent elsewhere).
3. *Written for non-technical stakeholders* — the visual-parity requirement was a table of SCSS
   variables and keyframe values. Restated as FR-032/FR-034 in terms of what a reviewer would
   observe. The exact token values remain in `docs/planning/099-quick-connect-onboarding-wizard.md`
   for the implementation phase, which is where they belong.
4. *Requirements testable and unambiguous* — several draft FRs bundled multiple behaviours into one
   statement. Split into single-behaviour requirements (for example, entry/gating separated into
   FR-001 through FR-007).

**Iteration 2** — all items pass.

### Deliberate decisions

- **Zero [NEEDS CLARIFICATION] markers.** The feature description was unusually complete, and the
  three points that could have been questions had defensible defaults, recorded in Assumptions
  instead: placeholder walkthrough content, single-site scope, and no completion flag.
- **Two transports behave differently, and that asymmetry is specified rather than smoothed over.**
  The recommended transport installs in place; the alternative can only be described, because it is
  not distributed through the plugin directory. FR-026 states the constraint so it is not mistaken
  for an implementation gap during planning.
- **Visual parity is expressed as an observable outcome** (SC-005: reviewers cannot identify
  differences) rather than as a list of style values, so it is verifiable without prescribing how.

### Clarification session 2026-09-07

Four questions asked and answered; all integrated into the spec. Two of the four changed behaviour
the spec had previously stated, and the contradicting text was replaced rather than duplicated:

1. **Auto-open gating** — only the recommended transport suppresses the automatic opening; sites
   running the alternative transport still get onboarded. Invalidated the original SC-003 wording
   ("0% of administrators who already have one are interrupted"), which was rewritten.
2. **Transport screen for existing alternative-transport users** — recommendation still leads and
   stays preselected, each option is labelled with its current state, and installation instructions
   are skipped when the alternative is already present. Split FR-011 into FR-011/FR-011a.
3. **Detection method** — presence is determined by whether the transport's code is loaded, not by
   whether it appears as a separately installed plugin, so bundled copies are recognised. This
   removed the false-negative dead-end risk that motivated the question.
4. **Blocked walkthrough player** — inline embed retained for visual parity, with a persistent
   external link so the content stays reachable when the embed is blocked.

Spec grew from 42 to 48 functional requirements and from 10 to 12 success criteria.

### Carried into planning

These are constraints the spec deliberately does not encode, and the plan must not lose:

- The exact palette, spacing, animation timings, and class vocabulary to port — see
  `docs/planning/099-quick-connect-onboarding-wizard.md`.
- The specific brand asset files and the requirement that they remain byte-identical.
- The reference implementation to port from, in the sibling AcrossAI MCP Manager plugin, including
  the navigation patterns whose comments document bugs already fixed there.
