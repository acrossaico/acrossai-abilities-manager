# Planning: Ability Family Tabs (Feature 101)

Replace the Ability Integrations screen's 18 tabs with **13 task families**, assigning
`meta.acrossai.tab_group` per ability rather than per folder. Shorten category slugs from
`acrossai-abilities-manager-*` to `acrossai-*` behind a two-store migration. Fix a pre-existing bug
found during verification: the Debugging category was never registered.

> **Provenance.** Written after implementation, to complete the artifact set. Feature 101 was executed
> from an approved plan rather than through `/speckit-specify` → `/speckit-plan` → `/speckit-tasks`.
> The `/speckit.specify` prompt below is what would have produced this specification, reconstructed
> from the plan that was actually approved. Shipped as `4984d23`, `5a8aed5`, `9614520`.

---

## The problem

One tab, **Core**, held **105 of ~450 abilities** — nine whole folders plus parts of two more. It was
not a subject; it was where abilities landed when nobody chose. Fourteen of the other tabs mapped 1:1
onto a single card, so the tab level bought them nothing.

Two categories rendered as **half-cards in two tabs**. `Settings/` had 10 abilities tagged `core` and
1 tagged `settings`; `SiteHealth/` was split 3/3. Because the tab filter keeps only slugs matching the
active tab, each card appeared twice showing a different half of itself, with nothing on screen
indicating the other half existed.

The structure was not at fault. Tabs come from `tab_group`, cards from `category`, and `LibraryCard`
has rendered "Tab membership" chips since Feature 055 — cards spanning tabs were always anticipated.
What was missing was that nobody had chosen the assignments.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "101-ability-family-tabs"

# 2. Specify
/speckit.specify "Regroup the Ability Integrations screen into 13 task families named for what an
administrator is doing, replacing 18 tabs of which one — core — holds 105 of ~450 abilities.

FAMILIES, with the categories each draws from:
  content       71  content 29, comments 12, content-search 11, taxonomies 10, media 9
  appearance    64  block design 35, menus 12, fonts 8, settings site-identity 7, widgets 2
  blocks        52  block authoring, including 8 files moved in from Content/
  updates       23  plugins 10, themes 7, core 6
  files         23  file-manager
  diagnostics   20  debugging 7, recovery 7, site-health 6
  configuration 19  options 7, admin-menu 5, settings permalinks 4, media MIME 2, cache rewrite 1
  database      17
  cron          16
  users         16
  cache          7  cache 6 + database/cleanup-expired-transients
  elementor     63  (only when active)
  rank-math     61  (only when active)

ASSIGN tab_group PER ABILITY, not per folder. Five categories legitimately span two families because
their abilities serve two different jobs: Block (authoring vs theme design), Settings (branding vs
permalinks), Media (library vs upload policy), Cache (transients vs a permalinks operation), Database
(data layer vs a transient duplicate). Each such card renders in both tabs showing its relevant
subset, and LibraryCard already names every tab a card belongs to.

Do NOT reintroduce a general-purpose family. `core` is retired. Its 105 abilities are exactly what
this feature exists to disperse, and it formed one copy-paste at a time despite
PATTERN-ABILITY-LIBRARY-TAB-AUTO-DERIVE already warning about that failure mode.

THIRTEEN IS A CEILING. Feature 100 exposes one MCP tool per family, and Anthropic documents
tool-selection accuracy degrading past 30-50 available tools; production telemetry places Haiku below
90% between 10 and 15 and Sonnet below 90% at 30. A fourteenth family is cheap in wp-admin and
expensive in an AI client. Record this in DECISIONS.md so a future addition is a decision, not a
reflex.

NAMING CONSTRAINT: AcrossAI_Tab_Group_Label::format() is ucwords(str_replace('-',' ',$key)) and spec
037 FR-007 forbids a separate display-label field — the key IS the label. No ampersands are
producible. 'Plugins & Updates' would render 'Plugins Updates'; use 'updates'. 'Data & Files' should
be split into 'database' and 'files' rather than worked around.

