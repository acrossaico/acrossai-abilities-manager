<?php
/**
 * Tests: AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition.
 *
 * Feature 041 — verify push_definition() reads sub_group / tab_group /
 * sub_group_label from $args['meta']['acrossai'] and NOT from the top level
 * of $args. Feature 033/037 top-level shape is hard-cut. See
 * PATTERN-META-ACROSSAI-NAMESPACE + DEC-META-ACROSSAI-NAMESPACE.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\Library;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The Ability_Definition push_definition() OPTIONAL sub_group pass-through test.
 */
class Test_Ability_Definition extends TestCase {

	/**
	 * The minimal subclass without a sub_group declaration.
	 *
	 * @return Ability_Definition
	 */
	private function make_subclass_without_sub_group(): Ability_Definition {
		return new class() extends Ability_Definition {
			protected function ability(): array {
				return array(
					'name' => 'test/no-sub',
					'args' => array(
						'label'    => 'No Sub',
						'category' => 'test-category',
					),
				);
			}
		};
	}

	/**
	 * The minimal subclass declaring meta['acrossai']['sub_group'] = 'core'.
	 *
	 * @return Ability_Definition
	 */
	private function make_subclass_with_core_sub_group(): Ability_Definition {
		return new class() extends Ability_Definition {
			protected function ability(): array {
				return array(
					'name' => 'test/with-sub',
					'args' => array(
						'label'    => 'With Sub',
						'category' => 'test-category',
						'meta'     => array(
							'acrossai' => array(
								'sub_group' => 'core',
							),
						),
					),
				);
			}
		};
	}

	/**
	 * The minimal subclass declaring an explicit meta['acrossai']['sub_group_label'] override.
	 *
	 * @return Ability_Definition
	 */
	private function make_subclass_with_explicit_label(): Ability_Definition {
		return new class() extends Ability_Definition {
			protected function ability(): array {
				return array(
					'name' => 'test/explicit-label',
					'args' => array(
						'label'    => 'Explicit Label',
						'category' => 'test-category',
						'meta'     => array(
							'acrossai' => array(
								'sub_group'       => 'debug-log',
								'sub_group_label' => 'Debug Log (custom)',
							),
						),
					),
				);
			}
		};
	}

	public function test_push_definition_omits_sub_group_when_absent(): void {
		$subject = $this->make_subclass_without_sub_group();
		$rows    = $subject->push_definition( array() );

		$this->assertCount( 1, $rows );
		$row = $rows[0];

		$this->assertArrayNotHasKey( 'sub_group', $row );
		$this->assertArrayNotHasKey( 'sub_group_label', $row );
		$this->assertSame( 'test/no-sub', $row['slug'] );
		$this->assertSame( 'No Sub', $row['slug_label'] );
		$this->assertSame( 'test-category', $row['category'] );
	}

	public function test_push_definition_includes_sub_group_when_present(): void {
		$subject = $this->make_subclass_with_core_sub_group();
		$rows    = $subject->push_definition( array() );

		$this->assertCount( 1, $rows );
		$row = $rows[0];

		$this->assertArrayHasKey( 'sub_group', $row );
		$this->assertArrayHasKey( 'sub_group_label', $row );
		$this->assertSame( 'core', $row['sub_group'] );
		$this->assertSame( 'Core', $row['sub_group_label'] );
	}

	public function test_push_definition_auto_derives_label_from_hyphenated_key(): void {
		$subject = new class() extends Ability_Definition {
			protected function ability(): array {
				return array(
					'name' => 'test/hyphen-sub',
					'args' => array(
						'label'    => 'Hyphen Sub',
						'category' => 'test-category',
						'meta'     => array(
							'acrossai' => array(
								'sub_group' => 'debug-log',
							),
						),
					),
				);
			}
		};

		$rows = $subject->push_definition( array() );
		$row  = $rows[0];

		$this->assertSame( 'debug-log', $row['sub_group'] );
		$this->assertSame( 'Debug Log', $row['sub_group_label'] );
	}

	public function test_push_definition_prefers_explicit_sub_group_label_when_provided(): void {
		$subject = $this->make_subclass_with_explicit_label();
		$rows    = $subject->push_definition( array() );
		$row     = $rows[0];

		$this->assertSame( 'debug-log', $row['sub_group'] );
		$this->assertSame( 'Debug Log (custom)', $row['sub_group_label'] );
	}

	public function test_push_definition_does_not_mutate_existing_rows(): void {
		$subject  = $this->make_subclass_with_core_sub_group();
		$existing = array(
			array(
				'category'   => 'other-cat',
				'slug'       => 'other/ability',
				'name'       => 'other/ability',
				'slug_label' => 'Other',
				'args'       => array(),
				// Intentionally NO category_label — proves push_definition() appends rather than rebuilds.
			),
		);

		$rows = $subject->push_definition( $existing );

		$this->assertCount( 2, $rows );
		$this->assertSame( 'other/ability', $rows[0]['slug'] );
		$this->assertSame( 'test/with-sub', $rows[1]['slug'] );
	}

