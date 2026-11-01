<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Llms;

/**
 * Writes llms.txt to the v2 specification (llmstxt.org, August 2026): an H1, a blockquote, then
 * H2 sections of links. It stays one screen long, with no SKUs, no prices and no instructions
 * aimed at agents: prices go stale in a file, and instructions read as prompt injection.
 */
final class Renderer
{
    /** Agents cut files off silently. The Agent-Friendly Documentation Spec advises staying under this. */
    public const MAX_CHARS = 50000;

    /**
     * @param array<int, array{title: string, links: array<int, array{name: string, url: string, note?: string}>}> $sections
     */
    public static function render(string $title, string $summary, array $sections): string
    {
        $out = '# ' . self::line($title) . "\n";
        $summary = self::line($summary);
        if ($summary !== '') {
            $out .= "\n> " . $summary . "\n";
        }
        foreach ($sections as $section) {
            if (!$section['links']) {
                continue;
            }
            $chunk = "\n## " . self::line($section['title']) . "\n\n";
            foreach ($section['links'] as $link) {
                $note = self::line($link['note'] ?? '');
                $chunk .= '- [' . self::label($link['name']) . '](' . self::url($link['url']) . ')' . ($note === '' ? '' : ': ' . $note) . "\n";
            }
            if (strlen($out) + strlen($chunk) > self::MAX_CHARS) {
                break;
            }
            $out .= $chunk;
        }

        return $out;
    }

    /** One line of plain text, cut at a word so that a note stays a note. */
    public static function note(string $html, int $limit = 120): string
    {
        $text = self::line(strip_tags($html));
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        $cut = mb_substr($text, 0, $limit);
        $space = mb_strrpos($cut, ' ');

        return rtrim(mb_substr($cut, 0, $space === false ? $limit : $space), " ,;:.-") . '...';
    }

    private static function line(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private static function label(string $text): string
    {
        return str_replace(['[', ']'], ['(', ')'], self::line($text));
    }

    private static function url(string $url): string
    {
        return str_replace([' ', '(', ')'], ['%20', '%28', '%29'], trim($url));
    }
}
