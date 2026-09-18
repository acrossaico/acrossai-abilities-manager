<?php
/**
 * The Orientation ability — what this site is, before an assistant acts on it.
 *
 * Every other entry in the tool list answers "what can I do?". This one answers
 * "what am I working on, and what should I know first?" — the questions whose
 * answers are currently spread across a dozen Toolset descriptions, repeated in
 * each, and paid for on every connection whether or not they are relevant.
 *
 * Deliberately NOT a Toolset. A Toolset dispatches to a group of abilities;
 * this dispatches to nothing and takes no input. It borrows the same three
 * declarations a Toolset makes to the transport — tool-level, server-type
 * default, protected slug — because those are about being a tool, not about
 * being a dispatcher.
 *
 * Read-only and side-effect free. It reports; it never changes anything.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Abilities/Toolset
 * @since      0.0.37
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Toolset;

use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Group;

defined( 'ABSPATH' ) || exit;

/**
 * Site orientation for a connected assistant.
 */
final class Orientation {

	/**
	 * This ability's slug.
	 *
	 * @since 0.0.37
	 * @var   string
	 */
	public const SLUG = 'acrossai/site-orientation';

	/**
	 * Wire registration. Mirrors Base_Toolset_Ability, which hooks the same three.
	 *
	 * @since 0.0.37
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_init', array( $this, 'register' ), 20 );
		add_filter( 'acrossai_mcp_manager_tool_abilities', array( $this, 'declare_tool_level_ability' ) );
		add_filter( 'acrossai_mcp_server_types', array( $this, 'declare_server_type_tool' ) );
	}

	/**
	 * @since 0.0.37
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) || wp_has_ability( self::SLUG ) ) {
			return;
		}

		wp_register_ability(
			self::SLUG,
			array(
				'label'               => __( 'Site Orientation', 'acrossai-abilities-manager' ),
				'description'         => __( 'Read this first on an unfamiliar site. Reports what this WordPress install actually is — versions, locale, active theme, how its pages are built and which capability areas are present — plus the handful of rules that decide whether an edit will stick. Takes no input and changes nothing. Cheaper than guessing: the page-builder answer alone determines whether the Content and Blocks tools will work at all on a given page.', 'acrossai-abilities-manager' ),
				'category'            => Base_Toolset_Ability::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'site'        => array( 'type' => 'object' ),
						'content'     => array( 'type' => 'object' ),
						'capability'  => array( 'type' => 'object' ),
						'rules'       => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
					'required'   => array( 'site', 'content', 'capability', 'rules' ),
				),
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);
	}

	/**
	 * Reporting what is installed is not sensitive, but it is still site detail.
	 *
	 * @since  0.0.37
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'read' );
	}

	/**
	 * @since  0.0.37
	 * @return array<string, mixed>
	 */
	public function execute(): array {
		$counts = AcrossAI_Ability_Group::counts();
		$theme  = wp_get_theme();

		return array(
			'site'       => array(
				'wordpress' => get_bloginfo( 'version' ),
				'php'       => PHP_VERSION,
				'locale'    => get_locale(),
				'multisite' => is_multisite(),
				'theme'     => $theme->get( 'Name' ),
				'block_theme' => function_exists( 'wp_is_block_theme' ) && wp_is_block_theme(),
			),
			'content'    => $this->content_shape( $counts ),
			'capability' => array(
				'areas'           => count( $counts ),
				'total_abilities' => array_sum( $counts ),
				'largest'         => array_slice( array_keys( $counts ), 0, 5 ),
			),
			'rules'      => $this->rules( $counts ),
		);
	}

	/**
	 * How this site's pages are built — the single most consequential fact.
	 *
	 * An assistant that edits `post_content` on an Elementor page gets a success
	 * response, changes nothing a visitor sees, and has its edit reverted the
	 * next time the page is opened in the builder. Answering this once, up
	 * front, is worth more than any other line in this response.
	 *
	 * @since  0.0.37
	 * @param  array<string, int> $counts Group member counts.
	 * @return array<string, mixed>
	 */
	private function content_shape( array $counts ): array {
		$builders = array_values(
			array_filter(
				array( 'elementor', 'beaver-builder', 'breakdance', 'bricks', 'divi' ),
				static function ( string $slug ) use ( $counts ): bool {
					return isset( $counts[ $slug ] );
				}
			)
		);

		return array(
			'page_builders' => $builders,
			'note'          => array() === $builders
				? __( 'No page builder detected. Pages are block or classic content, so the Content and Blocks tools edit what visitors actually see.', 'acrossai-abilities-manager' )
				: __( 'A page builder is active. It stores layout OUTSIDE post_content, so run content/inspect-post-builder before editing any existing page: an update through Content or Blocks can report success, change nothing a visitor sees, and be reverted the next time the page is saved in the builder.', 'acrossai-abilities-manager' ),
		);
	}

	/**
	 * The cross-cutting rules, stated once.
	 *
	 * These are currently repeated across several Toolset descriptions, where
	 * every connection pays for them whether or not they apply. Said once here,
	 * they can eventually come out of there.
	 *
	 * @since  0.0.37
	 * @param  array<string, int> $counts Group member counts.
	 * @return string[]
	 */
	private function rules( array $counts ): array {
		$rules = array(
			__( 'Every tool takes action=discover to list what it holds, action=info for one ability\'s schemas, and action=execute to run it. Call info before execute rather than guessing parameters.', 'acrossai-abilities-manager' ),
			__( 'Capability from installed plugins lives behind toolset/integrations, including plugins that are not in your tool list. Call its discover when nothing else matches.', 'acrossai-abilities-manager' ),
			__( 'Your tool list was fixed when you connected. If this site has a capability you cannot see a tool for, it is reachable through toolset/integrations rather than absent.', 'acrossai-abilities-manager' ),
		);

		if ( isset( $counts['database'] ) ) {
			$rules[] = __( 'The Database tool runs direct SQL against live data. Prefer a purpose-built ability over a query whenever one exists.', 'acrossai-abilities-manager' );
		}

		return $rules;
	}

	/**
	 * Advertise this as a tool-level entry, like a Toolset.
	 *
	 * @since  0.0.37
	 * @param  mixed $slugs Slugs collected so far.
	 * @return mixed
	 */
	public function declare_tool_level_ability( $slugs ) {
		if ( ! is_array( $slugs ) ) {
			return $slugs;
		}

		$slugs[] = self::SLUG;

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * A default of the `acrossai` server type — it is stable and always relevant.
	 *
	 * @since  0.0.37
	 * @param  mixed $types Types collected so far.
	 * @return mixed
	 */
	public function declare_server_type_tool( $types ) {
		if ( ! is_array( $types ) || ! isset( $types['acrossai'] ) || ! is_array( $types['acrossai'] ) ) {
			return $types;
		}

		$tools   = isset( $types['acrossai']['tools'] ) && is_array( $types['acrossai']['tools'] )
			? $types['acrossai']['tools']
			: array();
		$tools[] = self::SLUG;

		$types['acrossai']['tools'] = array_values( array_unique( $tools ) );

		return $types;
	}
}
