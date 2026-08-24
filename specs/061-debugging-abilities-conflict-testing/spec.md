# Feature Specification: Debugging Abilities — Conflict Testing (first sub-group)

**Feature Branch**: `061-debugging-abilities-conflict-testing`
**Created**: 2026-08-02
**Status**: Draft
**Input**: User description: "Debugging Abilities — Conflict Testing (first sub-group). Add a new Debugging ability category to acrossai-abilities-manager and land its first sub-group — Conflict Testing — as seven WP Abilities API abilities that mirror the operations today driven by the Local by Flywheel addon at local-wordpress-supercharged. The abilities let any WordPress-side consumer (the Local addon, another WP plugin, or an MCP/AI client) toggle individual plugins active/inactive without touching the wp_options.active_plugins row — via a must-use plugin that hooks option_active_plugins plus a JSON overrides file at wp-content/conflict-test-overrides.json that stores per-plugin diffs from real DB status. Ships seven abilities (list-plugins, get-overrides, set-override, bulk-set-overrides, clear-overrides, deploy-mu-plugin, remove-mu-plugin), one category, one mu-plugin source asset (verbatim from the Local addon), and two shared helpers. Cascade on set-override follows WP 6.5's RequiresPlugins header. Coexistence with the Local addon is a requirement — the JSON file shape is unchanged."

## Clarifications

### Session 2026-08-02

- Q: Is the Local by Flywheel addon expected to still drive the same overrides file on the same site, or is this feature the only writer? → A: Only this plugin (via the Abilities API) writes the overrides file. Local's IPC path is not expected to touch the same file at the same time. User Story 5 (coexistence) is dropped, FR-015 (byte-compat with Local as a hard requirement) is dropped, SC-003 (Local ↔ abilities round-trip) is dropped.
- Q: When a bulk-override call names some plugins that don't exist on the site, what should happen to the rest? → A: Best-effort with a report. Apply overrides for the plugins that do exist, skip the ones that don't, and return a structured summary listing which plugins were applied and which were skipped (with a reason for each skip). The single-plugin path still refuses unknown plugins; bulk diverges here because it is explicitly a multi-item operation whose caller wants partial progress on typos or races.
- Q: What happens to override entries for a plugin that later gets uninstalled from the site? → A: Auto-prune on read. The next read after a plugin is uninstalled MUST silently drop the entries whose plugin no longer resolves, and if the resulting map differs from the on-disk document the read MUST also rewrite the document (and delete it entirely if that leaves the map empty, per FR-012). Callers only ever see live entries.
- Q: Should set-override(active=true) refuse an override for a plugin that would fatal on load, mirroring WordPress core's `plugin_sandbox_scrape` pattern? → A: Yes — sandbox-scrape before writing. On any write path that flips a plugin to *active* (single-plugin or bulk), the operation MUST `include_once` the plugin file (with `WP_SANDBOX_SCRAPING` set) BEFORE the override is written. If PHP survives the include, the override is recorded; if PHP dies during the include, the write is never reached and the site's runtime behaviour is unchanged. Same trick core uses on the Plugins → Activate flow: the fatal happens first, the DB write happens second, so a broken plugin can never leave the site in a state where every subsequent page load fatals.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Reproduce a plugin conflict by temporarily deactivating a suspect plugin (Priority: P1)

A site administrator is investigating a symptom on their WordPress site — a checkout page throws a fatal, a block editor menu disappears, a REST call returns 500. They suspect a specific plugin. They want to observe the site with that plugin turned OFF for the duration of a browser session or a support call, then turn it back ON — with a **guarantee** that when they walk away, deleting one file returns the site to its exact prior state. Modifying `wp_options.active_plugins` directly (the WordPress default deactivation path) is unacceptable because it is a destructive, permanent write to the site's canonical plugin list that a colleague, a WP-Cron job, or an automated backup could observe mid-test.

This user story is the entire product. Every other story exists to make this one work under more scenarios.

**Why this priority**: Without this journey there is no product. The whole point of conflict testing is to be able to answer *"is plugin X causing symptom Y?"* in seconds, non-destructively, and reversibly. Every other user story either turns the mechanism on/off (P2), broadens it (P3, P4), or protects existing tooling (P5).

**Independent Test**: An administrator can (a) list all installed plugins and see one they suspect, (b) issue a single command against that plugin's file identifier to mark it *effectively inactive*, (c) reload the site frontend and confirm the plugin's behaviour is gone, (d) inspect the WordPress database directly and confirm the `active_plugins` option row is byte-identical to what it was before step (b), (e) issue a single command to clear the override and confirm the plugin is running again on the next page load.

