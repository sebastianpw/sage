<?php
// src/Services/WordnetApi.php
namespace App\Services;

class WordnetApi
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct(string $baseUrl = '')
    {
        // default pyapi URL — override by passing base URL or set WORDNET_PYAPI_URL in env_locals.php
        $this->baseUrl = $baseUrl ?: ($_ENV['WORDNET_PYAPI_URL'] ?? ($GLOBALS['WORDNET_PYAPI_URL'] ?? 'http://127.0.0.1:8009'));
        $this->timeout = 10;
    }

    protected function request(string $method, string $path, ?array $data = null)
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        $ch = curl_init();
        $headers = ['Accept: application/json'];
        if (in_array(strtoupper($method), ['POST','PUT','PATCH'])) {
            $payload = json_encode($data ?? []);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        } elseif ($data && strtoupper($method) === 'GET') {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            return ['ok' => false, 'status' => 0, 'error' => $err];
        }
        $decoded = json_decode($resp, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['ok' => false, 'status' => $code, 'error' => 'Invalid JSON from pyapi', 'raw' => $resp];
        }
        return ['ok' => true, 'status' => $code, 'data' => $decoded];
    }

    // convenience helpers:
    public function lemma(string $lemma)
    {
        return $this->request('GET', '/wordnet/lemma/' . rawurlencode($lemma));
    }

    public function synset(int $synsetid)
    {
        return $this->request('GET', '/wordnet/synset/' . (int)$synsetid);
    }

    public function search(string $q, int $limit = 50)
    {
        return $this->request('GET', '/wordnet/search', ['q' => $q, 'limit' => $limit]);
    }

    public function hypernyms(int $synsetid)
    {
        return $this->request('GET', '/wordnet/hypernyms/' . (int)$synsetid);
    }

    public function morph(string $m)
    {
        return $this->request('GET', '/wordnet/morph/' . rawurlencode($m));
    }
}
