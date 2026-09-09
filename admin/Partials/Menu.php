<?php
/**
 * Admin Menu Page for AcrossAI Abilities Manager
 *
 * Main admin page with interface for Abilities and Overrides management.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/Admin/Partials
 * @since      0.0.1
 */

namespace AcrossAI_Abilities_Manager\Admin\Partials;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Menu class for admin page content
 *
 * @since 0.0.1
 */
class Menu {

	/**
	 * Parent menu slug shared with the rest of the AcrossAI suite.
	 *
	 * @since 0.0.34
	 * @var   string
	 */
	public const PARENT_SLUG = 'acrossai';

	/**
	 * Menu slug of the Abilities page.
	 *
	 * @since 0.0.34
	 * @var   string
	 */
	public const ABILITIES_SLUG = 'acrossai-abilities-manager';

	/**
	 * URL-literal menu slug of the Quick Connect sidebar link.
	 *
	 * @since 0.0.34
	 * @var   string
	 */
	public const QUICK_CONNECT_SLUG = 'admin.php?page=acrossai-abilities-manager&quick-connect=1&step=1';

	/**
	 * Plugin name
	 *
	 * @since 0.0.1
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version
	 *
	 * @since 0.0.1
	 * @var string
	 */
	private $version;

	/**
	 * Initialize the class and set its properties
	 *
	 * @since 0.0.1
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the Abilities submenu under the shared `acrossai` parent menu.
	 *
	 * Feature 038: the page is no longer a top-level menu. The shared parent
	 * menu is owned by the `acrossai-co/main-menu` package and is bootstrapped
	 * from acrossai-abilities-manager.php on plugins_loaded priority 0. The
	 * menu_slug `acrossai-abilities-manager` is preserved so existing
	 * bookmarked URLs (wp-admin/admin.php?page=acrossai-abilities-manager) and
	 * the JS bundle handles continue to resolve.
	 *
	 * Position 1 places this submenu immediately after the host Settings entry,
	 * matching the agreed sidebar order: Settings, Abilities, Library, Logs,
	 * Add-ons.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Abilities Manager', 'acrossai-abilities-manager' ),
			__( 'Abilities', 'acrossai-abilities-manager' ),
			'manage_options',
			self::ABILITIES_SLUG,
			array( $this, 'contents' ),
			1
		);

		// Feature 099: a direct link to the wizard, not a page of its own.
		//
		// A URL-literal `menu_slug` with an empty render callback makes WordPress
		// output the sidebar item as a plain link. The wizard is served by this
		// same page's `contents()` dispatcher when `?quick-connect=1` is present,
		// so registering a second page would create a duplicate capability
		// surface for one onboarding flow.
		//
		// The sidebar uses the short label. Every other surface says "Quick
		// Connect via AcrossAI"; inside the AcrossAI menu that tail is redundant,
		// which is the same call the sibling plugin made for its own entry.
		//
		// Skipped entirely on sites running AcrossAI MCP Manager: it registers its
		// own "Quick Connect" under this same parent, and one sidebar does not
		// need two onboarding wizards.
		//
		// No position is requested here. Several plugins register into this shared
		// parent at overlapping positions and WordPress's collision handling then
		// shuffles items away from their declared slots, so the final placement is
		// settled by reorder_submenu() on a late admin_menu pass instead.
		if ( ! QuickConnect\EntryPoints::is_available() ) {
			return;
		}

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Quick Connect', 'acrossai-abilities-manager' ),
			__( 'Quick Connect', 'acrossai-abilities-manager' ),
			'manage_options',
			self::QUICK_CONNECT_SLUG,
			''
		);
	}

	/**
	 * Render admin page content
	 *
	 * Displays the main Abilities Manager interface.
	 * Execution Logs are available as a dedicated submenu page (Feature 006).
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function contents() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'acrossai-abilities-manager' ) );
		}

		// Feature 099: the Quick Connect wizard renders in place of the abilities
		// table when `?quick-connect=1` is present, so onboarding needs no second
		// admin page and no additional capability surface
		// (DEC-ADMIN-UI-NOT-MODULE). Everything else about this page is unchanged.
		$quick_connect = QuickConnect\QuickConnectPage::instance();

		if ( $quick_connect->is_quick_connect_request() ) {
			$quick_connect->render();
			return;
		}
		?>
		<div class="wrap acrossai-abilities-manager-wrap">
			<!-- Main Abilities Manager React app -->
			<div id="acrossai-abilities-root"></div>
		</div>
		<?php
	}

	/**
	 * Move the Quick Connect link so it directly follows Abilities.
	 *
	 * Hooked to `admin_menu` at a late priority, after every plugin sharing the
	 * `acrossai` parent has registered.
	 *
	 * add_submenu_page()'s $position cannot express "after this other item". It
	 * takes an absolute slot, and when that slot is taken WordPress nudges the
	 * entry to a fractional key — so the rendered order depends on which plugins
	 * are active and in what order they registered. Asking for a number that
	 * happened to look right on one site is how the link ends up detached from
	 * Abilities on the next one. Reordering the finished array is the only way to
	 * state the actual requirement.
	 *
	 * @since  0.0.34
	 * @return void
	 */
	public function reorder_submenu(): void {
		global $submenu;

		if ( empty( $submenu[ self::PARENT_SLUG ] ) || ! is_array( $submenu[ self::PARENT_SLUG ] ) ) {
			return;
		}

		$items       = array_values( $submenu[ self::PARENT_SLUG ] );
		$quick_index = $this->find_submenu_index( $items, self::QUICK_CONNECT_SLUG );

		if ( null === $quick_index ) {
			return;
		}

		$quick_item = $items[ $quick_index ];
		array_splice( $items, $quick_index, 1 );

		// Located after the removal, so the index refers to the shortened array.
		$abilities_index = $this->find_submenu_index( $items, self::ABILITIES_SLUG );

		if ( null === $abilities_index ) {
			return;
		}

		array_splice( $items, $abilities_index + 1, 0, array( $quick_item ) );

		$submenu[ self::PARENT_SLUG ] = $items; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- deliberate reordering of this plugin's own submenu entries.
	}

	/**
	 * Locate a submenu entry by its menu slug.
	 *
	 * @since  0.0.34
	 * @param  array<int, array<int, string>> $items Submenu entries.
	 * @param  string                         $slug  Menu slug to match.
	 * @return int|null Index, or null when absent.
	 */
	private function find_submenu_index( array $items, string $slug ): ?int {
		foreach ( $items as $index => $item ) {
			if ( isset( $item[2] ) && $slug === $item[2] ) {
				return $index;
			}
		}

		return null;
	}
}
