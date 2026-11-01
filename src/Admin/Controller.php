<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Admin;

use Iamshoaibktk\Geolens\Bots\AccessCheck;
use Iamshoaibktk\Geolens\Bots\Registry;
use Iamshoaibktk\Geolens\Catalog\Audit;
use Iamshoaibktk\Geolens\Feed\Builder;
use Iamshoaibktk\Geolens\Feed\Files;
use Iamshoaibktk\Geolens\Feed\Profiles;
use Iamshoaibktk\Geolens\Feed\Scheduler;
use Iamshoaibktk\Geolens\Indexing\IndexNow;
use Iamshoaibktk\Geolens\Robots\Block;
use Iamshoaibktk\Geolens\Robots\Policy;
use Iamshoaibktk\Geolens\Schema\Pages;
use Iamshoaibktk\Geolens\Support\Http;
use Iamshoaibktk\Geolens\Support\Maintenance;
use Iamshoaibktk\Geolens\Support\Store;

/**
 * The back office. The page is a shell and every tab and action comes through handle() as JSON. The
 * AdminModules token in the URL has already been checked by PrestaShop, along with the employee's
 * right to configure this module. Anything that changes something must also arrive as a POST.
 */
final class Controller
{
    private \Geolens $module;
    private \Context $context;
    private int $idShop;

    public function __construct(\Geolens $module)
    {
        $this->module = $module;
        $this->context = \Context::getContext();
        $this->idShop = (int) $this->context->shop->id;
    }

