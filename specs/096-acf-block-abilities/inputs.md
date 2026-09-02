# Feature 096 — ACF block abilities (inputs for /speckit-specify)

This file is the natural-language brief that `/speckit-specify` should turn into `spec.md`. Do not treat it as the spec itself.

## One-sentence goal

Add five new abilities that let an AI client register ACF-powered blocks, discover their fields, and insert or update ACF block instances inside post content — the workflow that ACF's own AI integration (6.8) does not cover.

## Why now

ACF 6.8 (released 2026-03-30) shipped a WordPress Abilities API integration exposing schema management (`acf/field-groups`, `acf/register-field-group`) and per-CPT / per-taxonomy content CRUD. That integration is already wired into this plugin through `includes/Abilities/Integrations/ACF.php` (Feature 060), which flips `acf/settings/enable_acf_ai`.

**Gap:** ACF's own abilities do not touch **block registration** (no `BlockType.php` in ACF's `src/AI/Abilities/`) and do not scope field-group introspection to blocks. Our own `blocks/add-block` and `blocks/update-post-block` abilities can insert any block mechanically but require the AI to hand-craft the ACF-specific block markup (`<!-- wp:acf/{name} {"data":{...}} /-->`) and hand-craft the `data` payload without any way to discover which fields the block accepts.

The AI-authored-block-with-ACF workflow is a common ask for content-authoring assistants that need to produce rich, editable, non-Gutenberg-native layouts. Today it requires the AI to compose Gutenberg markup manually and guess at field shapes.

## Scope

### Abilities to add (5)

All under the `blocks/` topic namespace (per project convention — block-editor abilities live under `blocks/`, not under a vendor prefix).

1. **`blocks/register-acf-block`** — Create an ACF block type. Inputs: name, title, category, icon, mode (edit/preview/auto), supports, render source (template path OR inline callback identifier), optional inline field-group definition (fields + location auto-bound to the new block). Wraps `acf_register_block_type()` and, when the inline field-group is supplied, calls ACF's field-group registration too so a single call yields a fully wired block.

2. **`blocks/list-acf-blocks`** — Enumerate every ACF-registered block on the site with, per entry: block name, title, category, bound field-group ID (if any), and field count. Lets the AI discover what already exists before creating a duplicate.

3. **`blocks/get-acf-block-fields`** — Given an ACF block name, return the fields defined by its bound field group: name, key, type, label, instructions, sub-fields (for repeaters / flex layouts / groups), default value. This is the piece the AI needs before building a valid `data` payload.

