# Feature 106 — Yoast SEO abilities (inputs for /speckit-specify)

This file is the natural-language brief that `/speckit-specify` should turn into `spec.md`. Do not treat it as the spec itself.

## One-sentence goal

Add 64 Yoast SEO abilities under a new `yoast-seo` toolset — site-wide settings, term and taxonomy SEO, indexables, sitemaps, indexation and tools — and adopt Yoast's own abilities into that toolset, which nothing currently claims.

## Why now

Yoast SEO 28.4 is the most-installed SEO plugin on WordPress. Unlike Contact Form 7 or LiteSpeed it **does** ship its own Abilities API integration, so this is a gap-analysis job rather than a build from scratch.

**What Yoast already covers**, from `src/abilities/user-interface/abilities-integration.php`:

- `yoast-seo/get-seo-scores`, `yoast-seo/get-readability-scores`, `yoast-seo/get-inclusive-language-scores`
- `yoast-seo/get-post-seo-data`, `yoast-seo/update-post-seo-data`

Five abilities, all scoped to **an individual post**: the SEO title, meta description, focus keyphrase, canonical, Open Graph and Twitter fields, schema types, cornerstone flag and robots toggles, plus three score reports.

**Two gaps, and the first is ours, not Yoast's.**

### Gap 1 — nothing claims the `yoast-seo` prefix, and the five vanish on staging anyway

There is no `Integrations/Yoast_Seo.php`. An ability registered by a host plugin that no integration claims falls into the **Other** catch-all: no tab, no dedicated MCP dispatcher. That is exactly the defect issue #184 described and fixed for Rank Math and ACF; Yoast was simply never declared.

Measured on this site with Yoast active, though, something else happens first — **the toolset total does not move at all**: 353 abilities before and after, with no `yoast-seo` group and no `other` group. The five are not mis-filed; they are absent. Traced to `Abilities_Integration::get_conditionals()`, which requires `Should_Index_Indexables_Conditional`, which resolves to `is_production_mode()`:

```
wp_get_environment_type():   local
Yoast is_production_mode():  false
should_index_indexables():   false      indexables table: 0 rows
```

**Yoast disables its own abilities on every non-production environment.** That is deliberate on their part — indexables store permalinks, and building them on staging bakes in the wrong ones.

Two consequences the implementation must honour:

1. The toolset declaration is still needed and still correct, but its visible effect is conditional: Yoast's five appear on production sites and not on staging ones. Both counts are verification targets.
2. **This suite must NOT inherit that gate.** Settings, terms, sitemaps and tools have nothing to do with indexables and must work on any environment. Only the `yoast-indexables` group genuinely depends on the table, and it should report cleanly when the table is empty rather than disappearing. Copying Yoast's conditional by reflex would make all 64 invisible on every staging site — an easy mistake, and the exact state measured above.

### Gap 2 — Yoast's coverage stops at the individual post

Nothing in its five abilities addresses:

- **Site-wide settings.** Four option groups, 280 keys, machine-counted: `wpseo` (124), `wpseo_titles` (129), `wpseo_social` (20), `wpseo_llmstxt` (7).
- **Terms and taxonomies.** `WPSEO_Taxonomy_Meta::get_term_meta()` / `set_values()` exist and no ability uses them.
- **Indexables** — home page, post-type archives, author archives, date archive, system pages (404, search) — all reachable through `Indexable_Repository`.
- **Sitemaps** — `WPSEO_Sitemaps_Cache::invalidate()` and friends.
- **Indexation** — the actions under `src/actions/indexing/`.
- **Tools** — the AIOSEO importer, conflicting-plugin detection.

## Scope — 64 abilities

Category `acrossai-yoast-seo`, `meta.acrossai.tab_group = 'yoast-seo'`, eight `sub_group` cards shaped like the Rank Math suite's.

**Slugs must NOT use the `yoast-seo/` namespace.** That prefix belongs to Yoast, and our integration claims it so Yoast's own abilities are adopted into the tab — a slug of ours under it would shadow one of theirs. Per `DEC-TOOLSET-SLUG-NAMESPACE` a namespace names the resource acted on, so these live under `seo/` and, where they act on terms, `taxonomies/`.

