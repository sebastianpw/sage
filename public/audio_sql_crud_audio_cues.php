<?php
// Template Placeholder: audio_cues
// Generated via rollout_audio_cruds.sh

require "e.php";
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require __DIR__ . '/VoicePool.php'; // Include VoicePool class
require "entity_icons.php";

$entity = 'audio_cues';
$selfScript = basename($_SERVER['PHP_SELF']);

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// --- DISCOVERY & CONFIGURATION ---
$stmt = $pdo->query("SHOW COLUMNS FROM `$entity`");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

$hasOrder = in_array('order', $columns);
$hasDescription = in_array('description', $columns);

// Audio specific regenerate column
$regenCol = in_array('regenerate_audios', $columns) ? 'regenerate_audios' : (in_array('regenerate', $columns) ? 'regenerate' : null);
$hasRegenerate = !empty($regenCol);

$hasMapRun = in_array('active_map_run_id', $columns);

// Check for Filename/URL for Audio Player
$hasFilename = in_array('filename', $columns);
$hasUrl = in_array('url', $columns);
$audioCol = $hasFilename ? 'filename' : ($hasUrl ? 'url' : null);

// --- Voice Identity Integration ---
$hasVoiceIdentityCol = in_array('audio_voice_identity_id', $columns);
$isVoiceIdentityTable = ($entity === 'audio_voice_identity');

// Icon Selection
$iconChar = $entityIcons[$entity] ?? '🎧';

