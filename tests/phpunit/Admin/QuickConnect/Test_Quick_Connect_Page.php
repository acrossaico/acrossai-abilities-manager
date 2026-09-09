<?php
/**
 * Feature 099 — Quick Connect page gating.
 *
 * The wizard bundle carries REST calls and a nonce. Spec FR-042 / SC-010 require
 * it to load on no other admin screen, so the guard is a boundary worth asserting
 * automatically rather than checking by hand at the end of the project (SEC-T04).
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Admin\Partials\QuickConnect\QuickConnectPage;

/**
 * Covers QuickConnectPage gating and asset registration.
 */
class Test_Quick_Connect_Page extends TestCase {

	/**
	 * Reset request state and recorded registrations before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		unset( $_GET[ QuickConnectPage::QUERY_ARG ] );

		$GLOBALS['acrossai_test_capabilities']   = array();
		$GLOBALS['acrossai_test_removed_actions'] = array();
		$GLOBALS['acrossai_test_enqueued']       = array(
			'scripts'  => array(),
			'styles'   => array(),
			'localize' => array(),
		);
	}

	/**
	 * Leave no request state behind for sibling suites.
	 */
	protected function tearDown(): void {
		unset( $_GET[ QuickConnectPage::QUERY_ARG ] );

		parent::tearDown();
	}

	/**
	 * The gate is closed when the query flag is absent.
	 */
	public function test_gate_is_closed_without_the_query_flag(): void {
		$this->assertFalse( QuickConnectPage::instance()->is_quick_connect_request() );
	}

	/**
	 * The gate opens only for the exact value `1`.
	 *
	 * @dataProvider non_matching_flag_provider
	 *
	 * @param string $value Query value that must not open the wizard.
	 */
	public function test_gate_stays_closed_for_non_matching_values( string $value ): void {
		$_GET[ QuickConnectPage::QUERY_ARG ] = $value;

		$this->assertFalse( QuickConnectPage::instance()->is_quick_connect_request() );
	}

	/**
	 * Values that must not be treated as "open the wizard".
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function non_matching_flag_provider(): array {
		return array(
			'zero'        => array( '0' ),
			'empty'       => array( '' ),
			'true string' => array( 'true' ),
			'yes'         => array( 'yes' ),
			'two'         => array( '2' ),
		);
	}

	/**
	 * The gate opens for the exact flag.
	 */
	public function test_gate_opens_for_exact_flag(): void {
		$_GET[ QuickConnectPage::QUERY_ARG ] = '1';

		$this->assertTrue( QuickConnectPage::instance()->is_quick_connect_request() );
	}

	/**
	 * No assets are registered on an unrelated admin screen.
	 *
	 * This is the FR-042 / SC-010 boundary: a regression here would ship the
	 * wizard bundle onto the abilities table, settings, and Integrations pages.
	 */
	public function test_enqueue_registers_nothing_without_the_flag(): void {
		QuickConnectPage::instance()->enqueue_assets();

		$this->assertSame( array(), $GLOBALS['acrossai_test_enqueued']['scripts'] );
		$this->assertSame( array(), $GLOBALS['acrossai_test_enqueued']['styles'] );
		$this->assertSame( array(), $GLOBALS['acrossai_test_enqueued']['localize'] );
	}

	/**
	 * The body class is only added on the wizard request.
	 */
	public function test_body_class_only_added_on_wizard_request(): void {
		$page = QuickConnectPage::instance();

		$this->assertSame( 'wp-admin', $page->add_body_class( 'wp-admin' ) );

		$_GET[ QuickConnectPage::QUERY_ARG ] = '1';

		$this->assertStringContainsString(
			QuickConnectPage::BODY_CLASS,
			$page->add_body_class( 'wp-admin' )
		);
	}

	/**
	 * Notice suppression never runs on unrelated screens.
	 *
	 * Suppression strips every admin notice, including security-critical ones
	 * from core and other plugins, so it must stay request-scoped (SEC-007).
	 */
	public function test_notice_suppression_is_request_scoped(): void {
		QuickConnectPage::instance()->suppress_admin_notices();

		$this->assertSame( array(), $GLOBALS['acrossai_test_removed_actions'] );
	}

	/**
	 * On the wizard request, all four notice hooks are cleared.
	 */
	public function test_notice_suppression_clears_all_four_hooks(): void {
		$_GET[ QuickConnectPage::QUERY_ARG ] = '1';

		QuickConnectPage::instance()->suppress_admin_notices();

		$this->assertEqualsCanonicalizing(
			array( 'admin_notices', 'all_admin_notices', 'user_admin_notices', 'network_admin_notices' ),
			$GLOBALS['acrossai_test_removed_actions']
		);
	}

	/**
	 * Rendering without manage_options is refused.
	 *
	 * Defence in depth: the parent page already enforces the capability, but the
	 * check travels with the branch if it is ever reached another way (SEC-005).
	 */
	public function test_render_requires_manage_options(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		QuickConnectPage::instance()->render();
	}

	/**
	 * With the capability, render emits the mount point and a noscript fallback.
	 */
	public function test_render_emits_mount_point_and_noscript(): void {
		$GLOBALS['acrossai_test_capabilities'] = array( 'manage_options' );

		ob_start();

		try {
			QuickConnectPage::instance()->render();
		} finally {
			// Always close the buffer: leaving it open on failure makes PHPUnit
			// report a misleading "risky test" instead of the real error.
			$html = (string) ob_get_clean();
		}

		$this->assertStringContainsString( 'id="' . QuickConnectPage::ROOT_ID . '"', $html );
		$this->assertStringContainsString( '<noscript>', $html );
	}
}
