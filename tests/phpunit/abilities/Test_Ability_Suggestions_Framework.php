<?php
/**
 * Feature 095 — framework coverage for the suggested_abilities() authoring
 * surface.
 *
 * Combines:
 *   - Framework-level structural checks against Ability_Definition (method
 *     exists, protected, default return, push_definition injection behaviour
 *     for both empty and non-empty overrides).
 *   - Source-inspection sweep over the four initial-batch abilities
 *     asserting each declares non-empty slug+reason for every entry.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.34
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use ReflectionClass;
use WP_UnitTestCase;

/**
 * Concrete Ability_Definition subclass with NO suggested_abilities override.
 * Used to prove the empty-default silent behaviour.
 */
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
class Fixture_Ability_No_Suggestions extends Ability_Definition {
	protected function ability(): array {
		return array(
			'name' => 'fixture/no-suggestions',
			'args' => array(
				'label'       => 'Fixture No Suggestions',
				'description' => 'Fixture used to prove empty default.',
				'category'    => 'acrossai-abilities-manager-fixtures',
			),
		);
	}
}

/**
 * Fixture that overrides suggested_abilities() with two entries.
 */
class Fixture_Ability_With_Suggestions extends Ability_Definition {
	protected function ability(): array {
		return array(
			'name' => 'fixture/with-suggestions',
			'args' => array(
				'label'       => 'Fixture With Suggestions',
				'description' => 'Fixture used to prove injection + order preservation.',
				'category'    => 'acrossai-abilities-manager-fixtures',
			),
		);
	}
	protected function suggested_abilities(): array {
		return array(
			array(
				'slug'   => 'blocks/outline-post-blocks',
				'reason' => 'Primary — outline first.',
				'saves'  => '~29K tokens on a 97 KB page',
			),
			array(
				'slug'   => 'blocks/update-post-block',
				'reason' => 'Secondary — surgical write after outline.',
			),
		);
	}
}

/**
 * Fixture that overrides but returns an empty array — must be treated as
 * "no suggestions" (spec Assumption; SC-004 guarantee).
 */
class Fixture_Ability_Empty_Override extends Ability_Definition {
	protected function ability(): array {
		return array(
			'name' => 'fixture/empty-override',
			'args' => array(
				'label'       => 'Fixture Empty Override',
				'description' => 'Fixture used to prove empty override is byte-identical to no override.',
				'category'    => 'acrossai-abilities-manager-fixtures',
			),
		);
	}
	protected function suggested_abilities(): array {
		return array();
	}
}
// phpcs:enable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Class Test_Ability_Suggestions_Framework.
 */
class Test_Ability_Suggestions_Framework extends WP_UnitTestCase {

	private string $plugin_root = '';

	protected function setUp(): void {
		parent::setUp();
		$this->plugin_root = dirname( __DIR__, 3 );
	}

	// -----------------------------------------------------------------------
	// Framework surface — method signature, protection, default return.
	// -----------------------------------------------------------------------

	public function test_suggested_abilities_method_exists_and_is_protected(): void {
		$reflection = new ReflectionClass( Ability_Definition::class );
		$this->assertTrue(
			$reflection->hasMethod( 'suggested_abilities' ),
			'Ability_Definition must declare suggested_abilities()'
		);
		$method = $reflection->getMethod( 'suggested_abilities' );
		$this->assertTrue(
			$method->isProtected(),
			'suggested_abilities() must be protected — matches suggested_plugins() convention'
		);
		$this->assertSame(
			'array',
			(string) $method->getReturnType(),
			'suggested_abilities() must declare array return type'
		);
	}

	public function test_default_return_is_empty_array(): void {
		$ability    = new Fixture_Ability_No_Suggestions();
		$reflection = new ReflectionClass( Ability_Definition::class );
		$method     = $reflection->getMethod( 'suggested_abilities' );
		$method->setAccessible( true );
		$this->assertSame( array(), $method->invoke( $ability ) );
	}

	// -----------------------------------------------------------------------
	// push_definition() injection — with non-empty override.
	// -----------------------------------------------------------------------

	public function test_push_definition_injects_declared_suggestions_into_meta(): void {
		$ability     = new Fixture_Ability_With_Suggestions();
		$definitions = $ability->push_definition( array() );

		$this->assertCount( 1, $definitions );
		$row = $definitions[0];
		$this->assertArrayHasKey( 'args', $row );
		$this->assertArrayHasKey( 'meta', $row['args'] );
		$this->assertArrayHasKey( 'acrossai', $row['args']['meta'] );
		$this->assertArrayHasKey(
			'suggested_abilities',
			$row['args']['meta']['acrossai'],
			'Non-empty suggested_abilities() must inject into meta.acrossai.suggested_abilities'
		);

		$injected = $row['args']['meta']['acrossai']['suggested_abilities'];
		$this->assertCount( 2, $injected );
		$this->assertSame( 'blocks/outline-post-blocks', $injected[0]['slug'], 'Order preserved: primary entry first' );
		$this->assertSame( 'blocks/update-post-block', $injected[1]['slug'], 'Order preserved: secondary entry second' );
		$this->assertSame( 'Primary — outline first.', $injected[0]['reason'] );
		$this->assertSame( '~29K tokens on a 97 KB page', $injected[0]['saves'], 'Optional saves field passed through verbatim' );
		$this->assertArrayNotHasKey( 'saves', $injected[1], 'saves is optional per entry' );
	}

