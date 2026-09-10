<?php
/**
 * Structural tests for the Feature 063 database/get-db-prefix ability.
 *
 * Source-inspection only — mirrors Test_Feature_042_Core_Update precedent.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Get_Db_Prefix.
 */
class Test_Get_Db_Prefix extends WP_UnitTestCase {

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
		$this->src       = (string) file_get_contents( $plugin_root . '/includes/Abilities/Database/Get_Db_Prefix.php' );
		$this->bootstrap = (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );
	}

	public function test_extends_ability_definition_and_uses_expected_ability_name(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
		$this->assertStringContainsString( "'database/get-db-prefix'", $this->src );
	}

	public function test_targets_the_database_category(): void {
		$this->assertStringContainsString( "'acrossai-database'", $this->src );
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

	public function test_execute_reads_both_prefix_properties_from_wpdb(): void {
		$this->assertStringContainsString( "\$GLOBALS['wpdb']", $this->src );
		$this->assertStringContainsString( '->prefix', $this->src );
		$this->assertStringContainsString( '->base_prefix', $this->src );
	}

	public function test_returns_message_wrapped_in_translation_call(): void {
		$this->assertMatchesRegularExpression(
			"/__\\(\\s*'Database prefix fetched\\.'/",
			$this->src
		);
	}

	public function test_bootstrap_instantiates_the_ability(): void {
		$this->assertStringContainsString( 'new Database\\Get_Db_Prefix()', $this->bootstrap );
	}
}
