# Feature 070 — Block-editor site context reads

**Status**: input brief for `/speckit-specify`. Written 2026-08-23.

## Problem

The `blocks/` namespace covers block-registry inspection and template / template-part CRUD, but it is thin on the **read-only inspection surface** an authoring client needs before generating or mutating content:

1. **Theme context** — the existing `blocks/get-site-editor-context` returns is-block-theme + active theme name + a few counts. Clients that need to reason about *authoring capability* (block theme? classic theme with template support? classic theme without?) or *style-variation posture* only get a partial answer.
2. **Style guide** — no way to summarize theme spacing scale, palette, typography ramp, and layout constraints as a single normalized structure. Clients today must call `blocks/read-theme-json` and reconstruct the summary themselves from the raw `settings` blob.
3. **Style book** — no way to fetch a coherent styled-block preview set (the same body of "styled samples" the Site Editor renders in its Styles → Style Book panel). Useful for design agents that need to see what each block *looks like* under the current theme before choosing one.
4. **Site-editor inventory summary** — `get-site-editor-context` returns counts only. There is no single call that returns a categorized inventory (templates by area, template parts by area, style variations by title, navigation entities by title) suitable for a one-shot orientation pass.
5. **Site-editor references** — no way to answer "what depends on template part X?" or "which templates render navigation Y?" without hand-crawling every template body.
6. **Block-category list** — `blocks/list-blocks` accepts a category filter but there is no way to enumerate the registered categories themselves (labels + slug + icon). Clients that build category-scoped UI or generation flows need this catalog.

This feature adds 5 new read-only abilities and (optionally) expands `blocks/get-site-editor-context` output to close the six gaps. `manage_options` remains the sole access gate. Every ability is `readonly: true, destructive: false, idempotent: true`.

## Proposed abilities

Slug convention per verb-first (Feature 058) under the `blocks/` namespace (per topic-based namespace convention).

### 5 new abilities — all under `Block/` category

| Slug | Purpose | Core APIs |
|---|---|---|
| `blocks/get-style-guide` | Return a normalized summary of the theme's design system: spacing scale, color palette (theme + user), typography (families + font-sizes + line-heights), layout widths (contentSize / wideSize), and root duotone/gradient sets. Flat, cacheable, no raw theme.json passthrough. | `WP_Theme_JSON_Resolver::get_merged_data()->get_settings()` → walk `spacing.spacingSizes`, `color.palette` (theme + custom + default), `typography.fontFamilies`, `typography.fontSizes`, `layout.contentSize`, `layout.wideSize`, `color.duotone`, `color.gradients`. |
| `blocks/get-style-book` | Return the Site Editor Style Book payload: for every registered block that has a style-book example, return the block name, title, category, and the pre-rendered example markup. The same data the Styles → Style Book panel uses. | WP core `WP_Theme_JSON::get_stylesheet( ['styles'] )` for CSS context + `WP_Block_Type_Registry::get_all_registered()` walk. Each block's example comes from `WP_Block_Type::example` (already used by core to build the panel). Skip blocks whose `example` is null. |
| `blocks/get-site-editor-summary` | Return a categorized inventory: templates grouped by area (`wp_template`), template parts grouped by area (`wp_template_part`), style variations by title, and navigation entities by title + slug. Complements `get-site-editor-context` (which returns scalar counts). | `get_block_templates( [], 'wp_template' )`, `get_block_templates( [], 'wp_template_part' )`, `WP_Theme_JSON_Resolver::get_style_variations()`, `get_posts( [ 'post_type' => 'wp_navigation', 'posts_per_page' => -1 ] )`. |
| `blocks/get-site-editor-references` | Given a target site-editor object (template, template-part, navigation, or synced pattern), return every other object that references it. Answers "what breaks if I remove X?". | For each template + template-part body in the site: `parse_blocks()` → recursive walk → look for `core/template-part` (matches `slug` + optional `theme`), `core/navigation` (matches `ref` attribute), `core/block` (matches `ref` attribute — synced patterns). Return array of `{ referencing_object_type, referencing_object_id, referencing_object_title, occurrences: int }`. |
| `blocks/list-block-categories` | Return every registered block category with slug, title, and icon. Complements `blocks/list-blocks`. | `WP_Block_Editor_Context` + `get_block_categories( $post ?? get_default_block_editor_context() )` — canonical WP way to fetch categories including plugin/theme-added ones. |

### 1 optional extension (no new slug) — `blocks/get-site-editor-context`

Extend output with two additional fields to complete "orientation" parity without adding another slug:

- `navigation_count`: `int` (currently absent).
- `style_variation_count`: `int` (currently absent — only `active_style_variation` name is returned).

Backwards-compatible: additive fields; existing consumers keep working.

### Overlap notes (why we don't add `get-theme-context` and `get-block-categories` as separate slugs)

- **`get-theme-context`**: covered by the existing `blocks/get-site-editor-context` (theme name + is-block-theme + active style variation + site-editor URL). No new slug needed. If the extension above lands, this is complete.
- **`get-block-details`**: already covered by `blocks/read-block`. Confirmed via file read.

## Reused utilities (do not reinvent)

