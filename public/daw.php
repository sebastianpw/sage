<?php
// public/daw.php
// SAGE Multitrack DAW — Forge UI
// v4: True DAW Architecture (Lanes, Draggable Clips, Master Playhead) + Restored v2 UI/API
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once __DIR__ . '/entity_icons.php';

$audioEntities = [
    'audio_ambiences',
    'audio_cues',
    'audio_dialogue_lines',
    'audio_foleys',
    'audio_fxsounds',
    'audio_themes',
];

$selectedEntity = $_REQUEST['entity'] ?? 'audio_cues';
if (!in_array($selectedEntity, $audioEntities, true)) {
    $selectedEntity = 'audio_cues';
}
if (isset($_GET['entity_type']) && in_array($_GET['entity_type'], $audioEntities, true)) {
    $selectedEntity = $_GET['entity_type'];
}
$deepLinkEntityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;

// ═══════════════════════════════════════════════════════════════════════════
// API HANDLER
// ═══════════════════════════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    header('Content-Type: application/json');
    $action    = $_REQUEST['api_action'];
    $reqEntity = $_REQUEST['entity'] ?? $selectedEntity;
    if (!in_array($reqEntity, $audioEntities, true)) $reqEntity = $selectedEntity;

    try {
        if ($action === 'get_entities') {
            $limit  = (int)($_GET['limit']  ?? 6);
            $offset = (int)($_GET['offset'] ?? 0);
            $search = trim($_GET['search']  ?? '');
            $table  = '`' . str_replace('`', '', $reqEntity) . '`';
            $where  = '1=1';
            if ($search) {
                $safeSearch = $pdo->quote("%{$search}%");
                $safeId     = (int)$search;
                $where     .= " AND (name LIKE {$safeSearch} OR id = {$safeId})";
            }
            $total = $pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
            $rows  = $pdo->query("SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $rows, 'total' => (int)$total]);
            exit;
        }

        if ($action === 'get_playlist') {
            $entityId = (int)($_GET['entity_id'] ?? 0);
            $search   = trim($_GET['search']     ?? '');
            if (!$entityId) { echo json_encode(['status' => 'success', 'data' => []]); exit; }
            $viewName   = 'v_player_' . $reqEntity;
            $viewExists = false;
            try { $pdo->query("SELECT 1 FROM `{$viewName}` LIMIT 1"); $viewExists = true; } catch (Exception $e) {}
            if ($viewExists) {
                $where = 'entity_id = ' . $entityId;
                if ($search) $where .= ' AND (name LIKE ' . $pdo->quote("%{$search}%") . ' OR filename LIKE ' . $pdo->quote("%{$search}%") . ')';
                $rows = $pdo->query("SELECT * FROM `{$viewName}` WHERE {$where} ORDER BY created_at DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $where = "entity_type = " . $pdo->quote($reqEntity) . " AND entity_id = {$entityId}";
                if ($search) $where .= ' AND filename LIKE ' . $pdo->quote("%{$search}%");
                $rows = $pdo->query("SELECT id AS audio_id, name, filename, created_at FROM audios WHERE {$where} ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode(['status' => 'success', 'data' => $rows]);
            exit;
        }

        if ($action === 'add_entity') {
            $table = '`' . $reqEntity . '`';
            $cols  = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);
            $name  = 'New ' . ucfirst(str_replace(['audio_', '_'], ['', ' '], $reqEntity)) . ' ' . time();
            $iCols = ['name']; $iVals = ['?']; $params = [$name];
            if (in_array('order', $cols)) { $iCols[] = '`order`'; $iVals[] = '0'; }
            $pdo->prepare("INSERT INTO {$table} (" . implode(',', $iCols) . ") VALUES (" . implode(',', $iVals) . ")")->execute($params);
            echo json_encode(['status' => 'success', 'id' => $pdo->lastInsertId()]);
            exit;
        }

        if ($action === 'delete_entity') {
            $id = (int)($_POST['entity_id'] ?? 0);
            if ($id > 0) $pdo->prepare("DELETE FROM `{$reqEntity}` WHERE id = ?")->execute([$id]);
            echo json_encode(['status' => 'success']);
            exit;
        }

        if ($action === 'update_field') {
            $id    = (int)($_POST['entity_id'] ?? 0);
            $field = $_POST['field']            ?? '';
            $value = $_POST['value']            ?? '';
            $table = '`' . str_replace('`', '', $reqEntity) . '`';
            $cols  = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);
            if ($id > 0 && in_array($field, $cols)) {
                $pdo->prepare("UPDATE {$table} SET `{$field}` = ? WHERE id = ?")->execute([$value, $id]);
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid field']);
            }
            exit;
        }

        if ($action === 'toggle_regenerate') {
            $id  = (int)($_POST['entity_id'] ?? 0);
            $val = (int)($_POST['value']     ?? 0);
            $col = $_POST['column']           ?? '';
            $table = '`' . str_replace('`', '', $reqEntity) . '`';
            $cols  = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);
            if ($id > 0 && in_array($col, $cols)) {
                $pdo->prepare("UPDATE {$table} SET `{$col}` = ? WHERE id = ?")->execute([$val, $id]);
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error']);
            }
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    exit;
}

// ─── Page Render ─────────────────────────────────────────────────────────────
$pageTitle = 'SAGE DAW — Multitrack';
ob_start();
?>
<script>
(function(){
    var t = localStorage.getItem('spw_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', t);
})();
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>
<script src="https://cdn.jsdelivr.net/npm/howler@2.2.4/dist/howler.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/wavesurfer.js@7/dist/wavesurfer.min.js"></script>
<?php if (file_exists(__DIR__ . '/forge_tool.php')) require_once "forge_tool.php"; ?>

<style>
/* ════════════════════════════════════════════════════════
   SAGE DAW v4 — Tokens
════════════════════════════════════════════════════════ */
:root {
    --bg:           #090910;
    --surface:      #0f0f18;
    --surface2:     #14141f;
    --border:       #1c1c2e;
    --border2:      #252538;
    --text:         #e4e4f0;
    --text-dim:     #6b6b8a;
    --text-faint:   #383850;
    --amber:        #f59e0b;
    --amber-glow:   rgba(245,158,11,0.15);
    --amber-border: rgba(245,158,11,0.35);
    --teal:         #14b8a6;
    --teal-glow:    rgba(20,184,166,0.12);
    --purple:       #8b5cf6;
    --purple-glow:  rgba(139,92,246,0.12);
    --red:          #ef4444;
    --green:        #22c55e;
    --font-mono:    'Space Mono', monospace;
    --font-sans:    'Syne', sans-serif;
    --header-h:     48px;
    --menubar-h:    34px;
    --ruler-h:      28px;
    --bin-w:        320px;
    --track-head-w: 220px;
    --lane-h:       80px;
}
[data-theme="light"] {
    --bg:         #f0f0f5;
    --surface:    #ffffff;
    --surface2:   #f7f7fc;
    --border:     #dcdcec;
    --border2:    #c8c8de;
    --text:       #1a1a2e;
    --text-dim:   #7070a0;
    --text-faint: #c0c0d8;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
    height: 100%; overflow: hidden;
    background: var(--bg); color: var(--text);
    font-family: var(--font-mono);
    font-size: 13px;
    -webkit-font-smoothing: antialiased;
}

/* ── Shell ──────────────────────────────────────────── */
.daw-shell {
    display: flex; flex-direction: column;
    height: 100dvh; overflow: hidden;
}

/* ── Main Header ────────────────────────────────────── */
.daw-header {
    flex-shrink: 0; height: var(--header-h);
    background: var(--surface); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; padding: 0 14px; gap: 12px; z-index: 100;
}
.daw-logo {
    font-family: var(--font-sans); font-size: 0.9rem; font-weight: 800;
    letter-spacing: 1.5px; color: var(--amber); text-transform: uppercase;
    display: flex; align-items: center; gap: 7px; flex-shrink: 0;
}
.daw-logo-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--amber); box-shadow: 0 0 8px var(--amber);
    animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:.4;transform:scale(.7);} }

.daw-transport {
    display: flex; align-items: center; gap: 6px;
    flex: 1; justify-content: center;
}
.tp-btn {
    width: 34px; height: 34px; border-radius: 6px;
    border: 1px solid var(--border2); background: transparent;
    color: var(--text-dim); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; transition: all .15s; flex-shrink: 0;
}
.tp-btn:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-glow); }
.tp-btn.pp {
    width: 42px; height: 42px;
    border-color: var(--amber-border); color: var(--amber); font-size: 18px;
}
.tp-btn.pp:hover { background: var(--amber-glow); border-color: var(--amber); }
.tp-btn.pp.playing {
    background: var(--amber-glow); border-color: var(--amber);
    box-shadow: 0 0 12px var(--amber-glow);
}
.tp-time {
    font-family: var(--font-mono); font-size: 0.85rem; color: var(--amber);
    background: var(--bg); border: 1px solid var(--border2);
    border-radius: 4px; padding: 4px 10px; letter-spacing: 2px;
    min-width: 90px; text-align: center; font-weight: 700;
}
.daw-header-right {
    display: flex; align-items: center; gap: 8px; flex-shrink: 0;
}
.hdr-btn {
    height: 28px; padding: 0 9px; border-radius: 5px;
    border: 1px solid var(--border2); background: transparent;
    color: var(--text-dim); font-family: var(--font-mono);
    font-size: 0.68rem; cursor: pointer;
    display: flex; align-items: center; gap: 4px;
    transition: all .15s; letter-spacing: .5px; text-transform: uppercase; font-weight: 700;
}
.hdr-btn:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-glow); }
.hdr-btn.active { border-color: var(--amber-border); color: var(--amber); background: var(--amber-glow); }
.hdr-btn.danger:hover { border-color: var(--red); color: var(--red); background: rgba(239,68,68,.1); }
.daw-zoom { display: flex; align-items: center; gap: 5px; }
.zoom-lbl { font-size: .62rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; }
.zoom-range {
    -webkit-appearance: none; width: 90px; height: 3px;
    background: var(--border2); border-radius: 2px; outline: none; cursor: pointer;
}
.zoom-range::-webkit-slider-thumb { -webkit-appearance: none; width: 12px; height: 12px; border-radius: 50%; background: var(--amber); cursor: pointer; }
.zoom-range::-moz-range-thumb { width: 12px; height: 12px; border-radius: 50%; background: var(--amber); border: none; cursor: pointer; }

