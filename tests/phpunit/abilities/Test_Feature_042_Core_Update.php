<?php
/**
 * Structural tests for the Feature 042 Core-category work.
 *
 * Covers:
 *   - Backups_Storage filename scheme change ({slug}-{unix}-{ms}.zip).
 *   - Core/Category_Registrar shape parity with other Category_Registrars.
 *   - Check_Wp_Core_Update + Update_Wp_Core ability scaffolding + guards.
 *   - Bootstrap wiring for the Core category and its two abilities.
 *
 * Source-inspection only, mirroring the Test_Feature_041_Backup_Abilities
 * suite: the plugin's stub bootstrap can't safely construct the full plugin.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Feature_042_Core_Update.
 */
class Test_Feature_042_Core_Update extends WP_UnitTestCase {

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

		$plugin_root = dirname( __DIR__, 3 );

		$this->sources = array(
			'category_registrar'   => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Core/Category_Registrar.php'
			),
			'wp_core_update_check' => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Core/Check_Wp_Core_Update.php'
			),
			'wp_core_update'       => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Core/Update_Wp_Core.php'
			),
			'backups_storage'      => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Utilities/Backups_Storage.php'
			),
			'bootstrap'            => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
			),
		);
	}

	// =========================================================================
	// Filename scheme change — Backups_Storage::random_backup_filename()
	// =========================================================================

	/**
	 * The helper no longer prepends the legacy `backup-` prefix and no longer
	 * calls `wp_generate_password()` for a 12-char random suffix.
	 *
	 * @return void
	 */
	public function test_filename_scheme_drops_backup_prefix_and_random_suffix(): void {
		$src = $this->sources['backups_storage'];
		$this->assertStringNotContainsString(
			"array( 'backup' )",
			$src,
			'random_backup_filename must not seed the parts array with "backup" anymore.'
		);
		$this->assertStringNotContainsString(
			'wp_generate_password( 12',
			$src,
			'random_backup_filename must not emit a 12-char random suffix anymore.'
		);
	}

	/**
	 * The new scheme composes `{slug}-{unix}-{ms}.zip` — driven by
	 * microtime(true) + two helpers.
	 *
	 * @return void
	 */
	public function test_filename_scheme_uses_slug_unix_ms(): void {
		$src = $this->sources['backups_storage'];
		$this->assertStringContainsString(
			'microtime( true )',
			$src,
			'random_backup_filename must derive the timestamp + ms from microtime(true).'
		);
		$this->assertStringContainsString(
			'filename_slug_segment',
			$src,
			'random_backup_filename must delegate slug resolution to filename_slug_segment().'
		);
		$this->assertStringContainsString(
			'filename_time_segments',
			$src,
			'random_backup_filename must delegate time resolution to filename_time_segments().'
		);
		$this->assertMatchesRegularExpression(
			"/\\\$slug\s*\\.\s*'-'\s*\\.\s*\\\$unix\s*\\.\s*'-'\s*\\.\s*\\\$ms\s*\\.\s*'\\.zip'/",
			$src,
			'The final concatenation must be {slug}-{unix}-{ms}.zip.'
		);
	}

	/**
	 * When both $target and $target_type are empty, the slug segment must
	 * fall back to a sane default so the filename never becomes `-{ts}.zip`.
	 *
	 * @return void
	 */
	public function test_filename_slug_segment_has_ultimate_fallback(): void {
		$src = $this->sources['backups_storage'];
		$this->assertMatchesRegularExpression(
			"/return\s*'backup'\s*;/",
			$src,
			'filename_slug_segment must fall back to "backup" when both target and type are empty.'
		);
	}

	// =========================================================================
	// Core Category_Registrar
	// =========================================================================

	/**
	 * The Core/Category_Registrar mirrors the shape of every other
	 * Category_Registrar (final singleton class, `register()` method calling
	 * wp_register_ability_category()).
	 *
	 * @return void
	 */
	public function test_core_category_registrar_shape(): void {
		$src = $this->sources['category_registrar'];
		$this->assertStringContainsString( 'final class Category_Registrar', $src );
		$this->assertStringContainsString( 'public static function instance(): self', $src );
		$this->assertStringContainsString( 'public function register(): void', $src );
		$this->assertStringContainsString(
			"'acrossai-core'",
			$src,
			'Category slug must be acrossai-core.'
		);
		$this->assertStringContainsString(
			'wp_register_ability_category',
			$src
		);
	}

	// =========================================================================
	// Check_Wp_Core_Update
	// =========================================================================

	/**
	 * The check ability is read-only, gates via manage_options only, and
	 * uses WP core's get_core_updates() from wp-admin/includes/update.php.
	 *
	 * @return void
	 */
	public function test_wp_core_update_check_shape(): void {
		$src = $this->sources['wp_core_update_check'];
		$this->assertStringContainsString( 'extends Ability_Definition', $src );
		$this->assertStringContainsString(
			"'core/check-wp-core-update'",
			$src,
			'Ability name must be core/check-wp-core-update.'
		);
		$this->assertStringContainsString(
			"'acrossai-core'",
			$src,
			'Ability category must point at the new Core category slug.'
		);
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$src
		);
		$this->assertStringContainsString( 'get_core_updates', $src );
		$this->assertStringContainsString(
			"wp-admin/includes/update.php",
			$src,
			'Must require WP core update.php.'
		);
		$this->assertStringContainsString(
			"'readonly'    => true",
			$src,
			'Check ability annotations must declare readonly=true.'
		);
	}

	// =========================================================================
	// Update_Wp_Core
	// =========================================================================

	/**
	 * The update ability wraps WP core's Core_Upgrader and honours DISALLOW_FILE_MODS.
	 *
	 * @return void
	 */
	public function test_wp_core_update_uses_core_upgrader_and_guards(): void {
		$src = $this->sources['wp_core_update'];
		$this->assertStringContainsString( 'extends Ability_Definition', $src );
		$this->assertStringContainsString(
			"'core/update-wp-core'",
			$src
		);
		$this->assertStringContainsString(
			"'acrossai-core'",
			$src
		);
		$this->assertStringContainsString(
			'File_Mods_Guard::blocked_response',
			$src,
			'Update_Wp_Core must invoke File_Mods_Guard::blocked_response(install).'
		);
		$this->assertMatchesRegularExpression(
			"/File_Mods_Guard::blocked_response\(\s*'install'\s*\)/",
			$src
		);
		$this->assertStringContainsString(
			'Core_Upgrader',
			$src,
			'Update_Wp_Core must wrap WP core Core_Upgrader.'
		);
		$this->assertStringContainsString(
			'->upgrade(',
			$src,
			'Update_Wp_Core must call ->upgrade( $update ).'
		);
		$this->assertStringContainsString(
			'WP_Ajax_Upgrader_Skin',
			$src,
			'Update_Wp_Core must use the WP_Ajax_Upgrader_Skin (same as Update_Plugin / Update_Theme).'
		);
		$this->assertStringContainsString(
			'is_multisite()',
			$src,
			'Update_Wp_Core must contain a multisite guard.'
		);
	}

	/**
	 * The update ability's permission_callback gates on BOTH manage_options
	 * AND update_core — matching WP core's own admin gate.
	 *
	 * @return void
	 */
	public function test_wp_core_update_requires_both_caps(): void {
		$src = $this->sources['wp_core_update'];
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\).*current_user_can\(\s*'update_core'\s*\)/s",
			$src,
			'Update_Wp_Core permission_callback must AND manage_options with update_core.'
		);
	}

	/**
	 * The update ability accepts both an optional `version` pin and an
	 * optional `locale` override (per the Clarifications decision).
	 *
	 * @return void
	 */
	public function test_wp_core_update_accepts_optional_version_and_locale(): void {
		$src = $this->sources['wp_core_update'];
		$this->assertStringContainsString( "'version'", $src );
		$this->assertStringContainsString( "'locale'", $src );
		$this->assertStringContainsString( 'find_core_update', $src );
		// Version is optional — must NOT be inside `required`.
		$this->assertStringNotContainsString(
			"'required'             => array( 'version' )",
			$src
		);
	}

	// =========================================================================
	// Bootstrap wiring
	// =========================================================================

	/**
	 * The core bootstrap registers the Core Category_Registrar callback and
	 * instantiates both new abilities.
	 *
	 * @return void
	 */
	public function test_bootstrap_wires_core_category_and_abilities(): void {
		$src = $this->sources['bootstrap'];
		$this->assertStringContainsString(
			"Core\\Category_Registrar::instance(), 'register'",
			$src,
			'Bootstrap must register the Core Category_Registrar callback.'
		);
		$this->assertStringContainsString(
			'new Core\\Check_Wp_Core_Update()',
			$src,
			'Bootstrap must instantiate Check_Wp_Core_Update.'
		);
		$this->assertStringContainsString(
			'new Core\\Update_Wp_Core()',
			$src,
			'Bootstrap must instantiate Update_Wp_Core.'
		);
	}
}
