<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Admin;

use Iamshoaibktk\Geolens\Bots\Registry;
use Iamshoaibktk\Geolens\Feed\Builder;
use Iamshoaibktk\Geolens\Feed\Files;
use Iamshoaibktk\Geolens\Feed\Neighbours;
use Iamshoaibktk\Geolens\Feed\Pricing;
use Iamshoaibktk\Geolens\Feed\Profiles;
use Iamshoaibktk\Geolens\Feed\Spec;
use Iamshoaibktk\Geolens\Indexing\IndexNow;
use Iamshoaibktk\Geolens\Llms\Builder as LlmsBuilder;
use Iamshoaibktk\Geolens\Robots\Policy;
use Iamshoaibktk\Geolens\Schema\Facts;
use Iamshoaibktk\Geolens\Schema\PageWriter;
use Iamshoaibktk\Geolens\Support\Store;
use Iamshoaibktk\Geolens\Traffic\Classifier;
use Iamshoaibktk\Geolens\Traffic\Report;

/** Prepares each tab's data and renders its template. Each tab loads on its own, so one slow audit never holds up the rest. */
final class Panels
{
    /** Validated with the palette checker for adjacent segments on a white surface. */
    private const COLORS = [Registry::SEARCH => '#2a78d6', Registry::USER => '#eb6834', Registry::TRAIN => '#1baf7a'];
    private const SINGLE = '#2a78d6';

    private \Geolens $module;
    private \Context $context;
    private int $idShop;

    public function __construct(\Geolens $module, \Context $context)
    {
        $this->module = $module;
        $this->context = $context;
        $this->idShop = (int) $context->shop->id;
    }

    public function render(string $tab, int $days): string
    {
        $data = match ($tab) {
            'overview' => $this->overview($days) + $this->traffic($days),
            'crawlers' => $this->crawlers($days),
            'data' => $this->data(),
            'feed' => $this->feed(),
            'settings' => $this->settings(),
            'channels' => $this->channels(),
            default => null,
        };
        if ($data === null) {
            return '';
        }
        $this->context->smarty->assign($data + ['gl_days' => $days]);

        return $this->module->display($this->module->getLocalPath() . 'geolens.php', 'views/templates/admin/panel-' . $tab . '.tpl');
    }

    private function money(float $amount): string
    {
        return \Tools::getContextLocale($this->context)->formatPrice($amount, (string) \Currency::getDefaultCurrency()->iso_code);
    }

    private function t(string $text, array $params = []): string
    {
        return $this->module->t($text, $params);
    }

    private function overview(int $days): array
    {
        $report = new Report($this->idShop, $days);
        $totals = $report->totals();
        $visits = [];
        foreach ($report->visitsByDay() as $day => $n) {
            $visits[$day] = ['visits' => $n];
        }

        $locale = \Tools::getContextLocale($this->context);

        return [
            'gl_totals' => array_map(static fn ($n) => is_int($n) ? $locale->formatNumber($n) : $n, $totals),
            'gl_value' => $this->money($totals['value']),
            'gl_order_share' => $totals['all_orders'] > 0 ? round($totals['orders'] / $totals['all_orders'] * 100, 1) : 0,
            'gl_chart_visits' => Chart::columns($visits, ['visits' => ['label' => $this->t('Visits'), 'color' => self::SINGLE]], $this->t('Visits from AI assistants per day')),
            'gl_chart_crawls' => Chart::columns($report->crawlsByDay(), $this->purposeSeries(), $this->t('AI crawler requests per day')),
            'gl_legend' => $this->purposeSeries(),
            'gl_table_visits' => $report->visitsByDay(),
            'gl_table_crawls' => $report->crawlsByDay(),
            'gl_checks' => (new Checks($this->module, $this->context))->run(),
        ];
    }