/* ── Sub-Header Menu Bar ────────────────────────────── */
.daw-menubar {
    flex-shrink: 0; height: var(--menubar-h);
    background: var(--surface2);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center;
    padding: 0 10px; gap: 4px; z-index: 99;
    overflow-x: auto; overflow-y: hidden;
}
.daw-menubar::-webkit-scrollbar { height: 2px; }
.mb-btn {
    height: 22px; padding: 0 8px; border-radius: 4px;
    border: 1px solid transparent; background: transparent;
    color: var(--text-dim); font-family: var(--font-mono);
    font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .6px; cursor: pointer; white-space: nowrap;
    display: flex; align-items: center; gap: 4px;
    transition: all .15s;
}
.mb-btn:hover { border-color: var(--border2); color: var(--text); background: rgba(255,255,255,.04); }
.mb-btn.active { border-color: var(--amber-border); color: var(--amber); background: var(--amber-glow); }
.mb-sep { width: 1px; height: 14px; background: var(--border2); margin: 0 4px; flex-shrink: 0; }

.mb-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 14px; height: 14px; border-radius: 3px;
    background: var(--teal); color: #000;
    font-size: 0.55rem; font-weight: 700; margin-left: 2px;
}
.mb-badge.off { background: var(--border2); color: var(--text-dim); }

/* ── Body Layout ───────────────────────────────────────── */
.daw-body { flex: 1; min-height: 0; display: flex; flex-direction: column; background: var(--bg); position: relative; }

/* ── Ruler Row ─────────────────────────────────────────── */
.daw-ruler-row {
    display: flex; height: var(--ruler-h); flex-shrink: 0; background: var(--surface2); border-bottom: 2px solid var(--border2);
}
.ruler-spacer { width: var(--track-head-w); flex-shrink: 0; border-right: 1px solid var(--border); }
.ruler-scroll { flex: 1; overflow: hidden; position: relative; }
#rulerCanvas { display: block; height: 100%; image-rendering: crisp-edges; }

/* ── Master Timeline Scroll Area ───────────────────────── */
.daw-timeline-scroll {
    flex: 1; overflow: auto; position: relative; display: flex; flex-direction: column;
}
.daw-timeline-content {
    position: relative; min-width: 100%; min-height: 100%; display: flex; flex-direction: column;
}
.playhead {
    position: absolute; top: 0; bottom: 0; width: 2px; background: var(--red); z-index: 50; pointer-events: none;
    box-shadow: 0 0 8px rgba(239,68,68,0.5); transform-origin: top left;
}

/* ── Track Row (Lanes) ─────────────────────────────────── */
.daw-track {
    display: flex; min-width: 100%; height: var(--lane-h); border-bottom: 1px solid var(--border);
}

.daw-empty {
    position: absolute; inset: 0; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 14px; color: var(--text-faint); pointer-events: none; user-select: none; padding: 40px; z-index: 0;
}
.daw-empty-icon { font-size: 2.4rem; opacity: .3; }
.daw-empty-text { font-family: var(--font-sans); font-size: .85rem; font-weight: 600; text-align: center; line-height: 1.6; color: var(--text-dim); }
.daw-empty-hint { font-size: .7rem; color: var(--text-faint); text-align: center; }

/* Sticky Left Panel */
.track-head {
    position: sticky; left: 0; width: var(--track-head-w); flex-shrink: 0; z-index: 10;
    background: var(--surface2); border-right: 1px solid var(--border);
    display: flex; flex-direction: column; justify-content: center; padding: 0 10px; gap: 4px;
}
.track-head-top { display: flex; align-items: center; gap: 8px; }
.track-color-strip { width: 3px; height: 26px; border-radius: 2px; flex-shrink: 0; }
.track-name { font-family: var(--font-sans); font-size: .76rem; font-weight: 600; color: var(--text); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.track-btns { display: flex; gap: 4px; }
.tk-btn { width: 22px; height: 22px; border-radius: 4px; border: 1px solid var(--border2); background: transparent; color: var(--text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 11px; transition: all .12s; }
.tk-btn:hover { background: var(--teal-glow); border-color: var(--teal); color: var(--teal); }
.tk-btn.muted { border-color: var(--amber-border); color: var(--amber); background: var(--amber-glow); }
.tk-btn.tk-del:hover { background: rgba(239,68,68,.1); border-color: var(--red); color: var(--red); }
.track-vol-wrap { display: flex; align-items: center; gap: 6px; padding-left: 11px; }
.track-vol { -webkit-appearance: none; flex: 1; height: 3px; background: var(--border2); border-radius: 2px; outline: none; cursor: pointer; }
.track-vol::-webkit-slider-thumb { -webkit-appearance: none; width: 10px; height: 10px; border-radius: 50%; background: var(--text-dim); cursor: pointer; }

/* The Drop Zone Lane */
.track-lane {
    flex: 1; position: relative; background: transparent; transition: background .15s;
}
.track-lane.drag-over { background: rgba(245,158,11,0.08); box-shadow: inset 0 0 0 1px var(--amber); }

/* ── Absolute Audio Clips ──────────────────────────────── */
.daw-clip {
    position: absolute; top: 4px; bottom: 4px; border-radius: 4px; overflow: hidden;
    background: var(--surface); border: 1px solid var(--border2);
    display: flex; flex-direction: column; cursor: grab; user-select: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3); transition: box-shadow .15s, border-color .15s;
}
.daw-clip:hover { border-color: var(--text-faint); }
.daw-clip.drag-over { border-color: var(--amber); background: rgba(245,158,11,0.1); }
.daw-clip:active, .daw-clip.dragging { cursor: grabbing; z-index: 20; border-color: var(--amber); box-shadow: 0 8px 24px rgba(0,0,0,0.6); opacity: 0.9; }
.clip-header {
    height: 18px; font-size: .65rem; padding: 0 6px; display: flex; align-items: center;
    background: rgba(255,255,255,0.05); color: var(--text); border-bottom: 1px solid var(--border);
    overflow: hidden; white-space: nowrap; font-family: var(--font-sans); font-weight: 600;
}
.clip-ws { flex: 1; position: relative; width: 100%; pointer-events: none; }
.clip-del { position: absolute; top: 2px; right: 4px; cursor: pointer; color: var(--text-dim); font-size: 10px; z-index: 2; padding: 2px; border-radius: 3px; }
.clip-del:hover { background: rgba(239,68,68,0.2); color: var(--red); }


/* ── Asset Bin Flyout ───────────────────────────────── */
.bin-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.55);
    z-index: 900; opacity: 0; pointer-events: none; transition: opacity .2s;
}
.bin-overlay.open { opacity: 1; pointer-events: auto; }
.bin-panel {
    position: fixed; top: 0; right: 0; bottom: 0;
    width: min(var(--bin-w), 92vw); background: var(--surface);
    border-left: 1px solid var(--border); z-index: 901;
    display: flex; flex-direction: column;
    transform: translateX(100%);
    transition: transform .25s cubic-bezier(.4,0,.2,1);
    will-change: transform;
}
.bin-panel.open { transform: translateX(0); }
.bin-header {
    flex-shrink: 0; height: var(--header-h);
    display: flex; align-items: center; padding: 0 14px; gap: 10px;
    border-bottom: 1px solid var(--border); background: var(--surface2);
}
.bin-title { font-family: var(--font-sans); font-weight: 700; font-size: .85rem; color: var(--text); flex: 1; }
.bin-close {
    width: 28px; height: 28px; border-radius: 5px;
    border: 1px solid var(--border2); background: transparent;
    color: var(--text-dim); cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 14px; transition: all .12s;
}
.bin-close:hover { border-color: var(--red); color: var(--red); background: rgba(239,68,68,.08); }
.bin-entity-bar {
    flex-shrink: 0; padding: 8px 12px;
    border-bottom: 1px solid var(--border);
    display: flex; flex-direction: column; gap: 7px;
    background: var(--surface2);
}
.bin-entity-select {
    width: 100%; background: var(--bg); border: 1px solid var(--border2);
    color: var(--text); padding: 7px 10px; border-radius: 5px;
    font-family: var(--font-mono); font-size: .75rem;
    outline: none; cursor: pointer; transition: border-color .15s;
}
.bin-entity-select:focus { border-color: var(--amber); }
.bin-search-row { display: flex; gap: 6px; align-items: center; }
.bin-search {
    flex: 1; background: var(--bg); border: 1px solid var(--border2);
    color: var(--text); padding: 6px 10px; border-radius: 5px;
    font-family: var(--font-mono); font-size: .75rem; outline: none;
    transition: border-color .15s;
}
.bin-search:focus { border-color: var(--teal); }
.bin-search::placeholder { color: var(--text-faint); }
.bin-new-btn {
    height: 30px; padding: 0 10px; border-radius: 5px;
    border: 1px solid var(--border2); background: transparent;
    color: var(--teal); font-family: var(--font-mono); font-size: .7rem; font-weight: 700;
    cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 4px;
    transition: all .15s; letter-spacing: .5px;
}
.bin-new-btn:hover { background: var(--teal-glow); border-color: var(--teal); }
.bin-entity-list { flex-shrink: 0; max-height: 38vh; overflow-y: auto; border-bottom: 2px solid var(--border2); }
.bin-entity-item {
    display: flex; align-items: center; gap: 8px;
    padding: 9px 12px; border-bottom: 1px solid var(--border);
    cursor: pointer; transition: background .12s;
}
.bin-entity-item:hover  { background: rgba(255,255,255,.04); }
.bin-entity-item.active { background: var(--amber-glow); border-left: 3px solid var(--amber); padding-left: 9px; }
.bi-eid  { font-size: .68rem; font-weight: 700; color: var(--amber); min-width: 36px; font-family: var(--font-mono); }
.bi-name { font-family: var(--font-sans); font-size: .78rem; color: var(--text); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bi-date { font-size: .62rem; color: var(--text-dim); flex-shrink: 0; }
.bin-pagination {
    display: flex; align-items: center; gap: 4px;
    padding: 5px 12px; border-bottom: 1px solid var(--border);
    background: var(--surface2); flex-shrink: 0;
}
.pg-btn {
    width: 24px; height: 24px; border-radius: 3px;
    border: 1px solid var(--border2); background: transparent;
    color: var(--text-dim); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; transition: all .12s;
}
.pg-btn:hover:not(:disabled) { border-color: var(--teal); color: var(--teal); }
.pg-btn:disabled { opacity: .3; cursor: default; }
.pg-num { font-size: .7rem; font-family: var(--font-mono); color: var(--amber); font-weight: 700; padding: 0 6px; }
.pg-of  { font-size: .65rem; color: var(--text-dim); }
.bin-assets-header {
    flex-shrink: 0; padding: 7px 12px 5px;
    display: flex; align-items: center; justify-content: space-between;
    background: var(--surface2); border-bottom: 1px solid var(--border);
}
.bin-assets-lbl   { font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-dim); }
.bin-assets-count { font-size: .65rem; color: var(--text-dim); }
.bin-assets-list  { flex: 1; overflow-y: auto; min-height: 0; }
.bin-asset-item   { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-bottom: 1px solid var(--border); transition: background .12s; }
.bin-asset-item:hover { background: rgba(255,255,255,.03); }
.ba-drag-handle {
    cursor: grab; color: var(--text-faint); padding: 0 4px;
    display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.ba-drag-handle:active { cursor: grabbing; color: var(--amber); }
.ba-play {
    width: 28px; height: 28px; border-radius: 50%;
    border: 1px solid var(--border2); background: transparent;
    color: var(--text-dim); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; flex-shrink: 0; transition: all .12s;
}
.ba-play:hover       { border-color: var(--teal); color: var(--teal); background: var(--teal-glow); }
.ba-play.previewing  { border-color: var(--amber); color: var(--amber); background: var(--amber-glow); animation: playing-pulse 1s ease-in-out infinite; }
@keyframes playing-pulse { 0%,100%{opacity:1;} 50%{opacity:.6;} }
.ba-info { flex: 1; min-width: 0; }
.ba-name { font-family: var(--font-sans); font-size: .75rem; font-weight: 600; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ba-file { font-size: .62rem; color: var(--text-dim); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-top: 1px; }
.ba-add {
    width: 28px; height: 28px; border-radius: 5px;
    border: 1px solid var(--border2); background: transparent;
    color: var(--purple); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; flex-shrink: 0; transition: all .12s;
}
.ba-add:hover { background: var(--purple-glow); border-color: rgba(139,92,246,.5); }
.bin-state {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 32px 20px; gap: 8px; color: var(--text-dim); font-size: .75rem; text-align: center;
}

/* ════════════════════════════════════════════════════════
   PARAM MODAL — shared pattern, reusable for all contexts
════════════════════════════════════════════════════════ */
.param-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,.75);
    z-index: 9999; display: none; align-items: flex-start; justify-content: center;
    padding-top: 60px;
}
.param-backdrop.open { display: flex; }

