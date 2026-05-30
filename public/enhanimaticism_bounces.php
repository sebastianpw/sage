<?php
// public/enhanimaticism_bounces.php
// Enhanimaticism (DAW Bounces mode) — Unified Audio Tool (Entity-specific)
// Mirrors enhanimaticism_audio.php architecture but exclusive for DAW Bounces:
//   - daw_projects
//   - editorial_shots
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once __DIR__ . '/entity_icons.php'; // provides $entityIcons
require_once __DIR__ . '/VoicePool.php';

// DAW Bounces entities whitelist — only entities that have a DAW mixdown
$audioEntities = [
    'daw_projects',
    'editorial_shots',
];

// Selected entity from request or default
$selectedEntity = $_REQUEST['entity'] ?? 'daw_projects';
if (!in_array($selectedEntity, $audioEntities, true)) {
    $selectedEntity = 'daw_projects';
}
$entityType = $selectedEntity;

// Deep-link params
if (isset($_GET['entity_type']) && in_array($_GET['entity_type'], $audioEntities, true)) {
    $selectedEntity = $_GET['entity_type'];
    $entityType     = $selectedEntity;
}
$deepLinkEntityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;
$deepLinkSearch   = isset($_GET['search'])    ? $_GET['search']          : '';

// ═══════════════════════════════════════════════════════
// API HANDLER
// ═══════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['api_action'];

    $reqEntity = $_REQUEST['entity'] ?? $entityType;
    if (!in_array($reqEntity, $audioEntities, true)) {
        $reqEntity = $entityType;
    }

    try {

        // ── 1. GET ENTITIES ──────────────────────────────────
        if ($action === 'get_entities') {
            $limit  = (int)($_GET['limit'] ?? 4);
            $offset = (int)($_GET['offset'] ?? 0);
            $search = $_GET['search'] ?? '';

            $table = "`" . str_replace('`', '', $reqEntity) . "`";
            $where = "1=1";
            if ($search) {
                $safeSearch = $pdo->quote("%$search%");
                $safeId     = intval($search);
                $where     .= " AND (name LIKE $safeSearch OR id = $safeId)";
            }
            $total = $pdo->query("SELECT COUNT(*) FROM $table WHERE $where")->fetchColumn();
            $sql   = "SELECT * FROM $table WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset";
            $rows  = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'success', 'data' => $rows, 'total' => $total]);
            exit;
        }

        // ── 2. CREATE ENTITY ──────────────────────────────────
        if ($action === 'add_entity') {
            $table = "`" . $reqEntity . "`";
            $stmt  = $pdo->query("SHOW COLUMNS FROM $table");
            $cols  = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $uniqueName  = "New " . ucfirst(str_replace(['audio_','_'], ['',' '], $reqEntity)) . " " . time();
            $insertCols  = ['name'];
            $insertVals  = ['?'];
            $params      = [$uniqueName];

            if (in_array('order', $cols)) { $insertCols[] = '`order`'; $insertVals[] = '0'; }
            if (in_array('audio_voice_identity_id', $cols)) {
                $defVoice = $pdo->query("SELECT id FROM audio_voice_identity ORDER BY id ASC LIMIT 1")->fetchColumn();
                if ($defVoice) { $insertCols[] = 'audio_voice_identity_id'; $insertVals[] = intval($defVoice); $params = [$uniqueName]; }
            }

            $sql = "INSERT INTO $table (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $insertVals) . ")";
            $pdo->prepare($sql)->execute($params);
            $newId = $pdo->lastInsertId();
            echo json_encode(['status' => 'success', 'id' => $newId]);
            exit;
        }

        // ── 3. DELETE ENTITY ──────────────────────────────────
        if ($action === 'delete_entity') {
            $id = (int)($_POST['entity_id'] ?? 0);
            if ($id > 0) {
                $table = "`" . $reqEntity . "`";
                $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
            }
            echo json_encode(['status' => 'success']);
            exit;
        }

        // ── 4. COPY ENTITY ────────────────────────────────────
        if ($action === 'copy_entity') {
            $id    = (int)($_POST['entity_id'] ?? 0);
            $table = "`" . $reqEntity . "`";
            $stmt  = $pdo->query("SHOW COLUMNS FROM $table");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $colsList = implode(", ", array_map(fn($c) => "`$c`", array_filter($columns, fn($c) => $c !== 'id')));
            $stmt = $pdo->prepare("SELECT $colsList FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if (isset($row['name'])) $row['name'] .= ' (Copy)';
                $placeholders = implode(", ", array_fill(0, count($row), '?'));
                $pdo->prepare("INSERT INTO $table ($colsList) VALUES ($placeholders)")->execute(array_values($row));
                echo json_encode(['status' => 'success', 'id' => $pdo->lastInsertId()]);
                exit;
            }
            echo json_encode(['status' => 'error', 'message' => 'Entity not found']);
            exit;
        }

        // ── 5. TOGGLE REGENERATE ──────────────────────────────
        if ($action === 'toggle_regenerate') {
            $id    = (int)($_POST['entity_id'] ?? 0);
            $val   = (int)($_POST['value']     ?? 0);
            $col   = $_POST['column']           ?? '';
            $table = "`" . str_replace('`', '', $reqEntity) . "`";
            $stmt  = $pdo->query("SHOW COLUMNS FROM $table");
            $validCols = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($id > 0 && in_array($col, $validCols)) {
                $pdo->prepare("UPDATE $table SET `$col` = ? WHERE id = ?")->execute([$val, $id]);
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
            }
            exit;
        }

        // ── 6. UPDATE FIELD ───────────────────────────────────
        if ($action === 'update_field') {
            $id    = (int)($_POST['entity_id'] ?? 0);
            $field = $_POST['field']            ?? '';
            $value = $_POST['value']            ?? '';
            $table = "`" . str_replace('`', '', $reqEntity) . "`";
            $stmt  = $pdo->query("SHOW COLUMNS FROM $table");
            $validCols = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($id > 0 && in_array($field, $validCols)) {
                $pdo->prepare("UPDATE $table SET `$field` = ? WHERE id = ?")->execute([$value, $id]);
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid field']);
            }
            exit;
        }

        // ── 7. SYNC VOICE MODELS ──────────────────────────────
        if ($action === 'sync_models') {
            $vp    = new VoicePool();
            $stats = $vp->syncFromApiToDb($pdo);
            echo json_encode(['status' => 'success', 'message' => "Synced! Added: {$stats['added']}, Updated: {$stats['updated']}"]);
            exit;
        }

        // ── 8. GET PLAYLIST (audios for entity) ───────────────
        if ($action === 'get_playlist') {
            $entityId = (int)($_GET['entity_id'] ?? 0);
            $search   = $_GET['search']           ?? '';
            if (!$entityId) { echo json_encode(['status' => 'success', 'data' => []]); exit; }

            $viewName = "v_player_" . $reqEntity;

            // Check if view exists; fall back to raw audios join
            $viewExists = false;
            try {
                $pdo->query("SELECT 1 FROM `$viewName` LIMIT 1");
                $viewExists = true;
            } catch (Exception $e) { $viewExists = false; }

            if ($viewExists) {
                $where  = "entity_id = " . intval($entityId);
                if ($search) {
                    $s      = $pdo->quote("%" . $search . "%");
                    $where .= " AND (name LIKE $s OR audio_name LIKE $s OR model LIKE $s OR filename LIKE $s)";
                }
                $sql  = "SELECT * FROM `$viewName` WHERE $where ORDER BY created_at DESC LIMIT 200";
                $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Fallback: direct audios table (updated to select `name` for Bounce Mixdown titles)
                $where  = "entity_type = " . $pdo->quote($reqEntity) . " AND entity_id = " . intval($entityId);
                if ($search) {
                    $s      = $pdo->quote("%" . $search . "%");
                    $where .= " AND (filename LIKE $s OR name LIKE $s)";
                }
                $sql  = "SELECT id as audio_id, name, name as audio_name, filename, created_at, entity_id FROM audios WHERE $where ORDER BY id DESC LIMIT 200";
                $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode(['status' => 'success', 'data' => $rows]);
            exit;
        }

        // ── 9. GET VOICE OPTIONS ──────────────────────────────
        if ($action === 'get_voice_options') {
            $rows = $pdo->query("SELECT id, name FROM audio_voice_identity ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $rows]);
            exit;
        }

        // ── 10. REGENERATE AUDIO ─────────────────────────────
        if ($action === 'regenerate_audio') {
            $id    = (int)($_POST['entity_id'] ?? 0);
            $table = "`" . str_replace('`', '', $reqEntity) . "`";
            $stmt  = $pdo->query("SHOW COLUMNS FROM $table");
            $cols  = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $regenCol = in_array('regenerate_audios', $cols) ? 'regenerate_audios' : (in_array('regenerate', $cols) ? 'regenerate' : null);
            if ($id > 0 && $regenCol) {
                $pdo->prepare("UPDATE $table SET `$regenCol` = 1 WHERE id = ?")->execute([$id]);
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No regen column found']);
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

// ─── Page render ───────────────────────────────────────
$pageTitle = 'Enhanimaticism — DAW Bounces';
ob_start();
?>
<!-- Dependencies -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<!-- Howler.js -->
<script src="https://cdn.jsdelivr.net/npm/howler@2.2.4/dist/howler.min.js"></script>

<?php require_once __DIR__ . '/modal_frame_details.php'; ?>
<?php require_once __DIR__ . '/modal_audio_details.php'; ?>

<style>
:root {
    --bg:          #0a0a0f;
    --card:        #111118;
    --border:      #1e1e2e;
    --text:        #e2e2f0;
    --text-muted:  #555570;
    --purple:      #8b5cf6;
    --purple-dim:  rgba(139, 92, 246, 0.1);
    --amber:       #f59e0b;
    --amber-dim:   rgba(245, 158, 11, 0.1);
    --red:         #ef4444;
    --teal:        #14b8a6;
    --teal-dim:    rgba(20, 184, 166, 0.12);
    --green:       #22c55e;
    --green-dim:   rgba(34, 197, 94, 0.12);
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

/* ── LAYOUT ── */
.eha-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }

/* ── HEADER ── */
.eha-header {
    flex-shrink: 0; padding: 0 16px; height: 50px;
    background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}
.eha-title { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--text); display: flex; align-items: center; gap: 8px; }
.eha-title span { color: var(--teal); }
.eha-nav { display: flex; height: 100%; gap: 12px; align-items: center; }

/* ── ENTITY LIST PANEL ── */
.mr-controls-row { display: flex; gap: 8px; padding: 8px 12px; border-bottom: 1px solid var(--border); align-items: center; background: var(--card); }
.mr-search-input { flex: 1; min-width: 0; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.8rem; }
.mr-search-input:focus { outline: none; border-color: var(--teal); }
.mr-pagination { display: flex; align-items: center; gap: 4px; }
.pg-btn { width: 26px; height: 26px; background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 3px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
.pg-btn:hover:not(:disabled) { border-color: var(--teal); color: var(--teal); }
.pg-input { width: 40px; text-align: center; background: var(--bg); border: 1px solid var(--border); color: var(--teal); border-radius: 3px; font-family: inherit; font-size: 0.75rem; font-weight: 700; padding: 4px 0; -moz-appearance: textfield; }
.pg-total { font-size: 0.7rem; color: var(--text-muted); padding: 0 4px; }

.mr-list-scroll { overflow-y: auto; overflow-x: hidden; min-height: 60px; }
.mr-item { padding: 8px 12px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.15s; display: flex; align-items: center; gap: 10px; }
.mr-item:hover { background: rgba(255,255,255,0.05); }
.mr-item.active { background: var(--teal-dim); border-left: 3px solid var(--teal); padding-left: 9px; }
.mr-id   { font-size: 0.7rem; font-weight: 700; color: var(--teal); min-width: 40px; }
.mr-note { font-size: 0.75rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; min-width: 0; }
.mr-meta { font-size: 0.65rem; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; }

.mr-regen-wrap { flex-shrink: 0; display: flex; align-items: center; justify-content: center; padding: 0 4px; }
.regen-checkbox { transform: scale(1.1); cursor: pointer; margin: 0; accent-color: var(--amber); }

.map-run-toggle {
    flex-shrink: 0; padding: 4px 10px; border-radius: 20px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.65rem; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.15s;
    text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;
}
.map-run-toggle.create-btn { border-color: var(--teal); color: var(--teal); }
.map-run-toggle.create-btn:hover { background: var(--teal); color: #000; }

/* ops menu */
.mr-ops-wrap { position: relative; flex-shrink: 0; }
.btn-mr-ops {
    flex-shrink: 0; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.65rem; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.15s;
}
.btn-mr-ops:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }
.mr-ops-menu {
    display: none; position: absolute; right: 0; top: calc(100% + 4px);
    background: var(--card); border: 1px solid var(--border); border-radius: 4px;
    min-width: 140px; z-index: 9999; box-shadow: 0 4px 16px rgba(0,0,0,0.5);
}
.mr-ops-menu.open { display: block; }
.mr-ops-item {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 12px; font-size: 0.72rem; color: var(--text-muted);
    cursor: pointer; transition: background 0.12s, color 0.12s;
    border: none; background: none; width: 100%; text-align: left; font-family: inherit;
    white-space: nowrap;
}
.mr-ops-item:hover { background: rgba(255,255,255,0.06); color: var(--text); }
.mr-ops-item + .mr-ops-item { border-top: 1px solid var(--border); }

/* ── PLAYER PANEL ── */
.eha-player-panel {
    flex-shrink: 0; background: var(--card); border-bottom: 1px solid var(--border);
    padding: 10px 14px; display: flex; flex-direction: column; gap: 6px;
}
.player-top { display: flex; align-items: center; gap: 10px; }
.player-meta { flex: 1; min-width: 0; }
.player-title { font-size: 0.85rem; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.player-sub   { font-size: 0.65rem; color: var(--text-muted); margin-top: 2px; }

.player-controls { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.ctrl-btn {
    width: 34px; height: 34px; border-radius: 50%; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 14px;
    transition: all 0.15s; flex-shrink: 0;
}
.ctrl-btn:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }
.ctrl-btn.play-pause { width: 40px; height: 40px; border-color: var(--teal); color: var(--teal); font-size: 16px; }
.ctrl-btn.play-pause:hover { background: var(--teal); color: #000; }
.ctrl-btn.active { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }

/* Seek bar */
.player-seek-row { display: flex; align-items: center; gap: 8px; }
.seek-time { font-size: 0.65rem; color: var(--text-muted); font-family: monospace; min-width: 36px; text-align: center; }
.seek-bar {
    flex: 1; -webkit-appearance: none; height: 4px; border-radius: 2px; cursor: pointer;
    background: linear-gradient(to right, var(--teal) 0%, var(--border) 0%);
    outline: none;
}
.seek-bar::-webkit-slider-thumb {
    -webkit-appearance: none; width: 14px; height: 14px; border-radius: 50%;
    background: var(--teal); cursor: pointer; margin-top: -5px;
}
.seek-bar::-moz-range-thumb {
    width: 14px; height: 14px; border-radius: 50%; background: var(--teal); cursor: pointer; border: none;
}

/* Volume */
.volume-row { display: flex; align-items: center; gap: 8px; }
.vol-icon { font-size: 0.85rem; color: var(--text-muted); flex-shrink: 0; cursor: pointer; }
.vol-bar {
    width: 80px; -webkit-appearance: none; height: 4px; border-radius: 2px; cursor: pointer;
    background: linear-gradient(to right, var(--purple) 70%, var(--border) 70%);
    outline: none;
}
.vol-bar::-webkit-slider-thumb { -webkit-appearance: none; width: 12px; height: 12px; border-radius: 50%; background: var(--purple); cursor: pointer; margin-top: -4px; }
.vol-bar::-moz-range-thumb     { width: 12px; height: 12px; border-radius: 50%; background: var(--purple); cursor: pointer; border: none; }

/* ── PLAYLIST ── */
.eha-playlist-area { flex: 1; overflow-y: auto; background: var(--bg); min-height: 0; }

.pl-toolbar {
    padding: 6px 12px; border-bottom: 1px solid var(--border);
    background: var(--card); display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap;
}
.pl-info { font-size: 0.7rem; color: var(--text-muted); flex: 1; }
.pl-search { flex: 1; min-width: 100px; max-width: 200px; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.75rem; }
.pl-search:focus { outline: none; border-color: var(--teal); }

.audio-track {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 12px; border-bottom: 1px solid var(--border);
    cursor: pointer; transition: background 0.12s; position: relative;
}
.audio-track:hover { background: rgba(255,255,255,0.04); }
.audio-track.playing { background: var(--teal-dim); border-left: 3px solid var(--teal); padding-left: 9px; }
.audio-track.selected { background: rgba(139,92,246,0.08); }

.track-play-icon { flex-shrink: 0; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-size: 14px; color: var(--text-muted); }
.audio-track.playing .track-play-icon { color: var(--teal); animation: pulseIcon 1.2s ease-in-out infinite; }
@keyframes pulseIcon { 0%,100%{opacity:1;} 50%{opacity:0.5;} }

.track-info { flex: 1; min-width: 0; }
.track-name { font-size: 0.8rem; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.track-meta { font-size: 0.65rem; color: var(--text-muted); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.track-dur  { font-size: 0.65rem; color: var(--text-muted); font-family: monospace; flex-shrink: 0; min-width: 36px; text-align: right; }

.track-actions { display: flex; gap: 4px; flex-shrink: 0; }
.tr-btn {
    width: 26px; height: 26px; border-radius: 4px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 12px;
    transition: all 0.12s;
}
.tr-btn:hover                { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }
.tr-btn.tr-regen:hover       { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }
.tr-btn.tr-mic:hover         { border-color: var(--green); color: var(--green); background: var(--green-dim); }
.tr-btn.tr-delete:hover      { border-color: var(--red); color: var(--red); background: rgba(239,68,68,0.1); }

.state-msg { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); font-size: 0.8rem; gap: 8px; padding: 40px; }
.spinner { width: 20px; height: 20px; border: 2px solid var(--border); border-top-color: var(--text); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── RECORDER CHOICE MODAL ── */
.rec-choice-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.8);
    z-index: 200000; display: none; align-items: center; justify-content: center;
}
.rec-choice-backdrop.active { display: flex; }
.rec-choice-card {
    background: var(--card); border: 1px solid var(--border); border-radius: 12px;
    padding: 24px; max-width: 360px; width: 90%;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5); text-align: center;
}
.rec-choice-title { font-size: 0.9rem; font-weight: 700; color: var(--teal); margin-bottom: 6px; }
.rec-choice-sub   { font-size: 0.72rem; color: var(--text-muted); margin-bottom: 18px; }
.rec-choice-btn {
    display: block; width: 100%; padding: 11px; margin-top: 10px;
    border: 1px solid var(--border); border-radius: 6px;
    background: var(--bg); color: var(--text); cursor: pointer;
    font-weight: 600; font-size: 0.85rem; font-family: inherit;
    transition: all 0.18s; text-align: center;
}
.rec-choice-btn:hover  { background: var(--teal); color: #000; border-color: var(--teal); }
.rec-choice-btn.cancel { font-size: 0.78rem; color: var(--text-muted); margin-top: 14px; border: none; background: transparent; }
.rec-choice-btn.cancel:hover { text-decoration: underline; background: transparent; color: var(--text); }

/* ── VOICE MODAL ── */
.voice-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.8);
    z-index: 200000; display: none; align-items: center; justify-content: center;
}
.voice-modal-backdrop.active { display: flex; }
.voice-modal-card {
    background: var(--card); border: 1px solid var(--border); border-radius: 12px;
    padding: 20px; max-width: 380px; width: 90%;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    max-height: 80vh; display: flex; flex-direction: column;
}
.voice-modal-title { font-size: 0.85rem; font-weight: 700; color: var(--teal); margin-bottom: 12px; }
.voice-option-list { overflow-y: auto; flex: 1; margin-bottom: 12px; }
.voice-option {
    padding: 9px 12px; border-radius: 5px; cursor: pointer; margin-bottom: 4px;
    font-size: 0.78rem; border: 1px solid var(--border); background: var(--bg); color: var(--text);
    transition: all 0.12s;
}
.voice-option:hover { border-color: var(--teal); background: var(--teal-dim); }
.voice-option.selected { border-color: var(--teal); background: var(--teal-dim); color: var(--teal); font-weight: 700; }
.voice-modal-cancel { padding: 10px; width: 100%; border: 1px solid var(--border); border-radius: 5px; background: transparent; color: var(--text-muted); font-family: inherit; cursor: pointer; font-size: 0.78rem; }
.voice-modal-cancel:hover { border-color: var(--red); color: var(--red); }
</style>

<?php require_once "forge_tool.php"; ?>

<div class="eha-layout">

    <!-- ── HEADER ── -->
    <div class="eha-header">
        <div class="eha-title"><span>&#127925;</span> <span style="font-size:0.7em; opacity:0.6; margin-left:8px;">DAW Bounces Viewer</span></div>
        <div class="eha-nav">
            <label for="entitySelect" style="font-size:0.85rem; color:var(--text-muted); margin-right:8px;">Entity:</label>
            <select id="entitySelect" onchange="selectEntity(this.value)" style="background:var(--card); border:1px solid var(--border); color:var(--text); padding:6px 8px; border-radius:4px; font-family:inherit;">
                <?php foreach ($audioEntities as $ename):
                    $icon = $entityIcons[$ename] ?? '🎧';
                ?>
                    <option value="<?php echo htmlspecialchars($ename, ENT_QUOTES); ?>" <?php echo ($ename === $selectedEntity ? 'selected' : ''); ?>>
                        <?php echo htmlspecialchars($icon . ' ' . $ename, ENT_QUOTES); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <!-- Sync voice models -->
            <button id="btnSyncModels" title="Sync Voice Models" onclick="syncModels()" style="padding:5px 10px; border:1px solid var(--border); border-radius:4px; background:transparent; color:var(--text-muted); font-family:inherit; font-size:0.7rem; cursor:pointer; display:flex; align-items:center; gap:4px; transition:all 0.15s;">
                <i class="bi bi-arrow-repeat" id="syncIcon"></i>
            </button>
        </div>
    </div>

    <!-- ── ENTITY LIST PANEL ── -->
    <div class="eha-top-panel">
        <div class="mr-controls-row">
            <button class="map-run-toggle create-btn" onclick="createNewEntity()" title="New Entity"><i class="bi bi-plus-lg"></i> New</button>
            <input type="text" class="mr-search-input" id="mrSearch" placeholder="Search entity..." oninput="debounceSearch()">
            <div class="mr-pagination">
                <button class="pg-btn" id="mrPrev" onclick="changePage(-1)">&#8592;</button>
                <input type="number" class="pg-input" id="mrPageInput" value="1" onchange="jumpToPage()">
                <span class="pg-total" id="mrTotalPages">/ 1</span>
                <button class="pg-btn" id="mrNext" onclick="changePage(1)">&#8594;</button>
            </div>
        </div>
        <div class="mr-list-scroll" id="mrList">
            <div class="state-msg">Loading...</div>
        </div>
    </div>

    <!-- ── HOWLER PLAYER PANEL ── -->
    <div class="eha-player-panel" id="playerPanel">
        <div class="player-top">
            <div class="player-meta">
                <div class="player-title" id="playerTitle">No track selected</div>
                <div class="player-sub" id="playerSub">Select an entity, then a track</div>
            </div>
            <div class="player-controls">
                <button class="ctrl-btn" id="btnShuffle" onclick="toggleShuffle()" title="Shuffle"><i class="bi bi-shuffle"></i></button>
                <button class="ctrl-btn" id="btnPrev"    onclick="prevTrack()"     title="Previous"><i class="bi bi-skip-start-fill"></i></button>
                <button class="ctrl-btn play-pause" id="btnPlayPause" onclick="togglePlayPause()" title="Play/Pause"><i class="bi bi-play-fill" id="ppIcon"></i></button>
                <button class="ctrl-btn" id="btnNext"    onclick="nextTrack()"     title="Next"><i class="bi bi-skip-end-fill"></i></button>
                <button class="ctrl-btn" id="btnLoop"    onclick="toggleLoop()"    title="Loop"><i class="bi bi-repeat"></i></button>
            </div>
        </div>
        <div class="player-seek-row">
            <span class="seek-time" id="seekCurrent">0:00</span>
            <input type="range" class="seek-bar" id="seekBar" min="0" max="100" value="0" step="0.1" oninput="onSeekInput(this.value)" onchange="onSeekChange(this.value)">
            <span class="seek-time" id="seekTotal">0:00</span>
        </div>
        <div class="volume-row">
            <i class="bi bi-volume-down vol-icon" onclick="toggleMute()" id="volIcon"></i>
            <input type="range" class="vol-bar" id="volBar" min="0" max="1" step="0.05" value="0.7" oninput="onVolChange(this.value)">
        </div>
    </div>

    <!-- ── PLAYLIST AREA ── -->
    <div class="eha-playlist-area">
        <div class="pl-toolbar">
            <span class="pl-info" id="plInfo">Select an entity to load playlist</span>
            <input type="text" class="pl-search" id="plSearch" placeholder="Filter tracks..." oninput="debouncePlaylistSearch()">
        </div>
        <div id="playlistContainer">
            <div class="state-msg"><div>&#8593; Select an Entity Above</div></div>
        </div>
    </div>
</div>

<!-- ── RECORDER CHOICE MODAL ── -->
<div class="rec-choice-backdrop" id="recChoiceBackdrop">
    <div class="rec-choice-card">
        <div class="rec-choice-title">🎙️ Start Recording</div>
        <div class="rec-choice-sub">Choose the recording mode:</div>
        <button class="rec-choice-btn" id="recSourceBtn">📝 <strong>Source (Wav2Wav)</strong><br><small style="font-weight:normal; color:var(--text-muted);">Updates source file</small></button>
        <button class="rec-choice-btn" id="recResultBtn">🆕 <strong>Result (New Entry)</strong><br><small style="font-weight:normal; color:var(--text-muted);">Creates new audio result</small></button>
        <button class="rec-choice-btn cancel" id="recCancelBtn">Cancel</button>
    </div>
</div>

<!-- ── VOICE PICKER MODAL ── -->
<div class="voice-modal-backdrop" id="voiceModalBackdrop">
    <div class="voice-modal-card">
        <div class="voice-modal-title"><i class="bi bi-person-voice"></i> Select Voice</div>
        <div class="voice-option-list" id="voiceOptionList"></div>
        <button class="voice-modal-cancel" onclick="closeVoiceModal()">Cancel</button>
    </div>
</div>

<script>
// ── STATE ─────────────────────────────────────────────
let curPage = 1, totalPages = 1, debounceTimer, plDebounceTimer;
let currentEntityId = null;
let currentEntity   = "<?php echo addslashes($selectedEntity); ?>";
const deepLinkEntityId = <?php echo $deepLinkEntityId; ?>;
const deepLinkSearch   = <?php echo json_encode($deepLinkSearch); ?>;

// Player state
let howl           = null;
let playlist       = [];        // [{audio_id, name, url, model, description, created_at, entity_id}, ...]
let currentTrackIdx = -1;
let isShuffle      = false;
let isLoop         = false;
let isMuted        = false;
let prevVolume     = 0.7;
let seekRafId      = null;
let isUserSeeking  = false;

// Voice options cache
let voiceOptions   = [];
let voiceTargetId  = null;       // entity_id for pending voice change

// Recorder state
let recTargetId    = null;

// Ops menu state
let activeOpsMenu = null;

const LS_KEY = 'enhanimaticism_bounces_nav';

// ── NAVIGATION PERSISTENCE ────────────────────────────
function saveNav() {
    try { localStorage.setItem(LS_KEY, JSON.stringify({ entity: currentEntity, page: curPage })); } catch(e) {}
}
function loadNav() {
    try { return JSON.parse(localStorage.getItem(LS_KEY) || 'null'); } catch(e) { return null; }
}

// ── ENTITY SELECTOR ───────────────────────────────────
function selectEntity(entity) {
    currentEntity = entity;
    const saved = loadNav();
    const startPage = (saved && saved.entity === entity && saved.page > 1) ? saved.page : 1;
    clearPlaylist();
    loadList(startPage);
}

function debounceSearch() { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => loadList(1), 300); }
function changePage(d)     { const n = curPage + d; if (n >= 1 && n <= totalPages) loadList(n); }
function jumpToPage()      { const v = parseInt(document.getElementById('mrPageInput').value); if (v >= 1 && v <= totalPages) loadList(v); }

// ── ENTITY LIST ───────────────────────────────────────
function loadList(page, onLoaded) {
    const list   = document.getElementById('mrList');
    const search = document.getElementById('mrSearch').value.trim();
    if (page === 1) list.scrollTop = 0;

    fetch(`?api_action=get_entities&limit=4&offset=${(page-1)*4}&search=${encodeURIComponent(search)}&entity=${encodeURIComponent(currentEntity)}`)
        .then(r => r.json()).then(res => {
            if (res.status !== 'success') return;
            curPage = page; totalPages = Math.ceil(res.total / 4) || 1;
            document.getElementById('mrPageInput').value = curPage;
            document.getElementById('mrTotalPages').textContent = `/ ${totalPages}`;
            saveNav();
            list.innerHTML = '';

            if (!res.data.length) { list.innerHTML = '<div class="state-msg">No entities found</div>'; return; }

            res.data.forEach(item => {
                const isActive = (item.id == currentEntityId);
                const el = document.createElement('div');
                el.className = `mr-item ${isActive ? 'active' : ''}`;
                el.dataset.id = item.id;
                el.onclick = () => selectEntityItem(item.id, el);

                // Detect regen column
                let regenCol = null, regenVal = 0;
                if (item.regenerate_audios !== undefined) { regenCol = 'regenerate_audios'; regenVal = item.regenerate_audios; }
                else if (item.regenerate    !== undefined) { regenCol = 'regenerate';        regenVal = item.regenerate; }

                let regenHtml = '';
                if (regenCol) {
                    const chk = parseInt(regenVal) === 1 ? 'checked' : '';
                    regenHtml = `<div class="mr-regen-wrap" title="Regenerate?" onclick="event.stopPropagation()">
                        <input type="checkbox" class="regen-checkbox" onchange="toggleRegen(this, ${item.id}, '${regenCol}')" ${chk}>
                    </div>`;
                }

                el.innerHTML = `
                    <div class="mr-id">#${item.id}</div>
                    <div class="mr-note">${esc(item.name || 'Unnamed')}</div>
                    <div class="mr-meta">${item.created_at ? item.created_at.substr(0,10) : ''}</div>
                    ${regenHtml}
                    <div class="mr-ops-wrap" onclick="event.stopPropagation()">
                        <button class="btn-mr-ops" onclick="toggleOpsMenu(event, ${item.id})" title="Options"><i class="bi bi-three-dots-vertical"></i></button>
                        <div class="mr-ops-menu">
                            <button class="mr-ops-item" onclick="opsEdit(event, ${item.id})"><i class="bi bi-pencil"></i> Edit</button>
                            <button class="mr-ops-item" onclick="opsCopy(event, ${item.id})"><i class="bi bi-files"></i> Copy</button>
                            <button class="mr-ops-item" onclick="opsVoice(event, ${item.id})"><i class="bi bi-person-voice"></i> Voice</button>
                            <button class="mr-ops-item" onclick="opsRec(event, ${item.id})"><i class="bi bi-mic"></i> Record</button>
                            <button class="mr-ops-item" onclick="opsRegenEntity(event, ${item.id})" style="color:var(--amber);"><i class="bi bi-arrow-repeat"></i> Regen</button>
                            <button class="mr-ops-item" onclick="opsDelete(event, ${item.id})" style="color:var(--red);"><i class="bi bi-trash"></i> Delete</button>
                        </div>
                    </div>
                `;
                list.appendChild(el);
            });
            if (typeof onLoaded === 'function') onLoaded();
        });
}

function selectEntityItem(id, el) {
    document.querySelectorAll('.mr-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
    currentEntityId = id;
    loadPlaylistForEntity(id);
}

// ── CREATE ENTITY ─────────────────────────────────────
function createNewEntity() {
    fetch(`?api_action=add_entity&entity=${encodeURIComponent(currentEntity)}`, { method: 'POST' })
        .then(r => r.json()).then(res => {
            if (res.status === 'success') {
                Toast.show('Created new entity', 'success');
                curPage = 1;
                document.getElementById('mrSearch').value = '';
                loadList(1, () => {
                    const el = document.querySelector(`.mr-item[data-id="${res.id}"]`);
                    if (el) selectEntityItem(res.id, el);
                    openEntityModal(res.id);
                });
            } else Toast.show(res.message || 'Error', 'error');
        });
}

function openEntityModal(id) {
    if (window.showEntityFormInModal) window.showEntityFormInModal(currentEntity, id);
}

// ── OPS MENU ──────────────────────────────────────────
function toggleOpsMenu(e, id) {
    e.stopPropagation();
    const menu = e.currentTarget.closest('.mr-ops-wrap').querySelector('.mr-ops-menu');
    if (activeOpsMenu && activeOpsMenu !== menu) activeOpsMenu.classList.remove('open');
    menu.classList.toggle('open');
    activeOpsMenu = menu.classList.contains('open') ? menu : null;
}
function closeAllOpsMenus() { document.querySelectorAll('.mr-ops-menu.open').forEach(m => m.classList.remove('open')); activeOpsMenu = null; }
document.addEventListener('click', closeAllOpsMenus);

function opsEdit(e, id)       { e.stopPropagation(); closeAllOpsMenus(); openEntityModal(id); }
function opsCopy(e, id)       {
    e.stopPropagation(); closeAllOpsMenus();
    if (!confirm('Copy entity #' + id + '?')) return;
    const fd = new URLSearchParams(); fd.append('entity_id', id);
    fetch(`?api_action=copy_entity&entity=${encodeURIComponent(currentEntity)}`, { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.status === 'success') { Toast.show('Copied', 'success'); loadList(curPage); }
            else Toast.show(res.message || 'Error', 'error');
        });
}
function opsDelete(e, id)     {
    e.stopPropagation(); closeAllOpsMenus();
    if (!confirm('Delete entity #' + id + '?')) return;
    const fd = new URLSearchParams(); fd.append('entity_id', id);
    fetch(`?api_action=delete_entity&entity=${encodeURIComponent(currentEntity)}`, { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.status === 'success') { Toast.show('Deleted', 'success'); loadList(curPage); if (currentEntityId == id) clearPlaylist(); }
        });
}
function opsRegenEntity(e, id) {
    e.stopPropagation(); closeAllOpsMenus();
    const fd = new URLSearchParams(); fd.append('entity_id', id);
    fetch(`?api_action=regenerate_audio&entity=${encodeURIComponent(currentEntity)}`, { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.status === 'success') Toast.show('Marked for regeneration', 'success');
            else Toast.show(res.message || 'Error', 'error');
        });
}
function opsVoice(e, id)      { e.stopPropagation(); closeAllOpsMenus(); openVoiceModal(id); }
function opsRec(e, id)        { e.stopPropagation(); closeAllOpsMenus(); openRecorderChoice(id); }

// ── TOGGLE REGEN (checkbox in list) ──────────────────
function toggleRegen(cb, id, col) {
    const val = cb.checked ? 1 : 0;
    const fd  = new URLSearchParams();
    fd.append('entity_id', id); fd.append('value', val); fd.append('column', col);
    fetch(`?api_action=toggle_regenerate&entity=${encodeURIComponent(currentEntity)}`, { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.status === 'success') Toast.show('Updated', 'success');
            else { cb.checked = !cb.checked; Toast.show(res.message || 'Error', 'error'); }
        });
}

