<?php
/**
 * Feature 107 — Classic_Editor_Guard.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.39
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\ClassicEditor\Classic_Editor_Guard;
use WP_Error;
use WP_UnitTestCase;

class Test_Classic_Editor_Guard extends WP_UnitTestCase {

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
	 * Availability needs both the constant and the class.
	 *
	 * The plugin wraps its class declaration in `if ( ! class_exists( 'Classic_Editor' ) )`, so
	 * another plugin can occupy the name; the class alone would not prove this is the real one.
	 */
	public function test_availability_requires_both_the_constant_and_the_class(): void {
		$expected = defined( 'CLASSIC_EDITOR_VERSION' ) && class_exists( 'Classic_Editor' );

		$this->assertSame( $expected, Classic_Editor_Guard::is_available() );

		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/ClassicEditor/Classic_Editor_Guard.php'
		);

		$this->assertStringContainsString( "defined( 'CLASSIC_EDITOR_VERSION' )", $src );
		$this->assertStringContainsString( "class_exists( 'Classic_Editor' )", $src );
	}

	public function test_assert_available_names_the_plugin_when_it_is_missing(): void {
		$result = Classic_Editor_Guard::assert_available();

		if ( Classic_Editor_Guard::is_available() ) {
			$this->assertTrue( $result );
			return;
		}

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'classic_editor_missing', (string) $result->get_error_code() );
	}

	public function test_assert_confirmed_blocks_until_confirm_is_true(): void {
		$this->assertInstanceOf( WP_Error::class, Classic_Editor_Guard::assert_confirmed( array() ) );
		$this->assertInstanceOf( WP_Error::class, Classic_Editor_Guard::assert_confirmed( array( 'confirm' => false ) ) );
		$this->assertTrue( Classic_Editor_Guard::assert_confirmed( array( 'confirm' => true ) ) );
	}

	public function test_the_confirmation_code_is_stable(): void {
		$result = Classic_Editor_Guard::assert_confirmed( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame(
			'confirmation_required',
			(string) $result->get_error_code(),
			'Clients branch on this code; renaming it is a breaking change.'
		);
	}

	public function test_assert_confirmed_uses_the_supplied_message(): void {
		$result = Classic_Editor_Guard::assert_confirmed( array(), 'This changes every author.' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertStringContainsString( 'This changes every author.', (string) $result->get_error_message() );
	}

	public function test_the_floor_admits_an_administrator_and_refuses_anyone_else(): void {
		$this->assertTrue(
			$this->with_capabilities( array( 'manage_options' ), static fn (): bool => (bool) call_user_func( Classic_Editor_Guard::can() ) )
		);
		$this->assertFalse(
			$this->with_capabilities( array( 'edit_posts' ), static fn (): bool => (bool) call_user_func( Classic_Editor_Guard::can() ) )
		);
		$this->assertFalse(
			$this->with_capabilities( array(), static fn (): bool => (bool) call_user_func( Classic_Editor_Guard::can() ) )
		);
	}

	public function test_a_raised_floor_is_honoured(): void {
		$this->assertFalse(
			$this->with_capabilities(
				array( 'manage_options' ),
				static fn (): bool => (bool) call_user_func( Classic_Editor_Guard::can( 'manage_network' ) )
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
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/ClassicEditor/Classic_Editor_Guard.php'
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
		$envelope = Classic_Editor_Guard::ok(
			array(
				'editor'     => 'classic',
				'success'    => false,
				'error_code' => 'spoofed',
			),
			'Real message.'
		);

		$this->assertTrue( $envelope['success'] );
		$this->assertArrayNotHasKey( 'error_code', $envelope );
		$this->assertSame( 'Real message.', $envelope['message'] );
		$this->assertSame( 'classic', $envelope['editor'] );
	}

	public function test_the_failure_envelope_preserves_code_and_message(): void {
		$envelope = Classic_Editor_Guard::fail( new WP_Error( 'invalid_editor', 'Not a valid editor.' ) );

		$this->assertFalse( $envelope['success'] );
		$this->assertSame( 'invalid_editor', $envelope['error_code'] );
		$this->assertStringContainsString( 'Not a valid editor.', $envelope['message'] );
	}
}
