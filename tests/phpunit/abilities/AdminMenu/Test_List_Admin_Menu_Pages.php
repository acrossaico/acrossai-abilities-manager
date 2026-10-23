<?php
/**
 * Tests: listing admin menu pages from outside an admin request.
 *
 * `$menu` and `$submenu` only exist in an admin request. Every REST call — and therefore every MCP
 * call — has neither, and until 0.0.43 this ability tried to fix that by `require_once`-ing
 * `wp-admin/includes/menu.php`. That is a procedural script, not a library, and outside the admin
 * it threw from inside core:
 *
 *     array_keys(): Argument #1 ($array) must be of type array, null given    (menu.php:368)
 *
 * Verified before and after against a real REST request: the old path returned HTTP 500 with
 * `ability_callback_exception`, the new one returns 200. See issue #268.
 *
 * Two things these tests pin that are easy to lose:
 *
 * 1. **It must not throw with the globals absent** — the reported bug, and the only state a REST
 *    caller is ever in.
 * 2. **It must say when the answer is partial.** Reassembly cannot recover WordPress's built-in
 *    entries, which are declared in a core file that cannot safely be loaded here. A short list
 *    that looks complete is worse than an error, so `complete` and the notice are behaviour, not
 *    decoration.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.43
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\Abilities\AdminMenu;

use AcrossAI_Abilities_Manager\Includes\Abilities\AdminMenu\List_Admin_Menu_Pages;
use PHPUnit\Framework\TestCase;

/**
 * Behaviour in and out of an admin request.
 */