4. **`blocks/insert-acf-block`** — Insert an ACF block instance into a post. Inputs: post_id, block name, `data` payload (validated against the field group's field names / types before insertion), optional `align` / `mode`, insert position (append / prepend / after-block-id). Thin wrapper over `blocks/add-block` that owns the ACF markup shape and validation.

5. **`blocks/update-acf-block-data`** — Locate a specific ACF block instance in a post (by client_id or by name + index) and patch only its `data` attribute. Complements `blocks/update-post-block` for the ACF-specific patch case; does not re-serialize the whole post_content.

### Explicitly out of scope

- **ACF field-value abilities on posts** (`acf/get-field`, `acf/update-field`, etc.) — ACF block field values live in `post_content` as the block's `data` attribute, not in `postmeta`. `update_field()` is not the write path here. Tracked separately if we ever need it for classic-editor / non-block field values.
- **Options-page fields, repeater-row-level operations, generic field-group CRUD** — none are on the critical path for authoring ACF blocks; wait for a real user pull.
- **Modifying ACF's own registered abilities** — we do not touch `acf/*` slugs; ACF owns that namespace.
- **Block.json generation on disk** — abilities operate at the runtime `acf_register_block_type()` layer. Filesystem-based `block.json` scaffolding is a separate concern (see the file-manager abilities cluster) and would be a follow-up.

## Constraints

- **ACF Pro required** — ACF blocks are a Pro-only feature (`acf_register_block_type` is undefined in free ACF). Guard every ability's `permission_callback` on `defined('ACF_VERSION') && function_exists('acf_register_block_type')`; return a clean "ACF Pro not active" error rather than a fatal when the function is missing.
- **Live behind the existing integration toggle** — abilities should only register when the `Advanced Custom Fields (AI)` toggle on the Library page is on. Reuse `Integrations/ACF::is_plugin_active()` for the detection path.
- **Consistent with the `apply_wp_slash` opt-out flag** — any ability that accepts caller strings and hands them to a WordPress core write function should merge `Slash_Input::schema_fragment()` into its input schema and use `Slash_Input::slash( $value, $input )` at the write boundary, matching PR #162 (this branch). `insert-acf-block` and `update-acf-block-data` are the two that need it, since block markup goes through `wp_insert_post()` / `wp_update_post()` under the hood.
- **Category namespacing** — new abilities register under an existing block-editor ability category (probably `acrossai-abilities-manager-blocks` — confirm during planning by reading `includes/Abilities/Block/*.php`). Do not create a new category unless there is a UX reason.
- **`suggested_abilities()` hints (Feature 095)** — `register-acf-block` should suggest `list-acf-blocks` first ("check what exists before creating a duplicate"). `insert-acf-block` should suggest `get-acf-block-fields` first ("know what fields the block accepts before building the data payload"). This mirrors the outline-first hint pattern used by `update-post`.

## Non-goals

- No changes to `Integrations/ACF.php` — that integration only flips ACF's own switch and lists the abilities ACF will register. New abilities are OURS and live under `includes/Abilities/Block/` or similar, not there.
- No client-side UI. Abilities appear in the Library table via the standard registration path; no admin page changes.
- No back-compat concerns — this is additive.

## Files that will likely be touched

- `includes/Abilities/Block/Register_Acf_Block.php` (new)
- `includes/Abilities/Block/List_Acf_Blocks.php` (new)
- `includes/Abilities/Block/Get_Acf_Block_Fields.php` (new)
- `includes/Abilities/Block/Insert_Acf_Block.php` (new)
- `includes/Abilities/Block/Update_Acf_Block_Data.php` (new)
- Possibly `includes/Abilities/Utilities/Acf_Block_Helpers.php` (new) for shared field-group resolution + `data`-payload validation
- `includes/Main.php` — register the five ability classes on `acrossai_abilities_api_init` (mirror how existing `Block/*` abilities are bootstrapped)

## Existing code to reuse

- `Slash_Input::schema_fragment()` / `Slash_Input::slash()` — for the two writers (added by branch `feat/apply-wp-slash-opt-out` / PR #162)
- `Integrations/ACF::is_plugin_active()` — the compound `defined('ACF_VERSION') && function_exists('acf_get_setting')` detection pattern
- `Ability_Definition` base class — every new ability extends this
- Existing `blocks/add-block` and `blocks/update-post-block` internals — `insert-acf-block` and `update-acf-block-data` are conceptually thin wrappers around these plus ACF-shape validation

## Verification (what "done" looks like)

- All 5 abilities register when ACF Pro is loaded AND the integration toggle is on; do not register otherwise
- `blocks/register-acf-block` end-to-end: given a name + field-group definition, the resulting block is visible in the Gutenberg block inserter and its fields render in the sidebar
- `blocks/list-acf-blocks` returns the block registered above
- `blocks/get-acf-block-fields` returns the field definitions with correct types and (for repeaters) sub-fields
- `blocks/insert-acf-block` inserts a working block instance into a post; visiting the post in the editor shows the block with the supplied `data` populated
- `blocks/update-acf-block-data` mutates an existing instance's data; other blocks in the post are untouched byte-for-byte
- `composer run phpcs` and `composer run phpstan` (level 8) clean
- `apply_wp_slash: false` opt-out works on both writers (round-trip test with a `\Foo\Bar`-containing text field)

## Suggested next steps (for the human)

1. `/speckit-specify` — read this brief and produce `specs/096-acf-block-abilities/spec.md`
2. `/speckit-plan` — turn the spec into `plan.md`
3. `/speckit-tasks` — decompose into `tasks.md`
4. `/speckit-implement` (or hand-execute) — build

I have NOT written spec.md / plan.md / tasks.md. Those are for the /speckit-* workflow you invoke.
