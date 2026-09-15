<?php
/**
 * Feature 112 - lists installed library snippets with a newer version available.
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
 * lists installed library snippets with a newer version available.
 *
 * @since 0.0.43
 */
final class List_Snippet_Updates extends Base_WPCode_Ability {

	/**
	 * @since  0.0.43
	 * @return string
	 */
	protected function slug(): string {
		return 'snippets/list-snippet-updates';
	}

	/**
	 * @since  0.0.43
	 * @return string
	 */
	protected function ability_label(): string {
		return __( 'List Snippet Updates', 'acrossai-abilities-manager' );
	}

	/**
	 * @since  0.0.43
	 * @return string
	 */
	protected function ability_description(): string {
		return __( 'List the snippets on this site that came from the WPCode library and have a newer version upstream. Nothing on a WordPress site tells you this: a library snippet is copied in once and then never changes, so a fix published upstream never arrives unless someone checks. Each row reports the local version and whether the snippet is currently active, so you can judge the risk before pulling.', 'acrossai-abilities-manager' );
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
			'updates' => array(
				'type'  => 'array',
				'items' => array( 'type' => 'object', 'additionalProperties' => true ),
			),
			'count'   => array( 'type' => 'integer' ),
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
		$updates = Library_Repository::updates();

		if ( is_wp_error( $updates ) ) {
			return $updates;
		}

		return array(
			'updates' => $updates,
			'count'   => count( $updates ),
		);
	}
}
