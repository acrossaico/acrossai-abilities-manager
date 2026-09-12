# Feature 104 — LiteSpeed Cache abilities (inputs for /speckit-specify)

This file is the natural-language brief that `/speckit-specify` should turn into `spec.md`. Do not treat it as the spec itself.

## One-sentence goal

Add a LiteSpeed Cache ability suite — 60 abilities under `litespeed/` — so an AI client can read and tune the whole caching stack of a site: page cache and purging, the CSS/JS/HTML optimisation pipeline, lazy loading, the crawler, database cleanup, object and browser cache, and settings presets — on a plugin that ships no Abilities API surface of its own.

## Why now

LiteSpeed Cache is one of the most-installed performance plugins on WordPress (7M+ active installs) and the single most common thing a site owner asks an assistant about that an assistant currently cannot touch: "the site is slow", "clear the cache", "my CSS changes aren't showing", "why is this page stale".

**Gap:** LiteSpeed Cache v7.9.1 registers **zero** WordPress abilities. Verified across the whole plugin — no `wp_register_ability`, no `wp_register_ability_category`, no `wp_abilities_api_init`, no abilities-api reference anywhere. Every ability must be written by us. This matches Contact Form 7 (Feature 103) and the three `acrossai-pro` integrations, and contrasts with ACF and Rank Math, which ship their own.

Without a suite, an assistant has no route to LiteSpeed at all. Its 201 settings live under prefixed option names it owns, its cache is purged through class methods rather than options, and there is no REST surface to borrow. Worse, the naive route is actively harmful: writing a LiteSpeed option with `update_option()` skips the type-casting and the side effects that make a change real — the conditional purge, the cron cleanup, the `.htaccess` rewrite and the CDN sync — so the option changes and the site's actual behaviour does not.

**This does not overlap the existing `cache` toolset.** That group is generic WordPress transients and the object-cache drop-in (`cache/flush-transients`, `cache/get-transient`, `cache/flush-object-cache`, …). This suite is LiteSpeed's own page cache, optimisation pipeline, crawler and database optimiser. The one deliberate near-duplicate is `litespeed/flush-litespeed-object-cache`, which goes through LiteSpeed's own handler so its summary and hooks stay consistent.

## Scope

### Abilities to add (60)

All under the `litespeed/` namespace, category `acrossai-litespeed-cache`, `meta.acrossai.tab_group = 'litespeed-cache'` — the suite gets its own toolset and MCP tool, mirroring Contact Form 7 and Rank Math. Eight `sub_group` cards.

**Design note that shapes the whole list:** where LiteSpeed exposes a family of near-identical operations, this suite ships **one parameterised ability** rather than one per variant. `purge-cache` takes a `target` enum instead of seven purge abilities; `update-cache-exclusions` takes a `type` enum instead of six; `optimize-database` takes a `type` enum instead of seven. Without that the same surface would be roughly 90 abilities, and an MCP client would have to choose between near-identical tool names. Every enum value must be listed in the ability description so the choice is discoverable.

#### `ls-purge` — purging and cache control (9)

1. **`litespeed/get-cache-status`** — Whether caching is on, the detected server type, whether the object cache and browser cache are active, the configured TTLs, and whether a QUIC.cloud key is present (reported, never used). The orientation call before anything else. Wraps `Root::conf()` reads plus `Core::cls('Control')`.

2. **`litespeed/purge-cache`** — Purge by target. Inputs: `target` (enum: `all`, `lscache`, `object`, `opcache`, `css-js`, `ucss`, `front-page`). Wraps `Purge::purge_all()`, `purge_all_lscache()`, `purge_all_object()`, `purge_all_opcache()` and `purge_ucss()`. Not confirm-gated — a purge is heavy on a large site but fully recoverable — though the description must say the site will rebuild its cache from cold.

3. **`litespeed/purge-url`** — Purge one or more specific URLs. Inputs: `urls` (array of strings, required). Wraps `Purge::purge_url()`.

4. **`litespeed/purge-post`** — Purge by post ID. Inputs: `post_ids` (array of integers, required). The common case after an edit that did not trigger an auto-purge.

