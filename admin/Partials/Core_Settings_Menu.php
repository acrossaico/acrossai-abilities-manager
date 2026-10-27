<?php
/**
 * Core admin settings tab for the absorbed acrossai-core-abilities runtime.
 *
 * Registers the "Core" tab on the shared acrossai-settings admin page.
 * Owns the extra-MIME-types textarea and the plugin-scoped uninstall opt-in.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Admin\Partials
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Admin\Partials;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Mime_Types_Store;

defined( 'ABSPATH' ) || exit;

/**
 * "Core" tab on the shared `admin.php?page=acrossai-settings` page.
 *
 * Hosts settings for the Acrossai Abilities Manager plugin itself. Today it
 * only exposes the "extra allowed upload MIME types" store; new sections
 * for future plugin-level settings can be added here without spinning up
 * another admin page.
 *
 * Registers via the shared `acrossai_settings_tabs` filter (same mechanism
 * as `acrossai-abilities-manager` at priority 10 and `acrossai-mcp-manager`
 * at priority 20). This tab lands at priority 30.
 */
final class Core_Settings_Menu {

	/**
	 * The Settings API tab this class renders into.
	 *
	 * Feature 046: the absorbed extra-MIME-types field lives inside the
	 * existing `abilities` tab (owned by SettingsMenu.php). No standalone
	 * "core" tab is registered; the `?tab=core` URL is not reachable.
	 */
	public const TAB_SLUG = 'abilities';

	/**
	 * Singleton reference.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Private constructor — access via instance().
	 */
	private function __construct() {}

	/**
	 * Return the singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooked on `admin_init`. Registers the extra-MIME-types Settings API
	 * field into the shared Abilities tab.
	 *
	 * Does NOT register a separate tab and does NOT register a second
	 * uninstall-opt-in checkbox — the manager's existing SettingsMenu.php
	 * owns both tab registration and the master
	 * `acrossai_abilities_uninstall_delete_data` opt-in that governs both
	 * the manager's data and this class's `Mime_Types_Store::OPTION`.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		if ( ! class_exists( '\AcrossAI_Main_Menu\SettingsPage' ) ) {
			return;
		}
		$renderer = \AcrossAI_Main_Menu\SettingsPage::get_settings_renderer();
		if ( ! $renderer ) {
			return;
		}
		$page_slug = $renderer->tab_page_slug( self::TAB_SLUG );

		register_setting(
			$page_slug,
			Mime_Types_Store::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_option' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'acrossai_core_upload_media',
			__( 'Upload Media Abilities', 'acrossai-abilities-manager' ),
			array( $this, 'render_upload_media_section' ),
			$page_slug
		);

		add_settings_field(
			'acrossai_core_mimes_currently_allowed',
			__( 'Allowed for upload-media', 'acrossai-abilities-manager' ),
			array( $this, 'render_currently_allowed_field' ),
			$page_slug,
			'acrossai_core_upload_media'
		);

		add_settings_field(
			'acrossai_core_mimes_custom',
			__( 'Add file types', 'acrossai-abilities-manager' ),
			array( $this, 'render_custom_field' ),
			$page_slug,
			'acrossai_core_upload_media'
		);
	}

	/**
	 * Sanitize callback for the wp_option. Parses the textarea into the
	 * canonical ext=>mime map, hands it to Mime_Types_Store, and surfaces
	 * per-entry rejections as settings_errors so the admin sees exactly
	 * what was dropped.
	 *
	 * @param mixed $input Raw value from $_POST[ Mime_Types_Store::OPTION ].
	 * @return array<string,string>
	 */
	public function sanitize_option( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		// The Settings API sanitize callback fires once with the raw form
		// value (`[ 'custom' => "ext = mime\n..." ]`) and a second time with
		// the already-normalized `ext => mime` map when update_option()
		// delegates to add_option() (which happens whenever the stored value
		// equals the registered `default`). Both shapes must be accepted or
		// the second pass clobbers our own output back to `[]`.
		if ( isset( $input['custom'] ) && is_string( $input['custom'] ) ) {
			$additions = array();
			foreach ( preg_split( '/\r\n|\r|\n/', $input['custom'] ) as $line_no => $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				if ( ! str_contains( $line, '=' ) ) {
					add_settings_error(
						Mime_Types_Store::OPTION,
						'acrossai_core_mimes_bad_line_' . $line_no,
						sprintf(
							/* translators: %s: the offending line */
							esc_html__( 'Skipped custom line "%s": expected "ext = mime/type".', 'acrossai-abilities-manager' ),
							esc_html( $line )
						),
						'error'
					);
					continue;
				}
				list( $ext, $mime ) = array_map( 'trim', explode( '=', $line, 2 ) );
				$additions[ $ext ]  = $mime;
			}
		} else {
			$additions = $input;
		}

		// Use validate() (not set()) here: we're already inside WordPress's
		// update_option() → sanitize_option_{OPTION} filter, so calling set()
		// would re-enter that same filter and OOM the site. Return the
		// validated value; WordPress persists it via the normal Settings API
		// round-trip after this callback returns.
		$result = Mime_Types_Store::validate( $additions );

		foreach ( $result['skipped'] as $entry ) {
			add_settings_error(
				Mime_Types_Store::OPTION,
				'acrossai_core_mimes_skipped_' . md5( $entry['ext'] . '|' . $entry['mime'] ),
				sprintf(
					/* translators: 1: extension, 2: mime type, 3: rejection reason */
					esc_html__( 'Skipped "%1$s = %2$s": %3$s', 'acrossai-abilities-manager' ),
					esc_html( $entry['ext'] ),
					esc_html( $entry['mime'] ),
					esc_html( $entry['reason'] )
				),
				'error'
			);
		}

		return $result['stored'];
	}

