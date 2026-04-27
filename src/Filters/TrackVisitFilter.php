<?php

namespace Pepeiborra\CI4TrafficReader\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Cache\CacheInterface;
use Pepeiborra\CI4TrafficReader\Config\TrafficReader as TrafficReaderConfig;
use Pepeiborra\CI4TrafficReader\Services\ThreatMailer;

class TrackVisitFilter implements FilterInterface
{
    // ─── Extensiones y rutas a ignorar ───────────────────────────────────────

    private array $excludeExtensions = [
        'css', 'js', 'map',
        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'bmp',
        'woff', 'woff2', 'ttf', 'eot', 'otf',
        'pdf', 'zip', 'gz',
    ];

    private array $excludePaths = [
        'traffic-reader',
    ];

    // ─── Patrones de amenaza ──────────────────────────────────────────────────

    private array $rcePatterns = [
        'allow_url_include', 'auto_prepend_file', 'php://input',
        'php://filter',      'data://text',        'expect://',
        'invokefunction',    'call_user_func',      'call_user_func_array',
        '/etc/passwd',       '/etc/shadow',         '/proc/self',
        'eval(',             'base64_decode(',       'system(',
        'exec(',             'passthru(',            'shell_exec(',
        'popen(',            'proc_open(',
    ];

    private array $sqliPatterns = [
        "' or '1'='1",   "' or 1=1",        "'; drop table",
        "union select",  "union all select", "information_schema",
        "load_file(",    "into outfile",     "xp_cmdshell",
        "waitfor delay", "sleep(",           "benchmark(",
        "extractvalue(", "updatexml(",       "0x",
    ];

    private array $xssPatterns = [
        '<script',       'javascript:',  'onerror=',
        'onload=',       'onclick=',     'onmouseover=',
        'alert(',        'confirm(',     'prompt(',
        'document.cookie', 'window.location', '<iframe',
        'src=data:',     'vbscript:',    'expression(',
    ];

    private array $sensitiveRoutes = [
        'admin',       'wp-admin',    'wp-login',
        'phpmyadmin',  '.env',        'config',
        'backup',      'dump',        '.git',
        'composer',    'artisan',     'storage',
        'xmlrpc',      'cpanel',      'webmail',
    ];

    private array $scannerUserAgents = [
        'sqlmap',    'nikto',      'nessus',
        'burpsuite', 'masscan',    'zgrab',
        'nuclei',    'hydra',      'acunetix',
        'dirbuster', 'gobuster',   'ffuf',
        'wfuzz',     'nmap',       'metasploit',
        'openvas',   'whatweb',    'skipfish',
        'w3af',      'zap',
    ];

    private array $criticalThreatTypes = [
        'RCE_ATTEMPT',
        'SQLI_ATTEMPT',
        'SCANNER_UA',
        'HIGH_RATE',
        'SENSITIVE_PROBE',
    ];

    // ─── Before: no hace nada, solo dejamos pasar ─────────────────────────────

    public function before(RequestInterface $request, $arguments = null)
    {
        // El tracking se hace after para conocer el status code
        return null;
    }

    // ─── After: registra la visita ────────────────────────────────────────────

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        try {
            $this->record($request, $response);
        } catch (\Throwable $e) {
            // Nunca romper la respuesta por un error del tracker
            log_message('error', '[TrafficReader] ' . $e->getMessage());
        }

