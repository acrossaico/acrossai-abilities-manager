<?php
/**
 * Structural tests for Feature 064 core/verify-core-checksums.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.23
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Verify_Core_Checksums.
 */
class Test_Verify_Core_Checksums extends WP_UnitTestCase {

	/**
	 * The Verify_Core_Checksums source, loaded once per test.
	 *
	 * @var string
	 */
	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Core/Verify_Core_Checksums.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'core/verify-core-checksums'", $this->src );
		$this->assertStringContainsString( "'acrossai-core'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_annotations_readonly_true(): void {
		$this->assertStringContainsString( "'readonly'    => true", $this->src );
		$this->assertStringContainsString( "'destructive' => false", $this->src );
	}

	public function test_uses_wp_core_get_core_checksums(): void {
		$this->assertStringContainsString( 'get_core_checksums(', $this->src );
	}

	public function test_requires_wp_admin_update_php(): void {
		$this->assertStringContainsString( "require_once ABSPATH . 'wp-admin/includes/update.php'", $this->src );
	}

	public function test_defaults_version_to_installed_and_locale_to_en_us(): void {
		$this->assertStringContainsString( "get_bloginfo( 'version' )", $this->src );
		$this->assertStringContainsString( "'en_US'", $this->src );
	}

	public function test_hashes_via_md5_file_and_hash_equals(): void {
		$this->assertStringContainsString( 'md5_file(', $this->src );
		$this->assertStringContainsString( 'hash_equals(', $this->src );
	}

	public function test_include_root_defaults_false(): void {
		$this->assertMatchesRegularExpression(
			"/'include_root'.*'default'\s*=>\s*false/s",
			$this->src
		);
	}

	public function test_accepts_exclude_and_strict_inputs(): void {
		$this->assertStringContainsString( "'exclude'", $this->src );
		$this->assertStringContainsString( "'strict'", $this->src );
	}

	public function test_status_enum_ok_modified_missing_added(): void {
		$this->assertStringContainsString( "'ok'", $this->src );
		$this->assertStringContainsString( "'modified'", $this->src );
		$this->assertStringContainsString( "'missing'", $this->src );
		$this->assertStringContainsString( "'added'", $this->src );
	}

	public function test_sanitizes_string_inputs(): void {
		$this->assertStringContainsString( 'sanitize_text_field(', $this->src );
	}
}
