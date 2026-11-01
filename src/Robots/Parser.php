<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Robots;

/**
 * Reads robots.txt the way RFC 9309 says a crawler must:
 * - consecutive user-agent lines share one group, and groups naming the same token are merged;
 * - a crawler obeys only its own group, and the * group only if it has none;
 * - the longest matching rule wins, and allow wins a tie.
 */
final class Parser
{
    /** @return array<string, array<int, array{allow: bool, path: string}>> lower case token => rules */
    public static function parse(string $text): array
    {
        $groups = [];
        $current = [];
        $inRules = false;
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if ($line === '' || strpos($line, ':') === false) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);
            if ($field === 'user-agent') {
                if ($inRules) {
                    $current = [];
                    $inRules = false;
                }
                $token = strtolower($value);
                $current[] = $token;
                $groups[$token] = $groups[$token] ?? [];
            } elseif ($field === 'allow' || $field === 'disallow') {
                $inRules = true;
                // An empty "Disallow:" is a group with no restriction, not a rule.
                if ($value !== '') {
                    foreach ($current as $token) {
                        $groups[$token][] = ['allow' => $field === 'allow', 'path' => $value];
                    }
                }
            }
        }

        return $groups;
    }

    /** @param array<string, array<int, array{allow: bool, path: string}>> $groups */
    public static function isAllowed(array $groups, string $token, string $path): bool
    {
        $rules = $groups[strtolower($token)] ?? $groups['*'] ?? [];
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        if ($path === '/robots.txt') {
            return true;
        }
        $best = -1;
        $allowed = true;
        foreach ($rules as $rule) {
            if (!self::matches($rule['path'], $path)) {
                continue;
            }
            $length = strlen($rule['path']);
            if ($length > $best || ($length === $best && $rule['allow'])) {
                $best = $length;
                $allowed = $rule['allow'];
            }
        }

        return $allowed;
    }

    private static function matches(string $pattern, string $path): bool
    {
        $anchored = substr($pattern, -1) === '$';
        if ($anchored) {
            $pattern = substr($pattern, 0, -1);
        }
        $regex = str_replace('\*', '.*', preg_quote($pattern, '~'));

        return (bool) preg_match('~^' . $regex . ($anchored ? '$' : '') . '~', $path);
    }

    /**
     * Named groups that stop a crawler obeying the shop's rules. A bare "User-agent: GPTBot /
     * Allow: /" looks harmless, but that bot then ignores everything in the * group: cart, search
     * and every filter URL.
     *
     * @param array<string, array<int, array{allow: bool, path: string}>> $groups
     *
     * @return string[] lower case tokens
     */
    public static function bypassingGroups(array $groups): array
    {
        $shared = count(array_filter($groups['*'] ?? [], static fn ($r) => !$r['allow']));
        if ($shared === 0) {
            return [];
        }
        $found = [];
        foreach ($groups as $token => $rules) {
            if ($token === '*') {
                continue;
            }
            $disallows = count(array_filter($rules, static fn ($r) => !$r['allow']));
            if ($disallows === 0) {
                $found[] = $token;
            }
        }

        return $found;
    }
}
