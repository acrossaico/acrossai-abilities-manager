# 120 — Integrations Directory

Input brief for `/speckit-specify`. Research is complete; the two open decisions are at the end.

## Goal

An admin screen listing every supported integration — name, icon, short description,
category, product and a link to its acrossai.co page — sourced from acrossai.co so the
list stays current without a plugin release. Search and category filter. Installed and
active plugins marked. Degrades gracefully offline. Reads as information, not an advert.

acrossai.co is planned first, because the plugin cannot be built against a feed that does
not exist yet.

---

## Part 1 — acrossai.co

### What is already there (verified over the `acrossai` MCP connector)

**`integrations` post type** — 24 published posts. Registered through **ACF's UI**, not code
(`acf-post-type` CPT is present; `integrations` is one of its entries). `public`,
`show_in_rest` true, empty `rest_base` so it answers at `/wp-json/wp/v2/integrations`.
Supports `thumbnail`, `excerpt`, `custom-fields`, `page-attributes`.

Taxonomies on it: `integration_category` (hierarchical) and `support_type`.

ACF fields, confirmed from post 906 (Elementor):

| Field | Example |
|---|---|
| `plugin_website` | `https://elementor.com` |
| `wporg_slug` | `elementor` |
| `integration_status` | `available` |
| `headline` | `Elementor MCP: edit Elementor pages and templates from…` |
| `abilities` | newline list of `Group | description` pairs |
| `show_disclaimer` | `1` |
| `ability_category` | term link into `ability_category` (see loader.php) |

`post_title` is the name, `post_excerpt` the short description, the permalink the link out.

**`products` taxonomy** — ACF taxonomy, post 684. Its stored `object_type` is
`['changelogs']` **only**, yet the live taxonomy reports
`['changelogs', 'abilities', 'demo']`. So something already adds object types at runtime,
outside the ACF config. **Find that code first** — it is the precedent for how to attach
`integrations`, and answers the "new plugin or follow existing practice" question.

**Where the directory lives today** — the child theme:
`wp-content/themes/child-twenty-twenty-five/inc/integration-abilities/loader.php` (18 KB),
with `archive-integrations.html`, `single-integrations.html`,
`taxonomy-integration_category.html`, and a block + block-bindings source
`acrossai/integration-abilities`. Supporting one-offs sit in `wp-content/mu-plugins/`
(e.g. `acrossai-addon-cpt.php`, `acrossai-pretty-links-abilities/`).

So: **no new plugin is needed.** The established practice is child-theme `inc/` for
presentation and mu-plugins for data/infrastructure.

### The one thing missing — product on an integration

`loader.php` does not read a product from the integration. It **infers** one: it walks the
integration's child `abilities` posts, collects their `products` terms, and then

```php
case 'price':
    return isset( $data['products']['acrossai-pro'] ) ? 'AcrossAI Pro' : 'Free';
```

The same inference drives `cta_label`, `cta_hero_label`, `cta_url` and `product`. The
excerpts repeat it as prose — every one of the 24 ends in either
"Free with AcrossAI Abilities Manager." or "Requires AcrossAI Pro."

That is the gap. The product is real data, stated three ways, stored nowhere.

### Changes

1. **Attach `products` to `integrations`.** Not a new taxonomy — `products` exists, is
   hierarchical, REST-exposed at `wp/v2/products`, and already spans `changelogs`,
   `abilities` and `demo`. Add `integrations` the same way the runtime additions for
   `abilities`/`demo` are done (locate that first; fall back to the ACF UI's object-type
   list on taxonomy 684 if there is no code path).
2. **Backfill the 24 terms.** No hand entry required: the correct value is already
   recoverable from each excerpt and from `loader.php`'s inferred `price`.
3. **Read the term instead of inferring it.** `acrossai_iab_binding_value()` keys `price`,
   `product`, `cta_label`, `cta_hero_label`, `cta_url` read the integration's own term,
   falling back to today's inference while the backfill is incomplete. Behaviour on the
   website is unchanged; it stops being derived.
4. **A feed endpoint for the plugin** — below.

### Why a purpose-built endpoint, not `/wp/v2/integrations`

Core's route returns rendered content. These posts are 11–13 KB each, so 24 of them is
~280 KB of HTML to deliver six fields. It also has no shape guarantee we control.

Proposed: `GET https://acrossai.co/wp-json/acrossai/v1/integrations`

```json
{ "version": 1, "generated": "2026-10-22T00:00:00Z", "integrations": [
  { "slug": "elementor", "name": "Elementor", "description": "…",
    "category": "Page Builders", "product": "free", "wporg_slug": "elementor",
    "url": "https://acrossai.co/integrations/elementor/", "status": "available" } ] }
```

Read-only, anonymous, no parameters, cached server-side, with `ETag`/`Last-Modified` so the
plugin's revalidation is cheap. Home: a **mu-plugin**, not the theme — the theme can be
switched, and a published plugin depending on it should not break when that happens.

