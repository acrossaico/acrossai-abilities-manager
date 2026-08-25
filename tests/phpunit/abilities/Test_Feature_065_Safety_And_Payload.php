<?php
/**
 * Structural tests for Feature 065 — safety envelope + payload enrichment
 * across 9 existing abilities.
 *
 * Source-inspection assertions covering every FR from spec.md. Runs against
 * the plugin's minimal unit-test bootstrap; no full WP environment needed.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.23
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Feature_065_Safety_And_Payload.
 */
class Test_Feature_065_Safety_And_Payload extends WP_UnitTestCase {

	/**
	 * Absolute paths to every source file exercised by these tests.
	 *
	 * @var array<string,string>
	 */
	private array $sources = array();

	/**
	 * Load every source file once per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$plugin_root = dirname( __DIR__, 3 );

		$this->sources = array(
			'deactivate_plugin' => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Plugins/Deactivate_Plugin.php'
			),
			'delete_media'      => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Media/Delete_Media.php'
			),
			'update_media'      => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Media/Update_Media.php'
			),
			'list_media'        => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Media/List_Media.php'
			),
			'get_post'          => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Content/Get_Post.php'
			),
			'update_post'       => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Content/Update_Post.php'
			),
			'delete_post'       => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Content/Delete_Post.php'
			),
			'read_file'         => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/FileManager/Read_File.php'
			),
			'delete_file'       => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/FileManager/Delete_File.php'
			),
		);
	}

	// =========================================================================
	// FR-001, FR-002 — Deactivate_Plugin protected-plugin guard
	// =========================================================================

	/**
	 * FR-001: PROTECTED_PLUGINS constant lists the three AcrossAI-family
	 * plugin file paths.
	 *
	 * @return void
	 */
	public function test_fr001_deactivate_plugin_defines_protected_list(): void {
		$src = $this->sources['deactivate_plugin'];
		$this->assertStringContainsString(
			'private const PROTECTED_PLUGINS',
			$src,
			'Deactivate_Plugin must declare PROTECTED_PLUGINS.'
		);
		$this->assertStringContainsString(
			"'acrossai-mcp-manager/acrossai-mcp-manager.php'",
			$src
		);
		$this->assertStringContainsString(
			"'acrossai-abilities-manager/acrossai-abilities-manager.php'",
			$src
		);
		$this->assertStringContainsString(
			"'acrossai-pro/acrossai-pro.php'",
			$src
		);
	}

	/**
	 * FR-002: guard checks the RESOLVED plugin file via in_array strict mode
	 * and returns success:false + blocked_reason:protected_plugin without
	 * invoking deactivate_plugins().
	 *
	 * @return void
	 */
	public function test_fr002_deactivate_plugin_refuses_with_protected_plugin_reason(): void {
		$src = $this->sources['deactivate_plugin'];
		$this->assertMatchesRegularExpression(
			"/in_array\(\s*\\\$plugin_file,\s*self::PROTECTED_PLUGINS,\s*true\s*\)/",
			$src,
			'Guard must use in_array with strict mode against the resolved file.'
		);
		$this->assertStringContainsString(
			"'blocked_reason' => 'protected_plugin'",
			$src
		);
		// The guard must run BEFORE deactivate_plugins is called.
		$pos_guard      = strpos( $src, "'protected_plugin'" );
		$pos_deactivate = strpos( $src, 'deactivate_plugins(' );
		$this->assertNotFalse( $pos_guard );
		$this->assertNotFalse( $pos_deactivate );
		$this->assertLessThan(
			$pos_deactivate,
			$pos_guard,
			'The protected-plugin refusal must precede the deactivate_plugins() call in source order.'
		);
	}

	// =========================================================================
	// FR-003, FR-004, FR-005 — Delete_Media confirmation + MEDIA_TRASH + string outcome
	// =========================================================================

	/**
	 * FR-003: delete-media requires confirm:true and refuses with
	 * confirmation_required otherwise.
	 *
	 * @return void
	 */
	public function test_fr003_delete_media_requires_confirm(): void {
		$src = $this->sources['delete_media'];
		$this->assertMatchesRegularExpression(
			"/'required'\s*=>\s*array\(\s*'id',\s*'confirm'\s*\)/",
			$src,
			'delete-media input schema must require both id and confirm.'
		);
		$this->assertStringContainsString(
			"'blocked_reason' => 'confirmation_required'",
			$src
		);
	}

