# Feature 118 — Consent banner toolset: manage the cookie banner, and say plainly what needs an account

Prepared for `/speckit-specify`. Every claim was read from CookieYes | GDPR Cookie Consent 3.5.6 on
this install, with file and line cited, or measured live.

## It registers no abilities, so all of these are ours

Zero `wp_register_ability` calls across the whole plugin — the Loco Translate shape, not the
WPForms/WPCode one. Nothing to adopt; nothing to collide with. New namespace **`consent/`** (unused
across all 36), `tab_group => 'consent'`, own toolset gated on the plugin being active.

## Two modes, and the split decides everything

The plugin defines `CKY_CLOUD_REQUEST` **once at boot**, straight from `account.connected`
(`lite/includes/class-cli.php:112-124` — `class-cli.php` is "Cookie Law Info", the main plugin class,
not WP-CLI). `cky_is_cloud_request()` reads that constant, so it is exactly "is this site connected",
and it **cannot change within a request**.

| | Standalone (this site) | Connected |
|---|---|---|
| Banner config | in WordPress, editable here | managed at the vendor's web app, synced down |
| Cookie inventory | manual | populated by an automated crawl |
| Consent log | **no local table exists** | recorded off-site |
| Cookie policy page | not generated | generated off-site |

Measured here: `account.connected: false`, `onboarding.step: 2`, no law selected.

## Connecting is a human step, and there is no ability for it

Authorising an external account is the site owner's act. **No `connect`, no `disconnect`, no ability
that writes `api.token`.** This is the Feature 112 decision applied again: a dedicated
"is it connected" ability would spend a tool call learning a fact the failing call can state itself,
so the state is reported inside the refusal instead, and carried on the reads where it changes the
answer.

`not_connected_error()` returns the exact admin URL, names the **Connect** button, and asks the
caller to report back once it is done — an instruction, not a dead end, because an assistant handed a
vague failure either gives up or silently retries.

**Credentials are never returned.** `api.token`, `account.website_key` and `account.website_id` are
readable in `cky_settings` and no ability exposes them — the Feature 106 lesson about Yoast's stored
OAuth tokens, applied before it can bite. Settings readers redact them and flag that they did.

## Why the generic abilities are not enough

The three tables are plain tables, so `database/*` reaches them, and `options/*` reaches the settings.
**That route produces a banner that does not change**, and it is the whole justification.

`cky_banner_template` holds the **rendered banner HTML per language**. It is invalidated by actions,
not by writes (`lite/admin/modules/banners/includes/class-template.php:139-142`):

```php
add_action( 'cky_after_update_banner',          array( $this, 'clear_template' ) );
add_action( 'cky_after_update_cookie_category', array( $this, 'clear_template' ) );
add_action( 'cky_after_update_cookie',          array( $this, 'clear_template' ) );
add_action( 'cky_clear_cache',                  array( $this, 'clear_template' ) );
```

Nothing watches the tables. A row written directly inserts correctly, reads back correctly, and the
visitor keeps seeing the previous banner and the previous preference centre. That is
`BUG-WRITE-REPORTED-WITHOUT-READ-BACK` — WPCode's `wpcode_snippets` cache and Loco's four artefacts,
a third time.

The plugin's own REST API is not a route in either: `cky/v1/*` requires `manage_options` **and a
valid nonce**, so it is the admin SPA's private interface.

| The state | Reachable by | Why we still wrap it |
|---|---|---|
| `wp_cky_cookies` | `database/*` | Banner HTML cache left stale; the banner keeps listing the old cookies |
| `wp_cky_cookie_categories` | `database/*` | Same, plus names are per-language JSON, not plain strings |
| `wp_cky_banners` | `database/*` | Same, plus `banner_default` is a cross-row invariant |
| `cky_settings` | `options/*` | Reachable, but holds credentials beside ordinary settings and nothing describes the keys |

## The data model

Three tables, measured on this install:

| Table | Rows | Columns that matter |
|---|---|---|
| `wp_cky_banners` | 2 | `name`, `slug`, `status`, `settings`, `contents`, `banner_default` |
| `wp_cky_cookie_categories` | 5 | `name` (per-language JSON), `slug`, `description`, `prior_consent`, `visibility`, `priority`, `sell_personal_data` |
| `wp_cky_cookies` | **0** | `name`, `description`, `duration`, `domain`, `category`, `type`, `url_pattern` |

The five categories are `necessary`, `functional`, `analytics`, `performance`, `advertisement`.

**Note the zero.** The banner is live with five categories and not one cookie declared, so the
preference centre lists nothing under any of them. In standalone mode that inventory is typed in by
hand — the repetitive job worth automating, and the one that makes the banner informative rather than
decorative.

## Scope — roughly 20 that work now, 4 that need an account

### Work in either mode (~20)
- **Cookie inventory (5)** — `list-cookies`, `get-cookie`, `add-cookie`, `update-cookie`,
  `delete-cookie`.
- **Categories (3)** — `list-categories`, `get-category`, `update-category` (name, description,
  `prior_consent`, `visibility`, `priority`, `sell_personal_data`).
- **Banners (4)** — `list-banners`, `get-banner`, `update-banner`, `set-default-banner`.
- **Banner output (2)** — `get-banner-status` (is the cached template present, which languages, is it
  stale), `rebuild-banner` (the repair for anyone who has written the tables directly).