	// ---------------------------------------------------------------------
	// Feature 037 — tab_group pass-through.
	// ---------------------------------------------------------------------

	public function test_push_definition_omits_tab_group_when_absent(): void {
		$subject = $this->make_subclass_without_sub_group();
		$rows    = $subject->push_definition( array() );

		$this->assertCount( 1, $rows );
		$this->assertArrayNotHasKey( 'tab_group', $rows[0] );
	}

	public function test_push_definition_includes_tab_group_when_present(): void {
		$subject = new class() extends Ability_Definition {
			protected function ability(): array {
				return array(
					'name' => 'test/with-tab',
					'args' => array(
						'label'    => 'With Tab',
						'category' => 'test-category',
						'meta'     => array(
							'acrossai' => array(
								'tab_group' => 'sales',
							),
						),
					),
				);
			}
		};

		$rows = $subject->push_definition( array() );
		$row  = $rows[0];

		$this->assertArrayHasKey( 'tab_group', $row );
		$this->assertSame( 'sales', $row['tab_group'] );
		// tab_group has NO paired tab_group_label field (FR-007).
		$this->assertArrayNotHasKey( 'tab_group_label', $row );
	}

	public function test_push_definition_carries_tab_group_alongside_sub_group(): void {
		$subject = new class() extends Ability_Definition {
			protected function ability(): array {
				return array(
					'name' => 'test/with-both',
					'args' => array(
						'label'    => 'With Both',
						'category' => 'test-category',
						'meta'     => array(
							'acrossai' => array(
								'sub_group' => 'core',
								'tab_group' => 'sales',
							),
						),
					),
				);
			}
		};

		$rows = $subject->push_definition( array() );
		$row  = $rows[0];

		$this->assertSame( 'core', $row['sub_group'] );
		$this->assertSame( 'Core', $row['sub_group_label'] );
		$this->assertSame( 'sales', $row['tab_group'] );
	}

	// ---------------------------------------------------------------------
	// Feature 041 — hard-cut regression coverage: top-level $args['sub_group']
	// / $args['tab_group'] / $args['sub_group_label'] MUST NOT be read.
	// ---------------------------------------------------------------------

	public function test_push_definition_ignores_top_level_sub_group_pre_041_shape(): void {
		// Legacy Feature 033 shape: sub_group at top of $args (NOT under meta.acrossai).
		// Post-Feature 041, this must NOT produce a row-level sub_group entry.
		$subject = new class() extends Ability_Definition {
			protected function ability(): array {
				return array(
					'name' => 'test/legacy-top-level',
					'args' => array(
						'label'           => 'Legacy Top-Level',
						'category'        => 'test-category',
						'sub_group'       => 'core',
						'sub_group_label' => 'Should Not Appear',
						'tab_group'       => 'sales',
					),
				);
			}
		};

		$rows = $subject->push_definition( array() );
		$row  = $rows[0];

		// Hard cut: none of the legacy top-level fields flow to row-top.
		$this->assertArrayNotHasKey( 'sub_group', $row );
		$this->assertArrayNotHasKey( 'sub_group_label', $row );
		$this->assertArrayNotHasKey( 'tab_group', $row );
	}

	public function test_push_definition_reads_only_from_meta_acrossai_when_both_shapes_present(): void {
		// If an add-on erroneously provides BOTH the new meta.acrossai shape
		// AND the legacy top-level shape, only meta.acrossai is honored.
		$subject = new class() extends Ability_Definition {
			protected function ability(): array {
				return array(
					'name' => 'test/both-shapes',
					'args' => array(
						'label'           => 'Both Shapes',
						'category'        => 'test-category',
						// Legacy top-level shape — must be ignored.
						'sub_group'       => 'legacy',
						'sub_group_label' => 'Legacy Label',
						'tab_group'       => 'legacy-tab',
						// Canonical Feature 041 shape — must win.
						'meta'            => array(
							'acrossai' => array(
								'sub_group'       => 'canonical',
								'sub_group_label' => 'Canonical Label',
								'tab_group'       => 'canonical-tab',
							),
						),
					),
				);
			}
		};

		$rows = $subject->push_definition( array() );
		$row  = $rows[0];

		$this->assertSame( 'canonical', $row['sub_group'] );
		$this->assertSame( 'Canonical Label', $row['sub_group_label'] );
		$this->assertSame( 'canonical-tab', $row['tab_group'] );
	}

	// -----------------------------------------------------------------------
	// Feature 052 — bulk-toggle static helpers
	// -----------------------------------------------------------------------

	/**
	 * Reset the shared site-option store and the Registry singleton between tests.
	 */
	protected function setUp(): void {
		parent::setUp();
		acrossai_test_site_options( array() );
		$this->reset_registry_definitions();
	}

	/**
	 * Reset the Library Registry's cached definitions via reflection.
	 */
	private function reset_registry_definitions(): void {
		$refl = new ReflectionClass( AcrossAI_Ability_Library_Registry::class );
		$prop = $refl->getProperty( 'definitions' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}
}
