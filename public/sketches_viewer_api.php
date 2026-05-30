<?php
// public/sketches_viewer_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require_once __DIR__ . '/entity_viewer_api.php'; 

use App\Core\PyApiVectorService;

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$page = max(1, (int)($_POST['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// COLLECTION CONFIG
$COLL_IMAGES   = 'sage_nu_images';
$COLL_SKETCHES = 'sage_sketches_nu';

try {
    if ($action === 'fetch') {
        
        $vectorService = new PyApiVectorService();
        
        $searchMode = ($_POST['search_mode'] ?? 'false') === 'true';
        $textQuery = $_POST['query'] ?? null;
        $imageFile = $_FILES['image']['tmp_name'] ?? null;

        $targetSketchIds = [];
        $isVectorSearch = false;

        // --- STRATEGY: SEARCH ---
        if ($searchMode) {
            $isVectorSearch = true;
            $results = [];

            // A. Image Search (Search Frames -> Get Sketches)
            if ($imageFile) {
                // Search Images Collection using CLIP Vision
                $chromaRes = $vectorService->query(null, $imageFile, $COLL_IMAGES, 'image', 20);
                
                if (!empty($chromaRes['result']['ids'][0])) {
                    $rawIds = $chromaRes['result']['ids'][0];
                    $distances = $chromaRes['result']['distances'][0] ?? [];
                    
                    $frameDistances = [];
                    foreach ($rawIds as $idx => $rid) {
                         $fid = (int)str_replace('frame_', '', $rid);
                         $frameDistances[$fid] = $distances[$idx] ?? 1.0;
                    }
                    
                    if (!empty($frameDistances)) {
                        $fids = implode(',', array_keys($frameDistances));
                        $sql = "
                            SELECT f.id as frame_id, map.to_id as sketch_id 
                            FROM frames f
                            JOIN frames_2_sketches map ON f.id = map.from_id
                            WHERE f.id IN ($fids)
                        ";
                        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($rows as $r) {
                            $sid = $r['sketch_id'];
                            $fid = $r['frame_id'];
                            if (!isset($targetSketchIds[$sid])) {
                                $targetSketchIds[$sid] = $frameDistances[$fid];
                            } else {
                                $targetSketchIds[$sid] = min($targetSketchIds[$sid], $frameDistances[$fid]);
                            }
                        }
                    }
                }
            }
            
            // B. Text Search (Search Sketches directly)
            elseif ($textQuery) {
                // Search Sketches Collection using MiniLM
                $chromaRes = $vectorService->query($textQuery, null, $COLL_SKETCHES, 'text', 20);
                
                if (!empty($chromaRes['result']['ids'][0])) {
                    $rawIds = $chromaRes['result']['ids'][0];
                    $distances = $chromaRes['result']['distances'][0] ?? [];
                    
                    foreach ($rawIds as $idx => $rid) {
                        if (preg_match('/sketch_(\d+)/', $rid, $m)) {
                            $sid = (int)$m[1];
                            $dist = $distances[$idx] ?? 1.0;
                            if (!isset($targetSketchIds[$sid])) {
                                $targetSketchIds[$sid] = $dist;
                            } else {
                                $targetSketchIds[$sid] = min($targetSketchIds[$sid], $dist);
                            }
                        }
                    }
                }
            }
            
            if (empty($targetSketchIds)) {
                echo json_encode(['ok' => true, 'items' => []]);
                exit;
            }
        }

        // --- STRATEGY: FETCH FROM DB ---
        
        $sketches = [];
        
        if ($isVectorSearch) {
            asort($targetSketchIds); 
            $orderedIds = array_keys($targetSketchIds);
            
            $pagedIds = array_slice($orderedIds, $offset, $limit);
            
            if (empty($pagedIds)) {
                echo json_encode(['ok' => true, 'items' => []]);
                exit;
            }

            $inIds = implode(',', $pagedIds);
            $orderBy = "FIELD(s.id, $inIds)";
            
            $sql = "
                SELECT s.id, s.name, s.description, s.created_at,
                       sa.overall_quality, sa.classification, sa.thematics
                FROM sketches s
                LEFT JOIN sketch_analysis sa ON s.id = sa.sketch_id
                WHERE s.id IN ($inIds)
                ORDER BY $orderBy
            ";
        } else {
            $sql = "
                SELECT s.id, s.name, s.description, s.created_at,
                       sa.overall_quality, sa.classification, sa.thematics
                FROM sketches s
                LEFT JOIN sketch_analysis sa ON s.id = sa.sketch_id
                ORDER BY s.id DESC
                LIMIT $limit OFFSET $offset
            ";
        }

        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $sid = $row['id'];
            
            // Fetch Frames
            $fSql = "
                SELECT f.id as frame_id, f.filename, f.prompt
                FROM frames f
                JOIN frames_2_sketches map ON f.id = map.from_id
                WHERE map.to_id = $sid
                ORDER BY f.id DESC
                LIMIT 10
            ";
            $frames = $pdo->query($fSql)->fetchAll(PDO::FETCH_ASSOC);
            
            $classif = json_decode($row['classification'] ?? '{}', true);
            $themes = json_decode($row['thematics'] ?? '{}', true);
            
            $sketches[] = [
                'id' => $sid,
                'name' => $row['name'],
                'description' => $row['description'],
                'quality' => (float)($row['overall_quality'] ?? 0),
                'narrative' => $classif['narrative_function'] ?? '',
                'themes' => $themes['primary_themes'] ?? [],
                'frames' => $frames,
                'relevance' => $isVectorSearch ? ($targetSketchIds[$sid] ?? 0) : null
            ];
        }

        echo json_encode(['ok' => true, 'items' => $sketches]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
