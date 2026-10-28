<?php
/**
 * Feature 132 — the panel registry: validation, and the leaf rule the whole spec rests on.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.44
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities\Aioseo;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Aioseo\Settings_Registry;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class Test_Aioseo_Settings_Registry extends TestCase {

	/**
	 * Flatten a defaults/values pair through the private helper.
	 *
	 * @param  array<string, mixed> $defaults Defaults.
	 * @param  array<string, mixed> $values   Values.
	 * @return array<string, array<string, mixed>>
	 */
	private function flatten( array $defaults, array $values ): array {
		$ref = new ReflectionMethod( Settings_Registry::class, 'flatten' );
		$ref->setAccessible( true );

		return $ref->invokeArgs( null, array( $defaults, $values, '' ) );
	}

	public function test_every_panel_declares_a_path_capability_and_label(): void {
		$panels = Settings_Registry::panels();

		$this->assertNotEmpty( $panels );

		foreach ( $panels as $slug => $panel ) {
			$this->assertMatchesRegularExpression( '/^[a-z0-9-]+$/', $slug, "Panel slug {$slug} must be kebab-case." );
			$this->assertNotEmpty( $panel['path'], "Panel {$slug} must declare a path." );
			$this->assertMatchesRegularExpression( '/^aioseo_[a-z_]+$/', $panel['capability'], "Panel {$slug} must name an AIOSEO capability." );
			$this->assertNotSame( '', (string) $panel['label'], "Panel {$slug} must carry a label." );
		}
	}

	/**
	 * `internal` and `deprecated` are AIOSEO's own bookkeeping and its compatibility shadow. Exposing
	 * either as a panel would invite a caller to write state AIOSEO maintains, or settings that change
	 * nothing a visitor sees.
	 */
	public function test_no_panel_reaches_an_excluded_group(): void {
		foreach ( Settings_Registry::panels() as $slug => $panel ) {
			$this->assertNotContains(
				$panel['path'][0],
				Settings_Registry::excluded_groups(),
				"Panel {$slug} reaches the excluded group {$panel['path'][0]}."
			);
		}
	}

	/**
	 * robots.txt is readable and not writable, because AIOSEO's own rules-add / rules-update /
	 * rules-delete manage those rules by an id this panel's raw arrays do not expose.
	 */
	public function test_the_robots_panel_is_read_only_and_everything_else_is_writable(): void {
		$this->assertFalse( Settings_Registry::is_writable( 'tools-robots' ) );
		$this->assertNotContains( 'tools-robots', Settings_Registry::writable_panel_slugs() );

		foreach ( Settings_Registry::panel_slugs() as $slug ) {
			if ( 'tools-robots' === $slug ) {
				continue;
			}

			$this->assertTrue( Settings_Registry::is_writable( $slug ), "Panel {$slug} should be writable." );
		}
	}

	public function test_an_unknown_panel_is_refused_and_lists_the_valid_ones(): void {
		$error = Settings_Registry::resolve( 'nope' );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'unknown_panel', $error->get_error_code() );
		$this->assertStringContainsString( 'breadcrumbs', $error->get_error_message() );
	}

	public function test_writing_the_read_only_panel_names_aioseos_own_abilities(): void {
		$error = Settings_Registry::validate( 'tools-robots', array( 'rules' => array() ) );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'panel_not_writable', $error->get_error_code() );
		$this->assertStringContainsString( 'aioseo-robots/rules-add', $error->get_error_message() );
	}

	/**
	 * THE leaf rule. A node is a leaf when it carries a STRING `type`. The string test is what makes
	 * it safe for a group that legitimately holds a child field named `type` — that child is an
	 * array, so it reads as a group member rather than as its parent's type marker. Get this wrong
	 * and a whole group collapses into one bogus field.
	 */
	public function test_a_group_holding_a_field_named_type_is_not_mistaken_for_a_leaf(): void {
		$fields = $this->flatten(
			array(
				'schema' => array(
					'type'  => array( 'type' => 'string', 'default' => 'Organization' ),
					'phone' => array( 'type' => 'string', 'default' => '' ),
				),
			),
			array( 'schema' => array( 'type' => 'Person', 'phone' => '123' ) )
		);

		$this->assertSame( array( 'schema.type', 'schema.phone' ), array_keys( $fields ) );
		$this->assertSame( 'string', $fields['schema.type']['type'] );
		$this->assertSame( 'Person', $fields['schema.type']['current'] );
		$this->assertSame( '123', $fields['schema.phone']['current'] );
	}

	public function test_a_leaf_with_no_stored_value_falls_back_to_its_default(): void {
		$fields = $this->flatten(
			array( 'linksPerIndex' => array( 'type' => 'number', 'default' => 1000 ) ),
			array()
		);

		$this->assertSame( 1000, $fields['linksPerIndex']['current'] );
		$this->assertSame( 1000, $fields['linksPerIndex']['default'] );
	}

	public function test_modifiers_are_reported(): void {
		$fields = $this->flatten(
			array(
				'separator' => array( 'type' => 'string', 'default' => '&raquo;', 'preserveHtml' => true ),
				'prefix'    => array( 'type' => 'string', 'localized' => true, 'default' => '' ),
			),
			array()
		);

		$this->assertTrue( $fields['separator']['preserve_html'] );
		$this->assertFalse( $fields['separator']['localized'] );
		$this->assertTrue( $fields['prefix']['localized'] );
		$this->assertFalse( $fields['prefix']['preserve_html'] );
	}
}
