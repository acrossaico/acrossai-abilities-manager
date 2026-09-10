<?php
/**
 * Structural tests for the Feature 063 file-manager/get-wp-config-constant ability.
 *
 * Verifies (a) the class scaffolding, (b) the nine hardcoded blocked
 * constants match the spec-required set exactly, (c) the block-list
 * check runs before any defined()/constant() call, and (d) sanitize_text_field
 * runs on the input.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Get_Wp_Config_Constant.
 */
class Test_Get_Wp_Config_Constant extends WP_UnitTestCase {

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
		$this->src       = (string) file_get_contents( $plugin_root . '/includes/Abilities/FileManager/Get_Wp_Config_Constant.php' );
		$this->bootstrap = (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );
	}

	public function test_extends_ability_definition_and_uses_expected_ability_name(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
		$this->assertStringContainsString( "'file-manager/get-wp-config-constant'", $this->src );
	}

	public function test_targets_the_file_manager_category(): void {
		$this->assertStringContainsString( "'acrossai-file-manager'", $this->src );
	}

	public function test_permission_callback_gates_on_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\\(\\s*'manage_options'\\s*\\)/",
			$this->src
		);
	}

	public function test_declares_the_nine_blocked_constants_verbatim(): void {
		foreach (
			array(
				'AUTH_KEY',
				'SECURE_AUTH_KEY',
				'LOGGED_IN_KEY',
				'NONCE_KEY',
				'AUTH_SALT',
				'SECURE_AUTH_SALT',
				'LOGGED_IN_SALT',
				'NONCE_SALT',
				'DB_PASSWORD',
			) as $constant
		) {
			$this->assertStringContainsString(
				"'$constant'",
				$this->src,
				"Blocked-constant list must include $constant."
			);
		}
	}

	public function test_uses_in_array_strict_match_against_blocked_constants(): void {
		$this->assertMatchesRegularExpression(
			'/in_array\\(\\s*\\$constant,\\s*self::BLOCKED_CONSTANTS,\\s*true\\s*\\)/',
			$this->src
		);
	}

	public function test_returns_sensitive_constant_blocked_reason(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'sensitive_constant'", $this->src );
	}

	public function test_sanitizes_input_via_sanitize_text_field(): void {
		$this->assertStringContainsString( 'sanitize_text_field(', $this->src );
	}

	public function test_calls_defined_and_constant_after_blocklist_check(): void {
		$this->assertStringContainsString( 'defined( $constant )', $this->src );
		$this->assertStringContainsString( 'constant( $constant )', $this->src );
	}

	public function test_bootstrap_instantiates_the_ability(): void {
		$this->assertStringContainsString( 'new FileManager\\Get_Wp_Config_Constant()', $this->bootstrap );
	}
}
