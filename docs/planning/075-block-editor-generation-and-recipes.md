# Feature 075 — Block-editor generation, recipes & authoring guidance

**Status**: input brief for `/speckit-specify`. Written 2026-08-23.

## Problem

The plugin can create posts and pages via the generic `content/create-page` fallback, and it can insert individual registered patterns, but there is no authoring-oriented layer that (a) recommends the right block or layout for a given scenario, (b) enumerates ready-made recipe options for full pages / reusable sections / dynamic query sections, or (c) generates a structured block tree from a small set of business inputs (business name, tone, section list). Every content-generation workflow today either (1) hand-composes block markup string-by-string, (2) drops a raw registered pattern with no adaptation, or (3) calls an LLM directly with no scaffolding.

This feature adds a guidance ability + 3 recipe enumerators + 3 generators + 3 page-creation abilities. `manage_options` remains the sole access gate. All abilities are theme-neutral by design — no theme-specific typography, colors, or layout tokens are hardcoded.

## Deferred design decisions (surface at `/speckit-clarify`)

**Decision A — recipe library shape.** Three options:

1. **Bundled JSON files** — recipes ship in `includes/Abilities/Block/recipes/*.json`, versioned with the plugin. Simplest; no extensibility.
2. **Bundled JSON + filter-contributed** — bundled defaults plus `apply_filters( 'acrossai_block_recipes', $recipes, $type )` so other plugins can add or replace recipes. **Recommended default.**
3. **Fully pluggable recipe registrar** — recipes registered via a PHP registrar class only, no bundled JSON. Most flexible but no built-ins.

**Decision B — relationship to `content/create-page`.**

1. `create-page-from-blocks` and `create-landing-page` **coexist** as thin wrappers around `content/create-page`, adding block-specific input schemas and validation. Existing consumers of `content/create-page` unaffected.
2. `create-page-from-blocks` **supersedes** `content/create-page` for block-editor targets; `content/create-page` becomes a classic-editor-only fallback. Breaks existing consumers.

Default proposal: **A2 + B1** (filter-contributed recipes; new abilities coexist with `content/create-page`). Confirm during `/speckit-clarify`.

**Decision C — generator determinism.** Generators produce structured block trees from a small input set. Should the output be:
1. **Fully deterministic** — same input always yields same output. Server-side templating with no randomization.
2. **Deterministic-per-seed** — accept an optional `seed` input; same seed + same input = same output.

Default proposal: **C1** (fully deterministic). Simpler to test and audit.

## Proposed abilities

Slug convention per verb-first (Feature 058) under the `blocks/` namespace.

### Authoring guidance (1) — under `Block/` category, sub_group `generation`

| Slug | Purpose | Approach |
|---|---|---|
| `blocks/get-block-guidance` | Given a scenario description (e.g. "hero section for a local service page", "three-column feature grid", "FAQ list with schema"), return one or more recommended block layouts with rationale and a starter block tree. Read-only — does not modify content. | Match `scenario` against a rules-based guidance registry (`Includes\Abilities\Utilities\Block_Guidance_Rules`). Return `{ recommendations: [{ pattern_name, rationale, starter_blocks: array, notes: string[] }] }`. Same rule registry is filter-extensible (`acrossai_block_guidance_rules`). Input: `{ scenario: string, max_recommendations?: int = 3 }`. |

### Recipe enumeration (3) — under `Block/` category, sub_group `generation`

| Slug | Purpose | Approach |
|---|---|---|
| `blocks/list-page-recipes` | Return every registered full-page recipe: `id`, `title`, `description`, `section_slugs[]` (the sections the recipe assembles), `example_input_shape`. Read-only. | Read from bundled JSON + apply the recipes filter (see Decision A). Filter by optional `keyword` search. Output: `{ recipes: [...], total }`. |
| `blocks/list-section-recipes` | Return every reusable-section recipe: `id`, `title`, `description`, `input_shape`, `preview_blocks: array`. Read-only. | Same registry pattern as `list-page-recipes` but for section recipes. |
| `blocks/list-query-section-recipes` | Return every dynamic `core/query` recipe: `id`, `title`, `description`, `input_shape`, `post_type_defaults`, `preview_blocks: array`. Read-only. | Same registry pattern; specialized for query-loop recipes. |

### Generators (3) — under `Block/` category, sub_group `generation`

| Slug | Purpose | Approach |
|---|---|---|
| `blocks/generate-landing-page` | Generate a structured landing-page block tree from a small input set: `business_name`, `tone`, `sections[]` (list of section recipe IDs to compose). Returns block tree — does not save. Theme-neutral (no hardcoded colors, typography, or width overrides). | Load selected section recipes, apply input substitutions (business name into headline templates, tone into copy templates), assemble into a top-level container tree. Output: `{ blocks: array, meta: { section_ids_used, warnings: string[] } }`. Input: `{ business_name: string, tone: string, sections: string[], recipe_id?: string }`. |
| `blocks/generate-section` | Generate one reusable section from a recipe ID + input payload. Returns block tree. | Load recipe; validate `input` against `input_shape`; apply substitutions; return blocks. Input: `{ recipe_id: string, input: object }`. Output: `{ blocks: array, meta: { warnings: string[] } }`. |
| `blocks/generate-query-section` | Generate a dynamic `core/query` section from a recipe ID + input payload (post_type, per_page, template shape). Returns block tree. | Load query recipe; validate + apply; return blocks. Input: `{ recipe_id: string, input: object }`. |

### Page creators (3) — under `Block/` category, sub_group `content`

