<?php
/**
 * REST controller for the File Manager settings tab (Feature 092).
 *
 * Exposes three GET/POST pairs under acrossai/v1:
 *
 *   GET/POST /file-manager-settings/write-allowlist
 *   GET/POST /file-manager-settings/read-allowlist
 *   GET/POST /file-manager-settings/redaction
 *
 * All routes require manage_options + a valid X-WP-Nonce; both checks are
 * delegated to AcrossAI_Abilities_Rest_Controller::check_permission() so
 * the security contract stays in one place.
 *
 * The two GET allowlist routes include enumeration data (core dirs under
 * ABSPATH, installed plugins via get_plugins(), installed themes via
 * wp_get_themes()) alongside the current allowlist so the React admin UI
 * can render the folder picker without a second round-trip.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Rest
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Rest;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Rest\AcrossAI_Abilities_Rest_Controller;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Path_Allowlist_Guard;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Secret_Redactor;

defined( 'ABSPATH' ) || exit;

/**
 * File_Manager_Settings_Controller.
 */
final class File_Manager_Settings_Controller {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Get the singleton.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Base URL fragment for this feature's routes.
	 */
	public const REST_BASE = 'file-manager-settings';

	/**
	 * Register routes on rest_api_init.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$permission = array( AcrossAI_Abilities_Rest_Controller::instance(), 'check_permission' );

		register_rest_route(
			AcrossAI_Abilities_Rest_Controller::REST_NAMESPACE,
			'/' . self::REST_BASE . '/write-allowlist',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_write_allowlist' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_write_allowlist' ),
					'permission_callback' => $permission,
					'args'                => array(
						'allowed_paths' => array(
							'type'     => 'array',
							'required' => true,
							'items'    => array( 'type' => 'string' ),
						),
					),
				),
			)
		);

		register_rest_route(
			AcrossAI_Abilities_Rest_Controller::REST_NAMESPACE,
			'/' . self::REST_BASE . '/read-allowlist',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_read_allowlist' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_read_allowlist' ),
					'permission_callback' => $permission,
					'args'                => array(
						'allowed_paths' => array(
							'type'     => 'array',
							'required' => true,
							'items'    => array( 'type' => 'string' ),
						),
					),
				),
			)
		);

		register_rest_route(
			AcrossAI_Abilities_Rest_Controller::REST_NAMESPACE,
			'/' . self::REST_BASE . '/redaction',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_redaction' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_redaction' ),
					'permission_callback' => $permission,
					'args'                => array(
						'patterns'        => array( 'type' => 'object' ),
						'custom_literals' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
			)
		);
	}

	/* -------------------------------------------------------------------- */
	/* Write allowlist                                                       */
	/* -------------------------------------------------------------------- */

