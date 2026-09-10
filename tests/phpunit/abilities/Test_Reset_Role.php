<?php
/**
 * Structural tests for the Feature 062 Reset_Role ability.
 *
 * Covers the users/reset-role ability under
 * includes/Abilities/Users/Reset_Role.php plus bootstrap wiring.
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
 * Class Test_Reset_Role.
 */
class Test_Reset_Role extends WP_UnitTestCase {

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
			'reset_role' => (string) file_get_contents( $plugin_root . '/includes/Abilities/Users/Reset_Role.php' ),
			'bootstrap'  => (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' ),
		);
	}

	public function test_class_extends_ability_definition(): void {
		$src = $this->sources['reset_role'];
		$this->assertStringContainsString(
			'namespace AcrossAI_Abilities_Manager\\Includes\\Abilities\\Users;',
			$src
		);
		$this->assertStringContainsString( 'class Reset_Role extends Ability_Definition', $src );
		$this->assertStringContainsString( "defined( 'ABSPATH' ) || exit;", $src );
	}

	public function test_ability_name_and_category(): void {
		$src = $this->sources['reset_role'];
		$this->assertStringContainsString( "'users/reset-role'", $src );
		$this->assertStringContainsString( "'acrossai-users'", $src );
	}

	public function test_permission_callback_gates_on_manage_options(): void {
		$src = $this->sources['reset_role'];
		$this->assertMatchesRegularExpression(
			"/'permission_callback'\s*=>\s*static function\s*\(\s*\)\s*:\s*bool\s*\{\s*return current_user_can\(\s*'manage_options'\s*\);\s*\}/",
			$src
		);
	}

	public function test_input_schema_enumerates_the_five_default_roles(): void {
		$src = $this->sources['reset_role'];
		$this->assertMatchesRegularExpression(
			"/'enum'\s*=>\s*array\(\s*'administrator',\s*'editor',\s*'author',\s*'contributor',\s*'subscriber'\s*\)/",
			$src,
			'input_schema.role.enum must enumerate the five WP-core built-in roles.'
		);
	}

	public function test_resettable_roles_constant_contains_all_five(): void {
		$src            = $this->sources['reset_role'];
		$expected_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

		$this->assertStringContainsString( 'private const RESETTABLE_ROLES = array(', $src );
		foreach ( $expected_roles as $role ) {
			$this->assertStringContainsString(
				"'" . $role . "'",
				$src,
				sprintf( 'RESETTABLE_ROLES must include the WordPress built-in role "%s".', $role )
			);
		}
	}

	public function test_execute_refuses_non_default_role_with_correct_reason(): void {
		$src = $this->sources['reset_role'];
		$this->assertStringContainsString(
			"'blocked_reason' => 'not_default_role'",
			$src
		);
		$this->assertMatchesRegularExpression(
			"/!\s*in_array\(\s*\\\$role_slug,\s*self::RESETTABLE_ROLES,\s*true\s*\)/",
			$src,
			'Guard must be a negated in_array against self::RESETTABLE_ROLES.'
		);
	}

	public function test_execute_removes_then_repopulates_role(): void {
		$src = $this->sources['reset_role'];
		$this->assertStringContainsString( 'remove_role( $role_slug );', $src );
		$this->assertStringContainsString(
			"require_once ABSPATH . 'wp-admin/includes/schema.php';",
			$src,
			'Must require WP core schema.php before invoking populate_roles().'
		);
		$this->assertStringContainsString( 'populate_roles();', $src );
	}

	public function test_execute_returns_restored_capabilities(): void {
		$src = $this->sources['reset_role'];
		$this->assertStringContainsString( "'restored_capabilities'", $src );
	}

	public function test_bootstrap_instantiates_reset_role(): void {
		$src = $this->sources['bootstrap'];
		$this->assertStringContainsString(
			'new Users\\Reset_Role()',
			$src
		);
	}
}
