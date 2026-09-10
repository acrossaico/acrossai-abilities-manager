# Planning: Toolset Abilities (Feature 100)

Add one dispatcher ability per ability **family** — a "Toolset" — so an AI client browses the site's
~450 abilities one family at a time instead of receiving the entire catalogue in a single unfiltered
response.

> **Grouping key changed from category to family (2026-09-10).** This brief originally proposed one
> Toolset per ability *category* — 25 of them. Feature 101 (merged as `5cf50ec4`) introduced
> **families**: 13 task-shaped groups assigned per ability, now shown as the Integrations screen's
> tabs. Toolsets follow families. Beyond halving the tool count, this removes four requirements the
> category-based draft had to legislate around — see *Why families settle four requirements* below.

Today the only MCP-facing surface is the three vendor tools (`mcp-adapter/discover-abilities`,
`mcp-adapter/get-ability-info`, `mcp-adapter/execute-ability`). `discover-abilities` declares **no
`input_schema` at all** and returns every `mcp.public` ability in one response — no filter, no search,
no pagination. The structure that makes the Integrations screen navigable stops at the browser.

A Toolset carries the same three verbs, scoped to a single family:

```
toolset-content { "action": "discover" }                    → that family's abilities
toolset-content { "action": "discover", "card": "comments" } → just the Comments ones
toolset-content { "action": "info", "ability": "content/…" } → schemas + annotations
toolset-content { "action": "execute", "ability": "…",
                  "parameters": { … } }                      → runs it
```

This plugin registers abilities and nothing else. It creates no MCP server, touches no server's tool
registry, and uses no Reflection — consistent with `DEC-PASS-AS-TOOL-REMOVED`. Toolsets reach a client
because the transport plugin's Tools screen curates arbitrary ability slugs and builds its picker from
`wp_get_abilities()`; the operator removes the three vendor tools there and adds the Toolsets.

---

## The thirteen families

| Family | Abilities | Cards it draws on |
|---|---:|---|
| `content` | 71 | content 29, comments 12, content-search 11, taxonomies 10, media 9 |
| `appearance` | 64 | block design 35, menus 12, fonts 8, settings 7, widgets 2 |
| `elementor` | 63 | elementor *(only when active)* |
| `rank-math` | 62 | rank-math *(only when active)* |
| `blocks` | 52 | block authoring |
| `updates` | 23 | plugins 10, themes 7, core 6 |
| `files` | 23 | file-manager |
| `diagnostics` | 20 | debugging 7, recovery 7, site-health 6 |
| `configuration` | 19 | options 7, admin-menu 5, settings 4, media 2, cache 1 |
| `database` | 17 | database |
| `cron` | 16 | cron |
| `users` | 16 | users |
| `cache` | 7 | cache 6, database 1 |

Five cards contribute to two families each. That is why `card` is a listing filter (FR-015) rather
than a tool boundary — it recovers the finer granularity without spending a tool slot on it.

---

## Why families settle four requirements

The category-based draft needed separate rules for: not registering a Toolset for a disabled category;
honouring the opposite enable-defaults of ordinary versus integration cards; reading that inverted
default only through the one helper that owns it; and exempting a Toolset from the per-ability
selection its own category applied in "specific" mode.

All four existed because a Toolset **shared a category with the abilities it dispatched to**, so the
operator's setting for that category applied to the Toolset itself.

A family Toolset shares a category with nothing. It declares its own, and resolves members from what
is *registered*. A card switched off, or set to expose only some abilities, already prevents those
abilities from registering — so the Toolset inherits every setting without asking about any of them.
One requirement replaces four.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "100-toolset-abilities"

# 2. Specify
/speckit.specify "Add one Toolset dispatcher ability per ability FAMILY to AcrossAI Abilities Manager.
A Toolset is a single ability taking an `action` of discover | info | execute that dispatches to the
abilities in its own family, so an AI client works through ~450 abilities one family at a time
instead of receiving the whole catalogue in one response.

