# Feature 112 — WPCode toolset: 24 abilities, and a home for the 5 WPCode already ships

Prepared for `/speckit-specify`. Every claim below was read from WPCode Lite 2.3.9 source on this
install, with file and line cited.

## WPCode already registers 5 abilities, and they land nowhere

`new WPCode_Abilities_API();` runs at `class-wpcode-abilities-api.php:541`, from a file required
unconditionally at `ihaf.php:367` — outside the `is_admin()` block, so on every request. The
constructor hooks `wp_abilities_api_categories_init` and `wp_abilities_api_init`, registers three
categories of its own, and registers five abilities with no feature flag, setting or version guard.

All five are read-only, and all five set `show_in_rest => true` — notable, because the Abilities API
defaults that to `false`, so WPCode opted into REST deliberately.

| Their ability | Gate |
|---|---|
| `wpcode/list-snippets` | `wpcode_edit_snippets` |
| `wpcode/detect-snippet-errors` | `wpcode_edit_snippets` |
| `wpcode/get-error-logs` | `manage_options` |
| `wpcode/search-library` | `wpcode_edit_snippets` |
| `wpcode/get-settings` | `manage_options` |

They carry no `tab_group`, and no integration claims the `wpcode/` prefix, so
`AcrossAI_Ability_Group::members()` skips them: live on the site and over REST, invisible to our tabs
and our MCP tools. Exactly the position Yoast's five were in before Feature 106.

`wpcode_edit_snippets` is granted on activation only to roles that already hold `manage_options`
(`class-wpcode-capabilities.php:19-23`), so on a default site all five are effectively admin-only.

## What WPCode stores

Snippets are a `wpcode` CPT (`post-type.php:26`) with three taxonomies — `wpcode_type`,
`wpcode_location`, `wpcode_tags` — and ~25 `_wpcode_*` meta keys. Six code types: `php`, `js`, `css`,
`html`, `text`, `universal` (`includes/execute/`). Eleven auto-insert locations:
`site_wide_header`, `site_wide_body`, `site_wide_footer`, `everywhere`, `admin_only`, `before_post`,
`after_post`, `before_content`, `after_content`, `before_paragraph`, `after_paragraph`.

The global header/body/footer scripts — the plugin's original job — live in the legacy
`ihaf_insert_header`, `ihaf_insert_body` and `ihaf_insert_footer` options, output on `wp_head`,
`wp_body_open` and `wp_footer` (`global-output.php:12-45`).

## Why the generic abilities are not enough

Snippets are a CPT, so `content/create-cpt-item`, `content/update-cpt-item` and
`content/update-post-meta` technically reach them. **That route produces snippets that do not run.**

`WPCode_Snippet::save()` ends with `rebuild_cache()` (`class-wpcode-snippet.php:638-650`), which
rewrites the `wpcode_snippets` option — and that option, not the CPT, is what the loader reads
(`auto-insert/class-wpcode-auto-insert-type.php:295` calls `get_cached_snippets()`). A raw CPT write
leaves the cache stale: the snippet looks correct in the database, reads back correctly, and the site
keeps executing the old cache. The write reports success and changes nothing — the ACF repeater
failure shape, and `BUG-WRITE-REPORTED-WITHOUT-READ-BACK`.

`save()` also fires `wpcode_before_snippet_save`, resets `_wpcode_last_error`, and syncs the code-type
term via `wp_set_post_terms()`. None of that happens on a raw write.

**Activation is worse to bypass.** `WPCode_Snippet::activate()` runs `run_activation_checks()`
(`class-wpcode-snippet.php:653+`), which test-executes `php` and `universal` snippets to catch fatals
before they go live. Flipping `post_status` directly skips it — a bad snippet goes straight to a white
screen, recoverable only through safe mode.

| The state | Already handled by | Why we still wrap it |
|---|---|---|
| Snippet CPT rows | `content/*-cpt-item` | Stale `wpcode_snippets` cache; no activation check |
| `_wpcode_*` meta | `content/update-post-meta` | Protected keys; no compile, cache or term sync |
| Global scripts | `options/get-option`, `options/update-option` | Reachable, but the `headers_footers_mode` setting and the legacy `ihaf_*` key names are undiscoverable |
| Settings blob | `options/get-option` | WPCode's own reader already ships |

## Scope — 24 new, 29 in the tab

New category `acrossai-wpcode`, `tab_group => 'wpcode'`, namespace **`snippets/`** — the `wpcode/`
namespace stays theirs. Own toolset gated on WPCode being active, the Classic Editor / LiteSpeed /
Contact Form 7 shape. `ability_prefixes()` returns `array( 'wpcode/' )` so their five are adopted into
the tab and the MCP tool without being re-registered.

- **Snippets (8)** — `list-snippets`, `get-snippet`, `create-snippet`, `update-snippet`,
  `delete-snippet`, `activate-snippet`, `deactivate-snippet`, `duplicate-snippet`.
- **Placement (3)** — `set-location`, `set-conditional-logic`, `list-locations`.
- **Global scripts (2)** — `get-global-scripts`, `update-global-scripts`.
- **Diagnostics (3)** — `get-snippet-status`, `list-snippet-errors`, `clear-snippet-errors`.
- **Library and packs (8)** — `search-library`, `get-library-snippet`, `install-library-snippet`,
  `list-packs`, `apply-pack`, plus three that only mean anything once the site is signed in to the
  WPCode library: `list-snippet-updates`, `update-snippet-from-library`, `install-shared-snippet`.

