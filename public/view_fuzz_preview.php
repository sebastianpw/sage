<?php
// public/view_fuzz_preview.php
// Clone of view_narrative_preview.php — uses approved fuzz_candidates (promoted/canonized)
// instead of narrative_sequences as the source for sketches and videos.
require_once __DIR__ . '/bootstrap.php';

use App\UI\Modules\VideoFrameExtractorModule;
use App\UI\Modules\ImageEditorModule;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$videoExtractor = new VideoFrameExtractorModule();
$imageEditor    = new ImageEditorModule();

// ── Inline API ──
if (isset($_GET['api_action'])) {
    header('Content-Type: application/json');
    $action = $_GET['api_action'];
    try {

        // ── List approved fuzz candidates (sidebar) ──────────────────────
        if ($action === 'list_candidates') {
            $page   = max(1, (int)($_GET['page']  ?? 1));
            $limit  = max(1, (int)($_GET['limit'] ?? 20));
            $search = trim($_GET['search'] ?? '');
            $offset = ($page - 1) * $limit;

            $params = [];
            $whereExtra = '';
            if ($search !== '') {
                $whereExtra = ' AND label LIKE :search';
                $params['search'] = '%' . $search . '%';
            }

            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM fuzz_candidates WHERE status IN ('promoted','canonized'){$whereExtra}"
            );
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();
            $pages = (int)ceil($total / $limit) ?: 1;
            if ($page > $pages) $page = $pages;
            $offset = ($page - 1) * $limit;

            $stmt = $pdo->prepare(
                "SELECT id, label, concept_type, status, confidence
                 FROM fuzz_candidates
                 WHERE status IN ('promoted','canonized'){$whereExtra}
                 ORDER BY id DESC
                 LIMIT $limit OFFSET $offset"
            );
            $stmt->execute($params);
            echo json_encode([
                'status' => 'ok',
                'data'   => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'pagination' => ['page' => $page, 'pages' => $pages, 'total' => $total],
            ]);
            exit;
        }

        // ── Helper: get sketch IDs linked to a candidate via fuzz_mentions ──
        // Returns array of sketch IDs (integers)
        function getCandidateSketchIds(PDO $pdo, int $candId): array {
            // Direct sketch mentions
            $stmt = $pdo->prepare(
                "SELECT DISTINCT source_row_id
                 FROM fuzz_mentions
                 WHERE candidate_id = :cid
                   AND source_table IN ('sketches', 'sketch_analysis', 'sketch_lore_history', 'sketch_ingredients')
                   AND source_row_id IS NOT NULL"
            );
            $stmt->execute(['cid' => $candId]);
            return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'source_row_id'));
        }

        // ── Get videos for a candidate ───────────────────────────────────
        if ($action === 'get_candidate_videos') {
            $candId = (int)($_GET['cand_id'] ?? 0);
            if (!$candId) throw new Exception('cand_id required');

            $sketchIds = getCandidateSketchIds($pdo, $candId);
            if (empty($sketchIds)) {
                echo json_encode(['status' => 'ok', 'data' => []]);
                exit;
            }
            $in = implode(',', $sketchIds);

            $stmt = $pdo->query(
                "SELECT DISTINCT v.id, v.name, v.url, v.thumbnail, v.duration, v.file_size,
                        v.description, v.category_id, v.is_active,
                        va.to_id as animatic_id, c.name as category_name
                 FROM videos v
                 JOIN videos_2_animatics va ON va.from_id = v.id
                 LEFT JOIN video_categories c ON v.category_id = c.id
                 JOIN animatics a ON va.to_id = a.id
                 JOIN frames f ON a.img2img_frame_id = f.id
                 WHERE v.is_active = 1
                   AND (
                       (f.entity_type = 'sketches' AND f.entity_id IN ($in))
                       OR f.id IN (
                           SELECT f2s.from_id FROM frames_2_sketches f2s
                           WHERE f2s.to_id IN ($in)
                       )
                   )
                 ORDER BY v.created_at DESC"
            );
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // ── ZIP candidate videos ─────────────────────────────────────────
        if ($action === 'zip_candidate_videos') {
            $candId = (int)($_GET['cand_id'] ?? 0);
            if (!$candId) throw new Exception('cand_id required');

            $sketchIds = getCandidateSketchIds($pdo, $candId);
            if (empty($sketchIds)) throw new Exception('No sketch mentions found for this candidate');
            $in = implode(',', $sketchIds);

            $stmt = $pdo->query(
                "SELECT DISTINCT v.id, v.name, v.url
                 FROM videos v
                 JOIN videos_2_animatics va ON va.from_id = v.id
                 JOIN animatics a ON va.to_id = a.id
                 JOIN frames f ON a.img2img_frame_id = f.id
                 WHERE v.is_active = 1
                   AND (
                       (f.entity_type = 'sketches' AND f.entity_id IN ($in))
                       OR f.id IN (
                           SELECT f2s.from_id FROM frames_2_sketches f2s
                           WHERE f2s.to_id IN ($in)
                       )
                   )
                 ORDER BY v.created_at DESC"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) throw new Exception('No videos found for this candidate');

            $publicPathAbs = rtrim($spw->getPublicPath(), '/');

            $nameStmt = $pdo->prepare("SELECT label FROM fuzz_candidates WHERE id = ?");
            $nameStmt->execute([$candId]);
            $candLabel = $nameStmt->fetchColumn() ?: 'candidate_' . $candId;
            $safeName  = preg_replace('/[^a-z0-9_-]/i', '_', $candLabel);
            $zipName   = 'fuzz_' . $candId . '_' . $safeName . '.zip';
            $tmpPath   = sys_get_temp_dir() . '/' . $zipName;

            $zip = new ZipArchive();
            if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('ZIP creation failed');
            }
            foreach ($rows as $row) {
                $abs = $publicPathAbs . '/' . ltrim($row['url'], '/');
                if (file_exists($abs)) {
                    $zip->addFile($abs, basename($row['url']));
                }
            }
            $zip->close();

            echo json_encode(['status' => 'ok', 'zip_name' => $zipName]);
            exit;
        }

        // ── ZIP download ─────────────────────────────────────────────────
        if ($action === 'zip_download') {
            $zipName = basename($_GET['zip_name'] ?? '');
            if (!preg_match('/^fuzz_\d+_[a-zA-Z0-9_\-]+\.zip$/', $zipName)) {
                http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Invalid filename']); exit;
            }
            $tmpPath = sys_get_temp_dir() . '/' . $zipName;
            if (!file_exists($tmpPath)) {
                http_response_code(404); echo json_encode(['status' => 'error', 'message' => 'File not found or expired']); exit;
            }
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipName . '"');
            header('Content-Length: ' . filesize($tmpPath));
            header('Cache-Control: no-cache');
            readfile($tmpPath);
            @unlink($tmpPath);
            exit;
        }

        // ── Video Tree: fetch full tree for jsTree ────────────────────────
        if ($action === 'tree_fetch') {
            $rows = $pdo->query(
                "SELECT id, parent_id, name, node_type FROM video_tree_nodes ORDER BY sort_order ASC, name ASC"
            )->fetchAll(PDO::FETCH_ASSOC);

            $nodes = [];
            foreach ($rows as $r) {
                $icon = match($r['node_type']) {
                    'episode'  => 'bi bi-film',
                    'sequence' => 'bi bi-collection-play',
                    'scene'    => 'bi bi-camera-video',
                    'other'    => 'bi bi-tag',
                    default    => 'bi bi-folder2',
                };
                $nodes[] = [
                    'id'     => 'n_' . $r['id'],
                    'parent' => $r['parent_id'] ? 'n_' . $r['parent_id'] : '#',
                    'text'   => $r['name'],
                    'icon'   => $icon,
                    'type'   => $r['node_type'],
                    'data'   => ['db_id' => (int)$r['id'], 'node_type' => $r['node_type']],
                ];
            }
            echo json_encode(['status' => 'ok', 'tree' => $nodes]);
            exit;
        }

        // ── Video Tree: create node ───────────────────────────────────────
        if ($action === 'tree_create_node') {
            $input    = json_decode(file_get_contents('php://input'), true);
            $name     = trim($input['name'] ?? '');
            $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;
            $nodeType = in_array($input['node_type'] ?? '', ['folder','episode','sequence','scene','other'])
                        ? $input['node_type'] : 'folder';
            if (!$name) throw new Exception('Name required');
            $stmt = $pdo->prepare(
                "INSERT INTO video_tree_nodes (parent_id, name, node_type) VALUES (?, ?, ?)"
            );
            $stmt->execute([$parentId, $name, $nodeType]);
            echo json_encode(['status' => 'ok', 'id' => (int)$pdo->lastInsertId(), 'name' => $name]);
            exit;
        }

        // ── Video Tree: get assignment for a video ────────────────────────
        if ($action === 'tree_get_assignment') {
            $videoId = (int)($_GET['video_id'] ?? 0);
            if (!$videoId) throw new Exception('Missing video_id');
            $stmt = $pdo->prepare(
                "SELECT vti.node_id, vtn.name as node_name, vtn.node_type
                 FROM video_tree_items vti
                 JOIN video_tree_nodes vtn ON vtn.id = vti.node_id
                 WHERE vti.video_id = ?"
            );
            $stmt->execute([$videoId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'ok', 'assignment' => $row ?: null]);
            exit;
        }

        // ── Video Tree: assign ────────────────────────────────────────────
        if ($action === 'tree_assign') {
            $input   = json_decode(file_get_contents('php://input'), true);
            $nodeId  = (int)($input['node_id']  ?? 0);
            $videoId = (int)($input['video_id'] ?? 0);
            if (!$nodeId || !$videoId) throw new Exception('Missing node_id or video_id');
            $stmt = $pdo->prepare("INSERT IGNORE INTO video_tree_items (node_id, video_id) VALUES (?, ?)");
            $stmt->execute([$nodeId, $videoId]);
            echo json_encode(['status' => 'ok', 'node_id' => $nodeId, 'video_id' => $videoId]);
            exit;
        }

        // ── Video Tree: unassign ──────────────────────────────────────────
        if ($action === 'tree_unassign') {
            $input   = json_decode(file_get_contents('php://input'), true);
            $videoId = (int)($input['video_id'] ?? 0);
            if (!$videoId) throw new Exception('Missing video_id');
            $stmt = $pdo->prepare("DELETE FROM video_tree_items WHERE video_id = ?");
            $stmt->execute([$videoId]);
            echo json_encode(['status' => 'ok']);
            exit;
        }

        throw new Exception('Unknown action');
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

