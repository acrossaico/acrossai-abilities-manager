<?php
/**
 * Structural tests for Feature 064 options/patch-option-value.
 *
 * Source-inspection tests, mirroring the Feature 059 pattern.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.23
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Patch_Option_Value.
 */
class Test_Patch_Option_Value extends WP_UnitTestCase {

	/**
	 * The Patch_Option_Value source, loaded once per test.
	 *
	 * @var string
	 */
	private string $src = '';

	/**
	 * The Update_Option source (block-list host), loaded once per test.
	 *
	 * @var string
	 */
	private string $update_option_src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root             = dirname( __DIR__, 3 );
		$this->src               = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Options/Patch_Option_Value.php'
		);
		$this->update_option_src = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Options/Update_Option.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'options/patch-option-value'", $this->src );
		$this->assertStringContainsString( "'acrossai-options'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_annotations_destructive_true(): void {
		$this->assertStringContainsString( "'readonly'    => false", $this->src );
		$this->assertStringContainsString( "'destructive' => true", $this->src );
	}

	public function test_operation_enum_is_insert_update_delete(): void {
		$this->assertStringContainsString( "'insert', 'update', 'delete'", $this->src );
	}

	public function test_input_schema_requires_option_operation_and_path(): void {
		$this->assertStringContainsString( "'required'             => array( 'option', 'operation', 'path' )", $this->src );
	}

	public function test_reuses_update_option_blocked_options_const(): void {
		$this->assertStringContainsString( 'Update_Option::BLOCKED_OPTIONS', $this->src );
		$this->assertStringContainsString( 'public const BLOCKED_OPTIONS', $this->update_option_src, 'Update_Option must expose BLOCKED_OPTIONS as a public const.' );
	}

	public function test_empty_path_guard_returns_empty_path_reason(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'empty_path'", $this->src );
	}

	public function test_blocked_option_guard_returns_blocked_option_reason(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'blocked_option'", $this->src );
	}

	public function test_non_traversable_intermediate_returns_non_traversable_reason(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'non_traversable_intermediate'", $this->src );
	}

	public function test_persists_via_update_option(): void {
		$this->assertStringContainsString( 'update_option(', $this->src );
	}

	public function test_reads_via_get_option(): void {
		$this->assertStringContainsString( 'get_option(', $this->src );
	}

	public function test_sanitizes_string_inputs(): void {
		$this->assertStringContainsString( 'sanitize_text_field(', $this->src );
	}
}
