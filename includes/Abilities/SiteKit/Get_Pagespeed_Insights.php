<?php
/**
 * Feature 120 — PageSpeed Insights through Site Kit.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\SiteKit
 * @since      0.0.39
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\SiteKit;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\SiteKit\Report_Repository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Ability #9 — site-kit/get-pagespeed-insights.
 *
 * Authenticated like the rest of the suite. The PageSpeed Insights API is public and
 * it is tempting to assume Site Kit reaches it with an API key — it does not.
 * PageSpeed_Insights::setup_services() builds its service on the same
 * Google_Site_Kit_Client every other module uses, and requests the 'openid' scope, so
 * an unauthenticated user gets a 401 rather than a report.
 */
class Get_Pagespeed_Insights extends Base_Site_Kit_Ability {

	protected function slug(): string {
		return 'get-pagespeed-insights';
	}

	protected function ability_label(): string {
		return __( 'Get PageSpeed Insights', 'acrossai-abilities-manager' );
	}

	protected function ability_description(): string {
		return __( 'Run Google PageSpeed Insights on one URL through Site Kit and return the full Lighthouse result: performance, accessibility, best-practices and SEO scores, the Core Web Vitals lab measurements, and — where Google has enough real traffic for the URL — the field data from the Chrome UX Report. Choose strategy "mobile" or "desktop"; they are separate tests and routinely disagree. Defaults to the site\'s home URL. The URL must be publicly reachable by Google, so this returns nothing useful for a local or password-protected site.', 'acrossai-abilities-manager' );
	}

	protected function sub_group(): string {
		return 'site-kit-data';
	}

	protected function site_kit_cap(): string {
		return '';
	}

	protected function requires_authentication(): bool {
		return true;
	}

	protected function required_module(): string {
		return Report_Repository::MODULE_PAGESPEED;
	}

	protected function input_properties(): array {
		return array(
			'url'      => array(
				'type'        => 'string',
				'description' => __( 'Public URL to test. Defaults to the site\'s own home URL.', 'acrossai-abilities-manager' ),
			),
			'strategy' => array(
				'type'        => 'string',
				'enum'        => array( 'mobile', 'desktop' ),
				'default'     => 'mobile',
				'description' => __( 'Which test to run. Mobile is the one Google ranks on.', 'acrossai-abilities-manager' ),
			),
		);
	}

	protected function output_properties(): array {
		return array(
			'strategy' => array( 'type' => 'string' ),
			'url'      => array( 'type' => 'string' ),
			'result'   => array( 'type' => array( 'object', 'array' ) ),
		);
	}

	protected function required_input(): array {
		return array();
	}

	/**
	 * Not idempotent: a Lighthouse run measures a live page at a moment in time, so
	 * repeating it returns different numbers even with identical input.
	 */
	protected function annotations(): array {
		return array( 'readonly' => true, 'destructive' => false, 'idempotent' => false );
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>|WP_Error
	 */
	protected function run( array $input ) {
		$url      = isset( $input['url'] ) ? (string) $input['url'] : '';
		$strategy = isset( $input['strategy'] ) ? (string) $input['strategy'] : 'mobile';

		$result = Report_Repository::pagespeed( $url, $strategy );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['message'] = sprintf(
			/* translators: 1: strategy, 2: URL tested or the site default */
			__( 'Ran the %1$s PageSpeed test on %2$s.', 'acrossai-abilities-manager' ),
			$strategy,
			'' !== $result['url'] ? $result['url'] : __( 'the site home URL', 'acrossai-abilities-manager' )
		);

		return $result;
	}
}
