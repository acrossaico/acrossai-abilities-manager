# Ability inventory

**Generated file — do not edit by hand.** Regenerate with:

```sh
php scripts/generate-abilities-inventory.php
```

Snapshot taken 2026-09-12. **Total abilities:** 537 across 27 topic namespaces.

Conditional integrations (Elementor, Rank Math, ACF) are listed here whether or not their
host plugin is active on any given site — this is a source inventory, not a runtime one.

## Groups

Tabs on the Ability Integrations screen. `tab_group` is assigned per ability, so a category
may legitimately span two groups (Feature 101, `DEC-ABILITY-GROUP-TAXONOMY`).

| Group | Abilities |
|---|---:|
| `content` — Content | 71 |
| `appearance` — Appearance | 64 |
| `elementor` — Elementor | 62 |
| `litespeed-cache` — Litespeed Cache | 61 |
| `rank-math` — Rank Math | 61 |
| `blocks` — Blocks | 52 |
| `contact-form-7` — Contact Form 7 | 25 |
| `updates` — Updates | 23 |
| `files` — Files | 23 |
| `diagnostics` — Diagnostics | 20 |
| `configuration` — Configuration | 19 |
| `database` — Database | 17 |
| `cron` — Cron | 16 |
| `users` — Users | 16 |
| `cache` — Cache | 7 |

## Namespaces

| Namespace | Count | WP category slug |
|---|---:|---|
| `acrossai/` | 7 | `acrossai-debugging` |
| `admin-menu/` | 5 | `acrossai-admin-menu` |
| `blocks/` | 87 | `acrossai-block` |
| `cache/` | 7 | `acrossai-cache` |
| `comments/` | 12 | `acrossai-comments` |
| `contact-form-7/` | 25 | `acrossai-contact-form-7` |
| `content-search/` | 11 | `acrossai-content-search` |
| `content/` | 29 | `acrossai-content` |
| `core/` | 6 | `acrossai-core` |
| `cron/` | 16 | `acrossai-cron` |
| `database/` | 18 | `acrossai-database` |
| `elementor/` | 62 | `acrossai-elementor` |
| `file-manager/` | 23 | `acrossai-file-manager` |
| `fonts/` | 8 | `acrossai-fonts` |
| `litespeed/` | 61 | `acrossai-litespeed-cache`, `cache-exc_cat` |
| `media/` | 11 | `acrossai-media` |
| `menus/` | 12 | `acrossai-menus` |
| `options/` | 7 | `acrossai-options` |
| `plugins/` | 10 | `acrossai-plugins` |
| `rank-math/` | 61 | `acrossai-rank-math` |
| `recovery/` | 7 | `acrossai-recovery` |
| `settings/` | 11 | `acrossai-settings` |
| `site-health/` | 6 | `acrossai-site-health` |
| `taxonomies/` | 10 | `acrossai-taxonomies` |
| `themes/` | 7 | `acrossai-themes` |
| `users/` | 16 | `acrossai-users` |
| `widgets/` | 2 | `acrossai-widgets` |

## Every ability

