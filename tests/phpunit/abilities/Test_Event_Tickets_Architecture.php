<?php
/**
 * Feature 110 — architectural invariants across The Events Calendar suite.
 *
 * Most of these exist because a live probe found the failure first. Where that is so the docblock
 * says which measurement produced the rule, because the alternative reading — that they are
 * speculative — invites someone to delete them.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.41
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Event_Tickets_Architecture extends WP_UnitTestCase {

	private const NON_ABILITY_FILES = array( 'Category_Registrar.php', 'Base_Event_Tickets_Ability.php' );

	private static function abilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/EventTickets/';
	}

	private static function utilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/EventTickets/';
	}

	/**
	 * @return string[]
	 */
	private static function ability_files(): array {
		$files = glob( self::abilities_dir() . '*.php' );

		return array_values(
			array_filter(
				is_array( $files ) ? $files : array(),
				static fn( string $f ): bool => ! in_array( basename( $f ), self::NON_ABILITY_FILES, true )
			)
		);
	}

	/**
	 * @return string[]
	 */
	private static function suite_files(): array {
		$a = glob( self::abilities_dir() . '*.php' );
		$u = glob( self::utilities_dir() . '*.php' );

		return array_merge( is_array( $a ) ? $a : array(), is_array( $u ) ? $u : array() );
	}

	/**
	 * Comments and string literals stripped.
	 *
	 * Both matter: descriptions legitimately name the meta keys they refuse to write, and our own
	 * namespace ends in `\EventTickets`.
	 */
	private static function code_no_strings( string $src ): string {
		$out = '';

		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) ) {
				if ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE ), true ) ) {
					continue;
				}

				$out .= $token[1];
				continue;
			}

			$out .= $token;
		}

		return str_replace(
			array( 'Abilities\\Utilities\\EventTickets', 'Abilities\\EventTickets', 'Utilities\\EventTickets' ),
			'',
			$out
		);
	}

	private static function code_only( string $src ): string {
		$out = '';

		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			$out .= is_array( $token ) ? $token[1] : $token;
		}

		return $out;
	}












	public function test_every_ability_is_final_and_extends_the_base(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );

			$this->assertMatchesRegularExpression( '/\bfinal class\b/', $src, basename( $file ) );
			$this->assertStringContainsString( 'extends Base_Event_Tickets_Ability', $src, basename( $file ) );
		}
	}

	public function test_the_capability_floor_is_final_and_not_overridden(): void {
		$base = (string) file_get_contents( self::abilities_dir() . 'Base_Event_Tickets_Ability.php' );

		$this->assertMatchesRegularExpression(
			"/final protected function permission_floor\(\): string \{\s*return 'manage_options';/",
			$base
		);

		foreach ( self::ability_files() as $file ) {
			$this->assertStringNotContainsString( 'function permission_floor', (string) file_get_contents( $file ), basename( $file ) );
		}
	}

	public function test_the_tab_group_matches_the_integration(): void {
		$base        = (string) file_get_contents( self::abilities_dir() . 'Base_Event_Tickets_Ability.php' );
		$integration = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/Event_Tickets.php' );

		$this->assertStringContainsString( "TAB_GROUP = 'event-tickets'", $base );
		$this->assertStringContainsString( "TAB_GROUP = 'event-tickets'", $integration );
	}

	public function test_the_integration_claims_no_prefixes(): void {
		$this->assertMatchesRegularExpression(
			'/function ability_prefixes\(\): array \{\s*return array\(\);/',
			(string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/Event_Tickets.php' )
		);
	}

	public function test_utilities_are_final_and_static_only(): void {
		foreach ( glob( self::utilities_dir() . '*.php' ) ?: array() as $file ) {
			$src = (string) file_get_contents( $file );

			$this->assertMatchesRegularExpression( '/\bfinal class\b/', $src, basename( $file ) );
			$this->assertStringContainsString( 'private function __construct()', $src, basename( $file ) );
		}
	}

	public function test_the_suite_has_sixteen_abilities(): void {
		$this->assertCount( 16, self::ability_files() );
	}

	/**
	 * No Event Tickets symbol outside Utilities/EventTickets/.
	 */
	public function test_no_ticketing_symbol_in_ability_classes(): void {
		foreach ( self::ability_files() as $file ) {
			$code = self::code_no_strings( (string) file_get_contents( $file ) );

			foreach ( array( 'Tribe__Tickets__', 'TEC\\Tickets\\', 'tribe_tickets(', 'tec_tc_orders(', 'tribe_attendees(' ) as $symbol ) {
				$this->assertStringNotContainsString(
					$symbol,
					$code,
					basename( $file ) . " reaches for {$symbol} directly. Event Tickets' API belongs behind the repositories."
				);
			}
		}
	}

	/**
	 * Capacity is never written as raw meta.
	 *
	 * `update_capacity()` subtracts pending and sold from the new stock. Writing
	 * `_tribe_ticket_capacity` or `_stock` directly skips that reconciliation and double-counts
	 * every sale already made, which on a paid event oversells or undersells real inventory.
	 * Everything goes through ticket_add(), which calls update_capacity() for us.
	 */
	public function test_capacity_is_never_written_directly(): void {
		foreach ( self::suite_files() as $file ) {
			$code = self::code_no_strings( (string) file_get_contents( $file ) );

			foreach ( array( 'update_post_meta(', 'add_post_meta(', 'delete_post_meta(' ) as $writer ) {
				$this->assertStringNotContainsString(
					$writer,
					$code,
					basename( $file ) . " calls {$writer}. Ticket state must go through ticket_add(), which reconciles stock against what is already sold."
				);
			}
		}

		$this->assertStringContainsString(
			'ticket_add(',
			(string) file_get_contents( self::utilities_dir() . 'Ticket_Repository.php' ),
			'Writes must go through the plugin\'s own front door.'
		);
	}

	/**
	 * A partial update carries the existing capacity forward.
	 *
	 * Measured: a price-only update zeroed a ticket that had 20 seats, because ticket_add() rebuilds
	 * the ticket from whatever it is handed and an absent capacity block means "no capacity", not
	 * "unchanged".
	 */
	public function test_partial_updates_preserve_capacity(): void {
		foreach ( array( 'Update_Ticket.php', 'Set_Ticket_Capacity.php' ) as $name ) {
			$src = (string) file_get_contents( self::abilities_dir() . $name );

			$this->assertStringContainsString(
				'array_merge(',
				$src,
				"{$name} must merge the caller's capacity over the current values, not replace them."
			);
			$this->assertStringContainsString( "\$current['capacity']", $src, "{$name} must read the current capacity." );
		}
	}

	/**
	 * The security code is never returned, under any flag.
	 *
	 * It is the check-in credential printed on the ticket. Disclosing it lets someone check in as
	 * another attendee, so it is excluded structurally rather than gated behind the PII flag.
	 */
	public function test_the_security_code_is_never_returned(): void {
		foreach ( self::suite_files() as $file ) {
			// code_only, NOT code_no_strings: the thing being forbidden IS a string literal, so
			// stripping literals would make this assertion unfalsifiable. Comments are still
			// stripped, because the docblocks legitimately explain why the key is not read.
			$code = self::code_only( (string) file_get_contents( $file ) );

			foreach ( array( '_security_code', '_tribe_rsvp_security_code', '_tec_tickets_commerce_security_code' ) as $key ) {
				$this->assertStringNotContainsString(
					$key,
					$code,
					basename( $file ) . " names {$key}. The check-in security code must never be read."
				);
			}
		}
	}

	/**
	 * Personal data is opt-in and counted.
	 */
	public function test_personal_data_is_opt_in_and_reported(): void {
		$repo = (string) file_get_contents( self::utilities_dir() . 'Attendee_Repository.php' );

		$this->assertStringContainsString( '$with_pii', $repo );
		$this->assertStringContainsString( 'disclosed', $repo, 'The number of disclosed records must be reported back.' );

		foreach ( array( 'List_Attendees.php', 'List_Orders.php', 'Get_Order.php' ) as $name ) {
			$this->assertStringContainsString(
				'include_personal_data',
				(string) file_get_contents( self::abilities_dir() . $name ),
				"{$name} must gate personal data behind an explicit flag."
			);
		}
	}

	/**
	 * Attendee and order listings are always paginated.
	 *
	 * An unpaginated attendee read hands every name and email on an event to a model in one call.
	 */
	public function test_listings_are_capped(): void {
		$repo = (string) file_get_contents( self::utilities_dir() . 'Attendee_Repository.php' );

		$this->assertStringContainsString( 'MAX_PER_PAGE', $repo );
		$this->assertMatchesRegularExpression( '/min\(\s*self::MAX_PER_PAGE/', $repo, 'The cap must be applied, not merely declared.' );
	}

	/**
	 * Gateway credentials and payloads are never read.
	 */
	public function test_gateway_data_is_never_read(): void {
		foreach ( self::suite_files() as $file ) {
			// code_only for the same reason as the security-code test: these are string literals.
			$code = self::code_only( (string) file_get_contents( $file ) );

			/*
			 * Exact key names, not loose words. With string literals visible, a term like "webhook"
			 * also matches the ability description that explains why gateway data is not read —
			 * flagging the documentation as the violation.
			 */
			foreach (
				array(
					'_tec_tc_order_gateway_payload',
					'_tec_tc_order_gateway_customer_id',
					'_tec_tc_order_gateway_order_object',
					'tec_tickets_commerce_stripe_signup_data',
					'tec_tickets_commerce_square_signup_data',
					'tickets-commerce-stripe-webhooks-signing-key',
					'tickets-commerce-square-code-verifier',
				) as $key
			) {
				$this->assertStringNotContainsString(
					$key,
					$code,
					basename( $file ) . " names {$key}. Payment credentials and raw gateway payloads must never be returned."
				);
			}
		}
	}

	/**
	 * Check-in is verified by reading the state back.
	 *
	 * Event Tickets exposes a filter over check-in and a site can veto it. Measured: a check-in that
	 * silently did nothing would otherwise have reported success.
	 */
	public function test_check_in_is_verified_by_read_back(): void {
		$repo = self::code_only( (string) file_get_contents( self::utilities_dir() . 'Attendee_Repository.php' ) );

		// Assert the comparison itself, not merely that the identifiers appear. An earlier version
		// checked only for the two names and survived the branch being replaced with if ( false ).
		$this->assertMatchesRegularExpression(
			'/\$stored\s*=\s*self::is_checked_in\(\s*\$attendee_id\s*\);\s*if\s*\(\s*\$stored\s*!==\s*\$in\s*\)/',
			$repo,
			'set_check_in() must compare the stored state against what was asked for.'
		);
		$this->assertStringContainsString( 'check_in_rejected', $repo );
	}

	/**
	 * The suite does not require The Events Calendar.
	 *
	 * Event Tickets has no dependency on it and tickets attach to pages by default, so gating on the
	 * calendar would hide working functionality on a site that only sells tickets on pages.
	 */
	public function test_the_suite_does_not_require_the_events_calendar(): void {
		foreach ( self::suite_files() as $file ) {
			// code_only: the dependency would most likely appear as class_exists( 'Tribe__Events__Main' ),
			// and that class name is a string literal.
			$code = self::code_only( (string) file_get_contents( $file ) );

			$this->assertStringNotContainsString(
				'Tribe__Events__Main',
				$code,
				basename( $file ) . ' references The Events Calendar. Event Tickets works without it.'
			);
		}

		$this->assertStringContainsString(
			'ticketable_post_types',
			(string) file_get_contents( self::utilities_dir() . 'Ticket_Repository.php' ),
			'The ticketable types must be read from the site, not assumed to be events.'
		);
	}
}
