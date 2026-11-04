<?php
/**
 * Update_Wp_Core ability (Feature 042).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Core
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Core;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Apply a WordPress core update via WP core's Core_Upgrader::upgrade().
 *
 * Same upgrader class the built-in WP dashboard uses; no custom download or
 * integrity checks. When called with no arguments, upgrades to the first
 * `response=upgrade` offer from get_core_updates() (the default WP admin
 * behaviour). Callers can pin to a specific `version` (+ optional `locale`)
 * to constrain the offer picked.
 */
class Update_Wp_Core extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'core/update-wp-core',
			'args' => array(
				'label'               => __( 'Update WordPress Core', 'acrossai-abilities-manager' ),
				'description'         => __( 'Apply the pending WordPress core update via WP core\'s Core_Upgrader. When called with no arguments, upgrades to the latest available offer. Provide "version" (+ optional "locale") to pin to a specific offer. Re-running when no update is available is a clean no-op. Honours DISALLOW_FILE_MODS.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-core',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' ) && current_user_can( 'update_core' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'version' => array(
							'type'        => 'string',
							'description' => __( 'Optional WordPress version to pin the upgrade to (e.g. "6.9.1"). When omitted, upgrades to the latest available offer.', 'acrossai-abilities-manager' ),
						),
						'locale'  => array(
							'type'        => 'string',
							'description' => __( 'Optional locale of the update offer to pin to (e.g. "en_US"). When omitted, defaults to the site\'s active locale from get_locale().', 'acrossai-abilities-manager' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'      => array( 'type' => 'boolean' ),
						'updated'      => array( 'type' => 'boolean' ),
						'from_version' => array( 'type' => 'string' ),
						'to_version'   => array( 'type' => 'string' ),
						'message'      => array( 'type' => 'string' ),
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
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	/**
	 * The pinning ability covers what this one cannot: making the version stick.
	 *
	 * Feature 136. `only_if_registered` keeps the hint out of the payload on sites where the
	 * WP Downgrade suite is not present, so a caller is never pointed at an ability that does not
	 * exist. Held back from the suggestion PR for exactly that reason — the target had to exist
	 * first.
	 *
	 * @since  0.0.45
	 * @return array<int,array<string,string|bool>>
	 */
	protected function suggested_abilities(): array {
		return array(
			array(
				'slug'               => 'versions/set-core-pin',
				'reason'             => __( 'To hold core on a specific version across future update checks rather than for this one run, pin it first.', 'acrossai-abilities-manager' ),
				'only_if_registered' => true,
			),
		);
	}

	/**
	 * WP Downgrade pins a core version; this ability does not.
	 *
	 * Feature 136. This ability pins one upgrade to a specific offer, but only to a version the Core API offers, and only for that one run.
	 *
	 * WP Downgrade does not itself move core. It filters `site_transient_update_core` so WordPress
	 * offers the pinned version, and the move happens on the next core update run — which is why
	 * the suggestion is about persistence, not about downgrading.
	 *
	 * `plugin_provides_abilities` is false: it is a settings-page plugin and defines no classes at
	 * all, so installing it gives an agent nothing until this plugin's own pinning abilities exist.
	 *
	 * @since  0.0.45
	 * @return array<int,array<string,scalar>>
	 */
	protected function suggested_plugins(): array {
		return array(
			array(
				'slug'                          => 'wp-downgrade',
				'name'                          => 'WP Downgrade | Specific Core Version',
				'reason'                        => __( 'This can pin a single upgrade to a specific offer, but only to a version the Core API offers and only for that run. WP Downgrade holds core on a chosen version across update checks.', 'acrossai-abilities-manager' ),
				'covers'                        => __( 'Pinning WordPress core to a chosen version so update checks keep offering it. Core only; it does not touch plugins or themes.', 'acrossai-abilities-manager' ),
				'plugin_provides_abilities'     => false,
				'acrossai_provides_integration' => false,
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

		if ( is_multisite() && ! current_user_can( 'update_core' ) ) {
			return array(
				'success' => false,
				'updated' => false,
				'message' => __( 'Core updates on multisite require the update_core capability at the network level.', 'acrossai-abilities-manager' ),
			);
		}

		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		if ( ! class_exists( '\Core_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		$from_version = (string) get_bloginfo( 'version' );

		$version = isset( $input['version'] ) ? sanitize_text_field( (string) $input['version'] ) : '';
		$locale  = isset( $input['locale'] ) ? sanitize_text_field( (string) $input['locale'] ) : '';

		if ( '' !== $version ) {
			$update = find_core_update( $version, '' !== $locale ? $locale : get_locale() );
		} else {
			$updates = get_core_updates();
			$update  = ( is_array( $updates ) && ! empty( $updates ) && is_object( $updates[0] ) ) ? $updates[0] : null;
		}

		if ( null === $update || false === $update ) {
			return array(
				'success'      => true,
				'updated'      => false,
				'from_version' => $from_version,
				'to_version'   => $from_version,
				'message'      => '' !== $version
					? sprintf(
						/* translators: %s: requested WordPress version */
						__( 'No core update offer found for version "%s".', 'acrossai-abilities-manager' ),
						$version
					)
					: __( 'No core update available.', 'acrossai-abilities-manager' ),
			);
		}

		$response = isset( $update->response ) ? (string) $update->response : '';
		if ( 'upgrade' !== $response ) {
			return array(
				'success'      => true,
				'updated'      => false,
				'from_version' => $from_version,
				'to_version'   => $from_version,
				'message'      => sprintf(
					/* translators: %s: WP update offer response value (e.g. "latest") */
					__( 'Core offer is not upgradable (response=%s).', 'acrossai-abilities-manager' ),
					'' !== $response ? $response : 'unknown'
				),
			);
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Core_Upgrader( $skin );
		$result   = $upgrader->upgrade( $update );

		$to_version = (string) get_bloginfo( 'version' );

		if ( is_wp_error( $result ) ) {
			return array(
				'success'      => true,
				'updated'      => false,
				'from_version' => $from_version,
				'to_version'   => $to_version,
				'message'      => $result->get_error_message(),
			);
		}

		if ( null === $result || false === $result ) {
			$skin_errors = $skin->get_errors();
			$msg         = is_wp_error( $skin_errors ) && $skin_errors->has_errors()
				? $skin_errors->get_error_message()
				: __( 'Core upgrade did not report success.', 'acrossai-abilities-manager' );
			return array(
				'success'      => true,
				'updated'      => false,
				'from_version' => $from_version,
				'to_version'   => $to_version,
				'message'      => $msg,
			);
		}

		return array(
			'success'      => true,
			'updated'      => true,
			'from_version' => $from_version,
			'to_version'   => $to_version,
			'message'      => sprintf(
				/* translators: 1: from version, 2: to version */
				__( 'WordPress upgraded from %1$s to %2$s.', 'acrossai-abilities-manager' ),
				$from_version,
				$to_version
			),
		);
	}
}
