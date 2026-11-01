<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Indexing;

use Iamshoaibktk\Geolens\Support\Http;

/**
 * Tells Bing, Yandex, Seznam, Naver, Yep, Amazonbot and the Internet Archive that a page changed.
 * Google does not take part. The queue holds objects, not URLs, so saving a product stays cheap
 * and an import of 100,000 products does not build a million links while the merchant waits.
 */
final class IndexNow
{
    public const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /** The protocol asks for at least five minutes between submissions of the same URL. */
    public const SPACING = 300;

    /** The protocol allows 10,000 URLs per request. */
    private const MAX_URLS = 10000;

    private const BATCH_OBJECTS = 1000;

    public static function key(): string
    {
        return (string) \Configuration::getGlobalValue('GEOLENS_INDEXNOW_KEY');
    }

    /**
     * A real file is served without PHP, so it works on any server setup. If the folder cannot be
     * written, the module answers the same URL itself.
     */
    public static function writeKeyFile(): bool
    {
        $file = _PS_ROOT_DIR_ . '/' . self::key() . '.txt';

        return is_file($file) || @file_put_contents($file, self::key()) !== false;
    }

    public static function removeKeyFile(): void
    {
        $key = self::key();
        if ($key !== '' && is_file(_PS_ROOT_DIR_ . '/' . $key . '.txt')) {
            @unlink(_PS_ROOT_DIR_ . '/' . $key . '.txt');
        }
    }