$pageTitle = "Fuzz Candidate Preview";
ob_start();

require_once __DIR__ . '/modal_video_details.php';
require_once __DIR__ . '/modal_frame_details.php';
?>
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
<link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
<?php else: ?>
<link rel="stylesheet" href="/vendor/video-js.css" />
<?php endif; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<script src="js/toast.js"></script>

<style>
/* ── Reset & base ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --sidebar-w: 260px;
    --hdr-h:     52px;
    --bg:        var(--card, #0f0f1a);
    --surface:   rgba(255,255,255,0.04);
    --border:    rgba(255,255,255,0.08);
    --accent:    #6c63ff;
    --accent-dim:rgba(108,99,255,0.18);
    --text:      #d4d4e8;
    --text-muted:#4a4a6a;
    --muted:     #5a5a7a;
    --muted-border-rgb: 26, 26, 46;
    --green:     #00e5a0;
    --tap:       48px;
}

html, body { height: 100%; background: var(--bg); color: var(--text); font-family: system-ui, sans-serif; }

/* ══ LAYOUT ══ */
.np-layout { display: flex; height: 100dvh; overflow: hidden; position: relative; }

/* ══ SIDEBAR ══ */
.np-sidebar { width: var(--sidebar-w); flex-shrink: 0; display: flex; flex-direction: column; background: #0d0d18; border-right: 1px solid var(--border); height: 100%; overflow: hidden; transition: transform 0.22s ease; z-index: 30; }
@media (max-width: 699px) { .np-sidebar { position: absolute; top: 0; left: 0; bottom: 0; transform: translateX(-100%); } .np-sidebar.open { transform: translateX(0); } }
.np-sidebar-head { padding: 0 12px; height: var(--hdr-h); display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.np-sidebar-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #6a6a9a; flex: 1; }
.np-seq-list { flex: 1; overflow-y: auto; padding: 6px 4px; }
.np-seq-list::-webkit-scrollbar { width: 3px; }
.np-seq-list::-webkit-scrollbar-thumb { background: var(--border); }
.np-seq-item { padding: 9px 10px; border-radius: 5px; cursor: pointer; border: 1px solid transparent; margin-bottom: 2px; transition: background 0.12s, border-color 0.12s; -webkit-tap-highlight-color: transparent; }
.np-seq-item:hover  { background: var(--surface); border-color: var(--border); }
.np-seq-item.active { background: var(--accent-dim); border-color: var(--accent); }
.np-seq-item .si-id      { font-size: 0.62rem; color: #5a5a8a; font-family: monospace; margin-bottom: 2px; }
.np-seq-item .si-name    { font-size: 0.82rem; font-weight: 600; color: #d4d4e8; line-height: 1.2; }
.np-seq-item .si-type    { font-size: 0.65rem; color: #5a5a8a; margin-top: 2px; }
.np-seq-item .si-status  { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 0.58rem; font-family: monospace; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; }
.si-status.promoted  { color: #6c63ff; background: rgba(108,99,255,0.12); border: 1px solid rgba(108,99,255,0.25); }
.si-status.canonized { color: #00e5a0; background: rgba(0,229,160,0.10); border: 1px solid rgba(0,229,160,0.25); }
.np-seq-empty { padding: 20px 10px; font-size: 0.75rem; color: var(--muted); text-align: center; }
.np-sidebar-search { padding: 6px 8px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.np-sidebar-search-input { width: 100%; padding: 7px 10px 7px 28px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; color: var(--text); font-size: 0.78rem; font-family: inherit; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398l3.85 3.85a1 1 0 0 0 1.415-1.415l-3.868-3.833zm-5.242 1.156a5 5 0 1 1 0-10 5 5 0 0 1 0 10z' fill='%235a5a8a'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: 8px center; transition: border-color 0.15s; }
.np-sidebar-search-input:focus { outline: none; border-color: var(--accent); background-color: rgba(108,99,255,0.06); }
.np-sidebar-search-input::placeholder { color: #4a4a6a; }

/* ══ MAIN CONTENT ══ */
.np-main { flex: 1; min-width: 0; display: flex; flex-direction: column; height: 100%; overflow: hidden; }
.np-topbar { height: var(--hdr-h); display: flex; align-items: center; gap: 10px; padding: 0 14px; padding-left: 74px; border-bottom: 1px solid var(--border); flex-shrink: 0; background: rgba(0,0,0,0.2); position: relative; }
@media (min-width: 700px) { .np-topbar { padding-left: 14px; } }
.np-hamburger { position: absolute; left: 65px; top: 50%; transform: translateY(-50%); width: 36px; height: 36px; background: transparent; border: 1px solid var(--border); border-radius: 5px; color: var(--text); font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; -webkit-tap-highlight-color: transparent; z-index: 10; }
.np-hamburger:active { background: var(--surface); }
@media (min-width: 700px) { .np-hamburger { display: none; } }
.np-seq-badge { font-size: 0.7rem; background: var(--accent-dim); border: 1px solid var(--accent); color: var(--accent); padding: 2px 8px; border-radius: 3px; font-weight: 700; white-space: nowrap; flex-shrink: 0; }
.np-seq-name-label { font-size: 0.88rem; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
.np-vid-count { font-size: 0.72rem; color: var(--muted); white-space: nowrap; flex-shrink: 0; }
.np-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 20; }
.np-backdrop.active { display: block; }
@media (min-width: 700px) { .np-backdrop { display: none !important; } }

/* ══ PLAYER AREA ══ */
.np-player-wrap { width: 100%; background: #000; position: relative; flex-shrink: 0; }
.np-player-wrap video, .np-player-wrap .video-js { width: 100%; aspect-ratio: 16/9; display: block; background: #000; max-height: 52dvh; }
.np-player-wrap .video-js { height: auto !important; }
.np-placeholder { aspect-ratio: 16/9; max-height: 52dvh; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #000; color: var(--muted); font-size: 0.75rem; letter-spacing: 2px; text-transform: uppercase; gap: 10px; }
.np-placeholder i { font-size: 2.5rem; opacity: 0.3; }

/* ══ NOW PLAYING BAR ══ */
.np-nowplaying { display: flex; align-items: center; gap: 10px; padding: 0 12px; height: 40px; background: rgba(0,0,0,0.4); border-bottom: 1px solid var(--border); flex-shrink: 0; }
.np-now-title { flex: 1; font-size: 0.78rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text); }
.np-now-pos { font-size: 0.68rem; color: var(--muted); font-family: monospace; flex-shrink: 0; }
.np-progress { width: 100%; height: 3px; background: var(--border); cursor: pointer; flex-shrink: 0; }
.np-progress-fill { height: 100%; background: var(--accent); width: 0%; pointer-events: none; transition: width 0.15s linear; }

/* ══ CONTROLS ══ */
.np-controls { display: flex; align-items: center; gap: 6px; padding: 6px 10px; background: rgba(0,0,0,0.25); border-bottom: 1px solid var(--border); flex-shrink: 0; }
.np-ctrl-btn { min-height: 38px; min-width: 44px; padding: 0 10px; background: transparent; border: 1px solid var(--border); color: var(--muted); border-radius: 4px; font-size: 1rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px; -webkit-tap-highlight-color: transparent; transition: border-color 0.12s, color 0.12s; white-space: nowrap; }
.np-ctrl-btn:active { border-color: var(--accent); color: var(--accent); }
.np-ctrl-btn:disabled { opacity: 0.3; pointer-events: none; }
.np-ctrl-btn.wide { flex: 1; }
.np-auto-label { display: flex; align-items: center; gap: 5px; font-size: 0.65rem; color: var(--muted); cursor: pointer; white-space: nowrap; margin-left: auto; -webkit-tap-highlight-color: transparent; }
.np-auto-label input { accent-color: var(--accent); width: 15px; height: 15px; }
.np-animatic-btn { min-height: 38px; padding: 0 12px; background: transparent; border: 1px solid rgba(255,209,102,0.3); color: rgba(255,209,102,0.7); border-radius: 4px; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; -webkit-tap-highlight-color: transparent; transition: background 0.12s; }
.np-animatic-btn:not(:disabled):active { background: rgba(255,209,102,0.12); }
.np-animatic-btn:disabled { opacity: 0.2; pointer-events: none; }
.np-btn-assign { min-height: 38px; padding: 0 12px; background: transparent; border: 1px solid var(--accent); color: var(--accent); border-radius: 4px; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; -webkit-tap-highlight-color: transparent; transition: background 0.12s; }
.np-btn-assign:not(:disabled):active { background: rgba(108,99,255,0.12); }
.np-btn-assign:disabled { opacity: 0.2; pointer-events: none; border-color: var(--border); color: var(--muted); }
.np-dl-btn { min-height: 38px; padding: 0 12px; background: transparent; border: 1px solid rgba(0,229,160,0.3); color: rgba(0,229,160,0.7); border-radius: 4px; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; -webkit-tap-highlight-color: transparent; transition: background 0.12s, opacity 0.12s; }
.np-dl-btn:not(:disabled):active { background: rgba(0,229,160,0.12); }
.np-dl-btn:disabled { opacity: 0.2; pointer-events: none; }
.np-dl-btn.loading { opacity: 0.6; pointer-events: none; font-size: 0.65rem; letter-spacing: 0.5px; }

/* ══ GRID ══ */
.np-grid-wrap { flex: 1; overflow-y: auto; padding: 8px; }
.np-grid-wrap::-webkit-scrollbar { width: 3px; }
.np-grid-wrap::-webkit-scrollbar-thumb { background: var(--border); }
.np-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; }
.np-card { position: relative; aspect-ratio: 16/9; background: #111; border-radius: 3px; overflow: hidden; cursor: pointer; border: 2px solid transparent; -webkit-tap-highlight-color: transparent; transition: border-color 0.1s; }
.np-card.active { border-color: var(--accent); }
.np-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
.np-card-idx { position: absolute; bottom: 2px; left: 3px; font-size: 0.5rem; color: rgba(255,255,255,0.5); pointer-events: none; font-family: monospace; }

/* ══ SIDEBAR PAGINATION ══ */
.np-sidebar-pg { flex-shrink: 0; border-top: 1px solid var(--border); padding: 6px 8px; display: flex; align-items: center; gap: 4px; background: #0d0d18; }
.np-pg-btn { width: 32px; height: 32px; background: transparent; border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; color: #8888aa; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; -webkit-tap-highlight-color: transparent; transition: border-color 0.12s, color 0.12s; }
.np-pg-btn:active:not(:disabled) { border-color: var(--accent); color: var(--accent); }
.np-pg-btn:disabled { opacity: 0.3; pointer-events: none; }
.np-pg-input { width: 38px; text-align: center; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; color: var(--accent); font-family: monospace; font-size: 0.82rem; font-weight: 700; padding: 5px 2px; height: 32px; -moz-appearance: textfield; }
.np-pg-input::-webkit-outer-spin-button, .np-pg-input::-webkit-inner-spin-button { -webkit-appearance: none; }
.np-pg-input:focus { outline: none; border-color: var(--accent); }
.np-pg-of { font-size: 0.62rem; color: #5a5a8a; white-space: nowrap; flex: 1; text-align: center; }

/* State messages */
.np-state { padding: 40px 20px; text-align: center; color: var(--muted); font-size: 0.75rem; display: flex; flex-direction: column; align-items: center; gap: 10px; }
.np-spinner { width: 20px; height: 20px; border: 2px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* jsTree overrides */
.jstree-default .jstree-anchor { color: var(--text) !important; line-height: 28px; height: 28px; }
.jstree-default .jstree-hovered { background: rgba(108,99,255,0.12) !important; border-radius: 4px; }
.jstree-default .jstree-clicked { background: rgba(108,99,255,0.25) !important; color: var(--accent) !important; border-radius: 4px; }
.jstree-default .jstree-icon { color: var(--muted); }
.jstree-default { background: transparent !important; color: var(--text); }
.jstree-container-ul { background: transparent !important; }

/* ════════════════════════════════════
   RICH VIDEO DETAIL MODAL CSS
════════════════════════════════════ */
.detail-modal-card { width: 100%; max-width: 800px; background: var(--card); border-radius: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.5); display: flex; flex-direction: column; max-height: 95vh; border: 1px solid rgba(var(--muted-border-rgb),0.06); overflow: hidden; }
.detail-player-wrapper { width: 100%; background: #000; aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center; }
.detail-player-wrapper video { width: 100%; height: 100%; max-height: 50vh; }
.detail-content { padding: 16px; overflow-y: auto; }
.detail-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 8px; color: var(--text); }
.detail-meta-row { display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px; }
.detail-actions-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 16px; }
@media (max-width: 768px) { .detail-actions-grid { grid-template-columns: repeat(4, 1fr); } }
.detail-actions-grid .btn { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 10px 4px; font-size: 0.8rem; height: auto; gap: 4px; }
.detail-desc-btn { width: 100%; text-align: left; margin-top: 10px; padding: 10px; background: rgba(var(--muted-border-rgb), 0.05); border-radius: 6px; border: 1px solid rgba(var(--muted-border-rgb), 0.1); color: var(--text); cursor: pointer; }
.detail-desc-btn:hover { background: rgba(var(--muted-border-rgb), 0.1); }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.65); display: none; align-items: center; justify-content: center; z-index: 120000; padding: 12px; }
.modal-overlay.active { display: flex; }
.modal-card { width: 100%; max-width: 600px; background: var(--card); border-radius: 10px; box-shadow: 0 8px 30px rgba(2,6,23,0.35); display: flex; flex-direction: column; max-height: 90vh; border: 1px solid rgba(var(--muted-border-rgb),0.06); overflow: hidden; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid rgba(var(--muted-border-rgb),0.08); flex-shrink: 0; }
.modal-body { padding: 20px; overflow-y: auto; color: var(--text); }
.modal-footer { padding: 12px 20px; border-top: 1px solid rgba(var(--muted-border-rgb),0.08); background: var(--bg); display: flex; justify-content: flex-end; gap: 8px; flex-shrink: 0; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid rgba(var(--muted-border-rgb), 0.12); font-size: 0.9rem; background: var(--bg); color: var(--text); transition: border-color 0.15s ease; }

/* ════════════════════════════════════
   REMBG MODAL STYLES
════════════════════════════════════ */
.rembg-color-row { display: flex; align-items: center; gap: 10px; padding: 14px; border-bottom: 1px solid rgba(var(--muted-border-rgb),0.08); flex-shrink: 0; }
.rembg-swatch { width: 36px; height: 36px; border-radius: 4px; border: 2px solid rgba(255,255,255,0.15); flex-shrink: 0; cursor: pointer; transition: border-color 0.15s; }
.rembg-swatch:active { border-color: var(--accent); }
.rembg-hex-input { flex: 1; padding: 8px 10px; background: var(--bg); border: 1px solid rgba(var(--muted-border-rgb),0.12); color: var(--text); border-radius: 4px; font-family: inherit; font-size: 0.9rem; letter-spacing: 1px; }
.rembg-hex-input:focus { outline: none; border-color: var(--accent); }
.rembg-pick-btn { padding: 8px 12px; background: transparent; border: 1px solid var(--accent); color: var(--accent); border-radius: 4px; font-family: inherit; font-size: 0.7rem; font-weight: 700; cursor: pointer; white-space: nowrap; -webkit-tap-highlight-color: transparent; }
.rembg-pick-btn:active { background: rgba(108,99,255,0.15); }
.rembg-info-row { padding: 8px 20px; font-size: 0.7rem; color: var(--text-muted); flex-shrink: 0; }

/* ════════════════════════════════════
   COLOR SAMPLER MODAL STYLES
════════════════════════════════════ */
.sampler-canvas-wrap { flex: 1; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #000; min-height: 180px; cursor: crosshair; touch-action: none; }
#samplerCanvas { display: block; max-width: 100%; max-height: 100%; }
.sampler-result-row { display: flex; align-items: center; gap: 10px; padding: 12px 20px; border-top: 1px solid rgba(var(--muted-border-rgb),0.08); flex-shrink: 0; }
.sampler-result-swatch { width: 40px; height: 40px; border-radius: 4px; border: 2px solid rgba(255,255,255,0.15); flex-shrink: 0; }
.sampler-result-hex { font-size: 1.1rem; font-weight: 700; letter-spacing: 2px; color: var(--text); }
.sampler-hint { font-size: 0.65rem; color: var(--text-muted); padding: 6px 20px; flex-shrink: 0; }
</style>

<div class="np-layout">

    <div class="np-backdrop" id="npBackdrop" onclick="closeSidebar()"></div>

    <div class="np-sidebar" id="npSidebar">
        <div class="np-sidebar-head">
            <span class="np-sidebar-title">Fuzz Candidates</span>
        </div>
        <div class="np-sidebar-search">
            <input type="search" id="npSidebarSearch" class="np-sidebar-search-input" placeholder="Search…" autocomplete="off">
        </div>
        <div class="np-seq-list" id="npSeqList">
            <div class="np-seq-empty"><div class="np-spinner"></div></div>
        </div>
        <div class="np-sidebar-pg">
            <button class="np-pg-btn" id="npSeqPrev" disabled>‹</button>
            <input  type="number" class="np-pg-input" id="npSeqPageInput" value="1" min="1">
            <span class="np-pg-of" id="npSeqPgOf">/ 1</span>
            <button class="np-pg-btn" id="npSeqNext" disabled>›</button>
        </div>
    </div>

    <div class="np-main">

        <div class="np-topbar">
            <button class="np-hamburger" id="npHamburger" onclick="toggleSidebar()" title="Fuzz Candidates">☰</button>
            <span class="np-seq-badge" id="npBadge" style="display:none;">FUZZ</span>
            <span class="np-seq-name-label" id="npSeqLabel">Select a candidate →</span>
            <span class="np-vid-count" id="npVidCount"></span>
        </div>

        <div class="np-player-wrap" id="npPlayerWrap">
            <div class="np-placeholder" id="npPlaceholder">
                <i class="bi bi-collection-play"></i>
                <span>Select a candidate to begin</span>
            </div>
            <video id="npPlayer" class="video-js vjs-default-skin vjs-big-play-centered"
                   controls preload="auto" playsinline style="display:none;">
            </video>
        </div>

        <div class="np-progress" id="npProgress">
            <div class="np-progress-fill" id="npProgressFill"></div>
        </div>

        <div class="np-nowplaying">
            <span class="np-now-title" id="npNowTitle">—</span>
            <span class="np-now-pos" id="npNowPos"></span>
            <span id="assignedBadge" style="display:none; color:var(--accent); font-weight:700; font-size:0.7rem; margin-left:10px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:120px;"></span>
        </div>

        <div class="np-controls">
            <button class="np-ctrl-btn" id="npPrev" disabled onclick="navigate(-1)">◀</button>
            <button class="np-ctrl-btn wide" id="npPlayPause" disabled onclick="togglePlayPause()" style="font-size:1.1rem;">▶</button>
            <button class="np-ctrl-btn" id="npNext" disabled onclick="navigate(1)">▶</button>
            <button class="np-animatic-btn" id="npVideoDetail" disabled onclick="openVideoDetail()" title="Video Details">🎬</button>
            <button class="np-btn-assign" id="npAssign" disabled onclick="openAssignModal()" title="Assign to story node">⬡</button>
            <button class="np-dl-btn" id="npDownload" disabled onclick="downloadCandidateZip()" title="Download all videos as ZIP">⬇</button>
            <label class="np-auto-label">
                <input type="checkbox" id="npAuto" checked> Auto
            </label>
        </div>

        <div class="np-grid-wrap" id="npGridWrap">
            <div class="np-state" id="npGridState" style="display:none;">
                <div class="np-spinner"></div><span>Loading…</span>
            </div>
            <div class="np-grid" id="npGrid"></div>
        </div>

    </div><!-- /.np-main -->

</div><!-- /.np-layout -->

<!-- ══ ASSIGN TREE MODAL ══ -->
<div class="modal-overlay" id="assignModal">
    <div class="modal-card" style="max-width: 480px;">
        <div class="modal-header">
            <strong style="font-size:0.85rem; text-transform:uppercase; letter-spacing:1px;">⬡ Assign to Story Node</strong>
            <button class="btn btn-sm btn-outline-secondary close-modal">✕</button>
        </div>
        <div style="padding: 8px 14px; font-size: 0.7rem; color: var(--muted); border-bottom: 1px solid rgba(var(--muted-border-rgb),0.08); display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 36px;">
            <span id="assignCurrentText">No assignment</span>
            <button class="btn btn-sm btn-outline-danger" id="btnUnassign" style="display:none; padding:3px 8px; font-size:0.6rem;" onclick="unassignVideo()">Unassign</button>
        </div>
        <div style="padding: 6px 10px; border-bottom: 1px solid rgba(var(--muted-border-rgb),0.08); display: flex; gap: 6px;">
            <input type="text" id="newNodeName" class="form-control" placeholder="New node name…" style="padding:5px 8px; font-size:0.75rem;">
            <select id="newNodeType" class="form-control" style="padding:5px 6px; font-size:0.7rem; width:auto;">
                <option value="folder">Folder</option>
                <option value="episode">Episode</option>
                <option value="sequence">Sequence</option>
                <option value="scene">Scene</option>
                <option value="other">Other</option>
            </select>
            <button class="btn btn-sm btn-primary" onclick="createTreeNode()" style="font-size:0.7rem; padding:5px 12px;">+ Add</button>
        </div>
        <div class="modal-body" style="padding:8px 6px; flex:1; overflow-y:auto; min-height:200px; background:var(--bg);">
            <div id="assignTree">Loading…</div>
        </div>
        <div class="modal-footer" style="padding:10px 14px;">
            <button class="btn btn-sm btn-success" id="btnAssignConfirm" disabled onclick="confirmAssign()" style="width:100%; font-size:0.8rem; font-weight:bold; text-transform:uppercase; letter-spacing:1px; min-height:38px;">
                Assign to Selected Node
            </button>
        </div>
    </div>
</div>

<!-- === RICH VIDEO DETAIL MODAL === -->
<div id="videoDetailModal" class="modal-overlay">
    <div class="detail-modal-card">
        <div class="modal-header">
            <strong id="detailModalTitle">Video Details</strong>
            <button class="btn btn-sm btn-outline-secondary close-modal">Close</button>
        </div>
        <div class="detail-player-wrapper">
            <video id="detailVideoPlayer" controls playsinline controlsList="nodownload"></video>
        </div>
        <div class="detail-content">
            <div class="detail-title" id="detailVideoName"></div>
            <div class="detail-meta-row" id="detailMeta"></div>
            <div class="detail-actions-grid" id="detailActionButtons"></div>
            <button id="detailDescTrigger" class="detail-desc-btn">
                📄 <strong>Description:</strong> <span id="detailDescSnippet"></span>
                <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">(Tap to view full)</div>
            </button>
        </div>
    </div>
</div>

<!-- ══ REMBG CONFIRMATION MODAL ══ -->
<div id="rembgModal" class="modal-overlay" style="z-index: 120010;">
    <div class="modal-card">
        <div class="modal-header">
            <strong>◩ Remove Background</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="closeRembgModal()">✕</button>
        </div>
        <div class="rembg-color-row">
            <div class="rembg-swatch" id="rembgSwatch" onclick="syncSwatchFromInput()" title="Current color"></div>
            <input type="text" class="rembg-hex-input" id="rembgHexInput" value="#00FB00" maxlength="7"
                   oninput="onRembgHexInput()" placeholder="#00FB00">
            <button class="rembg-pick-btn" onclick="openSamplerModal()">Pick from<br>Thumb</button>
        </div>
        <div class="rembg-info-row">
            Target animatic: <span id="rembgAnimaticId" style="color:var(--accent); font-weight:700;">—</span>
            &nbsp;|&nbsp; Source video: <span id="rembgVideoId" style="color:var(--accent); font-weight:700;">—</span>
        </div>
        <div class="modal-footer" style="gap:8px;">
            <button class="btn btn-sm btn-outline-secondary" onclick="closeRembgModal()">Cancel</button>
            <button class="btn btn-sm btn-success" id="btnRembgConfirm" onclick="confirmRembg()">Queue Removal</button>
        </div>
    </div>
</div>

<!-- ══ COLOR SAMPLER MODAL ══ -->
<div id="samplerModal" class="modal-overlay" style="z-index: 120015;">
    <div class="modal-card" style="max-height:92dvh;">
        <div class="modal-header">
            <strong>🎨 Pick Green Color</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="closeSamplerModal()">✕</button>
        </div>
        <div class="sampler-hint">Tap the green area on the thumbnail to sample its color.</div>
        <div class="sampler-canvas-wrap" id="samplerCanvasWrap">
            <canvas id="samplerCanvas"></canvas>
        </div>
        <div class="sampler-result-row">
            <div class="sampler-result-swatch" id="samplerSwatch" style="background:#00FB00;"></div>
            <span class="sampler-result-hex" id="samplerHex">#00FB00</span>
            <span style="font-size:0.65rem; color:var(--text-muted); margin-left:auto;">Tap to retap</span>
        </div>
        <div class="modal-footer" style="gap:8px;">
            <button class="btn btn-sm btn-outline-secondary" onclick="closeSamplerModal()">Cancel</button>
            <button class="btn btn-sm btn-success" onclick="useSampledColor()">Use This Color</button>
        </div>
    </div>
</div>

<!-- Admin Modals -->
<div id="editModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-header"><strong>Edit Video</strong><button class="btn btn-sm btn-outline-secondary close-modal">Close</button></div>
    <form id="editForm">
      <input type="hidden" id="editVideoId">
      <div class="modal-body">
        <div class="form-group"><label>Internal Name</label><input type="text" class="form-control" id="editName" required></div>
        <div class="form-group"><label>Description</label><textarea class="form-control" id="editDescription"></textarea></div>
        <div class="form-group"><label>Category</label><select class="form-control" id="editCategory"><option value="">None</option></select></div>
        <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" id="editActive"> Active</label></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary close-modal">Cancel</button><button type="submit" class="btn btn-sm btn-success">Save</button></div>
    </form>
  </div>
</div>

<div id="addToPlaylistModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-header"><strong>Add to Playlist</strong><button class="btn btn-sm btn-outline-secondary close-modal">Close</button></div>
    <div class="modal-body">
      <input type="hidden" id="addToPlaylistVideoId">
      <div id="playlistCheckboxes"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary close-modal">Cancel</button><button type="submit" class="btn btn-sm btn-success" id="addToPlaylistSubmitBtn">Save</button></div>
  </div>
</div>

<div id="descModal" class="modal-overlay" style="z-index: 120005;">
  <div class="modal-card">
    <div class="modal-header"><strong>Full Description</strong><button class="btn btn-sm btn-outline-secondary close-modal">Close</button></div>
    <div class="modal-body" id="descModalBody" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.6;"></div>
    <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary close-modal">Back</button></div>
  </div>
</div>

<!-- Modules Rendering -->
<?= $videoExtractor->render() ?>
<?= $imageEditor->render() ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
<script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
<?php else: ?>
<script src="/vendor/video.min.js"></script>
<?php endif; ?>

<script>
(function () {
'use strict';

// ── State ──
let candidates  = [];
let videos      = [];
let curCandId   = null;
let curCandName = '';
let curIdx      = -1;
let vjsPlayer   = null;
let vjsInited   = false;
let seqPage     = 1;
let seqTotalPages = 1;
const SEQ_PER_PAGE = 20;
let seqSearch   = '';
let seqSearchTimer = null;

let categories = [];
let playlists  = [];
let currentDetailVideoId = null;

// ── Rembg / Sampler State ──
let rembgTargetVideoId    = null;
let rembgTargetAnimaticId = null;
let rembgThumbnailUrl     = null;
let samplerPickedColor    = '#00FB00';
let samplerImg            = null;

Promise.all([
    fetch('video_admin_api.php?action=list_categories').then(r => r.json()),
    fetch('video_admin_api.php?action=list_playlists').then(r => r.json())
]).then(([catData, plData]) => {
    if (catData && catData.status === 'ok') {
        categories = catData.categories;
        const editCat = document.getElementById('editCategory');
        if(editCat) editCat.innerHTML = '<option value="">None</option>' + categories.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
    }
    if (plData && plData.status === 'ok') playlists = plData.playlists;
});

// ── DOM ──
const sidebar      = document.getElementById('npSidebar');
const backdrop     = document.getElementById('npBackdrop');
const seqList      = document.getElementById('npSeqList');
const seqPrevBtn   = document.getElementById('npSeqPrev');
const seqNextBtn   = document.getElementById('npSeqNext');
const seqPageInput = document.getElementById('npSeqPageInput');
const seqPgOf      = document.getElementById('npSeqPgOf');
const badge        = document.getElementById('npBadge');
const seqLabel     = document.getElementById('npSeqLabel');
const vidCount     = document.getElementById('npVidCount');
const placeholder  = document.getElementById('npPlaceholder');
const playerEl     = document.getElementById('npPlayer');
const progressFill = document.getElementById('npProgressFill');
const progressBar  = document.getElementById('npProgress');
const nowTitle     = document.getElementById('npNowTitle');
const nowPos       = document.getElementById('npNowPos');
const btnPrev      = document.getElementById('npPrev');
const btnNext      = document.getElementById('npNext');
const btnPlay      = document.getElementById('npPlayPause');
const btnVideoDetail = document.getElementById('npVideoDetail');
const btnAssign    = document.getElementById('npAssign');
const btnDl        = document.getElementById('npDownload');
const autoCb       = document.getElementById('npAuto');
const gridState    = document.getElementById('npGridState');
const grid         = document.getElementById('npGrid');

// ── Sidebar ──
function toggleSidebar() { sidebar.classList.contains('open') ? closeSidebar() : openSidebar(); }
function openSidebar()   { sidebar.classList.add('open');    backdrop.classList.add('active'); }
function closeSidebar()  { sidebar.classList.remove('open'); backdrop.classList.remove('active'); }
window.toggleSidebar = toggleSidebar;
window.closeSidebar  = closeSidebar;

// ── Type/status badge colour ──
const TYPE_ICONS = {
    character: '🦸', location: '🗺️', faction: '⚔️', artifact: '🏺',
    event: '⚡', concept: '💡', relationship: '🔗', other: '◆'
};

// ── Load candidates list (paginated) ──
async function loadCandidates(page) {
    page = parseInt(page) || 1;
    if (page < 1) page = 1;
    if (seqTotalPages > 1 && page > seqTotalPages) page = seqTotalPages;
    seqPage = page;

    seqList.innerHTML = '<div class="np-seq-empty"><div class="np-spinner"></div></div>';
    seqPrevBtn.disabled = true; seqNextBtn.disabled = true;

    const res = await fetch(`?api_action=list_candidates&page=${page}&limit=${SEQ_PER_PAGE}&search=${encodeURIComponent(seqSearch)}`).then(r => r.json());
    seqList.innerHTML = '';

    if (!res.status || res.status !== 'ok' || !res.data || !res.data.length) {
        seqList.innerHTML = '<div class="np-seq-empty">No approved candidates found.</div>';
        return;
    }

    candidates = res.data;
    const pg = res.pagination;
    seqTotalPages       = pg.pages;
    seqPageInput.value  = pg.page;
    seqPageInput.max    = pg.pages;
    seqPgOf.textContent = `/ ${pg.pages}`;
    seqPrevBtn.disabled = pg.page <= 1;
    seqNextBtn.disabled = pg.page >= pg.pages;

    candidates.forEach(cand => {
        const el = document.createElement('div');
        el.className = 'np-seq-item' + (cand.id == curCandId ? ' active' : '');
        el.dataset.id = cand.id;
        const icon = TYPE_ICONS[cand.concept_type] || '◆';
        el.innerHTML = `
            <div class="si-id">#${cand.id}</div>
            <div class="si-name">${icon} ${esc(cand.label)}</div>
            ${cand.concept_type ? `<div class="si-type">${esc(cand.concept_type)}</div>` : ''}
            <span class="si-status ${esc(cand.status)}">${esc(cand.status)}</span>
        `;
        el.onclick = () => selectCandidate(cand.id, cand.label);
        seqList.appendChild(el);
    });
}

// ── Select a candidate ──
async function selectCandidate(candId, candLabel) {
    curCandId   = candId;
    curCandName = candLabel;
    curIdx      = -1;
    videos      = [];
    btnDl.disabled = true; btnVideoDetail.disabled = true; btnAssign.disabled = true;

    document.querySelectorAll('.np-seq-item').forEach(el => el.classList.toggle('active', el.dataset.id == candId));

    badge.style.display  = 'inline-block';
    badge.textContent    = `#${candId}`;
    seqLabel.textContent = candLabel;
    vidCount.textContent = '';
    closeSidebar();

    grid.innerHTML  = '';
    gridState.style.display = 'flex';
    gridState.innerHTML = '<div class="np-spinner"></div><span>Loading videos…</span>';

    const res = await fetch(`?api_action=get_candidate_videos&cand_id=${candId}`).then(r => r.json());
    gridState.style.display = 'none';

    if (!res.status || res.status !== 'ok' || !res.data || !res.data.length) {
        grid.innerHTML = '<div class="np-state" style="grid-column:1/-1;">No videos found for this candidate.</div>';
        vidCount.textContent = '0 videos';
        return;
    }

    videos = res.data;
    vidCount.textContent = videos.length + ' video' + (videos.length !== 1 ? 's' : '');
    btnDl.disabled = false;
    renderGrid();
    playVideo(0, false);
}

// ── Render thumbnail grid ──
function renderGrid() {
    grid.innerHTML = videos.map((v, i) => `
        <div class="np-card ${i === curIdx ? 'active' : ''}" data-idx="${i}" onclick="playVideo(${i}, true)">
            <img src="${esc(v.thumbnail || '')}" loading="lazy" alt="${i+1}">
            <span class="np-card-idx">${i+1}</span>
        </div>
    `).join('');
}

// ── Play video ──
function playVideo(idx, autoPlay) {
    if (idx < 0 || idx >= videos.length) return;
    curIdx = idx;
    const v = videos[idx];

    if (!vjsInited) {
        placeholder.style.display = 'none';
        playerEl.style.display    = 'block';
        vjsPlayer = videojs('npPlayer', { controls: true, preload: 'auto', fill: false, fluid: false });
        vjsPlayer.on('timeupdate', () => {
            if (vjsPlayer.duration()) progressFill.style.width = (vjsPlayer.currentTime() / vjsPlayer.duration() * 100) + '%';
        });
        vjsPlayer.on('ended', () => { if (autoCb.checked && curIdx + 1 < videos.length) navigate(1); });
        vjsPlayer.on('play',  () => { btnPlay.textContent = '⏸'; });
        vjsPlayer.on('pause', () => { btnPlay.textContent = '▶'; });
        vjsInited = true;
    }

    const url = v.url.startsWith('/') ? v.url : '/' + v.url;
    vjsPlayer.src({ src: url, type: 'video/mp4' });
    if (autoPlay) vjsPlayer.play().catch(() => {});

    nowTitle.textContent = `${idx + 1}. ${v.name || 'Video #' + v.id}`;
    nowPos.textContent   = `${idx + 1} / ${videos.length}`;
    btnPrev.disabled     = idx <= 0;
    btnNext.disabled     = idx >= videos.length - 1;
    btnPlay.disabled     = false;
    btnPlay.textContent  = autoPlay ? '⏸' : '▶';
    btnVideoDetail.disabled = false;
    btnAssign.disabled = false;
    fetchAssignment(v.id);

    document.querySelectorAll('.np-card').forEach((c, i) => c.classList.toggle('active', i === idx));
    const activeCard = document.querySelector(`.np-card[data-idx="${idx}"]`);
    if (activeCard) activeCard.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

function navigate(dir) { const next = curIdx + dir; if (next >= 0 && next < videos.length) playVideo(next, true); }
window.navigate   = navigate;
window.playVideo  = playVideo;

function togglePlayPause() { if (!vjsInited || !vjsPlayer) return; vjsPlayer.paused() ? vjsPlayer.play().catch(() => {}) : vjsPlayer.pause(); }
window.togglePlayPause = togglePlayPause;

progressBar.addEventListener('click', e => {
    if (!vjsInited || !vjsPlayer || !vjsPlayer.duration()) return;
    const r = progressBar.getBoundingClientRect();
    vjsPlayer.currentTime(((e.clientX - r.left) / r.width) * vjsPlayer.duration());
});

// ── Helpers ──
function esc(t) { return String(t || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmtSize(b) { return b ? (b/1024/1024).toFixed(1)+' MB' : ''; }

// ════════════════════════════════════
// ASSIGN TREE MODAL
// ════════════════════════════════════
const assignedBadge   = document.getElementById('assignedBadge');
const assignmentCache = {};

function updateAssignBadge(assignment) {
    if (assignment && assignment.node_name) {
        assignedBadge.textContent = '⬡ ' + assignment.node_name;
        assignedBadge.style.display = 'inline-block';
        btnAssign.style.background = 'rgba(108,99,255,0.15)';
    } else {
        assignedBadge.style.display = 'none';
        btnAssign.style.background = 'transparent';
    }
}

function fetchAssignment(videoId) {
    if (videoId in assignmentCache) { updateAssignBadge(assignmentCache[videoId]); return; }
    fetch('?api_action=tree_get_assignment&video_id=' + videoId).then(r => r.json()).then(d => {
        if (d.status === 'ok') { assignmentCache[videoId] = d.assignment; updateAssignBadge(d.assignment); }
    });
}

let assignTreeInited = false;
let assignNodeId     = null;
let assignNodeName   = '';

function openAssignModal() {
    if (curIdx < 0) return;
    const v = videos[curIdx];
    const cached = assignmentCache[v.id];
    if (cached) {
        document.getElementById('assignCurrentText').innerHTML = 'Currently: <span style="color:var(--green);font-weight:bold;">' + esc(cached.node_name) + '</span>';
        document.getElementById('btnUnassign').style.display = 'inline-block';
    } else {
        document.getElementById('assignCurrentText').innerHTML = '<span style="color:var(--muted);">No assignment yet</span>';
        document.getElementById('btnUnassign').style.display = 'none';
    }
    assignNodeId   = null; assignNodeName = '';
    document.getElementById('btnAssignConfirm').disabled = true;
    document.getElementById('assignModal').classList.add('active');
    if (!assignTreeInited) initAssignTree(); else $('#assignTree').jstree('refresh');
}

function closeAssignModal() { document.getElementById('assignModal').classList.remove('active'); }

function initAssignTree() {
    assignTreeInited = true;
    $('#assignTree').jstree({
        core: { data: { url: '?api_action=tree_fetch', dataType: 'json', dataFilter: function(raw) { try { const j = JSON.parse(raw); return JSON.stringify(j.status === 'ok' ? j.tree : []); } catch(e) { return '[]'; } } }, themes: { name: 'default', dots: true, icons: true }, check_callback: false },
        plugins: ['types', 'state'],
        types: { folder: { icon: 'bi bi-folder2' }, episode: { icon: 'bi bi-film' }, sequence: { icon: 'bi bi-collection-play' }, scene: { icon: 'bi bi-camera-video' }, other: { icon: 'bi bi-tag' } },
    }).on('select_node.jstree', function(e, data) { assignNodeId = data.node.data.db_id; assignNodeName = data.node.text; document.getElementById('btnAssignConfirm').disabled = false; })
      .on('deselect_node.jstree', function() { assignNodeId = null; document.getElementById('btnAssignConfirm').disabled = true; });
}

function createTreeNode() {
    const name = document.getElementById('newNodeName').value.trim();
    const nodeType = document.getElementById('newNodeType').value;
    if (!name) return;
    const sel = $('#assignTree').jstree('get_selected', true);
    const parentId = sel.length ? sel[0].data.db_id : null;
    fetch('?api_action=tree_create_node', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name, node_type: nodeType, parent_id: parentId }) })
    .then(r => r.json()).then(d => { if (d.status === 'ok') { document.getElementById('newNodeName').value = ''; $('#assignTree').jstree('refresh'); if (typeof Toast !== 'undefined') Toast.show('Node created', 'success'); } else { if (typeof Toast !== 'undefined') Toast.show(d.message || 'Error', 'error'); } });
}

function confirmAssign() {
    if (!assignNodeId || curIdx < 0) return;
    const v = videos[curIdx];
    document.getElementById('btnAssignConfirm').disabled = true;
    document.getElementById('btnAssignConfirm').textContent = 'Assigning…';
    fetch('?api_action=tree_assign', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ node_id: assignNodeId, video_id: v.id }) })
    .then(r => r.json()).then(d => {
        if (d.status === 'ok') { assignmentCache[v.id] = { node_id: assignNodeId, node_name: assignNodeName }; updateAssignBadge(assignmentCache[v.id]); closeAssignModal(); if (typeof Toast !== 'undefined') Toast.show('Assigned to: ' + assignNodeName, 'success'); }
        else { if (typeof Toast !== 'undefined') Toast.show(d.message || 'Error', 'error'); }
    }).finally(() => { document.getElementById('btnAssignConfirm').disabled = false; document.getElementById('btnAssignConfirm').textContent = 'Assign to Selected Node'; });
}

function unassignVideo() {
    if (curIdx < 0) return;
    const v = videos[curIdx];
    fetch('?api_action=tree_unassign', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ video_id: v.id }) })
    .then(r => r.json()).then(d => {
        if (d.status === 'ok') { assignmentCache[v.id] = null; updateAssignBadge(null); document.getElementById('btnUnassign').style.display = 'none'; document.getElementById('assignCurrentText').innerHTML = '<span style="color:var(--muted);">No assignment yet</span>'; if (typeof Toast !== 'undefined') Toast.show('Unassigned', 'success'); }
    });
}

// ════════════════════════════════════
// RICH VIDEO DETAILS MODAL
// ════════════════════════════════════
const closeModals = () => {
    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.classList.remove('active');
        if(m.id === 'videoDetailModal') { const v = document.getElementById('detailVideoPlayer'); if(v) { v.pause(); v.src = ""; } }
    });
};

function openVideoDetail() {
    if (curIdx < 0 || curIdx >= videos.length) return;
    const vid = videos[curIdx];
    if(!vid) return;

    currentDetailVideoId = vid.id;
    if (vjsPlayer && !vjsPlayer.paused()) vjsPlayer.pause();

    const p = document.getElementById('detailVideoPlayer');
    p.src = vid.url.startsWith('/') ? vid.url : '/' + vid.url;
    p.load();

    document.getElementById('detailVideoName').textContent = vid.name;
    document.getElementById('detailMeta').innerHTML = `
        <span><strong>ID:</strong> ${vid.id}</span>
        <span><strong>Cat:</strong> ${vid.category_name || '-'}</span>
        <span><strong>Size:</strong> ${fmtSize(vid.file_size)}</span>
    `;
    document.getElementById('detailDescSnippet').textContent = vid.description ? vid.description.substring(0, 50) + '...' : 'None';

    document.getElementById('detailDescTrigger').onclick = () => {
        document.getElementById('descModalBody').textContent = vid.description || 'No description.';
        document.getElementById('descModal').classList.add('active');
    };

    let animaticBtn = '';
    if(vid.animatic_id) {
        animaticBtn = `<button class="btn btn-sm btn-outline-primary edit-animatic-btn" data-animatic-id="${vid.animatic_id}">🎬<br>Animatic</button>`;
    }

    document.getElementById('detailActionButtons').innerHTML = `
        <button class="btn btn-sm btn-outline-success extract-frame-btn" data-url="${esc(vid.url)}" data-id="${vid.id}">✂️<br>Frame</button>
        ${animaticBtn}
        <button class="btn btn-sm btn-outline-success rembg-video-btn"
                data-id="${vid.id}"
                data-animatic-id="${vid.animatic_id || ''}"
                data-thumbnail="${esc(vid.thumbnail || '')}">◩<br>Rembg</button>
        <button class="btn btn-sm btn-outline-secondary regen-thumb-btn" data-id="${vid.id}">🌇<br>Thumb</button>
        <button class="btn btn-sm btn-outline-secondary edit-video-btn" data-id="${vid.id}">✏️<br>Edit</button>
        <button class="btn btn-sm btn-outline-secondary add-playlist-btn" data-id="${vid.id}">📃<br>Playlst</button>
        <a href="${esc(vid.url.startsWith('/') ? vid.url : '/' + vid.url)}" download class="btn btn-sm btn-outline-primary" style="text-decoration:none;" target="_blank">⬇️<br>Dwnload</a>
        <button class="btn btn-sm btn-outline-danger delete-video-btn" data-id="${vid.id}">❌<br>Delete</button>
    `;

    document.getElementById('videoDetailModal').classList.add('active');
}
window.openVideoDetail = openVideoDetail;

document.addEventListener('click', e => {
    if (e.target.classList.contains('close-modal')) {
        const modal = e.target.closest('.modal-overlay');
        if (modal) modal.classList.remove('active');
        if(modal && modal.id === 'videoDetailModal') { const v = document.getElementById('detailVideoPlayer'); if(v) { v.pause(); v.src = ""; } }
        return;
    }
    if (e.target.classList.contains('modal-overlay')) { closeModals(); return; }

    const btn = e.target.closest('button');
    if (!btn) return;

    let id = btn.dataset.id;
    let url = btn.dataset.url;
    if(!id && !btn.dataset.animaticId) return;

    const vid = videos.find(v => v.id == id);

    if (btn.classList.contains('extract-frame-btn')) {
        const p = document.getElementById('detailVideoPlayer');
        if(p) p.pause();
        if(window.VideoFrameExtractor) window.VideoFrameExtractor.open(url, id);
    }

    if (btn.classList.contains('edit-animatic-btn')) {
        const animId = btn.dataset.animaticId;
        if(window.showEntityFormInModal && animId) { window.showEntityFormInModal('animatics', animId); }
        else { window.open('animatics_crud.php?id=' + animId, '_blank'); }
    }

    if (btn.classList.contains('edit-video-btn') && vid) {
        document.getElementById('editVideoId').value = id;
        document.getElementById('editName').value = vid.name;
        document.getElementById('editDescription').value = vid.description || '';
        document.getElementById('editCategory').value = vid.category_id || '';
        document.getElementById('editActive').checked = vid.is_active == 1;
        document.getElementById('editModal').classList.add('active');
    }

    if (btn.classList.contains('delete-video-btn')) {
        if (confirm('Delete video?')) {
            fetch('video_admin_api.php?action=delete_video', {method:'POST', body:JSON.stringify({id})})
            .then(r=>r.json()).then(d=>{
                if(d.status==='ok') { if(typeof Toast !== 'undefined') Toast.show('Deleted'); document.getElementById('videoDetailModal').classList.remove('active'); if (curCandId) selectCandidate(curCandId, curCandName); }
                else { if(typeof Toast !== 'undefined') Toast.show(d.message,'error'); }
            });
        }
    }

    if (btn.classList.contains('regen-thumb-btn')) {
        if (confirm('Regenerate thumbnail?')) {
            const origHtml = btn.innerHTML; btn.disabled = true; btn.innerHTML = '...';
            fetch('video_admin_api.php?action=regenerate_thumbnail', { method: 'POST', body: JSON.stringify({ id }) })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'ok') { if(typeof Toast !== 'undefined') Toast.show('Updated', 'success'); const img = document.querySelector(`.np-card[data-idx="${curIdx}"] img`); if(img && d.thumbnail_url) { img.src = d.thumbnail_url; if(vid) vid.thumbnail = d.thumbnail_url; } }
                else { if(typeof Toast !== 'undefined') Toast.show(d.message || 'Error', 'error'); }
            }).finally(() => { btn.disabled = false; btn.innerHTML = origHtml; });
        }
    }

    if (btn.classList.contains('add-playlist-btn')) {
        document.getElementById('addToPlaylistVideoId').value = id;
        const div = document.getElementById('playlistCheckboxes');
        div.innerHTML = playlists.map(p => `<label style="display:block;padding:8px;cursor:pointer;"><input type="checkbox" value="${p.id}" style="margin-right:8px;">${esc(p.name)}</label>`).join('');
        fetch('video_admin_api.php?action=get_video&id='+id).then(r=>r.json()).then(d=>{
            if(d.status==='ok' && d.video.playlists) { d.video.playlists.forEach(pl => { const cb = div.querySelector(`input[value="${pl.id}"]`); if(cb) cb.checked=true; }); }
            document.getElementById('addToPlaylistModal').classList.add('active');
        });
    }

    if (btn.classList.contains('rembg-video-btn')) {
        openRembgModal(id, btn.dataset.animaticId || null, btn.dataset.thumbnail || null);
    }
});

document.getElementById('editForm').onsubmit = e => {
    e.preventDefault();
    fetch('video_admin_api.php?action=update_video', { method:'POST', body:JSON.stringify({ id: document.getElementById('editVideoId').value, name: document.getElementById('editName').value, description: document.getElementById('editDescription').value, category_id: document.getElementById('editCategory').value || null, is_active: document.getElementById('editActive').checked ? 1:0 }) })
    .then(r=>r.json()).then(d=>{
        if(d.status==='ok') {
            document.getElementById('editModal').classList.remove('active');
            if (typeof Toast !== 'undefined') Toast.show('Saved');
            const updatedId = document.getElementById('editVideoId').value;
            const v = videos.find(x => x.id == updatedId);
            if (v) { v.name = document.getElementById('editName').value; v.description = document.getElementById('editDescription').value; v.category_id = document.getElementById('editCategory').value || null; v.is_active = document.getElementById('editActive').checked ? 1:0; const cat = categories.find(c => c.id == v.category_id); v.category_name = cat ? cat.name : null; if (currentDetailVideoId == v.id) openVideoDetail(); if (curIdx >= 0 && videos[curIdx].id == v.id) nowTitle.textContent = `${curIdx + 1}. ${v.name || 'Video #' + v.id}`; }
        } else if (typeof Toast !== 'undefined') Toast.show(d.message, 'error');
    });
};

document.getElementById('addToPlaylistSubmitBtn').onclick = () => {
    const vid = document.getElementById('addToPlaylistVideoId').value;
    const pids = Array.from(document.querySelectorAll('#playlistCheckboxes input:checked')).map(cb => cb.value);
    fetch('video_admin_api.php?action=sync_video_playlists', { method:'POST', body:JSON.stringify({video_id:vid, playlist_ids:pids}) })
    .then(r=>r.json()).then(d=>{ if(d.status==='ok') { document.getElementById('addToPlaylistModal').classList.remove('active'); if (typeof Toast !== 'undefined') Toast.show('Playlists updated'); } else if (typeof Toast !== 'undefined') Toast.show(d.message, 'error'); });
};

// ════════════════════════════════════
// REMBG MODAL
// ════════════════════════════════════

function openRembgModal(videoId, animaticId, thumbnailUrl) {
    rembgTargetVideoId    = videoId;
    rembgTargetAnimaticId = animaticId;
    rembgThumbnailUrl     = thumbnailUrl;
    setRembgColor('#00FB00');
    document.getElementById('rembgVideoId').textContent    = '#' + videoId;
    document.getElementById('rembgAnimaticId').textContent = animaticId ? '#' + animaticId : 'none';
    document.getElementById('rembgModal').classList.add('active');
}

function closeRembgModal() { document.getElementById('rembgModal').classList.remove('active'); }

function setRembgColor(hex) {
    hex = hex.toUpperCase();
    if (!/^#[0-9A-F]{6}$/.test(hex)) return;
    document.getElementById('rembgHexInput').value = hex;
    document.getElementById('rembgSwatch').style.background = hex;
}

function onRembgHexInput() {
    const val = document.getElementById('rembgHexInput').value.trim();
    if (/^#[0-9A-Fa-f]{6}$/.test(val)) document.getElementById('rembgSwatch').style.background = val;
}

function syncSwatchFromInput() { onRembgHexInput(); }

function confirmRembg() {
    const hex = document.getElementById('rembgHexInput').value.trim().toUpperCase();
    if (!/^#[0-9A-F]{6}$/.test(hex)) { if (typeof Toast !== 'undefined') Toast.show('Invalid hex color', 'error'); return; }
    if (!rembgTargetVideoId) return;
    const btn = document.getElementById('btnRembgConfirm');
    const origText = btn.textContent;
    btn.disabled = true; btn.textContent = 'Queuing…';
    fetch('video_admin_api.php?action=queue_rembg', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: rembgTargetVideoId, chromakey_color: hex }) })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'ok') { closeRembgModal(); if (typeof Toast !== 'undefined') Toast.show('Background removal queued ✓', 'success'); }
        else { if (typeof Toast !== 'undefined') Toast.show(d.message || 'Error', 'error'); }
    })
    .finally(() => { btn.disabled = false; btn.textContent = origText; });
}

// ════════════════════════════════════
// COLOR SAMPLER MODAL
// ════════════════════════════════════

const SAMPLE_RADIUS = 10;

function openSamplerModal() {
    if (!rembgThumbnailUrl) { if (typeof Toast !== 'undefined') Toast.show('No thumbnail available', 'error'); return; }
    samplerPickedColor = document.getElementById('rembgHexInput').value.trim() || '#00FB00';
    document.getElementById('samplerSwatch').style.background = samplerPickedColor;
    document.getElementById('samplerHex').textContent = samplerPickedColor.toUpperCase();
    document.getElementById('samplerModal').classList.add('active');
    requestAnimationFrame(() => { loadSamplerImage(rembgThumbnailUrl); });
}

function closeSamplerModal() { document.getElementById('samplerModal').classList.remove('active'); }

function loadSamplerImage(url) {
    const canvas = document.getElementById('samplerCanvas');
    const wrap   = document.getElementById('samplerCanvasWrap');
    const ctx    = canvas.getContext('2d');
    const img    = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = function () {
        samplerImg = img;
        const scale = Math.min(wrap.clientWidth / img.naturalWidth, wrap.clientHeight / img.naturalHeight);
        canvas.width  = Math.round(img.naturalWidth  * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    };
    img.onerror = function () { if (typeof Toast !== 'undefined') Toast.show('Could not load thumbnail', 'error'); };
    img.src = url;
}

function sampleCanvasAt(canvasX, canvasY) {
    const canvas = document.getElementById('samplerCanvas');
    const ctx    = canvas.getContext('2d');
    const r = SAMPLE_RADIUS;
    let totalR = 0, totalG = 0, totalB = 0, count = 0;
    const x0 = Math.max(0, Math.round(canvasX - r)), y0 = Math.max(0, Math.round(canvasY - r));
    const x1 = Math.min(canvas.width - 1, Math.round(canvasX + r)), y1 = Math.min(canvas.height - 1, Math.round(canvasY + r));
    const imageData = ctx.getImageData(x0, y0, x1 - x0 + 1, y1 - y0 + 1);
    const data = imageData.data;
    for (let py = y0; py <= y1; py++) {
        for (let px = x0; px <= x1; px++) {
            const dx = px - canvasX, dy = py - canvasY;
            if (dx * dx + dy * dy <= r * r) { const idx = ((py - y0) * (x1 - x0 + 1) + (px - x0)) * 4; totalR += data[idx]; totalG += data[idx + 1]; totalB += data[idx + 2]; count++; }
        }
    }
    if (count === 0) return null;
    return '#' + [Math.round(totalR/count), Math.round(totalG/count), Math.round(totalB/count)].map(v => v.toString(16).padStart(2,'0')).join('').toUpperCase();
}

function drawIndicator(canvasX, canvasY) {
    const canvas = document.getElementById('samplerCanvas');
    const ctx    = canvas.getContext('2d');
    if (samplerImg) ctx.drawImage(samplerImg, 0, 0, canvas.width, canvas.height);
    ctx.beginPath(); ctx.arc(canvasX, canvasY, SAMPLE_RADIUS + 2, 0, Math.PI * 2); ctx.strokeStyle = 'rgba(0,0,0,0.7)'; ctx.lineWidth = 2.5; ctx.stroke();
    ctx.beginPath(); ctx.arc(canvasX, canvasY, SAMPLE_RADIUS, 0, Math.PI * 2); ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 1.5; ctx.stroke();
}

function handleSamplerTap(clientX, clientY) {
    const canvas = document.getElementById('samplerCanvas');
    const rect   = canvas.getBoundingClientRect();
    const hex = sampleCanvasAt(clientX - rect.left, clientY - rect.top);
    if (!hex) return;
    samplerPickedColor = hex;
    drawIndicator(clientX - rect.left, clientY - rect.top);
    document.getElementById('samplerSwatch').style.background = hex;
    document.getElementById('samplerHex').textContent = hex;
}

document.getElementById('samplerCanvas').addEventListener('click', e => handleSamplerTap(e.clientX, e.clientY));
document.getElementById('samplerCanvas').addEventListener('touchend', function(e) { e.preventDefault(); const t = e.changedTouches[0]; handleSamplerTap(t.clientX, t.clientY); }, { passive: false });

function useSampledColor() { setRembgColor(samplerPickedColor); closeSamplerModal(); }

// Expose globals
window.openAssignModal  = openAssignModal;
window.closeAssignModal = closeAssignModal;
window.createTreeNode   = createTreeNode;
window.confirmAssign    = confirmAssign;
window.unassignVideo    = unassignVideo;
window.closeRembgModal   = closeRembgModal;
window.onRembgHexInput   = onRembgHexInput;
window.syncSwatchFromInput = syncSwatchFromInput;
window.confirmRembg      = confirmRembg;
window.openSamplerModal  = openSamplerModal;
window.closeSamplerModal = closeSamplerModal;
window.useSampledColor   = useSampledColor;

// ── Download ZIP ──
async function downloadCandidateZip() {
    if (!curCandId || !videos.length) return;
    btnDl.classList.add('loading'); btnDl.textContent = '…';
    try {
        const res = await fetch(`?api_action=zip_candidate_videos&cand_id=${curCandId}`).then(r => r.json());
        if (res.status !== 'ok') throw new Error(res.message || 'ZIP failed');
        const a = document.createElement('a');
        a.href     = `?api_action=zip_download&zip_name=${encodeURIComponent(res.zip_name)}`;
        a.download = res.zip_name;
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
    } catch (err) { if (typeof Toast !== 'undefined') Toast.show(err.message, 'error'); else alert(err.message); }
    finally { btnDl.classList.remove('loading'); btnDl.textContent = '⬇'; }
}
window.downloadCandidateZip = downloadCandidateZip;

// ── Keyboard ──
document.addEventListener('keydown', e => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    switch (e.key) {
        case 'ArrowRight': e.preventDefault(); navigate(1); break;
        case 'ArrowLeft':  e.preventDefault(); navigate(-1); break;
        case 'a': case 'A': e.preventDefault(); openAssignModal(); break;
        case ' ': e.preventDefault(); togglePlayPause(); break;
        case 'Escape': closeSidebar(); closeModals(); closeRembgModal(); closeSamplerModal(); break;
    }
});

// ── Boot ──
window.loadCandidates = loadCandidates;
seqPrevBtn.addEventListener('click', () => loadCandidates(seqPage - 1));
seqNextBtn.addEventListener('click', () => loadCandidates(seqPage + 1));
seqPageInput.addEventListener('change', () => loadCandidates(parseInt(seqPageInput.value)));
seqPageInput.addEventListener('keydown', e => { if (e.key === 'Enter') { loadCandidates(parseInt(seqPageInput.value)); seqPageInput.blur(); } });

const sidebarSearchInput = document.getElementById('npSidebarSearch');
sidebarSearchInput.addEventListener('input', () => {
    clearTimeout(seqSearchTimer);
    seqSearchTimer = setTimeout(() => {
        seqSearch = sidebarSearchInput.value.trim();
        seqPage = 1;
        loadCandidates(1);
    }, 280);
});
sidebarSearchInput.addEventListener('keydown', e => {
    if (e.key === 'Escape') { sidebarSearchInput.value = ''; seqSearch = ''; seqPage = 1; loadCandidates(1); }
});

loadCandidates(1);

})();
</script>
<?php
$spw->renderLayout(ob_get_clean(), $pageTitle);
?>
