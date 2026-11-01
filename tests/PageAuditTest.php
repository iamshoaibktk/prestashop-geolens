<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Tests;

use Iamshoaibktk\Geolens\Schema\PageAudit;
use PHPUnit\Framework\TestCase;

final class PageAuditTest extends TestCase
{
    private const TODAY = '2026-11-01';

    private static function page(array $ld = [], string $head = '<link rel="canonical" href="https://s/x">', string $body = '<h1>Blue tee</h1><p>Price: $19.12</p>'): string
    {
        $scripts = '';
        foreach ($ld as $block) {
            $scripts .= '<script type="application/ld+json">' . (is_string($block) ? $block : json_encode($block)) . '</script>';
        }

        return '<html><head><title>x</title>' . $head . $scripts . '</head><body>' . $body . '</body></html>';
    }

    private static function product(array $extra = []): array
    {
        return ['@type' => 'Product', 'name' => 'Blue tee', 'offers' => ['@type' => 'Offer', 'price' => '19.12', 'availability' => 'https://schema.org/InStock']] + $extra;
    }

    private static function codes(string $html, array $headers = [], bool $isProduct = true, string $today = self::TODAY): array
    {
        return array_column(PageAudit::run($html, $headers, $isProduct, $today)['problems'], 'code');
    }

    public function testACleanProductPage(): void
    {
        $this->assertSame([], self::codes(self::page([self::product()])));
    }

    public function testDirectivesThatHideAPage(): void
    {
        $this->assertContains('noindex', self::codes(self::page([], '<meta name="robots" content="noindex,follow">'), [], false));
        $this->assertContains('nosnippet', self::codes(self::page([], '<meta name="robots" content="nosnippet">'), [], false));
        $this->assertContains('nosnippet', self::codes(self::page([], '<meta name="robots" content="max-snippet:0">'), [], false));
        $this->assertContains('noindex', self::codes(self::page(), ['x-robots-tag' => 'noindex'], false));
        $this->assertContains('noindex', self::codes(self::page([], '<meta content="noindex" name="googlebot">'), [], false));
    }

    public function testASafeSnippetLimitIsNotAProblem(): void
    {
        $this->assertNotContains('nosnippet', self::codes(self::page([], '<meta name="robots" content="max-snippet:160">'), [], false));
    }

    public function testBingArchiveRuleExpiresOnItsDate(): void
    {
        $html = self::page([], '<link rel="canonical" href="x"><meta name="robots" content="noarchive,nocache">');
        $this->assertContains('noarchive', self::codes($html, [], false));
        $this->assertContains('nocache', self::codes($html, [], false));
        $this->assertNotContains('noarchive', self::codes($html, [], false, '2027-03-01'));
    }

    public function testMissingCanonical(): void
    {
        $this->assertContains('no_canonical', self::codes(self::page([], ''), [], false));
    }

    public function testPageNearGooglebotsCutoff(): void
    {
        $this->assertContains('too_big', self::codes(self::page([], '<link rel="canonical" href="x">', str_repeat('x', PageAudit::SIZE_WARNING + 1)), [], false));
    }

    public function testBrokenJsonLdIsReported(): void
    {
        $this->assertContains('ld_invalid', self::codes(self::page(['{bad,}'], '<link rel="canonical" href="x">'), [], false));
    }

    public function testDuplicateProductsAreReported(): void
    {
        $result = PageAudit::run(self::page([self::product(), self::product()]), [], true, self::TODAY);
        $this->assertContains('ld_duplicate', array_column($result['problems'], 'code'));
        $this->assertSame(2, $result['types']['Product']);
    }

    public function testDuplicateOrganizationsAreReported(): void
    {
        $this->assertContains('ld_duplicate_org', self::codes(self::page([['@type' => 'Organization'], ['@type' => 'Organization']]), [], false));
    }

    public function testNoProductMarkupOnAProductPage(): void
    {
        $this->assertContains('ld_no_product', self::codes(self::page()));
    }

