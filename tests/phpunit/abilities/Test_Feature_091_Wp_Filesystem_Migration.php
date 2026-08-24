<?php
/**
 * Cross-cutting structural tests for Feature 091 — WP_Filesystem migration.
 *
 * Verifies for every migrated ability: (a) the class imports
 * Wp_Filesystem_Init and calls its blocked_response(); (b) the class
 * contains at least one $fs-> or $wp_filesystem-> invocation; (c) the
 * class does NOT contain any of the native filesystem functions we
 * migrated away from. Also asserts the three deferred zip abilities
 * STILL contain native calls (so future authors don't accidentally
 * partial-migrate them without the full design). Also verifies the
 * File_Info schema shrink (ctime/atime absent).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Feature_091_Wp_Filesystem_Migration.
 */
class Test_Feature_091_Wp_Filesystem_Migration extends WP_UnitTestCase {

	/** @var string */
	private string $plugin_root = '';

	/**
	 * 19 file-manager ability class files that MUST be migrated. Keyed by
	 * short slot name (matches contract file naming).
	 *
	 * @var array<string,string>
	 */
	private const MIGRATED = array(
		'read_file'              => 'includes/Abilities/FileManager/Read_File.php',
		'create_file'            => 'includes/Abilities/FileManager/Create_File.php',
		'edit_file'              => 'includes/Abilities/FileManager/Edit_File.php',
		'delete_file'            => 'includes/Abilities/FileManager/Delete_File.php',
		'copy_file'              => 'includes/Abilities/FileManager/Copy_File.php',
		'move_file'              => 'includes/Abilities/FileManager/Move_File.php',
		'append_file'            => 'includes/Abilities/FileManager/Append_File.php',
		'create_directory'       => 'includes/Abilities/FileManager/Create_Directory.php',
		'delete_directory'       => 'includes/Abilities/FileManager/Delete_Directory.php',
		'list_directory'         => 'includes/Abilities/FileManager/List_Directory.php',
		'file_info'              => 'includes/Abilities/FileManager/File_Info.php',
		'read_wp_config'         => 'includes/Abilities/FileManager/Read_Wp_Config.php',
		'edit_wp_config'         => 'includes/Abilities/FileManager/Edit_Wp_Config.php',
		'read_debug_log'         => 'includes/Abilities/FileManager/Read_Debug_Log.php',
		'clear_debug_log'        => 'includes/Abilities/FileManager/Clear_Debug_Log.php',
		'download_zip_backup'    => 'includes/Abilities/FileManager/Download_Zip_Backup.php',
		'delete_zip_backup'      => 'includes/Abilities/FileManager/Delete_Zip_Backup.php',
	);

	/**
	 * The 3 zip abilities deferred to feature 092. These MUST still contain
	 * their native calls (the negative assertion catches accidental
	 * partial-migration).
	 *
	 * @var array<string,string>
	 */
	private const DEFERRED = array(
		'create_zip_backup'  => 'includes/Abilities/FileManager/Create_Zip_Backup.php',
		'extract_zip_backup' => 'includes/Abilities/FileManager/Extract_Zip_Backup.php',
		'upload_zip_backup'  => 'includes/Abilities/FileManager/Upload_Zip_Backup.php',
	);

	/**
	 * Abilities that read the runtime PHP state (constant()) or delegate
	 * everything to a helper — they do NOT need Wp_Filesystem_Init and NOT
	 * having $fs-> calls in them is expected.
	 *
	 * @var array<string,string>
	 */
	private const NO_FS_NEEDED = array(
		'get_wp_config_constant' => 'includes/Abilities/FileManager/Get_Wp_Config_Constant.php',
		'list_zip_backups'       => 'includes/Abilities/FileManager/List_Zip_Backups.php',
	);

	/**
	 * The Wp_Filesystem_Init helper source location.
	 *
	 * @var string
	 */
	private const HELPER = 'includes/Abilities/Utilities/Wp_Filesystem_Init.php';

	protected function setUp(): void {
		parent::setUp();
		$this->plugin_root = dirname( __DIR__, 3 );
	}

