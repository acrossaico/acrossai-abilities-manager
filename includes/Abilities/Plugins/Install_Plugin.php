<?php
/**
 * Absorbed ability class scaffolded from acrossai-core-abilities (Feature 046).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Plugins
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Plugins;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\File_Mods_Guard;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Plugin_Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Install_Plugin ability class (absorbed).
 */
class Install_Plugin extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'plugins/install-plugin',
			'args' => array(
				'label'               => __( 'Install Plugin', 'acrossai-abilities-manager' ),
				'description'         => __( 'Install a plugin from the WordPress.org plugin directory by name or slug.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-plugins',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'plugin'   => array(
							'type'        => 'string',
							'description' => __( 'The plugin name or slug to install from WordPress.org.', 'acrossai-abilities-manager' ),
						),
						'slug'     => array(
							'type'        => 'string',
							'description' => __( 'Alias for "plugin". If both are provided, "plugin" wins.', 'acrossai-abilities-manager' ),
						),
						'activate' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether to activate the plugin after installing.', 'acrossai-abilities-manager' ),
							'default'     => false,
						),
					),
					'anyOf'                => array(
						array( 'required' => array( 'plugin' ) ),
						array( 'required' => array( 'slug' ) ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'message'     => array( 'type' => 'string' ),
						'plugin_name' => array( 'type' => 'string' ),
						'plugin_slug' => array( 'type' => 'string' ),
						'activated'   => array( 'type' => 'boolean' ),
					),
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
						'destructive' => false,
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
			return $blocked;
		}

		$raw_plugin = ! empty( $input['plugin'] ) ? $input['plugin'] : ( $input['slug'] ?? '' );

		if ( empty( $raw_plugin ) ) {
			return array(
				'success' => false,
				'message' => __( 'No plugin specified. Pass "plugin" (or its alias "slug").', 'acrossai-abilities-manager' ),
			);
		}

		$plugin_slug = sanitize_text_field( $raw_plugin );
		$activate    = ! empty( $input['activate'] );

		$plugin_slug = sanitize_title( $plugin_slug );

		if ( '' === $plugin_slug ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid plugin slug.', 'acrossai-abilities-manager' ),
			);
		}

		// Check if plugin is already installed.
		$resolved = Plugin_Helpers::resolve_plugin( $plugin_slug );
		if ( null !== $resolved['plugin_file'] && $resolved['certainty'] >= 8.0 ) {
			$plugin_data = Plugin_Helpers::get_plugin_by_slug( $resolved['plugin_file'] );
			$status      = $plugin_data && $plugin_data['active'] ? __( 'active', 'acrossai-abilities-manager' ) : __( 'inactive', 'acrossai-abilities-manager' );

			return array(
				'success'           => true,
				/* translators: 1: plugin name, 2: plugin status */
				'message'           => sprintf( __( 'Plugin "%1$s" is already installed (%2$s).', 'acrossai-abilities-manager' ), $resolved['plugin_name'], $status ),
				'already_installed' => true,
				'plugin_name'       => $resolved['plugin_name'],
				'plugin_slug'       => $resolved['plugin_file'],
				'active'            => $plugin_data && $plugin_data['active'],
			);
		}

		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}
		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $plugin_slug,
				'fields' => array(
					'short_description' => true,
					'sections'          => false,
					'requires'          => true,
					'tested'            => true,
					'rating'            => false,
					'downloaded'        => false,
					'download_link'     => true,
					'last_updated'      => false,
					'homepage'          => false,
					'tags'              => false,
				),
			)
		);

		if ( is_wp_error( $api ) ) {
			return array(
				'success' => false,
				/* translators: 1: plugin slug, 2: error message */
				'message' => sprintf( __( 'Could not find plugin "%1$s" on WordPress.org: %2$s', 'acrossai-abilities-manager' ), $plugin_slug, $api->get_error_message() ),
			);
		}

		if ( empty( $api->download_link ) ) {
			return array(
				'success' => false,
				/* translators: %s: plugin name */
				'message' => sprintf( __( 'No download link available for "%s".', 'acrossai-abilities-manager' ), $api->name ?? $plugin_slug ),
			);
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				/* translators: 1: plugin name, 2: error message */
				'message' => sprintf( __( 'Failed to install "%1$s": %2$s', 'acrossai-abilities-manager' ), $api->name, $result->get_error_message() ),
			);
		}

		if ( true !== $result ) {
			$errors    = $skin->get_errors();
			$feedback  = $skin->get_upgrade_messages();
			$error_msg = '';

			if ( is_wp_error( $errors ) && $errors->has_errors() ) {
				$error_msg = $errors->get_error_message();
			} elseif ( ! empty( $feedback ) ) {
				$error_msg = implode( ' ', $feedback );
			} else {
				$error_msg = __( 'Unknown error during installation.', 'acrossai-abilities-manager' );
			}

			return array(
				'success' => false,
				/* translators: 1: plugin name, 2: error message */
				'message' => sprintf( __( 'Failed to install "%1$s": %2$s', 'acrossai-abilities-manager' ), $api->name, $error_msg ),
			);
		}

		$activated = false;

		if ( $activate ) {
			wp_clean_plugins_cache();
			$installed = Plugin_Helpers::resolve_plugin( $plugin_slug );
			if ( null !== $installed['plugin_file'] ) {
				$activate_result = activate_plugin( $installed['plugin_file'] );
				$activated       = ! is_wp_error( $activate_result );
			}
		}

		if ( $activate && $activated ) {
			/* translators: %s: plugin name */
			$message = sprintf( __( 'Plugin "%s" has been installed and activated successfully.', 'acrossai-abilities-manager' ), $api->name );
		} elseif ( $activate && ! $activated ) {
			/* translators: %s: plugin name */
			$message = sprintf( __( 'Plugin "%s" was installed but could not be activated.', 'acrossai-abilities-manager' ), $api->name );
		} else {
			/* translators: %s: plugin name */
			$message = sprintf( __( 'Plugin "%s" has been installed successfully.', 'acrossai-abilities-manager' ), $api->name );
		}

		return array(
			'success'     => true,
			'message'     => $message,
			'plugin_name' => $api->name,
			'plugin_slug' => $plugin_slug,
			'activated'   => $activated,
		);
	}
}
