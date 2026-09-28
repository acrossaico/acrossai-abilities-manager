<?php
/**
 * Feature 120 — behavioural tests for the Site Kit guard and context factory.
 *
 * These run in an environment where Site Kit is NOT loaded, which is deliberate: that
 * is the state every guard has to survive, and the one a unit bootstrap can reproduce
 * faithfully.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.39
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\SiteKit\Module_Repository;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\SiteKit\Report_Repository;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\SiteKit\Site_Kit_Context;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\SiteKit\Site_Kit_Guard;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\SiteKit\Status_Repository;
use WP_Error;
use WP_UnitTestCase;

class Test_Site_Kit_Guard extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Site_Kit_Context::flush();
		$GLOBALS['acrossai_test_capabilities']  = array();
		$GLOBALS['acrossai_test_filter_values'] = array();
	}

	protected function tearDown(): void {
		Site_Kit_Context::flush();
		$GLOBALS['acrossai_test_capabilities']  = array();
		$GLOBALS['acrossai_test_filter_values'] = array();
		parent::tearDown();
	}

	/* ------------------------------------------------------------ absence */

	public function test_site_kit_is_reported_absent_when_it_is_not_loaded(): void {
		$this->assertFalse( Site_Kit_Context::available() );
		$this->assertNull( Site_Kit_Context::context() );
		$this->assertNull( Site_Kit_Context::modules() );
	}

	/**
	 * The whole suite's promise: no fatal when Site Kit is gone, just a sentence.
	 */
	public function test_every_guard_degrades_to_an_error_rather_than_fatalling(): void {
		foreach ( array( 'assert_available', 'assert_setup', 'assert_authenticated' ) as $guard ) {
			$result = Site_Kit_Guard::$guard();
			$this->assertInstanceOf( WP_Error::class, $result, "{$guard} should return a WP_Error." );
			$this->assertSame( 'site_kit_missing', $result->get_error_code() );
		}
	}

	public function test_the_module_guard_degrades_too(): void {
		$result = Site_Kit_Guard::assert_module_connected( 'analytics-4' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'site_kit_missing', $result->get_error_code() );
	}

	/**
	 * Repositories are reached directly by the abilities, so each needs its own
	 * absence behaviour rather than relying on the guard having run first.
	 */
	public function test_repositories_return_errors_rather_than_fatalling(): void {
		$this->assertInstanceOf( WP_Error::class, Module_Repository::list_modules() );
		$this->assertInstanceOf( WP_Error::class, Module_Repository::get_module_settings( 'analytics-4' ) );
		$this->assertInstanceOf( WP_Error::class, Module_Repository::set_module_state( 'analytics-4', true ) );
		$this->assertInstanceOf( WP_Error::class, Module_Repository::get_sharing_settings() );
		$this->assertInstanceOf( WP_Error::class, Module_Repository::list_datapoints( 'analytics-4' ) );
		$this->assertInstanceOf( WP_Error::class, Report_Repository::search_analytics( array() ) );
		$this->assertInstanceOf( WP_Error::class, Report_Repository::pagespeed( '', 'mobile' ) );
	}

	/**
	 * Status must ANSWER on a site with nothing connected — that is the state it
	 * exists to describe, so returning an error there would be the wrong shape.
	 */
	public function test_status_answers_rather_than_erroring_when_nothing_is_connected(): void {
		$status = Status_Repository::status();

		$this->assertIsArray( $status );
		$this->assertFalse( $status['setup_completed'] );
		$this->assertFalse( $status['user_authenticated'] );
		$this->assertSame( array(), $status['active_modules'] );
		$this->assertNotSame( '', $status['next_step'] );
	}

	/**
	 * The token is never exposed. Only whether an account is connected, and which.
	 */
	public function test_status_never_exposes_a_token(): void {
		$status = Status_Repository::status();
		$this->assertArrayNotHasKey( 'token', $status );
		$this->assertArrayNotHasKey( 'access_token', $status );
		$this->assertSame( array( 'email' => null, 'photo' => null ), $status['google_account'] );
	}

	/* ------------------------------------------------------ input validation */

	public function test_an_invalid_pagespeed_strategy_is_refused_before_any_request(): void {
		$result = Report_Repository::pagespeed( 'https://example.com', 'tablet' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_input', $result->get_error_code() );
	}

	public function test_an_invalid_pagespeed_detail_level_is_refused(): void {
		$result = Report_Repository::pagespeed( '', 'mobile', 'everything' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_input', $result->get_error_code() );
	}

	/**
	 * The default must stay 'summary'. A real run measured 529 KB, of which the
	 * screenshot alone was 199 KB — defaulting to the raw payload overflows the
	 * response before a caller can read any of it.
	 */
	public function test_the_pagespeed_detail_levels_are_the_documented_three(): void {
		$this->assertSame(
			array( 'summary', 'audits', 'full' ),
			Report_Repository::PAGESPEED_DETAIL
		);
	}

	/**
	 * Analytics cannot run a report with no metrics, and saying so here costs nothing
	 * while letting it through costs a round-trip to Google to be told the same.
	 */
	public function test_an_analytics_report_without_metrics_is_refused_locally(): void {
		$result = Report_Repository::analytics_report( array( 'dimensions' => array( 'pagePath' ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_input', $result->get_error_code() );
		$this->assertStringContainsString( 'metrics', $result->get_error_message() );
	}

	/* ------------------------------------------------------------ capability */

	public function test_an_administrator_passes_on_the_floor_alone(): void {
		$GLOBALS['acrossai_test_capabilities'] = array( 'manage_options' );
		$can = Site_Kit_Guard::can( Site_Kit_Guard::CAP_VIEW );

		$this->assertTrue( $can() );
	}

	/**
	 * Site Kit grants its own capabilities dynamically, so they widen rather than
	 * narrow — otherwise nobody would hold them on a site nobody has connected yet,
	 * and get-status could not run where it is needed most.
	 */
	public function test_site_kit_own_capability_admits_a_non_administrator(): void {
		$GLOBALS['acrossai_test_capabilities'] = array( Site_Kit_Guard::CAP_VIEW );
		$can = Site_Kit_Guard::can( Site_Kit_Guard::CAP_VIEW );

		$this->assertTrue( $can() );
	}

	public function test_a_user_holding_neither_is_refused(): void {
		$GLOBALS['acrossai_test_capabilities'] = array( 'read' );
		$can = Site_Kit_Guard::can( Site_Kit_Guard::CAP_VIEW );

		$this->assertFalse( $can() );
	}

	/**
	 * An ability naming no Site Kit capability is protected by the floor alone, so
	 * has_cap() returning true for '' must not make the check vacuous.
	 */
	public function test_an_ability_with_no_site_kit_capability_still_needs_the_floor(): void {
		$GLOBALS['acrossai_test_capabilities'] = array( 'read' );
		$can = Site_Kit_Guard::can( '' );

		$this->assertFalse( $can() );
	}

	/**
	 * The filter may substitute one capability model for the other, but must not be
	 * able to admit a user holding neither.
	 */
	public function test_the_permission_filter_cannot_admit_a_user_holding_neither(): void {
		$GLOBALS['acrossai_test_capabilities'] = array( 'read' );
		$GLOBALS['acrossai_test_filter_values'][ Site_Kit_Guard::PERMISSION_FILTER ] = true;

		$can = Site_Kit_Guard::can( Site_Kit_Guard::CAP_VIEW );
		$this->assertFalse( $can() );
	}

	public function test_the_permission_filter_can_narrow(): void {
		$GLOBALS['acrossai_test_capabilities'] = array( 'manage_options' );
		$GLOBALS['acrossai_test_filter_values'][ Site_Kit_Guard::PERMISSION_FILTER ] = false;

		$can = Site_Kit_Guard::can( Site_Kit_Guard::CAP_VIEW );
		$this->assertFalse( $can() );
	}

	/* ------------------------------------------------------------- envelope */

	public function test_confirmation_is_required_when_the_flag_is_absent(): void {
		$result = Site_Kit_Guard::assert_confirmed( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'confirmation_required', $result->get_error_code() );
	}

	public function test_confirmation_passes_when_the_flag_is_set(): void {
		$this->assertTrue( Site_Kit_Guard::assert_confirmed( array( 'confirm' => true ) ) );
	}

	/**
	 * Context is caller-supplied input echoed back, so it must never be able to
	 * overwrite the envelope's own verdict.
	 */
	public function test_context_cannot_spoof_success_or_the_error_code(): void {
		$envelope = Site_Kit_Guard::error(
			'site_kit_missing',
			'Site Kit is not active.',
			array( 'success' => true, 'error_code' => 'nope', 'module' => 'analytics-4' )
		);

		$this->assertFalse( $envelope['success'] );
		$this->assertSame( 'site_kit_missing', $envelope['error_code'] );
		$this->assertSame( 'analytics-4', $envelope['module'] );
	}

	public function test_a_payload_cannot_spoof_success(): void {
		$envelope = Site_Kit_Guard::ok( array( 'success' => false, 'rows' => array() ), 'Done.' );

		$this->assertTrue( $envelope['success'] );
		$this->assertSame( 'Done.', $envelope['message'] );
	}
}
