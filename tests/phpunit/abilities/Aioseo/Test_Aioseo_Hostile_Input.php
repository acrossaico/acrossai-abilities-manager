<?php
/**
 * Feature 132 — the five defects an adversarial pass found, pinned so they cannot return.
 *
 * Every one of these got nonsense PAST the first version of the suite and reported success. They are
 * grouped here rather than spread across the per-class tests because they share a cause worth naming:
 * a value whose SHAPE is wrong survives a PHP cast and becomes a plausible-looking wrong answer, and
 * AIOSEO's own sanitiser casts rather than refuses. Validation has to happen before the cast or not
 * at all.
 *
 * Host-free: all five are pure input handling.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.44
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities\Aioseo;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Aioseo\Post_Seo_Repository;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Aioseo\Settings_Registry;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class Test_Aioseo_Hostile_Input extends TestCase {

	/**
	 * @param  string               $id    Field id.
	 * @param  mixed                $value Value.
	 * @param  array<string, mixed> $spec  Field spec entry.
	 * @return mixed
	 */
	private function shape( string $id, $value, array $spec ) {
		$ref = new ReflectionMethod( Settings_Registry::class, 'assert_shape' );
		$ref->setAccessible( true );

		return $ref->invokeArgs( null, array( $id, $value, $spec ) );
	}

	/**
	 * @param  array<string, mixed> $fields Fields.
	 * @return mixed
	 */
	private function shapes( array $fields ) {
		$ref = new ReflectionMethod( Post_Seo_Repository::class, 'assert_shapes' );
		$ref->setAccessible( true );

		return $ref->invokeArgs( null, array( $fields ) );
	}

	/**
	 * @param  mixed $value Value.
	 * @return int|null
	 */
	private function post_id( $value ): ?int {
		$ref = new ReflectionMethod( Post_Seo_Repository::class, 'post_id' );
		$ref->setAccessible( true );

		return $ref->invoke( null, $value );
	}

	/**
	 * FINDING 1 — `linksPerIndex: null` was stored as 0, a sitemap with zero links per index.
	 *
	 * `intval( null )` is 0, so null was a value rather than a cleared field. Null stays legal for a
	 * string and a boolean, where AIOSEO's cast genuinely means "clear" and "off".
	 */
	public function test_null_is_refused_for_a_number_and_allowed_for_text_and_booleans(): void {
		$error = $this->shape( 'linksPerIndex', null, array( 'type' => 'number' ) );
		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'invalid_field_value', $error->get_error_code() );
		$this->assertStringContainsString( '0', $error->get_error_message() );

		$this->assertTrue( $this->shape( 'homepageLabel', null, array( 'type' => 'string' ) ) );
		$this->assertTrue( $this->shape( 'showCurrentItem', null, array( 'type' => 'boolean' ) ) );
		$this->assertTrue( $this->shape( 'misc', null, array( 'type' => 'html' ) ) );
	}

	/**
	 * FINDING 2 — `postTypes.included: [["post"]]` was stored nested.
	 *
	 * `sanitizeField()` recurses into arrays, so the nesting was saved and the sitemap config became
	 * unreadable. Refused where the field's own default is a flat list.
	 */
	public function test_a_nested_member_is_refused_for_a_flat_list_field(): void {
		$spec = array( 'type' => 'array', 'nested' => false );

		foreach ( array( array( array( 'post' ) ), array( new \stdClass() ) ) as $value ) {
			$error = $this->shape( 'postTypes.included', $value, $spec );

			$this->assertInstanceOf( \WP_Error::class, $error );
			$this->assertSame( 'invalid_field_value', $error->get_error_code() );
		}

		$this->assertTrue( $this->shape( 'postTypes.included', array( 'post', 'page' ), $spec ) );
	}

	/**
	 * …and ALLOWED where the default itself is nested. `advanced.emailSummary.recipients` is a list of
	 * records and is the only such field in AIOSEO 5.0.3, which is why the rule asks the declared
	 * default rather than banning nesting outright.
	 */
	public function test_a_nested_member_is_allowed_where_the_default_is_nested(): void {
		$this->assertTrue(
			$this->shape( 'emailSummary.recipients', array( array( 'email' => 'a@b.c' ) ), array( 'type' => 'array', 'nested' => true ) )
		);
	}

	/**
	 * FINDING 3 — writing `emailSummary.recipients` tripped a PHP warning inside AIOSEO.
	 *
	 * `Options::sanitizeEmailSummary()` reads `email` and `frequency` off every entry
	 * unconditionally, so each member is a record with required keys that appear nowhere in the
	 * declared type. The registry's premise is that the declared types ARE the spec, so the one field
	 * where that is false is refused rather than guessed at. It stays readable.
	 */
	public function test_the_undeclarable_field_is_refused_on_write_and_still_readable(): void {
		$error = Settings_Registry::validate( 'advanced', array( 'emailSummary.recipients' => array( array( 'email' => 'a@b.c' ) ) ) );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'field_not_writable', $error->get_error_code() );

		// The panel itself is still writable, and still reports the field.
		$this->assertTrue( Settings_Registry::is_writable( 'advanced' ) );
		$this->assertContains( 'advanced', Settings_Registry::writable_panel_slugs() );
	}

	/**
	 * FINDING 4 — `post_ids: [[1]]` wrote SEO fields to post 1 and reported success.
	 *
	 * PHP casts an array or an object to 1, so any malformed entry resolved to the first post. Only
	 * an integer, or an integer-valued numeric string above zero, is a post id now.
	 */
	public function test_a_malformed_post_id_never_resolves_to_a_real_post(): void {
		foreach ( array( array( 1 ), new \stdClass(), 0, -1, 'abc', '1.5', null, true, false, '  12  ', '0x10', '', '01' ) as $value ) {
			$this->assertNull(
				$this->post_id( $value ),
				'A malformed id resolved to a post: ' . var_export( $value, true )
			);
		}
	}

	public function test_a_real_post_id_still_resolves(): void {
		$this->assertSame( 7, $this->post_id( 7 ) );
		$this->assertSame( 7, $this->post_id( '7' ) );
		$this->assertSame( 12345, $this->post_id( 12345 ) );
	}

	/**
	 * FINDING 5 — `fields: { title: ["a"] }` was accepted and stored as an array.
	 *
	 * AIOSEO's service assigns most fields straight into the save array, so the list was stored and
	 * reported as a success.
	 */
	public function test_a_list_is_refused_where_a_single_value_belongs(): void {
		foreach ( array( 'title', 'description', 'canonical_url', 'focus_keyphrase', 'pillar_content' ) as $field ) {
			$error = $this->shapes( array( $field => array( 'a' ) ) );

			$this->assertInstanceOf( \WP_Error::class, $error, "{$field} accepted a list." );
			$this->assertSame( 'invalid_field_value', $error->get_error_code() );
		}
	}

	/**
	 * …and the converse: the structured fields must not accept a scalar.
	 */
	public function test_a_single_value_is_refused_where_a_list_belongs(): void {
		foreach ( array( 'robots', 'social', 'additional_keyphrases' ) as $field ) {
			$error = $this->shapes( array( $field => 'x' ) );

			$this->assertInstanceOf( \WP_Error::class, $error, "{$field} accepted a scalar." );
			$this->assertSame( 'invalid_field_value', $error->get_error_code() );
		}
	}

	/**
	 * Shape validation must reject the WHOLE call, not write the good fields and skip the bad one.
	 * A partially-applied batch with a success report is the worst available outcome.
	 */
	public function test_one_bad_field_rejects_the_whole_call(): void {
		$error = $this->shapes( array( 'description' => 'fine', 'title' => array( 'bad' ) ) );

		$this->assertInstanceOf( \WP_Error::class, $error );
	}

	/**
	 * Null still clears a text field — the legitimate use the shape rule must not break.
	 */
	public function test_null_still_clears_a_text_field(): void {
		$this->assertTrue( $this->shapes( array( 'title' => null, 'description' => null ) ) );
	}
}
