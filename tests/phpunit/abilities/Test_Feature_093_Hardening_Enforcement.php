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

	/**
	 * Regression: sanitize_filename_check MUST NOT refuse legitimate WP dotfiles.
	 *
	 * Real WP's sanitize_file_name() strips leading dots via trim($filename, '.-_'),
	 * which would make `.htaccess` become `htaccess` and refuse the write. That
	 * killed the FR-004 htaccess-directive scan (never reachable) and blocked
	 * legitimate .user.ini / .htpasswd writes on every site. Mirrors the
	 * reference plugin's $allowed_dotfiles carve-out.
	 */
	public function test_filename_sanitize_allows_wp_adjacent_dotfiles(): void {
		update_option( Hardening_Settings::OPTION_SANITIZE_FILENAME_CHECK, true );
		foreach ( array( '.htaccess', '.htpasswd', '.user.ini' ) as $dotfile ) {
			$this->assertNull(
				Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/' . $dotfile, 'x' ),
				"Dotfile {$dotfile} must pass the sanitize check — the enforcer carve-out is missing"
			);
		}
	}

	/**
	 * Dotfiles NOT on the carve-out list still get refused when the check is on.
	 * This keeps the check meaningful for unknown dotfiles (.gitignore etc.) —
	 * admins who need those can disable the check.
	 */
	public function test_filename_sanitize_still_refuses_other_dotfiles(): void {
		update_option( Hardening_Settings::OPTION_SANITIZE_FILENAME_CHECK, true );
		$result = Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/.gitignore', 'x' );
		$this->assertNotNull( $result );
		$this->assertSame( 'filename_sanitize_failed', $result['blocked_reason'] );
	}

	/**
	 * With the carve-out in place, an admin can now create .htaccess AND
	 * write clean content. Verifies the FR-004 htaccess-directive scanner
	 * is reachable — with a clean body, the write succeeds.
	 */
	public function test_htaccess_write_reaches_directive_scan_after_sanitize_carveout(): void {
		update_option( Hardening_Settings::OPTION_SANITIZE_FILENAME_CHECK, true );
		update_option( Hardening_Settings::OPTION_HTACCESS_DIRECTIVE_SCAN, true );

		// Clean .htaccess body — no dangerous directives, so both checks pass.
		$this->assertNull( Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/.htaccess',
			"# Redirect to https\nRewriteEngine On\n"
		) );

		// Dirty .htaccess body — sanitize passes (carve-out), directive scan refuses.
		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/.htaccess',
			'php_value display_errors 1'
		);
		$this->assertNotNull( $result );
		$this->assertSame( 'htaccess_directive_blocked', $result['blocked_reason'] );
		$this->assertSame( 'php_value', $result['directive'] );
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

	/**
	 * Feature 094-complete flipped the Backup & Audit panel from "Scaffold
	 * only, feature 094 pending" to a live info line. This assertion was
	 * inverted during that flip — the banner NO LONGER references 094
	 * because 094 is done. Kept as a regression check so we don't
	 * accidentally reintroduce a scaffold-pointing banner.
	 */
	public function test_backup_audit_panel_no_longer_scaffold(): void {
		$src = $this->read_source( 'src/js/file-manager-settings/components/BackupAuditPanel.jsx' );
		$this->assertStringNotContainsString(
			'Scaffold only.',
			$src,
			'BackupAuditPanel should not carry a "Scaffold only." banner now that feature 094 is live'
		);
		$this->assertStringContainsString(
			'notice notice-info',
			$src,
			'BackupAuditPanel should render a live-status notice-info line'
		);
	}

	/* ==================================================================== */
	/* Extra safety tests — substitute for the live probes not executed      */
	/* ==================================================================== */

	/* --- Non-goal abilities must NOT import Hardening_Enforcer ----------- */

	/**
	 * Spec FR-012 explicitly excludes 15 abilities from this feature. If any
	 * of them accidentally gain an enforcer call site we'd introduce refusal
	 * behaviour the spec forbids. This test locks the surface area.
	 *
	 * @dataProvider non_goal_ability_provider
	 */
	public function test_non_goal_ability_does_not_import_enforcer( string $relative_path ): void {
		$src = $this->read_source( $relative_path );
		$this->assertStringNotContainsString(
			'Hardening_Enforcer',
			$src,
			"Non-goal ability {$relative_path} must not reference Hardening_Enforcer (spec FR-012)"
		);
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function non_goal_ability_provider(): array {
		return array(
			'delete-file'            => array( 'includes/Abilities/FileManager/Delete_File.php' ),
			'delete-directory'       => array( 'includes/Abilities/FileManager/Delete_Directory.php' ),
			'create-directory'       => array( 'includes/Abilities/FileManager/Create_Directory.php' ),
			'file-info'              => array( 'includes/Abilities/FileManager/File_Info.php' ),
			'list-directory'         => array( 'includes/Abilities/FileManager/List_Directory.php' ),
			'read-debug-log'         => array( 'includes/Abilities/FileManager/Read_Debug_Log.php' ),
			'clear-debug-log'        => array( 'includes/Abilities/FileManager/Clear_Debug_Log.php' ),
			'read-wp-config'         => array( 'includes/Abilities/FileManager/Read_Wp_Config.php' ),
			'edit-wp-config'         => array( 'includes/Abilities/FileManager/Edit_Wp_Config.php' ),
			'get-wp-config-constant' => array( 'includes/Abilities/FileManager/Get_Wp_Config_Constant.php' ),
			'create-zip-backup'      => array( 'includes/Abilities/FileManager/Create_Zip_Backup.php' ),
			'extract-zip-backup'     => array( 'includes/Abilities/FileManager/Extract_Zip_Backup.php' ),
			'upload-zip-backup'      => array( 'includes/Abilities/FileManager/Upload_Zip_Backup.php' ),
			'download-zip-backup'    => array( 'includes/Abilities/FileManager/Download_Zip_Backup.php' ),
			'list-zip-backups'       => array( 'includes/Abilities/FileManager/List_Zip_Backups.php' ),
			'delete-zip-backup'      => array( 'includes/Abilities/FileManager/Delete_Zip_Backup.php' ),
		);
	}

	/* --- Ordering: first-fires-only proves the check sequence ------------- */

	/**
	 * With every write check enabled and a file that violates several at once,
	 * only the FIRST-ORDERED refusal is returned. Cross-references the ordering
	 * documented in Hardening_Enforcer's class docblock:
	 *   extension → double-ext → sanitize → strict → mime → htaccess → write-size.
	 */
	public function test_ordering_extension_beats_all_other_write_checks(): void {
		// Turn every check on so the ability WOULD refuse under multiple rules.
		update_option( Hardening_Settings::OPTION_DANGEROUS_EXTENSIONS, array( 'exe' ) );
		update_option( Hardening_Settings::OPTION_BLOCK_DOUBLE_EXTENSIONS, true );
		update_option( Hardening_Settings::OPTION_SANITIZE_FILENAME_CHECK, true );
		update_option( Hardening_Settings::OPTION_STRICT_FILENAME_FILTER, true );
		update_option( Hardening_Settings::OPTION_MIME_TYPE_CHECK, true );
		update_option( Hardening_Settings::OPTION_WRITE_MAX_BYTES, 1024 );

		// Basename that would fail dangerous_extensions AND strict_filename
		// (contains "shell") AND sanitize_filename (has space) AND write_size.
		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/shell attack.exe',
			str_repeat( 'x', 5000 )
		);
		$this->assertNotNull( $result );
		$this->assertSame(
			'extension_blocked',
			$result['blocked_reason'],
			'Extension check must fire first when multiple checks would refuse'
		);
	}

	public function test_ordering_double_extension_beats_sanitize_strict_size(): void {
		// dangerous_extensions empty → extension check no-ops; the next
		// ordered check that fires is block_double_extensions.
		update_option( Hardening_Settings::OPTION_BLOCK_DOUBLE_EXTENSIONS, true );
		update_option( Hardening_Settings::OPTION_SANITIZE_FILENAME_CHECK, true );
		update_option( Hardening_Settings::OPTION_STRICT_FILENAME_FILTER, true );
		update_option( Hardening_Settings::OPTION_WRITE_MAX_BYTES, 1024 );

		// Basename "shell.php.jpg" hits double-ext + strict-filename + sanitize
		// (no problematic char here so sanitize actually passes) + write-size.
		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/shell.php.jpg',
			str_repeat( 'x', 5000 )
		);
		$this->assertSame( 'double_extension_blocked', $result['blocked_reason'] );
	}

	public function test_ordering_sanitize_beats_strict_and_size(): void {
		// Only sanitize + strict + size enabled. Basename with a space AND a
		// webshell marker AND huge content → sanitize fires first.
		update_option( Hardening_Settings::OPTION_SANITIZE_FILENAME_CHECK, true );
		update_option( Hardening_Settings::OPTION_STRICT_FILENAME_FILTER, true );
		update_option( Hardening_Settings::OPTION_WRITE_MAX_BYTES, 1024 );

		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/c99 attack.txt',
			str_repeat( 'x', 5000 )
		);
		$this->assertSame( 'filename_sanitize_failed', $result['blocked_reason'] );
	}

	public function test_ordering_size_check_fires_last(): void {
		// Everything OFF except the write-size cap. Only over-cap payload
		// triggers a refusal.
		update_option( Hardening_Settings::OPTION_WRITE_MAX_BYTES, 1024 );

		$result = Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/normal-file.txt',
			str_repeat( 'x', 5000 )
		);
		$this->assertSame( 'write_size_exceeded', $result['blocked_reason'] );
	}

	/* --- Snapshot-per-call: no caching between calls --------------------- */

	/**
	 * Spec Decision #2: no static caching. An admin flipping a toggle takes
	 * effect on the very next ability invocation.
	 */
	public function test_snapshot_is_read_on_every_call_no_caching(): void {
		// Call 1: extension blocklist empty → allowed.
		$this->assertNull( Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/probe.exe', 'x' ) );

		// Admin flips the toggle between calls.
		update_option( Hardening_Settings::OPTION_DANGEROUS_EXTENSIONS, array( 'exe' ) );

		// Call 2: the very next call must see the new snapshot.
		$result = Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/probe.exe', 'x' );
		$this->assertSame( 'extension_blocked', $result['blocked_reason'] );

		// Admin flips it back off.
		update_option( Hardening_Settings::OPTION_DANGEROUS_EXTENSIONS, array() );

		// Call 3: back to allowed on the very next call.
		$this->assertNull( Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . '/probe.exe', 'x' ) );
	}

	/* --- Every default-enabled option DOES enforce ----------------------- */

	/**
	 * If someone accidentally sets a default to `null` or empty in the
	 * seed, this proves the getters still fall back to the DEFAULT_* const
	 * and the check actually runs.
	 */
	public function test_defaults_from_hardening_settings_actually_enforce(): void {
		// Seed the same defaults the activator would seed.
		update_option( Hardening_Settings::OPTION_DANGEROUS_EXTENSIONS, Hardening_Settings::DEFAULT_DANGEROUS_EXTENSIONS );
		update_option( Hardening_Settings::OPTION_BLOCK_DOUBLE_EXTENSIONS, Hardening_Settings::DEFAULT_BLOCK_DOUBLE_EXTENSIONS );
		update_option( Hardening_Settings::OPTION_SANITIZE_FILENAME_CHECK, Hardening_Settings::DEFAULT_SANITIZE_FILENAME_CHECK );

		// Every default-listed dangerous extension should be refused.
		foreach ( Hardening_Settings::DEFAULT_DANGEROUS_EXTENSIONS as $ext ) {
			$result = Hardening_Enforcer::check_write( self::ABSPATH_FIXTURE . "/probe.{$ext}", 'x' );
			$this->assertNotNull( $result, "Default extension .{$ext} was NOT refused" );
			$this->assertSame( 'extension_blocked', $result['blocked_reason'] );
			$this->assertSame( $ext, $result['extension'] );
		}
	}

	/* --- .htaccess-scan basename edge cases ------------------------------ */

	public function test_htaccess_scan_ignores_htaccess_txt(): void {
		update_option( Hardening_Settings::OPTION_HTACCESS_DIRECTIVE_SCAN, true );
		// Basename "not-htaccess.txt" contains "htaccess" as a substring but
		// is NOT the literal ".htaccess" — scanner MUST ignore it.
		$this->assertNull( Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/not-htaccess.txt',
			'php_value display_errors 1'
		) );
	}

	public function test_htaccess_scan_ignores_dot_htaccess_dot_bak(): void {
		update_option( Hardening_Settings::OPTION_HTACCESS_DIRECTIVE_SCAN, true );
		// Backup filename — Apache won't parse it, so no scan needed.
		$this->assertNull( Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/.htaccess.bak',
			'AddType text/plain .foo'
		) );
	}

	public function test_htaccess_scan_empty_content_is_noop(): void {
		update_option( Hardening_Settings::OPTION_HTACCESS_DIRECTIVE_SCAN, true );
		// Writing an empty .htaccess (e.g. to reset it) must not refuse.
		$this->assertNull( Hardening_Enforcer::check_write(
			self::ABSPATH_FIXTURE . '/.htaccess',
			''
		) );
	}

	/* --- Sensitive-read basename edge cases ------------------------------ */

	public function test_sensitive_read_matches_basename_not_directory(): void {
		update_option( Hardening_Settings::OPTION_SENSITIVE_READ_DENYLIST, array( '.env' ) );
		// A directory NAMED ".env" is a legitimate path; only exact basename
		// on the target matters. A file INSIDE such a dir with a normal name
		// must not be refused.
		$this->assertNull( Hardening_Enforcer::check_read( self::ABSPATH_FIXTURE . '/.env/config.txt' ) );
	}

	public function test_sensitive_read_glob_ignores_files_without_matching_extension(): void {
		update_option( Hardening_Settings::OPTION_SENSITIVE_READ_DENYLIST, array( '*.key' ) );
		$this->assertNull( Hardening_Enforcer::check_read( self::ABSPATH_FIXTURE . '/backup.txt' ) );
		$this->assertNull( Hardening_Enforcer::check_read( self::ABSPATH_FIXTURE . '/key-notes.md' ) );
	}

	public function test_sensitive_read_glob_matches_only_full_extension(): void {
		update_option( Hardening_Settings::OPTION_SENSITIVE_READ_DENYLIST, array( '*.key' ) );
		// A file named "keyed.txt" has extension "txt", not "key", so must
		// pass — the glob is a full-extension match, not substring.
		$this->assertNull( Hardening_Enforcer::check_read( self::ABSPATH_FIXTURE . '/keyed.txt' ) );
	}

	/* --- Envelope shape completeness ------------------------------------- */

	/**
	 * Every new blocked_reason envelope MUST include the standard base keys
	 * (success, blocked_reason, path, message) — the ability adapter's
	 * schema validator depends on these being present, and callers rely on
	 * `path` to render error UIs. Missing any is a silent contract break.
	 */
	public function test_all_new_blocked_reason_envelopes_include_base_fields(): void {
		$scenarios = array(
			'extension_blocked'        => static function (): ?array {
				update_option( Hardening_Settings::OPTION_DANGEROUS_EXTENSIONS, array( 'exe' ) );
				return Hardening_Enforcer::check_write( '/fixture/probe.exe', 'x' );
			},
			'double_extension_blocked' => static function (): ?array {
				update_option( Hardening_Settings::OPTION_BLOCK_DOUBLE_EXTENSIONS, true );
				return Hardening_Enforcer::check_write( '/fixture/foo.php.jpg', 'x' );
			},
			'filename_sanitize_failed' => static function (): ?array {
				update_option( Hardening_Settings::OPTION_SANITIZE_FILENAME_CHECK, true );
				return Hardening_Enforcer::check_write( '/fixture/bad name.txt', 'x' );
			},
			'filename_strict_blocked'  => static function (): ?array {
				update_option( Hardening_Settings::OPTION_STRICT_FILENAME_FILTER, true );
				return Hardening_Enforcer::check_write( '/fixture/c99.txt', 'x' );
			},
			'mime_type_blocked'        => static function (): ?array {
				update_option( Hardening_Settings::OPTION_MIME_TYPE_CHECK, true );
				return Hardening_Enforcer::check_write( '/fixture/probe.xyz', 'x' );
			},
			'htaccess_directive_blocked' => static function (): ?array {
				update_option( Hardening_Settings::OPTION_HTACCESS_DIRECTIVE_SCAN, true );
				return Hardening_Enforcer::check_write( '/fixture/.htaccess', 'AddType foo .bar' );
			},
			'write_size_exceeded'      => static function (): ?array {
				update_option( Hardening_Settings::OPTION_WRITE_MAX_BYTES, 1024 );
				return Hardening_Enforcer::check_write( '/fixture/big.txt', str_repeat( 'x', 5000 ) );
			},
			'sensitive_read_blocked'   => static function (): ?array {
				update_option( Hardening_Settings::OPTION_SENSITIVE_READ_DENYLIST, array( '.env' ) );
				return Hardening_Enforcer::check_read( '/fixture/.env' );
			},
		);

		foreach ( $scenarios as $blocked_reason => $factory ) {
			// Reset options to avoid inter-scenario cross-fire.
			$this->setUp();
			$envelope = $factory();
			$this->assertNotNull( $envelope, "Scenario {$blocked_reason} did not return a refusal envelope" );
			$this->assertFalse( $envelope['success'], "Scenario {$blocked_reason} envelope success MUST be false" );
			$this->assertSame( $blocked_reason, $envelope['blocked_reason'] );
			$this->assertArrayHasKey( 'path', $envelope, "Scenario {$blocked_reason} missing 'path'" );
			$this->assertArrayHasKey( 'message', $envelope, "Scenario {$blocked_reason} missing 'message'" );
			$this->assertIsString( $envelope['message'] );
			$this->assertNotSame( '', $envelope['message'] );
		}
	}

	/* --- Enforcer const integrity (protect the marker/directive lists) --- */

	/**
	 * The enforcer's private const arrays are the substantive security
	 * contract. Verify they contain exactly the entries the spec + contract
	 * doc name, so an accidental typo or truncation is caught early.
	 */
	public function test_enforcer_constants_match_spec(): void {
		$src = $this->read_source( 'includes/Abilities/Utilities/Hardening_Enforcer.php' );

		foreach ( array( 'AddType', 'SetHandler', 'php_value', 'php_flag', 'auto_prepend', 'auto_append' ) as $directive ) {
			$this->assertMatchesRegularExpression(
				"/'{$directive}'/",
				$src,
				"HTACCESS_DIRECTIVES const missing entry: {$directive}"
			);
		}
		foreach ( array( 'c99', 'r57', 'wso', 'b374k', 'weevely', 'shell', 'alfa', 'bypass', 'backdoor' ) as $marker ) {
			$this->assertMatchesRegularExpression(
				"/'{$marker}'/",
				$src,
				"STRICT_FILENAME_MARKERS const missing entry: {$marker}"
			);
		}
		foreach ( array( 'php', 'txt', 'log', 'json', 'xml', 'css', 'js', 'md', 'html', 'htm', 'htaccess' ) as $ext ) {
			$this->assertMatchesRegularExpression(
				"/'{$ext}'/",
				$src,
				"MIME_ALWAYS_ALLOWED const missing entry: {$ext}"
			);
		}
		foreach ( array( '.htaccess', '.htpasswd', '.user.ini' ) as $dotfile ) {
			$this->assertMatchesRegularExpression(
				"/'\\{$dotfile}'/",
				$src,
				"ALLOWED_DOTFILES const missing entry: {$dotfile}"
			);
		}
	}

	/* --- File_Mods_Guard fires BEFORE Hardening_Enforcer (source proof) --- */

	/**
	 * @dataProvider write_ability_provider
	 */
	public function test_file_mods_guard_fires_before_enforcer( string $relative_path ): void {
		$src = $this->read_source( $relative_path );
		$fmg_pos      = strpos( $src, 'File_Mods_Guard::blocked_response(' );
		$enforcer_pos = strpos( $src, 'Hardening_Enforcer::check_write(' );
		$this->assertNotFalse( $fmg_pos, "{$relative_path} missing File_Mods_Guard call" );
		$this->assertNotFalse( $enforcer_pos, "{$relative_path} missing enforcer call" );
		$this->assertLessThan(
			$enforcer_pos,
			$fmg_pos,
			"File_Mods_Guard MUST fire before Hardening_Enforcer in {$relative_path} — otherwise DISALLOW_FILE_MODS/EDIT sites would see hardening refusals instead of the correct file_mods_disabled/file_edit_disabled"
		);
	}
}
