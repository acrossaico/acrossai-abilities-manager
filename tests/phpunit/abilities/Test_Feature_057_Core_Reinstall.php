<?php
/**
 * Structural tests for the Feature 057 Reinstall_Wp_Core ability.
 *
 * Covers:
 *   - Class scaffolding, name, category, guards, and permission gate.
 *   - The reinstall-specific difference: forces $update->response = 'reinstall'
 *     and passes allow_relaxed_file_ownership=false to Core_Upgrader.
 *   - Bootstrap wiring.
 *
 * Source-inspection only, mirroring Test_Feature_042_Core_Update.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Feature_057_Core_Reinstall.
 */
class Test_Feature_057_Core_Reinstall extends WP_UnitTestCase {

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
			'wp_core_reinstall' => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Core/Reinstall_Wp_Core.php'
			),
			'bootstrap'         => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
			),
		);
	}

	// =========================================================================
	// Reinstall_Wp_Core — scaffolding + guards
	// =========================================================================

	/**
	 * The reinstall ability wraps WP core's Core_Upgrader and honours
	 * DISALLOW_FILE_MODS via File_Mods_Guard.
	 *
	 * @return void
	 */
	public function test_wp_core_reinstall_uses_core_upgrader_and_guards(): void {
		$src = $this->sources['wp_core_reinstall'];
		$this->assertStringContainsString( 'extends Ability_Definition', $src );
		$this->assertStringContainsString(
			"'core/reinstall-wp-core'",
			$src,
			'Ability name must be core/reinstall-wp-core.'
		);
		$this->assertStringContainsString(
			"'acrossai-core'",
			$src,
			'Ability category must point at the Core category slug.'
		);
		$this->assertStringContainsString(
			'File_Mods_Guard::blocked_response',
			$src,
			'Reinstall_Wp_Core must invoke File_Mods_Guard::blocked_response(install).'
		);
		$this->assertMatchesRegularExpression(
			"/File_Mods_Guard::blocked_response\(\s*'install'\s*\)/",
			$src
		);
		$this->assertStringContainsString(
			'Core_Upgrader',
			$src,
			'Reinstall_Wp_Core must wrap WP core Core_Upgrader.'
		);
		$this->assertStringContainsString(
			'->upgrade(',
			$src,
			'Reinstall_Wp_Core must call ->upgrade( $update, ... ).'
		);
		$this->assertStringContainsString(
			'WP_Ajax_Upgrader_Skin',
			$src,
			'Reinstall_Wp_Core must use WP_Ajax_Upgrader_Skin (same as Update_Wp_Core).'
		);
		$this->assertStringContainsString(
			'is_multisite()',
			$src,
			'Reinstall_Wp_Core must contain a multisite guard.'
		);
	}

	/**
	 * The reinstall ability's permission_callback gates on BOTH manage_options
	 * AND update_core — matching WP core's own admin gate.
	 *
	 * @return void
	 */
	public function test_wp_core_reinstall_requires_both_caps(): void {
		$src = $this->sources['wp_core_reinstall'];
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\).*current_user_can\(\s*'update_core'\s*\)/s",
			$src,
			'Reinstall_Wp_Core permission_callback must AND manage_options with update_core.'
		);
	}

	/**
	 * The reinstall ability forces `$update->response = 'reinstall'` before
	 * calling Core_Upgrader::upgrade() — the semantic difference from
	 * Update_Wp_Core.
	 *
	 * @return void
	 */
	public function test_wp_core_reinstall_forces_reinstall_response(): void {
		$src = $this->sources['wp_core_reinstall'];
		$this->assertMatchesRegularExpression(
			"/\\\$update->response\s*=\s*'reinstall'\s*;/",
			$src,
			'Reinstall_Wp_Core must set $update->response = \'reinstall\' before calling the upgrader.'
		);
	}

	/**
	 * The reinstall ability passes `allow_relaxed_file_ownership=false` to
	 * the upgrader — matching WP admin's do_core_upgrade($reinstall=true).
	 *
	 * @return void
	 */
	public function test_wp_core_reinstall_disables_relaxed_file_ownership(): void {
		$src = $this->sources['wp_core_reinstall'];
		$this->assertStringContainsString(
			"'allow_relaxed_file_ownership' => false",
			$src,
			'Reinstall_Wp_Core must pass allow_relaxed_file_ownership=false to the upgrader.'
		);
	}

	/**
	 * The reinstall ability accepts an optional `locale` override and does
	 * NOT accept a `version` input (reinstall always targets the currently
	 * installed version, matching WP admin's do-core-reinstall).
	 *
	 * @return void
	 */
	public function test_wp_core_reinstall_accepts_locale_only(): void {
		$src = $this->sources['wp_core_reinstall'];
		$this->assertStringContainsString( "'locale'", $src );
		$this->assertStringContainsString( 'find_core_update', $src );
		$this->assertStringNotContainsString(
			"'version' => array(",
			$src,
			'Reinstall input schema must not accept a version pin.'
		);
	}

	/**
	 * The reinstall ability declares destructive=true — the annotation
	 * difference from Update_Wp_Core (which is destructive=false).
	 *
	 * @return void
	 */
	public function test_wp_core_reinstall_annotations_declare_destructive(): void {
		$src = $this->sources['wp_core_reinstall'];
		$this->assertMatchesRegularExpression(
			"/'destructive'\s*=>\s*true/",
			$src,
			'Reinstall annotations must declare destructive=true.'
		);
		$this->assertMatchesRegularExpression(
			"/'idempotent'\s*=>\s*true/",
			$src,
			'Reinstall annotations must declare idempotent=true.'
		);
	}

	// =========================================================================
	// Bootstrap wiring
	// =========================================================================

	/**
	 * The core bootstrap instantiates the new Reinstall_Wp_Core ability.
	 *
	 * @return void
	 */
	public function test_bootstrap_wires_wp_core_reinstall(): void {
		$src = $this->sources['bootstrap'];
		$this->assertStringContainsString(
			'new Core\\Reinstall_Wp_Core()',
			$src,
			'Bootstrap must instantiate Reinstall_Wp_Core.'
		);
	}
}
