<?php
/**
 * Structural tests for Feature 064 plugins/uninstall-plugin.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.23
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Uninstall_Plugin.
 */
class Test_Uninstall_Plugin extends WP_UnitTestCase {

	/**
	 * The Uninstall_Plugin source, loaded once per test.
	 *
	 * @var string
	 */
	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Plugins/Uninstall_Plugin.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'plugins/uninstall-plugin'", $this->src );
		$this->assertStringContainsString( "'acrossai-plugins'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_annotations_destructive_true(): void {
		$this->assertStringContainsString( "'destructive' => true", $this->src );
		$this->assertStringContainsString( "'idempotent'  => true", $this->src );
	}

	public function test_uses_file_mods_guard_install_context(): void {
		$this->assertMatchesRegularExpression(
			"/File_Mods_Guard::blocked_response\s*\(\s*'install'\s*\)/",
			$this->src
		);
	}

	public function test_uses_plugin_helpers_resolve_plugin(): void {
		$this->assertStringContainsString( 'Plugin_Helpers::resolve_plugin(', $this->src );
	}

	public function test_uses_is_plugin_active_guard(): void {
		$this->assertStringContainsString( 'is_plugin_active(', $this->src );
	}

	public function test_calls_wp_core_uninstall_plugin(): void {
		$this->assertStringContainsString( 'uninstall_plugin(', $this->src );
	}

	public function test_declares_all_three_blocked_reasons(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'file_mods_disallowed'", $this->src );
		$this->assertStringContainsString( "'blocked_reason' => 'plugin_not_found'", $this->src );
		$this->assertStringContainsString( "'blocked_reason' => 'plugin_active'", $this->src );
	}

	public function test_guard_order_matches_plan(): void {
		// file_mods_disallowed -> plugin_not_found -> plugin_active.
		$pos_fm     = strpos( $this->src, "'blocked_reason' => 'file_mods_disallowed'" );
		$pos_nf     = strpos( $this->src, "'blocked_reason' => 'plugin_not_found'" );
		$pos_active = strpos( $this->src, "'blocked_reason' => 'plugin_active'" );

		$this->assertNotFalse( $pos_fm );
		$this->assertNotFalse( $pos_nf );
		$this->assertNotFalse( $pos_active );

		$this->assertLessThan( $pos_nf, $pos_fm, 'file_mods_disallowed guard must appear before plugin_not_found.' );
		$this->assertLessThan( $pos_active, $pos_nf, 'plugin_not_found guard must appear before plugin_active.' );
	}

	public function test_requires_wp_admin_plugin_php_before_uninstall(): void {
		$this->assertStringContainsString( "require_once ABSPATH . 'wp-admin/includes/plugin.php'", $this->src );
	}

	public function test_input_schema_requires_plugin(): void {
		$this->assertStringContainsString( "'required'             => array( 'plugin' )", $this->src );
	}

	public function test_sanitizes_plugin_identifier(): void {
		$this->assertStringContainsString( 'sanitize_text_field(', $this->src );
	}
}