// ── VOICE MODAL ───────────────────────────────────────
function openVoiceModal(entityId) {
    voiceTargetId = entityId;
    const list = document.getElementById('voiceOptionList');
    list.innerHTML = '<div style="color:var(--text-muted); font-size:0.75rem; padding:8px;">Loading...</div>';
    document.getElementById('voiceModalBackdrop').classList.add('active');

    if (voiceOptions.length > 0) { renderVoiceOptions(); return; }

    fetch(`?api_action=get_voice_options&entity=${encodeURIComponent(currentEntity)}`)
        .then(r => r.json()).then(res => {
            if (res.status === 'success') { voiceOptions = res.data; renderVoiceOptions(); }
            else list.innerHTML = '<div style="color:var(--red); padding:8px;">Failed to load voices</div>';
        });
}

function renderVoiceOptions() {
    const list = document.getElementById('voiceOptionList');
    list.innerHTML = '';
    voiceOptions.forEach(v => {
        const el = document.createElement('div');
        el.className = 'voice-option';
        el.textContent = v.name;
        el.onclick = () => assignVoice(v.id, v.name);
        list.appendChild(el);
    });
}

function assignVoice(voiceId, voiceName) {
    if (!voiceTargetId) return;
    const fd = new URLSearchParams();
    fd.append('entity_id', voiceTargetId); fd.append('field', 'audio_voice_identity_id'); fd.append('value', voiceId);
    fetch(`?api_action=update_field&entity=${encodeURIComponent(currentEntity)}`, { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.status === 'success') Toast.show(`Voice → ${voiceName}`, 'success');
            else Toast.show(res.message || 'Error', 'error');
        });
    closeVoiceModal();
}

