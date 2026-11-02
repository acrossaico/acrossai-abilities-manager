# Feature 134 — a governance layer for database access, the way the file manager has one

> Feature number provisional — let `/speckit.specify` assign the next free one (133 is integration discovery).

**The ask.** The file manager has a policy layer: an administrator sets a read allowlist, a write
allowlist, redaction rules and content filters once, and every `file-manager/*` ability inherits
them. The database abilities have no equivalent. Set the controls in one place and have them apply.

## What exists today

**Files** — policy is configurable and centrally enforced:

| Piece | Where |
|---|---|
| Path allowlists, read and write | `Utilities\Path_Allowlist_Guard` |
| Secret redaction on reads | `Utilities\Secret_Redactor` |
| Write gating | `Utilities\File_Mods_Guard` |
| Admin surface | `Rest\File_Manager_Settings_Controller` — 6 routes: `read-allowlist`, `write-allowlist`, `redaction`, `content-filters`, `backup-audit`, `backup-audit-stats` |

**Database** — 19 abilities, and policy that is real but *hardcoded*:

| Piece | Where | Configurable? |
|---|---|---|
| Core-table allowlist (18 logical keys → `$wpdb` properties, so no ability takes a raw table name) | `Utilities\Database_Core_Table_Allowlist` | no |
| Column-name allowlist on writes (0.0.42 security fix) | the three write abilities | no |
| Mutation attribution | `Utilities\Database_Mutation_Attribution` | no |
| Redaction of returned values | — | **absent** |
| Audit log of mutations | — | **absent** |
| Admin surface | — | **absent** |

So the gap is the configurable surface and two missing controls, not the whole model.

## Survey: nothing to depend on, plenty to borrow

### WordPress database plugins — eight downloaded and read, none is a library

| Plugin | Installs | PHP lines | allowlist | redaction | audit | hooks |
|---|---|---|---|---|---|---|
| `wp-phpmyadmin-extension` | 40k | **589,973** | ✗ | ✗ | ✗ | 1 action |
| `wp-dbmanager` | 50k | 5,238 | ✗ | ✗ | ✗ | 1 filter |
| `pexlechris-adminer` | 20k | 2,038 | ✗ | ✗ | ✗ | 1 filter |
| `db-access-adminer` | 1k | 24,132 | ✗ | ✗ | ✗ | 1 filter |
| `sql-executioner` | 2k | **124** | ✗ | ✗ | ✗ | none |
| `simple-table-manager` | 400 | 1,935 | ✗ | ✗ | ✗ | 4 filters |
| `flydb` | 100 | 1,594 | ✗ | ✗ | ✗ | none |
| `db-viewer` | 100 | 5,637 | ✗ | ✗ | ✗ | internal AJAX only |

Every one is an **admin UI you open**, not a layer you configure and delegate to. Zero allowlists,
zero redaction, zero audit. Notable specifics:

- **WP phpMyAdmin** bundles the entire phpMyAdmin release — 2,324 files — so depending on it means
  inheriting phpMyAdmin's attack surface and CVE stream for no governance in return. wp.org already
  flags it untested against the last 3 WP majors.
- **WP Adminer**'s only extension point, `pexlechris_adminer_access_capabilities()`, controls *who
  may open the UI*, nothing about *what they may touch*.
- **SQL Executioner** is 124 lines and talks to `mysqli_connect()` directly rather than `$wpdb`.

**Conclusion: build, do not depend.**

### Easy MCP AI — no SQL tools, but three patterns worth copying

It ships no database or SQL tool at all; its DB-adjacent code is infrastructure. The governance
scaffolding is the valuable part:

1. **`Privileged_Groups`** — the high-risk tools (`files_read`, `wp_cli`, `execute_php`,
   `search_replace`) each carry their **own custom capability** rather than sharing
   `manage_options`, plus a risk `floor`, a `read_only` flag, an OAuth-style scope prefix
   (`mcp:privileged:`), and resource guards: 30s timeout, 128 MB memory headroom, 64 KB output cap,
   60 calls/minute.
2. **`Approval_Gate`** — out-of-band human approval. The administrator declares glob patterns of
   tool names that require sign-off; requests carry statuses (pending/approved/denied/consumed/
   expired), a 15-minute TTL, 7-day retention and a 2 KB preview of the pending change, behind an
   `Approval_Store` interface. **This is the one that should change our thinking:** our `confirm:
   true` is asserted by the model itself, which is a materially weaker guarantee than a human
   clicking approve.
3. **`history/`** — a redacted **before-image change log** with `Rebuild_Planner` for undo, a table
   **denylist** (`users`, `usermeta`, and its own token/audit/oauth tables), a `$core_tables` map
   with `id_cols` so a row stays addressable for rollback, transaction awareness, a 200-rows-per-call
   cap and memory guards.

