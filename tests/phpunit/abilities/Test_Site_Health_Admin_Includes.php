<?php
/**
 * The two Site Health abilities must load the admin libraries their WP core
 * classes call internally.
 *
 * ## The bug this guards
 *
 * Both abilities required their core class file and stopped there. Requiring
 * `class-wp-debug-data.php` makes the CLASS exist; it does not make the
 * functions that class calls exist. In a wp-admin request
 * `wp-admin/includes/admin.php` has already loaded them, which is why this
 * passed every manual check. Over REST — which is how an MCP client reaches
 * these — none of them are loaded, and
 * `site-health/get-site-health-info` returned
 * `Call to undefined function get_core_updates()` for every request,
 * whichever sections were asked for.
 *
 * ## Why the second test is the one with teeth
 *
 * The literal assertions below are the house pattern and they catch a
 * removed `require_once`. They cannot catch the thing that actually caused
 * this: a dependency nobody knew about. So the second test re-derives the
 * set from WP core — every function the core class calls that is defined in
 * a `wp-admin/includes` library — and fails if the ability does not load
 * that library. A future WordPress that adds a dependency breaks this test
 * rather than breaking the ability in production.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Site_Health_Admin_Includes.
 */
class Test_Site_Health_Admin_Includes extends WP_UnitTestCase {

	/**
	 * Ability source path => the core class file it depends on.
	 *
	 * @var array<string, string>
	 */
	private const SUBJECTS = array(
		'includes/Abilities/SiteHealth/Get_Site_Health_Info.php'   => 'class-wp-debug-data.php',
		'includes/Abilities/SiteHealth/Get_Site_Health_Status.php' => 'class-wp-site-health.php',
	);

	/**
	 * Libraries each ability must load, as observed on the WordPress this was
	 * written against. The second test is what keeps this list honest.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const EXPECTED = array(
		'includes/Abilities/SiteHealth/Get_Site_Health_Info.php'   => array(
			'update.php',
			'plugin.php',
			'file.php',
			'misc.php',
		),
		'includes/Abilities/SiteHealth/Get_Site_Health_Status.php' => array(
			'update.php',
			'plugin.php',
			'file.php',
			'misc.php',
			'screen.php',
		),
	);

	/**
	 * Plugin root.
	 *
	 * @var string
	 */
	private string $root = '';

	protected function setUp(): void {
		parent::setUp();
		$this->root = dirname( __DIR__, 3 );
	}

	/**
	 * Each ability requires every library it is known to need.
	 *
	 * @return void
	 */
	public function test_each_ability_requires_its_admin_libraries(): void {
		foreach ( self::EXPECTED as $relative => $libraries ) {
			$src = (string) file_get_contents( $this->root . '/' . $relative );

			foreach ( $libraries as $library ) {
				$this->assertStringContainsString(
					"require_once ABSPATH . 'wp-admin/includes/" . $library . "'",
					$src,
					$relative . ' must load wp-admin/includes/' . $library
						. ' — the class file alone does not define the functions the class calls.'
				);
			}
		}
	}

	/**
	 * Every library the core class actually needs is one the ability loads.
	 *
	 * Derived from the installed WordPress rather than from a list, so this
	 * keeps working when core changes. Skipped when core is not reachable —
	 * a false pass is better than a false failure in a harness that stubs
	 * WordPress.
	 *
	 * @return void
	 */
	public function test_the_required_set_still_covers_what_core_calls(): void {
		$includes = $this->locate_admin_includes();

		if ( null === $includes ) {
			$this->markTestSkipped( 'No wp-admin/includes to derive dependencies from.' );
		}

		// Where each admin function is defined: function name => library.
		$defined_in = array();

		foreach ( (array) glob( $includes . '/*.php' ) as $library ) {
			$name = basename( (string) $library );

			// Class files declare methods, not the loose functions at issue
			// here, and `noop.php` deliberately redefines a handful of
			// front-end functions for the admin bootstrap — treating either
			// as the home of a dependency would invent requirements.
			if ( 'noop.php' === $name || 0 === strpos( $name, 'class-' ) ) {
				continue;
			}

			if ( preg_match_all( '/^function ([a-z_][a-z0-9_]*)\s*\(/mi', (string) file_get_contents( (string) $library ), $found ) ) {
				foreach ( $found[1] as $function ) {
					$defined_in[ strtolower( $function ) ] = $name;
				}
			}
		}

		$this->assertNotEmpty( $defined_in, 'Could not read any admin function definitions.' );

		foreach ( self::SUBJECTS as $relative => $class_file ) {
			$class_path = $includes . '/' . $class_file;

			if ( ! is_readable( $class_path ) ) {
				continue;
			}

			preg_match_all( '/\b([a-z_][a-z0-9_]*)\s*\(/i', (string) file_get_contents( $class_path ), $calls );

			$needed = array();

			foreach ( array_unique( $calls[1] ) as $call ) {
				$key = strtolower( $call );

				if ( isset( $defined_in[ $key ] ) ) {
					$needed[ $defined_in[ $key ] ] = true;
				}
			}

			$src = (string) file_get_contents( $this->root . '/' . $relative );

			foreach ( array_keys( $needed ) as $library ) {
				$this->assertStringContainsString(
					"wp-admin/includes/" . $library,
					$src,
					$class_file . ' calls a function from wp-admin/includes/' . $library
						. ', so ' . $relative . ' must load it. If WordPress added this'
						. ' dependency, add the require and update self::EXPECTED.'
				);
			}
		}
	}

	/**
	 * Find the installed `wp-admin/includes`, walking up from the plugin.
	 *
	 * Searched rather than computed from a fixed depth: the plugin may sit
	 * under `wp-content/plugins`, a symlink, or a `mu-plugins` layout, and a
	 * hard-coded `dirname( $root, 2 )` silently skips the test instead of
	 * running it — which is the worst outcome for the one assertion here with
	 * any teeth.
	 *
	 * @return string|null
	 */
	private function locate_admin_includes(): ?string {
		$directory = $this->root;

		for ( $up = 0; $up < 6; $up++ ) {
			$directory = dirname( $directory );
			$candidate = $directory . '/wp-admin/includes';

			if ( is_dir( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * The libraries must be loaded before the class is used, not after.
	 *
	 * @return void
	 */
	public function test_dependencies_load_before_the_class_is_used(): void {
		foreach ( self::SUBJECTS as $relative => $class_file ) {
			$src = (string) file_get_contents( $this->root . '/' . $relative );

			$call  = strpos( $src, 'self::load_admin_dependencies();' );
			$class = strpos( $src, "require_once ABSPATH . 'wp-admin/includes/" . $class_file . "'" );

			$this->assertNotFalse( $call, $relative . ' must call load_admin_dependencies().' );
			$this->assertNotFalse( $class, $relative . ' must require ' . $class_file . '.' );
			$this->assertLessThan(
				$class,
				$call,
				$relative . ' must load its admin libraries before touching the core class.'
			);
		}
	}
}
