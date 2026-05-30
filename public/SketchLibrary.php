<?php
// SketchLibrary.php
// Handles fetching, formatting, and hydrating sketch data from the DB.
// Update: Added support for multiple frames per sketch and specific frame hydration.

class SketchLibrary {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Hydrates a specific page of results.
     * Fetches ALL associated frames (via direct link or frames_2_sketches) to allow browsing.
     */
    public function hydratePage(array $sortedItems, int $page, int $limit) {
        $totalItems = count($sortedItems);
        $totalPages = ceil($totalItems / $limit);
        $offset = ($page - 1) * $limit;
        $pageSlice = array_slice($sortedItems, $offset, $limit);

        if(empty($pageSlice)) {
            return ['data' => [], 'meta' => ['current_page'=>$page, 'total_pages'=>$totalPages]];
        }

        // Map IDs to preserve score/match data from the Context Engine
        $ids = array_column($pageSlice, 'id');
        $metaMap = [];
        foreach($pageSlice as $item) $metaMap[$item['id']] = $item;

        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        // Complex subquery to fetch JSON array of all related frames
        // Checks both frames.entity_id AND frames_2_sketches table
        $sql = "
            SELECT s.id, s.name, s.description, s.created_at, 
                   sa.overall_quality, sa.entities, sa.thematics, sa.classification, sa.scoring, sa.recommendations,
                   
                   -- Fetch all frames as JSON
                   (
                       SELECT JSON_ARRAYAGG(JSON_OBJECT('id', f.id, 'filename', f.filename))
                       FROM frames f
                       WHERE (f.entity_type='sketches' AND f.entity_id=s.id)
                          OR f.id IN (SELECT from_id FROM frames_2_sketches WHERE to_id=s.id)
                   ) as all_frames

            FROM sketches s
            JOIN sketch_analysis sa ON s.id = sa.sketch_id
            WHERE s.id IN ($placeholders)
              AND s.searchable = 1
            ORDER BY FIELD(s.id, " . implode(',', $ids) . ")";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach($rows as $r) {
            $formatted = $this->formatRow($r);
            
            // Re-attach score/match info
            $formatted['isMatch'] = ($metaMap[$r['id']]['score'] ?? 0) > 0;
            $formatted['matches'] = $metaMap[$r['id']]['matches'] ?? [];
            
            $results[] = $formatted;
        }

        return [
            'data' => $results, 
            'meta' => [
                'current_page' => $page, 
                'total_pages' => $totalPages, 
                'total_items' => $totalItems
            ]
        ];
    }

    /**
     * Hydrates a specific list of items.
     * Supports mixed input: simple IDs (int) OR objects ['sketch_id'=>1, 'frame_id'=>2]
     */
    public function hydrateSpecificIds(array $items) {
        if (empty($items)) return [];
        
        // 1. Extract Sketch IDs for the main query
        $sketchIds = [];
        $frameOverrides = []; // Map sketch_id => frame_id (if specific)

        foreach ($items as $item) {
            if (is_array($item) && isset($item['sketch_id'])) {
                $sid = (int)$item['sketch_id'];
                $sketchIds[] = $sid;
                if (!empty($item['frame_id'])) {
                    $frameOverrides[$sid] = (int)$item['frame_id'];
                }
            } elseif (is_numeric($item)) {
                $sketchIds[] = (int)$item;
            }
        }
        
        $sketchIds = array_unique($sketchIds);
        if (empty($sketchIds)) return [];

        $placeholders = str_repeat('?,', count($sketchIds) - 1) . '?';
        
        // 2. Fetch Sketches
        $sql = "
            SELECT s.id, s.name, s.description, s.created_at, 
                   sa.overall_quality, sa.entities, sa.thematics, sa.classification, sa.scoring, sa.recommendations,
                   (
                       SELECT JSON_ARRAYAGG(JSON_OBJECT('id', f.id, 'filename', f.filename))
                       FROM frames f
                       WHERE (f.entity_type='sketches' AND f.entity_id=s.id)
                          OR f.id IN (SELECT from_id FROM frames_2_sketches WHERE to_id=s.id)
                   ) as all_frames
            FROM sketches s
            JOIN sketch_analysis sa ON s.id = sa.sketch_id
            WHERE s.id IN ($placeholders)
              AND s.searchable = 1
            -- We sort by the input order later in PHP because inputs might contain duplicates of the same sketch
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($sketchIds));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Index rows by ID
        $rowMap = [];
        foreach ($rows as $r) {
            $rowMap[$r['id']] = $this->formatRow($r);
        }

        // 3. Build Final Result based on ORIGINAL input order
        $results = [];
        foreach ($items as $item) {
            $sid = is_array($item) ? ($item['sketch_id'] ?? 0) : (int)$item;
            $fid = is_array($item) ? ($item['frame_id'] ?? null) : null;
            
            if (isset($rowMap[$sid])) {
                $entry = $rowMap[$sid]; // Copy data
                
                // If a specific frame was requested, swap the thumb
                if ($fid && !empty($entry['frames'])) {
                    foreach ($entry['frames'] as $fr) {
                        if ($fr['id'] == $fid) {
                            $entry['thumb'] = $fr['filename'];
                            $entry['active_frame_id'] = $fr['id'];
                            break;
                        }
                    }
                }
                
                $results[] = $entry;
            }
        }
        
        return $results;
    }

    /**
     * Standardizes the JSON output.
     */
    private function formatRow($row) {
        $searchable = strtolower($row['name'] . ' ' . $row['description']);
        $ent = json_decode($row['entities'] ?? '{}', true);
        if($ent) $searchable .= ' ' . strtolower(json_encode($ent));
        
        $curation = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'created_at' => $row['created_at'],
            'score' => (float)$row['overall_quality'],
            'class' => json_decode($row['classification'] ?? '{}', true),
            'score_breakdown' => json_decode($row['scoring'] ?? '{}', true),
            'entities' => $ent,
            'themes' => json_decode($row['thematics'] ?? '{}', true),
            'recs' => json_decode($row['recommendations'] ?? '{}', true),
            'show' => []
        ];

        // Process Frames
        $frames = !empty($row['all_frames']) ? json_decode($row['all_frames'], true) : [];
        if (!is_array($frames)) $frames = [];
        
        // Sort frames by ID descending (newest first)
        usort($frames, function($a, $b) { return $b['id'] - $a['id']; });

        $thumb = '/placeholder.png';
        $activeFrameId = null;
        if (!empty($frames)) {
            $thumb = $frames[0]['filename'];
            $activeFrameId = $frames[0]['id'];
        }

        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'desc' => $row['description'],
            'thumb' => $thumb,
            'frames' => $frames, // Pass all frames to frontend
            'active_frame_id' => $activeFrameId,
            'quality' => (float)$row['overall_quality'],
            'search_blob' => $searchable,
            'curation' => $curation 
        ];
    }
}