	/**
	 * Render the section intro copy for the Upload Media MIME-types block.
	 *
	 * @return void
	 */
	public function render_upload_media_section(): void {
		echo '<p>';
		esc_html_e( 'Controls the file types the upload-media ability will accept. Entries here are merged into WordPress\'s allowlist only during upload-media calls — regular Media Library uploads via wp-admin are NOT affected. Add-only for core defaults; entries you add here can be removed from the same list.', 'acrossai-abilities-manager' );
		echo '</p>';
		echo '<p class="description">';
		printf(
			/* translators: %s: the ability slug, wrapped in a <code> tag. */
			esc_html__( 'Tip: you do not always need an image to hand. The %s ability can generate one from a text prompt and add it to the Media Library in a single call — handy for featured images and placeholders. It uses WordPress 7.0\'s built-in AI client and needs an AI connector that supports image generation.', 'acrossai-abilities-manager' ),
			'<code>media/generate-image</code>'
		);
		echo '</p>';
	}

	/**
	 * Render the "currently allowed" read-only MIME-types field.
	 *
	 * @return void
	 */
	public function render_currently_allowed_field(): void {
		// Merge WP core defaults + other plugins' filters with this plugin's
		// extras to show what upload-media will actually accept.
		$effective = Mime_Types_Store::filter_upload_mimes( get_allowed_mime_types() );
		$mimes     = array_unique( array_values( $effective ) );
		sort( $mimes );
		echo '<code style="word-break: break-word;">' . esc_html( implode( ', ', $mimes ) ) . '</code>';
	}

	/**
	 * Render the "custom MIME types" textarea field.
	 *
	 * @return void
	 */
	public function render_custom_field(): void {
		$stored       = Mime_Types_Store::get();
		$custom_lines = array();
		foreach ( $stored as $ext => $mime ) {
			$custom_lines[] = $ext . ' = ' . $mime;
		}
		sort( $custom_lines );

		$name        = sprintf( '%s[custom]', esc_attr( Mime_Types_Store::OPTION ) );
		$placeholder = "svg = image/svg+xml\nwebp = image/webp\navif = image/avif";

		printf(
			'<textarea id="acrossai_core_mimes_custom" name="%1$s" rows="6" cols="60" placeholder="%2$s">%3$s</textarea>',
			esc_attr( $name ),
			esc_attr( $placeholder ),
			esc_textarea( implode( "\n", $custom_lines ) )
		);
		?>
		<p class="description">
			<?php esc_html_e( 'One entry per line in the form "ext = mime/type". Examples:', 'acrossai-abilities-manager' ); ?>
		</p>
		<ul class="description" style="margin-top: 0.25em; margin-left: 1.5em; list-style: disc;">
			<li><code>svg = image/svg+xml</code> — <?php esc_html_e( '⚠ SVG files can contain scripts; only enable if you trust every uploader.', 'acrossai-abilities-manager' ); ?></li>
			<li><code>webp = image/webp</code></li>
			<li><code>avif = image/avif</code></li>
			<li><code>heic = image/heic</code></li>
			<li><code>ico = image/x-icon</code></li>
		</ul>
		<p class="description">
			<?php esc_html_e( 'Lines that fail sanitization are dropped with a notice on save. Removing a line removes that entry from the site\'s allowlist on the next save.', 'acrossai-abilities-manager' ); ?>
		</p>
		<?php
	}
}
