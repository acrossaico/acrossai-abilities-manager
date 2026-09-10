<?php
/**
 * Structural tests for the Feature 062 Create_Role ability.
 *
 * Covers the users/create-role ability under
 * includes/Abilities/Users/Create_Role.php plus bootstrap wiring.
 *
 * Source-inspection only, mirroring Test_Feature_057_Core_Reinstall — the
 * suite's established pattern for absorbed-tier abilities (fixture-free).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Create_Role.
 */
class Test_Create_Role extends WP_UnitTestCase {

	/**
	 * Absolute paths to every source file exercised by these tests.
	 *
	 * @var array<string,string>
	 */
	private array $sources = array();

	/**
	 * Load every source file once per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$plugin_root   = dirname( __DIR__, 3 );
		$this->sources = array(
			'create_role' => (string) file_get_contents( $plugin_root . '/includes/Abilities/Users/Create_Role.php' ),
			'bootstrap'   => (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' ),
		);
	}

	public function test_class_extends_ability_definition(): void {
		$src = $this->sources['create_role'];
		$this->assertStringContainsString(
			'namespace AcrossAI_Abilities_Manager\\Includes\\Abilities\\Users;',
			$src
		);
		$this->assertStringContainsString( 'class Create_Role extends Ability_Definition', $src );
		$this->assertStringContainsString( "defined( 'ABSPATH' ) || exit;", $src );
	}

	public function test_ability_name_and_category(): void {
		$src = $this->sources['create_role'];
		$this->assertStringContainsString( "'users/create-role'", $src );
		$this->assertStringContainsString( "'acrossai-users'", $src );
	}

	public function test_permission_callback_gates_on_manage_options(): void {
		$src = $this->sources['create_role'];
		$this->assertMatchesRegularExpression(
			"/'permission_callback'\s*=>\s*static function\s*\(\s*\)\s*:\s*bool\s*\{\s*return current_user_can\(\s*'manage_options'\s*\);\s*\}/",
			$src
		);
	}

	public function test_input_schema_requires_role_and_display_name(): void {
		$src = $this->sources['create_role'];
		$this->assertStringContainsString( "'role'", $src );
		$this->assertStringContainsString( "'display_name'", $src );
		$this->assertStringContainsString( "'clone_from'", $src );
		$this->assertStringContainsString(
			"'required'             => array( 'role', 'display_name' )",
			$src
		);
	}

	public function test_annotations_declare_destructive_non_idempotent(): void {
		$src = $this->sources['create_role'];
		$this->assertStringContainsString( "'readonly'    => false", $src );
		$this->assertStringContainsString( "'destructive' => true", $src );
		$this->assertStringContainsString( "'idempotent'  => false", $src );
	}

	public function test_execute_sanitizes_inputs(): void {
		$src = $this->sources['create_role'];
		$this->assertStringContainsString( 'sanitize_text_field', $src );
		$this->assertMatchesRegularExpression(
			"/sanitize_text_field\(\s*\(string\)\s*\(\s*\\\$input\['role'\]/",
			$src
		);
		$this->assertMatchesRegularExpression(
			"/sanitize_text_field\(\s*\(string\)\s*\(\s*\\\$input\['display_name'\]/",
			$src
		);
	}

	public function test_execute_refuses_existing_role_with_role_exists_reason(): void {
		$src = $this->sources['create_role'];
		$this->assertStringContainsString(
			"'blocked_reason' => 'role_exists'",
			$src
		);
		$this->assertMatchesRegularExpression(
			"/null\s*!==\s*get_role\(\s*\\\$role_slug\s*\)/",
			$src,
			'Guard must check get_role() before add_role().'
		);
	}

	public function test_execute_refuses_missing_clone_source(): void {
		$src = $this->sources['create_role'];
		$this->assertStringContainsString(
			"'blocked_reason' => 'clone_source_not_found'",
			$src
		);
	}

	public function test_execute_calls_add_role_with_capabilities(): void {
		$src = $this->sources['create_role'];
		$this->assertMatchesRegularExpression(
			"/add_role\(\s*\\\$role_slug,\s*\\\$display_name,\s*\\\$capabilities\s*\)/",
			$src,
			'Must call add_role( slug, display_name, capabilities ).'
		);
	}

	public function test_execute_returns_capabilities_map_on_success(): void {
		$src = $this->sources['create_role'];
		$this->assertStringContainsString( '(object) $new_role->capabilities', $src );
	}

	public function test_bootstrap_instantiates_create_role(): void {
		$src = $this->sources['bootstrap'];
		$this->assertStringContainsString(
			'new Users\\Create_Role()',
			$src
		);
	}
}
