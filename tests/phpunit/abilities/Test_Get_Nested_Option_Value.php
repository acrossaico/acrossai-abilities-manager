<?php
/**
 * Structural tests for Feature 064 options/get-nested-option-value.
 *
 * Source-inspection tests, mirroring the Feature 059 pattern.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.23
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Get_Nested_Option_Value.
 */
class Test_Get_Nested_Option_Value extends WP_UnitTestCase {

	/**
	 * The Get_Nested_Option_Value source, loaded once per test.
	 *
	 * @var string
	 */
	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Options/Get_Nested_Option_Value.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'options/get-nested-option-value'", $this->src );
		$this->assertStringContainsString( "'acrossai-options'", $this->src );
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

	public function test_reads_via_get_option(): void {
		$this->assertStringContainsString( 'get_option(', $this->src );
	}

	public function test_walks_path_via_array_key_exists(): void {
		$this->assertStringContainsString( 'array_key_exists(', $this->src );
	}

	public function test_returns_exists_false_when_option_missing(): void {
		$this->assertMatchesRegularExpression(
			"/null\s*===\s*\\\$option_value/",
			$this->src
		);
		$this->assertStringContainsString( "'exists'  => false", $this->src );
	}

	public function test_input_schema_requires_option_and_path(): void {
		$this->assertStringContainsString( "'required'             => array( 'option', 'path' )", $this->src );
		$this->assertStringContainsString( "'minItems' => 1", $this->src );
	}

	public function test_sanitizes_string_inputs(): void {
		$this->assertStringContainsString( 'sanitize_text_field(', $this->src );
	}
}
