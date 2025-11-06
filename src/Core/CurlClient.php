<?php
declare(strict_types=1);

namespace App\Core;

class CurlClient
{
    private int $timeout;
    private int $connectTimeout;
    private ?string $userAgent;

    /**
     * @param int $timeout total seconds for request (CURLOPT_TIMEOUT)
     * @param int $connectTimeout seconds to establish connection (CURLOPT_CONNECTTIMEOUT)
     * @param string|null $userAgent optional User-Agent string
     */
    public function __construct(int $timeout = 300, int $connectTimeout = 30, ?string $userAgent = null)
    {
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
        $this->userAgent = $userAgent;
    }

    // simple setters/getters for global defaults
    public function setTimeout(int $seconds): void { $this->timeout = $seconds; }
    public function setConnectTimeout(int $seconds): void { $this->connectTimeout = $seconds; }
    public function setUserAgent(?string $ua): void { $this->userAgent = $ua; }
    public function getTimeout(): int { return $this->timeout; }
    public function getConnectTimeout(): int { return $this->connectTimeout; }

    /**
     * Make a HTTP request using native PHP cURL. Minimal wrapper.
     *
     * IMPORTANT: $headers (if provided) are forwarded **unchanged** to CURLOPT_HTTPHEADER,
     * exactly like your original one-line implementation:
     *     curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
     *
     * @param string $url
     * @param string $method e.g. 'GET', 'POST', 'PUT', 'DELETE', 'PATCH'
     * @param string|array|null $body value passed directly to CURLOPT_POSTFIELDS if set
     * @param array|null $headers numeric list of header strings OR associative key=>value — passed as-is
     * @param array $curlOptions associative map of CURLOPT_* constants => value (applied via curl_setopt)
     *                       Note: if CURLOPT_TIMEOUT or CURLOPT_CONNECTTIMEOUT are present here they override globals.
     * @return array ['body' => string|false, 'http_code' => int, 'curl_error' => string, 'info' => array]
     */
    public function request(
        string $url,
        string $method = 'GET',
        $body = null,
        ?array $headers = null,
        array $curlOptions = []
    ): array {
        $ch = curl_init();

        // Basic required options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Respect user-supplied timeouts in $curlOptions, otherwise use globals
        if (array_key_exists(CURLOPT_TIMEOUT, $curlOptions)) {
            curl_setopt($ch, CURLOPT_TIMEOUT, (int)$curlOptions[CURLOPT_TIMEOUT]);
            unset($curlOptions[CURLOPT_TIMEOUT]);
        } else {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        }

        if (array_key_exists(CURLOPT_CONNECTTIMEOUT, $curlOptions)) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$curlOptions[CURLOPT_CONNECTTIMEOUT]);
            unset($curlOptions[CURLOPT_CONNECTTIMEOUT]);
        } else {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        }

        // Optional user-agent
        if ($this->userAgent !== null) {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        }

        // Method & body (do not transform $body)
        $methodUpper = strtoupper($method);
        switch ($methodUpper) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                break;
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $methodUpper);
                if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                break;
            default:
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                break;
        }

        // HEADERS: pass them exactly as provided (one line, same behaviour as original)
        if ($headers !== null) {
            // preserve exactly what caller passed
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // Apply any other curl options the caller passed in directly:
        // (This allows callers to use any CURLOPT_* constant exactly as before.)
        foreach ($curlOptions as $opt => $val) {
            // we accept both numeric constants (CURLOPT_...) and string keys (rare)
            // but prefer to pass them directly as given
            curl_setopt($ch, (int)$opt, $val);
        }

        // Execute
        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $info = curl_getinfo($ch);

        curl_close($ch);

        return [
            'body' => $response,
            'http_code' => (int)$httpCode,
            'curl_error' => $curlErr ?: '',
            'info' => $info
        ];
    }

    /**
     * Streaming variant that writes chunks to $writeCallback.
     * This method also forwards headers and $curlOptions unchanged.
     *
     * @param string $url
     * @param callable $writeCallback function(string $chunk): void
     * @param string $method
     * @param string|array|null $body
     * @param array|null $headers
     * @param array $curlOptions
     * @return array ['http_code' => int, 'curl_error' => string, 'info' => array]
     */
    public function requestStream(
        string $url,
        callable $writeCallback,
        string $method = 'GET',
        $body = null,
        ?array $headers = null,
        array $curlOptions = []
    ): array {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);

        // timeouts
        if (array_key_exists(CURLOPT_TIMEOUT, $curlOptions)) {
            curl_setopt($ch, CURLOPT_TIMEOUT, (int)$curlOptions[CURLOPT_TIMEOUT]);
            unset($curlOptions[CURLOPT_TIMEOUT]);
        } else {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        }

        if (array_key_exists(CURLOPT_CONNECTTIMEOUT, $curlOptions)) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$curlOptions[CURLOPT_CONNECTTIMEOUT]);
            unset($curlOptions[CURLOPT_CONNECTTIMEOUT]);
        } else {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        }

        if ($this->userAgent !== null) {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        }

        // Method & body
        $methodUpper = strtoupper($method);
        if ($methodUpper === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif (in_array($methodUpper, ['PUT', 'PATCH', 'DELETE'], true)) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $methodUpper);
            if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        // Headers forwarded as-is
        if ($headers !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // let caller set other curl opts
        foreach ($curlOptions as $opt => $val) {
            curl_setopt($ch, (int)$opt, $val);
        }

        // streaming callbacks
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($writeCallback) {
            $writeCallback($data);
            return strlen($data);
        });

        $res = curl_exec($ch);
        $err = curl_errno($ch);
        $errStr = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        return [
            'http_code' => (int)($info['http_code'] ?? 0),
            'curl_error' => $errStr ?: '',
            'info' => $info
        ];
    }
}
