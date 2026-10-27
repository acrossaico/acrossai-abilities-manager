# Feature 131 — Generate an image with AI and add it to the Media Library

> Feature number is provisional — let `/speckit.specify` assign the next free one (130 is Redirection).

An AI client connected over MCP can already *store* an image it has in hand: `media/upload-media` turns base64, a URL, or a local file into a real attachment through `media_handle_sideload()`. What it cannot do is *make* one. This feature adds the missing half — a single ability that generates an image from a text prompt and lands it in the Media Library in one call.

## The generator lives in WordPress core, not in us

WordPress 7.0 ships a PHP AI client and a Connectors API in core:

- `wp-includes/php-ai-client/` — the `WordPress\AiClient` SDK.
- `wp-includes/ai-client.php` → `wp_ai_client_prompt( $prompt )`, a fluent builder that returns `WP_Error` instead of throwing.
- `wp-includes/connectors.php` — the Connectors API. Core injects each configured connector's API key into the client on `init` P20, so a plugin that calls the client gets authenticated providers for free.

So this ability calls core directly: build a prompt, ask for an inline (base64) image, generate, then sideload. **No dependency on any other plugin.** On WordPress < 7.0, or when no connector advertises image-generation support, the ability degrades to a clear message — it never fatals.

## Scope — one ability, `media/generate-image`, in the `acrossai-media` group

`media/generate-image` — **Input:** a `prompt`; `confirm: true` (required — each run makes a paid AI call); optional `aspect_ratio`, a `post_id` to attach to, `set_as_featured`, `title`/`alt_text`/`caption`/`description`/`filename`, and advanced `provider`/`model` overrides. It generates the image with the core client, sideloads the bytes into the Library, optionally sets it as a post's featured image, stamps a provenance meta key, and returns the attachment id, URL and the standard media object.

It sits in the same category and tab as the other media abilities (`acrossai-media`, tab_group `content`), so it is governed by the normal per-ability site toggle — no new gate.

## Constraints — traps, not preferences

- **Core-only, graceful.** Detect `wp_ai_client_prompt()` and an image-capable connector; degrade to a message otherwise. Never require or name another plugin.
- **One Utilities file owns the SDK.** All `wp_ai_client_prompt` / `WordPress\AiClient\*` access lives in `Utilities/Media/Ai_Image_Generator.php`; all `media_handle_sideload()` access in `Utilities/Media/Media_Sideload.php` — the same "third-party surface in one place" rule the Query Monitor and Redirection suites follow.
- **Paid call → confirm.** `requires_confirmation()` is true; the generation is gated on `confirm: true`.
- **Respect the upload allowlist.** The sideload applies `Mime_Types_Store::filter_upload_mimes` for the duration of the call, so a generated image obeys the "Upload Media Abilities" MIME configuration like `media/upload-media` does.
- **Additive, not destructive.** `readonly=false, destructive=false, idempotent=false`.

## Discoverability

- A one-paragraph notice in the **Upload Media Abilities** settings section telling the admin they can generate an image from a prompt and add it to the Library in one step.
- `media/upload-media` and `media/list-media` declare `media/generate-image` in their `suggested_abilities()`, so an AI client working with media is pointed at it.

## Verification

- With an image-capable connector configured, `media/generate-image` with a prompt + `confirm:true` returns a new attachment id, a reachable URL, and a file in `wp-content/uploads`.
- With `post_id` + `set_as_featured:true`, the post's thumbnail is the new attachment.
- Without `confirm`, it returns `confirmation_required` and generates nothing.
- With image support filtered off (no capable connector), it returns a clear "not available" message, no fatal.
- The settings notice renders; the ability appears in the Content tab / Media group; the two media abilities list it as a suggestion.

## Out of scope (v1)

- Image editing (reference-image input the SDK supports via `with_file()`).
- Any wp-admin Media Library button or block-editor UI.
- Size/model management UI; batch generation.
