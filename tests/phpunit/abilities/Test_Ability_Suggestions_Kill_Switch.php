<?php
/**
 * Feature 095 — behavioural coverage for the ability-suggestions kill switch.
 *
 * Exercises AcrossAI_Ability_Library_Registry::apply_suggested_abilities_decoration()
 * against seeded rows to prove:
 *
 *   1. When option `acrossai_disable_ability_suggestions` is unset or `0`,
 *      the decoration preserves `args.meta.acrossai.suggested_abilities` on
 *      every row.
 *   2. When option is `1`, the decoration strips `suggested_abilities` on
 *      every row.
 *   3. Only `suggested_abilities` is stripped — every sibling
 *      `meta.acrossai.*` key (including Feature 088's `suggested_plugins`)
 *      survives. Regression guard against over-stripping.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.34
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry;
use WP_UnitTestCase;

/**
 * Class Test_Ability_Suggestions_Kill_Switch.
 */
class Test_Ability_Suggestions_Kill_Switch extends WP_UnitTestCase {

	/**
	 * Reset the shared option store before each test so kill-switch state
	 * from one test never leaks into the next.
	 */
	protected function setUp(): void {
		parent::setUp();
		global $__acrossai_test_options;
		$__acrossai_test_options = array();
	}

	/**
	 * Build a fixture row shape identical to what push_definition() emits —
	 * carrying every meta.acrossai.* key we need to guard against
	 * over-stripping.
	 *
	 * @param bool $with_suggestions Whether to include a suggested_abilities entry.
	 * @return array<string,mixed>
	 */
	private function fixture_row( bool $with_suggestions ): array {
		$acrossai = array(
			'sub_group'         => 'posts',
			'tab_group'         => 'core',
			'suggested_plugins' => array(
				array(
					'slug'   => 'better-search-replace',
					'name'   => 'Better Search Replace',
					'reason' => 'Sibling Feature 088 field — must survive Feature 095 strip.',
				),
			),
		);
		if ( $with_suggestions ) {
			$acrossai['suggested_abilities'] = array(
				array(
					'slug'   => 'blocks/outline-post-blocks',
					'reason' => 'Locate block cheaply before editing.',
					'saves'  => '~29K tokens',
				),
				array(
					'slug'   => 'blocks/update-post-block',
					'reason' => 'Surgical write.',
				),
			);
		}
		return array(
			'category'       => 'acrossai-abilities-manager-content',
			'category_label' => 'Acrossai Abilities Manager Content',
			'slug'           => 'content/update-page',
			'slug_label'     => 'Update Page',
			'name'           => 'content/update-page',
			'args'           => array(
				'label'       => 'Update Page',
				'description' => 'Update a page.',
				'meta'        => array(
					'acrossai'    => $acrossai,
					'mcp'         => array( 'public' => false, 'type' => 'tool' ),
					'annotations' => array( 'readonly' => false ),
				),
			),
		);
	}

	// -----------------------------------------------------------------------
	// Option OFF (default) — field preserved verbatim.
	// -----------------------------------------------------------------------

	public function test_option_unset_preserves_suggested_abilities(): void {
		global $__acrossai_test_options;
		unset( $__acrossai_test_options['acrossai_disable_ability_suggestions'] );

		$rows       = array( $this->fixture_row( true ) );
		$decorated  = AcrossAI_Ability_Library_Registry::apply_suggested_abilities_decoration( $rows );

		$this->assertArrayHasKey(
			'suggested_abilities',
			$decorated[0]['args']['meta']['acrossai'],
			'With option UNSET (default), suggested_abilities must be preserved'
		);
		$this->assertCount( 2, $decorated[0]['args']['meta']['acrossai']['suggested_abilities'] );
	}

