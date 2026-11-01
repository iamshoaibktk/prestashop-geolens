<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Feed;

use Iamshoaibktk\Geolens\Schema\Facts;
use Iamshoaibktk\Geolens\Support\Store;

/**
 * Builds one feed over as many requests as it takes. After each batch it records how far the file
 * got and how many bytes that was, so a request killed halfway costs one batch at most: the next
 * cuts the file back to that length and carries on from that product.
 */
final class Builder
{
    private const BATCH_PRODUCTS = 200;
    private const BATCH_ITEMS = 1000;
    private const SAMPLES = 10;

    /** A failed build tries again this much later. */
    private const RETRY = 3600;

    private array $feed;
    private int $idShop;

    public function __construct(array $feed, int $idShop)
    {
        $this->feed = $feed;
        $this->idShop = $idShop;
    }

    public static function jobName(string $key): string
    {
        return 'feed_job:' . $key;
    }

    public static function lastName(string $key): string
    {
        return 'feed_last:' . $key;
    }

    public static function start(array $feed, int $idShop): void
    {
        $source = new Source($idShop, (int) $feed['id_lang'], self::visitorGroup(), $feed['mode'] === Profiles::SUPPLEMENTAL);
        Store::set(self::jobName($feed['key']), [
            'started' => time(),
            'updated' => time(),
            'total' => $source->total(),
            'cursor' => 0,
            'bytes' => 0,
            'items' => 0,
            'products' => 0,
            'skipped' => (object) [],
            'samples' => (object) [],
            'notes' => (object) [],
            'problems' => [],
            'seconds' => 0.0,
            'steps' => 0,
            'peak' => 0,
        ], $idShop);
    }

    /** @return bool true once there is nothing left to do for this build */
    public function step(float $deadline): bool
    {
        $name = self::jobName($this->feed['key']);
        $job = Store::get($name, $this->idShop);
        if (!is_array($job)) {
            return true;
        }
        $job['skipped'] = (array) ($job['skipped'] ?? []);
        $job['samples'] = (array) ($job['samples'] ?? []);
        $job['notes'] = (array) ($job['notes'] ?? []);
        $began = microtime(true);
        try {
            $finished = $this->work($job, $deadline);
        } catch (\Throwable $e) {
            $job['error'] = $e->getMessage();
            $job['retry_at'] = time() + self::RETRY;
            Store::set($name, $job, $this->idShop);
            \PrestaShopLogger::addLog('GEO Lens feed: ' . $e->getMessage(), 3, null, 'Module');

            return false;
        }
        $job['seconds'] = (float) $job['seconds'] + microtime(true) - $began;
        $job['steps'] = (int) $job['steps'] + 1;
        $job['peak'] = max((int) $job['peak'], memory_get_peak_usage(true));
        unset($job['error'], $job['retry_at']);
        if ($finished) {
            $this->finish($job);

            return true;
        }
        $job['updated'] = time();
        Store::set($name, $job, $this->idShop);

        return false;
    }