// --- HANDLE AJAX REQUESTS ---
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. SYNC MODELS ACTION
    if ($action == 'sync_models' && ($isVoiceIdentityTable || $hasVoiceIdentityCol)) {
        try {
            $vp = new VoicePool();
            $stats = $vp->syncFromApiToDb($pdo);
            echo json_encode(['success' => true, 'message' => "Synced! Added: {$stats['added']}, Updated: {$stats['updated']}"]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action == 'fetch') {
        $search = $_POST['search'] ?? '';
        $page   = (int)($_POST['page'] ?? 1);
        $limit  = (int)($_POST['limit'] ?? 10);
        if ($limit <= 0) $limit = 10;
        if ($page <= 0) $page = 1;
        $offset = ($page - 1) * $limit;

        $sqlSelect = "SELECT * FROM `$entity`";
        $countSql  = "SELECT COUNT(*) FROM `$entity`"; 

        // Filtering
        $where = [];
        $params = [];
        if ($search !== '') {
            // Build search clause dynamically
            $searchClause = "(id = :id OR name LIKE :search";
            if ($hasDescription) {
                $searchClause .= " OR description LIKE :search";
            }
            $searchClause .= ")";
            
            $where[] = $searchClause;
            $params['id'] = (int)$search;
            $params['search'] = "%$search%";
        }

        if (!empty($where)) {
            $clause = " WHERE " . implode(' AND ', $where);
            $sqlSelect .= $clause;
            $countSql  .= $clause;
        }

        // Sorting
        if ($hasOrder) {
            $sqlSelect .= " ORDER BY `order` ASC, id DESC";
        } else {
            $sqlSelect .= " ORDER BY id DESC";
        }

        // Pagination Limits
        $sqlSelect .= " LIMIT :limit OFFSET :offset";

        // Execute Count
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRows = $countStmt->fetchColumn();
        $totalPages = ceil($totalRows / $limit);

        // Execute Data Fetch
        $stmt = $pdo->prepare($sqlSelect);
        foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Voice Options
        $voiceOptions = [];
        if ($hasVoiceIdentityCol) {
            try {
                $vStmt = $pdo->query("SELECT id, name FROM audio_voice_identity ORDER BY name ASC");
                $voiceOptions = $vStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $voiceOptions = [];
            }
        }

        echo json_encode([
            'rows' => $rows,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'voiceOptions' => $voiceOptions
        ]);
        exit;
    }

    if ($action == 'update') {
        $id = (int)$_POST['id'];
        $field = $_POST['field'];
        $value = $_POST['value'];
        if (!in_array($field, $columns)) exit('Invalid field');
        $stmt = $pdo->prepare("UPDATE `$entity` SET `$field` = :value WHERE id = :id");
        $stmt->execute(['value'=>$value, 'id'=>$id]);
        
        if ($field === 'audio_voice_identity_id' && $hasRegenerate) {
             $pdo->query("UPDATE `$entity` SET `$regenCol` = 1 WHERE id = $id");
        }
        
        exit('success');
    }

    if ($action == 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM `$entity` WHERE id = :id");
        $stmt->execute(['id'=>$id]);
        exit('success');
    }

    if ($action == 'add') {
        $uniqueName = "New " . ucfirst(str_replace(['audio_', '_'], ['', ' '], $entity)) . " " . time();
        $cols = ['name'];
        $vals = [':name'];
        $params = ['name' => $uniqueName];

        if ($hasOrder) {
            $cols[] = '`order`';
            $vals[] = '0';
        }
        
        if ($hasVoiceIdentityCol) {
            $defVoice = $pdo->query("SELECT id FROM audio_voice_identity ORDER BY id ASC LIMIT 1")->fetchColumn();
            if ($defVoice) {
                $cols[] = 'audio_voice_identity_id';
                $vals[] = $defVoice;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO `$entity` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")");
        $stmt->execute($params);
        echo $pdo->lastInsertId();
        exit;
    }

    if ($action == 'copy') {
        $id = (int)$_POST['id'];
        $colsList = implode(", ", array_map(fn($c) => "`$c`", array_filter($columns, fn($c)=> $c !== 'id')));
        $stmt = $pdo->prepare("SELECT $colsList FROM `$entity` WHERE id = :id");
        $stmt->execute(['id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            if(isset($row['name'])) $row['name'] .= ' (Copy)';
            $placeholders = implode(", ", array_fill(0, count($row), '?'));
            $stmt = $pdo->prepare("INSERT INTO `$entity` ($colsList) VALUES ($placeholders)");
            $stmt->execute(array_values($row));
            echo $pdo->lastInsertId();
        }
        exit;
    }

    if ($action == 'regenerate' && $hasRegenerate) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE `$entity` SET `$regenCol` = 1 WHERE id = :id");
        $stmt->execute(['id'=>$id]);
        exit('success');
    }

    if ($action == 'reorder' && $hasOrder) {
        $orderData = $_POST['order'] ?? [];
        $stmt = $pdo->prepare("UPDATE `$entity` SET `order` = :order WHERE id = :id");
        foreach ($orderData as $item) {
            $stmt->execute(['order'=>(int)$item['order'], 'id'=>(int)$item['id']]);
        }
        exit('success');
    }

    if($action === 'fetchMapRuns' && $hasMapRun) {
        $eid = (int)$_POST['entity_id'];
        try {
            $sql = "SELECT DISTINCT mr.id, mr.created_at, mr.note 
                    FROM map_runs mr
                    INNER JOIN audios a ON a.map_run_id = mr.id
                    WHERE a.entity_type = :ent 
                      AND a.entity_id = :eid 
                    ORDER BY mr.id DESC LIMIT 50";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['ent' => $entity, 'eid' => $eid]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($rows);
        } catch (Exception $e) {
            echo json_encode([]);
        }
        exit;
    }

    if($action === 'setActiveMapRun' && $hasMapRun) {
        $stmt = $pdo->prepare("UPDATE `$entity` SET active_map_run_id = :rid WHERE id = :eid");
        $stmt->execute(['rid'=>(int)$_POST['map_run_id'], 'eid'=>(int)$_POST['entity_id']]);
        exit('success');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo ucfirst(str_replace('_', ' ', $entity)); ?> Manager</title>

<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
      else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
    } catch (e) {}
  })();
</script>

<?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">

<!-- Swiper -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<?php else: ?>
    <link rel="stylesheet" href="/vendor/swiper/swiper-bundle.min.css" />
    <script src="/vendor/swiper/swiper-bundle.min.js"></script>
<?php endif; ?>

<style>
/* Modern styling using base.css vars */
:root {
    --table-header-bg: rgba(var(--muted-border-rgb), 0.1);
    --table-stripe: rgba(var(--muted-border-rgb), 0.03);
}

body {
    padding: 20px;
    background-color: var(--bg);
    color: var(--text);
    padding-bottom: 50px;
    position: relative;
    /* Spacer for the fixed header will be handled by JS */
}

/* FIXED HEADER WRAPPER */
.fixed-header-wrapper {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    background-color: var(--bg);
    padding: 10px 20px; /* Default padding, Left overridden by inline style */
    border-bottom: 1px solid var(--border);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

/* Header Compact */
.header-compact {
    display: flex;
    align-items: center;
    gap: 15px;
    height: 40px;
    margin-bottom: 10px;
}

/* Search Line */
.search-line {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Floating Entity Icon - Now Inline */
.entity-icon-link {
    font-size: 1.5rem;
    text-decoration: none;
    line-height: 1;
    transition: transform 0.2s;
    /* Removed absolute positioning to allow flex flow in header-compact */
    margin-right: 15px; 
}
.entity-icon-link:hover { transform: scale(1.15); }

.header-controls { display: flex; align-items: center; gap: 6px; }

.search-input {
    padding: 4px 8px;
    font-size: 0.85rem;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--card);
    color: var(--text);
    width: 200px;
    transition: width 0.2s;
}
.search-input:focus { outline: none; border-color: var(--accent); }

/* Table */
table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 8px; overflow: hidden; box-shadow: var(--card-elevation); }
th { background: var(--table-header-bg); color: var(--text-muted); font-weight: 600; text-align: left; padding: 12px; font-size: 0.85rem; text-transform: uppercase; }
td { padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; color: var(--text); }
tr:last-child td { border-bottom: none; }
tr:nth-child(even) { background-color: var(--table-stripe); }

td[contenteditable="true"] { background: rgba(var(--muted-border-rgb), 0.05); border-radius: 3px; min-width: 50px; }
td[contenteditable="true"]:focus { outline: 2px solid var(--accent); background: var(--bg); }

/* Buttons */
.action-btn { 
    width: 28px; height: 28px; padding: 0; 
    display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid var(--border); background: var(--bg); color: var(--text-muted);
    border-radius: 4px; cursor: pointer; transition: all 0.2s;
}
.action-btn:hover { border-color: var(--accent); color: var(--accent); transform: translateY(-1px); }
.action-btn.delete:hover { border-color: var(--red); color: var(--red); }

/* Checkbox Style in Action Bar */
.action-checkbox-wrapper {
    width: 28px; height: 28px;
    display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid var(--border); background: var(--bg);
    border-radius: 4px;
}
.regen-checkbox { transform: scale(1.2); cursor: pointer; margin: 0; }

/* Audio Player Style */
audio { height: 32px; width: 250px; margin-top: 4px; border-radius: 20px; }

/* Voice Dropdown */
.voice-select {
    width: 100%; max-width: 180px; padding: 4px;
    font-size: 0.85rem; border: 1px solid var(--border);
    background: var(--bg); color: var(--text); border-radius: 4px;
}

/* Swiper Fixes */
.swiper { 
    width: 100%; 
    overflow: visible; 
    padding-bottom: 120px; /* Space for pagination */
}
.swiper-slide { height: auto; opacity: 0; transition: opacity 0.3s; }
.swiper-slide-active { opacity: 1; }

.swiper-pagination {
    bottom: 0 !important;
    display: flex !important;
    flex-wrap: wrap !important;
    justify-content: center !important;
    padding: 10px;
}
.swiper-pagination-bullet {
    margin: 4px !important;
    width: 10px; 
    height: 10px;
}

/* Draggable */
.dragHandle { cursor: grab; color: var(--text-muted); font-size: 1.2rem; margin-right: 8px; }

/* Recorder Selection Modal Style */
.rec-select-modal {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    z-index: 100000;
    display: none;
    align-items: center; justify-content: center;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(2px);
}
.rec-select-card {
    background: var(--card); border: 1px solid var(--border); border-radius: 12px;
    padding: 25px; max-width: 400px; width: 90%;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    text-align: center;
}
.rec-select-btn {
    display: block; width: 100%; padding: 12px; margin-top: 10px;
    border: 1px solid var(--border); border-radius: 6px;
    background: var(--bg); color: var(--text); cursor: pointer;
    font-weight: 600; font-size: 1rem;
    transition: all 0.2s;
}
.rec-select-btn:hover { background: var(--accent); color: white; border-color: var(--accent); }
.rec-select-btn.secondary { font-size: 0.9rem; color: var(--text-muted); margin-top: 15px; border:none; background:transparent;}
.rec-select-btn.secondary:hover { text-decoration: underline; background: transparent; color: var(--text); }

/* SYNC BUTTON ANIMATION */
@keyframes spin { 100% { transform: rotate(360deg); } }
.syncing .icon-wrap {
    display: inline-block;
    animation: spin 1s linear infinite;
}
.syncing {
    background-color: var(--text) !important;
    color: var(--bg) !important;
    border-color: var(--text) !important;
    cursor: wait;
}

/* HEADER SPACER (Dynamically resized by JS) */
#headerSpacer {
    height: 100px; /* Fallback */
    width: 100%;
    display: block;
}