	/**
	 * FR-004: delete-media honours MEDIA_TRASH — computes $trashed and calls
	 * wp_delete_attachment( $id, ! $trashed ).
	 *
	 * @return void
	 */
	public function test_fr004_delete_media_honours_media_trash(): void {
		$src = $this->sources['delete_media'];
		$this->assertStringContainsString( "defined( 'MEDIA_TRASH' )", $src );
		$this->assertStringContainsString( 'MEDIA_TRASH', $src );
		$this->assertMatchesRegularExpression(
			"/wp_delete_attachment\(\s*\\\$id,\s*!\s*\\\$trashed\s*\)/",
			$src,
			'delete-media must call wp_delete_attachment($id, ! $trashed).'
		);
	}

	/**
	 * FR-005: response `deleted` is the string "deleted" or "trashed"
	 * (not boolean); output_schema declares the enum.
	 *
	 * @return void
	 */
	public function test_fr005_delete_media_response_uses_string_outcome(): void {
		$src = $this->sources['delete_media'];
		$this->assertMatchesRegularExpression(
			"/'deleted'\s*=>\s*\\\$trashed\s*\?\s*'trashed'\s*:\s*'deleted'/",
			$src
		);
		$this->assertStringContainsString( "'enum' => array( 'deleted', 'trashed' )", $src );
	}

	// =========================================================================
	// FR-006 — List_Media alt-text search
	// =========================================================================

	/**
	 * FR-006: list-media unions an alt-text meta_query with the text-search
	 * results and passes the deduplicated id list into post__in.
	 *
	 * @return void
	 */
	public function test_fr006_list_media_search_includes_alt_text(): void {
		$src = $this->sources['list_media'];
		$this->assertStringContainsString( '_wp_attachment_image_alt', $src );
		$this->assertStringContainsString( "'compare' => 'LIKE'", $src );
		$this->assertStringContainsString( "'post__in'", $src );
		$this->assertStringContainsString( 'array_unique', $src );
	}

	// =========================================================================
	// FR-007 — Update_Media updated[] field
	// =========================================================================

	/**
	 * FR-007: update-media response includes an `updated` array naming
	 * every field written; output_schema declares it as array<string>.
	 *
	 * @return void
	 */
	public function test_fr007_update_media_returns_updated_array(): void {
		$src = $this->sources['update_media'];
		$this->assertStringContainsString( '$updated    = array();', $src );
		$this->assertStringContainsString( "\$updated[]               = 'title';", $src );
		$this->assertStringContainsString( "\$updated[]                 = 'caption';", $src );
		$this->assertStringContainsString( "\$updated[]                 = 'description';", $src );
		$this->assertStringContainsString( "\$updated[] = 'alt_text';", $src );
		$this->assertStringContainsString( "'updated' => \$updated,", $src );
		$this->assertMatchesRegularExpression(
			"/'updated'\s*=>\s*array\(\s*\n?\s*'type'\s*=>\s*'array',\s*\n?\s*'items'\s*=>\s*array\(\s*'type'\s*=>\s*'string'\s*\)/",
			$src,
			'update-media output_schema must declare updated as array<string>.'
		);
	}

	// =========================================================================
	// FR-008, FR-009, FR-010, FR-011 — Update_Post gates
	// =========================================================================

	/**
	 * FR-008: update-post refuses non-writable post types.
	 *
	 * @return void
	 */
	public function test_fr008_update_post_refuses_non_writable_post_types(): void {
		$src = $this->sources['update_post'];
		$this->assertStringContainsString( 'get_post_type_object(', $src );
		$this->assertStringContainsString( "'blocked_reason' => 'non_writable_post_type'", $src );
		$this->assertStringContainsString( '$pt_obj->public', $src );
		$this->assertStringContainsString( '$pt_obj->show_in_rest', $src );
	}