function closeVoiceModal() {
    document.getElementById('voiceModalBackdrop').classList.remove('active');
    voiceTargetId = null;
}

// ── RECORDER CHOICE ───────────────────────────────────
function openRecorderChoice(entityId) {
    recTargetId = entityId;
    document.getElementById('recChoiceBackdrop').classList.add('active');
}

document.getElementById('recCancelBtn').onclick = () => {
    document.getElementById('recChoiceBackdrop').classList.remove('active');
    recTargetId = null;
};
document.getElementById('recSourceBtn').onclick = () => {
    if (recTargetId && window.showAudioRecorderModal) window.showAudioRecorderModal(currentEntity, recTargetId, 1);
    document.getElementById('recChoiceBackdrop').classList.remove('active');
};
document.getElementById('recResultBtn').onclick = () => {
    if (recTargetId && window.showAudioRecorderModal) window.showAudioRecorderModal(currentEntity, recTargetId, 0);
    document.getElementById('recChoiceBackdrop').classList.remove('active');
};

// ── PLAYLIST ──────────────────────────────────────────
let plSearchTimer;
function debouncePlaylistSearch() { clearTimeout(plSearchTimer); plSearchTimer = setTimeout(refilterPlaylist, 250); }

function refilterPlaylist() {
    if (currentEntityId) loadPlaylistForEntity(currentEntityId);
}

