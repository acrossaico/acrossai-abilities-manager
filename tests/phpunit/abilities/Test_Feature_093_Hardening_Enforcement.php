<?php
/**
 * Feature 093 — File Manager Hardening enforcement pass.
 *
 * Two flavours of coverage:
 *
 *   (1) Behavioural — direct calls into Hardening_Enforcer::check_write() and
 *       ::check_read() under a variety of option snapshots. Verifies each of
 *       the eight new blocked_reason envelopes matches the shape declared in
 *       specs/093-file-manager-hardening/contracts/blocked-reason-envelopes.md.
 *
 *   (2) Structural — file_get_contents on each of the six affected ability
 *       classes to verify the enforcer call site is present in the right
 *       spot (after Path_Allowlist_Guard) and that the output_schema declares
 *       every new context field.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Hardening_Enforcer;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Hardening_Settings;
use WP_UnitTestCase;

/**
 * Class Test_Feature_093_Hardening_Enforcement.
 */
class Test_Feature_093_Hardening_Enforcement extends WP_UnitTestCase {

	/** @var string */
	private string $plugin_root = '';

	/** Absolute-path fixture — never touched on disk. */
	private const ABSPATH_FIXTURE = '/tmp/acrossai-093-fixture';

	protected function setUp(): void {
		parent::setUp();
		$this->plugin_root = dirname( __DIR__, 3 );

		// Reset every hardening option to a KNOWN-DISABLED state so each test
		// starts from a clean slate — no test leakage between cases. Individual
		// tests re-enable exactly the options they exercise.
		global $__acrossai_test_options;
		$__acrossai_test_options = array(
			Hardening_Settings::OPTION_DANGEROUS_EXTENSIONS      => array(),
			Hardening_Settings::OPTION_BLOCK_DOUBLE_EXTENSIONS   => false,
			Hardening_Settings::OPTION_HTACCESS_DIRECTIVE_SCAN   => false,
			Hardening_Settings::OPTION_SANITIZE_FILENAME_CHECK   => false,
			Hardening_Settings::OPTION_WRITE_MAX_BYTES           => 0,
			Hardening_Settings::OPTION_SENSITIVE_READ_DENYLIST   => array(),
			Hardening_Settings::OPTION_STRICT_FILENAME_FILTER    => false,
			Hardening_Settings::OPTION_MIME_TYPE_CHECK           => false,
		);
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
	/* Behavioural — direct enforcer calls                                   */
	/* ==================================================================== */

	/* --- FR-002 extension_blocked ---------------------------------------- */

	public function test_extension_blocked_returns_refusal_envelope(): void {
		update_option( Hardening_Settings::OPTION_DANGEROUS_EXTENSIONS, array( 'exe' ) );
		$result = Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/probe.exe', 'x' );
		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 'extension_blocked', $result['blocked_reason'] );
		$this->assertSame( 'exe', $result['extension'] );
		$this->assertSame( self::ABSPATH_FIXTURE . '/probe.exe', $result['path'] );
	}

	public function test_extension_blocked_case_insensitive(): void {
		update_option( Hardening_Settings::OPTION_DANGEROUS_EXTENSIONS, array( 'exe' ) );
		$result = Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/probe.EXE', 'x' );
		$this->assertNotNull( $result );
		$this->assertSame( 'exe', $result['extension'] );
	}

	public function test_extension_blocked_noop_when_list_empty(): void {
		$result = Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/probe.exe', 'x' );
		$this->assertNull( $result );
	}

	public function test_extension_blocked_noop_when_extension_not_listed(): void {
		update_option( Hardening_Settings::OPTION_DANGEROUS_EXTENSIONS, array( 'exe' ) );
		$result = Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/probe.txt', 'x' );
		$this->assertNull( $result );
	}

