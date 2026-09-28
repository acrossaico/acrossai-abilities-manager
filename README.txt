=== AcrossAI Abilities Manager – WordPress Abilities for Claude, ChatGPT & Any AI Agent ===
Contributors: raftaar1191
Donate link: https://acrossai.co/abilities-manager/
Tags: abilities, ai assistant, chatgpt, claude, mcp
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.40
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

357 ready-made WordPress abilities across 14 toolsets, plus full control over every ability on your site — browse, override and bulk-manage.

== Description ==

**AcrossAI Abilities Manager gives your WordPress site 357 ready-made abilities, and gives you control over every one of them.**

An *ability* is a self-describing operation that WordPress 6.9's Abilities API lets a plugin register — something an AI assistant, a REST client or another plugin can discover and call. WordPress ships the API; almost nothing ships abilities. This plugin does: **357 across 14 toolsets on any site, with no configuration**, rising to **over 800 across 33 toolsets** as it detects the plugins you already run.

It is free, GPL, and works on its own. Pair it with an MCP server and your site becomes something Claude, ChatGPT, Cursor or any MCP-capable assistant can operate.

= What Your Site Can Do, Out of the Box =

* **Content** — posts, pages and any custom post type with meta and revisions; comments; media, categories and tags; and semantic search that proposes, reviews and applies internal links.
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

= Toolsets: 14 Tools, Not 357 =

An AI client is handed its tool list once, at connect time, and pays for it out of the model's context window on every conversation. Exposing 357 separate tools would flood it — most assistants degrade past a few dozen.

So abilities are grouped into **toolsets**, and a toolset is a **single tool** answering three actions: `discover` to list what it holds, `info` to read one ability's parameters, and `execute` to run it. Same three everywhere, so an assistant learns the pattern once. Your AI sees roughly fourteen tools and reaches all 357 through them.

= Plugins You Already Run Get Their Own Toolset =

Nineteen integrations ship with the plugin, each with its own page under https://acrossai.co/integrations/ — and each registers **only when that plugin is active**, so nothing appears for software you do not have, and each becomes one more dispatcher tool rather than a pile of loose ones.

