# Feature 107 — Classic Editor abilities

Inputs for `/speckit-specify`. Not a spec. Records the gap analysis, the reuse decision that sets the
scope, and the constraints that are traps rather than preferences.

## One-sentence goal

Add four Classic Editor abilities under a new `classic-editor` toolset — the derived-state questions
that no existing ability can answer — and point the rest at the generic abilities that already
handle the stored state.

## Why now

Classic Editor 1.7.0 is active at `wp-content/plugins/classic-editor`. It is one static class in one
1064-line file and it **registers no abilities of its own** — verified by grepping
`wp_register_ability`, `wp_abilities_api_init` and `abilities` across the whole plugin including
`js/` and `scripts/`: zero matches. So this is build-from-scratch, the Contact Form 7 / LiteSpeed
shape, not a gap analysis against a host that already ships some.

Nothing in our 630 abilities touches editor selection today:

```
grep -rn "classic-editor\|use_block_editor\|block_editor\|classic_editor\|choose_editor" includes/
→ (no output)
```

## The reuse decision — this is what sets the scope

Classic Editor stores almost nothing, and **every piece of it is already reachable through abilities
we ship**. This feature deliberately does not wrap them a second time:

| The state | Already handled by |
|---|---|
| `classic-editor-replace`, `classic-editor-allow-users` | `options/get-option`, `options/update-option` |
| `classic-editor-remember` post meta | `content/get-post-meta`, `content/update-post-meta`, `content/delete-post-meta` |
| Finding posts pinned to an editor | `content/list-posts` — it accepts `meta_key` + `meta_value` |
| The per-user preference | `users/get-user` (`meta_keys`), `users/update-user` (`meta`) |
| Post types | `content/list-post-types` |

Adding `editor/set-post-editor` or `editor/list-posts-by-editor` would be a second way to do
something that already works, which is worse than one documented way: more MCP surface, and two
tools an assistant has to choose between for one job.

**What is genuinely unreachable is the derived state, because the logic is `private`:**

| Question | Lives in | Visibility |
|---|---|---|
| What is the effective configuration, and which layer decided it? | `Classic_Editor::get_settings()` (line 221) | `private` |
| Which editor will this post open in, and why? | `Classic_Editor::is_classic()` (line 310) | `private` |
| Which editors are available for this post type? | `Classic_Editor::get_enabled_editors_for_post_type()` (line 770) | `private` |

Three private methods, three abilities. Plus one validated writer, for the reason below.

## Scope — 4 abilities

Category `acrossai-classic-editor`. `meta.acrossai.tab_group = 'classic-editor'` on all four. Slug
namespace **`editor/`**, unused across all 30 existing namespaces. Two `sub_group` cards.

### `editor-settings` (2)

1. **`editor/get-editor-settings`** — The effective configuration plus the layer that decided it:
   default editor, whether users may choose, whether the settings UI is hidden, the raw stored
   values, and whether the plugin is active. The only route to what `get_settings()` computes.

2. **`editor/update-editor-settings`** — Set the site default and/or the allow-users switch, through
   the plugin's own `Classic_Editor::validate_option_editor()` and `validate_option_allow_users()`
   (both `public static`, lines 410 and 418).

   This exists despite `options/update-option` because that ability accepts any string.
   `classic-editor-replace: "blok"` writes successfully and reports success, and the site silently
   becomes classic — anything not exactly `block`/`no-replace` falls through to classic (line 283).
   A settings writer that cannot reject a typo in a two-value enum is not much of a settings writer.
   Confirm-gate the change that flips the site default, which changes the editor for every author at
   once.

### `editor-resolution` (2)

3. **`editor/get-post-editor`** — Which editor this post opens in and why: the remembered post meta,
   the content sniff, post-type support, and the resolved answer with a precedence trace.

4. **`editor/get-post-type-editor-support`** — Per post type, whether each editor is enabled.
   Mirrors `get_enabled_editors_for_post_type()`, including re-applying the
   `classic_editor_enabled_editors_for_post_type` filter so a site that filters gets the answer it
   actually uses. Answers "why can't this post type use blocks" before anyone tries.

## `suggested_abilities` — the other half of the design

Use the richer `{ slug, reason }` shape (`DEC-ABILITY-SUGGESTED-ABILITIES-CONTRACT`; see
`ContactForm7/Update_Mail.php:103` for the form), never a bare slug. The `reason` must name the exact
key and the exact allowed values — that host-specific knowledge is precisely what the generic ability
lacks, and it is the whole reason a suggestion is worth more than a mention in a description.

