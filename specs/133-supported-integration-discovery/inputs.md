# Feature 133 — let an AI client recommend a plugin we already integrate with

> Feature number provisional — let `/speckit.specify` assign the next free one (132 is AIOSEO).

**The scenario.** A user tells an assistant "I want to do SEO on this site." Today the assistant can
only see what is installed. If the site has no SEO plugin it has nothing to say, and if it suggests
one it will suggest whatever it knows from training — which is very likely a plugin this manager has
no integration for. It should instead answer: "this site supports Yoast, Rank Math and All in One
SEO; none is installed; which would you like?" — and then install the one the user picks.

## Most of this already exists, which changes the shape of the work

Researched against the 0.0.44 tree. Three of the four pieces are built:

| Piece | Where | State |
|---|---|---|
| The catalogue — name, description, **category**, free/pro, `wporg_slug`, docs URL, ability-count hint | `AcrossAI_Integration_Directory` | **Done.** 28 entries. Categories are already "SEO", "Forms", "Backup and Migration", … |
| The join of catalogue onto local truth, including **"supported but not available here yet"** | `Rest\Integrations_Directory_Controller::get_directory()` | **Done**, and it already sorts by category then name |
| Installing a chosen plugin | the existing `plugins/*` abilities | **Done** — no new install code needed |
| Reaching any of it from an AI client | — | **Missing.** This is the feature. |

So this is a plumbing and exposure job, not a new data model. In particular: do **not** add a
category or `wporg_slug` method to `AcrossAI_Toolset_Integration` and then edit twenty integration
files. That data is catalogue copy, it lives in the catalogue, and the reason it is fetched rather
than hardcoded is written at the top of `AcrossAI_Integration_Directory`.

### Why the client cannot see it today

Both local enumerators drop exactly the rows this feature needs:

- `Toolset\Integrations::discover()` — `if ( 0 === $count ) { continue; }`
- `AcrossAI_Integration_Settings::from_toolset_integrations()` — skips `! $integration->is_active()`
  and then `0 === $count` as well

Both are right for their own purpose: a dispatcher that lists abilities you can run should not list
plugins that contribute none. Which is why the answer is a separate ability rather than loosening
either of them.

## Scope

**One read-only ability.** Working name `integrations/list-supported`. Returns the joined directory,
grouped by category:

- `category` — the grouping the user's question maps onto ("SEO")
- `name`, `description` — catalogue copy
- `slug`, `wporg_slug` — the second is what `plugins/install-plugin` needs
- `installed`, `active` — local truth, never catalogue opinion
- `abilities` — the live registry count when the plugin is here; the catalogue's hint when it is not
- `product` — free or pro, so the assistant does not recommend a paid add-on as if it were free
- `url` — the integration's page on acrossai.co
- `catalogue_stale` on the envelope — true when the bundled copy was used

Optional `category` input to narrow it, because "SEO" is the common case and returning all 28 rows
for it is noise.

**It must be in the server type's default set.** The whole point is that it works for a client whose
tool list was fixed before anything was installed — the same reasoning that puts
`toolset/integrations` in the default set, documented at the top of `Toolset\Integrations`.

**One clause added to the connect-time server instructions**, naming the ability. Do **not** put the
catalogue itself in the instructions: that string is sent to every client on every connect, and
twenty-eight plugins across eighteen categories would bloat every connection and go stale between
releases. One sentence pointing at the ability is the whole change.

**Choosing stays with the user.** The ability returns candidates; the description must tell the
assistant to present them and let the user pick. Installing is a separate, already-confirm-gated
call to `plugins/install-plugin`. Nothing here installs anything.

## Constraints — traps, not preferences

- **The ability must never trigger the outbound fetch.** `AcrossAI_Integration_Directory::get()`
  performs an HTTP request to acrossai.co when no transient is cached, and its own docblock limits
  that to "an administrator opens the screen. Never on a front-end request, never on cron, never on
  activation". An MCP tool call is none of those. Add a cache-or-bundled accessor that cannot fetch,
  and have the ability use only that. Otherwise the first tool call on a cold cache pays an
  outbound request — and today, a guaranteed-failing one (see below).