	/**
	 * GET /file-manager-settings/write-allowlist
	 *
	 * @return \WP_REST_Response
	 */
	public function get_write_allowlist(): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'allowed_paths' => Path_Allowlist_Guard::get_write_paths(),
				'available'     => $this->build_available(),
			),
			200
		);
	}

	/**
	 * POST /file-manager-settings/write-allowlist
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_write_allowlist( \WP_REST_Request $request ) {
		$raw = (array) $request->get_param( 'allowed_paths' );
		Path_Allowlist_Guard::set_write_paths( $raw );
		return $this->get_write_allowlist();
	}

	/* -------------------------------------------------------------------- */
	/* Read allowlist                                                        */
	/* -------------------------------------------------------------------- */

	/**
	 * GET /file-manager-settings/read-allowlist
	 *
	 * @return \WP_REST_Response
	 */
	public function get_read_allowlist(): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'allowed_paths' => Path_Allowlist_Guard::get_read_paths(),
				'available'     => $this->build_available(),
			),
			200
		);
	}

	/**
	 * POST /file-manager-settings/read-allowlist
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_read_allowlist( \WP_REST_Request $request ) {
		$raw = (array) $request->get_param( 'allowed_paths' );
		Path_Allowlist_Guard::set_read_paths( $raw );
		return $this->get_read_allowlist();
	}

	/* -------------------------------------------------------------------- */
	/* Redaction                                                             */
	/* -------------------------------------------------------------------- */

	/**
	 * GET /file-manager-settings/redaction
	 *
	 * @return \WP_REST_Response
	 */
	public function get_redaction(): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'config'    => Secret_Redactor::get_config(),
				'available' => Secret_Redactor::available_patterns(),
			),
			200
		);
	}

	/**
	 * POST /file-manager-settings/redaction
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_redaction( \WP_REST_Request $request ) {
		$patterns = (array) $request->get_param( 'patterns' );
		$literals = (array) $request->get_param( 'custom_literals' );
		Secret_Redactor::set_config(
			array(
				'patterns'        => $patterns,
				'custom_literals' => $literals,
			)
		);
		return $this->get_redaction();
	}

	/* -------------------------------------------------------------------- */
	/* Enumeration                                                           */
	/* -------------------------------------------------------------------- */

	/**
	 * Build the enumeration payload the React admin UI needs to render the
	 * folder tree + plugin picker + theme picker.
	 *
	 * @return array{core: array<int,string>, plugins: array<int,array{slug:string,name:string}>, themes: array<int,array{stylesheet:string,name:string}>}
	 */
	private function build_available(): array {
		return array(
			'core'    => $this->enumerate_core_dirs(),
			'plugins' => $this->enumerate_plugins(),
			'themes'  => $this->enumerate_themes(),
		);
	}

	/**
	 * List immediate directories under ABSPATH.
	 *
	 * Filters out non-directories and dot entries. Also returns the well-known
	 * wp-content immediate children (plugins, themes, uploads, mu-plugins,
	 * languages, upgrade) so the UI can render a two-level tree without a
	 * second call.
	 *
	 * @return array<int,array{path:string,children:array<int,string>}>
	 */
	private function enumerate_core_dirs(): array {
		$base = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$out  = array();

		$entries = @scandir( $base ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $entries ) {
			return $out;
		}
		sort( $entries );

		foreach ( $entries as $entry ) {
			if ( '' === $entry || '.' === $entry[0] ) {
				continue;
			}
			$full = $base . '/' . $entry;
			if ( ! is_dir( $full ) ) {
				continue;
			}
			$children = array();
			if ( 'wp-content' === $entry ) {
				$sub_entries = @scandir( $full ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( false !== $sub_entries ) {
					sort( $sub_entries );
					foreach ( $sub_entries as $sub ) {
						if ( '' === $sub || '.' === $sub[0] ) {
							continue;
						}
						if ( is_dir( $full . '/' . $sub ) ) {
							$children[] = 'wp-content/' . $sub;
						}
					}
				}
			}
			$out[] = array(
				'path'     => $entry,
				'children' => $children,
			);
		}

		return $out;
	}

	/**
	 * List installed plugins as {slug, name} pairs. Slug is the plugin
	 * directory (or file basename for single-file plugins).
	 *
	 * @return array<int,array{slug:string,name:string}>
	 */
	private function enumerate_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$out     = array();
		foreach ( $plugins as $file => $data ) {
			$slug  = str_contains( $file, '/' ) ? substr( $file, 0, strpos( $file, '/' ) ) : $file;
			$name  = isset( $data['Name'] ) ? (string) $data['Name'] : $slug;
			$out[] = array(
				'slug' => $slug,
				'name' => $name,
			);
		}
		usort(
			$out,
			static function ( array $a, array $b ) {
				return strcasecmp( (string) $a['name'], (string) $b['name'] );
			}
		);
		return $out;
	}

	/**
	 * List installed themes as {stylesheet, name} pairs.
	 *
	 * @return array<int,array{stylesheet:string,name:string}>
	 */
	private function enumerate_themes(): array {
		if ( ! function_exists( 'wp_get_themes' ) ) {
			return array();
		}
		$themes = wp_get_themes();
		$out    = array();
		foreach ( $themes as $stylesheet => $theme ) {
			$out[] = array(
				'stylesheet' => (string) $stylesheet,
				'name'       => (string) $theme->get( 'Name' ),
			);
		}
		usort(
			$out,
			static function ( array $a, array $b ) {
				return strcasecmp( (string) $a['name'], (string) $b['name'] );
			}
		);
		return $out;
	}
}