    private function purposeSeries(): array
    {
        return [
            Registry::SEARCH => ['label' => $this->t('Answers and search'), 'color' => self::COLORS[Registry::SEARCH]],
            Registry::USER => ['label' => $this->t('Read for a person'), 'color' => self::COLORS[Registry::USER]],
            Registry::TRAIN => ['label' => $this->t('Training'), 'color' => self::COLORS[Registry::TRAIN]],
        ];
    }

    private function traffic(int $days): array
    {
        $report = new Report($this->idShop, $days);
        $engines = [];
        foreach ($report->engines() as $row) {
            $row['label'] = Classifier::label($row['engine']);
            $row['value_label'] = $this->money($row['value']);
            $row['conversion'] = $row['visits'] > 0 ? round($row['orders'] / $row['visits'] * 100, 1) : null;
            $engines[] = $row;
        }
        $landing = [];
        foreach ($report->landingPages() as $row) {
            $row['label'] = Classifier::label($row['engine']);
            $landing[] = $row;
        }
        $orders = [];
        foreach ($report->recentOrders() as $row) {
            $row['label'] = Classifier::label($row['engine']);
            $row['value_label'] = $this->money((float) $row['value']);
            $row['date'] = \Tools::displayDate((string) $row['date_add']);
            $row['url'] = $this->context->link->getAdminLink('AdminOrders', true, ['route' => 'admin_orders_view', 'orderId' => (int) $row['id_order']], ['id_order' => (int) $row['id_order'], 'vieworder' => 1]);
            $orders[] = $row;
        }

        return [
            'gl_engines' => $engines,
            'gl_landing' => $landing,
            'gl_orders' => $orders,
        ];
    }

    private function settings(): array
    {
        return $this->freshness() + $this->llms() + [
            'gl_window' => (int) \Configuration::getGlobalValue('GEOLENS_ATTR_WINDOW'),
            'gl_log_visits' => (bool) \Configuration::getGlobalValue('GEOLENS_LOG_VISITS'),
            'gl_guard' => (bool) \Configuration::getGlobalValue('GEOLENS_GUARD'),
            'gl_log_bots' => (bool) \Configuration::getGlobalValue('GEOLENS_LOG_BOTS'),
            'gl_retention' => (int) \Configuration::getGlobalValue('GEOLENS_RETENTION'),
        ];
    }

    private function crawlers(int $days): array
    {
        $report = new Report($this->idShop, $days);
        $stats = $report->bots();
        $blocked = Policy::blocked();
        $rows = [];
        foreach (Registry::BOTS as $token => $bot) {
            $s = $stats[$token] ?? null;
            $hits = (int) ($s['hits'] ?? 0);
            $rows[$bot['purpose']][] = [
                'token' => $token,
                'op' => $bot['op'],
                'official' => $bot['official'],
                'obeys' => $bot['obeys'],
                'control' => $bot['ua'] === null,
                'testable' => $bot['ua'] !== null,
                'blocked' => in_array($token, $blocked, true),
                'hits' => $hits,
                'verified' => (int) ($s['verified'] ?? 0),
                'spoofed' => (int) ($s['spoofed'] ?? 0),
                'refused' => (int) ($s['refused'] ?? 0),
                'missing' => (int) ($s['missing'] ?? 0),
                'avg_ms' => $hits > 0 ? (int) round((int) $s['ms'] / $hits) : 0,
                'last' => isset($s['last']) ? \Tools::displayDate((string) $s['last']) : '',
                'can_verify' => $bot['ip'] !== null,
            ];
        }
        $access = (array) Store::get('access_results', $this->idShop);
        $context = (array) Store::get('access_context', $this->idShop);

        return [
            'gl_bots' => $rows,
            'gl_preset' => (string) \Configuration::getGlobalValue('GEOLENS_PRESET'),
            'gl_presets_json' => (string) json_encode(array_combine(Registry::PRESETS, array_map([Registry::class, 'presetBlocks'], Registry::PRESETS))),
            'gl_training' => (bool) \Configuration::getGlobalValue('GEOLENS_TRAINING'),
            'gl_signals' => (bool) \Configuration::getGlobalValue('GEOLENS_SIGNALS'),
            'gl_block' => Policy::block(),
            'gl_can_undo' => Policy::canUndo(),
            'gl_writable' => Policy::isWritable(),
            'gl_at_root' => Policy::isAtDomainRoot(),
            'gl_multistore' => \Shop::isFeatureActive(),
            'gl_bypass' => implode(', ', Policy::bypassing()),
            'gl_access' => $access,
            'gl_access_context' => $context,
            'gl_read_for_people' => $report->pagesReadForPeople(),
            'gl_missing' => $report->missingPages(),
            'gl_demand' => $report->demand(),
            'gl_lists_age' => Store::age('ip_lists_at'),
        ];
    }

