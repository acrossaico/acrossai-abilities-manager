# Contract: `GET /acrossai/v1/abilities`

**Status**: existing route, extended. No breaking change to existing consumers.

## Request arguments

| Arg | Type | Change |
|---|---|---|
| `page`, `per_page`, `search`, `orderby`, `order`, `source`, `category`, `editable` | — | unchanged |
| `status` | `'' \| draft \| publish` | **now applied on the registry path**, not only `source=db` |
| **`tab_group`** | string, `sanitize_key`, default `''` | **NEW.** Restricts to one toolset |

`tab_group` resolves through `AcrossAI_Ability_Group::member_names()` and is passed to the query layer as
a `slugs` list. It is never resolved by re-reading `meta.acrossai.tab_group` in the controller — the group
class is the single seam and carries the protected-slug exclusion that keeps the 13 dispatchers out of
their own toolsets.

Unknown `tab_group` → empty collection, `X-WP-Total: 0`. Not an error.

Filtering is implemented in `AcrossAI_Ability_Registry_Query`, not the controller
(`AC-QUERY-LAYER-FILTERING`). The `slugs` check sits **after** the protected-slug skip and **before** the
source/search filters so it composes with them.

## Response

Existing shape plus one field:

```json
{ "ability_slug": "acrossai/get-post", "label": "Get Post", "tab_group": "content",
  "category": "acrossai-content", "source": "plugin", "status": "publish",
  "site_allowed": null, "show_in_rest": true, "show_in_mcp": true, "mcp_type": "tool",
  "has_override": false }
```

`tab_group` is `''` for DB-created abilities. Pagination remains header-based
(`X-WP-Total` / `X-WP-TotalPages`); `tab_group` paginates normally — no special-casing, because every
ability now registers and counts are stable.

## Toolset counts

Counts are served from a sibling route in this namespace: **`GET /acrossai/v1/abilities/toolsets`**,
returning `{ "<tab_group>": int }`. They are **not** localised into the admin page, and **not** a header on
the list response — a header would recompute counts on every page of every list request and is awkward to
cache independently.

This is load-bearing, not stylistic. `AcrossAI_Ability_Override_Processor` runs PATH B (unregistering
`site_allowed = false` abilities) on ordinary requests and PATH A (no pruning) on this plugin's own REST
namespace. A count computed during admin page render is a PATH B count and omits every blocked ability, so
a switched-off toolset would report `0` while its tab lists those abilities. Counts and rows must come from
the same read.

**Registration order is load-bearing.** `/abilities/toolsets` is a literal segment and the namespace
already registers `/abilities/(?P<slug>[^/]+)`. Per `BUG-REST-ROUTE-ORDER-LITERAL-BEFORE-WILDCARD`, the
literal MUST be registered **before** the wildcard or WordPress resolves `toolsets` as an ability slug and
the route returns 404 for a slug that does not exist. Add a test that pins the order.

`window.acrossaiAbilitiesManager` is otherwise unchanged, and notably **loses** the ~451-row definitions
payload this feature removes from the page entirely.
