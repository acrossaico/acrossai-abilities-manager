<?php
/**
 * Whether a WordPress release can run on the PHP a site is using.
 *
 * This exists because the check WordPress itself performs does not answer the question. Measured
 * on the live API: the 4.7.39 core offer declares `php_version: 5.2.4`, so
 * `version_compare( PHP_VERSION, $offer->php_version, '>=' )` — the comparison in
 * wp-admin/update-core.php — passes on PHP 8.4. It guards upgrades. For a downgrade it is the
 * wrong direction entirely, and the full offer carries no field bounding the maximum.
 *
 * Pure, so these execute rather than skip.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Core_Php_Compat;

require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Core_Php_Compat.php';

final class Test_Core_Php_Compat extends TestCase {

	/* ------------------------------------------------------------------ */
	/* php_series                                                          */
	/* ------------------------------------------------------------------ */

	public function test_series_drops_the_patch(): void {
		$this->assertSame( '8.4', Core_Php_Compat::php_series( '8.4.12' ) );
		$this->assertSame( '7.4', Core_Php_Compat::php_series( '7.4.33' ) );
		$this->assertSame( '8.0', Core_Php_Compat::php_series( '8.0' ) );
	}

	public function test_series_tolerates_a_build_suffix(): void {
		// Distro PHP reports things like 8.1.2-1ubuntu2.14; the series is still 8.1.
		$this->assertSame( '8.1', Core_Php_Compat::php_series( '8.1.2-1ubuntu2.14' ) );
		$this->assertSame( '8.2', Core_Php_Compat::php_series( '8.2.0-RC1' ) );
	}

	public function test_series_of_nonsense_is_empty(): void {
		$this->assertSame( '', Core_Php_Compat::php_series( '' ) );
		$this->assertSame( '', Core_Php_Compat::php_series( 'eight' ) );
	}

	/* ------------------------------------------------------------------ */
	/* The refusal that protects the site                                  */
	/* ------------------------------------------------------------------ */

	public function test_an_ancient_release_on_modern_php_is_refused(): void {
		// The case that motivated all of this: verified reachable on a live site, with a full
		// package, no confirmation and no guard.
		$a = Core_Php_Compat::assess( '4.7.39', '8.4.12' );

		$this->assertSame( 'unsupported', $a['verdict'] );
		$this->assertSame( '6.7', $a['minimum_wp'] );
	}

	public function test_the_refusal_names_the_series_not_the_patch(): void {
		// Support was added for the 8.4 series, not for 8.4.12. The first version of this message
		// said "Support for PHP 8.4.12 was first added in WordPress 6.7", which states something
		// about a patch release that is not true of it specifically.
		$message = Core_Php_Compat::assess( '4.7.39', '8.4.12' )['message'];

		$this->assertStringContainsString( 'PHP 8.4 series', $message );
		$this->assertStringNotContainsString( 'PHP 8.4.12 series', $message );
	}

	public function test_the_refusal_says_the_api_cannot_report_this(): void {
		// Otherwise the obvious response to a refusal is to go looking for the API field that
		// disagreed with us. There isn't one.
		$this->assertStringContainsString(
			'no maximum PHP version per release',
			Core_Php_Compat::assess( '4.7.39', '8.4.12' )['message']
		);
	}

	/**
	 * @dataProvider boundary_provider
	 * @param string $php      Running PHP.
	 * @param string $target   Target WordPress.
	 * @param string $expected Expected verdict.
	 */
	public function test_boundaries_of_the_support_table( string $php, string $target, string $expected ): void {
		$this->assertSame( $expected, Core_Php_Compat::assess( $target, $php )['verdict'] );
	}

	/**
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function boundary_provider(): array {
		return array(
			// Exactly the first supporting release is supported, not refused.
			'8.0 on the first release supporting it' => array( '8.0.30', '5.6', 'ok' ),
			'8.0 one release below'                  => array( '8.0.30', '5.5', 'unsupported' ),
			'8.1 at its floor'                       => array( '8.1.27', '5.9', 'ok' ),
			'8.1 below its floor'                    => array( '8.1.27', '5.8.19', 'unsupported' ),
			'8.4 at its floor'                       => array( '8.4.12', '6.7', 'ok' ),
			'8.4 below its floor'                    => array( '8.4.12', '6.6.11', 'unsupported' ),
			'7.4 tolerates an old release'           => array( '7.4.33', '5.3', 'ok' ),
			'7.4 below its floor'                    => array( '7.4.33', '5.2.30', 'unsupported' ),
			'a current release on current php'       => array( '8.4.12', '7.0.8', 'ok' ),
		);
	}

	public function test_a_patch_below_the_floor_is_still_refused(): void {
		// 6.6.11 is a patch of a series older than 6.7, so version_compare must treat it as below
		// the floor rather than being confused by the longer string.
		$this->assertSame( 'unsupported', Core_Php_Compat::assess( '6.6.11', '8.4.12' )['verdict'] );
	}

	/* ------------------------------------------------------------------ */
	/* Unknown is unknown, not a guess                                     */
	/* ------------------------------------------------------------------ */

	public function test_an_unlisted_php_series_yields_unknown_not_a_refusal(): void {
		// A wrong refusal blocks legitimate recovery; a wrong pass bricks a site. For a PHP series
		// the table does not cover, the only honest answer is that it is not established — and the
		// confirmation flag then carries the decision.
		$a = Core_Php_Compat::assess( '6.8.12', '9.5.0' );

		$this->assertSame( 'unknown', $a['verdict'] );
		$this->assertSame( '', $a['minimum_wp'] );
	}

	public function test_an_unlisted_series_still_explains_the_risk(): void {
		$this->assertStringContainsString(
			'fatals on every request',
			Core_Php_Compat::assess( '6.8.12', '9.5.0' )['message']
		);
	}

	public function test_a_missing_target_version_is_unknown(): void {
		$this->assertSame( 'unknown', Core_Php_Compat::assess( '', '8.4.12' )['verdict'] );
	}

	public function test_an_unparseable_php_is_unknown_and_says_so_without_an_empty_slot(): void {
		$a = Core_Php_Compat::assess( '6.8.12', '' );

		$this->assertSame( 'unknown', $a['verdict'] );
		// The series is empty here, so the message must fall back rather than rendering
		// "the PHP  series" with a hole in it.
		$this->assertStringNotContainsString( 'PHP  series', $a['message'] );
	}

	/* ------------------------------------------------------------------ */
	/* offer_minimums — the check core makes and Core_Upgrader skips       */
	/* ------------------------------------------------------------------ */

	public function test_offer_minimums_passes_when_the_site_exceeds_both(): void {
		$this->assertSame( array(), Core_Php_Compat::offer_minimums( '7.4', '5.5.5', '8.4.12', '8.0.35' ) );
	}

	public function test_offer_minimums_catches_php_below_the_requirement(): void {
		$reasons = Core_Php_Compat::offer_minimums( '8.2', '', '8.1.27', '' );

		$this->assertCount( 1, $reasons );
		$this->assertStringContainsString( 'PHP 8.2 or newer', $reasons[0] );
	}

	public function test_offer_minimums_catches_mysql_below_the_requirement(): void {
		$reasons = Core_Php_Compat::offer_minimums( '', '8.0', '8.4.12', '5.7.40' );

		$this->assertCount( 1, $reasons );
		$this->assertStringContainsString( 'MySQL 8.0 or newer', $reasons[0] );
	}

	public function test_offer_minimums_reports_both_when_both_fail(): void {
		$this->assertCount( 2, Core_Php_Compat::offer_minimums( '8.2', '8.0', '8.1.0', '5.7.40' ) );
	}

	public function test_offer_minimums_skips_what_it_cannot_measure(): void {
		// An unavailable MySQL version must not be read as "below the requirement" — that would
		// refuse a legitimate rollback on any host where $wpdb->db_version() returns nothing.
		$this->assertSame( array(), Core_Php_Compat::offer_minimums( '7.4', '8.0', '8.4.12', '' ) );
		$this->assertSame( array(), Core_Php_Compat::offer_minimums( '', '', '8.4.12', '8.0.35' ) );
	}

	public function test_offer_minimums_is_the_forward_direction_only(): void {
		// Documents why this is not the guard for a downgrade: an ancient offer declares an
		// ancient minimum, so modern PHP sails past it. Measured — 4.7.39 declares 5.2.4.
		$this->assertSame( array(), Core_Php_Compat::offer_minimums( '5.2.4', '5.0', '8.4.12', '8.0.35' ) );
		$this->assertSame( 'unsupported', Core_Php_Compat::assess( '4.7.39', '8.4.12' )['verdict'] );
	}
}
