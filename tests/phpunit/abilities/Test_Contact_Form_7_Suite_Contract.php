<?php
/**
 * Feature 103 — the whole-suite contract.
 *
 * Verifies the 25 abilities as a SET rather than individually: the complete slug list, the
 * sub-group split, the capability map and the destructive set. A per-ability test cannot catch a
 * missing ability, a duplicated slug, or a capability that drifted from the documented map.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.35
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Contact_Form_7_Suite_Contract extends WP_UnitTestCase {

	/**
	 * Every ability's class => slug. This is the feature's inventory: adding an ability without
	 * updating it fails, which is the point.
	 *
	 * @return array<string,string>
	 */
	private static function inventory(): array {
		return array(
			// Forms (8).
			'List_Forms'                 => 'list-forms',
			'Get_Form'                   => 'get-form',
			'Find_Form'                  => 'find-form',
			'Get_Form_Shortcode'         => 'get-form-shortcode',
			'Create_Form'                => 'create-form',
			'Update_Form'                => 'update-form',
			'Duplicate_Form'             => 'duplicate-form',
			'Delete_Form'                => 'delete-form',
			// Fields & template (7).
			'List_Form_Fields'           => 'list-form-fields',
			'List_Field_Types'           => 'list-field-types',
			'Get_Form_Template'          => 'get-form-template',
			'Update_Form_Template'       => 'update-form-template',
			'Add_Form_Field'             => 'add-form-field',
			'Update_Form_Field'          => 'update-form-field',
			'Remove_Form_Field'          => 'remove-form-field',
			// Mail (5).
			'Get_Mail'                   => 'get-mail',
			'Update_Mail'                => 'update-mail',
			'Toggle_Mail_2'              => 'toggle-mail-2',
			'List_Mail_Tags'             => 'list-mail-tags',
			'Validate_Mail_Tags'         => 'validate-mail-tags',
			// Messages (2).
			'Get_Messages'               => 'get-messages',
			'Update_Messages'            => 'update-messages',
			// Settings & validation (3).
			'Get_Additional_Settings'    => 'get-additional-settings',
			'Update_Additional_Settings' => 'update-additional-settings',
			'Validate_Form_Config'       => 'validate-form-config',
		);
	}

	/**
	 * Class => sub-group. The five cards the admin renders.
	 *
	 * @return array<string,string>
	 */
	private static function sub_groups(): array {
		return array(
			'List_Forms'                 => 'cf7-forms',
			'Get_Form'                   => 'cf7-forms',
			'Find_Form'                  => 'cf7-forms',
			'Get_Form_Shortcode'         => 'cf7-forms',
			'Create_Form'                => 'cf7-forms',
			'Update_Form'                => 'cf7-forms',
			'Duplicate_Form'             => 'cf7-forms',
			'Delete_Form'                => 'cf7-forms',
			'List_Form_Fields'           => 'cf7-fields',
			'List_Field_Types'           => 'cf7-fields',
			'Get_Form_Template'          => 'cf7-fields',
			'Update_Form_Template'       => 'cf7-fields',
			'Add_Form_Field'             => 'cf7-fields',
			'Update_Form_Field'          => 'cf7-fields',
			'Remove_Form_Field'          => 'cf7-fields',
			'Get_Mail'                   => 'cf7-mail',
			'Update_Mail'                => 'cf7-mail',
			'Toggle_Mail_2'              => 'cf7-mail',
			'List_Mail_Tags'             => 'cf7-mail',
			'Validate_Mail_Tags'         => 'cf7-mail',
			'Get_Messages'               => 'cf7-messages',
			'Update_Messages'            => 'cf7-messages',
			'Get_Additional_Settings'    => 'cf7-settings',
			'Update_Additional_Settings' => 'cf7-settings',
			'Validate_Form_Config'       => 'cf7-settings',
		);
	}

	/**
	 * Class => CF7 capability suffix, composed on top of the manage_options floor.
	 *
	 * The suffixes are CF7's own (`includes/capabilities.php`), never the primitives they map onto:
	 * CF7 maps them to `publish_pages`/`edit_posts`, and both the map and the mapping are
	 * `define`-overridable, so checking the primitive would silently widen access.
	 *
	 * @return array<string,string>
	 */
	private static function capability_map(): array {
		return array(
			// Reads.
			'List_Forms'                 => 'read_contact_forms',
			'Get_Form'                   => 'read_contact_forms',
			'Find_Form'                  => 'read_contact_forms',
			'Get_Form_Shortcode'         => 'read_contact_forms',
			'List_Form_Fields'           => 'read_contact_forms',
			'List_Field_Types'           => 'read_contact_forms',
			'Get_Form_Template'          => 'read_contact_forms',
			'Get_Mail'                   => 'read_contact_forms',
			'List_Mail_Tags'             => 'read_contact_forms',
			'Validate_Mail_Tags'         => 'read_contact_forms',
			'Get_Messages'               => 'read_contact_forms',
			'Get_Additional_Settings'    => 'read_contact_forms',
			'Validate_Form_Config'       => 'read_contact_forms',
			// Writes.
			'Create_Form'                => 'edit_contact_forms',
			'Update_Form'                => 'edit_contact_forms',
			'Duplicate_Form'             => 'edit_contact_forms',
			'Update_Form_Template'       => 'edit_contact_forms',
			'Add_Form_Field'             => 'edit_contact_forms',
			'Update_Form_Field'          => 'edit_contact_forms',
			'Remove_Form_Field'          => 'edit_contact_forms',
			'Update_Mail'                => 'edit_contact_forms',
			'Toggle_Mail_2'              => 'edit_contact_forms',
			'Update_Messages'            => 'edit_contact_forms',
			'Update_Additional_Settings' => 'edit_contact_forms',
			// Deletes have their own capability.
			'Delete_Form'                => 'delete_contact_form',
		);
	}

	/**
	 * Abilities that must declare destructive:true and confirm-gate unconditionally.
	 *
	 * Update_Mail and Update_Additional_Settings are deliberately absent: they confirm
	 * conditionally, only when the change redirects submissions or stops the form delivering mail.
	 *
	 * @return string[]
	 */
	private static function destructive(): array {
		return array( 'Delete_Form', 'Remove_Form_Field' );
	}

	private static function src( string $class ): string {
		return (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/ContactForm7/' . $class . '.php'
		);
	}

	public function test_suite_has_exactly_twenty_five_abilities(): void {
		$this->assertCount( 25, self::inventory() );
	}

	/**
	 * The inventory and the filesystem must agree in both directions, so neither an unlisted file
	 * nor a listed-but-missing file can slip through.
	 */
	public function test_inventory_matches_the_filesystem(): void {
		$files = glob( dirname( __DIR__, 3 ) . '/includes/Abilities/ContactForm7/*.php' );
		$found = array_values(
			array_filter(
				array_map(
					static fn( string $f ): string => basename( $f, '.php' ),
					array_map( 'strval', (array) $files )
				),
				static fn( string $c ): bool => 'Category_Registrar' !== $c && 'Base_Contact_Form_7_Ability' !== $c
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
	 * DEC-SLUG-CONVENTION-VERB-FIRST — verb-first kebab-case, no namespace in the slug (the base
	 * prefixes `contact-form-7/`).
	 */
	public function test_slugs_are_verb_first_kebab_case(): void {
		$verbs = array( 'list', 'get', 'find', 'create', 'update', 'duplicate', 'delete', 'add', 'remove', 'toggle', 'validate' );

		foreach ( self::inventory() as $class => $slug ) {
			$this->assertMatchesRegularExpression( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug, "{$class}: '{$slug}' is not kebab-case." );
			$this->assertStringNotContainsString( 'contact-form-7', $slug, "{$class}: the slug must not repeat the namespace." );
			$this->assertContains( explode( '-', $slug )[0], $verbs, "{$class}: '{$slug}' does not start with a known verb." );
		}
	}

	public function test_every_class_declares_its_slug(): void {
		foreach ( self::inventory() as $class => $slug ) {
			$this->assertStringContainsString( "return '{$slug}';", self::src( $class ), "{$class} must return '{$slug}' from slug()." );
		}
	}

	public function test_every_class_declares_its_sub_group(): void {
		foreach ( self::sub_groups() as $class => $sub_group ) {
			$this->assertStringContainsString( "return '{$sub_group}';", self::src( $class ), "{$class} must return '{$sub_group}' from sub_group()." );
		}
	}

	/**
	 * The sub-group split is the shape the brief committed to and the admin renders.
	 */
	public function test_the_sub_group_split_is_eight_seven_five_two_three(): void {
		$counts = array_count_values( array_values( self::sub_groups() ) );
		ksort( $counts );

		$this->assertSame(
			array(
				'cf7-fields'   => 7,
				'cf7-forms'    => 8,
				'cf7-mail'     => 5,
				'cf7-messages' => 2,
				'cf7-settings' => 3,
			),
			$counts
		);
	}

	/**
	 * Every sub-group the abilities use must have a label in the base, or the admin renders a bare
	 * key.
	 */
	public function test_every_sub_group_has_a_label(): void {
		$base = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/ContactForm7/Base_Contact_Form_7_Ability.php'
		);

		foreach ( array_unique( array_values( self::sub_groups() ) ) as $sub_group ) {
			$this->assertMatchesRegularExpression(
				"/'{$sub_group}'\s*=>\s*__\(/",
				$base,
				"Base_Contact_Form_7_Ability::sub_group_labels() has no label for '{$sub_group}'."
			);
		}
	}

	/**
	 * Compared as sets, not sequences: the map is ordered reads-then-writes for readability, which
	 * is a different order from the inventory's grouping by card.
	 */
	public function test_the_capability_map_covers_every_ability(): void {
		$inventory = array_keys( self::inventory() );
		$mapped    = array_keys( self::capability_map() );
		sort( $inventory );
		sort( $mapped );

		$this->assertSame( $inventory, $mapped );
	}

	public function test_every_class_declares_its_capability(): void {
		foreach ( self::capability_map() as $class => $cap ) {
			$this->assertStringContainsString( "return '{$cap}';", self::src( $class ), "{$class} must return '{$cap}' from cf7_cap()." );
		}
	}

	/**
	 * The CF7 capability suffixes must be CF7's own, never a WordPress primitive. A suffix like
	 * `edit_posts` would compose into `wpcf7_edit_posts`, which nobody holds — the ability would
	 * simply never run — or, worse, a primitive checked directly would widen access.
	 */
	public function test_capabilities_are_contact_form_7_capabilities(): void {
		$known = array( 'read_contact_forms', 'edit_contact_forms', 'edit_contact_form', 'delete_contact_form', 'delete_contact_forms', 'manage_integration', 'submit_forms' );

		foreach ( self::capability_map() as $class => $cap ) {
			$this->assertContains( $cap, $known, "{$class} declares '{$cap}', which is not a Contact Form 7 capability suffix." );
		}
	}

	/**
	 * Readers read and writers write. The capability half of the annotation must agree with the
	 * readonly half, or a client trusts the wrong one.
	 */
	public function test_readonly_matches_the_read_capability(): void {
		foreach ( self::capability_map() as $class => $cap ) {
			$src      = self::src( $class );
			$readonly = (bool) preg_match( "/'readonly'\s*=>\s*true/", $src );

			$this->assertSame(
				'read_contact_forms' === $cap,
				$readonly,
				"{$class}: cf7_cap()='{$cap}' but readonly=" . var_export( $readonly, true ) . '.'
			);
		}
	}

	public function test_the_destructive_set_is_exactly_delete_form_and_remove_form_field(): void {
		$found = array();

		foreach ( array_keys( self::inventory() ) as $class ) {
			if ( preg_match( "/'destructive'\s*=>\s*true/", self::src( $class ) ) ) {
				$found[] = $class;
			}
		}

		sort( $found );
		$expected = self::destructive();
		sort( $expected );

		$this->assertSame( $expected, $found );
	}

	/**
	 * The conditional confirmers are not annotated destructive, but they must still carry a
	 * `confirm` input — otherwise the gate has no flag to read and the caller can never proceed.
	 */
	public function test_the_conditional_confirmers_expose_a_confirm_input(): void {
		foreach ( array( 'Update_Mail', 'Update_Additional_Settings' ) as $class ) {
			$src = self::src( $class );

			$this->assertMatchesRegularExpression( "/'confirm'\s*=>\s*array\(/", $src, "{$class} must expose a confirm input." );
			$this->assertStringContainsString( "'confirmation_required'", $src, "{$class} must be able to return confirmation_required." );
		}
	}

	/**
	 * The group key is load-bearing: it decides the tab, the counts, the REST filter, the Toolset
	 * column AND which MCP dispatcher tool exposes the ability. The base is the only place that
	 * sets it, and it must equal what the integration declares.
	 */
	public function test_the_tab_group_matches_the_integration_declaration(): void {
		$base        = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/ContactForm7/Base_Contact_Form_7_Ability.php' );
		$integration = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/Contact_Form_7.php' );

		$this->assertMatchesRegularExpression( "/const TAB_GROUP = 'contact-form-7';/", $base );
		$this->assertMatchesRegularExpression( "/const TAB_GROUP = 'contact-form-7';/", $integration );
	}
}
