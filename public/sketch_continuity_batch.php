<?php
// public/sketch_continuity_batch.php
// Character Continuity Batch Enqueuer
// Cloned from sketch_continuity.php — replaces "Rewrite Scene" with "Enqueue"
// instead of calling AI directly, inserts jobs into continuity_jobs table.
// Flyout sidebar items have checkboxes for multi-select batch enqueueing.
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;

$conn = $spw->getEntityManager()->getConnection();

// --- HELPERS ---
function fetchSequencesWithSketches($conn, $limit, $offset) {
    $sequences = $conn->fetchAllAssociative("SELECT id, name, sequence_data, created_at FROM narrative_sequences ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

    $allSketchIds = [];
    foreach ($sequences as &$seq) {
        $data = json_decode($seq['sequence_data'], true) ?? [];
        $seq['parsed_sketches'] = [];
        foreach ($data as $item) {
            if (is_numeric($item)) $seq['parsed_sketches'][] = (int)$item;
            elseif (is_array($item) && isset($item['sketch_id'])) $seq['parsed_sketches'][] = (int)$item['sketch_id'];
            elseif (is_array($item) && isset($item['id'])) $seq['parsed_sketches'][] = (int)$item['id'];
        }
        $allSketchIds = array_merge($allSketchIds, $seq['parsed_sketches']);
    }
    unset($seq);

    $sketchMap = [];
    if (!empty($allSketchIds)) {
        $idsStr = implode(',', array_unique($allSketchIds));
        if (!empty($idsStr)) {
            $rows = $conn->fetchAllAssociative("SELECT id, name, mood FROM sketches WHERE id IN ($idsStr)");
            foreach ($rows as $r) $sketchMap[$r['id']] = $r;
        }
    }

    $result = [];
    foreach ($sequences as $seq) {
        $items = [];
        foreach ($seq['parsed_sketches'] as $sid) {
            if (isset($sketchMap[$sid])) $items[] = $sketchMap[$sid];
        }
        $seq['items'] = $items;
        $result[] = $seq;
    }
    return $result;
}

function fetchFlatSketches($conn, $limit, $offset) {
    return $conn->fetchAllAssociative("SELECT id, name, mood FROM sketches ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
}

// --- API HANDLER ---
if (isset($_POST['action'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');

    try {
        $page   = max(1, intval($_POST['page'] ?? 1));
        $limit  = 15;
        $offset = ($page - 1) * $limit;

        // 1. FETCH SIDEBAR
        if ($_POST['action'] === 'fetch_sidebar') {
            $mode   = $_POST['mode'] ?? 'flat';
            $search = trim($_POST['search'] ?? '');

            if ($mode === 'sequences') {
                $total = $conn->fetchOne("SELECT COUNT(*) FROM narrative_sequences");
                $items = fetchSequencesWithSketches($conn, $limit, $offset);
            } else {
                if ($search !== '') {
                    $searchParam = "%$search%";
                    if (is_numeric($search)) {
                        $total = $conn->fetchOne("SELECT COUNT(*) FROM sketches WHERE id = ? OR name LIKE ?", [$search, $searchParam]);
                        $items = $conn->fetchAllAssociative("SELECT id, name, mood FROM sketches WHERE id = ? OR name LIKE ? ORDER BY created_at DESC LIMIT $limit OFFSET $offset", [$search, $searchParam]);
                    } else {
                        $total = $conn->fetchOne("SELECT COUNT(*) FROM sketches WHERE name LIKE ?", [$searchParam]);
                        $items = $conn->fetchAllAssociative("SELECT id, name, mood FROM sketches WHERE name LIKE ? ORDER BY created_at DESC LIMIT $limit OFFSET $offset", [$searchParam]);
                    }
                } else {
                    $total = $conn->fetchOne("SELECT COUNT(*) FROM sketches");
                    $items = fetchFlatSketches($conn, $limit, $offset);
                }
            }

            echo json_encode([
                'ok' => true, 'mode' => $mode, 'items' => $items,
                'total_pages' => ceil($total / $limit), 'current_page' => $page, 'total_items' => $total
            ]);
            exit;
        }

        // 2. GET SKETCH + FRAMES
        if ($_POST['action'] === 'get_sketch') {
            $id = (int)$_POST['id'];
            $sk = $conn->fetchAssociative("SELECT * FROM sketches WHERE id = ?", [$id]);
            if (!$sk) throw new Exception("Sketch not found");

            $frames = $conn->fetchAllAssociative("
                SELECT id, filename FROM frames
                WHERE (entity_type = 'sketches' AND entity_id = ?)
                ORDER BY id ASC
            ", [$id]);

            $sk['frames'] = $frames;
            echo json_encode(['ok' => true, 'data' => $sk]);
            exit;
        }

        // 3. ENQUEUE BATCH — core new action
        // Accepts: sketch_ids[] (array), character_ids[] (array), generator_id
        // Inserts rows into continuity_jobs (IGNORE duplicates via UNIQUE KEY uq_cj_sketch_char)
        if ($_POST['action'] === 'enqueue_batch') {
            $sketchIds  = array_map('intval', (array)($_POST['sketch_ids']  ?? []));
            $charIds    = array_map('intval', (array)($_POST['character_ids'] ?? []));
            $contGenId  = (int)($_POST['generator_id'] ?? 0);

            if (empty($sketchIds))  throw new Exception("No sketches selected.");
            if (empty($charIds))    throw new Exception("No characters selected.");
            if ($contGenId <= 0)    throw new Exception("No generator selected.");

            // Validate generator exists
            $genExists = $conn->fetchOne(
                "SELECT id FROM generator_config WHERE id = ? AND active = 1",
                [$contGenId]
            );
            if (!$genExists) throw new Exception("Selected generator config not found or inactive.");

            $inserted  = 0;
            $skipped   = 0;

            foreach ($sketchIds as $sketchId) {
                $sortOrder = 0;
                foreach ($charIds as $charId) {
                    try {
                        // FIX: use the return value of executeStatement to detect INSERT IGNORE skips.
                        // executeStatement returns affected row count: 1 = inserted, 0 = duplicate ignored.
                        $affected = $conn->executeStatement(
                            "INSERT IGNORE INTO continuity_jobs
                             (sketch_id, character_id, sort_order, status, cont_gen_id, created_at, updated_at)
                             VALUES (?, ?, ?, 'pending', ?, NOW(), NOW())",
                            [$sketchId, $charId, $sortOrder, $contGenId]
                        );
                        if ($affected > 0) {
                            $inserted++;
                        } else {
                            $skipped++;
                        }
                    } catch (\Exception $e) {
                        $skipped++;
                    }
                    $sortOrder++;
                }
            }

            echo json_encode([
                'ok'           => true,
                'enqueued'     => $inserted,
                'skipped'      => $skipped,
                'sketch_count' => count($sketchIds),
                'char_count'   => count($charIds),
                'message'      => "Enqueued jobs for " . count($sketchIds) . " sketch(es) × " . count($charIds) . " character(s)."
            ]);
            exit;
        }

        // 4. GET QUEUE STATS — summary for the status bar
        if ($_POST['action'] === 'get_queue_stats') {
            $stats = $conn->fetchAssociative(
                "SELECT
                    COUNT(*) AS total,
                    SUM(status = 'pending')  AS pending,
                    SUM(status = 'running')  AS running,
                    SUM(status = 'done')     AS done,
                    SUM(status = 'failed')   AS failed,
                    SUM(status = 'skipped')  AS skipped
                 FROM continuity_jobs"
            );
            echo json_encode(['ok' => true, 'stats' => $stats]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// --- INITIAL VIEW DATA ---
$chars        = $conn->fetchAllAssociative("SELECT id, name FROM characters ORDER BY name");
$gens         = $conn->fetchAllAssociative("SELECT id, title FROM generator_config WHERE active = 1 ORDER BY title");
$defaultGenId = 111;
$initSketchId = (int)($_GET['sketch_id'] ?? 0);

// Resolve the self URL once in PHP so JS can use it reliably (avoids empty-string POST targets)
$selfUrl = htmlspecialchars($_SERVER['PHP_SELF'] ?? basename(__FILE__));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.5">
    <title>Continuity Batch Enqueuer</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/photoswipe@5.4.3/dist/photoswipe.css">
    <link rel="stylesheet" href="/css/base.css">
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <style>
        body {
            background: #0a0a0f; color: #e2e2f0; font-family: 'DM Mono', monospace;
            padding: 0; margin: 0; overflow: hidden; font-size: 14px;
            height: 100vh;
        }

        /* ── LAYOUT ── */
        .app-container {
            display: flex;
            height: 90vh;
            width: 100vw;
            position: relative;
        }

        /* ── NAV TOGGLE (Fixed Top Left) ── */
        .nav-toggle-btn {
            position: absolute; top: 10px; left: 10px; z-index: 100;
            width: 40px; height: 40px; border-radius: 6px;
            background: #1a1a1a; border: 1px solid #333; color: #fff;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 1.2rem; transition: 0.2s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
        }
        .nav-toggle-btn:hover { border-color: #f59e0b; color: #f59e0b; }

        /* ── BATCH BADGE on toggle btn ── */
        .nav-badge {
            position: absolute; top: -6px; right: -6px;
            min-width: 18px; height: 18px; padding: 0 4px;
            background: #f59e0b; color: #000; border-radius: 9px;
            font-size: 0.65rem; font-weight: bold; display: none;
            align-items: center; justify-content: center;
        }
        .nav-badge.visible { display: flex; }

        /* ── FLYOUT SIDEBAR ── */
        .flyout-sidebar {
            position: absolute; top: 0; left: 0; bottom: 0;
            width: 360px; background: #111; border-right: 1px solid #333;
            z-index: 90; transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(0.25, 1, 0.5, 1);
            display: flex; flex-direction: column;
            padding-top: 60px;
            box-shadow: 10px 0 30px rgba(0,0,0,0.5);
        }
        .flyout-sidebar.active { transform: translateX(0); }

        .sidebar-header { padding: 10px 15px; border-bottom: 1px solid #333; background: #151515; }
        .sidebar-toggle { display: flex; background: #000; border-radius: 6px; padding: 2px; margin-bottom: 10px; }
        .toggle-btn { flex: 1; padding: 6px; text-align: center; cursor: pointer; border-radius: 4px; font-size: 0.8rem; color: #666; font-weight: bold; }
        .toggle-btn.active { background: #333; color: #fff; }

        /* Selection toolbar inside sidebar */
        .sel-toolbar {
            display: flex; gap: 6px; align-items: center;
            padding: 8px 15px; border-bottom: 1px solid #222;
            background: #0f0f13; flex-shrink: 0;
        }
        .sel-count {
            flex: 1; font-size: 0.78rem; color: #f59e0b; font-weight: bold;
        }
        .sel-btn {
            padding: 4px 8px; font-size: 0.72rem; background: #222; border: 1px solid #444;
            color: #aaa; border-radius: 4px; cursor: pointer; white-space: nowrap;
        }
        .sel-btn:hover { border-color: #f59e0b; color: #f59e0b; }

        .sidebar-list { flex: 1; overflow-y: auto; padding: 8px; }

        /* Item row with checkbox */
        .item-row {
            padding: 8px 10px; border-bottom: 1px solid #1e1e1e;
            cursor: pointer; border-left: 3px solid transparent;
            display: flex; align-items: center; gap: 8px;
            transition: background 0.15s;
        }
        .item-row:hover { background: #1a1a22; }
        .item-row.active { background: #1e1e2e; border-left-color: #f59e0b; }
        .item-row.selected { background: #1c1c10; border-left-color: #f59e0b; }
        .item-row.selected.active { background: #252512; }

        .item-cb {
            flex-shrink: 0; width: 16px; height: 16px;
            accent-color: #f59e0b; cursor: pointer;
        }
        .item-text { flex: 1; min-width: 0; }
        .item-text .iname { font-weight: bold; font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .item-text .imood { font-size: 0.7rem; color: #666; margin-top: 1px; }

        /* Sequence groups */
        .seq-group { border: 1px solid #2a2a2a; margin-bottom: 5px; border-radius: 4px; overflow: hidden; }
        .seq-header { padding: 8px; background: #1a1a1a; cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 6px; }
        .seq-header:hover { background: #222; }
        .seq-body { display: none; background: #111; padding-left: 10px; }
        .seq-group.open .seq-body { display: block; }

        .pagination-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 6px; }
        .pager-btn { background: #333; border: 1px solid #444; color: #fff; width: 24px; height: 24px; cursor: pointer; border-radius: 3px; }
        .pager-btn:hover { border-color: #f59e0b; }

        /* ── MAIN CONTENT (2 COLUMNS) ── */
        .main-grid {
            flex: 1; display: grid;
            grid-template-columns: 360px 1fr;
            height: 100%;
        }

        /* COL 1: CONFIG */
        .col-config {
            background: #141419; border-right: 1px solid #333;
            display: flex; flex-direction: column; padding: 15px; padding-top: 60px;
            gap: 15px; overflow: hidden;
        }
        .char-list-container { flex: 1; min-height: 0; display: flex; flex-direction: column; }
        .char-list-scroll { flex: 1; overflow-y: auto; background: #0f0f13; border: 1px solid #333; border-radius: 6px; padding: 5px; }
        .char-item { padding: 6px; border-bottom: 1px solid #1c1c1c; display: flex; align-items: center; gap: 8px; cursor: pointer; }
        .char-item:hover { background: #1a1a22; }
        .char-item input { accent-color: #f59e0b; }

        .config-footer { flex-shrink: 0; padding-top: 10px; border-top: 1px solid #222; display: flex; flex-direction: column; gap: 10px; }

        /* ── ENQUEUE BUTTON — amber instead of purple ── */
        .btn {
            padding: 15px; background: #f59e0b; color: #000; border: none;
            border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%;
            font-size: 1rem; transition: 0.2s; font-family: inherit;
        }
        .btn:hover { background: #d97706; }
        .btn:disabled { opacity: 0.45; cursor: not-allowed; }
        .btn.batch-active {
            background: #f59e0b;
            box-shadow: 0 0 0 2px #f59e0b44;
        }

        /* Batch sketch pill strip */
        .sketch-pills {
            display: flex; flex-wrap: wrap; gap: 4px;
            max-height: 64px; overflow-y: auto;
            background: #0f0f13; border: 1px solid #333; border-radius: 6px;
            padding: 6px; min-height: 32px;
        }
        .sketch-pill {
            background: #f59e0b22; border: 1px solid #f59e0b66;
            color: #f59e0b; border-radius: 4px; padding: 2px 6px;
            font-size: 0.7rem; display: flex; align-items: center; gap: 4px;
            white-space: nowrap;
        }
        .sketch-pill .rm { cursor: pointer; opacity: 0.6; }
        .sketch-pill .rm:hover { opacity: 1; }
        .pills-empty { color: #444; font-size: 0.75rem; padding: 2px 4px; }

        /* Queue stats bar */
        .queue-stats {
            display: flex; gap: 8px; flex-wrap: wrap;
            padding: 6px 10px; background: #0c0c10; border-top: 1px solid #222;
            font-size: 0.72rem;
        }
        .stat-chip {
            padding: 2px 8px; border-radius: 10px; font-weight: bold;
        }
        .stat-pending  { background: #1e1e00; color: #f59e0b; border: 1px solid #f59e0b44; }
        .stat-running  { background: #001e1e; color: #22d3ee; border: 1px solid #22d3ee44; }
        .stat-done     { background: #001e00; color: #10b981; border: 1px solid #10b98144; }
        .stat-failed   { background: #1e0000; color: #f87171; border: 1px solid #f8717144; }
        .stat-total    { background: #1a1a1a; color: #888; border: 1px solid #333; }

        /* COL 2: EDITOR */
        .col-editor {
            background: #0a0a0f; display: flex; flex-direction: column; padding: 20px;
            overflow: hidden; gap: 15px; min-width: 0;
        }

        .visual-stage {
            flex: 0 0 30%; min-height: 250px; min-width: 0;
            background: #000; border: 1px solid #333; border-radius: 6px;
            overflow: hidden; position: relative;
            display: flex; flex-direction: column;
            padding-top: 50px;
        }
        .visual-header {
            padding: 8px 15px; background: rgba(0,0,0,0.5);
            position: absolute; top: 0; left: 0; right: 0; z-index: 10;
            display: flex; justify-content: space-between; align-items: center;
        }

        .swiper { width: 100%; max-width: 360px; aspect-ratio: 1 / 1; margin: auto; margin-top: 40px; margin-bottom: 10px; }
        .swiper-slide {
            display: flex; align-items: center; justify-content: center;
            background: #000; border-radius: 4px; overflow: hidden; border: 1px solid #333;
            position: relative; box-sizing: border-box;
        }
        .swiper-slide a {
            display: flex; align-items: center; justify-content: center;
            width: 100%; height: 100%;
        }
        .swiper-slide img { width: 100%; height: 100%; object-fit: contain; display: block; }

        .text-stage { flex: 1; display: flex; flex-direction: column; min-height: 0; }
        .editor-header { margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        textarea {
            flex: 1; width: 100%; background: #080808; border: 1px solid #333; color: #ddd;
            padding: 20px; font-family: inherit; font-size: 1.1rem; line-height: 1.6; resize: none; border-radius: 6px;
        }
        textarea:focus { outline: none; border-color: #f59e0b; }

        h3 { margin: 0 0 8px 0; color: #f59e0b; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        .select-input { width: 100%; padding: 10px; background: #222; border: 1px solid #333; color: #fff; border-radius: 4px; font-family: inherit; }

        /* Toast */
        .toast {
            position: fixed; bottom: 20px; right: 20px; z-index: 9999;
            padding: 12px 18px; border-radius: 6px; font-size: 0.85rem;
            font-weight: bold; opacity: 0; transform: translateY(10px);
            transition: all 0.3s; pointer-events: none; max-width: 320px;
        }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { background: #064e3b; border: 1px solid #10b98166; color: #6ee7b7; }
        .toast.error   { background: #450a0a; border: 1px solid #f8717166; color: #fca5a5; }

        /* Frame Modal */
        .view-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 100000; display: none; align-items: center; justify-content: center; }
        .view-modal.active { display: flex; }
        .view-modal-content { width: 95vw; height: 95vh; background: #000; position: relative; border: 1px solid #444; box-shadow: 0 0 30px rgba(0,0,0,0.5); }
        .view-close { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: rgba(0,0,0,0.8); color: #fff; border: 1px solid #444; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; z-index: 200; transition: all 0.2s; }
        .view-close:hover { background: #fff; color: #000; }
        iframe.frame-viewer { width: 100%; height: 100%; border: none; }

        .f-view-btn { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; opacity: 0; transition: all 0.2s; font-size: 14px; }
        .swiper-slide:hover .f-view-btn { opacity: 1; }
        .f-view-btn:hover { background: #fff; border-color: #fff; color: #000; }

        /* Preview mode hint */
        .preview-hint {
            font-size: 0.72rem; color: #555; padding: 4px 0;
            border-top: 1px solid #1a1a1a; margin-top: 4px;
        }
        .preview-hint span { color: #f59e0b; }
    </style>

    <script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</head>
<body>

<!-- Flyout Toggle Button -->
<div class="nav-toggle-btn" style="margin-left:70px;" onclick="toggleSidebar()">
    <i class="bi bi-list"></i>
    <span class="nav-badge" id="navBadge">0</span>
</div>

<div class="app-container">

    <!-- LEFT: FLYOUT NAVIGATOR -->
    <div class="flyout-sidebar" id="flyoutSidebar">
        <div class="sidebar-header">
            <div class="sidebar-toggle">
                <div class="toggle-btn active" id="btnModeFlat" onclick="setMode('flat')">All Sketches</div>
                <div class="toggle-btn" id="btnModeSeq" onclick="setMode('sequences')">Sequences</div>
            </div>
            <div id="searchBarContainer" style="margin-bottom: 10px;">
                <input type="text" id="sidebarSearch" placeholder="Search by ID or Name..."
                       style="width: 100%; padding: 5px; background: #222; border: 1px solid #333; color: #fff; border-radius: 4px; box-sizing: border-box; font-family: inherit;">
            </div>
            <div class="pagination-bar">
                <button class="pager-btn" onclick="changePage(-1)">❮</button>
                <span style="font-size:0.8rem; color:#888;">Page
                    <input type="number" id="pageInput" style="width: 40px; background: #222; color: #fff; border: 1px solid #444; text-align: center; border-radius: 4px; margin: 0 4px; font-family: inherit;" value="1" min="1">
                    / <span id="totPage">1</span>
                </span>
                <button class="pager-btn" onclick="changePage(1)">❯</button>
            </div>
        </div>

        <!-- Selection toolbar -->
        <div class="sel-toolbar">
            <span class="sel-count" id="selCount">0 selected</span>
            <button class="sel-btn" onclick="selectAllVisible()">Select Page</button>
            <button class="sel-btn" onclick="clearSelection()">Clear</button>
            <button class="sel-btn" onclick="toggleSidebar()" style="color:#f59e0b; border-color:#f59e0b44;">Done ✓</button>
        </div>

        <div class="sidebar-list" id="sidebarContent">
            <div style="text-align:center; padding:20px; color:#666;">Loading...</div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="main-grid">

        <!-- CENTER: CONFIG -->
        <div class="col-config">
            <!-- Selected Sketches Pills -->
            <div>
                <h3>Selected Sketches</h3>
                <div class="sketch-pills" id="sketchPills">
                    <span class="pills-empty">No sketches selected — use the sidebar ☰</span>
                </div>
                <div class="preview-hint">Click a sketch to <span>preview</span> it. Check to <span>enqueue</span>.</div>
            </div>

            <!-- Characters -->
            <div class="char-list-container">
                <h3>Select Characters</h3>
                <div class="char-list-scroll">
                    <?php foreach ($chars as $c): ?>
                    <label class="char-item">
                        <input type="checkbox" name="chars[]" value="<?= $c['id'] ?>">
                        <span><?= htmlspecialchars($c['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="config-footer">
                <h3>Generator Config</h3>
                <select id="genConfig" class="select-input">
                    <?php foreach ($gens as $g): ?>
                    <option value="<?= $g['id'] ?>" <?= $g['id'] == $defaultGenId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['title']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn" id="enqueueBtn" onclick="enqueueBatch()" disabled>
                    <i class="bi bi-stack"></i> Enqueue Jobs
                </button>
            </div>
        </div>

        <!-- RIGHT: VISUALS & EDITOR -->
        <div class="col-editor">

            <!-- Top: Swiper Gallery (preview of last clicked sketch) -->
            <div class="visual-stage">
                <div class="visual-header">
                    <span style="color:#fff; font-weight:bold; font-size:0.9rem;" id="wkTitle">Click a sketch to preview</span>
                    <span style="color:#aaa; font-size:0.8rem;" id="wkId">#—</span>
                </div>
                <div class="swiper" id="sketchSwiper">
                    <div class="swiper-wrapper" id="swiperWrapper">
                        <div class="swiper-slide" style="color:#444;">No sketch selected</div>
                    </div>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-pagination"></div>
                </div>
            </div>

            <!-- Bottom: Scene Description (read-only preview) -->
            <div class="text-stage">
                <div class="editor-header">
                    <h3>Scene Description</h3>
                    <span id="wkMood" style="color:#888; font-size:0.8rem;"></span>
                </div>
                <textarea id="sceneDesc" placeholder="Select a sketch to preview its description..." readonly
                          style="color:#888; cursor:default;"></textarea>
            </div>

        </div>
    </div>
</div>

<!-- Queue Stats Bar -->
<div class="queue-stats" id="queueStats">
    <span style="color:#555; margin-right: 4px;">Queue:</span>
    <span class="stat-chip stat-total" id="qTotal">— total</span>
    <span class="stat-chip stat-pending" id="qPending">— pending</span>
    <span class="stat-chip stat-running" id="qRunning">— running</span>
    <span class="stat-chip stat-done" id="qDone">— done</span>
    <span class="stat-chip stat-failed" id="qFailed">— failed</span>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<!-- Frame View Modal -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>

<script>
// FIX: use explicit self URL resolved in PHP to avoid empty-string POST target ambiguity.
// An empty url: '' in $.ajax / $.post can resolve incorrectly on some Android Chrome versions
// when the page is loaded with query parameters (?sketch_id=N), stripping the path.
const SELF_URL = '<?= $selfUrl ?>';

// ── STATE ───────────────────────────────────────────────────────────────────
let sidebarMode  = 'flat';
let currentPage  = 1;
let searchQuery  = '';
let swiperInst   = null;
let previewSketchId = <?= $initSketchId ?>;

// selectedSketches: Map<id, {id, name}>
const selectedSketches = new Map();

window.updatePswpDims = function(img) {
    const a = img.closest('a');
    if (a) {
        a.setAttribute('data-pswp-width', img.naturalWidth);
        a.setAttribute('data-pswp-height', img.naturalHeight);
    }
};

// ── INIT ────────────────────────────────────────────────────────────────────
$(function() {
    loadSidebar();

    swiperInst = new Swiper('#sketchSwiper', {
        slidesPerView: 1, spaceBetween: 10, loop: true,
        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        pagination: { el: '.swiper-pagination' },
    });

    const savedGen = localStorage.getItem('continuity_batch_gen_id');
    if (savedGen) $('#genConfig').val(savedGen);
    $('#genConfig').change(function() { localStorage.setItem('continuity_batch_gen_id', $(this).val()); });

    if (previewSketchId > 0) loadSketchPreview(previewSketchId);
    if (previewSketchId === 0) $('#flyoutSidebar').addClass('active');

    $(document).on('click', '.seq-header', function() { $(this).closest('.seq-group').toggleClass('open'); });

    $('#pageInput').on('change', function() {
        let p = parseInt($(this).val());
        if (!isNaN(p) && p > 0) { currentPage = p; loadSidebar(); }
    });

    let searchTimeout = null;
    $('#sidebarSearch').on('input', function() {
        searchQuery = $(this).val(); currentPage = 1;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadSidebar, 300);
    });

    loadQueueStats();
    // Refresh stats every 30s
    setInterval(loadQueueStats, 30000);
});

// ── SIDEBAR ─────────────────────────────────────────────────────────────────
function toggleSidebar() {
    $('#flyoutSidebar').toggleClass('active');
}

function setMode(mode) {
    if (sidebarMode === mode) return;
    sidebarMode = mode; currentPage = 1;
    $('.toggle-btn').removeClass('active');
    $(`#btnMode${mode === 'flat' ? 'Flat' : 'Seq'}`).addClass('active');
    if (mode === 'flat') { $('#searchBarContainer').show(); }
    else { $('#searchBarContainer').hide(); searchQuery = ''; $('#sidebarSearch').val(''); }
    loadSidebar();
}

function changePage(d) {
    currentPage = Math.max(1, currentPage + d);
    loadSidebar();
}

function loadSidebar() {
    $('#sidebarContent').css('opacity', '0.5');
    // FIX: post to SELF_URL instead of '' to avoid URL resolution issues
    $.post(SELF_URL, { action: 'fetch_sidebar', mode: sidebarMode, page: currentPage, search: searchQuery }, function(res) {
        $('#sidebarContent').css('opacity', '1');
        if (!res.ok) return;

        $('#pageInput').val(res.current_page);
        $('#totPage').text(res.total_pages);

        let html = '';
        if (res.mode === 'flat') {
            res.items.forEach(item => {
                const isSelected = selectedSketches.has(item.id);
                const isActive   = (item.id == previewSketchId);
                html += renderItemRow(item, isSelected, isActive);
            });
        } else {
            res.items.forEach(seq => {
                const anyActive = seq.items.some(i => i.id == previewSketchId);
                let itemsHtml = '';
                seq.items.forEach(item => {
                    const isSelected = selectedSketches.has(item.id);
                    const isActive   = (item.id == previewSketchId);
                    itemsHtml += renderItemRow(item, isSelected, isActive);
                });
                const openClass = anyActive ? 'open' : '';
                html += `<div class="seq-group ${openClass}">
                    <div class="seq-header">
                        <span style="font-weight:bold;color:#ccc;flex:1">${escapeHtml(seq.name)}</span>
                        <span style="color:#555">▼</span>
                    </div>
                    <div class="seq-body">${itemsHtml}</div>
                </div>`;
            });
        }
        $('#sidebarContent').html(html);
    // FIX: added explicit 'json' dataType and a .fail() handler so errors surface instead of failing silently
    }, 'json').fail(function(xhr, status, err) {
        $('#sidebarContent').css('opacity', '1');
        $('#sidebarContent').html('<div style="color:#f87171;padding:16px;">Sidebar load failed: ' + escapeHtml(String(err || status)) + '</div>');
    });
}

function renderItemRow(item, isSelected, isActive) {
    const selClass    = isSelected ? 'selected' : '';
    const activeClass = isActive   ? 'active'   : '';
    const checked     = isSelected ? 'checked'  : '';
    
    // FIX: pass 'this' (the DOM element) into toggleSketchSelection and read the
    // data-name attribute instead of injecting JSON.stringify into an inline handler 
    // which breaks the HTML string if the name contains spaces and quotes.
    return `<div class="item-row ${selClass} ${activeClass}" data-id="${item.id}">
        <input type="checkbox" class="item-cb" data-id="${item.id}" data-name="${escapeHtml(item.name)}"
               ${checked} onclick="event.stopPropagation(); toggleSketchSelection(${item.id}, this)">
        <div class="item-text" onclick="loadSketchPreview(${item.id})">
            <div class="iname">${escapeHtml(item.name)}</div>
            <div class="imood">${escapeHtml(item.mood || '—')}</div>
        </div>
    </div>`;
}

// ── SELECTION MANAGEMENT ─────────────────────────────────────────────────────
function toggleSketchSelection(id, cbEl) {
    const name = cbEl.getAttribute('data-name') || '';
    const row = cbEl.closest('.item-row');
    
    if (cbEl.checked) {
        selectedSketches.set(id, { id, name });
        row.classList.add('selected');
    } else {
        selectedSketches.delete(id);
        row.classList.remove('selected');
    }
    updateSelectionUI();
}

function selectAllVisible() {
    $('#sidebarContent .item-cb').each(function() {
        const id   = parseInt($(this).data('id'));
        const name = $(this).data('name');
        if (!selectedSketches.has(id)) {
            selectedSketches.set(id, { id, name });
            $(this).prop('checked', true);
            $(this).closest('.item-row').addClass('selected');
        }
    });
    updateSelectionUI();
}

function clearSelection() {
    selectedSketches.clear();
    $('#sidebarContent .item-cb').prop('checked', false);
    $('#sidebarContent .item-row').removeClass('selected');
    updateSelectionUI();
}

function removeFromSelection(id) {
    selectedSketches.delete(id);
    // uncheck the checkbox in sidebar if visible
    $(`#sidebarContent .item-cb[data-id="${id}"]`).prop('checked', false)
        .closest('.item-row').removeClass('selected');
    updateSelectionUI();
}

function updateSelectionUI() {
    const count = selectedSketches.size;

    // Update count label
    $('#selCount').text(count + ' selected');

    // Update nav badge
    const badge = $('#navBadge');
    if (count > 0) { badge.text(count).addClass('visible'); }
    else           { badge.removeClass('visible'); }

    // Rebuild pills
    const pills = $('#sketchPills');
    if (count === 0) {
        pills.html('<span class="pills-empty">No sketches selected — use the sidebar ☰</span>');
    } else {
        let html = '';
        selectedSketches.forEach((sk) => {
            const label = sk.name.length > 20 ? sk.name.substring(0, 20) + '…' : sk.name;
            html += `<span class="sketch-pill">
                <span title="${escapeHtml(sk.name)}">#${sk.id} ${escapeHtml(label)}</span>
                <span class="rm" onclick="removeFromSelection(${sk.id})">✕</span>
            </span>`;
        });
        pills.html(html);
    }

    // Enable / disable enqueue button
    updateEnqueueBtn();
}

function updateEnqueueBtn() {
    const hasSketches = selectedSketches.size > 0;
    const hasChars    = document.querySelectorAll('input[name="chars[]"]:checked').length > 0;
    const btn = $('#enqueueBtn');
    btn.prop('disabled', !(hasSketches && hasChars));
    if (hasSketches && hasChars) {
        btn.html(`<i class="bi bi-stack"></i> Enqueue ${selectedSketches.size} × ${document.querySelectorAll('input[name="chars[]"]:checked').length} Jobs`);
        btn.addClass('batch-active');
    } else {
        btn.html(`<i class="bi bi-stack"></i> Enqueue Jobs`);
        btn.removeClass('batch-active');
    }
}

// Watch char checkboxes
$(document).on('change', 'input[name="chars[]"]', updateEnqueueBtn);

// ── PREVIEW SKETCH ────────────────────────────────────────────────────────────
function loadSketchPreview(id) {
    previewSketchId = id;

    // Update active state in sidebar
    $('#sidebarContent .item-row').removeClass('active');
    $(`#sidebarContent .item-row[data-id="${id}"]`).addClass('active');

    $('#wkTitle').text('Loading…');
    $('#wkId').text('#' + id);

    // FIX: post to SELF_URL instead of ''
    $.post(SELF_URL, { action: 'get_sketch', id: id }, function(res) {
        if (res.ok) {
            const s = res.data;
            $('#wkTitle').text(s.name);
            $('#wkId').text('#' + s.id);
            $('#wkMood').text(s.mood || 'No Mood');
            $('#sceneDesc').val(s.description || '');

            const swWrapper = $('#swiperWrapper');
            swWrapper.empty();
            if (s.frames && s.frames.length > 0) {
                s.frames.forEach(f => {
                    swWrapper.append(`
                        <div class="swiper-slide">
                            <a href="${f.filename}" data-pswp-width="800" data-pswp-height="600" target="_blank" class="pswp-link">
                                <img src="${f.filename}" loading="lazy" onload="updatePswpDims(this)">
                            </a>
                            <div class="f-view-btn" onclick="event.stopPropagation(); event.preventDefault(); openFrameModal(${f.id})">
                                <i class="bi bi-arrows-fullscreen"></i>
                            </div>
                        </div>`);
                });
            } else {
                swWrapper.html('<div class="swiper-slide" style="color:#444">No frames</div>');
            }
            swiperInst.update();
            swiperInst.slideTo(0);

            const newUrl = window.location.pathname + '?sketch_id=' + id;
            window.history.pushState({ path: newUrl }, '', newUrl);
        } else {
            $('#wkTitle').text('Error loading sketch');
        }
    // FIX: explicit dataType + fail handler
    }, 'json').fail(function() {
        $('#wkTitle').text('Load failed');
    });
}

// ── ENQUEUE ──────────────────────────────────────────────────────────────────
function enqueueBatch() {
    const sketchIds = Array.from(selectedSketches.keys());
    const charIds   = Array.from(document.querySelectorAll('input[name="chars[]"]:checked')).map(cb => cb.value);
    const genId     = $('#genConfig').val();

    if (sketchIds.length === 0) return showToast('No sketches selected.', 'error');
    if (charIds.length === 0)   return showToast('No characters selected.', 'error');

    const btn = $('#enqueueBtn');
    btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Enqueueing…');

    // FIX: use SELF_URL instead of '' to ensure the POST goes to the right endpoint.
    // Note: 'traditional: true' was removed because it forces arrays to 'key=val' instead of 'key[]=val'.
    // PHP requires 'key[]=val' to recognize variables as arrays, otherwise only the last element is received!
    $.ajax({
        url:         SELF_URL,
        method:      'POST',
        dataType:    'json',
        data: {
            action:        'enqueue_batch',
            sketch_ids:    sketchIds,
            character_ids: charIds,
            generator_id:  genId,
        },
        success: function(res) {
            btn.prop('disabled', false);
            if (res.ok) {
                showToast(res.message + ` (${res.enqueued} jobs inserted, ${res.skipped} skipped)`, 'success');
                loadQueueStats();
                updateEnqueueBtn();
            } else {
                showToast('Error: ' + res.error, 'error');
                updateEnqueueBtn();
            }
        },
        error: function(xhr, status, err) {
            btn.prop('disabled', false);
            updateEnqueueBtn();
            // FIX: surface the actual HTTP error text instead of a generic message
            const detail = xhr.responseText ? xhr.responseText.substring(0, 200) : (err || status);
            showToast('Server error: ' + detail, 'error');
        }
    });
}

// ── QUEUE STATS ───────────────────────────────────────────────────────────────
function loadQueueStats() {
    // FIX: post to SELF_URL instead of ''
    $.post(SELF_URL, { action: 'get_queue_stats' }, function(res) {
        if (res.ok && res.stats) {
            const s = res.stats;
            $('#qTotal').text(s.total + ' total');
            $('#qPending').text(s.pending + ' pending');
            $('#qRunning').text(s.running + ' running');
            $('#qDone').text(s.done + ' done');
            $('#qFailed').text(s.failed + ' failed');
        }
    }, 'json');
}

// ── TOAST ─────────────────────────────────────────────────────────────────────
let toastTimer = null;
function showToast(msg, type = 'success') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = `toast ${type} show`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.classList.remove('show'); }, 4000);
}

// ── HELPERS ───────────────────────────────────────────────────────────────────
function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

// ── FRAME MODAL ───────────────────────────────────────────────────────────────
function openFrameModal(id) {
    document.getElementById('frameViewer').src = `view_frame.php?frame_id=${id}&view=modal`;
    document.getElementById('viewModal').classList.add('active');
}
function closeFrameModal() {
    document.getElementById('viewModal').classList.remove('active');
    setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeFrameModal(); });
</script>

<!-- PhotoSwipe Init -->
<script type="module">
import PhotoSwipeLightbox from 'https://unpkg.com/photoswipe@5.4.3/dist/photoswipe-lightbox.esm.js';
import PhotoSwipe from 'https://unpkg.com/photoswipe@5.4.3/dist/photoswipe.esm.js';
const lightbox = new PhotoSwipeLightbox({
    gallery: '#swiperWrapper', children: 'a', pswpModule: PhotoSwipe
});
lightbox.init();
</script>

<?php echo $eruda; ?>

</body>
</html>