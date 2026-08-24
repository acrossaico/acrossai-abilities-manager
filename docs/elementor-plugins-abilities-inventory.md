# Elementor-Ecosystem Plugins — Abilities API Inventory

Reference snapshot of every ability registered (via `wp_register_ability()`) by the Elementor-related plugins installed under `wp-content/plugins/` and `wp-content/elementor-mcp/` on this local site.

**Scanned on:** 2026-08-12

## Plugins scanned

| Key | Plugin | Path |
|---|---|---|
| EL | Elementor (free) | `wp-content/plugins/elementor` |
| EL-Pro | Elementor Pro | `wp-content/plugins/elementor-pro` |
| PA | Premium Addons for Elementor | `wp-content/plugins/premium-addons-for-elementor` |
| AG | Angie | `wp-content/plugins/angie` |
| MCP | Elementor MCP | `wp-content/elementor-mcp` *(not under /plugins)* |

**Elementor?** column: ✓ related to Elementor · ✗ generic WP/other · ~ depends on plugin

## Totals

| Plugin | Categories | Ability registrations |
|---|---|---|
| Elementor (free) | 1 | 5 |
| Elementor Pro | 0 | 0 |
| Premium Addons for Elementor | 6 | 35 |
| Angie | 2 | 9 |
| Elementor MCP | 1 | 186 |
| **Total** | **10** | **235** |

Unique rows after collapsing shared base-names across plugins: **212** (23 registrations collapsed into 17 shared rows).

| Bucket | Rows |
|---|---|
| ✓ Elementor-related | 68 |
| ✗ Not Elementor-related | 141 |
| ~ Mixed (varies per plugin) | 3 |

## Categories registered

| Plugin | Category slug | Notes |
|---|---|---|
| EL | `elementor` | Elementor page builder data, global classes, and variables |
| PA | `pa-discovery` | Read-only site/content state |
| PA | `pa-build` | Create/edit/remove Elementor elements |
| PA | `pa-media` | Read and add images |
| PA | `pa-dashboard` | Manage PA settings |
| PA | `pa-page-post-management` | Create/manage pages |
| PA | `pa-copy-paste` | Cross-domain copy & paste of elements |
| AG | `angie-mcp` | Angie MCP adapter tools for `/mcp/angie` |
| AG | `super-admin` | Direct server-side operations for Angie super admin |
| MCP | `emcp-tools` | MCP Tools for Elementor |

## Consolidated ability table