### Novamira — opposite philosophy, sharpest reasoning

Explicitly "full access … for development and staging environments only", so not a model for
production. But `read-mode/policy.php` contains three things worth adopting verbatim:

1. **Honest framing.** "Read mode protects a site from mistakes of the administrator's own agent
   while it only inspects the site. It is **not** a security boundary against an administrator." Our
   wording should make the same distinction instead of implying more.
2. **It reads its own switch through `$wpdb`, not `get_option()`** — "so option filters and the
   object cache cannot make it read as off. There is deliberately no filter on this state." Our
   `Path_Allowlist_Guard` reads both allowlists via `get_option()`
   (`Path_Allowlist_Guard.php:68,87`), so a `pre_option_*` filter or a poisoned object cache can
   make the enforced policy differ from the stored and displayed one. Worth fixing in the file
   manager too, not only in the new layer.
3. **It refuses to derive authorization from `readonly` annotations** — "metadata that any provider
   can set" — using an explicit trusted allowlist instead. We lean on those annotations in places,
   and Easy MCP AI auto-enables abilities on `destructive === false`. A provider can declare
   `readonly: true` on a destructive ability.

### GitHub — the field has converged on one guardrail set

| Project | ★ | Model |
|---|---|---|
| `bytebase/dbhub` | 3,765 | "**Guardrails**: read-only mode, row limiting, and query timeout"; ships read-only by default |
| `crystaldba/postgres-mcp` | 3,447 | `--access-mode=restricted` (read-only transaction + execution-time limit) vs `unrestricted`; "safe SQL parsing" |
| `benborla/mcp-server-mysql` | — | read-only access to MySQL |

Consensus: **one access-mode switch**, read-only enforced **at transaction level**, a **row limit**,
a **query timeout**, and a real parser rather than a regex. `postgres-mcp` also points at
`alexander-zuev/supabase-mcp-server` for a three-tier safety architecture.

## Scope

A `Db_Policy_Guard` plus a settings controller mirroring the file-manager shape, built on the
borrowed parts:

- **One access mode** — `read-only` / `restricted` / `unrestricted` — read through `$wpdb` rather
  than `get_option()`, with no filter on it, per Novamira's reasoning.
- **Read-only enforced by `START TRANSACTION READ ONLY`**, not by inspecting the statement. MySQL
  then refuses writes however they are spelled. See issue #293: the current verb-regex guard lets
  `SELECT … INTO OUTFILE` through, which on a host with a permissive `secure_file_priv` turns a
  read-only query into an arbitrary file write — **routing around the file manager's write
  allowlist entirely.**
- **Resource caps per `Privileged_Groups`** — row limit enforced by fetching at most N rather than
  rewriting SQL (issue #293 again: the current `LIMIT`-append silently does not apply when `LIMIT`
  appears in a subquery or a string literal), plus `MAX_EXECUTION_TIME` and an output byte cap.
- **Table allow/denylist** extending `Database_Core_Table_Allowlist` beyond the 18 core tables to
  custom and plugin tables, with a denylist that always wins — `users`/`usermeta` by default, as
  Easy MCP AI does.
- **Value redaction on reads**, reusing `Secret_Redactor`. Its docblock currently scopes it to
  file-manager reads, but its design — one built-in WordPress-credentials pattern plus
  admin-supplied literals plus automatic pickup of AI connector keys — is content-agnostic.
- **A before-image change log with undo**, reusing `Database_Mutation_Attribution` and borrowing the
  `id_cols` idea so a changed row stays addressable.
- **An admin surface** mirroring `File_Manager_Settings_Controller`.

**Open question worth settling before implementation:** whether destructive database abilities move
from self-asserted `confirm: true` to an `Approval_Gate`-style human approval. It is the single
biggest strengthening available here, and it changes the shape of every write ability, so it should
be decided rather than discovered.

## Constraints — traps, not preferences

- **The access mode must not be readable through `get_option()`.** A policy switch that a filter can
  flip is not a policy switch.
- **Do not derive authorization from `annotations.readonly`.** Any provider can set it.
- **Read-only must be enforced by the server, not by our parsing.** Regex guards are a first filter
  and an error message, never the boundary.
- **A denylist always beats an allowlist entry.** Credentials tables stay out regardless of
  configuration.
- **The change log must be redacted**, or it becomes the exact thing the redactor exists to prevent.

## Verification

- With access mode `read-only`, every write ability refuses, and `SELECT … INTO OUTFILE` fails at
  the transaction boundary rather than at a regex.
- A query with `LIMIT` inside a subquery still returns at most the configured row count.
- A query exceeding the time cap is terminated and reported as such, not as an empty result.
- A denylisted table is refused even when explicitly allowlisted.
- Values matching a redaction rule are masked in query results and in the change log.
- Flipping the access mode via a `pre_option_*` filter does not change what is enforced.