	/**
	 * FR-009: update-post filters protected meta keys via the
	 * acrossai_allowed_protected_meta filter and reports dropped_meta_keys.
	 *
	 * @return void
	 */
	public function test_fr009_update_post_filters_protected_meta(): void {
		$src = $this->sources['update_post'];
		$this->assertStringContainsString( "apply_filters( 'acrossai_allowed_protected_meta', array() )", $src );
		$this->assertStringContainsString( 'is_protected_meta(', $src );
		$this->assertStringContainsString( "str_starts_with( \$key_str, '_' )", $src );
		$this->assertMatchesRegularExpression(
			"/in_array\(\s*\\\$key_str,\s*\\\$allowed,\s*true\s*\)/",
			$src,
			'Meta allow-list check must use in_array strict mode.'
		);
		$this->assertStringContainsString( "'dropped_meta_keys'", $src );
	}

	/**
	 * FR-010: update-post gates status changes into a public status on
	 * publish_posts.
	 *
	 * @return void
	 */
	public function test_fr010_update_post_gates_publish_status_on_publish_posts(): void {
		$src = $this->sources['update_post'];
		$this->assertStringContainsString( 'get_post_status_object(', $src );
		$this->assertStringContainsString( '$pt_obj->cap->publish_posts', $src );
		$this->assertStringContainsString( "'blocked_reason' => 'publish_cap_required'", $src );
	}

	/**
	 * FR-011: update-post gates cross-user author changes on
	 * edit_others_posts.
	 *
	 * @return void
	 */
	public function test_fr011_update_post_gates_author_change_on_edit_others_posts(): void {
		$src = $this->sources['update_post'];
		$this->assertStringContainsString( '$pt_obj->cap->edit_others_posts', $src );
		$this->assertStringContainsString( 'get_current_user_id()', $src );
		$this->assertStringContainsString( "'blocked_reason' => 'edit_others_posts_required'", $src );
	}

	// =========================================================================
	// FR-012, FR-013 — Get_Post enrichment
	// =========================================================================

	/**
	 * FR-012: get-post decorates its response with terms, meta,
	 * featured_image, permalink, edit_link, and author.
	 *
	 * @return void
	 */
	public function test_fr012_get_post_enriches_response_with_derived_fields(): void {
		$src = $this->sources['get_post'];
		$this->assertStringContainsString( 'get_object_taxonomies(', $src );
		$this->assertStringContainsString( 'get_the_terms(', $src );
		$this->assertStringContainsString( 'get_post_thumbnail_id(', $src );
		$this->assertStringContainsString( "wp_get_attachment_image_url( \$thumb_id, 'full' )", $src );
		$this->assertStringContainsString( 'get_permalink(', $src );
		$this->assertStringContainsString( "get_edit_post_link( \$id, 'raw' )", $src );
		$this->assertStringContainsString( 'get_userdata(', $src );
		// output_schema declares the new fields.
		$this->assertStringContainsString( "'terms'          => array( 'type' => 'object' )", $src );
		$this->assertStringContainsString( "'featured_image'", $src );
		$this->assertStringContainsString( "'permalink'      => array( 'type' => 'string' )", $src );
		$this->assertStringContainsString( "'edit_link'      => array( 'type' => 'string' )", $src );
		$this->assertStringContainsString( "'author'         => array( 'type' => 'object' )", $src );
	}

	/**
	 * FR-013: get-post's meta lookup honours the same
	 * acrossai_allowed_protected_meta filter used by update-post.
	 *
	 * @return void
	 */
	public function test_fr013_get_post_meta_respects_protected_meta_filter(): void {
		$src = $this->sources['get_post'];
		$this->assertStringContainsString( "apply_filters( 'acrossai_allowed_protected_meta', array() )", $src );
		$this->assertStringContainsString( 'is_protected_meta(', $src );
		$this->assertStringContainsString( "str_starts_with( \$key_str, '_' )", $src );
		$this->assertMatchesRegularExpression(
			"/in_array\(\s*\\\$key_str,\s*\\\$allowed,\s*true\s*\)/",
			$src
		);
	}

	// =========================================================================
	// FR-014, FR-015, FR-016 — Read_File guards
	// =========================================================================

