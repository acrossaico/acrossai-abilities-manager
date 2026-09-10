<?php
/**
 * Feature 055 — per-plugin lifecycle-context envelope.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Plugins
 * @since      0.0.13
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Plugins;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Lifecycle_Event_Log;

defined( 'ABSPATH' ) || exit;

/**
 * Return the lifecycle-context object for a single plugin: header, active
 * state, autoupdate enrolment, update availability, and last activated /
 * deactivated / updated timestamps from the option-backed event log.
 */
class Get_Plugin_Lifecycle_Context extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array<string,mixed>
	 */
	protected function ability(): array {
		return array(
			'name' => 'plugins/get-plugin-lifecycle-context',
			'args' => array(
				'label'               => __( 'Get Plugin Lifecycle Context', 'acrossai-abilities-manager' ),
				'description'         => __( 'Return the lifecycle-context envelope for a single plugin: header (name, version, author, description), active state, network-active state, autoupdate enrolment, update availability, and the last activated / deactivated / updated timestamps recorded since 0.0.13.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-plugins',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'plugin' => array(
							'type'      => 'string',
							'minLength' => 1,
						),
					),
					'required'             => array( 'plugin' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'             => array( 'type' => 'boolean' ),
						'basename'            => array( 'type' => 'string' ),
						'header'              => array( 'type' => 'object' ),
						'is_active'           => array( 'type' => 'boolean' ),
						'is_network_active'   => array( 'type' => 'boolean' ),
						'autoupdate_enabled'  => array( 'type' => 'boolean' ),
						'update_available'    => array( 'type' => 'boolean' ),
						'last_activated_at'   => array( 'type' => 'integer' ),
						'last_deactivated_at' => array( 'type' => 'integer' ),
						'last_updated_at'     => array( 'type' => 'integer' ),
						'message'             => array( 'type' => 'string' ),
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
						'readonly'    => true,
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
	 * @param array<string,mixed> $input Ability input payload.
	 * @return array<string,mixed>
	 */
	public function execute( array $input = array() ): array {
		// Feature 055 hardening — reject basenames containing path traversal
		// or absolute paths; only match /^[A-Za-z0-9_\-\/\.]+$/ shape.
		$basename = ltrim( (string) ( $input['plugin'] ?? '' ), '/' );
		if ( '' === $basename
			|| str_contains( $basename, '..' )
			|| str_contains( $basename, '\\' )
			|| ! preg_match( '/^[A-Za-z0-9_\-\/\.]+$/', $basename ) ) {
			return array(
				'success' => false,
				'message' => __( 'A valid plugin basename is required (e.g. "hello-dolly/hello.php").', 'acrossai-abilities-manager' ),
			);
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';

		$all_plugins = get_plugins();
		if ( ! isset( $all_plugins[ $basename ] ) ) {
			return array(
				'success' => false,
				/* translators: %s: plugin basename */
				'message' => sprintf( __( 'Plugin "%s" not installed.', 'acrossai-abilities-manager' ), $basename ),
			);
		}

		$header               = (array) $all_plugins[ $basename ];
		$is_active            = is_plugin_active( $basename );
		$is_network_active    = is_multisite() && is_plugin_active_for_network( $basename );
		$autoupdates          = (array) get_site_option( 'auto_update_plugins', array() );
		$autoupdate_enabled   = in_array( $basename, $autoupdates, true );

		$updates          = (array) get_plugin_updates();
		$update_available = isset( $updates[ $basename ] );

		$summary = Lifecycle_Event_Log::get_summary( 'plugin', $basename );

		// Feature 055 hardening — cap free-form header fields so a plugin
		// with a malicious multi-KB Description cannot bloat responses.
		$name_max = 200;
		$auth_max = 200;
		$desc_max = 500;
		$name     = wp_strip_all_tags( (string) ( $header['Name'] ?? '' ) );
		$author   = wp_strip_all_tags( (string) ( $header['Author'] ?? '' ) );
		$descr    = wp_strip_all_tags( (string) ( $header['Description'] ?? '' ) );
		if ( strlen( $name ) > $name_max ) {
			$name = rtrim( substr( $name, 0, $name_max ) ) . '...';
		}
		if ( strlen( $author ) > $auth_max ) {
			$author = rtrim( substr( $author, 0, $auth_max ) ) . '...';
		}
		if ( strlen( $descr ) > $desc_max ) {
			$descr = rtrim( substr( $descr, 0, $desc_max ) ) . '...';
		}

		return array(
			'success'             => true,
			'basename'            => $basename,
			'header'              => (object) array(
				'name'         => $name,
				'version'      => sanitize_text_field( (string) ( $header['Version'] ?? '' ) ),
				'author'       => $author,
				'description'  => $descr,
				'requires_wp'  => sanitize_text_field( (string) ( $header['RequiresWP'] ?? '' ) ),
				'requires_php' => sanitize_text_field( (string) ( $header['RequiresPHP'] ?? '' ) ),
			),
			'is_active'           => (bool) $is_active,
			'is_network_active'   => (bool) $is_network_active,
			'autoupdate_enabled'  => (bool) $autoupdate_enabled,
			'update_available'    => (bool) $update_available,
			'last_activated_at'   => (int) $summary['last_activated_at'],
			'last_deactivated_at' => (int) $summary['last_deactivated_at'],
			'last_updated_at'     => (int) $summary['last_updated_at'],
			/* translators: %s: plugin basename */
			'message'             => sprintf( __( 'Lifecycle for %s.', 'acrossai-abilities-manager' ), $basename ),
		);
	}
}
