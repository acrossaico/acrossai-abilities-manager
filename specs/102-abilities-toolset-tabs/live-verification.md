# T085 — quickstart walk, and what it found

Walked on `wordpress-7-0.local` on 2026-09-12, after Phase 7. Recorded honestly: several sections are
**not verifiable on this site any more**, and saying so is more useful than a tick.

## Verified

| § | Check | Result |
|---|---|---|
| Gates | phpcs, phpstan L8, PHPUnit, Jest, build, validate-packages | all green (T084) |
| 4 | Strip renders All + 12 toolsets with counts; Rank Math present, Elementor absent (plugin inactive) | ✅ |
| 4 | Selecting a toolset narrows the table and the row count matches the strip count (Cache 7 → 7 rows) | ✅ |
| 4 | Toolset column shows `toolset/cache`, `toolset/configuration`, …; search placeholder follows the tab | ✅ |
| 4 | Each entry is a real `<a href>`; no `role="tablist"`; existing query args preserved in every href | ✅ |
| 4 | `?tab=cache` deep-links and selects Cache | ✅ |
| 5 | `?page=acrossai-abilities-integrations&tab=cache` → abilities list, Cache selected | ✅ **after a fix — see below** |
| 5 | Same URL with a nonsense tab → abilities list, no error, falls back to All client-side | ✅ |
| 5 | `GET /acrossai-abilities-library/v1/abilities/config` → **404 `rest_no_route`** | ✅ |
| 5 | `GET /acrossai/v1/abilities/toolsets` → **200** `{counts, total}`, not swallowed by the `(?P<slug>…)` wildcard | ✅ |
| 4/5 | `total` is **419**, the unfiltered count, while a filtered tab shows its own — SEC-002 | ✅ |
| 5 | Settings → Abilities renders the Third-Party Integrations section | ✅ |
| 5 | Integrations submenu absent from the AcrossAI menu | ✅ |
| 6 | Payload delta 584,016 → 0 bytes | ✅ (T080) |

## Found only here — the redirect was hooked to the wrong action

`admin_init` priority 1 could never work, and every artifact in this feature said it would.

Core denies an unregistered `page` argument in `wp-admin/includes/menu.php:384`, reached from the
`require` at `wp-admin/admin.php:163` — **before `do_action( 'admin_init' )` at `admin.php:180`.** The
legacy URL became exactly such a page when the submenu was deleted, so the visitor got "Sorry, you are
not allowed to access this page" and the handler never ran.

All 11 unit tests passed the whole time, because they exercise `redirect_target()` — which was correct
from the first line. Nothing but a real request could catch a wrong *hook*. Fixed by hooking
`admin_page_access_denied` (fires at `menu.php:382`, immediately before that `wp_die()`), keeping
`admin_init` P1 for the case where another plugin still registers the slug. A structural regression test
now asserts the wiring, and was mutation-checked.

The stale claim was corrected in [contracts/removed-surface.md](./contracts/removed-surface.md) and
tasks T067/T069.

## Found only here — the migration stamped its rows as user-created abilities

`save_override()` does not set `source`; the column is `NOT NULL DEFAULT 'db'`. Its SEC-002 guard strips
a caller-supplied `'db'` and the schema default then silently reinstates it. The REST write path avoids
this by calling `AcrossAI_Ability_Source_Detector::detect()` first (RF-04); the migration did not.

Every override row it wrote was therefore stamped `source = 'db'` and came back from the `source=db`
listing as a custom ability with null label, status and callback. Fixed by passing `'source' => 'plugin'`
— the definitions registry only ever holds abilities this plugin registers, and the detector cannot be
used because it needs a registered `provider`, which would mean calling `wp_get_ability()` at `init`
P100 and forcing `wp_abilities_api_init` early. Guarded by a CI structural test (mutation-checked) and a
wp-env persistence test.

## Not verifiable on this site

| § | Why |
|---|---|
| 1, 2 | The migration has already run: `acrossai_library_config` is consumed and deleted and the done flag is set. There is no before-state left to diff, and the baseline's "expect 52" was derived from the original configuration, not the one that was later rebuilt by hand. |
| 2b | Concurrency needs a fresh un-migrated site. Recreating one means writing to the database, which the operator has reserved to themselves. |
| 3 | Multisite: out of scope — `README.txt:70-72` documents no multisite support. |
| 5 | The ACF switch: ACF is not active here, so the section correctly renders its empty state. The toggle itself is untested live. |
| 7 | MCP before/after comparison needs the pre-upgrade set, which no longer exists. |

`wp eval` cannot substitute for any of these. WordPress rejects `wp_register_ability()` outside
`wp_abilities_api_init`, so under WP-CLI only ~34 abilities register at all; a check that a blocked
ability is "absent" passes for every ability, blocked or not
(`BUG-PATH-B-AGGREGATE-UNDERCOUNT`). A control — confirming a *non*-blocked ability is present — is what
exposes that, and it fails too.

## Row disappearance — resolved, not a defect

Mid-session the abilities table went from 30 rows to 8: all 22 `source='db'` rows disappeared, including
the 16 Force Blocks the migration had written. This was recorded here as an unexplained loss and handed
to the operator.

**The operator deleted them manually.** No code path was involved, and the candidate mechanisms this
document previously speculated about — the `acrossai/conflict-test-*` abilities, a conflict-test
mu-plugin, `delete_override_by_slug()` — were all wrong. Nothing to investigate and nothing to fix.

Two things this does change about the evidence above, and they are worth being precise about:

- The `source='db'` finding stands on its own. It was observed directly: `query( array( 'source' =>
  'db' ) )` returned 22 rows including the migration's own, each with null label, status and callback,
  *before* any of them were deleted. That is what the fix addresses.
- The UI reading "Default" instead of "Blocked" for `cache/flush-rewrite-rules` was **not** evidence that
  the mislabelled source broke the override merge. By the time that search ran the rows were already
  gone, so "Default" simply meant "no override row". No merge defect was ever demonstrated, and none
  should be inferred from this document.
