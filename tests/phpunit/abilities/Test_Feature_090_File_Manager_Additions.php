<?php
/**
 * Structural tests for Feature 090 — file-manager additions.
 *
 * Verifies (a) the four new ability classes are in place with the expected
 * slugs, categories, capability checks, and guards; (b) File_Info is
 * read-only and does NOT route through File_Mods_Guard; (c) Append_File
 * declares the PROTECTED_FILES constant used by peers; (d) Delete_Directory
 * declares PROTECTED_DIRS and requires confirm:true; (e) the bootstrap
 * wires all four abilities.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Feature_090_File_Manager_Additions.
 */
class Test_Feature_090_File_Manager_Additions extends WP_UnitTestCase {

	/** @var string */
	private string $plugin_root = '';

	/** @var array<string,string> Cached file contents keyed by slot name. */
	private array $files = array();

	protected function setUp(): void {
		parent::setUp();
		$this->plugin_root = dirname( __DIR__, 3 );

		foreach (
			array(
				'append_file'      => 'includes/Abilities/FileManager/Append_File.php',
				'create_directory' => 'includes/Abilities/FileManager/Create_Directory.php',
				'delete_directory' => 'includes/Abilities/FileManager/Delete_Directory.php',
				'file_info'        => 'includes/Abilities/FileManager/File_Info.php',
				'bootstrap'        => 'includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php',
			) as $key => $rel
		) {
			$this->files[ $key ] = (string) file_get_contents( $this->plugin_root . '/' . $rel );
		}
	}

	/* -------------------------------------------------------------------- */
	/* Append_File                                                           */
	/* -------------------------------------------------------------------- */

	public function test_append_file_class_exists_and_extends_ability_definition(): void {
		$this->assertFileExists( $this->plugin_root . '/includes/Abilities/FileManager/Append_File.php' );
		$this->assertStringContainsString( 'extends Ability_Definition', $this->files['append_file'] );
	}

	public function test_append_file_uses_expected_slug_and_category(): void {
		$this->assertStringContainsString( "'file-manager/append-file'", $this->files['append_file'] );
		$this->assertStringContainsString( "'acrossai-abilities-manager-file-manager'", $this->files['append_file'] );
	}