	/**
	 * FR-014: read-file refuses wp-config.php and .htaccess at ABSPATH root
	 * with blocked_reason:protected_read.
	 *
	 * @return void
	 */
	public function test_fr014_read_file_refuses_protected_read_targets(): void {
		// Feature 092: read-file no longer REFUSES wp-config.php / .htaccess
		// outright — those files are now readable and Secret_Redactor scrubs
		// the sensitive content before the response leaves. The former
		// PROTECTED_FILES constant + protected_read guard were removed.
		// Assert the new posture instead.
		$src = $this->sources['read_file'];
		$this->assertStringNotContainsString( 'private const PROTECTED_FILES', $src, 'Feature 092 removes PROTECTED_FILES from Read_File.' );
		$this->assertStringNotContainsString( "'blocked_reason' => 'protected_read'", $src, 'Feature 092 removes the protected_read blocked_reason envelope.' );
		$this->assertStringContainsString( 'Secret_Redactor::scrub(', $src, 'Feature 092 routes read-file content through the secret redactor.' );
		$this->assertStringContainsString( 'Path_Allowlist_Guard::blocked_read_response(', $src, 'Feature 092 gates read-file with the read allowlist.' );
	}

	/**
	 * FR-015: read-file refuses when filesize exceeds MAX_READ_BYTES,
	 * before loading the file into memory.
	 *
	 * @return void
	 */
	public function test_fr015_read_file_caps_size_before_reading(): void {
		$src = $this->sources['read_file'];
		$this->assertStringContainsString( 'private const MAX_READ_BYTES', $src );
		$this->assertStringContainsString( '5242880', $src );
		$this->assertStringContainsString( "'blocked_reason' => 'file_too_large'", $src );
		// The size check must precede the read call. Feature 091 migrated
		// file_get_contents() → $fs->get_contents().
		$pos_check   = strpos( $src, 'MAX_READ_BYTES' );
		$pos_read    = strpos( $src, '$fs->get_contents(' );
		$this->assertNotFalse( $pos_check );
		$this->assertNotFalse( $pos_read );
		$this->assertLessThan(
			$pos_read,
			$pos_check,
			'Size cap must be checked before $fs->get_contents runs.'
		);
	}

	/**
	 * FR-016: read-file returns a distinct binary shape without raw bytes
	 * when mb_check_encoding UTF-8 fails.
	 *
	 * @return void
	 */
	public function test_fr016_read_file_detects_binary_content(): void {
		$src = $this->sources['read_file'];
		$this->assertStringContainsString( "mb_check_encoding( \$content, 'UTF-8' )", $src );
		$this->assertStringContainsString( "'binary'  => true", $src );
		$this->assertStringContainsString( "'message' => __( 'Binary file", $src );
	}

	// =========================================================================
	// FR-017, FR-018, FR-019, FR-020 — Delete_File guards + backup + opcache
	// =========================================================================

	/**
	 * FR-017: delete-file requires confirm:true and refuses with
	 * confirmation_required otherwise.
	 *
	 * @return void
	 */
	public function test_fr017_delete_file_requires_confirm(): void {
		$src = $this->sources['delete_file'];
		$this->assertMatchesRegularExpression(
			"/'required'\s*=>\s*array\(\s*'path',\s*'confirm'\s*\)/",
			$src
		);
		$this->assertStringContainsString( "'blocked_reason' => 'confirmation_required'", $src );
	}

	/**
	 * FR-018: delete-file refuses the protected files with
	 * blocked_reason:protected_write.
	 *
	 * @return void
	 */
	public function test_fr018_delete_file_refuses_protected_write_targets(): void {
		$src = $this->sources['delete_file'];
		$this->assertStringContainsString( 'private const PROTECTED_FILES', $src );
		$this->assertStringContainsString( "'wp-config.php'", $src );
		$this->assertStringContainsString( "'.htaccess'", $src );
		$this->assertStringContainsString( "'blocked_reason' => 'protected_write'", $src );
		$this->assertMatchesRegularExpression(
			"/in_array\(\s*basename\(\s*\\\$real\s*\),\s*self::PROTECTED_FILES,\s*true\s*\)/",
			$src
		);
	}

