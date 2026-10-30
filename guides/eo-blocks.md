---
title: EO Blocks
plugins: eo-blocks/eo-blocks.php
summary: Scroll animations on any block, text shadows, clickable groups, carousel, tabs, accordion/FAQ, gallery with lightbox, marquee, sticky, table of contents.
---
# EO Blocks

All EO Blocks blocks are rendered by PHP: write the block comment with its attributes and put only inner blocks inside. No wrapper HTML of your own.

The extensions below are attributes added to the comment of ANY block, core blocks included. They never change the saved HTML, so they are always safe.

## Scroll animations (any block)
- `eoAnimationIn`: animation when the block enters the screen.
- `eoAnimationOut`: animation when it leaves (rarely needed, leave it out by default).
- Values: `fade`, `scale-up`, `scale-down`, `slide-top`, `slide-right`, `slide-bottom`, `slide-left`, `rotate`, `bounce`, `flip`.

```
<!-- wp:group {"align":"full","layout":{"type":"constrained"},"eoAnimationIn":"fade"} -->
<!-- wp:image {"id":528,"sizeSlug":"large","linkDestination":"none","eoAnimationIn":"slide-right"} -->
<!-- wp:column {"eoAnimationIn":"slide-bottom"} -->
```

Good taste:
- Animate sections, images, cards and columns, not every paragraph. One animation per element.
- `fade` or `slide-bottom` for sections and titles; `slide-left` / `slide-right` alternated for image + text rows; `scale-up` for figures or cards.
- In a row of columns, give each column the same `slide-bottom`: they appear one after the other while scrolling.
- `rotate`, `bounce` and `flip` only on playful sites, and only on a small element.
- Not available on core/html, core/shortcode, core/block, template parts and eo-blocks/slide (animate the carousel itself).

## Text shadow (headings, paragraphs, buttons)
`"eoTextShadow":"natural"` with `natural`, `deep`, `sharp`, `outlined`, `crisp` or `glow`. Use it on titles placed over an image (cover) to keep them readable, `glow` for a neon look.

## Clickable group (core/group)
Makes the whole group a link, for cards:
```
<!-- wp:group {"layout":{"type":"constrained"},"blockLink":{"url":"https://example.com/services/","id":489,"title":"Services","type":"page","kind":"post-type"}} -->
```
Use the real page ID, title and URL (list-content).

## Columns per device (core/columns)
`"eoResponsiveColumns":{"enabled":true,"tablet":2,"mobile":1}` keeps several columns on tablet or mobile instead of stacking (e.g. 4 logos → 2 per row on mobile).

## Show by screen width (any block)
`"eoBreakpoint":{"enabled":true,"min":0,"max":781}` (pixels, one bound may be left out). Example: a mobile-only call to action.

## Carousel: eo-blocks/carousel with eo-blocks/slide
Each slide holds any blocks (image, testimonial group, card…).
Attributes: `slidesPerView` (1), `loop` (true), `autoplay` (false), `autoplayDelay` in ms (use 4000 or more with autoplay), `speed` (300), `pagination` (true), `navigation` (true), `navigationPosition` (`inside` | `outside`), `spaceBetween` in px, `mobileSlidesPerView` (1), `mainColor` (hex, take a theme color), `marquee` (continuous scroll).
Use it for portfolios, testimonials, team, logos (slidesPerView 4, autoplay, mobileSlidesPerView 2).
```
<!-- wp:eo-blocks/carousel {"slidesPerView":3,"spaceBetween":24,"autoplay":true,"autoplayDelay":5000,"mobileSlidesPerView":1,"navigationPosition":"outside","eoAnimationIn":"fade"} -->
<!-- wp:eo-blocks/slide -->
<!-- wp:image {"id":728,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://example.com/wp-content/uploads/photo-1024x640.jpg" alt="" class="wp-image-728"/></figure>
<!-- /wp:image -->
<!-- /wp:eo-blocks/slide -->
<!-- wp:eo-blocks/slide -->
…
<!-- /wp:eo-blocks/slide -->
<!-- /wp:eo-blocks/carousel -->
```

