# Feature 074 — Block parsing, serialization & content-quality analysis

**Status**: input brief for `/speckit-specify`. Written 2026-08-23.

## Problem

Two adjacent gaps in the content-quality surface:

1. **Structured content ops (parse / serialize)** — the plugin can read a post's tree (`blocks/get-post-blocks`) and write it back (`blocks/update-post-block`, mutation abilities), but there is no *stateless* pair of abilities that (a) parses an arbitrary block-markup string into a canonical tree without loading a post, and (b) serializes a canonical tree back into block markup without saving it. These primitives are what most higher-order tools (generators, transformers, diff tools, dry-run validators) actually need.
2. **Content-quality analysis (QA layer)** — the plugin can save whatever the client sends. There is no pre-save layer that (a) validates block markup for shape and mutation safety, (b) audits QA smells (missing alt text, buttons with no destination, orphan headings, empty containers), (c) analyzes structural metrics (outline, media count, link count, block-type distribution), or (d) evaluates design and copy quality with actionable remediation suggestions. Every content-authoring workflow re-implements this by hand.

This feature adds 2 structured-content primitives + 8 analysis abilities, split into three sub-groups. `manage_options` remains the sole access gate. All abilities in this feature are `readonly: true` — this is an inspection layer, not a mutation layer.

## Deferred design decision (surface at `/speckit-clarify`)

**Quality-heuristic extensibility model.** Three options:

1. **Rules-based, built-in only** — every check hardcoded in class constants. Simplest, no extension seam.
2. **Rules-based + filter-extensible** — built-in rule set plus a WordPress filter (`acrossai_block_qa_rules`) that lets other plugins add or replace rules. Recommended default.
3. **Fully pluggable rule engine** — a rule interface + registrar so external code can drop in rule classes. Most flexible, but adds public API surface and version-compat commitments.

Default proposal: **option 2** (filter-extensible with a documented rule shape). Confirm during `/speckit-clarify`.

## Proposed abilities

Slug convention per verb-first (Feature 058) under the `blocks/` namespace.

### Structured content primitives (2) — under `Block/` category, sub_group `content`

| Slug | Purpose | Core APIs |
|---|---|---|
| `blocks/parse-content` | Parse an arbitrary block-markup string into a normalized block tree. Stateless — no post ID required. | `parse_blocks( $content )`. Optional `include_raw?: bool = false` returns each block's raw markup alongside the parsed tree. Input: `{ content: string, include_raw?: bool = false }`. Output: `{ success, blocks: array, block_count: int, message }`. |
| `blocks/serialize-blocks` | Serialize a normalized block tree back into block markup. Stateless. Round-trip parity: `serialize_blocks(parse_blocks($x)) === $x` for valid input. | `serialize_blocks( $blocks )`. Validate the input array shape (`block_name`, `attrs`, `inner_blocks`, `inner_html`, `inner_content`) before serializing; reject invalid entries with a path-annotated message. Input: `{ blocks: array }`. Output: `{ success, content: string, message }`. |

### Validation & audit (3) — under `Block/` category, sub_group `analysis`

| Slug | Purpose | Approach |
|---|---|---|
| `blocks/validate-content` | Validate block-tree shape + mutation safety + layout-risk styles. Accepts either a `post_id` (validate the stored content) or `blocks[]` / `content` (validate arbitrary input). Returns structured issue list. | Checks: (a) block_name matches registered block-type; (b) required attributes present; (c) block-name/namespace format; (d) nested-block validity against parent's `allowedBlocks`; (e) known layout-risk patterns (empty container, full-width breakout without a shell wrapper). |
| `blocks/audit-content` | Run authoring-quality checks: missing alt text on `core/image`, buttons with empty `url`, headings without content, orphan `core/list-item` (list-item outside list), duplicate H1, empty `core/group`. Returns issues with severity + block path. | Traverse tree; per-block-type check registry (see deferred decision above). Returns `{ issues: [{ severity, code, path, block_name, message }], summary: { errors, warnings, notices } }`. |
| `blocks/evaluate-render-context` | Inspect the rendered page wrapper around the block content — find the element that carries `.entry-content` / `.page-content` (or theme-declared equivalent) and report wrapper class list, computed max-width, container padding, and whether the theme's shell would clip full-width content. Complements `validate-content` (which sees block markup only). | Render the target post via `get_the_content()` inside a mock-loop context; use DOMDocument to find the wrapper; walk up the DOM tree to identify the outermost container that constrains width. Requires a `post_id`. |

### Design & copy quality (5) — under `Block/` category, sub_group `analysis`

