<?php
/**
 * Structural tests for the Feature 063 core/get-wp-version ability.
 *
 * Source-inspection only — mirrors Test_Feature_042_Core_Update. The
 * plugin's stub bootstrap cannot safely load a full WordPress runtime,
 * so we assert the ability's class shape, permission gate, category
 * membership, and delegation to the correct WP core primitives.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Get_Wp_Version.
 */
class Test_Get_Wp_Version extends WP_UnitTestCase {

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
		$this->src       = (string) file_get_contents( $plugin_root . '/includes/Abilities/Core/Get_Wp_Version.php' );
		$this->bootstrap = (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );
	}

	public function test_extends_ability_definition_and_uses_expected_ability_name(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
		$this->assertStringContainsString( "'core/get-wp-version'", $this->src );
	}

	public function test_targets_the_core_category(): void {
		$this->assertStringContainsString( "'acrossai-core'", $this->src );
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

	public function test_declares_show_in_rest_and_non_public_mcp_tool(): void {
		$this->assertStringContainsString( "'show_in_rest' => true", $this->src );
		$this->assertStringContainsString( "'public' => false", $this->src );
		$this->assertStringContainsString( "'type'   => 'tool'", $this->src );
	}

	public function test_execute_delegates_to_get_bloginfo_version_and_is_multisite(): void {
		$this->assertStringContainsString( "get_bloginfo( 'version' )", $this->src );
		$this->assertStringContainsString( 'is_multisite()', $this->src );
	}

	public function test_returns_message_wrapped_in_translation_call(): void {
		$this->assertMatchesRegularExpression(
			"/__\\(\\s*'WordPress version fetched\\.'/",
			$this->src
		);
	}

	public function test_bootstrap_instantiates_the_ability(): void {
		$this->assertStringContainsString( 'new Core\\Get_Wp_Version()', $this->bootstrap );
	}
}
