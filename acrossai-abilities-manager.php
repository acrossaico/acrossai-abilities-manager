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
 * Plugin URI:        https://acrossai.co/abilities-manager/
 * Description:       357 ready-made WordPress abilities for Claude, ChatGPT and any AI agent, plus full control over every ability on your site.
 * Version:           0.0.45
 * Requires PHP:      8.1
 * Requires at least: 6.9
 * Tested up to:      7.1
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
 * Whether this plugin should load its own code at all.
 *
 * AcrossAI MCP Manager now carries this plugin's entire tree inside itself. On
 * a site running both, every ability, Toolset and admin screen would be
 * declared twice — so MCP Manager returns false here and this plugin loads
 * nothing, rather than the two racing for the same ability slugs, the same
 * `acrossai-toolset` category and the same settings tabs.
 *
 * ## Why the answer is taken on `plugins_loaded` and not at file scope
 *
 * WordPress reads active plugins in the order they sit in the `active_plugins`
 * option, and `activate_plugin()` sorts that array — so load order is
 * alphabetical and `acrossai-abilities-manager` comes before
 * `acrossai-mcp-manager`. MCP Manager therefore cannot have added a callback by
 * the time this file is read: a filter applied here would always see its own
 * default, and the switch could never be thrown.
 *
 * So nothing is decided at file scope. The bootstrap below is deferred to
 * `plugins_loaded` at the earliest priority — the first moment every plugin
 * file has been read and any of them can hold an opinion. Hooks this plugin
 * attaches at later priorities of that same action still fire, because
 * WordPress walks a hook's priority list as it runs rather than snapshotting
 * it.
 *
 * ## What runs even when the answer is false
 *
 * Only the activation and deactivation hooks, which must be registered at file
 * scope or WordPress cannot find them during an activation request. Both
 * consult this gate themselves.
 *
 * `ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE` is deliberately no longer defined at
 * file scope. MCP Manager derives its copy of this tree's paths from that
 * constant and reads finding it already defined as "the standalone plugin is
 * live, stand down" — so defining it unconditionally here would switch off the
 * half that is meant to win.
 *
 * @since 0.0.46
 * @return bool
 */
function acrossai_abilities_manager_should_load(): bool {
	/**
	 * Filters whether the AcrossAI Abilities Manager plugin loads its own code.
	 *
	 * Return false to make this plugin inert: no constants, no autoloader, no
	 * abilities, no admin screens. Intended for a plugin that ships this tree
	 * itself and would otherwise register everything twice.
	 *
	 * Must be added before `plugins_loaded` fires — any plugin file qualifies,
	 * whatever the load order.
	 *
	 * @since 0.0.46
	 * @param bool $load Whether to load this plugin's code. Default true.
	 */
	return (bool) apply_filters( 'acrossai_abilities_manager_load_plugin', true );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/activator.php
 */
function acrossai_abilities_manager_activate() {
	// Another plugin is carrying this tree, so its copy owns the tables and the
	// option migrations. Running ours would load a second set of these classes
	// into a request where theirs is already live. Harmless by itself — every
	// step is guarded by its own claim option — but there is nothing here left
	// to do, and loading the tree to discover that is the thing the gate exists
	// to avoid.
	if ( ! acrossai_abilities_manager_should_load() ) {
		return;
	}

	require_once plugin_dir_path( __FILE__ ) . 'includes/AcrossAI_Activator.php';
	Includes\AcrossAI_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/deactivator.php
 */
function acrossai_abilities_manager_deactivate() {
	if ( ! acrossai_abilities_manager_should_load() ) {
		return;
	}

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

use AcrossAI_Abilities_Manager\Includes\Main;

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * Loads `includes/Main.php` itself rather than being handed an already-loaded
 * class. That `require` used to sit at file scope, and moving it in here is
 * the point of the gate: when another plugin carries this tree, not one line of
 * it is read.
 *
 * @since    0.0.1
 */
function acrossai_abilities_manager_run() {

	/**
	 * Defines this tree's paths, so it must not be set when the gate is shut —
	 * AcrossAI MCP Manager reads an already-defined value as "the standalone
	 * plugin is live" and stands its own copy down.
	 */
	define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE', __FILE__ );

	require plugin_dir_path( __FILE__ ) . 'includes/Main.php';

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
		if ( ! acrossai_abilities_manager_should_load() ) {
			return;
		}
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

/**
 * Boot the plugin, unless something else is carrying its code.
 *
 * `PHP_INT_MIN` rather than a merely small number: this has to be the first
 * thing on `plugins_loaded`, because it is what loads the classes every later
 * callback on that action depends on. Anything attached ahead of it would run
 * against a tree that has not been read yet.
 *
 * The call used to sit bare at the end of this file. It cannot any more — at
 * file scope no other plugin has been read, so nothing could answer the gate.
 * See {@see acrossai_abilities_manager_should_load()}.
 *
 * @since 0.0.46
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! acrossai_abilities_manager_should_load() ) {
			return;
		}

		acrossai_abilities_manager_run();
	},
	PHP_INT_MIN
);
