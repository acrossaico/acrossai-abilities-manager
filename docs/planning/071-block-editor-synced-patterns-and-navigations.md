# Feature 071 — Reusable-block writes & Site-Editor navigation entities

**Status**: input brief for `/speckit-specify`. Written 2026-08-23.

## Problem

Two site-editor object types have partial read coverage but zero write coverage in the `blocks/` namespace:

1. **Reusable blocks (`wp_block` CPT — a.k.a. synced patterns)** — `blocks/list-reusable-blocks` enumerates them, but there is no way to (a) read a single reusable block by ID, (b) create one, (c) update its content, (d) extract a slice of an existing post's block tree into a new reusable block, or (e) insert a reusable-block reference into a post. Authoring automations that want to standardize repeated design (hero, testimonials, CTA) on `wp_block` are blocked.
2. **Site-Editor navigations (`wp_navigation` CPT)** — architecturally distinct from classic `nav_menu` menus (which are already handled under `menus/*`). `wp_navigation` was introduced with the Site Editor and is the *only* navigation type that appears in block themes' Navigation block. There is currently no `blocks/`-namespace coverage for it: no list, no read, no create, no update.

This feature adds 9 new abilities filling both gaps. `manage_options` remains the sole access gate. Every write is atomic per WordPress core (no partial writes).

## Slug naming decisions

- **Reusable blocks vs. synced patterns**: the plugin already uses `blocks/list-reusable-blocks` for the same `wp_block` CPT. To stay consistent with the existing namespace, all new abilities here use `reusable-block` (singular), not `synced-pattern`. Both terms map to the same underlying CPT; the plugin's convention wins.
- **Navigations vs. menus**: `blocks/*-navigation` targets `wp_navigation`; `menus/*` targets classic `nav_menu`. Do not merge; they are different post types serving different UIs.

## Proposed abilities

Slug convention per verb-first (Feature 058) under the `blocks/` namespace.

### Reusable-block writes (5) — under `Block/` category, sub_group `patterns`

| Slug | Purpose | Core APIs |
|---|---|---|
| `blocks/read-reusable-block` | Return a single reusable block by ID: title, slug, status, raw content, and the parsed block tree. Complements `list-reusable-blocks` (enumeration only). | `get_post( $id )` where `post_type = 'wp_block'`; `parse_blocks( $post->post_content )`. Return 404-style failure if post is missing, wrong post_type, or trashed. |
| `blocks/create-reusable-block` | Create a new reusable block. Accepts either raw block markup (`content: string`) or a structured block array (`blocks: array` — serialized via `serialize_blocks()`). One of the two required. | `wp_insert_post( [ 'post_type' => 'wp_block', 'post_status' => 'publish', 'post_title' => ..., 'post_content' => ... ] )`. |
| `blocks/update-reusable-block` | Update an existing reusable block's title and/or content. Preserves post_status by default. | `wp_update_post()`. Reject if post_type is not `wp_block`. |
| `blocks/extract-reusable-block` | Given a source post ID and a block path (canonical path array from `066` tree ops), (1) create a new `wp_block` from that subtree, (2) replace the source location with a `core/block {"ref": <new_id>}` reference. Atomic — either both writes succeed or both roll back. | Reuse `Block_Tree_Path_Resolver` from Feature 066 to locate + extract the subtree; `wp_insert_post()` for creation; `wp_update_post()` for the source post. Wrap in a `try/catch` and revert the source-post update if the insert fails. |
| `blocks/insert-reusable-block-into-post` | Insert a `core/block {"ref": <id>}` reference into a target post at a specified path + sibling index. Does not duplicate the reusable block's content — inserts a reference. | Reuse Feature 066's `Block_Tree_Mutation` helper for path-based insertion. Validate the reusable block exists before inserting. |

### Site-Editor navigation entities (4) — under `Block/` category, sub_group `site-editor`

| Slug | Purpose | Core APIs |
|---|---|---|
| `blocks/list-navigations` | Enumerate every `wp_navigation` post with `id`, `title`, `slug`, `status`, `modified`. | `get_posts( [ 'post_type' => 'wp_navigation', 'post_status' => [ 'publish', 'draft' ], 'posts_per_page' => -1 ] )`. Sort by `post_title` ascending. |
| `blocks/read-navigation` | Return one `wp_navigation` by ID: title, status, raw content, and parsed block tree (the tree is a flat sequence of `core/navigation-link`, `core/navigation-submenu`, `core/page-list` entries). | `get_post( $id )` with `post_type` guard; `parse_blocks()`. |
| `blocks/create-navigation` | Create a new `wp_navigation`. Accepts `title`, `blocks[]` (structured) or `content` (raw), and optional `status` (`publish\|draft`, default `publish`). | `wp_insert_post()` with `post_type = 'wp_navigation'`. If `blocks[]` provided, `serialize_blocks()` before insert. |
| `blocks/update-navigation` | Update title, content, or status of a `wp_navigation`. Preserves fields not passed in. | `wp_update_post()` with `post_type` guard. |

