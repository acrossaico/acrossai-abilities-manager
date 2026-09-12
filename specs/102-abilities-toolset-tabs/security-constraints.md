---
document_type: security-review
review_type: plan
assessment_date: 2026-09-12
codebase_analyzed: acrossai-abilities-manager (specs/102-abilities-toolset-tabs)
total_files_analyzed: 15
total_findings: 7
overall_risk: MODERATE
critical_count: 0
high_count: 0
medium_count: 2
low_count: 2
informational_count: 3
owasp_categories: [A01, A04, A05]
cwe_ids: [CWE-362, CWE-280, CWE-665, CWE-1188, CWE-862]
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

# Security Review — Feature 102 Plan (second pass)

> **This supersedes the first pass of 2026-09-12.** The plan changed between reviews: SEC-001 and SEC-002
> were folded into `plan.md`, `research.md`, `contracts/` and `quickstart.md` before architecture
> validation. This document re-assesses the corrected plan and records what the correction itself
> introduced.

## Executive Summary

Overall risk drops **HIGH → MODERATE**. The two findings that changed the design are resolved, and the
end-state security model is sound: `site_allowed = false` causes `wp_unregister_ability()` at
`wp_abilities_api_init` P100001, after every registration, so a blocked ability ceases to exist for
consumers exactly as the removed gate made it cease to exist. That is fail-closed replacing fail-closed.

The interesting result of this pass is that **the SEC-001 fix introduced a new weakness of its own.**
Moving the translation from `admin_init` to an early all-request-path hook was correct — an admin-only
hook cannot close a hole whose consumers are REST and MCP — but it changes the concurrency profile
completely. `admin_init` on a single admin's page load is effectively serialised. `plugins_loaded` on a
public site is not: every concurrent visitor races the same unguarded read-then-write flag. Nothing in the
plan mentions a lock, a claim, or any atomicity for that flag (verified: no occurrence of
`lock`, `mutex`, or `concurren` anywhere under `specs/102-abilities-toolset-tabs/`).

Nothing here blocks task generation. SEC-008 should be settled in `plan.md` first, because it decides the
shape of the migration entry point rather than a detail inside it.

## Plan Artifacts Reviewed

`plan.md` (post-correction) · `spec.md` · `research.md` (R1/R2 revised) · `data-model.md` ·
`contracts/abilities-list.md` (counts contract revised) · `contracts/removed-surface.md` · `quickstart.md` ·
`memory-synthesis.md` · `docs/memory/INDEX.md` · `docs/memory/security-constraints.md` ·
`.specify/memory/CONSTITUTION.md`

`.specify/memory/security_constitution.md` remains **absent** — plan reviews still have no
project-specific security baseline. `/speckit-security-review-init` would fix that.

## Scope Correction (2026-09-12, after the rehearsal)

**The plugin does not support Multisite** — `README.txt:70-72` states it has not been tested on network
installations, which is the explicit single-site scoping Constitution §II permits. That removes the case
SEC-001 and research R1 were built around.

- **SEC-001 is resolved, not merely mitigated.** Its HIGH rating rested entirely on a network site that
  nobody administers never running the translation. With one site, the activator and the `init` P100 hook
  both reach it, and T040a verified the translation end to end on real data: 23 blocks predicted by a dry
  run, 23 written, and none of the 23 still present in `wp_get_abilities()`.
- **The defensive multisite branches were kept anyway.** "Untested and unsupported" is weaker than
  "prevented" — nothing stops an operator activating this on a network. If one does, the per-site done
  flag and the `is_multisite()` guard in `finish()` retain the network-wide source option rather than
  stranding every site but the first. One branch, and it is the difference between degraded and
  destructive.
- **SEC-008 and SEC-009 stand unchanged.** Both concern the all-paths trigger itself, which is still how
  the migration runs on single-site, so neither is affected by this correction.

## Resolved Since First Pass

