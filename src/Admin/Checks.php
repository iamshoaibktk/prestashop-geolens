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
use Iamshoaibktk\Geolens\Feed\Neighbours;
use Iamshoaibktk\Geolens\Feed\Profiles;
use Iamshoaibktk\Geolens\Indexing\IndexNow;
use Iamshoaibktk\Geolens\Robots\Parser;
use Iamshoaibktk\Geolens\Robots\Policy;
use Iamshoaibktk\Geolens\Schema\PageWriter;
use Iamshoaibktk\Geolens\Support\Store;

/**
 * The overview's to-do list: things that are provably wrong or missing, each with where to fix it.
 * It is a list and never a score: a score of this kind measures the checklist, not visibility.
 */
final class Checks
{
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const INFO = 'info';

    /** Below this share of products, a missing fact is worth mentioning. */
    private const FACT_FLOOR = 0.8;

    private \Geolens $module;
    private \Context $context;
    private int $idShop;
    /** @var array<int, array{level: string, text: string, tab: string}> */
    private array $items = [];

    public function __construct(\Geolens $module, \Context $context)
    {
        $this->module = $module;
        $this->context = $context;
        $this->idShop = (int) $context->shop->id;
    }

    /** @return array<int, array{level: string, text: string, tab: string}> most serious first */
    public function run(): array
    {
        $this->shop();
        $this->robots();
        $this->access();
        $this->pages();
        $this->facts();
        $this->catalog();
        $this->feed();
        $this->freshness();
        $order =[self::ERROR => 0, self::WARNING => 1, self::INFO => 2];
        usort($this->items, static fn ($a, $b) => $order[$a['level']] <=> $order[$b['level']]);

        return $this->items;
    }

    /** @param array<string, string> $params */
    private function add(string $level, string $text, string $tab, array $params = []): void
    {
        $this->items[] = ['level' => $level, 'text' => $this->module->t($text, $params), 'tab' => $tab];
    }

    private function shop(): void
    {
        if (!\Configuration::get('PS_SHOP_ENABLE')) {
            $this->add(self::ERROR, 'Your shop is in maintenance mode, so no crawler can read it.', 'crawlers');
        }
    }

    private function robots(): void
    {
        if (!Policy::isAtDomainRoot()) {
            $this->add(self::WARNING, 'Your shop is in a subfolder, and crawlers only read robots.txt at the root of a domain. The lines below will not reach them from here. Blocks are still enforced by your server.', 'crawlers');
        }
        // Saving a shop URL rewrites the file and drops the block, so it is put back here and every day.
        if (!Policy::isApplied() && !Policy::write()) {
            $this->add(self::WARNING, 'Your crawler rules are not in robots.txt right now, and the file could not be updated.', 'crawlers');
        }
        $groups = Parser::parse(Policy::current());
        $closed = [];
        foreach (Registry::BOTS as $token => $bot) {
            if (in_array($bot['purpose'], [Registry::SEARCH, Registry::CORE], true) && !Policy::isBlocked($token) && !Parser::isAllowed($groups, $token, '/')) {
                $closed[] = $token;
            }
        }
        if ($closed) {
            $this->add(self::ERROR, 'robots.txt closes your shop to %bots%. You did not choose this here, so check the file.', 'crawlers', ['%bots%' => implode(', ', $closed)]);
        }
        $bypass = Policy::bypassing();
        if ($bypass) {
            $this->add(self::WARNING, 'robots.txt has its own group for %bots%, so they ignore your rules for the cart, search and filter pages. Remove those lines from the file. Allowed bots need no lines at all.', 'crawlers', ['%bots%' => implode(', ', $bypass)]);
        }
    }

