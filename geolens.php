<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Iamshoaibktk\Geolens\Admin\Controller;
use Iamshoaibktk\Geolens\Front\Gate;
use Iamshoaibktk\Geolens\Indexing\Fresh;
use Iamshoaibktk\Geolens\Indexing\IndexNow;
use Iamshoaibktk\Geolens\Robots\Policy;
use Iamshoaibktk\Geolens\Schema\PageWriter;
use Iamshoaibktk\Geolens\Support\Installer;
use Iamshoaibktk\Geolens\Traffic\Recorder;

class Geolens extends Module
{
    public const D = 'Modules.Geolens.Admin';

    public function __construct()
    {
        $this->name = 'geolens';
        $this->tab = 'seo';
        $this->version = '1.1.1';
        $this->author = 'iamshoaibktk@gmail.com';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('GEO Lens - AI Search Visibility', [], self::D);
        $this->description = $this->trans('See which AI crawlers read your store and which AI visits become orders, control crawler access safely, and give AI engines complete, accurate product data.', [], self::D);
        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall this module?', [], self::D);
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    public function install(): bool
    {
        if (!parent::install() || !Installer::install() || !$this->registerHook(Installer::HOOKS)) {
            return false;
        }
        // In the EU, a crossed-out price must follow the 30-day lowest price rule, which the module
        // cannot check, so EU shops start with it off.
        $country = (string) Country::getIsoById((int) Configuration::get('PS_COUNTRY_DEFAULT'));
        Configuration::updateValue('GEOLENS_STRIKETHROUGH', (int) !PageWriter::isEuropeanUnion($country));

        return true;
    }

    public function uninstall(): bool
    {
        Policy::remove();
        IndexNow::removeKeyFile();

        return Installer::uninstall() && parent::uninstall();
    }

    public function getContent(): string
    {
        return (new Controller($this))->handle();
    }

    /** Module::trans() is protected, and the back office classes need it. */
    public function t(string $text, array $params = []): string
    {
        return $this->trans($text, $params, self::D);
    }

    /** A bug in this module must never cost the merchant a page or an order. */
    private function safely(callable $work, string $fallback = ''): string
    {
        try {
            return (string) $work();
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('GEO Lens: ' . $e->getMessage(), 2, null, 'Module', (int) $this->id);

            return $fallback;
        }
    }

    /** The module only ever watches the storefront. */
    public function hookActionDispatcherBefore(array $params): void
    {
        if (($params['controller_type'] ?? 0) !== Dispatcher::FC_ADMIN) {
            $this->safely(fn () => (new Gate($this->context))->handle());
        }
    }

    public function hookActionOutputHTMLBefore(array &$params): void
    {
        if (Configuration::get('GEOLENS_SCHEMA')) {
            $html = $params['html'];
            $params['html'] = $this->safely(fn () => (new PageWriter($this->context))->rewrite($html), $html);
        }
    }

    public function hookActionFrontControllerSetMedia(): void
    {
        if (Configuration::get('GEOLENS_SHOW_FACTS') && ($this->context->controller->php_self ?? '') === 'product') {
            $this->context->controller->registerStylesheet('geolens-front', 'modules/geolens/views/css/front.css', ['media' => 'all', 'priority' => 150]);
        }
    }

    /** Shows delivery and returns, so the markup claims nothing the visitor cannot read. */
    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        if (!Configuration::get('GEOLENS_SHOW_FACTS')) {
            return '';
        }

        return $this->safely(fn () => $this->renderFacts((float) ($params['product']['price_amount'] ?? 0)));
    }

