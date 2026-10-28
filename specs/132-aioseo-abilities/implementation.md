# Feature 132 — what shipped

Implemented in 0.0.44 against **AIOSEO 5.0.3 free**, installed and active locally. The research that
set the scope is in `inputs.md`; this records what was built and the host behaviours that shaped it.

## Twelve abilities under `aioseo/`

| Ability | Why it exists |
|---|---|
| `get-settings-panel` | AIOSEO's `aioseo-settings/get` is read-only and documents its tree as opaque. This returns one capability-scoped panel with every field's type, default and current value. |
| `update-settings-panel` | AIOSEO ships no settings writer at all. |
| `get-object-settings` | The per-post-type / per-taxonomy template layer, in AIOSEO's second option store. Nothing of theirs reaches it. |
| `update-object-settings` | Same, writing. |
| `get-sitemap-health` | AIOSEO ships no sitemap ability; `aioseo-audit/site-get` reports only an on/off flag. |
| `regenerate-sitemap` | Static files do not reflect a settings change until rebuilt. |
| `get-status` | Edition, addons, and which panels this caller may write — AIOSEO keeps its own role matrix. |
| `list-backups` / `create-backup` / `manage-backup` | Makes a bulk settings change reversible in one call. |
| `dismiss-notification` | AIOSEO can raise and list notifications but not clear one. |
| `bulk-update-post-seo` | AIOSEO's `aioseo-posts/seo-data-update` takes one post. |

Nothing duplicates AIOSEO's thirteen, and `Test_Aioseo_Suite_Contract` asserts that by slug.

## Host behaviours that shaped the code

**1. `sanitizeAndSave()` is not a partial update — it deletes.** Both option stores finish with
`arrayIntersectRecursive( $dbOptions, $replacement, 'value' )` (`Options.php:699`,
`DynamicOptions.php:153`) under a comment saying they must "delete any individual keys that were
unset". Passing the three fields a caller named would erase every other AIOSEO setting on the site
and report success. AIOSEO's own REST route is safe only because its Vue app always posts the whole
tree. `Settings_Writer` therefore reads the full tree, merges, and passes the whole thing back —
`Test_Aioseo_Settings_Writer` pins that merge, including that a list value replaces rather than
appends (`array_merge_recursive`) or merges by index (`array_replace_recursive`).

**2. The field specification is AIOSEO's own.** `Options::getDefaults()` is public and gives every
leaf a `type` of boolean/string/number/array/html plus `default`, `localized`, `preserveHtml`. The
registry derives from it rather than hand-maintaining specs the way the Rank Math suite must, so it
cannot drift from the plugin. A node is a leaf when it carries a **string** `type` — the string test
is what keeps a group holding a child field named `type` from collapsing into one bogus field.
Verified: 302 leaves, 79 groups, no group with a `type` child.

**3. `aioseo()->sitemap->type` is only set during a sitemap request.** Outside one,
`includedPostTypes()` dereferences null, emits "Attempt to read property on null" warnings and
returns null — which an ability would report as "no post types included". `Sitemap_Repository` sets
`type` around every helper call and restores it.

**4. `Backup::delete()` compares strictly.** `$backup === $backupTime` at `Backup.php:72`, against an
index of integers from `time()`. Handing it a string deletes the backup's option row but leaves its
entry in the index — a listing showing a backup that no longer exists, and a restore that would read
null. Found by this suite's own live test, which reported `success: true, remaining: 1` after a
delete. `Status_Repository::resolve_backup_key()` resolves a caller's string to the stored native
value and every call into AIOSEO's utility uses that.

**5. Post writes go through AIOSEO's service, not its model.** `PostSeoService::updateSeoData()` is
public and carries the per-post `current_user_can( 'edit_post', $id )` check plus patch semantics. A
bulk editor that mapped fields itself and called `Models\Post::savePost()` would drop that check and
hand any caller past the floor a write to every post on the site.

## Cross-checked against Easy MCP AI 2.3.0

Worth doing because it is the other plugin on this site that reaches AIOSEO, and it turned up a bug
in this suite.

**Its AIOSEO coverage is three tools** — `get-post-seo`, `update-post-seo`, `get-breadcrumb` — against
eight for Yoast and seven for SEOPress. It has **no AIOSEO settings tool at all**, which is
independent confirmation that the settings layer is the real gap rather than an oversight on
AIOSEO's part.

**It writes through the WP REST API**, and so gates writes on AIOSEO Plus plus the `aioseo-rest-api`
addon for AIOSEO < 4.9.8, returning `plan_limitation` otherwise. This suite writes through
`PostSeoService` in-process, so it works on **free AIOSEO with no addon** — a better position, not a
worse one, and unchanged by the version floor.

**It caught a real bug here.** Its `update-post-seo` force-sets `default => false` whenever a robots
flag is present and `default` was not given. That is not defensive padding: a post ignores every
per-post robots directive while its `robots_default` flag is on — `Meta\Robots.php:211` returns the
post type's global values and never reads the per-post columns. AIOSEO's service maps `use_default`
but never clears it, so `robots: { noindex: true }` stored `robots_noindex = true` beside
`robots_default = true` and the page went on serving the defaults. Reproduced on AIOSEO 5.0.3 with
both columns true after the write. **AIOSEO's own `aioseo-posts/seo-data-update` has the same hole.**

`Post_Seo_Repository::reconcile_robots()` now clears the switch and reports it in `notes` — Easy MCP
AI does it silently, and a write that quietly did more than it was asked is the harder thing to debug.
An explicit `use_default: true` alongside flags is honoured with the contradiction named. The same
pass validates robots sub-keys against the eight the service actually maps, so `maxSnippet` is
refused instead of reporting success and storing nothing.

**Our annotations behave correctly in its consumer.** Easy MCP AI auto-enables an ability only when
`annotations['destructive'] === false` is explicitly present. Run against this suite: 11 auto-enabled,
`aioseo/manage-backup` withheld — exactly the intended split, and it only works because the base
class emits all three annotation keys on every ability.

## Namespace

`aioseo/`, assembled once in `Base_Aioseo_Ability`. `seo/` was unavailable — this plugin's 58 Yoast
abilities hold it and the two SEO plugins can run together. AIOSEO's own five namespaces are
`aioseo-<area>/`, so the bare prefix is off-pattern for them. **The standing rule still applies: if
AIOSEO ever claims an `aioseo/*` name of ours, ours moves or retires rather than shadowing** — the
rule 0.0.44 established when Rank Math took `rank-math/get-settings`.

## Verified live

Every ability executed against the running install as an administrator: 12 registered with zero
collisions against AIOSEO's 13; a two-field breadcrumbs write applied and read back with the rest of
the tree intact; a template write to `post-type:post`; sitemap health returning real post types and
taxonomies (proving the `type` fix); backup create / list / delete; a batch post write reporting
1 updated and 1 `not_found` from a two-id list. Error paths confirmed: `unknown_field`,
`unknown_panel`, `unknown_object`, `panel_not_writable`, `invalid_field_value` for a non-numeric
number and for a list given to a boolean, and `confirmation_required`. All test writes were reverted
through the suite itself.