| From | Suggests | The `reason` must say |
|---|---|---|
| `get-post-editor` | `content/update-post-meta` | key `classic-editor-remember`, values `classic-editor` / `block-editor` — **not** the `classic` / `block` the settings use |
| | `content/delete-post-meta` | clear it and the content sniff decides again |
| | `content/list-posts` | `meta_key=classic-editor-remember` finds every post pinned to an editor |
| `get-editor-settings` | `users/get-user`, `users/update-user` | the per-user preference is user meta under the blog-prefixed key `{$wpdb->prefix}classic-editor-settings`, values `classic` / `block` |
| | `editor/get-post-editor` | the site default is not the answer for any particular post |
| `update-editor-settings` | `editor/get-editor-settings` | read the effective state first — stored and effective are not the same thing here |
| `get-post-type-editor-support` | `content/list-post-types`, `editor/get-editor-settings` | |

## Explicitly out of scope

- **Multisite.** The network default and the `classic-editor-allow-sites` lock are real (lines
  259-274, 537-546) but this is a single-site install, so network abilities would ship unverified —
  the same reason Yoast Premium was excluded in Feature 106. `get-editor-settings` must still
  **report** the lock correctly; that path is unit-testable even though it cannot be live-executed
  here. Revisit if a multisite install becomes available.
- **Wrapping the stored state** — see the reuse table.
- **The `?classic-editor` / `?classic-editor__forget` query arguments.** They change editor selection
  for a single page load (lines 623-624, 646, 660). They are request state, not site state, and are
  meaningless in a REST context. `get-post-editor` should resolve as if no switch link were in play
  and say so, rather than silently reporting a hypothetical.

## Constraints

### The precedence must be re-derived, and two asymmetries carried over

`get_settings()` is private, so `Editor_Settings_Repository` has to mirror lines 221-306. Cite the
line numbers and pin the order with a test. Two things are easy to get wrong because they look like
bugs in the source:

- **Single-site normalises the legacy `no-replace` to block** (line 283). **The multisite path does
  not** — line 270 assigns the raw option value and only the later coercion at line 276 sees it, so
  `no-replace` on multisite resolves to classic. Mirror the difference; do not "fix" it.
- **Anything not exactly `block`/`no-replace` falls through to classic.** Missing, empty, and garbage
  all mean classic. That is why the reader must distinguish "stored as classic" from "defaulted to
  classic".

Precedence, highest first: the `classic_editor_plugin_settings` filter (returns an array → wins over
everything and forces `hide-settings-ui`) → the network lock → `classic_editor_network_default_settings`
→ site options → user meta → per-post resolution.

### Effective ≠ stored, and this site proves it

There are currently **no `classic-editor*` rows in `wp_options`** — the plugin runs entirely on its
coded defaults. A reader returning the raw option returns empty and implies "not configured" while
the site is behaving as classic. Return effective, raw, and the deciding layer. Test the defaults
path first; it is the state most real sites are in.

### Three value vocabularies

| Where | Values |
|---|---|
| `classic-editor-replace` option | `classic` / `block` (legacy `replace` / `no-replace`) |
| `classic-editor-settings` user meta | `classic` / `block` |
| `classic-editor-remember` post meta | **`classic-editor` / `block-editor`** (line 592) |

Mixing them is the most likely bug in this feature, and the reason the suggestion `reason` strings
spell the values out. Pin it with a test that no option is ever written a `*-editor` value and no
post meta is ever written a bare `classic` / `block`.

### Mode A has no per-post logic at all

With `allow-users` off, the plugin hard-filters `use_block_editor_for_post_type` to false and never
hooks `choose_editor` (lines 126-141). Post meta and the query args are ignored entirely. So
`get-post-editor` must report "this post remembers classic, and that is currently ignored because
per-user switching is off". Reporting the remembered value alone would be actively misleading — the
post will not open in that editor.

### Other

- **The content sniff is part of the answer.** With no post meta, `has_blocks()` decides (line 330).
  The plugin's private `has_blocks()` is `false !== strpos( $content, '<!-- wp:' )`, identical to WP
  core's public `has_blocks()` — use core's.
- **`register_setting()`'s sanitize callback does not run on a plain `update_option()`.** The writer
  must call the validators itself, then **read back** (`BUG-WRITE-REPORTED-WITHOUT-READ-BACK`).
