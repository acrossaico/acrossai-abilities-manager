# Feature 103 — Contact Form 7 abilities (inputs for /speckit-specify)

This file is the natural-language brief that `/speckit-specify` should turn into `spec.md`. Do not treat it as the spec itself.

## One-sentence goal

Add a Contact Form 7 ability suite — roughly 25 abilities under `contact-form-7/` — so an AI client can list, read, create and repair CF7 forms, including the form-tag markup and both mail templates, on a plugin that ships no Abilities API surface of its own.

## Why now

Contact Form 7 is the most-installed form plugin on WordPress (10M+ active installs) and one of the most common things a site owner asks an assistant to touch: "add a phone field to the contact form", "the notification email isn't arriving", "make a booking form".

**Gap:** CF7 v6.1.7 registers **zero** WordPress abilities. Verified across all 111 of its PHP files — no `wp_register_ability`, no `wp_register_ability_category`, no `wp_abilities_api_init`, no MCP code, no `AI/` directory. This is a deliberate contrast with the two vendors we already integrate: ACF ships `src/AI/Abilities/` (6 fixed + dynamic per CPT/taxonomy) and Rank Math ships `includes/abilities/` (27 of its own). CF7 has no equivalent whatsoever, so **every** CF7 ability must be written by us.

Without a suite, an assistant's only route to a CF7 form is the generic content abilities: read `post_content` of a `wpcf7_contact_form` post — which CF7 maintains purely as a flattened search dump, not as the authoritative record — and write post meta blind. The authoritative data lives in five separate meta keys (`_form`, `_mail`, `_mail_2`, `_messages`, `_additional_settings`), and writing them directly bypasses CF7's sanitisation and its config validator. That is a reliable way to produce a form that looks saved and silently does not send mail.

## Scope

### Abilities to add (25)

All under the `contact-form-7/` namespace, category `acrossai-contact-form-7`, `meta.acrossai.tab_group = 'contact-form-7'` — the suite gets its own toolset and MCP tool, mirroring Rank Math and Elementor. Five `sub_group` cards.

#### `cf7-forms` — the form lifecycle (8)

1. **`contact-form-7/list-forms`** — Enumerate every form. Per entry: id, hash, title, slug, locale, shortcode, field count, and config-error count so the caller can spot broken forms without a second call. Inputs: `search` (string, optional), `limit`/`offset`. Wraps `WPCF7_ContactForm::find()`.

2. **`contact-form-7/get-form`** — Full record for one form: all five properties (form template, mail, mail_2, messages, additional_settings) plus shortcode, locale, hash and config-validator errors. Inputs: `form_id`. Wraps `WPCF7_ContactForm::get_instance()` + `prop()`.

3. **`contact-form-7/find-form`** — Resolve a form by title or hash rather than id, because a caller usually knows the name a human used. Inputs: `title` or `hash` (one required). Wraps `wpcf7_get_contact_form_by_title()` / `wpcf7_get_contact_form_by_hash()`.

4. **`contact-form-7/create-form`** — Create a form, seeded from CF7's default template unless properties are supplied. Inputs: `title` (required), `locale`, and optional `form`, `mail`, `mail_2`, `messages`, `additional_settings`. Wraps `wpcf7_save_contact_form( [ 'id' => -1, … ] )`.

5. **`contact-form-7/update-form`** — Patch any subset of a form's properties in one call. Inputs: `form_id` (required) plus any of `title`, `locale`, `form`, `mail`, `mail_2`, `messages`, `additional_settings`. Omitted keys are untouched — this is `wpcf7_save_contact_form()`'s own semantics, not something we implement.

6. **`contact-form-7/duplicate-form`** — Copy a form under a new title. Inputs: `form_id` (required), `title` (optional; CF7 defaults to `{title}_copy`). Wraps `copy()` then `save()`, because `copy()` alone returns an unsaved object.

7. **`contact-form-7/delete-form`** — Delete a form permanently. Inputs: `form_id`, `confirm`. **Destructive**: CF7's `delete()` calls `wp_delete_post( $id, true )` — it bypasses the trash and there is no recovery.

8. **`contact-form-7/get-form-shortcode`** — Return the `[contact-form-7 id="{hash}" title="{title}"]` string for embedding. Inputs: `form_id`. Separate from `get-form` because "how do I put this on a page" is the single most common follow-up and should not require pulling the whole record.

#### `cf7-fields` — the form template and its tags (7)

This group is the reason the suite is worth building rather than shipping forms CRUD alone: CF7's form-tag syntax is the fiddly part, and hand-writing it is where a model most reliably produces something that looks right and does not work.

