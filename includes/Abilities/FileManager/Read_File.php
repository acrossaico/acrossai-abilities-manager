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
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Wp_Filesystem_Init;

defined( 'ABSPATH' ) || exit;

/**
 * Read_File ability class (absorbed).
 */
class Read_File extends Ability_Definition {

	/**
	 * Hardcoded filenames (at ABSPATH root) whose contents must never be
	 * returned. wp-config.php holds the DB password and eight auth constants;
	 * .htaccess can leak rewrite rules and access-control secrets.
	 *
	 * @var array<int,string>
	 */
	private const PROTECTED_FILES = array(
		'wp-config.php',
		'.htaccess',
	);

	/**
	 * Maximum file size (bytes) that read-file will return as text. Files
	 * larger than this are refused so the ability cannot exhaust PHP's
	 * memory limit on an arbitrary path.
	 *
	 * @var int
	 */
	private const MAX_READ_BYTES = 5242880; // 5 * 1024 * 1024.

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'file-manager/read-file',
			'args' => array(
				'label'               => __( 'Read File', 'acrossai-abilities-manager' ),
				'description'         => __( 'Reads the contents of a file within the WordPress installation. Path must be relative to ABSPATH. Refuses wp-config.php and .htaccess, refuses files larger than 5 MB, and reports binary content without returning raw bytes.', 'acrossai-abilities-manager' ),
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
							'description' => __( 'File path relative to ABSPATH (e.g. wp-content/uploads/test.txt).', 'acrossai-abilities-manager' ),
						),
					),
					'required'             => array( 'path' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'        => array( 'type' => 'boolean' ),
						'content'        => array( 'type' => 'string' ),
						'path'           => array( 'type' => 'string' ),
						'size'           => array( 'type' => 'integer' ),
						'binary'         => array( 'type' => 'boolean' ),
						'max_bytes'      => array( 'type' => 'integer' ),
						'blocked_reason' => array( 'type' => 'string' ),
						'message'        => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
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
		$blocked = Wp_Filesystem_Init::blocked_response();
		if ( null !== $blocked ) {
			return $blocked;
		}
		$fs = Wp_Filesystem_Init::get();

		$rel_path = sanitize_text_field( $input['path'] ?? '' );
		$base     = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$abs_path = $base . '/' . ltrim( $rel_path, '/' );
		$parent   = realpath( dirname( $abs_path ) );

		if ( false === $parent || ( $parent !== $base && 0 !== strpos( $parent, $base . '/' ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid or disallowed file path.', 'acrossai-abilities-manager' ),
			);
		}

		// Protected-file guard: refuse to return contents of secret-holding
		// files at ABSPATH root regardless of the caller's capability.
		$real_for_check = realpath( $abs_path );
		if ( false !== $real_for_check
			&& in_array( basename( $real_for_check ), self::PROTECTED_FILES, true )
			&& dirname( $real_for_check ) === $base ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'protected_read',
				/* translators: %s: filename */
				'message'        => sprintf( __( 'File "%s" is protected and cannot be read.', 'acrossai-abilities-manager' ), basename( $real_for_check ) ),
			);
		}

		if ( ! $fs->is_file( $abs_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'File does not exist.', 'acrossai-abilities-manager' ),
			);
		}

		// Size cap: refuse before loading the file into memory.
		$size = (int) $fs->size( $abs_path );
		if ( $size > self::MAX_READ_BYTES ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'file_too_large',
				'size'           => $size,
				'max_bytes'      => self::MAX_READ_BYTES,
				/* translators: 1: observed size, 2: max size */
				'message'        => sprintf( __( 'File size (%1$d bytes) exceeds the maximum readable size (%2$d bytes).', 'acrossai-abilities-manager' ), $size, self::MAX_READ_BYTES ),
			);
		}

		$content = $fs->get_contents( $abs_path );

		if ( false === $content ) {
			return array(
				'success' => false,
				'message' => __( 'Could not read file.', 'acrossai-abilities-manager' ),
			);
		}

		$real_path = realpath( $abs_path ) ?: $abs_path;

		// Binary detection: return a distinct shape without the raw bytes.
		if ( ! mb_check_encoding( $content, 'UTF-8' ) ) {
			return array(
				'success' => true,
				'binary'  => true,
				'path'    => $real_path,
				'size'    => $size,
				'message' => __( 'Binary file; contents not returned as text.', 'acrossai-abilities-manager' ),
			);
		}

		return array(
			'success' => true,
			'content' => $content,
			'path'    => $real_path,
			'size'    => $size,
			'binary'  => false,
		);
	}
}
