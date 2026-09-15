<?php
/**
 * Feature 110 — the whole-suite contract.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.41
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Event_Tickets_Suite_Contract extends WP_UnitTestCase {

	private const DIR = '/includes/Abilities/EventTickets/';

	/**
	 * Class => slug.
	 *
	 * @return array<string,string>
	 */
	private static function inventory(): array {
		return array(
			// attendees (4).
			'Check_In_Attendee' => 'tickets/check-in-attendee',
			'Get_Attendee_Summary' => 'tickets/get-attendee-summary',
			'List_Attendees' => 'tickets/list-attendees',
			'Undo_Check_In' => 'tickets/undo-check-in',
			// capacity (2).
			'Get_Capacity_Report' => 'tickets/get-capacity-report',
			'Set_Ticket_Capacity' => 'tickets/set-ticket-capacity',
			// orders (3).
			'Get_Order' => 'tickets/get-order',
			'Get_Sales_Summary' => 'tickets/get-sales-summary',
			'List_Orders' => 'tickets/list-orders',
			// setup (2).
			'Get_Ticket_Settings' => 'tickets/get-ticket-settings',
			'List_Ticket_Providers' => 'tickets/list-ticket-providers',
			// tickets (5).
			'Create_Ticket' => 'tickets/create-ticket',
			'Delete_Ticket' => 'tickets/delete-ticket',
			'Get_Ticket' => 'tickets/get-ticket',
			'List_Tickets' => 'tickets/list-tickets',
			'Update_Ticket' => 'tickets/update-ticket',
		);
	}

	/**
	 * @return array<string,int>
	 */
	private static function sub_group_counts(): array {
		return array(
			'attendees' => 4,
			'capacity' => 2,
			'orders' => 3,
			'setup' => 2,
			'tickets' => 5,
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
			'Delete_Ticket',
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
		// This suite's suggestions point only at its own abilities.
		return array();
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

			if ( in_array( $base, array( 'Category_Registrar', 'Base_Event_Tickets_Ability' ), true ) ) {
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
			$this->assertStringStartsWith( 'tickets/', $slug, "{$class}" );

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

	public function test_only_deleting_a_ticket_is_confirm_gated(): void {
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
			$this->assertSame( 'Delete_Ticket', $class, 'Only deleting a ticket is destructive enough to gate — Event Tickets removes tickets permanently.' );
		}
	}

	/**
	 * Read-only abilities are annotated read-only, writers are not.
	 */
	public function test_annotations_match_behaviour(): void {
		foreach ( array_keys( self::inventory() ) as $class ) {
			$src      = self::source( $class );
			$readonly = (bool) preg_match( "/'readonly'\\s*=> true/", $src );
			$writes   = (bool) preg_match( '/^(Create|Update|Delete|Set|Check_In|Undo)_/', $class );

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
				'new EventTickets\\' . $class . '();',
				$bootstrap,
				"{$class} is declared but never instantiated."
			);
		}
	}
}
