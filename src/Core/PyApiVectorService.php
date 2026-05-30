<?php
namespace App\Core;

use CURLFile;
use Exception;

/**
 * PyApiVectorService
 *
 * V2 Changes:
 * - query() now accepts optional $where array for metadata filtering.
 *   Passes filter to /chroma/query_json endpoint (JSON body, not form).
 * - queryJson() new dedicated method using /chroma/query_json for
 *   complex where filters — avoids form encoding issues with nested arrays.
 * - cleanIds() updated: no longer parses ID strings to extract sketch_id.
 *   Instead reads sketch_id from returned Chroma metadata, which survives
 *   chunking reliably. Falls back to ID string parsing if metadata absent.
 * - extractSketchIds() new helper: collapses chunked results to unique
 *   sketch_ids with their best distance score per sketch.
 */
class PyApiVectorService extends PyApiProxy
{

    public function getApiUrl() {
        $script = $this->spw->getProjectPath() . '/bash/pyapi_echo.sh';
        $apiUrl = trim(shell_exec('sh ' . escapeshellarg($script)));
        return $apiUrl;
    }

    public function __construct()
    {
        $this->spw = SpwBase::getInstance();

        $script = $this->spw->getProjectPath() . '/bash/pyapi_echo.sh';
        $apiUrl = trim(shell_exec('sh ' . escapeshellarg($script)));

        $this->apiUrl = $apiUrl !== ''
            ? rtrim($apiUrl, '/')
            : 'http://127.0.0.1:8009';
    }

    /**
     * Pings the Vector database PyAPI to check if it is online.
     * Hits the GET /ping endpoint which returns {"ok": true, "message": "pong"}
     */
    public function ping(): bool
    {
        $endpoint = $this->apiUrl . '/ping';
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Fast timeouts for health checks
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $decoded = json_decode($response, true);
            return isset($decoded['ok']) && $decoded['ok'] === true;
        }

        return false;
    }

    /**
     * Query the Vector DB (Form-based, backward compatible).
     *
     * Supports Text-to-Text, Text-to-Image (CLIP Text), Image-to-Image (CLIP Vision).
     *
     * @param string|null $text       The search query text
     * @param string|null $imagePath  Path to local image file for visual search
     * @param string $collection      Target collection name
     * @param string $modality        'text' or 'image'
     * @param int $nResults           Number of results to fetch
     * @param array $where            Optional metadata filter e.g. ['type' => 'primary']
     */
    public function query(
        ?string $text,
        ?string $imagePath,
        string $collection,
        string $modality = 'text',
        int $nResults = 20,
        array $where = []
    ): array {

        // If we have a where filter, route to the JSON endpoint for clean handling
        if (!empty($where) && $text && !$imagePath) {
            return $this->queryJson($text, $collection, $nResults, $modality, $where);
        }

        $endpoint = $this->apiUrl . '/chroma/query';

        $postData = [
            'n_results' => $nResults,
            'collection' => $collection,
            'modality' => $modality
        ];

        if ($text) {
            $postData['text'] = $text;
        }

        if (!empty($where)) {
            $postData['where'] = json_encode($where);
        }

        if ($imagePath && file_exists($imagePath)) {
            return $this->executeApiRequestWithImageJson($endpoint, $imagePath, $postData);
        }

        $response = $this->executeApiRequest($endpoint, $postData);
        return json_decode($response, true);
    }

    /**
     * Query using JSON body — supports complex where filters cleanly.
     *
     * Uses /chroma/query_json endpoint which accepts a JSON body
     * instead of form data. Preferred for where-filtered queries.
     *
     * @param string $text        Search query text
     * @param string $collection  Target collection name
     * @param int $nResults       Number of results
     * @param string $modality    'text' or 'image'
     * @param array $where        Metadata filter e.g. ['type' => 'primary'] or ['sketch_id' => 2718]
     */
    public function queryJson(
        string $text,
        string $collection,
        int $nResults = 20,
        string $modality = 'text',
        array $where = []
    ): array {
        $endpoint = $this->apiUrl . '/chroma/query_json';

        $payload = [
            'text'       => $text,
            'collection' => $collection,
            'n_results'  => $nResults,
            'modality'   => $modality,
        ];

        if (!empty($where)) {
            $payload['where'] = $where;
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['ok' => false, 'error' => $err];
        }

        return json_decode($response, true) ?: [];
    }

    /**
     * Extract unique sketch IDs from Chroma query results.
     *
     * Because add_text chunks documents, Chroma returns chunk-level IDs
     * like 'sketch_2718_primary_chunk_0'. This method reads sketch_id
     * from the returned metadata (reliable) and collapses all chunks
     * for the same sketch to a single entry, keeping the best (lowest) distance.
     *
     * Returns array of ['sketch_id' => int, 'type' => string, 'distance' => float]
     * sorted by distance ascending (best match first).
     *
     * @param array $chromaResult  Full Chroma query response array
     */
    public function extractSketchIds(array $chromaResult): array
    {
        $ids        = $chromaResult['result']['ids'][0]        ?? [];
        $metadatas  = $chromaResult['result']['metadatas'][0]  ?? [];
        $distances  = $chromaResult['result']['distances'][0]  ?? [];

        $seen = []; // sketch_id => best distance

        foreach ($ids as $i => $rawId) {
            $meta     = $metadatas[$i] ?? [];
            $distance = $distances[$i] ?? 1.0;

            // Prefer metadata sketch_id (survives chunking reliably)
            $sketchId = null;
            if (!empty($meta['sketch_id'])) {
                $sketchId = (int)$meta['sketch_id'];
            } elseif (!empty($meta['db_id'])) {
                $sketchId = (int)$meta['db_id'];
            } else {
                // Fallback: parse from ID string
                // Works for: sketch_2718_primary_chunk_0 → needs sketch_id from earlier segment
                if (preg_match('/^sketch_(\d+)_/', $rawId, $m)) {
                    $sketchId = (int)$m[1];
                }
            }

            if (!$sketchId) continue;

            $type = $meta['type'] ?? 'unknown';

            $key = $sketchId . '_' . $type;

            if (!isset($seen[$key]) || $distance < $seen[$key]['distance']) {
                $seen[$key] = [
                    'sketch_id' => $sketchId,
                    'type'      => $type,
                    'distance'  => $distance,
                ];
            }
        }

        $results = array_values($seen);

        // Sort by distance ascending (closest = best match)
        usort($results, fn($a, $b) => $a['distance'] <=> $b['distance']);

        return $results;
    }

    /**
     * Helper to clean IDs returned by Chroma (legacy, preserved for compatibility).
     *
     * WARNING: With chunked documents, this extracts the CHUNK index from IDs like
     * 'sketch_2718_primary_chunk_0' → returns 0 (wrong).
     *
     * Use extractSketchIds() instead for sketch queries.
     * This method is kept for frame/image queries where IDs are not chunked.
     */
    public function cleanIds(array $rawIds): array
    {
        $clean = [];
        foreach ($rawIds as $id) {
            if (preg_match('/^sketch_(\d+)_/', $id, $m)) {
                // New 3-vector format: extract sketch_id from second segment
                $clean[] = (int)$m[1];
            } elseif (preg_match('/_(\d+)$/', $id, $m)) {
                // Legacy format: sketch_123 or frame_456
                $clean[] = (int)$m[1];
            } elseif (is_numeric($id)) {
                $clean[] = (int)$id;
            }
        }
        return array_unique($clean);
    }
}