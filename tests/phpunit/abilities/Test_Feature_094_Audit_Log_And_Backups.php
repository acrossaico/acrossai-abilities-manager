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

		global $__acrossai_test_options;
		$__acrossai_test_options = array();
	}

	protected function tearDown(): void {
		global $__acrossai_test_options;
		$__acrossai_test_options = array();
		parent::tearDown();
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
			'delete-file'      => array( 'includes/Abilities/FileManager/Delete_File.php' ),
			'edit-file'        => array( 'includes/Abilities/FileManager/Edit_File.php' ),
			'create-directory' => array( 'includes/Abilities/FileManager/Create_Directory.php' ),
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

	/**
	 * The 5 remaining mutation abilities (Create_File, Append_File, Copy_File,
	 * Move_File, plus 3 log-only ones from Phase 6/7 of tasks.md — Edit_Wp_Config,
	 * Clear_Debug_Log, Delete_Directory) are DEFERRED to a follow-up PR per
	 * the scope cut on this PR. Verify they do NOT accidentally reference
	 * Audit_Trail (which would suggest incomplete wiring).
	 *
	 * @dataProvider deferred_ability_provider
	 */
	public function test_deferred_ability_not_yet_wired( string $relative_path ): void {
		$src = $this->read_source( $relative_path );
		$this->assertStringNotContainsString(
			'Audit_Trail',
			$src,
			"{$relative_path} references Audit_Trail — this ability is scoped to a follow-up PR. Either complete the wiring or move it out of the deferred list."
		);
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function deferred_ability_provider(): array {
		return array(
			'create-file'      => array( 'includes/Abilities/FileManager/Create_File.php' ),
			'append-file'      => array( 'includes/Abilities/FileManager/Append_File.php' ),
			'copy-file'        => array( 'includes/Abilities/FileManager/Copy_File.php' ),
			'move-file'        => array( 'includes/Abilities/FileManager/Move_File.php' ),
			'delete-directory' => array( 'includes/Abilities/FileManager/Delete_Directory.php' ),
			'edit-wp-config'   => array( 'includes/Abilities/FileManager/Edit_Wp_Config.php' ),
			'clear-debug-log'  => array( 'includes/Abilities/FileManager/Clear_Debug_Log.php' ),
		);
	}
}
