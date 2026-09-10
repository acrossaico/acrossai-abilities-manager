<?php
/**
 * Structural tests for the Feature 062 Add_User_Capability ability.
 *
 * Covers the users/add-user-capability ability under
 * includes/Abilities/Users/Add_User_Capability.php plus bootstrap wiring.
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
 * Class Test_Add_User_Capability.
 */
class Test_Add_User_Capability extends WP_UnitTestCase {

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
			'add_user_cap' => (string) file_get_contents( $plugin_root . '/includes/Abilities/Users/Add_User_Capability.php' ),
			'bootstrap'    => (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' ),
		);
	}

	public function test_class_extends_ability_definition(): void {
		$src = $this->sources['add_user_cap'];
		$this->assertStringContainsString(
			'namespace AcrossAI_Abilities_Manager\\Includes\\Abilities\\Users;',
			$src
		);
		$this->assertStringContainsString( 'class Add_User_Capability extends Ability_Definition', $src );
		$this->assertStringContainsString( "defined( 'ABSPATH' ) || exit;", $src );
	}

	public function test_ability_name_and_category(): void {
		$src = $this->sources['add_user_cap'];
		$this->assertStringContainsString( "'users/add-user-capability'", $src );
		$this->assertStringContainsString( "'acrossai-users'", $src );
	}

	public function test_permission_callback_gates_on_manage_options(): void {
		$src = $this->sources['add_user_cap'];
		$this->assertMatchesRegularExpression(
			"/'permission_callback'\s*=>\s*static function\s*\(\s*\)\s*:\s*bool\s*\{\s*return current_user_can\(\s*'manage_options'\s*\);\s*\}/",
			$src
		);
	}

	public function test_input_schema_requires_user_id_and_capability(): void {
		$src = $this->sources['add_user_cap'];
		$this->assertStringContainsString( "'user_id'", $src );
		$this->assertStringContainsString( "'capability'", $src );
		$this->assertStringContainsString(
			"'required'             => array( 'user_id', 'capability' )",
			$src
		);
	}

	public function test_annotations_declare_destructive_idempotent(): void {
		$src = $this->sources['add_user_cap'];
		$this->assertStringContainsString( "'readonly'    => false", $src );
		$this->assertStringContainsString( "'destructive' => true", $src );
		$this->assertStringContainsString( "'idempotent'  => true", $src );
	}

	public function test_execute_sanitizes_string_inputs(): void {
		$src = $this->sources['add_user_cap'];
		$this->assertMatchesRegularExpression(
			"/sanitize_text_field\(\s*\(string\)\s*\(\s*\\\$input\['capability'\]/",
			$src
		);
	}

	public function test_execute_casts_user_id_to_int(): void {
		$src = $this->sources['add_user_cap'];
		$this->assertMatchesRegularExpression(
			"/\(int\)\s*\\\$input\['user_id'\]/",
			$src
		);
	}

	public function test_execute_refuses_unknown_user_with_user_not_found_reason(): void {
		$src = $this->sources['add_user_cap'];
		$this->assertStringContainsString(
			"'blocked_reason' => 'user_not_found'",
			$src
		);
		$this->assertStringContainsString( 'get_userdata( $user_id );', $src );
	}

	public function test_execute_calls_add_cap_with_grant_default_true(): void {
		$src = $this->sources['add_user_cap'];
		$this->assertStringContainsString(
			'$user->add_cap( $capability, $grant );',
			$src,
			'Must call WP_User::add_cap( capability, grant ).'
		);
		$this->assertMatchesRegularExpression(
			"/array_key_exists\(\s*'grant',\s*\\\$input\s*\)\s*\?\s*\(bool\)\s*\\\$input\['grant'\]\s*:\s*true/",
			$src,
			'grant must default to true.'
		);
	}

	public function test_bootstrap_instantiates_add_user_capability(): void {
		$src = $this->sources['bootstrap'];
		$this->assertStringContainsString(
			'new Users\\Add_User_Capability()',
			$src
		);
	}
}
