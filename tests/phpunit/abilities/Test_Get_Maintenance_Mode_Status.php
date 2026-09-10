<?php
/**
 * Structural tests for the Feature 063 site-health/get-maintenance-mode-status ability.
 *
 * Source-inspection only — mirrors Test_Feature_042_Core_Update precedent.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Get_Maintenance_Mode_Status.
 */
class Test_Get_Maintenance_Mode_Status extends WP_UnitTestCase {

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
		$this->src       = (string) file_get_contents( $plugin_root . '/includes/Abilities/SiteHealth/Get_Maintenance_Mode_Status.php' );
		$this->bootstrap = (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );
	}

	public function test_extends_ability_definition_and_uses_expected_ability_name(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
		$this->assertStringContainsString( "'site-health/get-maintenance-mode-status'", $this->src );
	}

	public function test_targets_the_site_health_category(): void {
		$this->assertStringContainsString( "'acrossai-site-health'", $this->src );
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

	public function test_probes_the_maintenance_marker_at_abspath(): void {
		$this->assertStringContainsString( "ABSPATH . '.maintenance'", $this->src );
		$this->assertStringContainsString( 'file_exists(', $this->src );
	}

	public function test_uses_the_10_minute_stale_threshold(): void {
		$this->assertStringContainsString( 'STALE_AFTER_SECONDS = 600', $this->src );
	}

	public function test_reads_upgrading_via_scoped_include(): void {
		$this->assertMatchesRegularExpression(
			'/private static function read_upgrading_timestamp/',
			$this->src
		);
		$this->assertStringContainsString( 'include $marker;', $this->src );
	}

	public function test_bootstrap_instantiates_the_ability(): void {
		$this->assertStringContainsString( 'new SiteHealth\\Get_Maintenance_Mode_Status()', $this->bootstrap );
	}
}