| Sub-group | # | Slugs |
|---|---|---|
| `yoast-settings` | 16 | `seo/get-seo-settings`, `list-settings-areas`, `update-general-settings`, `update-crawl-settings`, `update-title-templates`, `update-archive-settings`, `update-breadcrumb-settings`, `update-knowledge-graph`, `update-social-defaults`, `update-social-profiles`, `update-schema-settings`, `update-rss-settings`, `update-llms-settings`, `update-webmaster-verification`, `export-settings`, `import-settings` |
| `yoast-content-types` | 6 | `seo/list-content-type-settings`, `get-post-type-seo`, `update-post-type-seo`, `get-taxonomy-seo`, `update-taxonomy-seo`, `update-archive-seo` |
| `yoast-terms` | 6 | `taxonomies/get-term-seo`, `update-term-seo`, `clear-term-seo`, `list-term-seo`, `get-primary-term`, `set-primary-term` |
| `yoast-indexables` | 9 | `seo/get-indexable`, `list-indexables`, `get-homepage-seo`, `update-homepage-seo`, `get-post-type-archive-seo`, `update-post-type-archive-seo`, `get-author-archive-seo`, `get-system-page-seo`, `update-system-page-seo` |
| `yoast-sitemap` | 6 | `seo/get-sitemap-status`, `list-sitemap-index`, `invalidate-sitemap`, `invalidate-sitemap-for-post`, `get-sitemap-settings`, `update-sitemap-settings` |
| `yoast-indexing` | 5 | `seo/get-indexing-status`, `get-indexation-counts`, `run-indexing`, `reset-indexing`, `cleanup-indexables` |
| `yoast-content` | 8 | `seo/list-cornerstone-content`, `set-cornerstone`, `get-internal-links`, `list-orphaned-content`, `get-seo-score-summary`, `list-low-score-content`, `get-keyphrase-usage`, `get-content-seo-issues` |
| `yoast-tools` | 6 | `seo/get-seo-status`, `list-conflicting-plugins`, `get-robots-settings`, `update-robots-settings`, `get-import-status`, `run-aioseo-import` |

The settings areas are derived from the option-key prefixes rather than invented: `remove_*`/`deny_*`/`clean_*` are crawl optimisation, `title-*`/`metadesc-*` the templates, `noindex-*`/`disable-*` the archives, `breadcrumbs-*`, `company_*`/`person_*`/`org-*` the knowledge graph, `social-*` in `wpseo_titles` the Open Graph defaults, `wpseo_social` the profile URLs, `schema-*`, `rss_*`, and the four `*verify` keys the webmaster tools.

## Explicitly out of scope

- **Yoast Premium in its entirety** — redirects, multiple keyphrases, internal-linking suggestions, the orphaned-content workflow's Premium half. Premium is not installed here, so any Premium-gated ability would ship unverified, and shipping unverified abilities is the thing this project's live-verification discipline exists to prevent. A follow-up if Premium is ever installed.
- **The Yoast add-ons** — Local, News, Video, WooCommerce SEO. Same reasoning.
- **Editing `robots.txt` or `.htaccess` file contents.** Yoast's file editor writes both; a bad write takes the site down. The robots *settings* that Yoast derives its output from are in scope; the raw file is not.

## Constraints

### Write through Yoast's API, never `update_option()`

`WPSEO_Options::set()` and the `Options_Helper` validate and sanitise per option class — each group has its own validation rules in `inc/options/class-wpseo-option-*.php`. A raw `update_option( 'wpseo_titles', … )` bypasses all of it and can store a value Yoast will later reject or misread. Terms go through `WPSEO_Taxonomy_Meta::set_values()` for the same reason.

### 280 keys — per-area typed field maps, not a passthrough

Exactly the LiteSpeed shape. Derive each key's type from Yoast's own `get_defaults()` rather than hand-listing: 73 booleans, 28 strings, 20 arrays and 3 integers in `wpseo` alone, and hand-maintaining that would drift on the first Yoast release. A key outside its area returns `setting_not_writable` naming what the area does accept.

### Rows, never maps

