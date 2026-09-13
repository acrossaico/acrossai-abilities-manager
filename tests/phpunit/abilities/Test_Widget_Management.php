<?php
/**
 * Feature 111 — invariants across the widget abilities.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.42
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Widget_Repository;
use WP_UnitTestCase;

class Test_Widget_Management extends WP_UnitTestCase {

	/**
	 * Class => slug. Two predate this feature; the other nine are its subject.
	 *
	 * @return array<string,string>
	 */
	private static function inventory(): array {
		return array(
			'List_Sidebars'                => 'widgets/list-sidebars',
			'List_Widgets'                 => 'widgets/list-widgets',
			'List_Widget_Types'            => 'widgets/list-widget-types',
			'Get_Widget'                   => 'widgets/get-widget',
			'Get_Widget_Management_Status' => 'widgets/get-widget-management-status',
			'Add_Widget'                   => 'widgets/add-widget',
			'Update_Widget'                => 'widgets/update-widget',
			'Move_Widget'                  => 'widgets/move-widget',
			'Reorder_Sidebar'              => 'widgets/reorder-sidebar',
			'Deactivate_Widget'            => 'widgets/deactivate-widget',
			'Remove_Widget'                => 'widgets/remove-widget',
		);
	}

	private static function dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Widgets/';
	}

	private static function repository(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Widget_Repository.php';
	}

	/**
	 * @return string[]
	 */
	private static function ability_files(): array {
		$files = glob( self::dir() . '*.php' );

		return array_values(
			array_filter(
				is_array( $files ) ? $files : array(),
				static fn( string $f ): bool => 'Category_Registrar.php' !== basename( $f )
			)
		);
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

	public function test_inventory_matches_the_directory(): void {
		$found = array_map(
			static fn( string $f ): string => basename( $f, '.php' ),
			self::ability_files()
		);

		sort( $found );
		$expected = array_keys( self::inventory() );
		sort( $expected );

		$this->assertSame( $expected, $found );
	}

	public function test_every_slug_is_declared_and_unique(): void {
		$slugs = array();

		foreach ( self::inventory() as $class => $slug ) {
			$src = (string) file_get_contents( self::dir() . $class . '.php' );

			$this->assertStringContainsString( "'name' => '" . $slug . "'", $src, "{$class} does not declare {$slug}." );
			$slugs[] = $slug;
		}

		$this->assertSame( count( $slugs ), count( array_unique( $slugs ) ) );
	}

	/**
	 * The registrar is a singleton with a private constructor and must never be instantiated as an
	 * ability.
	 *
	 * An earlier wiring pass globbed the folder and added `new Widgets\Category_Registrar()`, which
	 * would have been a fatal error on every page load.
	 */
	public function test_the_category_registrar_is_not_instantiated_as_an_ability(): void {
		$bootstrap = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		$this->assertStringNotContainsString(
			'new Widgets\\Category_Registrar();',
			$bootstrap,
			'Category_Registrar has a private constructor; it is hooked via instance(), never constructed.'
		);
		$this->assertStringContainsString(
			"Widgets\\Category_Registrar::instance(), 'register'",
			$bootstrap
		);
	}

	public function test_the_bootstrap_instantiates_every_ability(): void {
		$bootstrap = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		foreach ( array_keys( self::inventory() ) as $class ) {
			$this->assertStringContainsString(
				'new Widgets\\' . $class . '();',
				$bootstrap,
				"{$class} is declared but never instantiated."
			);
		}
	}

	/**
	 * Settings are written through the widget's own update handler.
	 *
	 * Each widget type sanitises its own input there — the text widget runs its title through
	 * sanitize_text_field() and, for anyone without unfiltered_html, its body through wp_kses_post().
	 * Writing the option directly would make these abilities the one route into a site's widgets
	 * that applies no sanitisation, and would ignore a widget that refuses an update.
	 */
	public function test_settings_go_through_the_widget_update_handler(): void {
		$repo = self::code_only( (string) file_get_contents( self::repository() ) );

		$this->assertMatchesRegularExpression(
			'/\$sanitised\s*=\s*\$widget->update\(\s*\$new,\s*\$old\s*\)/',
			$repo,
			'save_instance() must call WP_Widget::update() rather than writing the option.'
		);
		$this->assertStringContainsString( 'settings_rejected', $repo, 'A widget refusing an update must be reported.' );
	}

	/**
	 * Nothing writes the widget options directly.
	 */
	public function test_no_ability_writes_widget_options_directly(): void {
		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( (string) file_get_contents( $file ) );

			foreach ( array( 'update_option(', 'wp_set_sidebars_widgets(', 'wp_assign_widget_to_sidebar(' ) as $writer ) {
				$this->assertStringNotContainsString(
					$writer,
					$code,
					basename( $file ) . " calls {$writer} directly. Widget state belongs behind Widget_Repository."
				);
			}
		}
	}

	/**
	 * `sidebars_widgets` is not purely a map of sidebars.
	 *
	 * It also carries `array_version`, and `wp_inactive_widgets` is a real key that is not a
	 * registered sidebar. Iterating it as though every key were a sidebar corrupts the option.
	 */
	public function test_the_bookkeeping_key_is_excluded_from_sidebar_iteration(): void {
		$repo = self::code_only( (string) file_get_contents( self::repository() ) );

		$this->assertStringContainsString( "'array_version'", $repo );
		$this->assertMatchesRegularExpression(
			'/in_array\(\s*\(string\) \$sidebar,\s*self::NON_SIDEBAR_KEYS,\s*true\s*\)/',
			$repo,
			'placements() must skip the bookkeeping keys.'
		);
	}

	/**
	 * A partial reorder is refused.
	 *
	 * Applying one would silently drop every widget the caller left out — that looks like a reorder
	 * and is a deletion.
	 */
	public function test_a_partial_reorder_is_refused(): void {
		$repo = self::code_only( (string) file_get_contents( self::repository() ) );

		$this->assertStringContainsString( 'incomplete_order', $repo );
		$this->assertMatchesRegularExpression(
			'/if\s*\(\s*\$current\s*!==\s*\$check\s*\)/',
			$repo,
			'reorder() must compare the supplied list against what the sidebar actually holds.'
		);
	}

	/**
	 * Placement changes leave the option reindexed.
	 *
	 * wp_assign_widget_to_sidebar() unsets without reindexing — measured: a sidebar left holding
	 * keys 0, 1 and 3.
	 */
	public function test_placements_are_reindexed_after_a_move(): void {
		$repo = self::code_only( (string) file_get_contents( self::repository() ) );

		$this->assertStringContainsString( 'function normalise_placements', $repo );
		$this->assertGreaterThanOrEqual(
			2,
			substr_count( $repo, 'self::normalise_placements()' ),
			'Both the placement path and the delete path must reindex.'
		);
	}

	/**
	 * An orphaned sidebar is still a real place a widget sits.
	 *
	 * Measured on a block-theme site: no registered sidebars at all, while sidebars_widgets still
	 * held two of them with five widgets. Refusing those would make the suite inert on any block
	 * theme.
	 */
	public function test_orphaned_sidebars_are_accepted_and_reported(): void {
		$repo = self::code_only( (string) file_get_contents( self::repository() ) );

		$this->assertStringContainsString( 'function is_registered_sidebar', $repo );
		$this->assertStringContainsString( "'orphaned_sidebars'", $repo );
	}

	/**
	 * Removing a widget asks first; deactivating does not.
	 */
	public function test_only_removal_is_confirm_gated(): void {
		$remove = (string) file_get_contents( self::dir() . 'Remove_Widget.php' );

		$this->assertStringContainsString( 'confirmation_required', $remove );
		$this->assertStringContainsString( "'destructive' => true", $remove );

		foreach ( array( 'Deactivate_Widget.php', 'Move_Widget.php', 'Update_Widget.php', 'Add_Widget.php' ) as $name ) {
			$this->assertStringNotContainsString(
				'confirmation_required',
				(string) file_get_contents( self::dir() . $name ),
				"{$name} is reversible and should not ask for confirmation."
			);
		}
	}

	/**
	 * Every ability declares an error_code in its output schema.
	 *
	 * These return `error_code` on failure and every schema sets additionalProperties: false, so an
	 * undeclared key fails the ability's own validation after the work is done.
	 */
	public function test_error_code_is_declared_wherever_it_is_returned(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );

			if ( ! str_contains( $src, "'error_code' =>" ) ) {
				continue;
			}

			$this->assertStringContainsString(
				"'error_code' => array( 'type' => 'string' )",
				$src,
				basename( $file ) . " returns error_code but does not declare it, so the response fails its own schema."
			);
		}
	}

	public function test_the_repository_is_final_and_static_only(): void {
		$repo = (string) file_get_contents( self::repository() );

		$this->assertStringContainsString( 'final class Widget_Repository', $repo );
		$this->assertStringContainsString( 'private function __construct()', $repo );
	}

	/**
	 * The inactive store is a real destination, not an error.
	 */
	public function test_the_inactive_store_is_a_valid_sidebar(): void {
		$this->assertSame( 'wp_inactive_widgets', Widget_Repository::INACTIVE );
		$this->assertTrue( Widget_Repository::sidebar_exists( Widget_Repository::INACTIVE ) );
	}

	/**
	 * Instance ids parse into a type and a number.
	 */
	public function test_instance_ids_parse(): void {
		$this->assertSame( array( 'id_base' => 'text', 'number' => 3 ), Widget_Repository::parse_id( 'text-3' ) );
		$this->assertSame( array( 'id_base' => 'media_image', 'number' => 12 ), Widget_Repository::parse_id( 'media_image-12' ) );
		$this->assertNull( Widget_Repository::parse_id( 'notawidget' ) );
		$this->assertNull( Widget_Repository::parse_id( 'trailing-' ) );
	}
}
