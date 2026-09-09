# Feature 098 — ACF repeater / flex-content row abilities (inputs for /speckit-specify)

This file is the natural-language brief that `/speckit-specify` should turn into `spec.md`. Do not treat it as the spec itself.

## One-sentence goal

Add six row-scoped ability wrappers around ACF's repeater and flexible-content APIs so an AI content-editing assistant can add, patch, remove, or reorder individual rows / layouts without having to build the entire array-shaped value and round-trip it through `update-acf-field`.

## Why now

Feature 097 (`custom-fields/update-acf-field` + `update-acf-fields`) lets a caller write full field values — including full repeater / flex-content arrays. But for the common editor workflow — "add one more row to this repeater", "swap the third and fourth layouts", "delete the second card" — that forces the AI to:

1. Read the entire current field value with `get-acf-field`
2. Mutate the array in memory (append / splice / reorder)
3. Write the whole array back with `update-acf-field`

Three round trips for a one-row edit. On a repeater with 40 rows or a flex-content block with rich sub-fields (image, WYSIWYG, relationship), that's a lot of bytes moving over MCP for a small change, and every write also re-writes the untouched rows — losing any diff visibility a downstream system might rely on.

ACF's PHP API already exposes row-scoped primitives:

- **Repeater:** `add_row()`, `update_row()`, `delete_row()` — plus the field-value contract for row order via `update_field()` on a reordered array
- **Flexible content:** `add_row()` with a `layout` name in the row array, `delete_row()`, and layout ordering via array position

These are documented, stable ACF APIs (present in both ACF free and ACF Pro Repeater, though flex-content is Pro-only). Wrapping them one-to-one is a small, mechanical job that gives AI clients the same row-level ergonomics an author gets in the admin UI.

## Scope

### Abilities to add (6)

