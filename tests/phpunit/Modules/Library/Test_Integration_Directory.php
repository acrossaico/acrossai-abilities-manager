<?php
/**
 * Feature 120 — the integration catalogue fetch.
 *
 * This is the plugin's first outbound HTTP request from PHP, and the readme now makes four promises
 * about it to the WordPress.org reviewers and to anyone running the plugin: it sends no site or user
 * data, it never breaks the screen when it fails, it does not hammer a dead endpoint, and it trusts
 * nothing it reads back. Each of those is a property of the code rather than a comment, so each is
 * asserted here.
 *
 * The trust tests are the ones that matter most. `normalise()` is reached through the real fetch
 * path rather than called directly, so what these cases exercise is exactly what a live response
 * would go through — size cap, JSON decode, whitelist, and the URL host check.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.42
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Integration_Directory;

require_once dirname( __DIR__, 4 ) . '/includes/Modules/Library/AcrossAI_Integration_Directory.php';

/**
 * Covers AcrossAI_Integration_Directory.
 */
class Test_Integration_Directory extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		acrossai_test_transients( array() );
		acrossai_test_http_response( null );

		$GLOBALS['acrossai_test_http_calls']   = array();
		$GLOBALS['acrossai_test_filter_values'] = array();

		AcrossAI_Integration_Directory::flush();
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		acrossai_test_transients( array() );
		acrossai_test_http_response( null );

		AcrossAI_Integration_Directory::flush();

		parent::tearDown();
	}

	/**
	 * Build a 200 response carrying the given payload.
	 *
	 * @param  mixed $payload Anything json_encode can take, or a raw string body.
	 * @return array<string, mixed>
	 */
	private function reply( $payload ): array {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => is_string( $payload ) ? $payload : (string) wp_json_encode( $payload ),
		);
	}

	/**
	 * One well-formed catalogue entry.
	 *
	 * @param  array<string, mixed> $overrides Fields to replace.
	 * @return array<string, mixed>
	 */
	private function entry( array $overrides = array() ): array {
		return array_merge(
			array(
				'slug'        => 'rank-math',
				'name'        => 'Rank Math SEO',
				'description' => 'SEO meta, redirections, sitemaps.',
				'category'    => 'SEO',
				'product'     => 'free',
				'wporg_slug'  => 'seo-by-rank-math',
				'url'         => 'https://acrossai.co/integrations/rank-math/',
				'abilities'   => 61,
			),
			$overrides
		);
	}

	/**
	 * The bundled copy is a real, parseable catalogue.
	 *
	 * It is the entire fallback story, so a typo in it would mean an air-gapped site renders an
	 * empty screen. Asserted by disabling fetching outright, which is also the documented way to
	 * pin a site to the bundled list.
	 *
	 * @return void
	 */
	public function test_the_bundled_catalogue_parses_and_is_marked_stale(): void {
		$GLOBALS['acrossai_test_filter_values']['acrossai_integration_directory_endpoint'] = '';

		$result = AcrossAI_Integration_Directory::get();

		$this->assertTrue( $result['stale'], 'The bundled copy must announce itself as possibly out of date.' );
		$this->assertNotSame( array(), $result['entries'], 'The bundled catalogue must not be empty.' );
		$this->assertSame(
			array(),
			$GLOBALS['acrossai_test_http_calls'],
			'An empty endpoint must disable fetching entirely.'
		);

		foreach ( $result['entries'] as $slug => $entry ) {
			$this->assertSame( $slug, $entry['slug'], 'Entries must be keyed by their own slug.' );
			$this->assertNotSame( '', $entry['name'] );
			$this->assertContains( $entry['product'], array( 'free', 'pro' ) );
		}
	}

	/**
	 * A good response is used, and marked fresh.
	 *
	 * @return void
	 */
	public function test_a_good_response_is_used_and_not_stale(): void {
		acrossai_test_http_response(
			$this->reply( array( 'integrations' => array( $this->entry() ) ) )
		);

		$result = AcrossAI_Integration_Directory::get();

		$this->assertFalse( $result['stale'] );
		$this->assertArrayHasKey( 'rank-math', $result['entries'] );
		$this->assertSame( 'Rank Math SEO', $result['entries']['rank-math']['name'] );
		$this->assertSame( '61', $result['entries']['rank-math']['abilities'] );
	}

	/**
	 * The request carries nothing that identifies this site.
	 *
	 * The readme states the response is identical for every site. That is only true if the request
	 * is identical too, so the absence of a query string, cookies and a body is the assertion.
	 *
	 * @return void
	 */
	public function test_the_request_sends_no_site_or_user_data(): void {
		acrossai_test_http_response( $this->reply( array( 'integrations' => array( $this->entry() ) ) ) );

		AcrossAI_Integration_Directory::get();

		$this->assertCount( 1, $GLOBALS['acrossai_test_http_calls'] );

		$call = $GLOBALS['acrossai_test_http_calls'][0];

		$this->assertStringNotContainsString( '?', $call['url'], 'No query arguments.' );
		$this->assertSame( array(), $call['args']['cookies'], 'No cookies.' );
		$this->assertArrayNotHasKey( 'body', $call['args'], 'No request body.' );
		$this->assertSame( array( 'Accept' => 'application/json' ), $call['args']['headers'] );
		$this->assertTrue( $call['args']['sslverify'] );
	}

	/**
	 * Every failure mode falls back rather than throwing or emptying the screen.
	 *
	 * @dataProvider provide_failures
	 * @param  mixed $response What the endpoint does.
	 * @return void
	 */
	public function test_a_failure_falls_back_to_the_bundled_copy( $response ): void {
		acrossai_test_http_response( $response );

		$result = AcrossAI_Integration_Directory::get();

		$this->assertTrue( $result['stale'] );
		$this->assertNotSame( array(), $result['entries'], 'A failure must still render a list.' );
	}

	/**
	 * @return array<string, array<int, mixed>>
	 */
	public static function provide_failures(): array {
		return array(
			'unreachable host'   => array( new WP_Error( 'http_request_failed', 'down' ) ),
			'500'                => array( array( 'response' => array( 'code' => 500 ), 'body' => 'nope' ) ),
			'404'                => array( array( 'response' => array( 'code' => 404 ), 'body' => '' ) ),
			'empty body'         => array( array( 'response' => array( 'code' => 200 ), 'body' => '' ) ),
			'not json'           => array( array( 'response' => array( 'code' => 200 ), 'body' => '<html>hi</html>' ) ),
			'json but not ours'  => array( array( 'response' => array( 'code' => 200 ), 'body' => '{"ok":true}' ) ),
			'integrations wrong' => array( array( 'response' => array( 'code' => 200 ), 'body' => '{"integrations":"no"}' ) ),
		);
	}

	/**
	 * An oversized body is rejected before it is parsed.
	 *
	 * @return void
	 */
	public function test_an_oversized_body_is_refused(): void {
		acrossai_test_http_response(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => str_repeat( 'x', AcrossAI_Integration_Directory::MAX_BYTES + 1 ),
			)
		);

		$this->assertTrue( AcrossAI_Integration_Directory::get()['stale'] );
	}

	/**
	 * A dead endpoint is asked once, not once per page load.
	 *
	 * Without the backoff every render during an outage pays the full timeout.
	 *
	 * @return void
	 */
	public function test_a_failure_is_not_retried_immediately(): void {
		acrossai_test_http_response( new WP_Error( 'http_request_failed', 'down' ) );

		AcrossAI_Integration_Directory::get();
		$this->assertCount( 1, $GLOBALS['acrossai_test_http_calls'] );

		// A fresh page load: the per-request memo is gone, the transients are not.
		$this->reset_memo();
		AcrossAI_Integration_Directory::get();

		$this->assertCount(
			1,
			$GLOBALS['acrossai_test_http_calls'],
			'The backoff transient must suppress the second attempt.'
		);
	}

	/**
	 * A good response is cached, so the next page load does not re-fetch.
	 *
	 * @return void
	 */
	public function test_a_good_response_is_cached_across_requests(): void {
		acrossai_test_http_response( $this->reply( array( 'integrations' => array( $this->entry() ) ) ) );

		AcrossAI_Integration_Directory::get();
		$this->reset_memo();
		$result = AcrossAI_Integration_Directory::get();

		$this->assertCount( 1, $GLOBALS['acrossai_test_http_calls'] );
		$this->assertFalse( $result['stale'] );
		$this->assertArrayHasKey( 'rank-math', $result['entries'] );
	}

	/**
	 * Unknown keys in a response never reach the caller.
	 *
	 * The whitelist is the difference between "a changed endpoint makes this screen wrong" and "a
	 * changed endpoint makes this screen dangerous".
	 *
	 * @return void
	 */
	public function test_unexpected_fields_are_discarded(): void {
		acrossai_test_http_response(
			$this->reply(
				array(
					'integrations' => array(
						$this->entry(
							array(
								'onclick'     => 'alert(1)',
								'capability'  => 'manage_options',
								'enabled'     => true,
								'__proto__'   => 'x',
							)
						),
					),
				)
			)
		);

		$entry = AcrossAI_Integration_Directory::get()['entries']['rank-math'];

		$this->assertSame(
			array( 'slug', 'name', 'description', 'category', 'product', 'wporg_slug', 'url', 'abilities' ),
			array_keys( $entry ),
			'Only the declared schema may survive normalisation.'
		);
	}

	/**
	 * The catalogue cannot claim an integration is enabled.
	 *
	 * Belongs here rather than in the REST tests because it is the schema that makes it impossible:
	 * there is no field for it to travel in.
	 *
	 * @return void
	 */
	public function test_the_catalogue_cannot_carry_enabled_state(): void {
		acrossai_test_http_response(
			$this->reply( array( 'integrations' => array( $this->entry( array( 'enabled' => true ) ) ) ) )
		);

		$this->assertArrayNotHasKey(
			'enabled',
			AcrossAI_Integration_Directory::get()['entries']['rank-math']
		);
	}

	/**
	 * Links are accepted only when they point at acrossai.co over HTTPS.
	 *
	 * These render as clickable links in wp-admin, so the catalogue is not allowed to aim an
	 * administrator anywhere it likes.
	 *
	 * @dataProvider provide_urls
	 * @param  string $url      Candidate.
	 * @param  string $expected What should survive.
	 * @return void
	 */
	public function test_only_first_party_https_links_survive( string $url, string $expected ): void {
		acrossai_test_http_response(
			$this->reply( array( 'integrations' => array( $this->entry( array( 'url' => $url ) ) ) ) )
		);

		$this->assertSame(
			$expected,
			AcrossAI_Integration_Directory::get()['entries']['rank-math']['url']
		);
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	public static function provide_urls(): array {
		return array(
			'canonical'        => array( 'https://acrossai.co/integrations/x/', 'https://acrossai.co/integrations/x/' ),
			'www'              => array( 'https://www.acrossai.co/integrations/x/', 'https://www.acrossai.co/integrations/x/' ),
			'plain http'       => array( 'http://acrossai.co/integrations/x/', '' ),
			'other host'       => array( 'https://evil.example/x/', '' ),
			'lookalike host'   => array( 'https://acrossai.co.evil.example/x/', '' ),
			'javascript'       => array( 'javascript:alert(1)', '' ),
			'protocol relative' => array( '//acrossai.co/x/', '' ),
			'empty'            => array( '', '' ),
		);
	}

	/**
	 * A row with no slug or no name is dropped rather than half-rendered.
	 *
	 * @return void
	 */
	public function test_rows_that_cannot_be_rendered_are_dropped(): void {
		acrossai_test_http_response(
			$this->reply(
				array(
					'integrations' => array(
						$this->entry( array( 'slug' => '' ) ),
						$this->entry( array( 'slug' => 'nameless', 'name' => '' ) ),
						'not-an-array',
						$this->entry( array( 'slug' => 'keeper', 'name' => 'Keeper' ) ),
					),
				)
			)
		);

		$entries = AcrossAI_Integration_Directory::get()['entries'];

		$this->assertSame( array( 'keeper' ), array_keys( $entries ) );
	}

	/**
	 * `product` is a two-value enum, whatever arrives.
	 *
	 * @return void
	 */
	public function test_product_is_coerced_to_free_or_pro(): void {
		acrossai_test_http_response(
			$this->reply(
				array(
					'integrations' => array(
						$this->entry( array( 'slug' => 'a', 'product' => 'pro' ) ),
						$this->entry( array( 'slug' => 'b', 'product' => 'free' ) ),
						$this->entry( array( 'slug' => 'c', 'product' => 'enterprise' ) ),
						$this->entry( array( 'slug' => 'd', 'product' => array( 'pro' ) ) ),
					),
				)
			)
		);

		$entries = AcrossAI_Integration_Directory::get()['entries'];

		$this->assertSame( 'pro', $entries['a']['product'] );
		$this->assertSame( 'free', $entries['b']['product'] );
		$this->assertSame( 'free', $entries['c']['product'], 'An unknown product must not unlock anything.' );
		$this->assertSame( 'free', $entries['d']['product'] );
	}

	/**
	 * Long strings are clipped rather than passed through.
	 *
	 * @return void
	 */
	public function test_overlong_text_is_clipped(): void {
		acrossai_test_http_response(
			$this->reply(
				array(
					'integrations' => array(
						$this->entry(
							array(
								'name'        => str_repeat( 'a', 500 ),
								'description' => str_repeat( 'b', 5000 ),
								'category'    => str_repeat( 'c', 500 ),
							)
						),
					),
				)
			)
		);

		$entry = AcrossAI_Integration_Directory::get()['entries']['rank-math'];

		$this->assertLessThanOrEqual( 80, mb_strlen( $entry['name'] ) );
		$this->assertLessThanOrEqual( 300, mb_strlen( $entry['description'] ) );
		$this->assertLessThanOrEqual( 60, mb_strlen( $entry['category'] ) );
	}

	/**
	 * A response with more rows than the cap is truncated.
	 *
	 * @return void
	 */
	public function test_the_entry_count_is_capped(): void {
		$rows = array();

		for ( $i = 0; $i < AcrossAI_Integration_Directory::MAX_ENTRIES + 50; $i++ ) {
			$rows[] = $this->entry( array( 'slug' => 'int-' . $i ) );
		}

		acrossai_test_http_response( $this->reply( array( 'integrations' => $rows ) ) );

		$this->assertCount(
			AcrossAI_Integration_Directory::MAX_ENTRIES,
			AcrossAI_Integration_Directory::get()['entries']
		);
	}

	/**
	 * Clear only the per-request memo, leaving the transients — what a new page load looks like.
	 *
	 * @return void
	 */
	private function reset_memo(): void {
		$property = new ReflectionProperty( AcrossAI_Integration_Directory::class, 'memo' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}
}
