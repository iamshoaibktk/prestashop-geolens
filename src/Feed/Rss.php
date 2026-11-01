<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Feed;

/**
 * Google's RSS 2.0 shape: every field in the g: namespace, one element per value and one child per
 * sub-attribute. Items are written as strings and not through XMLWriter, because a file is built
 * over many requests and has to be resumed from any item.
 */
final class Rss
{
    public const NAMESPACE_URI = 'http://base.google.com/ns/1.0';

    public static function open(string $title, string $link, string $description): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss xmlns:g="' . self::NAMESPACE_URI . '" version="2.0">' . "\n"
            . "<channel>\n"
            . '<title>' . self::escape($title) . "</title>\n"
            . '<link>' . self::escape($link) . "</link>\n"
            . '<description>' . self::escape($description) . "</description>\n";
    }

    public static function close(): string
    {
        return "</channel>\n</rss>\n";
    }

    /** @param array<int, array{0: string, 1: string|array<string, string>}> $attributes */
    public static function item(array $attributes): string
    {
        $xml = "<item>\n";
        foreach ($attributes as [$name, $value]) {
            if (is_array($value)) {
                $xml .= '<g:' . $name . '>';
                foreach ($value as $child => $text) {
                    $xml .= '<g:' . $child . '>' . self::escape((string) $text) . '</g:' . $child . '>';
                }
                $xml .= '</g:' . $name . ">\n";
            } else {
                $xml .= '<g:' . $name . '>' . self::escape($value) . '</g:' . $name . ">\n";
            }
        }

        return $xml . "</item>\n";
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars(Text::xmlSafe($text), ENT_NOQUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
