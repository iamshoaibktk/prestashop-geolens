<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Bots;

/**
 * Finds the crawler a user agent claims to be. Runs on every front request, so it is one regex.
 * The token must stand alone: "Googlebot-Image" is not Googlebot and "GPTBot" inside a longer
 * word is not GPTBot. A match is only a claim; IpRanges decides whether it is true.
 */
final class Matcher
{
    private static ?string $pattern = null;
    /** @var array<string, string> lower case token => token */
    private static array $tokens = [];

    public static function match(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }
        if (self::$pattern === null) {
            foreach (Registry::BOTS as $token => $bot) {
                if ($bot['ua'] !== null) {
                    self::$tokens[strtolower($token)] = $token;
                }
            }
            $names = array_map(static fn ($t) => preg_quote($t, '~'), array_keys(self::$tokens));
            self::$pattern = '~(?<![a-z0-9_-])(' . implode('|', $names) . ')(?![a-z0-9_-])~i';
        }

        return preg_match(self::$pattern, $userAgent, $m) ? self::$tokens[strtolower($m[1])] : null;
    }
}
