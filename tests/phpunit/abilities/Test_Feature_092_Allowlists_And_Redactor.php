<?php
/**
 * Cross-cutting structural tests for Feature 092 — file-manager path
 * allowlists (read + write) + configurable secret redactor.
 *
 * Verifies (a) both utility classes exist and expose the expected surface,
 * (b) every write-capable ability wires Path_Allowlist_Guard::blocked_write_response(),
 * (c) Copy_File + Move_File call it TWICE, (d) Read_File no longer contains
 * the protected_read guard block and now calls both the read-allowlist guard
 * and Secret_Redactor, (e) Read_Debug_Log similarly, (f) the tab registrar
 * class exists and hooks the acrossai_settings_tabs filter, (g) the REST
 * controller declares the six expected routes, (h) the activator seeds all
 * three options.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Feature_092_Allowlists_And_Redactor.
 */
class Test_Feature_092_Allowlists_And_Redactor extends WP_UnitTestCase {

	/** @var string */
	private string $plugin_root = '';

	/**
	 * Ability files that gain the WRITE allowlist guard.
	 *
	 * @var array<string,string>
	 */
	private const WRITE_ABILITIES = array(
		'create_file'      => 'includes/Abilities/FileManager/Create_File.php',
		'edit_file'        => 'includes/Abilities/FileManager/Edit_File.php',
		'delete_file'      => 'includes/Abilities/FileManager/Delete_File.php',
		'copy_file'        => 'includes/Abilities/FileManager/Copy_File.php',
		'move_file'        => 'includes/Abilities/FileManager/Move_File.php',
		'append_file'      => 'includes/Abilities/FileManager/Append_File.php',
		'create_directory' => 'includes/Abilities/FileManager/Create_Directory.php',
		'delete_directory' => 'includes/Abilities/FileManager/Delete_Directory.php',
	);

	protected function setUp(): void {
		parent::setUp();
		$this->plugin_root = dirname( __DIR__, 3 );
	}

	private function read( string $rel ): string {
		return (string) file_get_contents( $this->plugin_root . '/' . $rel );
	}

	/* -------------------------------------------------------------------- */
	/* Path_Allowlist_Guard utility                                          */
	/* -------------------------------------------------------------------- */

	public function test_path_allowlist_guard_file_exists(): void {
		$this->assertFileExists( $this->plugin_root . '/includes/Abilities/Utilities/Path_Allowlist_Guard.php' );
	}

	public function test_path_allowlist_guard_declares_option_constants(): void {
		$src = $this->read( 'includes/Abilities/Utilities/Path_Allowlist_Guard.php' );
		$this->assertStringContainsString( "public const OPTION_WRITE = 'acrossai_file_manager_write_allowlist'", $src );
		$this->assertStringContainsString( "public const OPTION_READ = 'acrossai_file_manager_read_allowlist'", $src );
	}

	public function test_path_allowlist_guard_declares_expected_methods(): void {
		$src = $this->read( 'includes/Abilities/Utilities/Path_Allowlist_Guard.php' );
		foreach (
			array(
				'get_write_paths',
				'set_write_paths',
				'get_read_paths',
				'set_read_paths',
				'check_write',
				'check_read',
				'blocked_write_response',
				'blocked_read_response',
			) as $method
		) {
			$this->assertStringContainsString( "function $method(", $src, "Method $method must exist." );
		}
	}

	public function test_path_allowlist_guard_default_write_is_wp_content(): void {
		$src = $this->read( 'includes/Abilities/Utilities/Path_Allowlist_Guard.php' );
		$this->assertMatchesRegularExpression(
			"/DEFAULT_WRITE_ALLOWLIST\s*=\s*array\(\s*'wp-content'\s*\)/",
			$src
		);
	}

	public function test_path_allowlist_guard_default_read_is_unrestricted(): void {
		$src = $this->read( 'includes/Abilities/Utilities/Path_Allowlist_Guard.php' );
		$this->assertMatchesRegularExpression(
			"/DEFAULT_READ_ALLOWLIST\s*=\s*array\(\s*\)/",
			$src
		);
	}

	public function test_path_allowlist_guard_uses_blocked_reason_strings(): void {
		$src = $this->read( 'includes/Abilities/Utilities/Path_Allowlist_Guard.php' );
		$this->assertStringContainsString( "'path_not_allowed_for_write'", $src );
		$this->assertStringContainsString( "'path_not_allowed_for_read'", $src );
	}

	/* -------------------------------------------------------------------- */
	/* Secret_Redactor utility                                               */
	/* -------------------------------------------------------------------- */

	public function test_secret_redactor_file_exists(): void {
		$this->assertFileExists( $this->plugin_root . '/includes/Abilities/Utilities/Secret_Redactor.php' );
	}

	public function test_secret_redactor_declares_expected_methods(): void {
		$src = $this->read( 'includes/Abilities/Utilities/Secret_Redactor.php' );
		foreach (
			array( 'scrub', 'get_config', 'set_config', 'available_patterns', 'available_connector_sources', 'default_config' ) as $method
		) {
			$this->assertStringContainsString( "function $method(", $src, "Method $method must exist." );
		}
	}

