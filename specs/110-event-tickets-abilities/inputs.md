# Feature 110 — Event Tickets ability suite

Inputs for `/speckit-specify`. Records the gap analysis, the stance on personal data, and the
measurements that changed the implementation.

## One-sentence goal

Add 16 abilities for Event Tickets under its own toolset — tickets and their real capacity model,
attendance and check-in, orders and sales — writing through the plugin's own save path and treating
attendee data as personal by default.

## Why now

Event Tickets 5.29.4 is active and **registers no abilities of its own** — verified across the whole
plugin including its bundled common library and build output: zero matches for
`wp_register_ability`, `wp_abilities_api_init` or `WP_Ability`.

**It is a separate toolset from The Events Calendar because it is a separate plugin.** Event Tickets
has no dependency header on it, probes for it only defensively, and falls back to a literal post-type
string when it is absent. Tickets attach to whatever is in `ticket-enabled-post-types`, which
defaults to **events and pages**. A site can sell tickets on pages with no calendar installed, so
gating this suite on the calendar would hide working functionality. An architecture test forbids any
reference to `Tribe__Events__Main` in the suite.

## The measurement that shapes the write path

`Tribe__Tickets__Tickets::update_capacity()` reconciles stock against what has already been sold:

```php
$data['stock'] -= $totals['pending'] + $totals['sold'];
```

Writing `_tribe_ticket_capacity` and `_stock` directly — which `content/update-post-meta` will do —
skips that and double-counts every existing sale. On a paid event that is overselling or
underselling real inventory. Every write here goes through `ticket_add()`, which calls
`update_capacity()` for us, and an architecture test forbids meta writes anywhere in the suite.

**`tribe_tickets()->create()` cannot be used**: the default repository spans every provider and
returns `false` by design when it cannot tell which one is meant.

## Capacity is four modes, not one number

| Mode | Meaning |
|---|---|
| `own` | independent stock |
| `global` | draws from the event's shared pool |
| `capped` | draws from the pool, up to `_global_stock_cap` |
| unlimited | capacity `-1`, mode stored as an empty string |

Flattening this to an integer makes an unlimited ticket and a sold-out one identical. Every read
reports capacity, mode, stock, sold, pending and available separately, and
`tickets/get-capacity-report` adds the event-level pool because a ticket in `global` or `capped` mode
has no meaningful capacity of its own.

## The stance on personal data

Attendee records are personal data — Event Tickets registers GDPR exporters for them. An attendee
list handed to an AI client leaves the site and cannot be recalled. So:

- **Aggregate is the default.** `get-attendee-summary` returns counts and per-ticket breakdowns with
  nothing identifying, and answers most real questions.
- **Names and emails are opt-in** via `include_personal_data`, and the response reports how many
  records it disclosed so the disclosure is visible in the log.
- **Listings are always paginated**, capped at 100 regardless of what is asked for.
- **`security_code` is never returned, under any flag.** It is the check-in credential printed on the
  ticket; disclosing it lets someone check in as another attendee. Excluded structurally, and an
  architecture test forbids the meta key appearing anywhere in the suite.
- **Gateway data is never returned** — payloads, processor order identifiers, customer references.

Gateway *credentials* need no denylist here: Tickets Commerce stores Stripe, PayPal and Square
tokens, webhook signing keys and PKCE verifiers as standalone option rows rather than in the shared
settings blob, and this suite reads only the blob.

## Scope — 16 abilities

Category `acrossai-event-tickets`, `tab_group = 'event-tickets'`, slug namespace `tickets/`.

| Sub-group | # | Abilities |
|---|---:|---|
| tickets | 5 | list, get, create, update, delete |
| capacity | 2 | capacity report, set capacity |
| attendees | 4 | summary, list, check in, undo check-in |
| orders | 3 | list, get, sales summary |
| setup | 2 | provider status, settings read |

Gateway configuration is deliberately absent — no ability writes or reads a payment credential.

## Bugs the probe found, all in this suite's own code

1. **`isset()` where `array_key_exists()` was needed.** A provider self-registers in its constructor
   but sets its display name on `init` priority 9, so the registry holds
   `[ 'Tribe__Tickets__RSVP' => null ]`. `isset()` is false for null, so the only active provider was
   refused — with an error that contradicted itself: *"not an active ticket provider. Active:
   Tribe__Tickets__RSVP."* The same null produced an empty provider label.

