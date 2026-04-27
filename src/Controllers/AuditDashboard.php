<?php

namespace Pepeiborra\CI4TrafficReader\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Pepeiborra\CI4TrafficReader\Config\TrafficReader as TrafficReaderConfig;
use Pepeiborra\CI4TrafficReader\Services\VisitsLogReader;

class AuditDashboard extends Controller
{
    protected VisitsLogReader $reader;
    protected TrafficReaderConfig $config;

    public function initController(
        RequestInterface  $request,
        ResponseInterface $response,
        LoggerInterface   $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->reader = new VisitsLogReader();
        $this->config = config(TrafficReaderConfig::class);
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(): string
    {
        $dates        = $this->reader->availableDates();
        $selectedDate = $this->request->getGet('date') ?? ($dates[0] ?? date('Y-m-d'));
        $allMode      = (bool)$this->request->getGet('all');
        $activeTab    = $this->request->getGet('tab') ?? 'overview';

        $records = $allMode
            ? $this->reader->allRecords($dates)
            : $this->reader->records($selectedDate);

        $stats = $this->reader->stats($records);

        // Threats: all dates or just selected
        if ($allMode) {
            $threats = [];
            foreach ($dates as $d) {
                foreach ($this->reader->threats($d) as $t) {
                    $threats[] = $t;
                }
            }
            usort($threats, fn($a, $b) => strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? ''));
            $threats = array_slice($threats, 0, 50);
        } else {
            $threats = $this->reader->threats($selectedDate);
        }

        // Period label
        if ($allMode) {
            $periodLabel = 'Todo el período (' . count($dates) . ' días)';
        } else {
            try {
                $dt          = new \DateTime($selectedDate);
                $months      = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
                $periodLabel = $dt->format('d') . ' ' . $months[(int)$dt->format('n') - 1] . ' ' . $dt->format('Y');
            } catch (\Throwable) {
                $periodLabel = $selectedDate;
            }
        }

        $data = [
            'title'          => $this->config->dashboardTitle,
            'dates'          => $dates,
            'selectedDate'   => $selectedDate,
            'allMode'        => $allMode,
            'activeTab'      => $activeTab,
            'records'        => $records,
            'stats'          => $stats,
            'threats'        => $threats,
            'periodLabel'    => $periodLabel,
            'chartHours'     => json_encode(array_values($stats['hours'])),
            'chartByDay'     => $this->buildChartByDay($stats),
            'reader'         => $this->reader,
        ];

        $view = view('Pepeiborra\CI4TrafficReader\Views\dashboard\index', $data);

        if ($this->config->dashboardLayout) {
            return view($this->config->dashboardLayout, ['content' => $view]);
        }

        return $view;
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function buildChartByDay(array $stats): string
    {
        $byDay = $stats['by_day'];
        return json_encode([
            'labels'  => array_keys($byDay),
            'humans'  => array_column(array_values($byDay), 'humans'),
            'bots'    => array_column(array_values($byDay), 'bots'),
            'threats' => array_column(array_values($byDay), 'threats'),
        ]);
    }
}