function clearPlaylist() {
    playlist = [];
    currentTrackIdx = -1;
    stopHowl();
    document.getElementById('playlistContainer').innerHTML = '<div class="state-msg"><div>&#8593; Select an Entity Above</div></div>';
    document.getElementById('plInfo').textContent = 'Select an entity to load playlist';
    document.getElementById('playerTitle').textContent = 'No track selected';
    document.getElementById('playerSub').textContent   = 'Select an entity, then a track';
}

function loadPlaylistForEntity(entityId) {
    const container = document.getElementById('playlistContainer');
    const search    = document.getElementById('plSearch').value.trim();
    container.innerHTML = '<div class="state-msg"><div class="spinner"></div><div>Loading...</div></div>';

    fetch(`?api_action=get_playlist&entity_id=${entityId}&search=${encodeURIComponent(search)}&entity=${encodeURIComponent(currentEntity)}`)
        .then(r => r.json()).then(res => {
            if (res.status !== 'success') { container.innerHTML = '<div class="state-msg">Error loading playlist</div>'; return; }
            playlist = res.data.map(row => ({
                audio_id:    row.audio_id   || row.id,
                entity_id:   row.entity_id  || entityId,
                name:        row.name       || ('Audio #' + (row.audio_id || row.id)),
                audio_name:  row.audio_name || '',
                model:       row.model      || '',
                description: row.description|| '',
                filename:    row.filename   || '',
                created_at:  row.created_at || '',
            }));

            renderPlaylist();
            document.getElementById('plInfo').textContent = `${playlist.length} track${playlist.length !== 1 ? 's' : ''}`;
        });
}