/* Responsive / Mobile Card View */
@media (max-width: 768px) {
    /* Override padding on mobile if needed, but keeping left 60px might be good for mobile menu too? 
       If mobile menu is small, maybe reduce to 50px */
    .fixed-header-wrapper { padding-left: 60px !important; } 
    
    .entity-icon-link { margin-right: 10px; }
    .header-compact { flex-wrap: wrap; height: auto; margin-left: 0; }
    .search-line { margin-left: 0; padding: 0; }
    .search-input { width: 100%; }

    table, thead, tbody, th, td, tr { display: block; }
    thead { display: none; }
    tr { margin-bottom: 15px; border: 1px solid var(--border); border-radius: 8px; background: var(--card); padding: 10px; box-shadow: var(--card-elevation); }
    td { display: flex; justify-content: space-between; align-items: center; border: none; padding: 8px 0; border-bottom: 1px solid rgba(var(--muted-border-rgb), 0.1); }
    td:last-child { border-bottom: none; }
    td::before { content: attr(data-label); font-weight: 600; color: var(--text-muted); font-size: 0.8rem; margin-right: 15px; flex-shrink: 0; }
    tr td:first-child { display: flex; width: 100%; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 8px; margin-bottom: 8px; }
    td.force-visible { display: block !important; }
    td.force-visible::before { display: block; margin-bottom: 5px; }
    audio { width: 100%; }
    td[data-label="Actions"] { justify-content: flex-end; padding-top: 5px; }
    td[data-label="Actions"]::before { display: none; }
}

