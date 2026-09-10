<?php
/**
 * Feature 066 — source-inspection tests for the nested-path branch added to
 * blocks/update-post-block. Ensures the legacy branches remain intact and
 * the new path branch is wired to Block_Tree.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.24
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Update_Post_Block_Nested extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Block/Update_Post_Block.php'
		);
	}

	public function test_still_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_input_schema_still_accepts_legacy_fields(): void {
		$this->assertStringContainsString( "'block_index' => array(", $this->src );
		$this->assertStringContainsString( "'block_name'  => array( 'type' => 'string' )", $this->src );
		$this->assertStringContainsString( "'occurrence'  => array(", $this->src );
	}

	public function test_input_schema_adds_optional_path(): void {
		$this->assertStringContainsString( "'path'        => array(", $this->src );
		// Path is NOT required — post_id remains the only required field.
		$this->assertStringContainsString( "'required'             => array( 'post_id' )", $this->src );
	}

	public function test_path_branch_delegates_to_block_tree(): void {
		$this->assertStringContainsString( 'Block_Tree::get_at_path(', $this->src );
		$this->assertStringContainsString( 'Block_Tree::replace_at_path(', $this->src );
	}

	public function test_path_branch_short_circuits_before_legacy(): void {
		// The path branch check must appear textually BEFORE the block_index
		// branch to preserve the priority order documented in research R7.
		$path_pos  = strpos( $this->src, 'sanitize_path_input' );
		$index_pos = strpos( $this->src, "isset( \$input['block_index']" );
		$this->assertNotFalse( $path_pos );
		$this->assertNotFalse( $index_pos );
		$this->assertLessThan( $index_pos, $path_pos );
	}

	public function test_legacy_block_index_branch_intact(): void {
		$this->assertStringContainsString( "isset( \$input['block_index'] )", $this->src );
		$this->assertStringContainsString( "\$blocks[ \$target_index ]", $this->src );
	}

	public function test_legacy_block_name_regex_intact(): void {
		// Feature 055 regex must still be present so consumers relying on
		// block_name+occurrence get identical validation behaviour.
		$this->assertStringContainsString( '[A-Za-z0-9_-]+', $this->src );
	}

	public function test_top_level_only_comment_removed(): void {
		$this->assertStringNotContainsString( 'Bounded scope: matches only top-level', $this->src );
		$this->assertStringNotContainsString( 'Nested block editing is deferred', $this->src );
	}

	public function test_path_branch_returns_error_for_unresolved_path(): void {
		$this->assertStringContainsString( 'Path does not resolve to a block', $this->src );
	}

	public function test_path_branch_persists_via_wp_update_post(): void {
		// The path branch must go through the same serialize_blocks + wp_update_post pipeline.
		$this->assertStringContainsString( 'serialize_blocks( $blocks )', $this->src );
		$this->assertStringContainsString( 'wp_update_post(', $this->src );
	}
}
