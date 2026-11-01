<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Support;

/** The one place the module talks to the network. It never throws: a failure is status 0. */
final class Http
{
    /**
     * @param string[] $headers full header lines
     *
     * @return array{status: int, body: string, headers: array<string, string>, ms: int, error: string}
     */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 10, string $userAgent = 'GEO Lens (PrestaShop module)'): array
    {
        $received = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => '',
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$received) {
                // A redirect starts a new set of headers. Only the last response counts.
                if (stripos($line, 'HTTP/') === 0) {
                    $received = [];
                } elseif (strpos($line, ':') !== false) {
                    [$name, $value] = explode(':', $line, 2);
                    $received[strtolower(trim($name))] = trim($value);
                }

                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        $response = curl_exec($ch);
        $result = [
            'status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'body' => is_string($response) ? $response : '',
            'headers' => $received,
            'ms' => (int) round((float) curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000),
            'error' => curl_error($ch),
        ];
        curl_close($ch);

        return $result;
    }
}
