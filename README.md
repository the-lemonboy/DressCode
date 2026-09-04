# DressCode

**One-click AI styling for the WordPress classic editor — skill-driven, brand-consistent HTML optimization powered by GLM.**

English | [简体中文](./README.zh-CN.md)

[![WP](https://img.shields.io/badge/WordPress-5.6%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green)](./LICENSE)

## Features

- **Editor AI button** — An "AI Optimize" button and skill selector appear in the classic editor toolbar (Visual & Text modes) for posts, pages and products.
- **Skill system** — Each skill is a folder with a `SKILL.md` system prompt (plus optional `references/*.md`). Manage, edit, import via zip, or set a default from the admin UI.
- **Dual API dialects** — Works with any OpenAI-compatible `chat/completions` endpoint **or** Anthropic Messages `/v1/messages` endpoints (e.g. relays serving GLM models).
- **Selection-aware** — Optimize just the selected fragment, or the whole content when nothing is selected.
- **WooCommerce Custom Tabs** — Every local tab of the *WB Custom Product Tabs* panel gets its own AI button; tabs added at runtime are picked up automatically.
- **Server-side output minification** — Results are compressed (indentation, comments, inline CSS) before insertion, so the editor stays clean.
- **Prompt pre-cleaning** — Redundant paste artifacts (e.g. per-character `<span data-font-family>` wrappers) are unwrapped before sending, cutting tokens several-fold.
- **Self-healing default skill** — If the default skill's files go missing, they are rebuilt automatically from the built-in spec.
- **i18n** — Chinese source strings with a bundled English translation; admin UI follows the site language.

## Requirements

- WordPress 5.6+ with the **Classic Editor** plugin (or any setup where the classic `#content` editor is used)
- PHP 7.4+
- A GLM API key — from [open.bigmodel.cn](https://open.bigmodel.cn), [z.ai](https://z.ai), or any OpenAI/Anthropic-compatible relay serving GLM models
- WooCommerce + *WB Custom Product Tabs for WooCommerce* (optional, for the product-tab button)

## Installation

```bash
# Into wp-content/plugins/
git clone https://github.com/the-lemonboy/DressCode.git
```

Or download the repo as a zip and upload it via **Plugins → Add New → Upload Plugin**, then activate.

## Configuration

Open **DressCode → Settings**:

| Field | Description |
|---|---|
| GLM API Key | Your API key (masked password field) |
| API Format | `OpenAI-compatible (chat/completions)` or `Anthropic Messages (/v1/messages)` |
| Endpoint URL | Full endpoint URL, e.g. `https://open.bigmodel.cn/api/paas/v4/chat/completions` or `https://your-relay.com/v1/messages` |
| Model | e.g. `glm-4.6`, `glm-4.5-air`, `glm-5.3` |
| Temperature | 0–2 (0.2–0.5 recommended) |

> **Model choice note:** reasoning models (e.g. `glm-5.3`) can take several minutes on full-page content and may exceed the 300 s request budget; `glm-4.6` typically completes in about a minute. For long pages, either select a faster model or optimize the content in sections.

## Skills

Open **DressCode → Skills**. A skill is a folder under `wp-content/uploads/dresscode-skills/` (or your custom uploads dir):

```
dresscode-skills/
└── my-style/
    ├── SKILL.md            # system prompt (required)
    └── references/
        └── tokens.md       # appended to the prompt automatically
```

- Create skills by writing `SKILL.md` directly in the admin form, or **import a zip** containing `SKILL.md` (at the root or inside a single subfolder) plus optional `references/*.md`.
- Mark one skill as **default** — it is used when the editor dropdown has no explicit selection.
- The bundled *DressCode Standard* skill is a generic style guide (rem-based type scale, 1.5 line height, radii, responsive baselines, CSS variables). Edit or replace it with your own brand spec.

## Usage

1. Open any post, page or product in the classic editor.
2. Pick a skill in the dropdown next to the media buttons.
3. Optionally select a fragment of content.
4. Click **AI Optimize** — the sparkle icon spins while the request runs (~1–2 min for long content).
5. The optimized, minified HTML is written back into the editor. Save when you are happy with it.

On WooCommerce products, each local Custom Tab has its own small **AI Optimize** button under its content box.

## i18n

Source strings are authored in Chinese (`zh_CN`). An English (`en_US`) translation is bundled in `lang/`. To regenerate after changing strings:

```bash
php tools/extract-strings.php     # rebuild lang/dresscode.pot
php tools/build-translations.php  # rebuild en_US .po/.mo from the map in the script
```

## Development

```
dresscode.php                       # bootstrap / plugin header
includes/
├── class-dresscode-skills.php      # skill CRUD, zip import, prompt resolution
├── class-dresscode-glm-client.php  # GLM client (OpenAI + Anthropic dialects)
├── class-dresscode-editor.php      # editor integration, AJAX, pre-clean, minify
└── class-wordpress-plugin-template*.php
assets/
├── js/admin.js                     # editor + tab buttons (min.js kept in sync)
└── css/admin.css                   # button, toast, menu icon styles
tools/                              # POT extraction & translation build scripts
```

Hooks of interest: `wp_ajax_dresscode_optimize`, filters `dresscode_settings_fields`.

## FAQ

**Nothing happens when I click the button.**
Check the toast in the lower-right corner — the most common causes are an invalid API key (401) or a timed-out generation (reasoning model on very long content). Retry, switch to a faster model, or optimize in selections.

**Can I use a relay/proxy?**
Yes. Any endpoint that speaks OpenAI `chat/completions` or Anthropic `/v1/messages` and serves GLM models works — set the API format and full endpoint URL accordingly.

**Where are skills stored?**
In `wp-content/uploads/dresscode-skills/` (following your `upload_path` option). A `.htaccess` guards the folder against listing and PHP execution.

## Credits & License

Built on the [WordPress Plugin Template](https://github.com/hlashbrooke/WordPress-Plugin-Template) by Hugh Lashbrooke. Licensed under the [GPL-2.0-or-later](./LICENSE).