.param-modal {
    background: var(--surface); border: 1px solid var(--border2);
    border-radius: 10px; width: min(480px, 95vw);
    box-shadow: 0 24px 64px rgba(0,0,0,.6);
    display: flex; flex-direction: column; overflow: hidden;
    animation: modal-drop .18s cubic-bezier(.4,0,.2,1) both;
}
@keyframes modal-drop { from{opacity:0;transform:translateY(-12px);} to{opacity:1;transform:translateY(0);} }

.pm-header {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 16px; border-bottom: 1px solid var(--border);
    background: var(--surface2); flex-shrink: 0;
}
.pm-title {
    font-family: var(--font-sans); font-weight: 700; font-size: .9rem;
    color: var(--text); flex: 1;
}
.pm-close {
    width: 26px; height: 26px; border-radius: 5px;
    border: 1px solid var(--border2); background: transparent;
    color: var(--text-dim); cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 13px; transition: all .12s;
}
.pm-close:hover { border-color: var(--red); color: var(--red); }

.pm-body { padding: 18px 16px; display: flex; flex-direction: column; gap: 16px; overflow-y: auto; }

/* Row helpers */
.pm-row { display: flex; align-items: center; gap: 10px; }
.pm-row-stack { display: flex; flex-direction: column; gap: 5px; }
.pm-label {
    font-size: .68rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .8px; color: var(--text-dim); min-width: 80px;
}
.pm-sublabel { font-size: .65rem; color: var(--text-faint); margin-top: 1px; }

/* Number spinner with +/- buttons */
.pm-spinner {
    display: flex; align-items: center; gap: 0; flex-shrink: 0;
}
.pm-spin-btn {
    width: 28px; height: 32px; border: 1px solid var(--border2); background: var(--surface2);
    color: var(--text-dim); cursor: pointer; font-size: 14px;
    display: flex; align-items: center; justify-content: center; transition: all .12s;
}
.pm-spin-btn:first-child { border-radius: 5px 0 0 5px; }
.pm-spin-btn:last-child  { border-radius: 0 5px 5px 0; }
.pm-spin-btn:hover       { background: var(--teal-glow); color: var(--teal); border-color: var(--teal); }
.pm-spin-val {
    min-width: 54px; height: 32px; text-align: center;
    background: var(--bg); border: 1px solid var(--border2); border-left: none; border-right: none;
    color: var(--amber); font-family: var(--font-mono); font-size: .85rem; font-weight: 700;
    outline: none; padding: 0 4px;
}
.pm-spin-val:focus { border-color: var(--amber); }

/* Select */
.pm-select {
    flex: 1; background: var(--bg); border: 1px solid var(--border2);
    color: var(--text); padding: 7px 10px; border-radius: 5px;
    font-family: var(--font-mono); font-size: .8rem; outline: none; cursor: pointer;
}
.pm-select:focus { border-color: var(--amber); }