GROUPING KEY IS THE FAMILY — meta.acrossai.tab_group, established by Feature 101 and displayed as the
Integrations screen's tabs. Thirteen families with their live counts:
content 71, appearance 64, elementor 63, rank-math 62, blocks 52, updates 23, files 23,
diagnostics 20, configuration 19, database 17, cron 16, users 16, cache 7.

Do NOT group by ability category. There are 25 categories, and 25 always-loaded tools sits past the
threshold where tool-selection accuracy measurably degrades — Anthropic documents degradation past
30-50 tools, and production telemetry places Haiku below 90% between 10 and 15 and Sonnet below 90%
at 30. Thirteen is comfortably inside both. Feature 101 recorded thirteen as a deliberate ceiling in
DEC-ABILITY-FAMILY-TAXONOMY; a fourteenth family is a decision, not a reflex.

A family may draw on several cards (categories) and a card may contribute to two families. Five do.
The card is therefore a LISTING FILTER, not a tool boundary — action=discover must accept a `card`
parameter so a client can ask for 'just the comments ones' inside the 71-ability content family.
That recovers per-category granularity without spending a tool slot on each.

EVERY TOOLSET MUST DECLARE ITS OWN CATEGORY, distinct from any category holding real abilities.
WP_Abilities_Registry::register() refuses an ability whose category is not registered — it calls
_doing_it_wrong() and returns null, which is exactly how Feature 061's Debugging abilities silently
never existed for the feature's whole life. Register one dedicated category for all Toolsets on
wp_abilities_api_categories_init, and wire its registrar into
AcrossAI_Core_Abilities_Bootstrap::register_category_callbacks() — a registrar that is written but
never wired fails completely and silently (see BUG-UNWIRED-CATEGORY-REGISTRAR).

Giving Toolsets their own category is what decouples them from the operator settings that apply to
the abilities they dispatch to, and it is why this design needs ONE requirement where the earlier
category-based draft needed four.

RESOLVE MEMBERS FROM WHAT IS REGISTERED, LAZILY, PER CALL — never fixed at boot. On each call walk
wp_get_abilities() and keep abilities where (1) meta.acrossai.tab_group matches this Toolset's family,
(2) meta.mcp.type is 'tool', (3) the ability is not itself a Toolset and is not in
AcrossAI_Protected_Abilities::get_protected_slugs(), and (4) it passes the visibility filter. Memoize
per request. Resolving from registration means every operator setting is inherited rather than
re-implemented: a disabled card's abilities never registered, a 'specific' card's unticked abilities
never registered, and an untouched integration card's abilities never registered because integration
cards default to OFF. The Toolset asks about none of it.

Resolving lazily also removes any wp_abilities_api_init ordering window and behaves identically under
WP-CLI, where hook ordering differs.

CACHE THE RESOLVED MEMBER LIST IN A TRANSIENT, following the existing pattern in
AcrossAI_Ability_Override_Processor: a keyed transient with a 12-hour TTL, a static bust_cache() and a
Loader-compatible bust_cache_hook() instance wrapper so every add_action traces back to Main.php.

CACHE ONLY REGISTRATION-DERIVED FACTS: which abilities exist, each one's family and card, and whether
it is a callable tool. NEVER cache the visibility-filter result or any permission outcome. The
acrossai_toolset_member_visible filter is explicitly intended to let a policy narrow membership per
role or per connection, so caching its result would serve one caller's view of the catalogue to
another — a disclosure bug, not a stale cache. Resolve from cache, then apply visibility and
permissions fresh on every request.

Key the transient per family and stamp it with a schema version; treat a version mismatch as a miss so
an upgrade that changes the stored shape can never read back an entry written by the previous version.