## The connected library

Signing in stores `wpcode_library_api_auth` — an auth key, a webhook secret and a client id. **No
ability reaches any of them**, which is the Feature 106 lesson about Yoast's stored OAuth tokens
applied before it could bite.

**Connecting is a human step, and the abilities say so precisely.** It authorises an external WPCode
account, so nothing here can perform it. `not_connected_error()` returns the exact admin URL
(`admin.php?page=wpcode-library`), names the Connect button, and explicitly asks the caller to report
back once it is done — an instruction, not a dead end, because an assistant given a vague failure
either gives up or silently retries the same call. The URL is also on the error data for machine use.
The library readers carry `library_connected` and `connect_url` in their responses, so the state is
known before a call fails rather than after.

**There is no `get-library-connection` ability, deliberately.** "Is the library connected" is only
ever interesting when something else has just failed for want of it, so a dedicated ability would
spend a tool call learning a fact the failing call can state directly — and would add a tool to the
group for an answer nobody asks on its own. The state is reported inside `library_not_connected`
instead, and the multi-step library flows carry `suggested_abilities` naming their own next step.

Connecting unlocks a real gap: an installed library snippet is copied in once and then never changes,
so a fix published upstream never arrives. `list-snippet-updates` surfaces the stale ones —
measured on this install, it immediately found a pre-existing sample snippet with no recorded
version. `update-snippet-from-library` pulls the new copy, and **restores the local active state
afterwards**, because WPCode's own updater saves the library payload wholesale and would otherwise
switch a deliberately disabled snippet back on as a side effect of an update.

## Constraints — traps, not preferences

- **Never write the CPT or meta directly.** Everything routes through `WPCode_Snippet`,
  `WPCode_Library` and `WPCode_Packs`, behind one repository. Architecture test asserts no
  `wp_insert_post` / `wp_update_post` / `update_post_meta` anywhere in the suite outside it.
- **Read back after saving**, and assert the `wpcode_snippets` cache changed — that option is what
  decides whether the snippet runs, so it is the only honest proof the write landed.
- **PHP is written like any other code type.** The RCE risk was raised explicitly and the decision
  was to treat PHP as a normal type, confirm-gated on create, update and activate for `php` and
  `universal`. Two guards are retained because both are correctness rather than caution:
  activation goes through `run_activation_checks()` (skipping it produces white screens), and any PHP
  write refuses while WPCode's own `completely_disable_php` setting is on (ignoring it would override
  an explicit site policy).
- **A library install runs someone else's code.** `install-library-snippet` and `apply-pack` are
  confirm-gated and install **inactive**, so activation stays a deliberate human act.
- **Safe mode changes every answer.** With `?wpcode-safe-mode=1` nothing executes
  (`safe-mode.php:95`). `get-snippet-status` reports it, and the writers say plainly that a snippet
  saved now will not run until it is off.
- **Their five keep their own gates.** Adoption does not re-register them. The toolset description
  notes that `wpcode_edit_snippets` is admin-equivalent by default.
- **Floor `manage_options`** on all 21; `final` classes; rows never maps in `array`-typed output
  (`BUG-ARRAY-TYPED-OUTPUT-IS-A-JSON-OBJECT`); raise-only permission filter from the start.

## Verification

**Every one of the 21 is executed live — no sampling.** Across features 106–111 a green unit suite
never once caught what live execution caught. Each ability gets at least one success path and at least
one named failure path, run in process through a temporary mu-plugin REST route calling
`wp_get_ability( $name )->execute( $input )`, removed before committing.

Beyond the per-ability matrix:

1. **The cache proof.** Create and activate a CSS snippet, read `wpcode_snippets` straight from the
   database and confirm it contains the snippet. Then write an equivalent snippet through
   `content/create-cpt-item` and confirm the cache does **not** — the measurement that justifies the
   suite, taken rather than asserted.
2. **Front-end proof.** For `site_wide_header`, `site_wide_footer` and `before_content`, load a real
   page and confirm the output is in the HTML. A snippet that saves, activates and caches but never
   renders still fails.
3. **The white-screen guard.** Broken PHP through `activate-snippet` must be refused and the site must
   still load — verified by loading the front page, not by trusting the return value.
4. Safe mode on; `completely_disable_php` on; adoption of their five through `toolset/wpcode`.
5. Toolset: `sum(counts) === total`, the group shows 26, then deactivate WPCode and confirm the tab
   and the MCP tool disappear.
6. Restore site state and confirm `wpcode_snippets` and the `ihaf_insert_*` options are byte-identical
   to how they started, the way 111 verified `sidebars_widgets`.

## Out of scope

- **Generators** — they emit code a human reviews; an LLM writing the code directly is the better
  path, so wrapping them adds a step.
- **Pixels** — a settings surface, reachable through the settings abilities, and partly Pro.
- **File editor, duplicator page, live preview** — admin-screen tools. **Search-replace** is out per
  the standing decision that it belongs to a dedicated plugin.
- **Pro-only conditional logic** (device, schedule, WooCommerce, EDD, MemberPress, location, snippet)
  — Lite is installed, so those would ship unverified. `set-conditional-logic` handles page and user
  rules and reports the rest as requiring Pro.
