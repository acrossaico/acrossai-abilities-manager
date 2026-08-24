# Phase 0 Research — Debugging Abilities — Conflict Testing

All open questions from `spec.md` were resolved during `/speckit-clarify`. This document records the decision + rationale + alternatives-considered for each significant technical choice the plan rests on.

## R1. Fatal-safety on `active=true` writes — mirror `plugin_sandbox_scrape`

**Decision**: Every write path that flips a plugin's effective state to *active* — both `Set_Override` (single) and `Bulk_Set_Overrides` (per named plugin, when `active=true`) — MUST perform an `include_once` on the plugin's main file before the override is recorded, with `WP_SANDBOX_SCRAPING` set. The include is intentionally uncaught. If PHP survives, the write proceeds. If PHP dies, the write is never reached, so nothing lands on disk.

**Rationale**: WP core's `activate_plugin()` uses this exact ordering (`wp-admin/includes/plugin.php` line 641). It cannot catch parse errors or arbitrary fatals — no PHP construct can. It sidesteps by executing the risky include **before** the state-mutating `update_option( 'active_plugins', … )`. Our mu-plugin filters `option_active_plugins` on **every** request; if we recorded an `active=true` override for a plugin whose main file fatals, every subsequent request would fatal. Mirroring core's ordering means the override is only recorded when core would have activated the plugin under the same conditions. The `WP_SANDBOX_SCRAPING` constant (defined by core, checked by cooperating plugins that bail out of their own bootstrap during a probe) is a well-known signal that we honour verbatim.

**Alternatives considered**:

- **Try/catch the include** — cannot catch `E_ERROR` / parse errors reliably; `catch (\Throwable)` misses `E_COMPILE_ERROR` and PHP 7.4+ still terminates on some errors. Even if it worked, catching would hide the real state (an actively-erroring plugin) instead of leaving it out of the override map where the site keeps rendering.
- **Fork a subrequest via `wp_remote_get()` to a probe endpoint** — expensive (full WP boot per subrequest), adds an HTTP dependency to a write, and the subrequest itself would need the sandbox-scrape logic. Doubles complexity for no gain.
- **Skip the probe** (spec Q4 option B) — simpler code, but a broken plugin overridden to active takes down every request until the JSON file is manually removed. Unacceptable footgun.

## R2. Atomic write of the overrides file — temp file + rename

**Decision**: `Overrides_Store::write()` writes the new JSON to a sibling temp file (same directory, `.tmp.<pid>` suffix), calls `rename()` into the target path, and lets POSIX guarantee the rename is atomic on the same filesystem. Readers open the target path and always see either the pre-write document or the fully-written new one — never a torn intermediate.

**Rationale**: Even though Q1 established a sole-writer world (no Local IPC path), two REST calls can still race — an MCP client and a `curl` script at the same instant, or a browser and a scheduled cron. Standard atomic-write via rename is cheap (~one extra `syscall`), portable across all filesystems WordPress supports, and prevents FR-019's "malformed JSON" edge case from firing on race conditions. FR-019's "malformed → empty map" remains as a belt-and-suspenders safety net for genuine corruption (partial writes from OS crashes, filesystem full mid-write, etc.).

**Alternatives considered**:

- **Direct overwrite via `file_put_contents()`** — simplest, but a concurrent reader could observe the mid-write state as invalid JSON. FR-019 would trip and callers would see a spurious empty map. Race-window is small in practice but not zero.
- **`flock()`-guarded write** — requires every reader to also acquire the lock, and readers are cheap (no lock guarantees ordering across processes on all filesystems). Adds coordination overhead without solving the concurrent-reader case.
- **WP transient lock** — depends on the cache backend (memcached, Redis, none) and adds a network round-trip per write. Overkill for a per-site admin operation.

## R3. Overrides JSON structure — stable shape, forward-compatible reader

**Decision**: The on-disk document is a single JSON object with one top-level key: `overrides` — an object keyed by plugin file identifier (e.g. `hello-dolly/hello.php`) with boolean values. Unknown top-level keys or unknown fields inside an entry MUST be preserved on read/write round-trip so that a subsequent release can add optional fields without breaking older writers. Missing document, empty document, and document with no override entries are all semantically equivalent to "no overrides."

