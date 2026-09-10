<?php
/**
 * Structural tests for the Feature 063 themes/list-theme-mods ability.
 *
 * Source-inspection only — mirrors Test_Feature_042_Core_Update precedent.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_List_Theme_Mods.
 */
class Test_List_Theme_Mods extends WP_UnitTestCase {

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
		$this->src       = (string) file_get_contents( $plugin_root . '/includes/Abilities/Themes/List_Theme_Mods.php' );
		$this->bootstrap = (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );
	}

	public function test_extends_ability_definition_and_uses_expected_ability_name(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
		$this->assertStringContainsString( "'themes/list-theme-mods'", $this->src );
	}

	public function test_targets_the_themes_category(): void {
		$this->assertStringContainsString( "'acrossai-themes'", $this->src );
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

	public function test_execute_delegates_to_get_theme_mods_and_get_stylesheet(): void {
		$this->assertStringContainsString( 'get_theme_mods()', $this->src );
		$this->assertStringContainsString( 'get_stylesheet()', $this->src );
	}

	public function test_returns_message_wrapped_in_translation_call(): void {
		$this->assertMatchesRegularExpression(
			"/__\\(\\s*'Theme modifications fetched\\.'/",
			$this->src
		);
	}

	public function test_bootstrap_instantiates_the_ability(): void {
		$this->assertStringContainsString( 'new Themes\\List_Theme_Mods()', $this->bootstrap );
	}
}
