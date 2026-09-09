# Planning: Quick Connect Onboarding Wizard (Feature 099)

Add a full-screen React onboarding wizard to AcrossAI Abilities Manager, modelled directly on
the Quick Connect wizard in the sibling `acrossai-mcp-manager` plugin. The wizard opens
automatically after activation (only when AcrossAI MCP Manager is not already installed and
active), teaches the plugin in four screens, then connects a transport — either installing
AcrossAI MCP Manager in place and handing off to its own wizard, or guiding a manual MCP
Adapter install.

Visual parity with `acrossai-mcp-manager` is a hard requirement: same logo, same pulsing load
effect, same tokens, same `qs__*` class vocabulary.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "099-quick-connect-onboarding-wizard"

# 2. Specify
/speckit.specify "Add a Quick Connect onboarding wizard to AcrossAI Abilities Manager, ported from the
Quick Connect wizard in the sibling acrossai-mcp-manager plugin (admin/Partials/QuickConnect/**,
src/js/quick-connect/**, src/scss/quick-connect.scss, includes/REST/QuickConnectController.php).

Visual parity is a hard requirement. Reuse the AcrossAI logo assets already present at
assets/quick-setup/ (acrossai-logo.svg 1793 B, icon.svg 1810 B) which are byte-identical to the
sibling's assets/quick-connect/ — rename the directory to assets/quick-connect/ and commit it (it is
currently untracked). acrossai-logo.svg is the 28px header logo and the gate-card mark; icon.svg is the
96px pulsing loading/busy icon using the qs__initial-loading-pulse keyframes (1.6s ease-in-out infinite,
opacity 0.65 to 1, transform scale 0.96 to 1) with the qs__initial-loading--overlay variant
(rgba(255,255,255,0.92) backdrop, blur(2px), z-index 999999, 150ms fade-in).

Seven steps plus a completion screen:
(1) Abilities overview - big count of registered abilities,
(2) How to edit an ability - YouTube video,
(3) Bulk actions - same video,
(4) Integrations - read-only showcase grid of Library tab groups with per-group ability counts,
(5) Connect a transport - two RadioCards, AcrossAI MCP Manager (RECOMMENDED badge, selected by default)
and MCP Adapter, footer buttons Back and 'Continue - Install the plugin',
(6) MCP Adapter install instructions - GitHub download/install guidance, skipped unless method=mcp-adapter,
(7) Enable abilities for the MCP Adapter server - same video, skipped unless method=mcp-adapter,
(done) Completion summary.

Video for steps 2, 3 and 7: https://www.youtube-nocookie.com/embed/6nDuURDNmLc?list=PLL-i34ne1J0c&rel=0
in the sibling's responsive 16:9 wrapper, behind a click-to-load facade with referrerpolicy=no-referrer
and loading=lazy, plus a persistent external link (DEC-ADMIN-THIRD-PARTY-EMBED). Do not autoplay -
three autoplaying copies of one video in a single flow is worse than a click.

Step 5 branches: choosing MCP Manager posts to the install-plugin REST route (slug
acrossai-mcp-manager, which is on wordpress.org) and on success redirects to
admin.php?page=acrossai_mcp_manager&quick-connect=1&step=1&server=1 to hand off to MCP Manager's own
wizard. Choosing MCP Adapter advances to steps 6 and 7 - MCP Adapter is GitHub-only
(https://github.com/WordPress/mcp-adapter), so it cannot be installed via plugins_api/Plugin_Upgrader and
must be instructions plus a reload check. If MCP Manager is already active, step 5 renders an
'already connected' state and Continue goes to done. The dependency messaging 'AcrossAI Abilities Manager
works with MCP Adapter and AcrossAI MCP Manager' is the step 5 subtitle, deliberately not the first screen.

Activation redirect: AcrossAI_Activator::activate() sets a 30-second transient
acrossai_abilities_quick_connect_do_redirect; a new ActivationRedirect singleton on admin_init priority 5
consumes it with guards in this order - transient present, delete transient first for idempotency, skip
if activate-multi, skip if network admin, require manage_options, and require that AcrossAI MCP Manager is
NOT active and NOT installed. Registered after the existing priority-1 vendor-guard activation callback in
acrossai-abilities-manager.php.

The wizard is a query-param hijack of the existing manager page, not a new admin page: add a
?quick-connect=1 branch to the render callback in admin/Partials/Menu.php delegating to
QuickConnectPage::render(), exactly as acrossai-mcp-manager/admin/Partials/Settings.php:566 does. Add a
Quick Connect submenu entry (URL-literal menu_slug, empty callback, position 0), an admin-bar node at
admin_bar_menu priority 100, and a plugin_action_links entry so the wizard is re-runnable. QuickConnectPage
owns its own asset enqueue following admin/Partials/File_Manager_Settings_Menu.php, gated on
$_GET['quick-connect'] === '1' and never on the hook suffix. It also adds body class
acrossai-quick-connect-fullpage and suppresses admin notices via remove_all_actions() on the four notice
hooks at in_admin_header priority 1000.

Two REST routes only, in the existing acrossai/v1 namespace under a /quick-connect prefix, reusing
AcrossAI_Abilities_Rest_Controller::check_permission (manage_options plus explicit X-WP-Nonce):
GET /quick-connect/state returning ability totals, Library tab-group counts, and MCP plugin states; and
POST /quick-connect/install-plugin behind install_plugins AND activate_plugins, a port of the sibling's
handle_install_plugin with the same slug whitelist (acrossai-mcp-manager only), plugins_api then
Plugin_Upgrader then activate_plugin, and the same error hygiene where raw upgrader messages go to
error_log() only and users see hand-authored WP_Error strings with no file paths. Do NOT port the
sibling's per-user scratchpad transient, /step or /complete routes - steps 1 to 4 are read-only and
step 5's choice lives in the URL as ?method=, so there is no server-side wizard state.

Ability total is count(wp_get_abilities()) minus the three protected slugs via
AcrossAI_Protected_Abilities::is_protected(). Tab-group counts come from
AcrossAI_Ability_Library_Registry::instance()->get_definitions() grouped by each definition's tab_group,
labelled with the existing ucwords(str_replace('-', ' ', ...)) rule - these are exactly the tabs on the
Integrations page.

MCP plugin detection is new: a helper returning missing|inactive|active for AcrossAI MCP Manager
(basename acrossai-mcp-manager/acrossai-mcp-manager.php, already the convention at admin/Main.php:242) and
for MCP Adapter (candidate basename mcp-adapter/mcp-adapter.php plus a class_exists probe, because the
adapter can arrive vendored inside another plugin rather than standalone).

Port the React shell from the sibling rather than reinventing it: useWizardRouter (URL is the source of
truth; keep the synchronous history write outside the setState updater and the useMemo on the returned
object - both fix documented bugs), useAdvanceGuard with its guard context, StepLayout with the sticky
progress bar and single full-screen busy overlay, and the stepVisibilityTable pattern where one array
drives totalSteps, displayIndex and shouldSkip. Navigation must stay state-driven - steps never call
router.advance() imperatively. No dangerouslySetInnerHTML anywhere.

Add webpack entries js/quick-connect and css/quick-connect following this plugin's convention of
registering SCSS as its own entry (not importing it from the JS entry as the sibling does)."
```

---

## Visual Parity Requirement

This is the part most likely to be got wrong, so it is specified explicitly.

### Logo assets — already present, byte-identical

| File | Size | Status |
|------|------|--------|
| `assets/quick-setup/acrossai-logo.svg` | 1793 B | byte-identical to `acrossai-mcp-manager/assets/quick-connect/acrossai-logo.svg` |
| `assets/quick-setup/icon.svg` | 1810 B | byte-identical to `acrossai-mcp-manager/assets/quick-connect/icon.svg` |

Both are 100×100 viewBox SVGs with a `linearGradient id="coreGrad"` (#1E3A8A → #2563EB), an
`#EFF6FF` circle with `#1E40AF` stroke, and radiating node/spoke lines.

**Required actions**:
1. Rename the directory `assets/quick-setup/` → `assets/quick-connect/`. The sibling deliberately
   retired the `quick-setup` name and enforces it with a rename canary test; use `quick-connect`
   naming everywhere here too.
2. `git add` the directory — it is currently **untracked**. `.distignore` does not exclude
   `/assets`, so it ships in the plugin zip.
3. Do **not** regenerate, re-export, or restyle the SVGs. Byte-identical is the requirement.

### Logo usage and effect

| Asset | Bootstrap key | Where | Styling |
|-------|---------------|-------|---------|
| `acrossai-logo.svg` | `logoUrl` | `StepLayout` header | `.qs__header-logo` — `height: 28px`, `width: auto`, `flex: none`; `24px` below 640px |
| `acrossai-logo.svg` | `logoUrl` | gate-card mark on step screens | `.qs__gate-card__logo` |
| `icon.svg` | `iconUrl` | cold-start loader and mid-flow busy overlay | `.qs__initial-loading-icon` — `96px`, `max-width: 25vw`, `max-height: 25vh` |

The pulsing effect must be reproduced exactly:

```scss
.qs__initial-loading-icon {
	width: 96px;
	height: 96px;
	max-width: 25vw;
	max-height: 25vh;
	animation: qs__initial-loading-pulse 1.6s ease-in-out infinite;
}

@keyframes qs__initial-loading-pulse {
	0%, 100% { opacity: 0.65; transform: scale(0.96); }
	50%      { opacity: 1;    transform: scale(1); }
}

.qs__initial-loading {
	position: fixed;
	inset: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	background: #ffffff;
	z-index: 99999;
}

.qs__initial-loading--overlay {
	background: rgba(255, 255, 255, 0.92);
	backdrop-filter: blur(2px);
	z-index: 999999; // above the WP admin bar
	animation: qs__initial-loading-fade-in 150ms ease-out;
}

@keyframes qs__initial-loading-fade-in {
	from { opacity: 0; }
	to   { opacity: 1; }
}
```

Two states use it, exactly as in the sibling:
- **Cold start** — `.qs__initial-loading` while the first `GET /state` is in flight.
- **Mid-flow busy** — `.qs__initial-loading--overlay` whenever `footerAction.isLoading` is true
  (for example the MCP Manager install on step 5). One shared overlay, never per-button spinners.

### Design tokens — port verbatim

```scss
$qs-primary:        #3858e9;   $qs-primary-hover:  #2f4dc7;
$qs-brand-purple:   #4f46e5;   $qs-success:        #4ab866;
$qs-warning:        #f0b849;   $qs-danger:         #cc1818;
$qs-text:           #1e1e1e;   $qs-text-muted:     #757575;
$qs-border:         #ddd;      $qs-border-strong:  #949494;
$qs-bg-blue-tint:   #f0f4ff;   $qs-bg-purple-tint: #f5f3ff;
$qs-bg-warning:     #fcf9e8;   $qs-bg-success:     #edfaef;
$qs-bg-code:        #f6f7f7;   $qs-bg-code-dark:   #23282d;
$qs-radius:         2px;       $qs-space:          8px;
$qs-font-mono:      'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
$qs-font-body:      -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
```

Also carry over unchanged:
- **Two-tone wordmark** — `.qs__brand-mark__across { color: #1e3a8a; }`,
  `.qs__brand-mark__ai { color: #3858e9; }`, plus the `--inline` modifier used with
  `createInterpolateElement` so a heading reads "Install **AcrossAI** MCP Manager" as one sentence
  rather than three concatenated substrings.
- **Header grammar** — flex row, `padding: 22px 40px` (`16px 20px` below 640px), 28px logo, 13px
  muted title, the outlined `Free Consultations ↗` pill linking to `https://acrossai.co/consultations/`,
  and the `Exit setup` link (hidden on `done`).
- **Layout** — 3px sticky progress bar (`top: 0`, `z-index: 10`, `0.25s ease` width transition),
  centered `max-width: 720px` content column with `48px 24px 40px` padding, centered 28px step titles
  (22px mobile), footer with `min-width: 200px` buttons that become `column-reverse` and full-width
  below 640px.
- **Full-page takeover body rules** — hide `#wpadminbar`, `#adminmenumain`, `#adminmenuback`,
  `#adminmenuwrap`, `#wpfooter`, `#wp-toolbar`, `#screen-meta`, `#screen-meta-links`; zero the
  `#wpcontent` / `#wpbody-content` margins and padding; include the
  `html.wp-toolbar:has(body.acrossai-quick-connect-fullpage)` rule.
- **Class vocabulary** — `qs__progress`/`-fill`, `qs__header*`, `qs__content`, `qs__step-title`,
  `qs__step-subtitle`, `qs__footer`, `qs__sr-only`, `qs-btn`/`--secondary`/`--link`,
  `qs-notice`/`--info|warning|success|error`, `qs-card`/`--selected`/`__radio`/`__body`/`__title`/
  `__subtitle`/`__badge`, `qs__gate-card`/`__logo`/`__stat`, `qs__gate-bullets`/`--grid`,
  `qs__gate-actions`, `qs__pro-card--video`/`__video`.

The **only** intended divergence is the scope class: `.acrossai-quick-connect-wrap` and body class
`acrossai-quick-connect-fullpage` (the sibling uses `.acrossai-mcp-quick-connect-wrap` /
`acrossai-mcp-quick-connect-fullpage`). Everything inside stays identical.

---

## Step Flow

| # | Screen | Content | Skip predicate |
|---|--------|---------|----------------|
| 1 | Abilities overview | "This site has **N** abilities ready to use" — headline stat in a `qs__gate-card` | never |
| 2 | Editing abilities | Video + copy on editing an individual ability | never |
| 3 | Bulk actions | Same video + copy on bulk enable/disable | never |
| 4 | Integrations | Read-only showcase grid of Library tab groups with counts | never |
| 5 | Connect a transport | Two `RadioCard`s — MCP Manager (`RECOMMENDED`, default) / MCP Adapter | never |
| 6 | MCP Adapter install | GitHub download + install instructions, "I installed it — reload" | `method !== 'mcp-adapter'` |
| 7 | Adapter abilities | Same video + copy on enabling abilities for the adapter server | `method !== 'mcp-adapter'` |
| done | Completion | Summary + CTAs (Go to Abilities, Go to Integrations, Exit) | — |

Reference tab-group counts for step 4 (from `tab_group` declarations in `includes/Abilities/`):
Core 105, Blocks 79, Elementor 63, File Manager 23, Database 18, Users 16, Cron 16, Comments 12,
Media 11, Content Search 11, Plugins 10, Themes 7, Debugging 7, Cache 7, Site Health 3, Widgets 2,
Settings 1. Compute these at runtime — do not hardcode; Elementor/RankMath groups only appear when
those plugins are active, and `acrossai-pro` contributes MailerPress groups.

---

## CHANGE-1 — Assets

Rename `assets/quick-setup/` → `assets/quick-connect/` and `git add` it. No file contents change.

---

## CHANGE-2 — Activation Redirect

**Files**: `includes/AcrossAI_Activator.php`, `admin/Partials/QuickConnect/ActivationRedirect.php` (new)

`AcrossAI_Activator::activate()` gains:

```php
set_transient( 'acrossai_abilities_quick_connect_do_redirect', '1', 30 );
```

30-second TTL is deliberate — a WP-CLI activation with no following admin request lets it expire
instead of ambushing the operator hours later.

`ActivationRedirect::maybe_redirect()` on `admin_init` priority 5, guards in order:

1. transient present, else return
2. **delete the transient first** — idempotency
3. skip if `! empty( $_GET['activate-multi'] )`
4. skip if `is_network_admin()`
5. require `current_user_can( 'manage_options' )`
6. **require AcrossAI MCP Manager not active and not installed**
7. `wp_safe_redirect( admin_url( 'admin.php?page=acrossai-abilities-manager&quick-connect=1&step=1' ) ); exit;`

Guard 6 is the confirmed product requirement: sites that already run MCP Manager are not
interrupted. The wizard stays reachable manually.

---

## CHANGE-3 — Page Hijack and Discoverability

**Files**: `admin/Partials/Menu.php`, `admin/Partials/QuickConnect/QuickConnectPage.php` (new),
`admin/Partials/QuickConnect/AdminBarEntry.php` (new), `admin/Main.php`

`Menu.php`'s render callback gains a `?quick-connect=1` branch delegating to
`QuickConnectPage::instance()->render()`, mirroring
`acrossai-mcp-manager/admin/Partials/Settings.php:566`. No new admin page and no new capability
surface.

`QuickConnectPage::render()` emits only the mount point:

```php
echo '<div class="wrap acrossai-quick-connect-wrap">';
echo '<div id="acrossai-quick-connect-root"></div>';
echo '<noscript><p>' . esc_html__( 'The Quick Connect wizard requires JavaScript. Enable JavaScript in your browser to use it.', 'acrossai-abilities-manager' ) . '</p></noscript>';
echo '</div>';
```

The class also owns `enqueue_assets()`, `add_body_class()` and `suppress_admin_notices()`
(`remove_all_actions()` on `admin_notices`, `all_admin_notices`, `user_admin_notices`,
`network_admin_notices` at `in_admin_header` priority 1000). **Every gate is
`$_GET['quick-connect'] === '1'`, never the hook suffix** — hook suffixes are fragile on this
parent menu (see the `BUG-LIBRARY-HOOK-SUFFIX` note at `admin/Main.php:344`). Follow
`admin/Partials/File_Manager_Settings_Menu.php` as the self-enqueueing page-class precedent rather
than adding a fourth branch to `admin/Main.php::enqueue_scripts()`.

Discoverability: a `Quick Connect` submenu entry (URL-literal `menu_slug`, empty callback,
position 0), an `AdminBarEntry` node at `admin_bar_menu` priority 100, and a `plugin_action_links`
entry alongside the existing Settings link at `admin/Main.php:421`.

Localized via `wp_localize_script( 'acrossai-quick-connect', 'acrossaiQuickConnect', … )`:
`restUrl`, `restNonce`, `adminUrl`, `integrationsUrl`, `pluginInstallUrl`, `logoUrl`, `iconUrl`,
`mcpAdapterRepoUrl`, `mcpManagerWizardUrl`.

---

## CHANGE-4 — REST API

**Files**: `includes/Modules/QuickConnect/Rest/AcrossAI_Quick_Connect_Rest_Controller.php` (new),
`includes/Modules/QuickConnect/AcrossAI_Mcp_Plugin_Detector.php` (new), `includes/Main.php`

Singleton, does not extend `WP_REST_Controller` (matches `SC-027-05`). Namespace `acrossai/v1`,
prefix `/quick-connect`. Reuses `AcrossAI_Abilities_Rest_Controller::instance()->check_permission`
(`manage_options` + explicit `X-WP-Nonce`).

| Route | Method | Permission | Returns |
|-------|--------|------------|---------|
| `/acrossai/v1/quick-connect/state` | GET | shared `check_permission` | `{ abilities: { total, tabGroups: [{ key, label, count }] }, plugins: { mcpManager, mcpAdapter, mcpManagerWizardUrl } }` |
| `/acrossai/v1/quick-connect/install-plugin` | POST | `install_plugins` **AND** `activate_plugins` | `{ installed, active, plugin }` |

**Deliberately not ported**: the sibling's per-user scratchpad transient, `/step` and `/complete`.
The sibling needs them because it creates servers and saves access-control rules; here steps 1–4
are read-only and step 5's choice lives in the URL as `?method=`. No server-side wizard state means
nothing new in `uninstall.php`.

`install-plugin` is a port of
`acrossai-mcp-manager/includes/REST/QuickConnectController::handle_install_plugin()`:
slug whitelist (`acrossai-mcp-manager` only, 400 otherwise) → `plugins_api( 'plugin_information' )`
→ `Plugin_Upgrader` with `WP_Ajax_Upgrader_Skin` → `activate_plugin()`. Keep the **error hygiene**:
raw upgrader messages go to `error_log()` only; users see hand-authored `WP_Error` strings with no
file paths or vendor strings.

Counting for `/state`:

```php
// Total — excludes the three protected mcp-adapter slugs, matching the plugin's own UI.
$total = 0;
foreach ( wp_get_abilities() as $slug => $ability ) {
	if ( ! AcrossAI_Protected_Abilities::is_protected( $slug ) ) {
		++$total;
	}
}

// Tab groups — the Integrations page tabs.
$counts = array();
foreach ( AcrossAI_Ability_Library_Registry::instance()->get_definitions() as $definition ) {
	if ( empty( $definition['tab_group'] ) ) {
		continue;
	}
	$counts[ $definition['tab_group'] ] = ( $counts[ $definition['tab_group'] ] ?? 0 ) + 1;
}
```

Label with the existing `ucwords( str_replace( '-', ' ', $key ) )` rule. `get_definitions()` is safe
after `init` priority 99.

`AcrossAI_Mcp_Plugin_Detector` returns `'missing' | 'inactive' | 'active'`:
- MCP Manager — basename `acrossai-mcp-manager/acrossai-mcp-manager.php` (the convention already
  used at `admin/Main.php:242`).
- MCP Adapter — candidate basename `mcp-adapter/mcp-adapter.php` **plus** a `class_exists()` probe,
  because the adapter may be vendored inside another plugin rather than installed standalone. No
  detection for it exists anywhere in the plugin today.

All hook wiring goes in `includes/Main.php` using the variable-first pattern (`AC-HOOKS-MAIN`):
every `add_action` must trace back to `Main.php`.

---

## CHANGE-5 — React Wizard

**Files**: `src/js/quick-connect/**` (new), `src/scss/quick-connect/admin.scss` (new),
`webpack.config.js`

```text
src/js/quick-connect/
  index.js                    createRoot + apiFetch nonce middleware (no createRootURLMiddleware)
  App.jsx                     stepRegistry, skips, stepVisibilityTable, guard context
  StepLayout.jsx              progress bar, header, footer, busy overlay, a11y live region
  hooks/useWizardRouter.js    port verbatim
  hooks/useWizardState.js     reduced to fetch-only
  hooks/useAdvanceGuard.js    port verbatim
  components/{RadioCard,Notice,VideoEmbed,icons}.jsx
  steps/Step1_AbilitiesOverview.jsx … Step7_AdapterAbilities.jsx, Completion.jsx
```

Patterns that must survive the port — each fixes a bug already paid for in the sibling:

1. **`stepVisibilityTable`** — one array drives `totalSteps`, `displayIndex` and `shouldSkip`, so
   users see "Step 5 of 5" rather than raw step ids. Adding a step means one row plus one registry
   entry.
2. **State-driven navigation** — steps never call `router.advance()` imperatively. They mutate
   state, refetch, the skip predicate flips, and the auto-skip effect moves the user. Four separate
   comments in the sibling document double-jump races caused by doing otherwise.
3. **`useWizardRouter` rules** — the history write must be synchronous and outside the `setState`
   updater (otherwise Continue needs two clicks), and the returned object must be `useMemo`'d
   (otherwise a self-sustaining passive-effect update loop).
4. **One full-screen busy overlay** driven by `footerAction.isLoading`; never per-button spinners.
5. **No `dangerouslySetInnerHTML`** anywhere in the tree.

Wizard state is thin: `method` (`mcp-manager` | `mcp-adapter`) lives in the URL as `?method=`,
defaulting to `mcp-manager` so the recommended card is pre-selected. Skip predicates reduce to
`skipAdapterInstall` / `skipAdapterAbilities`, both `method !== 'mcp-adapter'`.

Video embed for steps 2, 3 and 7:

```jsx
const WALKTHROUGH_EMBED =
	'https://www.youtube-nocookie.com/embed/6nDuURDNmLc?list=PLL-i34ne1J0c&rel=0';
// Privacy host + click-to-load facade + referrerpolicy="no-referrer" + loading="lazy"
// are mandatory per DEC-ADMIN-THIRD-PARTY-EMBED (Active). See tasks T047-T049.
```

in the sibling's responsive 16:9 wrapper (`.qs__pro-card--video` / `.qs__pro-card__video`,
`padding-top: 56.25%` with an absolutely-positioned iframe). Omit `autoplay` — the sibling
autoplays because it shows one video once; three autoplaying copies of the same video in one flow
is worse than a click.

`StepLayout` must carry over the sibling's header markup unchanged (logo `<img src={logoUrl}>`,
muted title, `Free Consultations ↗` pill, `Exit setup`) and the `icon.svg` busy overlay — the
ported styling is meaningless without the matching DOM.

Webpack: add `'js/quick-connect' → src/js/quick-connect/index.js` and
`'css/quick-connect' → src/scss/quick-connect/admin.scss`. This plugin registers SCSS as its own
entry; do **not** copy the sibling's pattern of importing SCSS from the JS entry.

---

## What Must NOT Change

- Do not restyle, re-export, or regenerate the two AcrossAI SVGs — byte-identical is the requirement.
- Do not diverge from the sibling's tokens, header layout, progress bar, button styling, or
  `qs__*` class names. The only intended difference is the outer scope class.
- Do not gate wizard assets on the hook suffix; use `$_GET['quick-connect']`.
- Do not create a new top-level admin page — the wizard is a query-param branch of the existing
  manager page.
- Do not add a scratchpad transient, `/step`, or `/complete` routes.
- Do not add `acrossai-pro`, `mcp-adapter`, or any other slug to the install-plugin whitelist.
- Do not surface raw `Plugin_Upgrader` / `plugins_api` error text to the client.
- Do not auto-redirect on activation when AcrossAI MCP Manager is already installed and active.
- Do not change existing REST endpoint paths or response shapes.
- Do not register hooks outside `includes/Main.php` (`AC-HOOKS-MAIN`).

---

## Expected Files Changed

```text
assets/quick-connect/acrossai-logo.svg              (renamed from assets/quick-setup/, git add)
assets/quick-connect/icon.svg                       (renamed from assets/quick-setup/, git add)
admin/Partials/QuickConnect/QuickConnectPage.php    (new)
admin/Partials/QuickConnect/ActivationRedirect.php  (new)
admin/Partials/QuickConnect/AdminBarEntry.php       (new)
includes/Modules/QuickConnect/AcrossAI_Mcp_Plugin_Detector.php            (new)
includes/Modules/QuickConnect/Rest/AcrossAI_Quick_Connect_Rest_Controller.php (new)
src/js/quick-connect/index.js                       (new)
src/js/quick-connect/App.jsx                        (new)
src/js/quick-connect/StepLayout.jsx                 (new)
src/js/quick-connect/hooks/{useWizardRouter,useWizardState,useAdvanceGuard}.js (new)
src/js/quick-connect/components/{RadioCard,Notice,VideoEmbed,icons}.jsx        (new)
src/js/quick-connect/steps/*.jsx                    (new, 8 files)
src/scss/quick-connect/admin.scss                   (new)
admin/Partials/Menu.php
admin/Main.php
includes/AcrossAI_Activator.php
includes/Main.php
webpack.config.js
tests/phpunit/Admin/QuickConnect/*.php              (new)
tests/phpunit/Modules/QuickConnect/*.php            (new)
```

---

## Validation Checklist

### Visual parity

- [ ] `diff assets/quick-connect/acrossai-logo.svg ../acrossai-mcp-manager/assets/quick-connect/acrossai-logo.svg` is clean.
- [ ] `diff assets/quick-connect/icon.svg ../acrossai-mcp-manager/assets/quick-connect/icon.svg` is clean.
- [ ] `assets/quick-connect/` is tracked in git.
- [ ] Header logo renders at 28px (24px below 640px) from `acrossai-logo.svg`.
- [ ] Cold start shows the 96px `icon.svg` pulsing at `1.6s ease-in-out infinite`, opacity 0.65→1, scale 0.96→1.
- [ ] Clicking `Continue - Install the plugin` shows the translucent overlay variant (blur, 150ms fade-in) and locks all buttons.
- [ ] All `$qs-*` tokens match the sibling's values exactly.
- [ ] Side-by-side against `admin.php?page=acrossai_mcp_manager&quick-connect=1&step=1`: header, progress bar, typography, buttons, cards and notices are indistinguishable apart from step content — at desktop and below 640px.

### Wizard behaviour

- [ ] Deactivate MCP Manager, deactivate + reactivate abilities-manager → lands on step 1, WP chrome hidden, no stray admin notices.
- [ ] Reactivate with MCP Manager active → no redirect; wizard still reachable via admin bar, submenu and plugins.php link.
- [ ] Step 1 count matches the Abilities page total.
- [ ] Step 4 groups and counts match the Integrations page tabs.
- [ ] Videos play on steps 2, 3 and 7; none autoplay.
- [ ] Step 5 with MCP Manager missing → installs, activates, then lands on MCP Manager's Quick Connect step 1.
- [ ] Step 5 → MCP Adapter → step 6 → step 7 → `done`.
- [ ] Step 5 with MCP Manager already active → "already connected" state; Continue goes to `done`.
- [ ] Deep link `?quick-connect=1&step=6` with no `method` auto-skips forward; browser Back/Forward stay in sync.
- [ ] Progress reads "N of 5" on the manager path and "N of 7" on the adapter path.

### Security

- [ ] Editor role → both REST routes 403; wizard page not reachable.
- [ ] `install-plugin` with any slug other than `acrossai-mcp-manager` → 400.
- [ ] `install-plugin` without `install_plugins` or `activate_plugins` → 403.
- [ ] No raw upgrader/`plugins_api` text reaches the client; failures land in `error_log()`.
- [ ] No `dangerouslySetInnerHTML` in `src/js/quick-connect/`.

### Quality gates

- [ ] `npm run build` emits `build/js/quick-connect.js`, `.asset.php` and `build/css/quick-connect.css`.
- [ ] The wizard bundle does **not** load on the abilities table, settings, or Integrations pages.
- [ ] `composer run phpstan` passes.
- [ ] PHPCS on changed production PHP files introduces no new errors.
- [ ] PHPUnit: `ActivationRedirect` guard matrix (each guard independently blocks), `QuickConnectPage` render/gating, REST slug whitelist and capability checks.

---

## Spec-kit Commands

```markdown
# 3. Plan + guard + security
/speckit.memory-md.plan-with-memory
/speckit.architecture-guard.governed-plan
/speckit.security-review.plan

# 4. Tasks + guard
/speckit.tasks
/speckit.architecture-guard.governed-tasks

# 5. Implement + quality checks
/speckit.architecture-guard.governed-implement
composer run phpstan
composer run phpcs
npm run build

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```
