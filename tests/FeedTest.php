<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Tests;

use Iamshoaibktk\Geolens\Feed\Links;
use Iamshoaibktk\Geolens\Feed\Pricing;
use Iamshoaibktk\Geolens\Feed\Profiles;
use Iamshoaibktk\Geolens\Feed\Rss;
use Iamshoaibktk\Geolens\Feed\Server;
use Iamshoaibktk\Geolens\Feed\Text;
use Iamshoaibktk\Geolens\Schema\Gtin;
use PHPUnit\Framework\TestCase;

final class FeedTest extends TestCase
{
    public function testTagsAreRemovedAndWordsStayApart(): void
    {
        $this->assertSame('First line Second line one two', Text::plain('<p>First line</p><p>Second<br/>line</p><ul><li>one</li><li>two</li></ul>'));
    }

    public function testScriptsAndStylesLoseTheirContent(): void
    {
        $this->assertSame('Keep this', Text::plain('<style>.a{color:red}</style><script>alert(1)</script><div>Keep this</div>'));
    }

    public function testEntitiesAreDecodedAndSpacesReduced(): void
    {
        $this->assertSame('Tom & Jerry 100 % cotton', Text::plain("Tom &amp; Jerry&nbsp;100&nbsp;%\n\t cotton"));
    }

    public function testTagsStoredEncodedAreRemovedToo(): void
    {
        $this->assertSame('Bold text', Text::plain('&lt;b&gt;Bold&lt;/b&gt; text'));
    }

    public function testAComparisonInTextIsNotMistakenForATag(): void
    {
        $this->assertSame('a < b and c > d', Text::plain('a &lt; b and c &gt; d'));
    }

    public function testCharactersXmlCannotCarryAreRemoved(): void
    {
        $this->assertSame('ab', Text::line("a\x00\x08\x1Fb"));
        $this->assertSame('ab', Text::xmlSafe("a\x0Bb"));
        $this->assertSame('ab', Text::line("a\u{FFFE}b"));
    }

    public function testBrokenUtf8DoesNotStopTheFile(): void
    {
        $clean = Text::line("caf\xE9 au lait");
        $this->assertTrue(mb_check_encoding($clean, 'UTF-8'));
        $this->assertStringContainsString('au lait', $clean);
    }

    public function testLongHtmlIsTheSameWhenAskedTwice(): void
    {
        $html = '<p>' . str_repeat('word ', 100) . '</p>';
        $this->assertSame(Text::plain($html), Text::plain($html));
    }

    public function testLimitCutsAtAWordAndCountsCharactersNotBytes(): void
    {
        $this->assertSame('short', Text::limit('short', 10));
        $this->assertSame('one two three', Text::limit('one two three four', 15));
        $this->assertSame(10, mb_strlen(Text::limit(str_repeat('é', 30), 10), 'UTF-8'));
        $this->assertSame('abcdefghij', Text::limit('abcdefghijklmnop', 10));
    }

    public function testItemIsValidXmlInGoogleNamespace(): void
    {
        $xml = Rss::open('A & B <shop>', 'https://shop.example/', 'desc')
            . Rss::item([
                ['id', '1-0'],
                ['title', 'Salt & "pepper" <mill>'],
                ['shipping', ['country' => 'FR', 'price' => '4.90 EUR']],
                ['product_detail', ['attribute_name' => 'R&D', 'attribute_value' => '<1 kg']],
            ])
            . Rss::close();
        $root = new \SimpleXMLElement($xml);
        $this->assertSame('A & B <shop>', (string) $root->channel->title);
        $g = $root->channel->item->children(Rss::NAMESPACE_URI);
        $this->assertSame('1-0', (string) $g->id);
        $this->assertSame('Salt & "pepper" <mill>', (string) $g->title);
        $this->assertSame('FR', (string) $g->shipping->children(Rss::NAMESPACE_URI)->country);
        $this->assertSame('<1 kg', (string) $g->product_detail->children(Rss::NAMESPACE_URI)->attribute_value);
    }