/* Toggle switch */
.pm-toggle-wrap { display: flex; align-items: center; gap: 8px; }
.pm-toggle {
    position: relative; width: 38px; height: 20px;
    flex-shrink: 0; cursor: pointer;
}
.pm-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
.pm-toggle-track {
    position: absolute; inset: 0; border-radius: 10px;
    background: var(--border2); transition: background .2s;
}
.pm-toggle input:checked ~ .pm-toggle-track { background: var(--teal); }
.pm-toggle-thumb {
    position: absolute; top: 3px; left: 3px;
    width: 14px; height: 14px; border-radius: 50%;
    background: var(--text-dim); transition: all .2s;
}
.pm-toggle input:checked ~ .pm-toggle-thumb { left: 21px; background: #000; }
.pm-toggle-lbl { font-size: .78rem; color: var(--text); }

/* Color swatch picker row */
.pm-swatch-row { display: flex; gap: 6px; flex-wrap: wrap; }
.pm-swatch {
    width: 22px; height: 22px; border-radius: 4px; cursor: pointer;
    border: 2px solid transparent; transition: border-color .12s; flex-shrink: 0;
}
.pm-swatch.active { border-color: var(--amber); }
.pm-swatch:hover  { transform: scale(1.15); }

/* Divider */
.pm-divider { height: 1px; background: var(--border); margin: 2px 0; }

/* Footer */
.pm-footer {
    display: flex; justify-content: flex-end; gap: 8px;
    padding: 12px 16px; border-top: 1px solid var(--border); background: var(--surface2);
    flex-shrink: 0;
}
.pm-btn {
    height: 32px; padding: 0 16px; border-radius: 5px;
    font-family: var(--font-mono); font-size: .72rem; font-weight: 700;
    letter-spacing: .4px; cursor: pointer; transition: all .15s;
    display: flex; align-items: center; gap: 5px;
}
.pm-btn-cancel { border: 1px solid var(--border2); background: transparent; color: var(--text-dim); }
.pm-btn-cancel:hover { border-color: var(--text-dim); color: var(--text); }
.pm-btn-apply  { border: 1px solid var(--amber-border); background: var(--amber-glow); color: var(--amber); }
.pm-btn-apply:hover { background: var(--amber); color: #000; }

/* Scrollbars */
::-webkit-scrollbar        { width: 5px; height: 5px; }
::-webkit-scrollbar-track  { background: transparent; }
::-webkit-scrollbar-thumb  { background: var(--border2); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-dim); }
</style>

<!-- ════════════════════════════════════════════════════
     MARKUP
════════════════════════════════════════════════════ -->
<div class="daw-shell">

    <!-- ── Main Header / Transport ──────────────────── -->
    <div class="daw-header">
        <div class="daw-logo"><div class="daw-logo-dot"></div>DAW</div>

        <div class="daw-transport">
            <button class="tp-btn" onclick="dawRewind()" title="Rewind to start"><i class="bi bi-skip-backward-fill"></i></button>
            <button class="tp-btn pp" id="btnPP" onclick="dawPlayPause()" title="Play / Pause (Space)"><i class="bi bi-play-fill" id="ppIcon"></i></button>
            <button class="tp-btn" onclick="dawStop()" title="Stop"><i class="bi bi-stop-fill"></i></button>
            <div class="tp-time" id="tpTime">0:00.000</div>
        </div>

        <div class="daw-header-right">
            <div class="daw-zoom">
                <span class="zoom-lbl">Zoom</span>
                <input type="range" class="zoom-range" id="zoomRange" min="10" max="300" value="80" oninput="dawSetZoom(this.value)">
            </div>
            <button class="hdr-btn" id="btnBin" onclick="openBin()" title="Asset Bin">
                <i class="bi bi-collection-play"></i> Bin
            </button>
            <button class="hdr-btn danger" onclick="dawClearAll()" title="Clear all tracks">
                <i class="bi bi-trash3"></i>
            </button>
        </div>
    </div>

    <!-- ── Sub-Header Menu Bar ───────────────────────── -->
    <div class="daw-menubar">
        <button class="mb-btn" onclick="addTrackLane('New Track')" title="Add Empty Lane">
            <i class="bi bi-plus-lg"></i> Add Track
        </button>
        <span class="mb-sep"></span>
        <button class="mb-btn" id="mbBtnProject" onclick="openParamModal('project')" title="Project settings">
            <i class="bi bi-sliders2"></i> Project
        </button>
        <span class="mb-sep"></span>
        <!-- Grid snap indicator -->
        <button class="mb-btn" id="mbBtnSnap" onclick="toggleGridSnap()" title="Toggle grid snap">
            <i class="bi bi-magnet"></i> Snap
            <span class="mb-badge off" id="snapBadge">OFF</span>
        </button>
        <!-- Grid visibility toggle -->
        <button class="mb-btn" id="mbBtnGrid" onclick="toggleGridVisible()" title="Toggle grid overlay">
            <i class="bi bi-grid-3x2-gap"></i> Grid
            <span class="mb-badge" id="gridBadge">ON</span>
        </button>
        <span class="mb-sep"></span>
        <!-- Live readout: BPM / sig -->
        <span class="ri-item" style="margin-left:4px;">
            <i class="bi bi-music-note-beamed"></i>
            <span class="ri-val" id="mbBpm">120</span><span> BPM</span>
        </span>
        <span class="ri-item" style="margin-left:8px;">
            <span class="ri-val" id="mbSig">4/4</span>
        </span>
        <span class="ri-item" style="margin-left:8px;">
            <i class="bi bi-layout-three-columns"></i>
            <span class="ri-val" id="mbGrid">1/16</span>
        </span>
    </div>

    <!-- ── Body Layout ───────────────────────────────── -->
    <div class="daw-body">
        
        <!-- Ruler Row (Sticky left controls spacer + scrolling canvas) -->
        <div class="daw-ruler-row">
            <div class="ruler-spacer"></div>
            <div class="ruler-scroll" id="rulerWrap">
                <div id="rulerContent" style="height: 100%; min-width: 100%;">
                    <canvas id="rulerCanvas"></canvas>
                </div>
            </div>
        </div>

        <!-- Master Timeline (Tracks & Clips) -->
        <div class="daw-timeline-scroll" id="timelineScroll">
            <div class="daw-empty" id="dawEmpty">
                <div class="daw-empty-icon"><i class="bi bi-soundwave"></i></div>
                <div class="daw-empty-text">Timeline empty</div>
                <div class="daw-empty-hint">Open the <strong style="color:var(--amber);">Bin</strong> and click <i class="bi bi-plus-circle"></i> on any audio asset to add tracks.<br>Or drag and drop items here.</div>
            </div>
            
            <div class="daw-timeline-content" id="dawTimelineContent">
                <div class="playhead" id="playhead"></div>
                <!-- Track Lanes dynamically added here -->
            </div>
        </div>
        
    </div>
</div>

<!-- ── Asset Bin ─────────────────────────────────────── -->
<div class="bin-overlay" id="binOverlay" onclick="closeBin()"></div>
<div class="bin-panel"   id="binPanel">
    <div class="bin-header">
        <div class="bin-title"><i class="bi bi-collection-play" style="color:var(--amber);margin-right:5px;"></i>Asset Bin</div>
        <button class="bin-close" onclick="closeBin()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="bin-entity-bar">
        <select class="bin-entity-select" id="binEntitySelect" onchange="onEntityTypeChange(this.value)">
            <?php foreach ($audioEntities as $ename):
                $icon = $entityIcons[$ename] ?? '🎧';
            ?>
                <option value="<?php echo htmlspecialchars($ename, ENT_QUOTES); ?>"
                    <?php echo ($ename === $selectedEntity ? 'selected' : ''); ?>>
                    <?php echo htmlspecialchars($icon . '  ' . $ename, ENT_QUOTES); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="bin-search-row">
            <input type="text" class="bin-search" id="binSearch" placeholder="Search entities…" oninput="debouncedEntitySearch()">
            <button class="bin-new-btn" onclick="createNewEntity()"><i class="bi bi-plus-lg"></i> New</button>
        </div>
    </div>
    <div class="bin-entity-list" id="binEntityList">
        <div class="bin-state"><div class="spin-s"></div> Loading…</div>
    </div>
    <div class="bin-pagination" id="binPagination" style="display:none;">
        <button class="pg-btn" id="pgPrev" onclick="changePage(-1)"><i class="bi bi-chevron-left"></i></button>
        <span class="pg-num" id="pgCur">1</span>
        <span class="pg-of"  id="pgOf">/ 1</span>
        <button class="pg-btn" id="pgNext" onclick="changePage(1)"><i class="bi bi-chevron-right"></i></button>
    </div>
    <div class="bin-assets-header">
        <span class="bin-assets-lbl">Audios</span>
        <span class="bin-assets-count" id="binAssetsCount">–</span>
    </div>
    <div style="padding:6px 12px;border-bottom:1px solid var(--border);flex-shrink:0;">
        <input type="text" class="bin-search" id="binAssetSearch" placeholder="Filter audios…" oninput="debouncedAssetSearch()" style="width:100%;">
    </div>
    <div class="bin-assets-list" id="binAssetList">
        <div class="bin-state" style="color:var(--text-faint);">↑ Select an entity above</div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════
     PROJECT PARAM MODAL
════════════════════════════════════════════════════ -->
<div class="param-backdrop" id="paramBackdrop" onclick="onBackdropClick(event)">
    <div class="param-modal" id="paramModal">

        <div class="pm-header">
            <i class="bi bi-sliders2" style="color:var(--amber);font-size:1rem;"></i>
            <div class="pm-title" id="pmTitle">Project Settings</div>
            <button class="pm-close" onclick="closeParamModal()"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="pm-body" id="pmBody">

            <!-- ── TEMPO ── -->
            <div class="pm-row">
                <div style="flex:1;">
                    <div class="pm-label">Tempo</div>
                    <div class="pm-sublabel">Beats per minute</div>
                </div>
                <div class="pm-spinner">
                    <button class="pm-spin-btn" onclick="spinBpm(-1)">−</button>
                    <input  class="pm-spin-val" id="pmBpm" type="number" min="20" max="300" value="120" onchange="clampBpm()">
                    <button class="pm-spin-btn" onclick="spinBpm(1)">+</button>
                </div>
                <span style="font-size:.7rem;color:var(--text-dim);margin-left:4px;">BPM</span>
            </div>

            <div class="pm-divider"></div>

            <!-- ── TIME SIGNATURE ── -->
            <div class="pm-row">
                <div style="flex:1;">
                    <div class="pm-label">Time Signature</div>
                    <div class="pm-sublabel">Beats per bar / note value</div>
                </div>
                <div class="pm-spinner">
                    <button class="pm-spin-btn" onclick="spinSigNum(-1)">−</button>
                    <input  class="pm-spin-val" id="pmSigNum" type="number" min="1" max="16" value="4" style="min-width:40px;" onchange="clampSig()">
                    <button class="pm-spin-btn" onclick="spinSigNum(1)">+</button>
                </div>
                <span style="font-size:1.1rem;color:var(--text-dim);margin:0 4px;">/</span>
                <select class="pm-select" id="pmSigDen" style="flex:none;width:68px;">
                    <option value="2">2</option>
                    <option value="4" selected>4</option>
                    <option value="8">8</option>
                    <option value="16">16</option>
                </select>
            </div>

            <div class="pm-divider"></div>

            <!-- ── GRID SUBDIVISION ── -->
            <div class="pm-row">
                <div style="flex:1;">
                    <div class="pm-label">Grid Division</div>
                    <div class="pm-sublabel">Smallest visible grid unit</div>
                </div>
                <select class="pm-select" id="pmGridDiv" style="width:90px;flex:none;">
                    <option value="1">1/1</option>
                    <option value="2">1/2</option>
                    <option value="4" selected>1/4</option>
                    <option value="8">1/8</option>
                    <option value="16">1/16</option>
                    <option value="32">1/32</option>
                </select>
            </div>

            <div class="pm-divider"></div>

            <!-- ── GRID OPTIONS ── -->
            <div class="pm-row">
                <div style="flex:1;">
                    <div class="pm-label">Show Grid</div>
                    <div class="pm-sublabel">Draw beat lines on tracks</div>
                </div>
                <label class="pm-toggle" id="pmGridVisibleToggle">
                    <input type="checkbox" id="pmGridVisible" checked>
                    <span class="pm-toggle-track"></span>
                    <span class="pm-toggle-thumb"></span>
                </label>
            </div>

            <div class="pm-row">
                <div style="flex:1;">
                    <div class="pm-label">Snap to Grid</div>
                    <div class="pm-sublabel">Quantise clip positions</div>
                </div>
                <label class="pm-toggle">
                    <input type="checkbox" id="pmSnapEnabled">
                    <span class="pm-toggle-track"></span>
                    <span class="pm-toggle-thumb"></span>
                </label>
            </div>

            <div class="pm-divider"></div>

            <!-- ── GRID COLOUR ── -->
            <div class="pm-row-stack">
                <div class="pm-label">Grid Colour</div>
                <div class="pm-swatch-row" id="pmSwatchRow">
                    <!-- injected by JS -->
                </div>
            </div>

            <!-- ── GRID OPACITY ── -->
            <div class="pm-row">
                <div style="flex:1;">
                    <div class="pm-label">Grid Opacity</div>
                </div>
                <input type="range" id="pmGridOpacity" min="5" max="80" value="15"
                    style="-webkit-appearance:none;width:120px;height:3px;background:var(--border2);border-radius:2px;outline:none;cursor:pointer;"
                    oninput="document.getElementById('pmOpacityVal').textContent=this.value+'%'">
                <span id="pmOpacityVal" style="font-size:.72rem;color:var(--amber);min-width:36px;text-align:right;">15%</span>
            </div>

        </div><!-- /pm-body -->

        <div class="pm-footer">
            <button class="pm-btn pm-btn-cancel" onclick="closeParamModal()">Cancel</button>
            <button class="pm-btn pm-btn-apply"  onclick="applyProjectSettings()"><i class="bi bi-check2"></i> Apply</button>
        </div>
    </div>
</div>


<script>
// ═══════════════════════════════════════════════════════════════════════════
// SAGE DAW v4 — Core State & Engine
// ═══════════════════════════════════════════════════════════════════════════

const PROJECT = {
    bpm: 120, sigNum: 4, sigDen: 4, gridDiv: 16,
    gridVisible: true, snapEnabled: true,
    gridColor: '#f59e0b', gridOpacity: 15
};

const GRID_COLORS = ['#f59e0b','#14b8a6','#8b5cf6','#ef4444','#22c55e','#3b82f6','#ec4899','#f97316','#ffffff','#a0a0c0'];
let selectedSwatchIdx = 0;

const STATE = {
    entity: '<?php echo addslashes($selectedEntity); ?>',
    entityId: <?php echo $deepLinkEntityId ?: 'null'; ?>,
    page: 1, totalPages: 1, pageSize: 6,
    assetData: [],
    
    // Core DAW State
    tracks: [],   // Array of lane objects: { id, name, color, vol, muted }
    clips: [],    // Array of absolute clips: { id, trackId, url, name, startTime, duration, ws, isPlaying, el }
    trackIdSeq: 0,
    clipIdSeq: 0,
    
    masterZoom: 80, // pixels per second
    projectDuration: 60, // Minimum initial length (seconds)
    
    // Playback Engine
    isPlaying: false,
    curTime: 0,
    lastFrameTime: 0,
    rafId: null,
    previewHowl: null,
    previewIdx: -1,
    
    entityDebounce: null,
    assetDebounce: null,
};

const COLORS = ['#f59e0b','#14b8a6','#8b5cf6','#ef4444','#22c55e','#3b82f6','#ec4899','#f97316','#06b6d4','#a855f7'];
let colorIdx = 0;
function nextColor() { return COLORS[colorIdx++ % COLORS.length]; }

function esc(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }
function trunc(s,n){ if (!s) return ''; s=String(s); return s.length>n?s.slice(0,n)+'…':s; }
function fmt(secs){
    if (isNaN(secs)||secs<0) secs=0;
    const m=Math.floor(secs/60), s=secs%60;
    return m+':'+s.toFixed(3).padStart(6,'0');
}
function api(action, params={}, method='GET') {
    const base = `?api_action=${action}&entity=${encodeURIComponent(STATE.entity)}`;
    if (method==='GET') {
        const qs = Object.entries(params).map(([k,v])=>`${k}=${encodeURIComponent(v)}`).join('&');
        return fetch(base+(qs?'&'+qs:'')).then(r=>r.json());
    }
    const body = new URLSearchParams({entity:STATE.entity,...params});
    return fetch(base,{method:'POST',body}).then(r=>r.json());
}

// ── Initialization ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    buildSwatches();
    syncMenuBar();
    loadEntities(1);
    if (STATE.entityId) loadAssetsForEntity(STATE.entityId);
    
    // Sync Ruler horizontal scroll to Master Timeline scroll
    document.getElementById('timelineScroll').addEventListener('scroll', (e) => {
        document.getElementById('rulerWrap').scrollLeft = e.target.scrollLeft;
    });

    // Seek Playhead by clicking the timeline background or ruler
    const seekHandler = (e) => {
        if (e.target.closest('.daw-clip')) return; // Ignore if clicking a clip
        if (e.target.closest('.track-head')) return; // Ignore left panel
        
        const content = document.getElementById('dawTimelineContent');
        const rect = content.getBoundingClientRect();
        
        // Calculate X accounting for the track-head width (which is sticky)
        let localX = e.clientX - rect.left - 220; 
        if (localX < 0) localX = 0;

        let targetTime = localX / STATE.masterZoom;
        
        if (PROJECT.snapEnabled) {
            const snapSecs = (60 / PROJECT.bpm) / (PROJECT.gridDiv / PROJECT.sigDen);
            targetTime = Math.round(targetTime / snapSecs) * snapSecs;
        }
        
        seekTimeline(targetTime);
    };
    document.getElementById('dawTimelineContent').addEventListener('mousedown', seekHandler);
    document.getElementById('rulerWrap').addEventListener('mousedown', seekHandler);

    // Pinch to zoom logic on timeline scroll container
    let pinch = { active: false, startDist: 0, startZoom: 0 };
    function getDist(touches) {
        const dx = touches[0].clientX - touches[1].clientX;
        const dy = touches[0].clientY - touches[1].clientY;
        return Math.sqrt(dx*dx + dy*dy);
    }
    const tl = document.getElementById('timelineScroll');
    tl.addEventListener('touchstart', (e) => {
        if (e.touches.length === 2) {
            pinch.active = true;
            pinch.startDist = getDist(e.touches);
            pinch.startZoom = STATE.masterZoom;
            e.preventDefault();
        }
    }, {passive: false});
    tl.addEventListener('touchmove', (e) => {
        if (pinch.active && e.touches.length === 2) {
            e.preventDefault();
            const scale = getDist(e.touches) / pinch.startDist;
            let newZ = pinch.startZoom * scale;
            newZ = Math.max(10, Math.min(300, newZ));
            document.getElementById('zoomRange').value = newZ;
            dawSetZoom(newZ);
        }
    }, {passive: false});
    tl.addEventListener('touchend', (e) => {
        if (e.touches.length < 2) pinch.active = false;
    });

    // Initial Layout Setup
    updateMasterLayout();
});

