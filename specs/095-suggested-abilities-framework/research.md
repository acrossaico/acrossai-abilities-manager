# Phase 0 Research: Ability Suggestions Framework

## Overview

The Technical Context in `plan.md` has zero `NEEDS CLARIFICATION` markers. This research doc consolidates the exploration that led to those choices — specifically the shape of Feature 088 (`suggested_plugins`) that Feature 095 mirrors — so the design decisions are traceable.

---

## Decision 1: Mirror Feature 088's authoring surface exactly

**Decision**: `Ability_Definition::suggested_abilities()` is a `protected` instance method returning `array<int, array{slug: string, reason: string, saves?: string}>`, default `array()`. Overridden per-ability. Registered automatically by `push_definition()` into `meta.acrossai.suggested_abilities`.

**Rationale**:
- `Ability_Definition::suggested_plugins()` at `includes/Modules/Library/Ability_Definition.php:96–98` already establishes this exact shape (protected method, empty default, auto-injected into `meta.acrossai.suggested_plugins` at `push_definition()` lines 118–127).
- One convention across the codebase is worth more than any bespoke improvement — an ability author who's seen either method knows both.
- Empty-return silently skips the meta injection, so abilities without an override produce byte-identical payloads (SC-004).

**Alternatives considered**:
- **Static array constant on each ability class.** Rejected — Feature 088 uses a method for the same reason: allows future dynamic behavior (e.g. conditional suggestions based on WordPress version) without changing the framework's API.
- **Filter-based registration (`add_filter('acrossai_suggested_abilities', ...)`).** Rejected — divorces the suggestion from the ability's own class, breaking the "one file for the reviewer" ergonomics story (User Story 3).

---

## Decision 2: Meta-only surfacing; no `description` mutation

**Decision**: The framework writes to `meta.acrossai.suggested_abilities` only. `args['description']` is never modified at registration time.

**Rationale**:
- Feature 088 also writes meta-only. Consulting the exploration report: `push_definition()` at lines 109–165 does not touch `description`.
- The MCP adapter's `mcp-adapter-get-ability-info` returns `meta` verbatim to clients (per `plugins/mcp-adapter/includes/Abilities/GetAbilityInfoAbility.php:111–130`). AI callers will see the meta field automatically on the exact surface where it's useful — details lookup.
- `mcp-adapter-discover-abilities` returns only `name`, `label`, `description` (per `plugins/mcp-adapter/includes/Abilities/DiscoverAbilitiesAbility.php:102–106`) — deliberately keeping suggestions OUT of the discovery listing satisfies spec FR-003 (no discovery-time payload bloat).
- Kill-switch becomes a pure key removal at Registry time. If we had mutated `description`, the kill-switch would need to remember the pre-suggestion string per ability — messy state, no upside.

**Alternatives considered**:
- **Append suggestions to `args['description']`.** Rejected — bloats discovery-time payload for all ~325 abilities; couples the kill-switch to string-diffing.
- **Emit a top-level `suggested_abilities` sibling of `args['meta']`.** Rejected — no precedent in the plugin's REST or MCP shape; would require MCP adapter changes we don't own.

---

## Decision 3: Registry-level strip, not push_definition-level

**Decision**: The kill-switch (`acrossai_disable_ability_suggestions` option, default `0`) is honored inside `AcrossAI_Ability_Library_Registry::get_definitions()` via a new `apply_suggested_abilities_decoration()` step. When the option is truthy, every returned row has `args.meta.acrossai.suggested_abilities` stripped.

**Rationale**:
- Feature 088 does exactly this at `AcrossAI_Ability_Library_Registry.php:168` (`apply_suggested_plugins_decoration()`). Keeps the ability's own spec authoritative — the Registry filters the exposed payload, not the ability's internal registration state.
- Toggling the setting takes effect on the next request without re-registration or cache-bust (spec SC-002). The `get_definitions()` path is called on every REST/MCP consumer request.
- Never touches `push_definition()`, so the ability's canonical `args` always contains the declared suggestions — useful for future debugging, telemetry, or admin UI that wants to say "you have N suggestions declared but the kill-switch is on."

**Alternatives considered**:
- **Strip inside `push_definition()` when the option is truthy at registration time.** Rejected — abilities register once at `plugins_loaded` hook; changing the option would require an explicit re-registration flow or cache-bust. Fails SC-002.
- **Wrap every `to_row()` / details lookup separately.** Rejected — the Registry already centralizes the exposure surface; splitting the strip across multiple call sites duplicates the option read and creates drift risk.

