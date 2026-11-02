# Feature 135 — resolve file paths against the roots WordPress actually uses, not just ABSPATH

> Feature number provisional — let `/speckit.specify` assign the next free one (134 is database governance).

**The ask.** Every path-taking `file-manager/*` ability resolves its `path` parameter against
`ABSPATH` and refuses anything outside it. WordPress does not require `wp-content` to live inside
`ABSPATH`. On hosts where it does not, the abilities cannot reach the live site at all — and worse,
some of them succeed against a decoy directory instead of saying so.

## How this surfaced

Measured against a live managed-host install (`accel-staging`, WordPress 7.1.4):

```
ABSPATH        = /wordpress/core/7.1.4/
WP_CONTENT_DIR = /srv/htdocs/wp-content      ← outside ABSPATH
```

That is the WordPress.com / Pressable split-docroot layout: core is a version-pinned read-mostly
directory, and the site's own content lives elsewhere. Three measured consequences:

1. **`read-file wp-content/themes/twentytwentyfive/style.css` returned HTTP 200 with real bytes.**
   The resolved path was `/wordpress/core/7.1.4/wp-content/themes/...` — the host's *core bundle*,
   which ships only Akismet and the three default themes. Nothing in the response said this was not
   the live `wp-content`. An agent reading that reply reports "I read your theme file" and is wrong.
2. **`file-info wp-content/themes/accel-main/functions.php` → `blocked_reason: "invalid_path"`.**
   `accel-main` is the site's actual active theme. It is unreachable.
3. **`read-debug-log` and `get-changelog` work fine**, because they build their paths from
   `WP_CONTENT_DIR` instead of from caller input. So the plugin already disagrees with itself about
   where `wp-content` is.

### The write allowlist makes it worse

`Path_Allowlist_Guard` resolves its entries the same way — `realpath( ABSPATH . $entry )`
(`Path_Allowlist_Guard.php:249`) — and ships `DEFAULT_WRITE_ALLOWLIST = array( 'wp-content' )`.

On a split-docroot host that default resolves to the core bundle. The out-of-the-box posture
therefore **denies writes to the real `wp-content` and permits them inside the host's core
directory** — the exact inversion of what the setting reads as.

## Scope

No shared resolver exists; 14 path-taking abilities each repeat the idiom. From `Read_File.php:124`:

```php
$base     = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
$abs_path = $base . '/' . ltrim( $rel_path, '/' );
$parent   = realpath( dirname( $abs_path ) );

if ( false === $parent || ( $parent !== $base && 0 !== strpos( $parent, $base . '/' ) ) ) {
    return array( 'success' => false, 'message' => 'Invalid or disallowed file path.' );
}
```

Affected: `read-file`, `list-directory`, `file-info`, `create-file`, `edit-file`, `append-file`,
`delete-file`, `create-directory`, `delete-directory`, `copy-file`, `move-file`,
`create-zip-backup`, `extract-zip-backup`, plus `Path_Allowlist_Guard` and `Zip_Target_Resolver`.

## Prior art: how everyone else handles it

### WordPress core — never resolves from ABSPATH, and validates by enumeration

Core solves it twice over, and neither layer uses an `ABSPATH` prefix:

- **Resolution is per-subsystem.** `wp_edit_theme_plugin_file()` builds
  `WP_PLUGIN_DIR . '/' . $file` for plugins and `$theme->get_stylesheet_directory()` for themes.
  `get_theme_root()` is itself derived from `WP_CONTENT_DIR` (`wp-includes/theme.php:619`), so it
  follows `wp-content` wherever the host put it.
- **Containment is an allowlist of real files, not a prefix test.**
  `validate_file( $file, get_plugin_files( $plugin ) )` and
  `validate_file_to_edit( $file, $theme->get_files( 'php', -1 ) )`. `validate_file()` itself
  (`functions.php:6372`) only checks traversal and Windows drive letters, then `in_array()` against
  the supplied list.

This is why core's own Theme File Editor works on the host where our `read-file` does not.

### Of the MCP plugins that expose file paths, three of four repeat our mistake

| Plugin | Root for caller paths | Verdict |
|---|---|---|
| `mcp-content-manager-lite` | `WP_CONTENT_DIR`, plus a 2-entry bare-filename allowlist into ABSPATH | **correct** |
| `mcp-abilities-filesystem` | `realpath( ABSPATH )` + `strpos( … ) === 0` | same bug; also uses `WP_CONTENT_DIR` only for its own backups and logs |
| `wp-agentic-admin` (`write-file`) | `realpath( ABSPATH . ltrim( $p, '/' ) )` | same bug |
| `wp-file-manager` (Filester) | `str_replace( '\\', '/', ABSPATH )` | same root; mitigated only by a human-set `public_path` override — an escape hatch, not resolution |
| `easy-mcp-ai` | — | ships no filesystem abilities; sidesteps the question entirely |

