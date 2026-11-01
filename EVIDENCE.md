# The evidence behind GEO Lens

Generative Engine Optimization (GEO) is sold as a way to get cited by AI. Before building this module I read the research, the operators' own documentation and the competing products, as of November 2026. This page is the short version of what that found, and of why the module does what it does. There are no invented numbers here, and every claim links to its source.

**The short answer:** for a shop, GEO is mostly about access and accurate facts, not copywriting. I found no randomized test on a live independent store that proves any tactic beyond that. Be careful with anyone who promises otherwise, including me.

## How big is this?

AI assistants bring about 0.2 to 0.4% of e-commerce sessions in the best large samples ([Kaiser and Schulze](https://organicllm.org/), [Similarweb](https://www.similarweb.com/corp/reports/state-of-ecommerce-2026/)). Native apps hide their referrer, so the true figure is higher, but nobody can say by how much ([Cloudflare](https://blog.cloudflare.com/ai-search-crawl-refer-ratio-on-radar/)). Whether these visitors convert better is disputed: the peer-reviewed multi-channel study found organic search converts better ([Kaiser and Schulze](https://organicllm.org/)), and Adobe's more recent figures point the other way, on different data. That is why this module measures your own orders instead of quoting anyone's average.

## What holds up

| Claim | Why I believe it | What the module does |
|---|---|---|
| Access is a hard gate | OpenAI drops opted-out sites from search answers ([OpenAI](https://developers.openai.com/api/docs/bots)), and Google needs pages indexed and snippet-eligible ([Google](https://developers.google.com/search/docs/appearance/ai-features)) | Crawler policy and a live access test |
| robots.txt is not the last gate | A firewall or CDN can refuse a bot the file welcomes ([OpenAI](https://help.openai.com/en/articles/20001243-advertiser-guidance-for-allowing-openai-web-crawlers)) | The access test asks for real pages |
| A bare `User-agent: GPTBot / Allow: /` is a trap | A crawler obeys only the group that names it, so it stops reading your rules ([RFC 9309](https://www.rfc-editor.org/rfc/rfc9309.html)) | Writes no lines for allowed bots, and warns if the file has them |
| Bot names can be faked | Operators warn about it ([Google](https://developers.google.com/crawling/docs/crawlers-fetchers/google-common-crawlers)), and fakes are common in small studies ([Forrester](https://duaneforresterdecodes.substack.com/p/818-of-my-ai-assistant-traffic-was)) | Checks each request against the operator's published addresses |
| Stated price and specifications beat omission | The best on-page result, though in a small test setting ([What Gets Cited](https://arxiv.org/pdf/2605.25517)) | Audits your catalogue fact by fact |
| Engines rarely repeat themselves | Under 1 in 100 chance of the same brand list twice ([SparkToro](https://sparktoro.com/blog/new-research-ais-are-highly-inconsistent-when-recommending-brands-or-products-marketers-should-take-care-when-tracking-ai-visibility/)) | Offers no rank, because none exists |

## What does not hold up

| Popular claim | What was found |
|---|---|
| GEO boosts visibility by 40% | A word-share gain in a simulator using gpt-3.5-turbo, not more clicks or citations ([Aggarwal et al.](https://arxiv.org/abs/2311.09735), [Martinez](https://arxiv.org/html/2607.14035v1)) |
| Add statistics, quotes and citations to product copy | Statistics lowered rank in 19 of 24 settings ([C-SEO Bench](https://papers.neurips.cc/paper_files/paper/2025/file/27aa3aeff0f8460a7b43d30fa6c5c032-Paper-Datasets_and_Benchmarks_Track.pdf)), and in one simulation every method reduced citations for shopping ([SAGEO](https://arxiv.org/pdf/2602.12187)) |
| Let AI rewrite your product descriptions | Storytelling and advertising styles lost rank, and any gains depended on the model ([E-GEO](https://arxiv.org/pdf/2511.20867)) |
| llms.txt gets you cited | Google says its Search ignores it ([Google](https://developers.google.com/search/docs/fundamentals/ai-optimization-guide)), 97% of files saw no requests in a month ([Ahrefs](https://ahrefs.com/blog/llmstxt-study/)), and 300,000 domains showed no link to citations ([SE Ranking](https://seranking.com/blog/llms-txt/)) |
| Schema markup earns AI citations | 1,885 pages adding JSON-LD against 4,000 controls showed no gain ([Ahrefs](https://ahrefs.com/blog/schema-ai-citations/)). It does still drive rich results and Merchant Center, which is why the module fixes it |
| FAQ blocks are a quick win | Near zero effect in tests ([E-GEO](https://arxiv.org/html/2511.20867v2)), and Google stopped showing FAQ results in May 2026 ([Google](https://developers.google.com/search/updates)) |
| Track your AI rank | No stable position exists ([SparkToro](https://sparktoro.com/blog/new-research-ais-are-highly-inconsistent-when-recommending-brands-or-products-marketers-should-take-care-when-tracking-ai-visibility/)) |
| A readiness score predicts citations | A score that never sees the question barely predicts them, in a preprint by vendors ([Bajemon and Rochet](https://arxiv.org/html/2609.07559)) |
| Refresh your dates to look new | Google warns against date changes with no real change ([Ahrefs](https://ahrefs.com/blog/do-ai-assistants-prefer-to-cite-fresh-content/)) |
| "Summarize with AI" buttons | Microsoft classes prefilled prompts as recommendation poisoning ([Microsoft](https://www.microsoft.com/en-us/security/blog/2026/02/10/ai-recommendation-poisoning/)) |

Several of the debunkers sell competing tools, so treat their numbers with the same care. Independent replication is still rare.

## Why some things are included anyway

- **llms.txt** costs nothing, Google says it neither helps nor harms, and some tools check for it. It is opt-in and labelled for what it is.
- **IndexNow** has no measured effect on AI answers. It is here because Bing feeds Copilot and the protocol names price and availability changes explicitly ([IndexNow](https://www.indexnow.org/faq)), and it costs almost nothing. It is opt-in, and Google does not take part.
- **Improved markup** is not a citation lever, but Merchant Center corrects prices and availability from it ([Google](https://support.google.com/merchants/answer/12157888?hl=en)), and it should tell the truth regardless.

## Where to spend your effort instead

1. **Google Merchant Center.** Google says feeds can help products appear in AI responses ([Google](https://developers.google.com/search/docs/fundamentals/ai-optimization-guide)).
2. **Your product facts:** real prices, stock, specifications, identifiers.
3. **Microsoft Merchant Center and Bing Webmaster Tools.** Microsoft says its feeds inform organic Copilot results ([Microsoft](https://about.ads.microsoft.com/en/blog/post/january-2026/conversations-that-convert-copilot-checkout-and-brand-agents)).
4. **Being talked about elsewhere.** Brand mentions correlate with AI visibility more strongly than backlinks, though nobody has shown they cause it ([Ahrefs](https://ahrefs.com/blog/ai-brand-visibility-correlations/)).

AI checkout programmes are mostly limited to US buyers or selected merchants for now, so they are not worth planning around unless you sell there.

## The product feed

Field rules come from [Google's product data specification](https://support.google.com/merchants/answer/7052112) and [Microsoft's product attributes](https://learn.microsoft.com/en-us/advertising/msa-help/hlp_ba_conc_aboutbingmerchantcentercatalogfile), read on 1 November 2026. A few decisions rest on those pages disagreeing or saying little:

- **Two primary sources.** Google recommends one, and says a product uploaded through several [belongs to whichever updated last](https://support.google.com/merchants/answer/13982673). That is why the module warns when Marketing with Google is active.
- **Item IDs.** Marketing with Google writes `product-combination` in its own events, so the feed does too. PrestaShop's cloud service sets the IDs in Merchant Center and is not public, so that they match is my inference.
- **Product detail length.** Google's [dedicated page](https://support.google.com/merchants/answer/9218260) says 150 characters per detail and the main specification says 140 and 1,000 per field, so a detail must meet both.
- **Availability.** Google's specification says `in_stock`, its [RSS example](https://support.google.com/merchants/answer/14987622) says `in stock`, and Microsoft documents only the spaced form and has no backorder. A Microsoft feed uses Microsoft's spelling and says out of stock for backorders.
- **Supplemental sources.** [Google's page](https://support.google.com/merchants/answer/14990942) does not say an ID alone is enough, so this rests on its description of them as adding details to existing products.
- **Reference prices in the EU.** The [CJEU](https://curia.europa.eu/jcms/upload/docs/application/pdf/2024-09/cp240152en.pdf) ties a reduction to the lowest price of the previous 30 days, and the module keeps no price history. Whether an AI product card counts as the trader's offer is unsettled.

What was tested, on 1 November 2026, on one laptop:

| Test | Catalogue | Result |
|---|---|---|
| Full build, one feed | Copy of the 110,020-product test shop, given text in 10 languages, 2 images per product, 4 features and 94,290 combinations: 188,623 items | About 135 to 340 s depending on load, in resumable steps of 25 s, 43 to 132 MB of memory, about 500 MB file, 11 MB sent with gzip |
| Same, supplemental | The same | 32 s, 135 MB file |
| Test shop as it is | 110,020 products, of which only 23 have images and only 1 has text in the other 9 languages, so nearly all are correctly left out | 4 to 6 s per feed, 47 items in English |
| Every item against these rules | All builds above | 0 problems. This check also found a real fault, since fixed: variants with no attribute names in a language looked identical |
| Killed twice with `kill -9`, then resumed | 188,623 items | Byte-identical to an uninterrupted build |
| Price, currency and stock against the real page | 25 pages, French feed in EUR with 20% VAT | All 25 match |
| A storefront request that triggers a build | Test shop | 0.44 s, the same as any other; the build runs in its own request |

**The feed has not been submitted to a real Merchant Center account**, Google's or Microsoft's, because a local shop's domain cannot be verified. The checks above use the module's own reading of the rules. Only the platforms can say what they accept. Multistore was not tested.

## What I could not check

Reddit could not be read. Competitors' features are their own claims, since I installed none of them. Nothing here was tested against live AI crawlers, because a local test shop cannot be crawled. If you find something wrong or newer, please open an issue.

*Last reviewed: 1 November 2026.*
