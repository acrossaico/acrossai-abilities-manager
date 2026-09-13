<?php
/**
 * Feature 106 — the whole-suite contract.
 *
 * Verifies the 64 abilities as a SET: the slug list, the sub-group split, the confirmation set, and
 * that every suggested slug resolves. A per-ability test cannot catch a missing ability, a
 * duplicated slug, or an ability that moved sub-group without its counts moving too.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.38
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Yoast_Suite_Contract extends WP_UnitTestCase {

	private const DIR = '/includes/Abilities/Yoast/';

	/**
	 * Every ability's class => slug.
	 *
	 * Two slug namespaces, deliberately. SEO-domain abilities live under `seo/` and term operations
	 * under `taxonomies/`, because a slug names the resource acted on while the toolset names where an
	 * operator finds it (DEC-TOOLSET-SLUG-NAMESPACE). All 64 share tab_group `yoast-seo`.
	 *
	 * None of them may use the `yoast-seo/` namespace — that belongs to Yoast, whose own abilities
	 * this toolset adopts by prefix. Test_Yoast_Architecture enforces that separately.
	 *
	 * @return array<string,string>
	 */
	private static function inventory(): array {
		return array(
			// yoast-content (8).
			'Get_Content_Seo_Issues' => 'seo/get-content-seo-issues',
			'Get_Internal_Links' => 'seo/get-internal-links',
			'Get_Keyphrase_Usage' => 'seo/get-keyphrase-usage',
			'Get_Seo_Score_Summary' => 'seo/get-seo-score-summary',
			'List_Cornerstone_Content' => 'seo/list-cornerstone-content',
			'List_Low_Score_Content' => 'seo/list-low-score-content',
			'List_Orphaned_Content' => 'seo/list-orphaned-content',
			'Set_Cornerstone' => 'seo/set-cornerstone',
			// yoast-content-types (6).
			'Get_Post_Type_Seo' => 'seo/get-post-type-seo',
			'Get_Taxonomy_Seo' => 'seo/get-taxonomy-seo',
			'List_Content_Type_Settings' => 'seo/list-content-type-settings',
			'Update_Archive_Seo' => 'seo/update-archive-seo',
			'Update_Post_Type_Seo' => 'seo/update-post-type-seo',
			'Update_Taxonomy_Seo' => 'seo/update-taxonomy-seo',
			// yoast-indexables (9).
			'Get_Author_Archive_Seo' => 'seo/get-author-archive-seo',
			'Get_Homepage_Seo' => 'seo/get-homepage-seo',
			'Get_Indexable' => 'seo/get-indexable',
			'Get_Post_Type_Archive_Seo' => 'seo/get-post-type-archive-seo',
			'Get_System_Page_Seo' => 'seo/get-system-page-seo',
			'List_Indexables' => 'seo/list-indexables',
			'Update_Homepage_Seo' => 'seo/update-homepage-seo',
			'Update_Post_Type_Archive_Seo' => 'seo/update-post-type-archive-seo',
			'Update_System_Page_Seo' => 'seo/update-system-page-seo',
			// yoast-indexing (5).
			'Cleanup_Indexables' => 'seo/cleanup-indexables',
			'Get_Indexation_Counts' => 'seo/get-indexation-counts',
			'Get_Indexing_Status' => 'seo/get-indexing-status',
			'Reset_Indexing' => 'seo/reset-indexing',
			'Run_Indexing' => 'seo/run-indexing',
			// yoast-settings (18).
			'Export_Settings' => 'seo/export-settings',
			'Get_Seo_Settings' => 'seo/get-seo-settings',
			'Import_Settings' => 'seo/import-settings',
			'List_Settings_Areas' => 'seo/list-settings-areas',
			'Update_Advanced_Settings' => 'seo/update-advanced-settings',
			'Update_Archive_Settings' => 'seo/update-archive-settings',
			'Update_Breadcrumb_Settings' => 'seo/update-breadcrumb-settings',
			'Update_Crawl_Settings' => 'seo/update-crawl-settings',
			'Update_General_Settings' => 'seo/update-general-settings',
			'Update_Integration_Settings' => 'seo/update-integration-settings',
			'Update_Knowledge_Graph' => 'seo/update-knowledge-graph',
			'Update_Llms_Settings' => 'seo/update-llms-settings',
			'Update_Rss_Settings' => 'seo/update-rss-settings',
			'Update_Schema_Settings' => 'seo/update-schema-settings',
			'Update_Social_Defaults' => 'seo/update-social-defaults',
			'Update_Social_Profiles' => 'seo/update-social-profiles',
			'Update_Title_Templates' => 'seo/update-title-templates',
			'Update_Webmaster_Verification' => 'seo/update-webmaster-verification',
			// yoast-sitemap (6).
			'Get_Sitemap_Settings' => 'seo/get-sitemap-settings',
			'Get_Sitemap_Status' => 'seo/get-sitemap-status',
			'Invalidate_Sitemap' => 'seo/invalidate-sitemap',
			'Invalidate_Sitemap_For_Post' => 'seo/invalidate-sitemap-for-post',
			'List_Sitemap_Index' => 'seo/list-sitemap-index',
			'Update_Sitemap_Settings' => 'seo/update-sitemap-settings',
			// yoast-terms (6).
			'Clear_Term_Seo' => 'taxonomies/clear-term-seo',
			'Get_Primary_Term' => 'taxonomies/get-primary-term',
			'Get_Term_Seo' => 'taxonomies/get-term-seo',
			'List_Term_Seo' => 'taxonomies/list-term-seo',
			'Set_Primary_Term' => 'taxonomies/set-primary-term',
			'Update_Term_Seo' => 'taxonomies/update-term-seo',
			// yoast-tools (6).
			'Get_First_Time_Config' => 'seo/get-first-time-config',
			'Get_Import_Status' => 'seo/get-import-status',
			'Get_Robots_Settings' => 'seo/get-robots-settings',
			'Get_Seo_Status' => 'seo/get-seo-status',
			'List_Conflicting_Plugins' => 'seo/list-conflicting-plugins',
			'Update_Robots_Settings' => 'seo/update-robots-settings',
		);
	}

	/**
	 * Abilities per sub-group.
	 *
	 * @return array<string,int>
	 */
	private static function sub_group_counts(): array {
		return array(
			'yoast-content' => 8,
			'yoast-content-types' => 6,
			'yoast-indexables' => 9,
			'yoast-indexing' => 5,
			'yoast-settings' => 18,
			'yoast-sitemap' => 6,
			'yoast-terms' => 6,
			'yoast-tools' => 6,
		);
	}

	/**
	 * The abilities that refuse to run without confirm: true.
	 *
	 * Pinned so a destructive ability cannot quietly lose its gate. `seo/cleanup-indexables` is
	 * deliberately absent: it removes orphaned indexable rows, which are derived data Yoast rebuilds
	 * and prunes on its own schedule, so gating it would add friction to routine maintenance.
	 * `seo/update-robots-settings` gates itself inside run(), and only in the direction that hides
	 * the site — see the assertion below.
	 *
	 * @return string[]
	 */
	private static function confirm_gated(): array {
		return array(
			'Clear_Term_Seo',
			'Import_Settings',
			'Reset_Indexing',
		);
	}

	private static function dir(): string {
		return dirname( __DIR__, 3 ) . self::DIR;
	}

	private static function source( string $class ): string {
		$file = self::dir() . $class . '.php';

		return is_readable( $file ) ? (string) file_get_contents( $file ) : '';
	}

	/**
	 * The inventory must match the directory exactly — in both directions.
	 *
	 * A one-way check passes when an ability is added and never pinned here, which is how a suite
	 * silently grows past its own contract.
	 */
	public function test_inventory_matches_the_directory(): void {
		$files = glob( self::dir() . '*.php' );
		$found = array();

		foreach ( is_array( $files ) ? $files : array() as $file ) {
			$base = basename( $file, '.php' );

			if ( in_array( $base, array( 'Category_Registrar', 'Base_Yoast_Ability', 'Base_Settings_Write_Ability' ), true ) ) {
				continue;
			}

			$found[] = $base;
		}

		sort( $found );
		$expected = array_keys( self::inventory() );
		sort( $expected );

		$this->assertSame( $expected, $found, 'The Yoast ability directory and this inventory have drifted.' );
	}

	public function test_every_slug_is_declared_and_unique(): void {
		$slugs = array();

		foreach ( self::inventory() as $class => $slug ) {
			$src = self::source( $class );

			$this->assertNotSame( '', $src, "{$class}.php is missing." );
			$this->assertMatchesRegularExpression(
				"/function slug\(\): string \{\s*return '" . preg_quote( $slug, '/' ) . "'/",
				$src,
				"{$class} does not declare slug {$slug}."
			);

			$slugs[] = $slug;
		}

		$this->assertSame(
			count( $slugs ),
			count( array_unique( $slugs ) ),
			'Duplicate slug in the Yoast suite: ' . implode( ', ', array_diff_assoc( $slugs, array_unique( $slugs ) ) )
		);
	}

	public function test_sub_group_counts_hold(): void {
		$counts = array();

		foreach ( self::inventory() as $class => $slug ) {
			$src = self::source( $class );

			if ( preg_match( "/function sub_group\(\): string \{\s*return '([^']+)'/", $src, $m ) ) {
				$group = $m[1];
			} else {
				$this->assertStringContainsString(
					'extends Base_Settings_Write_Ability',
					$src,
					"{$class} declares no sub_group and does not inherit one."
				);

				$group = 'yoast-settings';
			}

			$counts[ $group ] = ( $counts[ $group ] ?? 0 ) + 1;
		}

		ksort( $counts );
		$expected = self::sub_group_counts();
		ksort( $expected );

		$this->assertSame( $expected, $counts );
		$this->assertSame( count( self::inventory() ), array_sum( $counts ) );
	}

	/**
	 * Confirmation gates, pinned in both directions.
	 */
	public function test_confirmation_gates_are_exactly_as_declared(): void {
		$gated = array();

		foreach ( array_keys( self::inventory() ) as $class ) {
			if ( preg_match( '/function requires_confirmation\(\): bool \{\s*return true;/', self::source( $class ) ) ) {
				$gated[] = $class;
			}
		}

		sort( $gated );
		$expected = self::confirm_gated();
		sort( $expected );

		$this->assertSame( $expected, $gated, 'The set of confirm-gated Yoast abilities changed.' );
	}

	/**
	 * The site-wide noindex switch must gate itself, and only in the direction that hides the site.
	 */
	public function test_robots_writer_gates_the_destructive_direction( ): void {
		$src = self::source( 'Update_Robots_Settings' );

		$this->assertStringContainsString( 'confirmation_required', $src );
		$this->assertMatchesRegularExpression(
			'/\$discourage\s*&&\s*!\s*\$current\s*&&\s*empty\(\s*\$input\[.confirm.\]\s*\)/',
			$src,
			'Discouraging search engines noindexes the whole site and must require confirm; allowing them back must not.'
		);
	}

	/**
	 * Every suggested ability slug must be one that exists.
	 *
	 * A suggestion pointing at nothing is worse than none: the client calls it and gets a not-found
	 * it cannot act on. Suggestions may name abilities outside this suite, so the check is against
	 * the registry when it is populated and against this inventory otherwise.
	 */
	public function test_every_suggested_slug_resolves(): void {
		$own = array_values( self::inventory() );
		$checked = 0;

		foreach ( array_keys( self::inventory() ) as $class ) {
			$src = self::source( $class );

			if ( ! preg_match( '/function suggested_abilities\(\): array \{(.*?)\n\t\}/s', $src, $m ) ) {
				continue;
			}

			preg_match_all( "/'([a-z0-9-]+\/[a-z0-9-]+)'/", $m[1], $hits );

			foreach ( $hits[1] as $slug ) {
				++$checked;

				if ( function_exists( 'wp_get_ability' ) && null !== wp_get_ability( $slug ) ) {
					continue;
				}

				$this->assertContains(
					$slug,
					$own,
					"{$class} suggests \"{$slug}\", which is neither registered nor part of this suite."
				);
			}
		}

		$this->assertGreaterThan( 0, $checked, 'No suggestions were checked — the parser stopped matching.' );
	}
}
