<?php
/**
 * SAGE Mail Hub — SMTP Provider
 * src/MailHub/Providers/SmtpProvider.php
 *
 * Generic SMTP delivery driver using PHP's mail() or a lightweight
 * socket-based sender — no Composer dependency required.
 * For Termux/Android environments without sendmail configured,
 * the socket sender is used directly.
 *
 * Config JSON keys:
 *   host         SMTP hostname (e.g. smtp.gmail.com)
 *   port         25 | 465 | 587
 *   encryption   '' | 'ssl' | 'tls'
 *   username     SMTP auth username (often the from address)
 *   password     SMTP auth password / app password
 *   default_from Sender address
 *   default_name Sender display name
 *   timeout      (optional, default 30s)
 */

namespace App\MailHub\Providers;

class SmtpProvider implements MailProviderInterface
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    // ── MailProviderInterface ─────────────────────────────────────────────

    public function getDriverKey(): string
    {
        return 'smtp';
    }

    public function validateConfig(array $config): array
    {
        $errors = [];
        if (empty($config['host']))         $errors[] = 'host is required';
        if (empty($config['default_from'])) $errors[] = 'default_from is required';
        return $errors;
    }

    public function send(array $message): array
    {
        $errors = $this->validateConfig($this->config);
        if (!empty($errors)) {
            return $this->errorResult('Config invalid: ' . implode(', ', $errors));
        }

        try {
            return $this->sendViaSockets($message);
        } catch (\Throwable $e) {
            return $this->errorResult($e->getMessage());
        }
    }

    public function sendBatch(array $messages): array
    {
        $results = [];
        foreach ($messages as $message) {
            $results[] = $this->send($message);
            usleep(50_000);
        }
        return $results;
    }

    // ── Socket SMTP implementation ────────────────────────────────────────

    private function sendViaSockets(array $m): array
    {
        $host       = $this->config['host'];
        $port       = (int)($this->config['port'] ?? 587);
        $encryption = strtolower($this->config['encryption'] ?? 'tls');
        $user       = $this->config['username'] ?? '';
        $pass       = $this->config['password'] ?? '';
        $timeout    = (int)($this->config['timeout'] ?? 30);

        $fromEmail  = $m['from_email'] ?? ($this->config['default_from'] ?? '');
        $fromName   = $m['from_name']  ?? ($this->config['default_name'] ?? $fromEmail);
        $toEmail    = $m['to_email'];
        $toName     = $m['to_name']    ?? null;
        $subject    = $m['subject'];
        $html       = $m['html']       ?? '';
        $text       = $m['text']       ?? strip_tags($html);

        $connHost = ($encryption === 'ssl') ? 'ssl://' . $host : $host;

        $sock = @fsockopen($connHost, $port, $errno, $errstr, $timeout);
        if (!$sock) {
            return $this->errorResult("Connection failed ({$errno}): {$errstr}");
        }
        stream_set_timeout($sock, $timeout);

        $read = function () use ($sock): string {
            $buf = '';
            while (!feof($sock)) {
                $line = fgets($sock, 1024);
                $buf .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            return $buf;
        };
        $cmd = function (string $c) use ($sock, $read): string {
            fputs($sock, $c . "\r\n");
            return $read();
        };

        $read(); // banner

        // EHLO
        $ehlo = $cmd('EHLO ' . (gethostname() ?: 'localhost'));

        // STARTTLS upgrade for port 587
        if ($encryption === 'tls') {
            $cmd('STARTTLS');
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($sock);
                return $this->errorResult('STARTTLS negotiation failed');
            }
            $cmd('EHLO ' . (gethostname() ?: 'localhost'));
        }

        // AUTH LOGIN
        if ($user !== '') {
            $resp = $cmd('AUTH LOGIN');
            $cmd(base64_encode($user));
            $resp = $cmd(base64_encode($pass));
            if (substr(trim($resp), 0, 3) !== '235') {
                fclose($sock);
                return $this->errorResult('SMTP AUTH failed: ' . trim($resp));
            }
        }

        // Envelope
        $cmd("MAIL FROM:<{$fromEmail}>");
        $cmd("RCPT TO:<{$toEmail}>");
        $cmd('DATA');

        // Build MIME message
        $msgId    = '<' . uniqid('mail', true) . '@mailhub>';
        $boundary = 'MH_' . md5(uniqid());
        $toHeader = $toName ? ('"' . addslashes($toName) . '" <' . $toEmail . '>') : $toEmail;
        $date     = date('r');

        $rawMsg = "Message-ID: {$msgId}\r\n"
                . "Date: {$date}\r\n"
                . "From: \"{$fromName}\" <{$fromEmail}>\r\n"
                . "To: {$toHeader}\r\n"
                . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n"
                . "\r\n"
                . "--{$boundary}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($text)) . "\r\n"
                . "--{$boundary}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($html)) . "\r\n"
                . "--{$boundary}--\r\n"
                . ".";

        $resp = $cmd($rawMsg);
        $cmd('QUIT');
        fclose($sock);

        if (substr(trim($resp), 0, 3) === '250') {
            return [
                'success'    => true,
                'message_id' => $msgId,
                'error'      => null,
                'raw'        => trim($resp),
            ];
        }

        return $this->errorResult('DATA rejection: ' . trim($resp), $resp);
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