| # | Ability | Description | Plugin(s) | Category | Elementor? |
|---|---|---|---|---|---|
| 1 | `acf-read` | Advanced Custom Fields read | MCP | emcp-tools | ✗ |
| 2 | `acf-write` | Advanced Custom Fields write | MCP | emcp-tools | ✗ |
| 3 | `activate-plugin` | Activate plugin | MCP | emcp-tools | ✗ |
| 4 | `add-atomic-button` | Add Elementor atomic button | MCP | emcp-tools | ✓ |
| 5 | `add-atomic-divider` | Add Elementor atomic divider | MCP | emcp-tools | ✓ |
| 6 | `add-atomic-heading` | Add Elementor atomic heading | MCP | emcp-tools | ✓ |
| 7 | `add-atomic-image` | Add Elementor atomic image | MCP | emcp-tools | ✓ |
| 8 | `add-atomic-paragraph` | Add Elementor atomic paragraph | MCP | emcp-tools | ✓ |
| 9 | `add-atomic-svg` | Add Elementor atomic SVG | MCP | emcp-tools | ✓ |
| 10 | `add-atomic-video` | Add Elementor atomic video | MCP | emcp-tools | ✓ |
| 11 | `add-atomic-widget` | Add generic Elementor atomic widget | MCP | emcp-tools | ✓ |
| 12 | `add-atomic-youtube` | Add Elementor atomic YouTube embed | MCP | emcp-tools | ✓ |
| 13 | `add-block` | Add Gutenberg block | MCP | emcp-tools | ✗ |
| 14 | `add-code-snippet` | Add code snippet | MCP | emcp-tools | ✗ |
| 15 | `add-container` | Add Elementor v3 container | PA, MCP | pa-build / emcp-tools | ✓ |
| 16 | `add-custom-css` | Add global custom CSS | MCP | emcp-tools | ✗ |
| 17 | `add-custom-js` | Add global custom JavaScript | MCP | emcp-tools | ✗ |
| 18 | `add-div-block` | Add Elementor atomic div block | MCP | emcp-tools | ✓ |
| 19 | `add-flexbox` | Add Elementor v4 flexbox | PA, MCP | pa-build / emcp-tools | ✓ |
| 20 | `add-free-widget` | Add free Elementor widget | MCP | emcp-tools | ✓ |
| 21 | `add-pro-widget` | Add Elementor Pro widget | MCP | emcp-tools | ✓ |
| 22 | `add-stock-image` | Insert stock image into library | MCP | emcp-tools | ✗ |
| 23 | `analyze-performance` | Site performance analysis | MCP | emcp-tools | ✗ |
| 24 | `apply-template` | Apply Elementor template | MCP | emcp-tools | ✓ |
| 25 | `astra-read` | Astra theme read | MCP | emcp-tools | ✗ |
| 26 | `astra-write` | Astra theme write | MCP | emcp-tools | ✗ |
| 27 | `batch-update` | Bulk update Elementor elements | MCP | emcp-tools | ✓ |
| 28 | `build-page` | Composite Elementor page builder | MCP | emcp-tools | ✓ |
| 29 | `call-tool` | Generic dispatcher: invoke a tool | MCP | emcp-tools | ✗ |
| 30 | `cf7-read` | Contact Form 7 read | MCP | emcp-tools | ✗ |
| 31 | `cf7-write` | Contact Form 7 write | MCP | emcp-tools | ✗ |
| 32 | `change-post-status` | Publish/draft/etc. | PA | pa-page-post-management | ✗ |
| 33 | `check-elementor-element` | Check if post uses an Elementor element | PA | pa-discovery | ✓ |
| 34 | `check-import-compatibility` | Verify cross-site copy compatibility | PA | pa-copy-paste | ✓ |
| 35 | `clear-dynamic-assets` | Clear Premium Addons dynamic assets | PA | pa-dashboard | ✓ |
| 36 | `cloud-backup` | Cloud backup | MCP | emcp-tools | ✗ |
| 37 | `cloud-config-sync` | Cloud config sync | MCP | emcp-tools | ✗ |
| 38 | `cloud-list` | List cloud items | MCP | emcp-tools | ✗ |
| 39 | `cloud-marketplace-install` | Install from cloud marketplace | MCP | emcp-tools | ✗ |
| 40 | `cloud-marketplace-list` | List cloud marketplace items | MCP | emcp-tools | ✗ |
| 41 | `cloud-pull` | Pull from cloud | MCP | emcp-tools | ✗ |
| 42 | `cloud-status` | Cloud status | MCP | emcp-tools | ✗ |
| 43 | `create-elementor-page-guide` | Playbook for building Elementor via execute-php | AG | super-admin | ✓ |
| 44 | `create-elementor-template` | Create Elementor template | PA | pa-page-post-management | ✓ |
| 45 | `create-elementor-theme-template` | Create Elementor theme template | MCP | emcp-tools | ✓ |
| 46 | `create-global-class` | Create Elementor global CSS class | MCP | emcp-tools | ✓ |
| 47 | `create-page` | Create Elementor page | EL, PA, MCP | elementor / pa-page-post-management / emcp-tools | ✓ |
| 48 | `create-php-snippet` | Create PHP snippet (draft) | MCP | emcp-tools | ✗ |
| 49 | `create-popup` | Create Elementor popup | MCP | emcp-tools | ✓ |
| 50 | `create-post` | Create WP post | MCP | emcp-tools | ✗ |
| 51 | `create-redirect` | Create redirect | MCP | emcp-tools | ✗ |
| 52 | `create-theme-php-template` | Create classic theme PHP template | MCP | emcp-tools | ✗ |
| 53 | `create-theme-template` | Create block-theme template | MCP | emcp-tools | ✗ |
| 54 | `create-user` | Create WP user | MCP | emcp-tools | ✗ |
| 55 | `deactivate-plugin` | Deactivate plugin | MCP | emcp-tools | ✗ |
| 56 | `delete-file` | Delete file on server | MCP | emcp-tools | ✗ |
| 57 | `delete-global-class` | Delete Elementor global class | MCP | emcp-tools | ✓ |
| 58 | `delete-media` | Delete media item | MCP | emcp-tools | ✗ |
| 59 | `delete-page-content` | Wipe Elementor page content | MCP | emcp-tools | ✓ |
| 60 | `delete-php-snippet` | Delete PHP snippet | MCP | emcp-tools | ✗ |
| 61 | `delete-plugin` | Delete plugin | MCP | emcp-tools | ✗ |
| 62 | `delete-post` | Delete WP post | MCP | emcp-tools | ✗ |
| 63 | `delete-redirect` | Delete redirect | MCP | emcp-tools | ✗ |
| 64 | `delete-theme` | Delete theme | MCP | emcp-tools | ✗ |
| 65 | `delete-theme-php-template` | Delete classic-theme PHP template | MCP | emcp-tools | ✗ |
| 66 | `delete-theme-template` | Delete block-theme template | MCP | emcp-tools | ✗ |
| 67 | `detect-atomic-support` | Detect Elementor Atomic support | PA | pa-discovery | ✓ |
| 68 | `detect-elementor-version` | Detect Elementor version | MCP | emcp-tools | ✓ |
| 69 | `disable-unused-widgets` | Disable unused PA widgets | PA | pa-dashboard | ✓ |
| 70 | `discover-abilities` | Discover abilities (Angie MCP adapter) | AG | angie-mcp | ✗ |
| 71 | `dispatch-wp-cli` | Async WP-CLI dispatch | MCP | emcp-tools | ✗ |
| 72 | `duplicate-block` | Duplicate Gutenberg block | MCP | emcp-tools | ✗ |
| 73 | `duplicate-element` | Duplicate Elementor element | MCP | emcp-tools | ✓ |
| 74 | `duplicate-post` | Duplicate page/post | PA | pa-page-post-management | ✗ |
| 75 | `edit-file` | Edit file on server | MCP | emcp-tools | ✗ |
| 76 | `execute-ability` | Execute another ability | AG | angie-mcp | ✗ |
| 77 | `execute-php` | Execute arbitrary PHP (super admin) | AG | super-admin | ✗ |
| 78 | `export-content` | Export content | MCP | emcp-tools | ✗ |
| 79 | `export-elements` | Export Elementor elements for copy | PA | pa-copy-paste | ✓ |
| 80 | `export-page` | Export Elementor page | MCP | emcp-tools | ✓ |
| 81 | `export-sandbox-artifact` | Export sandbox artifact | MCP | emcp-tools | ✗ |
| 82 | `find-broken-links` | Broken link scan | MCP | emcp-tools | ✗ |
| 83 | `find-element` | Locate Elementor element | MCP | emcp-tools | ✓ |
| 84 | `get-ability-info` | Ability I/O schema | AG | angie-mcp | ✗ |
| 85 | `get-addon-schema` | PA addon schema | PA | pa-discovery | ✓ |
| 86 | `get-block-schema` | Gutenberg block schema | MCP | emcp-tools | ✗ |
| 87 | `get-change` | Get transaction change | MCP | emcp-tools | ✗ |
| 88 | `get-container-schema` | Elementor container schema | MCP | emcp-tools | ✓ |
| 89 | `get-element-settings` | Elementor element settings | PA, MCP | pa-discovery / emcp-tools | ✓ |
| 90 | `get-global-settings` | Elementor global settings | PA, MCP | pa-discovery / emcp-tools | ✓ |
| 91 | `get-globals` | Elementor global classes & variables | EL | elementor | ✓ |
| 92 | `get-id-by-title` | Resolve post/page ID by title | PA | pa-discovery | ✗ |
| 93 | `get-media` | Get media item | MCP | emcp-tools | ✗ |
| 94 | `get-page-snapshot` | Normalized Elementor page snapshot | MCP | emcp-tools | ✓ |
| 95 | `get-page-structure` | Elementor element tree | EL, PA, MCP | elementor / pa-discovery / emcp-tools | ✓ |
| 96 | `get-php-snippet` | Get PHP snippet | MCP | emcp-tools | ✗ |
| 97 | `get-post` | Get WP post | MCP | emcp-tools | ✗ |
| 98 | `get-post-blocks` | Get Gutenberg blocks for a post | MCP | emcp-tools | ✗ |
| 99 | `get-settings` | Get plugin/site settings | PA, MCP | pa-dashboard / emcp-tools | ~ |
| 100 | `get-super-admin-status` | Angie super admin enabled? | AG | super-admin | ✗ |
| 101 | `get-theme-info` | Active theme info | PA | pa-discovery | ✗ |
| 102 | `get-theme-php-template` | Get classic-theme PHP template | MCP | emcp-tools | ✗ |
| 103 | `get-theme-styles` | Active theme styles | PA | pa-discovery | ✗ |
| 104 | `get-theme-template` | Get block-theme template | MCP | emcp-tools | ✗ |
| 105 | `get-tool-schema` | Generic dispatcher: tool schema | MCP | emcp-tools | ✗ |
| 106 | `get-user` | Get WP user | MCP | emcp-tools | ✗ |
| 107 | `get-widget-schema` | Elementor widget schema | PA, MCP | pa-discovery / emcp-tools | ✓ |
| 108 | `get-wp-cli-job` | Get WP-CLI job status | MCP | emcp-tools | ✗ |
| 109 | `import-elements` | Import copied Elementor elements | PA | pa-copy-paste | ✓ |
| 110 | `import-sandbox-artifact` | Import sandbox artifact | MCP | emcp-tools | ✗ |
| 111 | `import-template` | Import Elementor template onto page | MCP | emcp-tools | ✓ |
| 112 | `insert-pattern` | Insert Gutenberg pattern | MCP | emcp-tools | ✗ |
| 113 | `insert-widget` | Insert Premium Addons widget | PA | pa-build | ✓ |
| 114 | `install-plugin` | Install plugin | MCP | emcp-tools | ✗ |
| 115 | `install-theme` | Install theme | MCP | emcp-tools | ✗ |
| 116 | `kadence-blocks-read` | Kadence Blocks read | MCP | emcp-tools | ✗ |
| 117 | `kadence-blocks-write` | Kadence Blocks write | MCP | emcp-tools | ✗ |
| 118 | `kadence-read` | Kadence theme read | MCP | emcp-tools | ✗ |
| 119 | `kadence-write` | Kadence theme write | MCP | emcp-tools | ✗ |
| 120 | `list-available-elements` / `list-widgets` | List Elementor widgets | PA (`list-available-elements`), MCP (`list-widgets`) | pa-discovery / emcp-tools | ✓ |
| 121 | `list-blocks` | List Gutenberg blocks | MCP | emcp-tools | ✗ |
| 122 | `list-changes` | List transaction ledger | MCP | emcp-tools | ✗ |
| 123 | `list-code-snippets` | List code snippets | MCP | emcp-tools | ✗ |
| 124 | `list-condition-targets` | Elementor template condition targets | MCP | emcp-tools | ✓ |
| 125 | `list-content-exports` | List content export jobs | MCP | emcp-tools | ✗ |
| 126 | `list-directory` | List directory under ABSPATH | AG, MCP | super-admin / emcp-tools | ✗ |
| 127 | `list-dynamic-tags` | List Elementor dynamic tags | MCP | emcp-tools | ✓ |
| 128 | `list-global-classes` | List Elementor global classes | MCP | emcp-tools | ✓ |
| 129 | `list-media` | List WP media library | PA, MCP | pa-media / emcp-tools | ✗ |
| 130 | `list-pa-addons` | List available Premium Addons | PA | pa-discovery | ✓ |
| 131 | `list-pages` | List Elementor-built pages | EL, PA, MCP | elementor / pa-discovery / emcp-tools | ✓ |
| 132 | `list-patterns` | List Gutenberg patterns | MCP | emcp-tools | ✗ |
| 133 | `list-php-snippets` | List PHP snippets | MCP | emcp-tools | ✗ |
| 134 | `list-plugins` | List installed plugins | MCP | emcp-tools | ✗ |
| 135 | `list-post-types` | List post types | MCP | emcp-tools | ✗ |
| 136 | `list-posts` | List posts | MCP | emcp-tools | ✗ |
| 137 | `list-redirects` | List redirects | MCP | emcp-tools | ✗ |
| 138 | `list-taxonomies` | List taxonomies | MCP | emcp-tools | ✗ |
| 139 | `list-templates` | List Elementor templates | PA, MCP | pa-discovery / emcp-tools | ✓ |
| 140 | `list-theme-php-templates` | List classic-theme PHP templates | MCP | emcp-tools | ✗ |
| 141 | `list-theme-templates` | List block-theme templates | MCP | emcp-tools | ✗ |
| 142 | `list-themes` | List installed themes | MCP | emcp-tools | ✗ |
| 143 | `list-tools` | Generic dispatcher: list tools | MCP | emcp-tools | ✗ |
| 144 | `list-users` | List users | MCP | emcp-tools | ✗ |
| 145 | `list-wp-cli-jobs` | List WP-CLI jobs | MCP | emcp-tools | ✗ |
| 146 | `menu-read` | Nav menu read | MCP | emcp-tools | ✗ |
| 147 | `menu-write` | Nav menu write | MCP | emcp-tools | ✗ |
| 148 | `metabox-read` | Meta Box read | MCP | emcp-tools | ✗ |
| 149 | `metabox-write` | Meta Box write | MCP | emcp-tools | ✗ |
| 150 | `move-block` | Move Gutenberg block | MCP | emcp-tools | ✗ |
| 151 | `move-element` | Move Elementor element in tree | MCP | emcp-tools | ✓ |
| 152 | `rankmath-read` | Rank Math SEO read | MCP | emcp-tools | ✗ |
| 153 | `rankmath-write` | Rank Math SEO write | MCP | emcp-tools | ✗ |
| 154 | `read-file` | Read file under ABSPATH | AG, MCP | super-admin / emcp-tools | ✗ |
| 155 | `read-resource` | Read Angie markdown guide | AG | angie-mcp | ✗ |
| 156 | `reindex-search` | Rebuild search index | MCP | emcp-tools | ✗ |
| 157 | `remove-block` | Remove Gutenberg block | MCP | emcp-tools | ✗ |
| 158 | `remove-element` | Remove Elementor element | PA, MCP | pa-build / emcp-tools | ✓ |
| 159 | `reorder-elements` | Reorder Elementor sibling elements | MCP | emcp-tools | ✓ |
| 160 | `reorder-global-classes` | Reorder Elementor global classes | MCP | emcp-tools | ✓ |
| 161 | `resize-media` | Resize media item | MCP | emcp-tools | ✗ |
| 162 | `resolve-template` | Resolve Elementor template for URL | MCP | emcp-tools | ✓ |
| 163 | `restore-content` | Restore exported content | MCP | emcp-tools | ✗ |
| 164 | `rollback-change` | Roll back transaction | MCP | emcp-tools | ✗ |
| 165 | `run-wp-cli` | Run WP-CLI command | MCP | emcp-tools | ✗ |
| 166 | `save-as-template` | Save design as Elementor template | MCP | emcp-tools | ✓ |
| 167 | `scan-security` | Security/malware scan | MCP | emcp-tools | ✗ |
| 168 | `scan-usage` | Scan PA widget usage | PA | pa-dashboard | ✓ |
| 169 | `search-content` | Search pages/templates/widgets | MCP | emcp-tools | ~ |
| 170 | `search-files` | Search files on disk | MCP | emcp-tools | ✗ |
| 171 | `search-images` | Stock image search | MCP | emcp-tools | ✗ |
| 172 | `search-plugins` | Search plugin repo | MCP | emcp-tools | ✗ |
| 173 | `search-themes` | Search theme repo | MCP | emcp-tools | ✗ |
| 174 | `set-dynamic-tag` | Apply Elementor dynamic tag | MCP | emcp-tools | ✓ |
| 175 | `set-element-label` | Set Elementor element label | MCP | emcp-tools | ✓ |
| 176 | `set-elementor-template-conditions` / `set-template-conditions` | Set Elementor template display conditions | MCP | emcp-tools | ✓ |
| 177 | `set-popup-settings` | Configure Elementor popup | MCP | emcp-tools | ✓ |
| 178 | `set-post-terms` | Assign terms to a post | MCP | emcp-tools | ✗ |
| 179 | `sideload-image` | Sideload image from URL | MCP | emcp-tools | ✗ |
| 180 | `slimstat-read` | Slim SEO read | MCP | emcp-tools | ✗ |
| 181 | `slimstat-write` | Slim SEO write | MCP | emcp-tools | ✗ |
| 182 | `spectra-read` | Spectra read | MCP | emcp-tools | ✗ |
| 183 | `spectra-write` | Spectra write | MCP | emcp-tools | ✗ |
| 184 | `subscribe-newsletter` | Subscribe to PA newsletter | PA | pa-dashboard | ✗ |
| 185 | `switch-theme` | Switch active theme | MCP | emcp-tools | ✗ |
| 186 | `theme-read` | Active theme config read | MCP | emcp-tools | ✗ |
| 187 | `theme-write` | Active theme config write | MCP | emcp-tools | ✗ |
| 188 | `update-atomic-widget` | Update Elementor atomic widget | MCP | emcp-tools | ✓ |
| 189 | `update-block` | Update Gutenberg block | MCP | emcp-tools | ✗ |
| 190 | `update-container` | Update Elementor container | MCP | emcp-tools | ✓ |
| 191 | `update-element` / `update-element-settings` | Update Elementor element settings | PA (`update-element-settings`), MCP (`update-element`) | pa-build / emcp-tools | ✓ |
| 192 | `update-global-class` | Update Elementor global class | MCP | emcp-tools | ✓ |
| 193 | `update-global-colors` | Update Elementor global colors | MCP | emcp-tools | ✓ |
| 194 | `update-global-typography` | Update Elementor global typography | MCP | emcp-tools | ✓ |
| 195 | `update-media` | Update media item | MCP | emcp-tools | ✗ |
| 196 | `update-page-settings` | Update Elementor document settings | EL, MCP | elementor / emcp-tools | ✓ |
| 197 | `update-php-snippet` | Update PHP snippet | MCP | emcp-tools | ✗ |
| 198 | `update-plugin` | Update plugin | MCP | emcp-tools | ✗ |
| 199 | `update-post` | Update WP post | MCP | emcp-tools | ✗ |
| 200 | `update-redirect` | Update redirect | MCP | emcp-tools | ✗ |
| 201 | `update-setting` / `update-settings` | Update plugin/site settings | PA (`update-setting`), MCP (`update-settings`) | pa-dashboard / emcp-tools | ~ |
| 202 | `update-theme` | Update theme | MCP | emcp-tools | ✗ |
| 203 | `update-theme-php-template` | Update classic-theme PHP template | MCP | emcp-tools | ✗ |
| 204 | `update-theme-template` | Update block-theme template | MCP | emcp-tools | ✗ |
| 205 | `update-user` | Update WP user | MCP | emcp-tools | ✗ |
| 206 | `update-widget` | Update Elementor widget | MCP | emcp-tools | ✓ |
| 207 | `upload-media` | Upload image to library | PA | pa-media | ✗ |
| 208 | `upload-svg-icon` | Upload SVG icon | MCP | emcp-tools | ✗ |
| 209 | `validate-php-snippet` | Validate PHP snippet | MCP | emcp-tools | ✗ |
| 210 | `write-file` | Write file on server | MCP | emcp-tools | ✗ |
| 211 | `yoast-read` | Yoast SEO read | MCP | emcp-tools | ✗ |
| 212 | `yoast-write` | Yoast SEO write | MCP | emcp-tools | ✗ |

## Notes

- **Elementor Pro** registers zero abilities via the WordPress Abilities API — all Elementor first-party abilities live in the free `elementor` plugin under `modules/mcp/`.
- **Elementor MCP** is the largest surface by far (186 registrations) and is majority generic-WP: ~60 Elementor-specific, ~126 generic (WP-CLI, plugins, themes, users, menus, filesystem, third-party integrations).
- **Security-sensitive abilities** worth flagging before exposing over MCP:
  - `angie/execute-php` (Angie super-admin)
  - `read-file` / `list-directory` (Angie + MCP)
  - `write-file` / `edit-file` / `delete-file` / `run-wp-cli` (MCP)
  - `install-plugin` / `activate-plugin` / `switch-theme` / `install-theme` (MCP)
- Slug spellings above match the base name used in each plugin's `wp_register_ability()` call; some plugins prefix with a namespace (`elementor/…`, `emcp-tools/…`, `angie/…`) — the base name is used here for cross-plugin comparison.
