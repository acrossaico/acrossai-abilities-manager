# Planning: Retire the Ability Registration Gate (Feature 102)

Remove the Ability Integrations screen's registration gate, and fold its task-family tabs into the
Custom Abilities table as a filter. Every first-party ability always registers; access is controlled
only by the per-ability `site_allowed` overrides that already exist. The table gains a **Toolset**
column fed by `tab_group`, newly exposed in the REST record.

---

## The problem

The plugin ships two admin screens that both claim to control ability access. They are not two views of
one thing — they are two layers, and one is silently upstream of the other.

`AcrossAI_Ability_Library_Processor::register_abilities()` (`wp_abilities_api_init` **P5**) calls
`wp_register_ability()` only for definitions `is_permitted()` accepts: category `enabled === false` is
skipped, and `mode === 'specific'` admits only slugs ticked in `sub_keys`.

So switching a card off on Ability Integrations does not *hide* those abilities — it prevents them
existing. `wp_get_abilities()` never sees them, so they vanish from the Custom Abilities table entirely.
Search that table for `database` with the Database card off and you get **"No abilities found"**, with
nothing anywhere on the screen explaining why.

Meanwhile the abilities table already carries a complete per-ability access model — `site_allowed`
(Default / Force Allow / Force Block) plus MCP exposure — reachable from both the edit form and bulk
actions. Two independent off-switches, one of which erases its own evidence.

This feature deletes the upstream one.

---

## Where "disabled" actually happens

The full chain, so the removal has exact coordinates.

### Write path — UI writes an option. None of this has any effect by itself.

| Step | Location |
|---|---|
| Group-wise toggle | `LibraryCard.js:117,143` — `ToggleControl` → `update({ enabled: value })` |
| `All` / `Specific` mode | `LibraryCard.js:147,163` — `RadioControl` → `update({ mode })` |
| Per-ability tick | `LibraryCard.js:204,211` — `CheckboxControl` → `sub_keys` |
| Collected into an entry | `LibraryCard.js:90` — `onChange( category, { ...entry, ...patch } )` |
| Save on every click (no debounce) | `LibraryPage.js:302` — `handleChange()` → `saveConfig( next )` |
| "Disable All" | `LibraryPage.js:352-358` → `runBulkPatch` → `collectInScopeCategories` (194) + `buildBulkPatch` (225) |
| POST | `api.js:24` → `{restBase}/abilities/config` |
| Sanitize + store | `AcrossAI_Ability_Library_Config.php` — `save_config()` (43), `sanitize_entry()` (93), `update_site_option( 'acrossai_library_config' )` (81) |

Note **"Disable All" is tab-scoped, not site-wide** — `collectInScopeCategories` (line 194) narrows to
the active tab. The button's label has always overstated it.

### Enforcement path — one function, and it is the whole feature.

| Step | Location |
|---|---|
| Hook | `includes/Main.php:457` — `wp_abilities_api_init` **priority 5** |
| Loop | `AcrossAI_Ability_Library_Processor.php:64-77` |
| **The decision** | `…Processor.php:73` — `if ( ! $this->is_permitted( $definition, $config ) ) { continue; }` |
| The rule | `…Processor.php:94-120` — `is_permitted()` |
| The effect | `…Processor.php:76` — `wp_register_ability()` is simply never called |

`is_permitted()` at line 94 is the single point where "disabled" becomes real. Everything above it is
plumbing that writes an option; everything downstream — the empty abilities table, the missing REST
rows, the absent MCP tools — is a consequence of line 76 not running.

---

## What changes

### Removing

| # | What | Where |
|---|---|---|
| 1 | The registration gate | `AcrossAI_Ability_Library_Processor::is_permitted()` + its call site |
| 2 | Per-category on/off toggle | `LibraryCard.js` |
| 3 | `All` / `Specific` mode and its `sub_keys` allow-list | `LibraryCard.js`, `AcrossAI_Ability_Library_Config` |
| 4 | The `MAX_KEYS` / `MAX_SUB_KEYS` 50-entry cap (a live truncation bug) | `AcrossAI_Ability_Library_Config.php:22-23,103` |
| 5 | The `acrossai_library_config` option | deleted by the migration once converted |
| 6 | REST route `/acrossai-abilities-library/v1/abilities/config` | `AcrossAI_Ability_Library_Config_Controller.php` |
| 7 | `Enable All` / `Disable All` and their helpers | `LibraryPage.js`; `is_all_enabled()`, `is_all_disabled()`, `bulk_toggle_state()` |
| 8 | The Integrations submenu and page | `LibraryMenu.php`, `src/js/ability-library/`, `src/scss/ability-library/` |
| 9 | Webpack entries and committed build artefacts | `webpack.config.js:81-90`, `build/{js,css}/ability-library*` |
| 10 | The definitions payload on admin screens (~451 rows with full JSON Schema) | `admin/Main.php:294` |

