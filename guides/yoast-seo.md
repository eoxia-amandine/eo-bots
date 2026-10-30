---
title: Yoast SEO
plugins: wordpress-seo/wp-seo.php
abilities: yoast-seo/get-post-seo-data, yoast-seo/update-post-seo-data, yoast-seo/get-seo-scores, yoast-seo/get-readability-scores, yoast-seo/set-xml-sitemap-status
confirm: yoast-seo/update-post-seo-data=live, yoast-seo/set-*=always
summary: SEO audit of the site and of a page, SEO stats, focus keyphrase, SEO title and meta description, indexing settings.
---
# Yoast SEO

## Tools
- `eobots/seo-report`: site-wide health (score distribution, missing keyphrases and meta descriptions, duplicates, pages nobody links to, noindex, blocking settings), with page IDs.
- `eobots/seo-review`: live analysis of one page for its keyphrase. Use it before and after improving a page: Yoast's own scores only refresh when someone saves the page in the editor.
- `yoast-seo/get-post-seo-data`: current SEO title, meta description, keyphrase, robots, schema, Yoast scores of a page (by post_id, permalink or title words).
- `yoast-seo/update-post-seo-data`: change SEO title, meta description, cornerstone, schema page type, social titles, robots. Only the fields you send change. Asks the user when the page is published.
- `eobots/set-focus-keyphrase`: the keyphrase is not part of the Yoast update tool, use this one.
- `yoast-seo/get-seo-scores`, `yoast-seo/get-readability-scores`: Yoast scores of the latest edited pages.

## Answering "how is my SEO?"
1. Run seo-report.
2. Answer in a few lines: number of pages, how many good / to improve / not optimized, then the 3 priorities that will help most, in this order: blocking site issues (search engines discouraged first), pages without keyphrase or meta description among the important pages (home, services, contact, cornerstone), duplicate keyphrases, pages with no internal link to them.
3. Offer to fix the first priority now. Never dump the raw lists.

## Optimizing a page
1. get-post-seo-data and seo-review to see the current state.
2. If there is no keyphrase, choose 2 to 4 words a customer would type in Google (the service + often the city for a local business), check it is not used by another page, set it.
3. SEO title: 30 to 60 characters, keyphrase near the start, then the brand: "Plumber in Lyon – Dupont & Fils". You may use Yoast variables such as %%sitename%%.
4. Meta description: 120 to 156 characters, keyphrase, a concrete benefit and a call to action.
5. Content, with update-content: keyphrase in the first paragraph and in one or two H2, at least 300 useful words, 2 or 3 internal links to related pages, a descriptive alt text on images. Natural writing first: never stuff keywords.
6. Run seo-review again and tell the user what changed. Remind them that the Yoast traffic lights refresh when they open the page in the editor.

## Rules
- One keyphrase per page, never the same on two pages.
- Never set noindex, nofollow or a canonical URL unless the user asks for it explicitly: these can remove pages from Google.
- Main pages (home, services, key articles) can be marked cornerstone.
- Schema page type: `FAQPage` for a FAQ page, `ContactPage` for contact, `AboutPage` for about.
- The SEO title and meta description are what people see in Google: write them for humans, in the site language.