    public function testMicrodataNextToJsonLd(): void
    {
        $this->assertContains('microdata_product', self::codes(self::page([self::product()], '<link rel="canonical" href="x">', '<div itemtype="https://schema.org/Product"><h1>Blue tee</h1> 19.12</div>')));
    }

    public function testMarkupThePageDoesNotShow(): void
    {
        $html = self::page([self::product()], '<link rel="canonical" href="x">', '<h1>Other</h1><div id="price"></div><script>price="19.12"; name="Blue tee"</script>');
        $codes = self::codes($html);
        $this->assertContains('price_not_visible', $codes);
        $this->assertContains('name_not_visible', $codes);
    }

    public function testProductGroupIsReadThroughItsVariants(): void
    {
        $group = ['@type' => 'ProductGroup', 'name' => 'Blue tee', 'hasVariant' => [['@type' => 'Product', 'offers' => ['price' => '19.12', 'availability' => 'x']]]];
        $this->assertSame([], self::codes(self::page([$group])));
    }

    public function testFactsInMarkupMustBeOnThePage(): void
    {
        $product = self::product();
        $product['offers']['hasMerchantReturnPolicy'] = ['@type' => 'MerchantReturnPolicy'];
        $missing = PageAudit::run(self::page([$product]), [], true, self::TODAY, true);
        $this->assertContains('facts_not_visible', array_column($missing['problems'], 'code'));

        $shown = PageAudit::run(self::page([$product], '<link rel="canonical" href="x">', '<h1>Blue tee</h1> 19.12 <ul class="geolens-facts"></ul>'), [], true, self::TODAY, true);
        $this->assertNotContains('facts_not_visible', array_column($shown['problems'], 'code'));
    }

    public function testNoFactsWarningWhenTheBlockIsSwitchedOffOrNothingIsStated(): void
    {
        $product = self::product();
        $product['offers']['shippingDetails'] = ['@type' => 'OfferShippingDetails'];
        $this->assertNotContains('facts_not_visible', array_column(PageAudit::run(self::page([$product]), [], true, self::TODAY, false)['problems'], 'code'));
        $this->assertNotContains('facts_not_visible', array_column(PageAudit::run(self::page([self::product()]), [], true, self::TODAY, true)['problems'], 'code'));
    }

    public function testProductWithoutAnOffer(): void
    {
        $this->assertContains('no_offer', self::codes(self::page([['@type' => 'Product', 'name' => 'Blue tee']])));
    }

    public function testRollingDateIsNamed(): void
    {
        $product = self::product();
        $product['offers']['priceValidUntil'] = date('Y-m-d', strtotime('+15 days'));
        $this->assertContains('rolling_date', self::codes(self::page([$product])));
    }

    public function testARealFarOffDateIsFine(): void
    {
        $product = self::product();
        $product['offers']['priceValidUntil'] = date('Y-m-d', strtotime('+60 days'));
        $this->assertNotContains('rolling_date', self::codes(self::page([$product])));
    }

    /** @dataProvider prices */
    public function testPriceIsFoundInAnyFormat(string $price, string $text, bool $expected): void
    {
        $this->assertSame($expected, PageAudit::priceVisible($price, $text));
    }

    public static function prices(): array
    {
        return [
            'dot' => ['19.12', 'Price: $19.12', true],
            'comma' => ['19.12', '19,12 €', true],
            'thousands space' => ['1234.50', '1 234,50 €', true],
            'thousands no-break space' => ['1234.50', "1\u{00A0}234,50 €", true],
            'thousands comma' => ['1234.50', '$1,234.50', true],
            'swiss' => ['1234.50', "CHF 1'234.50", true],
            'whole price with no decimals' => ['20.00', '20 €', true],
            'whole price with decimals' => ['20.00', '20,00 €', true],
            'absent' => ['19.12', 'Price: $29.12', false],
            'not part of a longer number' => ['19.12', 'Ref 1191245', false],
        ];
    }

    public function testVisibleTextDropsScriptsAndTheHead(): void
    {
        $text = PageAudit::visibleText('<html><head><title>hidden</title></head><body>Hi <script>no()</script><style>.x{}</style>there &amp; you</body></html>');
        $this->assertSame('Hi there & you', $text);
    }
}
