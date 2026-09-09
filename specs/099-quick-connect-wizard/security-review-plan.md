---
document_type: security-review
review_type: plan
assessment_date: 2026-09-08
codebase_analyzed: acrossai-abilities-manager / specs/099-quick-connect-wizard
total_files_analyzed: 11
total_findings: 8
overall_risk: MODERATE
critical_count: 0
high_count: 0
medium_count: 1
low_count: 4
informational_count: 3
owasp_categories: [A01, A03, A05, A08]
cwe_ids: [CWE-829, CWE-601, CWE-862, CWE-494, CWE-532, CWE-20, CWE-770]
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

# Security Review — Feature 099 Quick Connect Wizard (Plan)

> **Revision 2 — 2026-09-08, re-run against updated project memory.**
> Plan artifacts are byte-identical to Revision 1 (verified by mtime: all predate the first review).
> The **memory hub changed**: `DEC-ADMIN-THIRD-PARTY-EMBED` was captured into `DECISIONS.md` as an
> Active decision. SEC-001 therefore changes character — it is no longer a recommendation against
> general good practice, it is now a **plan conflict with a binding project standard**. Severity and
> finding counts are unchanged; the obligation is not. No other finding is affected.

## Executive Summary

**Overall risk: MODERATE.** No critical or high findings. The plan is unusually clean for a feature
that installs software: it adds no persistence, no SQL, and no new data at rest, and its most
dangerous capability — installing and activating a plugin — is correctly gated behind capabilities
that *already* grant that power through the Plugins screen, so it confers **no privilege
escalation**.

The single Medium finding is not the installer but the **YouTube embed**: three admin screens make
an unconsented third-party request from an authenticated session, leaking the admin's IP, user
agent, and a referrer that reveals the site's admin URL. That is a privacy exposure and, separately,
friction against WordPress.org Guideline 7.

Four Low findings are hardening items (open-redirect surface on the hand-off URL, enum validation,
defence-in-depth capability check on render, and absent server-side abuse limits). Three
informational items record design decisions worth stating explicitly rather than defects.

**Nothing here blocks implementation.** All eight items are addressable during the build.

## Plan Artifacts Reviewed

| Artifact | Purpose |
|---|---|
| `specs/099-quick-connect-wizard/spec.md` | 48 FRs / 12 SCs |
| `specs/099-quick-connect-wizard/plan.md` | Constitution Check, structure |
| `specs/099-quick-connect-wizard/research.md` | R1–R10 decisions |
| `specs/099-quick-connect-wizard/data-model.md` | Entities, guard order |
| `specs/099-quick-connect-wizard/contracts/quick-connect-rest.md` | REST contract |
| `specs/099-quick-connect-wizard/contracts/wizard-router.md` | Client contract |
| `specs/099-quick-connect-wizard/quickstart.md` | Verification |
| `specs/099-quick-connect-wizard/memory-synthesis.md` | Durable-memory context |
| `.specify/memory/CONSTITUTION.md` | v1.4.8 §IV Security First |
| `docs/memory/security-constraints.md` | SEC-04 strict comparison |
| `docs/memory/INDEX.md` | Selected routing entries |

**Note**: `.specify/memory/security_constitution.md` has never been initialized, so this review is
anchored on Constitution §IV plus `docs/memory/security-constraints.md`. Consider running
`/speckit-security-review-init`.

**Revision 2 addendum**: `docs/memory/DECISIONS.md` now also carries `DEC-ADMIN-THIRD-PARTY-EMBED`
(Active), which functions as a project-specific security baseline for admin embeds — the first such
entry. It was derived from SEC-001 in Revision 1 of this document, so the finding and the standard
are self-consistent by construction.

## Trust Boundaries

| # | Boundary | Authn / Authz | Notes |
|---|---|---|---|
| 1 | Admin browser → `acrossai/v1/quick-connect/*` | WP cookie + `X-WP-Nonce`; `manage_options`; install route adds `install_plugins` **and** `activate_plugins` | Correctly modelled |
| 2 | Plugin → wordpress.org (`plugins_api`, package download) | None (public API over TLS) | Code-execution boundary — see SEC-002 |
| 3 | Plugin → filesystem (`Plugin_Upgrader`) | WP filesystem abstraction | Inherits core behaviour |
| 4 | Admin browser → YouTube | None | Unconsented third-party — see SEC-001 |

## Vulnerability Findings

### SEC-001 — Unconsented third-party embed in an authenticated admin session

- **Severity**: MEDIUM · **CVSS**: 4.3
- **Status (rev 2)**: **Standards violation** — conflicts with Active decision `DEC-ADMIN-THIRD-PARTY-EMBED`
- **Location**: `spec.md` FR-019/FR-019a; `contracts/wizard-router.md` (VideoEmbed); screens 2, 3, 7
- **OWASP**: A08:2025-Software and Data Integrity Failures · **CWE**: CWE-829
- **Spec Kit task**: TASK-SEC-001

