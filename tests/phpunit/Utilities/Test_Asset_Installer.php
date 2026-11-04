<?php
/**
 * The two pure decisions behind overwriting an installed plugin or theme.
 *
 * Both exist because reading WordPress core turned up something the obvious implementation gets
 * wrong. `Plugin_Upgrader::install()` never arms `temp_backup` — only `upgrade()` does — so an
 * overwrite clears the destination with nothing to fall back to. And `install()` returns one of
 * four values, three of which mean failure, with the actual reason delivered on the skin instead
 * of in the return value.
 *
 * Pure, so these execute rather than skip. #298 is open because thirteen database tests skip for
 * want of a $wpdb, and that is how an inert row cap and an 8x miscount both reached green reviews.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Asset_Installer;

require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Asset_Installer.php';

final class Test_Asset_Installer extends TestCase {

	/* ------------------------------------------------------------------ */
	/* temp_backup_args — the restore point                                */
	/* ------------------------------------------------------------------ */

	public function test_plugin_backup_names_the_plugins_directory(): void {
		$args = Asset_Installer::temp_backup_args( 'plugin', 'akismet', '/srv/htdocs/wp-content/plugins' );

		// Core's move_to_temp_backup_dir() wants exactly these three and bails on an empty one.
		$this->assertSame(
			array(
				'slug' => 'akismet',
				'src'  => '/srv/htdocs/wp-content/plugins',
				'dir'  => 'plugins',
			),
			$args
		);
	}

	public function test_theme_backup_names_the_themes_directory(): void {
		$args = Asset_Installer::temp_backup_args( 'theme', 'twentytwenty', '/srv/htdocs/wp-content/themes' );

		$this->assertSame( 'themes', $args['dir'] );
		$this->assertSame( 'twentytwenty', $args['slug'] );
	}

	public function test_dot_slug_cannot_be_armed(): void {
		// dirname() on a single-file plugin at the plugins root returns '.'. Core skips a slug of
		// '.' because $src then ends in a period and mkdir() fails — so there would be no restore
		// point, and install() must refuse rather than overwrite unprotected.
		$this->assertSame( array(), Asset_Installer::temp_backup_args( 'plugin', '.', '/plugins' ) );
		$this->assertSame( array(), Asset_Installer::temp_backup_args( 'plugin', '..', '/plugins' ) );
	}

	public function test_empty_slug_cannot_be_armed(): void {
		$this->assertSame( array(), Asset_Installer::temp_backup_args( 'plugin', '', '/plugins' ) );
		$this->assertSame( array(), Asset_Installer::temp_backup_args( 'plugin', '   ', '/plugins' ) );
	}

	public function test_slug_is_trimmed(): void {
		$args = Asset_Installer::temp_backup_args( 'plugin', '  akismet  ', '/plugins' );

		$this->assertSame( 'akismet', $args['slug'] );
	}

	public function test_plugin_root_defaults_to_wp_plugin_dir(): void {
		$args = Asset_Installer::temp_backup_args( 'plugin', 'akismet' );

		$this->assertSame( WP_PLUGIN_DIR, $args['src'] );
	}

	public function test_theme_root_defaults_to_the_theme_root(): void {
		$args = Asset_Installer::temp_backup_args( 'theme', 'twentytwenty' );

		$this->assertSame( get_theme_root(), $args['src'] );
		$this->assertSame( 'themes', $args['dir'] );
	}

	public function test_unknown_type_is_treated_as_a_plugin(): void {
		// Only 'theme' selects the theme branch; anything else is a plugin. Asserted so a typo in
		// a caller's type string cannot quietly back a plugin up into the themes directory.
		$args = Asset_Installer::temp_backup_args( 'plugins', 'akismet', '/plugins' );

		$this->assertSame( 'plugins', $args['dir'] );
	}

	/* ------------------------------------------------------------------ */
	/* read_outcome — four return shapes, one success                      */
	/* ------------------------------------------------------------------ */

	public function test_true_is_the_only_success(): void {
		$this->assertTrue( Asset_Installer::read_outcome( true )['ok'] );
		$this->assertSame( '', Asset_Installer::read_outcome( true )['message'] );
	}

	public function test_false_null_and_wp_error_all_fail(): void {
		$this->assertFalse( Asset_Installer::read_outcome( false )['ok'] );
		$this->assertFalse( Asset_Installer::read_outcome( null )['ok'] );
		$this->assertFalse( Asset_Installer::read_outcome( new WP_Error( 'x', 'boom' ) )['ok'] );
	}

	public function test_truthy_non_true_is_not_success(): void {
		// install() documents true for success. A string or an array is not it, and treating any
		// truthy value as success would report a reinstall that did not happen.
		$this->assertFalse( Asset_Installer::read_outcome( 1 )['ok'] );
		$this->assertFalse( Asset_Installer::read_outcome( 'ok' )['ok'] );
		$this->assertFalse( Asset_Installer::read_outcome( array( 'destination' => '/x' ) )['ok'] );
	}

	public function test_wp_error_message_wins_over_the_skin(): void {
		$outcome = Asset_Installer::read_outcome(
			new WP_Error( 'download_failed', 'Download failed.' ),
			array( 'skin error' ),
			array( 'skin feedback' )
		);

		$this->assertSame( 'Download failed.', $outcome['message'] );
	}

	public function test_skin_errors_are_reported_when_the_return_value_is_bare(): void {
		// The real case: install() returns false and the reason is only on the skin.
		$outcome = Asset_Installer::read_outcome(
			false,
			array( 'Could not copy file.', 'Destination not writable.' )
		);

		$this->assertFalse( $outcome['ok'] );
		$this->assertSame( 'Could not copy file. Destination not writable.', $outcome['message'] );
	}

	public function test_skin_feedback_is_the_fallback_when_there_are_no_errors(): void {
		$outcome = Asset_Installer::read_outcome( false, array(), array( 'Unpacking the package.' ) );

		$this->assertSame( 'Unpacking the package.', $outcome['message'] );
	}

	public function test_errors_are_preferred_to_feedback(): void {
		$outcome = Asset_Installer::read_outcome( false, array( 'real reason' ), array( 'Unpacking.' ) );

		$this->assertSame( 'real reason', $outcome['message'] );
	}

	public function test_blank_messages_are_discarded_rather_than_joined(): void {
		// Skins emit empty strings. Without filtering, the message is a run of spaces, which reads
		// as "it failed and we have no idea" while actually having had a reason available.
		$outcome = Asset_Installer::read_outcome( false, array( '', '  ' ), array( 'the real one' ) );

		$this->assertSame( 'the real one', $outcome['message'] );
	}

	public function test_silent_failure_still_says_something(): void {
		$outcome = Asset_Installer::read_outcome( false );

		$this->assertFalse( $outcome['ok'] );
		$this->assertNotSame( '', $outcome['message'] );
	}

	/* ------------------------------------------------------------------ */
	/* pick_version — exact match only                                     */
	/* ------------------------------------------------------------------ */

	public function test_pick_version_returns_the_package_for_an_exact_match(): void {
		$map = array(
			'1.9.4'  => 'https://downloads.wordpress.org/plugin/p.1.9.4.zip',
			'1.12.4' => 'https://downloads.wordpress.org/plugin/p.1.12.4.zip',
		);

		$this->assertSame( 'https://downloads.wordpress.org/plugin/p.1.9.4.zip', Asset_Installer::pick_version( $map, '1.9.4' ) );
	}

	public function test_pick_version_never_falls_back_to_a_near_miss(): void {
		// The reason to name a version is usually that a specific one is known to work. Returning
		// 4.3.1 for a request for 4.3 defeats the point while looking like a success — and
		// version_compare would happily call them close.
		$map = array( '4.3.1' => 'u', '4.3.2' => 'u' );

		$this->assertSame( '', Asset_Installer::pick_version( $map, '4.3' ) );
	}

	public function test_pick_version_rejects_an_empty_request(): void {
		$this->assertSame( '', Asset_Installer::pick_version( array( '1.0' => 'u' ), '' ) );
		$this->assertSame( '', Asset_Installer::pick_version( array( '1.0' => 'u' ), '   ' ) );
	}

	public function test_pick_version_trims_the_requested_version(): void {
		$this->assertSame( 'u', Asset_Installer::pick_version( array( '1.0' => 'u' ), ' 1.0 ' ) );
	}

	public function test_pick_version_ignores_a_non_string_package(): void {
		// The version map comes from an API response cast to an array, so a value need not be a
		// string. Returning an array here would reach Plugin_Upgrader::install() as a package.
		$this->assertSame( '', Asset_Installer::pick_version( array( '1.0' => array( 'url' => 'u' ) ), '1.0' ) );
		$this->assertSame( '', Asset_Installer::pick_version( array( '1.0' => null ), '1.0' ) );
	}

	public function test_pick_version_treats_trunk_like_any_other_key(): void {
		// Asset_Versions::releases() is what excludes trunk from the offered list; pick_version is
		// asked for one exact key and does not second-guess it. Asserted so the division of
		// responsibility is explicit rather than assumed.
		$this->assertSame( 'u', Asset_Installer::pick_version( array( 'trunk' => 'u' ), 'trunk' ) );
	}

	/* ------------------------------------------------------------------ */
	/* install() refuses before touching anything                          */
	/* ------------------------------------------------------------------ */

	public function test_install_refuses_an_empty_package_url(): void {
		$result = Asset_Installer::install( 'plugin', 'akismet', '   ' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'no_package_url', $result->get_error_code() );
	}

	public function test_install_refuses_when_no_restore_point_is_possible(): void {
		// The guard that matters: overwriting clears the destination first, so without a backup a
		// package that fails to unpack leaves a working plugin deleted.
		$result = Asset_Installer::install( 'plugin', '.', 'https://example.org/p.zip' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'no_restore_point', $result->get_error_code() );
	}
}
