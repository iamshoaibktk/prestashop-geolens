# Changelog

## 1.1.0

- Product feed in Google's RSS format, as a primary or supplemental source for Google Merchant Center, with a version for Microsoft Merchant Center
- One item per combination with `item_group_id`, `item_group_title` and `variant_option`; `product_detail` from features
- Prices from PrestaShop's own calculation, with the tax and currency of the chosen country, and tax left out for the US and Canada as Google requires
- GTINs only if the check digit passes and the code is outside the ranges Google refuses
- Reference prices follow the crossed-out price setting, and a shop outside the EU states none in a feed for an EU country
- Built in resumable batches by the background jobs, never on a storefront request; served from a finished file behind a secret address, with gzip
- Every item checked against Google's specification before it is written; `tests/check-feed.php` checks a whole file
- Warns about a primary feed competing with Marketing with Google, and about prices that cannot match the page
- The Channels tab and the overview point to the feed
- Simpler back office: six tabs instead of eight, AI visits on the overview, options under Settings, and details in collapsible sections

## 1.0.0

First release.

- AI visits, carts, orders and order value by assistant
- Crawler log by purpose, with real and fake bots told apart using the operators' published address lists
- Pages assistants read for people, pages they could not find, and demand counters
- Crawler policy with presets, preview, backup and undo; a safe robots.txt block that survives regeneration
- Live access test, load guard, shared training opt-out and Content Signals
- Product markup that extends the theme's: variants, delivery, returns, checked GTINs, no made-up dates or identifiers
- Delivery and returns shown on the product page
- Catalogue facts audit and page check
- IndexNow, honest change dates, sitemap check
- Opt-in llms.txt and agents.md per language
- Channel status and evidence notes
- 10 languages, with a PDF guide in each
