<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Schema;

/**
 * Delivery and returns as the merchant states them. Only the merchant knows these, and PrestaShop
 * carriers cannot be turned into one honest sentence, so nothing is guessed: with no facts saved,
 * nothing is published. Shapes follow Google's merchant listing, return policy and shipping policy
 * documentation, last updated 2026-09-08.
 */
final class Facts
{
    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array{
     *     returns?: array{mode?: string, days?: int, fees?: string, fee?: float, countries?: string[], origin?: string},
     *     shipping?: array<int, array{countries: string[], rate: float, free_over: float|null, handling_min: int, handling_max: int, transit_min: int, transit_max: int}>,
     *     currency?: string
     * } $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function hasReturns(): bool
    {
        return !empty($this->config['returns']['mode']) && !empty($this->config['returns']['countries']);
    }

    public function hasShipping(): bool
    {
        return !empty($this->config['shipping']);
    }

    /** @param float $fx rate from the shop's currency to $currency */
    public function returnPolicy(string $currency, float $fx = 1.0): ?array
    {
        if (!$this->hasReturns()) {
            return null;
        }
        $r = $this->config['returns'];
        $countries = array_values($r['countries']);
        $policy = [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => count($countries) === 1 ? $countries[0] : $countries,
        ];
        if (!empty($r['origin'])) {
            $policy['returnPolicyCountry'] = $r['origin'];
        }
        if ($r['mode'] === 'none') {
            $policy['returnPolicyCategory'] = 'https://schema.org/MerchantReturnNotPermitted';

            return $policy;
        }
        if ($r['mode'] === 'unlimited') {
            $policy['returnPolicyCategory'] = 'https://schema.org/MerchantReturnUnlimitedWindow';
        } else {
            $policy['returnPolicyCategory'] = 'https://schema.org/MerchantReturnFiniteReturnWindow';
            $policy['merchantReturnDays'] = (int) ($r['days'] ?? 0);
        }
        $policy['returnMethod'] = 'https://schema.org/ReturnByMail';
        $fees = $r['fees'] ?? 'customer';
        if ($fees === 'free') {
            $policy['returnFees'] = 'https://schema.org/FreeReturn';
        } elseif ($fees === 'fixed') {
            $policy['returnFees'] = 'https://schema.org/ReturnShippingFees';
            $policy['returnShippingFeesAmount'] = [
                '@type' => 'MonetaryAmount',
                'value' => self::money((float) ($r['fee'] ?? 0) * $fx),
                'currency' => $currency,
            ];
        } else {
            $policy['returnFees'] = 'https://schema.org/ReturnFeesCustomerResponsibility';
        }

        return $policy;
    }

    /**
     * One OfferShippingDetails per delivery rule, at the rate this product's price would pay.
     *
     * @param float $price price of the product in $currency
     *
     * @return array<int, array>
     */
    public function offerShipping(float $price, string $currency, float $fx = 1.0): array
    {
        $nodes = [];
        foreach ((array) ($this->config['shipping'] ?? []) as $rule) {
            $free = $rule['free_over'] !== null && $price >= $rule['free_over'] * $fx;
            $nodes[] = [
                '@type' => 'OfferShippingDetails',
                'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => $free ? 0 : self::money($rule['rate'] * $fx), 'currency' => $currency],
                'shippingDestination' => self::regions($rule['countries']),
                'deliveryTime' => [
                    '@type' => 'ShippingDeliveryTime',
                    'handlingTime' => self::days($rule['handling_min'], $rule['handling_max']),
                    'transitTime' => self::days($rule['transit_min'], $rule['transit_max']),
                ],
            ];
        }

        return $nodes;
    }

