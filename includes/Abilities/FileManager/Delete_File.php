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
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Wp_Filesystem_Init;

defined( 'ABSPATH' ) || exit;

/**
 * Delete_File ability class (absorbed).
 */
class Delete_File extends Ability_Definition {

	/**
	 * Hardcoded filenames (at ABSPATH root) that this ability must never
	 * delete. Same list as Read_File::PROTECTED_FILES.
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
			'name' => 'file-manager/delete-file',
			'args' => array(
				'label'               => __( 'Delete File', 'acrossai-abilities-manager' ),
				'description'         => __( 'Deletes a file within the WordPress installation. Path must be relative to ABSPATH. Requires confirm:true, refuses wp-config.php and .htaccess, writes a .bak.<timestamp> copy before deleting, and invalidates OPcache when available.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'path'    => array(
							'type'        => 'string',
							'description' => __( 'File path relative to ABSPATH.', 'acrossai-abilities-manager' ),
						),
						'confirm' => array(
							'type'        => 'boolean',
							'description' => __( 'Must be true to proceed. Guards against accidental deletes.', 'acrossai-abilities-manager' ),
						),
					),
					'required'             => array( 'path', 'confirm' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'        => array( 'type' => 'boolean' ),
						'backup'         => array( 'type' => array( 'string', 'null' ) ),
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
		// Explicit-confirmation guard. Refuse before any I/O.
		if ( empty( $input['confirm'] ) || true !== (bool) $input['confirm'] ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'confirmation_required',
				'message'        => __( 'Deleting a file is permanent. Pass confirm:true to proceed.', 'acrossai-abilities-manager' ),
			);
		}

		$blocked = File_Mods_Guard::blocked_response();
		if ( null !== $blocked ) {
			return $blocked;
		}
		$blocked = Wp_Filesystem_Init::blocked_response();
		if ( null !== $blocked ) {
			return $blocked;
		}
		$fs = Wp_Filesystem_Init::get();

		$rel_path = sanitize_text_field( $input['path'] ?? '' );
		$base     = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$real     = realpath( $base . '/' . ltrim( $rel_path, '/' ) );

		if ( false === $real || 0 !== strpos( $real, $base . '/' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid or disallowed file path.', 'acrossai-abilities-manager' ),
			);
		}

		// Protected-file guard: refuse to delete secret-holding files at
		// ABSPATH root regardless of the caller's capability.
		if ( in_array( basename( $real ), self::PROTECTED_FILES, true )
			&& dirname( $real ) === $base ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'protected_write',
				/* translators: %s: filename */
				'message'        => sprintf( __( 'File "%s" is protected and cannot be deleted.', 'acrossai-abilities-manager' ), basename( $real ) ),
			);
		}

		if ( ! $fs->is_file( $real ) ) {
			return array(
				'success' => false,
				'message' => __( 'File does not exist.', 'acrossai-abilities-manager' ),
			);
		}

		// Best-effort backup: copy the file to <path>.bak.<timestamp> before
		// deleting. If the copy fails we still proceed with the delete —
		// backup is a convenience, not a hard prerequisite.
		$backup = $real . '.bak.' . time();
		if ( ! $fs->copy( $real, $backup, false, FS_CHMOD_FILE ) ) {
			$backup = null;
		}

		if ( ! $fs->delete( $real ) ) {
			return array(
				'success' => false,
				'backup'  => $backup,
				'message' => __( 'Could not delete file.', 'acrossai-abilities-manager' ),
			);
		}

		// OPcache: invalidate the removed path so stale bytecode doesn't linger.
		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $real, true );
		}

		return array(
			'success' => true,
			'backup'  => $backup,
			'message' => __( 'File deleted.', 'acrossai-abilities-manager' ),
		);
	}
}
