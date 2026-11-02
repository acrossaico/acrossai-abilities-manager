<?php
/**
 * Issue #296 — plugins/get-plugin-lifecycle-context resolves identifiers like its siblings.
 *
 * The ability used to do an exact `isset( $all_plugins[ $input ] )`, the only raw lookup among the
 * ten plugin abilities, and refused anything else with "not installed" — a statement about the
 * site that is false whenever the plugin is present under a different identifier.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.46
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Plugin_Helpers;

require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Plugin_Helpers.php';

/**
 * Behavioural coverage of the resolver, plus the ability's own contract.
 */
final class Test_Plugin_Lifecycle_Context_Resolution extends TestCase {

	/**
	 * Ability source, for the contract assertions.
	 *
	 * @var string
	 */
	private string $src = '';

	/**
	 * Ability source with comments stripped.
	 *
	 * Negative assertions have to run against code, not prose. The fix's own comments quote the
	 * old `isset( $all_plugins[ $identifier ] )` and the old "not installed" wording to explain
	 * what changed, so asserting their absence against the raw file matches the explanation and
	 * fails. php_strip_whitespace() leaves executable code only.
	 *
	 * @var string
	 */
	private string $code = '';

	protected function setUp(): void {
		parent::setUp();

		$path      = dirname( __DIR__, 3 ) . '/includes/Abilities/Plugins/Get_Plugin_Lifecycle_Context.php';
		$this->src = (string) file_get_contents( $path );
		$this->code = (string) php_strip_whitespace( $path );

		$GLOBALS['acrossai_test_installed_plugins'] = array(
			'acrossai-abilities-manager/acrossai-abilities-manager.php' => array(
				'Name'    => 'AcrossAI Abilities Manager',
				'Version' => '0.0.46',
				'Author'  => 'raftaar1191',
			),
			'akismet/akismet.php' => array(
				'Name'    => 'Akismet Anti-spam: Spam Protection',
				'Version' => '5.7.2',
				'Author'  => 'Automattic',
			),
		);
	}

	protected function tearDown(): void {
		unset( $GLOBALS['acrossai_test_installed_plugins'] );
		parent::tearDown();
	}

	/* ------------------------------------------------------------------ */
	/* The identifiers that used to be refused                            */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array<string,array{0:string,1:float}>
	 */
	public static function identifier_provider(): array {
		return array(
			'plugin file (always worked)' => array( 'acrossai-abilities-manager/acrossai-abilities-manager.php', 10.0 ),
			'display name'                => array( 'AcrossAI Abilities Manager', 9.5 ),
			'directory slug'              => array( 'acrossai-abilities-manager', 9.0 ),
		);
	}

	/**
	 * @dataProvider identifier_provider
	 * @param string $identifier Caller input.
	 * @param float  $certainty  Expected certainty.
	 */
	public function test_identifier_resolves_to_the_plugin_file( string $identifier, float $certainty ): void {
		$resolved = Plugin_Helpers::resolve_plugin( $identifier );

		$this->assertSame(
			'acrossai-abilities-manager/acrossai-abilities-manager.php',
			$resolved['plugin_file'],
			$identifier . ' must resolve; before #296 only the plugin file did.'
		);
		$this->assertSame( $certainty, $resolved['certainty'] );
		$this->assertGreaterThanOrEqual(
			8.0,
			$resolved['certainty'],
			'Must clear the 8.0 threshold, or the ability returns candidates instead of an envelope.'
		);
	}

	public function test_partial_match_resolves_but_below_the_threshold(): void {
		$resolved = Plugin_Helpers::resolve_plugin( 'abilities manager' );

		$this->assertSame( 'acrossai-abilities-manager/acrossai-abilities-manager.php', $resolved['plugin_file'] );
		$this->assertLessThan(
			8.0,
			$resolved['certainty'],
			'A substring match is a guess, so the ability must offer candidates rather than assume.'
		);
		$this->assertNotEmpty( $resolved['candidates'] );
	}

	public function test_genuinely_absent_plugin_does_not_resolve(): void {
		$resolved = Plugin_Helpers::resolve_plugin( 'no-such-plugin-anywhere' );

		$this->assertNull( $resolved['plugin_file'] );
		$this->assertSame( 0.0, $resolved['certainty'] );
	}

	/* ------------------------------------------------------------------ */
	/* The ability's contract                                             */
	/* ------------------------------------------------------------------ */

	public function test_ability_routes_through_the_shared_resolver(): void {
		$this->assertStringContainsString( 'Plugin_Helpers::resolve_plugin(', $this->src );
		$this->assertStringNotContainsString(
			'$all_plugins[ $identifier ]',
			$this->code,
			'The raw exact lookup on caller input is the defect in #296.'
		);
		$this->assertStringContainsString(
			'$all_plugins[ $basename ]',
			$this->code,
			'The surviving lookup must use the RESOLVED plugin file, not caller input.'
		);
	}

	public function test_ability_no_longer_claims_a_resolved_plugin_is_not_installed(): void {
		$this->assertStringNotContainsString(
			'not installed.',
			$this->code,
			'"not installed" asserts something false when the plugin is present under another name.'
		);
		$this->assertStringContainsString( 'No plugin found matching', $this->src );
		$this->assertStringContainsString( 'Could not resolve', $this->src );
	}

	public function test_ability_offers_candidates_below_the_shared_threshold(): void {
		$this->assertStringContainsString( 'Plugin_Helpers::build_candidate_response(', $this->src );
		$this->assertMatchesRegularExpression(
			"/certainty'\\]\\s*<\\s*8\\.0/",
			$this->src,
			'Must use the same 8.0 threshold as activate/deactivate/update.'
		);
	}

	public function test_new_response_keys_are_declared_in_the_output_schema(): void {
		// additionalProperties is false on this ability, so an undeclared key is stripped by
		// output validation and the caller silently loses it.
		$this->assertStringContainsString( "'additionalProperties' => false", $this->src );

		foreach ( array( 'matched_plugin', 'certainty', 'actions' ) as $key ) {
			$this->assertMatchesRegularExpression(
				"/'" . $key . "'\\s*=> array\\( 'type'/",
				$this->src,
				$key . ' is returned, so it must be declared in output_schema.'
			);
		}
	}

	public function test_traversal_and_backslashes_are_still_refused(): void {
		$this->assertStringContainsString( "str_contains( \$identifier, '..' )", $this->src );
		$this->assertStringContainsString( 'ltrim( (string) ( $input[\'plugin\'] ?? \'\' ), \'/\' )', $this->src );
	}

	public function test_hardening_pattern_admits_a_space_for_display_names(): void {
		// A display name contains a space; a space cannot express a traversal, and the identifier
		// never reaches the filesystem — it is only compared against get_plugins() keys and names.
		$this->assertStringContainsString( 'A-Za-z0-9_\\-\\/\\. ', $this->src );
	}

	public function test_documented_input_matches_what_is_accepted(): void {
		$this->assertStringContainsString( 'display name, slug, or partial match', $this->src );
	}
}
