# Feature 136 — put a plugin, a theme or core on a version you choose

> Feature number provisional — let `/speckit.specify` assign the next free one (135 is filesystem root resolution).

**The ask.** Recover from a bad update. "Put that plugin back on the version it was on yesterday" is
the most common thing an operator needs after an update breaks a site, and no ability in this suite
can do it.

## What exists today, measured

Every ability in the core / plugin / theme family was executed against a live site to establish
this, not read off the source.

| action | core | plugins | themes |
|---|---|---|---|
| check for updates | `core/check-wp-core-update` | `plugins/check-plugin-updates` ¹ | covered by ¹ |
| move forward | `core/update-wp-core` **+ `version`** | `plugins/update-plugin` (pending only) | `themes/update-theme` |
| **move to a chosen version** | **`core/rollback-wp-core`** | **absent** | **absent** |
| reinstall same version | `core/reinstall-wp-core` | absent | absent |
| verify checksums | `core/verify-core-checksums` | `plugins/verify-plugin-checksums` | absent ² |
| install | — | `plugins/install-plugin` (latest only) | `themes/install-theme` (latest only) |

¹ `plugins/check-plugin-updates` returns core, plugins **and** themes — confirmed live: core plus 35
plugins plus 1 theme in one response. The name undersells it, which is tracked separately.