BUST ON EVERY ONE OF THESE:
  - activation and deactivation of this plugin (Activator / Deactivator);
  - activated_plugin and deactivated_plugin for ANY plugin — Elementor and Rank Math abilities appear
    and disappear with their host, so a foreign plugin toggling changes this plugin's answer;
  - upgrader_process_complete (plugin, theme or core update);
  - the Integrations settings being saved — see the gap below;
  - acrossai_abilities_after_create / _after_update / _after_delete, the existing DB-ability lifecycle
    actions;
  - whatever busts acrossai_ability_overrides_cache, since an override changes what registers;
  - switch_blog on multisite.
Publish an action (e.g. acrossai_toolset_flush_cache) so a companion plugin can discard it without
reaching into internals.

GAP TO CLOSE: saving the Integrations settings currently fires NOTHING. Verified —
AcrossAI_Ability_Library_Config_Controller has do_action only for the integration-toggle DENIED case,
nothing on a successful save. The card on/off state, the all/specific mode and the per-ability
selection all change which abilities register, so this is the single most important bust trigger and
there is no hook for it. Add one (e.g. acrossai_library_config_saved, passing the saved config) as part
of this feature, and note that it is generally useful beyond the cache.

Correctness must not depend on the cache being warm: a miss, an expiry and a flush must all behave
identically to a hit. Test with caching disabled as well as enabled.

STRUCTURE — EXACTLY ONE ABSTRACT CLASS, extended by thirteen four-line subclasses.
Add includes/Abilities/Toolset/Base_Toolset_Ability.php extending the existing abstract
includes/Modules/Library/Ability_Definition.php (single abstract method: ability(): array).

That base class owns EVERYTHING shared: input and output schemas, three-action dispatch, member
resolution, search, card and sub-group filtering, pagination, all three permission layers, the
registration of the Toolsets' shared ability category, the published catalogue filter, the
protected-slugs callback, and the fallback that covers a family with no declared subclass. Do NOT
split these across separate registrar or helper classes — one place to look, one place to change
(Constitution VI).

Each family gets a subclass declaring ONLY four things: its family key, its slug, its hand-written
label and its hand-written description. A subclass MUST NOT carry behaviour. If one needs anything
beyond those four declarations, the base class is missing something — add it there. Adding a family
must mean adding one file with four methods and nothing else.

Descriptions are hand-written because the description is the only thing a model reads when choosing a
tool; generated text would be generic exactly where precision matters.

FALLBACK: after the declared subclasses register, a registrar walks the live families and auto-creates
a generic Toolset for any family with no declared subclass, so an integration activated later is never
silently missing a tool. Emit a debug notice naming each family on the fallback.

NAMING: slug is 'toolset/' + the family key — toolset/content, toolset/appearance, toolset/rank-math.
The vendor sanitizer maps / to - so clients see toolset-content; longest is toolset-configuration at
21 characters against a 128 limit, so no mcp_adapter_tool_name filter is needed. The 'toolset-' prefix
is Anthropic's own recommended pattern: 'prefix by service or resource so one search matches the whole
group'. Guard collisions with wp_has_ability() before registering — skip and fire an observability
action rather than clobber a third party's slug. Land a DECISIONS.md entry for the 'toolset/'
namespace in the same PR, amending the topic-namespace convention: topic namespaces name the resource
an ability acts on, while 'toolset/' is reserved for abilities acting on the ability catalogue itself.

INPUT SCHEMA, identical for every Toolset, defined once on the base class:
  action         enum discover|info|execute   REQUIRED
  search         string maxLength 100          discover — substring over name, label, description
  card           string maxLength 100          discover — narrow to one contributing category
  sub_group      string maxLength 64           discover — narrow by meta.acrossai.sub_group
  limit          integer 1..200 default 50     discover
  offset         integer >= 0 default 0        discover
  include_fields array of string               discover/info — trim the response shape
  ability        string maxLength 255          info or execute
  abilities      array of string maxItems 20   info batch; takes precedence over `ability`
  parameters     object                        execute — the target ability's own input
  additionalProperties false