.mapRunSelect { max-width: 150px; padding: 2px; font-size: 0.8rem; border: 1px solid var(--border); background: var(--bg); color: var(--text); border-radius: 4px; }
</style>
</head>
<body>

<?php require __DIR__ . '/modal_frame_details.php'; ?>
<?php require __DIR__ . '/modal_audio_details.php'; ?>

<!-- Fixed Header Wrapper -->
<div style="padding-left:60px;" id="fixedHeader" class="fixed-header-wrapper">
    <div class="header-compact">
        <a href="player_<?php echo $entity; ?>.php" class="entity-icon-link" title="Player"><?php echo $iconChar; ?></a>
        <div class="header-controls">
            <button id="addBtn" class="btn btn-sm btn-outline-primary">Add</button>
            
            <?php if($isVoiceIdentityTable || $hasVoiceIdentityCol): ?>
                <!-- Sync Button: Only Icon -->
                <button id="syncBtn" class="btn btn-sm btn-accent" title="Sync Models" style="border:1px solid var(--accent); padding: 5px 10px;">
                    <span class="icon-wrap">♻</span>
                </button>
            <?php endif; ?>
            
            <!-- Scheduler Run Button -->
            <a class="runBtn scheduler" data-id="50" title="Trigger Audio/Models Scheduler" style="cursor:pointer; font-size:1.2rem; text-decoration:none; margin-left:5px;">🌀</a>

            <?php if($hasOrder): ?>
            <button id="toggleSortBtn" class="btn btn-sm btn-outline-secondary">Drag</button>
            <div class="btn-group" role="group" style="display:inline-flex;">
                <button id="reorderAscBtn" class="btn btn-sm btn-outline-secondary" title="Sort A-Z (ID)">▲</button>
                <button style="margin-left:6px;" id="reorderDescBtn" class="btn btn-sm btn-outline-secondary" title="Sort Z-A (ID)">▼</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="search-line">
        <input type="text" id="searchInput" class="search-input" placeholder="Search...">
        <button id="sendSearchBtn" class="btn btn-sm btn-outline-secondary" title="Search">Send</button>
        <button id="resetSearchBtn" class="btn btn-sm btn-outline-secondary" title="Reset">Reset</button>
    </div>
