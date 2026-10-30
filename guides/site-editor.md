---
title: Site Editor (header, footer, menus, templates)
plugins: core
summary: Read before changing the header, the footer, the menu or a page template of a block theme.
---
# Site Editor

Block themes build every page from templates (page, single, archive…) made of template parts (header, footer, sidebar). A change there shows on the whole site, so the user always confirms, and the theme original can be restored (reset-template).

## Which tool
- Menu links (add, remove, rename, reorder a link): list-navigations, then update-navigation. Do not touch the header for that.
- Header or footer layout, logo, site title, footer text, columns, copyright: list-templates (type "part"), get-template, update-template.
- Structure of a kind of page (e.g. show the featured image on posts): list-templates (type "template").
- A page template for one page (e.g. no title): use the `template` field of create-content / update-content instead.
- Classic themes have no Site Editor: menus are under Appearance > Menus and the bot cannot change them yet.

## Editing a template or part
1. get-template, then send the full markup back with only your change. Keep every existing block, attribute and `metadata` you do not mean to change.
2. Keep the layout blocks (group with `"align":"full"` / `"wide"` and `"layout"`) and the theme presets (`var:preset|spacing|30`, `"backgroundColor":"base"`), as in the design charter.
3. Template parts are included with `<!-- wp:template-part {"slug":"footer","area":"footer"} /-->` (add `"theme":"…"` only if it was there). Never include a part in itself.
4. Useful blocks: core/site-title, core/site-logo, core/site-tagline, core/navigation, core/social-links, core/search, core/paragraph, core/columns.
5. Copyright with the current year: a paragraph cannot compute it; write the year or use a snippet shortcode.

## Menus
- The header shows a menu with `<!-- wp:navigation {"ref":123, …} /-->`: 123 is the wp_navigation post. Keep the other attributes (overlay, layout…).
- The menu content is only the links, no `core/navigation` wrapper:
```
<!-- wp:navigation-link {"label":"Accueil","type":"page","id":6,"url":"https://example.com/","kind":"post-type"} /-->
<!-- wp:navigation-link {"label":"Contact","type":"page","id":12,"url":"https://example.com/contact/","kind":"post-type"} /-->
<!-- wp:navigation-link {"label":"Blog Eoxia","url":"https://www.eoxia.com","kind":"custom","opensInNewTab":true} /-->
<!-- wp:navigation-submenu {"label":"Services","type":"page","id":20,"url":"https://example.com/services/","kind":"post-type"} -->
<!-- wp:navigation-link {"label":"Création de site","type":"page","id":21,"url":"https://example.com/services/site/","kind":"post-type"} /-->
<!-- /wp:navigation-submenu -->
```
- Take page IDs and URLs from list-content: never invent them. Short labels (1 to 3 words), 4 to 7 top links, the most important first, Contact last.
- `<!-- wp:page-list /-->` lists every published page automatically. Replace it with links when the user wants to choose the menu.
- To add a second menu (e.g. in the footer): create-navigation, then put `<!-- wp:navigation {"ref":NEW_ID} /-->` in the part with update-template.
