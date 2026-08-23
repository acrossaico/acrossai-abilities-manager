<?php
/**
 * Feature 070 — source-inspection tests for blocks/get-site-editor-references.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.31
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Get_Site_Editor_References extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Block/Get_Site_Editor_References.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'blocks/get-site-editor-references'", $this->src );
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

	public function test_input_schema_enumerates_object_types(): void {
		$this->assertStringContainsString( "'template_part'", $this->src );
		$this->assertStringContainsString( "'navigation'", $this->src );
		$this->assertStringContainsString( "'reusable_block'", $this->src );
	}

	public function test_requires_object_type(): void {
		$this->assertStringContainsString( "'required'             => array( 'object_type' )", $this->src );
	}

	public function test_matches_target_block_names(): void {
		$this->assertStringContainsString( "'core/template-part'", $this->src );
		$this->assertStringContainsString( "'core/navigation'", $this->src );
		$this->assertStringContainsString( "'core/block'", $this->src );
	}

	public function test_walks_templates_and_parts(): void {
		$this->assertStringContainsString( "get_block_templates( array(), 'wp_template' )", $this->src );
		$this->assertStringContainsString( "get_block_templates( array(), 'wp_template_part' )", $this->src );
		$this->assertStringContainsString( 'parse_blocks(', $this->src );
	}

	public function test_recursively_counts_matches(): void {
		$this->assertStringContainsString( "'innerBlocks'", $this->src );
		$this->assertStringContainsString( 'count_matches(', $this->src );
	}

	public function test_output_includes_reference_shape(): void {
		$this->assertStringContainsString( "'referencing_object_type'", $this->src );
		$this->assertStringContainsString( "'referencing_object_id'", $this->src );
		$this->assertStringContainsString( "'referencing_object_title'", $this->src );
		$this->assertStringContainsString( "'occurrences'", $this->src );
	}

	public function test_sub_group_is_site_editor(): void {
		$this->assertStringContainsString( "'sub_group'       => 'site-editor'", $this->src );
	}
}
