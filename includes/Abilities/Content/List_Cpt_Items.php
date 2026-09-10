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
 * List_Cpt_Items ability class (absorbed).
 */
class List_Cpt_Items extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'content/list-cpt-items',
			'args' => array(
				'label'               => __( 'Get CPT Items', 'acrossai-abilities-manager' ),
				'description'         => __( 'List custom post type records via WP_Query. post_type is required.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_type' => array( 'type' => 'string' ),
						'status'    => array(
							'type'    => 'string',
							'default' => 'any',
						),
						'page'      => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page'  => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 10,
						),
						'search'    => array( 'type' => 'string' ),
						'orderby'   => array(
							'type'    => 'string',
							'default' => 'date',
						),
						'order'     => array(
							'type'    => 'string',
							'enum'    => array( 'ASC', 'DESC', 'asc', 'desc' ),
							'default' => 'DESC',
						),
					),
					'required'             => array( 'post_type' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'items'   => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
						'pages'   => array( 'type' => 'integer' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'content',
						'sub_group'       => 'cpt',
						'sub_group_label' => __( 'Custom Post Types', 'acrossai-abilities-manager' ),
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
		$post_type = sanitize_key( (string) ( $input['post_type'] ?? '' ) );
		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			return array(
				'success' => false,
				/* translators: %s: post type */
				'message' => sprintf( __( 'Unknown post type "%s".', 'acrossai-abilities-manager' ), $post_type ),
			);
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => sanitize_text_field( (string) ( $input['status'] ?? 'any' ) ),
			'paged'          => max( 1, (int) ( $input['page'] ?? 1 ) ),
			'posts_per_page' => min( 100, max( 1, (int) ( $input['per_page'] ?? 10 ) ) ),
			'orderby'        => sanitize_key( (string) ( $input['orderby'] ?? 'date' ) ),
			'order'          => strtoupper( (string) ( $input['order'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC',
		);
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( (string) $input['search'] );
		}

		$query = new \WP_Query( $args );
		$out   = array();
		foreach ( $query->posts as $p ) {
			$out[] = (array) $p;
		}

		return array(
			'success' => true,
			'items'   => $out,
			'total'   => (int) $query->found_posts,
			'pages'   => (int) $query->max_num_pages,
		);
	}
}