    private function work(array &$job, float $deadline): bool
    {
        if (!Files::prepare()) {
            throw new \RuntimeException('Cannot write to ' . Files::dir());
        }
        if (!Files::hasRoom()) {
            throw new \RuntimeException('Under 256 MB of disk space is left, so the build stopped to protect the shop');
        }
        $path = Files::partial($this->idShop, $this->feed['key']);
        clearstatcache(true, $path);
        if ((int) $job['bytes'] > 0 && (!is_file($path) || filesize($path) < (int) $job['bytes'])) {
            // Nothing to resume from: extending the file would put a hole in the middle of it.
            self::start($this->feed, $this->idShop);
            $job = (array) Store::get(self::jobName($this->feed['key']), $this->idShop);
            foreach (['skipped', 'samples', 'notes'] as $list) {
                $job[$list] = (array) ($job[$list] ?? []);
            }
        }
        $primary = $this->feed['mode'] !== Profiles::SUPPLEMENTAL;
        $idLang = (int) $this->feed['id_lang'];
        $source = new Source($this->idShop, $idLang, self::visitorGroup(), !$primary);
        $settings = $this->settings();
        $prices = $primary ? $this->prices() : null;
        $links = $primary ? new Links($idLang, $this->idShop, $this->linkParameters()) : null;
        $brand = \Configuration::get('GEOLENS_SHOP_BRAND', null, null, $this->idShop) ? (string) \Configuration::get('PS_SHOP_NAME', null, null, $this->idShop) : '';

        $file = @fopen($path, 'c+b');
        if (!$file) {
            throw new \RuntimeException('Cannot open the feed file');
        }
        // Whatever the last request wrote after its last checkpoint goes away here.
        ftruncate($file, (int) $job['bytes']);
        fseek($file, (int) $job['bytes']);
        if ((int) $job['bytes'] === 0) {
            $this->write($file, $this->header(), $job);
        }

        $done = false;
        do {
            $batch = $source->batch((int) $job['cursor'], self::BATCH_PRODUCTS, self::BATCH_ITEMS);
            $xml = '';
            foreach ($batch['products'] as $row) {
                $xml .= $this->product($row, $settings, $prices, $links, $brand, $job);
            }
            $this->write($file, $xml, $job);
            $job['cursor'] = $batch['cursor'];
            $done = $batch['done'];
            if (!$done) {
                $job['updated'] = time();
                Store::set(self::jobName($this->feed['key']), $job, $this->idShop);
            }
            Prices::forget();
        } while (!$done && microtime(true) < $deadline && !self::memoryIsTight());

        if ($done) {
            $this->write($file, Rss::close(), $job);
        }
        fclose($file);

        return $done;
    }

    /** @param resource $file */
    private function write($file, string $text, array &$job): void
    {
        if ($text === '') {
            return;
        }
        $written = fwrite($file, $text);
        if ($written !== strlen($text)) {
            throw new \RuntimeException('The disk is full or the feed file cannot be written');
        }
        fflush($file);
        $job['bytes'] = (int) $job['bytes'] + $written;
    }

    private function header(): string
    {
        $shop = new \Shop($this->idShop);
        $base = (\Configuration::get('PS_SSL_ENABLED') ? 'https://' . $shop->domain_ssl : 'http://' . $shop->domain) . $shop->getBaseURI();
        $language = (string) \Language::getIsoById((int) $this->feed['id_lang']);

        return Rss::open(
            (string) \Configuration::get('PS_SHOP_NAME', null, null, $this->idShop),
            $base,
            'Product data for ' . $this->feed['country'] . ' in ' . $language . ', generated by GEO Lens'
        );
    }

    /** @return string the product's items as XML */
    private function product(array $row, array $settings, ?Prices $prices, ?Links $links, string $brand, array &$job): string
    {
        $id = (int) $row['id_product'];
        $job['products'] = (int) $job['products'] + 1;
        try {
            $mode = (string) $settings['mode'];
            $reason = Mapper::skipReason($row, $mode);
            if ($reason !== null) {
                self::skip($job, $reason, $id);

                return '';
            }
            $items = $row['combinations'] ?: [0 => null];
            $variant = count($items) > 1;
            $urls = [];
            $images = [];
            if ($links !== null) {
                foreach ($items as $idCombination => $combination) {
                    $urls[$idCombination] = $links->product($row, (int) $idCombination);
                }
                $urls = Links::distinguish($urls);
                foreach ($row['images'] as $idImage) {
                    $images[(int) $idImage] = $links->image((string) $row['link_rewrite'], (int) $idImage);
                }
                $prices->knowTaxGroup($id, (int) $row['id_tax_rules_group']);
            }
            $xml = '';
            $written = false;
            foreach ($items as $idCombination => $combination) {
                $f = self::facts($row, (int) $idCombination, $combination, $variant, $urls, $images, $brand);
                if ($prices !== null) {
                    $price = $prices->of($id, (int) $idCombination);
                    if ($price === null || $price['current'] <= 0) {
                        self::skip($job, 'no_price', $id);
                        continue;
                    }
                    $f['price'] = $price['current'];
                    $f['regular'] = $price['regular'];
                    $f['sale_from'] = $price['from'];
                    $f['sale_to'] = $price['to'];
                }
                $notes = [];
                $attributes = Mapper::attributes($f, $settings, $notes);
                if ($prices === null && count($attributes) === 1) {
                    // A supplemental row with only an ID says nothing the primary source does not.
                    self::skip($job, 'nothing_to_add', $id);
                    continue;
                }
                $problems = Spec::check($attributes, $mode, (string) $settings['target']);
                if ($problems) {
                    self::skip($job, 'spec', $id);
                    if (count($job['problems']) < self::SAMPLES) {
                        $job['problems'][] = $id . ': ' . $problems[0];
                    }
                    continue;
                }
                foreach ($notes as $note => $count) {
                    $job['notes'][$note] = ($job['notes'][$note] ?? 0) + $count;
                }
                $xml .= Rss::item($attributes);
                $job['items'] = (int) $job['items'] + 1;
                $written = true;
            }

            return $written ? $xml : '';
        } catch (\Throwable $e) {
            // One broken product must not cost the merchant the feed.
            self::skip($job, 'error', $id);
            if (count($job['problems']) < self::SAMPLES) {
                $job['problems'][] = $id . ': ' . $e->getMessage();
            }

            return '';
        }
    }

