# Ability Contract — `acrossai/conflict-test-get-overrides`

**Class**: `AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\Get_Overrides`
**Auto-exposed REST route**: `POST /wp-json/wp-abilities/v1/abilities/acrossai/conflict-test-get-overrides/run`
**Annotations**: `readonly: true`, `idempotent: true`, `destructive: false`

## Purpose

Return the current override map plus a status indicator for the underlying mu-plugin mechanism. Auto-prunes entries for uninstalled plugins per FR-021 and rewrites the on-disk document if that pruning shrunk the map.

## Permission

`current_user_can( 'manage_options' )`.

## Input (JSON body)

```json
{}
```

No input fields.

## Output (success — normal case)

```json
{
  "overrides": {
    "hello-dolly/hello.php": false,
    "akismet/akismet.php":   true
  },
  "mu_plugin_status": "deployed",
  "parse_error":      null
}
```

- `overrides` is an object keyed by plugin file identifier. Value is a boolean — `true` means "effectively active" (was DB-inactive, override flipped it on); `false` means "effectively inactive" (was DB-active, override flipped it off). Empty object when no overrides are recorded.
- `mu_plugin_status` is one of `deployed`, `missing`, `stale`:
  - `deployed` — mu-plugin file exists at `WPMU_PLUGIN_DIR/wp-conflict-tester.php` and its SHA-256 matches the bundled reference at `includes/Abilities/Debugging/assets/wp-conflict-tester.php`.
  - `missing` — mu-plugin file does not exist. Any overrides in the map are inert.
  - `stale` — mu-plugin file exists but SHA-256 mismatches the bundled reference (older release, hand-edited, or a different consumer wrote its own copy).
- `parse_error` is `null` on a clean read. When FR-019 tripped (file existed but was malformed JSON), `overrides` is `{}` and `parse_error` is a short string describing the parse failure (`"unexpected token at position 42"` etc.).

## Output (special cases)

- **File absent** → `overrides: {}`, `parse_error: null`.
- **File present but empty** → `overrides: {}`, `parse_error: null` (empty is a valid representation of "no overrides").
- **File present and malformed** → `overrides: {}`, `parse_error: "<message>"`. **The file is not modified** on this path (the read tolerates malformed content but doesn't silently overwrite it — repair happens on the next `set-override` write).
- **File present with orphans** → orphan entries silently dropped; if the pruned map is smaller than the on-disk document, the store rewrites (or deletes if empty). The returned `overrides` reflects the post-prune state.

## Output (error)

Standard `WP_Error` — only surface is capability failure (HTTP 403).

## Side effects

- **Auto-prune on read** (per FR-021): if any entry's `plugin_file` doesn't resolve against `get_plugins()`, the entry is dropped from the returned map. If the pruned map differs from the on-disk document, the store rewrites (or deletes if empty, per FR-012).
- No other writes.

## Backing implementation notes

- SHA-256 is computed via `hash_file( 'sha256', $path )`. The bundled reference hash is computed once per request from the asset file on disk (not cached across requests — trivial cost).
- Read tolerates the file being on a filesystem that returns `EACCES` — treat as `missing` for mu-plugin status; treat as empty for overrides map.
