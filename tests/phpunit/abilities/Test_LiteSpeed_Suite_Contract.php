<?php
/**
 * Feature 104 — the whole-suite contract.
 *
 * Verifies the 61 abilities as a SET rather than individually: the complete slug list, the sub-group
 * split and the confirmation set. A per-ability test cannot catch a missing ability, a duplicated
 * slug, or a sub-group that drifted.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.36
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_LiteSpeed_Suite_Contract extends WP_UnitTestCase {

	/**
	 * Every ability's class => slug. This is the feature's inventory: adding an ability without
	 * updating it fails, which is the point.
	 *
	 * @return array<string,string>
	 */
	private static function inventory(): array {
		return array(
			// ls-purge (9).
			'Get_Cache_Status'              => 'get-cache-status',
			'Get_Purge_Settings'            => 'get-purge-settings',
			'Purge_By_Tag'                  => 'purge-by-tag',
			'Purge_Cache'                   => 'purge-cache',
			'Purge_Post'                    => 'purge-post',
			'Purge_Taxonomy'                => 'purge-taxonomy',
			'Purge_Url'                     => 'purge-url',
			'Update_Purge_Settings'         => 'update-purge-settings',
			'Update_Scheduled_Purge'        => 'update-scheduled-purge',
			// ls-cache (10).
			'Get_Cache_Settings'            => 'get-cache-settings',
			'Get_Cache_Vary'                => 'get-cache-vary',
			'List_Cache_Exclusions'         => 'list-cache-exclusions',
			'Set_Cache_State'               => 'set-cache-state',
			'Update_Cache_Exclusions'       => 'update-cache-exclusions',
			'Update_Cache_Scope'            => 'update-cache-scope',
			'Update_Cache_Settings'         => 'update-cache-settings',
			'Update_Cache_Ttl'              => 'update-cache-ttl',
			'Update_Cache_Vary'             => 'update-cache-vary',
			'Update_Guest_Mode'             => 'update-guest-mode',
			// ls-optimize (11).
			'Get_Optimization_Settings'     => 'get-optimization-settings',
			'Get_Optimization_Status'       => 'get-optimization-status',
			'List_Optimization_Exclusions'  => 'list-optimization-exclusions',
			'Purge_Optimization_Cache'      => 'purge-optimization-cache',
			'Update_Css_Settings'           => 'update-css-settings',
			'Update_Font_Settings'          => 'update-font-settings',
			'Update_Html_Settings'          => 'update-html-settings',
			'Update_Js_Settings'            => 'update-js-settings',
			'Update_Localization_Settings'  => 'update-localization-settings',
			'Update_Optimization_Exclusions'=> 'update-optimization-exclusions',
			'Update_Tuning_Settings'        => 'update-tuning-settings',
			// ls-media (6).
			'Get_Media_Settings'            => 'get-media-settings',
			'Get_Media_Status'              => 'get-media-status',
			'Update_Lazyload_Settings'      => 'update-lazyload-settings',
			'Update_Media_Exclusions'       => 'update-media-exclusions',
			'Update_Placeholder_Settings'   => 'update-placeholder-settings',
			'Update_Viewport_Settings'      => 'update-viewport-settings',
			// ls-crawler (8).
			'Get_Crawler_Map'               => 'get-crawler-map',
			'Get_Crawler_Settings'          => 'get-crawler-settings',
			'Get_Crawler_Status'            => 'get-crawler-status',
			'List_Crawlers'                 => 'list-crawlers',
			'Reset_Crawler'                 => 'reset-crawler',
			'Run_Crawler'                   => 'run-crawler',
			'Set_Crawler_State'             => 'set-crawler-state',
			'Update_Crawler_Settings'       => 'update-crawler-settings',
			// ls-database (4).
			'Get_Autoload_Summary'          => 'get-autoload-summary',
			'Get_Database_Summary'          => 'get-database-summary',
			'List_Myisam_Tables'            => 'list-myisam-tables',
			'Plan_Database_Cleanup'         => 'plan-database-cleanup',
			// ls-object (5).
			'Flush_Object_Cache'  => 'flush-object-cache',
			'Get_Object_Cache_Status'       => 'get-object-cache-status',
			'Test_Object_Cache_Connection'  => 'test-object-cache-connection',
			'Update_Browser_Cache_Settings' => 'update-browser-cache-settings',
			'Update_Object_Cache_Settings'  => 'update-object-cache-settings',
			// ls-toolbox (8).
			'Apply_Preset'                  => 'apply-preset',
			'Export_Settings'               => 'export-settings',
			'Get_Advanced_Settings'         => 'get-advanced-settings',
			'Get_Environment_Report'        => 'get-environment-report',
			'List_Preset_Backups'           => 'list-preset-backups',
			'List_Settings_Areas'           => 'list-settings-areas',
			'Restore_Preset_Backup'         => 'restore-preset-backup',
			'Update_Advanced_Settings'      => 'update-advanced-settings',
		);
	}

	/**
	 * Abilities that confirm before acting.
	 *
	 * None is annotated destructive: nothing here deletes data. They are gated because each changes
	 * the whole site at once with nothing on the front end to say so — the same silent-behaviour
	 * shape as Contact Form 7's skip_mail. Applying a preset and restoring a backup are recoverable
	 * through litespeed/list-preset-backups, which is why they confirm rather than being marked
	 * destructive.
	 *
	 * `Set_Cache_State` is absent from this list deliberately: it confirms only when DISABLING, which
	 * it enforces inside run() rather than through requires_confirmation(). Gating both directions
	 * would make turning caching back on need a confirmation, which protects nothing.
	 *
	 * @return string[]
	 */
	private static function confirmed(): array {
		return array( 'Apply_Preset', 'Restore_Preset_Backup' );
	}

	private static function abilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/LiteSpeed/';
	}

	private static function src( string $class ): string {
		return (string) file_get_contents( self::abilities_dir() . $class . '.php' );
	}

	public function test_suite_has_exactly_sixty_one_abilities(): void {
		$this->assertCount( 61, self::inventory() );
	}

	/**
	 * The inventory and the filesystem must agree in both directions, so neither an unlisted file nor
	 * a listed-but-missing file can slip through.
	 */
	public function test_inventory_matches_the_filesystem(): void {
		$skip  = array( 'Category_Registrar', 'Base_LiteSpeed_Ability', 'Base_Settings_Read_Ability', 'Base_Settings_Write_Ability' );
		$found = array_values(
			array_filter(
				array_map(
					static fn( string $f ): string => basename( $f, '.php' ),
					array_map( 'strval', (array) glob( self::abilities_dir() . '*.php' ) )
				),
				static fn( string $c ): bool => ! in_array( $c, $skip, true )
			)
		);

		$expected = array_keys( self::inventory() );
		sort( $expected );
		sort( $found );

		$this->assertSame( $expected, $found );
	}

	public function test_every_slug_is_unique(): void {
		$slugs = array_values( self::inventory() );

		$this->assertSame( $slugs, array_unique( $slugs ) );
	}

	/**
	 * DEC-SLUG-CONVENTION-VERB-FIRST — verb-first kebab-case, no namespace in the slug.
	 */
	public function test_slugs_are_verb_first_kebab_case(): void {
		$verbs = array( 'get', 'list', 'set', 'update', 'purge', 'run', 'reset', 'apply', 'restore', 'export', 'test', 'flush', 'plan' );

		foreach ( self::inventory() as $class => $slug ) {
			$this->assertMatchesRegularExpression( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug, "{$class}: '{$slug}' is not kebab-case." );
			$this->assertStringNotContainsString( 'litespeed', $slug, "{$class}: the slug must not repeat the namespace." );
			$this->assertContains( explode( '-', $slug )[0], $verbs, "{$class}: '{$slug}' does not start with a known verb." );
		}
	}

	public function test_every_class_declares_its_slug(): void {
		foreach ( self::inventory() as $class => $slug ) {
			$this->assertStringContainsString( "return '{$slug}';", self::src( $class ), "{$class} must return '{$slug}' from slug()." );
		}
	}

	/**
	 * The sub-group split is the shape the brief committed to and the admin renders.
	 */
	public function test_the_sub_group_split_is_as_specified(): void {
		$counts = array();

		foreach ( array_keys( self::inventory() ) as $class ) {
			preg_match( "/function sub_group\(\): string \{\s*return '([a-z-]+)';/", self::src( $class ), $m );
			$counts[ $m[1] ] = ( $counts[ $m[1] ] ?? 0 ) + 1;
		}

		ksort( $counts );

		$this->assertSame(
			array(
				'ls-cache'    => 10,
				'ls-crawler'  => 8,
				'ls-database' => 4,
				'ls-media'    => 6,
				'ls-object'   => 5,
				'ls-optimize' => 11,
				'ls-purge'    => 9,
				'ls-toolbox'  => 8,
			),
			$counts
		);
	}

	/**
	 * Every sub-group the abilities use must have a label in the base, or the admin renders a bare key.
	 */
	public function test_every_sub_group_has_a_label(): void {
		$base = (string) file_get_contents( self::abilities_dir() . 'Base_LiteSpeed_Ability.php' );

		foreach ( array( 'ls-purge', 'ls-cache', 'ls-optimize', 'ls-media', 'ls-crawler', 'ls-database', 'ls-object', 'ls-toolbox' ) as $sub ) {
			$this->assertMatchesRegularExpression(
				"/'{$sub}'\s*=>\s*__\(/",
				$base,
				"Base_LiteSpeed_Ability::sub_group_labels() has no label for '{$sub}'."
			);
		}
	}

	/**
	 * The confirmation set is exactly what it should be — no more, and no less.
	 */
	public function test_the_confirmation_set_is_exact(): void {
		$found = array();

		foreach ( array_keys( self::inventory() ) as $class ) {
			if ( preg_match( '/function requires_confirmation\(\): bool \{\s*return true;/', self::src( $class ) ) ) {
				$found[] = $class;
			}
		}

		sort( $found );
		$expected = self::confirmed();
		sort( $expected );

		$this->assertSame( $expected, $found );
	}

	/**
	 * Nothing in this suite deletes data, so nothing may claim to.
	 *
	 * The destructive annotation exists to warn a client about irreversibility. LiteSpeed's database
	 * cleanup — the one genuinely destructive operation — is absent from the suite because it has no
	 * entry point callable from an ability, so a destructive:true here would be describing something
	 * that does not happen.
	 */
	public function test_no_ability_claims_to_be_destructive(): void {
		foreach ( array_keys( self::inventory() ) as $class ) {
			$this->assertDoesNotMatchRegularExpression(
				"/'destructive'\s*=>\s*true/",
				self::src( $class ),
				"{$class} claims destructive:true, but nothing in this suite deletes data."
			);
		}
	}

	/**
	 * Turning the cache off must confirm, and turning it on must not.
	 *
	 * Enforced inside run() rather than through requires_confirmation(), so it gets its own assertion.
	 */
	public function test_disabling_the_cache_confirms_but_enabling_does_not(): void {
		$src = self::src( 'Set_Cache_State' );

		$this->assertMatchesRegularExpression(
			'/if \(\s*! \$enabled && empty\( \$input\[.confirm.\] \)\s*\)/',
			$src,
			'Set_Cache_State must confirm only when disabling.'
		);
		$this->assertStringContainsString( "'confirmation_required'", $src );
		$this->assertStringNotContainsString( 'function requires_confirmation', $src, 'Set_Cache_State must not gate both directions.' );
	}

	/**
	 * Every ability named in a suggested_abilities() list must actually exist.
	 *
	 * A suggestion is a dead end a client will follow: it reads "call this next", tries it, and gets
	 * ability_not_found. Suggestions pointing outside this suite are legitimate — the whole point of
	 * litespeed/plan-database-cleanup is to hand the caller to the database and cache toolsets — so
	 * external slugs are allowed, but only from an explicit list that a human has checked.
	 *
	 * @return string[]
	 */
	private static function external_suggestions(): array {
		return array(
			'cache/delete-expired-transients',
			'cache/flush-transients',
			'comments/delete-comment',
			'comments/list-comments',
			'database/audit-core-table-engines',
			'database/audit-options-health',
			'database/convert-core-tables-to-innodb',
			'database/delete-db-rows',
			'database/set-option-autoload',
		);
	}

	public function test_every_suggested_ability_exists(): void {
		$own      = array();
		$suggested = array();

		foreach ( self::inventory() as $class => $slug ) {
			$own[] = 'litespeed/' . $slug;
		}

		foreach ( array_keys( self::inventory() ) as $class ) {
			if ( ! preg_match( '/function suggested_abilities\(\): array \{(.*?)\n\t\}/s', self::src( $class ), $m ) ) {
				continue;
			}

			preg_match_all( "/'([a-z0-9-]+\/[a-z0-9-]+)'/", $m[1], $found );

			foreach ( $found[1] as $slug ) {
				$suggested[ $slug ][] = $class;
			}
		}

		$this->assertNotEmpty( $suggested, 'No ability suggests a follow-up; the framework is going unused.' );

		$known = array_merge( $own, self::external_suggestions() );

		foreach ( $suggested as $slug => $classes ) {
			$this->assertContains(
				$slug,
				$known,
				sprintf(
					'%s suggests "%s", which is neither in this suite nor on the checked external list. '
						. 'A client following it gets ability_not_found.',
					implode( ', ', $classes ),
					$slug
				)
			);
		}
	}

	/**
	 * Every cleanup type LiteSpeed counts must have a recommendation.
	 *
	 * The two lists live side by side in Database_Repository so they can be compared here. Without
	 * this, a future LiteSpeed release adding a cleanup type would leave a row in
	 * litespeed/plan-database-cleanup with an empty `ability` and nothing would notice.
	 */
	public function test_every_cleanup_type_has_a_recommendation(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/LiteSpeed/Database_Repository.php'
		);

		preg_match( '/function types\(\): array \{(.*?)\n\t\}/s', $src, $t );
		preg_match( '/function recommendations\(\): array \{(.*?)\n\t\}/s', $src, $r );

		$this->assertNotEmpty( $t, 'types() not found.' );
		$this->assertNotEmpty( $r, 'recommendations() not found.' );

		preg_match_all( "/'([a-z_-]+)'\s*=> __\(/", $t[1], $types );
		preg_match_all( "/'([a-z_-]+)'\s*=> array\(/", $r[1], $recs );

		$missing = array_diff( $types[1], $recs[1] );
		$extra   = array_diff( $recs[1], $types[1] );

		$this->assertSame( array(), array_values( $missing ), 'Cleanup types with no recommendation: ' . implode( ', ', $missing ) );
		$this->assertSame( array(), array_values( $extra ), 'Recommendations for types that do not exist: ' . implode( ', ', $extra ) );
	}

	/**
	 * The group key is load-bearing: it decides the tab, the counts, the REST filter, the Toolset
	 * column AND which MCP dispatcher tool exposes the ability. The base is the only place that sets
	 * it, and it must equal what the integration declares.
	 */
	public function test_the_tab_group_matches_the_integration_declaration(): void {
		$base        = (string) file_get_contents( self::abilities_dir() . 'Base_LiteSpeed_Ability.php' );
		$integration = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/LiteSpeed_Cache.php' );

		$this->assertMatchesRegularExpression( "/const TAB_GROUP = 'litespeed-cache';/", $base );
		$this->assertMatchesRegularExpression( "/const TAB_GROUP = 'litespeed-cache';/", $integration );
	}

	/**
	 * Every ability the bootstrap must instantiate is actually instantiated there. A class nothing
	 * constructs never registers, and no other test in this suite would notice.
	 */
	public function test_the_bootstrap_instantiates_every_ability(): void {
		$bootstrap = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		foreach ( array_keys( self::inventory() ) as $class ) {
			$this->assertStringContainsString(
				'new LiteSpeed\\' . $class . '()',
				$bootstrap,
				"{$class} is never instantiated in the bootstrap, so it never registers."
			);
		}
	}
}
