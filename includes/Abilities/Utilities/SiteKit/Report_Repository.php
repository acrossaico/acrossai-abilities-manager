<?php
/**
 * Feature 120 — Google data reads through Site Kit's own module clients.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Utilities\SiteKit
 * @since      0.0.39
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\SiteKit;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Static-only reader for Search Console, Analytics 4, PageSpeed Insights and AdSense.
 *
 * Every call goes through Module::get_data(), which is Site Kit's own server-side
 * entry point: it builds the Google API request, signs it with the current user's
 * stored OAuth token, executes it and parses the response. Nothing here talks to
 * Google directly and nothing re-implements a request shape — the arguments are the
 * same ones Site Kit's own dashboard sends.
 *
 * SECURITY NOTE. get_data() is deliberately not the REST route: REST_Modules_Controller
 * layers datapoint-level permission checks (shareable datapoints, dashboard sharing)
 * on top of it, and calling get_data() directly skips those. That is acceptable here
 * only because every ability in this suite is gated at manage_options or Site Kit's own
 * capability first — the abilities are the permission layer, so they may not be
 * loosened without revisiting this.
 */
final class Report_Repository {

	/**
	 * Site Kit module slugs this repository reaches.
	 */
	public const MODULE_SEARCH_CONSOLE = 'search-console';
	public const MODULE_ANALYTICS      = 'analytics-4';
	public const MODULE_PAGESPEED      = 'pagespeed-insights';
	public const MODULE_ADSENSE        = 'adsense';

	/**
	 * Private constructor — this class is static-only (DEC-UTILITY-STATIC-ONLY).
	 */
	private function __construct() {}

	/**
	 * Search Console search analytics.
	 *
	 * @param array<string,mixed> $args Ability input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function search_analytics( array $args ) {
		$request = array(
			'startDate' => (string) ( $args['start_date'] ?? '' ),
			'endDate'   => (string) ( $args['end_date'] ?? '' ),
			'limit'     => max( 1, min( 1000, (int) ( $args['limit'] ?? 20 ) ) ),
		);

		if ( ! empty( $args['dimensions'] ) && is_array( $args['dimensions'] ) ) {
			$request['dimensions'] = array_values( array_map( 'strval', $args['dimensions'] ) );
		}
		if ( ! empty( $args['url'] ) ) {
			$request['url'] = esc_url_raw( (string) $args['url'] );
		}

		$rows = self::request( self::MODULE_SEARCH_CONSOLE, 'searchanalytics', $request );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		return array(
			'rows'    => is_array( $rows ) ? $rows : array(),
			'request' => $request,
		);
	}

	/**
	 * Analytics 4 runReport.
	 *
	 * @param array<string,mixed> $args Ability input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function analytics_report( array $args ) {
		$metrics = $args['metrics'] ?? array();
		if ( is_string( $metrics ) ) {
			$metrics = array_filter( array_map( 'trim', explode( ',', $metrics ) ) );
		}
		if ( ! is_array( $metrics ) || array() === $metrics ) {
			return new WP_Error(
				'invalid_input',
				__( 'metrics is required — Analytics cannot run a report without at least one. Common choices: sessions, totalUsers, screenPageViews, engagementRate.', 'acrossai-abilities-manager' )
			);
		}

		$request = array(
			'metrics'   => array_values( array_map( 'strval', $metrics ) ),
			'startDate' => (string) ( $args['start_date'] ?? '' ),
			'endDate'   => (string) ( $args['end_date'] ?? '' ),
			'limit'     => max( 1, min( 1000, (int) ( $args['limit'] ?? 20 ) ) ),
		);

		if ( ! empty( $args['dimensions'] ) && is_array( $args['dimensions'] ) ) {
			$request['dimensions'] = array_values( array_map( 'strval', $args['dimensions'] ) );
		}
		if ( ! empty( $args['url'] ) ) {
			$request['url'] = esc_url_raw( (string) $args['url'] );
		}
		if ( ! empty( $args['order_by'] ) ) {
			// Site Kit accepts its dashboard's orderby shape; pass it straight through
			// rather than inventing a second grammar for the same thing.
			$request['orderby'] = $args['order_by'];
		}

		$report = self::request( self::MODULE_ANALYTICS, 'report', $request );
		if ( is_wp_error( $report ) ) {
			return $report;
		}

		return array(
			'report'  => $report,
			'request' => $request,
		);
	}

	/**
	 * PageSpeed Insights for one URL.
	 *
	 * @param string $url      URL to test, or '' for the site's reference URL.
	 * @param string $strategy 'mobile' or 'desktop'.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function pagespeed( string $url, string $strategy ) {
		if ( ! in_array( $strategy, array( 'mobile', 'desktop' ), true ) ) {
			return new WP_Error(
				'invalid_input',
				__( 'strategy must be "mobile" or "desktop".', 'acrossai-abilities-manager' )
			);
		}

		$request = array( 'strategy' => $strategy );
		if ( '' !== $url ) {
			$request['url'] = esc_url_raw( $url );
		}

		$result = self::request( self::MODULE_PAGESPEED, 'pagespeed', $request );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'strategy' => $strategy,
			'url'      => $request['url'] ?? '',
			'result'   => $result,
		);
	}

	/**
	 * AdSense earnings report.
	 *
	 * @param array<string,mixed> $args Ability input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function adsense_report( array $args ) {
		$request = array(
			'startDate' => (string) ( $args['start_date'] ?? '' ),
			'endDate'   => (string) ( $args['end_date'] ?? '' ),
			'limit'     => max( 1, min( 1000, (int) ( $args['limit'] ?? 20 ) ) ),
		);

		if ( ! empty( $args['metrics'] ) && is_array( $args['metrics'] ) ) {
			$request['metrics'] = array_values( array_map( 'strval', $args['metrics'] ) );
		}
		if ( ! empty( $args['dimensions'] ) && is_array( $args['dimensions'] ) ) {
			$request['dimensions'] = array_values( array_map( 'strval', $args['dimensions'] ) );
		}

		$report = self::request( self::MODULE_ADSENSE, 'report', $request );
		if ( is_wp_error( $report ) ) {
			return $report;
		}

		return array(
			'report'  => $report,
			'request' => $request,
		);
	}

	/**
	 * Any GET datapoint on any module.
	 *
	 * Site Kit exposes roughly sixty datapoints across its modules and wraps each in
	 * its own request shape. Wrapping every one as an ability would be a maintenance
	 * treadmill against a plugin that ships weekly, so the named abilities cover what
	 * people actually ask for and this covers the rest — discover the list with
	 * site-kit/list-module-datapoints.
	 *
	 * GET only, deliberately. set_data() reaches Site Kit's POST datapoints, which
	 * create Analytics properties and rewrite tag configuration; those belong behind
	 * named, confirm-gated abilities rather than a generic passthrough.
	 *
	 * @param string              $slug      Module slug.
	 * @param string              $datapoint Datapoint name, without the GET: prefix.
	 * @param array<string,mixed> $params    Datapoint parameters.
	 * @return mixed|WP_Error
	 */
	public static function module_data( string $slug, string $datapoint, array $params ) {
		return self::request( $slug, $datapoint, $params );
	}