- **Local truth wins over the catalogue, in both directions.** A catalogue entry must never invent an
  integration the site does not have, nor claim one is active when it is not. The REST controller's
  local-first ordering already encodes this; reuse the reasoning rather than re-deriving it.
- **An integration newer than the catalogue must still appear.** It will have local state and no
  catalogue entry, which is exactly the AIOSEO case right now.
- **Read-only, idempotent, no confirmation.** It reads two caches and a registry.

## The feed: resolved — use the CPT route, not the purpose-built one

`acrossai/v1/integrations` returns 403 for everybody, and **it cannot be fixed by permissions**: that
path is *this plugin's own admin route* running on acrossai.co
(`Integrations_Directory_Controller::REST_BASE = 'integrations'` under
`AcrossAI_Abilities_Rest_Controller::REST_NAMESPACE = 'acrossai/v1'`), gated on `manage_options`
**plus** an `X-WP-Nonce` an anonymous cross-site fetch can never supply. The 403 body is our own
string, from `AcrossAI_Abilities_Rest_Controller.php:101`. Even opened up it would return
acrossai.co's *own* installed-plugin state, not a catalogue. Ruled out: no CDN or LiteSpeed caching
(`x-litespeed-cache-control: no-cache`), and a cache-buster returns the same 403.

**`wp/v2/integrations` works** — 200, 26 entries, and it carries everything needed: `slug`, `title`,
`excerpt`, `link`, plus ACF `wporg_slug`, `integration_status`, `plugin_website`, `docs_link`, and
`integration_category` term ids that resolve against `wp/v2/ability-categories`.

The original objection to this route is obsolete. The docblock rejected it as "a quarter of a
megabyte of HTML", and that is true of the bare collection — 322 KB measured. With
`_fields=slug,title,excerpt,link,acf,integration_category,support_type&per_page=100` it is
**26.6 KB, a 92% reduction**. `_fields` was not considered when that comment was written.

So: repoint `ENDPOINT` at `wp/v2/integrations` with `_fields`, and teach `normalise()` the CPT shape
(`excerpt.rendered` → description, `acf.wporg_slug` → wporg_slug, term id → category name, `link` →
url). Keep the whitelist discipline exactly as it is. No new route needed on acrossai.co.

### One thing the CPT cannot supply: the ability count

`acf.abilities` is a newline-separated blob of marketing highlights, not a count — Rank Math lists
10 lines against 59 shipped abilities, LiteSpeed 9 against 61. The accurate count comes from the
`ability_category` term tree, and the plugin already knows its own counts exactly. So **counts stay
local** and the feed supplies copy only, which is the separation the directory's docblock already
describes ("A hint, not an authority").

## Blockers: the join key disagrees across the three sources

The join is by `slug`. Measured, three ways:

**Slug mismatches that would produce DUPLICATE rows on every user's Integrations screen** — the row
appears once as installed from local state, and again as "supported but not available here" from the
catalogue:

| Local `TAB_GROUP` | acrossai.co post slug |
|---|---|
| `acf` | `advanced-custom-fields` |
| `events-calendar` | `the-events-calendar` |

**This is why the repoint must not land first.** Fix the key, then repoint.

`TAB_GROUP` is load-bearing (`DEC-ABILITY-GROUP-IDENTIFIER-LOAD-BEARING`) — changing it moves
abilities between MCP tools — so the rename belongs on acrossai.co. Renaming a published post slug
changes its public URL, so it needs a 301; the Redirection plugin is active on acrossai.co and this
plugin now has `redirects/*` abilities to create them. An alias map inside the plugin is the
alternative, and is worse: it hides the mismatch rather than removing it.

**Shipped integrations with no acrossai.co page (2):** `all-in-one-seo` (12 abilities), `site-kit`
(14). Writing those pages is editorial work in the site's own voice, not something to improvise.