5. **`litespeed/purge-taxonomy`** — Purge a category or tag archive. Inputs: `taxonomy` (enum: `category`, `post_tag`), `terms` (array of slugs or IDs). Wraps `Purge::purge_cat()` / `purge_tag()`.

6. **`litespeed/purge-by-tag`** — Purge by raw LiteSpeed cache tag, for callers that know the tag scheme. Inputs: `tags` (array of strings). Wraps `Purge::add()`.

7. **`litespeed/get-purge-settings`** — Read the auto-purge rules: which hooks purge what, and the stale-serving setting. Returns rows.

8. **`litespeed/update-purge-settings`** — Write the auto-purge rules. Typed field map over the `O_PURGE_*` options.

9. **`litespeed/update-scheduled-purge`** — The timed-purge configuration: purge specific URLs at a set time each day. Inputs: `enabled`, `urls`, `time`. Wraps `O_PURGE_TIMED_URLS` / `O_PURGE_TIMED_URLS_TIME`.

#### `ls-cache` — cache settings (10)

10. **`litespeed/get-cache-settings`** — Read the cache area. Inputs: `group` (optional enum to narrow: `general`, `ttl`, `scope`, `exclusions`, `vary`, `guest`). Returns `[{key, value, default, writable}]` rows.

11. **`litespeed/update-cache-settings`** — Write the general cache toggles. Typed field map over `O_CACHE_*`.

12. **`litespeed/set-cache-state`** — Turn caching on or off site-wide. Inputs: `enabled` (boolean, required), `confirm`. **Confirm-gated when disabling**: the site keeps working and simply stops being cached, with nothing on the front end to say so — the same silent-behaviour-change shape as CF7's `skip_mail`.

13. **`litespeed/update-cache-ttl`** — The TTL family in one call: public, private, front page, feed, REST, and the 403/404/500 pages. Separate from `update-cache-settings` because tuning TTLs is a distinct task from toggling features.

14. **`litespeed/update-cache-scope`** — What gets cached for whom: logged-in users, commenters, REST requests, the mobile view, and the login page.

15. **`litespeed/list-cache-exclusions`** — Read every exclusion list. Inputs: `type` (optional enum). Returns rows so the caller can see all six lists at once.

16. **`litespeed/update-cache-exclusions`** — Write one exclusion list. Inputs: `type` (enum: `uri`, `category`, `tag`, `cookie`, `user-agent`, `role`), `values` (array of strings), `mode` (enum: `replace`, `add`, `remove`, default `replace`). The `mode` parameter is what stops a caller having to read-modify-write a list just to add one line.

17. **`litespeed/get-cache-vary`** — Read the vary/cookie configuration that decides when a separate cache copy is kept.

18. **`litespeed/update-cache-vary`** — Write it. High-impact: a wrong vary rule can serve one visitor's page to another, so the description must say so plainly.

19. **`litespeed/update-guest-mode`** — Guest mode and guest optimisation, which serve a pre-built page to first-time visitors.

#### `ls-optimize` — CSS, JS, HTML and fonts (11)

20. **`litespeed/get-optimization-settings`** — Read the whole optimisation area. Inputs: `group` (optional enum: `css`, `js`, `html`, `font`, `tuning`, `localization`).

21. **`litespeed/update-css-settings`** — Minify, combine, inline, and the async/critical toggles that do not call QUIC.cloud.

22. **`litespeed/update-js-settings`** — Minify, combine, defer and delay.

23. **`litespeed/update-html-settings`** — HTML minify, DNS prefetch, and the removal toggles (query strings, emoji, noscript).

24. **`litespeed/update-font-settings`** — Google Fonts handling: async, or remove entirely.

25. **`litespeed/update-tuning-settings`** — The tuning lists: which CSS/JS files to exclude from combine, defer or delay. Inputs include `mode` (`replace`/`add`/`remove`), as with cache exclusions.

26. **`litespeed/list-optimization-exclusions`** — Read every optimisation exclusion list in one call.

27. **`litespeed/update-optimization-exclusions`** — Write one. Inputs: `type` (enum: `css`, `js`, `js-defer`, `js-delay`, `uri`, `role`), `values`, `mode`.

28. **`litespeed/update-localization-settings`** — Localise external resources: gravatars and third-party JS pulled to the local server.

