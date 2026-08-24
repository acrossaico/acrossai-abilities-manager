<?php
/**
 * File-manager delete-directory ability (Feature 090).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\FileManager
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\FileManager;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\File_Mods_Guard;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

defined( 'ABSPATH' ) || exit;

/**
 * Delete_Directory ability class.
 *
 * Remove a directory under ABSPATH. Requires confirm:true. Default
 * recursive:false refuses non-empty directories. Refuses a hardcoded
 * list of critical WordPress directories regardless of inputs.
 * Symlinks are unlinked (not followed).
 */
class Delete_Directory extends Ability_Definition {

	/**
	 * ABSPATH-relative directory paths that this ability refuses to delete
	 * regardless of confirm/recursive inputs. Deleting any of these would
	 * irreversibly break the site.
	 *
	 * Empty string means ABSPATH itself.
	 *
	 * @var array<int,string>
	 */
	private const PROTECTED_DIRS = array(
		'',
		'wp-admin',
		'wp-includes',
		'wp-content',
		'wp-content/plugins',
		'wp-content/themes',
		'wp-content/mu-plugins',
		'wp-content/uploads',
		'wp-content/plugins/acrossai-abilities-manager',
	);

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'file-manager/delete-directory',
			'args' => array(
				'label'               => __( 'Delete Directory', 'acrossai-abilities-manager' ),
				'description'         => __( 'Delete a directory inside the WordPress installation. Path must be relative to ABSPATH. Requires confirm:true. Default recursive:false refuses non-empty directories. Refuses a hardcoded list of critical WordPress directories (ABSPATH, wp-admin, wp-includes, wp-content, wp-content/plugins, wp-content/themes, wp-content/mu-plugins, wp-content/uploads, and this plugin\'s directory). Symlinks are not followed during recursive walks. Idempotent — a missing target returns success with entries_removed:0.', 'acrossai-abilities-manager' ),
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
							'default'     => false,
							'description' => __( 'When true, contents are removed bottom-up before the directory itself. Default false requires the directory to be empty.', 'acrossai-abilities-manager' ),
						),
						'confirm'   => array(
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
						'success'         => array( 'type' => 'boolean' ),
						'path'            => array( 'type' => 'string' ),
						'entries_removed' => array( 'type' => 'integer' ),
						'message'         => array( 'type' => 'string' ),
						'blocked_reason'  => array( 'type' => 'string' ),
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
				'message'        => __( 'Deleting a directory is permanent. Pass confirm:true to proceed.', 'acrossai-abilities-manager' ),
			);
		}

		$blocked = File_Mods_Guard::blocked_response();
		if ( null !== $blocked ) {
			return $blocked;
		}

		$rel_path  = sanitize_text_field( $input['path'] ?? '' );
		$recursive = ! empty( $input['recursive'] );

		$base = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$abs  = $base . '/' . ltrim( $rel_path, '/' );
		$real = realpath( $abs );

		// Missing target: idempotent success.
		if ( false === $real ) {
			// Still enforce scope on the requested path so a caller can't
			// probe outside ABSPATH by asking us to "delete" a path there.
			if ( 0 !== strpos( $abs, $base . '/' ) && $abs !== $base ) {
				return array(
					'success'        => false,
					'blocked_reason' => 'invalid_path',
					'message'        => __( 'Invalid or disallowed directory path.', 'acrossai-abilities-manager' ),
				);
			}
			return array(
				'success'         => true,
				'path'            => $abs,
				'entries_removed' => 0,
				'message'         => __( 'Directory does not exist.', 'acrossai-abilities-manager' ),
			);
		}

		if ( $real !== $base && 0 !== strpos( $real, $base . '/' ) ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'invalid_path',
				'message'        => __( 'Invalid or disallowed directory path.', 'acrossai-abilities-manager' ),
			);
		}

		if ( ! is_dir( $real ) ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'not_a_directory',
				'message'        => __( 'Path exists but is not a directory.', 'acrossai-abilities-manager' ),
			);
		}

		// Protected-directory guard: compare $real against each protected
		// entry resolved to its absolute path. Refuses ABSPATH itself and
		// the eight critical WordPress subdirectories.
		foreach ( self::PROTECTED_DIRS as $rel ) {
			$protected_abs = '' === $rel ? $base : $base . '/' . $rel;
			$protected_real = realpath( $protected_abs );
			if ( false !== $protected_real && $protected_real === $real ) {
				return array(
					'success'        => false,
					'blocked_reason' => 'protected_directory',
					/* translators: %s: relative directory path */
					'message'        => sprintf( __( 'Directory "%s" is protected and cannot be deleted.', 'acrossai-abilities-manager' ), '' === $rel ? 'ABSPATH' : $rel ),
				);
			}
		}

		if ( ! $recursive ) {
			if ( ! rmdir( $real ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
				return array(
					'success'         => false,
					'blocked_reason'  => 'not_empty',
					'entries_removed' => 0,
					'message'         => __( 'Directory is not empty. Pass recursive:true to delete its contents.', 'acrossai-abilities-manager' ),
				);
			}
			return array(
				'success'         => true,
				'path'            => $real,
				'entries_removed' => 1,
				'message'         => __( 'Empty directory removed.', 'acrossai-abilities-manager' ),
			);
		}

		// Recursive walk, bottom-up.
		$entries_removed = 0;
		$iterator        = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $real, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file_info ) {
			$entry_path = $file_info->getPathname();

			// Symlinks are unlinked as references — never followed.
			if ( $file_info->isLink() ) {
				if ( ! @unlink( $entry_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					return array(
						'success'         => false,
						'entries_removed' => $entries_removed,
						/* translators: %s: entry path */
						'message'         => sprintf( __( 'Could not remove symlink at "%s".', 'acrossai-abilities-manager' ), $entry_path ),
					);
				}
				++$entries_removed;
				continue;
			}

			if ( $file_info->isDir() ) {
				if ( ! @rmdir( $entry_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					return array(
						'success'         => false,
						'entries_removed' => $entries_removed,
						/* translators: %s: entry path */
						'message'         => sprintf( __( 'Could not remove directory at "%s".', 'acrossai-abilities-manager' ), $entry_path ),
					);
				}
			} elseif ( ! @unlink( $entry_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return array(
					'success'         => false,
					'entries_removed' => $entries_removed,
					/* translators: %s: entry path */
					'message'         => sprintf( __( 'Could not remove file at "%s".', 'acrossai-abilities-manager' ), $entry_path ),
				);
			}
			++$entries_removed;
		}

		if ( ! @rmdir( $real ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return array(
				'success'         => false,
				'entries_removed' => $entries_removed,
				'message'         => __( 'Could not remove the top-level directory after clearing its contents.', 'acrossai-abilities-manager' ),
			);
		}
		++$entries_removed;

		return array(
			'success'         => true,
			'path'            => $real,
			'entries_removed' => $entries_removed,
			/* translators: %d: entries removed */
			'message'         => sprintf( __( 'Directory removed (%d entries).', 'acrossai-abilities-manager' ), $entries_removed ),
		);
	}
}