	public function test_injection_uses_array_values_to_preserve_list_shape(): void {
		$ability     = new Fixture_Ability_With_Suggestions();
		$definitions = $ability->push_definition( array() );
		$injected    = $definitions[0]['args']['meta']['acrossai']['suggested_abilities'];

		$this->assertTrue(
			array_is_list( $injected ),
			'Injected suggested_abilities must be a list (numeric keys 0..N-1) so JSON serialization is stable'
		);
	}

	// -----------------------------------------------------------------------
	// push_definition() silent default — no override + empty override.
	// -----------------------------------------------------------------------

	public function test_no_override_omits_suggested_abilities_key_entirely(): void {
		$ability     = new Fixture_Ability_No_Suggestions();
		$definitions = $ability->push_definition( array() );
		$row         = $definitions[0];

		if ( isset( $row['args']['meta']['acrossai'] ) ) {
			$this->assertArrayNotHasKey(
				'suggested_abilities',
				$row['args']['meta']['acrossai'],
				'An ability with no override must produce NO suggested_abilities key at all — not an empty list'
			);
		} else {
			$this->addToAssertionCount( 1 );
		}
	}

	public function test_empty_override_return_omits_suggested_abilities_key(): void {
		$ability     = new Fixture_Ability_Empty_Override();
		$definitions = $ability->push_definition( array() );
		$row         = $definitions[0];

		if ( isset( $row['args']['meta']['acrossai'] ) ) {
			$this->assertArrayNotHasKey(
				'suggested_abilities',
				$row['args']['meta']['acrossai'],
				'Empty override return must be identical to no-override — no phantom empty list'
			);
		} else {
			$this->addToAssertionCount( 1 );
		}
	}

	// -----------------------------------------------------------------------
	// Description untouched by the injection (Decision 2 in research.md).
	// -----------------------------------------------------------------------

	public function test_description_is_not_mutated_by_injection(): void {
		$ability     = new Fixture_Ability_With_Suggestions();
		$definitions = $ability->push_definition( array() );
		$this->assertSame(
			'Fixture used to prove injection + order preservation.',
			$definitions[0]['args']['description'],
			'push_definition() MUST NOT append or otherwise mutate args.description — meta-only surfacing'
		);
	}

	// -----------------------------------------------------------------------
	// Initial-batch source-inspection sweep — each override is well-formed.
	// -----------------------------------------------------------------------

	/**
	 * @return array<string, array{0:string,1:string}> Relative path + ability slug for messages.
	 */
	public static function initial_batch_provider(): array {
		return array(
			'update-page'     => array( 'includes/Abilities/Content/Update_Page.php', 'content/update-page' ),
			'update-post'     => array( 'includes/Abilities/Content/Update_Post.php', 'content/update-post' ),
			'update-cpt-item' => array( 'includes/Abilities/Content/Update_Cpt_Item.php', 'content/update-cpt-item' ),
			'get-post-blocks' => array( 'includes/Abilities/Content/Get_Post_Blocks.php', 'blocks/get-post-blocks' ),
		);
	}

	/**
	 * @dataProvider initial_batch_provider
	 */
	public function test_initial_batch_ability_declares_suggested_abilities_override( string $relative_path ): void {
		$src = (string) file_get_contents( $this->plugin_root . '/' . $relative_path );
		$this->assertMatchesRegularExpression(
			'/protected\\s+function\\s+suggested_abilities\\s*\\(\\s*\\)\\s*:\\s*array/',
			$src,
			"{$relative_path} must declare a protected suggested_abilities(): array override"
		);
	}

	/**
	 * @dataProvider initial_batch_provider
	 */
	public function test_initial_batch_entries_all_have_slug_and_reason( string $relative_path, string $slug ): void {
		$src = (string) file_get_contents( $this->plugin_root . '/' . $relative_path );

		// Isolate the suggested_abilities() method body.
		$this->assertSame(
			1,
			preg_match(
				'/protected\\s+function\\s+suggested_abilities\\s*\\(\\s*\\)\\s*:\\s*array\\s*\\{(.*?)\\n\\t\\}/s',
				$src,
				$method_match
			),
			"Could not locate suggested_abilities() method body in {$relative_path}"
		);
		$body = $method_match[1];

		// Every `'slug' =>` clause must be a non-empty string literal.
		preg_match_all( "/'slug'\\s*=>\\s*'([^']*)'/", $body, $slug_matches );
		$this->assertNotEmpty( $slug_matches[1], "{$slug}: at least one 'slug' entry required" );
		foreach ( $slug_matches[1] as $entry_slug ) {
			$this->assertNotSame( '', $entry_slug, "{$slug}: every suggestion entry must have a non-empty slug" );
		}

		// Every `'reason' =>` clause must be a non-empty string literal (or __() wrapper with a non-empty first arg).
		preg_match_all( "/'reason'\\s*=>\\s*(?:__\\(\\s*)?'([^']*)'/", $body, $reason_matches );
		$this->assertNotEmpty( $reason_matches[1], "{$slug}: at least one 'reason' entry required" );
		foreach ( $reason_matches[1] as $reason ) {
			$this->assertNotSame( '', $reason, "{$slug}: every suggestion entry must have a non-empty reason" );
		}

		// Each entry-count MUST match (every slug has a matching reason).
		$this->assertCount(
			count( $slug_matches[1] ),
			$reason_matches[1],
			"{$slug}: mismatched slug/reason pairs — every entry needs both"
		);
	}
}
