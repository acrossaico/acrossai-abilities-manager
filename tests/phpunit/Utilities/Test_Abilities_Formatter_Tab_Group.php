<?php
/**
 * Feature 102 — tab_group reaches the REST record.
 *
 * The abilities screen's Toolset column and its toolset filter both read this field.
 * Before Feature 102, AcrossAI_Ability_Merger::normalize_registry() dropped meta.acrossai
 * entirely, so tab_group never left the server — which is why the grouping previously had
 * to be shipped as a separate ~451-row definitions payload.
 *
 * tab_group is READ-ONLY for this plugin. The MCP tool catalogue is derived from the same
 * value (DEC-ABILITY-GROUP-IDENTIFIER-LOAD-BEARING), so re-tagging an ability silently moves
 * it between MCP tools. These tests pin the field's presence and its empty default; they do
 * not sanction writing it.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Abilities_Formatter;

require_once dirname( __DIR__, 3 ) . '/includes/Utilities/AcrossAI_Abilities_Formatter.php';

/**
 * Covers AcrossAI_Abilities_Formatter::format_merged_ability() for Feature 102.
 */
class Test_Abilities_Formatter_Tab_Group extends TestCase {

	/**
	 * A registry-backed ability carries its toolset through to the response.
	 *
	 * @return void
	 */
	public function test_merged_ability_exposes_tab_group(): void {
		$out = AcrossAI_Abilities_Formatter::format_merged_ability(
			array(
				'slug'      => 'acrossai/get-post',
				'label'     => 'Get Post',
				'category'  => 'acrossai-content',
				'tab_group' => 'content',
			)
		);

		$this->assertArrayHasKey( 'tab_group', $out, 'tab_group must be part of the ability record.' );
		$this->assertSame( 'content', $out['tab_group'] );
	}

	/**
	 * An ability with no toolset reports an empty string, never null.
	 *
	 * The client renders this cell directly; a null would print as "null" or throw in the
	 * column renderer, where '' is the documented "belongs to no toolset" value.
	 *
	 * @return void
	 */
	public function test_missing_tab_group_defaults_to_empty_string(): void {
		$out = AcrossAI_Abilities_Formatter::format_merged_ability(
			array(
				'slug'  => 'acrossai/some-ability',
				'label' => 'Some Ability',
			)
		);

		$this->assertSame( '', $out['tab_group'] );
		$this->assertNotNull( $out['tab_group'] );
	}

	/**
	 * An empty toolset survives as an empty string rather than being coerced away.
	 *
	 * @return void
	 */
	public function test_explicit_empty_tab_group_is_preserved(): void {
		$out = AcrossAI_Abilities_Formatter::format_merged_ability(
			array(
				'slug'      => 'acrossai/some-ability',
				'tab_group' => '',
			)
		);

		$this->assertSame( '', $out['tab_group'] );
	}

	/**
	 * Adding tab_group did not disturb the surrounding contract.
	 *
	 * Registry rows are forced to 'publish' and 'editable' => false; the status filter added
	 * by this same feature depends on that being true, so it is pinned here.
	 *
	 * @return void
	 */
	public function test_registry_rows_remain_published_and_uneditable(): void {
		$out = AcrossAI_Abilities_Formatter::format_merged_ability(
			array(
				'slug'      => 'acrossai/get-post',
				'tab_group' => 'content',
			)
		);

		$this->assertSame( 'publish', $out['status'] );
		$this->assertFalse( $out['editable'] );
	}
}
