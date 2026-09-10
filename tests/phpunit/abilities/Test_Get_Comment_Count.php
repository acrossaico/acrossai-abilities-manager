<?php
/**
 * Structural tests for the Feature 063 comments/get-comment-count ability.
 *
 * Source-inspection only — mirrors Test_Feature_042_Core_Update precedent.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Get_Comment_Count.
 */
class Test_Get_Comment_Count extends WP_UnitTestCase {

	/** @var string */
	private string $src = '';

	/** @var string */
	private string $bootstrap = '';

	/**
	 * Load the ability source once per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$plugin_root     = dirname( __DIR__, 3 );
		$this->src       = (string) file_get_contents( $plugin_root . '/includes/Abilities/Comments/Get_Comment_Count.php' );
		$this->bootstrap = (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );
	}

	public function test_extends_ability_definition_and_uses_expected_ability_name(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
		$this->assertStringContainsString( "'comments/get-comment-count'", $this->src );
	}

	public function test_targets_the_comments_category(): void {
		$this->assertStringContainsString( "'acrossai-comments'", $this->src );
	}

	public function test_permission_callback_gates_on_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\\(\\s*'manage_options'\\s*\\)/",
			$this->src
		);
	}

	public function test_declares_readonly_idempotent_non_destructive_annotations(): void {
		$this->assertStringContainsString( "'readonly'    => true", $this->src );
		$this->assertStringContainsString( "'idempotent'  => true", $this->src );
		$this->assertStringContainsString( "'destructive' => false", $this->src );
	}

	public function test_post_id_input_is_optional_with_default_zero(): void {
		$this->assertMatchesRegularExpression(
			"/'post_id'\\s*=>\\s*array\\(/",
			$this->src
		);
		$this->assertStringContainsString( "'default'     => 0", $this->src );
		$this->assertStringNotContainsString( "'required'             => array( 'post_id' )", $this->src );
	}

	public function test_execute_absints_input_and_calls_wp_count_comments(): void {
		$this->assertStringContainsString( 'absint(', $this->src );
		$this->assertStringContainsString( 'wp_count_comments(', $this->src );
	}

	public function test_bootstrap_instantiates_the_ability(): void {
		$this->assertStringContainsString( 'new Comments\\Get_Comment_Count()', $this->bootstrap );
	}
}
