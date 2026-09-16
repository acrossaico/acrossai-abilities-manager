<?php
/**
 * Feature 117 — seed integration opt-ins that should start switched ON.
 *
 * {@see AcrossAI_Integration_Settings::is_enabled()} treats an absent entry as OFF, and its docblock
 * is explicit that the asymmetry is load-bearing — it is the containment for
 * BUG-SPARSE-STORAGE-UNIFORM-DEFAULT-ASSUMPTION, and that class is the only path that reads opt-in
 * state. Giving one integration a different default by teaching `is_enabled()` a per-slug default
 * would reintroduce exactly the mixed-default confusion that bug records.
 *
 * So a default-on integration is expressed as a stored `true` written once, rather than as an absent
 * entry that reads differently depending on the slug. Absent still means off; the seeded slug is
 * simply never absent.
 *
 * The seed runs ONCE, guarded by its own option, and only fills slugs that have no stored value.
 * That ordering is what lets an administrator switch WPForms writing off and have it stay off: the
 * slug then holds `false`, which is a stored value, so a later run skips it.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Library
 * @since      0.0.47
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Library;

defined( 'ABSPATH' ) || exit;

/**
 * One-time seeding of integration opt-ins whose default is ON.
 *
 * @since 0.0.47
 */
final class AcrossAI_Integration_Default_Opt_Ins {

	/**
	 * Guard option. Presence means the seed has already run.
	 *
	 * @since 0.0.47
	 * @var   string
	 */
	public const DONE_OPTION = 'acrossai_integration_default_opt_ins_done';

	/**
	 * Integration slugs that start switched on.
	 *
	 * `wpforms` is on by default because it is already the site's behaviour: WPForms' own write gate
	 * lives in a `permission_callback` that the ability override processor replaces, so writes are
	 * permitted today whatever WPForms' switch says. Seeding `true` changes nothing that works; the
	 * value of the toggle is its OFF position, which currently exists nowhere. See
	 * {@see \AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\WPForms} and issue #210.
	 *
	 * @since 0.0.47
	 * @var   string[]
	 */
	private const DEFAULT_ON = array( 'wpforms' );

	/**
	 * Private constructor — static utility (DEC-UTILITY-STATIC-ONLY).
	 */
	private function __construct() {}

	/**
	 * Seed the default-on slugs, once.
	 *
	 * Hooked early enough to matter: the opt-in base attaches its enable filter at
	 * `plugins_loaded` P20, so a seed that ran later would leave the first request of the site's
	 * life with the integration reading as off.
	 *
	 * @since  0.0.47
	 * @return void
	 */
	public static function maybe_seed(): void {
		if ( get_site_option( self::DONE_OPTION ) ) {
			return;
		}

		update_site_option( self::DONE_OPTION, '1' );

		$stored = get_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$changed = false;

		foreach ( self::DEFAULT_ON as $slug ) {
			// Only ever fills a HOLE. A slug already carrying false is a decision someone made, and
			// overwriting it would turn a deliberate opt-out back on behind their back.
			if ( array_key_exists( $slug, $stored ) ) {
				continue;
			}

			$stored[ $slug ] = true;
			$changed         = true;
		}

		if ( $changed ) {
			update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, $stored );
		}
	}
}
