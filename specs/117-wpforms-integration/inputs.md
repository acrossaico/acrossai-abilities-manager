# Feature 117 — WPForms: adopt the 8 abilities it already ships, and restore an off switch for its writes

Prepared for `/speckit-specify`. Every claim below was read from WPForms Lite 2.0.1.1 source on this
install, with file and line cited, or measured live on the running site.

## WPForms registers 8 abilities, and they land in the catch-all

`WPForms\Integrations\Abilities\Abilities` hooks `wp_abilities_api_categories_init` and
`wp_abilities_api_init` (`src/Integrations/Abilities/Abilities.php:68-69`) and registers seven
abilities; the Lite subclass (`src/Lite/Integrations/Abilities/Abilities.php`) adds an eighth. All
eight use the `wpforms/` namespace and the `wpforms-forms` category.

| Ability | Their permission check | Write-gated |
|---|---|---|
| `wpforms/list-forms` | `view_forms` | — |
| `wpforms/get-form` | `view_form_single` (per form) | — |
| `wpforms/describe-editing-schema` | `view_forms` | — |
| `wpforms/get-form-stats` | `view_form_single` (per form) | — |
| `wpforms/create-form` | `create_forms` | yes |
| `wpforms/update-form-settings` | `edit_form_single` (per form) | yes |
| `wpforms/add-field` | `edit_form_single` (per form) | yes |
| `wpforms/update-field` | `edit_form_single` (per form) | yes |

Only `describe-editing-schema` sets `show_in_rest => true`; the other seven take the API default of
false.

Registration is unconditional — `allow_load()` returns `function_exists( 'wp_register_ability' )` and
nothing else (`Abilities.php:48`). No setting, no feature flag, no version guard.

No integration of ours claims the `wpforms` prefix, so `AcrossAI_Ability_Group_Tagger` drops all eight
into `CATCH_ALL_GROUP`. **Measured:**

```
wpforms/list-forms     -> "other"
acf/field-groups       -> "acf"      (for contrast: a claimed prefix works)
```

This is the position WPCode's five and Yoast's five were in before Features 112 and 106.

## Scope — adopt the 8, build nothing new

No new abilities. One integration class that claims the namespace, supplies the tab label and the
toolset description, and carries the eight display rows. The `wpforms` tab and its MCP tool appear
only where WPForms is active.

The decision to add nothing is deliberate: WPForms Lite has no entries, notifications or
confirmations surfaces to wrap — those are Pro — so a "fuller" suite would have shipped a Pro half
that could never be executed here, the reason Yoast Premium was excluded in 106.

## `ability_prefixes()` must be `'wpforms'`, with no trailing slash

`AcrossAI_Ability_Group_Tagger:129-134` takes the segment **before** the first slash and looks that up
in the prefix map:

```php
$slash  = strpos( $name, '/' );
$prefix = false !== $slash ? substr( $name, 0, $slash ) : $name;
```

So `'wpforms/'` would never match anything. This is not hypothetical — it is issue #209: `WPCode`
declares `'wpcode/'` and its five abilities are consequently in `other`, not the `wpcode` tab, despite
the changelog saying otherwise. Every integration that works uses the bare form (`'acf'`,
`'yoast-seo'`, `'rank-math'`).

## The write toggle, and why the default is on

WPForms gates its four write abilities behind `ai-mcp-write-enabled` in the shared `wpforms_settings`
option, read through `wpforms_setting()` and passed through the
`wpforms_integrations_abilities_allow_write` filter (`Abilities.php:564-572`). It defaults to **off**,
and the gate lives in `check_write_gate()` → `check_write_permission()` → the `permission_callback`.

**That gate is already inert on any site running this plugin, and that is the real problem this
feature solves.** `AcrossAI_Ability_Override_Processor` replaces `permission_callback` on every
non-router ability and never consults the original, so the gate is discarded along with WPForms'
per-form capability checks. The execute callbacks do not re-check — `ability_create_form()` goes
straight to the mutator. **Measured, with the WPForms toggle off:**

```
wpforms_write_setting:         false      <-- WPForms says writes are disabled
wpforms/create-form:           ALLOWED
wpforms/add-field:             ALLOWED
wpforms/update-field:          ALLOWED
wpforms/update-form-settings:  ALLOWED
```

So the toggle proposed here **defaults to on because that is what the site already does** — it is not
a widening. What it adds is the off switch that currently does not exist anywhere: turn it off and
our integration stops attaching the allow-write filter, and the site owner regains the control
WPForms intended. The broader question of whether the floor should wrap rather than replace
third-party callbacks is issue #210 and is deliberately out of scope here.

