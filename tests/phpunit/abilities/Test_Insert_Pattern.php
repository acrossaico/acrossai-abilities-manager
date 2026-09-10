<?php
/**
 * Feature 066 — source-inspection tests for blocks/insert-pattern.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.24
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Insert_Pattern extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Block/Insert_Pattern.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'blocks/insert-pattern'", $this->src );
		$this->assertStringContainsString( "'acrossai-block'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_delegates_slug_resolution_to_pattern_detector(): void {
		// This is the DRY requirement — Insert_Pattern must not duplicate
		// source-scanning logic already implemented in Pattern_Detector.
		$this->assertStringContainsString( 'Pattern_Detector::locate(', $this->src );
		$this->assertStringContainsString( 'Pattern_Detector::select(', $this->src );
	}

	public function test_input_schema_accepts_source_disambiguation(): void {
		$this->assertStringContainsString( "'source'      => array(", $this->src );
		$this->assertStringContainsString( "'db', 'theme', 'plugin'", $this->src );
	}

	public function test_input_requires_slug_and_position(): void {
		$this->assertStringContainsString(
			"'required'             => array( 'post_id', 'parent_path', 'index', 'slug' )",
			$this->src
		);
	}

	public function test_inserts_via_block_tree(): void {
		$this->assertStringContainsString( 'Block_Tree::insert_at_path(', $this->src );
	}

	public function test_persists_via_wp_update_post(): void {
		$this->assertStringContainsString( 'wp_update_post(', $this->src );
	}

	public function test_response_reports_inserted_paths(): void {
		$this->assertStringContainsString( "'inserted_paths' => \$inserted_paths", $this->src );
		$this->assertStringContainsString( "'count'          => count( \$inserted_paths )", $this->src );
	}

	public function test_sanitizes_slug(): void {
		$this->assertStringContainsString( 'sanitize_title(', $this->src );
	}
}
