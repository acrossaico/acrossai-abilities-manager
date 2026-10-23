<?php
/**
 * Feature 099 — MCP transport detection.
 *
 * The load-bearing case is the alternative transport (MCP Adapter). It used to be detected by
 * probing for its class, because it is often vendored inside another plugin — see
 * `test_a_bundled_adapter_class_is_not_the_adapter_plugin()` for why that was reversed in 0.0.42
 * and what it costs.
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
	 * A bundled copy of the adapter is NOT the adapter plugin.
	 *
	 * This asserted the opposite until 0.0.42, citing SC-012: a vendored adapter means the site has
	 * a working transport, so reporting "missing" would ask an administrator to install something
	 * they already have. That reasoning was sound when the only way to get a vendored copy was to
	 * have deliberately bundled one.
	 *
	 * It stopped being sound when Elementor began shipping `wordpress/mcp-adapter` in its own
	 * vendor directory, as does AcrossAI MCP Manager. The probe then fired on sites that had never
	 * installed the plugin — including one where `mcp-adapter/mcp-adapter.php` sat installed and
	 * switched OFF while Quick Connect reported "Already active", hiding the Activate button that
	 * would have fixed it.
	 *
	 * **The trade-off is real and deliberate.** A bundled copy does still create the adapter's
	 * default server — verified: with both plugins inactive, `/wp-json/mcp/mcp-adapter-default-server`
	 * answers. So this now reports `missing` for a site that has a serving transport. That is the
	 * lesser error: the card's two actions are install and activate, and neither is meaningful for a
	 * library inside somebody else's plugin, whereas the old answer suppressed the one action that
	 * was.
	 *
	 * @return void
	 */
	public function test_a_bundled_adapter_class_is_not_the_adapter_plugin(): void {
		$this->assertSame( array(), $GLOBALS['acrossai_test_installed_plugins'] );

		// A vendored copy, as Elementor ships one.
		if ( ! class_exists( 'WP\\MCP\\Core\\McpAdapter', false ) ) {
			eval( 'namespace WP\\MCP\\Core; class McpAdapter {}' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test fixture: declares a stub class in an isolated namespace to simulate a vendored dependency.
		}

		$this->assertSame(
			Detector::STATE_MISSING,
			Detector::detect( Detector::TRANSPORT_MCP_ADAPTER ),
			'A class loaded from another plugin\'s vendor directory is not an installable plugin.'
		);
	}

	/**
	 * An installed-but-switched-off adapter plugin reports inactive, not active.
	 *
	 * The case the class probe used to mask, and the one where the wizard has something useful to
	 * offer: an Activate button.
	 *
	 * @return void
	 */
	public function test_an_installed_inactive_adapter_plugin_reports_inactive(): void {
		$GLOBALS['acrossai_test_installed_plugins'] = array(
			'mcp-adapter/mcp-adapter.php' => array( 'Name' => 'MCP Adapter' ),
		);
		$GLOBALS['acrossai_test_active_plugins']    = array();

		$this->assertSame(
			Detector::STATE_INACTIVE,
			Detector::detect( Detector::TRANSPORT_MCP_ADAPTER )
		);
	}

	/**
	 * detect_all() returns a state for every supported transport.
	 *
	 * The count is asserted deliberately. This feeds the Quick Connect step, which names the
	 * transports in prose — "works with A, B and C" — and that sentence is the one thing no test
	 * can check. Pinning the count here means adding a fourth transport fails until someone has
	 * looked at the copy.
	 */
	public function test_detect_all_covers_every_supported_transport(): void {
		$states = Detector::detect_all();

		$this->assertArrayHasKey( Detector::TRANSPORT_MCP_MANAGER, $states );
		$this->assertArrayHasKey( Detector::TRANSPORT_MCP_ADAPTER, $states );
		$this->assertArrayHasKey( Detector::TRANSPORT_ELEMENTOR_MCP, $states );
		$this->assertCount( 3, $states );
	}

	/**
	 * Elementor's MCP server reports missing when Elementor is not here.
	 *
	 * @return void
	 */
	public function test_elementor_mcp_is_missing_without_elementor(): void {
		$this->assertSame(
			Detector::STATE_MISSING,
			Detector::detect( Detector::TRANSPORT_ELEMENTOR_MCP ),
			'No Elementor in a unit run, so there is no server to reach.'
		);
	}

}
