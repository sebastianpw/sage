<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once __DIR__ . '/../src/Continuity/ContinuityPrep.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

ini_set('display_errors', '0');

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    $prep = new \App\Continuity\ContinuityPrep($pdo);

    // ── Auto-increment / next-ID status for sketches, characters, frames, narrative_sequences ──
    if ($action === 'get_auto_increments') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'statuses' => $prep->getAllTableStatuses()]);
        exit;
    }

    // ── Autocomplete searches ──
    if ($action === 'search_map_runs') {
        header('Content-Type: application/json; charset=utf-8');
        $q = trim($input['q'] ?? '');
        if ($q === '') {
            $stmt = $pdo->query("SELECT id, note, created_at FROM map_runs WHERE entity_type = 'sketches' ORDER BY id DESC LIMIT 20");
        } else {
            $stmt = $pdo->prepare("SELECT id, note, created_at FROM map_runs WHERE entity_type = 'sketches' AND (note LIKE ? OR id = ?) ORDER BY id DESC LIMIT 20");
            $stmt->execute(["%$q%", (int)$q]);
        }
        echo json_encode(['ok' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'search_sequences') {
        header('Content-Type: application/json; charset=utf-8');
        $q = trim($input['q'] ?? '');
        if ($q === '') {
            $stmt = $pdo->query("SELECT id, name FROM narrative_sequences ORDER BY id DESC LIMIT 20");
        } else {
            $stmt = $pdo->prepare("SELECT id, name FROM narrative_sequences WHERE name LIKE ? OR id = ? ORDER BY id DESC LIMIT 20");
            $stmt->execute(["%$q%", (int)$q]);
        }
        echo json_encode(['ok' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── Analyze explicit ID bounds ──
    if ($action === 'analyze_range') {
        header('Content-Type: application/json; charset=utf-8');
        $table   = (string)($input['table'] ?? '');
        $fromId  = (int)($input['from_id'] ?? 0);
        $toId    = (int)($input['to_id'] ?? 0);

        if (!in_array($table, ['sketches', 'characters'], true)) {
            echo json_encode(['ok' => false, 'error' => 'table must be "sketches" or "characters"']);
            exit;
        }
        if ($fromId <= 0 || $toId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'from_id and to_id are required and must be positive integers']);
            exit;
        }

        echo json_encode(['ok' => true, 'result' => $prep->analyzeRange($table, $fromId, $toId)]);
        exit;
    }

    // ── Analyze Collections (Map Runs & Sequences) ──
    if ($action === 'analyze_map_run') {
        header('Content-Type: application/json; charset=utf-8');
        $mapRunId = (int)($input['map_run_id'] ?? 0);
        echo json_encode(['ok' => true, 'result' => $prep->analyzeMapRun($mapRunId)]);
        exit;
    }

    if ($action === 'analyze_sequence') {
        header('Content-Type: application/json; charset=utf-8');
        $seqId = (int)($input['seq_id'] ?? 0);
        echo json_encode(['ok' => true, 'result' => $prep->analyzeSequence($seqId)]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);

} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}