29. **`litespeed/purge-optimization-cache`** — Purge only the generated CSS/JS artefacts, leaving the page cache alone. The targeted alternative to `purge-cache(all)` after a theme change.

30. **`litespeed/get-optimization-status`** — What has actually been generated: counts and ages of the CSS/JS artefacts on disk, so a caller can tell "configured" from "working".

#### `ls-media` — lazy loading and media (6)

31. **`litespeed/get-media-settings`** — Read the media area.

32. **`litespeed/update-lazyload-settings`** — Image and iframe lazy load, and the inline lazy-load library.

33. **`litespeed/update-placeholder-settings`** — The placeholder shown while an image loads: responsive placeholder, colour, and SVG. **Excludes LQIP**, which is a QUIC.cloud service.

34. **`litespeed/update-media-exclusions`** — Which images or classes to leave alone. Inputs: `type` (enum: `lazyload`, `lazyload-class`, `lazyload-parent-class`, `lazyload-uri`), `values`, `mode`.

35. **`litespeed/update-viewport-settings`** — The viewport-images toggles that do not call QUIC.cloud. Where a setting only takes effect with VPI generation, say so in the description rather than silently accepting a write that does nothing.

36. **`litespeed/get-media-status`** — Whether lazy load is active and how the media settings interact, for diagnosing "my images don't load".

#### `ls-crawler` — the crawler (8)

37. **`litespeed/get-crawler-status`** — Summary: is it on, which crawler is running, position, last start and end, and the current server load against its limit. Wraps `Crawler::get_summary()`.

38. **`litespeed/list-crawlers`** — Every crawler variant the configuration generates (per role, per cookie, per mobile state), each with its index and enabled flag. Wraps `Crawler::list_crawlers()`.

39. **`litespeed/set-crawler-state`** — Enable or disable one crawler by index. Inputs: `index` (integer, required), `enabled` (boolean, required). Wraps `toggle_activeness()`; must return `unknown_crawler` for an index that does not exist rather than silently doing nothing.

40. **`litespeed/run-crawler`** — Start a crawl now. Wraps `Crawler::start( true )`. Long-running, so the response reports that it has been dispatched rather than pretending to be complete.

41. **`litespeed/reset-crawler`** — Reset the crawl position so the next run starts from the beginning. Wraps `reset_pos()`.

42. **`litespeed/get-crawler-settings`** — Read the crawler configuration: interval, threads, load limit, timeout, and the sitemap source.

43. **`litespeed/update-crawler-settings`** — Write it. Typed field map over `O_CRAWLER_*`.

44. **`litespeed/get-crawler-map`** — The sitemap the crawler works from: URL count and a page of entries. Inputs: `limit`, `offset`.

#### `ls-database` — database optimisation (5)

45. **`litespeed/get-database-summary`** — Row counts per cleanup type, so a caller can see what a cleanup *would* delete before running it. **The intended first call before `optimize-database`.** Wraps `DB_Optm::db_count()` for each type.

46. **`litespeed/optimize-database`** — Run one cleanup. Inputs: `type` (enum: `revision`, `orphaned_post_meta`, `trash_post`, `spam_comment`, `trash_comment`, `expired_transient`, `all`), `confirm`. **Destructive**: these are permanent deletes with no trash and no recovery. `annotations.destructive = true`.

47. **`litespeed/get-autoload-summary`** — The largest autoloaded options, the usual cause of a slow site. Read-only.

48. **`litespeed/list-myisam-tables`** — Which tables are still MyISAM. Read-only. Wraps `DB_Optm::list_myisam()`.

49. **`litespeed/convert-tables-to-innodb`** — Convert them. Inputs: `confirm`. **Destructive**: it rewrites table engines in place; a failure part-way leaves a mixed state.

#### `ls-object` — object and browser cache (5)

50. **`litespeed/get-object-cache-status`** — Whether the object cache is on, which backend (Redis or Memcached), the host/port, and whether the drop-in file is in place and current.

51. **`litespeed/update-object-cache-settings`** — Backend, host, port, database, timeouts, and the global/non-persistent group lists.

