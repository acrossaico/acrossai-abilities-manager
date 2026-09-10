<?php
/**
 * Feature 066 — source-inspection tests for blocks/move-block.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.24
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Move_Block extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Block/Move_Block.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'blocks/move-block'", $this->src );
		$this->assertStringContainsString( "'acrossai-block'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_input_requires_from_and_to(): void {
		$this->assertStringContainsString(
			"'required'             => array( 'post_id', 'from_path', 'to_parent_path', 'to_index' )",
			$this->src
		);
	}

	public function test_delegates_atomic_move_to_block_tree(): void {
		$this->assertStringContainsString( 'Block_Tree::move(', $this->src );
	}

	public function test_persists_via_wp_update_post(): void {
		$this->assertStringContainsString( 'wp_update_post(', $this->src );
	}

	public function test_response_includes_previous_path(): void {
		$this->assertStringContainsString( "'previous_path' => \$from_path", $this->src );
	}
}
