<?php
/**
 * The site guide, for a client connected to Elementor's MCP server.
 *
 * {@see \AcrossAI_Abilities_Manager\Includes\Abilities\Toolset\Guide} written for a different
 * reader. The first-party guide addresses a client holding fifteen dispatchers and explains how to
 * choose between them. This one addresses a client holding Elementor's twenty tools plus one
 * gateway, and its job is to say that the gateway exists, what is behind it, and when to prefer it
 * over the elementor-* tools it arrived with.
 *
 * The four sections — the calling convention, what this site holds, the two unusual Toolsets, the
 * error codes — are inherited rather than restated, so the two guides cannot drift about how a
 * Toolset is called. Only the slug, label, description and connect-time copy differ.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Abilities/Elementor/Mcp
 * @since      0.0.42
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Elementor\Mcp;

use AcrossAI_Abilities_Manager\Includes\Abilities\Toolset\Guide;

defined( 'ABSPATH' ) || exit;

/**
 * A one-call briefing for a client that reached this site through Elementor.
 */
final class Site_Guide extends Guide {

	/**
	 * This guide's slug.
	 *
	 * Under `toolset/` for the two reasons given on {@see Site_Toolset::SLUG}: the prefix is what
	 * `ROUTER_PREFIXES` and `protect_own_slug()` key on, and `elementor/` is Elementor's namespace
	 * to register in, not ours.
	 *
	 * Shaped `<family>/<something>-guide` like its two siblings — `toolset/server-guide` and the
	 * transport's `mcp-adapter/server-guide` — so an assistant that has met one knows what to look
	 * for here.
	 *
	 * @since 0.0.42
	 * @var   string
	 */
	public const SLUG = 'toolset/elementor-site-guide';

	/**
	 * Built for a third-party host, so kept out of our own surfaces.
	 *
	 * {@see Guide::is_host_only()}. Without it this would supply the connect message for AcrossAI
	 * servers as well, which already have their own guide, and would be offered in the transport's
	 * Tools picker as though an operator might sensibly add it to an AcrossAI server.
	 *
	 * @since  0.0.42
	 * @return bool
	 */
	protected function is_host_only(): bool {
		return true;
	}

	/**
	 * Every area is reached through the gateway, because that is all this client has.
	 *
	 * The inherited answer is "call it directly — it is in the default set, so your tool list has
	 * it", which is true on an AcrossAI server and false here. A client on Elementor's server holds
	 * Elementor's twenty tools plus these two; told to call `toolset/database` directly it would
	 * reach for a tool that is not there, which is exactly the fault the guide exists to prevent.
	 *
	 * @since  0.0.42
	 * @param  string $name     The toolset's ability name.
	 * @param  string $group    Its group slug.
	 * @param  bool   $indirect Unused here — every row is indirect from this server.
	 * @param  bool   $empty    Whether its group currently holds nothing.
	 * @return string
	 */
	protected function call_route( string $name, string $group, bool $indirect, bool $empty ): string {
		unset( $name, $indirect );

		$route = sprintf(
			/* translators: 1: the site toolset's ability name, 2: the area slug. */
			__( 'Call %1$s with action=discover and area=%2$s. It is not in your tool list as a tool of its own — on this server everything travels through %1$s.', 'acrossai-abilities-manager' ),
			Site_Toolset::SLUG,
			$group
		);

		if ( ! $empty ) {
			return $route;
		}

		return $route . ' ' . __( 'It holds nothing at the moment, and that changes when a plugin or theme is activated, so call discover rather than ruling it out for the rest of this session.', 'acrossai-abilities-manager' );
	}

	/**
	 * The two unusual areas, named as this client can actually reach them.
	 *
	 * Same correction as `call_route()`: the inherited text tells the reader to call
	 * `toolset/integrations`, which is not on this server either.
	 *
	 * @since  0.0.42
	 * @return array<string, string>
	 */
	protected function special(): array {
		return array(
			Site_Toolset::SLUG => __( 'The one way to anything outside the Elementor editor. Call it with action=discover and no arguments for a menu of areas with a count each, then discover again with area=<area>. Two areas are worth knowing about: "other" holds abilities that matched no area, by fallthrough rather than assignment, so it is often empty; and the plugin areas appear and disappear as plugins are activated. Your tool list was fixed when you connected and cannot be refreshed, so call discover again rather than reusing an earlier answer.', 'acrossai-abilities-manager' ),
		);
	}

	/**
	 * @since  0.0.42
	 * @return string
	 */
	protected function label(): string {
		return __( 'WordPress Site Guide', 'acrossai-abilities-manager' );
	}

	/**
	 * Written for a reader who came here to edit pages.
	 *
	 * It leads with the boundary rather than the calling convention: the thing this client does not
	 * know is that the rest of WordPress is reachable at all.
	 *
	 * @since  0.0.42
	 * @return string
	 */
	protected function description(): string {
		return __( 'Read this first if you need anything outside the Elementor editor. This site also exposes the rest of WordPress through one tool — posts and media, themes, settings, users, the database, files, cron, updates, and every other plugin installed here. This guide lists what is available with a count per area, explains the three actions that reach it, gives the real limits, and names the error codes worth recognising. Takes no input and changes nothing.', 'acrossai-abilities-manager' );
	}
}