**Acceptance Scenarios**:

1. **Given** Hello Dolly is active on a site and the administrator is investigating a header-render symptom, **When** the administrator sets an override marking `hello-dolly/hello.php` as inactive, **Then** the next admin-page render no longer includes Hello Dolly's admin-header quote, but `SELECT option_value FROM wp_options WHERE option_name='active_plugins'` still contains `hello-dolly/hello.php`.
2. **Given** an override is in place marking Hello Dolly as inactive, **When** the administrator clears all overrides, **Then** the next admin-page render includes Hello Dolly's quote again and no overrides file remains on disk.
3. **Given** a plugin's real active-state matches the requested override value (e.g. the administrator asks to mark an already-active plugin as active), **When** the override is applied, **Then** no entry is written for that plugin and the resulting overrides map does not carry a redundant record.
4. **Given** the administrator wants to see current state before acting, **When** they read the current overrides and mechanism state, **Then** they receive the full override map and a status indicator for the underlying mechanism (deployed, missing, or out-of-date relative to the current bundled source).
5. **Given** a plugin whose main file triggers a PHP fatal on include, **When** the administrator issues a set-override marking that plugin as active, **Then** the operation refuses the override with a "plugin-fatal-on-load" error, no override entry is recorded on disk, and the next page request renders the site identically to the pre-call state.

---

### User Story 2 — Turn conflict testing on and off site-wide (Priority: P1)

Conflict testing depends on a small must-use plugin being present in the site's mu-plugin directory. Without it, overrides are ignored and every plugin runs according to the DB row alone. A site administrator must be able to (a) install that mechanism with a single command when they need to start doing conflict tests, and (b) remove it with a single command when they want the site to behave exactly like a stock WordPress install again — with the option to also wipe any leftover overrides in the same call.

**Why this priority**: This is a strict prerequisite for User Story 1. On a fresh site the mechanism does not exist, so calling *set-override* would silently accomplish nothing until the mechanism is deployed. It is P1 alongside Story 1 (not P2) because both must work for the product to deliver any value, and a caller needs to know how to bring the site into a working state.

**Independent Test**: On a fresh site (no mu-plugin present), an administrator deploys the mechanism, confirms the mechanism file appears on disk, then removes it and confirms the mechanism file is gone. Deploy is safely re-runnable — a second deploy against an already-deployed and current mechanism produces no on-disk change.

**Acceptance Scenarios**:

1. **Given** a site with no conflict-testing mechanism deployed, **When** the administrator issues the deploy command, **Then** the mechanism file exists in the mu-plugin directory afterwards and its contents match the bundled source byte-for-byte.
2. **Given** a site with the mechanism already deployed and current, **When** the administrator issues the deploy command again, **Then** the operation succeeds and reports "already up-to-date"; the file is not rewritten needlessly.
3. **Given** a site with the mechanism deployed and an overrides file present, **When** the administrator issues the remove command with the *also-clear-overrides* flag, **Then** both the mechanism file and the overrides file are gone afterwards.
4. **Given** a site where WordPress file modifications are disabled (`DISALLOW_FILE_MODS` is `true`), **When** the administrator issues either the deploy or the remove command, **Then** the command is refused with a clear error and the on-disk state does not change.

---

### User Story 3 — Test multiple plugins together in one operation (Priority: P2)

A conflict investigation sometimes needs to isolate a group of plugins at once — turn a suspected set of five off, verify the symptom, turn them back on, then narrow down. Issuing five separate commands with per-command latency is tedious and race-prone (a page load between commands could observe an intermediate state). The administrator needs a way to say "these five plugins, all off" in a single atomic call.

**Why this priority**: Story 1 already covers the single-plugin path. Bulk narrows the loop but is not required to reproduce a conflict. Callers can loop over Story 1 as a fallback.

**Independent Test**: An administrator lists installed plugins, picks five, issues one bulk-override command with those five plugin files and `active: false`, then reads the overrides map and confirms all five entries are present and set to `false`.

**Acceptance Scenarios**:

1. **Given** five installed and active plugins, **When** the administrator issues one bulk override command listing all five with `active: false`, **Then** all five have override entries recorded, no other plugin is touched, and the response reports all five as *applied* with no *skipped* entries.
2. **Given** a bulk override command targeting a plugin whose real state already matches the requested state, **When** the operation runs, **Then** that plugin's entry is dropped from the resulting map for the same reason it would be dropped by the single-plugin path, and the response records that plugin under *no-op* rather than *applied*.
3. **Given** a bulk override command listing four installed plugins and one plugin name that is not installed on the site, **When** the operation runs, **Then** overrides are recorded for the four installed plugins and the response records the fifth under *skipped* with reason `plugin-not-installed`; the operation succeeds overall.

