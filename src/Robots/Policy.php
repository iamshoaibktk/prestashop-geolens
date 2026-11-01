<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Robots;

use Iamshoaibktk\Geolens\Bots\Registry;
use Iamshoaibktk\Geolens\Support\Store;

/**
 * Who may read the shop, and the robots.txt that says so. robots.txt is one physical file for the
 * whole installation, so this policy is installation-wide. Saving a shop URL makes PrestaShop
 * rewrite that file without telling modules, so the block is checked again every day.
 */
final class Policy
{
    private const BACKUP = 'robots_backup';

    public static function path(): string
    {
        return _PS_ROOT_DIR_ . '/robots.txt';
    }

    /** @return string[] */
    public static function blocked(): array
    {
        $blocked = json_decode((string) \Configuration::getGlobalValue('GEOLENS_BLOCKED'), true);

        return is_array($blocked) ? array_values(array_intersect(array_keys(Registry::blockable()), $blocked)) : [];
    }

    public static function isBlocked(string $token): bool
    {
        return in_array($token, self::blocked(), true);
    }

    public static function block(): string
    {
        return Block::build(self::blocked(), (bool) \Configuration::getGlobalValue('GEOLENS_TRAINING'), (bool) \Configuration::getGlobalValue('GEOLENS_SIGNALS'));
    }

    public static function current(): string
    {
        return is_file(self::path()) ? (string) file_get_contents(self::path()) : '';
    }

    public static function isWritable(): bool
    {
        return is_file(self::path()) ? is_writable(self::path()) : is_writable(dirname(self::path()));
    }

    public static function isApplied(): bool
    {
        return Block::isPresent(self::current(), self::block());
    }

    /**
     * Crawlers the merchant's own lines let past the shop's rules. The module's block is left out, and
     * so are tokens that never visit: "User-agent: Google-Extended / Allow: /" opens nothing.
     *
     * @return string[]
     */
    public static function bypassing(): array
    {
        $found = Parser::bypassingGroups(Parser::parse(Block::strip(self::current())));
        $settings = [];
        foreach (Registry::BOTS as $token => $bot) {
            if ($bot['ua'] === null) {
                $settings[] = strtolower($token);
            }
        }

        return array_values(array_diff($found, $settings));
    }

    /**
     * Saves the decision and writes it, keeping the file as it was so the change can be undone.
     *
     * @param string[] $blocked
     *
     * @return bool false when the file cannot be written; the decision is still kept
     */
    public static function save(array $blocked, bool $training, bool $signals, string $preset): bool
    {
        // Pressing Apply twice must not replace the copy from before the real change.
        $before = Block::build(self::blocked(), (bool) \Configuration::getGlobalValue('GEOLENS_TRAINING'), (bool) \Configuration::getGlobalValue('GEOLENS_SIGNALS'));
        if (Block::build($blocked, $training, $signals) !== $before) {
            Store::set(self::BACKUP, [
                'robots' => self::current(),
                'blocked' => self::blocked(),
                'training' => (bool) \Configuration::getGlobalValue('GEOLENS_TRAINING'),
                'signals' => (bool) \Configuration::getGlobalValue('GEOLENS_SIGNALS'),
                'preset' => (string) \Configuration::getGlobalValue('GEOLENS_PRESET'),
            ]);
        }
        self::remember($blocked, $training, $signals, $preset);

        return self::write();
    }

    /** @param string[] $blocked */
    private static function remember(array $blocked, bool $training, bool $signals, string $preset): void
    {
        $known = array_values(array_intersect(array_keys(Registry::blockable()), $blocked));
        \Configuration::updateGlobalValue('GEOLENS_BLOCKED', (string) json_encode($known));
        \Configuration::updateGlobalValue('GEOLENS_TRAINING', (int) $training);
        \Configuration::updateGlobalValue('GEOLENS_SIGNALS', (int) $signals);
        \Configuration::updateGlobalValue('GEOLENS_PRESET', in_array($preset, Registry::PRESETS, true) ? $preset : 'custom');
    }

    public static function write(): bool
    {
        return self::writeFile(Block::apply(self::current(), self::block()));
    }

    public static function canUndo(): bool
    {
        return Store::get(self::BACKUP) !== null;
    }

    /** Puts back the file and the decision from before the last change. */
    public static function undo(): bool
    {
        $backup = Store::get(self::BACKUP);
        if (!is_array($backup)) {
            return false;
        }
        self::remember((array) $backup['blocked'], (bool) $backup['training'], (bool) $backup['signals'], (string) $backup['preset']);
        Store::delete(self::BACKUP);

        return self::writeFile((string) $backup['robots']);
    }

    /** Takes the module's lines out of the file. The merchant's own lines stay. */
    public static function remove(): void
    {
        if (is_file(self::path()) && strpos(self::current(), Block::BEGIN) !== false) {
            self::writeFile(Block::apply(self::current(), ''));
        }
    }

    /** Written to a temporary file first, so a full disk or a crash cannot leave half a robots.txt. */
    private static function writeFile(string $content): bool
    {
        if (!self::isWritable()) {
            return false;
        }
        $temp = self::path() . '.geolens.tmp';
        if (@file_put_contents($temp, $content) !== strlen($content)) {
            @unlink($temp);

            return false;
        }
        if (!@rename($temp, self::path())) {
            @unlink($temp);

            return false;
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(self::path(), true);
        }

        return true;
    }

    /** Crawlers only read robots.txt at the root of a domain. */
    public static function isAtDomainRoot(): bool
    {
        return \Context::getContext()->shop->getBaseURI() === '/';
    }
}
