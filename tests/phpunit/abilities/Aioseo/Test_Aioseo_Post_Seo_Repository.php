<?php
/**
 * Feature 132 — the robots reconciliation, which is a silent-no-op guard rather than a nicety.
 *
 * A post ignores every per-post robots directive while its `robots_default` flag is on:
 * `Meta\Robots.php:211` returns the post type's global values and never reads the per-post columns.
 * AIOSEO's service maps `use_default` but never clears it, so `robots: { noindex: true }` stores the
 * flag, reports success, and changes nothing a crawler sees. Confirmed on AIOSEO 5.0.3 before this
 * guard existed: both columns true after the write.
 *
 * Unit-level and host-free — the reconciliation is pure array work, and the one thing worth guarding
 * should not depend on AIOSEO being installed.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.44
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities\Aioseo;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Aioseo\Post_Seo_Repository;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class Test_Aioseo_Post_Seo_Repository extends TestCase {

	/**
	 * @param  array<string, mixed> $fields Caller fields.
	 * @return mixed
	 */
	private function reconcile( array $fields ) {
		$ref = new ReflectionMethod( Post_Seo_Repository::class, 'reconcile_robots' );
		$ref->setAccessible( true );

		return $ref->invokeArgs( null, array( $fields ) );
	}

	/**
	 * THE guard. A directive passed without use_default must turn the switch off, or it is inert.
	 */
	public function test_a_robots_directive_turns_the_use_default_switch_off(): void {
		$out = $this->reconcile( array( 'robots' => array( 'noindex' => true ) ) );

		$this->assertFalse( $out['fields']['robots']['use_default'] );
		$this->assertCount( 1, $out['notes'] );
		$this->assertStringContainsString( 'use default', $out['notes'][0] );
	}

	/**
	 * Doing it silently would be the harder thing to debug, so the note is part of the contract.
	 */
	public function test_the_override_is_reported_rather_than_silent(): void {
		$out = $this->reconcile( array( 'robots' => array( 'nofollow' => true ) ) );

		$this->assertNotEmpty( $out['notes'] );
	}

	/**
	 * An explicit use_default:false is left alone and needs no note.
	 */
	public function test_an_explicit_false_is_untouched_and_unannotated(): void {
		$out = $this->reconcile( array( 'robots' => array( 'noindex' => true, 'use_default' => false ) ) );

		$this->assertFalse( $out['fields']['robots']['use_default'] );
		$this->assertSame( array(), $out['notes'] );
	}

	/**
	 * An explicit use_default:true is HONOURED, with the contradiction reported. Overriding an
	 * explicit instruction would be worse than naming the conflict.
	 */
	public function test_an_explicit_true_is_honoured_with_the_conflict_named(): void {
		$out = $this->reconcile( array( 'robots' => array( 'noindex' => true, 'use_default' => true ) ) );

		$this->assertTrue( $out['fields']['robots']['use_default'] );
		$this->assertCount( 1, $out['notes'] );
		$this->assertStringContainsString( 'ignore', $out['notes'][0] );
	}

	/**
	 * use_default on its own is a legitimate "go back to the defaults" write and must not be altered.
	 */
	public function test_use_default_alone_is_a_valid_write(): void {
		$out = $this->reconcile( array( 'robots' => array( 'use_default' => true ) ) );

		$this->assertTrue( $out['fields']['robots']['use_default'] );
		$this->assertSame( array(), $out['notes'] );
	}

	/**
	 * A directive AIOSEO's service does not map is refused, because it would report success and
	 * store nothing. maxSnippet is the realistic case — it exists in AIOSEO, just not on this path.
	 */
	public function test_a_directive_aioseo_does_not_store_is_refused(): void {
		$error = $this->reconcile( array( 'robots' => array( 'maxSnippet' => 20 ) ) );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'unknown_field', $error->get_error_code() );
		$this->assertStringContainsString( 'maxSnippet', $error->get_error_message() );
	}

	public function test_robots_must_be_an_object(): void {
		$error = $this->reconcile( array( 'robots' => 'noindex' ) );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'invalid_field_value', $error->get_error_code() );
	}

	public function test_fields_without_robots_pass_through_untouched(): void {
		$out = $this->reconcile( array( 'title' => 'x' ) );

		$this->assertSame( array( 'title' => 'x' ), $out['fields'] );
		$this->assertSame( array(), $out['notes'] );
	}

	/**
	 * The accepted field list is the one AIOSEO's service actually maps.
	 */
	public function test_the_accepted_field_list_matches_aioseos_service(): void {
		$this->assertSame(
			array( 'title', 'description', 'canonical_url', 'pillar_content', 'focus_keyphrase', 'additional_keyphrases', 'robots', 'social' ),
			Post_Seo_Repository::fields()
		);
	}
}