function renderPlaylist() {
    const container = document.getElementById('playlistContainer');
    container.innerHTML = '';

    if (!playlist.length) {
        container.innerHTML = '<div class="state-msg"><div>No audio found for this entity</div></div>';
        return;
    }

    playlist.forEach((track, idx) => {
        const isPlaying = (idx === currentTrackIdx);
        const el = document.createElement('div');
        el.className = 'audio-track' + (isPlaying ? ' playing' : '');
        el.dataset.idx = idx;

        const displayName = track.audio_name ? `${track.name} (${track.audio_name})` : track.name;
        const metaParts   = [track.model, track.created_at ? track.created_at.substr(0, 10) : ''].filter(Boolean);
        const descPreview = truncate(track.description, 60);

        el.innerHTML = `
            <div class="track-play-icon"><i class="bi ${isPlaying ? 'bi-soundwave' : 'bi-play-fill'}"></i></div>
            <div class="track-info">
                <div class="track-name">${esc(displayName)}</div>
                <div class="track-meta">${esc(metaParts.join(' · '))}${descPreview ? ' · <span title="' + esc(track.description) + '">' + esc(descPreview) + '</span>' : ''}</div>
            </div>
            <div class="track-dur" id="dur-${idx}">—</div>
            <div class="track-actions" onclick="event.stopPropagation()">
                <a class="tr-btn" title="Download" href="${esc(track.filename)}" download target="_blank" style="text-decoration:none;"><i class="bi bi-download"></i></a>
                <button class="tr-btn tr-mic"    title="Record" onclick="openRecorderChoice(${track.entity_id})"><i class="bi bi-mic"></i></button>
                <button class="tr-btn tr-regen"  title="Regenerate" onclick="regenTrack(${track.entity_id})"><i class="bi bi-arrow-repeat"></i></button>
                <button class="tr-btn"           title="Edit entity" onclick="openEntityModal(${track.entity_id})"><i class="bi bi-pencil"></i></button>
                <button class="tr-btn tr-delete" title="Delete entity" onclick="deleteTrackEntity(${track.entity_id})"><i class="bi bi-trash"></i></button>
            </div>
        `;

        // Click on track body → play
        el.querySelector('.track-info').addEventListener('click', () => playTrackAt(idx));
        el.querySelector('.track-play-icon').addEventListener('click', () => playTrackAt(idx));

        container.appendChild(el);
    });
}

