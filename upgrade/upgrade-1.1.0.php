<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

use Iamshoaibktk\Geolens\Feed\Files;

/**
 * Adds the product feed. Its settings live in the module's existing store table, so there is no new
 * table. A feed exists only once the merchant adds one, so nothing is built or sent by upgrading.
 *
 * @param Geolens $module
 *
 * @return bool
 */
function upgrade_module_1_1_0($module)
{
    foreach (Shop::getShops(false, null, true) as $idShop) {
        Configuration::updateValue('GEOLENS_FEED_NEXT', 0, false, null, (int) $idShop);
    }
    // Not fatal: without the folder, adding a feed explains the problem to the merchant.
    Files::prepare();

    return true;
}
