# ci4-traffic-reader

Lightweight SIEM filter for **CodeIgniter 4** (PHP 7.4+): visit tracking, threat detection, and security alerting. Equivalent to `pepeiborra/traffic-reader` for Laravel.

## Features

- 🔍 **Visit tracking** — IP, device, OS, browser, referrer, URL, status code
- 🛡️ **Threat detection** — RCE, SQLi, XSS, path traversal, scanner UA, brute force, rate abuse
- 📊 **Dashboard PHP puro** — estadísticas, gráficas Chart.js, log de amenazas
- 📧 **Alertas** — email (CI4 Email) + Slack webhook opcionales
- ⚙️ **Configurable** — umbrales, storage, rutas excluidas, layout

---

## Requirements

| | Versión |
|---|---|
| PHP | ^7.4 \| ^8.x |
| CodeIgniter | ^4.0 |

---

## Installation

```bash
composer require pepeiborra/ci4-traffic-reader
```

---

## Quick start

### 1. Publicar la config

```bash
php spark traffic-reader:publish
```

Esto copia `Config/TrafficReader.php` a `app/Config/TrafficReader.php`.

O manualmente: copia `vendor/pepeiborra/ci4-traffic-reader/src/Config/TrafficReader.php` a `app/Config/`.

### 2. Registrar el filtro en `app/Config/Filters.php`

```php
use Pepeiborra\CI4TrafficReader\Filters\TrackVisitFilter;

public array $aliases = [
    // ... tus filtros existentes
    'trackVisit' => TrackVisitFilter::class,
];

// Aplicar a todas las rutas web:
public array $globals = [
    'after' => [
        'trackVisit',
    ],
];
```

### 3. Registrar la ruta del dashboard en `app/Config/Routes.php`

```php
use Pepeiborra\CI4TrafficReader\Controllers\AuditDashboard;

$routes->get('traffic-reader/visitas', [AuditDashboard::class, 'index'], [
    'filter' => 'login',   // tu filtro de autenticación
    'as'     => 'traffic-reader.visits',
]);
```

### 4. Variables de entorno (`.env`)

```dotenv
# Emails separados por coma
TRAFFIC_READER_ALERT_EMAILS=admin@example.com,seguridad@example.com

# Slack (opcional)
TRAFFIC_READER_SLACK_WEBHOOK=https://hooks.slack.com/services/XXX/YYY/ZZZ
```

---

## Configuración completa (`app/Config/TrafficReader.php`)

```php
<?php namespace Config;

use Pepeiborra\CI4TrafficReader\Config\TrafficReader as BaseConfig;

class TrafficReader extends BaseConfig
{
    // Emails de alerta (separados por coma)
    public string $alertEmails  = '';   // o usar $_ENV

    // Slack webhook
    public string $slackWebhook = '';

    // Carpeta de logs dentro de WRITEPATH
    public string $storageFolder = 'traffic_reader';

    // Retención de logs en días
    public int $logRetentionDays = 30;

    // Umbrales
    public int $rateThreshold       = 60;   // req/min por IP
    public int $notFoundThreshold   = 20;   // 404/hora por IP
    public int $probeThreshold      = 3;    // accesos sensibles/hora
    public int $bruteForceThreshold = 10;   // 401-403/hora por IP

    // Rutas a excluir del tracking
    public array $excludePaths = ['api/health'];

    // Dashboard
    public string  $dashboardPrefix  = 'traffic-reader';
    public array   $dashboardFilters = ['login'];
    public string  $dashboardTitle   = 'Dashboard de Visitas';
    public ?string $dashboardLayout  = null; // null = standalone HTML
}
```

Para leer desde `.env`, sobreescribe en el constructor:

```php
public function __construct()
{
    parent::__construct();
    $this->alertEmails  = $_ENV['TRAFFIC_READER_ALERT_EMAILS']  ?? '';
    $this->slackWebhook = $_ENV['TRAFFIC_READER_SLACK_WEBHOOK'] ?? '';
}
```

---

## Usar el lector directamente

```php
use Pepeiborra\CI4TrafficReader\Services\VisitsLogReader;

$reader  = new VisitsLogReader();
$dates   = $reader->availableDates();
$records = $reader->records('2025-04-23');
$stats   = $reader->stats($records);
$threats = $reader->threats('2025-04-23');
```

---

## Estructura del paquete

```
ci4-traffic-reader/
├── composer.json
├── README.md
└── src/
    ├── TrafficReaderRegistrar.php
    ├── Config/
    │   └── TrafficReader.php
    ├── Controllers/
    │   └── AuditDashboard.php
    ├── Filters/
    │   └── TrackVisitFilter.php
    ├── Services/
    │   ├── VisitsLogReader.php
    │   └── ThreatMailer.php
    └── Views/
        └── dashboard/
            └── index.php
```

---

## Tipos de amenaza detectados

| Tipo | Descripción |
|---|---|
| `RCE_ATTEMPT` | Remote Code Execution en la URL |
| `SQLI_ATTEMPT` | SQL Injection |
| `XSS_ATTEMPT` | Cross-Site Scripting |
| `PATH_TRAVERSAL` | Traversal de directorios |
| `SCANNER_UA` | User-Agent de herramienta (sqlmap, nikto…) |
| `UNUSUAL_METHOD` | TRACE, CONNECT, PROPFIND… |
| `SUSPICIOUS_UA` | User-Agent vacío o muy corto |
| `HIGH_RATE` | Más de N req/min desde la misma IP |
| `ROUTE_SCAN` | Más de N 404s/hora |
| `SENSITIVE_PROBE` | Accesos a rutas sensibles (.env, wp-admin…) |
| `BRUTE_FORCE` | Más de N 401/403 por hora |

---

## License

MIT
