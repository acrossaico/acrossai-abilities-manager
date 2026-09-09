---
document_type: security-review
review_type: tasks
assessment_date: 2026-09-08
codebase_analyzed: acrossai-abilities-manager / specs/099-quick-connect-wizard
total_files_analyzed: 10
total_findings: 6
overall_risk: MODERATE
critical_count: 0
high_count: 0
medium_count: 2
low_count: 3
informational_count: 1
owasp_categories: [A01, A05, A08]
cwe_ids: [CWE-862, CWE-494, CWE-16]
field_summaries:
  document_type: "Always 'security-review'. Allows indexers to skip non-review documents."
  review_type: "Which command generated this document: audit, branch, staged, plan, tasks, or followup."
  assessment_date: "ISO 8601 date the review was performed (YYYY-MM-DD)."
  overall_risk: "Highest severity tier with active findings (CRITICAL, HIGH, MODERATE, LOW, INFORMATIONAL)."
  critical_count: "Number of Critical findings (CVSS 9.0-10.0)."
  high_count: "Number of High findings (CVSS 7.0-8.9)."
  medium_count: "Number of Medium findings (CVSS 4.0-6.9)."
  low_count: "Number of Low findings (CVSS 0.1-3.9)."
  informational_count: "Number of Informational findings."
  owasp_categories: "OWASP Top 10 2025 categories (A01-A10) that have at least one finding."
  cwe_ids: "CWE identifiers referenced in this document."
  finding_id: "Unique finding identifier (SEC-NNN) for cross-referencing and task linkage."
  location: "File path and line number of the vulnerable code (path/to/file.ext:line)."
  owasp_category: "OWASP Top 10 2025 category for this finding (AXX:2025-Name)."
  cwe: "Common Weakness Enumeration identifier with short name (CWE-NNN: Name)."
  cvss_score: "CVSS v3.1 base score (0.0-10.0). 9.0+=Critical, 7.0-8.9=High, 4.0-6.9=Medium, 0.1-3.9=Low."
  spec_kit_task: "Spec-Kit task ID for backlog tracking and remediation follow-up (TASK-SEC-NNN)."
---

# Security Review — Feature 099 Quick Connect Wizard (Tasks)

## Executive Summary

**Overall risk: MODERATE.** Coverage is strong — **all eight findings from the plan review map to
concrete, correctly-placed tasks**, and the hardening work sits inside the phase that writes the
code rather than being deferred to a cleanup pass. That is the single most important property of
this task list and it holds.

Two Medium findings remain. First, the feature's highest-risk endpoint — remote plugin installation
— is **implemented before its negative tests** (T040 before T046, with T046 only `[P]`-marked, so
nothing enforces the order). Second, spec requirement **SC-009 has no task at all**: nothing makes
the install control conditional on the user actually holding `install_plugins`, so a capability-less
administrator would be shown a button guaranteed to 403.

Three Low findings concern verification timing rather than missing controls. Nothing here is a
design defect; all six are addressable by adding two tasks and moving one.

## Tasks Reviewed

| Artifact | Purpose |
|---|---|
| `specs/099-quick-connect-wizard/tasks.md` | 72 tasks, 8 phases |
| `specs/099-quick-connect-wizard/plan.md` | Constitution Check, structure |
| `specs/099-quick-connect-wizard/spec.md` | 48 FRs / 12 SCs |
| `specs/099-quick-connect-wizard/research.md` | R1–R10 |
| `specs/099-quick-connect-wizard/data-model.md` | Guard order, install contract |
| `specs/099-quick-connect-wizard/contracts/*.md` | REST + router contracts |
| `specs/099-quick-connect-wizard/security-review-plan.md` | SEC-001…SEC-008 (rev 2) |
| `specs/099-quick-connect-wizard/memory-synthesis.md` | Refreshed conflict status |
| `.specify/memory/CONSTITUTION.md` | v1.4.8 §IV |
| `docs/memory/security-constraints.md` | SEC-04 |

