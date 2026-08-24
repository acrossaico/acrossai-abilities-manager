# Ability Contract — `acrossai/conflict-test-deploy-mu-plugin`

**Class**: `AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\Deploy_Mu_Plugin`
**Auto-exposed REST route**: `POST /wp-json/wp-abilities/v1/abilities/acrossai/conflict-test-deploy-mu-plugin/run`
**Annotations**: `readonly: false`, `idempotent: true`, `destructive: false`

## Purpose

Install the underlying override mechanism by writing `wp-conflict-tester.php` into the site's mu-plugin directory. Idempotent — a redeploy against an already-current mechanism is a zero-write no-op (SC-006).

## Permission

`current_user_can( 'manage_options' )` **and** `! File_Mods_Guard::blocked()` (FR-013).

## Input (JSON body)

```json
{}
```

No input fields.

## Output (success — first deploy)

```json
{
  "deployed":        true,
  "already_current": false,
  "path":            "/Users/…/wp-content/mu-plugins/wp-conflict-tester.php"
}
```

## Output (success — idempotent redeploy)

```json
{
  "deployed":        false,
  "already_current": true,
  "path":            "/Users/…/wp-content/mu-plugins/wp-conflict-tester.php"
}
```

`deployed: false` with `already_current: true` — the on-disk file's SHA-256 already matched the bundled reference; no bytes were written.

## Output (stale-overwrite)

```json
{
  "deployed":        true,
  "already_current": false,
  "path":            "/Users/…/wp-content/mu-plugins/wp-conflict-tester.php"
}
```

`already_current: false` with `deployed: true` — a previous (stale) mu-plugin file was present. It has been overwritten with the current bundled bytes.

## Output (error paths)

- **File mods disabled** (FR-013):

  ```json
  { "code": "file-mods-disabled", "message": "WordPress file modifications are globally disabled on this site.", "data": { "status": 409 } }
  ```

  No on-disk changes are attempted.

- **`WPMU_PLUGIN_DIR` not writable**: `WP_Error` with code `filesystem-write-failed`, HTTP 500.

- **`WPMU_PLUGIN_DIR` does not exist**: mu-plugins directory is created via `wp_mkdir_p( WPMU_PLUGIN_DIR )` before the write (matches WP core behaviour). If creation itself fails, `WP_Error` with code `mu-plugins-dir-uncreatable`, HTTP 500.

- **Bundled asset missing** (should never happen — indicates a broken plugin install): `WP_Error` with code `bundled-source-missing`, HTTP 500.

- **Capability failure**: HTTP 403.

## Side effects

- **Guard first**: `File_Mods_Guard::blocked_response()` — returns error before touching the filesystem.
- **Hash-compare**: SHA-256 of `WPMU_PLUGIN_DIR . '/wp-conflict-tester.php'` (if it exists) vs SHA-256 of `<plugin_dir>/includes/Abilities/Debugging/assets/wp-conflict-tester.php`. If equal, skip the write.
- **Ensure mu-plugins directory**: `wp_mkdir_p( WPMU_PLUGIN_DIR )` if the directory doesn't exist.
- **Atomic write**: write the asset bytes to a sibling temp file, then rename into the target (same pattern as `Overrides_Store::write`).

## Backing implementation notes

- The bundled source is loaded via `file_get_contents( plugin_dir_path( __FILE__ ) . 'assets/wp-conflict-tester.php' )` — that path resolves relative to `Deploy_Mu_Plugin.php` at `includes/Abilities/Debugging/assets/wp-conflict-tester.php`.
- Marked `idempotent: true` and **not** `destructive: false`. Redeploying against a current mechanism is expected client behaviour (e.g. run as part of a setup script that is safe to re-run).
- No `WP_SANDBOX_SCRAPING` involvement — the deployed mu-plugin's own contents are what will run on future requests, but the deploy itself does not include or execute those contents.