| Slug | Purpose | Approach |
|---|---|---|
| `blocks/create-page-from-blocks` | Create a new WordPress page from a structured block tree. Thin wrapper around `wp_insert_post()` with pre-save validation via Feature 074's `validate-content`. | Validate `blocks[]` → serialize → `wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => ..., 'post_content' => serialized ] )`. Input: `{ title: string, blocks: array, status?: 'publish'\|'draft' = 'publish' }`. Output: `{ success, page_id, edit_url, view_url, message }`. |
| `blocks/create-page-from-pattern` | Create a new WordPress page from a registered pattern slug. Optional input substitutions applied to placeholder tokens in the pattern markup. | Load pattern via `WP_Block_Patterns_Registry`; optional token substitution; `wp_insert_post()`. Input: `{ title: string, pattern_slug: string, substitutions?: object, status?: enum = 'publish' }`. |
| `blocks/create-landing-page` | Composite: (1) call `generate-landing-page` internally, (2) create a page from the result, (3) return page metadata + generation warnings in one round trip. | Internal composition of two abilities; single response envelope. Input: `{ title: string, business_name: string, tone: string, sections: string[], recipe_id?: string, status?: enum = 'publish' }`. |

## Reused utilities (do not reinvent)

- **`Ability_Definition`** parent class.
- **Feature 074's `blocks/validate-content`** — called internally by all 3 page-creator abilities before persistence. If validation fails, the create is rejected with the validator's issue list surfaced verbatim.
- **Feature 074's `blocks/serialize-blocks`** — called internally to convert `blocks[]` inputs to `post_content` strings. Do not shadow.
- **`WP_Block_Patterns_Registry`** — canonical pattern registry.
- **`Block_Info`** — for block-name validation.
- **`meta.acrossai.sub_group`** — `'generation'` for guidance + recipes + generators; `'content'` for page-creators.

## New utility classes (proposed)

- **`Includes\Abilities\Utilities\Block_Guidance_Rules`** — scenario → recommendation matcher, filter-extensible.
- **`Includes\Abilities\Utilities\Block_Recipe_Registry`** — loads bundled JSON, applies filter, serves `page_recipes` / `section_recipes` / `query_section_recipes`.
- **`Includes\Abilities\Utilities\Block_Recipe_Renderer`** — validates input against recipe `input_shape`, applies substitutions, returns a block tree. Shared by all 3 generators.

## Common shape (all 10)

- Namespace: `AcrossAI_Abilities_Manager\Includes\Abilities\Block`.
- Category slug: `acrossai-abilities-manager-block`.
- Reads (guidance + recipe enumerators + generators): `readonly: true, destructive: false, idempotent: true` — generators produce output without saving.
- Page creators: `readonly: false, destructive: false, idempotent: false`.
- All string inputs sanitized with `sanitize_text_field()`; recipe IDs validated against the registry (unknown ID rejected with actionable message).
- All generator output is theme-neutral — no `style` attributes with hardcoded colors, no block-level typography overrides. Layout uses `align: 'wide'` / `align: 'full'` (theme-declared support), never explicit widths.
- All returned messages wrapped in `__( '...', 'acrossai-abilities-manager' )`.
- `meta.show_in_rest = true`, `meta.mcp = { public: false, type: 'tool' }`.
- Generator output envelope: `{ success, blocks: array, meta: { ... }, message }`.
- Page-creator output envelope: `{ success, page_id, edit_url, view_url, warnings?: string[], message }`.

## Bootstrap wiring

Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()`:

- Add 7 new `new Block\<Class>();` lines for guidance + recipes + generators (`Get_Block_Guidance`, `List_Page_Recipes`, `List_Section_Recipes`, `List_Query_Section_Recipes`, `Generate_Landing_Page`, `Generate_Section`, `Generate_Query_Section`).
- Add 3 new `new Block\<Class>();` lines for page creators (`Create_Page_From_Blocks`, `Create_Page_From_Pattern`, `Create_Landing_Page`).
- Add 1 include for the bundled recipes JSON directory.

## Testing

Under `tests/phpunit/abilities/`, one test file per new ability.

Guidance:
- `get-block-guidance` on `scenario: 'hero section'` returns at least one recommendation with non-empty `starter_blocks`.

Recipes:
- `list-page-recipes` returns at least one bundled recipe.
- Filter-added recipe (`acrossai_block_recipes` filter in test setUp) appears in the output.

Generators:
- `generate-landing-page` with `business_name: 'Example'` + 2 section IDs → returns a block tree containing 2 top-level sections; substitution replaces the headline placeholder with `'Example'`.
- Output is deterministic: same input → same output (byte-for-byte).
- No hardcoded color / typography attributes anywhere in generator output (assert on serialized markup).

Page creators:
- `create-page-from-blocks` with a valid block tree → page created, `page_id > 0`, `get_post($page_id)->post_content` equals expected serialization.
- `create-page-from-blocks` with an invalid block tree → rejected with `validate-content` issues surfaced.
- `create-landing-page` composite → single response contains `page_id` + generator warnings.

Guardrails:
- Unknown `recipe_id` → rejected with actionable message.
- Missing required substitution input → rejected against recipe's `input_shape`.
- Empty `blocks[]` on `create-page-from-blocks` → rejected.

Target: ~10 golden-path tests + ~8 guardrail tests.

## Delivery

Feature branch off `main`. No version bump — bundle into the next `release-0.0.X`.

## Dependencies

- **Depends on Feature 074** — page-creator abilities call `validate-content` and `serialize-blocks` internally. If 074 has not landed yet, temporarily inline the validation via direct `parse_blocks()` and defer the QA gate to a follow-up.
- **Independent of Features 070–073** — can land in any order once 074 is in.
- **Bundled recipes** — start with a minimal set (2 page recipes, 3 section recipes, 2 query-section recipes). More can be added later via the recipes filter without a code change.
