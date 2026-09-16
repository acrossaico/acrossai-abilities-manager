# Feature 119 — Email delivery toolset, and adopting the anti-spam abilities

Prepared for `/speckit-specify`. Every claim was read from WP Mail SMTP 4.9.0 and Akismet on this
install, with file and line cited, or measured live.

## Why this one, ahead of bigger install bases

Not reach. It makes what this plugin already ships **reliable**.

We register abilities that create Contact Form 7 forms (#103) and adopt WPForms' (#117) — forms whose
notifications may never arrive. Across 739 abilities nothing can answer *"why did that email not
send?"*. Measured on this site: the mailer is **`mail`**, PHP's own `mail()`, which is the default and
the configuration that silently fails on most hosts.

## What the two plugins already register, and where it lands

| Ability | Plugin | Currently |
|---|---|---|
| `wp-mail-smtp/get-debug-events` | WP Mail SMTP Lite | catch-all |
| `akismet/get-stats` | Akismet | catch-all |
| `akismet/comment-check` | Akismet | catch-all |

Measured — all three resolve to `other`. The position WPCode's five, WPForms' eight and WooCommerce's
were in.

WP Mail SMTP registers through its own `AbilityRegistrar` (`src/Abilities/AbilityRegistrar.php:164`)
under the namespace `wp-mail-smtp`, and its category description says *"Read-only access to WP Mail
SMTP email logs, statistics, and debug events"* — Lite ships one, the rest are Pro. Claiming the
prefix now means those arrive filed correctly without another release. Akismet registers two through
`class-akismet-abilities.php`, category `akismet`.

Both are adopted, never re-registered.

## The trap: a settings reader built the obvious way leaks passwords

`wp_mail_smtp` holds the mailer credentials — `pass`, `client_secret`, `api_key` across every mailer
(`src/Options.php:42-97`). They are stored **encrypted**, and `Options::get()` **decrypts them on
read**:

```php
// src/Options.php:439
return Crypto::decrypt( $this->options[ $group ][ $key ] );
```

So a reader built on the plugin's own Options API — the natural way to write it — hands out plaintext
SMTP passwords and API keys. `options/get-option` is less bad but still returns the ciphertext blob.
This is the Feature 106 lesson about Yoast's stored tokens and the Feature 118 lesson about the
consent token, with a sharper edge: here the plugin decrypts for you.

**No ability returns a credential value.** The reader is an explicit allow-list that reports only
*whether* each credential is set, and lists what it withheld.

| The state | Reachable by | Why we still wrap it |
|---|---|---|
| `wp_mail_smtp` option | `options/get-option` | Returns credentials; nothing describes the keys |
| Whether mail actually works | — | Not derivable from settings at all; needs a real send |
| Conflicting mail plugins | — | Needs `Conflicts`, which knows the rival list |
| Last send failure | adopted `get-debug-events` | Already theirs |

## Scope — 4 built, 3 adopted, 2 groups

New namespace **`email/`** (unused across 37), tab group `email`; plus tab group `anti-spam` for
Akismet's two. Both groups appear only when their plugin is active.

- `email/get-delivery-settings` — mailer, from address and name, force-from flags, and which
  credentials are **set** (never their values), with the withheld list reported.
- `email/get-delivery-status` — the composite health answer: is a mailer configured, are its
  credentials present, is another mail plugin fighting it (`Conflicts::is_detected()`,
  `get_all_conflict_names()`), and what the last recorded failure was (`Debug::get_last()`).
- `email/send-test-email` — the only thing that actually proves delivery. Goes through
  `WPMailSMTP\TestEmail\TestEmail`, which also runs a domain check, and returns the real error when
  it fails. Confirm-gated: it sends a real email to a real person.
- `email/update-delivery-settings` — from name, from email and the force-from flags **only**.

**Not writable, deliberately:** the mailer choice and every credential. Switching mailer without
credentials breaks all mail on the site, and writing secrets is out on the same grounds as #118.

## Constraints — traps, not preferences

- **Never return a credential**, and never call `Options::get()` for a credential key at all — report
  presence by checking the raw stored value, so a decrypt never happens.
- **The test email sends to a real address.** Confirm-gated, the recipient is required and never
  defaulted to the admin, and the response says plainly that a message was delivered to a person.
- **Report failure honestly.** `is_successful()` false must surface `get_result()`, not a generic
  refusal — the error text is the entire value of the ability.
- **Adopt with a BARE prefix** — `wp-mail-smtp`, `akismet`. The tagger matches the segment before the
  first slash, so a trailing slash never matches (#209).
- **Akismet's two keep their own gates**, now that #210 wraps rather than replaces them.
- **Floor `manage_options`**; `final` classes; rows never maps in `array`-typed output; raise-only
  permission filter.

## Verification

1. Execute all four live, plus both negative paths and the confirm gate.
2. **The credential proof.** Set a mailer password, then assert no ability's output contains it —
   scanned against the actual stored value, the way #118 was checked once a real token existed.
3. **The failure proof.** Send a test email with a deliberately broken mailer and confirm the real
   error reaches the caller rather than a generic failure.
4. Confirm the three adopted abilities move from `other` into `email` and `anti-spam`.
5. `sum(counts) === total`; dispatch through `toolset/email` and `toolset/anti-spam`; deactivate each
   plugin and confirm its tab and tool disappear.
6. Full `phpunit`, full `phpcs`, PHPCompatibility over `includes/`.

## Out of scope

- **Anything that writes a credential or chooses a mailer.**
- **Email logs and reports** — Pro. The category description advertises them; Lite ships only debug
  events, and building against a Pro surface we cannot execute here is the #106 exclusion.
- **Akismet key management** — an account credential, the #118 rule.
- **Sending arbitrary mail.** A general "send an email as this site" ability is a spam vector and a
  different decision; the test send is a diagnostic to one address, confirm-gated.
