<?php

namespace Pepeiborra\CI4TrafficReader\Services;

use Pepeiborra\CI4TrafficReader\Config\TrafficReader as TrafficReaderConfig;

class ThreatMailer
{
    public function send(array $visit, array $threats, TrafficReaderConfig $config): void
    {
        $this->sendEmail($visit, $threats, $config);

        if (!empty($config->slackWebhook)) {
            $this->sendSlack($visit, $threats, $config);
        }
    }

    // ─── Email ────────────────────────────────────────────────────────────────

    private function sendEmail(array $visit, array $threats, TrafficReaderConfig $config): void
    {
        $recipients = array_filter(explode(',', $config->alertEmails));

        if (empty($recipients)) {
            return;
        }

        $types   = implode(', ', array_column($threats, 'type'));
        $appName = config('App')->appName ?? 'App';
        $ip      = $visit['ip'];
        $url     = $visit['url'];
        $time    = $visit['timestamp'];
        $detail  = json_encode($threats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $today   = date('Y-m-d');

        $subject = "[SIEM] Amenaza detectada: {$types}";

        $body = "
⚠ Alerta de seguridad — {$appName}

IP:       {$ip}
URL:      {$url}
Hora:     {$time}
Amenazas: {$types}

Detalle:
{$detail}

Revisa el log en: writable/traffic_reader/threats_{$today}.txt
        ";

        try {
            $email = \Config\Services::email();
            $email->setFrom(config('App')->emailProtocol ?? 'noreply@example.com', $appName . ' SIEM');

            foreach ($recipients as $to) {
                $email->setTo(trim($to));
            }

            $email->setSubject($subject);
            $email->setMessage(nl2br(htmlspecialchars($body)));
            $email->setAltMessage($body);
            $email->send(false);
        } catch (\Throwable $e) {
            log_message('error', '[TrafficReader] Email failed: ' . $e->getMessage());
        }
    }

    // ─── Slack ────────────────────────────────────────────────────────────────

    private function sendSlack(array $visit, array $threats, TrafficReaderConfig $config): void
    {
        $types   = implode(', ', array_column($threats, 'type'));
        $appName = config('App')->appName ?? 'App';

        $payload = json_encode([
            'text'        => "⚠ *[SIEM] {$appName}* — Amenaza detectada",
            'attachments' => [[
                'color'  => 'danger',
                'fields' => [
                    ['title' => 'IP',       'value' => $visit['ip'],        'short' => true],
                    ['title' => 'Amenazas', 'value' => $types,              'short' => true],
                    ['title' => 'URL',      'value' => $visit['url'],       'short' => false],
                    ['title' => 'Hora',     'value' => $visit['timestamp'], 'short' => true],
                ],
            ]],
        ]);

        try {
            $curl = curl_init($config->slackWebhook);
            curl_setopt_array($curl, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
            ]);
            curl_exec($curl);
            curl_close($curl);
        } catch (\Throwable $e) {
            log_message('error', '[TrafficReader] Slack failed: ' . $e->getMessage());
        }
    }
}
