<?php
/**
 * Feature 105 — the whole-suite contract.
 *
 * Verifies the 16 abilities as a SET: slug list, sub-group split, which need ACF Pro, the
 * confirmation set, and that every suggested slug resolves. A per-ability test cannot catch a
 * missing ability, a duplicated slug, or an ability that moved group without its gate moving too.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.37
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Acf_Suite_Contract extends WP_UnitTestCase {

	/**
	 * Every ability's class => slug.
	 *
	 * Note the two slug namespaces. Field data lives under `custom-fields/` and block operations
	 * under `blocks/`, because a slug names the resource acted on, while the toolset names where an
	 * operator finds it (DEC-TOOLSET-SLUG-NAMESPACE). All 16 share tab_group `acf`.
	 *
	 * @return array<string,string>
	 */
	private static function inventory(): array {
		return array(
			// acf-fields (5).
			'Delete_Acf_Field'         => 'custom-fields/delete-acf-field',
			'Get_Acf_Field'            => 'custom-fields/get-acf-field',
			'Get_Acf_Fields'           => 'custom-fields/get-acf-fields',
			'Update_Acf_Field'         => 'custom-fields/update-acf-field',
			'Update_Acf_Fields'        => 'custom-fields/update-acf-fields',
			// acf-rows (6).
			'Add_Acf_Flex_Layout'      => 'custom-fields/add-acf-flex-layout',
			'Add_Acf_Repeater_Row'     => 'custom-fields/add-acf-repeater-row',
			'Remove_Acf_Flex_Layout'   => 'custom-fields/remove-acf-flex-layout',
			'Remove_Acf_Repeater_Row'  => 'custom-fields/remove-acf-repeater-row',
			'Reorder_Acf_Repeater_Rows'=> 'custom-fields/reorder-acf-repeater-rows',
			'Update_Acf_Repeater_Row'  => 'custom-fields/update-acf-repeater-row',
			// acf-blocks (5).
			'Get_Acf_Block_Fields'     => 'blocks/get-acf-block-fields',
			'Insert_Acf_Block'         => 'blocks/insert-acf-block',
			'List_Acf_Blocks'          => 'blocks/list-acf-blocks',
			'Register_Acf_Block'       => 'blocks/register-acf-block',
			'Update_Acf_Block_Data'    => 'blocks/update-acf-block-data',
		);
	}

	/**
	 * The abilities that require ACF Pro.
	 *
	 * Pinned explicitly so moving an ability between sub-groups without changing its gate fails.
	 * The split is not arbitrary: `repeater` and `flexible_content` are Pro-only FIELD TYPES and
	 * `acf_register_block_type()` is a Pro-only function, whereas `get_field()`/`update_field()`/
	 * `delete_field()` ship in both editions.
	 *
	 * @return string[]
	 */
	private static function pro_only(): array {
		return array(
			'Add_Acf_Flex_Layout',
			'Add_Acf_Repeater_Row',
			'Get_Acf_Block_Fields',
			'Insert_Acf_Block',
			'List_Acf_Blocks',
			'Register_Acf_Block',
			'Remove_Acf_Flex_Layout',
			'Remove_Acf_Repeater_Row',
			'Reorder_Acf_Repeater_Rows',
			'Update_Acf_Block_Data',
			'Update_Acf_Repeater_Row',
		);
	}

	private static function abilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Acf/';
	}

	private static function src( string $class ): string {
		return (string) file_get_contents( self::abilities_dir() . $class . '.php' );
	}

	public function test_suite_has_exactly_sixteen_abilities(): void {
		$this->assertCount( 16, self::inventory() );
	}

	public function test_inventory_matches_the_filesystem(): void {
		$skip  = array( 'Category_Registrar', 'Base_Acf_Ability' );
		$found = array_values(
			array_filter(
				array_map(
					static fn( string $f ): string => basename( $f, '.php' ),
					array_map( 'strval', (array) glob( self::abilities_dir() . '*.php' ) )
				),
				static fn( string $c ): bool => ! in_array( $c, $skip, true )
			)
		);

		$expected = array_keys( self::inventory() );
		sort( $expected );
		sort( $found );

		$this->assertSame( $expected, $found );
	}

	public function test_every_slug_is_unique(): void {
		$slugs = array_values( self::inventory() );

		$this->assertSame( $slugs, array_unique( $slugs ) );
	}

	/**
	 * Slugs live under exactly the two namespaces this suite claims, verb-first after the slash.
	 */
	public function test_slugs_use_the_expected_namespaces(): void {
		$verbs = array( 'get', 'list', 'update', 'delete', 'add', 'remove', 'reorder', 'register', 'insert' );

		foreach ( self::inventory() as $class => $slug ) {
			$this->assertMatchesRegularExpression(
				'#^(custom-fields|blocks)/[a-z0-9]+(-[a-z0-9]+)*$#',
				$slug,
				"{$class}: '{$slug}' must live under custom-fields/ or blocks/."
			);

			$suffix = explode( '/', $slug )[1];
			$this->assertContains( explode( '-', $suffix )[0], $verbs, "{$class}: '{$slug}' does not start with a known verb." );
		}
	}

	public function test_every_class_declares_its_slug(): void {
		foreach ( self::inventory() as $class => $slug ) {
			$this->assertStringContainsString( "return '{$slug}';", self::src( $class ), "{$class} must return '{$slug}'." );
		}
	}

	public function test_the_sub_group_split_is_five_six_five(): void {
		$counts = array();

		foreach ( array_keys( self::inventory() ) as $class ) {
			preg_match( "/function sub_group\(\): string \{\s*return '([a-z-]+)';/", self::src( $class ), $m );
			$counts[ $m[1] ] = ( $counts[ $m[1] ] ?? 0 ) + 1;
		}

		ksort( $counts );

		$this->assertSame(
			array(
				'acf-blocks' => 5,
				'acf-fields' => 5,
				'acf-rows'   => 6,
			),
			$counts
		);
	}

	/**
	 * THE gating assertion. Exactly these 11 declare requires_pro(); the other 5 must not.
	 */
	public function test_the_pro_only_set_is_exact(): void {
		$found = array();

		foreach ( array_keys( self::inventory() ) as $class ) {
			if ( preg_match( '/function requires_pro\(\): bool \{\s*return true;/', self::src( $class ) ) ) {
				$found[] = $class;
			}
		}

		sort( $found );
		$expected = self::pro_only();
		sort( $expected );

		$this->assertSame( $expected, $found );
		$this->assertCount( 11, $expected, 'Eleven of the sixteen require ACF Pro.' );
	}

	/**
	 * The row abilities gate on the FIELD TYPE, never on function_exists().
	 *
	 * add_row(), update_row() and delete_row() ship in BOTH editions, so a function check passes on
	 * free ACF — where repeater and flexible_content are not registered types and no such field can
	 * exist. Six abilities would be advertised that can never succeed. Issue #169 proposed exactly
	 * that check; this assertion is what stops it coming back.
	 */
	public function test_row_abilities_gate_on_the_field_type(): void {
		$rows = array();

		foreach ( array_keys( self::inventory() ) as $class ) {
			$src = self::src( $class );

			if ( preg_match( "/function sub_group\(\): string \{\s*return 'acf-rows';/", $src ) ) {
				$rows[] = $class;
			}
		}

		$this->assertCount( 6, $rows );

		foreach ( $rows as $class ) {
			$src = self::src( $class );

			$this->assertMatchesRegularExpression(
				'/function required_field_types\(\): array \{\s*return array\(\s*\'(repeater|flexible_content)\'/',
				$src,
				"{$class} must declare the field type it needs."
			);
			$this->assertStringNotContainsString(
				"function_exists( 'add_row'",
				$src,
				"{$class} must not gate on function_exists( 'add_row' ) — it is true on free ACF."
			);
		}
	}

	/**
	 * Confirmation is required exactly where something is destroyed.
	 */
	public function test_destructive_and_confirmation_agree(): void {
		foreach ( array_keys( self::inventory() ) as $class ) {
			$src         = self::src( $class );
			$destructive = (bool) preg_match( "/'destructive' => true/", $src );
			$confirms    = (bool) preg_match( '/function requires_confirmation\(\): bool \{\s*return true;/', $src );

			$this->assertSame(
				$destructive,
				$confirms,
				"{$class}: destructive=" . var_export( $destructive, true )
					. ' but requires_confirmation=' . var_export( $confirms, true ) . '. Both or neither.'
			);
		}
	}

	/**
	 * `is_writer()` must mean exactly "this ability slashes a caller-supplied string".
	 *
	 * Brief 097 asked for the apply_wp_slash flag on delete-acf-field too, "for consistency". That is
	 * declined deliberately: a delete takes no value, so the flag would appear in the schema and do
	 * nothing, and an AI client reading it would reason about a control that has no effect. The four
	 * abilities that write without accepting a caller string — the three deletes and the reorder,
	 * which only rearranges values already stored — therefore do not carry it.
	 */
	public function test_is_writer_means_the_ability_slashes_input(): void {
		$slashing = 0;

		foreach ( array_keys( self::inventory() ) as $class ) {
			$src    = self::src( $class );
			$writer = (bool) preg_match( '/function is_writer\(\): bool \{\s*return true;/', $src );
			$slash  = str_contains( $src, 'Slash_Input::slash(' );

			$this->assertSame(
				$writer,
				$slash,
				"{$class}: is_writer=" . var_export( $writer, true ) . ' but Slash_Input::slash() '
					. ( $slash ? 'IS' : 'is NOT' ) . ' called. The flag must advertise real behaviour.'
			);

			if ( $writer ) {
				++$slashing;
			}
		}

		$this->assertSame( 7, $slashing, 'Seven abilities accept a caller-supplied string.' );
	}

	/**
	 * A readonly ability must never write.
	 */
	public function test_readonly_abilities_do_not_write(): void {
		foreach ( array_keys( self::inventory() ) as $class ) {
			$src = self::src( $class );

			if ( ! preg_match( "/'readonly'    => true/", $src ) ) {
				continue;
			}

			foreach ( array( 'Field_Repository::update(', 'Field_Repository::delete(', 'Field_Repository::add_row(', 'Block_Repository::insert(', 'Block_Repository::register(' ) as $writer ) {
				$this->assertStringNotContainsString( $writer, $src, "{$class} is readonly but calls {$writer}." );
			}
		}
	}

	/**
	 * Every ability naming a follow-up must name one that exists.
	 *
	 * @return string[]
	 */
	private static function external_suggestions(): array {
		return array( 'blocks/outline-post-blocks', 'blocks/update-post-block' );
	}

	public function test_every_suggested_ability_exists(): void {
		$own       = array_values( self::inventory() );
		$known     = array_merge( $own, self::external_suggestions() );
		$suggested = array();

		foreach ( array_keys( self::inventory() ) as $class ) {
			if ( ! preg_match( '/function suggested_abilities\(\): array \{(.*?)\n\t\}/s', self::src( $class ), $m ) ) {
				continue;
			}

			preg_match_all( "/'([a-z0-9-]+\/[a-z0-9-]+)'/", $m[1], $found );

			foreach ( $found[1] as $slug ) {
				$suggested[ $slug ][] = $class;
			}
		}

		$this->assertNotEmpty( $suggested, 'No ability suggests a follow-up; the framework is going unused.' );

		foreach ( $suggested as $slug => $classes ) {
			$this->assertContains(
				$slug,
				$known,
				sprintf( '%s suggests "%s", which does not exist.', implode( ', ', $classes ), $slug )
			);
		}
	}

	/**
	 * All 16 join the EXISTING acf toolset rather than creating a new one, so the base's TAB_GROUP
	 * must equal what Integrations\ACF declares. If they drift, the abilities land on one tab and
	 * ACF's own on another.
	 */
	public function test_the_tab_group_matches_the_existing_acf_integration(): void {
		$base        = (string) file_get_contents( self::abilities_dir() . 'Base_Acf_Ability.php' );
		$integration = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/ACF.php' );

		$this->assertMatchesRegularExpression( "/const TAB_GROUP = 'acf';/", $base );
		$this->assertMatchesRegularExpression( "/const TAB_GROUP = 'acf';/", $integration );
	}

	public function test_the_bootstrap_instantiates_every_ability(): void {
		$bootstrap = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		foreach ( array_keys( self::inventory() ) as $class ) {
			$this->assertStringContainsString(
				'new Acf\\' . $class . '()',
				$bootstrap,
				"{$class} is never instantiated in the bootstrap, so it never registers."
			);
		}
	}
}
