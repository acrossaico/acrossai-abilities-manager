# Quickstart: Rank Math Ability Suite

**Feature**: 069-rank-math-abilities

## Environment

`php` is not on `PATH` in this Local (Flywheel) environment. Prefix tooling:

```bash
export PATH="$HOME/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin/bin:$PATH"
php vendor/bin/phpstan analyse --level=8 --no-progress
php vendor/bin/phpunit --testsuite feature-069-unit
```

Baseline at feature start: PHPStan level 8 clean, and no `ignoreErrors` entry is needed for
`\RankMath\*` — the Elementor suite already references `\Elementor\Plugin::$instance` directly with a
clean baseline.

## Verifying the admin UI

1. Ensure Rank Math is active.
2. Visit **AcrossAI → Integrations**.
3. Confirm a **Rank Math** tab appears — the label is auto-derived from
   `meta.acrossai.tab_group = 'rank-math'` by `titleCaseTabLabel()`, so nothing declares the string
   "Rank Math".
4. Confirm the card title reads "Acrossai Abilities Manager Rank Math" (auto-derived from the category
   slug) and that the sub-group headings render.
5. Deactivate Rank Math and reload: the tab must disappear with no notices or fatals.

Do this at **Batch 1**, not at the end. Feature 078 existed solely because a suite shipped with
`tab_group => 'core'` and rendered under the wrong tab.

## Relaxing the capability policy

Abilities require the house floor **and** Rank Math's granular capability. To allow an editor holding
only `rank_math_titles` (Rank Math's own model, looser than ours):

```php
add_filter(
	'acrossai_abilities_manager_rank_math_permission',
	static function ( bool $allowed, string $rm_cap, string $floor ): bool {
		if ( 'titles' === $rm_cap ) {
			return current_user_can( 'rank_math_titles' );
		}
		return $allowed;
	},
	10,
	3
);
```

## Integration checks — run locally, skipped in CI

These cannot be verified by source inspection. Each is written as a PHPUnit test guarded by
`markTestSkipped()` when `! class_exists( '\RankMath\Helper' )`, and is **not** listed in the
`feature-069-unit` suite.

### 1. Partial-write safety (research F1 and F8)

The entire settings design rests on this.

```
$before = RankMath\Helper::get_settings( 'general' );
→ rank-math/update-general-settings { section: "breadcrumbs", settings: { breadcrumbs_separator: "›" } }
$after  = RankMath\Helper::get_settings( 'general' );
```

Assert every key except `breadcrumbs_separator` has the same **effective value**.

Compare through `Helper::get_settings()`, **not** raw `get_option()`. Rank Math's `save_settings()`
reads the blob through that accessor, which casts `'off'` → `false`, and then persists the merged cast
array — so the first save on a site with legacy string toggles rewrites all of them at the byte level
(research F8). Its own admin UI does exactly the same. Raw `wp_options` comparison therefore reports
~20 spurious "changes" on a non-normalised site and none on a normalised one, which makes it a flaky
assertion rather than a meaningful one.

Then assert idempotence, which is the real invariant:

```
→ (same call again)
```

Zero fields differ at the byte level on the second write.

### 2. Newline preservation (research F2)

Use an **unprotected** multi-line field. `robots_txt_content` and `custom_webmaster_tags` are poor
tests here: `sanitize_by_field_id()` protects both by ID regardless of the type passed (F2b), so they
round-trip correctly even with a broken type map. The real test is a `textarea_small` field, which
falls through to the lossy `default` branch unless the registry maps it to `textarea` (F2c).

```
→ rank-math/update-general-settings {
    section: "links",
    settings: { nofollow_domains: "example.com\nexample.org\nexample.net" }
  }
→ rank-math/get-settings-panel { panel: "general-links" }
```

Assert the two `\n` characters survived. If the value comes back as
`"example.com example.org example.net"`, the registry emitted `textarea_small` verbatim instead of
mapping it to `textarea`, and Rank Math's `sanitize_text_field()` collapsed the whitespace.

Repeat for `rss_before_content` (`section: "others"`) and `pt_post_image_customfields`
(`update-sitemap-settings { scope: "post-type", object: "post" }`) — the other unprotected
`textarea_small` fields.

### 3. Maintenance tool dispatch (research F3)

```
→ rank-math/run-maintenance-tool { tool: "clear_transients", confirm: true }
```

Assert a real count is returned, **not** the string `Something went wrong.` — that string means the
implementation used `apply_filters( 'rank_math/tools/...' )`, whose listeners only exist during an
actual `/toolsAction` REST request.

### 4. Loopback isolation (research F4)

```
→ rank-math/get-rendered-head { url: "<any post permalink>" }
→ rank-math/get-settings-panel { panel: "general-links" }
```

Both in the same PHP request. Assert the second still succeeds. If it fails, the head was fetched
in-process and `remove_all_actions()` corrupted global state.

### 5. Role-capability isolation

```
→ users/add-role-capability { role: "editor", capability: "rank_math_titles" }
→ rank-math/get-role-capabilities
```

Assert `administrator` retains every Rank Math cap. (This is why no bulk writer ships —
`Helper::set_capabilities()` strips omitted roles.)

### 6. Infinite-loop detection

Create a redirection whose source resolves to its own destination.

```
→ rank-math/create-redirection { sources: [{pattern: "/loop", comparison: "exact"}], url_to: "/loop" }
```

Assert `error_code: "infinite_loop_new"` and that Rank Math stored the rule with status `inactive`.
Then attempt the same shape via `update-redirection` and assert `infinite_loop_update` with no write.

### 7. Repeatable-group round-trip

```
→ rank-math/update-title-settings {
    scope: "local-seo",
    settings: { opening_hours: [ { day: "Monday", time: "09:00-17:00" }, { day: "Tuesday", time: "10:00-16:00" } ] }
  }
```

Assert `Local_Seo::get_opening_hours()` resolves both rows. A single collapsed row means the group was
not `array_values()`-reindexed before reaching `Sanitize_Settings::sanitize_group_value()`.

### 8. Module state leaves no stale rewrite rules

```
→ rank-math/set-module-status { modules: { "llms-txt": false } }
→ rank-math/get-llms-status
→ rank-math/set-module-status { modules: { "llms-txt": true } }
→ rank-math/get-llms-status
```

Assert the llms.txt rewrite rule is absent after `off` and present after `on`, and that
`/llms.txt` actually resolves — not a 404.

### 9. Credit guard makes no remote request

With a zero Content AI balance:

```
→ rank-math/research-keyword { keyword: "test", confirm: true }
```

Assert `error_code: "content_ai_no_credits"` and that no outbound HTTP request was made (hook
`pre_http_request` and fail the test if it fires).

## Re-diffing Settings_Registry after a Rank Math upgrade

`Settings_Registry` hand-mirrors Rank Math's CMB2 field definitions, which are dead code in React mode
and therefore not covered by Rank Math's own tests. After upgrading Rank Math:

1. Each panel entry carries a `source` value naming the Rank Math `file:line` it mirrors.
2. `git diff` those files between the old and new Rank Math tags.
3. Any added field is safe — unknown keys are rejected loudly rather than written as `text`.
4. Any **renamed** field or **changed type** must be updated in the registry. A type change from
   `textarea` to `text` (or a rename) is the failure mode that silently loses data.
5. Re-run integration checks 1, 2 and 7.
