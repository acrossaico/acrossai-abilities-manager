<?php
/**
 * Feature 070 — source-inspection tests for blocks/get-style-guide.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.31
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Get_Style_Guide extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Block/Get_Style_Guide.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'blocks/get-style-guide'", $this->src );
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
		$this->assertStringContainsString( "'destructive' => false", $this->src );
		$this->assertStringContainsString( "'idempotent'  => true", $this->src );
	}

	public function test_output_schema_includes_design_system_fields(): void {
		$this->assertStringContainsString( "'spacing'", $this->src );
		$this->assertStringContainsString( "'palette'", $this->src );
		$this->assertStringContainsString( "'typography'", $this->src );
		$this->assertStringContainsString( "'layout'", $this->src );
		$this->assertStringContainsString( "'duotone'", $this->src );
		$this->assertStringContainsString( "'gradients'", $this->src );
	}

	public function test_reads_theme_json_via_resolver(): void {
		$this->assertStringContainsString( 'WP_Theme_JSON_Resolver::get_merged_data', $this->src );
		$this->assertStringContainsString( '->get_settings()', $this->src );
	}

	public function test_guards_missing_resolver(): void {
		$this->assertStringContainsString( "class_exists( '\\WP_Theme_JSON_Resolver' )", $this->src );
	}

	public function test_sub_group_is_site_editor(): void {
		$this->assertStringContainsString( "'sub_group'       => 'site-editor'", $this->src );
	}
}
