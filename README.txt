=== AcrossAI Abilities Manager – WordPress Abilities for Claude, ChatGPT & Any AI Agent ===
Contributors: raftaar1191
Donate link: https://acrossai.co/abilities-manager/
Tags: abilities, ai assistant, chatgpt, claude, mcp
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.0.43
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

357 ready-made WordPress abilities for Claude, ChatGPT and any AI agent — browse, override and control every one.

== Description ==

**AcrossAI Abilities Manager gives your WordPress site 357 ready-made abilities, and gives you control over every one of them.**

An *ability* is a self-describing operation that WordPress 6.9's Abilities API lets a plugin register — something an AI assistant, a REST client or another plugin can discover and call. WordPress ships the API; almost nothing ships abilities. This plugin does: **357 on any site, with no configuration**, rising to **over 800** as it detects the plugins you run.

It is free, GPL, and works on its own. Pair it with an MCP server and your site becomes something Claude, ChatGPT, Cursor or any MCP-capable assistant can operate.

= What Your Site Can Do, Out of the Box =

* **Content** — posts, pages and any custom post type with meta and revisions; comments; media, categories and tags; generating an image from a prompt straight into the Media Library (when an AI connector is configured); and semantic search that proposes, reviews and applies internal links.
* **Blocks** — edit a page's block tree without rewriting the page, build from patterns, generate sections and landing pages, and audit copy and design.
* **Appearance** — theme.json and global styles, site-editor templates and parts, menus, widget areas, fonts, and site title, logo and icon.
* **Users** — create and edit users, reset passwords, create roles, and grant or revoke individual capabilities.
* **Configuration** — any option including values nested inside serialised arrays, permalinks, and which admin screen a setting lives on.
* **Database** — schema and table sizes, index health, bloated autoloaded options, EXPLAIN on a slow query, table optimisation, and serialisation-safe search-and-replace.
* **Files** — browse, read, write and delete inside an administrator-defined allowlist; zip backups; wp-config constants; the debug log.
* **Cron** — every scheduled task, the overdue ones, running one on demand, and whether WP-Cron is firing at all.
* **Updates** — plugins, themes and core; rollback; and verification against official checksums.
* **Diagnostics** — Site Health, maintenance mode, recent fatal errors, and un-pausing what WordPress auto-disabled.
* **Cache** — transients, object cache and rewrite rules.

= A Dozen Tools, Not 357 =

An AI client is handed its tool list once, at connect time, and pays for it out of the context window on every conversation. Exposing 357 separate tools would flood it — most assistants degrade past a few dozen.

So your MCP server groups them into **toolsets**, and a toolset is a **single tool** answering three actions: `discover` to list what it holds, `info` to read one ability's parameters, and `execute` to run it — the same three everywhere. AcrossAI MCP Manager provides that layer; this plugin provides the abilities.

= Plugins You Already Run Get Their Own Toolset =

