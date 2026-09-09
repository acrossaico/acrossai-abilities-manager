# Contract: Wizard Router, Step Registry & Guard Context

Client-side contract for `src/js/quick-connect/`. Ported from the sibling wizard — the rules below
encode bugs already paid for there and must survive the port intact.

---

## Step registry

| Step | Component | Skip predicate |
|---|---|---|
| `1` | `Step1_AbilitiesOverview` | never |
| `2` | `Step2_EditAbility` | never |
| `3` | `Step3_BulkActions` | never |
| `4` | `Step4_Integrations` | never |
| `5` | `Step5_ConnectTransport` | never |
| `6` | `Step6_AdapterInstall` | `skipAdapterInstall` |
| `7` | `Step7_AdapterAbilities` | `skipAdapterAbilities` |
| `done` | `Completion` | — |

```js
const STEP_ORDER = [ '1', '2', '3', '4', '5', '6', '7', 'done' ];

const skips = {
  // Alternative not chosen, OR chosen but already present (FR-011a).
  skipAdapterInstall:   method !== 'mcp-adapter' || plugins.mcpAdapter === 'active',
  skipAdapterAbilities: method !== 'mcp-adapter',
};
```

## Step-visibility table — single source of truth

One array drives `totalSteps`, `displayIndex`, and `shouldSkip`. Adding a step means adding **one
row plus one registry entry** — nothing else.

```js
const stepVisibilityTable = [
  { id: 1, skip: false },
  { id: 2, skip: false },
  { id: 3, skip: false },
  { id: 4, skip: false },
  { id: 5, skip: false },
  { id: 6, skip: skips.skipAdapterInstall },
  { id: 7, skip: skips.skipAdapterAbilities },
];

const totalSteps   = stepVisibilityTable.filter( r => ! r.skip ).length;
// displayIndex = ordinal among non-skipped rows; 'done' maps to totalSteps.
```

Resulting counts: **5** on the recommended path, **7** on the alternative path needing installation,
**6** when the alternative is already present.

---

## URL is the source of truth

| Param | Values | Default |
|---|---|---|
| `step` | `1`–`7`, `done` | `1` |
| `method` | `mcp-manager`, `mcp-adapter` | `mcp-manager` |

Read with `getQueryArg`, written with `addQueryArgs` + `history.pushState`.

### Three rules that must not be "simplified"

1. **The history write is synchronous and outside the `setState` updater.** Deferring it inside an
   updater caused Continue to require two clicks.
2. **The object returned by `useWizardRouter()` is `useMemo`'d.** A fresh literal per render caused a
   self-sustaining passive-effect update loop.
3. **A custom `acrossai-qc-nav` event is dispatched after every `pushState`/`replaceState`**, because
   native `popstate` does not fire for programmatic navigation and every mounted router instance
   must resync.

---

## Navigation is state-driven, never imperative

A step **must not** call `router.advance()` after performing an action. The required sequence:

```text
step mutates state ──> refetch() ──> skip predicate flips ──> auto-skip effect advances
```

Imperative advancement races the auto-skip effect and double-jumps. The only exceptions are the
Back/Continue controls owned by the shell, and the terminal `goTo('done')`.

**Auto-skip effect**: when the current step's skip predicate is true, call `advance({ skips })` so
chained skips resolve in one pass.

**Deep-link guard**: an unrecognised `step` value redirects to `1` and corrects the URL.

---

## Guard context

The shell owns Back and Continue; steps lift their needs into `WizardGuardContext`.

| Hook | Purpose |
|---|---|
| `useAdvanceGuard( canAdvance, beforeAdvance? )` | Enable/disable Continue; optional async pre-submit that cancels navigation on a falsy return |
| `useFooterAction( action \| action[] )` | Extra footer buttons: `{ label, onClick, isLoading, disabled, variant, href, target }` |
| `useHideContinue( bool )` | Hide Continue entirely on gate screens |
| `useWizardAdvance()` | Stable `advance()` bound to the shell's memoized `skips` |

Setter gotcha — `useState` treats a function value as an updater:

```js
setBeforeAdvance( () => beforeAdvance );
```

**Button demotion**: when a step registers a footer action, Continue renders as secondary so the
recommended combined action is visually primary.

**External links use `href` + `target="_blank"`, never `onClick` + `window.open`** — popup blockers
silently kill the latter, producing a dead button.

---

## Busy state

A single full-screen overlay, driven by `isLoading || footerActions.some( a => a.isLoading )`.
Never per-button spinners. While busy: all three controls are disabled, the overlay blocks pointer
events, and the pulsing brand icon is shown with `role="status"` / `aria-live="polite"`.

---

## Accessibility

- On step change: focus moves to the first focusable element inside `.qs__content`.
- A live region announces "Step %1$d of %2$d, %3$s" after a ~100 ms delay.
- The progress bar carries `role="progressbar"` with `aria-valuenow`/`min`/`max`.
- Full keyboard operability end to end (SC-007).
- **No `dangerouslySetInnerHTML` anywhere in the tree.**

---

## Client state shape

```js
{
  status: 'idle' | 'loading' | 'ready' | 'error',
  error: null | { message },
  abilities: { total: 0, tabGroups: [] },
  plugins: { mcpManager: 'missing', mcpAdapter: 'missing', mcpManagerWizardUrl: '' },
}
```

Fetch-only — no `saveStep`, no `complete`. Refetch on window focus, `visibilitychange`, and
`popstate`, so an install performed in another tab is detected without polling. After the first
successful hydrate, subsequent loading states show the overlay rather than unmounting the current
step (otherwise returning to the tab loses local state and can loop on gate screens).

---

## Testable pure helpers

Exported as named exports alongside the default component (`PATTERN-NAMED-EXPORT-JEST`):
`shouldSkip`, `computeSkips`, `computeTotalSteps`, `computeDisplayIndex`, `titleCaseTabLabel`.

`titleCaseTabLabel` must match the PHP `ucwords( str_replace( '-', ' ', $key ) )` rule
character-for-character — asserted by a paired Jest/PHPUnit fixture (R6).