---

## Decision 4: Negative-framed toggle name matches Feature 088

**Decision**: Option key is `acrossai_disable_ability_suggestions`. Default value `0` (feature enabled). Admin UI checkbox reads "Disable ability suggestions" — ticking it turns the feature OFF.

**Rationale**:
- Feature 088's toggle is `acrossai_disable_plugin_suggestions` with identical semantics. Two related toggles on the same settings tab reading with the same polarity is less confusing than mixed polarities.
- Users have muscle memory for the existing "Disable Plugin Suggestions" checkbox — the new one behaves identically.
- The negative framing is standard WordPress convention for opt-out toggles (`disable_wpautop`, `disable_login_screen`, etc.).

**Alternatives considered**:
- **Positive framing (`acrossai_enable_ability_suggestions`, default `1`).** Rejected — inconsistent with the sibling Feature 088 toggle on the same page; violates the constitution's DRY / reusability principle for UI conventions.

---

## Decision 5: Initial batch of 4 abilities ships with the framework

**Decision**: The four abilities that gain a `suggested_abilities()` override in this PR:

- `content/update-page` — suggests `blocks/outline-post-blocks` + `blocks/update-post-block` (narrow edit path saves ~29K tokens on a ~97 KB page)
- `content/update-post` — same suggestions
- `content/update-cpt-item` — same suggestions
- `blocks/get-post-blocks` — suggests `blocks/outline-post-blocks` (cheap block-level index, avoids returning every block's `innerHTML`)

**Rationale**:
- Framework without any adoption is dead code. Shipping four concrete overrides validates the full pipeline (declaration → meta → MCP surface → kill-switch) end-to-end from day one.
- These four are the abilities where the token-savings math is most dramatic (the very reason the framework exists — see PR #152 and PR #154 which built the alternatives). Every other candidate (menu writes, taxonomy writes, comment writes) already returns tiny payloads.
- Additional abilities can add overrides in follow-up PRs — the framework never breaks existing abilities that don't override.

**Alternatives considered**:
- **Ship framework alone; add overrides in a follow-up PR.** Rejected — you can't verify the surfacing end-to-end without at least one override. And once you're testing one, adding the other three is trivial (~10 lines each).
- **Add overrides to more abilities (media/get-media-item, options/get-option, etc.).** Rejected — token savings for those abilities are marginal because they already return small responses. Scope creep.

---

## Decision 6: `saves` field is optional free-form text, not a structured field

**Decision**: The entry shape is `{slug: string, reason: string, saves?: string}`. `saves` is optional and is a free-form human-readable hint like "~29K tokens vs full page rewrite". No units are enforced; no schema is enforced beyond "string".

**Rationale**:
- Parallel to Feature 088's `covers` field (optional, free-form) documented in `Ability_Definition.php:83`.
- Structured cost data (numeric tokens, percentage savings) would drift out of sync with model pricing as models evolve. Free-form lets authors say what's true at declaration time without over-committing.
- AI callers can read the free-form value as context; there's no downstream code that needs to parse it into a number.

**Alternatives considered**:
- **Structured `saves_tokens: int`.** Rejected — model-specific, drifts.
- **Enum `saves: 'small' | 'medium' | 'large'`.** Rejected — over-engineered for a hint; free-form gives authors more control.

---

## Decision 7: No slug shape validation, no target-existence validation at declaration time

**Decision**: The framework accepts any string as a suggestion `slug`. It does not verify the target is a registered ability, nor does it validate the `namespace/name` shape.

**Rationale**:
- Feature 088 accepts any string as its `slug` field (see `Ability_Definition.php:76`).
- Runtime validation would fire on every `push_definition()` call — hundreds of registrations at `plugins_loaded`. Adds cost for zero user value (the AI simply ignores unknown slugs).
- Ability plugins ship suggestions pointing at OTHER ability plugins that may not be installed on this site. Validating existence would either reject those suggestions or force a `hard_dependencies` scheme neither Feature 088 nor 095 has appetite for.
- Ability authors carry the shape correctness — same as any other declarative content in `ability()` (labels, categories, descriptions).

**Alternatives considered**:
- **Reject malformed slugs at registration.** Rejected — no user-visible benefit, forces synchronous validation at plugin_load hook.
- **Warn (not reject) via `_doing_it_wrong`.** Rejected — noise for authors experimenting with cross-plugin suggestions.
