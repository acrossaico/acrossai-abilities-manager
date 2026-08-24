# Ability inventory

Generated snapshot of every ability the plugin registers, using the **post-rename** slugs
(after PRs #134 blocks, #135 elementor, #136 rank-math, #137 topic-namespaces land).

**Total abilities:** 385 across 24 topic namespaces.

## Summary

| Namespace | Count | WP category slug |
|---|---:|---|
| `admin-menu/` | 5 | `acrossai-abilities-manager-admin-menu` |
| `blocks/` | 40 | `acrossai-abilities-manager-block`, `acrossai-abilities-manager-content` |
| `cache/` | 7 | `acrossai-abilities-manager-cache` |
| `comments/` | 12 | `acrossai-abilities-manager-comments` |
| `content/` | 29 | `acrossai-abilities-manager-content` |
| `content-search/` | 11 | `acrossai-abilities-manager-content-search` |
| `core/` | 6 | `acrossai-abilities-manager-core` |
| `cron/` | 16 | `acrossai-abilities-manager-cron` |
| `database/` | 11 | `acrossai-abilities-manager-database` |
| `elementor/` | 62 | `acrossai-abilities-manager-elementor` |
| `file-manager/` | 18 | `acrossai-abilities-manager-file-manager` |
| `fonts/` | 8 | `acrossai-abilities-manager-fonts` |
| `media/` | 11 | `acrossai-abilities-manager-media` |
| `menus/` | 12 | `acrossai-abilities-manager-menus` |
| `options/` | 7 | `acrossai-abilities-manager-options` |
| `plugins/` | 10 | `acrossai-abilities-manager-plugins` |
| `rank-math/` | 61 | `acrossai-abilities-manager-rank-math` |
| `recovery/` | 7 | `acrossai-abilities-manager-recovery` |
| `settings/` | 11 | `acrossai-abilities-manager-settings` |
| `site-health/` | 6 | `acrossai-abilities-manager-site-health` |
| `taxonomies/` | 10 | `acrossai-abilities-manager-taxonomies` |
| `themes/` | 7 | `acrossai-abilities-manager-themes` |
| `users/` | 16 | `acrossai-abilities-manager-users` |
| `widgets/` | 2 | `acrossai-abilities-manager-widgets` |

## Detail — all abilities

Sorted by namespace → sub-group → slug.

| Namespace | Slug | Tab group | Sub-group | Label |
|---|---|---|---|---|
| `admin-menu/` | `admin-menu/get-admin-menu-context` | core | admin-menu | Get Admin Menu Context |
| `admin-menu/` | `admin-menu/get-admin-menu-navigation-target` | core | admin-menu | Get Admin Menu Navigation Target |
| `admin-menu/` | `admin-menu/list-admin-menu-pages` | core | admin-menu | List Admin Menu Pages |
| `admin-menu/` | `admin-menu/list-admin-settings` | core | admin-menu | List Admin Settings |
| `admin-menu/` | `admin-menu/refresh-admin-menu-context` | core | admin-menu | Refresh Admin Menu Context |
| `blocks/` | `blocks/list-blocks` | blocks | block-info | List Blocks |
| `blocks/` | `blocks/read-block` | blocks | block-info | Read Block |
| `blocks/` | `blocks/create-block-style-variation` | blocks | block-style-variations | Create Block Style Variation |
| `blocks/` | `blocks/delete-block-style-variation` | blocks | block-style-variations | Delete Block Style Variation |
| `blocks/` | `blocks/list-block-style-variations` | blocks | block-style-variations | List Block Style Variations |
| `blocks/` | `blocks/read-block-style-variation` | blocks | block-style-variations | Read Block Style Variation |
| `blocks/` | `blocks/update-block-style-variation` | blocks | block-style-variations | Update Block Style Variation |
| `blocks/` | `blocks/create-global-style` | blocks | global-styles | Create Global Style |
| `blocks/` | `blocks/delete-global-style` | blocks | global-styles | Delete Global Style |
| `blocks/` | `blocks/list-global-styles` | blocks | global-styles | List Global Styles |
| `blocks/` | `blocks/read-global-style` | blocks | global-styles | Read Global Style |
| `blocks/` | `blocks/update-global-style` | blocks | global-styles | Update Global Style |
| `blocks/` | `blocks/create-block-pattern` | blocks | patterns | Create Block Pattern |
| `blocks/` | `blocks/delete-block-pattern` | blocks | patterns | Delete Block Pattern |
| `blocks/` | `blocks/list-block-patterns` | blocks | patterns | List Block Patterns |
| `blocks/` | `blocks/read-block-pattern` | blocks | patterns | Read Block Pattern |
| `blocks/` | `blocks/update-block-pattern` | blocks | patterns | Update Block Pattern |
| `blocks/` | `blocks/list-reusable-blocks` | blocks | reusable | List Reusable Blocks |
| `blocks/` | `blocks/get-site-editor-context` | blocks | site-editor | Get Site Editor Context |
| `blocks/` | `blocks/list-block-areas` | blocks | site-editor | List Block Areas |
| `blocks/` | `blocks/refresh-site-editor-context` | blocks | site-editor | Refresh Site Editor Context |
| `blocks/` | `blocks/create-block-template-part` | blocks | template-parts | Create Block Template Part |
| `blocks/` | `blocks/delete-block-template-part` | blocks | template-parts | Delete Block Template Part |
| `blocks/` | `blocks/list-block-template-parts` | blocks | template-parts | List Block Template Parts |
| `blocks/` | `blocks/read-block-template-part` | blocks | template-parts | Read Block Template Part |
| `blocks/` | `blocks/update-block-template-part` | blocks | template-parts | Update Block Template Part |
| `blocks/` | `blocks/create-block-template` | blocks | templates | Create Block Template |
| `blocks/` | `blocks/delete-block-template` | blocks | templates | Delete Block Template |
| `blocks/` | `blocks/list-block-templates` | blocks | templates | List Block Templates |
| `blocks/` | `blocks/read-block-template` | blocks | templates | Read Block Template |
| `blocks/` | `blocks/update-block-template` | blocks | templates | Update Block Template |
| `blocks/` | `blocks/read-theme-json` | blocks | theme-json-settings | Read theme.json |
| `blocks/` | `blocks/update-theme-json` | blocks | theme-json-settings | Update theme.json |
| `blocks/` | `blocks/add-block` | core | posts | Add Block |
| `blocks/` | `blocks/duplicate-block` | core | posts | Duplicate Block |
| `blocks/` | `blocks/get-post-blocks` | core | posts | Get Post Blocks |
| `blocks/` | `blocks/insert-pattern` | core | posts | Insert Pattern |
| `blocks/` | `blocks/move-block` | core | posts | Move Block |
| `blocks/` | `blocks/remove-block` | core | posts | Remove Block |
| `blocks/` | `blocks/update-post-block` | core | posts | Update Block |
| `cache/` | `cache/flush-object-cache` | cache | — | Flush Object Cache |
| `cache/` | `cache/flush-rewrite-rules` | cache | — | Flush Rewrite Rules |
| `cache/` | `cache/flush-transients` | cache | — | Flush Transients |
| `cache/` | `cache/delete-expired-transients` | cache | cache | Delete Expired Transients |
| `cache/` | `cache/delete-transient` | cache | cache | Delete Transient |
| `cache/` | `cache/get-transient` | cache | cache | Get Transient |
| `cache/` | `cache/list-transients` | cache | cache | List Transients |
| `comments/` | `comments/get-comment-count` | comments | introspection | Get Comment Count |
| `comments/` | `comments/create-comment` | comments | manage | Create Comment |
| `comments/` | `comments/delete-comment` | comments | manage | Delete Comment |
| `comments/` | `comments/get-comment` | comments | manage | Get Comment |
| `comments/` | `comments/list-comments` | comments | manage | List Comments |
| `comments/` | `comments/update-comment` | comments | manage | Update Comment |
| `comments/` | `comments/get-comment-meta` | comments | meta | Get Comment Meta |
| `comments/` | `comments/update-comment-meta` | comments | meta | Update Comment Meta |
| `comments/` | `comments/approve-comment` | comments | moderation | Approve Comment |
| `comments/` | `comments/bulk-update-comments` | comments | moderation | Bulk Update Comments |
| `comments/` | `comments/mark-comment-spam` | comments | moderation | Mark Comment as Spam |
| `comments/` | `comments/unapprove-comment` | comments | moderation | Unapprove Comment |
| `content/` | `content/create-cpt-item` | core | cpt | Create CPT Item |
| `content/` | `content/delete-cpt-item` | core | cpt | Delete CPT Item |
| `content/` | `content/get-cpt-item` | core | cpt | Get CPT Item |
| `content/` | `content/list-cpt-item-revisions` | core | cpt | Get CPT Item Revisions |
| `content/` | `content/list-cpt-items` | core | cpt | Get CPT Items |
| `content/` | `content/list-post-types` | core | cpt | List Post Types |
| `content/` | `content/update-cpt-item` | core | cpt | Update CPT Item |
| `content/` | `content/link-post-translation` | core | multilanguage | Link Post Translations |
| `content/` | `content/list-post-translations` | core | multilanguage | Get Post Translations |
| `content/` | `content/set-post-language` | core | multilanguage | Set Post Language |
| `content/` | `content/get-jet-engine-options-page` | core | options-pages | Get Options Page |
| `content/` | `content/list-jet-engine-options-pages` | core | options-pages | List Options Pages |
| `content/` | `content/update-jet-engine-options-page-field` | core | options-pages | Update Options Page Field |
| `content/` | `content/create-page` | core | pages | Create Page |
| `content/` | `content/get-page` | core | pages | Get Page |
| `content/` | `content/list-page-revisions` | core | pages | Get Page Revisions |
| `content/` | `content/list-pages` | core | pages | Get Pages |
| `content/` | `content/update-page` | core | pages | Update Page |
| `content/` | `content/add-post-meta` | core | posts | Add Post Meta |
| `content/` | `content/create-post` | core | posts | Create Post |
| `content/` | `content/delete-post` | core | posts | Delete Post |
| `content/` | `content/delete-post-meta` | core | posts | Delete Post Meta |
| `content/` | `content/get-post` | core | posts | Get Post |
| `content/` | `content/get-post-meta` | core | posts | Get Post Meta |
| `content/` | `content/inspect-post-autosaves` | core | posts | Inspect Autosaves |
| `content/` | `content/list-post-revisions` | core | posts | Get Post Revisions |
| `content/` | `content/list-posts` | core | posts | Get Posts |
| `content/` | `content/update-post` | core | posts | Update Post |
| `content/` | `content/update-post-meta` | core | posts | Update Post Meta |
| `content-search/` | `content-search/audit-internal-links` | content-search | audit | Audit Internal Links |
| `content-search/` | `content-search/find-internal-links` | content-search | find | Find Internal Links |
| `content-search/` | `content-search/find-related-content` | content-search | find | Find Related Content |
| `content-search/` | `content-search/refresh-content-index-batch` | content-search | index | Refresh Content Index Batch |
| `content-search/` | `content-search/apply-internal-link-suggestion` | content-search | internal-links | Apply Internal Link Suggestion |
| `content-search/` | `content-search/create-internal-link-suggestions` | content-search | internal-links | Create Internal Link Suggestions |
| `content-search/` | `content-search/get-internal-link-policy` | content-search | internal-links | Get Internal Link Policy |
| `content-search/` | `content-search/list-internal-link-suggestions` | content-search | internal-links | List Internal Link Suggestions |
| `content-search/` | `content-search/review-internal-link-suggestion` | content-search | internal-links | Review Internal Link Suggestion |
| `content-search/` | `content-search/search-content-chunks` | content-search | search | Search Content Chunks |
| `content-search/` | `content-search/search-content-items` | content-search | search | Search Content Items |
| `core/` | `core/verify-core-checksums` | core | integrity | Verify Core Checksums |
| `core/` | `core/get-wp-version` | core | introspection | Get WordPress Version |
| `core/` | `core/check-wp-core-update` | core | lifecycle | Check WordPress Core Update |
| `core/` | `core/reinstall-wp-core` | core | lifecycle | Reinstall WordPress Core |
| `core/` | `core/rollback-wp-core` | core | lifecycle | Rollback WordPress Core |
| `core/` | `core/update-wp-core` | core | lifecycle | Update WordPress Core |
| `cron/` | `cron/delete-cron-job` | cron | delete | Delete Cron Job |
| `cron/` | `cron/delete-cron-jobs-by-hook` | cron | delete | Delete All Cron Jobs By Hook |
| `cron/` | `cron/delete-cron-schedule` | cron | delete | Delete Custom Schedule |
| `cron/` | `cron/check-cron-job-exists` | cron | read | Check If Cron Job Exists |
| `cron/` | `cron/get-cron-job` | cron | read | Get Cron Job Details |
| `cron/` | `cron/get-cron-schedule` | cron | read | Get Schedule Details |
| `cron/` | `cron/get-cron-status` | cron | read | Get Cron Status |
| `cron/` | `cron/get-next-cron-run` | cron | read | Get Next Run Time |
| `cron/` | `cron/list-cron-jobs` | cron | read | List Cron Jobs |
| `cron/` | `cron/list-cron-schedules` | cron | read | List Schedules |
| `cron/` | `cron/list-overdue-cron-jobs` | cron | read | Get Overdue Cron Jobs |
| `cron/` | `cron/test-wp-cron` | cron | read | Test WP-Cron |
| `cron/` | `cron/create-cron-job` | cron | write | Create Cron Job |
| `cron/` | `cron/create-cron-schedule` | cron | write | Create Custom Schedule |
| `cron/` | `cron/run-cron-job-now` | cron | write | Run Cron Job Now |
| `cron/` | `cron/update-cron-job` | cron | write | Update Cron Job |
| `database/` | `database/get-db-prefix` | database | introspection | Get Database Prefix |
| `database/` | `database/get-db-stats` | database | maintenance | Database Stats |
| `database/` | `database/optimize-db-tables` | database | maintenance | Optimize Database Tables |
| `database/` | `database/delete-db-rows` | database | queries | Delete Rows |
| `database/` | `database/explain-db-query` | database | queries | Explain Query |
| `database/` | `database/insert-db-row` | database | queries | Insert Row |
| `database/` | `database/run-db-select-query` | database | queries | Run SELECT Query |
| `database/` | `database/search-replace` | database | queries | Search Replace |
| `database/` | `database/update-db-rows` | database | queries | Update Rows |
| `database/` | `database/extract-db-schema` | database | schema | Extract Database Schema |
| `database/` | `database/list-db-tables` | database | schema | List Database Tables |
| `elementor/` | `elementor/add-button` | elementor | elementor | Add Elementor Button |
| `elementor/` | `elementor/add-container` | elementor | elementor | Add Elementor Container |
| `elementor/` | `elementor/add-heading` | elementor | elementor | Add Elementor Heading |
| `elementor/` | `elementor/add-image` | elementor | elementor | Add Elementor Image |
| `elementor/` | `elementor/add-post-tabs` | elementor | elementor | Add Elementor Post Tabs |
| `elementor/` | `elementor/add-text-editor` | elementor | elementor | Add Elementor Text Editor |
| `elementor/` | `elementor/add-widget` | elementor | elementor | Add Elementor Widget |
| `elementor/` | `elementor/clear-cache` | elementor | elementor | Clear Elementor Cache |
| `elementor/` | `elementor/clone-data` | elementor | elementor | Clone Elementor Document Data |
| `elementor/` | `elementor/create-custom-code` | elementor | elementor | Create Elementor Pro Custom Code |
| `elementor/` | `elementor/create-page` | elementor | elementor | Create Elementor Page |
| `elementor/` | `elementor/create-template` | elementor | elementor | Create Elementor Template |
| `elementor/` | `elementor/delete-custom-code` | elementor | elementor | Delete Elementor Pro Custom Code |
| `elementor/` | `elementor/delete-element` | elementor | elementor | Delete Elementor Element |
| `elementor/` | `elementor/delete-form-submission` | elementor | elementor | Delete Elementor Pro Form Submission |
| `elementor/` | `elementor/delete-template` | elementor | elementor | Delete Elementor Template |
| `elementor/` | `elementor/duplicate-element` | elementor | elementor | Duplicate Elementor Element |
| `elementor/` | `elementor/duplicate-template` | elementor | elementor | Duplicate Elementor Template |
| `elementor/` | `elementor/empty-trash` | elementor | elementor | Empty Elementor Template Trash |
| `elementor/` | `elementor/evaluate-design` | elementor | elementor | Evaluate Elementor Design |
| `elementor/` | `elementor/evaluate-render-context` | elementor | elementor | Evaluate Elementor Render Context |
| `elementor/` | `elementor/export-template` | elementor | elementor | Export Elementor Template |
| `elementor/` | `elementor/find-elements` | elementor | elementor | Find Elementor Elements |
| `elementor/` | `elementor/find-template-for-pattern` | elementor | elementor | Find Elementor Template For Pattern |
| `elementor/` | `elementor/get-custom-code` | elementor | elementor | Get Elementor Pro Custom Code |
| `elementor/` | `elementor/get-data` | elementor | elementor | Get Elementor Document Data |
| `elementor/` | `elementor/get-element` | elementor | elementor | Get Elementor Element |
| `elementor/` | `elementor/get-form-submission` | elementor | elementor | Get Elementor Pro Form Submission |
| `elementor/` | `elementor/get-kit-settings` | elementor | elementor | Get Elementor Kit Settings |
| `elementor/` | `elementor/get-maintenance-mode` | elementor | elementor | Get Elementor Maintenance Mode |
| `elementor/` | `elementor/get-official-pattern-guidance` | elementor | elementor | Get Elementor Pattern Guidance |
| `elementor/` | `elementor/get-official-widget-catalog` | elementor | elementor | Get Elementor Official Widget Catalog |
| `elementor/` | `elementor/get-style-guide` | elementor | elementor | Get Elementor Style Guide |
| `elementor/` | `elementor/get-template` | elementor | elementor | Get Elementor Template |
| `elementor/` | `elementor/get-theme-builder-conditions` | elementor | elementor | Get Theme Builder Conditions |
| `elementor/` | `elementor/get-theme-context` | elementor | elementor | Get Elementor Theme Context |
| `elementor/` | `elementor/get-widget-controls` | elementor | elementor | Get Elementor Widget Controls |
| `elementor/` | `elementor/import-template` | elementor | elementor | Import Elementor Template |
| `elementor/` | `elementor/list-custom-code` | elementor | elementor | List Elementor Pro Custom Code |
| `elementor/` | `elementor/list-experiments` | elementor | elementor | List Elementor Experiments |
| `elementor/` | `elementor/list-form-submissions` | elementor | elementor | List Elementor Pro Form Submissions |
| `elementor/` | `elementor/list-global-widgets` | elementor | elementor | List Elementor Global Widgets |
| `elementor/` | `elementor/list-kits` | elementor | elementor | List Elementor Kits |
| `elementor/` | `elementor/list-templates` | elementor | elementor | List Elementor Templates |
| `elementor/` | `elementor/merge-element-settings` | elementor | elementor | Merge Elementor Element Settings |
| `elementor/` | `elementor/move-element` | elementor | elementor | Move Elementor Element |
| `elementor/` | `elementor/patch-data` | elementor | elementor | Patch Elementor Document Data |
| `elementor/` | `elementor/remove-element` | elementor | elementor | Remove Elementor Element |
| `elementor/` | `elementor/reorder-elements` | elementor | elementor | Reorder Elementor Elements |
| `elementor/` | `elementor/replace-urls` | elementor | elementor | Replace URLs in Elementor Documents |
| `elementor/` | `elementor/restore-template` | elementor | elementor | Restore Elementor Template |
| `elementor/` | `elementor/set-active-kit` | elementor | elementor | Set Active Elementor Kit |
| `elementor/` | `elementor/suggest-design-fixes` | elementor | elementor | Suggest Elementor Design Fixes |
| `elementor/` | `elementor/update-custom-code` | elementor | elementor | Update Elementor Pro Custom Code |
| `elementor/` | `elementor/update-data` | elementor | elementor | Update Elementor Document Data |
| `elementor/` | `elementor/update-element` | elementor | elementor | Update Elementor Element |
| `elementor/` | `elementor/update-experiment` | elementor | elementor | Update Elementor Experiment |
| `elementor/` | `elementor/update-kit-settings` | elementor | elementor | Update Elementor Kit Settings |
| `elementor/` | `elementor/update-maintenance-mode` | elementor | elementor | Update Elementor Maintenance Mode |
| `elementor/` | `elementor/update-page-settings` | elementor | elementor | Update Elementor Page Settings |
| `elementor/` | `elementor/update-template` | elementor | elementor | Update Elementor Template |
| `elementor/` | `elementor/update-theme-builder-conditions` | elementor | elementor | Update Theme Builder Conditions |
| `file-manager/` | `file-manager/create-zip-backup` | file-manager | backups | Create Zip Backup |
| `file-manager/` | `file-manager/delete-zip-backup` | file-manager | backups | Delete Zip Backup |
| `file-manager/` | `file-manager/download-zip-backup` | file-manager | backups | Download Zip Backup |
| `file-manager/` | `file-manager/extract-zip-backup` | file-manager | backups | Extract Zip Backup |
| `file-manager/` | `file-manager/list-zip-backups` | file-manager | backups | List Zip Backups |
| `file-manager/` | `file-manager/upload-zip-backup` | file-manager | backups | Upload Zip Backup |
| `file-manager/` | `file-manager/clear-debug-log` | file-manager | debug | Clear Debug Log |
| `file-manager/` | `file-manager/read-debug-log` | file-manager | debug | Read Debug Log |
| `file-manager/` | `file-manager/copy-file` | file-manager | files | Copy File |
| `file-manager/` | `file-manager/create-file` | file-manager | files | Create File |
| `file-manager/` | `file-manager/delete-file` | file-manager | files | Delete File |
| `file-manager/` | `file-manager/edit-file` | file-manager | files | Create or Overwrite File |
| `file-manager/` | `file-manager/list-directory` | file-manager | files | List Directory |
| `file-manager/` | `file-manager/move-file` | file-manager | files | Move File |
| `file-manager/` | `file-manager/read-file` | file-manager | files | Read File |
| `file-manager/` | `file-manager/edit-wp-config` | file-manager | wp-config | Edit wp-config.php |
| `file-manager/` | `file-manager/get-wp-config-constant` | file-manager | wp-config | Get wp-config Constant |
| `file-manager/` | `file-manager/read-wp-config` | file-manager | wp-config | Read wp-config.php |
| `fonts/` | `fonts/create-font-face` | core | font-faces | Create Font Face |
| `fonts/` | `fonts/delete-font-face` | core | font-faces | Delete Font Face |
| `fonts/` | `fonts/get-font-face` | core | font-faces | Get Font Face |
| `fonts/` | `fonts/list-font-faces` | core | font-faces | List Font Faces |
| `fonts/` | `fonts/create-font-family` | core | font-families | Create Font Family |
| `fonts/` | `fonts/delete-font-family` | core | font-families | Delete Font Family |
| `fonts/` | `fonts/get-font-family` | core | font-families | Get Font Family |
| `fonts/` | `fonts/list-font-families` | core | font-families | List Font Families |
| `media/` | `media/list-image-sizes` | media | introspection | List Image Sizes |
| `media/` | `media/delete-media` | media | manage | Delete Media |
| `media/` | `media/get-media` | media | manage | Get Media |
| `media/` | `media/list-media` | media | manage | List Media |
| `media/` | `media/list-upload-mime-types` | media | manage | List Allowed Upload MIME Types |
| `media/` | `media/rename-media-file` | media | manage | Rename Media File |
| `media/` | `media/update-media` | media | manage | Update Media |
| `media/` | `media/update-upload-mime-types` | media | manage | Add or Remove Allowed Upload MIME Types |
| `media/` | `media/upload-media` | media | manage | Upload Media |
| `media/` | `media/get-media-meta` | media | meta | Get Media Meta |
| `media/` | `media/update-media-meta` | media | meta | Update Media Meta |
| `menus/` | `menus/create-menu-item` | core | menu-items | Create Menu Item |
| `menus/` | `menus/delete-menu-item` | core | menu-items | Delete Menu Item |
| `menus/` | `menus/get-menu-item` | core | menu-items | Get Menu Item |
| `menus/` | `menus/list-menu-items` | core | menu-items | List Menu Items |
| `menus/` | `menus/update-menu-item` | core | menu-items | Update Menu Item |
| `menus/` | `menus/create-menu` | core | menus | Create Menu |
| `menus/` | `menus/delete-menu` | core | menus | Delete Menu |
| `menus/` | `menus/get-menu` | core | menus | Get Menu |
| `menus/` | `menus/get-navigation-context` | core | menus | Get Navigation Context |
| `menus/` | `menus/list-menus` | core | menus | List Menus |
| `menus/` | `menus/list-navigation-locations` | core | menus | List Navigation Locations |
| `menus/` | `menus/update-menu` | core | menus | Update Menu |
| `options/` | `options/delete-option` | core | manage | Delete Option |
| `options/` | `options/get-nested-option-value` | core | manage | Get Nested Option Value |
| `options/` | `options/get-option` | core | manage | Get Option |
| `options/` | `options/patch-option-value` | core | manage | Patch Option Value |
| `options/` | `options/update-option` | core | manage | Update Option |
| `options/` | `options/list-options` | core | search | List Options |
| `options/` | `options/search-options` | core | search | Search Options |
| `plugins/` | `plugins/check-plugin-updates` | plugins | info | Check Updates |
| `plugins/` | `plugins/list-plugins` | plugins | info | List Plugins |
| `plugins/` | `plugins/verify-plugin-checksums` | plugins | integrity | Verify Plugin Checksums |
| `plugins/` | `plugins/activate-plugin` | plugins | lifecycle | Activate Plugin |
| `plugins/` | `plugins/deactivate-plugin` | plugins | lifecycle | Deactivate Plugin |
| `plugins/` | `plugins/get-plugin-lifecycle-context` | plugins | lifecycle | Get Plugin Lifecycle Context |
| `plugins/` | `plugins/install-plugin` | plugins | lifecycle | Install Plugin |
| `plugins/` | `plugins/search-wp-plugin-directory` | plugins | lifecycle | Search WordPress.org Plugin Directory |
| `plugins/` | `plugins/uninstall-plugin` | plugins | lifecycle | Uninstall Plugin |
| `plugins/` | `plugins/update-plugin` | plugins | lifecycle | Update Plugin |
| `rank-math/` | `rank-math/update-general-settings` | rank-math | — | Update Rank Math General Settings |
| `rank-math/` | `rank-math/update-sitemap-settings` | rank-math | — | Update Rank Math Sitemap Settings |
| `rank-math/` | `rank-math/update-title-settings` | rank-math | — | Update Rank Math Title & Meta Settings |
| `rank-math/` | `rank-math/delete-404-logs` | rank-math | rank-math-404-monitor | Delete Rank Math 404 Log Entries |
| `rank-math/` | `rank-math/list-404-logs` | rank-math | rank-math-404-monitor | List Rank Math 404 Logs |
| `rank-math/` | `rank-math/get-ai-visibility-brand` | rank-math | rank-math-ai-visibility | Get AI Visibility Brand |
| `rank-math/` | `rank-math/update-ai-visibility-object` | rank-math | rank-math-ai-visibility | Update AI Visibility Brand or Query |
| `rank-math/` | `rank-math/get-analytics-rows` | rank-math | rank-math-analytics | Get Rank Math Analytics Rows |
| `rank-math/` | `rank-math/get-analytics-summary` | rank-math | rank-math-analytics | Get Rank Math Analytics Summary |
| `rank-math/` | `rank-math/get-index-status` | rank-math | rank-math-analytics | Get Rank Math Index Status |
| `rank-math/` | `rank-math/inspect-url` | rank-math | rank-math-analytics | Inspect URL with Google |
| `rank-math/` | `rank-math/audit-content-seo` | rank-math | rank-math-content | Audit Rank Math Content SEO |
| `rank-math/` | `rank-math/audit-faq-links` | rank-math | rank-math-content | Audit Rank Math FAQ Blocks |
| `rank-math/` | `rank-math/bulk-update-meta` | rank-math | rank-math-content | Bulk Update Rank Math Meta |
| `rank-math/` | `rank-math/get-inbound-links` | rank-math | rank-math-content | Get Inbound Internal Links |
| `rank-math/` | `rank-math/get-primary-term` | rank-math | rank-math-content | Get Rank Math Primary Term |
| `rank-math/` | `rank-math/get-rendered-head` | rank-math | rank-math-content | Get Rank Math Rendered Head |
| `rank-math/` | `rank-math/update-primary-term` | rank-math | rank-math-content | Update Rank Math Primary Term |
| `rank-math/` | `rank-math/update-seo-meta` | rank-math | rank-math-content | Update Rank Math SEO Meta |
| `rank-math/` | `rank-math/update-seo-scores` | rank-math | rank-math-content | Update Rank Math SEO Scores |
| `rank-math/` | `rank-math/get-content-ai-status` | rank-math | rank-math-content-ai | Get Rank Math Content AI Status |
| `rank-math/` | `rank-math/manage-content-ai-output` | rank-math | rank-math-content-ai | Manage Content AI Output |
| `rank-math/` | `rank-math/manage-content-ai-prompts` | rank-math | rank-math-content-ai | Manage Content AI Prompts |
| `rank-math/` | `rank-math/research-keyword` | rank-math | rank-math-content-ai | Research Keyword with Content AI |
| `rank-math/` | `rank-math/clear-indexing-log` | rank-math | rank-math-instant-indexing | Clear IndexNow Submission Log |
| `rank-math/` | `rank-math/get-indexing-log` | rank-math | rank-math-instant-indexing | Get IndexNow Submission Log |
| `rank-math/` | `rank-math/reset-indexing-key` | rank-math | rank-math-instant-indexing | Reset IndexNow API Key |
| `rank-math/` | `rank-math/submit-urls` | rank-math | rank-math-instant-indexing | Submit URLs to IndexNow |
| `rank-math/` | `rank-math/list-modules` | rank-math | rank-math-modules | List Rank Math Modules |
| `rank-math/` | `rank-math/set-module-state` | rank-math | rank-math-modules | Set Rank Math Module State |
| `rank-math/` | `rank-math/change-redirection-status` | rank-math | rank-math-redirections | Change Rank Math Redirection Status |
| `rank-math/` | `rank-math/create-redirection` | rank-math | rank-math-redirections | Create Rank Math Redirection |
| `rank-math/` | `rank-math/delete-redirections` | rank-math | rank-math-redirections | Delete Rank Math Redirections |
| `rank-math/` | `rank-math/delete-trashed-redirections` | rank-math | rank-math-redirections | Empty Rank Math Redirection Trash |
| `rank-math/` | `rank-math/export-redirections` | rank-math | rank-math-redirections | Export Rank Math Redirections |
| `rank-math/` | `rank-math/find-redirection` | rank-math | rank-math-redirections | Find Rank Math Redirection |
| `rank-math/` | `rank-math/get-redirection-stats` | rank-math | rank-math-redirections | Get Rank Math Redirection Stats |
| `rank-math/` | `rank-math/list-redirections` | rank-math | rank-math-redirections | List Rank Math Redirections |
| `rank-math/` | `rank-math/update-redirection` | rank-math | rank-math-redirections | Update Rank Math Redirection |
| `rank-math/` | `rank-math/get-role-capabilities` | rank-math | rank-math-role-manager | Get Rank Math Role Capabilities |
| `rank-math/` | `rank-math/reset-role-capabilities` | rank-math | rank-math-role-manager | Reset Rank Math Role Capabilities |
| `rank-math/` | `rank-math/get-llms-status` | rank-math | rank-math-routes | Get Rank Math llms.txt Status |
| `rank-math/` | `rank-math/refresh-llms-route` | rank-math | rank-math-routes | Refresh Rank Math llms.txt Route |
| `rank-math/` | `rank-math/delete-post-schemas` | rank-math | rank-math-schema | Delete Rank Math Post Schemas |
| `rank-math/` | `rank-math/get-schema-status` | rank-math | rank-math-schema | Get Rank Math Schema Status |
| `rank-math/` | `rank-math/update-post-schemas` | rank-math | rank-math-schema | Update Rank Math Post Schemas |
| `rank-math/` | `rank-math/get-seo-analysis-results` | rank-math | rank-math-seo-analysis | Get Cached Rank Math SEO Analysis |
| `rank-math/` | `rank-math/get-settings` | rank-math | rank-math-settings | Get Rank Math Settings |
| `rank-math/` | `rank-math/update-instant-indexing-settings` | rank-math | rank-math-settings | Update Rank Math Instant Indexing Settings |
| `rank-math/` | `rank-math/update-robots-txt` | rank-math | rank-math-settings | Update Rank Math robots.txt |
| `rank-math/` | `rank-math/get-sitemap-status` | rank-math | rank-math-sitemap | Get Rank Math Sitemap Status |
| `rank-math/` | `rank-math/invalidate-sitemap-cache` | rank-math | rank-math-sitemap | Invalidate Rank Math Sitemap Cache |
| `rank-math/` | `rank-math/list-sitemap-urls` | rank-math | rank-math-sitemap | List Rank Math Sitemap URLs |
| `rank-math/` | `rank-math/create-backup` | rank-math | rank-math-status | Create Rank Math Settings Backup |
| `rank-math/` | `rank-math/detect-seo-plugins` | rank-math | rank-math-status | Detect Other SEO Plugin Data |
| `rank-math/` | `rank-math/export-settings` | rank-math | rank-math-status | Export Rank Math Settings |
| `rank-math/` | `rank-math/get-status` | rank-math | rank-math-status | Get Rank Math Status |
| `rank-math/` | `rank-math/import-settings` | rank-math | rank-math-status | Import Rank Math Settings |
| `rank-math/` | `rank-math/list-backups` | rank-math | rank-math-status | List Rank Math Settings Backups |
| `rank-math/` | `rank-math/manage-backup` | rank-math | rank-math-status | Restore or Delete Rank Math Backup |
| `rank-math/` | `rank-math/run-maintenance-tool` | rank-math | rank-math-status | Run Rank Math Maintenance Tool |
| `recovery/` | `recovery/get-recovery-exit-url` | core | recovery | Get Recovery Mode Exit URL |
| `recovery/` | `recovery/get-recovery-mode-status` | core | recovery | Get Recovery Mode Status |
| `recovery/` | `recovery/list-paused-plugins` | core | recovery | List Paused Plugins |
| `recovery/` | `recovery/list-paused-themes` | core | recovery | List Paused Themes |
| `recovery/` | `recovery/list-recent-fatal-errors` | core | recovery | List Recent Fatal Errors |
| `recovery/` | `recovery/unpause-plugin` | core | recovery | Unpause Plugin |
| `recovery/` | `recovery/unpause-theme` | core | recovery | Unpause Theme |
| `settings/` | `settings/flush-permalink-structure` | core | permalinks | Reset / Flush Permalinks |
| `settings/` | `settings/get-permalink-structure` | core | permalinks | Get Permalink Structure |
| `settings/` | `settings/set-permalink-structure` | core | permalinks | Set Permalink Structure |
| `settings/` | `settings/get-site-icon` | core | site-identity | Get Site Icon |
| `settings/` | `settings/get-site-title` | core | site-identity | Get Site Title |
| `settings/` | `settings/get-tagline` | core | site-identity | Get Tagline |
| `settings/` | `settings/update-site-icon` | core | site-identity | Update Site Icon |
| `settings/` | `settings/update-site-logo` | core | site-identity | Update Site Logo |
| `settings/` | `settings/update-site-title` | core | site-identity | Update Site Title |
| `settings/` | `settings/update-tagline` | core | site-identity | Update Tagline |
| `settings/` | `settings/list-rewrite-rules` | settings | permalinks | List Rewrite Rules |
| `site-health/` | `site-health/get-site-health-info` | core | read | Get Site Health Info |
| `site-health/` | `site-health/get-site-health-status` | core | read | Get Site Health Status |
| `site-health/` | `site-health/get-site-maintenance-report` | core | site-health | Site Maintenance Report |
| `site-health/` | `site-health/get-maintenance-mode-status` | site-health | maintenance | Get Maintenance Mode Status |
| `site-health/` | `site-health/set-site-maintenance-mode` | site-health | maintenance | Set Site Maintenance Mode |
| `site-health/` | `site-health/unset-site-maintenance-mode` | site-health | maintenance | Unset Site Maintenance Mode |
| `taxonomies/` | `taxonomies/get-taxonomy` | core | taxonomies | Get Taxonomy |
| `taxonomies/` | `taxonomies/list-cpt-taxonomies` | core | taxonomies | Get CPT Taxonomies |
| `taxonomies/` | `taxonomies/list-taxonomies` | core | taxonomies | List Taxonomies |
| `taxonomies/` | `taxonomies/assign-cpt-terms` | core | terms | Assign Terms |
| `taxonomies/` | `taxonomies/create-term` | core | terms | Create Term |
| `taxonomies/` | `taxonomies/delete-term` | core | terms | Delete Term |
| `taxonomies/` | `taxonomies/get-term` | core | terms | Get Term |
| `taxonomies/` | `taxonomies/list-terms` | core | terms | List Terms |
| `taxonomies/` | `taxonomies/set-term-image` | core | terms | Set Term Image |
| `taxonomies/` | `taxonomies/update-term` | core | terms | Update Term |
| `themes/` | `themes/list-themes` | themes | info | List Themes |
| `themes/` | `themes/list-theme-mods` | themes | introspection | List Theme Mods |
| `themes/` | `themes/activate-theme` | themes | lifecycle | Activate Theme |
| `themes/` | `themes/delete-theme` | themes | lifecycle | Delete Theme |
| `themes/` | `themes/get-theme-lifecycle-context` | themes | lifecycle | Get Theme Lifecycle Context |
| `themes/` | `themes/install-theme` | themes | lifecycle | Install Theme |
| `themes/` | `themes/update-theme` | themes | lifecycle | Update Theme |
| `users/` | `users/add-role-capability` | users | roles | Add Role Capability |
| `users/` | `users/create-role` | users | roles | Create Role |
| `users/` | `users/delete-role` | users | roles | Delete Role |
| `users/` | `users/get-current-user-access` | users | roles | Current User Access |
| `users/` | `users/get-role-capabilities` | users | roles | Get Role Capabilities |
| `users/` | `users/list-user-roles` | users | roles | List User Roles |
| `users/` | `users/remove-role-capability` | users | roles | Remove Role Capability |
| `users/` | `users/reset-role` | users | roles | Reset Role |
| `users/` | `users/add-user-capability` | users | users | Add User Capability |
| `users/` | `users/create-user` | users | users | Create User |
| `users/` | `users/delete-user` | users | users | Delete User |
| `users/` | `users/get-user` | users | users | Get User |
| `users/` | `users/list-users` | users | users | List Users |
| `users/` | `users/remove-user-capability` | users | users | Remove User Capability |
| `users/` | `users/reset-user-password` | users | users | Reset User Password |
| `users/` | `users/update-user` | users | users | Update User |
| `widgets/` | `widgets/list-sidebars` | widgets | introspection | List Sidebars |
| `widgets/` | `widgets/list-widgets` | widgets | introspection | List Widgets |