</div>

<!-- Spacer to prevent content overlap -->
<div id="headerSpacer"></div>

<div class="swiper" id="mainSwiper">
  <div class="swiper-wrapper">
    <div class="swiper-slide" data-page="1">
      <div class="slide-inner">
        <table id="dataTable">
            <thead>
                <tr>
                    <th width="30%">Name</th>
                    <th width="140">Actions</th>
                    <?php
                    $hiddenCols = ['id', 'name', 'created_at', 'updated_at', 'order', 'regenerate', 'regenerate_audios', 'active_map_run_id', 'wav2wav', 'wav2wav_audio_id', 'wav2wav_audio_filename', 'filename', 'url'];
                    $displayCols = array_diff($columns, $hiddenCols);
                    foreach ($displayCols as $col) echo "<th>" . ucfirst(str_replace('_', ' ', $col)) . "</th>";
                    if ($audioCol) echo "<th>Audio</th>";
                    ?>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="swiper-pagination"></div>
</div>

<div id="recorderChoiceModal" class="rec-select-modal">
    <div class="rec-select-card">
        <h3>🎙️ Start Recording</h3>
        <p style="color:var(--text-muted); margin-bottom:20px;">Choose the recording mode:</p>
        <button class="rec-select-btn" id="recSourceBtn">📝 <strong>Source (Wav2Wav)</strong><br><small>Updates source file</small></button>
        <button class="rec-select-btn" id="recResultBtn">🆕 <strong>Result (New Entry)</strong><br><small>Creates new audio result</small></button>
        <button class="rec-select-btn secondary" id="recCancelBtn">Cancel</button>
    </div>
</div>

<script>
const ENTITY = '<?php echo $entity; ?>';
const HAS_ORDER = <?php echo $hasOrder ? 'true' : 'false'; ?>;
const REGEN_COL = '<?php echo $regenCol ?: ""; ?>';
const HAS_REGEN = <?php echo $hasRegenerate ? 'true' : 'false'; ?>;
const HAS_MAP = <?php echo $hasMapRun ? 'true' : 'false'; ?>;
const AUDIO_COL = '<?php echo $audioCol ?: ""; ?>';
const SELF_URL = '<?php echo $selfScript; ?>';
const HAS_VOICE_COL = <?php echo $hasVoiceIdentityCol ? 'true' : 'false'; ?>;
let globalVoiceOptions = [];

let currentPage = 1;
let rowsPerPage = 10;
let sortableEnabled = false;
let swiper = null;
let recordingTargetId = null;

