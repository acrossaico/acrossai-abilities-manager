# Feature 105 — ACF ability suite (inputs for /speckit-specify)

This file is the natural-language brief that `/speckit-specify` should turn into `spec.md`. Do not treat it as the spec itself.

**Consolidates three earlier briefs.** `specs/096-acf-block-abilities/inputs.md`, `specs/097-acf-field-value-abilities/inputs.md` and `specs/098-acf-repeater-flex-row-abilities/inputs.md` remain the detailed source for their respective abilities — this brief supersedes them on scope, grouping and gating, and records four decisions that depart from what they say. Closes issues #163, #166 and #169.

## One-sentence goal

Add 16 ACF abilities — field values on any target, repeater and flex-content row operations, and ACF block registration and insertion — so an AI client can read and write ACF data correctly on the 70%+ of ACF surface that ACF's own Abilities API does not reach.

## Why now

ACF 6.8 shipped its own Abilities API integration, already wired into this plugin through `includes/Abilities/Integrations/ACF.php` (Feature 060). It covers **schema** (`acf/field-groups`, `acf/register-field-group`, CPTs, taxonomies) and **per-CPT record CRUD** for ACF-managed post types only.

**Gap**, in ACF's own words from its `register-field-group` docstring:

> This creates the field structure that will appear on content, not the field values themselves.

So there is no ACF-native route to a field **value** on a standard `post`/`page`, a theme-registered CPT, a user, a term, a comment or an options page. Nor to a block. Nor to a single repeater row.

The naive workaround is actively harmful. `content/update-post-meta` writes to `postmeta` directly and silently corrupts every field type with ACF-specific serialisation — repeaters, flexible content, clones, relationships, post objects, taxonomy and user fields all store a value row plus a `_`-prefixed field-key reference row, and complex types store per-row sub-keys. Writing the value row alone leaves ACF unable to read its own data back. **Proving this is a verification step, not an assumption** (see below).

## Scope — 16 abilities

Category `acrossai-acf`. `meta.acrossai.tab_group = 'acf'` on all 16. Three `sub_group` cards.

### `acf-fields` — field values on any target (5)

Build first; the target resolver everything else depends on lands here.

1. **`custom-fields/get-acf-field`** — Read one field value from any target. Wraps `get_field()` so complex types hydrate.
2. **`custom-fields/get-acf-fields`** — Bulk-read every ACF field on a target. Wraps `get_fields()`. Returns **rows**, `[{name, key, type, value}]`, never a name-keyed map.
3. **`custom-fields/update-acf-field`** — Write one value. Wraps `update_field()`.
4. **`custom-fields/update-acf-fields`** — Bulk-write; returns `{updated: [], failed: []}`, matching `User_Helpers::apply_meta()`.
5. **`custom-fields/delete-acf-field`** — Clear one value. Wraps `delete_field()` so the field-key reference row and repeater sub-rows go too. **Destructive**, confirm-gated.

### `acf-rows` — repeater and flex-content rows (6)

Row-scoped so the common editor action ("add one more row", "delete the second card") is one call rather than a read-modify-write of the whole array, which loses diff visibility and grows with the field.

6. **`custom-fields/add-acf-repeater-row`** — Append or insert at a position. Wraps `add_row()`.
7. **`custom-fields/update-acf-repeater-row`** — Patch one row; unspecified sub-fields preserved. Wraps `update_row()`.
8. **`custom-fields/remove-acf-repeater-row`** — Remove one row. Wraps `delete_row()`. **Destructive**, confirm-gated.
9. **`custom-fields/reorder-acf-repeater-rows`** — Replace row order with a permutation. Rejects anything that is not a valid permutation of the current row count.
10. **`custom-fields/add-acf-flex-layout`** — Append or insert one flexible-content layout with its sub-field values.
11. **`custom-fields/remove-acf-flex-layout`** — Remove one layout. **Destructive**, confirm-gated.

**1-based indexes throughout**, matching ACF's public API. State it in every schema description — a model defaults to 0-based habits.

### `acf-blocks` — ACF blocks (5)

12. **`blocks/register-acf-block`** — Create an ACF block type. Wraps `acf_register_block_type()`. Optional inline field-group definition so one call yields a wired block.
13. **`blocks/list-acf-blocks`** — Every ACF-registered block: name, title, category, bound field group, field count.
14. **`blocks/get-acf-block-fields`** — The field-group fields behind a block name, including sub-fields.
15. **`blocks/insert-acf-block`** — Insert a block instance into a post with a `data` payload validated against the field group.
16. **`blocks/update-acf-block-data`** — Patch the `data` attribute of one block instance.

**Block field values live in `post_content`, not `postmeta`.** These must NOT route through `update_field()` — that is the single most likely implementation error in this group.

## Decisions that depart from briefs 096 / 097 / 098

### 1. One feature, not three

They share a guard, a target resolver and a category. 098 already declared a hard dependency on 097. Building them separately means writing that shell three times.

