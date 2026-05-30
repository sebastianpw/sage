<?php
// public/view_gs_assign_forge.php
// Greenscreen Assign Forge — maps character entities → video_tree_nodes and batch-assigns videos
require_once __DIR__ . '/bootstrap.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();
$pageTitle = "GS Assign Forge";

// ═══════════════════════════════════════════════════════
// INLINE API HANDLER
// ═══════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['api_action'];
    try {

        // ── List all character entities with their character_id counts ──
        if ($action === 'list_characters') {
            $rows =[];
            try {
                $stmt = $pdo->query("
                    SELECT DISTINCT c.id, c.name
                    FROM characters c
                    WHERE c.id IN (
                        SELECT DISTINCT character_id FROM character_poses
                        UNION
                        SELECT DISTINCT character_id FROM character_expressions
                        UNION
                        SELECT DISTINCT character_id FROM character_anima_poses
                    )
                    ORDER BY c.name ASC
                ");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                // Fallback if characters table schema differs
                $stmt = $pdo->query("
                    SELECT character_id as id, CONCAT('Character #', character_id) as name
                    FROM (
                        SELECT DISTINCT character_id FROM character_poses
                        UNION
                        SELECT DISTINCT character_id FROM character_expressions
                        UNION
                        SELECT DISTINCT character_id FROM character_anima_poses
                    ) t
                    ORDER BY character_id ASC
                ");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode(['status' => 'ok', 'characters' => $rows]);
            exit;
        }

        // ── List gs_assign_config rows ──
        if ($action === 'list_configs') {
            $stmt = $pdo->query("
                SELECT g.*, n.name as node_name
                FROM gs_assign_config g
                LEFT JOIN video_tree_nodes n ON n.id = g.node_id
                ORDER BY g.entity_type, g.source_id
            ");
            echo json_encode(['status' => 'ok', 'configs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // ── Save / upsert a config row ──
        if ($action === 'save_config') {
            $input      = json_decode(file_get_contents('php://input'), true);
            $id         = (int)($input['id'] ?? 0);
            $label      = trim($input['label'] ?? '');
            $entityType = $input['entity_type'] ?? '';
            $sourceId   = (int)($input['source_id'] ?? 0);
            $nodeId     = (int)($input['node_id'] ?? 0);
            $isActive   = (int)($input['is_active'] ?? 1);

            $validTypes =['character_poses', 'character_expressions', 'character_anima_poses', 'locations'];
            if (!in_array($entityType, $validTypes)) throw new Exception('Invalid entity_type');
            if (!$sourceId) throw new Exception('source_id required');
            if (!$nodeId)   throw new Exception('node_id required');

            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE gs_assign_config
                    SET label=?, entity_type=?, source_id=?, node_id=?, is_active=?
                    WHERE id=?
                ");
                $stmt->execute([$label, $entityType, $sourceId, $nodeId, $isActive, $id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO gs_assign_config (label, entity_type, source_id, node_id, is_active)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE label=VALUES(label), node_id=VALUES(node_id), is_active=VALUES(is_active)
                ");
                $stmt->execute([$label, $entityType, $sourceId, $nodeId, $isActive]);
                $id = (int)$pdo->lastInsertId();
            }
            echo json_encode(['status' => 'ok', 'id' => $id]);
            exit;
        }

        // ── Delete a config row ──
        if ($action === 'delete_config') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id    = (int)($input['id'] ?? 0);
            if (!$id) throw new Exception('id required');
            $pdo->prepare("DELETE FROM gs_assign_config WHERE id=?")->execute([$id]);
            echo json_encode(['status' => 'ok']);
            exit;
        }

        // ── Tree: fetch tree ──
        if ($action === 'tree_fetch') {
            $rows = $pdo->query("SELECT id, parent_id, name, node_type FROM video_tree_nodes ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
            $nodes =[];
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
                    'data'   =>['db_id' => (int)$r['id'], 'node_type' => $r['node_type']],
                ];
            }
            echo json_encode(['status' => 'ok', 'tree' => $nodes]);
            exit;
        }

        // ── Tree: create node ──
        if ($action === 'tree_create_node') {
            $input    = json_decode(file_get_contents('php://input'), true);
            $name     = trim($input['name'] ?? '');
            $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;
            $nodeType = in_array($input['node_type'] ?? '',['folder','episode','sequence','scene','other']) ? $input['node_type'] : 'folder';
            if (!$name) throw new Exception('Name required');
            $stmt = $pdo->prepare("INSERT INTO video_tree_nodes (parent_id, name, node_type) VALUES (?, ?, ?)");
            $stmt->execute([$parentId, $name, $nodeType]);
            echo json_encode(['status' => 'ok', 'id' => (int)$pdo->lastInsertId(), 'name' => $name]);
            exit;
        }

        // ── Fetch Thumbnails for Preview Pagination ──
        if ($action === 'get_thumbnails') {
            $input = json_decode(file_get_contents('php://input'), true);
            $vids  = array_filter(array_map('intval', $input['video_ids'] ??[]));
            if (empty($vids)) {
                echo json_encode(['status' => 'ok', 'videos' => []]); 
                exit;
            }
            $ph = implode(',', array_fill(0, count($vids), '?'));
            $stmt = $pdo->prepare("SELECT id, thumbnail, width, height, url FROM videos WHERE id IN ($ph) ORDER BY id DESC");
            $stmt->execute($vids);
            echo json_encode(['status' => 'ok', 'videos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // ── DRY RUN & EXECUTE ──
        if ($action === 'run_assign' || $action === 'dry_run') {
            $input     = json_decode(file_get_contents('php://input'), true);
            $configIds = array_map('intval', $input['config_ids'] ??[]);
            $dryRun    = ($action === 'dry_run');

            if (empty($configIds)) throw new Exception('No config IDs selected');

            $inList = implode(',', $configIds);
            $configs = $pdo->query("SELECT * FROM gs_assign_config WHERE id IN ($inList) AND is_active=1")
                           ->fetchAll(PDO::FETCH_ASSOC);

            if (empty($configs)) throw new Exception('No active configs found for given IDs');

            $log    =[];
            $total  = 0;
            $errors = 0;

            foreach ($configs as $cfg) {
                $entityType = $cfg['entity_type'];
                $charId     = (int)$cfg['source_id'];
                $nodeId     = (int)$cfg['node_id'];
                $label      = $cfg['label'] ?: $entityType . ':' . $charId;

                try {
                    if (!in_array($entityType,['character_poses','character_expressions','character_anima_poses','locations'])) {
                        $log[] =['config' => $label, 'status' => 'skip', 'reason' => 'unsupported entity_type'];
                        continue;
                    }

                    // ── Find all frames generated from this entity ──
                    if ($entityType === 'locations') {
                        $frameStmt = $pdo->prepare("SELECT DISTINCT m.from_id FROM `frames_2_locations` m WHERE m.to_id = ?");
                        $frameStmt->execute([$charId]);
                    } else {
                        $mappingTable = "frames_2_{$entityType}";
                        $frameStmt = $pdo->prepare("
                            SELECT DISTINCT m.from_id 
                            FROM `$mappingTable` m
                            INNER JOIN `$entityType` e ON e.id = m.to_id
                            WHERE e.character_id = ?
                        ");
                        $frameStmt->execute([$charId]);
                    }
                    
                    $frameIds = $frameStmt->fetchAll(PDO::FETCH_COLUMN);

                    if (empty($frameIds)) {
                        $log[] =['config' => $label, 'status' => 'skip', 'reason' => "no frames generated from $entityType for this entity", 'count' => 0];
                        continue;
                    }

                    $framePh = implode(',', array_fill(0, count($frameIds), '?'));

                    // Get animatic IDs
                    $animStmt = $pdo->prepare("
                        SELECT DISTINCT id 
                        FROM animatics 
                        WHERE img2img_frame_id IN ($framePh)
                           OR cnmap_frame_id IN ($framePh)
                    ");
                    $animStmt->execute(array_merge($frameIds, $frameIds));
                    $animaticIds = $animStmt->fetchAll(PDO::FETCH_COLUMN);

                    if (empty($animaticIds)) {
                        $log[] =['config' => $label, 'status' => 'skip', 'reason' => 'no animatics linked to those frames', 'count' => 0];
                        continue;
                    }

                    $animPh = implode(',', array_fill(0, count($animaticIds), '?'));

                    // Get video IDs
                    $vidStmt = $pdo->prepare("SELECT DISTINCT from_id FROM videos_2_animatics WHERE to_id IN ($animPh)");
                    $vidStmt->execute($animaticIds);
                    $videoIds = array_map('intval', $vidStmt->fetchAll(PDO::FETCH_COLUMN));

                    if (empty($videoIds)) {
                        $log[] =['config' => $label, 'status' => 'skip', 'reason' => 'no videos linked to those animatics', 'count' => 0];
                        continue;
                    }

                    if ($dryRun) {
                        $log[] =[
                            'config'       => $label,
                            'status'       => 'preview',
                            'count'        => count($videoIds),
                            'frame_count'  => count($frameIds),
                            'anim_count'   => count($animaticIds),
                            'video_ids'    => $videoIds,
                            'node_id'      => $nodeId,
                        ];
                        $total += count($videoIds);
                    } else {
                        // Batch upsert into video_tree_items
                        $inserted = 0;
                        $pdo->beginTransaction();
                        foreach ($videoIds as $vid) {
                            $pdo->prepare("INSERT IGNORE INTO video_tree_items (node_id, video_id) VALUES (?, ?)")
                                ->execute([$nodeId, $vid]);
                            $inserted++;
                        }
                        $pdo->commit();

                        $log[] =[
                            'config'      => $label,
                            'status'      => 'ok',
                            'count'       => $inserted,
                            'frame_count' => count($frameIds),
                            'anim_count'  => count($animaticIds),
                            'video_ids'   => $videoIds,
                            'node_id'     => $nodeId,
                        ];
                        $total += $inserted;
                    }

                } catch (\Exception $inner) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $log[] =['config' => $label, 'status' => 'error', 'reason' => $inner->getMessage()];
                    $errors++;
                }
            }

            echo json_encode([
                'status'   => $errors ? 'partial' : 'ok',
                'dry_run'  => $dryRun,
                'total'    => $total,
                'errors'   => $errors,
                'log'      => $log,
            ]);
            exit;
        }

    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

ob_start();
?>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<!-- jsTree -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<script src="/js/toast.js"></script>

<style>
/* ════════════════════════════════════════════════════════
   GS ASSIGN FORGE
   Aesthetic: Dark terminal forge — amber accents on deep
   black, industrial mono type, grid-heavy layout
════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:          #070709;
    --surface:     #0d0d12;
    --card:        #111118;
    --border:      #1e1e2a;
    --border2:     #2a2a3a;
    --text:        #d0d0e0;
    --muted:       #484860;
    --amber:       #ffb020;
    --amber-dim:   rgba(255,176,32,0.10);
    --amber-glow:  rgba(255,176,32,0.22);
    --green:       #00e5a0;
    --green-dim:   rgba(0,229,160,0.10);
    --danger:      #ff5f6d;
    --danger-dim:  rgba(255,95,109,0.10);
    --accent:      #6c63ff;
    --accent-dim:  rgba(108,99,255,0.12);
    --cyan:        #00cfcf;
    --scan-color:  rgba(255,176,32,0.04);
}

html, body {
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Mono', 'Fira Mono', 'Courier New', monospace;
    min-height: 100dvh;
}

body::before {
    content: '';
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
    background: repeating-linear-gradient(to bottom, transparent 0px, transparent 3px, var(--scan-color) 3px, var(--scan-color) 4px);
}

/* ── Header ── */
.gsf-header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 0 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 50px;
    flex-wrap: wrap;
}
.gsf-header-title { font-size: 0.7rem; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; color: var(--amber); white-space: nowrap; }
.gsf-header-title span { color: var(--muted); }
.gsf-header-meta { font-size: 0.6rem; color: var(--muted); letter-spacing: 1px; flex: 1; min-width: 0; }

/* ── Page body ── */
.gsf-body {
    position: relative;
    z-index: 1;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 900px;
    margin: 0 auto;
    padding-bottom: calc(100px + env(safe-area-inset-bottom));
}

/* ── Section card ── */
.gsf-section { background: var(--card); border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
.gsf-section-head { padding: 9px 14px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 10px; background: rgba(255,176,32,0.03); }
.gsf-section-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 2.5px; text-transform: uppercase; color: var(--amber); }
.gsf-section-body { padding: 14px; }

/* ── Form controls ── */
.gsf-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
.gsf-field { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 120px; }
.gsf-field label { font-size: 0.58rem; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); font-weight: 700; }
.gsf-input, .gsf-select { background: var(--bg); border: 1px solid var(--border2); color: var(--text); font-family: inherit; font-size: 0.8rem; padding: 8px 10px; border-radius: 4px; width: 100%; transition: border-color 0.15s; -webkit-appearance: none; }
.gsf-input:focus, .gsf-select:focus { outline: none; border-color: var(--amber); }

/* ── Buttons ── */
.gsf-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 14px;
    border-radius: 4px;
    border: 1px solid transparent;
    font-family: inherit;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    transition: background 0.1s, border-color 0.1s, color 0.1s, opacity 0.1s;
    white-space: nowrap;
    min-height: 38px;
}
.gsf-btn:active { transform: scale(0.96); }
.gsf-btn:disabled { opacity: 0.35; pointer-events: none; }
.gsf-btn-amber { background: var(--amber-dim); border-color: var(--amber); color: var(--amber); }
.gsf-btn-amber:active { background: var(--amber-glow); }
.gsf-btn-green { background: var(--green); border-color: var(--green); color: #000; }
.gsf-btn-green:active { opacity: 0.8; }
.gsf-btn-danger { background: var(--danger-dim); border-color: var(--danger); color: var(--danger); }
.gsf-btn-ghost { background: transparent; border-color: var(--border2); color: var(--muted); }
.gsf-btn-ghost:active { color: var(--text); border-color: var(--text); }

/* ── Config table ── */
.gsf-table { width: 100%; border-collapse: collapse; font-size: 0.72rem; }
.gsf-table th { padding: 7px 10px; text-align: left; font-size: 0.55rem; letter-spacing: 2px; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); font-weight: 700; white-space: nowrap; }
.gsf-table td { padding: 8px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; color: var(--text); }
.gsf-table tr:hover td { background: rgba(255,176,32,0.02); }

.gsf-pill { display: inline-block; padding: 2px 7px; border-radius: 3px; font-size: 0.58rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
.gsf-pill-pose  { background: rgba(108,99,255,0.15); color: var(--accent); }
.gsf-pill-expr  { background: rgba(0,207,207,0.12);  color: var(--cyan); }
.gsf-pill-anima { background: rgba(0,229,160,0.12);  color: var(--green); }
.gsf-pill-inactive { background: rgba(255,95,109,0.12); color: var(--danger); }
.gsf-pill-active   { background: var(--green-dim); color: var(--green); }
.config-row-cb { accent-color: var(--amber); width: 15px; height: 15px; cursor: pointer; }

/* ── Run panel ── */
.gsf-run-panel { background: var(--card); border: 1px solid var(--border); border-radius: 6px; position: sticky; bottom: env(safe-area-inset-bottom); z-index: 90; }
.gsf-run-inner { padding: 10px 14px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.gsf-run-sel { font-size: 0.65rem; color: var(--muted); flex: 1; }
.gsf-run-sel span { color: var(--amber); font-weight: 700; }

/* ── Log / status monitor ── */
.gsf-log {
    background: var(--bg); border: 1px solid var(--border); border-radius: 4px; padding: 10px 12px;
    font-size: 0.68rem; line-height: 1.7; max-height: 60vh; overflow-y: auto; font-family: 'DM Mono', 'Fira Mono', monospace;
}
.gsf-log::-webkit-scrollbar { width: 3px; }
.gsf-log::-webkit-scrollbar-thumb { background: var(--border2); }
.gsf-log-line { display: flex; gap: 8px; align-items: baseline; }
.gsf-log-time { color: var(--muted); flex-shrink: 0; font-size: 0.58rem; }
.gsf-log-ok      { color: var(--green); }
.gsf-log-skip    { color: var(--muted); }
.gsf-log-preview { color: var(--amber); }
.gsf-log-error   { color: var(--danger); }
.gsf-log-summary { color: var(--text); font-weight: 700; border-top: 1px solid var(--border); margin-top: 6px; padding-top: 6px; }

/* ── AJAX Thumbnail Preview Grid ── */
.gsf-preview-wrap { margin: 6px 0 12px 42px; background: rgba(0,0,0,0.3); border: 1px solid var(--border2); border-radius: 4px; overflow: hidden; }
.gsf-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); gap: 4px; padding: 6px; }
.gsf-preview-thumb { width: 100%; aspect-ratio: 1/1; object-fit: cover; border-radius: 3px; border: 1px solid var(--border); transition: opacity 0.2s, transform 0.2s, border-color 0.2s; cursor: pointer; }
.gsf-preview-thumb:hover { transform: scale(1.1); border-color: var(--amber); z-index: 10; position: relative; }
.gsf-pagination { display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; border-top: 1px solid var(--border); background: var(--surface); font-size: 0.65rem; color: var(--muted); }
.gsf-page-btn { background: transparent; border: 1px solid var(--border2); color: var(--text); padding: 3px 8px; border-radius: 3px; cursor: pointer; font-family: inherit; }
.gsf-page-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.gsf-page-btn:hover:not(:disabled) { border-color: var(--amber); color: var(--amber); }

/* ── jsTree Overrides ── */
.jstree-default .jstree-anchor { color: var(--text) !important; line-height: 28px; height: 28px; }
.jstree-default .jstree-hovered { background: rgba(108,99,255,0.12) !important; border-radius: 4px; }
.jstree-default .jstree-clicked { background: rgba(108,99,255,0.25) !important; color: var(--accent) !important; border-radius: 4px; }
.jstree-default .jstree-icon { color: var(--muted); }
.jstree-default { background: transparent !important; color: var(--text); }
.jstree-container-ul { background: transparent !important; }

.gsf-spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid var(--border2); border-top-color: var(--amber); border-radius: 50%; animation: gsf-spin 0.7s linear infinite; vertical-align: middle; margin-right: 6px; }
@keyframes gsf-spin { to { transform: rotate(360deg); } }
.gsf-toggle { position: relative; display: inline-block; width: 34px; height: 18px; flex-shrink: 0; }
.gsf-toggle input { opacity: 0; width: 0; height: 0; }
.gsf-toggle-slider { position: absolute; inset: 0; background: var(--border2); border-radius: 18px; cursor: pointer; transition: background 0.2s; }
.gsf-toggle-slider::before { content: ''; position: absolute; height: 12px; width: 12px; left: 3px; top: 3px; background: var(--muted); border-radius: 50%; transition: transform 0.2s, background 0.2s; }
.gsf-toggle input:checked + .gsf-toggle-slider { background: var(--amber-dim); border: 1px solid var(--amber); }
.gsf-toggle input:checked + .gsf-toggle-slider::before { transform: translateX(16px); background: var(--amber); }

/* ── Mobile ── */
@media (max-width: 500px) {
    .gsf-table th:nth-child(3),
    .gsf-table td:nth-child(3) { display: none; }
    .gsf-table th:nth-child(5),
    .gsf-table td:nth-child(5) { display: none; }
}
</style>

<div class="gsf-header">
    <div class="gsf-header-title">◈ GS <span>ASSIGN</span> FORGE</div>
    <div class="gsf-header-meta" id="headerMeta">Loading config…</div>
    <button class="gsf-btn gsf-btn-amber" onclick="openAddModal()">+ Config</button>
</div>

<div class="gsf-body">

    <!-- ── Config Table ── -->
    <div class="gsf-section">
        <div class="gsf-section-head">
            <span class="gsf-section-label">⚙ Assign Configurations</span>
            <div style="display:flex;gap:6px;align-items:center;">
                <button class="gsf-btn gsf-btn-ghost" id="btnSelectAll" onclick="toggleSelectAll()" style="font-size:0.6rem;padding:5px 10px;min-height:30px;">Select All</button>
            </div>
        </div>
        <div id="configTableWrap">
            <div style="padding: 30px;text-align: center;color: var(--muted);font-size: 0.7rem;"><span class="gsf-spinner"></span> Loading…</div>
        </div>
    </div>

    <!-- ── Run Panel (sticky bottom) ── -->
    <div class="gsf-run-panel" id="runPanel">
        <div class="gsf-run-inner">
            <div class="gsf-run-sel">Selected: <span id="selectedCount">0</span> config(s)</div>
            <button class="gsf-btn gsf-btn-ghost" id="btnDryRun" onclick="runAssign(true)" disabled>🔍 Dry Run</button>
            <button class="gsf-btn gsf-btn-amber" id="btnAssign" onclick="runAssign(false)" disabled>▶ Assign</button>
        </div>
    </div>

    <!-- ── Log Monitor ── -->
    <div class="gsf-section" id="logSection" style="display:none;">
        <div class="gsf-section-head">
            <span class="gsf-section-label" id="logTitle">◈ Run Log</span>
            <button class="gsf-btn gsf-btn-ghost" onclick="clearLog()" style="font-size:0.6rem;padding:5px 10px;min-height:30px;">Clear</button>
        </div>
        <div class="gsf-section-body" style="padding:10px;">
            <div class="gsf-log" id="logEl"></div>
        </div>
    </div>

</div>

<!-- ════════════════════════════════════════════
     ADD / EDIT CONFIG MODAL
════════════════════════════════════════════ -->
<div id="configModal" style="position:fixed;inset:0;z-index:500;background:rgba(0,0,0,0.85);display:none;align-items:flex-end;justify-content:center;">
    <div style="width:100%;max-width:520px;background:var(--card);border:1px solid var(--border2);border-radius:12px 12px 0 0;display:flex;flex-direction:column;max-height:92dvh;overflow:hidden;">
        <div style="padding:12px 14px 10px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <span style="font-size:0.7rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--amber);" id="modalTitle">+ New Config</span>
            <button onclick="closeModal()" style="background:transparent;border:1px solid var(--border2);color:var(--muted);width:30px;height:30px;border-radius:4px;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;">✕</button>
        </div>
        <div style="padding:16px;overflow-y:auto;flex:1;">
            <input type="hidden" id="cfgId">

            <div class="gsf-row">
                <div class="gsf-field" style="flex:2;">
                    <label>Label</label>
                    <input type="text" class="gsf-input" id="cfgLabel" placeholder="e.g. Eve – Poses">
                </div>
            </div>

            <div class="gsf-row">
                <div class="gsf-field">
                    <label>Entity Type</label>
                    <select class="gsf-select" id="cfgEntityType">
                        <option value="character_poses">character_poses</option>
                        <option value="character_expressions">character_expressions</option>
                        <option value="character_anima_poses">character_anima_poses</option>
                        <!-- <option value="locations">locations</option> -->
                    </select>
                </div>
                <div class="gsf-field">
                    <label>Character / Entity</label>
                    <select class="gsf-select" id="cfgCharacter">
                        <option value="">Loading…</option>
                    </select>
                </div>
            </div>

            <!-- jsTree Target Node Selector -->
            <div class="gsf-row">
                <div class="gsf-field" style="flex:2;">
                    <label>Target Node: <span id="cfgTargetText" style="color:var(--amber);text-transform:none;">None selected</span></label>
                    <div style="border:1px solid var(--border2);border-radius:4px;background:var(--bg);display:flex;flex-direction:column;height:240px;margin-top:4px;">
                        <div style="padding:6px;border-bottom:1px solid var(--border2);display:flex;gap:4px;">
                            <input type="text" class="gsf-input" id="newNodeName" placeholder="New node…" style="padding:4px 6px;height:28px;flex:1;">
                            <select class="gsf-select" id="newNodeType" style="padding:4px 6px;height:28px;width:auto;">
                                <option value="folder">Folder</option>
                                <option value="episode">Episode</option>
                                <option value="sequence">Sequence</option>
                                <option value="scene">Scene</option>
                                <option value="other">Other</option>
                            </select>
                            <button type="button" class="gsf-btn gsf-btn-ghost" onclick="createTreeNode()" style="min-height:28px;padding:0 8px;">+ Add</button>
                        </div>
                        <div id="cfgNodeTree" style="flex:1;overflow-y:auto;padding:6px;">
                            <div class="gsf-spinner"></div> Loading tree…
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;margin-top:10px;">
                <label class="gsf-toggle"><input type="checkbox" id="cfgActive" checked><span class="gsf-toggle-slider"></span></label>
                <span style="font-size:0.7rem;color:var(--muted);">Active</span>
            </div>
        </div>
        
        <!-- Modal Footer Actions -->
        <div style="padding:10px 14px;border-top:1px solid var(--border);display:flex;gap:8px;flex-shrink:0;">
            <button class="gsf-btn gsf-btn-danger" id="btnDeleteConfigModal" onclick="deleteConfigFromModal()" style="display:none; flex:0.3;">Delete</button>
            <button class="gsf-btn gsf-btn-ghost" onclick="closeModal()" style="flex:0.5;">Cancel</button>
            <button class="gsf-btn gsf-btn-amber" onclick="saveConfig()" style="flex:1;" id="btnSaveConfig">Save Config</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>

<script>
(function () {
    'use strict';

    // ── State ──
    let configs       =[];
    let characters    =[];
    let selectedIds   = new Set();
    let editingId     = null;
    let allSelected   = false;
    let treeInited    = false;
    let selectedNodeId = null;
    
    // Pagination state tracker
    window.gridData = {};

    // ── Boot ──
    Promise.all([ loadCharacters(), loadConfigs() ]);

    function loadCharacters() {
        return fetch('?api_action=list_characters')
            .then(r => r.json())
            .then(d => {
                if (d.status === 'ok') characters = d.characters;
                const el = document.getElementById('cfgCharacter');
                el.innerHTML = '<option value="">Select character…</option>' +
                    characters.map(c => `<option value="${c.id}">${escH(c.name)} (ID: ${c.id})</option>`).join('');
            });
    }

    function loadConfigs() {
        return fetch('?api_action=list_configs')
            .then(r => r.json())
            .then(d => {
                configs = d.status === 'ok' ? d.configs :[];
                renderConfigTable();
                const active = configs.filter(c => c.is_active == 1).length;
                document.getElementById('headerMeta').textContent = configs.length + ' config(s) · ' + active + ' active';
            });
    }

    // ═══════════════════════════════════════
    // TREE INITIALIZATION
    // ═══════════════════════════════════════

    function initAssignTree() {
        treeInited = true;
        $('#cfgNodeTree').jstree({
            core: {
                data: {
                    url: '?api_action=tree_fetch',
                    dataType: 'json',
                    dataFilter: function (raw) {
                        try {
                            const j = JSON.parse(raw);
                            return JSON.stringify(j.status === 'ok' ? j.tree :[]);
                        } catch (e) { return '[]'; }
                    }
                },
                themes: { name: 'default', dots: true, icons: true },
                check_callback: false,
            },
            plugins:['types', 'state'],
            types: {
                folder:   { icon: 'bi bi-folder2' },
                episode:  { icon: 'bi bi-film' },
                sequence: { icon: 'bi bi-collection-play' },
                scene:    { icon: 'bi bi-camera-video' },
                other:    { icon: 'bi bi-tag' },
            },
        })
        .on('select_node.jstree', function (e, data) {
            selectedNodeId = data.node.data.db_id;
            document.getElementById('cfgTargetText').innerHTML = escH(data.node.text);
        })
        .on('deselect_node.jstree', function () {
            selectedNodeId = null;
            document.getElementById('cfgTargetText').innerHTML = 'None selected';
        })
        .on('ready.jstree', function() {
            if (selectedNodeId) {
                $('#cfgNodeTree').jstree('select_node', 'n_' + selectedNodeId);
            }
        });
    }

    window.createTreeNode = function () {
        const name     = document.getElementById('newNodeName').value.trim();
        const nodeType = document.getElementById('newNodeType').value;
        if (!name) return;

        const sel      = $('#cfgNodeTree').jstree('get_selected', true);
        const parentId = sel.length ? sel[0].data.db_id : null;

        fetch('?api_action=tree_create_node', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, node_type: nodeType, parent_id: parentId }),
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') {
                document.getElementById('newNodeName').value = '';
                $('#cfgNodeTree').jstree('refresh');
                Toast.show('Node created', 'success');
            } else {
                Toast.show(d.message || 'Error', 'error');
            }
        });
    };

    // ═══════════════════════════════════════
    // CONFIG TABLE RENDER
    // ═══════════════════════════════════════

    function renderConfigTable() {
        const wrap = document.getElementById('configTableWrap');
        if (!configs.length) {
            wrap.innerHTML = '<div style="padding:30px;text-align:center;color:var(--muted);font-size:0.7rem;">No configurations yet. Tap + Config to add one.</div>';
            updateRunPanel();
            return;
        }

        const pillClass = { character_poses:'gsf-pill-pose', character_expressions:'gsf-pill-expr', character_anima_poses:'gsf-pill-anima' };
        const pillLabel = { character_poses:'poses', character_expressions:'expr', character_anima_poses:'anima' };

        wrap.innerHTML = `
            <table class="gsf-table">
                <thead>
                    <tr>
                        <th style="width:32px;"></th>
                        <th>Label</th>
                        <th>Entity</th>
                        <th>Char</th>
                        <th>Target Node</th>
                        <th>Status</th>
                        <th style="width:100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${configs.map(cfg => {
                        const checked   = selectedIds.has(cfg.id) ? 'checked' : '';
                        const pill      = pillClass[cfg.entity_type] || '';
                        const shortType = pillLabel[cfg.entity_type] || cfg.entity_type;
                        const active    = cfg.is_active == 1;
                        const charName  = characters.find(c => c.id == cfg.source_id)?.name || ('#' + cfg.source_id);
                        return `
                        <tr data-id="${cfg.id}" class="${!active ? 'gsf-row-inactive' : ''}">
                            <td><input type="checkbox" class="config-row-cb" data-id="${cfg.id}" ${checked} ${!active ? 'disabled' : ''} onchange="onRowCheck(${cfg.id}, this.checked)"></td>
                            <td style="font-weight:600;color:${active ? 'var(--text)' : 'var(--muted)'}">${escH(cfg.label || '—')}</td>
                            <td><span class="gsf-pill ${pill}">${shortType}</span></td>
                            <td style="color:var(--amber);font-size:0.7rem;">${escH(charName)}</td>
                            <td style="color:var(--muted);font-size:0.68rem;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escH(cfg.node_name || '')}">${escH(cfg.node_name || '—')}</td>
                            <td><span class="gsf-pill ${active ? 'gsf-pill-active' : 'gsf-pill-inactive'}">${active ? 'on' : 'off'}</span></td>
                            <td>
                                <div style="display:flex;gap:4px;">
                                    <button class="gsf-btn gsf-btn-ghost" onclick="editConfig(${cfg.id})" style="font-size:0.6rem;padding:4px 8px;min-height:26px;">Edit</button>
                                    <button class="gsf-btn gsf-btn-danger" onclick="deleteConfig(${cfg.id})" style="font-size:0.6rem;padding:4px 8px;min-height:26px;">Delete</button>
                                </div>
                            </td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>`;
        updateRunPanel();
    }

    window.onRowCheck = function (id, checked) {
        if (checked) selectedIds.add(id); else selectedIds.delete(id);
        updateRunPanel();
    };

    window.toggleSelectAll = function () {
        allSelected = !allSelected;
        selectedIds.clear();
        if (allSelected) configs.filter(c => c.is_active == 1).forEach(c => selectedIds.add(c.id));
        document.querySelectorAll('.config-row-cb').forEach(cb => { cb.checked = selectedIds.has(parseInt(cb.dataset.id)); });
        document.getElementById('btnSelectAll').textContent = allSelected ? 'Deselect All' : 'Select All';
        updateRunPanel();
    };

    function updateRunPanel() {
        const count = selectedIds.size;
        document.getElementById('selectedCount').textContent = count;
        document.getElementById('btnDryRun').disabled = count === 0;
        document.getElementById('btnAssign').disabled = count === 0;
    }

    // ═══════════════════════════════════════
    // MODAL: Add / Edit
    // ═══════════════════════════════════════

    window.openAddModal = function () {
        editingId = null;
        selectedNodeId = null;
        document.getElementById('modalTitle').textContent = '+ New Config';
        document.getElementById('cfgId').value = '';
        document.getElementById('cfgLabel').value = '';
        document.getElementById('cfgEntityType').value = 'character_poses';
        document.getElementById('cfgCharacter').value = '';
        document.getElementById('cfgActive').checked = true;
        document.getElementById('cfgTargetText').innerHTML = 'None selected';
        
        document.getElementById('btnDeleteConfigModal').style.display = 'none';
        document.getElementById('configModal').style.display = 'flex';
        
        if (!treeInited) initAssignTree(); else { $('#cfgNodeTree').jstree('deselect_all'); $('#cfgNodeTree').jstree('refresh'); }
    };

    window.editConfig = function (id) {
        const cfg = configs.find(c => c.id == id);
        if (!cfg) return;
        editingId = id;
        selectedNodeId = cfg.node_id;
        document.getElementById('modalTitle').textContent = '✏ Edit Config';
        document.getElementById('cfgId').value = cfg.id;
        document.getElementById('cfgLabel').value = cfg.label || '';
        document.getElementById('cfgEntityType').value = cfg.entity_type;
        document.getElementById('cfgCharacter').value = cfg.source_id;
        document.getElementById('cfgActive').checked = cfg.is_active == 1;
        document.getElementById('cfgTargetText').innerHTML = escH(cfg.node_name || 'None selected');
        
        document.getElementById('btnDeleteConfigModal').style.display = 'flex';
        document.getElementById('configModal').style.display = 'flex';
        
        if (!treeInited) initAssignTree(); 
        else { 
            $('#cfgNodeTree').jstree('deselect_all');
            $('#cfgNodeTree').jstree('select_node', 'n_' + selectedNodeId);
        }
    };

    window.closeModal = function () { document.getElementById('configModal').style.display = 'none'; };
    document.getElementById('configModal').addEventListener('click', function(e) { if(e.target === this) closeModal(); });

    window.saveConfig = function () {
        const btn = document.getElementById('btnSaveConfig');
        const label = document.getElementById('cfgLabel').value.trim();
        const entityType = document.getElementById('cfgEntityType').value;
        const sourceId = parseInt(document.getElementById('cfgCharacter').value);
        const nodeId = selectedNodeId;
        const isActive = document.getElementById('cfgActive').checked ? 1 : 0;
        const id = parseInt(document.getElementById('cfgId').value) || 0;

        if (!sourceId) { Toast.show('Select a character', 'error'); return; }
        if (!nodeId)   { Toast.show('Select a target tree node', 'error'); return; }

        btn.disabled = true; btn.textContent = 'Saving…';

        fetch('?api_action=save_config', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, label, entity_type: entityType, source_id: sourceId, node_id: nodeId, is_active: isActive }),
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') { closeModal(); loadConfigs(); Toast.show(id ? 'Config updated' : 'Config added', 'success'); }
            else Toast.show(d.message || 'Error', 'error');
        })
        .finally(() => { btn.disabled = false; btn.textContent = 'Save Config'; });
    };

    window.deleteConfig = function (id) {
        if (!confirm('Delete this config?')) return;
        fetch('?api_action=delete_config', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }),
        })
        .then(r => r.json())
        .then(d => { if (d.status === 'ok') { selectedIds.delete(id); loadConfigs(); Toast.show('Deleted', 'success'); } });
    };

    window.deleteConfigFromModal = function() {
        if (editingId) {
            deleteConfig(editingId);
            closeModal();
        }
    };

    // ═══════════════════════════════════════
    // RUN ASSIGN / AJAX PREVIEWS
    // ═══════════════════════════════════════

    let gridCounter = 0;

    window.runAssign = function (isDry) {
        const ids = Array.from(selectedIds);
        if (!ids.length) return;
        if (!isDry && !confirm(`Assign videos for ${ids.length} config(s) to tree nodes?`)) return;

        const logSection = document.getElementById('logSection');
        const logEl      = document.getElementById('logEl');
        const btnDry     = document.getElementById('btnDryRun');
        const btnRun     = document.getElementById('btnAssign');

        logSection.style.display = 'block';
        logEl.innerHTML = '';
        document.getElementById('logTitle').textContent = isDry ? '🔍 Dry Run Log' : '▶ Assign Log';
        btnDry.disabled = true; btnRun.disabled = true;

        appendLog('info', (isDry ? 'DRY RUN' : 'EXECUTE') + ' — ' + ids.length + ' config(s) selected');

        fetch('?api_action=' + (isDry ? 'dry_run' : 'run_assign'), {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ config_ids: ids }),
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'error') { appendLog('error', 'FATAL: ' + (d.message || 'Unknown error')); return; }

            (d.log ||[]).forEach(entry => {
                const status = entry.status;
                let msg = '';
                let htmlPayload = '';

                if (status === 'ok') msg = `[OK] ${entry.config} → assigned ${entry.count} video(s) to node #${entry.node_id} (${entry.frame_count} frames)`;
                else if (status === 'preview') msg = `[PREVIEW] ${entry.config} → would assign ${entry.count} video(s) to node #${entry.node_id} (${entry.frame_count} frames)`;
                else if (status === 'skip') msg = `[SKIP] ${entry.config} — ${entry.reason}`;
                else if (status === 'error') msg = `[ERR] ${entry.config} — ${entry.reason}`;

                if ((status === 'preview' || status === 'ok') && entry.video_ids && entry.video_ids.length > 0) {
                    const gridId = 'grid-' + (++gridCounter);
                    window.gridData[gridId] = { ids: entry.video_ids, page: 1, perPage: 24, totalPages: Math.ceil(entry.video_ids.length / 24) };
                    
                    htmlPayload = `
                        <div class="gsf-preview-wrap" id="wrap-${gridId}">
                            <div class="gsf-preview-grid" id="${gridId}"><div class="gsf-spinner"></div> Fetching previews...</div>
                            <div class="gsf-pagination">
                                <button class="gsf-page-btn" onclick="changeGridPage('${gridId}', -1)" id="prev-${gridId}">Prev</button>
                                <span id="info-${gridId}">Page 1</span>
                                <button class="gsf-page-btn" onclick="changeGridPage('${gridId}', 1)" id="next-${gridId}">Next</button>
                            </div>
                        </div>`;
                }

                appendLog(status === 'ok' ? 'ok' : status === 'preview' ? 'preview' : status === 'skip' ? 'skip' : 'error', msg, htmlPayload);
                
                // Immediately fetch the first page for the newly appended grid
                if (htmlPayload) fetchGridPage('grid-' + gridCounter);
            });

            appendLog('summary', `${isDry ? 'DRY RUN COMPLETE' : 'DONE'} — total: ${d.total} video(s)${d.errors > 0 ? ` · ${d.errors} error(s)` : ''}`);
            if (!isDry) Toast.show(`Assigned ${d.total} video(s) ✓`, 'success');
        })
        .finally(() => {
            btnDry.disabled = ids.size === 0; btnRun.disabled = ids.size === 0;
        });
    };

    window.changeGridPage = function(gridId, dir) {
        const data = window.gridData[gridId];
        data.page += dir;
        if (data.page < 1) data.page = 1;
        if (data.page > data.totalPages) data.page = data.totalPages;
        fetchGridPage(gridId);
    };

    window.fetchGridPage = function(gridId) {
        const data = window.gridData[gridId];
        const start = (data.page - 1) * data.perPage;
        const slice = data.ids.slice(start, start + data.perPage);

        document.getElementById(`info-${gridId}`).textContent = `Page ${data.page} of ${data.totalPages} (${data.ids.length} total)`;
        document.getElementById(`prev-${gridId}`).disabled = data.page <= 1;
        document.getElementById(`next-${gridId}`).disabled = data.page >= data.totalPages;

        const gridEl = document.getElementById(gridId);
        gridEl.innerHTML = '<div class="gsf-spinner"></div> Loading thumbnails...';

        fetch('?api_action=get_thumbnails', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ video_ids: slice })
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') {
                gridEl.innerHTML = d.videos.map(v => {
                    const thumb = v.thumbnail || '/placeholder.png';
                    const targetUrl = v.url || thumb; // Fallback to thumb if no video url
                    return `<a href="${targetUrl}" target="_blank" style="display:block;">
                               <img src="${thumb}" class="gsf-preview-thumb" title="Video #${v.id}">
                            </a>`;
                }).join('');
            }
        });
    };

    function appendLog(type, text, htmlPayload = '') {
        const logEl = document.getElementById('logEl');
        const now = new Date().toLocaleTimeString('en-US', { hour12: false });
        const lines = text.split('\n');
        
        lines.forEach((line, i) => {
            const div = document.createElement('div');
            div.className = 'gsf-log-line';
            if (type === 'summary') div.classList.add('gsf-log-summary');
            div.innerHTML = `<span class="gsf-log-time">${i === 0 ? now : '      '}</span><span class="gsf-log-${type}">${escH(line)}</span>`;
            logEl.appendChild(div);
        });

        if (htmlPayload) {
            const htmlDiv = document.createElement('div');
            htmlDiv.innerHTML = htmlPayload;
            logEl.appendChild(htmlDiv);
        }
        logEl.scrollTop = logEl.scrollHeight;
    }

    window.clearLog = function () {
        document.getElementById('logEl').innerHTML = '';
        document.getElementById('logSection').style.display = 'none';
    };

    function escH(s) { return String(s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

})();
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>