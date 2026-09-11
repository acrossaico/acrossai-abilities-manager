<?php
/**
 * Tests: AcrossAI_Ability_Library_Registry — Feature 041 update of Feature 033/037 tests.
 *
 * Verifies the OPTIONAL sub_group / sub_group_label / tab_group pass-through
 * through validate_and_normalize(). Registry reads these fields from row-top
 * (already flattened by Ability_Definition::push_definition()) — those
 * row-top reads are UNCHANGED by Feature 041. What DID change:
 * ALLOWED_ARGS_FIELDS no longer contains 'sub_group', 'sub_group_label',
 * or 'tab_group' as top-level args keys; the canonical shape is
 * $args['meta']['acrossai']. See PATTERN-META-ACROSSAI-NAMESPACE.
 * validate_and_normalize() is private; tests call it via Reflection.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\Library;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry;
use PHPUnit\Framework\TestCase;

/**
 * The Library Registry sub_group validation test.
 */
class Test_Ability_Library_Registry extends TestCase {

	/**
	 * Invoke the private validate_and_normalize() via Reflection.
	 *
	 * @param  array<int, array<string, mixed>> $raw Raw filter payload.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize( array $raw ): array {
		$registry = AcrossAI_Ability_Library_Registry::instance();
		$ref      = new \ReflectionClass( $registry );
		$method   = $ref->getMethod( 'validate_and_normalize' );
		$method->setAccessible( true );
		return $method->invoke( $registry, $raw );
	}

	/**
	 * The minimum valid row shape (required fields only).
	 *
	 * @param  array<string, mixed> $overrides Field overrides.
	 * @return array<string, mixed>
	 */
	private function valid_row( array $overrides = array() ): array {
		return array_merge(
			array(
				'category'       => 'file-manager',
				'category_label' => 'File Manager',
				'slug'           => 'plugin/read-file',
				'slug_label'     => 'Read File',
				'name'           => 'plugin/read-file',
				'args'           => array(
					'label'    => 'Read File',
					'category' => 'file-manager',
				),
			),
			$overrides
		);
	}

	public function test_registry_omits_sub_group_when_not_declared(): void {
		$rows = $this->normalize( array( $this->valid_row() ) );
		$this->assertCount( 1, $rows );
		$this->assertArrayNotHasKey( 'sub_group', $rows[0] );
		$this->assertArrayNotHasKey( 'sub_group_label', $rows[0] );
	}

	public function test_registry_accepts_sub_group_and_derives_label(): void {
		$row = $this->valid_row(
			array(
				'sub_group' => 'core',
				'args'      => array(
					'label'    => 'Read File',
					'category' => 'file-manager',
					'meta'     => array(
						'acrossai' => array(
							'sub_group' => 'core',
						),
					),
				),
			)
		);

		$rows = $this->normalize( array( $row ) );

		$this->assertSame( 'core', $rows[0]['sub_group'] );
		$this->assertSame( 'Core', $rows[0]['sub_group_label'] );
	}

	/**
	 * A sub-group label reaches the JS as JSON and is rendered by LibraryCard
	 * as a React text node, never as HTML. Running it through wp_kses_post()
	 * would entity-encode the ampersand and the heading would read
	 * "Modules &amp;amp; Roles" on screen.
	 */
	public function test_registry_keeps_an_ampersand_in_a_sub_group_label_unencoded(): void {
		$row = $this->valid_row(
			array(
				'sub_group'       => 'admin',
				'sub_group_label' => 'Modules & Roles',
			)
		);

		$rows = $this->normalize( array( $row ) );

		$this->assertSame( 'Modules & Roles', $rows[0]['sub_group_label'] );
	}

	public function test_registry_strips_invalid_sub_group_characters(): void {
		$row = $this->valid_row(
			array(
				'sub_group' => '!!! BAD-INPUT ###',
			)
		);

		$rows = $this->normalize( array( $row ) );

		// sanitize_key() lowercases and strips non [a-z0-9_-]. ' ' and '!' / '#' go away.
		$this->assertSame( 'bad-input', $rows[0]['sub_group'] );
		$this->assertSame( 'Bad Input', $rows[0]['sub_group_label'] );
	}

	public function test_registry_omits_sub_group_when_empty_after_sanitize(): void {
		$row = $this->valid_row(
			array(
				'sub_group' => '!!! ###',
			)
		);

		$rows = $this->normalize( array( $row ) );

		// FR-018 / SC-033-03: empty after sanitize → omit both keys.
		$this->assertArrayNotHasKey( 'sub_group', $rows[0] );
		$this->assertArrayNotHasKey( 'sub_group_label', $rows[0] );
	}

