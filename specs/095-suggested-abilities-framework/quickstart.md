# Quickstart: Ability Suggestions Framework

## For ability authors — declaring a suggestion

Add a `suggested_abilities()` method to your ability class. That's it — no filter registration, no config file, no build step. The framework injects the entries into the ability's registered `args.meta.acrossai.suggested_abilities` automatically.

```php
namespace AcrossAI_Abilities_Manager\Includes\Abilities\Content;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

class Update_Page extends Ability_Definition {

    protected function ability(): array {
        return array( /* ...existing spec... */ );
    }

    protected function suggested_abilities(): array {
        return array(
            array(
                'slug'   => 'blocks/outline-post-blocks',
                'reason' => __( 'For narrow edits, outline first to locate the target block cheaply.', 'acrossai-abilities-manager' ),
                'saves'  => __( '~29K tokens vs full page rewrite on a 97 KB page', 'acrossai-abilities-manager' ),
            ),
            array(
                'slug'   => 'blocks/update-post-block',
                'reason' => __( 'Update just the located block without re-serializing the whole post.', 'acrossai-abilities-manager' ),
            ),
        );
    }
}
```

**Rules of thumb:**
- `slug` and `reason` are required; `saves` is optional.
- Prefer 1–2 suggestions per ability, ordered "most-primary-alternative first". Three or more starts to feel like noise to an AI reader.
- Point at concrete abilities registered somewhere on this site (or a well-known ability of a widely-installed companion plugin). Framework doesn't validate existence — invalid slugs simply confuse the AI.
- Keep `reason` under ~120 characters — it's read as inline hint text.

## For AI callers — seeing the suggestions

Suggestions appear in `mcp-adapter-get-ability-info` responses under `meta.acrossai.suggested_abilities`. They do NOT appear in `mcp-adapter-discover-abilities` — you have to inspect a specific ability's details to see its hints.

```json
{
  "name": "content/update-page",
  "label": "Update Page",
  "description": "Update an existing page...",
  "meta": {
    "acrossai": {
      "suggested_abilities": [
        {
          "slug": "blocks/outline-post-blocks",
          "reason": "For narrow edits, outline first to locate the target block cheaply.",
          "saves": "~29K tokens vs full page rewrite on a 97 KB page"
        },
        {
          "slug": "blocks/update-post-block",
          "reason": "Update just the located block without re-serializing the whole post."
        }
      ]
    }
  }
}
```

## For site administrators — turning suggestions off

1. Go to `Settings → AcrossAI → Abilities tab`
2. Find the "Ability Suggestions" section (between "Plugin Suggestions" and "Uninstall Settings")
3. Check the "Disable ability suggestions" checkbox
4. Click "Save Changes"

The setting takes effect on the very next AI request. No cache purge, no restart. Unchecking restores the previous behavior with no data loss (your ability declarations are unchanged; only the exposed payload is filtered).

## For contributors — running the tests

```sh
# Framework structural tests
./vendor/bin/phpunit --filter Test_Ability_Suggestions_Framework

# Kill-switch behavioural tests (uses tests/bootstrap.php's $__acrossai_test_options shim)
./vendor/bin/phpunit --filter Test_Ability_Suggestions_Kill_Switch

# Full suite must remain green
./vendor/bin/phpunit
```

## For contributors — live MCP smoke test

Assumes the plugin is loaded on `wordpress-7-0.local` (Local by Flywheel). After `composer dump-autoload -o`:

1. Call `mcp-adapter-get-ability-info` for `content/update-page` via the wordpress-7-0-default MCP server. Assert `response.meta.acrossai.suggested_abilities[0].slug == "blocks/outline-post-blocks"`.
2. Visit the admin settings tab, tick "Disable ability suggestions", save. Re-call `get-ability-info` for `content/update-page`. Assert `meta.acrossai.suggested_abilities` is absent.
3. Untick, save, re-call. Assert it returns.
