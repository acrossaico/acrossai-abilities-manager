<?php
/**
 * QueryMonitorTest — Feature 129 Query Monitor abilities.
 *
 * Covers the deterministic paths without a running WordPress or Query Monitor:
 * the guard's "not active" degradation (the mutation check — if the guard ever
 * stopped short-circuiting, these flip), and the full plugin-toggle logic. The
 * active-path collector reading is verified live against a real Query Monitor,
 * because stubbing processed collector data would test the stub, not QM.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.43
 */

declare(strict_types=1);

use AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\Get_Environment;
use AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\Get_Object_Cache_Status;
use AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\Profile_Url;
use AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\Toggle_Query_Monitor;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\QueryMonitor\Query_Monitor_Guard;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'activate_plugin' ) ) {
	/**
	 * Stub — mark a plugin active in the fixture.
	 *
	 * @param string $file Plugin basename.
	 * @return null|WP_Error
	 */
	function activate_plugin( string $file ) {
		$active = (array) ( $GLOBALS['acrossai_test_active_plugins'] ?? array() );
		if ( ! in_array( $file, $active, true ) ) {
			$active[] = $file;
		}
		$GLOBALS['acrossai_test_active_plugins'] = $active;
		return null;
	}
}

if ( ! function_exists( 'deactivate_plugins' ) ) {
	/**
	 * Stub — mark plugins inactive in the fixture.
	 *
	 * @param string|string[] $files Plugin basename(s).
	 * @return void
	 */
	function deactivate_plugins( $files ): void {
		$files  = (array) $files;
		$active = (array) ( $GLOBALS['acrossai_test_active_plugins'] ?? array() );
		$GLOBALS['acrossai_test_active_plugins'] = array_values( array_diff( $active, $files ) );
	}
}

/**
 * @coversDefaultClass \AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\QueryMonitor\Query_Monitor_Guard
 */
final class QueryMonitorTest extends TestCase {

	private const QM_FILE = 'query-monitor/query-monitor.php';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['acrossai_test_installed_plugins'] = array();
		$GLOBALS['acrossai_test_active_plugins']    = array();
		$GLOBALS['__acrossai_debug_test_get_plugins'] = null;
	}

	/**
	 * The whole suite runs in a process where Query Monitor is not loaded, so the
	 * guard must report inactive.
	 */
	public function test_guard_reports_inactive_without_query_monitor(): void {
		$this->assertFalse( class_exists( '\QM_Collectors' ), 'Test must run without a real Query Monitor.' );
		$this->assertFalse( Query_Monitor_Guard::is_active() );

		$not_active = Query_Monitor_Guard::not_active();
		$this->assertFalse( $not_active['active'] );
		$this->assertNotEmpty( $not_active['message'] );
	}

	/**
	 * The mutation check: every read ability short-circuits to the "not active"
	 * response when Query Monitor is absent, instead of dereferencing QM.
	 *
	 * @dataProvider read_abilities
	 * @param object $ability A read ability instance.
	 */
	public function test_read_abilities_degrade_when_qm_absent( object $ability ): void {
		$out = $ability->execute( array( 'url' => 'https://example.test/' ) );
		$this->assertFalse( $out['active'] );
		$this->assertArrayHasKey( 'message', $out );
	}

	/**
	 * @return array<string,array{0:object}>
	 */
	public static function read_abilities(): array {
		return array(
			'get-environment'         => array( new Get_Environment() ),
			'get-object-cache-status' => array( new Get_Object_Cache_Status() ),
			'profile-url'             => array( new Profile_Url() ),
		);
	}

	public function test_profile_url_reports_not_active_as_failure(): void {
		$out = ( new Profile_Url() )->execute( array( 'url' => 'https://example.test/' ) );
		$this->assertFalse( $out['success'] );
		$this->assertFalse( $out['active'] );
	}

	public function test_toggle_refuses_when_not_installed(): void {
		$GLOBALS['acrossai_test_installed_plugins'] = array();

		$out = ( new Toggle_Query_Monitor() )->execute( array( 'enabled' => true ) );

		$this->assertFalse( $out['success'] );
		$this->assertFalse( $out['installed'] );
		$this->assertFalse( $out['changed'] );
	}

	public function test_toggle_is_noop_when_already_in_requested_state(): void {
		$GLOBALS['acrossai_test_installed_plugins'] = array( self::QM_FILE => array( 'Name' => 'Query Monitor', 'Version' => '4.0.7' ) );
		$GLOBALS['acrossai_test_active_plugins']    = array( self::QM_FILE );

		$out = ( new Toggle_Query_Monitor() )->execute( array( 'enabled' => true ) );

		$this->assertTrue( $out['success'] );
		$this->assertTrue( $out['active'] );
		$this->assertFalse( $out['changed'] );
	}

	public function test_toggle_activates_an_installed_inactive_plugin(): void {
		$GLOBALS['acrossai_test_installed_plugins'] = array( self::QM_FILE => array( 'Name' => 'Query Monitor', 'Version' => '4.0.7' ) );
		$GLOBALS['acrossai_test_active_plugins']    = array();

		$out = ( new Toggle_Query_Monitor() )->execute( array( 'enabled' => true ) );

		$this->assertTrue( $out['success'] );
		$this->assertTrue( $out['active'] );
		$this->assertTrue( $out['changed'] );
	}

	public function test_toggle_deactivates_an_installed_active_plugin(): void {
		$GLOBALS['acrossai_test_installed_plugins'] = array( self::QM_FILE => array( 'Name' => 'Query Monitor', 'Version' => '4.0.7' ) );
		$GLOBALS['acrossai_test_active_plugins']    = array( self::QM_FILE );

		$out = ( new Toggle_Query_Monitor() )->execute( array( 'enabled' => false ) );

		$this->assertTrue( $out['success'] );
		$this->assertFalse( $out['active'] );
		$this->assertTrue( $out['changed'] );
	}
}
