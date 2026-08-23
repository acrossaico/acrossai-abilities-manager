# Feature 073 — Block locking, template locking, and block bindings

**Status**: input brief for `/speckit-specify`. Written 2026-08-23.

## Problem

Two orthogonal block-editor authoring controls have no `blocks/` namespace coverage:

1. **Locking** — WordPress block editor supports three lock modes that prevent unwanted edits: (a) per-block `lock` attribute (`move`, `remove`, or both), (b) container-block `allowedBlocks` attribute (restrict which block types may nest inside), (c) container-block `templateLock` attribute (`all` freezes children, `insert` allows edits but blocks structural changes). None of these are settable via ability today. Automations that build reusable design shells (a landing-page container that a client can edit within, but not restructure) cannot lock the structure after generation.
2. **Block bindings** — WordPress 6.5+ introduces the block-bindings API, which lets specific block attributes (typically `content`, `url`, `alt`, `title`) pull their value from an external source (post meta, patterns, custom source). Bindings live in the block's `metadata.bindings` object and are the canonical way to render dynamic content from Gutenberg blocks. There is no ability to inspect or set bindings today.

This feature adds 3 locking abilities + 2 binding abilities. `manage_options` remains the sole access gate. All writes are atomic per post.

## Proposed abilities

Slug convention per verb-first (Feature 058) under the `blocks/` namespace.

### Locking (3) — under `Block/` category, sub_group `mutation`

| Slug | Purpose | Core APIs |
|---|---|---|
| `blocks/set-block-lock` | Set the `lock` attribute on a block at a specified path. `lock` is an object `{ move: bool, remove: bool }`. Passing `null` (or omitting both fields) removes the lock. | Reuse `Block_Tree_Mutation` from Feature 066. Read block at path → update `attrs.lock` → persist. Input: `{ post_id: int, path: int[], move?: bool, remove?: bool, clear?: bool = false }`. If `clear: true`, delete `attrs.lock` entirely. |
| `blocks/set-allowed-blocks` | Set the `allowedBlocks` attribute on a container block (e.g. `core/group`, `core/columns`, `core/cover`, `core/query`). Value is an array of block-type names. Passing an empty array removes the restriction. | Reuse `Block_Tree_Mutation`. Reject if target block does not accept inner blocks (`WP_Block_Type::supports.inserter` OR presence of `<InnerBlocks/>` in edit script — heuristic: check registered block type for `has_block_children` or verify block name is in a known container allowlist). Input: `{ post_id: int, path: int[], allowed_blocks: string[] }`. |
| `blocks/set-template-lock` | Set the `templateLock` attribute on a container block. Enum: `'all'` (freeze children), `'insert'` (allow content edits, block structural), `false` (no lock), `null` (clear the attribute). | Reuse `Block_Tree_Mutation`. Same container-block validation as `set-allowed-blocks`. Input: `{ post_id: int, path: int[], mode: 'all'\|'insert'\|'contentOnly'\|false, clear?: bool = false }`. `contentOnly` is a WP 6.5+ mode; document `contentOnly` behavior in the ability description. |

### Block bindings (2) — under `Block/` category, sub_group `bindings`

| Slug | Purpose | Core APIs |
|---|---|---|
| `blocks/read-block-bindings` | Return the `metadata.bindings` map for a block at a specified path. Also returns the resolved binding source for each entry (e.g. `core/post-meta`, `core/pattern-overrides`) and the current live value the block renders. | Read block at path via `Block_Tree_Path_Resolver`. Return `{ bindings: { <attribute>: { source: string, args: object } }, resolved: { <attribute>: mixed } }`. For `resolved` values, call the WP core `get_block_bindings_source( $source )` and evaluate against the parent post context. |
| `blocks/set-block-bindings` | Set (or clear) bindings on a block's `metadata.bindings` map. Supports `core/post-meta`, `core/pattern-overrides`, and any custom source registered via `register_block_bindings_source()`. | Read block at path → merge or replace `attrs.metadata.bindings` → persist. Input: `{ post_id: int, path: int[], bindings: { <attribute>: { source: string, args?: object } }, mode?: 'merge'\|'replace' = 'merge', clear?: string[] }`. `clear` is a list of attribute names whose bindings should be deleted. Validate every `source` against the registered-bindings-source registry; reject unknown sources with an actionable message. |

