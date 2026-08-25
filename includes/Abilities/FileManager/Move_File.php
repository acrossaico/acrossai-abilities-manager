<?php
/**
 * File-manager move-file ability (Feature 089).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\FileManager
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\FileManager;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\File_Mods_Guard;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Hardening_Enforcer;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Path_Allowlist_Guard;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Wp_Filesystem_Init;

defined( 'ABSPATH' ) || exit;

/**
 * Move_File ability class.
 *
 * Rename a file between two paths under ABSPATH. Refuses when destination
 * exists (unless overwrite:true). Refuses when EITHER source OR destination
 * resolves to wp-config.php or .htaccess at ABSPATH root even with
 * overwrite:true.
 */
class Move_File extends Ability_Definition {

	/**
	 * Filenames refused as source or destination.
	 * Same list as Read_File::PROTECTED_FILES and Delete_File::PROTECTED_FILES.
	 *
	 * @var array<int,string>
	 */
	private const PROTECTED_FILES = array(
		'wp-config.php',
		'.htaccess',
	);

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'file-manager/move-file',
			'args' => array(
				'label'               => __( 'Move File', 'acrossai-abilities-manager' ),
				'description'         => __( 'Move a file from a source path to a destination path within the WordPress installation. Both paths must remain inside ABSPATH. Default refuses when the destination exists; pass overwrite:true to replace it. Refuses when either source or destination resolves to wp-config.php or .htaccess even with overwrite:true.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'source'      => array(
							'type'        => 'string',
							'description' => __( 'Source file path relative to ABSPATH. Must exist.', 'acrossai-abilities-manager' ),
						),
						'destination' => array(
							'type'        => 'string',
							'description' => __( 'Destination file path relative to ABSPATH.', 'acrossai-abilities-manager' ),
						),
						'overwrite'   => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'When true, an existing destination file is replaced. wp-config.php and .htaccess are still refused.', 'acrossai-abilities-manager' ),
						),
					),
					'required'             => array( 'source', 'destination' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'        => array( 'type' => 'boolean' ),
						'source'         => array( 'type' => 'string' ),
						'destination'    => array( 'type' => 'string' ),
						'overwritten'    => array( 'type' => 'boolean' ),
						'message'        => array( 'type' => 'string' ),
						'blocked_reason' => array( 'type' => 'string' ),
						'allowed_roots'  => array( 'type' => 'array' ),
						'path'           => array( 'type' => 'string' ),
						// Feature 093 context fields.
						'extension'      => array( 'type' => 'string' ),
						'basename'       => array( 'type' => 'string' ),
						'directive'      => array( 'type' => 'string' ),
						'input'          => array( 'type' => 'string' ),
						'sanitized'      => array( 'type' => 'string' ),
						'size'           => array( 'type' => 'integer' ),
						'max_bytes'      => array( 'type' => 'integer' ),
						'marker'         => array( 'type' => 'string' ),
					),
					'required'             => array( 'success', 'message' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'file-manager',
						'sub_group'       => 'files',
						'sub_group_label' => __( 'Files', 'acrossai-abilities-manager' ),
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
		$blocked = File_Mods_Guard::blocked_response();
		if ( null !== $blocked ) {
			return $blocked;
		}
		$blocked = Wp_Filesystem_Init::blocked_response();
		if ( null !== $blocked ) {
			return $blocked;
		}
		$fs = Wp_Filesystem_Init::get();

		$rel_source  = sanitize_text_field( $input['source'] ?? '' );
		$rel_dest    = sanitize_text_field( $input['destination'] ?? '' );
		$overwrite   = ! empty( $input['overwrite'] );

		$base        = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$src_real    = realpath( $base . '/' . ltrim( $rel_source, '/' ) );
		$dest_abs    = $base . '/' . ltrim( $rel_dest, '/' );
		$dest_parent = realpath( dirname( $dest_abs ) );

		if ( false === $src_real || 0 !== strpos( $src_real, $base . '/' ) ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'invalid_path',
				'message'        => __( 'Source path is invalid or outside ABSPATH.', 'acrossai-abilities-manager' ),
			);
		}

		if ( ! $fs->is_file( $src_real ) ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'source_not_found',
				'message'        => __( 'Source file does not exist or is not a regular file.', 'acrossai-abilities-manager' ),
			);
		}

		// Protected-source guard: refuse to move wp-config.php or .htaccess out.
		$src_basename = basename( $src_real );
		if ( in_array( $src_basename, self::PROTECTED_FILES, true ) && dirname( $src_real ) === $base ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'protected_write',
				/* translators: %s: filename */
				'message'        => sprintf( __( 'File "%s" is protected and cannot be moved.', 'acrossai-abilities-manager' ), $src_basename ),
			);
		}

		if ( false === $dest_parent || ( $dest_parent !== $base && 0 !== strpos( $dest_parent, $base . '/' ) ) ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'invalid_path',
				'message'        => __( 'Destination path is invalid or outside ABSPATH.', 'acrossai-abilities-manager' ),
			);
		}

		$dest_basename = basename( $dest_abs );
		if ( in_array( $dest_basename, self::PROTECTED_FILES, true ) && $dest_parent === $base ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'protected_write',
				/* translators: %s: filename */
				'message'        => sprintf( __( 'File "%s" is protected and cannot be written.', 'acrossai-abilities-manager' ), $dest_basename ),
			);
		}

		$overwritten = false;
		if ( $fs->exists( $dest_abs ) ) {
			if ( ! $overwrite ) {
				return array(
					'success'        => false,
					'blocked_reason' => 'destination_exists',
					'message'        => __( 'Destination already exists. Pass overwrite:true to replace it.', 'acrossai-abilities-manager' ),
				);
			}
			$overwritten = true;
		}

		// Feature 092: admin-controlled write allowlist gate — both endpoints.
		$blocked = Path_Allowlist_Guard::blocked_write_response( $src_real );
		if ( null !== $blocked ) {
			return $blocked;
		}
		$blocked = Path_Allowlist_Guard::blocked_write_response( $dest_abs );
		if ( null !== $blocked ) {
			return $blocked;
		}

		// Feature 093: content-filter enforcement — checks apply to the
		// DESTINATION basename; source_size + lazy content reader mirror
		// copy-file semantics.
		$blocked = Hardening_Enforcer::check_write(
			$src_real,
			'',
			array(
				'mode'                     => 'move',
				'target_basename_override' => basename( $dest_abs ),
				'source_size'              => (int) $fs->size( $src_real ),
				'source_content_reader'    => static function () use ( $fs, $src_real ) {
					$bytes = $fs->get_contents( $src_real );
					return is_string( $bytes ) ? $bytes : '';
				},
			)
		);
		if ( null !== $blocked ) {
			return $blocked;
		}

		if ( ! $fs->move( $src_real, $dest_abs, $overwrite ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not move file.', 'acrossai-abilities-manager' ),
			);
		}

		return array(
			'success'     => true,
			'source'      => $src_real,
			'destination' => realpath( $dest_abs ) ?: $dest_abs,
			'overwritten' => $overwritten,
			'message'     => $overwritten
				? __( 'File moved (destination replaced).', 'acrossai-abilities-manager' )
				: __( 'File moved.', 'acrossai-abilities-manager' ),
		);
	}
}