52. **`litespeed/test-object-cache-connection`** — Prove the configured backend is reachable before relying on it. Wraps `Object_Cache::test_connection()`. Read-only.

53. **`litespeed/flush-litespeed-object-cache`** — Flush through LiteSpeed's own handler so its summary and hooks stay consistent. Deliberately distinct from the existing `cache/flush-object-cache`, and the description must say which to prefer.

54. **`litespeed/update-browser-cache-settings`** — Browser cache on/off and TTL.

#### `ls-toolbox` — presets, import/export, diagnostics (6)

55. **`litespeed/list-preset-backups`** — Every automatic backup taken before a preset was applied, with its timestamp. Wraps `Preset::get_backups()`. **The recovery path for the next two abilities**, so it is listed first.

56. **`litespeed/apply-preset`** — Apply one of the five shipped presets. Inputs: `preset` (enum: `essentials`, `basic`, `advanced`, `aggressive`, `extreme`), `confirm`. **Confirm-gated**: it rewrites the whole configuration. It is not annotated destructive because LiteSpeed takes an automatic backup first — which the description must name, along with `restore-preset-backup`.

57. **`litespeed/restore-preset-backup`** — Roll back to a backup. Inputs: `timestamp` (required), `confirm`. Wraps `Preset::restore()`.

58. **`litespeed/export-settings`** — The full configuration as a portable payload, for backup or copying to another site. Read-only. Wraps `Import::export( true )`.

59. **`litespeed/import-settings`** — Apply an exported payload. Inputs: `data` (required), `confirm`. Same blast radius as `apply-preset`.

60. **`litespeed/get-environment-report`** — LiteSpeed's own environment report: server, PHP, plugin state and detected conflicts. The diagnostic to attach when something is wrong. Wraps `Report::generate_environment_report()`.

### Explicitly out of scope for this feature

- **Everything QUIC.cloud.** Image optimisation (`src/img-optm.cls.php`), CCSS, UCSS, VPI and LQIP generation, CDN activation, and CDN DNS deletion (`Cloud::SVC_*`). All require a domain key and consume paid quota, and the DNS call is destructive against infrastructure this plugin does not own. Reading whether a key exists is in scope; spending it is not. A follow-up feature if wanted, and it should follow the Rank Math precedent of annotating credit-spending abilities `destructive: true`.
- **Direct `.htaccess` editing** (`src/htaccess.cls.php`). A bad write returns a 500 for the whole site. Settings writes still update `.htaccess` *indirectly* via LiteSpeed's own `Activation::update_files()`, which is the safe route and happens automatically.
- **`reset-settings`** (`Import::reset()`). Superseded by `apply-preset` plus `restore-preset-backup`, which reach the same place with a recovery path.
- **The Cloudflare integration** (`src/cdn/cloudflare.cls.php`). It holds third-party API credentials — the same reason CF7's reCAPTCHA and Stripe settings were excluded in Feature 103.

## Prior-art check (2026-09-12)

- **LiteSpeed Cache v7.9.1 itself**: zero abilities, zero MCP code. Verified across the plugin.
- **The existing `cache` toolset in this plugin**: 7 abilities, all generic WordPress transients and the object-cache drop-in. No LiteSpeed awareness. No overlap except the one deliberate near-duplicate noted above.
- **`mcp-abilities-database`** (installed, inactive): generic WordPress database abilities. Does not know about LiteSpeed's cleanup types or its per-type counts.
- No third-party plugin on this site exposes LiteSpeed through the Abilities API.

## Constraints

### Write settings through LiteSpeed's own save path, never `update_option()`

`Conf::update_confs( $matrix )` (`src/conf.cls.php:473`) is the write path. It type-casts each value and then fires the side effects that make a change real: the conditional purge, cron cleanup, `Activation::update_files()` (which rewrites `.htaccess`), the crawler's disabled-list reset, and the CDN config sync. A direct `update_option()` changes the stored value and leaves the site behaving as before — the most likely silent failure in this whole suite.

LiteSpeed's own CLI goes one level higher: it builds an `ENROLL` envelope and calls `Admin_Settings::save()` (`cli/option.cls.php:44`), which additionally runs `Admin::cleanup_text()` and validates the child keys of the two array-shaped options (`cdn-mapping`, `crawler-cookies`). Prefer that path for parity with both the admin screen and the CLI — but note it calls `wp_die()` on an empty enroll list, so the repository must never invoke it with nothing to write.

