# Feature 116 — Loco Translate toolset: 14 abilities

Prepared for `/speckit-specify`. Every claim below was read from Loco Translate 2.8.8 source on this
install, with file and line cited.

## Loco registers no abilities of its own

Verified by grepping `wp_register_ability`, `wp_abilities_api_init` and `abilities` across the whole
plugin including `src/`, `lib/` and `pub/`: **zero matches**. So unlike WPCode, Yoast or Rank Math
there is nothing to adopt — every ability in this group is ours, and `ability_prefixes()` is empty.

Loco is installed but **not active** on this install, and there are no translations at all:
`wp-content/languages` does not exist, and there are zero `.po`, `.mo`, `.json` or `.l10n.php` files
anywhere under it. WordPress here is **7.1**, which matters below.

## Nothing we already ship touches translations

Three abilities mention translation and none of them are about this: `content/list-post-translations`,
`content/link-post-translation` and `content/set-post-language` are Polylang/WPML post-level
concerns. There is no ability that can read a `.po`, list a text domain, or report what is
untranslated.

## Why the generic file abilities are not enough

The 23 `file-manager/` abilities can read and edit any file, a `.po` included. **That route produces
translations that never appear on the site.**

`Loco_gettext_Compiler::writeAll()` writes **four** artefacts from one save
(`src/gettext/Compiler.php`):

| Artefact | Written by | Why it matters |
|---|---|---|
| `.po` | `writePo()` | Human-editable source. **Nothing reads it at runtime.** |
| `.mo` | `writeMo()` → `msgfmt()` (line 90) | What `load_textdomain()` historically reads. Binary, so a text edit corrupts it. |
| `.l10n.php` | line 116, gated on `class_exists( 'WP_Translation_File_PHP' )` | The WordPress 6.5+ translation cache. **This site is WordPress 7.1, so this is what WP prefers.** |
| `.json` | `writeJson()` → `msgjed()` (lines 138, 200) | One JED fragment per JS reference, for `wp.i18n` in the block editor. |

Editing a `.po` through `file-manager/edit-file` therefore leaves **all four out of step**: the file
says one thing and the site renders another, with no error anywhere. Even a caller who knew to
recompile the `.mo` would still be wrong on WP 6.5+, because WordPress reads the `.l10n.php` in
preference. Same failure shape as `BUG-WRITE-REPORTED-WITHOUT-READ-BACK` — the write succeeds and
changes nothing visible.

| The state | Already handled by | Why we still wrap it |
|---|---|---|
| `.po` / `.pot` contents | `file-manager/read-file`, `edit-file` | Leaves `.mo`, `.l10n.php` and `.json` stale |
| `.mo` | — | Binary; a text edit corrupts it |
| Loco settings | `options/get-option`, `options/update-option` | `loco_settings` is reachable but undescribed |
| Which bundles and text domains exist | — | Not derivable from the filesystem; needs Loco's bundle configuration |

## The constraint that shapes the implementation

**Loco's WP-CLI commands cannot be reused.** `Loco_cli_SyncCommand::run()`, `Loco_cli_ExtractCommand`
and `Loco_cli_FetchCommand` call `WP_CLI::log()` and `WP_CLI::success()` directly
(`src/cli/SyncCommand.php:24`), so invoking them from an ability is a fatal outside WP-CLI. The suite
goes through the layer underneath:

- `Loco_package_Plugin::getAll()` / `Loco_package_Theme::getAll()` / `Loco_package_Core::create()`
- `Loco_gettext_Data::load()` / `msgfmt()` / `msgjed()`
- `Loco_gettext_Compiler::writeAll()` — the four-artefact write
- `Loco_gettext_Extraction` — xgettext
- `Loco_api_WordPressTranslations` — WordPress.org language packs

## Scope — 14 abilities

New category `acrossai-loco-translate`, `tab_group => 'translations'`, namespace **`translations/`**
(unused across all 35 namespaces). Own toolset gated on Loco being active.

- **Discovery (4)** — `list-bundles`, `get-bundle`, `list-locales`, `get-translation-status`.
- **Reading (2)** — `list-strings`, `get-string`.
- **Writing (3)** — `update-strings`, `create-translation-file`, `delete-translation-file`.
- **Maintenance (3)** — `compile-translations`, `sync-translations`, `extract-strings`.
- **WordPress.org (2)** — `list-available-languages`, `fetch-translations`.

## Constraints — traps, not preferences

- **Never write a translation file directly.** Everything routes through `Loco_gettext_Compiler`
  behind one repository, asserted by an architecture test.
- **Prove the write per artefact.** `writeAll()` populates `pobytes`, `mobytes`, `numjson` and
  `phbytes`. Report all four rather than a boolean: a `.po` that saved while the `.l10n.php` did not
  is precisely the bug this suite exists to prevent, and only a per-artefact count makes it visible.
- **`.l10n.php` exists only on WP ≥ 6.5.** A `phbytes` of 0 is correct on older WordPress and a
  failure on this one, so `get-translation-status` reports which case applies.
- **Writes need a writable target.** `wp-content/languages` does not exist here. Loco routes through
  `Loco_api_WordPressFileSystem`, which can demand FTP credentials; refuse by name rather than
  half-write.
- **A locale is not a free string.** Route through `Loco_Locale::parse()` and refuse an unparseable
  tag rather than creating `wp-content/languages/plugins/foo-nonsense.po`.
- **Confirm-gate** `delete-translation-file`, and `extract-strings` because rebuilding a POT can
  orphan existing translations.
- **Floor `manage_options`.** Loco's own capability is `loco_admin` and it creates a `translator`
  role; neither is a safe floor for an AI client.

## Verification

Every one of the 14 executed live against an activated Loco — no sampling. Beyond the per-ability
matrix:

1. **The four-artefact proof.** Create a PO for a real bundle and locale, write a translation, then
   read the directory from disk and confirm `.po`, `.mo`, `.l10n.php` and `.json` all exist with
   non-zero size. The measurement that justifies the suite, taken rather than asserted.
2. **The counter-proof.** Edit the same `.po` through `file-manager/edit-file` and confirm the `.mo`
   and `.l10n.php` are now stale and still carry the old string; then run `compile-translations` and
   confirm they catch up.
3. **Render proof** — the translated string actually appears, not merely that files changed.
4. Negative paths: unknown bundle, unparseable locale, missing confirm, unwritable target, no POT.
5. Deactivate Loco and confirm the tab and the MCP tool disappear.
6. Restore state — delete everything the run created and leave `wp-content/languages` as found.

## Out of scope

- **Paid API integrations** (DeepL, Google Translate, Loco.app sync) — third-party credentials this
  install does not have, so they would ship unverified.
- **The bundle configuration editor** — a packaging concern for plugin authors, not a translation
  task.
- **Translation memory** — a suggestion engine, which is what the AI client already is.