function adjustHeaderSpacer() {
    const header = document.getElementById('fixedHeader');
    const spacer = document.getElementById('headerSpacer');
    if(header && spacer) {
        // Add a little buffer (e.g. 10px) to make it look clean
        spacer.style.height = (header.offsetHeight + 10) + 'px';
    }
}

function buildRowsHTML(rows) {
    let html = '';
    rows.forEach(row => {
        html += `<tr data-id="${row.id}">`;

        // 1. Name
        html += `<td data-label="Name">
                    <div style="display:flex; align-items:center; width:100%;">
                        ${HAS_ORDER ? '<span class="dragHandle">☰</span>' : ''}
                        <span style="font-family:monospace; font-size:0.75em; color:var(--text-muted); margin-right:8px; min-width:30px;">#${row.id}</span>
                        <div contenteditable="true" data-field="name" style="flex:1; font-weight:600; padding:4px 0;">${row.name || ''}</div>
                    </div>
                 </td>`;

        // 2. Actions
        html += `<td data-label="Actions" class="action-cell"><div style="display:flex; gap:5px; flex-wrap:wrap; justify-content:flex-end;">`;
        html += `<button class="action-btn micBtn" title="Record Audio">🎤</button>`;
        if (HAS_REGEN) {
            let checked = (row[REGEN_COL] == 1) ? 'checked' : '';
            html += `<label class="action-checkbox-wrapper" title="Regenerate?"><input type="checkbox" class="regen-checkbox" data-field="${REGEN_COL}" ${checked}></label>`;
        }
        html += `<button class="action-btn editBtn" title="Details">🕸️</button>`;
        html += `<button class="action-btn copyBtn" title="Copy">⎘</button>`;
        html += `<button class="action-btn delete" title="Delete">🗑</button>`;
        if (HAS_MAP) {
            html += `<select class="mapRunSelect" data-entity-id="${row.id}" data-active-id="${row.active_map_run_id}"><option value="">Run...</option></select>`;
        }
        html += `</div></td>`;

        // 3. Dynamic Cols
        <?php foreach ($displayCols as $col): ?>
            if ('<?php echo $col; ?>' === 'audio_voice_identity_id') {
                let currentVal = row['<?php echo $col; ?>'];
                let opts = '<option value="">(Default)</option>';
                globalVoiceOptions.forEach(v => {
                    let sel = (v.id == currentVal) ? 'selected' : '';
                    opts += `<option value="${v.id}" ${sel}>${v.name}</option>`;
                });
                html += `<td data-label="Voice" class="force-visible"><select class="voice-select" data-field="audio_voice_identity_id">${opts}</select></td>`;
            } else {
                html += `<td data-label="<?php echo ucfirst(str_replace('_',' ',$col)); ?>" class="force-visible" contenteditable="true" data-field="<?php echo $col; ?>">${row['<?php echo $col; ?>'] || ''}</td>`;
            }
        <?php endforeach; ?>

        // 4. Audio
        if (AUDIO_COL) {
            let file = row[AUDIO_COL];
            html += `<td data-label="Audio" class="force-visible">`;
            if (file) {
                html += `<audio controls preload="none" src="${file}"></audio><div style="font-size:0.75rem; color:var(--text-muted); word-break:break-all; margin-top:2px;">${file}</div>`;
            } else { html += `<span style="color:var(--text-muted); font-size:0.8rem;">-</span>`; }
            html += `</td>`;
        }
        html += `</tr>`;
    });
    return html;
}

