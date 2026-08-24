# Ability Contract — `acrossai/conflict-test-remove-mu-plugin`

**Class**: `AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\Remove_Mu_Plugin`
**Auto-exposed REST route**: `POST /wp-json/wp-abilities/v1/abilities/acrossai/conflict-test-remove-mu-plugin/run`
**Annotations**: `readonly: false`, `idempotent: true`, `destructive: true`

## Purpose

Remove the mu-plugin file at `WPMU_PLUGIN_DIR/wp-conflict-tester.php`. Turns off conflict testing entirely — any lingering overrides on disk become inert (nothing filters `option_active_plugins` any more). Optional flag `also_clear_overrides` deletes the JSON overrides file in the same call for a full cleanup.

## Permission

`current_user_can( 'manage_options' )` **and** `! File_Mods_Guard::blocked()` (FR-013).

## Input (JSON body)

```json
{
  "also_clear_overrides": true
}
```

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| `also_clear_overrides` | `boolean` | no | `false` | When `true`, additionally delete `WP_CONTENT_DIR . '/conflict-test-overrides.json'` if it exists. When `false`, the overrides file is left on disk (inert until the mu-plugin is redeployed). |

## Output (success — mu-plugin was deployed)

```json
{
  "removed":              true,
  "file_existed_before":  true,
  "overrides_cleared":    false
}
```

## Output (success — idempotent no-op, mu-plugin was already absent)

```json
{
  "removed":              true,
  "file_existed_before":  false,
  "overrides_cleared":    false
}
```

## Output (success — full cleanup)

```json
{
  "removed":              true,
  "file_existed_before":  true,
  "overrides_cleared":    true
}
```

- `removed: true` regardless of pre-state — the operation is idempotent.
- `file_existed_before` reflects whether the mu-plugin was present at call time.
- `overrides_cleared` reflects whether the JSON overrides file was deleted this call. Only `true` when the input flag was `true` **and** the JSON file existed.

## Output (error paths)

- **File mods disabled** (FR-013):

  ```json
  { "code": "file-mods-disabled", "message": "WordPress file modifications are globally disabled on this site.", "data": { "status": 409 } }
  ```

  No on-disk changes are attempted.

- **`unlink()` fails** on either file (e.g. filesystem permission denied): `WP_Error` with code `filesystem-write-failed`. Partial state possible if the mu-plugin was removed but the JSON delete then failed — the response's `removed: true` reflects the mu-plugin state, and the caller is expected to retry (idempotent — subsequent calls will succeed on the JSON deletion).

- **Capability failure**: HTTP 403.

## Side effects

1. **Guard first**: `File_Mods_Guard::blocked_response()` — returns error before touching the filesystem.
2. **Remove mu-plugin**: `unlink( WPMU_PLUGIN_DIR . '/wp-conflict-tester.php' )` if the file exists. Idempotent — an absent file is a success.
3. **Conditional overrides clear**: if `also_clear_overrides: true`, `unlink( WP_CONTENT_DIR . '/conflict-test-overrides.json' )` if the file exists. Idempotent.

Order matters: the mu-plugin is removed first so that even if the JSON delete fails, the mu-plugin is definitely gone and the JSON is inert.

## Backing implementation notes

- Marked `destructive: true` per the spec's annotations table. Clients that surface an "are you sure?" affordance on destructive operations should honour it.
- The mu-plugin path and overrides path are both class-level constants — no caller-supplied paths (FR-014).
- Rationale for the flag existing (vs always clearing overrides too): a common workflow is to `remove-mu-plugin` to turn off conflict testing for a redeploy or an audit, keeping the overrides map on disk so `deploy-mu-plugin` re-enables the same test state. Callers that want a full reset pass `also_clear_overrides: true`.
