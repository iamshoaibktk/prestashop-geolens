<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Feed;

use Iamshoaibktk\Geolens\Support\Store;

/**
 * The feeds a shop has asked for. Merchant Center ties one file to one country and language, so each
 * is a separate file. "key" names the file on disk and "secret" is the only way to fetch it.
 */
final class Profiles
{
    public const PRIMARY = 'primary';
    public const SUPPLEMENTAL = 'supplemental';

    public const GOOGLE = 'google';
    public const MICROSOFT = 'microsoft';

    private const STORE = 'feeds';

    /** @return array<int, string> ISO code by ID, for the currencies a visitor can really be charged in */
    public static function currencies(int $idShop): array
    {
        $list = [];
        foreach (\Currency::getCurrenciesByIdShop($idShop) as $currency) {
            if (!empty($currency['active']) && empty($currency['deleted'])) {
                $list[(int) $currency['id_currency']] = (string) $currency['iso_code'];
            }
        }

        return $list;
    }

    /** @return array<string, array{key: string, secret: string, country: string, id_lang: int, id_currency: int, mode: string, target: string}> */
    public static function all(int $idShop): array
    {
        $feeds = Store::get(self::STORE, $idShop);

        return is_array($feeds) ? $feeds : [];
    }

    public static function find(int $idShop, string $key): ?array
    {
        return self::all($idShop)[$key] ?? null;
    }

    /** Compared in constant time, since this is the one thing keeping the file private. */
    public static function bySecret(int $idShop, string $secret): ?array
    {
        $found = null;
        foreach (self::all($idShop) as $feed) {
            if (hash_equals((string) $feed['secret'], $secret)) {
                $found = $feed;
            }
        }

        return $found;
    }

    public static function isSecret(string $text): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/', $text);
    }

    /**
     * Two feeds with the same items for the same country and language would be the conflict the
     * screen warns about, so only one of each can exist. Microsoft has no supplemental sources in
     * its documentation.
     *
     * @return string|null why the new feed cannot be added
     */
    public static function refusal(array $all, string $country, int $idLang, string $mode, string $target): ?string
    {
        if ($mode === self::SUPPLEMENTAL && $target === self::MICROSOFT) {
            return 'microsoft_supplemental';
        }
        foreach ($all as $feed) {
            if ($feed['country'] === $country && (int) $feed['id_lang'] === $idLang && $feed['mode'] === $mode && $feed['target'] === $target) {
                return 'duplicate';
            }
        }

        return null;
    }

    public static function add(int $idShop, string $country, int $idLang, int $idCurrency, string $mode, string $target): array
    {
        $all = self::all($idShop);
        $key = bin2hex(random_bytes(8));
        $all[$key] = [
            'key' => $key,
            'secret' => bin2hex(random_bytes(16)),
            'country' => $country,
            'id_lang' => $idLang,
            'id_currency' => $idCurrency,
            'mode' => $mode,
            'target' => $target,
        ];
        Store::set(self::STORE, $all, $idShop);

        return $all[$key];
    }

    public static function remove(int $idShop, string $key): void
    {
        $all = self::all($idShop);
        unset($all[$key]);
        if ($all) {
            Store::set(self::STORE, $all, $idShop);
        } else {
            Store::delete(self::STORE, $idShop);
        }
    }

    /** For a link that leaked. The old address stops working at once, and Merchant Center needs the new one. */
    public static function rotate(int $idShop, string $key): ?array
    {
        $all = self::all($idShop);
        if (!isset($all[$key])) {
            return null;
        }
        $all[$key]['secret'] = bin2hex(random_bytes(16));
        Store::set(self::STORE, $all, $idShop);

        return $all[$key];
    }

    /**
     * With friendly URLs on, the address looks like the file it is, which is what the fetchers document.
     * Without them the server would not pass an unknown path to PrestaShop, so it is a parameter.
     */
    public static function url(array $feed, \Shop $shop, bool $friendly): string
    {
        $base = (\Configuration::get('PS_SSL_ENABLED') ? 'https://' . $shop->domain_ssl : 'http://' . $shop->domain) . $shop->getBaseURI();

        return $base . ($friendly ? 'geolens-feed-' . $feed['secret'] . '.xml' : 'index.php?geolens_feed=' . $feed['secret']);
    }
}