function regenTrack(entityId) {
    const fd = new URLSearchParams(); fd.append('entity_id', entityId);
    fetch(`?api_action=regenerate_audio&entity=${encodeURIComponent(currentEntity)}`, { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.status === 'success') Toast.show('Marked for regen', 'success');
            else Toast.show(res.message || 'Error', 'error');
        });
}

function deleteTrackEntity(entityId) {
    if (!confirm('Delete entity #' + entityId + '?')) return;
    const fd = new URLSearchParams(); fd.append('entity_id', entityId);
    fetch(`?api_action=delete_entity&entity=${encodeURIComponent(currentEntity)}`, { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.status === 'success') {
                Toast.show('Deleted', 'success');
                loadList(curPage);
                if (currentEntityId) loadPlaylistForEntity(currentEntityId);
            }
        });
}

// ── HOWLER PLAYER ─────────────────────────────────────
function stopHowl() {
    if (howl) { howl.stop(); howl.unload(); howl = null; }
    cancelAnimationFrame(seekRafId);
    updateSeekUI(0, 0);
    setPPIcon(false);
}

function playTrackAt(idx) {
    if (idx < 0 || idx >= playlist.length) return;
    stopHowl();
    currentTrackIdx = idx;
    const track = playlist[idx];

    // Update player meta
    document.getElementById('playerTitle').textContent = track.audio_name
        ? `${track.name} (${track.audio_name})`
        : track.name;
    document.getElementById('playerSub').textContent =
        [track.model, truncate(track.description, 80)].filter(Boolean).join(' — ') || currentEntity;

    // Highlight row
    document.querySelectorAll('.audio-track').forEach((el, i) => {
        el.classList.toggle('playing', i === idx);
        const icon = el.querySelector('.track-play-icon i');
        if (icon) {
            icon.className = 'bi ' + (i === idx ? 'bi-soundwave' : 'bi-play-fill');
        }
    });

    howl = new Howl({
        src:    [track.filename],
        html5:  true,
        volume: parseFloat(document.getElementById('volBar').value),
        onplay: () => {
            setPPIcon(true);
            seekRafLoop();
            // Show duration once metadata loaded
            const dur = howl.duration();
            document.getElementById('seekTotal').textContent = formatTime(dur || 0);
            document.getElementById('seekBar').max = dur || 100;
            // Update list item dur
            const durEl = document.getElementById('dur-' + idx);
            if (durEl) durEl.textContent = formatTime(dur);
        },
        onpause: () => { setPPIcon(false); cancelAnimationFrame(seekRafId); },
        onstop:  () => { setPPIcon(false); cancelAnimationFrame(seekRafId); },
        onend:   () => {
            setPPIcon(false);
            cancelAnimationFrame(seekRafId);
            updateSeekUI(0, howl ? howl.duration() : 0);
            if (isLoop)    { playTrackAt(idx); }
            else           { autoNext(); }
        },
        onloaderror: () => Toast.show('Failed to load audio', 'error'),
    });
    howl.play();
}

