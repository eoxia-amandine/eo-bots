---
title: WordPress blocks and page design
plugins: core
summary: How to build rich, valid pages with core blocks and theme presets. Read it before building or redesigning any page.
---
# WordPress blocks and page design

## Rules for valid markup
- Content is serialized blocks: `<!-- wp:name {json attributes} -->HTML<!-- /wp:name -->`, or `<!-- wp:name {json} /-->` for blocks without inner HTML.
- For static core blocks, the HTML must match exactly what the editor would save for those attributes, or the editor shows "This block contains unexpected content". Every class and inline style in the HTML must come from an attribute, and the reverse.
- For complex blocks (cover, media-text, quote, gallery…), copy and adapt markup from a theme pattern (get-pattern) or from find-block-examples instead of writing it from memory.
- Use theme presets, never raw values: `"backgroundColor":"accent-1"` → class `has-accent-1-background-color has-background`; `"textColor":"contrast"` → `has-contrast-color has-text-color`; `"fontSize":"large"` → `has-large-font-size`; spacing `var:preset|spacing|50` in attributes becomes `var(--wp--preset--spacing--50)` in styles. Get the slugs with get-design-system.
- Images: only media library images (list-media) with their real ID and URL.

## Page recipe
1. Hero: full-width cover with a media library image and a dark overlay, or a full-width group with a theme color. H1 title, one sentence of promise, one or two buttons.
2. Sections: full-width groups (`"align":"full"`) with vertical padding (spacing preset 50 to 70), alternating the base color and a light accent color. Inside, a constrained layout, an H2, then the content.
3. Section ideas: benefits in 3 columns (icon or image, H3, short text), image + text side by side (columns or media-text), figures, testimonials, FAQ, gallery, steps.
4. Closing call to action: a colored section with a short title and a button to the contact page or the main action.
5. Rhythm: short paragraphs, one idea per section, consistent alignment, `"align":"wide"` for columns.

## Copy-ready markup

Section with background and padding:
```
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"backgroundColor":"accent-5","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-accent-5-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Section title</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","fontSize":"large"} -->
<p class="has-text-align-center has-large-font-size">Intro sentence.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
```

Three columns:
```
<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Benefit</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Short text.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">…</div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
```

Image with rounded corners:
```
<!-- wp:image {"id":728,"sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"16px"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="https://example.com/wp-content/uploads/photo-1024x640.jpg" alt="Describe the image" class="wp-image-728" style="border-radius:16px"/></figure>
<!-- /wp:image -->
```

Buttons (the second one outlined):
```
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/contact/">Contact us</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/services/">Our services</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
```

Spacer:
```
<!-- wp:spacer {"height":"40px"} -->
<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->
```
