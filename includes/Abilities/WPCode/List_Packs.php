<?php
/**
 * Feature 112 - lists the WPCode snippet packs.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\WPCode
 * @since      0.0.43
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\WPCode;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\WPCode\Library_Repository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * lists the WPCode snippet packs.
 *
 * @since 0.0.43
 */
final class List_Packs extends Base_WPCode_Ability {

	/**
	 * @since  0.0.43
	 * @return string
	 */
	protected function slug(): string {
		return 'snippets/list-packs';
	}

	/**
	 * @since  0.0.43
	 * @return string
	 */
	protected function ability_label(): string {
		return __( 'List Snippet Packs', 'acrossai-abilities-manager' );
	}

	/**
	 * @since  0.0.43
	 * @return string
	 */
	protected function ability_description(): string {
		return __( 'List WPCode snippet packs, which are themed bundles of library snippets, with how many snippets each contains and whether it is already installed on this site.', 'acrossai-abilities-manager' );
	}

	/**
	 * @since  0.0.43
	 * @return string
	 */
	protected function sub_group(): string {
		return 'library';
	}

	/**
	 * @since  0.0.43
	 * @return array<string, mixed>
	 */
	protected function input_properties(): array {
		return array();
	}

	/**
	 * @since  0.0.43
	 * @return array<int, string>
	 */
	protected function required_input(): array {
		return array();
	}

	/**
	 * @since  0.0.43
	 * @return array<string, mixed>
	 */
	protected function output_properties(): array {
		return array(
			'packs' => array(
				'type'  => 'array',
				'items' => array( 'type' => 'object', 'additionalProperties' => true ),
			),
			'count' => array( 'type' => 'integer' ),
		);
	}

	/**
	 * @since  0.0.43
	 * @return array<string, bool>
	 */
	protected function annotations(): array {
		return array( 'readonly' => true, 'destructive' => false, 'idempotent' => true );
	}

	/**
	 * @since  0.0.43
	 * @param  array<string, mixed> $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	protected function run( array $input ) {
		$packs = Library_Repository::packs();

		if ( is_wp_error( $packs ) ) {
			return $packs;
		}

		return array(
			'packs' => $packs,
			'count' => count( $packs ),
		);
	}
}