    /** The whole policy for the Organization node. Google uses it when a product has no details of its own. */
    public function organizationShipping(string $shopName): ?array
    {
        if (!$this->hasShipping()) {
            return null;
        }
        $currency = (string) ($this->config['currency'] ?? '');
        $services = [];
        foreach ($this->config['shipping'] as $rule) {
            $conditions = [];
            $base = ['@type' => 'ShippingConditions', 'shippingDestination' => self::regions($rule['countries'])];
            $transit = ['@type' => 'ServicePeriod', 'duration' => self::days($rule['transit_min'], $rule['transit_max'])];
            $paid = $base;
            if ($rule['free_over'] !== null) {
                $paid['orderValue'] = ['@type' => 'MonetaryAmount', 'minValue' => 0, 'maxValue' => self::money($rule['free_over'] - 0.01), 'currency' => $currency];
            }
            $paid['shippingRate'] = ['@type' => 'MonetaryAmount', 'value' => self::money($rule['rate']), 'currency' => $currency];
            $paid['transitTime'] = $transit;
            $conditions[] = $paid;
            if ($rule['free_over'] !== null) {
                $free = $base;
                $free['orderValue'] = ['@type' => 'MonetaryAmount', 'minValue' => self::money($rule['free_over']), 'currency' => $currency];
                $free['shippingRate'] = ['@type' => 'MonetaryAmount', 'value' => 0, 'currency' => $currency];
                $free['transitTime'] = $transit;
                $conditions[] = $free;
            }
            $services[] = [
                '@type' => 'ShippingService',
                'name' => $shopName . ' - ' . implode(', ', $rule['countries']),
                'fulfillmentType' => 'FulfillmentTypeDelivery',
                'handlingTime' => ['@type' => 'ServicePeriod', 'duration' => self::days($rule['handling_min'], $rule['handling_max'])],
                'shippingConditions' => $conditions,
            ];
        }

        return count($services) === 1 ? $services[0] : $services;
    }

    /**
     * What the product page shows, so the markup claims nothing the visitor cannot read.
     *
     * @param string $country visitor's country, used to pick the rule
     *
     * @return array{shipping: array|null, returns: array|null}
     */
    public function visible(string $country, float $price, float $fx = 1.0): array
    {
        $picked = $this->ruleFor($country);
        $shipping = null;
        if ($picked !== null) {
            $shipping = [
                'free' => $picked['free_over'] !== null && $price >= $picked['free_over'] * $fx,
                'rate' => $picked['rate'] * $fx,
                'free_over' => $picked['free_over'] === null ? null : $picked['free_over'] * $fx,
                'min' => $picked['handling_min'] + $picked['transit_min'],
                'max' => $picked['handling_max'] + $picked['transit_max'],
            ];
        }
        $returns = null;
        if ($this->hasReturns() && in_array($country, $this->config['returns']['countries'], true)) {
            $r = $this->config['returns'];
            $returns = ['mode' => $r['mode'], 'days' => (int) ($r['days'] ?? 0), 'fees' => $r['fees'] ?? 'customer', 'fee' => (float) ($r['fee'] ?? 0) * $fx];
        }

        return ['shipping' => $shipping, 'returns' => $returns];
    }

    /** @return array{countries: string[], rate: float, free_over: float|null, handling_min: int, handling_max: int, transit_min: int, transit_max: int}|null the first rule that covers a country */
    public function ruleFor(string $country): ?array
    {
        foreach ((array) ($this->config['shipping'] ?? []) as $rule) {
            if (in_array($country, $rule['countries'], true)) {
                return $rule;
            }
        }

        return null;
    }

    /** @param string[] $countries */
    private static function regions(array $countries): array
    {
        $regions = array_map(static fn ($c) => ['@type' => 'DefinedRegion', 'addressCountry' => $c], array_values($countries));

        return count($regions) === 1 ? $regions[0] : $regions;
    }

    private static function days(int $min, int $max): array
    {
        return ['@type' => 'QuantitativeValue', 'minValue' => $min, 'maxValue' => $max, 'unitCode' => 'DAY'];
    }

    private static function money(float $amount): float
    {
        return round($amount, 2);
    }
}