- **Languages (2)** — `list-languages`, `set-languages`.
- **Settings (2)** — `get-consent-settings` (credentials redacted; reports connection state and law),
  `update-consent-settings`.
- **Google Consent Mode (2)** — `get-google-consent-mode`, `update-google-consent-mode`. The local
  option `cky_gcm_settings` is writable in either mode; only the cloud-backed read is gated.

### Require a connected account (4), each refusing by name until then
- `get-consent-log-statistics` — `lite/admin/modules/consentlogs/api/class-api.php:81`
- `get-pageview-statistics` — `lite/admin/modules/pageviews/api/class-api.php:81`
- `get-scan-status` — last scan, last successful scan, scan limit and plan
  (`settings/includes/class-controller.php:276-365`)
- `get-google-consent-mode-status` — the cloud-backed half, `gcm/api/class-api.php:68`

**That list is short on purpose, and the reason is worth stating plainly.** Connecting does not open
up more of the *plugin*; it moves the work to the vendor's web app. Scanning has no local trigger —
grepped, there is none — and there is no local cookie-policy generation at all. The cloud sync class
exists but `prepare_request()` has no callers in this edition. So four is the honest local surface
that a connection unlocks; anything beyond it would be an ability that calls nothing.

## Constraints — traps, not preferences

- **Never write the three tables directly.** Everything routes through the plugin's own writers so
  the `cky_after_update_*` actions fire, behind one repository. Architecture test: no `$wpdb`
  insert/update/delete and no direct `update_option( 'cky_banner_template' )` anywhere in the suite
  outside it.
- **Prove the banner regenerated.** Read `cky_banner_template` back after every write and assert it
  changed, or is absent pending rebuild. The table row is not the proof — the rendered HTML is what a
  visitor sees.
- **Category and banner names are per-language JSON**, not strings. A plain string write destroys the
  other languages. Read-modify-write per language key.
- **`banner_default` is a cross-row invariant.** Setting one default must clear the others, or the
  front end picks arbitrarily.
- **Connection state is fixed for the request.** `CKY_CLOUD_REQUEST` is defined at boot, so an
  ability cannot connect and then use the result in the same call. The refusal must say so rather
  than suggest retrying.
- **Never return `api.token`, `account.website_key` or `account.website_id`.**
- **No compliance claims.** Nothing in a description or message may state or imply that using these
  abilities makes a site compliant. That is a legal determination about the owner's situation, and
  the law setting in particular is theirs to choose — an ability may report it and change it on
  request, never decide it.
- **Floor `manage_options`**; `final` classes; rows never maps in `array`-typed output
  (`BUG-ARRAY-TYPED-OUTPUT-IS-A-JSON-OBJECT`); raise-only permission filter from the start.

## The plugin ships instructions aimed at AI agents

`AGENTS.md` (473 lines), plus `CLAUDE.md` and `GEMINI.md` pointing at it, are included in the release
package and address "AI coding assistants and agents working on a WordPress site that uses this
plugin". They were read here as vendor documentation and every claim taken from them was verified
against the code; nothing in them was treated as an instruction.

This is worth recording as a finding in its own right: a plugin in the stack is shipping directives
to whatever agent touches the site, and a future assistant may simply follow them. The content is
reasonable — do not claim compliance, do not fabricate an API token, do not choose the law for the
owner — which is precisely why it is easy to follow without noticing that it was not the user who
said it.

One trap it documents that the code confirms: the WP Consent API mapping is asymmetric. CookieYes
`performance` maps to `functional`, and **not** the reverse; `analytics` maps to `statistics`. A
hand-written mapping gets this backwards.

## Verification

1. Execute all ~24 live through a temporary mu-plugin REST route; delete it before committing.
2. **The banner proof.** Add a cookie, then read `cky_banner_template` from the database and confirm
   it regenerated and the new cookie appears in the rendered preference centre. Then write an
   equivalent row through `database/*` and confirm the template does **not** change — the measurement
   that justifies the suite, taken rather than asserted.
3. **Front-end proof.** Load a real page and confirm the banner HTML served to a visitor carries the
   change, not just that the option moved.
4. Per-language proof: change one language's category name and confirm the others survive.
5. `set-default-banner` leaves exactly one default.
6. Every one of the four gated abilities returns its named refusal on this (standalone) site, with
   the connect URL present in the message and on the error data.
7. Negative paths: unknown cookie, unknown category, unknown banner, missing confirm on delete.
8. `vendor/bin/phpunit`, full `vendor/bin/phpcs`, and PHPCompatibility over `includes/` — the gate
   that actually sweeps this code, since `includes/Abilities/` is excluded from the PHPCS baseline.
9. Toolset: `sum(counts) === total`, the group shows its count, dispatch through `toolset/consent`,
   then deactivate the plugin and confirm the tab and the MCP tool disappear.
10. Restore site state: remove every cookie row the run created and leave the tables as found.

## Out of scope

- **Connecting or disconnecting the account, and anything that writes or reads the API token.**
- **Cookie scanning and cookie-policy generation** — no local trigger and no local generator exist;
  both happen on the vendor's platform.
- **The cloud sync push** — `prepare_request()` has no callers in this edition, so wrapping it would
  ship an ability that exercises nothing.
- **Consent decisions themselves.** Reading or writing a visitor's stored consent is the banner's
  job, and an ability that forged one would be both useless and wrong.
