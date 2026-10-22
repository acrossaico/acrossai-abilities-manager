<?php
/**
 * Feature 099 — Quick Connect sidebar placement.
 *
 * add_submenu_page()'s $position takes an absolute slot, not "after this other
 * item", so on a parent menu several plugins register into, the rendered order
 * depends on activation order. Menu::reorder_submenu() states the real rule on a
 * late admin_menu pass; these tests hold it to that rule against the awkward
 * arrangements, since the failure is cosmetic and easy to miss by eye.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Admin\Partials\Menu;

/**
 * Covers reorder_submenu().
 */
class Test_Quick_Connect_Submenu_Order extends TestCase {

	/**
	 * Menu under test.
	 *
	 * @var Menu
	 */
	private $menu;

	/**
	 * Build a fresh Menu and clear the submenu global.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->menu             = new Menu( 'acrossai-abilities-manager', '0.0.34' );
		$GLOBALS['submenu']     = array();
	}

	/**
	 * Leave no globals behind for sibling suites.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['submenu'] );

		parent::tearDown();
	}

	/**
	 * Build one submenu row in WordPress's shape.
	 *
	 * @param  string $title Menu title.
	 * @param  string $slug  Menu slug.
	 * @return array<int, string> Submenu row.
	 */
	private function row( string $title, string $slug ): array {
		return array( $title, 'manage_options', $slug );
	}

	/**
	 * An unrelated row sharing the `acrossai` parent menu.
	 *
	 * These fixtures need *some* sibling between Abilities and the end of the group, to prove the
	 * reorder places Quick Connect relatively rather than at an absolute slot. Until Feature 102
	 * that sibling was the Ability Integrations page; using a retired slug here would read as if
	 * the page still existed, and what the test actually needs is any row it does not own.
	 *
	 * @var string
	 */
	private const SIBLING_SLUG = 'acrossai-addons';

	/**
	 * Read back the slug order.
	 *
	 * @return array<int, string> Slugs in render order.
	 */
	private function slugs(): array {
		return array_map(
			static fn( array $item ): string => $item[2],
			array_values( $GLOBALS['submenu'][ Menu::PARENT_SLUG ] )
		);
	}

	/**
	 * The common case: WordPress parked the link below the pages it must precede.
	 */
	public function test_link_moves_up_to_second_place(): void {
		$GLOBALS['submenu'][ Menu::PARENT_SLUG ] = array(
			$this->row( 'AcrossAI', 'acrossai' ),
			$this->row( 'Quick Connect', Menu::QUICK_CONNECT_SLUG ),
			$this->row( 'Abilities', Menu::ABILITIES_SLUG ),
			$this->row( 'Add-ons', self::SIBLING_SLUG ),
		);

		$this->menu->reorder_submenu();

		$this->assertSame(
			array( 'acrossai', Menu::QUICK_CONNECT_SLUG, Menu::ABILITIES_SLUG, self::SIBLING_SLUG ),
			$this->slugs()
		);
	}

	/**
	 * The link also has to move backwards when it landed at the end.
	 */
	public function test_link_moves_from_the_end_to_second_place(): void {
		$GLOBALS['submenu'][ Menu::PARENT_SLUG ] = array(
			$this->row( 'AcrossAI', 'acrossai' ),
			$this->row( 'Abilities', Menu::ABILITIES_SLUG ),
			$this->row( 'Add-ons', self::SIBLING_SLUG ),
			$this->row( 'Settings', 'acrossai-settings' ),
			$this->row( 'Quick Connect', Menu::QUICK_CONNECT_SLUG ),
		);

		$this->menu->reorder_submenu();

		$this->assertSame(
			array( 'acrossai', Menu::QUICK_CONNECT_SLUG, Menu::ABILITIES_SLUG, self::SIBLING_SLUG, 'acrossai-settings' ),
			$this->slugs()
		);
	}

	/**
	 * Already correct is a no-op, not an off-by-one shuffle.
	 */
	public function test_correct_order_is_left_alone(): void {
		$expected = array( 'acrossai', Menu::QUICK_CONNECT_SLUG, Menu::ABILITIES_SLUG, 'acrossai-settings' );

		$GLOBALS['submenu'][ Menu::PARENT_SLUG ] = array(
			$this->row( 'AcrossAI', 'acrossai' ),
			$this->row( 'Quick Connect', Menu::QUICK_CONNECT_SLUG ),
			$this->row( 'Abilities', Menu::ABILITIES_SLUG ),
			$this->row( 'Settings', 'acrossai-settings' ),
		);

		$this->menu->reorder_submenu();

		$this->assertSame( $expected, $this->slugs() );
	}

	/**
	 * On a site running MCP Manager the link is never registered. Reordering must
	 * leave the sibling's own entries untouched rather than reshuffling them.
	 */
	public function test_absent_link_leaves_the_menu_untouched(): void {
		$expected = array( 'acrossai', Menu::ABILITIES_SLUG, 'acrossai_mcp_manager', 'acrossai-settings' );

		$GLOBALS['submenu'][ Menu::PARENT_SLUG ] = array(
			$this->row( 'AcrossAI', 'acrossai' ),
			$this->row( 'Abilities', Menu::ABILITIES_SLUG ),
			$this->row( 'MCP', 'acrossai_mcp_manager' ),
			$this->row( 'Settings', 'acrossai-settings' ),
		);

		$this->menu->reorder_submenu();

		$this->assertSame( $expected, $this->slugs() );
	}

	/**
	 * A group without the repeated parent row still places the link first.
	 *
	 * The anchor is the parent entry, so its absence used to be a reason to bail.
	 * Front of the group is the closer answer to "second, below AcrossAI" than
	 * wherever WordPress happened to park it.
	 */
	public function test_a_missing_parent_row_puts_the_link_first(): void {
		$GLOBALS['submenu'][ Menu::PARENT_SLUG ] = array(
			$this->row( 'Abilities', Menu::ABILITIES_SLUG ),
			$this->row( 'Settings', 'acrossai-settings' ),
			$this->row( 'Quick Connect', Menu::QUICK_CONNECT_SLUG ),
		);

		$this->menu->reorder_submenu();

		$this->assertSame(
			array( Menu::QUICK_CONNECT_SLUG, Menu::ABILITIES_SLUG, 'acrossai-settings' ),
			$this->slugs()
		);
	}

	/**
	 * Placement does not depend on Abilities being present at all.
	 *
	 * Abilities was the old anchor. Asserting this keeps the new rule from
	 * quietly regaining that dependency.
	 */
	public function test_placement_does_not_depend_on_abilities(): void {
		$GLOBALS['submenu'][ Menu::PARENT_SLUG ] = array(
			$this->row( 'AcrossAI', 'acrossai' ),
			$this->row( 'Settings', 'acrossai-settings' ),
			$this->row( 'Quick Connect', Menu::QUICK_CONNECT_SLUG ),
		);

		$this->menu->reorder_submenu();

		$this->assertSame(
			array( 'acrossai', Menu::QUICK_CONNECT_SLUG, 'acrossai-settings' ),
			$this->slugs()
		);
	}

	/**
	 * An empty or absent parent menu must not fatal.
	 */
	public function test_empty_menu_is_survivable(): void {
		$GLOBALS['submenu'] = array();

		$this->menu->reorder_submenu();

		$this->assertSame( array(), $GLOBALS['submenu'] );
	}
}
