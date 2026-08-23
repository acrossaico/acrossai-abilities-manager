<?php
/**
 * Feature 070 — normalized theme.json style guide.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Block
 * @since      0.0.31
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Block;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Return a flat, cacheable summary of the active theme's design system:
 * spacing scale, palette, typography, layout widths, duotones, gradients.
 */
class Get_Style_Guide extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array<string,mixed>
	 */
	protected function ability(): array {
		return array(
			'name' => 'blocks/get-style-guide',
			'args' => array(
				'label'               => __( 'Get Style Guide', 'acrossai-abilities-manager' ),
				'description'         => __( 'Return a normalized summary of the active theme\'s design system: spacing scale, color palette (theme + user), typography (families + font-sizes), layout widths (contentSize / wideSize), and root duotone/gradient sets.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'    => array( 'type' => 'boolean' ),
						'spacing'    => array( 'type' => 'array' ),
						'palette'    => array( 'type' => 'array' ),
						'typography' => array( 'type' => 'object' ),
						'layout'     => array( 'type' => 'object' ),
						'duotone'    => array( 'type' => 'array' ),
						'gradients'  => array( 'type' => 'array' ),
						'message'    => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'blocks',
						'sub_group'       => 'site-editor',
						'sub_group_label' => __( 'Site Editor', 'acrossai-abilities-manager' ),
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
	 * @param array<string,mixed> $input Ability input payload.
	 * @return array<string,mixed>
	 */
	public function execute( array $input = array() ): array {
		unset( $input );

		if ( ! class_exists( '\WP_Theme_JSON_Resolver' ) ) {
			return array(
				'success'    => false,
				'spacing'    => array(),
				'palette'    => array(),
				'typography' => new \stdClass(),
				'layout'     => new \stdClass(),
				'duotone'    => array(),
				'gradients'  => array(),
				'message'    => __( 'WP_Theme_JSON_Resolver is unavailable.', 'acrossai-abilities-manager' ),
			);
		}

		$merged   = \WP_Theme_JSON_Resolver::get_merged_data();
		$settings = is_object( $merged ) && method_exists( $merged, 'get_settings' ) ? $merged->get_settings() : array();

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$spacing_sizes  = (array) ( $settings['spacing']['spacingSizes'] ?? array() );
		$palette_theme  = (array) ( $settings['color']['palette']['theme'] ?? array() );
		$palette_custom = (array) ( $settings['color']['palette']['custom'] ?? array() );
		$palette        = array_merge( $palette_theme, $palette_custom );
		$font_families  = (array) ( $settings['typography']['fontFamilies']['theme'] ?? array() );
		$font_sizes     = (array) ( $settings['typography']['fontSizes']['theme'] ?? array() );
		$duotone        = (array) ( $settings['color']['duotone']['theme'] ?? array() );
		$gradients      = (array) ( $settings['color']['gradients']['theme'] ?? array() );

		return array(
			'success'    => true,
			'spacing'    => array_values( $spacing_sizes ),
			'palette'    => array_values( $palette ),
			'typography' => array(
				'font_families' => array_values( $font_families ),
				'font_sizes'    => array_values( $font_sizes ),
			),
			'layout'     => array(
				'content_size' => sanitize_text_field( (string) ( $settings['layout']['contentSize'] ?? '' ) ),
				'wide_size'    => sanitize_text_field( (string) ( $settings['layout']['wideSize'] ?? '' ) ),
			),
			'duotone'    => array_values( $duotone ),
			'gradients'  => array_values( $gradients ),
			'message'    => __( 'Style guide returned from merged theme.json settings.', 'acrossai-abilities-manager' ),
		);
	}
}
