<?php
/**
 * Source-inspection tests for Set_Site_Maintenance_Mode.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Set_Site_Maintenance_Mode extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/SiteHealth/Set_Site_Maintenance_Mode.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'site-health/set-site-maintenance-mode'", $this->src );
	}

	public function test_lives_in_site_health_category(): void {
		$this->assertStringContainsString( "'acrossai-site-health'", $this->src );
	}

	public function test_requires_confirm_flag(): void {
		$this->assertStringContainsString( "'required'             => array( 'confirm' )", $this->src );
		$this->assertStringContainsString( "'confirmation_required'", $this->src );
	}

	public function test_destructive_annotation(): void {
		$this->assertStringContainsString( "'destructive' => true", $this->src );
		$this->assertStringContainsString( "'idempotent' => false", $this->src );
	}

	public function test_duration_bounds(): void {
		$this->assertStringContainsString( "'minimum' => 1", $this->src );
		$this->assertStringContainsString( 'MAX_MINUTES      = 1440', $this->src );
		$this->assertStringContainsString( 'DEFAULT_MINUTES  = 60', $this->src );
	}

	public function test_writes_wp_core_marker(): void {
		$this->assertStringContainsString( "ABSPATH . '.maintenance'", $this->src );
		$this->assertStringContainsString( "'<?php \$upgrading = '", $this->src );
	}

	public function test_registers_5_minute_refresh_schedule(): void {
		$this->assertStringContainsString( 'REFRESH_INTERVAL = 300', $this->src );
		$this->assertStringContainsString( "'acrossai_five_minutes'", $this->src );
		$this->assertStringContainsString( 'register_cron_schedule', $this->src );
	}

	public function test_refresh_marker_self_cleans_after_expiry(): void {
		$this->assertStringContainsString( 'public static function refresh_marker(): void', $this->src );
		$this->assertStringContainsString( '$now >= $expires_at', $this->src );
		$this->assertStringContainsString( '@unlink( $marker )', $this->src );
		$this->assertStringContainsString( 'wp_clear_scheduled_hook( self::CRON_HOOK )', $this->src );
	}

	public function test_requires_manage_options(): void {
		$this->assertStringContainsString( "current_user_can( 'manage_options' )", $this->src );
	}

	public function test_persists_expiry_to_option(): void {
		$this->assertStringContainsString( "EXPIRY_OPTION    = 'acrossai_maintenance_expires_at'", $this->src );
		$this->assertStringContainsString( 'update_option( self::EXPIRY_OPTION', $this->src );
	}
}
