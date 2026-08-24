<?php
/**
 * Structural tests for Feature 089 — file abilities consolidation.
 *
 * Verifies (a) the three new file-manager abilities are in place with the
 * expected slugs, categories, capability checks, and guards; (b) the
 * PROTECTED_FILES guard was added to Create_File and Edit_File; (c) the
 * six removed classes are gone; (d) the bootstrap wires the three new
 * abilities and no longer references the six removed ones.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Feature_089_File_Consolidation.
 */
class Test_Feature_089_File_Consolidation extends WP_UnitTestCase {

	/** @var string */
	private string $plugin_root = '';

	/** @var array<string,string> Cached file contents keyed by relative path. */
	private array $files = array();

	protected function setUp(): void {
		parent::setUp();
		$this->plugin_root = dirname( __DIR__, 3 );

		foreach (
			array(
				'list_directory'                => 'includes/Abilities/FileManager/List_Directory.php',
				'copy_file'                     => 'includes/Abilities/FileManager/Copy_File.php',
				'move_file'                     => 'includes/Abilities/FileManager/Move_File.php',
				'create_file'                   => 'includes/Abilities/FileManager/Create_File.php',
				'edit_file'                     => 'includes/Abilities/FileManager/Edit_File.php',
				'bootstrap'                     => 'includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php',
			) as $key => $rel
		) {
			$this->files[ $key ] = (string) file_get_contents( $this->plugin_root . '/' . $rel );
		}
	}

	/* -------------------------------------------------------------------- */
	/* List_Directory                                                        */
	/* -------------------------------------------------------------------- */

	public function test_list_directory_class_exists_and_extends_ability_definition(): void {
		$this->assertFileExists( $this->plugin_root . '/includes/Abilities/FileManager/List_Directory.php' );
		$this->assertStringContainsString( 'extends Ability_Definition', $this->files['list_directory'] );
	}

	public function test_list_directory_uses_expected_slug_and_category(): void {
		$this->assertStringContainsString( "'file-manager/list-directory'", $this->files['list_directory'] );
		$this->assertStringContainsString( "'acrossai-abilities-manager-file-manager'", $this->files['list_directory'] );
	}