Three screens embed `youtube.com/embed/...` directly. On load — with no consent step — the admin's
browser discloses IP, user agent, and a `Referer` revealing the site's admin URL to a third party,
and accepts third-party cookies into an authenticated admin context. FR-019a's fallback link
mitigates *availability* when the embed is blocked, but not the disclosure when it is not.

This also runs against WordPress.org Guideline 7 (external requests without consent), which this
project has already had to reason about.

**Recommendation** (in ascending order of protection, all compatible with SC-005 visual parity):

1. Switch the host to `https://www.youtube-nocookie.com/embed/...`.
2. Add a `referrerpolicy` and `loading="lazy"` to the iframe.

   > **Corrected 2026-09-08 by browser testing.** `no-referrer` was implemented
   > first and **breaks playback**: YouTube returns *"Video player configuration
   > error (Error 153)"* because it needs a `Referer` to verify which origin is
   > permitted to embed the video. The working policy is
   > **`strict-origin-when-cross-origin`**, which still resolves this finding —
   > it sends only the origin (`https://example.com`), never the admin path and
   > query string that SEC-001 is actually about. Verified end to end on a live
   > site: the walkthrough plays with the privacy host and an origin-only
   > referrer.
3. **Preferred** — render a click-to-load facade: a locally-hosted still frame that swaps in the
   iframe on click. This makes the third-party request *user-initiated*, which simultaneously
   resolves the consent question, the blocked-embed edge case, and the disclosure.

**Revision 2 — now binding, not advisory.** `DEC-ADMIN-THIRD-PARTY-EMBED` (Active, captured
2026-09-08) makes all four of these mandatory for any third-party embed on an admin screen. The
recorded embed URL for this feature — `https://www.youtube.com/embed/6nDuURDNmLc?list=PLL-i34ne1J0c&rel=0`
in `docs/planning/099-quick-connect-onboarding-wizard.md` and the spec clarification — uses the
non-privacy host and specifies no facade or referrer policy, so it does not currently satisfy that
decision. The design artifacts must be amended before implementation, or the decision consciously
deviated from and that deviation recorded.

### SEC-002 — Runtime plugin installation is a code-execution path

- **Severity**: LOW · **CVSS**: 3.1
- **Location**: `contracts/quick-connect-rest.md` → `POST /install-plugin`; `research.md` R9
- **OWASP**: A08:2025-Software and Data Integrity Failures · **CWE**: CWE-494
- **Spec Kit task**: TASK-SEC-002

The endpoint downloads and activates code. Severity is held to Low by a strong control set: a
strict single-value allowlist (`in_array(..., true)`, SEC-04), `install_plugins` **and**
`activate_plugins`, and nonce verification. **Crucially, any caller who passes those checks can
already install arbitrary plugins through `plugin-install.php`, so this route grants no new
privilege.**

Residual risk: `plugins_api` results and `Plugin_Upgrader` options are filterable by other plugins,
so a hostile or compromised plugin could redirect the download source. WordPress core does not
verify plugin package signatures, so this is inherited platform behaviour, not a defect introduced
here.

**Recommendation**: after `activate_plugin()`, assert the activated basename equals the expected
`acrossai-mcp-manager/acrossai-mcp-manager.php` before reporting success, so a redirected package
cannot be reported as the expected plugin. Log a warning if it differs.

### SEC-003 — Hand-off URL assigned to `window.location` from a response body

- **Severity**: LOW · **CVSS**: 3.5
- **Location**: `contracts/quick-connect-rest.md` (`plugins.mcpManagerWizardUrl`); step 5 hand-off
- **OWASP**: A01:2025-Broken Access Control · **CWE**: CWE-601
- **Spec Kit task**: TASK-SEC-003

The client navigates to a URL taken from the REST payload. The value is built server-side with
`admin_url()`, so it is trustworthy today — but the pattern (assigning a server-supplied string to
`window.location.href`) is one refactor away from an open redirect, and the plan does not state a
client-side constraint.

**Recommendation**: before assigning, verify the URL's origin matches `window.location.origin`;
or construct it client-side from the already-localized `bootstrap.adminUrl` and drop the field from
the response entirely.

### SEC-004 — `method` must be validated as an enum, not merely sanitized

- **Severity**: LOW · **CVSS**: 3.1
- **Location**: `data-model.md` §3; `research.md` R3
- **OWASP**: A03:2025-Injection · **CWE**: CWE-20
- **Spec Kit task**: TASK-SEC-004

`step` and `method` come from the URL. Sanitizing (`sanitize_key`) constrains the character set but
does not constrain the value. Any use of these in rendered output or in branching must compare
against the closed allowlists (`mcp-manager`/`mcp-adapter`, `1`–`7`/`done`) with strict comparison,
falling back to the default rather than echoing the received value.

**Recommendation**: validate against the enum immediately on read, both server-side and in the
client router; never render either value directly.

### SEC-005 — Page render should re-check capability (defence in depth)

