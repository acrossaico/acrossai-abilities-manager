<?php
/**
 * Rollback_Wp_Core ability (Feature 043).
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
 * Roll back WordPress core to an earlier version.
 *
 * Fetches the list of offered versions from the WP.org Core API
 * (`https://api.wordpress.org/core/version-check/1.7/`) via wp_remote_get(),
 * picks the offer matching the requested `version`, and hands it directly to
 * WP core's `Core_Upgrader::upgrade()` — the same class the built-in "Update
 * WordPress" screen uses. `Core_Upgrader::upgrade($offer)` does not care
 * whether $offer->version is older than the currently-installed version; it
 * simply installs whatever the offer describes.
 *
 * Uses only WordPress functions — no bundled updater code, no custom download,
 * no custom integrity verification. Inspired by Andy Fragen's core-rollback
 * plugin (`https://github.com/afragen/core-rollback`) but skips the transient
 * / http_request_args dance that plugin needs to funnel through the
 * `update-core.php?action=do-core-reinstall` UI — we invoke Core_Upgrader
 * directly, so we can pass a hand-constructed offer without touching WP core's
 * upgrade transient.
 */
class Rollback_Wp_Core extends Ability_Definition {

	/**
	 * WP.org Core API endpoint (1.7).
	 */
	private const CORE_API_URL = 'https://api.wordpress.org/core/version-check/1.7/';

	/**
	 * Per-locale offer cache TTL. One day matches the reference core-rollback
	 * plugin and the WP.org API's own cache posture.
	 */
	private const OFFER_CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Site transient key prefix for the offer cache.
	 */
	private const OFFER_CACHE_PREFIX = 'acrossai_abilities_manager_core_offers_';

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'core/rollback-wp-core',
			'args' => array(
				'label'               => __( 'Rollback WordPress Core', 'acrossai-abilities-manager' ),
				'description'         => __( 'Roll back WordPress core to an earlier offered version via the WP.org Core API. Fetches the available offers from api.wordpress.org, picks the requested version, and hands the offer to Core_Upgrader::upgrade() — the same class the WP dashboard uses. Uses only WordPress functions; no bundled updater code. Refuses when the target version is equal to or newer than the currently-running version (use wp-core-update for upgrades). Honours DISALLOW_FILE_MODS.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-core',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' ) && current_user_can( 'update_core' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'version' => array(
							'type'        => 'string',
							'description' => __( 'Target WordPress version to roll back to (e.g. "6.8.1"). MUST be strictly older than the currently-installed version — use wp-core-update to move forward.', 'acrossai-abilities-manager' ),
						),
						'locale'  => array(
							'type'        => 'string',
							'description' => __( 'Optional locale for the WP.org Core API offer (e.g. "en_US"). Defaults to get_locale().', 'acrossai-abilities-manager' ),
						),
					),
					'required'             => array( 'version' ),
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
						'destructive' => true,
						'idempotent'  => false,
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

		if ( is_multisite() && ! current_user_can( 'update_core' ) ) {
			return array(
				'success' => false,
				'updated' => false,
				'message' => __( 'Core rollback on multisite requires the update_core capability at the network level.', 'acrossai-abilities-manager' ),
			);
		}

		$version = isset( $input['version'] ) ? sanitize_text_field( (string) $input['version'] ) : '';
		if ( '' === $version ) {
			return array(
				'success' => false,
				'updated' => false,
				'message' => __( 'The "version" input is required (e.g. "6.8.1").', 'acrossai-abilities-manager' ),
			);
		}

		$locale = isset( $input['locale'] ) ? sanitize_text_field( (string) $input['locale'] ) : '';
		if ( '' === $locale ) {
			$locale = get_locale();
		}

		$from_version = (string) get_bloginfo( 'version' );

		if ( version_compare( $version, $from_version, '>=' ) ) {
			return array(
				'success'      => false,
				'updated'      => false,
				'from_version' => $from_version,
				'to_version'   => $from_version,
				'message'      => sprintf(
					/* translators: 1: requested version, 2: current version */
					__( 'Target version %1$s is not older than current version %2$s. Use core/update-wp-core for upgrades.', 'acrossai-abilities-manager' ),
					$version,
					$from_version
				),
			);
		}

