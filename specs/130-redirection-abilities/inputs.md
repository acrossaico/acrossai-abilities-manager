# Feature 130 — Redirection: manage redirects and triage 404s a WordPress site can't otherwise expose

> Feature number is provisional — let `/speckit.specify` assign the next free one (129 is Query Monitor; 117–128 are already taken in code).

The Redirection plugin is installed and active on the dev site (v5.10.1). It is the de-facto WordPress tool for managing URL redirects and watching 404s: it keeps redirects, groups, a 404 log and a hit log in **its own database tables**, and exposes all of it **only through its own REST API** (`redirection/v1`). It registers **no WP Abilities of its own**. This feature adds the abilities that let an AI assistant create and manage redirects, and turn a site's 404s into redirects, without the owner opening the Redirection admin screen.

## Redirection keeps everything in its own tables, and that is the whole constraint

Redirects do not live in `wp_posts` or `wp_options` in any shape our generic abilities understand. They live in `wp_redirection_items`, grouped by `wp_redirection_groups`, with misses logged to `wp_redirection_404` and hits to `wp_redirection_logs`. The only supported way in or out is Redirection's REST controllers. So every ability here is a thin, guarded wrapper over that REST API — never a raw table write, which would skip Redirection's URL-matching, flag handling and cache flush and leave the redirect silently dead.

## Nothing we already ship manages a Redirection redirect

- `content/delete-post` already *notices* the problem: when a published post is force-deleted it returns a `suggested_redirect` target for the now-dead URL — but nothing in the plugin can act on it. This suite is what finally acts on it.
- Rank Math's ability suite (feature 069) manages **Rank Math's** redirects and 404 log, in Rank Math's storage. That is a different plugin with a different store; it cannot read or write a Redirection redirect. The two are siblings, not duplicates — a site runs one or the other.
- `database/*` can run a query we name, but it cannot create a redirect correctly: a redirect is not one row, it is a sanitized source URL, match flags, an action + HTTP code, a serialized action payload and a cache flush, all of which Redirection's model does and a raw INSERT does not.

So the thing worth building is the thing none of these do: **create, read, update and remove Redirection redirects, and read the 404 log that tells you which ones a site needs.**

## Scope — namespace `redirects/`, its own integration

Topic-based `redirects/` namespace (not a plugin-branded prefix, per house convention). Each ability degrades to a clear "Redirection is not active" message — never a fatal — when the plugin is missing or off, detected by class/function, never by the active-plugins option.

**Recommended core (6):**

1. **`redirects/create-redirect`** — the reason for the feature. **Input:** a source `url`; an action (`action_type`: `url` to redirect, with `action_code` 301/302/307/308 and a target; or `error` for 404/410; or `pass`/`random`/`nothing`); optional `match_type` (default `url`), `group_id` (defaults to the site's last-used group), `regex`, `title`. Returns the created redirect. This is what consumes `content/delete-post`'s `suggested_redirect`.
2. **`redirects/list-redirects`** — list / search / filter / paginate existing redirects: source, target, HTTP code, group, enabled state, hit count and last-access. Read-only.
3. **`redirects/update-redirect`** — change a redirect's target or code, enable or disable it, move it to another group, or edit its title. Write.
4. **`redirects/delete-redirects`** — delete, enable, disable or reset-hits on one or many redirects (Redirection's bulk actions). Destructive on delete; annotate accordingly and gate behind a confirmation.
5. **`redirects/list-404-errors`** — the 404 log: which URLs visitors hit that returned nothing, with counts and last-seen. This is the raw material for "which redirects does this site actually need". Read-only.
6. **`redirects/list-groups`** — the groups a redirect can belong to, each with its module (WordPress / Apache / Nginx). Needed to place a new redirect. Read-only.

**Optional, decide in `/speckit.specify` (2):**

7. **`redirects/get-settings`** — read Redirection's options: whether it auto-creates a redirect when a post slug changes (`monitor_post` / `auto_target`), redirect expiry, trailing-slash and https handling, and what it logs. Read-only. (A settings *write* is deliberately left out of v1 — the option set is broad and mostly one-time setup.)
8. **`redirects/list-hit-log`** — recent redirect hits: which redirects are firing and from where. Read-only, useful for confirming a new redirect works.

## The headline workflow this unlocks

"Find every URL that is 404ing and redirect it to the right page." `list-404-errors` surfaces the misses → the assistant proposes targets → `create-redirect` fixes each → `list-redirects` confirms. Plus the delete-a-post loop: `content/delete-post` hands back a `suggested_redirect`, and `create-redirect` is now there to accept it.

## Constraints — traps, not preferences

- **Redirection must be active.** Detect by its classes/REST namespace; degrade to a clear message, never a fatal, when it is missing or off.
- **Go through the REST API / models, never the tables.** A raw INSERT skips URL sanitizing, match flags, the action payload serialization and the cache flush, producing a redirect that is stored but never fires. Call Redirection's own controllers (or its model layer) so the redirect is created exactly as the admin screen would.
- **Capability is `manage_options`.** That is Redirection's own default (`Redirection_Capabilities::CAP_DEFAULT`, filterable via `redirection_role`); the write/delete abilities must require it too, and respect the per-area caps if the site has narrowed them.
- **A redirect is more than a target.** `action_type` + `action_code` must agree (a `url` action needs a 3xx code and a target; an `error` action needs 404/410 and no target). Validate the combination and return a stable `error_code` on mismatch rather than writing a broken redirect.
- **Regex and match types are power tools.** Support `regex` and the non-`url` match types (referrer, user-agent, cookie, HTTP header, IP, server, page, language, login, user-role) in the schema, but default to a plain `url` match so the common case stays one call.
- **Delete is destructive and bulk.** `delete-redirects` can remove many at once; annotate `destructive: true`, require an explicit confirmation, and never default to delete when enable/disable/reset was meant.
- **Read-only except create / update / delete.** `list-*` and `get-settings` are `readonly: true`.
- **Namespace `redirects/`**, its own integration entry and ability category; do not fold it into Rank Math or the debugging category.

## Reuse and suggestions

- Wire `content/delete-post` → suggest `redirects/create-redirect` (it already emits `suggested_redirect`).
- Wire `redirects/list-404-errors` → suggest `redirects/create-redirect` as the next step.
- Note Rank Math's redirect abilities (069) as the sibling for Rank-Math-run sites; this suite is for Redirection-run sites.

## Verification

- With Redirection active, `create-redirect` makes a 301 from `/old` to `/new`; hitting `/old` on the site returns a 301 to `/new`, and `list-redirects` shows it with the right code and group.
- `create-redirect` with an `error` action and code 410 makes `/gone` return 410.
- `list-404-errors` returns a logged 404 after a missing URL is requested; creating a redirect for it and re-requesting clears the miss.
- A `url` action with no target, or with a 404 code, is refused with a clear `error_code` and nothing is written.
- `delete-redirects` without a confirmation does not delete.
- With Redirection deactivated, every ability returns the "not active" message and no fatal.
- Behavioural and mutation-checked: deactivate Redirection and the first test must flip to the "not active" path; feed `create-redirect` a mismatched action/code and it must refuse.

## Out of scope

- Import and export (`/import`, `/export`): file upload, other-plugin import, and Apache/Nginx/CSV/JSON bundles. Powerful but file-heavy; a later feature.
- Creating or deleting groups, and editing Redirection's modules. Redirects go to the default/last group unless a `group_id` from `list-groups` is given.
- Writing Redirection's settings. v1 reads them only.
- Managing the hit log beyond reading it (no bulk-clear in v1).
- Anything the generic or Rank Math suites already cover.
