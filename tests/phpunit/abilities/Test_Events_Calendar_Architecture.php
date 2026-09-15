<?php
/**
 * Feature 109 — architectural invariants across The Events Calendar suite.
 *
 * Most of these exist because a live probe found the failure first. Where that is so the docblock
 * says which measurement produced the rule, because the alternative reading — that they are
 * speculative — invites someone to delete them.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.40
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Events_Calendar_Architecture extends WP_UnitTestCase {

	private const NON_ABILITY_FILES = array( 'Category_Registrar.php', 'Base_Events_Calendar_Ability.php' );

	private static function abilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/EventsCalendar/';
	}

	private static function utilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/EventsCalendar/';
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
	 * namespace ends in `\EventsCalendar`.
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
			array( 'Abilities\\Utilities\\EventsCalendar', 'Abilities\\EventsCalendar', 'Utilities\\EventsCalendar' ),
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

	public function test_the_suite_has_eighteen_abilities(): void {
		$this->assertCount( 18, self::ability_files() );
	}

	/**
	 * No calendar symbol outside Utilities/EventsCalendar/.
	 */
	public function test_no_calendar_symbol_in_ability_classes(): void {
		foreach ( self::ability_files() as $file ) {
			$code = self::code_no_strings( (string) file_get_contents( $file ) );

			foreach ( array( 'Tribe__', 'tribe_events(', 'tribe_venues(', 'tribe_organizers(', 'tribe_get_event(' ) as $symbol ) {
				$this->assertStringNotContainsString(
					$symbol,
					$code,
					basename( $file ) . " reaches for {$symbol} directly. The calendar's API belongs behind Event_Repository."
				);
			}
		}
	}

	/**
	 * NOTHING in this suite writes event meta directly.
	 *
	 * This is the rule the whole feature exists for. Measured on a live install: after a bare
	 * update_post_meta( '_EventStartDate', '14:00' ) the meta said 14:00, _EventStartDateUTC still
	 * said 14:00 UTC — the OLD local time for that timezone — and both tec_events and
	 * tec_occurrences still said 09:00. Four stores, four answers, no error. Only the ORM keeps
	 * them in step.
	 */
	public function test_the_suite_never_writes_event_meta_directly(): void {
		foreach ( self::suite_files() as $file ) {
			$code = self::code_no_strings( (string) file_get_contents( $file ) );

			foreach ( array( 'update_post_meta(', 'add_post_meta(', 'delete_post_meta(' ) as $writer ) {
				$this->assertStringNotContainsString(
					$writer,
					$code,
					basename( $file ) . " calls {$writer}. Event data must go through the calendar's ORM — a direct meta write desynchronises the custom tables silently."
				);
			}
		}
	}

	/**
	 * The derived meta keys are named only as a denylist, never assigned.
	 */
	public function test_derived_meta_is_declared_and_never_written(): void {
		$repo = (string) file_get_contents( self::utilities_dir() . 'Event_Repository.php' );

		foreach ( array( '_EventStartDateUTC', '_EventEndDateUTC', '_EventDuration', '_EventTimezoneAbbr', '_EventOrigin' ) as $key ) {
			$this->assertStringContainsString(
				"'" . $key . "'",
				$repo,
				"The DERIVED_META denylist must name {$key}."
			);
		}
	}

	/**
	 * The ORM's return value is not treated as a success signal.
	 *
	 * Measured: save() returns a Tribe__Promise for a single-event update — not the documented
	 * [ id => true ] — for a write that had already been applied synchronously. An earlier draft of
	 * this suite reported failure for writes that worked. Only an explicit WP_Error is an error;
	 * correctness comes from reading the value back.
	 */
	public function test_the_orm_return_value_is_not_trusted(): void {
		$repo = self::code_only( (string) file_get_contents( self::utilities_dir() . 'Event_Repository.php' ) );

		$this->assertMatchesRegularExpression(
			'/is_array\(\s*\$result\s*\)\s*&&\s*isset\(\s*\$result\[\s*\$post_id\s*\]\s*\)\s*&&\s*is_wp_error\(/',
			$repo,
			'update() must fail only on an explicit WP_Error, because save() can return a promise for a write that succeeded.'
		);
		$this->assertStringContainsString(
			'function assert_dates_applied',
			$repo,
			'Correctness must be established by reading the dates back.'
		);
	}

	/**
	 * Dates are read back after every write that touches them.
	 *
	 * The ORM discards the entire date block, silently and without error, when the end precedes the
	 * start. Verified live: the update reported success and the event kept its previous time.
	 */
	public function test_date_writes_are_verified_by_read_back(): void {
		foreach ( array( 'Create_Event.php', 'Update_Event.php' ) as $name ) {
			$src = (string) file_get_contents( self::abilities_dir() . $name );

			$this->assertStringContainsString(
				'assert_dates_applied',
				$src,
				"{$name} writes dates and must confirm they were applied."
			);
		}
	}

	/**
	 * Linked IDs are validated before the write, not after.
	 *
	 * The ORM discards a venue or organizer it cannot resolve and still reports success.
	 */
	public function test_linked_ids_are_validated_before_writing(): void {
		foreach ( array( 'Create_Event.php', 'Update_Event.php' ) as $name ) {
			$src = (string) file_get_contents( self::abilities_dir() . $name );

			$this->assertStringContainsString( 'validate_links', $src, "{$name} must validate venue and organizer IDs." );
		}
	}

	/**
	 * Deletion is trash, never permanent.
	 *
	 * Core only auto-trashes literal post and page; a custom post type handed to wp_delete_post()
	 * is removed outright with no way back.
	 */
	public function test_deletion_is_always_trash(): void {
		foreach ( self::suite_files() as $file ) {
			$code = self::code_no_strings( (string) file_get_contents( $file ) );

			$this->assertStringNotContainsString(
				'wp_delete_post(',
				$code,
				basename( $file ) . ' calls wp_delete_post(). Calendar posts must be trashed, not deleted.'
			);
		}

		$this->assertStringContainsString(
			'wp_trash_post(',
			(string) file_get_contents( self::utilities_dir() . 'Event_Repository.php' )
		);
	}

	/**
	 * Trash refuses rather than escalating when trash is disabled.
	 */
	public function test_trash_refuses_when_trash_is_disabled(): void {
		$repo = (string) file_get_contents( self::utilities_dir() . 'Event_Repository.php' );

		$this->assertStringContainsString( 'EMPTY_TRASH_DAYS', $repo );
		$this->assertStringContainsString( 'trash_disabled', $repo );
	}

	/**
	 * Credentials in the shared settings blob are redacted.
	 *
	 * The blob is shared by the calendar, the ticketing plugin and every add-on, and holds map API
	 * keys and social access tokens beside ordinary preferences.
	 */
	public function test_settings_credentials_are_redacted(): void {
		$repo = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Utilities\\EventsCalendar\\Event_Repository';

		if ( ! class_exists( $repo ) ) {
			$this->markTestSkipped( 'Event_Repository not loaded.' );
		}

		foreach ( array( 'google_maps_js_api_key', 'meetup_api_key', 'meetup_security_key', 'eb_security_key', 'fb_token', 'fb_token_scopes' ) as $key ) {
			$this->assertTrue( $repo::is_secret( $key ), "{$key} must be treated as a credential." );
		}

		foreach ( array( 'viewOption', 'dateWithYearFormat', 'ticket-enabled-post-types' ) as $key ) {
			$this->assertFalse( $repo::is_secret( $key ), "{$key} is an ordinary setting and must not be redacted." );
		}
	}

	/**
	 * Recurring events are refused rather than half-handled.
	 *
	 * Recurrence is a Pro feature. Free calendar code displays a series and never edits its
	 * occurrences, so a write here would change the parent and leave the occurrences behind.
	 */
	public function test_recurring_events_are_refused_on_write(): void {
		$this->assertStringContainsString(
			'assert_not_recurring',
			(string) file_get_contents( self::abilities_dir() . 'Update_Event.php' ),
			'Update_Event must refuse a recurring event.'
		);
		$this->assertStringContainsString(
			'recurring_event',
			(string) file_get_contents( self::utilities_dir() . 'Event_Repository.php' )
		);
	}

	public function test_every_ability_is_final_and_extends_the_base(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );

			$this->assertMatchesRegularExpression( '/\bfinal class\b/', $src, basename( $file ) );
			$this->assertStringContainsString( 'extends Base_Events_Calendar_Ability', $src, basename( $file ) );
		}
	}

	public function test_the_capability_floor_is_final_and_not_overridden(): void {
		$base = (string) file_get_contents( self::abilities_dir() . 'Base_Events_Calendar_Ability.php' );

		$this->assertMatchesRegularExpression(
			"/final protected function permission_floor\(\): string \{\s*return 'manage_options';/",
			$base
		);

		foreach ( self::ability_files() as $file ) {
			$this->assertStringNotContainsString( 'function permission_floor', (string) file_get_contents( $file ), basename( $file ) );
		}
	}

	public function test_the_tab_group_matches_the_integration(): void {
		$base        = (string) file_get_contents( self::abilities_dir() . 'Base_Events_Calendar_Ability.php' );
		$integration = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/Events_Calendar.php' );

		$this->assertStringContainsString( "TAB_GROUP = 'events-calendar'", $base );
		$this->assertStringContainsString( "TAB_GROUP = 'events-calendar'", $integration );
	}

	public function test_the_integration_claims_no_prefixes(): void {
		$this->assertMatchesRegularExpression(
			'/function ability_prefixes\(\): array \{\s*return array\(\);/',
			(string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/Events_Calendar.php' )
		);
	}

	public function test_utilities_are_final_and_static_only(): void {
		foreach ( glob( self::utilities_dir() . '*.php' ) ?: array() as $file ) {
			$src = (string) file_get_contents( $file );

			$this->assertMatchesRegularExpression( '/\bfinal class\b/', $src, basename( $file ) );
			$this->assertStringContainsString( 'private function __construct()', $src, basename( $file ) );
		}
	}
}
