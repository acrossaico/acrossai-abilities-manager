# Feature 072 — Usage lookups & advanced block-tree mutation

**Status**: input brief for `/speckit-specify`. Written 2026-08-23.

## Problem

Feature 066 added the primitive block-tree edits (insert / remove / move / update / duplicate by path). Two gaps remain before authoring automations can safely refactor real sites:

1. **Reference discovery** — before removing a template part, navigation, or reusable block, a client needs to know what depends on it. Removing a template part that is still referenced by three templates silently breaks those templates. There is currently no way to answer "what references X?" without a full-site block-tree crawl.
2. **Higher-order mutations** — the current mutation primitives require the client to compute paths and payloads manually. Common editing intents are inefficient or unsafe to express as a sequence of primitives:
   - Rewriting text across a subtree (search-and-replace inside `core/paragraph`, `core/heading`, `core/list-item`, etc.) requires reading the tree, walking every leaf, and issuing per-block updates.
   - Structural transforms (converting `core/list` into `core/list-item` inner blocks, or promoting a `core/paragraph` into a `core/heading`) require multiple insert-then-remove sequences with fragile ordering.
   - Heading hierarchy normalization (H4 that follows an H2 with no H3 in between is an accessibility failure) has no dedicated fix path.
   - Path-array-based bulk mutation ("insert this here, remove that there, replace that other one") requires N separate ability calls with N round trips.

This feature adds 3 reference-lookup abilities + 4 higher-order mutation abilities. `manage_options` remains the sole access gate. Every write ability is atomic (all-or-nothing).

## Proposed abilities

Slug convention per verb-first (Feature 058) under the `blocks/` namespace.

### Reference lookups (3) — under `Block/` category, sub_group `site-editor`

| Slug | Purpose | Core APIs |
|---|---|---|
| `blocks/find-navigation-usage` | Given a `wp_navigation` ID, return every template + template-part + post whose block tree contains a `core/navigation {"ref": <id>}` reference. | Walk (a) all `wp_template` posts, (b) all `wp_template_part` posts, (c) optionally all `post` / `page` posts if `include_posts: true`. For each: `parse_blocks()` → recursive walk → match `core/navigation` blocks whose `attrs.ref === target_id`. |
| `blocks/find-template-part-usage` | Given a template-part `slug` (+ optional `theme`), return every template + template-part whose block tree contains a matching `core/template-part` reference. | Same walk pattern. Match `core/template-part` blocks whose `attrs.slug === target_slug` and (if `theme` provided) `attrs.theme === target_theme`. |
| `blocks/find-reusable-block-usage` | Given a `wp_block` ID, return every template + template-part + post whose block tree contains a `core/block {"ref": <id>}` reference. | Same walk pattern. Match `core/block` blocks whose `attrs.ref === target_id`. |

All three abilities share:
- Input: target identifier + `include_posts?: bool = false` + `post_types?: string[]` filter (defaults to `['post', 'page']` when `include_posts: true`).
- Output: `{ success, references: [{ referencing_object_type: 'wp_template'|'wp_template_part'|'post'|'page', referencing_object_id, referencing_object_title, occurrences: int }], total, message }`.
- `readonly: true, destructive: false, idempotent: true`.
- Result cap: 500 references per call (safety); if exceeded, output includes `truncated: true` and the client can paginate via `offset`.

### Higher-order mutations (4) — under `Block/` category, sub_group `mutation`

