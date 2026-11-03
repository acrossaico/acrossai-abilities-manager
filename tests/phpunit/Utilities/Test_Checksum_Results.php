<?php
/**
 * Trimming checksum verification output down to what needs reading.
 *
 * core/verify-core-checksums returned 3,766 rows and 608,677 characters on a clean install, which
 * exceeds the MCP response limit — so the ability returned an error rather than an answer, despite
 * working correctly. Three of those rows mattered. The filter is shared by both checksum abilities
 * and lives here so it is testable without a database, a manifest fetch or a WordPress install.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Checksum_Results;

require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Checksum_Results.php';

final class Test_Checksum_Results extends TestCase {

	/**
	 * One verification row.
	 *
	 * @param string $file   Path.
	 * @param string $status ok | modified | missing | added.
	 * @return array<string,string>
	 */
	private function row( string $file, string $status ): array {
		return array(
			'file'     => $file,
			'expected' => 'aaa',
			'actual'   => 'ok' === $status ? 'aaa' : 'bbb',
			'status'   => $status,
		);
	}

	public function test_matching_rows_are_dropped_and_problems_kept(): void {
		$results = array(
			$this->row( 'a.php', 'ok' ),
			$this->row( 'b.php', 'modified' ),
			$this->row( 'c.php', 'ok' ),
			$this->row( 'd.php', 'missing' ),
			$this->row( 'e.php', 'added' ),
		);

		$kept = Checksum_Results::only_problems( $results );

		$this->assertCount( 3, $kept );
		$this->assertSame( array( 'b.php', 'd.php', 'e.php' ), array_column( $kept, 'file' ) );
		$this->assertSame( 2, Checksum_Results::matched_count( $results ) );
	}

	public function test_the_kept_rows_are_reindexed(): void {
		// A caller reading results[0] must get the first problem, not a hole where an 'ok' row was.
		$kept = Checksum_Results::only_problems(
			array( $this->row( 'a', 'ok' ), $this->row( 'b', 'ok' ), $this->row( 'c', 'modified' ) )
		);

		$this->assertArrayHasKey( 0, $kept );
		$this->assertSame( 'c', $kept[0]['file'] );
		$this->assertSame( array( 0 ), array_keys( $kept ) );
	}

	public function test_an_all_clean_verification_returns_nothing_to_read(): void {
		$results = array( $this->row( 'a', 'ok' ), $this->row( 'b', 'ok' ) );

		$this->assertSame( array(), Checksum_Results::only_problems( $results ) );
		$this->assertSame( 2, Checksum_Results::matched_count( $results ) );
	}

	public function test_empty_input_is_handled(): void {
		$this->assertSame( array(), Checksum_Results::only_problems( array() ) );
		$this->assertSame( 0, Checksum_Results::matched_count( array() ) );
	}

	/* ------------------------------------------------------------------ */
	/* Deliberate: unknown shapes are kept, never hidden                  */
	/* ------------------------------------------------------------------ */

	public function test_a_row_with_no_status_is_kept(): void {
		// The filter removes rows known to be fine. A row with no status is not known to be fine,
		// and dropping it would hide it.
		$kept = Checksum_Results::only_problems( array( array( 'file' => 'mystery.php' ) ) );

		$this->assertCount( 1, $kept );
	}

	public function test_a_non_array_row_is_kept(): void {
		$kept = Checksum_Results::only_problems( array( 'unexpected', $this->row( 'a', 'ok' ) ) );

		$this->assertSame( array( 'unexpected' ), $kept );
	}

	public function test_an_unrecognised_status_is_kept(): void {
		$kept = Checksum_Results::only_problems( array( $this->row( 'a', 'something-new' ) ) );

		$this->assertCount( 1, $kept, 'A status this code does not know about must survive the filter.' );
	}

	public function test_status_matching_is_exact(): void {
		// 'okay' and 'OK' are not 'ok'. Loosening this would silently drop rows.
		foreach ( array( 'okay', 'OK', 'Ok', ' ok' ) as $status ) {
			$this->assertCount(
				1,
				Checksum_Results::only_problems( array( $this->row( 'a', $status ) ) ),
				'"' . $status . '" must not be treated as ok.'
			);
		}
	}

	/* ------------------------------------------------------------------ */
	/* The measured case                                                  */
	/* ------------------------------------------------------------------ */

	public function test_the_core_install_that_broke_the_response_limit(): void {
		// 3,766 rows / 3,763 ok / 2 modified / 1 missing, measured on a clean WordPress 7.1.4.
		$results = array();

		for ( $i = 0; $i < 3763; $i++ ) {
			$results[] = $this->row( 'wp-includes/file-' . $i . '.php', 'ok' );
		}

		$results[] = $this->row( 'wp-content/plugins/hello.php', 'missing' );
		$results[] = $this->row( 'wp-content/themes/twentytwentyfive/style.css', 'modified' );
		$results[] = $this->row( 'wp-content/themes/twentytwentyfive/readme.txt', 'modified' );

		$this->assertCount( 3766, $results );

		$kept = Checksum_Results::only_problems( $results );

		$this->assertCount( 3, $kept, 'Three rows out of 3,766 were worth reading.' );
		$this->assertSame( 3763, Checksum_Results::matched_count( $results ) );
		$this->assertLessThan(
			strlen( (string) wp_json_encode( $results ) ) / 100,
			strlen( (string) wp_json_encode( $kept ) ),
			'The trimmed payload must be a small fraction of the original, not merely smaller.'
		);
	}
}
