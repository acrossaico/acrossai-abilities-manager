---
document_type: security-review
review_type: tasks
assessment_date: 2026-09-12
codebase_analyzed: acrossai-abilities-manager (specs/102-abilities-toolset-tabs)
total_files_analyzed: 12
total_findings: 5
overall_risk: HIGH
critical_count: 0
high_count: 2
medium_count: 2
low_count: 1
informational_count: 0
owasp_categories: [A01, A04, A05]
cwe_ids: [CWE-672, CWE-841, CWE-862, CWE-362]
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

# Security Review — Feature 102 Task List

## Executive Summary

Every finding from the two plan-stage reviews is represented by a concrete task, and the sequencing rule
that matters most — migration before gate removal — is stated as a hard rule rather than buried in a
dependency graph. The security *coverage* of the task list is good.

The problem is in the **deletion phase**, and it is not a coverage gap — it is a task that cannot be
executed as written. **T073 deletes `AcrossAI_Ability_Library_Config`, but eight call sites across three
classes the plan explicitly retains still depend on it.** Following the task list in order produces a fatal
error on every request. The same class also holds the option key the lazily-triggered multisite migration
reads, so a network site that has not yet migrated by the time Phase 8 lands is left with no route to ever
migrate.

Both are HIGH rather than Critical because the consequence is availability, not confidentiality — but on a
WordPress site a fatal on every request is total, and the multisite case silently leaves previously blocked
abilities reachable forever, which is exactly what User Story 2 exists to prevent.

Two Medium findings concern missing negative tests and an unenforced sequencing gate. Nothing here blocks
starting Phases 1-7.

## Tasks Reviewed

`tasks.md` (85 tasks, 8 phases) against `plan.md` · `spec.md` · `research.md` · `data-model.md` ·
`contracts/removed-surface.md` · `contracts/abilities-list.md` · `quickstart.md` · `memory-synthesis.md` ·
`security-constraints.md` · `docs/memory/INDEX.md` · `docs/memory/security-constraints.md` ·
`.specify/memory/CONSTITUTION.md`

## Findings

### SEC-010 — T073 deletes a class that three retained classes still call

- **Severity**: HIGH · **CVSS**: 7.5 (AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:N/A:H) · **OWASP**: A05:2025-Security Misconfiguration · **CWE**: CWE-672: Operation on a Resource after Expiration or Release
- **Location**: `tasks.md` T073; `contracts/removed-surface.md` "PHP symbols"
- **Spec-Kit task**: TASK-SEC-010

`AcrossAI_Ability_Library_Config` is scheduled for deletion, but it owns two members that outlive it:
`sanitize_key_field()` and `OPTION_KEY`. Verified call sites in classes the plan **retains**:

| Retained file | Calls | Resolved by an existing task? |
|---|---|---|
| `Modules/Library/AcrossAI_Ability_Library_Registry.php` | `sanitize_key_field()` ×5 — lines 417, 418, 462, 474, 498 | **No** |
| `Modules/Library/Integrations/AcrossAI_Integration_Ability_Base.php` | `sanitize_key_field()` line 320 | **No** (T059 repoints line 249 only) |
| `Modules/Library/AcrossAI_Category_Slug_Migration.php` | `OPTION_KEY` lines 132, 155 | **No** |

Two further call sites *are* resolved incidentally: `Ability_Definition.php:225,247` sit inside the helpers
T043 deletes, and `AcrossAI_Ability_Library_Processor.php:70` loads the config for the `is_permitted()`
call T042 removes.

**Recommendation**: add a relocation task **before** T073 —

