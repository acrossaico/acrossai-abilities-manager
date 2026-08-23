<?php
/**
 * Feature 070 — source-inspection tests for blocks/list-block-categories.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.31
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_List_Block_Categories extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Block/List_Block_Categories.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'blocks/list-block-categories'", $this->src );
		$this->assertStringContainsString( "'acrossai-abilities-manager-block'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_annotations_mark_readonly(): void {
		$this->assertStringContainsString( "'readonly'    => true", $this->src );
	}

	public function test_calls_get_block_categories(): void {
		$this->assertStringContainsString( 'get_block_categories(', $this->src );
	}

	public function test_guards_missing_core_function(): void {
		$this->assertStringContainsString( "function_exists( 'get_block_categories' )", $this->src );
	}

	public function test_output_shape_includes_slug_title_icon(): void {
		$this->assertStringContainsString( "'slug'", $this->src );
		$this->assertStringContainsString( "'title'", $this->src );
		$this->assertStringContainsString( "'icon'", $this->src );
	}

	public function test_sub_group_is_block_info(): void {
		$this->assertStringContainsString( "'sub_group'       => 'block-info'", $this->src );
	}
}
