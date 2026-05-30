<?php
// src/Core/VectorContextEngine.php
namespace App\Core;

require_once __DIR__ . '/AbstractContextEngine.php';

use PDO;
use Exception;

class VectorContextEngine extends AbstractContextEngine {

    private $vectorService;
    private $collectionName = 'sage_sketches_nu';
    public $lastQueryPrompt = "";

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
        $this->vectorService = new PyApiVectorService();
    }


    // ============================================================
    // SINGLE QUERY — unchanged, used by all existing callers
    // ============================================================

    public function getRankedItems(?int $contextId, ?string $customQuery = null): array {

        $queryText = "";

        if ($customQuery && trim($customQuery) !== '') {
            $queryText = $customQuery;
        } elseif ($contextId) {
            $stmt = $this->pdo->prepare("SELECT summary, thematics FROM md_doc_analysis WHERE doc_id = ?");
            $stmt->execute([$contextId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $queryText = $row['summary'] ?? "";
                $them = json_decode($row['thematics'] ?? '{}', true);
                if (!empty($them['mood']))   $queryText .= " Mood: " . $them['mood'] . ".";
                if (!empty($them['themes']) && is_array($them['themes'])) {
                    $queryText .= " Themes: " . implode(", ", $them['themes']) . ".";
                }
            }
        }

        $this->lastQueryPrompt = $queryText;

        if (empty($queryText)) {
            return [];
        }

        return $this->runVectorQuery($queryText, 300);
    }


    // ============================================================
    // MULTI QUERY — union of per-item queries with score merging
    // ============================================================
    // Accepts an array of query strings (one per filter item).
    // Runs each query separately, merges results by sketch ID.
    // When the same sketch appears in multiple query results,
    // its score is boosted (max + small bonus per extra appearance)
    // so sketches relevant to multiple selected items rank higher.
    // Returns the same format as getRankedItems().
    // ============================================================

    public function getRankedItemsMulti(?int $contextId, array $queries): array {

        if (empty($queries)) {
            return [];
        }

        // Single query — no need for union logic
        if (count($queries) === 1) {
            return $this->getRankedItems($contextId, $queries[0]);
        }

        // How many results to request per query.
        // More queries = fewer per query, but union fills the gap.
        // Cap at 300 per query (same as single query default).
        $nPerQuery = min(300, (int)ceil(400 / count($queries)));

        $scoreMap    = []; // sketch_id => best score seen
        $hitCount    = []; // sketch_id => how many queries it appeared in
        $matchesMap  = []; // sketch_id => match labels

        foreach ($queries as $idx => $queryText) {
            if (empty(trim($queryText))) continue;

            $this->lastQueryPrompt = $queryText;

            $results = $this->runVectorQuery($queryText, $nPerQuery);

            foreach ($results as $r) {
                $id    = $r['id'];
                $score = $r['score'];

                if (!isset($scoreMap[$id])) {
                    $scoreMap[$id]   = $score;
                    $hitCount[$id]   = 1;
                    $matchesMap[$id] = $r['matches'];
                } else {
                    // Keep best score, boost for multi-query relevance
                    $scoreMap[$id] = max($scoreMap[$id], $score);
                    $hitCount[$id]++;
                }
            }
        }

        // Apply multi-hit bonus: +0.05 per additional query a sketch appeared in
        // (capped at +0.20 so a sketch appearing in all 4 queries gets +0.15 bonus)
        $merged = [];
        foreach ($scoreMap as $id => $score) {
            $bonus    = min(0.20, ($hitCount[$id] - 1) * 0.05);
            $merged[] = [
                'id'      => $id,
                'score'   => min(1.0, $score + $bonus),
                'matches' => $matchesMap[$id],
            ];
        }

        // Sort by score descending
        usort($merged, fn($a, $b) => $b['score'] <=> $a['score']);

        return $merged;
    }


    // ============================================================
    // INTERNAL: run a single vector query and parse results
    // ============================================================

    private function runVectorQuery(string $queryText, int $nResults): array {
        try {
            $response = $this->vectorService->query(
                $queryText,
                null,
                $this->collectionName,
                'text',
                $nResults
            );
        } catch (Exception $e) {
            error_log("VectorContextEngine Error: " . $e->getMessage());
            return [];
        }

        $scored = [];

        if (!empty($response['result']['ids'][0])) {
            $ids       = $response['result']['ids'][0];
            $distances = $response['result']['distances'][0] ?? [];

            foreach ($ids as $index => $rawId) {
                $cleanId = 0;

                if (preg_match('/sketch_(\d+)/', $rawId, $m)) {
                    $cleanId = (int)$m[1];
                } elseif (is_numeric($rawId)) {
                    $cleanId = (int)$rawId;
                }

                if ($cleanId > 0) {
                    $dist    = $distances[$index] ?? 1.0;
                    $score   = max(0, 1 - $dist);
                    $scored[] = [
                        'id'      => $cleanId,
                        'score'   => $score,
                        'matches' => ['Semantic Match'],
                    ];
                }
            }
        }

        return $scored;
    }
}