Every settings read returns `[{key, value, type, writable}]`. `BUG-ARRAY-TYPED-OUTPUT-IS-A-JSON-OBJECT` has now bitten in Features 103 and 104 and was guarded against in 105; a settings read is the natural place for it to happen a fourth time.

### No Yoast symbol outside `includes/Abilities/Utilities/Yoast/`

Enforced by an architecture test. Yoast's API is a mix of legacy `WPSEO_*` classes and modern `Yoast\WP\SEO\*` namespaced ones, so the test must catch both spellings — and strip our own namespace first, which contains the vendor string (the trap Feature 104 hit).

### No environment gating in this suite

No ability or repository may reference `is_production_mode`, `should_index_indexables` or `wp_get_environment_type`. Asserted by test. Yoast's gate is right for indexables and wrong for settings.

### Suite conventions (enforced by tests)

- The base class is the sole assembler of `ability()` and the sole enforcer of the guard order.
- `Slash_Input` on every writer accepting a caller-supplied string — schema fragment, `slash()` around the write, `meta_flags()` in `meta`. `is_writer()` must mean the ability actually slashes something, per the Feature 105 decision.
- Floor `manage_options`, declared `final`.
- `confirm` never schema-required; confirm-gate `import-settings`, `reset-indexing`, `cleanup-indexables`, `clear-term-seo` and `run-aioseo-import`.
- `suggested_abilities()` where a caller predictably needs a second call, with every suggested slug asserted to resolve.
- `AcrossAI_Category_Slug_Migration::OWNED` gains `yoast-seo`; `'Yoast'` goes into **both** skip arrays in `Test_Ability_Group_Map.php`; `phpunit.xml.dist` gains explicit `<file>` entries.

## Files

New: `includes/Abilities/Yoast/Base_Yoast_Ability.php`, `Base_Settings_{Read,Write}_Ability.php`, 64 ability classes, `Category_Registrar.php`; `includes/Abilities/Utilities/Yoast/{Yoast_Guard,Settings_Repository,Term_Repository,Indexable_Repository,Sitemap_Repository,Tools_Repository}.php`; `includes/Abilities/Integrations/Yoast_Seo.php`; three test files.

Modified: the bootstrap (three edits), `AcrossAI_Toolset_Integrations::built_in()`, the category migration, `Test_Ability_Group_Map.php`, `phpunit.xml.dist`, `README.txt`, `docs/abilities-inventory.md` (regenerated).

**`Integrations/Yoast_Seo.php` differs from every suite so far**: `ability_prefixes()` returns `array( 'yoast-seo' )`, not the empty array used for Contact Form 7 and LiteSpeed, because Yoast genuinely registers under that prefix and we want those five adopted. That is the ACF and Rank Math shape.

## Verification

Yoast is active; the baseline is captured above.

