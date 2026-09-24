=== AcrossAI Abilities Manager ===
Contributors: raftaar1191
Donate link: https://github.com/acrosswp/acrossai-abilities-manager
Tags: abilities, mcp, access control, site management, ai
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.38
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

357 ready-made WordPress abilities across 14 toolsets, plus full control over every ability on your site — browse, override and bulk-manage.

== Description ==

AcrossAI Abilities Manager does two things. It **gives your site abilities**, and it **lets you control every ability the site has**.

= What it gives you =

On activation the plugin registers **357 abilities across 14 toolsets**, on any WordPress site, with no configuration. These are the operations an AI assistant, a REST client, or another plugin can discover and call through the WordPress Abilities API:

* **Content** — create and update posts, pages and any custom post type with their meta and revisions; moderate comments; manage the media library, categories and tags; run semantic search to find related content and propose, review and apply internal links.
* **Blocks** — read and surgically edit a page's block tree without rewriting the page, build from patterns, generate sections and landing pages, and audit copy and design.
* **Appearance** — theme.json and global styles, site-editor templates and template parts, navigation menus, widget areas, fonts, and site title, logo and icon.
* **Users** — create and edit users, reset passwords, create roles, and grant or revoke individual capabilities.
* **Configuration** — read and write any option including values nested inside serialised arrays, change permalinks, and walk the admin menu to find which screen a setting lives on.
* **Database** — inspect schema and table sizes, audit index health and bloated autoloaded options, EXPLAIN a slow query, optimise tables, or run a serialisation-safe search-and-replace.
* **Files** — browse, read, write and delete files inside an administrator-defined allowlist; take and extract zip backups; read and edit wp-config constants; read the debug log.
* **Cron** — see every scheduled task, spot the overdue ones, run one on demand, and prove whether WP-Cron is firing at all.
* **Updates** — search the WordPress.org directory, install and update plugins, themes and core, roll back, and verify files against official checksums.
* **Diagnostics** — Site Health, maintenance mode, recent fatal errors, un-pause what WordPress auto-disabled, and bisect a plugin conflict without ever writing `active_plugins`.
* **Cache** — transients, object cache and rewrite rules.

**Plugins you already run get their own toolsets**, registered only when that plugin is active: WooCommerce, Elementor (and Pro), Rank Math, Yoast SEO, LiteSpeed Cache, Contact Form 7, WPCode, CookieYes, WP Mail SMTP, The Events Calendar, Event Tickets, Loco Translate, Classic Editor, Advanced Custom Fields, Akismet, WPForms, UpdraftPlus and All-in-One WP Migration. With those detected, the catalogue rises to **over 800 abilities across 32 toolsets**.

= Nothing is wide open =

Every ability carries a WordPress capability check, and going through this plugin grants nobody anything they could not already do. Beyond that:

* Roughly **half the catalogue is annotated read-only**, and only about **13% is flagged destructive**, so building a look-but-don't-touch surface is a matter of filtering.
* Higher-risk operations require an explicit **confirmation flag** before they run.
* **Search-and-replace is a dry run** unless you deliberately say otherwise.
* File access is confined to an **administrator-defined path allowlist**, with an empty write-allowlist meaning "deny all writes".
* **Secrets are redacted** — database credentials and authentication salts are stripped out of file and debug-log reads.
* Any ability can be **disallowed site-wide**, and an ability turned off is unregistered rather than merely hidden.

= What it lets you control =

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

357 abilities across 14 toolsets on any site — content, blocks, appearance, users, configuration, database, files, cron, cache, updates and diagnostics — rising to over 800 across 32 toolsets as it detects plugins such as WooCommerce, Elementor, Rank Math, Yoast SEO, ACF and LiteSpeed Cache. Abilities are the capability layer; connecting an AI assistant to them needs a transport (see below).

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

**4. YouTube walkthrough videos (`youtube-nocookie.com`, `youtube.com`)**

*What it is:* The Quick Connect setup wizard embeds short walkthrough recordings that explain how to
edit an ability, how to use bulk actions, and how to reach abilities through the MCP Adapter's
default server. Embeds use YouTube's privacy-enhanced host, `www.youtube-nocookie.com`.

