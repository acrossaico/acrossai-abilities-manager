<?php
/**
 * Feature 069 — read cached SEO Analyzer results.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\RankMath
 * @since      0.0.28
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\RankMath;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath\Status_Tools_Repository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Ability #38 — rank-math/get-seo-analysis-results.
 *
 * Deliberately does NOT re-run the analyzer. Rank Math core already ships
 * rank-math/audit-site-seo for that, and running it makes remote API calls to
 * rankmath.com. This is the cheap read of whatever the last run left behind.
 *
 * No stored run is reported as success with has_results:false, not as an error —
 * "nobody has run the audit yet" is a valid answer, not a failure.
 *
 * Read-only, idempotent.
 */
class Get_Seo_Analysis_Results extends Base_Rank_Math_Ability {

	protected function slug(): string {
		return 'get-seo-analysis-results';
	}

	protected function ability_label(): string {
		return __( 'Get Cached Rank Math SEO Analysis', 'acrossai-abilities-manager' );
	}

	protected function ability_description(): string {
		return __( 'Return the stored results of the last Rank Math SEO Analyzer run, with the date it was performed. Does not re-run the analysis and makes no remote requests — to run a fresh audit use Rank Math\'s own rank-math/audit-site-seo ability. Returns has_results false when no audit has been run.', 'acrossai-abilities-manager' );
	}

	protected function sub_group(): string {
		return 'rank-math-content';
	}

	protected function rank_math_cap(): string {
		return 'site_analysis';
	}

	protected function required_module(): string {
		return 'seo-analysis';
	}

	protected function input_properties(): array {
		return array();
	}

	protected function output_properties(): array {
		return array(
			'has_results'  => array( 'type' => 'boolean' ),
			'last_checked' => array( 'type' => array( 'string', 'null' ) ),
			'result_count' => array( 'type' => 'integer' ),
			'results'      => array( 'type' => 'array' ),
		);
	}

	protected function required_input(): array {
		return array();
	}

	protected function annotations(): array {
		return array( 'readonly' => true, 'destructive' => false, 'idempotent' => true );
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>|WP_Error
	 */
	protected function run( array $input ) {
		$result = Status_Tools_Repository::seo_analysis_results();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['message'] = $result['has_results']
			? sprintf(
				/* translators: 1: number of test results, 2: date of the last run */
				__( 'Returned %1$d stored SEO analysis results from %2$s.', 'acrossai-abilities-manager' ),
				$result['result_count'],
				null !== $result['last_checked'] ? $result['last_checked'] : __( 'an unknown date', 'acrossai-abilities-manager' )
			)
			: __( 'No SEO analysis has been run on this site yet. Run Rank Math\'s rank-math/audit-site-seo ability first.', 'acrossai-abilities-manager' );

		return $result;
	}
}
