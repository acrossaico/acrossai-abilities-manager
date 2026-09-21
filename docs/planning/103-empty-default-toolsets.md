# Planning: A Default Toolset Is a Tool Even When It Holds Nothing (Feature 103)

Register every server-type-default Toolset whether or not its group currently has members, and tell
callers which listings can change under them. Per-plugin Toolsets keep their existing behaviour.

---

## In plain English

When an AI assistant connects to a site it is handed a list of tools. That list is printed once, at
the moment it connects, and cannot be reissued.

Until now a toolset that happened to contain nothing was left off that list. So `toolset/other` — the
drawer for abilities belonging to no group — was missing from a demo site, because every ability there
already had a home. The site's own Tools tab listed **14**; the assistant was served **13**.
Reconnecting did not help, and never could: the toolset was empty at that moment too.

Two of these toolsets are also **dynamic**. Integrations and Other fill and empty as plugins and themes
are activated. An assistant that called `discover` once and kept the answer is wrong from the next
activation onwards, and nothing tells it. So alongside registering them, we mark those listings as
perishable.

## The problem, precisely

`Base_Toolset_Ability::register()` bailed before `wp_register_ability()` whenever the group had no
members:

```php
if ( ! $this->has_any_member() ) {
    return;
}
```

`has_any_member()` is `private`, so no subclass could exempt itself.

For a **per-plugin** Toolset that is correct. A `toolset/woocommerce` on a site without WooCommerce
advertises a subject area that does not exist, and `toolset/integrations` reaches it the moment the
plugin arrives. Nothing is lost by leaving it out until then.

For a **default** it is wrong, and wrong permanently. The default set is what every server of this type
serves. An MCP client caches `tools/list` at connect time; the adapter advertises
`tools.listChanged: false`, and issue #129 measured a client ignoring the notification even when given
one. A default absent because it happened to hold nothing that day is absent for the life of that
connection — including after the plugin that would have filled it is installed.

### The sharper half

`Toolset\Integrations` inherited the same bail. Its own docblock promised:

> This Toolset is always in the default set, so that client always has it.

True of the server-type default set (`declare_server_type_tool()`), false of registration. Its
membership comes only from non-default Toolsets via `acrossai_toolset_integration_groups`, and today
that is non-empty solely because the UpdraftPlus and All-in-One abilities register unconditionally.
Block those two families and the site's only route to a plugin installed after a client connected
disappears — silently, and precisely for the caller least able to notice.

`Toolset\Guide` was never affected: it is deliberately not a Toolset and its `register()` has no
membership check. Of the two "always present" escape hatches, only one actually was.

## What changes

One line, in `Base_Toolset_Ability::register()`:

```php
if ( ! $this->has_any_member() && ! $this->is_server_type_default() ) {
    return;
}
```

`is_server_type_default()` is safe to ask here because it answers from the group name alone and never
consults the ability registry — the rule that exists to prevent the ordering bug recorded on
`declare_server_type_tool()`, where a registry-dependent gate dropped every Toolset on REST requests.

Nothing else needed editing, because the rule reads declarations that were already there:

| Always registers now | Still suppresses when empty |
|---|---|
| content, blocks, appearance, configuration, users, updates, cron, cache, database, files, diagnostics | `UpdraftPlus`, `All_In_One`, `Elementor`, `Rank_Math` |
| `Integrations` — no override, so the base `true` | every generated `Integration_Toolset` |
| `other` — `Integration_Toolset` returns `parent::` for the catch-all alone | |

Thirteen plus `Guide` is fourteen: exactly `acrossai-mcp-manager`'s `Registrar::TOOL_SLUGS`. The two
counts can no longer disagree.

An empty Toolset is not a dead end. `do_discover()` already answered one with a message rather than an
error; that path is now reachable.

## Saying which listings move

A tool DESCRIPTION is cached when the client connects, so it cannot carry a fact about the present
moment. A discover PAYLOAD is rebuilt on every call, which makes it the only channel that reaches a
model mid-session.

So `Base_Toolset_Ability` gains `is_volatile()`, default `false`, overridden to `true` by
`Toolset\Integrations` and by `Integration_Toolset` — the set whose membership tracks plugin and theme
activation. Every `discover` return from a volatile Toolset carries:

```php
'volatile' => true,
'note'     => 'This listing reflects the plugins and themes active right now, and changes when one is
                activated or deactivated. Call discover again rather than reusing an earlier result.'
```