* **[Elementor](https://acrossai.co/integrations/elementor/)** (89 abilities) — pages, templates, kits, global widgets, form submissions and its cache. A Pro subset needs Elementor Pro.
* **[Yoast SEO](https://acrossai.co/integrations/yoast-seo/)** (64) — titles and meta, indexing, breadcrumbs, the knowledge graph, social defaults and schema.
* **[Rank Math](https://acrossai.co/integrations/rank-math/)** (61) — on-page and site-wide SEO, redirections, schema, analytics and settings.
* **[LiteSpeed Cache](https://acrossai.co/integrations/litespeed-cache/)** (61) — purge by target, URL, post or taxonomy, and tune TTLs, exclusions and vary rules.
* **[WooCommerce](https://acrossai.co/integrations/woocommerce/)** (34) — catalogue, prices, stock, orders, customers and store health.
* **[Contact Form 7](https://acrossai.co/integrations/contact-form-7/)** (25) — forms, fields, both mail templates, tag validation and messages.
* **[WPCode](https://acrossai.co/integrations/wpcode/)** (24) — snippets of every type, where each is inserted, and its conditional logic.
* **[CookieYes](https://acrossai.co/integrations/cookieyes/)** (22) — declared cookies, consent categories, the banner, its languages and Google Consent Mode.
* **[The Events Calendar](https://acrossai.co/integrations/the-events-calendar/)** (18) — find, create, reschedule and trash events; manage venues and organizers.
* **[Event Tickets](https://acrossai.co/integrations/event-tickets/)** (16) — tickets, real capacity including shared pools, check-ins, orders and sales.
* **[Advanced Custom Fields](https://acrossai.co/integrations/advanced-custom-fields/)** (16) — field groups, and post types and taxonomies registered through ACF.
* **Site Kit by Google** (14) — Search Console analytics, Analytics 4 reports, PageSpeed Insights and AdSense, plus what is actually connected.
* **[Loco Translate](https://acrossai.co/integrations/loco-translate/)** (14) — what can be translated, what is untranslated, and writing translations.
* **[All-in-One WP Migration](https://acrossai.co/integrations/all-in-one-wp-migration/)** (9) — what archives exist, how recent they are, and exporting or removing them.
* **[UpdraftPlus](https://acrossai.co/integrations/updraftplus/)** (8) — when a backup last ran, whether it worked, and what each set contains.
* **[WP Mail SMTP](https://acrossai.co/integrations/wp-mail-smtp/)** (4) — how the site sends mail, whether it can, and a real test send.
* **[Classic Editor](https://acrossai.co/integrations/classic-editor/)** (4) — the effective editor per post type, which layer decided it, and whether users may choose.
* **Akismet** — spam figures and checking a comment. These abilities come from Akismet itself.
* **WPForms** — read forms and statistics, create forms, change settings. Writes sit behind an admin switch, off by default.

The two backup integrations are deliberate exceptions: they register whether or not their plugin is installed, so *"is this site backed up?"* can be answered **"no, there is no backup plugin here"** rather than having no tool to answer it.

Third-party developers can register a toolset of their own through a filter, without touching this plugin.

= More Integrations With AcrossAI Pro =

The paid [AcrossAI Pro](https://acrossai.co/pricing/) add-on contributes **276 further abilities** through the same toolset mechanism, again only when the host plugin is active:

* **MailerPress** (89 abilities) *(Pro)* — campaigns, contacts, lists, tags, templates, workflows and settings.
* **LearnDash** (74) *(Pro)* — courses, lessons, quizzes, enrolment, progress, groups and reporting, plus the Certificates, Notifications and WooCommerce add-ons.
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
* **Ability Library** — enable or disable add-on ability groups from a dedicated page, with All/Specific mode per group.
* **Add-ons page** — browse companion plugins from wp-admin; WordPress.org-hosted ones install and activate in place.

Overrides live in their own table. **The ability registry is never modified** — only fields that differ from registry defaults are stored, so removing the plugin leaves it exactly as found.

= Reproduce a Plugin Conflict Without Breaking the Site =

**Debugging → Conflict Testing** toggles any plugin's *effective* active state **without ever writing to `wp_options.active_plugins`** — reproduce a conflict for one browser session or a support call, then restore the site exactly by clearing a single JSON file.

Seven abilities expose the same thing to a REST client or an AI assistant, so an assistant can bisect a conflict on your behalf. Every activation is guarded by a WordPress-core-style sandbox probe, which means a fatal-erroring plugin can never leave the site in a state where every page load dies — the override is refused instead.

= Works With or Without an MCP Server =

Abilities are the capability layer, not the connection. They are registered with `show_in_rest`, so they are reachable over the REST API and callable by any plugin the moment you activate it.

To let an AI assistant reach them over the Model Context Protocol you add a transport — the free [AcrossAI MCP Manager](https://wordpress.org/plugins/acrossai-mcp-manager/) is the one this plugin is built alongside, and it turns the toolsets into MCP tools with per-server curation and access control. Any other MCP server that reads the Abilities API works too.

= Requirements =

* WordPress **6.9 or later** — the Abilities API arrived in 6.9, and this plugin registers nothing without it
* PHP **8.1 or later**
* No other plugin is required

= Where To Read More =

* **The plugin** — https://acrossai.co/abilities-manager/
* **Every ability, searchable** — https://acrossai.co/abilities/ — one page per ability, rather than a list in a readme.
* **Integrations** — https://acrossai.co/integrations/
* **Use cases** — https://acrossai.co/use-cases/ — real jobs done through an AI assistant, start to finish.
* **Full changelog** — https://acrossai.co/changelog/acrossai-abilities-manager/ — including releases trimmed from the Changelog here for length.

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

357 abilities across 14 toolsets on any site — content, blocks, appearance, users, configuration, database, files, cron, cache, updates and diagnostics — rising to over 800 across 33 toolsets as it detects plugins such as WooCommerce, Elementor, Rank Math, Yoast SEO, ACF and LiteSpeed Cache. Abilities are the capability layer; connecting an AI assistant to them needs a transport (see below).

= Why does my AI only see about 14 tools when there are 357 abilities? =

That is deliberate, and it is what makes the catalogue usable. Abilities are grouped into toolsets, and each toolset is a single tool answering three actions — `discover` to list what it holds, `info` to read one ability's parameters, `execute` to run it. Exposing 357 separate tools would flood the model's context window, and most assistants degrade badly past a few dozen. Your AI reaches everything through the fourteen, drilling in only when it needs to.

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

This plugin's own code makes no external HTTP requests. Each connection below is triggered by a specific admin-only action, and is disclosed per the WordPress.org plugin directory guidelines. In every case the plugin sends no site content, user data or ability data.

**1. Calendly (`calendly.com`)** — a third-party scheduling service.
*When:* never on render. The Consultations page loads no Calendly script, iframe, cookie or asset; Calendly is reached only if an administrator clicks "Book a Consultation", opening the booking page in a new tab.
*Data:* only the browser's standard metadata (IP, User-Agent, referrer) on that click. Anything typed into Calendly's own form is processed by Calendly; this plugin never intercepts or stores it.
*Note:* that page also references Google Fonts (`fonts.googleapis.com`), its only external asset.
*Terms:* https://calendly.com/pages/terms · *Privacy:* https://calendly.com/pages/privacy

**2. WordPress.org plugin directory (`api.wordpress.org`, `downloads.wordpress.org`)** — installs free companion plugins from the Add-ons page.
*When:* only when an administrator with `install_plugins` clicks Install on a card sourced from WordPress.org, always through core's own `plugins_api()` and `Plugin_Upgrader`. Add-ons hosted elsewhere render as plain links; nothing is requested from those vendors.
*Data:* core's standard plugin-API payload — site URL, WordPress version, PHP version, locale.
*Terms:* https://wordpress.org/about/terms/ · *Privacy:* https://wordpress.org/about/privacy/

**3. WordPress.org core version-check (`api.wordpress.org/core/version-check/1.7/`)**
*When:* only when an administrator invokes the `core/rollback-wp-core` ability and the local cache has expired — at most once per day, per locale, per site.
*Data:* core's standard version-check payload. Same terms and privacy policy as service 2.

**4. YouTube walkthrough videos (`youtube-nocookie.com`, `youtube.com`)** — short recordings embedded in the setup wizard, via YouTube's privacy-enhanced host.
*When:* only on the wizard's own screens, gated on the `quick-connect` parameter and loaded nowhere else in wp-admin. Two screens autoplay, so YouTube is contacted on render; the rest show a local placeholder and embed only when play is pressed.
*Data:* the browser's standard metadata plus a `Referer` limited to the site's origin, because a `strict-origin-when-cross-origin` policy keeps the wp-admin path private. No tracking cookies unless playback begins.
*Avoiding it:* every embed is paired with a plain link, and the wizard is optional — every screen offers Exit setup.
*Terms:* https://www.youtube.com/t/terms · *Privacy:* https://policies.google.com/privacy

**5. GitHub (`github.com`)** — the wizard links to MCP Adapter's latest release, which is distributed there rather than on WordPress.org.
*When:* never on render; only if an administrator clicks the link. The plugin makes no request to GitHub and downloads nothing.
*Data:* standard browser metadata only, as with any external link.
*Terms:* https://docs.github.com/site-policy/github-terms/github-terms-of-service · *Privacy:* https://docs.github.com/site-policy/privacy-policies/github-privacy-statement

== Privacy Policy ==

This plugin does not itself collect, store, or transmit any user data to any third party.

Several admin-only actions can cause external services to receive data — all are described in the External Services section above and are triggered only by an authenticated administrator:

* The AcrossAI → Consultations admin page displays a static call-to-action button. Merely loading the Consultations page sends no data to Calendly — no Calendly script, iframe, or asset is loaded inside wp-admin. If the administrator clicks the CTA button, their browser opens `calendly.com/acrossai/using-ai-in-wordpress` in a new tab, at which point standard browser metadata (IP, User-Agent, referrer) is sent to Calendly and Calendly's own privacy policy applies. If they then book a consultation on Calendly's site, information they enter into Calendly's form (name, email, meeting details) is transmitted to Calendly.
* Installing a WordPress.org-hosted add-on from the AcrossAI → Add-ons page contacts the WordPress.org plugin directory via WordPress core's own `plugins_api()` and `Plugin_Upgrader` (`api.wordpress.org` + `downloads.wordpress.org`). Add-ons distributed elsewhere (e.g. GitHub, Freemius) are rendered as external "Get add-on ↗" links that open the vendor's site in a new browser tab — the plugin itself does not download or install those add-ons, so no request is sent to the vendor's servers from wp-admin. If the administrator clicks the external link, their browser navigates directly to the vendor and standard browser metadata (IP, User-Agent, referrer) is sent to the vendor as with any external hyperlink.
* Invoking the `core/rollback-wp-core` ability contacts the WordPress.org core version-check API (a WordPress-core-hosted service) via the standard WordPress update API.

No data is sent to any external server without an explicit administrator action.

== Changelog ==

= Unreleased =

(nothing yet)

= 0.0.40 - 2026-09-28 =

* **Fixed: the plugin's Description was being truncated on WordPress.org.** Every import reported "The Description section is too long and was truncated. A maximum of 2,500 words is supported" — a warning only the plugin's committers can see, so the listing was silently losing its tail for anyone reading it. The cause was not obvious: the Description itself was well inside the limit at around 1,800 words, but WordPress.org folds sections it does not recognise into the Description, and this readme carries two of them — External Services and Privacy Policy, both required disclosures totalling another 750. Together they crossed the limit. The Description is now tightened to 2,372 effective words with every point kept, leaving room for the next few releases, and neither disclosure was touched.
* **The WordPress.org listing title now says what the plugin does.** It read "AcrossAI Abilities Manager", which tells a search engine nothing, while the sibling plugins carry a descriptive title. It is now "AcrossAI Abilities Manager – WordPress Abilities for Claude, ChatGPT & Any AI Agent". The name shown inside wp-admin is unchanged. The tags move from `abilities, mcp, access control, site management, ai` to `abilities, ai assistant, chatgpt, claude, mcp` — the terms people actually search, with `abilities` kept because it is the one word that distinguishes this plugin from an MCP server.
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

= 0.0.35 - 2026-09-21 =

* **The backup abilities are now two tabs, one per plugin.** `UpdraftPlus` and `All-in-One WP Migration` each get their own tab, their own toolset and their own abilities, the same way Elementor, Rank Math, WPCode and every other integration works. 0.0.34 shipped them as a single "Backups" tab that reached both plugins through a shared layer; that made two genuinely different plugins look interchangeable and turned every real difference into a flag you had to go and check.
* **Each suite now offers only what its plugin can actually do.** UpdraftPlus schedules backups and restores them, and stores no label - so it has no label ability. All-in-One labels its archives, and restoring belongs to their paid Unlimited Extension - so that ability asks the plugin and passes its own answer back, naming the manual import route, rather than refusing on its behalf.
* **Breaking: the `backups/*` abilities are gone.** They are replaced by `updraftplus/*` and `all-in-one/*`. Anything holding a `backups/` slug needs updating; there are no aliases. The suite was one release old.
* **Fixed: restoring never worked outside the admin screens.** The restore checked whether WordPress could write to the filesystem directly - the check that stops a restore dying half-way through - using a function WordPress only loads inside wp-admin. Every restore request therefore failed on that line before checking anything, whatever it was asked to do. This shipped in 0.0.34 and is fixed here.
* **The exposure check is shared and reports per plugin.** Whether the web server will hand out a backup archive has nothing to do with which plugin wrote it, so that logic exists once - but each tab now reports on its own storage rather than on everything at once.

= 0.0.34 - 2026-09-18 =

The largest release so far: 25 features, 19 new tabs and around 400 new abilities. The theme is reach and honesty - most of the popular plugins a site actually runs can now be driven directly, each behind this plugin's own permission floor, and every ability that cannot do something says why and names the route that works instead.

**Breaking changes**

* **Abilities now require administrator rights unless you say otherwise.** If anyone below administrator drives this site through an AI client - a shop manager running a store, for example - they lose access on update until an administrator grants it. Set a rule on the individual ability under User Access, or move the site-wide floor with the `acrossai_default_ability_capability` filter.
* **Why: every plugin chose its own lock, and nobody was checking them.** Measured across the abilities installed on one site: three registered with no permission check at all, two were open to any logged-in subscriber, and one that *writes content* was open at contributor level. This plugin now decides who may run an ability, whoever registered it.
* **Setting access used to be able to remove the lock.** Choosing "Everyone" on an ability replaced its built-in check with one that allowed anybody - an action that reads as tightening actually opened the door. Access rules now sit on top of a floor that cannot be removed by accident.

**New tabs**

* **Store (WooCommerce) - 34 abilities.** The catalogue, pricing, stock, orders, customers, coupons, tax, shipping and store settings, plus WooCommerce's own seven adopted into the same tab. Variable products can now be created at all, which WooCommerce's own abilities cannot do.
* **Backups - 9 abilities.** Whether this site can be recovered: what exists, when it last ran and whether it worked, whether the archives are reachable over HTTP, and taking, labelling, deleting or restoring one. Works with UpdraftPlus and All-in-One WP Migration through one set of abilities.
* **Yoast SEO - 64 abilities**, and Yoast's own two now have a home.
* **LiteSpeed Cache - 61 abilities.**
* **Contact Form 7 - 25 abilities**, and **WPForms'** own abilities now have a home with an off switch for form writing.
* **WPCode - 24 abilities**, adopting the five WPCode already had.
* **Cookie Consent - 22 abilities**, with an honest account gate rather than silent failure.
* **The Events Calendar - 18 abilities** and **Event Tickets - 16**, with capacity modelled and personal data gated.
* **Advanced Custom Fields - 16 abilities**, joining the existing ACF tab.
* **Translations - 14 abilities.**
* **Email Delivery - 4 abilities**, plus a home for the ones the mail plugin ships, and **Akismet's** own abilities adopted.
* **Classic Editor - 4 abilities** for what nothing else can reach.

**Safety**

* **Fixed: editing a WooCommerce product or order through the generic content tools silently corrupted the store.** Writing a price through `content/update-cpt-item` left the price the shop actually charges on the old value, and saving the product correctly afterwards did not repair it. Orders were worse: WooCommerce no longer keeps them in the posts table, so the write changed a row nothing reads and was later deleted. Both are now refused, naming the ability that does work.
* **A backup archive that anyone can download is a total compromise, and this now checks for it.** Both backup plugins drop a .htaccess to prevent it; on nginx, IIS and Caddy that file is never read, so the protection is present, looks correct, and does nothing.
* **Restoring says plainly that it cannot be undone**, and records what the site looked like beforehand so what was given up is visible.

**The abilities screen**

* **Integration tabs are now named after the plugin they drive**, and abilities registered by other plugins now belong to a toolset instead of vanishing into a catch-all.
* **One screen, one access model.** The registration gate is gone; tabs were regrouped into task groups, and deep links to retired tabs fall back to "All".
* **Fixed: WPCode's own five abilities were never actually adopted** into its tab - the prefix could not match.

For the complete detail of this release - all 156 entries - and the full history of every earlier release, see changelog.txt inside the plugin, or
https://github.com/acrossaico/acrossai-abilities-manager/blob/main/changelog.txt

= 0.0.33 - 2026-08-28 =
**Release theme: closing the cheap-edit loop.** A follow-up to 0.0.32 that closes the last two gaps between "locate a block cheaply" and "modify it cheaply". Two changes, both surgical and backwards-compatible.

**`return_content:false` default now covers the two block-tree writers.** `blocks/add-block` and `blocks/update-post-block` gain the same `return_content:{boolean, default:false}` input as the six content writers (PR #152) and nine block-editor writers (PR #153). When false (default), the response's `block` object strips its `innerHTML`, `innerContent`, and `innerBlocks` — leaving `blockName`, `attrs`, and `path` — and `content_bytes` reports the saved `innerHTML` size. Container blocks (columns, cover, group) previously echoed their entire innerBlocks subtree; now they don't unless the caller passes `return_content:true`. BREAKING for callers reading `response.block.innerHTML` on these two abilities — pass `return_content:true` explicitly. Every other block-tree read/write (mutate-block-tree, replace-block-text, remove-block, duplicate-block, move-block) already returned lightweight envelopes and is unchanged.

**`blocks/get-post-blocks` gains scoping inputs.** Three new optional inputs close the "read one block's markup" gap between `blocks/get-post-blocks` (full tree, full content) and `blocks/outline-post-blocks` (scoped but never returns content). `path: int[]` scopes the response to a subtree (uses the same raw parse_blocks() index scheme as add-block / update-post-block / remove-block, so returned paths interchange). `depth: integer` bounds descent below the subtree root (-1 unlimited, 0 subtree root only, N below). `include_html: boolean` (default true = backwards-compat) strips innerHTML + innerContent from every returned node when false. Backwards-compatible: existing callers passing only `post_id` see identical responses. An unresolvable `path` returns a standard error envelope with `error_code: invalid_path` naming which depth failed and how many blocks exist at that level.

= Earlier releases =

Every release before 0.0.33 is recorded in full at https://acrossai.co/changelog/acrossai-abilities-manager/ and in changelog.txt, shipped inside the plugin.

== Upgrade Notice ==

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
