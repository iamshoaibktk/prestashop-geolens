<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Support;

use Iamshoaibktk\Geolens\Bots\IpRanges;
use Iamshoaibktk\Geolens\Bots\Registry;
use Iamshoaibktk\Geolens\Feed\Scheduler;
use Iamshoaibktk\Geolens\Indexing\Fresh;
use Iamshoaibktk\Geolens\Indexing\IndexNow;
use Iamshoaibktk\Geolens\Robots\Policy;

/**
 * Background jobs. They run from a real cron when the merchant has one, otherwise from a request
 * the shop sends to itself, and always when the back office opens.
 */
final class Maintenance
{
    /**
     * @param bool $feedOnly a build continuing in a chain of requests, where the other jobs would run
     *                       far more often than they should. IndexNow asks for minutes between submissions.
     * @param int $feedSeconds the most time the feed may take from this request
     *
     * @return array{daily: bool, flushed: int, feed: array{pending: bool, finished: int}}
     */
    public static function run(bool $forceDaily = false, bool $feedOnly = false, int $feedSeconds = 25): array
    {
        $db = \Db::getInstance();
        // Two runs at once would submit the same URLs twice, and write the same feed file twice.
        if ((int) $db->getValue('SELECT GET_LOCK("' . pSQL(_DB_NAME_ . '_geolens') . '", 0)') !== 1) {
            return ['daily' => false, 'flushed' => 0, 'feed' => ['pending' => false, 'finished' => 0]];
        }
        $daily = false;
        $flushed = 0;
        $now = time();
        if (!$feedOnly) {
            if ($forceDaily || $now >= (int) \Configuration::getGlobalValue('GEOLENS_NEXT_DAILY') || Store::age('ip_lists_at') === null || Store::age('ip_lists_at') > 86400) {
                self::daily();
                \Configuration::updateGlobalValue('GEOLENS_NEXT_DAILY', $now + 86400);
                $daily = true;
            }
            foreach (\Shop::getShops(true, null, true) as $idShop) {
                $flushed += IndexNow::flush((int) $idShop);
            }
            \Configuration::updateGlobalValue('GEOLENS_NEXT_FLUSH', $now + IndexNow::SPACING);
            \Configuration::updateGlobalValue('GEOLENS_DIRTY', (int) IndexNow::hasWork());
        }
        try {
            $feed = Scheduler::run((int) \Context::getContext()->shop->id, Scheduler::budget($feedSeconds));
        } catch (\Throwable $e) {
            \PrestaShopLogger::addLog('GEO Lens feed: ' . $e->getMessage(), 3, null, 'Module');
            $feed = ['pending' => false, 'finished' => 0];
        }
        $db->getValue('SELECT RELEASE_LOCK("' . pSQL(_DB_NAME_ . '_geolens') . '")');
        // After the lock is free, or the next request would find it taken and stop the chain.
        if ($feed['pending']) {
            self::spawn(true);
        }

        return ['daily' => $daily, 'flushed' => $flushed, 'feed' => $feed];
    }

    private static function daily(): void
    {
        self::refreshIpLists();
        self::prune();
        Fresh::sweepSales();
        // Saving a shop URL rewrites robots.txt without any hook, which drops the module's block.
        if (!Policy::isApplied()) {
            Policy::write();
        }
    }

    /** A list that fails to load keeps its last good copy. */
    public static function refreshIpLists(): void
    {
        foreach (Registry::IP_LISTS as $key => $url) {
            $response = Http::request('GET', $url, [], null, 8);
            if ($response['status'] === 200) {
                $cidrs = IpRanges::parseList($response['body']);
                if ($cidrs) {
                    Store::set('ip:' . $key, $cidrs);
                }
            }
        }
        Store::set('ip_lists_at', time());
    }

    private static function prune(): void
    {
        $db = \Db::getInstance();
        $p = _DB_PREFIX_ . 'geolens_';
        $days = max(7, (int) \Configuration::getGlobalValue('GEOLENS_RETENTION'));
        $before = date('Y-m-d', strtotime('-' . $days . ' days'));
        foreach (['bot_day', 'demand_day', 'visit_day'] as $table) {
            $db->execute("DELETE FROM `{$p}{$table}` WHERE `day` < '{$before}'");
        }
        foreach (['bot_page', 'visit_page'] as $table) {
            $db->execute("DELETE FROM `{$p}{$table}` WHERE `last_seen` < '{$before}'");
        }
        // Carts that never became an order are of no use once they are older than any report.
        $db->execute("DELETE FROM `{$p}cart` WHERE `date_add` < '{$before}'");
        $db->execute("DELETE FROM `{$p}indexnow_log` WHERE `date_add` < '{$before}'");
    }

    /** Asks the shop to run the jobs in a separate request and does not wait for the answer. */
    public static function spawn(bool $feedOnly = false): void
    {
        $url = self::cronUrl() . ($feedOnly ? '&geolens_step=feed' : '');
        $parts = parse_url($url);
        if (PHP_SAPI === 'cli' || empty($parts['host'])) {
            return;
        }
        $ssl = ($parts['scheme'] ?? 'http') === 'https';
        $socket = @fsockopen(($ssl ? 'ssl://' : '') . $parts['host'], (int) ($parts['port'] ?? ($ssl ? 443 : 80)), $errno, $error, 0.5);
        if (!$socket) {
            return;
        }
        $host = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        fwrite($socket, 'GET ' . $parts['path'] . '?' . $parts['query'] . " HTTP/1.1\r\nHost: {$host}\r\nUser-Agent: GEO Lens\r\nConnection: close\r\n\r\n");
        fclose($socket);
    }

    public static function cronUrl(): string
    {
        $shop = \Context::getContext()->shop;
        $domain = \Configuration::get('PS_SSL_ENABLED') ? 'https://' . $shop->domain_ssl : 'http://' . $shop->domain;

        return $domain . $shop->getBaseURI() . 'index.php?geolens_cron=' . \Configuration::getGlobalValue('GEOLENS_TOKEN');
    }
}