    public function handle(): string
    {
        $action = (string) \Tools::getValue('gl');
        if ($action !== '') {
            $this->respond($action);
        }

        $path = $this->module->getPathUri();
        $dir = $this->module->getLocalPath();
        $iso = (string) $this->context->language->iso_code;
        $this->context->smarty->assign([
            'author_img' => $path . 'views/img/author.jpg',
            'gl_css' => $path . 'views/css/admin.css?v=' . filemtime($dir . 'views/css/admin.css'),
            'gl_js' => $path . 'views/js/admin.js?v=' . filemtime($dir . 'views/js/admin.js'),
            'gl_logo' => $path . 'views/img/logo-512.png',
            'gl_url' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->module->name]),
            'gl_docs' => $path . 'docs/geolens-' . (is_file($dir . 'docs/geolens-' . $iso . '.pdf') ? $iso : 'en') . '.pdf',
        ]);
        $file = $dir . 'geolens.php';

        return $this->module->display($file, 'views/templates/admin/author.tpl') . $this->module->display($file, 'views/templates/admin/config.tpl');
    }

    private function respond(string $action): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $out = $this->route($action);
        } catch (\Throwable $e) {
            http_response_code(500);
            \PrestaShopLogger::addLog('GEO Lens: ' . $e->getMessage(), 3, null, 'Module', (int) $this->module->id);
            $out = ['error' => $this->t('Something went wrong. The details are in the PrestaShop log.')];
        }
        echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    /** @return array<string, mixed> */
    private function route(string $action): array
    {
        $read = [
            'panel' => fn () => ['html' => $this->panel((string) \Tools::getValue('tab'))],
            'catalog' => fn () => $this->catalog(),
            'catalog_missing' => fn () => $this->catalogMissing(),
        ];
        if (isset($read[$action])) {
            return $read[$action]();
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);

            return ['error' => 'POST required'];
        }
        $write = [
            // The chain of background requests takes over from here, so the page never waits on a long build.
            'tick' => fn () => Maintenance::run(false, false, 5),
            'preview_policy' => fn () => $this->previewPolicy(),
            'save_policy' => fn () => $this->savePolicy(),
            'undo_policy' => fn () => $this->undoPolicy(),
            'access_prepare' => fn () => $this->accessPrepare(),
            'access_bot' => fn () => $this->accessBot(),
            'save_measure' => fn () => $this->saveMeasure(),
            'save_attribution' => fn () => $this->saveAttribution(),
            'save_guard' => fn () => $this->saveGuard(),
            'save_facts' => fn () => $this->saveFacts(),
            'save_markup' => fn () => $this->saveMarkup(),
            'page_samples' => fn () => ['samples' => Pages::samples($this->context)],
            'page_check' => fn () => $this->pageCheck(),
            'save_indexnow' => fn () => $this->saveIndexNow(),
            'indexnow_send' => fn () => $this->indexNowSend(),
            'indexnow_backfill' => fn () => $this->indexNowBackfill(),
            'save_llms' => fn () => $this->saveLlms(),
            'save_feed' => fn () => $this->saveFeed(),
            'feed_step' => fn () => $this->feedStep(),
            'feed_rebuild' => fn () => $this->feedRebuild(),
            'feed_test' => fn () => $this->feedTest(),
            'feed_rotate' => fn () => $this->feedRotate(),
            'feed_remove' => fn () => $this->feedRemove(),
        ];
        if (!isset($write[$action])) {
            http_response_code(404);

            return ['error' => 'Unknown action'];
        }

        return $write[$action]();
    }

    private function t(string $text, array $params = []): string
    {
        return $this->module->t($text, $params);
    }

    private function panel(string $tab): string
    {
        return (new Panels($this->module, $this->context))->render($tab, $this->days());
    }

    private function days(): int
    {
        $days = (int) \Tools::getValue('days', 30);

        return in_array($days, [7, 30, 90], true) ? $days : 30;
    }

    /** @return array{html: string, flash: array{type: string, text: string}} */
    private function done(string $tab, string $text, string $type = 'success'): array
    {
        return ['html' => $this->panel($tab), 'flash' => ['type' => $type, 'text' => $text]];
    }

    /** @return string[] */
    private function postedBlocked(): array
    {
        $blocked = \Tools::getValue('blocked');

        return is_array($blocked) ? array_map('strval', $blocked) : [];
    }

    private function previewPolicy(): array
    {
        $blocked = $this->postedBlocked();
        $block = Block::build($blocked, (bool) \Tools::getValue('training'), (bool) \Tools::getValue('signals'));

        return ['block' => $block, 'preset' => $this->matchPreset($blocked)];
    }

    /** @param string[] $blocked */
    private function matchPreset(array $blocked): string
    {
        sort($blocked);
        foreach (Registry::PRESETS as $preset) {
            $blocks = Registry::presetBlocks($preset);
            sort($blocks);
            if ($blocks === $blocked) {
                return $preset;
            }
        }

        return 'custom';
    }

    private function savePolicy(): array
    {
        $blocked = $this->postedBlocked();
        $written = Policy::save($blocked, (bool) \Tools::getValue('training'), (bool) \Tools::getValue('signals'), $this->matchPreset($blocked));
        if (!$written) {
            return $this->done('crawlers', $this->t('Your choice is saved, but robots.txt cannot be written. Add the lines below to it by hand.'), 'warning');
        }

        return $this->done('crawlers', $this->t('robots.txt updated. OpenAI says changes reach its search in about 24 hours; others may take longer.'));
    }

    private function undoPolicy(): array
    {
        if (!Policy::undo()) {
            return $this->done('crawlers', $this->t('There is nothing to undo.'), 'warning');
        }

        return $this->done('crawlers', $this->t('robots.txt and your crawler choices are back to how they were.'));
    }

    /** @return array{bots: string[], cdn: string, differs: bool, status: int} */
    private function accessPrepare(): array
    {
        $prepared = AccessCheck::prepare($this->context);
        $bots = [];
        foreach (Registry::BOTS as $token => $bot) {
            $bots[] = $token;
        }

        return ['bots' => $bots, 'cdn' => $prepared['cdn'], 'differs' => $prepared['differs'], 'status' => $prepared['status']];
    }

    private function accessBot(): array
    {
        $token = (string) \Tools::getValue('bot');
        if (!isset(Registry::BOTS[$token])) {
            return ['error' => 'Unknown bot'];
        }
        $result = AccessCheck::bot($token, $this->idShop);
        $this->context->smarty->assign('gl_r', $result);
        $html = $this->module->display($this->module->getLocalPath() . 'geolens.php', 'views/templates/admin/access-result.tpl');

        return ['token' => $token, 'html' => $html, 'problem' => !$result['expected']];
    }

    private function saveMeasure(): array
    {
        \Configuration::updateGlobalValue('GEOLENS_LOG_BOTS', (int) (bool) \Tools::getValue('log_bots'));
        $retention = (int) \Tools::getValue('retention', 90);
        \Configuration::updateGlobalValue('GEOLENS_RETENTION', in_array($retention, [30, 90, 180, 365], true) ? $retention : 90);

        return $this->done('settings', $this->t('Settings updated.'));
    }

    private function saveAttribution(): array
    {
        \Configuration::updateGlobalValue('GEOLENS_LOG_VISITS', (int) (bool) \Tools::getValue('log_visits'));
        $window = (int) \Tools::getValue('window', 7200);
        \Configuration::updateGlobalValue('GEOLENS_ATTR_WINDOW', in_array($window, [7200, 2592000], true) ? $window : 7200);

        return $this->done('settings', $this->t('Settings updated.'));
    }

    private function saveGuard(): array
    {
        \Configuration::updateGlobalValue('GEOLENS_GUARD', (int) (bool) \Tools::getValue('guard'));

        return $this->done('settings', $this->t('Settings updated.'));
    }

    /** @return array<string, mixed> */
    private function saveFacts(): array
    {
        $known = array_column(\Country::getCountries((int) $this->context->language->id, true), 'iso_code');
        $clean = static fn ($list) => array_values(array_intersect($known, array_map('strval', is_array($list) ? $list : [])));
        $facts = ['returns' => [], 'shipping' => []];

        $mode = (string) \Tools::getValue('returns_mode');
        if (in_array($mode, ['finite', 'unlimited', 'none'], true)) {
            $days = (int) \Tools::getValue('returns_days');
            $countries = $clean(\Tools::getValue('returns_countries'));
            if (!$countries) {
                return $this->factsError($this->t('Pick at least one country for your returns policy.'));
            }
            if ($mode === 'finite' && $days < 1) {
                return $this->factsError($this->t('Enter how many days customers have to return a product.'));
            }
            $fees = (string) \Tools::getValue('returns_fees');
            $facts['returns'] = [
                'mode' => $mode,
                'days' => $days,
                'fees' => in_array($fees, ['free', 'customer', 'fixed'], true) ? $fees : 'customer',
                'fee' => max(0.0, (float) \Tools::getValue('returns_fee')),
                'countries' => $countries,
                'origin' => (string) \Country::getIsoById((int) \Configuration::get('PS_COUNTRY_DEFAULT')),
            ];
        }

        $rules = \Tools::getValue('rule');
        foreach (is_array($rules) ? $rules : [] as $rule) {
            $countries = $clean($rule['countries'] ?? []);
            if (!$countries) {
                return $this->factsError($this->t('Pick at least one country for each delivery rule, or remove the rule.'));
            }
            $values = array_map(static fn ($k) => max(0, (int) ($rule[$k] ?? 0)), ['handling_min', 'handling_max', 'transit_min', 'transit_max']);
            if ($values[0] > $values[1] || $values[2] > $values[3]) {
                return $this->factsError($this->t('A delivery time cannot start after it ends.'));
            }
            $free = trim((string) ($rule['free_over'] ?? ''));
            $facts['shipping'][] = [
                'countries' => $countries,
                'rate' => max(0.0, (float) ($rule['rate'] ?? 0)),
                'free_over' => $free === '' ? null : max(0.0, (float) $free),
                'handling_min' => $values[0],
                'handling_max' => $values[1],
                'transit_min' => $values[2],
                'transit_max' => $values[3],
            ];
        }
        Store::set('facts', $facts, $this->idShop);

        return $this->done('data', $this->t('Delivery and returns saved. They are now on your product pages and in your product data.'));
    }

    private function factsError(string $message): array
    {
        return ['flash' => ['type' => 'error', 'text' => $message]];
    }

    private function saveMarkup(): array
    {
        foreach (['GEOLENS_SCHEMA' => 'schema', 'GEOLENS_VARIANTS' => 'variants', 'GEOLENS_SHOW_FACTS' => 'show_facts', 'GEOLENS_SHOP_BRAND' => 'shop_brand', 'GEOLENS_STRIKETHROUGH' => 'strike'] as $name => $field) {
            \Configuration::updateValue($name, (int) (bool) \Tools::getValue($field));
        }

        return $this->done('data', $this->t('Settings updated.'));
    }

    private function pageCheck(): array
    {
        $url = trim((string) \Tools::getValue('url'));
        if (!Pages::isOwnUrl($url, $this->context)) {
            return ['error' => $this->t('Enter a page of this shop.')];
        }
        $key = (string) \Tools::getValue('key', 'custom');
        $result = Pages::check($url, (bool) \Tools::getValue('product'), $this->idShop, preg_replace('/[^a-z]/', '', $key));
        $this->context->smarty->assign('gl_p', $result);
        $html = $this->module->display($this->module->getLocalPath() . 'geolens.php', 'views/templates/admin/page-result.tpl');

        return ['html' => $html, 'errors' => count(array_filter($result['problems'], static fn ($p) => $p['level'] === 'error'))];
    }

    private function catalog(): array
    {
        $idLang = (int) \Tools::getValue('lang', $this->context->language->id);
        $name = 'catalog:' . $idLang;
        $audit = Store::get($name, $this->idShop);
        $age = Store::age($name, $this->idShop);
        if (!is_array($audit) || \Tools::getValue('refresh') || $age === null || $age > 21600) {
            $audit = (new Audit($this->idShop, $idLang))->run();
            Store::set($name, $audit, $this->idShop);
            $age = 0;
        }
        $locale = \Tools::getContextLocale($this->context);
        $number = static fn ($n) => $locale->formatNumber((int) $n);
        $rows = [];
        foreach (Audit::FACTS as $fact) {
            // A check digit only means something for products that have a GTIN.
            $base = $fact === 'gtin_valid' ? (int) $audit['facts']['gtin'] : (int) $audit['total'];
            if ($base === 0) {
                continue;
            }
            $n = (int) $audit['facts'][$fact];
            $rows[] = [
                'fact' => $fact,
                'has' => $number($n),
                'of' => $number($base),
                'gaps' => $n < $base,
                // Rounded down, so 99.99% never reads as 100% with products still to fix.
                'percent' => (int) floor($n * 100 / $base),
            ];
        }
        $this->context->smarty->assign([
            'gl_a' => $audit,
            'gl_fact_rows' => $rows,
            'gl_in_stock' => $number($audit['in_stock']),
            'gl_of' => $number($audit['total']),
            'gl_reviews' => $audit['reviews'] === null ? null : $number($audit['reviews']),
            'gl_age' => (int) floor($age / 60),
        ]);

        return ['html' => $this->module->display($this->module->getLocalPath() . 'geolens.php', 'views/templates/admin/catalog.tpl')];
    }

    private function catalogMissing(): array
    {
        $fact = (string) \Tools::getValue('fact');
        if (!in_array($fact, Audit::FACTS, true)) {
            return ['error' => 'Unknown fact'];
        }
        $idLang = (int) \Tools::getValue('lang', $this->context->language->id);
        $result = (new Audit($this->idShop, $idLang))->missing($fact, (int) \Tools::getValue('after'));
        $rows = [];
        foreach ($result['rows'] as $row) {
            $rows[] = [
                'name' => (string) $row['name'],
                'reference' => (string) $row['reference'],
                'url' => $this->context->link->getAdminLink('AdminProducts', true, ['route' => 'admin_products_edit', 'productId' => (int) $row['id_product']], ['id_product' => (int) $row['id_product']]),
            ];
        }
        $this->context->smarty->assign(['gl_rows' => $rows, 'gl_next' => $result['next'], 'gl_fact' => $fact, 'gl_alang' => $idLang]);

        return ['html' => $this->module->display($this->module->getLocalPath() . 'geolens.php', 'views/templates/admin/catalog-missing.tpl')];
    }

    private function saveIndexNow(): array
    {
        $on = (bool) \Tools::getValue('indexnow');
        \Configuration::updateValue('GEOLENS_INDEXNOW', (int) $on);
        if ($on) {
            IndexNow::writeKeyFile();
        }

        return $this->done('settings', $this->t('Settings updated.'));
    }

    private function indexNowSend(): array
    {
        $sent = IndexNow::flush($this->idShop);

        return $this->done('settings', $this->t('%count% URLs submitted.', ['%count%' => (string) $sent]));
    }

    private function indexNowBackfill(): array
    {
        IndexNow::startBackfill($this->idShop);
        $sent = IndexNow::flush($this->idShop);

        return $this->done('settings', $this->t('Sending your whole catalogue in the background. First batch: %count% URLs.', ['%count%' => (string) $sent]));
    }

    private function saveLlms(): array
    {
        \Configuration::updateValue('GEOLENS_LLMS', (int) (bool) \Tools::getValue('llms'));
        $summary = [];
        $posted = \Tools::getValue('summary');
        foreach (is_array($posted) ? $posted : [] as $idLang => $text) {
            $text = trim(strip_tags((string) $text));
            if ($text !== '') {
                $summary[(int) $idLang] = \Tools::substr($text, 0, 500);
            }
        }
        Store::set('llms', ['summary' => $summary], $this->idShop);

        return $this->done('settings', $this->t('Settings updated.'));
    }

    private function saveFeed(): array
    {
        $country = (string) \Tools::getValue('country');
        $idLang = (int) \Tools::getValue('id_lang');
        $idCurrency = (int) \Tools::getValue('id_currency');
        $mode = (string) \Tools::getValue('mode');
        $target = (string) \Tools::getValue('target');
        $countries = array_column(\Country::getCountries((int) $this->context->language->id, true), 'iso_code');
        $languages = array_map('intval', array_column(\Language::getLanguages(true, $this->idShop), 'id_lang'));
        if (!in_array($country, $countries, true) || !in_array($idLang, $languages, true) || !isset(Profiles::currencies($this->idShop)[$idCurrency])
            || !in_array($mode, [Profiles::PRIMARY, Profiles::SUPPLEMENTAL], true) || !in_array($target, [Profiles::GOOGLE, Profiles::MICROSOFT], true)) {
            return $this->factsError($this->t('Choose a country, language and currency this shop sells in.'));
        }
        $refusal = Profiles::refusal(Profiles::all($this->idShop), $country, $idLang, $mode, $target);
        if ($refusal === 'duplicate') {
            return $this->factsError($this->t('There is already a feed like this for that country and language. Two would compete for the same products.'));
        }
        if ($refusal === 'microsoft_supplemental') {
            return $this->factsError($this->t('A supplemental source is for Google only. Microsoft\'s documentation has no such thing.'));
        }
        if (!Files::prepare()) {
            return $this->factsError($this->t('The folder %folder% cannot be written, so there is nowhere to keep the file.', ['%folder%' => 'var/modules/geolens']));
        }
        Builder::start(Profiles::add($this->idShop, $country, $idLang, $idCurrency, $mode, $target), $this->idShop);
        $this->startBuilding();

        return $this->done('feed', $this->t('Feed added and being built in the background. You can leave this page.'));
    }

    /** Asks for the next stretch of work. It takes 5 seconds at most, or returns at once if a background request has the lock. */
    private function feedStep(): array
    {
        Maintenance::run(false, true, 5);

        return ['list' => (new Panels($this->module, $this->context))->feedList()];
    }

    private function feedRebuild(): array
    {
        $feed = Profiles::find($this->idShop, (string) \Tools::getValue('key'));
        if ($feed === null) {
            return ['error' => 'Unknown feed'];
        }
        Builder::start($feed, $this->idShop);
        $this->startBuilding();

        return $this->done('feed', $this->t('Building in the background. You can leave this page.'));
    }

    private function startBuilding(): void
    {
        Scheduler::plan($this->idShop, true);
        Maintenance::spawn(true);
    }

    /** A request to the file's own address, as Merchant Center will make it. Only the response headers are read. */
    private function feedTest(): array
    {
        $feed = Profiles::find($this->idShop, (string) \Tools::getValue('key'));
        if ($feed === null) {
            return ['error' => 'Unknown feed'];
        }
        $url = Profiles::url($feed, $this->context->shop, (bool) \Configuration::get('PS_REWRITING_SETTINGS'));
        $response = Http::request('HEAD', $url, ['X-Geolens-Check: ' . \Configuration::getGlobalValue('GEOLENS_TOKEN')], null, 15);
        if ($response['status'] === 200) {
            return $this->done('feed', $this->t('The address works from this server. A firewall could still refuse Merchant Center, whose fetch is the real test.'));
        }
        if ($response['status'] === 503) {
            return $this->done('feed', $this->t('The address works, but the file is not built yet.'), 'warning');
        }

        return $this->done('feed', $this->t('The address answered with code %code%. Merchant Center would get the same, so check your server or firewall.', ['%code%' => (string) $response['status']]), 'error');
    }

    private function feedRotate(): array
    {
        if (Profiles::rotate($this->idShop, (string) \Tools::getValue('key')) === null) {
            return ['error' => 'Unknown feed'];
        }

        return $this->done('feed', $this->t('New address created. The old one has stopped working, so update it in Merchant Center.'), 'warning');
    }

    private function feedRemove(): array
    {
        $key = (string) \Tools::getValue('key');
        if (Profiles::find($this->idShop, $key) === null) {
            return ['error' => 'Unknown feed'];
        }
        Profiles::remove($this->idShop, $key);
        Files::remove($this->idShop, $key);
        foreach ([Builder::jobName($key), Builder::lastName($key), 'feed_fetch:' . $key] as $name) {
            Store::delete($name, $this->idShop);
        }
        Scheduler::plan($this->idShop);

        return $this->done('feed', $this->t('Feed removed. Remove it from Merchant Center too, or its next fetch will fail.'));
    }
}
