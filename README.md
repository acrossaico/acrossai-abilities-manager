<div align="center">

<img src=".wordpress-org/banner-772x250.png" alt="AcrossAI Abilities Manager" width="100%" />

# AcrossAI Abilities Manager

### 357 ready-made WordPress abilities for Claude, ChatGPT and any AI agent — browse, override and control every one.

[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![Version](https://img.shields.io/badge/stable-0.0.41-2ea44f)](https://acrossai.co/changelog/acrossai-abilities-manager/)
[![License](https://img.shields.io/badge/license-GPLv2%2B-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Abilities](https://img.shields.io/badge/abilities-357%20→%20800%2B-ff6b35)](https://acrossai.co/abilities/)

[**Website**](https://acrossai.co/abilities-manager/) · [**Every ability, searchable**](https://acrossai.co/abilities/) · [**Integrations**](https://acrossai.co/integrations/) · [**Use cases**](https://acrossai.co/use-cases/) · [**Changelog**](https://acrossai.co/changelog/acrossai-abilities-manager/)

</div>

---

## What this is

An **ability** is a self-describing operation that WordPress 6.9's [Abilities API](https://make.wordpress.org/core/) lets a plugin register — something an AI assistant, a REST client or another plugin can discover and call. WordPress ships the API; almost nothing ships abilities.

**This plugin does — 357 on any site, with no configuration — rising to over 800 as it detects the plugins you already run.**

It is **free, GPL, and works on its own.** Pair it with an MCP server and your site becomes something Claude, ChatGPT, Cursor or any MCP-capable assistant can operate.

```
357 abilities out of the box  ·  800+ with your existing plugins  ·  ~half read-only  ·  only ~13% destructive
```

<div align="center">
<img src=".wordpress-org/screenshot-1.png" alt="The Abilities Manager admin page — searchable, sortable ability table" width="90%" />
</div>

---

## Table of contents

- [What your site can do, out of the box](#what-your-site-can-do-out-of-the-box)
- [A dozen tools, not 357](#a-dozen-tools-not-357)
- [Plugins you already run get their own toolset](#plugins-you-already-run-get-their-own-toolset)
- [More integrations with AcrossAI Pro](#more-integrations-with-acrossai-pro)
- [Nothing is wide open](#nothing-is-wide-open)
- [Full control over every ability](#full-control-over-every-ability)
- [Reproduce a plugin conflict without breaking the site](#reproduce-a-plugin-conflict-without-breaking-the-site)
- [Works with or without an MCP server](#works-with-or-without-an-mcp-server)
- [Installation](#installation)
- [Requirements](#requirements)
- [FAQ](#faq)
- [Screenshots](#screenshots)
- [License](#license)

---

## What your site can do, out of the box

| Area | What the abilities cover |
| --- | --- |
| 📝 **Content** | Posts, pages and any custom post type with meta and revisions; comments; media, categories and tags; and semantic search that proposes, reviews and applies internal links. |
| 🧱 **Blocks** | Edit a page's block tree without rewriting the page, build from patterns, generate sections and landing pages, and audit copy and design. |
| 🎨 **Appearance** | `theme.json` and global styles, site-editor templates and parts, menus, widget areas, fonts, and site title, logo and icon. |
| 👥 **Users** | Create and edit users, reset passwords, create roles, and grant or revoke individual capabilities. |
| ⚙️ **Configuration** | Any option including values nested inside serialised arrays, permalinks, and which admin screen a setting lives on. |
| 🗄️ **Database** | Schema and table sizes, index health, bloated autoloaded options, `EXPLAIN` on a slow query, table optimisation, and serialisation-safe search-and-replace. |
| 📂 **Files** | Browse, read, write and delete inside an administrator-defined allowlist; zip backups; `wp-config` constants; the debug log. |
| ⏰ **Cron** | Every scheduled task, the overdue ones, running one on demand, and whether WP-Cron is firing at all. |
| ⬆️ **Updates** | Plugins, themes and core; rollback; and verification against official checksums. |
| 🩺 **Diagnostics** | Site Health, maintenance mode, recent fatal errors, and un-pausing what WordPress auto-disabled. |
| ⚡ **Cache** | Transients, object cache and rewrite rules. |

---

## A dozen tools, not 357

An AI client is handed its tool list once, at connect time, and pays for it out of the model's context window on **every** conversation. Exposing 357 separate tools would flood it — most assistants degrade past a few dozen.

So your MCP server groups them into **toolsets**, and a toolset is a **single tool** answering three actions — the same three everywhere:

| Action | What it does |
| --- | --- |
| `discover` | List what the toolset holds |
| `info` | Read one ability's parameters |
| `execute` | Run it |

[AcrossAI MCP Manager](https://wordpress.org/plugins/acrossai-mcp-manager/) provides that layer; **this plugin provides the abilities.**

---

## Plugins you already run get their own toolset

Nineteen integrations ship with the plugin, each with its own page under [acrossai.co/integrations](https://acrossai.co/integrations/) — and each registers **only when that plugin is active**, so nothing appears for software you do not have, and each becomes one more dispatcher tool rather than a pile of loose ones.

| Integration | Abilities | What it covers |
| --- | :---: | --- |
| [Elementor](https://acrossai.co/integrations/elementor/) | 89 | Pages, templates, kits, global widgets, form submissions and its cache. *(A Pro subset needs Elementor Pro.)* |
| [Yoast SEO](https://acrossai.co/integrations/yoast-seo/) | 64 | Titles and meta, indexing, breadcrumbs, the knowledge graph, social defaults and schema. |
| [Rank Math](https://acrossai.co/integrations/rank-math/) | 61 | On-page and site-wide SEO, redirections, schema, analytics and settings. |
| [LiteSpeed Cache](https://acrossai.co/integrations/litespeed-cache/) | 61 | Purge by target, URL, post or taxonomy, and tune TTLs, exclusions and vary rules. |
| [WooCommerce](https://acrossai.co/integrations/woocommerce/) | 34 | Catalogue, prices, stock, orders, customers and store health. |
| [Contact Form 7](https://acrossai.co/integrations/contact-form-7/) | 25 | Forms, fields, both mail templates, tag validation and messages. |
| [WPCode](https://acrossai.co/integrations/wpcode/) | 24 | Snippets of every type, where each is inserted, and its conditional logic. |
| [CookieYes](https://acrossai.co/integrations/cookieyes/) | 22 | Declared cookies, consent categories, the banner, its languages and Google Consent Mode. |
| [The Events Calendar](https://acrossai.co/integrations/the-events-calendar/) | 18 | Find, create, reschedule and trash events; manage venues and organizers. |
| [Event Tickets](https://acrossai.co/integrations/event-tickets/) | 16 | Tickets, real capacity including shared pools, check-ins, orders and sales. |
| [Advanced Custom Fields](https://acrossai.co/integrations/advanced-custom-fields/) | 16 | Field groups, and post types and taxonomies registered through ACF. |
| Site Kit by Google | 14 | Search Console analytics, Analytics 4 reports, PageSpeed Insights and AdSense, plus what is actually connected. |
| [Loco Translate](https://acrossai.co/integrations/loco-translate/) | 14 | What can be translated, what is untranslated, and writing translations. |
| [All-in-One WP Migration](https://acrossai.co/integrations/all-in-one-wp-migration/) | 9 | What archives exist, how recent they are, and exporting or removing them. |
| [UpdraftPlus](https://acrossai.co/integrations/updraftplus/) | 8 | When a backup last ran, whether it worked, and what each set contains. |
| [WP Mail SMTP](https://acrossai.co/integrations/wp-mail-smtp/) | 4 | How the site sends mail, whether it can, and a real test send. |
| [Classic Editor](https://acrossai.co/integrations/classic-editor/) | 4 | The effective editor per post type, which layer decided it, and whether users may choose. |
| Akismet | — | Spam figures and checking a comment. *(These abilities come from Akismet itself.)* |
| WPForms | — | Read forms and statistics, create forms, change settings. *(Writes sit behind an admin switch, off by default.)* |

> The two backup integrations are deliberate exceptions: they register whether or not their plugin is installed, so *"is this site backed up?"* can be answered **"no, there is no backup plugin here"** rather than having no tool to answer it.

**Third-party developers can register a toolset of their own through a filter, without touching this plugin.**

---

## More integrations with AcrossAI Pro

The paid [AcrossAI Pro](https://acrossai.co/pricing/) add-on contributes **276 further abilities** through the same toolset mechanism, again only when the host plugin is active:

| Integration | Abilities | What it covers |
| --- | :---: | --- |
| MailerPress | 89 | Campaigns, contacts, lists, tags, templates, workflows and settings. |
| LearnDash | 74 | Courses, lessons, quizzes, enrolment, progress, groups and reporting, plus the Certificates, Notifications and WooCommerce add-ons. |
| BuddyBoss | 60 | Members, groups, activity, forums, messages, media, connections and moderation. |
| MailerPress Pro | 28 | Segments, custom fields, webhooks, embed keys and email templates. |
| GeoDirectory | 25 | Listings, locations, fields, pricing packages and directory pages. |

*Everything else on this page is free.*

---

## Nothing is wide open

Every ability runs **WordPress's own capability check for the calling user**, so reaching one through this plugin grants nobody anything they could not already do. On top of that:

- 🔵 Roughly **half the catalogue is annotated read-only** and only about **13% is flagged destructive** — a look-but-don't-touch surface is a matter of filtering, not trust.
- ✅ Higher-risk operations require an explicit **confirmation flag** before they run.
- 🧪 **Search-and-replace is a dry run** unless you say otherwise, and skips post GUIDs unless asked.
- 📁 File access is confined to an **administrator-defined path allowlist** — an empty write-allowlist means *"deny all writes"* — with a dangerous-extension blocklist and a maximum write size on top.
- 🔑 **Secrets are redacted** — database credentials and authentication salts are stripped out of file and debug-log reads.
- 🗄️ Database abilities **never accept a raw table name**; they work from a fixed allowlist of core tables.
- 🚫 Any ability can be **disallowed site-wide**, and one you turn off is **unregistered outright** rather than merely hidden.

---

## Full control over every ability

<div align="center">
<img src=".wordpress-org/screenshot-2.png" alt="The edit drawer — tri-state override controls for each ability field" width="90%" />
</div>

- **Browse all abilities** — a searchable, sortable, paginated table listing every registered ability with slug, provider, source and current status.
- **Toggle allow/disallow** — enable or disable any ability site-wide with a single click, saved instantly without a page reload.
- **Edit ability metadata** — override `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`, `mcp_type` and `mcp_servers` per ability with a tri-state **Yes / No / Inherit** control, and reset any of it to registry defaults in one click.
- **Bulk actions** — allow, disallow or reset up to 50 abilities at once.
- **Ability Library** — enable or disable add-on ability groups from a dedicated page, with **All / Specific** mode per group.
- **Add-ons page** — browse companion plugins from wp-admin; WordPress.org-hosted ones install and activate in place.

> Overrides live in their own table. **The ability registry is never modified** — only fields that differ from registry defaults are stored, so removing the plugin leaves it exactly as found.

---

## Reproduce a plugin conflict without breaking the site

**Debugging → Conflict Testing** toggles any plugin's *effective* active state **without ever writing to `wp_options.active_plugins`** — reproduce a conflict for one browser session, then restore the site exactly by clearing a single JSON file.

Seven abilities expose the same thing to a REST client or an AI assistant, so an assistant can bisect a conflict for you. Every activation is guarded by a WordPress-core-style sandbox probe, so a fatal-erroring plugin cannot leave the site where every page load dies — the override is refused instead.

---

## Works with or without an MCP server

Abilities are the capability layer, **not** the connection. Every one is registered through WordPress 6.9's own Abilities API with `show_in_rest`, so it is reachable over the REST API and callable by any plugin the moment you activate this one. Nothing here is proprietary, and nothing is bound to a particular transport.

| Consumer | How it reaches the abilities |
| --- | --- |
| [**AcrossAI MCP Manager**](https://wordpress.org/plugins/acrossai-mcp-manager/) | The free MCP server this plugin is built alongside. Turns the toolsets into MCP tools with per-server curation and access control. |
| **MCP Adapter** | The WordPress MCP Adapter exposes registered abilities as MCP tools. When it is active, this plugin also lists its servers in the ability edit panel. |
| **Any other consumer** | Another MCP server, a REST client, or a plugin calling the Abilities API directly. Abilities registered here are ordinary WordPress abilities, not a private format. |

---

## Installation

```text
1. Upload the `acrossai-abilities-manager` folder to /wp-content/plugins/
2. Activate the plugin through the Plugins menu in WordPress
3. Open "AcrossAI Abilities Manager" in the admin menu
```

**Quick Connect setup wizard** — on activation the plugin opens a short setup wizard once: how many abilities the site has, how to edit them, how to act on many at once, what they cover, and how to connect them to an AI assistant. It does not open on sites already running AcrossAI MCP Manager (which provides its own wizard), and it is re-runnable any time from **AcrossAI → Quick Connect**, the admin toolbar, the Plugins screen, or **Settings → Abilities**.

**Add-ons** — go to **AcrossAI → Add-ons** to browse companion plugins. All are free and hosted on WordPress.org; each card offers one-click Install / Activate / Deactivate via the standard WordPress plugin installer.

---

## Requirements

| Requirement | Version |
| --- | --- |
| WordPress | **6.9 or later** — the Abilities API arrived in 6.9, and this plugin registers nothing without it |
| PHP | **8.1 or later** |
| Other plugins | **None required** |

> **Multisite:** not supported — the plugin has not been tested on WordPress Multisite installations.

---

## FAQ

<details>
<summary><strong>Is this plugin free?</strong></summary>

Yes, entirely, and under GPL. There is no paid tier of this plugin and no feature is held back.
</details>

<details>
<summary><strong>What can an AI actually do once this is installed?</strong></summary>

357 abilities on any site — content, blocks, appearance, users, configuration, database, files, cron, cache, updates and diagnostics — rising to over 800 as it detects plugins such as WooCommerce, Elementor, Rank Math, Yoast SEO, ACF and LiteSpeed Cache. Abilities are the capability layer; connecting an AI assistant to them needs a transport.
</details>

<details>
<summary><strong>Why does my AI only see about a dozen tools when there are 357 abilities?</strong></summary>

That is deliberate, and it is what makes the catalogue usable. Your MCP server groups the abilities into toolsets, and each toolset is a single tool answering three actions — `discover`, `info`, `execute`. Exposing 357 separate tools would flood the model's context window, and most assistants degrade badly past a few dozen. Your AI reaches everything through those, drilling in only when it needs to.
</details>

<details>
<summary><strong>Can an AI break my site?</strong></summary>

It can only do what you allow. Every ability runs WordPress's own capability check for the calling user, so nothing here grants extra privilege. Roughly half the catalogue is read-only and only ~13% is flagged destructive; higher-risk operations require an explicit confirmation flag; search-and-replace defaults to a dry run; file access is confined to an administrator-defined path allowlist; and secrets such as database credentials and auth salts are stripped from file and log reads. Any ability you disallow is unregistered outright, not merely hidden.
</details>

<details>
<summary><strong>Do I need another plugin to use this with an AI assistant?</strong></summary>

For an AI client to reach these abilities over MCP, yes — you need an MCP server such as [AcrossAI MCP Manager](https://wordpress.org/plugins/acrossai-mcp-manager/), which is also free. This plugin works perfectly well without one: abilities are registered with `show_in_rest`, so they remain reachable over the WordPress REST API and callable by any plugin.
</details>

<details>
<summary><strong>Does removing the plugin leave anything behind?</strong></summary>

The WordPress ability registry is never modified, so deactivating returns it exactly as it was. Overrides you set live in the plugin's own table; abilities registered by this plugin simply stop being registered. Resetting an override deletes its row, and the ability inherits registry values again.
</details>

<details>
<summary><strong>Does this plugin make external HTTP requests?</strong></summary>

The plugin's own code makes no external HTTP requests. A few admin-only surfaces trigger external connections on behalf of an authenticated administrator — the Add-ons installer (`api.wordpress.org` / `downloads.wordpress.org` via WordPress core), the `core/rollback-wp-core` version check, a Calendly link on the Consultations page, and YouTube walkthroughs in the setup wizard. Full disclosure, including what data is transmitted, is in the **External Services** section of [`readme.txt`](readme.txt).
</details>

---

## Screenshots

| | |
| --- | --- |
| <img src=".wordpress-org/screenshot-1.png" alt="Searchable, sortable ability table" /> | <img src=".wordpress-org/screenshot-2.png" alt="Tri-state override controls" /> |
| **1.** The Abilities Manager admin page — searchable, sortable ability table. | **2.** The edit drawer — tri-state override controls for each ability field. |
| <img src=".wordpress-org/screenshot-3.png" alt="Bulk actions toolbar" /> | <img src=".wordpress-org/screenshot-4.png" alt="The Ability Library page" /> |
| **3.** Bulk actions toolbar for allow / disallow / reset across multiple abilities. | **4.** The Ability Library page — enable/disable add-on ability groups. |
| <img src=".wordpress-org/screenshot-5.png" alt="The Add-ons page" /> | <img src=".wordpress-org/screenshot-6.png" alt="Settings" /> |
| **5.** The Add-ons page — browse free companion plugins. | **6.** Settings — Display and Upload Media Abilities. |

---

## Learn more

- 🌐 **The plugin** — https://acrossai.co/abilities-manager/
- 🔎 **Every ability, searchable** — https://acrossai.co/abilities/ (one page per ability)
- 🧩 **Integrations** — https://acrossai.co/integrations/
- 💡 **Use cases** — https://acrossai.co/use-cases/ (real jobs done through an AI assistant, start to finish)
- 📜 **Full changelog** — https://acrossai.co/changelog/acrossai-abilities-manager/

---

## License

Released under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html). © AcrossAI.

<div align="center">

**Built on WordPress's Abilities API — no lock-in, no proprietary format.**

</div>

<!-- agents:project-docs:start -->
## Using agents in this repository

This repository uses `@agents-dev/cli` to keep MCP servers, skills, and instructions aligned across AI tools.

### Quick commands

```bash
agents status
agents mcp add <url-or-name>
agents mcp test --runtime
agents sync
agents sync --check
```

### One MCP setup for all tools

Add a server once in `.agents/agents.json`, then run `agents sync` to materialize it for enabled integrations.

### References

- MCP Protocol Docs: https://modelcontextprotocol.io
- MCP servers catalog: https://mcpservers.org
- Project examples: `docs/EXAMPLES.md`
<!-- agents:project-docs:end -->