---

### User Story 4 — Automatically handle plugin dependency chains during a conflict test (Priority: P2)

WordPress 6.5+ lets a plugin declare `Requires Plugins: some-other-plugin/some-other-plugin.php` in its header, meaning WordPress refuses to run the plugin unless its declared dependency is also active. If an administrator turns OFF a dependency (say, `woocommerce/woocommerce.php`) they generally also want its dependents (say, `woocommerce-subscriptions/woocommerce-subscriptions.php`) to be turned OFF for the same conflict test — otherwise those dependents will start throwing "requires WooCommerce" notices and mask the symptom they were trying to reproduce. The reverse holds for activation: turning ON a dependent requires its dependency chain to be ON too.

When the administrator sets a single-plugin override with the *cascade* flag on (the default), the system walks the dependency graph in the correct direction and writes override entries for the transitive closure — so one command produces a coherent conflict-test state.

**Why this priority**: This story sharpens Story 1's single-plugin path — it's the difference between "override applied" and "override applied and site actually runs cleanly." But callers who understand their site's dependency graph can achieve the same result by looping over Story 1 with `cascade` off, so this is a valuable convenience rather than a hard prerequisite.

**Independent Test**: Install two plugins where plugin B declares `Requires Plugins: plugin-a`. Set an override deactivating plugin A with cascade on. Read the overrides map and confirm both A and B carry deactivation entries.

**Acceptance Scenarios**:

1. **Given** plugin B declares plugin A as a required plugin and both are currently active, **When** the administrator overrides A to inactive with cascade on, **Then** B receives an inactive override entry as well.
2. **Given** the same two plugins with A currently inactive and B currently inactive, **When** the administrator overrides B to active with cascade on, **Then** A receives an active override entry as well.
3. **Given** a chain of three plugins (C requires B, B requires A) all active, **When** A is overridden inactive with cascade on, **Then** B and C both receive inactive override entries.
4. **Given** the same setup as scenario 1, **When** the administrator overrides A to inactive with cascade **off**, **Then** only A receives an override entry and B is left alone.

---

### Edge Cases

