<?php
/**
 * Feature 106 — Yoast_Guard.
 *
 * Covers the paths a site with Yoast active cannot reach: the Yoast-absent branch, the
 * confirmation gate, the capability floor and its raise-only filter, and the envelope shapes every
 * ability returns through.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.38
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Yoast\Yoast_Guard;
use WP_Error;
use WP_UnitTestCase;

class Test_Yoast_Guard extends WP_UnitTestCase {

	public function test_is_available_requires_both_the_constant_and_the_class(): void {
		$expected = defined( 'WPSEO_VERSION' ) && class_exists( '\WPSEO_Options' );

		$this->assertSame( $expected, Yoast_Guard::is_available() );
	}

	/**
	 * The Yoast-absent branch, which cannot be reached on a site with Yoast active.
	 */
	public function test_assert_available_reports_a_named_error_when_yoast_is_missing(): void {
		if ( Yoast_Guard::is_available() ) {
			$this->assertTrue( Yoast_Guard::assert_available() );

			$src = (string) file_get_contents(
				dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Yoast/Yoast_Guard.php'
			);

			$this->assertMatchesRegularExpression(
				'/new WP_Error\(\s*\'[a-z_]+\'/',
				$src,
				'The unavailable branch must return a coded WP_Error, not a bare false.'
			);

			return;
		}

		$result = Yoast_Guard::assert_available();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertNotSame( '', (string) $result->get_error_code() );
	}

	public function test_assert_confirmed_blocks_until_confirm_is_true(): void {
		$this->assertInstanceOf( WP_Error::class, Yoast_Guard::assert_confirmed( array() ) );
		$this->assertInstanceOf( WP_Error::class, Yoast_Guard::assert_confirmed( array( 'confirm' => false ) ) );
		$this->assertTrue( Yoast_Guard::assert_confirmed( array( 'confirm' => true ) ) );
	}

	public function test_assert_confirmed_uses_the_supplied_message(): void {
		$result = Yoast_Guard::assert_confirmed( array(), 'This wipes the index.' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertStringContainsString( 'This wipes the index.', (string) $result->get_error_message() );
	}

	public function test_confirmation_error_code_is_stable(): void {
		$result = Yoast_Guard::assert_confirmed( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame(
			'confirmation_required',
			(string) $result->get_error_code(),
			'Clients branch on this code; renaming it is a breaking change.'
		);
	}

	/**
	 * Capabilities in this suite come from the stub bootstrap's global, not a user factory.
	 *
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

	public function test_permission_callback_is_callable(): void {
		$this->assertIsCallable( Yoast_Guard::can() );
	}

	public function test_permission_callback_allows_a_user_with_the_floor(): void {
		$this->assertTrue(
			$this->with_capabilities(
				array( 'manage_options' ),
				static fn (): bool => (bool) call_user_func( Yoast_Guard::can() )
			)
		);
	}

	public function test_permission_callback_denies_a_user_without_the_floor(): void {
		$this->assertFalse(
			$this->with_capabilities(
				array( 'edit_posts' ),
				static fn (): bool => (bool) call_user_func( Yoast_Guard::can() )
			)
		);
	}

	public function test_permission_callback_denies_a_user_with_no_capabilities(): void {
		$this->assertFalse(
			$this->with_capabilities(
				array(),
				static fn (): bool => (bool) call_user_func( Yoast_Guard::can() )
			)
		);
	}

	public function test_permission_callback_honours_a_raised_floor(): void {
		$this->assertFalse(
			$this->with_capabilities(
				array( 'manage_options' ),
				static fn (): bool => (bool) call_user_func( Yoast_Guard::can( 'manage_network' ) )
			)
		);
	}

	/**
	 * The permission filter may tighten access, never widen it.
	 *
	 * PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY. Returning the filter's value directly — the shape
	 * this guard shipped with — lets any plugin on the site hand an SEO write to a subscriber.
	 * Asserted structurally because the stub bootstrap's add_filter() is a no-op, so a behavioural
	 * test here would pass no matter which way round the code was.
	 */
	public function test_permission_filter_is_consulted_only_after_the_floor_is_cleared(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Yoast/Yoast_Guard.php'
		);

		$code = '';

		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			$code .= is_array( $token ) ? $token[1] : $token;
		}

		$this->assertMatchesRegularExpression(
			'/\$allowed\s*=\s*current_user_can\(\s*\$floor\s*\);\s*if\s*\(\s*!\s*\$allowed\s*\)\s*\{\s*return\s+false;\s*\}/',
			$code,
			'can() must return false before consulting the filter, so the filter can only ever deny.'
		);
	}

	public function test_ok_envelope_carries_success_payload_and_message(): void {
		$envelope = Yoast_Guard::ok( array( 'count' => 3 ), 'Three found.' );

		$this->assertTrue( $envelope['success'] );
		$this->assertSame( 3, $envelope['count'] );
		$this->assertSame( 'Three found.', $envelope['message'] );
	}

	public function test_fail_envelope_preserves_the_code_and_message(): void {
		$envelope = Yoast_Guard::fail( new WP_Error( 'setting_rejected', 'Yoast kept the old value.' ) );

		$this->assertFalse( $envelope['success'] );
		$this->assertSame( 'setting_rejected', $envelope['error_code'] );
		$this->assertStringContainsString( 'Yoast kept the old value.', $envelope['message'] );
	}

	/**
	 * has_indexables() reports; it must never gate.
	 *
	 * The indexables table is empty on every non-production install because Yoast refuses to build
	 * it there. If this became a gate, the whole suite would disappear on staging.
	 */
	public function test_has_indexables_is_a_report_not_a_gate(): void {
		$this->assertIsBool( Yoast_Guard::has_indexables() );

		$dir   = dirname( __DIR__, 3 ) . '/includes/Abilities/Yoast/';
		$files = glob( $dir . '*.php' );

		foreach ( is_array( $files ) ? $files : array() as $file ) {
			$src = (string) file_get_contents( $file );

			$this->assertDoesNotMatchRegularExpression(
				'/if\s*\(\s*!\s*(?:Yoast_Guard::)?has_indexables\(\s*\)\s*\)\s*\{\s*return\s+new\s+WP_Error/',
				$src,
				basename( $file ) . ' refuses to run when the indexables table is empty. Report the emptiness instead.'
			);
		}
	}
}