### 2. `tab_group = 'acf'` — no new `custom-fields` toolset

Briefs 097 and 098 imply a new group. Every toolset is an always-loaded MCP tool, the site is already at 19, and `DEC-ABILITY-GROUP-TAXONOMY` treats that count as a ceiling because tool-choice accuracy degrades as the list grows.

Slug namespaces still follow the briefs — `custom-fields/*` and `blocks/*` — because **slug namespace and toolset group are independent**. `DEC-TOOLSET-SLUG-NAMESPACE` says a namespace names the resource being acted on; the toolset names where an operator finds it. Everything ACF therefore lands on one tab and disappears together when ACF does.

### 3. Register whenever ACF is present — NOT behind the integration toggle

All three briefs say to gate on the "Advanced Custom Fields (AI)" toggle. **That is wrong.** The toggle flips `acf/settings/enable_acf_ai`, which governs *ACF's own* abilities. Ours call `get_field()`/`update_field()` directly and work whatever that setting says, so binding them to it would hide working functionality behind an unrelated switch — and an operator who turned the toggle off to silence ACF's abilities would silently lose ours too.

### 4. The Pro gate is the field type, not the function

Brief 098 and issue #169 say to gate the row abilities on `function_exists( 'add_row' )`. **That does not work.** `add_row()`, `update_row()` and `delete_row()` ship in `includes/api/api-template.php` in *both* editions, so the check passes on free ACF — where `repeater` and `flexible_content` are not registered field types and no repeater field can exist. Six abilities would be advertised in the MCP tool list that can never succeed.

## The two editions

**ACF and ACF Pro are mutually exclusive.** Pro is a *replacement* for free ACF — same `acf.php`, same `ACF_VERSION`, same function set, plus four extra field types (`repeater`, `flexible-content`, `clone`, `gallery`) and blocks. WordPress refuses to run both, auto-deactivating one:

> Advanced Custom Fields and Advanced Custom Fields PRO should not be active at the same time. We've automatically deactivated Advanced Custom Fields PRO.

That removes a case rather than adding one — there is no both-active state and no double-registration risk. It also means **detection must read loaded code, never the filesystem**: a development machine commonly has both directories, and that says nothing about which is running.

**5 of the 16 work on free ACF; 11 require Pro.**

| Group | # | Free ACF | Why |
|---|---|---|---|
| `acf-fields` | 5 | yes | `get_field`/`update_field`/`delete_field` are in both editions |
| `acf-rows` | 6 | no | functions are in both, but `repeater` and `flexible-content` are Pro-only field types |
| `acf-blocks` | 5 | no | `acf_register_block_type()` is absent from free ACF |

Gate per group, not suite-wide, so a free-ACF site gets 5 abilities rather than none.

### Detection

Compound checks per SEC-002 — a single symbol is spoofable by any plugin defining the same name.

- **ACF present** — `defined( 'ACF_VERSION' ) && function_exists( 'acf_get_setting' )`. Reuse `Integrations/ACF::is_plugin_active()`.
- **Pro edition** — `function_exists( 'acf_is_pro' ) && acf_is_pro()`. ACF's own helper, in both editions since 6.2 (`includes/acf-helper-functions.php:676`), returning `defined( 'ACF_PRO' ) && ACF_PRO`. Pro sets it at `pro/acf-pro.php:26` via `acf()->define( 'ACF_PRO', true )`; free never does. Asking ACF beats inferring, and survives ACF changing how it marks the edition.
- **Blocks additionally** — `function_exists( 'acf_register_block_type' )`.
- **Rows additionally** — `acf_get_field_type( 'repeater' )` / `acf_get_field_type( 'flexible_content' )`, which also covers a field type removed by filter rather than by edition.

Gate at boot; re-check inside `execute()`, because the edition can be switched after registration in the same request.

## wp.org policy — permitted, and already precedented here

This plugin is distributed on wp.org (`.github/workflows/wordpress-plugin-deploy.yml`, via `10up/action-wordpress-plugin-deploy`), so adding features for a commercial plugin needs to be checked rather than assumed. It is allowed:

- **Guideline 5 (trialware)** bars locking *our own* functionality behind *our own* payment. Depending on a third-party plugin is a dependency, not trialware.
- **Guideline 6** permits interfaces to third-party services "even for paid services", provided they offer real functionality and are documented in the readme.
- **Guideline 17 (trademarks)** constrains the plugin *slug*. We are not renaming anything, and ability slugs already carry vendor names.
- **Guideline 2** — no bundling of ACF Pro code. We call its public API and ship none of it.

The decisive precedent is local: this plugin already ships **Elementor Pro-gated abilities** on wp.org — `Utilities/Elementor/Document_Repository.php:104` gates on `class_exists( '\ElementorPro\Plugin' )`, and `Elementor/List_Custom_Code.php` is documented Pro-only. Rank Math's premium surfaces are gated the same way.

