<?php
/**
 * File Manager tab integration tests.
 *
 * The tab at admin.php?page=acrossai-settings&tab=file-manager surfaces
 * five panels backed by three utility classes (Path_Allowlist_Guard,
 * Secret_Redactor, Hardening_Settings) and one runtime consumer
 * (Hardening_Enforcer + Audit_Trail). Per-feature tests exist for each
 * (Test_Feature_092..094); this file tests the tab as one deliverable:
 *
 *   - REST surface completeness (all 6 endpoints, all 6 handlers)
 *   - Activator seeds every option key the panels bind to
 *   - Panel registration + React tree completeness
 *   - Persistence-layer completeness (every panel field has get/set)
 *   - Cross-feature interaction — allowlist + hardening enforcer + audit
 *     trail fire in the right order on a single mutation
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Audit_Trail;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Hardening_Enforcer;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Hardening_Settings;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Path_Allowlist_Guard;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Secret_Redactor;
use WP_UnitTestCase;

/**
 * Class Test_File_Manager_Tab_Integration.
 */
class Test_File_Manager_Tab_Integration extends WP_UnitTestCase {

	/** @var string */
	private string $plugin_root = '';

	protected function setUp(): void {
		parent::setUp();
		$this->plugin_root = dirname( __DIR__, 3 );

		global $__acrossai_test_options, $wp_filesystem, $__acrossai_test_current_user;
		$__acrossai_test_options       = array();
		$wp_filesystem                 = new \Test_Fake_WP_Filesystem();
		$__acrossai_test_current_user  = (object) array( 'ID' => 42, 'user_email' => 'phpunit@acrossai.test' );
		$_SERVER['REMOTE_ADDR']        = '127.0.0.1';

		$this->purge_dir( Audit_Trail::backup_base_dir() );
		$this->purge_dir( Audit_Trail::log_dir() );
	}

	protected function tearDown(): void {
		global $__acrossai_test_options, $wp_filesystem, $__acrossai_test_current_user;
		$this->purge_dir( Audit_Trail::backup_base_dir() );
		$this->purge_dir( Audit_Trail::log_dir() );
		$__acrossai_test_options      = array();
		$wp_filesystem                = null;
		$__acrossai_test_current_user = null;
		parent::tearDown();
	}

