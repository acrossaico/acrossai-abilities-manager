# Planning: Debugging Abilities — Conflict Testing (Feature 061)

Add a new **Debugging** ability category to `acrossai-abilities-manager` and land its
first sub-group — **Conflict Testing** — as seven WP Abilities API abilities.

The Local by Flywheel addon at `local-wordpress-supercharged` today ships a
"Conflict Testing" feature that lets you toggle individual plugins active/inactive
without touching the `wp_options.active_plugins` row in the database. It works via:

1. A **must-use plugin** (`wp-conflict-tester.php`) that hooks `option_active_plugins`
   and rewrites the returned list.
2. A **JSON file** at `wp-content/conflict-test-overrides.json` that stores per-plugin
   overrides (only diffs from real DB status).
3. **Cascade logic** that follows WP 6.5's `RequiresPlugins` header — deactivating a
   plugin also deactivates its dependents; activating a plugin also activates its
   requirements.

Today this is driven entirely by the Electron addon over IPC. The goal is to expose
the same operations as WordPress Abilities inside this plugin so any WordPress-side
consumer (the Local addon, another WP plugin, or an MCP/AI client) can drive
conflict testing through the WP Abilities API at
`/wp-json/wp-abilities/v1/abilities/{slug}/run`.

The abilities land in a new **Debugging** category — broader than "Conflict Testing"
so future debugging-related abilities (log tail, Query Monitor toggle, transient
inspection, etc.) can join the same domain without another category proliferation.
This first commit ships the seven conflict-testing abilities only; the broader
Debugging category is designed to grow but nothing else lands in this PR.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "061-debugging-abilities-conflict-testing"

# 2. Specify
/speckit.specify "Add a new Debugging ability category to acrossai-abilities-manager
and land its first sub-group — Conflict Testing — as seven WP Abilities API abilities
that mirror the operations today driven by the Local by Flywheel addon at
local-wordpress-supercharged. The abilities let any WordPress-side consumer (the Local
addon, another WP plugin, or an MCP/AI client) toggle individual plugins active/inactive
without touching the wp_options.active_plugins row — via a must-use plugin that hooks
option_active_plugins plus a JSON overrides file at wp-content/conflict-test-overrides.json
that stores per-plugin diffs from real DB status.

Locked design decisions:

(1) Ability count and slugs (7 abilities, 1 category, 1 mu-plugin source asset, 2 shared helpers):
    - acrossai/conflict-test-list-plugins — readonly, idempotent
    - acrossai/conflict-test-get-overrides — readonly, idempotent
    - acrossai/conflict-test-set-override — not readonly, not destructive
    - acrossai/conflict-test-bulk-set-overrides — not readonly, not destructive
    - acrossai/conflict-test-clear-overrides — not readonly, destructive
    - acrossai/conflict-test-deploy-mu-plugin — not readonly, idempotent
    - acrossai/conflict-test-remove-mu-plugin — not readonly, destructive

(2) Category slug: acrossai-abilities-manager-debugging. Category label:
    'Acrossai Abilities Manager — Debugging'. tab_group: debugging.
    sub_group: conflict-testing (all seven abilities share this sub_group so the UI
    groups them together within the Debugging tab). sub_group_label: 'Conflict Testing'.

(3) Location and pattern — mirror the existing FileManager/ sibling:
    New ability domain directory at includes/Abilities/Debugging/. Every ability
    class extends AcrossAI_Abilities_Manager\\Includes\\Modules\\Library\\Ability_Definition.
    Only protected function ability(): array and public function execute(array $input = array()): array
    are implemented per class. permission_callback is a static function (): bool returning
    current_user_can('manage_options'). meta.acrossai uses tab_group, sub_group,
    sub_group_label for UI grouping. meta.show_in_rest = true.
    meta.mcp = ['public' => false, 'type' => 'tool'].
    meta.annotations with readonly/destructive/idempotent booleans on every ability.
    Category_Registrar is final class, singleton via private constructor + instance().
    defined('ABSPATH') || exit; guard at the top of every file.
    Namespace maps 1:1 to directory (PSR-4 already configured for
    AcrossAI_Abilities_Manager\\Includes\\ → includes/).