### Keeping (deliberately)

Two different things share the word "integration". The **page** goes; the **framework** stays.

| What | Why |
|---|---|
| `AcrossAI_Ability_Library_Registry` + `Processor` | They register the entire ~451-ability catalogue — only the gate check goes |
| `Ability_Definition` normalization | `tab_group` becomes load-bearing for the new column and the tabs |
| Third-party integration opt-in (`maybe_enable()` → `enable_filter()`) | `ACF::enable_filter()` is `add_filter( 'acf/settings/enable_acf_ai', '__return_true' )` — it is what makes ACF register its abilities at all. Without it there is nothing for the table to override. **Relocates to settings** |
| `acrossai_integration_toggle_capability` filter | Moves with the switch, or we widen who can enable ACF's AI |

### Doing

| # | What | Where |
|---|---|---|
| 1 | Expose `tab_group` in the ability record | `AcrossAI_Ability_Merger::normalize_registry()`, `AcrossAI_Abilities_Formatter` |
| 2 | `tab_group` filter on the abilities list route | `AcrossAI_Abilities_Read_Controller`, `AcrossAI_Ability_Registry_Query` (`slugs` param) |
| 3 | Localize per-family counts | `AcrossAI_Ability_Group::counts()` → `window.acrossaiAbilitiesManager` |
| 4 | Tab strip: `All` + one tab per family, filtering the same table | new `GroupTabs.jsx` |
| 5 | New **Toolset** column (`toolset/content`), `—` when none | new `ToolsetCell` |
| 6 | Split the 1128-line list into container + table + toolbar + cells | `AbilitiesList.jsx` |
| 7 | Merge the two URL hooks so `?tab=` and `?action=edit` stop clobbering each other | new `hooks/useUrlSync.js` |
| 8 | `activeTab` in the store; tab change resets page and clears selection | `store/index.js` |
| 9 | **One-time migration: blocked-by-config → `site_allowed = false`** | new `AcrossAI_Library_Gate_Migration` |
| 10 | Integration opt-ins → settings page, state and capability intact | new `Integrations_Settings_Menu.php`, new `acrossai_integrations` option |
| 11 | 301 the retired slug, preserving `?tab=` | new `Integrations_Redirect.php` |

---

## File-by-file

**PHP — `includes/Modules/Library/`** (four of nine go)

| File | Verdict |
|---|---|
| `Ability_Definition.php` | **Keep.** Trim `is_all_enabled()`, `is_all_disabled()`, `bulk_toggle_state()` |
| `AcrossAI_Ability_Group.php` | **Keep** — powers the tabs, counts and `tab_group` filter |
| `AcrossAI_Ability_Library_Registry.php` | **Keep** — the ability catalogue itself |
| `AcrossAI_Ability_Library_Processor.php` | **Keep**, minus `is_permitted()` |
| `AcrossAI_Category_Slug_Migration.php` | **Keep** — unrelated historical upgrade path |
| `Integrations/AcrossAI_Integration_Ability_Base.php` | **Keep** — one line changes, to read the new option |
| `AcrossAI_Ability_Library_Config.php` | **Delete** after the migration reads it |
| `Rest/AcrossAI_Ability_Library_Config_Controller.php` | **Delete** |
| `Rest/AcrossAI_Ability_Library_Rest_Controller.php` | **Delete** — exists only to register the config controller (line 66); the whole `acrossai-abilities-library/v1` namespace goes with it |

**PHP elsewhere** — `includes/Abilities/Integrations/ACF.php` **keep**; `admin/Partials/LibraryMenu.php`
**delete**.