## The default-on toggle cannot be built by changing `is_enabled()`

`AcrossAI_Integration_Settings::is_enabled()` returns false for an absent entry, and its docblock is
explicit that this is load-bearing:

> **The default is off.** An absent entry means disabled, which is the opposite of the retired
> category config, where an absent entry meant permitted. That asymmetry is the reason
> BUG-SPARSE-STORAGE-UNIFORM-DEFAULT-ASSUMPTION exists, and it is contained here: this class is the
> only PHP code path that reads integration opt-in state.

Adding a per-slug default would reintroduce exactly the mixed-default confusion that bug records. So
default-on is achieved by **seeding an explicit `true`** for the `wpforms` slug once, the way
`AcrossAI_Library_Gate_Migration::carry_integration_opt_ins()` already writes that option
(`includes/Modules/Abilities/AcrossAI_Library_Gate_Migration.php:371-383`). Absent still means off;
`wpforms` is simply never absent. A site that switches it off stores `false` and the migration must
not run again and undo that.

## Anatomy

| # | Artefact | Path |
|---|---|---|
| 1 | Integration class — toolset + opt-out toggle | `includes/Abilities/Integrations/WPForms.php` |
| 2 | One-time seed of the `wpforms` opt-in to `true` | existing migration module |
| 3 | Registry entry | `AcrossAI_Toolset_Integrations::built_in()` |
| 4 | Tests | `tests/phpunit/abilities/Test_WPForms_Integration.php` |

Modelled on `ACF.php`, which is the only existing class that is both an `AcrossAI_Toolset_Integration`
and an `AcrossAI_Integration_Ability_Base` opt-in:

- `TAB_GROUP = 'wpforms'`, `slug()` and `group()` both return it
- `ability_prefixes()` → `array( 'wpforms' )`
- `is_plugin_active()` → `defined( 'WPFORMS_VERSION' ) && function_exists( 'wpforms_setting' )` — two
  stable symbols per SEC-002, and `wpforms_setting()` is chosen because it is the function the target
  filter is read through, the same reasoning ACF uses for `acf_get_setting()`
- `enable_filter()` → `add_filter( 'wpforms_integrations_abilities_allow_write', '__return_true' )`,
  called by the base class only when the toggle is on and the plugin is active
- `abilities()` → the eight display rows

## Constraints — traps, not preferences

- **The prefix has no trailing slash.** See #209. A test asserting no declared prefix contains `/`
  would have caught that one and should exist here.
- **Display rows must match reality.** `Test_Integration_Row_Accuracy` asserts every declared slug
  resolves when the host plugin is active — ACF's rows had drifted to two abilities that do not exist
  before that test was added. All eight slugs above are copied from the registration calls.
- **Do not re-register their eight.** Adoption is tagging, not registration. Registering a name
  WPForms already owns would hit the duplicate refusal, and which side wins depends only on load
  order.
- **The seed must be idempotent and must not resurrect a deliberate off.**
- **The toggle governs writes only.** The four read abilities are unaffected, and the tab shows all
  eight either way.
- **Their per-form checks are gone regardless.** This feature does not restore them; #210 owns that.
  The toolset description should not imply per-form scoping still applies.

## Verification

1. Confirm all eight land in the `wpforms` tab, measured through the tagger rather than assumed —
   the check that would have caught #209.
2. `sum(counts) === total`, the group shows 8, and `toolset/wpforms` dispatches to them.
3. Toggle on: `wpforms_integrations_abilities_allow_write` is attached and the four writes are
   permitted. Toggle off: the filter is absent and WPForms' own gate denies again with
   `wpforms_writes_disabled` — the behaviour that is currently unreachable.
4. Execute one real write end-to-end with the toggle on (create a form, add a field, read it back,
   then remove it) and confirm the toggle-off path genuinely refuses the same call.
5. Fresh-install path: the seed writes `true`. Then switch it off, re-run the migration, and confirm
   it stays off.
6. Deactivate WPForms and confirm the tab and the MCP tool disappear.
7. `vendor/bin/phpunit`, full `vendor/bin/phpcs`, and PHPCompatibility over `includes/` — the gate
   that actually sweeps this code, since `includes/Abilities/` is excluded from the PHPCS baseline.

## Out of scope

- **Any new WPForms ability.** Decided: adopt only.
- **Entries, notifications, confirmations, entry export** — Pro surfaces, unverifiable on this
  install.
- **Whether the floor should wrap rather than replace third-party permission callbacks** — issue
  #210. It is the more general fix and would make this feature's toggle unnecessary for the gate,
  though still useful as a visible control.
- **Fixing WPCode's prefix** — issue #209, separate.
