<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Traffic;

use Iamshoaibktk\Geolens\Bots\Registry;

/** Reads the counters back for the back office. Order value comes from the live order, so a cancelled one stops counting. */
final class Report
{
    private int $idShop;
    private int $days;
    private string $since;
    private string $p;
    private \Db $db;

    public function __construct(int $idShop, int $days)
    {
        $this->idShop = $idShop;
        $this->days = max(1, $days);
        $this->since = date('Y-m-d', strtotime('-' . ($this->days - 1) . ' days'));
        $this->p = _DB_PREFIX_ . 'geolens_';
        $this->db = \Db::getInstance();
    }

    private function orderJoin(): string
    {
        $excluded = (int) \Configuration::get('PS_OS_CANCELED') . ',' . (int) \Configuration::get('PS_OS_ERROR');

        return 'INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_order` = g.`id_order` AND o.`current_state` NOT IN (' . $excluded . ')';
    }

    /** @return array{visits: int, orders: int, value: float, crawls: int, all_orders: int} */
    public function totals(): array
    {
        $visits = (int) $this->db->getValue("SELECT SUM(`visits`) FROM `{$this->p}visit_day` WHERE `id_shop` = {$this->idShop} AND `day` >= '{$this->since}'");
        $orders = $this->db->getRow('SELECT COUNT(*) AS n, SUM(o.`total_paid_tax_incl` / IF(o.`conversion_rate` > 0, o.`conversion_rate`, 1)) AS value
            FROM `' . $this->p . 'order` g ' . $this->orderJoin() . "
            WHERE g.`id_shop` = {$this->idShop} AND g.`date_add` >= '{$this->since}'");
        $crawls = 0;
        foreach ($this->db->executeS("SELECT `bot`, SUM(`hits`) AS hits FROM `{$this->p}bot_day` WHERE `id_shop` = {$this->idShop} AND `day` >= '{$this->since}' GROUP BY `bot`") as $row) {
            if ((Registry::BOTS[$row['bot']]['purpose'] ?? '') !== Registry::CORE) {
                $crawls += (int) $row['hits'];
            }
        }
        $all = (int) $this->db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . "orders` WHERE `id_shop` = {$this->idShop} AND `date_add` >= '{$this->since}'");

        return ['visits' => $visits, 'orders' => (int) $orders['n'], 'value' => (float) $orders['value'], 'crawls' => $crawls, 'all_orders' => $all];
    }

    /** @return string[] every day of the period, oldest first */
    public function dayList(): array
    {
        $days = [];
        for ($i = $this->days - 1; $i >= 0; --$i) {
            $days[] = date('Y-m-d', strtotime('-' . $i . ' days'));
        }

        return $days;
    }

    /** @return array<string, int> day => AI visits */
    public function visitsByDay(): array
    {
        $out = array_fill_keys($this->dayList(), 0);
        foreach ($this->db->executeS("SELECT `day`, SUM(`visits`) AS n FROM `{$this->p}visit_day` WHERE `id_shop` = {$this->idShop} AND `day` >= '{$this->since}' GROUP BY `day`") as $row) {
            $out[$row['day']] = (int) $row['n'];
        }

        return $out;
    }

    /** @return array<string, array<string, int>> day => purpose => requests */
    public function crawlsByDay(): array
    {
        $empty = [Registry::SEARCH => 0, Registry::USER => 0, Registry::TRAIN => 0];
        $out = array_fill_keys($this->dayList(), $empty);
        foreach ($this->db->executeS("SELECT `day`, `bot`, SUM(`hits`) AS n FROM `{$this->p}bot_day` WHERE `id_shop` = {$this->idShop} AND `day` >= '{$this->since}' GROUP BY `day`, `bot`") as $row) {
            $purpose = Registry::BOTS[$row['bot']]['purpose'] ?? '';
            if (isset($empty[$purpose], $out[$row['day']])) {
                $out[$row['day']][$purpose] += (int) $row['n'];
            }
        }

        return $out;
    }

