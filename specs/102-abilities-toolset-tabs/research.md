# Phase 0 Research: One Access Model for Abilities

**Feature**: 102-abilities-toolset-tabs · **Date**: 2026-09-12

Seven unknowns were carried out of Technical Context. All are resolved below. R1 changes the shape of
the feature and is the reason the migration is not a single activation-time routine.

---

## R1 — Migration scope on multisite (BLOCKING, resolved)

**Question**: The gate's configuration and the overrides that replace it live at different scopes. Does a
single migration run translate a whole network?

**Findings (verified in code)**:

| Store | Scope | Evidence |
|---|---|---|
| `acrossai_library_config` | **Network-wide** | `AcrossAI_Ability_Library_Config.php:33,81` use `get_site_option()` / `update_site_option()` |
| Override rows (`…acrossai_abilities`) | **Per-site** | `AcrossAI_Abilities_Table.php:57,63` — `protected $global = false;`, "Use per-site table prefix" |
| `AcrossAI_Activator::activate()` | **Runs once, current site only** | no `get_sites()`, `switch_to_blog()`, `is_multisite()` or `network_wide` handling anywhere in the activator or the bootstrap |

So one network-wide setting must produce override rows in *N* per-site tables, and the only existing
migration entry point visits exactly one of them. A naive activation-time routine would translate the site
that happened to run activation and silently leave every other site in the network with
previously-blocked abilities reachable — a direct breach of FR-010, User Story 2, and Constitution §II's
multisite-compatibility mandate.

`AcrossAI_Category_Slug_Migration` already exhibits the same mismatch in a benign form: it guards with
per-site `get_option( DONE_OPTION )` (line 111) while rewriting the network-wide config (lines 132, 155).
That is safe there only because it is an idempotent key rename. It is not a safe model to copy here.

**Decision**: Run the translation **per site**, not once per network.

- Guard with a **per-site** done flag, mirroring `AcrossAI_Category_Slug_Migration::DONE_OPTION` — but
  **claimed atomically before the work** via `add_option( $flag, '1', '', false )`, not written after it.
  A read-then-write guard is safe under `admin_init` and unsafe under an all-paths hook; see SEC-008.
- Trigger on an **early all-request-path hook** (`plugins_loaded` / `init`), in addition to the activator,
  so a site translates on its first request of any kind. **Not `admin_init`** — see R2.
- Read the network config, write to the current site's override table.
- **Do not delete `acrossai_library_config` on multisite.** Deletion is only safe once every site has
  translated, and nothing tracks that. On single-site, delete after a successful run; on multisite, leave
  the option in place, unread. It is inert once `is_permitted()` is gone.

**Rationale**: The per-site flag is the only bookkeeping that is correct for a per-site target table, and
it reuses an established precedent in this codebase. Retaining a dead option on multisite is a far
cheaper price than an un-translated site.

**Alternatives rejected**:
- *Loop `get_sites()` at activation* — unbounded work on large networks, fails on a 10k-site network, and
  still misses sites created after activation.
- *Network-wide done flag* — would mark the network complete after one site translated; this is precisely
  the failure mode.
- *Delete the option anyway* — makes the un-translated case unrecoverable.

---

## R2 — When the translation runs relative to gate removal

**Question**: The gate is removed in the same release. What ordering keeps FR-010 true?

**Decision (revised after plan-stage security review, SEC-001)**: the translation runs from an **early
all-request-path hook** (`plugins_loaded` / `init` at low priority), guarded by the per-site done flag, and
also from the activator for the activating site. Gate removal (deleting `is_permitted()`) ships in the same
release, after the translation is proven.

**Why not `admin_init`**, which was the original decision: `admin_init` fires only on wp-admin page loads,
while the exposure this translation closes is through REST and MCP — neither of which touches wp-admin. A
network site whose administrator never opens its admin would never translate, and would serve every
previously-blocked ability indefinitely to any REST or MCP caller. Spec Q1 (no report) and Q2 (no retry)
mean nothing would detect or correct it. The done flag makes the all-paths cost one cache-backed
`get_option()` per request.

**Accepted consequence** (spec Clarifications, Q2): one attempt, no retry, no rollback. Between the code
swap and a site's first qualifying request, that site's previously-blocked abilities are reachable. This is
inherent to the answer given and is recorded rather than mitigated. Bound: on single-site, one activation;
on multisite, until each site's first request.

**Rationale**: the activator alone cannot reach sites it never activates on, and an admin-only hook cannot
close a hole whose consumers are not administrators.

---

## R3 — Making the Status filter apply (FR-015a)

**Question**: Why does Status currently do nothing, and where does the fix belong?