// ═══════════════════════════════════════════════════════════════════════════
// MASTER TIMELINE LAYOUT & GRID
// ═══════════════════════════════════════════════════════════════════════════

function pxPerBeat() { return STATE.masterZoom * (60 / PROJECT.bpm); }
function pxPerBar() { return pxPerBeat() * PROJECT.sigNum; }
function pxPerDiv() { return pxPerBeat() / (PROJECT.gridDiv / PROJECT.sigDen); }

// Keep timeline wide enough for all clips + 15 seconds padding
function updateMasterLayout() {
    let maxTime = 60; // minimum 60s
    STATE.clips.forEach(c => {
        if (c.startTime + c.duration > maxTime) maxTime = c.startTime + c.duration;
    });
    STATE.projectDuration = maxTime + 15;
    
    const totalPx = (STATE.projectDuration * STATE.masterZoom) + 220; // + 220 for sticky header
    document.getElementById('dawTimelineContent').style.width = totalPx + 'px';
    document.getElementById('rulerContent').style.width = totalPx + 'px';
    
    generateInfiniteGrid();
    drawRuler();
    
    // Update playhead
    document.getElementById('playhead').style.transform = `translateX(${(STATE.curTime * STATE.masterZoom) + 220}px)`;
    
    // Update Empty State visibility
    document.getElementById('dawEmpty').style.display = STATE.tracks.length ? 'none' : 'flex';
}

// Generate CSS repeating background for infinite perfect grid
function generateInfiniteGrid() {
    const content = document.getElementById('dawTimelineContent');
    if (!PROJECT.gridVisible) {
        content.style.backgroundImage = 'none';
        return;
    }

    const dpr = window.devicePixelRatio || 1;
    const ppBar = pxPerBar();
    const ppDiv = pxPerDiv();
    
    // Create a 1-bar wide canvas to act as the repeating tile
    const canvas = document.createElement('canvas');
    canvas.width = ppBar * dpr;
    canvas.height = 100 * dpr; // Arbitrary height, we will stretch it
    const ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);

    const op = PROJECT.gridOpacity / 100;
    const color = PROJECT.gridColor;
    
    function hexA(hex, alpha) {
        const r = parseInt(hex.slice(1,3),16);
        const g = parseInt(hex.slice(3,5),16);
        const b = parseInt(hex.slice(5,7),16);
        return `rgba(${r},${g},${b},${alpha})`;
    }

    const divsPerBar = PROJECT.sigNum * (PROJECT.gridDiv / PROJECT.sigDen);
    const divsPerBeat = PROJECT.gridDiv / PROJECT.sigDen;

    for (let d = 0; d < divsPerBar; d++) {
        const x = d * ppDiv;
        if (d === 0) {
            ctx.fillStyle = hexA(color, op);
            ctx.fillRect(x, 0, 1.5, 100);
        } else if (d % divsPerBeat === 0) {
            ctx.fillStyle = hexA(color, op * 0.55);
            ctx.fillRect(x, 0, 1, 100);
        } else if (ppDiv >= 5) {
            ctx.fillStyle = hexA(color, op * 0.3);
            ctx.fillRect(x, 0, 1, 100);
        }
    }

    const dataUrl = canvas.toDataURL();
    // Start drawing the grid exactly after the sticky headers (220px)
    content.style.backgroundImage = `url(${dataUrl})`;
    content.style.backgroundSize = `${ppBar}px 100px`;
    content.style.backgroundRepeat = 'repeat';
    content.style.backgroundPosition = `220px 0`; 
}

function drawRuler() {
    const canvas = document.getElementById('rulerCanvas');
    const wrap = document.getElementById('rulerWrap');
    if (!canvas || !wrap) return;

    const dpr = window.devicePixelRatio || 1;
    const W = (STATE.projectDuration * STATE.masterZoom);
    const H = 28;

    canvas.width = (W + 220) * dpr;
    canvas.height = H * dpr;
    canvas.style.width = (W + 220) + 'px';
    canvas.style.height = H + 'px';

    const ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);

    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    ctx.fillStyle = isDark ? '#14141f' : '#f0f0f8';
    ctx.fillRect(0, 0, W + 220, H);
    ctx.fillStyle = isDark ? '#252538' : '#c8c8de';
    ctx.fillRect(0, H-1, W + 220, 1);

    const ppbar = pxPerBar();
    const ppBeat = pxPerBeat();
    const barColor = isDark ? '#f59e0b' : '#b87200';
    const beatColor = isDark ? '#3a3a58' : '#b0b0d0';

    ctx.font = 'bold 9px "Space Mono", monospace';

    const totalBars = Math.ceil(W / ppbar);

    for (let bar = 0; bar <= totalBars; bar++) {
        // Offset by 220 for sticky header region
        const barX = 220 + (bar * ppbar); 

        // Bar Line
        ctx.fillStyle = isDark ? '#2c2c46' : '#b8b8d4';
        ctx.fillRect(barX, 0, 1, H);
        ctx.fillStyle = barColor;
        ctx.fillText(String(bar + 1), barX + 3, H - 5);

        // Beat Lines
        for (let beat = 1; beat < PROJECT.sigNum; beat++) {
            const bx = barX + (beat * ppBeat);
            ctx.fillStyle = beatColor;
            ctx.fillRect(bx, H * 0.35, 1, H * 0.65);
        }
    }
}

