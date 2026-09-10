<?php
/**
 * Feature 067 — Get_Data ability tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Get_Data extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Get_Data.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'elementor/get-data'", $this->src );
		$this->assertStringContainsString( "'acrossai-elementor'", $this->src );
	}

	public function test_readonly_annotations(): void {
		$this->assertStringContainsString( "'readonly'    => true", $this->src );
		$this->assertStringContainsString( "'idempotent'  => true", $this->src );
	}

	public function test_input_requires_post_id(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id' )", $this->src );
	}

	public function test_uses_read_capability_via_document_repository(): void {
		$this->assertStringContainsString( "Document_Repository::load_document( \$post_id, 'read' )", $this->src );
	}

	public function test_defense_in_depth_elementor_gate(): void {
		$this->assertStringContainsString( 'Document_Repository::assert_elementor_available()', $this->src );
	}

	public function test_walks_tree_to_count_elements(): void {
		$this->assertStringContainsString( 'Document_Repository::walk_tree(', $this->src );
	}
}
