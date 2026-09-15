# Feature 109 — The Events Calendar ability suite

Inputs for `/speckit-specify`. Records the gap analysis, the measurements that shaped the design,
and the constraints that are traps rather than preferences.

## One-sentence goal

Add 18 abilities for The Events Calendar under its own toolset, writing exclusively through the
calendar's own data layer, because every other route silently desynchronises an event.

## Why now

The Events Calendar 6.17.4.1 is active. It **registers no abilities of its own** — verified by
grepping `wp_register_ability`, `wp_abilities_api_init` and `abilities` across the plugin including
its bundled common library: zero matches. Build from scratch.

Nothing in our 653 touches it: `grep -rn "tribe\|tec_\|_EventStartDate" includes/` returned nothing.

## The measurement that justifies the whole suite

We can already create and update events today. `content/create-cpt-item` and
`content/update-cpt-item` accept a `meta` object and pass it straight into `meta_input` with no
protected-key filter, so `_EventStartDate` writes fine. **It also breaks the event, silently.**

Measured on this install, after `update_post_meta( $id, '_EventStartDate', '14:00' )` on an event
created at 09:00 America/New_York:

| Store | Value | |
|---|---|---|
| `_EventStartDate` | 14:00 | moved |
| `_EventStartDateUTC` | 14:00 UTC | **stale** — that is 09:00 New York, the old time |
| `wp_tec_events.start_date` | 09:00 | **stale** |
| `wp_tec_occurrences.start_date` | 09:00 | **stale** |

`_EventDuration` also stayed at 28800s while the end time was unchanged. Four stores, four answers,
no error anywhere. Calendar views query the custom tables and show 09:00; the single-event template
reads meta and shows 14:00.

Since 6.0 the authoritative timing lives in `wp_tec_events` and `wp_tec_occurrences` as well as in
meta, and the ORM is the only thing that writes all of them plus the derived values. That is the
feature.

## Scope — 18 abilities

Category `acrossai-events-calendar`, `tab_group = 'events-calendar'`, slug namespace `events/`.

| Sub-group | # | Abilities |
|---|---:|---|
| events | 5 | list (date-range, venue, organizer, category, featured), get, create, update, trash |
| venues | 5 | list, get, create, update, trash |
| organizers | 5 | list, get, create, update, trash |
| categories | 2 | list with event counts, set on an event |
| calendar | 1 | settings read, credentials redacted |

**`events/list-events` is the ability that earns the suite its place.** The repository understands
`starts_after`, `starts_before`, `ends_after`, `venue`, `organizer`, `event_category` and `featured`
against the calendar's own tables. `content/list-posts` has no date query at all, so "what is on next
week" — the most natural calendar question there is — cannot be expressed with what we already ship.

**Event category CRUD is deliberately absent.** Categories are ordinary WordPress terms and are not
duplicated into the custom tables, so `taxonomies/*` already creates, renames and deletes them
correctly. `events/list-event-categories` exists only because those cannot report how many events
sit in each; `events/set-event-categories` exists because assigning them to an event alongside
validation is worth one call. Both suggest the taxonomy abilities for the rest.

## Constraints — measured, not assumed

### The ORM's return value is not a success signal

`save()` is documented to return `[ id => true|WP_Error ]`. Measured here: it returns a
`Tribe__Promise` for a **single-event** update, for a write that had already applied synchronously.
An earlier draft of this suite treated that as failure and reported an error on a write that worked.

Only an explicit `WP_Error` is an error. Correctness comes from reading the value back. This is
deliberately not solved the way it could be — by filtering `tribe_repository_update_background_activated`
to force the synchronous branch — because that mutates a global hook for the duration of someone
else's save cycle.

### The ORM discards the whole date block, silently, when end precedes start

Verified: the update reported success and the event kept its previous time.
`Event_Repository::assert_dates_applied()` re-reads both dates after every write that touches them
and returns `dates_rejected` naming what was asked for and what is stored.

### `save()` writes to every post matching the query

It must be scoped by ID first (`by_args( [ 'id' => N, 'status' => 'any' ] )`) or an update becomes a
mass update of the calendar.

### Bad venue and organizer IDs are dropped without a word

