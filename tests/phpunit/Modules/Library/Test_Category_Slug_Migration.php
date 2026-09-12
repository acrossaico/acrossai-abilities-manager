<?php
/**
 * Tests: AcrossAI_Category_Slug_Migration.
 *
 * The migration exists because a category slug is a persistence key in two
 * places, and WordPress refuses to register an ability whose category is not
 * registered. Getting this wrong does not throw — it makes operator-created
 * abilities quietly cease to exist. These tests pin the parts that would fail
 * silently: that owned slugs are rewritten, that unowned ones are not, and
 * that a second run cannot clobber a newer preference with a stale one.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\Library;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Category_Slug_Migration;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Category slug rewrite behaviour.
 */
class Test_Category_Slug_Migration extends TestCase {

	/**
	 * Reset the stubbed option stores between tests.
	 */
	protected function setUp(): void {
		parent::setUp();
		acrossai_test_site_options( array() );

		global $__acrossai_test_options;
		$__acrossai_test_options = array();
	}

	/**
	 * Invoke a private static method.
	 *
	 * @param  string       $method Method name.
	 * @param  array<mixed> $args   Arguments.
	 * @return mixed
	 */
	private function invoke( string $method, array $args = array() ) {
		$refl = new ReflectionMethod( AcrossAI_Category_Slug_Migration::class, $method );
		$refl->setAccessible( true );
		return $refl->invokeArgs( null, $args );
	}

	/**
	 * Every owned category slug is shortened.
	 */
	public function test_owned_slugs_are_renamed(): void {
		$this->assertSame( 'acrossai-content', $this->invoke( 'rename', array( 'acrossai-abilities-manager-content' ) ) );
		$this->assertSame( 'acrossai-block', $this->invoke( 'rename', array( 'acrossai-abilities-manager-block' ) ) );
		$this->assertSame( 'acrossai-file-manager', $this->invoke( 'rename', array( 'acrossai-abilities-manager-file-manager' ) ) );
		$this->assertSame( 'acrossai-rank-math', $this->invoke( 'rename', array( 'acrossai-abilities-manager-rank-math' ) ) );
	}

	/**
	 * `content-search` is not mangled by the shorter `content` entry.
	 *
	 * The two overlap, so a naive prefix match would turn
	 * `…-content-search` into `acrossai-content-search` only by luck, or into
	 * something wrong if the list were applied in the other order.
	 */
	public function test_content_search_is_not_shadowed_by_content(): void {
		$this->assertSame(
			'acrossai-content-search',
			$this->invoke( 'rename', array( 'acrossai-abilities-manager-content-search' ) )
		);
	}

	/**
	 * Strings that share the prefix but are not categories are left alone.
	 *
	 * These are real: two asset handles and a CSS class registered elsewhere
	 * in the plugin. A blanket prefix rewrite would corrupt all three.
	 */
	public function test_non_category_strings_are_untouched(): void {
		foreach ( array(
			'acrossai-abilities-manager-abilities',
			'acrossai-abilities-manager-mcp-extension',
			'acrossai-abilities-manager-wrap',
			'acrossai-abilities-manager-roles',
			'acrossai-abilities-manager-fixtures',
		) as $handle ) {
			$this->assertSame( $handle, $this->invoke( 'rename', array( $handle ) ) );
		}
	}

	/**
	 * A third-party category is never rewritten.
	 */
	public function test_third_party_categories_are_untouched(): void {
		$this->assertSame( 'mcp-adapter', $this->invoke( 'rename', array( 'mcp-adapter' ) ) );
		$this->assertSame( 'ai-experiments', $this->invoke( 'rename', array( 'ai-experiments' ) ) );
		$this->assertSame( 'my-plugin-acf', $this->invoke( 'rename', array( 'my-plugin-acf' ) ) );
	}

	/**
	 * Library config keys are rewritten and their entries preserved intact.
	 */
	public function test_library_config_keys_are_rewritten(): void {
		update_site_option(
			AcrossAI_Category_Slug_Migration::SOURCE_OPTION,
			array(
				'acrossai-abilities-manager-content' => array(
					'enabled'  => false,
					'mode'     => 'specific',
					'sub_keys' => array( 'content/get-post' => true ),
				),
				'mcp-adapter'                        => array( 'enabled' => true ),
			)
		);

		$this->invoke( 'migrate_library_config' );

		$config = get_site_option( AcrossAI_Category_Slug_Migration::SOURCE_OPTION );

		$this->assertArrayHasKey( 'acrossai-content', $config );
		$this->assertArrayNotHasKey( 'acrossai-abilities-manager-content', $config );

		// The operator's actual preferences survive the rename.
		$this->assertFalse( $config['acrossai-content']['enabled'] );
		$this->assertSame( 'specific', $config['acrossai-content']['mode'] );
		$this->assertSame( array( 'content/get-post' => true ), $config['acrossai-content']['sub_keys'] );

		// A foreign key is carried across untouched.
		$this->assertArrayHasKey( 'mcp-adapter', $config );
	}

