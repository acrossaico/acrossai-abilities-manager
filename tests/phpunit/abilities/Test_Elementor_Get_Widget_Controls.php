<?php
/**
 * Feature 067 — Get_Widget_Controls ability tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Get_Widget_Controls extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Get_Widget_Controls.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'elementor/get-widget-controls'", $this->src );
		$this->assertStringContainsString( "'acrossai-elementor'", $this->src );
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

	public function test_input_requires_widget_type(): void {
		$this->assertStringContainsString( "'required'             => array( 'widget_type' )", $this->src );
	}

	public function test_defense_in_depth_elementor_gate(): void {
		$this->assertStringContainsString( 'Document_Repository::assert_elementor_available()', $this->src );
	}

	public function test_delegates_to_widget_controls_utility(): void {
		$this->assertStringContainsString( 'Widget_Controls::get_type(', $this->src );
		$this->assertStringContainsString( 'Widget_Controls::summarize(', $this->src );
	}

	public function test_returns_invalid_widget_type_error_code(): void {
		$this->assertStringContainsString( "'invalid_widget_type'", $this->src );
	}
}
