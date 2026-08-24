<?php
/**
 * The core plugin class file.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/acrosswp/acrossai-abilities-manager
 * @since      0.0.1
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      0.0.1
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes
 * @author     AcrossWP <deepak@acrosswp.com>
 */
final class Main {

	/**
	 * The single instance of the class.
	 *
	 * @var AcrossAI_Abilities_Manager
	 * @since 0.0.1
	 */
	protected static $instance = null;

	/**
	 * The autoloader instance.
	 *
	 * @since    0.0.1
	 * @access   protected
	 * @var      Autoloader    $autoloader    The plugin autoloader instance.
	 */
	protected $autoloader;

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    0.0.1
	 * @access   protected
	 * @var      AcrossAI_Abilities_Manager_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    0.0.1
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The plugin dir path
	 *
	 * @since    0.0.1
	 * @access   protected
	 * @var      string    $plugin_path    The string for plugin dir path
	 */
	protected $plugin_path;

	/**
	 * The current version of the plugin.
	 *
	 * @since    0.0.1
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	protected $plugin_dir;

	/**
	 * Boot-resilience flag set by load_composer_dependencies() when
	 * vendor/autoload_packages.php is absent. When true, __construct()
	 * registers a manage_options-gated admin notice and returns before
	 * load_dependencies() — preventing the AcrossAI_Loader class-not-found
	 * fatal at line 228 that was the reproducer for Feature 038 (Constitution
	 * §V Integration Resilience).
	 *
	 * @since 0.0.1
	 * @var bool
	 */
	private bool $vendor_missing = false;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    0.0.1
	 */
	private function __construct() {

		$this->define_constants();

		$this->plugin_name = 'acrossai-abilities-manager';
		$this->version     = ACROSSAI_ABILITIES_MANAGER_VERSION;

		// Load the autoloader class manually before registering it.
		$this->plugin_dir = ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH;

		$this->load_composer_dependencies();

		// Feature 038 boot resilience (T011 / SEC-001 / DEC-FAIL-OPEN-NOTICE):
		// When vendor/autoload_packages.php is absent the PSR-4 autoloader
		// is not registered, so referencing any plugin-namespaced class
		// would fatal. Register a self-contained admin notice (closure
		// uses only WP globals — no $this->, no FQCN under
		// AcrossAI_Abilities_Manager\…) and short-circuit before
		// load_dependencies() and load_hooks().
		if ( $this->vendor_missing ) {
			add_action(
				'admin_notices',
				static function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return;
					}
					printf(
						'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
						esc_html__( 'AcrossAI Abilities Manager:', 'acrossai-abilities-manager' ),
						esc_html__( 'The Composer autoloader is missing. Run "composer install" inside the plugin directory to restore functionality.', 'acrossai-abilities-manager' )
					);
				}
			);
			return;
		}

		$this->load_dependencies();

		$this->load_hooks();
	}

	/**
	 * Main AcrossAI_Abilities_Manager Instance.
	 *
	 * Ensures only one instance of WooCommerce is loaded or can be loaded.
	 *
	 * @since 0.0.1
	 * @static
	 * @see AcrossAI_Abilities_Manager()
	 * @return AcrossAI_Abilities_Manager - Main instance.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Define WCE Constants
	 */
	private function define_constants() {

		$this->define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_BASENAME', plugin_basename( \ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE ) );
		$this->define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH', plugin_dir_path( \ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE ) );
		$this->define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL', plugin_dir_url( \ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE ) );
		$this->define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_NAME_SLUG', $this->plugin_name );
		$this->define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_NAME', 'AcrossAI Abilities Manager' );
		$this->define( 'ACROSSAI_ABILITIES_MANAGER_VERSION', '0.0.30' );
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param  string      $acrossai_name  The constant name.
	 * @param  string|bool $acrossai_value The constant value.
	 */
	private function define( $acrossai_name, $acrossai_value ) {
		if ( ! defined( $acrossai_name ) ) {
			define( $acrossai_name, $acrossai_value );
		}
	}

	/**
	 * Register all the hook once all the active plugins are loaded
	 *
	 * Uses the plugins_loaded to load all the hooks and filters
	 *
	 * @since    0.0.1
	 * @access   private
	 */
	public function load_hooks() {

		/**
		 * Check if plugin can be loaded safely or not
		 *
		 * @since    0.0.1
		 */
		if ( apply_filters( 'acrossai_abilities_manager_load', true ) ) {
			$this->define_admin_hooks();
			$this->define_public_hooks();
		}
	}

	/**
	 * Load the required composer dependencies for this plugin.
	 *
	 * @since    0.0.1
	 * @access   private
	 */
	private function load_composer_dependencies() {

		/**
		 * Add composer file
		 */
		$plugin_path = ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH;

		if ( file_exists( $plugin_path . 'vendor/autoload_packages.php' ) ) {
			require_once $plugin_path . 'vendor/autoload_packages.php';
			return;
		}

		// Feature 038 boot resilience: signal __construct() to short-circuit
		// before load_dependencies() touches the PSR-4 autoloader. Surfaced
		// to admins via the manage_options-gated admin notice in __construct().
		$this->vendor_missing = true;
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - AcrossAI_Abilities_Manager\Admin\Loader. Orchestrates the hooks of the plugin.
	 * - AcrossAI_Abilities_Manager\Admin\Main. Defines all hooks for the admin area.
	 * - AcrossAI_Abilities_Manager_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    0.0.1
	 * @access   private
	 */
	private function load_dependencies() {

		$this->loader = AcrossAI_Loader::instance();
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    0.0.1
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new \AcrossAI_Abilities_Manager\Admin\Main( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		$this->loader->add_action( 'plugin_action_links', $plugin_admin, 'plugin_action_links', 1000, 2 );

		/**
		 * Add the Plugin Main Menu
		 */
		$main_menu = new \AcrossAI_Abilities_Manager\Admin\Partials\Menu( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_menu', $main_menu, 'register_submenu' );

		// Library submenu page — second position, immediately after main menu (Feature 027/031).
		// Named variable before Loader call — Boot Flow Rule variable-first pattern.
		$ability_library_menu = \AcrossAI_Abilities_Manager\Admin\Partials\LibraryMenu::instance();
		$this->loader->add_action( 'admin_menu', $ability_library_menu, 'register_submenu' );

		// Settings sections (Feature 019; reparented to host page in Feature 038;
		// migrated to instance-method API + tab-scoped option_group in Feature 045).
		// Host page rendering owned by acrossai-co/main-menu — the plugin
		// only contributes (a) an "Abilities" tab via the
		// `acrossai_settings_tabs` filter, and (b) settings sections
		// targeting the per-tab page slug derived by
		// SettingsPage::get_settings_renderer()->tab_page_slug() (main-menu v0.0.14+).
		// option_group is the SAME tab-scoped slug, so each tab has its own
		// whitelist — preventing the cross-tab option-clobber bug that
		// shared-`acrossai-settings` had in 0.0.12.
		// Named variable before Loader call — Boot Flow Rule variable-first pattern (AC-HOOKS-MAIN).
		$settings_menu = \AcrossAI_Abilities_Manager\Admin\Partials\SettingsMenu::instance();
		$this->loader->add_filter( 'acrossai_settings_tabs', $settings_menu, 'register_tab' );
		$this->loader->add_action( 'admin_init', $settings_menu, 'register_settings' );

		// Feature 046 — Absorbed Core Settings field (extra MIME types) renders
		// INSIDE the existing Abilities tab. Core_Settings_Menu does not register
		// its own tab and does not register a second uninstall-opt-in; the
		// manager's SettingsMenu owns both concerns. Variable-first per AC-HOOKS-MAIN.
		$core_settings_menu = \AcrossAI_Abilities_Manager\Admin\Partials\Core_Settings_Menu::instance();
		$this->loader->add_action( 'admin_init', $core_settings_menu, 'register_settings' );

		// Uninstall Settings section is wired LAST — priority 20 pushes it below
		// every default-priority section (Display Settings above, plus
		// Core_Settings_Menu's Upload Media Abilities section). Sections render
		// in the order they were added, so a later priority puts this at the
		// bottom of the Abilities tab.
		$this->loader->add_action( 'admin_init', $settings_menu, 'register_uninstall_settings', 20 );

		// Add-ons submenu page (Feature 026 → 030 → 039 → 053).
		// As of acrossai-co/main-menu 0.0.21 the `\AcrossAI_Addon\AddonsPage`
		// entry-class is removed and the Add-ons submenu is registered
		// internally by `\AcrossAI_Main_Menu\MenuRegistrar` when the shared
		// `\AcrossAI_Main_Menu\SettingsPage` bootstrap runs (done at
		// `plugins_loaded @ P0` in this plugin's entry file, see
		// acrossai-abilities-manager.php). Consumers extend the shared list
		// via `add_filter( 'acrossai_addons', … )`; freemius integration was
		// dropped upstream in 0.0.21 so no `fs_*` args are passed anymore.
		// The submenu URL admin.php?page=acrossai-addons is unchanged.

		// Abilities DB table setup — BerlinDB hooks maybe_upgrade() to admin_init.
		// Named variable before Loader call — Boot Flow Rule variable-first pattern (AC-HOOKS-MAIN).
		$abilities_table = \AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Table::instance();

		// Register Access Control REST routes and library-missing notice (SAC-01).
		// The library-missing notice is routed through the shared main-menu 0.0.30
		// `acrossai_notices` filter — surfaces on AcrossAI → Notices submenu +
		// the top-of-page summary banner instead of a raw admin_notices banner.
		$abilities_ac = \AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Abilities_Access_Control::instance();
		$this->loader->add_action( 'rest_api_init', $abilities_ac, 'register_rest_api' );
		$this->loader->add_filter( 'acrossai_notices', $abilities_ac, 'register_library_notice' );

		// Abilities module REST routes (Spec 009).
		// Named variable before Loader call — Boot Flow Rule variable-first pattern.
		$abilities_rest = \AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Rest\AcrossAI_Abilities_Rest_Controller::instance();
		$this->loader->add_action( 'rest_api_init', $abilities_rest, 'register_routes' );

		// Library Registry — collect add-on definitions at init P99 (Feature 027).
		// Named variable before Loader call — Boot Flow Rule variable-first pattern.
		$ability_library_registry = \AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry::instance();
		$this->loader->add_action( 'init', $ability_library_registry, 'collect', 99 );

		// Library REST orchestrator — acrossai-abilities-library/v1 namespace (Feature 027).
		// Named variable before Loader call — Boot Flow Rule variable-first pattern.
		$ability_library_rest = \AcrossAI_Abilities_Manager\Includes\Modules\Library\Rest\AcrossAI_Ability_Library_Rest_Controller::instance();
		$this->loader->add_action( 'rest_api_init', $ability_library_rest, 'register_routes' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    0.0.1
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new \AcrossAI_Abilities_Manager\Front\Main( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Ability Override Processor — boot at plugins_loaded P20 and bust cache on override save.
		// Named variable before Loader calls satisfies the Boot Flow Rule (SEC-PLAN-002).
		$override_processor = \AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Ability_Override_Processor::instance();
		$this->loader->add_action( 'plugins_loaded', $override_processor, 'boot_hook', 20 );
		// CRITICAL-01 fix (sub-change 11d): wire bust_cache_hook to all three Abilities lifecycle hooks.
		$this->loader->add_action( 'acrossai_abilities_after_create', $override_processor, 'bust_cache_hook' );
		$this->loader->add_action( 'acrossai_abilities_after_update', $override_processor, 'bust_cache_hook' );
		$this->loader->add_action( 'acrossai_abilities_after_delete', $override_processor, 'bust_cache_hook' );

		// Abilities Processor — registers published source=db abilities at wp_abilities_api_init P10 (Spec 009).
		// Named variable before Loader call — Boot Flow Rule variable-first pattern.
		$abilities_processor = \AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Abilities_Processor::instance();
		$this->loader->add_action( 'wp_abilities_api_init', $abilities_processor, 'register_abilities', 10 );

		// Library Processor — registers add-on abilities at wp_abilities_api_init P5 (Feature 027).
		// Runs before the DB processor at P10 so add-on abilities are gated first.
		// Named variable before Loader call — Boot Flow Rule variable-first pattern.
		$ability_library_processor = \AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Processor::instance();
		$this->loader->add_action( 'wp_abilities_api_init', $ability_library_processor, 'register_abilities', 5 );

		// Feature 046 — Absorbed Core Abilities Bootstrap. Registers 17 category
		// callbacks with the Loader (each is one $this->loader->add_action on
		// wp_abilities_api_categories_init) and hooks the 176-class ability
		// instantiation to plugins_loaded @ P20 matching the companion plugin's
		// original hook point. See specs/046-absorb-core-abilities-into-manager/.
		$core_abilities_bootstrap = \AcrossAI_Abilities_Manager\Includes\Abilities\AcrossAI_Core_Abilities_Bootstrap::instance();
		$core_abilities_bootstrap->register_category_callbacks( $this->loader );
		$this->loader->add_action( 'plugins_loaded', $core_abilities_bootstrap, 'register_abilities', 20 );

		// Feature 060 — Third-party integration bootstraps. Each concrete
		// subclass of AcrossAI_Integration_Ability_Base registers its own
		// hooks from the constructor (plugins_loaded P20 for the enable-filter
		// side-effect + acrossai_abilities_api_init P10 for the synthetic
		// definition row). Both hook callbacks gate on subclass
		// is_plugin_active() so a missing target plugin renders no card, no
		// tab, and attaches no filter. Boot Flow Rule deviation is documented
		// under DEC-ABILITY-DEFINITION-CTOR-HOOKS (Feature 060), sibling to
		// DEC-EXTERNAL-PACKAGE-HOOK-CTOR. See
		// specs/060-library-third-party-integration-toggles/plan.md
		// Complexity Tracking for the justification.
		//
		// Instantiation happens synchronously here (from define_public_hooks(),
		// which itself runs at plugins_loaded @ P0 via the plugin entry file)
		// so the constructor's plugins_loaded P20 add_action registers before
		// P20 fires. Adding another integration is a one-line change here.
		new \AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\ACF();
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    0.0.1
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     0.0.1
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     0.0.1
	 * @return    AcrossAI_Abilities_Manager_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * The reference to the autoloader instance.
	 *
	 * @since     0.0.1
	 * @return    Autoloader    The plugin autoloader instance.
	 */
	public function get_autoloader() {
		return $this->autoloader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     0.0.1
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
