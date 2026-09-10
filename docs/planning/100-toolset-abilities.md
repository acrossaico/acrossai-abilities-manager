# Planning: Toolset Abilities (Feature 100)

Add one dispatcher ability per ability category — a "Toolset" — so an AI client browses the site's
~450 abilities one category at a time instead of receiving the entire catalogue in a single
unfiltered response.

Today the only MCP-facing surface is the three vendor tools (`mcp-adapter/discover-abilities`,
`mcp-adapter/get-ability-info`, `mcp-adapter/execute-ability`). `discover-abilities` declares **no
`input_schema` at all** and returns every `mcp.public` ability in one response — no category filter,
no search, no pagination. The category structure that makes the Integrations screen navigable stops
at the browser and never reaches a client.

A Toolset carries the same three verbs, scoped to a single category:

```
toolset-media { "action": "discover" }                     → the 11 Media abilities
toolset-media { "action": "info", "ability": "media/…" }    → schemas + annotations
toolset-media { "action": "execute", "ability": "media/…",
                "parameters": { … } }                       → runs it
```

This plugin registers abilities and nothing else. It creates no MCP server, touches no server's tool
registry, and uses no Reflection — consistent with `DEC-PASS-AS-TOOL-REMOVED` ("the Abilities Manager
owns ability metadata; MCP servers own their tool lists"). Toolsets reach a client because the
transport plugin's Tools screen curates arbitrary ability slugs and builds its picker from
`wp_get_abilities()`; the operator removes the three vendor tools there and adds the Toolsets.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "100-toolset-abilities"

# 2. Specify
/speckit.specify "Add one Toolset dispatcher ability per ability category to AcrossAI Abilities
Manager. A Toolset is a single ability that takes an `action` of discover | info | execute and
dispatches to the abilities registered in its own category, so an AI client works through ~450
abilities one category at a time instead of receiving the whole catalogue in one response.

GROUPING KEY IS THE ABILITY CATEGORY, which maps 1:1 to includes/Abilities/<Folder>/. Twenty-five
categories, each with its live ability count:

acrossai-abilities-manager-block 79, -elementor 63, -rank-math ~63, -content 37, -file-manager 23,
-database 18, -users 16, -cron 16, -comments 12, -menus 12, -settings 11, -media 11,
-content-search 11, -plugins 10, -taxonomies 10, -fonts 8, -themes 7, -recovery 7, -options 7,
-debugging 7, -cache 7, -site-health 6, -core 6, -admin-menu 5, -widgets 2.

Do NOT group by meta.acrossai.tab_group. The two agree for 14 of 18 tabs, but tab_group folds nine
folders plus parts of two more into a single 'core' value of 105 abilities — a bucket with no
describable purpose, which is the problem this feature exists to solve. tab_group is also a
hand-typed display string that nothing validates, and it has already drifted: includes/Abilities/
Settings/ holds 11 abilities of which only 1 is tagged tab_group 'settings' and 10 are tagged 'core';
includes/Abilities/SiteHealth/ is split 3/3. Category comes from each ability's own registration,
cannot drift, already carries a registered label and description via wp_register_ability_category(),
and is already the key of the acrossai_library_config option — so a Toolset is gated by the same
Integrations-screen switch as its members, with no special-casing. No admin UI changes: the
Integrations tabs stay exactly as they are.

STRUCTURE — shared base class plus one small subclass per folder, plus an automatic fallback.

Add includes/Abilities/Base_Toolset_Ability.php extending the existing abstract
includes/Modules/Library/Ability_Definition.php (whose single abstract method is ability(): array).
The base class owns ALL shared logic: the input and output schemas, the three-action dispatch, member
resolution, search, pagination, and permission handling (Constitution VI — extract before the second
use).

Each folder then gets includes/Abilities/<Folder>/Toolset.php declaring only a CATEGORY constant, a
SLUG constant, a hand-written label, and a hand-written description. Instantiate them in
AcrossAI_Core_Abilities_Bootstrap alongside the existing per-folder Category_Registrar calls, wired
from Main.php so every add_action traces back to Main.php. Elementor and Rank Math Toolsets
self-guard on class_exists() exactly as their Category_Registrar already does. If a subclass ever
grows logic beyond those four declarations, that logic belongs on the base class.

Descriptions are hand-written because the description is the only thing a model reads when choosing
a tool; generated text would be generic exactly where precision matters. Audit the existing 25
category labels and descriptions as part of this work — several are stale, e.g. the Block category is
labelled 'Block Patterns' but carries 79 abilities spanning far more than patterns.

FALLBACK: after the declared subclasses register, a registrar walks the registered ability categories
and auto-creates a generic Toolset for any category with no declared subclass, using that category's
own registered label and description. This guarantees a new folder, or a third-party plugin that
registers its own category without extending Base_Toolset_Ability, is never silently missing a tool.
Emit a _doing_it_wrong or debug-log notice naming each category running on the fallback so the gap
stays visible.

MEMBER RESOLUTION IS LAZY, AT CALL TIME — not derived at boot. On each call the Toolset walks
wp_get_abilities() and keeps abilities where (1) category matches its own CATEGORY, (2)
meta.mcp.type is 'tool' (resources and prompts excluded, matching every existing surface), (3) the
ability is not itself a Toolset (guard on meta.acrossai.toolset) and is not in
AcrossAI_Protected_Abilities::get_protected_slugs(), and (4) it passes the visibility filter below.
Memoize per request. Resolving lazily means there is no wp_abilities_api_init ordering window to get
wrong, behaviour is identical under WP-CLI where hook ordering differs, and a category toggled
mid-request reports accurately.

INPUT SCHEMA, identical for every Toolset, defined once on the base class:
  action         enum discover|info|execute   REQUIRED
  search         string maxLength 100          discover — substring over name, label, description
  sub_group      string maxLength 64           discover — narrow by meta.acrossai.sub_group
  limit          integer 1..200 default 50     discover
  offset         integer >= 0 default 0        discover
  include_fields array of string               discover/info — trim the response shape
  ability        string maxLength 255          info or execute
  abilities      array of string maxItems 20   info batch; takes precedence over `ability`
  parameters     object                        execute — the target ability's own input
  additionalProperties false

Rationale to encode: `action` is REQUIRED and never defaulted, because an optional action means a
model sending {ability, parameters} intending execute silently receives a listing with no signal it
did the wrong thing. Batch info uses a separate `abilities` array rather than a union type on
`ability`, because clients and JSON Schema validators handle type: [string, array] inconsistently.
Pagination and search are mandatory, not optional — block is 79 abilities and elementor 63, so an
unpaginated dump reproduces the problem being fixed. include_fields ignores unknown keys rather than
erroring, and always returns `name`.

The action vocabulary is discover | info | execute to match the vendor tools' own naming
(get-ability-info) and the 'discover' | 'get_info' | 'execute' context values already used by the
sibling transport plugin's exposure filter, so the two contracts line up.

DISCOVER returns name, label, description and sub_group ONLY. No input_schema, no output_schema, no
annotations — those are the info surface. Explicitly no suggested_abilities and no suggested_plugins
on discover, per DEC-ABILITY-SUGGESTED-ABILITIES-CONTRACT (details-only surface, ~29K tokens saved).
Response carries total, returned, offset and has_more.

OUTPUT SCHEMA is one permissive object keyed by action rather than oneOf, which renders badly in
clients and gives the model no real help: action, group, success, error, error_code, message,
abilities[] (with input_schema/output_schema/annotations present only for info), total, returned,
offset, has_more, not_found[], and `data` typed as the same union ExecuteAbilityAbility uses
(object, array, string, number, integer, boolean, null) because it must absorb ~450 different output
shapes and WordPress runs validate_output() on it. Required: action, success.

Soft failures return success:false plus a machine-readable error_code — ability_not_found,
ability_not_in_group, ability_not_visible, invalid_action, missing_ability — so the model can
self-correct rather than receiving a protocol-shaped failure it cannot learn from. Permission denial
is the deliberate exception: it propagates as a WP_Error from permission_callback so it is a real 403
and cannot be confused with a soft miss.

PERMISSIONS — three layers, none skippable, all testable:
(1) The Toolset's own permission_callback is is_user_logged_in() plus a capability filterable via
    acrossai_toolset_capability, defaulting to 'read'. Deliberately low: this gates a listing, and
    hardcoding manage_options would lock out a legitimately-scoped editor while protecting nothing.
(2) For action=execute the Toolset's permission_callback ALSO resolves the target ability, normalizes
    the parameters, and runs the target's own check_permissions(), propagating any WP_Error verbatim
    — a real 403 raised before execute_callback ever runs.
(3) execute_callback invokes the target via WP_Ability::execute(). NEVER do_execute(), never the raw
    execute callback. WP_Ability::execute() re-runs normalize_input, validate_input,
    check_permissions, the callback and validate_output, and fires wp_pre_execute_ability,
    wp_ability_invoked, wp_before_execute_ability and wp_after_execute_ability — which is what keeps
    AcrossAI_Ability_Override_Processor, access control and any logging in the loop. The double
    permission evaluation is intentional and cheap; comment the call site so it is not optimised away.

Argument normalization is mandatory — MCP clients send {} for no-argument tools and every zero-input
ability breaks without it. Add includes/Utilities/AcrossAI_Ability_Input_Normalizer.php wrapping the
adapter's AbilityArgumentNormalizer::normalize() behind a class_exists() guard, with a local fallback
(empty array plus no input_schema resolves to null), so this plugin gains no hard dependency on the
adapter package.

ANTI-BYPASS INVARIANTS, each written as a testable functional requirement:
- MUST NOT read get_execute_callback() or invoke a target callable directly.
- MUST NOT forward its own $input to the target — only $input['parameters'].
- action=info MUST apply the same membership and visibility gate as execute. Input schemas name
  parameters and constraints, so an ungated info is a read-side information-disclosure bypass. This
  is the single most likely requirement to be forgotten.
- MUST NOT dispatch to another Toolset — guard on meta.acrossai.toolset. No recursion, no
  cross-category hop.
- Every Toolset MUST ship meta.mcp.public = false, matching every other bundled ability, so the
  vendor default server's discover-abilities output does not silently grow by ~25 entries. Make this
  a merge-blocking test. It does not impede the transport plugin's Tools screen, which builds its
  picker from wp_get_abilities() and ignores that flag.
- Toolset slugs are added to the protected list by hooking the existing
  acrossai_abilities_manager_protected_slugs filter, not by editing AcrossAI_Protected_Abilities'
  hardcoded default — the slug set is dynamic.

NAMING: slug is 'toolset/' plus the category with the 'acrossai-abilities-manager-' prefix stripped —
toolset/block, toolset/file-manager, toolset/rank-math. Third-party categories without that prefix
use their sanitized category slug. The vendor sanitizer maps / to - so clients see toolset-block,
toolset-file-manager; longest is toolset-content-search at 22 characters against a 128 limit, so no
mcp_adapter_tool_name filter is needed. Guard collisions with wp_has_ability() before registering —
skip and fire an observability action rather than clobber a third party's slug. Register a 'toolset'
ability category on wp_abilities_api_categories_init.

The 'toolset/' namespace needs a DECISIONS.md entry landed in the same PR, amending the topic-
namespace convention: topic namespaces (blocks/, media/, users/) name the resource an ability acts
on, while 'toolset/' is reserved for abilities that act on the ability catalogue itself. Without that
entry a later cleanup pass will 'correct' the slugs and silently rename every tool.

EXPOSURE: by default a Toolset sees every registered, tool-typed ability in its category. Publish
apply_filters( 'acrossai_toolset_member_visible', true, WP_Ability \$ability, string \$category,
string \$context ) where context is 'discover' | 'info' | 'execute'. Nothing in this plugin hooks it;
it ships as the extension point so a per-server or per-role policy can narrow membership later
without any change here, and its four-argument shape deliberately mirrors the sibling transport
plugin's existing exposure filter so the two read as siblings. Also publish
acrossai_toolset_abilities (outbound catalogue of category, label, ability slug and member count) and
acrossai_toolset_report_unavailable (default false; when true, discover returns
unavailable: [{name, reason}] for filtered-out members instead of omitting them, so a model stops
retrying something the operator switched off rather than concluding it never existed).

State the security posture explicitly in the spec rather than leaving it implicit: with nothing
hooking the visibility filter the shipped default is broader than the three vendor tools, which gate
on meta.mcp.public and would otherwise expose nothing. The counterweights are (a) the Integrations
screen already prevented every disabled category from registering at all, so a Toolset cannot see
them; (b) exposure is not authorization — every execute runs the target's own permission_callback and
nearly all bundled abilities require manage_options; (c) reaching a Toolset requires an authenticated
MCP session against a server where an operator explicitly added that tool. Net: a Toolset exposes
approximately what the authenticated user could already do in wp-admin, and
acrossai_toolset_member_visible plus the existing annotations.readonly / annotations.destructive
metadata is the one-callback answer for sites that want it narrower.

TESTS (PHPUnit, added to phpunit.xml.dist's explicit <file> list):
- One Toolset per declared category; the fallback creates one for a registered category with no
  subclass and none for a category that has one; a category disabled on the Integrations screen
  registers no Toolset; a slug collision is skipped rather than clobbering.
- All three actions; search, sub_group, limit, offset, total, returned, has_more; include_fields
  trims the response and always keeps name; batch info with a found/not_found mix; unknown action;
  missing ability on execute; an out-of-category ability rejected with ability_not_in_group; a
  category whose members are all filtered out returns an empty abilities array plus a message, not an
  error.
- Permission passthrough is the load-bearing suite: a target whose permission_callback returns false
  or WP_Error is denied THROUGH the Toolset with the error propagating from permission_callback;
  assert WP_Ability::execute() is the invocation path by spying on wp_before_execute_ability and
  wp_after_execute_ability; assert info is gated identically to execute; assert no Toolset dispatches
  to another Toolset.
- The visibility filter defaults to true, receives the four documented arguments, and a false return
  removes the member from all three contexts.
- Schemas are byte-stable (guards against silent manifest churn) and every action's real response
  validates against the declared output schema.
- Merge-blocking: every Toolset ships meta.mcp.public === false.

Definition of Done per Constitution VII: PHPCS zero errors and warnings, PHPStan level 8 zero errors,
unit tests for all new logic, no DRY violations, all classes prefixed, npm run validate-packages
passing."
```

---

## Notes for the spec

**Files expected**

| Path | Purpose |
|---|---|
| `includes/Abilities/Base_Toolset_Ability.php` | All shared logic — schemas, dispatch, resolution, search, paging, permissions |
| `includes/Abilities/<Folder>/Toolset.php` × 25 | Category constant, slug, hand-written label + description |
| `includes/Modules/Groups/AcrossAI_Toolset_Registrar.php` | Catalogue filter provider, protected-slugs callback, fallback for undeclared categories |
| `includes/Utilities/AcrossAI_Ability_Input_Normalizer.php` | Guarded wrapper over the adapter's argument normalizer |
| `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` | Instantiate the Toolset subclasses alongside the existing registrars |
| `includes/Main.php` | Loader wiring only, variable-first Boot Flow Rule |
| `phpunit.xml.dist` | New `<file>` entries |

**Folder → category → live count**

| Folder | Category | Count |
|---|---|---|
| Block | `…-block` | 79 |
| Elementor | `…-elementor` | 63 (conditional) |
| RankMath | `…-rank-math` | ~63 (conditional) |
| Content | `…-content` | 37 |
| FileManager | `…-file-manager` | 23 |
| Database | `…-database` | 18 |
| Users | `…-users` | 16 |
| Cron | `…-cron` | 16 |
| Comments | `…-comments` | 12 |
| Menus | `…-menus` | 12 |
| Settings | `…-settings` | 11 |
| Media | `…-media` | 11 |
| ContentSearch | `…-content-search` | 11 |
| Plugins | `…-plugins` | 10 |
| Taxonomies | `…-taxonomies` | 10 |
| Fonts | `…-fonts` | 8 |
| Themes | `…-themes` | 7 |
| Recovery | `…-recovery` | 7 |
| Options | `…-options` | 7 |
| Debugging | `…-debugging` | 7 |
| Cache | `…-cache` | 7 |
| SiteHealth | `…-site-health` | 6 |
| Core | `…-core` | 6 |
| AdminMenu | `…-admin-menu` | 5 |
| Widgets | `…-widgets` | 2 |

**Manual acceptance run** — on the transport plugin's MCP → Tools screen

1. After activation the "All abilities" pane lists the ~25 `toolset/*` entries.
2. **Remove** the three vendor tools, **Add** the ~25 Toolsets. The header moves from
   "3 of 327 abilities added as tools" to ~25.
3. `tools/list` shows ~25 `toolset-*` and no `mcp-adapter-*`.
4. `toolset-block {action:'discover', limit:5}` → 5 rows, `total: 79`, `has_more: true`. Repeat with
   `search`, `sub_group`, and `include_fields:['name']`.
5. `toolset-core {action:'info', ability:'core/get-wp-version'}` → schemas.
6. `toolset-core {action:'execute', ability:'core/get-wp-version', parameters:{}}` → the version.
   Proves `{}` normalization.
7. As a subscriber-level user, step 6 must 403 from the target's own `permission_callback` while
   step 4 still works.
8. Disable a category on the Integrations screen → its Toolset disappears from both the Tools pane
   and `tools/list`.
9. Activate Rank Math → its Toolset appears; deactivate → it goes.
10. `wp --debug` with the plugin active → no `_doing_it_wrong` notices about ability registration.

**Measure and record** `tools/list` payload bytes before and after as a success criterion. Roughly 25
Toolsets sharing a ~200-token schema is a real manifest cost; the thing it replaces is a
`discover-abilities` call that returns the entire catalogue.

**Known risks to carry into the spec**

1. Broader default exposure than the vendor tools — the security review's central claim, not an
   assumption.
2. ~25 near-identical subclass files; any logic that appears in one belongs on the base class.
3. A forgotten `Toolset.php` on a new folder is covered by the fallback, but silently — the debug
   notice is what keeps it from becoming permanent.
4. Existing category labels and descriptions are uneven and are now model-facing; audit all 25.
5. `validate_output()` now runs the union-typed `data` for every ability; sweep all categories once.
6. Activating an integration changes the tool manifest mid-session, and the operator must then add the
   new Toolset on the Tools screen — it does not attach to a server by itself. Document it; it is the
   predictable "why isn't Rank Math showing up" support question.
