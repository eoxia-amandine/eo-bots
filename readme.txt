=== EO-BOTS - Mon agent IA ===
Contributors: eoxia
Tags: ai, assistant, agent, chatbot, content
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A cute AI agent you design yourself. It lives in the corner of your screen and runs your WordPress site for you through a simple chat.

== Description ==

Create your own EO-BOT like a video game character: pick an animal, a color and a hat, give it a name, and bring it to life. From then on it lives in the bottom right corner of your screen, in the admin and on your site, follows your mouse with its eyes, and does the work for you when you ask:

* "Create a Contact page" – it looks at your theme, your blocks and your existing pages first, then builds a draft that matches your site.
* "Fix the typos on this page", "Sum up this page" – it knows which page you are on.
* "Publish it" – it asks for your OK before anything goes live.
* "Put Contact last in the menu", "Add a copyright line in the footer" – it edits the menus, header, footer and templates of block themes.
* "How is my SEO?" – with Yoast SEO it audits the site and optimizes pages.
* "Hide the author name on posts" – it writes small CSS or PHP snippets, tested before they run (administrators only).
* "Remember: buttons always say Book now" – it keeps notes from your team and follows them in every conversation.

**Built on WordPress itself.** EO-BOTS uses the AI Client, the Connectors screen and the Abilities API that ship with WordPress 7.0. Nothing to configure twice: connect your AI provider once in Settings › Connectors.

**Guardrails.**

* New content is always created as a draft.
* Publishing, editing live content and moving to the trash always wait for your confirmation in the chat.
* Every change is journaled with an Undo button.
* The bot acts with your own rights: it can never do more than you can.
* Text read on your site is treated as data, never as instructions.

**One EO-BOT per user, one key per site.** Each user designs their own EO-BOT; the AI provider key is shared by the site.

== External services ==

EO-BOTS sends your chat messages, a short overview of your site (name, theme, active plugins, content counts) and the content the bot reads or writes (pages, templates, and the theme or plugin code it reads to write a snippet) to the AI provider you connected in Settings › Connectors (for example Anthropic, OpenAI or Google), through the WordPress AI Client. Nothing is sent before you connect a provider and start chatting. Usage is billed by your provider on your own API key. Read your provider's terms and privacy policy:

* Anthropic: https://www.anthropic.com/legal/consumer-terms – https://www.anthropic.com/legal/privacy
* OpenAI: https://openai.com/policies/terms-of-use – https://openai.com/policies/privacy-policy
* Google: https://ai.google.dev/gemini-api/terms – https://policies.google.com/privacy

== Installation ==

1. Install and activate EO-BOTS. You land on the creation screen.
2. Design your EO-BOT with the arrows, name it, and click "Create my EO-BOT".
3. Connect an AI provider with your API key in Settings › Connectors.
4. Click your EO-BOT in the bottom right corner and start chatting.

== Frequently Asked Questions ==

= Can I use my ChatGPT or Claude subscription? =

No. Plugins need an API key from the provider's developer platform. Usage is billed per use by the provider.

= Who can use the bot? =

Every user who can edit posts (filter `eobots_capability`). Each action is checked against the user's own capabilities.

= A snippet broke my site. What do I do? =

EO-BOTS tests every PHP snippet before switching it on and switches off a snippet that causes a fatal error. If your site still fails, open the secret safe mode address shown in EO-BOTS › Snippets, or add `define( 'EOBOTS_SAFE_MODE', true );` to wp-config.php.

== Changelog ==

= 0.1.10 =
* Leaving the page while the bot works asks for confirmation; if you leave anyway, the bot finishes its work on the next page. One step at a time per conversation (server lock).
* Code review: WordPress coding standards and Plugin Check rules pass; the system instruction moved to prompts/system.md.
* Readme: new features, FAQ about snippets.

= 0.1.9 =
* EO-BOTS has its own menu again, at the very bottom of the admin menu, with Snippets as a submenu.
* The product is named EO-BOTS everywhere; "your EO-BOT" still names your own bot.

= 0.1.8 =
* Memory: notes from the site team that all the EO-BOTS of the site follow. The bot saves one when you correct it (you confirm), and the chat panel has a Memory tab to read, add, edit and delete notes.

= 0.1.7 =
* Site Editor: EO-BOTS edits the header, the footer, the page templates and the navigation menus of block themes (always confirmed, undo and reset to the theme original).

= 0.1.6 =
* Code workshop: EO-BOTS writes CSS and PHP snippets, switched off until you confirm. PHP is syntax-checked and tested on the site before it runs, a snippet causing an error is switched off automatically, and a secret safe mode URL switches every PHP snippet off.
* EO-BOTS > Snippets screen.
* Read-only access to theme and plugin code for EO-BOTS.

= 0.1.5 =
* Yoast SEO: site SEO report, live page SEO analysis, focus keyphrase, and Yoast's own abilities enabled through its guide.
* Guides can enable abilities of other plugins and set their confirmation rules (abilities: and confirm: lines).

= 0.1.4 =
* UI/UX charter computed from theme.json and the site Global Styles (color roles, readable pairs, fonts, spacing roles, page templates).
* Automatic design review after every page creation or edit, fixed by the bot itself; review-design ability.
* Page template choice when creating or editing content.

= 0.1.3 =
* Plugin guides: one notice per plugin (guides/ and wp-content/eo-bots-guides/), read by the bot before using a plugin. Guides for WordPress design, EO Blocks and Gravity Forms.
* Find block examples already used on the site.
* Page design method in the bot instructions.

= 0.1.1 =
* EO-BOT settings page under Settings, AI model choice, popup after creation, chat bubbles, larger chat window, visible send button.

= 0.1.0 =
* First version: EO-BOT creator, floating bot, chat with history, content abilities with confirmation and undo.
