<?php
namespace App\Wordnet;

/**
 * WordnetBridge
 *
 * Small wrapper for calling the pyapi WordNet service.
 * - Uses WORDNET_PYAPI_URL from env_locals.php (or defaults to http://127.0.0.1:8009)
 * - Simple file cache in PROJECT_ROOT/var/cache/wordnet
 * - Methods return associative arrays decoded from JSON
 *
 * The constructor now accepts any logger object (or null). It will call ->info(),
 * ->warning(), ->error() if those methods exist on the provided logger.
 */
class WordnetBridge
{
    private string $baseUrl;
    private $logger = null;
    private string $cacheDir;
    private int $ttl = 3600; // 1 hour default

    /**
     * @param mixed|null $logger Any logger object (e.g. App\Core\FileLogger) or null
     * @param string|null $baseUrl Optional override for pyapi base URL
     */
    public function __construct($logger = null, ?string $baseUrl = null)
    {
        $this->logger = $logger;

        // allow override; otherwise read global set in env_locals.php
        if ($baseUrl) {
            $this->baseUrl = rtrim($baseUrl, '/');
        } elseif (!empty($GLOBALS['WORDNET_PYAPI_URL'])) {
            $this->baseUrl = rtrim($GLOBALS['WORDNET_PYAPI_URL'], '/');
        } else {
            $this->baseUrl = 'http://127.0.0.1:8009';
        }

        $this->cacheDir = defined('PROJECT_ROOT') ? PROJECT_ROOT . '/var/cache/wordnet' : __DIR__ . '/../../var/cache/wordnet';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    // Public API wrappers -------------------------------------------------

    public function lemma(string $lemma): ?array
    {
        $key = 'lemma_' . md5(strtolower($lemma));
        $path = $this->cachePath($key);
        if ($cached = $this->readCache($path)) return $cached;

        $endpoint = $this->baseUrl . '/wordnet/lemma/' . rawurlencode($lemma);
        $res = $this->httpGet($endpoint);
        if ($res !== null) $this->writeCache($path, $res);
        return $res;
    }

    public function synset(int $synsetid): ?array
    {
        $key = 'synset_' . (int)$synsetid;
        $path = $this->cachePath($key);
        if ($cached = $this->readCache($path)) return $cached;

        $endpoint = $this->baseUrl . '/wordnet/synset/' . (int)$synsetid;
        $res = $this->httpGet($endpoint);
        if ($res !== null) $this->writeCache($path, $res);
        return $res;
    }

    public function search(string $q, int $limit = 50): ?array
    {
        $key = 'search_' . md5(strtolower($q) . "_$limit");
        $path = $this->cachePath($key);
        if ($cached = $this->readCache($path)) return $cached;

        $url = $this->baseUrl . '/wordnet/search?q=' . rawurlencode($q) . '&limit=' . (int)$limit;
        $res = $this->httpGet($url);
        if ($res !== null) $this->writeCache($path, $res);
        return $res;
    }

    public function hypernyms(int $synsetid): ?array
    {
        $key = 'hypernyms_' . (int)$synsetid;
        $path = $this->cachePath($key);
        if ($cached = $this->readCache($path)) return $cached;

        $url = $this->baseUrl . '/wordnet/hypernyms/' . (int)$synsetid;
        $res = $this->httpGet($url);
        if ($res !== null) $this->writeCache($path, $res);
        return $res;
    }

    // Debug / info
    public function debug(): ?array
    {
        $url = $this->baseUrl . '/wordnet/debug';
        return $this->httpGet($url);
    }

    // --- Helpers ---------------------------------------------------------

    private function cachePath(string $key): string
    {
        return $this->cacheDir . '/' . preg_replace('/[^a-z0-9_\-\.]/i', '_', $key) . '.json';
    }

    private function readCache(string $path): ?array
    {
        if (!file_exists($path)) return null;
        $stat = @stat($path);
        if ($stat === false) return null;
        if ((time() - $stat['mtime']) > $this->ttl) {
            @unlink($path);
            return null;
        }
        $contents = @file_get_contents($path);
        if ($contents === false) return null;
        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function writeCache(string $path, $data): void
    {
        @file_put_contents($path, json_encode($data));
    }

    /**
     * Safe http GET with basic error handling
     */
    private function httpGet(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        // optional: set header to identify
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'X-Spw-Source: php-bridge']);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($errno !== 0) {
            $this->log('error', "WordnetBridge curl error ({$errno}) fetching {$url}");
            return null;
        }
        if (!empty($info['http_code']) && $info['http_code'] >= 400) {
            $this->log('warning', "WordnetBridge HTTP {$info['http_code']} for {$url}");
            return null;
        }

        $decoded = json_decode($body, true);
        if ($decoded === null) {
            $this->log('warning', "WordnetBridge invalid JSON from {$url}");
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Try to call logger methods if present, otherwise fall back to error_log
     */
    private function log(string $level, string $message): void
    {
        // Normalize common levels to method names
        $method = strtolower($level);
        if ($this->logger && is_object($this->logger)) {
            // If logger has the direct method, call it
            if (method_exists($this->logger, $method)) {
                try {
                    $this->logger->{$method}($message);
                    return;
                } catch (\Throwable $e) {
                    // fallback to error_log
                }
            }
            // Some custom loggers may use log($level, $msg)
            if (method_exists($this->logger, 'log')) {
                try {
                    $this->logger->log($level, $message);
                    return;
                } catch (\Throwable $e) {
                    // fallback
                }
            }
        }

        // Default fallback
        error_log("[WordnetBridge][$level] " . $message);
    }
}
