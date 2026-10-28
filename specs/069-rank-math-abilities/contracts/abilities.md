# Ability Contracts: Rank Math Suite

**Feature**: 069-rank-math-abilities

61 abilities. Slug prefix `rank-math/`; category `acrossai-abilities-manager-rank-math`;
`meta.acrossai.tab_group = 'rank-math'` on all.

Reference numbers are the stable design IDs from the approved plan. Two IDs were dropped after the
overlap audit against the plugin's existing 328 abilities, so the sequence is non-contiguous:
`update-role-capabilities` (superseded by `users/add-role-capability` /
`remove-role-capability`) and `get-rewrite-status` (superseded by `settings/list-rewrite-rules`
plus #59).

## Cross-cutting behaviour

Applies to every ability; not repeated per entry.

1. **Envelope** — see [data-model.md](../data-model.md). `output_schema` always declares
   `'required' => array( 'success' )` and `'additionalProperties' => false`. Every output schema
   includes `success`, `message`, `error_code` alongside its payload keys.
2. **Guard ordering** — `assert_available()` → `assert_module()` → `assert_confirmed()` →
   `run()` → envelope. Enforced by `Base_Rank_Math_Ability::execute()`, not by each subclass.
3. **Permissions** — `Rank_Math_Guard::can( $rm_cap, $floor )` returns
   `current_user_can( $floor ) && has_cap( $rm_cap )`, filtered through
   `acrossai_abilities_manager_rank_math_permission`. The floor is **`manage_options` for every
   ability in the suite**, declared `final` on the base so it cannot be lowered. The post-scoped
   abilities additionally perform a per-object `current_user_can( 'edit_post', $id )` inside `run()`
   as defence in depth, and the schema writers additionally re-assert `edit_user` / `edit_terms` and
   verify meta-row ownership — see the security note under `rank-math-schema`.
4. **Destructive abilities** require `confirm: true` (boolean, default `false`) in `input_schema` and
   declare `annotations.destructive = true`. Reversible operations do not.
5. **`meta`** — `show_in_rest => true`, `mcp => ['public' => false, 'type' => 'tool']`, and the
   `annotations` triple.
6. **Entitlement gating** — #39–#44 register whenever Rank Math is present and fail at runtime with
   `rank_math_account_required` / `content_ai_no_credits` / `rank_math_pro_required`.
7. **`additionalProperties => false`** on every `input_schema`.

---

## `rank-math-settings` (6)

> **#1 and #57 were renamed.** They shipped as `get-settings` and `get-sitemap-status`.
> Rank Math 1.0.277 claimed both names for abilities of its own, and the Abilities API gives
> the name to whichever plugin registers first — so one side silently lost its ability and
> callers got the other side's schema. The host plugin owns its namespace, so ours moved to
> `get-settings-panel` and `get-sitemap-health`, which is also what each actually returns:
> one panel with its field specification, and a live sitemap health check.

| # | Slug | Input | Output payload |
|---|---|---|---|
| 1 | `get-settings-panel` | `panel` (enum, 20 values, required), `object` (string — post type or taxonomy, required when the panel is dynamic) | `panel`, `object`, `fields[]` of `{id, type, enum, min, max, default, current, readonly}`, `state` (robots-txt panel only) |
| 2 | `update-general-settings` | `section` (enum: links, breadcrumbs, webmaster, image-seo, 404-monitor, redirections, robots-txt, others), `settings` (object), | `section`, `updated{}` (only the fields written), `notifications[]` from Rank Math |
| 3 | `update-title-settings` | `scope` (enum: post-type, taxonomy, homepage, author, misc, global, social, local-seo), `object` (required for post-type/taxonomy), `settings` (object) | `scope`, `object`, `updated{}`, `notifications[]` |
| 4 | `update-sitemap-settings` | `scope` (enum: general, post-type, taxonomy), `object`, `settings` (object) | `scope`, `object`, `updated{}`, `notifications[]` |
| 5 | `update-instant-indexing-settings` | `settings` (object: `bing_post_types` multicheck, `indexnow_api_key` text) | `updated{}`, `key_location` (derived, read-only) |
| 6 | `update-robots-txt` | `content` (string, multi-line) | `content`, `state{editable, robots_locked, site_not_public, physical_file_exists}` |

**Errors**: `unknown_field` (names the field and panel), `protected_field`, `invalid_input`,
`insufficient_capability` (#1 only — the panel-specific cap is re-checked inside `run()` because
`permission_callback` receives no input).

**#1 permission note**: gated at the least-privileged combination (`manage_options` +
`rank_math_general`) with the panel's own cap re-checked in `run()`. This is the only runtime
capability check in the suite; it is deliberate, not an oversight.