function autoNext() {
    if (!playlist.length) return;
    if (isShuffle) {
        let next;
        do { next = Math.floor(Math.random() * playlist.length); } while (playlist.length > 1 && next === currentTrackIdx);
        playTrackAt(next);
    } else {
        const next = currentTrackIdx + 1;
        if (next < playlist.length) playTrackAt(next);
        // else: stop at end
    }
}

function togglePlayPause() {
    if (!howl) {
        if (playlist.length > 0) playTrackAt(currentTrackIdx >= 0 ? currentTrackIdx : 0);
        return;
    }
    if (howl.playing()) howl.pause();
    else                howl.play();
}

function prevTrack() {
    if (!playlist.length) return;
    if (currentTrackIdx <= 0) playTrackAt(playlist.length - 1);
    else                       playTrackAt(currentTrackIdx - 1);
}

function nextTrack() { autoNext(); }

function toggleShuffle() {
    isShuffle = !isShuffle;
    document.getElementById('btnShuffle').classList.toggle('active', isShuffle);
}

function toggleLoop() {
    isLoop = !isLoop;
    document.getElementById('btnLoop').classList.toggle('active', isLoop);
}

function toggleMute() {
    if (!howl) return;
    isMuted = !isMuted;
    howl.mute(isMuted);
    document.getElementById('volIcon').className = isMuted ? 'bi bi-volume-mute vol-icon' : 'bi bi-volume-down vol-icon';
}

