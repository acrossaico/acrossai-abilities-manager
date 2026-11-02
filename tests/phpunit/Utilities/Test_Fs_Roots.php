<?php
/**
 * Feature 135 — path resolution against WordPress's real roots.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Fs_Roots;

require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Fs_Roots.php';

/**
 * Exercises Fs_Roots against a synthetic split-docroot layout.
 *
 * The roots are injected rather than taken from constants, because the behaviour that matters only
 * appears when WP_CONTENT_DIR is outside ABSPATH and a same-named decoy exists underneath ABSPATH —
 * a shape that cannot be produced by redefining constants mid-run.
 */
final class Test_Fs_Roots extends TestCase {

	/**
	 * Workspace root.
	 *
	 * @var string
	 */
	private string $tmp = '';

	/**
	 * Injected roots: content outside core, mimicking /srv/htdocs + /wordpress/core/X.
	 *
	 * @var array<string,string>
	 */
	private array $roots = array();

	protected function setUp(): void {
		parent::setUp();

		$this->tmp = sys_get_temp_dir() . '/acrossai-135-' . getmypid() . '-' . uniqid();

		$core    = $this->tmp . '/wordpress/core/7.1.4';
		$content = $this->tmp . '/srv/htdocs/wp-content';
		$decoy   = $core . '/wp-content';

		// The live theme: only ever under WP_CONTENT_DIR.
		mkdir( $content . '/themes/live-theme', 0777, true );
		file_put_contents( $content . '/themes/live-theme/functions.php', "<?php // live\n" );

		// The decoy: a theme that exists ONLY in the core bundle's unused wp-content. This is the
		// shape that produced a successful read of the wrong file on the host.
		mkdir( $decoy . '/themes/bundled-theme', 0777, true );
		file_put_contents( $decoy . '/themes/bundled-theme/style.css', "/* bundled */\n" );

		mkdir( $core . '/wp-includes', 0777, true );
		file_put_contents( $core . '/wp-includes/version.php', "<?php // core\n" );

		$this->roots = array(
			'content' => realpath( $content ),
			'core'    => realpath( $core ),
		);
	}

	protected function tearDown(): void {
		$this->rmrf( $this->tmp );
		parent::tearDown();
	}

	/**
	 * Recursively remove the workspace.
	 *
	 * @param string $dir Directory.
	 * @return void
	 */
	private function rmrf( string $dir ): void {
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}

		foreach ( scandir( $dir ) ?: array() as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$path = $dir . '/' . $entry;

			if ( is_dir( $path ) && ! is_link( $path ) ) {
				$this->rmrf( $path );
			} else {
				unlink( $path );
			}
		}