- **Rows, never maps** in `array`-typed output (`BUG-ARRAY-TYPED-OUTPUT-IS-A-JSON-OBJECT`).
- **No `Classic_Editor` symbol outside `includes/Abilities/Utilities/ClassicEditor/`** — architecture
  test. Note the class is wrapped in `if ( ! class_exists( 'Classic_Editor' ) )` (line 34), so another
  plugin can pre-empt it; the guard should check both `CLASSIC_EDITOR_VERSION` and the class, the
  shape `Yoast_Guard::is_available()` uses.
- **Raise-only permission filter from the start** — the corrected shape from
  `BUG-BOOLEAN-PERMISSION-FILTER-WIDENS`: return false before consulting the filter. Do not copy an
  older guard, three of which had the broken shape until Feature 106.
- **No `Slash_Input` and no `is_writer()`.** The only writer takes enum values, so nothing accepts
  caller free text; a flag advertising a no-op control is worse than no flag (the Feature 105 lesson).
- **Floor `manage_options`, `final`**; every class `final`.

## Files

New: `includes/Abilities/ClassicEditor/{Base_Classic_Editor_Ability,Category_Registrar}.php` plus 4
ability classes; `includes/Abilities/Utilities/ClassicEditor/{Classic_Editor_Guard,Editor_Settings_Repository}.php`;
`includes/Abilities/Integrations/Classic_Editor.php`; three test files.

Modified: `AcrossAI_Core_Abilities_Bootstrap.php` (category action, `class_exists` gate, 4 `new`
lines) · `AcrossAI_Toolset_Integrations::built_in()` · `AcrossAI_Category_Slug_Migration::OWNED` ·
`Test_Ability_Group_Map.php` (`'ClassicEditor'` into **both** skip arrays) ·
`Test_Toolset_Group_Coverage.php` (`require_once`) · `phpunit.xml.dist` (three `<file>` entries) ·
`docs/abilities-inventory.md` (630 → 634) · `README.txt`.

`ability_prefixes()` returns `array()` — the "we supply everything" shape. Classic Editor registers
nothing, so there is nothing to adopt, and claiming `editor/` would capture any future ability in
that namespace. This is a 19th MCP tool against the `DEC-ABILITY-GROUP-TAXONOMY` ceiling, accepted
because the cost is conditional: with no prefixes claimed and `Base_Toolset_Ability` declining to
register an empty group, the tab and the tool exist only on sites running Classic Editor. Verify that
rather than assume it.

## Verification

1. Execute all 4 in process via a temporary mu-plugin REST route calling
   `wp_get_ability( $name )->execute( $input )`; delete it before committing.
2. **Defaults path first** — no options present: effective `classic`, allow-users false, and the
   response says the value was defaulted rather than stored.
3. Round-trip `update-editor-settings` on both keys; stored and effective agree; restore. Confirm an
   invalid enum is **refused**, not silently coerced.
4. **Mode A vs Mode B** — with `allow-users` off, `get-post-editor` reports the remembered value as
   ignored; turn it on and the same post resolves to the remembered editor.
5. Per-post — one post with block markup and one without. Set the meta via `content/update-post-meta`
   (the suggested route, so this verifies the suggestion is correct), clear it, confirm the content
   sniff takes over.
6. `get-post-type-editor-support` against `post`, `page`, and a CPT registered without editor support.
7. Negative paths: plugin inactive (guard suite), unknown post, unknown post type, invalid enum.
8. Toolset: `sum(counts) === total`, `classic-editor` shows 4, dispatch through
   `toolset/classic-editor`; then **deactivate Classic Editor and confirm the tab and the MCP tool
   disappear**.
9. Every suggested slug resolves — they point into other suites, so a rename elsewhere breaks them
   silently.
10. `vendor/bin/phpunit`, `npx wp-scripts test-unit-js`, PHPCS. **No PHPStan claim** from
    `composer phpstan` — it analyses nothing (#190).
11. Restore site state: delete any `classic-editor*` options and post meta the run created.

## Filed separately

**#194** — `options/update-option` never enforces its own `BLOCKED_OPTIONS`. Found while checking
whether the generic writer already covered `classic-editor-replace`. It does, along with `siteurl`,
`default_role` and the auth salts. Not fixed here.

## Suggested next steps (for the human)

`/speckit-specify` → `/speckit-plan` → `/speckit-tasks` → implement. `spec.md` / `plan.md` /
`tasks.md` intentionally not hand-authored.
