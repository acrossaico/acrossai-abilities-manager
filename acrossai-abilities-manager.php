<?php
/**
 * Instantiates the AcrossAI Abilities Manager plugin
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager;

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/acrosswp/acrossai-abilities-manager
 * @since             0.0.1
 * @package           AcrossAI_Abilities_Manager
 *
 * @wordpress-plugin
 * Plugin Name:       AcrossAI Abilities Manager
 * Plugin URI:        https://acrossai.co/
 * Description:       Manage and customize the abilities of AcrossAI on your WordPress site. Tailor the AI's capabilities to suit your needs, enhancing user experience and engagement.
 * Version:           0.0.30
 * Requires PHP:      8.1
 * Requires at least: 6.9
 * Tested up to:      7.0
 * Author:            raftaar1191
 * Author URI:        https://profiles.wordpress.org/raftaar1191/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       acrossai-abilities-manager
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 0.0.1 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE', __FILE__ );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/activator.php
 */
function acrossai_abilities_manager_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/AcrossAI_Activator.php';
	Includes\AcrossAI_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/deactivator.php
 */
function acrossai_abilities_manager_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/AcrossAI_Deactivator.php';
	Includes\AcrossAI_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'AcrossAI_Abilities_Manager\acrossai_abilities_manager_activate' );
register_deactivation_hook( __FILE__, 'AcrossAI_Abilities_Manager\acrossai_abilities_manager_deactivate' );

/**
 * Feature 038 activation guard: block activation when the Composer autoloader
 * is missing, so the existing acrossai_abilities_manager_activate callback
 * (which transitively requires autoloaded classes) cannot fatal first.
 *
 * Registered at priority 1 on the WordPress-internal `activate_<plugin>` action
 * so it runs BEFORE the default-priority-10 callback registered by the
 * register_activation_hook above. Without this priority shift the existing
 * callback would fatal on a missing-vendor install and never reach this guard
 * (SEC-002 in specs/038-acrossai-main-menu-integration/security-review-plan.md).
 */
add_action(
	'activate_' . plugin_basename( __FILE__ ),
	static function () {
		if ( ! file_exists( __DIR__ . '/vendor/autoload_packages.php' ) ) {
			wp_die(
				esc_html__(
					'AcrossAI Abilities Manager cannot activate: the Composer autoloader is missing. Run "composer install" inside the plugin directory and try again.',
					'acrossai-abilities-manager'
				)
			);
		}
	},
	1
);

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/Main.php';

use AcrossAI_Abilities_Manager\Includes\Main;

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.0.1
 */
function acrossai_abilities_manager_run() {

	$plugin = Main::instance();

	/**
	 * Run this plugin on the plugins_loaded functions
	 */
	add_action( 'plugins_loaded', array( $plugin, 'run' ), 0 );
}

/**
 * Bootstrap the shared `acrossai-co/main-menu` top-level menu host.
 *
 * Registered at plugins_loaded priority 0 so the shared parent menu exists
 * before any plugin's admin_menu hooks fire on default priority 10. The
 * `did_action()` guard makes the bootstrap idempotent across multiple
 * AcrossAI plugins consuming the same shared menu (PATTERN-SHARED-MENU-
 * CONSUMER-IDEMPOTENCY). The `class_exists()` guard provides Constitution
 * §V Integration Resilience graceful degradation when the package is
 * absent — submenus simply won't have a parent rather than fataling.
 *
 * Accepted deviation from CONSTITUTION.md §I Boot Flow Rule
 * (DEC-EXTERNAL-PACKAGE-HOOK-CTOR scope extension): the bootstrap lives in
 * the plugin entry file rather than in includes/Main.php because the host
 * menu must be the canonical owner of the top-level menu, independent of
 * any single consuming plugin's internal Loader. See Feature 038 plan and
 * memory-synthesis.md for the full justification.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( did_action( 'acrossai_main_menu_bootstrapped' ) ) {
			return;
		}
		if ( class_exists( \AcrossAI_Main_Menu\SettingsPage::class ) ) {
			new \AcrossAI_Main_Menu\SettingsPage();
			do_action( 'acrossai_main_menu_bootstrapped' );
		}
	},
	0
);

acrossai_abilities_manager_run();
