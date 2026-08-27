<?php
/**
 * Absorbed ability class scaffolded from acrossai-core-abilities (Feature 046).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Block
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Block;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\File_Mods_Guard;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Db;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Detector;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Global_Styles\Global_Styles_File;

defined( 'ABSPATH' ) || exit;

/**
 * Updates Global Styles. Implements the full decision tree:
 *  - Auto-detects location; pass source / theme_type / plugin_slug to disambiguate.
 *  - Scenario 3: refuses to write to parent theme directly.
 *  - Scenario 14, 15: every file write routes through Global_Styles_File which
 *    invokes File_Mods_Guard (DISALLOW_FILE_MODS / DISALLOW_FILE_EDIT / read-only).
 *  - Scenarios 16, 17 (delete handled by global-styles-delete): supports
 *    section-scoped updates via "section" + "data".
 *  - migrate_to=db / child_theme moves the record across sources, optionally
 *    deleting the source via delete_source (parent-theme / plugin files are
 *    never deleted).
 *  - Scenarios 18, 19, 20, 21: validation in Global_Styles_Db.
 *  - merge=true (default) deep-merges into the existing record; merge=false
 *    replaces the record outright.
 */
class Update_Global_Style extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'blocks/update-global-style',
			'args' => array(
				'label'               => __( 'Update Global Style', 'acrossai-abilities-manager' ),
				'description'         => __( 'Updates Global Styles. By default deep-merges new data into the existing record; pass merge=false to replace. Use "section" + "data" to update one section only. Supports cross-source migration via migrate_to.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'theme'         => array(
							'type'    => 'string',
							'default' => '',
						),
						'source'        => array(
							'type'    => 'string',
							'enum'    => array( '', 'db', 'theme', 'child_theme', 'plugin' ),
							'default' => '',
						),
						'theme_type'    => array(
							'type'    => 'string',
							'enum'    => array( '', 'child', 'parent', 'theme' ),
							'default' => '',
						),
						'plugin_slug'   => array(
							'type'    => 'string',
							'default' => '',
						),
						'content'       => array(
							'type'        => array( 'string', 'object' ),
							'description' => __( 'Full or partial theme.json content. Object or JSON string.', 'acrossai-abilities-manager' ),
						),
						'section'       => array(
							'type' => 'string',
							'enum' => array( '', 'colors', 'typography', 'spacing', 'layout', 'blockStyles', 'customCss' ),
						),
						'data'          => array(
							'type'        => array( 'string', 'object' ),
							'description' => __( 'Section data; required when "section" is provided.', 'acrossai-abilities-manager' ),
						),
						'merge'         => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'true deep-merges; false replaces.', 'acrossai-abilities-manager' ),
						),
						'migrate_to'    => array(
							'type'    => 'string',
							'enum'    => array( '', 'db', 'child_theme' ),
							'default' => '',
						),
						'delete_source' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'return_content' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'When true, the response includes the saved theme.json JSON as record.data. Default false: the large JSON blob is stripped and content_bytes is returned instead, so a small edit does not echo the whole record back through the tunnel.', 'acrossai-abilities-manager' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'       => array( 'type' => 'boolean' ),
						'record'        => array( 'type' => 'object' ),
						'content_bytes' => array( 'type' => 'integer' ),
						'migrated'      => array( 'type' => 'boolean' ),
						'warnings'      => array( 'type' => 'array' ),
						'locations'     => array( 'type' => 'array' ),
						'candidates'    => array( 'type' => 'array' ),
						'message'       => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'blocks',
						'sub_group'       => 'global-styles',
						'sub_group_label' => __( 'Global Styles', 'acrossai-abilities-manager' ),
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
		$theme         = sanitize_key( $input['theme'] ?? '' );
		$source        = sanitize_text_field( $input['source'] ?? '' );
		$theme_type    = sanitize_text_field( $input['theme_type'] ?? '' );
		$plugin_slug   = sanitize_key( $input['plugin_slug'] ?? '' );
		$migrate_to    = sanitize_text_field( $input['migrate_to'] ?? '' );
		$delete_source = ! empty( $input['delete_source'] );
		$merge         = ! isset( $input['merge'] ) || (bool) $input['merge'];

		$locations = Global_Styles_Detector::locate( $theme );
		if ( empty( $locations ) ) {
			return array(
				'success'   => false,
				/* translators: %s: theme */
				'message'   => sprintf( __( 'No Global Styles record exists for theme "%s". Use global-styles-create.', 'acrossai-abilities-manager' ), '' !== $theme ? $theme : (string) get_stylesheet() ),
				'locations' => array(),
			);
		}

		$selected = Global_Styles_Detector::select( $locations, $source, $theme_type, $plugin_slug );
		if ( is_wp_error( $selected ) ) {
			$data = $selected->get_error_data();
			return array(
				'success'    => false,
				'message'    => $selected->get_error_message(),
				'locations'  => $locations,
				'candidates' => is_array( $data ) ? ( $data['locations'] ?? array() ) : array(),
			);
		}

		// File-mods guard once we know we'll be writing files.
		$selected_src = (string) ( $selected['source'] ?? '' );
		if ( ( 'theme' === $selected_src || 'plugin' === $selected_src ) || '' !== $migrate_to ) {
			$blocked = File_Mods_Guard::blocked_response();
			if ( null !== $blocked ) {
				return $blocked;
			}
		}

		$payload = $this->resolve_payload( $input );
		if ( is_wp_error( $payload ) ) {
			return $this->error_response( $payload );
		}

		$return_content = ! empty( $input['return_content'] );

		if ( '' !== $migrate_to ) {
			return $this->migrate( $selected, $migrate_to, $delete_source, $payload, $return_content );
		}

		// Scenario 3 — refuse parent theme write.
		if ( 'theme' === $selected_src && 'parent' === ( $selected['theme_type'] ?? '' ) ) {
			return array(
				'success'   => false,
				'message'   => __( 'Refusing to edit the parent theme directly. Re-run with migrate_to=child_theme or migrate_to=db.', 'acrossai-abilities-manager' ),
				'locations' => $locations,
			);
		}

		switch ( $selected_src ) {
			case 'db':
				return $this->update_db( $selected, $payload, $merge, $return_content );
			case 'theme':
			case 'plugin':
				return $this->update_file( $selected, $payload, $merge );
		}

		return array(
			'success' => false,
			'message' => __( 'Unknown source.', 'acrossai-abilities-manager' ),
		);
	}

	/**
	 * Update db.
	 *
	 * @param array $loc
	 * @param array $payload
	 * @param bool $merge
	 * @param bool $return_content
	 * @return array
	 */
	private function update_db( array $loc, array $payload, bool $merge, bool $return_content ): array {
		$post = get_post( (int) ( $loc['post_id'] ?? 0 ) );
		if ( ! $post ) {
			return array(
				'success' => false,
				'message' => __( 'wp_global_styles post not found.', 'acrossai-abilities-manager' ),
			);
		}

		$result = Global_Styles_Db::update( $post, $payload, $merge );
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		$updated       = get_post( (int) $result );
		$content_bytes = $updated ? strlen( (string) $updated->post_content ) : 0;

		return array(
			'success'       => true,
			'message'       => __( 'Updated DB Global Styles record.', 'acrossai-abilities-manager' ),
			'record'        => $updated ? Global_Styles_Db::to_row( $updated, $return_content ) : array(),
			'content_bytes' => $content_bytes,
			'warnings'      => array(),
		);
	}

	/**
	 * Update file.
	 *
	 * @param array $loc
	 * @param array $payload
	 * @param bool $merge
	 * @return array
	 */
	private function update_file( array $loc, array $payload, bool $merge ): array {
		$path     = (string) ( $loc['path'] ?? '' );
		$existing = Global_Styles_File::read_json( $path );
		if ( is_wp_error( $existing ) ) {
			return $this->error_response( $existing );
		}

		$new = $merge ? Global_Styles_Db::deep_merge( is_array( $existing ) ? $existing : array(), $payload ) : $payload;

		$valid = Global_Styles_Db::validate_data( $new );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}
		$valid = Global_Styles_Db::validate_block_styles( $new );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}

		$bytes = Global_Styles_File::write_json( $path, $new );
		if ( is_wp_error( $bytes ) ) {
			return $this->error_response( $bytes );
		}

		$warnings = array();
		if ( 'plugin' === ( $loc['source'] ?? '' ) && false === ( $loc['plugin_active'] ?? true ) ) {
			/* translators: %s: plugin slug */
			$warnings[] = sprintf( __( 'Plugin "%s" is inactive — your edit will only take effect once the plugin is activated.', 'acrossai-abilities-manager' ), $loc['plugin'] ?? '' );
		}

		return array(
			'success'  => true,
			/* translators: %s: file path */
			'message'  => sprintf( __( 'Updated theme.json at %s.', 'acrossai-abilities-manager' ), $path ),
			'record'   => array(
				'source'        => (string) ( $loc['source'] ?? '' ),
				'theme'         => (string) ( $loc['theme'] ?? '' ),
				'theme_type'    => (string) ( $loc['theme_type'] ?? '' ),
				'plugin'        => (string) ( $loc['plugin'] ?? '' ),
				'plugin_active' => (bool) ( $loc['plugin_active'] ?? false ),
				'path'          => $path,
				'bytes'         => (int) $bytes,
			),
			'warnings' => $warnings,
		);
	}

	/**
	 * Migrate.
	 *
	 * @param array $loc
	 * @param string $migrate_to
	 * @param bool $delete_source
	 * @param array $payload
	 * @param bool $return_content
	 * @return array
	 */
	private function migrate( array $loc, string $migrate_to, bool $delete_source, array $payload, bool $return_content ): array {
		$src      = (string) ( $loc['source'] ?? '' );
		$theme    = (string) ( $loc['theme'] ?? get_stylesheet() );
		$warnings = array();

		// Resolve source content first.
		if ( 'db' === $src ) {
			$post = get_post( (int) ( $loc['post_id'] ?? 0 ) );
			if ( ! $post ) {
				return array(
					'success' => false,
					'message' => __( 'Source DB record not found.', 'acrossai-abilities-manager' ),
				);
			}
			$existing = Global_Styles_Db::decode_content( $post );
		} else {
			$read = Global_Styles_File::read_json( (string) ( $loc['path'] ?? '' ) );
			if ( is_wp_error( $read ) ) {
				return $this->error_response( $read );
			}
			$existing = $read;
		}

		$merged = empty( $payload )
			? ( is_array( $existing ) ? $existing : array() )
			: Global_Styles_Db::deep_merge( is_array( $existing ) ? $existing : array(), $payload );

		$valid = Global_Styles_Db::validate_data( $merged );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}
		$valid = Global_Styles_Db::validate_block_styles( $merged );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}

		if ( 'db' === $migrate_to ) {
			if ( Global_Styles_Db::find_by_theme( $theme ) ) {
				return array(
					'success' => false,
					'message' => __( 'A DB Global Styles record already exists for this theme. Update it directly instead of migrating.', 'acrossai-abilities-manager' ),
				);
			}
			$id = Global_Styles_Db::create( $theme, $merged );
			if ( is_wp_error( $id ) ) {
				return $this->error_response( $id );
			}
			$new_post   = get_post( (int) $id );
			$warnings[] = __( 'DB version will override the file copy from now on.', 'acrossai-abilities-manager' );

			if ( $delete_source && 'theme' === $src && 'parent' !== ( $loc['theme_type'] ?? '' ) ) {
				$del = Global_Styles_File::delete_file( (string) $loc['path'] );
				if ( is_wp_error( $del ) ) {
					$warnings[] = $del->get_error_message();
				}
			} elseif ( $delete_source ) {
				$warnings[] = __( 'Skipped deleting source — parent-theme and plugin files are preserved.', 'acrossai-abilities-manager' );
			}

			$content_bytes = $new_post ? strlen( (string) $new_post->post_content ) : 0;

			return array(
				'success'       => true,
				/* translators: %s: theme */
				'message'       => sprintf( __( 'Migrated Global Styles for "%s" from file to database.', 'acrossai-abilities-manager' ), $theme ),
				'record'        => $new_post ? Global_Styles_Db::to_row( $new_post, $return_content ) : array(),
				'content_bytes' => $content_bytes,
				'migrated'      => true,
				'warnings'      => $warnings,
			);
		}

		// migrate_to = child_theme
		$child_dir = Global_Styles_File::get_child_theme_dir();
		if ( null === $child_dir ) {
			return array(
				'success' => false,
				'message' => __( 'No child theme is active. Create one before migrating to child theme.', 'acrossai-abilities-manager' ),
			);
		}

		$path  = Global_Styles_File::theme_json_path( $child_dir );
		$bytes = Global_Styles_File::write_json( $path, $merged );
		if ( is_wp_error( $bytes ) ) {
			return $this->error_response( $bytes );
		}

		if ( $delete_source && 'db' === $src ) {
			$post = get_post( (int) ( $loc['post_id'] ?? 0 ) );
			if ( $post ) {
				Global_Styles_Db::delete( $post );
				$warnings[] = __( 'DB record deleted — child-theme theme.json is now the only copy.', 'acrossai-abilities-manager' );
			}
		} elseif ( $delete_source && 'theme' === $src && 'parent' === ( $loc['theme_type'] ?? '' ) ) {
			$warnings[] = __( 'Skipped deleting parent-theme source — parent files are preserved.', 'acrossai-abilities-manager' );
		} elseif ( $delete_source && 'plugin' === $src ) {
			$warnings[] = __( 'Skipped deleting plugin source — plugin files are preserved.', 'acrossai-abilities-manager' );
		}

		return array(
			'success'  => true,
			/* translators: %s: path */
			'message'  => sprintf( __( 'Migrated Global Styles to child theme at %s.', 'acrossai-abilities-manager' ), $path ),
			'record'   => array(
				'source'     => 'theme',
				'theme_type' => 'child',
				'theme'      => basename( $child_dir ),
				'path'       => $path,
				'bytes'      => (int) $bytes,
			),
			'migrated' => true,
			'warnings' => $warnings,
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	private function resolve_payload( array $input ) {
		$section = (string) ( $input['section'] ?? '' );
		if ( '' !== $section ) {
			$norm = Global_Styles_Db::normalize_section( $section );
			if ( ! Global_Styles_Db::valid_section( $norm ) ) {
				return new \WP_Error(
					'invalid_section',
					/* translators: %s: list of valid sections */
					sprintf( __( 'Invalid section. Allowed: %s.', 'acrossai-abilities-manager' ), implode( ', ', Global_Styles_Db::valid_sections() ) )
				);
			}

			// customCss is a single scalar string (theme.json: styles.css) — accept
			// the raw CSS directly so callers don't have to hand-build the JSON wrapper.
			if ( 'customCss' === $norm && isset( $input['data'] ) && is_string( $input['data'] ) ) {
				return array( 'styles' => array( 'css' => (string) $input['data'] ) );
			}

			$data = $this->coerce_array( $input['data'] ?? null );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			if ( empty( $data ) ) {
				return new \WP_Error( 'empty_section_data', __( 'Section data is required when "section" is provided.', 'acrossai-abilities-manager' ) );
			}
			$payload = array();
			foreach ( Global_Styles_Db::SECTION_PATHS[ $norm ] as $path ) {
				$value = Global_Styles_Db::path_get( $data, $path );
				if ( null !== $value ) {
					Global_Styles_Db::path_set( $payload, $path, $value );
				}
			}
			return $payload;
		}

		// migrate-only calls don't need content.
		if ( ! isset( $input['content'] ) ) {
			return array();
		}

		return $this->coerce_array( $input['content'] );
	}

	/**
	 * @return array|\WP_Error
	 */
	private function coerce_array( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return Global_Styles_Db::parse_json( $value );
		}
		if ( is_object( $value ) ) {
			return json_decode( wp_json_encode( $value ), true ) ?: array();
		}
		return new \WP_Error( 'missing_content', __( 'Content is required.', 'acrossai-abilities-manager' ) );
	}

	/**
	 * Error response.
	 *
	 * @param \WP_Error $err
	 * @return array
	 */
	private function error_response( \WP_Error $err ): array {
		return array(
			'success' => false,
			'message' => $err->get_error_message(),
		);
	}
}