	public function test_secret_redactor_wraps_option_and_token_constants(): void {
		$src = $this->read( 'includes/Abilities/Utilities/Secret_Redactor.php' );
		$this->assertStringContainsString( "public const OPTION = 'acrossai_file_manager_redaction_config'", $src );
		$this->assertStringContainsString( "public const TOKEN = '***REDACTED***'", $src );
	}

	public function test_secret_redactor_ships_only_wp_credentials_built_in_pattern(): void {
		// Feature 092 refinement: third-party API-key regexes were dropped
		// in favour of "wp_credentials only" — user adds anything else as
		// custom literals. Two things must be true: (a) the default_config
		// exposes exactly one pattern id, (b) it is wp_credentials.
		$src = $this->read( 'includes/Abilities/Utilities/Secret_Redactor.php' );
		$this->assertStringContainsString( "'wp_credentials' => true", $src );
		// The dropped IDs must NOT appear as default_config keys.
		foreach ( array( 'stripe', 'aws_access_key', 'openai_key', 'anthropic_key', 'github_key', 'sendgrid_key', 'jwt' ) as $dropped ) {
			$this->assertStringNotContainsString(
				"'$dropped' => true",
				$src,
				"Feature 092 refinement removed the $dropped built-in pattern."
			);
			$this->assertStringNotContainsString(
				"'$dropped' => false",
				$src,
				"Feature 092 refinement removed the $dropped built-in pattern."
			);
		}
	}

	public function test_secret_redactor_wp_credentials_covers_eleven_constants(): void {
		$src = $this->read( 'includes/Abilities/Utilities/Secret_Redactor.php' );
		foreach (
			array(
				'DB_PASSWORD',
				'DB_USER',
				'AUTH_KEY',
				'SECURE_AUTH_KEY',
				'LOGGED_IN_KEY',
				'NONCE_KEY',
				'AUTH_SALT',
				'SECURE_AUTH_SALT',
				'LOGGED_IN_SALT',
				'NONCE_SALT',
				'SECRET_KEY',
			) as $const
		) {
			$this->assertStringContainsString( "'$const'", $src, "WP_SECRET_CONSTANTS must include $const." );
		}
	}

	public function test_secret_redactor_declares_ai_connector_option_map(): void {
		// Feature 092 refinement: the redactor auto-reads the WordPress AI
		// plugin's connectors_ai_<provider>_api_key options and scrubs any
		// non-empty value transparently.
		$src = $this->read( 'includes/Abilities/Utilities/Secret_Redactor.php' );
		$this->assertStringContainsString( 'private const AI_CONNECTOR_OPTIONS', $src );
		$this->assertStringContainsString( "'openai'    => 'connectors_ai_openai_api_key'", $src );
		$this->assertStringContainsString( "'anthropic' => 'connectors_ai_anthropic_api_key'", $src );
		$this->assertStringContainsString( "'google'    => 'connectors_ai_google_api_key'", $src );
	}

	public function test_secret_redactor_scrub_reads_connector_options(): void {
		$src = $this->read( 'includes/Abilities/Utilities/Secret_Redactor.php' );
		$this->assertStringContainsString( 'collect_connector_key_values(', $src );
		$this->assertStringContainsString( 'get_option( $option_name', $src );
	}

	/* -------------------------------------------------------------------- */
	/* Every write ability wires blocked_write_response                      */
	/* -------------------------------------------------------------------- */

	/**
	 * @dataProvider provide_write_abilities
	 */
	public function test_write_ability_calls_blocked_write_response( string $slot, string $rel ): void {
		$src = $this->read( $rel );
		$this->assertStringContainsString(
			'Path_Allowlist_Guard::blocked_write_response(',
			$src,
			"Write ability [$slot] must call Path_Allowlist_Guard::blocked_write_response()."
		);
	}

	public static function provide_write_abilities(): array {
		$data = array();
		foreach ( self::WRITE_ABILITIES as $slot => $rel ) {
			$data[ $slot ] = array( $slot, $rel );
		}
		return $data;
	}

	public function test_copy_file_calls_blocked_write_response_twice(): void {
		$src   = $this->read( self::WRITE_ABILITIES['copy_file'] );
		$count = substr_count( $src, 'Path_Allowlist_Guard::blocked_write_response(' );
		$this->assertGreaterThanOrEqual( 2, $count, 'Copy_File must gate both source and destination.' );
	}

	public function test_move_file_calls_blocked_write_response_twice(): void {
		$src   = $this->read( self::WRITE_ABILITIES['move_file'] );
		$count = substr_count( $src, 'Path_Allowlist_Guard::blocked_write_response(' );
		$this->assertGreaterThanOrEqual( 2, $count, 'Move_File must gate both source and destination.' );
	}

	/* -------------------------------------------------------------------- */
	/* Read abilities: allowlist + redactor + removed protected_read         */
	/* -------------------------------------------------------------------- */

