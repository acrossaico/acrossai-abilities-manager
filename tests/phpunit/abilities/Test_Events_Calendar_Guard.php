<?php
/**
 * Feature 109 — Events_Calendar_Guard.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.40
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\EventsCalendar\Events_Calendar_Guard;
use WP_Error;
use WP_UnitTestCase;

class Test_Events_Calendar_Guard extends WP_UnitTestCase {

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
	 * Availability needs the class AND the ORM entry point.
	 *
	 * Every write in this suite goes through tribe_events(); a site where the class is loaded but
	 * the template tags are not would pass a class-only probe and then fatal on the first call.
	 */
	public function test_availability_requires_the_class_and_the_orm(): void {
		$expected = class_exists( 'Tribe__Events__Main' )
			&& function_exists( 'tribe_events' )
			&& function_exists( 'tribe_get_event' );

		$this->assertSame( $expected, Events_Calendar_Guard::is_available() );

		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/EventsCalendar/Events_Calendar_Guard.php'
		);

		$this->assertStringContainsString( "class_exists( 'Tribe__Events__Main' )", $src );
		$this->assertStringContainsString( "function_exists( 'tribe_events' )", $src );
	}

	public function test_assert_available_names_the_plugin_when_it_is_missing(): void {
		$result = Events_Calendar_Guard::assert_available();

		if ( Events_Calendar_Guard::is_available() ) {
			$this->assertTrue( $result );
			return;
		}

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'events_calendar_missing', (string) $result->get_error_code() );
	}

	public function test_assert_confirmed_blocks_until_confirm_is_true(): void {
		$this->assertInstanceOf( WP_Error::class, Events_Calendar_Guard::assert_confirmed( array() ) );
		$this->assertInstanceOf( WP_Error::class, Events_Calendar_Guard::assert_confirmed( array( 'confirm' => false ) ) );
		$this->assertTrue( Events_Calendar_Guard::assert_confirmed( array( 'confirm' => true ) ) );
	}

	public function test_the_confirmation_code_is_stable(): void {
		$result = Events_Calendar_Guard::assert_confirmed( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame(
			'confirmation_required',
			(string) $result->get_error_code(),
			'Clients branch on this code; renaming it is a breaking change.'
		);
	}

	public function test_assert_confirmed_uses_the_supplied_message(): void {
		$result = Events_Calendar_Guard::assert_confirmed( array(), 'This trashes a venue.' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertStringContainsString( 'This trashes a venue.', (string) $result->get_error_message() );
	}

	public function test_the_floor_admits_an_administrator_and_refuses_anyone_else(): void {
		$this->assertTrue(
			$this->with_capabilities( array( 'manage_options' ), static fn (): bool => (bool) call_user_func( Events_Calendar_Guard::can() ) )
		);
		$this->assertFalse(
			$this->with_capabilities( array( 'edit_posts' ), static fn (): bool => (bool) call_user_func( Events_Calendar_Guard::can() ) )
		);
		$this->assertFalse(
			$this->with_capabilities( array(), static fn (): bool => (bool) call_user_func( Events_Calendar_Guard::can() ) )
		);
	}

	public function test_a_raised_floor_is_honoured(): void {
		$this->assertFalse(
			$this->with_capabilities(
				array( 'manage_options' ),
				static fn (): bool => (bool) call_user_func( Events_Calendar_Guard::can( 'manage_network' ) )
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
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/EventsCalendar/Events_Calendar_Guard.php'
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
		$envelope = Events_Calendar_Guard::ok(
			array(
				'event_id'   => 42,
				'success'    => false,
				'error_code' => 'spoofed',
			),
			'Real message.'
		);

		$this->assertTrue( $envelope['success'] );
		$this->assertArrayNotHasKey( 'error_code', $envelope );
		$this->assertSame( 'Real message.', $envelope['message'] );
		$this->assertSame( 42, $envelope['event_id'] );
	}

	public function test_the_failure_envelope_preserves_code_and_message(): void {
		$envelope = Events_Calendar_Guard::fail( new WP_Error( 'dates_rejected', 'The calendar discarded the dates.' ) );

		$this->assertFalse( $envelope['success'] );
		$this->assertSame( 'dates_rejected', $envelope['error_code'] );
		$this->assertStringContainsString( 'The calendar discarded the dates.', $envelope['message'] );
	}
}
