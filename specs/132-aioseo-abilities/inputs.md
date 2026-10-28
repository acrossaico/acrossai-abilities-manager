# Feature 132 — All in One SEO ability suite

> Feature number is provisional — let `/speckit.specify` assign the next free one (131 is AI image generation).

All in One SEO is the third major SEO plugin this manager meets, after Yoast (Feature 106, 64 abilities) and Rank Math (Feature 069, 59). Unlike those two, the groundwork is already partly done: **the grouping half shipped in 0.0.44** as `Integrations/All_In_One_Seo.php`. This feature is the abilities.

Researched against **AIOSEO 5.0.3 free**, installed and active at `wp-content/plugins/all-in-one-seo-pack/`.

## What already exists, measured not assumed

**AIOSEO ships thirteen abilities of its own** (since 5.0.x, `app/Common/Abilities/Abilities.php`, hooked on `wp_abilities_api_categories_init` and `wp_abilities_api_init`). Confirmed registered at runtime:

| Namespace | Abilities |
|---|---|
| `aioseo-posts/` | `seo-data-get`, `seo-data-update`, `list-missing-seo`, `list-truseo-score` |
| `aioseo-robots/` | `output-get`, `rules-list`, `rules-add`, `rules-update`, `rules-delete` |
| `aioseo-audit/` | `homepage-get`, `site-get` |
| `aioseo-settings/` | `get` |
| `aioseo-notifications/` | `list` |

