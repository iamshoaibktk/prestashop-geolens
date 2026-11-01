<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Feed;

/**
 * Marketing with Google sends products to Merchant Center through PrestaShop's cloud, with no file. A
 * primary feed here would then send the same products a second way. Google says a product uploaded
 * through two sources belongs to whichever updated last, so the two would keep taking it from each
 * other. Its side of the connection is not public: this is only what can be seen from the shop.
 */
final class Neighbours
{
    private const MODULE = 'psxmarketingwithgoogle';

    /** That module stores the account number here once its screen has linked one. */
    private const LINKED_ACCOUNT = 'REMARKETING_CONVERSION_MERCHANT_GMC_ID';

    /** @return array{active: bool, linked: bool} */
    public static function marketingWithGoogle(): array
    {
        $active = \Module::isEnabled(self::MODULE);

        return ['active' => $active, 'linked' => $active && (string) \Configuration::get(self::LINKED_ACCOUNT) !== ''];
    }
}