    private function access(): void
    {
        $results = Store::get('access_results', $this->idShop);
        if (!$results) {
            $this->add(self::INFO, 'Test whether AI crawlers can really reach your shop. A firewall or CDN can refuse them even when robots.txt welcomes them.', 'crawlers');

            return;
        }
        $problems = [];
        foreach ($results as $token => $result) {
            if (empty($result['expected'])) {
                $problems[] = $token;
            }
        }
        if ($problems) {
            $this->add(self::ERROR, 'The last access test could not reach your shop as %bots%.', 'crawlers', ['%bots%' => implode(', ', array_slice($problems, 0, 6))]);
        }
    }

    private function pages(): void
    {
        $results = Store::get('page_results', $this->idShop);
        if (!$results) {
            $this->add(self::INFO, 'Check your pages to see what a crawler that runs no JavaScript gets from them.', 'data');

            return;
        }
        $errors = 0;
        foreach ($results as $page) {
            foreach ((array) ($page['problems'] ?? []) as $problem) {
                if ($problem['level'] === 'error') {
                    ++$errors;
                }
            }
        }
        if ($errors) {
            $this->add(self::ERROR, 'The last page check found %count% problems that hide content or contradict your markup.', 'data', ['%count%' => (string) $errors]);
        }
    }

    private function facts(): void
    {
        $facts = (new PageWriter($this->context))->facts();
        if (!$facts->hasReturns() || !$facts->hasShipping()) {
            $this->add(self::WARNING, 'Add your delivery and returns facts. PrestaShop publishes neither, and shoppers and Merchant Center both look for them.', 'data');
        }
    }

    private function catalog(): void
    {
        $audit = Store::get('catalog:' . (int) $this->context->language->id, $this->idShop);
        if (!is_array($audit) || empty($audit['total'])) {
            return;
        }
        $labels = [
            'brand' => '%percent%% of your products name a brand.',
            'gtin' => '%percent%% of your products have a GTIN.',
            'short' => '%percent%% of your products have a short description in this language.',
            'image' => '%percent%% of your products have a cover image.',
        ];
        foreach ($labels as $fact => $text) {
            $share = (int) ($audit['facts'][$fact] ?? 0) / (int) $audit['total'];
            if ($share < self::FACT_FLOOR) {
                $this->add(self::WARNING, $text, 'data', ['%percent%' => (string) round($share * 100)]);
            }
        }
    }

    private function feed(): void
    {
        $feeds = Profiles::all($this->idShop);
        if (!$feeds && !Neighbours::marketingWithGoogle()['linked']) {
            $this->add(self::INFO, 'Google says Merchant Center feeds can help products appear in AI responses. Build a product feed for it.', 'feed');
        }
        foreach ($feeds as $feed) {
            $job = Store::get(Builder::jobName($feed['key']), $this->idShop);
            if (is_array($job) && !empty($job['error'])) {
                $this->add(self::WARNING, 'A product feed could not be built. The last good file is still being served.', 'feed');
                break;
            }
        }
    }

    private function freshness(): void
    {
        if (!\Configuration::get('GEOLENS_INDEXNOW')) {
            $this->add(self::INFO, 'Turn on IndexNow to tell Bing, which feeds Copilot, about price and stock changes.', 'settings');
        } else {
            $code = (int) \Db::getInstance()->getValue('SELECT `code` FROM `' . _DB_PREFIX_ . 'geolens_indexnow_log` WHERE `id_shop` = ' . $this->idShop . ' ORDER BY `id` DESC');
            if ($code && $code !== 200 && $code !== 202) {
                $this->add(self::WARNING, 'The last IndexNow submission was refused with code %code%.', 'settings', ['%code%' => (string) $code]);
            }
        }
        if (IndexNow::backfillAt($this->idShop) > 0) {
            return;
        }
        $sitemap = _PS_ROOT_DIR_ . '/' . $this->idShop . '_index_sitemap.xml';
        if (!is_file($sitemap)) {
            $this->add(self::WARNING, 'This shop has no sitemap yet. Generate one with the Google sitemap module.', 'settings');
        }
    }
}