| ID | Was | Resolution in plan |
|---|---|---|
| **SEC-001** | HIGH — `admin_init` trigger cannot reach sites whose exposure is via REST/MCP | Trigger moved to an early all-paths hook (`plugins_loaded`/`init`), per-site done flag retained. `plan.md` "Security Constraints Applied"; `research.md` R2 rewritten with the rejected option recorded. |
| **SEC-002** | MEDIUM — counts computed at admin render are PATH B numbers, under-reporting blocked abilities | Counts now served from `acrossai/v1` alongside the rows. `contracts/abilities-list.md` "Toolset counts" states the PATH A/B reason so it is not "simplified" later. |
| **SEC-004** | MEDIUM — translation window undocumented | Now an explicit bounded threat assumption in `plan.md`. Downgraded to INFORMATIONAL below. |

## Findings

### SEC-008 — All-paths trigger races an unguarded read-then-write flag *(NEW — introduced by the SEC-001 fix)*

- **Severity**: MEDIUM · **CVSS**: 5.9 · **OWASP**: A04:2025-Insecure Design · **CWE**: CWE-362: Concurrent Execution using Shared Resource without Proper Synchronization
- **Location**: `research.md` R1 ("Guard with a per-site done flag (`get_option()` / `update_option( …, '1', false )`)"), R2; `plan.md` Phase 5
- **Spec-Kit task**: TASK-SEC-008

`get_option()` then later `update_option()` is a read-then-write with no atomicity. Under `admin_init`
that was tolerable — one administrator, one page load. Under `plugins_loaded` on a public site, every
concurrent request on a freshly upgraded site reads the flag as unset simultaneously and every one of them
begins translating. The window is the full duration of the translation, which iterates every definition in
every configured category and writes override rows.

Consequences: duplicated insert attempts against the per-site override table, lock contention on a table
the front end is otherwise not touching, and — because the translation is specified to skip abilities that
already have an override — interleaved runs can observe each other's partial writes and reach different
conclusions about what "already exists".

**Recommendation**: claim the flag **atomically before doing the work**, not after.
`add_option( $flag, '1', '', false )` is a single INSERT that returns `false` when the row already exists,
so exactly one request wins the claim. A `wp_cache_add()` mutex is a weaker alternative — it is not
reliable without a persistent object cache.

**Note the trade this forces, and take it deliberately**: claiming before the work means a request that
dies mid-translation leaves the site permanently marked done and partially translated. That is precisely
the outcome spec Clarification Q2 already accepts ("one attempt, no retry, no rollback"), so claim-first is
consistent with the product decision. Claiming *after* the work would instead guarantee repeated
concurrent full runs on every busy site — strictly worse, and inconsistent with Q2 anyway.

### SEC-003 — Relocated capability check is only raise-only if the outer page gate survives *(unchanged)*

- **Severity**: MEDIUM · **CVSS**: 5.4 · **OWASP**: A01:2025-Broken Access Control · **CWE**: CWE-280: Improper Handling of Insufficient Permissions
- **Location**: `plan.md` §5 / `research.md` R6 — `Integrations_Settings_Menu.php`
- **Spec-Kit task**: TASK-SEC-003

`PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY` holds only because the single filtered `current_user_can()`
sits *behind* a surface already gated at `manage_options`. Registering the section on a page with a lower
capability, or letting the filtered check become the sole gate, lets a filter returning a weaker capability
**lower** the requirement for enabling a third-party integration's abilities.

**Recommendation**: assert in a test that the settings page capability is `manage_options` and that a
filter returning `'read'` does not let a subscriber toggle an integration.

### SEC-009 — Privileged migration is reachable by unauthenticated requests *(NEW — consequence of the SEC-001 fix)*

- **Severity**: LOW · **CVSS**: 3.1 · **OWASP**: A01:2025-Broken Access Control · **CWE**: CWE-862: Missing Authorization
- **Location**: `research.md` R2 — all-paths trigger

After the fix, an anonymous front-end request can trigger a routine that writes access-control rows. This
is **defensible and intended** — it is a system migration, not a user action, and requiring a capability
would reintroduce SEC-001 exactly. It is recorded so the trust boundary is explicit rather than accidental.