MOVE FILES ONLY UNDER AN OBJECTIVE TEST: relocate an ability's file only when its slug namespace
already disagrees with its folder. Eight abilities in includes/Abilities/Content/ register as
blocks/* while declaring the content category — move them to Block/, changing category to
acrossai-block and sub_group to post-blocks. Their SLUGS MUST NOT CHANGE, so no MCP client is
affected. Three superficially similar candidates (media/*-upload-mime-types, cache/flush-rewrite-rules,
database/cleanup-expired-transients) must NOT move: their slug matches their folder, so moving them
would either relocate the inconsistency or force a slug rename, which README.txt records as breaking
with no back-compat alias. Handle them with per-ability tab_group instead.

ALSO SHORTEN CATEGORY SLUGS from acrossai-abilities-manager-* to acrossai-*, WITH A MIGRATION. A
category slug is a persistence key in two places: the keys of the acrossai_library_config site option,
and the category column of {prefix}acrossai_abilities for every DB-defined ability. WordPress refuses
to register an ability whose category is not registered — WP_Abilities_Registry::register() calls
_doing_it_wrong() and returns null — so a rename without migration makes operator-created abilities
silently cease to exist. Add AcrossAI_Category_Slug_Migration rewriting both stores, called from
Activator::activate() AND admin_init priority 1, because an in-place upgrade via composer, wp-cli or
file replace never re-activates. Guard with a completion flag. When rewriting config, prefer an entry
already under the new key over a stale legacy one so re-running cannot clobber a newer preference.

DRIVE THE RENAME FROM AN EXPLICIT ALLOW-LIST, NEVER A REGEX. Thirty distinct
acrossai-abilities-manager-* strings exist and only 25 are categories. The other five are the
-abilities and -mcp-extension asset handles registered in admin/Main.php, the -wrap CSS class in
admin/Partials/Menu.php, a docblock naming a dropped -roles category, and a -fixtures test category. A
prefix sweep corrupts all five — the same false-positive class recorded in
DEC-SLUG-CONVENTION-VERB-FIRST for Feature 058. Apply replacements longest-first so content-search is
not shadowed by content.

REPOINT PINNED_FIRST_TAB_GROUP in src/js/ability-library/components/LibraryPage.js from 'core' to
'content'. Once core stops existing the pin silently degrades to a no-op and ordering becomes
alphabetical, so the constant must move or the most-used family lands mid-alphabet. Update the Jest
test that pins it — the test uses fixtures, so it will NOT catch a stale constant.

GUARD EVERYTHING WITH TESTS, AND VERIFY EACH BY BREAKING WHAT IT GUARDS. The runtime has no
allow-list, no register_tab() and no server-side validation of tab_group, so a wrong value produces a
plausible tab with no error and no failing test. That is how core reached 105 despite a documented
warning. Add:
  - Test_Ability_Family_Map — the canonical map, core retirement, family sizes, label-safety, and the
    moved files' category/sub_group/slug consistency. Verify by misfiling one ability.
  - Test_Category_Slug_Migration — allow-list correctness, content/content-search ordering,
    non-category strings untouched, idempotency, newer-preference-wins, and a coverage test asserting
    the OWNED list covers every registered category. That coverage test MUST scan both declaration
    shapes ('category' => '...' and const CATEGORY = '...') and assert a minimum discovery count,
    because a version scanning only the first finds 24 of 25 and passes vacuously.
  - Test_Category_Registrar_Wiring — see below.

FIX THE UNWIRED DEBUGGING CATEGORY. includes/Abilities/Debugging/Category_Registrar.php shipped with
Feature 061 and was never added to AcrossAI_Core_Abilities_Bootstrap::register_category_callbacks() —
24 registrars are wired, 25 exist. Its seven conflict-testing abilities are instantiated, attempt to
register, and are rejected on every request; they have never existed at runtime, and 399 notices have
accumulated in debug.log unread. This also explains why docs/abilities-inventory.md recorded 389
abilities across 24 namespaces and omitted Debugging entirely — it was maintained from runtime data.
Wire the registrar and add Test_Category_Registrar_Wiring asserting BOTH directions: every folder
shipping a registrar is wired, and every folder whose abilities are instantiated has a registrar, with
a minimum-discovery count so a broken glob cannot pass against an empty set.

REGENERATE docs/abilities-inventory.md FROM SOURCE via a committed script rather than by hand. It had
drifted to claiming 389 abilities across 24 namespaces when source had 451 across 25, and nothing
verified it. The generator must resolve base-class constants (Rank Math declares const CATEGORY and
composes its slug as 'rank-math/' . \$this->slug()) and skip Integrations/, whose rows are synthetic
display entries rather than real abilities.

DO NOT rewrite historical changelog entries in README.txt — those describe past releases. Add a new
Unreleased entry covering: the regrouping, the two fixed half-cards, the file move and its orphaned
Specific-mode selections, the changed bulk-action scope, and the ten deep links that stop resolving
(?tab=core, media, themes, plugins, file-manager, comments, content-search, site-health, widgets,
settings) and fall back to All per spec 052.

Definition of Done per Constitution VII: PHPCS zero errors and warnings, PHPStan level 8 zero errors,
unit tests for all new logic, no DRY violations, all classes prefixed, npm run validate-packages
passing."
```

---

## Known consequences, carried into the spec

1. **A split category has one switch shown in two places.** The enable toggle and All/Specific mode
   are keyed by category (`LibraryCard` → `onChange( category, … )`), so turning `Block` off from the
   Appearance tab also removes its 44 authoring abilities from Blocks. The membership chips warn that
   the card lives elsewhere, but the control is genuinely shared. Removing this means splitting the
   five spanning categories into real separate categories, which is a config migration and out of
   scope.
2. **Bulk Enable All / Disable All scope changes meaning.** Same button, different blast radius, and
   split categories are now in scope for both of their tabs.
3. **Up to 8 saved Specific-mode selections orphan** from the file move, since those ticks are keyed
   to the old category.
4. **Ten deep links stop resolving.** Silent fallback, already the documented contract.
5. **`docs/abilities-inventory.md` will drift again** unless the generator is run. Consider a test
   that fails when it diverges from source.

## Out of scope, recorded so it is not lost

- **Duplicate ability pairs remain duplicates.** Filing `cleanup-expired-transients` with Cache and
  `flush-rewrite-rules` with permalinks makes the overlaps visible; it does not resolve them. Same for
  `site-health/set-site-maintenance-mode` vs `elementor/update-maintenance-mode`.
- **Debugging still uses legacy `acrossai/` slugs** — the only folder never renamed to a topic
  namespace. Renaming breaks any connected client.
- **`destructive` annotations are inconsistent.** `users/create-role` and `users/add-role-capability`
  are marked destructive though they add; `recovery/unpause-plugin` though it recovers;
  `options/patch-option-value` is destructive while `options/update-option` is not. Any UI or tool
  gating on this flag will mislead.
- **Splitting the five spanning categories** into real separate categories is the natural follow-up.
- **Feature 100's spec needs a follow-up edit** — its grouping key changes from category to family.
