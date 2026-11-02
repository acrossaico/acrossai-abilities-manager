<?php
/**
 * Issue 302 — asset counts must count handles, not the keys of each handle row.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\QueryMonitor\Url_Profiler;

require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/QueryMonitor/Url_Profiler.php';

/**
 * Pins the shape Query Monitor's assets collector actually returns.
 *
 * QM_Collector_Assets stores a flat list, one associative row per handle, so the
 * count is the number of rows. The old loop assumed each row was a group and
 * summed its keys instead, reporting eight times the real number of handles.
 */
final class Test_Url_Profiler_Asset_Count extends TestCase {

	/**
	 * Invoke the private counter without widening its visibility.
	 *
	 * @param object|null $data Collector data.
	 * @return int
	 */
	private function count_assets( $data ): int {
		$method = new ReflectionMethod( Url_Profiler::class, 'asset_count' );

		return $method->invoke( null, $data );
	}

	/**
	 * One row per handle, each row carrying the eight keys QM sets.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function handle_rows(): array {
		$rows = array();
		foreach ( array( 'jquery', 'wp-block-library', 'theme-style' ) as $handle ) {
			$rows[] = array(
				'handle'       => $handle,
				'position'     => 'header',
				'url'          => 'https://example.test/' . $handle . '.js',
				'source'       => null,
				'ver'          => '1.0',
				'warning'      => '',
				'dependents'   => array(),
				'dependencies' => array(),
			);
		}

		return $rows;
	}

	public function test_counts_handles_not_the_keys_of_each_row(): void {
		$data = (object) array( 'assets' => $this->handle_rows() );

		$this->assertSame( 3, $this->count_assets( $data ) );
	}

	public function test_counts_an_empty_asset_list_as_zero(): void {
		$data = (object) array( 'assets' => array() );

		$this->assertSame( 0, $this->count_assets( $data ) );
	}

	public function test_missing_assets_key_is_zero(): void {
		$this->assertSame( 0, $this->count_assets( (object) array( 'url' => 'https://example.test/' ) ) );
	}

	public function test_null_collector_is_zero(): void {
		$this->assertSame( 0, $this->count_assets( null ) );
	}
}