	/**
	 * FR-019: delete-file writes a pre-image backup before deleting and
	 * returns the backup path in the response.
	 *
	 * Feature 094 (2026-08-26) REPLACED the original inline
	 * `$real . '.bak.' . time()` scheme with a centralised backup dir
	 * managed by Audit_Trail — see specs/094-file-manager-audit-log/. The
	 * FR-019 intent (a pre-image is available on delete) is unchanged; only
	 * the storage location and the response field name evolved. Callers
	 * should read `response.backup_path` (canonical) — `response.backup`
	 * is populated for one transition release and will be removed.
	 *
	 * @return void
	 */
	public function test_fr019_delete_file_writes_timestamped_backup(): void {
		$src = $this->sources['delete_file'];
		// Feature 094: backup writer runs before delete.
		$this->assertStringContainsString( 'Audit_Trail::write_backup(', $src );
		// Canonical response field for the backup location.
		$this->assertStringContainsString( "'backup_path' => \$backup_path,", $src );
		// Deprecated `backup` field is mirrored from backup_path for one release.
		$this->assertMatchesRegularExpression( "/'backup'\\s*=>\\s*\\\$backup_path/", $src );
	}

	/**
	 * FR-020: delete-file invalidates OPcache when the extension is loaded,
	 * guarded by function_exists.
	 *
	 * @return void
	 */
	public function test_fr020_delete_file_invalidates_opcache_when_available(): void {
		$src = $this->sources['delete_file'];
		$this->assertMatchesRegularExpression(
			"/function_exists\(\s*'opcache_invalidate'\s*\)/",
			$src
		);
		$this->assertStringContainsString( 'opcache_invalidate( $real, true )', $src );
	}

	// =========================================================================
	// FR-021 — Delete_Post suggested_redirect
	// =========================================================================

	/**
	 * FR-021: delete-post includes suggested_redirect{from,to} only when the
	 * deleted post was publish and force:true was passed.
	 *
	 * @return void
	 */
	public function test_fr021_delete_post_suggests_redirect_on_published_force_delete(): void {
		$src = $this->sources['delete_post'];
		$this->assertStringContainsString( "\$was_publish = ( 'publish' === (string) \$post->post_status )", $src );
		$this->assertStringContainsString( '$permalink   = (string) get_permalink( $post )', $src );
		$this->assertStringContainsString( 'if ( $force && $was_publish )', $src );
		$this->assertStringContainsString( 'get_post_type_archive_link(', $src );
		$this->assertStringContainsString( "home_url( '/' )", $src );
		$this->assertStringContainsString( "'suggested_redirect'", $src );
		$this->assertMatchesRegularExpression(
			"/'from'\s*=>\s*\\\$permalink,\s*\n?\s*'to'\s*=>\s*\\\$target/",
			$src
		);
	}

	// =========================================================================
	// FR-022 — Refusal envelope contract
	// =========================================================================

	/**
	 * FR-022: every refusal path returns success:false + blocked_reason +
	 * message and never mutates state before returning. Verified structurally
	 * by asserting each blocked_reason literal appears alongside 'success' =>
	 * false in the same source file.
	 *
	 * @return void
	 */
	public function test_fr022_refusal_shape_is_uniform(): void {
		$expectations = array(
			'deactivate_plugin' => array( 'protected_plugin' ),
			'delete_media'      => array( 'confirmation_required' ),
			'update_post'       => array( 'non_writable_post_type', 'publish_cap_required', 'edit_others_posts_required' ),
			// Feature 092: 'protected_read' removed; reads now succeed with
			// Secret_Redactor scrubbing. Only the size-cap refusal remains.
			'read_file'         => array( 'file_too_large' ),
			'delete_file'       => array( 'confirmation_required', 'protected_write' ),
		);
		foreach ( $expectations as $key => $reasons ) {
			$src = $this->sources[ $key ];
			$this->assertStringContainsString( "'success'        => false", $src, "{$key} must return success=>false on refusal." );
			foreach ( $reasons as $reason ) {
				$this->assertStringContainsString(
					"'{$reason}'",
					$src,
					"{$key} must include blocked_reason '{$reason}'."
				);
			}
		}
	}

	// =========================================================================
	// FR-023 — Permission callback unchanged
	// =========================================================================

	/**
	 * FR-023: every modified ability continues to gate on
	 * current_user_can('manage_options') via the identical permission
	 * callback closure.
	 *
	 * @return void
	 */
	public function test_fr023_permission_callback_unchanged_on_every_ability(): void {
		$literal = "'permission_callback' => static function (): bool {\n\t\t\t\t\treturn current_user_can( 'manage_options' );\n\t\t\t\t},";
		foreach ( $this->sources as $key => $src ) {
			$this->assertStringContainsString(
				$literal,
				$src,
				"{$key} permission_callback must remain the literal manage_options closure."
			);
		}
	}
}
