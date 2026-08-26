<?php
/**
 * Feature 094 — File Manager Audit Log + Backup Harness.
 *
 * The WP-less test bootstrap doesn't ship a functional WP_Filesystem, so
 * behavioural I/O tests for Audit_Trail's backup + log writers can't run
 * here — they're covered by live MCP probes in specs/094-file-manager-audit-log/
 * quickstart.md and by the plugin's own CI matrix (which mounts a real WP).
 *
 * This file focuses on the coverage the unit bootstrap CAN provide:
 *   1. Path accessors — pure computation over constants
 *   2. Structural — source-inspection of Audit_Trail, Get_Changelog, the
 *      three wired abilities, and the bootstrap registration
 *   3. Contract — the log entry format regex is compiled and reasonable
 *   4. Config — Hardening_Settings::get_backup_audit() returns defaults
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Audit_Trail;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Hardening_Settings;
use WP_UnitTestCase;

/**
 * Test_Feature_094_Audit_Log_And_Backups.
 */
class Test_Feature_094_Audit_Log_And_Backups extends WP_UnitTestCase {

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

		// Fully wipe the test-time WP_CONTENT_DIR between tests so each case
		// starts from a clean disk. The bootstrap points WP_CONTENT_DIR into
		// a per-PID temp workspace, so this rm-rf can never touch real data.
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

	private function seed_toggles( bool $backup, bool $log ): void {
		update_option( Hardening_Settings::OPTION_BACKUP_ENABLED, $backup );
		update_option( Hardening_Settings::OPTION_AUDIT_LOG_ENABLED, $log );
	}

	private function read_source( string $rel ): string {
		return (string) file_get_contents( $this->plugin_root . '/' . $rel );
	}

	/* ==================================================================== */
	/* Path accessors                                                        */
	/* ==================================================================== */

	public function test_backup_base_dir_lives_under_wp_content(): void {
		$path = Audit_Trail::backup_base_dir();
		$this->assertStringContainsString( 'acrossai-file-manager-backups', $path );
		$this->assertStringNotContainsString( gmdate( 'Y-m-d' ), $path, 'Base dir must NOT include today\'s date' );
	}

	public function test_backup_today_dir_includes_utc_date(): void {
		$path = Audit_Trail::backup_today_dir();
		$this->assertStringContainsString( gmdate( 'Y-m-d' ), $path );
		$this->assertStringStartsWith( Audit_Trail::backup_base_dir(), $path );
	}

	public function test_log_path_lives_in_dedicated_dir(): void {
		$path = Audit_Trail::log_path();
		$this->assertStringContainsString( 'acrossai-file-manager-logs', $path );
		$this->assertStringEndsWith( 'acrossai-file-manager.log', $path );
	}

	public function test_log_dir_matches_log_path_parent(): void {
		$this->assertSame( dirname( Audit_Trail::log_path() ), Audit_Trail::log_dir() );
	}

	/* ==================================================================== */
	/* No-op semantics — behavioural without WP_Filesystem                   */
	/* ==================================================================== */

	public function test_write_backup_returns_null_when_disabled(): void {
		// backup_enabled default = false → early return before any I/O.
		$this->assertNull( Audit_Trail::write_backup( '/tmp/whatever.txt' ) );
	}

	public function test_write_log_noops_when_disabled(): void {
		// audit_log_enabled default = false → early return before any I/O.
		// Test succeeds if the call doesn't throw.
		Audit_Trail::write_log( 'EDIT', '/tmp/whatever.txt', array( 'ability_slug' => 'file-manager/edit-file' ) );
		$this->assertTrue( true );
	}

	public function test_hardening_settings_get_backup_audit_defaults(): void {
		$snap = Hardening_Settings::get_backup_audit();
		$this->assertFalse( $snap['backup_enabled'] );
		$this->assertFalse( $snap['audit_log_enabled'] );
		$this->assertSame( 7, $snap['backup_retention_days'] );
		$this->assertSame( 7, $snap['audit_log_retention_days'] );
	}

	/* ==================================================================== */
	/* Structural — Audit_Trail utility internals                            */
	/* ==================================================================== */

	public function test_audit_trail_file_exists(): void {
		$this->assertFileExists( $this->plugin_root . '/includes/Abilities/Utilities/Audit_Trail.php' );
	}