### 201 settings — per-area typed field maps, not one passthrough

`Base::$_default_options` holds all 201 and is `protected`; read values through the public `Root::conf( $id )` and take each option's type from its default. **Each writer declares an explicit typed field map for its own area** — the `Base_Settings_Write_Ability` shape already used by Rank Math — rather than accepting an arbitrary option key. That is what makes a parameterised suite safe, and it is why the suite is split by area rather than exposing a single `update-setting`. An unknown or non-writable key returns `setting_not_writable` naming the keys that area does accept.

### Settings reads return rows, not maps

Every settings read returns `[{key, value, default, writable}]`. An associative map is the natural shape and the wrong one: PHP encodes it as a JSON object, so an output property declared `type => array` fails the ability's own output schema *after* the work is done (`BUG-ARRAY-TYPED-OUTPUT-IS-A-JSON-OBJECT`, found live in Feature 103). This suite is the most likely place in the codebase to reintroduce that bug, since every one of its reads is naturally a map. One shared row-shaping helper, used by every reader and every writer's confirmation payload.

### High-risk operations

- **Permanent deletes**: `optimize-database` and `convert-tables-to-innodb`. `destructive: true` and `confirm: true`.
- **Silent site-wide behaviour changes**: `set-cache-state(false)`, `apply-preset`, `import-settings`, `restore-preset-backup`. Confirm-gated but not destructive — LiteSpeed backs up before a preset, and nothing is deleted.
- **Correctness risk rather than data risk**: `update-cache-vary` and `update-cache-scope` can cause one visitor's page to be served to another. Not gated, because the same risk exists for every cache setting and a gate on one is misleading — but the description must state it.

### Capabilities and the permission floor

Floor at `manage_options`, declared `final` on the base. LiteSpeed's own admin screens are `manage_options` throughout, so unlike Contact Form 7 there is no lower host capability to compose with — but the `final` matters for the same reason: it stops a future subclass quietly lowering the floor, which is what opened a real hole in the Rank Math suite.

### Suite conventions (non-negotiable, enforced by tests)

- The base class is the **sole** assembler of `ability()` and the sole enforcer of the guard order: available → confirmed → `run()` → envelope. Subclasses implement `run()` and metadata only, and never override `ability()` or `execute()`.
- **No ability class may name a `LiteSpeed\*` symbol.** All host access goes through `includes/Abilities/Utilities/LiteSpeed/`.
- `output_schema` is always `success` + payload + `message` + `error_code`, `required => array( 'success' )`, `additionalProperties => false` on both schemas.
- **`confirm` must never be schema-required** — core validates the input schema before `execute()` runs, so a required `confirm` produces a generic `ability_invalid_input` and the confirmation gate never fires. The base strips it defensively.
- Slugs are verb-first kebab-case under `litespeed/` (`DEC-SLUG-CONVENTION-VERB-FIRST`).
- Every enum value a parameterised ability accepts must appear in its description; an MCP client cannot discover them otherwise.

### Registration is conditional on LiteSpeed

Gate the whole suite at boot on the host being present, and re-check inside `execute()` — LiteSpeed can be deactivated after the abilities were registered in the same request. The category registrar self-guards too, because WP core silently drops any ability whose category was not pre-registered.

## Non-goals

- Replacing the existing generic `cache` abilities.
- A performance-audit or scoring ability. Reporting what is configured is in scope; judging it is a different feature.
- Front-end measurement of any kind.
- Multisite network-level settings (`Conf::network_update()`). Single-site only for this feature; note it as a follow-up.

## Files that will likely be touched (all new unless noted)

