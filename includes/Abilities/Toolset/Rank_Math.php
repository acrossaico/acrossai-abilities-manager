<?php
/**
 * The Rank Math Toolset.
 *
 * Rank Math SEO — redirections, content analysis, sitemaps, schema and status.
 *
 * Four declarations and no behaviour — everything else is
 * {@see Base_Toolset_Ability}. If this class ever needs more than these
 * methods, the shared class is missing something; add it there.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Abilities/Toolset
 * @since      0.0.34
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Toolset;

defined( 'ABSPATH' ) || exit;

/**
 * Dispatcher for the Rank Math group.
 */
final class Rank_Math extends Base_Toolset_Ability {

	/**
	 * @return string
	 */
	protected function group(): string {
		return 'rank-math';
	}

	/**
	 * @return string
	 */
	protected function slug(): string {
		return 'toolset/rank-math';
	}

	/**
	 * @return string
	 */
	protected function toolset_label(): string {
		return __( 'Rank Math', 'acrossai-abilities-manager' );
	}

	/**
	 * @return string
	 */
	protected function toolset_description(): string {
		return __( 'Work with Rank Math SEO: manage redirections and the 404 monitor, analyse and optimise content, read and change settings, manage sitemaps and schema, run instant indexing, and read analytics and module status. Only present when Rank Math is active. Narrow action=discover with sub_group: rank-math-redirections (redirects and 404 logs), rank-math-content (SEO meta, scores, audits, internal links), rank-math-status (status, backups, import/export, maintenance), rank-math-content-ai (Content AI and AI visibility), rank-math-settings (all settings writes, including the Instant Indexing settings), rank-math-instant-indexing (submitting URLs and the indexing log), rank-math-sitemap (sitemaps and llms.txt routes), rank-math-analytics, rank-math-admin (modules and role capabilities) and rank-math-schema. action=info returns schemas; action=execute runs one ability.', 'acrossai-abilities-manager' );
	}
}
