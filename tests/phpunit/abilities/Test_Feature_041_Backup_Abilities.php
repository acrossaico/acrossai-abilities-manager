<?php
/**
 * Structural tests for the Feature 041 backup/restore + update abilities.
 *
 * Source-inspection only, mirroring the existing
 * tests/phpunit/abilities/Absorbed/ suite: the plugin's stub bootstrap can't
 * safely construct the full plugin, so instead each ability source file is
 * checked for the required scaffolding (Ability_Definition extend, args
 * shape, rebranded slugs, security guards).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Feature_041_Backup_Abilities.
 */
class Test_Feature_041_Backup_Abilities extends WP_UnitTestCase {

	/**
	 * Absolute paths to every ability source file exercised by these tests.
	 *
	 * @var array<string,string>
	 */
	private array $sources = array();

	/**
	 * Path to the bootstrap file that must instantiate every new ability.
	 *
	 * @var string
	 */
	private string $bootstrap_source = '';

	/**
	 * Path to the Backups_Storage utility.
	 *
	 * @var string
	 */
	private string $backups_storage_source = '';

	/**
	 * Path to the Zip_Target_Resolver utility.
	 *
	 * @var string
	 */
	private string $target_resolver_source = '';

	/**
	 * Load every source file once per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$plugin_root = dirname( __DIR__, 3 );

		$this->sources = array(
			'zip_create'    => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/FileManager/Create_Zip_Backup.php'
			),
			'zip_upload'    => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/FileManager/Upload_Zip_Backup.php'
			),
			'zip_extract'   => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/FileManager/Extract_Zip_Backup.php'
			),
			'zip_download'  => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/FileManager/Download_Zip_Backup.php'
			),
			'zip_list'      => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/FileManager/List_Zip_Backups.php'
			),
			'zip_delete'    => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/FileManager/Delete_Zip_Backup.php'
			),
			'plugin_update' => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Plugins/Update_Plugin.php'
			),
			'theme_update'  => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Themes/Update_Theme.php'
			),
		);

		$this->bootstrap_source       = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
		);
		$this->backups_storage_source = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Utilities/Backups_Storage.php'
		);
		$this->target_resolver_source = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Utilities/Zip_Target_Resolver.php'
		);
	}

	// =========================================================================
	// Every new ability extends Ability_Definition and carries the full args shape
	// =========================================================================

	/**
	 * Every ability class extends Ability_Definition — same audit the Feature
	 * 046 suite runs against its samples.
	 *
	 * @return void
	 */
	public function test_all_abilities_extend_ability_definition(): void {
		foreach ( $this->sources as $tag => $src ) {
			$this->assertStringContainsString(
				'extends Ability_Definition',
				$src,
				"$tag must extend Ability_Definition"
			);
		}
	}

	/**
	 * Every ability declares the required top-level `args` keys.
	 *
	 * @return void
	 */
	public function test_all_abilities_carry_full_args_shape(): void {
		$required_keys = array(
			"'name'",
			"'label'",
			"'description'",
			"'category'",
			"'execute_callback'",
			"'permission_callback'",
			"'input_schema'",
			"'output_schema'",
			"'meta'",
		);
		foreach ( $this->sources as $tag => $src ) {
			foreach ( $required_keys as $key ) {
				$this->assertStringContainsString(
					$key,
					$src,
					"$tag must expose args key $key"
				);
			}
		}
	}

	// =========================================================================
	// Slugs / categories align with the rebranded namespace
	// =========================================================================

	/**
	 * Every ability name uses the acrossai/ prefix, and the
	 * category matches the FileManager / Plugins / Themes rebranded slugs.
	 *
	 * @return void
	 */
	public function test_all_abilities_use_rebranded_slugs(): void {
		$expected = array(
			'zip_create'    => array(
				'name'     => 'file-manager/create-zip-backup',
				'category' => 'acrossai-file-manager',
			),
			'zip_upload'    => array(
				'name'     => 'file-manager/upload-zip-backup',
				'category' => 'acrossai-file-manager',
			),
			'zip_extract'   => array(
				'name'     => 'file-manager/extract-zip-backup',
				'category' => 'acrossai-file-manager',
			),
			'zip_download'  => array(
				'name'     => 'file-manager/download-zip-backup',
				'category' => 'acrossai-file-manager',
			),
			'zip_list'      => array(
				'name'     => 'file-manager/list-zip-backups',
				'category' => 'acrossai-file-manager',
			),
			'zip_delete'    => array(
				'name'     => 'file-manager/delete-zip-backup',
				'category' => 'acrossai-file-manager',
			),
			'plugin_update' => array(
				'name'     => 'plugins/update-plugin',
				'category' => 'acrossai-plugins',
			),
			'theme_update'  => array(
				'name'     => 'themes/update-theme',
				'category' => 'acrossai-themes',
			),
		);
		foreach ( $expected as $tag => $pair ) {
			$this->assertStringContainsString(
				"'{$pair['name']}'",
				$this->sources[ $tag ],
				"$tag must carry rebranded ability name"
			);
			$this->assertStringContainsString(
				"'{$pair['category']}'",
				$this->sources[ $tag ],
				"$tag must carry rebranded category slug"
			);
		}
	}

