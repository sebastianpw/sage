<?php
// public/view_sketch_migration.php
// =============================================================================
// SAGE Sketch Migration Forge
// Export sketches to a portable ZIP bundle; import ZIP bundles onto any
// SAGE instance with fresh IDs, no collisions.
// =============================================================================
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once __DIR__ . '/SketchMigExporter.php';
require_once __DIR__ . '/SketchMigImporter.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }

// ─── Staging directories ──────────────────────────────────────────────────────
$exportTmpDir  = PROJECT_ROOT . '/storage/sketchmig/exports/';
$importZipDir  = PROJECT_ROOT . '/storage/sketchmig/zips/';
$importXtDir   = PROJECT_ROOT . '/storage/sketchmig/extracted/';
foreach ([$exportTmpDir, $importZipDir, $importXtDir] as $d) {
    if (!is_dir($d)) mkdir($d, 0755, true);
}

// ─── DOWNLOAD HANDLER ────────────────────────────────────────────────────────
if (!empty($_GET['dl']) && !empty($_GET['file'])) {
    $file = basename($_GET['file']);
    $zipPath = $exportTmpDir . $file;
    if (file_exists($zipPath)) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        exit;
    }
    header("HTTP/1.0 404 Not Found");
    exit('ZIP not found');
}

// ─── AJAX dispatcher ─────────────────────────────────────────────────────────
if (!empty($_REQUEST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action   = $_REQUEST['action'];
    $exporter = new SketchMigExporter($pdo, $framesDir, $projectPath);

    // ── 1. GET MAP RUNS ──────────────────────────────────────────────────────
    if ($action === 'get_map_runs') {
        $limit  = max(1, min(200, (int)($_GET['limit'] ?? 50)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $search = trim($_GET['search'] ?? '');
        $where  = "entity_type = 'sketches'";
        $params = [];
        
        if ($search !== '') {
            $where .= " AND (note LIKE :q OR id = :id)";
            $params[':q']  = '%' . $search . '%';
            $params[':id'] = (int)$search;
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM map_runs WHERE $where");
        foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT *, (SELECT COUNT(*) FROM frames WHERE map_run_id = map_runs.id AND entity_type='sketches') as frame_count FROM map_runs WHERE $where ORDER BY id DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC), 'total' => $total]);
        exit;
    }

    // ── 2. GET ENTITIES (SKETCHES) ───────────────────────────────────────────
    if ($action === 'get_entities') {
        $limit  = max(1, min(200, (int)($_GET['limit'] ?? 50)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $search = trim($_GET['search'] ?? '');
        $sort   = $_GET['sort'] ?? 'id';
        $where  = "1=1";
        $params = [];

        if ($search !== '') {
            $where .= " AND (s.name LIKE :q OR s.id = :id OR s.description LIKE :q)";
            $params[':q']  = '%' . $search . '%';
            $params[':id'] = (int)$search;
        }

        $orderBy = "s.id DESC";
        if ($sort === 'latest_frame') {
            $orderBy = "(SELECT MAX(id) FROM frames WHERE entity_type='sketches' AND entity_id = s.id) DESC, s.id DESC";
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM sketches s WHERE $where");
        foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT s.*, 
                    (SELECT COUNT(*) FROM frames WHERE entity_type='sketches' AND entity_id=s.id) AS frame_count,
                    CASE WHEN sa.id IS NOT NULL THEN 1 ELSE 0 END AS has_analysis,
                    CASE WHEN ssa.id IS NOT NULL THEN 1 ELSE 0 END AS has_seq
             FROM sketches s
             LEFT JOIN sketch_analysis sa ON sa.sketch_id = s.id
             LEFT JOIN sketch_sequence_analysis ssa ON ssa.sketch_id = s.id
             WHERE $where
             ORDER BY $orderBy LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC), 'total' => $total]);
        exit;
    }

    // ── 3. GET FRAMES (For Map Run / Sketch / Direct Frames Mode) ────────────
    if ($action === 'get_frames') {
        $runId  = (int)($_GET['map_run_id'] ?? 0);
        $entId  = (int)($_GET['entity_id'] ?? 0);
        $limit  = max(1, min(200, (int)($_GET['limit'] ?? 50)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $search = trim($_GET['search'] ?? '');
        $sort   = $_GET['sort'] ?? 'id';
        
        $baseSql = "SELECT f.id as frame_id, f.filename, f.name, f.prompt, f.entity_id, 
                           s.name as entity_name,
                           (SELECT COUNT(*) FROM frames WHERE entity_type='sketches' AND entity_id = f.entity_id) as fc,
                           CASE WHEN EXISTS (SELECT 1 FROM animatics a WHERE a.img2img_frame_id = f.id) THEN 1 ELSE 0 END as is_imported,
                           CASE WHEN EXISTS (SELECT 1 FROM frame_enhancements fe WHERE fe.img2img_frame_id = f.id) THEN 1 ELSE 0 END as is_enhanced
                    FROM frames f
                    LEFT JOIN sketches s ON f.entity_id = s.id
                    WHERE f.entity_type = 'sketches'";
        $params = [];

        if ($runId > 0) {
            $sql = $baseSql . " AND f.map_run_id = ? ORDER BY f.id ASC";
            $params[] = $runId;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC), 'total' => 0]);
            exit;
        } 
        elseif ($entId > 0) {
            $sql = $baseSql . " AND f.entity_id = ? ORDER BY f.id DESC";
            $params[] = $entId;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC), 'total' => 0]);
            exit;
        } 
        else {
            $where = "f.entity_type = 'sketches'";
            if ($search !== '') {
                $where .= " AND (f.name LIKE :q OR f.id = :id OR s.name LIKE :q)";
                $params[':q']  = '%' . $search . '%';
                $params[':id'] = (int)$search;
            }

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM frames f LEFT JOIN sketches s ON f.entity_id = s.id WHERE $where");
            foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
            $countStmt->execute();
            $total = (int)$countStmt->fetchColumn();

            $orderBy = $sort === 'latest_frame' ? "f.entity_id DESC, f.id DESC" : "f.id DESC";
            $sql = $baseSql . " AND " . str_replace("f.entity_type = 'sketches' AND ", "", $where) . " ORDER BY $orderBy LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            
            echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC), 'total' => $total]);
            exit;
        }
    }

    // ── GET SKETCH IDS FOR MAP RUN (Helper) ──────────────────────────────────
    if ($action === 'get_run_sketch_ids') {
        $runId = (int)($_GET['map_run_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT DISTINCT entity_id FROM frames WHERE map_run_id = ? AND entity_type = 'sketches' AND entity_id IS NOT NULL");
        $stmt->execute([$runId]);
        $ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        
        $data = [];
        if (!empty($ids)) {
            $in = implode(',', $ids);
            $cStmt = $pdo->query("SELECT id, (SELECT COUNT(*) FROM frames_2_sketches WHERE to_id=sketches.id) as fc FROM sketches WHERE id IN ($in)");
            $data = $cStmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    // ── EXPORT: create bundle + ZIP ───────────────────────────────────────────
    if ($action === 'export_bundle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $ids       = array_map('intval', $body['sketch_ids'] ?? []);
        $label     = trim($body['label'] ?? 'Export ' . date('Y-m-d H:i'));
        $sourceDb  = $dbname;

        $ids = array_unique($ids);

        if (empty($ids)) {
            echo json_encode(['ok' => false, 'error' => 'No sketches selected.']);
            exit;
        }

        try {
            $bundleId  = $exporter->exportBundle($ids, $label, $sourceDb);
            $zipName   = 'sketchmig_bundle_' . $bundleId . '_' . date('Ymd_His') . '.zip';
            $zipPath   = $exportTmpDir . $zipName;
            $exporter->buildZip($bundleId, $zipPath);
            $downloadUrl = '?dl=1&file=' . urlencode($zipName);
            echo json_encode(['ok' => true, 'bundle_id' => $bundleId, 'zip_name' => $zipName, 'download_url' => $downloadUrl]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── UPLOAD CHUNK ──────────────────────────────────────────────────────────
    if ($action === 'upload_chunk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $chunkIndex  = (int)($_POST['chunk_index'] ?? 0);
        $totalChunks = (int)($_POST['total_chunks'] ?? 1);
        $uploadId    = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['upload_id'] ?? uniqid('up_'));
        $origName    = basename($_POST['original_filename'] ?? 'bundle.zip');

        if (empty($_FILES['zip_chunk']) || $_FILES['zip_chunk']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'error' => 'Upload error']);
            exit;
        }

        $chunkDir  = $importZipDir . $uploadId . '/';
        if (!is_dir($chunkDir)) mkdir($chunkDir, 0755, true);

        $chunkFile = $chunkDir . 'chunk_' . str_pad($chunkIndex, 5, '0', STR_PAD_LEFT);
        move_uploaded_file($_FILES['zip_chunk']['tmp_name'], $chunkFile);

        $arrived = count(glob($chunkDir . 'chunk_*'));
        if ($arrived >= $totalChunks) {
            $finalZip = $importZipDir . $uploadId . '_' . $origName;
            $fh = fopen($finalZip, 'wb');
            for ($i = 0; $i < $totalChunks; $i++) {
                $cf = $chunkDir . 'chunk_' . str_pad($i, 5, '0', STR_PAD_LEFT);
                fwrite($fh, file_get_contents($cf));
                unlink($cf);
            }
            fclose($fh);
            rmdir($chunkDir);

            echo json_encode(['ok' => true, 'assembled' => true, 'upload_id' => $uploadId, 'zip_path' => $finalZip, 'filename' => $origName]);
        } else {
            echo json_encode(['ok' => true, 'assembled' => false, 'received' => $arrived, 'total' => $totalChunks]);
        }
        exit;
    }

    // ── EXTRACT ZIP ───────────────────────────────────────────────────────────
    if ($action === 'extract_zip' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $uploadId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['upload_id'] ?? '');
        $filename = basename($body['filename'] ?? '');
        $zipPath  = $importZipDir . $uploadId . '_' . $filename;

        if (!file_exists($zipPath)) { echo json_encode(['ok' => false, 'error' => 'ZIP not found']); exit; }

        $extractDir = $importXtDir . $uploadId . '/';
        if (!is_dir($extractDir)) mkdir($extractDir, 0755, true);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) { echo json_encode(['ok' => false, 'error' => 'Cannot open ZIP']); exit; }
        $zip->extractTo($extractDir);
        $zip->close();

        if (!file_exists($extractDir . 'bundle.sql')) { echo json_encode(['ok' => false, 'error' => 'bundle.sql not found']); exit; }

        $meta = [];
        if (file_exists($extractDir . 'bundle_meta.json')) {
            $meta = json_decode(file_get_contents($extractDir . 'bundle_meta.json'), true) ?? [];
        }

        echo json_encode(['ok' => true, 'upload_id' => $uploadId, 'extract_dir' => $extractDir, 'meta' => $meta]);
        exit;
    }

    // ── INGEST SQL INTO STAGING TABLES ────────────────────────────────────────
    if ($action === 'ingest_sql' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $uploadId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['upload_id'] ?? '');
        $extractDir = $importXtDir . $uploadId . '/';
        $sqlFile    = $extractDir . 'bundle.sql';

        if (!file_exists($sqlFile)) { echo json_encode(['ok' => false, 'error' => 'bundle.sql not found']); exit; }

        try {
            $pdo->exec(file_get_contents($sqlFile));
        } catch (\PDOException $e) {
            echo json_encode(['ok' => false, 'error' => 'SQL error: ' . $e->getMessage()]);
            exit;
        }

        $stmt = $pdo->query("SELECT id, label, sketch_count, frame_count FROM sketchmig_bundle ORDER BY id DESC LIMIT 1");
        $bundle = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Update status and save the uploadId for resuming
        $pdo->prepare("UPDATE sketchmig_bundle SET status='pending', import_note=? WHERE id=?")
            ->execute(["staging_dir:" . $uploadId, $bundle['id']]);

        echo json_encode(['ok' => true, 'bundle_id' => $bundle['id'], 'label' => $bundle['label'], 'sketches' => $bundle['sketch_count'], 'frames' => $bundle['frame_count']]);
        exit;
    }

    // ── RUN IMPORT ────────────────────────────────────────────────────────────
    if ($action === 'run_import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $bundleId  = (int)($body['bundle_id'] ?? 0);
        $uploadId  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['upload_id'] ?? '');

        if (!$bundleId || !$uploadId) { echo json_encode(['ok' => false, 'error' => 'Bundle or upload id missing']); exit; }

        $importer = new SketchMigImporter($pdo, $mysqli, $framesDir, $framesDirRel, $importXtDir);
        $result = $importer->importBundle($bundleId, $uploadId);
        echo json_encode(['ok' => $result['success'], 'result' => $result]);
        exit;
    }

    // ── PURGE STAGING ─────────────────────────────────────────────────────────
    if ($action === 'purge_bundle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $bundleId = (int)($body['bundle_id'] ?? 0);
        if (!$bundleId) { echo json_encode(['ok' => false, 'error' => 'No bundle ID']); exit; }
        
        $importer = new SketchMigImporter($pdo, $mysqli, $framesDir, $framesDirRel, $importXtDir);
        $importer->purgeBundle($bundleId);
        
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── LIST BUNDLES ──────────────────────────────────────────────────────────
    if ($action === 'list_bundles') {
        $stmt = $pdo->query("SELECT id, label, source_db, sketch_count, frame_count, status, import_note, created_at, imported_at FROM sketchmig_bundle ORDER BY id DESC LIMIT 50");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['upload_id'] = null;
            if (preg_match('/staging_dir:([a-zA-Z0-9_\-]+)/', $row['import_note'] ?? '', $m)) {
                $row['upload_id'] = $m[1];
            }
        }
        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=0.8, viewport-fit=cover">
<title>Sketch Migration Forge — SAGE</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe-lightbox.umd.min.js"></script>
<script>
(function(){try{var t=localStorage.getItem('spw_theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}}());
</script>
<style>
/* ═══════════════════════════════ EXACT ENHANIMATICS TOKENS ══════════════════════════════ */
:root {
    --bg: #0a0a0f;
    --card: #111118;
    --border: #1e1e2e;
    --text: #e2e2f0;
    --text-muted: #555570;
    --purple: #8b5cf6; 
    --purple-dim: rgba(139, 92, 246, 0.1);
    --amber: #f59e0b;
    --amber-dim: rgba(245, 158, 11, 0.1);
    --red: #ef4444;
    --teal: #14b8a6;
    --teal-dim: rgba(20, 184, 166, 0.12);
}
[data-theme="light"] {
    --bg:         #f4f4f8;
    --card:       #ffffff;
    --border:     #d0d0e0;
    --text:       #1a1a2e;
    --text-muted: #888899;
}
*, *::before, *::after { box-sizing: border-box; }
html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }

::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

/* ─── LAYOUT ─────────────────────────────────────────────────────────── */
.eh-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }

/* ─── HEADER ─────────────────────────────────────────────────────────── */
.eh-header {
    flex-shrink: 0; padding: 0 16px; height: 50px;
    background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; z-index: 100;
}
.eh-title { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--text); display: flex; align-items: center; gap: 8px; }
.eh-title span { color: var(--amber); }
.eh-nav { display: flex; height: 100%; gap: 12px; align-items:center; }
.eh-nav button { background:transparent; border:none; color:var(--text-muted); cursor:pointer; font-size:16px; transition:color 0.2s;}
.eh-nav button:hover { color:var(--text); }
.eh-nav .active-tab { color: var(--amber); font-weight:bold; }

/* ─── EXPORT WORKSPACE (eh-layout style) ─────────────────────────────── */
#wsExport { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

/* Top Panel */
.eh-top-panel { flex-shrink: 0; max-height: 40vh; display: flex; flex-direction: column; border-bottom: 1px solid var(--border); background: var(--card); }
.mr-controls-row { display: flex; gap: 8px; padding: 8px 12px; border-bottom: 1px solid var(--border); align-items: center; background: var(--card); flex-wrap: wrap; }
.map-run-toggle {
    flex-shrink: 0; padding: 4px 10px; border-radius: 20px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.65rem; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.15s;
    text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;
}
.map-run-toggle.active {
    border-color: var(--teal); color: #000; background: var(--teal);
    box-shadow: 0 0 10px rgba(20, 184, 166, 0.4);
}
.mr-search-input { flex: 1; min-width: 0; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.8rem; }
.mr-search-input:focus { outline: none; border-color: var(--amber); }
.mr-pagination { display: flex; align-items: center; gap: 4px; }
.pg-btn { width: 26px; height: 26px; background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 3px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
.pg-btn:hover:not(:disabled) { border-color: var(--amber); color: var(--amber); }
.pg-input { width: 40px; text-align: center; background: var(--bg); border: 1px solid var(--border); color: var(--amber); border-radius: 3px; font-family: inherit; font-size: 0.75rem; font-weight: 700; padding: 4px 0; -moz-appearance: textfield; }
.pg-total { font-size: 0.7rem; color: var(--text-muted); padding: 0 4px; }

/* Sort Bar */
.sort-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; background: var(--card); padding: 4px 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
.sort-bar-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.8px; white-space: nowrap; flex-shrink: 0; }
.sort-btn {
    padding: 3px 9px; border-radius: 20px; font-size: 0.65rem; font-family: inherit;
    border: 1px solid var(--border); background: transparent; color: var(--text-muted);
    cursor: pointer; transition: all 0.15s; white-space: nowrap; display: flex; align-items: center; gap: 4px;
}
.sort-btn:hover { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }
.sort-btn.active { border-color: var(--amber); color: var(--text); background: var(--amber-dim); }

/* List Scroll */
.mr-list-scroll { overflow-y: auto; overflow-x: hidden; flex: 1; min-height: 60px; }
.mr-item { padding: 8px 12px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.15s; display: flex; align-items: center; gap: 10px; }
.mr-item:hover { background: rgba(255,255,255,0.05); }
.mr-item.active { background: var(--amber-dim); border-left: 3px solid var(--amber); padding-left: 9px; }
.mr-item.selected { background: rgba(245, 158, 11, 0.15); border-color: var(--amber); }
.mr-id { font-size: 0.7rem; font-weight: 700; color: var(--amber); min-width: 40px; }
.mr-item.selected .mr-id { color: #fff; }
.mr-note { font-size: 0.75rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; min-width: 0; }
.mr-meta { font-size: 0.65rem; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; display:flex; flex-direction:column; align-items:flex-end;}

.sk-item-check {
    width:16px;height:16px;border-radius:3px;border:1px solid var(--border);
    flex-shrink:0;display:flex;align-items:center;justify-content:center;
    font-size:10px;transition:all .12s; cursor: pointer; color: transparent; background: rgba(0,0,0,0.3);
}
.sk-item-check:hover { border-color: var(--amber); }
.mr-item.selected .sk-item-check { background:var(--amber);border-color:var(--amber);color:#000; }

/* Mid Panel (Grid Toolbar) */
.eh-mid-panel { flex-shrink: 0; background: var(--card); border-bottom: 1px solid var(--border); z-index: 5; }
.grid-toolbar { background: rgba(0,0,0,0.2); display: flex; flex-direction: column; }
.gt-row1 { padding: 6px 12px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.04); }
.gt-row2 { padding: 5px 12px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.gt-info { font-size: 0.7rem; color: var(--text-muted); }
.action-btn { padding: 4px 10px; border-radius: 3px; font-size: 0.65rem; font-weight: 700; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; text-transform: uppercase; font-family: inherit; }
.action-btn:hover { color: var(--amber); border-color: var(--amber); }
.action-btn.primary { border-color: var(--amber); color: var(--amber); }
.chk-label { display: flex; align-items: center; gap: 6px; font-size: 0.7rem; color: var(--text); cursor: pointer; user-select: none; }

/* Grid Area */
.eh-grid-area { flex: 1; overflow-y: auto; padding: 10px; position: relative; background: var(--bg); min-height: 0; }
.state-msg { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); font-size: 0.8rem; gap: 8px; }
.frames-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; padding-bottom: 20px; }
.frames-grid.one-col { grid-template-columns: 1fr; }
@media (min-width: 600px) { .frames-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); } }
.f-card { aspect-ratio: 1; background: #111; border: 2px solid var(--border); border-radius: 4px; position: relative; overflow: hidden; }
.f-card.selected { border-color: var(--text); box-shadow: 0 0 0 1px var(--text); }
.f-card.is-imported { border-color: #333; opacity: 0.25; filter: grayscale(80%); }
.f-card.is-imported::before { content: "IMPORTED"; position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 0.7rem; font-weight: 900; color: rgba(139, 92, 246, 0.6); transform: rotate(-15deg); pointer-events: none; z-index: 5; }
.f-card.is-imported.hidden-in-grid { display: none; }
.f-card.is-enhanced { border-color: rgba(245, 158, 11, 0.4); opacity: 0.4; filter: sepia(80%) hue-rotate(-10deg) saturate(1.5) brightness(0.6); }
.f-card.is-enhanced::after { content: "ENHANCED"; position: absolute; bottom: 35px; left: 0; right: 0; text-align: center; font-size: 0.65rem; font-weight: 800; color: rgba(245, 158, 11, 0.8); pointer-events: none; z-index: 5; text-shadow: 0 1px 2px #000; }
.f-card.is-enhanced.hidden-in-grid { display: none; }
.frames-grid.show-raw .f-card.is-imported, .frames-grid.show-raw .f-card.is-enhanced { opacity: 1; filter: none; border-color: var(--border); }
.frames-grid.show-raw .f-card.is-imported::before, .frames-grid.show-raw .f-card.is-enhanced::after { display: none; }
.f-link { display: block; width: 100%; height: calc(100% - 24px); overflow: hidden; cursor: zoom-in; }
.f-link img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.2s; }
.f-link:hover img { transform: scale(1.03); }
.f-view-btn { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; opacity: 0; transition: all 0.2s; font-size: 14px; }
.f-card:hover .f-view-btn { opacity: 1; }
.f-view-btn:hover { background: var(--text); border-color: var(--text); color: #000; }
.f-label { position: absolute; bottom: 0; left: 0; right: 0; height: 24px; background: rgba(20,20,25,0.95); padding: 0 6px; font-size: 0.65rem; color: #aaa; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; z-index: 2; }
.f-card.selected .f-label { background: rgba(255,255,255,0.1); color: #fff; border-top-color: #fff; }
.f-select-trigger { width: 18px; height: 18px; border: 1px solid #555; border-radius: 3px; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); font-size: 0; }
.f-card.selected .f-select-trigger { background: #fff; border-color: #fff; color: #000; font-size: 10px; font-weight: 900; }
.f-card.selected .f-select-trigger::after { content: '✓'; }

/* Footer */
.eh-footer {
    flex-shrink: 0; padding: 10px 16px; 
    padding-bottom: max(10px, env(safe-area-inset-bottom));
    background: var(--card); border-top: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    z-index: 10; position: relative; flex-wrap: wrap;
}
.ft-summary { font-size: 0.75rem; color: var(--text-muted); }
.ft-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; align-items:center; }
.btn-action { padding: 8px 12px; border-radius: 4px; border: none; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; cursor: pointer; font-family: inherit; transition: filter 0.15s; color: #fff; min-width: 0; white-space: nowrap; }
.btn-action:disabled { opacity: 0.5; cursor: not-allowed; background: var(--border) !important; color: #888 !important; }
.btn-amber { background: var(--amber); color: #000; }

/* ─── IMPORT WORKSPACE ───────────────────────────────────────────────── */
#wsImport { flex: 1; overflow-y: auto; padding: 16px; display: none; background: var(--bg); }
.import-card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 14px; }
.import-card-title { font-size: 0.95rem; font-weight: 700; color: var(--text); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.drop-zone { border: 2px dashed var(--border); border-radius: 8px; padding: 32px 20px; text-align: center; background: rgba(255,255,255,0.02); cursor: pointer; transition: all 0.2s; }
.drop-zone:hover { border-color: var(--amber); background: rgba(245, 158, 11, 0.05); }
.drop-zone-icon { font-size: 2.5rem; margin-bottom: 10px; opacity: 0.5; }
.drop-zone-text { font-size: 0.88rem; color: var(--text); font-weight: 600; }

/* Utilities */
.view-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 100000; display: none; align-items: center; justify-content: center; }
.view-modal.active { display: flex; }
.view-modal-content { width: 95vw; height: 95vh; background: #000; position: relative; border: 1px solid var(--border); box-shadow: 0 0 30px rgba(0,0,0,0.5); }
.view-close { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: rgba(0,0,0,0.8); color: #fff; border: 1px solid #444; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; z-index: 200; transition: all 0.2s; }
.view-close:hover { background: #fff; color: #000; }
iframe.frame-viewer { width: 100%; height: 100%; border: none; }

.prog-wrap{ height:6px;border-radius:3px;background:var(--border); overflow:hidden;margin:10px 0; }
.prog-fill{ height:100%;background:var(--amber);border-radius:3px; transition:width .2s;width:0%; }
.log-box{ background:#000;border:1px solid var(--border); border-radius:4px;padding:12px; font-family:var(--mono);font-size:.73rem;color:#7aff8e; line-height:1.7;white-space:pre-wrap;word-break:break-word; max-height:280px;overflow-y:auto;min-height:80px; }
.log-line-err{color:var(--red);} .log-line-warn{color:var(--amber);}

#toastRoot{ position:fixed;bottom:18px;right:16px;z-index:9999; display:flex;flex-direction:column;gap:7px;pointer-events:none; }
.toast{ pointer-events:all;padding:9px 14px;border-radius:4px; background:var(--card);border:1px solid var(--border); font-family:var(--mono);font-size:.78rem;color:var(--text); box-shadow:0 4px 20px rgba(0,0,0,.5); display:flex;align-items:center;gap:8px; animation:toastIn .2s ease;max-width:300px;cursor:pointer; }
.toast.success{border-color:var(--teal);} .toast.error{border-color:var(--red);color:var(--red);} .toast.info{border-color:var(--amber);}
.toast.out{animation:toastOut .2s ease forwards;}
@keyframes toastIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
@keyframes toastOut{to{opacity:0;transform:translateY(8px)}}
</style>
</head>
<body>

<div class="eh-layout">

    <!-- ═══ HEADER ══════════════════════════════════════════════════════════════ -->
    <div class="eh-header">
        <div class="eh-title">
            <span><i class="bi bi-box-arrow-right"></i></span> 
            Sketch Mig
        </div>
        <div class="eh-nav">
            <button class="active-tab" id="tabExport" onclick="SMF.switchTab('export')">Export</button>
            <button id="tabImport" onclick="SMF.switchTab('import')">Import</button>
            <div style="width:1px; height:20px; background:var(--border); margin:0 4px;"></div>
            <button onclick="SMF.refreshBundles()" title="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
            <button onclick="SMF.toggleTheme()" title="Toggle theme"><i class="bi bi-circle-half"></i></button>
            <a href="/dashboard.php" style="color:var(--text-muted); text-decoration:none;"><i class="bi bi-house"></i></a>
        </div>
    </div>

    <!-- ═══ EXPORT WORKSPACE ════════════════════════════════════════════════════ -->
    <div id="wsExport">
        
        <!-- TOP PANEL (List) -->
        <div class="eh-top-panel">
            <div class="mr-controls-row">
                <button id="btnListModeRuns" class="map-run-toggle active" onclick="SMF.switchListMode('runs')">Map Runs</button>
                <button id="btnListModeSketches" class="map-run-toggle" onclick="SMF.switchListMode('entities')">Sketches</button>
                <button id="btnListModeFrames" class="map-run-toggle" onclick="SMF.switchListMode('frames')">Frames</button>
                
                <input type="text" class="mr-search-input" id="sidebarSearch" placeholder="Search..." autocomplete="off">
                
                <div class="mr-pagination">
                    <button class="pg-btn" id="btnPrev" onclick="SMF.prevPage()">‹</button>
                    <input type="number" class="pg-input" id="pageInput" value="1" onchange="SMF.jumpToPage()">
                    <span class="pg-total" id="totalPages">/ 1</span>
                    <button class="pg-btn" id="btnNext" onclick="SMF.nextPage()">›</button>
                </div>
            </div>
            
            <div class="sort-bar" id="entitySortBar" style="display:none;">
                <span class="sort-bar-label"><i class="bi bi-sort-down"></i> Sort</span>
                <button class="sort-btn active" id="esort_id" onclick="SMF.setSort('id')"><i class="bi bi-hash"></i> ID</button>
                <button class="sort-btn" id="esort_latest_frame" onclick="SMF.setSort('latest_frame')"><i class="bi bi-images"></i> Latest Frame</button>
                <div style="flex:1;"></div>
                <button id="btnSelectAllList" class="sort-btn" style="display:none; color:var(--text);" onclick="SMF.selectAllInList()">[Sel. All]</button>
            </div>
            
            <div class="mr-list-scroll" id="sidebarList">
                <div style="text-align:center;padding:30px;color:var(--text-muted);font-size:.78rem;">Loading…</div>
            </div>
        </div>

        <!-- MID PANEL (Grid Toolbar) -->
        <div class="eh-mid-panel">
            <div class="grid-toolbar">
                <div class="gt-row1">
                    <div class="gt-info" id="gridInfo">Select an item above</div>
                    <div class="gt-actions" id="gridActions" style="display:flex; gap:8px;">
                        <button class="action-btn" onclick="SMF.selectNoneInGrid()">None</button>
                        <button class="action-btn primary" style="border-color:var(--text); color:var(--text);" onclick="SMF.selectAllInGrid()">All</button>
                    </div>
                </div>
                <div class="gt-row2">
                    <label class="chk-label" title="Hide frames imported to Animatics">
                        <input type="checkbox" id="hideImported" onchange="SMF.applyGridFilters()" style="accent-color: var(--blue);"> HideImp
                    </label>
                    <label class="chk-label" title="Hide frames already enhanced">
                        <input type="checkbox" id="hideEnhanced" onchange="SMF.applyGridFilters()" style="accent-color: var(--amber);"> HideEnh
                    </label>
                    <label class="chk-label" title="Show all frames without opacity/darkness indicators">
                        <input type="checkbox" id="showRaw" onchange="SMF.applyGridFilters()" style="accent-color: #4ade80;"> Raw
                    </label>
                    <label class="chk-label" title="Show one column grid for larger images">
                        <input type="checkbox" id="oneColGrid" onchange="SMF.toggleGridCols()" style="accent-color: var(--teal);"> 1Col
                    </label>
                </div>
            </div>
        </div>

        <!-- GRID AREA -->
        <div class="eh-grid-area">
            <div class="state-msg" id="gridState"><div>↑ Select an Item</div></div>
            <div class="frames-grid pswp-gallery" id="framesGrid" style="display:none;"></div>
        </div>

        <!-- FOOTER -->
        <div class="eh-footer">
            <div class="ft-summary">
                <span id="sumSketches" style="color:var(--amber); font-weight:bold;">0</span> sketches selected 
                <span style="opacity:0.6;">(~<span id="sumFrames">0</span> frames)</span>
                <div id="exportStatus" style="display:none; font-size:0.7rem; margin-top:4px; color:var(--amber);">
                    <span id="exportMsg"></span> <span id="exportProg"></span>
                </div>
            </div>
            <div class="ft-actions">
                <input type="text" id="exportLabel" placeholder="Bundle Label..." style="padding:6px 10px; border-radius:4px; border:1px solid var(--border); background:var(--bg); color:var(--text); font-family:inherit; font-size:0.75rem; width:180px;">
                <button class="btn-action btn-amber" id="btnExport" disabled onclick="SMF.startExport()">Export Bundle</button>
            </div>
        </div>
    </div>

    <!-- ═══ IMPORT WORKSPACE ════════════════════════════════════════════════════ -->
    <div id="wsImport">
        
        <div class="import-card">
            <div class="import-card-title"><i class="bi bi-clock-history" style="color:var(--amber);"></i> History & Purge</div>
            <div id="bundleList" style="margin-top:10px;">
                <div style="color:var(--text-muted);font-size:.78rem;">Loading…</div>
            </div>
        </div>

        <div class="import-card" id="cardUpload">
            <div class="import-card-title"><i class="bi bi-box-arrow-in-down-right" style="color:var(--teal);"></i> Step 1: Upload ZIP</div>
            <div class="drop-zone" id="dropZone" onclick="document.getElementById('zipInput').click()">
                <div class="drop-zone-icon">📦</div>
                <div class="drop-zone-text">Click or drag a .zip bundle here</div>
                <div class="drop-zone-sub">sketchmig_bundle_*.zip</div>
            </div>
            <input type="file" id="zipInput" accept=".zip" style="display:none;">

            <div id="uploadProgress" style="display:none;margin-top:14px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px; font-size:0.75rem;">
                    <span style="color:var(--text-muted);" id="uploadFileName"></span>
                    <span style="color:var(--amber);" id="uploadPct">0%</span>
                </div>
                <div class="prog-wrap"><div class="prog-fill" id="uploadProg"></div></div>
            </div>
        </div>

        <div class="import-card" id="cardVerify" style="display:none;">
            <div class="import-card-title">Step 2: Bundle Preview</div>
            <div id="bundlePreview" style="font-size:0.8rem; line-height:1.8; color:var(--text-muted);"></div>
            <div style="margin-top:14px;">
                <button class="btn-action" style="background:var(--teal); color:#000;" id="btnIngestSql" onclick="SMF.ingestSql()">Stage SQL Data</button>
            </div>
        </div>

        <div class="import-card" id="cardRunImport" style="display:none;">
            <div class="import-card-title">Step 3: Run Import</div>
            <div id="importBundleInfo" style="font-size:0.8rem; line-height:1.8; color:var(--text-muted); margin-bottom:14px;"></div>
            <button class="btn-action btn-amber" id="btnRunImport" onclick="SMF.runImport()">Execute Import</button>
            
            <div id="importProgressWrap" style="display:none;margin-top:14px;">
                <div class="prog-wrap"><div class="prog-fill" id="importProg" style="background:var(--teal);"></div></div>
            </div>
            <div id="importLog" class="log-box" style="margin-top:14px;display:none;"></div>
        </div>

        <div class="import-card" id="cardPurge" style="display:none;">
            <div class="import-card-title">Step 4: Purge Staging</div>
            <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:14px;">
                Import successful. Remove the <code style="color:var(--amber);">sketchmig_*</code> staging rows for this bundle to keep the DB clean.
            </p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button class="btn-action" style="background:var(--red); color:#fff;" id="btnPurge" onclick="SMF.purgeBundle()">Purge Staging Rows</button>
                <button class="btn-action" style="background:transparent; border:1px solid var(--border); color:var(--text);" onclick="SMF.resetImport()">Import Another</button>
            </div>
        </div>
    </div>

</div><!-- /eh-layout -->

<div id="toastRoot"></div>

<!-- Frame viewer Modal -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="SMF.closeFrameModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>

<script>
const SMF = (() => {
'use strict';

const API = window.location.pathname;
const CHUNK_SIZE = 1 * 1024 * 1024; // 1 MB chunks

// ─── STATE ────────────────────────────────────────────────────────────────
let _tab       = 'export';
let _listMode  = 'runs'; // 'runs' | 'entities' | 'frames'
let _sort      = 'id';   // 'id' | 'latest_frame'
let _page      = 1;
let _perPage   = 30;
let _totalPages= 1;
let _search    = '';
let _searchTmo = null;
let _items     = [];
let _previewId = null;
let _previewType = null; // 'run' | 'entity'
let _currentFrames = [];

// Export Selection
let _selected  = new Set(); // holds sketch IDs
let _selFrames = {};        // sketchId => frameCount

// Import state
let _imp = { uploadId: null, filename: null, meta: null, bundleId: null };

// ─── INIT ─────────────────────────────────────────────────────────────────
async function init() {
    // Auto-generate Export Label
    const now = new Date();
    const pad = n => n.toString().padStart(2, '0');
    document.getElementById('exportLabel').value = `Export ${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}`;

    loadList(_page);
    bindSearch();
    bindDropZone();
    bindZipInput();
}

// ─── TAB SWITCHING ────────────────────────────────────────────────────────
function switchTab(tab) {
    _tab = tab;
    document.getElementById('tabExport').classList.toggle('active-tab', tab === 'export');
    document.getElementById('tabImport').classList.toggle('active-tab', tab === 'import');
    document.getElementById('wsExport').style.display  = tab === 'export' ? 'flex' : 'none';
    document.getElementById('wsImport').style.display  = tab === 'import' ? 'block' : 'none';
    if (tab === 'import') refreshBundles();
}

function toggleTheme() {
    const cur = document.documentElement.getAttribute('data-theme') || 'dark';
    const nxt = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', nxt);
    try { localStorage.setItem('spw_theme', nxt); } catch(e){}
}

// ─── SIDEBAR LIST (Runs / Entities / Frames) ──────────────────────────────
function switchListMode(mode) {
    _listMode = mode;
    document.getElementById('btnListModeRuns').classList.toggle('active', mode === 'runs');
    document.getElementById('btnListModeSketches').classList.toggle('active', mode === 'entities');
    document.getElementById('btnListModeFrames').classList.toggle('active', mode === 'frames');
    
    document.getElementById('entitySortBar').style.display = (mode === 'entities' || mode === 'frames') ? 'flex' : 'none';
    document.getElementById('btnSelectAllList').style.display = mode === 'entities' ? 'block' : 'none';
    
    if (mode === 'runs') document.getElementById('sidebarSearch').placeholder = 'Search map runs...';
    else if (mode === 'entities') document.getElementById('sidebarSearch').placeholder = 'Search sketches...';
    else document.getElementById('sidebarSearch').placeholder = 'Search frame names/IDs...';
    
    document.getElementById('sidebarSearch').value = '';
    _search = '';
    _page = 1;
    loadList(_page);
}

function setSort(sort) {
    _sort = sort;
    document.getElementById('esort_id').classList.toggle('active', sort === 'id');
    document.getElementById('esort_latest_frame').classList.toggle('active', sort === 'latest_frame');
    _page = 1;
    loadList(_page);
}

function bindSearch() {
    document.getElementById('sidebarSearch').addEventListener('input', e => {
        clearTimeout(_searchTmo);
        _searchTmo = setTimeout(() => {
            _search = e.target.value.trim();
            _page   = 1;
            loadList(_page);
        }, 300);
    });
}

function prevPage() { if (_page > 1) { loadList(_page - 1); } }
function nextPage() { if (_page < _totalPages) { loadList(_page + 1); } }
function jumpToPage() { const v = parseInt(document.getElementById('pageInput').value); if (v >= 1 && v <= _totalPages) loadList(v); }

async function loadList(page) {
    const container = document.getElementById('sidebarList');
    
    if (_listMode === 'frames') {
        container.style.display = 'none';
        document.getElementById('gridState').style.display = 'none';
        document.getElementById('gridInfo').innerHTML = 'Frames Mode (Global)';
        
        const grid = document.getElementById('framesGrid');
        grid.style.display = 'grid';
        grid.innerHTML = '<div style="padding:20px; color:var(--text-muted); grid-column: 1/-1;">Loading frames...</div>';
        
        const offset = (page - 1) * _perPage;
        const url = `?action=get_frames&search=${encodeURIComponent(_search)}&limit=${_perPage}&offset=${offset}&sort=${_sort}`;
        
        try {
            const res = await get(url);
            if (res.ok) {
                _currentFrames = res.data;
                _page = page;
                _totalPages = Math.ceil(res.total / _perPage) || 1;
                updatePagerUI();
                renderGrid();
            } else {
                grid.innerHTML = '<div style="padding:20px; color:var(--red); grid-column: 1/-1;">Error loading frames.</div>';
            }
        } catch (e) {
            grid.innerHTML = '<div style="padding:20px; color:var(--red); grid-column: 1/-1;">Network error.</div>';
        }
        return;
    }

    container.style.display = 'block';
    container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted);font-size:.78rem;">Loading…</div>';
    
    const offset = (page - 1) * _perPage;
    const action = _listMode === 'runs' ? 'get_map_runs' : 'get_entities';
    const url = `?action=${action}&search=${encodeURIComponent(_search)}&limit=${_perPage}&offset=${offset}&sort=${_sort}`;
    
    try {
        const res = await get(url);
        if (!res.ok) { toast('Failed to load list', 'error'); return; }
        
        _items = res.data;
        _page = page;
        _totalPages = Math.ceil(res.total / _perPage) || 1;
        updatePagerUI();
        renderList();
    } catch (e) {
        toast('Network error loading list', 'error');
    }
}

function updatePagerUI() {
    document.getElementById('pageInput').value = _page;
    document.getElementById('totalPages').textContent = `/ ${_totalPages}`;
    document.getElementById('btnPrev').disabled = _page <= 1;
    document.getElementById('btnNext').disabled = _page >= _totalPages;
}

function renderList() {
    const container = document.getElementById('sidebarList');
    if (_items.length === 0) {
        container.innerHTML = `<div style="text-align:center;padding:30px;color:var(--text-muted);font-size:.76rem;">No items found</div>`;
        return;
    }

    container.innerHTML = _items.map(item => {
        const isPreview = (_previewId === item.id && _previewType === _listMode);
        
        if (_listMode === 'runs') {
            return `
            <div class="mr-item${isPreview ? ' active' : ''}" data-id="${item.id}" onclick="SMF.previewFrames('run', ${item.id}, 'Map Run #${item.id}')">
                <div class="mr-id">#${item.id}</div>
                <div class="mr-note">${esc(item.note || 'No note')}</div>
                <div class="mr-meta">
                    <div>${item.frame_count} frames</div>
                    <button class="action-btn" style="padding:2px 6px; font-size:0.6rem; margin-top:2px;" onclick="SMF.addAllSketchesFromRun(${item.id}, event)" title="Select all sketches in this map run for export">[+ All]</button>
                </div>
            </div>`;
        } else {
            const sel = _selected.has(item.id);
            return `
            <div class="mr-item${sel ? ' selected' : ''}${isPreview ? ' active' : ''}" data-id="${item.id}" onclick="SMF.previewFrames('entity', ${item.id}, '${esc(item.name)}')">
                <div class="sk-item-check" onclick="SMF.toggleSelect(${item.id}, ${item.frame_count}, event)">✓</div>
                <div class="mr-id">#${item.id}</div>
                <div class="mr-note">${esc(item.name)}</div>
                <div class="mr-meta">${item.frame_count} frames</div>
            </div>`;
        }
    }).join('');
    updateSelBadge();
}

function toggleSelect(sketchId, frameCount, event) {
    if (event) event.stopPropagation();
    if (!sketchId) return;
    
    if (_selected.has(sketchId)) { 
        _selected.delete(sketchId); 
        delete _selFrames[sketchId]; 
    } else { 
        _selected.add(sketchId); 
        _selFrames[sketchId] = frameCount || 0; 
    }
    
    if (_listMode === 'entities') renderList();
    renderGrid(); 
    updateExportSummary();
}

function selectAllInList() {
    if (_listMode !== 'entities') return;
    let count = 0;
    _items.forEach(item => {
        if (!_selected.has(item.id)) {
            _selected.add(item.id);
            _selFrames[item.id] = item.frame_count || 0;
            count++;
        }
    });
    if (count > 0) {
        toast(`Added ${count} sketch(es)`, 'success');
        updateExportSummary();
        renderList();
        renderGrid();
    }
}

function selectNone() {
    _selected.clear();
    _selFrames = {};
    if (_listMode === 'entities') renderList();
    renderGrid();
    updateExportSummary();
}

async function addAllSketchesFromRun(runId, event) {
    if (event) event.stopPropagation();
    const btn = event.currentTarget;
    btn.disabled = true;
    btn.textContent = '...';
    try {
        const res = await get(`?action=get_run_sketch_ids&map_run_id=${runId}`);
        if (res.ok && res.data.length > 0) {
            res.data.forEach(sk => {
                _selected.add(sk.id);
                _selFrames[sk.id] = sk.fc;
            });
            toast(`Added ${res.data.length} sketches to bundle`, 'success');
            updateExportSummary();
            renderGrid();
        } else {
            toast('No valid sketches found in this run', 'info');
        }
    } catch(e) {
        toast('Error loading sketches', 'error');
    }
    btn.disabled = false;
    btn.textContent = '[+ All]';
}

function updateSelBadge() {
    // Left for legacy compatibility, badge removed from header to match new UI
}
function updateExportSummary() {
    document.getElementById('sumSketches').textContent = _selected.size;
    const totalFrames = Object.values(_selFrames).reduce((a,b)=>a+b,0);
    document.getElementById('sumFrames').textContent = totalFrames;
    document.getElementById('btnExport').disabled = _selected.size === 0;
}

// ─── FRAME PREVIEW GRID (EXACT ENHANIMATICISM PORT) ───────────────────────
async function previewFrames(type, id, titleStr) {
    _previewId = id;
    _previewType = type;
    document.querySelectorAll('.mr-item').forEach(el => el.classList.remove('active'));
    const row = document.querySelector(`.mr-item[data-id="${id}"]`);
    if (row) row.classList.add('active');

    document.getElementById('gridState').style.display = 'none';
    document.getElementById('gridInfo').textContent = titleStr;
    const grid = document.getElementById('framesGrid');
    grid.style.display = 'grid';
    grid.innerHTML = '<div style="padding:20px; color:var(--text-muted); grid-column: 1/-1;">Loading frames...</div>';

    const url = type === 'run' ? `?action=get_frames&map_run_id=${id}` : `?action=get_frames&entity_id=${id}`;
    
    try {
        const res = await get(url);
        if (res.ok) {
            _currentFrames = res.data;
            renderGrid();
        } else {
            grid.innerHTML = '<div style="padding:20px; color:var(--red); grid-column: 1/-1;">Error loading frames.</div>';
        }
    } catch(e) {
        grid.innerHTML = '<div style="padding:20px; color:var(--red); grid-column: 1/-1;">Network error loading frames.</div>';
    }
}

function renderGrid() {
    const grid = document.getElementById('framesGrid');
    grid.innerHTML = '';
    
    const hideImp = document.getElementById('hideImported') ? document.getElementById('hideImported').checked : false;
    const hideEnh = document.getElementById('hideEnhanced') ? document.getElementById('hideEnhanced').checked : false;
    const showRaw = document.getElementById('showRaw') ? document.getElementById('showRaw').checked : false;
    
    grid.classList.toggle('show-raw', showRaw);

    if (_currentFrames.length === 0) {
        grid.innerHTML = '<div style="padding:20px; color:var(--text-muted); grid-column: 1/-1;">No frames found.</div>';
        return;
    }

    _currentFrames.forEach(f => {
        const isImp = parseInt(f.is_imported) === 1;
        const isEnh = parseInt(f.is_enhanced) === 1;
        
        const card = document.createElement('div');
        card.className = 'f-card';
        if (isImp) card.classList.add('is-imported');
        if (isEnh) card.classList.add('is-enhanced');
        if ((isImp && hideImp) || (isEnh && hideEnh)) card.classList.add('hidden-in-grid');
        
        const sel = _selected.has(f.entity_id);
        if (sel) card.classList.add('selected');
        
        card.dataset.fid = f.frame_id;
        card.dataset.entityId = f.entity_id;
        card.dataset.imported = isImp ? "1" : "0";
        card.dataset.enhanced = isEnh ? "1" : "0";

        const path = f.filename.startsWith('/') ? f.filename : '/' + f.filename;

        const link = document.createElement('a');
        link.className = 'f-link'; 
        link.href = path;
        link.target = '_blank';
        link.dataset.pswpWidth = 1024; link.dataset.pswpHeight = 1024;

        const img = document.createElement('img');
        img.src = path;
        img.loading = 'lazy';
        img.onload = function() { link.dataset.pswpWidth = this.naturalWidth; link.dataset.pswpHeight = this.naturalHeight; };
        link.appendChild(img);

        const viewBtn = document.createElement('div');
        viewBtn.className = 'f-view-btn';
        viewBtn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
        viewBtn.title = "View Frame Details Context";
        viewBtn.onclick = (e) => { e.stopPropagation(); e.preventDefault(); openFrameModal(f.frame_id); };

        const label = document.createElement('div');
        label.className = 'f-label';
        label.title = "Add underlying sketch to export bundle";
        
        const labelText = (_listMode === 'frames' || _listMode === 'runs') 
            ? `<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:70%;">${esc(f.entity_name || '#'+f.frame_id)}</span>`
            : `<span>#${f.frame_id}</span>`;
            
        label.innerHTML = labelText + `<div class="f-select-trigger"></div>`;
        label.onclick = (e) => { e.preventDefault(); toggleSelect(f.entity_id, f.fc, e); };

        card.appendChild(link);
        card.appendChild(viewBtn);
        card.appendChild(label);
        grid.appendChild(card);
    });

    try {
        if (typeof PhotoSwipeLightbox !== 'undefined') {
            if (window.pswpLightbox) { window.pswpLightbox.destroy(); }
            window.pswpLightbox = new PhotoSwipeLightbox({ gallery: '#framesGrid', children: 'a.f-link', pswpModule: PhotoSwipe });
            window.pswpLightbox.init();
        }
    } catch(e) {
        console.error("PhotoSwipe init failed:", e);
    }
}

function selectAllInGrid() {
    let count = 0;
    _currentFrames.forEach(f => {
        if (f.entity_id && !_selected.has(f.entity_id)) {
            _selected.add(f.entity_id);
            _selFrames[f.entity_id] = f.fc || 0;
            count++;
        }
    });
    if (count > 0) {
        toast(`Added ${count} sketch(es) from visible frames`, 'success');
        updateExportSummary();
        renderGrid();
        if (_listMode === 'entities') renderList();
    }
}

function selectNoneInGrid() {
    let count = 0;
    _currentFrames.forEach(f => {
        if (f.entity_id && _selected.has(f.entity_id)) {
            _selected.delete(f.entity_id);
            delete _selFrames[f.entity_id];
            count++;
        }
    });
    if (count > 0) {
        toast(`Removed ${count} sketch(es)`, 'info');
        updateExportSummary();
        renderGrid();
        if (_listMode === 'entities') renderList();
    }
}

function toggleGridCols() {
    const isOneCol = document.getElementById('oneColGrid').checked;
    document.getElementById('framesGrid').classList.toggle('one-col', isOneCol);
}

function applyGridFilters() {
    const hideImp = document.getElementById('hideImported').checked;
    const hideEnh = document.getElementById('hideEnhanced').checked;
    const showRaw = document.getElementById('showRaw').checked;
    
    document.getElementById('framesGrid').classList.toggle('show-raw', showRaw);
    
    document.querySelectorAll('.f-card').forEach(c => {
        const isImp = c.dataset.imported === "1";
        const isEnh = c.dataset.enhanced === "1";
        if ((isImp && hideImp) || (isEnh && hideEnh)) {
            c.classList.add('hidden-in-grid');
        } else { 
            c.classList.remove('hidden-in-grid'); 
        }
    });
}

function openFrameModal(id) {
    document.getElementById('frameViewer').src = `view_frame.php?frame_id=${id}&view=modal`;
    document.getElementById('viewModal').classList.add('active');
}

function closeFrameModal() {
    document.getElementById('viewModal').classList.remove('active');
    setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
}

// ─── EXPORT ───────────────────────────────────────────────────────────────
async function startExport() {
    if (_selected.size === 0) { toast('Select at least one sketch', 'error'); return; }

    const label = document.getElementById('exportLabel').value.trim();
    const btn   = document.getElementById('btnExport');
    btn.disabled = true;

    setExportStatus(true, 'Creating bundle…', '20%');

    try {
        const res = await post('?action=export_bundle', {
            sketch_ids: [..._selected],
            label,
        });

        if (!res.ok) throw new Error(res.error || 'Export failed');

        setExportStatus(true, 'Building ZIP…', '60%');
        await delay(300);
        setExportStatus(true, 'Downloading…', '100%');

        const a = document.createElement('a');
        a.href = API + '?dl=1&file=' + encodeURIComponent(res.zip_name);
        a.download = res.zip_name;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);

        toast('ZIP download started', 'success');
        setTimeout(() => setExportStatus(false), 2000);
    } catch(e) {
        toast(e.message, 'error');
        setExportStatus(false);
    }
    btn.disabled = false;
}

function setExportStatus(visible, msg, pct) {
    document.getElementById('exportStatus').style.display = visible ? 'block' : 'none';
    if (msg) document.getElementById('exportMsg').textContent = msg;
    if (pct != null) document.getElementById('exportProg').textContent = pct;
}

// ─── BUNDLE LISTS (Import Tab Only) ───────────────────────────────────────
async function refreshBundles() {
    if (_tab !== 'import') return;
    const res = await get('?action=list_bundles');
    if (res.ok) refreshBundleList(res.data);
}

function refreshBundleList(data) {
    const container = document.getElementById('bundleList');
    if (!data.length) {
        container.innerHTML = `<div style="color:var(--text-muted);font-size:.76rem;text-align:center;">No bundles found.</div>`;
        return;
    }
    container.innerHTML = data.map(b => {
        let sc = '';
        if (b.status === 'imported') sc = 'color:var(--teal);';
        if (b.status === 'failed') sc = 'color:var(--red);';

        let actions = '';
        if (b.upload_id && b.status !== 'imported') {
            actions += `<button class="action-btn" style="border-color:var(--amber); color:var(--amber); margin-left:8px;" onclick="SMF.resumeImport(${b.id}, '${b.upload_id}', '${esc(b.label)}')"><i class="bi bi-play-fill"></i> Resume</button>`;
        }
        actions += `<button class="action-btn" style="border-color:var(--red); color:var(--red); margin-left:8px;" onclick="SMF.resumePurge(${b.id})"><i class="bi bi-trash"></i> Purge</button>`;

        return `
        <div style="padding:8px 12px; border:1px solid rgba(255,255,255,0.05); margin-bottom:6px; border-radius:4px; background:rgba(0,0,0,0.2); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <div>
                <div style="font-weight:bold; font-size:0.85rem; color:var(--text);">${esc(b.label)}</div>
                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px;">
                    <span style="${sc}">${b.status.toUpperCase()}</span> | 
                    ${b.sketch_count} sk / ${b.frame_count} fr
                    ${b.source_db ? `| from: ${esc(b.source_db)}` : ''}
                </div>
            </div>
            <div style="display:flex; align-items:center;">
                ${actions}
            </div>
        </div>`;
    }).join('');
}

// ─── IMPORT: DROP ZONE ────────────────────────────────────────────────────
function bindDropZone() {
    const dz = document.getElementById('dropZone');
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-over'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
    dz.addEventListener('drop', e => {
        e.preventDefault();
        dz.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files[0]) handleZipFile(files[0]);
    });
}
function bindZipInput() {
    document.getElementById('zipInput').addEventListener('change', e => {
        if (e.target.files[0]) handleZipFile(e.target.files[0]);
        e.target.value = '';
    });
}

async function handleZipFile(file) {
    if (!file.name.endsWith('.zip')) { toast('Please select a .zip file', 'error'); return; }

    _imp = { uploadId: 'up_' + Date.now(), filename: file.name, meta: null, bundleId: null };

    document.getElementById('uploadProgress').style.display = '';
    document.getElementById('uploadFileName').textContent = file.name;

    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    let assembled = false;

    for (let i = 0; i < totalChunks; i++) {
        const chunk = file.slice(i * CHUNK_SIZE, (i + 1) * CHUNK_SIZE);
        const fd = new FormData();
        fd.append('action', 'upload_chunk');
        fd.append('zip_chunk', chunk, file.name);
        fd.append('chunk_index', i);
        fd.append('total_chunks', totalChunks);
        fd.append('upload_id', _imp.uploadId);
        fd.append('original_filename', file.name);

        const pct = Math.round(((i + 1) / totalChunks) * 100);
        document.getElementById('uploadPct').textContent = pct + '%';
        document.getElementById('uploadProg').style.width = pct + '%';

        const res = await fetch(API, { method: 'POST', body: fd }).then(r => r.json());
        if (!res.ok) { toast('Upload chunk error: ' + (res.error || '?'), 'error'); return; }
        if (res.assembled) assembled = true;
    }

    if (!assembled) { toast('Upload incomplete.', 'error'); return; }
    toast('ZIP uploaded — extracting…', 'info');
    setStep(2);

    const extRes = await post('?action=extract_zip', { upload_id: _imp.uploadId, filename: _imp.filename });
    if (!extRes.ok) { toast('Extract error: ' + extRes.error, 'error'); return; }

    _imp.meta = extRes.meta;
    showBundlePreview(extRes.meta);
    document.getElementById('cardVerify').style.display = 'block';
    setStep(2, 'active');
}

function showBundlePreview(meta) {
    const prev = document.getElementById('bundlePreview');
    if (!meta || !meta.label) {
        prev.innerHTML = `<div>ZIP contents: Valid</div>`;
        return;
    }
    prev.innerHTML = `
    <div><strong>Label:</strong> ${esc(meta.label)}</div>
    <div><strong>Source DB:</strong> ${esc(meta.source_db || '—')}</div>
    <div><strong>Sketches:</strong> ${meta.sketch_count}</div>
    <div><strong>Frames:</strong> ${meta.frame_count}</div>
    <div><strong>Exported At:</strong> ${esc(meta.exported_at || '—')}</div>
    `;
}

async function ingestSql() {
    document.getElementById('btnIngestSql').disabled = true;
    toast('Ingesting SQL into staging…', 'info');

    const res = await post('?action=ingest_sql', { upload_id: _imp.uploadId });
    if (!res.ok) {
        toast('SQL ingest error: ' + res.error, 'error');
        document.getElementById('btnIngestSql').disabled = false;
        return;
    }

    _imp.bundleId = res.bundle_id;
    document.getElementById('importBundleInfo').innerHTML = `
    <div><strong>Bundle ID (staging):</strong> #${res.bundle_id}</div>
    <div><strong>Label:</strong> ${esc(res.label)}</div>
    <div><strong>Sketches to import:</strong> ${res.sketches}</div>
    <div><strong>Frames to import:</strong> ${res.frames}</div>
    `;

    document.getElementById('cardRunImport').style.display = 'block';
    setStep(3, 'active');
    toast('Staging ready', 'success');
}

async function runImport() {
    if (!_imp.bundleId || !_imp.uploadId) { toast('Nothing staged.', 'error'); return; }
    document.getElementById('btnRunImport').disabled = true;
    document.getElementById('importProgressWrap').style.display = '';
    document.getElementById('importLog').style.display = 'block';

    let prog = 5;
    const progEl = document.getElementById('importProg');
    progEl.style.width = prog + '%';
    const tick = setInterval(() => {
        prog = Math.min(90, prog + (Math.random() * 4));
        progEl.style.width = prog + '%';
    }, 400);

    const res = await post('?action=run_import', {
        bundle_id: _imp.bundleId,
        upload_id: _imp.uploadId,
    });

    clearInterval(tick);
    progEl.style.width = '100%';

    const logBox = document.getElementById('importLog');
    if (res.ok && res.result) {
        logBox.innerHTML = (res.result.log || []).map(line => {
            const cls = line.includes('ERROR') ? 'log-line-err' : (line.includes('WARN') ? 'log-line-warn' : '');
            return `<span class="${cls}">${esc(line)}</span>`;
        }).join('\n');
        logBox.scrollTop = logBox.scrollHeight;
        toast(`Import done — ${res.result.sketch_count} sketches, ${res.result.frame_count} frames`, 'success');
        document.getElementById('cardPurge').style.display = 'block';
        setStep(4, 'active');
    } else {
        const err = res.result ? (res.result.error || '') : (res.error || 'Unknown error');
        logBox.innerHTML = `<span class="log-line-err">IMPORT FAILED: ${esc(err)}</span>`;
        toast('Import failed: ' + err, 'error');
    }
    document.getElementById('btnRunImport').disabled = false;
    refreshBundles();
}

async function resumePurge(bundleId) {
    if (!confirm('Purge staging data and extracted files for this bundle?')) return;
    const res = await post('?action=purge_bundle', { bundle_id: bundleId });
    if (res.ok) {
        toast('Staging rows and files purged', 'success');
        if (_imp.bundleId === bundleId) resetImport();
        refreshBundles();
    } else {
        toast('Purge failed', 'error');
    }
}

function resumeImport(bundleId, uploadId, label) {
    _imp = { uploadId, filename: 'Resumed', meta: null, bundleId };
    document.getElementById('cardUpload').style.display = 'none';
    document.getElementById('cardVerify').style.display = 'none';
    document.getElementById('cardRunImport').style.display = 'block';
    document.getElementById('cardPurge').style.display = 'none';
    
    document.getElementById('importBundleInfo').innerHTML = `
    <div><strong>Bundle ID (staging):</strong> #${bundleId}</div>
    <div><strong>Label:</strong> ${esc(label)}</div>
    <div><em>Resumed from history. Ready to run import.</em></div>
    `;
    
    document.getElementById('importLog').style.display = 'none';
    document.getElementById('importProgressWrap').style.display = 'none';
    document.getElementById('btnRunImport').disabled = false;
    
    setStep(3, 'active');
    document.getElementById('cardRunImport').scrollIntoView({ behavior: 'smooth' });
    toast('Resumed import session', 'info');
}

async function purgeBundle() {
    if (!_imp.bundleId) return;
    const res = await post('?action=purge_bundle', { bundle_id: _imp.bundleId });
    if (res.ok) {
        toast('Staging rows purged', 'success');
        setStep(4, 'done');
        refreshBundles();
    } else {
        toast('Purge failed', 'error');
    }
}

function resetImport() {
    _imp = { uploadId:null, filename:null, meta:null, bundleId:null };
    document.getElementById('cardVerify').style.display    = 'none';
    document.getElementById('cardRunImport').style.display = 'none';
    document.getElementById('cardPurge').style.display     = 'none';
    document.getElementById('cardUpload').style.display    = 'block';
    document.getElementById('uploadProgress').style.display = 'none';
    document.getElementById('importLog').style.display     = 'none';
    document.getElementById('importProgressWrap').style.display = 'none';
    [1,2,3,4].forEach(n => setStep(n, n === 1 ? 'active' : ''));
    toast('Ready for next import', 'info');
}

// ─── HTTP HELPERS ─────────────────────────────────────────────────────────
function setStep(n, state) {
    [1,2,3,4].forEach(i => {
        const el = document.getElementById('stepN' + i);
        const sp = document.getElementById('stepS' + i);
        if (!el) return;
        el.className = 'step-num' + (i < n ? ' done' : (i === n ? ' active' : ''));
        if (!sp) return;
        if (i < n) {
            sp.className = 'pill pill-teal'; sp.textContent = '✓ Done'; sp.style.display = '';
        } else if (i === n) {
            sp.className = 'pill pill-amber'; sp.textContent = 'Current'; sp.style.display = '';
        } else {
            sp.style.display = 'none';
        }
    });
}

async function get(url) {
    const r = await fetch(url);
    return r.json();
}
async function post(url, body) {
    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    return r.json();
}
function delay(ms) { return new Promise(r => setTimeout(r, ms)); }

function toast(msg, type = 'info') {
    const icons = { success: '✓', error: '✕', info: '◆' };
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<span>${icons[type]||'◆'}</span> ${esc(msg)}`;
    el.onclick = () => dismiss(el);
    document.getElementById('toastRoot').appendChild(el);
    const dismiss = e => { e.classList.add('out'); setTimeout(()=>e.remove(),250); };
    setTimeout(()=>dismiss(el), 3500);
}

function esc(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;')
        .replace(/'/g,'&#39;');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeFrameModal();
});

return { 
    init, switchTab, toggleTheme,
    switchListMode, setSort, prevPage, nextPage, jumpToPage,
    toggleSelect, selectNone, selectAllInList, addAllSketchesFromRun,
    previewFrames, applyGridFilters, toggleGridCols, closeFrameModal,
    selectAllInGrid, selectNoneInGrid,
    startExport, refreshBundles,
    ingestSql, runImport, purgeBundle, resetImport,
    resumeImport, resumePurge
};
})();

document.addEventListener('DOMContentLoaded', () => SMF.init());
</script>

<?php require_once __DIR__ . '/modal_frame_details.php'; ?>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

<?php echo $eruda ?? ''; ?>
</body>
</html>