- **Severity**: LOW · **CVSS**: 2.7
- **Location**: `plan.md` → `QuickConnectPage::render()`; `admin/Partials/Menu.php` branch
- **OWASP**: A01:2025-Broken Access Control · **CWE**: CWE-862
- **Spec Kit task**: TASK-SEC-005

The wizard renders through the existing menu page, whose registration enforces `manage_options`. The
plan does not require `render()` to check independently. If the branch is ever reached from another
call path, the capability gate disappears with it.

**Recommendation**: begin `render()` with an explicit `current_user_can( 'manage_options' )` check
and `wp_die()` otherwise — mirroring the sibling's `render_list_page()`.

### SEC-006 — No server-side limit on repeated install attempts

- **Severity**: INFORMATIONAL · **CVSS**: 2.2
- **Location**: `spec.md` FR-031; `contracts/quick-connect-rest.md`
- **OWASP**: A05:2025-Security Misconfiguration · **CWE**: CWE-770
- **Spec Kit task**: TASK-SEC-006

FR-031 prevents concurrent installs **client-side only**. A direct caller could invoke the endpoint
repeatedly, generating outbound requests to wordpress.org and filesystem churn. Requires
`install_plugins`, so the actor is already highly privileged; impact is self-inflicted resource use.

**Recommendation**: a short-lived in-flight transient guard returning `409` while an install is
running would make the client-side rule authoritative. Optional.

### SEC-007 — Blanket `remove_all_actions()` hides all admin notices on the wizard screen

- **Severity**: INFORMATIONAL · **CVSS**: 2.0
- **Location**: `plan.md` → `QuickConnectPage::suppress_admin_notices()`
- **OWASP**: A05:2025-Security Misconfiguration · **CWE**: CWE-1021
- **Spec Kit task**: TASK-SEC-007

Suppressing all four notice hooks also suppresses security-critical notices from core and other
plugins (site-health warnings, malware alerts, urgent update prompts). Scope is tightly bounded —
one screen, one request, only when the wizard flag is present — and the sibling wizard does the
same, so this is recorded rather than objected to.

**Recommendation**: keep the suppression request-scoped (never global) and ensure the completion
screen returns the administrator to a normal screen where notices render again.

### SEC-008 — Raw upgrader output written to the error log

- **Severity**: INFORMATIONAL · **CVSS**: 2.0
- **Location**: `research.md` R9; `contracts/quick-connect-rest.md` error table
- **OWASP**: A05:2025-Security Misconfiguration · **CWE**: CWE-532
- **Spec Kit task**: TASK-SEC-008

Routing raw `Plugin_Upgrader` / `plugins_api` messages to `error_log()` is the right call — it keeps
filesystem paths out of the HTTP response (FR-030). Noted only because on misconfigured hosts
`debug.log` is web-readable, so those paths can become externally visible.

**Recommendation**: gate the logging on `WP_DEBUG_LOG`, consistent with the plugin's existing
diagnostic-logging pattern.

## Confirmed Secure Patterns

- **Strict-comparison allowlist** on the install slug — honours SEC-04 and closes the type-coercion
  bypass that a loose `in_array()` would open.
- **Capability escalation above the baseline**: `install_plugins` **and** `activate_plugins` for the
  only mutating route, over the `manage_options` floor.
- **`permission_callback` returns only `true|false|WP_Error`** — explicitly stated in the contract.
  This directly addresses the constitution's named critical defect (a `WP_REST_Response` is truthy,
  so it silently grants access).
- **Error hygiene**: hand-authored client messages; no paths, vendor strings, or upgrader output in
  responses.
- **Minimal attack surface by construction**: no new tables, options, or settings; the only
  persisted artifact is a 30-second single-use transient, deleted *before* the guards run so a
  failed hand-off cannot loop.
- **No SQL introduced** — the entire §II `%i`/`prepare()` surface is untouched.
- **No `dangerouslySetInnerHTML`** anywhere in the client tree.
- **Two routes only** — declining to port the sibling's `/step`, `/complete`, and per-user
  scratchpad removes an entire class of stored-state and cleanup risk.
- **Guard ordering** in `ActivationRedirect` is fail-closed and idempotent (transient consumed
  first, capability checked before any redirect).

## Action Plan & Next Steps

1. **Before implementation** — fold TASK-SEC-001 (embed hardening; facade preferred) and
   TASK-SEC-003/004/005 (open-redirect, enum validation, render capability) into `tasks.md`. Each is
   a few lines of work if planned now and awkward to retrofit later.
2. **Optional** — TASK-SEC-002 (post-activation basename assertion) and TASK-SEC-006 (in-flight
   guard).
3. **No `/speckit-security-review-followup` run is required** — there are no Critical or High
   findings.
4. **Consider `/speckit-security-review-init`** — `.specify/memory/security_constitution.md` does
   not exist, so plan reviews currently have no project-specific security baseline to check against.

## Memory Hub INDEX.md Row

```text
| specs/099-quick-connect-wizard/security-review-plan.md | plan | 2026-09-08 | MODERATE | C:0 H:0 M:1 L:4 I:3 | A01,A03,A05,A08 |
```