`novamira`, `royal-mcp`, `cowboy-mcp`, `iato-mcp` and `stifli-flex-mcp` expose no path-taking file
abilities (their `roots` symbols are the MCP protocol's *client* roots DTO, unrelated to disk).

**`mcp-content-manager-lite` is the one to borrow from.** It inverts the default — caller paths are
relative to `WP_CONTENT_DIR`, and `ABSPATH` is reachable only through an explicit two-entry
allowlist (`.htaccess`, `.maintenance`) that also requires the path to contain no slash:

```php
$content_dir = wp_normalize_path( WP_CONTENT_DIR );
if ( ! $is_abspath_file && ! str_starts_with( $path, '/' ) ) {
    $path = $content_dir . '/' . $path;
}
...
if ( ! str_starts_with( wp_normalize_path( $real_path ), wp_normalize_path( realpath( WP_CONTENT_DIR ) ) ) ) {
    return new WP_Error( 'path_outside_content', … );
}
```

It also blocks null bytes and stream wrappers (`phar://`, `php://`), rejects `..` before any
processing, restricts extensions, and derives its backup directory from `wp_upload_dir()['basedir']`
with the comment that this "works correctly regardless of custom `WP_CONTENT_DIR` or non-standard
WordPress directory structures." Someone there hit this layout and fixed it deliberately.

## What to build

**A shared root resolver** (`Utilities\Fs_Roots`) that every path-taking ability calls, replacing
14 copies of the idiom. Roots, each resolved through `realpath`:

| Label | Source |
|---|---|
| `content` | `WP_CONTENT_DIR` |
| `plugins` | `WP_PLUGIN_DIR` |
| `mu-plugins` | `WPMU_PLUGIN_DIR` |
| `themes` | every entry of `get_theme_roots()` |
| `uploads` | `wp_upload_dir()['basedir']` |
| `core` | `ABSPATH` |

Requirements:

- **FR-1 — reach the live content directory.** A caller path beginning with the basename of
  `WP_CONTENT_DIR` (normally `wp-content/`) maps onto `WP_CONTENT_DIR`, so the documented
  `wp-content/themes/accel-main/functions.php` reaches the real theme.
- **FR-2 — accept any real root.** Containment passes when the resolved real path lies within any
  root above, not only `ABSPATH`.
- **FR-3 — never serve the decoy silently.** When `WP_CONTENT_DIR` is outside `ABSPATH` and a
  caller path would resolve inside `ABSPATH`'s own unused `wp-content`, refuse with a distinct
  reason naming both candidate paths. A refusal is recoverable; a wrong file returned as a success
  is not. This is the requirement that would have prevented the incident above.
- **FR-4 — report where the answer came from.** Every response carries the absolute `path` (already
  true) plus the matched root label, so a caller can tell core from content without inferring it.
- **FR-5 — fix the allowlist default.** `Path_Allowlist_Guard` resolves its entries through the same
  resolver, so a stored `wp-content` entry means the live directory on every layout. Needs a
  migration decision for sites that stored entries under the old semantics.
- **FR-6 — keep every existing refusal.** `wp-config.php` and `.htaccess` protection, the read and
  write allowlists, `File_Mods_Guard`, `Hardening_Enforcer` and secret redaction are unchanged. This
  feature widens where a path may legitimately point; it does not widen what may be done there.
- **FR-7 — borrow the input hardening we lack.** Reject null bytes and stream-wrapper schemes
  (`phar://`, `php://`, …) before resolution.

**Non-goals.** No new abilities. No change to the `path` parameter's documented meaning on a
standard install, where every candidate resolution coincides. No configurable root list — the roots
come from WordPress, which is the entire point.

## Open question for `/speckit.plan`

FR-5 needs a call on stored allowlists. An entry saved as `wp-content` was *written* meaning "the
content directory" but *enforced* as "ABSPATH/wp-content". Re-resolving changes behaviour on
split-docroot hosts — which is the fix, and is still a behaviour change on upgrade. Options: migrate
entries to root-qualified form (`content:`-prefixed), re-resolve silently, or re-resolve and surface
a one-time admin notice naming what moved.
