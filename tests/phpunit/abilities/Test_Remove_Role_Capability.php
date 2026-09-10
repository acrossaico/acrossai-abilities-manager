<?php
/**
 * Structural tests for the Feature 062 Remove_Role_Capability ability.
 *
 * Covers the users/remove-role-capability ability under
 * includes/Abilities/Users/Remove_Role_Capability.php including the
 * CORE_ADMIN_CAPS safety block-list and bootstrap wiring.
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
 * Class Test_Remove_Role_Capability.
 */
class Test_Remove_Role_Capability extends WP_UnitTestCase {

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
			'remove_role_cap' => (string) file_get_contents( $plugin_root . '/includes/Abilities/Users/Remove_Role_Capability.php' ),
			'bootstrap'       => (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' ),
		);
	}

	// =========================================================================
	// Class scaffolding
	// =========================================================================

	public function test_class_extends_ability_definition(): void {
		$src = $this->sources['remove_role_cap'];
		$this->assertStringContainsString(
			'namespace AcrossAI_Abilities_Manager\\Includes\\Abilities\\Users;',
			$src
		);
		$this->assertStringContainsString( 'class Remove_Role_Capability extends Ability_Definition', $src );
		$this->assertStringContainsString(
			"defined( 'ABSPATH' ) || exit;",
			$src
		);
	}

	// =========================================================================
	// Ability spec
	// =========================================================================

	public function test_ability_name_and_category(): void {
		$src = $this->sources['remove_role_cap'];
		$this->assertStringContainsString(
			"'users/remove-role-capability'",
			$src
		);
		$this->assertStringContainsString(
			"'acrossai-users'",
			$src
		);
	}

	public function test_permission_callback_gates_on_manage_options(): void {
		$src = $this->sources['remove_role_cap'];
		$this->assertMatchesRegularExpression(
			"/'permission_callback'\s*=>\s*static function\s*\(\s*\)\s*:\s*bool\s*\{\s*return current_user_can\(\s*'manage_options'\s*\);\s*\}/",
			$src
		);
	}

	public function test_annotations_declare_destructive_idempotent(): void {
		$src = $this->sources['remove_role_cap'];
		$this->assertStringContainsString( "'readonly'    => false", $src );
		$this->assertStringContainsString( "'destructive' => true", $src );
		$this->assertStringContainsString( "'idempotent'  => true", $src );
	}

	// =========================================================================
	// CORE_ADMIN_CAPS block-list — Decision 3 in research.md
	// =========================================================================

	public function test_core_admin_caps_constant_defined(): void {
		$src = $this->sources['remove_role_cap'];
		$this->assertStringContainsString(
			'private const CORE_ADMIN_CAPS = array(',
			$src,
			'CORE_ADMIN_CAPS must be a private const array on the class.'
		);
	}

	/**
	 * Anchor caps sourced from wp-admin/includes/schema.php populate_roles_*().
	 *
	 * @return void
	 */
	public function test_core_admin_caps_contains_critical_baseline_caps(): void {
		$src           = $this->sources['remove_role_cap'];
		$expected_caps = array(
			// Highest-risk lockout caps.
			'manage_options',
			'activate_plugins',
			'delete_users',
			'edit_users',
			'create_users',
			'promote_users',
			'remove_users',
			'update_core',
			// Full-file access caps.
			'edit_files',
			'edit_plugins',
			'edit_themes',
			// Plugin/theme lifecycle caps.
			'install_plugins',
			'install_themes',
			'update_plugins',
			'update_themes',
			'delete_plugins',
			'delete_themes',
			'switch_themes',
			// Content caps.
			'edit_posts',
			'edit_others_posts',
			'publish_posts',
			'edit_pages',
			'read',
		);

		foreach ( $expected_caps as $cap ) {
			$this->assertStringContainsString(
				"'" . $cap . "'",
				$src,
				sprintf( 'CORE_ADMIN_CAPS must include the WordPress-core admin cap "%s".', $cap )
			);
		}
	}

	// =========================================================================
	// Guardrails
	// =========================================================================

	public function test_execute_sanitizes_string_inputs(): void {
		$src = $this->sources['remove_role_cap'];
		$this->assertMatchesRegularExpression(
			"/sanitize_text_field\(\s*\(string\)\s*\(\s*\\\$input\['role'\]/",
			$src
		);
		$this->assertMatchesRegularExpression(
			"/sanitize_text_field\(\s*\(string\)\s*\(\s*\\\$input\['capability'\]/",
			$src
		);
	}

	public function test_execute_refuses_missing_role_with_role_not_found_reason(): void {
		$src = $this->sources['remove_role_cap'];
		$this->assertStringContainsString(
			"'blocked_reason' => 'role_not_found'",
			$src
		);
	}

	public function test_execute_refuses_core_admin_cap_with_correct_reason(): void {
		$src = $this->sources['remove_role_cap'];
		$this->assertStringContainsString(
			"'blocked_reason' => 'core_admin_cap'",
			$src
		);
		$this->assertMatchesRegularExpression(
			"/'administrator'\s*===\s*\\\$role_slug\s*&&\s*in_array\(\s*\\\$capability,\s*self::CORE_ADMIN_CAPS,\s*true\s*\)/",
			$src,
			'Guard must AND the administrator-role check with an in_array( capability, CORE_ADMIN_CAPS ) check.'
		);
	}

	public function test_execute_calls_remove_cap_on_role(): void {
		$src = $this->sources['remove_role_cap'];
		$this->assertStringContainsString(
			'$role->remove_cap( $capability );',
			$src,
			'Must call WP_Role::remove_cap().'
		);
	}

	// =========================================================================
	// Bootstrap wiring
	// =========================================================================

	public function test_bootstrap_instantiates_remove_role_capability(): void {
		$src = $this->sources['bootstrap'];
		$this->assertStringContainsString(
			'new Users\\Remove_Role_Capability()',
			$src
		);
	}
}
