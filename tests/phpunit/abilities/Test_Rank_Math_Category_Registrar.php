<?php
/**
 * Feature 069 — source-inspection tests for the Rank Math category registrar.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.28
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Rank_Math_Category_Registrar extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/RankMath/Category_Registrar.php'
		);
	}

	public function test_singleton_pattern(): void {
		$this->assertStringContainsString( 'public static function instance(): self', $this->src );
		$this->assertStringContainsString( 'private function __construct()', $this->src );
	}

	public function test_registers_correct_category_slug(): void {
		$this->assertStringContainsString( "'acrossai-rank-math'", $this->src );
	}

	public function test_uses_wp_register_ability_category(): void {
		$this->assertStringContainsString( 'wp_register_ability_category(', $this->src );
	}

	/**
	 * FR-003 — the category must not be advertised on sites without Rank Math,
	 * and the guard must precede the registration call.
	 */
	public function test_guarded_on_rank_math_presence(): void {
		$this->assertStringContainsString( "class_exists( '\\RankMath\\Helper' )", $this->src );
		$guard_pos    = strpos( $this->src, "class_exists( '\\RankMath\\Helper' )" );
		$register_pos = strpos( $this->src, 'wp_register_ability_category(' );
		$this->assertNotFalse( $guard_pos );
		$this->assertNotFalse( $register_pos );
		$this->assertLessThan( $register_pos, $guard_pos );
	}
}
