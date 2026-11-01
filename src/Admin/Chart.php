<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Admin;

/**
 * Daily columns as inline SVG: no library, no request, and the numbers stay in the page. One axis,
 * rounded data ends, a 2px gap between stacked segments and a hover tooltip per day, with a table
 * of the same numbers beside it for anyone who cannot use the colours.
 */
final class Chart
{
    private const WIDTH = 640;
    private const HEIGHT = 190;
    private const LEFT = 34;
    private const RIGHT = 6;
    private const TOP = 8;
    private const BOTTOM = 24;
    private const MAX_BAR = 24;

    /**
     * @param array<string, array<string, int>> $days day => series key => value
     * @param array<string, array{label: string, color: string}> $series in stacking order, first at the bottom
     */
    public static function columns(array $days, array $series, string $title): string
    {
        $max = 0;
        foreach ($days as $values) {
            $max = max($max, array_sum($values));
        }
        $top = self::niceMax($max);
        $plotW = self::WIDTH - self::LEFT - self::RIGHT;
        $plotH = self::HEIGHT - self::TOP - self::BOTTOM;
        $slot = $plotW / max(1, count($days));
        $bar = min(self::MAX_BAR, max(2, $slot - 2));

        $svg = '<svg class="gl-chart-svg" viewBox="0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '" role="img" aria-label="' . self::e($title) . '">';
        foreach ([0, $top / 2, $top] as $tick) {
            $y = self::TOP + $plotH - ($top > 0 ? $tick / $top : 0) * $plotH;
            $svg .= '<line class="gl-grid" x1="' . self::LEFT . '" x2="' . (self::WIDTH - self::RIGHT) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '"/>';
            $svg .= '<text class="gl-axis" x="' . (self::LEFT - 6) . '" y="' . round($y + 3.5, 1) . '" text-anchor="end">' . self::compact((int) $tick) . '</text>';
        }

        $i = 0;
        $count = count($days);
        foreach ($days as $day => $values) {
            $x = self::LEFT + $i * $slot + ($slot - $bar) / 2;
            $y = (float) (self::TOP + $plotH);
            $tip = [self::shortDate($day)];
            $last = null;
            foreach ($series as $key => $info) {
                if (($values[$key] ?? 0) > 0) {
                    $last = $key;
                }
            }
            $marks = '';
            foreach ($series as $key => $info) {
                $value = (int) ($values[$key] ?? 0);
                if (count($series) > 1) {
                    $tip[] = $info['label'] . ': ' . number_format($value);
                }
                if ($value <= 0) {
                    continue;
                }
                $height = max(1.5, $value / $top * $plotH);
                $y -= $height;
                $gap = $key === $last ? 0 : 2;
                $marks .= '<path d="' . self::bar($x, $y + $gap, $bar, $height - $gap, $key === $last) . '" fill="' . $info['color'] . '"/>';
            }
            if (count($series) === 1) {
                $tip[] = number_format(array_sum($values));
            }
            // The hit area is the whole day's slot, wider than the mark, so thin bars are easy to hover.
            $svg .= '<g class="gl-col" data-tip="' . self::e(implode('|', $tip)) . '">'
                . '<rect class="gl-hit" x="' . round(self::LEFT + $i * $slot, 1) . '" y="' . self::TOP . '" width="' . round($slot, 1) . '" height="' . $plotH . '"/>'
                . $marks . '</g>';
            if ($i === 0 || $i === $count - 1 || $i === (int) floor(($count - 1) / 2)) {
                $anchor = $i === 0 ? 'start' : ($i === $count - 1 ? 'end' : 'middle');
                $labelX = $i === 0 ? self::LEFT : ($i === $count - 1 ? self::WIDTH - self::RIGHT : $x + $bar / 2);
                $svg .= '<text class="gl-axis" x="' . round($labelX, 1) . '" y="' . (self::HEIGHT - 8) . '" text-anchor="' . $anchor . '">' . self::shortDate($day) . '</text>';
            }
            ++$i;
        }

        return $svg . '</svg>';
    }

    /** A column with its data end rounded and its foot square on the baseline. */
    private static function bar(float $x, float $y, float $w, float $h, bool $rounded): string
    {
        $r = $rounded ? min(4.0, $w / 2, $h) : 0.0;
        $x2 = $x + $w;
        $y2 = $y + $h;

        return sprintf('M%.1f %.1fV%.1fQ%.1f %.1f %.1f %.1fH%.1fQ%.1f %.1f %.1f %.1fV%.1fZ', $x, $y2, $y + $r, $x, $y, $x + $r, $y, $x2 - $r, $x2, $y, $x2, $y + $r, $y2);
    }

    /** The next round number above the largest value, so the axis reads 0, 50, 100 and not 0, 47, 94. */
    public static function niceMax(int $value): int
    {
        if ($value <= 4) {
            return 4;
        }
        $step = 10 ** (int) floor(log10($value));
        foreach ([1, 2, 4, 5, 10] as $m) {
            if ($m * $step >= $value) {
                return (int) ($m * $step);
            }
        }

        return (int) (10 * $step);
    }

    public static function compact(int $value): string
    {
        if ($value >= 1000000) {
            return rtrim(rtrim(number_format($value / 1000000, 1), '0'), '.') . 'M';
        }
        if ($value >= 1000) {
            return rtrim(rtrim(number_format($value / 1000, 1), '0'), '.') . 'K';
        }

        return (string) $value;
    }

    private static function shortDate(string $day): string
    {
        return date('j M', (int) strtotime($day));
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
