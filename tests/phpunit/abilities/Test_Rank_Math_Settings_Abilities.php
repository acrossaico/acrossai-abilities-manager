<?php
/**
 * Feature 069 — source-inspection tests for the six settings abilities.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.28
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath\Settings_Registry;
use WP_UnitTestCase;

class Test_Rank_Math_Settings_Abilities extends WP_UnitTestCase {

	private static function src( string $class ): string {
		return (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/RankMath/' . $class . '.php'
		);
	}

	/**
	 * @return array<string,array{0:string,1:string,2:string}> class, slug, cap
	 */
	public static function provide_abilities(): array {
		return array(
			'get-settings-panel'               => array( 'Get_Settings_Panel', 'get-settings-panel', 'general' ),
			'update-general-settings'          => array( 'Update_General_Settings', 'update-general-settings', 'general' ),
			'update-title-settings'            => array( 'Update_Title_Settings', 'update-title-settings', 'titles' ),
			'update-sitemap-settings'          => array( 'Update_Sitemap_Settings', 'update-sitemap-settings', 'sitemap' ),
			'update-instant-indexing-settings' => array( 'Update_Instant_Indexing_Settings', 'update-instant-indexing-settings', 'general' ),
			'update-robots-txt'                => array( 'Update_Robots_Txt', 'update-robots-txt', 'general' ),
		);
	}

	/**
	 * @dataProvider provide_abilities
	 */
	public function test_slug_and_capability( string $class, string $slug, string $cap ): void {
		$src = self::src( $class );
		$this->assertStringContainsString( "return '{$slug}';", $src );
		$this->assertStringContainsString( "return '{$cap}';", $src );
	}

	/**
	 * @dataProvider provide_abilities
	 */
	public function test_belongs_to_the_settings_sub_group( string $class ): void {
		$src = self::src( $class );
		$has = str_contains( $src, "return 'rank-math-settings';" )
			|| str_contains( $src, 'extends Base_Settings_Write_Ability' );
		$this->assertTrue( $has, "{$class} must sit in the rank-math-settings sub-group." );
	}

	/**
	 * None of the six is destructive: settings writes are reversible by writing
	 * the previous value back, so a confirm gate here would only train agents to
	 * pass confirm reflexively.
	 *
	 * @dataProvider provide_abilities
	 */
	public function test_none_are_destructive( string $class ): void {
		$this->assertDoesNotMatchRegularExpression( "/'destructive'\s*=>\s*true/", self::src( $class ) );
	}

	// -----------------------------------------------------------------
	// Get_Settings_Panel — the runtime capability re-check.
	// -----------------------------------------------------------------

	/**
	 * permission_callback receives no input, so it cannot branch on the requested
	 * panel. The panel's own capability must therefore be re-checked inside run().
	 * This is the only runtime capability check in the suite and is deliberate.
	 */
	public function test_get_settings_rechecks_the_panel_capability(): void {
		$src = self::src( 'Get_Settings_Panel' );
		$this->assertStringContainsString( 'Rank_Math_Guard::has_cap( $cap )', $src );
		$this->assertStringContainsString( "'insufficient_capability'", $src );
		$this->assertStringContainsString( "\$cap = (string) \$definition['cap'];", $src );
		// And the deviation must be documented, or a reviewer reads it as a hole.
		$this->assertStringContainsString( 'CAPABILITY NOTE', $src );
	}

	public function test_get_settings_enum_is_sourced_from_the_registry(): void {
		$src = self::src( 'Get_Settings_Panel' );
		$this->assertStringContainsString( "'enum'        => Settings_Registry::panel_slugs()", $src );
		$this->assertCount( 20, Settings_Registry::panel_slugs() );
	}

	public function test_get_settings_reports_robots_txt_state(): void {
		$src = self::src( 'Get_Settings_Panel' );
		$this->assertStringContainsString( "'general-robots-txt' === \$panel", $src );
		$this->assertStringContainsString( 'physical_file_exists', $src );
		$this->assertStringContainsString( 'site_not_public', $src );
	}

	// -----------------------------------------------------------------
	// Scope enums must resolve to real registry panels.
	// -----------------------------------------------------------------

	/**
	 * The scope enum and the registry's panel list must not drift apart — that is
	 * the failure mode a consolidated enum ability introduces. Every enum member
	 * must map to a panel that actually exists, and to the right option blob.
	 *
	 * @dataProvider provide_scope_enums
	 */
	public function test_every_scope_maps_to_a_real_panel( string $prefix, string $option_type, array $scopes ): void {
		foreach ( $scopes as $scope ) {
			$panel      = $prefix . $scope;
			$definition = Settings_Registry::panel( $panel );
			$this->assertNotNull( $definition, "Scope '{$scope}' maps to missing panel '{$panel}'." );
			$this->assertSame( $option_type, $definition['option_type'], "Panel '{$panel}' writes the wrong option blob." );
		}
	}

	/**
	 * @return array<string,array{0:string,1:string,2:string[]}>
	 */
	public static function provide_scope_enums(): array {
		return array(
			'general' => array( 'general-', 'general', array( 'links', 'breadcrumbs', 'webmaster', 'image-seo', '404-monitor', 'redirections', 'others' ) ),
			'titles'  => array( 'titles-', 'titles', array( 'global', 'homepage', 'author', 'misc', 'social', 'local-seo', 'post-type', 'taxonomy' ) ),
			'sitemap' => array( 'sitemap-', 'sitemap', array( 'general', 'post-type', 'taxonomy' ) ),
		);
	}

	/**
	 * robots.txt and Instant Indexing have their own abilities, so they must not
	 * also be reachable through the general writer — two paths to one field is
	 * exactly the duplication this design avoids.
	 */
	public function test_general_writer_excludes_the_standalone_panels(): void {
		$src = self::src( 'Update_General_Settings' );
		preg_match( '/function scope_enum\(\): array \{\s*return array\((.*?)\);/s', $src, $m );
		$this->assertNotEmpty( $m );
		$this->assertStringNotContainsString( "'robots-txt'", $m[1] );
		$this->assertStringNotContainsString( "'instant-indexing'", $m[1] );
	}

	// -----------------------------------------------------------------
	// Standalone writers.
	// -----------------------------------------------------------------

	/**
	 * A physical robots.txt overrides the virtual one, so a write would silently
	 * do nothing. Refuse it and say why.
	 */
	public function test_robots_txt_refuses_when_a_physical_file_exists(): void {
		$src = self::src( 'Update_Robots_Txt' );
		$this->assertStringContainsString( "file_exists( \$physical )", $src );
		$this->assertStringContainsString( "get_option( 'blog_public' )", $src );
		// Both guards must precede the write.
		$guard = strpos( $src, 'file_exists( $physical )' );
		$write = strpos( $src, 'Settings_Writer::save(' );
		$this->assertNotFalse( $guard );
		$this->assertNotFalse( $write );
		$this->assertLessThan( $write, $guard );
	}

	public function test_robots_txt_requires_its_module(): void {
		$this->assertStringContainsString( "return 'robots-txt';", self::src( 'Update_Robots_Txt' ) );
	}

	public function test_sitemap_writer_requires_the_sitemap_module(): void {
		$this->assertStringContainsString( "return 'sitemap';", self::src( 'Update_Sitemap_Settings' ) );
	}

	public function test_instant_indexing_writer_requires_its_module(): void {
		$this->assertStringContainsString( "return 'instant-indexing';", self::src( 'Update_Instant_Indexing_Settings' ) );
	}

	/**
	 * The key location is derived from home_url() and never stored, so it is
	 * reported but not writable.
	 */
	public function test_instant_indexing_key_location_is_reported_not_written(): void {
		$src = self::src( 'Update_Instant_Indexing_Settings' );
		$this->assertStringContainsString( 'Instant_Indexing_Repository::key_location()', $src );
		$types = Settings_Registry::field_types_for( 'general-instant-indexing' );
		$this->assertArrayNotHasKey( 'indexnow_api_key_location', $types );
	}

	// -----------------------------------------------------------------
	// Base_Settings_Write_Ability.
	// -----------------------------------------------------------------

	public function test_base_validates_the_object_against_the_registry(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/RankMath/Base_Settings_Write_Ability.php'
		);
		$this->assertStringContainsString( 'post_type_exists( $object )', $src );
		$this->assertStringContainsString( 'taxonomy_exists( $object )', $src );
		$this->assertStringContainsString( 'Settings_Writer::save( $panel, $object, $settings )', $src );
	}

	public function test_base_rejects_an_empty_settings_object(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/RankMath/Base_Settings_Write_Ability.php'
		);
		$this->assertStringContainsString( "array() === \$settings", $src );
	}
}
