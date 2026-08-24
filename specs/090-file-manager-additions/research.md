# Research: File-Manager Additions (090)

Phase 0 records the decisions and alternatives evaluated for each of the four new abilities. All NEEDS CLARIFICATION items from the spec are resolved.

## 1. Library evaluation — should we vendor elFinder?

**Decision**: No. Use WordPress core + PHP built-ins.

**Rationale**: The reference plugin `wp-file-manager` (v8.0.4, GPLv2) vendors the full elFinder PHP library at `lib/php/` (multiple 3000+ line volume driver classes). Every operation needed for feature 090 is one WordPress or PHP built-in call:
- `create-directory` → `wp_mkdir_p($abs)` (WordPress core, recursive-by-default, ABSPATH-friendly).
- `delete-directory` → `RecursiveIteratorIterator( RecursiveDirectoryIterator, CHILD_FIRST )` + `unlink` / `rmdir` (same idiom feature 089 uses for `list-directory`).
- `append-file` → `file_put_contents( $abs, $content, FILE_APPEND | LOCK_EX )` (native).
- `file-info` → `stat($abs)` + `posix_getpwuid` / `posix_getgrgid` when available (native).

Vendoring ~10 MB of volume-abstraction / hash-encoding / caching code for four thin wrappers is disproportionate and would fragment the module's style (peer abilities from feature 089 already use raw PHP through `File_Mods_Guard`).

**Alternatives considered**:
- Vendor elFinder — rejected on size + coupling grounds.
- Use `WP_Filesystem` abstraction — rejected on consistency grounds. Peer abilities in `includes/Abilities/FileManager/` all use raw PHP (`file_get_contents`, `file_put_contents`, `copy`, `rename`, `RecursiveDirectoryIterator`). Introducing `WP_Filesystem` here would create two coexisting patterns.

## 2. Reference for elFinder patterns worth mirroring

Even without vendoring, elFinder's local-volume driver contains patterns worth studying:
- `_stat()` at `lib/php/elFinderVolumeLocalFileSystem.class.php:559` — approach for optional POSIX name resolution guarded by `stat()` support and conditional feature detection.
- `_inpath()` at the same file:525 — path scoping via `realpath()` + prefix check (matches the pattern we already use in `Read_File.php`).
- `delTree()` at `lib/php/elFinderVolumeDriver.class.php:4316` — recursive scan + `_unlink` + `_rmdir` bottom-up. Simpler than an iterator but equivalent.

Feature 090 uses the same shapes but expressed via standard-library iterators for concision.

## 3. Path scoping + traversal check

**Decision**: Reuse the `realpath()`-parent check from `Read_File.php:114-123`. Copy the block into each new ability rather than extract a helper.

**Rationale**: Established convention from feature 089 — keeps the guard visible at the point of use, avoids proliferating tiny utility classes, matches how peer abilities read on a code review.

**Alternatives considered**:
- Extract a `Path_Scope::check(string $rel): string|WP_Error` helper — rejected on the same grounds as feature 089 (the copy is small and per-call visibility is more important than DRY for a security guard).

## 4. Protected-file guard for `append-file`

**Decision**: Duplicate the `PROTECTED_FILES = ['wp-config.php', '.htaccess']` constant plus the basename-at-ABSPATH-root refusal from `Delete_File.php:131-138` into `Append_File.php`.

**Rationale**: Same list, same shape, same rationale as feature 089's hardening of `Create_File` and `Edit_File`. Consistency is the design constraint.

## 5. Protected-directory guard for `delete-directory`

**Decision**: New class constant `PROTECTED_DIRS` on `Delete_Directory.php` listing critical WordPress directories:
- `ABSPATH` itself
- `wp-admin`, `wp-includes`
- `wp-content`, `wp-content/plugins`, `wp-content/themes`, `wp-content/mu-plugins`, `wp-content/uploads`
- This plugin's own directory: `wp-content/plugins/acrossai-abilities-manager`

Refusal check: compare `realpath($abs)` against `realpath(ABSPATH . <entry>)` for each entry. Refuse with `blocked_reason:'protected_directory'`.

**Rationale**: A recursive `delete-directory` on any of these paths would irreversibly break the site. The list is short and stable enough to hardcode; a filter hook can be added later if a real integrator needs to extend it.

