<?php
/**
 * Feature 102 — the gate migration must never read request input.
 *
 * Its trigger is `plugins_loaded` on every request path, which is what makes it reachable by an
 * unauthenticated visitor. That is intended: requiring a capability would reinstate the hole it
 * exists to close, since the exposure is via REST and MCP rather than wp-admin. The trade is that
 * every input must come from stored state, because with an unauthenticated trigger any
 * request-derived parameter becomes an unauthenticated write primitive.
 *
 * A plausible future change — "add ?force-remigrate=1 so support can re-run it" — would pass code
 * review and silently create exactly that. This test is the thing that stops it.
 *
 * Deliberately a token scan, not an adjacency regex: per BUG-SOURCE-INSPECTION-ADJACENCY-BRITTLE,
 * source assertions requiring tokens to sit next to each other rot on the first reformat.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.34
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Guards the migration's trust boundary.
 */
class Test_Library_Gate_Migration_No_Request_Input extends TestCase {

	/**
	 * The migration source with comments stripped.
	 *
	 * Comments are removed via token_get_all() rather than regex. Without this the assertions
	 * below trip over the class's own docblocks, which discuss the very things being forbidden —
	 * the docblock explains *why* it does not read AcrossAI_Ability_Library_Config, and a raw
	 * string scan cannot tell that apart from actually doing it.
	 *
	 * @return string
	 */
	private function source(): string {
		$path = dirname( __DIR__, 4 ) . '/includes/Modules/Abilities/AcrossAI_Library_Gate_Migration.php';
		$raw  = file_get_contents( $path );

		$this->assertIsString( $raw, 'The migration source must be readable.' );

		$code = '';

		foreach ( token_get_all( (string) $raw ) as $token ) {
			if ( is_array( $token ) ) {
				if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
					continue;
				}

				$code .= $token[1];

				continue;
			}

			$code .= $token;
		}

		return $code;
	}

	/**
	 * No superglobal access anywhere in the class.
	 *
	 * @return void
	 */
	public function test_reads_no_superglobals(): void {
		foreach ( array( '$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SERVER', '$_FILES' ) as $global ) {
			$this->assertStringNotContainsString(
				$global,
				$this->source(),
				sprintf(
					'%s must not appear in the gate migration: its trigger is unauthenticated, so any '
					. 'request-derived input is an unauthenticated write primitive.',
					$global
				)
			);
		}
	}

	/**
	 * No request-accessor helpers either.
	 *
	 * @return void
	 */
	public function test_uses_no_request_accessors(): void {
		foreach ( array( 'filter_input', 'wp_unslash', 'WP_REST_Request', 'get_query_var' ) as $token ) {
			$this->assertStringNotContainsString(
				$token,
				$this->source(),
				sprintf( '%s implies request input reaching the migration.', $token )
			);
		}
	}

	/**
	 * The done flag is claimed with add_option(), before the work.
	 *
	 * A read-then-write guard would let every concurrent request on a freshly upgraded site start
	 * its own full translation; add_option() is a single INSERT, so exactly one wins.
	 *
	 * @return void
	 */
	public function test_claims_the_flag_atomically(): void {
		$src = $this->source();

		$this->assertStringContainsString( 'add_option( self::DONE_OPTION', $src );
		$this->assertStringNotContainsString( 'update_option( self::DONE_OPTION', $src );
	}

	/**
	 * It does not depend on the class deleted in the same feature.
	 *
	 * On multisite a site may not translate until long after that deletion ships; a dependency on
	 * it would leave late-migrating sites with no path to migrate at all.
	 *
	 * @return void
	 */
	/**
	 * Every save_override() call must pass `source`.
	 *
	 * The column is `NOT NULL DEFAULT 'db'` and save_override() never sets it — the caller owns it
	 * (RF-04, which the REST write path satisfies via AcrossAI_Ability_Source_Detector). Omitting it
	 * does not fail: MySQL supplies 'db', and every row this migration writes is then reported as a
	 * user-created ability. That shipped once and was caught only by reading the table on a real
	 * site, because nothing about it is visible from the migration's own behaviour — the abilities
	 * still get blocked, which is all the other tests check.
	 *
	 * Structural rather than behavioural because the real assertion needs a database; the paired
	 * check is `test_written_overrides_are_not_stamped_as_db_abilities` in the wp-env suite.
	 *
	 * @return void
	 */
	public function test_every_save_override_call_passes_a_source(): void {
		$code = $this->source();

		$calls = preg_match_all( '/save_override\s*\(/', $code );

		$this->assertGreaterThan( 0, $calls, 'The migration is expected to write overrides.' );

		// Each call site's argument list, up to the matching close of the array literal. Crude on
		// purpose: the point is that the string "'source'" appears inside every call, and a
		// regression here is a deletion, not a clever restructuring.
		$offset = 0;

		for ( $i = 0; $i < $calls; $i++ ) {
			$start = strpos( $code, 'save_override', $offset );
			$this->assertIsInt( $start );

			$chunk = substr( $code, (int) $start, 400 );

			$this->assertMatchesRegularExpression(
				"/'source'\s*=>\s*'plugin'/",
				$chunk,
				'save_override() must be called with source => \'plugin\'. The column defaults to '
					. "'db', which mislabels the row as a user-created ability; and every slug this "
					. 'migration writes comes from the definitions registry, which only ever holds '
					. 'abilities this plugin registers.'
			);

			$offset = (int) $start + 13;
		}
	}

	/**
	 * The migration must not reach into the Library module.
	 *
	 * It lives in `Modules\\Abilities` and its source data lives in `Modules\\Library`.
	 * Constitution Module Contract #3 forbids depending on a sibling module directly; #4 makes
	 * filters the sanctioned seam, and the Library module publishes
	 * `acrossai_ability_library_definitions` for exactly this consumer.
	 *
	 * This shipped as a direct `AcrossAI_Ability_Library_Registry::instance()->get_definitions()`
	 * call and was caught by an architecture review, not by any test — the migration behaved
	 * correctly either way, so only the dependency direction was wrong.
	 *
	 * Comments are stripped by source(), so the docblocks that *discuss* the Registry do not
	 * trip this.
	 *
	 * @return void
	 */
	public function test_does_not_reach_into_the_library_module(): void {
		$code = $this->source();

		$this->assertStringNotContainsString(
			'AcrossAI_Ability_Library_Registry',
			$code,
			'Read the definitions through the acrossai_ability_library_definitions filter instead.'
		);

		$this->assertStringNotContainsString(
			'Modules\\Library',
			$code,
			'Modules\\Abilities must not import from Modules\\Library (Module Contract #3).'
		);

		$this->assertStringContainsString(
			"apply_filters( 'acrossai_ability_library_definitions'",
			$code,
			'The published filter is the sanctioned seam for these rows.'
		);
	}

	public function test_does_not_depend_on_the_retiring_config_class(): void {
		$this->assertStringNotContainsString( 'AcrossAI_Ability_Library_Config', $this->source() );
	}
}