function onVolChange(val) {
    const v = parseFloat(val);
    if (howl) howl.volume(v);
    // Update gradient
    document.getElementById('volBar').style.background =
        `linear-gradient(to right, var(--purple) ${v*100}%, var(--border) ${v*100}%)`;
}

function onSeekInput(val) {
    // While dragging, just update display; don't seek yet (smoother)
    isUserSeeking = true;
    const dur = howl ? howl.duration() : 0;
    const pos = (parseFloat(val) / 100) * dur;
    document.getElementById('seekCurrent').textContent = formatTime(pos);
}

function onSeekChange(val) {
    isUserSeeking = false;
    if (!howl) return;
    const dur = howl.duration();
    const pos = (parseFloat(val) / 100) * dur;
    howl.seek(pos);
}

function seekRafLoop() {
    seekRafId = requestAnimationFrame(() => {
        if (howl && howl.playing() && !isUserSeeking) {
            const pos = howl.seek() || 0;
            const dur = howl.duration() || 0;
            updateSeekUI(pos, dur);
        }
        if (howl && howl.playing()) seekRafLoop();
    });
}

function updateSeekUI(pos, dur) {
    document.getElementById('seekCurrent').textContent = formatTime(pos);
    document.getElementById('seekTotal').textContent   = formatTime(dur);
    const pct = dur > 0 ? (pos / dur) * 100 : 0;
    const bar  = document.getElementById('seekBar');
    bar.value  = pct;
    bar.style.background = `linear-gradient(to right, var(--teal) ${pct}%, var(--border) ${pct}%)`;
}

function setPPIcon(playing) {
    const icon = document.getElementById('ppIcon');
    icon.className = playing ? 'bi bi-pause-fill' : 'bi bi-play-fill';
}

function formatTime(sec) {
    if (!sec || isNaN(sec)) return '0:00';
    const m = Math.floor(sec / 60);
    const s = Math.floor(sec % 60);
    return m + ':' + (s < 10 ? '0' : '') + s;
}

// ── SYNC VOICE MODELS ─────────────────────────────────
function syncModels() {
    const btn  = document.getElementById('btnSyncModels');
    const icon = document.getElementById('syncIcon');
    btn.disabled = true; icon.className = 'bi bi-arrow-repeat spinning';
    btn.style.borderColor = 'var(--teal)'; btn.style.color = 'var(--teal)';
    fetch(`?api_action=sync_models&entity=${encodeURIComponent(currentEntity)}`, { method: 'POST' })
        .then(r => r.json()).then(res => {
            if (res.status === 'success') { Toast.show(res.message || 'Synced!', 'success'); voiceOptions = []; }
            else Toast.show(res.message || 'Sync error', 'error');
        }).catch(() => Toast.show('Network error', 'error'))
        .finally(() => {
            btn.disabled = false; icon.className = 'bi bi-arrow-repeat';
            btn.style.borderColor = ''; btn.style.color = '';
        });
}

// Spinning style for sync icon
const spinStyle = document.createElement('style');
spinStyle.textContent = '.spinning { display:inline-block; animation: spin 0.8s linear infinite; }';
document.head.appendChild(spinStyle);

// ── INIT ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Restore nav
    const saved = loadNav();
    const startPage = (saved && saved.entity === currentEntity && saved.page > 1) ? saved.page : 1;

    // Apply entity selector
    const sel = document.getElementById('entitySelect');
    if (sel) sel.value = currentEntity;

    // Set initial volume gradient
    onVolChange(document.getElementById('volBar').value);

    if (deepLinkEntityId) {
        document.getElementById('mrSearch').value = String(deepLinkEntityId);
        loadList(1, () => {
            const first = document.querySelector('.mr-item');
            if (first) first.click();
        });
    } else if (deepLinkSearch) {
        document.getElementById('mrSearch').value = deepLinkSearch;
        loadList(1);
    } else {
        loadList(startPage);
    }
});

document.addEventListener('keydown', e => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (e.key === ' ') { e.preventDefault(); togglePlayPause(); }
    if (e.key === 'ArrowRight') { e.preventDefault(); nextTrack(); }
    if (e.key === 'ArrowLeft')  { e.preventDefault(); prevTrack(); }
    if (e.key === 'Escape') { closeAllOpsMenus(); closeVoiceModal(); }
});

function esc(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }
function truncate(s, max) { if (!s) return ''; s = String(s); return s.length > max ? s.slice(0, max) + '…' : s; }
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>