| Slug | Purpose | Core APIs |
|---|---|---|
| `blocks/mutate-block-tree` | Apply an ordered list of primitive mutations (`insert` / `remove` / `move` / `update` / `duplicate`) to a target post's block tree in a single atomic operation. If any single mutation fails, the entire batch is rolled back. | Reuse `Block_Tree_Mutation` from Feature 066 for each primitive; wrap the loop in `wp_update_post()` with a pre-computed final block string. Do not persist intermediate states. Input: `{ post_id: int, operations: [{ op: enum, path?: int[], parent_path?: int[], index?: int, block?: object, ... }] }`. |
| `blocks/transform-blocks` | Convert one or more blocks at specified paths using a named transform (`paragraph-to-heading`, `heading-to-paragraph`, `list-classic-to-list-item`, `group-to-columns`, `columns-to-group`). Uses WordPress-block-registry-declared block transforms where available, falls back to explicit transform maps for the named set. | Load the block-transform map from a plugin constant. For each path: read the block, run the named transform closure, replace in place. Batch-safe. Input: `{ post_id: int, transforms: [{ path: int[], transform: enum }] }`. |
| `blocks/replace-block-text` | Search-and-replace text inside every leaf text-bearing block (`core/paragraph`, `core/heading`, `core/list-item`, `core/quote`, `core/button`, `core/image` (alt only)) matching an optional `block_names[]` filter. Supports plain string or regex modes. | Walk the tree; for each matching leaf, update `attrs.content` (or `inner_html` for HTML-backed blocks) using `str_replace()` or `preg_replace()`. Track and return per-block change counts. Input: `{ post_id: int, search: string, replace: string, mode?: 'plain'\|'regex' (default 'plain'), block_names?: string[], case_sensitive?: bool = true }`. |
| `blocks/normalize-heading-levels` | Rewrite heading levels in a post so that no heading skips more than one level (H2 → H3 OK; H2 → H4 not OK, promoted to H3). Optional `top_level?: int (2-4)` sets the highest allowed heading; anything above is demoted to `top_level`. Non-destructive to `core/heading` `content`. | Parse tree, find every `core/heading`, compute the actual hierarchy, produce a normalization map (`{path → new_level}`), apply. Report before/after level for each heading. Input: `{ post_id: int, top_level?: int = 2, preserve_h1?: bool = true }`. |

All four write abilities: `readonly: false, destructive: true, idempotent: false` (except `mutate-block-tree` where idempotency depends on the operation set — mark `idempotent: false`).

## Reused utilities (do not reinvent)

- **`Ability_Definition`** parent class.
- **`Block_Tree_Path_Resolver`** and **`Block_Tree_Mutation`** — from Feature 066. All four write abilities depend on these.
- **`Block_Info`** — for block-type source classification and content-field detection.
- **Feature 070's `get-site-editor-references`** — the reference-walk implementation is generalized in 070; the three `find-*-usage` abilities here are thin wrappers that call the same walker with a specific block-name + attribute-match filter.
- **`meta.acrossai.sub_group`** — `'site-editor'` for the 3 reference lookups, `'mutation'` for the 4 higher-order mutations.

## Common shape (all 7)

- Namespace: `AcrossAI_Abilities_Manager\Includes\Abilities\Block`.
- Category slug: `acrossai-abilities-manager-block`.
- Input validation: `additionalProperties: false`; enum values enforced at the schema layer.
- All post IDs guarded: return failure if `get_post( $id )` returns null or wrong post_type.
- All returned messages wrapped in `__( '...', 'acrossai-abilities-manager' )`.
- `meta.show_in_rest = true`, `meta.mcp = { public: false, type: 'tool' }`.
- Write abilities return `{ success, changes: [{ path, before, after }], message }` for audit trails.

## Bootstrap wiring

Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()`:

- Add 3 new `new Block\Find_<Object>_Usage();` lines adjacent to existing site-editor abilities.
- Add 4 new `new Block\<Class>();` lines adjacent to existing Feature-066 mutation classes (`Add_Block`, `Move_Block`, `Duplicate_Block`, `Remove_Block`).

## Testing

Under `tests/phpunit/abilities/`, one test file per new ability.

Reference lookups:
- Seed a template that references a template-part `slug=header`; `find-template-part-usage(slug=header)` returns the template with `occurrences: 1`.
- Multiple references in one template (same navigation used twice) → `occurrences: 2`.
- Guardrail: unknown navigation ID → returns `{ references: [], total: 0 }` (empty, not error).

Higher-order mutations:
- `mutate-block-tree` — one batch with insert + update + remove; verify final tree matches expectations; verify a mid-batch failure leaves the post unchanged.
- `transform-blocks` — `paragraph-to-heading` at path `[0]` → block name becomes `core/heading`, content preserved.
- `replace-block-text` — plain mode across 5 paragraphs; regex mode with a capture group; `block_names` filter narrows scope.
- `normalize-heading-levels` — post with H2 → H4 → verify H4 becomes H3; H2 → H5 → verify H5 becomes H3; `preserve_h1: true` leaves H1 untouched.

Target: ~7 golden-path tests + ~6 guardrail tests.

## Delivery

Feature branch off `main`. No version bump — bundle into the next `release-0.0.X`.

## Dependencies

- **Depends on Feature 070** — reference lookups reuse the generalized walker introduced by `get-site-editor-references`.
- **Depends on Feature 066** — all 4 higher-order mutations require `Block_Tree_Path_Resolver` and `Block_Tree_Mutation`. Already merged.
