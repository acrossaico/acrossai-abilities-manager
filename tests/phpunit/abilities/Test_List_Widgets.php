<?php
/**
 * Structural tests for the Feature 063 widgets/list-widgets ability.
 *
 * Also verifies the Widgets Category_Registrar exists and the bootstrap
 * wires both.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_List_Widgets.
 */
class Test_List_Widgets extends WP_UnitTestCase {

	/** @var string */
	private string $src = '';

	/** @var string */
	private string $registrar = '';

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
		$this->src       = (string) file_get_contents( $plugin_root . '/includes/Abilities/Widgets/List_Widgets.php' );
		$this->registrar = (string) file_get_contents( $plugin_root . '/includes/Abilities/Widgets/Category_Registrar.php' );
		$this->bootstrap = (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );
	}

	public function test_extends_ability_definition_and_uses_expected_ability_name(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
		$this->assertStringContainsString( "'widgets/list-widgets'", $this->src );
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

	public function test_reads_sidebar_widgets_and_registered_widgets_registry(): void {
		$this->assertStringContainsString( 'wp_get_sidebars_widgets()', $this->src );
		$this->assertStringContainsString( "\$GLOBALS['wp_registered_widgets']", $this->src );
	}

	public function test_projects_each_widget_with_name_and_classname(): void {
		$this->assertStringContainsString( "'name'", $this->src );
		$this->assertStringContainsString( "'classname'", $this->src );
	}

	public function test_widgets_category_registrar_shape(): void {
		$this->assertStringContainsString( 'final class Category_Registrar', $this->registrar );
		$this->assertStringContainsString( 'public static function instance(): self', $this->registrar );
		$this->assertStringContainsString( 'public function register(): void', $this->registrar );
		$this->assertStringContainsString( "'acrossai-widgets'", $this->registrar );
		$this->assertStringContainsString( 'wp_register_ability_category', $this->registrar );
	}

	public function test_bootstrap_wires_widgets_category_and_instantiates_the_ability(): void {
		$this->assertStringContainsString( "Widgets\\Category_Registrar::instance(), 'register'", $this->bootstrap );
		$this->assertStringContainsString( 'new Widgets\\List_Widgets()', $this->bootstrap );
	}
}