`action` is REQUIRED and never defaulted: an optional action means a model sending
{ability, parameters} intending execute silently receives a listing with no signal it did the wrong
thing. Batch info uses a separate `abilities` array rather than a union type on `ability`, because
clients and JSON Schema validators handle type: [string, array] inconsistently. Pagination, search and
the card filter are mandatory — content is 71 abilities across five cards, so an unpaginated dump
reproduces the problem being fixed. include_fields ignores unknown keys rather than erroring, and
always returns `name`.

The action vocabulary is discover | info | execute to match the vendor tools' own naming
(get-ability-info) and the 'discover' | 'get_info' | 'execute' context values already used by the
sibling transport plugin's exposure filter.

DISCOVER returns name, label, description, card and sub_group ONLY. No input_schema, no output_schema,
no annotations — those are the info surface. Explicitly no suggested_abilities and no suggested_plugins
per DEC-ABILITY-SUGGESTED-ABILITIES-CONTRACT. Response carries total, returned, offset and has_more.

OUTPUT SCHEMA is one permissive object keyed by action rather than oneOf: action, family, success,
error, error_code, message, abilities[] (with input_schema/output_schema/annotations present only for
info), total, returned, offset, has_more, not_found[], and `data` typed as the same union
ExecuteAbilityAbility uses (object, array, string, number, integer, boolean, null) because it must
absorb ~450 output shapes and WordPress runs validate_output() on it. Required: action, success.

Soft failures return success:false plus a machine-readable error_code — ability_not_found,
ability_not_in_family, ability_not_visible, invalid_action, missing_ability — so the model can
self-correct. Permission denial is the deliberate exception: it propagates as a WP_Error from
permission_callback so it is a real 403.

PERMISSIONS — three layers, none skippable:
(1) The Toolset's own permission_callback is is_user_logged_in() plus a capability filterable via
    acrossai_toolset_capability, defaulting to 'read'. Deliberately low: this gates a listing, and
    hardcoding manage_options would lock out a legitimately-scoped editor while protecting nothing.
(2) For action=execute the permission_callback ALSO resolves the target, normalizes the parameters and
    runs the target's own check_permissions(), propagating any WP_Error verbatim — a real 403 raised
    before execute_callback runs.
(3) execute_callback invokes the target via WP_Ability::execute(). NEVER do_execute(), never the raw
    callback. WP_Ability::execute() re-runs normalize_input, validate_input, check_permissions, the
    callback and validate_output, and fires wp_pre_execute_ability, wp_ability_invoked,
    wp_before_execute_ability and wp_after_execute_ability — which is what keeps
    AcrossAI_Ability_Override_Processor, access control and logging in the loop. The double permission
    evaluation is intentional; comment the call site so it is not optimised away.

Argument normalization is mandatory — MCP clients send {} for no-argument tools. Add
includes/Utilities/AcrossAI_Ability_Input_Normalizer.php wrapping AbilityArgumentNormalizer::normalize()
behind a class_exists() guard with a local fallback, so this plugin gains no hard dependency on the
adapter package.

ANTI-BYPASS INVARIANTS, each a testable functional requirement:
- MUST NOT read get_execute_callback() or invoke a target callable directly.
- MUST NOT forward its own \$input to the target — only \$input['parameters'].
- action=info MUST apply the same membership and visibility gate as execute. Input schemas name
  parameters and constraints, so an ungated info is a read-side information-disclosure bypass. This is
  the single most likely requirement to be forgotten.
- MUST NOT dispatch to another Toolset. No recursion, no cross-family hop.
- Every Toolset MUST ship meta.mcp.public = false, so the vendor default server's discover-abilities
  output does not silently grow by 13 entries. Merge-blocking test. This does not impede the transport
  plugin's Tools screen, which builds its picker from wp_get_abilities() and ignores that flag.
- Toolset slugs are added to the protected list by hooking
  acrossai_abilities_manager_protected_slugs, not by editing the hardcoded default — the slug set is
  dynamic.
- Toolsets MUST NOT be published into the definition catalogue that builds the Integrations screen's
  cards, so no Toolset row appears inside any card.

