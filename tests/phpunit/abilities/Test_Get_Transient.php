<?php
/**
 * Structural tests for Feature 064 cache/get-transient.
 *
 * Source-inspection tests, mirroring the Feature 059 pattern — the plugin's
 * PHPUnit bootstrap is a minimal WP stub, not a full WP environment.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.23
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Get_Transient.
 */
class Test_Get_Transient extends WP_UnitTestCase {

	/**
	 * The Get_Transient source, loaded once per test.
	 *
	 * @var string
	 */
	private string $src = '';

	/**
	 * Load the source file.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Cache/Get_Transient.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'cache/get-transient'", $this->src );
		$this->assertStringContainsString( "'acrossai-cache'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_annotations_are_readonly_true(): void {
		$this->assertStringContainsString( "'readonly'    => true", $this->src );
		$this->assertStringContainsString( "'destructive' => false", $this->src );
		$this->assertStringContainsString( "'idempotent'  => true", $this->src );
	}

	public function test_distinguishes_unset_from_false_via_option_lookup(): void {
		$this->assertStringContainsString( 'get_option(', $this->src, 'Must consult raw option to distinguish unset from false-valued transient.' );
		$this->assertMatchesRegularExpression(
			"/null\s*!==\s*get_option\s*\(/",
			$this->src,
			'Existence check must compare get_option() result against null.'
		);
	}

	public function test_reads_via_core_transient_apis(): void {
		$this->assertStringContainsString( 'get_transient(', $this->src );
		$this->assertStringContainsString( 'get_site_transient(', $this->src );
	}

	public function test_sanitizes_key_input(): void {
		$this->assertStringContainsString( 'sanitize_text_field(', $this->src );
	}

	public function test_returns_expires_at_from_timeout_option(): void {
		$this->assertStringContainsString( '_transient_timeout_', $this->src );
		$this->assertStringContainsString( '_site_transient_timeout_', $this->src );
	}

	public function test_input_schema_requires_key(): void {
		$this->assertStringContainsString( "'required'             => array( 'key' )", $this->src );
	}

	public function test_meta_mcp_public_false_tool(): void {
		$this->assertStringContainsString( "'public' => false", $this->src );
		$this->assertStringContainsString( "'type'   => 'tool'", $this->src );
	}

	public function test_message_wrapped_in_translation_call(): void {
		$this->assertStringContainsString( "'acrossai-abilities-manager'", $this->src );
	}
}