(4) Two shared helper classes so ability classes stay thin:
    - Overrides_Store — centralizes JSON read/write/cleanup (mirrors the TS
      readOverrides/writeOverride/clearOverrides in the Local addon). Writes
      exclusively to WP_CONTENT_DIR . '/conflict-test-overrides.json' — no path
      argument accepted from ability input. Computes mu_plugin_status
      ('deployed'|'missing'|'stale') by comparing the deployed
      WPMU_PLUGIN_DIR/wp-conflict-tester.php against the bundled source asset.
    - Dependency_Resolver — centralizes cascade logic (mirrors getDependentPlugins).
      Uses get_plugins() (WP core, via wp-admin/includes/plugin.php) to read the
      RequiresPlugins header off every installed plugin, then computes the transitive
      closure of dependents (for deactivation) and requirements (for activation).

(5) The mu-plugin source asset ships verbatim from the Local addon's
    src/features/conflict-test/wp-conflict-tester.php (33 lines, no changes needed).
    Deploy_Mu_Plugin reads this file and writes it into WPMU_PLUGIN_DIR.
    Target path is hardcoded to WPMU_PLUGIN_DIR . '/wp-conflict-tester.php' —
    no path argument accepted from input.

(6) Registration — modify includes/Modules/Library/AcrossAI_Core_Abilities_Bootstrap.php
    with two additions matching the existing pattern:
    - In register_category_callbacks(): add
      \$loader->add_action('wp_abilities_api_categories_init',
      Debugging\\Category_Registrar::instance(), 'register')
      alongside the existing 20+ category registrars.
    - In register_abilities(): add seven new Debugging\\<ClassName>() lines alongside
      the existing ability instantiations.
    No changes to composer.json — PSR-4 covers the new Debugging\\ sub-namespace
    automatically. No manual require calls needed anywhere.

(7) Permission model and security — every ability uses
    permission_callback => static fn() => current_user_can('manage_options')
    (identical to FileManager). Extra safety in Deploy_Mu_Plugin and Remove_Mu_Plugin:
    the target path is hardcoded to WPMU_PLUGIN_DIR . '/wp-conflict-tester.php'.
    Gate Deploy_Mu_Plugin and Remove_Mu_Plugin behind File_Mods_Guard::blocked_response()
    (same helper FileManager\\Edit_File uses) so a site with DISALLOW_FILE_MODS = true
    cannot be forced to deploy or remove the mu-plugin. Overrides_Store writes
    exclusively to WP_CONTENT_DIR . '/conflict-test-overrides.json' — no path argument
    accepted from ability input.

