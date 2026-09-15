<?php
/**
 * Feature 109 — the whole-suite contract.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.40
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Events_Calendar_Suite_Contract extends WP_UnitTestCase {

	private const DIR = '/includes/Abilities/EventsCalendar/';

	/**
	 * Class => slug.
	 *
	 * @return array<string,string>
	 */
	private static function inventory(): array {
		return array(
			// calendar (1).
			'Get_Calendar_Settings' => 'events/get-calendar-settings',
			// categories (2).
			'List_Event_Categories' => 'events/list-event-categories',
			'Set_Event_Categories' => 'events/set-event-categories',
			// events (5).
			'Create_Event' => 'events/create-event',
			'Get_Event' => 'events/get-event',
			'List_Events' => 'events/list-events',
			'Trash_Event' => 'events/trash-event',
			'Update_Event' => 'events/update-event',
			// organizers (5).
			'Create_Organizer' => 'events/create-organizer',
			'Get_Organizer' => 'events/get-organizer',
			'List_Organizers' => 'events/list-organizers',
			'Trash_Organizer' => 'events/trash-organizer',
			'Update_Organizer' => 'events/update-organizer',
			// venues (5).
			'Create_Venue' => 'events/create-venue',
			'Get_Venue' => 'events/get-venue',
			'List_Venues' => 'events/list-venues',
			'Trash_Venue' => 'events/trash-venue',
			'Update_Venue' => 'events/update-venue',
		);
	}

	/**
	 * @return array<string,int>
	 */
	private static function sub_group_counts(): array {
		return array(
			'calendar' => 1,
			'categories' => 2,
			'events' => 5,
			'organizers' => 5,
			'venues' => 5,
		);
	}

	/**
	 * The abilities that refuse to run without confirm: true.
	 *
	 * All three are the trash operations. Nothing else in the suite is destructive — writes are
	 * field-level and reversible by writing again.
	 *
	 * @return string[]
	 */
	private static function confirm_gated(): array {
		return array(
			'Trash_Event',
			'Trash_Organizer',
			'Trash_Venue',
		);
	}

	/**
	 * Slugs in other suites this one points at.
	 *
	 * Listed so that removing one upstream fails here loudly rather than leaving a dead suggestion.
	 *
	 * @return string[]
	 */
	private static function known_foreign_slugs(): array {
		return array(
			'taxonomies/list-terms',
			'taxonomies/create-term',
		);
	}

	private static function dir(): string {
		return dirname( __DIR__, 3 ) . self::DIR;
	}

	private static function source( string $class ): string {
		$file = self::dir() . $class . '.php';

		return is_readable( $file ) ? (string) file_get_contents( $file ) : '';
	}

	public function test_inventory_matches_the_directory(): void {
		$files = glob( self::dir() . '*.php' );
		$found = array();

		foreach ( is_array( $files ) ? $files : array() as $file ) {
			$base = basename( $file, '.php' );

			if ( in_array( $base, array( 'Category_Registrar', 'Base_Events_Calendar_Ability' ), true ) ) {
				continue;
			}

			$found[] = $base;
		}

		sort( $found );
		$expected = array_keys( self::inventory() );
		sort( $expected );

		$this->assertSame( $expected, $found );
	}

	public function test_every_slug_is_declared_and_unique(): void {
		$slugs = array();

		foreach ( self::inventory() as $class => $slug ) {
			$src = self::source( $class );

			$this->assertNotSame( '', $src, "{$class}.php is missing." );
			$this->assertMatchesRegularExpression(
				"/function slug\\(\\): string \\{\\s*return '" . preg_quote( $slug, '/' ) . "'/",
				$src,
				"{$class} does not declare slug {$slug}."
			);
			$this->assertStringStartsWith( 'events/', $slug, "{$class}" );

			$slugs[] = $slug;
		}

		$this->assertSame( count( $slugs ), count( array_unique( $slugs ) ) );
	}

	public function test_sub_group_counts_hold(): void {
		$counts = array();

		foreach ( array_keys( self::inventory() ) as $class ) {
			preg_match( "/function sub_group\\(\\): string \\{\\s*return '([a-z-]+)'/", self::source( $class ), $m );
			$this->assertNotEmpty( $m, "{$class} declares no sub_group." );
			$counts[ $m[1] ] = ( $counts[ $m[1] ] ?? 0 ) + 1;
		}

		ksort( $counts );
		$expected = self::sub_group_counts();
		ksort( $expected );

		$this->assertSame( $expected, $counts );
		$this->assertSame( count( self::inventory() ), array_sum( $counts ) );
	}

	public function test_confirmation_gates_are_exactly_the_trash_abilities(): void {
		$gated = array();

		foreach ( array_keys( self::inventory() ) as $class ) {
			if ( preg_match( '/function requires_confirmation\\(\\): bool \\{\\s*return true;/', self::source( $class ) ) ) {
				$gated[] = $class;
			}
		}

		sort( $gated );
		$expected = self::confirm_gated();
		sort( $expected );

		$this->assertSame( $expected, $gated );

		foreach ( $gated as $class ) {
			$this->assertStringContainsString( 'Trash_', $class, 'Only the trash abilities should be confirm-gated.' );
		}
	}

	/**
	 * Read-only abilities are annotated read-only, writers are not.
	 */
	public function test_annotations_match_behaviour(): void {
		foreach ( array_keys( self::inventory() ) as $class ) {
			$src      = self::source( $class );
			$readonly = (bool) preg_match( "/'readonly'\\s*=> true/", $src );
			$writes   = (bool) preg_match( '/^(Create|Update|Trash|Set)_/', $class );

			$this->assertSame( ! $writes, $readonly, "{$class} annotation does not match its name." );
		}
	}

	/**
	 * Every suggested slug resolves.
	 */
	public function test_every_suggested_slug_resolves(): void {
		$own     = array_values( self::inventory() );
		$checked = 0;

		foreach ( array_keys( self::inventory() ) as $class ) {
			$src = self::source( $class );

			if ( ! preg_match( '/function suggested_abilities\\(\\): array \\{(.*?)\\n\\t\\}/s', $src, $m ) ) {
				continue;
			}

			preg_match_all( "/'slug'\\s*=> '([a-z0-9-]+\\/[a-z0-9-]+)'/", $m[1], $hits );

			foreach ( $hits[1] as $slug ) {
				++$checked;

				if ( function_exists( 'wp_get_ability' ) && null !== wp_get_ability( $slug ) ) {
					continue;
				}

				$this->assertContains(
					$slug,
					array_merge( $own, self::known_foreign_slugs() ),
					"{$class} suggests \"{$slug}\", which is neither registered nor known to this suite."
				);
			}
		}

		$this->assertGreaterThan( 0, $checked );
	}

	public function test_the_bootstrap_instantiates_every_ability(): void {
		$bootstrap = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		foreach ( array_keys( self::inventory() ) as $class ) {
			$this->assertStringContainsString(
				'new EventsCalendar\\' . $class . '();',
				$bootstrap,
				"{$class} is declared but never instantiated."
			);
		}
	}
}
