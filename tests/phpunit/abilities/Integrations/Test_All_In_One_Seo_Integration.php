<?php
/**
 * Tests: the All in One SEO row claims every namespace AIOSEO actually ships.
 *
 * AIOSEO namespaces its abilities per AREA, not per plugin — `aioseo-posts/`, `aioseo-settings/`,
 * `aioseo-robots/`, `aioseo-audit/`, `aioseo-notifications/`. A single-prefix claim of the kind every
 * other integration here makes would therefore capture none of them, and all thirteen would stay in
 * the catch-all with no toolset of their own (issue #184). That is the regression these assertions
 * exist for: the claim is a list, and shortening it to one entry has no visible symptom until an
 * assistant cannot find the abilities.
 *
 * Driven through `ability_prefixes()` and the tagger rather than asserted against source, so a claim
 * that is present but mis-spelled fails too.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.44
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\Abilities\Integrations;

use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\All_In_One_Seo;
use PHPUnit\Framework\TestCase;

/**
 * The AIOSEO toolset declaration.
 */
final class Test_All_In_One_Seo_Integration extends TestCase {

	/**
	 * Every ability name AIOSEO 5.0.3 registers.
	 *
	 * The full set rather than one per namespace: a claim that covers four of the five areas is the
	 * realistic mistake, and only enumerating all of them catches it.
	 *
	 * @return string[]
	 */
	private static function host_ability_names(): array {
		return array(
			'aioseo-posts/seo-data-get',
			'aioseo-posts/seo-data-update',
			'aioseo-posts/list-missing-seo',
			'aioseo-posts/list-truseo-score',
			'aioseo-settings/get',
			'aioseo-notifications/list',
			'aioseo-robots/output-get',
			'aioseo-robots/rules-list',
			'aioseo-robots/rules-add',
			'aioseo-robots/rules-update',
			'aioseo-robots/rules-delete',
			'aioseo-audit/homepage-get',
			'aioseo-audit/site-get',
		);
	}

	/**
	 * The first name segment, which is what a prefix claim matches on.
	 *
	 * @param  string $name Ability name.
	 * @return string
	 */
	private static function prefix_of( string $name ): string {
		$slash = strpos( $name, '/' );

		return false === $slash ? $name : substr( $name, 0, $slash );
	}

	public function test_every_aioseo_ability_is_covered_by_a_claimed_prefix(): void {
		$claimed = ( new All_In_One_Seo() )->ability_prefixes();

		foreach ( self::host_ability_names() as $name ) {
			$this->assertContains(
				self::prefix_of( $name ),
				$claimed,
				"{$name} is claimed by no prefix, so it would fall through to the catch-all group."
			);
		}
	}

	/**
	 * The bare `aioseo` prefix is reserved for abilities of ours.
	 *
	 * AIOSEO has never used it, and claiming it before we ship anything there means those abilities
	 * group correctly on the day they land rather than after someone notices they did not.
	 */
	public function test_the_bare_aioseo_prefix_is_reserved(): void {
		$this->assertContains( 'aioseo', ( new All_In_One_Seo() )->ability_prefixes() );
	}

	/**
	 * `seo` must NOT be claimed.
	 *
	 * That namespace holds this plugin's 58 Yoast abilities. Claiming it here would pull every one of
	 * them out of the Yoast toolset and into this one on any site running both plugins.
	 */
	public function test_the_yoast_seo_namespace_is_not_claimed(): void {
		$claimed = ( new All_In_One_Seo() )->ability_prefixes();

		$this->assertNotContains( 'seo', $claimed );
		$this->assertNotContains( 'taxonomies', $claimed );
	}

	/**
	 * The group key is load-bearing: it is the `?tab=` argument and the `toolset/{group}` tool name.
	 */
	public function test_the_group_key_names_the_plugin(): void {
		$integration = new All_In_One_Seo();

		$this->assertSame( 'all-in-one-seo', $integration->group() );
		$this->assertSame( All_In_One_Seo::TAB_GROUP, $integration->group() );
	}

	/**
	 * Presence needs both symbols.
	 *
	 * `AIOSEO_VERSION` is defined by the free and Pro editions early in load, before the `aioseo()`
	 * container exists, so the constant alone would report the plugin usable while every call through
	 * the container would fail.
	 */
	public function test_presence_requires_the_constant_and_the_container(): void {
		$expected = defined( 'AIOSEO_VERSION' ) && function_exists( 'aioseo' );

		$this->assertSame( $expected, ( new All_In_One_Seo() )->is_active() );
	}

	/**
	 * The MCP description has to warn about the two limits in AIOSEO's own set, because an assistant
	 * that plans a settings write or a fresh audit will otherwise fail at execute time.
	 */
	public function test_the_description_names_the_read_only_settings_and_cached_audit_limits(): void {
		$description = ( new All_In_One_Seo() )->toolset_description();

		$this->assertStringContainsString( 'read-only', $description );
		$this->assertStringContainsString( 'stored scan', $description );
	}
}
