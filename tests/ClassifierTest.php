<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Tests;

use Iamshoaibktk\Geolens\Traffic\Classifier;
use PHPUnit\Framework\TestCase;

final class ClassifierTest extends TestCase
{
    /** @dataProvider cases */
    public function testClassify(string $referer, string $utm, ?string $expected): void
    {
        $this->assertSame($expected, Classifier::classify($referer, $utm, 'www.shop.example'));
    }

    public static function cases(): array
    {
        return [
            'chatgpt web' => ['https://chatgpt.com/', '', 'chatgpt'],
            'chatgpt old host' => ['https://chat.openai.com/c/abc', '', 'chatgpt'],
            'perplexity with www' => ['https://www.perplexity.ai/search?q=x', '', 'perplexity'],
            'gemini' => ['https://gemini.google.com/app', '', 'gemini'],
            'copilot' => ['https://copilot.microsoft.com/', '', 'copilot'],
            'claude' => ['https://claude.ai/chat/1', '', 'claude'],
            'grok' => ['https://grok.com/', '', 'grok'],
            'grok on x' => ['https://x.com/i/grok', '', 'grok'],
            'plain x is social' => ['https://x.com/someone', '', null],
            'chatgpt tag with no referrer' => ['', 'chatgpt.com', 'chatgpt'],
            'tag case and spaces' => ['', ' ChatGPT.com ', 'chatgpt'],
            'paid chatgpt' => ['', 'openai', 'chatgpt_ads'],
            'plain Google is not AI' => ['https://www.google.com/search?q=x', '', null],
            'plain Bing is not AI' => ['https://www.bing.com/search?q=x', '', null],
            'google tag is not AI' => ['', 'google', null],
            'direct' => ['', '', null],
            'other site' => ['https://news.example/article', '', null],
            'lookalike host' => ['https://chatgpt.com.evil.example/', '', null],
            'broken referer' => ['http:///', '', null],
            'click inside the shop' => ['https://shop.example/1-tee.html', 'chatgpt.com', null],
            'click inside with www' => ['https://www.shop.example/', 'chatgpt.com', null],
            'click inside on a port' => ['http://shop.example:8888/a', 'chatgpt.com', null],
            'referrer beats a stale tag' => ['https://claude.ai/', 'chatgpt.com', 'claude'],
        ];
    }

    public function testEveryEngineHasALabel(): void
    {
        $this->assertSame('ChatGPT', Classifier::label('chatgpt'));
        $this->assertSame('unknown', Classifier::label('unknown'));
    }
}