2. **The order repository defaults `post_status` to a single status** — whichever Tickets Commerce
   inserts new orders in. "List the orders" therefore returned only that state and omitted every
   completed, refunded and denied order. Now queries all statuses, read from the plugin's own status
   handler so an upstream addition is picked up, with `trash` excluded.

3. **A price-only update zeroed a ticket's capacity.** `ticket_add()` rebuilds the ticket from what
   it is handed, so an absent capacity block means "no capacity", not "unchanged". A ticket with 20
   seats came back with 0. Both writers now carry the current capacity forward and overlay the
   caller's values.

4. **Fixing that broke the empty-update guard**, because the capacity block made `$data` never
   empty. The guard now runs on caller input, before the preservation block.

5. **`Ticket_Object::capacity()` returns 0 for an unlimited ticket, not -1.** A ticket whose stored
   `_tribe_ticket_capacity` was `-1` read back as 0, which both hid unlimited tickets behind a
   sold-out-looking number and made a successful write look rejected. Reads now go through
   `tribe_tickets_get_capacity()`, which normalises to -1.

6. **`get_event_attendees()` does not guarantee a decorated record.** It returned plain post arrays
   keyed `ID`/`post_author`/`post_date` with none of the `attendee_id` or `holder_name` fields the
   decorated shape carries, so reading only the decorated keys produced empty rows. Fields are now
   read from the provider meta, which is what the decoration is built from anyway.

## Verification

Executed live. Tickets Commerce was enabled for the run so paid tickets and orders could be exercised
against real data, and switched back off afterwards.

- Both providers register with correct labels and the paid flag; ticketable types read from the site
  (`tribe_events`, `page`).
- **A paid Tickets Commerce ticket** created, read, updated, capacity changed 20 → 35 → unlimited →
  25, and deleted. Unlimited round-trips as `-1` / `unlimited`.
- **A price-only update preserves capacity** — the regression above, now covered.
- **PII policy proven**: default rows carry no purchaser fields and report `disclosed: 0`; with the
  flag they carry name and email and report `disclosed: 1`. A gateway payload deliberately planted as
  `never-surface` appeared in neither, and a security code planted as `SEC-DO-NOT-LEAK` appeared in
  neither.
- Eight negative paths, each with a named code: `unknown_ticket`, `unknown_order`,
  `unknown_attendee`, `post_type_not_ticketable`, `unknown_provider`, `invalid_input` (twice),
  `confirmation_required`.
- Toolset discover returns 16.
- **Eight architecture guards mutation-verified — after four of them initially failed to catch their
  mutation.** The cause was this suite's own `code_no_strings()` helper: it strips string literals,
  and every forbidden meta key *is* a string literal, so those assertions were unfalsifiable. They
  now use comment-stripped-but-literal-preserving source, and the gateway test names exact keys
  rather than loose words so it does not flag the description that explains the policy.
- PHPUnit 2741 / 13541 green, PHPCS clean. Inventory 653 → 669.

## Known limitation

**A real check-in could not be verified.** Creating a faithful attendee requires the purchase flow; a
hand-built attendee record was not enough for `checkin()` to act on. What *is* verified is that the
read-back guard refused to report success when the state did not change — which is the behaviour that
matters, and it caught the incomplete fixture rather than claiming a check-in that never happened.
The error path, the provider resolution and the attendee listing are all verified.

## Files

New: `includes/Abilities/EventTickets/` (base, registrar, 16 abilities),
`includes/Abilities/Utilities/EventTickets/{Event_Tickets_Guard,Ticket_Repository,Attendee_Repository}.php`,
`includes/Abilities/Integrations/Event_Tickets.php`, three test files.

Modified: bootstrap · `built_in()` · `AcrossAI_Category_Slug_Migration::OWNED` ·
`Test_Ability_Group_Map.php` (both skip arrays) · `Test_Toolset_Group_Coverage.php` ·
`phpunit.xml.dist` · inventory · `README.txt`.

21st MCP tool, gated on Event Tickets alone.
