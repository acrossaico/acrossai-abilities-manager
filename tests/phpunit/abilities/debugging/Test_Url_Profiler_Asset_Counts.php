<?php
/**
 * Issue #302 — profile-url reported asset counts eight times too high.
 *
 * The defect was that the counting loop added `count($row)` — the number of keys in one of Query
 * Monitor's asset rows — instead of counting the rows. Eight keys per row, so every figure was
 * exactly 8x. It survived review because the logic sat inside a method that needs Query Monitor and
 * a live loopback request, so nothing covering it could execute (see #298).
 *
 * These drive the pure function with synthetic collector payloads and need neither.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\QueryMonitor\Url_Profiler;

require_once dirname( __DIR__, 4 ) . '/includes/Abilities/Utilities/QueryMonitor/Url_Profiler.php';

final class Test_Url_Profiler_Asset_Counts extends TestCase {

	/**
	 * One asset row in Query Monitor's shape: eight keys, exactly as QM_Collector_Assets builds it.
	 *
	 * @param string $handle   Handle.
	 * @param string $position header, footer or modules.
	 * @return array<string,mixed>
	 */
	private function row( string $handle, string $position = 'header' ): array {
		return array(
			'handle'       => $handle,
			'position'     => $position,
			'url'          => '/wp-includes/js/' . $handle . '.js',
			'source'       => 'core',
			'ver'          => '1.0',
			'warning'      => false,
			'dependents'   => array(),
			'dependencies' => array(),
		);
	}

	/**
	 * A collector payload.
	 *
	 * @param array<int,array<string,mixed>> $rows    Asset rows.
	 * @param array<string,bool>             $missing Missing dependencies.
	 * @return object
	 */
	private function collector( array $rows, array $missing = array() ): object {
		$d                       = new stdClass();
		$d->url                  = 'https://example.test/';
		$d->assets               = $rows;
		$d->missing_dependencies = $missing;

		return $d;
	}

	/* ------------------------------------------------------------------ */
	/* The defect                                                         */
	/* ------------------------------------------------------------------ */

	public function test_counts_rows_not_the_keys_of_each_row(): void {
		$scripts = $this->collector( array( $this->row( 'a' ), $this->row( 'b' ), $this->row( 'c' ) ) );
		$profile = Url_Profiler::asset_profile( $scripts, $this->collector( array() ) );

		$this->assertSame( 3, $profile['scripts'], 'Three handles must count as three.' );
		$this->assertNotSame( 24, $profile['scripts'], 'Twenty-four is three rows times eight keys — the #302 defect.' );
	}

	/**
	 * Reproduces the two sites the bug was measured on, so the regression is pinned to real figures.
	 *
	 * @return array<string,array{0:int,1:int,2:int,3:int}>
	 */
	public static function measured_sites_provider(): array {
		return array(
			// handles scripts, handles styles, what the bug reported, what the bug reported.
			'staging — 56 plugins' => array( 127, 89, 1016, 712 ),
			'local — near-stock'   => array( 4, 25, 32, 200 ),
		);
	}

	/**
	 * @dataProvider measured_sites_provider
	 * @param int $script_handles Real script handles.
	 * @param int $style_handles  Real style handles.
	 * @param int $buggy_scripts  What the defect reported.
	 * @param int $buggy_styles   What the defect reported.
	 */
	public function test_measured_sites_now_report_their_real_handle_counts(
		int $script_handles,
		int $style_handles,
		int $buggy_scripts,
		int $buggy_styles
	): void {
		$scripts = array();
		for ( $i = 0; $i < $script_handles; $i++ ) {
			$scripts[] = $this->row( 'script-' . $i );
		}

		$styles = array();
		for ( $i = 0; $i < $style_handles; $i++ ) {
			$styles[] = $this->row( 'style-' . $i );
		}

		$profile = Url_Profiler::asset_profile( $this->collector( $scripts ), $this->collector( $styles ) );

		$this->assertSame( $script_handles, $profile['scripts'] );
		$this->assertSame( $style_handles, $profile['styles'] );
		$this->assertNotSame( $buggy_scripts, $profile['scripts'] );
		$this->assertNotSame( $buggy_styles, $profile['styles'] );

		// The relationship that identified the cause: the old figure was always 8x.
		$this->assertSame( $buggy_scripts, $profile['scripts'] * 8 );
		$this->assertSame( $buggy_styles, $profile['styles'] * 8 );
	}

	public function test_a_raw_key_is_ignored(): void {
		// QM_Collector_Assets never sets `raw`; the old code consulted it first. A payload carrying
		// only `raw` must count zero, which proves the speculative branch is gone.
		$d      = new stdClass();
		$d->raw = array( $this->row( 'a' ), $this->row( 'b' ) );

		$profile = Url_Profiler::asset_profile( $d, $d );

		$this->assertSame( 0, $profile['scripts'] );
		$this->assertSame( 0, $profile['styles'] );
	}

	/* ------------------------------------------------------------------ */
	/* Degenerate input                                                   */
	/* ------------------------------------------------------------------ */

	public function test_null_collectors_count_zero(): void {
		$profile = Url_Profiler::asset_profile( null, null );

		$this->assertSame( 0, $profile['scripts'] );
		$this->assertSame( 0, $profile['styles'] );
		$this->assertSame( array(), $profile['scripts_by_position'] );
		$this->assertSame( 0, $profile['missing_dependencies']['scripts'] );
	}

	public function test_an_empty_collector_counts_zero(): void {
		$profile = Url_Profiler::asset_profile( $this->collector( array() ), $this->collector( array() ) );

		$this->assertSame( 0, $profile['scripts'] );
		$this->assertSame( 0, $profile['styles'] );
	}

	public function test_a_non_array_assets_value_counts_zero(): void {
		$d         = new stdClass();
		$d->assets = 'not an array';

		$profile = Url_Profiler::asset_profile( $d, $d );

		$this->assertSame( 0, $profile['scripts'] );
	}

	/* ------------------------------------------------------------------ */
	/* The breakdown the data already carried                             */
	/* ------------------------------------------------------------------ */

	public function test_handles_are_split_by_position(): void {
		$scripts = $this->collector(
			array(
				$this->row( 'a', 'header' ),
				$this->row( 'b', 'header' ),
				$this->row( 'c', 'footer' ),
				$this->row( 'd', 'modules' ),
			)
		);

		$profile = Url_Profiler::asset_profile( $scripts, $this->collector( array() ) );

		$this->assertSame(
			array(
				'footer'  => 1,
				'header'  => 2,
				'modules' => 1,
			),
			$profile['scripts_by_position'],
			'A hundred handles in the footer is a different problem from a hundred in the head.'
		);
		$this->assertSame( 4, $profile['scripts'], 'The breakdown must sum to the total.' );
	}

	public function test_a_row_without_a_position_is_reported_as_unknown(): void {
		$row = $this->row( 'a' );
		unset( $row['position'] );

		$profile = Url_Profiler::asset_profile( $this->collector( array( $row ) ), $this->collector( array() ) );

		$this->assertSame( array( 'unknown' => 1 ), $profile['scripts_by_position'] );
	}

	public function test_missing_dependencies_are_counted(): void {
		$scripts = $this->collector(
			array( $this->row( 'a' ) ),
			array(
				'nonexistent-dep'  => true,
				'another-missing'  => true,
			)
		);

		$profile = Url_Profiler::asset_profile( $scripts, $this->collector( array() ) );

		$this->assertSame( 2, $profile['missing_dependencies']['scripts'] );
		$this->assertSame( 0, $profile['missing_dependencies']['styles'] );
	}

	public function test_the_shape_keeps_scripts_and_styles_as_integers(): void {
		// A consumer reading profile.assets.scripts as a number must keep working; the new keys are
		// siblings, not a replacement.
		$profile = Url_Profiler::asset_profile( $this->collector( array( $this->row( 'a' ) ) ), $this->collector( array() ) );

		$this->assertIsInt( $profile['scripts'] );
		$this->assertIsInt( $profile['styles'] );
		$this->assertArrayHasKey( 'scripts_by_position', $profile );
		$this->assertArrayHasKey( 'missing_dependencies', $profile );
	}
}