- The overrides file exists but is malformed JSON → read operations return an empty map and surface the parse error so the caller can decide to fall back or repair; write operations proceed and overwrite the malformed content with a fresh, well-formed document.
- The mu-plugin file exists but does not byte-match the bundled source (older version installed by a prior release, hand-edited, or another consumer wrote its own copy) → the mechanism status is reported as *stale*; deploy safely re-writes to the current source, remove still removes.
- The administrator issues a set-override for a plugin file that isn't installed → the operation refuses the entry with a "unknown plugin" error rather than writing a dangling record.
- The resulting overrides map after a write is empty (every override cancelled out because it matched the DB state) → the overrides file is deleted from disk rather than persisted as an empty document, so a site with no active overrides has zero on-disk footprint.
- Every operation is refused for callers who do not hold the WordPress `manage_options` capability.
- Deploy and remove operations are refused when the site's WordPress installation has file modifications globally disabled — no partial-succeed, no silent fallback.
- The remove-mechanism command runs on a site where the file was already absent → treated as a success (idempotent removal); the also-clear-overrides flag still applies to the JSON file if that flag is set and the file exists.
- The overrides map contains entries for one or more plugins that have since been uninstalled → the next read auto-prunes those entries (per FR-021), rewrites the on-disk document with the smaller version, and returns only the live entries. If pruning empties the map, the on-disk document is deleted (per FR-012).
- A caller issues `set-override` (single or bulk) marking a plugin as *active*, and loading that plugin's main file triggers a PHP fatal → the write is never reached (per FR-022); no override is recorded, no in-progress state is left on disk, and subsequent page requests render the site exactly as they did before the call. Single-plugin path returns a 500 with a "plugin-fatal-on-load" error; bulk path records the offending plugin under *skipped* with the same reason and continues processing the rest.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST provide a way to enumerate every installed plugin on the site with, at minimum, its file identifier, human-readable name, version, current effective active state, and any plugins it declares as required.
- **FR-002**: System MUST provide a way to read the current per-plugin override map plus a status indicator describing whether the underlying override mechanism is deployed, missing, or stale relative to the bundled reference.
- **FR-003**: System MUST provide a way to set one plugin's effective active state to either active or inactive without modifying the WordPress `active_plugins` option row.
- **FR-004**: System MUST provide a way to set many plugins' effective active states in a single operation without modifying the `active_plugins` option row.
- **FR-005**: System MUST provide a way to remove every override in one operation, restoring every plugin's effective active state to its DB-recorded state.
- **FR-006**: System MUST provide a way to install the override mechanism idempotently — a second call against an already-current mechanism MUST NOT rewrite the underlying file.
- **FR-007**: System MUST provide a way to remove the override mechanism, with an optional flag in the same call to also delete any leftover override map.
- **FR-008**: Every operation MUST be gated on the caller holding the WordPress `manage_options` capability.
- **FR-009**: Setting a single override MUST default to cascading through the WordPress `Requires Plugins` header — deactivating a plugin also produces override entries for its transitive dependents; activating a plugin also produces override entries for its transitive requirements — and callers MUST be able to opt out per call.
- **FR-010**: Bulk override MUST NOT cascade — for each plugin named by the caller that resolves to an installed plugin, the exact requested override state applies and no unnamed plugin is touched.
- **FR-010a**: Bulk override MUST be best-effort with a per-plugin report. The response MUST classify every named plugin under one of *applied* (override written), *no-op* (requested state already matched the DB-recorded state, so no entry recorded per FR-011), or *skipped* (with a machine-readable reason such as `plugin-not-installed`). Skipped entries MUST NOT abort the overall operation; the remaining plugins in the same call MUST be applied.
- **FR-011**: Setting an override whose requested value matches the plugin's real DB-recorded active state MUST NOT create an override entry, so no redundant records accumulate.
- **FR-012**: When the resulting override map after any write operation is empty, the on-disk override document MUST be deleted rather than persisted as an empty structure.
- **FR-013**: Deploy and remove operations against the override mechanism MUST refuse to run when WordPress file modifications are globally disabled, and MUST leave the on-disk state unchanged when refused.
- **FR-014**: Every filesystem path written or read by these operations MUST be a fixed system-owned path — no caller input may name a filesystem location.
- **FR-015**: The on-disk override document MUST use a documented, stable structure so that a subsequent release can add optional fields without breaking older readers, and older writers can still produce documents readable by newer code.
- **FR-016**: All new abilities MUST be exposed under a single new "Debugging" category so that future debugging abilities (log tail, transient inspection, Query Monitor toggling, etc.) can join the same category without a category proliferation.
- **FR-017**: All seven conflict-testing abilities MUST share a single sub-group inside the Debugging category so the UI groups them together and future debugging sub-groups can be added alongside.
- **FR-018**: In the single-plugin path, setting an override for a plugin file that is not installed on the site MUST refuse the whole call with a clear "unknown plugin" error. In the bulk path the same condition MUST report the plugin under *skipped* (per FR-010a) without aborting the operation.
- **FR-019**: Every read operation MUST tolerate a malformed override document by returning an empty map plus a parse-error indicator so callers can decide whether to repair or fall back; subsequent writes MUST overwrite the malformed content with a well-formed document.
- **FR-020**: The mechanism status MUST report *deployed*, *missing*, or *stale* — where *stale* means an override mechanism file exists at the expected location but its bytes do not match the current bundled reference.
- **FR-021**: Every read operation MUST auto-prune override entries whose plugin no longer resolves to an installed plugin on the site. If pruning changes the map, the on-disk document MUST be rewritten with the smaller version — or deleted entirely if the pruned map is empty (per FR-012). Callers observe only live entries.
- **FR-022**: Any write operation (single-plugin or bulk) that flips a plugin's effective state to *active* MUST first probe the plugin file by including it once, exactly as WordPress core does before writing to `active_plugins`. If the probe succeeds without a fatal, the override is recorded; if the probe triggers a PHP fatal, the operation MUST NOT reach the write step and the site's runtime behaviour MUST remain identical to the pre-call state. The probe MUST expose a well-known constant so plugins that want to bail out of their own bootstrap during a probe can do so. In the bulk path, a fatal on one plugin MUST record it under *skipped* with a reason such as `plugin-fatal-on-load` (or, if PHP genuinely dies mid-request, the whole call returns 500 with no partial write reaching disk) and MUST NOT abort the remaining plugins' processing when the failure is caught cooperatively.

### Key Entities *(include if feature involves data)*

