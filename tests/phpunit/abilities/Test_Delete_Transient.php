<?php
/**
 * Structural tests for Feature 064 cache/delete-transient.
 *
 * Source-inspection tests, mirroring the Feature 059 pattern.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.23
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Delete_Transient.
 */
class Test_Delete_Transient extends WP_UnitTestCase {

	/**
	 * The Delete_Transient source, loaded once per test.
	 *
	 * @var string
	 */
	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Cache/Delete_Transient.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'cache/delete-transient'", $this->src );
		$this->assertStringContainsString( "'acrossai-cache'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_annotations_are_destructive_true_readonly_false(): void {
		$this->assertStringContainsString( "'readonly'    => false", $this->src );
		$this->assertStringContainsString( "'destructive' => true", $this->src );
		$this->assertStringContainsString( "'idempotent'  => true", $this->src );
	}

	public function test_dispatches_to_delete_transient_or_delete_site_transient(): void {
		$this->assertStringContainsString( 'delete_transient(', $this->src );
		$this->assertStringContainsString( 'delete_site_transient(', $this->src );
	}

	public function test_input_schema_requires_key_and_accepts_site_flag(): void {
		$this->assertStringContainsString( "'required'             => array( 'key' )", $this->src );
		$this->assertStringContainsString( "'site'", $this->src );
	}

	public function test_sanitizes_key_input(): void {
		$this->assertStringContainsString( 'sanitize_text_field(', $this->src );
	}
}