		rmdir( $dir );
	}

	/* ---------------------------------------------------------------- */
	/* The incident this feature exists for                             */
	/* ---------------------------------------------------------------- */

	public function test_wp_content_path_reaches_the_live_content_directory(): void {
		$resolved = Fs_Roots::resolve( 'wp-content/themes/live-theme/functions.php', true, $this->roots );

		$this->assertIsArray( $resolved, 'The live theme must be reachable.' );
		$this->assertSame( $this->roots['content'] . '/themes/live-theme/functions.php', $resolved['path'] );
		$this->assertSame( 'content', $resolved['root'] );
	}

	public function test_path_existing_only_under_abspath_content_is_refused_not_served(): void {
		// Before Feature 135 this returned HTTP 200 with the bundled file's bytes, and the caller
		// had no way to tell it was not the live theme.
		$resolved = Fs_Roots::resolve( 'wp-content/themes/bundled-theme/style.css', false, $this->roots );

		$this->assertInstanceOf( \WP_Error::class, $resolved );
		$this->assertSame( Fs_Roots::ERR_DECOY, $resolved->get_error_code() );
	}

	public function test_decoy_refusal_names_the_real_content_directory(): void {
		$resolved = Fs_Roots::resolve( 'wp-content/themes/bundled-theme/style.css', false, $this->roots );

		$this->assertInstanceOf( \WP_Error::class, $resolved );
		$this->assertStringContainsString( $this->roots['content'], $resolved->get_error_message() );
	}

	/* ---------------------------------------------------------------- */
	/* Ordinary resolution                                              */
	/* ---------------------------------------------------------------- */

	public function test_core_path_resolves_against_abspath(): void {
		$resolved = Fs_Roots::resolve( 'wp-includes/version.php', true, $this->roots );

		$this->assertIsArray( $resolved );
		$this->assertSame( $this->roots['core'] . '/wp-includes/version.php', $resolved['path'] );
		$this->assertSame( 'core', $resolved['root'] );
	}

	public function test_bare_content_relative_path_resolves(): void {
		$resolved = Fs_Roots::resolve( 'themes/live-theme/functions.php', true, $this->roots );

		$this->assertIsArray( $resolved );
		$this->assertSame( 'content', $resolved['root'] );
	}

	public function test_missing_target_is_reported_when_existence_required(): void {
		$resolved = Fs_Roots::resolve( 'wp-content/themes/nope/functions.php', true, $this->roots );

		$this->assertInstanceOf( \WP_Error::class, $resolved );
		$this->assertSame( Fs_Roots::ERR_MISSING, $resolved->get_error_code() );
	}

	public function test_nonexistent_target_resolves_for_create_when_parent_exists(): void {
		$resolved = Fs_Roots::resolve( 'wp-content/themes/live-theme/new.php', false, $this->roots );

		$this->assertIsArray( $resolved );
		$this->assertFalse( $resolved['exists'] );
		$this->assertSame( $this->roots['content'] . '/themes/live-theme/new.php', $resolved['path'] );
	}

	/* ---------------------------------------------------------------- */
	/* Input hardening                                                  */
	/* ---------------------------------------------------------------- */

	public function test_traversal_segment_is_refused(): void {
		$resolved = Fs_Roots::resolve( 'wp-content/../../../etc/passwd', false, $this->roots );

		$this->assertInstanceOf( \WP_Error::class, $resolved );
		$this->assertSame( Fs_Roots::ERR_INVALID, $resolved->get_error_code() );
	}

	public function test_double_dot_inside_a_filename_is_not_traversal(): void {
		mkdir( $this->roots['content'] . '/plugins/my..plugin', 0777, true );
		file_put_contents( $this->roots['content'] . '/plugins/my..plugin/x.php', '<?php' );

		$resolved = Fs_Roots::resolve( 'wp-content/plugins/my..plugin/x.php', true, $this->roots );

		$this->assertIsArray( $resolved, 'A name containing two dots is not a traversal segment.' );
	}

	public function test_null_byte_is_refused(): void {
		$resolved = Fs_Roots::resolve( "wp-content/themes/live-theme/functions.php\0.txt", false, $this->roots );

		$this->assertInstanceOf( \WP_Error::class, $resolved );
		$this->assertSame( Fs_Roots::ERR_INVALID, $resolved->get_error_code() );
	}

	public function test_stream_wrapper_is_refused(): void {
		foreach ( array( 'phar://x/y', 'php://input', 'data://text/plain,x' ) as $wrapped ) {
			$resolved = Fs_Roots::resolve( $wrapped, false, $this->roots );

			$this->assertInstanceOf( \WP_Error::class, $resolved, $wrapped );
			$this->assertSame( Fs_Roots::ERR_INVALID, $resolved->get_error_code(), $wrapped );
		}
	}

	public function test_empty_path_is_refused(): void {
		$resolved = Fs_Roots::resolve( '   ', false, $this->roots );

		$this->assertInstanceOf( \WP_Error::class, $resolved );
		$this->assertSame( Fs_Roots::ERR_INVALID, $resolved->get_error_code() );
	}

	public function test_absolute_path_outside_every_root_is_refused(): void {
		$resolved = Fs_Roots::resolve( $this->tmp . '/srv/htdocs', false, $this->roots );

		$this->assertInstanceOf( \WP_Error::class, $resolved );
		$this->assertSame( Fs_Roots::ERR_OUTSIDE, $resolved->get_error_code() );
	}

	/* ---------------------------------------------------------------- */
	/* Containment and round-tripping                                   */
	/* ---------------------------------------------------------------- */

	public function test_sibling_directory_sharing_a_prefix_is_not_inside(): void {
		$this->assertFalse(
			Fs_Roots::inside( '/srv/htdocs/wp-content-backup/x', '/srv/htdocs/wp-content' ),
			'A prefix match without a slash boundary would let wp-content-backup pass as wp-content.'
		);
		$this->assertTrue( Fs_Roots::inside( '/srv/htdocs/wp-content/x', '/srv/htdocs/wp-content' ) );
		$this->assertTrue( Fs_Roots::inside( '/srv/htdocs/wp-content', '/srv/htdocs/wp-content' ) );
	}

	public function test_display_base_keeps_the_content_directory_name_in_relative_paths(): void {
		$abs      = $this->roots['content'] . '/themes/live-theme/functions.php';
		$base     = Fs_Roots::display_base( $abs, $this->roots );
		$relative = substr( $abs, strlen( $base ) + 1 );

		$this->assertSame( 'wp-content/themes/live-theme/functions.php', $relative );

		// And it round-trips: what list-directory reports, read-file can resolve.
		$round = Fs_Roots::resolve( $relative, true, $this->roots );

		$this->assertIsArray( $round );
		$this->assertSame( $abs, $round['path'] );
	}

	public function test_match_returns_null_outside_every_root(): void {
		$this->assertNull( Fs_Roots::match( '/definitely/not/wordpress', $this->roots ) );
	}

	/* ---------------------------------------------------------------- */
	/* Standard single-directory install is unaffected                  */
	/* ---------------------------------------------------------------- */

	public function test_standard_layout_has_no_decoy_refusal(): void {
		$core    = $this->tmp . '/standard';
		$content = $core . '/wp-content';

		mkdir( $content . '/themes/t', 0777, true );
		file_put_contents( $content . '/themes/t/style.css', '/* x */' );

		$roots = array(
			'content' => realpath( $content ),
			'core'    => realpath( $core ),
		);

		$resolved = Fs_Roots::resolve( 'wp-content/themes/t/style.css', true, $roots );

		$this->assertIsArray( $resolved, 'Content inside ABSPATH is the ordinary case; nothing to disambiguate.' );
		$this->assertSame( $roots['content'] . '/themes/t/style.css', $resolved['path'] );
	}

	public function test_roots_from_the_environment_are_real_and_labelled(): void {
		$roots = Fs_Roots::roots();

		$this->assertArrayHasKey( 'core', $roots );
		$this->assertArrayHasKey( 'content', $roots );

		foreach ( $roots as $label => $path ) {
			$this->assertSame( $path, rtrim( $path, '/' ), $label . ' must not carry a trailing slash.' );
			$this->assertDirectoryExists( $path, $label . ' must be a real directory.' );
		}
	}
}
