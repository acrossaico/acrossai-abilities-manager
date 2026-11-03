<?php
/**
 * WP Rollback's toolset declaration.
 *
 * The "we supply everything" shape, and emphatically so: WP Rollback registers **no** abilities of
 * its own. Verified rather than assumed — activating it left the registry at 388 abilities, and
 * `wp_register_ability` appears nowhere in its 130 PHP files. So every ability in this group is one
 * of ours, declared under `includes/Abilities/Versions/` and carrying
 * `meta.acrossai.tab_group = 'wp-rollback'`.
 *
 * `ability_prefixes()` is empty for two reasons: the host ships nothing to claim, and the
 * `versions/` namespace is shared with the core-pinning abilities, which belong to the WP Downgrade
 * group. Claiming the prefix would pull those into this one.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Abilities/Integrations
 * @since      0.0.45
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Integrations;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\WpRollback\Wp_Rollback_Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Groups this plugin's WP Rollback abilities.
 *
 * @since 0.0.45
 */
final class Wp_Rollback implements AcrossAI_Toolset_Integration {

	/**
	 * Toolset key.
	 *
	 * Must equal the `tab_group` the abilities under `includes/Abilities/Versions/` declare. If the
	 * two drift, the abilities land in one group and the dispatcher serves another.
	 *
	 * @since 0.0.45
	 * @var   string
	 */
	public const TAB_GROUP = 'wp-rollback';

	/**
	 * @since  0.0.45
	 * @return string
	 */
	public function group(): string {
		return self::TAB_GROUP;
	}

	/**
	 * @since  0.0.45
	 * @return string
	 */
	public function toolset_label(): string {
		return __( 'WP Rollback', 'acrossai-abilities-manager' );
	}

	/**
	 * @since  0.0.45
	 * @return string
	 */
	public function toolset_description(): string {
		return __(
			'Put an installed plugin or theme onto a version you choose, rather than only the latest. List every release WordPress.org published for it, pre-flight the move — including whether other sites on a multisite use it — and roll it back. Two limits are worth knowing up front: WordPress core is not covered, because WP Rollback supports plugins and themes only; and release history comes from WordPress.org, so a premium or privately-hosted asset has none there and can only be restored to a version this site already holds a local archive of. Listing versions works without WP Rollback installed; moving versions does not.',
			'acrossai-abilities-manager'
		);
	}

	/**
	 * Nothing to claim: the host plugin ships no abilities.
	 *
	 * @since  0.0.45
	 * @return string[]
	 */
	public function ability_prefixes(): array {
		return array();
	}

	/**
	 * @since  0.0.45
	 * @return bool
	 */
	public function is_active(): bool {
		return Wp_Rollback_Guard::is_active();
	}
}