    private function data(): array
    {
        $idLang = (int) $this->context->language->id;
        $facts = Store::get('facts', $this->idShop);
        $defaultCountry = (string) \Country::getIsoById((int) \Configuration::get('PS_COUNTRY_DEFAULT'));
        $freeOver = (float) \Configuration::get('PS_SHIPPING_FREE_PRICE');
        $defaults = [
            'returns' => [
                'mode' => '',
                'days' => (int) \Configuration::get('PS_ORDER_RETURN_NB_DAYS') ?: 14,
                'fees' => 'customer',
                'fee' => 0,
                'countries' => [$defaultCountry],
            ],
            'shipping' => [['countries' => [$defaultCountry], 'rate' => 0, 'free_over' => $freeOver > 0 ? $freeOver : null, 'handling_min' => 0, 'handling_max' => 1, 'transit_min' => 1, 'transit_max' => 3]],
        ];
        $countries = [];
        foreach (\Country::getCountries($idLang, true) as $country) {
            $countries[] = ['iso' => $country['iso_code'], 'name' => $country['name']];
        }

        return [
            'gl_facts' => is_array($facts) ? $facts + $defaults : $defaults,
            'gl_facts_saved' => is_array($facts),
            'gl_countries' => $countries,
            'gl_currency' => (string) \Currency::getDefaultCurrency()->sign,
            'gl_schema' => (bool) \Configuration::get('GEOLENS_SCHEMA'),
            'gl_variants' => (bool) \Configuration::get('GEOLENS_VARIANTS'),
            'gl_show_facts' => (bool) \Configuration::get('GEOLENS_SHOW_FACTS'),
            'gl_shop_brand' => (bool) \Configuration::get('GEOLENS_SHOP_BRAND'),
            'gl_strike' => (bool) \Configuration::get('GEOLENS_STRIKETHROUGH'),
            'gl_in_eu' => PageWriter::isEuropeanUnion($defaultCountry),
            'gl_languages' => \Language::getLanguages(true, $this->idShop),
            'gl_lang' => $idLang,
            'gl_page_results' => (array) Store::get('page_results', $this->idShop),
        ];
    }

    private function feed(): array
    {
        $idLang = (int) $this->context->language->id;
        $countries = [];
        foreach (\Country::getCountries($idLang, true) as $country) {
            $countries[] = ['iso' => $country['iso_code'], 'name' => $country['name']];
        }
        $currencies = [];
        foreach (Profiles::currencies($this->idShop) as $id => $iso) {
            $currencies[] = ['id' => $id, 'iso' => $iso];
        }

        return [
            'gl_feeds' => $this->feedRows(),
            'gl_feed_countries' => $countries,
            'gl_feed_languages' => \Language::getLanguages(true, $this->idShop),
            'gl_feed_currencies' => $currencies,
            'gl_feed_country' => (string) \Country::getIsoById((int) \Configuration::get('PS_COUNTRY_DEFAULT')),
            'gl_feed_lang' => (int) \Configuration::get('PS_LANG_DEFAULT'),
            'gl_feed_currency' => (int) \Configuration::get('PS_CURRENCY_DEFAULT'),
            'gl_mwg' => Neighbours::marketingWithGoogle(),
            'gl_feed_shop_notes' => $this->feedShopNotes(),
            'gl_spec_checked' => \Tools::displayDate(Spec::CHECKED),
            'gl_spec_url' => Spec::SOURCE,
        ];
    }