*When it is contacted:* Only on the wizard's own screens, and never anywhere else in wp-admin — the
wizard's assets are gated on the `quick-connect` request parameter and load on no other admin page.
On two of those screens the recording begins on its own, so YouTube is contacted when the screen
renders rather than on a click. On the remaining screens nothing is requested from YouTube until the
administrator presses play: a locally-hosted placeholder is shown first and the embed is inserted
only on that click.

*What data is transmitted:* Nothing by this plugin. Loading an embed causes the administrator's own
browser to send standard metadata to YouTube (IP address, User-Agent) together with a `Referer`
limited to the site's origin — the `strict-origin-when-cross-origin` referrer policy means the
wp-admin path and query string are never disclosed. The privacy-enhanced host does not set tracking
cookies unless playback begins. This plugin transmits no site content, user data, or ability data to
YouTube.

*Avoiding it entirely:* Every embed is paired with a plain external link, so the wizard remains
usable when the embed is blocked by connectivity, a privacy tool, or a regional restriction. The
wizard can also simply be skipped — it is optional and every screen offers Exit setup.

*Terms of service:* https://www.youtube.com/t/terms
*Privacy policy:* https://policies.google.com/privacy

**5. GitHub release page (`github.com`)**

*What it is:* MCP Adapter is distributed from GitHub rather than the WordPress plugin directory. The
wizard's adapter screen links to that project's latest release page so the administrator can
download the plugin archive.

*When it is contacted:* Never on page render. The screen shows a plain link; GitHub is contacted
only if the administrator clicks it, at which point their browser navigates to
`https://github.com/WordPress/mcp-adapter/releases/latest` in a new tab. The plugin performs no
HTTP request to GitHub and does not download or install anything from it.

*What data is transmitted:* Nothing by this plugin. Standard browser metadata only, as with any
external hyperlink.

*Terms of service:* https://docs.github.com/site-policy/github-terms/github-terms-of-service
*Privacy policy:* https://docs.github.com/site-policy/privacy-policies/github-privacy-statement

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

= 0.0.32 - 2026-08-28 =
**Release theme: token-efficient AI callers.** Two closely related shifts. First, response payloads shrink dramatically for the common "small edit" and "just tell me the block structure" intents. Second, ability descriptions gain author-declared hints pointing AI callers at cheaper sibling abilities when their intent maps to one. Every change is either strictly additive or opt-out only via an admin toggle — no ability's execute() behaviour changes.

**Token-efficiency default for six content writers.** `content/create-page`, `content/update-page`, `content/create-post`, `content/update-post`, `content/create-cpt-item`, `content/update-cpt-item` gain a new optional input `return_content:boolean, default:false`. When false (the default), the response's `page` / `post` / `item` object strips three large fields — `post_content`, `post_content_filtered`, `post_excerpt` — and adds `content_bytes:integer` so callers still see the saved payload size at a glance.

**Why.** A single-word edit on a ~97 KB page via `content/update-page` previously round-tripped ~60 K LLM tokens (the caller sent the whole new body and the ability echoed the same body back). With this default, the echo drops to ~0 tokens — the caller pays only for the upload it already had to make. Fine-grained edits via `blocks/update-post-block` remain ~10× cheaper still because they never touch the surrounding content.

**BREAKING for callers reading `response.page.post_content` (or `.post` / `.item` equivalents).** Existing callers that need the saved content back — e.g. to diff against what they sent — must pass `return_content:true` explicitly. The three stripped fields remain queryable via `content/get-page` / `content/get-post` after the write.

**Not affected.** Every other content ability (get / list / delete / block-tree operations / meta ops) is unchanged. The block-tree writers (`blocks/update-post-block`, `blocks/add-block`, etc.) already returned just the modified block, not the whole page — nothing to strip.