    public function testAValueThatSkippedCleaningCannotBreakTheFile(): void
    {
        $xml = Rss::open('s', 'https://shop.example/', 'd') . Rss::item([['brand', "Bad\x01Brand \xE9"]]) . Rss::close();
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function testGoogleWantsTaxLeftOutInTheUsAndCanadaOnly(): void
    {
        $this->assertFalse(Pricing::taxIncluded('US'));
        $this->assertFalse(Pricing::taxIncluded('ca'));
        foreach (['FR', 'DE', 'GB', 'JP', 'AU', 'CH'] as $country) {
            $this->assertTrue(Pricing::taxIncluded($country), $country);
        }
    }

    public function testReferencePriceNeedsTheSetting(): void
    {
        $this->assertFalse(Pricing::referenceAllowed(false, 'US', 'US'));
        $this->assertFalse(Pricing::referenceAllowed(false, 'FR', 'FR'));
    }

    public function testShopInTheEuThatChoseCrossedOutPricesGetsThem(): void
    {
        $this->assertTrue(Pricing::referenceAllowed(true, 'FR', 'FR'));
        $this->assertTrue(Pricing::referenceAllowed(true, 'US', 'FR'));
    }

    public function testShopOutsideTheEuWasNeverAskedAboutTheEuRule(): void
    {
        $this->assertFalse(Pricing::referenceAllowed(true, 'FR', 'US'));
        $this->assertTrue(Pricing::referenceAllowed(true, 'US', 'US'));
        $this->assertTrue(Pricing::referenceAllowed(true, 'GB', 'US'));
    }

    public function testPriceIsWhatTheCustomerPaysWithNoReference(): void
    {
        $this->assertSame(['price' => 80.0, 'sale' => null], Pricing::pair(80.0, 100.0, false));
        $this->assertSame(['price' => 100.0, 'sale' => 80.0], Pricing::pair(80.0, 100.0, true));
        $this->assertSame(['price' => 80.0, 'sale' => null], Pricing::pair(80.0, null, true));
        $this->assertSame(['price' => 80.0, 'sale' => null], Pricing::pair(80.0, 80.0, true));
        $this->assertSame(['price' => 80.0, 'sale' => null], Pricing::pair(80.0, 70.0, true));
    }

    public function testAmountHasTwoDecimalsAndAPoint(): void
    {
        $this->assertSame('15.00 USD', Pricing::amount(15, 'USD'));
        $this->assertSame('1234.50 EUR', Pricing::amount(1234.5, 'EUR'));
        $this->assertSame('29.90 EUR', Pricing::amount(29.8999, 'EUR'));
    }

    public function testSaleDatesOnlyWithARealEnd(): void
    {
        $now = strtotime('2026-11-01 12:00:00');
        $this->assertNull(Pricing::saleDates('2026-10-01 00:00:00', '0000-00-00 00:00:00', $now));
        $this->assertNull(Pricing::saleDates('2026-10-01 00:00:00', null, $now));
        $this->assertNull(Pricing::saleDates('2026-10-01 00:00:00', '2026-10-20 00:00:00', $now), 'already over');
        $this->assertNull(Pricing::saleDates('2026-12-30 00:00:00', '2026-12-01 00:00:00', $now), 'start after end');
        $dates = Pricing::saleDates('2026-10-01 00:00:00', '2026-12-01 00:00:00', $now);
        $this->assertMatchesRegularExpression('~^2026-10-01T00:00[+-]\d{4} / 2026-12-01T00:00[+-]\d{4}$~', (string) $dates);
        $this->assertLessThanOrEqual(51, strlen((string) $dates));
    }

    public function testASaleWithNoStartBeginsNow(): void
    {
        $now = strtotime('2026-11-01 12:00:00');
        $dates = (string) Pricing::saleDates('0000-00-00 00:00:00', '2026-12-01 00:00:00', $now);
        $this->assertStringStartsWith(date('Y-m-d\TH:iO', $now) . ' / ', $dates);
    }

    public function testSameFeedTwiceIsRefused(): void
    {
        $feed = ['country' => 'FR', 'id_lang' => 2, 'mode' => Profiles::PRIMARY, 'target' => Profiles::GOOGLE];
        $this->assertSame('duplicate', Profiles::refusal([$feed], 'FR', 2, Profiles::PRIMARY, Profiles::GOOGLE));
        $this->assertNull(Profiles::refusal([$feed], 'FR', 3, Profiles::PRIMARY, Profiles::GOOGLE));
        $this->assertNull(Profiles::refusal([$feed], 'DE', 2, Profiles::PRIMARY, Profiles::GOOGLE));
        $this->assertNull(Profiles::refusal([$feed], 'FR', 2, Profiles::SUPPLEMENTAL, Profiles::GOOGLE));
        $this->assertNull(Profiles::refusal([$feed], 'FR', 2, Profiles::PRIMARY, Profiles::MICROSOFT));
    }

    public function testMicrosoftHasNoSupplementalSource(): void
    {
        $this->assertSame('microsoft_supplemental', Profiles::refusal([], 'FR', 2, Profiles::SUPPLEMENTAL, Profiles::MICROSOFT));
    }

    public function testSecretMustLookLikeOne(): void
    {
        $this->assertTrue(Profiles::isSecret(str_repeat('a1', 16)));
        $this->assertFalse(Profiles::isSecret(''));
        $this->assertFalse(Profiles::isSecret('short'));
        $this->assertFalse(Profiles::isSecret(str_repeat('z', 32)));
        $this->assertFalse(Profiles::isSecret("' OR 1=1 --"));
    }

    public function testVariantsWithTheirOwnAddressesAreLeftAlone(): void
    {
        $urls = [1 => 'https://s/1-1-tee.html', 2 => 'https://s/1-2-tee.html'];
        $this->assertSame($urls, Links::distinguish($urls));
    }

    public function testVariantsSharingAnAddressGetTheParameter(): void
    {
        $this->assertSame(
            [1 => 'https://s/tee.html?id_product_attribute=1', 2 => 'https://s/tee.html?id_product_attribute=2'],
            Links::distinguish([1 => 'https://s/tee.html', 2 => 'https://s/tee.html'])
        );
        $this->assertSame(
            [1 => 'https://s/tee.html?id_currency=2&id_product_attribute=1', 2 => 'https://s/tee.html?id_currency=2&id_product_attribute=2'],
            Links::distinguish([1 => 'https://s/tee.html?id_currency=2', 2 => 'https://s/tee.html?id_currency=2'])
        );
    }

    public function testASimpleProductKeepsItsAddress(): void
    {
        $this->assertSame([0 => 'https://s/tee.html'], Links::distinguish([0 => 'https://s/tee.html']));
    }

    public function testFetcherIsReducedToAName(): void
    {
        $this->assertSame('Google', Server::fetcher('Mozilla/5.0 (compatible; Googlebot/2.1)'));
        $this->assertSame('Google', Server::fetcher('FeedFetcher-Google; (+http://www.google.com/feedfetcher.html)'));
        $this->assertSame('Microsoft', Server::fetcher('Mozilla/5.0 (compatible; bingbot/2.0)'));
        $this->assertSame('Microsoft', Server::fetcher('AdIdxBot/2.0'));
        $this->assertSame('other', Server::fetcher('curl/8.0'));
        $this->assertSame('other', Server::fetcher(''));
    }

    public function testInStoreAndCouponRangesPassTheCheckDigitButAreRestricted(): void
    {
        // Each has a correct check digit, so only the range rule can catch it.
        foreach (['2000000000008', '0200000000004', '0400000000008', '9800000000007', '9900000000004'] as $code) {
            $this->assertTrue(Gtin::isValid($code), $code . ' check digit');
            $this->assertTrue(Gtin::isRestricted($code), $code);
        }
        $this->assertTrue(Gtin::isRestricted('200000000004'), 'a 12-digit code starting with 2');
    }

    public function testOrdinaryCodesAreNotRestricted(): void
    {
        foreach (['3234567890126', '4006381333931', '9780201379624', '012345678905', '40170725'] as $code) {
            $this->assertFalse(Gtin::isRestricted($code), $code);
        }
    }
}