	public function test_append_file_gates_on_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\\(\\s*'manage_options'\\s*\\)/",
			$this->files['append_file']
		);
	}

	public function test_append_file_declares_protected_files_constant(): void {
		$this->assertStringContainsString( 'private const PROTECTED_FILES', $this->files['append_file'] );
		$this->assertStringContainsString( "'wp-config.php'", $this->files['append_file'] );
		$this->assertStringContainsString( "'.htaccess'", $this->files['append_file'] );
	}

	public function test_append_file_routes_through_file_mods_guard(): void {
		$this->assertStringContainsString( 'File_Mods_Guard::blocked_response()', $this->files['append_file'] );
	}

	public function test_append_file_uses_file_append_and_lock_ex(): void {
		$this->assertStringContainsString( 'FILE_APPEND | LOCK_EX', $this->files['append_file'] );
	}

	public function test_append_file_refuses_missing_source(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'source_not_found'", $this->files['append_file'] );
	}

	public function test_append_file_refuses_protected_target(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'protected_write'", $this->files['append_file'] );
	}

	public function test_append_file_annotations_are_not_readonly_and_not_idempotent(): void {
		$this->assertMatchesRegularExpression(
			"/'readonly'\\s*=>\\s*false[^)]*'destructive'\\s*=>\\s*false[^)]*'idempotent'\\s*=>\\s*false/s",
			$this->files['append_file']
		);
	}

	/* -------------------------------------------------------------------- */
	/* Create_Directory                                                      */
	/* -------------------------------------------------------------------- */

	public function test_create_directory_class_exists_and_extends_ability_definition(): void {
		$this->assertFileExists( $this->plugin_root . '/includes/Abilities/FileManager/Create_Directory.php' );
		$this->assertStringContainsString( 'extends Ability_Definition', $this->files['create_directory'] );
	}

	public function test_create_directory_uses_expected_slug_and_category(): void {
		$this->assertStringContainsString( "'file-manager/create-directory'", $this->files['create_directory'] );
		$this->assertStringContainsString( "'acrossai-abilities-manager-file-manager'", $this->files['create_directory'] );
	}

	public function test_create_directory_gates_on_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\\(\\s*'manage_options'\\s*\\)/",
			$this->files['create_directory']
		);
	}

	public function test_create_directory_routes_through_file_mods_guard(): void {
		$this->assertStringContainsString( 'File_Mods_Guard::blocked_response()', $this->files['create_directory'] );
	}

	public function test_create_directory_uses_wp_mkdir_p_for_recursive_path(): void {
		$this->assertStringContainsString( 'wp_mkdir_p( $abs )', $this->files['create_directory'] );
	}

	public function test_create_directory_uses_mkdir_for_non_recursive_path(): void {
		$this->assertMatchesRegularExpression(
			"/mkdir\\(\\s*\\\$abs\\s*\\)/",
			$this->files['create_directory']
		);
	}

	public function test_create_directory_reports_created_false_when_already_exists(): void {
		$this->assertStringContainsString( "'created' => false", $this->files['create_directory'] );
	}

	public function test_create_directory_refuses_path_that_is_a_file(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'path_is_file'", $this->files['create_directory'] );
	}

	public function test_create_directory_annotations_are_idempotent_non_destructive(): void {
		$this->assertMatchesRegularExpression(
			"/'readonly'\\s*=>\\s*false[^)]*'destructive'\\s*=>\\s*false[^)]*'idempotent'\\s*=>\\s*true/s",
			$this->files['create_directory']
		);
	}

	/* -------------------------------------------------------------------- */
	/* Delete_Directory                                                      */
	/* -------------------------------------------------------------------- */

	public function test_delete_directory_class_exists_and_extends_ability_definition(): void {
		$this->assertFileExists( $this->plugin_root . '/includes/Abilities/FileManager/Delete_Directory.php' );
		$this->assertStringContainsString( 'extends Ability_Definition', $this->files['delete_directory'] );
	}

	public function test_delete_directory_uses_expected_slug_and_category(): void {
		$this->assertStringContainsString( "'file-manager/delete-directory'", $this->files['delete_directory'] );
		$this->assertStringContainsString( "'acrossai-abilities-manager-file-manager'", $this->files['delete_directory'] );
	}

	public function test_delete_directory_gates_on_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\\(\\s*'manage_options'\\s*\\)/",
			$this->files['delete_directory']
		);
	}

	public function test_delete_directory_requires_confirm_true(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'confirmation_required'", $this->files['delete_directory'] );
	}

	public function test_delete_directory_routes_through_file_mods_guard(): void {
		$this->assertStringContainsString( 'File_Mods_Guard::blocked_response()', $this->files['delete_directory'] );
	}

	public function test_delete_directory_declares_protected_dirs_constant(): void {
		$this->assertStringContainsString( 'private const PROTECTED_DIRS', $this->files['delete_directory'] );
		foreach (
			array(
				"''",
				"'wp-admin'",
				"'wp-includes'",
				"'wp-content'",
				"'wp-content/plugins'",
				"'wp-content/themes'",
				"'wp-content/mu-plugins'",
				"'wp-content/uploads'",
				"'wp-content/plugins/acrossai-abilities-manager'",
			) as $needle
		) {
			$this->assertStringContainsString(
				$needle,
				$this->files['delete_directory'],
				"PROTECTED_DIRS must include $needle"
			);
		}
	}

	public function test_delete_directory_refuses_protected_directory(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'protected_directory'", $this->files['delete_directory'] );
	}

	public function test_delete_directory_uses_recursive_iterator_with_child_first(): void {
		$this->assertStringContainsString( 'RecursiveIteratorIterator::CHILD_FIRST', $this->files['delete_directory'] );
		$this->assertStringContainsString( 'RecursiveDirectoryIterator::SKIP_DOTS', $this->files['delete_directory'] );
	}

	public function test_delete_directory_skips_symlinks(): void {
		$this->assertStringContainsString( '->isLink()', $this->files['delete_directory'] );
	}

	public function test_delete_directory_reports_partial_entries_on_failure(): void {
		$this->assertStringContainsString( "'entries_removed' => \$entries_removed", $this->files['delete_directory'] );
	}

	public function test_delete_directory_annotations_are_destructive_idempotent(): void {
		$this->assertMatchesRegularExpression(
			"/'readonly'\\s*=>\\s*false[^)]*'destructive'\\s*=>\\s*true[^)]*'idempotent'\\s*=>\\s*true/s",
			$this->files['delete_directory']
		);
	}

	/* -------------------------------------------------------------------- */
	/* File_Info                                                             */
	/* -------------------------------------------------------------------- */

	public function test_file_info_class_exists_and_extends_ability_definition(): void {
		$this->assertFileExists( $this->plugin_root . '/includes/Abilities/FileManager/File_Info.php' );
		$this->assertStringContainsString( 'extends Ability_Definition', $this->files['file_info'] );
	}

	public function test_file_info_uses_expected_slug_and_category(): void {
		$this->assertStringContainsString( "'file-manager/file-info'", $this->files['file_info'] );
		$this->assertStringContainsString( "'acrossai-abilities-manager-file-manager'", $this->files['file_info'] );
	}

	public function test_file_info_gates_on_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\\(\\s*'manage_options'\\s*\\)/",
			$this->files['file_info']
		);
	}

	public function test_file_info_does_not_route_through_file_mods_guard(): void {
		$this->assertStringNotContainsString( 'File_Mods_Guard', $this->files['file_info'] );
	}

	public function test_file_info_calls_native_stat(): void {
		$this->assertMatchesRegularExpression(
			"/stat\\(\\s*\\\$candidate\\s*\\)/",
			$this->files['file_info']
		);
	}

	public function test_file_info_derives_octal_mode_from_stat(): void {
		$this->assertStringContainsString( 'decoct(', $this->files['file_info'] );
	}

	public function test_file_info_guards_posix_functions_behind_function_exists(): void {
		$this->assertMatchesRegularExpression(
			"/function_exists\\(\\s*'posix_getpwuid'\\s*\\)/",
			$this->files['file_info']
		);
		$this->assertMatchesRegularExpression(
			"/function_exists\\(\\s*'posix_getgrgid'\\s*\\)/",
			$this->files['file_info']
		);
	}

	public function test_file_info_refuses_missing_path(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'path_not_found'", $this->files['file_info'] );
	}

	public function test_file_info_annotations_are_readonly_idempotent(): void {
		$this->assertMatchesRegularExpression(
			"/'readonly'\\s*=>\\s*true[^)]*'destructive'\\s*=>\\s*false[^)]*'idempotent'\\s*=>\\s*true/s",
			$this->files['file_info']
		);
	}

	/* -------------------------------------------------------------------- */
	/* Bootstrap wiring                                                      */
	/* -------------------------------------------------------------------- */

	public function test_bootstrap_wires_all_four_new_abilities(): void {
		$this->assertStringContainsString( 'new FileManager\\Append_File()', $this->files['bootstrap'] );
		$this->assertStringContainsString( 'new FileManager\\Create_Directory()', $this->files['bootstrap'] );
		$this->assertStringContainsString( 'new FileManager\\Delete_Directory()', $this->files['bootstrap'] );
		$this->assertStringContainsString( 'new FileManager\\File_Info()', $this->files['bootstrap'] );
	}
}
