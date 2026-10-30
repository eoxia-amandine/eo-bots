---
title: Gravity Forms
plugins: gravityforms/gravityforms.php
summary: Insert an existing Gravity Forms form in a page (contact, quote, registration).
---
# Gravity Forms

Insert a form with its block (self-closing):
```
<!-- wp:gravityforms/form {"formId":"1"} /-->
```
- `formId` is the form ID as a string. Forms are created in the Forms menu, not in pages: you cannot create or edit a form.
- Find the IDs already used on the site with find-block-examples (block "gravityforms/form"). If you don't know which form fits, ask the user, or tell them to create one in Forms › New Form and give you its ID.
- Optional: `"title":false` and `"description":false` hide the form title and description (use them when your page already has a heading above the form).
- Place the form inside a section with some padding, under a short heading and one sentence explaining what happens after sending.
