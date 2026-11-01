<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Feed;

/**
 * Where finished feeds live. Under var/, so clearing the cache does not lose one, and outside the
 * web root's reach: the only way in is Server, which asks for the secret. A random key names each
 * file, so a server that ignores .htaccess still has nothing guessable to hand out.
 */
final class Files
{
    private const RESERVE = 268435456;

    public static function dir(): string
    {
        return _PS_ROOT_DIR_ . '/var/modules/geolens/feeds/';
    }

    public static function live(int $idShop, string $key): string
    {
        return self::dir() . $idShop . '-' . $key . '.xml';
    }

    /** Built here and moved into place only when complete, so a fetch never gets half a file. */
    public static function partial(int $idShop, string $key): string
    {
        return self::dir() . $idShop . '-' . $key . '.tmp';
    }

    public static function prepare(): bool
    {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
        if (!is_file($dir . '.htaccess')) {
            @file_put_contents($dir . '.htaccess', "Require all denied\n");
        }
        if (!is_file($dir . 'index.php')) {
            @file_put_contents($dir . 'index.php', "<?php\nexit;\n");
        }

        return is_writable($dir);
    }

    /** A big catalogue makes a big file, and a full disk would stop the shop itself. So the build stops first. */
    public static function hasRoom(): bool
    {
        $free = @disk_free_space(self::dir());

        return $free === false || $free > self::RESERVE;
    }

    public static function remove(int $idShop, string $key): void
    {
        foreach ([self::live($idShop, $key), self::partial($idShop, $key)] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /** Everything the module put under var/, and nothing else. */
    public static function removeAll(): void
    {
        $dir = self::dir();
        foreach ((array) glob($dir . '*.{xml,tmp}', GLOB_BRACE) as $file) {
            @unlink((string) $file);
        }
        foreach (['.htaccess', 'index.php'] as $name) {
            if (is_file($dir . $name)) {
                @unlink($dir . $name);
            }
        }
        @rmdir($dir);
        @rmdir(dirname($dir));
    }
}
