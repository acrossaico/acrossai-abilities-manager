<?php
/**
 * Feature 120 — the generic writers must not treat a plugin-owned post type as ordinary.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.50
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Protected_Post_Types;
use WP_UnitTestCase;

class Test_Protected_Post_Types extends WP_UnitTestCase {

	/**
	 * The six writers the guard must cover.
	 *
	 * @return string[]
	 */
	private static function writers(): array {
		return array(
			'Content/Create_Cpt_Item.php',
			'Content/Update_Cpt_Item.php',
			'Content/Delete_Cpt_Item.php',
			'Content/Add_Post_Meta.php',
			'Content/Update_Post_Meta.php',
			'Content/Delete_Post_Meta.php',
		);
	}

	private static function read( string $relative ): string {
		$path = dirname( __DIR__, 3 ) . '/includes/Abilities/' . $relative;

		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	private static function code_only( string $src ): string {
		$out = '';

		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			$out .= is_array( $token ) ? $token[1] : $token;
		}

		return $out;
	}

	/**
	 * Drive the descriptor table directly.
	 *
	 * The behavioural tests must not depend on WooCommerce being installed in the harness — it is
	 * not. An earlier version guarded every assertion behind "if the owner is absent, return", which
	 * meant the verdict logic was never exercised at all: four mutations to the blocking rules
	 * survived because the tests took the early exit every time.
	 *
	 * @param string $owner_probe A function name; use one that exists to simulate an active owner.
	 */
	private function seed_table( string $owner_probe ): void {
		$GLOBALS['acrossai_test_filter_values']['acrossai_protected_post_types'] = array(
			'zz_derived'  => array(
				'owner'     => 'Ghost Commerce',
				'writes'    => Protected_Post_Types::WRITES_INCOMPLETE,
				'active_if' => array( 'function' => $owner_probe ),
				'authority' => 'the posts table plus a lookup table',
				'stale'     => array( 'zz_lookup', '_price' ),
				'instead'   => array( 'ghost/update-thing' ),
			),
			'zz_external' => array(
				'owner'     => 'Ghost Commerce',
				'writes'    => Protected_Post_Types::WRITES_DISCARDED,
				'active_if' => array( 'function' => $owner_probe ),
				'authority' => 'its own tables',
				'stale'     => array( 'zz_things' ),
				'instead'   => array( 'ghost/read-thing' ),
			),
		);
	}

	protected function tearDown(): void {
		unset( $GLOBALS['acrossai_test_filter_values']['acrossai_protected_post_types'] );

		parent::tearDown();
	}

	/**
	 * An unlisted post type is ordinary and nothing is blocked.
	 *
	 * The guard must stay invisible for the 99% case; a guard that fires on ordinary content would
	 * be routed around within a day.
	 */
	public function test_an_ordinary_post_type_is_untouched(): void {
		foreach ( array( 'post', 'page', 'attachment', 'zz_made_up' ) as $type ) {
			$verdict = Protected_Post_Types::inspect( $type );

			$this->assertSame( Protected_Post_Types::WRITES_APPLY, $verdict['writes'], "{$type} must be ordinary." );
			$this->assertFalse( Protected_Post_Types::assess( $type, true )['blocked'] );
			$this->assertFalse( Protected_Post_Types::assess( $type, false )['blocked'] );
		}
	}

	/**
	 * A meta write to a derived-data type is refused; a title write is not.
	 *
	 * The scoping is the whole design. A product's `post_content` genuinely IS its description and
	 * the block writers edit it legitimately, so a blanket ban would be wrong. What must not happen
	 * is a meta write, because that is where the derived values live.
	 *
	 * Measured on WooCommerce 11.1: writing `_regular_price` through the generic writer left `_price`
	 * and `wc_product_meta_lookup` on the OLD price — and saving the product correctly afterwards did
	 * NOT repair it, because WooCommerce saw the meta already changed and registered no change at all.
	 */
	public function test_meta_is_refused_on_a_derived_type_and_title_is_not(): void {
		$this->seed_table( 'strlen' );

		$verdict = Protected_Post_Types::inspect( 'zz_derived' );

		$this->assertSame( Protected_Post_Types::WRITES_INCOMPLETE, $verdict['writes'] );
		$this->assertTrue( $verdict['owner_active'] );
		$this->assertTrue( Protected_Post_Types::assess( 'zz_derived', true )['blocked'], 'A meta write must be refused.' );
		$this->assertFalse( Protected_Post_Types::assess( 'zz_derived', false )['blocked'], 'A title-only write must be allowed.' );
		$this->assertContains( 'ghost/update-thing', $verdict['use_instead'] );
	}

	/**
	 * A type whose records live elsewhere is refused outright, meta or not.
	 */
	public function test_a_discarded_type_is_refused_for_any_write(): void {
		$this->seed_table( 'strlen' );

		$verdict = Protected_Post_Types::inspect( 'zz_external' );

		$this->assertSame( Protected_Post_Types::WRITES_DISCARDED, $verdict['writes'] );
		$this->assertTrue( Protected_Post_Types::assess( 'zz_external', true )['blocked'] );
		$this->assertTrue( Protected_Post_Types::assess( 'zz_external', false )['blocked'], 'A discarded type is refused even without meta.' );
	}

	public function test_an_absent_owner_downgrades_and_explains(): void {
		$this->seed_table( 'zz_definitely_not_a_function' );

		$verdict = Protected_Post_Types::inspect( 'zz_external' );

		$this->assertSame( Protected_Post_Types::WRITES_APPLY, $verdict['writes'] );
		$this->assertFalse( $verdict['owner_active'] );
		$this->assertArrayHasKey( 'note', $verdict, 'An absent owner must say so rather than silently passing.' );
		$this->assertStringContainsString( 'Ghost Commerce', $verdict['note'] );
		$this->assertFalse( Protected_Post_Types::assess( 'zz_external', true )['blocked'] );
	}

	/**
	 * The table is extensible, because this file cannot know every plugin's storage.
	 */
	public function test_the_table_is_filterable(): void {
		$this->assertStringContainsString(
			"apply_filters( 'acrossai_protected_post_types'",
			self::read( 'Utilities/Protected_Post_Types.php' )
		);
	}

	/**
	 * Every writer consults the guard, and honours the override.
	 */
	public function test_every_writer_is_guarded(): void {
		foreach ( self::writers() as $relative ) {
			$code = self::code_only( self::read( $relative ) );

			$this->assertStringContainsString(
				'Protected_Post_Types::assess(',
				$code,
				"{$relative} writes without consulting the guard."
			);
			$this->assertStringContainsString(
				"empty( \$input['allow_protected_post_type'] )",
				$code,
				"{$relative} has no override, so the guard is a wall rather than a door."
			);
			$this->assertStringContainsString(
				'Protected_Post_Types::refusal(',
				$code,
				"{$relative} must refuse in the shared envelope shape."
			);
		}
	}

	/**
	 * The refusal uses the existing envelope key.
	 *
	 * `blocked_reason` is already the convention in Update_Post and Patch_Option_Value. A new key
	 * would mean a caller has to learn two vocabularies for the same idea.
	 */
	public function test_the_refusal_uses_the_existing_envelope_key(): void {
		$this->assertSame( 'protected_post_type', Protected_Post_Types::BLOCKED_REASON );
		$this->assertStringContainsString(
			"'blocked_reason' => self::BLOCKED_REASON",
			self::read( 'Utilities/Protected_Post_Types.php' )
		);
	}

	/**
	 * Every key the refusal returns is declared in each writer's output schema.
	 *
	 * Undeclared keys are rejected by `additionalProperties: false` AFTER the work is done. Measured
	 * twice now: the refusal returned `post_type`, which no writer declared, so the guard fired and
	 * the caller received `ability_invalid_output` instead of the explanation. Same shape as the
	 * send-test-email defect in #119 and BUG-ARRAY-TYPED-OUTPUT-IS-A-JSON-OBJECT before it.
	 */
	public function test_refusal_keys_are_declared_by_every_writer(): void {
		$refusal = Protected_Post_Types::refusal( Protected_Post_Types::inspect( 'post' ) );

		foreach ( self::writers() as $relative ) {
			$src = self::read( $relative );

			/*
			 * Scoped to the OUTPUT schema. A whole-file search passes on `post_type` because it also
			 * appears in the INPUT schema — the substring trap that let the WPCode prefix assertion
			 * certify its own bug, and that hid this exact defect until it was measured live.
			 */
			$from = strpos( $src, "'output_schema'" );
			$this->assertNotFalse( $from, "{$relative} has no output schema." );

			$to     = strpos( $src, "'meta'                =>", $from );
			$schema = substr( $src, $from, false === $to ? null : $to - $from );

			foreach ( array_keys( $refusal ) as $key ) {
				if ( in_array( $key, array( 'success', 'message' ), true ) ) {
					continue;
				}

				$this->assertStringContainsString(
					"'" . $key . "'",
					$schema,
					"{$relative} can return {$key} but does not declare it in its output schema; the response is rejected after the write."
				);
			}
		}
	}

	/**
	 * The detector does not require the plugin it describes.
	 *
	 * It lives in Utilities/, not Utilities/Store/, and must load on a site with no e-commerce at all.
	 */
	public function test_the_detector_stands_alone(): void {
		$code = self::code_only( self::read( 'Utilities/Protected_Post_Types.php' ) );

		$this->assertStringNotContainsString( 'WC_Product', $code );
		$this->assertStringNotContainsString( 'wc_get_product(', $code, 'Probe by name, never by calling it.' );
		$this->assertStringContainsString( 'class_exists(', $code );
		$this->assertStringContainsString( 'function_exists(', $code );
	}

	/**
	 * The read surface exists and is wired.
	 */
	public function test_the_inspect_ability_exists_and_is_wired(): void {
		$src = self::read( 'Content/Inspect_Post_Type.php' );

		$this->assertStringContainsString( "'name' => 'content/inspect-post-type'", $src );
		$this->assertStringContainsString( "'readonly'    => true", $src );

		$bootstrap = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );
		$this->assertStringContainsString( 'new Content\\Inspect_Post_Type();', $bootstrap );
	}
}
