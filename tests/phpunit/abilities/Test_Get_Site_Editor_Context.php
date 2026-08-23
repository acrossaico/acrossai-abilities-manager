<?php
/**
 * Feature 070 — regression tests for blocks/get-site-editor-context (2-field extension).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.31
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Get_Site_Editor_Context extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Block/Get_Site_Editor_Context.php'
		);
	}

	public function test_slug_and_category_unchanged(): void {
		$this->assertStringContainsString( "'blocks/get-site-editor-context'", $this->src );
		$this->assertStringContainsString( "'acrossai-abilities-manager-block'", $this->src );
	}

	public function test_existing_output_fields_preserved(): void {
		$this->assertStringContainsString( "'is_block_theme'", $this->src );
		$this->assertStringContainsString( "'active_theme'", $this->src );
		$this->assertStringContainsString( "'active_style_variation'", $this->src );
		$this->assertStringContainsString( "'template_count'", $this->src );
		$this->assertStringContainsString( "'template_part_count'", $this->src );
		$this->assertStringContainsString( "'site_editor_url'", $this->src );
	}

	public function test_new_navigation_count_field_present_in_schema(): void {
		$this->assertStringContainsString( "'navigation_count'       => array( 'type' => 'integer' )", $this->src );
	}

	public function test_new_style_variation_count_field_present_in_schema(): void {
		$this->assertStringContainsString( "'style_variation_count'  => array( 'type' => 'integer' )", $this->src );
	}

	public function test_navigation_count_queries_wp_navigation_posts(): void {
		$this->assertStringContainsString( "'post_type'      => 'wp_navigation'", $this->src );
		$this->assertStringContainsString( "'fields'         => 'ids'", $this->src );
	}

	public function test_style_variation_count_uses_resolver(): void {
		$this->assertStringContainsString( "WP_Theme_JSON_Resolver', 'get_style_variations'", $this->src );
	}

	public function test_annotations_still_readonly(): void {
		$this->assertStringContainsString( "'readonly'    => true", $this->src );
		$this->assertStringContainsString( "'idempotent'  => true", $this->src );
	}
}
