<?php
/**
 * Structural tests for Feature 064 plugins/verify-plugin-checksums.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.23
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Verify_Plugin_Checksums.
 */
class Test_Verify_Plugin_Checksums extends WP_UnitTestCase {

	/**
	 * The Verify_Plugin_Checksums source, loaded once per test.
	 *
	 * @var string
	 */
	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Plugins/Verify_Plugin_Checksums.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'plugins/verify-plugin-checksums'", $this->src );
		$this->assertStringContainsString( "'acrossai-plugins'", $this->src );
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

	public function test_uses_plugin_helpers_resolve_plugin(): void {
		$this->assertStringContainsString( 'Plugin_Helpers::resolve_plugin(', $this->src );
	}

	public function test_fetches_manifest_via_wp_remote_get(): void {
		$this->assertStringContainsString( 'wp_remote_get(', $this->src );
		$this->assertStringContainsString( 'api.wordpress.org/plugins/checksums/1.0/', $this->src );
	}

	public function test_hashes_via_md5_file(): void {
		$this->assertStringContainsString( 'md5_file(', $this->src );
	}

	public function test_compares_hashes_via_hash_equals(): void {
		$this->assertStringContainsString( 'hash_equals(', $this->src );
	}

	public function test_returns_no_manifest_message_on_failure(): void {
		$this->assertStringContainsString( "'no_manifest'", $this->src );
	}

	public function test_status_enum_ok_modified_missing_added(): void {
		$this->assertStringContainsString( "'ok'", $this->src );
		$this->assertStringContainsString( "'modified'", $this->src );
		$this->assertStringContainsString( "'missing'", $this->src );
		$this->assertStringContainsString( "'added'", $this->src );
	}

	public function test_strict_flag_walks_disk_for_added_files(): void {
		$this->assertStringContainsString( '$strict', $this->src );
	}

	public function test_sanitizes_plugin_identifier(): void {
		$this->assertStringContainsString( 'sanitize_text_field(', $this->src );
	}

	public function test_summary_includes_five_counters(): void {
		$this->assertStringContainsString( "'total'", $this->src );
		$this->assertStringContainsString( "'ok'", $this->src );
		$this->assertStringContainsString( "'modified'", $this->src );
		$this->assertStringContainsString( "'missing'", $this->src );
		$this->assertStringContainsString( "'added'", $this->src );
	}
}
