<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Traffic;

/**
 * Says which AI assistant sent a visitor. Hosts follow Matomo's maintained list, read on
 * 2026-11-01. google.com and bing.com are not here on purpose: they mostly carry ordinary search
 * clicks, and AI Overviews and AI Mode cannot be told apart from them. Assistant apps usually
 * send no referrer at all, so every number built on this is a floor, not a total.
 */
final class Classifier
{
    public const ENGINES = [
        'chatgpt' => 'ChatGPT',
        'chatgpt_ads' => 'ChatGPT Ads',
        'perplexity' => 'Perplexity',
        'gemini' => 'Gemini',
        'copilot' => 'Copilot',
        'claude' => 'Claude',
        'grok' => 'Grok',
        'deepseek' => 'DeepSeek',
        'meta' => 'Meta AI',
        'mistral' => 'Le Chat',
        'duck' => 'Duck.ai',
        'you' => 'You.com',
        'notebooklm' => 'NotebookLM',
        'kimi' => 'Kimi',
        'qwen' => 'Qwen',
        'doubao' => 'Doubao',
        'yuanbao' => 'Yuanbao',
        'baidu' => 'Baidu AI',
        'chatglm' => 'ChatGLM',
        'iask' => 'iAsk',
    ];

    private const HOSTS = [
        'chatgpt.com' => 'chatgpt',
        'chat.openai.com' => 'chatgpt',
        'perplexity.ai' => 'perplexity',
        'gemini.google.com' => 'gemini',
        'bard.google.com' => 'gemini',
        'copilot.microsoft.com' => 'copilot',
        'copilot.com' => 'copilot',
        'claude.ai' => 'claude',
        'grok.com' => 'grok',
        'chat.deepseek.com' => 'deepseek',
        'meta.ai' => 'meta',
        'chat.mistral.ai' => 'mistral',
        'duck.ai' => 'duck',
        'you.com' => 'you',
        'notebooklm.google.com' => 'notebooklm',
        'kimi.com' => 'kimi',
        'kimi.moonshot.cn' => 'kimi',
        'chat.qwen.ai' => 'qwen',
        'doubao.com' => 'doubao',
        'yuanbao.tencent.com' => 'yuanbao',
        'yiyan.baidu.com' => 'baidu',
        'ai.baidu.com' => 'baidu',
        'chatglm.cn' => 'chatglm',
        'iask.ai' => 'iask',
    ];

    /** ChatGPT tags its citation links this way, and many clicks arrive with only this tag. */
    private const UTM = [
        'chatgpt.com' => 'chatgpt',
        'chatgpt' => 'chatgpt',
        'openai' => 'chatgpt_ads',
        'perplexity' => 'perplexity',
        'perplexity.ai' => 'perplexity',
        'gemini' => 'gemini',
        'gemini.google.com' => 'gemini',
        'copilot' => 'copilot',
        'copilot.com' => 'copilot',
        'copilot.microsoft.com' => 'copilot',
        'claude' => 'claude',
        'claude.ai' => 'claude',
        'grok' => 'grok',
        'grok.com' => 'grok',
        'deepseek' => 'deepseek',
        'meta.ai' => 'meta',
        'mistral' => 'mistral',
    ];

    /** @return string|null a key of ENGINES, or null when this is no AI landing */
    public static function classify(string $referer, string $utmSource, string $ownHost): ?string
    {
        $host = '';
        $path = '';
        if ($referer !== '') {
            $parts = parse_url($referer);
            $host = strtolower((string) ($parts['host'] ?? ''));
            $path = (string) ($parts['path'] ?? '');
            if (str_starts_with($host, 'www.')) {
                $host = substr($host, 4);
            }
        }
        $own = strtolower((string) preg_replace('/:\d+$/', '', $ownHost));
        if (str_starts_with($own, 'www.')) {
            $own = substr($own, 4);
        }
        // A click inside the shop is not a landing, whatever tag the address still carries.
        if ($host !== '' && $host === $own) {
            return null;
        }
        if (isset(self::HOSTS[$host])) {
            return self::HOSTS[$host];
        }
        if ($host === 'x.com' && str_starts_with($path, '/i/grok')) {
            return 'grok';
        }

        return self::UTM[strtolower(trim($utmSource))] ?? null;
    }

    public static function label(string $engine): string
    {
        return self::ENGINES[$engine] ?? $engine;
    }
}