**Already closed in 0.0.44:** all thirteen reported `tab_group => other` before the integration row landed, and now report `all-in-one-seo` with a generated `toolset/all-in-one-seo` dispatcher. Nothing in this feature should re-do that; the row claims six prefixes (`aioseo`, plus AIOSEO's five area namespaces) and is covered by `Test_All_In_One_Seo_Integration`.

**Easy MCP AI is not a factor.** It was checked because it advertises SEO coverage. It is an Abilities API *consumer*, not a registrant: it reads `wp_get_abilities()` (`includes/class-ability-seed.php`) and seeds an enable-list, and its own 243 "tools" are an internal format, not WP abilities. Its AIOSEO coverage is three tools — `aioseo-get-post-seo`, `aioseo-update-post-seo`, `aioseo-get-breadcrumb` — which duplicate AIOSEO's own post abilities and register nothing. **No collision risk and nothing to adopt.**

## The gap — AIOSEO's own set is narrow in two specific ways

Both limits are stated by AIOSEO itself, in its own ability descriptions:

1. **Settings are read-only, and opaque.** `aioseo-settings/get` returns "the full AIOSEO settings tree … Shape is internal AIOSEO; treat as opaque structured data", with "Read-only: settings cannot be modified via abilities — use the AIOSEO admin UI." There is **no write ability for any setting**, and no field specification to discover one from.
2. **`aioseo-posts/seo-data-update` refuses schema and EEAT** — "Fields not listed (e.g. schema, EEAT) are rejected — use the AIOSEO admin UI for those."

Plus, by absence: no sitemap abilities at all, no term/taxonomy SEO, no per-post-type or archive search-appearance reads or writes, no settings import/export or backup, no bulk edit, no fresh audit run (`aioseo-audit/*` returns the cached scan), no redirects, no internal-link tooling, no Search Statistics reads.

**So the suite's centre of gravity is the settings layer**, exactly as it is for Rank Math.

### The write path exists and is sanctioned

`AIOSEO\Plugin\Common\Options\Options::sanitizeAndSave( array $options )` is public and is AIOSEO's own save path. It is not a thin `update_option` — it handles the side effects a raw write would skip: sitemap regeneration when sitemap options change, phone-number reformatting, a rewrite flush when `removeCategoryBase` changes, llms.txt option handling, and log-retention rescheduling. **Every write in this suite must go through it**, the same rule the Rank Math suite follows with Rank Math's own sanitizer. A raw `update_option( 'aioseo_options', … )` would store settings that never take effect — the failure class this plugin has hit before.

### The settings tree, read from the live install

Top-level groups from `aioseo()->options->all()`:

`webmasterTools`, `aiContent`, `breadcrumbs`, `rssContent`, `advanced`, `sitemap` (general, rss, html, llms), `social` (profiles, facebook, twitter), `searchAppearance` (global, advanced, archives), `searchStatistics`, `tools` (robots, importExport, seoAlerts) — plus `internal` and `deprecated`, which are **not** writable surface.

Per-object settings live in a separate store, `aioseo()->dynamicOptions->all()`: `searchAppearance.postTypes` / `.taxonomies` / `.archives`, `sitemap.priority`, `social.facebook`, `seoAnalysis.*`. One post type's shape is `show, advanced, title, metaDescription, schemaType, webPageType, articleType, customFields`. **Two stores, two writers** — a single "update settings" ability that silently picks one would be wrong half the time.

### Capabilities map cleanly onto panels

`aioseo()->access->getCapabilityList()` yields 27 granular caps, including `aioseo_general_settings`, `aioseo_search_appearance_settings`, `aioseo_sitemap_settings`, `aioseo_social_networks_settings`, `aioseo_tools_settings`, `aioseo_page_analysis`, `aioseo_page_schema_settings`, `aioseo_redirects_manage`, `aioseo_local_seo_settings`, `aioseo_link_assistant_settings`, `aioseo_search_statistics_settings`.

This is the same shape as the Rank Math capability map: a `manage_options` floor composed with the panel's own AIOSEO capability. AIOSEO's own abilities use a lower bar — `canEditPosts` for the post four, `canReadGeneralSettings` for the settings/notifications reads, `canManageTools` for robots.txt.

### Pro is addon-shaped, and nothing is installed here

`aioseo()->addons->getAddons()` lists seven, all `installed=n` on this site: `aioseo-eeat`, `aioseo-link-assistant`, `aioseo-video-sitemap`, `aioseo-local-business`, `aioseo-news-sitemap`, `aioseo-index-now`, `aioseo-image-seo`. Redirects and Search Statistics are Pro-edition features.

Follow the **ACF precedent, not the Elementor one**: gate each Pro-dependent ability on the *capability it actually needs* (the addon being active), individually, rather than on an edition check — and prefer the Rank Math shape of registering and reporting at runtime where availability can change without a plugin activation.

## Namespace — the one decision that needs making before any code

Three namespaces are already spoken for:

- `seo/` — **taken**, by this plugin's 58 Yoast abilities (`seo/get-seo-settings`, `seo/update-sitemap-settings`, `seo/get-homepage-seo`, …). An AIOSEO suite here would collide name-for-name on the areas that matter most, and Yoast and AIOSEO *can* be active together. One side would lose abilities silently by load order.
- `taxonomies/` — **taken**, by the Yoast suite's six term abilities.
- `aioseo-posts/`, `aioseo-settings/`, `aioseo-robots/`, `aioseo-audit/`, `aioseo-notifications/` — **AIOSEO's own**. Never register into these.

**Recommendation: `aioseo/`.** It is unused by AIOSEO today and off-pattern for them — their convention is demonstrably `aioseo-<area>/`, so the bare prefix is less likely to be claimed later than `rank-math/` ever was. The integration row already claims it, so abilities group correctly on day one.

**The standing risk, stated plainly:** this is the same bet Feature 069 made with `rank-math/`, and that bet lost — Rank Math claimed `rank-math/get-settings` and `rank-math/get-sitemap-status` in 1.0.277 and we renamed two abilities in 0.0.44. If AIOSEO ever ships `aioseo/*`, we move again. The mitigation is not a cleverer prefix, it is the rule we now hold: **the host plugin owns its namespace, and when it claims a name we move or retire rather than shadow.** Spec should record that explicitly so the next person does not have to rediscover it.

## Scope — what to build, and what NOT to

Follow the Rank Math suite's architecture, which is the closest fit:

- **`Base_Aioseo_Ability`** — sole assembler of `ability()`, sole enforcer of the guard order (available → capability → confirmation → `run()` → envelope), owner of `CATEGORY = 'acrossai-all-in-one-seo'` and `TAB_GROUP = 'all-in-one-seo'` (must equal `All_In_One_Seo::TAB_GROUP`). Subclasses give the slug suffix; they must never name an `AIOSEO\*` symbol.
- **`Utilities/Aioseo/Aioseo_Guard`** — the two-symbol presence test (`AIOSEO_VERSION` **and** `aioseo()`, matching the integration row), the capability factory, and the `ok()`/`fail()` envelope.
- **`Utilities/Aioseo/Settings_Registry`** — the typed field specification per panel, the piece AIOSEO has no equivalent of and the reason a write suite is possible at all. This is what makes `aioseo/get-settings-panel` worth having next to their opaque `aioseo-settings/get`.
- **`Utilities/Aioseo/Settings_Writer`** — the single `sanitizeAndSave()` call site, covering both the static and dynamic option stores.
- **`Category_Registrar`** — registered **unconditionally**, per the 0.0.44 backup-suite fix: a guarded registrar plus an unguarded ability registration silently drops the whole suite.

**Candidate ability areas**, each scoped to what AIOSEO's own thirteen and this plugin's generic abilities do not already do: a panel reader with field specs; typed writers for search appearance (global / advanced / archives), per-post-type and per-taxonomy settings, sitemap (general / rss / html / llms), social profiles and defaults, breadcrumbs, webmaster verification, RSS content, and the advanced/crawl group; sitemap status, URL enumeration and cache invalidation; term SEO read/write; bulk post-meta edit; settings export, import and backup; a fresh SEO Site Analysis run; notification dismissal.

**Explicitly out of scope — do not duplicate:**

- One post's SEO read and write — `aioseo-posts/seo-data-get` / `seo-data-update` cover it. Where our bulk editor overlaps, point at theirs for the single case.
- Posts missing SEO data, and posts by TruSEO score — `aioseo-posts/list-missing-seo`, `list-truseo-score`.
- robots.txt rules and output — `aioseo-robots/*` is full CRUD and better than a whole-file replace.
- Listing notifications — `aioseo-notifications/list`.
- Reading the raw settings tree — `aioseo-settings/get`.
- Anything the generic suites already do (media, content, taxonomies, options, cron). Wire `suggested_abilities()` to the existing ones rather than adding near-duplicates.

## Constraints — traps, not preferences

- **Never register into `aioseo-*/`**, and never into `seo/` or `taxonomies/`.
- **All AIOSEO symbol access lives in `Utilities/Aioseo/`.** `aioseo()`, `AIOSEO\*`, `AIOSEO_VERSION` appear nowhere else — the rule the Rank Math, Query Monitor and Redirection suites already follow and have architecture tests for.
- **Writes go through `sanitizeAndSave()`**, never `update_option( 'aioseo_options' )`.
- **Two option stores.** A writer must say which it targets; `dynamicOptions` is not a sub-key of `options`.
- **Category registered unconditionally** (0.0.44 lesson).
- **Capability floor composed, raise-only** — `manage_options` plus the panel's AIOSEO capability, with the filter consulted only after the floor passes.
- **Confirm-gate the irreversible ones**: settings import (replaces a panel wholesale), backup restore, notification dismissal in bulk, any destructive sitemap or analysis reset.

## Verification

- With AIOSEO active, `wp_get_abilities()` contains both sets, zero name collisions, and every one reports `tab_group => all-in-one-seo`.
- A write through each typed writer is read back changed, and the side effect `sanitizeAndSave()` owns actually happened (a sitemap option change invalidates the sitemap; a `removeCategoryBase` change flushes rewrites).
- A field that does not belong to the target panel rejects the whole write and names the panel.
- With AIOSEO deactivated, the category still registers, every ability still exists, and each returns a single typed `aioseo_missing` error naming AIOSEO — not a fatal, and not a different suite's error code (the 0.0.39 lesson).
- No ability names an `AIOSEO\*` symbol outside `Utilities/Aioseo/` (architecture test).