		$offer = $this->fetch_offer( $version, $locale );
		if ( is_wp_error( $offer ) ) {
			return array(
				'success'      => false,
				'updated'      => false,
				'from_version' => $from_version,
				'to_version'   => $from_version,
				'message'      => $offer->get_error_message(),
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

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Core_Upgrader( $skin );
		$result   = $upgrader->upgrade( $offer );

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
				: __( 'Core rollback did not report success.', 'acrossai-abilities-manager' );
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
				__( 'WordPress rolled back from %1$s to %2$s.', 'acrossai-abilities-manager' ),
				$from_version,
				$to_version
			),
		);
	}

	/**
	 * Fetch the WP.org Core API offer matching $version. Cached per-locale in
	 * a site transient with a day-long TTL (matches the reference
	 * core-rollback plugin's cache posture).
	 *
	 * The returned offer has its `response` field forced to `upgrade` so
	 * `Core_Upgrader::upgrade()` proceeds regardless of the original API
	 * marking (the API marks offered versions as `latest`; `Core_Upgrader`
	 * itself only inspects `->download` / `->packages` / `->version`).
	 *
	 * @return \stdClass|\WP_Error
	 */
	private function fetch_offer( string $version, string $locale ) {
		$cache_key = self::OFFER_CACHE_PREFIX . sanitize_key( $locale );
		$cached    = get_site_transient( $cache_key );

		if ( ! is_array( $cached ) || ! isset( $cached[ $version ] ) ) {
			$fetched = $this->fetch_all_offers( $locale );
			if ( is_wp_error( $fetched ) ) {
				return $fetched;
			}
			set_site_transient( $cache_key, $fetched, self::OFFER_CACHE_TTL );
			$cached = $fetched;
		}

		if ( ! isset( $cached[ $version ] ) ) {
			return new \WP_Error(
				'core_version_not_found',
				sprintf(
					/* translators: %s: requested WordPress version */
					__( 'WordPress %s was not found in the WP.org Core API offer list. Only versions the WP.org API still exposes can be installed.', 'acrossai-abilities-manager' ),
					$version
				)
			);
		}

		$offer = $cached[ $version ];

		if ( is_object( $offer ) ) {
			$offer->response = 'upgrade';
			if ( isset( $offer->version ) ) {
				$offer->current = $offer->version;
			}
		}

		return $offer;
	}

	/**
	 * Fetch every offer from the WP.org Core API for a given locale. Returns
	 * an array keyed by `version` string.
	 *
	 * @return array<string, \stdClass>|\WP_Error
	 */
	private function fetch_all_offers( string $locale ) {
		$url = self::CORE_API_URL . '?locale=' . rawurlencode( $locale );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error(
				'core_api_http_error',
				sprintf(
					/* translators: 1: HTTP code, 2: WP.org Core API URL */
					__( 'WP.org Core API returned HTTP %1$d for %2$s.', 'acrossai-abilities-manager' ),
					$code,
					$url
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );
		if ( ! is_object( $data ) || ! isset( $data->offers ) || ! is_array( $data->offers ) ) {
			return new \WP_Error(
				'core_api_malformed',
				__( 'WP.org Core API response is malformed (missing "offers" array).', 'acrossai-abilities-manager' )
			);
		}

		$offers = array();
		foreach ( $data->offers as $offer ) {
			if ( is_object( $offer ) && isset( $offer->version ) && version_compare( (string) $offer->version, '4.0', '>=' ) ) {
				$offers[ (string) $offer->version ] = $offer;
			}
		}

		if ( empty( $offers ) ) {
			return new \WP_Error(
				'core_api_no_offers',
				__( 'WP.org Core API returned no usable offers.', 'acrossai-abilities-manager' )
			);
		}

		return $offers;
	}
}
