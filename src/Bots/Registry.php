<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Bots;

/**
 * Every crawler the module knows, grouped by what it does for the shop. Tokens, strings and IP
 * lists come from each operator's own documentation, read on 2026-11-01. "official" is false
 * when no operator page could be found, so a request with that name can never be verified.
 */
final class Registry
{
    /** Builds an index behind AI answers. Blocking it can remove the shop from those answers. */
    public const SEARCH = 'search';
    /** Opens a page because a person asked an assistant to. */
    public const USER = 'user';
    /** Collects content to train models. Blocking it has no stated cost in search. */
    public const TRAIN = 'train';
    /** The search engines behind AI Overviews, AI Mode and Copilot. The module never blocks them. */
    public const CORE = 'core';

    public const PURPOSES = [self::SEARCH, self::USER, self::TRAIN, self::CORE];

    /**
     * op: operator; purpose: see above; ua: the documented user agent, used to test access (null
     * means a robots.txt token that never visits); ip: key of IP_LISTS; obeys: false when the
     * operator says the fetcher may ignore robots.txt; official: see the class comment.
     */
    public const BOTS = [
        // Answers and search
        'OAI-SearchBot' => ['op' => 'OpenAI', 'purpose' => self::SEARCH, 'ip' => 'oai-searchbot', 'obeys' => true, 'official' => true,
            'ua' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36; compatible; OAI-SearchBot/1.4; +https://openai.com/searchbot'],
        'PerplexityBot' => ['op' => 'Perplexity', 'purpose' => self::SEARCH, 'ip' => 'perplexitybot', 'obeys' => true, 'official' => true,
            'ua' => 'Mozilla/5.0 (compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)'],
        'Claude-SearchBot' => ['op' => 'Anthropic', 'purpose' => self::SEARCH, 'ip' => 'anthropic', 'obeys' => true, 'official' => true,
            'ua' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Claude-SearchBot/1.0; +searchbot@anthropic.com)'],
        'DuckAssistBot' => ['op' => 'DuckDuckGo', 'purpose' => self::SEARCH, 'ip' => 'duckassistbot', 'obeys' => true, 'official' => true,
            'ua' => 'DuckAssistBot/1.2; (+http://duckduckgo.com/duckassistbot.html)'],
        'Meta-WebIndexer' => ['op' => 'Meta', 'purpose' => self::SEARCH, 'ip' => null, 'obeys' => true, 'official' => true,
            'ua' => 'meta-webindexer/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/web-crawlers)'],
        'Amzn-SearchBot' => ['op' => 'Amazon', 'purpose' => self::SEARCH, 'ip' => null, 'obeys' => true, 'official' => true,
            'ua' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Amzn-SearchBot/0.1) Chrome/119.0.6045.214 Safari/537.36'],
        'MistralAI-Index' => ['op' => 'Mistral', 'purpose' => self::SEARCH, 'ip' => 'mistral-index', 'obeys' => true, 'official' => true,
            'ua' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; MistralAI-Index/1.0; +https://docs.mistral.ai/robots)'],
        'YouBot' => ['op' => 'You.com', 'purpose' => self::SEARCH, 'ip' => null, 'obeys' => true, 'official' => false,
            'ua' => 'Mozilla/5.0 (compatible; YouBot/1.0; +https://you.com/bot)'],

        // Fetches for a person
        'ChatGPT-User' => ['op' => 'OpenAI', 'purpose' => self::USER, 'ip' => 'chatgpt-user', 'obeys' => false, 'official' => true,
            'ua' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot'],
        'Perplexity-User' => ['op' => 'Perplexity', 'purpose' => self::USER, 'ip' => 'perplexity-user', 'obeys' => false, 'official' => true,
            'ua' => 'Mozilla/5.0 (compatible; Perplexity-User/1.0; +https://perplexity.ai/perplexity-user)'],
        'Claude-User' => ['op' => 'Anthropic', 'purpose' => self::USER, 'ip' => 'anthropic', 'obeys' => true, 'official' => true,
            'ua' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Claude-User/1.0; +claude-user@anthropic.com)'],
        'Google-Agent' => ['op' => 'Google', 'purpose' => self::USER, 'ip' => 'google-agent', 'obeys' => false, 'official' => true,
            'ua' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko; compatible; Google-Agent; +https://developers.google.com/crawling/docs/crawlers-fetchers/google-agent) Chrome/131.0.0.0 Safari/537.36'],
        'Google-GeminiNotebook' => ['op' => 'Google', 'purpose' => self::USER, 'ip' => 'google-user', 'obeys' => false, 'official' => true,
            'ua' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 (compatible; Google-GeminiNotebook; +https://developers.google.com/crawling/docs/crawlers-fetchers/google-gemininotebook)'],
        'MistralAI-User' => ['op' => 'Mistral', 'purpose' => self::USER, 'ip' => 'mistral-user', 'obeys' => true, 'official' => true,
            'ua' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; MistralAI-User/1.0; +https://docs.mistral.ai/robots)'],
        'Meta-ExternalFetcher' => ['op' => 'Meta', 'purpose' => self::USER, 'ip' => null, 'obeys' => false, 'official' => true,
            'ua' => 'meta-externalfetcher/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/web-crawlers)'],
        'Amzn-User' => ['op' => 'Amazon', 'purpose' => self::USER, 'ip' => null, 'obeys' => false, 'official' => true,
            'ua' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Amzn-User/0.1) Chrome/119.0.6045.214 Safari/537.36'],
        'GrokBot' => ['op' => 'xAI', 'purpose' => self::USER, 'ip' => null, 'obeys' => false, 'official' => false,
            'ua' => 'Mozilla/5.0 (compatible; GrokBot/1.0; +https://x.ai/)'],
        'xAI-Grok' => ['op' => 'xAI', 'purpose' => self::USER, 'ip' => null, 'obeys' => false, 'official' => false,
            'ua' => 'Mozilla/5.0 (compatible; xAI-Grok/1.0; +https://x.ai/)'],

        // Training
        'GPTBot' => ['op' => 'OpenAI', 'purpose' => self::TRAIN, 'ip' => 'gptbot', 'obeys' => true, 'official' => true,
            'ua' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.4; +https://openai.com/gptbot'],
        'ClaudeBot' => ['op' => 'Anthropic', 'purpose' => self::TRAIN, 'ip' => 'anthropic', 'obeys' => true, 'official' => true,
            'ua' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)'],
        'Google-Extended' => ['op' => 'Google', 'purpose' => self::TRAIN, 'ip' => null, 'obeys' => true, 'official' => true, 'ua' => null],
        'Applebot-Extended' => ['op' => 'Apple', 'purpose' => self::TRAIN, 'ip' => null, 'obeys' => true, 'official' => true, 'ua' => null],
        'Meta-ExternalAgent' => ['op' => 'Meta', 'purpose' => self::TRAIN, 'ip' => null, 'obeys' => true, 'official' => true,
            'ua' => 'meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/web-crawlers)'],
        'Amazonbot' => ['op' => 'Amazon', 'purpose' => self::TRAIN, 'ip' => null, 'obeys' => true, 'official' => true,
            'ua' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Amazonbot/0.1) Chrome/119.0.6045.214 Safari/537.36'],
        'CCBot' => ['op' => 'Common Crawl', 'purpose' => self::TRAIN, 'ip' => 'ccbot', 'obeys' => true, 'official' => true,
            'ua' => 'CCBot/2.0 (https://commoncrawl.org/faq/)'],
        'MistralAI-Training' => ['op' => 'Mistral', 'purpose' => self::TRAIN, 'ip' => null, 'obeys' => true, 'official' => true,
            'ua' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; MistralAI-Training/1.0; +https://docs.mistral.ai/robots)'],
        'Bytespider' => ['op' => 'ByteDance', 'purpose' => self::TRAIN, 'ip' => null, 'obeys' => false, 'official' => false,
            'ua' => 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)'],
        'DeepSeekBot' => ['op' => 'DeepSeek', 'purpose' => self::TRAIN, 'ip' => null, 'obeys' => false, 'official' => false,
            'ua' => 'Mozilla/5.0 (compatible; DeepSeekBot/1.0; +https://www.deepseek.com/)'],
        'Timpibot' => ['op' => 'Timpi', 'purpose' => self::TRAIN, 'ip' => null, 'obeys' => false, 'official' => false,
            'ua' => 'Mozilla/5.0 (compatible; Timpibot/1.0; +http://www.timpi.io)'],
        'cohere-ai' => ['op' => 'Cohere', 'purpose' => self::TRAIN, 'ip' => null, 'obeys' => false, 'official' => false,
            'ua' => 'Mozilla/5.0 (compatible; cohere-ai; +https://cohere.com/crawler)'],

        // Search engines: logged, never blocked
        'Googlebot' => ['op' => 'Google', 'purpose' => self::CORE, 'ip' => 'google-common', 'obeys' => true, 'official' => true,
            'ua' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Googlebot/2.1; +http://www.google.com/bot.html) Chrome/131.0.0.0 Safari/537.36'],
        'Storebot-Google' => ['op' => 'Google', 'purpose' => self::CORE, 'ip' => 'google-common', 'obeys' => true, 'official' => true,
            'ua' => 'Mozilla/5.0 (X11; Linux x86_64; Storebot-Google/1.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'],
        'bingbot' => ['op' => 'Microsoft', 'purpose' => self::CORE, 'ip' => 'bingbot', 'obeys' => true, 'official' => true,
            'ua' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/131.0.0.0 Safari/537.36'],
        'Applebot' => ['op' => 'Apple', 'purpose' => self::CORE, 'ip' => 'applebot', 'obeys' => true, 'official' => true,
            'ua' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)'],
    ];

    /** Operators' published address lists. All use {"prefixes": [{"ipv4Prefix"|"ipv6Prefix": "..."}]}. */
    public const IP_LISTS = [
        'oai-searchbot' => 'https://openai.com/searchbot.json',
        'chatgpt-user' => 'https://openai.com/chatgpt-user.json',
        'gptbot' => 'https://openai.com/gptbot.json',
        'perplexitybot' => 'https://www.perplexity.ai/perplexitybot.json',
        'perplexity-user' => 'https://www.perplexity.ai/perplexity-user.json',
        'anthropic' => 'https://claude.com/crawling/bots.json',
        'duckassistbot' => 'https://duckduckgo.com/duckassistbot.json',
        'mistral-index' => 'https://mistral.ai/mistralai-index-ips.json',
        'mistral-user' => 'https://mistral.ai/mistralai-user-ips.json',
        'ccbot' => 'https://index.commoncrawl.org/ccbot.json',
        'google-common' => 'https://developers.google.com/static/crawling/ipranges/common-crawlers.json',
        'google-agent' => 'https://developers.google.com/static/crawling/ipranges/user-triggered-agents.json',
        'google-user' => 'https://developers.google.com/static/crawling/ipranges/user-triggered-fetchers-google.json',
        'bingbot' => 'https://www.bing.com/toolbox/bingbot.json',
        'applebot' => 'https://search.developer.apple.com/applebot.json',
    ];

    /** What the "No training" and "Block all AI" presets leave open, and what they close. */
    public const PRESETS = ['visibility', 'notrain', 'lockdown'];

    /** @return array<string, array> the bots a merchant can decide about (search engines excluded) */
    public static function blockable(): array
    {
        return array_filter(self::BOTS, static fn ($b) => $b['purpose'] !== self::CORE);
    }

    /** @return string[] tokens the preset blocks */
    public static function presetBlocks(string $preset): array
    {
        $blocked = [];
        foreach (self::blockable() as $token => $bot) {
            if ($preset === 'lockdown'
                || ($preset === 'notrain' && $bot['purpose'] === self::TRAIN && $token !== 'Google-Extended')) {
                $blocked[] = $token;
            }
        }

        return $blocked;
    }
}
