# Feature 111 — Widget management: 9 abilities on the existing `widgets/` namespace

Prepared for `/speckit-specify`. Every claim below was measured on this install, not inferred.

## The starting request, and why the answer is not what was asked for

The ask was "build the abilities for `/plugins/classic-widgets`, it will have its own toolset too".

**Classic Widgets has no state to manage.** The whole plugin is 71 lines. It registers no options,
no post meta, no user meta and no abilities — verified by grepping `add_option`, `update_option`,
`register_setting`, `wp_register_ability` and `wp_abilities_api_init` across the plugin: zero
matches. All it does is `remove_theme_support( 'widgets-block-editor' )` on `after_setup_theme`,
which swaps the widgets admin screen from the block editor back to the pre-5.8 form UI.

A suite wrapping it would be one ability answering "is this plugin active", and a toolset holding
that one ability. The plugin's *effect* — widgets are edited as classic widget instances rather
than blocks — is the thing worth acting on, and that state belongs to WordPress core, not to
Classic Widgets. So this feature builds a **Widgets** suite against core, and reports the
Classic Widgets mode as one field of a status ability rather than as a suite of its own.

## What we already ship, and what is missing

Per the reuse rule established in 107, the existing surface was inventoried before anything new
was designed.

| The state | Already handled by |
|---|---|
| Which sidebars a theme registers | `widgets/list-sidebars` |
| Which widgets sit in them | `widgets/list-widgets` |
| Reading the raw `widget_{id_base}` option | `options/get-option` |
| Reading `sidebars_widgets` | `options/get-option` |

Two abilities existed and both are read-only. **Nothing could change a widget** — not its settings,
not its position, not its existence. `options/update-option` can technically write the options, and
that route is actively unsafe here for three separate reasons documented under Constraints.

So the gap is the entire write surface, plus three reads that core's own data does not answer
directly: what widget types are installable, what one instance actually holds, and whether widget
management is even meaningful on this site.

## Scope — 9 new abilities, 11 total

Existing category `acrossai-widgets`, existing namespace `widgets/`,
`tab_group => 'appearance'`, `sub_group => 'widgets'`. **No new toolset and no new MCP tool** —
this lands inside the Appearance group, which already exists and already holds the two readers.
That keeps `DEC-ABILITY-GROUP-TAXONOMY` where it is at 19 groups.

### Reads (3 new)

1. **`widgets/get-widget-management-status`** — The orientation call. Whether the site uses the
   block widget editor or the classic form UI, whether Classic Widgets is active, how many sidebars
   the theme registers, how many placements the option actually holds, and any **orphaned**
   sidebars. Answers "will editing widgets here do anything visible" before anyone tries.
2. **`widgets/list-widget-types`** — Every registered `WP_Widget` subclass with its `id_base`,
   name, description and current instance count. There is no other way to learn what
   `add-widget` will accept.
3. **`widgets/get-widget`** — One instance: its settings, its sidebar, its position, and whether
   that sidebar is registered.

### Writes (6 new)

4. **`widgets/add-widget`** — Create an instance of a type and place it. Allocates the next free
   number, writes through the widget's own `update()`.
5. **`widgets/update-widget`** — Change an existing instance's settings, through `update()`.
6. **`widgets/move-widget`** — Move an instance to another sidebar, optionally at a position.
7. **`widgets/reorder-sidebar`** — Reorder one sidebar. Refuses a partial list.
8. **`widgets/deactivate-widget`** — Move to `wp_inactive_widgets`. Reversible, keeps settings.
9. **`widgets/remove-widget`** — Delete the instance and its settings. Confirm-gated, `destructive`.

## Constraints — measured traps, not preferences

- **`sidebars_widgets` is not a map of sidebars.** It also carries `array_version` (an integer),
  and `wp_inactive_widgets` is a real key that is not a registered sidebar. Iterating every key as
  though it were a sidebar corrupts the option. Measured on this install: 4 keys, of which 2 are
  sidebars.