function dawSetZoom(val) {
    STATE.masterZoom = parseInt(val);
    updateMasterLayout();
    
    // Update all clips physically
    STATE.clips.forEach(c => {
        if (!c.el) return;
        c.el.style.left = ((c.startTime * STATE.masterZoom) + 220) + 'px';
        c.el.style.width = (c.duration * STATE.masterZoom) + 'px';
        if (c.ws) {
            c.ws.zoom(STATE.masterZoom);
        }
    });
}

// ═══════════════════════════════════════════════════════════════════════════
// TRACKS & CLIPS
// ═══════════════════════════════════════════════════════════════════════════

function addTrackLane(name = "Audio Track") {
    const id = ++STATE.trackIdSeq;
    const color = nextColor();
    const track = { id, name, color, vol: 1, muted: false };
    STATE.tracks.push(track);
    
    const row = document.createElement('div');
    row.className = 'daw-track';
    row.id = 'track-' + id;
    row.innerHTML = `
        <div class="track-head">
            <div class="track-head-top">
                <div class="track-color-strip" style="background:${color};"></div>
                <div class="track-name" title="${esc(name)}">${esc(name)}</div>
                <div class="track-btns">
                    <button class="tk-btn" id="mute-${id}" onclick="toggleMute(${id})"><i class="bi bi-volume-mute-fill"></i></button>
                    <button class="tk-btn tk-del" onclick="removeTrack(${id})"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="track-vol-wrap">
                <i class="bi bi-volume-up" style="color:var(--text-dim);font-size:10px;"></i>
                <input type="range" class="track-vol" min="0" max="1" step="0.01" value="1" oninput="setTrackVol(${id}, this.value)">
            </div>
        </div>
        <div class="track-lane" id="lane-${id}"></div>
    `;
    document.getElementById('dawTimelineContent').appendChild(row);
    updateMasterLayout();
    return id;
}

function addClip(trackId, url, name, startTime = 0) {
    const track = STATE.tracks.find(t => t.id === trackId);
    if (!track) return;

    const clipId = ++STATE.clipIdSeq;
    const clip = {
        id: clipId, trackId, url, name, color: track.color,
        startTime, duration: 1, // temporary duration until loaded
        ws: null, isPlaying: false, el: null
    };
    STATE.clips.push(clip);

    const lane = document.getElementById('lane-' + trackId);
    
    const el = document.createElement('div');
    el.className = 'daw-clip';
    el.id = 'clip-' + clipId;
    el.style.left = ((startTime * STATE.masterZoom) + 220) + 'px';
    el.style.width = '100px'; 
    el.innerHTML = `
        <div class="clip-header">
            <div style="flex:1;overflow:hidden;text-overflow:ellipsis;">${esc(name)}</div>
            <i class="bi bi-x clip-del" onclick="removeClip(${clipId}, event)" title="Remove Clip"></i>
        </div>
        <div class="clip-ws"></div>
    `;
    
    // Attach drag events to the clip
    el.addEventListener('mousedown', (e) => startClipDrag(e, clip));
    el.addEventListener('touchstart', (e) => startClipDrag(e, clip), {passive:false});

    lane.appendChild(el);
    clip.el = el;

    // Initialize WaveSurfer purely as a waveform visualizer
    const ws = WaveSurfer.create({
        container: el.querySelector('.clip-ws'),
        url: url,
        waveColor: clip.color,
        progressColor: clip.color + 'aa',
        height: 52,
        interact: false, // Disables native clicking/seeking inside the clip
        normalize: true,
        cursorWidth: 0,  // Hide cursor, we use master playhead
    });

    ws.on('ready', () => {
        clip.ws = ws;
        clip.duration = ws.getDuration();
        ws.setVolume(track.muted ? 0 : track.vol);
        
        el.style.width = (clip.duration * STATE.masterZoom) + 'px';
        ws.zoom(STATE.masterZoom);
        updateMasterLayout();
    });
}

function addTrackFromAsset(assetIdx) {
    const a = STATE.assetData[assetIdx];
    if (!a?.filename) return;
    stopPreview();
    
    const trackId = addTrackLane(a.name);
    addClip(trackId, a.filename, a.name, 0);
    Toast.show('Added: ' + trunc(a.name, 24), 'success');
}

function replaceClipAudio(clipId, assetIdx) {
    const a = STATE.assetData[assetIdx];
    if (!a?.filename) return;
    const clip = STATE.clips.find(c => c.id === clipId);
    if (!clip || !clip.el) return;
    
    stopPreview();
    clip.name = a.name;
    clip.url  = a.filename;
    
    clip.el.querySelector('.clip-header div').textContent = a.name;
    
    if (clip.ws) {
        clip.ws.destroy();
        clip.ws = null;
    }
    
    const ws = WaveSurfer.create({
        container: clip.el.querySelector('.clip-ws'),
        url: clip.url,
        waveColor: clip.color,
        progressColor: clip.color + 'aa',
        height: 52,
        interact: false,
        normalize: true,
        cursorWidth: 0,
    });
    
    ws.on('ready', () => {
        clip.ws = ws;
        clip.duration = ws.getDuration();
        const track = STATE.tracks.find(t => t.id === clip.trackId);
        ws.setVolume(track && track.muted ? 0 : (track ? track.vol : 1));
        
        clip.el.style.width = (clip.duration * STATE.masterZoom) + 'px';
        ws.zoom(STATE.masterZoom);
        updateMasterLayout();
    });
    
    Toast.show('Replaced with: ' + trunc(a.name, 24), 'success');
}

function removeTrack(id) {
    [...STATE.clips].filter(c => c.trackId === id).forEach(c => removeClip(c.id));
    const idx = STATE.tracks.findIndex(t => t.id === id);
    if (idx > -1) STATE.tracks.splice(idx, 1);
    
    const el = document.getElementById('track-' + id);
    if (el) el.remove();
    updateMasterLayout();
}

function removeClip(id, event = null) {
    if (event) { event.stopPropagation(); event.preventDefault(); }
    const idx = STATE.clips.findIndex(c => c.id === id);
    if (idx === -1) return;
    
    const clip = STATE.clips[idx];
    if (clip.ws) { clip.ws.pause(); clip.ws.destroy(); }
    if (clip.el) clip.el.remove();
    
    STATE.clips.splice(idx, 1);
    updateMasterLayout();
}

function toggleMute(id) {
    const t = STATE.tracks.find(t => t.id === id);
    if (!t) return;
    t.muted = !t.muted;
    STATE.clips.filter(c => c.trackId === id).forEach(c => {
        if (c.ws) c.ws.setVolume(t.muted ? 0 : t.vol);
    });
    const btn = document.getElementById('mute-' + id);
    if (btn) btn.classList.toggle('muted', t.muted);
}

