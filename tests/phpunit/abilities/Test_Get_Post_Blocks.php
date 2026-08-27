<?php
/**
 * Feature 066 — source-inspection tests for blocks/get-post-blocks.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.24
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Get_Post_Blocks extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Content/Get_Post_Blocks.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'blocks/get-post-blocks'", $this->src );
		$this->assertStringContainsString( "'acrossai-abilities-manager-content'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_annotations_readonly(): void {
		$this->assertStringContainsString( "'readonly'    => true", $this->src );
		$this->assertStringContainsString( "'destructive' => false", $this->src );
		$this->assertStringContainsString( "'idempotent'  => true", $this->src );
	}

	public function test_delegates_to_block_tree_read(): void {
		$this->assertStringContainsString( "Block_Tree::parse_post_blocks( \$post_id, 'read' )", $this->src );
		$this->assertStringContainsString( 'Block_Tree::annotate_with_paths(', $this->src );
	}

	public function test_input_schema_requires_post_id(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id' )", $this->src );
	}

	public function test_output_exposes_blocks_and_total(): void {
		$this->assertMatchesRegularExpression(
			"/'blocks'\\s*=>\\s*array\\(\\s*'type'\\s*=>\\s*'array'\\s*\\)/",
			$this->src
		);
		$this->assertMatchesRegularExpression(
			"/'total'\\s*=>\\s*array\\(\\s*'type'\\s*=>\\s*'integer'\\s*\\)/",
			$this->src
		);
	}

	public function test_input_schema_declares_scoping_fields(): void {
		$this->assertMatchesRegularExpression(
			"/'path'\\s*=>\\s*array\\([^)]*'type'\\s*=>\\s*'array'/s",
			$this->src,
			'input_schema must declare path:array for subtree scoping'
		);
		$this->assertMatchesRegularExpression(
			"/'depth'\\s*=>\\s*array\\([^)]*'default'\\s*=>\\s*-1/s",
			$this->src,
			'input_schema must declare depth with default -1 (backwards-compat)'
		);
		$this->assertMatchesRegularExpression(
			"/'include_html'\\s*=>\\s*array\\([^)]*'default'\\s*=>\\s*true/s",
			$this->src,
			'input_schema must declare include_html with default true (backwards-compat)'
		);
	}

	public function test_output_schema_declares_scoping_confirmation_fields(): void {
		$this->assertMatchesRegularExpression(
			"/'path'\\s*=>\\s*array\\(\\s*'type'\\s*=>\\s*'array'\\s*\\)/",
			$this->src,
			'output_schema must confirm the resolved path back to the caller'
		);
		$this->assertMatchesRegularExpression(
			"/'include_html'\\s*=>\\s*array\\(\\s*'type'\\s*=>\\s*'boolean'\\s*\\)/",
			$this->src,
			'output_schema must confirm include_html back to the caller'
		);
		$this->assertMatchesRegularExpression(
			"/'error_code'\\s*=>\\s*array\\(\\s*'type'\\s*=>\\s*'string'\\s*\\)/",
			$this->src,
			'output_schema must declare error_code for invalid_path envelopes'
		);
	}

	public function test_execute_reads_new_scoping_inputs(): void {
		$this->assertStringContainsString( "\$input['path']", $this->src );
		$this->assertStringContainsString( "\$input['depth']", $this->src );
		$this->assertStringContainsString( "\$input['include_html']", $this->src );
	}

	public function test_execute_uses_block_tree_get_at_path_for_subtree_resolution(): void {
		$this->assertStringContainsString( 'Block_Tree::get_at_path(', $this->src );
	}

	public function test_include_html_false_strips_inner_html_and_inner_content(): void {
		$this->assertMatchesRegularExpression(
			"/unset\\(\\s*\\\$block\\['innerHTML'\\]\\s*,\\s*\\\$block\\['innerContent'\\]\\s*\\)/",
			$this->src,
			'include_html:false must unset both innerHTML and innerContent'
		);
	}

	public function test_description_documents_new_capabilities(): void {
		$this->assertMatchesRegularExpression(
			'/path.*depth.*include_html/s',
			$this->src,
			'Description must mention all three new inputs so LLM callers discover the cheap-read path'
		);
	}
}