class Test_List_Admin_Menu_Pages extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['acrossai_test_capabilities'] = array( 'manage_options' );
		$GLOBALS['acrossai_test_current_user'] = 1;
		$GLOBALS['acrossai_test_did_action']   = array();

		// The REST state: WordPress never built the menu, so these are null, not empty.
		foreach ( array( 'menu', 'submenu', 'admin_page_hooks', '_registered_pages', '_parent_pages', '_wp_real_parent_file' ) as $global ) {
			unset( $GLOBALS[ $global ] );
		}
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( array( 'menu', 'submenu', 'admin_page_hooks', '_registered_pages', '_parent_pages', '_wp_real_parent_file' ) as $global ) {
			unset( $GLOBALS[ $global ] );
		}

		unset( $GLOBALS['acrossai_test_did_action'] );

		parent::tearDown();
	}

	/**
	 * Run the ability.
	 *
	 * @return array<string, mixed>
	 */
	private function run_ability(): array {
		return ( new List_Admin_Menu_Pages() )->execute( array() );
	}

	/**
	 * Say the menu has already been built, as an admin request would.
	 *
	 * @return void
	 */
	private function given_an_admin_request(): void {
		$GLOBALS['acrossai_test_did_action'] = array( 'admin_menu' => 1 );
	}

	/**
	 * Seed a top-level entry with one submenu, as `add_menu_page()` would have.
	 *
	 * @param  string $slug       Menu slug.
	 * @param  string $capability Capability required.
	 * @return void
	 */
	private function given_menu_entry( string $slug, string $capability = 'manage_options' ): void {
		$GLOBALS['menu']             = $GLOBALS['menu'] ?? array();
		$GLOBALS['submenu']          = $GLOBALS['submenu'] ?? array();
		$GLOBALS['menu'][]           = array( ucfirst( $slug ), $capability, $slug, '', '' );
		$GLOBALS['submenu'][ $slug ] = array(
			array( ucfirst( $slug ) . ' Home', $capability, $slug ),
		);
	}

	/* ---------------------------------------------------------------------
	 * The reported bug.
	 * ------------------------------------------------------------------ */

	/**
	 * With no admin bootstrap and null globals, it answers instead of throwing.
	 *
	 * This is issue #268 exactly. The old implementation raised a `TypeError` from inside WordPress
	 * core here, which the Abilities layer surfaced as `ability_callback_exception` on 100% of REST
	 * calls.
	 *
	 * @return void
	 */
	public function test_it_does_not_throw_outside_an_admin_request(): void {
		$this->assertArrayNotHasKey( 'menu', $GLOBALS, 'Precondition: the REST state has no $menu.' );

		$result = $this->run_ability();

		$this->assertTrue( $result['success'] );
		$this->assertIsArray( $result['items'] );
	}

	/**
	 * And it says the answer is partial.
	 *
	 * Reassembly fires `admin_menu` so plugins register, but WordPress's own entries come from
	 * `wp-admin/menu.php`, which cannot safely be loaded outside the admin — it ends by requiring
	 * the script that `wp_die( 403 )`s on a request with no `$pagenow`.
	 *
	 * @return void
	 */
	public function test_a_rebuilt_menu_is_reported_as_incomplete(): void {
		$result = $this->run_ability();

		$this->assertFalse(
			$result['complete'],
			'A list missing every core page must not present itself as the whole menu.'
		);
		$this->assertNotEmpty( $result['notice'] );
	}

	/* ---------------------------------------------------------------------
	 * …and an admin request still gets the real thing.
	 * ------------------------------------------------------------------ */

	/**
	 * In an admin request the menu is already built, so it is read as-is and reported complete.
	 *
	 * @return void
	 */
	public function test_an_admin_request_is_reported_complete(): void {
		$this->given_an_admin_request();
		$this->given_menu_entry( 'tools' );

		$result = $this->run_ability();

		$this->assertTrue( $result['success'] );
		$this->assertTrue( $result['complete'] );
		$this->assertArrayNotHasKey( 'notice', $result, 'Nothing to warn about when the menu is real.' );
		$this->assertCount( 1, $result['items'] );
	}

	/**
	 * A menu that already exists is never discarded.
	 *
	 * The guard only initialises globals that are missing. Overwriting them would throw away the
	 * real menu in the one case where it was correct.
	 *
	 * @return void
	 */
	public function test_an_existing_menu_is_not_clobbered(): void {
		$this->given_menu_entry( 'tools' );

		$result = $this->run_ability();

		$this->assertCount(
			1,
			$result['items'],
			'The entry present before the call must survive it.'
		);
		$this->assertSame( 'tools', $result['items'][0]['slug'] );
	}

	/* ---------------------------------------------------------------------
	 * Shape and authorisation.
	 * ------------------------------------------------------------------ */

	/**
	 * Rows carry what a caller needs to act, including submenus.
	 *
	 * @return void
	 */
	public function test_rows_carry_slug_capability_url_and_submenus(): void {
		$this->given_an_admin_request();
		$this->given_menu_entry( 'tools' );

		$row = $this->run_ability()['items'][0];

		$this->assertSame( 'tools', $row['slug'] );
		$this->assertSame( 'manage_options', $row['capability'] );
		$this->assertStringContainsString( 'page=tools', (string) $row['url'] );
		$this->assertCount( 1, $row['submenu'] );
		$this->assertSame( 'tools', $row['submenu'][0]['slug'] );
	}

	/**
	 * Entries the caller could not reach in the admin are not listed.
	 *
	 * Visibility here has to track the admin itself: naming a page the user cannot open sends an
	 * assistant at a dead end, and discloses that the page exists.
	 *
	 * @return void
	 */
	public function test_entries_the_user_cannot_access_are_omitted(): void {
		$this->given_an_admin_request();
		$this->given_menu_entry( 'tools', 'manage_options' );
		$this->given_menu_entry( 'secrets', 'activate_plugins' );

		$GLOBALS['acrossai_test_capabilities'] = array( 'manage_options' );

		$slugs = array_column( $this->run_ability()['items'], 'slug' );

		$this->assertContains( 'tools', $slugs );
		$this->assertNotContains( 'secrets', $slugs, 'activate_plugins was not granted.' );
	}

	/**
	 * An entry with no slug is skipped rather than emitted half-formed.
	 *
	 * Core uses slug-less rows as menu separators.
	 *
	 * @return void
	 */
	public function test_separators_are_skipped(): void {
		$this->given_an_admin_request();
		$GLOBALS['menu']   = array( array( '', 'read', '', '', 'wp-menu-separator' ) );
		$GLOBALS['submenu'] = array();

		$this->assertSame( array(), $this->run_ability()['items'] );
	}

	/* ---------------------------------------------------------------------
	 * Callable at all over REST.
	 * ------------------------------------------------------------------ */

	/**
	 * The input schema carries a top-level default, so a zero-argument call works.
	 *
	 * The second thing that made this unreachable. Core applies only the top-level default and
	 * rejects null once a schema exists, and this ability is `readonly` — so core's run endpoint
	 * requires GET, reads `input` from the query string where every value is a string, and
	 * `?input={}` arrives as the two-character string "{}". Without the default there is no way for
	 * any caller to satisfy it; measured as HTTP 400 `ability_invalid_input` before the fix.
	 *
	 * Asserted from the registered arguments rather than the source.
	 *
	 * @return void
	 */
	public function test_it_is_callable_with_no_arguments(): void {
		$spec = ( new \ReflectionClass( List_Admin_Menu_Pages::class ) )->getMethod( 'ability' );
		$spec->setAccessible( true );

		$args = $spec->invoke( new List_Admin_Menu_Pages() )['args'];

		$this->assertArrayHasKey(
			'default',
			$args['input_schema'],
			'Without a top-level default, core rejects every zero-argument call.'
		);
		$this->assertSame( array(), $args['input_schema']['default'] );
	}

	/**
	 * The output schema names every key the response can carry.
	 *
	 * It is `additionalProperties: false`, so a key the response carries but the schema does not
	 * name fails the entire call rather than being dropped — which has already cost one debugging
	 * session elsewhere in this plugin.
	 *
	 * @return void
	 */
	public function test_the_output_schema_names_every_key_returned(): void {
		$spec = ( new \ReflectionClass( List_Admin_Menu_Pages::class ) )->getMethod( 'ability' );
		$spec->setAccessible( true );

		$declared = array_keys(
			$spec->invoke( new List_Admin_Menu_Pages() )['args']['output_schema']['properties']
		);

		foreach ( array_keys( $this->run_ability() ) as $key ) {
			$this->assertContains( $key, $declared, $key . ' is returned but not declared.' );
		}
	}
}
