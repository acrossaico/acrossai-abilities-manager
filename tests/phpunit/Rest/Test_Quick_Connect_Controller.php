<?php
/**
 * Feature 099 — Quick Connect REST controller.
 *
 * Focus is the authorization surface and the install allowlist. The install
 * endpoint downloads and activates code, so its negative paths matter more than
 * its happy path (which needs a real filesystem and network and is covered by
 * the quickstart's manual verification instead).
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Rest\AcrossAI_Quick_Connect_Controller as Controller;

/**
 * Covers AcrossAI_Quick_Connect_Controller.
 */
class Test_Quick_Connect_Controller extends TestCase {

	/**
	 * Reset all fixture globals before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['acrossai_test_capabilities']      = array();
		$GLOBALS['acrossai_test_abilities']         = array();
		// Clear the older debug-suite fixture so get_plugins() reads ours
		// regardless of which suite ran first (single process, no isolation).
		unset( $GLOBALS['__acrossai_debug_test_get_plugins'] );
		$GLOBALS['acrossai_test_installed_plugins'] = array();
		$GLOBALS['acrossai_test_active_plugins']    = array();
		$GLOBALS['acrossai_test_registered_routes'] = array();
		unset( $GLOBALS['acrossai_test_reject_nonce'] );

		// The install lock is a transient; a leak from one test would make the
		// next one fail with 409 for reasons that have nothing to do with it.
		$GLOBALS['acrossai_transients'] = array();
	}

	/**
	 * Build a request carrying a nonce header.
	 *
	 * @param  string $slug Slug parameter value.
	 * @return WP_REST_Request
	 */
	private function make_install_request( string $slug ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/acrossai/v1/quick-connect/install-plugin' );
		$request->set_param( 'slug', $slug );

		if ( method_exists( $request, 'set_header' ) ) {
			$request->set_header( 'X-WP-Nonce', 'test-nonce' );
		}

		return $request;
	}

	/**
	 * Grant every capability the install route requires.
	 */
	private function grant_install_capabilities(): void {
		$GLOBALS['acrossai_test_capabilities'] = array( 'manage_options', 'install_plugins', 'activate_plugins' );
	}

	/**
	 * Both routes register under the shared acrossai/v1 namespace.
	 */
	public function test_registers_both_routes_under_shared_namespace(): void {
		Controller::instance()->register_routes();

		$routes = array_keys( $GLOBALS['acrossai_test_registered_routes'] );

		$this->assertContains( 'acrossai/v1/quick-connect/state', $routes );
		$this->assertContains( 'acrossai/v1/quick-connect/install-plugin', $routes );
	}

