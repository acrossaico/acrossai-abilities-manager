<?php
/**
 * Feature 099 — activation redirect guard matrix.
 *
 * Hijacking an administrator's screen is intrusive, so each guard is asserted
 * to block on its own. A guard that silently stops working would not fail any
 * other test — the wizard would simply start appearing when it should not.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Admin\Partials\QuickConnect\ActivationRedirect;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Mcp_Transport_Detector as Detector;

/**
 * Covers ActivationRedirect::should_redirect().
 */
class Test_Activation_Redirect extends TestCase {

	/**
	 * Start each test from "everything permits the redirect".
	 */
	protected function setUp(): void {
		parent::setUp();

		unset( $_GET['activate-multi'] );
		unset( $GLOBALS['__acrossai_debug_test_get_plugins'] );

		$GLOBALS['acrossai_test_capabilities']      = array( 'manage_options' );
		$GLOBALS['acrossai_test_installed_plugins'] = array();
		$GLOBALS['acrossai_test_active_plugins']    = array();
	}

	/**
	 * Leave no request state behind for sibling suites.
	 */
	protected function tearDown(): void {
		unset( $_GET['activate-multi'] );

		parent::tearDown();
	}

	/**
	 * Baseline: a fresh single site with no transport opens the wizard.
	 *
	 * If this ever fails, every other assertion here is meaningless — they all
	 * work by breaking one condition away from this state.
	 */
	public function test_redirects_on_a_fresh_site_with_no_transport(): void {
		$this->assertTrue( ActivationRedirect::instance()->should_redirect() );
	}

	/**
	 * Guard: bulk activation must not hijack the Plugins screen.
	 */
	public function test_blocked_during_bulk_activation(): void {
		$_GET['activate-multi'] = '1';

		$this->assertFalse( ActivationRedirect::instance()->should_redirect() );
	}

	/**
	 * Guard: a user without manage_options is never redirected.
	 */
	public function test_blocked_without_manage_options(): void {
		$GLOBALS['acrossai_test_capabilities'] = array();

		$this->assertFalse( ActivationRedirect::instance()->should_redirect() );
	}

	/**
	 * Guard: an active recommended transport means the site is already working.
	 */
	public function test_blocked_when_recommended_transport_is_active(): void {
		$GLOBALS['acrossai_test_installed_plugins'] = array(
			Detector::MCP_MANAGER_BASENAME => array( 'Name' => 'AcrossAI MCP Manager' ),
		);
		$GLOBALS['acrossai_test_active_plugins']    = array( Detector::MCP_MANAGER_BASENAME );

		$this->assertFalse( ActivationRedirect::instance()->should_redirect() );
	}

	/**
	 * An installed-but-INACTIVE recommended transport must NOT suppress the wizard.
	 *
	 * Spec FR-002 suppresses only when the transport is "installed and active".
	 * An inactive copy connects nothing, so that site's abilities are
	 * unreachable and its administrator needs the wizard — the transport screen
	 * offers them a one-click "Continue - Activate the plugin".
	 *
	 * This case originally asserted the opposite. The wizard was reported
	 * missing on a real site whose MCP Manager was installed but switched off,
	 * which is exactly the population the wizard helps most.
	 */
	public function test_not_blocked_when_recommended_transport_is_installed_but_inactive(): void {
		$GLOBALS['acrossai_test_installed_plugins'] = array(
			Detector::MCP_MANAGER_BASENAME => array( 'Name' => 'AcrossAI MCP Manager' ),
		);

		$this->assertTrue( ActivationRedirect::instance()->should_redirect() );
	}

	/**
	 * The alternative transport does NOT suppress the redirect.
	 *
	 * Spec FR-002a — an explicit product decision, not an oversight: sites
	 * running only MCP Adapter are still introduced to the recommended
	 * transport. Asserted because it looks like a bug to anyone reading the
	 * guard list quickly, and a well-meaning "fix" would break the requirement.
	 */
	public function test_not_blocked_when_only_the_alternative_transport_is_present(): void {
		$GLOBALS['acrossai_test_installed_plugins'] = array(
			'mcp-adapter/mcp-adapter.php' => array( 'Name' => 'MCP Adapter' ),
		);
		$GLOBALS['acrossai_test_active_plugins']    = array( 'mcp-adapter/mcp-adapter.php' );

		$this->assertTrue( ActivationRedirect::instance()->should_redirect() );
	}

	/**
	 * The destination is step 1 of the wizard on the manager page.
	 */
	public function test_wizard_url_targets_step_one(): void {
		$url = ActivationRedirect::instance()->wizard_url();

		$this->assertStringContainsString( 'page=acrossai-abilities-manager', $url );
		$this->assertStringContainsString( 'quick-connect=1', $url );
		$this->assertStringContainsString( 'step=1', $url );
	}

	/**
	 * Guards are independent: each one blocks on its own.
	 *
	 * Catches a refactor that accidentally makes one guard depend on another
	 * (for example an early return that skips the capability check).
	 *
	 * @dataProvider single_blocking_condition_provider
	 *
	 * @param callable $apply Applies exactly one blocking condition.
	 */
	public function test_each_guard_blocks_independently( callable $apply ): void {
		$apply();

		$this->assertFalse( ActivationRedirect::instance()->should_redirect() );
	}

	/**
	 * One blocking condition per case, applied to the otherwise-permitting state.
	 *
	 * @return array<string, array{0: callable}>
	 */
	public static function single_blocking_condition_provider(): array {
		return array(
			'bulk activation' => array(
				static function (): void {
					$_GET['activate-multi'] = '1';
				},
			),
			'no capability' => array(
				static function (): void {
					$GLOBALS['acrossai_test_capabilities'] = array();
				},
			),
			'recommended transport active' => array(
				static function (): void {
					$GLOBALS['acrossai_test_installed_plugins'] = array(
						Detector::MCP_MANAGER_BASENAME => array( 'Name' => 'AcrossAI MCP Manager' ),
					);
					$GLOBALS['acrossai_test_active_plugins'] = array( Detector::MCP_MANAGER_BASENAME );
				},
			),
		);
	}
}