    /** The feeds alone, so a build in progress can update them without touching the form below. */
    public function feedList(): string
    {
        $this->context->smarty->assign('gl_feeds', $this->feedRows());

        return $this->module->display($this->module->getLocalPath() . 'geolens.php', 'views/templates/admin/feed-list.tpl');
    }

    /** @return array<int, array<string, mixed>> */
    private function feedRows(): array
    {
        $rows = [];
        $locale = \Tools::getContextLocale($this->context);
        $number = static fn ($n) => $locale->formatNumber((int) $n);
        foreach (Profiles::all($this->idShop) as $feed) {
            $key = $feed['key'];
            $job = Store::get(Builder::jobName($key), $this->idShop);
            $last = Store::get(Builder::lastName($key), $this->idShop);
            $file = Files::live($this->idShop, $key);
            $status = 'never';
            if (is_array($job)) {
                $status = empty($job['error']) ? 'building' : 'failed';
            } elseif (is_array($last)) {
                $status = 'ready';
            }
            $language = new \Language((int) $feed['id_lang']);
            $currency = new \Currency((int) $feed['id_currency'], null, $this->idShop);
            $fetched = Store::get('feed_fetch:' . $key, $this->idShop);
            $fetchAge = Store::age('feed_fetch:' . $key, $this->idShop);
            $row = [
                'key' => $key,
                'country' => $feed['country'],
                'country_name' => (string) \Country::getNameById((int) $this->context->language->id, (int) \Country::getByIso((string) $feed['country'])),
                'language' => (string) $language->name,
                'iso' => (string) $language->iso_code,
                'currency' => (string) $currency->iso_code,
                'mode' => $feed['mode'],
                'target' => $feed['target'],
                'url' => Profiles::url($feed, $this->context->shop, (bool) \Configuration::get('PS_REWRITING_SETTINGS')),
                'status' => $status,
                'has_file' => is_file($file),
                'percent' => 0,
                'done' => '0',
                'total' => '0',
                'stuck' => false,
                'error' => '',
                'items' => null,
                'size' => '',
                'large_for_microsoft' => false,
                'built' => '',
                'seconds' => null,
                'dropped' => false,
                'left_out' => [],
                'notes' => [],
                'problems' => [],
                'fetched' => $fetchAge === null ? '' : \Tools::displayDate(date('Y-m-d H:i:s', time() - $fetchAge), true),
                'fetched_by' => (string) ($fetched['by'] ?? ''),
                'advice' => $this->feedAdvice($feed),
            ];
            if (is_array($job)) {
                $total = max(1, (int) $job['total']);
                $row['percent'] = (int) min(99, floor((int) $job['products'] * 100 / $total));
                $row['done'] = $number($job['products']);
                $row['total'] = $number($job['total']);
                // A chain of requests that stopped: most often a host refusing requests to itself.
                $row['stuck'] = empty($job['error']) && time() - (int) $job['updated'] > 900;
                $row['error'] = (string) ($job['error'] ?? '');
            }
            if (is_array($last)) {
                $row['items'] = $number($last['items']);
                $row['size'] = self::size((int) $last['bytes']);
                $row['large_for_microsoft'] = (int) $last['bytes'] > 1000000000;
                $row['built'] = \Tools::displayDate(date('Y-m-d H:i:s', (int) $last['finished']), true);
                $row['seconds'] = $number(ceil((float) $last['seconds']));
                $row['dropped'] = !empty($last['previous_items']) && (int) $last['items'] < (int) $last['previous_items'] * 0.5;
                $row['left_out'] = $this->feedLeftOut((array) ($last['skipped'] ?? []), (array) ($last['samples'] ?? []), $number);
                $row['notes'] = $this->feedNotes((array) ($last['notes'] ?? []), $number);
                $row['problems'] = (array) ($last['problems'] ?? []);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array<int, array{text: string, count: string, examples: array<int, array{id: int, url: string}>}> */
    private function feedLeftOut(array $skipped, array $samples, callable $number): array
    {
        $labels = [
            'no_name' => $this->t('No name in this language'),
            'not_public' => $this->t('Only in categories visitors cannot open'),
            'price_hidden' => $this->t('The price is hidden on the page'),
            'no_image' => $this->t('No image'),
            'no_price' => $this->t('No price'),
            'nothing_to_add' => $this->t('No features or combinations to add'),
            'spec' => $this->t('A field does not meet Google\'s rules'),
            'error' => $this->t('Could not be read'),
        ];
        $rows = [];
        foreach ($skipped as $reason => $count) {
            $examples = [];
            foreach ((array) ($samples[$reason] ?? []) as $id) {
                $examples[] = ['id' => (int) $id, 'url' => $this->context->link->getAdminLink('AdminProducts', true, ['route' => 'admin_products_edit', 'productId' => (int) $id], ['id_product' => (int) $id])];
            }
            $rows[] = ['text' => $labels[$reason] ?? (string) $reason, 'count' => $number($count), 'examples' => $examples];
        }

        return $rows;
    }

    /** @return array<int, array{text: string, count: string}> */
    private function feedNotes(array $notes, callable $number): array
    {
        $labels = [
            'no_description' => $this->t('No description, so the name stands in for it'),
            'no_identifier' => $this->t('No GTIN, and no brand with a part number, so the item says it has no identifier. If the maker assigned one, add it.'),
            'gtin_invalid' => $this->t('A GTIN whose check digit fails, so it was left out'),
            'gtin_restricted' => $this->t('A GTIN from a range kept for in-store labels or coupons, so it was left out'),
            'detail_too_long' => $this->t('Features too long for a product detail, so they were left out'),
            'option_untranslated' => $this->t('A size, color or similar option has no name in this language, so the default language\'s is used'),
        ];
        $rows = [];
        foreach ($notes as $key => $count) {
            if (isset($labels[$key])) {
                $rows[] = ['text' => $labels[$key], 'count' => $number($count)];
            }
        }

        return $rows;
    }

    /** @return array<int, array{level: string, text: string, tab?: string}> what the merchant should know before submitting this feed */
    private function feedAdvice(array $feed): array
    {
        if ($feed['mode'] === Profiles::SUPPLEMENTAL) {
            return [['level' => 'info', 'text' => $this->t('Holds IDs, combinations and product details only, with no prices, stock or titles, so it cannot contradict your primary source.')]];
        }
        $iso = (string) $feed['country'];
        $name = (string) \Country::getNameById((int) $this->context->language->id, (int) \Country::getByIso($iso));
        $default = (string) \Country::getIsoById((int) \Configuration::get('PS_COUNTRY_DEFAULT'));
        $withTax = Pricing::taxIncluded($iso);
        $advice = [];

        $advice[] = ['level' => 'info', 'text' => $withTax
            ? $this->t('Prices include tax for %country%, as Google requires there.', ['%country%' => $name])
            : $this->t('Prices leave out tax, as Google and Microsoft require in %country%.', ['%country%' => $name])];
        $pagesWithoutTax = (int) \Group::getPriceDisplayMethod(Builder::visitorGroup()) === 1;
        if (\Configuration::get('PS_TAX') && $withTax && $pagesWithoutTax) {
            $advice[] = ['level' => 'warning', 'text' => $this->t('Your pages show visitors prices without tax, but this feed must include it, so the two will not match. Google compares them. Change this in Customer settings, Groups.')];
        } elseif (\Configuration::get('PS_TAX') && !$withTax && !$pagesWithoutTax) {
            $advice[] = ['level' => 'warning', 'text' => $this->t('Your pages show visitors prices with tax, but this feed must leave it out, so the two will not match. Google compares them. Change this in Customer settings, Groups.')];
        }
        if ($iso !== $default) {
            $advice[] = ['level' => 'warning', 'text' => $this->t('PrestaShop works out a first-time visitor\'s tax from their browser language and falls back on %default%. Google may therefore see a different tax on the page from the one in this feed. Check a few products in Merchant Center diagnostics.', ['%default%' => $default])];
        }

        $setting = (bool) \Configuration::get('GEOLENS_STRIKETHROUGH');
        if (!$setting) {
            $advice[] = ['level' => 'info', 'text' => $this->t('Crossed-out prices are off in Product data, so each item states only what it costs today.'), 'tab' => 'data'];
        } elseif (!Builder::reference($iso, $this->idShop)) {
            $advice[] = ['level' => 'info', 'text' => $this->t('No crossed-out prices in this feed, so each item states only what it costs today. In the EU a reduction must be calculated from the lowest price of the previous 30 days, and the module cannot check that.')];
        } elseif (PageWriter::isEuropeanUnion($iso)) {
            $advice[] = ['level' => 'warning', 'text' => $this->t('You turned on crossed-out prices. In the EU each reduction must be calculated from the lowest price of the previous 30 days. The module cannot check that, so this is your own statement.'), 'tab' => 'data'];
        } else {
            $advice[] = ['level' => 'info', 'text' => $this->t('A discounted product states its regular price and its sale price, as set in Product data.'), 'tab' => 'data'];
        }

        $facts = Store::get('facts', $this->idShop);
        if ((new Facts(is_array($facts) ? $facts : []))->ruleFor($iso) === null) {
            $advice[] = ['level' => 'warning', 'text' => $this->t('No delivery rule covers %country%, so items carry no shipping. Add one in Product data, or set delivery in Merchant Center. Many countries require it.', ['%country%' => $name]), 'tab' => 'data'];
        }
        if ($feed['target'] === Profiles::MICROSOFT) {
            $advice[] = ['level' => 'info', 'text' => $this->t('Uses Microsoft\'s spelling of availability. Microsoft lists no value for backorder, so those products say out of stock.')];
        }

        return $advice;
    }

    /** @return array<int, array{level: string, text: string, tab?: string}> what affects every feed of this shop */
    private function feedShopNotes(): array
    {
        $notes = [];
        if (\Configuration::get('PS_CATALOG_MODE')) {
            $notes[] = ['level' => 'warning', 'text' => $this->t('Your shop is in catalogue mode, so visitors cannot order. Merchant Center needs a page where the product can be bought.')];
        }
        if (!(new \Group(Builder::visitorGroup()))->show_prices) {
            $notes[] = ['level' => 'warning', 'text' => $this->t('Visitors who have not logged in cannot see prices. Google reads your pages the same way, so it cannot confirm any price in the feed.')];
        }
        $audit = Store::get('catalog:' . (int) $this->context->language->id, $this->idShop);
        $sample = is_array($audit) ? ($audit['image_sample'] ?? null) : null;
        if (is_array($sample) && (int) $sample['small'] > 0) {
            $notes[] = ['level' => 'warning', 'text' => $this->t('%small% of %checked% cover photos sampled are under 500 x 500 pixels. Merchant Center enforces that minimum from 31 January 2027.', ['%small%' => (string) $sample['small'], '%checked%' => (string) $sample['checked']]), 'tab' => 'data'];
        }

        return $notes;
    }

    private static function size(int $bytes): string
    {
        if ($bytes >= 1000000000) {
            return round($bytes / 1000000000, 1) . ' GB';
        }
        if ($bytes >= 1000000) {
            return round($bytes / 1000000, 1) . ' MB';
        }

        return max(1, (int) round($bytes / 1000)) . ' KB';
    }

    private function freshness(): array
    {
        $log = \Db::getInstance()->executeS('SELECT `date_add`, `urls`, `code` FROM `' . _DB_PREFIX_ . 'geolens_indexnow_log` WHERE `id_shop` = ' . $this->idShop . ' ORDER BY `id` DESC LIMIT 8');
        foreach ($log as &$row) {
            $row['date'] = \Tools::displayDate((string) $row['date_add'], true);
        }
        $sitemap = _PS_ROOT_DIR_ . '/' . $this->idShop . '_index_sitemap.xml';
        $total = (int) \Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_shop` WHERE `id_shop` = ' . $this->idShop . ' AND `active` = 1');
        // A big block of products sharing one date means an import or a reorder, not real edits. Search
        // engines learn to ignore lastmod when it behaves like this.
        $sameDay = (int) \Db::getInstance()->getValue('SELECT COUNT(*) AS n FROM `' . _DB_PREFIX_ . 'product_shop` WHERE `id_shop` = ' . $this->idShop . ' AND `active` = 1 GROUP BY DATE(`date_upd`) ORDER BY n DESC');

        return [
            'gl_indexnow' => (bool) \Configuration::get('GEOLENS_INDEXNOW'),
            'gl_pending' => IndexNow::pending($this->idShop),
            'gl_backfill' => IndexNow::backfillAt($this->idShop),
            'gl_key' => IndexNow::key(),
            'gl_key_url' => $this->shopUrl() . IndexNow::key() . '.txt',
            'gl_log' => $log,
            'gl_sitemap_exists' => is_file($sitemap),
            'gl_sitemap_url' => $this->shopUrl() . $this->idShop . '_index_sitemap.xml',
            'gl_sitemap_age' => is_file($sitemap) ? (int) floor((time() - (int) filemtime($sitemap)) / 86400) : null,
            'gl_bulk_share' => $total > 0 ? (int) round($sameDay / $total * 100) : 0,
            'gl_total' => $total,
            'gl_cron' => $this->cronUrl(),
        ];
    }

    private function llms(): array
    {
        $settings = Store::get('llms', $this->idShop);
        $languages = [];
        foreach (\Language::getLanguages(true, $this->idShop) as $language) {
            $languages[] = [
                'id' => (int) $language['id_lang'],
                'name' => $language['name'],
                'iso' => $language['iso_code'],
                'summary' => (string) ($settings['summary'][(int) $language['id_lang']] ?? ''),
            ];
        }
        $idLang = (int) \Configuration::get('PS_LANG_DEFAULT');

        return [
            'gl_llms' => (bool) \Configuration::get('GEOLENS_LLMS'),
            'gl_llms_url' => $this->shopUrl() . 'llms.txt',
            'gl_agents_url' => $this->shopUrl() . 'agents.md',
            'gl_llms_languages' => $languages,
            'gl_preview' => LlmsBuilder::build($idLang, $this->idShop, true),
        ];
    }

    private function channels(): array
    {
        $usActive = (bool) \Db::getInstance()->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . 'country` WHERE `iso_code` = "US" AND `active` = 1');
        $rows = [];
        foreach (Channels::LIST as $key => $channel) {
            $rows[] = ['key' => $key, 'open' => $channel['markets'] === 'all' || $usActive] + $channel;
        }

        return [
            'gl_channels' => $rows,
            'gl_checked' => \Tools::displayDate(Channels::CHECKED),
            'gl_prompts' => Channels::prompts($this->context, $this->module),
        ];
    }

    private function shopUrl(): string
    {
        $shop = $this->context->shop;

        return (\Configuration::get('PS_SSL_ENABLED') ? 'https://' . $shop->domain_ssl : 'http://' . $shop->domain) . $shop->getBaseURI();
    }

    private function cronUrl(): string
    {
        return $this->shopUrl() . 'index.php?geolens_cron=' . \Configuration::getGlobalValue('GEOLENS_TOKEN');
    }
}