	private function read( string $rel ): string {
		return (string) file_get_contents( $this->plugin_root . '/' . $rel );
	}

	/* -------------------------------------------------------------------- */
	/* Wp_Filesystem_Init helper                                             */
	/* -------------------------------------------------------------------- */

	public function test_helper_file_exists(): void {
		$this->assertFileExists( $this->plugin_root . '/' . self::HELPER );
	}

	public function test_helper_declares_expected_class_and_methods(): void {
		$src = $this->read( self::HELPER );
		$this->assertStringContainsString( 'final class Wp_Filesystem_Init', $src );
		$this->assertStringContainsString( 'public static function get()', $src );
		$this->assertStringContainsString( 'public static function blocked_response()', $src );
	}

	public function test_helper_returns_wp_error_on_init_failure(): void {
		$src = $this->read( self::HELPER );
		$this->assertStringContainsString( "'filesystem_unavailable'", $src );
		$this->assertStringContainsString( 'new \\WP_Error', $src );
	}

	public function test_helper_requires_file_php_and_calls_wp_filesystem(): void {
		$src = $this->read( self::HELPER );
		$this->assertStringContainsString( "function_exists( 'WP_Filesystem' )", $src );
		$this->assertStringContainsString( "require_once ABSPATH . 'wp-admin/includes/file.php'", $src );
		$this->assertStringContainsString( 'WP_Filesystem()', $src );
	}

	/* -------------------------------------------------------------------- */
	/* Every migrated ability calls the helper + uses $fs->                  */
	/* -------------------------------------------------------------------- */

	public function test_every_migrated_ability_calls_wp_filesystem_init(): void {
		foreach ( self::MIGRATED as $slot => $rel ) {
			$src = $this->read( $rel );
			$this->assertStringContainsString(
				'Wp_Filesystem_Init',
				$src,
				"Migrated ability [$slot] must import/use Wp_Filesystem_Init."
			);
			$this->assertStringContainsString(
				'Wp_Filesystem_Init::blocked_response()',
				$src,
				"Migrated ability [$slot] must call Wp_Filesystem_Init::blocked_response() at the top of execute()."
			);
		}
	}

	public function test_every_migrated_ability_uses_fs_object(): void {
		foreach ( self::MIGRATED as $slot => $rel ) {
			$src = $this->read( $rel );
			$this->assertMatchesRegularExpression(
				'/\\$(fs|wp_filesystem)->/',
				$src,
				"Migrated ability [$slot] must contain at least one \$fs-> or \$wp_filesystem-> call."
			);
		}
	}

	/* -------------------------------------------------------------------- */
	/* Every migrated ability drops the disallowed native calls              */
	/* -------------------------------------------------------------------- */

	/**
	 * @dataProvider provide_migrated_files
	 */
	public function test_migrated_ability_drops_native_filesystem_calls( string $slot, string $rel ): void {
		$src = $this->read( $rel );

		// wp_delete_file matches "\bdelete_file(", so exclude it from the
		// "delete_file(" pattern deliberately by requiring the negative
		// lookbehind wouldn't be portable — instead use a plain-string check
		// for the exact native form we'd have written pre-migration.
		$forbidden = array(
			'file_put_contents(',
			'file_get_contents(',
			'wp_delete_file(',
			'@copy(',
			'@rename(',
			'@unlink(',
			'@rmdir(',
			'@mkdir(',
			'FILE_APPEND',
			'RecursiveIteratorIterator',
			'RecursiveDirectoryIterator',
		);
		foreach ( $forbidden as $needle ) {
			$this->assertStringNotContainsString(
				$needle,
				$src,
				"Migrated ability [$slot] must not contain native filesystem call [$needle]."
			);
		}

		// The bare word patterns need regex boundaries to avoid matching
		// e.g. WP_Filesystem_Init or wp_mkdir_p or $this->rmdir.
		$forbidden_regex = array(
			'/(?<![\\w>])unlink\\(/'    => 'unlink(',
			'/(?<![\\w>])rmdir\\(/'     => 'rmdir(',
			'/(?<![\\w>_])mkdir\\(/'    => 'mkdir(',  // mkdir but not wp_mkdir_p
			'/(?<![\\w>])copy\\(/'      => 'copy(',
			'/(?<![\\w>])rename\\(/'    => 'rename(',
			'/(?<![\\w>])file_exists\\(/' => 'file_exists(',
			'/(?<![\\w>])is_file\\(/'   => 'is_file(',
			'/(?<![\\w>])is_dir\\(/'    => 'is_dir(',
			'/(?<![\\w>])is_readable\\(/' => 'is_readable(',
			'/(?<![\\w>])is_writable\\(/' => 'is_writable(',
			'/(?<![\\w>])filesize\\(/'  => 'filesize(',
			'/(?<![\\w>])filemtime\\(/' => 'filemtime(',
			'/(?<![\\w>])stat\\(/'      => 'stat(',
		);
		foreach ( $forbidden_regex as $regex => $label ) {
			$this->assertDoesNotMatchRegularExpression(
				$regex,
				$src,
				"Migrated ability [$slot] must not contain native filesystem call [$label] (regex: $regex)."
			);
		}
	}