The repository discards a relation it cannot resolve and still reports success, so IDs are validated
**before** the write and refused by name.

### Derived meta must never be written

`_EventStartDateUTC`, `_EventEndDateUTC`, `_EventDuration`, `_EventTimezoneAbbr` and `_EventOrigin`
are all computed by the ORM from the local time and the timezone. They are declared as a denylist
and an architecture test forbids any meta write anywhere in the suite.

### Recurring events are refused

Recurrence is an Events Calendar Pro feature. Free calendar code displays a series and never edits
its occurrences — its own migration strategy throws on one. A write here would change the parent
event and leave the occurrences behind, so `update-event` refuses when `_EventRecurrence['rules']`
is non-empty. Pro is not installed, so this could not be verified beyond the refusal itself.

### Trash, never delete

Core only auto-trashes literal `post` and `page`; a custom post type handed to `wp_delete_post()` is
removed permanently. All three trash abilities use `wp_trash_post()` and refuse outright when
`EMPTY_TRASH_DAYS` is 0 rather than silently escalating.

Trashing a venue or organizer reports how many events still reference it, because the calendar does
not clear those links.

### Credentials in the settings blob

`tribe_events_calendar_options` is shared by the calendar, the ticketing plugin and every add-on, and
holds `google_maps_js_api_key`, `meetup_api_key`, `meetup_security_key`, `eb_security_key`, `fb_token`
and its two companions beside ordinary preferences. Redacted keys are returned with a `null` value
and a `redacted` flag so a caller can see the setting exists. Licence keys are separate
`pue_install_key_*` option rows and are never read here.

Worth knowing: the Google Maps key on this install is byte-identical to the calendar's own shared
default constant, so it is not a user secret — but it is still an API key, and there is no way to
distinguish "configured" from "default" without comparing against that constant.

## Files

New: `includes/Abilities/EventsCalendar/` (base, registrar, 18 abilities),
`includes/Abilities/Utilities/EventsCalendar/{Events_Calendar_Guard,Event_Repository}.php`,
`includes/Abilities/Integrations/Events_Calendar.php`, three test files.

Modified: bootstrap · `built_in()` · `AcrossAI_Category_Slug_Migration::OWNED` ·
`Test_Ability_Group_Map.php` (both skip arrays) · `Test_Toolset_Group_Coverage.php` ·
`phpunit.xml.dist` · inventory (635 → 653) · `README.txt`.

`ability_prefixes()` is empty — the calendar registers nothing, and claiming `events` would capture
any future ability in that namespace. This is a 20th MCP tool, and the cost is conditional: the
suite is gated on the plugin AND its ORM, so the tab and the tool exist only where they mean
something.

## Verification

All 18 executed live against a real calendar; fixtures created and removed.

- Venue, organizer and event created through the ORM with links resolved and UTC derived
  (09:00 America/New_York → 14:00 UTC).
- **Date-range querying proven**: 1 event returned for a window containing it, 0 for a window
  excluding it. Filtering by venue and by organizer both work.
- Nine negative paths, each with a named code: `unknown_event`, `unknown_venue`, `unknown_organizer`
  (for an ID that is the wrong post type), `dates_rejected`, `unknown_term`, `invalid_input`,
  `confirmation_required` on all three trash abilities.
- Settings: 22 keys, 1 redacted, the maps key returned as `null` with `redacted: true`.
- Trashing a venue reported "1 event(s) still reference it and were not changed".
- Toolset discover returns 18; info and execute both work; suggestions surface on info.
- Eight architecture guards mutation-verified.
- PHPUnit 2708 / 12956 green, PHPCS clean.

## Follow-ups, not done here

- **Recurring events are refused, not handled.** With Events Calendar Pro installed the right
  behaviour would be to edit a single occurrence or the whole series explicitly; that needs Pro to
  verify against.
- **`content/create-cpt-item` and `content/update-cpt-item` still accept arbitrary meta**, so the
  corrupting path remains open to anyone who takes it deliberately. They are also inconsistent with
  `content/update-post`, which strips protected keys and reports `dropped_meta_keys`. Worth a
  separate decision rather than a change bundled here.