	public function test_option_zero_preserves_suggested_abilities(): void {
		global $__acrossai_test_options;
		$__acrossai_test_options['acrossai_disable_ability_suggestions'] = 0;

		$rows      = array( $this->fixture_row( true ) );
		$decorated = AcrossAI_Ability_Library_Registry::apply_suggested_abilities_decoration( $rows );

		$this->assertArrayHasKey(
			'suggested_abilities',
			$decorated[0]['args']['meta']['acrossai'],
			'With option = 0, suggested_abilities must be preserved'
		);
	}

	// -----------------------------------------------------------------------
	// Option ON — field stripped from every row.
	// -----------------------------------------------------------------------

	public function test_option_one_strips_suggested_abilities(): void {
		global $__acrossai_test_options;
		$__acrossai_test_options['acrossai_disable_ability_suggestions'] = 1;

		$rows      = array( $this->fixture_row( true ), $this->fixture_row( true ) );
		$decorated = AcrossAI_Ability_Library_Registry::apply_suggested_abilities_decoration( $rows );

		foreach ( $decorated as $index => $row ) {
			$this->assertArrayNotHasKey(
				'suggested_abilities',
				$row['args']['meta']['acrossai'],
				"Row {$index}: with option = 1, suggested_abilities MUST be stripped"
			);
		}
	}

	public function test_option_truthy_string_strips(): void {
		global $__acrossai_test_options;
		$__acrossai_test_options['acrossai_disable_ability_suggestions'] = '1';

		$rows      = array( $this->fixture_row( true ) );
		$decorated = AcrossAI_Ability_Library_Registry::apply_suggested_abilities_decoration( $rows );

		$this->assertArrayNotHasKey(
			'suggested_abilities',
			$decorated[0]['args']['meta']['acrossai'],
			'Truthy string "1" must be treated identically to int 1'
		);
	}

	// -----------------------------------------------------------------------
	// Regression guards — the strip touches ONLY suggested_abilities.
	// -----------------------------------------------------------------------

	public function test_strip_preserves_sibling_meta_acrossai_keys(): void {
		global $__acrossai_test_options;
		$__acrossai_test_options['acrossai_disable_ability_suggestions'] = 1;

		$rows      = array( $this->fixture_row( true ) );
		$decorated = AcrossAI_Ability_Library_Registry::apply_suggested_abilities_decoration( $rows );

		$acrossai = $decorated[0]['args']['meta']['acrossai'];

		$this->assertArrayHasKey( 'sub_group', $acrossai, 'sub_group must survive the strip' );
		$this->assertArrayHasKey( 'tab_group', $acrossai, 'tab_group must survive the strip' );
		$this->assertArrayHasKey(
			'suggested_plugins',
			$acrossai,
			'Feature 088 suggested_plugins must survive Feature 095 strip'
		);
		$this->assertCount( 1, $acrossai['suggested_plugins'], 'suggested_plugins value must be preserved verbatim' );
	}

	public function test_strip_preserves_top_level_meta_keys(): void {
		global $__acrossai_test_options;
		$__acrossai_test_options['acrossai_disable_ability_suggestions'] = 1;

		$rows      = array( $this->fixture_row( true ) );
		$decorated = AcrossAI_Ability_Library_Registry::apply_suggested_abilities_decoration( $rows );

		$meta = $decorated[0]['args']['meta'];

		$this->assertArrayHasKey( 'mcp', $meta );
		$this->assertArrayHasKey( 'annotations', $meta );
	}

	public function test_row_without_suggestions_is_untouched_when_option_on(): void {
		global $__acrossai_test_options;
		$__acrossai_test_options['acrossai_disable_ability_suggestions'] = 1;

		$rows      = array( $this->fixture_row( false ) );
		$decorated = AcrossAI_Ability_Library_Registry::apply_suggested_abilities_decoration( $rows );

		$this->assertArrayNotHasKey(
			'suggested_abilities',
			$decorated[0]['args']['meta']['acrossai']
		);
		// Sanity — the row is otherwise byte-identical to the fixture (SC-005).
		$this->assertSame( $rows[0], $decorated[0], 'Row with no suggested_abilities is untouched when option ON — SC-005 guarantee' );
	}
}
