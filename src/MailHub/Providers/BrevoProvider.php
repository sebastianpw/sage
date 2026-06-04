<?php
/**
 * SAGE Mail Hub — Brevo Provider
 * src/MailHub/Providers/BrevoProvider.php
 *
 * Sends emails via the Brevo (formerly Sendinblue) Transactional Email API v3.
 * Free tier: 300 emails/day, no daily limit enforcement needed on their side
 * for small volumes, but we respect mail_hub_providers.daily_limit.
 *
 * Config JSON keys:
 *   api_key       (required) Brevo API key (starts with "xkeysib-")
 *   default_from  (optional) default sender email
 *   default_name  (optional) default sender name
 */

namespace App\MailHub\Providers;

class BrevoProvider implements MailProviderInterface
{
    private const API_BASE    = 'https://api.brevo.com/v3';
    private const SEND_SINGLE = '/smtp/email';

    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    // ── MailProviderInterface ─────────────────────────────────────────────

    public function getDriverKey(): string
    {
        return 'brevo';
    }

    public function validateConfig(array $config): array
    {
        $errors = [];
        if (empty($config['api_key'])) {
            $errors[] = 'api_key is required';
        }
        return $errors;
    }

    /**
     * Send a single transactional email via Brevo API.
     */
    public function send(array $message): array
    {
        $apiKey = $this->config['api_key'] ?? '';
        if (empty($apiKey)) {
            return $this->errorResult('Brevo api_key is not configured');
        }

        $payload = $this->buildPayload($message);

        $ch = curl_init(self::API_BASE . self::SEND_SINGLE);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'api-key: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return $this->errorResult('cURL error: ' . $curlErr);
        }

        $decoded = @json_decode($body, true);

        // Brevo returns 201 Created on success
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success'    => true,
                'message_id' => $decoded['messageId'] ?? null,
                'error'      => null,
                'raw'        => $decoded,
            ];
        }

        $errMsg = $decoded['message'] ?? ('HTTP ' . $httpCode . ': ' . $body);
        return $this->errorResult($errMsg, $decoded);
    }

    /**
     * Send a batch of messages.
     * Brevo's free-tier transactional endpoint is per-message,
     * so we loop. Paid accounts can use bulk campaign API instead.
     */
    public function sendBatch(array $messages): array
    {
        $results = [];
        foreach ($messages as $message) {
            $results[] = $this->send($message);
            // Polite delay to avoid rate-limit bursts (50 ms)
            usleep(50_000);
        }
        return $results;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function buildPayload(array $m): array
    {
        $toName  = $m['to_name'] ?? null;
        $toEntry = ['email' => $m['to_email']];
        if ($toName) $toEntry['name'] = $toName;

        $fromEmail = $m['from_email'] ?? ($this->config['default_from'] ?? '');
        $fromName  = $m['from_name']  ?? ($this->config['default_name'] ?? $fromEmail);

        $payload = [
            'sender'  => ['name' => $fromName, 'email' => $fromEmail],
            'to'      => [$toEntry],
            'subject' => $m['subject'],
        ];

        if (!empty($m['html']))  $payload['htmlContent'] = $m['html'];
        if (!empty($m['text']))  $payload['textContent'] = $m['text'];
        if (!empty($m['reply_to'])) {
            $payload['replyTo'] = ['email' => $m['reply_to']];
        }

        // Extra custom headers if provided
        if (!empty($m['headers']) && is_array($m['headers'])) {
            $payload['headers'] = $m['headers'];
        }

        return $payload;
    }

    private function errorResult(string $msg, $raw = null): array
    {
        return [
            'success'    => false,
            'message_id' => null,
            'error'      => $msg,
            'raw'        => $raw,
        ];
    }
}