function setTrackVol(id, val) {
    const t = STATE.tracks.find(t => t.id === id);
    if (!t) return;
    t.vol = parseFloat(val);
    if (!t.muted) {
        STATE.clips.filter(c => c.trackId === id).forEach(c => {
            if (c.ws) c.ws.setVolume(t.vol);
        });
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// CLIP DRAG & DROP LOGIC
// ═══════════════════════════════════════════════════════════════════════════
let clipDrag = { active: false, clip: null, startX: 0, startY: 0, initLeft: 0, initTrackId: null };

function startClipDrag(e, clip) {
    if (e.target.classList.contains('clip-del')) return;
    e.preventDefault(); e.stopPropagation();
    
    clipDrag.active = true;
    clipDrag.clip = clip;
    clipDrag.initLeft = (clip.startTime * STATE.masterZoom) + 220;
    clipDrag.initTrackId = clip.trackId;
    
    const pt = e.touches ? e.touches[0] : e;
    clipDrag.startX = pt.clientX;
    clipDrag.startY = pt.clientY;
    
    clip.el.classList.add('dragging');
}

function moveClipDrag(e) {
    if (!clipDrag.active || !clipDrag.clip) return;
    
    const pt = e.touches ? e.touches[0] : e;
    const dx = pt.clientX - clipDrag.startX;
    
    let newLeft = Math.max(220, clipDrag.initLeft + dx);
    let newTime = (newLeft - 220) / STATE.masterZoom;
    
    if (PROJECT.snapEnabled) {
        const snapSecs = (60 / PROJECT.bpm) / (PROJECT.gridDiv / PROJECT.sigDen);
        newTime = Math.round(newTime / snapSecs) * snapSecs;
        newLeft = (newTime * STATE.masterZoom) + 220;
    }
    
    clipDrag.clip.el.style.left = newLeft + 'px';
    clipDrag.clip.startTime = newTime;
    
    clipDrag.clip.el.style.display = 'none';
    const target = document.elementFromPoint(pt.clientX, pt.clientY);
    clipDrag.clip.el.style.display = 'flex';
    
    if (target) {
        const lane = target.closest('.track-lane');
        if (lane) {
            const targetTrackId = parseInt(lane.id.replace('lane-', ''));
            if (targetTrackId !== clipDrag.clip.trackId) {
                clipDrag.clip.trackId = targetTrackId;
                lane.appendChild(clipDrag.clip.el);
            }
        }
    }
}

function endClipDrag(e) {
    if (!clipDrag.active) return;
    clipDrag.clip.el.classList.remove('dragging');
    clipDrag.active = false;
    updateMasterLayout();
}

document.addEventListener('mousemove', moveClipDrag);
document.addEventListener('mouseup', endClipDrag);
document.addEventListener('touchmove', moveClipDrag, {passive:false});
document.addEventListener('touchend', endClipDrag);

// ═══════════════════════════════════════════════════════════════════════════
// BIN TO TIMELINE DRAG & DROP
// ═══════════════════════════════════════════════════════════════════════════
let binDrag = { active: false, clone: null, assetIdx: null, offsetX: 0, offsetY: 0 };

function startBinDrag(e) {
    const handle = e.target.closest('.ba-drag-handle');
    if (!handle) return;
    e.preventDefault();
    
    const item = handle.closest('.bin-asset-item');
    binDrag.assetIdx = handle.getAttribute('data-drag-idx');
    binDrag.active = true;
    
    binDrag.clone = item.cloneNode(true);
    binDrag.clone.style.position = 'fixed';
    binDrag.clone.style.zIndex = '9999';
    binDrag.clone.style.background = 'var(--surface)';
    binDrag.clone.style.border = '1px solid var(--amber)';
    binDrag.clone.style.width = item.offsetWidth + 'px';
    binDrag.clone.style.opacity = '0.9';
    binDrag.clone.style.pointerEvents = 'none';
    
    const rect = item.getBoundingClientRect();
    const pt = e.touches ? e.touches[0] : e;
    binDrag.offsetX = pt.clientX - rect.left;
    binDrag.offsetY = pt.clientY - rect.top;
    
    binDrag.clone.style.left = (pt.clientX - binDrag.offsetX) + 'px';
    binDrag.clone.style.top = (pt.clientY - binDrag.offsetY) + 'px';
    
    document.body.appendChild(binDrag.clone);
    closeBin();
}

function moveBinDrag(e) {
    if (!binDrag.active || !binDrag.clone) return;
    e.preventDefault();
    const pt = e.touches ? e.touches[0] : e;
    binDrag.clone.style.left = (pt.clientX - binDrag.offsetX) + 'px';
    binDrag.clone.style.top = (pt.clientY - binDrag.offsetY) + 'px';
    
    document.querySelectorAll('.track-lane').forEach(l => l.classList.remove('drag-over'));
    document.querySelectorAll('.daw-clip').forEach(c => c.classList.remove('drag-over'));
    
    binDrag.clone.style.display = 'none';
    const target = document.elementFromPoint(pt.clientX, pt.clientY);
    binDrag.clone.style.display = 'flex';
    
    if (target) {
        const clip = target.closest('.daw-clip');
        const lane = target.closest('.track-lane');
        if (clip) clip.classList.add('drag-over');
        else if (lane) lane.classList.add('drag-over');
    }
}

function endBinDrag(e) {
    if (!binDrag.active) return;
    binDrag.active = false;
    
    const pt = e.changedTouches ? e.changedTouches[0] : e;
    binDrag.clone.style.display = 'none';
    const target = document.elementFromPoint(pt.clientX, pt.clientY);
    
    if (binDrag.clone.parentNode) binDrag.clone.parentNode.removeChild(binDrag.clone);
    binDrag.clone = null;
    
    document.querySelectorAll('.track-lane').forEach(l => l.classList.remove('drag-over'));
    document.querySelectorAll('.daw-clip').forEach(c => c.classList.remove('drag-over'));
    
    if (target) {
        const clipEl = target.closest('.daw-clip');
        const laneEl = target.closest('.track-lane');
        const content = target.closest('.daw-timeline-scroll');
        
        if (clipEl) {
            // Replace Audio on an existing clip
            const clipId = parseInt(clipEl.id.replace('clip-', ''));
            replaceClipAudio(clipId, binDrag.assetIdx);
        } else if (laneEl || content) {
            // Add new clip to a lane (or create new lane)
            const asset = STATE.assetData[binDrag.assetIdx];
            if (!asset) return;
            
            const contentRect = document.getElementById('dawTimelineContent').getBoundingClientRect();
            let localX = pt.clientX - contentRect.left - 220;
            if (localX < 0) localX = 0;
            
            let dropTime = localX / STATE.masterZoom;
            if (PROJECT.snapEnabled) {
                const snapSecs = (60 / PROJECT.bpm) / (PROJECT.gridDiv / PROJECT.sigDen);
                dropTime = Math.round(dropTime / snapSecs) * snapSecs;
            }
            
            let trackId;
            if (laneEl) {
                trackId = parseInt(laneEl.id.replace('lane-', ''));
            } else {
                trackId = addTrackLane(asset.name);
            }
            addClip(trackId, asset.filename, asset.name, dropTime);
        } else {
            openBin();
        }
    } else {
        openBin();
    }
}

document.addEventListener('touchstart', startBinDrag, {passive: false});
document.addEventListener('mousedown', startBinDrag);
document.addEventListener('touchmove', moveBinDrag, {passive: false});
document.addEventListener('mousemove', moveBinDrag);
document.addEventListener('touchend', endBinDrag);
document.addEventListener('mouseup', endBinDrag);


// ═══════════════════════════════════════════════════════════════════════════
// MASTER PLAYBACK ENGINE
// ═══════════════════════════════════════════════════════════════════════════

function dawPlayPause() { STATE.isPlaying ? dawPause() : (stopPreview(), dawPlay()); }

function dawPlay() {
    if (STATE.isPlaying) return;
    STATE.isPlaying = true;
    STATE.lastFrameTime = performance.now();
    updateTransportUI();
    STATE.rafId = requestAnimationFrame(playLoop);
}

function dawPause() {
    STATE.isPlaying = false;
    cancelAnimationFrame(STATE.rafId);
    updateTransportUI();
    
    // Pause all running clips
    STATE.clips.forEach(c => {
        if (c.isPlaying && c.ws) {
            c.ws.pause();
            c.isPlaying = false;
        }
    });
}

function dawStop() {
    dawPause();
    seekTimeline(0);
}

function dawRewind() {
    const was = STATE.isPlaying;
    dawStop();
    if (was) setTimeout(dawPlay, 50);
}

function seekTimeline(timeSecs) {
    STATE.curTime = Math.max(0, timeSecs);
    document.getElementById('playhead').style.transform = `translateX(${(STATE.curTime * STATE.masterZoom) + 220}px)`;
    document.getElementById('tpTime').textContent = fmt(STATE.curTime);
    
    // Force sync playing clips if currently active
    STATE.clips.forEach(c => {
        if (c.isPlaying && c.ws) {
            c.ws.pause();
            c.isPlaying = false;
        }
    });
}

function playLoop(now) {
    if (!STATE.isPlaying) return;
    
    const dt = (now - STATE.lastFrameTime) / 1000;
    STATE.lastFrameTime = now;
    STATE.curTime += dt;
    
    if (STATE.curTime >= STATE.projectDuration) {
        dawStop();
        return;
    }
    
    document.getElementById('playhead').style.transform = `translateX(${(STATE.curTime * STATE.masterZoom) + 220}px)`;
    document.getElementById('tpTime').textContent = fmt(STATE.curTime);
    
    // Trigger / Stop clips
    STATE.clips.forEach(c => {
        if (!c.ws || !c.duration) return;
        
        const end = c.startTime + c.duration;
        const shouldPlay = STATE.curTime >= c.startTime && STATE.curTime < end;
        
        if (shouldPlay && !c.isPlaying) {
            c.isPlaying = true;
            c.ws.play();
            c.ws.seekTo((STATE.curTime - c.startTime) / c.duration);
        } else if (!shouldPlay && c.isPlaying) {
            c.isPlaying = false;
            c.ws.pause();
        }
    });
    
    STATE.rafId = requestAnimationFrame(playLoop);
}

function updateTransportUI() {
    const btn = document.getElementById('btnPP');
    const icon = document.getElementById('ppIcon');
    if (btn) btn.classList.toggle('playing', STATE.isPlaying);
    if (icon) icon.className = STATE.isPlaying ? 'bi bi-pause-fill' : 'bi bi-play-fill';
}

function dawClearAll() {
    if (!STATE.tracks.length) return;
    if (!confirm('Remove all tracks and clips?')) return;
    
    [...STATE.clips].forEach(c => removeClip(c.id));
    [...STATE.tracks].forEach(t => removeTrack(t.id));
    STATE.trackIdSeq = 0; 
    STATE.clipIdSeq = 0;
    STATE.curTime = 0;
    seekTimeline(0);
}

// Keyboard Transport
document.addEventListener('keydown', e => {
    if (e.target.tagName === 'INPUT'||e.target.tagName==='TEXTAREA'||e.target.tagName==='SELECT') return;
    if (e.key === ' ') { e.preventDefault(); dawPlayPause(); }
    if (e.key === 'Escape') { closeParamModal(); if (document.getElementById('binPanel').classList.contains('open')) closeBin(); }
    if (e.key === 'Home') { e.preventDefault(); dawStop(); }
});

// ═══════════════════════════════════════════════════════════════════════════
// ENTITY LIST & ASSET BIN (FULLY RESTORED v2)
// ═══════════════════════════════════════════════════════════════════════════

function onEntityTypeChange(entity) {
    STATE.entity = entity;
    STATE.entityId = null;
    STATE.page = 1;
    clearAssets();
    loadEntities(1);
}

function debouncedEntitySearch() {
    clearTimeout(STATE.entityDebounce);
    STATE.entityDebounce = setTimeout(() => loadEntities(1), 300);
}

function changePage(d) {
    const n = STATE.page + d;
    if (n >= 1 && n <= STATE.totalPages) loadEntities(n);
}

function loadEntities(page) {
    STATE.page = page;
    const search = (document.getElementById('binSearch')?.value || '').trim();
    const offset = (page - 1) * STATE.pageSize;
    const list = document.getElementById('binEntityList');
    list.innerHTML = '<div class="bin-state"><div class="spin-s"></div> Loading…</div>';

    api('get_entities', { limit: STATE.pageSize, offset, search })
        .then(res => {
            if (res.status !== 'success') { list.innerHTML = '<div class="bin-state">Error loading entities</div>'; return; }
            STATE.totalPages = Math.ceil(res.total / STATE.pageSize) || 1;
            renderEntityList(res.data);
            updatePagination();
        })
        .catch(() => list.innerHTML = '<div class="bin-state">Network error</div>');
}

function renderEntityList(rows) {
    const list = document.getElementById('binEntityList');
    if (!rows.length) { list.innerHTML = '<div class="bin-state">No entities found</div>'; return; }
    list.innerHTML = rows.map(row => {
        const active = row.id == STATE.entityId ? 'active' : '';
        const date = (row.created_at || '').substring(0, 10);
        return `<div class="bin-entity-item ${active}" onclick="selectEntity(${row.id}, this)">
            <span class="bi-eid">#${row.id}</span>
            <span class="bi-name">${esc(row.name || 'Unnamed')}</span>
            <span class="bi-date">${esc(date)}</span>
        </div>`;
    }).join('');
}

function updatePagination() {
    const el = document.getElementById('binPagination');
    el.style.display = STATE.totalPages > 1 ? 'flex' : 'none';
    document.getElementById('pgCur').textContent = STATE.page;
    document.getElementById('pgOf').textContent = '/ ' + STATE.totalPages;
    document.getElementById('pgPrev').disabled = STATE.page <= 1;
    document.getElementById('pgNext').disabled = STATE.page >= STATE.totalPages;
}

function selectEntity(id, el) {
    document.querySelectorAll('.bin-entity-item').forEach(e => e.classList.remove('active'));
    el.classList.add('active');
    STATE.entityId = id;
    loadAssetsForEntity(id);
}

function createNewEntity() {
    api('add_entity', {}, 'POST').then(res => {
        if (res.status === 'success') { Toast.show('Created entity #' + res.id, 'success'); loadEntities(1); }
        else Toast.show(res.message || 'Error', 'error');
    });
}

function clearAssets() {
    STATE.assetData = [];
    STATE.previewIdx = -1;
    stopPreview();
    document.getElementById('binAssetList').innerHTML = '<div class="bin-state" style="color:var(--text-faint);">↑ Select an entity above</div>';
    document.getElementById('binAssetsCount').textContent = '–';
}

function debouncedAssetSearch() {
    clearTimeout(STATE.assetDebounce);
    STATE.assetDebounce = setTimeout(() => { if (STATE.entityId) loadAssetsForEntity(STATE.entityId); }, 250);
}

function loadAssetsForEntity(entityId) {
    STATE.entityId = entityId;
    const search = (document.getElementById('binAssetSearch')?.value || '').trim();
    const list = document.getElementById('binAssetList');
    list.innerHTML = '<div class="bin-state"><div class="spin-s"></div> Loading audios…</div>';
    
    api('get_playlist', { entity_id: entityId, search })
        .then(res => {
            if (res.status !== 'success') { list.innerHTML = '<div class="bin-state">Error loading audios</div>'; return; }
            STATE.assetData = res.data.map(r => ({
                id: r.audio_id || r.id,
                name: r.name || ('Audio #'+(r.audio_id||r.id)),
                filename: r.filename || '',
            }));
            document.getElementById('binAssetsCount').textContent = STATE.assetData.length + ' audio' + (STATE.assetData.length!==1?'s':'');
            renderAssets();
        })
        .catch(() => list.innerHTML = '<div class="bin-state">Network error</div>');
}

function renderAssets() {
    const list = document.getElementById('binAssetList');
    if (!STATE.assetData.length) { list.innerHTML = '<div class="bin-state">No audio assets for this entity</div>'; return; }
    list.innerHTML = STATE.assetData.map((a, idx) => {
        const isPrev = idx === STATE.previewIdx;
        return `<div class="bin-asset-item" data-idx="${idx}">
            <div class="ba-drag-handle" data-drag-idx="${idx}" title="Drag to Timeline">
                <i class="bi bi-grip-vertical"></i>
            </div>
            <button class="ba-play ${isPrev?'previewing':''}" onclick="togglePreview(${idx})" title="Preview">
                <i class="bi ${isPrev?'bi-stop-fill':'bi-play-fill'}"></i>
            </button>
            <div class="ba-info">
                <div class="ba-name">${esc(a.name)}</div>
                <div class="ba-file" title="${esc(a.filename)}">${esc(trunc(a.filename.split('/').pop(),36))}</div>
            </div>
            <button class="ba-add" onclick="addTrackFromAsset(${idx})" title="Add to Timeline">
                <i class="bi bi-plus-circle-fill"></i>
            </button>
        </div>`;
    }).join('');
}

// ── Preview Engine ──
function stopPreview() {
    if (STATE.previewHowl) { STATE.previewHowl.stop(); STATE.previewHowl.unload(); STATE.previewHowl = null; }
    STATE.previewIdx = -1;
    updateAssetPlayUI();
}

function togglePreview(idx) {
    if (STATE.previewIdx === idx) { stopPreview(); return; }
    stopPreview();
    if (STATE.isPlaying) dawStop();
    const a = STATE.assetData[idx];
    if (!a?.filename) return;
    STATE.previewIdx = idx;
    STATE.previewHowl = new Howl({
        src: [a.filename], html5: true,
        onend: stopPreview,
        onloaderror: () => { Toast.show('Cannot load preview', 'error'); stopPreview(); }
    });
    STATE.previewHowl.play();
    updateAssetPlayUI();
}

function updateAssetPlayUI() {
    document.querySelectorAll('.bin-asset-item').forEach((el, i) => {
        const btn = el.querySelector('.ba-play');
        const ico = btn?.querySelector('i');
        if (!btn || !ico) return;
        const p = i === STATE.previewIdx;
        btn.classList.toggle('previewing', p);
        ico.className = p ? 'bi bi-stop-fill' : 'bi bi-play-fill';
    });
}

function openBin() {
    document.getElementById('binPanel').classList.add('open');
    document.getElementById('binOverlay').classList.add('open');
    document.getElementById('btnBin').classList.add('active');
}

function closeBin() {
    document.getElementById('binPanel').classList.remove('open');
    document.getElementById('binOverlay').classList.remove('open');
    document.getElementById('btnBin').classList.remove('active');
}

// ═══════════════════════════════════════════════════════════════════════════
// PROJECT PARAM MODAL (FULLY RESTORED)
// ═══════════════════════════════════════════════════════════════════════════

function buildSwatches() {
    const row = document.getElementById('pmSwatchRow');
    if (!row) return;
    GRID_COLORS.forEach((c, i) => {
        const s = document.createElement('div');
        s.className = 'pm-swatch' + (i === selectedSwatchIdx ? ' active' : '');
        s.style.background = c;
        s.title = c;
        s.onclick = () => selectSwatch(i);
        row.appendChild(s);
    });
}

function selectSwatch(i) {
    selectedSwatchIdx = i;
    document.querySelectorAll('.pm-swatch').forEach((s,j) => s.classList.toggle('active', i===j));
}

function openParamModal(context) {
    document.getElementById('pmBpm').value = PROJECT.bpm;
    document.getElementById('pmSigNum').value = PROJECT.sigNum;
    document.getElementById('pmSigDen').value = String(PROJECT.sigDen);
    document.getElementById('pmGridDiv').value = String(PROJECT.gridDiv);
    document.getElementById('pmGridVisible').checked = PROJECT.gridVisible;
    document.getElementById('pmSnapEnabled').checked = PROJECT.snapEnabled;
    document.getElementById('pmGridOpacity').value = PROJECT.gridOpacity;
    document.getElementById('pmOpacityVal').textContent = PROJECT.gridOpacity + '%';

    selectedSwatchIdx = GRID_COLORS.indexOf(PROJECT.gridColor);
    if (selectedSwatchIdx < 0) selectedSwatchIdx = 0;
    document.querySelectorAll('.pm-swatch').forEach((s,j) => s.classList.toggle('active', j===selectedSwatchIdx));

    document.getElementById('paramBackdrop').classList.add('open');
    document.getElementById('mbBtnProject').classList.add('active');
}

function closeParamModal() {
    document.getElementById('paramBackdrop').classList.remove('open');
    document.getElementById('mbBtnProject').classList.remove('active');
}

function onBackdropClick(e) {
    if (e.target === document.getElementById('paramBackdrop')) closeParamModal();
}

function spinBpm(d)    { const el=document.getElementById('pmBpm');    el.value=Math.max(20,Math.min(300,parseInt(el.value||120)+d)); }
function spinSigNum(d) { const el=document.getElementById('pmSigNum'); el.value=Math.max(1, Math.min(16, parseInt(el.value||4)+d)); }
function clampBpm()    { const el=document.getElementById('pmBpm');    el.value=Math.max(20,Math.min(300,parseInt(el.value)||120)); }
function clampSig()    { const el=document.getElementById('pmSigNum'); el.value=Math.max(1, Math.min(16, parseInt(el.value)||4)); }

function applyProjectSettings() {
    PROJECT.bpm         = parseInt(document.getElementById('pmBpm').value)    || 120;
    PROJECT.sigNum      = parseInt(document.getElementById('pmSigNum').value) || 4;
    PROJECT.sigDen      = parseInt(document.getElementById('pmSigDen').value) || 4;
    PROJECT.gridDiv     = parseInt(document.getElementById('pmGridDiv').value)|| 16;
    PROJECT.gridVisible = document.getElementById('pmGridVisible').checked;
    PROJECT.snapEnabled = document.getElementById('pmSnapEnabled').checked;
    PROJECT.gridColor   = GRID_COLORS[selectedSwatchIdx] || '#f59e0b';
    PROJECT.gridOpacity = parseInt(document.getElementById('pmGridOpacity').value) || 15;

    closeParamModal();
    syncMenuBar();
    updateMasterLayout();
    Toast.show('Project settings applied', 'success');
}

function syncMenuBar() {
    document.getElementById('mbBpm').textContent  = PROJECT.bpm;
    document.getElementById('mbSig').textContent  = PROJECT.sigNum + '/' + PROJECT.sigDen;
    document.getElementById('mbGrid').textContent = '1/' + PROJECT.gridDiv;

    const snapBadge = document.getElementById('snapBadge');
    snapBadge.textContent = PROJECT.snapEnabled ? 'ON' : 'OFF';
    snapBadge.classList.toggle('off', !PROJECT.snapEnabled);

    const gridBadge = document.getElementById('gridBadge');
    gridBadge.textContent = PROJECT.gridVisible ? 'ON' : 'OFF';
    gridBadge.classList.toggle('off', !PROJECT.gridVisible);
}

function toggleGridSnap() {
    PROJECT.snapEnabled = !PROJECT.snapEnabled;
    syncMenuBar();
    Toast.show('Snap ' + (PROJECT.snapEnabled ? 'ON' : 'OFF'), 'info');
}

function toggleGridVisible() {
    PROJECT.gridVisible = !PROJECT.gridVisible;
    syncMenuBar();
    updateMasterLayout();
}
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>