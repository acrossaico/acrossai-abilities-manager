# Quickstart: Quick Connect Onboarding Wizard (Feature 099)

## Prerequisites

- **Node ≥ 20** — `npm run build` fails silently on older versions (`DEC-NODE-20-BUILD-REQUIRED`)
- PHP 8.1+, WordPress 6.9+
- Local test site: `wordpress-7-0` (LocalWP)

WP-CLI on this site needs the LocalWP socket:

```bash
cd /Users/raftaar1191/local-sites/wordpress-7-0/app/public
wp <command> --exec="define('DB_HOST','localhost:/Users/raftaar1191/Library/Application Support/Local/run/t739Logms/mysql/mysqld.sock');"
```

## Build

```bash
cd wp-content/plugins/acrossai-abilities-manager
node -v            # must be >= 20
npm run build      # emits build/js/quick-connect.js + .asset.php + build/css/quick-connect.css
```

## First-run check

```bash
# Ensure the recommended transport is absent, then re-activate.
wp plugin deactivate acrossai-mcp-manager
wp plugin deactivate acrossai-abilities-manager && wp plugin activate acrossai-abilities-manager
```

Open any admin page. Expected: redirected once to
`admin.php?page=acrossai-abilities-manager&quick-connect=1&step=1`, WordPress chrome hidden, no
stray admin notices.

Reach it manually any time from the **Quick Connect** submenu, the admin toolbar, or the plugin's
Plugins-screen action link.

## Walk both paths

**Recommended path (5 screens)** — screens 1→5, choose AcrossAI MCP Manager (preselected), click
*Continue - Install the plugin*. Expect: pulsing-icon overlay, all controls locked, then a landing
in MCP Manager's own Quick Connect at step 1.

**Alternative path (7 screens, or 6 if already present)** — on screen 5 choose MCP Adapter →
instructions screen → after installing, confirm → enablement walkthrough → completion. If the
adapter is already present, the instructions screen is skipped entirely.

## Verification checklist

### Visual parity (SC-005)

```bash
cd wp-content/plugins
diff acrossai-abilities-manager/assets/quick-connect/acrossai-logo.svg \
     acrossai-mcp-manager/assets/quick-connect/acrossai-logo.svg && echo "logo OK"
diff acrossai-abilities-manager/assets/quick-connect/icon.svg \
     acrossai-mcp-manager/assets/quick-connect/icon.svg && echo "icon OK"
git -C acrossai-abilities-manager status --short assets/   # must be empty — assets are tracked
```

Then open both wizards side by side and compare header, progress bar, typography, buttons, cards,
and notices — at desktop width and below 640 px. Confirm the 96 px icon pulses at 1.6 s
(opacity 0.65→1, scale 0.96→1) on cold start, and that the translucent blurred overlay variant
appears during the install.

### Data accuracy (SC-004)

Screen 1's count must equal the Abilities screen total; screen 4's groups and counts must match the
Integrations page tabs exactly.

```bash
curl -s -b cookies.txt -H "X-WP-Nonce: $NONCE" \
  "$SITE/wp-json/acrossai/v1/quick-connect/state" | jq '.abilities.total, (.abilities.tabGroups|length)'
```

### Navigation (SC-006)

- Deep-link `?quick-connect=1&step=6` with no `method` → auto-skips forward, no dead end
- Browser Back/Forward stay in sync with the displayed screen
- Progress reads "N of 5" on the recommended path, "N of 7" (or 6) on the alternative

### Security

```bash
# As an Editor — both must return 403
curl -s -o /dev/null -w '%{http_code}\n' -b editor-cookies.txt \
  "$SITE/wp-json/acrossai/v1/quick-connect/state"

# Slug allowlist — must return 400
curl -s -X POST -b cookies.txt -H "X-WP-Nonce: $NONCE" -H 'Content-Type: application/json' \
  -d '{"slug":"acrossai-pro"}' \
  "$SITE/wp-json/acrossai/v1/quick-connect/install-plugin" | jq .code
```

Also confirm no failure response contains a filesystem path, and that
`grep -r dangerouslySetInnerHTML src/js/quick-connect/` returns nothing.

### Asset gating (SC-010)

Load the Abilities, Settings, and Integrations pages and confirm `quick-connect.js` appears in none
of them.

### Quality gates

```bash
composer run phpstan                  # level 8, zero errors
vendor/bin/phpcs --standard=phpcs.xml.dist <changed production files>
npx jest src/js/quick-connect
vendor/bin/phpunit --filter QuickConnect
```

## Rollback

Purely additive. Deactivating the plugin removes every surface; no schema, no options, and the only
persisted artifact — a 30-second transient — expires on its own.
