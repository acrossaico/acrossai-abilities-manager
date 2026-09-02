<?php
/**
 * Structural tests for Feature 064 content/add-post-meta.
 *
 * Source-inspection tests, mirroring the Feature 059 pattern.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.23
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Add_Post_Meta.
 */
class Test_Add_Post_Meta extends WP_UnitTestCase {

	/**
	 * The Add_Post_Meta source, loaded once per test.
	 *
	 * @var string
	 */
	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Content/Add_Post_Meta.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'content/add-post-meta'", $this->src );
		$this->assertStringContainsString( "'acrossai-abilities-manager-content'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_annotations_destructive_false_additive(): void {
		$this->assertStringContainsString( "'readonly'    => false", $this->src );
		$this->assertStringContainsString( "'destructive' => false", $this->src );
	}

	public function test_calls_add_post_meta_with_unique_flag(): void {
		$this->assertStringContainsString( 'add_post_meta( $post_id, $key, wp_slash( $value ), $unique )', $this->src );
	}

	public function test_accepts_key_and_meta_key_aliases(): void {
		$this->assertStringContainsString( "'key'", $this->src );
		$this->assertStringContainsString( "'meta_key'", $this->src );
		$this->assertStringContainsString( "'value'", $this->src );
		$this->assertStringContainsString( "'meta_value'", $this->src );
	}

	public function test_accepts_unique_flag_defaulting_false(): void {
		$this->assertStringContainsString( "'unique'", $this->src );
		$this->assertMatchesRegularExpression(
			"/'unique'.*'default'\s*=>\s*false/s",
			$this->src
		);
	}

	public function test_input_schema_requires_post_id_and_a_key_alias(): void {
		$this->assertStringContainsString( "'required' => array( 'post_id' )", $this->src );
		$this->assertStringContainsString( "'anyOf' => array(", $this->src );
	}

	public function test_refuses_when_post_not_found(): void {
		$this->assertStringContainsString( 'get_post( $post_id )', $this->src );
	}

	public function test_sanitizes_meta_key(): void {
		$this->assertStringContainsString( 'sanitize_text_field(', $this->src );
	}

	public function test_returns_meta_id_or_false(): void {
		$this->assertStringContainsString( "'meta_id'", $this->src );
		$this->assertStringContainsString( 'false === $meta_id', $this->src );
	}
}
