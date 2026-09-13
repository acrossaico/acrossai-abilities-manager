# Feature 108 — tell an agent how a post is built, before it writes

## One-sentence goal

Add `content/inspect-post-builder`, which reports whether a post is owned by Elementor, another page
builder, the block editor, the classic editor, or is empty — and make sure an AI client actually
sees the warning before it edits content.

## Why

Every content writer in this plugin writes `post_content`. A page builder does not keep its layout
there, and on an Elementor post the combination fails three ways at once, all of them silent:

1. `Elementor\Frontend::apply_builder_in_content()` (`includes/frontend.php:1094`) replaces
   `$content` wholesale on `the_content`, so the write changes nothing a visitor sees.
2. `Elementor\Db::save_plain_text()` (`includes/db.php:225`) rewrites `post_content` from the widget
   tree on **every** save in the editor, so the write is reverted the next time anyone opens the page.
3. The ability reports success, because `wp_update_post()` genuinely succeeded.

So `post_content` on an Elementor page is not empty — it is stale, derived plain text. An agent
editing it gets a green result and no effect.

**Two builders invert the risk.** WPBakery and Fusion render *from* `post_content` as shortcodes, so
a naive rewrite lands and destroys the layout instead of doing nothing. One warning does not fit
both cases, which is why the verdict is three-way and not a boolean.

Nothing in the plugin detected any of this before: no `has_blocks()` call anywhere in the abilities
layer, and no builder detection of any kind.

## Scope — 1 ability, plus the wiring that makes it reach an agent

### `content/inspect-post-builder`

Takes `post_id`, or `post_ids` for up to 100 at once so a bulk edit can be scoped in one call.
Returns per post: the builder, whether that builder is still installed, where the content actually
lives, a three-way verdict on `post_content` writes (`applies` / `ignored` / `destructive`), the
byte size of `post_content`, the signals the answer was derived from, and a `guidance` sentence
written for the caller to act on.

Floor is `edit_posts`, not `manage_options` — it is read-only, it reveals nothing sensitive, and a
guard nobody can afford to call is a guard nobody calls.

### Why the `content` group and not `elementor`

The Elementor suite is gated on `class_exists( '\Elementor\Plugin' )`, so the whole group disappears
when Elementor is deactivated — exactly when a post still flagged as Elementor-built is most likely
to be mishandled. The detector has to be builder-agnostic and always present, and it has to be in
the same toolset as the writers it is warning about.

## The hard part: a suggestion alone does not reach the agent

`meta.acrossai.suggested_abilities` could not do this job as it stood, for two independent reasons:

- `DEC-ABILITY-SUGGESTED-ABILITIES-CONTRACT` deliberately keeps suggestions out of
  `discover-abilities` to keep listing payloads small.
- `Base_Toolset_Ability::summarise()` and `describe()` build their own rows and read only
  `annotations` out of meta, so on the **toolset path — the primary MCP surface** — suggestions were
  invisible on discover *and* info.

An agent going straight to execute therefore saw them never, anywhere. Three layers now carry the
warning:

1. **Toolset descriptions.** `Toolset/Content.php` and `Toolset/Blocks.php` each gained a sentence
   naming the detector. This is the only surface guaranteed to be read before tool selection. The
   precedent already existed in `Toolset/Elementor.php`, which says the same thing — but only in
   Elementor's own description, which an agent working in the Content tool never reads.
2. **Suggestions on 19 writers.** The three `content/update-*` gained the detector as their *first*
   suggestion; 16 `blocks/*` writers that had no `suggested_abilities()` at all now declare one.
3. **A framework fix.** `describe()` now surfaces `suggested_abilities` on `action=info`, declared in
   the response schema and honouring the existing `acrossai_disable_ability_suggestions`
   kill-switch. This is ~30 lines and it makes every suggestion already in the codebase work on the
   MCP path, not just this feature's.