	/**
	 * Execute one datapoint request and normalise whatever comes back.
	 *
	 * @param string              $slug      Module slug.
	 * @param string              $datapoint Datapoint name.
	 * @param array<string,mixed> $params    Datapoint parameters.
	 * @return mixed|WP_Error
	 */
	private static function request( string $slug, string $datapoint, array $params ) {
		$module = Module_Repository::module( $slug );
		if ( is_wp_error( $module ) ) {
			return $module;
		}

		if ( ! method_exists( $module, 'get_data' ) ) {
			return new WP_Error(
				'site_kit_request_failed',
				sprintf(
					/* translators: %s: Site Kit module slug */
					__( 'The Site Kit "%s" module cannot serve data requests in this version.', 'acrossai-abilities-manager' ),
					$slug
				)
			);
		}

		try {
			$response = $module->get_data( $datapoint, $params );
		} catch ( \Throwable $e ) {
			// A datapoint that does not exist throws rather than returning WP_Error, and
			// an expired token can surface as a transport exception. Neither should reach
			// the client as a fatal.
			return new WP_Error(
				'site_kit_request_failed',
				sprintf(
					/* translators: 1: datapoint name, 2: module slug, 3: error message */
					__( 'Site Kit could not serve "%1$s" from the "%2$s" module: %3$s', 'acrossai-abilities-manager' ),
					$datapoint,
					$slug,
					$e->getMessage()
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			return self::rewrite_error( $response, $slug );
		}

		return self::normalize( $response );
	}

	/**
	 * Turn Site Kit's Google service models into plain arrays.
	 *
	 * Responses come back as generated Google\Service\* model objects whose public
	 * shape is defined by protected property maps, so neither (array) nor get_object_vars()
	 * produces anything useful. A JSON round-trip is what Site Kit's own REST layer
	 * does to serialise them, so it yields exactly the shape its dashboard consumes.
	 *
	 * @param mixed $response Raw response.
	 * @return mixed
	 */
	private static function normalize( $response ) {
		if ( is_scalar( $response ) || null === $response ) {
			return $response;
		}

		$encoded = wp_json_encode( $response );
		if ( false === $encoded ) {
			return array();
		}

		$decoded = json_decode( $encoded, true );

		return null === $decoded ? array() : $decoded;
	}

	/**
	 * Give Site Kit's API errors an actionable message.
	 *
	 * Google's own 401/403 text ("Request had invalid authentication credentials")
	 * says nothing about what to do in WordPress, and this is the most common failure
	 * on a site where a token has expired.
	 *
	 * @param WP_Error $error Error from Site Kit.
	 * @param string   $slug  Module slug.
	 * @return WP_Error
	 */
	private static function rewrite_error( WP_Error $error, string $slug ): WP_Error {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;

		if ( 401 === $status || 403 === $status ) {
			return new WP_Error(
				'site_kit_not_authenticated',
				sprintf(
					/* translators: 1: module slug, 2: message from Google */
					__( 'Google rejected the credentials Site Kit holds for the current user on the "%1$s" module, so the token has most likely expired or been revoked. Reconnect at Site Kit → Dashboard. Google said: %2$s', 'acrossai-abilities-manager' ),
					$slug,
					$error->get_error_message()
				),
				$data
			);
		}

		return new WP_Error(
			'site_kit_request_failed',
			sprintf(
				/* translators: 1: module slug, 2: message from Site Kit or Google */
				__( 'The Site Kit "%1$s" request failed: %2$s', 'acrossai-abilities-manager' ),
				$slug,
				$error->get_error_message()
			),
			$data
		);
	}
}
