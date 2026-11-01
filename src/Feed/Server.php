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
 * Hands out a finished file to whoever has its secret. It never builds anything, so a fetch costs
 * the same however big the catalogue is. Google's fetcher takes gzip, which makes a file of 200 MB
 * about 10 MB on the wire, and it compresses as it reads, so nothing extra is stored.
 */
final class Server
{
    private const CHUNK = 1048576;

    /** Merchant Center can retry a fetch several times in a row, and one row is enough to show it came. */
    private const FETCH_NOTE_EVERY = 600;

    /** @return never */
    public static function serve(int $idShop, string $secret): void
    {
        $feed = Profiles::isSecret($secret) ? Profiles::bySecret($idShop, $secret) : null;
        if ($feed === null) {
            self::stop(404, 'Not found.');
        }
        $file = Files::live($idShop, $feed['key']);
        if (!is_file($file)) {
            // Temporary, so a fetcher tries again and does not treat this as an empty catalogue.
            header('Retry-After: 3600');
            self::stop(503, 'This feed has not been built yet.');
        }
        self::noteFetch($idShop, $feed['key']);

        $size = (int) filesize($file);
        $modified = (int) filemtime($file);
        $etag = '"' . $modified . '-' . $size . '"';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        @ini_set('zlib.output_compression', '0');
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: inline; filename="geolens-feed-' . strtolower((string) $feed['country']) . '.xml"');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: private, no-transform');
        header('Vary: Accept-Encoding');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
        header('ETag: ' . $etag);
        if (self::notModified($etag, $modified)) {
            http_response_code(304);
            exit;
        }
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'HEAD') {
            header('Content-Length: ' . $size);
            exit;
        }
        ignore_user_abort(false);
        @set_time_limit(0);
        $handle = fopen($file, 'rb');
        if (!$handle) {
            self::stop(500, 'The feed cannot be read.');
        }
        if (function_exists('deflate_init') && stripos((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''), 'gzip') !== false) {
            self::sendCompressed($handle);
        } else {
            header('Content-Length: ' . $size);
            while (!feof($handle)) {
                echo fread($handle, self::CHUNK);
                flush();
            }
        }
        fclose($handle);
        exit;
    }

    /** @param resource $handle */
    private static function sendCompressed($handle): void
    {
        $deflate = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 4]);
        if ($deflate === false) {
            return;
        }
        header('Content-Encoding: gzip');
        while (!feof($handle)) {
            echo deflate_add($deflate, (string) fread($handle, self::CHUNK), ZLIB_NO_FLUSH);
            flush();
        }
        echo deflate_add($deflate, '', ZLIB_FINISH);
    }

    private static function notModified(string $etag, int $modified): bool
    {
        $match = (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
        if ($match !== '') {
            return trim($match) === $etag;
        }
        $since = strtotime((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));

        return $since !== false && $since >= $modified;
    }

    /**
     * The merchant's only proof that a platform really reads the file. Who is told by the user agent,
     * reduced to a name: the module stores no user agents and no addresses.
     */
    private static function noteFetch(int $idShop, string $key): void
    {
        // The back office testing its own link is not a platform reading the file.
        if (hash_equals((string) \Configuration::getGlobalValue('GEOLENS_TOKEN'), (string) ($_SERVER['HTTP_X_GEOLENS_CHECK'] ?? ''))) {
            return;
        }
        $name = 'feed_fetch:' . $key;
        $age = Store::age($name, $idShop);
        if ($age !== null && $age < self::FETCH_NOTE_EVERY) {
            return;
        }
        Store::set($name, ['by' => self::fetcher((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))], $idShop);
    }

    public static function fetcher(string $userAgent): string
    {
        if (stripos($userAgent, 'google') !== false) {
            return 'Google';
        }
        if (preg_match('/bing|microsoft|msn|adidx/i', $userAgent)) {
            return 'Microsoft';
        }

        return 'other';
    }

    /** @return never */
    private static function stop(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo $message;
        exit;
    }
}