## Reused utilities (do not reinvent)

- **`Ability_Definition`** parent class.
- **`Block_Tree_Path_Resolver`** and **`Block_Tree_Mutation`** — introduced by Feature 066 for path-based block-tree edits. `extract-reusable-block` and `insert-reusable-block-into-post` both depend on these.
- **`Block_Info`** utility class for block-name validation on structured inputs.
- **Block category** already declared: `acrossai-abilities-manager-block` (`includes/Abilities/Block/Category_Registrar.php`).
- **Existing `blocks/list-reusable-blocks`** — same pattern for `list-navigations` (return array of lightweight rows, no full content).
- **`meta.acrossai.sub_group`** — `'patterns'` for the 5 reusable-block ops, `'site-editor'` for the 4 navigation ops.

## Common shape (all 9)

- Namespace: `AcrossAI_Abilities_Manager\Includes\Abilities\Block`.
- Category slug: `acrossai-abilities-manager-block`.
- Reads (`read-reusable-block`, `read-navigation`, `list-navigations`): `readonly: true, destructive: false, idempotent: true`.
- Writes (`create-*`, `update-*`, `extract-*`, `insert-*`): `readonly: false, destructive: false, idempotent: false` — creation and updates produce new state; extract is `destructive: true` because it rewrites the source post.
- All post_type reads guard against wrong CPT and return actionable "wrong post_type" messages.
- Input schemas: `additionalProperties: false`; either/or fields (`content` vs `blocks[]`) enforced with `oneOf` at the schema layer.
- All string inputs sanitized with `sanitize_text_field()`; content strings passed through `wp_kses_post()` before `wp_insert_post` / `wp_update_post` if provided as raw HTML; structured `blocks[]` serialized via `serialize_blocks()`.
- All returned messages wrapped in `__( '...', 'acrossai-abilities-manager' )`.
- `meta.show_in_rest = true`, `meta.mcp = { public: false, type: 'tool' }`.
- Output envelope: `{ success: bool, id?: int, ...data, message: string }`.

## Bootstrap wiring

Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()`:

- Add 5 new `new Block\<Class>();` lines adjacent to `List_Reusable_Blocks` for the reusable-block ops.
- Add 4 new `new Block\<Class>();` lines adjacent to `Get_Site_Editor_Context` for the navigation ops.
- No new category registrar.

## Testing

Under `tests/phpunit/abilities/`, one test file per new ability.

Reusable blocks:
- `create-reusable-block` with `blocks: [ core/paragraph ]` → returns `id`, post exists, `get_post_type($id) === 'wp_block'`.
- `read-reusable-block` on the created ID returns the paragraph.
- `update-reusable-block` swaps content; re-read returns new content.
- `extract-reusable-block` on a post-with-container → source post reference exists, new `wp_block` contains extracted subtree, extract failure rolls back source post.
- `insert-reusable-block-into-post` inserts `core/block` ref; validate target post now contains that ref at expected path.
- Guardrails: wrong post_type ID on `read-reusable-block` → failure with actionable message; missing `content` and `blocks[]` on create → schema rejection.

Navigations:
- `create-navigation` with `blocks: [ core/navigation-link ]` → returns `id`, `get_post_type($id) === 'wp_navigation'`.
- `list-navigations` after seeding two entries returns both, sorted.
- `read-navigation` returns parsed block tree.
- `update-navigation` changes title only → content preserved.
- Guardrail: `read-navigation` against a `nav_menu` post_id → failure with "wrong post_type" message.

Target: ~9 golden-path tests + ~7 guardrail tests.

## Delivery

Feature branch off `main`. No version bump — bundle into the next `release-0.0.X` alongside sibling parity phases.

## Dependencies

- **Depends on Feature 070** — reuses the site-editor summary output shape for navigation inventory.
- **Depends on Feature 066** — `extract-reusable-block` and `insert-reusable-block-into-post` require `Block_Tree_Path_Resolver` and `Block_Tree_Mutation` helpers. Already merged.
