<?php
/**
 * File-manager create-directory ability (Feature 090).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\FileManager
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\FileManager;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\File_Mods_Guard;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Wp_Filesystem_Init;

defined( 'ABSPATH' ) || exit;

/**
 * Create_Directory ability class.
 *
 * Create a directory under ABSPATH. Recursive by default (wp_mkdir_p);
 * pass recursive:false for a single-level mkdir. Idempotent — returns
 * success with created:false when the target already exists.
 */
class Create_Directory extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'file-manager/create-directory',
			'args' => array(
				'label'               => __( 'Create Directory', 'acrossai-abilities-manager' ),
				'description'         => __( 'Create a directory inside the WordPress installation. Path must be relative to ABSPATH. Default recursive:true uses wp_mkdir_p (creates any missing parents); recursive:false requires the parent to exist. Idempotent — returns success with created:false when the target already exists as a directory. Refuses when the target exists as a file.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'path'      => array(
							'type'        => 'string',
							'description' => __( 'Directory path relative to ABSPATH.', 'acrossai-abilities-manager' ),
						),
						'recursive' => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'When true (default), missing parent directories are created via wp_mkdir_p. When false, a single mkdir is used and refuses if the parent does not exist.', 'acrossai-abilities-manager' ),
						),
					),
					'required'             => array( 'path' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'        => array( 'type' => 'boolean' ),
						'path'           => array( 'type' => 'string' ),
						'created'        => array( 'type' => 'boolean' ),
						'message'        => array( 'type' => 'string' ),
						'blocked_reason' => array( 'type' => 'string' ),
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
		$blocked = File_Mods_Guard::blocked_response();
		if ( null !== $blocked ) {
			return $blocked;
		}
		$blocked = Wp_Filesystem_Init::blocked_response();
		if ( null !== $blocked ) {
			return $blocked;
		}
		$fs = Wp_Filesystem_Init::get();

		$rel_path  = sanitize_text_field( $input['path'] ?? '' );
		$recursive = array_key_exists( 'recursive', $input ) ? (bool) $input['recursive'] : true;

		$base = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$abs  = $base . '/' . ltrim( $rel_path, '/' );

		// If the target already exists as a directory, treat as idempotent success.
		if ( $fs->is_dir( $abs ) ) {
			$real = realpath( $abs );
			if ( false === $real || ( $real !== $base && 0 !== strpos( $real, $base . '/' ) ) ) {
				return array(
					'success'        => false,
					'blocked_reason' => 'invalid_path',
					'message'        => __( 'Invalid or disallowed directory path.', 'acrossai-abilities-manager' ),
				);
			}
			return array(
				'success' => true,
				'path'    => $real,
				'created' => false,
				'message' => __( 'Directory already exists.', 'acrossai-abilities-manager' ),
			);
		}

		if ( $fs->exists( $abs ) ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'path_is_file',
				'message'        => __( 'Path exists as a file; cannot create a directory here.', 'acrossai-abilities-manager' ),
			);
		}

		// Parent-scope check for the (not-yet-created) target.
		if ( $recursive ) {
			// wp_mkdir_p handles missing parents; we still need to bound the
			// requested path itself to ABSPATH before calling it.
			if ( 0 !== strpos( $abs, $base . '/' ) ) {
				return array(
					'success'        => false,
					'blocked_reason' => 'invalid_path',
					'message'        => __( 'Invalid or disallowed directory path.', 'acrossai-abilities-manager' ),
				);
			}
			if ( ! wp_mkdir_p( $abs ) ) {
				return array(
					'success' => false,
					'message' => __( 'Could not create directory.', 'acrossai-abilities-manager' ),
				);
			}
		} else {
			$parent = realpath( dirname( $abs ) );
			if ( false === $parent ) {
				return array(
					'success'        => false,
					'blocked_reason' => 'parent_missing',
					'message'        => __( 'Parent directory does not exist. Pass recursive:true to create it.', 'acrossai-abilities-manager' ),
				);
			}
			if ( $parent !== $base && 0 !== strpos( $parent, $base . '/' ) ) {
				return array(
					'success'        => false,
					'blocked_reason' => 'invalid_path',
					'message'        => __( 'Invalid or disallowed directory path.', 'acrossai-abilities-manager' ),
				);
			}
			if ( ! $fs->mkdir( $abs ) ) {
				return array(
					'success' => false,
					'message' => __( 'Could not create directory.', 'acrossai-abilities-manager' ),
				);
			}
		}

		return array(
			'success' => true,
			'path'    => realpath( $abs ) ?: $abs,
			'created' => true,
			'message' => __( 'Directory created.', 'acrossai-abilities-manager' ),
		);
	}
}