## Reused utilities (do not reinvent)

- **`Ability_Definition`** parent class.
- **`Block_Tree_Path_Resolver`** and **`Block_Tree_Mutation`** — from Feature 066. All 5 abilities depend on these.
- **`Block_Info`** — for container-block detection heuristic used by `set-allowed-blocks` and `set-template-lock`. If a new "is-container" helper is needed, add it as a new static method on `Block_Info` (do not create a parallel helper).
- **WP core `WP_Block_Bindings_Registry`** — canonical bindings-source registry. Do not shadow.
- **`meta.acrossai.sub_group`** — `'mutation'` for the 3 locking ops, `'bindings'` for the 2 binding ops.

## Common shape (all 5)

- Namespace: `AcrossAI_Abilities_Manager\Includes\Abilities\Block`.
- Category slug: `acrossai-abilities-manager-block`.
- `read-block-bindings`: `readonly: true, destructive: false, idempotent: true`.
- All four write abilities: `readonly: false, destructive: false, idempotent: true` — setting the same lock or binding twice is a no-op.
- All post IDs guarded: return failure if `get_post( $id )` returns null or wrong post_type.
- Path array validation: reject empty arrays and out-of-bounds indices with a clear message.
- All returned messages wrapped in `__( '...', 'acrossai-abilities-manager' )`.
- `meta.show_in_rest = true`, `meta.mcp = { public: false, type: 'tool' }`.
- Write output: `{ success, path, before: { <attr>: mixed }, after: { <attr>: mixed }, message }`.

## Bootstrap wiring

Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()`:

- Add 3 new `new Block\Set_<Lock>();` lines adjacent to Feature-066 mutation classes.
- Add 2 new `new Block\<Bindings>();` lines in a new adjacent group (bindings are conceptually distinct from mutation but belong to the same category).

## Testing

Under `tests/phpunit/abilities/`, one test file per new ability.

Locking:
- `set-block-lock` with `{ move: true, remove: true }` on a top-level paragraph → re-read shows `attrs.lock = { move: true, remove: true }`.
- `set-block-lock` with `clear: true` removes the lock attribute entirely.
- `set-allowed-blocks` on a `core/group` with `['core/paragraph', 'core/heading']` → attribute set; on a `core/paragraph` (non-container) → rejected with actionable message.
- `set-template-lock` with `'all'` on a `core/columns` → attribute set; with `false` → attribute cleared.

Bindings:
- `set-block-bindings` on a paragraph with `{ content: { source: 'core/post-meta', args: { key: 'subtitle' } } }` → `attrs.metadata.bindings.content` matches.
- `read-block-bindings` on the same block → returns the binding + `resolved.content` equals the post-meta value for the parent post.
- `set-block-bindings` with `clear: ['content']` → binding removed.
- Guardrail: unknown source (`custom/nope`) → rejected with "unknown binding source" message.

Target: ~5 golden-path tests + ~5 guardrail tests.

## Delivery

Feature branch off `main`. No version bump — bundle into the next `release-0.0.X`.

## Dependencies

- **Depends on Feature 066** — all 5 abilities require `Block_Tree_Path_Resolver` and `Block_Tree_Mutation`. Already merged.
- **Independent of Features 070–072** — can land in any order once 066 is in.
- **Minimum WordPress version**: bindings require WP 6.5+. `set-block-bindings` and `read-block-bindings` should surface an actionable message ("block bindings require WordPress 6.5 or later") if `WP_Block_Bindings_Registry` is undefined. Locking abilities work on WP 6.0+.
