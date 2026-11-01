<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Schema;

/**
 * Finds and rewrites the JSON-LD a page already has. The module merges into what the core or the
 * theme printed instead of adding a second Product, because duplicates are the most common way
 * structured data goes wrong. PrestaShop 9.2 prints it from PHP in Hummingbird and from Smarty in
 * Classic, and older shops and most paid themes differ again, so the finished HTML is the one
 * place that sees them all.
 */
final class JsonLd
{
    private const BLOCK = '~(<script\b[^>]*\btype\s*=\s*["\']application/ld\+json["\'][^>]*>)(.*?)(</script>)~is';

    /**
     * Rewrites each block. $fn gets the decoded data and returns new data, or null to leave the
     * block exactly as it was. A block that does not decode is never touched.
     */
    public static function rewrite(string $html, callable $fn): string
    {
        return (string) preg_replace_callback(self::BLOCK, static function (array $m) use ($fn) {
            $data = json_decode($m[2], true);
            if (!is_array($data)) {
                return $m[0];
            }
            $new = $fn($data);

            return $new === null ? $m[0] : $m[1] . self::encode($new) . $m[3];
        }, $html);
    }

    /** @return array<int, array{raw: string, data: array|null}> every block on the page */
    public static function blocks(string $html): array
    {
        preg_match_all(self::BLOCK, $html, $m);
        $blocks = [];
        foreach ($m[2] as $raw) {
            $data = json_decode($raw, true);
            $blocks[] = ['raw' => $raw, 'data' => is_array($data) ? $data : null];
        }

        return $blocks;
    }

    /** Adds a new block at the end of the head, or does nothing on a page without one. */
    public static function append(string $html, array $data): string
    {
        $tag = '<script type="application/ld+json">' . self::encode($data) . '</script>';
        $pos = stripos($html, '</head>');

        return $pos === false ? $html : substr_replace($html, $tag . "\n", $pos, 0);
    }

    /** Text from the shop inside a script tag, so "</script>" in a product name must not close it. */
    public static function encode(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return str_replace('</', '<\/', (string) $json);
    }

    /**
     * Every node in a block: a single object, a list of objects, or an @graph.
     *
     * @return array<int, array>
     */
    public static function nodes(array $data): array
    {
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            return array_values(array_filter($data['@graph'], 'is_array'));
        }
        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        return [$data];
    }

    public static function hasType(array $node, string $type): bool
    {
        foreach ((array) ($node['@type'] ?? []) as $t) {
            // Google's own examples sometimes write the full https://schema.org/Organization.
            if (is_string($t) && strcasecmp(preg_replace('~^https?://schema\.org/~i', '', $t), $type) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Applies $fn to the nodes of one type inside a block, wherever they sit. Returns null when the
     * block has no such node. $fn may return a list to replace the node with several.
     */
    public static function mapType(array $data, string $type, callable $fn): ?array
    {
        $changed = false;
        $apply = static function (array $nodes) use ($type, $fn, &$changed) {
            $out = [];
            foreach ($nodes as $node) {
                if (is_array($node) && self::hasType($node, $type)) {
                    $changed = true;
                    $new = $fn($node);
                    foreach (array_is_list($new) ? $new : [$new] as $item) {
                        $out[] = $item;
                    }
                } else {
                    $out[] = $node;
                }
            }

            return $out;
        };
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            $data['@graph'] = $apply($data['@graph']);
        } elseif (array_is_list($data)) {
            $data = $apply($data);
        } else {
            $result = $apply([$data]);
            $data = count($result) === 1 ? $result[0] : $result;
        }

        return $changed ? $data : null;
    }
}