Two obligations follow: the plugin must work without ACF (it has 537 other abilities and this suite self-suppresses), and **the readme must state which abilities need Pro** — an operator on free ACF should not have to wonder where the other 11 went.

## Constraints

- **No ACF symbol outside `includes/Abilities/Utilities/Acf/`** — architecture test, as for Contact Form 7 and LiteSpeed. Note the trap Feature 104 hit: our own namespace contains the vendor string, so the test must strip it before searching.
- **Rows, never maps, in `array`-typed output.** `BUG-ARRAY-TYPED-OUTPUT-IS-A-JSON-OBJECT` has now bitten twice. An associative array encodes as a JSON object and fails the ability's own output schema *after* the write. `get-acf-fields` is the obvious risk; the Feature 104 guard is indirection-aware and must be extended here.
- **`Slash_Input`** (`includes/Abilities/Utilities/Slash_Input.php`) on every writer: `schema_fragment()` into the input schema, `slash( $value, $input )` around the write, `meta_flags()` into `meta`.
- **`suggested_abilities()`** — `update-acf-field` → `get-acf-field`; every row mutator → `get-acf-field` (read the rows to learn the valid index range); `insert-acf-block` → `get-acf-block-fields`; `register-acf-block` → `list-acf-blocks`.
- **Floor `manage_options`, declared `final`.** No per-target capability checks in this feature; note it as a deliberate deferral.
- **`confirm` never schema-required** — core validates the schema before `execute()`, so a required `confirm` yields a generic `ability_invalid_input` and the gate never fires.
- **`AcrossAI_Category_Slug_Migration::OWNED` must gain `acf`.** Feature 104 shipped without its entry and a cross-cutting test caught it.

## Files

New: `includes/Abilities/Acf/Base_Acf_Ability.php`, 16 ability classes, `Category_Registrar.php`; `includes/Abilities/Utilities/Acf/{Acf_Guard,Acf_Target,Field_Repository,Block_Repository}.php`; two test files plus a guard suite.

Modified: `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` (three edits), `AcrossAI_Category_Slug_Migration.php`, `Test_Ability_Group_Map.php` (both skip arrays), `phpunit.xml.dist`, `README.txt`, `docs/abilities-inventory.md` (regenerated).

**No `Integrations/` file and no `built_in()` change** — `Integrations/ACF.php` already declares the `acf` toolset. This is the first suite to *join* an existing toolset rather than create one.

**`Acf_Target` is the piece to get right.** It resolves `(target_type, target_id)` into what ACF expects — `123`, `user_45`, `term_12`, `comment_7`, `option` — and validates the target exists. All 11 field and row abilities take that pair, so it is the one place a bad target is caught.

## Verification

The site currently runs **free ACF**, with Pro installed but deactivated — the better starting point, because it proves the 11 Pro abilities stay unregistered with no special setup.

1. Execute all 16 in process via a temporary mu-plugin REST route calling `wp_get_ability( $name )->execute( $input )`, deleted before committing. The core GET run route cannot express typed object input (`BUG-GET-RUN-ROUTE-CANNOT-EXPRESS-TYPED-INPUT`).
2. **The test that justifies the feature**: write a repeater via `update-acf-field` and read it back intact; write the same payload via `content/update-post-meta` and show ACF cannot read it. If this does not reproduce, the premise is wrong and the scope needs revisiting.
3. All five targets: post, user, term, comment, options page.
4. A CPT registered outside ACF with a field group attached — the gap ACF's own per-CPT CRUD leaves.
5. Rows: add, update by 1-based index, remove, reorder; reject an invalid permutation and an out-of-range index.
6. Flex layouts: add and remove; a repeater-only field must be rejected with a typed error.
7. Blocks: register, list, introspect, insert, patch `data`; confirm the `<!-- wp:acf/... -->` markup in `post_content`.
8. **Edition switch — the check that actually proves the gating.** On free ACF, exactly 5 register and the 11 Pro slugs are absent from `toolset/acf action=discover` rather than present-and-failing. Activate Pro (free auto-deactivates) and confirm the count rises by exactly 11. Confirm stored per-ability overrides for the Pro slugs stay dormant rather than being erased (`DEC-ACCESS-CONTROL-MUST-LEAVE-EVIDENCE`).
9. `sum(counts) === total`; dispatch through `toolset/acf`; a cross-group call refused with `ability_not_in_group`. **Needs mcp-adapter active** — currently it is not.
10. `vendor/bin/phpunit`, `npx wp-scripts test-unit-js`, PHPCS on the new files. **Do not report a PHPStan result from `composer phpstan`** — it analyses nothing (issue #190). Run it with a working config and report that.
11. Regenerate `docs/abilities-inventory.md`; changelog entry naming which 11 abilities need Pro.

## Suggested next steps (for the human)

`/speckit-specify` → `/speckit-plan` → `/speckit-tasks` → implement. `spec.md` / `plan.md` / `tasks.md` intentionally not hand-authored.
