<?php
/**
 * Traffic Reader — Dashboard de Visitas
 * Vista PHP pura para CodeIgniter 4
 *
 * Variables disponibles:
 * @var string $title
 * @var array  $dates
 * @var string $selectedDate
 * @var bool   $allMode
 * @var string $activeTab
 * @var array  $stats
 * @var array  $threats
 * @var string $periodLabel
 * @var string $chartHours
 * @var string $chartByDay
 * @var \Pepeiborra\CI4TrafficReader\Services\VisitsLogReader $reader
 */

$baseUrl = rtrim(site_url('traffic-reader/visitas'), '/');

function adUrl(string $baseUrl, string $tab, string $date, bool $all): string {
    $params = ['tab' => $tab];
    if ($all) {
        $params['all'] = '1';
    } else {
        $params['date'] = $date;
    }
    return $baseUrl . '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title) ?></title>
    <style>
        /* ── Paleta ── */
        .ad {
            --guinda: #9b2247; --guinda-dk: #7a1a38;
            --verde: #1e5b4f; --verde-dk: #164438;
            --sky: #0a5782; --lime: #4C8C2B;
            --orange: #b5430e;
            --surface: #ffffff; --bg: #f5f4f0;
            --border: #e0deda; --text: #1a1a18;
            --muted: #6b6a65; --r: 8px;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, sans-serif; background: var(--bg); color: var(--text); }
        .ad { padding: 1.5rem; min-height: 80vh; }

        /* ── Toolbar ── */
        .ad-toolbar { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem; background:var(--verde); border-radius:var(--r); padding:1rem 1.25rem; margin-bottom:1.25rem; }
        .ad-toolbar h1 { font-size:16px; font-weight:700; color:#fff; margin:0; }
        .ad-toolbar p  { font-size:11px; color:rgba(255,255,255,.65); margin:2px 0 0; font-family:monospace; }
        .ad-controls   { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .ad-btn        { height:32px; padding:0 14px; border-radius:5px; font-size:12px; font-weight:600; cursor:pointer; border:none; transition:opacity .15s; text-decoration:none; display:inline-flex; align-items:center; }
        .ad-btn:hover  { opacity:.85; }
        .ad-btn-all    { background:var(--guinda); color:#fff; }
        .ad-btn-all.active { background:#fff; color:var(--guinda); }
        .ad-select     { height:32px; border-radius:5px; font-size:12px; padding:0 10px; border:1px solid rgba(255,255,255,.35); background:rgba(255,255,255,.15); color:#fff; cursor:pointer; }
        .ad-select option { color:var(--text); background:#fff; }
        .ad-badge      { display:inline-block; background:var(--guinda); color:#fff; font-size:10px; font-weight:700; padding:2px 8px; border-radius:3px; margin-left:8px; vertical-align:middle; text-transform:uppercase; }

        /* ── Tabs ── */
        .ad-tabs  { display:flex; gap:2px; border-bottom:2px solid var(--border); margin-bottom:1.25rem; }
        .ad-tab   { padding:7px 18px; font-size:13px; font-weight:600; color:var(--muted); border:none; background:none; cursor:pointer; border-radius:6px 6px 0 0; border-bottom:2px solid transparent; margin-bottom:-2px; text-decoration:none; transition:color .15s; }
        .ad-tab:hover  { color:var(--verde); }
        .ad-tab.active { color:var(--verde); border-bottom-color:var(--verde); }
        .ad-tab-badge  { background:var(--orange); color:#fff; font-size:10px; padding:1px 5px; border-radius:3px; margin-left:5px; }

        /* ── Métricas ── */
        .ad-metrics { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:10px; margin-bottom:1.25rem; }
        .ad-metric  { background:var(--surface); border:1px solid var(--border); border-top:3px solid var(--verde); border-radius:var(--r); padding:.9rem 1rem; text-align:center; }
        .ad-metric.c-guinda { border-top-color:var(--guinda); }
        .ad-metric.c-sky    { border-top-color:var(--sky); }
        .ad-metric.c-orange { border-top-color:var(--orange); }
        .ad-metric.c-lime   { border-top-color:var(--lime); }
        .ad-metric-label    { font-size:10px; color:var(--muted); text-transform:uppercase; letter-spacing:.07em; margin-bottom:5px; }
        .ad-metric-value    { font-size:28px; font-weight:700; color:var(--text); line-height:1; }
        .ad-metric.c-guinda .ad-metric-value { color:var(--guinda); }
        .ad-metric.c-sky    .ad-metric-value { color:var(--sky); }
        .ad-metric.c-orange .ad-metric-value { color:var(--orange); }
        .ad-metric-sub { font-size:11px; color:var(--muted); margin-top:3px; }

        /* ── Panels ── */
        .ad-panel       { background:var(--surface); border:1px solid var(--border); border-radius:var(--r); padding:1.1rem 1.25rem; margin-bottom:1.1rem; }
        .ad-panel-title { font-size:11px; font-weight:700; color:var(--verde); text-transform:uppercase; letter-spacing:.08em; border-bottom:1px solid var(--border); padding-bottom:8px; margin-bottom:12px; }
        .ad-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1.1rem; margin-bottom:1.1rem; }
        @media (max-width:768px) { .ad-grid-2 { grid-template-columns:1fr; } }

        /* ── Barras ── */
        .ad-bars { list-style:none; padding:0; margin:0; }
        .ad-bar  { margin-bottom:9px; }
        .ad-bar-label { display:flex; justify-content:space-between; font-size:12px; margin-bottom:3px; }
        .ad-bar-label span:first-child { color:var(--text); }
        .ad-bar-label span:last-child  { color:var(--muted); font-weight:600; }
        .ad-bar-track { background:var(--bg); border-radius:3px; height:6px; overflow:hidden; }
        .ad-bar-fill  { height:100%; border-radius:3px; }

        /* ── Tablas ── */
        .ad-table    { width:100%; border-collapse:collapse; font-size:12px; }
        .ad-table th { text-align:left; font-size:10px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.07em; padding:0 8px 8px; border-bottom:2px solid var(--verde); }
        .ad-table td { padding:7px 8px; border-bottom:1px solid var(--border); vertical-align:top; word-break:break-all; }
        .ad-table tr:last-child td { border-bottom:none; }
        .ad-table tr:hover td { background:#fafaf8; }
        .col-r   { text-align:right; color:var(--muted); white-space:nowrap; }
        .col-idx { color:var(--muted); width:24px; }

        /* ── Tags ── */
        .ad-tag         { display:inline-block; font-size:10px; font-weight:700; padding:2px 6px; border-radius:3px; white-space:nowrap; }
        .ad-tag-attack  { background:#fde8e8; color:var(--orange); margin-left:5px; }
        .ad-tag-rce, .ad-tag-sqli, .ad-tag-xss { background:#fde8e8; color:#c0392b; }
        .ad-tag-rate, .ad-tag-route, .ad-tag-brute { background:#fff0eb; color:var(--orange); }
        .ad-tag-scanner, .ad-tag-path { background:#fef9e7; color:#b7770d; }
        .ad-tag-default { background:#f0f0f0; color:var(--muted); }

        /* ── Alerta ── */
        .ad-alert { display:flex; align-items:flex-start; gap:10px; background:#fdf2f2; border:1px solid #e8a0a0; border-left:3px solid var(--orange); border-radius:var(--r); padding:.75rem 1rem; font-size:13px; margin-bottom:1.1rem; color:#5a1a1a; }

        /* ── Empty ── */
        .ad-empty { text-align:center; padding:3rem 1rem; color:var(--muted); }
        .ad-empty-icon { font-size:36px; color:#ccc; margin-bottom:10px; }
        .ad-empty h3 { font-size:15px; color:var(--text); margin-bottom:6px; }
    </style>
</head>
<body>
<div class="ad">

    <?php
    // ── Toolbar ──────────────────────────────────────────────────────────────
    ?>
    <div class="ad-toolbar">
        <div>
            <h1>
                <?= esc($title) ?>
                <?php if ($allMode): ?>
                    <span class="ad-badge">Todo el período</span>
                <?php endif; ?>
            </h1>
            <p><?= esc($periodLabel) ?></p>
        </div>
        <div class="ad-controls">
            <a href="<?= adUrl($baseUrl, $activeTab, $selectedDate, true) ?>"
               class="ad-btn ad-btn-all <?= $allMode ? 'active' : '' ?>">
                &#9776; Todo
            </a>
            <select class="ad-select" onchange="location.href='<?= $baseUrl ?>?tab=<?= esc($activeTab) ?>&date='+this.value">
                <?php foreach ($dates as $d): ?>
                    <option value="<?= esc($d) ?>" <?= (!$allMode && $d === $selectedDate) ? 'selected' : '' ?>>
                        <?= esc($d) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if (empty($stats['total'])): ?>
        <div class="ad-empty">
            <div class="ad-empty-icon">📂</div>
            <h3>Sin registros</h3>
            <p>No se encontraron visitas para <strong><?= esc($periodLabel) ?></strong>.</p>
        </div>

    <?php else: ?>

        <?php if ($stats['threats'] > 0): ?>
            <div class="ad-alert">
                <span style="font-size:18px;">⚠</span>
                <div>
                    <strong>Actividad sospechosa detectada</strong>
                    &mdash; <?= $stats['threats'] ?> peticiones con amenazas
                    <?php if (!empty($stats['threat_types'])): ?>
                        (<?= esc(implode(', ', array_keys($stats['threat_types']))) ?>)
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php
        // ── Tabs ─────────────────────────────────────────────────────────────
        ?>
        <div class="ad-tabs">
            <a href="<?= adUrl($baseUrl, 'overview', $selectedDate, $allMode) ?>"
               class="ad-tab <?= $activeTab === 'overview' ? 'active' : '' ?>">Resumen</a>
            <a href="<?= adUrl($baseUrl, 'hours', $selectedDate, $allMode) ?>"
               class="ad-tab <?= $activeTab === 'hours' ? 'active' : '' ?>">Por hora / día</a>
            <a href="<?= adUrl($baseUrl, 'threats', $selectedDate, $allMode) ?>"
               class="ad-tab <?= $activeTab === 'threats' ? 'active' : '' ?>">
                Amenazas
                <?php if ($stats['threats'] > 0): ?>
                    <span class="ad-tab-badge"><?= $stats['threats'] ?></span>
                <?php endif; ?>
            </a>
        </div>

        <?php
        // ════════════════════════════════
        // TAB: RESUMEN
        // ════════════════════════════════
        if ($activeTab === 'overview'):
        ?>

            <div class="ad-metrics">
                <div class="ad-metric <?= $allMode ? 'c-guinda' : '' ?>">
                    <div class="ad-metric-label">Total registros</div>
                    <div class="ad-metric-value"><?= number_format($stats['total']) ?></div>
                    <div class="ad-metric-sub"><?= esc($periodLabel) ?></div>
                </div>
                <div class="ad-metric">
                    <div class="ad-metric-label">Humanos</div>
                    <div class="ad-metric-value"><?= number_format($stats['humans']) ?></div>
                    <div class="ad-metric-sub"><?= $stats['humans_pct'] ?>% del total</div>
                </div>
                <div class="ad-metric c-sky">
                    <div class="ad-metric-label">Bots</div>
                    <div class="ad-metric-value"><?= number_format($stats['bots']) ?></div>
                    <div class="ad-metric-sub"><?= $stats['bots_pct'] ?>% del total</div>
                </div>
                <?php if ($allMode && count($dates) > 0): ?>
                    <div class="ad-metric c-guinda">
                        <div class="ad-metric-label">Promedio / día</div>
                        <div class="ad-metric-value"><?= number_format($stats['humans'] / count($dates), 1) ?></div>
                        <div class="ad-metric-sub">visitas humanas</div>
                    </div>
                <?php endif; ?>
                <div class="ad-metric">
                    <div class="ad-metric-label">Desktop</div>
                    <div class="ad-metric-value"><?= $stats['desktop'] ?></div>
                    <div class="ad-metric-sub"><?= $stats['desktop_pct'] ?>%</div>
                </div>
                <div class="ad-metric">
                    <div class="ad-metric-label">Mobile</div>
                    <div class="ad-metric-value"><?= $stats['mobile'] ?></div>
                    <div class="ad-metric-sub"><?= $stats['mobile_pct'] ?>%</div>
                </div>
                <div class="ad-metric">
                    <div class="ad-metric-label">Hora pico</div>
                    <div class="ad-metric-value"><?= esc($stats['peak_hour']) ?>h</div>
                    <div class="ad-metric-sub"><?= $stats['peak_count'] ?> visitas</div>
                </div>
                <?php if ($stats['threats'] > 0): ?>
                    <div class="ad-metric c-orange">
                        <div class="ad-metric-label">Amenazas</div>
                        <div class="ad-metric-value"><?= $stats['threats'] ?></div>
                        <div class="ad-metric-sub">peticiones</div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ad-grid-2">
                <?php $refTotal = array_sum($stats['referer']); ?>
                <div class="ad-panel">
                    <div class="ad-panel-title">Fuente de tráfico</div>
                    <ul class="ad-bars">
                        <?php foreach ($stats['referer'] as $label => $count):
                            $pct = $refTotal > 0 ? round($count / $refTotal * 100) : 0; ?>
                            <li class="ad-bar">
                                <div class="ad-bar-label"><span><?= esc($label) ?></span><span><?= $count ?> &nbsp;<?= $pct ?>%</span></div>
                                <div class="ad-bar-track"><div class="ad-bar-fill" style="width:<?= $pct ?>%;background:var(--guinda);"></div></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php $brTotal = array_sum($stats['browser']); ?>
                <div class="ad-panel">
                    <div class="ad-panel-title">Navegador</div>
                    <ul class="ad-bars">
                        <?php foreach ($stats['browser'] as $label => $count):
                            $pct = $brTotal > 0 ? round($count / $brTotal * 100) : 0; ?>
                            <li class="ad-bar">
                                <div class="ad-bar-label"><span><?= esc($label) ?></span><span><?= $count ?> &nbsp;<?= $pct ?>%</span></div>
                                <div class="ad-bar-track"><div class="ad-bar-fill" style="width:<?= $pct ?>%;background:var(--verde);"></div></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="ad-grid-2">
                <?php $osTotal = array_sum($stats['os']); ?>
                <div class="ad-panel">
                    <div class="ad-panel-title">Sistema operativo</div>
                    <ul class="ad-bars">
                        <?php foreach ($stats['os'] as $label => $count):
                            $pct = $osTotal > 0 ? round($count / $osTotal * 100) : 0; ?>
                            <li class="ad-bar">
                                <div class="ad-bar-label"><span><?= esc($label) ?></span><span><?= $count ?> &nbsp;<?= $pct ?>%</span></div>
                                <div class="ad-bar-track"><div class="ad-bar-fill" style="width:<?= $pct ?>%;background:var(--sky);"></div></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php $mTotal = array_sum($stats['method']); ?>
                <div class="ad-panel">
                    <div class="ad-panel-title">Método HTTP</div>
                    <ul class="ad-bars">
                        <?php foreach ($stats['method'] as $label => $count):
                            $pct = $mTotal > 0 ? round($count / $mTotal * 100) : 0; ?>
                            <li class="ad-bar">
                                <div class="ad-bar-label"><span><?= esc($label) ?></span><span><?= $count ?> &nbsp;<?= $pct ?>%</span></div>
                                <div class="ad-bar-track"><div class="ad-bar-fill" style="width:<?= $pct ?>%;background:var(--lime);"></div></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="ad-panel">
                <div class="ad-panel-title">Top 10 URLs</div>
                <table class="ad-table">
                    <thead><tr><th class="col-idx">#</th><th>URL</th><th class="col-r">Visitas</th></tr></thead>
                    <tbody>
                    <?php $i = 0; foreach ($stats['top_urls'] as $url => $count): $i++; ?>
                        <tr>
                            <td class="col-idx"><?= $i ?></td>
                            <td>
                                <?= esc(mb_substr($url, 0, 110)) ?>
                                <?php if ($reader->isAttackUrl($url)): ?>
                                    <span class="ad-tag ad-tag-attack">ATAQUE</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-r"><?= number_format($count) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php
        // ════════════════════════════════
        // TAB: POR HORA / DÍA
        // ════════════════════════════════
        elseif ($activeTab === 'hours'):
        ?>

            <div class="ad-panel">
                <div class="ad-panel-title">Visitas por hora <?= $allMode ? '(acumulado)' : '' ?></div>
                <div style="position:relative;height:200px;"><canvas id="chartHour"></canvas></div>
            </div>

            <?php if ($allMode && count($stats['by_day']) > 1): ?>
                <div class="ad-panel">
                    <div class="ad-panel-title">Visitas por día</div>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
                        <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted);"><span style="width:9px;height:9px;border-radius:2px;background:var(--verde);display:inline-block;"></span>Humanos</span>
                        <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted);"><span style="width:9px;height:9px;border-radius:2px;background:#aaa;display:inline-block;"></span>Bots</span>
                        <?php if ($stats['threats'] > 0): ?>
                            <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted);"><span style="width:9px;height:9px;border-radius:2px;background:var(--orange);display:inline-block;"></span>Amenazas</span>
                        <?php endif; ?>
                    </div>
                    <div style="position:relative;height:220px;"><canvas id="chartDays"></canvas></div>
                </div>
            <?php endif; ?>

            <?php $maxH = max($stats['hours']) ?: 1; ?>
            <div class="ad-panel">
                <div class="ad-panel-title">Detalle por hora</div>
                <table class="ad-table">
                    <thead><tr><th style="width:50px;">Hora</th><th>Distribución</th><th class="col-r">Visitas</th></tr></thead>
                    <tbody>
                    <?php foreach ($stats['hours'] as $h => $count): if ($count > 0): ?>
                        <tr>
                            <td><strong><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>h</strong></td>
                            <td>
                                <div class="ad-bar-track">
                                    <div class="ad-bar-fill" style="width:<?= round($count / $maxH * 100) ?>%;background:var(--verde);"></div>
                                </div>
                            </td>
                            <td class="col-r"><?= $count ?></td>
                        </tr>
                    <?php endif; endforeach; ?>
                    </tbody>
                </table>
            </div>

            <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
            <script>
                (function () {
                    var tc = '#6b6a65', gc = 'rgba(0,0,0,0.06)';
                    var baseScales = {
                        xAxes: [{ticks:{fontColor:tc,fontSize:11,autoSkip:false,maxRotation:30},gridLines:{display:false}}],
                        yAxes: [{ticks:{fontColor:tc,fontSize:11},gridLines:{color:gc},beginAtZero:true}]
                    };

                    var hCanvas = document.getElementById('chartHour');
                    if (hCanvas) {
                        new Chart(hCanvas, {
                            type: 'bar',
                            data: {
                                labels: ['00','01','02','03','04','05','06','07','08','09','10','11',
                                         '12','13','14','15','16','17','18','19','20','21','22','23'],
                                datasets: [{data: <?= $chartHours ?>, backgroundColor:'#1e5b4f'}]
                            },
                            options: {responsive:true,maintainAspectRatio:false,legend:{display:false},scales:baseScales}
                        });
                    }

                    <?php if ($allMode && count($stats['by_day']) > 1): ?>
                    var dCanvas = document.getElementById('chartDays');
                    if (dCanvas) {
                        var dayData = <?= $chartByDay ?>;
                        var datasets = [
                            {label:'Humanos',data:dayData.humans,backgroundColor:'#1e5b4f'},
                            {label:'Bots',data:dayData.bots,backgroundColor:'#aaa'}
                        ];
                        <?php if ($stats['threats'] > 0): ?>
                        datasets.push({label:'Amenazas',data:dayData.threats,backgroundColor:'#b5430e'});
                        <?php endif; ?>
                        new Chart(dCanvas, {
                            type: 'bar',
                            data: {labels:dayData.labels,datasets:datasets},
                            options: {responsive:true,maintainAspectRatio:false,legend:{display:false},scales:baseScales}
                        });
                    }
                    <?php endif; ?>
                }());
            </script>

        <?php
        // ════════════════════════════════
        // TAB: AMENAZAS
        // ════════════════════════════════
        elseif ($activeTab === 'threats'):
        ?>

            <?php if ($stats['threats'] === 0): ?>
                <div class="ad-empty">
                    <div class="ad-empty-icon">✅</div>
                    <h3>Sin amenazas detectadas</h3>
                    <p>No se registraron peticiones sospechosas en este período.</p>
                </div>
            <?php else: ?>

                <?php if (!empty($stats['threat_types'])):
                    $ttTotal = array_sum($stats['threat_types']); ?>
                    <div class="ad-panel">
                        <div class="ad-panel-title">Tipos de amenaza</div>
                        <ul class="ad-bars">
                            <?php foreach ($stats['threat_types'] as $type => $count):
                                $pct      = $ttTotal > 0 ? round($count / $ttTotal * 100) : 0;
                                $tagClass = 'ad-tag-' . strtolower(explode('_', $type)[0]); ?>
                                <li class="ad-bar">
                                    <div class="ad-bar-label">
                                        <span><span class="ad-tag <?= esc($tagClass) ?>"><?= esc($type) ?></span></span>
                                        <span><?= $count ?></span>
                                    </div>
                                    <div class="ad-bar-track"><div class="ad-bar-fill" style="width:<?= $pct ?>%;background:var(--orange);"></div></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="ad-panel">
                    <div class="ad-panel-title">
                        Log de amenazas
                        <span style="font-weight:400;color:var(--muted);font-size:11px;margin-left:6px;">(últimas <?= count($threats) ?>)</span>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="ad-table">
                            <thead><tr><th>Timestamp</th><th>IP</th><th>Tipo(s)</th><th>URL</th><th class="col-r">Status</th></tr></thead>
                            <tbody>
                            <?php if (empty($threats)): ?>
                                <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:1.5rem;">Sin registros en este período</td></tr>
                            <?php else: foreach ($threats as $threat): ?>
                                <tr>
                                    <td style="white-space:nowrap;color:var(--muted);font-size:11px;"><?= esc($threat['timestamp'] ?? '-') ?></td>
                                    <td style="white-space:nowrap;font-family:monospace;font-size:11px;"><?= esc($threat['ip'] ?? '-') ?></td>
                                    <td style="white-space:nowrap;">
                                        <?php foreach ($threat['threats'] ?? [] as $t):
                                            $tc = 'ad-tag-' . strtolower(explode('_', $t['type'])[0]); ?>
                                            <span class="ad-tag <?= esc($tc) ?>"><?= esc($t['type']) ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?= esc(mb_substr($threat['url'] ?? '-', 0, 80)) ?></td>
                                    <td class="col-r"><span style="font-family:monospace;"><?= esc($threat['status_code'] ?? '-') ?></span></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php endif; ?>

        <?php endif; // end tabs ?>

    <?php endif; // end records ?>

</div>
</body>
</html>
