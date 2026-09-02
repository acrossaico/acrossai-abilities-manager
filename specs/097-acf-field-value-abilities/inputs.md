# Feature 097 — ACF field-value abilities (inputs for /speckit-specify)

This file is the natural-language brief that `/speckit-specify` should turn into `spec.md`. Do not treat it as the spec itself.

## One-sentence goal

Add five thin ability wrappers around ACF's own `get_field` / `update_field` / `delete_field` PHP API so an AI client can read or write ACF field values on any target (post, page, non-ACF CPTs, users, terms, comments, options) — the workflow that ACF 6.8's own Abilities integration does not cover.

## Why now

ACF 6.8.9 (installed as of 2026-09-02) ships a WordPress Abilities API integration with **schema management** (`acf/field-groups`, `acf/register-field-group`, and equivalents for CPTs/taxonomies) plus **per-CPT record CRUD** dynamically registered as `acf/{cpt}s`, `acf/create-{cpt}`, `acf/view-{cpt}`, `acf/update-{cpt}`, `acf/delete-{cpt}` for every ACF-registered CPT (and parallel per-taxonomy CRUD).

**Gap:** ACF's per-CPT CRUD is heavyweight (whole-record) and only covers **ACF-registered CPTs**. It does not touch:

- Standard `post` / `page` post types
- CPTs registered outside ACF (via `register_post_type()` in a theme or other plugin)
- Users, terms, comments, options-page targets
- Field-scoped writes (patch one field without touching post_title / post_content / other fields)

ACF's own `register-field-group` docstring even calls this out explicitly:

> *"This creates the field structure that will appear on content, not the field values themselves. Field values are set when creating or updating posts, terms, or other content."*

So on any target outside ACF-managed CPTs — including the built-in `post` / `page` used by 90% of WP sites — there is currently **no ACF-native way for an AI client to read or write field values**. Our existing `content/update-post-meta` handles the raw postmeta write but silently corrupts repeaters, flexible content, clones, relationship fields, and any other complex ACF type because the storage shape is ACF-specific.

## Scope

### Abilities to add (5)

All under a new `custom-fields/` topic namespace (per project convention — topic-based namespaces, ACF specificity carried in the verb). If Meta Box, Pods, or JetEngine field abilities are ever added later, they get a matching `custom-fields/{verb}-{plugin}` pair without needing a new namespace.

