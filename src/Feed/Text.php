<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Feed;

/** Product text as a feed needs it. Merchant Center and Microsoft both want plain text, not markup. */
final class Text
{
    private static string $lastHtml = '';
    private static string $lastText = '';

    /** Takes out what XML 1.0 cannot carry. One such character in 100,000 products would break the whole file. */
    public static function xmlSafe(string $text): string
    {
        if (!preg_match('/[^\x20-\x7E\t\n\r]/', $text)) {
            return $text;
        }
        $text = mb_scrub($text, 'UTF-8');

        return (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);
    }

    /** One line of clean text, with every kind of space reduced to one. */
    public static function line(string $text): string
    {
        $text = self::xmlSafe($text);
        $text = (string) preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);

        return trim((string) preg_replace('/[\s\x{A0}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]+/u', ' ', $text));
    }

    /** The text a visitor reads from a piece of HTML. */
    public static function plain(string $html): string
    {
        if ($html === '') {
            return '';
        }
        // Every combination of a product asks for the same description, up to thousands of times.
        if ($html === self::$lastHtml) {
            return self::$lastText;
        }
        $text = $html;
        if (strpos($text, '<') !== false) {
            $text = (string) preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', ' ', $text);
            // A closing block becomes a space, so the words on either side of it stay apart.
            $text = (string) preg_replace('~<(?:br|hr|/p|/div|/li|/ul|/ol|/tr|/td|/th|/h[1-6]|/blockquote)\b[^>]*>~i', ' ', $text);
            $text = strip_tags($text);
        }
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Again, for text that was stored with its tags already encoded.
        if (strpos($text, '<') !== false) {
            $text = strip_tags($text);
        }
        $text = self::line($text);
        if (strlen($html) > 200) {
            self::$lastHtml = $html;
            self::$lastText = $text;
        }

        return $text;
    }

    /** Cuts at a word when that keeps most of the text. A limit is a limit, so there is no ellipsis. */
    public static function limit(string $text, int $max): string
    {
        if (strlen($text) <= $max || mb_strlen($text, 'UTF-8') <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max, 'UTF-8');
        $space = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($space !== false && $space > $max * 0.8) {
            $cut = mb_substr($cut, 0, $space, 'UTF-8');
        }

        return rtrim($cut, " ,;:-");
    }
}
