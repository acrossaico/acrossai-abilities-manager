# T071 — Removal inventory

Exhaustive `grep -rEn` over `includes/ src/ tests/ admin/ webpack.config.js` for every symbol in
[contracts/removed-surface.md](./contracts/removed-surface.md) **and every public member of every
class being deleted** — not just the class names. Hits inside the doomed files themselves are
excluded; what is listed is what *survives* the deletion and therefore has to be dealt with first.

Scoping an inventory to class names is what let SEC-010 through (`BUG-INVENTORY-GREP-MISS`): the
class was on the deletion list while eight call sites to its members lived in classes that are
retained.

## Public members enumerated

| Doomed file | Public members |
|---|---|
| `Modules/Library/AcrossAI_Ability_Library_Config.php` | `OPTION_KEY`, `MAX_KEY_LENGTH`, `MAX_KEYS`, `MAX_SUB_KEYS`, `MAX_SLUGS`, `VALID_MODES`, `get_config()`, `save_config()`, `sanitize_entry()`, `sanitize_key_field()`, `is_integration_enabled()` |
| `Modules/Library/Rest/AcrossAI_Ability_Library_Config_Controller.php` | `instance()`, `register_routes()`, `get_config()`, `save_config()` |
| `Modules/Library/Rest/AcrossAI_Ability_Library_Rest_Controller.php` | `REST_NAMESPACE`, `instance()`, `register_routes()`, `check_permission()` |
| `admin/Partials/LibraryMenu.php` | `instance()`, `register_submenu()`, `get_hook_suffix()`, `render()` |

## Surviving references, and what clears each

| # | Surviving reference | Cleared by |
|---|---|---|
| 1 | `Ability_Definition.php:13` (`use`), `:225`, `:247` — `Config::get_config()` inside `is_all_enabled()` / `is_all_disabled()` | **T043** (deleting the three helpers removes the only Config dependency in this retained class, `use` included) |
| 2 | `AcrossAI_Category_Slug_Migration.php:132`, `:155` — `Config::OPTION_KEY` | **T072b** (own `SOURCE_OPTION`) |
| 3 | `AcrossAI_Ability_Library_Registry.php:417`, `:418`, `:462`, `:474`, `:498` and `Integrations/AcrossAI_Integration_Ability_Base.php:322` (+ docblock `:168`) — `Config::sanitize_key_field()` | **T072a** (extract to `Utilities/`) |
| 4 | `includes/Main.php:303` — `LibraryMenu::instance()` | **T072** |
| 5 | `includes/Main.php:432`, `:434` — library REST orchestrator + namespace comment | **T073** |
| 6 | `admin/Main.php:14`, `:295` — `Rest_Controller::REST_NAMESPACE` in the library payload | **T074** |
| 7 | `admin/Main.php:160`, `:190`, `:210`, `:280`, `:382` — `is_library_page()`; `:298` — `bulk_toggle_state()`; `:279`, `:292` — `acrossaiAbilityLibraryData`; `:374`, `:383` — `LibraryMenu::get_hook_suffix()` | **T074** |
| 8 | `tests/phpunit/Modules/Library/Test_Ability_Library_Config.php` (whole file) | **T078** |
| 9 | `tests/jest/ability-library/` (11 files) | **T078** |

## Gaps this inventory found — not in the task list before T071

These are the reason the gate exists. Each became a new task.

| # | Surviving reference | New task |
|---|---|---|
| G1 | `tests/phpunit/Modules/Library/Test_Ability_Definition.php:354-449` — six tests of `is_all_enabled()` / `is_all_disabled()` / `bulk_toggle_state()`, plus the `use` at `:16` and the `Config::OPTION_KEY` seeds at `:355-439` | **T043a** |
| G2 | `tests/phpunit/Modules/Library/Integrations/Test_Integration_Ability_Base.php:349-420`, `:588-642` — eight tests exercising `Config::save_config()` / `get_config()` / `is_integration_enabled()` directly. T065 repointed the `maybe_enable` seeds; these test the doomed class itself and T078 never listed them | **T073a** |
| G3 | `includes/Abilities/Integrations/ACF.php:12` — docblock states the opt-in persists in `acrossai_library_config`; it now persists in `acrossai_integrations` | **T073b** |
| G4 | `AcrossAI_Ability_Library_Registry.php:443` — comment describing the `window.acrossaiAbilityLibraryData` payload that T074 deletes | **T074a** |
| G5 | `src/js/shared/titleCaseTabLabel.js:9` — docblock points at `src/js/ability-library/components/LibraryPage.js` as a re-exporting consumer; T075 deletes it | **T075a** |
| G6 | `tests/phpunit/Modules/Library/Test_Category_Slug_Migration.php` — **12** references to `Config::OPTION_KEY` plus the `use` at `:17`: a retained test, for a retained class, depending on the doomed one. Found only on the second pass — the first pass truncated its output at 25 lines and this file sorted below the cut, which is `BUG-INVENTORY-GREP-MISS` reproducing itself inside the very task meant to prevent it. **Never cap the output of an inventory grep.** | folded into **T072b** |

## Deliberately left alone

- `tests/phpunit/Modules/Abilities/Test_Library_Gate_Migration_No_Request_Input.php:123` asserts the
  string `AcrossAI_Ability_Library_Config` is **absent** from the migration's source. The match is the
  assertion, so it stays.
- `includes/Modules/Abilities/AcrossAI_Library_Gate_Migration.php:80` declares its own
  `SOURCE_OPTION = 'acrossai_library_config'`, and `:73` explains why it is not read from the doomed
  class. That is the intended shape.
- `tests/phpunit/Modules/Abilities/Test_Library_Gate_Migration_Rules.php:228` mentions `MAX_SUB_KEYS`
  in a comment explaining why a fixture stops at 50 entries — historical context for the cap the
  translation has to reproduce, not a reference to the constant.
- `includes/Modules/Library/AcrossAI_Ability_Library_Processor.php:72` names `is_permitted()` in the
  comment recording that the gate was removed and why.
- `Secret_Redactor::get_config()` and `Test_Feature_092_*` are an unrelated `get_config` — different
  class, no relation to the library config.
- `src/js/abilities/constants.js:10` and `tests/jest/abilities/useUrlSync.test.js:4` reference
  `ability-library` only to record where the salvaged helpers came from.
