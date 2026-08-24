# Ability Contract — `acrossai/conflict-test-bulk-set-overrides`

**Class**: `AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\Bulk_Set_Overrides`
**Auto-exposed REST route**: `POST /wp-json/wp-abilities/v1/abilities/acrossai/conflict-test-bulk-set-overrides/run`
**Annotations**: `readonly: false`, `idempotent: true`, `destructive: false`

## Purpose

Set the effective active state of many plugins in a single operation. Best-effort with a per-plugin report — unknowns and fatals don't abort the call. Does **not** cascade — caller controls the exact list.

## Permission

`current_user_can( 'manage_options' )`.

## Input (JSON body)

```json
{
  "plugin_files": [
    "hello-dolly/hello.php",
    "akismet/akismet.php",
    "wp-super-cache/wp-cache.php"
  ],
  "active": false
}
```

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| `plugin_files` | `array<string>` | yes | — | Every element is a plugin file identifier. Duplicates allowed but treated as one entry (the second write is a no-op because the first already set the state). Empty array returns a valid response with three empty result arrays. |
| `active` | `boolean` | yes | — | Applied to every plugin in `plugin_files`. **No mixed-state bulk** — for mixed states, issue one bulk call per state or use single-plugin calls. |

## Output (success)

```json
{
  "applied": [
    { "plugin_file": "akismet/akismet.php",           "active": false },
    { "plugin_file": "wp-super-cache/wp-cache.php",   "active": false }
  ],
  "no_op": [
    { "plugin_file": "hello-dolly/hello.php", "reason": "matches-db-state" }
  ],
  "skipped": [
    { "plugin_file": "typo-plugin/tpyo.php", "reason": "plugin-not-installed" },
    { "plugin_file": "broken/main.php",     "reason": "plugin-fatal-on-load" }
  ]
}
```

- `applied` — plugins whose override entry was recorded on disk.
- `no_op` — plugins whose requested state already matched the DB-recorded state (per FR-011).
- `skipped` — plugins that could not be applied. `reason` is a machine-readable enum:
  - `plugin-not-installed` — no matching entry in `get_plugins()` (per FR-018 bulk-path).
  - `plugin-fatal-on-load` — the sandbox-scrape probe caught a `Throwable`-catchable failure (see backing implementation).

The three arrays partition the input `plugin_files` list — every entry in `plugin_files` appears in exactly one of the three (modulo dedup).

## Output (error)

- **Capability failure**: HTTP 403 via `WP_Error`.
- **All-fatal case**: if the sandbox-scrape probe triggers an uncatchable fatal (parse error, `E_ERROR`, etc.), PHP dies mid-request and the client observes a 500 with no partial write. Everything processed successfully **before** the fatal is guaranteed not to have been written, because the store is called only after every scrape has passed.

## Side effects

Per plugin in `plugin_files`, in order:

1. Resolve `plugin_file` in `get_plugins()`. If unknown, record under `skipped: plugin-not-installed` and continue.
2. If `active: true`, run the sandbox-scrape probe (see `set-override` contract). Catch `\Throwable` (which covers PHP 7+ `Error` subclasses like `ParseError` on the parent request; **not** `E_ERROR` from the includee, which is uncatchable). If a catchable failure fires, record under `skipped: plugin-fatal-on-load` and continue.
3. If the requested `active` state matches the DB-recorded state, record under `no_op: matches-db-state` and continue.
4. Add the plugin to the in-memory pending-write batch.

After iteration completes:

5. Perform a **single** write-back with all batched changes (`Overrides_Store::write_many( array<plugin_file, active> )`) — one atomic temp-file + rename per bulk call, not one per plugin.
6. Auto-prune orphans encountered during the read-modify-write cycle.

## Backing implementation notes

- Batched write is important: writing per-plugin would issue N atomic renames and N reads of the on-disk file. Batching keeps the disk I/O at O(1) regardless of `plugin_files` length.
- The sandbox-scrape probe is the same helper used by `set-override`. Per the fatal semantics of PHP's `include`, we CAN catch a subset of failures (`ParseError`, `Error` from `include`d code in PHP 7+) but CANNOT catch `E_ERROR` from the includee. When the uncatchable case fires, the whole REST call dies with a 500 and the store call in step 5 never runs — the on-disk map remains at its pre-call state.
- Empty `plugin_files` returns `{ applied: [], no_op: [], skipped: [] }` — a valid response, not an error.
