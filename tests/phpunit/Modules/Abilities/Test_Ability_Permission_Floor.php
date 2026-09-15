<?php
/**
 * Issue #200 — this plugin owns the lock on every ability.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.46
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Modules\Abilities;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Ability_Override_Processor as Processor;
use PHPUnit\Framework\TestCase;

class Test_Ability_Permission_Floor extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['acrossai_test_capabilities'] = array();
	}

	protected function tearDown(): void {
		$GLOBALS['acrossai_test_capabilities'] = array();
		unset( $GLOBALS['acrossai_test_filter_values']['acrossai_default_ability_capability'] );
		parent::tearDown();
	}

	/**
	 * Give the current user a set of capabilities for one assertion.
	 *
	 * @param string ...$caps Capabilities.
	 */
	private function as_user( string ...$caps ): void {
		$GLOBALS['acrossai_test_capabilities'] = $caps;
	}

	private static function source(): string {
		return (string) file_get_contents(
			dirname( __DIR__, 4 ) . '/includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php'
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

	/**
	 * The floor is manage_options.
	 */
	public function test_the_default_floor_is_administrator(): void {
		$this->assertSame( 'manage_options', Processor::DEFAULT_CAPABILITY );
		$this->assertSame( 'manage_options', Processor::default_capability() );
	}

	/**
	 * With no AC library and no rule, only a user holding the floor passes.
	 *
	 * This is the regression that matters most. Both branches previously returned true, which was
	 * defensible while the ability's own callback was still the real gate. It no longer is — so an
	 * absent library would have silently opened every ability on the site.
	 *
	 * Exercised through floor_allows(), which is the decision both branches now make. The branches
	 * themselves are pinned by test_no_bare_return_true_remains_in_the_access_check() below, because
	 * reaching them needs the access-control manager, which needs BerlinDB, which needs a database
	 * this harness does not have. The whole path is verified live instead.
	 */
	public function test_it_fails_closed_when_no_rule_can_be_resolved(): void {
		$this->as_user( 'read', 'edit_posts', 'upload_files', 'edit_theme_options' );
		$this->assertFalse(
			Processor::floor_allows(),
			'An editor-level user must be denied when nothing grants them the floor.'
		);

		$this->as_user( 'manage_options' );
		$this->assertTrue(
			Processor::floor_allows(),
			'An administrator must pass.'
		);
	}

	/**
	 * A logged-out caller is denied.
	 *
	 * The case that motivated the issue: third-party abilities registered with `__return_true` and
	 * no check at all.
	 */
	public function test_an_unauthenticated_caller_is_denied(): void {
		$this->as_user();

		$this->assertFalse( Processor::floor_allows() );
	}

	/**
	 * A capability that is not the floor does not pass, however many the user has.
	 *
	 * Covers the measured third-party gates: `read`, `edit_posts`, `upload_files`, `list_users`.
	 */
	public function test_lesser_capabilities_do_not_satisfy_the_floor(): void {
		foreach ( array( 'read', 'edit_posts', 'upload_files', 'list_users', 'read_post', 'edit_shop_orders' ) as $cap ) {
			$this->as_user( $cap );

			$this->assertFalse(
				Processor::floor_allows(),
				"Holding {$cap} must not satisfy a manage_options floor."
			);
		}
	}

	/**
	 * The floor can be moved site-wide rather than rule by rule.
	 */
	public function test_the_filter_moves_the_floor(): void {
		// The harness's add_filter is a no-op; filter answers are supplied through this fixture.
		$GLOBALS['acrossai_test_filter_values']['acrossai_default_ability_capability'] = 'edit_posts';

		$this->assertSame( 'edit_posts', Processor::default_capability() );

		$this->as_user( 'edit_posts' );
		$this->assertTrue( Processor::floor_allows(), 'With the floor moved, an editor passes.' );

		unset( $GLOBALS['acrossai_test_filter_values']['acrossai_default_ability_capability'] );

		$this->assertSame( 'manage_options', Processor::default_capability(), 'The floor must return.' );
	}

	/**
	 * An empty filter value cannot be used to remove the floor.
	 *
	 * Returning '' from the filter would otherwise reach current_user_can( '' ), which is not a
	 * capability anyone holds — denying everyone including admins — or, on a different WP build,
	 * could be read as "no capability required". Neither is a floor, so the constant wins.
	 */
	public function test_an_empty_filter_value_falls_back_to_the_constant(): void {
		$GLOBALS['acrossai_test_filter_values']['acrossai_default_ability_capability'] = '';

		$this->assertSame( 'manage_options', Processor::default_capability() );

		unset( $GLOBALS['acrossai_test_filter_values']['acrossai_default_ability_capability'] );
	}

	/**
	 * Routers keep their own callback.
	 *
	 * Their permission callback does structural work — the hidden-ability 403 and a target
	 * pre-check — which a capability test cannot express. Replacing it would collapse the per-server
	 * EXPOSURE layer into the permission layer and make hidden abilities reachable.
	 */
	public function test_routers_are_exempt(): void {
		$code = self::code_only( self::source() );

		$this->assertStringContainsString( "ROUTER_PREFIXES = array( 'toolset/', 'mcp-adapter/' )", $code );
		$this->assertMatchesRegularExpression(
			'/if \( ! self::is_router\( \$slug \) \) \{\s*\$args\[.permission_callback.\] = self::build_permission_callback\( \$slug \);/',
			$code,
			'The replacement must be skipped for routers.'
		);
	}

	/**
	 * Every ability that is not a router gets our callback, whoever registered it.
	 *
	 * build_permission_callback() must not be able to decline. It returned null when no rule
	 * existed, which is precisely how a third-party ability kept its own lock.
	 */
	public function test_the_callback_is_installed_unconditionally(): void {
		$code = self::code_only( self::source() );

		$this->assertStringContainsString(
			'private static function build_permission_callback( string $slug ): callable',
			$code,
			'It must return a callable, never null.'
		);
		$this->assertStringNotContainsString(
			'if ( null !== $callback )',
			$code,
			'A conditional install lets an ability keep its own lock.'
		);
	}

	/**
	 * No fail-open path remains.
	 */
	public function test_no_bare_return_true_remains_in_the_access_check(): void {
		$code  = self::code_only( self::source() );
		$start = strpos( $code, 'function user_has_ability_access' );

		$this->assertNotFalse( $start );

		$body = substr( $code, $start, 700 );

		$this->assertStringNotContainsString(
			'return true;',
			$body,
			'A bare return true in the access check is a fail-open path.'
		);
		$this->assertSame(
			2,
			substr_count( $body, 'self::floor_allows()' ),
			'Both unresolvable branches must fall back to the floor.'
		);
	}
}