EXPOSURE: by default a Toolset sees every registered, tool-typed ability in its family. Publish
apply_filters( 'acrossai_toolset_member_visible', true, WP_Ability \$ability, string \$family,
string \$context ) where context is 'discover' | 'info' | 'execute'. Nothing in this plugin hooks it;
it ships as the extension point so a per-server or per-role policy can narrow membership later, and
its four-argument shape deliberately mirrors the sibling transport plugin's existing exposure filter.
Also publish acrossai_toolset_abilities (family, label, slug, member count) and
acrossai_toolset_report_unavailable (default false; when true, discover returns
unavailable: [{name, reason}] instead of omitting filtered-out members).

State the security posture explicitly rather than leaving it implicit: with nothing hooking the
visibility filter the shipped default is broader than the three vendor tools, which gate on
meta.mcp.public and would otherwise expose nothing. The counterweights are (a) the Integrations screen
already prevented every disabled card from registering; (b) exposure is not authorization — every
execute runs the target's own permission_callback and nearly all bundled abilities require
manage_options; (c) reaching a Toolset requires an authenticated MCP session against a server where an
operator explicitly added that tool. Net: a Toolset exposes approximately what the authenticated user
could already do in wp-admin.

TESTS (PHPUnit, added to phpunit.xml.dist's explicit <file> list):
- One Toolset per live family; none for a family with no registered abilities; the fallback creates one
  for a family with no subclass and none for a family that has one; a family whose every contributing
  card is switched off registers no Toolset; slug collision is skipped rather than clobbering.
- All three actions; search, card, sub_group, limit, offset, total, returned, has_more; include_fields
  trims and always keeps name; batch info with a found/not_found mix; unknown action; missing ability
  on execute; an out-of-family ability rejected with ability_not_in_family; a family whose members are
  all filtered out returns an empty array plus a message, not an error.
- A card contributing to two families yields each of its abilities to exactly one Toolset.
- Permission passthrough is the load-bearing suite: a target whose permission_callback returns false or
  WP_Error is denied THROUGH the Toolset with the error propagating from permission_callback; assert
  WP_Ability::execute() is the invocation path by spying on wp_before_execute_ability and
  wp_after_execute_ability; assert info is gated identically to execute; assert no Toolset dispatches
  to another Toolset.
- The visibility filter defaults to true, receives the four documented arguments, and a false return
  removes the member from all three contexts.
- Schemas are byte-stable and every action's real response validates against the declared output schema.
- Merge-blocking: every Toolset ships meta.mcp.public === false.
- Every test that scans source must fail when its scan finds nothing, rather than passing against an
  empty set — a coverage test in Feature 101 passed vacuously at 24 of 25 for exactly this reason.

Definition of Done per Constitution VII: PHPCS zero errors and warnings, PHPStan level 8 zero errors,
unit tests for all new logic, no DRY violations, all classes prefixed, npm run validate-packages
passing."
```

---

## Notes for the spec

**Files expected**

**One abstract class holds everything. A family class holds four declarations and nothing else.**

| Path | Purpose |
|---|---|
| `includes/Abilities/Toolset/Base_Toolset_Ability.php` | **The single abstract class.** Schemas, three-action dispatch, member resolution, search, card and sub-group filters, pagination, all three permission layers, the shared category registration, the published catalogue, and the fallback for an undeclared family. Everything. |
| `includes/Abilities/Toolset/<Family>.php` × 13 | Family key, slug, label, description. Four declarations, no behaviour. |
| `includes/Utilities/AcrossAI_Ability_Input_Normalizer.php` | Guarded wrapper over the adapter's argument normalizer (shared utility, not Toolset-specific) |
| `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Config_Controller.php` | Fire a `acrossai_library_config_saved` action on successful save — the hook the cache needs and that does not exist today |
| `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` | Instantiate the 13 subclasses; wire the base class's category registrar |
| `includes/Main.php` | Loader wiring only, variable-first Boot Flow Rule |
| `phpunit.xml.dist` | New `<file>` entries |

A family subclass should look like this and no larger:

```php
final class Content extends Base_Toolset_Ability {
    protected function family(): string { return 'content'; }
    protected function slug(): string   { return 'toolset/content'; }
    protected function toolset_label(): string { … }
    protected function toolset_description(): string { … }   // hand-written, model-facing
}
```

**Adding a family means adding one file with four methods.** If anything else is needed, the shared
class is missing something — fix it there, not in the subclass. The category registration, the
catalogue filter, the protected-slugs callback and the fallback all live on the base rather than in
separate components, so there is exactly one place to look and exactly one place to change.

**Manual acceptance run** — on the transport plugin's MCP → Tools screen

1. After activation the "All abilities" pane lists the 13 `toolset/*` entries.
2. **Remove** the three vendor tools, **Add** the 13 Toolsets.
3. `tools/list` shows 13 `toolset-*` and no `mcp-adapter-*`.
4. `toolset-content {action:'discover', limit:5}` → 5 rows, `total: 71`, `has_more: true`. Repeat with
   `search`, with `card:'comments'` (→ 12), and with `include_fields:['name']`.
5. `toolset-updates {action:'info', ability:'core/get-wp-version'}` → schemas.
6. `toolset-updates {action:'execute', ability:'core/get-wp-version', parameters:{}}` → the version.
7. As a subscriber-level user, step 6 must 403 from the target's own `permission_callback` while step 4
   still works.
8. Switch a card off on the Integrations screen → its abilities vanish from the Toolset that drew on
   them, and the Toolset itself survives if any other card still contributes.
9. Switch every card in a family off → that family's Toolset disappears from the pane.
10. Activate Rank Math → its Toolset appears; deactivate → it goes.
11. `wp --debug` → no `_doing_it_wrong` notices about ability registration.
12. Call `discover` twice and confirm the second is served from cache; then switch a card off on the
    Integrations screen and confirm the next call reflects it immediately, not after the TTL.
13. Activate an unrelated plugin that registers abilities, and confirm the next `discover` sees them.
14. As two users with different roles, under an extension that narrows visibility by role, confirm each
    receives their own listing — the cache must not leak one to the other.
15. Disable object caching / force the transient to miss, and confirm every listing is still correct.

**Measure and record** `tools/list` payload bytes before and after. Thirteen Toolsets sharing a
~200-token schema is roughly 2–4k tokens; what it replaces is a `discover-abilities` call returning the
entire catalogue.

**Known risks to carry into the spec**

1. Broader default exposure than the vendor tools — the security review's central claim, not an
   assumption.
2. Thirteen near-identical subclass files; any logic appearing in one belongs on the base class.
3. A family with no declared subclass is covered by the fallback, but silently — the debug notice is
   what stops that becoming permanent.
4. `validate_output()` now runs the union-typed `data` for every ability; sweep all families once.
5. Activating an integration changes the tool manifest mid-session, and the operator must then add the
   new Toolset on the Tools screen — it does not attach to a server by itself.
6. **Cache staleness is the failure mode, not cache cost.** Resolving a family filters a few hundred
   already-loaded objects — cheap. The transient exists to avoid repeating that per request, and its
   real risk is answering from a list that no longer matches what is registered. That is why the bust
   triggers are enumerated rather than left to expiry, and why the feature must be correct with the
   cache absent.
7. **Caching a visibility decision would be a disclosure bug.** The visibility filter exists to let a
   policy narrow membership per role or connection. Anything cached must be caller-independent by
   construction. This is the single easiest mistake to make here, because the obvious implementation —
   cache the finished listing — is the wrong one.
8. **The transport plugin's call-time gate refuses every curated tool that is not one of the three
   hardcoded generic ones**, because it compares the sanitised tool name a client sends against raw
   ability slugs. Toolsets will be refused by it until both sides are normalised. Out of scope here,
   but scheduling this feature without scheduling that fix ships a feature that does not work.
