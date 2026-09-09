<?php
/**
 * Feature 099 — MCP transport detection.
 *
 * The load-bearing case is the alternative transport (MCP Adapter), which is
 * frequently vendored inside another plugin rather than installed standalone. A
 * basename-only check reports "missing" for a perfectly working install, which
 * would strand an administrator on the wizard's instructions screen. The class
 * probe must win.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Mcp_Transport_Detector as Detector;

require_once dirname( __DIR__, 3 ) . '/includes/Utilities/AcrossAI_Mcp_Transport_Detector.php';

/**
 * Covers AcrossAI_Mcp_Transport_Detector.
 */
class Test_Mcp_Transport_Detector extends TestCase {

	/**
	 * Reset the installed-plugin and active-plugin fixtures before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Clear the older debug-suite fixture so get_plugins() reads ours
		// regardless of which suite ran first (single process, no isolation).
		unset( $GLOBALS['__acrossai_debug_test_get_plugins'] );
		$GLOBALS['acrossai_test_installed_plugins'] = array();
		$GLOBALS['acrossai_test_active_plugins']    = array();
	}

	/**
	 * An unknown transport identifier reports missing rather than erroring.
	 */
	public function test_unknown_transport_reports_missing(): void {
		$this->assertSame( Detector::STATE_MISSING, Detector::detect( 'not-a-transport' ) );
	}

	/**
	 * Recommended transport: absent from the installed list.
	 */
	public function test_mcp_manager_missing_when_not_installed(): void {
		$this->assertSame(
			Detector::STATE_MISSING,
			Detector::detect( Detector::TRANSPORT_MCP_MANAGER )
		);
	}

	/**
	 * Recommended transport: installed but not activated.
	 */
	public function test_mcp_manager_inactive_when_installed_only(): void {
		$GLOBALS['acrossai_test_installed_plugins'] = array(
			Detector::MCP_MANAGER_BASENAME => array( 'Name' => 'AcrossAI MCP Manager' ),
		);

		$this->assertSame(
			Detector::STATE_INACTIVE,
			Detector::detect( Detector::TRANSPORT_MCP_MANAGER )
		);
	}

	/**
	 * Recommended transport: installed and active.
	 */
	public function test_mcp_manager_active_when_activated(): void {
		$GLOBALS['acrossai_test_installed_plugins'] = array(
			Detector::MCP_MANAGER_BASENAME => array( 'Name' => 'AcrossAI MCP Manager' ),
		);
		$GLOBALS['acrossai_test_active_plugins']    = array( Detector::MCP_MANAGER_BASENAME );

		$this->assertSame(
			Detector::STATE_ACTIVE,
			Detector::detect( Detector::TRANSPORT_MCP_MANAGER )
		);
	}

	/**
	 * Alternative transport: nothing installed and no class loaded.
	 */
	public function test_mcp_adapter_missing_when_absent(): void {
		$this->assertSame(
			Detector::STATE_MISSING,
			Detector::detect( Detector::TRANSPORT_MCP_ADAPTER )
		);
	}

	/**
	 * Alternative transport: standalone plugin present but not activated.
	 */
	public function test_mcp_adapter_inactive_when_plugin_file_present(): void {
		$GLOBALS['acrossai_test_installed_plugins'] = array(
			'mcp-adapter/mcp-adapter.php' => array( 'Name' => 'MCP Adapter' ),
		);

		$this->assertSame(
			Detector::STATE_INACTIVE,
			Detector::detect( Detector::TRANSPORT_MCP_ADAPTER )
		);
	}

	/**
	 * Alternative transport bundled inside another plugin is still recognised.
	 *
	 * This is the regression this detector exists for: no plugin file, but the
	 * adapter's class is loaded, so the site genuinely has a working transport.
	 * Reporting "missing" here would ask the administrator to install something
	 * they already have (spec SC-012).
	 */
	public function test_mcp_adapter_active_when_bundled_class_is_loaded(): void {
		// No plugin file installed at all.
		$this->assertSame( array(), $GLOBALS['acrossai_test_installed_plugins'] );

		// Simulate a vendored copy by declaring one of the probed classes.
		if ( ! class_exists( 'WP\\MCP\\Core\\McpAdapter', false ) ) {
			eval( 'namespace WP\\MCP\\Core; class McpAdapter {}' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test fixture: declares a stub class in an isolated namespace to simulate a vendored dependency.
		}

		$this->assertSame(
			Detector::STATE_ACTIVE,
			Detector::detect( Detector::TRANSPORT_MCP_ADAPTER )
		);
	}

	/**
	 * detect_all() returns a state for both supported transports.
	 */
	public function test_detect_all_covers_both_transports(): void {
		$states = Detector::detect_all();

		$this->assertArrayHasKey( Detector::TRANSPORT_MCP_MANAGER, $states );
		$this->assertArrayHasKey( Detector::TRANSPORT_MCP_ADAPTER, $states );
		$this->assertCount( 2, $states );
	}
}
