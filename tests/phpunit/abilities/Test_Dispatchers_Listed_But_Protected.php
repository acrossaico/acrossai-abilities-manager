<?php
/**
 * Dispatchers belong in the operator's inventory, but nowhere else.
 *
 * ## The two jobs the protected list was doing
 *
 * "Protected" covers every `toolset/*` dispatcher, the mcp-adapter routers,
 * and any transport ability a sibling declares — acrossai-pro's Fleet tool
 * arrives that way, through `ToolAbilities::protect_from_toolsets()`.
 *
 * It was doing two unrelated jobs at once:
 *
 *   1. keeping dispatchers out of toolset MEMBERSHIP — `toolset/other` was
 *      observed listing the server guide that describes how to call a Toolset;
 *   2. hiding them from the operator's own ability inventory.
 *
 * Only the first is worth having. A dispatcher that cannot be seen cannot be
 * understood, and a count of "791 abilities" that silently omits the twenty-odd
 * tools an assistant actually calls is a worse answer than listing them.
 *
 * So the list shows them and marks them; every other consumer still excludes
 * them. These tests pin both halves, because the failure mode of getting it
 * half-right is silent: dispatchers reappearing as members of their own
 * toolsets, which is the bug that produced the protected list in the first
 * place.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.46
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Abilities_Formatter;
use WP_UnitTestCase;

/**
 * Class Test_Dispatchers_Listed_But_Protected.
 */
class Test_Dispatchers_Listed_But_Protected extends WP_UnitTestCase {

	/**
	 * Source of the registry query, read once.
	 *
	 * @var string
	 */
	private string $query_src = '';

	protected function setUp(): void {
		parent::setUp();
		$root            = dirname( __DIR__, 3 );
		$this->query_src = (string) file_get_contents(
			$root . '/includes/Utilities/AcrossAI_Ability_Registry_Query.php'
		);
	}

	/**
	 * The inventory must not skip a protected slug.
	 *
	 * @return void
	 */
	public function test_the_registry_query_no_longer_skips_protected_slugs(): void {
		$this->assertStringNotContainsString(
			"if ( AcrossAI_Protected_Abilities::is_protected( \$slug ) ) {\n\t\t\t\tcontinue;",
			$this->query_src,
			'The inventory skipped a protected slug again — dispatchers would vanish from the screen.'
		);
	}

	/**
	 * …but it must still ASK, so the row can be marked.
	 *
	 * @return void
	 */
	public function test_the_registry_query_still_marks_protected_slugs(): void {
		$this->assertStringContainsString(
			'AcrossAI_Protected_Abilities::is_protected( $slug )',
			$this->query_src,
			'Without the check there is no flag, and the screen cannot tell a dispatcher from an editable ability.'
		);
		$this->assertStringContainsString(
			"\$merged['protected']",
			$this->query_src,
			'The flag must reach the merged shape or the formatter has nothing to emit.'
		);
	}

	/**
	 * The flag reaches REST, and defaults to false rather than absent.
	 *
	 * @return void
	 */
	public function test_the_formatter_emits_the_flag_for_both_shapes(): void {
		$dispatcher = AcrossAI_Abilities_Formatter::format_merged_ability(
			array(
				'slug'      => 'toolset/diagnostics',
				'protected' => true,
			)
		);
		$ordinary = AcrossAI_Abilities_Formatter::format_merged_ability(
			array( 'slug' => 'content/get-post' )
		);

		$this->assertArrayHasKey( 'protected', $dispatcher );
		$this->assertTrue( $dispatcher['protected'] );

		$this->assertArrayHasKey(
			'protected',
			$ordinary,
			'A key present on only some rows is one the screen has to guess at.'
		);
		$this->assertFalse( $ordinary['protected'] );
	}

	/**
	 * Both formatter shapes carry the same keys.
	 *
	 * A DB-created ability is never a dispatcher, but the screen reads one
	 * list built from both shapes.
	 *
	 * @return void
	 */
	public function test_both_formatter_shapes_agree_on_the_key(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Utilities/AcrossAI_Abilities_Formatter.php'
		);

		$this->assertSame(
			2,
			substr_count( $src, "'protected'       =>" ),
			'Both format_for_response() and format_merged_ability() must emit the key.'
		);
	}

	/**
	 * The job worth keeping: a dispatcher is never a member of a toolset.
	 *
	 * This is the regression that created the protected list — `toolset/other`
	 * listing the very abilities that describe how to call a Toolset. Listing
	 * dispatchers in the inventory must not bring it back.
	 *
	 * @return void
	 */
	public function test_grouping_still_excludes_dispatchers(): void {
		$group = '\AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Group';

		if ( ! class_exists( $group ) ) {
			$this->markTestSkipped( 'Ability grouping is not loaded in this harness.' );
		}

		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Utilities/AcrossAI_Ability_Group.php'
		);

		$this->assertStringContainsString(
			'AcrossAI_Protected_Abilities::get_protected_slugs()',
			$src,
			'Grouping stopped excluding protected slugs — dispatchers will reappear as members of their own toolsets.'
		);
		$this->assertStringContainsString(
			'in_array( $ability->get_name(), $protected, true )',
			$src,
			'The exclusion itself is gone from the grouping loop.'
		);
	}

	/**
	 * Overrides are still refused on a dispatcher.
	 *
	 * The row is visible; it is not editable. Both write paths must keep
	 * saying so, or the screen offers a control that corrupts a router.
	 *
	 * @return void
	 */
	public function test_write_endpoints_still_refuse_protected_slugs(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php'
		);

		$this->assertSame(
			2,
			substr_count( $src, 'AcrossAI_Protected_Abilities::get_protected_slugs(), true )' ),
			'Both write paths must still refuse a protected slug.'
		);
		$this->assertStringContainsString( 'rest_protected_ability', $src );
	}
}
