# Security Constitution — AcrossAI Abilities Manager

**Status**: Active · **Created**: 2026-09-12 · **Version**: 1.0.0

Companion to `.specify/memory/CONSTITUTION.md`, which remains the general standard. Where the two overlap,
they agree; this file goes deeper and is the document security reviews audit against.

Every rule below is derived from this codebase — its architecture, its recorded decisions, and in several
cases a defect it actually shipped. Rules that cite a `BUG-*` identifier exist because that failure
happened here.

---

## 1. Trust Boundaries

| Zone | Contents | Trust |
|---|---|---|
| **Untrusted** | REST request bodies, query args, headers, admin form posts, MCP tool arguments, third-party plugin output, `args.*` values in the ability definitions registry | Never trusted |
| **Semi-trusted** | Values read back from this plugin's own options and tables | Structurally trusted, value-sanitised on read |
| **Trusted** | Code in `includes/`, `admin/`, `src/` and the WordPress core API surface | Trusted |

**The plugin's central risk is not data theft — it is capability.** An ability is a callable operation
against the site: `database/search-replace`, the file-manager set, `updates/*`. A control failure here
grants an attacker *actions*, not records. Availability and integrity outrank confidentiality in every
severity judgement on this project.

**Named boundaries:**

- **PATH A / PATH B** (`AcrossAI_Ability_Override_Processor`). PATH A — requests to this plugin's own REST
  namespace — reads the registry **unpruned**, so blocked abilities remain visible to the admin UI. PATH B
  — everything else — unregisters every `site_allowed = false` ability at `wp_abilities_api_init` P100001.
  Any new read of the ability registry MUST state which path it is on. Aggregates must come from the same
  path as the rows they describe (`BUG-PATH-B-AGGREGATE-UNDERCOUNT`).
- **Network vs site** (multisite). Configuration is network-scoped (`get_site_option`); override rows are
  per-site (`$global = false`, `SEC-03`). Anything crossing that boundary follows
  `PATTERN-NETWORK-OPTION-TO-PER-SITE-TABLE-MIGRATION`.
- **Plugin vs third-party integration.** A third-party integration is opt-in and off by default. Its
  synthetic definition rows are display-only and MUST keep fail-closed `execute_callback` and
  `permission_callback`.

---

## 2. Authentication & Authorization Standards

WordPress capabilities and nonces are the only authentication mechanism. There is no bespoke session,
token, or identity layer, and none may be introduced without amending this document.

- **R-AUTH-1** — Every `permission_callback` and every `check_permission()` returns **only** `true`,
  `false`, or `WP_Error`. Returning `WP_REST_Response` is a critical defect: it is truthy, so WordPress
  grants access regardless of the status code inside it (`BUG-PERMISSION-CALLBACK-TRUTHY-RESPONSE`).
- **R-AUTH-2** — Every admin page render and every mutating endpoint enforces a capability check,
  `manage_options` minimum, plus nonce verification (`X-WP-Nonce` for REST).
- **R-AUTH-3** — A filter-overridable capability MUST be a **single** `current_user_can( $filtered )` behind
  a surface already gated at `manage_options`. That shape is inherently raise-only. Splitting it into two
  checks, or making the filtered check the sole gate, lets a filter *lower* the requirement
  (`PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY`).
- **R-AUTH-4** — Access-control rules from `wpb-access-control` are **fail-open**: a missing rule means no
  restriction. Any consumer MUST therefore also call `WP_Ability::check_permissions()` as an independent
  gate (`BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS`). Never probe for methods that do not exist —
  `get_permission_callback()` and `get_args()` are not on `WP_Ability`, and a failed probe silently grants
  access to everyone (`BUG-WP-ABILITY-CHECK-PERMISSIONS`).
- **R-AUTH-5** — A `null` return from an access-control library is not `false`. Compare explicitly
  (`BUG-AC-NULL-RETURN-SILENT-FAIL`).
