# Research — Feature 093 (File Manager Hardening enforcement pass)

The spec had zero `[NEEDS CLARIFICATION]` markers. This document captures the three genuine design decisions that shape the implementation.

---

## Decision 1 — Centralise all checks in a new `Hardening_Enforcer` utility

**Decision**: Add `includes/Abilities/Utilities/Hardening_Enforcer.php` with two public entrypoints:

```php
Hardening_Enforcer::check_write( string $absolute_path, string $content = '', array $opts = [] ): ?array
Hardening_Enforcer::check_read(  string $absolute_path ): ?array
```

Each returns `null` when the caller may proceed, or a ready-made refusal envelope (`['success' => false, 'blocked_reason' => '…', 'path' => '…', 'message' => '…', …context]`) when a rule triggers. Ability classes call the enforcer immediately after the existing `File_Mods_Guard` + `Path_Allowlist_Guard` gates and return the envelope as-is when non-null.

**Rationale**:
- **DRY**: Seven write checks × five write abilities = 35 potential per-call-site duplications. Centralising avoids that and satisfies Constitution §VI.
- **Consistency**: Every write ability applies the checks in the same order. Admins get uniform refusal behaviour (spec User Story 4).
- **Testability**: The enforcer can be unit-tested against every check without instantiating the ability classes or bootstrapping the ability adapter.
- **Follows established pattern**: `Path_Allowlist_Guard::blocked_write_response()` / `blocked_read_response()` already return a ready-made envelope-or-null. `Hardening_Enforcer` mirrors that shape so ability code reads consistently.

**Alternatives considered**:
- **Inline the checks per ability**: Rejected. Would create seven near-identical copies of each check function. Any bug fix would need updating in five places.
- **Extend `Hardening_Settings` with the check functions**: Rejected. `Hardening_Settings` is the persistence layer (option keys, defaults, sanitising getters/setters). Runtime enforcement is a different concern and should not mix with the storage class — mirrors the existing `Path_Allowlist_Guard` (runtime) vs. its own option accessors (storage) split.
- **A per-check strategy pattern (one class per check)**: Rejected as overkill. Seven small check functions fit fine in one static class; a strategy pattern would multiply files without buying any extensibility we need.

---

## Decision 2 — Options-read semantics: one snapshot per ability call

**Decision**: Each ability call takes exactly one `Hardening_Settings::get_content_filters()` snapshot at the top of the enforcer's `check_write()` / `check_read()`, and uses that snapshot for every check within the call. No caching between calls.

**Rationale**:
- **Correctness under admin change**: An admin flipping a toggle in the settings tab takes effect on the very next ability call. Zero surprise.
- **Cheap**: `get_option` is memoised by WordPress core within a request; the second-and-onward reads within a snapshot are effectively free. There's no perf motivation to cache further.
- **Testability**: Every check is deterministic per call — no static state to reset between PHPUnit test cases.

**Alternatives considered**:
- **Cache the snapshot in a static property for the request lifetime**: Rejected. Adds a "did I forget to reset this in tests?" footgun with no measurable perf benefit given WP's own option memoisation.
- **Read each option key individually when its check runs**: Rejected. Fine correctness-wise but ~7× more `get_option` calls per ability invocation for no gain.

---

## Decision 3 — `.htaccess` scan uses case-insensitive substring match (not a real parser)

**Decision**: For `htaccess_directive_scan`, refuse when `stripos($content, $directive) !== false` for any of the six directive names. No line-parsing, no comment-stripping, no context-aware analysis.

**Rationale**:
- **Matches reference plugin behaviour**: `mcp-abilities-filesystem.php:253-257` uses the exact same `stripos` check. Compatible with the pattern this feature is porting.
- **Predictable false positives are OK**: An admin who wants to write "Learn about `AddType`…" as a comment in `.htaccess` gets refused. The toggle is opt-in (on by default because the risk is real). The alternative — writing a genuine Apache directive parser — is 100+ lines of code for a WordPress plugin, not worth it.
- **Fail closed**: Better to refuse a legit `.htaccess` write with a comment than to accept a genuinely-dangerous directive because it happens to be preceded by `#`. Admins can disable the toggle if they need commented docs in their `.htaccess`.

**Alternatives considered**:
- **Parse each line, strip `#` comments, check for directives outside comments**: Rejected. Legitimate parser complexity for marginal false-positive-avoidance gain. Also, `.htaccess` allows `<IfModule>` blocks, `\` line continuations, and other Apache-isms that make "just skip comments" incomplete anyway.
- **Match at word boundaries only (`\bAddType\b`)**: Rejected. Slightly reduces false positives on prose comments but adds regex-vs-substring cognitive complexity for questionable benefit. Substring is more paranoid, which is the right default for a security filter.

---

## Non-decisions (already fixed by spec)

The spec pinned these; no further research needed:
- **Sensitive-read denylist runs after read allowlist** — spec FR-011.
- **Literal denylist entries case-sensitive; `*.EXT` globs case-insensitive** — spec User Story 2 acceptance scenarios 3 + 4.
- **`mime_type_check` "always allowed" set** — `{php, txt, log, json, xml, css, js, md, html, htm, htaccess}` — spec FR-008 and Edge Cases.
- **Append-file semantics** — `htaccess_directive_scan` on appended content only; `write_max_bytes` on `new_size = current + appended` — spec FR-004, FR-006, Edge Cases.
- **Copy/move destination-basename checks + source-size for write_max_bytes** — spec FR-009 and User Story 4 acceptance scenarios 2 + 3.
- **Enforcement order** — after `File_Mods_Guard` + `Path_Allowlist_Guard`, before actual filesystem I/O — spec FR-014.
- **No changes to Delete/Directory/ZIP/Wp-Config abilities** — spec FR-012.