```json
{
  "overrides": {
    "hello-dolly/hello.php": false,
    "akismet/akismet.php": true
  }
}
```

**Rationale**: FR-015 (post-clarify) requires "a documented, stable structure so that a subsequent release can add optional fields without breaking older readers, and older writers can still produce documents readable by newer code." The single-key top-level object leaves room for `metadata`, `schema_version`, and similar without renaming the payload. Booleans are the simplest representation of the effective state; the mu-plugin filters `option_active_plugins` based on their truthiness.

**Alternatives considered**:

- **Array of `{ file, active }` objects** — more verbose, no lookup speedup (all callers key by plugin file anyway), and harder to enforce uniqueness (two entries for the same file become possible).
- **Nested structure (`{ overrides: { active: [], inactive: [] } }`)** — two writes per state flip, awkward for the sparse case.
- **Schema versioning up front** — YAGNI; add it in a later release if the shape ever needs to break compatibility.

## R4. Auto-prune orphans on read

**Decision**: `Overrides_Store::read()` filters the loaded map against `get_plugins()` at read time. Entries whose plugin identifier is not present in `get_plugins()` are silently dropped from the returned map. If the pruned map differs from the loaded document, the store rewrites the on-disk document with the smaller version (or deletes it entirely if the pruned map is empty, per FR-012). The read then returns the pruned map.

**Rationale**: Q3 established this behaviour. Uninstalling a plugin while an override exists for it leaves an orphan that would otherwise accumulate. Auto-prune on read keeps the on-disk footprint small and matches the "if you delete the plugin, its override goes away" mental model. The write-back-on-shrink means the next read on any caller (Local addon UI, a REST client, another plugin) sees the pruned state without needing to run a separate cleanup pass.

**Alternatives considered**:

- **Prune only on explicit clear** — the file grows unboundedly with each uninstalled plugin's override still recorded. Callers who never issue clear-overrides see garbage entries forever.
- **Return orphans with an "orphan" flag** — passes the cleanup responsibility to callers and clutters `get-overrides` output.
- **Prune on schedule (cron)** — introduces a cron job for a cheap-to-do-inline operation.

## R5. Bulk semantics — best-effort with per-plugin classification

**Decision**: `Bulk_Set_Overrides::execute()` iterates every named plugin and classifies the outcome per entry into `applied`, `no_op`, or `skipped`. The response is `{ applied: [...], no_op: [...], skipped: [ { plugin_file, reason }, ... ] }`. Skipped entries never abort the call. Reasons currently include `plugin-not-installed` (FR-018 bulk-path) and `plugin-fatal-on-load` (FR-022 bulk-path).

**Rationale**: Q2 established this. Bulk callers frequently have a stale plugin list (typos, plugin recently uninstalled, plugin freshly renamed). Aborting the whole call because one name is wrong forces callers to pre-validate against `list-plugins` — but by the time the bulk call runs, another plugin may have been uninstalled anyway. Best-effort with a structured report lets callers see exactly what happened and decide whether to retry.

**Alternatives considered**:

- **All-or-nothing** (Q2 option A) — cleaner semantics but forces caller-side pre-validation and re-checks. Doesn't help with the racy "plugin was there a second ago" case.
- **Silent best-effort** (Q2 option C) — hides typos. Callers can't tell whether a plugin was skipped because it wasn't installed or because their input was mis-shaped.

## R6. Mu-plugin source — verbatim from Local addon reference implementation

**Decision**: The bundled `assets/wp-conflict-tester.php` is a byte-identical copy of Local by Flywheel's `src/features/conflict-test/wp-conflict-tester.php` (33 lines, provided by the user before `/speckit-implement`). The `Deploy_Mu_Plugin` ability reads that asset from the plugin's own installation directory and writes it to `WPMU_PLUGIN_DIR . '/wp-conflict-tester.php'`, first hash-comparing to skip a no-op deploy.

**Rationale**: The mu-plugin is 33 lines of well-tested `option_active_plugins` filter logic. Reimplementing it (a) risks behavioural drift from a known-good reference, (b) creates two implementations to maintain, and (c) means any prior Local-written overrides file on the same site would be observed under a subtly different rewrite path. Copying byte-for-byte avoids all three. The hash-compare on deploy means redeploys against a current mechanism are zero-write (SC-007).