### Plan-finding → task traceability

| Plan finding | Task | Placement verdict |
|---|---|---|
| SEC-001 third-party embed | T048, T049, T052 | ✅ in the phase that builds the embed |
| SEC-002 post-activation basename assertion | T041 | ✅ immediately after install |
| SEC-003 hand-off origin validation | T044 | ✅ with the hand-off |
| SEC-004 enum validation | T026 | ✅ Foundational, before any screen reads params |
| SEC-005 render capability check | T013 | ✅ first task that creates `render()` |
| SEC-006 in-flight guard | T066 | ✅ correctly optional/polish |
| SEC-007 scoped notice suppression | T017 | ✅ gated by T014 |
| SEC-008 log gating | T042 | ✅ with the install error paths |

**8 of 8 mapped.** No plan finding was lost in translation to tasks.

## Vulnerability Findings

### SEC-T01 — Highest-risk endpoint is implemented before its negative tests

- **Severity**: MEDIUM · **CVSS**: 4.0
- **Location**: `tasks.md` T040 (implement install route) vs T046 (contract tests)
- **OWASP**: A08:2025-Software and Data Integrity Failures · **CWE**: CWE-494
- **Spec Kit task**: TASK-SEC-T01

T040 implements `POST /install-plugin` — the one endpoint that downloads and activates code. Its
negative tests (allowlist → 400, missing `install_plugins` → 403, missing `activate_plugins` → 403,
no filesystem path in failure bodies) are T046, six tasks later and marked `[P]`, so nothing
enforces the ordering. Between T040 and T046 the endpoint is live and unverified, and T041/T042 add
behaviour on top of it in that window.

**Recommendation**: move the negative half of T046 to run **before** T040. The allowlist and
capability tests are pure contract assertions and can be written against the route signature, so
this costs no rework. Leave the success-path assertions where they are.

### SEC-T02 — SC-009 has no task: install control is not capability-conditional

- **Severity**: MEDIUM · **CVSS**: 3.7
- **Location**: `tasks.md` T038, T039, T043 (transport screen) — requirement `spec.md` SC-009, FR-029
- **OWASP**: A01:2025-Broken Access Control · **CWE**: CWE-862
- **Spec Kit task**: TASK-SEC-T02

SC-009 states: "Users without installation permission are never shown a control that would fail for
them." FR-029 requires the capability server-side, and T040/T046 enforce and test that. But **no
task makes the UI reflect it.** As written, an administrator with `manage_options` but without
`install_plugins` reaches the transport screen, sees "Continue - Install the plugin", clicks it, and
receives a 403.

The server is safe; the requirement is a UX-of-authorization one, and it is currently unimplemented
and untested.

**Recommendation**: add `canInstall` to the `GET /state` response (derived from the same dual
capability check), and have `Step5_ConnectTransport.jsx` render an "Ask a site administrator"
message in place of the install action when it is false. Mirrors the pattern acrossai-pro uses on
its gate card.

### SEC-T03 — `GET /state` ships concurrently with, not before, its authorization tests

- **Severity**: LOW · **CVSS**: 2.6
- **Location**: `tasks.md` T021 (implement) vs T023 (`[P]` contract test)
- **OWASP**: A01:2025-Broken Access Control · **CWE**: CWE-862
- **Spec Kit task**: TASK-SEC-T03

T023 verifies Editor → 403, missing nonce → 403, and that `permission_callback` returns only
`true|false|WP_Error` — the constitution's named critical defect. Being `[P]` in the same phase, it
may land after T021. Lower risk than SEC-T01 because the endpoint is read-only, but the
`permission_callback` return-type assertion in particular is worth having green before the route is
delegated in T022.

**Recommendation**: drop the `[P]` marker on T023 and sequence it immediately after T021.

### SEC-T04 — Asset-gating boundary is verified only manually, in the final phase

