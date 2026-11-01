<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * Checks a finished feed file against the module's reading of Google's specification:
 * php tests/check-feed.php <file> [primary|supplemental] [google|microsoft]
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
require __DIR__ . '/../vendor/autoload.php';

use Iamshoaibktk\Geolens\Feed\Profiles;
use Iamshoaibktk\Geolens\Feed\Spec;

$file = $argv[1] ?? '';
if (!is_file($file)) {
    fwrite(STDERR, "Usage: php tests/check-feed.php <file> [primary|supplemental] [google|microsoft]\n");
    exit(2);
}
$started = microtime(true);
$result = Spec::checkFile($file, $argv[2] ?? Profiles::PRIMARY, $argv[3] ?? Profiles::GOOGLE);
printf("%d items, %d groups of variants, %.1f MB, read in %.1f s\n", $result['items'], $result['groups'], filesize($file) / 1048576, microtime(true) - $started);
printf("%d items with problems\n", $result['invalid']);
foreach ($result['problems'] as $problem => $count) {
    printf("  %6d  %s\n", $count, $problem);
}
foreach ($result['examples'] as $example) {
    echo '  e.g. ', $example, "\n";
}
exit($result['invalid'] > 0 ? 1 : 0);
