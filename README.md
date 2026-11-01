<p align="center"><img src="views/img/logo-512.png" width="128" alt="GEO Lens logo"></p>

# GEO Lens - AI Search Visibility

See which AI crawlers read your PrestaShop 9 store and which AI visits become orders, control crawler access safely, and give AI engines complete, accurate product data. Free and open source (AFL-3.0).

**This module does not promise AI citations, and nothing honestly can.** The research behind it is in [EVIDENCE.md](EVIDENCE.md): what has been tested, what failed, and why this module refuses to sell the rest.

## What it does

**Measure**
- **AI visits and orders**: visits, carts, orders and order value for each assistant (ChatGPT, Perplexity, Gemini, Copilot, Claude and others), with the landing pages
- **Verified crawler log**: every AI crawler by purpose, with real and fake bots told apart using the operators' published address lists
- **What assistants read for people**: the pages opened because someone asked an assistant something
- **Pages they could not find**: the addresses AI systems ask for that give a 404
- **Demand counters**: who asks for `/llms.txt`, `/agents.md`, `/.well-known/ucp`, Markdown and signed requests, so you can see for yourself whether any of it matters yet

**Control**
- **Crawler policy by purpose**: answers, read for a person, training. Three presets or bot by bot, with a preview, a backup and one-click undo
- **Safe `robots.txt`**: allowed bots get no lines, because a bot with its own group stops obeying your rules for the cart, search and filters. Your own lines are never touched, and the block comes back if PrestaShop rewrites the file
- **Live access test**: asks for a product page in each crawler's name and shows what really comes back, including from a firewall or CDN
- **Load guard** (opt-in): refuses declared AI crawlers on pages `robots.txt` already forbids

**Get the facts right**
- **Product markup that extends the theme's**, so there is never a second Product: removes the made-up "valid for 15 days" date, invented identifiers and the shop name posing as a brand, checks GTINs, and adds condition, delivery, returns and variants
- **Delivery and returns** stated once, then shown on product pages and in the markup, so the two agree
- **Catalogue facts**: which products lack a brand, GTIN, specifications, images and more, fact by fact, with a list to fix. It stays fast at 110,000 products
- **Page check**: what a crawler that runs no JavaScript gets, with duplicate or broken markup and directives that hide a page

**Merchant Center**
- **Product feed**: a file in Google's format for the country, language and currency you choose, as a primary or supplemental source, with a version for Microsoft Merchant Center. One item per combination, real prices and tax, checked GTINs, features as `product_detail`. The module writes the file, you submit its address, and the platform decides what to accept and show

**Stay fresh**
- **IndexNow** (opt-in): tells Bing, which feeds Copilot, about changed pages, prices and stock crossing zero
- **Honest dates**: its own record of real changes, including scheduled sales. It never changes a date just to look fresh

**Also**
- **llms.txt and agents.md** (opt-in): short, per language, with no prices and no instructions to AI, and labelled for what it is: no study has found it earns citations
- **Channels**: where AI gets product data, and which programmes are open to your shop

## What it deliberately does not do

AI rewriting of your product text, visibility scores, AI rank tracking, FAQ generators, special "AI tags", date refreshes and citation promises. The evidence for each decision is in [EVIDENCE.md](EVIDENCE.md).

## Good to know

- **The numbers are a floor.** Assistant apps hide where a click came from, Google's AI answers look like ordinary Google clicks, and a page cache can hide crawlers from PHP.
- **It is light.** Measured on a test shop with 110,000 products: no measurable cost on a simple product page and about 8 ms on a product with 8 combinations, out of about 450 ms. Refusing a blocked crawler costs half a normal page.
- **Page builders:** a builder such as Creative Elements may not print the `displayProductAdditionalInfo` hook, so the delivery and returns block will not appear there. The page check tells you when that happens.
- **The feed and Marketing with Google:** if that module already sends your products, do not add a primary feed for the same country and language. Use a supplemental feed. Its IDs read `product-combination`, the form that module uses in its own events, but its cloud service sets the IDs in Merchant Center and is not public, so compare a few first.
- **Feed size:** about 2.7 KB per item, so 500 MB for 190,000 items, and one file per country and language. The build stops if under 256 MB of disk is left. It is sent compressed where the fetcher accepts it.
- **Not in the feed:** gender and age group, Google product category, energy labels, unit prices, the adult flag and returns. PrestaShop holds no such fact, or Google asks you to set it in the account.
- **Nothing leaves your shop by default.** The only thing it ever sends is page addresses to IndexNow, if you turn that on. A feed is a file that waits at a secret address until you give that address to a platform. It downloads the operators' public address lists, and no key or account is needed.
- **No personal data.** No IP address or user agent is stored, only counters. An AI visit is remembered inside PrestaShop's own cookie, for 2 hours by default.
- **Multistore:** `robots.txt` is one file per installation, so the crawler policy applies to every shop. Everything else is per shop.
- **Clean uninstall:** removes the `robots.txt` lines, the tables, the settings, the key file and the feed files.
- No core overrides. 10 languages, with a PDF guide in each, in [`docs/`](docs/).

## Requirements

PrestaShop 9.0+, PHP 8.1+. Tested on 9.0.0, 9.1.4 and 9.2.0, with Hummingbird and Classic.

## Development

```bash
composer install
vendor/bin/phpunit
```

## Author and support

- Email: iamshoaibktk@gmail.com
- WhatsApp: +923459016286
- Fiverr: https://www.fiverr.com/shoaibktk

## License

[Academic Free License 3.0 (AFL-3.0)](https://opensource.org/licenses/AFL-3.0)