**#6 precondition**: `Helper::is_edit_allowed()` must be true and no physical `robots.txt` may exist,
else the write is refused with the state reported.

---

## `rank-math-instant-indexing` (4)

| # | Slug | Input | Output payload |
|---|---|---|---|
| 7 | `submit-urls` | `urls` (string[], required, min 1) | `submitted[]`, `accepted` (bool), `response_code` |
| 8 | `get-indexing-log` | `filter` (enum: all, manual, auto — default all), `limit` (int, default 50, max 500) | `entries[]` of `{url, time, time_formatted, time_human, manual}`, `count` |
| 9 | `clear-indexing-log` | `confirm` | `cleared` (bool) |
| 10 | `reset-indexing-key` | `confirm` | `key`, `key_location` |

**#7**: calls `Api::submit( $urls, true )` — the manual path, which is **not** subject to
`THROTTLE_LIMIT`. Maps HTTP status to `indexnow_400`, `indexnow_403_invalid_key`, `indexnow_422`,
`indexnow_429_rate_limited`, `indexnow_500`.
**#10** is destructive because the previous key file becomes stale and search engines reject
submissions until the new key is verified.

---

## `rank-math-redirections` (9)

| # | Slug | Input | Output payload |
|---|---|---|---|
| 11 | `update-redirection` | `id` (int, required), `sources[]`, `url_to`, `header_code` (enum 301/302/307/410/451), `status` (enum active/inactive) | `id`, `redirection{}` |
| 12 | `change-redirection-status` | `ids` (int[], required), `action` (enum: activate, deactivate, trash, restore) | `ids`, `action`, `changed` (int) |
| 13 | `delete-trashed-redirections` | `confirm` | `deleted` (int) |
| 14 | `get-redirection-stats` | — | `counts{active, inactive, trashed, all}`, `stats{total, hits}` |
| 15 | `export-redirections` | `format` (enum: json, apache, nginx — default json) | `format`, `content` (string, for apache/nginx), `redirections[]` (json), `format_parity`, `warnings[]` |
| 51 | `list-redirections` | `status` (enum: all, active, inactive, trashed — default all), `limit`, `offset`, `search` | `redirections[]`, `count`, `total` |
| 52 | `find-redirection` | `url` (string, required), `active_only` (bool, default true), `limit` | `path`, `matches[]`, `count` |
| 53 | `create-redirection` | `sources[]` (required), `url_to`, `header_code`, `status` | `id`, `redirection{}` |
| 54 | `delete-redirections` | `ids` (int[], required), `confirm` | `deleted` (int) |

**#11 / #53**: `is_infinite_loop()` runs **before** `save()`. A new redirection that loops is saved
but auto-deactivated → `infinite_loop_new`; an update that would loop is refused →
`infinite_loop_update`. `save()` returning false → `no_valid_source`.
**#12** is `destructive: false` — all four transitions are reversible. Hard delete is #54, separately
gated.
**#15** apache/nginx output comes from the ported serializers; rules whose regex is invalid are
emitted commented-out in Apache and omitted from Nginx, and listed in `warnings[]`.
**#51** must implement `status=trashed`, or #13 has no discovery path.

---

## `rank-math-role-manager` (2)

