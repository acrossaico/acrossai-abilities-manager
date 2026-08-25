<?php
/**
 * Absorbed ability class scaffolded from acrossai-core-abilities (Feature 046).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\FileManager
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\FileManager;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\File_Mods_Guard;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Path_Allowlist_Guard;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Wp_Filesystem_Init;

defined( 'ABSPATH' ) || exit;

/**
 * Create_File ability class (absorbed).
 */
class Create_File extends Ability_Definition {

	/**
	 * Filenames refused as targets even when they don't yet exist.
	 * Same list as Read_File::PROTECTED_FILES and Delete_File::PROTECTED_FILES.
	 * Added by Feature 089 to close a guard-gap where the generic create
	 * path could write wp-config.php or .htaccess while the read/delete
	 * paths already refused them.
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
			'name' => 'file-manager/create-file',
			'args' => array(
				'label'               => __( 'Create File', 'acrossai-abilities-manager' ),
				'description'         => __( 'Creates a new file within the WordPress installation. Fails if the file already exists. Path must be relative to ABSPATH. Refuses wp-config.php and .htaccess at ABSPATH root. Pass create_dirs=true to auto-create any missing parent directories.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'path'        => array(
							'type'        => 'string',
							'description' => __( 'File path relative to ABSPATH.', 'acrossai-abilities-manager' ),
						),
						'content'     => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Initial file content.', 'acrossai-abilities-manager' ),
						),
						'create_dirs' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'If true, missing parent directories are created (wp_mkdir_p) before the file is written. Defaults to false.', 'acrossai-abilities-manager' ),
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

		$rel_path    = sanitize_text_field( $input['path'] ?? '' );
		$content     = $input['content'] ?? '';
		$create_dirs = ! empty( $input['create_dirs'] );
		$base        = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$abs_path    = $base . '/' . ltrim( $rel_path, '/' );
		$parent_want = dirname( $abs_path );
		$parent      = realpath( $parent_want );

		if ( false === $parent ) {
			if ( ! $create_dirs ) {
				return array(
					'success' => false,
					'message' => __( 'Parent directory does not exist. Pass create_dirs=true to create it.', 'acrossai-abilities-manager' ),
				);
			}
			if ( 0 !== strpos( $parent_want, $base . '/' ) ) {
				return array(
					'success' => false,
					'message' => __( 'Invalid or disallowed file path.', 'acrossai-abilities-manager' ),
				);
			}
			if ( ! wp_mkdir_p( $parent_want ) ) {
				return array(
					'success' => false,
					'message' => __( 'Could not create parent directories.', 'acrossai-abilities-manager' ),
				);
			}
			$parent = realpath( $parent_want );
		}

		if ( false === $parent || 0 !== strpos( $parent, $base . '/' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid or disallowed file path.', 'acrossai-abilities-manager' ),
			);
		}

		// Protected-file guard: refuse to write secret-holding files at
		// ABSPATH root regardless of the caller's capability. Mirrors
		// Read_File / Delete_File / Edit_File / Copy_File / Move_File.
		$target_basename = basename( $abs_path );
		if ( in_array( $target_basename, self::PROTECTED_FILES, true ) && $parent === $base ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'protected_write',
				/* translators: %s: filename */
				'message'        => sprintf( __( 'File "%s" is protected and cannot be created.', 'acrossai-abilities-manager' ), $target_basename ),
			);
		}

		if ( $fs->exists( $abs_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'File already exists. Use file-edit to overwrite.', 'acrossai-abilities-manager' ),
			);
		}

		// Feature 092: admin-controlled write allowlist gate.
		$blocked = Path_Allowlist_Guard::blocked_write_response( $abs_path );
		if ( null !== $blocked ) {
			return $blocked;
		}

		$result = $fs->put_contents( $abs_path, $content, FS_CHMOD_FILE );

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create file.', 'acrossai-abilities-manager' ),
			);
		}

		return array(
			'success' => true,
			'path'    => $abs_path,
			'message' => __( 'File created.', 'acrossai-abilities-manager' ),
		);
	}
}
