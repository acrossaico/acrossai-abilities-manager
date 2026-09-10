<?php
/**
 * Structural tests for the Feature 063 cron/test-wp-cron ability.
 *
 * Verifies the class fires a non-blocking wp_remote_get() with a tiny
 * timeout at site_url('wp-cron.php?doing_wp_cron') and surfaces the
 * DISABLE_WP_CRON constant state.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Test_Wp_Cron.
 */
class Test_Test_Wp_Cron extends WP_UnitTestCase {

	/** @var string */
	private string $src = '';

	/** @var string */
	private string $bootstrap = '';

	/**
	 * Load the ability source once per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$plugin_root     = dirname( __DIR__, 3 );
		$this->src       = (string) file_get_contents( $plugin_root . '/includes/Abilities/Cron/Test_Wp_Cron.php' );
		$this->bootstrap = (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );
	}

	public function test_extends_ability_definition_and_uses_expected_ability_name(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
		$this->assertStringContainsString( "'cron/test-wp-cron'", $this->src );
	}

	public function test_targets_the_cron_category(): void {
		$this->assertStringContainsString( "'acrossai-cron'", $this->src );
	}

	public function test_permission_callback_gates_on_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\\(\\s*'manage_options'\\s*\\)/",
			$this->src
		);
	}

	public function test_declares_readonly_idempotent_non_destructive_annotations(): void {
		$this->assertStringContainsString( "'readonly'    => true", $this->src );
		$this->assertStringContainsString( "'idempotent'  => true", $this->src );
		$this->assertStringContainsString( "'destructive' => false", $this->src );
	}

	public function test_uses_wp_remote_get_non_blocking_with_tiny_timeout(): void {
		$this->assertStringContainsString( 'wp_remote_get(', $this->src );
		$this->assertStringContainsString( "site_url( 'wp-cron.php?doing_wp_cron'", $this->src );
		$this->assertStringContainsString( "'blocking' => false", $this->src );
		$this->assertStringContainsString( "'timeout'  => 0.01", $this->src );
	}

	public function test_reports_reachability_via_is_wp_error(): void {
		$this->assertStringContainsString( '! is_wp_error( $response )', $this->src );
	}

	public function test_reports_disable_wp_cron_constant_state(): void {
		$this->assertStringContainsString( "defined( 'DISABLE_WP_CRON' )", $this->src );
		$this->assertStringContainsString( 'DISABLE_WP_CRON', $this->src );
	}

	public function test_bootstrap_instantiates_the_ability(): void {
		$this->assertStringContainsString( 'new Cron\\Test_Wp_Cron()', $this->bootstrap );
	}
}
