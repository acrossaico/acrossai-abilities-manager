<?php
/**
 * One gateway to the whole site, for a client connected to Elementor's MCP server.
 *
 * Every other Toolset covers one area. This one covers all of them, because of where it is used: a
 * client on `/wp-json/elementor/mcp` has Elementor's twenty tools and whatever we contribute
 * beside them, and contributing fifteen dispatchers to somebody else's server would bury the
 * twenty they shipped. So the areas become a menu this tool returns, rather than entries in the
 * client's tool list.
 *
 * The shape is `toolset/integrations`' — answer a bare `discover` with the map, not the contents —
 * applied to every group instead of the plugin ones. Measured on this site: the menu is ~456
 * tokens for fourteen rows against ~6,752 to list the same catalogue flat, and it is also what
 * lets a model route to `database` or `mailerpress` instead of guessing.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Abilities/Elementor/Mcp
 * @since      0.0.42
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Elementor\Mcp;

use AcrossAI_Abilities_Manager\Includes\Abilities\Toolset\Base_Toolset_Ability;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Group;
use WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Dispatcher spanning every ability group on the site.
 */
final class Site_Toolset extends Base_Toolset_Ability {

	/**
	 * This Toolset's slug.
	 *
	 * Under `toolset/`, not `elementor/`, and both halves of that matter.
	 *
	 * `toolset/` is not decoration: `AcrossAI_Ability_Override_Processor::ROUTER_PREFIXES` lists it
	 * as a prefix whose permission callback may never be replaced by an operator override, which a
	 * dispatcher routing to eight hundred abilities needs, and `protect_own_slug()` keys on it too.
	 * Under another prefix this would silently lose both.
	 *
	 * `elementor/` is Elementor's namespace. Registering there would squat it, and this tool is not
	 * Elementor's — it is ours, published onto their server.
	 *
	 * @since 0.0.42
	 * @var   string
	 */
	public const SLUG = 'toolset/elementor-site';

	/**
	 * Identity, not membership.
	 *
	 * No ability carries `elementor-site` as its group — membership comes from `groups()`, which is
	 * every group on the site. This is what the Toolset reports as itself and what a slug collision
	 * would be logged against.
	 *
	 * Deliberately not `elementor`: that group is the real Elementor ability suite, fronted by
	 * `toolset/elementor`. Claiming it here would collide with a live dispatcher.
	 *
	 * @since  0.0.42
	 * @return string
	 */
	protected function group(): string {
		return 'elementor-site';
	}

	/**
	 * @since  0.0.42
	 * @return string
	 */
	protected function slug(): string {
		return self::SLUG;
	}

	/**
	 * @since  0.0.42
	 * @return string
	 */
	protected function toolset_label(): string {
		return __( 'WordPress Site Tools', 'acrossai-abilities-manager' );
	}

	/**
	 * Written for an Elementor audience, which is the point of a dedicated Toolset.
	 *
	 * A client here came to edit pages. It does not know this site also exposes the database, cron,
	 * the filesystem, users, updates and every installed plugin's capability, and nothing in
	 * Elementor's twenty tools suggests it. So the description leads with the boundary — everything
	 * beyond the Elementor editor — and says `discover` returns a menu rather than a wall.
	 *
	 * @since  0.0.42
	 * @return string
	 */
	protected function toolset_description(): string {
		return __( 'Everything this WordPress site can do beyond the Elementor editor: posts and pages, media, blocks and templates, themes and appearance, settings, users, the database, files, cron, caching, updates and diagnostics — plus the capability of every other plugin installed here, from SEO and forms to ecommerce and email. Hundreds of abilities, so this does not list them: call action=discover with no arguments and it returns a short menu of areas with a count for each. Then call discover again with area=<area> for one area\'s abilities, or search to match by name across all of them. action=info returns one ability\'s parameters; action=execute runs it. Use this for anything outside the Elementor editor — Elementor\'s own tools are the ones named elementor-*.', 'acrossai-abilities-manager' );
	}

	/**
	 * Every ability group on the site.
	 *
	 * Read from the registry rather than listed here, so a new integration or a new first-party
	 * group is reachable through this gateway with no edit in this file.
	 *
	 * @since  0.0.42
	 * @return string[]
	 */
	protected function groups(): array {
		return array_values( array_map( 'strval', array_keys( AcrossAI_Ability_Group::counts() ) ) );
	}

	/**
	 * Never part of the AcrossAI server type's default set.
	 *
	 * AcrossAI's own servers already serve the fourteen Toolsets directly, each with its own
	 * description, which is a better answer than one gateway in front of all of them. This exists
	 * for servers that cannot do that.
	 *
	 * @since  0.0.42
	 * @return bool
	 */
	protected function is_server_type_default(): bool {
		return false;
	}

	/**
	 * Built for a third-party host, so kept out of our own surfaces entirely.
	 *
	 * {@see Base_Toolset_Ability::is_host_only()} for what that excludes and why. Without it this
	 * would be offered in the transport's Tools picker and named by `toolset/server-guide` as an
	 * empty default — telling a client on an AcrossAI server to call a tool it does not have.
	 *
	 * @since  0.0.42
	 * @return bool
	 */
	protected function is_host_only(): bool {
		return true;
	}

	/**
	 * Membership moves with the site, so a cached listing goes stale.
	 *
	 * True for the same reason as `toolset/integrations`, and more so: this spans every group, so
	 * any plugin activation changes the answer.
	 *
	 * @since  0.0.42
	 * @return bool
	 */
	protected function is_volatile(): bool {
		return true;
	}

