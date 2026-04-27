<?php

namespace Pepeiborra\CI4TrafficReader\Services;

use Pepeiborra\CI4TrafficReader\Config\TrafficReader as TrafficReaderConfig;

class VisitsLogReader
{
    private string $folder;

    public function __construct()
    {
        $config       = config(TrafficReaderConfig::class);
        $this->folder = rtrim(WRITEPATH, '/') . '/' . trim($config->storageFolder, '/');
    }

    // ─── Fechas disponibles ───────────────────────────────────────────────────

    public function availableDates(): array
    {
        if (!is_dir($this->folder)) {
            return [];
        }

        $dates = [];
        foreach (scandir($this->folder) as $file) {
            if (preg_match('/^visits_(\d{4}-\d{2}-\d{2})\.txt$/', $file, $m)) {
                $dates[] = $m[1];
            }
        }

        rsort($dates);
        return $dates;
    }

    // ─── Leer registros ───────────────────────────────────────────────────────

    public function records(string $date): array
    {
        $path = $this->folder . '/visits_' . $date . '.txt';

        if (!file_exists($path)) {
            return [];
        }

        return $this->parseLines(file_get_contents($path));
    }

    public function allRecords(array $dates): array
    {
        $all = [];
        foreach ($dates as $date) {
            foreach ($this->records($date) as $r) {
                $all[] = $r;
            }
        }
        return $all;
    }

    // ─── Amenazas ─────────────────────────────────────────────────────────────

    public function threats(string $date): array
    {
        $path = $this->folder . '/threats_' . $date . '.txt';

        if (!file_exists($path)) {
            return [];
        }

        return array_reverse($this->parseLines(file_get_contents($path)));
    }

    // ─── Estadísticas ─────────────────────────────────────────────────────────

    public function stats(array $records): array
    {
        $total   = count($records);
        $humans  = array_values(array_filter($records, fn($r) => ($r['bot']    ?? 'NO') === 'NO'));
        $bots    = array_filter($records,              fn($r) => ($r['bot']    ?? 'NO') !== 'NO');
        $threats = array_filter($records,              fn($r) => ($r['threat'] ?? 'NO') === 'YES');

        $device  = $this->countKey($humans, 'device');
        $os      = $this->countKey($humans, 'os');
        $browser = $this->countKey($humans, 'browser');
        $method  = $this->countKey($humans, 'method');
        $referer = $this->countKey($humans, 'referer', fn($v) => $this->catReferer($v));
        $urls    = $this->countKey($humans, 'url');

        $hours = array_fill(0, 24, 0);
        foreach ($humans as $r) {
            $h = (int)($r['session_hour'] ?? 0);
            if ($h >= 0 && $h <= 23) {
                $hours[$h]++;
            }
        }

        $byDay = [];
        foreach ($records as $r) {
            $day = $r['session_date'] ?? 'unknown';
            if (!isset($byDay[$day])) {
                $byDay[$day] = ['humans' => 0, 'bots' => 0, 'threats' => 0];
            }
            if (($r['bot'] ?? 'NO') === 'NO') $byDay[$day]['humans']++;
            else $byDay[$day]['bots']++;
            if (($r['threat'] ?? 'NO') === 'YES') $byDay[$day]['threats']++;
        }
        ksort($byDay);

        arsort($urls);
        $topUrls = array_slice($urls, 0, 10, true);

        $threatTypes = [];
        foreach ($threats as $r) {
            foreach ($r['threats'] ?? [] as $t) {
                $type = $t['type'] ?? 'UNKNOWN';
                $threatTypes[$type] = ($threatTypes[$type] ?? 0) + 1;
            }
        }
        arsort($threatTypes);

        $peakCount    = !empty($hours) ? max($hours) : 0;
        $peakHour     = $peakCount > 0 ? array_search($peakCount, $hours) : 0;
        $humanCount   = count($humans);
        $desktopCount = $device['DESKTOP'] ?? 0;
        $mobileCount  = ($device['MOBILE'] ?? 0) + ($device['TABLET'] ?? 0);

        return [
            'total'        => $total,
            'humans'       => $humanCount,
            'bots'         => count($bots),
            'threats'      => count($threats),
            'humans_pct'   => $total > 0 ? round($humanCount / $total * 100, 1) : 0,
            'bots_pct'     => $total > 0 ? round(count($bots) / $total * 100, 1) : 0,
            'desktop'      => $desktopCount,
            'mobile'       => $mobileCount,
            'desktop_pct'  => $humanCount > 0 ? round($desktopCount / $humanCount * 100, 1) : 0,
            'mobile_pct'   => $humanCount > 0 ? round($mobileCount  / $humanCount * 100, 1) : 0,
            'peak_hour'    => str_pad((string)$peakHour, 2, '0', STR_PAD_LEFT),
            'peak_count'   => $peakCount,
            'hours'        => array_values($hours),
            'device'       => $device,
            'os'           => $os,
            'browser'      => $browser,
            'method'       => $method,
            'referer'      => $referer,
            'by_day'       => $byDay,
            'top_urls'     => $topUrls,
            'threat_types' => $threatTypes,
        ];
    }

    // ─── Helpers públicos ─────────────────────────────────────────────────────

    public function isAttackUrl(string $url): bool
    {
        $patterns = [
            'allow_url_include', 'auto_prepend_file', 'php://',
            'invokefunction', 'call_user_func', '../', '.env',
            'wp-admin', 'phpMyAdmin', '/etc/', 'eval(',
        ];
        foreach ($patterns as $p) {
            if (stripos($url, $p) !== false) return true;
        }
        return false;
    }

    // ─── Helpers privados ─────────────────────────────────────────────────────

    private function parseLines(string $content): array
    {
        $records = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if (!$line) continue;
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $records[] = $decoded;
            }
        }
        return $records;
    }

    private function countKey(array $records, string $key, ?callable $transform = null): array
    {
        $counts = [];
        foreach ($records as $r) {
            $val = $r[$key] ?? 'UNKNOWN';
            if ($transform) $val = $transform($val);
            $counts[$val] = ($counts[$val] ?? 0) + 1;
        }
        arsort($counts);
        return $counts;
    }

    private function catReferer(string $ref): string
    {
        if (!$ref || $ref === 'DIRECT') return 'Directo';
        $lower = strtolower($ref);
        if (str_contains($lower, 'google'))   return 'Google';
        if (str_contains($lower, 'bing'))     return 'Bing';
        if (str_contains($lower, 'facebook')) return 'Facebook';
        if (str_contains($lower, 'fb.com'))   return 'Facebook';
        if (str_contains($lower, 'gob.mx'))   return 'gob.mx';
        if (str_contains($lower, 'conanp'))   return 'conanp';
        return 'Otro';
    }
}