- **Plugin** — a WordPress plugin installed on the site. Identified by its file path relative to the plugins directory (e.g. `hello-dolly/hello.php`). Carries a human-readable name, a version string, its DB-recorded active state, and zero or more declared required-plugin identifiers.
- **Override Entry** — a per-plugin decision that this plugin's effective active state should be different from its DB-recorded state. Keyed by the plugin's file identifier. Value is a boolean — `true` means "run this plugin regardless of what the DB says", `false` means "don't run this plugin regardless of what the DB says".
- **Override Map** — the full collection of Override Entries for the current site. Persisted as a single on-disk document at a fixed system-owned path. Absent-document and empty-map are semantically equivalent.
- **Mechanism** — the underlying must-use plugin that reads the Override Map at runtime and rewrites WordPress's returned active-plugins list accordingly. Has a deployed / missing / stale status relative to a bundled reference.
- **Debugging category** — a new top-level ability grouping intended to grow beyond conflict testing into other debugging sub-groups (log tail, transient inspection, Query Monitor toggle, etc.). Displayed as its own tab in the plugin's Ability Library UI.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A site administrator can toggle a suspected plugin OFF, verify the symptom is gone, then toggle it back ON — completing the loop in under 30 seconds elapsed and issuing at most three commands (list, set, clear).
- **SC-002**: Across all seven operations, the WordPress `active_plugins` option row is written zero times. Verified by comparing the row's stored value byte-for-byte before and after every scenario in the Acceptance Scenarios.
- **SC-003**: A site with no active overrides has zero footprint on disk: no override document exists and every plugin's effective active state equals its DB-recorded state.
- **SC-004**: On a plugin chain of any depth, toggling the root with cascade produces exactly one override entry per plugin in the chain (root plus every transitive dependent for deactivate, root plus every transitive requirement for activate). No entry duplicated, no unrelated plugin touched.
- **SC-005**: On a site with WordPress file modifications globally disabled, deploy and remove operations refuse with a clear message in 100% of attempts. No partial-success, no silent fallback.
- **SC-006**: Redeploying an already-current mechanism performs no on-disk write. Verified by comparing the mechanism file's modification timestamp before and after a no-op deploy — the timestamp is unchanged.
- **SC-007**: After the initial commit lands, adding another debugging sub-group in a future release does not require creating a new ability category — the existing "Debugging" category is reused, and the new sub-group appears alongside "Conflict Testing" in the UI with zero changes to category registration code.
- **SC-008**: In 100% of attempts to override a fatal-error-triggering plugin to *active*, the site's next page render is byte-identical to the pre-call state — no override entry lands on disk. Verified by staging a deliberately-broken plugin (e.g. syntax error in its main file), issuing the set-override call, and comparing the mu-plugin-driven active-plugins list before and after.

## Assumptions

- The seven abilities land in a **single** initial release. No PHPUnit tests ship in this commit — every existing sibling ability domain (`FileManager`, `Cache`, `Database`, …) has no PHPUnit coverage in the plugin, so matching that convention is the baseline; tests can arrive incrementally later.
- No migration or backfill runs — a fresh install with no prior overrides is the assumed starting state, and any pre-existing overrides file (e.g. left behind by a prior Local addon session on the same site) is treated as valid input and drives behaviour on first read.
- No custom REST-endpoint code is written — the underlying WP Abilities API auto-exposes each registered ability under a standard REST path, so callers reach every operation through one uniform route.
- The mu-plugin source shipped with these abilities is a **verbatim** copy of Local's existing must-use plugin — no line-level changes. Using the same reference implementation avoids reimplementing a well-tested `option_active_plugins` filter, keeps the two implementations in behavioural sync, and lets prior Local-written overrides files on the same site be picked up on first read.
- The `Requires Plugins` header used for cascade is WordPress 6.5+ core functionality — the site's WordPress version is assumed to already meet the plugin's declared minimum (`Requires at least: 6.9`). No fallback path for pre-6.5 sites.
- Only the seven conflict-testing abilities land in this release. Other future debugging sub-groups (log tail, Query Monitor toggle, transient inspection, etc.) are deliberately out of scope — the category is designed to grow, but this release only lands the first sub-group.
- Every caller is an authenticated administrator with `manage_options`. No lower-privilege caller — support engineers, plugin developers on a client's staging, or MCP clients acting on behalf of a signed-in admin — is supported without that capability.
- Callers are already discovering abilities via the existing Abilities API mechanism (REST index, MCP `discover-abilities`, or the plugin's admin UI). No new discovery surface is added.
