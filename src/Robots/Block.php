<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Robots;

use Iamshoaibktk\Geolens\Bots\Registry;

/**
 * The module's own part of robots.txt, between two marker lines so it can be found and replaced.
 *
 * Allowed bots get no lines at all. A bot with its own group stops reading the * group, so
 * writing "Allow: /" for it would open the cart, search and filter URLs to it.
 */
final class Block
{
    public const BEGIN = '# BEGIN GEO Lens (managed by the module, edit it in the back office)';
    public const END = '# END GEO Lens';

    /**
     * @param string[] $blocked tokens to disallow
     * @param bool $trainingOptOut publish the shared __usage_ai-training token
     * @param bool $signals publish Content Signals
     *
     * @return string empty when there is nothing to say
     */
    public static function build(array $blocked, bool $trainingOptOut, bool $signals): string
    {
        $known = array_intersect(array_keys(Registry::blockable()), $blocked);
        $lines = [];
        if ($known) {
            foreach ($known as $token) {
                $lines[] = 'User-agent: ' . $token;
            }
            $lines[] = 'Disallow: /';
            $lines[] = '';
        }
        if ($trainingOptOut) {
            $lines[] = 'User-agent: __usage_ai-training';
            $lines[] = 'Disallow: /';
            $lines[] = '';
            // Google reads the shared token as a no for Google-Extended unless it is named. It is
            // a control token that never crawls, so this group opens nothing.
            if (!in_array('Google-Extended', $known, true)) {
                $lines[] = 'User-agent: Google-Extended';
                $lines[] = 'Allow: /';
                $lines[] = '';
            }
        }
        if ($signals) {
            $input = self::allSearchBlocked($known) ? 'no' : 'yes';
            $lines[] = 'User-agent: *';
            $lines[] = 'Content-Signal: search=yes, ai-input=' . $input . ', ai-train=' . ($trainingOptOut ? 'no' : 'yes');
            $lines[] = '';
        }
        if (!$lines) {
            return '';
        }
        array_pop($lines);

        return self::BEGIN . "\n" . implode("\n", $lines) . "\n" . self::END . "\n";
    }

    /** @param string[] $known */
    private static function allSearchBlocked(array $known): bool
    {
        foreach (Registry::blockable() as $token => $bot) {
            if ($bot['purpose'] === Registry::SEARCH && !in_array($token, $known, true)) {
                return false;
            }
        }

        return true;
    }

    /** The file without the module's block. */
    public static function strip(string $robots): string
    {
        $pattern = '~\n*' . preg_quote(self::BEGIN, '~') . '.*?' . preg_quote(self::END, '~') . '\n?~s';

        return (string) preg_replace($pattern, "\n", $robots);
    }

    /** The file with the block in place of any earlier one. */
    public static function apply(string $robots, string $block): string
    {
        $clean = rtrim(self::strip($robots));
        if ($block === '') {
            return $clean === '' ? '' : $clean . "\n";
        }

        return ($clean === '' ? '' : $clean . "\n\n") . $block;
    }

    public static function isPresent(string $robots, string $block): bool
    {
        return $block === '' ? strpos($robots, self::BEGIN) === false : strpos($robots, rtrim($block)) !== false;
    }
}