**Catalogue-only rows (8):** `buddyboss`, `geodirectory`, `jetengine`, `learndash`, `mailerpress`,
`mailerpress-pro`, `polylang`, `wpml` — Pro or other-product integrations that ship no abilities in
this repo. Legitimately present in the catalogue and absent locally; the join must tolerate this,
which it already does.

**Missing `wporg_slug` (5):** `buddyboss`, `jetengine`, `learndash`, `mailerpress-pro`, `wpml`.
Without it an assistant cannot offer to install the plugin it just recommended.

## The ability list on each integration page — fixed, 2026-11-01

Separate from the catalogue, and the thing that made 17 integration pages unable to show what they
cover.

**How it works.** `ability-categories` is a hierarchical taxonomy on the `abilities` CPT. Each
integration has a PARENT term (`Rank Math`, id 179) whose own count is 0; the abilities are tagged to
its CHILD terms (`Redirections & 404s` 11, `Content SEO` 10, …). The integration post's
`acf.ability_category` points at the parent, and the page sums the children.

**What was wrong.** 17 of 26 integration posts had `ability_category` empty — including Rank Math,
Yoast, Elementor, WooCommerce, ACF, Contact Form 7, UpdraftPlus, WPCode, CookieYes, Loco Translate,
Event Tickets and All-in-One WP Migration. The parent terms all existed and were correctly populated;
nothing pointed at them. The mechanism was sound, the data was 65% unpopulated.

**Fixed** by setting `ability_category` on all 17 through `custom-fields/update-acf-field`, which
goes through ACF's `update_field()`. That matters: the field is an ACF `taxonomy` field
(`field_aa_int_ability_cat`), and ACF stores a value row plus a field-key reference row — a raw post
meta write leaves them out of step and ACF stops reading its own data while reporting success.
Verified afterwards: **26 of 26 resolve, none zero.**

**Counts now cross-check against `docs/abilities-inventory.md` exactly**, with one exception:
Rank Math reads 61 on the site against 59 shipped. That is precisely the two abilities 0.0.44 retired
(`set-module-state`, `get-seo-analysis-results`), so the taxonomy still carries them and needs those
two ability entries retired.

## Earlier notes, superseded above

**1. The live catalogue endpoint returns 403 for everybody.**

```
GET https://acrossai.co/wp-json/acrossai/v1/integrations
→ 403 {"code":"rest_forbidden","message":"You do not have permission to manage abilities."}
```

The plugin fetches it anonymously by design — "no query arguments, no headers, no body". So the
fetch cannot succeed on any site: every install silently falls back to the bundled copy and flags
itself `stale`. The "catalogue changes between plugin releases" premise is currently not working at
all. This is on acrossai.co, not in this repo.

**2. The bundled catalogue is behind the plugin.** `assets/integrations.json` lists SEO as
`rank-math` and `yoast-seo` only — **All in One SEO is missing** (shipped in 0.0.44), as are
Redirection and Query Monitor (0.0.43). Since defect 1 means the bundled copy is in practice the
only catalogue anyone sees, this is what users would actually get.

**The decision these force:** either fix the endpoint's permissions so the feed works as designed,
or accept that the bundled file is the real catalogue and generate it at release time from the
integration registry — the way `docs/abilities-inventory.md` is generated — so it cannot drift
again. The second is more robust and removes an outbound request from the product; the first keeps
the ability to correct copy without shipping a release. Picking one changes what this feature is
built on, so it should be settled before implementation.

## Verification

- With no SEO plugin installed, the ability returns Yoast, Rank Math and AIOSEO under category SEO,
  each `installed: false`, each carrying a `wporg_slug`.
- With Rank Math active, the same call reports it `installed: true, active: true` with its **live**
  ability count, and the other two still listed as candidates.
- An integration present locally but absent from the catalogue still appears.
- Called on a cold cache, no outbound HTTP request is made (assert with a `pre_http_request` guard).
- The ability is reachable from a client whose tool list predates every plugin install.