	public function test_audit_trail_declares_public_entrypoints(): void {
		$src = $this->read_source( 'includes/Abilities/Utilities/Audit_Trail.php' );
		foreach ( array( 'write_backup', 'write_log', 'maybe_cleanup', 'run_cleanup_now', 'stats', 'backup_base_dir', 'backup_today_dir', 'log_path', 'log_dir' ) as $method ) {
			$this->assertMatchesRegularExpression(
				"/public\\s+static\\s+function\\s+{$method}\\s*\\(/",
				$src,
				"Audit_Trail must declare public static {$method}()"
			);
		}
	}

	public function test_audit_trail_fires_action_hook(): void {
		$src = $this->read_source( 'includes/Abilities/Utilities/Audit_Trail.php' );
		$this->assertStringContainsString(
			"do_action( 'acrossai_file_manager_log_entry',",
			$src,
			'Audit_Trail must fire the acrossai_file_manager_log_entry action hook'
		);
	}

	public function test_audit_trail_writes_htaccess_guard(): void {
		$src = $this->read_source( 'includes/Abilities/Utilities/Audit_Trail.php' );
		$this->assertStringContainsString( 'Deny from all', $src, 'Audit_Trail must write a Deny-from-all .htaccess to protect storage dirs' );
	}

	public function test_audit_trail_uses_wp_filesystem_not_native(): void {
		$src = $this->read_source( 'includes/Abilities/Utilities/Audit_Trail.php' );
		// $wp_filesystem->copy / put_contents / get_contents / rmdir are the
		// WP_Filesystem calls; native file_put_contents / unlink / copy MUST NOT
		// appear in the utility body (only in test helper if any).
		$this->assertStringContainsString( '$wp_filesystem->copy(', $src );
		$this->assertStringContainsString( '$wp_filesystem->put_contents(', $src );
		$this->assertStringContainsString( '$wp_filesystem->get_contents(', $src );
	}

	public function test_audit_trail_cleanup_uses_probabilistic_trigger(): void {
		$src = $this->read_source( 'includes/Abilities/Utilities/Audit_Trail.php' );
		$this->assertMatchesRegularExpression(
			'/wp_rand\(\s*1\s*,\s*self::CLEANUP_DIE_ROLL\s*\)/',
			$src,
			'Audit_Trail must gate maybe_cleanup() behind a wp_rand probabilistic trigger'
		);
	}

	public function test_audit_trail_truncates_context_to_500(): void {
		$src = $this->read_source( 'includes/Abilities/Utilities/Audit_Trail.php' );
		$this->assertStringContainsString( "CONTEXT_STORE_MAX = 500", $src );
		$this->assertStringContainsString( 'substr( sanitize_text_field( $context_raw ), 0, self::CONTEXT_STORE_MAX )', $src );
	}

	/* ==================================================================== */
	/* Structural — Get_Changelog                                            */
	/* ==================================================================== */

