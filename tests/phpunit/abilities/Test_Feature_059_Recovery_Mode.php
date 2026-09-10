<?php
/**
 * Structural tests for the Feature 059 Recovery Mode abilities.
 *
 * Covers all 7 new abilities under includes/Abilities/Recovery/:
 *   - Get_Recovery_Mode_Status
 *   - List_Paused_Plugins
 *   - List_Paused_Themes
 *   - Get_Recovery_Exit_Url
 *   - Unpause_Plugin
 *   - Unpause_Theme
 *   - List_Recent_Fatal_Errors
 * Plus: Category_Registrar and Bootstrap wiring.
 *
 * Source-inspection only, mirroring Test_Feature_057_Core_Reinstall — the
 * suite's established pattern for absorbed-tier abilities (fixture-free).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.17
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Feature_059_Recovery_Mode.
 */
class Test_Feature_059_Recovery_Mode extends WP_UnitTestCase {

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
			'category_registrar'      => (string) file_get_contents( $plugin_root . '/includes/Abilities/Recovery/Category_Registrar.php' ),
			'get_status'              => (string) file_get_contents( $plugin_root . '/includes/Abilities/Recovery/Get_Recovery_Mode_Status.php' ),
			'list_paused_plugins'     => (string) file_get_contents( $plugin_root . '/includes/Abilities/Recovery/List_Paused_Plugins.php' ),
			'list_paused_themes'      => (string) file_get_contents( $plugin_root . '/includes/Abilities/Recovery/List_Paused_Themes.php' ),
			'get_exit_url'            => (string) file_get_contents( $plugin_root . '/includes/Abilities/Recovery/Get_Recovery_Exit_Url.php' ),
			'unpause_plugin'          => (string) file_get_contents( $plugin_root . '/includes/Abilities/Recovery/Unpause_Plugin.php' ),
			'unpause_theme'           => (string) file_get_contents( $plugin_root . '/includes/Abilities/Recovery/Unpause_Theme.php' ),
			'list_recent_fatal'       => (string) file_get_contents( $plugin_root . '/includes/Abilities/Recovery/List_Recent_Fatal_Errors.php' ),
			'bootstrap'               => (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' ),
			'activate_plugin'         => (string) file_get_contents( $plugin_root . '/includes/Abilities/Plugins/Activate_Plugin.php' ),
			'deactivate_plugin'       => (string) file_get_contents( $plugin_root . '/includes/Abilities/Plugins/Deactivate_Plugin.php' ),
			'activate_theme'          => (string) file_get_contents( $plugin_root . '/includes/Abilities/Themes/Activate_Theme.php' ),
		);
	}

	// =========================================================================
	// Category_Registrar
	// =========================================================================

	public function test_category_registrar_uses_recovery_category_slug(): void {
		$src = $this->sources['category_registrar'];
		$this->assertStringContainsString(
			"'acrossai-recovery'",
			$src,
			'Recovery category slug must be acrossai-recovery.'
		);
		$this->assertStringContainsString( 'wp_register_ability_category', $src );
		$this->assertStringContainsString( 'final class Category_Registrar', $src );
		$this->assertStringContainsString( 'public static function instance(): self', $src );
	}

	// =========================================================================
	// Common scaffolding across every ability
	// =========================================================================

	/**
	 * Data provider for per-ability slug + expected annotations.
	 *
	 * @return array<string,array{string,string,bool,bool,bool}>
	 */
	public static function ability_provider(): array {
		return array(
			//                        source-key,             slug,                                    readonly, destructive, idempotent
			'get_status'          => array( 'get_status',          'recovery/get-recovery-mode-status',   true,  false, true ),
			'list_paused_plugins' => array( 'list_paused_plugins', 'recovery/list-paused-plugins',        true,  false, true ),
			'list_paused_themes'  => array( 'list_paused_themes',  'recovery/list-paused-themes',         true,  false, true ),
			'get_exit_url'        => array( 'get_exit_url',        'recovery/get-recovery-exit-url',      true,  false, true ),
			'unpause_plugin'      => array( 'unpause_plugin',      'recovery/unpause-plugin',             false, true,  true ),
			'unpause_theme'       => array( 'unpause_theme',       'recovery/unpause-theme',              false, true,  true ),
			'list_recent_fatal'   => array( 'list_recent_fatal',   'recovery/list-recent-fatal-errors',   true,  false, true ),
		);
	}

	/**
	 * @dataProvider ability_provider
	 */
	public function test_ability_has_correct_slug_category_and_scaffolding(
		string $src_key,
		string $expected_slug,
		bool $readonly,
		bool $destructive,
		bool $idempotent
	): void {
		$src = $this->sources[ $src_key ];

		$this->assertStringContainsString( 'extends Ability_Definition', $src, "$src_key must extend Ability_Definition" );
		$this->assertStringContainsString( "'{$expected_slug}'", $src, "$src_key must register slug $expected_slug" );
		$this->assertStringContainsString(
			"'acrossai-recovery'",
			$src,
			"$src_key must point at the Recovery category slug"
		);
		$this->assertStringContainsString(
			"current_user_can( 'manage_options' )",
			$src,
			"$src_key permission_callback must gate on manage_options"
		);

		// Annotations: readonly / destructive / idempotent booleans.
		$this->assertStringContainsString(
			"'readonly'    => " . ( $readonly ? 'true' : 'false' ),
			$src,
			"$src_key annotation 'readonly' must be " . var_export( $readonly, true )
		);
		$this->assertStringContainsString(
			"'destructive' => " . ( $destructive ? 'true' : 'false' ),
			$src,
			"$src_key annotation 'destructive' must be " . var_export( $destructive, true )
		);
		$this->assertStringContainsString(
			"'idempotent'  => " . ( $idempotent ? 'true' : 'false' ),
			$src,
			"$src_key annotation 'idempotent' must be " . var_export( $idempotent, true )
		);

		// meta.acrossai sub_group.
		$this->assertStringContainsString(
			"'sub_group'       => 'recovery'",
			$src,
			"$src_key meta.acrossai.sub_group must be 'recovery'"
		);
	}

	// =========================================================================
	// Write-action guards
	// =========================================================================

	public function test_unpause_plugin_uses_file_mods_guard_and_plugin_helpers(): void {
		$src = $this->sources['unpause_plugin'];
		$this->assertStringContainsString( 'File_Mods_Guard::blocked_response', $src );
		$this->assertStringContainsString( 'Plugin_Helpers::resolve_plugin', $src );
		$this->assertStringContainsString( 'wp_paused_plugins()', $src );
		$this->assertStringContainsString( '->delete(', $src, 'Unpause_Plugin must call delete() on the paused-extensions storage' );
	}

	public function test_unpause_theme_uses_file_mods_guard_and_theme_helpers(): void {
		$src = $this->sources['unpause_theme'];
		$this->assertStringContainsString( 'File_Mods_Guard::blocked_response', $src );
		$this->assertStringContainsString( 'Theme_Helpers::resolve_theme', $src );
		$this->assertStringContainsString( 'wp_paused_themes()', $src );
		$this->assertStringContainsString( '->delete(', $src, 'Unpause_Theme must call delete() on the paused-extensions storage' );
	}

	// =========================================================================
	// Read-side call sites
	// =========================================================================

	public function test_get_status_calls_wp_is_recovery_mode(): void {
		$src = $this->sources['get_status'];
		$this->assertStringContainsString( 'wp_is_recovery_mode', $src );
		$this->assertStringContainsString( 'wp_paused_plugins', $src );
		$this->assertStringContainsString( 'wp_paused_themes', $src );
		$this->assertStringContainsString( 'wp_is_fatal_error_handler_enabled', $src );
	}

	public function test_list_paused_plugins_calls_get_all(): void {
		$src = $this->sources['list_paused_plugins'];
		$this->assertStringContainsString( 'wp_paused_plugins()->get_all()', $src );
	}

	public function test_list_paused_themes_calls_get_all(): void {
		$src = $this->sources['list_paused_themes'];
		$this->assertStringContainsString( 'wp_paused_themes()->get_all()', $src );
	}

	public function test_get_exit_url_uses_nonce_and_exit_action(): void {
		$src = $this->sources['get_exit_url'];
		$this->assertStringContainsString( 'wp_nonce_url', $src );
		$this->assertStringContainsString( 'EXIT_ACTION', $src );
		$this->assertStringContainsString( 'wp_is_recovery_mode', $src );
	}

	// =========================================================================
	// Fatal-error log filter — safety + regex shape
	// =========================================================================

	public function test_list_recent_fatal_errors_streams_with_tail_cap(): void {
		$src = $this->sources['list_recent_fatal'];
		$this->assertStringContainsString( 'MAX_SCAN_BYTES', $src, 'must define a tail-scan cap' );
		$this->assertStringContainsString( 'ERROR_LINE_REGEX', $src, 'must define an error-line regex constant' );
		$this->assertStringContainsString( 'fopen(', $src, 'must stream the file (fopen), not slurp with file_get_contents' );
		$this->assertStringContainsString( 'fgets(', $src, 'must read line-by-line with fgets' );
		$this->assertStringContainsString( 'PHP Fatal error', $src, 'regex must match "PHP Fatal error" prose (present in the regex comment)' );
	}

	public function test_list_recent_fatal_errors_honors_debug_log_guard(): void {
		$src = $this->sources['list_recent_fatal'];
		$this->assertStringContainsString( "WP_CONTENT_DIR . '/debug.log'", $src );
		$this->assertStringContainsString( 'WP_DEBUG_LOG', $src );
		$this->assertStringContainsString( 'WP_DEBUG', $src );
	}

	// =========================================================================
	// Bootstrap wiring
	// =========================================================================

	public function test_bootstrap_registers_recovery_category(): void {
		$src = $this->sources['bootstrap'];
		$this->assertStringContainsString(
			'Recovery\\Category_Registrar::instance()',
			$src,
			'Bootstrap must register the Recovery Category_Registrar on wp_abilities_api_categories_init'
		);
	}

	public function test_bootstrap_instantiates_all_seven_recovery_abilities(): void {
		$src = $this->sources['bootstrap'];
		foreach (
			array(
				'new Recovery\\Get_Recovery_Mode_Status()',
				'new Recovery\\List_Paused_Plugins()',
				'new Recovery\\List_Paused_Themes()',
				'new Recovery\\Get_Recovery_Exit_Url()',
				'new Recovery\\Unpause_Plugin()',
				'new Recovery\\Unpause_Theme()',
				'new Recovery\\List_Recent_Fatal_Errors()',
			) as $expected
		) {
			$this->assertStringContainsString(
				$expected,
				$src,
				"Bootstrap must instantiate $expected in register_abilities()"
			);
		}
	}

	// =========================================================================
	// Description tweaks on existing plugin/theme abilities
	// =========================================================================

	public function test_activate_plugin_description_notes_recovery_compatibility(): void {
		$src = $this->sources['activate_plugin'];
		$this->assertStringContainsString( 'Works in recovery mode', $src );
	}

	public function test_deactivate_plugin_description_notes_recovery_compatibility(): void {
		$src = $this->sources['deactivate_plugin'];
		$this->assertStringContainsString( 'Works in recovery mode', $src );
	}

	public function test_activate_theme_description_notes_recovery_compatibility(): void {
		$src = $this->sources['activate_theme'];
		$this->assertStringContainsString( 'Works in recovery mode', $src );
	}
}
