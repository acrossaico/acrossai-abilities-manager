<?php
/**
 * Feature 108 — the warning has to reach an agent, and keep reaching it.
 *
 * Three surfaces carry it, and each is asserted here because each can be removed independently:
 * the toolset descriptions an assistant reads before choosing a tool, the per-writer suggestions,
 * and the `describe()` path that makes those suggestions visible at all.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.39
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Content_Writers_Warn_About_Builders extends WP_UnitTestCase {

	private const DETECTOR = 'content/inspect-post-builder';

	/**
	 * Abilities that rewrite an EXISTING post's body.
	 *
	 * Creators are deliberately absent: a post being created has no builder to clash with, so the
	 * suggestion would be noise. If a creator is ever changed to accept an existing ID it belongs
	 * on this list.
	 *
	 * @return array<int, string>
	 */
	private static function writers(): array {
		return array(
			'Content/Update_Post.php',
			'Content/Update_Page.php',
			'Content/Update_Cpt_Item.php',
			'Block/Update_Post_Block.php',
			'Block/Mutate_Block_Tree.php',
			'Block/Replace_Block_Text.php',
			'Block/Add_Block.php',
			'Block/Remove_Block.php',
			'Block/Move_Block.php',
			'Block/Transform_Blocks.php',
			'Block/Duplicate_Block.php',
			'Block/Normalize_Heading_Levels.php',
			'Block/Insert_Pattern.php',
			'Block/Insert_Reusable_Block_Into_Post.php',
			'Block/Extract_Reusable_Block.php',
			'Block/Set_Block_Lock.php',
			'Block/Set_Allowed_Blocks.php',
			'Block/Set_Block_Bindings.php',
			'Block/Set_Template_Lock.php',
		);
	}

	private static function abilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/';
	}

	private static function read( string $relative ): string {
		$path = self::abilities_dir() . $relative;

		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/**
	 * Every post_content writer points at the detector.
	 *
	 * This is the guard that matters as the plugin grows: a new writer added without it ships an
	 * ability that can silently no-op on any Elementor site.
	 */
	public function test_every_post_content_writer_suggests_the_detector(): void {
		foreach ( self::writers() as $relative ) {
			$src = self::read( $relative );

			$this->assertNotSame( '', $src, "{$relative} is missing — update this list or restore the file." );
			$this->assertStringContainsString(
				'function suggested_abilities',
				$src,
				"{$relative} rewrites post_content but declares no suggested_abilities()."
			);
			$this->assertStringContainsString(
				self::DETECTOR,
				$src,
				"{$relative} rewrites post_content without suggesting " . self::DETECTOR . '.'
			);
		}
	}

	/**
	 * The list above must not silently fall behind the directory.
	 *
	 * Any ability that calls wp_update_post() with a post_content key is a writer. Creators are
	 * excluded by name; anything else new must be added to the list, with its suggestion.
	 */
	public function test_no_unlisted_ability_rewrites_post_content(): void {
		$known    = self::writers();
		$creators = array(
			'Content/Create_Post.php',
			'Content/Create_Page.php',
			'Content/Create_Cpt_Item.php',
		);

		/*
		 * Pinned to a post type no page builder can own. `wp_block` and `wp_navigation` are always
		 * block markup by definition, so the detector would answer "block-editor" every time and
		 * the suggestion would be pure noise. Excluded on that basis, not overlooked.
		 */
		$never_builder_owned = array(
			'Block/Update_Navigation.php',
			'Block/Update_Reusable_Block.php',
		);

		$unlisted = array();

		foreach ( array( 'Content', 'Block' ) as $folder ) {
			$files = glob( self::abilities_dir() . $folder . '/*.php' );

			foreach ( is_array( $files ) ? $files : array() as $file ) {
				$relative = $folder . '/' . basename( $file );

				if ( in_array( $relative, $known, true )
					|| in_array( $relative, $creators, true )
					|| in_array( $relative, $never_builder_owned, true ) ) {
					continue;
				}

				$src = (string) file_get_contents( $file );

				// A writer of an existing body: updates a post AND names post_content.
				if ( false === strpos( $src, 'wp_update_post(' ) || false === strpos( $src, 'post_content' ) ) {
					continue;
				}

				// Creators build a fresh post; they have nothing to clash with.
				if ( false !== strpos( $src, 'wp_insert_post(' ) && false === strpos( $src, 'wp_update_post(' ) ) {
					continue;
				}

				if ( false === strpos( $src, self::DETECTOR ) ) {
					$unlisted[] = $relative;
				}
			}
		}

		$this->assertSame(
			array(),
			$unlisted,
			'These rewrite post_content but neither suggest the detector nor appear on the reviewed list: ' . implode( ', ', $unlisted )
		);
	}

	/**
	 * The toolset descriptions carry the warning.
	 *
	 * This is the only surface guaranteed to be read before tool selection — suggestions are not
	 * in `discover`, by contract. Both groups need it: an agent told to use the surgical block
	 * writers instead of update-post lands in the Blocks tool, where post_content is just as
	 * absent on an Elementor page.
	 */
	public function test_both_toolset_descriptions_name_the_detector(): void {
		foreach ( array( 'Content', 'Blocks' ) as $toolset ) {
			$src = self::read( '../Abilities/Toolset/' . $toolset . '.php' );

			if ( '' === $src ) {
				$src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Toolset/' . $toolset . '.php' );
			}

			$this->assertStringContainsString(
				self::DETECTOR,
				$src,
				"The {$toolset} toolset description must tell an assistant to run the detector before editing an existing post."
			);
		}
	}

	/**
	 * `describe()` surfaces suggestions, and declares them in its own output schema.
	 *
	 * Both halves are required. Adding the key to the row without declaring it fails the
	 * dispatcher's own `additionalProperties: false` validation — the response is rejected after
	 * the work is done, the same shape as BUG-ARRAY-TYPED-OUTPUT-IS-A-JSON-OBJECT.
	 */
	public function test_the_dispatcher_surfaces_and_declares_suggestions(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Toolset/Base_Toolset_Ability.php' );

		$this->assertStringContainsString(
			'suggested_abilities_of',
			$src,
			'describe() must read the ability\'s declared suggestions.'
		);
		$this->assertMatchesRegularExpression(
			"/'suggested_abilities' => array\(\s*'type'\s*=> 'array'/",
			$src,
			'suggested_abilities must be declared in the response schema, or the row fails additionalProperties: false.'
		);
	}

	/**
	 * The admin kill-switch still governs the MCP path.
	 *
	 * `acrossai_disable_ability_suggestions` is honoured once in the Library registry. Surfacing
	 * suggestions through the dispatcher without re-checking it would leave the toggle looking
	 * effective while doing nothing for the surface that matters most.
	 */
	public function test_the_dispatcher_honours_the_suggestions_kill_switch(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Toolset/Base_Toolset_Ability.php' );

		$this->assertStringContainsString(
			"get_option( 'acrossai_disable_ability_suggestions', 0 )",
			$src,
			'The dispatcher must respect the same kill-switch as the Library path.'
		);
	}

	/**
	 * Suggestions stay out of discovery.
	 *
	 * DEC-ABILITY-SUGGESTED-ABILITIES-CONTRACT keeps listing payloads small on purpose. Feature 108
	 * adds them to info only; if they ever appear in summarise() the contract needs revisiting
	 * first, not silently.
	 */
	public function test_discovery_rows_stay_lean(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Toolset/Base_Toolset_Ability.php' );

		$start = strpos( $src, 'private function summarise(' );
		$this->assertNotFalse( $start );

		$end = strpos( $src, 'private function describe(', $start );
		$this->assertNotFalse( $end );

		$this->assertStringNotContainsString(
			'suggested_abilities',
			substr( $src, $start, $end - $start ),
			'summarise() builds the discover row and must stay free of suggestions.'
		);
	}

	/**
	 * The detector itself is registered and reachable.
	 */
	public function test_the_detector_ability_exists_and_is_wired(): void {
		$src = self::read( 'Content/Inspect_Post_Builder.php' );

		$this->assertStringContainsString( "'name' => '" . self::DETECTOR . "'", $src );
		$this->assertStringContainsString( "'tab_group'       => 'content'", $src, 'It must sit in the same toolset as the writers it warns about.' );

		$bootstrap = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		$this->assertStringContainsString(
			'new Content\\Inspect_Post_Builder();',
			$bootstrap,
			'The ability is declared but never instantiated.'
		);
	}
}
