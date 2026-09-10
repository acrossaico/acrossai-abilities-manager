<?php
/**
 * Structural tests for the Feature 062 Delete_Role ability.
 *
 * Covers the users/delete-role ability under
 * includes/Abilities/Users/Delete_Role.php plus bootstrap wiring.
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
 * Class Test_Delete_Role.
 */
class Test_Delete_Role extends WP_UnitTestCase {

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
			'delete_role' => (string) file_get_contents( $plugin_root . '/includes/Abilities/Users/Delete_Role.php' ),
			'bootstrap'   => (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' ),
		);
	}

	public function test_class_extends_ability_definition(): void {
		$src = $this->sources['delete_role'];
		$this->assertStringContainsString(
			'namespace AcrossAI_Abilities_Manager\\Includes\\Abilities\\Users;',
			$src
		);
		$this->assertStringContainsString( 'class Delete_Role extends Ability_Definition', $src );
		$this->assertStringContainsString( "defined( 'ABSPATH' ) || exit;", $src );
	}

	public function test_ability_name_and_category(): void {
		$src = $this->sources['delete_role'];
		$this->assertStringContainsString( "'users/delete-role'", $src );
		$this->assertStringContainsString( "'acrossai-users'", $src );
	}

	public function test_permission_callback_gates_on_manage_options(): void {
		$src = $this->sources['delete_role'];
		$this->assertMatchesRegularExpression(
			"/'permission_callback'\s*=>\s*static function\s*\(\s*\)\s*:\s*bool\s*\{\s*return current_user_can\(\s*'manage_options'\s*\);\s*\}/",
			$src
		);
	}

	public function test_default_roles_constant_contains_all_five_wp_core_roles(): void {
		$src           = $this->sources['delete_role'];
		$expected_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

		$this->assertStringContainsString( 'private const DEFAULT_ROLES = array(', $src );
		foreach ( $expected_roles as $role ) {
			$this->assertStringContainsString(
				"'" . $role . "'",
				$src,
				sprintf( 'DEFAULT_ROLES must include the WordPress built-in role "%s".', $role )
			);
		}
	}

	public function test_execute_refuses_default_role_with_correct_reason(): void {
		$src = $this->sources['delete_role'];
		$this->assertStringContainsString(
			"'blocked_reason' => 'default_role'",
			$src
		);
		$this->assertMatchesRegularExpression(
			"/in_array\(\s*\\\$role_slug,\s*self::DEFAULT_ROLES,\s*true\s*\)/",
			$src,
			'Default-role guard must in_array against self::DEFAULT_ROLES.'
		);
	}

	public function test_execute_refuses_role_with_users_and_reports_count(): void {
		$src = $this->sources['delete_role'];
		$this->assertStringContainsString(
			"'blocked_reason' => 'role_has_users'",
			$src
		);
		$this->assertStringContainsString(
			"'user_count'     => \$count",
			$src,
			'user_count field must echo the number of users holding the role.'
		);
		$this->assertMatchesRegularExpression(
			"/get_users\(\s*array\(\s*'role'\s*=>\s*\\\$role_slug,\s*'fields'\s*=>\s*'ID',?\s*\)\s*\)/",
			$src,
			'Must probe holders via get_users( role, fields=>ID ).'
		);
	}

	public function test_execute_refuses_nonexistent_role(): void {
		$src = $this->sources['delete_role'];
		$this->assertStringContainsString(
			"'blocked_reason' => 'role_not_found'",
			$src
		);
	}

	public function test_execute_calls_remove_role_on_success(): void {
		$src = $this->sources['delete_role'];
		$this->assertStringContainsString( 'remove_role( $role_slug );', $src );
	}

	public function test_bootstrap_instantiates_delete_role(): void {
		$src = $this->sources['bootstrap'];
		$this->assertStringContainsString(
			'new Users\\Delete_Role()',
			$src
		);
	}
}
