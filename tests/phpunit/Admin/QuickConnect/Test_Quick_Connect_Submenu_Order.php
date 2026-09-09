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
	 * The common case: WordPress parked the link at the top of the group.
	 */
	public function test_link_moves_from_the_top_to_just_after_abilities(): void {
		$GLOBALS['submenu'][ Menu::PARENT_SLUG ] = array(
			$this->row( 'AcrossAI', 'acrossai' ),
			$this->row( 'Quick Connect', Menu::QUICK_CONNECT_SLUG ),
			$this->row( 'Abilities', Menu::ABILITIES_SLUG ),
			$this->row( 'Integrations', 'acrossai-abilities-integrations' ),
		);

		$this->menu->reorder_submenu();

		$this->assertSame(
			array( 'acrossai', Menu::ABILITIES_SLUG, Menu::QUICK_CONNECT_SLUG, 'acrossai-abilities-integrations' ),
			$this->slugs()
		);
	}

	/**
	 * The link also has to move backwards when it landed at the end.
	 */
	public function test_link_moves_from_the_end_to_just_after_abilities(): void {
		$GLOBALS['submenu'][ Menu::PARENT_SLUG ] = array(
			$this->row( 'AcrossAI', 'acrossai' ),
			$this->row( 'Abilities', Menu::ABILITIES_SLUG ),
			$this->row( 'Integrations', 'acrossai-abilities-integrations' ),
			$this->row( 'Settings', 'acrossai-settings' ),
			$this->row( 'Quick Connect', Menu::QUICK_CONNECT_SLUG ),
		);

		$this->menu->reorder_submenu();

		$this->assertSame(
			array( 'acrossai', Menu::ABILITIES_SLUG, Menu::QUICK_CONNECT_SLUG, 'acrossai-abilities-integrations', 'acrossai-settings' ),
			$this->slugs()
		);
	}

	/**
	 * Already correct is a no-op, not an off-by-one shuffle.
	 */
	public function test_correct_order_is_left_alone(): void {
		$expected = array( 'acrossai', Menu::ABILITIES_SLUG, Menu::QUICK_CONNECT_SLUG, 'acrossai-settings' );

		$GLOBALS['submenu'][ Menu::PARENT_SLUG ] = array(
			$this->row( 'AcrossAI', 'acrossai' ),
			$this->row( 'Abilities', Menu::ABILITIES_SLUG ),
			$this->row( 'Quick Connect', Menu::QUICK_CONNECT_SLUG ),
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
	 * A missing Abilities entry means there is nothing to anchor to; the link is
	 * left where it is rather than being dropped.
	 */
	public function test_missing_anchor_preserves_the_link(): void {
		$GLOBALS['submenu'][ Menu::PARENT_SLUG ] = array(
			$this->row( 'AcrossAI', 'acrossai' ),
			$this->row( 'Quick Connect', Menu::QUICK_CONNECT_SLUG ),
		);

		$this->menu->reorder_submenu();

		$this->assertContains( Menu::QUICK_CONNECT_SLUG, $this->slugs() );
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