**JS** — all five files in `src/js/ability-library/` deleted. Salvage only the pure URL helpers
(`parseTabFromUrl`, `buildUrlFromTab`) into `src/js/abilities/hooks/useUrlSync.js`. The tab-grouping
helpers (`groupDefinitions`, `collectTabGroups`, `filterItemsByTabGroup`,
`groupBySubGroupPreservingOrder`) are **not** salvaged — tabs are now driven by REST `tab_group` and
server-side counts, so they have no consumer.

**CSS** — `src/scss/ability-library/admin.scss` deleted, after porting the still-live rules (empty
state, chip treatment) into a new `src/scss/abilities/_toolset-tabs.scss` — the first partial in that
1602-line single file.

**Tests** — delete 11 of the 12 in `tests/jest/ability-library/`, repointing
`useLibraryTabSync.test.js` at `useUrlSync.js`. Delete `Test_Ability_Library_Config.php`. Keep
`Test_Integration_Ability_Base.php` (updated for the new option), `Test_Ability_Definition.php`,
`Test_Ability_Group.php`, `Test_Ability_Group_Map.php`, `Test_Ability_Library_Registry.php`,
`Test_Category_Slug_Migration.php`.

---

## The screen being built

One page, `?page=acrossai-abilities-manager`:

- **Header** — h1 "Custom Abilities" + subtitle. No action buttons.
- **Tab strip** — `All` plus one tab per task family with its count, data-driven so Rank Math and
  Elementor appear only when their host plugins are active. Selecting a tab filters the table.
- **Toolbar** — identical on every tab: Bulk Actions · Apply · All Sources · All Statuses · search ·
  Columns · item count · pager. Search placeholder reflects the tab.
- **Table** — today's table plus a **Toolset** column between Category and Source, rendering
  `toolset/content` as a mono chip; `—` for abilities with no family.

Columns: checkbox · Slug · Label + "by &lt;provider&gt;" · Category · **Toolset** · Source · Status ·
Type · Show in REST · MCP · Actions.

Row checkboxes mean bulk selection on every tab. One meaning, no cards, no bands, no sub-group rows.

### Why this collapses the work

`AcrossAI_Ability_Merger::normalize_registry()` currently drops `meta.acrossai` entirely, so `tab_group`
never reaches the client. Exposing it makes the new column *and* the tab filter both work off the
existing REST collection — no definitions payload on this screen, no client-side join, no special-cased
pagination. And because every ability now registers, per-family counts are stable and pagination
behaves normally.

---

## Implementation notes

**1 — Expose `tab_group`.** `AcrossAI_Ability_Merger::normalize_registry()` reads it from
`meta.acrossai.tab_group` via the existing `$ann_or_meta` accessor; `AcrossAI_Abilities_Formatter`
emits it from both `format_merged_ability()` and `format_for_response()` (`''` for DB abilities).

**2 — Filter and count.** Add a `tab_group` list arg (`sanitize_key`) resolved through
`AcrossAI_Ability_Group::member_names()` and passed as `$registry_params['slugs']`. Never re-read
`meta.acrossai.tab_group` inline — that class is the single seam and already excludes protected slugs,
which is what keeps the 13 Toolset dispatchers out of the tabs. `AcrossAI_Ability_Registry_Query` takes
one optional `slugs` param: a single `in_array()` skip after the protected-slug skip and before the
source/search filters, so it composes. Counts come from `AcrossAI_Ability_Group::counts()`, **not** from
`acrossai_ability_library_tab_group_summary` — that is sorted count-descending for its Quick Connect
consumer and would be a second source of truth.

> The `category` filter is dead on the registry path (`Read_Controller.php:203-210` omits it, and
> `AcrossAI_Ability_Registry_Query::query()` has no category filter). Out of scope here, but do not
> assume it works.

**4 — Migration.** Follows `AcrossAI_Category_Slug_Migration` exactly: `maybe_migrate()`, option-flag
guarded, wired at `AcrossAI_Activator.php:50` and `Main.php:402`. For each first-party category entry:
`enabled === false` → `site_allowed = false` for every ability in that category; `mode === 'specific'` →
`site_allowed = false` for every ability **not** ticked in `sub_keys`; absent or all-mode → nothing.
Written through `AcrossAI_Abilities_Query` / `AcrossAI_Abilities_Row`, not raw SQL. **Never overwrite an
existing override row** — an explicit admin decision wins. Entries with
`card_variant === 'integration'` are copied into the new `acrossai_integrations` option rather than
converted, so ACF's opt-in state survives. Resolve category → ability list from `get_definitions()`, not
`wp_get_abilities()`, so the mapping does not depend on hook ordering.

