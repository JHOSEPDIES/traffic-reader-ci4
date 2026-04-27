<?php

namespace Pepeiborra\CI4TrafficReader\Config;

use CodeIgniter\Config\BaseConfig;

class TrafficReader extends BaseConfig
{
    // ─── Alertas ──────────────────────────────────────────────────────────────

    /**
     * Emails separados por coma que recibirán alertas críticas.
     * Ej: admin@example.com,seguridad@example.com
     */
    public string $alertEmails = '';

    /**
     * Webhook de Slack (opcional).
     * Ej: https://hooks.slack.com/services/XXX/YYY/ZZZ
     */
    public string $slackWebhook = '';

    // ─── Almacenamiento ───────────────────────────────────────────────────────

    /**
     * Carpeta dentro de WRITEPATH donde se guardan los logs.
     * Por defecto: WRITEPATH/traffic_reader/
     */
    public string $storageFolder = 'traffic_reader';

    /**
     * Días que se conservan los logs de seguridad.
     */
    public int $logRetentionDays = 30;

    // ─── Umbrales de detección ────────────────────────────────────────────────

    /** Requests por minuto por IP antes de considerarse alta tasa */
    public int $rateThreshold = 60;

    /** 404s por hora por IP antes de considerarse scanner */
    public int $notFoundThreshold = 20;

    /** Accesos a rutas sensibles por hora antes de alertar */
    public int $probeThreshold = 3;

    /** 401/403 por hora por IP antes de considerarse brute force */
    public int $bruteForceThreshold = 10;

    // ─── Rutas a excluir ──────────────────────────────────────────────────────

    /**
     * Prefijos de URI a ignorar (además de los internos del paquete).
     * Ej: ['api/health', 'admin/ping']
     */
    public array $excludePaths = [];

    // ─── Dashboard ────────────────────────────────────────────────────────────

    /**
     * Prefijo de la ruta del dashboard.
     * URL resultante: /traffic-reader/visitas
     */
    public string $dashboardPrefix = 'traffic-reader';

    /**
     * Middlewares (filtros) que protegen el dashboard.
     * Deben existir como filtros registrados en tu app.
     * Ej: ['login'] aplica el filtro 'login' de CI4.
     */
    public array $dashboardFilters = ['login'];

    /**
     * Título del dashboard.
     */
    public string $dashboardTitle = 'Dashboard de Visitas';

    /**
     * Vista de layout que envuelve el dashboard.
     * Null = sin layout, el dashboard se renderiza solo.
     * Si defines una vista, debe incluir <?= $this->renderSection('content') ?>
     */
    public ?string $dashboardLayout = null;
}