**Constraint to carry into implementation**: the translation must derive every input from stored state —
the site option and the definitions registry — and **must never read request input**. No `$_GET`
short-circuit, no "force re-run" query parameter, no request-derived site or category selection. With an
unauthenticated trigger, any such parameter becomes an unauthenticated write primitive.

### SEC-005 — Permanent redirect is not revocable *(unchanged)*

- **Severity**: LOW · **CVSS**: 2.6 · **OWASP**: A05:2025-Security Misconfiguration · **CWE**: CWE-1188

Target is correctly built from `admin_url()` with `sanitize_key()` on `tab`, so there is no open-redirect
surface. Only permanence is at issue: a 301 is cached indefinitely, so the retired slug can never be
reused. Use 302 unless permanence is deliberate.

### SEC-004 — Translation window is a bounded, documented threat assumption *(INFORMATIONAL — resolved)*

`plan.md` now states the bound: on single-site, one activation; on multisite, until each site's first
qualifying request. Recorded rather than mitigated, per spec Clarification Q2.

### SEC-006 — `sub_keys` truncation must fail toward blocking *(INFORMATIONAL — unchanged)*

`MAX_SUB_KEYS = 50` truncated the stored pick list for `acrossai-block` (87), `acrossai-elementor` (62) and
`acrossai-rank-math` (61), so it is not a reliable allow-list. `data-model.md` already mandates resolving
membership from `get_definitions()` and treating anything not explicitly `true` as unpicked — failing
toward blocking. Correct as specified; restated so it is not simplified during implementation.

### SEC-007 — New `tab_group` field carries no sensitive data *(INFORMATIONAL — unchanged)*

A static grouping identifier on an endpoint already gated at `manage_options`. The same change **removes**
the ~451-row definitions payload with full input/output JSON Schema previously inlined into admin HTML at
`admin/Main.php:294` — a net reduction in data surfaced to the browser.

## Confirmed Secure Patterns

| Pattern | Evidence |
|---|---|
| Replacement control is fail-closed | `unregister_blocked_abilities()` calls `wp_unregister_ability()` at P100001, after all registrations, so nothing can restore a blocked ability |
| Strict comparison in the access decision | `false === $row->site_allowed` — satisfies SEC-04 |
| Admin visibility without weakening enforcement | PATH A/B split lets this plugin's REST namespace read the unpruned registry while consumers see the pruned one |
| Counts now honour that boundary | Aggregates moved to `acrossai/v1` so counts and rows come from the same read (SEC-002 fix) |
| Same-request ordering is safe | The translation writes at `plugins_loaded`; `AcrossAI_Ability_Override_Processor` loads its override cache at `wp_abilities_api_init` P100001 — later in the same request, so a site translated on request *N* is enforced on request *N*, not *N+1* |
| No open redirect | Target always built from `admin_url()`; `tab` via `sanitize_key()`; unknown values fall back client-side |
| Group resolution keeps protected-slug exclusion | Filtering routed through `AcrossAI_Ability_Group::member_names()` |
| Translation never escalates | Writes only `site_allowed = false`; never overwrites an existing override (FR-008) |
| Opt-in defaults stay closed | Absent integration entry remains `false` (FR-021); copy is OR-monotonic |
| No new SQL surface | Translation writes through BerlinDB; no raw queries |

## Action Plan & Next Steps

1. ~~**Settle SEC-008 in `plan.md` before `/speckit-tasks`.**~~ **Applied 2026-09-12** — the flag is now
   claimed with `add_option()` before the work; see `plan.md` § Security Constraints Applied. SEC-009's
   "stored state only, never request input" constraint was recorded there at the same time, and SEC-005 is
   closed by spec FR-024, which mandates a permanent redirect.
2. SEC-003 and SEC-009 are constraints to carry into tasks and tests; neither changes the design.
3. SEC-005 is a one-word decision (301 vs 302).
4. Recommend `/speckit-security-review-followup` to raise TASK-SEC-003, TASK-SEC-008, TASK-SEC-009.
5. `/speckit-security-review-init` — this project still has no `security_constitution.md`.

Not repeated here: the architecture pass separately flagged the undecided counts contract (header vs
sibling route) and the Critical module-boundary placement of the migration. Both stand.
