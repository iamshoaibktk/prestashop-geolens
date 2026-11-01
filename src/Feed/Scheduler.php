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
 * Decides which feed gets the time a job has. Only feeds of the shop that received the request are
 * built, because product and image addresses depend on the shop the core thinks it is serving.
 */
final class Scheduler
{
    /** Merchant Center fetches once a day by default, so a file a day keeps it as fresh as it reads. */
    public const INTERVAL = 86400;

    /** A little early, so a fetch at the same hour each day does not just miss the new file. */
    private const EARLY = 900;

    /** Asked again this soon while a build is unfinished, in case the request chain broke. */
    private const RESUME = 60;

    /** @return array{pending: bool, finished: int} */
    public static function run(int $idShop, float $budget): array
    {
        $deadline = microtime(true) + $budget;
        $pending = false;
        $finished = 0;
        $now = time();
        foreach (Profiles::all($idShop) as $feed) {
            $job = Store::get(Builder::jobName($feed['key']), $idShop);
            if (!is_array($job)) {
                if (!self::isDue($feed, $idShop, $now)) {
                    continue;
                }
                Builder::start($feed, $idShop);
            } elseif (!empty($job['retry_at']) && $now < (int) $job['retry_at']) {
                continue;
            }
            if (microtime(true) >= $deadline) {
                $pending = true;
                break;
            }
            if (!(new Builder($feed, $idShop))->step($deadline)) {
                $failed = Store::get(Builder::jobName($feed['key']), $idShop);
                if (empty($failed['error'])) {
                    $pending = true;
                    break;
                }
                continue;
            }
            ++$finished;
        }
        self::plan($idShop, $pending);

        return ['pending' => $pending, 'finished' => $finished];
    }

    public static function plan(int $idShop, bool $pending = false): void
    {
        \Configuration::updateValue('GEOLENS_FEED_NEXT', $pending ? time() + self::RESUME : self::next($idShop), false, null, $idShop);
    }

    /** Claims the work, so a busy shop sends one request for it and not one per visitor. */
    public static function claim(int $idShop): void
    {
        \Configuration::updateValue('GEOLENS_FEED_NEXT', time() + self::RESUME * 2, false, null, $idShop);
    }

    private static function isDue(array $feed, int $idShop, int $now): bool
    {
        $last = Store::get(Builder::lastName($feed['key']), $idShop);

        return !is_array($last) || $now >= (int) $last['finished'] + self::INTERVAL - self::EARLY;
    }

    /** @return int when the shop next has work, or 0 when it has no feeds and so never does */
    private static function next(int $idShop): int
    {
        $next = 0;
        foreach (Profiles::all($idShop) as $feed) {
            $job = Store::get(Builder::jobName($feed['key']), $idShop);
            if (is_array($job)) {
                $at = (int) ($job['retry_at'] ?? time());
            } else {
                $last = Store::get(Builder::lastName($feed['key']), $idShop);
                $at = is_array($last) ? (int) $last['finished'] + self::INTERVAL - self::EARLY : time();
            }
            $next = $next === 0 ? $at : min($next, $at);
        }

        return $next;
    }

    /** Half the time PHP allows, so a slow batch at the end still fits. */
    public static function budget(int $most = 25): float
    {
        $limit = (int) ini_get('max_execution_time');

        return (float) ($limit > 0 ? max(3, min($most, (int) floor($limit / 2))) : $most);
    }
}
