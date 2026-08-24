<?php
/**
 * File-manager file-info ability (Feature 090).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\FileManager
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\FileManager;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * File_Info ability class.
 *
 * Return stat metadata for any path under ABSPATH without loading its
 * content. Owner/group names are resolved via the PHP POSIX extension
 * when available and omitted otherwise.
 */
class File_Info extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'file-manager/file-info',
			'args' => array(
				'label'               => __( 'Get File Info', 'acrossai-abilities-manager' ),
				'description'         => __( 'Return stat metadata (type, size, timestamps, mode/permissions, ownership, readability, writability, symlink flag) for any path inside the WordPress installation. Path must be relative to ABSPATH. Read-only — does not open the file. Owner/group name fields are omitted when the PHP POSIX extension is absent.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'path' => array(
							'type'        => 'string',
							'description' => __( 'File or directory path relative to ABSPATH.', 'acrossai-abilities-manager' ),
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
						'type'           => array(
							'type' => 'string',
							'enum' => array( 'file', 'dir', 'link' ),
						),
						'size'           => array( 'type' => 'integer' ),
						'mtime'          => array( 'type' => 'integer' ),
						'ctime'          => array( 'type' => 'integer' ),
						'atime'          => array( 'type' => 'integer' ),
						'mode_octal'     => array( 'type' => 'string' ),
						'owner_uid'      => array( 'type' => 'integer' ),
						'owner_name'     => array( 'type' => 'string' ),
						'group_gid'      => array( 'type' => 'integer' ),
						'group_name'     => array( 'type' => 'string' ),
						'readable'       => array( 'type' => 'boolean' ),
						'writable'       => array( 'type' => 'boolean' ),
						'is_link'        => array( 'type' => 'boolean' ),
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
		$rel_path = sanitize_text_field( $input['path'] ?? '' );
		$base     = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$candidate = $base . '/' . ltrim( $rel_path, '/' );

		// Scope check: parent must resolve inside ABSPATH. This mirrors the
		// pattern used by Read_File and lets us handle broken symlinks (where
		// realpath($candidate) itself is false but the parent resolves).
		$parent = realpath( dirname( $candidate ) );
		if ( false === $parent || ( $parent !== $base && 0 !== strpos( $parent, $base . '/' ) ) ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'invalid_path',
				'message'        => __( 'Invalid or disallowed path.', 'acrossai-abilities-manager' ),
			);
		}

		if ( ! file_exists( $candidate ) && ! is_link( $candidate ) ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'path_not_found',
				'message'        => __( 'Path does not exist.', 'acrossai-abilities-manager' ),
			);
		}

		$stat = @stat( $candidate ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $stat ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not stat path.', 'acrossai-abilities-manager' ),
			);
		}

		$is_link = is_link( $candidate );
		if ( $is_link && ! file_exists( $candidate ) ) {
			$type = 'link';
		} elseif ( is_dir( $candidate ) ) {
			$type = 'dir';
		} else {
			$type = 'file';
		}

		$response = array(
			'success'    => true,
			'path'       => $candidate,
			'type'       => $type,
			'size'       => 'dir' === $type ? 0 : (int) $stat['size'],
			'mtime'      => (int) $stat['mtime'],
			'ctime'      => (int) $stat['ctime'],
			'atime'      => (int) $stat['atime'],
			'mode_octal' => substr( (string) decoct( (int) $stat['mode'] ), -4 ),
			'owner_uid'  => (int) $stat['uid'],
			'group_gid'  => (int) $stat['gid'],
			'readable'   => is_readable( $candidate ),
			'writable'   => is_writable( $candidate ),
			'is_link'    => $is_link,
			'message'    => __( 'Path metadata retrieved.', 'acrossai-abilities-manager' ),
		);

		if ( function_exists( 'posix_getpwuid' ) ) {
			$pw = posix_getpwuid( (int) $stat['uid'] );
			if ( is_array( $pw ) && isset( $pw['name'] ) && '' !== $pw['name'] ) {
				$response['owner_name'] = (string) $pw['name'];
			}
		}
		if ( function_exists( 'posix_getgrgid' ) ) {
			$gr = posix_getgrgid( (int) $stat['gid'] );
			if ( is_array( $gr ) && isset( $gr['name'] ) && '' !== $gr['name'] ) {
				$response['group_name'] = (string) $gr['name'];
			}
		}

		return $response;
	}
}