function initSlides() {
    const search = $('#searchInput').val();
    $.post(SELF_URL, { action: 'fetch', search: search, page: 1, limit: rowsPerPage }, function(data) {
        data = JSON.parse(data);
        if(data.voiceOptions) globalVoiceOptions = data.voiceOptions;
        const firstSlide = $('#mainSwiper .swiper-slide').first();
        firstSlide.find('tbody').html(buildRowsHTML(data.rows));
        if(HAS_MAP) loadMapRunsForSlide(firstSlide);
        $('#mainSwiper .swiper-wrapper .swiper-slide').not(':first').remove();
        const header = $('#dataTable thead').prop('outerHTML');
        for (let p = 2; p <= data.totalPages; p++) {
             $('#mainSwiper .swiper-wrapper').append(`<div class="swiper-slide" data-page="${p}" data-loaded="0"><div class="slide-inner"><table>${header}<tbody></tbody></table></div></div>`);
        }
        if (swiper) swiper.destroy();
        swiper = new Swiper('#mainSwiper', { autoHeight: true, pagination: { el: '.swiper-pagination', clickable: true }, on: { slideChange: function() { loadPageData(this.activeIndex + 1); } } });
        if (sortableEnabled) enableSortable(firstSlide.find('tbody'));
    });
}

function loadPageData(page) {
    const slide = $(`.swiper-slide[data-page="${page}"]`);
    if (slide.attr('data-loaded') === '1') return;
    $.post(SELF_URL, { action: 'fetch', search: $('#searchInput').val(), page: page, limit: rowsPerPage }, function(data) {
        data = JSON.parse(data);
        slide.find('tbody').html(buildRowsHTML(data.rows));
        slide.attr('data-loaded', '1');
        if(HAS_MAP) loadMapRunsForSlide(slide);
        swiper.updateAutoHeight();
    });
}

function loadMapRunsForSlide(slide) {
    slide.find('.mapRunSelect').each(function() {
        const sel = $(this);
        $.post(SELF_URL, {action: 'fetchMapRuns', entity_id: sel.data('entity-id')}, function(res){
            let runs = []; try { runs = JSON.parse(res); } catch(e){}
            sel.empty().append('<option value="">Run...</option>');
            runs.forEach(r => {
                let s = (r.id == sel.data('active-id')) ? 'selected' : '';
                sel.append(`<option value="${r.id}" ${s}>${r.id}: ${r.note || r.created_at}</option>`);
            });
        });
    });
}

function ajaxReorder(direction) {
    $.post('/order_recalc.php?ajax=1', { entity: ENTITY, direction: direction, keepNonZero: 0 }, function(data) {
        if (data.success) { Toast.show(data.message, 'success'); initSlides(); } else { Toast.show(data.message, 'error'); }
    }, 'json');
}