    /** The facts of one item. Images fall back to the product's. */
    private static function facts(array $row, int $idCombination, ?array $combination, bool $variant, array $urls, array $images, string $shopBrand): array
    {
        $own = $combination['images'] ?? [];
        $list = $own ? array_values(array_intersect_key($images, array_flip($own))) : array_values($images);
        $code = static fn (string $field) => Mapper::code((string) ($combination[$field] ?? ''), (string) ($row[$field] ?? ''), $variant);
        $quantity = $combination === null ? (int) ($row['quantity'] ?? 0) : (int) $combination['quantity'];
        $date = (string) ($combination['available_date'] ?? '');

        return [
            'id_product' => (int) $row['id_product'],
            'id_combination' => $idCombination,
            'name' => (string) $row['name'],
            'description' => (string) ($row['description'] ?? ''),
            'short' => (string) ($row['description_short'] ?? ''),
            'url' => $urls[$idCombination] ?? '',
            'images' => $list,
            'brand' => (string) ($row['brand'] ?? '') ?: $shopBrand,
            'ean13' => $code('ean13'),
            'upc' => $code('upc'),
            'isbn' => $code('isbn'),
            'mpn' => $code('mpn'),
            'condition' => (string) ($row['condition'] ?? ''),
            'for_sale' => !empty($row['available_for_order']),
            'manage_stock' => (bool) \Configuration::get('PS_STOCK_MANAGEMENT'),
            'allow_orders' => \Product::isAvailableWhenOutOfStock((int) ($row['out_of_stock'] ?? 2)),
            'quantity' => $quantity,
            'available_date' => $date !== '' && strpos($date, '0000') !== 0 ? $date : ($row['available_date'] ?? null),
            'options' => $combination['options'] ?? [],
            'is_variant' => $variant,
            'features' => (array) ($row['features'] ?? []),
            'product_type' => (string) ($row['product_type'] ?? ''),
            'weight' => (float) ($row['weight'] ?? 0) + (float) ($combination['weight'] ?? 0),
            'virtual' => !empty($row['is_virtual']),
            'pack' => $row['pack'] ?? null,
        ];
    }

    private static function skip(array &$job, string $reason, int $id): void
    {
        $job['skipped'][$reason] = ($job['skipped'][$reason] ?? 0) + 1;
        if (count($job['samples'][$reason] ?? []) < self::SAMPLES) {
            $job['samples'][$reason][] = $id;
        }
    }