        return null;
    }

    // ─── Registro principal ───────────────────────────────────────────────────

    private function record(RequestInterface $request, ResponseInterface $response): void
    {
        /** @var \CodeIgniter\HTTP\IncomingRequest $request */
        $uri    = $request->getUri();
        $path   = ltrim($uri->getPath(), '/');
        $config = config(TrafficReaderConfig::class);

        // Ignorar assets por extensión
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext && in_array($ext, $this->excludeExtensions, true)) {
            return;
        }

        // Ignorar rutas internas del paquete
        foreach ($this->excludePaths as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        // Ignorar rutas configuradas por el usuario
        foreach ($config->excludePaths as $prefix) {
            if (str_starts_with($path, ltrim($prefix, '/'))) {
                return;
            }
        }

        $ua         = $request->getUserAgent()->getAgentString() ?? 'UNKNOWN';
        $statusCode = $response->getStatusCode();
        $now        = new \DateTime();

        $threats  = array_merge(
            $this->detectPatterns($request),
            $this->detectRateAnomalies($request, $statusCode, $config),
        );

        $isThreat = !empty($threats);

        $queryString = $uri->getQuery();

        $data = [
            'timestamp'    => $now->format('Y-m-d H:i:s'),
            'session_date' => $now->format('Y-m-d'),
            'session_hour' => $now->format('H'),
            'ip'           => $request->getIPAddress(),
            'host'         => $uri->getHost(),
            'bot'          => $this->isBot($ua) ? 'YES' : 'NO',
            'device'       => $this->device($ua),
            'os'           => $this->os($ua),
            'browser'      => $this->browser($ua),
            'method'       => $request->getMethod(true),
            'url'          => '/' . $path . ($queryString ? '?' . $queryString : ''),
            'referer'      => $request->getHeaderLine('referer') ?: 'DIRECT',
            'page'         => basename($path) ?: 'index',
            'query_string' => $queryString,
            'status_code'  => $statusCode,
            'route_name'   => '',
            'threat'       => $isThreat ? 'YES' : 'NO',
            'threats'      => $threats,
            'user_agent'   => $ua,
        ];

        $line   = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $folder = rtrim(WRITEPATH, '/') . '/' . trim($config->storageFolder, '/');

        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        // Log diario de visitas
        file_put_contents(
            $folder . '/visits_' . $now->format('Y-m-d') . '.txt',
            $line,
            FILE_APPEND | LOCK_EX
        );

        // Log de amenazas
        if ($isThreat) {
            file_put_contents(
                $folder . '/threats_' . $now->format('Y-m-d') . '.txt',
                $line,
                FILE_APPEND | LOCK_EX
            );

            // Alertas para amenazas críticas
            $criticalThreats = array_values(array_filter(
                $threats,
                fn($t) => in_array($t['type'], $this->criticalThreatTypes, true)
            ));

            if (!empty($criticalThreats)) {
                (new ThreatMailer())->send($data, $criticalThreats, $config);
            }
        }
    }

    // ─── Detección de patrones (sin estado) ──────────────────────────────────

    private function detectPatterns(RequestInterface $request): array
    {
        /** @var \CodeIgniter\HTTP\IncomingRequest $request */
        $threats = [];
        $url     = rawurldecode('/' . ltrim($request->getUri()->getPath(), '/'));
        $qs      = $request->getUri()->getQuery();
        $full    = $url . ($qs ? '?' . $qs : '');
        $ua      = $request->getUserAgent()->getAgentString() ?? '';
        $method  = strtoupper($request->getMethod(true));

        foreach ($this->rcePatterns as $p) {
            if (stripos($full, $p) !== false) {
                $threats[] = ['type' => 'RCE_ATTEMPT', 'pattern' => $p];
                break;
            }
        }

        foreach ($this->sqliPatterns as $p) {
            if (stripos($full, $p) !== false) {
                $threats[] = ['type' => 'SQLI_ATTEMPT', 'pattern' => $p];
                break;
            }
        }

        foreach ($this->xssPatterns as $p) {
            if (stripos($full, $p) !== false) {
                $threats[] = ['type' => 'XSS_ATTEMPT', 'pattern' => $p];
                break;
            }
        }

        if (preg_match('/\.\.\/|\.\.\\\\|%2e%2e%2f|%252e%252e|%c0%af/i', $full)) {
            $threats[] = ['type' => 'PATH_TRAVERSAL'];
        }

        foreach ($this->scannerUserAgents as $s) {
            if (stripos($ua, $s) !== false) {
                $threats[] = ['type' => 'SCANNER_UA', 'tool' => $s];
                break;
            }
        }

        if (in_array($method, ['TRACE', 'TRACK', 'CONNECT', 'PROPFIND', 'MOVE'], true)) {
            $threats[] = ['type' => 'UNUSUAL_METHOD', 'method' => $method];
        }

        if (strlen($ua) < 10) {
            $threats[] = ['type' => 'SUSPICIOUS_UA', 'ua_length' => strlen($ua)];
        }

        return $threats;
    }

    // ─── Cache key segura (solo alphanum + underscore) ────────────────────────

    private function cacheKey(string $prefix, string $ip, string $window): string
    {
        // Elimina todo lo que no sea letra, número o guión bajo
        $safeIp     = preg_replace('/[^a-zA-Z0-9]/', '_', $ip);
        $safeWindow = preg_replace('/[^a-zA-Z0-9]/', '_', $window);
        return "tr_{$prefix}_{$safeIp}_{$safeWindow}";
    }

    // ─── Detección de anomalías con estado (Cache de CI4) ────────────────────

    private function detectRateAnomalies(
        RequestInterface $request,
        int $statusCode,
        TrafficReaderConfig $config
    ): array {
        $threats = [];
        /** @var CacheInterface $cache */
        $cache  = \Config\Services::cache();
        $ip     = $request->getIPAddress();
        $now    = new \DateTime();
        $window = $now->format('YmdHi');  // sin guiones ni colons
        $hour   = $now->format('YmdH');

        // Alta tasa
        $rateKey   = $this->cacheKey('rate', $ip, $window);
        $rateCount = (int)($cache->get($rateKey) ?? 0) + 1;
        $cache->save($rateKey, $rateCount, 90);

        if ($rateCount > $config->rateThreshold) {
            $threats[] = ['type' => 'HIGH_RATE', 'rpm' => $rateCount, 'threshold' => $config->rateThreshold];
        }

        // Route scan (404s)
        if ($statusCode === 404) {
            $nfKey   = $this->cacheKey('404', $ip, $hour);
            $nfCount = (int)($cache->get($nfKey) ?? 0) + 1;
            $cache->save($nfKey, $nfCount, 3600);

            if ($nfCount > $config->notFoundThreshold) {
                $threats[] = ['type' => 'ROUTE_SCAN', 'not_found' => $nfCount, 'threshold' => $config->notFoundThreshold];
            }
        }

        // Sensitive probe
        $path = ltrim($request->getUri()->getPath(), '/');
        foreach ($this->sensitiveRoutes as $route) {
            if (stripos($path, $route) !== false) {
                $probeKey   = $this->cacheKey('probe', $ip, $hour);
                $probeCount = (int)($cache->get($probeKey) ?? 0) + 1;
                $cache->save($probeKey, $probeCount, 3600);

                if ($probeCount >= $config->probeThreshold) {
                    $threats[] = ['type' => 'SENSITIVE_PROBE', 'path' => $path, 'count' => $probeCount, 'threshold' => $config->probeThreshold];
                }
                break;
            }
        }

        // Brute force (401/403)
        if (in_array($statusCode, [401, 403], true)) {
            $bfKey   = $this->cacheKey('bf', $ip, $hour);
            $bfCount = (int)($cache->get($bfKey) ?? 0) + 1;
            $cache->save($bfKey, $bfCount, 3600);

            if ($bfCount > $config->bruteForceThreshold) {
                $threats[] = ['type' => 'BRUTE_FORCE', 'count' => $bfCount, 'status' => $statusCode];
            }
        }

        return $threats;
    }

    // ─── UA helpers ──────────────────────────────────────────────────────────

    private function device(string $ua): string
    {
        if (preg_match('/tablet|ipad/i', $ua))           return 'TABLET';
        if (preg_match('/mobile|android|iphone/i', $ua)) return 'MOBILE';
        return 'DESKTOP';
    }

    private function os(string $ua): string
    {
        if (preg_match('/android/i',           $ua)) return 'Android';
        if (preg_match('/iphone|ipad/i',        $ua)) return 'iOS';
        if (preg_match('/windows nt 10/i',      $ua)) return 'Windows 10';
        if (preg_match('/windows nt 11/i',      $ua)) return 'Windows 11';
        if (preg_match('/windows nt 6\.3/i',    $ua)) return 'Windows 8.1';
        if (preg_match('/windows nt 6\.1/i',    $ua)) return 'Windows 7';
        if (preg_match('/macintosh|mac os x/i', $ua)) return 'MacOS';
        if (preg_match('/linux/i',              $ua)) return 'Linux';
        return 'UNKNOWN';
    }

    private function browser(string $ua): string
    {
        if (preg_match('/edg(e|\/)/i',    $ua)) return 'Edge';
        if (preg_match('/opr\//i',        $ua)) return 'Opera';
        if (preg_match('/chrome/i',       $ua)) return 'Chrome';
        if (preg_match('/firefox/i',      $ua)) return 'Firefox';
        if (preg_match('/safari/i',       $ua)) return 'Safari';
        if (preg_match('/msie|trident/i', $ua)) return 'Internet Explorer';
        return 'UNKNOWN';
    }

    private function isBot(string $ua): bool
    {
        return (bool) preg_match(
            '/bot|crawl|spider|slurp|facebookexternalhit|Amazonbot|GPTBot|ClaudeBot|bingbot|Googlebot|YandexBot|DuckDuckBot|Baiduspider/i',
            $ua
        );
    }
}
