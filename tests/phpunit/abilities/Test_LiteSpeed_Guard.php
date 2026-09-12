<?php
/**
 * Feature 104 — the LiteSpeed guard's behaviour when the host plugin is absent.
 *
 * This is the half of the suite that live verification cannot reach: on a site with LiteSpeed active
 * every ability succeeds, and the interesting path — what happens when it is NOT — only appears when
 * the plugin is gone. The PHPUnit bootstrap loads no plugins, so `\LiteSpeed\Core` does not exist
 * here and this file exercises exactly that state.
 *
 * It matters because the bootstrap gate is not the only defence: LiteSpeed can be deactivated after
 * the abilities were registered in the same request, so every execute() re-checks. If that check
 * regressed, the failure on a real site would be a fatal error on a missing class rather than a
 * clean error envelope.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.36
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\LiteSpeed\LiteSpeed_Guard;
use WP_Error;
use WP_UnitTestCase;

class Test_LiteSpeed_Guard extends WP_UnitTestCase {

	/**
	 * The premise every other test here rests on. If LiteSpeed ever IS loaded in the test
	 * environment, the assertions below would silently invert, so state the premise explicitly.
	 */
	public function test_litespeed_is_absent_from_the_test_environment(): void {
		$this->assertFalse(
			class_exists( '\LiteSpeed\Core' ),
			'LiteSpeed is loaded in the test environment; the unavailable-path assertions below no longer test what they claim.'
		);
	}

	public function test_is_available_is_false_without_the_host(): void {
		$this->assertFalse( LiteSpeed_Guard::is_available() );
	}

	public function test_assert_available_returns_a_typed_error(): void {
		$result = LiteSpeed_Guard::assert_available();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'litespeed_missing', $result->get_error_code() );
		$this->assertNotSame( '', (string) $result->get_error_message() );
	}

	/**
	 * The generic confirmation message, and the per-operation override.
	 */
	public function test_assert_confirmed_gates_on_the_flag(): void {
		$missing = LiteSpeed_Guard::assert_confirmed( array() );

		$this->assertInstanceOf( WP_Error::class, $missing );
		$this->assertSame( 'confirmation_required', $missing->get_error_code() );

		$this->assertTrue( LiteSpeed_Guard::assert_confirmed( array( 'confirm' => true ) ) );

		$custom = LiteSpeed_Guard::assert_confirmed( array(), 'Operation-specific warning.' );

		$this->assertInstanceOf( WP_Error::class, $custom );
		$this->assertSame( 'Operation-specific warning.', $custom->get_error_message() );
	}

	/**
	 * PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY — the filter may tighten access, never widen it.
	 */
	public function test_the_permission_callback_enforces_the_floor(): void {
		$callback = LiteSpeed_Guard::can( 'manage_options' );
		$original = $GLOBALS['acrossai_test_capabilities'] ?? array();

		$GLOBALS['acrossai_test_capabilities'] = array();
		$this->assertFalse( $callback(), 'Without manage_options the callback must deny.' );

		$GLOBALS['acrossai_test_capabilities'] = array( 'edit_posts' );
		$this->assertFalse( $callback(), 'A lesser capability must not satisfy the floor.' );

		$GLOBALS['acrossai_test_capabilities'] = array( 'manage_options' );
		$this->assertTrue( $callback(), 'manage_options must pass.' );

		// PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY: the filter may tighten, never widen. add_filter()
		// is a no-op in this bootstrap, so the filter is simulated through the fixture the stub's
		// apply_filters() consults.
		$GLOBALS['acrossai_test_filter_callbacks'][ LiteSpeed_Guard::PERMISSION_FILTER ] = static fn(): bool => false;
		$this->assertFalse( $callback(), 'The filter must be able to deny a capable user.' );

		unset( $GLOBALS['acrossai_test_filter_callbacks'][ LiteSpeed_Guard::PERMISSION_FILTER ] );
		$this->assertTrue( $callback(), 'Removing the filter must restore the floor result.' );

		$GLOBALS['acrossai_test_capabilities'] = $original;
	}

	/**
	 * The envelope shapes every ability returns through.
	 */
	public function test_the_success_envelope_cannot_be_spoofed(): void {
		$envelope = LiteSpeed_Guard::ok(
			array(
				'value'      => 1,
				'success'    => false,
				'error_code' => 'spoofed',
				'message'    => 'spoofed',
			),
			'Real message.'
		);

		$this->assertTrue( $envelope['success'] );
		$this->assertSame( 'Real message.', $envelope['message'] );
		$this->assertSame( 1, $envelope['value'] );
		$this->assertArrayNotHasKey( 'error_code', $envelope, 'A payload must not be able to inject an error code into a success.' );
	}

	public function test_the_failure_envelope_carries_the_code(): void {
		$envelope = LiteSpeed_Guard::fail( new WP_Error( 'some_code', 'Some message.' ) );

		$this->assertFalse( $envelope['success'] );
		$this->assertSame( 'some_code', $envelope['error_code'] );
		$this->assertSame( 'Some message.', $envelope['message'] );
	}

	/**
	 * Every ability runs the availability check FIRST, so on a site where LiteSpeed was deactivated
	 * mid-request the caller gets litespeed_missing rather than a fatal on a missing class.
	 */
	public function test_an_ability_declines_cleanly_when_the_host_is_absent(): void {
		$ability = new \AcrossAI_Abilities_Manager\Includes\Abilities\LiteSpeed\Get_Cache_Status();
		$result  = $ability->execute( array() );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'litespeed_missing', $result['error_code'] );
	}

	/**
	 * And the confirm-gated ones decline on availability BEFORE confirmation — the guard order is
	 * structural, not incidental.
	 */
	public function test_availability_is_checked_before_confirmation(): void {
		$ability = new \AcrossAI_Abilities_Manager\Includes\Abilities\LiteSpeed\Apply_Preset();
		$result  = $ability->execute( array( 'preset' => 'essentials' ) );

		$this->assertSame(
			'litespeed_missing',
			$result['error_code'],
			'An unconfirmed call with LiteSpeed absent must report the missing host, not confirmation_required.'
		);
	}

	/**
	 * The category is not advertised on a site without LiteSpeed.
	 */
	public function test_the_category_registrar_registers_nothing_without_the_host(): void {
		\AcrossAI_Abilities_Manager\Includes\Abilities\LiteSpeed\Category_Registrar::instance()->register();

		$this->assertFalse(
			function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( 'acrossai-litespeed-cache' ),
			'The LiteSpeed category must not register when LiteSpeed is absent.'
		);
	}
}
