<?php
/**
 * Feature 128 — fixes found by driving the abilities over MCP.
 *
 * Five issues, one theme: abilities that were correct but expensive or misleading to an AI caller.
 * Full post bodies with no way to ask for less, a `total` that contradicted its own name, raw
 * dashicon markup in a health report, annotations that said nothing, and a cache ability that did
 * not mention the plugin actually holding the page cache.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.36
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Post_Summary;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry;
use WP_UnitTestCase;

class Test_Feature_128_Caller_Ergonomics extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		global $__acrossai_test_options;
		$__acrossai_test_options = array();
	}

	// ------------------------------------------------------------------ fix 1

	/**
	 * All three list abilities offer `fields`, and default to the old behaviour.
	 *
	 * The default is the backwards-compatibility contract: a caller that passes nothing must get
	 * what it got before, byte for byte.
	 */
	public function test_every_list_ability_offers_fields_and_defaults_to_full(): void {
		foreach ( array( 'List_Posts', 'List_Pages', 'List_Cpt_Items' ) as $class ) {
			$src = self::read( self::content() . $class . '.php' );

			$this->assertStringContainsString( "'fields'", $src, "{$class} must offer the fields input." );
			$this->assertStringContainsString( 'Post_Summary::input_property()', $src, "{$class} must share the one definition." );
			$this->assertStringContainsString(
				'Post_Summary::rows(',
				$src,
				"{$class} must shape its rows through the shared helper, so the three cannot drift."
			);
			$this->assertStringNotContainsString(
				'(array) $p;',
				self::code_only( $src ),
				"{$class} must no longer emit the whole WP_Post unconditionally."
			);
		}

		$property = Post_Summary::input_property();

		$this->assertSame( 'full', $property['default'], 'The default must reproduce current behaviour.' );
		$this->assertSame( array( 'full', 'summary' ), $property['enum'] );
	}

	/**
	 * `summary` carries what identifies an item, and none of the body.
	 */
	public function test_summary_drops_the_body_and_keeps_the_identifiers(): void {
		$post = self::post_stub( 'Hello world', str_repeat( 'x', 4096 ) );

		$row = Post_Summary::row( $post, true );

		foreach ( array( 'ID', 'post_title', 'post_status', 'post_type', 'post_date', 'post_modified', 'post_name', 'post_excerpt', 'post_author', 'content_bytes' ) as $key ) {
			$this->assertArrayHasKey( $key, $row, "summary must carry {$key}." );
		}

		$this->assertArrayNotHasKey( 'post_content', $row, 'summary must not carry the body.' );
		$this->assertArrayNotHasKey( 'post_content_filtered', $row );
		$this->assertSame( 4096, $row['content_bytes'], 'content_bytes lets a caller size the follow-up read.' );
		$this->assertCount( 10, $row, 'summary is exactly the ten documented fields.' );
	}

	/**
	 * `full` is the unmodified post, so existing callers see no change.
	 */
	public function test_full_is_the_unmodified_post(): void {
		$post = self::post_stub( 'Hello world', 'body' );

		$this->assertSame( (array) $post, Post_Summary::row( $post, false ) );
		$this->assertFalse( Post_Summary::wants_summary( array() ), 'No input means full.' );
		$this->assertFalse( Post_Summary::wants_summary( array( 'fields' => 'full' ) ) );
		$this->assertFalse(
			Post_Summary::wants_summary( array( 'fields' => 'nonsense' ) ),
			'An unrecognised value must fail towards full, not silently drop content.'
		);
		$this->assertTrue( Post_Summary::wants_summary( array( 'fields' => 'summary' ) ) );
	}

	/**
	 * An empty excerpt falls back to the body, stripped.
	 *
	 * Most posts have no excerpt, so returning it verbatim would make summaries useless on exactly
	 * the sites that need them.
	 */
	public function test_summary_falls_back_to_a_stripped_body_when_there_is_no_excerpt(): void {
		$row = Post_Summary::row( self::post_stub( 'T', '<p>Hello <strong>there</strong></p>', '' ), true );

		$this->assertStringContainsString( 'Hello there', $row['post_excerpt'] );
		$this->assertStringNotContainsString( '<', $row['post_excerpt'], 'The excerpt must not carry markup.' );

		$long = Post_Summary::row( self::post_stub( 'T', str_repeat( 'word ', 400 ), '' ), true );

		$this->assertLessThanOrEqual(
			Post_Summary::EXCERPT_CHARS + 1,
			mb_strlen( $long['post_excerpt'] ),
			'The excerpt must stay bounded or a hundred rows are not cheap.'
		);
	}

	// ------------------------------------------------------------------ fix 2

	/**
	 * `total` counts matches, not the truncated page.
	 *
	 * Measured before the fix: max_results 3 on a 40-block post reported total: 3.
	 */
	public function test_outline_counts_every_match_not_just_the_returned_page(): void {
		$src  = self::read( self::dir() . 'Block/Outline_Post_Blocks.php' );
		$code = self::code_only( $src );

		$this->assertMatchesRegularExpression(
			"/'total'\s*=> \(int\) \\\$outcome\['matched'\]/",
			$code,
			'total must come from the match counter, not from the returned array.'
		);
		$this->assertMatchesRegularExpression(
			"/'returned'\s*=> count\( \\\$outcome\['blocks'\] \)/",
			$code,
			'returned carries what total used to say.'
		);

		// The early return is what stopped the walk and made total unknowable.
		$this->assertDoesNotMatchRegularExpression(
			'/if \( \$truncated \) \{\s*return;/',
			$code,
			'Bailing out at the cap stops counting; collection stops, counting must not.'
		);
		$this->assertMatchesRegularExpression(
			'/if \( count\( \$entries \) < \$max_results \) \{/',
			$code,
			'Collection is capped by an explicit bound, not by abandoning the walk.'
		);
	}

	/**
	 * subtree_bytes sits beside bytes and includes descendants.
	 */
	public function test_outline_reports_subtree_bytes_beside_bytes(): void {
		$code = self::code_only( self::read( self::dir() . 'Block/Outline_Post_Blocks.php' ) );

		$this->assertStringContainsString( '\'bytes\'      => strlen( $inner_html )', $code, 'bytes stays the block\'s own size.' );
		$this->assertStringContainsString( 'subtree_bytes', $code );
		$this->assertMatchesRegularExpression(
			'/\$bytes \+= self::subtree_bytes\( \$child \);/',
			$code,
			'subtree_bytes must actually recurse, or it is just bytes again.'
		);
	}

	/**
	 * The depth semantics are documented. Behaviour unchanged — wording only.
	 */
	public function test_outline_documents_its_depth_semantics(): void {
		$this->assertStringContainsString(
			'depth counts from the post: 1 returns top-level blocks; 0 with no path returns nothing.',
			self::read( self::dir() . 'Block/Outline_Post_Blocks.php' )
		);
	}

	// ------------------------------------------------------------------ fix 4

	/**
	 * Every ability must declare all three annotations.
	 *
	 * A null is not "unknown" to an AI caller — it reads as "not destructive", which is the
	 * dangerous direction to be wrong in.
	 */
	public function test_missing_annotations_are_detected(): void {
		$this->assertSame(
			array(),
			Ability_Definition::missing_annotations(
				array( 'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ) )
			),
			'A complete set reports nothing missing.'
		);

		$this->assertSame(
			array( 'readonly', 'destructive', 'idempotent' ),
			Ability_Definition::missing_annotations( array() ),
			'No annotations at all must name all three.'
		);

		$this->assertSame(
			array( 'destructive' ),
			Ability_Definition::missing_annotations(
				array( 'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => null, 'idempotent' => true ) ) )
			),
			'A key present but null is exactly the case this guard exists for.'
		);

		$this->assertSame(
			array( 'idempotent' ),
			Ability_Definition::missing_annotations(
				array( 'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => true ) ) )
			)
		);
	}

	/**
	 * Every ability this plugin ships declares all three.
	 *
	 * The registry-wide sweep the task asked for: in tests this fails rather than warns.
	 */
	public function test_no_shipped_ability_is_missing_annotations(): void {
		$offenders = array();

		foreach ( self::ability_sources() as $path ) {
			// Comments stripped first: two docblocks carry an example registration for third-party
			// authors ('my-plugin/my-acf-helper'), which is documentation, not a shipped ability.
			$src = self::code_only( self::read( $path ) );

			// Only classes that assemble their own args here; suites that go through a shared base
			// declare annotations on the base's behalf via an abstract the base merges in.
			if ( ! preg_match( "/'name'\s*=>\s*'([a-z0-9-]+\/[a-z0-9-]+)'/", $src, $m ) ) {
				continue;
			}

			if ( ! preg_match( '/[\'"]annotations[\'"]/', $src ) ) {
				$offenders[] = $m[1];
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			"These abilities assemble their own args but declare no annotations:\n  " . implode( "\n  ", $offenders )
		);
	}

	/**
	 * The runtime check is advisory and costs nothing in production.
	 */
	public function test_the_runtime_annotation_check_is_debug_only(): void {
		$body = self::code_only( self::method_body( self::read( self::lib() . 'Ability_Definition.php' ), 'assert_annotations' ) );

		$this->assertMatchesRegularExpression(
			"/if \( ! defined\( 'WP_DEBUG' \) \|\| ! WP_DEBUG \) \{\s*return;/",
			$body,
			'It must return immediately when WP_DEBUG is off — this runs for every ability on every request.'
		);
		$this->assertStringContainsString( '_doing_it_wrong(', $body, 'With WP_DEBUG on it must name the ability.' );
		$this->assertStringNotContainsString( 'throw ', $body, 'It must never throw; registration would break.' );
	}

	// ------------------------------------------------------------------ fix 5

	/**
	 * Site health offers `format`, defaulting to the markup it returns today.
	 */
	public function test_site_health_offers_a_text_format_defaulting_to_html(): void {
		$src = self::read( self::dir() . 'SiteHealth/Get_Site_Health_Status.php' );

		$this->assertMatchesRegularExpression(
			"/'enum'\s*=> array\( 'html', 'text' \),\s*'default'\s*=> 'html',/",
			$src,
			'The default must reproduce current behaviour.'
		);
		$this->assertMatchesRegularExpression(
			"/\\\$as_text = isset\( \\\$input\['format'\] \) && 'text' === \\\$input\['format'\];/",
			self::code_only( $src ),
			'Anything but the exact string "text" must mean html.'
		);
	}

	/**
	 * The text form drops markup, keeps link destinations, and decodes entities.
	 */
	public function test_site_health_text_form_is_readable(): void {
		$body = self::code_only( self::method_body( self::read( self::dir() . 'SiteHealth/Get_Site_Health_Status.php' ), 'to_text' ) );

		$this->assertStringContainsString( 'screen-reader-text', $body, 'Screen-reader spans duplicate every link label.' );
		$this->assertStringContainsString( 'wp_strip_all_tags(', $body );
		$this->assertStringContainsString( 'html_entity_decode(', $body );
		$this->assertMatchesRegularExpression(
			"/\\\$text \. ' \(' \. \\\$url \. '\)'/",
			$body,
			'A link destination is usually the actionable part and must survive.'
		);
	}

	// ------------------------------------------------------------------ fix 6

	/**
	 * A conditional suggestion survives when its target is in the set.
	 */
	public function test_a_conditional_suggestion_survives_when_its_target_exists(): void {
		$rows = self::rows_with_conditional_suggestion();
		$rows[] = array(
			'name' => 'litespeed/purge-cache',
			'args' => array( 'meta' => array( 'acrossai' => array() ) ),
		);

		$decorated = AcrossAI_Ability_Library_Registry::apply_suggested_abilities_decoration( $rows );
		$entries   = $decorated[0]['args']['meta']['acrossai']['suggested_abilities'];

		$this->assertCount( 1, $entries );
		$this->assertSame( 'litespeed/purge-cache', $entries[0]['slug'] );
		$this->assertArrayNotHasKey(
			'only_if_registered',
			$entries[0],
			'The flag instructs this pass; a caller should not have to read it.'
		);
	}

	/**
	 * It disappears when the target is absent.
	 */
	public function test_a_conditional_suggestion_is_pruned_when_its_target_is_absent(): void {
		$decorated = AcrossAI_Ability_Library_Registry::apply_suggested_abilities_decoration( self::rows_with_conditional_suggestion() );

		$this->assertArrayNotHasKey(
			'suggested_abilities',
			$decorated[0]['args']['meta']['acrossai'],
			'A hint pointing at an ability this site does not have is worse than no hint.'
		);
	}

	/**
	 * An unflagged suggestion is never pruned.
	 *
	 * The framework documents that a suggestion may point at an ability the caller's site does not
	 * have — third-party ability plugins rely on it. Only the opt-in flag changes that.
	 */
	public function test_an_unflagged_suggestion_is_left_alone(): void {
		$rows = array(
			array(
				'name' => 'cache/flush-transients',
				'args' => array(
					'meta' => array(
						'acrossai' => array(
							'suggested_abilities' => array(
								array( 'slug' => 'some-plugin/not-installed', 'reason' => 'Third-party target.' ),
							),
						),
					),
				),
			),
		);

		$decorated = AcrossAI_Ability_Library_Registry::apply_suggested_abilities_decoration( $rows );

		$this->assertCount( 1, $decorated[0]['args']['meta']['acrossai']['suggested_abilities'] );
	}

	/**
	 * The kill switch still wins over everything.
	 */
	public function test_the_kill_switch_still_strips_conditional_suggestions(): void {
		update_option( 'acrossai_disable_ability_suggestions', 1 );

		$rows = self::rows_with_conditional_suggestion();
		$rows[] = array( 'name' => 'litespeed/purge-cache', 'args' => array() );

		$decorated = AcrossAI_Ability_Library_Registry::apply_suggested_abilities_decoration( $rows );

		$this->assertArrayNotHasKey( 'suggested_abilities', $decorated[0]['args']['meta']['acrossai'] );
	}

	/**
	 * The three cache abilities carry the conditional hint.
	 */
	public function test_the_cache_abilities_point_at_litespeed_conditionally(): void {
		foreach ( array( 'Delete_Expired_Transients', 'Flush_Transients', 'Flush_Object_Cache' ) as $class ) {
			$src = self::read( self::dir() . 'Cache/' . $class . '.php' );

			$this->assertStringContainsString( "'litespeed/purge-cache'", $src, "{$class} must name the page-cache ability." );
			$this->assertStringContainsString( "'only_if_registered'  => true", $src, "{$class} must gate the hint on that ability existing." );
			$this->assertStringContainsString(
				'For page cache purges (what visitors see), use LiteSpeed',
				$src,
				"{$class} must explain the distinction, not just point."
			);
		}
	}

	// ---------------------------------------------------------------- helpers

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function rows_with_conditional_suggestion(): array {
		return array(
			array(
				'name' => 'cache/flush-transients',
				'args' => array(
					'meta' => array(
						'acrossai' => array(
							'sub_group'           => 'transients',
							'suggested_abilities' => array(
								array(
									'slug'               => 'litespeed/purge-cache',
									'reason'             => 'For page cache purges, use LiteSpeed.',
									'only_if_registered' => true,
								),
							),
						),
					),
				),
			),
		);
	}

	private static function post_stub( string $title, string $content, string $excerpt = 'An excerpt' ): \WP_Post {
		return new \WP_Post(
			(object) array(
				'ID'                    => 7,
				'post_title'            => $title,
				'post_status'           => 'publish',
				'post_type'             => 'post',
				'post_date'             => '2026-01-01 00:00:00',
				'post_modified'         => '2026-01-02 00:00:00',
				'post_name'             => 'hello-world',
				'post_excerpt'          => $excerpt,
				'post_author'           => 1,
				'post_content'          => $content,
				'post_content_filtered' => '',
			)
		);
	}

	/**
	 * @return string[]
	 */
	private static function ability_sources(): array {
		$out = array();
		$it  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::dir() ) );

		foreach ( $it as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$out[] = $file->getPathname();
			}
		}

		return $out;
	}

	private static function dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/';
	}

	private static function content(): string {
		return self::dir() . 'Content/';
	}

	private static function lib(): string {
		return dirname( __DIR__, 3 ) . '/includes/Modules/Library/';
	}

	private static function read( string $path ): string {
		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	private static function code_only( string $src ): string {
		if ( '' === $src ) {
			return '';
		}

		$out = '';

		foreach ( token_get_all( 0 === strpos( $src, '<?php' ) ? $src : '<?php ' . $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			$out .= is_array( $token ) ? $token[1] : $token;
		}

		return $out;
	}

	private static function method_body( string $src, string $method ): string {
		$start = strpos( $src, 'function ' . $method . '(' );

		if ( false === $start ) {
			return '';
		}

		$brace = strpos( $src, '{', $start );

		if ( false === $brace ) {
			return '';
		}

		$depth = 0;
		$len   = strlen( $src );

		for ( $i = $brace; $i < $len; $i++ ) {
			if ( '{' === $src[ $i ] ) {
				++$depth;
			} elseif ( '}' === $src[ $i ] ) {
				--$depth;

				if ( 0 === $depth ) {
					return substr( $src, $brace, $i - $brace + 1 );
				}
			}
		}

		return substr( $src, $brace );
	}
}
