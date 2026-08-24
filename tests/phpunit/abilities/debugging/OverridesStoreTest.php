<?php
/**
 * OverridesStoreTest — unit coverage for the Debugging Overrides_Store helper.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.21
 */

declare(strict_types=1);

use AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\Overrides_Store;
use PHPUnit\Framework\TestCase;

// ---------------------------------------------------------------------------
// Global test stubs for WP constants and get_plugins() — Overrides_Store
// depends on these. The bootstrap already stubs get_option / update_option to
// consult $__acrossai_test_options.
// ---------------------------------------------------------------------------

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/acrossai-tests-wp-content-' . uniqid( '', true ) );
	if ( ! is_dir( WP_CONTENT_DIR ) ) {
		mkdir( WP_CONTENT_DIR, 0777, true );
	}
}

if ( ! defined( 'WPMU_PLUGIN_DIR' ) ) {
	define( 'WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins' );
	if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
		mkdir( WPMU_PLUGIN_DIR, 0777, true );
	}
}

if ( ! defined( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH' ) ) {
	define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH', dirname( __DIR__, 4 ) . '/' );
}

if ( ! function_exists( 'get_plugins' ) ) {
	/**
	 * Controllable get_plugins() stub — reads from a test-owned global.
	 *
	 * @return array<string,array>
	 */
	function get_plugins( string $plugin_folder = '' ): array {
		global $__acrossai_debug_test_get_plugins;
		return is_array( $__acrossai_debug_test_get_plugins ) ? $__acrossai_debug_test_get_plugins : array();
	}
}

/**
 * @coversDefaultClass \AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\Overrides_Store
 */
final class OverridesStoreTest extends TestCase {

	private string $overrides_path;
	private string $mu_plugin_path;
	private string $bundled_path;

	protected function setUp(): void {
		parent::setUp();

		$store                = Overrides_Store::instance();
		$this->overrides_path = $store->overrides_path();
		$this->mu_plugin_path = $store->mu_plugin_path();
		$this->bundled_path   = $store->bundled_mu_source_path();

		if ( file_exists( $this->overrides_path ) ) {
			unlink( $this->overrides_path );
		}
		if ( file_exists( $this->mu_plugin_path ) ) {
			unlink( $this->mu_plugin_path );
		}

		// Default installed-plugins fixture. Tests override individual entries as needed.
		$GLOBALS['__acrossai_debug_test_get_plugins'] = array(
			'hello-dolly/hello.php'  => array( 'Name' => 'Hello Dolly', 'Version' => '1.7.2', 'RequiresPlugins' => '' ),
			'akismet/akismet.php'    => array( 'Name' => 'Akismet',     'Version' => '5.3',   'RequiresPlugins' => '' ),
			'plugin-a/plugin-a.php'  => array( 'Name' => 'Plugin A',    'Version' => '1.0',   'RequiresPlugins' => '' ),
			'plugin-b/plugin-b.php'  => array( 'Name' => 'Plugin B',    'Version' => '1.0',   'RequiresPlugins' => 'plugin-a' ),
		);

		// Default: no plugins active in DB.
		$GLOBALS['__acrossai_test_options'] = array( 'active_plugins' => array() );
	}

	protected function tearDown(): void {
		if ( file_exists( $this->overrides_path ) ) {
			unlink( $this->overrides_path );
		}
		if ( file_exists( $this->mu_plugin_path ) ) {
			unlink( $this->mu_plugin_path );
		}
		parent::tearDown();
	}

	/** read() on absent file returns empty map + parse_error null. */
	public function test_read_returns_empty_map_when_file_missing(): void {
		$result = Overrides_Store::instance()->read();
		$this->assertSame( array( 'overrides' => array(), 'parse_error' => null ), $result );
	}

	/** FR-019: read() on malformed JSON returns empty map + parse_error message. */
	public function test_read_tolerates_malformed_json(): void {
		file_put_contents( $this->overrides_path, '{not valid json' );
		$result = Overrides_Store::instance()->read();
		$this->assertSame( array(), $result['overrides'] );
		$this->assertNotNull( $result['parse_error'] );
		$this->assertStringContainsString( 'malformed', strtolower( (string) $result['parse_error'] ) );
	}

	/** FR-011: write_one with requested state matching DB state records nothing. */
	public function test_write_one_drops_entry_that_matches_db_state(): void {
		$GLOBALS['__acrossai_test_options']['active_plugins'] = array( 'hello-dolly/hello.php' );

		$result = Overrides_Store::instance()->write_one( 'hello-dolly/hello.php', true );

		$this->assertFalse( $result['recorded'] );
		$this->assertSame( 'matches-db-state', $result['reason'] );
		$this->assertFileDoesNotExist( $this->overrides_path );
	}

	/** FR-012: write that leaves the map empty deletes the on-disk file. */
	public function test_persist_deletes_file_when_map_empty(): void {
		file_put_contents(
			$this->overrides_path,
			(string) wp_json_encode( array( 'overrides' => array( 'hello-dolly/hello.php' => false ) ) )
		);
		$GLOBALS['__acrossai_test_options']['active_plugins'] = array( 'hello-dolly/hello.php' );

		// Toggle Hello Dolly back to active while DB says active → matches-db-state
		// cancels the existing entry, and because the map is now empty the file is deleted.
		$result = Overrides_Store::instance()->write_one( 'hello-dolly/hello.php', true );

		$this->assertSame( 'matches-db-state', $result['reason'] );
		$this->assertFileDoesNotExist( $this->overrides_path );
	}

	/** Atomic write leaves no `.wctester-*` sibling once the write completes. */
	public function test_write_leaves_no_tmp_sibling(): void {
		Overrides_Store::instance()->write_one( 'hello-dolly/hello.php', true );

		$this->assertFileExists( $this->overrides_path );
		$tmp_matches = glob( dirname( $this->overrides_path ) . '/.wctester-*' );
		$this->assertSame( array(), $tmp_matches, 'No .wctester-* temp sibling should remain after a successful rename.' );
	}

	/** FR-021: read() prunes orphans and rewrites the file with the smaller map. */
	public function test_read_auto_prunes_orphans(): void {
		file_put_contents(
			$this->overrides_path,
			(string) wp_json_encode(
				array(
					'overrides' => array(
						'hello-dolly/hello.php'         => false,
						'uninstalled-plugin/gone.php'   => true,
						'another-uninstalled/main.php'  => false,
					),
				)
			)
		);

		$result = Overrides_Store::instance()->read();

		$this->assertSame( array( 'hello-dolly/hello.php' => false ), $result['overrides'] );

		$on_disk = json_decode( (string) file_get_contents( $this->overrides_path ), true );
		$this->assertSame( array( 'hello-dolly/hello.php' => false ), $on_disk['overrides'] );
	}

	/** FR-021 + FR-012: read() that prunes to empty deletes the file. */
	public function test_read_deletes_file_when_pruning_empties_map(): void {
		file_put_contents(
			$this->overrides_path,
			(string) wp_json_encode(
				array(
					'overrides' => array(
						'gone-plugin/gone.php' => true,
					),
				)
			)
		);

		$result = Overrides_Store::instance()->read();

		$this->assertSame( array(), $result['overrides'] );
		$this->assertFileDoesNotExist( $this->overrides_path );
	}

	/** FR-020: mu_plugin_status() reports 'missing' when the file is absent. */
	public function test_mu_plugin_status_missing(): void {
		$this->assertSame( 'missing', Overrides_Store::instance()->mu_plugin_status() );
	}

	/** FR-020: mu_plugin_status() reports 'deployed' when hash matches. */
	public function test_mu_plugin_status_deployed(): void {
		$this->assertFileExists( $this->bundled_path, 'Bundled reference asset must exist for this test to be meaningful.' );
		copy( $this->bundled_path, $this->mu_plugin_path );

		$this->assertSame( 'deployed', Overrides_Store::instance()->mu_plugin_status() );
	}

	/** FR-020: mu_plugin_status() reports 'stale' when hash mismatches. */
	public function test_mu_plugin_status_stale(): void {
		file_put_contents( $this->mu_plugin_path, "<?php\n// deliberately different\n" );
		$this->assertSame( 'stale', Overrides_Store::instance()->mu_plugin_status() );
	}

	/** write_many with mixed states records one atomic write covering every change. */
	public function test_write_many_batches_writes(): void {
		$GLOBALS['__acrossai_test_options']['active_plugins'] = array( 'hello-dolly/hello.php' );

		$result = Overrides_Store::instance()->write_many(
			array(
				'akismet/akismet.php'   => true,   // DB inactive, override active → applied
				'hello-dolly/hello.php' => true,   // DB active, override active → no_op
				'plugin-a/plugin-a.php' => false,  // DB inactive, override inactive → no_op
			)
		);

		$this->assertCount( 1, $result['applied'] );
		$this->assertSame( 'akismet/akismet.php', $result['applied'][0]['plugin_file'] );
		$this->assertTrue( $result['applied'][0]['active'] );
		$this->assertCount( 2, $result['no_op'] );

		$tmp_matches = glob( dirname( $this->overrides_path ) . '/.wctester-*' );
		$this->assertSame( array(), $tmp_matches );
	}
}