- **R-AUTH-6** — Client-side availability flags such as `access_control_available` are **rendering gates
  only**. Server authorisation is always enforced independently (`DEC-AC-RENDERING-GATE`).
- **R-AUTH-7** — Query-layer helpers stay auth-free; callers gate before exposure
  (`DEC-BY-SOURCE-AUTHZ`). A query class is not an authorisation boundary.

---

## 3. Data Isolation & Privacy

This plugin stores no PII beyond WordPress user IDs, and no financial, health, or payment data. The
sensitive assets are **capability configuration** and **ability payload schemas**.

- **R-DATA-1** — Per-site isolation on multisite is explicit and deliberate (`SEC-03`). A migration whose
  source and target differ in scope uses a **per-site** done flag; a network-wide flag marks the whole
  network complete after one site finishes.
- **R-DATA-2** — Sparse stores whose keys do not share one default MUST dispatch on key type **before**
  applying any default (`BUG-SPARSE-STORAGE-UNIFORM-DEFAULT-ASSUMPTION`).
- **R-DATA-3** — Never demote a truthy opt-in during a migration, and never overwrite a value an
  administrator set by hand (`PATTERN-OPTION-KEY-MIGRATION-OR-MONOTONIC`).
- **R-DATA-4** — Minimise what reaches the browser. Inline admin payloads carry only fields the client
  actually reads; do not ship full input/output JSON Schema to the page because it is convenient.
- **R-DATA-5** — `uninstall.php` wraps destructive drops in the opt-in delete-data gate; option deletions
  belong **inside** that gate (`BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE`, `PATTERN-UNINSTALL-DATA-GATE`).

---

## 4. Secrets Management

**This plugin holds no secrets.** No API keys, no credentials, no tokens. Outbound HTTP goes only to
public WordPress.org endpoints.

- **R-SEC-1** — Introducing any credential requires amending this document first. Credentials never live
  in options readable by a lower capability, never in an inline admin payload, and never in a log line.
- **R-SEC-2** — All outbound HTTP uses `wp_remote_get()` / `wp_remote_post()`. Never `curl` directly.
- **R-SEC-3** — Any newly contacted external service is a README "External Services" disclosure change,
  even when the data sent is unchanged (`DEC-EXTERNAL-SERVICE-DISCLOSURE-TRIGGER`).

---

## 5. Secure-by-Design Patterns

- **R-PAT-1** — Sanitise at entry, escape at render, using the most specific function available. Slugs go
  through `sanitize_ability_slug()` at every REST endpoint that receives one, max 255 chars (`SEC-01`).
- **R-PAT-2** — **Strict comparison** in every access decision: `===`, and `in_array( …, true )`. Loose
  comparison coerces (`0 == 'admin'` is `true`) and has produced a live bypass here (`SEC-04`,
  `BUG-LOOSE-COMPARISON-BYPASS`).
- **R-PAT-3** — All SQL through `$wpdb->prepare()`; identifiers via `%i`, values via `%s` / `%d`. No
  interpolated table names without a documented, narrow, allowlisted suppression.
- **R-PAT-4** — `eval()`, `extract()`, and shell/process execution are prohibited in production code. A
  forbidden-function finding is fixed by removal or replacement, never by a workflow ignore-code, which
  weakens the gate for all future code (`BUG-EVAL-NOT-SUPPRESSIBLE`).
- **R-PAT-5** — Required-field enforcement is audited at **all three** layers — REST args, sanitizer
  presence guards, and validator. A JS-side hook can strip a field before it reaches any one of them
  (`PATTERN-REQUIRED-FIELD-MULTI-LAYER-AUDIT`).
- **R-PAT-6** — The definitions registry key-allowlists `args` but does **not** value-sanitise it. Every
  consumer of `args.*` escapes at the point of use or renders through React text nodes
  (`PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH`).