| # | Slug | Input | Output payload |
|---|---|---|---|
| 16 | `get-role-capabilities` | `role` (string, optional — all roles when omitted) | `capabilities[]` (the 16 Rank Math cap ids with labels), `roles{}` role → cap → bool |
| 17 | `reset-role-capabilities` | `confirm` | `roles_reset` (int) |

No bulk writer ships: `Helper::set_capabilities()` strips every registered cap absent from the
payload across **all** roles. Use the plugin's existing `users/add-role-capability` /
`users/remove-role-capability` for grants — `rank_math_*` caps are ordinary WP caps.

---

## `rank-math-status` (8)

| # | Slug | Input | Output payload |
|---|---|---|---|
| 19 | `get-status` | `panel` (enum: status, tools, import_export, version_control, google — default status), `include_sites` (bool, default false) | `panel`, `data{}` |
| 20 | `run-maintenance-tool` | `tool` (enum, 12 values, required), `confirm` | `tool`, `completed`, `async`, `tool_message`, `poll_hint` |
| 21 | `export-settings` | `panels[]` (optional — all when omitted) | `panels[]`, `data{}` |
| 22 | `import-settings` | `data` (object, required), `confirm` | `panels_imported[]`, `backup_key` |
| 23 | `list-backups` | — | `backups[]` of `{key, date}`, `count` |
| 24 | `create-backup` | — | `key`, `date` |
| 25 | `manage-backup` | `action` (enum: restore, delete), `key` (required), `confirm` | `action`, `key` |
| 26 | `detect-seo-plugins` | — | `detected[]` of `{importer, name, choices[]}` |

**#19 `panel=google`** calls `Console::get_sites()` only when `include_sites: true` — it makes a live
Google API request. `panel=version_control` is read-only; rollback and beta opt-in are out of scope.
**#20** dispatches through a static `[class, method]` map, never `apply_filters`. Tools whose module
is inactive return `tool_unavailable` naming the module.
**#22** overwrites whole option blobs; `backup_key` is returned so the caller can undo via #25.

---

## `rank-math-sitemap` (3)

| # | Slug | Input | Output payload |
|---|---|---|---|
| 27 | `invalidate-sitemap-cache` | `scope` (enum: all, type, post — default all), `type`, `post_id` | `scope`, `invalidated`, `cache_enabled` |
| 57 | `get-sitemap-health` | — | `module_active`, `object_types[]`, `rewrite{}`, `index_check{}` |
| 58 | `list-sitemap-urls` | `include_children` (bool, default true), `limit` (default 250, max 1000) | `sitemaps[]`, `urls[]`, `count` |

**#27**: `scope=post` uses `Cache_Watcher::invalidate_post()` followed by `clear_queued()`, because
the watcher otherwise defers the work to `shutdown`. `cache_enabled: false` means the call was a no-op.

---

## `rank-math-modules` (2)

| # | Slug | Input | Output payload |
|---|---|---|---|
| 28 | ~~`set-module-state`~~ | *Retired.* Rank Math's own `set-module-status` does the same work and more (several modules per call, dependency and PRO-availability checks), including the rewrite-rule refresh and `rank_math/module_changed` action this row existed to guarantee. |
| 63 | `list-modules` | — | `modules[]` of `{id, label, active, disabled, pro}` |

**#28** replicates `Admin_Rest::save_module()` in full, including the rewrite-rule refresh and the
`rank_math/module_changed` action. Omitting either leaves stale rewrite rules.

---

## `rank-math-analytics` (4)

| # | Slug | Input | Output payload |
|---|---|---|---|
| 29 | `get-analytics-summary` | `report` (enum: dashboard, analytics, keywords, posts, optimization, post), `date_range` (enum, default `-30 days`), `post_type`, `post_id` (required for `report=post`) | `report`, `date_range`, `data{}`, `comparison{}` |
| 30 | `get-analytics-rows` | `dataset` (enum: posts, keywords, keywords-overview), `date_range`, `page`, `per_page`, `order_by`, `order`, `search` | `dataset`, `rows[]`, `total` |
| 31 | `get-index-status` | `page`, `per_page`, `order_by`, `order`, `search`, `filter`, `filter_type` | `results[]`, `total` |
| 32 | `inspect-url` | `url` (required), `mode` (enum: schedule, now — default schedule) | `url`, `mode`, `scheduled`, `result{}` |

