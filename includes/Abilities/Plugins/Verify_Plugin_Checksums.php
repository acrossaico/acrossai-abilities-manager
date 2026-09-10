<?php
/**
 * Feature 064 — Verify a plugin's on-disk files against the WordPress.org
 * checksums manifest.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Plugins
 * @since      0.0.23
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Plugins;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Plugin_Helpers;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Verify a plugin's files against the WordPress.org checksums manifest.
 *
 * Fetches the expected manifest via wp_remote_get() against
 * https://api.wordpress.org/plugins/checksums/1.0/. For each entry in the
 * manifest, md5_file() the on-disk path and compare. Emits per-file
 * status: 'ok' | 'modified' | 'missing' | 'added' (added only when
 * strict:true and the file is present on disk but absent from the manifest).
 *
 * Plugins not hosted on WordPress.org will return an empty manifest — the
 * ability then reports success:true, results:[], message:'no_manifest' so
 * the caller learns the check cannot be performed.
 */
class Verify_Plugin_Checksums extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'plugins/verify-plugin-checksums',
			'args' => array(
				'label'               => __( 'Verify Plugin Checksums', 'acrossai-abilities-manager' ),
				'description'         => __( 'Verify an installed plugin\'s on-disk files against the official WordPress.org checksums manifest. Per-file status: ok / modified / missing / added (added only when strict:true). Plugins without a WP.org manifest report success:true with results:[] and message:"no_manifest".', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-plugins',
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
						'strict' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'required'             => array( 'plugin' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'plugin'  => array( 'type' => 'string' ),
						'version' => array( 'type' => 'string' ),
						'results' => array( 'type' => 'array' ),
						'summary' => array( 'type' => 'object' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'updates',
						'sub_group'       => 'integrity',
						'sub_group_label' => __( 'Integrity', 'acrossai-abilities-manager' ),
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
	 * @param array $input Ability input payload.
	 * @return array
	 */
	public function execute( array $input = array() ): array {
		$identifier = sanitize_text_field( (string) ( $input['plugin'] ?? '' ) );
		if ( '' === $identifier ) {
			return array(
				'success' => false,
				'message' => __( 'plugin is required.', 'acrossai-abilities-manager' ),
			);
		}
		$strict = ! empty( $input['strict'] );

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$resolved = Plugin_Helpers::resolve_plugin( $identifier );
		if ( null === $resolved['plugin_file'] || $resolved['certainty'] < 8.0 ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: input identifier */
					__( 'No plugin found matching "%s".', 'acrossai-abilities-manager' ),
					$identifier
				),
			);
		}

		$plugin_file = (string) $resolved['plugin_file'];
		$slug        = dirname( $plugin_file );
		if ( '.' === $slug || '' === $slug ) {
			// Single-file plugin at the root of WP_PLUGIN_DIR — no manifest exists.
			return array(
				'success' => true,
				'plugin'  => $plugin_file,
				'version' => '',
				'results' => array(),
				'summary' => array(
					'total'    => 0,
					'ok'       => 0,
					'modified' => 0,
					'missing'  => 0,
					'added'    => 0,
				),
				'message' => 'no_manifest',
			);
		}

		$plugin_data = Plugin_Helpers::get_plugin_by_slug( $plugin_file );
		$version     = null !== $plugin_data && isset( $plugin_data['version'] ) ? (string) $plugin_data['version'] : '';

		$manifest_url = 'https://api.wordpress.org/plugins/checksums/1.0/?plugin=' . rawurlencode( $slug ) . '&version=' . rawurlencode( $version );
		$response     = wp_remote_get( $manifest_url, array( 'timeout' => 15 ) );

		$empty_summary = array(
			'total'    => 0,
			'ok'       => 0,
			'modified' => 0,
			'missing'  => 0,
			'added'    => 0,
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array(
				'success' => true,
				'plugin'  => $slug,
				'version' => $version,
				'results' => array(),
				'summary' => $empty_summary,
				'message' => 'no_manifest',
			);
		}

		$body     = (string) wp_remote_retrieve_body( $response );
		$manifest = json_decode( $body, true );
		if ( ! is_array( $manifest ) || empty( $manifest['files'] ) || ! is_array( $manifest['files'] ) ) {
			return array(
				'success' => true,
				'plugin'  => $slug,
				'version' => $version,
				'results' => array(),
				'summary' => $empty_summary,
				'message' => 'no_manifest',
			);
		}

		$plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
		$results    = array();
		$summary    = $empty_summary;

		foreach ( $manifest['files'] as $file => $expected_data ) {
			$file          = (string) $file;
			$expected_hash = self::extract_expected_hash( $expected_data );
			$disk_path     = $plugin_dir . '/' . $file;

			if ( ! file_exists( $disk_path ) ) {
				$results[] = array(
					'file'     => $file,
					'expected' => $expected_hash,
					'actual'   => '',
					'status'   => 'missing',
				);
				++$summary['missing'];
				continue;
			}

			$actual_hash = (string) md5_file( $disk_path );
			$status      = hash_equals( $expected_hash, $actual_hash ) ? 'ok' : 'modified';

			$results[] = array(
				'file'     => $file,
				'expected' => $expected_hash,
				'actual'   => $actual_hash,
				'status'   => $status,
			);
			++$summary[ $status ];
		}

		if ( $strict && is_dir( $plugin_dir ) ) {
			$manifest_files = array_map( 'strval', array_keys( $manifest['files'] ) );
			$disk_files     = self::list_files_recursive( $plugin_dir );
			$added_files    = array_diff( $disk_files, $manifest_files );
			foreach ( $added_files as $file ) {
				$results[] = array(
					'file'     => $file,
					'expected' => '',
					'actual'   => (string) md5_file( $plugin_dir . '/' . $file ),
					'status'   => 'added',
				);
				++$summary['added'];
			}
		}

		$summary['total'] = count( $results );

		return array(
			'success' => true,
			'plugin'  => $slug,
			'version' => $version,
			'results' => $results,
			'summary' => $summary,
			'message' => sprintf(
				/* translators: 1: slug, 2: total */
				__( 'Verified %2$d file(s) for plugin "%1$s".', 'acrossai-abilities-manager' ),
				$slug,
				$summary['total']
			),
		);
	}

	/**
	 * Extract the expected MD5 hash from a manifest entry.
	 *
	 * The WP.org manifest ships each entry as either a scalar hash string or
	 * an array of the form { md5: '<hex>' } (older format).
	 *
	 * @param mixed $entry Manifest entry.
	 * @return string
	 */
	private static function extract_expected_hash( $entry ): string {
		if ( is_string( $entry ) ) {
			return $entry;
		}
		if ( is_array( $entry ) && isset( $entry['md5'] ) ) {
			return (string) $entry['md5'];
		}
		return '';
	}

	/**
	 * List every file under $dir as paths relative to $dir.
	 *
	 * @param string $dir Absolute directory path.
	 * @return string[]
	 */
	private static function list_files_recursive( string $dir ): array {
		if ( ! is_dir( $dir ) ) {
			return array();
		}
		$files = array();
		$iter  = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
		);
		$prefix_len = strlen( $dir ) + 1;
		foreach ( $iter as $item ) {
			if ( $item->isFile() ) {
				$files[] = substr( $item->getPathname(), $prefix_len );
			}
		}
		return $files;
	}
}
