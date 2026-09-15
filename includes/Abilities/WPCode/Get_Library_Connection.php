<?php
/**
 * Feature 112 - reports whether this site is signed in to the WPCode library.
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
 * reports whether this site is signed in to the WPCode library.
 *
 * @since 0.0.43
 */
final class Get_Library_Connection extends Base_WPCode_Ability {

	/**
	 * @since  0.0.43
	 * @return string
	 */
	protected function slug(): string {
		return 'snippets/get-library-connection';
	}

	/**
	 * @since  0.0.43
	 * @return string
	 */
	protected function ability_label(): string {
		return __( 'Get Library Connection', 'acrossai-abilities-manager' );
	}

	/**
	 * @since  0.0.43
	 * @return string
	 */
	protected function ability_description(): string {
		return __( 'Report whether this site is signed in to the WPCode snippet library and under which username. Connecting unlocks update checking for installed library snippets and installing snippets shared by link. The stored credentials are never returned by this or any other ability. Signing in cannot be done from here; it happens in wp-admin under WPCode, Library.', 'acrossai-abilities-manager' );
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
			'connected' => array( 'type' => 'boolean' ),
			'username'  => array(
				'type'        => 'string',
				'description' => __( 'The library account this site is signed in as. Empty when not connected.', 'acrossai-abilities-manager' ),
			),
			'reason'    => array(
				'type'        => 'string',
				'description' => __( 'Why the site is not connected, when it is not.', 'acrossai-abilities-manager' ),
			),
			'unlocks'   => array(
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'description' => __( 'Abilities that need the connection to work.', 'acrossai-abilities-manager' ),
			),
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
		return array_merge(
			Library_Repository::connection(),
			array(
				'unlocks' => array(
					'snippets/list-snippet-updates',
					'snippets/update-snippet-from-library',
					'snippets/install-shared-snippet',
				),
			)
		);
	}
}
