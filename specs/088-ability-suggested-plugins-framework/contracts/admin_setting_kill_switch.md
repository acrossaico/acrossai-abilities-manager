# Contract: "Disable the Plugin suggestion" admin setting

**Feature**: 088 | **Location**: AcrossAI settings page (`admin.php?page=acrossai-settings`) + WP option `acrossai_disable_plugin_suggestions`

## Storage

- WordPress option key: `acrossai_disable_plugin_suggestions`
- Value type: `bool` (stored as WP option — typically `'1'` / `'0'` or `true` / `false` after `absint()` cast)
- Autoload: `no` (only read at ability payload emission; not part of frontend request lifecycle)
- Default: `false` (suggestions shown — opt-out model per FR-006)

## Read contract

- Read via `get_option( 'acrossai_disable_plugin_suggestions', false )` — always with an explicit `false` default
- Reader (`AcrossAI_Ability_Library_Registry::format_merged_ability()`) MUST cast to `bool` before use: `$disabled = (bool) get_option( 'acrossai_disable_plugin_suggestions', false )`

## Write contract

- Written via WP Settings API save handler (i.e. by submitting the settings page form)
- Sanitize callback registered via `register_setting()` — a named public method (not closure) per `PATTERN-CHECKBOX-SANITIZE`:
  ```php
  public static function sanitize_disable_plugin_suggestions( $input ): int {
      return empty( $input ) ? 0 : 1;
  }
  ```
- Never written from any other code path (no runtime toggles, no REST route, no CLI flag)

## Admin UI contract

- New section OR new field inside an existing section on the AcrossAI settings page — placement determined during implementation, but MUST be on `admin.php?page=acrossai-settings`
- Label: **"Disable the Plugin suggestion"** (exact wording per user request)
- Description: **"When enabled, no suggested-plugin cards appear on the Library page and no `suggested_plugins` field is emitted in ability payloads (REST + MCP). Ability behaviour is unaffected."**
- Render: single unchecked-by-default checkbox
- Save button: shared with other settings on the page (existing WP Settings API `submit_button()` handles this)
- Nonce: built-in via WP Settings API (`settings_fields()` outputs it)
- Capability gate: inherited from the settings page menu registration (`manage_options`)

## Uninstall contract

- `uninstall.php` MUST delete the option:
  ```php
  if ( $acrossai_delete_data ) {
      delete_option( 'acrossai_disable_plugin_suggestions' );
      // ... other option deletions inside the same gate
  }
  ```
- Gated by the existing `$acrossai_delete_data` boolean per `PATTERN-UNINSTALL-DATA-GATE`
- Not deleted on plugin deactivate (WP standard: only uninstall clears data)

## Effect contract

When the option changes value, the effect MUST be visible:
- **Library UI**: on the next page load (no JS-side caching)
- **REST payloads**: on the next request (option cache TTL is per-request in WP)
- **MCP payloads**: on the next `discover-abilities` invocation
- **Existing 500+ ability rendering**: NO effect — the option only gates the `suggested_plugins` field, and abilities without a `suggested_plugins()` override never had that field to begin with

## Test coverage expectations

- Golden: option `false` (default) + fixture ability with a declared suggestion → payload includes `suggested_plugins`
- Kill-switch active: option `true` + same fixture → payload has no `suggested_plugins` field
- Sanitize callback: called with empty input → returns `0`; called with any truthy input → returns `1`
- Uninstall with data-delete off: option persists after simulated uninstall run
- Uninstall with data-delete on: option removed after simulated uninstall run
- Non-boolean option value in database (defensive, edge case): reader still coerces to bool without error