All under `custom-fields/` (matches feature 097's namespace so all field-manipulation abilities live together).

1. **`custom-fields/add-acf-repeater-row`** — Append one row to a repeater field on a target. Inputs: target keys (same shape as feature 097: `target_type`, `target_id`), `field_name` (or `field_key`), `row` (object — sub-field values, validated to be a plain object not an array), `position` (int, optional — insert at index; default = append). Wraps `add_row()`. When `position` is set to a valid index, uses `update_field()` on the reconstructed array to honor insertion order (since ACF's `add_row()` only appends).

2. **`custom-fields/update-acf-repeater-row`** — Patch one row of a repeater by 1-based index. Inputs: target keys + `field_name` + `row_index` (int, 1-based per ACF convention) + `row` (partial object — merged into the existing row, sub-fields not present are preserved). Wraps `update_row()`. Rejects with a clear error when `row_index` is out of range.

3. **`custom-fields/remove-acf-repeater-row`** — Remove one row from a repeater by 1-based index. Inputs: target keys + `field_name` + `row_index`. Wraps `delete_row()`. Rejects with a clear error when `row_index` is out of range.

4. **`custom-fields/reorder-acf-repeater-rows`** — Replace the row order of a repeater. Inputs: target keys + `field_name` + `order` (array of 1-based original indices in the new order — e.g. `[3, 1, 2]` moves the third row to first). Reads the current rows, reorders in memory, writes back via `update_field()`. Rejects if `order` is not a permutation of the current row indices (no duplicates, no missing indices, no out-of-range values).

5. **`custom-fields/add-acf-flex-layout`** — Append one flex-content layout to a flex-content field. Inputs: target keys + `field_name` + `layout` (string — the layout name defined in the field group) + `values` (object — sub-field values for that layout) + `position` (int, optional). Wraps `add_row()` with `acf_fc_layout` set to the layout name in the row array. Rejects if `layout` is not a valid layout name defined on the field.

6. **`custom-fields/remove-acf-flex-layout`** — Remove one flex-content layout by 1-based index. Inputs: target keys + `field_name` + `row_index`. Wraps `delete_row()`. (Reuses the same row-based API as repeater; flex-content layouts are stored as rows with an `acf_fc_layout` marker.)

### Explicitly out of scope for this feature

- **Reordering flex-content layouts** — decide during `/speckit-plan` whether to alias `custom-fields/reorder-acf-repeater-rows` to also accept a flex-content field (since flex layouts are rows internally) OR add a dedicated `reorder-acf-flex-layouts`. Leaning alias for simplicity; leaving open for planning.
- **Nested repeater ops** — sub-repeaters inside a repeater / flex layout are out of scope for the MVP. Callers can still write the whole nested structure via `update-acf-repeater-row` with a full nested row object. Row-level ops on the *nested* repeater are a future feature if demand appears.
- **Move-row across two repeaters** — e.g. "cut row 3 from repeater A and paste at position 1 in repeater B". Two-target atomic move is a distinct concern.
- **Copy-row / duplicate-row** — trivially expressible as read-then-add-row; the AI can do it in two calls if needed.

## Constraints

- **ACF required** — repeaters ship in ACF Pro; flex-content is Pro-only. Guard every repeater ability on `defined('ACF_VERSION') && function_exists('add_row') && function_exists('update_row') && function_exists('delete_row')`. Flex-content abilities additionally check that the target field is of `type` `flexible_content` (validated at execute time via `get_field_object()`). Return clean errors, never fatal, when a symbol or field type is wrong.
- **Live behind the existing integration toggle** — same as features 096 / 097. Reuse `Integrations/ACF::is_plugin_active()`.
- **`apply_wp_slash` opt-out on all six** — every ability is a writer. Merge `Slash_Input::schema_fragment()`, route the sub-field values through `Slash_Input::slash( $value, $input )` before handing to ACF's row functions, advertise via `Slash_Input::meta_flags()`. Same pattern as the 21 writers in #165 and the 3 writers proposed for feature 097.
- **Field-type validation at execute time** — the ability's target field must be of the expected type. `add-acf-repeater-row` / `update-acf-repeater-row` / `remove-acf-repeater-row` / `reorder-acf-repeater-rows` require `type: repeater`. `add-acf-flex-layout` / `remove-acf-flex-layout` require `type: flexible_content`. Return `success: false` with a descriptive message if the field is the wrong type — do not attempt the operation.
- **1-based row index convention** — ACF's public API uses 1-based indices (`update_row( $row_index )` treats 1 as the first row). Our abilities use the same convention. The input schema description must call this out explicitly so an AI doesn't fall through to 0-based habits.
- **Namespace scoping** — all `permission_callback` gates on `manage_options`. No per-target capability check in this feature.
- **Feature 095 hints (`suggested_abilities()`)** — every mutator suggests `get-acf-field` first ("read current rows to know the index range before targeting a row"). `reorder-acf-repeater-rows` also suggests reading current row count so the `order` permutation is well-formed. Mirrors the pattern.

## Non-goals

- No changes to `Integrations/ACF.php` beyond possibly extending its advertising card. Decide during `/speckit-plan`.
- No admin UI.
- No back-compat concerns — additive.

## Files that will likely be touched (all new)

- `includes/Abilities/CustomFields/Add_Acf_Repeater_Row.php`
- `includes/Abilities/CustomFields/Update_Acf_Repeater_Row.php`
- `includes/Abilities/CustomFields/Remove_Acf_Repeater_Row.php`
- `includes/Abilities/CustomFields/Reorder_Acf_Repeater_Rows.php`
- `includes/Abilities/CustomFields/Add_Acf_Flex_Layout.php`
- `includes/Abilities/CustomFields/Remove_Acf_Flex_Layout.php`
- Possibly extend `includes/Abilities/Utilities/Acf_Target.php` (from feature 097) with a `resolve_field_object()` helper for the field-type validation guard
- `includes/Main.php` — register the six ability classes; reuse the `acrossai-abilities-manager-custom-fields` ability category from feature 097

## Existing code to reuse (once features 097 lands)

- **`Slash_Input::schema_fragment()` / `Slash_Input::slash()` / `Slash_Input::meta_flags()`** — the opt-out helper from #165
- **`Acf_Target` helper (feature 097)** — resolves `(target_type, target_id)` into ACF's target string; add a `resolve_field_object()` helper here for field-type validation
- **`Integrations/ACF::is_plugin_active()`** — plugin detection
- **`Ability_Definition` base class**
- **Feature 095 `suggested_abilities()` pattern** — see `includes/Abilities/Content/Update_Post.php`

## Dependency

- **Feature 097 must land first.** This feature reuses the `Acf_Target` helper (target-string resolution) and the `custom-fields/` ability category. Both are introduced in feature 097. Sequencing: 097 lands → 098 built on top. Do not attempt to work on 098 in parallel; the merge conflicts on the shared helper would be painful.

## Verification (what "done" looks like)

All checks performed via the `claude.ai acrosai` MCP connector against a live WP install with ACF Pro loaded and the integration toggle on:

1. **Register-time guard works** — with ACF Pro deactivated (or a free ACF install where `flexible_content` is undefined), the flex-content abilities do not register; the repeater abilities register only if `add_row` / `update_row` / `delete_row` exist.
2. **`add-acf-repeater-row` at end** — create a repeater with 2 existing rows; call `add-acf-repeater-row` without `position`; assert 3 rows after, new row is at index 3, first two rows unchanged byte-for-byte.
3. **`add-acf-repeater-row` at middle** — same repeater; call with `position: 2`; assert new row is at index 2, previous rows 2+ shifted down by one, first row unchanged.
4. **`update-acf-repeater-row` merges partial payload** — repeater with sub-fields `title, subtitle, image`; call `update-acf-repeater-row` on row 1 with `{title: "New"}` only; assert row 1's `title` is "New" and `subtitle`/`image` are preserved.
5. **`update-acf-repeater-row` out of range** — call with `row_index: 999` on a 3-row repeater; assert clean error response, repeater unchanged.
6. **`remove-acf-repeater-row`** — 5 rows; remove row 3; assert 4 rows remain with original rows 1, 2, 4, 5 (in that order, byte-for-byte on sub-fields).
7. **`reorder-acf-repeater-rows`** — 4 rows; call with `order: [4, 2, 3, 1]`; assert new order matches. Call with `order: [1, 1, 2, 3]` (duplicate); assert rejection. Call with `order: [1, 2, 3]` (missing an index); assert rejection.
8. **`add-acf-flex-layout`** — flex-content field with two layouts defined (`hero`, `card`); add a `hero` layout with values; assert row appended with `acf_fc_layout: hero` and the supplied values. Attempt to add layout `nonexistent`; assert rejection.
9. **`remove-acf-flex-layout`** — remove a layout by index; assert other layouts preserved.
10. **Field-type validation** — call `add-acf-repeater-row` on a text field; assert clean error with a message identifying the wrong field type.
11. **`apply_wp_slash: false` opt-out** — write a row containing `\Foo\Bar` in a sub-field with the flag off; assert stored bytes match input (backslashes stripped, proving flag routes through `Slash_Input::slash()`).
12. **PHPCS clean** on the six new files + any `Acf_Target` extension.
13. **PHPStan clean** (level 8).

## Suggested next steps (for the human)

1. Ship feature 097 first — 098 depends on the shared `Acf_Target` helper and category.
2. `/speckit-specify` — read this brief and produce `specs/098-acf-repeater-flex-row-abilities/spec.md`.
3. `/speckit-plan` — turn the spec into `plan.md`. Two design decisions to lock in during planning: (a) whether `reorder-acf-repeater-rows` also handles flex-content layouts via alias vs a dedicated `reorder-acf-flex-layouts`; (b) whether `position` on `add-*` is an index or an anchor (`{after_row_index: N}`).
4. `/speckit-tasks` — decompose into `tasks.md`.
5. `/speckit-implement` (or hand-execute) — build.

I have NOT written spec.md / plan.md / tasks.md. Those are for the /speckit-* workflow you invoke.