| Namespace | Slug | Family | Sub-group | Label |
|---|---|---|---|---|
| `acrossai/` | `acrossai/conflict-test-bulk-set-overrides` | diagnostics | conflict-testing | Bulk Set Conflict-Test Overrides |
| `acrossai/` | `acrossai/conflict-test-clear-overrides` | diagnostics | conflict-testing | Clear Conflict-Test Overrides |
| `acrossai/` | `acrossai/conflict-test-deploy-mu-plugin` | diagnostics | conflict-testing | Deploy Conflict-Test Mu-Plugin |
| `acrossai/` | `acrossai/conflict-test-get-overrides` | diagnostics | conflict-testing | Get Conflict-Test Overrides |
| `acrossai/` | `acrossai/conflict-test-list-plugins` | diagnostics | conflict-testing | List Plugins (Conflict Testing) |
| `acrossai/` | `acrossai/conflict-test-remove-mu-plugin` | diagnostics | conflict-testing | Remove Conflict-Test Mu-Plugin |
| `acrossai/` | `acrossai/conflict-test-set-override` | diagnostics | conflict-testing | Set Conflict-Test Override |
| `admin-menu/` | `admin-menu/get-admin-menu-context` | configuration | admin-menu | Get Admin Menu Context |
| `admin-menu/` | `admin-menu/get-admin-menu-navigation-target` | configuration | admin-menu | Get Admin Menu Navigation Target |
| `admin-menu/` | `admin-menu/list-admin-menu-pages` | configuration | admin-menu | List Admin Menu Pages |
| `admin-menu/` | `admin-menu/list-admin-settings` | configuration | admin-menu | List Admin Settings |
| `admin-menu/` | `admin-menu/refresh-admin-menu-context` | configuration | admin-menu | Refresh Admin Menu Context |
| `blocks/` | `blocks/add-block` | blocks | post-blocks | Add Block |
| `blocks/` | `blocks/analyze-content` | blocks | analysis | Analyze Content |
| `blocks/` | `blocks/audit-content` | blocks | analysis | Audit Content |
| `blocks/` | `blocks/create-block-pattern` | blocks | patterns | Create Block Pattern |
| `blocks/` | `blocks/create-block-style-variation` | appearance | block-style-variations | Create Block Style Variation |
| `blocks/` | `blocks/create-block-template` | appearance | templates | Create Block Template |
| `blocks/` | `blocks/create-block-template-part` | appearance | template-parts | Create Block Template Part |
| `blocks/` | `blocks/create-global-style` | appearance | global-styles | Create Global Style |
| `blocks/` | `blocks/create-landing-page` | blocks | content | Create Landing Page |
| `blocks/` | `blocks/create-navigation` | appearance | site-editor | Create Navigation |
| `blocks/` | `blocks/create-page-from-blocks` | blocks | content | Create Page From Blocks |
| `blocks/` | `blocks/create-page-from-pattern` | blocks | content | Create Page From Pattern |
| `blocks/` | `blocks/create-reusable-block` | blocks | patterns | Create Reusable Block |
| `blocks/` | `blocks/delete-block-pattern` | blocks | patterns | Delete Block Pattern |
| `blocks/` | `blocks/delete-block-style-variation` | appearance | block-style-variations | Delete Block Style Variation |
| `blocks/` | `blocks/delete-block-template` | appearance | templates | Delete Block Template |
| `blocks/` | `blocks/delete-block-template-part` | appearance | template-parts | Delete Block Template Part |
| `blocks/` | `blocks/delete-global-style` | appearance | global-styles | Delete Global Style |
| `blocks/` | `blocks/duplicate-block` | blocks | post-blocks | Duplicate Block |
| `blocks/` | `blocks/evaluate-copy` | blocks | analysis | Evaluate Copy |
| `blocks/` | `blocks/evaluate-design` | blocks | analysis | Evaluate Design |
| `blocks/` | `blocks/evaluate-render-context` | blocks | analysis | Evaluate Render Context |
| `blocks/` | `blocks/extract-reusable-block` | blocks | patterns | Extract Reusable Block |
| `blocks/` | `blocks/find-navigation-usage` | appearance | site-editor | Find Navigation Usage |
| `blocks/` | `blocks/find-reusable-block-usage` | appearance | site-editor | Find Reusable Block Usage |
| `blocks/` | `blocks/find-template-part-usage` | appearance | site-editor | Find Template Part Usage |
| `blocks/` | `blocks/generate-landing-page` | blocks | generation | Generate Landing Page |
| `blocks/` | `blocks/generate-query-section` | blocks | generation | Generate Query Section |
| `blocks/` | `blocks/generate-section` | blocks | generation | Generate Section |
| `blocks/` | `blocks/get-block-guidance` | blocks | generation | Get Block Guidance |
| `blocks/` | `blocks/get-post-blocks` | blocks | post-blocks | Get Post Blocks |
| `blocks/` | `blocks/get-site-editor-context` | appearance | site-editor | Get Site Editor Context |
| `blocks/` | `blocks/get-site-editor-references` | appearance | site-editor | Get Site Editor References |
| `blocks/` | `blocks/get-site-editor-summary` | appearance | site-editor | Get Site Editor Summary |
| `blocks/` | `blocks/get-style-book` | blocks | block-info | Get Style Book |
| `blocks/` | `blocks/get-style-guide` | appearance | site-editor | Get Style Guide |
| `blocks/` | `blocks/insert-pattern` | blocks | post-blocks | Insert Pattern |
| `blocks/` | `blocks/insert-reusable-block-into-post` | blocks | patterns | Insert Reusable Block Into Post |
| `blocks/` | `blocks/list-block-areas` | appearance | site-editor | List Block Areas |
| `blocks/` | `blocks/list-block-categories` | blocks | block-info | List Block Categories |
| `blocks/` | `blocks/list-block-patterns` | blocks | patterns | List Block Patterns |
| `blocks/` | `blocks/list-block-style-variations` | appearance | block-style-variations | List Block Style Variations |
| `blocks/` | `blocks/list-block-template-parts` | appearance | template-parts | List Block Template Parts |
| `blocks/` | `blocks/list-block-templates` | appearance | templates | List Block Templates |
| `blocks/` | `blocks/list-blocks` | blocks | block-info | List Blocks |
| `blocks/` | `blocks/list-global-styles` | appearance | global-styles | List Global Styles |
| `blocks/` | `blocks/list-navigations` | appearance | site-editor | List Navigations |
| `blocks/` | `blocks/list-page-recipes` | blocks | generation | List Page Recipes |
| `blocks/` | `blocks/list-query-section-recipes` | blocks | generation | List Query Section Recipes |
| `blocks/` | `blocks/list-reusable-blocks` | blocks | reusable | List Reusable Blocks |
| `blocks/` | `blocks/list-section-recipes` | blocks | generation | List Section Recipes |
| `blocks/` | `blocks/move-block` | blocks | post-blocks | Move Block |
| `blocks/` | `blocks/mutate-block-tree` | blocks | mutation | Mutate Block Tree |
| `blocks/` | `blocks/normalize-heading-levels` | blocks | mutation | Normalize Heading Levels |
| `blocks/` | `blocks/outline-post-blocks` | blocks | post-blocks | Outline Post Blocks |
| `blocks/` | `blocks/parse-content` | blocks | content | Parse Content |
| `blocks/` | `blocks/read-block` | blocks | block-info | Read Block |
| `blocks/` | `blocks/read-block-bindings` | blocks | bindings | Read Block Bindings |
| `blocks/` | `blocks/read-block-pattern` | blocks | patterns | Read Block Pattern |
| `blocks/` | `blocks/read-block-style-variation` | appearance | block-style-variations | Read Block Style Variation |
| `blocks/` | `blocks/read-block-template` | appearance | templates | Read Block Template |
| `blocks/` | `blocks/read-block-template-part` | appearance | template-parts | Read Block Template Part |
| `blocks/` | `blocks/read-global-style` | appearance | global-styles | Read Global Style |
| `blocks/` | `blocks/read-navigation` | appearance | site-editor | Read Navigation |
| `blocks/` | `blocks/read-reusable-block` | blocks | patterns | Read Reusable Block |
| `blocks/` | `blocks/read-theme-json` | appearance | theme-json-settings | Read theme.json |
| `blocks/` | `blocks/refresh-site-editor-context` | appearance | site-editor | Refresh Site Editor Context |
| `blocks/` | `blocks/remove-block` | blocks | post-blocks | Remove Block |
| `blocks/` | `blocks/replace-block-text` | blocks | mutation | Replace Block Text |
| `blocks/` | `blocks/serialize-blocks` | blocks | content | Serialize Blocks |
| `blocks/` | `blocks/set-allowed-blocks` | blocks | mutation | Set Allowed Blocks |
| `blocks/` | `blocks/set-block-bindings` | blocks | bindings | Set Block Bindings |
| `blocks/` | `blocks/set-block-lock` | blocks | mutation | Set Block Lock |
| `blocks/` | `blocks/set-template-lock` | blocks | mutation | Set Template Lock |
| `blocks/` | `blocks/suggest-copy-fixes` | blocks | analysis | Suggest Copy Fixes |
| `blocks/` | `blocks/suggest-design-fixes` | blocks | analysis | Suggest Design Fixes |
| `blocks/` | `blocks/transform-blocks` | blocks | mutation | Transform Blocks |
| `blocks/` | `blocks/update-block-pattern` | blocks | patterns | Update Block Pattern |
| `blocks/` | `blocks/update-block-style-variation` | appearance | block-style-variations | Update Block Style Variation |
| `blocks/` | `blocks/update-block-template` | appearance | templates | Update Block Template |
| `blocks/` | `blocks/update-block-template-part` | appearance | template-parts | Update Block Template Part |
| `blocks/` | `blocks/update-global-style` | appearance | global-styles | Update Global Style |
| `blocks/` | `blocks/update-navigation` | appearance | site-editor | Update Navigation |
| `blocks/` | `blocks/update-post-block` | blocks | post-blocks | Update Block |
| `blocks/` | `blocks/update-reusable-block` | blocks | patterns | Update Reusable Block |
| `blocks/` | `blocks/update-theme-json` | appearance | theme-json-settings | Update theme.json |
| `blocks/` | `blocks/validate-content` | blocks | analysis | Validate Content |
| `cache/` | `cache/delete-expired-transients` | cache | cache | Delete Expired Transients |
| `cache/` | `cache/delete-transient` | cache | cache | Delete Transient |
| `cache/` | `cache/flush-object-cache` | cache |  | Flush Object Cache |
| `cache/` | `cache/flush-rewrite-rules` | configuration |  | Flush Rewrite Rules |
| `cache/` | `cache/flush-transients` | cache |  | Flush Transients |
| `cache/` | `cache/get-transient` | cache | cache | Get Transient |
| `cache/` | `cache/list-transients` | cache | cache | List Transients |
| `comments/` | `comments/approve-comment` | content | moderation | Approve Comment |
| `comments/` | `comments/bulk-update-comments` | content | moderation | Bulk Update Comments |
| `comments/` | `comments/create-comment` | content | manage | Create Comment |
| `comments/` | `comments/delete-comment` | content | manage | Delete Comment |
| `comments/` | `comments/get-comment` | content | manage | Get Comment |
| `comments/` | `comments/get-comment-count` | content | introspection | Get Comment Count |
| `comments/` | `comments/get-comment-meta` | content | meta | Get Comment Meta |
| `comments/` | `comments/list-comments` | content | manage | List Comments |
| `comments/` | `comments/mark-comment-spam` | content | moderation | Mark Comment as Spam |
| `comments/` | `comments/unapprove-comment` | content | moderation | Unapprove Comment |
| `comments/` | `comments/update-comment` | content | manage | Update Comment |
| `comments/` | `comments/update-comment-meta` | content | meta | Update Comment Meta |
| `contact-form-7/` | `contact-form-7/add-form-field` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/create-form` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/delete-form` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/duplicate-form` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/find-form` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/get-additional-settings` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/get-form` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/get-form-shortcode` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/get-form-template` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/get-mail` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/get-messages` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/list-field-types` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/list-form-fields` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/list-forms` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/list-mail-tags` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/remove-form-field` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/toggle-mail-2` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/update-additional-settings` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/update-form` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/update-form-field` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/update-form-template` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/update-mail` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/update-messages` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/validate-form-config` | contact-form-7 |  |  |
| `contact-form-7/` | `contact-form-7/validate-mail-tags` | contact-form-7 |  |  |
| `content-search/` | `content-search/apply-internal-link-suggestion` | content | internal-links | Apply Internal Link Suggestion |
| `content-search/` | `content-search/audit-internal-links` | content | audit | Audit Internal Links |
| `content-search/` | `content-search/create-internal-link-suggestions` | content | internal-links | Create Internal Link Suggestions |
| `content-search/` | `content-search/find-internal-links` | content | find | Find Internal Links |
| `content-search/` | `content-search/find-related-content` | content | find | Find Related Content |
| `content-search/` | `content-search/get-internal-link-policy` | content | internal-links | Get Internal Link Policy |
| `content-search/` | `content-search/list-internal-link-suggestions` | content | internal-links | List Internal Link Suggestions |
| `content-search/` | `content-search/refresh-content-index-batch` | content | index | Refresh Content Index Batch |
| `content-search/` | `content-search/review-internal-link-suggestion` | content | internal-links | Review Internal Link Suggestion |
| `content-search/` | `content-search/search-content-chunks` | content | search | Search Content Chunks |
| `content-search/` | `content-search/search-content-items` | content | search | Search Content Items |
| `content/` | `content/add-post-meta` | content | posts | Add Post Meta |
| `content/` | `content/create-cpt-item` | content | cpt | Create CPT Item |
| `content/` | `content/create-page` | content | pages | Create Page |
| `content/` | `content/create-post` | content | posts | Create Post |
| `content/` | `content/delete-cpt-item` | content | cpt | Delete CPT Item |
| `content/` | `content/delete-post` | content | posts | Delete Post |
| `content/` | `content/delete-post-meta` | content | posts | Delete Post Meta |
| `content/` | `content/get-cpt-item` | content | cpt | Get CPT Item |
| `content/` | `content/get-jet-engine-options-page` | content | options-pages | Get Options Page |
| `content/` | `content/get-page` | content | pages | Get Page |
| `content/` | `content/get-post` | content | posts | Get Post |
| `content/` | `content/get-post-meta` | content | posts | Get Post Meta |
| `content/` | `content/inspect-post-autosaves` | content | posts | Inspect Autosaves |
| `content/` | `content/link-post-translation` | content | multilanguage | Link Post Translations |
| `content/` | `content/list-cpt-item-revisions` | content | cpt | Get CPT Item Revisions |
| `content/` | `content/list-cpt-items` | content | cpt | Get CPT Items |
| `content/` | `content/list-jet-engine-options-pages` | content | options-pages | List Options Pages |
| `content/` | `content/list-page-revisions` | content | pages | Get Page Revisions |
| `content/` | `content/list-pages` | content | pages | Get Pages |
| `content/` | `content/list-post-revisions` | content | posts | Get Post Revisions |
| `content/` | `content/list-post-translations` | content | multilanguage | Get Post Translations |
| `content/` | `content/list-post-types` | content | cpt | List Post Types |
| `content/` | `content/list-posts` | content | posts | Get Posts |
| `content/` | `content/set-post-language` | content | multilanguage | Set Post Language |
| `content/` | `content/update-cpt-item` | content | cpt | Update CPT Item |
| `content/` | `content/update-jet-engine-options-page-field` | content | options-pages | Update Options Page Field |
| `content/` | `content/update-page` | content | pages | Update Page |
| `content/` | `content/update-post` | content | posts | Update Post |
| `content/` | `content/update-post-meta` | content | posts | Update Post Meta |
| `core/` | `core/check-wp-core-update` | updates | lifecycle | Check WordPress Core Update |
| `core/` | `core/get-wp-version` | updates | introspection | Get WordPress Version |
| `core/` | `core/reinstall-wp-core` | updates | lifecycle | Reinstall WordPress Core |
| `core/` | `core/rollback-wp-core` | updates | lifecycle | Rollback WordPress Core |
| `core/` | `core/update-wp-core` | updates | lifecycle | Update WordPress Core |
| `core/` | `core/verify-core-checksums` | updates | integrity | Verify Core Checksums |
| `cron/` | `cron/check-cron-job-exists` | cron | read | Check If Cron Job Exists |
| `cron/` | `cron/create-cron-job` | cron | write | Create Cron Job |
| `cron/` | `cron/create-cron-schedule` | cron | write | Create Custom Schedule |
| `cron/` | `cron/delete-cron-job` | cron | delete | Delete Cron Job |
| `cron/` | `cron/delete-cron-jobs-by-hook` | cron | delete | Delete All Cron Jobs By Hook |
| `cron/` | `cron/delete-cron-schedule` | cron | delete | Delete Custom Schedule |
| `cron/` | `cron/get-cron-job` | cron | read | Get Cron Job Details |
| `cron/` | `cron/get-cron-schedule` | cron | read | Get Schedule Details |
| `cron/` | `cron/get-cron-status` | cron | read | Get Cron Status |
| `cron/` | `cron/get-next-cron-run` | cron | read | Get Next Run Time |
| `cron/` | `cron/list-cron-jobs` | cron | read | List Cron Jobs |
| `cron/` | `cron/list-cron-schedules` | cron | read | List Schedules |
| `cron/` | `cron/list-overdue-cron-jobs` | cron | read | Get Overdue Cron Jobs |
| `cron/` | `cron/run-cron-job-now` | cron | write | Run Cron Job Now |
| `cron/` | `cron/test-wp-cron` | cron | read | Test WP-Cron |
| `cron/` | `cron/update-cron-job` | cron | write | Update Cron Job |
| `database/` | `database/audit-core-table-engines` | database | engine | Audit Core Table Engines |
| `database/` | `database/audit-health` | database | audit | Audit Database Health |
| `database/` | `database/audit-index-health` | database | audit | Audit Index Health |
| `database/` | `database/audit-options-health` | database | audit | Audit Options Health |
| `database/` | `database/cleanup-expired-transients` | cache | safe-writes | Cleanup Expired Transients |
| `database/` | `database/convert-core-tables-to-innodb` | database | engine | Convert Core Tables to InnoDB |
| `database/` | `database/delete-db-rows` | database | queries | Delete Rows |
| `database/` | `database/explain-db-query` | database | queries | Explain Query |
| `database/` | `database/extract-db-schema` | database | schema | Extract Database Schema |
| `database/` | `database/get-db-prefix` | database | introspection | Get Database Prefix |
| `database/` | `database/get-db-stats` | database | maintenance | Database Stats |
| `database/` | `database/insert-db-row` | database | queries | Insert Row |
| `database/` | `database/list-db-tables` | database | schema | List Database Tables |
| `database/` | `database/optimize-db-tables` | database | maintenance | Optimize Database Tables |
| `database/` | `database/run-db-select-query` | database | queries | Run SELECT Query |
| `database/` | `database/search-replace` | database | queries | Search Replace |
| `database/` | `database/set-option-autoload` | database | safe-writes | Set Option Autoload |
| `database/` | `database/update-db-rows` | database | queries | Update Rows |
| `elementor/` | `elementor/add-button` | elementor | elementor-elements | Add Elementor Button |
| `elementor/` | `elementor/add-container` | elementor | elementor-elements | Add Elementor Container |
| `elementor/` | `elementor/add-heading` | elementor | elementor-elements | Add Elementor Heading |
| `elementor/` | `elementor/add-image` | elementor | elementor-elements | Add Elementor Image |
| `elementor/` | `elementor/add-post-tabs` | elementor | elementor-elements | Add Elementor Post Tabs |
| `elementor/` | `elementor/add-text-editor` | elementor | elementor-elements | Add Elementor Text Editor |
| `elementor/` | `elementor/add-widget` | elementor | elementor-elements | Add Elementor Widget |
| `elementor/` | `elementor/clear-cache` | elementor | elementor-system | Clear Elementor Cache |
| `elementor/` | `elementor/clone-data` | elementor | elementor-documents | Clone Elementor Document Data |
| `elementor/` | `elementor/create-custom-code` | elementor | elementor-custom-code | Create Elementor Pro Custom Code |
| `elementor/` | `elementor/create-page` | elementor | elementor-documents | Create Elementor Page |
| `elementor/` | `elementor/create-template` | elementor | elementor-templates | Create Elementor Template |
| `elementor/` | `elementor/delete-custom-code` | elementor | elementor-custom-code | Delete Elementor Pro Custom Code |
| `elementor/` | `elementor/delete-element` | elementor | elementor-elements | Delete Elementor Element |
| `elementor/` | `elementor/delete-form-submission` | elementor | elementor-forms | Delete Elementor Pro Form Submission |
| `elementor/` | `elementor/delete-template` | elementor | elementor-templates | Delete Elementor Template |
| `elementor/` | `elementor/duplicate-element` | elementor | elementor-elements | Duplicate Elementor Element |
| `elementor/` | `elementor/duplicate-template` | elementor | elementor-templates | Duplicate Elementor Template |
| `elementor/` | `elementor/empty-trash` | elementor | elementor-templates | Empty Elementor Template Trash |
| `elementor/` | `elementor/evaluate-design` | elementor | elementor-design-audit | Evaluate Elementor Design |
| `elementor/` | `elementor/evaluate-render-context` | elementor | elementor-guidance | Evaluate Elementor Render Context |
| `elementor/` | `elementor/export-template` | elementor | elementor-templates | Export Elementor Template |
| `elementor/` | `elementor/find-elements` | elementor | elementor-elements | Find Elementor Elements |
| `elementor/` | `elementor/find-template-for-pattern` | elementor | elementor-templates | Find Elementor Template For Pattern |
| `elementor/` | `elementor/get-custom-code` | elementor | elementor-custom-code | Get Elementor Pro Custom Code |
| `elementor/` | `elementor/get-data` | elementor | elementor-documents | Get Elementor Document Data |
| `elementor/` | `elementor/get-element` | elementor | elementor-elements | Get Elementor Element |
| `elementor/` | `elementor/get-form-submission` | elementor | elementor-forms | Get Elementor Pro Form Submission |
| `elementor/` | `elementor/get-kit-settings` | elementor | elementor-kits | Get Elementor Kit Settings |
| `elementor/` | `elementor/get-maintenance-mode` | elementor | elementor-system | Get Elementor Maintenance Mode |
| `elementor/` | `elementor/get-official-pattern-guidance` | elementor | elementor-guidance | Get Elementor Pattern Guidance |
| `elementor/` | `elementor/get-official-widget-catalog` | elementor | elementor-guidance | Get Elementor Official Widget Catalog |
| `elementor/` | `elementor/get-style-guide` | elementor | elementor-guidance | Get Elementor Style Guide |
| `elementor/` | `elementor/get-template` | elementor | elementor-templates | Get Elementor Template |
| `elementor/` | `elementor/get-theme-builder-conditions` | elementor | elementor-templates | Get Theme Builder Conditions |
| `elementor/` | `elementor/get-theme-context` | elementor | elementor-guidance | Get Elementor Theme Context |
| `elementor/` | `elementor/get-widget-controls` | elementor | elementor-guidance | Get Elementor Widget Controls |
| `elementor/` | `elementor/import-template` | elementor | elementor-templates | Import Elementor Template |
| `elementor/` | `elementor/list-custom-code` | elementor | elementor-custom-code | List Elementor Pro Custom Code |
| `elementor/` | `elementor/list-experiments` | elementor | elementor-system | List Elementor Experiments |
| `elementor/` | `elementor/list-form-submissions` | elementor | elementor-forms | List Elementor Pro Form Submissions |
| `elementor/` | `elementor/list-global-widgets` | elementor | elementor-kits | List Elementor Global Widgets |
| `elementor/` | `elementor/list-kits` | elementor | elementor-kits | List Elementor Kits |
| `elementor/` | `elementor/list-templates` | elementor | elementor-templates | List Elementor Templates |
| `elementor/` | `elementor/merge-element-settings` | elementor | elementor-elements | Merge Elementor Element Settings |
| `elementor/` | `elementor/move-element` | elementor | elementor-elements | Move Elementor Element |
| `elementor/` | `elementor/patch-data` | elementor | elementor-documents | Patch Elementor Document Data |
| `elementor/` | `elementor/remove-element` | elementor | elementor-elements | Remove Elementor Element |
| `elementor/` | `elementor/reorder-elements` | elementor | elementor-elements | Reorder Elementor Elements |
| `elementor/` | `elementor/replace-urls` | elementor | elementor-system | Replace URLs in Elementor Documents |
| `elementor/` | `elementor/restore-template` | elementor | elementor-templates | Restore Elementor Template |
| `elementor/` | `elementor/set-active-kit` | elementor | elementor-kits | Set Active Elementor Kit |
| `elementor/` | `elementor/suggest-design-fixes` | elementor | elementor-design-audit | Suggest Elementor Design Fixes |
| `elementor/` | `elementor/update-custom-code` | elementor | elementor-custom-code | Update Elementor Pro Custom Code |
| `elementor/` | `elementor/update-data` | elementor | elementor-documents | Update Elementor Document Data |
| `elementor/` | `elementor/update-element` | elementor | elementor-elements | Update Elementor Element |
| `elementor/` | `elementor/update-experiment` | elementor | elementor-system | Update Elementor Experiment |
| `elementor/` | `elementor/update-kit-settings` | elementor | elementor-kits | Update Elementor Kit Settings |
| `elementor/` | `elementor/update-maintenance-mode` | elementor | elementor-system | Update Elementor Maintenance Mode |
| `elementor/` | `elementor/update-page-settings` | elementor | elementor-documents | Update Elementor Page Settings |
| `elementor/` | `elementor/update-template` | elementor | elementor-templates | Update Elementor Template |
| `elementor/` | `elementor/update-theme-builder-conditions` | elementor | elementor-templates | Update Theme Builder Conditions |
| `file-manager/` | `file-manager/append-file` | files | files | Append to File |
| `file-manager/` | `file-manager/clear-debug-log` | files | debug | Clear Debug Log |
| `file-manager/` | `file-manager/copy-file` | files | files | Copy File |
| `file-manager/` | `file-manager/create-directory` | files | files | Create Directory |
| `file-manager/` | `file-manager/create-file` | files | files | Create File |
| `file-manager/` | `file-manager/create-zip-backup` | files | backups | Create Zip Backup |
| `file-manager/` | `file-manager/delete-directory` | files | files | Delete Directory |
| `file-manager/` | `file-manager/delete-file` | files | files | Delete File |
| `file-manager/` | `file-manager/delete-zip-backup` | files | backups | Delete Zip Backup |
| `file-manager/` | `file-manager/download-zip-backup` | files | backups | Download Zip Backup |
| `file-manager/` | `file-manager/edit-file` | files | files | Create or Overwrite File |
| `file-manager/` | `file-manager/edit-wp-config` | files | wp-config | Edit wp-config.php |
| `file-manager/` | `file-manager/extract-zip-backup` | files | backups | Extract Zip Backup |
| `file-manager/` | `file-manager/file-info` | files | files | Get File Info |
| `file-manager/` | `file-manager/get-changelog` | files | audit | Get File Manager Changelog |
| `file-manager/` | `file-manager/get-wp-config-constant` | files | wp-config | Get wp-config Constant |
| `file-manager/` | `file-manager/list-directory` | files | files | List Directory |
| `file-manager/` | `file-manager/list-zip-backups` | files | backups | List Zip Backups |
| `file-manager/` | `file-manager/move-file` | files | files | Move File |
| `file-manager/` | `file-manager/read-debug-log` | files | debug | Read Debug Log |
| `file-manager/` | `file-manager/read-file` | files | files | Read File |
| `file-manager/` | `file-manager/read-wp-config` | files | wp-config | Read wp-config.php |
| `file-manager/` | `file-manager/upload-zip-backup` | files | backups | Upload Zip Backup |
| `fonts/` | `fonts/create-font-face` | appearance | font-faces | Create Font Face |
| `fonts/` | `fonts/create-font-family` | appearance | font-families | Create Font Family |
| `fonts/` | `fonts/delete-font-face` | appearance | font-faces | Delete Font Face |
| `fonts/` | `fonts/delete-font-family` | appearance | font-families | Delete Font Family |
| `fonts/` | `fonts/get-font-face` | appearance | font-faces | Get Font Face |
| `fonts/` | `fonts/get-font-family` | appearance | font-families | Get Font Family |
| `fonts/` | `fonts/list-font-faces` | appearance | font-faces | List Font Faces |
| `fonts/` | `fonts/list-font-families` | appearance | font-families | List Font Families |
| `litespeed/` | `litespeed/apply-preset` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/export-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/flush-object-cache` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-advanced-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-autoload-summary` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-cache-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-cache-status` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-cache-vary` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-crawler-map` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-crawler-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-crawler-status` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-database-summary` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-environment-report` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-media-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-media-status` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-object-cache-status` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-optimization-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-optimization-status` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/get-purge-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/list-cache-exclusions` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/list-crawlers` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/list-myisam-tables` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/list-optimization-exclusions` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/list-preset-backups` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/list-settings-areas` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/plan-database-cleanup` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/purge-by-tag` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/purge-cache` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/purge-optimization-cache` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/purge-post` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/purge-taxonomy` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/purge-url` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/reset-crawler` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/restore-preset-backup` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/run-crawler` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/set-cache-state` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/set-crawler-state` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/test-object-cache-connection` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-advanced-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-browser-cache-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-cache-exclusions` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-cache-scope` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-cache-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-cache-ttl` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-cache-vary` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-crawler-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-css-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-font-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-guest-mode` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-html-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-js-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-lazyload-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-localization-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-media-exclusions` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-object-cache-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-optimization-exclusions` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-placeholder-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-purge-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-scheduled-purge` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-tuning-settings` | litespeed-cache |  |  |
| `litespeed/` | `litespeed/update-viewport-settings` | litespeed-cache |  |  |
| `media/` | `media/delete-media` | content | manage | Delete Media |
| `media/` | `media/get-media` | content | manage | Get Media |
| `media/` | `media/get-media-meta` | content | meta | Get Media Meta |
| `media/` | `media/list-image-sizes` | content | introspection | List Image Sizes |
| `media/` | `media/list-media` | content | manage | List Media |
| `media/` | `media/list-upload-mime-types` | configuration | manage | List Allowed Upload MIME Types |
| `media/` | `media/rename-media-file` | content | manage | Rename Media File |
| `media/` | `media/update-media` | content | manage | Update Media |
| `media/` | `media/update-media-meta` | content | meta | Update Media Meta |
| `media/` | `media/update-upload-mime-types` | configuration | manage | Add or Remove Allowed Upload MIME Types |
| `media/` | `media/upload-media` | content | manage | Upload Media |
| `menus/` | `menus/create-menu` | appearance | menus | Create Menu |
| `menus/` | `menus/create-menu-item` | appearance | menu-items | Create Menu Item |
| `menus/` | `menus/delete-menu` | appearance | menus | Delete Menu |
| `menus/` | `menus/delete-menu-item` | appearance | menu-items | Delete Menu Item |
| `menus/` | `menus/get-menu` | appearance | menus | Get Menu |
| `menus/` | `menus/get-menu-item` | appearance | menu-items | Get Menu Item |
| `menus/` | `menus/get-navigation-context` | appearance | menus | Get Navigation Context |
| `menus/` | `menus/list-menu-items` | appearance | menu-items | List Menu Items |
| `menus/` | `menus/list-menus` | appearance | menus | List Menus |
| `menus/` | `menus/list-navigation-locations` | appearance | menus | List Navigation Locations |
| `menus/` | `menus/update-menu` | appearance | menus | Update Menu |
| `menus/` | `menus/update-menu-item` | appearance | menu-items | Update Menu Item |
| `options/` | `options/delete-option` | configuration | manage | Delete Option |
| `options/` | `options/get-nested-option-value` | configuration | manage | Get Nested Option Value |
| `options/` | `options/get-option` | configuration | manage | Get Option |
| `options/` | `options/list-options` | configuration | search | List Options |
| `options/` | `options/patch-option-value` | configuration | manage | Patch Option Value |
| `options/` | `options/search-options` | configuration | search | Search Options |
| `options/` | `options/update-option` | configuration | manage | Update Option |
| `plugins/` | `plugins/activate-plugin` | updates | lifecycle | Activate Plugin |
| `plugins/` | `plugins/check-plugin-updates` | updates | info | Check Updates |
| `plugins/` | `plugins/deactivate-plugin` | updates | lifecycle | Deactivate Plugin |
| `plugins/` | `plugins/get-plugin-lifecycle-context` | updates | lifecycle | Get Plugin Lifecycle Context |
| `plugins/` | `plugins/install-plugin` | updates | lifecycle | Install Plugin |
| `plugins/` | `plugins/list-plugins` | updates | info | List Plugins |
| `plugins/` | `plugins/search-wp-plugin-directory` | updates | lifecycle | Search WordPress.org Plugin Directory |
| `plugins/` | `plugins/uninstall-plugin` | updates | lifecycle | Uninstall Plugin |
| `plugins/` | `plugins/update-plugin` | updates | lifecycle | Update Plugin |
| `plugins/` | `plugins/verify-plugin-checksums` | updates | integrity | Verify Plugin Checksums |
| `rank-math/` | `rank-math/audit-content-seo` | rank-math |  |  |
| `rank-math/` | `rank-math/audit-faq-links` | rank-math |  |  |
| `rank-math/` | `rank-math/bulk-update-meta` | rank-math |  |  |
| `rank-math/` | `rank-math/change-redirection-status` | rank-math |  |  |
| `rank-math/` | `rank-math/clear-indexing-log` | rank-math |  |  |
| `rank-math/` | `rank-math/create-backup` | rank-math |  |  |
| `rank-math/` | `rank-math/create-redirection` | rank-math |  |  |
| `rank-math/` | `rank-math/delete-404-logs` | rank-math |  |  |
| `rank-math/` | `rank-math/delete-post-schemas` | rank-math |  |  |
| `rank-math/` | `rank-math/delete-redirections` | rank-math |  |  |
| `rank-math/` | `rank-math/delete-trashed-redirections` | rank-math |  |  |
| `rank-math/` | `rank-math/detect-seo-plugins` | rank-math |  |  |
| `rank-math/` | `rank-math/export-redirections` | rank-math |  |  |
| `rank-math/` | `rank-math/export-settings` | rank-math |  |  |
| `rank-math/` | `rank-math/find-redirection` | rank-math |  |  |
| `rank-math/` | `rank-math/get-ai-visibility-brand` | rank-math |  |  |
| `rank-math/` | `rank-math/get-analytics-rows` | rank-math |  |  |
| `rank-math/` | `rank-math/get-analytics-summary` | rank-math |  |  |
| `rank-math/` | `rank-math/get-content-ai-status` | rank-math |  |  |
| `rank-math/` | `rank-math/get-inbound-links` | rank-math |  |  |
| `rank-math/` | `rank-math/get-index-status` | rank-math |  |  |
| `rank-math/` | `rank-math/get-indexing-log` | rank-math |  |  |
| `rank-math/` | `rank-math/get-llms-status` | rank-math |  |  |
| `rank-math/` | `rank-math/get-primary-term` | rank-math |  |  |
| `rank-math/` | `rank-math/get-redirection-stats` | rank-math |  |  |
| `rank-math/` | `rank-math/get-rendered-head` | rank-math |  |  |
| `rank-math/` | `rank-math/get-role-capabilities` | rank-math |  |  |
| `rank-math/` | `rank-math/get-schema-status` | rank-math |  |  |
| `rank-math/` | `rank-math/get-seo-analysis-results` | rank-math |  |  |
| `rank-math/` | `rank-math/get-settings` | rank-math |  |  |
| `rank-math/` | `rank-math/get-sitemap-status` | rank-math |  |  |
| `rank-math/` | `rank-math/get-status` | rank-math |  |  |
| `rank-math/` | `rank-math/import-settings` | rank-math |  |  |
| `rank-math/` | `rank-math/inspect-url` | rank-math |  |  |
| `rank-math/` | `rank-math/invalidate-sitemap-cache` | rank-math |  |  |
| `rank-math/` | `rank-math/list-404-logs` | rank-math |  |  |
| `rank-math/` | `rank-math/list-backups` | rank-math |  |  |
| `rank-math/` | `rank-math/list-modules` | rank-math |  |  |
| `rank-math/` | `rank-math/list-redirections` | rank-math |  |  |
| `rank-math/` | `rank-math/list-sitemap-urls` | rank-math |  |  |
| `rank-math/` | `rank-math/manage-backup` | rank-math |  |  |
| `rank-math/` | `rank-math/manage-content-ai-output` | rank-math |  |  |
| `rank-math/` | `rank-math/manage-content-ai-prompts` | rank-math |  |  |
| `rank-math/` | `rank-math/refresh-llms-route` | rank-math |  |  |
| `rank-math/` | `rank-math/research-keyword` | rank-math |  |  |
| `rank-math/` | `rank-math/reset-indexing-key` | rank-math |  |  |
| `rank-math/` | `rank-math/reset-role-capabilities` | rank-math |  |  |
| `rank-math/` | `rank-math/run-maintenance-tool` | rank-math |  |  |
| `rank-math/` | `rank-math/set-module-state` | rank-math |  |  |
| `rank-math/` | `rank-math/submit-urls` | rank-math |  |  |
| `rank-math/` | `rank-math/update-ai-visibility-object` | rank-math |  |  |
| `rank-math/` | `rank-math/update-general-settings` | rank-math |  |  |
| `rank-math/` | `rank-math/update-instant-indexing-settings` | rank-math |  |  |
| `rank-math/` | `rank-math/update-post-schemas` | rank-math |  |  |
| `rank-math/` | `rank-math/update-primary-term` | rank-math |  |  |
| `rank-math/` | `rank-math/update-redirection` | rank-math |  |  |
| `rank-math/` | `rank-math/update-robots-txt` | rank-math |  |  |
| `rank-math/` | `rank-math/update-seo-meta` | rank-math |  |  |
| `rank-math/` | `rank-math/update-seo-scores` | rank-math |  |  |
| `rank-math/` | `rank-math/update-sitemap-settings` | rank-math |  |  |
| `rank-math/` | `rank-math/update-title-settings` | rank-math |  |  |
| `recovery/` | `recovery/get-recovery-exit-url` | diagnostics | recovery | Get Recovery Mode Exit URL |
| `recovery/` | `recovery/get-recovery-mode-status` | diagnostics | recovery | Get Recovery Mode Status |
| `recovery/` | `recovery/list-paused-plugins` | diagnostics | recovery | List Paused Plugins |
| `recovery/` | `recovery/list-paused-themes` | diagnostics | recovery | List Paused Themes |
| `recovery/` | `recovery/list-recent-fatal-errors` | diagnostics | recovery | List Recent Fatal Errors |
| `recovery/` | `recovery/unpause-plugin` | diagnostics | recovery | Unpause Plugin |
| `recovery/` | `recovery/unpause-theme` | diagnostics | recovery | Unpause Theme |
| `settings/` | `settings/flush-permalink-structure` | configuration | permalinks | Reset / Flush Permalinks |
| `settings/` | `settings/get-permalink-structure` | configuration | permalinks | Get Permalink Structure |
| `settings/` | `settings/get-site-icon` | appearance | site-identity | Get Site Icon |
| `settings/` | `settings/get-site-title` | appearance | site-identity | Get Site Title |
| `settings/` | `settings/get-tagline` | appearance | site-identity | Get Tagline |
| `settings/` | `settings/list-rewrite-rules` | configuration | permalinks | List Rewrite Rules |
| `settings/` | `settings/set-permalink-structure` | configuration | permalinks | Set Permalink Structure |
| `settings/` | `settings/update-site-icon` | appearance | site-identity | Update Site Icon |
| `settings/` | `settings/update-site-logo` | appearance | site-identity | Update Site Logo |
| `settings/` | `settings/update-site-title` | appearance | site-identity | Update Site Title |
| `settings/` | `settings/update-tagline` | appearance | site-identity | Update Tagline |
| `site-health/` | `site-health/get-maintenance-mode-status` | diagnostics | maintenance | Get Maintenance Mode Status |
| `site-health/` | `site-health/get-site-health-info` | diagnostics | read | Get Site Health Info |
| `site-health/` | `site-health/get-site-health-status` | diagnostics | read | Get Site Health Status |
| `site-health/` | `site-health/get-site-maintenance-report` | diagnostics | site-health | Site Maintenance Report |
| `site-health/` | `site-health/set-site-maintenance-mode` | diagnostics | maintenance | Set Site Maintenance Mode |
| `site-health/` | `site-health/unset-site-maintenance-mode` | diagnostics | maintenance | Unset Site Maintenance Mode |
| `taxonomies/` | `taxonomies/assign-cpt-terms` | content | terms | Assign Terms |
| `taxonomies/` | `taxonomies/create-term` | content | terms | Create Term |
| `taxonomies/` | `taxonomies/delete-term` | content | terms | Delete Term |
| `taxonomies/` | `taxonomies/get-taxonomy` | content | taxonomies | Get Taxonomy |
| `taxonomies/` | `taxonomies/get-term` | content | terms | Get Term |
| `taxonomies/` | `taxonomies/list-cpt-taxonomies` | content | taxonomies | Get CPT Taxonomies |
| `taxonomies/` | `taxonomies/list-taxonomies` | content | taxonomies | List Taxonomies |
| `taxonomies/` | `taxonomies/list-terms` | content | terms | List Terms |
| `taxonomies/` | `taxonomies/set-term-image` | content | terms | Set Term Image |
| `taxonomies/` | `taxonomies/update-term` | content | terms | Update Term |
| `themes/` | `themes/activate-theme` | updates | lifecycle | Activate Theme |
| `themes/` | `themes/delete-theme` | updates | lifecycle | Delete Theme |
| `themes/` | `themes/get-theme-lifecycle-context` | updates | lifecycle | Get Theme Lifecycle Context |
| `themes/` | `themes/install-theme` | updates | lifecycle | Install Theme |
| `themes/` | `themes/list-theme-mods` | updates | introspection | List Theme Mods |
| `themes/` | `themes/list-themes` | updates | info | List Themes |
| `themes/` | `themes/update-theme` | updates | lifecycle | Update Theme |
| `users/` | `users/add-role-capability` | users | roles | Add Role Capability |
| `users/` | `users/add-user-capability` | users | users | Add User Capability |
| `users/` | `users/create-role` | users | roles | Create Role |
| `users/` | `users/create-user` | users | users | Create User |
| `users/` | `users/delete-role` | users | roles | Delete Role |
| `users/` | `users/delete-user` | users | users | Delete User |
| `users/` | `users/get-current-user-access` | users | roles | Current User Access |
| `users/` | `users/get-role-capabilities` | users | roles | Get Role Capabilities |
| `users/` | `users/get-user` | users | users | Get User |
| `users/` | `users/list-user-roles` | users | roles | List User Roles |
| `users/` | `users/list-users` | users | users | List Users |
| `users/` | `users/remove-role-capability` | users | roles | Remove Role Capability |
| `users/` | `users/remove-user-capability` | users | users | Remove User Capability |
| `users/` | `users/reset-role` | users | roles | Reset Role |
| `users/` | `users/reset-user-password` | users | users | Reset User Password |
| `users/` | `users/update-user` | users | users | Update User |
| `widgets/` | `widgets/list-sidebars` | appearance | introspection | List Sidebars |
| `widgets/` | `widgets/list-widgets` | appearance | introspection | List Widgets |