**Finding**: `AcrossAI_Abilities_Read_Controller.php:203-210` builds `$registry_params` from only
`search, orderby, order, source, page, per_page`. `status` (and `category`) are forwarded solely on the
`source=db` branch, and `AcrossAI_Ability_Registry_Query::query()` has no status filter.

**Decision**: Add `status` to the registry path. Registry-sourced abilities are always `publish`
(`AcrossAI_Abilities_Formatter::format_merged_ability()` forces it), so `status=publish` is a no-op over
them and `status=draft` correctly narrows to DB-created drafts. Implement the comparison **in
`AcrossAI_Ability_Registry_Query`**, not the controller, per `AC-QUERY-LAYER-FILTERING`.

**Alternatives rejected**: hiding the control unless `source=db` — leaves a filter that appears and
disappears, and does not satisfy SC-010.

---

## R4 — Source of the Toolset column value

**Decision**: `meta.acrossai.tab_group`, newly carried through
`AcrossAI_Ability_Merger::normalize_registry()` and emitted by both formatter methods. The column renders
`toolset/<group>`; DB-created abilities carry no `tab_group` and render empty.

**Constraint carried from memory**: `DEC-ABILITY-GROUP-IDENTIFIER-LOAD-BEARING` — `tab_group` is not
display-only; the MCP tool catalogue is derived from it. This feature therefore **reads** it and never
writes or re-tags it. `DEC-TOOLSET-SLUG-NAMESPACE` reserves `toolset/` for abilities acting on the
catalogue itself, which is exactly what the dispatchers are, so the rendered value is consistent with the
namespace rule.

**Filtering**: resolve a tab to its members through `AcrossAI_Ability_Group::member_names()` and pass the
result as a `slugs` parameter. Resolving inline would duplicate the group rule and lose the protected-slug
exclusion that keeps the 13 dispatchers out of their own tabs (`DEC-PROTECTED-SLUGS-PATTERN`).

---

## R5 — Effect of removing the gate on registration volume

**Finding**: `is_permitted()` returns **true** when a category is absent from the config
(`AcrossAI_Ability_Library_Processor.php:95-99`), and `push_definition()` on the integration base gates
only on `is_plugin_active()` (`AcrossAI_Integration_Ability_Base.php:316`). So on a default site every
definition — including third-party synthetic rows — already reaches `wp_register_ability()` today.

**Decision**: No mitigation required. Removing the gate widens an existing path rather than creating one.
Synthetic integration rows continue to be rejected by WP core because their category is deliberately never
registered (documented design contract, `…Integration_Ability_Base.php:284-300`).

**Open verification item**: `BUG-WP-CORE-ABILITY-CATEGORY-PRE-REGISTRATION` notes a `_doing_it_wrong()`
accompanies that rejection. Measure notice volume under `WP_DEBUG` after gate removal; if it is noisy,
skip `card_variant === 'integration'` rows in the Processor loop. Not a spec change.

---

## R6 — Integration opt-in storage after the move

**Decision**: New `acrossai_integrations` option (`slug => bool`), read by a small
`AcrossAI_Integration_Settings`; `AcrossAI_Integration_Ability_Base::maybe_enable()` line 249 changes to
call it. Absent entry remains `false` (FR-021).

**Copy must be OR-monotonic and independently idempotent**, per
`PATTERN-OPTION-KEY-MIGRATION-OR-MONOTONIC` ("never demote a truthy opt-in"). This is a deliberate
*divergence* from R2's accepted one-shot risk: the override translation may fail without recovery, but the
opt-in copy must never turn an enabled integration off, because FR-022 requires the opposite and the
memory pattern forbids it explicitly.

**Sparse-store hazard**: the same option holds two opposite defaults — an absent *category* means
*permitted*, an absent *integration* means *disabled*. Reading it with one uniform default mis-translates
one of the two (`BUG-SPARSE-STORAGE-UNIFORM-DEFAULT-ASSUMPTION`). The two key kinds must be dispatched on
`card_variant` before any default is applied.

**Capability**: keep a single filtered `current_user_can( apply_filters( 'acrossai_integration_toggle_capability', … ) )`
on the save path. `PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY` holds only for the single-check shape;
splitting it into two checks would let the filter lower the effective capability.

---

## R7 — Decommission order

**Decision**: Follow `PATTERN-ASSET-DECOMMISSION-ORDER` — remove the PHP include first, then the webpack
entry and source files, then clean `build/`. Run an exhaustive `grep -rEn` across
`includes/ src/ tests/ admin/` for every removed symbol **before** the removal task list is approved
(`BUG-INVENTORY-GREP-MISS`: Feature 034's inventory missed four PHP files).

Retired decisions are marked Superseded and kept intact; patterns that merely lose their consumer are
annotated with a forward-pointer rather than deleted (`PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION`).