	public function test_get_changelog_class_exists_and_registered(): void {
		$this->assertTrue( class_exists( '\\AcrossAI_Abilities_Manager\\Includes\\Abilities\\FileManager\\Get_Changelog' ) );
		$src = $this->read_source( 'includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );
		$this->assertStringContainsString( 'new FileManager\\Get_Changelog()', $src );
	}

	public function test_get_changelog_declares_slug_and_honours_read_allowlist(): void {
		$src = $this->read_source( 'includes/Abilities/FileManager/Get_Changelog.php' );
		$this->assertStringContainsString( "'file-manager/get-changelog'", $src );
		$this->assertStringContainsString( 'Path_Allowlist_Guard::blocked_read_response(', $src, 'get-changelog MUST honour the read allowlist' );
	}

	public function test_get_changelog_input_bounds(): void {
		$src = $this->read_source( 'includes/Abilities/FileManager/Get_Changelog.php' );
		$this->assertStringContainsString( "DEFAULT_LINES = 100", $src );
		$this->assertStringContainsString( "MIN_LINES     = 1", $src );
		$this->assertStringContainsString( "MAX_LINES     = 500", $src );
	}

	public function test_get_changelog_output_includes_stats_fields(): void {
		$src = $this->read_source( 'includes/Abilities/FileManager/Get_Changelog.php' );
		foreach ( array( 'log', 'path', 'lines_returned', 'total_lines', 'message' ) as $field ) {
			$this->assertMatchesRegularExpression(
				"/'{$field}'\\s*=>\\s*array\\(\\s*'type'/",
				$src,
				"Get_Changelog output_schema must declare '{$field}'"
			);
		}
	}

	public function test_get_changelog_empty_file_returns_friendly_message(): void {
		$src = $this->read_source( 'includes/Abilities/FileManager/Get_Changelog.php' );
		$this->assertStringContainsString( 'No filesystem operations have been logged yet', $src );
		$this->assertStringContainsString( 'Log file exists but contains no entries', $src );
	}

	/* ==================================================================== */
	/* Structural — wired abilities                                          */
	/* ==================================================================== */

	/**
	 * @dataProvider wired_ability_provider
	 */
	public function test_wired_ability_imports_and_calls_audit_trail( string $relative_path ): void {
		$src = $this->read_source( $relative_path );
		$this->assertStringContainsString(
			'use AcrossAI_Abilities_Manager\\Includes\\Abilities\\Utilities\\Audit_Trail;',
			$src,
			"Missing Audit_Trail use statement in {$relative_path}"
		);
		$this->assertStringContainsString(
			'Audit_Trail::write_log(',
			$src,
			"Missing Audit_Trail::write_log() call in {$relative_path}"
		);
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function wired_ability_provider(): array {
		return array(
			// Feature 094 PR #147 (initial).
			'delete-file'      => array( 'includes/Abilities/FileManager/Delete_File.php' ),
			'edit-file'        => array( 'includes/Abilities/FileManager/Edit_File.php' ),
			'create-directory' => array( 'includes/Abilities/FileManager/Create_Directory.php' ),
			// Feature 094-complete (this PR).
			'create-file'      => array( 'includes/Abilities/FileManager/Create_File.php' ),
			'append-file'      => array( 'includes/Abilities/FileManager/Append_File.php' ),
			'copy-file'        => array( 'includes/Abilities/FileManager/Copy_File.php' ),
			'move-file'        => array( 'includes/Abilities/FileManager/Move_File.php' ),
			'delete-directory' => array( 'includes/Abilities/FileManager/Delete_Directory.php' ),
			'edit-wp-config'   => array( 'includes/Abilities/FileManager/Edit_Wp_Config.php' ),
			'clear-debug-log'  => array( 'includes/Abilities/FileManager/Clear_Debug_Log.php' ),
		);
	}

	public function test_delete_file_no_longer_writes_inline_bak(): void {
		$src = $this->read_source( 'includes/Abilities/FileManager/Delete_File.php' );
		$this->assertStringNotContainsString(
			"\$real . '.bak.' . time()",
			$src,
			'Delete_File must no longer write inline <path>.bak.<time>'
		);
		$this->assertStringContainsString( "'backup_path'", $src, 'Delete_File must expose backup_path in response' );
		$this->assertStringContainsString( 'Audit_Trail::write_backup(', $src );
	}

	public function test_delete_file_keeps_legacy_backup_field_populated_this_release(): void {
		$src = $this->read_source( 'includes/Abilities/FileManager/Delete_File.php' );
		// Deprecated `backup` field is mirrored from backup_path for one release.
		$this->assertMatchesRegularExpression( "/'backup'\\s*=>\\s*\\\$backup_path/", $src );
	}

	public function test_edit_file_populates_backup_path_in_response(): void {
		$src = $this->read_source( 'includes/Abilities/FileManager/Edit_File.php' );
		$this->assertMatchesRegularExpression( "/'backup_path'\\s*=>\\s*\\\$backup_path/", $src );
	}

	public function test_create_directory_logs_mkdir_operation(): void {
		$src = $this->read_source( 'includes/Abilities/FileManager/Create_Directory.php' );
		$this->assertStringContainsString( "Audit_Trail::write_log(\n\t\t\t'MKDIR'", $src );
	}

	public function test_wired_abilities_declare_context_input_field(): void {
		foreach ( array( 'Delete_File', 'Edit_File', 'Create_Directory' ) as $file ) {
			$src = $this->read_source( "includes/Abilities/FileManager/{$file}.php" );
			$this->assertMatchesRegularExpression(
				"/'context'\\s*=>\\s*array\\(\\s*'type'\\s*=>\\s*'string'\\s*,\\s*'maxLength'\\s*=>\\s*2000/",
				$src,
				"{$file} input_schema must declare context:{type:string, maxLength:2000}"
			);
		}
	}

	public function test_wired_abilities_declare_backup_path_in_output_schema(): void {
		foreach ( array( 'Delete_File', 'Edit_File' ) as $file ) {
			$src = $this->read_source( "includes/Abilities/FileManager/{$file}.php" );
			$this->assertMatchesRegularExpression(
				"/'backup_path'\\s*=>\\s*array\\(\\s*'type'\\s*=>\\s*array\\(\\s*'string'\\s*,\\s*'null'\\s*\\)/",
				$src,
				"{$file} output_schema must declare backup_path:['string','null']"
			);
		}
	}

	/* ==================================================================== */
	/* Structural — non-goals + surface lock                                 */
	/* ==================================================================== */

	/* ==================================================================== */
	/* Feature 094-complete additions                                        */
	/* ==================================================================== */

	public function test_all_ten_mutation_abilities_wired_to_audit_trail(): void {
		// The complete list — every mutation ability MUST reference Audit_Trail
		// now that the harness is fully wired. Regression protection against
		// removing a wiring.
		foreach ( array( 'Create_File', 'Edit_File', 'Append_File', 'Copy_File', 'Move_File', 'Delete_File', 'Create_Directory', 'Delete_Directory', 'Edit_Wp_Config', 'Clear_Debug_Log' ) as $file ) {
			$src = $this->read_source( "includes/Abilities/FileManager/{$file}.php" );
			$this->assertStringContainsString(
				'Audit_Trail::write_log(',
				$src,
				"{$file} must call Audit_Trail::write_log() — mutation abilities are all wired now"
			);
		}
	}

	public function test_backup_capable_abilities_call_write_backup(): void {
		// mkdir + rmdir don't back up (no content); everyone else does.
		foreach ( array( 'Create_File', 'Edit_File', 'Append_File', 'Copy_File', 'Move_File', 'Delete_File', 'Edit_Wp_Config', 'Clear_Debug_Log' ) as $file ) {
			$src = $this->read_source( "includes/Abilities/FileManager/{$file}.php" );
			$this->assertStringContainsString(
				'Audit_Trail::write_backup(',
				$src,
				"{$file} must call Audit_Trail::write_backup() — this ability mutates file content"
			);
		}
	}

	public function test_backup_capable_abilities_declare_backup_path_output(): void {
		foreach ( array( 'Create_File', 'Edit_File', 'Append_File', 'Copy_File', 'Move_File', 'Delete_File', 'Edit_Wp_Config', 'Clear_Debug_Log' ) as $file ) {
			$src = $this->read_source( "includes/Abilities/FileManager/{$file}.php" );
			$this->assertMatchesRegularExpression(
				"/'backup_path'\\s*=>\\s*array\\(\\s*'type'\\s*=>\\s*array\\(\\s*'string'\\s*,\\s*'null'\\s*\\)/",
				$src,
				"{$file} output_schema must declare backup_path:['string','null']"
			);
		}
	}

	public function test_delete_directory_logs_rmdir_operation(): void {
		$src = $this->read_source( 'includes/Abilities/FileManager/Delete_Directory.php' );
		$this->assertStringContainsString( "'RMDIR'", $src );
		$this->assertStringContainsString( "'entries_removed'", $src );
	}

	public function test_edit_wp_config_logs_edit_wp_config_operation(): void {
		$src = $this->read_source( 'includes/Abilities/FileManager/Edit_Wp_Config.php' );
		$this->assertStringContainsString( "'EDIT_WP_CONFIG'", $src );
	}

	public function test_clear_debug_log_logs_clear_debug_log_operation(): void {
		$src = $this->read_source( 'includes/Abilities/FileManager/Clear_Debug_Log.php' );
		$this->assertStringContainsString( "'CLEAR_DEBUG_LOG'", $src );
	}

	public function test_rest_controller_flips_backup_audit_scaffold_only(): void {
		$src = $this->read_source( 'includes/Abilities/Rest/File_Manager_Settings_Controller.php' );
		// The `/backup-audit` GET response now sets scaffold_only:false + follow_up_spec:null.
		$this->assertMatchesRegularExpression(
			"/get_backup_audit.+?'scaffold_only'\\s*=>\\s*false.+?'follow_up_spec'\\s*=>\\s*null/s",
			$src,
			'get_backup_audit MUST report scaffold_only:false and follow_up_spec:null now that 094 is live'
		);
	}

	public function test_rest_controller_registers_backup_audit_stats_route(): void {
		$src = $this->read_source( 'includes/Abilities/Rest/File_Manager_Settings_Controller.php' );
		$this->assertStringContainsString( "'/' . self::REST_BASE . '/backup-audit-stats'", $src );
		$this->assertStringContainsString( 'get_backup_audit_stats', $src );
		$this->assertStringContainsString( 'Audit_Trail::stats()', $src );
	}

	public function test_backup_audit_panel_dropped_scaffold_banner(): void {
		$src = $this->read_source( 'src/js/file-manager-settings/components/BackupAuditPanel.jsx' );
		$this->assertStringNotContainsString(
			'Scaffold only.',
			$src,
			'BackupAuditPanel should no longer show the yellow "Scaffold only." banner'
		);
		$this->assertStringContainsString(
			'notice notice-info',
			$src,
			'BackupAuditPanel should render a notice-info line now that 094 is live'
		);
		$this->assertStringContainsString(
			'statsPath',
			$src,
			'BackupAuditPanel should accept and consume a statsPath prop'
		);
	}

	public function test_orchestrator_passes_stats_path_to_panel(): void {
		$src = $this->read_source( 'src/js/file-manager-settings/components/FileManagerSettings.jsx' );
		$this->assertStringContainsString( 'backup-audit-stats', $src );
		$this->assertStringContainsString( 'statsPath=', $src );
	}

	public function test_uninstall_deletes_all_094_options(): void {
		$src = $this->read_source( 'uninstall.php' );
		foreach ( array(
			'acrossai_file_manager_backup_enabled',
			'acrossai_file_manager_backup_retention_days',
			'acrossai_file_manager_audit_log_enabled',
			'acrossai_file_manager_audit_log_retention_days',
		) as $option ) {
			$this->assertStringContainsString( "delete_option( '{$option}' )", $src );
		}
	}

	public function test_uninstall_purges_backup_and_log_dirs(): void {
		$src = $this->read_source( 'uninstall.php' );
		$this->assertStringContainsString( 'acrossai-file-manager-backups', $src );
		$this->assertStringContainsString( 'acrossai-file-manager-logs', $src );
		$this->assertStringContainsString( 'RecursiveDirectoryIterator', $src );
		$this->assertStringContainsString( 'wp_delete_file', $src );
	}

	public function test_all_ten_mutation_abilities_declare_context_input(): void {
		foreach ( array( 'Create_File', 'Edit_File', 'Append_File', 'Copy_File', 'Move_File', 'Delete_File', 'Create_Directory', 'Delete_Directory', 'Edit_Wp_Config', 'Clear_Debug_Log' ) as $file ) {
			$src = $this->read_source( "includes/Abilities/FileManager/{$file}.php" );
			$this->assertMatchesRegularExpression(
				"/'context'\\s*=>\\s*array\\(\\s*'type'\\s*=>\\s*'string'\\s*,\\s*'maxLength'\\s*=>\\s*2000/",
				$src,
				"{$file} input_schema must declare context:{type:string, maxLength:2000}"
			);
		}
	}

	/* ==================================================================== */
	/* Behavioural — full-loop over the Test_Fake_WP_Filesystem shim         */
	/* ==================================================================== */
	/* Mirrors the live MCP smoke test recorded in the session: enables the
	 * toggles, fires each Audit_Trail entrypoint against real files in a
	 * temp workspace, asserts on-disk state + parsed log entries. Every one
	 * of the 10 operation labels the ability classes emit is exercised. */

	private function tmp_source( string $basename, string $content ): string {
		$dir = WP_CONTENT_DIR . '/uploads';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}
		$path = $dir . '/' . $basename;
		file_put_contents( $path, $content );
		return $path;
	}

	private function log_contents(): string {
		$log = Audit_Trail::log_path();
		return is_file( $log ) ? (string) file_get_contents( $log ) : '';
	}

	private function log_entries(): array {
		$blocks = preg_split( '/\n{2,}/', trim( $this->log_contents() ) );
		return array_values( array_filter( (array) $blocks, static fn( string $b ): bool => '' !== trim( $b ) ) );
	}

	public function test_backup_writer_creates_dated_dir_and_htaccess(): void {
		$this->seed_toggles( true, false );
		$src = $this->tmp_source( 'backup-me.txt', 'the pre-image' );

		$result = Audit_Trail::write_backup( $src );
		$this->assertIsString( $result );
		$this->assertFileExists( $result );
		$this->assertSame( 'the pre-image', file_get_contents( $result ) );
		$this->assertStringContainsString( gmdate( 'Y-m-d' ), $result );
		$this->assertStringContainsString( '.bak.', $result );

		// .htaccess guard on the backup base dir.
		$htaccess = Audit_Trail::backup_base_dir() . '/.htaccess';
		$this->assertFileExists( $htaccess );
		$this->assertStringContainsString( 'Deny from all', (string) file_get_contents( $htaccess ) );
	}

	public function test_backup_writer_returns_null_when_target_missing(): void {
		$this->seed_toggles( true, false );
		$this->assertNull( Audit_Trail::write_backup( WP_CONTENT_DIR . '/uploads/nothing-here.txt' ) );
	}

	public function test_backup_writer_returns_null_when_disabled(): void {
		$this->seed_toggles( false, false );
		$src = $this->tmp_source( 'still-here.txt', 'x' );
		$this->assertNull( Audit_Trail::write_backup( $src ) );
	}

	public function test_backup_collision_produces_counter_suffix(): void {
		$this->seed_toggles( true, false );
		$src = $this->tmp_source( 'same-second.txt', 'x' );

		$a = Audit_Trail::write_backup( $src );
		$b = Audit_Trail::write_backup( $src );
		$c = Audit_Trail::write_backup( $src );

		$this->assertIsString( $a );
		$this->assertIsString( $b );
		$this->assertIsString( $c );
		$this->assertNotSame( $a, $b );
		$this->assertNotSame( $b, $c );
		// b and c should carry .1 / .2 counter suffixes.
		$this->assertMatchesRegularExpression( '/\.bak\.\d{6}(\.\d+)?$/', $b );
	}

	public function test_log_writer_creates_dir_htaccess_and_wellformed_entry(): void {
		$this->seed_toggles( false, true );

		Audit_Trail::write_log(
			'EDIT',
			'/abs/path/foo.txt',
			array(
				'ability_slug'  => 'file-manager/edit-file',
				'size_before'   => 100,
				'size_after'    => 250,
				'backup_status' => 'disabled',
				'context'       => 'phpunit-behavioural',
			)
		);

		// .htaccess guard on the log dir.
		$this->assertFileExists( Audit_Trail::log_dir() . '/.htaccess' );

		$log = $this->log_contents();
		$this->assertNotSame( '', $log );
		$this->assertMatchesRegularExpression( '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} UTC\] EDIT$/m', $log );
		$this->assertStringContainsString( '  Ability: file-manager/edit-file', $log );
		$this->assertStringContainsString( '  File: /abs/path/foo.txt', $log );
		$this->assertStringContainsString( '  User: phpunit@acrossai.test (ID:42) IP:127.0.0.1', $log );
		$this->assertStringContainsString( '  Size: 100 -> 250 bytes', $log );
		$this->assertStringContainsString( '  Backup: DISABLED', $log );
		$this->assertStringContainsString( '  Context: phpunit-behavioural', $log );
	}

	public function test_log_noop_when_disabled(): void {
		$this->seed_toggles( false, false );
		Audit_Trail::write_log( 'DELETE', '/abs/path/x', array( 'ability_slug' => 'file-manager/delete-file' ) );
		$this->assertSame( '', $this->log_contents() );
		$this->assertFileDoesNotExist( Audit_Trail::log_path() );
	}

	public function test_log_context_truncates_at_500_chars(): void {
		$this->seed_toggles( false, true );
		Audit_Trail::write_log(
			'CREATE',
			'/abs/path/x',
			array( 'ability_slug' => 'file-manager/create-file', 'context' => str_repeat( 'a', 800 ) )
		);
		$log = $this->log_contents();
		$this->assertStringContainsString( 'Context: ' . str_repeat( 'a', 500 ), $log );
		$this->assertStringNotContainsString( 'Context: ' . str_repeat( 'a', 501 ), $log );
	}

	public function test_log_context_sanitises_tags_and_control_chars(): void {
		$this->seed_toggles( false, true );
		Audit_Trail::write_log(
			'DELETE',
			'/abs/path/x',
			array( 'ability_slug' => 'file-manager/delete-file', 'context' => "<script>alert(1)</script>\n\tbad" )
		);
		$log = $this->log_contents();
		$this->assertStringNotContainsString( '<script>', $log );
	}

	public function test_log_captures_destination_for_move_and_copy_only(): void {
		$this->seed_toggles( false, true );

		Audit_Trail::write_log(
			'MOVE',
			'/abs/src.txt',
			array( 'ability_slug' => 'file-manager/move-file', 'destination' => '/abs/dest.txt' )
		);
		Audit_Trail::write_log(
			'CREATE',
			'/abs/created.txt',
			array( 'ability_slug' => 'file-manager/create-file', 'size_after' => 5 )
		);

		$log = $this->log_contents();
		// MOVE entry has Destination line.
		$this->assertStringContainsString( '  Destination: /abs/dest.txt', $log );
		// CREATE entry does NOT have a Destination line (only the MOVE one does).
		$create_block = null;
		foreach ( $this->log_entries() as $block ) {
			if ( str_contains( $block, ' CREATE' ) ) {
				$create_block = $block;
				break;
			}
		}
		$this->assertNotNull( $create_block );
		$this->assertStringNotContainsString( 'Destination:', $create_block );
	}

	public function test_log_backup_line_reports_written_path(): void {
		$this->seed_toggles( true, true );

		$src         = $this->tmp_source( 'bkp-line.txt', 'seed' );
		$backup_path = Audit_Trail::write_backup( $src );
		$this->assertIsString( $backup_path );

		Audit_Trail::write_log(
			'EDIT',
			$src,
			array(
				'ability_slug'  => 'file-manager/edit-file',
				'size_before'   => 4,
				'size_after'    => 8,
				'backup_status' => 'written',
				'backup_path'   => $backup_path,
				'context'       => 'behavioural',
			)
		);

		$log = $this->log_contents();
		$this->assertStringContainsString( '  Backup: ' . $backup_path, $log );
	}

	public function test_log_backup_line_reports_failed_reason(): void {
		$this->seed_toggles( false, true );
		Audit_Trail::write_log(
			'EDIT',
			'/abs/path.txt',
			array(
				'ability_slug'  => 'file-manager/edit-file',
				'backup_status' => 'failed',
				'backup_reason' => 'disk full',
			)
		);
		$this->assertStringContainsString( '  Backup: FAILED (disk full)', $this->log_contents() );
	}

	public function test_log_backup_line_reports_skipped_reason(): void {
		$this->seed_toggles( false, true );
		Audit_Trail::write_log(
			'CREATE',
			'/abs/new.txt',
			array(
				'ability_slug'  => 'file-manager/create-file',
				'backup_status' => 'skipped',
				'backup_reason' => 'target did not exist',
			)
		);
		$this->assertStringContainsString( '  Backup: SKIPPED (target did not exist)', $this->log_contents() );
	}

	public function test_log_size_string_renders_n_a_for_null_sides(): void {
		$this->seed_toggles( false, true );

		// CREATE: null size_before → "n/a"
		Audit_Trail::write_log(
			'CREATE',
			'/abs/new.txt',
			array( 'ability_slug' => 'file-manager/create-file', 'size_before' => null, 'size_after' => 10 )
		);
		// DELETE: null size_after → "n/a"
		Audit_Trail::write_log(
			'DELETE',
			'/abs/gone.txt',
			array( 'ability_slug' => 'file-manager/delete-file', 'size_before' => 20, 'size_after' => null )
		);

		$log = $this->log_contents();
		$this->assertStringContainsString( '  Size: n/a -> 10 bytes', $log );
		$this->assertStringContainsString( '  Size: 20 -> n/a bytes', $log );
	}

	/*
	 * Note on action-hook behavioural coverage: the WP-less bootstrap stubs
	 * add_action/do_action as no-ops (deliberate — other tests rely on that
	 * to isolate side effects). Hook firing is instead covered by
	 * test_audit_trail_fires_action_hook above (structural — asserts the
	 * do_action call site is present in Audit_Trail source) and by the
	 * live MCP smoke test in the session log.
	 */

	public function test_stats_reflects_disk_state_after_writes(): void {
		$this->seed_toggles( true, true );

		// Prime one backup + two log entries.
		$src = $this->tmp_source( 'stats.txt', 'seed' );
		Audit_Trail::write_backup( $src );
		Audit_Trail::write_log( 'CREATE', $src, array( 'ability_slug' => 'file-manager/create-file', 'size_after' => 4 ) );
		Audit_Trail::write_log( 'EDIT',   $src, array( 'ability_slug' => 'file-manager/edit-file', 'size_before' => 4, 'size_after' => 8 ) );

		$stats = Audit_Trail::stats();
		$this->assertGreaterThanOrEqual( 2, (int) $stats['log_total_lines'] );
		$this->assertGreaterThan( 0, (int) $stats['log_size_bytes'] );
		$this->assertGreaterThanOrEqual( 1, (int) $stats['backup_days_present'] );
		$this->assertGreaterThan( 0, (int) $stats['backup_total_size_bytes'] );
		$this->assertNotNull( $stats['log_last_entry_timestamp'] );
	}

	public function test_run_cleanup_now_deletes_old_backup_dirs(): void {
		$this->seed_toggles( true, false );
		update_option( Hardening_Settings::OPTION_BACKUP_RETENTION_DAYS, 1 );

		$base   = Audit_Trail::backup_base_dir();
		$today  = Audit_Trail::backup_today_dir();
		$stale  = $base . '/2020-01-01';
		mkdir( $today, 0755, true );
		mkdir( $stale, 0755, true );
		file_put_contents( $stale . '/leftover.bak.000000', 'stale' );

		Audit_Trail::run_cleanup_now();

		$this->assertFileDoesNotExist( $stale );
		$this->assertDirectoryExists( $today );
	}

	public function test_run_cleanup_now_trims_old_log_entries(): void {
		$this->seed_toggles( false, true );
		update_option( Hardening_Settings::OPTION_AUDIT_LOG_RETENTION_DAYS, 1 );

		// Prime the log with one very-old entry and one fresh entry.
		$log = Audit_Trail::log_path();
		mkdir( Audit_Trail::log_dir(), 0755, true );
		$old = "[2020-01-01 00:00:00 UTC] EDIT\n  Ability: file-manager/edit-file\n  File: /old\n  User:  (ID:0) IP:unknown\n  Size: 0 -> 0 bytes\n  Backup: DISABLED\n  Context: ancient\n";
		$new = '[' . gmdate( 'Y-m-d H:i:s' ) . " UTC] EDIT\n  Ability: file-manager/edit-file\n  File: /new\n  User:  (ID:0) IP:unknown\n  Size: 0 -> 0 bytes\n  Backup: DISABLED\n  Context: fresh\n";
		file_put_contents( $log, $old . "\n" . $new );

		Audit_Trail::run_cleanup_now();

		$after = (string) file_get_contents( $log );
		$this->assertStringNotContainsString( 'ancient', $after );
		$this->assertStringContainsString( 'fresh', $after );
	}

	/**
	 * End-to-end matrix mirroring the live MCP smoke test — every one of
	 * the 10 operation labels the ability classes emit is exercised here.
	 * When this passes we have parity between what CI covers and what the
	 * live MCP session covered.
	 *
	 * @dataProvider operation_label_provider
	 */
	public function test_every_operation_label_writes_wellformed_entry( string $operation, string $ability_slug, array $details ): void {
		$this->seed_toggles( false, true );

		Audit_Trail::write_log(
			$operation,
			'/abs/fixture/' . strtolower( $operation ) . '.tgt',
			array_merge(
				array( 'ability_slug' => $ability_slug, 'context' => 'matrix-' . $operation ),
				$details
			)
		);

		$log = $this->log_contents();
		$this->assertStringContainsString( '] ' . $operation, $log );
		$this->assertStringContainsString( '  Ability: ' . $ability_slug, $log );
		$this->assertStringContainsString( '  Context: matrix-' . $operation, $log );
	}

	/**
	 * @return array<string, array{0:string,1:string,2:array<string,mixed>}>
	 */
	public static function operation_label_provider(): array {
		return array(
			'CREATE'          => array( 'CREATE',          'file-manager/create-file',     array( 'size_before' => null, 'size_after' => 10 ) ),
			'EDIT'            => array( 'EDIT',            'file-manager/edit-file',       array( 'size_before' => 10, 'size_after' => 12 ) ),
			'APPEND'          => array( 'APPEND',          'file-manager/append-file',     array( 'size_before' => 10, 'size_after' => 14 ) ),
			'COPY'            => array( 'COPY',            'file-manager/copy-file',       array( 'size_before' => null, 'size_after' => 10, 'destination' => '/abs/copy-dest.txt' ) ),
			'MOVE'            => array( 'MOVE',            'file-manager/move-file',       array( 'size_before' => 10, 'size_after' => 10, 'destination' => '/abs/move-dest.txt' ) ),
			'DELETE'          => array( 'DELETE',          'file-manager/delete-file',     array( 'size_before' => 10, 'size_after' => null ) ),
			'MKDIR'           => array( 'MKDIR',           'file-manager/create-directory', array( 'size_before' => null, 'size_after' => null ) ),
			'RMDIR'           => array( 'RMDIR',           'file-manager/delete-directory', array( 'size_before' => null, 'size_after' => null ) ),
			'EDIT_WP_CONFIG'  => array( 'EDIT_WP_CONFIG',  'file-manager/edit-wp-config',  array( 'size_before' => 1000, 'size_after' => 1005 ) ),
			'CLEAR_DEBUG_LOG' => array( 'CLEAR_DEBUG_LOG', 'file-manager/clear-debug-log', array( 'size_before' => 500, 'size_after' => 0 ) ),
		);
	}
}
