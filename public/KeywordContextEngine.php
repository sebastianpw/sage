<?php

require_once "AbstractContextEngine.php";

/**
 * V1 Implementation: Keyword/Tag Matching
 */
class KeywordContextEngine extends AbstractContextEngine {
    
    public function getRankedItems(?int $contextId): array {
        // 1. If no context, return standard date sort
        if (!$contextId) {
            $stmt = $this->pdo->query("
                SELECT sketch_id as id, 0 as score 
                FROM sketch_analysis 
                WHERE overall_quality > 0 
                ORDER BY analyzed_at DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 2. Fetch Context Tags
        $stmt = $this->pdo->prepare("SELECT entities, thematics FROM md_doc_analysis WHERE doc_id = ?");
        $stmt->execute([$contextId]);
        $docRow = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $tags = $this->extractTags($docRow);

        // 3. Fetch Searchable Data for ALL sketches
        $allSketches = $this->pdo->query("
            SELECT s.id, s.name, s.description, sa.entities, sa.thematics
            FROM sketches s
            JOIN sketch_analysis sa ON s.id = sa.sketch_id
            WHERE sa.overall_quality > 0
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 4. Score
        $scored = [];
        foreach($allSketches as $s) {
            $score = 0;
            $matches = [];
            $haystack = strtolower($s['name'] . ' ' . $s['description'] . ' ' . $s['entities'] . ' ' . $s['thematics']);

            foreach($tags as $tag) {
                if (strpos($haystack, $tag) !== false) {
                    $score++;
                    $matches[] = $tag;
                }
            }
            // Structure expected by Library
            $scored[] = [
                'id' => $s['id'],
                'score' => $score,
                'matches' => array_slice($matches, 0, 3)
            ];
        }

        // 5. Sort (Score DESC)
        usort($scored, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $scored;
    }

    private function extractTags($row) {
        $tags = [];
        if(!$row) return $tags;
        
        $ent = json_decode($row['entities'], true) ?? [];
        $them = json_decode($row['thematics'], true) ?? [];

        if (isset($ent['characters'])) foreach($ent['characters'] as $c) { if(isset($c['name'])) $tags[] = strtolower($c['name']); }
        if (isset($ent['locations'])) foreach($ent['locations'] as $l) { if(isset($l['name'])) $tags[] = strtolower($l['name']); }
        if (isset($them['mood'])) $tags[] = strtolower($them['mood']);
        if (isset($them['themes']) && is_array($them['themes'])) foreach($them['themes'] as $t) $tags[] = strtolower($t);
        
        return array_unique(array_filter($tags));
    }
}