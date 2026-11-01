<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Support;

use Iamshoaibktk\Geolens\Feed\Files;

/** Tables and defaults. Everything here is removed again on uninstall. */
final class Installer
{
    public const TABLES = ['store', 'bot_day', 'bot_page', 'demand_day', 'visit_day', 'visit_page', 'cart', 'order', 'queue', 'indexnow_log', 'fresh'];

    public const HOOKS = [
        'actionDispatcherBefore',
        'actionOutputHTMLBefore',
        'actionFrontControllerSetMedia',
        'displayProductAdditionalInfo',
        'actionCartSave',
        'actionValidateOrder',
        'actionAdminMetaAfterWriteRobotsFile',
        'actionObjectProductAddAfter',
        'actionObjectProductUpdateAfter',
        'actionObjectCategoryAddAfter',
        'actionObjectCategoryUpdateAfter',
        'actionObjectCmsAddAfter',
        'actionObjectCmsUpdateAfter',
        'actionObjectSpecificPriceAddAfter',
        'actionObjectSpecificPriceUpdateAfter',
        'actionObjectSpecificPriceDeleteAfter',
        'actionUpdateQuantity',
    ];

    /** Installation-wide settings. Nothing is blocked and nothing is sent anywhere until the merchant says so. */
    public const GLOBAL_DEFAULTS = [
        'GEOLENS_LOG_BOTS' => 1,
        'GEOLENS_LOG_VISITS' => 1,
        'GEOLENS_GUARD' => 0,
        'GEOLENS_ATTR_WINDOW' => 7200,
        'GEOLENS_RETENTION' => 90,
        'GEOLENS_BLOCKED' => '[]',
        'GEOLENS_TRAINING' => 0,
        'GEOLENS_SIGNALS' => 0,
        'GEOLENS_PRESET' => 'visibility',
        'GEOLENS_NEXT_DAILY' => 0,
        'GEOLENS_NEXT_FLUSH' => 0,
        'GEOLENS_DIRTY' => 0,
    ];

    /** Per-shop settings. */
    public const SHOP_DEFAULTS = [
        'GEOLENS_SCHEMA' => 1,
        'GEOLENS_VARIANTS' => 1,
        'GEOLENS_VARIANT_LIMIT' => 50,
        'GEOLENS_SHOP_BRAND' => 0,
        'GEOLENS_SHOW_FACTS' => 1,
        'GEOLENS_INDEXNOW' => 0,
        'GEOLENS_LLMS' => 0,
        'GEOLENS_FEED_NEXT' => 0,
    ];

    public static function install(): bool
    {
        $db = \Db::getInstance();
        $p = _DB_PREFIX_ . 'geolens_';
        $engine = _MYSQL_ENGINE_;
        $tables = [
            "CREATE TABLE IF NOT EXISTS `{$p}store` (
                `name` VARCHAR(64) NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `value` MEDIUMTEXT NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`name`, `id_shop`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}bot_day` (
                `id_shop` INT UNSIGNED NOT NULL,
                `day` DATE NOT NULL,
                `bot` VARCHAR(40) NOT NULL,
                `hits` INT UNSIGNED NOT NULL DEFAULT 0,
                `ok` INT UNSIGNED NOT NULL DEFAULT 0,
                `missing` INT UNSIGNED NOT NULL DEFAULT 0,
                `refused` INT UNSIGNED NOT NULL DEFAULT 0,
                `errors` INT UNSIGNED NOT NULL DEFAULT 0,
                `verified` INT UNSIGNED NOT NULL DEFAULT 0,
                `spoofed` INT UNSIGNED NOT NULL DEFAULT 0,
                `ms` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_shop`, `day`, `bot`),
                KEY `day` (`day`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}bot_page` (
                `id_shop` INT UNSIGNED NOT NULL,
                `bot` VARCHAR(40) NOT NULL,
                `path_hash` CHAR(32) NOT NULL,
                `path` VARCHAR(255) NOT NULL,
                `status` SMALLINT UNSIGNED NOT NULL,
                `hits` INT UNSIGNED NOT NULL DEFAULT 0,
                `last_seen` DATETIME NOT NULL,
                PRIMARY KEY (`id_shop`, `bot`, `path_hash`, `status`),
                KEY `last_seen` (`last_seen`),
                KEY `top` (`id_shop`, `status`, `hits`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}demand_day` (
                `id_shop` INT UNSIGNED NOT NULL,
                `day` DATE NOT NULL,
                `what` VARCHAR(30) NOT NULL,
                `bot` VARCHAR(40) NOT NULL,
                `hits` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_shop`, `day`, `what`, `bot`),
                KEY `day` (`day`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}visit_day` (
                `id_shop` INT UNSIGNED NOT NULL,
                `day` DATE NOT NULL,
                `engine` VARCHAR(20) NOT NULL,
                `visits` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_shop`, `day`, `engine`),
                KEY `day` (`day`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}visit_page` (
                `id_shop` INT UNSIGNED NOT NULL,
                `engine` VARCHAR(20) NOT NULL,
                `path_hash` CHAR(32) NOT NULL,
                `path` VARCHAR(255) NOT NULL,
                `visits` INT UNSIGNED NOT NULL DEFAULT 0,
                `last_seen` DATETIME NOT NULL,
                PRIMARY KEY (`id_shop`, `engine`, `path_hash`),
                KEY `last_seen` (`last_seen`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}cart` (
                `id_cart` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `engine` VARCHAR(20) NOT NULL,
                `landing` VARCHAR(255) NOT NULL,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_cart`),
                KEY `report` (`id_shop`, `date_add`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}order` (
                `id_order` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `engine` VARCHAR(20) NOT NULL,
                `landing` VARCHAR(255) NOT NULL,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_order`),
                KEY `report` (`id_shop`, `date_add`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}queue` (
                `id_shop` INT UNSIGNED NOT NULL,
                `type` VARCHAR(10) NOT NULL,
                `id_object` INT UNSIGNED NOT NULL,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_shop`, `type`, `id_object`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}indexnow_log` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL,
                `date_add` DATETIME NOT NULL,
                `urls` INT UNSIGNED NOT NULL,
                `code` SMALLINT UNSIGNED NOT NULL,
                PRIMARY KEY (`id`),
                KEY `report` (`id_shop`, `id`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}fresh` (
                `id_shop` INT UNSIGNED NOT NULL,
                `id_product` INT UNSIGNED NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_shop`, `id_product`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($tables as $sql) {
            if (!$db->execute($sql)) {
                return false;
            }
        }
        foreach (self::GLOBAL_DEFAULTS as $name => $value) {
            \Configuration::updateGlobalValue($name, $value);
        }
        foreach (self::SHOP_DEFAULTS as $name => $value) {
            \Configuration::updateValue($name, $value);
        }
        \Configuration::updateGlobalValue('GEOLENS_TOKEN', bin2hex(random_bytes(16)));
        \Configuration::updateGlobalValue('GEOLENS_INDEXNOW_KEY', bin2hex(random_bytes(16)));

        return true;
    }

    public static function uninstall(): bool
    {
        Files::removeAll();
        foreach (self::TABLES as $table) {
            \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'geolens_' . $table . '`');
        }
        \Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` LIKE "GEOLENS\_%"');
        \Configuration::loadConfiguration();

        return true;
    }
}