- **`Ability_Definition`** parent class.
- **`Block_Info`** utility class (already used by `List_Blocks.php`, `Read_Block.php`) — for block source classification if `get-style-book` needs it.
- **`WP_Theme_JSON_Resolver`** — WordPress core, no new wrapper.
- **`WP_Block_Type_Registry::get_all_registered()`** — canonical block-type enumerator.
- **Site Editor category** already declared: `acrossai-abilities-manager-block` (see `includes/Abilities/Block/Category_Registrar.php`).
- **`meta.acrossai.sub_group`** — reuse existing `'site-editor'` sub-group for the four site-editor-facing abilities; use `'blocks'` sub-group for `list-block-categories` and `get-style-book`.

## Common shape (all 5)

- Namespace: `AcrossAI_Abilities_Manager\Includes\Abilities\Block`.
- Category slug: `acrossai-abilities-manager-block`.
- All abilities `readonly: true`, `destructive: false`, `idempotent: true`.
- All string inputs sanitized with `sanitize_text_field()`; array inputs shape-validated by JSON schema (`additionalProperties: false`).
- All returned messages wrapped in `__( '...', 'acrossai-abilities-manager' )`.
- `meta.show_in_rest = true`, `meta.mcp = { public: false, type: 'tool' }`.
- Output envelope: `{ success: bool, ...data, message: string }` — mirrors existing `Block/` classes.

## Bootstrap wiring

Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()`:

- Add 5 new `new Block\<Class>();` lines to the existing Block section (adjacent to `Get_Site_Editor_Context` / `Refresh_Site_Editor_Context` for the 3 site-editor ones, and adjacent to `List_Blocks` / `Read_Block` for the block-registry ones).
- No new category registrar (the `Block` category already exists).

## Testing

Under `tests/phpunit/abilities/`, one test file per new ability. All read-only, so no rollback needed.

- `get-style-guide` — with default theme active, output includes at least one entry in `spacing`, `palette`, `typography.font_sizes`, and `layout.content_size` is a non-empty string.
- `get-style-book` — output includes an entry for `core/paragraph` with a non-empty `example` markup.
- `get-site-editor-summary` — with the default block theme, output groups templates by area (`front-page`, `single`, `archive`, `page`, `index`, etc.) and returns arrays not scalars.
- `get-site-editor-references` — seed a template that contains a `<!-- wp:template-part {"slug":"header"} /-->` block; call with `object_type='template_part', slug='header'` → output includes the seeded template.
- `list-block-categories` — output includes the core categories (`text`, `media`, `design`, `widgets`, `theme`, `embed`) and each entry has `slug` + `title`.

Target: ~5 golden-path tests + ~5 guardrail tests (missing target, invalid enum, empty registry, non-block-theme fallback, JSON schema validation).

## Delivery

Feature branch off `main`. No version bump — bundle into the next `release-0.0.X` alongside sibling parity phases (071 onward).

---

## Appendix — Full block-editor parity roadmap

Feature 070 is Phase 1 of a multi-phase parity plan. Remaining phases (grouped so each is landable independently and each stays in the 4–10 ability range):

| Phase | Slug | Abilities | Concern |
|---|---|---|---|
| **070 (this)** | `block-editor-site-context-reads` | 5 + 1 extension | Read-only inspection surface for authoring clients |
| **071** | `block-editor-synced-patterns-and-navigations` | 9 | Synced-pattern CRUD (`get`/`create`/`update`/`extract`/`insert-synced-pattern`) + `wp_navigation` CRUD (`list`/`get`/`create`/`update-navigation`) |
| **072** | `block-editor-usage-and-advanced-mutation` | 7 | Usage lookups (`find-navigation-usage`, `find-template-part-usage`, `find-synced-pattern-usage`) + advanced tree ops (`mutate-block-tree`, `transform-blocks`, `replace-block-text`, `normalize-heading-levels`) |
| **073** | `block-editor-locking-and-bindings` | 5 | Block/template locking (`set-block-lock`, `set-allowed-blocks`, `set-template-lock`) + block bindings (`get-block-bindings`, `set-block-bindings`) |
| **074** | `block-editor-parse-and-analysis` | 10 | Structured content ops (`parse-content`, `serialize-blocks`) + QA layer (`validate-content`, `audit-content`, `analyze-content`, `evaluate-design`, `suggest-design-fixes`, `evaluate-copy`, `suggest-copy-fixes`, `evaluate-render-context`) |
| **075** | `block-editor-generation-and-recipes` | 9 | Guidance & recipes (`block-guidance`, `get-page-recipes`, `get-section-recipes`, `get-query-section-recipes`) + generators (`generate-landing-page`, `generate-section`, `generate-query-section`, `create-page-from-blocks`, `create-page-from-pattern`, `create-landing-page`) |

**Totals** — 45 new ability slugs across 6 phases, restoring full block-editor authoring parity to the `blocks/` namespace.

**Phase dependencies**:
- 071 depends on 070 (`get-site-editor-summary` output shape is reused for navigation inventory).
- 072 depends on 070 (`get-site-editor-references` output shape is reused for usage lookups).
- 073, 074, 075 are independent of each other and can land in any order after 070.

**Deferred decisions** (surface during `/speckit-clarify` for the relevant phase):
- Phase 074 — quality heuristics for `evaluate-design` / `evaluate-copy` (rules-based scoring vs. filter-extensible).
- Phase 075 — recipe library shape (JSON files? filter-hook contributed? built-in only?).
- Phase 075 — whether `create-page-from-blocks` and `create-landing-page` supersede the existing `content/create-page` fallback or coexist as thin wrappers.