    private function finish(array $job): void
    {
        $key = $this->feed['key'];
        $partial = Files::partial($this->idShop, $key);
        $live = Files::live($this->idShop, $key);
        $last = Store::get(self::lastName($key), $this->idShop);
        // Merchant Center treats a feed as the whole catalogue, so an empty one would take every
        // product down. An import that disabled everything for a moment should not be able to do that.
        if ((int) $job['items'] === 0 && is_file($live)) {
            @unlink($partial);
            $job['error'] = 'no_items';
            $job['retry_at'] = time() + self::RETRY;
            Store::set(self::jobName($key), $job, $this->idShop);

            return;
        }
        if (!@rename($partial, $live)) {
            throw new \RuntimeException('Cannot move the finished feed into place');
        }
        Store::set(self::lastName($key), [
            'finished' => time(),
            'started' => $job['started'],
            'total' => $job['total'],
            'products' => $job['products'],
            'items' => $job['items'],
            'previous_items' => is_array($last) ? (int) $last['items'] : null,
            'bytes' => (int) $job['bytes'],
            'skipped' => (object) $job['skipped'],
            'samples' => (object) $job['samples'],
            'notes' => (object) $job['notes'],
            'problems' => $job['problems'],
            'seconds' => round((float) $job['seconds'], 1),
            'steps' => (int) $job['steps'],
            'peak' => (int) $job['peak'],
            // What this run did about reference prices, for the screen to state.
            'reference' => $this->settings()['reference'],
        ], $this->idShop);
        Store::delete(self::jobName($key), $this->idShop);
    }

    /** @return array<string, mixed> Mapper's settings */
    private function settings(): array
    {
        $iso = (string) $this->feed['country'];
        $currency = new \Currency((int) $this->feed['id_currency'], null, $this->idShop);
        $facts = Store::get('facts', $this->idShop);

        return [
            'mode' => (string) $this->feed['mode'],
            'target' => (string) $this->feed['target'],
            'country' => $iso,
            'currency' => (string) $currency->iso_code,
            'reference' => self::reference($iso, $this->idShop),
            'weight_unit' => (string) \Configuration::get('PS_WEIGHT_UNIT', null, null, $this->idShop),
            'shipping' => (new Facts(is_array($facts) ? $facts : []))->ruleFor($iso),
            'fx' => (float) $currency->conversion_rate ?: 1.0,
            'now' => time(),
        ];
    }

    public static function reference(string $country, int $idShop): bool
    {
        $shopCountry = (string) \Country::getIsoById((int) \Configuration::get('PS_COUNTRY_DEFAULT', null, null, $idShop));

        return Pricing::referenceAllowed((bool) \Configuration::get('GEOLENS_STRIKETHROUGH', null, null, $idShop), $country, $shopCountry);
    }

    private function prices(): Prices
    {
        $iso = (string) $this->feed['country'];
        $currency = new \Currency((int) $this->feed['id_currency'], null, $this->idShop);
        $withTax = Pricing::taxIncluded($iso) && (bool) \Configuration::get('PS_TAX', null, null, $this->idShop);

        return new Prices($this->idShop, (int) \Country::getByIso($iso), (int) $this->feed['id_currency'], self::visitorGroup(), $withTax, (int) $currency->precision);
    }

    /** Opens the page in the feed's currency, since a visitor's first page otherwise shows the shop's. */
    private function linkParameters(): array
    {
        $default = (int) \Configuration::get('PS_CURRENCY_DEFAULT', null, null, $this->idShop);
        $idCurrency = (int) $this->feed['id_currency'];

        return $idCurrency === $default ? [] : ['SubmitCurrency' => 1, 'id_currency' => $idCurrency];
    }

    /** Google reads a page as someone who has not logged in, so that is whose price counts. */
    public static function visitorGroup(): int
    {
        return (int) \Configuration::get('PS_UNIDENTIFIED_GROUP') ?: 1;
    }

    private static function memoryIsTight(): bool
    {
        $limit = (string) ini_get('memory_limit');
        if ($limit === '-1' || $limit === '') {
            return false;
        }
        $units = ['k' => 1024, 'm' => 1048576, 'g' => 1073741824];
        $bytes = (int) $limit * ($units[strtolower(substr($limit, -1))] ?? 1);

        return memory_get_usage(true) > $bytes * 0.7;
    }
}
