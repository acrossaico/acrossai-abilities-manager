<?php
/**
 * Feature 069 — source-inspection tests for the Rank Math bootstrap wiring.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.28
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Rank_Math_Bootstrap_Wiring extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
		);
	}

	public function test_registers_rank_math_category_callback(): void {
		$this->assertStringContainsString(
			"\$loader->add_action( 'wp_abilities_api_categories_init', RankMath\\Category_Registrar::instance(), 'register' );",
			$this->src
		);
	}

	/**
	 * FR-003 — no ability class may be instantiated when Rank Math is absent.
	 *
	 * The host check now shares its `if` with the Integrations switch, which can also stop the
	 * suite registering. Both halves are pinned: the switch must not have replaced the presence
	 * test, because an operator switching the integration on cannot conjure the plugin, and
	 * instantiating the suite without it is what FR-003 exists to prevent.
	 */
	public function test_ability_registration_is_gated_on_rank_math(): void {
		$this->assertStringContainsString(
			"if ( class_exists( '\\RankMath\\Helper' ) && \$this->integration_on( 'rank-math' ) ) {",
			$this->src
		);
		$this->assertStringContainsString( '$this->register_rank_math_abilities();', $this->src );
	}

	public function test_declares_the_registration_method(): void {
		$this->assertStringContainsString( 'private function register_rank_math_abilities(): void', $this->src );
	}

	/**
	 * The entitlement-gating divergence from register_elementor_pro_abilities()
	 * is deliberate. Without this comment a future maintainer will wrap the
	 * Content AI / AI Visibility abilities in a second gate and break FR-013, so
	 * the comment is load-bearing and asserted.
	 */
	public function test_documents_the_deliberate_entitlement_divergence(): void {
		$this->assertStringContainsString( 'DELIBERATE DIVERGENCE', $this->src );
		$this->assertStringContainsString( 'registered UNCONDITIONALLY', $this->src );
		$this->assertStringContainsString( 'specs/069-rank-math-abilities/research.md F7', $this->src );
	}

	/**
	 * There must be no second, entitlement-gated registration method mirroring
	 * the Elementor Pro shape.
	 */
	public function test_no_entitlement_gated_registration_method(): void {
		$this->assertStringNotContainsString( 'register_rank_math_pro_abilities', $this->src );
		$this->assertStringNotContainsString( "defined( 'RANK_MATH_PRO_VERSION' )", $this->src );
	}

	public function test_registers_batch_one_abilities(): void {
		$this->assertStringContainsString( 'new RankMath\\Get_Status();', $this->src );
	}
}