## Accordion / FAQ: eo-blocks/accordion
One accordion per question; the inner blocks are the answer.
Attributes: `title`, `subtitle`, `titleTag` (`h2`…`h4`, default h2), `isOpened` (false), `displaySchema` (true adds FAQ structured data for Google: use it on FAQ sections).
```
<!-- wp:eo-blocks/accordion {"title":"How long does delivery take?","titleTag":"h3","displaySchema":true} -->
<!-- wp:paragraph -->
<p>Between 2 and 4 working days in mainland France.</p>
<!-- /wp:paragraph -->
<!-- /wp:eo-blocks/accordion -->
```

## Tabs: eo-blocks/tabs
Structure: tabs → tabs-buttons (one tabs-buttons-inner per tab) + tabs-contents (one tabs-contents-inner per tab). Each tab has a unique `tabKey` shared by its button and its content; `activeTabIndex` on the parent is the tabKey of the tab open at first.
```
<!-- wp:eo-blocks/tabs {"activeTabIndex":"tab-offer-1"} -->
<!-- wp:eo-blocks/tabs-buttons -->
<!-- wp:eo-blocks/tabs-buttons-inner {"tabKey":"tab-offer-1"} -->
<!-- wp:paragraph -->
<p>Starter</p>
<!-- /wp:paragraph -->
<!-- /wp:eo-blocks/tabs-buttons-inner -->
<!-- wp:eo-blocks/tabs-buttons-inner {"tabKey":"tab-offer-2"} -->
<!-- wp:paragraph -->
<p>Premium</p>
<!-- /wp:paragraph -->
<!-- /wp:eo-blocks/tabs-buttons-inner -->
<!-- /wp:eo-blocks/tabs-buttons -->
<!-- wp:eo-blocks/tabs-contents -->
<!-- wp:eo-blocks/tabs-contents-inner {"tabKey":"tab-offer-1"} -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Starter offer</h3>
<!-- /wp:heading -->
<!-- /wp:eo-blocks/tabs-contents-inner -->
<!-- wp:eo-blocks/tabs-contents-inner {"tabKey":"tab-offer-2"} -->
…
<!-- /wp:eo-blocks/tabs-contents-inner -->
<!-- /wp:eo-blocks/tabs-contents -->
<!-- /wp:eo-blocks/tabs -->
```
Use it for offers, services by audience, before/after: content the visitor compares.

## Gallery: eo-blocks/gallery
Self-closing block; images go in `items`, taken from list-media:
```
<!-- wp:eo-blocks/gallery {"items":[{"id":728,"type":"image","url":"https://example.com/wp-content/uploads/photo.jpg","alt":"","caption":"","width":1280,"height":800}],"layout":"masonry","columns":3,"mobileColumns":2,"gap":16,"radius":12,"eoAnimationIn":"fade"} /-->
```
Other attributes: `layout` (`grid` | `masonry` | `carousel`), `crop` (true), `aspectRatio` ("4/3"), `hoverZoom` (true), `showCaptions` (true), `linkTo` (`lightbox` | `media` | `attachment` | `none`, default lightbox).

## Marquee: eo-blocks/marquee
Its inner blocks scroll horizontally without end: logos, keywords, a short claim. Keep the content short.

## Sticky: eo-blocks/sticky
Its inner blocks stay pinned on screen while scrolling (e.g. a small "Call us" button). `contentPosition` ("top right", "bottom right"…), `displayMobile` (true). One per page at most.

## Table of contents: eo-blocks/summary
Self-closing; lists the headings of the page with anchors. For long pages (guides, legal pages). `orientation` (`horizontal` | `vertical`).

## Other blocks, only when the site already uses them
- `eo-blocks/map` needs a map created in the EO Blocks admin: reuse a `mapId` found with find-block-examples, never invent one.
- `eo-blocks/google-review-card` and `eo-blocks/google-review-prompt` need the Google reviews connection set up in EO Blocks.
- `eo-blocks/kpi-contentment` (`blockLabel`, `contentmentLabel` as a percentage), `eo-blocks/search`.