	public function test_extension_blocked_uses_target_basename_override_for_copy_move(): void {
		update_option( Hardening_Settings::OPTION_DANGEROUS_EXTENSIONS, array( 'exe' ) );
		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/source.txt',
			'',
			array( 'mode' => 'copy', 'target_basename_override' => 'dest.exe' )
		);
		$this->assertNotNull( $result );
		$this->assertSame( 'extension_blocked', $result['blocked_reason'] );
	}

	/* --- FR-003 double_extension_blocked --------------------------------- */

	public function test_double_extension_blocked_php_jpg(): void {
		update_option( Hardening_Settings::OPTION_BLOCK_DOUBLE_EXTENSIONS, true );
		$result = Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/foo.php.jpg', 'x' );
		$this->assertSame( 'double_extension_blocked', $result['blocked_reason'] );
		$this->assertSame( 'foo.php.jpg', $result['basename'] );
	}

	public function test_double_extension_blocked_phtml_png(): void {
		update_option( Hardening_Settings::OPTION_BLOCK_DOUBLE_EXTENSIONS, true );
		$result = Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/foo.phtml.png', 'x' );
		$this->assertSame( 'double_extension_blocked', $result['blocked_reason'] );
	}

	public function test_double_extension_blocked_phar_variant(): void {
		update_option( Hardening_Settings::OPTION_BLOCK_DOUBLE_EXTENSIONS, true );
		$result = Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/foo.phar.gif', 'x' );
		$this->assertSame( 'double_extension_blocked', $result['blocked_reason'] );
	}

	public function test_double_extension_ignored_for_plain_jpg(): void {
		update_option( Hardening_Settings::OPTION_BLOCK_DOUBLE_EXTENSIONS, true );
		$result = Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/foo.jpg', 'x' );
		$this->assertNull( $result );
	}

	/* --- FR-004 htaccess_directive_blocked ------------------------------- */

	public function test_htaccess_directive_blocked_addtype(): void {
		update_option( Hardening_Settings::OPTION_HTACCESS_DIRECTIVE_SCAN, true );
		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/.htaccess',
			'AddType text/plain .foo'
		);
		$this->assertSame( 'htaccess_directive_blocked', $result['blocked_reason'] );
		$this->assertSame( 'AddType', $result['directive'] );
	}

	public function test_htaccess_directive_scan_case_insensitive(): void {
		update_option( Hardening_Settings::OPTION_HTACCESS_DIRECTIVE_SCAN, true );
		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/.htaccess',
			'ADDTYPE text/plain .foo'
		);
		$this->assertSame( 'htaccess_directive_blocked', $result['blocked_reason'] );
	}

	public function test_htaccess_directive_scan_covers_all_six_directives(): void {
		update_option( Hardening_Settings::OPTION_HTACCESS_DIRECTIVE_SCAN, true );
		foreach ( array( 'AddType', 'SetHandler', 'php_value', 'php_flag', 'auto_prepend', 'auto_append' ) as $directive ) {
			$content = "# comment\n" . $directive . " something on\n";
			$result  = Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/.htaccess', $content );
			$this->assertNotNull( $result, "Directive {$directive} was not caught" );
			$this->assertSame( 'htaccess_directive_blocked', $result['blocked_reason'] );
			$this->assertSame( $directive, $result['directive'] );
		}
	}

	public function test_htaccess_directive_scan_only_fires_on_dot_htaccess(): void {
		update_option( Hardening_Settings::OPTION_HTACCESS_DIRECTIVE_SCAN, true );
		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/readme.txt',
			'AddType text/plain .foo'
		);
		$this->assertNull( $result );
	}

	public function test_htaccess_directive_scan_on_append_uses_appended_content_only(): void {
		update_option( Hardening_Settings::OPTION_HTACCESS_DIRECTIVE_SCAN, true );
		// The appended content is clean; source content isn't inspected in append mode.
		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/.htaccess',
			"# harmless append\n",
			array( 'mode' => 'append', 'existing_size' => 0 )
		);
		$this->assertNull( $result );
	}

	public function test_htaccess_directive_scan_on_copy_uses_source_content(): void {
		update_option( Hardening_Settings::OPTION_HTACCESS_DIRECTIVE_SCAN, true );
		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/source.htaccess',
			'',
			array(
				'mode'                     => 'copy',
				'target_basename_override' => '.htaccess',
				'source_content_reader'    => static fn(): string => 'php_value display_errors on',
			)
		);
		$this->assertNotNull( $result );
		$this->assertSame( 'htaccess_directive_blocked', $result['blocked_reason'] );
		$this->assertSame( 'php_value', $result['directive'] );
	}

	/* --- FR-005 filename_sanitize_failed --------------------------------- */

	public function test_filename_sanitize_failed_reports_input_and_sanitized(): void {
		update_option( Hardening_Settings::OPTION_SANITIZE_FILENAME_CHECK, true );
		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/weird name.txt',
			'x'
		);
		$this->assertSame( 'filename_sanitize_failed', $result['blocked_reason'] );
		$this->assertSame( 'weird name.txt', $result['input'] );
		$this->assertSame( 'weird-name.txt', $result['sanitized'] );
	}

	public function test_filename_sanitize_accepts_clean_name(): void {
		update_option( Hardening_Settings::OPTION_SANITIZE_FILENAME_CHECK, true );
		$this->assertNull( Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/clean-name.txt', 'x' ) );
	}

	/* --- FR-006 write_size_exceeded -------------------------------------- */

	// Use MIN_WRITE_MAX_BYTES (1024 bytes) as the smallest valid cap — the
	// getter clamps to this minimum, so cap < 1024 is silently rewritten.

	public function test_write_size_exceeded_on_create(): void {
		update_option( Hardening_Settings::OPTION_WRITE_MAX_BYTES, 1024 );
		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/big.txt',
			str_repeat( 'x', 2048 )
		);
		$this->assertSame( 'write_size_exceeded', $result['blocked_reason'] );
		$this->assertSame( 2048, $result['size'] );
		$this->assertSame( 1024, $result['max_bytes'] );
	}

	public function test_write_size_exceeded_boundary_at_cap_ok(): void {
		update_option( Hardening_Settings::OPTION_WRITE_MAX_BYTES, 1024 );
		$this->assertNull( Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/exact.txt',
			str_repeat( 'x', 1024 )
		) );
	}

	public function test_write_size_exceeded_boundary_at_cap_plus_one_refused(): void {
		update_option( Hardening_Settings::OPTION_WRITE_MAX_BYTES, 1024 );
		$this->assertNotNull( Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/big.txt',
			str_repeat( 'x', 1025 )
		) );
	}

	public function test_write_size_exceeded_on_append_uses_new_size(): void {
		update_option( Hardening_Settings::OPTION_WRITE_MAX_BYTES, 1024 );
		// Existing 600 + appending 500 = 1100 > 1024 cap.
		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/log.txt',
			str_repeat( 'x', 500 ),
			array( 'mode' => 'append', 'existing_size' => 600 )
		);
		$this->assertSame( 'write_size_exceeded', $result['blocked_reason'] );
		$this->assertSame( 1100, $result['size'] );
	}

	public function test_write_size_exceeded_on_copy_uses_source_size(): void {
		update_option( Hardening_Settings::OPTION_WRITE_MAX_BYTES, 1024 );
		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/source.txt',
			'',
			array( 'mode' => 'copy', 'target_basename_override' => 'dest.txt', 'source_size' => 5000 )
		);
		$this->assertSame( 'write_size_exceeded', $result['blocked_reason'] );
		$this->assertSame( 5000, $result['size'] );
	}

	/* --- FR-007 filename_strict_blocked ---------------------------------- */

	public function test_filename_strict_blocked_covers_every_marker(): void {
		update_option( Hardening_Settings::OPTION_STRICT_FILENAME_FILTER, true );
		foreach ( array( 'c99', 'r57', 'wso', 'b374k', 'weevely', 'shell', 'alfa', 'bypass', 'backdoor' ) as $marker ) {
			$result = Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . "/{$marker}-file.txt", 'x' );
			$this->assertNotNull( $result, "Marker {$marker} was not caught" );
			$this->assertSame( 'filename_strict_blocked', $result['blocked_reason'] );
			$this->assertSame( $marker, $result['marker'] );
		}
	}

	public function test_filename_strict_blocked_off_by_default(): void {
		$this->assertNull( Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/c99.txt', 'x' ) );
	}

	/* --- FR-008 mime_type_blocked ---------------------------------------- */

	public function test_mime_type_blocked_refuses_unknown_extension(): void {
		update_option( Hardening_Settings::OPTION_MIME_TYPE_CHECK, true );
		$result = Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/probe.xyz', 'x' );
		$this->assertSame( 'mime_type_blocked', $result['blocked_reason'] );
		$this->assertSame( 'xyz', $result['extension'] );
	}

	public function test_mime_type_always_allowed_extensions_succeed(): void {
		update_option( Hardening_Settings::OPTION_MIME_TYPE_CHECK, true );
		foreach ( array( 'php', 'txt', 'log', 'json', 'xml', 'css', 'js', 'md', 'html', 'htm', 'htaccess' ) as $ext ) {
			$result = Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . "/file.{$ext}", 'x' );
			$this->assertNull( $result, "Always-allowed extension .{$ext} was blocked" );
		}
	}

	public function test_mime_type_check_skipped_on_append_mode(): void {
		update_option( Hardening_Settings::OPTION_MIME_TYPE_CHECK, true );
		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/probe.xyz',
			'x',
			array( 'mode' => 'append', 'existing_size' => 10 )
		);
		$this->assertNull( $result );
	}

	/* --- FR-010 / FR-011 sensitive_read_blocked --------------------------- */

	public function test_sensitive_read_blocked_literal_case_sensitive(): void {
		update_option( Hardening_Settings::OPTION_SENSITIVE_READ_DENYLIST, array( 'id_rsa' ) );
		$hit = Hardening_Enforcer::check_read( self::ABSPATH_FIXTURE . '/id_rsa' );
		$this->assertSame( 'sensitive_read_blocked', $hit['blocked_reason'] );
		$this->assertSame( 'id_rsa', $hit['matched_pattern'] );

		// Different case → no match (spec User Story 2 acceptance scenario 4).
		$this->assertNull( Hardening_Enforcer::check_read( self::ABSPATH_FIXTURE . '/ID_RSA' ) );
	}

	public function test_sensitive_read_blocked_glob_case_insensitive(): void {
		update_option( Hardening_Settings::OPTION_SENSITIVE_READ_DENYLIST, array( '*.key' ) );

		$hit_lower = Hardening_Enforcer::check_read( self::ABSPATH_FIXTURE . '/backup.key' );
		$this->assertSame( 'sensitive_read_blocked', $hit_lower['blocked_reason'] );
		$this->assertSame( '*.key', $hit_lower['matched_pattern'] );

		$hit_upper = Hardening_Enforcer::check_read( self::ABSPATH_FIXTURE . '/BACKUP.KEY' );
		$this->assertSame( 'sensitive_read_blocked', $hit_upper['blocked_reason'] );
	}

	public function test_sensitive_read_denylist_empty_is_noop(): void {
		$this->assertNull( Hardening_Enforcer::check_read( self::ABSPATH_FIXTURE . '/.env' ) );
	}

	public function test_sensitive_read_denylist_covers_default_secrets(): void {
		update_option(
			Hardening_Settings::OPTION_SENSITIVE_READ_DENYLIST,
			Hardening_Settings::DEFAULT_SENSITIVE_READ_DENYLIST
		);
		foreach ( array( '.env', 'id_rsa', 'authorized_keys' ) as $name ) {
			$this->assertNotNull(
				Hardening_Enforcer::check_read( self::ABSPATH_FIXTURE . '/' . $name ),
				"Default denylist did not catch {$name}"
			);
		}
		foreach ( array( 'server.crt', 'foo.pem', 'bar.p12' ) as $name ) {
			$this->assertNotNull(
				Hardening_Enforcer::check_read( self::ABSPATH_FIXTURE . '/' . $name ),
				"Default denylist glob did not catch {$name}"
			);
		}
	}

	/* ==================================================================== */
	/* Structural — enforcer call-site presence in each ability              */
	/* ==================================================================== */

	/**
	 * @dataProvider write_ability_provider
	 */
	public function test_write_ability_calls_enforcer_after_allowlist_guard( string $relative_path ): void {
		$src = $this->read_source( $relative_path );
		$this->assertStringContainsString(
			'use AcrossAI_Abilities_Manager\\Includes\\Abilities\\Utilities\\Hardening_Enforcer;',
			$src,
			"Missing Hardening_Enforcer use statement in {$relative_path}"
		);
		$this->assertStringContainsString(
			'Hardening_Enforcer::check_write(',
			$src,
			"Missing Hardening_Enforcer::check_write() call in {$relative_path}"
		);

		// Enforcer call MUST appear AFTER the allowlist guard call (spec FR-014).
		$allowlist_pos = strpos( $src, 'Path_Allowlist_Guard::blocked_write_response(' );
		$enforcer_pos  = strpos( $src, 'Hardening_Enforcer::check_write(' );
		$this->assertNotFalse( $allowlist_pos );
		$this->assertNotFalse( $enforcer_pos );
		$this->assertGreaterThan(
			$allowlist_pos,
			$enforcer_pos,
			"Enforcer call site must appear after the allowlist guard in {$relative_path}"
		);
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function write_ability_provider(): array {
		return array(
			'create-file' => array( 'includes/Abilities/FileManager/Create_File.php' ),
			'edit-file'   => array( 'includes/Abilities/FileManager/Edit_File.php' ),
			'append-file' => array( 'includes/Abilities/FileManager/Append_File.php' ),
			'copy-file'   => array( 'includes/Abilities/FileManager/Copy_File.php' ),
			'move-file'   => array( 'includes/Abilities/FileManager/Move_File.php' ),
		);
	}

	public function test_read_file_calls_enforcer_after_read_allowlist_guard(): void {
		$src = $this->read_source( 'includes/Abilities/FileManager/Read_File.php' );
		$this->assertStringContainsString( 'Hardening_Enforcer::check_read(', $src );
		$allowlist_pos = strpos( $src, 'Path_Allowlist_Guard::blocked_read_response(' );
		$enforcer_pos  = strpos( $src, 'Hardening_Enforcer::check_read(' );
		$this->assertNotFalse( $allowlist_pos );
		$this->assertNotFalse( $enforcer_pos );
		$this->assertGreaterThan(
			$allowlist_pos,
			$enforcer_pos,
			'Sensitive-read denylist check must appear after the read-allowlist guard (spec FR-011)'
		);
	}

	/**
	 * @dataProvider write_ability_provider
	 */
	public function test_write_ability_output_schema_declares_new_context_fields( string $relative_path ): void {
		$src = $this->read_source( $relative_path );
		foreach ( array( 'extension', 'basename', 'directive', 'input', 'sanitized', 'size', 'max_bytes', 'marker' ) as $key ) {
			$this->assertMatchesRegularExpression(
				"/'{$key}'\\s*=>\\s*array\\(\\s*'type'/",
				$src,
				"Missing '{$key}' in {$relative_path} output_schema"
			);
		}
	}

	public function test_read_file_output_schema_declares_sensitive_read_fields(): void {
		$src = $this->read_source( 'includes/Abilities/FileManager/Read_File.php' );
		foreach ( array( 'basename', 'matched_pattern' ) as $key ) {
			$this->assertMatchesRegularExpression(
				"/'{$key}'\\s*=>\\s*array\\(\\s*'type'/",
				$src,
				"Missing '{$key}' in Read_File output_schema"
			);
		}
	}

	/* ==================================================================== */
	/* Structural — REST controller scaffold flag flip + panel banner        */
	/* ==================================================================== */

	public function test_content_filters_endpoint_marked_no_longer_scaffold(): void {
		$src = $this->read_source( 'includes/Abilities/Rest/File_Manager_Settings_Controller.php' );
		// GET response block must have scaffold_only:false for content-filters.
		$this->assertStringContainsString(
			"'scaffold_only'  => false",
			$src,
			'Content Filters endpoint should now report scaffold_only:false'
		);
	}

	public function test_content_filters_panel_dropped_scaffold_notice(): void {
		$src = $this->read_source( 'src/js/file-manager-settings/components/ContentFiltersPanel.jsx' );
		// The old scaffold banner said "Scaffold only." — that string is gone.
		// (A separate notice-warning still exists for per-entry sanitiser drops;
		// that's the PR #144 UX and unrelated to the scaffold flag.)
		$this->assertStringNotContainsString(
			'Scaffold only.',
			$src,
			'Content Filters panel should no longer show the "Scaffold only." scaffold banner'
		);
		$this->assertStringContainsString(
			'This list now gates',
			$src,
			'Content Filters panel should show the notice-info line confirming enforcement'
		);
		$this->assertStringContainsString(
			'notice notice-info',
			$src,
			'Content Filters panel should render a notice-info block confirming enforcement'
		);
	}

	public function test_backup_audit_panel_banner_references_094(): void {
		$src = $this->read_source( 'src/js/file-manager-settings/components/BackupAuditPanel.jsx' );
		$this->assertStringContainsString(
			'094-file-manager-audit-log',
			$src,
			'Backup & Audit panel banner should reference 094-file-manager-audit-log'
		);
	}
}
