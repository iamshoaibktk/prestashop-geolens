<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Tests;

use Iamshoaibktk\Geolens\Bots\IpRanges;
use Iamshoaibktk\Geolens\Bots\Matcher;
use Iamshoaibktk\Geolens\Bots\Registry;
use PHPUnit\Framework\TestCase;

final class BotsTest extends TestCase
{
    public function testEveryDocumentedUserAgentIsRecognised(): void
    {
        foreach (Registry::BOTS as $token => $bot) {
            if ($bot['ua'] !== null) {
                $this->assertSame($token, Matcher::match($bot['ua']), $bot['ua']);
            }
        }
    }

    public function testEveryOfficialListIsAKnownUrl(): void
    {
        foreach (Registry::BOTS as $token => $bot) {
            if ($bot['ip'] !== null) {
                $this->assertArrayHasKey($bot['ip'], Registry::IP_LISTS, $token);
            }
        }
    }

    /** @dataProvider users */
    public function testPeopleAreNotBots(string $userAgent): void
    {
        $this->assertNull(Matcher::match($userAgent));
    }

    public static function users(): array
    {
        return [
            'chrome' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'],
            'safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1'],
            'empty' => [''],
            'other Google bot' => ['Googlebot-Image/1.0'],
            'other Apple token' => ['Mozilla/5.0 (Applebot-Foo/1.0)'],
            'token inside a word' => ['MyGPTBotChecker/1.0'],
            'Google-Extended never visits' => ['Google-Extended/1.0'],
        ];
    }

    public function testChromeLookingSearchBotIsStillFound(): void
    {
        $this->assertSame('OAI-SearchBot', Matcher::match('Mozilla/5.0 (Macintosh) Chrome/131.0.0.0 Safari/537.36; compatible; OAI-SearchBot/1.4; robots.txt; +https://openai.com/searchbot'));
    }

    public function testMatchingIgnoresCase(): void
    {
        $this->assertSame('Meta-ExternalAgent', Matcher::match('meta-externalagent/1.1'));
    }

    public function testSearchEnginesAreNeverBlockable(): void
    {
        foreach (['Googlebot', 'Storebot-Google', 'bingbot', 'Applebot'] as $token) {
            $this->assertArrayNotHasKey($token, Registry::blockable());
            $this->assertNotContains($token, Registry::presetBlocks('lockdown'));
        }
    }

    public function testVisibilityPresetBlocksNothing(): void
    {
        $this->assertSame([], Registry::presetBlocks('visibility'));
    }

    public function testNoTrainingPresetKeepsSearchAndUserAgentsOpen(): void
    {
        $blocked = Registry::presetBlocks('notrain');
        $this->assertContains('GPTBot', $blocked);
        $this->assertContains('ClaudeBot', $blocked);
        $this->assertNotContains('OAI-SearchBot', $blocked);
        $this->assertNotContains('ChatGPT-User', $blocked);
        // Blocking it also ends grounding in Gemini Apps, so it stays the merchant's own choice.
        $this->assertNotContains('Google-Extended', $blocked);
    }

    /** @dataProvider ranges */
    public function testCidr(string $ip, array $list, bool $expected): void
    {
        $this->assertSame($expected, IpRanges::contains($ip, $list));
    }

    public static function ranges(): array
    {
        return [
            'v4 inside' => ['20.171.207.5', ['20.171.207.0/24'], true],
            'v4 outside' => ['20.171.208.5', ['20.171.207.0/24'], false],
            'v4 partial byte in' => ['10.0.16.1', ['10.0.16.0/20'], true],
            'v4 partial byte out' => ['10.0.32.1', ['10.0.16.0/20'], false],
            'v4 single' => ['1.2.3.4', ['1.2.3.4/32'], true],
            'v4 no prefix' => ['1.2.3.4', ['1.2.3.4'], true],
            'v6 inside' => ['2001:4860:4801:10::1', ['2001:4860:4801:10::/64'], true],
            'v6 outside' => ['2001:4860:4802:10::1', ['2001:4860:4801:10::/64'], false],
            'families do not mix' => ['1.2.3.4', ['2001:4860:4801:10::/64'], false],
            'nonsense' => ['not-an-ip', ['1.2.3.0/24'], false],
            'bad entry skipped' => ['1.2.3.4', ['bad/24', '1.2.3.0/24'], true],
            'zero prefix' => ['8.8.8.8', ['0.0.0.0/0'], true],
        ];
    }

    public function testVerdicts(): void
    {
        $this->assertSame(IpRanges::VERIFIED, IpRanges::verdict('1.2.3.4', ['1.2.3.0/24']));
        $this->assertSame(IpRanges::SPOOFED, IpRanges::verdict('9.9.9.9', ['1.2.3.0/24']));
        $this->assertSame(IpRanges::UNVERIFIABLE, IpRanges::verdict('9.9.9.9', null));
        $this->assertSame(IpRanges::UNVERIFIABLE, IpRanges::verdict('9.9.9.9', []));
        $this->assertSame(IpRanges::UNVERIFIABLE, IpRanges::verdict('junk', ['1.2.3.0/24']));
    }

    public function testPublishedListParsing(): void
    {
        $json = '{"creationTime":"2026-10-27","prefixes":[{"ipv4Prefix":"20.171.207.0/24"},{"ipv6Prefix":"2001:db8::/32"},{"other":"x"},{"ipv4Prefix":"bad/24"}]}';
        $this->assertSame(['20.171.207.0/24', '2001:db8::/32'], IpRanges::parseList($json));
        $this->assertSame([], IpRanges::parseList('<html>down</html>'));
    }

    public function testClientIpPrefersTheCdnHeader(): void
    {
        $this->assertSame('5.5.5.5', IpRanges::clientIp(['HTTP_CF_CONNECTING_IP' => '5.5.5.5', 'REMOTE_ADDR' => '1.1.1.1']));
        $this->assertSame('6.6.6.6', IpRanges::clientIp(['HTTP_X_FORWARDED_FOR' => '6.6.6.6, 7.7.7.7', 'REMOTE_ADDR' => '1.1.1.1']));
        $this->assertSame('1.1.1.1', IpRanges::clientIp(['REMOTE_ADDR' => '1.1.1.1']));
        $this->assertSame('', IpRanges::clientIp([]));
    }
}