| Slug | Purpose | Approach |
|---|---|---|
| `blocks/analyze-content` | Structural metrics: outline (heading hierarchy tree), internal + external link counts, media (image + video + embed) counts, block-type distribution histogram, word count, estimated read time. | Single tree walk; aggregate counters; return `{ outline: [{ level, text, path }], links: { internal, external, total }, media: { images, videos, embeds }, block_types: { <name>: count }, word_count, read_time_minutes }`. |
| `blocks/evaluate-design` | Score design coherence + flag layout risks: card monotony, section rhythm drift, internal-measure mismatch, full-width section seam gaps, boxed-vs-open sibling inconsistency, over-diverse border-radius / shadow token usage. Returns 0–100 score + issue list. | Rule-based (see deferred decision). Reuse the same tree-walk as `analyze-content`; layer design-specific rules. Returns `{ score: int (0-100), issues: [{ severity, code, path, message }] }`. |
| `blocks/suggest-design-fixes` | Convert issues from `evaluate-design` into concrete remediation suggestions (block-attribute changes, structural transforms, spacing adjustments). Read-only — returns suggestions, does not apply. | Per-issue-code suggestion registry mapping issue → suggested-fix payload. Input: `{ post_id: int }` OR `{ issues: array }` (pass-through mode for pre-analyzed input). Output: `{ suggestions: [{ issue_code, path, suggestion_type, suggestion_payload, rationale }] }`. |
| `blocks/evaluate-copy` | Score copy quality + flag weak patterns: bare label chips (proof rows with no supporting text), sentence-run-on lengths, missing calls-to-action on hero sections, weak headline verbs, excessive passive voice. Returns 0–100 score + issue list. | Extract text nodes from the tree (paragraph, heading, list-item, button label, quote); apply copy-quality rules. |
| `blocks/suggest-copy-fixes` | Convert issues from `evaluate-copy` into rewrite suggestions. Read-only. | Per-issue-code suggestion registry mapping issue → rewrite payload (before → after text). |

## Reused utilities (do not reinvent)

- **`Ability_Definition`** parent class.
- **`Block_Tree_Path_Resolver`** — for path annotation on issues.
- **`Block_Info`** — for block-type metadata used by rule checks.
- **WP core `parse_blocks()` / `serialize_blocks()`** — canonical, do not wrap.
- **`WP_Block_Type_Registry`** — for registered-block validation.
- **`meta.acrossai.sub_group`** — `'content'` for the 2 primitives, `'analysis'` for the 8 QA abilities.

## New utility classes (proposed)

- **`Includes\Abilities\Utilities\Block_QA_Rules`** — rule registry backing `validate-content`, `audit-content`, `evaluate-design`, `evaluate-copy`. Exposes a `apply_filters( 'acrossai_block_qa_rules', $rules, $context )` hook per the deferred decision above.
- **`Includes\Abilities\Utilities\Block_Content_Analyzer`** — single-walk analyzer that produces the metrics consumed by `analyze-content` and reused by design/copy rules.

## Common shape (all 10)

- Namespace: `AcrossAI_Abilities_Manager\Includes\Abilities\Block`.
- Category slug: `acrossai-abilities-manager-block`.
- All abilities `readonly: true, destructive: false, idempotent: true`.
- Accepts either `post_id` (fetch stored content) or `blocks[]` (structured input) or `content: string` (raw markup) — `oneOf` at the schema layer for the two primitives; QA abilities accept `post_id` OR analysis pass-through payload.
- All string inputs sanitized with `sanitize_text_field()`.
- All returned messages wrapped in `__( '...', 'acrossai-abilities-manager' )`.
- `meta.show_in_rest = true`, `meta.mcp = { public: false, type: 'tool' }`.
- Issue-list output shape is consistent across `validate-content`, `audit-content`, `evaluate-design`, `evaluate-copy` — same `{ severity, code, path, block_name?, message }` per entry so clients can build one issue UI.

## Bootstrap wiring

Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()`:

- Add 2 new `new Block\<Class>();` lines for the primitives (adjacent to `Get_Post_Blocks`).
- Add 8 new `new Block\<Class>();` lines for the analysis abilities, grouped under a new inline comment block within the Block section.

## Testing

Under `tests/phpunit/abilities/`, one test file per new ability.

Primitives:
- `parse-content` on `'<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->'` returns 1 block, `block_name === 'core/paragraph'`.
- `serialize-blocks` round-trip: `serialize-blocks(parse-content($x)).content === $x` for representative fixtures.
- Guardrail: `serialize-blocks` with a malformed entry (missing `block_name`) → rejected with path-annotated message.

Analysis:
- `validate-content` on a tree with an unknown block name → issue emitted with `severity: 'error'`.
- `audit-content` on a `core/image` with empty `alt` → issue emitted with `severity: 'warning'`.
- `analyze-content` on a fixture with 3 paragraphs (2 with links) + 1 image → correct counts.
- `evaluate-design` on a fixture with 5 identical rounded-box sections → `card_monotony` issue emitted.
- `evaluate-copy` on a fixture with a proof row containing 3 label-only chips → `noninteractive_control_affordance_risk` issue emitted.
- `suggest-design-fixes` on the design-eval output → suggestions returned with `suggestion_payload` shape validated.
- `evaluate-render-context` on a post rendered inside a `.wp-block-group` wrapper → wrapper class list returned.

Target: ~10 golden-path tests + ~8 guardrail tests.

## Delivery

Feature branch off `main`. No version bump — bundle into the next `release-0.0.X`.

## Dependencies

- **Independent of Features 070–073** — can land in any order.
- **Depends on WP core `parse_blocks()` / `serialize_blocks()`** — available since WP 5.0.
- **`evaluate-render-context` requires a rendered post context** — mock the loop in test setup via `setup_postdata()`.