**Alternatives considered**:
- Filter hook (`acrossai_abilities_manager_protected_dirs`) from day one — rejected as premature abstraction; can be added the moment a third caller requests it.
- Only refuse `ABSPATH` root and rely on operator judgement for the rest — rejected as too weak; the whole point of the guard is preventing accidental site destruction by an LLM-driven MCP client.

## 6. Recursive-delete implementation

**Decision**: `RecursiveIteratorIterator( new RecursiveDirectoryIterator( $abs, SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST )`. For each entry: skip symlinks, `unlink()` files, `rmdir()` empty directories. Count as we go; if any single `unlink`/`rmdir` fails, capture that entry name, return `success:false` with `entries_removed` set to the partial count.

**Rationale**: Standard PHP idiom, no new deps. `CHILD_FIRST` guarantees files are removed before their parent directory. `SKIP_DOTS` avoids the `.` / `..` self-references. Explicit symlink skip mirrors the same rule feature 089's `list-directory` uses.

**Alternatives considered**:
- Recursive function that calls `scandir` + `is_dir` + recurse — equivalent behaviour, marginally more code, no clear win.
- `wp_delete_file` for each file — offers no advantage over `unlink()` for this use case; the WordPress helper adds filter hooks that would fire per-file (potential N-times overhead).

## 7. `file-info` implementation

**Decision**: Single `stat($abs)` for size / mtime / ctime / atime / mode / uid / gid. Booleans via `is_dir`, `is_file`, `is_link`, `is_readable`, `is_writable`. Octal permissions via `substr( decoct( $stat['mode'] ), -4 )`. Owner/group name resolution via `posix_getpwuid( $uid )` / `posix_getgrgid( $gid )` gated behind `function_exists( 'posix_getpwuid' )` — omit the name fields entirely when POSIX is absent (Windows hosts, containers without the extension).

**Rationale**: Read-only, cheap, matches the `elFinder` approach at `_stat()` without the volume-abstraction overhead. Type field is derived at the top of the response: `is_link` first (broken-symlink case), then `is_dir` → `"dir"`, else `"file"`.

## 8. `append-file` implementation

**Decision**:
- Append path: `file_put_contents( $abs, $content, FILE_APPEND | LOCK_EX )`. Atomic-ish; single system call.
- Prepend path: `file_get_contents` → `$content . $existing` → `file_put_contents( $abs, $joined, LOCK_EX )`. Not atomic — a concurrent writer between read and write would win. Documented as a caveat in the ability description.
- File-must-exist guard: `is_file( $abs )` check before either path. If absent, refuse with a message directing the caller to `create-file`.
- Return `bytes_written` (the delta) and `new_size` (post-op file size for the caller's sanity check).

**Rationale**: Straightforward. `FILE_APPEND | LOCK_EX` is the right primitive for the common (append) case. Prepend is a small ergonomic extra that costs one extra read.

**Alternatives considered**:
- Support create-if-missing via a `create_if_missing:true` flag — rejected. Muddies the ability's contract. Callers who want that can call `create-file` first.
- Chunked prepend — rejected as premature optimisation. The ability description warns operators away from prepending to large files.

## 9. Idempotency semantics

**Decision**:
- `create-directory` returns `success:true, created:true` on first call, `success:true, created:false` on repeat calls when the directory already exists — safe to re-run.
- `delete-directory` returns `success:true, entries_removed:0, message:"Directory does not exist."` on a missing target — also safe to re-run.
- `append-file` is not idempotent (each call appends more bytes) — do NOT mark `idempotent:true` in annotations.
- `file-info` is trivially idempotent.

**Rationale**: Matches user expectation for MCP-driven workflows where the same call may be retried after a transport hiccup.

## 10. Test convention

**Decision**: Structural-inspection tests in `tests/phpunit/abilities/Test_Feature_090_File_Manager_Additions.php`, following the exact pattern from `Test_Feature_089_File_Consolidation.php`. Load the source file as a string; assert on class name, extends clause, ability slug, category slug, capability check, guard invocation, key implementation calls (`FILE_APPEND | LOCK_EX`, `wp_mkdir_p`, `RecursiveIteratorIterator`, `stat()`, `posix_getpwuid` guarded by `function_exists`).

**Rationale**: Established plugin-wide convention. Runtime tests would need a real filesystem sandbox + WordPress test-suite bootstrap that this plugin doesn't currently ship.

## Open questions

None. All FRs and edge cases in `spec.md` have unambiguous implementations.
