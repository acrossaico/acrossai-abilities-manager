<?php
/**
 * Feature 070 — source-inspection tests for blocks/get-site-editor-summary.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.31
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Get_Site_Editor_Summary extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Block/Get_Site_Editor_Summary.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'blocks/get-site-editor-summary'", $this->src );
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

	public function test_groups_templates_and_parts_by_area(): void {
		$this->assertStringContainsString( "'templates_by_area'", $this->src );
		$this->assertStringContainsString( "'template_parts_by_area'", $this->src );
	}

	public function test_includes_style_variations_and_navigations(): void {
		$this->assertStringContainsString( "'style_variations'", $this->src );
		$this->assertStringContainsString( "'navigations'", $this->src );
	}

	public function test_reads_block_templates(): void {
		$this->assertStringContainsString( "get_block_templates( array(), 'wp_template' )", $this->src );
		$this->assertStringContainsString( "get_block_templates( array(), 'wp_template_part' )", $this->src );
	}

	public function test_reads_wp_navigation_posts(): void {
		$this->assertStringContainsString( "'post_type'      => 'wp_navigation'", $this->src );
	}

	public function test_reads_style_variations_via_resolver(): void {
		$this->assertStringContainsString( "WP_Theme_JSON_Resolver', 'get_style_variations'", $this->src );
	}

	public function test_sub_group_is_site_editor(): void {
		$this->assertStringContainsString( "'sub_group'       => 'site-editor'", $this->src );
	}
}
