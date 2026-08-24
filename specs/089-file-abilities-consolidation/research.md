# Research: File Abilities Consolidation (089)

Phase 0 of the plan resolves technical unknowns and records the reference patterns each new ability follows.

## 1. Ability class pattern

**Decision**: Follow the pattern established by `includes/Abilities/FileManager/Read_File.php`, `Create_File.php`, `Edit_File.php`, `Delete_File.php`. Each new ability is a class extending `AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition`, exposing an `ability()` method that returns the WP ability spec array (`name`, `label`, `description`, `input_schema`, `output_schema`, `annotations`, `execute_callback`, `permission_callback`).

**Rationale**: This is the established convention across all `file-manager/*` abilities. Reusing it keeps class layout, response envelope shape, and registration lifecycle uniform. `Ability_Definition` centralises `wp_register_ability()` wiring so subclasses only implement `ability()`.

**Alternatives considered**:
- Direct `wp_register_ability()` calls in the bootstrap — rejected; breaks the module encapsulation the rest of the plugin uses.
- One mega-ability with an `action=list|copy|move` param — rejected earlier in planning conversation; would force a mixed response schema and diverge from the read/create/edit/delete one-verb-per-ability convention.

## 2. Path scoping + traversal check

**Decision**: Use the `realpath()`-parent check from `Read_File.php`. Resolve the caller's input path against ABSPATH; if `realpath()` returns false or the result does not sit under `realpath(ABSPATH)`, refuse.

**Rationale**: Battle-tested; already relied on by four sibling abilities. Handles `..`, symlink escapes, and non-existent parents in one call. Consistent error shape.

**Alternatives considered**:
- Regex-based path sanitisation — rejected; regex on filesystem paths misses symlink resolution.
- `wp_normalize_path()` alone — rejected; normalises separators but does not resolve symlinks or catch traversal.

## 3. Protected files list

**Decision**: Reuse the `PROTECTED_FILES` array literal (`['wp-config.php', '.htaccess']`) already present in `Read_File.php` and `Delete_File.php`. Duplicate the constant into `Create_File.php`, `Edit_File.php`, `Copy_File.php`, `Move_File.php` for now.

**Rationale**: Copy-paste is intentionally simple and keeps the guard visible at the point of use. The list is two items; extracting it to a shared constant would require introducing a new utility class for a two-string array — worse ergonomics than the copy.

**Alternatives considered**:
- Extract to `File_Mods_Guard::PROTECTED_FILES` — rejected on ergonomics grounds; guard is a lockout-constant checker, not a file-registry.
- Register a filter (`acrossai_file_manager_protected_files`) — rejected as premature abstraction; can be added the moment a third caller requests it.

## 4. Directory listing implementation

**Decision**: Use PHP's `RecursiveDirectoryIterator` + `RecursiveIteratorIterator` with `SKIP_DOTS` and `FOLLOW_SYMLINKS = false`. Enforce a depth cap by comparing `$iterator->getDepth()` against the input `max_depth`. Enforce an entry cap by counting collected entries and breaking when reached; set `truncated: true` on the response.

**Rationale**: Standard PHP idiom; no new dependencies. Refusing symlink descent avoids traversal outside ABSPATH (belt-and-braces beyond the entry-time `realpath` check).

**Alternatives considered**:
- `glob()` recursion — rejected; awkward to depth-cap, no built-in entry cap.
- `scandir()` recursion — rejected; loses depth accounting without extra bookkeeping.

## 5. Copy and move semantics

**Decision**:
- `Copy_File`: use `copy($source, $destination)`. Reject if `file_exists($destination)` and `overwrite` is not true. Refuse if destination resolves to a protected file. Route through `File_Mods_Guard::check('edit')` first.
- `Move_File`: use `rename($source, $destination)` (atomic on same filesystem). Reject if source doesn't exist, if destination exists without `overwrite: true`, if either source or destination resolves to a protected file. Route through `File_Mods_Guard::check('edit')` first.

**Rationale**: `copy()` and `rename()` are the WordPress-friendly PHP primitives; both work uniformly across the local-filesystem environments this plugin targets. No need for the WP_Filesystem abstraction because the file-manager module already uses raw file functions (mirrors `Read_File`, `Delete_File`).

**Alternatives considered**:
- `WP_Filesystem::copy()` / `move()` — rejected; introduces credential prompts on non-direct filesystem environments where the existing FileManager abilities already fail identically. Consistency with siblings wins.
- Manual read-then-write — rejected for `move`; loses atomicity, bad for large files, breaks inode continuity.

## 6. Response envelope

**Decision**: Match the shape used by existing FileManager abilities: `{ success: bool, message: string, path?: string, blocked_reason?: string, ...other-fields }`. For directory listing, add `entries: array<{path, type, size, mtime}>` and `truncated: bool`. For copy/move, add `source: string`, `destination: string`.

**Rationale**: MCP clients already handle this envelope for every other file-manager ability. Introducing a new shape would fragment client handling.

## 7. Test scaffolding

**Decision**: Add test classes under `tests/Unit/Abilities/FileManager/` following the naming convention `<ClassName>_Test.php`. Use `WP_UnitTestCase` if the existing test suite provides it; otherwise plain PHPUnit `TestCase` with fixtures created in `sys_get_temp_dir()`.

**Rationale**: Aligns with existing test locations discovered during Phase 1 exploration. Filesystem-heavy tests need real temporary directories, not mocks.

**Alternatives considered**:
- Mock filesystem via vfsStream — rejected; adds a dev dependency for tests that need actual OS-level `realpath()` semantics to exercise the scoping guard.

## 8. Documentation surface

**Decision**: Update `docs/abilities-inventory.md` (remove 6 rows, add 3), `README.md` and `README.txt` (Changelog entry naming the six removed slugs and their `file-manager/*` replacements), and add a migration table row in the CHANGELOG.

**Rationale**: These are the three files integrators consult. `docs/abilities-inventory.md` is the canonical enumeration; the READMEs are what WordPress.org and MCP-client authors read.

**Alternatives considered**:
- Auto-generate the inventory from code — out of scope; existing inventory is hand-maintained and this feature does not change that.

## 9. Rollout / deprecation strategy

**Decision**: Hard delete. Six removed slugs are simply gone from the registry. CHANGELOG documents the removal + the `file-manager/*` replacement per slug.

**Rationale**: Confirmed with user during planning conversation. Plugin is pre-1.0; scoped abilities are not part of a public stability contract. Shims would double the surface again and reintroduce the duplication this feature aims to eliminate.

**Alternatives considered**:
- Deprecation shims (each removed class becomes a thin wrapper that calls the file-manager equivalent and emits `_doing_it_wrong()`) — rejected on the user's explicit direction; adds ongoing maintenance without a demonstrated integrator need.

## Open questions

None. All NEEDS CLARIFICATION markers from Phase 1 (of the specification) were resolved during that phase.