$(document).ready(function() {
    initSlides();
    adjustHeaderSpacer();
    window.addEventListener('resize', adjustHeaderSpacer);

    $('#searchInput').on('keyup', function(e) { if(e.key==='Enter') initSlides(); });
    $('#sendSearchBtn').click(function() { initSlides(); });
    $('#resetSearchBtn').click(function() { $('#searchInput').val(''); initSlides(); });
    $('#addBtn').click(function() { $.post(SELF_URL, {action:'add'}, function(){ initSlides(); Toast.show('Added','success'); }); });
    $('#reorderAscBtn').click(() => ajaxReorder('ASC'));
    $('#reorderDescBtn').click(() => ajaxReorder('DESC'));

    // --- UPDATED SYNC BUTTON LOGIC ---
    $('#syncBtn').click(function() {
        const btn = $(this); 
        // Add visual loading state (Invert color & Spin icon)
        btn.prop('disabled', true).addClass('syncing');
        
        $.post(SELF_URL, {action: 'sync_models'}, function(data) {
            try { 
                const res = JSON.parse(data);
                if(res.success) { 
                    Toast.show(res.message, 'success'); 
                    initSlides(); 
                } else { 
                    Toast.show(res.message, 'error'); 
                }
            } catch(e) { 
                Toast.show('Sync error', 'error'); 
            }
            // Remove loading state (Text never changes, just class)
            btn.prop('disabled', false).removeClass('syncing');
        });
    });

    $(document).on('change', '.voice-select', function() {
        const el = $(this);
        $.post(SELF_URL, { action: 'update', id: el.closest('tr').data('id'), field: el.data('field'), value: el.val() }, function(res) {
            if(res === 'success') Toast.show('Voice updated', 'success'); else Toast.show('Error', 'error');
        });
    });

    $(document).on('click', '.micBtn', function() { recordingTargetId = $(this).closest('tr').data('id'); $('#recorderChoiceModal').css('display', 'flex'); });
    $('#recCancelBtn').click(() => { $('#recorderChoiceModal').hide(); recordingTargetId = null; });
    $('#recSourceBtn').click(() => { if(recordingTargetId) { window.showAudioRecorderModal(ENTITY, recordingTargetId, 1); $('#recorderChoiceModal').hide(); }});
    $('#recResultBtn').click(() => { if(recordingTargetId) { window.showAudioRecorderModal(ENTITY, recordingTargetId, 0); $('#recorderChoiceModal').hide(); }});

    $(document).on('change', '.regen-checkbox', function() {
        let el = $(this);
        $.post(SELF_URL, { action: 'update', id: el.closest('tr').data('id'), field: el.data('field'), value: el.is(':checked')?1:0 }, function(res) {
            if(res === 'success') Toast.show('Updated', 'success'); else { Toast.show('Error', 'error'); el.prop('checked', !el.is(':checked')); }
        });
    });

    $(document).on('change', '.mapRunSelect', function() {
        let sel = $(this);
        $.post(SELF_URL, { action: 'setActiveMapRun', entity_id: sel.data('entity-id'), map_run_id: sel.val() }, function(res) {
            if(res === 'success') Toast.show('Map Run Updated', 'success'); else Toast.show('Error', 'error');
        });
    });

    $(document).on('blur', '[contenteditable="true"]', function() {
        let el = $(this);
        $.post(SELF_URL, { action: 'update', id: el.closest('tr').data('id'), field: el.data('field'), value: el.text() }, function(res){
            if(res!=='success') Toast.show('Error saving','error');
        });
    });

    $(document).on('click', '.copyBtn', function() { if(confirm('Copy?')) $.post(SELF_URL, {action:'copy', id:$(this).closest('tr').data('id')}, function(){ initSlides(); Toast.show('Copied','success'); }); });
    $(document).on('click', '.delete', function() { if(confirm('Delete?')) $.post(SELF_URL, {action:'delete', id:$(this).closest('tr').data('id')}, function(){ initSlides(); Toast.show('Deleted','success'); }); });
    $(document).on('click', '.editBtn', function() { if(window.showEntityFormInModal) window.showEntityFormInModal(ENTITY, $(this).closest('tr').data('id')); });

    $('#toggleSortBtn').click(function() {
        sortableEnabled = !sortableEnabled;
        if(sortableEnabled) { $(this).addClass('btn-accent'); enableSortable($('.swiper-slide-active tbody')); }
        else { $(this).removeClass('btn-accent'); $('.ui-sortable').sortable('destroy'); }
    });
});

function enableSortable(tbody) {
    if(tbody.hasClass('ui-sortable')) tbody.sortable('destroy');
    tbody.sortable({ handle: '.dragHandle', update: function() {
        let o=[]; $(this).find('tr').each(function(i){ o.push({id:$(this).data('id'), order:i}); });
        $.post(SELF_URL, {action:'reorder', order:o}, function(){ Toast.show('Saved','success'); });
    }});
}
</script>
<?php require_once "forge_tool.php"; ?>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

<?php echo $eruda; ?>
<div id="toast-container"></div>
<script src="/js/toast.js"></script>
<script>
    $(document).ready(function() {
        $(document).on('click', '.runBtn', function() {
            let id = $(this).data('id');
            $.post('scheduler_view.php', {
                action: 'run_now',
                id: id
            }, function(res) {
                if (res === 'success') {
                    Toast.show('Task scheduled to run now!', 'success');
                } else {
                    Toast.show('Failed to trigger task', 'error');
                }
            });
        });
    });
</script>
</body>
</html>
