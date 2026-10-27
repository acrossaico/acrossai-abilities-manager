# Feature 129 — Query Monitor: profiling abilities a WordPress site can't otherwise expose

> Feature number is provisional — let `/speckit.specify` assign the next free one (117–128 are already taken in code).

Query Monitor is installed and active on the dev site (v4.0.7). It is the de-facto WordPress developer tool for profiling a single request: database queries, hooks, HTTP calls, PHP errors, the template used, timing and memory — 27 "collectors" in all. It registers **no WP Abilities of its own**; it is an admin debug panel. This feature adds the abilities that let an AI assistant read what Query Monitor sees, and lives in the existing **`acrossai-debugging`** category, whose description already names "Query Monitor toggling" as a planned sub-group.

## Query Monitor is a per-request profiler, and that is the whole constraint

QM's collectors gather data about the request *as it runs*, and QM shows the result at the end of that request. An ability is called as its own isolated REST request. So running QM's collectors "for the current request" inside an ability profiles the ability's own call — which tells you nothing about any real page. Every decision below follows from this one fact.

QM emits its data through "dispatchers": HTML (the footer panel), AJAX, REST, Redirect and `wp_die`. The AJAX and REST dispatchers already attach a **subset** of QM's data as `X-QM-*` response headers — but only three collectors: `overview` (time, memory, limits), `php_errors` (counts) and `redirects`. QM is also extensible: the `qm/dispatchers` and `qm/collectors` filters let a plugin register its own dispatcher and read every collector. Those two facts are the way in.

## Nothing we already ship profiles a request

- `database/run-db-select-query` and `database/explain-db-query` run a query you name. They cannot tell you which queries a given *page* runs, which are slow, or which are duplicated.
- `cache/*` reads transients. It does not report the object-cache backend or the hit ratio.
- Diagnostics / Site Health report environment basics. They do not carry QM's PHP-extension list, the MySQL server variables or the full constant set, and they cannot profile a URL.

So the one thing worth building is the thing none of these do: **profile an arbitrary URL** and report what happened on it.

## Scope — 4 abilities, namespace `debugging/`, sub-group `query-monitor`

Not `acrossai/conflict-test-*` — that is the legacy prefix the conflict-testing suite uses. New abilities use the topic-based `debugging/` namespace.

1. **`debugging/profile-url`** — the reason for the feature. **Input:** a site URL (front-end or admin), optional user/role to run as, optional "slowest N" cap. It registers a lightweight JSON dispatcher through `qm/dispatchers`, fires one authenticated loopback request to the URL, and returns the structured profile:
   - generation time, peak memory, and the time/memory limits
   - database: query count, total query time, the slowest N queries (SQL + caller + time + rows), and the duplicated queries
   - outbound HTTP calls (`wp_remote_*`): URL, method, status, time
   - PHP errors and `_doing_it_wrong()` / deprecation calls
   - the template that rendered the page
   - enqueued-asset counts (scripts/styles) and any broken dependencies

   The `X-QM-*` headers are the zero-dependency fallback if the dispatcher path ever fails; on their own they give time, memory and error count.

2. **`debugging/get-environment`** — QM's environment snapshot, read-only and request-independent: PHP version + loaded extensions + key ini limits; the MySQL server/client version + the relevant server variables; the WordPress version + active constants; the server software.

3. **`debugging/get-object-cache-status`** — whether a persistent object cache is active, which backend / drop-in, and the hit/miss ratio. Read-only.

4. **`debugging/toggle-query-monitor`** — enable or disable QM's own output on the site (the one write in the set). Does not uninstall; flips whether QM runs and renders.

## Constraints — traps, not preferences

- **QM must be active.** Every ability degrades to a clear "Query Monitor is not active" message rather than a fatal when the plugin is missing or off. Detect by class/function; never assume.
- **The loopback request must authenticate as a real user**, or QM refuses to render (its `user_can_view()` gate) and a logged-out front-end page differs from what an admin sees. Use the SSRF-safe loopback with internal auth, run as a named capable user, and report which user it ran as.
- **The header channel only carries `overview`, `php_errors`, `redirects`.** The full query / hook / HTTP data never comes back in headers — it only reaches the HTML dispatcher. The full profile therefore depends on the custom JSON dispatcher reading `QM_Collectors` during the loopback request; the headers are a fallback, not the primary source.
- **`profile-url` makes a real HTTP request to the site itself.** Refuse off-site hosts and non-HTTP(S) schemes, use the SSRF-hardened request path (`wp_safe_remote_get`), cap redirects and timeout, and never profile a URL outside this site's host.
- **Reading QM internals touches another plugin's classes.** Guard every class and method; degrade to "profile unavailable" if QM restructures, rather than fatal. QM's data shapes are not a public contract.
- **Read-only except the toggle.** `profile-url`, `get-environment`, `get-object-cache-status` are annotated `readonly: true`; `toggle-query-monitor` is a settings write.
- **Namespace `debugging/`, sub_group `query-monitor`**, category `acrossai-debugging`. Do not copy the legacy `acrossai/` prefix.

## Verification

- With QM active, `debugging/profile-url` against the home URL returns a non-empty query list with a total time, and its slowest query matches what QM's own panel shows for that page.
- `get-environment` returns the same PHP version and extension set QM's Environment panel shows.
- With QM deactivated, every ability returns the "not active" message and no fatal.
- `profile-url` refuses an off-site URL and a non-HTTP scheme.
- The loopback runs as a named capable user; profiling the admin dashboard returns admin-only data, proving auth worked.
- Behavioural and mutation-checked: deactivate QM and the first test must flip to the "not active" path; break the dispatcher and the header fallback still returns time/memory.

## Out of scope

- Surfacing each of QM's 27 collectors as its own ability. Most are only meaningful as part of one URL's profile, which `profile-url` already carries.
- Anything already covered: raw DB queries (`database/*`), transients (`cache/*`), recent fatals / debug.log (diagnostics).
- A continuous/live profiler, or storing profiles over time. One URL, one profile, on demand.
- Changing QM's own settings beyond on/off.
- Any wp-admin UI or panel work.
