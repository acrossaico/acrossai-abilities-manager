<?php
/**
 * Feature 069 — enable or disable a Rank Math module.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\RankMath
 * @since      0.0.28
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\RankMath;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath\Module_Repository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Ability #28 — rank-math/set-module-state.
 *
 * Replicates Admin_Rest::save_module() in full, INCLUDING the rewrite-rule refresh
 * and the rank_math/module_changed action. A plain option write omits both, which
 * leaves stale rewrite rules — the sitemap and llms.txt routes then 404 despite the
 * module reporting itself active.
 *
 * Not destructive: toggling a module off leaves its data intact and toggling it
 * back on restores the previous state, so no confirm gate.
 */
class Set_Module_State extends Base_Rank_Math_Ability {

	protected function slug(): string {
		return 'set-module-state';
	}

	protected function ability_label(): string {
		return __( 'Set Rank Math Module State', 'acrossai-abilities-manager' );
	}

	protected function ability_description(): string {
		return __( 'Enable or disable one Rank Math module. Also refreshes rewrite rules and fires Rank Math\'s module-changed action, which the sitemap and llms.txt modules depend on — without those steps their routes return 404 even though the module reports itself active. Discover the available slugs with rank-math/list-modules. Disabling a module does not delete its data.', 'acrossai-abilities-manager' );
	}

	protected function sub_group(): string {
		return 'rank-math-admin';
	}

	/**
	 * Rank Math gates its module screen on manage_options, so there is no granular
	 * capability to compose.
	 */
	protected function rank_math_cap(): string {
		return '';
	}

	protected function input_properties(): array {
		return array(
			'module' => array(
				'type'        => 'string',
				'description' => __( 'Module slug, e.g. sitemap, redirections, 404-monitor, llms-txt, analytics. List them with rank-math/list-modules.', 'acrossai-abilities-manager' ),
			),
			'state'  => array(
				'type'        => 'string',
				'enum'        => array( 'on', 'off' ),
				'description' => __( 'Whether the module should be active.', 'acrossai-abilities-manager' ),
			),
		);
	}

	protected function output_properties(): array {
		return array(
			'module'          => array( 'type' => 'string' ),
			'state'           => array( 'type' => 'string' ),
			'was_active'      => array( 'type' => 'boolean' ),
			'is_active'       => array( 'type' => 'boolean' ),
			'rewrite_flushed' => array( 'type' => 'boolean' ),
		);
	}

	protected function required_input(): array {
		return array( 'module', 'state' );
	}

	protected function annotations(): array {
		return array( 'readonly' => false, 'destructive' => false, 'idempotent' => true );
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>|WP_Error
	 */
	protected function run( array $input ) {
		$module = isset( $input['module'] ) ? sanitize_key( (string) $input['module'] ) : '';
		$state  = isset( $input['state'] ) ? sanitize_key( (string) $input['state'] ) : '';

		if ( '' === $module ) {
			return new WP_Error( 'invalid_input', __( 'module is required.', 'acrossai-abilities-manager' ) );
		}

		$result = Module_Repository::set_state( $module, $state );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['message'] = $result['rewrite_flushed']
			? sprintf(
				/* translators: 1: module slug, 2: 'on' or 'off' */
				__( 'Set the Rank Math "%1$s" module to %2$s and refreshed rewrite rules.', 'acrossai-abilities-manager' ),
				$module,
				$state
			)
			: sprintf(
				/* translators: 1: module slug, 2: 'on' or 'off' */
				__( 'Set the Rank Math "%1$s" module to %2$s.', 'acrossai-abilities-manager' ),
				$module,
				$state
			);

		return $result;
	}
}