² Not an oversight: WordPress.org publishes no theme checksum manifests, and WP-CLI ships no
`wp theme verify-checksums` either. Recorded and closed as not planned (#307).

So `core/rollback-wp-core` is the only version-targeting ability in the whole suite. For plugins and
themes:

- `install-plugin` and `install-theme` accept **no `version` parameter** — verified, zero
  occurrences in either file. They always fetch latest.
- `update-plugin` applies only an update WP.org is already offering; re-running with nothing pending
  is a documented no-op, confirmed live.

Once a plugin is on 4.3.5 there is no route to 4.3.1.

## The two plugins, and what they actually do

### WP Rollback — plugins and themes

200,000 installs, 98% rating, 3.2.0 (current). Its own route definition is the authority:

```php
// vendor/wp-rollback/shared-core/src/RestAPI/ProcessRollbackApiRoute.php:46
'type'    => [ 'enum' => ['plugin', 'theme'] ],
'slug'    => [ 'enum' => array_merge( getPluginsSlug(), getThemesSlug() ) ],
'version' => [ 'type' => 'string' ],
```

- **plugins and themes, yes. Core, no** — the enum is exactly those two, and the only subsystems
  present are `PluginRollback/` and `ThemeRollback/`. No `CoreRollback/`.
- **Blocks are plugins.** WP.org blocks ship as plugins, so "block" needs no separate type.
- **Either direction.** `version` is a free string, so downgrade or upgrade.
- `slug` is enum-restricted to *installed* assets, so it re-versions what is there; it cannot
  install something new.

Programmatic surface:

```
REST  wp-rollback/v1
  POST /process-rollback/     perform
  GET  /rollback-steps/       the staged plan
  GET  /fetch-info/           available versions; 404s as asset_not_found
services  BackupService · PackageValidationService · MaintenanceService · NetworkAssetUsageService
ajax      wp_ajax_wpr_process_rollback
```

**WordPress.org only.** Version history comes from `api.wordpress.org/plugins/info/1.2` and
`.../themes/info/1.2`, packages from `downloads.wordpress.org`. On a site like the reference staging
install that excludes Elementor Pro, Gravity Forms and its add-ons, Astra Pro, Admin Columns Pro,
LearnDash, Yoast Premium, ShopLentor Pro, WP Mail SMTP Pro and the WooCommerce extensions — roughly
half of what is installed. It works for the free half: Elementor, WooCommerce, Akismet, Jetpack,
Query Monitor, ShortPixel, Yoast free, Classic Editor.

### WP Downgrade — core only, and it *pins*

100,000 installs, 96% rating, 1.2.9. Procedural, no classes. Read from
`plugins.svn.wordpress.org/wp-downgrade/trunk/`:

```
options  wpdg_specific_version_name    the pin
         wpdg_download_url
         wpdg_edit_download_url
hooks    pre_site_option_update_core   -> wpdg_specific_version
         site_transient_update_core    -> wpdg_specific_version
settings register_setting('wpdg-settings-group', ...), page slug 'wpdowngrade'
```

**It does not perform a downgrade.** It filters the core update transient so WordPress believes the
chosen version is the latest; the move happens on the next core update run. This is the single most
important fact in this document, because an ability that writes the option and reports "downgraded"
would be lying.

What it adds over `core/rollback-wp-core`:

| | ours | WP Downgrade |
|---|---|---|
| target | a version the Core API **offers** | any version number |
| persistence | one-shot — WP re-offers newer next check | **pinned** until cleared |
| direction | downgrade only (refuses `>=` current) | both |
| custom package URL | no | yes (deliberately **out of scope** here) |

The persistence is the real gap: `core/rollback-wp-core` moves core down, and the next update check
offers the newer version again — an auto-update can silently undo it.

### Neither plugin ships a single ability

Verified four ways, because the whole design depends on it:

| check | result |
|---|---|
| `wp_register_ability` / `wp_abilities_api_init` / `WP_Ability` across WP Rollback's 130 PHP files | zero |
| registry count, WP Rollback inactive then active | **388 → 388** |
| `toolset-integrations discover search=rollback` | 0 abilities |
| `diagnostics/list-unclassified-abilities` | 8 unclassified, all `fleet`/`mcp-adapter`/`toolset`; none theirs |

The only `rollback` ability on the site is ours. WP Downgrade defines no classes at all. So these
plugins cannot answer an agent on their own — this feature is the abilities that drive them.

## Requirements

- **FR-1 — version targeting for plugins and themes.** List released versions, and move an
  installed plugin or theme to a chosen one, through WP Rollback's own validation and backup path
  rather than a reimplementation of version swapping.
- **FR-2 — refuse premium assets clearly.** An asset WordPress.org does not publish returns a
  distinct `not_on_wordpress_org` reason naming it. Never a generic failure, and never silence.
- **FR-3 — pre-flight before a destructive move.** Report whether the version exists, what
  `PackageValidationService` says, and what `NetworkAssetUsageService` reports about multisite
  usage. Rolling back a network-activated plugin affects every site, and that is the check a human
  forgets.
- **FR-4 — core version pinning, and honest reporting of it.** Read, set and clear the pin.
  Setting it must state that core has **not** moved and name the ability that moves it.
- **FR-5 — no arbitrary-URL core installs.** Do not write `wpdg_download_url` or
  `wpdg_edit_download_url`. If a custom URL is already enabled, say so and refuse to set a pin,
  because the pin would resolve through a URL this plugin did not set.
- **FR-6 — suggest the host plugin when it is absent.** `suggested_plugins()` has existed since
  Feature 088 and **no ability uses it**. The update and install abilities should point at the right
  host plugin, with `plugin_provides_abilities: false` — which is true, and worth saying, because a
  caller would otherwise expect installing it to produce abilities.
- **FR-7 — bounded responses.** `core/verify-core-checksums` returns 608 KB and exceeds the MCP
  response limit outright, so it returns nothing at all. A version list for a plugin with a long
  release history has the same shape. Cap and paginate from the start.
- **FR-8 — every guard the family already applies.** `confirm` on destructive moves,
  `DISALLOW_FILE_MODS`, `manage_options` plus the relevant update capability, the active-theme
  refusal, and a clean "host plugin not active" response rather than a fatal.

**Non-goals.** No package-URL fallback for premium plugins — it would let an ability install code
from an arbitrary URL and needs its own security review. No core rollback reimplementation;
`core/rollback-wp-core` stays. No theme checksum verification (#307).

## Open questions for `/speckit.plan`

1. **How to invoke WP Rollback.** Its REST routes are nonce-guarded. Calling the service classes
   directly is cleaner but couples to internals; `rest_do_request()` dispatches internally but
   carries the auth question. Decide against the source and record why.
2. **Namespace.** `versions/` reads as the topic and keeps one slug family across both hosts, with
   two `tab_group`s because `is_active()` differs per plugin. The alternative is `rollback/` plus
   `core-pin/`, which pairs slug to host but splits a single concept.
3. **Pinning and our own rollback.** With WP Downgrade installed, `core/rollback-wp-core` and
   `versions/set-core-pin` both move core down by different mechanisms. Decide whether
   `rollback-wp-core` should mention the pin when one is set, since a pin will re-offer the pinned
   version after our rollback completes.
