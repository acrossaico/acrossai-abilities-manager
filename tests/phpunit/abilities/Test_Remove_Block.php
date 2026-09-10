<?php
/**
 * Feature 066 — source-inspection tests for blocks/remove-block.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.24
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Remove_Block extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Block/Remove_Block.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'blocks/remove-block'", $this->src );
		$this->assertStringContainsString( "'acrossai-abilities-manager-block'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_annotations_destructive_true(): void {
		$this->assertStringContainsString( "'destructive' => true", $this->src );
	}

	public function test_input_requires_non_empty_path(): void {
		$this->assertStringContainsString( "'minItems' => 1", $this->src );
		$this->assertStringContainsString( "'required'             => array( 'post_id', 'path' )", $this->src );
	}

	public function test_delegates_to_block_tree_remove(): void {
		$this->assertStringContainsString( 'Block_Tree::remove_at_path(', $this->src );
	}

	public function test_persists_via_wp_update_post(): void {
		$this->assertStringContainsString( 'wp_update_post(', $this->src );
	}

	public function test_response_returns_removed_block(): void {
		$this->assertStringContainsString( "'removed' => \$removed", $this->src );
	}

	public function test_returns_invalid_path_error_code(): void {
		$this->assertStringContainsString( "'invalid_path'", $this->src );
	}
}
