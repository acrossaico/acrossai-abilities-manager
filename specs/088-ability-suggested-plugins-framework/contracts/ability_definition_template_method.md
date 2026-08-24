# Contract: `Ability_Definition::suggested_plugins()` template method

**Feature**: 088 | **Class**: `AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition`

## Signature

```php
/**
 * Return external WordPress plugins the site admin may consider as
 * alternatives or specialists for this ability's scope.
 *
 * Optional; default is an empty list. Override in subclasses that want to
 * surface plugin suggestions on their Library card and in agent discovery.
 *
 * @return array<int,array<string,scalar>> Zero-or-more entries following the
 *     Suggested Plugin Entry shape (see data-model.md).
 */
protected function suggested_plugins(): array {
    return array();
}
```

## Behavioural contract

1. **Default is safe**: base class returns `[]`. Subclasses that do not override are byte-identical to pre-feature behaviour.
2. **Auto-injection**: when a subclass overrides and returns a non-empty array, the existing `push_definition()` callback MUST auto-merge the result into `$args['meta']['acrossai']['suggested_plugins']` without any subclass code touching `meta`.
3. **No side effects**: the method MUST NOT modify any external state (no `update_option()`, no hooks fired, no I/O). It returns a value or nothing.
4. **Idempotent**: calling `suggested_plugins()` twice on the same instance MUST return identical results.
5. **Runtime-safe**: subclasses SHOULD prefer static or hard-coded return values; any dynamic input (e.g. from filters) MUST tolerate missing dependencies without fataling.

## Return value contract

Each entry in the returned array MUST conform to the Suggested Plugin Entry shape (see `data-model.md`):

- Required keys: `slug` (string, non-empty), `name` (string, non-empty), `reason` (string, non-empty)
- Optional keys: `url`, `covers`, `plugin_provides_abilities`, `acrossai_provides_integration`
- All required keys missing OR empty → entry is silently dropped by the Registry (never fatal)

## Invocation lifecycle

1. Subclass constructor runs → hooks into `acrossai_abilities_api_init` filter via `push_definition()` callback (existing behaviour, unchanged)
2. At `init P99`, `AcrossAI_Ability_Library_Registry::collect()` fires the filter
3. `push_definition()` invokes `$this->ability()` (existing) AND `$this->suggested_plugins()` (new)
4. If `suggested_plugins()` returned non-empty, merge into `$args['meta']['acrossai']['suggested_plugins']`
5. Registry stores the merged spec; downstream `format_merged_ability()` reads the field on payload emission

## Backwards compatibility guarantees

- Existing subclasses (500+) that do NOT override `suggested_plugins()` MUST produce identical payloads before and after this feature ships (SC-001).
- The method visibility (`protected`) mirrors `ability()` — subclasses use it; external code does not.
- No trait, no interface, no new abstract method — the addition cannot break autoloading, static analysis, or subclass instantiation.

## Test coverage expectations

- Golden: an in-file test fixture class overrides `suggested_plugins()` with a 1-entry return; the resulting `ability()` payload contains `meta.acrossai.suggested_plugins[0]` with the expected fields.
- Regression: a fixture class that does NOT override; the payload's `meta.acrossai` MUST NOT contain a `suggested_plugins` key at all.
- Edge: fixture returns `[]` explicitly → payload MUST NOT contain the key (indistinguishable from no override).
- Edge: fixture returns a malformed entry (missing `slug`) → Registry drops the entry silently; ability payload still emits (never fatal).
