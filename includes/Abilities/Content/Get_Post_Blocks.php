<?php
/**
 * Feature 066 — return a post's parsed block tree with canonical paths.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Content
 * @since      0.0.24
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Content;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Block_Tree;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Read a post's block tree via parse_blocks() and annotate every node with a
 * canonical integer-array `path`. Read-only, idempotent, does not touch the
 * database beyond a single get_post() lookup.
 */
class Get_Post_Blocks extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array<string,mixed>
	 */
	protected function ability(): array {
		return array(
			'name' => 'blocks/get-post-blocks',
			'args' => array(
				'label'               => __( 'Get Post Blocks', 'acrossai-abilities-manager' ),
				'description'         => __( 'Return the parsed Gutenberg block tree of a post with each block annotated with its canonical integer-array path (e.g. [0, 2, 1] = 2nd grandchild of the 3rd child of the 1st top-level block).', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' ) && current_user_can( 'edit_posts' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'post_id' => array( 'type' => 'integer' ),
						'blocks'  => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
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
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	/**
	 * Feature 095 — hint that when the caller only needs to locate a block,
	 * the outline ability returns kilobytes instead of the full parsed tree
	 * (which includes every block's innerHTML).
	 *
	 * @return array<int,array<string,string>>
	 */
	protected function suggested_abilities(): array {
		return array(
			array(
				'slug'   => 'blocks/outline-post-blocks',
				'reason' => __( 'If you only need block paths, types, and short previews, this returns kilobytes even for pages that get-post-blocks would return as hundreds — same path convention, so the returned paths are drop-in usable with add-block / update-post-block / remove-block.', 'acrossai-abilities-manager' ),
				'saves'  => __( '~28K tokens on a 97 KB page', 'acrossai-abilities-manager' ),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * @param array<string,mixed> $input Ability input payload.
	 * @return array<string,mixed>
	 */
	public function execute( array $input = array() ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$blocks  = Block_Tree::parse_post_blocks( $post_id, 'read' );
		if ( is_wp_error( $blocks ) ) {
			return array(
				'success'    => false,
				'post_id'    => $post_id,
				'message'    => $blocks->get_error_message(),
				'error_code' => $blocks->get_error_code(),
			);
		}

		$annotated = Block_Tree::annotate_with_paths( $blocks );
		$total     = 0;
		Block_Tree::walk_tree(
			$blocks,
			static function () use ( &$total ): void {
				++$total;
			}
		);

		return array(
			'success' => true,
			'post_id' => $post_id,
			'blocks'  => $annotated,
			'total'   => $total,
			/* translators: 1: total block count, 2: post ID */
			'message' => sprintf( __( 'Returned %1$d blocks for post #%2$d.', 'acrossai-abilities-manager' ), $total, $post_id ),
		);
	}
}