All route through `Analytics_Repository`, which calls `set_date_range()` explicitly — without it
Rank Math falls back to a browser-cookie value that abilities do not have.
**#31**: a `null` return means the storage table is absent → `inspections_table_missing`, with a hint
to run `run-maintenance-tool(tool=recreate_tables)`.
**#32 `mode=now`** consumes Google Search Console daily quota and is `idempotent: false`.
Google account connect/disconnect is `wp_ajax_`-only and out of scope.

---

## `rank-math-content` (11)

| # | Slug | Input | Output payload |
|---|---|---|---|
| 33 | `bulk-update-meta` | `object_type` (enum: post, term), `rows` (object: id → `{focus_keyword?, title?, description?, image_alt?, image_title?}`) | `processed[]`, `skipped[]` of `{id, reason}` |
| 34 | `update-post-schemas` | `object_type`, `object_id`, `schemas` (object keyed `new-<n>` or `schema-<meta_id>`) | `object_id`, `saved[]` |
| 35 | `delete-post-schemas` | `post_id` (required), `confirm` | `post_id`, `deleted` |
| 36 | `update-seo-scores` | `scores` (object: post_id → 0–100) | `updated[]`, `skipped[]` |
| 37 | `get-rendered-head` | `url` (required) | `url`, `head` (string) |
| 45 | `update-seo-meta` | `post_id` (required), plus optional `title`, `description`, `focus_keyword`, `robots[]`, `canonical_url`, `is_pillar`, `is_cornerstone` | `post_id`, `updated{}` |
| 46 | `get-primary-term` | `post_id`, `taxonomy` | `post_id`, `taxonomy`, `primary_term`, `assigned[]` |
| 47 | `update-primary-term` | `post_id`, `taxonomy`, `term_id` (0 clears) | `post_id`, `taxonomy`, `primary_term` |
| 48 | `audit-content-seo` | `post_types[]`, `post_statuses[]`, `per_page`, `page`, `search`, `score_below`, `include_schema`, `include_inbound`, `only_issues` (default true) | `items[]`, `total`, `page`, `pages`, `counts{}` |
| 49 | `get-inbound-links` | `target_post_id`, `target_url`, `post_types[]`, `include_sources`, `include_menus`, `min_count`, `limit` | `items[]`, `count`, `scanned_sources` |
| 50 | `audit-faq-links` | `post_types[]`, `per_page`, `page` | `items[]`, `count` |

**#33/#36**: Rank Math's own handlers silently skip unprocessable rows and always return success, so
these abilities pre-compute `processed[]` / `skipped[]` with reasons via
`Helper::is_post_type_accessible()` and `Helper::get_allowed_taxonomies()`.
**#34** is `idempotent: false` — `new-*` keys append.
**#37** is an HTTP loopback to `/wp-json/rankmath/v1/getHead`. Precondition
`general.headless_support` → else `headless_support_disabled`.
**#45** encodes `robots` as an array and the flags as `'on'` / absent, which the generic
`content/update-post-meta` would not.
**#48** with `only_issues: false` is the bulk metadata reader; there is no separate bulk-read ability.

---

## `rank-math-schema` (1)

| # | Slug | Input | Output payload |
|---|---|---|---|
| 62 | `get-schema-status` | — | `publisher_type`, `publisher_id`, `publisher_name`, `website_name`, `logo{}`, `same_as[]`, `local_seo{}` |

Returns the **effective computed** publisher output, not raw stored fields — that is what
distinguishes it from #1 `get-settings-panel(panel=titles-local-seo)`.

---

## `rank-math-seo-analysis` (0 — retired)