	public function test_registry_strips_top_level_sub_group_from_args_post_041(): void {
		// Feature 041 hard cut: 'sub_group' and 'sub_group_label' are NO LONGER
		// in ALLOWED_ARGS_FIELDS. Top-level shape gets stripped by
		// array_intersect_key(). Canonical shape lives under meta.acrossai
		// and survives via the 'meta' allowlist entry.
		$row = $this->valid_row(
			array(
				'sub_group'       => 'core',
				'sub_group_label' => 'Core (custom)',
				'args'            => array(
					'label'           => 'Read File',
					'category'        => 'file-manager',
					// Legacy top-level shape — must be stripped by the allowlist.
					'sub_group'       => 'legacy',
					'sub_group_label' => 'Legacy Label',
					// Canonical Feature 041 shape — survives via the 'meta' allowlist entry.
					'meta'            => array(
						'acrossai' => array(
							'sub_group'       => 'core',
							'sub_group_label' => 'Core (custom)',
						),
					),
					'NOT_ALLOWED_KEY' => 'should be stripped',
				),
			)
		);

		$rows = $this->normalize( array( $row ) );

		// Row-top values (already flattened by push_definition() in production) survive.
		$this->assertSame( 'core', $rows[0]['sub_group'] );
		$this->assertSame( 'Core (custom)', $rows[0]['sub_group_label'] );

		// Legacy top-level shape in args is stripped by the allowlist.
		$this->assertArrayNotHasKey( 'sub_group', $rows[0]['args'] );
		$this->assertArrayNotHasKey( 'sub_group_label', $rows[0]['args'] );

		// Canonical meta.acrossai shape survives via the 'meta' allowlist entry.
		$this->assertArrayHasKey( 'meta', $rows[0]['args'] );
		$this->assertSame( 'core', $rows[0]['args']['meta']['acrossai']['sub_group'] );
		$this->assertSame( 'Core (custom)', $rows[0]['args']['meta']['acrossai']['sub_group_label'] );

		// Unrelated non-allowlisted key still stripped.
		$this->assertArrayNotHasKey( 'NOT_ALLOWED_KEY', $rows[0]['args'] );
	}

	public function test_registry_explicit_sub_group_label_overrides_auto_derived(): void {
		$row = $this->valid_row(
			array(
				'sub_group'       => 'core',
				'sub_group_label' => 'My Custom Heading',
			)
		);

		$rows = $this->normalize( array( $row ) );

		$this->assertSame( 'core', $rows[0]['sub_group'] );
		$this->assertSame( 'My Custom Heading', $rows[0]['sub_group_label'] );
	}

	// ---------------------------------------------------------------------
	// Feature 037 — tab_group pass-through.
	// ---------------------------------------------------------------------

	public function test_registry_omits_tab_group_when_not_declared(): void {
		$rows = $this->normalize( array( $this->valid_row() ) );
		$this->assertCount( 1, $rows );
		$this->assertArrayNotHasKey( 'tab_group', $rows[0] );
	}

	public function test_registry_accepts_tab_group(): void {
		$row = $this->valid_row(
			array(
				'tab_group' => 'sales',
				'args'      => array(
					'label'    => 'Read File',
					'category' => 'file-manager',
					'meta'     => array(
						'acrossai' => array(
							'tab_group' => 'sales',
						),
					),
				),
			)
		);

		$rows = $this->normalize( array( $row ) );

		$this->assertSame( 'sales', $rows[0]['tab_group'] );
	}

	public function test_registry_strips_invalid_tab_group_characters(): void {
		$row = $this->valid_row(
			array(
				'tab_group' => '!!! BAD-INPUT ###',
			)
		);

		$rows = $this->normalize( array( $row ) );

		// sanitize_key() lowercases and strips non [a-z0-9_-]; spaces and punctuation go.
		$this->assertSame( 'bad-input', $rows[0]['tab_group'] );
	}

	public function test_registry_omits_tab_group_when_empty_after_sanitize(): void {
		$row = $this->valid_row(
			array(
				'tab_group' => '!!! ###',
			)
		);

		$rows = $this->normalize( array( $row ) );

		// Empty after sanitize → key omitted entirely (treated as ungrouped).
		$this->assertArrayNotHasKey( 'tab_group', $rows[0] );
	}

	public function test_registry_strips_top_level_tab_group_from_args_post_041(): void {
		// Feature 041 hard cut: 'tab_group' is NO LONGER in ALLOWED_ARGS_FIELDS.
		// Top-level shape gets stripped by array_intersect_key(). Canonical
		// shape lives under meta.acrossai and survives via 'meta' allowlist entry.
		$row = $this->valid_row(
			array(
				'tab_group' => 'sales',
				'args'      => array(
					'label'           => 'Read File',
					'category'        => 'file-manager',
					// Legacy top-level shape — must be stripped by the allowlist.
					'tab_group'       => 'legacy-tab',
					// Canonical Feature 041 shape — survives via the 'meta' allowlist entry.
					'meta'            => array(
						'acrossai' => array(
							'tab_group' => 'sales',
						),
					),
					'NOT_ALLOWED_KEY' => 'should be stripped',
				),
			)
		);

		$rows = $this->normalize( array( $row ) );

		// Row-top value (already flattened by push_definition() in production) survives.
		$this->assertSame( 'sales', $rows[0]['tab_group'] );

		// Legacy top-level shape in args is stripped by the allowlist.
		$this->assertArrayNotHasKey( 'tab_group', $rows[0]['args'] );

		// Canonical meta.acrossai shape survives via the 'meta' allowlist entry.
		$this->assertArrayHasKey( 'meta', $rows[0]['args'] );
		$this->assertSame( 'sales', $rows[0]['args']['meta']['acrossai']['tab_group'] );

		$this->assertArrayNotHasKey( 'NOT_ALLOWED_KEY', $rows[0]['args'] );
	}
}
