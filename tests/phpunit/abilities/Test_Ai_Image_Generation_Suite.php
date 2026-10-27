<?php
/**
 * Feature 131 — invariants for the generate-image ability.
 *
 * Core's AI client and a connector are absent in the test harness, so these
 * assert the ability's shape and the decisions that must not be edited away. The
 * behavioural half (a real generation) is verified live against a configured
 * connector.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.43
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Ai_Image_Generation_Suite extends WP_UnitTestCase {

	private static function root(): string {
		return dirname( __DIR__, 3 );
	}

	private static function read( string $rel ): string {
		$path = self::root() . '/' . ltrim( $rel, '/' );
		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/**
	 * Strip comments so an assertion cannot match a docblock that merely names a symbol.
	 */
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

	private const ABILITY = 'includes/Abilities/Media/Generate_Image.php';
	private const GEN     = 'includes/Abilities/Utilities/Media/Ai_Image_Generator.php';
	private const SIDE    = 'includes/Abilities/Utilities/Media/Media_Sideload.php';

	public function test_slug_category_and_group(): void {
		$src = self::read( self::ABILITY );
		$this->assertStringContainsString( "'name' => 'media/generate-image'", $src );
		$this->assertStringContainsString( "'category'            => 'acrossai-media'", $src );
		$this->assertStringContainsString( "'tab_group'       => 'content'", $src );
	}

	public function test_annotations_are_additive_not_destructive(): void {
		$this->assertMatchesRegularExpression(
			"/'readonly'\s*=>\s*false,\s*'destructive'\s*=>\s*false,\s*'idempotent'\s*=>\s*false/",
			self::read( self::ABILITY )
		);
	}

	/**
	 * Each run makes a paid AI call, so it must be gated on confirm.
	 */
	public function test_generation_is_confirm_gated(): void {
		$src = self::code_only( self::read( self::ABILITY ) );
		$this->assertStringContainsString( "'confirm'", $src, 'The input schema must declare a confirm property.' );
		$this->assertStringContainsString( "'confirmation_required'", $src, 'An unconfirmed call must return confirmation_required.' );
	}

	/**
	 * Only the generator Utility names core's AI client.
	 */
	public function test_only_the_generator_names_the_ai_client(): void {
		$ability = self::code_only( self::read( self::ABILITY ) );
		foreach ( array( 'wp_ai_client_prompt', 'WordPress\\AiClient', 'generate_image_result' ) as $symbol ) {
			$this->assertStringNotContainsString( $symbol, $ability, 'Generate_Image names ' . $symbol . ' directly; the SDK belongs in Ai_Image_Generator.' );
		}
		$gen = self::read( self::GEN );
		$this->assertStringContainsString( 'wp_ai_client_prompt', $gen );
		$this->assertStringContainsString( 'generate_image_result', $gen );
	}

	/**
	 * Only the sideload Utility writes to the Media Library.
	 */
	public function test_only_the_sideloader_creates_attachments(): void {
		$ability = self::code_only( self::read( self::ABILITY ) );
		$this->assertStringNotContainsString( 'media_handle_sideload', $ability, 'Generate_Image sideloads directly; that belongs in Media_Sideload.' );
		$this->assertStringContainsString( 'media_handle_sideload', self::read( self::SIDE ) );
	}

	/**
	 * The sideload respects the Upload Media Abilities MIME allowlist, like upload-media.
	 */
	public function test_sideload_applies_the_mime_allowlist(): void {
		$src = self::code_only( self::read( self::SIDE ) );
		$this->assertStringContainsString( 'Mime_Types_Store::class', $src );
		$this->assertMatchesRegularExpression( "/add_filter\(\s*'upload_mimes'/", $src );
		$this->assertMatchesRegularExpression( "/remove_filter\(\s*'upload_mimes'/", $src );
	}

	public function test_utilities_are_final_and_static_only(): void {
		foreach ( array( 'Ai_Image_Generator' => self::GEN, 'Media_Sideload' => self::SIDE ) as $class => $rel ) {
			$src = self::read( $rel );
			$this->assertStringContainsString( 'final class ' . $class, $src );
			$this->assertStringContainsString( 'private function __construct()', $src );
		}
	}

	public function test_availability_is_guarded_core_only(): void {
		$gen = self::read( self::GEN );
		$this->assertStringContainsString( "function_exists( 'wp_ai_client_prompt' )", $gen, 'Must detect WP 7.0 core before calling the client.' );
		$this->assertStringContainsString( 'has_image_support', $gen, 'Must check an image-capable connector exists.' );
		// Must not depend on the AI plugin.
		$this->assertStringNotContainsString( 'WordPress\\AI\\', self::code_only( $gen ), 'The generator must not depend on the AI plugin namespace.' );
	}

	/**
	 * When nothing is configured, the message must say how to fix it, and the
	 * key check must go through WordPress core's own resolver.
	 */
	public function test_unavailable_message_is_actionable(): void {
		$gen = self::read( self::GEN );
		$this->assertStringContainsString( 'options-connectors.php', $gen, 'The message should point to the Connectors settings screen.' );
		$this->assertStringContainsString( 'API key', $gen, 'The message should name the missing API key.' );
		$this->assertStringContainsString( '_wp_connectors_get_api_key_source', $gen, 'The key check should prefer core\'s own resolver.' );
	}

	/**
	 * Reference images: accepted as URL(s)/data URIs, fetched SSRF-safely, and
	 * image-validated, with an edit-specific error when the model can't do it.
	 */
	public function test_reference_images_are_supported_and_fetched_safely(): void {
		$ability = self::read( self::ABILITY );
		$this->assertStringContainsString( "'reference'", $ability, 'The ability must declare a reference input.' );

		$gen = self::code_only( self::read( self::GEN ) );
		$this->assertStringContainsString( 'wp_safe_remote_get', $gen, 'Reference URLs must be fetched SSRF-safely.' );
		$this->assertStringContainsString( "0 !== strpos( \$mime, 'image/' )", $gen, 'A reference URL must be validated as an image.' );
		$this->assertStringContainsString( 'unsupported_image_edit', $gen, 'A model that cannot edit must return a reference-specific error.' );
		$this->assertStringContainsString( 'with_file', $gen, 'References must be attached to the prompt.' );
	}

	public function test_bootstrap_instantiates_the_ability(): void {
		$this->assertStringContainsString(
			'new Media\\Generate_Image();',
			self::read( 'includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' )
		);
	}

	public function test_media_abilities_suggest_the_generator(): void {
		foreach ( array( 'includes/Abilities/Media/Upload_Media.php', 'includes/Abilities/Media/List_Media.php' ) as $rel ) {
			$this->assertStringContainsString(
				"'media/generate-image'",
				self::read( $rel ),
				basename( $rel ) . ' should suggest media/generate-image.'
			);
		}
	}

	public function test_settings_section_mentions_the_ability(): void {
		$this->assertStringContainsString(
			'media/generate-image',
			self::read( 'admin/Partials/Core_Settings_Menu.php' ),
			'The Upload Media Abilities section should point admins at the generator.'
		);
	}
}