**Same default now applies to nine block-editor writers.** `blocks/create-block-pattern`, `blocks/create-block-template`, `blocks/update-block-template`, `blocks/create-block-template-part`, `blocks/update-block-template-part`, `blocks/create-block-style-variation`, `blocks/update-block-style-variation`, `blocks/create-global-style`, and `blocks/update-global-style` gain the same `return_content:boolean, default:false` input. Response objects (`pattern` / `template` / `part` / `variation` / `record`) strip the large `content` (pattern/template/template-part markup) or `data` (variation/theme.json JSON) field by default and add `content_bytes:integer`. For `Variation_Db::to_row` and `Global_Styles_Db::to_row`, the writers now pass the caller's `$return_content` through instead of hardcoding `true` — the helpers skip `decode_content()` when the payload isn't wanted (CPU saving on the hot path). BREAKING for callers reading `response.pattern.content`, `response.template.content`, `response.part.content`, `response.variation.data`, or `response.record.data` — pass `return_content:true` explicitly. `blocks/update-block-pattern` is unchanged — it already returned a lightweight location descriptor.

**New ability `blocks/outline-post-blocks`.** Returns a flat, depth-first index of a post's block tree — canonical path, block type, child count, byte size, and a short text preview — without any block content. Cheap way for an LLM caller to locate a block before editing it: `blocks/get-post-blocks` on a large page can be hundreds of kilobytes because it returns every block's full `innerHTML`; this ability returns kilobytes for the same post. Paths use the same raw-`parse_blocks()` index scheme as `Block_Tree`, so a path returned here is drop-in usable with `blocks/add-block`, `blocks/update-post-block`, and `blocks/remove-block`. Paths are positional — a write can re-serialize the post and shift raw indices — so the response includes `post_modified_gmt` for staleness detection; re-outline after each write rather than caching paths. Filters (`block_names`, `contains`, `max_text`, `depth`, `include_attrs`, `max_results`) compose. `contains` matches only within the extracted text preview (up to `max_text` characters); raise `max_text` for deeper substring searches. Whitespace nodes (`parse_blocks` entries with null `blockName`) are excluded from output but still consume index positions — same convention `Block_Tree` already uses. `readonly`, `idempotent`, `non-destructive`.

**New — Ability Suggestions framework (Feature 095).** Ability authors can now declare a small list of other abilities an AI caller might use instead — a token-saving hint mechanism mirroring Feature 088's `suggested_plugins()`. Each ability class can override a new protected method `suggested_abilities()` returning `array<int, array{slug: string, reason: string, saves?: string}>`; entries surface under `args.meta.acrossai.suggested_abilities` on `mcp-adapter-get-ability-info` (not on discover-abilities — details-only surface, avoids discovery bloat). Hints are strictly advisory — nothing about the original ability's execution changes. Four initial ability overrides ship in this release: `content/update-page`, `content/update-post`, `content/update-cpt-item` each suggest `blocks/outline-post-blocks` + `blocks/update-post-block` for narrow edits (~29K tokens saved on a 97 KB page); `blocks/get-post-blocks` suggests `blocks/outline-post-blocks` when only paths are needed (~28K tokens saved on the same page). New admin toggle "Disable ability suggestions" on the Abilities settings tab (between "Plugin Suggestions" and "Uninstall Settings") strips the field site-wide (option key `acrossai_disable_ability_suggestions`, default `0` = feature enabled). Uninstall cleans up the option when "delete all data" is on. An ability with no override produces a byte-identical payload to what it produced before Feature 095 — no schema drift, no phantom empty list.

**Ten more `suggested_abilities()` overrides added to the Feature 095 hint catalog.** `content/get-page`, `content/get-post`, `content/get-cpt-item` each hint that `blocks/outline-post-blocks` is far cheaper (~20×) when the caller only needs to locate a block or see the structure. `blocks/read-theme-json` hints that `blocks/get-style-guide` returns a normalized token summary (~5–8×) when the caller wants design tokens, not the raw spec. `options/list-options`, `media/list-media`, `users/list-users`, `blocks/list-block-templates`, `blocks/list-block-patterns`, `blocks/list-global-styles` each hint that their corresponding targeted-read siblings (`options/get-option`, `media/get-media`, `users/get-user`, `blocks/read-block-template`, `blocks/read-block-pattern`, `blocks/read-global-style`) are 5–30× cheaper when the caller already knows the identifier — list is for discovery, get/read is for retrieval.

= Earlier releases =

Every release before 0.0.32 is recorded in full in changelog.txt, shipped inside the plugin and readable at
https://github.com/acrossaico/acrossai-abilities-manager/blob/main/changelog.txt

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
