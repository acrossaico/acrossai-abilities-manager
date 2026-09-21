<?php
/**
 * Tests: composing Contact Form 7 form tags, and listing the types available.
 *
 * Both of these were found by driving the suite over MCP against a live site
 * rather than by reading the code, and both are the same shape of fault: the
 * ability reported success while handing back something Contact Form 7 reads
 * differently from how the caller wrote it.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\ContactForm7\Form_Tag_Repository;
use WP_UnitTestCase;

/**
 * Tag composition and the field-type listing.
 */
class Test_Contact_Form_7_Tag_Composition extends WP_UnitTestCase {

	/**
	 * Reset the stub registry between tests.
	 */
	public function tear_down(): void {
		unset( $GLOBALS['acrossai_test_cf7_tag_types'], $GLOBALS['acrossai_test_cf7_tag_features'] );
		parent::tear_down();
	}

	/* ----------------------------------------------------------------- *
	 * Options.
	 * ----------------------------------------------------------------- */

	/**
	 * An option whose value contains a space is quoted, so CF7 reads it as one.
	 *
	 * `placeholder:+44 7700 900000` used to arrive as THREE options and the
	 * field ended up with a placeholder of `+44`. Values were already quoted
	 * here; options were not, so one ability spoke two conventions.
	 *
	 * @dataProvider provide_options
	 * @param string $option   Option as supplied.
	 * @param string $expected Normalised option.
	 */
	public function test_an_option_is_normalised_for_contact_form_7( string $option, string $expected ): void {
		$this->assertSame( $expected, Form_Tag_Repository::normalise_option( $option ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provide_options(): array {
		return array(
			'no whitespace is untouched'   => array( 'class:wide', 'class:wide' ),
			'bare option is untouched'     => array( 'readonly', 'readonly' ),
			'spaced value gets quoted'     => array( 'placeholder:+44 7700 900000', 'placeholder:"+44 7700 900000"' ),
			'already quoted is not doubled' => array( 'placeholder:"a b"', 'placeholder:"a b"' ),
			'surrounding space is trimmed' => array( '  class:wide  ', 'class:wide' ),
			'empty stays empty'            => array( '   ', '' ),
		);
	}

	/**
	 * An option with spaces and no `key:` has no value half to quote.
	 *
	 * Nothing can be done with it, so the write abilities refuse rather than
	 * storing a tag that reads back as several options.
	 */
	public function test_an_unquotable_option_is_reported(): void {
		$this->assertSame(
			array( 'two words' ),
			Form_Tag_Repository::invalid_options( array( 'class:wide', 'two words', 'placeholder:a b', '' ) )
		);
	}

	/**
	 * Nothing is reported when every option can be written.
	 */
	public function test_writable_options_are_not_reported(): void {
		$this->assertSame(
			array(),
			Form_Tag_Repository::invalid_options( array( 'class:wide', 'placeholder:a b', 'readonly' ) )
		);
	}

	/**
	 * The composed tag survives a round trip through the option rules.
	 */
	public function test_compose_tag_quotes_options_and_values_alike(): void {
		$this->assertSame(
			'[tel* phone placeholder:"+44 7700 900000" class:wide]',
			Form_Tag_Repository::compose_tag(
				'tel',
				'phone',
				true,
				array( 'placeholder:+44 7700 900000', 'class:wide' ),
				array()
			)
		);
	}

	/* ----------------------------------------------------------------- *
	 * Field types.
	 * ----------------------------------------------------------------- */

	/**
	 * One row per base type, and a required form only where CF7 has one.
	 *
	 * CF7 registers `text` and `text*` as separate tag types. Appending an
	 * asterisk to whatever came back produced a duplicate row AND the string
	 * `text**`, which is not CF7 syntax — so the listing told a caller to write
	 * something that cannot work.
	 */
	public function test_field_types_collapse_to_base_types(): void {
		$GLOBALS['acrossai_test_cf7_tag_types']    = array( 'text', 'text*', 'submit', 'email', 'email*' );
		$GLOBALS['acrossai_test_cf7_tag_features'] = array(
			'name-attr'    => array( 'text', 'email' ),
			'not-for-mail' => array(),
		);

		$types = Form_Tag_Repository::types();

		$this->assertSame( array( 'email', 'submit', 'text' ), array_column( $types, 'type' ) );
		$this->assertSame(
			array( 'email*', null, 'text*' ),
			array_column( $types, 'required_form' ),
			'submit has no required variant, so it must not claim one.'
		);
	}

	/**
	 * No listed required form ever carries two asterisks.
	 */
	public function test_no_required_form_is_double_starred(): void {
		$GLOBALS['acrossai_test_cf7_tag_types']    = array( 'text', 'text*', 'checkbox', 'checkbox*' );
		$GLOBALS['acrossai_test_cf7_tag_features'] = array();

		foreach ( Form_Tag_Repository::types() as $type ) {
			$this->assertNotSame( '**', substr( (string) $type['required_form'], -2 ) );
		}
	}

	/**
	 * The per-type flags are read off the base type, not the starred one.
	 */
	public function test_flags_are_resolved_from_the_base_type(): void {
		$GLOBALS['acrossai_test_cf7_tag_types']    = array( 'captchac', 'text', 'text*' );
		$GLOBALS['acrossai_test_cf7_tag_features'] = array(
			'name-attr'    => array( 'captchac', 'text' ),
			'not-for-mail' => array( 'captchac' ),
		);

		$types = array_column( Form_Tag_Repository::types(), null, 'type' );

		$this->assertTrue( $types['captchac']['accepts_name'] );
		$this->assertFalse( $types['captchac']['in_mail'] );
		$this->assertTrue( $types['text']['in_mail'] );
	}
}
