<?php
/**
 * Structural tests for Feature 064 plugins/uninstall-plugin.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.23
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Uninstall_Plugin.
 */
class Test_Uninstall_Plugin extends WP_UnitTestCase {

	/**
	 * The Uninstall_Plugin source, loaded once per test.
	 *
	 * @var string
	 */
	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Plugins/Uninstall_Plugin.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'plugins/uninstall-plugin'", $this->src );
		$this->assertStringContainsString( "'acrossai-plugins'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_annotations_destructive_true(): void {
		$this->assertStringContainsString( "'destructive' => true", $this->src );
		$this->assertStringContainsString( "'idempotent'  => true", $this->src );
	}

	public function test_uses_file_mods_guard_install_context(): void {
		$this->assertMatchesRegularExpression(
			"/File_Mods_Guard::blocked_response\s*\(\s*'install'\s*\)/",
			$this->src
		);
	}

	public function test_uses_plugin_helpers_resolve_plugin(): void {
		$this->assertStringContainsString( 'Plugin_Helpers::resolve_plugin(', $this->src );
	}

	public function test_uses_is_plugin_active_guard(): void {
		$this->assertStringContainsString( 'is_plugin_active(', $this->src );
	}

	/**
	 * `delete_plugins()`, not `uninstall_plugin()`.
	 *
	 * This previously asserted `uninstall_plugin(` and passed for as long as
	 * the ability was broken: core's `uninstall_plugin()` only fires the
	 * uninstall hook and removes no files, so a plugin with no uninstall hook
	 * (most of them) "uninstalled" with nothing happening and stayed listed in
	 * wp-admin. A test that greps for a call pins the implementation, not the
	 * behaviour — which is exactly why it could not notice.
	 */
	public function test_calls_wp_core_delete_plugins(): void {
		$this->assertStringContainsString( 'delete_plugins( array( $plugin_file ) )', $this->src );
		$this->assertDoesNotMatchRegularExpression(
			'/\$result\s*=\s*uninstall_plugin\(/',
			$this->src,
			'uninstall_plugin() alone removes no files; it must not be what this ability relies on.'
		);
	}

	/**
	 * A symlinked plugin must be refused, and refused FIRST.
	 *
	 * `WP_Filesystem_Direct::delete()` recursive calls `dirlist()`, and PHP's
	 * directory functions follow symlinks — so deleting a linked plugin walks
	 * into the TARGET and empties it, leaving the link behind. Where several
	 * sites link one checkout, that destroys the plugin everywhere at once and
	 * takes its `.git` with it.
	 *
	 * Learned the hard way: one fleet-wide call across four symlinked sites
	 * emptied the shared working tree, and only committed work survived.
	 */
	public function test_refuses_to_delete_a_symlinked_plugin(): void {
		$this->assertStringContainsString( 'is_link( $plugin_dir )', $this->src );
		$this->assertStringContainsString( "'blocked_reason' => 'plugin_is_symlinked'", $this->src );
		$this->assertLessThan(
			strpos( $this->src, 'delete_plugins(' ),
			strpos( $this->src, "'plugin_is_symlinked'" ),
			'The symlink check must precede the delete, or it guards nothing.'
		);
	}

	/** The removal is verified against the disk, not merely reported. */
	public function test_verifies_the_files_are_actually_gone(): void {
		$this->assertStringContainsString( "file_exists( WP_PLUGIN_DIR . '/' . \$plugin_file )", $this->src );
		$this->assertStringContainsString( "'blocked_reason' => 'files_remain'", $this->src );
		// PHP can answer file_exists() from its stat cache; a check that may
		// read a stale answer is not a check.
		$this->assertStringContainsString( 'clearstatcache( true, WP_PLUGIN_DIR', $this->src );
	}

	/**
	 * A non-direct filesystem is refused before core can print at us.
	 *
	 * `delete_plugins()` calls `request_filesystem_credentials()`, which
	 * echoes an HTML form when it cannot proceed. This ability answers in JSON.
	 */
	public function test_refuses_when_the_filesystem_is_not_direct(): void {
		$this->assertStringContainsString( "'direct' !== get_filesystem_method()", $this->src );
		$this->assertStringContainsString( "'blocked_reason' => 'filesystem_not_direct'", $this->src );
	}

	public function test_declares_every_blocked_reason(): void {
		$this->assertStringContainsString( "'blocked_reason' => 'file_mods_disallowed'", $this->src );
		$this->assertStringContainsString( "'blocked_reason' => 'plugin_not_found'", $this->src );
		$this->assertStringContainsString( "'blocked_reason' => 'plugin_active'", $this->src );
		// Three more since the deletion fix: a symlinked plugin, a host that
		// needs FTP, and a deletion that reported success and left files.
		$this->assertStringContainsString( "'blocked_reason' => 'plugin_is_symlinked'", $this->src );
		$this->assertStringContainsString( "'blocked_reason' => 'filesystem_not_direct'", $this->src );
		$this->assertStringContainsString( "'blocked_reason' => 'files_remain'", $this->src );
	}

	public function test_guard_order_matches_plan(): void {
		// file_mods_disallowed -> plugin_not_found -> plugin_active.
		$pos_fm     = strpos( $this->src, "'blocked_reason' => 'file_mods_disallowed'" );
		$pos_nf     = strpos( $this->src, "'blocked_reason' => 'plugin_not_found'" );
		$pos_active = strpos( $this->src, "'blocked_reason' => 'plugin_active'" );

		$this->assertNotFalse( $pos_fm );
		$this->assertNotFalse( $pos_nf );
		$this->assertNotFalse( $pos_active );

		$this->assertLessThan( $pos_nf, $pos_fm, 'file_mods_disallowed guard must appear before plugin_not_found.' );
		$this->assertLessThan( $pos_active, $pos_nf, 'plugin_not_found guard must appear before plugin_active.' );
	}

	public function test_requires_wp_admin_plugin_php_before_uninstall(): void {
		$this->assertStringContainsString( "require_once ABSPATH . 'wp-admin/includes/plugin.php'", $this->src );
	}

	public function test_input_schema_requires_plugin(): void {
		$this->assertStringContainsString( "'required'             => array( 'plugin' )", $this->src );
	}

	public function test_sanitizes_plugin_identifier(): void {
		$this->assertStringContainsString( 'sanitize_text_field(', $this->src );
	}
}
