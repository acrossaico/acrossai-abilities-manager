# Specification Quality Checklist: Toolset Abilities

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-09-10
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

## Validation Notes

**Iteration 2 — all items pass.** Re-validated after the grouping key changed from ability *category*
to *group* (2026-09-10), following Feature 101.

Automated scans against `spec.md` return zero code identifiers (`wp_*()` calls, filter/action names,
file extensions, class references, `::`), zero `[NEEDS CLARIFICATION]` markers, and zero third-party
product names. 47 functional requirements, 12 success criteria, 5 user stories, 11 edge cases.

**Terms retained deliberately.** *Ability*, *group*, *card*, *sub-group*, *input schema*, *output
schema* and *annotations* are domain vocabulary of the product's existing public contract, not
implementation detail. Removing them would make the requirements untestable.

**What the regrouping changed, and why it is a simplification rather than a rename.** The first
iteration needed four separate requirements to keep a Toolset out of the operator settings that
applied to its own category — because it shared a category with the abilities it dispatched to. A
group Toolset declares its own category (FR-008) and resolves members from what is registered
(FR-034), so every enable, disable and specific-selection is inherited rather than re-implemented.
FR-041 replaces all four. A reviewer comparing revisions should check that claim first: if it does not
hold, the four requirements need to come back.

**Three claims a reviewer should test rather than accept.**

1. **The default exposure posture** (Assumptions, and SC-006/SC-007) is broader than the entry points
   it supplements. It is stated with its safeguards because it is the central claim any security
   review must test.
2. **FR-041's "without consulting them directly"** is the requirement most likely to be satisfied by
   accident and then broken by a later optimisation. If a Toolset ever reads the settings option
   itself, it has stopped inheriting and started re-implementing, and the inverted default for
   untouched integration cards is what will break first.
3. **SC-010 pins the tool count to a threshold the team does not control.** Thirteen is inside the
   published degradation range; a fourteenth group is a decision recorded in
   `DEC-ABILITY-GROUP-TAXONOMY`, not a free addition.

**One dependency is a blocker, not a note.** The Dependencies section records a defect in the connected
transport that refuses every curated tool which is not one of three hardcoded generic ones. Toolsets
are curated tools. Scheduling this feature without scheduling that fix produces a feature that ships
and then does not work — which is why it appears in the specification rather than only in a planning
document.
