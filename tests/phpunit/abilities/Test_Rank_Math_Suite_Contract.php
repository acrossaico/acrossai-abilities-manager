<?php
/**
 * Feature 069 — the whole-suite contract.
 *
 * Verifies the 59 abilities as a SET rather than individually: the complete slug list,
 * the capability map, the destructive set, and the invariants that only make sense
 * across the suite. A per-ability test cannot catch a missing ability, a duplicated
 * slug, or a capability that drifted from the documented map.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.28
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Rank_Math_Suite_Contract extends WP_UnitTestCase {

	/**
	 * Every ability's class => slug. This is the feature's inventory: adding an ability
	 * without updating it fails, which is the point.
	 *
	 * @return array<string,string>
	 */
	private static function inventory(): array {
		return array(
			// Settings (6).
			'Get_Settings_Panel'               => 'get-settings-panel',
			'Update_General_Settings'          => 'update-general-settings',
			'Update_Title_Settings'            => 'update-title-settings',
			'Update_Sitemap_Settings'          => 'update-sitemap-settings',
			'Update_Instant_Indexing_Settings' => 'update-instant-indexing-settings',
			'Update_Robots_Txt'                => 'update-robots-txt',
			// Instant Indexing (4).
			'Submit_Urls'                      => 'submit-urls',
			'Get_Indexing_Log'                 => 'get-indexing-log',
			'Clear_Indexing_Log'               => 'clear-indexing-log',
			'Reset_Indexing_Key'               => 'reset-indexing-key',
			// Redirections (9).
			'List_Redirections'                => 'list-redirections',
			'Find_Redirection'                 => 'find-redirection',
			'Get_Redirection_Stats'            => 'get-redirection-stats',
			'Export_Redirections'              => 'export-redirections',
			'Create_Redirection'               => 'create-redirection',
			'Update_Redirection'               => 'update-redirection',
			'Change_Redirection_Status'        => 'change-redirection-status',
			'Delete_Redirections'              => 'delete-redirections',
			'Delete_Trashed_Redirections'      => 'delete-trashed-redirections',
			// 404 monitor (2).
			'List_404_Logs'                    => 'list-404-logs',
			'Delete_404_Logs'                  => 'delete-404-logs',
			// Role manager (2).
			'Get_Role_Capabilities'            => 'get-role-capabilities',
			'Reset_Role_Capabilities'          => 'reset-role-capabilities',
			// Status / tools (8).
			'Get_Status'                       => 'get-status',
			'Run_Maintenance_Tool'             => 'run-maintenance-tool',
			'Export_Settings'                  => 'export-settings',
			'Import_Settings'                  => 'import-settings',
			'List_Backups'                     => 'list-backups',
			'Create_Backup'                    => 'create-backup',
			'Manage_Backup'                    => 'manage-backup',
			'Detect_Seo_Plugins'               => 'detect-seo-plugins',
			// Sitemap (3).
			'Get_Sitemap_Health'               => 'get-sitemap-health',
			'List_Sitemap_Urls'                => 'list-sitemap-urls',
			'Invalidate_Sitemap_Cache'         => 'invalidate-sitemap-cache',
			// Modules (2).
			'List_Modules'                     => 'list-modules',
			// Routes (2).
			'Get_Llms_Status'                  => 'get-llms-status',
			'Refresh_Llms_Route'               => 'refresh-llms-route',
			// Analytics (4).
			'Get_Analytics_Summary'            => 'get-analytics-summary',
			'Get_Analytics_Rows'               => 'get-analytics-rows',
			'Get_Index_Status'                 => 'get-index-status',
			'Inspect_Url'                      => 'inspect-url',
			// Content (9).
			'Update_Seo_Meta'                  => 'update-seo-meta',
			'Bulk_Update_Meta'                 => 'bulk-update-meta',
			'Update_Seo_Scores'                => 'update-seo-scores',
			'Get_Primary_Term'                 => 'get-primary-term',
			'Update_Primary_Term'              => 'update-primary-term',
			'Get_Rendered_Head'                => 'get-rendered-head',
			'Audit_Content_Seo'                => 'audit-content-seo',
			'Get_Inbound_Links'                => 'get-inbound-links',
			'Audit_Faq_Links'                  => 'audit-faq-links',
			// Schema (3).
			'Update_Post_Schemas'              => 'update-post-schemas',
			'Delete_Post_Schemas'              => 'delete-post-schemas',
			'Get_Schema_Status'                => 'get-schema-status',
			// SEO analysis (1).
			// Content AI (4).
			'Get_Content_Ai_Status'            => 'get-content-ai-status',
			'Manage_Content_Ai_Prompts'        => 'manage-content-ai-prompts',
			'Manage_Content_Ai_Output'         => 'manage-content-ai-output',
			'Research_Keyword'                 => 'research-keyword',
			// AI Visibility (2).
			'Get_Ai_Visibility_Brand'          => 'get-ai-visibility-brand',
			'Update_Ai_Visibility_Object'      => 'update-ai-visibility-object',
		);
	}

	/**
	 * Abilities that must declare destructive:true and confirm-gate.
	 *
	 * research-keyword and update-ai-visibility-object are here because they spend an
	 * unrecoverable paid balance. They destroy no data, but the destructive annotation
	 * exists to warn about irreversibility.
	 *
	 * @return string[]
	 */
	private static function destructive(): array {
		return array(
			'Clear_Indexing_Log',
			'Reset_Indexing_Key',
			'Delete_Redirections',
			'Delete_Trashed_Redirections',
			'Delete_404_Logs',
			'Reset_Role_Capabilities',
			'Run_Maintenance_Tool',
			'Import_Settings',
			'Manage_Backup',
			'Delete_Post_Schemas',
			'Research_Keyword',
			'Update_Ai_Visibility_Object',
		);
	}

	/**
	 * The capability map from contracts/abilities.md, as class => rank_math_* suffix.
	 * '' means the ability is gated on the floor alone, because Rank Math itself uses
	 * manage_options there and no granular capability exists to compose.
	 *
	 * @return array<string,string>
	 */
	private static function capability_map(): array {
		return array(
			'Update_General_Settings'          => 'general',
			'Update_Instant_Indexing_Settings' => 'general',
			'Update_Robots_Txt'                => 'general',
			'Submit_Urls'                      => 'general',
			'Get_Indexing_Log'                 => 'general',
			'Clear_Indexing_Log'               => 'general',
			'Reset_Indexing_Key'               => 'general',
			'Export_Redirections'              => 'general',
			'Export_Settings'                  => 'general',
			'Import_Settings'                  => 'general',
			'List_Backups'                     => 'general',
			'Create_Backup'                    => 'general',
			'Manage_Backup'                    => 'general',
			'Get_Rendered_Head'                => 'general',
			'Get_Llms_Status'                  => 'general',
			'Refresh_Llms_Route'               => 'general',
			'Update_Title_Settings'            => 'titles',
			'Get_Schema_Status'                => 'titles',
			'Update_Sitemap_Settings'          => 'sitemap',
			'Invalidate_Sitemap_Cache'         => 'sitemap',
			'Get_Sitemap_Health'               => 'sitemap',
			'List_Sitemap_Urls'                => 'sitemap',
			'List_Redirections'                => 'redirections',
			'Find_Redirection'                 => 'redirections',
			'Get_Redirection_Stats'            => 'redirections',
			'Create_Redirection'               => 'redirections',
			'Update_Redirection'               => 'redirections',
			'Change_Redirection_Status'        => 'redirections',
			'Delete_Redirections'              => 'redirections',
			'Delete_Trashed_Redirections'      => 'redirections',
			'List_404_Logs'                    => '404-monitor',
			'Delete_404_Logs'                  => '404-monitor',
			'Get_Role_Capabilities'            => 'role_manager',
			'Reset_Role_Capabilities'          => 'role_manager',
			'Get_Analytics_Summary'            => 'analytics',
			'Get_Analytics_Rows'               => 'analytics',
			'Get_Index_Status'                 => 'analytics',
			'Inspect_Url'                      => 'analytics',
			'Update_Seo_Meta'                  => 'onpage_general',
			'Bulk_Update_Meta'                 => 'onpage_general',
			'Get_Primary_Term'                 => 'onpage_general',
			'Update_Primary_Term'              => 'onpage_general',
			'Audit_Content_Seo'                => 'onpage_general',
			'Audit_Faq_Links'                  => 'onpage_general',
			'Update_Post_Schemas'              => 'onpage_snippet',
			'Delete_Post_Schemas'              => 'onpage_snippet',
			'Get_Inbound_Links'                => 'link_builder',
			'Get_Content_Ai_Status'            => 'content_ai',
			'Manage_Content_Ai_Prompts'        => 'content_ai',
			'Manage_Content_Ai_Output'         => 'content_ai',
			'Research_Keyword'                 => 'content_ai',
			// Floor only.
			'Get_Status'                       => '',
			'Run_Maintenance_Tool'             => '',
			'Detect_Seo_Plugins'               => '',
			'List_Modules'                     => '',
			'Update_Seo_Scores'                => '',
			'Get_Ai_Visibility_Brand'          => '',
			'Update_Ai_Visibility_Object'      => '',
			// Dynamic — resolved per panel inside run().
			'Get_Settings_Panel'               => 'general',
		);
	}

	private static function src( string $class ): string {
		return (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/RankMath/' . $class . '.php'
		);
	}

	public function test_suite_has_exactly_fifty_nine_abilities(): void {
		$this->assertCount( 59, self::inventory() );
	}

	/**
	 * The inventory and the filesystem must agree in both directions, so neither an
	 * unlisted file nor a listed-but-missing file can slip through.
	 */
	public function test_inventory_matches_the_filesystem(): void {
		$files = glob( dirname( __DIR__, 3 ) . '/includes/Abilities/RankMath/*.php' );
		$on_disk = array();
		foreach ( (array) $files as $file ) {
			$name = basename( (string) $file, '.php' );
			if ( in_array( $name, array( 'Category_Registrar', 'Base_Rank_Math_Ability', 'Base_Settings_Write_Ability' ), true ) ) {
				continue;
			}
			$on_disk[] = $name;
		}
		sort( $on_disk );
		$expected = array_keys( self::inventory() );
		sort( $expected );
		$this->assertSame( $expected, $on_disk );
	}

	/**
	 * @dataProvider provide_inventory
	 */
	public function test_each_ability_declares_its_slug( string $class, string $slug ): void {
		$this->assertStringContainsString( "return '{$slug}';", self::src( $class ) );
	}

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function provide_inventory(): array {
		$out = array();
		foreach ( self::inventory() as $class => $slug ) {
			$out[ $slug ] = array( $class, $slug );
		}
		return $out;
	}

	public function test_slugs_are_unique(): void {
		$slugs = array_values( self::inventory() );
		$this->assertSame( array_unique( $slugs ), $slugs );
	}

	public function test_slugs_are_verb_first_kebab_case(): void {
		foreach ( self::inventory() as $class => $slug ) {
			$this->assertMatchesRegularExpression( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug, "{$class} slug shape" );
		}
	}

	/**
	 * FR-005 — none of the 61 may collide with Rank Math core's own 13.
	 */
	public function test_no_slug_duplicates_a_rank_math_core_ability(): void {
		$core = array(
			'get-post-seo-meta',
			'analyze-post-content',
			'get-seo-scores',
			'get-post-schema',
			'audit-site-seo',
			'fix-site-seo',
			'get-link-report',
			'get-post-links',
			'get-top-keywords',
			'get-ai-visibility-overview',
			'get-ai-visibility-brand-insights',
			'get-ai-visibility-brand-queries',
			'create-ai-visibility-brand',
		);
		foreach ( self::inventory() as $class => $slug ) {
			$this->assertNotContains( $slug, $core, "{$class} duplicates a Rank Math core ability slug." );
		}
	}

	/**
	 * The capability map is the security contract; drift between it and the code is a
	 * privilege bug waiting to happen.
	 *
	 * @dataProvider provide_capability_map
	 */
	public function test_capability_matches_the_documented_map( string $class, string $cap ): void {
		$src = self::src( $class );
		$this->assertMatchesRegularExpression(
			"/function rank_math_cap\(\): string \{\s*return '" . preg_quote( $cap, '/' ) . "';/",
			$src,
			"{$class} must declare rank_math_cap() as '{$cap}'."
		);
	}

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function provide_capability_map(): array {
		$out = array();
		foreach ( self::capability_map() as $class => $cap ) {
			$out[ $class ] = array( $class, $cap );
		}
		return $out;
	}

	public function test_capability_map_covers_every_ability(): void {
		$this->assertSame(
			array(),
			array_diff( array_keys( self::inventory() ), array_keys( self::capability_map() ) ),
			'Every ability needs an entry in the capability map.'
		);
	}

	/**
	 * @dataProvider provide_destructive
	 */
	public function test_destructive_abilities_are_annotated_and_gated( string $class ): void {
		$src = self::src( $class );
		$this->assertMatchesRegularExpression( "/'destructive'\s*=>\s*true/", $src );
		$this->assertMatchesRegularExpression( '/function requires_confirmation\(\)\s*:\s*bool\s*\{\s*return true;/s', $src );
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public static function provide_destructive(): array {
		$out = array();
		foreach ( self::destructive() as $class ) {
			$out[ $class ] = array( $class );
		}
		return $out;
	}

	public function test_exactly_twelve_abilities_are_destructive(): void {
		$this->assertCount( 12, self::destructive() );
	}

	/**
	 * Nothing outside the declared set may be destructive.
	 */
	public function test_no_undeclared_ability_is_destructive(): void {
		$declared = self::destructive();
		foreach ( array_keys( self::inventory() ) as $class ) {
			if ( in_array( $class, $declared, true ) ) {
				continue;
			}
			$this->assertDoesNotMatchRegularExpression(
				"/'destructive'\s*=>\s*true/",
				self::src( $class ),
				"{$class} is destructive but not in the declared destructive set."
			);
		}
	}

	/**
	 * SECURITY — every ability in the suite requires manage_options, matching the
	 * convention across the rest of includes/Abilities/. No ability may lower it.
	 *
	 * An earlier revision used an edit_posts floor for the ten post-scoped abilities.
	 * That opened a real hole: Rank Math grants rank_math_onpage_snippet to author and
	 * editor by default, so an Author passed the callback for update-post-schemas,
	 * which reaches a writer that addresses meta rows by id and ignores the object id.
	 */
	public function test_no_ability_lowers_the_capability_floor(): void {
		foreach ( array_keys( self::inventory() ) as $class ) {
			$this->assertStringNotContainsString(
				'function permission_floor()',
				self::src( $class ),
				"{$class} must not override permission_floor(); the whole suite is manage_options."
			);
		}
	}

	/**
	 * The base declares it final so it cannot be overridden at all.
	 */
	public function test_base_floor_is_manage_options_and_final(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/RankMath/Base_Rank_Math_Ability.php'
		);
		$this->assertStringContainsString( 'final protected function permission_floor(): string', $src );
		$this->assertStringContainsString( "return 'manage_options';", $src );
		$this->assertStringNotContainsString( "return 'edit_posts';", $src );
	}

	/**
	 * SECURITY regression guard. Rank Math's schema handler carries no capability
	 * logic — its REST route's permission_callback does — so calling it directly must
	 * re-assert per-object rights for post, term AND user, and must verify that a
	 * schema-<meta_id> row actually belongs to the named object, because
	 * update_metadata_by_mid() addresses rows by meta id alone.
	 */
	public function test_schema_writes_authorise_every_object_type(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/RankMath/Post_Meta_Repository.php'
		);
		$this->assertStringContainsString( 'assert_object_editable( $object_type, $object_id )', $src );
		$this->assertStringContainsString( 'assert_meta_row_belongs_to(', $src );
		$this->assertStringContainsString( "current_user_can( 'edit_user', \$object_id )", $src );
		$this->assertStringContainsString( 'get_metadata_by_mid( $object_type, $meta_id )', $src );
		// The old shape gated only the post branch.
		$this->assertStringNotContainsString( "if ( 'post' === \$object_type ) {\n\t\t\t\$editable", $src );
	}
}