    private function renderFacts(float $price): string
    {
        $currency = $this->context->currency;
        $seen = (new PageWriter($this->context))->facts()->visible((string) $this->context->country->iso_code, $price, (float) $currency->conversion_rate ?: 1.0);
        if ($seen['shipping'] === null && $seen['returns'] === null) {
            return '';
        }
        $locale = Tools::getContextLocale($this->context);
        $money = static fn (float $amount) => $locale->formatPrice($amount, (string) $currency->iso_code);
        if ($seen['shipping']) {
            $seen['shipping']['rate_label'] = $money($seen['shipping']['rate']);
            $seen['shipping']['free_over_label'] = $seen['shipping']['free_over'] === null ? '' : $money($seen['shipping']['free_over']);
        }
        if ($seen['returns']) {
            $seen['returns']['fee_label'] = $money($seen['returns']['fee']);
        }
        $this->context->smarty->assign('geolens_facts', $seen);

        return $this->fetch('module:geolens/views/templates/hook/facts.tpl');
    }

    /**
     * The order may be validated later by a payment webhook with no visitor cookie, so the link
     * from the AI visit is made here, while the visitor is on the shop.
     */
    public function hookActionCartSave(): void
    {
        $cart = $this->context->cart;
        $cookie = $this->context->cookie;
        if (!$cart || !$cart->id || empty($cookie->geolens_src)) {
            return;
        }
        if (time() - (int) $cookie->geolens_at <= (int) Configuration::getGlobalValue('GEOLENS_ATTR_WINDOW')) {
            $this->safely(fn () => Recorder::cart((int) $this->context->shop->id, (int) $cart->id, (string) $cookie->geolens_src, (string) $cookie->geolens_lp));
        }
    }

    public function hookActionValidateOrder(array $params): void
    {
        $order = $params['order'] ?? null;
        if ($order && Validate::isLoadedObject($order)) {
            $this->safely(fn () => Recorder::order((int) $order->id_shop, (int) $order->id, (int) $order->id_cart));
        }
    }

    /** Puts the module's lines back when the merchant regenerates robots.txt. */
    public function hookActionAdminMetaAfterWriteRobotsFile(array $params): void
    {
        $block = Policy::block();
        if ($block !== '' && !empty($params['write_fd'])) {
            fwrite($params['write_fd'], "\n" . $block);
        }
    }

    public function hookActionObjectProductAddAfter(array $params): void
    {
        $this->productChanged((int) $params['object']->id);
    }

    public function hookActionObjectProductUpdateAfter(array $params): void
    {
        $this->productChanged((int) $params['object']->id);
    }

    public function hookActionObjectCategoryAddAfter(array $params): void
    {
        $this->queue('category', (int) $params['object']->id);
    }

    public function hookActionObjectCategoryUpdateAfter(array $params): void
    {
        $this->queue('category', (int) $params['object']->id);
    }

    public function hookActionObjectCmsAddAfter(array $params): void
    {
        $this->queue('cms', (int) $params['object']->id);
    }

    public function hookActionObjectCmsUpdateAfter(array $params): void
    {
        $this->queue('cms', (int) $params['object']->id);
    }

    public function hookActionObjectSpecificPriceAddAfter(array $params): void
    {
        $this->productChanged((int) $params['object']->id_product);
    }

    public function hookActionObjectSpecificPriceUpdateAfter(array $params): void
    {
        $this->productChanged((int) $params['object']->id_product);
    }

    public function hookActionObjectSpecificPriceDeleteAfter(array $params): void
    {
        $this->productChanged((int) $params['object']->id_product);
    }

    /** Only crossing zero matters. Telling search engines about every unit sold would be noise. */
    public function hookActionUpdateQuantity(array $params): void
    {
        $quantity = (int) ($params['quantity'] ?? 0);
        $delta = $params['delta_quantity'] ?? null;
        // Some paths in the core give no change, so only an empty shelf can be told apart.
        $crossed = $delta === null ? $quantity <= 0 : (($quantity - (int) $delta) > 0) !== ($quantity > 0);
        if ($crossed) {
            $this->productChanged((int) ($params['id_product'] ?? 0));
        }
    }

    private function queue(string $type, int $id): void
    {
        $this->safely(fn () => IndexNow::queue((int) $this->context->shop->id, $type, $id));
    }

    private function productChanged(int $idProduct): void
    {
        if ($idProduct > 0) {
            $this->safely(fn () => Fresh::touch((int) $this->context->shop->id, $idProduct));
            $this->queue('product', $idProduct);
        }
    }
}
