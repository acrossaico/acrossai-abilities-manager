<?php
/**
 * Feature 099 — integration group summary shape.
 *
 * Spec SC-004 requires the wizard's groups to match the Integrations admin page
 * exactly. That page derives its tabs in JavaScript at render time
 * (PATTERN-ABILITY-LIBRARY-TAB-AUTO-DERIVE), so the two derivations can drift
 * silently. These assertions pin the contract the wizard depends on.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Rest\AcrossAI_Quick_Connect_Controller as Controller;

/**
 * Covers the tabGroups portion of GET /quick-connect/state.
 */
class Test_Quick_Connect_Tab_Groups extends TestCase {

	/**
	 * Reset fixtures before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['acrossai_test_capabilities'] = array( 'manage_options' );
		$GLOBALS['acrossai_test_abilities']    = array();
		unset( $GLOBALS['acrossai_test_filter_values'] );
	}

	/**
	 * An empty catalogue yields an empty list, not a malformed one.
	 *
	 * The wizard renders an explained empty state from this, so the shape must
	 * stay an array rather than becoming null.
	 */
	public function test_empty_catalogue_returns_empty_array(): void {
		$data = Controller::instance()->get_state()->get_data();

		$this->assertIsArray( $data['abilities']['tabGroups'] );
		$this->assertSame( array(), $data['abilities']['tabGroups'] );
	}

	/**
	 * Stand in for the Library module's filter provider.
	 *
	 * @param  array<int, mixed> $rows Rows the provider would return.
	 * @return void
	 */
	private function publish_groups( array $rows ): void {
		$GLOBALS['acrossai_test_filter_values']['acrossai_ability_library_tab_group_summary'] = $rows;
	}

	/**
	 * Every row exposes exactly the three documented keys, correctly typed.
	 *
	 * The client indexes on these names; a rename would break the screen
	 * without failing anything else.
	 */
	public function test_rows_expose_the_documented_shape(): void {
		$this->publish_groups(
			array(
				array(
					'key'   => 'core',
					'label' => 'Core',
					'count' => 105,
				),
				array(
					'key'   => 'content-search',
					'label' => 'Content Search',
					'count' => 11,
				),
			)
		);

		$rows = Controller::instance()->get_state()->get_data()['abilities']['tabGroups'];

		$this->assertCount( 2, $rows );

		foreach ( $rows as $row ) {
			$this->assertSame( array( 'key', 'label', 'count' ), array_keys( $row ) );
			$this->assertIsInt( $row['count'] );
			$this->assertIsString( $row['label'] );
		}
	}

	/**
	 * Malformed rows from a third-party filter are dropped, not forwarded.
	 *
	 * The filter is a public extension point, so another plugin can return
	 * anything. A keyless row would render as a blank entry on the wizard's
	 * showcase screen.
	 */
	public function test_malformed_rows_are_discarded(): void {
		$this->publish_groups(
			array(
				array(
					'key'   => 'core',
					'label' => 'Core',
					'count' => 3,
				),
				array( 'label' => 'No key at all' ),
				array(
					'key'   => '',
					'label' => 'Empty key',
				),
				'not-an-array',
			)
		);

		$rows = Controller::instance()->get_state()->get_data()['abilities']['tabGroups'];

		$this->assertCount( 1, $rows );
		$this->assertSame( 'core', $rows[0]['key'] );
	}

	/**
	 * Counts are coerced to integers even when the provider supplies strings.
	 */
	public function test_counts_are_coerced_to_integers(): void {
		$this->publish_groups(
			array(
				array(
					'key'   => 'blocks',
					'label' => 'Blocks',
					'count' => '79',
				),
			)
		);

		$rows = Controller::instance()->get_state()->get_data()['abilities']['tabGroups'];

		$this->assertSame( 79, $rows[0]['count'] );
	}
}
