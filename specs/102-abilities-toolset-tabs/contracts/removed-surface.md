# Contract: Withdrawn surface

Everything below is removed. Each row names what replaces it.

## REST

| Withdrawn | Replacement |
|---|---|
| `GET/POST /acrossai-abilities-library/v1/abilities/config` | none — integration opt-ins move to the Settings API; category gating ceases to exist |
| The entire `acrossai-abilities-library/v1` namespace | — (`AcrossAI_Ability_Library_Rest_Controller` existed only to register the config controller) |

No external consumer is known; the namespace served one admin screen in this plugin.

## Admin

| Withdrawn | Replacement |
|---|---|
| `?page=acrossai-abilities-integrations` | **301** to `?page=acrossai-abilities-manager`, preserving `?tab=` |
| Per-category toggle, `All`/`Specific` mode, `sub_keys` picker | per-ability access setting on the abilities list |
| `Enable All` / `Disable All` | bulk actions with explicit selection and confirmation |
| Third-party integration opt-in | Settings → **Integrations**, a new tab on the `acrossai-settings` host page |

Redirect runs on **`admin_page_access_denied`**, plus `admin_init` priority 1.

`admin_init` alone does not work, and this contract said it did. Core denies an unregistered `page`
argument in `wp-admin/includes/menu.php:384`, reached from the `require` at `wp-admin/admin.php:163` —
**before `do_action( 'admin_init' )` at `admin.php:180`.** Once the submenu was deleted the legacy URL
became exactly such a page, so no `admin_init` priority is early enough: the visitor saw "Sorry, you are
not allowed to access this page". Verified in a browser, not by tests — the unit test exercised
`redirect_target()`, which was correct all along; only the hook was wrong.
`admin_page_access_denied` fires at `menu.php:382`, immediately before that `wp_die()`.
`admin_init` priority 1 is kept for the case where something else still registers the legacy slug, so
access is never denied and the access hook never fires.

The target is always built from `admin_url()`; `tab` passes through `sanitize_key()`, non-scalar request
values are discarded before they reach it, and an unrecognised value falls back to "All" client-side.

## Stored data

| Withdrawn | Replacement |
|---|---|
| `acrossai_library_config` | override rows via the one-time translation; **retained un-deleted on multisite** (research.md R1) |
| `MAX_KEYS` / `MAX_SUB_KEYS = 50` | no cap — overrides are rows, not a capped map |

## PHP symbols

`AcrossAI_Ability_Library_Processor::is_permitted()` · `AcrossAI_Ability_Library_Config` (whole class,
after the translation reads it) · both `Modules/Library/Rest/` controllers · `LibraryMenu` ·
`Ability_Definition::is_all_enabled()` / `is_all_disabled()` / `bulk_toggle_state()` ·
`Admin\Main::is_library_page()`

**Retained deliberately**: `AcrossAI_Ability_Library_Registry` and `AcrossAI_Ability_Library_Processor`
(they register the whole ~451-ability catalogue), `Ability_Definition` normalisation (`tab_group` is now
load-bearing), `AcrossAI_Ability_Group`, and `AcrossAI_Integration_Ability_Base` + `ACF` (the opt-in is
real functionality).

## Assets

`src/js/ability-library/` (5 files) · `src/scss/ability-library/` · webpack entries `js/ability-library`
and `css/ability-library` · committed `build/{js,css}/ability-library*` · the ~451-row definitions payload
inlined at `admin/Main.php:294`.

Order: PHP include → webpack entry + sources → clean `build/`
(`PATTERN-ASSET-DECOMMISSION-ORDER`). Run an exhaustive `grep -rEn` over `includes/ src/ tests/ admin/`
for every symbol above **before** approving the removal list (`BUG-INVENTORY-GREP-MISS`).
