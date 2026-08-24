# Ability Contract — `acrossai/conflict-test-clear-overrides`

**Class**: `AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\Clear_Overrides`
**Auto-exposed REST route**: `POST /wp-json/wp-abilities/v1/abilities/acrossai/conflict-test-clear-overrides/run`
**Annotations**: `readonly: false`, `idempotent: true`, `destructive: true`

## Purpose

Remove every override in a single operation, restoring every plugin's effective active state to its DB-recorded state.

## Permission

`current_user_can( 'manage_options' )`.

## Input (JSON body)

```json
{}
```

No input fields.

## Output (success)

```json
{
  "cleared":              true,
  "file_existed_before":  true
}
```

- `cleared: true` — the operation succeeded (idempotent).
- `file_existed_before: true` when the JSON overrides file was present at call time; `false` when the call was a no-op (file was already absent, so the map was already empty).

## Output (error)

- **Capability failure**: HTTP 403 via `WP_Error`.
- **Filesystem write failure** (e.g. permission denied on unlink): standard `WP_Error` with code `filesystem-write-failed`.

## Side effects

- If the JSON overrides file exists, `unlink()` it. The map is now empty (per FR-012 — empty map ⇔ file absent).
- No effect on the mu-plugin file. Callers who also want to remove the mu-plugin should follow with `remove-mu-plugin`.

## Backing implementation notes

- Idempotent by construction — calling twice on an already-empty map returns `cleared: true`, `file_existed_before: false` on the second call.
- Marked `destructive: true` per the annotations table in the spec (though the destruction is "override-restoration-to-DB-state", not "permanent data loss"). Clients that surface an "are you sure?" affordance on destructive operations should honour it.
