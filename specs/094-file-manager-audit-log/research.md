# Research — Feature 094 (Audit Log + Backup Harness)

Every substantive design decision, with rationale + alternatives considered. The briefing had 8 open questions; all resolved via the recommended defaults captured here.

---

## Decision 1 — Single `Audit_Trail` utility, not two separate classes

**Decision**: One class `Audit_Trail` in `includes/Abilities/Utilities/` owns backup, log write, cleanup, and stats. Public entrypoints:

```php
Audit_Trail::write_backup( string $absolute_path, array $opts = [] ): string|false|null
Audit_Trail::write_log( string $operation, string $absolute_path, array $details = [] ): void
Audit_Trail::maybe_cleanup(): void          // probabilistic; called from write_log()
Audit_Trail::stats(): array                 // for the /backup-audit-stats endpoint
Audit_Trail::backup_base_dir(): string      // for path composition
Audit_Trail::log_path(): string             // for get-changelog
```

**Rationale**:
- **Cohesion**: All four responsibilities read the same option snapshot and share the same disk layout. Splitting into `Backup_Writer` + `Log_Writer` + `Cleanup_Runner` would triplicate the snapshot read.
- **Consistency with existing pattern**: `Hardening_Enforcer` (feature 093) similarly owns 7 checks in one class. Same shape.
- **Testability**: Every path unit-testable via direct static calls. No object graph.

**Alternatives considered**:
- Two classes (`Backup_Writer`, `Log_Writer`): rejected. The cleanup logic straddles both concerns; putting it in a third class creates a coordination problem.
- Extending `Hardening_Enforcer`: rejected. The enforcer decides IF a write proceeds. The trail records WHAT proceeded. Different responsibilities — mixing would violate SRP and confuse readers.

---

## Decision 2 — Log location: dedicated directory (not bare file at wp-content root)

**Decision**: Log lives at `wp-content/acrossai-file-manager-logs/acrossai-file-manager.log`. Directory created lazily on first log write with an `.htaccess` (`Deny from all`) sibling.

**Rationale**:
- **HTTP protection**: `.htaccess` can protect the whole dir with a single rule. Placing the log at `wp-content/acrossai-file-manager.log` would require modifying WordPress's own `wp-content/.htaccess` (dangerous) or a rule-per-file approach.
- **Symmetry with backup dir**: `wp-content/acrossai-file-manager-backups/` is already dedicated. Two dedicated dirs at the same level are easy to reason about.
- **Growth room**: If a future feature adds `logs/audit-<year>.log.gz` or similar rotation, the dir already exists.
- **Reference plugin parity**: `mcp-abilities-filesystem.php:141` uses `WP_CONTENT_DIR . '/mcp-filesystem.log'` (bare file). We diverge intentionally for the security benefit.

**Alternatives considered**:
- Bare file at `wp-content/acrossai-file-manager.log`: rejected. Can't protect it via `.htaccess` without touching WP's own file.
- Inside `wp-content/uploads/`: rejected. Uploads is user-content territory; the log is plugin operational data.

---

## Decision 3 — Cleanup trigger: 1-in-10 amortisation, not WP-Cron

**Decision**: `Audit_Trail::write_log()` ends with `if (wp_rand(1, 10) === 1) { Audit_Trail::maybe_cleanup(); }`. No `wp_schedule_event` and no dependency on WP-Cron.

**Rationale**:
- **Reference plugin parity**: `mcp-abilities-filesystem.php:186` does exactly this. Well-worn pattern.
- **Zero cron surface**: No `wp_schedule_event` to register at activation, no cron hook to clean up at uninstall, no "WP-Cron isn't running" support tickets.
- **Predictable frequency**: On a site with 10+ writes/day cleanup fires ≥ once/day. Retention windows are day-scale so this is more than fine. On a quiet site cleanup may not fire for days — worst case is a slightly-larger backup dir, which the next cleanup will catch.
- **No cost when disabled**: `write_log()` only runs when `audit_log_enabled=true`. Cleanup only runs inside write_log. Disabled → zero I/O.

**Alternatives considered**:
- WP-Cron daily event: rejected. Adds surface area (`wp_schedule_event`, unschedule at uninstall, cron-disabled edge cases). Predictable but not proportionally better than amortised for this use case.
- Every write triggers cleanup: rejected. Reads the whole log file to check retention on every single write — quadratic in worst case.
- Cleanup on plugin admin page load: rejected. Coupling storage maintenance to a UI event is fragile; admins may never visit the tab.

---

## Decision 4 — Backup filename: `<basename>.bak.<HHMMSS>[.N]`, day-bucket dir

**Decision**: Backup file lives at `wp-content/acrossai-file-manager-backups/<YYYY-MM-DD>/<basename>.bak.<HHMMSS>[.N]`. `.N` counter (starting at `.1`) handles second-collisions, incrementing until unique (cap at `.100` then give up).

**Rationale**:
- **Reference plugin parity**: `mcp-abilities-filesystem.php:80-89` uses exactly this scheme.
- **Human-inspectable**: An admin browsing the dir can immediately identify what was backed up when.
- **Retention friendly**: Cleanup deletes whole date-dirs, no per-file date parsing.
- **Filename collisions on the same second are rare in practice** — for a human admin they're impossible; for a fast-fire LLM they might happen. `.100` cap is defensive.

