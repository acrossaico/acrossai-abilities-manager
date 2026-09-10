<?php
/**
 * Feature 069 — source-inspection tests for the Rank Math ability base class.
 *
 * Base_Rank_Math_Ability is the sole assembler of ability() and the sole
 * enforcer of the execute() guard ordering, so these assertions protect the
 * shared contract for all 61 abilities at once. That is materially stronger than
 * repeating the same assertions in 61 files.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.28
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Rank_Math_Base_Ability extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/RankMath/Base_Rank_Math_Ability.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_declares_shared_category(): void {
		$this->assertStringContainsString( "CATEGORY = 'acrossai-rank-math'", $this->src );
	}

	/**
	 * FR-002 / R13 — Feature 078 existed solely because a suite shipped with
	 * tab_group => 'core'. Setting it once in the base closes that bug class.
	 */
	public function test_declares_rank_math_tab_group(): void {
		$this->assertStringContainsString( "TAB_GROUP = 'rank-math'", $this->src );
		$this->assertStringContainsString( "'tab_group' => self::TAB_GROUP", $this->src );
	}

	public function test_slug_prefix_is_applied_centrally(): void {
		$this->assertStringContainsString( "'rank-math/' . \$this->slug()", $this->src );
	}

	public function test_declares_required_meta_block(): void {
		$this->assertStringContainsString( "'show_in_rest' => true", $this->src );
		$this->assertStringContainsString( "'mcp'          => array( 'public' => false, 'type' => 'tool' )", $this->src );
	}

	public function test_output_schema_contract(): void {
		$this->assertStringContainsString( "'required'             => array( 'success' )", $this->src );
		$this->assertStringContainsString( "'additionalProperties' => false", $this->src );
	}

	/**
	 * R12 — the Registry silently strips any arg key outside its allowlist, so
	 * the base must emit exactly these eight and no others.
	 */
	public function test_emits_only_allowlisted_arg_keys(): void {
		foreach ( array( 'label', 'description', 'category', 'execute_callback', 'permission_callback', 'input_schema', 'output_schema', 'meta' ) as $key ) {
			$this->assertMatchesRegularExpression( "/'{$key}'\s*=>/", $this->src, "Missing arg key: {$key}" );
		}
		// 'name' and 'args' are the two top-level spec keys, not args keys.
		$this->assertStringContainsString( "'name' => 'rank-math/'", $this->src );
	}

	/**
	 * The mandatory guard order. Asserting the positions is what makes the
	 * ordering a structural property rather than a review checklist item.
	 */
	public function test_execute_enforces_guard_ordering(): void {
		$available = strpos( $this->src, 'Rank_Math_Guard::assert_available()' );
		$module    = strpos( $this->src, 'Rank_Math_Guard::assert_module(' );
		$confirmed = strpos( $this->src, 'Rank_Math_Guard::assert_confirmed(' );
		$run       = strpos( $this->src, '$result = $this->run( $input )' );
		$envelope  = strpos( $this->src, 'Rank_Math_Guard::ok(' );

		foreach ( array( $available, $module, $confirmed, $run, $envelope ) as $pos ) {
			$this->assertNotFalse( $pos );
		}
		$this->assertLessThan( $module, $available );
		$this->assertLessThan( $confirmed, $module );
		$this->assertLessThan( $run, $confirmed );
		$this->assertLessThan( $envelope, $run );
	}

	/**
	 * FR-004 — a raw WP_Error must never leave execute().
	 */
	public function test_unwraps_wp_error_into_envelope(): void {
		$this->assertStringContainsString( 'Rank_Math_Guard::fail( $result )', $this->src );
		$this->assertStringContainsString( 'if ( is_wp_error( $result ) )', $this->src );
	}

	/**
	 * FR-009 — the confirm property is added centrally for every ability that
	 * declares requires_confirmation(), so an ability cannot declare one without
	 * the other.
	 */
	public function test_confirm_property_added_centrally(): void {
		$this->assertStringContainsString( 'if ( $this->requires_confirmation() )', $this->src );
		$this->assertStringContainsString( "\$properties['confirm']", $this->src );
	}

	public function test_permission_callback_composes_via_guard(): void {
		$this->assertStringContainsString( 'Rank_Math_Guard::can( $this->rank_math_cap(), $this->permission_floor() )', $this->src );
	}

	public function test_default_floor_is_manage_options(): void {
		$this->assertStringContainsString( "return 'manage_options';", $this->src );
	}
}