Twenty-one integrations ship with the plugin, each with its own page in the [integrations directory](https://acrossai.co/integrations/) — and each registers **only when that plugin is active**, so nothing appears for software you do not have, and each becomes one more dispatcher tool rather than a pile of loose ones. They are listed on the plugin's own **Integrations** screen (under AcrossAI), where a single switch turns any one's abilities on or off.

* **Elementor** (89 abilities) — pages, templates, kits, widgets, form submissions and cache; a subset needs Elementor Pro.
* **Yoast SEO** (64) — titles and meta, indexing, breadcrumbs, schema and social defaults.
* **Rank Math** (61) — SEO, redirections, schema, analytics and settings.
* **LiteSpeed Cache** (61) — purge by target, and tune TTLs, exclusions and vary rules.
* **WooCommerce** (34) — catalogue, prices, stock, orders, customers and store health.
* **Contact Form 7** (25) — forms, fields, mail templates, tag validation and messages.
* **WPCode** (24) — snippets, their insertion points and conditional logic.
* **CookieYes** (22) — cookies, consent categories, the banner and Consent Mode.
* **The Events Calendar** (18) — create, reschedule and trash events; venues and organizers.
* **Event Tickets** (16) — tickets, capacity, check-ins, orders and sales.
* **Advanced Custom Fields** (16) — field groups, plus post types and taxonomies registered through ACF.
* **Site Kit by Google** (14) — Search Console, Analytics 4, PageSpeed Insights and AdSense.
* **Loco Translate** (14) — what is untranslated, and writing translations.
* **All-in-One WP Migration** (9) — what archives exist, how recent, and exporting or removing them.
* **UpdraftPlus** (8) — when a backup last ran, whether it worked, and its contents.
* **Redirection** (8) — create, list, update and delete redirects; the 404 error log that shows which URLs need one; redirect groups; and Redirection's settings.
* **WP Mail SMTP** (4) — how the site sends mail, and a real test send.
* **Classic Editor** (4) — the effective editor per post type, and whether users may choose.
* **Query Monitor** (4) — profile a URL's slow and duplicated queries, outbound HTTP calls and PHP errors; the PHP, MySQL and WordPress environment; object-cache status; and turning Query Monitor on or off.
* **Akismet** — spam figures and checking a comment, from Akismet itself.
* **WPForms** — read forms and stats, create forms, change settings; writes behind an admin switch, off by default.

The two backup integrations are deliberate exceptions: they register whether or not their plugin is installed, so *"is this site backed up?"* can be answered **"no, there is no backup plugin here"** rather than having no tool to answer it.

Third-party developers can register a toolset of their own through a filter, without touching this plugin.

= More Integrations With AcrossAI Pro =

The paid [AcrossAI Pro](https://acrossai.co/pricing/) add-on contributes **276 further abilities** through the same toolset mechanism, again only when the host plugin is active:

* **MailerPress** (89 abilities) *(Pro)* — campaigns, contacts, lists, tags, templates, workflows and settings.
* **LearnDash** (74) *(Pro)* — courses, lessons, quizzes, enrolment, progress, groups and reporting.
* **BuddyBoss** (60) *(Pro)* — members, groups, activity, forums, messages, media, connections and moderation.
* **MailerPress Pro** (28) *(Pro)* — segments, custom fields, webhooks, embed keys and email templates.
* **GeoDirectory** (25) *(Pro)* — listings, locations, fields, pricing packages and directory pages.

Everything else on this page is free.

= Nothing Is Wide Open =

Every ability runs WordPress's own capability check for the calling user, so reaching one through this plugin grants nobody anything they could not already do. On top of that:

* Roughly **half the catalogue is annotated read-only** and only about **13% is flagged destructive**, so a look-but-don't-touch surface is a matter of filtering, not trust.
* Higher-risk operations require an explicit **confirmation flag** before they run.
* **Search-and-replace is a dry run** unless you say otherwise, and skips post GUIDs unless asked.
* File access is confined to an **administrator-defined path allowlist** — an empty write-allowlist means "deny all writes" — with a dangerous-extension blocklist and a maximum write size on top.
* **Secrets are redacted** — database credentials and authentication salts are stripped out of file and debug-log reads.
* Database abilities never accept a raw table name; they work from a fixed allowlist of core tables.
* Any ability can be **disallowed site-wide**, and one you turn off is unregistered outright rather than merely hidden.

= Full Control Over Every Ability =

* **Browse all abilities** — a searchable, sortable, paginated table listing every registered ability with slug, provider, source and current status.
* **Toggle allow/disallow** — enable or disable any ability site-wide with a single click, saved instantly without a page reload.
* **Edit ability metadata** — override `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`, `mcp_type` and `mcp_servers` per ability with a tri-state Yes / No / Inherit control, and reset any of it to registry defaults in one click.
* **Bulk actions** — allow, disallow or reset up to 50 abilities at once.
* **Integrations** — a dedicated screen listing every integration on the site, each with a single on/off switch that withdraws this plugin's abilities for it. Each row shows how many abilities it contributes.
* **Ability Library** — enable or disable add-on ability groups from a dedicated page, with All/Specific mode per group.
* **Add-ons page** — browse companion plugins from wp-admin; WordPress.org-hosted ones install and activate in place.

Overrides live in their own table. **The ability registry is never modified** — only fields that differ from registry defaults are stored, so removing the plugin leaves it exactly as found.

= Reproduce a Plugin Conflict Without Breaking the Site =

**Debugging → Conflict Testing** toggles any plugin's *effective* active state **without ever writing to `wp_options.active_plugins`** — reproduce a conflict for one browser session, then restore the site exactly by clearing a single JSON file.

Seven abilities expose the same thing to REST and AI clients, so an assistant can bisect a conflict for you. Each activation is guarded by a core-style sandbox probe, so a fatal-erroring plugin cannot take the site down — the override is refused instead.

= Works With or Without an MCP Server =

Abilities are the capability layer, not the connection. Every one is registered through WordPress 6.9's own Abilities API with `show_in_rest`, so it is reachable over REST and callable by any plugin the moment you activate this one. Nothing is proprietary or bound to a particular transport, so anything that reads the Abilities API can expose these abilities — there is no lock-in:

* **[AcrossAI MCP Manager](https://wordpress.org/plugins/acrossai-mcp-manager/)** — the free MCP server this plugin is built alongside, turning the toolsets into MCP tools with per-server curation and access control.
* **MCP Adapter** — exposes registered abilities as MCP tools; when active, this plugin also lists its servers in the ability edit panel.
* **Any other consumer** — another MCP server, a REST client, or a plugin calling the Abilities API directly. These are ordinary WordPress abilities, not a private format.

= Requirements =

* WordPress **6.9 or later** — the Abilities API arrived in 6.9, and this plugin registers nothing without it
* PHP **8.1 or later**
* No other plugin is required

= Where To Read More =

* **The plugin** — the [plugin home page](https://acrossai.co/abilities-manager/).
* **Every ability, searchable** — [one page per ability](https://acrossai.co/abilities/), rather than a list in a readme.
* **Integrations** — [the full integrations directory](https://acrossai.co/integrations/).
* **Use cases** — [real jobs done through an AI assistant](https://acrossai.co/use-cases/), start to finish.
* **Full changelog** — [the complete history](https://acrossai.co/changelog/acrossai-abilities-manager/), including releases trimmed from the Changelog here for length.

== Installation ==

1. Upload the `acrossai-abilities-manager` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **AcrossAI Abilities Manager** in the WordPress admin menu.

**Quick Connect setup wizard:**

On activation the plugin opens a short setup wizard once — how many abilities the site has, how to
edit them, how to act on many at once, what they cover, and how to connect them to an AI assistant.
It does not open on sites already running AcrossAI MCP Manager, which provides its own wizard.

The wizard is re-runnable at any time and is reachable from four places: **AcrossAI → Quick
Connect** in the sidebar, the **Quick Connect via AcrossAI** entry in the admin toolbar, the
**Quick Connect via AcrossAI** link on the Plugins screen, and a button under **Setup** on the
AcrossAI → Settings → Abilities tab. Those entries are hidden when AcrossAI MCP Manager is active,
to avoid two wizards competing for the same surfaces; the wizard itself stays reachable at
`/wp-admin/admin.php?page=acrossai-abilities-manager&quick-connect=1&step=1`.

**Add-ons:**

1. Go to **AcrossAI → Add-ons** to browse available companion plugins.
2. All add-ons are free and hosted on WordPress.org; each card offers a one-click Install / Activate / Deactivate action via the standard WordPress plugin installer.

== Frequently Asked Questions ==

= Is this plugin free? =

Yes, entirely, and under GPL. There is no paid tier of this plugin and no feature is held back.

= What can an AI actually do once this is installed? =

357 abilities on any site — content, blocks, appearance, users, configuration, database, files, cron, cache, updates and diagnostics — rising to over 800 as it detects plugins such as WooCommerce, Elementor, Rank Math, Yoast SEO, ACF and LiteSpeed Cache. Abilities are the capability layer; connecting an AI assistant to them needs a transport (see below).

= Why does my AI only see about a dozen tools when there are 357 abilities? =

That is deliberate, and it is what makes the catalogue usable. Your MCP server groups the abilities into toolsets, and each toolset is a single tool answering three actions — `discover` to list what it holds, `info` to read one ability's parameters, `execute` to run it. Exposing 357 separate tools would flood the model's context window, and most assistants degrade badly past a few dozen. Your AI reaches everything through those, drilling in only when it needs to.

= Does removing the plugin leave anything behind? =

The WordPress ability registry is never modified, so deactivating returns it exactly as it was. Overrides you set live in the plugin's own table; abilities registered by this plugin simply stop being registered.

= Do I need another plugin to use this with an AI assistant? =

For an AI client to reach these abilities over MCP, yes — you need an MCP server such as [AcrossAI MCP Manager](https://wordpress.org/plugins/acrossai-mcp-manager/), which is also free. This plugin works perfectly well without one: abilities are registered with `show_in_rest`, so they remain reachable over the WordPress REST API and callable by any plugin.

= Can an AI break my site? =

It can only do what you allow. Every ability runs WordPress's own capability check for the calling user, so nothing here grants extra privilege. Roughly half the catalogue is annotated read-only and only about 13% is flagged destructive; higher-risk operations require an explicit confirmation flag; search-and-replace defaults to a dry run; file access is confined to an administrator-defined path allowlist; and secrets such as database credentials and auth salts are stripped from file and log reads. Any ability you disallow is unregistered outright, not merely hidden.

= Does it need WordPress 6.9? =

Yes. The Abilities API arrived in WordPress 6.9, and this plugin registers nothing without it — the registration path is guarded, so an older site simply gets no abilities rather than an error.

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

Just one from the plugin's own code: when an administrator opens **AcrossAI → Integrations**, it fetches the list of supported integrations from `https://acrossai.co/wp-json/acrossai/v1/integrations` so the list stays current between releases. It carries no site, user or ability data, is cached, and falls back to a copy bundled with the plugin if acrossai.co can't be reached.

A few admin screens also load Google Fonts and images from `acrossai.co`, the setup wizard embeds YouTube walkthroughs, and some buttons link out to `acrossai.co` and the WordPress.org directory — all contacted by the administrator's browser, not your server. Full disclosure is in the **External Services** section of this readme.

== Screenshots ==

1. The Abilities Manager admin page — searchable, sortable ability table.
2. The edit drawer — tri-state override controls for each ability field.
3. Bulk actions toolbar for allow/disallow/reset across multiple abilities.
4. The Ability Library page — enable/disable add-on ability groups.
5. The Add-ons page — browse free companion plugins.
6. Settings — Display (abilities-per-page) and Upload Media Abilities (allowed-MIME list + Add file types).

== External Services ==

Two external connections, each triggered only by a logged-in administrator, and neither sends your site content, user data or ability data. Disclosed per the WordPress.org plugin directory guidelines:

**AcrossAI (`acrossai.co`)** — on the **AcrossAI → Integrations** screen the plugin fetches the list of supported integrations once (a cached GET to `https://acrossai.co/wp-json/acrossai/v1/integrations`), so the list stays current between releases. No site, user or ability data is sent, and if acrossai.co cannot be reached the screen falls back to a copy bundled with the plugin. AcrossAI admin screens also show images hosted on acrossai.co and link to acrossai.co (the **Consultations** page links to `https://acrossai.co/consultations/`); those are contacted by the browser, not the server.
*Terms:* https://acrossai.co/terms/ · *Privacy:* https://acrossai.co/privacy-policy/

**Google (`fonts.googleapis.com`, `youtube-nocookie.com`)** — AcrossAI admin screens use Google Fonts, and the optional setup wizard embeds short YouTube walkthrough videos via YouTube's privacy-enhanced host. Two wizard screens preload a muted clip (so YouTube is contacted when they render); the rest load only when you press play, and every embed is paired with a plain link. No tracking cookies unless a video plays.
*Terms:* https://policies.google.com/terms · *Privacy:* https://policies.google.com/privacy

== Privacy Policy ==

This plugin does not itself collect, store, or transmit any personal data.

A few admin-only actions can cause an external service to receive data — all described in the External Services section above, and triggered only by an authenticated administrator:

* Opening **AcrossAI → Integrations** fetches the supported-integrations list from `acrossai.co` (no site, user or ability data is sent).
* AcrossAI admin screens load Google Fonts and images hosted on `acrossai.co`, which receive standard browser metadata only. The **Consultations** page's button opens `acrossai.co/consultations/` in a new tab.
* The optional setup wizard embeds YouTube walkthroughs; YouTube is contacted when a preloaded clip renders or when you press play, and every embed is paired with a plain link.

No data is sent to any external server without an administrator action, and the plugin loads no external script into wp-admin.
== Changelog ==

= 0.0.44 - unreleased =

* **Fixed: the backup abilities were missing from every site that had no backup plugin active — which is exactly where they were meant to answer.** All seventeen UpdraftPlus and All-in-One WP Migration abilities are registered whether or not those plugins are installed, deliberately, so that `updraftplus/get-status` is there to tell you the site is not backed up. But the family they belong to was only declared when the plugin was detected, and WordPress discards an ability whose family it does not know about — without an error anyone would see. So on those sites all seventeen silently did not exist, and asking "is this site backed up?" got no answer at all rather than a no. The family is now declared unconditionally, matching the abilities themselves.
* **Added: a twelve-ability All in One SEO suite — the settings AIOSEO itself cannot write.** AIOSEO's own abilities read its settings and say so plainly: "settings cannot be modified via abilities — use the AIOSEO admin UI", and the tree they hand back is undocumented. So an assistant could describe your SEO configuration and change none of it. `aioseo/get-settings-panel` now returns one named panel — breadcrumbs, webmaster verification, RSS content, search appearance, the four sitemaps, social profiles and cards, SEO alerts, the advanced toggles — with every field's type, default and current value, and `aioseo/update-settings-panel` writes them through AIOSEO's own sanitiser, so a sitemap change regenerates the sitemap and a permalink change flushes rewrite rules exactly as the admin screens would. A field that does not belong to the panel rejects the whole write rather than applying half of it, and the response reports each field read back after the save, so a value AIOSEO reduced or refused is visible instead of assumed. `aioseo/get-object-settings` and `aioseo/update-object-settings` reach the other half — the title and description templates, schema type and robots directives that every post of a type or term of a taxonomy inherits — which lives in a second settings store nothing else touches.
* **Added: AIOSEO sitemap health, and the answer to "it is switched on, why does it 404?".** AIOSEO ships no sitemap ability at all. `aioseo/get-sitemap-health` reports whether the XML sitemap is on, its index URL, which post types and taxonomies are really included, whether the RSS, HTML and llms.txt sitemaps are on, whether AIOSEO is writing static files or building on request, and — unless you turn it off — fetches the index to confirm it is actually served and answers with XML. When something is wrong it names the likely cause rather than handing back a flag. `aioseo/regenerate-sitemap` rebuilds the static files after a settings change, and on a site that builds on request it says there is nothing to rebuild instead of reporting work it did not do.
* **Fixed before it shipped: a noindex that was stored and ignored.** All in One SEO ignores every per-post robots directive while that post's "use default settings" switch is on, and neither AIOSEO's own update ability nor an early version of ours turned it off — so asking to noindex a post stored the flag, reported success, and the page went on telling search engines exactly what it had before. `aioseo/bulk-update-post-seo` now turns that switch off when you set a directive and tells you it did, and a directive AIOSEO does not actually store on that path is refused outright instead of succeeding silently.
* **Added: AIOSEO settings backups, status, bulk post SEO and notification dismissal.** `aioseo/create-backup`, `aioseo/list-backups` and `aioseo/manage-backup` snapshot every AIOSEO setting and restore or delete one, which makes a bulk settings change reversible in a single call. `aioseo/get-status` reports the version and edition, every addon with whether it is installed, and every settings panel with whether your role may write it — AIOSEO keeps its own role matrix separate from WordPress roles, so this is what explains a refusal before you hit it. `aioseo/bulk-update-post-seo` writes the same SEO fields across a list of posts, where AIOSEO's own ability takes one at a time; each post goes through AIOSEO's own service, so a post you cannot edit is refused individually and reports why, and one failure does not abort the rest. `aioseo/dismiss-notification` clears a resolved alert, which AIOSEO can raise and list but not dismiss.
* **Added: All in One SEO gets its own toolset.** AIOSEO ships thirteen abilities of its own — reading and writing one post's SEO fields, listing posts missing a title or focus keyphrase, listing posts by TruSEO score, the cached site audit, active notifications, and full management of its custom robots.txt rules. Until now this plugin did not recognise them, so all thirteen were filed under "Other", had no toolset of their own, and an AI assistant asking for the SEO tools could not see them. They now appear together as an All in One SEO toolset and Integrations row, exactly like Rank Math's and Yoast's. The toolset description also names the two limits of AIOSEO's own set up front — its settings read has no write counterpart, and its audit returns the last stored scan rather than a fresh one — so an assistant does not plan work that cannot be done.
* **Two of our Rank Math abilities are retired, because Rank Math now ships better versions of them.** `rank-math/set-module-state` turned one module on or off; Rank Math's own `rank-math/set-module-status` does several in one call and checks dependencies and PRO availability first, while still refreshing the rewrite rules that a sitemap or llms.txt route needs — the one thing ours existed to guarantee. `rank-math/get-seo-analysis-results` read the stored SEO audit; Rank Math's own `rank-math/audit-site-seo` reads the same stored run when you pass `refresh: false`, and can re-run it or audit a single URL as well. Where Rank Math covers the ground itself, this plugin now steps back rather than shipping a second, narrower copy. Both replacements are in the same Rank Math toolset, so nothing moves.
* **Fixed: two Rank Math abilities were taking names Rank Math now uses for its own.** Rank Math started shipping abilities of its own, and two of them — `rank-math/get-settings` and `rank-math/get-sitemap-status` — had the same names as two of ours. WordPress gives a name to whichever plugin registers first, so one side lost its ability outright and a request written for one got the other's input format, with which side won depending on load order. Ours are renamed to `rank-math/get-settings-panel` and `rank-math/get-sitemap-health`, so both sets now work side by side and reach the same toolset. The new names also describe them better: one returns a single settings panel together with every field's type and allowed values, which is what the write abilities need and Rank Math's own snapshot does not carry; the other is a live health check that catches a sitemap which is configured correctly and still returns a 404. If you call either by its old name, update the name — nothing else about them changed.

= 0.0.43 - 2026-10-28 =

* **Added: generate an image from a prompt and add it straight to the Media Library.** The new `media/generate-image` ability takes a text prompt, generates an image through WordPress 7.0's built-in AI client — using whichever connector the site has configured for image generation — and adds the result to the Media Library in a single call, optionally attaching it to a post and setting it as the featured image. Until now an assistant could store an image it already had, but not make one. Because each call makes a paid AI request, it is gated behind a confirmation; it respects the Upload Media Abilities file-type allowlist, and on a site with no image-capable connector it returns a clear message rather than failing. The Upload Media Abilities settings section and the media abilities now point to it.
* **Added: manage URL redirects and triage 404s with the Redirection plugin.** When the Redirection plugin is active, eight new abilities let an AI assistant create, list, update and delete redirects, read the 404 error log to find the URLs a site is missing redirects for, read the redirect hit log, list redirect groups, and read Redirection's settings. Every redirect is created through Redirection's own model — with its URL matching, flags and cache flush — rather than a raw database write that would store a redirect that never fires. Deleting a redirect is irreversible and asks for confirmation, and the REST API token is never returned. It also closes a loop the plugin already opened: deleting a published post hands back a suggested redirect, and there is now an ability that creates it.
* **Added: profile any page with Query Monitor, in its own toolset.** Four new abilities let an AI assistant profile one URL and read what it actually did — its slow and duplicated database queries, outbound HTTP calls, PHP errors and the template that rendered it — plus the PHP, MySQL and WordPress environment, whether a persistent object cache is connected and its hit ratio, and turning Query Monitor's own output on or off. They form a dedicated Query Monitor toolset and Integrations row rather than being folded into Diagnostics. Requires the Query Monitor plugin; without it each ability returns a clear "not active" message rather than failing.

= 0.0.42 - 2026-10-26 =

* **Security: database write abilities can no longer be tricked into altering their own SQL.** `database/insert-db-row`, `database/update-db-rows` and `database/delete-db-rows` pass column names to `$wpdb`, which wraps them in backticks without escaping. A crafted column name containing a backtick could break out of that quoting — turning, for example, a targeted delete into a full-table one — and the call still reported success. Column names are now checked against an allowlist (`[A-Za-z0-9_]`) before any write, and a name outside it is refused with a clear message. Legitimate writes are unaffected.
* **Fixed: database row writes could corrupt a value and still report success.** `$wpdb` matches format specifiers (`%s`/`%d`/`%f`) to columns by position, in the order the data object's keys happen to arrive — and that order is not dependable once a request has crossed an AI client and the transport. A misaligned `%d` cast a non-numeric value to `0`, wrote it, and returned success. Formats are now validated against their values before the write (a numeric specifier on a non-numeric value is refused), and a column-keyed object form is accepted that cannot misalign.
* **Fixed: `content/update-post` silently dropped plugin meta such as Rank Math's SEO fields.** Updating a post with meta like `rank_math_title` or `rank_math_description` discarded it while reporting success, even though `content/create-post` and `content/update-post-meta` both write it. Update now writes those keys, refusing only genuinely-internal underscore-prefixed keys — and when it does refuse one, it says so in a per-key warning naming the key and the ability to use instead, instead of an empty warnings list beside a successful response.
* **Fixed: `admin-menu/list-admin-menu-pages` threw on every REST and MCP call.** It tried to build the admin menu by loading a core admin script that assumes the admin request lifecycle, which raised a fatal outside it. It now assembles the menu safely and reports when the result is partial (plugin and theme pages only, since WordPress's own entries are declared by a file that cannot be loaded here) rather than erroring.
* **Added: Integrations screen.** A dedicated screen under AcrossAI listing every integration this site has, each with a single on/off switch. Turning an integration off withdraws this plugin's abilities for it; a row shows how many abilities it contributes and, when switched off, how many the host plugin still provides on its own.
* **Added: use this site's abilities from Elementor's MCP server.** When Elementor's MCP server is enabled, a client connected to it can now reach the rest of the site — content, media, the database, files, users and every installed plugin's capability — through two tools (`toolset-wp-site` and a guide), without leaving Elementor's editor tools. The pair is host-neutral, built to extend to other hosts' MCP servers in future.
* **Added: Elementor's own abilities without switching on its MCP server.** Enabling the Elementor integration registers Elementor's 27 MCP abilities so they are usable through AcrossAI's servers, without turning on Elementor's site-wide MCP exposure — that switch stays the site owner's to set on Elementor's own screen.
* **Added: diagnostics for ability coverage.** New diagnostics report abilities that no toolset lists and abilities that match no grouping rule, and name the plugin that lost a slug collision — so a capability that has quietly fallen out of reach can be found.

= 0.0.41 - 2026-09-30 =

* **Fixed: Global Styles saved through the abilities were stored but never applied.** Every write to the database record (`blocks/create-global-style`, `blocks/update-global-style` with `source: "db"`) saved `settings` and `styles` and nothing else. WordPress only uses that record when it also carries `"isGlobalStylesUserThemeJSON": true` — `WP_Theme_JSON_Resolver::get_user_data()` discards the whole thing otherwise and falls back to the theme's `theme.json`, without logging anything. So a palette, a font, a layout width would be written, read back correctly, reported as `origin: "db"` and `effective: true`, and the site would go on rendering theme defaults. Adding the key by hand did not help either: it was rejected as an unknown top-level key, because the record was being validated against the list of keys a `theme.json` *file* may carry. Both flags — the marker and `version`, set to `WP_Theme_JSON::LATEST_SCHEMA` — are now stamped on the server for every DB write, including section writes, merges, replaces and migrations, so nothing has to be passed by the caller. The DB source is validated against the shape core actually reads from it (`version`, `isGlobalStylesUserThemeJSON`, `settings`, `styles`, `title`); `templateParts`, `customTemplates` and `patterns` are refused there by name, with the message saying they belong in a `theme.json` file and which ability writes one.
* **Fixed: a font stack containing double quotes wiped the entire Global Styles record, and still reported success.** `"fontFamily": "\"EB Garamond\", Georgia, serif"` is an ordinary value; saving it destroyed everything else in the record. The encoded JSON was handed to `wp_update_post()` unslashed, and core runs `wp_unslash()` over every field it is given, so each `\"` inside the JSON lost its backslash and the stored content stopped being JSON. It then decoded to null, which the read path reported as an *empty* record — so the next merge-write started from empty and saved only the section it was given, silently dropping the colors, layout and spacing saved minutes earlier. The ability reported `success: true` and a non-zero `content_bytes` throughout. Content is now slashed on the way in, exactly as core's own Global Styles REST controller does; every write is read back and decoded before it is called a success, and one that does not decode is rolled back to the previous content and returned as `success: false`. A merge into a record that does not decode is refused outright rather than treated as empty — that conversion is what turned one bad write into total loss. The same fix is applied to block style variation records, which are written through the same path and had the same defect.
* **Global Styles reads now say when WordPress is ignoring the record.** `read-global-style` reported `origin: "db"` and `list-global-styles` reported `effective: true` for records WordPress was not using at all. `origin` is now `"db:ignored"` for a record that is missing the marker or does not decode, with a warning naming the reason; `list-global-styles` reports `applied_by_wordpress` on every row and marks the `theme.json` copy the site is really serving as the effective one. Records written by earlier versions are repaired automatically by the next write, by a one-time upgrade routine that runs on activation and on the first request after updating, or on demand with `update-global-style` and `repair: true`, which adds the flags and changes nothing else. The upgrade routine touches only records whose content decodes and that carry core's `wp-global-styles-` naming; content that does not parse is left alone for hand-recovery, and block style variations are skipped, since they must not carry the marker.
* **Section writes no longer discard what they were given in silence.** A `section: "typography"` write carrying `styles.elements` saved the typography and dropped every heading, link and button style in the same call, reporting success. A `section: "blockStyles"` write carrying only `styles.elements` saved nothing at all and also reported success. Element styles now have a section of their own — `section: "elements"` — anything a section write does not save is named back in `warnings` as `ignored: styles.elements`, and a section write that would save nothing is refused with a message naming what was ignored and where it belongs.
* **`blocks/get-style-guide` now labels where each preset came from.** It listed theme and user presets for the same slug side by side with nothing to tell them apart, so an overridden colour read as a duplicate; and for fonts, font sizes, duotones and gradients it read the theme's presets only, so user presets were missing from the style guide entirely. Each preset now carries an `origin` of `theme` or `custom`, a slug appears once — the value the site actually renders — and where a user preset replaced a theme one it says so with `overrides`.

= 0.0.40 - 2026-09-28 =

* **Fixed: the plugin's Description was being truncated on WordPress.org.** Every import reported "The Description section is too long and was truncated. A maximum of 2,500 words is supported" — a warning only the plugin's committers can see, so the listing was silently losing its tail for anyone reading it. The cause was not obvious: the Description itself was well inside the limit at around 1,800 words, but WordPress.org folds sections it does not recognise into the Description, and this readme carries two of them — External Services and Privacy Policy, both required disclosures totalling another 750. Together they crossed the limit. The Description is now tightened to 2,372 effective words with every point kept, leaving room for the next few releases, and neither disclosure was touched.
* **The WordPress.org listing title now says what the plugin does.** It read "AcrossAI Abilities Manager", which tells a search engine nothing, while the sibling plugins carry a descriptive title. It is now "AcrossAI Abilities Manager – WordPress Abilities for Claude, ChatGPT & Any AI Agent". The name shown inside wp-admin is unchanged. The tags move from `abilities, mcp, access control, site management, ai` to `abilities, ai assistant, chatgpt, claude, mcp` — the terms people actually search, with `abilities` kept because it is the one word that distinguishes this plugin from an MCP server.
* **The listing no longer counts toolsets as this plugin's feature.** It led with "357 ready-made WordPress abilities across 14 toolsets" and explained the toolset dispatch model as something this plugin gives you. That attribution is wrong: AcrossAI MCP Manager owns the toolset layer now. The counts of abilities stay, because those are what this plugin ships; the counts of toolsets are gone, and the explanation of why an AI sees a dozen tools instead of hundreds now credits the MCP server for the grouping. The per-plugin toolsets — Elementor, Rank Math, Site Kit, UpdraftPlus, All-in-One and the rest — are still described here, because they still come from this plugin and MCP Manager deliberately does not carry them. Nothing about the code changed in this release.
* **Tested up to WordPress 7.1, and the plugin's own one-line description rewritten.** The header still described this as a way to "manage and customize the abilities of AcrossAI … tailor the AI's capabilities", which is what an AI settings panel does, not a plugin that ships 357 abilities. That line is what WordPress shows under the plugin name in wp-admin, so it now says what you actually get.
* **The listing now says plainly that this works with any Abilities API consumer, not just one MCP server.** Every ability here is registered through WordPress 6.9's own Abilities API, so anything that reads that API can expose them — AcrossAI MCP Manager, the WordPress MCP Adapter, another MCP server, a REST client, or a plugin calling the Abilities API directly. That was previously one clause at the end of a paragraph; it is now three named routes and an explicit statement that nothing here is proprietary or bound to a particular transport.
* **The integration list is shorter, because each entry now links to its own page.** Every integration gained a link in 0.0.39; the inline paragraph describing each one was then saying what the linked page says at length. Each is now a single line naming the area it covers.

Verified: the Description parses at 2,372 of 2,500 words with the two unrecognised sections folded in, as WordPress.org counts them.

= 0.0.39 - 2026-09-28 =

* **Fixed: asking All-in-One WP Migration for a backup told you to install UpdraftPlus.** The All-in-One abilities detect their plugin correctly, but when it was missing they reported the failure using UpdraftPlus's error code and UpdraftPlus's message. So calling `all-in-one/get-status` on a site without All-in-One named the wrong plugin — and following that advice installed the wrong plugin, after which the ability still failed. Worse for anything automated, both suites returned the same `updraftplus_missing` code, so a caller checking which plugin was absent could not tell them apart, which is the one question a typed error code exists to answer. All-in-One now returns `all_in_one_missing` and a message about its own archives and exports. Every guard in the plugin is now checked for a shared code, so this cannot recur quietly.
* **Rank Math SEO scores now record where they came from and when.** Rank Math stores a score as a bare number, so nothing distinguished a score its own browser analyzer wrote months ago from one an AI client worked out this morning — and "are these scores current?" had no answer. `rank-math/update-seo-scores` now stamps each score it writes with a timestamp and a source, and takes a new optional `source`: `agent` (the default, meaning an AI client graded the post against the rubric Rank Math's own `rank-math/analyze-post-content` hands out) or `rank-math-analyzer` (a number Rank Math's client-side analyzer produced). The two do not always agree, and saying which one you have is the point. `rank-math/audit-content-seo` reports both back as `seo_score_at` and `seo_score_source`. Scores Rank Math wrote itself report null for both, which is the honest answer rather than a guess. Nothing about the score itself changes, and the existing single-argument call still works.
* **`rank-math/audit-content-seo` can now audit an exact list of posts.** It could sweep a post type or search for text, but there was no way to ask about five specific posts — which is exactly what you need before and after changing them. Pass `post_ids` and it reports on those posts, in the order you gave, all in one response. Because naming ids means naming the posts, it also stops applying the post/page and published-only defaults that would quietly drop a draft or a custom post type and leave it looking like it did not exist, and it lists healthy posts alongside problem ones instead of returning a shorter list with no explanation. Passing `post_types`, `post_statuses` or `only_issues` yourself still overrides all of that, and a sweep with no `post_ids` behaves exactly as before.

Verified against Rank Math 1.0.278.
* **New: a Site Kit by Google toolset — 11 abilities under `site-kit/*`.** Site Kit connects a WordPress site to Search Console, Analytics 4, PageSpeed Insights, AdSense and Tag Manager, and until now none of that was reachable. `site-kit/get-status` reports whether Site Kit is set up, whether the WordPress user making the call has connected their own Google account, which account that is, and what to do next. `site-kit/list-modules`, `site-kit/get-module-settings`, `site-kit/set-module-state`, `site-kit/get-sharing-settings` and `site-kit/list-module-datapoints` cover the modules — which are on, which are fully configured, which Google property each points at, and who can see the data. `site-kit/get-search-analytics` reads Search Console clicks, impressions, CTR and position by query, page, country, device or date; `site-kit/get-analytics-report` runs GA4 reports; `site-kit/get-pagespeed-insights` runs Lighthouse; `site-kit/get-adsense-report` reads earnings. `site-kit/get-module-data` reaches any read datapoint the named abilities do not cover. Everything is read through Site Kit's own module clients, so no request shape is reimplemented and no Google credential is ever returned. The toolset appears only when Site Kit is active, and like Rank Math's it is not in a new MCP server's default set — add it by hand or reach it through Integrations.
* **Site Kit abilities say whose problem it is when there is no data.** Site Kit stores one Google token per WordPress user, so an administrator on a fully configured site can still see nothing because a colleague did the connecting. Four situations that all look like "no data" — Site Kit not set up, this user not connected, the module switched off, the module on but missing its property settings — each get their own error code and their own sentence naming who must do what. Search Console returning zero rows is reported as the ordinary result it usually is, with the two-day reporting lag named, rather than as a failure.
* **PageSpeed Insights returns a summary, not half a megabyte.** Google's raw Lighthouse response for one page measured 529 KB — 199 KB of it a base64 screenshot no assistant can display, and 315 KB of audit detail tables — which overflowed the reply before any of it could be read. `site-kit/get-pagespeed-insights` now returns the category scores as percentages, the Core Web Vitals, any real-user field data Google holds for the URL, and just the audits that failed. Pass `detail: "audits"` for every audit's score without its tables, or `detail: "full"` for Google's whole response. The screenshot is dropped at every level. The ability also now says up front that a run takes ten to sixty seconds and can outlast a client's request timeout.
* **Site Kit settings can now be changed, not just read.** The suite could report that a site sends its analytics to one Google property but could do nothing about it. `site-kit/update-module-settings` writes them: which Analytics 4 property and measurement ID the site reports to, which Search Console property it reads, which Tag Manager container it uses, whether each module places its snippet, and who is excluded from tracking. Send only the keys you want changed. A key the module does not have is named back to you rather than silently dropped, which is what Site Kit's own writer does and the reason a typo used to look like success. The response reports each key's old and new value read back after the write, because every module runs its own sanitiser and what you asked for is not always what got stored. Confirm-gated, since switching a snippet off stops measurement on the live site immediately.
* **Changing a Site Kit connection setting moves who owns the module — and now says so.** Site Kit silently reassigns a module's owner to whoever changes its property or account, and that owner's Google credentials are what serve the module's data to everyone reading a shared dashboard. The write reports when that happened instead of leaving it to be discovered. `ownerID` itself cannot be written by hand, and neither can any credential key — an ability that hides secrets on read should not let you set one.
* **New: read and change your Site Kit Key Metrics.** The row of tiles at the top of the Site Kit dashboard — new visitors, most popular content, top traffic source — was invisible here. `site-kit/get-key-metrics` reports which tiles the current user has chosen, whether the row is hidden, and who set it up for the site; `site-kit/update-key-metrics` changes them. The selection is stored per WordPress user, so both describe and change only the dashboard of the user making the call. The slug list lives in Site Kit's JavaScript and grows with ordinary releases, so an unrecognised tile is saved and flagged rather than refused — refusing would break on exactly the tiles a newer Site Kit just added. Not confirm-gated: it moves tiles on one admin screen and touches neither the site nor its measurement.

Verified against Site Kit by Google 1.188.0 on a live site with Search Console, Analytics 4, Tag Manager and PageSpeed Insights connected.
* **The 27 Elementor design audits now actually examine the page.** They were registered and callable, but the analysis inside each was never written: they returned a made-up score with no findings, wrapped in "Ran audit: …" and a claim to be grounded in Elementor's official documentation. On a test page built as four identical 50/50 sections carrying the same button, `audit-generic-layout-patterns` used to answer 100 out of 100. It now answers 46, and names the three reasons — four repeated 50/50 rows, every row splitting evenly, and a stock split hero opening the page. All 27 are implemented against a shared model of the document, so every audit means the same thing by a row, a lane and a ratio.
* **The eleven design fixes now change the page, and say exactly what they changed.** Each one is confirm-gated, writes through a single audited path, and returns every setting it touched with its previous value so a change can be put back by hand. Running one twice changes nothing the second time and does not re-save. The image-to-background conversion hides the original widget rather than deleting it, because that judgement is one you may want to reverse.
* **Fixed: `elementor/evaluate-design` failed on every call, on every site.** It returned two fields its own output schema did not declare, and the schema forbids undeclared fields, so it errored every time it was used — it had never worked. The same fault was then found in the individual audits, which return their evidence under a field the schema also did not declare. Both are fixed and both are now pinned by tests.
* **`elementor/evaluate-design` composes the audits it is supposed to.** Its registry was only ever filled in by the test suite, so on a real site it aggregated nothing — a separate fault from the skeletons, and one that fixing them would not have touched. Audits now enrol themselves, and the aggregate reports 16 running on a typical page. The mutating abilities deliberately do not enrol: an aggregate that rewrote the document as a side effect of being asked a question would be indefensible.
* **Fixed a false positive found by building a good page rather than a bad one.** The native-widget audit counted icon elements across a whole row, so the standard three-up feature grid — one icon box per column — was told to become an Icon List, which is a vertical list inside a single column and not the same thing at all. It now counts within each column. Advice that is wrong about correct work is how a tool teaches people to ignore it.
* **The aggregate score now says what it is.** It is a mean across audits, so audits finding nothing pull it up and a page with real problems can still read in the eighties. The response now carries that caveat and the lowest individual score alongside the average, so the number is read rather than trusted.

= 0.0.38 - 2026-09-22 =

* **Fixed: a toolset that happened to be empty disappeared from the tool list, and could never come back.** An AI assistant is handed its list of tools once, when it connects, and there is no way to hand it a new one. A toolset holding nothing was left off that list — so on a site where every ability already had a home, the Other toolset was missing, the Tools tab said fourteen while the assistant was served thirteen, and reconnecting did not help because it was still empty at that moment. The built-in toolsets are now always offered, empty or not; calling an empty one answers with a message rather than an error. Toolsets belonging to a specific plugin are unchanged: they still appear only when their plugin is there, and the Integrations toolset still reaches them either way.
* **Fixed: the Integrations toolset could disappear too — the one thing that should never have.** Integrations exists so an assistant can reach a plugin installed after it connected. Its contents come only from plugin toolsets, so on a site with none active it was empty and therefore absent, exactly when it was most needed. It is now always present.
* **Integrations and Other now say that their contents change.** Both fill and empty as plugins and themes are activated, so an assistant that asked once and remembered the answer was wrong from the next activation onwards with nothing to tell it. Their listings now carry a `volatile` marker and a note to ask again. An empty one says the emptiness is about this moment rather than settled.
* **Fixed: Contact Form 7 mail tags written inside angle brackets were silently deleted.** `From: [your-name] <[your-email]>` is how a From line is normally written in a plain-text mail body. Sanitising read `<[your-email]>` as an unknown HTML tag and removed it, taking the mail tag with it — the save reported success, and checking the template afterwards reported it *valid*, because the tag that would have been flagged was gone. The tag survives now. Sanitising itself is unchanged and still removes real HTML; only this one shape, a bare mail tag between angle brackets, is let through. Updating a mail template also now names any field whose stored content was altered, so nothing is dropped quietly again.
* **Fixed: a Contact Form 7 field option containing a space became several options.** Contact Form 7 splits a field tag on spaces, so `placeholder:+44 7700 900000` arrived as three separate options and the field ended up with a placeholder of `+44`. Option values are now quoted the way choice values always were. An option with a space and no `key:` in front of it cannot be repaired, so it is refused with the offending text named rather than silently mangled.
* **Fixed: the Contact Form 7 field-type list advertised syntax that does not work.** Contact Form 7 registers `text` and `text*` as separate types, and the list appended an asterisk to each — producing a duplicate row for every type and the string `text**`, which Contact Form 7 does not understand. There is now one row per type, showing a required form only where one genuinely exists: `submit` and the captcha fields have none. The list goes from 35 entries to 24.
* **Creating a Contact Form 7 form now accepts `template`, the name the other template abilities already use.** Creating a form called the markup `form` while reading and replacing it called the same thing `template`, so anything that learned one name was rejected by the next. `template` works everywhere now, and `form` still works for anything already using it.
* **Enabling a Contact Form 7 autoresponder now warns when it would send to nobody.** A form whose fields were rewritten keeps Contact Form 7's stock autoresponder, which is addressed to `[your-email]` — on a form without that field the reply goes nowhere, and the visitor still sees a success message. Switching the autoresponder on now reports any mail tag in it that matches no field, so the problem is visible at the moment it is created.

= 0.0.37 - 2026-09-21 =

* **Fixed: the UpdraftPlus and All-in-One WP Migration tools were offered on sites without those plugins.** Both appeared in an MCP server's tool picker, and in the set a new server starts with, whether or not the backup plugin was anywhere on the site. Every other per-plugin toolset — Elementor, Rank Math, LiteSpeed — already excluded itself; these two, added in 0.0.35, did not. The tools themselves were never broken: they are still registered when their plugin is active, still addable by hand, and still reachable through the Integrations toolset. What changes is that they are no longer part of what a new server is given by default. If a server already has one and the plugin is not installed, "Reset to Type Defaults" clears it.

= 0.0.36 - 2026-09-21 =

* **Listing posts no longer forces you to download every body.** `content/list-posts`, `content/list-pages` and `content/list-cpt-items` returned every field of every item including the whole post_content — ten posts came to about 172 KB when almost all of it was content nobody had asked for yet. Pass `fields: "summary"` to get just what identifies an item: title, status, dates, slug, author, a trimmed excerpt, and content_bytes so you can size the follow-up read. Measured at 36-39x smaller. The default is unchanged, so nothing existing sees a difference.
* **Fixed: the block outline reported the wrong total.** Asking for 3 blocks of a 40-block post reported `total: 3` — it counted what it returned rather than what matched, because it stopped walking the moment it had enough. It now reports `total: 40` with a new `returned: 3` alongside, so you can tell a small post from a truncated view of a large one. Each block also reports `subtree_bytes` next to `bytes`, which distinguishes a genuinely small block from a small wrapper around half the page.
* **Site Health results can now be read as text.** The description and actions fields carry WordPress core's own markup — paragraph tags, icon spans that render as pictures and read as nothing, and screen-reader spans that repeat every link's text. Pass `format: "text"` for plain sentences with the link destinations kept. The default still returns the markup unchanged.
* **Every ability now has to say whether it reads, destroys, or can be repeated.** Those three flags are how an AI client decides whether something is safe to try, safe to retry, and safe to run without asking, and a missing one reads as "not destructive" — the dangerous way to be wrong. Abilities missing them are now caught by the test suite, and reported on screen while `WP_DEBUG` is on. Nothing changes on a production site.
* **The transient and object-cache abilities now point at the page cache when there is one.** Clearing transients is not what a visitor sees. On a site running LiteSpeed, these abilities now suggest `litespeed/purge-cache` for that — and say nothing on sites without it, rather than naming an ability that is not there.

= Earlier releases =

Every release before 0.0.36 is recorded in full at https://acrossai.co/changelog/acrossai-abilities-manager/ and in changelog.txt, shipped inside the plugin.

== Upgrade Notice ==

= 0.0.43 =
Adds a Redirection toolset (create and manage redirects and triage 404s), gives Query Monitor its own toolset for profiling a page's queries, HTTP calls and environment, and a new ability that generates an image from a text prompt straight into the Media Library using WordPress's built-in AI client. No breaking changes.

= 0.0.42 =
Security and data-integrity fixes for the database write and post-update abilities, plus the Integrations screen and Elementor MCP support. Recommended for all sites.

= 0.0.41 =
Worth reading before you update if this site's Global Styles were ever set through the abilities rather than the Site Editor. Those records were being saved without the key WordPress requires before it will use them, so the styles were stored, reported back as active, and never applied - the site went on rendering theme defaults. This release adds the key, and a one-time routine on upgrade adds it to records written by earlier versions. The effect is that colours, fonts, spacing and layout widths that were saved months ago and never took effect will start rendering the moment you update. Nothing is changed in those records beyond the two keys, and records whose content is not valid JSON are left untouched for hand-recovery. Also fixes a font stack containing double quotes destroying the whole record while reporting success. No breaking changes to any ability's input.

= 0.0.37 =
Fixes the UpdraftPlus and All-in-One WP Migration tools being offered on sites without those plugins installed - they appeared in the tool picker and in what a new MCP server starts with, unlike every other per-plugin toolset. The tools themselves were never broken and still work exactly as before when their plugin is active. If a server already has one of them and the plugin is not installed, "Reset to Type Defaults" clears it. No other changes.

= 0.0.36 =
No breaking changes - every addition here is optional and defaults to what the plugin did before. Worth updating for three things. Listing posts, pages or custom post type items no longer forces the whole post body down the wire: pass fields: "summary" for titles, dates, slugs and a content size instead, measured 36x smaller on ten real posts. The block outline reported the wrong total when truncated - asking for 3 blocks of a 40-block post said "total: 3" - which is now the true match count with a separate "returned" alongside. And Site Health results can be read as plain text with format: "text" rather than WordPress core's own markup. Also adds a check that every ability declares whether it reads, destroys or can be repeated, since an AI client treats a missing flag as "not destructive".

= 0.0.35 =
BREAKING - the `backups/*` abilities added in 0.0.34 are replaced by `updraftplus/*` and `all-in-one/*`. Anything holding a `backups/` slug needs updating; there are no aliases. The backup abilities are now two tabs, one per plugin, matching how every other integration works: each offers only what its plugin can actually do rather than advertising everything and reporting absence when you try to use it. Also fixes restoring, which never worked outside the admin screens in 0.0.34 - it checked the filesystem using a function WordPress only loads inside wp-admin, so every restore request failed on that line before checking anything. If you rely on restoring from UpdraftPlus through this plugin, this release is the one that makes it work. Nothing else changes; existing abilities, overrides and access rules are unaffected.

= 0.0.34 =
BREAKING - abilities now require administrator rights unless a rule says otherwise. If anyone below administrator drives this site through an AI client, they lose access on update until an administrator grants it: set a rule on the individual ability under User Access, or move the site-wide floor with the `acrossai_default_ability_capability` filter. This closes a real hole - measured on one site, three installed abilities had no permission check at all, two were open to any logged-in subscriber, and one that writes content was open at contributor level. Otherwise additive: 19 new tabs and around 400 new abilities, including WooCommerce, backups, Yoast SEO, LiteSpeed Cache and Contact Form 7. Existing abilities, overrides and access rules are unaffected.

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
