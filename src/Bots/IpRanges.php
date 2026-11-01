<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Bots;

/**
 * Checks a visitor's address against an operator's published list, so a fake "GPTBot" can be
 * told from the real one. No address is ever stored, only the verdict.
 */
final class IpRanges
{
    public const VERIFIED = 'verified';
    public const SPOOFED = 'spoofed';
    public const UNVERIFIABLE = 'unverifiable';

    /** @param string[]|null $cidrs null when the operator publishes no list or it is not loaded yet */
    public static function verdict(string $ip, ?array $cidrs): string
    {
        if ($cidrs === null || $cidrs === [] || @inet_pton($ip) === false) {
            return self::UNVERIFIABLE;
        }

        return self::contains($ip, $cidrs) ? self::VERIFIED : self::SPOOFED;
    }

    /** @param string[] $cidrs */
    public static function contains(string $ip, array $cidrs): bool
    {
        $address = @inet_pton($ip);
        if ($address === false) {
            return false;
        }
        foreach ($cidrs as $cidr) {
            $parts = explode('/', $cidr, 2);
            $network = @inet_pton($parts[0]);
            if ($network === false || strlen($network) !== strlen($address)) {
                continue;
            }
            $bits = isset($parts[1]) ? (int) $parts[1] : strlen($network) * 8;
            $bytes = intdiv($bits, 8);
            if (substr($address, 0, $bytes) !== substr($network, 0, $bytes)) {
                continue;
            }
            $rest = $bits % 8;
            if ($rest === 0) {
                return true;
            }
            $mask = chr((0xFF << (8 - $rest)) & 0xFF);
            if (($address[$bytes] & $mask) === ($network[$bytes] & $mask)) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] the prefixes of a published list; empty when the file is not what we expect */
    public static function parseList(string $json): array
    {
        $data = json_decode($json, true);
        $cidrs = [];
        foreach ((array) ($data['prefixes'] ?? []) as $row) {
            $prefix = $row['ipv4Prefix'] ?? $row['ipv6Prefix'] ?? null;
            if (is_string($prefix) && strpos($prefix, '/') !== false && @inet_pton(explode('/', $prefix)[0]) !== false) {
                $cidrs[] = $prefix;
            }
        }

        return $cidrs;
    }

    /**
     * The address a request really came from. Behind a CDN the connection comes from the CDN, so
     * its forwarding header decides. This only feeds a statistic, never an access decision, so a
     * forged header can do no more than flatter one number.
     *
     * @param array<string, mixed> $server
     */
    public static function clientIp(array $server): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP'] as $header) {
            if (!empty($server[$header])) {
                return trim((string) $server[$header]);
            }
        }
        if (!empty($server['HTTP_X_FORWARDED_FOR'])) {
            return trim(explode(',', (string) $server['HTTP_X_FORWARDED_FOR'])[0]);
        }

        return (string) ($server['REMOTE_ADDR'] ?? '');
    }
}