    public static function queue(int $idShop, string $type, int $idObject): void
    {
        if ($idObject <= 0 || !\Configuration::get('GEOLENS_INDEXNOW', null, null, $idShop)) {
            return;
        }
        $p = _DB_PREFIX_ . 'geolens_';
        \Db::getInstance()->execute("INSERT IGNORE INTO `{$p}queue` (`id_shop`, `type`, `id_object`, `date_add`)
            VALUES ({$idShop}, '" . pSQL($type) . "', {$idObject}, NOW())");
        \Configuration::updateGlobalValue('GEOLENS_DIRTY', 1);
    }

    public static function hasWork(): bool
    {
        $p = _DB_PREFIX_ . 'geolens_';
        if (\Db::getInstance()->getValue("SELECT 1 FROM `{$p}queue`")) {
            return true;
        }
        foreach (\Shop::getShops(true, null, true) as $idShop) {
            if ((int) \Configuration::get('GEOLENS_BACKFILL', null, null, (int) $idShop) > 0) {
                return true;
            }
        }

        return false;
    }

    public static function pending(int $idShop): int
    {
        $p = _DB_PREFIX_ . 'geolens_';

        return (int) \Db::getInstance()->getValue("SELECT COUNT(*) FROM `{$p}queue` WHERE `id_shop` = {$idShop}");
    }

    /** Sends the whole catalogue once, working through it from the lowest product ID. */
    public static function startBackfill(int $idShop): void
    {
        \Configuration::updateValue('GEOLENS_BACKFILL', 1, false, null, $idShop);
        \Configuration::updateGlobalValue('GEOLENS_DIRTY', 1);
    }

    public static function backfillAt(int $idShop): int
    {
        return (int) \Configuration::get('GEOLENS_BACKFILL', null, null, $idShop);
    }

    /** @return int how many URLs were accepted */
    public static function flush(int $idShop): int
    {
        if (!\Configuration::get('GEOLENS_INDEXNOW', null, null, $idShop) || time() < (int) \Configuration::get('GEOLENS_RETRY_AT', null, null, $idShop)) {
            return 0;
        }
        $p = _DB_PREFIX_ . 'geolens_';
        $db = \Db::getInstance();
        $rows = (array) $db->executeS("SELECT `type`, `id_object` FROM `{$p}queue` WHERE `id_shop` = {$idShop} ORDER BY `date_add` LIMIT " . self::BATCH_OBJECTS);
        $backfill = [];
        if (count($rows) < self::BATCH_OBJECTS) {
            $backfill = self::backfillBatch($idShop, self::BATCH_OBJECTS - count($rows));
        }
        $objects = array_merge($rows, $backfill);
        if (!$objects) {
            return 0;
        }

        $sent = 0;
        foreach (array_chunk(self::urls($idShop, $objects), self::MAX_URLS) as $urls) {
            $code = self::submit($idShop, $urls);
            self::log($idShop, count($urls), $code);
            if ($code === 200 || $code === 202) {
                $sent += count($urls);
            } elseif ($code === 429 || $code === 403 || $code === 0 || $code >= 500) {
                // Too fast, key not readable yet, or a network failure: all worth trying again later.
                \Configuration::updateValue('GEOLENS_RETRY_AT', time() + 3600, false, null, $idShop);

                return $sent;
            }
            // 400 and 422 mean the request itself is wrong, and sending it again cannot help.
        }

        foreach ($rows as $row) {
            $db->execute("DELETE FROM `{$p}queue` WHERE `id_shop` = {$idShop} AND `type` = '" . pSQL($row['type']) . "' AND `id_object` = " . (int) $row['id_object']);
        }
        if ($backfill) {
            \Configuration::updateValue('GEOLENS_BACKFILL', (int) end($backfill)['id_object'] + 1, false, null, $idShop);
        } elseif (self::backfillAt($idShop) > 0) {
            \Configuration::updateValue('GEOLENS_BACKFILL', 0, false, null, $idShop);
        }

        return $sent;
    }

    /** @return array<int, array{type: string, id_object: int}> */
    private static function backfillBatch(int $idShop, int $limit): array
    {
        $from = self::backfillAt($idShop);
        if ($from <= 0) {
            return [];
        }
        $rows = \Db::getInstance()->executeS('SELECT `id_product` AS id_object, "product" AS type FROM `' . _DB_PREFIX_ . 'product_shop`
            WHERE `id_shop` = ' . $idShop . ' AND `active` = 1 AND `visibility` != "none" AND `id_product` >= ' . $from . '
            ORDER BY `id_product` LIMIT ' . $limit);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Each language has its own URL, and each must be submitted on its own.
     *
     * @return string[]
     */
    private static function urls(int $idShop, array $objects): array
    {
        $link = new \Link();
        $languages = \Language::getLanguages(true, $idShop, true);
        $translated = self::translated($objects, $idShop);
        $urls = [];
        foreach ($objects as $object) {
            foreach ($languages as $idLang) {
                if (!isset($translated[$object['type'] . '-' . $object['id_object'] . '-' . $idLang])) {
                    continue;
                }
                try {
                    $url = self::url($link, (string) $object['type'], (int) $object['id_object'], (int) $idLang, $idShop);
                } catch (\Throwable $e) {
                    continue;
                }
                if ($url !== '') {
                    $urls[$url] = true;
                }
            }
        }

        return array_keys($urls);
    }

    /**
     * A language with no text for an object has no page either, and PrestaShop would build a link to
     * the language's home page instead. One query covers the whole batch.
     *
     * @return array<string, bool> "type-id-lang" for each page that exists
     */
    private static function translated(array $objects, int $idShop): array
    {
        $tables = ['product' => 'product', 'category' => 'category', 'cms' => 'cms'];
        $ids = [];
        foreach ($objects as $object) {
            $ids[(string) $object['type']][] = (int) $object['id_object'];
        }
        $found = [];
        foreach ($ids as $type => $list) {
            if (!isset($tables[$type])) {
                continue;
            }
            $table = $tables[$type];
            $rows = \Db::getInstance()->executeS('SELECT `id_' . $table . '` AS id, `id_lang` FROM `' . _DB_PREFIX_ . $table . '_lang`
                WHERE `id_' . $table . '` IN (' . implode(',', $list) . ') AND `id_shop` = ' . $idShop . ' AND `link_rewrite` != ""');
            foreach ((array) $rows as $row) {
                $found[$type . '-' . (int) $row['id'] . '-' . (int) $row['id_lang']] = true;
            }
        }

        return $found;
    }

    private static function url(\Link $link, string $type, int $id, int $idLang, int $idShop): string
    {
        switch ($type) {
            case 'product':
                return strtok($link->getProductLink($id, null, null, null, $idLang, $idShop, null, false, false, false, [], false), '#') ?: '';
            case 'category':
                return (string) $link->getCategoryLink($id, null, $idLang, null, $idShop);
            case 'cms':
                return (string) $link->getCMSLink($id, null, null, $idLang, $idShop);
        }

        return '';
    }

    /** @param string[] $urls */
    private static function submit(int $idShop, array $urls): int
    {
        $host = (string) parse_url($urls[0], PHP_URL_HOST);
        $port = parse_url($urls[0], PHP_URL_PORT);
        $scheme = (string) parse_url($urls[0], PHP_URL_SCHEME);
        $shop = new \Shop($idShop);
        $body = json_encode([
            'host' => $host,
            'key' => self::key(),
            // A file in a subfolder only covers URLs under it, which is what a subfolder shop has.
            'keyLocation' => $scheme . '://' . $host . ($port ? ':' . $port : '') . $shop->getBaseURI() . self::key() . '.txt',
            'urlList' => array_values($urls),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // The constant lets a test point at a local server instead of the real service.
        $endpoint = defined('GEOLENS_INDEXNOW_ENDPOINT') ? GEOLENS_INDEXNOW_ENDPOINT : self::ENDPOINT;

        return Http::request('POST', $endpoint, ['Content-Type: application/json; charset=utf-8'], (string) $body, 20)['status'];
    }

    private static function log(int $idShop, int $urls, int $code): void
    {
        $p = _DB_PREFIX_ . 'geolens_';
        \Db::getInstance()->execute("INSERT INTO `{$p}indexnow_log` (`id_shop`, `date_add`, `urls`, `code`) VALUES ({$idShop}, NOW(), {$urls}, {$code})");
    }
}
