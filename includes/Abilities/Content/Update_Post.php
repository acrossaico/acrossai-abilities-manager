<?php
/**
 * Absorbed ability class scaffolded from acrossai-core-abilities (Feature 046).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Content
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Content;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Update an existing post (any post type) via wp_update_post().
 * All fields besides "id" are optional — only the supplied fields are touched.
 */
class Update_Post extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'content/update-post',
			'args' => array(
				'label'               => __( 'Update Post', 'acrossai-abilities-manager' ),
				'description'         => __( 'Update an existing post (any post type) via wp_update_post(). Only the supplied fields are changed. Refuses non-writable post types and strips protected meta keys unless allow-listed.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'      => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
						'excerpt' => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string' ),
						'slug'    => array( 'type' => 'string' ),
						'author'  => array( 'type' => 'integer' ),
						'date'    => array( 'type' => 'string' ),
						'meta'    => array( 'type' => 'object' ),
						'return_content' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'When true, the response includes the saved post_content / post_excerpt / post_content_filtered fields. Default false: those large fields are stripped and content_bytes is returned instead, so a "one-word edit" round-trip does not echo the whole post body back through the tunnel.', 'acrossai-abilities-manager' ),
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'           => array( 'type' => 'boolean' ),
						'id'                => array( 'type' => 'integer' ),
						'post'              => array( 'type' => 'object' ),
						'content_bytes'     => array( 'type' => 'integer' ),
						'dropped_meta_keys' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'blocked_reason'    => array( 'type' => 'string' ),
						'message'           => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'core',
						'sub_group'       => 'posts',
						'sub_group_label' => __( 'Posts', 'acrossai-abilities-manager' ),
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
		$id   = (int) ( $input['id'] ?? 0 );
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! ( $post instanceof \WP_Post ) ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found.', 'acrossai-abilities-manager' ),
			);
		}

		// Writable-post-type gate: mirrors WP REST writability convention.
		$pt_obj = get_post_type_object( (string) $post->post_type );
		if ( null === $pt_obj || ! ( ! empty( $pt_obj->public ) || ! empty( $pt_obj->show_in_rest ) ) ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'non_writable_post_type',
				/* translators: %s: post type name */
				'message'        => sprintf( __( 'Post type "%s" is not writable via this ability.', 'acrossai-abilities-manager' ), (string) $post->post_type ),
			);
		}

		// publish_posts capability gate on any status change into a public state.
		if ( isset( $input['status'] ) ) {
			$requested_status = sanitize_key( (string) $input['status'] );
			$status_obj       = get_post_status_object( $requested_status );
			if ( $status_obj && ! empty( $status_obj->public )
				&& ! current_user_can( (string) $pt_obj->cap->publish_posts ) ) {
				return array(
					'success'        => false,
					'blocked_reason' => 'publish_cap_required',
					/* translators: 1: status, 2: post type */
					'message'        => sprintf( __( 'You do not have permission to set status "%1$s" on post type "%2$s".', 'acrossai-abilities-manager' ), $requested_status, (string) $post->post_type ),
				);
			}
		}

		// edit_others_posts capability gate on author changes.
		if ( isset( $input['author'] )
			&& (int) $input['author'] !== (int) get_current_user_id()
			&& ! current_user_can( (string) $pt_obj->cap->edit_others_posts ) ) {
			return array(
				'success'        => false,
				'blocked_reason' => 'edit_others_posts_required',
				/* translators: %s: post type name */
				'message'        => sprintf( __( 'You do not have permission to change the author on post type "%s".', 'acrossai-abilities-manager' ), (string) $post->post_type ),
			);
		}

		$args = array( 'ID' => $id );
		if ( isset( $input['title'] ) ) {
			$args['post_title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
			$args['post_content'] = (string) $input['content'];
		}
		if ( isset( $input['excerpt'] ) ) {
			$args['post_excerpt'] = (string) $input['excerpt'];
		}
		if ( isset( $input['status'] ) ) {
			$args['post_status'] = sanitize_key( (string) $input['status'] );
		}
		if ( isset( $input['slug'] ) ) {
			$args['post_name'] = sanitize_title( (string) $input['slug'] );
		}
		if ( isset( $input['author'] ) ) {
			$args['post_author'] = (int) $input['author'];
		}
		if ( isset( $input['date'] ) ) {
			$args['post_date'] = (string) $input['date'];
		}

		// Protected-meta filter. Strip keys that begin with `_` or are reported
		// by is_protected_meta() unless allow-listed via acrossai_allowed_protected_meta.
		$dropped_meta_keys = array();
		if ( ! empty( $input['meta'] ) && is_array( $input['meta'] ) ) {
			$allowed  = (array) apply_filters( 'acrossai_allowed_protected_meta', array() );
			$filtered = array();
			foreach ( $input['meta'] as $meta_key => $meta_value ) {
				$key_str = (string) $meta_key;
				$is_prot = str_starts_with( $key_str, '_' ) || is_protected_meta( $key_str, 'post' );
				if ( $is_prot && ! in_array( $key_str, $allowed, true ) ) {
					$dropped_meta_keys[] = $key_str;
					continue;
				}
				$filtered[ $key_str ] = $meta_value;
			}
			if ( ! empty( $filtered ) ) {
				$args['meta_input'] = $filtered;
			}
		}

		$result = wp_update_post( $args, true );
		if ( is_wp_error( $result ) ) {
			return array(
				'success'           => false,
				'dropped_meta_keys' => $dropped_meta_keys,
				'message'           => $result->get_error_message(),
			);
		}

		$fetched        = (array) get_post( (int) $result, ARRAY_A );
		$content_bytes  = strlen( (string) ( $fetched['post_content'] ?? '' ) );
		$return_content = ! empty( $input['return_content'] );
		if ( ! $return_content ) {
			unset(
				$fetched['post_content'],
				$fetched['post_content_filtered'],
				$fetched['post_excerpt']
			);
		}

		return array(
			'success'           => true,
			'id'                => (int) $result,
			'post'              => $fetched,
			'content_bytes'     => $content_bytes,
			'dropped_meta_keys' => $dropped_meta_keys,
			/* translators: %d: post ID */
			'message'           => sprintf( __( 'Updated post #%d.', 'acrossai-abilities-manager' ), $result ),
		);
	}
}
