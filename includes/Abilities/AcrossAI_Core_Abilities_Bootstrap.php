<?php
/**
 * Orchestrator for the absorbed acrossai-core-abilities runtime.
 *
 * Wired from Main.php::define_public_hooks(). Two public entry points:
 * - register_category_callbacks( AcrossAI_Loader $loader ): adds 17 loader
 *   actions on wp_abilities_api_categories_init, one per Category_Registrar.
 * - register_abilities(): instantiates the 176 absorbed ability classes and
 *   runs the three companion-Main.php extras (Cron_Helpers::register_filter,
 *   Upload_Media chunk-sweep cron). Wired to plugins_loaded @ P20 matching
 *   the companion's original hook point.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities;

use AcrossAI_Abilities_Manager\Includes\AcrossAI_Loader;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Cron_Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap singleton for the absorbed ability inventory (Feature 046).
 */
final class AcrossAI_Core_Abilities_Bootstrap {

	/**
	 * Singleton reference.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Get the singleton instance.
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
	 * Private constructor — access via instance().
	 */
	private function __construct() {}

	/**
	 * Register the 17 category-registrar callbacks with the manager Loader.
	 *
	 * Called from Main.php::define_public_hooks() so every add_action
	 * literally traces back to Main.php (AC-HOOKS-MAIN literalism).
	 *
	 * @param AcrossAI_Loader $loader Manager hook loader.
	 * @return void
	 */
	public function register_category_callbacks( AcrossAI_Loader $loader ): void {
		$loader->add_action( 'wp_abilities_api_categories_init', Plugins\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Themes\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', FileManager\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Cache\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Database\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Users\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Block\Category_Registrar::instance(), 'register' );
		// Feature 067 — Elementor category (self-guards on class_exists inside register()).
		$loader->add_action( 'wp_abilities_api_categories_init', Elementor\Category_Registrar::instance(), 'register' );
		// Feature 069 — Rank Math category (self-guards on class_exists inside register()).
		$loader->add_action( 'wp_abilities_api_categories_init', RankMath\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Settings\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Fonts\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Content\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Taxonomies\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Media\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Comments\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Menus\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Options\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Cron\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', SiteHealth\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', Core\Category_Registrar::instance(), 'register' );
		// Feature 055 — two new categories.
		$loader->add_action( 'wp_abilities_api_categories_init', AdminMenu\Category_Registrar::instance(), 'register' );
		$loader->add_action( 'wp_abilities_api_categories_init', ContentSearch\Category_Registrar::instance(), 'register' );
		// Feature 059 — Recovery Mode / fatal-error abilities.
		$loader->add_action( 'wp_abilities_api_categories_init', Recovery\Category_Registrar::instance(), 'register' );
		// Feature 063 — Widgets category.
		$loader->add_action( 'wp_abilities_api_categories_init', Widgets\Category_Registrar::instance(), 'register' );
	}

	/**
	 * Instantiate the 176 absorbed ability classes so their inherited
	 * Ability_Definition constructor hooks acrossai_abilities_api_init.
	 *
	 * Also runs the three extras the companion Main.php ran alongside the
	 * ability instantiations: Cron_Helpers filter registration, the
	 * Upload_Media chunk-sweep hook, and the chunk-sweep cron scheduler.
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		// Default class_exists() autoloading is REQUIRED here — the composer
		// autoloader hasn't necessarily resolved Ability_Definition yet at
		// plugins_loaded @ P20. Passing false as the second arg would skip
		// autoload and cause a silent no-op (Feature 046 regression bug found
		// via the live Library page showing "No abilities registered yet"
		// while all 176 classes were on disk).
		if ( ! class_exists( '\AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition' ) ) {
			return;
		}

		new Plugins\Activate_Plugin();
		new Plugins\Deactivate_Plugin();
		new Plugins\Install_Plugin();
		new Plugins\Update_Plugin();
		new Plugins\List_Plugins();
		new Plugins\Check_Plugin_Updates();
		// Feature 064 — plugin lifecycle & integrity (3).
		new Plugins\Search_Wp_Plugin_Directory();
		new Plugins\Uninstall_Plugin();
		new Plugins\Verify_Plugin_Checksums();
		new Settings\Get_Permalink_Structure();
		new Settings\Set_Permalink_Structure();
		new Settings\Flush_Permalink_Structure();
		new Settings\Get_Site_Title();
		new Settings\Update_Site_Title();
		new Settings\Get_Tagline();
		new Settings\Update_Tagline();
		new Settings\Update_Site_Logo();
		new Settings\Get_Site_Icon();
		new Settings\Update_Site_Icon();
		// Feature 063 — Rewrite rules read.
		new Settings\List_Rewrite_Rules();
		new Themes\Activate_Theme();
		new Themes\Delete_Theme();
		new Themes\Install_Theme();
		new Themes\Update_Theme();
		new Themes\List_Themes();
		new Users\Get_User();
		new Users\List_Users();
		new Users\Create_User();
		new Users\Update_User();
		new Users\Delete_User();
		new Users\Reset_User_Password();
		new Users\List_User_Roles();
		new Users\Get_Role_Capabilities();
		// Feature 062 — role & capability CRUD.
		new Users\Add_Role_Capability();
		new Users\Remove_Role_Capability();
		new Users\Create_Role();
		new Users\Delete_Role();
		new Users\Reset_Role();
		new Users\Add_User_Capability();
		new Users\Remove_User_Capability();
		new Cache\Flush_Object_Cache();
		new Cache\Flush_Transients();
		new Cache\Flush_Rewrite_Rules();
		// Feature 064 — transient CRUD (4).
		new Cache\Get_Transient();
		new Cache\List_Transients();
		new Cache\Delete_Transient();
		new Cache\Delete_Expired_Transients();
		new Database\Extract_Db_Schema();
		new Database\Run_Db_Select_Query();
		new Database\Insert_Db_Row();
		new Database\Update_Db_Rows();
		new Database\Delete_Db_Rows();
		new Database\List_Db_Tables();
		new Database\Explain_Db_Query();
		new Database\Get_Db_Stats();
		new Database\Optimize_Db_Tables();
		// Feature 062 — serialized-safe site-wide search-replace.
		new Database\Search_Replace();
		// Feature 063 — Database introspection.
		new Database\Get_Db_Prefix();
		// Feature 086 — database health audits + safe writes.
		new Database\Audit_Health();
		new Database\Audit_Index_Health();
		new Database\Audit_Options_Health();
		new Database\Cleanup_Expired_Transients();
		new Database\Set_Option_Autoload();
		// Feature 087 — core-table engine audit + InnoDB conversion.
		new Database\Audit_Core_Table_Engines();
		new Database\Convert_Core_Tables_To_Innodb();
		new FileManager\Read_File();
		new FileManager\Create_File();
		new FileManager\Edit_File();
		new FileManager\Delete_File();
		new Plugins\Read_Plugin_Structure();
		new Plugins\Read_Plugin_Code();
		new Plugins\Manage_Plugin_Files();
		new Themes\Read_Theme_Structure();
		new Themes\Read_Theme_Code();
		new Themes\Edit_Theme_File();
		new FileManager\Read_Wp_Config();
		new FileManager\Edit_Wp_Config();
		new FileManager\Read_Debug_Log();
		new FileManager\Clear_Debug_Log();
		new FileManager\Create_Zip_Backup();
		new FileManager\Upload_Zip_Backup();
		new FileManager\Extract_Zip_Backup();
		new FileManager\Download_Zip_Backup();
		new FileManager\List_Zip_Backups();
		new FileManager\Delete_Zip_Backup();
		// Feature 063 — wp-config constant read.
		new FileManager\Get_Wp_Config_Constant();
		new Block\List_Block_Patterns();
		new Block\Read_Block_Pattern();
		new Block\Create_Block_Pattern();
		new Block\Update_Block_Pattern();
		new Block\Delete_Block_Pattern();
		new Block\List_Block_Templates();
		new Block\Read_Block_Template();
		new Block\Create_Block_Template();
		new Block\Update_Block_Template();
		new Block\Delete_Block_Template();
		new Block\List_Global_Styles();
		new Block\Read_Global_Style();
		new Block\Create_Global_Style();
		new Block\Update_Global_Style();
		new Block\Delete_Global_Style();
		new Block\Read_Theme_Json();
		new Block\Update_Theme_Json();
		new Block\List_Block_Style_Variations();
		new Block\Read_Block_Style_Variation();
		new Block\Create_Block_Style_Variation();
		new Block\Update_Block_Style_Variation();
		new Block\Delete_Block_Style_Variation();
		new Block\List_Blocks();
		new Block\Read_Block();
		new Block\List_Block_Template_Parts();
		new Block\Read_Block_Template_Part();
		new Block\Create_Block_Template_Part();
		new Block\Update_Block_Template_Part();
		new Block\Delete_Block_Template_Part();
		new Fonts\List_Font_Families();
		new Fonts\Get_Font_Family();
		new Fonts\Create_Font_Family();
		new Fonts\Delete_Font_Family();
		new Fonts\List_Font_Faces();
		new Fonts\Get_Font_Face();
		new Fonts\Create_Font_Face();
		new Fonts\Delete_Font_Face();
		new Content\Create_Post();
		new Content\Get_Post();
		new Content\List_Post_Revisions();
		new Content\List_Posts();
		new Content\Update_Post();
		new Content\Delete_Post();
		new Content\Get_Post_Meta();
		new Content\Update_Post_Meta();
		new Content\Delete_Post_Meta();
		// Feature 064 — post-meta append (1).
		new Content\Add_Post_Meta();
		new Content\Create_Page();
		new Content\Get_Page();
		new Content\List_Page_Revisions();
		new Content\List_Pages();
		new Content\Update_Page();
		new Content\List_Post_Types();
		new Content\Create_Cpt_Item();
		new Content\Get_Cpt_Item();
		new Content\List_Cpt_Item_Revisions();
		new Content\List_Cpt_Items();
		new Content\Update_Cpt_Item();
		new Content\Delete_Cpt_Item();
		new Content\List_Post_Translations();
		new Content\Set_Post_Language();
		new Content\Link_Post_Translation();
		new Content\List_Jet_Engine_Options_Pages();
		new Content\Get_Jet_Engine_Options_Page();
		new Content\Update_Jet_Engine_Options_Page_Field();
		new Taxonomies\List_Taxonomies();
		new Taxonomies\Get_Taxonomy();
		new Taxonomies\List_Cpt_Taxonomies();
		new Taxonomies\List_Terms();
		new Taxonomies\Get_Term();
		new Taxonomies\Create_Term();
		new Taxonomies\Update_Term();
		new Taxonomies\Delete_Term();
		new Taxonomies\Assign_Cpt_Terms();
		new Media\Upload_Media();
		new Media\Get_Media();
		new Media\List_Media();
		new Media\Update_Media();
		new Media\Delete_Media();
		new Media\Get_Media_Meta();
		new Media\Update_Media_Meta();
		new Media\List_Upload_Mime_Types();
		new Media\Update_Upload_Mime_Types();
		new Comments\Create_Comment();
		new Comments\Get_Comment();
		new Comments\List_Comments();
		new Comments\Update_Comment();
		new Comments\Delete_Comment();
		new Comments\Approve_Comment();
		new Comments\Unapprove_Comment();
		new Comments\Mark_As_Spam();
		new Comments\Get_Comment_Meta();
		new Comments\Update_Comment_Meta();
		new Menus\List_Menus();
		new Menus\Get_Menu();
		new Menus\Create_Menu();
		new Menus\Update_Menu();
		new Menus\Delete_Menu();
		new Menus\List_Menu_Items();
		new Menus\Get_Menu_Item();
		new Menus\Create_Menu_Item();
		new Menus\Update_Menu_Item();
		new Menus\Delete_Menu_Item();
		// Feature 063 — Widgets category (2 abilities).
		new Widgets\List_Widgets();
		new Widgets\List_Sidebars();
		new Options\Get_Option();
		new Options\Update_Option();
		new Options\Delete_Option();
		new Options\List_Options();
		new Options\Search_Options();
		// Feature 064 — nested option access (2).
		new Options\Get_Nested_Option_Value();
		new Options\Patch_Option_Value();
		new Cron\List_Cron_Jobs();
		new Cron\Get_Cron_Job();
		new Cron\Get_Next_Cron_Run();
		new Cron\Check_Cron_Job_Exists();
		new Cron\List_Cron_Schedules();
		new Cron\Get_Cron_Schedule();
		new Cron\Get_Cron_Status();
		new Cron\List_Overdue_Cron_Jobs();
		new Cron\Create_Cron_Job();
		new Cron\Update_Cron_Job();
		new Cron\Run_Cron_Job_Now();
		new Cron\Create_Cron_Schedule();
		new Cron\Delete_Cron_Job();
		new Cron\Delete_Cron_Jobs_By_Hook();
		new Cron\Delete_Cron_Schedule();
		// Feature 063 — Cron endpoint reachability probe.
		new Cron\Test_Wp_Cron();
		new SiteHealth\Get_Site_Health_Status();
		new SiteHealth\Get_Site_Health_Info();
		new Core\Check_Wp_Core_Update();
		new Core\Update_Wp_Core();
		new Core\Rollback_Wp_Core();
		new Core\Reinstall_Wp_Core();
		// Feature 063 — Core introspection.
		new Core\Get_Wp_Version();
		// Feature 064 — core integrity (1).
		new Core\Verify_Core_Checksums();

		// Feature 055 — 31 new abilities across 10 domains.
		new Users\Get_Current_User_Access();
		new Taxonomies\Set_Term_Image();
		new Comments\Bulk_Update_Comments();
		// Feature 063 — Comment counts read.
		new Comments\Get_Comment_Count();
		new Media\Rename_Media_File();
		// Feature 063 — Image sizes read.
		new Media\List_Image_Sizes();
		new Menus\Get_Navigation_Context();
		new Menus\List_Navigation_Locations();
		new Content\Update_Post_Block();
		// Feature 066 — Block tree mutation & nested editing.
		new Content\Get_Post_Blocks();
		new Content\Add_Block();
		new Content\Remove_Block();
		new Content\Duplicate_Block();
		new Content\Move_Block();
		new Content\Insert_Pattern();
		new Content\Inspect_Post_Autosaves();
		new Block\Get_Site_Editor_Context();
		new Block\Refresh_Site_Editor_Context();
		new Block\List_Reusable_Blocks();
		new Block\List_Block_Areas();
		// Feature 070 — block-editor site-context reads.
		new Block\Get_Style_Guide();
		new Block\Get_Style_Book();
		new Block\Get_Site_Editor_Summary();
		new Block\Get_Site_Editor_References();
		new Block\List_Block_Categories();
		// Feature 071 — reusable-block writes & wp_navigation entities.
		new Block\Read_Reusable_Block();
		new Block\Create_Reusable_Block();
		new Block\Update_Reusable_Block();
		new Block\Extract_Reusable_Block();
		new Block\Insert_Reusable_Block_Into_Post();
		new Block\List_Navigations();
		new Block\Read_Navigation();
		new Block\Create_Navigation();
		new Block\Update_Navigation();
		// Feature 072 — usage lookups & advanced block-tree mutation.
		new Block\Find_Navigation_Usage();
		new Block\Find_Template_Part_Usage();
		new Block\Find_Reusable_Block_Usage();
		new Block\Mutate_Block_Tree();
		new Block\Transform_Blocks();
		new Block\Replace_Block_Text();
		new Block\Normalize_Heading_Levels();
		// Feature 073 — block/template locking + block bindings.
		new Block\Set_Block_Lock();
		new Block\Set_Allowed_Blocks();
		new Block\Set_Template_Lock();
		new Block\Read_Block_Bindings();
		new Block\Set_Block_Bindings();
		// Feature 074 — parse / serialize primitives + content-quality analysis.
		new Block\Parse_Content();
		new Block\Serialize_Blocks();
		new Block\Validate_Content();
		new Block\Audit_Content();
		new Block\Analyze_Content();
		new Block\Evaluate_Design();
		new Block\Suggest_Design_Fixes();
		new Block\Evaluate_Copy();
		new Block\Suggest_Copy_Fixes();
		new Block\Evaluate_Render_Context();
		// Feature 075 — block-editor generation, recipes & authoring guidance.
		new Block\Get_Block_Guidance();
		new Block\List_Page_Recipes();
		new Block\List_Section_Recipes();
		new Block\List_Query_Section_Recipes();
		new Block\Generate_Landing_Page();
		new Block\Generate_Section();
		new Block\Generate_Query_Section();
		new Block\Create_Page_From_Blocks();
		new Block\Create_Page_From_Pattern();
		new Block\Create_Landing_Page();
		new SiteHealth\Get_Site_Maintenance_Report();
		// Feature 063 — Maintenance-mode status read.
		new SiteHealth\Get_Maintenance_Mode_Status();
		// Site maintenance-mode toggle (write) — native WP-core .maintenance marker.
		new SiteHealth\Set_Site_Maintenance_Mode();
		new SiteHealth\Unset_Site_Maintenance_Mode();
		new Plugins\Get_Plugin_Lifecycle_Context();
		new Themes\Get_Theme_Lifecycle_Context();
		// Feature 063 — Theme mods introspection.
		new Themes\List_Theme_Mods();
		new AdminMenu\Get_Admin_Menu_Context();
		new AdminMenu\Refresh_Admin_Menu_Context();
		new AdminMenu\List_Admin_Menu_Pages();
		new AdminMenu\Get_Admin_Menu_Navigation_Target();
		new AdminMenu\List_Admin_Settings();
		new ContentSearch\Refresh_Content_Index_Batch();
		new ContentSearch\Search_Content_Items();
		new ContentSearch\Search_Content_Chunks();
		new ContentSearch\Find_Related_Content();
		new ContentSearch\Find_Internal_Links();
		new ContentSearch\Get_Internal_Link_Policy();
		new ContentSearch\Create_Internal_Link_Suggestions();
		new ContentSearch\List_Internal_Link_Suggestions();
		new ContentSearch\Review_Internal_Link_Suggestion();
		new ContentSearch\Apply_Internal_Link_Suggestion();
		new ContentSearch\Audit_Internal_Links();

		// Feature 059 — Recovery Mode / fatal-error abilities.
		new Recovery\Get_Recovery_Mode_Status();
		new Recovery\List_Paused_Plugins();
		new Recovery\List_Paused_Themes();
		new Recovery\Get_Recovery_Exit_Url();
		new Recovery\Unpause_Plugin();
		new Recovery\Unpause_Theme();
		new Recovery\List_Recent_Fatal_Errors();

		// Feature 061 — Debugging / Conflict Testing abilities.
		new Debugging\List_Plugins();
		new Debugging\Get_Overrides();
		new Debugging\Set_Override();
		new Debugging\Clear_Overrides();
		new Debugging\Deploy_Mu_Plugin();
		new Debugging\Remove_Mu_Plugin();
		new Debugging\Bulk_Set_Overrides();

		// Feature 055 — register the option-backed lifecycle event log.
		Utilities\Lifecycle_Event_Log::register_hooks();

		// Extras the companion Main.php also ran alongside the ability
		// instantiations. See docs/planning/046-absorb-core-abilities-into-manager.md
		// CHANGE-4c.
		Cron_Helpers::register_filter();
		add_action( Media\Upload_Media::CHUNK_SWEEP_HOOK, array( Media\Upload_Media::class, 'sweep_chunk_sessions' ) );
		Media\Upload_Media::register_sweep_cron();

		// Feature 041: Upload_Zip_Backup chunk sweeper — same shape as Upload_Media.
		add_action( FileManager\Upload_Zip_Backup::CHUNK_SWEEP_HOOK, array( FileManager\Upload_Zip_Backup::class, 'sweep_chunk_sessions' ) );
		FileManager\Upload_Zip_Backup::register_sweep_cron();

		// Site maintenance-mode toggle — 5-min cron schedule + refresh callback.
		add_filter( 'cron_schedules', array( SiteHealth\Set_Site_Maintenance_Mode::class, 'register_cron_schedule' ) );
		add_action( SiteHealth\Set_Site_Maintenance_Mode::CRON_HOOK, array( SiteHealth\Set_Site_Maintenance_Mode::class, 'refresh_marker' ) );

		// Feature 067 — Elementor ability suite (up to 88 abilities under
		// elementor/*). Gated on Elementor presence; the Pro-only
		// subset (Custom Code CRUD + Form Submissions) is additionally gated
		// on Elementor Pro. See specs/067-elementor-abilities/plan.md.
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$this->register_elementor_free_abilities();
			if ( class_exists( '\ElementorPro\Plugin' ) || defined( 'ELEMENTOR_PRO_VERSION' ) ) {
				$this->register_elementor_pro_abilities();
			}
		}

		// Feature 069 — Rank Math ability suite (up to 61 abilities under
		// rank-math/*). Gated on Rank Math presence only.
		//
		// DELIBERATE DIVERGENCE from the Elementor block above: there is no
		// second entitlement-gated registration method. Rank Math's Content AI
		// and AI Visibility abilities are registered UNCONDITIONALLY and gate at
		// runtime inside execute(), returning rank_math_account_required or
		// content_ai_no_credits. This is a product decision, not an oversight —
		// unlike Elementor Pro, those features ship in Rank Math free core and
		// gate on cloud-account registration plus a credit balance rather than on
		// a separate plugin, so their availability can change without a plugin
		// activation and cannot be decided at registration time.
		// Do not "fix" this into the Elementor shape.
		// See specs/069-rank-math-abilities/research.md F7.
		if ( class_exists( '\RankMath\Helper' ) ) {
			$this->register_rank_math_abilities();
		}
	}

	/**
	 * Feature 069 — instantiate the Rank Math ability classes.
	 *
	 * Each `new RankMath\<Class>()` line is added as the corresponding ability
	 * class lands across Batches 1-7 of
	 * specs/069-rank-math-abilities/tasks.md.
	 *
	 * @return void
	 */
	private function register_rank_math_abilities(): void {
		// Batch 1 — status / diagnostics.
		new RankMath\Get_Status();

		// Batch 2 — typed settings. One reader for all 20 panels, one writer per
		// Rank Math option blob, plus the two blobs that need their own ability.
		new RankMath\Get_Settings();
		new RankMath\Update_General_Settings();
		new RankMath\Update_Title_Settings();
		new RankMath\Update_Sitemap_Settings();
		new RankMath\Update_Instant_Indexing_Settings();
		new RankMath\Update_Robots_Txt();

		// Batch 3 — Instant Indexing.
		new RankMath\Submit_Urls();
		new RankMath\Get_Indexing_Log();
		new RankMath\Clear_Indexing_Log();
		new RankMath\Reset_Indexing_Key();

		// Batch 3 — module state.
		new RankMath\List_Modules();
		new RankMath\Set_Module_State();

		// Batch 3 — sitemap operations.
		new RankMath\Get_Sitemap_Status();
		new RankMath\List_Sitemap_Urls();
		new RankMath\Invalidate_Sitemap_Cache();

		// Batch 3 — virtual routes.
		new RankMath\Get_Llms_Status();
		new RankMath\Refresh_Llms_Route();

		// Batch 4 — redirections. Reads first, then writes; hard delete is
		// separate from the reversible status transitions so each can declare its
		// annotations honestly.
		new RankMath\List_Redirections();
		new RankMath\Find_Redirection();
		new RankMath\Get_Redirection_Stats();
		new RankMath\Export_Redirections();
		new RankMath\Create_Redirection();
		new RankMath\Update_Redirection();
		new RankMath\Change_Redirection_Status();
		new RankMath\Delete_Redirections();
		new RankMath\Delete_Trashed_Redirections();

		// Batch 4 — 404 monitor log.
		new RankMath\List_404_Logs();
		new RankMath\Delete_404_Logs();

		// Batch 4 — role capabilities. Read and reset only: no bulk writer, because
		// Helper::set_capabilities() strips capabilities from omitted roles. Grants
		// go through the plugin's existing users/add-role-capability.
		new RankMath\Get_Role_Capabilities();
		new RankMath\Reset_Role_Capabilities();

		// Batch 5 — status, maintenance tools, backups, import/export.
		new RankMath\Run_Maintenance_Tool();
		new RankMath\Export_Settings();
		new RankMath\Import_Settings();
		new RankMath\List_Backups();
		new RankMath\Create_Backup();
		new RankMath\Manage_Backup();
		new RankMath\Detect_Seo_Plugins();
		new RankMath\Get_Seo_Analysis_Results();

		// Batch 6 — analytics.
		new RankMath\Get_Analytics_Summary();
		new RankMath\Get_Analytics_Rows();
		new RankMath\Get_Index_Status();
		new RankMath\Inspect_Url();

		// Batch 6 — post-level content and schema.
		new RankMath\Update_Seo_Meta();
		new RankMath\Bulk_Update_Meta();
		new RankMath\Update_Seo_Scores();
		new RankMath\Get_Primary_Term();
		new RankMath\Update_Primary_Term();
		new RankMath\Update_Post_Schemas();
		new RankMath\Delete_Post_Schemas();
		new RankMath\Get_Schema_Status();
		new RankMath\Get_Rendered_Head();
		new RankMath\Audit_Content_Seo();
		new RankMath\Get_Inbound_Links();
		new RankMath\Audit_Faq_Links();

		// Batch 7 — entitlement-gated. Registered UNCONDITIONALLY and gated at
		// runtime; see the block comment above register_rank_math_abilities().
		new RankMath\Get_Content_Ai_Status();
		new RankMath\Manage_Content_Ai_Prompts();
		new RankMath\Manage_Content_Ai_Output();
		new RankMath\Research_Keyword();
		new RankMath\Get_Ai_Visibility_Brand();
		new RankMath\Update_Ai_Visibility_Object();
	}

	/**
	 * Feature 067 — instantiate the free-Elementor ability classes.
	 *
	 * Each `new Elementor\<Class>()` line is added as the corresponding
	 * ability class lands in Phases 3-12 of tasks.md.
	 *
	 * @return void
	 */
	private function register_elementor_free_abilities(): void {
		// Group 3 — discovery / guidance.
		new Elementor\Get_Widget_Controls();
		// Group 1 — document / element operations.
		new Elementor\Get_Data();
		new Elementor\Get_Element();
		new Elementor\Find_Elements();
		new Elementor\Update_Element();
		new Elementor\Add_Container();
		new Elementor\Add_Widget();
		new Elementor\Merge_Element_Settings();
		new Elementor\Delete_Element();
		new Elementor\Remove_Element();
		new Elementor\Move_Element();
		new Elementor\Duplicate_Element();
		new Elementor\Reorder_Elements();
		// Document-scoped ops.
		new Elementor\Create_Page();
		new Elementor\Update_Page_Settings();
		new Elementor\Update_Data();
		new Elementor\Patch_Data();
		new Elementor\Clone_Data();
		// Widget shortcuts.
		new Elementor\Add_Heading();
		new Elementor\Add_Text_Editor();
		new Elementor\Add_Image();
		new Elementor\Add_Button();
		new Elementor\Add_Post_Tabs();
		// System & maintenance.
		new Elementor\Clear_Cache();
		new Elementor\Replace_Urls();
		new Elementor\Get_Maintenance_Mode();
		new Elementor\Update_Maintenance_Mode();
		// Theme Builder.
		new Elementor\Get_Theme_Builder_Conditions();
		new Elementor\Update_Theme_Builder_Conditions();
		// Discovery / guidance.
		new Elementor\Get_Official_Widget_Catalog();
		new Elementor\Get_Official_Pattern_Guidance();
		new Elementor\Get_Theme_Context();
		new Elementor\Get_Style_Guide();
		new Elementor\Evaluate_Render_Context();
		// Templates (Group 4 — full CRUD).
		new Elementor\List_Templates();
		new Elementor\Get_Template();
		new Elementor\Create_Template();
		new Elementor\Update_Template();
		new Elementor\Delete_Template();
		new Elementor\Restore_Template();
		new Elementor\Duplicate_Template();
		new Elementor\Empty_Trash();
		new Elementor\Export_Template();
		new Elementor\Import_Template();
		new Elementor\Find_Template_For_Pattern();
		// Kits & site settings.
		new Elementor\List_Kits();
		new Elementor\Get_Kit_Settings();
		new Elementor\Update_Kit_Settings();
		new Elementor\Set_Active_Kit();
		new Elementor\List_Global_Widgets();
		new Elementor\List_Experiments();
		new Elementor\Update_Experiment();
		// Design audits — aggregators + scorers.
		new Elementor\Evaluate_Design();
		new Elementor\Suggest_Design_Fixes();
		new Elementor\Score_Distinctiveness();
		new Elementor\Extract_Design_Tokens();
		// Design audits — 14 audit-* abilities.
		new Elementor\Audit_Column_Alignment_Rhythm();
		new Elementor\Audit_Column_Balance();
		new Elementor\Audit_Column_Dominance();
		new Elementor\Audit_Column_Necessity();
		new Elementor\Audit_Column_Patterns();
		new Elementor\Audit_Composition_Rhythm();
		new Elementor\Audit_Emphasis_Drift();
		new Elementor\Audit_Generic_Component_Repetition();
		new Elementor\Audit_Generic_Layout_Patterns();
		new Elementor\Audit_Layout_Mechanism_Fit();
		new Elementor\Audit_Native_Widget_Opportunities();
		new Elementor\Audit_Section_Rivalry();
		new Elementor\Audit_Separator_Discipline();
		new Elementor\Audit_Surface_Overuse();
		// Design audits — 7 subtree operations.
		new Elementor\Apply_Text_Hierarchy();
		new Elementor\Enforce_Boundary_Coherence();
		new Elementor\Fix_Visible_Gap_Rhythm();
		new Elementor\Normalize_Responsive_Values();
		new Elementor\Normalize_Section_Spacing_Rhythm();
		new Elementor\Reset_Negative_Margins_Subtree();
		new Elementor\Zero_Container_Padding_Subtree();
		// Design audits — 4 copy/sync/convert helpers.
		new Elementor\Copy_Lane_Settings();
		new Elementor\Copy_Row_Balance();
		new Elementor\Image_Widget_To_Background_Container();
		new Elementor\Sync_Component_Variant();
	}

	/**
	 * Feature 067 — instantiate the Elementor Pro-gated ability classes.
	 *
	 * @return void
	 */
	private function register_elementor_pro_abilities(): void {
		// Custom Code CRUD (elementor_snippet CPT).
		new Elementor\List_Custom_Code();
		new Elementor\Get_Custom_Code();
		new Elementor\Create_Custom_Code();
		new Elementor\Update_Custom_Code();
		new Elementor\Delete_Custom_Code();
		// Form Submissions (Elementor Pro's e_submissions table).
		new Elementor\List_Form_Submissions();
		new Elementor\Get_Form_Submission();
		new Elementor\Delete_Form_Submission();
	}
}