**Alternatives considered**:

- **Rewrite from scratch to plugin-house style** — pointless work, no functional gain, opens a bug window where the two versions disagree.
- **Fetch the mu-plugin over HTTPS at deploy time** — introduces a network dependency, makes offline sites break, opens a supply-chain concern.
- **Ship a Composer package containing the mu-plugin** — overkill for a 33-line file.

## R7. Category and sub-group taxonomy — `debugging` tab + `conflict-testing` sub-group

**Decision**: A new ability category `acrossai-abilities-manager-debugging` with label "Acrossai Abilities Manager — Debugging" and `tab_group = 'debugging'`. All seven abilities share `sub_group = 'conflict-testing'` and `sub_group_label = 'Conflict Testing'`. Future debugging sub-groups (log tail, transient inspection, Query Monitor toggle) join the same category and tab, each with their own sub-group.

**Rationale**: FR-016 explicitly demands a category that grows. FR-017 groups the seven conflict-testing abilities together in the Library UI. Category naming follows the established `acrossai-abilities-manager-<domain>` slug pattern (see `acrossai-abilities-manager-file-manager`, `acrossai-abilities-manager-cache`, etc.). SC-007 makes the growth affordance measurable: adding another sub-group in a future release does not touch category registration code.

**Alternatives considered**:

- **New "Conflict Testing" category** (narrower slug) — would force future log-tail / Query Monitor / transient-inspection abilities to either sit in an unrelated category or spawn "Log Tail", "Query Monitor", "Transient Inspection" categories one per sub-group. Category proliferation is exactly what FR-016 forbids.
- **No sub-groups, just abilities in the flat category** — the seven abilities blend into future debugging abilities in the Library UI with no visual separation.

## R8. Permission model — `manage_options` only, no fine-grained caps

**Decision**: Every ability's `permission_callback` is `static fn(): bool => current_user_can( 'manage_options' )`. No feature-specific capability is introduced.

**Rationale**: Matches every existing sibling ability domain in the plugin (`FileManager`, `Cache`, `Database`, `Options`, `Cron`, `SiteHealth`, `Content`, etc. — all `manage_options`). Introducing a `conflict_testing` cap would require a mapping-to-role mechanism, add friction to admins who expect one capability, and give no security benefit — anyone with `manage_options` can already deactivate any plugin from the standard WordPress admin UI.

**Alternatives considered**:

- **New `activate_plugins` capability check** (WP core's actual cap for plugin activation) — closer alignment to core but excludes admins who have `manage_options` without `activate_plugins`. In practice `manage_options` implies `activate_plugins` on single-site (both come with the Administrator role); on multisite `manage_options` is per-site while `activate_plugins` is not — matching the plugin's per-site JSON file design.
- **Filterable capability** (like Feature 060's `acrossai_integration_toggle_capability`) — plausible add for a later release; not necessary v1.

## R9. Multisite behaviour — per-site JSON file, no network scope

**Decision**: The JSON file lives at `WP_CONTENT_DIR . '/conflict-test-overrides.json'`. On multisite this is shared across every site in the network (since `WP_CONTENT_DIR` is network-wide), but since every plugin identifier in the file is a `plugins/<folder>/<file>.php` path, the mu-plugin filters `option_active_plugins` uniformly for every site.

**Rationale**: The mu-plugin is a single file at `WPMU_PLUGIN_DIR/wp-conflict-tester.php` — mu-plugins are network-wide by design. A per-site overrides file would require the mu-plugin to be site-context-aware (calling `get_current_blog_id()` on every filter fire), which the reference Local addon implementation does not do. Keeping the file network-wide matches the reference behaviour.

**Trade-off acknowledged**: on multisite, a conflict test toggled from one site's admin affects all sites. This is consistent with WP core mu-plugin behaviour but should be surfaced in `quickstart.md`.

**Alternatives considered**:

- **Per-site JSON file** (`WP_CONTENT_DIR . '/conflict-test-overrides-' . get_current_blog_id() . '.json'`) — requires modifying the reference mu-plugin (no longer verbatim, per R6) and introduces a new failure mode when the mu-plugin can't determine the blog ID (very early boot).
- **Network-scope-only** — no site-level toggling. Fewer sharp edges but forces network admins to be the only ones running conflict tests.