    /** @return array<int, array{engine: string, visits: int, carts: int, orders: int, value: float}> */
    public function engines(): array
    {
        $rows = [];
        foreach ($this->db->executeS("SELECT `engine`, SUM(`visits`) AS n FROM `{$this->p}visit_day` WHERE `id_shop` = {$this->idShop} AND `day` >= '{$this->since}' GROUP BY `engine`") as $row) {
            $rows[$row['engine']] = ['engine' => $row['engine'], 'visits' => (int) $row['n'], 'carts' => 0, 'orders' => 0, 'value' => 0.0];
        }
        foreach ($this->db->executeS("SELECT `engine`, COUNT(*) AS n FROM `{$this->p}cart` WHERE `id_shop` = {$this->idShop} AND `date_add` >= '{$this->since}' GROUP BY `engine`") as $row) {
            $rows[$row['engine']] = ($rows[$row['engine']] ?? ['engine' => $row['engine'], 'visits' => 0, 'carts' => 0, 'orders' => 0, 'value' => 0.0]);
            $rows[$row['engine']]['carts'] = (int) $row['n'];
        }
        foreach ($this->db->executeS('SELECT g.`engine`, COUNT(*) AS n, SUM(o.`total_paid_tax_incl` / IF(o.`conversion_rate` > 0, o.`conversion_rate`, 1)) AS value
            FROM `' . $this->p . 'order` g ' . $this->orderJoin() . "
            WHERE g.`id_shop` = {$this->idShop} AND g.`date_add` >= '{$this->since}' GROUP BY g.`engine`") as $row) {
            $rows[$row['engine']] = ($rows[$row['engine']] ?? ['engine' => $row['engine'], 'visits' => 0, 'carts' => 0, 'orders' => 0, 'value' => 0.0]);
            $rows[$row['engine']]['orders'] = (int) $row['n'];
            $rows[$row['engine']]['value'] = (float) $row['value'];
        }
        usort($rows, static fn ($a, $b) => [$b['value'], $b['visits']] <=> [$a['value'], $a['visits']]);

        return $rows;
    }

    /** @return array<int, array{path: string, engine: string, visits: int}> */
    public function landingPages(int $limit = 10): array
    {
        return (array) $this->db->executeS("SELECT `path`, `engine`, `visits` FROM `{$this->p}visit_page`
            WHERE `id_shop` = {$this->idShop} AND `last_seen` >= '{$this->since}' ORDER BY `visits` DESC LIMIT " . $limit);
    }

    /** @return array<int, array> */
    public function recentOrders(int $limit = 10): array
    {
        return (array) $this->db->executeS('SELECT g.`id_order`, g.`engine`, g.`landing`, g.`date_add`, o.`reference`, o.`total_paid_tax_incl` / IF(o.`conversion_rate` > 0, o.`conversion_rate`, 1) AS value
            FROM `' . $this->p . 'order` g ' . $this->orderJoin() . "
            WHERE g.`id_shop` = {$this->idShop} ORDER BY g.`id_order` DESC LIMIT " . $limit);
    }

    /** @return array<string, array{hits: int, ok: int, missing: int, refused: int, errors: int, verified: int, spoofed: int, ms: int, last: string}> */
    public function bots(): array
    {
        $out = [];
        foreach ($this->db->executeS("SELECT `bot`, SUM(`hits`) AS hits, SUM(`ok`) AS ok, SUM(`missing`) AS missing, SUM(`refused`) AS refused, SUM(`errors`) AS errors,
                SUM(`verified`) AS verified, SUM(`spoofed`) AS spoofed, SUM(`ms`) AS ms, MAX(`day`) AS last
            FROM `{$this->p}bot_day` WHERE `id_shop` = {$this->idShop} AND `day` >= '{$this->since}' GROUP BY `bot`") as $row) {
            $out[$row['bot']] = $row;
        }

        return $out;
    }

    /**
     * What assistants opened because someone asked: the nearest thing to seeing the question.
     *
     * @return array<int, array{path: string, hits: int, bots: string}>
     */
    public function pagesReadForPeople(int $limit = 10): array
    {
        return $this->pages(200, [Registry::USER], $limit);
    }

    /** @return array<int, array{path: string, hits: int, bots: string}> AI systems sent here and found nothing */
    public function missingPages(int $limit = 10): array
    {
        return $this->pages(404, [Registry::USER, Registry::SEARCH], $limit);
    }

    /**
     * @param string[] $purposes
     *
     * @return array<int, array{path: string, hits: int, bots: string}>
     */
    private function pages(int $status, array $purposes, int $limit): array
    {
        $tokens = [];
        foreach (Registry::BOTS as $token => $bot) {
            if (in_array($bot['purpose'], $purposes, true)) {
                $tokens[] = "'" . pSQL($token) . "'";
            }
        }

        return (array) $this->db->executeS("SELECT `path`, SUM(`hits`) AS hits, GROUP_CONCAT(DISTINCT `bot` SEPARATOR ', ') AS bots FROM `{$this->p}bot_page`
            WHERE `id_shop` = {$this->idShop} AND `status` = {$status} AND `last_seen` >= '{$this->since}' AND `bot` IN (" . implode(',', $tokens) . ')
            GROUP BY `path_hash`, `path` ORDER BY hits DESC LIMIT ' . $limit);
    }

    /** @return array<string, array{hits: int, bots: string}> */
    public function demand(): array
    {
        $out = [];
        foreach ($this->db->executeS("SELECT `what`, SUM(`hits`) AS hits, GROUP_CONCAT(DISTINCT `bot` SEPARATOR ', ') AS bots FROM `{$this->p}demand_day`
            WHERE `id_shop` = {$this->idShop} AND `day` >= '{$this->since}' GROUP BY `what` ORDER BY hits DESC") as $row) {
            $out[$row['what']] = $row;
        }

        return $out;
    }
}
