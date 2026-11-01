<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Front;

use Iamshoaibktk\Geolens\Bots\IpRanges;
use Iamshoaibktk\Geolens\Bots\Matcher;
use Iamshoaibktk\Geolens\Bots\Registry;
use Iamshoaibktk\Geolens\Feed\Scheduler;
use Iamshoaibktk\Geolens\Feed\Server;
use Iamshoaibktk\Geolens\Indexing\IndexNow;
use Iamshoaibktk\Geolens\Llms\Builder;
use Iamshoaibktk\Geolens\Robots\Parser;
use Iamshoaibktk\Geolens\Robots\Policy;
use Iamshoaibktk\Geolens\Support\Maintenance;
use Iamshoaibktk\Geolens\Support\Store;
use Iamshoaibktk\Geolens\Traffic\Classifier;
use Iamshoaibktk\Geolens\Traffic\Recorder;

/**
 * Runs first on every front request, before any controller is built. An ordinary visitor costs one
 * regex and a few string checks, and writes nothing. It only does more for a known crawler, for an
 * arrival from an AI assistant, or for one of the module's own files.
 */
final class Gate
{
    /** Paths agents ask for that a shop may not serve. Counting them tests the module's own exclusions. */
    private const DEMAND_PATHS = [
        'llms.txt' => 'llms_txt',
        'llms-full.txt' => 'llms_full',
        'agents.md' => 'agents_md',
        '.well-known/ucp' => 'ucp',
        '.well-known/mcp.json' => 'mcp',
        '.well-known/mcp/server-card.json' => 'mcp',
        '.well-known/agent-card.json' => 'a2a',
        '.well-known/agent.json' => 'a2a',
    ];

    private \Context $context;
    private int $idShop;
    private ?string $verdict = null;

    public function __construct(\Context $context)
    {
        $this->context = $context;
        $this->idShop = (int) $context->shop->id;
    }

    public function handle(): void
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $relative = $this->relative($path);

        if (isset($_GET['geolens_cron'])) {
            $this->cron((string) $_GET['geolens_cron']);
        }
        // Before the crawler log and policy: this is a merchant's own platform collecting a private file.
        if (isset($_GET['geolens_feed'])) {
            Server::serve($this->idShop, (string) $_GET['geolens_feed']);
        }
        if (str_starts_with($relative, 'geolens-feed-') && str_ends_with($relative, '.xml')) {
            Server::serve($this->idShop, substr($relative, 13, -4));
        }