	// =========================================================================
	// Security posture
	// =========================================================================

	/**
	 * Every ability declares a permission_callback.
	 *
	 * @return void
	 */
	public function test_all_abilities_declare_permission_callback(): void {
		foreach ( $this->sources as $tag => $src ) {
			$this->assertStringContainsString(
				"'permission_callback'",
				$src,
				"$tag must declare a permission_callback"
			);
		}
	}

	/**
	 * Every ability's permission_callback gates via at least `manage_options`.
	 * Plugin/Update_Theme additionally require the WP update capability.
	 *
	 * @return void
	 */
	public function test_all_abilities_use_manage_options_gate(): void {
		foreach ( $this->sources as $tag => $src ) {
			$this->assertMatchesRegularExpression(
				"/current_user_can\(\s*'manage_options'\s*\)/",
				$src,
				"$tag must gate via current_user_can( 'manage_options' )"
			);
		}
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'update_plugins'\s*\)/",
			$this->sources['plugin_update'],
			'plugin_update must additionally gate via update_plugins.'
		);
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'update_themes'\s*\)/",
			$this->sources['theme_update'],
			'theme_update must additionally gate via update_themes.'
		);
	}

	/**
	 * Destructive/mutating abilities must honour the DISALLOW_FILE_MODS guard
	 * via File_Mods_Guard::blocked_response(). Read-only Download_Zip_Backup and
	 * List_Zip_Backups are exempt.
	 *
	 * @return void
	 */
	public function test_mutating_abilities_call_file_mods_guard(): void {
		$must_guard = array(
			'zip_upload',
			'zip_extract',
			'zip_delete',
			'plugin_update',
			'theme_update',
		);
		foreach ( $must_guard as $tag ) {
			$this->assertStringContainsString(
				'File_Mods_Guard::blocked_response',
				$this->sources[ $tag ],
				"$tag must invoke File_Mods_Guard::blocked_response()"
			);
		}
	}

	/**
	 * Extract_Zip_Backup must audit archive entries for zip-slip / traversal before
	 * extraction. The audit rejects `..` segments and absolute paths.
	 *
	 * @return void
	 */
	public function test_zip_extract_audits_for_traversal(): void {
		$src = $this->sources['zip_extract'];
		$this->assertStringContainsString(
			"'..'",
			$src,
			'Extract_Zip_Backup must reject entries containing ".." segments.'
		);
		$this->assertMatchesRegularExpression(
			"/substr\(\s*\\\$name,\s*0,\s*1\s*\)/",
			$src,
			'Extract_Zip_Backup must reject entries with an absolute path (leading "/").'
		);
	}

	/**
	 * Upload_Zip_Backup must validate the finalized bytes carry a valid zip magic
	 * signature (PK\\x03\\x04 / PK\\x05\\x06 / PK\\x07\\x08) before moving
	 * the archive into acrossai-backups/.
	 *
	 * @return void
	 */
	public function test_zip_upload_validates_zip_magic(): void {
		$this->assertStringContainsString(
			'"PK\\x03\\x04"',
			$this->sources['zip_upload'],
			'Upload_Zip_Backup must include the standard local-file-header magic check.'
		);
	}

	/**
	 * Create_Zip_Backup's include_hidden=false path must check EVERY segment of the
	 * relative path, not just the current entry basename. Otherwise
	 * SELF_FIRST would still descend into files INSIDE a hidden directory
	 * (e.g. .git/objects/xxx) after `continue`-ing on the top-level .git/
	 * entry. Enforces the fix inspired by the reference download-plugin.
	 *
	 * @return void
	 */
	public function test_zip_create_skips_hidden_at_every_segment(): void {
		$src = $this->sources['zip_create'];
		$this->assertStringContainsString(
			'has_hidden_segment',
			$src,
			'Create_Zip_Backup must consult a per-segment hidden-path check.'
		);
		$this->assertMatchesRegularExpression(
			"/explode\(\s*'\\/',\s*\\\$relative\s*\)/",
			$src,
			'has_hidden_segment must explode on "/" so every path segment is inspected.'
		);
	}

	// =========================================================================
	// Bootstrap wires every new ability
	// =========================================================================

	/**
	 * The core bootstrap instantiates every ability so its constructor hooks
	 * `acrossai_abilities_api_init`.
	 *
	 * @return void
	 */
	public function test_bootstrap_instantiates_every_new_ability(): void {
		$expected = array(
			'new FileManager\\Create_Zip_Backup()',
			'new FileManager\\Upload_Zip_Backup()',
			'new FileManager\\Extract_Zip_Backup()',
			'new FileManager\\Download_Zip_Backup()',
			'new FileManager\\List_Zip_Backups()',
			'new FileManager\\Delete_Zip_Backup()',
			'new Plugins\\Update_Plugin()',
			'new Themes\\Update_Theme()',
		);
		foreach ( $expected as $needle ) {
			$this->assertStringContainsString(
				$needle,
				$this->bootstrap_source,
				"Bootstrap must contain: $needle"
			);
		}
	}

	/**
	 * Bootstrap registers the Upload_Zip_Backup chunk-sweeper cron the same way it
	 * registers the Upload_Media sweeper.
	 *
	 * @return void
	 */
	public function test_bootstrap_registers_zip_upload_sweeper(): void {
		$this->assertStringContainsString(
			'FileManager\\Upload_Zip_Backup::CHUNK_SWEEP_HOOK',
			$this->bootstrap_source
		);
		$this->assertStringContainsString(
			"FileManager\\Upload_Zip_Backup::class, 'sweep_chunk_sessions'",
			$this->bootstrap_source
		);
		$this->assertStringContainsString(
			'FileManager\\Upload_Zip_Backup::register_sweep_cron();',
			$this->bootstrap_source
		);
	}

	// =========================================================================
	// Utilities carry the expected surface
	// =========================================================================

	/**
	 * Backups_Storage exposes the constants and helpers every Zip_* ability
	 * imports.
	 *
	 * @return void
	 */
	public function test_backups_storage_exposes_required_surface(): void {
		$src = $this->backups_storage_source;
		foreach (
			array(
				"public const BACKUPS_DIR = 'acrossai-backups';",
				"public const STAGING_DIR = 'acrossai-staging';",
				'public static function backups_path()',
				'public static function staging_path()',
				'public static function resolve_managed_path(',
				'public static function random_backup_filename(',
				'public static function list_entries(',
				'public static function sha256_of(',
			) as $needle
		) {
			$this->assertStringContainsString(
				$needle,
				$src,
				"Backups_Storage must expose: $needle"
			);
		}
	}

	/**
	 * Backups_Storage writes hardening files (.htaccess + index.php) on first
	 * use but MUST allow direct downloads of .zip files (otherwise Create_Zip_Backup's
	 * returned file_url is unreachable).
	 *
	 * @return void
	 */
	public function test_backups_storage_hardening_allows_zip_downloads(): void {
		$src = $this->backups_storage_source;
		$this->assertStringContainsString(
			'.htaccess',
			$src,
			'Backups_Storage must write an .htaccess for hardening.'
		);
		$this->assertStringContainsString(
			'index.php',
			$src,
			'Backups_Storage must write an index.php for enumeration defense.'
		);
		$this->assertStringNotContainsString(
			'Deny from all\n<IfModule',
			$src,
			'Backups_Storage must NOT globally deny all requests — .zip downloads must remain reachable via file_url.'
		);
		$this->assertMatchesRegularExpression(
			'/FilesMatch[^\n]+\\\.\\(php/',
			$src,
			'Backups_Storage hardening must scope the deny to executable extensions.'
		);
	}

	/**
	 * Zip_Target_Resolver enumerates the five supported target types.
	 *
	 * @return void
	 */
	public function test_target_resolver_supports_all_five_types(): void {
		$src = $this->target_resolver_source;
		foreach (
			array(
				"public const TYPE_PLUGIN     = 'plugin';",
				"public const TYPE_THEME      = 'theme';",
				"public const TYPE_UPLOADS    = 'uploads';",
				"public const TYPE_MU_PLUGINS = 'mu-plugins';",
				"public const TYPE_PATH       = 'path';",
			) as $needle
		) {
			$this->assertStringContainsString(
				$needle,
				$src,
				"Zip_Target_Resolver must declare: $needle"
			);
		}
	}

	/**
	 * The `path` branch of Zip_Target_Resolver applies the same
	 * realpath()-inside-ABSPATH boundary check that Read_File uses.
	 *
	 * @return void
	 */
	public function test_target_resolver_boundary_checks_path_targets(): void {
		$this->assertMatchesRegularExpression(
			'/realpath\(\s*ABSPATH\s*\)/',
			$this->target_resolver_source,
			'Zip_Target_Resolver must resolve ABSPATH via realpath() for boundary checks.'
		);
		$this->assertMatchesRegularExpression(
			"/strpos\(\s*\\\$parent,\s*\\\$base\s*\\.\s*'\\/'/",
			$this->target_resolver_source,
			'Zip_Target_Resolver must verify the resolved parent lives inside ABSPATH.'
		);
	}
}
