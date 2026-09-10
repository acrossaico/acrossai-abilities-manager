<?php
/**
 * Structural tests for the Feature 062 Remove_User_Capability ability.
 *
 * Covers the users/remove-user-capability ability under
 * includes/Abilities/Users/Remove_User_Capability.php including the
 * CORE_ADMIN_CAPS safety block-list, the last-admin guard, and
 * bootstrap wiring.
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
 * Class Test_Remove_User_Capability.
 */
class Test_Remove_User_Capability extends WP_UnitTestCase {

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
			'remove_user_cap' => (string) file_get_contents( $plugin_root . '/includes/Abilities/Users/Remove_User_Capability.php' ),
			'bootstrap'       => (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' ),
		);
	}

	public function test_class_extends_ability_definition(): void {
		$src = $this->sources['remove_user_cap'];
		$this->assertStringContainsString(
			'namespace AcrossAI_Abilities_Manager\\Includes\\Abilities\\Users;',
			$src
		);
		$this->assertStringContainsString( 'class Remove_User_Capability extends Ability_Definition', $src );
		$this->assertStringContainsString( "defined( 'ABSPATH' ) || exit;", $src );
	}

	public function test_ability_name_and_category(): void {
		$src = $this->sources['remove_user_cap'];
		$this->assertStringContainsString( "'users/remove-user-capability'", $src );
		$this->assertStringContainsString( "'acrossai-users'", $src );
	}

	public function test_permission_callback_gates_on_manage_options(): void {
		$src = $this->sources['remove_user_cap'];
		$this->assertMatchesRegularExpression(
			"/'permission_callback'\s*=>\s*static function\s*\(\s*\)\s*:\s*bool\s*\{\s*return current_user_can\(\s*'manage_options'\s*\);\s*\}/",
			$src
		);
	}

	public function test_input_schema_does_not_include_grant_field(): void {
		$src = $this->sources['remove_user_cap'];
		$this->assertStringNotContainsString(
			"'grant'      => array(",
			$src,
			'remove-user-capability input schema must not include the "grant" field (contract §7).'
		);
	}

	public function test_core_admin_caps_constant_defined(): void {
		$src = $this->sources['remove_user_cap'];
		$this->assertStringContainsString(
			'private const CORE_ADMIN_CAPS = array(',
			$src
		);
	}

	public function test_core_admin_caps_contains_critical_baseline_caps(): void {
		$src           = $this->sources['remove_user_cap'];
		$expected_caps = array(
			'manage_options',
			'activate_plugins',
			'delete_users',
			'edit_users',
			'edit_files',
			'edit_plugins',
			'edit_themes',
			'install_plugins',
			'install_themes',
			'update_core',
			'promote_users',
			'remove_users',
		);

		foreach ( $expected_caps as $cap ) {
			$this->assertStringContainsString(
				"'" . $cap . "'",
				$src,
				sprintf( 'CORE_ADMIN_CAPS must include the WordPress-core admin cap "%s".', $cap )
			);
		}
	}

	public function test_execute_refuses_unknown_user_with_user_not_found_reason(): void {
		$src = $this->sources['remove_user_cap'];
		$this->assertStringContainsString(
			"'blocked_reason' => 'user_not_found'",
			$src
		);
		$this->assertStringContainsString( 'get_userdata( $user_id );', $src );
	}

	public function test_execute_enforces_last_admin_guard(): void {
		$src = $this->sources['remove_user_cap'];
		$this->assertStringContainsString(
			"'blocked_reason' => 'last_admin_core_cap'",
			$src
		);
		$this->assertMatchesRegularExpression(
			"/get_users\(\s*array\(\s*'role'\s*=>\s*'administrator',\s*'fields'\s*=>\s*'ID',?\s*\)\s*\)/",
			$src,
			'Must probe admins via get_users( role=administrator, fields=ID ).'
		);
		$this->assertMatchesRegularExpression(
			"/1\s*===\s*count\(\s*\\\$admins\s*\)/",
			$src,
			'Guard must check for exactly one remaining admin.'
		);
		$this->assertMatchesRegularExpression(
			"/in_array\(\s*\\\$capability,\s*self::CORE_ADMIN_CAPS,\s*true\s*\)/",
			$src,
			'Guard must AND in_array( capability, CORE_ADMIN_CAPS ) — only WP-core admin caps trigger the block.'
		);
	}

	public function test_execute_calls_remove_cap_on_user(): void {
		$src = $this->sources['remove_user_cap'];
		$this->assertStringContainsString(
			'$user->remove_cap( $capability );',
			$src
		);
	}

	public function test_bootstrap_instantiates_remove_user_capability(): void {
		$src = $this->sources['bootstrap'];
		$this->assertStringContainsString(
			'new Users\\Remove_User_Capability()',
			$src
		);
	}
}
