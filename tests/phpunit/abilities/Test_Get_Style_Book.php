<?php
/**
 * Feature 070 — source-inspection tests for blocks/get-style-book.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.31
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Get_Style_Book extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Block/Get_Style_Book.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'blocks/get-style-book'", $this->src );
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

	public function test_walks_block_type_registry(): void {
		$this->assertStringContainsString( 'WP_Block_Type_Registry::get_instance()', $this->src );
		$this->assertStringContainsString( '->get_all_registered()', $this->src );
	}

	public function test_skips_blocks_without_example(): void {
		$this->assertStringContainsString( 'empty( $block_type->example )', $this->src );
	}

	public function test_renders_example_markup(): void {
		$this->assertStringContainsString( "function_exists( 'render_block' )", $this->src );
		$this->assertStringContainsString( 'render_block(', $this->src );
	}

	public function test_output_includes_expected_fields(): void {
		$this->assertStringContainsString( "'name'", $this->src );
		$this->assertStringContainsString( "'title'", $this->src );
		$this->assertStringContainsString( "'category'", $this->src );
		$this->assertStringContainsString( "'example'", $this->src );
	}

	public function test_sub_group_is_block_info(): void {
		$this->assertStringContainsString( "'sub_group'       => 'block-info'", $this->src );
	}
}
