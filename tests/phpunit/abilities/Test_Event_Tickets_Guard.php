<?php
/**
 * Feature 110 — Event_Tickets_Guard.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.41
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\EventTickets\Event_Tickets_Guard;
use WP_Error;
use WP_UnitTestCase;

class Test_Event_Tickets_Guard extends WP_UnitTestCase {

	/**
	 * @param string[] $caps Capabilities the current user should hold.
	 */
	private function with_capabilities( array $caps, callable $body ) {
		$original                              = $GLOBALS['acrossai_test_capabilities'] ?? array();
		$GLOBALS['acrossai_test_capabilities'] = $caps;

		try {
			return $body();
		} finally {
			$GLOBALS['acrossai_test_capabilities'] = $original;
		}
	}

	/**
	 * Availability needs both Event Tickets classes, and NOT The Events Calendar.
	 */
	public function test_availability_requires_event_tickets_only(): void {
		$expected = class_exists( 'Tribe__Tickets__Main' ) && class_exists( 'Tribe__Tickets__Tickets' );

		$this->assertSame( $expected, Event_Tickets_Guard::is_available() );

		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/EventTickets/Event_Tickets_Guard.php'
		);

		$this->assertStringContainsString( "class_exists( 'Tribe__Tickets__Main' )", $src );
		$this->assertStringNotContainsString(
			"class_exists( 'Tribe__Events__Main' )",
			$src,
			'Event Tickets does not require The Events Calendar; gating on it would hide the suite on a site that only sells tickets on pages.'
		);
	}

	/**
	 * Provider availability is reported, not gated on.
	 */
	public function test_provider_availability_is_reported(): void {
		$this->assertIsBool( Event_Tickets_Guard::commerce_enabled() );
		$this->assertIsBool( Event_Tickets_Guard::plus_active() );
	}

	public function test_assert_available_names_the_plugin_when_it_is_missing(): void {
		$result = Event_Tickets_Guard::assert_available();

		if ( Event_Tickets_Guard::is_available() ) {
			$this->assertTrue( $result );
			return;
		}

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'event_tickets_missing', (string) $result->get_error_code() );
	}

	public function test_assert_confirmed_blocks_until_confirm_is_true(): void {
		$this->assertInstanceOf( WP_Error::class, Event_Tickets_Guard::assert_confirmed( array() ) );
		$this->assertInstanceOf( WP_Error::class, Event_Tickets_Guard::assert_confirmed( array( 'confirm' => false ) ) );
		$this->assertTrue( Event_Tickets_Guard::assert_confirmed( array( 'confirm' => true ) ) );
	}

	public function test_the_confirmation_code_is_stable(): void {
		$result = Event_Tickets_Guard::assert_confirmed( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame(
			'confirmation_required',
			(string) $result->get_error_code(),
			'Clients branch on this code; renaming it is a breaking change.'
		);
	}

	public function test_assert_confirmed_uses_the_supplied_message(): void {
		$result = Event_Tickets_Guard::assert_confirmed( array(), 'This deletes a ticket permanently.' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertStringContainsString( 'This deletes a ticket permanently.', (string) $result->get_error_message() );
	}

	public function test_the_floor_admits_an_administrator_and_refuses_anyone_else(): void {
		$this->assertTrue(
			$this->with_capabilities( array( 'manage_options' ), static fn (): bool => (bool) call_user_func( Event_Tickets_Guard::can() ) )
		);
		$this->assertFalse(
			$this->with_capabilities( array( 'edit_posts' ), static fn (): bool => (bool) call_user_func( Event_Tickets_Guard::can() ) )
		);
		$this->assertFalse(
			$this->with_capabilities( array(), static fn (): bool => (bool) call_user_func( Event_Tickets_Guard::can() ) )
		);
	}

	public function test_a_raised_floor_is_honoured(): void {
		$this->assertFalse(
			$this->with_capabilities(
				array( 'manage_options' ),
				static fn (): bool => (bool) call_user_func( Event_Tickets_Guard::can( 'manage_network' ) )
			)
		);
	}

	/**
	 * The permission filter may tighten access, never widen it.
	 *
	 * Asserted structurally because the stub bootstrap's add_filter() is a no-op, so a behavioural
	 * test would pass whichever way round the code was — which is exactly how the broken shape
	 * survived in four other guards until Feature 106.
	 */
	public function test_the_permission_filter_cannot_widen_access(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/EventTickets/Event_Tickets_Guard.php'
		);

		$code = '';

		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			$code .= is_array( $token ) ? $token[1] : $token;
		}

		$this->assertMatchesRegularExpression(
			'/if\s*\(\s*!\s*current_user_can\(\s*\$floor\s*\)\s*\)\s*\{\s*return\s+false;\s*\}/',
			$code,
			'can() must return false before consulting the filter, so the filter can only ever deny.'
		);
		$this->assertStringContainsString(
			'apply_filters( self::PERMISSION_FILTER, true, $floor )',
			$code,
			'The filter must be passed true, not a computed value it could invert.'
		);
	}

	public function test_the_success_envelope_cannot_be_spoofed(): void {
		$envelope = Event_Tickets_Guard::ok(
			array(
				'ticket_id'  => 42,
				'success'    => false,
				'error_code' => 'spoofed',
			),
			'Real message.'
		);

		$this->assertTrue( $envelope['success'] );
		$this->assertArrayNotHasKey( 'error_code', $envelope );
		$this->assertSame( 'Real message.', $envelope['message'] );
		$this->assertSame( 42, $envelope['ticket_id'] );
	}

	public function test_the_failure_envelope_preserves_code_and_message(): void {
		$envelope = Event_Tickets_Guard::fail( new WP_Error( 'capacity_rejected', 'The capacity did not apply.' ) );

		$this->assertFalse( $envelope['success'] );
		$this->assertSame( 'capacity_rejected', $envelope['error_code'] );
		$this->assertStringContainsString( 'The capacity did not apply.', $envelope['message'] );
	}
}
