# Research: WP_Filesystem Migration (091)

## 1. Native-to-WP_Filesystem mapping

Standard WordPress-core mapping used across all 19 migrated abilities:

| Native | `WP_Filesystem_Base` |
|---|---|
| `file_get_contents($p)` | `$fs->get_contents($p)` |
| `file_put_contents($p, $c)` | `$fs->put_contents($p, $c, FS_CHMOD_FILE)` |
| `file_put_contents($p, $c, FILE_APPEND \| LOCK_EX)` | `$fs->put_contents($p, $fs->get_contents($p) . $c, FS_CHMOD_FILE)` — **non-atomic** |
| `unlink($p)` / `wp_delete_file($p)` | `$fs->delete($p)` |
| `rmdir($p)` | `$fs->rmdir($p)` |
| `mkdir($p, $mode)` | `$fs->mkdir($p, $mode)` |
| `wp_mkdir_p($p)` | **stays** (already transport-aware) |
| `copy($s, $d)` | `$fs->copy($s, $d, $overwrite, FS_CHMOD_FILE)` |
| `rename($s, $d)` | `$fs->move($s, $d, $overwrite)` |
| `file_exists($p)` | `$fs->exists($p)` |
| `is_file($p)` / `is_dir($p)` / `is_readable($p)` / `is_writable($p)` | `$fs->is_file/is_dir/is_readable/is_writable($p)` |
| `filesize($p)` | `$fs->size($p)` |
| `filemtime($p)` | `$fs->mtime($p)` |
| `realpath($p)` | **stays** (pure path normalisation) |
| `opcache_invalidate($p)` | **stays** (opcode cache, not filesystem I/O) |
| `posix_getpwuid` / `posix_getgrgid` | **stays** with `function_exists` guard |

## 2. Init pattern — decision

**Decision**: Wrap the boilerplate in a shared utility `Wp_Filesystem_Init` (`includes/Abilities/Utilities/Wp_Filesystem_Init.php`) that returns `WP_Filesystem_Base|WP_Error` and offers a `blocked_response()` companion returning the ability response envelope.

**Rationale**: 19 ability classes need the same 6-line boilerplate. Duplicating it violates Constitution §VI (DRY). Centralising it also gives one place to enhance credential handling later.

**Reference implementation**: `mcp-abilities-filesystem.php` lines 73-78, 107-112, 171-176 all use the same init sequence — proven pattern.

## 3. Recursive walks — decision

**Decision**: Replace `RecursiveDirectoryIterator` + `RecursiveIteratorIterator` in `List_Directory` and `Delete_Directory` with a private recursive method that calls `$fs->dirlist($path)` and walks the returned array manually.

**Rationale**: The SPL iterators only work on directly-accessible filesystems. `dirlist()` returns entries via whatever transport is active — the only WP_Filesystem-compatible way to walk a tree.

**Bookkeeping preserved**: depth counter (`$current_depth < $max_depth`), entry counter (`count($entries) < $max_entries`), symlink skip (`if ($info['type'] === 'l') continue`), pre-order for list, post-order (CHILD_FIRST) for delete.

**Alternatives considered**:
- `scandir` recursion — same problem as SPL iterators (native filesystem only).
- Keep the SPL iterator on `direct` transport and switch to `dirlist` on others — rejected as fragmentation.

## 4. `File_Info` schema shrink

**Decision**: Drop `ctime` and `atime` from the response schema. Update the `output_schema` array accordingly.

**Rationale**: `WP_Filesystem_Base` exposes `size()`, `mtime()`, `owner()`, `group()`, `getchmod()`, but no `ctime` or `atime`. `WP_Filesystem_Direct` could shim them via native `stat()`, but the FTP/SSH transports have no equivalent, so keeping them as always-zero-on-remote would be misleading. Better to shrink the schema honestly.

**Migration impact for callers**: BREAKING for anyone reading `.ctime` or `.atime` programmatically. Documented in the CHANGELOG.

**Alternatives considered**:
- Keep as `0` — rejected (misleading).
- Best-effort via native `stat()` when transport is `direct` — rejected (transport-conditional schema is worse than a shrink).

## 5. Append-file non-atomicity

**Decision**: Implement append as read + concat + write (both prepend AND the new append path). Document in the ability description.

**Rationale**: `$fs->put_contents()` does not accept a `FILE_APPEND` flag. The read+concat+write pattern is the only WP_Filesystem-compatible way. Reference plugin uses exactly this pattern for its log-append (`mcp-abilities-filesystem.php:171-182`).

**Trade-off**: Two concurrent append callers can overwrite each other's writes. In practice, MCP-driven ability calls are serialised per-client and this window is small. Callers who need atomic append on very high-throughput logs should not use `file-manager/append-file` — they should use direct log-ingestion tooling.

## 6. Zip abilities — deferral

**Decision**: `Create_Zip_Backup`, `Extract_Zip_Backup`, `Upload_Zip_Backup` keep native PHP. Each carries a source-level `// TODO(feature-092): migrate to WP_Filesystem`. Their PHPCS suppressions stay.

**Rationale**: `ZipArchive` requires direct filesystem access (it's a PHP extension that opens file handles). `Upload_Zip_Backup` uses `fopen`/`fwrite`/`fclose` for chunked upload — WP_Filesystem exposes no file-handle API. A proper design conversation is needed for feature 092 (options: refuse on non-`direct`, stage via temp local copy, redesign chunking).

## 7. `Ability_Definition` — do not touch

**Decision**: Zero modifications to `includes/Modules/Library/Ability_Definition.php`.

**Rationale**: Sibling plugin `acrossai-buddyboss` extends this class for ~20 of its own abilities. Any signature change ripples into that plugin. This migration only modifies subclass `execute()` bodies — the base class is untouched.

## 8. `File_Mods_Guard` — do not touch

**Decision**: Zero modifications. `File_Mods_Guard` is a pure `DISALLOW_FILE_MODS` / `DISALLOW_FILE_EDIT` checker with no filesystem I/O of its own. Orthogonal to transport.

## Open questions

None. All FRs and edge cases in `spec.md` have unambiguous implementations.
