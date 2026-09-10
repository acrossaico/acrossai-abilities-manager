<?php
/**
 * Feature 067 — Get_Element ability tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Get_Element extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Get_Element.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'elementor/get-element'", $this->src );
		$this->assertStringContainsString( "'acrossai-elementor'", $this->src );
	}

	public function test_input_requires_post_id_and_element_id(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id', 'element_id' )", $this->src );
	}

	public function test_defense_in_depth_elementor_gate(): void {
		$this->assertStringContainsString( 'Document_Repository::assert_elementor_available()', $this->src );
	}

	public function test_validates_element_id(): void {
		$this->assertStringContainsString( 'Document_Repository::is_valid_element_id', $this->src );
		$this->assertStringContainsString( "'invalid_element_id'", $this->src );
	}

	public function test_uses_read_capability(): void {
		$this->assertStringContainsString( "load_document( \$post_id, 'read' )", $this->src );
	}

	public function test_returns_element_not_found(): void {
		$this->assertStringContainsString( "'element_not_found'", $this->src );
	}

	public function test_readonly_annotations(): void {
		$this->assertStringContainsString( "'readonly' => true", $this->src );
		$this->assertStringContainsString( "'idempotent' => true", $this->src );
	}
}
