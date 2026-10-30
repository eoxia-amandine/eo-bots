# EO-BOTS guides

A guide teaches EO-BOTS how to use one plugin: its blocks, the exact markup and attributes, and when to use each feature. The bot sees the list of active guides (slug, title, summary) and reads a full guide with the `eobots/read-guide` ability when it needs it.

## Where guides live

- `wp-content/plugins/eo-bots/guides/*.md`: guides shipped with EO-BOTS.
- `wp-content/eo-bots-guides/*.md`: guides added by the site. A file with the same name replaces the shipped guide. Use this folder for your own plugins or to adapt a guide to a site: it survives EO-BOTS updates.
- Other folders can be added with the `eobots_guide_paths` filter.

## Format

One Markdown file per plugin. The file name (without `.md`) is the guide slug.

```
---
title: Contact Form 7
plugins: contact-form-7/wp-contact-form-7.php
summary: Insert contact forms; one line, shown to the bot in every conversation.
---
# Contact Form 7

Free text for the bot…
```

- `plugins`: plugin file(s) relative to `wp-content/plugins`, comma separated. The guide is used only when one of them is active. `core` means always.
- `summary`: one short line telling the bot when this guide is useful.
- `abilities` (optional): abilities registered by the plugin (WordPress Abilities API) that the bot may call, comma separated, `*` allowed: `abilities: yoast-seo/get-*, yoast-seo/update-post-seo-data`. Only list what the bot needs.
- `confirm` (optional): when the user must confirm before one of these abilities runs: `confirm: yoast-seo/update-*=live, yoast-seo/set-*=always`. `never`, `always`, or `live` (only when the targeted post, given as `id`, `post_id` or `permalink`, is published). Without a rule, read-only abilities run directly and the others always ask.

## Writing a good guide

- Write for the bot, not for humans: short, factual, exact.
- Give the exact block names, attribute names and allowed values, with a copy-ready markup example for each feature. Prefer markup copied from a real page that works.
- Say when to use each feature and when not to (taste, limits, settings the plugin needs first).
- Keep it under a few hundred lines: the whole guide is sent to the AI when the bot reads it.
