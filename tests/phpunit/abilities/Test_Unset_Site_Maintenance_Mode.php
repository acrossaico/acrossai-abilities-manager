<?php
/**
 * Source-inspection tests for Unset_Site_Maintenance_Mode.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Unset_Site_Maintenance_Mode extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/SiteHealth/Unset_Site_Maintenance_Mode.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'site-health/unset-site-maintenance-mode'", $this->src );
	}

	public function test_lives_in_site_health_category(): void {
		$this->assertStringContainsString( "'acrossai-site-health'", $this->src );
	}

	public function test_takes_no_input(): void {
		$this->assertStringContainsString( "'properties'           => array()", $this->src );
	}

	public function test_idempotent_annotation(): void {
		$this->assertStringContainsString( "'idempotent' => true", $this->src );
		$this->assertStringContainsString( "'destructive' => false", $this->src );
	}

	public function test_removes_wp_core_marker(): void {
		$this->assertStringContainsString( "ABSPATH . '.maintenance'", $this->src );
		$this->assertStringContainsString( '@unlink( $marker )', $this->src );
	}

	public function test_reports_was_active(): void {
		$this->assertStringContainsString( "'was_active'", $this->src );
		$this->assertStringContainsString( '$was_active = file_exists( $marker )', $this->src );
	}

	public function test_clears_expiry_option_and_cron(): void {
		$this->assertStringContainsString( 'delete_option( Set_Site_Maintenance_Mode::EXPIRY_OPTION )', $this->src );
		$this->assertStringContainsString( 'wp_clear_scheduled_hook( Set_Site_Maintenance_Mode::CRON_HOOK )', $this->src );
	}

	public function test_requires_manage_options(): void {
		$this->assertStringContainsString( "current_user_can( 'manage_options' )", $this->src );
	}

	public function test_handles_delete_failure(): void {
		$this->assertStringContainsString( "'marker_delete_failed'", $this->src );
	}
}