	/**
	 * Lean rows by default — the one change the measurements forced.
	 *
	 * The inherited default returns every field, and a drill into one area then costs **10,299**
	 * tokens for fifty rows, because each carries its full description. Names and labels are
	 * **1,093**. A model asking "what is in `database`?" wants to pick one, not read fifty essays,
	 * and will not think to ask for less — so the cheap answer is the default and detail is paid
	 * for deliberately, via `include_fields`.
	 *
	 * Only when the caller named no fields. An explicit `include_fields` is always honoured as
	 * given, including `include_fields=["description"]` to get the full rows back.
	 *
	 * **`discover` only.** This helper also feeds `action=info`, where the fields ARE the answer —
	 * defaulting there to name and label would strip the input and output schemas off every info
	 * call and leave a model guessing at parameters, which is the one thing info exists to prevent.
	 *
	 * @since  0.0.42
	 * @param  array<string, mixed> $input Caller input.
	 * @return string[]|null
	 */
	protected function requested_fields( array $input ): ?array {
		$fields = parent::requested_fields( $input );

		if ( null !== $fields ) {
			return $fields;
		}

		$action = isset( $input['action'] ) ? (string) $input['action'] : '';

		return 'discover' === $action ? array( 'name', 'label' ) : null;
	}

	/**
	 * Answer a bare `discover` with the areas, not their abilities.
	 *
	 * Modelled on `Toolset\Integrations::discover_overview()`, which solves the same problem for
	 * the plugin groups. The difference is only the vocabulary: the caller narrows with `area`,
	 * which is `plugin` under a name that is not a lie for `database` or `cron`.
	 *
	 * NOT `sub_group`, which was the first draft's mistake and is the one `Integrations` warns
	 * about in a comment: a sub-group is a division WITHIN a group, so it never equals a group
	 * name, and `sub_group=database` returned an empty list with nothing to explain why.
	 *
	 * @since  0.0.42
	 * @param  array<string, mixed> $input Caller input.
	 * @return array<string, mixed>|null
	 */
	protected function discover_overview( array $input ): ?array {
		$narrowed = false;

		foreach ( array( 'area', 'sub_group', 'plugin', 'search', 'card' ) as $key ) {
			if ( isset( $input[ $key ] ) && '' !== (string) $input[ $key ] ) {
				$narrowed = true;
				break;
			}
		}

		if ( $narrowed ) {
			return null;
		}

		// `member_counts()`, not `AcrossAI_Ability_Group::counts()`: the latter includes abilities
		// this gateway cannot dispatch to, so the menu promised `elementor 106` while the drill
		// returned 99. A menu that overstates is worse than a smaller one — a caller cannot tell
		// which rows are real.
		$counts = $this->member_counts( 'discover' );
		$areas  = array();
		$total  = 0;

		foreach ( $counts as $group => $count ) {
			$group = (string) $group;
			$count = (int) $count;

			if ( 0 === $count || $this->group() === $group ) {
				continue;
			}

			$row = array(
				'area'      => $group,
				'abilities' => $count,
			);

			// Label and one-line summary read off the group's own dispatcher rather than restated
			// here — the same technique `Integrations::discover_overview()` and `Guide::toolsets()`
			// use, so a new group needs no edit in this file and the three cannot drift.
			$toolset = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'toolset/' . $group ) : null;

			if ( $toolset instanceof WP_Ability ) {
				$row['label']  = $toolset->get_label();
				$row['covers'] = self::summarise( (string) $toolset->get_description() );
			}

			$areas[] = $row;
			$total  += $count;
		}

		if ( array() === $areas ) {
			return array(
				'action'  => 'discover',
				'group'   => $this->group(),
				'success' => true,
				'areas'   => array(),
				'message' => __( 'No abilities are registered on this site right now.', 'acrossai-abilities-manager' ),
			);
		}

		return array(
			'action'          => 'discover',
			'group'           => $this->group(),
			'success'         => true,
			'areas'           => $areas,
			'total_areas'     => count( $areas ),
			'total_abilities' => $total,
			'hint'            => __( 'Pick an area and call discover again with area=<area>, or pass search=<term> to match by name across every area at once. Rows come back as name and label; add include_fields=["description"] for the full text. Then action=info with ability=<name> for its parameters, and action=execute with ability and parameters to run it.', 'acrossai-abilities-manager' ),
		);
	}

	/**
	 * One line about what an area covers — enough to choose, not the essay.
	 *
	 * The same trim `Toolset\Guide` applies, and for the same reason: fourteen full descriptions
	 * would cost more than the menu is meant to save. Split on a full stop only — these
	 * descriptions are written as "Subject: detail, detail." and splitting on the colon yields a
	 * stub that tells a model nothing the label did not.
	 *
	 * @since  0.0.42
	 * @param  string $description The dispatcher's full description.
	 * @return string
	 */
	private static function summarise( string $description ): string {
		$limit    = 120;
		$sentence = trim( (string) preg_split( '/(?<=\.)\s+/', $description, 2 )[0] );

		if ( '' === $sentence || mb_strlen( $sentence ) <= $limit ) {
			return $sentence;
		}

		$cut  = mb_substr( $sentence, 0, $limit );
		$last = mb_strrpos( $cut, ' ' );

		return ( false === $last ? $cut : mb_substr( $cut, 0, $last ) ) . '…';
	}
}