Suggestions stay out of `summarise()`, so discovery payloads are unchanged and the contract holds.

## Constraints that shaped the implementation

- **A builder flag beats block markup.** Elementor leaves derived text in `post_content` and Divi
  keeps shortcodes there, so checking `has_blocks()` first would mislabel both. Flagged builders are
  checked first, then content markers, then core.
- **Prefer the builder's own API, but never depend on it.** Elementor's
  `Plugin::$instance->documents->get( $id )->is_built_with_elementor()` is authoritative and is used
  when the class is loaded; the meta key is the fallback, because a post stays builder-owned after
  the plugin is deactivated and that is the case where the answer matters most. Note
  `Db::is_built_with_elementor()` is deprecated as of Elementor 3.2.0 and is not used. Also note
  `documents->get()` returns `false`, not `null`, for an unknown post.
- **Ownership and availability are separate facts.** A post flagged for a builder whose plugin is
  gone renders whatever is in `post_content`, so a write does take effect now and will vanish if the
  builder returns. That gets its own guidance sentence.
- **Empty is not classic.** `has_blocks()` is a substring test for `<!-- wp:` and returns false for
  both, but writing to an empty post is always safe while writing over classic content replaces
  someone's markup.
- **Rows, never maps** in `array`-typed output (`BUG-ARRAY-TYPED-OUTPUT-IS-A-JSON-OBJECT`). This bit
  during implementation in a new place: the dispatcher's own response schema declares
  `additionalProperties: false` on its ability rows, so adding `suggested_abilities` to the row
  without declaring it would have failed the toolset's own output validation.

## Files

New: `includes/Abilities/Utilities/Post_Builder_Detector.php`,
`includes/Abilities/Content/Inspect_Post_Builder.php`, two test files.

Modified: `AcrossAI_Core_Abilities_Bootstrap.php` · `Toolset/Base_Toolset_Ability.php` (describe +
schema) · `Toolset/Content.php`, `Toolset/Blocks.php` · 19 writers ·
`Test_Ability_Group_Map.php` (content 71 → 72) · `tests/bootstrap.php` (`get_post`, `has_blocks`
stubs) · `phpunit.xml.dist` · `docs/abilities-inventory.md` (630 → 631) · `README.txt`.

## Verification

Executed live against a real site, with fixtures created for each builder and deleted afterwards.

- Eight posts classified correctly in one call: Elementor, Divi, WPBakery, an orphaned
  Elementor page, block, classic, and two empty; a non-existent ID reported in `missing`.
- Elementor activated so the **API path** actually ran — signal reported as
  `elementor API=true [builder confirmed ownership]`, and non-Elementor posts correctly fell through
  rather than false-matching.
- The warning verified end to end on the toolset path: `toolset/content action=info` on
  `content/update-post` returns the detector **first** in `suggested_abilities`, and
  `blocks/mutate-block-tree` — which had none before — now carries it.
- `action=discover` confirmed to carry no suggestions.
- Kill-switch confirmed: 3 suggestions → 0 with the option on → 3 restored.
- Both toolset descriptions confirmed to name the detector.
- Six guards mutation-verified, including the detection-order one and the schema declaration.
- PHPUnit 2644 / 12389 green, PHPCS clean.

Site state restored: fixtures deleted, Elementor returned to inactive, harness removed.

## Follow-ups, not done here

- The detector answers for one post at a time (or 100). It does not scan a whole site; use
  `content/list-posts` with the builder's ownership meta key, which is what it suggests.
- The writers still do not *refuse* a builder-owned post. There is no shared write chokepoint — 18
  block abilities call `wp_update_post()` directly — so a refusal guard means touching every one and
  changing shipped behaviour. Worth revisiting if the advisory layers prove insufficient.
- Builders other than Elementor are detected from published meta keys and content markers, not from
  their APIs, because none of them are installed here. Those entries are unverified against a live
  install and should be treated as best-effort until one is.
