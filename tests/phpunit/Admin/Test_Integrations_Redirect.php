<?php
/**
 * Feature 102 — the 301 from the retired Ability Integrations page.
 *
 * Scope: `redirect_target()`, the pure half. `maybe_redirect()` calls `exit` after it, so the
 * decision and the URL construction are what is testable and also what is worth testing — the
 * destination must be built from `admin_url()` every time, with nothing caller-supplied reaching
 * it beyond a `sanitize_key()`-ed tab (FR-024).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.34
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Admin\Partials\Integrations_Redirect;
use AcrossAI_Abilities_Manager\Admin\Partials\Menu;

require_once dirname( __DIR__, 3 ) . '/admin/Partials/Menu.php';
require_once dirname( __DIR__, 3 ) . '/admin/Partials/Integrations_Redirect.php';

/**
 * Covers Integrations_Redirect::redirect_target().
 */
class Test_Integrations_Redirect extends TestCase {

	/**
	 * Clear the request between cases.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$_GET = array();
	}

	/**
	 * Leave no request state behind.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_GET = array();

		parent::tearDown();
	}

	/**
	 * Resolve the target for a given query string.
	 *
	 * @param  array<string, mixed> $query Request arguments.
	 * @return string|null
	 */
	private function target( array $query ): ?string {
		$_GET = $query;

		return Integrations_Redirect::instance()->redirect_target();
	}

	/**
	 * The bare legacy slug goes to the abilities page.
	 *
	 * @return void
	 */
	public function test_legacy_slug_redirects_to_the_abilities_page(): void {
		$this->assertSame(
			admin_url( 'admin.php?page=' . Menu::ABILITIES_SLUG ),
			$this->target( array( 'page' => Menu::INTEGRATIONS_LEGACY_SLUG ) )
		);
	}

	/**
	 * A tab argument survives the redirect.
	 *
	 * The tab values the retired page used are the same ones the toolset strip filters by, so a
	 * documented deep link keeps landing on the toolset the reader was sent to.
	 *
	 * @return void
	 */
	public function test_tab_argument_is_preserved(): void {
		$this->assertSame(
			admin_url( 'admin.php?page=' . Menu::ABILITIES_SLUG . '&tab=cache' ),
			$this->target(
				array(
					'page' => Menu::INTEGRATIONS_LEGACY_SLUG,
					'tab'  => 'cache',
				)
			)
		);
	}

	/**
	 * A tab that is not a real toolset still redirects rather than erroring.
	 *
	 * The strip falls back to All for an unknown value, which is a better outcome than a dead URL —
	 * and this class is not the place to hold a list of valid toolsets, since they are data-driven.
	 *
	 * @return void
	 */
	public function test_unrecognised_tab_still_redirects(): void {
		$this->assertSame(
			admin_url( 'admin.php?page=' . Menu::ABILITIES_SLUG . '&tab=nonexistent' ),
			$this->target(
				array(
					'page' => Menu::INTEGRATIONS_LEGACY_SLUG,
					'tab'  => 'nonexistent',
				)
			)
		);
	}

	/**
	 * A hostile tab cannot escape the admin URL.
	 *
	 * sanitize_key() strips the scheme, slashes and dots, so what is left cannot start a new host
	 * or path — the destination is still `admin_url()` with a key-shaped suffix.
	 *
	 * @return void
	 */
	public function test_tab_cannot_inject_a_foreign_destination(): void {
		$target = $this->target(
			array(
				'page' => Menu::INTEGRATIONS_LEGACY_SLUG,
				'tab'  => 'https://evil.example/?x=',
			)
		);

		$this->assertIsString( $target );
		$this->assertStringStartsWith( admin_url( 'admin.php?page=' . Menu::ABILITIES_SLUG ), $target );
		$this->assertStringNotContainsString( 'evil.example', $target );
		$this->assertStringNotContainsString( '//', substr( (string) $target, 8 ) );
	}

	/**
	 * An empty tab does not append a dangling argument.
	 *
	 * @return void
	 */
	public function test_empty_tab_is_omitted(): void {
		$this->assertSame(
			admin_url( 'admin.php?page=' . Menu::ABILITIES_SLUG ),
			$this->target(
				array(
					'page' => Menu::INTEGRATIONS_LEGACY_SLUG,
					'tab'  => '',
				)
			)
		);
	}

	/**
	 * The redirect must be wired to `admin_page_access_denied`, not only `admin_init`.
	 *
	 * This is the test that was missing. Core denies an unregistered `page` argument inside
	 * `wp-admin/includes/menu.php:384`, reached from the `require` at `wp-admin/admin.php:163`,
	 * which runs BEFORE `do_action( 'admin_init' )` at `admin.php:180`. The first implementation
	 * hooked `admin_init` at priority 1 on the documented — and wrong — assumption that it ran
	 * first, so the redirect never fired and the legacy URL showed "Sorry, you are not allowed to
	 * access this page". Every test in this file passed, because they all exercise
	 * `redirect_target()`, which was correct from the start.
	 *
	 * A structural assertion on the wiring is the only way to catch this without a live request.
	 *
	 * @return void
	 */
	public function test_is_wired_to_the_access_denied_hook(): void {
		$main = file_get_contents( dirname( __DIR__, 3 ) . '/includes/Main.php' );

		$this->assertIsString( $main );

		$this->assertMatchesRegularExpression(
			'/add_action\(\s*.admin_page_access_denied.,\s*\$integrations_redirect/',
			(string) $main,
			'The legacy URL is an unregistered page once the submenu is deleted, and core denies it '
				. 'before admin_init runs. admin_page_access_denied is the only hook that catches it.'
		);
	}

	/**
	 * Every other admin screen is left alone.
	 *
	 * This runs on `admin_init` at priority 1, so on every single admin request. Matching anything
	 * beyond the one retired slug would redirect screens that have nothing to do with the plugin.
	 *
	 * @param  array<string, mixed> $query Request that must not be touched.
	 * @return void
	 *
	 * @dataProvider provide_untouched_requests
	 */
	public function test_other_requests_are_untouched( array $query ): void {
		$this->assertNull( $this->target( $query ) );
	}

	/**
	 * Requests the redirect must ignore.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function provide_untouched_requests(): array {
		return array(
			'no page argument'      => array( array() ),
			'the abilities page'    => array( array( 'page' => Menu::ABILITIES_SLUG ) ),
			'an unrelated plugin'   => array( array( 'page' => 'some-other-plugin' ) ),
			'the settings page'     => array( array( 'page' => 'acrossai-settings' ) ),
			'a near-miss prefix'    => array( array( 'page' => 'acrossai-abilities-integrations-extra' ) ),
			'a non-scalar page'     => array( array( 'page' => array( 'acrossai-abilities-integrations' ) ) ),
		);
	}
}
