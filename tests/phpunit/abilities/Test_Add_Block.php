<?php
/**
 * Feature 066 — source-inspection tests for blocks/add-block.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.24
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Add_Block extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Block/Add_Block.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'blocks/add-block'", $this->src );
		$this->assertStringContainsString( "'acrossai-block'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_validates_block_name_before_persisting(): void {
		$this->assertStringContainsString( 'Block_Tree::validate_block_name(', $this->src );
		$this->assertStringContainsString( "'invalid_block_name'", $this->src );
	}

	public function test_validates_attributes_against_schema(): void {
		$this->assertStringContainsString( 'Block_Tree::validate_attributes_against_schema(', $this->src );
	}

	public function test_delegates_to_block_tree_insert(): void {
		$this->assertStringContainsString( 'Block_Tree::insert_at_path(', $this->src );
	}

	public function test_persists_via_wp_update_post(): void {
		$this->assertStringContainsString( 'wp_update_post(', $this->src );
		$this->assertStringContainsString( 'serialize_blocks(', $this->src );
	}

	public function test_input_schema_requires_all_five_fields(): void {
		$this->assertStringContainsString(
			"'required'             => array( 'post_id', 'parent_path', 'index', 'block' )",
			$this->src
		);
	}

	public function test_response_includes_new_path(): void {
		$this->assertStringContainsString( "\$inserted['path']", $this->src );
	}
}