	public function test_list_directory_gates_on_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\\(\\s*'manage_options'\\s*\\)/",
			$this->files['list_directory']
		);
	}

	public function test_list_directory_enforces_max_depth_and_max_entries(): void {
		$this->assertStringContainsString( 'setMaxDepth', $this->files['list_directory'] );
		$this->assertStringContainsString( 'DEFAULT_MAX_DEPTH', $this->files['list_directory'] );
		$this->assertStringContainsString( 'DEFAULT_MAX_ENTRIES', $this->files['list_directory'] );
		$this->assertStringContainsString( 'HARD_CAP_MAX_ENTRIES', $this->files['list_directory'] );
		$this->assertStringContainsString( "'truncated' => \$truncated", $this->files['list_directory'] );
	}

	public function test_list_directory_scopes_paths_to_absroot(): void {
		$this->assertStringContainsString( 'realpath( ABSPATH )', $this->files['list_directory'] );
		$this->assertStringContainsString( "'blocked_reason' => 'invalid_path'", $this->files['list_directory'] );
	}

	public function test_list_directory_refuses_when_path_is_not_a_directory(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'not_a_directory'", $this->files['list_directory'] );
	}

	public function test_list_directory_does_not_follow_symlinks(): void {
		// The iterator setup uses SKIP_DOTS but no FOLLOW_SYMLINKS flag, and
		// the loop explicitly skips isLink().
		$this->assertStringNotContainsString( 'FOLLOW_SYMLINKS', $this->files['list_directory'] );
		$this->assertStringContainsString( '->isLink()', $this->files['list_directory'] );
	}

	public function test_list_directory_annotations_are_readonly_and_idempotent(): void {
		$this->assertMatchesRegularExpression(
			"/'readonly'\\s*=>\\s*true[^)]*'destructive'\\s*=>\\s*false[^)]*'idempotent'\\s*=>\\s*true/s",
			$this->files['list_directory']
		);
	}

	/* -------------------------------------------------------------------- */
	/* Copy_File                                                             */
	/* -------------------------------------------------------------------- */

	public function test_copy_file_class_exists_and_extends_ability_definition(): void {
		$this->assertFileExists( $this->plugin_root . '/includes/Abilities/FileManager/Copy_File.php' );
		$this->assertStringContainsString( 'extends Ability_Definition', $this->files['copy_file'] );
	}

	public function test_copy_file_uses_expected_slug_and_category(): void {
		$this->assertStringContainsString( "'file-manager/copy-file'", $this->files['copy_file'] );
		$this->assertStringContainsString( "'acrossai-abilities-manager-file-manager'", $this->files['copy_file'] );
	}

	public function test_copy_file_gates_on_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\\(\\s*'manage_options'\\s*\\)/",
			$this->files['copy_file']
		);
	}

	public function test_copy_file_declares_protected_files_constant(): void {
		$this->assertStringContainsString( "private const PROTECTED_FILES", $this->files['copy_file'] );
		$this->assertStringContainsString( "'wp-config.php'", $this->files['copy_file'] );
		$this->assertStringContainsString( "'.htaccess'", $this->files['copy_file'] );
	}

	public function test_copy_file_refuses_protected_destination(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'protected_write'", $this->files['copy_file'] );
	}

	public function test_copy_file_refuses_when_destination_exists_without_overwrite(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'destination_exists'", $this->files['copy_file'] );
		$this->assertMatchesRegularExpression(
			"/file_exists\\(\\s*\\\$dest_abs\\s*\\)/",
			$this->files['copy_file']
		);
	}

	public function test_copy_file_refuses_missing_source(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'source_not_found'", $this->files['copy_file'] );
	}

	public function test_copy_file_routes_through_file_mods_guard(): void {
		$this->assertStringContainsString( 'File_Mods_Guard::blocked_response()', $this->files['copy_file'] );
	}

	public function test_copy_file_uses_native_copy_function(): void {
		$this->assertMatchesRegularExpression(
			"/copy\\(\\s*\\\$src_real,\\s*\\\$dest_abs\\s*\\)/",
			$this->files['copy_file']
		);
	}

	/* -------------------------------------------------------------------- */
	/* Move_File                                                             */
	/* -------------------------------------------------------------------- */

	public function test_move_file_class_exists_and_extends_ability_definition(): void {
		$this->assertFileExists( $this->plugin_root . '/includes/Abilities/FileManager/Move_File.php' );
		$this->assertStringContainsString( 'extends Ability_Definition', $this->files['move_file'] );
	}

	public function test_move_file_uses_expected_slug_and_category(): void {
		$this->assertStringContainsString( "'file-manager/move-file'", $this->files['move_file'] );
		$this->assertStringContainsString( "'acrossai-abilities-manager-file-manager'", $this->files['move_file'] );
	}

	public function test_move_file_gates_on_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\\(\\s*'manage_options'\\s*\\)/",
			$this->files['move_file']
		);
	}

	public function test_move_file_declares_protected_files_constant(): void {
		$this->assertStringContainsString( "private const PROTECTED_FILES", $this->files['move_file'] );
		$this->assertStringContainsString( "'wp-config.php'", $this->files['move_file'] );
		$this->assertStringContainsString( "'.htaccess'", $this->files['move_file'] );
	}

	public function test_move_file_refuses_protected_source(): void {
		// Two independent PROTECTED_FILES membership checks (source + destination).
		$this->assertGreaterThanOrEqual(
			2,
			substr_count( $this->files['move_file'], "in_array( \$" )
		);
	}

	public function test_move_file_refuses_protected_destination(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'protected_write'", $this->files['move_file'] );
	}

	public function test_move_file_refuses_when_destination_exists_without_overwrite(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'destination_exists'", $this->files['move_file'] );
	}

	public function test_move_file_routes_through_file_mods_guard(): void {
		$this->assertStringContainsString( 'File_Mods_Guard::blocked_response()', $this->files['move_file'] );
	}

	public function test_move_file_uses_native_rename_function(): void {
		$this->assertMatchesRegularExpression(
			"/rename\\(\\s*\\\$src_real,\\s*\\\$dest_abs\\s*\\)/",
			$this->files['move_file']
		);
	}

	public function test_move_file_annotations_are_destructive(): void {
		$this->assertMatchesRegularExpression(
			"/'readonly'\\s*=>\\s*false[^)]*'destructive'\\s*=>\\s*true/s",
			$this->files['move_file']
		);
	}

	/* -------------------------------------------------------------------- */
	/* Create_File / Edit_File — PROTECTED_FILES guard added                 */
	/* -------------------------------------------------------------------- */

	public function test_create_file_now_declares_protected_files_constant(): void {
		$this->assertStringContainsString( "private const PROTECTED_FILES", $this->files['create_file'] );
		$this->assertStringContainsString( "'wp-config.php'", $this->files['create_file'] );
		$this->assertStringContainsString( "'.htaccess'", $this->files['create_file'] );
	}

	public function test_create_file_refuses_protected_target(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'protected_write'", $this->files['create_file'] );
	}

	public function test_edit_file_now_declares_protected_files_constant(): void {
		$this->assertStringContainsString( "private const PROTECTED_FILES", $this->files['edit_file'] );
		$this->assertStringContainsString( "'wp-config.php'", $this->files['edit_file'] );
		$this->assertStringContainsString( "'.htaccess'", $this->files['edit_file'] );
	}

	public function test_edit_file_refuses_protected_target(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'protected_write'", $this->files['edit_file'] );
	}

	/* -------------------------------------------------------------------- */
	/* Removed classes                                                       */
	/* -------------------------------------------------------------------- */

	public function test_six_duplicate_ability_files_are_deleted(): void {
		foreach (
			array(
				'includes/Abilities/Themes/Read_Theme_Code.php',
				'includes/Abilities/Themes/Read_Theme_Structure.php',
				'includes/Abilities/Themes/Edit_Theme_File.php',
				'includes/Abilities/Plugins/Read_Plugin_Code.php',
				'includes/Abilities/Plugins/Read_Plugin_Structure.php',
				'includes/Abilities/Plugins/Manage_Plugin_Files.php',
			) as $rel
		) {
			$this->assertFileDoesNotExist(
				$this->plugin_root . '/' . $rel,
				"Duplicate ability class must be deleted: $rel"
			);
		}
	}

	/* -------------------------------------------------------------------- */
	/* Bootstrap wiring                                                      */
	/* -------------------------------------------------------------------- */

	public function test_bootstrap_wires_three_new_file_manager_abilities(): void {
		$this->assertStringContainsString( 'new FileManager\\List_Directory()', $this->files['bootstrap'] );
		$this->assertStringContainsString( 'new FileManager\\Copy_File()', $this->files['bootstrap'] );
		$this->assertStringContainsString( 'new FileManager\\Move_File()', $this->files['bootstrap'] );
	}

	public function test_bootstrap_no_longer_wires_the_six_removed_abilities(): void {
		foreach (
			array(
				'Plugins\\Read_Plugin_Structure',
				'Plugins\\Read_Plugin_Code',
				'Plugins\\Manage_Plugin_Files',
				'Themes\\Read_Theme_Structure',
				'Themes\\Read_Theme_Code',
				'Themes\\Edit_Theme_File',
			) as $qualified
		) {
			$this->assertStringNotContainsString(
				$qualified,
				$this->files['bootstrap'],
				"Bootstrap must not reference removed class: $qualified"
			);
		}
	}
}