1. **Our 64 register on this `local` site.** The single most important assertion: if the suite inherits Yoast's production gate it is invisible on every staging install.
2. **Adoption of Yoast's own abilities**, using `add_filter( 'Yoast\WP\SEO\should_index_indexables', '__return_true' )` — confirmed to flip the conditional without touching `WP_ENVIRONMENT_TYPE`. Only **two** of Yoast's five ever appear, so the tab reads **66** with the filter on and **64** with it off. Yoast gates its inclusive-language ability on an analysis feature that is off here, and `register_get_post_seo_data_ability()` / `register_update_post_seo_data_ability()` are defined in `abilities-integration.php` but never called from `register_abilities()` — dead methods in Yoast 28.4.
3. Execute all 64 in process via a temporary mu-plugin REST route calling `wp_get_ability( $name )->execute( $input )`, deleted before committing.
4. Round-trip a setting in each of the four option groups: read, write, read back, restore.
5. Term SEO round-trip on a real category, read back through `WPSEO_Taxonomy_Meta`.
6. Indexables: home page, a post-type archive, an author archive, a system page — and confirm they degrade cleanly with an empty indexables table rather than erroring.
7. Sitemap: read status, invalidate, confirm the cache actually cleared.
8. Negative paths: Yoast-absent via the guard suite; unknown option key; unknown term; unknown indexable type.
9. `sum(counts) === total`; dispatch through `toolset/yoast-seo`, including **one of Yoast's own abilities** through our dispatcher — the end-to-end proof of adoption.
10. `vendor/bin/phpunit`, `npx wp-scripts test-unit-js`, PHPCS on the new files. **No PHPStan claim from `composer phpstan`** — it analyses nothing (issue #190); run it with a working config and report that.
11. Regenerate `docs/abilities-inventory.md` and confirm the 64 are actually counted — the generator was fixed in Feature 105 to handle classes that supply a full slug, which is the shape this suite uses.

## What live verification found (all fixed in this branch)

Every item here was invisible to the test suite and surfaced only by executing the abilities against a real site. Each now has a mutation-verified guard.

1. **Two stored OAuth credentials were readable.** `semrush_tokens` and `wincher_tokens` hold live access and refresh tokens (`Yoast\WP\SEO\Values\OAuth\OAuth_Token`). The settings reader returned every key in an area, so `seo/get-seo-settings` handed them to any caller — over MCP, off-site. Yoast excludes both from its own telemetry and from its settings screen via `Settings_Integration::DISALLOWED_SETTINGS`. The repository now subtracts that constant at runtime (18 keys, 5 of which were in our map), and a test asserts our frozen fallback still covers Yoast's live list.
2. **The per-post-type key map was a frozen snapshot.** Yoast mints `title-{pt}`, `noindex-tax-{tax}`, `title-ptarchive-{pt}` and eleven other families per registered type. Deriving them once captured only `post`, `page`, `attachment`, `category`, `post_tag` — every custom type was refused by name. Worse, no post type on the generating install had `has_archive`, so **not one `*-ptarchive-*` key existed** and `seo/update-post-type-archive-seo` could not write anything on any site. Now read from the live `wpseo_titles` option per request, with longest-prefix family matching. Found by registering a `guide` CPT as a fixture.
3. **Rejected writes reported success.** Yoast validates per option group and silently keeps the old value; the enum keys `schema-article-type-*` and `llms_txt_selection_mode` do exactly this. `write()` reported the key as updated regardless. It now reads each key back after `save()` and returns `setting_rejected` naming the requested value, the value Yoast kept, and the keys in the same call that did apply.
4. **Two settings areas had no writer, and nine advertised abilities did not exist.** `List_Settings_Areas` built its slugs by concatenating `seo/update-` onto the area name, which resolved for 5 of 14 areas. `integrations` and `advanced` had no writer at all and were silently read-only. The map is now declared (`Settings_Repository::writer_for()`), two writers were added — `seo/update-advanced-settings` and `seo/update-integration-settings` — and the contract test resolves every entry. This is what took the suite from 62 to 64.
5. **The permission filter could lower the floor.** `can()` returned `apply_filters( ..., $allowed, $floor )` directly, so a filter returning true granted a subscriber an SEO write — the opposite of the raise-only behaviour its own docblock claimed. It now returns false before consulting the filter. **The same shape is in `Acf_Guard` and `LiteSpeed_Guard`, both already shipped** — left alone here rather than changed silently; worth a follow-up decision.
6. **Yoast aliases a column in its link query.** `get_incoming_link_counts_for_post_ids()` selects `target_post_id` **as** `post_id`, so rows have no `target_post_id` key. Reading the obvious name yields zero incoming links for every post, and an empty link table returns nothing either way — only visible against real link data.

### Verification performed

* All **64** executed in process against the live site via a temporary mu-plugin route; **64/64** pass, re-run after every fix.
* All **14** settings areas round-tripped: read, write, read back, restore.
* Both enum-validated areas confirmed to accept a valid value and to report `setting_rejected` for an invalid one, including a mixed call where one key applied and one was refused.
* Confirmation gates verified to fire unconfirmed and pass confirmed, for all three gated abilities plus the robots writer's one-directional gate.
* PHPUnit 2619 tests / 12271 assertions green; Jest 174 green; PHPCS clean on the suite.
* Eight new architecture guards each mutation-verified by reintroducing the defect.

## Suggested next steps (for the human)

`/speckit-specify` → `/speckit-plan` → `/speckit-tasks` → implement.