1. **`custom-fields/get-acf-field`** — Read one ACF field value from any target. Inputs: `target_type` (`post` / `user` / `term` / `comment` / `option`), `target_id` (int or 'option' string for options page), `field_name` (or `field_key` for stable identifier), `format_value` (bool, default true — passes to `get_field`'s third argument). Wraps `get_field()` so repeaters, flex-content, clones, and relationship fields hydrate correctly.

2. **`custom-fields/get-acf-fields`** — Bulk-read every ACF field value on a target. Same target inputs. Returns a name → hydrated-value map. Wraps `get_fields()`.

3. **`custom-fields/update-acf-field`** — Write one ACF field value on a target. Inputs: same target keys + `field_name` or `field_key` + `value` (mixed) + `apply_wp_slash` (default true, uses `Slash_Input::slash()`). Wraps `update_field()` so complex types serialize correctly instead of getting mangled by raw postmeta writes.

4. **`custom-fields/update-acf-fields`** — Bulk-write multiple ACF field values on a target in one call. Inputs: target keys + `fields` (name → value map) + `apply_wp_slash`. Loops through the map calling `update_field()` for each; returns per-field `updated` / `failed` buckets like `User_Helpers::apply_meta()` does.

5. **`custom-fields/delete-acf-field`** — Clear one ACF field value on a target. Wraps `delete_field()`. Distinguished from raw meta-delete because `delete_field()` also cleans up field-key reference rows and repeater sub-row rows that a raw `delete_post_meta()` would leave orphaned.

### Explicitly out of scope for this feature

- **Repeater / flex-content row-level operations** (add-row / update-row / remove-row / reorder-rows) — tracked as feature 098 candidate. Callers can still write full repeater arrays via `update-acf-fields`; row-level ops are an ergonomics improvement.
- **Options-page enumeration** (`list-acf-options-pages`) — `options` target is already supported via `target_type: option`; enumerating registered pages is a separate discovery concern.
- **Field group introspection** (`get-acf-field-group`, `list-acf-field-group-fields`, `get-acf-field-by-name`, `get-acf-field-by-key`) — the read/write abilities need field names at call time; introspection is a separate discovery workflow that can ride on a future feature.
- **Query helpers** (`find-posts-by-acf-field`, `list-acf-field-values`) — cross-post query is a different shape; tracked separately.
- **Field group CRUD gaps** (update/delete field group) — ACF ships `register` (create); update/delete is a separate feature area.
- **Block-specific field ops** — ACF blocks store field values in `post_content` as the block's `data` attribute, not in postmeta. Feature 096 handles those; do NOT try to route block field ops through this feature.

## Constraints

- **ACF required (any edition)** — `get_field` / `update_field` / `delete_field` exist in both ACF free and ACF Pro. Guard every ability's `permission_callback` on `defined('ACF_VERSION') && function_exists('get_field') && function_exists('update_field')`; return a clean "ACF not active" error rather than a fatal when either symbol is missing. No ACF Pro requirement (unlike feature 096, which needs ACF Pro for blocks).
- **Live behind the existing integration toggle** — abilities should only register when the "Advanced Custom Fields (AI)" toggle on the Library page is on. Reuse `Integrations/ACF::is_plugin_active()` for the detection path. Rationale: operators who deliberately disabled the ACF integration should not have our field-value writers registering either.
- **`apply_wp_slash` opt-out** — the three writers (`update-acf-field`, `update-acf-fields`, `delete-acf-field` — although delete doesn't touch value strings, keep it consistent) must merge `Slash_Input::schema_fragment()` into their input schemas and route the write value through `Slash_Input::slash( $value, $input )` before handing it to `update_field()`. Matches the pattern established across the 21 writers in #165.
- **`meta.input_flags` advertisement** — same three writers get `Slash_Input::meta_flags()` merged into their `meta` block so the flag is enumerable at discovery time.
- **Namespace scoping** — every ability's `permission_callback` gates on `manage_options`. Matches every other write ability we ship. Do not attempt per-target capability checks (e.g. `edit_post` on the target post) in this feature; that's a downstream refinement.
- **Feature 095 hints (`suggested_abilities()`)** — `update-acf-field` should suggest `get-acf-field` first ("read the current value before overwriting so you can produce an idempotent update"). `update-acf-fields` should suggest `get-acf-fields` first. Read abilities need no hint. This mirrors the pattern in `Update_Post`.
- **No `blocks/` overlap** — deliberately live under `custom-fields/`, not `content/` or `blocks/`, so the ACF-versus-generic-postmeta distinction is visible in the slug.

## Non-goals

- No changes to `Integrations/ACF.php` beyond possibly extending its read-only advertising card to mention the new abilities (decide during `/speckit-plan`).
- No new WP admin UI. Abilities appear in the Library table via the standard registration path.
- No back-compat concerns — this is additive.

## Files that will likely be touched (all new)

- `includes/Abilities/CustomFields/Get_Acf_Field.php`
- `includes/Abilities/CustomFields/Get_Acf_Fields.php`
- `includes/Abilities/CustomFields/Update_Acf_Field.php`
- `includes/Abilities/CustomFields/Update_Acf_Fields.php`
- `includes/Abilities/CustomFields/Delete_Acf_Field.php`
- `includes/Abilities/Utilities/Acf_Target.php` (new helper) — resolves the `(target_type, target_id)` input pair into the string ACF expects (`123` for a post, `user_45` for a user, `term_12` for a term, `comment_7` for a comment, `option` for options page); centralizes validation
- `includes/Main.php` — register the five ability classes; add the corresponding ability-category slug if a new one is introduced
- Possibly new ability category: `acrossai-abilities-manager-custom-fields` (decide during planning by checking existing `wp_register_ability_category()` calls in the plugin)

## Existing code to reuse

- **`Slash_Input::schema_fragment()` / `Slash_Input::slash()` / `Slash_Input::meta_flags()`** — the opt-out helper from #165; the three writers use it exactly the same way the existing 21 writers do
- **`Integrations/ACF::is_plugin_active()`** — reuse the compound `defined('ACF_VERSION') && function_exists('acf_get_setting')` detection; the abilities' guard extends this by also checking for `get_field` / `update_field` / `delete_field`
- **`User_Helpers::apply_meta()` return shape** — `array{updated: string[], failed: string[]}` — `update-acf-fields` should return the same shape for consistency with existing bulk-write abilities
- **`Ability_Definition` base class** — every new ability extends this
- **Feature 095 `suggested_abilities()` pattern** — see `includes/Abilities/Content/Update_Post.php` for the read-first hint pattern to mirror

## Verification (what "done" looks like)

All checks performed via the `claude.ai acrosai` MCP connector against a live WP install with ACF loaded and the integration toggle on:

1. **Register-time guard works** — with ACF unloaded (deactivate plugin), abilities do not register; discover-abilities does not return them.
2. **`get-acf-field` round-trip** — set a text field via `update-acf-field`, read it back via `get-acf-field`; assert byte equality (backslashes preserved by default).
3. **`update-acf-field` with `apply_wp_slash: false`** — write `\Foo\Bar`; read back; assert `FooBar` (proves opt-out actually skips slashing).
4. **Repeater round-trip** — create a repeater field on a post via ACF admin; write an array of rows via `update-acf-field`; read back via `get-acf-field`; assert rows match. Repeat with the same payload via raw `content/update-post-meta` (bypassing ACF) and assert *that* one produces corrupted / partial data — proves this ability is not redundant with post-meta writes.
5. **Non-ACF-registered CPT** — create a CPT via `register_post_type()` in a mu-plugin; attach an ACF field group to it via location rules; verify `update-acf-field` writes to that CPT even though ACF's `acf/update-{cpt}` ability is NOT registered for it (proves this covers the CPT gap).
6. **User / term targets** — write and read a field on a user (`target_type: user`) and on a taxonomy term (`target_type: term`); assert values persist.
7. **Options page target** — write a field with `target_type: option`; read it back; assert persistence.
8. **Bulk write** — call `update-acf-fields` with 3 fields on one post; verify all three land; the returned `failed` array is empty.
9. **Bulk write partial failure** — call `update-acf-fields` with one valid field name and one bogus one; verify the valid one lands and the bogus one appears in `failed`.
10. **PHPCS clean** on the six new files.
11. **PHPStan clean** (level 8) — no new type errors.
12. **PHPUnit** — new tests for the source-shape assertion pattern already established (e.g. `Test_Update_Post_Meta`); verify each ability's file contains `update_field(` / `get_field(` / `delete_field(` and the expected `Slash_Input::slash()` wrap.

## Suggested next steps (for the human)

1. `/speckit-specify` — read this brief and produce `specs/097-acf-field-value-abilities/spec.md`
2. `/speckit-plan` — turn the spec into `plan.md`
3. `/speckit-tasks` — decompose into `tasks.md`
4. `/speckit-implement` (or hand-execute) — build

I have NOT written spec.md / plan.md / tasks.md. Those are for the /speckit-* workflow you invoke.