- **R-PAT-7** — Registry-driven JSON fields carry a 64 KB guard at the DB layer (`DEC-JSON-SIZE-GUARD`).
- **R-PAT-8** — Deleting a symbol requires an exhaustive `grep -rEn` over `includes/ src/ tests/ admin/`
  for the symbol **and every public member of it** — not the class name alone
  (`BUG-INVENTORY-GREP-MISS`).

---

## 6. API & Integration Security

- **R-API-1** — One REST namespace per module; never shared (`DEC-ABILITYAPI-NAMESPACE`).
- **R-API-2** — Literal-segment routes register **before** wildcard `[^/]+` routes, or the wildcard
  shadows them (`BUG-REST-ROUTE-ORDER-LITERAL-BEFORE-WILDCARD`).
- **R-API-3** — 404 checks run before database lookups, so a probe cannot distinguish "absent" from
  "forbidden" by timing (`DEC-EARLY-404-REST-CHECK`).
- **R-API-4** — Protected slugs are excluded through the central utility, never by ad-hoc filtering
  (`DEC-PROTECTED-SLUGS-PATTERN`).
- **R-API-5** — Every optional integration degrades gracefully when absent, and third-party integrations
  are **off unless explicitly enabled**. `enable_filter()` calls are wrapped so a third-party bug cannot
  fatal the site.
- **R-API-6** — Unauthenticated-reachable routines (migrations on early hooks, cron) derive **every** input
  from stored state. No superglobal access, no "force" parameter — with an unauthenticated trigger, any
  request-derived parameter is an unauthenticated write primitive.
- **R-API-7** — Slugs containing `/` are passed raw to access-control endpoints; `encodeURIComponent()`
  strips `%2F` and corrupts the key (`BUG-COMPOSER-AC-SLUG-DOUBLE-ENCODE`). `SEC-01` validates server-side.

---

## 7. Audit, Logging & Monitoring

Ability execution logging was removed in Feature 040 and moves to a companion plugin. This project
therefore has **no security event log**, which is a deliberate, known limitation.

- **R-LOG-1** — Because there is no audit trail, correctness of security-relevant state changes must be
  established by automated tests **before release**, not observed after it.
- **R-LOG-2** — Never log capability values, slugs with embedded credentials, or full ability payloads.
- **R-LOG-3** — Degraded-mode admin notices use only WordPress globals inside the closure and always gate
  on `manage_options` (`PATTERN-ADMIN-NOTICE-SELF-CONTAINED`, `DEC-FAIL-OPEN-NOTICE`).
- **R-LOG-4** — Silent failure is the dominant risk mode on this project. A fail-open path, a swallowed
  `null`, or a migration that reports nothing must be paired with a test that would fail if it broke.

---

## 8. Compliance Mapping

| Framework | Relevance |
|---|---|
| **OWASP Top 10 2025** | Primary. A01 (access control) and A04 (insecure design) dominate; A02/A03 are marginal — this plugin holds no secrets and writes no dynamic SQL |
| **WordPress.org Plugin Directory Guidelines** | Binding for distribution. Guideline 7 (external services disclosure) is the live one |
| **WordPress Plugin Check** | Required CI gate, zero errors and zero warnings on the production surface |
| SOC2 / PCI / HIPAA / GDPR | Not applicable — no PII beyond WordPress user IDs, no payment or health data |

---

## Assumptions to confirm

Derived from the codebase rather than asked. Correct any that are wrong:

1. No credentials or API keys are held now, or planned.
2. Multisite is supported but not a hostile multi-tenant environment — network administrators are trusted.
3. No compliance framework beyond OWASP and the WordPress.org guidelines applies.
4. The absence of an audit log is accepted, with logging deferred to a companion plugin.

## Governance

Amendments require a matching entry in `docs/memory/DECISIONS.md`. Rules citing a `BUG-*` or `SEC-*`
identifier MUST NOT be relaxed without superseding that entry — each records a defect that reached this
codebase.