        $token = (string) \Configuration::getGlobalValue('GEOLENS_TOKEN');
        $selfCheck = hash_equals($token, (string) ($_SERVER['HTTP_X_GEOLENS_CHECK'] ?? ''));
        $bot = Matcher::match((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $logging = !$selfCheck && \Configuration::getGlobalValue('GEOLENS_LOG_BOTS');

        if ($logging) {
            foreach ($this->demandSignals($relative) as $what) {
                Recorder::demand($this->idShop, $what, $bot ?? 'other');
            }
        }
        if ($bot !== null) {
            if ($logging) {
                $this->recordBotAtEnd($bot, $path);
            }
            $this->enforce($bot, $uri);
        }

        $this->serveFile($relative);

        if ($bot === null && !$selfCheck) {
            $this->arrival($path);
            $this->tick();
        }
    }

    /** The path inside the shop, without its base folder or language. */
    private function relative(string $path): string
    {
        $base = (string) $this->context->shop->getBaseURI();
        if ($base !== '/' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = ltrim($path, '/');

        return (string) preg_replace('~^[a-z]{2}/~', '', $path);
    }

    /** @return string[] */
    private function demandSignals(string $relative): array
    {
        $signals = [];
        if (isset(self::DEMAND_PATHS[$relative])) {
            $signals[] = self::DEMAND_PATHS[$relative];
        } elseif (str_ends_with($relative, '.md')) {
            $signals[] = 'md_url';
        }
        if (stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/markdown') !== false) {
            $signals[] = 'accept_markdown';
        }
        if (isset($_SERVER['HTTP_SIGNATURE_AGENT'])) {
            $signals[] = 'signature_agent';
        }

        return $signals;
    }

    private function verdict(string $bot): string
    {
        if ($this->verdict === null) {
            $list = Registry::BOTS[$bot]['ip'];
            $cidrs = $list === null ? null : Store::get('ip:' . $list);
            $this->verdict = IpRanges::verdict(IpRanges::clientIp($_SERVER), is_array($cidrs) ? $cidrs : null);
        }

        return $this->verdict;
    }

    /** The status code only exists once the page has run, so the counter is written last. */
    private function recordBotAtEnd(string $bot, string $path): void
    {
        $started = microtime(true);
        register_shutdown_function(function () use ($bot, $path, $started) {
            $status = http_response_code();
            try {
                Recorder::bot($this->idShop, $bot, $this->verdict($bot), $status === false ? 200 : (int) $status, (int) round((microtime(true) - $started) * 1000), $path);
            } catch (\Throwable $e) {
                // The page has already been served. A lost counter is not worth an error on it.
            }
        });
    }

    private function enforce(string $bot, string $uri): void
    {
        if (Registry::BOTS[$bot]['purpose'] === Registry::CORE) {
            return;
        }
        if (Policy::isBlocked($bot)) {
            $this->refuse(403);
        }
        if (\Configuration::getGlobalValue('GEOLENS_GUARD') && $this->guardRefuses($bot, $uri)) {
            header('Retry-After: 86400');
            $this->refuse(429);
        }
    }

    /**
     * The load guard. A declared AI crawler asking for something robots.txt already forbids, such as
     * the cart, search or a filter URL. A compliant crawler never asks, so this costs no visibility.
     * A verified fetch for a person passes, because robots.txt does not bind those by design.
     */
    private function guardRefuses(string $bot, string $uri): bool
    {
        if (Registry::BOTS[$bot]['purpose'] === Registry::USER && $this->verdict($bot) === IpRanges::VERIFIED) {
            return false;
        }

        return !Parser::isAllowed(Parser::parse(Policy::current()), $bot, $uri);
    }

    private function refuse(int $status): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo 'Refused by this shop\'s crawler policy.';
        exit;
    }

    private function serveFile(string $relative): void
    {
        if ($relative === 'llms.txt' || $relative === 'agents.md') {
            if (\Configuration::get('GEOLENS_LLMS')) {
                $this->serveLlms();
            }

            return;
        }
        if (str_ends_with($relative, '.txt') && \Configuration::get('GEOLENS_INDEXNOW')) {
            $key = IndexNow::key();
            if ($relative === $key . '.txt') {
                header('Content-Type: text/plain; charset=utf-8');
                echo $key;
                exit;
            }
        }
    }

    /** A broken generator must not answer 5xx, the one state Lighthouse flags. It falls through to the 404. */
    private function serveLlms(): void
    {
        try {
            $text = Builder::forRequest($this->context, (string) ($_SERVER['REQUEST_URI'] ?? '/'));
        } catch (\Throwable $e) {
            return;
        }
        header('Content-Type: text/markdown; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        header('X-Robots-Tag: noindex');
        echo $text;
        exit;
    }

    /** Someone clicked through from an AI assistant: count it and remember it for the order. */
    private function arrival(string $path): void
    {
        if (!\Configuration::getGlobalValue('GEOLENS_LOG_VISITS')
            || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
            || isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            || stripos((string) ($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? ''), 'prefetch') !== false) {
            return;
        }
        $engine = Classifier::classify((string) ($_SERVER['HTTP_REFERER'] ?? ''), (string) ($_GET['utm_source'] ?? ''), (string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($engine === null) {
            return;
        }
        $cookie = $this->context->cookie;
        // A reload of the same landing page is not a new visit.
        $repeat = $cookie->geolens_src === $engine && $cookie->geolens_lp === $path && time() - (int) $cookie->geolens_at < 1800;
        $cookie->geolens_src = $engine;
        $cookie->geolens_at = time();
        $cookie->geolens_lp = substr($path, 0, 255);
        if ($repeat) {
            return;
        }
        register_shutdown_function(function () use ($engine, $path) {
            $status = http_response_code();
            try {
                if ($status === false || (int) $status < 400) {
                    Recorder::visit($this->idShop, $engine, $path);
                }
            } catch (\Throwable $e) {
                // As above: the visitor already has the page.
            }
        });
    }

    /**
     * Keeps the module's jobs running on shops with no cron set up. When one is due it asks the
     * shop to run it as a separate request, so no visitor waits for it.
     */
    private function tick(): void
    {
        $now = time();
        $daily = $now >= (int) \Configuration::getGlobalValue('GEOLENS_NEXT_DAILY');
        $flush = \Configuration::getGlobalValue('GEOLENS_DIRTY') && $now >= (int) \Configuration::getGlobalValue('GEOLENS_NEXT_FLUSH');
        $next = (int) \Configuration::get('GEOLENS_FEED_NEXT');
        $feed = $next > 0 && $now >= $next;
        if (!$daily && !$flush && !$feed) {
            return;
        }
        // Claimed first, so a busy shop sends one request and not one per visitor.
        if ($feed) {
            Scheduler::claim($this->idShop);
        }
        if ($daily || $flush) {
            \Configuration::updateGlobalValue('GEOLENS_NEXT_FLUSH', $now + IndexNow::SPACING);
        }
        if ($daily) {
            \Configuration::updateGlobalValue('GEOLENS_NEXT_DAILY', $now + 3600);
        }
        $feedOnly = !$daily && !$flush;
        register_shutdown_function(static function () use ($feedOnly) {
            Maintenance::spawn($feedOnly);
        });
    }

    private function cron(string $token): void
    {
        if (!hash_equals((string) \Configuration::getGlobalValue('GEOLENS_TOKEN'), $token)) {
            http_response_code(403);
            exit;
        }
        ignore_user_abort(true);
        @set_time_limit(120);
        header('Content-Type: application/json');
        echo json_encode(Maintenance::run(false, ($_GET['geolens_step'] ?? '') === 'feed'));
        exit;
    }
}