**5 — Settings.** `is_integration_enabled()` moves to a small `AcrossAI_Integration_Settings` reading
`acrossai_integrations`; `maybe_enable()`'s call site is a one-line change and its try/catch resilience
contract is untouched. New `admin/Partials/Integrations_Settings_Menu.php` follows
`File_Manager_Settings_Menu.php`: `register_setting` + `add_settings_section` on the shared
`acrossai-settings` host page, one checkbox per integration. Server-rendered, no new JS bundle.

**6 — Client.** `AbilitiesList.jsx` keeps its filename and gains the tab strip; `AbilitiesTable.jsx`
(lines 753-1066), `AbilitiesToolbar.jsx` (521-750 + pager 1068-1106) and `cells/index.jsx` (26-150)
extract downward. `GroupTabs.jsx` must **not** use `@wordpress/components` `TabPanel` — this screen is
hand-rolled classic WP-admin markup end to end, and `TabPanel` would inject `components-tab-panel__*`
classes and its own focus model into a screen that uses none of it. **Superseded detail:** an earlier
draft of this document said to render `<nav role="tablist">`. Spec FR-012a (added during
`/speckit-clarify`) chose navigation links instead, so `role="tablist"` must **not** be used — declaring
the ARIA tabs pattern commits to arrow-key roving focus that links deliberately do not implement. `COLUMN_DEFAULTS` gains
`toolset: true`; leave `description` alone rather than silently flipping a default that saved prefs
would not reset.

**7 — URL sync.** `useUrlViewSync` and `useLibraryTabSync` both do
`pushState( buildUrl( …, window.location.href ) )`; if `view` and `tab` change in one tick the second
clobbers the first. Merge into one hook owning `action`, `slug`, `tab` with a single `pushState`,
composing the existing pure helpers so their unit tests stay green:
`buildUrl(view, tab, href) = buildUrlFromTab(tab, buildUrlFromView(view, href), ALL_TABS_KEY)`. Preserve
`?tab=` while editing; keep `parseTabFromUrl`'s invalid-value sentinel fallback.

---

## Ordering

1. **Expose `tab_group`** (merger + formatter) + tests. Invisible.
2. **`tab_group` filter** + `slugs` param + counts in the payload + tests. Invisible.
3. **Client refactor** — split `AbilitiesList.jsx`. **Screenshot before/after: pixel no-op.**
4. **Tabs + Toolset column** — `GroupTabs`, `ToolsetCell`, `activeTab`, merged URL hook. The screen now
   matches the design while the Integrations page still works.
5. **Migration + settings page** — write the converter test-first, move the ACF opt-in, rehearse on a
   copy of a site with categories switched off. Ship nothing else in this step.
6. **Remove the gate** — delete `is_permitted()`. Only safe once step 5 is proven.
7. **Delete the old surface** — menu, redirect, bundles, SCSS, stale build artefacts, dead helpers.
8. **Docs** — `README.txt` changelog (slug changes are documented carefully here — see `README.txt:394`),
   `docs/FEATURES.md`, `docs/memory/`.

Steps 1-4 are individually shippable and invisible. **Step 6 must not land before step 5 is verified.**

---

## Risks

- **The migration is the whole risk.** Get it wrong and a site that had `database/search-replace` or the
  file-manager abilities switched off silently exposes them. It runs once, on upgrade, unattended.
  Test-first, idempotent, never clobbers an existing override.
- **Order dependency is a security constraint, not a preference.** Gate removal before migration is a
  live regression for a release. They land in order, ideally in one release.
- **ACF opt-in state must survive.** `is_integration_enabled()` returns false for an absent entry, so a
  missed copy silently switches ACF off — confusing rather than dangerous, but cover it.
- **Capability gate must move with the switch.** Dropping `acrossai_integration_toggle_capability` on
  the settings save path would widen who can enable ACF's AI.
- **Pre-existing no-op: the Status filter** does nothing on the registry path (`status` is never
  forwarded to the registry query). It will look newly broken in a redesigned toolbar — either forward
  it in step 2 or hide it unless `source=db`.