	public function test_read_file_no_longer_refuses_wp_config(): void {
		$src = $this->read( 'includes/Abilities/FileManager/Read_File.php' );
		$this->assertStringNotContainsString( 'private const PROTECTED_FILES', $src );
		$this->assertStringNotContainsString( "'blocked_reason' => 'protected_read'", $src );
	}

	public function test_read_file_gates_via_read_allowlist(): void {
		$src = $this->read( 'includes/Abilities/FileManager/Read_File.php' );
		$this->assertStringContainsString( 'Path_Allowlist_Guard::blocked_read_response(', $src );
	}

	public function test_read_file_scrubs_content_via_redactor(): void {
		$src = $this->read( 'includes/Abilities/FileManager/Read_File.php' );
		$this->assertStringContainsString( 'Secret_Redactor::scrub(', $src );
		$this->assertStringContainsString( "'redacted'", $src );
		$this->assertStringContainsString( "'redaction_count'", $src );
	}

	public function test_read_debug_log_gates_via_read_allowlist(): void {
		$src = $this->read( 'includes/Abilities/FileManager/Read_Debug_Log.php' );
		$this->assertStringContainsString( 'Path_Allowlist_Guard::blocked_read_response(', $src );
	}

	public function test_read_debug_log_scrubs_content_via_redactor(): void {
		$src = $this->read( 'includes/Abilities/FileManager/Read_Debug_Log.php' );
		$this->assertStringContainsString( 'Secret_Redactor::scrub(', $src );
	}

	/* -------------------------------------------------------------------- */
	/* Tab registrar + REST controller                                       */
	/* -------------------------------------------------------------------- */

	public function test_settings_tab_class_exists(): void {
		$this->assertFileExists( $this->plugin_root . '/admin/Partials/File_Manager_Settings_Menu.php' );
	}

	public function test_settings_tab_hooks_acrossai_settings_tabs_filter(): void {
		$src = $this->read( 'admin/Partials/File_Manager_Settings_Menu.php' );
		$this->assertStringContainsString( "public const TAB_SLUG = 'file-manager'", $src );
		$this->assertStringContainsString( "'label'    => __( 'File Manager', 'acrossai-abilities-manager' )", $src );
	}

	public function test_settings_tab_wired_from_main_php(): void {
		$src = $this->read( 'includes/Main.php' );
		$this->assertStringContainsString( 'File_Manager_Settings_Menu::instance()', $src );
		$this->assertStringContainsString( "add_filter( 'acrossai_settings_tabs', \$file_manager_settings, 'register_tab' )", $src );
	}

	public function test_rest_controller_file_exists(): void {
		$this->assertFileExists( $this->plugin_root . '/includes/Abilities/Rest/File_Manager_Settings_Controller.php' );
	}

	public function test_rest_controller_declares_six_routes(): void {
		$src = $this->read( 'includes/Abilities/Rest/File_Manager_Settings_Controller.php' );
		// The controller builds route paths as '/' . self::REST_BASE . '/<suffix>'
		// so the literal '/file-manager-settings/<suffix>' string won't be
		// present verbatim — assert on the three route suffixes and the base.
		$this->assertStringContainsString( "public const REST_BASE = 'file-manager-settings'", $src );
		foreach (
			array( '/write-allowlist', '/read-allowlist', '/redaction' ) as $suffix
		) {
			$this->assertStringContainsString(
				"self::REST_BASE . '" . $suffix . "'",
				$src,
				"REST controller must register a route suffix $suffix."
			);
		}
		// Each route registers both READABLE and CREATABLE methods.
		$this->assertMatchesRegularExpression( '/WP_REST_Server::READABLE/', $src );
		$this->assertMatchesRegularExpression( '/WP_REST_Server::CREATABLE/', $src );
	}

	public function test_rest_controller_wired_from_main_php(): void {
		$src = $this->read( 'includes/Main.php' );
		$this->assertStringContainsString( 'File_Manager_Settings_Controller::instance()', $src );
		$this->assertStringContainsString( "add_action( 'rest_api_init', \$file_manager_rest, 'register_routes' )", $src );
	}

	/* -------------------------------------------------------------------- */
	/* Activator                                                             */
	/* -------------------------------------------------------------------- */

	public function test_activator_seeds_all_three_options(): void {
		$src = $this->read( 'includes/AcrossAI_Activator.php' );
		$this->assertStringContainsString( 'Path_Allowlist_Guard::OPTION_WRITE', $src );
		$this->assertStringContainsString( 'Path_Allowlist_Guard::OPTION_READ', $src );
		$this->assertStringContainsString( 'Secret_Redactor::OPTION', $src );
		$this->assertStringContainsString( 'Path_Allowlist_Guard::DEFAULT_WRITE_ALLOWLIST', $src );
		$this->assertStringContainsString( 'Path_Allowlist_Guard::DEFAULT_READ_ALLOWLIST', $src );
		$this->assertStringContainsString( 'Secret_Redactor::default_config()', $src );
	}
}