### Icons

No featured images are set (post 906 has no `_thumbnail_id`). acrossai.co already solves
this: `loader.php` exposes an `initials` binding key and renders initial badges. The plugin
does the same. This is what makes "load no remote images, all assets ship with the plugin"
free — there is nothing to ship.

---

## Part 2 — the plugin

Home: **AcrossAI → Integrations** (`admin.php?page=acrossai-integrations`,
`admin/Partials/Integrations_Menu.php`), a submenu directly after Abilities and before
Settings.

It was briefly a tab on the Settings page, which was the wrong shelf: a settings tab is
where you go to change a preference you already know you want, and this is a catalogue you
go to in order to find out what exists. Sitting after Abilities puts it next to the
question it follows on from — what the site can do, then what else it could do.

It subsumes the on/off list #263 put on that tab, joining remote catalogue data onto the
rows that were already there, so one screen answers both "what is supported" and "what is
on here":

| Column | Source |
|---|---|
| Name, description, category, product, link | acrossai.co feed |
| Installed / active | `get_plugins()` matched on `wporg_slug` |
| Ability count | `AcrossAI_Ability_Group::counts()` |
| Enabled | `AcrossAI_Integration_Settings::is_enabled()` |

**Fetch** — `wp_safe_remote_get()`, only while rendering this tab, only for an
authenticated `manage_options` admin. No query arguments, no headers and no body that
identify the site or the user. Short timeout; failure is not an error state.

**Cache** — one transient (~12 h), refreshed on a cache miss. Never fetched on a front-end
request, on cron, or on activation.

**Offline** — a bundled JSON copy of the feed ships with the plugin and is used when the
transient is empty and the fetch fails. A quiet inline notice says the list may be out of
date. The screen never renders empty and never shows an error.

**Untrusted input** — the feed is treated as hostile: every field normalised to an expected
scalar type, length-capped, category matched against a known set, `url` required to be an
`https://acrossai.co/` URL, array length capped, unknown keys dropped, and `esc_html()` /
`esc_url()` at every output site. No HTML from the feed is rendered.

**Not built** — installing, activating or configuring anything from this screen. Also
forbidden for a wp.org-hosted plugin by detailed guideline #8, and already precedent in
`AddonsPageRenderer`, which offers in-page install for wp.org sources only.

### readme.txt

The readme currently states, twice, that the plugin makes no outbound requests:

- line 211 — "The plugin's own code makes no external HTTP requests."
- line 229 — "This plugin's own code makes no external HTTP requests."

**This feature makes both false** and they must be rewritten — this is the plugin's first
server-side outbound request. The existing **External Services** section already lists five
services in a what / when / data / terms / privacy format; acrossai.co becomes the sixth,
in that same shape, naming the endpoint, the trigger (an admin opening this tab), the
payload (none), and links to the acrossai.co terms and privacy policy.

### Done when

- The screen matches the design at desktop and mobile admin widths.
- The list reflects acrossai.co/integrations, and degrades gracefully with no network.
- readme.txt discloses the service and no longer claims there are no outbound requests.
- Plugin Check passes with no new errors or warnings.

---

## Status

**Part 2 is built.** The screen is live at AcrossAI → Integrations, implemented from the
design's section 1 ("Built with @wordpress/components") — the second section of that file
is labelled "Earlier mock (custom styling)" and was not used.

| File | What |
|---|---|
| `includes/Modules/Library/AcrossAI_Integration_Directory.php` | the fetch, cache, backoff, bundled fallback and whitelist |
| `includes/Abilities/Rest/Integrations_Directory_Controller.php` | `GET /acrossai/v1/integrations`, `POST /acrossai/v1/integrations/{slug}` |
| `admin/Partials/Integrations_Menu.php` | the submenu page and the option's capability guard |
| `admin/Partials/Menu.php` | `reorder_submenu()` generalised; places Integrations after Abilities |
| `src/js/integrations/`, `src/scss/integrations/` | the screen |
| `assets/integrations.json` | bundled catalogue, 28 entries |
| `tests/phpunit/Modules/Library/Test_Integration_Directory.php` | 27 cases, 139 assertions |
| `README.txt` | the two "no external HTTP requests" claims rewritten; acrossai.co added as service 1 |

Two departures from the mock, each recorded in the source:

1. Blocked rows **link** to core's plugin-install and plugins screens rather than installing
   or activating in place. Out of scope per the request, and wp.org guideline 8.
2. The mock says turning an integration off "removes its abilities". It does not, and the
   screen does not say so: Feature 102 requires registered-and-refused. Verified live —
   Elementor's 106 abilities stay registered with the integration off.

**Part 1 is not built.** Until the endpoint exists the screen serves the bundled copy and
says so, which is the degraded path working as designed rather than a stopgap. The feed
shape it expects is the one above; `assets/integrations.json` is a working example of it.