9. **`contact-form-7/list-form-fields`** — Parse a form's template and return each field as structured data: tag type, name, required flag, options, default value, and the raw tag text. Inputs: `form_id`. Wraps `WPCF7_ContactForm::scan_form_tags()`.

10. **`contact-form-7/list-field-types`** — Every form-tag type registered on this install, with the features each supports (`name-attr`, `do-not-store`, `not-for-mail`, …). Inputs: none. Wraps `WPCF7_FormTagsManager::collect_tag_types()`. This is install-specific — CF7 modules and add-ons register types — so it cannot be a static list in the model's head.

11. **`contact-form-7/add-form-field`** — Append or insert a field into the template, emitting both the label wrapper and the tag. Inputs: `form_id`, `type`, `name`, `required` (bool), `label`, `options` (array), `placeholder`, `position` (enum: append / prepend / before-field / after-field) + `relative_to`.

12. **`contact-form-7/update-form-field`** — Change an existing field in place by name: type, required flag, options, default. Inputs: `form_id`, `name` (required) plus the fields to change. Leaves the rest of the template byte-identical.

13. **`contact-form-7/remove-form-field`** — Remove a named field and its label wrapper. Inputs: `form_id`, `name`, `confirm`. Confirmation because removing a field the mail template still references silently breaks the notification — `validate-mail-tags` (#20) is the paired check.

14. **`contact-form-7/get-form-template`** — The raw `form` property, for a caller that wants to reason about the markup directly rather than field by field. Inputs: `form_id`.

15. **`contact-form-7/update-form-template`** — Replace the whole template. Inputs: `form_id`, `form`. The escape hatch when the structured field abilities cannot express the change; the caller owns correctness, so the description should point at `validate-form-config` afterwards.

#### `cf7-mail` — notification templates (5)

16. **`contact-form-7/get-mail`** — One of the two mail templates: subject, sender, recipient, body, additional headers, attachments, `use_html`, `exclude_blank`, and whether it is active. Inputs: `form_id`, `which` (enum: `mail` | `mail_2`, default `mail`).

17. **`contact-form-7/update-mail`** — Patch a mail template. Inputs: `form_id`, `which`, plus any of the fields above, and `confirm`. **Confirmation is required when `recipient` changes** — that field decides where every submission of this form is delivered, and CF7 only `trim()`s it.

18. **`contact-form-7/toggle-mail-2`** — Turn the optional second mail (the autoresponder) on or off without touching its content. Inputs: `form_id`, `active` (bool). Separate from `update-mail` because enabling an unconfigured mail_2 sends blank autoresponders.

19. **`contact-form-7/list-mail-tags`** — Every mail tag available to a given form: the `[your-name]`-style tags derived from its own fields, plus CF7's special tags (`[_site_title]`, `[_post_url]`, `[_date]`, …). Inputs: `form_id`. This is what a caller needs before writing a mail body.

20. **`contact-form-7/validate-mail-tags`** — Check that every tag used in a form's mail templates resolves against that form's fields, and report the ones that do not. Inputs: `form_id`, optional `which`. This is the commonest silent CF7 breakage: rename a field and the notification keeps sending with an empty line where the value was, with no error anywhere.

#### `cf7-messages` — validation and status text (2)

21. **`contact-form-7/get-messages`** — The full message set with each slug's current and default text, so a caller can see what has been customised. Inputs: `form_id`.

22. **`contact-form-7/update-messages`** — Patch specific message slugs. Inputs: `form_id`, `messages` (object keyed by slug). Unknown slugs are rejected rather than silently dropped.

#### `cf7-settings` — behaviour switches and validation (3)

23. **`contact-form-7/get-additional-settings`** — The parsed `key: value` lines with their effects explained. Inputs: `form_id`.

24. **`contact-form-7/update-additional-settings`** — Write **whitelisted** settings only: `demo_mode`, `skip_mail`, `subscribers_only`, `acceptance_as_validation`. Inputs: `form_id`, `settings` (object), `confirm`. CF7 does not sanitise this property at all — only `trim()` — and `skip_mail: on` silently disables all mail for the form, so this ability must not be a free-text passthrough.

25. **`contact-form-7/validate-form-config`** — Run CF7's own `WPCF7_ConfigValidator` and return the errors per section with their codes and messages. Inputs: `form_id`. The natural last step of any authoring sequence, and the ability that turns "I edited the form" into "the form works".

### Explicitly out of scope for this feature

- **Submissions.** CF7 stores nothing. `WPCF7_Submission` is a per-request singleton — no table, no CPT, no option, no transient — and persistence is delegated to Flamingo via `modules/flamingo.php`, which no-ops unless Flamingo is installed. **Flamingo is not installed on this site.** There is no "list submissions" ability to build here. A `cf7-submissions` sub-group gated on `class_exists( 'Flamingo_Inbound_Message' )` is a clean follow-up feature if anyone asks.
- **Third-party service credentials** — reCAPTCHA, Turnstile, Stripe, Sendinblue, Constant Contact. CF7 stores these as global options, they are the most security-sensitive surface in the plugin, and writing an API key is not something this suite should make easy.
- **Front-end form submission.** Filling in and submitting a form on behalf of a visitor is a different job with a different threat model. The `webmcpfy-contact-form-7` plugin already does it as browser-side WebMCP tools.
- **Per-form spam and Akismet configuration** — depends on the credentials above.

## Prior-art check (2026-09-12)

Verified against the installed plugin set on this site plus the plugin's own source:

- **Contact Form 7 itself (6.1.7)** — zero abilities. Exhaustive grep across all 111 PHP files: no `wp_register_ability`, no `wp_register_ability_category`, no `wp_abilities_api_init`, no `mcp`, no `AI/` directory. Its only programmatic surface is the `contact-form-7/v1` REST namespace (5 routes: list, single CRUD, feedback, feedback schema, refill).
- **`webmcpfy-contact-form-7` (1.0.2, ibsofts)** — installed but inactive. Generates **WebMCP browser tools for submitting** CF7 forms. Contains no `wp_register_ability` calls; a different mechanism aimed at the front end. No overlap with this suite.
- **Elementor MCP** — registers `cf7-read` and `cf7-write` (recorded in `docs/elementor-plugins-abilities-inventory.md:86-87`): two blunt tools covering the whole of CF7. That is the shape Feature 100 deliberately moved away from — a caller cannot tell what `cf7-write` will do, and its schema cannot describe the union of every CF7 write.
- **This plugin** — no CF7 code at all. `grep -ril "wpcf7\|contact.form.7" includes/ admin/ src/` returns nothing.

**Implication:** no naming or namespace collisions. `contact-form-7/*` is unclaimed, and because CF7 registers nothing of its own there is no risk of the ability-name collision that already exists between this plugin and Rank Math (both register `rank-math/get-settings`; WordPress keeps whichever registers first).

## Constraints

### Wrap CF7's own save function, not the object setters

`wpcf7_save_contact_form( $data, $context )` (`includes/contact-form-functions.php:294`) is the canonical write path — both CF7's REST controller and its admin screen use it. It sanitises every property, and any key left `null` is untouched, which is exactly the patch semantics the update abilities want. Pass `id => -1` to create. Do **not** reach for `set_properties()` + `save()`: that skips sanitisation entirely, and `set_properties()` silently drops unknown keys.

### `unfiltered_html` bypasses CF7's sanitisation — do not rely on it

`wpcf7_sanitize_form()` (`includes/contact-form-functions.php:366`) runs `wpcf7_kses()` **only for users without `unfiltered_html`**. The same conditional guards the mail body. On a single-site install an administrator has that capability, so an admin-context ability can write `<script>` straight into a form template or a notification body. Every writer in this suite should call `wpcf7_kses()` unconditionally rather than inheriting CF7's caps-dependent behaviour — an LLM-authored payload is not the trusted-admin input that exemption was written for.

### `additional_settings` is not sanitised at all

`wpcf7_sanitize_additional_settings()` does only `trim()`. The values are behaviour switches: `skip_mail: on` silently disables all mail for the form, `demo_mode: on` stops storage. Ability #24 must whitelist the writable keys and require confirmation for the behaviour-changing ones, rather than accepting free text.

### High-risk fields

- **`mail.recipient`** decides where every submission goes and is only `trim()`ed plus newline-stripped at send time. Require `confirm` when a write changes it.
- **`mail.attachments`** is a filesystem path list. CF7 confines it to `wp-content` (`includes/mail.php:270-284`), but that still permits attaching another plugin's config or a backup file. Restrict or confirm.
- **`delete-form`** force-deletes with no trash (`includes/contact-form.php:1341`).

### Capabilities and the permission floor

CF7's own caps are `wpcf7_read_contact_forms`, `wpcf7_edit_contact_forms`, per-form `wpcf7_edit_contact_form`, and `wpcf7_delete_contact_form` (`includes/capabilities.php:7-12`). They map to `WPCF7_ADMIN_READ_WRITE_CAPABILITY` (default `publish_pages`) and `WPCF7_ADMIN_READ_CAPABILITY` (default `edit_posts`), both `define`-overridable by site owners — so always check the `wpcf7_*` caps, never the raw primitives.

**Floor every ability at `manage_options`, declared `final`, and check the CF7 cap on top.** This is not an open question: `Base_Rank_Math_Ability::permission_floor()`'s docblock is a post-mortem of doing otherwise. An earlier revision lowered that suite's floor to `edit_posts` for post-scoped abilities and opened a real hole, because the host plugin grants its own caps to Author and Editor by default. CF7 has exactly the same shape — its caps resolve to `edit_posts` for reads. Per-object `current_user_can( 'wpcf7_edit_contact_form', $form_id )` stays inside `run()` as defence in depth.

**State the trade explicitly in the spec:** this means an Editor who can manage forms in wp-admin today cannot manage them through an ability. That is deliberate — an ability is reachable by an AI client, wp-admin is not — but it should be a recorded decision rather than a side effect.

### Suite conventions (non-negotiable, enforced by tests)

- **A single suite base is the sole `ability()` assembler**, following `Base_Rank_Math_Ability`. Subclasses implement `run()` and metadata accessors only, and never override `ability()` or `execute()`.
- **No ability class may name a `WPCF7_*` symbol.** All host access goes through a new `includes/Abilities/Utilities/ContactForm7/` — a guard plus a form repository. Rank Math has an architecture test asserting exactly this; the CF7 suite needs its equivalent.
- **Envelope**: every `output_schema` is `success` + payload + `message` + `error_code`, `required => array( 'success' )`, `additionalProperties => false` on both schemas.
- **`confirm` must never be schema-required.** Core validates `input_schema` before `execute()` runs, so a required `confirm` produces a generic `ability_invalid_input` and the confirmation gate never fires — the caller never sees the message naming the flag. The base should strip it defensively.
- **Guard order is structural**, enforced by the base: availability → confirmation → `run()` → envelope.
- **Slugs are verb-first kebab-case** (`DEC-SLUG-CONVENTION-VERB-FIRST`) under the host namespace, as Rank Math and Elementor do.
- **`Slash_Input::schema_fragment()` / `slash()`** on every writer, since they reach `wp_insert_post()` underneath `wpcf7_save_contact_form()`.
- **`suggested_abilities()` hints** (Feature 095): `update-form` → `get-form` first; `add-form-field` → `list-field-types`; `update-mail` → `list-mail-tags`; `update-form-template` and `remove-form-field` → `validate-form-config`.

### Registration is conditional on CF7

Gate on `class_exists( 'WPCF7_ContactForm' )` at bootstrap — the Elementor shape, not Rank Math's. Rank Math registers unconditionally and gates at runtime because its Content AI features depend on a cloud account that can change without a plugin activation; CF7 has no equivalent, so a boot-time gate is correct. Keep a runtime `assert_available()` in the guard anyway, since CF7 can be deactivated after registration within the same request.

## Non-goals

- No changes to the generic content abilities. They can already read the `wpcf7_contact_form` CPT; this suite is about the five meta properties they cannot safely write.
- No admin UI. Abilities appear in the Custom Abilities table and the toolset strip through the standard registration path.
- No CF7 add-on coverage (Conditional Fields, Multi-Step, etc.) in this feature.
- No back-compat concerns — entirely additive.

## Files that will likely be touched (all new unless noted)

- `includes/Abilities/ContactForm7/Base_Contact_Form_7_Ability.php` — the sole `ability()` assembler; `CATEGORY = 'acrossai-contact-form-7'`, `TAB_GROUP = 'contact-form-7'`, `sub_group_labels()`, `final permission_floor()`
- `includes/Abilities/ContactForm7/<Verb>_<Subject>.php` × 25
- `includes/Abilities/ContactForm7/Category_Registrar.php` — singleton, `register()` short-circuits on `class_exists( 'WPCF7_ContactForm' )`
- `includes/Abilities/Utilities/ContactForm7/Contact_Form_7_Guard.php` — `assert_available()`, `can( $cap, $floor )`, `assert_confirmed()`, `ok()` / `fail()` / `error()`
- `includes/Abilities/Utilities/ContactForm7/Form_Repository.php` — every `WPCF7_*` call lives here
- `includes/Abilities/Utilities/ContactForm7/Form_Tag_Repository.php` — tag parsing and template mutation for #9–#15
- `includes/Abilities/Integrations/Contact_Form_7.php` — implements `AcrossAI_Toolset_Integration`; `public const TAB_GROUP` must equal the base's. **This is the whole toolset** — tab, counts, filter and MCP dispatcher all derive from it, so there is **no `includes/Abilities/Toolset/` file** and no `$claimed` entry
- `includes/Abilities/Integrations/AcrossAI_Toolset_Integrations.php` — *modified*: add to `built_in()`
- `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` — *modified*, three edits: the category `add_action` line near `:75`; the `class_exists` gate in `register_abilities()`; a `register_contact_form_7_abilities()` method holding one `new` per ability
- `tests/phpunit/Modules/Library/Test_Ability_Group_Map.php` — *modified*: append `'ContactForm7'` to **both** skip arrays (`:131` and `:248` — they are duplicated and must stay in sync). Vendor suites are not swept; miss this and the sweep demands `WHOLE_FOLDER` / `EXPECTED_COUNTS` entries and fails
- `tests/phpunit/abilities/Toolset/Test_Toolset_Group_Coverage.php` — *modified*: add the new integration class to its `require_once` block, or `built_in()` fatals in the WP-less bootstrap
- `phpunit.xml.dist` — *modified*: one `<file>` entry per new test file (this file uses explicit entries, not directory globs)
- `README.txt` — *modified*: changelog

`docs/abilities-inventory.md` is generated — run `php scripts/generate-abilities-inventory.php` after implementation rather than editing it.

## Existing code to reuse

- `Base_Rank_Math_Ability` — copy its structure wholesale: the constants, the abstract contract, the `confirm`-stripping in `ability()`, the guard ordering in `execute()`, the three-tier `sub_group_label` resolution
- `Rank_Math_Guard` — the model for the CF7 guard: `can()` returning a `permission_callback` closure with a filter, `ok()`/`fail()`, `error()` stripping `success`/`message`/`error_code` from echoed context so a caller cannot spoof success
- `AcrossAI_Toolset_Integration` + `AcrossAI_Toolset_Integrations` (Feature 184) — the declaration that yields the toolset and dispatcher
- `Ability_Definition` — the base of the base; also where `suggested_abilities()` / `suggested_plugins()` are injected
- `Slash_Input::schema_fragment()` / `slash()` — for every writer
- `tests/phpunit/abilities/Test_Rank_Math_Architecture.php` and `Test_Rank_Math_Suite_Contract.php` — the two test files to mirror method-for-method; the contract test pins the exact ability count and slug list, which is the point

## Verification (what "done" looks like)

**Prerequisite: activate Contact Form 7.** It is installed but inactive on this site, so none of the below is observable until it is on — a reader who skips this step will conclude the suite is broken.

- All 25 abilities register when CF7 is active and none register when it is not
- A **Contact Form 7** toolset tab appears with the count; `?tab=contact-form-7` deep-links; rows show `toolset/contact-form-7`; the tab and the `toolset-contact-form-7` MCP tool both disappear when CF7 is deactivated
- `sum(counts) === total` still holds on `GET /acrossai/v1/abilities/toolsets`
- End to end on a real form: `create-form` → `add-form-field` (a required email field) → `update-mail` (referencing that field) → `validate-form-config` reports no errors → the form renders on a page and a submission delivers with the field's value present
- `validate-mail-tags` reports the breakage when a field is renamed but the mail body is not updated
- `update-form` patches one property and leaves the other four byte-identical
- `delete-form` refuses without `confirm: true`, and `update-additional-settings` refuses a key outside the whitelist
- A writer called by a user with `unfiltered_html` still has `<script>` stripped from the form template
- `composer phpcs`, `composer phpstan` (level 8), `vendor/bin/phpunit`, `npx wp-scripts test-unit-js`, `npm run build`, `npm run validate-packages`

## Suggested next steps (for the human)

1. `/speckit-specify` — read this brief and produce `specs/103-contact-form-7-abilities/spec.md`
2. `/speckit-clarify` — the permission floor and the `additional_settings` whitelist are the two decisions worth pinning before planning
3. `/speckit-plan` — turn the spec into `plan.md` and `contracts/abilities.md` (the `| # | Slug | Input | Output payload |` table, following `specs/069-rank-math-abilities/contracts/abilities.md`)
4. `/speckit-tasks` — decompose into `tasks.md`
5. `/speckit-implement` (or hand-execute) — build

I have NOT written spec.md / plan.md / tasks.md. Those are for the /speckit-* workflow you invoke.
