---
title: Code snippets
plugins: core
summary: Read before writing CSS or PHP code (snippets): when to code, rules, safety, how to switch a snippet on.
---
# Code snippets

Snippets are small pieces of CSS or PHP that you write for the site. They are stored by EO-BOTS (not in the theme), so theme and plugin updates keep them. Only administrators can use them; PHP may be disabled on some sites (list-snippets says `php_allowed`).

## Before writing code
1. Prefer no code: a setting, a block, a block style, the Site Editor, Global Styles or an installed plugin. Code is for what these cannot do.
2. Check list-snippets: improve an existing snippet instead of adding a second one for the same thing.
3. Look before guessing: read-source and search-source show the theme and plugin code. Find the real CSS classes, hook names and function signatures there. Never invent a hook.

## Workflow
1. create-snippet: one purpose per snippet, a clear title and a one or two sentence description in the user language (what it does, where).
2. Switch it on with set-snippet-status. The user sees the code and confirms. For PHP, the site is first loaded with the snippet; if it breaks, you get the error and it stays off: fix it with update-snippet and try again (twice at most, then explain).
3. Tell the user in one or two sentences what changed and where to look. Remind them they can switch it off from EO-BOTS > Snippets.

## CSS
- Use the theme presets: `var(--wp--preset--color--{slug})`, `var(--wp--preset--spacing--{slug})`, `var(--wp--preset--font-size--{slug})` (read the design-charter guide for the slugs).
- Target precise selectors found in the theme or page markup (`.wp-block-…`, block class names). Avoid `!important` unless overriding an inline style; never style `body`, `*` or bare tags globally.
- Mobile first: check how it looks on small screens, use `@media (min-width: 782px)` for wider layouts.
- Scope `front` for the public site (also loaded in the block editor canvas), `admin` for admin screens.

## PHP
- No opening `<?php` tag, no namespace. Code runs during `plugins_loaded`: put the work in hooks (`init`, `wp_enqueue_scripts`, `the_content`, `wp_footer`, …), never output HTML directly at load.
- Prefix every function, constant and option with `eobot_` plus a short name, and wrap named functions in `if ( ! function_exists( … ) )`. Closures are fine for hooks.
- Security: escape all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`), sanitize every input (`sanitize_text_field`, `absint`…), check rights with `current_user_can()` and use nonces for any form or action.
- Use WordPress APIs (`WP_Query`, `get_option`, `wp_remote_get`, `add_shortcode`…), never direct SQL unless `$wpdb->prepare()` is used.
- Forbidden unless the user explicitly asks and understands it: shell commands, `eval`, writing or deleting files, creating users or changing roles, sending emails, calling external services, changing options of other plugins. The card warns the user about such functions.
- Check that a plugin is active before using its functions (`class_exists`, `function_exists`).
- Shortcode example:
```php
add_shortcode( 'eobot_year', function () {
	return esc_html( wp_date( 'Y' ) );
} );
```
- Filter example:
```php
add_filter( 'excerpt_length', function () {
	return 30;
} );
```
