<?php
/**
 * File-manager directory-listing ability (Feature 089).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\FileManager
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\FileManager;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

defined( 'ABSPATH' ) || exit;

/**
 * List_Directory ability class.
 *
 * Recursive walk of a directory under ABSPATH. Bounded by max_depth and
 * max_entries so a caller cannot enumerate the entire filesystem in one call.
 */
class List_Directory extends Ability_Definition {

	private const DEFAULT_MAX_DEPTH   = 5;
	private const DEFAULT_MAX_ENTRIES = 1000;
	private const HARD_CAP_MAX_DEPTH  = 20;
	private const HARD_CAP_MAX_ENTRIES = 5000;

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'file-manager/list-directory',
			'args' => array(
				'label'               => __( 'List Directory', 'acrossai-abilities-manager' ),
				'description'         => __( 'Recursively list entries under a directory inside the WordPress installation. Path must be relative to ABSPATH. Results are bounded by max_depth (default 5, max 20) and max_entries (default 1000, max 5000); when either bound is reached the response sets truncated:true. Symlinks are not followed.', 'acrossai-abilities-manager' ),
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
							'description' => __( 'Directory path relative to ABSPATH (e.g. wp-content/plugins/hello-dolly).', 'acrossai-abilities-manager' ),
						),
						'max_depth'   => array(
							'type'        => 'integer',
							'default'     => self::DEFAULT_MAX_DEPTH,
							'minimum'     => 1,
							'maximum'     => self::HARD_CAP_MAX_DEPTH,
							'description' => __( 'Maximum recursion depth. Depth 1 lists only direct children.', 'acrossai-abilities-manager' ),
						),
						'max_entries' => array(
							'type'        => 'integer',
							'default'     => self::DEFAULT_MAX_ENTRIES,
							'minimum'     => 1,
							'maximum'     => self::HARD_CAP_MAX_ENTRIES,
							'description' => __( 'Maximum number of entries returned. If reached, response has truncated:true.', 'acrossai-abilities-manager' ),
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
						'entries'        => array(
							'type'  => 'array',
							'items' => array(
								'type'                 => 'object',
								'properties'           => array(
									'path'  => array( 'type' => 'string' ),
									'type'  => array(
										'type' => 'string',
										'enum' => array( 'file', 'dir' ),
									),
									'size'  => array( 'type' => 'integer' ),
									'mtime' => array( 'type' => 'integer' ),
								),
								'required'             => array( 'path', 'type', 'size', 'mtime' ),
								'additionalProperties' => false,
							),
						),
						'truncated'      => array( 'type' => 'boolean' ),
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
		$rel_path    = sanitize_text_field( $input['path'] ?? '' );
		$max_depth   = isset( $input['max_depth'] ) ? (int) $input['max_depth'] : self::DEFAULT_MAX_DEPTH;
		$max_entries = isset( $input['max_entries'] ) ? (int) $input['max_entries'] : self::DEFAULT_MAX_ENTRIES;

		if ( $max_depth < 1 ) {
			$max_depth = 1;
		}
		if ( $max_depth > self::HARD_CAP_MAX_DEPTH ) {
			$max_depth = self::HARD_CAP_MAX_DEPTH;
		}
		if ( $max_entries < 1 ) {
			$max_entries = 1;
		}
		if ( $max_entries > self::HARD_CAP_MAX_ENTRIES ) {
			$max_entries = self::HARD_CAP_MAX_ENTRIES;
		}

		$base = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$real = realpath( $base . '/' . ltrim( $rel_path, '/' ) );

		if ( false === $real || ( $real !== $base && 0 !== strpos( $real, $base . '/' ) ) ) {
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

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $real, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);
		$iterator->setMaxDepth( $max_depth - 1 );

		$entries   = array();
		$truncated = false;
		$base_len  = strlen( $base ) + 1;

		foreach ( $iterator as $file_info ) {
			// Never follow symlinks — belt-and-braces beyond the entry-time realpath.
			if ( $file_info->isLink() ) {
				continue;
			}

			$abs = $file_info->getPathname();

			$entries[] = array(
				'path'  => substr( $abs, $base_len ),
				'type'  => $file_info->isDir() ? 'dir' : 'file',
				'size'  => $file_info->isDir() ? 0 : (int) $file_info->getSize(),
				'mtime' => (int) $file_info->getMTime(),
			);

			if ( count( $entries ) >= $max_entries ) {
				$truncated = true;
				break;
			}
		}

		return array(
			'success'   => true,
			'path'      => $real,
			'entries'   => $entries,
			'truncated' => $truncated,
			'message'   => sprintf(
				/* translators: 1: entry count, 2: truncated flag literal */
				_n(
					'Listed %1$d entry (truncated=%2$s).',
					'Listed %1$d entries (truncated=%2$s).',
					count( $entries ),
					'acrossai-abilities-manager'
				),
				count( $entries ),
				$truncated ? 'true' : 'false'
			),
		);
	}
}