- **`wp_assign_widget_to_sidebar()` unsets without reindexing.** Measured: after removing the
  middle widget, `sidebar-1` held keys `0`, `1`, `3`. The option is then a JSON object rather than
  an array to every consumer that reads it as JSON, which is the same failure shape as
  `BUG-ARRAY-TYPED-OUTPUT-IS-A-JSON-OBJECT`, and core's own widgets screen renders the gap. Every
  placement change must reindex.
- **A widget sanitises its own input in `WP_Widget::update()`.** The text widget runs the title
  through `sanitize_text_field()` and, for a user without `unfiltered_html`, the body through
  `wp_kses_post()`. Writing `widget_text` directly through `options/update-option` bypasses all of
  it, and would make these abilities the one route into a site's widgets that applies no
  sanitisation. It also silently ignores a widget that *refuses* an update — `update()` returning
  false is a real answer, and must surface as `settings_rejected`
  (`BUG-WRITE-REPORTED-WITHOUT-READ-BACK`).
- **`_multiwidget` is bookkeeping, not an instance.** Every `widget_{id_base}` option carries it.
  Counting option keys as instances overcounts by exactly one, every time.
- **An orphaned sidebar is still a real place a widget sits.** Measured on this install, which runs
  a block theme: **zero registered sidebars**, while `sidebars_widgets` held two of them with five
  widgets between them. Refusing to act on a sidebar the theme does not register would make the
  whole suite inert on any block theme — the majority case now. Accept them, and report them.
- **Unlimited and zero are different answers everywhere**, and the third read exists because
  neither `list-sidebars` nor `list-widgets` distinguishes "this theme registers no sidebars" from
  "these sidebars are empty".
- **Removal is the only irreversible operation.** Deactivation keeps the settings and is a move;
  removal deletes them. Only removal is confirm-gated — gating the reversible ones trains a caller
  to pass `confirm: true` reflexively, which is the 107 lesson.

## Suggested abilities

`{slug, reason}` rows per `DEC-ABILITY-SUGGESTED-ABILITIES-CONTRACT`. Each `reason` names what the
generic route gets wrong:

- every writer → `widgets/get-widget-management-status` (*"a block theme may register no sidebars
  at all, in which case the placements you edit are orphaned and render nowhere"*)
- `add-widget` → `widgets/list-widget-types` (*"the `id_base` must be a registered type; there is
  no other list of them"*)
- `update-widget` → `widgets/get-widget` (*"settings are replaced wholesale, so read the current
  instance first"*)
- `remove-widget` → `widgets/deactivate-widget` (*"deactivation keeps the settings and is
  reversible; removal is not"*)
- `reorder-sidebar` → `widgets/list-widgets` (*"the list must name every widget currently in the
  sidebar — a partial list is refused, because applying one would delete the rest"*)

## Anatomy

| # | Artefact | Path |
|---|---|---|
| 1 | 9 ability classes | `includes/Abilities/Widgets/<Verb>_<Subject>.php` |
| 2 | All widget state behind one boundary | `includes/Abilities/Utilities/Widget_Repository.php` |
| 3 | Bootstrap — 9 `new` lines | `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` |

No new guard, no new category registrar, no new toolset integration — all three already exist for
`acrossai-widgets`. Floor `edit_theme_options` throughout, matching core's own widgets screen.

## Verification

1. Execute all 9 in process via a temporary mu-plugin REST route calling
   `wp_get_ability( $name )->execute( $input )`; delete it before committing.
2. Full add → update → move → reorder → deactivate → remove cycle against a live sidebar, reading
   `sidebars_widgets` and `widget_text` out of the database between each step.
3. Confirm the option is **byte-identical** to its starting value once the cycle completes — the
   reindexing bug is only visible this way.
4. Negative paths: unknown widget, malformed id, unknown type, unknown sidebar, partial reorder,
   empty update, removal without `confirm`.
5. Mutation-verify each guard by reintroducing the defect and confirming the test fails.
6. `vendor/bin/phpunit`, PHPCS on the new files.

## Deliberately excluded

- **Block widgets.** On a block-theme site the widget area is a template part, not a sidebar; that
  is a template-editing problem and belongs with the block abilities, not here.
- **A Classic Widgets toolset**, for the reason at the top.
- **Widget preview or rendering.** `the_widget()` output is theme-dependent and not useful data.
