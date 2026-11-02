<?php
/**
 * The plugin checksums manifest endpoint, and what a failed fetch is allowed to claim.
 *
 * plugins/verify-plugin-checksums called api.wordpress.org/plugins/checksums/1.0/, which is not a
 * WordPress.org endpoint and 404s for every plugin and version. Because the code treated any
 * non-200 as "no manifest available" with success:true, the integrity check reported that every
 * plugin on every site could not be verified, and looked successful doing it.
 *
 * It had no test because the URL sat behind a wp_remote_get() inside a method that also
 * require_once's wp-admin/includes/plugin.php, which does not exist under the stub harness. The URL
 * builder and the response classifier are extracted so both can be driven directly.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Abilities\Plugins\Verify_Plugin_Checksums;

require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Plugins/Verify_Plugin_Checksums.php';

final class Test_Verify_Plugin_Checksums_Endpoint extends TestCase {

	/* ------------------------------------------------------------------ */
	/* The URL — the actual defect                                        */
	/* ------------------------------------------------------------------ */

	public function test_manifest_url_is_the_endpoint_that_exists(): void {
		$this->assertSame(
			'https://downloads.wordpress.org/plugin-checksums/akismet/5.7.2.json',
			Verify_Plugin_Checksums::manifest_url( 'akismet', '5.7.2' ),
			'Verified live: this returns 200 with a 47-file manifest.'
		);
	}

	public function test_manifest_url_is_not_the_old_404ing_api_path(): void {
		$url = Verify_Plugin_Checksums::manifest_url( 'akismet', '5.7.2' );

		$this->assertStringNotContainsString(
			'api.wordpress.org',
			$url,
			'api.wordpress.org/plugins/checksums/1.0/ 404s for every plugin — it is not a WP.org endpoint.'
		);
		$this->assertStringNotContainsString( '?plugin=', $url );
		$this->assertStringNotContainsString( 'checksums/1.0', $url );
	}

	public function test_slug_and_version_are_url_encoded(): void {
		// A slug cannot contain a slash in practice, but the builder must not be the place that
		// assumes it: an unencoded value here would change which path is requested.
		$url = Verify_Plugin_Checksums::manifest_url( 'a b/c', '1.0 beta' );

		$this->assertStringNotContainsString( ' ', $url );
		$this->assertStringContainsString( 'a%20b%2Fc', $url );
		$this->assertStringContainsString( '1.0%20beta.json', $url );
	}

	public function test_url_ends_in_the_json_document(): void {
		$this->assertStringEndsWith( '.json', Verify_Plugin_Checksums::manifest_url( 'x', '1.0' ) );
	}

	/* ------------------------------------------------------------------ */
	/* What each outcome is allowed to claim                              */
	/* ------------------------------------------------------------------ */

	public function test_a_usable_response_is_not_a_failure(): void {
		$this->assertNull(
			Verify_Plugin_Checksums::manifest_failure( array( 'response' => array( 'code' => 200 ) ) ),
			'200 must let verification proceed.'
		);
	}

	public function test_404_is_a_fact_about_the_plugin_not_a_failure(): void {
		$out = Verify_Plugin_Checksums::manifest_failure( array( 'response' => array( 'code' => 404 ) ), 'acme-pro', '2.1' );

		$this->assertIsArray( $out );
		$this->assertTrue( $out['success'], 'WP.org having no manifest is an answer, not an error.' );
		$this->assertSame( 'no_manifest', $out['error_code'] );
		$this->assertStringContainsString( 'acme-pro', $out['message'] );
		$this->assertStringContainsString( '2.1', $out['message'] );
	}

	/**
	 * @return array<string,array{0:int}>
	 */
	public static function failing_status_provider(): array {
		return array(
			'server error'      => array( 500 ),
			'bad gateway'       => array( 502 ),
			'service down'      => array( 503 ),
			'rate limited'      => array( 429 ),
			'forbidden'         => array( 403 ),
			'unexpected redirect' => array( 301 ),
		);
	}

	/**
	 * @dataProvider failing_status_provider
	 * @param int $status HTTP status.
	 */
	public function test_any_other_status_is_a_request_failure( int $status ): void {
		$out = Verify_Plugin_Checksums::manifest_failure( array( 'response' => array( 'code' => $status ) ), 'akismet', '5.7.2' );

		$this->assertIsArray( $out );
		$this->assertFalse(
			$out['success'],
			"HTTP $status is a fact about the request. Reporting it as success tells the caller their plugin cannot be verified when the truth is that we could not ask."
		);
		$this->assertSame( 'manifest_fetch_failed', $out['error_code'] );
		$this->assertStringContainsString( (string) $status, $out['message'] );
	}

	public function test_a_transport_error_is_a_request_failure(): void {
		$error = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );

		$out = Verify_Plugin_Checksums::manifest_failure( $error, 'akismet', '5.7.2' );

		$this->assertIsArray( $out );
		$this->assertFalse( $out['success'] );
		$this->assertSame( 'manifest_fetch_failed', $out['error_code'] );
		$this->assertStringContainsString( 'timed out', $out['message'], 'The transport reason must survive into the message.' );
	}

	public function test_a_timeout_is_never_reported_as_no_manifest(): void {
		// The precise confusion this fix exists to remove.
		$out = Verify_Plugin_Checksums::manifest_failure( new WP_Error( 'http_request_failed', 'timeout' ) );

		$this->assertNotSame( 'no_manifest', $out['error_code'] );
	}

	public function test_messages_survive_without_a_slug(): void {
		$out = Verify_Plugin_Checksums::manifest_failure( array( 'response' => array( 'code' => 404 ) ) );

		$this->assertSame( 'no_manifest', $out['error_code'] );
		$this->assertNotSame( '', trim( $out['message'] ) );
	}

	/* ------------------------------------------------------------------ */
	/* Contract                                                           */
	/* ------------------------------------------------------------------ */

	public function test_error_code_is_declared_in_the_output_schema(): void {
		// additionalProperties is false on this ability, so an undeclared key is stripped by output
		// validation and the caller silently loses it.
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Plugins/Verify_Plugin_Checksums.php'
		);

		$this->assertStringContainsString( "'additionalProperties' => false", $src );
		$this->assertMatchesRegularExpression( "/'error_code' *=> array\( 'type' => 'string' \)/", $src );
	}

	public function test_the_old_endpoint_is_gone_from_the_source(): void {
		$src = (string) php_strip_whitespace(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Plugins/Verify_Plugin_Checksums.php'
		);

		$this->assertStringNotContainsString( 'plugins/checksums/1.0', $src );
	}
}
