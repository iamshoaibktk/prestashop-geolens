<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Traffic;

use Iamshoaibktk\Geolens\Bots\IpRanges;
use Iamshoaibktk\Geolens\Bots\Registry;

/**
 * Writes counters and nothing about people. No IP address and no user agent is ever stored, and
 * paths lose their query string, which is where personal data would be.
 */
final class Recorder
{
    /** Keeps one abusive bot from filling the table with made-up URLs. */
    private const MAX_PAGE_ROWS = 200000;

    public static function bot(int $idShop, string $token, string $verdict, int $status, int $ms, string $path): void
    {
        $p = _DB_PREFIX_ . 'geolens_';
        $bot = pSQL($token);
        $ok = (int) ($status >= 200 && $status < 400);
        $missing = (int) ($status === 404 || $status === 410);
        $refused = (int) ($status === 401 || $status === 403 || $status === 429);
        $errors = (int) ($status >= 500);
        $verified = (int) ($verdict === IpRanges::VERIFIED);
        $spoofed = (int) ($verdict === IpRanges::SPOOFED);
        \Db::getInstance()->execute("INSERT INTO `{$p}bot_day` (`id_shop`, `day`, `bot`, `hits`, `ok`, `missing`, `refused`, `errors`, `verified`, `spoofed`, `ms`)
            VALUES ({$idShop}, '" . date('Y-m-d') . "', '{$bot}', 1, {$ok}, {$missing}, {$refused}, {$errors}, {$verified}, {$spoofed}, {$ms})
            ON DUPLICATE KEY UPDATE `hits` = `hits` + 1, `ok` = `ok` + {$ok}, `missing` = `missing` + {$missing}, `refused` = `refused` + {$refused},
                `errors` = `errors` + {$errors}, `verified` = `verified` + {$verified}, `spoofed` = `spoofed` + {$spoofed}, `ms` = `ms` + {$ms}");

        // Pages are kept for bots that act on a person's question or feed an answer index, and only
        // what they really read or failed to find. A fake bot probing for .env tells us nothing.
        $purpose = Registry::BOTS[$token]['purpose'] ?? '';
        if ($verdict !== IpRanges::SPOOFED && ($status === 200 || $status === 404)
            && ($purpose === Registry::USER || $purpose === Registry::SEARCH)) {
            self::botPage($idShop, $bot, $status, $path);
        }
    }

    private static function botPage(int $idShop, string $bot, int $status, string $path): void
    {
        static $full = null;
        $p = _DB_PREFIX_ . 'geolens_';
        $db = \Db::getInstance();
        $path = substr($path, 0, 255);
        $hash = md5($path);
        $sql = pSQL($path);
        if ($full === null) {
            $full = (int) $db->getValue("SELECT COUNT(*) FROM (SELECT 1 FROM `{$p}bot_page` LIMIT " . (self::MAX_PAGE_ROWS + 1) . ') t') > self::MAX_PAGE_ROWS;
        }
        if ($full) {
            $db->execute("UPDATE `{$p}bot_page` SET `hits` = `hits` + 1, `last_seen` = NOW()
                WHERE `id_shop` = {$idShop} AND `bot` = '{$bot}' AND `path_hash` = '{$hash}' AND `status` = {$status}");

            return;
        }
        $db->execute("INSERT INTO `{$p}bot_page` (`id_shop`, `bot`, `path_hash`, `path`, `status`, `hits`, `last_seen`)
            VALUES ({$idShop}, '{$bot}', '{$hash}', '{$sql}', {$status}, 1, NOW())
            ON DUPLICATE KEY UPDATE `hits` = `hits` + 1, `last_seen` = NOW()");
    }

    public static function demand(int $idShop, string $what, string $bot): void
    {
        $p = _DB_PREFIX_ . 'geolens_';
        \Db::getInstance()->execute("INSERT INTO `{$p}demand_day` (`id_shop`, `day`, `what`, `bot`, `hits`)
            VALUES ({$idShop}, '" . date('Y-m-d') . "', '" . pSQL($what) . "', '" . pSQL($bot) . "', 1)
            ON DUPLICATE KEY UPDATE `hits` = `hits` + 1");
    }

    public static function visit(int $idShop, string $engine, string $path): void
    {
        $p = _DB_PREFIX_ . 'geolens_';
        $db = \Db::getInstance();
        $e = pSQL($engine);
        $path = substr($path, 0, 255);
        $db->execute("INSERT INTO `{$p}visit_day` (`id_shop`, `day`, `engine`, `visits`) VALUES ({$idShop}, '" . date('Y-m-d') . "', '{$e}', 1)
            ON DUPLICATE KEY UPDATE `visits` = `visits` + 1");
        $db->execute("INSERT INTO `{$p}visit_page` (`id_shop`, `engine`, `path_hash`, `path`, `visits`, `last_seen`)
            VALUES ({$idShop}, '{$e}', '" . md5($path) . "', '" . pSQL($path) . "', 1, NOW())
            ON DUPLICATE KEY UPDATE `visits` = `visits` + 1, `last_seen` = NOW()");
    }

    public static function cart(int $idShop, int $idCart, string $engine, string $landing): void
    {
        $p = _DB_PREFIX_ . 'geolens_';
        \Db::getInstance()->execute("INSERT IGNORE INTO `{$p}cart` (`id_cart`, `id_shop`, `engine`, `landing`, `date_add`)
            VALUES ({$idCart}, {$idShop}, '" . pSQL($engine) . "', '" . pSQL(substr($landing, 0, 255)) . "', NOW())");
    }

    /**
     * A payment module's webhook validates the order on a server with no visitor cookie, so the
     * link to the visit is made earlier, on the cart.
     */
    public static function order(int $idShop, int $idOrder, int $idCart): void
    {
        $p = _DB_PREFIX_ . 'geolens_';
        $db = \Db::getInstance();
        $row = $db->getRow("SELECT `engine`, `landing` FROM `{$p}cart` WHERE `id_cart` = {$idCart}");
        if ($row) {
            $db->execute("INSERT IGNORE INTO `{$p}order` (`id_order`, `id_shop`, `engine`, `landing`, `date_add`)
                VALUES ({$idOrder}, {$idShop}, '" . pSQL((string) $row['engine']) . "', '" . pSQL((string) $row['landing']) . "', NOW())");
        }
    }
}
