<?php
/**
 * Structural tests for the Feature 063 widgets/list-sidebars ability.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_List_Sidebars.
 */
class Test_List_Sidebars extends WP_UnitTestCase {

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
		$this->src       = (string) file_get_contents( $plugin_root . '/includes/Abilities/Widgets/List_Sidebars.php' );
		$this->bootstrap = (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );
	}

	public function test_extends_ability_definition_and_uses_expected_ability_name(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
		$this->assertStringContainsString( "'widgets/list-sidebars'", $this->src );
	}

	public function test_targets_the_widgets_category(): void {
		$this->assertStringContainsString( "'acrossai-widgets'", $this->src );
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

	public function test_iterates_the_registered_sidebars_global(): void {
		$this->assertStringContainsString( "\$GLOBALS['wp_registered_sidebars']", $this->src );
	}

	public function test_projects_every_wrapper_field_declared_in_the_contract(): void {
		foreach (
			array(
				"'id'",
				"'name'",
				"'description'",
				"'before_widget'",
				"'after_widget'",
				"'before_title'",
				"'after_title'",
			) as $field
		) {
			$this->assertStringContainsString(
				$field,
				$this->src,
				"Sidebar projection must expose $field."
			);
		}
	}

	public function test_bootstrap_instantiates_the_ability(): void {
		$this->assertStringContainsString( 'new Widgets\\List_Sidebars()', $this->bootstrap );
	}
}
