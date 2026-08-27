# Contract: Ability Payload with Suggestions

## Surface

This contract describes the shape of an ability's registered `args` after `Ability_Definition::push_definition()` runs, and how the `AcrossAI_Ability_Library_Registry::get_definitions()` output changes based on the kill-switch state.

## Ability with no `suggested_abilities()` override

**Given** an ability class that does NOT override `suggested_abilities()` — or overrides it and returns `[]`.

**When** the ability's `push_definition()` runs.

**Then** the ability's `args.meta.acrossai` MUST NOT contain a `suggested_abilities` key. If `args.meta.acrossai` was empty and this feature would have been its only key, the whole `args.meta.acrossai` sub-array MAY still be absent (matches Feature 088's silent-default behavior).

```php
// Ability class
protected function suggested_abilities(): array {
    return array();  // or method omitted entirely
}

// Registered payload
$args['meta'] = array(
    'acrossai' => array(
        // sub_group, tab_group, suggested_plugins, etc. as previously —
        // NO 'suggested_abilities' key
    ),
    // 'mcp', 'annotations', 'show_in_rest' unchanged
);
```

**Guarantee (spec SC-004)**: byte-identical `args` to what the ability produced before Feature 095 shipped.

---

## Ability with a `suggested_abilities()` override

**Given** an ability class overriding `suggested_abilities()` with a non-empty return.

**When** the ability's `push_definition()` runs.

**Then** the ability's `args.meta.acrossai.suggested_abilities` MUST be present as an `array<int, array{slug: string, reason: string, saves?: string}>`, preserving order and every declared entry.

```php
// Ability class
protected function suggested_abilities(): array {
    return array(
        array(
            'slug'   => 'blocks/outline-post-blocks',
            'reason' => 'For narrow edits, outline first to locate the target block cheaply.',
            'saves'  => '~29K tokens vs full page rewrite on a 97 KB page',
        ),
        array(
            'slug'   => 'blocks/update-post-block',
            'reason' => 'Update just the located block without re-serializing the whole post.',
        ),
    );
}

// Registered payload
$args['meta']['acrossai']['suggested_abilities'] = array(
    array(
        'slug'   => 'blocks/outline-post-blocks',
        'reason' => 'For narrow edits, outline first to locate the target block cheaply.',
        'saves'  => '~29K tokens vs full page rewrite on a 97 KB page',
    ),
    array(
        'slug'   => 'blocks/update-post-block',
        'reason' => 'Update just the located block without re-serializing the whole post.',
    ),
);
```

**Guarantees**:
- Order preserved via `array_values()`.
- Every author-declared field key preserved verbatim; no framework-added keys.
- `args.description` UNCHANGED.

---

## Registry `get_definitions()` with kill-switch ON

**Given** the option `acrossai_disable_ability_suggestions` is set to `1`.

**When** any consumer calls `AcrossAI_Ability_Library_Registry::instance()->get_definitions()`.

**Then** every returned row MUST have `args.meta.acrossai.suggested_abilities` stripped (key removed, not set to `[]`).

```php
// Toggle ON
update_option( 'acrossai_disable_ability_suggestions', 1 );

// Any ability that would have had suggested_abilities in its args
$definitions = AcrossAI_Ability_Library_Registry::instance()->get_definitions();
foreach ( $definitions as $def ) {
    // Guaranteed absent
    assert( ! isset( $def['args']['meta']['acrossai']['suggested_abilities'] ) );

    // But every OTHER meta.acrossai.* key survives (spec FR-008, SC-005)
    // e.g. sub_group, tab_group, suggested_plugins from Feature 088, etc.
}
```

**Guarantee (spec FR-008)**: The strip touches ONLY `suggested_abilities`. `suggested_plugins` (Feature 088), `sub_group`, `tab_group`, and every other `meta.acrossai.*` sibling MUST be preserved.

---

## MCP surface

**Given** an ability with declared `suggested_abilities` and the kill-switch OFF.

**When** an MCP client calls `mcp-adapter-get-ability-info` for that ability's slug.

**Then** the response's `meta` object MUST contain the `acrossai.suggested_abilities` list verbatim.

**When** the same client calls `mcp-adapter-discover-abilities`.

**Then** the ability's entry in the returned list MUST NOT contain any `suggested_abilities` field — discovery surfaces only `name`, `label`, `description` per the MCP adapter's own contract (Feature 095 does not modify the adapter).

---

## Regression guarantees

1. An ability with no override produces a byte-identical `args` to pre-Feature-095 (spec SC-004).
2. With kill-switch ON, every ability's exposed payload is byte-identical to pre-Feature-095 (spec SC-005).
3. Toggle changes are visible on the very next request — no cache purge required (spec SC-002).
