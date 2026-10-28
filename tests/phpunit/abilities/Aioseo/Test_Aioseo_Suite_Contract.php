<?php
/**
 * Feature 132 — the whole-suite contract for All in One SEO.
 *
 * Verifies the twelve abilities as a SET: the slug list, the namespace rule, the sub-groups, the
 * confirm/destructive pairing, and the architecture boundary. A per-ability test cannot catch a
 * missing ability, a duplicated slug, or a slug that strayed into AIOSEO's own namespace.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.44
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities\Aioseo;

use PHPUnit\Framework\TestCase;

class Test_Aioseo_Suite_Contract extends TestCase {

	/**
	 * Every ability's class => slug. The feature's inventory: adding one without updating this
	 * fails, which is the point.
	 *
	 * @return array<string, string>
	 */
	private static function inventory(): array {
		return array(
			'Get_Settings_Panel'    => 'get-settings-panel',
			'Update_Settings_Panel' => 'update-settings-panel',
			'Get_Object_Settings'   => 'get-object-settings',
			'Update_Object_Settings' => 'update-object-settings',
			'Get_Sitemap_Health'    => 'get-sitemap-health',
			'Regenerate_Sitemap'    => 'regenerate-sitemap',
			'Get_Status'            => 'get-status',
			'List_Backups'          => 'list-backups',
			'Create_Backup'         => 'create-backup',
			'Manage_Backup'         => 'manage-backup',
			'Dismiss_Notification'  => 'dismiss-notification',
			'Bulk_Update_Post_Seo'  => 'bulk-update-post-seo',
		);
	}

	/**
	 * Abilities that must declare destructive:true AND confirm-gate.
	 *
	 * Only one. Restoring a backup replaces every AIOSEO setting and deleting one removes the only
	 * copy of that state; nothing else here destroys anything a read cannot recover.
	 *
	 * @return string[]
	 */
	private static function destructive(): array {
		return array( 'Manage_Backup' );
	}

	/**
	 * Abilities that confirm-gate WITHOUT being destructive.
	 *
	 * The batch post write. It destroys nothing — every field it sets can be set back — but it
	 * reaches an arbitrary number of posts with no per-post prompt, which is worth a gate.
	 *
	 * @return string[]
	 */
	private static function confirmed_but_not_destructive(): array {
		return array( 'Bulk_Update_Post_Seo' );
	}

	private static function dir(): string {
		return dirname( __DIR__, 4 ) . '/includes/Abilities/Aioseo/';
	}

	private static function src( string $class ): string {
		$path = self::dir() . $class . '.php';

		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/**
	 * Source with comments and docblocks stripped, so an assertion about CODE is not satisfied by
	 * prose. Several of these files discuss AIOSEO symbols in their docblocks deliberately.
	 *
	 * @param  string $src Source.
	 * @return string
	 */
	private static function code_only( string $src ): string {
		return (string) preg_replace( array( '#/\*.*?\*/#s', '#//[^\n]*#' ), '', $src );
	}

	public function test_suite_has_exactly_twelve_abilities(): void {
		$this->assertCount( 12, self::inventory() );
	}

	/**
	 * The inventory and the filesystem must agree in both directions, so neither an unlisted file
	 * nor a listed-but-missing file can slip through.
	 */
	public function test_inventory_matches_the_filesystem(): void {
		$on_disk = array();

		foreach ( (array) glob( self::dir() . '*.php' ) as $file ) {
			$name = basename( (string) $file, '.php' );

			if ( in_array( $name, array( 'Base_Aioseo_Ability', 'Category_Registrar' ), true ) ) {
				continue;
			}

			$on_disk[] = $name;
		}

		sort( $on_disk );
		$listed = array_keys( self::inventory() );
		sort( $listed );

		$this->assertSame( $listed, $on_disk );
	}

	public function test_every_ability_declares_its_listed_slug(): void {
		foreach ( self::inventory() as $class => $slug ) {
			$this->assertMatchesRegularExpression(
				"/protected function slug\(\): string \{\s*return '" . preg_quote( $slug, '/' ) . "';/",
				self::src( $class ),
				"{$class} must declare the slug {$slug}."
			);
		}
	}

	public function test_slugs_are_unique(): void {
		$slugs = array_values( self::inventory() );

		$this->assertSame( $slugs, array_unique( $slugs ) );
	}

	/**
	 * The namespace rule, and the reason this suite exists in the shape it does.
	 *
	 * AIOSEO owns `aioseo-posts/`, `aioseo-settings/`, `aioseo-robots/`, `aioseo-audit/` and
	 * `aioseo-notifications/`. Ours live under the bare `aioseo/`, assembled once in the base class.
	 * A slug that began with one of AIOSEO's area names would shadow one of theirs — the failure
	 * 0.0.44 spent two renames undoing on Rank Math.
	 */
	public function test_no_slug_strays_into_aioseos_own_namespace(): void {
		$theirs = array( 'posts', 'settings', 'robots', 'audit', 'notifications' );

		foreach ( self::inventory() as $class => $slug ) {
			$this->assertStringNotContainsString( '/', $slug, "{$class}: the base class supplies the aioseo/ prefix; the slug must be the suffix only." );

			foreach ( $theirs as $area ) {
				$this->assertStringStartsNotWith(
					$area . '-',
					$slug,
					"{$class}: \"{$slug}\" reads as AIOSEO's own {$area} namespace."
				);
			}
		}
	}

	/**
	 * The prefix is assembled in exactly one place, so no subclass can register outside it.
	 */
	public function test_the_base_class_owns_the_namespace(): void {
		$base = self::code_only( self::src( 'Base_Aioseo_Ability' ) );

		$this->assertStringContainsString( "'aioseo/' . \$this->slug()", $base );
		$this->assertStringContainsString( "protected const CATEGORY = 'acrossai-all-in-one-seo';", $base );
		$this->assertStringContainsString( "protected const TAB_GROUP = 'all-in-one-seo';", $base );
	}

	/**
	 * The tab group must equal the integration row's, or this suite and AIOSEO's own thirteen land in
	 * different groups and the dispatcher serves only one of them.
	 */
	public function test_the_tab_group_matches_the_integration_row(): void {
		$row = (string) file_get_contents( dirname( __DIR__, 4 ) . '/includes/Abilities/Integrations/All_In_One_Seo.php' );

		$this->assertStringContainsString( "public const TAB_GROUP = 'all-in-one-seo';", $row );
		$this->assertStringContainsString( "protected const TAB_GROUP = 'all-in-one-seo';", self::code_only( self::src( 'Base_Aioseo_Ability' ) ) );
	}

	public function test_destructive_abilities_confirm_gate(): void {
		foreach ( self::destructive() as $class ) {
			$src = self::src( $class );

			$this->assertMatchesRegularExpression(
				"/'destructive' *=> *true/",
				$src,
				"{$class} must declare destructive:true."
			);
			$this->assertMatchesRegularExpression(
				'/protected function requires_confirmation\(\): bool \{\s*return true;/',
				$src,
				"{$class} declares destructive:true, so it must confirm-gate."
			);
		}
	}

	/**
	 * Nothing else declares destructive:true. A suite that marks reads destructive trains a caller to
	 * ignore the flag.
	 */
	public function test_no_other_ability_claims_to_be_destructive(): void {
		foreach ( self::inventory() as $class => $slug ) {
			if ( in_array( $class, self::destructive(), true ) ) {
				continue;
			}

			$this->assertDoesNotMatchRegularExpression(
				"/'destructive' *=> *true/",
				self::src( $class ),
				"{$class} is not in the destructive list but declares destructive:true."
			);
		}
	}

	public function test_only_the_named_abilities_confirm_gate(): void {
		$expected = array_merge( self::destructive(), self::confirmed_but_not_destructive() );

		foreach ( self::inventory() as $class => $slug ) {
			$gated = 1 === preg_match( '/protected function requires_confirmation\(\): bool \{\s*return true;/', self::src( $class ) );

			$this->assertSame(
				in_array( $class, $expected, true ),
				$gated,
				"{$class}: confirm gating does not match the documented set."
			);
		}
	}

	/**
	 * Read-only abilities must not claim to write, and writers must not claim to be read-only.
	 */
	public function test_readonly_and_write_annotations_are_consistent(): void {
		$readonly = array( 'Get_Settings_Panel', 'Get_Object_Settings', 'Get_Sitemap_Health', 'Get_Status', 'List_Backups' );

		foreach ( self::inventory() as $class => $slug ) {
			$src      = self::src( $class );
			$declared = 1 === preg_match( "/'readonly' *=> *true/", $src );

			$this->assertSame(
				in_array( $class, $readonly, true ),
				$declared,
				"{$class}: readonly annotation does not match whether it writes."
			);
		}
	}

	/**
	 * THE architecture boundary. No ability may name an AIOSEO symbol — all host access lives in
	 * `Utilities/Aioseo`, so an AIOSEO rename breaks one file rather than twelve. Checked against
	 * code with docblocks stripped, because several of these files discuss AIOSEO's internals in prose
	 * on purpose.
	 */
	public function test_no_ability_names_an_aioseo_symbol(): void {
		foreach ( array_keys( self::inventory() ) as $class ) {
			$code = self::code_only( self::src( $class ) );

			foreach ( array( 'aioseo()', 'AIOSEO_VERSION', 'AIOSEO\\Plugin', 'aioseo_options' ) as $symbol ) {
				$this->assertStringNotContainsString(
					$symbol,
					$code,
					"{$class} names the AIOSEO symbol \"{$symbol}\". Host access belongs in Utilities/Aioseo."
				);
			}
		}
	}

	/**
	 * Nor may the base class or the category registrar.
	 */
	public function test_the_base_and_registrar_name_no_aioseo_symbol(): void {
		foreach ( array( 'Base_Aioseo_Ability', 'Category_Registrar' ) as $class ) {
			$code = self::code_only( self::src( $class ) );

			foreach ( array( 'aioseo()', 'AIOSEO_VERSION', 'AIOSEO\\Plugin' ) as $symbol ) {
				$this->assertStringNotContainsString( $symbol, $code, "{$class} names {$symbol}." );
			}
		}
	}

	/**
	 * The floor is final. Every ability here is site-wide configuration, and a subclass must not be
	 * able to lower it for convenience.
	 */
	public function test_the_permission_floor_is_final_and_administrator(): void {
		$this->assertMatchesRegularExpression(
			"/final protected function permission_floor\(\): string \{\s*return 'manage_options';/",
			self::code_only( self::src( 'Base_Aioseo_Ability' ) )
		);

		foreach ( array_keys( self::inventory() ) as $class ) {
			$this->assertStringNotContainsString(
				'function permission_floor',
				self::src( $class ),
				"{$class} must not redeclare permission_floor."
			);
		}
	}

	/**
	 * No ability may override the assembler or the guard order.
	 */
	public function test_no_ability_overrides_ability_or_execute(): void {
		foreach ( array_keys( self::inventory() ) as $class ) {
			$src = self::src( $class );

			$this->assertStringNotContainsString( 'function ability(', $src, "{$class} must not override ability()." );
			$this->assertStringNotContainsString( 'function execute(', $src, "{$class} must not override execute()." );
		}
	}

	/**
	 * Every ability sits in one of the suite's four sub-groups, and each has a label.
	 */
	public function test_every_ability_declares_a_known_sub_group(): void {
		$base   = self::src( 'Base_Aioseo_Ability' );
		$known  = array( 'aioseo-settings', 'aioseo-sitemap', 'aioseo-content', 'aioseo-status' );

		foreach ( $known as $slug ) {
			$this->assertStringContainsString( "'{$slug}'", $base, "The base class must label the {$slug} sub-group." );
		}

		foreach ( array_keys( self::inventory() ) as $class ) {
			$this->assertSame(
				1,
				preg_match( "/protected function sub_group\(\): string \{\s*return '([a-z-]+)';/", self::src( $class ), $m ),
				"{$class} must declare a sub_group."
			);
			$this->assertContains( $m[1], $known, "{$class} declares the unknown sub-group {$m[1]}." );
		}
	}

	/**
	 * The suite must not duplicate AIOSEO's own thirteen. Asserted on the slug list, because a
	 * near-duplicate arrives as a plausibly-named new ability rather than as an obvious clash.
	 */
	public function test_nothing_duplicates_an_aioseo_ability(): void {
		$forbidden = array(
			'seo-data-get',
			'seo-data-update',
			'list-missing-seo',
			'list-truseo-score',
			'get-settings',
			'list-notifications',
			'get-robots-txt',
			'list-robots-rules',
			'add-robots-rule',
			'update-robots-rule',
			'delete-robots-rule',
			'get-homepage-audit',
			'get-site-audit',
		);

		foreach ( self::inventory() as $class => $slug ) {
			$this->assertNotContains(
				$slug,
				$forbidden,
				"{$class}: \"{$slug}\" duplicates something AIOSEO already ships. Point at theirs instead."
			);
		}
	}
}