	public static function provide_migrated_files(): array {
		$data = array();
		foreach ( self::MIGRATED as $slot => $rel ) {
			$data[ $slot ] = array( $slot, $rel );
		}
		return $data;
	}

	/* -------------------------------------------------------------------- */
	/* Deferred zip abilities STILL contain native calls                     */
	/* -------------------------------------------------------------------- */

	public function test_deferred_zip_abilities_carry_todo_marker(): void {
		foreach ( self::DEFERRED as $slot => $rel ) {
			$src = $this->read( $rel );
			$this->assertStringContainsString(
				'TODO(feature-092)',
				$src,
				"Deferred zip ability [$slot] must carry the feature-092 TODO marker."
			);
		}
	}

	public function test_deferred_zip_abilities_still_use_native_ziparchive(): void {
		foreach ( array( 'create_zip_backup', 'extract_zip_backup' ) as $slot ) {
			$src = $this->read( self::DEFERRED[ $slot ] );
			$this->assertMatchesRegularExpression(
				'/\\\\ZipArchive|new ZipArchive/',
				$src,
				"Deferred ability [$slot] must still reference ZipArchive (WP_Filesystem has no equivalent — deferred to feature 092)."
			);
		}
	}

	public function test_deferred_upload_zip_still_uses_fopen_handles(): void {
		$src = $this->read( self::DEFERRED['upload_zip_backup'] );
		$this->assertMatchesRegularExpression(
			'/(?<![\\w>])fopen\\(/',
			$src,
			'Deferred Upload_Zip_Backup must still use fopen() for chunked upload (deferred to feature 092).'
		);
	}

	/* -------------------------------------------------------------------- */
	/* File_Info schema shrink (BREAKING)                                    */
	/* -------------------------------------------------------------------- */

	public function test_file_info_response_schema_omits_ctime_and_atime(): void {
		$src = $this->read( self::MIGRATED['file_info'] );
		$this->assertStringNotContainsString( "'ctime'", $src );
		$this->assertStringNotContainsString( "'atime'", $src );
	}

	/* -------------------------------------------------------------------- */
	/* Get_Wp_Config_Constant is intentionally not migrated                  */
	/* -------------------------------------------------------------------- */

	public function test_get_wp_config_constant_has_no_fs_calls(): void {
		$src = $this->read( self::NO_FS_NEEDED['get_wp_config_constant'] );
		// Confirm the class explicitly documents why it doesn't need the helper.
		$this->assertStringContainsString( 'no filesystem I/O', $src );
		// And confirm it really doesn't perform any filesystem I/O.
		foreach ( array( 'file_put_contents(', 'file_get_contents(', '$fs->', '$wp_filesystem->' ) as $needle ) {
			$this->assertStringNotContainsString(
				$needle,
				$src,
				"Get_Wp_Config_Constant must contain no filesystem call [$needle]."
			);
		}
	}
}
