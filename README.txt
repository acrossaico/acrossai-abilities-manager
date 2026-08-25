=== AcrossAI Abilities Manager ===
Contributors: raftaar1191
Donate link: https://github.com/acrosswp/acrossai-abilities-manager
Tags: abilities, ability management, access control, site management, ai
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.30
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Manage every WordPress ability registered on your site — view, search, override, and bulk-control ability metadata from a single admin page.

== Description ==

AcrossAI Abilities Manager gives site administrators full visibility and control over every ability registered via the WordPress Abilities API (`wp_get_ability()`).

**Features:**

* **Browse all abilities** — a searchable, sortable, paginated table listing every registered ability with slug, provider, source, and current status.
* **Toggle allow/disallow** — enable or disable any ability site-wide with a single click. Changes are saved instantly without a page reload.
* **Edit ability metadata** — override `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`, `mcp_type`, and `mcp_servers` fields per ability using a tri-state system (Yes / No / Inherit from registry).
* **Reset overrides** — restore any ability back to its registry defaults with one click.
* **Bulk actions** — allow, disallow, or reset up to 50 abilities at once.
* **Ability Library** — enable or disable add-on ability groups from a dedicated Library page, with All/Specific mode controls per group.
* **Add-ons page** — browse companion plugins from the WordPress admin. WordPress.org-hosted add-ons install / activate / deactivate in place; add-ons distributed elsewhere link out to the vendor's site so you can install them via Plugins → Add New → Upload Plugin.
* **MCP server list** — view all registered MCP servers when the MCP Adapter plugin is active.
* **Debugging → Conflict Testing** — toggle any installed plugin's *effective* active state without ever writing to `wp_options.active_plugins`. Seven WP Abilities API abilities (`acrossai/conflict-test-list-plugins`, `-get-overrides`, `-set-override`, `-bulk-set-overrides`, `-clear-overrides`, `-deploy-mu-plugin`, `-remove-mu-plugin`) let a REST client, MCP AI client, or another plugin reproduce a plugin conflict for a browser session or a support call, then restore the site to its exact prior state by clearing one JSON file. Overrides cascade through WP 6.5+ `Requires Plugins:` headers by default. Every `active=true` write is guarded by a WordPress-core-style `plugin_sandbox_scrape` probe, so a broken plugin can never leave the site in a state where every subsequent page load fatals — the override is refused instead. Feature 061.

All overrides are stored in a dedicated database table. The WordPress ability registry is never modified — only the fields that differ from registry defaults are persisted.

**Security:**

* All endpoints require `manage_options` capability.
* All state-changing requests are protected by WordPress nonce verification.
* All input is sanitized; all output is escaped.

**Third-party integrations (optional):**

* **MCP Adapter plugin** — if active, the plugin displays a list of registered MCP servers inside the ability edit panel. No data is sent to any external service. The MCP Adapter plugin communicates only with your own WordPress installation.

This plugin's own code makes no external HTTP requests. One admin-only surface can contact an external service on your behalf: the AcrossAI → Add-ons page installs WordPress.org-hosted companion plugins directly through WordPress core's own plugin installer (`api.wordpress.org` + `downloads.wordpress.org`). Add-ons registered with any other source (e.g. GitHub, Freemius) are shown as external "Get add-on ↗" links that open the vendor's site in a new browser tab — the plugin does not download or install them itself. The AcrossAI → Consultations submenu renders a static call-to-action button that opens `calendly.com` in a new browser tab only after the administrator clicks it — no third-party asset is loaded inside wp-admin. Full disclosure — including what data is transmitted to each service and links to their terms + privacy policies — is in the **External Services** section below.

== Installation ==

1. Upload the `acrossai-abilities-manager` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **AcrossAI Abilities Manager** in the WordPress admin menu.

**Add-ons:**

1. Go to **AcrossAI → Add-ons** to browse available companion plugins.
2. All add-ons are free and hosted on WordPress.org; each card offers a one-click Install / Activate / Deactivate action via the standard WordPress plugin installer.

== Frequently Asked Questions ==

= Does this plugin support Multisite? =

No. This plugin has not been tested on WordPress Multisite installations.

= Does this plugin modify the WordPress ability registry? =

No. The plugin stores only overrides — fields that differ from the registry defaults. The ability registry itself (`wp_get_ability()`) is never modified.

= What happens when I reset an override? =

The override row is deleted from the database. The ability will inherit its values from the registry again.

= What is the Ability Library? =

The Library page lets you enable or disable ability groups registered by add-on plugins. Each group shows an ON/OFF master toggle and an All/Specific mode selector. In Specific mode, individual ability slots can be toggled independently.

= What is the MCP Adapter integration? =

If the MCP Adapter plugin is active on your site, AcrossAI Abilities Manager will display the list of registered MCP servers in the ability edit panel. This is entirely optional — the plugin works without the MCP Adapter.

= Does this plugin make external HTTP requests? =

The plugin's own code makes no external HTTP requests. Two admin-only surfaces trigger external connections on behalf of an authenticated administrator:

* **AcrossAI → Consultations** submenu — renders a static call-to-action button that links to `https://calendly.com/acrossai/using-ai-in-wordpress` and opens in a new browser tab. The plugin does not load any Calendly script, iframe, or asset inside wp-admin. Calendly is only contacted if the administrator explicitly clicks the button — at which point their browser navigates directly to `calendly.com`, exactly as with any external hyperlink.
* **AcrossAI → Add-ons** submenu — installs WordPress.org-hosted companion plugins in place through WordPress core's `plugins_api()` + `Plugin_Upgrader` (contacts `api.wordpress.org` + `downloads.wordpress.org`). Add-ons registered with any other source (e.g. GitHub, Freemius) render as external "Get add-on ↗" links that open the vendor's site in a new browser tab — the plugin does not download or install those add-ons itself. Users install off-directory add-ons via WP admin's standard **Plugins → Add New → Upload Plugin** flow (or via the vendor's own installer once the paid plugin is activated).

Full disclosure — including what data is transmitted, and links to each service's terms + privacy policy — is in the **External Services** section of this readme.

== Screenshots ==

1. The Abilities Manager admin page — searchable, sortable ability table.
2. The edit drawer — tri-state override controls for each ability field.
3. Bulk actions toolbar for allow/disallow/reset across multiple abilities.
4. The Ability Library page — enable/disable add-on ability groups.
5. The Add-ons page — browse free companion plugins.
6. Settings — Display (abilities-per-page) and Upload Media Abilities (allowed-MIME list + Add file types).

== External Services ==

This plugin connects to the following external services on your behalf. Each connection is triggered by a specific admin-only action and is disclosed here per the WordPress.org plugin directory guidelines.

**1. Calendly external link (`calendly.com`)**

*What it is:* Calendly is a third-party scheduling service. The AcrossAI → Consultations submenu displays a static call-to-action button that links out to a Calendly booking page for AcrossAI consultations ("Using AI in WordPress").

*When it is contacted:* Never on page render. The Consultations submenu at `/wp-admin/admin.php?page=acrossai-consultations` is a self-contained wp-admin page — it does not load any Calendly script, iframe, cookie, or asset. Calendly is only contacted if the administrator explicitly clicks the "Book a Consultation" button, at which point their browser navigates directly to `https://calendly.com/acrossai/using-ai-in-wordpress` in a new tab (`target="_blank" rel="noopener noreferrer"`). This is identical to clicking any external hyperlink from an admin page.

*What is loaded on the Consultations page:* Nothing from Calendly. The page renders self-contained HTML + CSS. The only external asset referenced by the page is Google Fonts (Space Grotesk + IBM Plex Sans via `fonts.googleapis.com`) — permitted under the "third-party CDNs beyond fonts" carve-out in the WordPress plugin guidelines.

*What data is transmitted to Calendly:* Nothing by this plugin. If the administrator clicks the CTA button, their browser navigates directly to Calendly and sends standard browser metadata (IP address, User-Agent, referrer) to Calendly as with any external link. If the administrator then chooses to book a consultation on Calendly's own site, any information they enter into Calendly's booking form (name, email address, meeting preferences, etc.) is transmitted to and processed by Calendly. This plugin does not intercept, store, or forward that data.

*Terms of service:* https://calendly.com/pages/terms
*Privacy policy:* https://calendly.com/pages/privacy

**2. WordPress.org plugin directory (`api.wordpress.org` and `downloads.wordpress.org`)**

*What it is:* The Add-ons page (`/wp-admin/admin.php?page=acrossai-addons`) uses the WordPress.org plugin directory to install free companion plugins directly from wp-admin.

*When it is contacted:* Only when an authenticated administrator (`install_plugins` capability) clicks the "Install" button on a card whose `source` is `wordpress.org`. Contact happens through WordPress core's own `plugins_api()` and `Plugin_Upgrader` — this plugin does not issue direct HTTP requests. Add-ons registered with any other source (e.g. `github`, `freemius`) are rendered as external "Get add-on ↗" links that open the vendor's site in a new browser tab; the plugin does NOT download or install those add-ons itself, so no request is made to the vendor's servers from wp-admin.

*What data is transmitted:* The WordPress core plugin API request payload (site URL, WP version, PHP version, locale) as per WordPress core's standard update check protocol.

*Terms of service:* https://wordpress.org/about/terms/
*Privacy policy:* https://wordpress.org/about/privacy/

**3. WordPress.org core version-check API (`api.wordpress.org/core/version-check/1.7/`)**

Called only when an administrator invokes the `core/rollback-wp-core` ability (registered under the Core category) and the local core-version cache has expired. Rate-bounded to at most one request per day per locale per site via a site-transient cache. This is a WordPress-core-hosted API — no data beyond the standard WordPress core version-check request payload is transmitted. Same wp.org terms + privacy policy as service #2 above.

== Privacy Policy ==

This plugin does not itself collect, store, or transmit any user data to any third party.

Several admin-only actions can cause external services to receive data — all are described in the External Services section above and are triggered only by an authenticated administrator:

* The AcrossAI → Consultations admin page displays a static call-to-action button. Merely loading the Consultations page sends no data to Calendly — no Calendly script, iframe, or asset is loaded inside wp-admin. If the administrator clicks the CTA button, their browser opens `calendly.com/acrossai/using-ai-in-wordpress` in a new tab, at which point standard browser metadata (IP, User-Agent, referrer) is sent to Calendly and Calendly's own privacy policy applies. If they then book a consultation on Calendly's site, information they enter into Calendly's form (name, email, meeting details) is transmitted to Calendly.
* Installing a WordPress.org-hosted add-on from the AcrossAI → Add-ons page contacts the WordPress.org plugin directory via WordPress core's own `plugins_api()` and `Plugin_Upgrader` (`api.wordpress.org` + `downloads.wordpress.org`). Add-ons distributed elsewhere (e.g. GitHub, Freemius) are rendered as external "Get add-on ↗" links that open the vendor's site in a new browser tab — the plugin itself does not download or install those add-ons, so no request is sent to the vendor's servers from wp-admin. If the administrator clicks the external link, their browser navigates directly to the vendor and standard browser metadata (IP, User-Agent, referrer) is sent to the vendor as with any external hyperlink.
* Invoking the `core/rollback-wp-core` ability contacts the WordPress.org core version-check API (a WordPress-core-hosted service) via the standard WordPress update API.

No data is sent to any external server without an explicit administrator action.

== Changelog ==

= Unreleased =
**Feature 092 — File Manager admin tab: per-folder read/write allowlists + configurable secret redactor.** New "File Manager" tab at `admin.php?page=acrossai-settings` gives site admins three per-folder controls over what MCP clients can do via `file-manager/*` abilities. Also introduces a hardened secret-scrubber that runs on every read response.

**Write allowlist.** The 8 write-capable file-manager abilities (`create-file`, `edit-file`, `delete-file`, `copy-file`, `move-file`, `append-file`, `create-directory`, `delete-directory`) refuse any operation whose target path resolves outside the admin's allowlist. Default on activation: `['wp-content']` (writes only inside wp-content). `copy-file` and `move-file` check both source and destination. Refusal returns `{success:false, blocked_reason:"path_not_allowed_for_write", allowed_roots:[…]}`.

**Read allowlist.** The 2 content-reading abilities (`read-file`, `read-debug-log`) can also be gated. Default on activation: `[]` — unrestricted (every path readable). Admins can flip a "Restrict reads to specific folders" toggle and pick specific folders. Refusal returns `{success:false, blocked_reason:"path_not_allowed_for_read"}`. `list-directory` and `file-info` remain ungated (metadata only, no content leak).

**Secret redactor.** Every text response from `read-file` and `read-debug-log` is scrubbed through 8 configurable pattern classes before return: **WordPress credentials** (DB_PASSWORD, DB_USER, all 8 auth keys/salts, SECRET_KEY) — value replaced but constant name preserved; **Stripe** (sk_live_ / sk_test_ / rk_live_ / rk_test_); **AWS access key IDs** (AKIA…); **OpenAI** (sk-…{48+}); **Anthropic** (sk-ant-…); **GitHub** (ghp_/gho_/ghu_/ghs_/ghr_…); **SendGrid** (SG.xxx.yyy); **JWT** (eyJ…\.eyJ…\.…). All enabled by default except JWT (false-positive risk). Admin can toggle each pattern and add custom literal strings (case-sensitive substring match) via the settings UI. Responses grow two fields: `redacted:bool` and `redaction_count:int`.

**REST endpoints.** Six new routes under `acrossai/v1`: GET/POST `/file-manager-settings/write-allowlist`, `/read-allowlist`, `/redaction`. `manage_options` + `X-WP-Nonce` required. GET responses include enumeration data (immediate ABSPATH children, `get_plugins()`, `wp_get_themes()`) so the React UI renders without a second round-trip.

**BREAKING — `file-manager/read-file`.** The previous outright refusal of `wp-config.php` and `.htaccess` (`blocked_reason:"protected_read"`) is REMOVED. Those files are now readable; sensitive content is redacted per the secret redactor above. Callers that programmatically handled `blocked_reason:"protected_read"` should switch to reading the returned content with `redacted:true`. Write-side refusals on `wp-config.php` / `.htaccess` for `create-file`, `edit-file`, `delete-file`, `copy-file`, `move-file`, `append-file` are UNCHANGED.

**Not touched.** `Ability_Definition`, `File_Mods_Guard`, `Wp_Filesystem_Init`, `read-wp-config`, `edit-wp-config`, `get-wp-config-constant`, `list-directory`, `file-info`, all 6 zip-backup abilities, and every ability outside `file-manager/*`.