- `includes/Abilities/LiteSpeed/Base_LiteSpeed_Ability.php` — the sole `ability()` assembler.
- `includes/Abilities/LiteSpeed/<Verb>_<Subject>.php` × 60.
- `includes/Abilities/LiteSpeed/Category_Registrar.php`.
- `includes/Abilities/Utilities/LiteSpeed/LiteSpeed_Guard.php` — availability, confirmation, `ok()`/`fail()`.
- `includes/Abilities/Utilities/LiteSpeed/Settings_Repository.php` — the one save call site, the row shaper, the typed field maps.
- `includes/Abilities/Utilities/LiteSpeed/{Purge,Crawler,Database,Toolbox}_Repository.php`.
- `includes/Abilities/Integrations/LiteSpeed_Cache.php` — the toolset declaration; `ability_prefixes()` returns `array()`.
- `includes/Abilities/Integrations/AcrossAI_Toolset_Integrations.php` — **modified**, one line in `built_in()`.
- `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` — **modified**, three edits: the category action, the `class_exists` gate, and the private registration method.
- `tests/phpunit/abilities/Test_LiteSpeed_Architecture.php`, `Test_LiteSpeed_Suite_Contract.php`.
- `tests/phpunit/Modules/Library/Test_Ability_Group_Map.php` — **modified**, append `'LiteSpeed'` to **both** skip arrays (they are duplicated and must stay in sync).
- `tests/phpunit/abilities/Toolset/Test_Toolset_Group_Coverage.php` — **modified**, add the new integration to its `require_once` block or `built_in()` fatals in the WP-less bootstrap.
- `phpunit.xml.dist` — **modified**, explicit `<file>` entries.
- `README.txt` — **modified**, changelog.
- `docs/abilities-inventory.md` — regenerated, not hand-edited.

## Existing code to reuse

- `includes/Abilities/ContactForm7/Base_Contact_Form_7_Ability.php` — the closest model for the base, including the `confirm`-stripping and the `final permission_floor()`.
- `includes/Abilities/Utilities/ContactForm7/Contact_Form_7_Guard.php` — the guard and envelope shape, including `is_available()`.
- `includes/Abilities/Utilities/ContactForm7/Form_Repository.php::describe_additional_settings()` — the row-shaping precedent for settings reads.
- `includes/Abilities/RankMath/Base_Settings_Write_Ability.php` — the typed field-map pattern for per-area writers.
- `includes/Abilities/Integrations/Contact_Form_7.php` — the toolset declaration to copy, including why `ability_prefixes()` is empty.
- `tests/phpunit/abilities/Test_Contact_Form_7_Architecture.php` and `Test_Contact_Form_7_Suite_Contract.php` — mirror both.

## Verification (what "done" looks like)

**LiteSpeed Cache is currently inactive on the development site. Activate it first — the suite is invisible until then, and that is correct behaviour, not a bug.**

1. Abilities register only when LiteSpeed is active; the tab and the MCP tool disappear when it is not.
2. A `LiteSpeed Cache` tab appears with its count; `?tab=litespeed-cache` deep-links; rows show `toolset/litespeed-cache`.
3. `sum(counts) === total` still holds on `GET /acrossai/v1/abilities/toolsets`.
4. **Every one of the 60 abilities executed against a live site**, via a temporary mu-plugin REST route calling `wp_get_ability( $name )->execute( $input )` in process, deleted before committing. The core GET run route cannot express typed object input and `wp eval` does not register this plugin's abilities (`BUG-GET-RUN-ROUTE-CANNOT-EXPRESS-TYPED-INPUT`).
5. Negative paths: `confirmation_required` on `optimize-database`, `convert-tables-to-innodb`, `set-cache-state(false)`, `apply-preset`, `import-settings` and `restore-preset-backup`; `setting_not_writable` for a key outside an area's map; an unknown `target`, `type` or crawler `index`.
6. Round-trip: read a setting, change it, read it back, and confirm both the value and the row shape survive. Then restore the original.
7. `get-database-summary` counts match what `optimize-database` then removes.
8. Dispatch through `toolset/litespeed-cache` (`action=discover`, then `action=execute`) and confirm a cross-group call is refused with `ability_not_in_group`.
9. `composer phpcs`, `composer phpstan` (level 8), `vendor/bin/phpunit`, `npx wp-scripts test-unit-js`.

## Suggested next steps (for the human)

`/speckit-specify` → `/speckit-plan` → `/speckit-tasks` → implement. Commit the brief alone first, house message shape:
`docs(specs): 104 litespeed-cache-abilities — feature brief for /speckit-specify`.