	/**
	 * Install is refused when the caller holds only manage_options.
	 *
	 * Guards spec FR-029: manage_options alone must not reach a code-installing
	 * endpoint.
	 */
	public function test_install_permission_denied_without_install_plugins(): void {
		$GLOBALS['acrossai_test_capabilities'] = array( 'manage_options' );

		$result = Controller::instance()->check_install_permission( $this->make_install_request( 'acrossai-mcp-manager' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Install is refused when activate_plugins is absent.
	 */
	public function test_install_permission_denied_without_activate_plugins(): void {
		$GLOBALS['acrossai_test_capabilities'] = array( 'manage_options', 'install_plugins' );

		$result = Controller::instance()->check_install_permission( $this->make_install_request( 'acrossai-mcp-manager' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Permission callbacks may only return true, false, or WP_Error.
	 *
	 * A WP_REST_Response is truthy, so returning one would grant access
	 * regardless of the status code inside it — the constitution names this a
	 * critical security defect.
	 */
	public function test_install_permission_returns_only_allowed_types(): void {
		$this->grant_install_capabilities();

		$result = Controller::instance()->check_install_permission( $this->make_install_request( 'acrossai-mcp-manager' ) );

		$this->assertTrue(
			true === $result || false === $result || $result instanceof WP_Error,
			'permission_callback must return true|false|WP_Error only.'
		);
		$this->assertNotInstanceOf( WP_REST_Response::class, $result );
	}

	/**
	 * A slug outside the allowlist is rejected with 400.
	 *
	 * @dataProvider disallowed_slug_provider
	 *
	 * @param string $slug Slug that must be refused.
	 */
	public function test_install_rejects_slugs_outside_allowlist( string $slug ): void {
		$this->grant_install_capabilities();

		$result = Controller::instance()->install_plugin( $this->make_install_request( $slug ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'acrossai_quick_connect_invalid_plugin', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * Slugs that must never be installable from this endpoint.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function disallowed_slug_provider(): array {
		return array(
			'sibling paid plugin' => array( 'acrossai-pro' ),
			'alternative transport' => array( 'mcp-adapter' ),
			'arbitrary plugin' => array( 'hello-dolly' ),
			'empty slug' => array( '' ),
		);
	}

	/**
	 * Rejection messages never leak a filesystem path.
	 */
	public function test_rejection_message_contains_no_filesystem_path(): void {
		$this->grant_install_capabilities();

		$result = Controller::instance()->install_plugin( $this->make_install_request( 'acrossai-pro' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertStringNotContainsString( '/', $result->get_error_message() );
	}

	/**
	 * State reports zero abilities coherently rather than erroring.
	 */
	public function test_state_handles_empty_ability_registry(): void {
		$data = Controller::instance()->get_state()->get_data();

		$this->assertSame( 0, $data['abilities']['total'] );
		$this->assertIsArray( $data['abilities']['tabGroups'] );
	}

	/**
	 * Protected mcp-adapter slugs are excluded from the reported total.
	 *
	 * Keeps the wizard's headline figure equal to the abilities screen (SC-004).
	 */
	public function test_state_excludes_protected_slugs_from_total(): void {
		$GLOBALS['acrossai_test_abilities'] = array(
			'settings/get-site-title'         => (object) array(),
			'content/update-page'             => (object) array(),
			'mcp-adapter/discover-abilities'  => (object) array(),
			'mcp-adapter/execute-ability'     => (object) array(),
			'mcp-adapter/get-ability-info'    => (object) array(),
		);

		$data = Controller::instance()->get_state()->get_data();

		$this->assertSame( 2, $data['abilities']['total'] );
	}

	/**
	 * canInstall mirrors the install route's own capability requirement.
	 *
	 * Spec SC-009: the UI must not offer an action the caller cannot perform.
	 */
	public function test_state_reports_can_install_false_without_capabilities(): void {
		$GLOBALS['acrossai_test_capabilities'] = array( 'manage_options' );

		$data = Controller::instance()->get_state()->get_data();

		$this->assertFalse( $data['canInstall'] );
	}

	/**
	 * canInstall is true only when both capabilities are present.
	 */
	public function test_state_reports_can_install_true_with_both_capabilities(): void {
		$this->grant_install_capabilities();

		$data = Controller::instance()->get_state()->get_data();

		$this->assertTrue( $data['canInstall'] );
	}

	/**
	 * State exposes a transport status for both supported transports.
	 */
	public function test_state_reports_both_transport_states(): void {
		$data = Controller::instance()->get_state()->get_data();

		$this->assertArrayHasKey( 'mcpManager', $data['plugins'] );
		$this->assertArrayHasKey( 'mcpAdapter', $data['plugins'] );
		$this->assertArrayHasKey( 'mcpManagerWizardUrl', $data['plugins'] );
	}

	/**
	 * SEC-006 / FR-031 — a second install cannot start while one is running.
	 *
	 * FR-031 previously lived only in the browser, so a direct caller could set
	 * several downloads and unzips running over the same directory. These tests
	 * hold the server-side guard to the two things that actually matter: that it
	 * refuses a concurrent call, and that it never stays shut afterwards.
	 */
	public function test_concurrent_install_is_refused_with_409(): void {
		$this->grant_install_capabilities();

		// Stand in for a request that is still running.
		set_transient( 'acrossai_quick_connect_install_lock', time(), 120 );

		$result = Controller::instance()->install_plugin(
			$this->make_install_request( 'acrossai-mcp-manager' )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'acrossai_quick_connect_install_in_progress', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
	}

	/**
	 * Invoke the install route, swallowing the environment's own noise.
	 *
	 * The handler requires wp-admin files this WP-less harness does not have, so
	 * PHP emits a warning before throwing. That warning is a fact about the
	 * harness, not about the code under test, and letting it into the suite
	 * output teaches everyone to scroll past warnings.
	 *
	 * @param  string $slug Slug parameter value.
	 * @return mixed  Handler result, or null when it threw.
	 */
	private function install_ignoring_harness_warnings( string $slug ) {
		set_error_handler( static fn(): bool => true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_set_error_handler

		try {
			return Controller::instance()->install_plugin( $this->make_install_request( $slug ) );
		} catch ( \Throwable $e ) {
			unset( $e );

			return null;
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * The regression `finally` exists for.
	 *
	 * The install has several exit paths and can also throw out of the upgrader.
	 * A lock that leaks on one uncommon path is worse than no lock, because the
	 * endpoint then stays shut until the TTL expires and the administrator is
	 * told an install is running when none is.
	 *
	 * This harness has no wp-admin/, so the handler's require throws — which is
	 * precisely the escaping-throwable case the release has to survive. Asserting
	 * through the throw tests the unwind rather than the happy path.
	 */
	public function test_lock_is_released_when_the_install_throws(): void {
		$this->grant_install_capabilities();
		$GLOBALS['acrossai_test_installed_plugins'] = array();

		$this->install_ignoring_harness_warnings( 'acrossai-mcp-manager' );

		$this->assertFalse(
			get_transient( 'acrossai_quick_connect_install_lock' ),
			'The lock must be released even when the install throws.'
		);
	}

	/**
	 * A rejected slug must not take the lock. It does no work, so holding the
	 * endpoint shut over it would be denial of service via a typo.
	 */
	public function test_invalid_slug_does_not_take_the_lock(): void {
		$this->grant_install_capabilities();

		$result = Controller::instance()->install_plugin(
			$this->make_install_request( 'some-other-plugin' )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 400, $result->get_error_data()['status'] );
		$this->assertFalse(
			get_transient( 'acrossai_quick_connect_install_lock' ),
			'A rejected slug must leave the endpoint open.'
		);
	}

	/**
	 * With no lock held the request gets past the guard, or the check would be
	 * indistinguishable from an endpoint that never works. It cannot complete an
	 * install in this harness, so the assertion is that it is not refused as
	 * concurrent — it fails later, for environmental reasons, not at the gate.
	 */
	public function test_no_lock_means_the_request_gets_past_the_guard(): void {
		$this->grant_install_capabilities();
		$GLOBALS['acrossai_test_installed_plugins'] = array();

		$this->assertFalse( get_transient( 'acrossai_quick_connect_install_lock' ) );

		$result = $this->install_ignoring_harness_warnings( 'acrossai-mcp-manager' );
		$code   = $result instanceof WP_Error ? $result->get_error_code() : null;

		$this->assertNotSame( 'acrossai_quick_connect_install_in_progress', $code );
	}
}