= Feature 091 milestone =
**Feature 091 — WP_Filesystem migration for `file-manager/*` abilities.** Every filesystem read, write, list, delete, copy, move, and stat performed by 19 file-manager abilities now routes through WordPress's `WP_Filesystem` transport instead of raw PHP filesystem functions. On the majority of hosts (`FS_METHOD='direct'`) the behaviour is identical. On hosts where WordPress requires FTP / SSH credentials (`FS_METHOD='ftpext'` / `'ftpsockets'` / `'ssh2'`) the abilities now succeed via the same channel WordPress core's file editor uses instead of silently failing.

**Biggest wins — `wp-config.php` and `debug.log`:** `file-manager/read-wp-config`, `file-manager/edit-wp-config`, `file-manager/read-debug-log`, `file-manager/clear-debug-log` are the abilities most likely to touch files owned by the SSH user rather than the web-server user. Those calls previously failed on non-`direct` transports; they now work.

**New response value:** every migrated ability's `blocked_reason` enum widens by one value — `filesystem_unavailable` — returned when `WP_Filesystem()` initialisation fails (typically missing `FTP_HOST` / `FTP_USER` / `FTP_PASS` on a non-`direct` host).

**BREAKING — `file-manager/file-info` schema shrink:** the response no longer includes `ctime` or `atime` fields. `WP_Filesystem_Base` does not expose these consistently across transports (native `stat` returns them on `direct`, FTP/SSH transports don't). Callers programmatically reading `.ctime` or `.atime` must switch to `.mtime` or accept the loss. Every other field on the response is unchanged.

**Deferred to feature 092:** `file-manager/create-zip-backup`, `file-manager/extract-zip-backup`, and `file-manager/upload-zip-backup` remain on native PHP for now. `ZipArchive` requires direct filesystem access and has no `WP_Filesystem` equivalent, and chunked upload uses `fopen`/`fwrite`/`fclose` file-handle APIs that `WP_Filesystem` does not expose. These three abilities continue to work exactly as before on `direct` transports and continue to fail as before on FTP/SSH ones. Every file carries a `// TODO(feature-092)` marker.

**Housekeeping:** approximately 20 `phpcs:ignore WordPress.WP.AlternativeFunctions` suppressions removed from the 19 migrated files. `Ability_Definition` and `File_Mods_Guard` are unchanged (verified — sibling plugin `acrossai-buddyboss` continues to extend the former without issue).

**Feature 090 — file-manager additions.** Four new abilities extend the `file-manager/*` namespace to cover directory management and metadata: `file-manager/append-file` (append or prepend to an existing file; refuses missing files and refuses `wp-config.php` / `.htaccess`), `file-manager/create-directory` (recursive-by-default `mkdir` under ABSPATH; idempotent), `file-manager/delete-directory` (empty-only by default; opt-in `recursive:true`; requires `confirm:true`; refuses nine critical WordPress directories), and `file-manager/file-info` (read-only stat wrapper with optional POSIX owner/group names). Ability count 385 → 389.

**Feature 089 — file abilities consolidation.** Every file read / write / list / copy / move for the WordPress installation now flows through the `file-manager/*` namespace. Three new abilities added; six duplicate theme- and plugin-scoped abilities removed; a pre-existing security gap closed.

**Added — three new `file-manager/*` abilities:**

* **`file-manager/list-directory`** — recursive directory walk under ABSPATH. Bounded by `max_depth` (default 5, max 20) and `max_entries` (default 1000, max 5000); response sets `truncated:true` when a bound is reached. Symlinks are not followed. Replaces `themes/read-theme-structure` and `plugins/read-plugin-structure`.
* **`file-manager/copy-file`** — copy a file between two paths under ABSPATH. Default refuses when the destination exists; pass `overwrite:true` to replace. Refuses copies **onto** `wp-config.php` or `.htaccess` even with `overwrite:true`. Replaces the copy mode of `plugins/manage-plugin-files`.
* **`file-manager/move-file`** — rename/move a file between two paths under ABSPATH. Same overwrite semantics as `copy-file`, plus refuses moves **from** `wp-config.php` or `.htaccess`. Replaces the move mode of `plugins/manage-plugin-files`.

**Removed — six duplicate abilities (BREAKING):** any MCP client hardcoding these slugs will get an "unknown ability" error. Migrate to the `file-manager/*` replacement.

| Removed slug | Replacement |
|---|---|
| `themes/read-theme-code` | `file-manager/read-file` |
| `themes/edit-theme-file` | `file-manager/edit-file` |
| `themes/read-theme-structure` | `file-manager/list-directory` |
| `plugins/read-plugin-code` | `file-manager/read-file` |
| `plugins/read-plugin-structure` | `file-manager/list-directory` |
| `plugins/manage-plugin-files` | `file-manager/copy-file` or `file-manager/move-file` |

**Hardened — `file-manager/create-file` and `file-manager/edit-file` now refuse `wp-config.php` and `.htaccess`.** Before this release, these two abilities silently allowed overwriting those files even though `read-file` and `delete-file` refused them. This closes the last generic write path to those protected files; the specialized `file-manager/edit-wp-config` (single-constant edit with secret-key allowlist) remains the only supported way to modify `wp-config.php`.

**Kept as-is (not duplicates):** `file-manager/read-wp-config`, `file-manager/edit-wp-config`, `file-manager/get-wp-config-constant`, `file-manager/read-debug-log`, `file-manager/clear-debug-log`, `recovery/list-recent-fatal-errors`, and all theme / plugin lifecycle abilities (install / activate / update / delete-theme / lifecycle-context / checksums / etc.).

**Ability count:** 388 → 385 (-6 removed, +3 added). Full per-ability inventory refreshed at `docs/abilities-inventory.md`.

= 0.0.30 - 2026-08-19 =
**Ability namespace migration — every ability slug moves from `acrossai/*` to a topic-based prefix.** 388 abilities across 24 topic namespaces. No behavioural changes; this is a slug rename only. Delivered as four disjoint PRs merged in order: #134 (blocks), #135 (elementor), #136 (rank-math), #137 (remaining 21 domains).

**Breaking for every MCP client:** any reference to `acrossai/<slug>` must switch to `<topic>/<slug>`. There is no back-compat alias — discovery now returns the new names only.

* **`blocks/*` (40)** — every block-editor primitive: templates, template parts, patterns, style variations, global styles, theme.json, site-editor context, blocks/reusable blocks. Also includes the 7 block-tree ops that previously lived under `acrossai/*` in the Content folder (add-block, duplicate-block, get-post-blocks, insert-pattern, move-block, remove-block, update-post-block). Prefix-only rename — the second segment is preserved (e.g. `acrossai/create-block-template` → `blocks/create-block-template`).
* **`elementor/*` (62)** — every Elementor ability. Redundant `elementor-` fragment collapsed into the namespace, so `acrossai/elementor-add-widget` → `elementor/add-widget`, `acrossai/elementor-create-template` → `elementor/create-template`, etc. `Base_Audit_Ability` now builds slugs as `'elementor/' . audit_slug()` — all dynamic audit subclasses inherit the new prefix.
* **`rank-math/*` (61)** — every Rank Math ability. Redundant `rank-math-` fragment collapsed. `Base_Rank_Math_Ability` slug construction changed in one line (`'rank-math/' . slug()`) — the entire suite picks up the rename automatically. User-visible error messages that name specific slugs (e.g. `Utilities/RankMath/Maintenance_Tools`) refreshed to match.
* **Remaining 21 topic namespaces (225)** — prefix-only rename per domain:
  * `admin-menu/` (5), `cache/` (7), `comments/` (12), `content/` (29), `content-search/` (11), `core/` (6), `cron/` (16), `database/` (11), `file-manager/` (15), `fonts/` (8), `media/` (11), `menus/` (12), `options/` (7), `plugins/` (13), `recovery/` (7), `settings/` (11), `site-health/` (6), `taxonomies/` (10), `themes/` (10), `users/` (16), `widgets/` (2).
  * Examples: `acrossai/get-option` → `options/get-option`; `acrossai/list-db-tables` → `database/list-db-tables`; `acrossai/create-user` → `users/create-user`.
  * The second segment is unchanged from what shipped before — only the vendor prefix moves. No collapsing (unlike Elementor/Rank Math, where the redundant fragment was literally the namespace name).

**Why:** topic namespaces make the ability surface discoverable — a client fetching `mcp-adapter-discover-abilities` and filtering on the prefix gets exactly the domain it asked for. The old `acrossai/` prefix tagged ownership but carried no discovery information. Full per-ability inventory is now published at `docs/abilities-inventory.md`.

**Category taxonomy slugs (`acrossai-abilities-manager-*`) are unchanged.** Internal PHP class namespaces are unchanged. Tests, spec artifacts, and docstring cross-references were updated in the same commits as the slug renames — nothing left pointing at `acrossai/*`.

= 0.0.29 - 2026-08-18 =
**Feature 065 — safety envelope + payload enrichment across 9 existing abilities.** No new abilities. Plugin version bumped 0.0.28 → 0.0.29. Two changes are breaking for programmatic callers: `media/delete-media` and `file-manager/delete-file` now require an explicit `confirm: true`; `content/update-post` silently strips protected meta keys (reported back in `dropped_meta_keys`). Every guardrail-triggered refusal now returns `success: false` + a machine-readable `blocked_reason` + a human `message`, with no state mutation on the refusal path.

* **`plugins/deactivate-plugin` — protected-plugin guard.** Refuses to deactivate `acrossai-mcp-manager`, `acrossai-abilities-manager`, or `acrossai-pro` — the three plugins that host either the ability surface itself or the MCP transport the AI is using to reach the site. Match runs against the *resolved* plugin file path, so slug / partial-name / file-path variants that fuzzy-resolve to a protected plugin are all refused (`blocked_reason: "protected_plugin"`).
* **`media/delete-media` — explicit confirmation + trash-aware.** Requires `confirm: true` (refuses with `blocked_reason: "confirmation_required"` otherwise). Honours the `MEDIA_TRASH` constant — trashes when defined truthy and `force` is absent; permanent-deletes otherwise. Response now carries `deleted: "deleted" | "trashed"`.
* **`file-manager/delete-file` — confirmation + protected-write + backup + opcache invalidation.** Requires `confirm: true`. Refuses on `wp-config.php` / `.htaccess` at ABSPATH (`blocked_reason: "protected_write"`). Writes a `.bak.<timestamp>` copy next to the target before the delete and returns the backup path in `backup`. Calls `opcache_invalidate()` on the deleted path when OPcache is loaded.
* **`file-manager/read-file` — protected-read + size cap + binary detection.** Refuses on `wp-config.php` / `.htaccess` at ABSPATH (`blocked_reason: "protected_read"`) — this closes the highest-value accidental disclosure path (database password + eight auth constants). Refuses files over 5 MB without loading them into memory (`blocked_reason: "file_too_large"`; response reports observed size + cap). Non-UTF-8 payloads return `{ binary: true, size, path, message }` instead of raw bytes.
* **`media/list-media` — alt-text search.** `search` now matches against `_wp_attachment_image_alt` postmeta in addition to WP_Query's default `s` fields (title / caption / description). Results are de-duplicated by attachment ID, so an image matched by both title and alt-text appears once.
* **`media/update-media` — updated-fields report.** Response now carries an `updated` array naming each field that was actually written (subset of `title` / `caption` / `description` / `alt_text`), in the order fields were processed. Empty array when no update fields were passed.
* **`content/update-post` — writability + protected-meta + publish / author gates.** Refuses on post types that are neither `public: true` nor `show_in_rest: true` (matches WP-REST writability). Filters caller-supplied `meta` to drop `_`-prefixed keys and any key that `is_protected_meta()` reports; the `acrossai_allowed_protected_meta` filter opts specific keys back in. Dropped keys are reported in the response as `dropped_meta_keys`. Refuses `status: "publish"` (or any status entering a public state) unless the caller holds `publish_posts` for the post type. Refuses `author: <different_user_id>` unless the caller holds `edit_others_posts`.
* **`content/get-post` — hydrated payload.** Response now includes `terms` (object keyed by taxonomy, each entry `{ term_id, name, slug }`), `meta` (non-protected keys only — same allow-list filter as `update-post`), `featured_image` (`{ id, url, alt }` or `null`), `permalink`, `edit_link`, and `author: { id, name }`. Callers no longer need 4–5 follow-up hydration calls per post.
* **`content/delete-post` — suggested-redirect hint.** When the target was `publish` and `force: true` is passed, the response includes `suggested_redirect: { from: <permalink>, to: <parent-or-archive-or-root-url> }`. Omitted for drafts and for trash operations (URL may return on restore).

**Test coverage.** `Test_Feature_065_Safety_And_Payload` — 23 source-inspection tests covering all 23 FRs. Full suite green; PHPCS (WPCS strict) and PHPStan level 8 clean.

= 0.0.28 - 2026-08-17 =
**Feature 069 — Rank Math ability suite: 61 new abilities under a new "Rank Math" tab.** Gated on Rank Math SEO being active; absent entirely without it. Plugin version bumped 0.0.27 → 0.0.28.

Coverage baseline was deliberately narrow: Rank Math core ships **13** abilities of its own under `rank-math/`, and only those 13 counted as existing coverage. The third-party `mcp-abilities-rankmath` companion plugin was **not** treated as coverage — it is not ours to maintain, its writes go through raw `update_option()` blobs that bypass Rank Math's sanitizer, and it gates every ability on blanket `manage_options` regardless of the Role Manager. Slugs do not collide (`rank-math/` vs `rankmath/` vs `rank-math/`).

**Batch 1 — plumbing.** `RankMath\Category_Registrar` registers `acrossai-abilities-manager-rank-math`, guarded on `class_exists('\RankMath\Helper')`. `Base_Rank_Math_Ability` is the sole assembler of `ability()` and sole enforcer of the `execute()` guard order, which is what guarantees `tab_group => 'rank-math'` on all 61 — the Feature 078 regression class. `Rank_Math_Guard` holds every guard plus the response envelope.

**Batch 2 — typed settings (6 abilities).** `rank-math/get-settings` reads any of 20 panels with each field's type, allowed values, bounds and current value, which makes the writers' accepted keys discoverable at runtime. `-update-general-settings`, `-update-title-settings` and `-update-sitemap-settings` take a section/scope enum, replacing ~20 near-identical per-panel classes. `-update-instant-indexing-settings` and `-update-robots-txt` are separate because the first writes a different option and the second is conditional on state the caller cannot see. Titles & Meta templates — the global per-post-type and per-taxonomy layer — had no read or write anywhere before this.

**Batch 3 — Instant Indexing, modules, sitemap, routes (10 abilities).** `-submit-urls`, `-get-indexing-log`, `-clear-indexing-log`, `-reset-indexing-key`; `-list-modules` and `-set-module-state`; `-get-sitemap-status`, `-list-sitemap-urls`, `-invalidate-sitemap-cache`; `-get-llms-status` and `-refresh-llms-route`. `-set-module-state` replicates Rank Math's own `save_module()` in full including the rewrite-rule refresh and `rank_math/module_changed` action — omitting either leaves stale rewrite rules, so the sitemap and llms.txt routes 404 while the module reports itself active.

**Batch 4 — redirections, 404 logs, roles (13 abilities).** `-list-redirections` (with the `status=trashed` filter), `-find-redirection`, `-get-redirection-stats`, `-export-redirections`, `-create-redirection`, `-update-redirection`, `-change-redirection-status`, `-delete-redirections`, `-delete-trashed-redirections`; `-list-404-logs` and `-delete-404-logs`; `-get-role-capabilities` and `-reset-role-capabilities`. **`-update-redirection` fills a real gap: nothing could previously EDIT a redirection**, and emulating it by delete-then-recreate loses the rule's id, hit counter and creation date. Apache/Nginx export is a port of Rank Math's private formatters, since its own exporter reads `$_GET`, calls `check_admin_referer()`, echoes and exits.

**Batch 5 — status, maintenance, backups (8 abilities).** `-get-status` (5 panels behind an enum), `-run-maintenance-tool` (12 tools behind an enum), `-export-settings`, `-import-settings`, `-list-backups`, `-create-backup`, `-manage-backup`, `-detect-seo-plugins`, plus `-get-seo-analysis-results` for the cached audit.

**Batch 6 — analytics and post-level content (16 abilities).** `-get-analytics-summary` (6 reports), `-get-analytics-rows` (3 datasets), `-get-index-status`, `-inspect-url`; `-update-seo-meta`, `-bulk-update-meta`, `-update-seo-scores`, `-get-primary-term`, `-update-primary-term`, `-update-post-schemas`, `-delete-post-schemas`, `-get-schema-status`, `-get-rendered-head`, `-audit-content-seo`, `-get-inbound-links`, `-audit-faq-links`. `-get-inbound-links` answers which pages link **to** a page, including navigation-menu links — the opposite direction from every existing outbound-link ability.

**Batch 7 — entitlement-gated (6 abilities).** `-get-content-ai-status`, `-manage-content-ai-prompts`, `-manage-content-ai-output`, `-research-keyword`; `-get-ai-visibility-brand`, `-update-ai-visibility-object`. Registered **unconditionally** and gated at runtime, deliberately unlike `register_elementor_pro_abilities()`: Content AI and AI Visibility ship in Rank Math *free* and gate on cloud-account registration plus a credit balance, not on a separate plugin, so availability can change without an activation and cannot be decided at registration time.

**Security.** Every ability requires `manage_options` **and** Rank Math's own granular `rank_math_*` capability, matching the convention across the rest of the plugin's ability suites. The floor is uniform across all 61 and declared `final` so it cannot be lowered per ability. Revoking a capability in Rank Math's Role Manager therefore genuinely blocks the corresponding ability, which the companion plugin's blanket `manage_options` ignores. One documented filter, `acrossai_abilities_manager_rank_math_permission`, lets site owners relax the policy. Twelve abilities are irreversible and require `confirm: true`. The post-scoped writers additionally perform per-object `edit_post` / `edit_user` / `edit_terms` checks inside their handler as defence in depth, and the schema writers verify that a `schema-<meta_id>` row actually belongs to the named object before writing — Rank Math addresses schema rows by meta id and would otherwise update a different object's row.

**Data-loss prevention.** Rank Math's settings sanitizer defaults any field it was not told the type of to single-line text, which strips newlines, and its own field definitions use *legacy* CMB2 type names while the sanitizer's cases are the *React* names — 11 of 19 legacy types match no case. `Settings_Registry` therefore ships a declarative field-spec table for all 20 panels, mirroring the Rank Math source with a file citation per panel, and maps legacy names onto the sanitizer's vocabulary. Verified live with a control: writing `nofollow_domains` with the mapped `textarea` type preserves newlines, while the identical write using Rank Math's own declared `textarea_small` stores them collapsed onto one line. Nine multi-line settings were at risk.

**Notable.** No raw Rank Math option read/write ability ships — the plugin already provides generic option abilities, and adding Rank Math-branded raw writers would reintroduce exactly the data-loss path above. No bulk role-capability writer ships either, because `Helper::set_capabilities()` strips capabilities from roles omitted from the payload; the existing per-capability abilities cannot trigger that. The `.htaccess` editor, version rollback and beta opt-in are out of scope.

= 0.0.27 - 2026-08-14 =
**Patch release — UI polish + admin-surface rename following the 0.0.26 Feature 067 rollup.** No new abilities; both entries below are UX-affecting changes to the admin surface. Plugin version bumped 0.0.26 → 0.0.27.

* **Rename — "Ability Library" admin page is now "Ability Integrations".** The submenu label ("Library" → "Integrations"), page title ("Ability Library" → "Ability Integrations"), main heading, and URL slug (`page=acrossai-abilities-library` → `page=acrossai-abilities-integrations`) all updated. Bookmarks / external links to the old slug will 404 in wp-admin — update saved links to the new URL. Internal class names, hook names, REST endpoint namespace (`/wp-json/acrossai-abilities-library/v1/`), and the DOM mount id are unchanged (deliberately scoped rename — extending to the REST namespace would break external MCP callers).
* **UI fix — Elementor abilities now render under their own "Elementor" tab in the Ability Integrations screen, not "Core".** Every Elementor ability (all 88 under `elementor/*`) had its meta `tab_group` set to `'core'`, causing the group to appear in the Core tab with only a sub-heading identifying it as Elementor. Flipped every declaration to `tab_group => 'elementor'` (63 files including `Base_Audit_Ability`, which drives the 25 audit subclasses via inheritance). The Ability Integrations UI auto-derives tab names from distinct `tab_group` values, so a new "Elementor" tab appears without any frontend/asset rebuild.

= 0.0.26 - 2026-08-14 =
**Release rollup — 89 abilities total: 87 unreleased Elementor abilities (Feature 067 completion) + 2 native site maintenance-mode abilities.** Plugin version bumped 0.0.25 → 0.0.26. Elementor abilities gate on `class_exists('\Elementor\Plugin')` (with 8 additionally gated on Elementor Pro); site maintenance-mode toggle has no plugin dependency.

* **Native site maintenance-mode toggle (2 abilities):**
  * `site-health/set-site-maintenance-mode` — activate WordPress core maintenance mode by writing the `ABSPATH/.maintenance` marker file (the same file WP core writes during plugin/theme/core updates). A wp-cron event refreshes the marker every 5 minutes so the site stays down for the requested `duration_minutes` (default 60, hard-cap 1440). Requires `confirm=true` — blocks wp-admin as well as the frontend.
  * `site-health/unset-site-maintenance-mode` — deactivate: delete the marker, clear the refresh cron, drop the expiry option. Idempotent — safe to call when maintenance mode is already inactive. Reports `was_active` in the response.
  * Both live under the existing `acrossai-abilities-manager-site-health` category alongside `site-health/get-maintenance-mode-status` (Feature 063 read). No Elementor / plugin dependency — works on every WP install.

* **Feature 067 COMPLETE — 87 additional Elementor abilities ship in this release.** Combined with the 2 foundation abilities from 0.0.25, the full 88 planned abilities are now available under the `elementor/*` namespace. Design-audit ability logic is skeletal (`Base_Audit_Ability` skeleton returning empty findings) — real audit heuristics to be filled in follow-up work.

**Batch 10 — full-document replacement (closes the parity gap):**
  * `elementor/update-data` — overwrite the entire `_elementor_data` tree for a post with a caller-supplied element array; optional `page_settings` merge; `force_replace=true` required when the new payload is materially smaller than the existing document. Returns `element_count` + cache scope report.

**Batch 9 — 29 design-audit abilities (this commit):**

Aggregators + scorers (4):
  * `elementor/evaluate-design` — aggregate report from every registered design audit (score + findings + recommendations).
  * `elementor/suggest-design-fixes` — turn aggregated findings into concrete fix recommendations.
  * `elementor/score-distinctiveness` — neutral distinctiveness score for structural repetition.
  * `elementor/extract-design-tokens` — extract recurring colors / typography / spacing / dimensional tokens.

Individual audits (14):
  * Column: `audit-column-alignment-rhythm`, `audit-column-balance`, `audit-column-dominance`, `audit-column-necessity`, `audit-column-patterns`
  * Composition & emphasis: `audit-composition-rhythm`, `audit-emphasis-drift`, `audit-section-rivalry`, `audit-separator-discipline`, `audit-surface-overuse`
  * Layout & repetition: `audit-generic-component-repetition`, `audit-generic-layout-patterns`, `audit-layout-mechanism-fit`, `audit-native-widget-opportunities`

Subtree operations — destructive (7):
  * `apply-text-hierarchy`, `enforce-boundary-coherence`, `fix-visible-gap-rhythm`, `normalize-responsive-values`, `normalize-section-spacing-rhythm`, `reset-negative-margins-subtree`, `zero-container-padding-subtree`

Copy / sync / convert helpers — destructive (4):
  * `copy-lane-settings`, `copy-row-balance`, `image-widget-to-background-container`, `sync-component-variant`

New utility class `includes/Abilities/Elementor/Base_Audit_Ability.php` provides the shared skeleton for 27 of the 29 audit abilities — subclasses supply `audit_slug`, `audit_label`, `audit_description`, and `analyze()`. `Evaluate_Design` and `Suggest_Design_Fixes` are self-contained aggregators.

**Batch 8 — 8 Elementor Pro-gated abilities:**
  * `elementor/list-custom-code` — list Custom Code snippets from `elementor_snippet` CPT; optional location filter.
  * `elementor/get-custom-code` — read one snippet including its code body.
  * `elementor/create-custom-code` — create snippet with title, code, location (head / body_start / body_end / footer), priority, status.
  * `elementor/update-custom-code` — update snippet fields.
  * `elementor/delete-custom-code` — trash (default) or permanently delete with `force=true`.
  * `elementor/list-form-submissions` — list Form widget submissions from the `e_submissions` table; optional `form_id` filter + `include_values` flag. Graceful degradation when the Pro submissions table is missing.
  * `elementor/get-form-submission` — read one submission by ID; optional field values.
  * `elementor/delete-form-submission` — permanently delete submission + its `e_submissions_values` rows; requires `confirm=true`.

All 8 Pro abilities gated on **both** `class_exists( '\Elementor\Plugin' )` **and** `class_exists( '\ElementorPro\Plugin' ) || defined( 'ELEMENTOR_PRO_VERSION' )` — silently absent on sites without Elementor Pro. Runtime deactivation returns `error_code: elementor_pro_missing`.

**Batch 7 — 7 kits & site-settings abilities:**
  * `elementor/list-kits` — list all Elementor kits; marks active kit.
  * `elementor/get-kit-settings` — read kit settings (defaults to active kit).
  * `elementor/update-kit-settings` — merge new settings; `force_replace` for full overwrite; site-wide cache invalidation.
  * `elementor/set-active-kit` — switch site-wide active kit; invalidates cache.
  * `elementor/list-global-widgets` — list global (reusable) widgets from elementor_library CPT.
  * `elementor/list-experiments` — list feature flags with current + default state.
  * `elementor/update-experiment` — toggle experiment state (active | inactive | default).

**Batch 6 — 11 template abilities:**
  * `elementor/list-templates` — list saved templates with filters on `template_type` + `status` + pagination.
  * `elementor/get-template` — return one template's metadata + conditions + optional `_elementor_data`.
  * `elementor/create-template` — create a new template of type page / section / popup / header / footer / single / archive; sets taxonomy term + Elementor meta.
  * `elementor/update-template` — update title / page_settings / full data with `force_replace` guard.
  * `elementor/delete-template` — trash (default) or permanently delete with `force=true`.
  * `elementor/restore-template` — restore a trashed template.
  * `elementor/duplicate-template` — clone template preserving type + conditions + sub_type; regenerates element IDs.
  * `elementor/empty-trash` — permanently delete every trashed template; requires `confirm=true`.
  * `elementor/export-template` — export template as JSON-encodable object (title, template_type, sub_type, page_settings, content, conditions).
  * `elementor/import-template` — import from JSON export; regenerates element IDs; optional `overwrite_id` to replace an existing template.
  * `elementor/find-template-for-pattern` — rank saved templates by keyword match (title + tax term + widget-types in content); returns top N with scores.

**Batch 5 — 11 site-management abilities:**
  * `elementor/clear-cache` — clear Elementor cache at post / site / all scope; optional `regenerate_css=true` for a specific post.
  * `elementor/replace-urls` — bulk find/replace URLs across every Elementor document on the site with `dry_run=true` default preview.
  * `elementor/get-maintenance-mode` — read current maintenance mode settings (mode, template, exclude rules).
  * `elementor/update-maintenance-mode` — enable/disable maintenance mode with mode selection (maintenance | coming_soon).
  * `elementor/get-theme-builder-conditions` — read display conditions attached to an Elementor template.
  * `elementor/update-theme-builder-conditions` — replace display conditions; pass empty array to clear. Invalidates Elementor's condition cache.
  * `elementor/get-official-widget-catalog` — canonical widget catalog (Basic / Pro / Theme / WooCommerce) with 12-hour transient.
  * `elementor/get-official-pattern-guidance` — pattern & layout guidance (widgets / patterns / layouts topics) grounded in Elementor documentation.
  * `elementor/get-theme-context` — active theme + Elementor version + active kit + viewport settings snapshot.
  * `elementor/get-style-guide` — style-guide summary from active kit (colors, typography, buttons, forms, layout, custom CSS).
  * `elementor/evaluate-render-context` — inspect frontend template + canvas type + edit-mode flag for a post.

**Batch 4 — 9 page-composition abilities:**
  * `elementor/create-page` — insert a new post/page pre-configured for Elementor (sets `_elementor_edit_mode`, `_elementor_template_type`, `_elementor_version`; seeds empty `_elementor_data`). Returns edit URL.
  * `elementor/update-page-settings` — merge new page-level settings into `_elementor_page_settings`. `force_replace` guard on materially-smaller payloads.
  * `elementor/patch-data` — find/replace text within the raw Elementor JSON string; updates every widget containing the match in one pass.
  * `elementor/clone-data` — copy the full Elementor tree from one post to another with fresh element IDs throughout. Optionally include page settings. `force_replace` guard on populated targets.
  * `elementor/add-heading` — widget shortcut (title, header_size h1-h6, align, title_color).
  * `elementor/add-text-editor` — widget shortcut (editor HTML, align).
  * `elementor/add-image` — widget shortcut (image ID or URL, size, align, caption, link).
  * `elementor/add-button` — widget shortcut (text, link, size xs-xl, align).
  * `elementor/add-post-tabs` — higher-order shortcut: Nested Tabs widget where each tab contains a native Posts widget (optionally filtered by taxonomy term or query args).

**Batch 3 — 6 element-lifecycle abilities (previously in this section):**
  * `elementor/merge-element-settings` — deep-merge new settings into an element by ID. Additive (no force_replace guard needed); reports `changed_keys` in the response.
  * `elementor/delete-element` — remove an element by ID. Guarded by `force_delete=true` for top-level or populated (with-children) elements.
  * `elementor/remove-element` — safer alias for `delete-element` with identical semantics.
  * `elementor/move-element` — atomic move to a new parent/position with descendant-guard preventing cycle-creating moves into own subtree.
  * `elementor/duplicate-element` — deep-clone an element (all nested children included) with fresh IDs generated throughout the cloned subtree; inserted as the next sibling.
  * `elementor/reorder-elements` — reorder direct children of a parent (or root); children omitted from `ordered_element_ids` retain their prior relative order and are appended after.

**Batch 2 — 5 abilities merged earlier:**
  * `elementor/get-element` — read a single element by 7-char hex ID.
  * `elementor/find-elements` — search by `element_type` / `widget_type` / contains-text.
  * `elementor/update-element` — replace by ID with `force_replace` guard.
  * `elementor/add-container` — insert Elementor v3+ container.
  * `elementor/add-widget` — insert any registered widget (validated via `Widget_Controls`).

**Test coverage:** 43 (b2) + 40 (b3) + 53 (b4) + 64 (b5) + 53 (b6) + 33 (b7) + 45 (b8) + 8 (b9 manifest) = 339 new source-inspection tests across 58 test files. Full suite: 975 tests, 1991 assertions, 0 failures. phpcs (WPCS strict) and phpstan (level 8) both clean.

**Feature 067 ability surface complete: 88 of 88 abilities.** Foundation + 2 shipped in 0.0.25 + 86 in this release. Every planned ability shipped. Design-audit analysis logic is skeletal (returns empty findings + recommendations); the audit surface is registered and composable via `Design_Audit_Runner`, real heuristics to be filled in follow-up work.

= 0.0.25 =
* **New — Feature 067 Elementor Ability Suite (interim ship: foundation + 2 abilities).** First release of the planned 88-ability Elementor integration. This interim release delivers the full foundational infrastructure plus two highest-value abilities. Follow-up features (068+) will incrementally add the remaining 86 abilities.

**Foundation — 6 utility classes + category registrar under `includes/Abilities/Utilities/Elementor/` and `includes/Abilities/Elementor/`:**
  * `Category_Registrar` — registers the new `acrossai-abilities-manager-elementor` ability category. Self-guards on `class_exists( '\Elementor\Plugin' )` so the category is silently absent on non-Elementor sites.
  * `Document_Repository` — Elementor document I/O with mandatory `wp_slash()` policy on `_elementor_data` writes, cache invalidation (Elementor files manager + WP post cache + `_elementor_css` meta delete), and full tree helpers (find/insert/remove/reorder/replace by element ID, deep-clone with fresh IDs, descendant-guard).
  * `Widget_Controls` — schema-safe summariser over Elementor's `WidgetsManager::get_widget_types()` with case-insensitive control-name filtering.
  * `Template_Query` — `WP_Query` wrappers for the `elementor_library` CPT with tax filters + keyword-scoring for pattern-search abilities.
  * `Guidance_Catalog` — canonical Elementor.com widget catalog (60+ Basic/Pro/Theme/WooCommerce widgets seeded, 12-hour transient) + pattern & layout guidance data (nav-menu vs mega-menu, container vs section, Grid vs Flexbox for symmetric columns, etc.).
  * `Design_Audit_Runner` — orchestrator for the 28 design-audit abilities landing in follow-up features (register + run individual + run-all with aggregate score + findings + recommendations).

**Bootstrap gating** in `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php`:
  * Two-layer gate: outer `class_exists( '\Elementor\Plugin' )` at `plugins_loaded` P20 (registration-time) plus per-ability defense-in-depth check at execution time (runtime deactivation returns clean `error_code: elementor_missing` envelope, no fatals).
  * Inner Pro gate: `class_exists( '\ElementorPro\Plugin' ) || defined( 'ELEMENTOR_PRO_VERSION' )` for the future Custom Code + Form Submissions abilities.
  * Split into two private methods `register_elementor_free_abilities()` + `register_elementor_pro_abilities()` — new `new Elementor\<Class>()` lines added as each ability class lands.

**Two shipped abilities under `elementor/*` namespace:**
  * `elementor/get-widget-controls` — schema-lookup primitive. Returns the schema-safe control summary for any registered Elementor widget on the current site (free + Pro + third-party). Enables clients to author valid add-widget / update-element payloads without hard-coded per-widget wrappers. Optional case-insensitive search filter.
  * `elementor/get-data` — the read primitive. Returns the parsed Elementor document tree + page settings for a post, plus recursive element count.

**Test coverage:** 44 new utility tests + 15 new ability tests = 59 additional PHPUnit assertions. Full suite: 636 tests, 1530 assertions, 0 failures. phpcs (WPCS strict) and phpstan (level 8) both clean.

**Test-bootstrap additions:** stubs for `wp_rand`, `get_transient`, `set_transient`, `delete_transient`, and `HOUR_IN_SECONDS` constant to support the new utilities under the unit-only bootstrap.

**Spec artifacts** at `specs/067-elementor-abilities/`: complete design for all 88 abilities documented in `spec.md` / `plan.md` / `research.md` / `data-model.md` / `contracts/abilities.md` / `quickstart.md` / `tasks.md` — follow-up features will implement Phases 3-13 tasks against these contracts.

= 0.0.24 =
* **New — 6 abilities and 1 enhancement for full Gutenberg block-tree control (feature 066).** Closes the gap between the plugin's existing block-registry surface and per-post block-tree manipulation. All abilities live under the existing `acrossai-abilities-manager-content` category.

**Feature 066 — Block tree mutation & nested editing (6 new abilities + 1 modified).**
  * `blocks/get-post-blocks` — return a post's parsed Gutenberg block tree with each block annotated with its canonical integer-array path (e.g. `[0, 2, 1]` = 2nd grandchild of the 3rd child of the 1st top-level block). Read-only, idempotent.
  * `blocks/add-block` — insert a new block into a post at `parent_path` + `index`. Appends when the requested index exceeds the current sibling count.
  * `blocks/remove-block` — remove the block at a canonical path; returns the removed payload so callers can undo/log.
  * `blocks/duplicate-block` — deep-clone the block at a path (including all inner blocks) and insert the clone as the next sibling.
  * `blocks/move-block` — atomically move a block from `from_path` to `to_parent_path` + `to_index`. Refuses moves into the source's own subtree (would create a cycle).
  * `blocks/insert-pattern` — resolve a saved block pattern by slug across database / active theme / installed plugins, then insert its constituent blocks at `parent_path` + `index`. Ambiguous slugs return `multiple_locations` so callers can disambiguate via `source` / `theme_type` / `plugin_slug`.
  * `blocks/update-post-block` (modified) — now accepts an optional `path` input for nested editing at any depth. Existing consumers using `block_index` or `block_name` + `occurrence` see **zero behaviour change** — the path branch is a strict addition.
  * All write abilities share the same guards as the existing `update-post-block`: `manage_options` + `edit_posts` globally, `edit_post` per-post, post-type whitelist against internal CPTs (revision / nav_menu_item / custom_css / customize_changeset / oembed_cache / user_request), block-name regex validation, and soft-fail attribute-schema validation against the registered block type.
  * Shared `Block_Tree` utility (`includes/Abilities/Utilities/Block_Tree.php`) centralises tree-path primitives — walk, get-at-path, insert / remove / replace / move, block-name and attribute-schema validation. Extracts what was previously private inline logic in `Update_Post_Block::execute`.
  * Test coverage: 82 new PHPUnit assertions across 8 test files.

= 0.0.23 =
* **New — 30 abilities across three feature spec drops (062, 063, 064).** Bulk expansion of the plugin's ability surface. No breaking changes.

**Feature 062 — Role & capability CRUD + site-wide DB search-replace (8 abilities).**
  * `users/add-role-capability`, `users/remove-role-capability`, `users/create-role`, `users/delete-role`, `users/reset-role`, `users/add-user-capability`, `users/remove-user-capability` — writers for the role/cap surface WordPress core REST does not expose. Every write is `destructive: true`.
  * `database/search-replace` — site-wide serialized-data-safe string replacement across every WordPress-managed table. **`dry_run: true` by default** — the ability returns a per-table / per-column match tally without mutating any row, and mutating writes only happen when the caller explicitly passes `dry_run: false`. Table allowlist mirrors `Update_Db_Rows.php` (validates every input table against `SHOW TABLES` before scanning). Skips `wp_posts.guid` unless the caller explicitly opts in via `include_guids: true` (safer default than WP-CLI). Recursive `maybe_unserialize` / `maybe_serialize` walk keeps serialized meta / options structurally valid.
  * Guardrails: `remove-role-capability` refuses to strip a WP-core administrator baseline capability from the `administrator` role; `delete-role` refuses on any of the 5 built-in roles AND when the role is still held by any user; `reset-role` accepts only the 5 built-in role slugs; `remove-user-capability` refuses to strip a WP-core admin cap from the last remaining administrator.

**Feature 063 — Site introspection reads + new Widgets category (11 abilities).**
  * `core/get-wp-version`, `database/get-db-prefix`, `file-manager/get-wp-config-constant`, `themes/list-theme-mods`, `settings/list-rewrite-rules`, `media/list-image-sizes`, `comments/get-comment-count`, `site-health/get-maintenance-mode-status`, `cron/test-wp-cron` — small single-purpose reads that WordPress does not expose through a public REST endpoint. Every ability is `readonly: true, idempotent: true, destructive: false`.
  * `widgets/list-widgets`, `widgets/list-sidebars` — legacy widget-system introspection under a new **Widgets** category (slug `acrossai-abilities-manager-widgets`).
  * Guardrails: `get-wp-config-constant` hard-blocks disclosure of `AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`, `LOGGED_IN_SALT`, `NONCE_SALT`, and `DB_PASSWORD` regardless of the `manage_options` gate; `get-maintenance-mode-status` uses WordPress core's own 10-minute staleness threshold; `test-wp-cron` fires a single non-blocking `wp_remote_get()` with a 0.01s timeout so it never hangs a REST response.

**Feature 064 — Transient CRUD, nested option access, plugin lifecycle & checksum integrity (11 abilities).**
  * Transient CRUD (Cache category): `cache/get-transient`, `cache/list-transients` (paginated, search-filterable, expiry-aware), `cache/delete-transient`, `cache/delete-expired-transients` — closes the previous read-nothing / bulk-only-delete gap.
  * Nested option access (Options category): `options/get-nested-option-value` and `options/patch-option-value` — read or mutate one nested key inside a serialized option without round-tripping the whole blob. Guarded by `Update_Option::BLOCKED_OPTIONS` (extracted as a `public const` on `Update_Option` in this release so both classes share one authoritative block-list of 21 protected core options).
  * Post-meta append (Content category): `content/add-post-meta` — WordPress core `add_post_meta()` semantics with the WP-core `unique` flag. Complements the existing update / delete post-meta writers.
  * Plugin lifecycle (Plugins category): `plugins/search-wp-plugin-directory` (searches the WordPress.org plugin directory via `plugins_api()`; short description sanitised via `wp_kses_post()`), `plugins/uninstall-plugin` (fires the plugin's registered uninstall hook + deletes files via WP core `uninstall_plugin()`; refuses on active plugins and on sites with `DISALLOW_FILE_MODS`), `plugins/verify-plugin-checksums`.
  * Core integrity (Core category): `core/verify-core-checksums` — fetches the official `api.wordpress.org` checksums manifest via `wp_remote_get()` and compares `md5_file()` hashes; per-file `status: 'ok'|'modified'|'missing'|'added'` and a summary counter.

* **Every one of the 30 new abilities gates on `current_user_can( 'manage_options' )`** using the identical permission-callback pattern already used by all 219 existing abilities: `static function (): bool { return current_user_can( 'manage_options' ); }`. No cap escalation via filter.
* **One new ability category — Widgets** (`acrossai-abilities-manager-widgets`), registered via `includes/Abilities/Widgets/Category_Registrar.php` mirroring the shape of `includes/Abilities/Menus/`.
* **204 new PHPUnit test methods** on top of the previous 191 (final suite: ~395 methods across the 8.1 → 8.5 PHP CI matrix). Every new class file passes PHPStan level 8 and the plugin's PHPCS WPCS strict profile.
* **No breaking changes.** No ability slug rename. No REST endpoint change. No option-shape change. No new required capability. Existing 218 abilities behave identically. The `Update_Option::BLOCKED_OPTIONS` extraction in Feature 064 is a pure move of an inline literal into a `public const`; behaviour is unchanged. Safe upgrade from 0.0.22.

= 0.0.22 =
* **New — `content/delete-post-meta` ability under the Content category.** Deletes a single post meta row via WordPress core `delete_post_meta()`. Accepts `post_id` + `key` (with the WP-core-native `meta_key` alias) and an optional `value` (with `meta_value` alias). When a value is supplied, only rows matching that value are removed; otherwise every row for the given key is removed. Gated by `manage_options`; annotated `destructive: true`, `idempotent: true`. Mirrors the shape of `content/update-post-meta` for consistent client ergonomics.
* **Fixed — `content/update-post-meta` no longer rejects protected meta keys (#99).** The pre-0.0.22 `execute()` short-circuited with `success: false` whenever `is_protected_meta( $key, 'post' )` returned true, contradicting the class docblock ("Works for ANY meta key"). Now the ability writes any key the `manage_options` gate allows through — the capability check remains the sole access boundary. The registered `description` was also updated to match the new behaviour ("Works for any meta key, including protected keys.").
* **Composer dependency bump — `acrossai-co/main-menu` 0.0.30 → 0.0.33.** Rolls three shared-menu library releases into one hop; `composer.lock` regenerated to reference `e17e1e8`.
* **No breaking changes.** No ability slug rename. No REST endpoint change. No option-shape change. No new required capability. Existing 218 abilities behave identically. Safe upgrade from 0.0.21.

= 0.0.21 =
* **Composer dependency bump — `wpboilerplate/wpb-access-control` `^2.0.0` → `^3.1.0`.** Adopts two major releases of the shared access-control library in one hop:
  * **v3.0.0 (breaking, but not for this plugin).** The two plugin-dependent providers shipped in the library's v1.4.0 / v1.5.0 — `BuddyBossProfileTypeProvider` (`bb_profile_type`) and `MemberPressMembershipProvider` (`mepr_membership`) — were extracted into a separate WordPress add-on called **AcrossAI User Access Pro** (`acrossai/user-access-pro`), along with eight new integrations (LearnDash Group, LifterLMS Membership, Paid Memberships Pro, Restrict Content Pro, WooCommerce Memberships, s2Member Level, Wishlist Member Level, Memberium Membership). The library now ships only the three WordPress-native providers (`wp_role`, `wp_user`, `wp_capability`) plus a new `wpb_access_control_register_providers` global filter for add-on registration. **This plugin uses only `AccessControlManager` + `RuleTable` — neither of the removed provider classes.** No consumer-side code change is required; every existing Access Control rule shape is preserved and the per-consumer `AccessControlManager( $providers_filter, $table_slug )` constructor signature is unchanged.
  * **v3.1.0.** Adds a new `AccessControlManager::TYPE_AUTHENTICATED` (`'authenticated'`) sentinel rule type — grants access to any logged-in user without requiring a specific role or capability match. Rendered in the Access Control dropdown as "Any logged-in user", stored as a single sentinel row like `everyone`. Also renames the public option label from "Everyone (no restriction)" to "Public (no login required)" for clarity. Existing rules are untouched; the `everyone` key behaves identically.
* **New Access Control rule affordance on every ability.** Site administrators can now pick "Any logged-in user" from the Access Control dropdown on the ability edit panel — useful for abilities that should be reachable by every authenticated user (including subscribers) without curating a specific role list. Rules using the previous "Everyone" wording continue to work unchanged; the dropdown label just clarifies that `everyone` means "no login required."
* **Migration required only for sites vendoring the built assets.** Consumer plugins that pin `vendor/wpboilerplate/wpb-access-control/assets/build/` in their release bundle should `composer update` and rebuild to pick up the new dropdown option. This plugin re-vendors the library's compiled CSS via `admin/Main.php::enqueue_styles()` and the `composer update` this changelog entry documents already regenerates that asset path.
* **No breaking changes.** No ability slug rename. No REST endpoint change. No option-shape change. No new required capability. Every existing 218 abilities behave identically. Existing Access Control rules keep working — the removed BuddyBoss / MemberPress providers were never registered from this plugin (they defaulted to `is_available() === false` in the library's v1.6.0 – v2.0.x range on sites that had not explicitly opted in). Safe upgrade from 0.0.20.

= 0.0.20 =
* **Changed — access-control library-missing notice now routes through the shared AcrossAI notice hub.** The pre-0.0.20 `AcrossAI_Abilities_Access_Control::maybe_show_library_notice()` method was hooked on WordPress core `admin_notices` and printed a raw `.notice.notice-warning` banner on every admin screen when the `wpb-access-control` library wasn't loaded. It is renamed to `register_library_notice( array $notices ): array` and now registers into the new `acrossai_notices` filter shipped by `acrossai-co/main-menu` 0.0.30. The notice appears in two places instead: (1) as a card on the new **AcrossAI → Notices** submenu (only registered when at least one notice is present, with a WP-style count bubble on the menu label), and (2) as a single top-of-page WordPress-native `.notice.notice-warning.is-dismissible` summary banner ("AcrossAI has N notifications for your attention — View notices →") printed on every other admin page. Dismissal is fingerprint-persisted per user until the notice set changes. Notice record shape: `id=wpb_access_control_missing`, `type=warning`, `source=AcrossAI Abilities Manager`. Semantics are unchanged — the fail-open behaviour, the `manage_options` gate (enforced by the menu itself and the summary emitter), and the message copy are all preserved.
* **Composer dependency bump — `acrossai-co/main-menu` 0.0.29 → 0.0.30.** Ships the cross-plugin notice system this release routes through:
  * New `acrossai_notices` filter — any AcrossAI consumer plugin can push admin-notice records into a shared collection using a single documented record shape (`id`, `title`, `message`, `type`, optional `source`, optional `action { label, url }`). Later registrations of the same `id` are ignored (first-wins). Missing `id` or both `title` and `message` empty → the entry is dropped.
  * New **AcrossAI → Notices** submenu (slug `acrossai-notices`, class `NoticesPageRenderer`) — only registered when at least one notice exists. Menu label carries a WP-style count bubble (`.awaiting-mod`).
  * New top-of-page summary notice emitter (`SummaryNoticeEmitter`) — prints one WordPress-native dismissible banner on every other admin page linking to the Notices submenu. Dismissal is fingerprint-based (SHA-1 of sorted notice IDs stored in per-user meta `_acrossai_notices_summary_fp`) so the summary re-appears whenever the notice set changes.
  * New AJAX endpoint `wp_ajax_acrossai_notices_dismiss_summary` — nonce + `manage_options` guarded; server re-validates the client-supplied fingerprint against the current notice set as defense-in-depth against poisoning the user meta with an unrelated hash.
  * New public classes under `AcrossAI_Main_Menu\`: `Notices`, `NoticesPageRenderer`, `NoticesAjaxHandlers`, `SummaryNoticeEmitter`. New page-slug constant `SettingsPage::NOTICES_SLUG` and static accessor `SettingsPage::get_notices(): ?Notices` for consumers that want to inspect the current notice list programmatically.
* **Note — the vendor-missing boot-resilience notice in `Includes\Main::__construct()` remains on core `admin_notices`.** That code path fires precisely when the composer autoloader is absent — the moment when the shared main-menu package isn't loadable either — so the `acrossai_notices` filter cannot be reached from it. This is intentional and matches Constitution §V Integration Resilience.
* **No breaking changes.** No ability slug rename. No REST endpoint change. No option-shape change. No new required capability. Existing 218 abilities behave identically. Safe upgrade from 0.0.19.

= 0.0.19 =
* **New — MCP Manager promo callout on the ability edit form.** The MCP Exposure section (Section 3) of the Custom Abilities edit page now surfaces a blue-tinted informational callout advertising the sibling `acrossai-mcp-manager` plugin when it is not installed / active on the current site. The callout renders directly below the existing "Heads up" warning and offers two actions: an "Install from Add-ons" button that deep-links to the AcrossAI Add-ons page (`admin.php?page=acrossai-addons`), and a "Learn more" external link to `https://acrossai.co/mcp-manager/`. When the AcrossAI MCP Manager plugin IS active on the site, the callout is fully suppressed — zero UI on the edit form. Detection uses WordPress core `is_plugin_active( 'acrossai-mcp-manager/acrossai-mcp-manager.php' )` inside the admin script enqueue path; the resolved boolean plus the two URLs are injected into the existing `window.acrossaiAbilitiesManager` localize payload as `mcp_manager_active`, `mcp_manager_addons_url`, and `mcp_manager_info_url`. The callout also degrades gracefully on older bundles or a customised localize payload — the two action buttons render only when their corresponding URL keys are non-empty.
* **Composer dependency bump — `acrossai-co/main-menu` 0.0.27 → 0.0.29.** Two-hop bump rolled into one release:
  * *0.0.28* — refreshed the Add-ons page baseline catalogue. The hard-coded add-on list now surfaces three entries: **AcrossAI Abilities Manager** (wp.org), **AcrossAI MCP Manager** (wp.org), and **AI Connectors** (external "Get add-on ↗" link to `acrossai.co/ai-connectors/#pricing`). AcrossAI Model Manager and Turn Off AI Features are dropped from the hard-coded baseline — sites that still want them can register them via the `acrossai_addons` filter unchanged. All three baseline cards render the shared AcrossAI SVG logo from `acrossai.co` instead of per-plugin `ps.w.org` PNG icons, so the Add-ons page reads as one product surface. Icon fit switched from `cover` to `contain` (with 6px padding) so wide/horizontal SVG logos render fully instead of being cropped inside the 56×56 icon box. Grid pinned to a fixed 3-column layout (`repeat(3, minmax(0, 1fr))`) with responsive fallbacks (2 cols under 1100px, 1 col under 720px). New optional `learn_more_url` add-on field renders as a "Learn more" text link inside the card action row for every add-on regardless of `source`.
  * *0.0.29* — reworked the Add-ons card action states so the page reads as a **discovery surface, not a plugin manager**. Active add-ons now render a non-clickable green **"● Running"** pill instead of a "Deactivate" button; deactivation stays in Plugins → Installed Plugins where WP admins expect it (new CSS classes `.acrossai-addons__status` / `.acrossai-addons__status--active` / `.acrossai-addons__status-dot`). Installed non-`wordpress.org` add-ons now show an in-page **Activate** button instead of always rendering the external "Get add-on ↗" link — detection is source-agnostic and driven by `AddonsInstaller::find_plugin_file()`, so a paid/off-directory add-on that the admin uploaded via Plugins → Add New → Upload Plugin can be activated straight from the AcrossAI Add-ons page. The Install code path remains restricted to `wordpress.org` sources (WP.org guideline #8 — no change). The `AI Connectors` baseline entry declares `install_folder => 'acrossai-ai-connectors'` so install detection matches the actual plugin folder even though the registry slug (`ai-connectors`) differs — canonical example for consumers whose extracted folder ≠ slug.
* **No breaking changes.** No ability slug rename. No REST endpoint change. No option-shape change. No new required capability. Existing 218 abilities behave identically. Safe upgrade from 0.0.18.

= 0.0.18 =
* **New — Third-party integration framework (Feature 060) with Advanced Custom Fields as the first concrete integration.** Adds a new "Acf" tab to the Ability Library page (`/wp-admin/admin.php?page=acrossai-abilities-library&tab=acf`) with a single toggle labelled "Advanced Custom Fields (AI)". Flipping the toggle ON attaches `add_filter( 'acf/settings/enable_acf_ai', '__return_true' )` early enough in `plugins_loaded` (priority 20) that ACF picks it up on the same request and registers its FieldGroup / PostType / Taxonomy AI abilities. Flipping OFF leaves ACF's default (writes disabled) in place. Default is **OFF** for every integration — enabling AI-driven schema manipulation on a production site is always an explicit admin decision. The tab and card only appear when the target plugin (ACF) is installed AND active on the current site; deactivating ACF while the toggle is on preserves the saved state without leaking any error notice or fatal.
* **New — extensibility surface for third-party AcrossAI plugins.** Any WordPress plugin can now register its own regular ability cards on an integration's tab (alongside the integration's own toggle card) using a documented 3-step contract: (1) register the ability category on `wp_abilities_api_categories_init` via `wp_register_ability_category()`, (2) extend `\AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition`, and (3) set `meta.acrossai.tab_group` on the ability's args to the integration's published `TAB_GROUP` constant (e.g. `\AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\ACF::TAB_GROUP`). Reads from the new `AcrossAI_Integration_Ability_Base` docblock + the quickstart worked example under `specs/060-library-third-party-integration-toggles/quickstart.md`. This is the mechanism that lets the sibling `acrossai-acf-abilities` plugin surface its own cards on the same "Acf" tab.
* **New REST filter — `acrossai_integration_toggle_capability`.** Lets sites raise (never lower) the WordPress capability required to flip an integration toggle. Default is `manage_options` (matches the rest of the Ability Library page); a site can attach a filter returning e.g. `manage_network_options` and a `manage_options`-only user will then receive HTTP 403 on the REST write. Enforced server-side on the same write path that persists the toggle — cannot be bypassed by a crafted REST request even if the JS UI presented the toggle as interactive. Companion action `acrossai_integration_toggle_denied` fires immediately before the 403 so sites can wire audit logging without amending core code.
* **Bugfix — sparse-storage in `acrossai_library_config` was silently stripping integration ON entries.** The pre-Feature-060 sparse-storage rule in `AcrossAI_Ability_Library_Config::save_config()` assumed every category defaults to `enabled=true`, so a `{ enabled: true, mode: 'all', sub_keys: {} }` payload was stripped as "default state". Feature 060 integration categories invert that default (missing = OFF per FR-008), so the ON state was being silently dropped and the toggle appeared to turn itself off on reload. Fixed by teaching sparse-storage which slugs are integrations (via the new public helper `AcrossAI_Ability_Library_Registry::get_integration_slugs()`) and computing the correct default per-category before deciding whether to strip.
* **Composer dependency bump — `acrossai-co/main-menu` 0.0.23 → 0.0.27.** Two WordPress.org plugin directory guideline #8 fixes rolled into one bump:
  * *0.0.26* — the Consultations submenu (introduced in 0.0.24) previously embedded the Calendly widget via `assets.calendly.com/assets/external/widget.js` and an iframe pointed at `calendly.com/acrossai/using-ai-in-wordpress`. It now renders a self-contained call-to-action page that opens `calendly.com` in a new browser tab only when the admin clicks the button — no Calendly script, iframe, or asset is loaded inside wp-admin. Fixes the "using iframes for admin pages" prohibition.
  * *0.0.27* — the Add-ons page's install action is now WordPress.org-only. Cards whose `source` is `wordpress.org` continue to render an in-page Install / Activate / Deactivate button (routed through WordPress core's `plugins_api()` + `Plugin_Upgrader`). Cards with any other `source` (e.g. `github`, `freemius`, or any consumer-defined value) render an external "Get add-on ↗" link that opens the vendor's site in a new browser tab — users install those add-ons via WP admin's standard **Plugins → Add New → Upload Plugin** flow, or via the vendor's own installer. Fixes the "installing plugins/themes/add-ons from non-WordPress.org servers" prohibition. Same design pattern used by WooCommerce and GiveWP for their extension marketplaces. Also adds a new public helper `AddonsInstaller::is_installable_source( array $addon ): bool` and defense-in-depth rejection in the AJAX install handler.

  Purely additive on the AcrossAI parent-menu surface — no changes to any of our own submenus (Ability Library at priority 2, Settings at priority 20). `SettingsPage`'s public constructor signature is unchanged. Consumers that only ship wp.org-sourced add-ons see no visible change; consumers pushing GitHub/Freemius entries via the `acrossai_addons` filter will see those cards flip from Install button to "Get add-on ↗" link — no code change required on their side.
* **20 new PHPUnit tests + 12 new Jest tests** cover the base-class contract, the extension pattern end-to-end, resilience under target-plugin deactivation, the default-OFF safety property, and the sparse-storage bugfix regression. Full suite: 191 tests passing.
* **6 new durable memory entries** in `docs/memory/` (DECISIONS.md, ARCHITECTURE.md, BUGS.md, INDEX.md) capture the reusable patterns: `DEC-ABILITY-DEFINITION-CTOR-HOOKS`, `PATTERN-LIBRARY-INTEGRATION-BASE`, `PATTERN-LIBRARY-INTEGRATION-TAB-EXTENSION`, `PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY`, `BUG-WP-CORE-ABILITY-CATEGORY-PRE-REGISTRATION`, `BUG-SPARSE-STORAGE-UNIFORM-DEFAULT-ASSUMPTION`. Documented so the next contributor doesn't rediscover the same traps (specifically: WP core silently rejects abilities whose category isn't pre-registered via `wp_register_ability_category`, and asymmetric-default keys break naive sparse-storage optimisations).
* **No breaking changes.** No ability slug rename. No REST endpoint change. No option-shape change. No new required capability. Existing 218 abilities behave identically. Safe upgrade from 0.0.17.

= 0.0.17 =
* **New — 7 Recovery Mode abilities under a new `Recovery` category.** Adds `recovery/get-recovery-mode-status`, `recovery/list-paused-plugins`, `recovery/list-paused-themes`, `recovery/get-recovery-exit-url`, `recovery/unpause-plugin`, `recovery/unpause-theme`, and `recovery/list-recent-fatal-errors`. Together they let an AI agent driving the site over REST/MCP detect if WordPress has entered Recovery Mode after a fatal error, enumerate paused (fatally-erroring) plugins and themes with their captured error details, clear a paused entry so WP retries loading the extension on the next request, retrieve the admin-clickable exit URL, and pull grouped fatal-error signatures from `debug.log`. Every write action gates on `manage_options` + `File_Mods_Guard::blocked_response()`; fuzzy plugin/theme identifiers flow through the existing `Plugin_Helpers::resolve_plugin()` / `Theme_Helpers::resolve_theme()` resolvers.
* **Intentional non-goals: no programmatic recovery-mode trigger, no programmatic exit.** WP core has no public API to enter recovery mode from a REST call (the handler only fires on a real fatal at a protected endpoint), and exit is guarded by both a session cookie and a nonce that a normal admin REST session can't satisfy. The `get-recovery-exit-url` ability returns the URL for an admin (or a browser-driving agent) to follow instead.
* **Docs — recovery-mode compatibility noted on 3 existing abilities.** `activate-plugin`, `deactivate-plugin`, and `activate-theme` now mention in their `description` that they work in recovery mode (they only update the `active_plugins` / active-theme option and don't load the extension file).
* **Ships without JS, DB, or REST-controller changes.** Purely new PHP source under `includes/Abilities/Recovery/` plus 7-line bootstrap wiring. 21 new PHPUnit tests under the new `feature-059-unit` testsuite.

= 0.0.16 =
* **New — `core/reinstall-wp-core` ability under the Core category.** Reinstalls the currently-installed WordPress version by handing a synthetic offer with `response = 'reinstall'` to WP core's `Core_Upgrader::upgrade()` — same code path the WordPress dashboard's "Reinstall now" button uses. Uses `allow_relaxed_file_ownership=false` so ownership mismatches surface as errors instead of silent partial-upgrades. Requires BOTH `manage_options` AND `update_core`; honours `DISALLOW_FILE_MODS` via `File_Mods_Guard`; multisite-guarded; refuses when core is being actively updated to a different version (use `core/update-wp-core` for that). Complements the existing `core/update-wp-core`, `core/rollback-wp-core`, and `core/check-wp-core-update` abilities.
* **BREAKING — every ability slug has been renamed.** Two changes at once: (a) namespace shortens from `acrossai-abilities-manager/` (27 chars) to `acrossai/` (9 chars), and (b) suffixes flip to verb-first form. Every ability is now `acrossai/<verb>-<subject>` — e.g. `settings/get-site-title`, `themes/activate-theme`, `plugins/list-plugins` — instead of the pre-0.0.16 `acrossai-abilities-manager/<subject>-<verb>` form. 163 suffixes changed; 56 already-verb-first suffixes only had their namespace shortened. Every ability's `label` was already verb-first ("Get Site Title", "Activate Theme"), so the slug now reads the same word order as the label. Motivation: alignment with the WordPress core MCP adapter convention (`mcp-adapter/discover-abilities`, `mcp-adapter/execute-ability`) and with the broader function-calling / MCP naming used by every major LLM tool-use spec.
* **Class files and PHP class names flipped to verb-first too.** 162 files renamed to match their slugs — `Site_Title_Get.php` → `Get_Site_Title.php`, `Theme_Activate.php` → `Activate_Theme.php`, etc. Internal-only change; no external API surface. PSR-4 autoload picks up the new file/class names automatically.
* **No automatic data migration.** The plugin is still small enough that no auto-migration ships with this release. If you had a pre-0.0.16 install with saved overrides or ACL rules keyed on the old `acrossai-abilities-manager/<subject>-<verb>` slugs, wipe those rows manually (Custom Abilities admin page → clear overrides; Access Control admin page → clear rules) and re-create them under the new `acrossai/<verb>-<subject>` names. External callers (custom code, saved MCP client configs, scripts calling `/wp-json/wp-abilities/v1/abilities/acrossai-abilities-manager/<old-slug>/run`) must update their slug strings to `/wp-json/wp-abilities/v1/abilities/acrossai/<new-slug>/run`. No backwards-compatibility aliases are shipped.

= 0.0.15 =
* **Bulk Actions overhaul on the Custom Abilities admin page — Site Access / MCP Exposure / User Access / Overrides.** Replaces the misleading Publish / Unpublish / Delete dropdown (WP-CPT vocabulary that never mapped to how ability overrides behave) with four ability-native optgroups that mirror the per-row edit drawer: **Site Access** tri-state (Force Allow / Inherit / Force Block writing `site_allowed`), **MCP Exposure** tri-state (Enable / Default / Disable writing `show_in_mcp`), **User Access** (opens a modal that mounts the composer's `<AccessControl>` picker and applies one rule across every selected slug, plus a "Reset to Default — allow everyone" quick action), and **Overrides → Force Reset** (clears every override column per slug via the existing `DELETE /abilities/{slug}/override` endpoint). Destructive transitions (Force Block, MCP Disable, User Access Reset, Force Reset) prompt for confirmation before dispatch.
* **Row-level checkbox and Edit action now work on every ability regardless of Source.** The pre-0.0.15 checkbox gate limited selection to Custom (`db`) rows only — a hangover from the deleted Publish/Unpublish/Delete flow. Bulk tri-state operations apply to any Source, so every visible row now shows a checkbox and can be included in a bulk selection. The Edit action was already unconditional across sources; verified with the same release.
* **Full-screen busy overlay with WP-native spinner + body scroll-lock during every bulk apply.** Uses `<span class="spinner is-active">` (the same spinner WP admin shows next to Save Draft) over a backdrop-blurred wash; the underlying page is un-clickable and the body cannot scroll until the bulk request set resolves. Escape-to-dismiss on the User Access modal is suppressed while its apply is in flight to prevent half-applied state on the underlying multi-slug write.
* **Client-side only release. No PHP changes, no new REST endpoints, no new database tables, no new composer or npm packages.** All storage, sanitisation, capability enforcement, and REST controllers are unchanged. The feature loops the pre-existing per-slug endpoints under `Promise.all` inside three new Redux thunks (`bulkUpdateTristate`, `bulkClearOverrides`, `bulkSetUserAccessRule`); the composer package's provider enumeration and rule storage are reused verbatim.
* **25 new Jest tests across three suites** cover payload discipline (raw JSON `true` / `false` / `null` on tri-state writes), partial-failure re-throw discipline (operator sees an error and keeps the selection for retry instead of a silent success), composer null-response guard, and a slug-encoding regression guard (see below). Also adds two new architecture patterns and one new bug pattern to `docs/memory/`.
* **Fixed: composer User Access rule keys were storing the ability slug with the `/` character stripped when applied via the bulk path.** Root cause: client-side `encodeURIComponent(slug)` on the composer PUT URL was collapsed to nothing by the composer's key sanitizer (`%2F` was stripped rather than decoded back to `/`), producing orphan rows like `acrossai-abilities-managerblock-pattern-delete`. Fixed by matching the per-row edit drawer's pattern — passing the slug raw into the URL. Server-side `sanitize_ability_slug()` still validates independently, so no security regression. Guarded by a Jest regression test.

= 0.0.14 =
* **wp.org banner artwork refreshed + filenames renamed to the WP.org canonical convention.** `banner1544x500.png` → `banner-1544x500.png` and `banner772x250.png` → `banner-772x250.png`. WordPress.org's plugin directory only auto-detects banners at the dashed paths (`.wordpress-org/banner-{width}x{height}.png`) — the un-dashed variants shipped in 0.0.13 were not being surfaced on the plugin listing page. Both banners also carry updated artwork in this release. wp.org-assets-only change; no plugin code touched.

= 0.0.13 =
* **Docs — ability gap audit landed under `specs/054-ability-gap-audit/`.** Tracks 31 abilities across 10 domains that external AI-tool inventories expect but the plugin does not yet expose (Site editor / structure, Admin menu, Navigation, Users, Content index / search / linking, Content advanced, Taxonomy, Media, Site lifecycle, Comments). Current registered inventory: 187 abilities under `acrossai/*`, verified via grep against `wp_register_ability` and confirmed wired 1:1 into `AcrossAI_Core_Abilities_Bootstrap.php`. For every missing ability, the audit names the closest existing ability in the plugin (or explicitly declares the domain as absent) so future implementation waves do not accidentally duplicate work. Each missing ability becomes its own follow-up spec later. No runtime code changes; audit-only release.
* **wp.org assets — banner (1544×500 and 772×250) and a sixth screenshot added.** The plugin directory listing now shows a proper header banner (previously falling back to the WordPress.org default header since 0.0.4) and a sixth screenshot covering the Settings page (Display + Upload Media Abilities sections). Metadata-only change to `.wordpress-org/` — no plugin code touched.
* **31 new abilities across 10 domains — 187 → 218.** Ships the entire backlog surfaced by the external AI-tool inventory audit. Two new categories are added: `acrossai-abilities-manager-admin-menu` (5 abilities) and `acrossai-abilities-manager-content-search` (11 abilities). Single-item additions land in existing categories: `users-current-access` (Users), `taxonomy-set-term-image` (Taxonomies), `comments-bulk-update` (Comments), `media-rename-file` (Media), `navigation-get-context` + `navigation-list-locations` (Menus), `content-update-block` + `content-autosaves-inspect` (Content), `site-editor-get-context` + `site-editor-refresh-context` + `site-structure-list-reusable-blocks` + `site-structure-list-block-areas` (Block), `site-maintenance-report` (SiteHealth), `plugin-lifecycle-get-plugin` (Plugins), `theme-lifecycle-get-theme` (Themes).
* **New option-backed lifecycle event log.** `plugin-lifecycle-get-plugin` and `theme-lifecycle-get-theme` return `last_activated_at` / `last_deactivated_at` / `last_updated_at` timestamps from a rolling event log (`acrossai_abilities_manager_lifecycle_log` option, capped at 50 events per plugin/theme). Events are recorded from 0.0.13 forward — pre-0.0.13 lifecycle history is not backfilled and those timestamps read `0` until the next event fires.
* **New option-backed internal-link suggestion store.** The 5 `content-internal-link-*` abilities (create, list, review, apply, policy) plus `content-audit-internal-links` persist to `acrossai_abilities_manager_link_suggestions` (option-backed, capped at 500 total suggestions). Zero external HTTP; zero new database tables.
* **No breaking changes.** No existing ability slug / input schema / output schema / permission callback is altered. Every previously-registered ability still resolves to the same class.
* **Safety notes.** `media-rename-file` refuses filenames with a directory separator, null byte, or leading dot; enforces realpath containment inside the attachment's original upload sub-directory; refuses to clobber an existing target. `comments-bulk-update` requires `moderate_comments` and caps at 100 comment ids per call. `content-internal-link-suggestion-apply` requires `edit_others_posts`, re-validates the target as same-site, and only mutates on first-occurrence substring match.

= 0.0.12 =
* **New — WordPress core rollback ability under the Core category.** `core/rollback-wp-core` rolls back WordPress core to an earlier offered version via WP core's `Core_Upgrader::upgrade()` — the same class the WordPress dashboard uses for forward updates. Fetches the offer list from the WP.org Core API 1.7 endpoint (`https://api.wordpress.org/core/version-check/1.7/`) via `wp_remote_get()`, picks the requested version, and hands the offer directly to the upgrader. Uses only WordPress functions; no bundled updater code. Requires BOTH `manage_options` AND `update_core`; honours `DISALLOW_FILE_MODS` via `File_Mods_Guard`; multisite-guarded; refuses when the target version is equal to or newer than the currently-installed version (steers callers to `wp-core-update`). The per-locale offer list is cached in a site transient with a day-long TTL. Annotated `destructive=true` — rolling WordPress back is a real production operation and clients should surface it accordingly. Inspired by Andy Fragen's [core-rollback](https://github.com/afragen/core-rollback) plugin (MIT-licensed). See PR [#77](https://github.com/acrossai-co/acrossai-abilities-manager/pull/77).
* **First outbound HTTP request from the plugin.** Historically the plugin has made zero outbound HTTP requests on its own (the Add-ons page delegates to the WordPress plugin installer's own contact with WordPress.org; other abilities operate on the local site). `wp-core-rollback` introduces the plugin's first direct outbound request — to `api.wordpress.org/core/version-check/1.7/`. The URL is a hardcoded class constant (no SSRF surface), the request has a 15-second timeout, and only the sanitized locale is derived from user input. The per-locale offer list is cached in a site transient with a day-long TTL, so the request rate is bounded to at most one call per day per locale per site.

= 0.0.11 =
* **New — WordPress core update abilities under a new "Core" category.** Two new abilities: `core/check-wp-core-update` reports whether a WordPress core update is available (returns `current_version`, `new_version`, download URL, PHP / MySQL requirements — flattens WP's core update offer into a JSON-friendly shape); `core/update-wp-core` applies the update via WP core's `Core_Upgrader::upgrade()`. When called with no arguments it upgrades to the first `response=upgrade` offer from `get_core_updates()`; pass `version` (+ optional `locale`) to pin to a specific offer. Requires BOTH `manage_options` AND `update_core` (matches WP core's own admin gate). Honours `DISALLOW_FILE_MODS` via `File_Mods_Guard`. Multisite guard bails cleanly if the current user lacks network-level `update_core`. Idempotent — re-running when no update is available returns a clean success envelope with `updated=false`. Uses WP core functions exclusively; no bundled updater, no custom HTTP, no custom integrity checks. See PR [#75](https://github.com/acrossai-co/acrossai-abilities-manager/pull/75).
* **New Core category folder.** `includes/Abilities/Core/` joins the existing 17 Category folders (Plugins, Themes, FileManager, Cache, Database, Users, Block, Settings, Fonts, Content, Taxonomies, Media, Comments, Menus, Options, Cron, SiteHealth). Displayed as a new "Core" tab on the Ability Library page. Not a new module — Constitution §I locks the module count at five; Category folders are sub-partitions of the existing Custom Ability Registration module.
* **Backup filenames — human-readable and time-sortable.** Filenames produced by `zip-create` (and finalized zips from `zip-upload`) change from `backup-{type}-{slug}-{random-12-chars}.zip` to `{slug}-{unix-timestamp}-{ms}.zip` (e.g. `hello-dolly-1721260800-517.zip`). Lexicographic sort now equals chronological sort; the target is readable at a glance. The 3-digit millisecond suffix from `microtime(true)` prevents same-second collisions when two back-to-back calls target the same slug. Trade-off: dropping the 12-char random suffix removes the enumeration-by-guessing defense the old scheme provided. Mitigations still in force: the `.htaccess` in `wp-content/uploads/acrossai-backups/` still disables directory listing (`Options -Indexes`) and still blocks execution of PHP-family extensions, and `zip-list` / `zip-download` still require `manage_options`. Backups created on 0.0.9 / 0.0.10 with the old scheme continue to work — the filename change only affects new backups.
* **Spec-Kit backfill for Feature 041.** `specs/041-backup-restore-abilities-and-updates/` now exists with the full artifact set (spec, plan, tasks, checklists, security-constraints, memory-synthesis, architecture-review) documenting the 8 abilities that shipped in 0.0.9 and the 0.0.10 include_hidden fix. Same seven-file layout used by Feature 053's backfill.

= 0.0.10 =
* **Fix (Create_Zip_Backup) — `include_hidden=false` now applies recursively.** The 0.0.9 implementation used `RecursiveIteratorIterator::SELF_FIRST` with a per-entry basename check that only skipped the top-level hidden directory itself; the iterator kept descending into it, so files INSIDE a hidden directory (e.g. `.git/objects/xxx`) were still added because their basenames don't start with `.`. Fixed to check EVERY segment of the entry's relative path — same approach the reference `download-plugin` uses in its `app/Plugins/Base.php`. Applied to both the archive assembly (`append_dir_to_zip`) and the pre-write size guard (`estimate_tree_size`). If you called `zip-create` with `include_hidden=false` against a dev checkout in 0.0.9, the archive contained the full contents of every hidden directory beneath the source — regenerate any such archives on 0.0.10. See PR [#73](https://github.com/acrossai-co/acrossai-abilities-manager/pull/73).

= 0.0.9 =
* **New — six Zip abilities under FileManager for backup / restore workflows.** `file-manager/create-zip-backup` archives a plugin, theme, uploads folder, mu-plugins folder, or any ABSPATH-relative path into `wp-content/uploads/acrossai-backups/<random>.zip` and returns the download URL + SHA-256. `zip-upload` accepts a zip via base64, chunked (up to 8 MB per chunk / 64 MB per session, filterable), or a remote URL and finalizes it into the same directory after validating the `PK\x03\x04` magic bytes. `zip-extract` extracts a zip already on disk or fetched from a URL into a resolved target directory (plugin / theme / uploads / mu-plugins / path); every archive entry is audited for zip-slip (`..` segments, absolute paths, backslashes, null bytes) before extraction. `zip-download` returns a fresh URL + metadata for any managed zip. `zip-list` paginates the managed directories, newest first. `zip-delete` removes a zip from the managed directories idempotently. The backups directory is hardened on first use with an `.htaccess` that blocks PHP execution while keeping `.zip` downloads reachable, plus an empty `index.php` for enumeration defense. All abilities enforce `manage_options`; every mutating ability honours `DISALLOW_FILE_MODS` via the shared `File_Mods_Guard`. See PR #TBD.
* **New — `plugins/update-plugin` and `themes/update-theme` abilities.** The Plugins and Themes categories previously shipped an `update-check` reporter but no way to apply updates through the abilities API. The new abilities wrap WP core `Plugin_Upgrader::bulk_upgrade()` / `Theme_Upgrader::bulk_upgrade()` (same pattern as the existing `plugin-install` / `theme-install`), accept an array of plugin files / slugs or theme stylesheets, and return per-slug results with `from_version`, `to_version`, `updated`, and `message`. Update_Plugin additionally requires `update_plugins`; Update_Theme additionally requires `update_themes`. Idempotent: re-running when no update is available reports `updated_count: 0` with a clean success envelope.
* **Shared utilities: `Backups_Storage` and `Zip_Target_Resolver`.** New helpers under `includes/Abilities/Utilities/`. `Backups_Storage` manages the `acrossai-backups/` and `acrossai-staging/` directories under `wp-content/uploads/`, generates enumeration-resistant random filenames, resolves managed paths with `realpath()` boundary checks, and computes SHA-256 for the listing / download responses. `Zip_Target_Resolver` maps `(target_type, target)` to an absolute filesystem path — plugin slugs resolve via `Plugin_Helpers` (existing fuzzy resolver), theme stylesheets via `Theme_Helpers`, `uploads` via `wp_get_upload_dir()`, `mu-plugins` via `WPMU_PLUGIN_DIR`, and `path` values via a strict inside-ABSPATH realpath check.
* **Upload_Zip_Backup chunk sweeper cron.** A new daily cron (`acrossai_abilities_manager_zip_upload_sweep_chunks`) sweeps abandoned chunk sessions from `wp-content/uploads/acrossai-staging/` after the configurable TTL (default: 1 day, filterable via `acrossai_abilities_manager_zip_upload_session_ttl`). Mirrors the existing Upload_Media sweeper.
* **New configurable limits.** `acrossai_abilities_manager_zip_max_bytes` (default 512 MB) caps the decompressed size of any zip written or extracted. `acrossai_abilities_manager_zip_upload_chunk_max_bytes` (default 8 MB base64) and `acrossai_abilities_manager_zip_upload_session_max_bytes` (default 64 MB base64) cap the chunked upload flow.

= 0.0.8 =
* **Freemius integration removed entirely.** The `freemius/wordpress-sdk` composer dependency is dropped (upstream `acrossai-co/main-menu` 0.0.21+ no longer requires it). The plugin no longer sends any data to Freemius, no longer shows a Connect / Login / Buy affordance on the Add-ons page, and the entire Freemius vendored SDK tree (~2,000 files) is removed from the installable ZIP. If you previously connected a Freemius account tied to this plugin, that connection is now inert; any `fs_*` or `freemius_*` rows in `wp_options` are no longer read by anything and can be safely deleted (e.g. `wp option list --search='fs_*'` then `wp option delete <name>` for each). This supersedes the 0.0.6 changelog entry about Freemius credentials — those credentials are no longer used. See PR [#69](https://github.com/acrossai-co/acrossai-abilities-manager/pull/69).
* **Add-ons page — free-only, and this plugin excluded from its own listing.** The Add-ons page (`?page=acrossai-addons`) now lists only free companion plugins hosted on WordPress.org (Install / Activate / Deactivate via the standard WP plugin installer). This plugin no longer appears in its own Add-ons page — a small self-filter on the `acrossai_addons` hook removes it, since it's obviously already active when the page renders. Other AcrossAI companion plugins (MCP Manager, Model Manager, Turn Off AI Features) still list normally.
* **Library page — title and Enable All / Disable All buttons on a single horizontal row.** The "Ability Library" page heading and the bulk-action buttons introduced in 0.0.7 (Feature 052) now share one line at the top of the page (title anchored left, buttons anchored right). Saves vertical space; matches administrator expectations for admin page layouts.
* **Dependencies: `acrossai-co/main-menu` bumped from `0.0.14` to `0.0.23`.** Three-hop bump (0.0.21 → 0.0.22 → 0.0.23) accumulated during the release cycle. Consumer API surface (`SettingsPage`, `MenuRegistrar`, `AddonsPageRenderer`) preserved across each hop; no code changes required beyond removing the old `\AcrossAI_Addon\AddonsPage` instantiation (class deleted upstream in 0.0.21 — its responsibilities moved into `\AcrossAI_Main_Menu\MenuRegistrar` which is registered automatically when the shared `SettingsPage` bootstrap runs).

= 0.0.7 =
* **Library page — bulk Enable All / Disable All action buttons.** A new right-aligned header row above the tab strip on `?page=acrossai-abilities-library` renders two side-by-side buttons that toggle every ability category currently in view with a single click. Actions are scoped to the active tab: on the `All` tab they touch every registered category; on a specific tab (Core, Blocks, Themes, Users, Cache, File Manager, Cron, Database, Plugins) they only touch categories whose ability metadata declares that `tab_group`. Categories in other tabs pass through byte-for-byte unchanged. Each category's mode (All / Specific) and per-slug selections are preserved on both actions — a Disable All → Enable All cycle is a lossless round-trip. Persisted via the existing `POST /acrossai-abilities-library/v1/abilities/config` REST route (`manage_options` + nonce, unchanged). See PR [#68](https://github.com/acrossai-co/acrossai-abilities-manager/pull/68).
* **URL-synced tabs on the Library page.** The active tab is now reflected in the browser URL as `?tab=<slug>`. Deep-linkable, bookmarkable, and browser back / forward navigation re-syncs the visible tab. Direct-navigation to `?page=acrossai-abilities-library&tab=themes` opens the Themes tab on first paint. Invalid tab values silently fall back to the default `All` view — no error, no console warning. The default `All` view keeps the canonical URL clean by removing the `tab` query arg entirely.
* **Disabled-card UI refresh on the Library page.** Disabled category cards now show the master toggle + category label + chevron (visible whenever the category has at least one registered ability). Expanding the chevron on a disabled card reveals a readonly bullet-style preview of the abilities in that category (with descriptions). The All / Specific mode selector and interactive per-ability checkboxes remain hidden while the card is disabled — no interactive control can render on a disabled card even when the stored mode is `Specific`. The stored mode and per-slug selections are preserved so re-enabling restores the prior configuration exactly. Manual per-card disable and bulk `Disable All` produce identical card DOM.

= 0.0.6 =
* **BREAKING (downstream integrators) — 17 ability category slugs rebranded from `acrossai-core-abilities-<domain>` to `acrossai-abilities-manager-<domain>`, and 176 ability slugs rebranded from `acrossai-core-abilities/<verb>` to `acrossai/<verb>`.** The companion `acrossai-core-abilities` plugin's entire 201-file runtime (17 Category_Registrars, 176 ability classes, 8 helper classes, plus the extra-MIME-types admin field) is absorbed into this plugin. Every category and ability slug is renamed uniformly; ability payload shapes and permission callbacks are preserved verbatim. Downstream code (MCP servers, REST/WP-CLI callers, integration tests) that referenced the legacy `acrossai-core-abilities-*` slugs by string must update on cutover. Ability payloads themselves are unchanged. See PR [#65](https://github.com/acrossai-co/acrossai-abilities-manager/pull/65).
* **Absorbed extra-MIME-types Settings field lands under the Abilities tab.** The companion plugin's Core settings tab is retired; its "extra allowed upload MIME types" field now renders inside the shared Settings → Abilities tab. The companion's separate uninstall opt-in is folded into the manager's existing single `acrossai_abilities_uninstall_delete_data` opt-in — no second checkbox appears. Activation-time migration copies the legacy option (`acrossai_core_abilities_extra_mimes` → `acrossai_abilities_manager_extra_mimes`), OR-monotonically folds the legacy uninstall opt-in into the manager's opt-in (never demotes a manager-true value), and deletes both legacy option rows. Existing admin configuration is preserved. See PR [#65](https://github.com/acrossai-co/acrossai-abilities-manager/pull/65).
* **Retire the `acrossai-core-abilities` companion plugin.** After upgrading to 0.0.6, deactivate and uninstall the standalone `acrossai-core-abilities` plugin — all 176 abilities are now provided by this manager plugin directly. Keeping both plugins active will emit duplicate-registration notices from the WP Abilities API on every request. Removal of the companion plugin folder from production sites is an operational task, separate from this release.
* **Library page — Themes / Blocks / Plugins / Users / Database / Cron / Cache / File Manager get their own tabs.** The absorbed categories are promoted from the shared "Core" tab into their own top-level tabs on the Ability Library page (`?page=acrossai-abilities-library`). The "Core" tab stays pinned as the second option (immediately after "All") regardless of alphabetical ordering. The "No abilities registered yet" empty-state copy is updated for the new bundled reality.
* **Dependencies: `acrossai-co/main-menu` bumped from `0.0.11` to `0.0.14`.** Adopts the Tabs base class extraction (0.0.14) and tab-scoped `option_group` (0.0.13) — the latter fixes the cross-tab option-clobber bug where saving one Settings tab silently wiped other tabs' options. See PR [#66](https://github.com/acrossai-co/acrossai-abilities-manager/pull/66).
* **Freemius product identifiers rotated** — `fs_product_id` changed from `31230` to `34418`, `fs_public_key` rotated to `pk_d61a7ddb1a619f7697fbb4fc397b6`. If you have a Freemius account tied to the previous product ID, reconnect on the Account submenu after upgrade.

= 0.0.5 =
* **Dependencies: `acrossai-co/main-menu` bumped to `0.0.11`.** Picks up the latest AcrossAI shared parent menu / dashboard / settings / add-ons page code from that package. No plugin-owned code changes in this release — the bump is the only functional delta vs 0.0.4.

= 0.0.4 =
* **BREAKING (add-on developers) — Library display fields moved from top-level `$args` into `$args['meta']['acrossai']`.** The three Library-only fields introduced by Features 033 and 037 — `sub_group`, `sub_group_label`, and `tab_group` — are no longer read from the top level of the `$args` array passed to `wp_register_ability()`. They must now be nested under `$args['meta']['acrossai']`, matching the existing `meta.mcp` (MCP integration) and `meta.annotations` (WP-core annotations) convention. This is a hard cut with no back-compat shim: any add-on that still passes the fields at the top level will silently render its Library card without a sub-group heading or custom tab placement. Migration: change `'sub_group' => 'x'` to `'meta' => [ 'acrossai' => [ 'sub_group' => 'x' ] ]` (same for `sub_group_label` and `tab_group`). Only affects add-ons that extend `Ability_Definition` and use these Library display fields; abilities without them are unaffected. No end-user data migration, no DB schema change, no REST API change.
* **Plugin icon replaced with a vector (SVG) asset.** The WordPress.org plugin directory now serves `.wordpress-org/icon.svg` in place of the previous 128×128 / 256×256 JPG icons, so the icon renders sharp at any display density. Also removes the 772×250 and 1544×500 header banners from the directory listing — the plugin page will show the WordPress.org default header until banners are re-added. wp.org-assets-only change.

= 0.0.3 =
* **Fix: plugin now activates on installs from WordPress.org.** The 0.0.2 release ZIP shipped without the Composer autoloader (`vendor/autoload_packages.php`) because the WordPress.org deploy workflow did not run `composer install` before uploading. Users installing 0.0.2 from the WordPress.org plugin directory saw the plugin activation guard trigger: *"AcrossAI Abilities Manager cannot activate: the Composer autoloader is missing…"*. The 0.0.3 release ZIP includes the full production autoloader; no other code changes. If you already installed 0.0.2 and hit the activation error, delete the plugin folder and reinstall 0.0.3.

= 0.0.2 =
* **Composer dependency refresh** — `wpb-access-control` bumped to v2.0.0 (per-consumer database tables); `acrossai-co/main-menu` bumped to v0.0.10 (now bundles the Add-ons page and includes the JS-side rebrand-sync fix that restores Install / Activate / Deactivate button behavior). The standalone `acrossai-co/addons-page` package has been removed from direct dependencies; the same `AcrossAI_Addon\AddonsPage` class now ships from the `main-menu` package.
* **Per-consumer access-control storage** — this plugin now owns its own `{prefix}abilities_access_control` database table, keeping its rules fully isolated from any other plugin embedding the same access-control library. The dedicated table is created automatically on plugin activation.
* **Add-ons submenu URL changed** — the Add-ons page slug is now `acrossai-addons` (was `wpb-addons`). Any bookmarks or external links pointing at `wp-admin/admin.php?page=wpb-addons` should be updated to `wp-admin/admin.php?page=acrossai-addons`. The submenu location and behavior are otherwise unchanged.
* **BREAKING — Access Control rules from earlier releases are NOT migrated.** If you previously configured Access Control rules on any ability, those rules were stored in the shared `{prefix}wpb_access_control` table and are **no longer read** by this release. After upgrading, please audit every ability's Access Control panel and reconfigure any rules that were previously in place. The legacy table is left on disk (in case you need to reference the prior configuration) and can be dropped manually by a database administrator if desired: `DROP TABLE {prefix}wpb_access_control;` and `DELETE FROM {prefix}options WHERE option_name = 'wpb_access_control_db_version';`.
* **BREAKING — Ability execution logging removed.** The dedicated Logs admin page, the log-retention Settings field, the `{prefix}acrossai_ability_logs` database table, and the `/wp-json/acrossai-abilities-log/v1/logger/logs` REST endpoint are all removed. If you rely on ability-execution logging for security monitoring or auditing, install a compatible logging plugin or hook `wp_after_execute_ability` directly in your own consumer code — the upstream ability-execution events remain available. Bookmarks to `wp-admin/admin.php?page=acrossai-abilities-logs` receive the standard "page does not exist" response. External integrations polling the removed REST endpoint receive 404. On existing installs, the legacy logs table and its schema-version option are orphaned; opt into the "delete all data on uninstall" setting to drop them cleanly, or run manually: `DROP TABLE {prefix}acrossai_ability_logs;` and `DELETE FROM {prefix}options WHERE option_name IN ('acrossai_abilities_log_retention_days', 'acrossai_ability_logs_db_version');`.

= 0.0.1 =
* Initial release.
* Sitewide Ability Management: browse, toggle, edit, reset, bulk-action.
* Ability Library: enable/disable add-on ability groups with All/Specific mode controls.
* Add-ons page powered by wpb-addons-page with Freemius integration.
* MCP server listing via MCP Adapter integration.

== Upgrade Notice ==

= 0.0.21 =
Bumps the `wpboilerplate/wpb-access-control` composer dependency from `^2.0.0` to `^3.1.0` — two library releases in one hop. v3.0.0 removed two plugin-dependent providers (`BuddyBossProfileTypeProvider`, `MemberPressMembershipProvider`) that were extracted into a separate add-on (`acrossai/user-access-pro`); this plugin uses only the core `AccessControlManager` + `RuleTable` classes, so no consumer code change is required. v3.1.0 adds a new "Any logged-in user" option to the Access Control dropdown (backed by a new `authenticated` sentinel rule type), and renames "Everyone (no restriction)" → "Public (no login required)" for clarity. Existing rules unaffected. Safe upgrade from 0.0.20.

= 0.0.20 =
Routes the access-control library-missing warning through the new shared AcrossAI notice hub (`acrossai_notices` filter shipped by `acrossai-co/main-menu` 0.0.30). Instead of a raw wp-admin banner on every screen, the notice now appears on the new AcrossAI → Notices submenu (with a count bubble on the menu label) and as a single top-of-page summary banner ("AcrossAI has N notifications for your attention — View notices →") whose dismissal persists per user until the notice set changes. The fail-open semantics and message copy are unchanged. No breaking changes; existing abilities unaffected. Safe upgrade from 0.0.19.

= 0.0.19 =
Adds a blue promotional callout on the ability edit form (MCP Exposure section) that advertises the sibling AcrossAI MCP Manager plugin when it is not installed / active. The callout links to the AcrossAI Add-ons page for install and to acrossai.co/mcp-manager/ for more info. Fully suppressed when the AcrossAI MCP Manager plugin is active. Also bumps the `acrossai-co/main-menu` composer dependency from 0.0.27 to 0.0.29 — 0.0.28 refreshes the Add-ons page baseline catalogue (AcrossAI Abilities Manager + AcrossAI MCP Manager + AI Connectors) with shared brand icon, `contain`-fitted icon boxes, fixed 3-column grid layout, and a new optional `learn_more_url` field; 0.0.29 reworks the card action states so active add-ons render a non-clickable "● Running" pill (deactivation stays in Plugins → Installed Plugins) and installed non-wp.org add-ons now show an in-page Activate button instead of always linking out. No breaking changes; existing abilities unaffected. Safe upgrade from 0.0.18.

= 0.0.18 =
New third-party integration framework (Feature 060) with Advanced Custom Fields as the first concrete integration — flip one toggle on the new "Acf" tab of the Ability Library page to enable ACF's AI abilities without editing code. Also new: extensibility surface so other AcrossAI plugins can add their own cards to an integration's tab, filterable capability check for the toggle (via `acrossai_integration_toggle_capability`), and audit action (`acrossai_integration_toggle_denied`). Fixes a sparse-storage bug that could silently strip the integration ON state. Bumps the `acrossai-co/main-menu` composer dependency from 0.0.23 to 0.0.27 to land two WordPress.org plugin directory guideline #8 fixes: the Consultations submenu now uses an external-link CTA instead of an embedded Calendly iframe, and the Add-ons page install action is now WordPress.org-only (non-wp.org cards render as external "Get add-on ↗" links opening the vendor's site in a new tab). No breaking changes; existing abilities unaffected. Safe upgrade from 0.0.17.

= 0.0.17 =
BREAKING — every ability slug has been renamed. Namespace shortens from `acrossai-abilities-manager/` to `acrossai/`; suffixes flip to verb-first form (e.g. `site-title-get` → `get-site-title`, `theme-activate` → `activate-theme`). External callers (custom code, saved MCP client configs, ACL entries created outside the plugin's UI, scripts calling `/wp-json/wp-abilities/v1/abilities/acrossai-abilities-manager/<old>/run`) must update their slug references to `/wp-json/wp-abilities/v1/abilities/acrossai/<new>/run`. No backwards-compatibility aliases; no automatic data migration — clear pre-existing overrides + ACL rules keyed on old slugs from the admin UI and re-add them under the new names. Also new: 7 Recovery Mode abilities (detect recovery, list paused plugins/themes, unpause, exit URL, fatal-error log filter) and `core/reinstall-wp-core`. 162 PHP class files renamed to match slugs (internal-only; PSR-4 autoload picks up automatically). PHP 8.1+ / WP 6.9+ floor unchanged.

= 0.0.15 =
UI-only release. Replaces the Custom Abilities Bulk Actions dropdown (Publish / Unpublish / Delete) with Site Access, MCP Exposure, User Access, and Overrides operations that match the per-row edit drawer. Row-level checkbox now works on every ability regardless of Source. Reuses existing REST endpoints; no new database tables, no new endpoints, no PHP changes, no dependency changes, no permission changes. Also fixes a bug that stored composer User Access rule keys with the ability slug's `/` character stripped when applied via the (new) bulk path. Safe upgrade.

= 0.0.14 =
wp.org assets only. Refreshes the banner artwork and renames both banner files from `banner{width}x{height}.png` to the WP.org-canonical `banner-{width}x{height}.png` (the 0.0.13 filenames were not being auto-detected by the plugin directory). No plugin code touched; no REST, DB, or capability changes. Safe upgrade.

= 0.0.13 =

Docs + wp.org assets only. Adds `specs/054-ability-gap-audit/` (a reference audit of abilities that external AI-tool inventories expect but the plugin does not yet expose) and commits the previously-untracked `.wordpress-org` banner (1544×500 + 772×250) and a sixth screenshot covering the Settings page. No functional changes; no REST, DB, or capability changes; no code touched under `includes/` or `src/`. Safe upgrade.
Adds 31 new abilities across 10 domains (187 → 218). Two new categories join the Ability Library: Admin Menu (5 abilities) and Content Search (11 abilities). Introduces two option-backed data stores: a lifecycle event log for plugin/theme activate/deactivate/update timestamps, and an internal-link suggestion queue capped at 500 entries. Zero new REST endpoints, zero new capability requirements beyond the operation-specific caps already enforced by WP core (moderate_comments, upload_files, edit_others_posts). Zero external HTTP; zero new database tables. No breaking changes to existing abilities. Safe upgrade.

= 0.0.12 =
Adds a third ability to the Core tab — `wp-core-rollback` — that rolls back WordPress core to an earlier version via WP core's `Core_Upgrader::upgrade()`, the same class the dashboard uses for forward updates. Requires both `manage_options` and `update_core`; honours `DISALLOW_FILE_MODS`; refuses when the target version isn't strictly older than the currently-installed version. Introduces the plugin's first outbound HTTP request (to `api.wordpress.org/core/version-check/1.7/`), rate-bounded to at most one request per day per locale per site via a site-transient cache. No breaking changes; no database, REST, or capability changes to existing abilities. Safe upgrade.

= 0.0.11 =
Adds two WordPress-core-scoped abilities under a new "Core" tab in the Ability Library — `wp-core-update-check` (report availability) and `wp-core-update` (apply via `Core_Upgrader`). The update ability requires both `manage_options` and `update_core`; honours `DISALLOW_FILE_MODS`; multisite-guarded. Also changes backup filenames from `backup-{type}-{slug}-{random}.zip` to `{slug}-{unix-timestamp}-{ms}.zip` — human-readable and time-sortable, but predictable (directory listing remains disabled on the backups dir). Existing backups continue to work; the filename change only affects new backups. No breaking changes; no database, REST, or capability changes to existing abilities. Safe upgrade.

= 0.0.10 =
Bugfix release. `Create_Zip_Backup` with `include_hidden=false` was silently descending into hidden directories and archiving their contents in 0.0.9 (only the top-level `.git/` etc. entry was skipped, not the files beneath it). Fixed to check every segment of each entry's relative path. Regenerate any `include_hidden=false` archives created on 0.0.9 if their source tree contained hidden directories. No breaking changes; no database, REST, or capability changes. Safe upgrade.

= 0.0.9 =
Adds eight new abilities: six under FileManager for zip-based backup / restore workflows (`zip-create`, `zip-upload`, `zip-extract`, `zip-download`, `zip-list`, `zip-delete`) plus `plugin-update` and `theme-update` that finally let AI clients apply pending WordPress core updates through the Abilities API. All new abilities enforce `manage_options`; mutating abilities additionally honour `DISALLOW_FILE_MODS`. Zip extraction rejects zip-slip archives (any entry containing `..`, an absolute path, a backslash, or a null byte). Zip uploads are validated for the `PK` magic signature before finalization. A new `wp-content/uploads/acrossai-backups/` directory is created on first use, hardened with an `.htaccess` that blocks PHP execution but permits `.zip` downloads (required so the URLs returned by `zip-create` remain reachable). No breaking changes to existing abilities, REST endpoints, capability requirements, or database schema. Safe upgrade.

= 0.0.8 =
IMPORTANT: this release **removes the Freemius integration entirely** — the plugin no longer sends any data to Freemius and no longer offers a Connect / Login / Buy affordance on the Add-ons page. If you previously connected a Freemius account tied to this plugin, that connection is now inert; stale `fs_*` or `freemius_*` rows in `wp_options` are safe to delete manually. Also: the Add-ons page now shows only free WordPress.org companion plugins (and no longer lists this plugin itself); the Library page compacts its title + bulk-action buttons onto one horizontal row; and `acrossai-co/main-menu` bumps `0.0.14 → 0.0.23`. No breaking changes to REST endpoints, capability requirements, or database schema. Safe upgrade.

= 0.0.7 =
Adds Library page bulk Enable All / Disable All buttons scoped to the active tab, URL-synced tabs (`?tab=<slug>`) for deep-linkable views, and a readonly ability preview on disabled cards. No breaking changes; no database schema changes; no new REST endpoints; no new capability requirements. `mode` and per-slug selections are preserved through disable / enable cycles. Safe upgrade.

= 0.0.6 =
IMPORTANT: this release absorbs the companion `acrossai-core-abilities` plugin — deactivate and uninstall that plugin after upgrading to avoid duplicate ability registrations. BREAKING for downstream integrators: 17 category slugs rebranded `acrossai-core-abilities-<domain>` → `acrossai-abilities-manager-<domain>` and 176 ability slugs `acrossai-core-abilities/<verb>` → `acrossai/<verb>`; update any MCP/REST/WP-CLI callers that referenced the legacy slugs. Ability payload shapes and permission callbacks unchanged. Also promotes Themes / Blocks / Plugins / Users / Database / Cron / Cache / File Manager to their own Library page tabs, bumps `acrossai-co/main-menu` to `0.0.14`, and rotates Freemius credentials.

= 0.0.5 =
Dependency-only release: refreshes the bundled `acrossai-co/main-menu` package to `0.0.11`. No functional changes to this plugin. Safe upgrade.

= 0.0.4 =
IMPORTANT for add-on developers: Library display fields `sub_group`, `sub_group_label`, and `tab_group` must now be nested under `$args['meta']['acrossai']` when calling `wp_register_ability()`. The old top-level shape is silently dropped — cards will render without their sub-group heading or custom tab placement until you migrate. End users and site administrators are not affected; no data migration, no DB or REST changes. Also swaps the WordPress.org plugin icon to an SVG and drops the directory banners.

= 0.0.3 =
Fixes the 0.0.2 activation error on WordPress.org installs — the release ZIP now includes the Composer autoloader. No functional or user-facing changes vs 0.0.2. If you hit the "Composer autoloader is missing" error on 0.0.2, delete the plugin folder and reinstall 0.0.3.

= 0.0.2 =
IMPORTANT: (1) This release does NOT migrate Access Control rules from previous versions. If you had configured any Access Control rules on abilities, audit and reconfigure them after upgrading. Pre-existing rules remain in the database (in the orphaned `{prefix}wpb_access_control` table) but are no longer applied. (2) Ability execution logging has been removed — the Logs admin page is gone; ability-execution denials are no longer recorded by this plugin. Install a compatible logging plugin if you need this signal.

= 0.0.1 =
Initial release.
