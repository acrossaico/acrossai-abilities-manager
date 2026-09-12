<?php
/**
 * Feature 105 — the ACF guard, and the edition path live testing cannot reach.
 *
 * A site runs one ACF edition at a time — Pro REPLACES free rather than extending it, and WordPress
 * auto-deactivates whichever was there first. So a development site can only ever exercise one half
 * of the gating live. The PHPUnit bootstrap loads no plugins at all, which makes this the natural
 * place to test the ACF-absent path, and the fixture below simulates the free edition for the rest.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.37
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Acf\Acf_Guard;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Acf\Acf_Target;
use WP_Error;
use WP_UnitTestCase;

class Test_Acf_Guard extends WP_UnitTestCase {

	/**
	 * The premise the rest of the file rests on. If ACF is ever loaded here, the assertions below
	 * silently invert rather than fail, so state it explicitly.
	 */
	public function test_acf_is_absent_from_the_test_environment(): void {
		$this->assertFalse(
			defined( 'ACF_VERSION' ),
			'ACF is loaded in the test environment; the absent-path assertions below no longer test what they claim.'
		);
	}

	public function test_is_available_is_false_without_acf(): void {
		$this->assertFalse( Acf_Guard::is_available() );
	}

	/**
	 * is_pro() must be false — and must NOT fatal — when ACF is absent entirely.
	 *
	 * It calls acf_is_pro(), which does not exist without ACF. The guard checks availability first
	 * for exactly that reason.
	 */
	public function test_is_pro_is_false_and_safe_without_acf(): void {
		$this->assertFalse( Acf_Guard::is_pro() );
	}

	public function test_has_field_type_is_false_and_safe_without_acf(): void {
		$this->assertFalse( Acf_Guard::has_field_type( 'repeater' ) );
		$this->assertFalse( Acf_Guard::has_blocks() );
	}

	public function test_assert_available_returns_a_typed_error(): void {
		$result = Acf_Guard::assert_available();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'acf_missing', $result->get_error_code() );
	}

	public function test_assert_pro_names_the_feature(): void {
		$result = Acf_Guard::assert_pro( 'Add Repeater Row' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'acf_pro_required', $result->get_error_code() );
		$this->assertStringContainsString( 'Add Repeater Row', $result->get_error_message() );
	}

	/**
	 * The field-type error must say the type is a PRO type, not merely that it is missing.
	 *
	 * An operator on free ACF who reads "repeater is not available" and nothing more has no idea
	 * whether that is a bug or a licensing boundary.
	 */
	public function test_assert_field_type_explains_the_edition_boundary(): void {
		$result = Acf_Guard::assert_field_type( 'repeater' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'acf_field_type_unavailable', $result->get_error_code() );
		$this->assertStringContainsString( 'PRO', $result->get_error_message() );
	}

	public function test_assert_confirmed_gates_on_the_flag(): void {
		$missing = Acf_Guard::assert_confirmed( array() );

		$this->assertInstanceOf( WP_Error::class, $missing );
		$this->assertSame( 'confirmation_required', $missing->get_error_code() );
		$this->assertTrue( Acf_Guard::assert_confirmed( array( 'confirm' => true ) ) );
	}

	/**
	 * PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY.
	 */
	public function test_the_permission_callback_enforces_the_floor(): void {
		$callback = Acf_Guard::can( 'manage_options' );
		$original = $GLOBALS['acrossai_test_capabilities'] ?? array();

		$GLOBALS['acrossai_test_capabilities'] = array( 'edit_posts' );
		$this->assertFalse( $callback(), 'A lesser capability must not satisfy the floor.' );

		$GLOBALS['acrossai_test_capabilities'] = array( 'manage_options' );
		$this->assertTrue( $callback() );

		$GLOBALS['acrossai_test_filter_callbacks'][ Acf_Guard::PERMISSION_FILTER ] = static fn(): bool => false;
		$this->assertFalse( $callback(), 'The filter must be able to deny a capable user.' );
		unset( $GLOBALS['acrossai_test_filter_callbacks'][ Acf_Guard::PERMISSION_FILTER ] );

		$GLOBALS['acrossai_test_capabilities'] = $original;
	}

	public function test_the_success_envelope_cannot_be_spoofed(): void {
		$envelope = Acf_Guard::ok(
			array(
				'value'      => 1,
				'success'    => false,
				'error_code' => 'spoofed',
			),
			'Real message.'
		);

		$this->assertTrue( $envelope['success'] );
		$this->assertSame( 'Real message.', $envelope['message'] );
		$this->assertArrayNotHasKey( 'error_code', $envelope );
	}

	/**
	 * Every ability declines cleanly with ACF absent rather than fatalling on a missing function.
	 */
	public function test_abilities_decline_cleanly_when_acf_is_absent(): void {
		$free = new \AcrossAI_Abilities_Manager\Includes\Abilities\Acf\Get_Acf_Field();
		$pro  = new \AcrossAI_Abilities_Manager\Includes\Abilities\Acf\Add_Acf_Repeater_Row();

		foreach ( array( $free, $pro ) as $ability ) {
			$result = $ability->execute( array( 'selector' => 'x', 'target_id' => 1 ) );

			$this->assertFalse( $result['success'] );
			$this->assertSame(
				'acf_missing',
				$result['error_code'],
				'With ACF absent, availability must be reported before anything else.'
			);
		}
	}

	/**
	 * Availability is checked BEFORE the Pro gate and before confirmation — the order is structural.
	 */
	public function test_availability_is_checked_before_the_pro_gate(): void {
		$ability = new \AcrossAI_Abilities_Manager\Includes\Abilities\Acf\Remove_Acf_Repeater_Row();
		$result  = $ability->execute( array( 'selector' => 'x', 'index' => 1, 'target_id' => 1 ) );

		$this->assertSame(
			'acf_missing',
			$result['error_code'],
			'An unconfirmed Pro-only call with ACF absent must report the missing plugin, not acf_pro_required or confirmation_required.'
		);
	}

	public function test_the_category_registrar_registers_nothing_without_acf(): void {
		\AcrossAI_Abilities_Manager\Includes\Abilities\Acf\Category_Registrar::instance()->register();

		$this->assertFalse(
			function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( 'acrossai-acf' ),
			'The ACF category must not register when ACF is absent.'
		);
	}

	/**
	 * The target resolver rejects an unknown kind and names the valid ones.
	 */
	public function test_target_resolver_rejects_an_unknown_type(): void {
		$result = Acf_Target::resolve( array( 'target_type' => 'nope' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'unknown_target_type', $result->get_error_code() );
		$this->assertStringContainsString( 'comment', $result->get_error_message(), 'The error should list the valid kinds.' );
	}

	/**
	 * The options store is the one target with no id, and must resolve without one.
	 */
	public function test_target_resolver_handles_the_options_store(): void {
		$this->assertSame( 'option', Acf_Target::resolve( array( 'target_type' => 'option' ) ) );
		$this->assertSame( 'my-options', Acf_Target::resolve( array( 'target_type' => 'option', 'target_id' => 'my-options' ) ) );
	}

	/**
	 * Every other target requires a positive id, so a caller cannot address "post 0".
	 */
	public function test_target_resolver_requires_an_id_for_everything_else(): void {
		foreach ( array( 'post', 'user', 'term', 'comment' ) as $type ) {
			$result = Acf_Target::resolve( array( 'target_type' => $type ) );

			$this->assertInstanceOf( WP_Error::class, $result, "{$type} must require an id." );
			$this->assertSame( 'invalid_input', $result->get_error_code() );
		}
	}

	/**
	 * The schema fragment every field and row ability shares must offer all five kinds.
	 */
	public function test_the_shared_target_schema_offers_every_kind(): void {
		$fragment = Acf_Target::schema_fragment();

		$this->assertSame(
			array( 'post', 'user', 'term', 'comment', 'option' ),
			$fragment['target_type']['enum']
		);
		$this->assertSame( 'post', $fragment['target_type']['default'] );
	}
}