- **Severity**: LOW · **CVSS**: 2.4
- **Location**: `tasks.md` T069 (manual check, Phase 8) — requirement FR-042, SC-010
- **OWASP**: A05:2025-Security Misconfiguration · **CWE**: CWE-16
- **Spec Kit task**: TASK-SEC-T04

FR-042 requires the wizard's assets to load on no other admin screen. The wizard bundle carries REST
calls and a nonce; loading it on unrelated screens would widen the surface unnecessarily. T069
verifies this by hand in the last phase, so a regression introduced in Phase 2 stays undetected
through five phases.

**Recommendation**: add a PHPUnit assertion alongside T015 that `enqueue_assets()` registers nothing
when the request flag is absent. Cheap, and it makes the boundary a gate rather than an observation.

### SEC-T05 — Design artifacts are corrected mid-implementation, not before it

- **Severity**: LOW · **CVSS**: 2.0
- **Location**: `tasks.md` T052 (Phase 4)
- **OWASP**: A05:2025-Security Misconfiguration · **CWE**: CWE-16
- **Spec Kit task**: TASK-SEC-T05

T052 amends the embed URL recorded in `docs/planning/099-quick-connect-onboarding-wizard.md` and the
spec clarification to the privacy host required by `DEC-ADMIN-THIRD-PARTY-EMBED`. It sits in Phase 4,
so from Phase 1 through Phase 3 the reference documents still instruct an implementer to use the
non-privacy host.

**Recommendation**: move T052 into Phase 1. It is a documentation edit with no dependencies.

### SEC-T06 — No uninstall task, and that is correct

- **Severity**: INFORMATIONAL
- **Location**: `tasks.md` (absence of an `uninstall.php` task)
- **OWASP**: n/a · **CWE**: n/a
- **Spec Kit task**: TASK-SEC-T06

The feature adds no options and no schema; the only persisted artifact is a 30-second transient that
expires on its own (`data-model.md` §4). So `uninstall.php` genuinely needs no changes.

Recorded explicitly so a future reviewer does not "fix" the perceived omission by adding cleanup for
state that does not exist.

## Confirmed Secure Patterns

- **Security work is phase-local, not deferred.** SEC-001…SEC-005 and SEC-008 are embedded in the
  tasks that write the corresponding code (T013, T014, T026, T041, T042, T044, T048). This is the
  correct shape and the most common thing task lists get wrong.
- **Foundational-phase ordering is sound**: the capability check (T013) and the request gate (T014)
  precede the enqueue (T015), the localize (T016), the notice suppression (T017), and the Menu.php
  branch (T018) that first makes `render()` reachable.
- **Enum validation (T026) lands in Foundational**, before any screen consumes `step`/`method`.
- **Notice suppression is gated** by T014 rather than global — bounded to the wizard request.
- **The activation-redirect guard matrix (T036) is an explicit test task**, including the
  non-obvious FR-002a case where an active alternative transport must *not* suppress.
- **Parallel markers do not bypass security prerequisites.** Reviewed every `[P]` task: none of the
  27 removes a control its siblings depend on. Phases 4, 5, and 7 can run concurrently without
  touching the install path.
- **Dual-capability enforcement and the strict `in_array(..., true)` allowlist** (SEC-04) both carry
  explicit tasks and explicit tests.

## Action Plan & Next Steps

1. **Add two tasks and move two** — T073 (capability-conditional install control, SEC-T02), T074
   (enqueue-gating assertion, SEC-T04); move T052 to Phase 1 and de-parallelize T023.
2. **Reorder T046's negative assertions before T040** (SEC-T01).
3. **No `/speckit-security-review-followup` run required** — zero Critical, zero High.
4. **Durable memory**: one reusable lesson identified — see the capture proposal in the orchestrator
   summary (server-side authorization needs a matching client-side affordance, or the UI promises
   what the API refuses).

## Memory Hub INDEX.md Row

```text
| specs/099-quick-connect-wizard/security-review-tasks.md | tasks | 2026-09-08 | MODERATE | C:0 H:0 M:2 L:3 I:1 | A01,A05,A08 |
```