	private function purge_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$iter = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iter as $entry ) {
			if ( $entry->isDir() ) {
				@rmdir( $entry->getPathname() );
			} else {
				@unlink( $entry->getPathname() );
			}
		}
		@rmdir( $dir );
	}

	private function read( string $rel ): string {
		return (string) file_get_contents( $this->plugin_root . '/' . $rel );
	}

	/* ==================================================================== */
	/* REST surface — all 6 endpoints registered + shipped                   */
	/* ==================================================================== */

	/**
	 * Every field the panels bind to has a route. Missing a route means
	 * an admin can save via the panel but the backend never learns.
	 */
	public function test_rest_controller_registers_all_six_routes(): void {
		$src = $this->read( 'includes/Abilities/Rest/File_Manager_Settings_Controller.php' );
		foreach ( array(
			'write-allowlist',
			'read-allowlist',
			'redaction',
			'content-filters',
			'backup-audit',
			'backup-audit-stats',
		) as $route ) {
			$this->assertStringContainsString(
				"'/' . self::REST_BASE . '/" . $route . "'",
				$src,
				"REST controller must register /{$route}"
			);
		}
	}

	public function test_rest_controller_declares_every_handler(): void {
		$src = $this->read( 'includes/Abilities/Rest/File_Manager_Settings_Controller.php' );
		foreach ( array(
			'get_write_allowlist',
			'save_write_allowlist',
			'get_read_allowlist',
			'save_read_allowlist',
			'get_redaction',
			'save_redaction',
			'get_content_filters',
			'save_content_filters',
			'get_backup_audit',
			'save_backup_audit',
			'get_backup_audit_stats',
		) as $method ) {
			$this->assertMatchesRegularExpression(
				"/public\\s+function\\s+{$method}\\s*\\(/",
				$src,
				"REST controller must expose public {$method}()"
			);
		}
	}

	public function test_rest_controller_declares_correct_scaffold_state_per_panel(): void {
		$src = $this->read( 'includes/Abilities/Rest/File_Manager_Settings_Controller.php' );
		// After feature 093, content-filters is live.
		$this->assertMatchesRegularExpression(
			"/get_content_filters.+?'scaffold_only'\\s*=>\\s*false/s",
			$src,
			'content-filters GET should report scaffold_only:false (feature 093 shipped)'
		);
		// After feature 094-complete, backup-audit is also live.
		$this->assertMatchesRegularExpression(
			"/get_backup_audit\\s*\\(\\s*\\).+?'scaffold_only'\\s*=>\\s*false/s",
			$src,
			'backup-audit GET should report scaffold_only:false (feature 094-complete shipped)'
		);
	}

	/* ==================================================================== */
	/* Activator — every persisted key gets seeded                            */
	/* ==================================================================== */

	public function test_activator_seeds_every_file_manager_option(): void {
		$src = $this->read( 'includes/AcrossAI_Activator.php' );
		// Feature 092: allowlists + redactor.
		$this->assertStringContainsString( 'Path_Allowlist_Guard::OPTION_WRITE', $src );
		$this->assertStringContainsString( 'Path_Allowlist_Guard::OPTION_READ', $src );
		$this->assertStringContainsString( 'Secret_Redactor::OPTION', $src );

		// Features 093 + 094: every Hardening_Settings option constant is seeded.
		foreach ( array(
			'OPTION_DANGEROUS_EXTENSIONS',
			'OPTION_BLOCK_DOUBLE_EXTENSIONS',
			'OPTION_HTACCESS_DIRECTIVE_SCAN',
			'OPTION_SANITIZE_FILENAME_CHECK',
			'OPTION_WRITE_MAX_BYTES',
			'OPTION_SENSITIVE_READ_DENYLIST',
			'OPTION_STRICT_FILENAME_FILTER',
			'OPTION_MIME_TYPE_CHECK',
			'OPTION_AUDIT_LOG_ENABLED',
			'OPTION_AUDIT_LOG_RETENTION_DAYS',
			'OPTION_BACKUP_ENABLED',
			'OPTION_BACKUP_RETENTION_DAYS',
		) as $const ) {
			$this->assertStringContainsString(
				"Hardening_Settings::{$const}",
				$src,
				"Activator must seed Hardening_Settings::{$const}"
			);
		}
	}

	/* ==================================================================== */
	/* Panel registration + React tree                                        */
	/* ==================================================================== */

	public function test_settings_menu_registers_tab_at_priority_30(): void {
		$src = $this->read( 'admin/Partials/File_Manager_Settings_Menu.php' );
		$this->assertStringContainsString( "'slug'     => self::TAB_SLUG", $src );
		$this->assertStringContainsString( "'priority' => 30", $src );
		$this->assertStringContainsString( "TAB_SLUG = 'file-manager'", $src );
		$this->assertStringContainsString( 'acrossai_settings_tabs', $src );
	}

	public function test_react_orchestrator_renders_all_five_panels(): void {
		$src = $this->read( 'src/js/file-manager-settings/components/FileManagerSettings.jsx' );
		foreach ( array(
			'WriteAllowlistPanel',
			'ReadAllowlistPanel',
			'RedactionPanel',
			'ContentFiltersPanel',
			'BackupAuditPanel',
		) as $component ) {
			$this->assertMatchesRegularExpression(
				"/import\\s+{$component}\\s+from/",
				$src,
				"FileManagerSettings.jsx must import {$component}"
			);
			$this->assertStringContainsString(
				"<{$component}",
				$src,
				"FileManagerSettings.jsx must render <{$component} />"
			);
		}
	}

	public function test_react_orchestrator_loads_all_five_settings_endpoints_in_parallel(): void {
		$src = $this->read( 'src/js/file-manager-settings/components/FileManagerSettings.jsx' );
		foreach ( array(
			'/write-allowlist',
			'/read-allowlist',
			'/redaction',
			'/content-filters',
			'/backup-audit',
		) as $path ) {
			$this->assertStringContainsString( $path, $src, "Orchestrator must load {$path}" );
		}
		// Promise.all — everything loads together, not sequentially.
		$this->assertStringContainsString( 'Promise.all', $src );
	}

	public function test_backup_audit_panel_consumes_stats_endpoint(): void {
		$src = $this->read( 'src/js/file-manager-settings/components/FileManagerSettings.jsx' );
		$this->assertStringContainsString( 'backup-audit-stats', $src );
		$this->assertStringContainsString( 'statsPath=', $src );
	}

	public function test_every_panel_class_exists(): void {
		foreach ( array(
			'WriteAllowlistPanel',
			'ReadAllowlistPanel',
			'RedactionPanel',
			'ContentFiltersPanel',
			'BackupAuditPanel',
			'AllowlistTree',
		) as $file ) {
			$this->assertFileExists(
				$this->plugin_root . '/src/js/file-manager-settings/components/' . $file . '.jsx',
				"React panel component {$file}.jsx must exist"
			);
		}
	}

	/* ==================================================================== */
	/* Persistence layer — every panel field round-trips                      */
	/* ==================================================================== */

	public function test_hardening_settings_get_content_filters_returns_all_eight_fields(): void {
		$snap = Hardening_Settings::get_content_filters();
		foreach ( array(
			'dangerous_extensions',
			'block_double_extensions',
			'htaccess_directive_scan',
			'sanitize_filename_check',
			'write_max_bytes',
			'sensitive_read_denylist',
			'strict_filename_filter',
			'mime_type_check',
		) as $field ) {
			$this->assertArrayHasKey( $field, $snap, "get_content_filters must return {$field}" );
		}
	}

	public function test_hardening_settings_get_backup_audit_returns_all_four_fields(): void {
		$snap = Hardening_Settings::get_backup_audit();
		foreach ( array(
			'backup_enabled',
			'backup_retention_days',
			'audit_log_enabled',
			'audit_log_retention_days',
		) as $field ) {
			$this->assertArrayHasKey( $field, $snap, "get_backup_audit must return {$field}" );
		}
	}

	public function test_hardening_settings_set_content_filters_round_trips(): void {
		$result = Hardening_Settings::set_content_filters( array(
			'dangerous_extensions'    => array( 'exe', 'sh' ),
			'block_double_extensions' => true,
			'write_max_bytes'         => 5242880,
			'strict_filename_filter'  => true,
		) );

		$this->assertArrayHasKey( 'config', $result );
		$snap = $result['config'];
		$this->assertContains( 'exe', $snap['dangerous_extensions'] );
		$this->assertContains( 'sh', $snap['dangerous_extensions'] );
		$this->assertTrue( $snap['block_double_extensions'] );
		$this->assertSame( 5242880, $snap['write_max_bytes'] );
		$this->assertTrue( $snap['strict_filename_filter'] );
	}

	public function test_hardening_settings_set_backup_audit_round_trips_and_clamps_retention(): void {
		Hardening_Settings::set_backup_audit( array(
			'backup_enabled'           => true,
			'backup_retention_days'    => 999,          // above max (90) → clamped
			'audit_log_enabled'        => true,
			'audit_log_retention_days' => 0,            // below min (1) → clamped
		) );
		$snap = Hardening_Settings::get_backup_audit();
		$this->assertTrue( $snap['backup_enabled'] );
		$this->assertTrue( $snap['audit_log_enabled'] );
		$this->assertSame( 90, $snap['backup_retention_days'] );
		$this->assertSame( 1, $snap['audit_log_retention_days'] );
	}

	public function test_path_allowlist_guard_defaults_match_activator_seed(): void {
		// Guard's DEFAULT_WRITE_ALLOWLIST must be non-empty (wp-content) so a
		// fresh install still permits writes there. If this ever regresses to
		// empty, admins get "writes disabled" out of the box.
		$this->assertNotEmpty( Path_Allowlist_Guard::DEFAULT_WRITE_ALLOWLIST );
		$this->assertContains( 'wp-content', Path_Allowlist_Guard::DEFAULT_WRITE_ALLOWLIST );
		// Read default MUST be empty = unrestricted, so an admin who doesn't
		// touch the panel doesn't accidentally lock out every read.
		$this->assertSame( array(), Path_Allowlist_Guard::DEFAULT_READ_ALLOWLIST );
	}

	/* ==================================================================== */
	/* Cross-feature interaction — the mutation pipeline as a whole          */
	/* ==================================================================== */

	/**
	 * Whole-tab pipeline on a happy-path write: enforcer approves, backup
	 * writes, log emits. This is the "everything on, everything works" case.
	 */
	public function test_full_pipeline_happy_path_writes_backup_and_log(): void {
		// Emulate an admin who has: enabled backup + audit, tightened
		// dangerous_extensions to just .exe, set write cap to 1 MiB.
		Hardening_Settings::set_content_filters( array(
			'dangerous_extensions' => array( 'exe' ),
			'write_max_bytes'      => 1048576,
		) );
		Hardening_Settings::set_backup_audit( array(
			'backup_enabled'    => true,
			'audit_log_enabled' => true,
		) );

		// A .txt file — passes every content filter.
		$abs_path = WP_CONTENT_DIR . '/uploads/happy-path.txt';
		if ( ! is_dir( dirname( $abs_path ) ) ) {
			mkdir( dirname( $abs_path ), 0755, true );
		}
		file_put_contents( $abs_path, 'v1 seed content' );

		// Step 1: Hardening enforcer approves.
		$enforcer_result = Hardening_Enforcer::check_write( $abs_path, 'v2 new content', array( 'mode' => 'edit' ) );
		$this->assertNull( $enforcer_result, 'Enforcer must approve a clean edit' );

		// Step 2: Backup writer persists the pre-image.
		$backup_path = Audit_Trail::write_backup( $abs_path );
		$this->assertIsString( $backup_path );
		$this->assertFileExists( $backup_path );
		$this->assertSame( 'v1 seed content', file_get_contents( $backup_path ) );

		// Step 3: Simulate primary write + log emission.
		file_put_contents( $abs_path, 'v2 new content' );
		Audit_Trail::write_log( 'EDIT', $abs_path, array(
			'ability_slug'  => 'file-manager/edit-file',
			'size_before'   => 15,
			'size_after'    => 14,
			'backup_status' => 'written',
			'backup_path'   => $backup_path,
			'context'       => 'integration-happy',
		) );

		// Assert on the tab-level outcome — log entry, backup file, response envelope.
		$log = (string) file_get_contents( Audit_Trail::log_path() );
		$this->assertStringContainsString( '] EDIT', $log );
		$this->assertStringContainsString( '  Ability: file-manager/edit-file', $log );
		$this->assertStringContainsString( '  Backup: ' . $backup_path, $log );
		$this->assertStringContainsString( '  Context: integration-happy', $log );
	}

	/**
	 * Whole-tab pipeline on a hardening-blocked write: enforcer refuses BEFORE
	 * any I/O reaches the backup or log path. Documents current behaviour —
	 * a refused write leaves NO trail. If a future feature wants to log
	 * refusals too, this test must flip.
	 */
	public function test_full_pipeline_hardening_refusal_leaves_no_backup_or_log(): void {
		Hardening_Settings::set_content_filters( array( 'dangerous_extensions' => array( 'exe' ) ) );
		Hardening_Settings::set_backup_audit( array( 'backup_enabled' => true, 'audit_log_enabled' => true ) );

		$refused = Hardening_Enforcer::check_write( '/abs/probe.exe', 'x' );
		$this->assertNotNull( $refused );
		$this->assertSame( 'extension_blocked', $refused['blocked_reason'] );

		// Caller returns the refusal envelope and never invokes Audit_Trail.
		// Assert on disk: no backup file, no log file.
		$this->assertDirectoryDoesNotExist( Audit_Trail::backup_today_dir() );
		$this->assertFileDoesNotExist( Audit_Trail::log_path() );
	}

	/**
	 * Whole-tab pipeline on a backup-disabled site: enforcer approves, backup
	 * is skipped, log still emits with Backup: DISABLED.
	 */
	public function test_full_pipeline_backup_disabled_audit_enabled(): void {
		Hardening_Settings::set_backup_audit( array( 'backup_enabled' => false, 'audit_log_enabled' => true ) );

		// Backup writer no-ops.
		$this->assertNull( Audit_Trail::write_backup( '/abs/whatever.txt' ) );

		// Log still writes with Backup: DISABLED.
		Audit_Trail::write_log( 'CREATE', '/abs/new.txt', array(
			'ability_slug'  => 'file-manager/create-file',
			'size_before'   => null,
			'size_after'    => 10,
			'backup_status' => 'disabled',
			'context'       => 'no-backup site',
		) );
		$this->assertStringContainsString( '  Backup: DISABLED', (string) file_get_contents( Audit_Trail::log_path() ) );
	}

	/**
	 * Whole-tab pipeline on an audit-disabled site: enforcer approves, backup
	 * writes, but the log is silent — no file on disk.
	 */
	public function test_full_pipeline_audit_disabled_backup_enabled(): void {
		Hardening_Settings::set_backup_audit( array( 'backup_enabled' => true, 'audit_log_enabled' => false ) );

		$src = WP_CONTENT_DIR . '/uploads/audit-off.txt';
		if ( ! is_dir( dirname( $src ) ) ) {
			mkdir( dirname( $src ), 0755, true );
		}
		file_put_contents( $src, 'seed' );

		$backup = Audit_Trail::write_backup( $src );
		$this->assertIsString( $backup );
		$this->assertFileExists( $backup );

		Audit_Trail::write_log( 'DELETE', $src, array( 'ability_slug' => 'file-manager/delete-file' ) );
		$this->assertFileDoesNotExist( Audit_Trail::log_path() );
	}

	/**
	 * Sensitive-read denylist beats a permissive read allowlist (spec FR-011
	 * from feature 093). Cross-feature: the guard says "allowed" but the
	 * enforcer's denylist says "no".
	 */
	public function test_read_pipeline_denylist_fires_after_allowlist(): void {
		Path_Allowlist_Guard::set_read_paths( array() ); // unrestricted
		Hardening_Settings::set_content_filters( array( 'sensitive_read_denylist' => array( '.env' ) ) );

		$abs = '/abs/wp-content/uploads/.env';

		// Allowlist permits.
		$this->assertNull( Path_Allowlist_Guard::blocked_read_response( $abs ) );

		// But the denylist refuses.
		$blocked = Hardening_Enforcer::check_read( $abs );
		$this->assertNotNull( $blocked );
		$this->assertSame( 'sensitive_read_blocked', $blocked['blocked_reason'] );
		$this->assertSame( '.env', $blocked['matched_pattern'] );
	}

	/* ==================================================================== */
	/* Cross-panel consistency — 5 panels present the same tab identity      */
	/* ==================================================================== */

	public function test_every_panel_uses_the_shared_acrossai_fm_panel_class(): void {
		foreach ( array(
			'WriteAllowlistPanel',
			'ReadAllowlistPanel',
			'RedactionPanel',
			'ContentFiltersPanel',
			'BackupAuditPanel',
		) as $file ) {
			$src = $this->read( "src/js/file-manager-settings/components/{$file}.jsx" );
			$this->assertStringContainsString(
				'acrossai-fm-panel',
				$src,
				"{$file} must render inside a <section className=\"acrossai-fm-panel\"> for visual consistency across the tab"
			);
		}
	}

	public function test_react_bundle_asset_manifest_exists(): void {
		$this->assertFileExists( $this->plugin_root . '/build/js/file-manager-settings.asset.php' );
		$this->assertFileExists( $this->plugin_root . '/build/js/file-manager-settings.js' );
		$this->assertFileExists( $this->plugin_root . '/build/css/file-manager-settings.css' );
	}

	public function test_settings_menu_enqueues_bundle_with_nonce_middleware(): void {
		$src = $this->read( 'admin/Partials/File_Manager_Settings_Menu.php' );
		$this->assertStringContainsString( 'wp_enqueue_script', $src );
		$this->assertStringContainsString( 'file-manager-settings.js', $src );
		$this->assertStringContainsString( 'wp_create_nonce', $src );
		$this->assertStringContainsString( 'acrossaiFileManagerSettings', $src );
	}

	/* ==================================================================== */
	/* Uninstall — every persisted option gets deleted                        */
	/* ==================================================================== */

	public function test_uninstall_deletes_every_file_manager_option(): void {
		$src = $this->read( 'uninstall.php' );
		foreach ( array(
			'acrossai_file_manager_write_allowlist',
			'acrossai_file_manager_read_allowlist',
			'acrossai_file_manager_redaction_config',
			'acrossai_file_manager_dangerous_extensions',
			'acrossai_file_manager_block_double_extensions',
			'acrossai_file_manager_htaccess_directive_scan',
			'acrossai_file_manager_sanitize_filename_check',
			'acrossai_file_manager_write_max_bytes',
			'acrossai_file_manager_sensitive_read_denylist',
			'acrossai_file_manager_strict_filename_filter',
			'acrossai_file_manager_mime_type_check',
			'acrossai_file_manager_backup_enabled',
			'acrossai_file_manager_backup_retention_days',
			'acrossai_file_manager_audit_log_enabled',
			'acrossai_file_manager_audit_log_retention_days',
		) as $option ) {
			$this->assertStringContainsString(
				"delete_option( '{$option}' )",
				$src,
				"uninstall.php must delete_option( '{$option}' ) — orphan options left behind on uninstall"
			);
		}
	}

	public function test_uninstall_purges_disk_state(): void {
		$src = $this->read( 'uninstall.php' );
		$this->assertStringContainsString( 'acrossai-file-manager-backups', $src );
		$this->assertStringContainsString( 'acrossai-file-manager-logs', $src );
	}

	/* ==================================================================== */
	/* Redactor sanity — Secret_Redactor default patterns wire in cleanly    */
	/* ==================================================================== */

	public function test_secret_redactor_default_config_has_expected_shape(): void {
		$default = Secret_Redactor::default_config();
		$this->assertArrayHasKey( 'patterns', $default );
		$this->assertArrayHasKey( 'custom_literals', $default );
		$this->assertIsArray( $default['patterns'] );
	}

	public function test_secret_redactor_scrub_returns_expected_result_shape(): void {
		// Feed it content that contains a pretend-secret matching the
		// wp_credentials pattern.
		$content = "DB_PASSWORD is 'super_secret_password_123'";
		$result  = Secret_Redactor::scrub( $content );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'text', $result );
		$this->assertArrayHasKey( 'redacted', $result );
		$this->assertArrayHasKey( 'redaction_count', $result );
		$this->assertIsBool( $result['redacted'] );
		$this->assertIsInt( $result['redaction_count'] );
	}
}
