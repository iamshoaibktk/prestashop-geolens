<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Bots;

use Iamshoaibktk\Geolens\Robots\Parser;
use Iamshoaibktk\Geolens\Robots\Policy;
use Iamshoaibktk\Geolens\Support\Http;
use Iamshoaibktk\Geolens\Support\Store;

/**
 * Asks the shop for a product page in each crawler's name, from the outside, the way a crawler
 * would. robots.txt alone misleads: a firewall or CDN can refuse a bot the file welcomes. The
 * reverse also happens, because this request comes from the shop's own address, which a firewall
 * may treat differently from the bot's real one. The screen says so.
 */
final class AccessCheck
{
    public const OPEN = 'open';
    public const ROBOTS = 'robots';
    public const SERVER = 'server';
    public const CHALLENGE = 'challenge';

    /** @return array{product: string, path: string, robots: string, status: int, differs: bool, cdn: string} */
    public static function prepare(\Context $context): array
    {
        $shop = $context->shop;
        $domain = (\Configuration::get('PS_SSL_ENABLED') ? 'https://' . $shop->domain_ssl : 'http://' . $shop->domain) . $shop->getBaseURI();
        $idProduct = (int) \Db::getInstance()->getValue('SELECT ps.`id_product` FROM `' . _DB_PREFIX_ . 'product_shop` ps
            WHERE ps.`id_shop` = ' . (int) $shop->id . ' AND ps.`active` = 1 AND ps.`visibility` != "none" ORDER BY ps.`id_product`');
        $product = $idProduct ? strtok((string) $context->link->getProductLink($idProduct), '#') : $domain;

        $response = Http::request('GET', $domain . 'robots.txt');
        $served = $response['status'] === 200 ? $response['body'] : '';
        $prepared = [
            'product' => (string) $product,
            'path' => (string) parse_url((string) $product, PHP_URL_PATH),
            'robots' => $served,
            'status' => $response['status'],
            // Cloudflare can place its own rules in front of the file, so what crawlers read is not
            // always what the shop wrote.
            'differs' => $response['status'] === 200 && trim($served) !== trim(Policy::current()),
            'cdn' => self::cdn($response['headers']),
        ];
        Store::set('access_context', $prepared, (int) $shop->id);
        Store::set('access_results', [], (int) $shop->id);

        return $prepared;
    }

    /** @return array{token: string, robots_ok: bool, status: int|null, ms: int|null, state: string, expected: bool} */
    public static function bot(string $token, int $idShop): array
    {
        $context = (array) Store::get('access_context', $idShop);
        $bot = Registry::BOTS[$token];
        $robotsOk = Parser::isAllowed(Parser::parse((string) ($context['robots'] ?? '')), $token, (string) ($context['path'] ?? '/'));
        $status = null;
        $ms = null;
        $state = $robotsOk ? self::OPEN : self::ROBOTS;
        if ($bot['ua'] !== null && !empty($context['product'])) {
            $response = Http::request('GET', (string) $context['product'], ['X-GeoLens-Check: ' . \Configuration::getGlobalValue('GEOLENS_TOKEN')], null, 20, (string) $bot['ua']);
            $status = $response['status'];
            $ms = $response['ms'];
            if (self::isChallenge($response['headers'], $response['body'])) {
                $state = self::CHALLENGE;
            } elseif ($status === 0 || $status >= 400) {
                $state = self::SERVER;
            }
        }
        $result = [
            'token' => $token,
            'robots_ok' => $robotsOk,
            'status' => $status,
            'ms' => $ms,
            'state' => $state,
            // Closed because the merchant chose that is working as intended, not a problem.
            'expected' => $state === self::OPEN ? !Policy::isBlocked($token) : Policy::isBlocked($token),
        ];
        $all = (array) Store::get('access_results', $idShop);
        $all[$token] = $result;
        Store::set('access_results', $all, $idShop);

        return $result;
    }

    /** @param array<string, string> $headers */
    public static function cdn(array $headers): string
    {
        if (isset($headers['cf-ray']) || stripos($headers['server'] ?? '', 'cloudflare') !== false) {
            return 'Cloudflare';
        }
        if (isset($headers['x-sucuri-id'])) {
            return 'Sucuri';
        }
        if (isset($headers['x-amz-cf-id'])) {
            return 'CloudFront';
        }
        if (isset($headers['x-served-by']) && stripos($headers['x-served-by'], 'cache') !== false) {
            return 'Fastly';
        }

        return '';
    }

    /** @param array<string, string> $headers */
    public static function isChallenge(array $headers, string $body): bool
    {
        return isset($headers['cf-mitigated']) || stripos(substr($body, 0, 3000), 'Just a moment...') !== false;
    }
}