| # | Slug | Input | Output payload |
|---|---|---|---|
| 38 | ~~`get-seo-analysis-results`~~ | *Retired.* Rank Math's own `audit-site-seo` with `refresh: false` reads the stored run, and can also re-run it or audit a single URL. |

Reads stored results only; never re-runs the analyzer, because Rank Math core already ships
`rank-math/audit-site-seo`. No stored run → `success: true, has_results: false` (not an error).

---

## `rank-math-content-ai` (4) — entitlement-gated

| # | Slug | Input | Output payload |
|---|---|---|---|
| 39 | `get-content-ai-status` | — | `connected`, `credits`, `plan`, `usage{}`, `reachable` |
| 40 | `manage-content-ai-prompts` | `action` (enum: save, update, update-recent), `prompts[]` / `prompt{}` | `action`, `prompts[]` |
| 41 | `manage-content-ai-output` | `action` (enum: save, delete), `output{}` / `key` | `action`, `outputs[]` |
| 42 | `research-keyword` | `keyword` (required), `country`, `confirm` | `keyword`, `research{}`, `credits_before`, `credits_after` |

**#42** verifies `Helper::get_credits() > 0` **before** any remote call →
`content_ai_no_credits`. `generateAlt` and `createPost` are out of scope (core ships an alt generator;
`createPost` overlaps the plugin's own `Content\Create_Post`).

---

## `rank-math-ai-visibility` (2) — entitlement-gated

| # | Slug | Input | Output payload |
|---|---|---|---|
| 43 | `get-ai-visibility-brand` | `brand_id` (required) | `brand{}` |
| 44 | `update-ai-visibility-object` | `target` (enum: brand, query, generate-queries), `brand_id` (required), `query_id` (required for `query`), `data{}`, `confirm` (required for `generate-queries`) | `target`, `brand_id`, `result{}` |

Core already covers overview, brand insights, brand queries and brand creation. `#44` is declared
`idempotent: false` because `generate-queries` is not idempotent; `brand` and `query` updates in fact
are, which the description states.

---

## Capability map

| Rank Math cap | Abilities |
|---|---|
| `general` | 2, 5, 6, 7, 8, 9, 10, 15, 21, 22, 23, 24, 25, 37, 59, 60 |
| `titles` | 3, 62 |
| `sitemap` | 4, 27, 57, 58 |
| `redirections` | 11, 12, 13, 14, 51, 52, 53, 54 |
| `role_manager` | 16, 17 |
| `404_monitor` | 55, 56 |
| `analytics` | 29, 30, 31, 32 |
| `site_analysis` | 38 |
| `onpage_general` | 33, 45, 46, 47, 48, 50 |
| `onpage_snippet` | 34, 35 |
| `link_builder` | 49 |
| `content_ai` | 39, 40, 41, 42 |
| *(floor only)* | 19, 20, 26, 28, 36, 43, 44, 63 |
| *(dynamic, from the panel)* | 1 |

---

## `rank-math-404-monitor` (2)

| # | Slug | Input | Output payload |
|---|---|---|---|
| 55 | `list-404-logs` | `limit`, `offset`, `search`, `order_by`, `order` | `logs[]`, `count`, `total` |
| 56 | `delete-404-logs` | `ids` (int[], required), `confirm` | `deleted` (int) |

Clear-all is #20 `run-maintenance-tool(tool=delete_log)`; there is no third ability.

---

## `rank-math-routes` (2)

| # | Slug | Input | Output payload |
|---|---|---|---|
| 59 | `get-llms-status` | `preview_lines` (int, default 12, max 100) | `module_active`, `route_url`, `rewrite{}`, `settings{}`, `live_preview{}` |
| 60 | `refresh-llms-route` | — | `rule_present_before`, `flushed`, `rule_present_after` |

`get-rewrite-status` was dropped: `settings/list-rewrite-rules` already returns the full persisted
rewrite map, and #59 already reports the llms.txt rule's presence.
