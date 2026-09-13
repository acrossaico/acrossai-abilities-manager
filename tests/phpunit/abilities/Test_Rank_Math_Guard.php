<?php
/**
 * Feature 069 — tests for Rank_Math_Guard.
 *
 * The harness (tests/bootstrap.php) provides stubs, not a live WordPress:
 * current_user_can() always returns false and add_filter() is a no-op. So
 * capability composition and the permission filter are asserted by source
 * inspection, while the pure-logic paths — envelope construction, the
 * confirmation gate, and the absent-Rank-Math guard — are exercised for real.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.28
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath\Rank_Math_Guard;
use WP_Error;
use WP_UnitTestCase;

class Test_Rank_Math_Guard extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/RankMath/Rank_Math_Guard.php'
		);
	}

	// -----------------------------------------------------------------
	// Behavioural — pure logic, works under the stub harness.
	// -----------------------------------------------------------------

	/**
	 * FR-004 / SC-005 — must fail cleanly, not fatally, when Rank Math is absent.
	 * CI never has Rank Math installed, so this is the default path here.
	 */
	public function test_assert_available_fails_cleanly_without_rank_math(): void {
		if ( class_exists( '\RankMath\Helper' ) ) {
			$this->markTestSkipped( 'Rank Math is installed; this asserts the absent-plugin path.' );
		}
		$result = Rank_Math_Guard::assert_available();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rank_math_missing', $result->get_error_code() );
	}

	/**
	 * An empty module means "no module requirement" and must short-circuit before
	 * touching Rank Math at all.
	 */
	public function test_assert_module_short_circuits_on_empty_module(): void {
		$this->assertTrue( Rank_Math_Guard::assert_module( '' ) );
	}

	public function test_assert_module_fails_cleanly_without_rank_math(): void {
		if ( class_exists( '\RankMath\Helper' ) ) {
			$this->markTestSkipped( 'Rank Math is installed; this asserts the absent-plugin path.' );
		}
		$result = Rank_Math_Guard::assert_module( 'redirections' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rank_math_missing', $result->get_error_code() );
	}

	public function test_assert_pro_fails_without_pro(): void {
		if ( defined( 'RANK_MATH_PRO_VERSION' ) || class_exists( '\RankMathPro\Plugin' ) ) {
			$this->markTestSkipped( 'Rank Math PRO is installed.' );
		}
		$result = Rank_Math_Guard::assert_pro();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rank_math_pro_required', $result->get_error_code() );
	}

	/**
	 * FR-009 — the message must name the flag so a client can retry without
	 * guessing.
	 */
	public function test_assert_confirmed_requires_the_flag(): void {
		$missing = Rank_Math_Guard::assert_confirmed( array() );
		$this->assertInstanceOf( WP_Error::class, $missing );
		$this->assertSame( 'confirmation_required', $missing->get_error_code() );
		$this->assertStringContainsString( 'confirm: true', $missing->get_error_message() );

		$this->assertInstanceOf( WP_Error::class, Rank_Math_Guard::assert_confirmed( array( 'confirm' => false ) ) );
		$this->assertTrue( Rank_Math_Guard::assert_confirmed( array( 'confirm' => true ) ) );
	}

	/**
	 * An empty capability suffix means "no granular capability applies" and must
	 * not deny — this is the path used by the floor-only abilities.
	 */
	public function test_empty_cap_passes(): void {
		$this->assertTrue( Rank_Math_Guard::has_cap( '' ) );
	}

	public function test_ok_envelope_shape(): void {
		$envelope = Rank_Math_Guard::ok( array( 'panel' => 'status' ), 'Done.' );
		$this->assertTrue( $envelope['success'] );
		$this->assertSame( 'status', $envelope['panel'] );
		$this->assertSame( 'Done.', $envelope['message'] );
		$this->assertArrayNotHasKey( 'error_code', $envelope );
	}

	public function test_error_envelope_shape(): void {
		$envelope = Rank_Math_Guard::error( 'invalid_input', 'Bad panel.', array( 'panel' => 'nope' ) );
		$this->assertFalse( $envelope['success'] );
		$this->assertSame( 'invalid_input', $envelope['error_code'] );
		$this->assertSame( 'Bad panel.', $envelope['message'] );
		$this->assertSame( 'nope', $envelope['panel'] );
	}

	/**
	 * FR-004 — a raw WP_Error must never leave execute(); this is where it is
	 * unwrapped into the envelope.
	 */
	public function test_fail_unwraps_wp_error(): void {
		$envelope = Rank_Math_Guard::fail( new WP_Error( 'not_found', 'Missing.' ), array( 'id' => 7 ) );
		$this->assertFalse( $envelope['success'] );
		$this->assertSame( 'not_found', $envelope['error_code'] );
		$this->assertSame( 'Missing.', $envelope['message'] );
		$this->assertSame( 7, $envelope['id'] );
	}

	/**
	 * Context must not be able to overwrite the envelope's own keys.
	 */
	public function test_envelope_keys_win_over_context(): void {
		$envelope = Rank_Math_Guard::error( 'invalid_input', 'Real message.', array( 'message' => 'spoofed', 'success' => true ) );
		$this->assertFalse( $envelope['success'] );
		$this->assertSame( 'Real message.', $envelope['message'] );
	}

	// -----------------------------------------------------------------
	// Source inspection — the stub harness cannot exercise these.
	// -----------------------------------------------------------------

	public function test_is_static_only_utility(): void {
		$this->assertStringContainsString( 'final class Rank_Math_Guard', $this->src );
		$this->assertStringContainsString( 'private function __construct()', $this->src );
		$this->assertStringNotContainsString( 'public static function instance()', $this->src );
	}

	/**
	 * The '-' to '_' normalisation matters: the module id is '404-monitor' but the
	 * capability is 'rank_math_404_monitor'.
	 */
	public function test_has_cap_normalises_hyphens_and_prefixes(): void {
		$this->assertStringContainsString(
			"current_user_can( 'rank_math_' . str_replace( '-', '_', \$cap ) )",
			$this->src
		);
	}

	/**
	 * FR-012 — floor AND granular capability. AND is never looser than either
	 * model alone, and is what keeps Rank Math's Role Manager grants meaningful.
	 */
	public function test_can_composes_floor_and_granular_capability(): void {
		$this->assertMatchesRegularExpression(
			'/\$allowed\s*=\s*\$floor_ok\s*&&\s*\$cap_ok;/',
			$this->src
		);
		$this->assertStringContainsString( '$floor_ok = current_user_can( $floor );', $this->src );
		$this->assertStringContainsString( '$cap_ok   = self::has_cap( $rm_cap );', $this->src );
	}

	/**
	 * The widening filter is bounded below.
	 *
	 * This suite's filter is deliberately allowed to widen — that is how an editor holding
	 * rank_math_titles but not manage_options gets in, which is the documented purpose. Returning
	 * the filter's value unbounded, the shape this shipped with, also let it grant access to a user
	 * holding neither capability: any other plugin on the site could hand a subscriber an SEO
	 * write. The result is now floored at "clears the floor, or holds the granular capability this
	 * ability names".
	 *
	 * The `'' !== $rm_cap` term is load-bearing. has_cap() returns true when no granular capability
	 * applies, so without it the bound collapses to `true` for exactly the abilities that have
	 * nothing but the floor protecting them.
	 */
	public function test_widening_filter_cannot_admit_a_user_holding_neither_capability(): void {
		$this->assertMatchesRegularExpression(
			"/return \\\$filtered && \\( \\\$floor_ok \\|\\| \\( '' !== \\\$rm_cap && \\\$cap_ok \\) \\);/",
			$this->src,
			'can() must bound the filtered result below, and the empty-capability term must be present.'
		);
	}

	/**
	 * One filter must govern every ability in the suite, so it has to be applied
	 * inside can() rather than per-ability.
	 */
	public function test_permission_filter_is_applied_inside_can(): void {
		$this->assertStringContainsString( "PERMISSION_FILTER = 'acrossai_abilities_manager_rank_math_permission'", $this->src );
		$can_pos    = strpos( $this->src, 'public static function can(' );
		$filter_pos = strpos( $this->src, 'apply_filters( self::PERMISSION_FILTER' );
		$this->assertNotFalse( $can_pos );
		$this->assertNotFalse( $filter_pos );
		$this->assertLessThan( $filter_pos, $can_pos );
	}

	/**
	 * Research F7 — three distinct gate flavours, because Content AI and AI
	 * Visibility gate on account plus credits rather than on the PRO plugin.
	 */
	public function test_declares_three_distinct_entitlement_gates(): void {
		$this->assertStringContainsString( 'public static function assert_pro()', $this->src );
		$this->assertStringContainsString( 'public static function assert_account()', $this->src );
		$this->assertStringContainsString( 'public static function assert_credits(', $this->src );
		$this->assertStringContainsString( "'rank_math_account_required'", $this->src );
		$this->assertStringContainsString( "'content_ai_no_credits'", $this->src );
	}

	/**
	 * FR-014 — the credit check must precede any remote request, which means
	 * assert_credits() reads the balance locally and never calls out.
	 */
	public function test_credit_guard_reads_balance_locally(): void {
		$this->assertStringContainsString( '\RankMath\Helper::get_credits()', $this->src );
		$this->assertStringNotContainsString( 'wp_remote_', $this->src );
	}
}
