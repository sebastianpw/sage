<?php
// public/narrative_sequencer_v2_api.php
// Neural API Handler for V2 Sequencer
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// Include Logic Classes
require_once __DIR__ . '/AbstractContextEngine.php'; 
require_once __DIR__ . '/../src/Core/VectorContextEngine.php'; // The New Brain
require_once __DIR__ . '/SketchLibrary.php';
require_once __DIR__ . '/SequenceManager.php';

use App\Core\VectorContextEngine;

// Initialize Engines
$engine = new VectorContextEngine($pdo);
$library = new SketchLibrary($pdo);
$seqManager = new SequenceManager($pdo);

header('Content-Type: application/json');

// --- ROUTER ---

if (isset($_GET['action'])) {
    try {
        switch ($_GET['action']) {
            
            // 1. NEURAL FETCH
            case 'fetch_library':
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $contextId = isset($_GET['context_id']) && $_GET['context_id'] !== '' ? (int)$_GET['context_id'] : null;
                
                // A. Vector Search (The Heavy Lifting)
                $rankedItems = $engine->getRankedItems($contextId);
                
                // B. Pagination & Hydration (Standard)
                // We show 50 items per page from the ranked list
                $result = $library->hydratePage($rankedItems, $page, 50);
                
                echo json_encode(['status' => 'success'] + $result);
                break;

            // 2. HYDRATE SAVED SEQUENCE
            case 'hydrate_sequence':
                $idsStr = $_GET['ids'] ?? '';
                $ids = array_filter(array_map('intval', explode(',', $idsStr)));
                
                $data = $library->hydrateSpecificIds($ids);
                echo json_encode(['status'=>'success', 'data'=>$data]);
                break;
        }
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// 3. SAVE SEQUENCE (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_sequence') {
    try {
        $ids = json_decode($_POST['sketch_ids'] ?? '[]', true);
        $docId = !empty($_POST['linked_doc_id']) ? $_POST['linked_doc_id'] : null;
        $seqId = !empty($_POST['sequence_id']) ? $_POST['sequence_id'] : null;
        
        $newId = $seqManager->save($_POST['name'], $_POST['description'], $ids, $docId, $seqId);
        
        echo json_encode(['status' => 'success', 'id' => $newId]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}