The empty-state message changes too. "No abilities in this group are currently available to you." reads
as settled, and tells a caller to stop asking — the wrong lesson when installing a plugin is what fills
the group. A volatile empty listing says so instead.

The descriptions gain a shorter version of the same sentence. A description that is stale about
*contents* is harmless; one that is stale about *volatility* is not, because volatility is a property of
the toolset rather than of this moment.

Deliberately **not** using `tools/listChanged` — see #129.

## Keeping the guide honest

`Guide::toolsets()` iterates `AcrossAI_Ability_Group::counts()`, which is built by walking abilities and
therefore cannot emit a zero: a group with no members has no key there at all. After this change an
empty default would be in the caller's tool list but absent from the guide that claims to name every
toolset.

So the guide now unions in registered-but-empty dispatchers with `abilities: 0`, keyed on what is
actually registered rather than on what the site knows about — which preserves the original guarantee
that it never names a toolset with no dispatcher behind it.

`Guide::special()` described the catch-all only when it had members, which is the opposite of when a
caller most needs to know what it is for. It is now described whenever it is registered. The guard
stays for the one case that still leaves it out: another plugin claiming the slug first.

## What this does not touch

**`acrossai-mcp-manager` needs no change.** `ServerTypes::registered_only()` correctly strips a curated
row whose ability does not exist, the Tools tab pane is unfiltered on purpose so curated picks
round-trip on save, and `src/js/tools.js` already derives and reports the gap. Those all stay; they
simply stop having anything to report on a stock site. `ServerCreateTypeTest` asserts that divergence
with a genuinely unregistered slug, which is unaffected.

**The override-ordering asymmetry stays.** Toolsets register at `wp_abilities_api_init` P20 while
`AcrossAI_Ability_Override_Processor` unregisters blocked abilities at P100001, and
`AcrossAI_Ability_Group::$memo` is never flushed in production, so within one request the registration
layer and the counts layer can disagree about whether a group is empty. Pre-existing, unchanged, and
worth its own feature.

**Per-plugin Toolsets are not registered unconditionally.** That would undo the stable default set this
builds on.

## Relationship to the stable-tool-menu work

`acrossai-mcp-manager`'s Feature 092 argued that the menu must stop changing when plugins are installed.
This is the same thesis applied to the tools that exist precisely to survive a menu that is already
out of date: an escape hatch that can itself go missing is not an escape hatch.

## Tests

`tests/phpunit/abilities/Toolset/Test_Toolset_Empty_Registration.php` — both halves of the rule, the
collision guard still outranking the new exemption, `toolset/integrations` and `toolset/other`
surviving emptiness, and the volatility markers.

`tests/phpunit/abilities/Toolset/Test_Toolset_Guide.php` — new; the guide had no coverage at all. Pins
that it names a registered-but-empty default with a zero count, still refuses to name a group with no
dispatcher, and warns on both special entries.

`Test_Toolset_Permissions::test_unregistered_toolset_still_contributes()` now pins the fixture
non-default, which is the only combination that still bails, and asserts the precondition it had only
been asserting in a comment.

Note that `phpunit.xml.dist` lists test files explicitly — a file not listed silently does not run.
Both new files are registered under `library-unit`.

## Two bugs the unit tests did not catch

Both were found by deploying to a live site and calling the tools, and both are now pinned.

**`toolset/` is a shared namespace.** The first cut of `Guide::empty_groups()` matched on the slug
prefix, which swept up `toolset/setup-required` — registered by the transport, served only while this
add-on is missing, and stripped from every healthy server. The guide listed it as an empty default and
told a model to call a tool it will never be given. Membership is now decided by
`meta.acrossai.toolset`, the marker `args()` puts on every dispatcher and nothing else puts anywhere;
the group tagger already relied on it for the same reason.

**The output schema is `additionalProperties: false`.** Adding `volatile` and `note` to the discover
response without declaring them did not degrade — WordPress rejected the entire response, and the
caller got `volatile is not a valid property of Object` in place of its listing. The schema's own
comment warns about exactly this; the keys are now declared.

The second is the more interesting gap: the unit harness does not validate a response against its
ability's schema, so no test could have failed. `test_every_discover_key_is_declared_in_the_output_schema`
closes it by asserting the contract directly, across all four discover shapes, rather than key by key.