	/**
	 * A newer entry already under the new key wins over a stale legacy one.
	 */
	public function test_existing_new_key_is_not_clobbered_by_legacy(): void {
		update_site_option(
			AcrossAI_Category_Slug_Migration::SOURCE_OPTION,
			array(
				'acrossai-content'                   => array( 'enabled' => true ),
				'acrossai-abilities-manager-content' => array( 'enabled' => false ),
			)
		);

		$this->invoke( 'migrate_library_config' );

		$config = get_site_option( AcrossAI_Category_Slug_Migration::SOURCE_OPTION );

		$this->assertTrue( $config['acrossai-content']['enabled'] );
		$this->assertCount( 1, $config );
	}

	/**
	 * An absent or empty option is a no-op, not a fatal.
	 */
	public function test_empty_config_is_a_no_op(): void {
		$this->invoke( 'migrate_library_config' );
		$this->assertFalse( get_site_option( AcrossAI_Category_Slug_Migration::SOURCE_OPTION ) );

		update_site_option( AcrossAI_Category_Slug_Migration::SOURCE_OPTION, array() );
		$this->invoke( 'migrate_library_config' );
		$this->assertSame( array(), get_site_option( AcrossAI_Category_Slug_Migration::SOURCE_OPTION ) );
	}

	/**
	 * Re-running over already-migrated config changes nothing.
	 */
	public function test_migration_is_idempotent(): void {
		update_site_option(
			AcrossAI_Category_Slug_Migration::SOURCE_OPTION,
			array( 'acrossai-abilities-manager-media' => array( 'enabled' => false ) )
		);

		$this->invoke( 'migrate_library_config' );
		$first = get_site_option( AcrossAI_Category_Slug_Migration::SOURCE_OPTION );

		$this->invoke( 'migrate_library_config' );
		$second = get_site_option( AcrossAI_Category_Slug_Migration::SOURCE_OPTION );

		$this->assertSame( $first, $second );
		$this->assertArrayHasKey( 'acrossai-media', $second );
	}

	/**
	 * The completion flag short-circuits the whole migration.
	 */
	public function test_completion_flag_short_circuits(): void {
		update_option( AcrossAI_Category_Slug_Migration::DONE_OPTION, '1' );

		update_site_option(
			AcrossAI_Category_Slug_Migration::SOURCE_OPTION,
			array( 'acrossai-abilities-manager-media' => array( 'enabled' => false ) )
		);

		AcrossAI_Category_Slug_Migration::maybe_migrate();

		$config = get_site_option( AcrossAI_Category_Slug_Migration::SOURCE_OPTION );
		$this->assertArrayHasKey( 'acrossai-abilities-manager-media', $config );
	}

	/**
	 * The owned list matches the categories the plugin actually registers.
	 *
	 * If a category is added later and not listed here, its stored rows and
	 * config entries are silently left on the old prefix.
	 */
	public function test_owned_list_covers_every_registered_category(): void {
		$refl = new \ReflectionClass( AcrossAI_Category_Slug_Migration::class );
		$owned = $refl->getConstant( 'OWNED' );

		$dir   = dirname( __DIR__, 4 ) . '/includes/Abilities';
		$found = array();

		// Two declaration shapes: an inline `'category' => '…'` and a class
		// constant `const CATEGORY = '…'`. Rank Math uses the second, so a
		// pattern requiring the leading quote silently misses it — and would
		// miss any future base class that follows the same style.
		$patterns = array(
			"/'category'\s*=>\s*'acrossai-([a-z-]+)'/",
			"/const\s+CATEGORY\s*=\s*'acrossai-([a-z-]+)'/",
		);

		foreach ( glob( $dir . '/*/*.php' ) as $file ) {
			$src = (string) file_get_contents( $file );
			foreach ( $patterns as $pattern ) {
				if ( preg_match_all( $pattern, $src, $m ) ) {
					foreach ( $m[1] as $slug ) {
						$found[ $slug ] = true;
					}
				}
			}
		}

		// Guard against a vacuous pass: an empty scan would also diff to empty.
		$this->assertGreaterThanOrEqual(
			25,
			count( $found ),
			'Expected to discover at least 25 registered categories; the scan found ' . count( $found ) . '.'
		);

		$missing = array_values( array_diff( array_keys( $found ), $owned ) );

		$this->assertSame(
			array(),
			$missing,
			'Categories registered but absent from the migration OWNED list: ' . implode( ', ', $missing )
		);
	}
}
