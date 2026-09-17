<?php
/**
 * Feature 126 — the backup suite: one set of abilities over two backup plugins.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.52
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Backup_Suite extends WP_UnitTestCase {

	/**
	 * Every ability in the suite, and its slug.
	 *
	 * @return array<string, string> class => slug
	 */
	private static function inventory(): array {
		return array(
			'Get_Status'           => 'backups/get-status',
			'List_Backups'         => 'backups/list-backups',
			'Get_Backup'           => 'backups/get-backup',
			'Check_Exposure'       => 'backups/check-exposure',
			'Get_Backup_Progress'  => 'backups/get-backup-progress',
			'Start_Backup'         => 'backups/start-backup',
			'Set_Backup_Label'     => 'backups/set-backup-label',
			'Delete_Backup'        => 'backups/delete-backup',
			'Restore_Backup'       => 'backups/restore-backup',
		);
	}

	/**
	 * Each class declares the slug it is supposed to.
	 */
	public function test_every_ability_declares_its_slug(): void {
		foreach ( self::inventory() as $class => $slug ) {
			$src = self::read( self::dir() . $class . '.php' );

			$this->assertNotSame( '', $src, "{$class}.php is missing." );
			$this->assertStringContainsString( "return '{$slug}';", $src, "{$class} must declare {$slug}." );
			$this->assertStringContainsString( 'extends Base_Backup_Ability', $src, "{$class} must go through the base." );
		}
	}

	/**
	 * The suite registers whether or not a backup plugin is installed.
	 *
	 * This is the one suite that must NOT be gated on its subject being present. A site with no
	 * backup plugin is exactly the site whose operator most needs to be told so, and gating the
	 * abilities away would make "is this site backed up?" unanswerable rather than answered no.
	 */
	public function test_registration_is_not_gated_on_a_backup_plugin(): void {
		$src = self::code_only( self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' ) );

		$this->assertMatchesRegularExpression(
			'/\}\s*\n\s*\$this->register_backup_abilities\(\);/',
			$src,
			'register_backup_abilities() must sit outside any availability guard.'
		);
		$this->assertDoesNotMatchRegularExpression(
			'/if \([^)]*Guard::is_available\(\) \) \{\s*\$this->register_backup_abilities\(\);/',
			$src,
			'The backup suite must not be wrapped in an availability guard.'
		);
	}

	/**
	 * The floor is administrator and a subclass cannot lower it.
	 */
	public function test_permission_floor_is_final(): void {
		$src = self::code_only( self::read( self::dir() . 'Base_Backup_Ability.php' ) );

		$this->assertMatchesRegularExpression(
			'/final protected function permission_floor\(\): string \{\s*return \'manage_options\';/',
			$src,
			'A backup ability must require an administrator, and no subclass may widen that.'
		);
	}

	/**
	 * Destroying something is gated; reading is not.
	 */
	public function test_only_the_destructive_abilities_demand_confirmation(): void {
		$gated   = array( 'Delete_Backup', 'Restore_Backup' );
		$ungated = array( 'Get_Status', 'List_Backups', 'Get_Backup', 'Check_Exposure', 'Get_Backup_Progress', 'Start_Backup', 'Set_Backup_Label' );

		foreach ( $gated as $class ) {
			$this->assertMatchesRegularExpression(
				'/protected function requires_confirmation\(\): bool \{\s*return true;/',
				self::code_only( self::read( self::dir() . $class . '.php' ) ),
				"{$class} destroys something and must require confirmation."
			);
		}

		foreach ( $ungated as $class ) {
			$this->assertStringNotContainsString(
				'requires_confirmation',
				self::code_only( self::read( self::dir() . $class . '.php' ) ),
				"{$class} does not destroy anything, so demanding confirmation would only cost a round trip."
			);
		}
	}

	/**
	 * Restore says, in the confirmation itself, that it cannot be undone.
	 *
	 * An operator who has not understood that a restore discards everything written since the
	 * backup has not really confirmed anything. The wording is part of the guard, not decoration.
	 */
	public function test_restore_states_its_irreversibility_in_the_gate(): void {
		$src = self::read( self::dir() . 'Restore_Backup.php' );

		$gate = self::method_body( $src, 'confirmation_message' );

		$this->assertMatchesRegularExpression( '/IRREVERSIBLE/', $gate, 'The confirmation must say so in plain words.' );
		$this->assertMatchesRegularExpression( '/permanently lost/', $gate );
		$this->assertMatchesRegularExpression( '/no undo/', $gate );
		$this->assertMatchesRegularExpression( '/start-backup/', $gate, 'It must point at taking a backup of the current state first.' );

		$this->assertMatchesRegularExpression(
			'/THIS IS IRREVERSIBLE/',
			self::method_body( $src, 'ability_description' ),
			'The description must carry the warning too, because that is what a tool list shows.'
		);
	}

	/**
	 * What a restore is about to destroy is recorded BEFORE it runs.
	 *
	 * Afterwards there is nothing left to read it from, so an answer to "what did I just lose"
	 * has to be captured first or not at all.
	 */
	public function test_restore_records_the_state_it_replaces_before_running(): void {
		$body = self::code_only( self::method_body( self::read( self::dir() . 'Restore_Backup.php' ), 'run' ) );

		$before = strpos( $body, 'self::current_state(' );
		$call   = strpos( $body, '::restore_backup(' );

		$this->assertNotFalse( $before, 'The pre-restore state must be captured.' );
		$this->assertNotFalse( $call, 'The restore must actually be invoked.' );
		$this->assertLessThan(
			$call,
			$before,
			'The state has to be read before the restore replaces it; afterwards the old values are gone.'
		);
	}

	/**
	 * The suite never speaks to a backup plugin directly.
	 *
	 * Every ability goes through the provider layer, which is what lets one set of abilities serve
	 * a site running either plugin. An ability naming a plugin's own class or function would work
	 * on one site and break on the next.
	 */
	public function test_no_ability_reaches_past_the_provider_layer(): void {
		foreach ( array_keys( self::inventory() ) as $class ) {
			$src = self::code_only( self::read( self::dir() . $class . '.php' ) );

			foreach ( array( 'UpdraftPlus_Backup_History', 'Ai1wm_Backups', 'UpdraftPlus_Options', 'AI1WM_BACKUPS_PATH' ) as $internal ) {
				$this->assertStringNotContainsString(
					$internal,
					$src,
					"{$class} must reach the backup plugin through the provider layer, not name {$internal}."
				);
			}
		}
	}

	/**
	 * Both providers satisfy the whole contract.
	 *
	 * A provider missing a method is a fatal error at call time rather than a clean refusal, and
	 * the one that would be hit first is the one nobody tests: the second plugin.
	 */
	public function test_both_providers_implement_the_full_contract(): void {
		$contract = self::read( self::util() . 'Backup_Provider.php' );

		preg_match_all( '/public static function (\w+)\(/', $contract, $matches );
		$required = $matches[1];

		$this->assertGreaterThan( 8, count( $required ), 'The contract should declare the full provider surface.' );

		foreach ( array( 'UpdraftPlus_Provider', 'All_In_One_Provider' ) as $provider ) {
			$src = self::read( self::util() . $provider . '.php' );

			$this->assertStringContainsString( 'implements Backup_Provider', $src, "{$provider} must declare the contract." );

			foreach ( $required as $method ) {
				$this->assertMatchesRegularExpression(
					'/public static function ' . preg_quote( $method, '/' ) . '\(/',
					$src,
					"{$provider} must implement {$method}()."
				);
			}
		}
	}

	/**
	 * A capability is reported by what is possible, not what happens to be loaded.
	 *
	 * Measured: UpdraftPlus only loads its admin class on admin screens, so a class_exists() check
	 * reported "cannot start a backup" on every REST request -- which is every request this suite
	 * serves. The provider loads the file on demand, so the file's existence is the real condition.
	 */
	public function test_capabilities_are_not_decided_by_what_is_already_loaded(): void {
		$body = self::code_only( self::method_body( self::read( self::util() . 'UpdraftPlus_Provider.php' ), 'supports' ) );

		$this->assertStringContainsString( 'is_readable(', $body, 'Capability must follow from the file being present.' );
		$this->assertStringNotContainsString(
			"class_exists( 'UpdraftPlus_Admin' )",
			$body,
			'A class that is loaded on demand must not be used as the capability test.'
		);
	}

	/**
	 * A refusal carries the provider's own reason when it has one.
	 *
	 * All-in-One's restore is part of a paid extension. "Cannot do this" leaves the caller stuck;
	 * naming the extension and the manual import route does not.
	 */
	public function test_a_refusal_prefers_the_specific_reason(): void {
		$body = self::code_only( self::method_body( self::read( self::util() . 'Provider_Registry.php' ), 'resolve_for' ) );

		$this->assertMatchesRegularExpression(
			'/\$reason = \$provider::unsupported_reason\( \$capability \);/',
			$body,
			'The registry must ask the provider why before falling back to generic wording.'
		);
		$this->assertMatchesRegularExpression(
			"/if \\( '' !== \\\$reason \\) \\{\\s*return new WP_Error\\( 'unsupported_by_provider', \\\$reason \\);/",
			$body,
			'A specific reason must win over the generic one.'
		);

		$reasons = self::read( self::util() . 'All_In_One_Provider.php' );
		$this->assertMatchesRegularExpression( '/Unlimited Extension/', $reasons, 'The paid-extension refusal must name it.' );
		$this->assertMatchesRegularExpression( '/Import/', $reasons, 'And must name the route that still works.' );
	}

	/**
	 * With two backup plugins active, the suite asks which rather than guessing.
	 *
	 * Picking one silently would be a guess about which plugin the operator trusts with their
	 * recovery, on the one operation where being wrong matters most.
	 */
	public function test_an_ambiguous_provider_is_asked_for_not_guessed(): void {
		$body = self::code_only( self::method_body( self::read( self::util() . 'Provider_Registry.php' ), 'resolve' ) );

		$this->assertMatchesRegularExpression(
			'/if \( 1 === count\( \$active \) \) \{\s*return \$active\[0\];/',
			$body,
			'One active provider needs no disambiguation.'
		);
		$this->assertStringContainsString(
			"'provider_required'",
			$body,
			'More than one active provider must be refused with a request to name one.'
		);
	}

	/**
	 * Remote storage is named and never described.
	 *
	 * A destination's settings hold its access tokens in the same structure as its name, so
	 * returning the settings would hand those out — the position already taken on payment gateways.
	 */
	public function test_remote_storage_credentials_are_never_returned(): void {
		$src  = self::read( self::util() . 'UpdraftPlus_Provider.php' );
		$body = self::code_only( self::method_body( $src, 'remote_storage' ) );

		$this->assertStringContainsString( "self::option( 'updraft_service'", $body, 'Only the service list is read.' );

		foreach ( array( 'updraft_s3', 'updraft_dropbox', 'updraft_googledrive', 'secret', 'token' ) as $forbidden ) {
			$this->assertStringNotContainsString(
				$forbidden,
				$body,
				"No storage credential may be read here ({$forbidden})."
			);
		}
	}

	/**
	 * The exposure check asks the server rather than trusting the guard file.
	 *
	 * Both plugins drop a .htaccess in their backup directory. On nginx that file is never read, so
	 * the protection is present, looks correct, and does nothing. Measured on the development site:
	 * nginx, .htaccess in place, both directories answering HTTP 200.
	 */
	public function test_exposure_is_measured_not_assumed(): void {
		$src = self::code_only( self::read( self::util() . 'Exposure_Scanner.php' ) );

		$this->assertStringContainsString( 'wp_remote_get(', $src, 'The real URL must be requested.' );
		$this->assertMatchesRegularExpression(
			'/\$reachable = 200 === \$status;/',
			$src,
			'The verdict must follow from the status code the server actually returned.'
		);
		$this->assertMatchesRegularExpression(
			'/false !== strpos\( \$server, \'apache\' \) \|\| false !== strpos\( \$server, \'litespeed\' \)/',
			$src,
			'Only Apache and LiteSpeed read .htaccess; everything else must be reported as not honouring it.'
		);
	}

	/**
	 * An unknown server is treated as not protecting anything.
	 *
	 * Assuming protection that may not exist is the dangerous direction to be wrong in.
	 */
	public function test_an_unrecognised_server_is_assumed_not_to_honour_htaccess(): void {
		$body = self::code_only( self::method_body( self::read( self::util() . 'Exposure_Scanner.php' ), 'htaccess_honoured' ) );

		$this->assertStringContainsString( 'return false !== strpos', $body );
		$this->assertStringNotContainsString(
			'return true;',
			$body,
			'The default must be that .htaccess is NOT honoured, so an unknown server errs towards reporting exposure.'
		);
	}

	/**
	 * Starting a backup does not block the request.
	 *
	 * Measured: UpdraftPlus's own request_backupnow() ends by echoing its JSON and detaching the
	 * client, then runs the entire backup in the remainder of the process. Called from an ability
	 * it replaced the response wholesale — the ability returned {"nonce":...,"m":...} and nothing
	 * else — and would have blocked until the backup finished or the server gave up.
	 */
	public function test_starting_a_backup_is_queued_rather_than_run_inline(): void {
		$src  = self::read( self::util() . 'UpdraftPlus_Provider.php' );
		$body = self::code_only( self::method_body( $src, 'start_backup' ) );

		$this->assertStringContainsString( 'wp_schedule_single_event(', $body, 'The job must be queued.' );
		$this->assertStringNotContainsString(
			'request_backupnow',
			$body,
			'request_backupnow() hijacks the response and runs the backup inline; it must not be called.'
		);
		$this->assertStringContainsString( 'spawn_cron()', $body, 'Cron should be nudged so the job starts now.' );
	}

	/**
	 * A restore refuses up front when the filesystem would stop it half-way.
	 *
	 * ensure_wp_filesystem_set_up_for_restore() calls exit outright when WordPress cannot write
	 * directly and needs FTP credentials. Discovering that at the point of no return, on the most
	 * destructive operation here, is the worst available failure.
	 */
	public function test_restore_checks_the_filesystem_before_touching_anything(): void {
		$body = self::code_only( self::method_body( self::read( self::util() . 'UpdraftPlus_Provider.php' ), 'restore_backup' ) );

		$check   = strpos( $body, "'direct' !== get_filesystem_method()" );
		$restore = strpos( $body, 'perform_restore(' );

		$this->assertNotFalse( $check, 'The filesystem method must be checked.' );
		$this->assertNotFalse( $restore, 'The restore must be performed.' );
		$this->assertLessThan( $restore, $check, 'The check has to come first, before anything is modified.' );
		$this->assertStringContainsString( 'ob_start()', $body, "The plugin's admin markup must not reach the caller." );
	}

	/**
	 * A partly-applied restore is never reported as success.
	 *
	 * A site with half a backup on it is a worse place to be than one with none, and an operator
	 * told "done" will not go looking.
	 */
	public function test_an_incomplete_restore_is_reported_as_such(): void {
		$body = self::code_only( self::method_body( self::read( self::util() . 'UpdraftPlus_Provider.php' ), 'restore_backup' ) );

		$this->assertMatchesRegularExpression(
			'/if \( true !== \$outcome \) \{/',
			$body,
			'Anything other than an explicit success must be treated as a failure.'
		);
		$this->assertStringContainsString( "'restore_incomplete'", $body );
		$this->assertMatchesRegularExpression( '/mixed state/', $body, 'And must say the site may be part-restored.' );
	}

	/**
	 * A set's components come from the plugin's own entity list, not from guesswork.
	 *
	 * Measured: a real database backup reported has_database false, file_count 0 and zero bytes
	 * while a 753,564-byte archive sat on disk. The set mixes components with metadata under one
	 * roof — `db` sits beside `checksums`, `nonce` and `service` — so "anything not a scalar" was
	 * wrong in both directions: it listed checksums as a component and missed db entirely, because
	 * db's value is a plain filename string.
	 */
	public function test_backup_components_are_read_from_the_entity_list(): void {
		$src  = self::read( self::util() . 'UpdraftPlus_Provider.php' );
		$body = self::code_only( self::method_body( $src, 'entity_keys' ) );

		$this->assertStringContainsString(
			'get_backupable_file_entities(',
			$body,
			"The plugin's own entity list is the authority on what a component is."
		);
		$this->assertStringContainsString( "'db'", $body, 'The database is an entity too, and is not in that list.' );

		$shape = self::code_only( self::method_body( $src, 'shape' ) );

		$this->assertMatchesRegularExpression(
			'/foreach \( self::entity_keys\(\) as \$entity \)/',
			$shape,
			'shape() must walk the entity list rather than every key in the set.'
		);
		$this->assertMatchesRegularExpression(
			'/if \( isset\( \$set\[ \$entity \. \'-size\' \] \) && is_numeric\( \$set\[ \$entity \. \'-size\' \] \) \) \{/',
			$shape,
			'The recorded size must be PREFERRED over filesize(): it is authoritative and survives the archive moving to remote storage, where filesize() reports nothing.'
		);
	}

	/**
	 * A set whose archives are not all here says so.
	 *
	 * A restore needs every part, and a set that looks complete in a listing but is missing a file
	 * fails at the worst moment.
	 */
	public function test_a_set_missing_archives_locally_is_flagged(): void {
		$shape = self::code_only( self::method_body( self::read( self::util() . 'UpdraftPlus_Provider.php' ), 'shape' ) );

		$this->assertMatchesRegularExpression(
			'/if \( \$present < count\( \$files \) \) \{/',
			$shape,
			'A set with fewer archives on disk than it lists must be flagged.'
		);
	}

	/**
	 * Deleting the last backup says that nothing is left.
	 *
	 * "Deleted, 1 file removed" and "deleted, and this site can now no longer be restored" are
	 * different pieces of news.
	 */
	public function test_deleting_the_last_backup_says_so(): void {
		$body = self::code_only( self::method_body( self::read( self::dir() . 'Delete_Backup.php' ), 'run' ) );

		$this->assertMatchesRegularExpression(
			'/\$remaining = \$provider::status\(\);/',
			$body,
			'The remaining count must be read back rather than assumed.'
		);
		$this->assertMatchesRegularExpression( '/if \( 0 === \$left \) \{/', $body );
		$this->assertMatchesRegularExpression( '/nothing to restore/', $body );
	}

	/**
	 * A provider that reports a capability it cannot deliver is caught, not trusted.
	 */
	public function test_a_lying_capability_is_refused_rather_than_fataled(): void {
		$body = self::code_only( self::method_body( self::read( self::dir() . 'Set_Backup_Label.php' ), 'run' ) );

		$this->assertMatchesRegularExpression(
			'/if \( ! is_a\( \$provider, All_In_One_Provider::class, true \) \) \{/',
			$body,
			'Only the provider that actually implements set_label() may be called for it.'
		);
		$this->assertStringContainsString( "'unsupported_by_provider'", $body );
	}

	/**
	 * A third-party provider that is malformed is dropped, not fatal.
	 *
	 * This runs on every request; one bad entry from a filter must not take the suite down.
	 */
	public function test_a_malformed_third_party_provider_is_dropped(): void {
		$body = self::code_only( self::method_body( self::read( self::util() . 'Provider_Registry.php' ), 'all' ) );

		$this->assertMatchesRegularExpression(
			'/is_subclass_of\( \$provider, Backup_Provider::class \)/',
			$body,
			'A declared provider must actually implement the contract before it is used.'
		);
		$this->assertMatchesRegularExpression(
			'/return empty\( \$valid \) \? \$providers : /',
			$body,
			'A filter that returns nothing usable must fall back to the built-in providers.'
		);
	}

	/**
	 * The utilities are static-only (DEC-UTILITY-STATIC-ONLY).
	 */
	public function test_utilities_are_final_and_static_only(): void {
		foreach ( array( 'Backup_Guard', 'Provider_Registry', 'Exposure_Scanner', 'UpdraftPlus_Provider', 'All_In_One_Provider' ) as $class ) {
			$src = self::read( self::util() . $class . '.php' );

			$this->assertStringContainsString( "final class {$class}", $src, "{$class} must be final." );
			$this->assertStringContainsString( 'private function __construct() {}', $src, "{$class} must not be instantiable." );
		}
	}

	/**
	 * The permission filter can tighten access and never widen it.
	 */
	public function test_the_permission_filter_cannot_widen_access(): void {
		$body = self::code_only( self::method_body( self::read( self::util() . 'Backup_Guard.php' ), 'can' ) );

		$cap    = strpos( $body, 'current_user_can( $floor )' );
		$filter = strpos( $body, 'apply_filters(' );

		$this->assertNotFalse( $cap );
		$this->assertNotFalse( $filter );
		$this->assertLessThan(
			$filter,
			$cap,
			'The capability check must pass before the filter is consulted, so a filter can only tighten.'
		);
	}

	// ---------------------------------------------------------------- helpers

	private static function dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Backups/';
	}

	private static function util(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Backups/';
	}

	private static function read( string $path ): string {
		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/**
	 * Source with comments and docblocks stripped.
	 *
	 * Needed wherever a test asserts that a token is ABSENT: the docblocks here deliberately name
	 * the things that must not be called, so a raw-text search would pass on the prose.
	 */
	private static function code_only( string $src ): string {
		if ( '' === $src ) {
			return '';
		}

		$out = '';

		foreach ( token_get_all( '' === trim( $src ) || 0 === strpos( $src, '<?php' ) ? $src : '<?php ' . $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			$out .= is_array( $token ) ? $token[1] : $token;
		}

		return $out;
	}

	private static function method_body( string $src, string $method ): string {
		$start = strpos( $src, 'function ' . $method . '(' );

		if ( false === $start ) {
			return '';
		}

		$brace = strpos( $src, '{', $start );

		if ( false === $brace ) {
			return '';
		}

		$depth = 0;
		$len   = strlen( $src );

		for ( $i = $brace; $i < $len; $i++ ) {
			if ( '{' === $src[ $i ] ) {
				++$depth;
			} elseif ( '}' === $src[ $i ] ) {
				--$depth;

				if ( 0 === $depth ) {
					return substr( $src, $brace, $i - $brace + 1 );
				}
			}
		}

		return substr( $src, $brace );
	}
}