(8) JSON store format is UNCHANGED from the Local addon's shape:
    { \"overrides\": { \"plugin/file.php\": bool, ... } }
    So the abilities and the Local addon can coexist and drive the same file with no
    divergence. No migration or backfill needed.

(9) Cascade behaviour — Set_Override accepts input
    { plugin_file, active, cascade? = true }. When cascade = true and active = false,
    Dependency_Resolver walks the transitive closure of dependents (any plugin whose
    RequiresPlugins header names this plugin) and writes an override entry for each
    to also inactive. When cascade = true and active = true, walks the transitive
    closure of requirements and writes an override entry for each to active.
    Bulk_Set_Overrides accepts { plugin_files: [], active } and skips cascade entirely
    — the caller controls the list, matching current BULK_SET_CONFLICT_OVERRIDES semantics.

(10) Overrides_Store::write applies the same 'no-op if matches DB, delete file if empty'
     logic as the addon's writeOverride: if the requested active state matches the real
     get_option('active_plugins') state, the entry is dropped from overrides; if the
     resulting overrides map is empty, the JSON file is deleted (rather than written as
     '{\"overrides\":{}}').

Files to create:
  includes/Abilities/Debugging/Category_Registrar.php     — final singleton, registers 'acrossai-abilities-manager-debugging'
  includes/Abilities/Debugging/List_Plugins.php           — ability 1
  includes/Abilities/Debugging/Get_Overrides.php          — ability 2
  includes/Abilities/Debugging/Set_Override.php           — ability 3
  includes/Abilities/Debugging/Bulk_Set_Overrides.php     — ability 4
  includes/Abilities/Debugging/Clear_Overrides.php        — ability 5
  includes/Abilities/Debugging/Deploy_Mu_Plugin.php       — ability 6
  includes/Abilities/Debugging/Remove_Mu_Plugin.php       — ability 7
  includes/Abilities/Debugging/Overrides_Store.php        — shared helper: read/write JSON + compute mu_plugin_status
  includes/Abilities/Debugging/Dependency_Resolver.php    — shared helper: dependents / requirements cascade
  includes/Abilities/Debugging/assets/wp-conflict-tester.php — source asset copied on deploy

Files to modify:
  includes/Modules/Library/AcrossAI_Core_Abilities_Bootstrap.php
    — register_category_callbacks(): add Debugging category registrar
    — register_abilities(): instantiate the seven new Debugging\\* ability classes

Out of scope for this feature:
  - No PHPUnit tests in the initial commit (existing FileManager abilities have none
    — matches project convention).
  - No migration or backfill — the JSON store format is unchanged from the addon.
  - No custom REST-endpoint code — the WP Abilities API auto-exposes each registered
    ability at /wp-json/wp-abilities/v1/abilities/{slug}/run.
  - Other debugging abilities (log tail, Query Monitor toggle, transient inspection,
    etc.) are deliberately left out of this first commit — the Debugging category is
    designed to grow but this PR only lands the seven conflict-testing abilities.

Verification (end-to-end smoke test):
  1. Composer autoload dump — composer dump-autoload in the plugin root so the new
     Debugging\\ namespace is discoverable.
  2. List abilities via REST —
     curl -u admin:pass https://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities
     | jq '.[] | select(.name | startswith(\"acrossai/conflict-test-\"))' — expect all 7.
  3. End-to-end:
     - POST .../conflict-test-deploy-mu-plugin/run
       → verify wp-content/mu-plugins/wp-conflict-tester.php now exists.
     - POST .../conflict-test-list-plugins/run
       → confirm result matches Local's UI plugin list.
     - POST .../conflict-test-set-override/run with
       { plugin_file: 'hello-dolly/hello.php', active: false } on an active Hello Dolly
       → reload site frontend and confirm Hello Dolly is no longer loaded, but
       SELECT option_value FROM wp_options WHERE option_name='active_plugins' still
       contains it.
     - POST .../conflict-test-clear-overrides/run
       → confirm JSON file is deleted and Hello Dolly runs again.
     - POST .../conflict-test-remove-mu-plugin/run
       → confirm the mu-plugin file is removed.
  4. Cascade regression — activate two plugins where B declares RequiresPlugins: a,
     then call set-override to deactivate A with cascade: true, and verify B also
     gets an override entry.
  5. Coexistence with Local addon — with the addon's ConflictTestPanel open on the
     same site, toggle a plugin from the addon UI, then call get-overrides via REST —
     both sides must read the same JSON file with no divergence."

# 3. Clarify (optional but recommended)
/speckit.clarify

# 4. Plan
/speckit.plan

# 5. Tasks
/speckit.tasks

# 6. Implement
/speckit.implement
```

---

## Ability contract details (feed into /speckit.plan)

### Category

- **Slug**: `acrossai-abilities-manager-debugging`
- **Label**: `Acrossai Abilities Manager — Debugging`
- **tab_group**: `debugging`
- **sub_group** (shared by all seven abilities): `conflict-testing`
- **sub_group_label**: `Conflict Testing`

### Abilities

| # | Class | Slug | Purpose | Annotations |
|---|-------|------|---------|-------------|
| 1 | `List_Plugins` | `acrossai/conflict-test-list-plugins` | Return every plugin with `{ file, name, version, status, requires_plugins }` using `get_plugins()` + `get_option('active_plugins')`. Replaces the addon's `wp plugin list` + `wp eval` calls. | readonly, idempotent |
| 2 | `Get_Overrides` | `acrossai/conflict-test-get-overrides` | Read `wp-content/conflict-test-overrides.json`. Return `{ overrides, mu_plugin_status: 'deployed'\|'missing'\|'stale' }`. | readonly, idempotent |
| 3 | `Set_Override` | `acrossai/conflict-test-set-override` | Input `{ plugin_file, active, cascade? = true }`. Uses the same "no-op if matches DB, delete file if empty" logic as the addon's `writeOverride`. When `cascade=true`, follows dependents (deactivate) or requirements (activate) via `RequiresPlugins`. | not readonly, not destructive |
| 4 | `Bulk_Set_Overrides` | `acrossai/conflict-test-bulk-set-overrides` | Input `{ plugin_files: [], active }`. Sets many overrides at once, no cascade (caller controls the list). Matches current `BULK_SET_CONFLICT_OVERRIDES` semantics. | not readonly, not destructive |
| 5 | `Clear_Overrides` | `acrossai/conflict-test-clear-overrides` | Deletes the overrides JSON file. Returns confirmation. | not readonly, destructive |
| 6 | `Deploy_Mu_Plugin` | `acrossai/conflict-test-deploy-mu-plugin` | Writes `WPMU_PLUGIN_DIR/wp-conflict-tester.php` from the bundled source asset. Skips write if file already matches (hash-compare). | not readonly, idempotent |
| 7 | `Remove_Mu_Plugin` | `acrossai/conflict-test-remove-mu-plugin` | Removes the mu-plugin (input flag `also_clear_overrides` optionally deletes the JSON too). Turns off conflict testing entirely. | not readonly, destructive |

### Reusable pieces already in the codebase

- `Ability_Definition` base class at `includes/Modules/Library/Ability_Definition.php` — every ability extends this; only `ability(): array` and `execute(...)` need implementing.
- `File_Mods_Guard` — reuse for the two mu-plugin abilities (same helper `FileManager\Edit_File` uses).
- PSR-4 autoloader already configured — no manual `require` for any new class.
- `get_plugins()` (WP core, via `wp-admin/includes/plugin.php`) — provides `Name`, `Version`, `RequiresPlugins`; active status computable from `get_option('active_plugins')`. Fully replaces the addon's `wp plugin list` + `wp eval` invocations.
- The mu-plugin PHP itself — bring over verbatim from `src/features/conflict-test/wp-conflict-tester.php` (33 lines).
- JSON store format — unchanged from the addon (`{ "overrides": { "plugin/file.php": bool, ... } }`), so the abilities and the Local addon can coexist and drive the same file with no divergence.

### Files touched (final tree)

```
includes/Abilities/Debugging/
├── Category_Registrar.php                    # final singleton, registers 'acrossai-abilities-manager-debugging'
├── List_Plugins.php                          # ability 1
├── Get_Overrides.php                         # ability 2
├── Set_Override.php                          # ability 3
├── Bulk_Set_Overrides.php                    # ability 4
├── Clear_Overrides.php                       # ability 5
├── Deploy_Mu_Plugin.php                      # ability 6
├── Remove_Mu_Plugin.php                      # ability 7
├── Overrides_Store.php                       # shared helper: read/write JSON + compute mu_plugin_status
├── Dependency_Resolver.php                   # shared helper: getDependents / getRequirements cascade
└── assets/
    └── wp-conflict-tester.php                # source asset copied on deploy — VERBATIM from Local addon

includes/Modules/Library/AcrossAI_Core_Abilities_Bootstrap.php
    # register_category_callbacks(): add Debugging category registrar
    # register_abilities(): instantiate the seven new Debugging\* ability classes
```

---

## Prerequisite before /speckit.implement

The mu-plugin source at `includes/Abilities/Debugging/assets/wp-conflict-tester.php`
is a **verbatim copy** of `src/features/conflict-test/wp-conflict-tester.php` from the
Local by Flywheel addon (`local-wordpress-supercharged`). That source lives outside
this repo. Paste its 33 lines into the conversation before running
`/speckit.implement` so the implementation step can write the file byte-identically.
