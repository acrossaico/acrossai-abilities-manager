<?php
/**
 * Feature 066 — source-inspection tests for blocks/duplicate-block.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.24
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Duplicate_Block extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Block/Duplicate_Block.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'blocks/duplicate-block'", $this->src );
		$this->assertStringContainsString( "'acrossai-block'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_input_requires_non_empty_path(): void {
		$this->assertStringContainsString( "'minItems' => 1", $this->src );
	}

	public function test_reads_source_and_inserts_clone(): void {
		$this->assertStringContainsString( 'Block_Tree::get_at_path(', $this->src );
		$this->assertStringContainsString( 'Block_Tree::insert_at_path(', $this->src );
	}

	public function test_clone_inserted_as_next_sibling(): void {
		$this->assertStringContainsString( '$source_index + 1', $this->src );
	}

	public function test_persists_via_wp_update_post(): void {
		$this->assertStringContainsString( 'wp_update_post(', $this->src );
	}

	public function test_response_annotates_clone_with_new_path(): void {
		$this->assertStringContainsString( "\$cloned_block['path']", $this->src );
	}
}
