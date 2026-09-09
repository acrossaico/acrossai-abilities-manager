<?php
/**
 * Feature 099 — Quick Connect re-entry points.
 *
 * Four surfaces link into the wizard: the sidebar submenu, the plugins-row
 * action link, the settings-tab button, and the toolbar chip. Each takes both
 * its destination and its visibility from EntryPoints, so tests on that one
 * class are what keep them from drifting — the failure modes being an entry
 * point landing on a step the others do not, or one surface still advertising
 * the wizard on a site where the others have stood down.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Admin\Partials\QuickConnect\AdminBarEntry;
use AcrossAI_Abilities_Manager\Admin\Partials\QuickConnect\EntryPoints;
use AcrossAI_Abilities_Manager\Admin\Partials\QuickConnect\QuickConnectPage;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Mcp_Transport_Detector as Detector;

/**
 * Covers the shared entry-point rules and the toolbar node.
 */
class Test_Admin_Bar_Entry extends TestCase {

	/**
	 * Reset request, capability and plugin state before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		unset( $_GET[ QuickConnectPage::QUERY_ARG ] );
		unset( $GLOBALS['__acrossai_debug_test_get_plugins'] );

		$GLOBALS['acrossai_test_capabilities']      = array( 'manage_options' );
		$GLOBALS['acrossai_test_installed_plugins'] = array();
		$GLOBALS['acrossai_test_active_plugins']    = array();

		EntryPoints::reset_availability_cache();
	}

	/**
	 * Leave no request state behind for sibling suites.
	 */
	protected function tearDown(): void {
		unset( $_GET[ QuickConnectPage::QUERY_ARG ] );

		EntryPoints::reset_availability_cache();

		parent::tearDown();
	}

	/**
	 * The canonical URL targets the wizard's first step on the manager page.
	 */
	public function test_wizard_url_points_at_step_one(): void {
		$url = EntryPoints::wizard_url();

		$this->assertStringContainsString( 'page=acrossai-abilities-manager', $url );
		$this->assertStringContainsString( QuickConnectPage::QUERY_ARG . '=1', $url );
		$this->assertStringContainsString( 'step=1', $url );
	}

	/**
	 * AdminBarEntry delegates rather than building a second copy of the URL.
	 */
	public function test_admin_bar_entry_delegates_the_wizard_url(): void {
		$this->assertSame( EntryPoints::wizard_url(), AdminBarEntry::wizard_url() );
	}

	/**
	 * The submenu registered in Menu.php must resolve to the same destination as
	 * the shared helper — the sidebar uses a URL literal rather than calling it.
	 */
	public function test_submenu_url_literal_matches_shared_helper(): void {
		$literal = 'admin.php?page=acrossai-abilities-manager&' . QuickConnectPage::QUERY_ARG . '=1&step=1';

		$this->assertSame( admin_url( $literal ), EntryPoints::wizard_url() );
	}

	/**
	 * With no recommended transport present, every surface is offered.
	 */
	public function test_entry_points_are_available_when_mcp_manager_is_missing(): void {
		$this->assertTrue( EntryPoints::is_available() );
	}

	/**
	 * An installed-but-inactive MCP Manager registers none of its own entry
	 * points, so hiding ours would leave no route into the wizard at all —
	 * including on the very site the activation redirect sends here.
	 */
	public function test_entry_points_are_available_when_mcp_manager_is_inactive(): void {
		$GLOBALS['acrossai_test_installed_plugins'] = array(
			Detector::MCP_MANAGER_BASENAME => array( 'Name' => 'AcrossAI MCP Manager' ),
		);

		$this->assertTrue( EntryPoints::is_available() );
	}

	/**
	 * An active MCP Manager stands every surface down — it advertises its own
	 * Quick Connect in these same places.
	 */
	public function test_entry_points_are_withdrawn_when_mcp_manager_is_active(): void {
		$GLOBALS['acrossai_test_installed_plugins'] = array(
			Detector::MCP_MANAGER_BASENAME => array( 'Name' => 'AcrossAI MCP Manager' ),
		);
		$GLOBALS['acrossai_test_active_plugins']    = array( Detector::MCP_MANAGER_BASENAME );

		$this->assertFalse( EntryPoints::is_available() );
	}

	/**
	 * Withdrawing the advertising must not disable the wizard itself — a saved
	 * link has to keep working so support can walk someone through it.
	 */
	public function test_wizard_url_still_resolves_when_entry_points_are_withdrawn(): void {
		$GLOBALS['acrossai_test_installed_plugins'] = array(
			Detector::MCP_MANAGER_BASENAME => array( 'Name' => 'AcrossAI MCP Manager' ),
		);
		$GLOBALS['acrossai_test_active_plugins']    = array( Detector::MCP_MANAGER_BASENAME );

		$this->assertFalse( EntryPoints::is_available() );
		$this->assertStringContainsString( 'step=1', EntryPoints::wizard_url() );
	}

	/**
	 * Users without manage_options never see the toolbar entry — the same gate
	 * the page itself applies, so the link is absent rather than refused.
	 */
	public function test_node_is_not_registered_without_capability(): void {
		$GLOBALS['acrossai_test_capabilities'] = array();

		$bar = $this->make_admin_bar();
		AdminBarEntry::instance()->register_node( $bar );

		$this->assertSame( array(), $bar->nodes );
	}

	/**
	 * An administrator gets the chip, pointing at the shared wizard URL.
	 */
	public function test_node_is_registered_for_administrators(): void {
		$bar = $this->make_admin_bar();
		AdminBarEntry::instance()->register_node( $bar );

		$this->assertArrayHasKey( AdminBarEntry::NODE_ID, $bar->nodes );
		$this->assertSame(
			EntryPoints::wizard_url(),
			$bar->nodes[ AdminBarEntry::NODE_ID ]['href']
		);
	}

	/**
	 * The chip is suppressed on the wizard itself — linking to the page already
	 * on screen is noise, and the wizard hides the toolbar regardless.
	 */
	public function test_node_is_suppressed_on_the_wizard_screen(): void {
		$_GET[ QuickConnectPage::QUERY_ARG ] = '1';

		$bar = $this->make_admin_bar();
		AdminBarEntry::instance()->register_node( $bar );

		$this->assertSame( array(), $bar->nodes );
	}

	/**
	 * The chip is suppressed on sites running MCP Manager.
	 */
	public function test_node_is_suppressed_when_mcp_manager_is_active(): void {
		$GLOBALS['acrossai_test_installed_plugins'] = array(
			Detector::MCP_MANAGER_BASENAME => array( 'Name' => 'AcrossAI MCP Manager' ),
		);
		$GLOBALS['acrossai_test_active_plugins']    = array( Detector::MCP_MANAGER_BASENAME );

		$bar = $this->make_admin_bar();
		AdminBarEntry::instance()->register_node( $bar );

		$this->assertSame( array(), $bar->nodes );
	}

	/**
	 * Minimal WP_Admin_Bar stand-in recording what was added.
	 *
	 * @return object Recorder exposing a public $nodes array.
	 */
	private function make_admin_bar(): object {
		return new class() {
			/**
			 * Nodes added, keyed by id.
			 *
			 * @var array<string, array<string, mixed>>
			 */
			public array $nodes = array();

			/**
			 * Record a node.
			 *
			 * @param array<string, mixed> $args Node arguments.
			 * @return void
			 */
			public function add_node( array $args ): void {
				$this->nodes[ $args['id'] ] = $args;
			}
		};
	}
}
