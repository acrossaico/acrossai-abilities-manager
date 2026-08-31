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
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Slash_Input;

defined( 'ABSPATH' ) || exit;

/**
 * Write a single post meta value via update_post_meta(). Works for ANY meta key.
 */
class Update_Post_Meta extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'content/update-post-meta',
			'args' => array(
				'label'               => __( 'Update Post Meta', 'acrossai-abilities-manager' ),
				'description'         => __( 'Set a post meta value via update_post_meta(). Works for any meta key, including protected keys.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'key'        => array( 'type' => 'string' ),
						'meta_key'   => array(
							'type'        => 'string',
							'description' => __( 'Alias for "key" (matches WordPress core naming). If both are provided, "key" wins.', 'acrossai-abilities-manager' ),
						),
						'value'      => array( 'type' => array( 'string', 'integer', 'number', 'boolean', 'array', 'object', 'null' ) ),
						'meta_value' => array(
							'type'        => array( 'string', 'integer', 'number', 'boolean', 'array', 'object', 'null' ),
							'description' => __( 'Alias for "value" (matches WordPress core naming). If both are provided, "value" wins.', 'acrossai-abilities-manager' ),
						),
						'apply_wp_slash' => Slash_Input::schema_fragment()['apply_wp_slash'],
					),
					'allOf'                => array(
						array( 'required' => array( 'post_id' ) ),
						array(
							'anyOf' => array(
								array( 'required' => array( 'key' ) ),
								array( 'required' => array( 'meta_key' ) ),
							),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'updated' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
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
		$post_id = (int) ( $input['post_id'] ?? 0 );
		$raw_key = ! empty( $input['key'] ) ? $input['key'] : ( $input['meta_key'] ?? '' );
		$key     = sanitize_text_field( (string) $raw_key );

		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found.', 'acrossai-abilities-manager' ),
			);
		}
		if ( '' === $key ) {
			return array(
				'success' => false,
				'message' => __( 'Meta key is empty. Pass "key" (or its alias "meta_key").', 'acrossai-abilities-manager' ),
			);
		}

		$value  = array_key_exists( 'value', $input ) ? $input['value'] : ( $input['meta_value'] ?? '' );
		$result = update_post_meta( $post_id, $key, Slash_Input::slash( $value, $input ) );

		return array(
			'success' => true,
			'updated' => (bool) $result,
			/* translators: 1: meta key, 2: post ID */
			'message' => sprintf( __( 'Wrote meta "%1$s" on post #%2$d.', 'acrossai-abilities-manager' ), $key, $post_id ),
		);
	}
}