- **`is_manager_page()`** (`admin/Main.php:344`) hardcodes a hook suffix derived from a menu title owned
  by the external `acrossai-co/main-menu` package. After this change that one string gates the only
  remaining screen.
- **Published/Draft counts in `.subsubsub`** are current-page-only (`AbilitiesList.jsx:306-309`) while
  "All" uses the server total. The row is `display:none` today; reviving it revives the disagreement.

---

## Verification

- `npm run build` clean; `npx jest` and `composer phpcs` / `phpstan` green.
- `tests/phpunit/abilities/AbilitiesReadControllerTest.php` is a `WP_UnitTestCase` deliberately excluded
  from `phpunit.xml.dist` — run the new `tab_group` coverage under wp-env, not CI.
- **Migration rehearsal** on a copy of a site with the Database card off: record the effective
  allow/block set, upgrade, confirm it is byte-identical and that all 17 `database` abilities now appear
  reading `Force Block`.
- In the browser:
  - All tab matches today's table plus the Toolset column; tab-strip counts match the rows each tab yields.
  - The reported bug is gone — searching `database` returns 17 abilities, not "No abilities found".
  - `?tab=cache` deep-links; changing tab resets to page 1 and clears selection; Back from an edit
    returns to the right tab; `?page=acrossai-abilities-integrations&tab=elementor` 301s correctly.
  - Settings → Integrations shows the ACF switch in its pre-upgrade state; toggling it off stops ACF
    registering its abilities.
- MCP: `mcp-adapter-discover-abilities` returns the same set before and after the upgrade.

---

## Phase 1: Setup & Specification

```markdown
# 1. Create the feature branch
/speckit.git.feature "abilities-toolset-tabs"

# 2. Specify the requirements — use the detailed prompt below
/speckit.specify "Remove the ability registration gate from the Integrations screen and fold its task-family tabs into the Custom Abilities table as a filter."
```

### Detailed Description for `/speckit.specify`:

> Remove the ability registration gate from the Integrations screen, and fold that screen's task-family
> tabs into the Custom Abilities table as a filter.
>
> Today the plugin has two screens that both claim to control ability access, and one is silently
> upstream of the other. The Integrations screen's per-category toggle and All/Specific mode do not hide
> abilities — they stop those abilities being registered with WordPress at all. The result is that the
> Custom Abilities table, which lists registered abilities, shows nothing for a category that has been
> switched off, with no explanation on the screen. Searching that table for a disabled category's
> abilities returns no results. Meanwhile the abilities table already has a complete per-ability access
> model — a Default / Force Allow / Force Block site-access setting and an MCP exposure setting —
> reachable from both the edit form and bulk actions. Two independent off-switches, one of which erases
> its own evidence.
>
> After this change there is one screen and one access model. Every first-party ability always
> registers, and access is controlled only by the per-ability settings on the abilities table. The
> Custom Abilities page gains a tab strip: All, plus one tab per ability task family with its count,
> data-driven so families belonging to inactive host plugins do not appear. Selecting a tab filters the
> same flat table; the toolbar, the row checkboxes and the bulk actions behave identically on every tab.
> The table gains a Toolset column showing which task family each ability belongs to, blank for
> abilities that belong to none. The Enable All and Disable All buttons are removed — bulk actions
> already cover that with explicit selection and a confirmation step, and a one-click site-wide block
> has no undo.
>
> Existing sites must not change behaviour on upgrade. A one-time migration converts whatever the
> current configuration blocks into equivalent per-ability Force Block settings: every ability in a
> switched-off category, and every unticked ability in a category set to Specific. An ability whose
> access an administrator has already set explicitly keeps that setting — the migration never overwrites
> one. The effective set of reachable abilities must be identical immediately before and after the
> upgrade.
>
> One switch on the Integrations screen is not a duplicate and must survive: the opt-in for third-party
> integrations, which is what causes a third-party plugin to register its abilities in the first place.
> Without it there is nothing for the abilities table to control, and it is off unless explicitly
> enabled. These opt-ins move to the AcrossAI settings page as a short list of switches, keeping their
> existing capability check and their current on/off state. The Integrations submenu is then removed and
> its URL permanently redirects to the abilities page, preserving any tab parameter, since external
> documentation links to it.
>
> The visual treatment follows the plugin's existing admin styling, not a new design language.
