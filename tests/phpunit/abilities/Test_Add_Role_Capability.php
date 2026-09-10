<?php
/**
 * Structural tests for the Feature 062 Add_Role_Capability ability.
 *
 * Covers the users/add-role-capability ability under
 * includes/Abilities/Users/Add_Role_Capability.php plus its bootstrap wiring.
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
 * Class Test_Add_Role_Capability.
 */
class Test_Add_Role_Capability extends WP_UnitTestCase {

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
			'add_role_cap' => (string) file_get_contents( $plugin_root . '/includes/Abilities/Users/Add_Role_Capability.php' ),
			'bootstrap'    => (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' ),
		);
	}

	// =========================================================================
	// Class scaffolding
	// =========================================================================

	public function test_class_extends_ability_definition(): void {
		$src = $this->sources['add_role_cap'];
		$this->assertStringContainsString(
			'namespace AcrossAI_Abilities_Manager\\Includes\\Abilities\\Users;',
			$src
		);
		$this->assertStringContainsString( 'class Add_Role_Capability extends Ability_Definition', $src );
		$this->assertStringContainsString(
			"defined( 'ABSPATH' ) || exit;",
			$src
		);
	}

	// =========================================================================
	// Ability spec
	// =========================================================================

	public function test_ability_name_and_category(): void {
		$src = $this->sources['add_role_cap'];
		$this->assertStringContainsString(
			"'users/add-role-capability'",
			$src,
			'Ability name must be users/add-role-capability.'
		);
		$this->assertStringContainsString(
			"'acrossai-users'",
			$src,
			'Category slug must be acrossai-users.'
		);
	}

	public function test_permission_callback_gates_on_manage_options(): void {
		$src = $this->sources['add_role_cap'];
		$this->assertMatchesRegularExpression(
			"/'permission_callback'\s*=>\s*static function\s*\(\s*\)\s*:\s*bool\s*\{\s*return current_user_can\(\s*'manage_options'\s*\);\s*\}/",
			$src,
			'permission_callback must be the literal static-fn returning current_user_can(manage_options).'
		);
	}

	public function test_input_schema_requires_role_and_capability(): void {
		$src = $this->sources['add_role_cap'];
		$this->assertStringContainsString( "'role'", $src );
		$this->assertStringContainsString( "'capability'", $src );
		$this->assertStringContainsString( "'grant'", $src );
		$this->assertStringContainsString(
			"'required'             => array( 'role', 'capability' )",
			$src,
			'input_schema.required must include role and capability.'
		);
		$this->assertStringContainsString(
			"'additionalProperties' => false",
			$src
		);
	}

	public function test_annotations_declare_destructive_idempotent(): void {
		$src = $this->sources['add_role_cap'];
		$this->assertStringContainsString( "'readonly'    => false", $src );
		$this->assertStringContainsString( "'destructive' => true", $src );
		$this->assertStringContainsString( "'idempotent'  => true", $src );
	}

	// =========================================================================
	// Guardrails
	// =========================================================================

	public function test_execute_sanitizes_string_inputs(): void {
		$src = $this->sources['add_role_cap'];
		$this->assertMatchesRegularExpression(
			"/sanitize_text_field\(\s*\(string\)\s*\(\s*\\\$input\['role'\]/",
			$src,
			'execute() must sanitize_text_field the role input.'
		);
		$this->assertMatchesRegularExpression(
			"/sanitize_text_field\(\s*\(string\)\s*\(\s*\\\$input\['capability'\]/",
			$src,
			'execute() must sanitize_text_field the capability input.'
		);
	}

	public function test_execute_refuses_missing_role_with_role_not_found_reason(): void {
		$src = $this->sources['add_role_cap'];
		$this->assertStringContainsString(
			"'blocked_reason' => 'role_not_found'",
			$src,
			'Missing role must be refused with blocked_reason=role_not_found.'
		);
		$this->assertStringContainsString(
			'$role = get_role( $role_slug );',
			$src,
			'Must resolve role via WP core get_role().'
		);
	}

	public function test_execute_calls_add_cap_with_grant(): void {
		$src = $this->sources['add_role_cap'];
		$this->assertStringContainsString(
			'$role->add_cap( $capability, $grant );',
			$src,
			'Must call WP_Role::add_cap( capability, grant ).'
		);
	}

	public function test_grant_defaults_to_true(): void {
		$src = $this->sources['add_role_cap'];
		$this->assertMatchesRegularExpression(
			"/array_key_exists\(\s*'grant',\s*\\\$input\s*\)\s*\?\s*\(bool\)\s*\\\$input\['grant'\]\s*:\s*true/",
			$src,
			'grant must default to true when not supplied by the caller.'
		);
	}

	// =========================================================================
	// Bootstrap wiring
	// =========================================================================

	public function test_bootstrap_instantiates_add_role_capability(): void {
		$src = $this->sources['bootstrap'];
		$this->assertStringContainsString(
			'new Users\\Add_Role_Capability()',
			$src,
			'Bootstrap must instantiate Users\\Add_Role_Capability.'
		);
	}
}