- Move `sanitize_key_field()` into `includes/Utilities/` per Constitution §VI ("All common logic MUST be
  extracted to shared utilities before it is used in a second location" — it already has six consumers).
- Give the retained migrations their own option-key constant, or inline the literal `acrossai_library_config`.
- Repoint all six `sanitize_key_field()` call sites and both `OPTION_KEY` call sites, then delete the class.

This is precisely the failure `BUG-INVENTORY-GREP-MISS` warns about, and T071 would likely have caught it —
but T071 is scoped to "symbols listed in `removed-surface.md`", and `removed-surface.md` lists the class
without listing what depends on it. Widen T071 to grep for *members* of each removed class, not only the
class name.

### SEC-011 — Lazily-migrating multisite sites lose their migration path in the same release

- **Severity**: HIGH · **CVSS**: 7.1 · **OWASP**: A01:2025-Broken Access Control · **CWE**: CWE-672
- **Location**: `tasks.md` T024/T029 vs T073; `research.md` R1
- **Spec-Kit task**: TASK-SEC-011

R1 establishes that on multisite each site migrates on its **own first request**, which may be days or
weeks after the upgrade, and that `acrossai_library_config` is deliberately retained there. T073 deletes the
class that reads it in the same release. Any site that has not yet had a first request when Phase 8 ships
will, on its next request, attempt a migration whose source accessor no longer exists.

The failure is silent in the way that matters: with `is_permitted()` already gone (T042), that site serves
every previously blocked ability — the exact outcome FR-010 and User Story 2 forbid — and spec
Clarifications Q1 and Q2 guarantee nothing reports or retries it.

**Recommendation**: `AcrossAI_Library_Gate_Migration` must depend on **no** class scheduled for deletion.
Give it its own `SOURCE_OPTION` constant and read `get_site_option()` directly. Add an explicit task
asserting the migration class has zero references to `AcrossAI_Ability_Library_Config`.

### SEC-012 — No negative test enforces "the migration reads no request input"

- **Severity**: MEDIUM · **CVSS**: 5.3 · **OWASP**: A01:2025-Broken Access Control · **CWE**: CWE-862: Missing Authorization
- **Location**: `tasks.md` T030
- **Spec-Kit task**: TASK-SEC-012

T030 states the constraint that carries SEC-009 — the trigger is reachable unauthenticated, so the
translation must read only stored state. No task verifies it. A later "add a `?force-remigrate=1` escape
hatch for support" is exactly the plausible change this constraint exists to prevent, and nothing would
fail.

**Recommendation**: add a test asserting `AcrossAI_Library_Gate_Migration` contains no superglobal access
(`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`) and calls no request accessor. Keep the assertion a simple
token scan — `BUG-SOURCE-INSPECTION-ADJACENCY-BRITTLE` warns that adjacency-based source regexes rot.

### SEC-013 — The Phase 3 → Phase 4 gate is prose, not an executable dependency

- **Severity**: MEDIUM · **CVSS**: 4.3 · **OWASP**: A04:2025-Insecure Design · **CWE**: CWE-841: Improper Enforcement of Behavioral Workflow
- **Location**: `tasks.md` ordering rule 1, Phase 4 header, T042
- **Spec-Kit task**: TASK-SEC-013

The single most important constraint in the feature — do not delete `is_permitted()` until the migration is
verified — appears twice in prose and once in an ASCII dependency diagram, but no checkbox enforces it.
T042 is tickable the moment someone reaches it. Prose does not survive a task list being worked from the
bottom up or split across people.

**Recommendation**: insert an explicit gate task immediately before T042 whose description is "confirm
T033–T041 are all ticked and green", so skipping it is visible as an unticked box rather than an
unremembered paragraph.

### SEC-014 — T040 tests sequential re-entry, not concurrency

- **Severity**: LOW · **CVSS**: 3.1 · **OWASP**: A04:2025-Insecure Design · **CWE**: CWE-362: Race Condition
- **Location**: `tasks.md` T040

T040 asserts that a second call performs no work while the flag exists — which is re-entrancy, not
atomicity. The property SEC-008 actually needs is that two requests racing an *unset* flag produce exactly
one winner, and that comes from `add_option()`'s single INSERT rather than from anything a unit test can
observe. The only genuine check is the manual one in `quickstart.md` §2b.

**Recommendation**: no new test — unit-testing MySQL concurrency is not worth the harness. Instead note the
limitation in T040's description so the manual check is not treated as redundant and skipped.

## Confirmed Secure Patterns

| Plan finding | Task coverage |
|---|---|
| SEC-001 all-paths trigger | T031 (`define_public_hooks`), T041 (multisite rehearsal including a site never opened in wp-admin) |
| SEC-002 counts from PATH A | T009 (sibling route, literal-before-wildcard), T057 (counts match rows on a site with blocked abilities) |
| SEC-003 capability raise-only | T061 (single filtered check behind a `manage_options` page), T064 (a filter returning `read` does not let a subscriber toggle) |
| SEC-005 permanent redirect | T067 implements 301 per FR-024; target from `admin_url()`, `tab` via `sanitize_key()` |
| SEC-006 `sub_keys` fails toward blocking | T025 (membership from the registry), T034 (unticked → blocked), T038 (all 87 of a >50 category) |
| SEC-008 atomic claim | T023 (`add_option` before the work) |
| FR-008 never overwrite an operator's override | T027 implementation, T035 test |
| FR-022 opt-in survives | T028 (OR-monotonic copy), T039 test |
| Route-order hazard | T009 plus T012 pinning the order |
| Deletion discipline | T071 gates all of Phase 8 — though it needs widening, see SEC-010 |

Test-first sequencing is respected throughout: T033–T041 precede the gate removal they protect, and
Phase 2's PHPUnit tasks accompany rather than trail their subjects.

## Action Plan & Next Steps

1. **SEC-010 and SEC-011 must be fixed in `tasks.md` before Phase 8 is reached** — but they do not block
   Phases 1-7. Both are resolved by one relocation task plus a constant, added ahead of T073.
2. SEC-012 and SEC-013 are one new task each.
3. SEC-014 is a wording change to T040.
4. Recommend `/speckit-security-review-followup` to raise TASK-SEC-010 through TASK-SEC-013.
5. `/speckit-security-review-init` remains outstanding — this project still has no `security_constitution.md`.
