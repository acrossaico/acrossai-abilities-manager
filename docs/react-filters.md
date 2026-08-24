# React & PHP Extension Hooks

> **Status**: stable public contract as of Feature 034 (2026-06-14).
> **Source of truth**: [`specs/034-remove-allowed-servers-add-extensibility-hooks/contracts/extension-hooks.md`](../specs/034-remove-allowed-servers-add-extensibility-hooks/contracts/extension-hooks.md).
> **Last synced with source**: 2026-08-09.
>
> This file mirrors the authoritative contract into `docs/` for discoverability. If the two ever disagree, the file under `specs/034-*/contracts/` wins — open a PR to re-sync this document.

This plugin exposes **five extension hooks** — three React (JS-side) and two PHP-side — plus a public `window.acrossaiAbilitiesManager` global for cross-plugin data injection. Together they let a third-party plugin (or an mu-plugin) extend the ability edit form UI, mutate the REST payload before save, react to draft changes, inject localized JS data, and enqueue its own bundle at the correct moment in the admin lifecycle.

---

## Table of contents

- [Trust model](#trust-model)
- [React hooks](#react-hooks-wordpresshooks)
  - [`acrossai_abilities.form.extra_sections`](#filter-acrossai_abilitiesformextra_sections) — filter
  - [`acrossai_abilities.form.draft_changed`](#action-acrossai_abilitiesformdraft_changed) — action
  - [`acrossai_abilities.form.save_payload`](#filter-acrossai_abilitiesformsave_payload) — filter
- [PHP hooks](#php-hooks-wordpress-core)
  - [`acrossai_abilities_admin_localize_data`](#filter-acrossai_abilities_admin_localize_data) — filter
  - [`acrossai_abilities_form_settings_registered`](#action-acrossai_abilities_form_settings_registered) — action
- [`window.acrossaiAbilitiesManager` global](#windowacrossaiabilitiesmanager-global)
- [How to load your extension](#how-to-load-your-extension)
- [Smoke-test MU plugin](#smoke-test-mu-plugin)
- [Contract change procedure](#contract-change-procedure)

---

## Trust model

Extensions subscribing to these hooks execute **inside the WordPress admin trust boundary** — the same boundary as the host plugin itself. They run in the same PHP process (PHP hooks) and the same browser context (JS hooks) as `acrossai-abilities-manager`. A malicious or buggy extension can already do anything an installed WordPress plugin can do (modify the database, exfiltrate data, escalate privileges within WP). These hooks **do not expand that attack surface**, but they also **do not add any sandbox**.

What this plugin **does NOT** and **will NOT** add at the hook callsites:

- **Capability checks** (e.g. `current_user_can( 'manage_options' )`) — capability gating belongs at the REST/page boundary upstream, where it already runs. Adding it at filter callsites would falsely imply isolation that does not exist.
- **Nonce verification** — same reason.
- **Defensive coercion** of subscriber return values (e.g. type-checking the array returned from `acrossai_abilities.form.extra_sections`) — broken subscribers are the subscriber's bug.
- **Try/catch wrapping** of action callbacks — WordPress hooks are not exception-isolated by design; subscribers that throw will surface as unhandled errors. This is consistent with every other `do_action` callsite in WordPress.

Site owners install extensions at their own risk, the same as any other WordPress plugin. The supply-chain exposure of this hook surface is identical to *"I installed another WP plugin"*; it is not greater because of the hook contract.

---

## React hooks (`@wordpress/hooks`)

External plugins call `wp.hooks.addFilter(...)` / `wp.hooks.addAction(...)` against these names on the JS side. From inside this plugin's bundle, imports come from `@wordpress/hooks`.

### Filter: `acrossai_abilities.form.extra_sections`

**Purpose**: render extension-provided UI inside the ability edit form's Section 3 (MCP Exposure), where the removed Allowed Servers block used to live.

**Callsite**: `src/js/abilities/components/AbilityForm.jsx:1319`.

**Signature**:

```ts
applyFilters(
  'acrossai_abilities.form.extra_sections',
  sections: ReactNode[],          // initial value: []
  context: FormContext            // see below
): ReactNode[]
```

**Context object** (frozen — all four keys are public per Feature 034 FR-010):

```ts
interface FormContext {
  abilityId: string | null;       // ability id, or null when creating a new one
  slug:      string | null;       // ability slug, or null when creating
  draft:     Record<string, any>; // entire current form draft
  isNonDb:   boolean;             // true if the ability is registered in code only
}
```

**Subscriber usage**:

```js
import { addFilter } from '@wordpress/hooks';
import { createElement, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

addFilter(
  'acrossai_abilities.form.extra_sections',
  'my-plugin/rate-limit-section',  // subscriber namespace — must be plugin-slug/anything
  ( sections, context ) => {
    // Skip the Add-New screen so the panel only renders on saved abilities.
    if ( ! context.abilityId ) {
      return sections;
    }

    return [
      ...sections,
      createElement(
        'div',
        { className: 'sect', key: 'my-plugin-rate-limit' },
        createElement(
          'div',
          { className: 'sect-hdr' },
          __( 'Rate Limit', 'my-plugin' )
        )
        // …your custom fields here — you own the layout
      ),
    ];
  }
);
```

**Default behavior with zero subscribers**: returns `[]`; no extra sections render. Layout identical to baseline.

**Guarantees**:

- Returned array values are rendered with **React keys auto-assigned by index** (`<Fragment key={i}>`). Extensions returning React elements need not supply their own `key`, though it is good practice.
- Filter is called **on every form render**. Subscribers MUST be pure with respect to `context` — no imperative side effects inside the callback.
- Subscribers MUST return an array. Returning non-arrays is a subscriber bug; this plugin will not defensively coerce.

---

### Action: `acrossai_abilities.form.draft_changed`

**Purpose**: notify extensions whenever the form's draft state updates — mirror state, run validation, log activity, autosave, etc.

**Callsite**: `src/js/abilities/components/AbilityForm.jsx:318` (inside a `useEffect` with `[draftAbility]` as dependency).

**Signature**:

```ts
doAction( 'acrossai_abilities.form.draft_changed', draft: Record<string, any> ): void
```

**Firing cadence (contract)**: fires on **every React commit** where the `draft` state reference has changed. For typical text inputs, that means **per keystroke**; for grouped state updates batched into a single `setState`, that means once per batch. The plugin applies **no internal debouncing**. Debouncing is the subscriber's responsibility.

**Subscriber usage**:

```js
import { addAction } from '@wordpress/hooks';

let scheduled = null;
addAction(
  'acrossai_abilities.form.draft_changed',
  'my-plugin/mirror-state',
  ( draft ) => {
    clearTimeout( scheduled );
    scheduled = setTimeout( () => myStore.setDraft( draft ), 250 );
  }
);
```

**Default behavior with zero subscribers**: action call returns immediately with no observable effect.

**Guarantees**:

- The `draft` argument is the current draft object reference held by the form's React state. Subscribers **MUST NOT mutate it**. Treat as read-only.
- WordPress's hooks system does **NOT isolate subscriber exceptions**; a throwing subscriber will surface as an unhandled error in the form. Subscribers MUST handle their own failures.
- Fires once on initial mount (after the first `setDraftAbility`); then on every subsequent draft reference change; **not** on unmount.

---

### Filter: `acrossai_abilities.form.save_payload`

**Purpose**: allow extensions to mutate or augment the REST request body **immediately before save**.

**Callsites**: `src/js/abilities/components/AbilityForm.jsx:496` (create path) and `:532` (edit path). Fired once per save; the create-mode and edit-mode invocations carry different `context.abilityId` / `context.slug` values.

**Signature**:

```ts
applyFilters(
  'acrossai_abilities.form.save_payload',
  payload: Record<string, any>,   // base payload built by this plugin
  context: SaveContext            // see below
): Record<string, any>            // augmented payload, sent to REST as-is
```

**Context object**:

```ts
interface SaveContext {
  abilityId: string | null;
  slug:      string | null;
  isNonDb:   boolean;
}
```

**Subscriber usage**:

```js
import { addFilter } from '@wordpress/hooks';

addFilter(
  'acrossai_abilities.form.save_payload',
  'my-plugin/attach-rate-limit',
  ( payload, context ) => {
    if ( ! context.slug ) {
      return payload;  // create-mode; nothing to attach yet
    }
    return {
      ...payload,
      my_rate_limit: window.myPluginRateLimitStore.getState()[ context.slug ] ?? null,
    };
  }
);
```

**Default behavior with zero subscribers**: returns `payload` unchanged.

**Guarantees**:

- The filter runs **synchronously** before the REST POST / PUT. **Async subscribers (returning a Promise) are NOT supported** — the payload sent to the REST endpoint is whatever the filter returned synchronously.
- This plugin's REST controllers **WILL reject malformed payloads**. An extension that strips a required field is the extension's bug; this plugin's error handling is not extended to cover it.

**⚠️ Security note**: because `save_payload` runs before the REST call, a subscriber can **strip fields the server considers required** — a malicious or buggy subscriber could corrupt persisted state (e.g., drop `slug_suffix` to produce an entry with an empty slug). Reviewers integrating third-party extensions should audit the extension's `save_payload` subscriber the same way they'd audit a REST middleware.

---

## PHP hooks (WordPress core)

### Filter: `acrossai_abilities_admin_localize_data`

**Purpose**: allow extensions to add keys to the localized JS data object that the admin React bundle reads on mount.

**Callsite**: `admin/Main.php::enqueue_scripts()` (search for `apply_filters( 'acrossai_abilities_admin_localize_data'`).

**Signature**:

```php
$data = apply_filters( 'acrossai_abilities_admin_localize_data', array $data );
```

**Read endpoint on the JS side**: `window.acrossaiAbilitiesManager` (this name is part of the contract per FR-010). Extension subscribers append keys, then read them as `window.acrossaiAbilitiesManager.theirKey` from JS.

**Subscriber usage**:

```php
add_filter( 'acrossai_abilities_admin_localize_data', function ( array $data ): array {
    $data['my_plugin'] = array(
        'rate_limits' => \My_Plugin\get_rate_limits_for_current_user(),
    );
    return $data;
} );
```

Read it from React:

```js
const rateLimits = window.acrossaiAbilitiesManager?.my_plugin?.rate_limits ?? {};
```

**Default behavior with zero subscribers**: returns `$data` unchanged.

**⚠️ Security — data-minimization (SEC-002)**:

Values added to `$data` are serialized by `wp_json_encode` and injected as a JavaScript global on the admin page. They become **readable by every script running on that page**, including:

- Other admin plugins installed on the same site.
- Browser extensions running as content scripts.
- Any XSS payload that survives WordPress's escaping (a moving target — assume a payload can read your data).

Subscribers MUST data-minimize. Specifically, do **NOT** add:

- API keys, OAuth tokens, or any credentials (even short-lived).
- Hashed credentials, password hashes, or salts.
- Personally identifiable information beyond what is strictly required for UI rendering (e.g., display name is fine; full address or phone number is not).
- Database-internal IDs that the user shouldn't enumerate (e.g., raw `user_id` from another user, internal numeric primary keys that don't appear in the URL anyway).
- Bulk data that could be queried lazily on demand (e.g., the entire list of 10,000 abilities — fetch via REST when needed instead).

If you must expose something sensitive, **expose it via an authenticated REST endpoint instead** and have the React component fetch it on mount.

**Guarantees**:

- Filter fires inside `admin/Main::enqueue_scripts()` once per admin page load that includes the abilities bundle.
- The data is JSON-encoded via `wp_json_encode` and injected via `wp_add_inline_script( $handle, 'window.acrossaiAbilitiesManager = ...', 'before' )` — so it is available to the bundle **synchronously before any module-level code runs**.
- Extensions MUST **namespace their keys** (e.g., prefix with the extension's slug) to avoid collisions. Future versions of this plugin reserve the right to add new keys; collisions with extension-added keys would be the extension's responsibility to resolve.
- The base array shape this plugin owns (existing keys — see [Reserved keys](#windowacrossaiabilitiesmanager-global)) is governed by separate contracts — **do NOT modify those keys, only add new ones**.

---

### Action: `acrossai_abilities_form_settings_registered`

**Purpose**: signal that the abilities admin bundle has been enqueued and its localize data has been injected. Extensions hook here to enqueue their own dependent bundles.

**Callsite**: `admin/Main.php::enqueue_scripts()` (right after `wp_add_inline_script` for the localize data).

**Signature**:

```php
do_action( 'acrossai_abilities_form_settings_registered' );  // no arguments
```

**Subscriber usage**:

```php
add_action( 'acrossai_abilities_form_settings_registered', function (): void {
    wp_enqueue_script(
        'my-plugin/abilities-extension',
        plugins_url( 'build/abilities-extension.js', __FILE__ ),
        array( 'wp-hooks', 'wp-element' ),
        '1.0.0',
        true
    );
} );
```

**Default behavior with zero subscribers**: action returns immediately with no observable effect.

**Guarantees**:

- Action fires **after** `wp_enqueue_script` for the abilities admin bundle and **after** `wp_add_inline_script` for `window.acrossaiAbilitiesManager`. Extensions enqueueing here can rely on both being already wired.
- Action fires **exactly once per admin page load** that includes the abilities bundle. It does NOT fire on non-abilities admin pages.

---

## `window.acrossaiAbilitiesManager` global

**Status**: public contract per Feature 034 FR-010.

This window-level global is the **read endpoint** for any data injected by `acrossai_abilities_admin_localize_data` subscribers (and for this plugin's own existing keys).

### Reserved keys

Owned by `acrossai-abilities-manager` as of Feature 034 (subject to additive evolution — extensions MUST NOT use these names):

| Key | Type | Purpose |
|---|---|---|
| `nonce` | `string` | WordPress REST nonce for the current user (use as the `X-WP-Nonce` request header). |
| `rest_url` | `string` | Untrailingslashited site REST root (e.g. `https://example.com/wp-json`). |
| `rest_namespace` | `string` | This plugin's REST namespace prefix (`acrossai/v1`). |
| `current_user_id` | `number` | The currently logged-in WP user ID (rendering hint only — never trust client-side). |
| `perPage` | `number` | Page size for the abilities list. |
| `access_control_available` | `boolean` | Whether the `wpb-access-control` library is loaded — used as a client rendering gate only. Server authorization is independently enforced by `wpb-ac/v1` REST endpoints (SEC-018-02). |
| `access_control_slug` | `string` | Per-consumer AC slug (wpb-access-control v2+) — the React `<AccessControl>` component needs this to construct REST URLs like `/wpb-ac/v1/{slug}/providers` and `/wpb-ac/v1/{slug}/rules/...`. |
| `protected_slugs` | `string[]` | Ability slugs that cannot be deleted (per `DEC-PROTECTED-SLUGS-PATTERN`). |
| `mcp_manager_active` | `boolean` | Whether `acrossai-mcp-manager` is active on the current site (Feature 0.0.19 promo callout guard). |
| `mcp_manager_addons_url` | `string` | URL of the Add-ons page — used as the "Install from Add-ons" action in the MCP Manager promo callout. |
| `mcp_manager_info_url` | `string` | Marketing URL for the MCP Manager — used as the "Learn more" action in the promo callout. |

The set may grow in future minor releases without notice (additive only, per the [Contract change procedure](#contract-change-procedure) below).

### How extensions avoid collisions

1. **Prefix** keys with the extension's plugin slug or namespace (e.g. `my_plugin_state`, `acrossai_mcp_manager_servers`).
2. Prefer a **single namespaced object key** over many top-level keys — e.g. `my_plugin: { rate_limits: [...], settings: {...} }` over `my_plugin_rate_limits: [...]` + `my_plugin_settings: {...}`. Keeps the global flat namespace clean.
3. Do **NOT rely on iteration order** of `Object.keys( window.acrossaiAbilitiesManager )` — additions may appear anywhere.

If a new reserved key ever collides with a name an extension has shipped, the extension MUST rename theirs. The reserved-key list is governed by the FR-010 contract; extension-chosen names are not.

### Read pattern from extensions

```js
const myData = window.acrossaiAbilitiesManager?.[ 'my-extension-key' ] ?? {};
```

Use optional chaining and a default — extensions cannot assume their key is present (e.g., if a sibling extension prevented their `add_filter` from firing).

---

## How to load your extension

Every React hook uses `@wordpress/hooks` — WordPress's global hook registry. To make your subscriber run, you need your JS to enqueue **after** this plugin's bundle so `addFilter()` runs before `AbilityForm.jsx` first renders.

The canonical pattern (used by this plugin's own MCP Manager tab extension in `admin/Main.php::enqueue_scripts()` for the sibling `acrossai-mcp-manager` plugin):

```php
add_action( 'acrossai_abilities_form_settings_registered', function (): void {
    wp_register_script(
        'my-plugin/abilities-extension',
        plugins_url( 'build/js/my-extension.js', __FILE__ ),
        array(
            'acrossai-abilities-manager-abilities',  // <-- depend on this plugin's handle
            'wp-hooks',
            'wp-element',
        ),
        $asset_file['version'],
        true
    );
    wp_enqueue_script( 'my-plugin/abilities-extension' );
} );
```

The `acrossai-abilities-manager-abilities` handle is only registered when the admin is on the main Custom Abilities page (see `admin/Main.php::enqueue_scripts()`), so this enqueue is a **silent no-op on every other admin page** — no dev work required to scope your extension.

Once your script is registered, `addFilter( 'acrossai_abilities.form.extra_sections', ..., ... )` at module load time is enough — the plugin will find your subscriber on the next form render.

---

## Smoke-test MU plugin

Drop this file into `wp-content/mu-plugins/test-abilities-hooks.php` to verify every hook fires end-to-end. Delete the file when you're done to unsubscribe. Full source & verification checklist lives at [`specs/034-remove-allowed-servers-add-extensibility-hooks/quickstart.md`](../specs/034-remove-allowed-servers-add-extensibility-hooks/quickstart.md); condensed here for convenience.

```php
<?php
/**
 * Plugin Name: Test — AcrossAI Abilities Hooks
 * Description: Smoke-tests the 5 extension hooks. Drop into wp-content/mu-plugins/
 *              and load an ability edit page. Delete the file to unsubscribe.
 */

// PHP hook 1: localize-data filter — inject a probe key.
add_filter( 'acrossai_abilities_admin_localize_data', function ( array $data ): array {
    $data['_test_probe'] = 'hello from test MU plugin';
    return $data;
} );

// PHP hook 2: page-load action — record that it fired.
add_action( 'acrossai_abilities_form_settings_registered', function (): void {
    update_option( '_test_abilities_hook_fired', current_time( 'mysql' ) );
} );

// React hooks: register via inline JS attached to the abilities admin bundle.
add_action( 'admin_enqueue_scripts', function (): void {
    if ( ! wp_script_is( 'acrossai-abilities-manager-abilities', 'enqueued' ) ) {
        return;
    }
    wp_add_inline_script( 'acrossai-abilities-manager-abilities', <<<'JS'
( function () {
    if ( ! window.wp || ! window.wp.hooks || ! window.wp.element ) { return; }
    var hooks = window.wp.hooks;
    var el    = window.wp.element.createElement;

    // JS hook 1: extra_sections filter — inject a custom panel.
    hooks.addFilter(
        'acrossai_abilities.form.extra_sections',
        'test/extra-section',
        function ( sections, context ) {
            var probe = ( window.acrossaiAbilitiesManager
                && window.acrossaiAbilitiesManager._test_probe )
                || '(no probe)';
            return sections.concat( [
                el( 'div', {
                    id: 'test-probe-panel',
                    style: { padding: '8px', background: '#ffeecc', border: '1px solid #cc9' },
                },
                    'HELLO from test MU plugin. ',
                    'Ability slug: ' + ( context.slug || '(new)' ) + '. ',
                    'isNonDb: ' + String( context.isNonDb ) + '. ',
                    'Localize probe: ' + probe
                ),
            ] );
        }
    );

    // JS hook 2: draft_changed action — log every draft update.
    hooks.addAction(
        'acrossai_abilities.form.draft_changed',
        'test/draft-log',
        function ( draft ) {
            // eslint-disable-next-line no-console
            console.log( '[test draft_changed]', draft );
        }
    );

    // JS hook 3: save_payload filter — attach a probe to outbound saves.
    hooks.addFilter(
        'acrossai_abilities.form.save_payload',
        'test/save-probe',
        function ( payload, context ) {
            return Object.assign( {}, payload, {
                _test_save_probe: true,
                _test_ctx:        context,
            } );
        }
    );
}() );
JS
    );
}, 100 );
```

### Verification checklist (with the MU plugin enabled)

1. **PHP `acrossai_abilities_admin_localize_data`** — DevTools console: `window.acrossaiAbilitiesManager._test_probe` returns `'hello from test MU plugin'`.
2. **PHP `acrossai_abilities_form_settings_registered`** — `wp option get _test_abilities_hook_fired` returns a recent timestamp.
3. **JS `acrossai_abilities.form.extra_sections`** — a yellow-background `#test-probe-panel` div renders inside the ability edit form.
4. **JS `acrossai_abilities.form.draft_changed`** — typing in any field logs `[test draft_changed] {...}` on every keystroke.
5. **JS `acrossai_abilities.form.save_payload`** — Network tab shows the outbound REST body contains `_test_save_probe: true` and `_test_ctx: { abilityId, slug, isNonDb }`.
6. **No console errors, no PHP notices** with `WP_DEBUG` + `WP_DEBUG_LOG` on.

With the MU plugin disabled (delete the file), the panel disappears, no test keys appear in save payloads, no `_test_probe` on the global, and the form renders identically to baseline.

---

## Contract change procedure

If this contract needs to change after shipping:

1. **Add new** (hook name, context key, or window key): **non-breaking**. Ship in any release.
2. **Rename or remove**: must follow the FR-010 deprecation cycle:
   - Continue firing the old name **in parallel with the new for ≥ 1 minor release**.
   - For PHP action `*_form_settings_registered` and filter `*_admin_localize_data`, this means literally calling `do_action` / `apply_filters` for both names with the same payload.
   - For React hooks, call `applyFilters` / `doAction` for both names.
   - Document the deprecation in the release notes and in `docs/memory/DECISIONS.md`.
3. **Change semantics without renaming**: prefer **adding a new hook** with the new semantics and deprecating the old, rather than redefining the existing one.

---

## Related documentation

- **Authoritative contract**: [`specs/034-remove-allowed-servers-add-extensibility-hooks/contracts/extension-hooks.md`](../specs/034-remove-allowed-servers-add-extensibility-hooks/contracts/extension-hooks.md) — the source-of-truth spec-kit contract this document mirrors.
- **Quickstart with full smoke-test verification checklist**: [`specs/034-remove-allowed-servers-add-extensibility-hooks/quickstart.md`](../specs/034-remove-allowed-servers-add-extensibility-hooks/quickstart.md).
- **Design rationale + trade-offs**: [`specs/034-remove-allowed-servers-add-extensibility-hooks/spec.md`](../specs/034-remove-allowed-servers-add-extensibility-hooks/spec.md), [`plan.md`](../specs/034-remove-allowed-servers-add-extensibility-hooks/plan.md), [`research.md`](../specs/034-remove-allowed-servers-add-extensibility-hooks/research.md), [`security-constraints.md`](../specs/034-remove-allowed-servers-add-extensibility-hooks/security-constraints.md).
- **Architecture notes** (Section 3 layout, save-payload security, JS-hooks naming convention): [`docs/memory/ARCHITECTURE.md`](memory/ARCHITECTURE.md).
- **Pre-speckit planning doc**: [`docs/planning/034-remove-allowed-servers-add-extensibility-hooks.md`](planning/034-remove-allowed-servers-add-extensibility-hooks.md).

---

**Maintaining this file**: on every change to the source-of-truth contract at `specs/034-*/contracts/extension-hooks.md`, this document must be re-synced. Any change to `admin/Main.php::enqueue_scripts()` that adds a new key to `window.acrossaiAbilitiesManager` must update the Reserved keys table above.
