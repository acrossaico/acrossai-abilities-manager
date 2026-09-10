<?php
/**
 * Feature 064 — Uninstall a plugin (fires uninstall hook + deletes files).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Plugins
 * @since      0.0.23
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Plugins;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\File_Mods_Guard;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Plugin_Helpers;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Uninstall a plugin by fuzzy identifier. Wraps WP core's own
 * uninstall_plugin() from wp-admin/includes/plugin.php so the plugin's
 * registered uninstall hook fires and its files are deleted.
 *
 * Guardrails, in order:
 *   1. File_Mods_Guard::blocked_response('install') -> file_mods_disallowed.
 *   2. Plugin_Helpers::resolve_plugin() returns null (or low certainty) ->
 *      plugin_not_found.
 *   3. is_plugin_active() -> plugin_active (caller must deactivate first).
 */
class Uninstall_Plugin extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'plugins/uninstall-plugin',
			'args' => array(
				'label'               => __( 'Uninstall Plugin', 'acrossai-abilities-manager' ),
				'description'         => __( 'Uninstall a plugin (fires its uninstall hook and deletes its files) via WordPress core uninstall_plugin(). Distinct from deactivate-plugin (which only flips active_plugins). Refuses on active plugins and honours DISALLOW_FILE_MODS.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-plugins',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'plugin'      => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'delete_data' => array(
							'type'    => 'boolean',
							'default' => true,
						),
					),
					'required'             => array( 'plugin' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'        => array( 'type' => 'boolean' ),
						'uninstalled'    => array( 'type' => 'boolean' ),
						'plugin_file'    => array( 'type' => array( 'string', 'null' ) ),
						'plugin_name'    => array( 'type' => array( 'string', 'null' ) ),
						'message'        => array( 'type' => 'string' ),
						'blocked_reason' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'updates',
						'sub_group'       => 'lifecycle',
						'sub_group_label' => __( 'Lifecycle', 'acrossai-abilities-manager' ),
					),
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => false,
						'type'   => 'tool',
					),
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * @param array $input Ability input payload.
	 * @return array
	 */
	public function execute( array $input = array() ): array {
		$blocked = File_Mods_Guard::blocked_response( 'install' );
		if ( null !== $blocked ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'file_mods_disallowed',
				'message'        => $blocked['message'],
			);
		}

		$identifier = sanitize_text_field( (string) ( $input['plugin'] ?? '' ) );
		if ( '' === $identifier ) {
			return array(
				'success' => false,
				'message' => __( 'plugin is required.', 'acrossai-abilities-manager' ),
			);
		}

		$resolved = Plugin_Helpers::resolve_plugin( $identifier );
		if ( null === $resolved['plugin_file'] || $resolved['certainty'] < 8.0 ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'plugin_not_found',
				'plugin_file'    => null,
				'plugin_name'    => null,
				'message'        => sprintf(
					/* translators: %s: input identifier */
					__( 'No plugin found matching "%s". Pass a more specific slug or the full plugin_file value.', 'acrossai-abilities-manager' ),
					$identifier
				),
			);
		}

		$plugin_file = (string) $resolved['plugin_file'];
		$plugin_name = (string) $resolved['plugin_name'];

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( is_plugin_active( $plugin_file ) ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'plugin_active',
				'plugin_file'    => $plugin_file,
				'plugin_name'    => $plugin_name,
				'message'        => sprintf(
					/* translators: %s: plugin name */
					__( 'Plugin "%s" is currently active. Deactivate it via plugins/deactivate-plugin before uninstalling.', 'acrossai-abilities-manager' ),
					$plugin_name
				),
			);
		}

		$result = uninstall_plugin( $plugin_file );

		if ( is_wp_error( $result ) ) {
			return array(
				'success'     => false,
				'uninstalled' => false,
				'plugin_file' => $plugin_file,
				'plugin_name' => $plugin_name,
				'message'     => sprintf(
					/* translators: 1: plugin name, 2: error message */
					__( 'Failed to uninstall "%1$s": %2$s', 'acrossai-abilities-manager' ),
					$plugin_name,
					$result->get_error_message()
				),
			);
		}

		// uninstall_plugin() returns true on hook-based path, void on the
		// uninstall.php-file path. Both count as success.
		return array(
			'success'     => true,
			'uninstalled' => true,
			'plugin_file' => $plugin_file,
			'plugin_name' => $plugin_name,
			'message'     => sprintf(
				/* translators: %s: plugin name */
				__( 'Uninstalled plugin "%s".', 'acrossai-abilities-manager' ),
				$plugin_name
			),
		);
	}
}