**Alternatives considered**:
- Content-addressed (`<sha1>.bak`): rejected. Deduplicates identical pre-images but harder to identify visually; complicates cleanup.
- Timestamp with subseconds (`<basename>.bak.<HHMMSSmmm>`): rejected. `gmdate` doesn't produce milliseconds without extra work; collisions in millisecond resolution are still possible; complexity not worth it.

---

## Decision 5 — `context` field: schema max 2000, truncate-store 500

**Decision**: Input schema declares `context:{type:string, maxLength:2000}` (adapter rejects > 2000 chars). Log writer runs `sanitize_text_field()` then `substr(0, 500)` before appending to the log entry.

**Rationale**:
- **Two-layer defence**: Schema max prevents 10-MB-context DoS at the ability adapter. Truncate-store keeps individual log lines readable.
- **Reference plugin doesn't have this**: `mcp-abilities-filesystem.php` has no context field. This is an original contribution — the 500-char store limit is a judgment call. 500 is enough for "fixing customer ticket #12345; rollback to previous config if breaks" but short enough that the entry stays on one visual page.
- **Deterministic truncation**: Callers who send 1500 chars know they'll see the first 500 in the log — same behaviour every time.

**Alternatives considered**:
- No limit: rejected. Enables log-spam attack.
- Same 2000-char store limit: rejected. Makes log entries several screens tall for verbose callers.
- Store only truncated hash: rejected. Loses the actual context; defeats the accountability goal.

---

## Decision 6 — Delete_File inline `.bak.<time>` → centralised (BREAKING)

**Decision**: `Delete_File.php` calls `Audit_Trail::write_backup()` when `backup_enabled=true`. Its response gains a new `backup_path` field (the centralised backup location). The legacy `backup` field is deprecated but populated with the same value for one transition release. When `backup_enabled=false`, both fields are absent — no backup written at all.

**Rationale**:
- **Consistency**: All ten mutation abilities share the same backup semantics. Delete_File was the odd one with an inline scheme.
- **Storage locality**: Centralised backups live in one dir the admin can find and clean up. Inline `.bak.<time>` files scattered next to originals get lost.
- **Retention**: Centralised dir participates in the amortised cleanup. Inline files never got cleaned up before.
- **Transition kindness**: Populating both `backup` and `backup_path` for one release lets callers migrate without a hard break — deprecation notice in the CHANGELOG.

**Alternatives considered**:
- Keep the inline scheme AND add centralised backup: rejected. Two backup copies per delete doubles disk cost, confuses the log entry ("which backup do I reference?"), and doesn't help anyone.
- Migrate silently, drop `backup` field immediately: rejected. Real callers may be reading that field. One-release transition is polite.

---

## Decision 7 — Log format: human-readable multi-line text (not JSON)

**Decision**: Each entry is:

```
[YYYY-MM-DD HH:MM:SS UTC] <OPERATION>
  Ability: file-manager/<slug>
  File: <abs path>
  User: <email> (ID:<id>) IP:<ip>
  Size: <before> -> <after> bytes
  Destination: <abs path>              (move/copy only)
  Backup: <path | SKIPPED (...) | FAILED (...)>
  Context: <sanitised text or empty>
```

Blank line separates entries.

**Rationale**:
- **Grep-friendly**: `tail -f`, `grep 'DELETE'`, `awk '/^\[/' log` all Just Work. Admins troubleshooting incidents want text.
- **Reference plugin parity**: `mcp-abilities-filesystem.php:150-168` uses the same shape. Consistent with what admins already read on other sites.
- **Deterministic parsing**: The blank-line delimiter + fixed key order makes both `get-changelog` and third-party subscribers via `acrossai_file_manager_log_entry` action parseable.
- **JSON export is a follow-up**: When someone needs structured audit-log ingestion, they subscribe to the action hook and emit JSON themselves. This plugin doesn't dictate the format.

**Alternatives considered**:
- JSON-lines (`{ts, op, file, user, ...}\n`): rejected. Not scannable by admins; every parser has to `jq` it. Better to emit rich structured data via the action hook and let subscribers format for their target.
- CSV: rejected. Multi-value fields (like Size: 100 -> 200) don't fit; escaping commas in paths is annoying.
- Structured but multi-line (YAML-like): rejected. YAML in log files is a nightmare to grep.

---

## Decision 8 — `acrossai_file_manager_log_entry` action hook

**Decision**: After every successful log write, `do_action('acrossai_file_manager_log_entry', array $entry)` fires. `$entry` is the parsed assoc array (same fields as the log entry).

**Rationale**:
- **Extensibility without core modification** (Constitution §V): third parties integrate without touching this plugin.
- **Cheap**: One `do_action` call per log write. Zero cost when no subscribers.
- **Standard WP pattern**: Every log-emitting plugin has one. WP core's `wp_die`, WooCommerce's order events, etc. all follow this shape.

**Alternatives considered**:
- No hook: rejected. Third parties would have to poll the log file — slow and racy.
- REST webhook: rejected. Adds surface (endpoint, auth, delivery guarantee) for a use case action-hooks solve cleanly.

---

## Non-decisions (spec-locked)

- **`get-changelog` ordering**: chronological (oldest first, newest last) — spec FR-014, matches log's append order.
- **`get-changelog` line range**: min 1, max 500, default 100 — spec FR-008, matches reference plugin.
- **Uninstall**: obeys `acrossai_abilities_uninstall_delete_data` opt-in — spec FR-018.
- **No log rotation**: retention trim handles growth over time — spec Non-goals + Constraints.
- **Backup + log run AFTER Hardening_Enforcer**: spec Assumptions + B-8 in briefing.
