<?php
/**
 * Feature 136 — the rules applied to a WordPress.org version list.
 *
 * Every rule here came from calling the real API first: akismet returns 94 entries and one of them
 * is `trunk`; twentytwenty returns 24; a premium plugin returns WP_Error. Pure, so these execute.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\WpRollback\Asset_Versions;

require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/WpRollback/Asset_Versions.php';

final class Test_Asset_Versions extends TestCase {

	/* ------------------------------------------------------------------ */
	/* trunk is not a release                                             */
	/* ------------------------------------------------------------------ */

	public function test_trunk_is_never_offered_as_a_rollback_target(): void {
		// Measured: plugins_api for akismet returns ... 5.7, 5.7.1, 5.7.2, trunk. `trunk` is
		// whatever was last committed, so offering it as a version to roll back to is offering a
		// moving target.
		$releases = Asset_Versions::releases(
			array(
				'5.7'   => 'u',
				'5.7.1' => 'u',
				'trunk' => 'u',
			)
		);

		$this->assertNotContains( 'trunk', $releases );
		$this->assertSame( array( '5.7.1', '5.7' ), $releases );
	}

	public function test_trunk_is_matched_case_insensitively(): void {
		$this->assertSame( array(), Asset_Versions::releases( array( 'TRUNK' => 'u', 'Trunk' => 'u' ) ) );
	}

	public function test_entries_that_are_not_versions_are_dropped(): void {
		// The keys come from an external API. A target version_compare() cannot reason about is
		// worse than an omitted one.
		$releases = Asset_Versions::releases(
			array(
				'1.0'      => 'u',
				'beta'     => 'u',
				''         => 'u',
				'v2.0'     => 'u',
				'2.0'      => 'u',
			)
		);

		$this->assertSame( array( '2.0', '1.0' ), $releases );
	}

	/* ------------------------------------------------------------------ */
	/* Ordering                                                           */
	/* ------------------------------------------------------------------ */

	public function test_releases_come_back_newest_first(): void {
		$releases = Asset_Versions::releases(
			array(
				'1.9'    => 'u',
				'1.10'   => 'u',
				'1.2'    => 'u',
				'2.0'    => 'u',
				'1.10.1' => 'u',
			)
		);

		// 1.10 sorts above 1.9 — string sorting would get this wrong, which is why version_compare.
		$this->assertSame( array( '2.0', '1.10.1', '1.10', '1.9', '1.2' ), $releases );
	}

	public function test_duplicates_collapse(): void {
		$this->assertSame( array( '1.0' ), Asset_Versions::releases( array( '1.0' => 'a' ) + array( '1.0' => 'b' ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* Pagination — 94 versions for a small plugin                        */
	/* ------------------------------------------------------------------ */

	public function test_a_long_release_history_is_paginated(): void {
		$releases = array();
		for ( $i = 94; $i >= 1; $i-- ) {
			$releases[] = '1.' . $i;
		}

		$page = Asset_Versions::page( $releases );

		$this->assertCount( Asset_Versions::DEFAULT_LIMIT, $page['versions'] );
		$this->assertSame( 94, $page['total'] );
		$this->assertTrue( $page['has_more'] );
	}

	public function test_the_last_page_reports_no_more(): void {
		$page = Asset_Versions::page( array( 'a', 'b', 'c' ), 10, 0 );

		$this->assertSame( 3, $page['total'] );
		$this->assertFalse( $page['has_more'] );
	}

	public function test_an_offset_past_the_end_is_empty_and_not_an_error(): void {
		$page = Asset_Versions::page( array( '1.0', '2.0' ), 10, 50 );

		$this->assertSame( array(), $page['versions'] );
		$this->assertFalse( $page['has_more'] );
		$this->assertSame( 2, $page['total'] );
	}

	public function test_limit_is_clamped_both_ways(): void {
		$releases = array_fill( 0, 500, '1.0' );

		$this->assertCount( Asset_Versions::MAX_LIMIT, Asset_Versions::page( $releases, 9999 )['versions'] );
		$this->assertCount( 1, Asset_Versions::page( $releases, 0 )['versions'] );
		$this->assertCount( 1, Asset_Versions::page( $releases, -5 )['versions'] );
	}

	public function test_a_negative_offset_is_treated_as_the_start(): void {
		$page = Asset_Versions::page( array( '2.0', '1.0' ), 1, -10 );

		$this->assertSame( array( '2.0' ), $page['versions'] );
		$this->assertSame( 0, $page['offset'] );
	}

	/* ------------------------------------------------------------------ */
	/* Relation to what is installed                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function relation_provider(): array {
		return array(
			'downgrade'          => array( '4.3.1', '4.3.5', 'older' ),
			'upgrade'            => array( '4.4.0', '4.3.5', 'newer' ),
			'same'               => array( '4.3.5', '4.3.5', 'same' ),
			// version_compare( '1.0', '1.0.0' ) is -1, not 0 — PHP treats the shorter string as
			// lower. Worth pinning, because "those are the same version" is the intuitive and
			// wrong reading.
			'shorter is older'   => array( '1.0', '1.0.0', 'older' ),
			'no candidate'       => array( '', '1.0', 'unknown' ),
			'no installed'       => array( '1.0', '', 'unknown' ),
		);
	}

	/**
	 * @dataProvider relation_provider
	 * @param string $candidate Target.
	 * @param string $installed Installed.
	 * @param string $expected  Expected relation.
	 */
	public function test_relation_is_named_not_numeric( string $candidate, string $installed, string $expected ): void {
		$this->assertSame( $expected, Asset_Versions::relation( $candidate, $installed ) );
	}

	/* ------------------------------------------------------------------ */
	/* Publication check is exact                                         */
	/* ------------------------------------------------------------------ */

	public function test_publication_check_is_an_exact_tag_match(): void {
		$releases = array( '1.0.0', '2.0' );

		$this->assertTrue( Asset_Versions::is_published( '1.0.0', $releases ) );
		$this->assertTrue( Asset_Versions::is_published( '2.0', $releases ) );

		// WordPress.org keys its package map by the exact tag, so when only 1.0.0 is published,
		// '1.0' is simply not a key. Asking for a tag that does not exist must fail rather than
		// silently resolve to a neighbouring release.
		$this->assertFalse( Asset_Versions::is_published( '1.0', $releases ) );
		$this->assertFalse( Asset_Versions::is_published( '2.0.0', $releases ) );
	}

	public function test_an_empty_release_list_publishes_nothing(): void {
		$this->assertFalse( Asset_Versions::is_published( '1.0', array() ) );
	}
}
