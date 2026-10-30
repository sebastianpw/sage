<?php
// public/cruzz.php
// CRUZZ — Generic entity SQL CRUD (clone/hybrid of sql_crud_sketches + enhanimaticism)
// ----------------------------------------------------
// Functional lineage from sql_crud_sketches.php:
//   fetch / update / delete / add / copy / regenerate / reorder — preserved,
//   adapted to be entity-generic. The 🕸️ "Details" button (fullscreen iframe
//   modal via modal_frame_details.php -> entity_form.php) is restored here too.
// Structural/visual lineage from enhanimaticism.php:
//   entity whitelist + switcher, economic dark UI, definite AJAX pagination widget
//   (prev / editable page index / next), fullscreen entity-form modal pattern.
// Map Runs (v2): repurposed as a READ-ONLY filter. Typing a map_run_id resolves
// frames.entity_id for that run (restricted to frames.entity_type = current
// entity) and filters the main list to those rows. Mismatched entity types or
// runs with no frames yield an empty result by design. "Set active map run" and
// the old per-row map-run dropdown/assignment UI have been removed entirely.
// Explicitly NOT carried over: frames grid, image thumbnails, remove_image,
// clipboard/compose/forge/STBA tooling, Swiper/PhotoSwipe.

require "e.php";
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require "entity_icons.php";

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// --- ENTITY WHITELIST & SELECTION (Enhanimaticism-style) ---
$allowedEntities = array_keys($entityIcons ?? []);
if (empty($allowedEntities)) {
    $allowedEntities = ['sketches'];
}

$entity = $_REQUEST['entity'] ?? 'sketches';
if (!in_array($entity, $allowedEntities, true)) {
    $entity = $allowedEntities[0];
}

// --- DISCOVERY & CONFIGURATION (per-entity, mirrors sql_crud_sketches.php) ---
function cruzz_discover(PDO $pdo, string $entity): array {
    $stmt = $pdo->query("SHOW COLUMNS FROM `$entity`");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $hasOrder = in_array('order', $columns);
    $hasName = in_array('name', $columns);
    $hasDescription = in_array('description', $columns);
    $regenCol = in_array('regenerate_images', $columns) ? 'regenerate_images' : (in_array('regenerate_videos', $columns) ? 'regenerate_videos' : (in_array('regenerate_audios', $columns) ? 'regenerate_audios' : (in_array('regenerate_meshes', $columns) ? 'regenerate_meshes' : (in_array('regenerate', $columns) ? 'regenerate' : null))));
    //$regenCol = in_array('regenerate_images', $columns) ? 'regenerate_images' : (in_array('regenerate_videos', $columns) ? 'regenerate_videos' : (in_array('regenerate_audios', $columns) ? 'regenerate_audios' : (in_array('regenerate', $columns) ? 'regenerate' : null)));
    $hasRegenerate = !empty($regenCol);
    $hasMapRun = in_array('active_map_run_id', $columns);

    return [
        'columns' => $columns,
        'hasOrder' => $hasOrder,
        'hasName' => $hasName,
        'hasDescription' => $hasDescription,
        'regenCol' => $regenCol,
        'hasRegenerate' => $hasRegenerate,
        'hasMapRun' => $hasMapRun,
    ];
}

$disco = cruzz_discover($pdo, $entity);
$columns = $disco['columns'];
$hasOrder = $disco['hasOrder'];
$hasName = $disco['hasName'];
$hasDescription = $disco['hasDescription'];
$regenCol = $disco['regenCol'];
$hasRegenerate = $disco['hasRegenerate'];
$hasMapRun = $disco['hasMapRun'];

// Icon Selection
$iconChar = $entityIcons[$entity] ?? '📦';

// Scheduler ID Lookup (kept — same lineage as sql_crud_sketches.php)
$schedulerId = $entitySchedulerIds[$entity] ?? null;

// --- HANDLE AJAX REQUESTS ---
if (isset($_POST['action']) || isset($_GET['action'])) {
    $action = $_POST['action'] ?? $_GET['action'];

    // Entity is re-validated per request (mirrors enhanimaticism_api.php pattern)
    $reqEntity = $_POST['entity'] ?? $_GET['entity'] ?? $entity;
    if (!in_array($reqEntity, $allowedEntities, true)) {
        $reqEntity = $entity;
    }
    if ($reqEntity !== $entity) {
        $entity = $reqEntity;
        $disco = cruzz_discover($pdo, $entity);
        $columns = $disco['columns'];
        $hasOrder = $disco['hasOrder'];
        $hasName = $disco['hasName'];
        $hasDescription = $disco['hasDescription'];
        $regenCol = $disco['regenCol'];
        $hasRegenerate = $disco['hasRegenerate'];
        $hasMapRun = $disco['hasMapRun'];
    }

    if ($action == 'fetch') {
        $search = $_POST['search'] ?? '';
        $mapRunId = isset($_POST['map_run_id']) ? trim((string)$_POST['map_run_id']) : '';
        $page   = (int)($_POST['page'] ?? 1);
        $limit  = (int)($_POST['limit'] ?? 10);
        if ($limit <= 0) $limit = 10;
        if ($page <= 0) $page = 1;
        $offset = ($page - 1) * $limit;

        $sqlSelect = "SELECT e.* FROM `$entity` e";
        $countSql  = "SELECT COUNT(*) FROM `$entity` e";

        // Filtering (preserved from sql_crud_sketches.php)
        $where = [];
        $params = [];
        if ($search !== '') {
            if ($hasName) {
                $where[] = "(e.id = :id OR e.name LIKE :search)";
                $params['id'] = (int)$search;
                $params['search'] = "%$search%";
            } else {
                $where[] = "e.id = :id";
                $params['id'] = (int)$search;
            }
        }

        // Map Run ID filter (new): resolve entity_ids that were used to generate
        // frames under this map_run_id, restricted to frames.entity_type = current entity.
        // If the map run belongs to a different entity type (or doesn't exist / has no
        // frames), this yields an empty result set by design (per spec).
        if ($mapRunId !== '') {
            $mrStmt = $pdo->prepare("SELECT DISTINCT entity_id FROM frames WHERE map_run_id = :mrid AND entity_type = :etype AND entity_id IS NOT NULL");
            $mrStmt->execute(['mrid' => (int)$mapRunId, 'etype' => $entity]);
            $mrIds = $mrStmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($mrIds)) {
                echo json_encode([
                    'rows' => [],
                    'totalRows' => 0,
                    'totalPages' => 1,
                    'currentPage' => 1
                ]);
                exit;
            }
            $inPlaceholders = [];
            foreach ($mrIds as $i => $mrEid) {
                $key = "mrid_$i";
                $inPlaceholders[] = ":$key";
                $params[$key] = (int)$mrEid;
            }
            $where[] = "e.id IN (" . implode(',', $inPlaceholders) . ")";
        }

        if (!empty($where)) {
            $clause = " WHERE " . implode(' AND ', $where);
            $sqlSelect .= $clause;
            $countSql  .= $clause;
        }

        // Sorting — CRUZZ default is id DESC regardless of an `order` column
        // (deliberate divergence from sql_crud_sketches.php, per spec)
        $sqlSelect .= " ORDER BY e.id DESC";

        $sqlSelect .= " LIMIT :limit OFFSET :offset";

        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRows = $countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $limit));

        $stmt = $pdo->prepare($sqlSelect);
        foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode([
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'totalRows' => (int)$totalRows,
            'totalPages' => $totalPages,
            'currentPage' => $page
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
        exit('success');
    }

    if ($action == 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM `$entity` WHERE id = :id");
        $stmt->execute(['id'=>$id]);
        exit('success');
    }

    if ($action == 'add') {
        $cols = [];
        $vals = [];
        $params = [];

        if ($hasName) {
            $uniqueName = "New " . ucfirst($entity) . " " . time();
            $cols[] = 'name';
            $vals[] = ':name';
            $params['name'] = $uniqueName;
        }

        if ($hasOrder) {
            $cols[] = '`order`';
            $vals[] = '0';
        }

        if (empty($cols)) {
            // No name/order columns to seed — insert a row relying entirely on column defaults.
            $stmt = $pdo->prepare("INSERT INTO `$entity` () VALUES ()");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("INSERT INTO `$entity` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")");
            $stmt->execute($params);
        }
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

    // NOTE: remove_image intentionally omitted — CRUZZ does not display frames/images.

    // NOTE: fetchMapRuns (per-row map-run listing/dropdown) intentionally removed
    // per spec. Map Run ID is now used purely as a filter on the main 'fetch'
    // action (see above), resolving frames -> entity_ids for the current entity.

    // NOTE: setActiveMapRun intentionally removed per spec — CRUZZ uses map_run_id
    // purely as a read-only lookup/filter (resolving frames -> entity_ids), not as
    // a per-row "active run" assignment.

    // Unknown action for this entity/config
    http_response_code(400);
    exit('Invalid action');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=0.7, maximum-scale=1, user-scalable=0">
<title>CRUZZ — <?php echo ucfirst($entity); ?></title>

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
<script src="/js/toast.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<?php require_once __DIR__ . '/modal_frame_details.php'; ?>

<style>
/* ══════════════════════════════════════════════════════
   CRUZZ — Economic dark styling, lineage: enhanimaticism.php
   ══════════════════════════════════════════════════════ */
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
html, body {
    margin: 0; background: var(--bg); color: var(--text);
    font-family: 'DM Mono', 'Fira Mono', monospace;
    height: 100%; overflow: hidden;
    width: 100%; max-width: 100%;
}
.cz-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }

/* Header */
.cz-header {
    flex-shrink: 0; padding: 0 12px; height: 50px;
    background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.cz-title { font-size: 0.95rem; font-weight: 700; letter-spacing: 1px; color: var(--text); display: flex; align-items: center; gap: 8px; min-width: 0; }
.cz-title .icon-link { font-size: 1.3rem; text-decoration: none; line-height: 1; flex-shrink: 0; }
.cz-title span.label { color: var(--purple); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cz-nav { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.cz-entity-select {
    background: var(--bg); border: 1px solid var(--border); color: var(--text);
    padding: 5px 8px; border-radius: 4px; font-family: inherit; font-size: 0.75rem; max-width: 140px;
}

/* Controls row (add / drag-sort / scheduler / map-run toggle) */
.cz-controls-row {
    display: flex; gap: 6px; padding: 8px 12px; border-bottom: 1px solid var(--border);
    align-items: center; background: var(--card); flex-wrap: wrap;
}
.cz-btn {
    padding: 5px 10px; border-radius: 20px; font-size: 0.68rem; font-weight: 700;
    border: 1px solid var(--border); background: transparent; color: var(--text-muted);
    cursor: pointer; transition: all 0.15s; text-transform: uppercase; letter-spacing: 0.4px;
    display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; font-family: inherit;
}
.cz-btn:hover { border-color: var(--purple); color: var(--purple); background: var(--purple-dim); }
.cz-btn.active { border-color: var(--teal); color: #000; background: var(--teal); }
.cz-btn.scheduler-link { text-decoration: none; font-size: 1.1rem; padding: 4px 8px; }

/* Search line */
.cz-search-line { display: flex; align-items: center; gap: 6px; padding: 8px 12px; border-bottom: 1px solid var(--border); background: var(--card); }
.cz-search-input {
    flex: 1; min-width: 0; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border);
    background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.8rem;
}
.cz-search-input:focus { outline: none; border-color: var(--purple); }

/* Pagination widget — definite pagination, AJAX, editable page index (lineage: mr-pagination) */
.cz-pagination-row {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 6px 12px; border-bottom: 1px solid var(--border); background: var(--card); flex-wrap: wrap;
}
.cz-page-info { font-size: 0.68rem; color: var(--text-muted); white-space: nowrap; }
.cz-pagination { display: flex; align-items: center; gap: 4px; }
.pg-btn { width: 28px; height: 28px; background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 3px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; font-family: inherit; }
.pg-btn:hover:not(:disabled) { border-color: var(--purple); color: var(--purple); }
.pg-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.pg-input { width: 46px; text-align: center; background: var(--bg); border: 1px solid var(--border); color: var(--purple); border-radius: 3px; font-family: inherit; font-size: 0.75rem; font-weight: 700; padding: 5px 0; -moz-appearance: textfield; }
.pg-total { font-size: 0.7rem; color: var(--text-muted); padding: 0 2px; white-space: nowrap; }
.cz-limit-select {
    background: var(--bg); border: 1px solid var(--border); color: var(--text-muted);
    padding: 4px 6px; border-radius: 4px; font-family: inherit; font-size: 0.68rem;
}

/* Map run panel (optional, collapsible, not default browse mode) */
.cz-maprun-panel {
    display: none; padding: 8px 12px; border-bottom: 1px solid var(--border);
    background: rgba(20, 184, 166, 0.05); flex-direction: column; gap: 6px;
}
.cz-maprun-panel.open { display: flex; }
.cz-maprun-row { display: flex; gap: 6px; align-items: center; }
.cz-maprun-select {
    flex: 1; padding: 5px 8px; font-size: 0.72rem; border: 1px solid var(--border);
    background: var(--bg); color: var(--text); border-radius: 4px; font-family: inherit;
}
.cz-maprun-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
.cz-maprun-hint { font-size: 0.65rem; color: var(--text-muted); font-style: italic; line-height: 1.4; }
.cz-maprun-active-badge {
    font-size: 0.62rem; color: #000; background: var(--teal); padding: 2px 8px;
    border-radius: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;
    display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;
}

/* Row list */
.cz-list-area { flex: 1; overflow-y: auto; padding: 8px; background: var(--bg); }
.cz-row-card {
    background: var(--card); border: 1px solid var(--border); border-radius: 8px;
    margin-bottom: 10px; padding: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}
.cz-row-head { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.cz-drag-handle { cursor: grab; color: var(--text-muted); font-size: 1rem; flex-shrink: 0; display: none; }
.cz-drag-handle.enabled { display: inline-block; }
.cz-row-id { font-family: monospace; font-size: 0.68rem; color: var(--text-muted); flex-shrink: 0; min-width: 32px; }
.cz-name-field {
    flex: 1; min-width: 0; font-weight: 600; padding: 4px 6px; border-radius: 4px;
    border: 1px solid transparent; background: transparent; color: var(--text); font-family: inherit; font-size: 0.85rem;
}
.cz-name-field:focus { outline: none; border-color: var(--purple); background: rgba(139,92,246,0.06); }
.cz-row-actions { display: flex; gap: 4px; flex-shrink: 0; flex-wrap: wrap; justify-content: flex-end; }
.cz-action-btn {
    width: 26px; height: 26px; padding: 0; display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid var(--border); background: transparent; color: var(--text-muted);
    border-radius: 4px; cursor: pointer; transition: all 0.15s; font-size: 0.8rem;
}
.cz-action-btn:hover { border-color: var(--purple); color: var(--purple); }
.cz-action-btn.delete:hover { border-color: var(--red); color: var(--red); }
.cz-regen-wrap {
    width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid var(--border); border-radius: 4px; background: transparent;
}
.cz-regen-checkbox { transform: scale(1.1); cursor: pointer; margin: 0; accent-color: var(--amber); }

/* Description — auto-expand on focus, inline AJAX auto-save (core new feature vs sql_crud_sketches) */
.cz-field-block { margin-bottom: 6px; }
.cz-field-label {
    font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.8px;
    color: var(--text-muted); margin-bottom: 3px; display: flex; align-items: center; justify-content: space-between;
}
.cz-save-indicator { font-size: 0.6rem; opacity: 0; transition: opacity 0.2s; }
.cz-save-indicator.show-saving { opacity: 1; color: var(--amber); }
.cz-save-indicator.show-saved  { opacity: 1; color: var(--teal); }
.cz-save-indicator.show-error  { opacity: 1; color: var(--red); }
.cz-desc-textarea {
    width: 100%; resize: none; overflow: hidden;
    background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 5px;
    color: var(--text); font-family: inherit; font-size: 0.8rem; line-height: 1.45;
    padding: 7px 9px; transition: border-color 0.15s, background 0.15s, min-height 0.15s ease;
    min-height: 40px; /* collapsed default — compact footprint per spec */
}
.cz-desc-textarea.is-secondary { min-height: 32px; font-size: 0.75rem; opacity: 0.9; }
.cz-desc-textarea:focus {
    outline: none; border-color: var(--amber); background: var(--bg);
    min-height: 140px; /* expand on focus */
}
.cz-desc-textarea::placeholder { color: var(--text-muted); font-style: italic; opacity: 0.6; }

.cz-state-msg { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 10px; color: var(--text-muted); font-size: 0.8rem; gap: 8px; }
.spinner { width: 20px; height: 20px; border: 2px solid var(--border); border-top-color: var(--text); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Sortable ghost */
.cz-row-card.ui-sortable-helper { box-shadow: 0 6px 18px rgba(0,0,0,0.5); }
.cz-list-area.ui-sortable .cz-row-card { }
</style>
</head>
<body>

<div class="cz-layout">
    <!-- Header -->
    <div class="cz-header">
        <div class="cz-title">
            <?php if ($hasMapRun): ?>
            <a href="view_map_runs_<?php echo htmlspecialchars($entity, ENT_QUOTES); ?>.php" class="icon-link" title="Gallery"><?php echo $iconChar; ?></a>
            <?php else: ?>
            <span class="icon-link"><?php echo $iconChar; ?></span>
            <?php endif; ?>
            <span class="label">CRUZZ</span>
        </div>
        <div class="cz-nav">
            <select id="entitySelect" class="cz-entity-select" onchange="selectEntity(this.value)">
                <?php foreach ($entityIcons as $ename => $icon): ?>
                    <option value="<?php echo htmlspecialchars($ename, ENT_QUOTES); ?>" <?php echo ($ename === $entity ? 'selected' : ''); ?>>
                        <?php echo htmlspecialchars($icon . ' ' . $ename, ENT_QUOTES); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Controls -->
    <div class="cz-controls-row">
        <button class="cz-btn" onclick="createEntity()"><i class="bi bi-plus-lg"></i> Add</button>

        <?php if ($schedulerId): ?>
        <a class="cz-btn scheduler-link runBtn" data-id="<?php echo (int)$schedulerId; ?>" title="Trigger Scheduler">🌀</a>
        <?php endif; ?>

        <?php if ($hasOrder): ?>
        <button class="cz-btn" id="toggleSortBtn" onclick="toggleDragSort()"><i class="bi bi-arrows-move"></i> Drag</button>
        <button class="cz-btn" onclick="ajaxReorder('ASC')" title="Sort A-Z (ID)"><i class="bi bi-sort-numeric-down"></i></button>
        <button class="cz-btn" onclick="ajaxReorder('DESC')" title="Sort Z-A (ID)"><i class="bi bi-sort-numeric-down-alt"></i></button>
        <?php endif; ?>

        <?php if ($hasMapRun): ?>
        <button class="cz-btn" id="toggleMapRunBtn" onclick="toggleMapRunPanel()"><i class="bi bi-diagram-3"></i> Map Runs</button>
        <?php endif; ?>
    </div>

    <!-- Search -->
    <div class="cz-search-line">
        <input type="text" id="searchInput" class="cz-search-input" placeholder="Search name or #id...">
        <button class="cz-btn" onclick="doSearch()"><i class="bi bi-search"></i></button>
        <button class="cz-btn" onclick="resetSearch()"><i class="bi bi-x-lg"></i></button>
    </div>

    <!-- Definite AJAX Pagination -->
    <div class="cz-pagination-row">
        <div class="cz-page-info" id="pageInfo">Loading…</div>
        <div class="cz-pagination">
            <select class="cz-limit-select" id="limitSelect" onchange="onLimitChange()">
                <option value="10">10/pg</option>
                <option value="25">25/pg</option>
                <option value="50">50/pg</option>
                <option value="100">100/pg</option>
            </select>
            <button class="pg-btn" id="pgPrev" onclick="changePage(-1)"><i class="bi bi-chevron-left"></i></button>
            <input type="number" class="pg-input" id="pgInput" value="1" min="1" onchange="jumpToPage()" onkeydown="if(event.key==='Enter'){jumpToPage();}">
            <span class="pg-total" id="pgTotal">/ 1</span>
            <button class="pg-btn" id="pgNext" onclick="changePage(1)"><i class="bi bi-chevron-right"></i></button>
        </div>
    </div>

    <!-- Map Run Panel (optional filter — NOT default browse mode) -->
    <?php if ($hasMapRun): ?>
    <div class="cz-maprun-panel" id="mapRunPanel">
        <div class="cz-maprun-row">
            <span class="cz-maprun-label">Map Run ID:</span>
            <input type="number" class="pg-input" id="mapRunIdFilter" placeholder="#run" style="width:80px;" onkeydown="if(event.key==='Enter'){applyMapRunFilter();}">
            <button class="cz-btn" onclick="applyMapRunFilter()"><i class="bi bi-funnel"></i> Filter</button>
            <button class="cz-btn" onclick="clearMapRunFilter()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="cz-maprun-hint" id="mapRunHint">Enter a Map Run ID to show only the <?php echo htmlspecialchars($entity, ENT_QUOTES); ?> rows whose flagged frames were generated under that run.</div>
    </div>
    <?php endif; ?>

    <!-- Row List -->
    <div class="cz-list-area" id="rowList">
        <div class="cz-state-msg"><div class="spinner"></div><div>Loading…</div></div>
    </div>
</div>

<script>
const ENTITY_ICONS = <?php echo json_encode($entityIcons); ?>;
let ENTITY = '<?php echo addslashes($entity); ?>';
let HAS_ORDER = <?php echo $hasOrder ? 'true' : 'false'; ?>;
let REGEN_COL = '<?php echo $regenCol ?: ""; ?>';
let HAS_REGEN = <?php echo $hasRegenerate ? 'true' : 'false'; ?>;
let HAS_MAP = <?php echo $hasMapRun ? 'true' : 'false'; ?>;
let HAS_NAME = <?php echo $hasName ? 'true' : 'false'; ?>;
// Composites-only extras (lineage: sql_crud_sketches.php's IS_COMPOSITE puzzleBtn),
// plus the new BANG! button — both open fullscreen iframe modals, shown only for
// this entity type, never rendered/empty for any other entity.
let IS_COMPOSITE = ENTITY === 'composites';
// Columns treated as "long text" description-style fields, rendered as auto-expand textareas.
// description is primary/most prominent; prompt_negative (if present) is shown secondary.
const DESC_FIELDS_PRIMARY = ['description'];
const DESC_FIELDS_SECONDARY = ['prompt_negative'];

let curPage = 1;
let totalPages = 1;
let rowsPerPage = 10;
let sortableEnabled = false;
let currentRowsData = [];
let saveDebounceTimers = {};

const LS_KEY = 'cruzz_nav';
function saveNav() { try { localStorage.setItem(LS_KEY, JSON.stringify({ entity: ENTITY, page: curPage, limit: rowsPerPage })); } catch(e) {} }
function loadNav() { try { return JSON.parse(localStorage.getItem(LS_KEY) || 'null'); } catch(e) { return null; } }

function esc(s) { return s === null || s === undefined ? '' : s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function selectEntity(newEntity) {
    ENTITY = newEntity;
    const saved = loadNav();
    rowsPerPage = (saved && saved.entity === newEntity && saved.limit) ? saved.limit : 10;
    document.getElementById('limitSelect').value = String(rowsPerPage);
    curPage = (saved && saved.entity === newEntity && saved.page > 1) ? saved.page : 1;
    document.getElementById('searchInput').value = '';
    // Re-fetch entity capabilities by reloading page with ?entity=... so PHP-side
    // discovery (hasOrder/hasMapRun/regenCol) stays authoritative and in sync with UI chrome.
    window.location.href = 'cruzz.php?entity=' + encodeURIComponent(newEntity);
}

function doSearch() { curPage = 1; loadList(1); }
function resetSearch() { document.getElementById('searchInput').value = ''; curPage = 1; loadList(1); }

document.getElementById('searchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
});

function onLimitChange() {
    rowsPerPage = parseInt(document.getElementById('limitSelect').value, 10) || 10;
    curPage = 1;
    loadList(1);
}

function changePage(delta) {
    const n = curPage + delta;
    if (n >= 1 && n <= totalPages) loadList(n);
}
function jumpToPage() {
    let v = parseInt(document.getElementById('pgInput').value, 10);
    if (isNaN(v)) v = 1;
    if (v < 1) v = 1;
    if (v > totalPages) v = totalPages;
    document.getElementById('pgInput').value = v;
    loadList(v);
}

function updatePaginationUI(data) {
    curPage = data.currentPage;
    totalPages = data.totalPages;
    document.getElementById('pgInput').value = curPage;
    document.getElementById('pgInput').max = totalPages;
    document.getElementById('pgTotal').textContent = '/ ' + totalPages;
    document.getElementById('pgPrev').disabled = curPage <= 1;
    document.getElementById('pgNext').disabled = curPage >= totalPages;
    let infoText = `${data.totalRows} total • page ${curPage} of ${totalPages}`;
    if (activeMapRunFilter) infoText += ` • Run #${activeMapRunFilter}`;
    document.getElementById('pageInfo').textContent = infoText;
    saveNav();
}

function loadList(page) {
    const list = document.getElementById('rowList');
    const search = document.getElementById('searchInput').value.trim();
    list.innerHTML = '<div class="cz-state-msg"><div class="spinner"></div><div>Loading…</div></div>';

    $.post('cruzz.php', { action: 'fetch', entity: ENTITY, search: search, map_run_id: activeMapRunFilter, page: page, limit: rowsPerPage }, function(data) {
        data = JSON.parse(data);
        currentRowsData = data.rows;
        updatePaginationUI(data);
        renderRows(data.rows);
    }).fail(function() {
        list.innerHTML = '<div class="cz-state-msg">Error loading data.</div>';
    });
}

function renderRows(rows) {
    const list = document.getElementById('rowList');
    list.innerHTML = '';
    if (!rows.length) {
        list.innerHTML = '<div class="cz-state-msg">No rows found.</div>';
        return;
    }
    rows.forEach(row => list.appendChild(buildRowCard(row)));
    if (sortableEnabled) enableSortable();
}

function buildRowCard(row) {
    const card = document.createElement('div');
    card.className = 'cz-row-card';
    card.dataset.id = row.id;

    // Head: drag handle, id, name, actions
    const head = document.createElement('div');
    head.className = 'cz-row-head';

    const drag = document.createElement('span');
    drag.className = 'cz-drag-handle' + (sortableEnabled ? ' enabled' : '');
    drag.innerHTML = '<i class="bi bi-grip-vertical"></i>';
    head.appendChild(drag);

    const idSpan = document.createElement('span');
    idSpan.className = 'cz-row-id';
    idSpan.textContent = '#' + row.id;
    head.appendChild(idSpan);

    const nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.className = 'cz-name-field';
    if (HAS_NAME) {
        nameInput.value = row.name || '';
        nameInput.dataset.field = 'name';
        nameInput.addEventListener('blur', () => saveField(row.id, 'name', nameInput.value, null));
        nameInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') nameInput.blur(); });
    } else {
        nameInput.value = '';
        nameInput.placeholder = '(no name column)';
        nameInput.disabled = true;
    }
    head.appendChild(nameInput);

    const actions = document.createElement('div');
    actions.className = 'cz-row-actions';

    if (HAS_REGEN) {
        const wrap = document.createElement('label');
        wrap.className = 'cz-regen-wrap';
        wrap.title = 'Regenerate?';
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.className = 'cz-regen-checkbox';
        cb.checked = parseInt(row[REGEN_COL]) === 1;
        cb.addEventListener('change', () => {
            $.post('cruzz.php', { action: 'update', entity: ENTITY, id: row.id, field: REGEN_COL, value: cb.checked ? 1 : 0 }, function(res) {
                if (res === 'success') Toast.show('Updated', 'success');
                else { Toast.show('Error', 'error'); cb.checked = !cb.checked; }
            });
        });
        wrap.appendChild(cb);
        actions.appendChild(wrap);
    }

    actions.appendChild(makeEmojiActionBtn('🕸️', 'Details', () => {
        openEntityModal(row.id);
    }));
    if (IS_COMPOSITE) {
        actions.appendChild(makeEmojiActionBtn('🧩', 'Multiplane', () => {
            if (window.showMultiplaneInModal) {
                window.showMultiplaneInModal(row.id);
            } else {
                Toast.show('Multiplane viewer not available on this page', 'error');
            }
        }));
        actions.appendChild(makeEmojiActionBtn('💥', 'BANG!', () => {
            openBangModal(row.id);
        }));
    }
    actions.appendChild(makeActionBtn('bi-files', 'Copy', () => {
        if (!confirm('Copy this row?')) return;
        $.post('cruzz.php', { action: 'copy', entity: ENTITY, id: row.id }, function() { Toast.show('Copied', 'success'); loadList(curPage); });
    }));
    actions.appendChild(makeActionBtn('bi-trash3', 'Delete', () => {
        if (!confirm('Delete this row?')) return;
        $.post('cruzz.php', { action: 'delete', entity: ENTITY, id: row.id }, function() { Toast.show('Deleted', 'success'); loadList(curPage); });
    }, 'delete'));

    head.appendChild(actions);
    card.appendChild(head);

    // Description-style fields — auto-expand-on-focus textareas w/ AJAX autosave
    DESC_FIELDS_PRIMARY.forEach(field => {
        if (row.hasOwnProperty(field)) card.appendChild(buildDescBlock(row, field, false));
    });
    DESC_FIELDS_SECONDARY.forEach(field => {
        if (row.hasOwnProperty(field)) card.appendChild(buildDescBlock(row, field, true));
    });

    return card;
}

function makeActionBtn(iconClass, title, onClick, extraClass) {
    const btn = document.createElement('button');
    btn.className = 'cz-action-btn' + (extraClass ? ' ' + extraClass : '');
    btn.title = title;
    btn.innerHTML = `<i class="bi ${iconClass}"></i>`;
    btn.addEventListener('click', onClick);
    return btn;
}

function makeEmojiActionBtn(emoji, title, onClick, extraClass) {
    const btn = document.createElement('button');
    btn.className = 'cz-action-btn' + (extraClass ? ' ' + extraClass : '');
    btn.title = title;
    btn.textContent = emoji;
    btn.addEventListener('click', onClick);
    return btn;
}

// Opens the fullscreen entity-form iframe modal (restored from sql_crud_sketches.php,
// using modal_frame_details.php's markup/JS — same pattern as enhanimaticism.php).
function openEntityModal(id) {
    const modal  = document.getElementById('frameDetailsModal');
    const iframe = document.getElementById('frameDetailsIframe');
    const loader = document.getElementById('ieLoadingOverlay');
    const pickerFooter = document.getElementById('iePickerFooter');
    const url = `entity_form.php?entity_type=${encodeURIComponent(ENTITY)}&entity_id=${id}`;
    if (modal && iframe) {
        if (pickerFooter) pickerFooter.style.display = 'none';
        if (loader) { loader.style.display = 'flex'; const p = loader.querySelector('p'); if (p) p.textContent = 'Loading...'; }
        iframe.style.opacity = '0';
        iframe.src = url;
        modal.style.display = 'flex';
    } else {
        // Fallback if modal markup isn't available for some reason
        window.open(url, '_blank');
    }
}

// Opens BANG! (composites-only) in the same fullscreen iframe modal.
function openBangModal(compositeId) {
    const modal  = document.getElementById('frameDetailsModal');
    const iframe = document.getElementById('frameDetailsIframe');
    const loader = document.getElementById('ieLoadingOverlay');
    const pickerFooter = document.getElementById('iePickerFooter');
    const url = `bang.php?composite_id=${compositeId}`;
    if (modal && iframe) {
        if (pickerFooter) pickerFooter.style.display = 'none';
        if (loader) { loader.style.display = 'flex'; const p = loader.querySelector('p'); if (p) p.textContent = 'Loading BANG!...'; }
        iframe.style.opacity = '0';
        iframe.src = url;
        modal.style.display = 'flex';
    } else {
        window.open(url, '_blank');
    }
}

function buildDescBlock(row, field, isSecondary) {
    const block = document.createElement('div');
    block.className = 'cz-field-block';

    const label = document.createElement('div');
    label.className = 'cz-field-label';
    const labelText = document.createElement('span');
    labelText.textContent = field.replace(/_/g, ' ');
    const indicator = document.createElement('span');
    indicator.className = 'cz-save-indicator';
    indicator.dataset.indicatorFor = field;
    label.appendChild(labelText);
    label.appendChild(indicator);
    block.appendChild(label);

    const ta = document.createElement('textarea');
    ta.className = 'cz-desc-textarea' + (isSecondary ? ' is-secondary' : '');
    ta.rows = 1;
    ta.dataset.field = field;
    ta.dataset.id = row.id;
    ta.placeholder = isSecondary ? (field.replace(/_/g,' ') + '...') : 'Description…';
    ta.value = row[field] || '';

    ta.addEventListener('input', () => {
        autoGrow(ta);
        debounceSave(row.id, field, ta.value, indicator);
    });
    ta.addEventListener('focus', () => autoGrow(ta));
    ta.addEventListener('blur', () => {
        // Ensure a final save fires immediately on blur (in addition to debounce),
        // so nothing is lost if the user navigates away quickly.
        clearTimeout(saveDebounceTimers[row.id + '_' + field]);
        saveField(row.id, field, ta.value, indicator);
    });

    block.appendChild(ta);
    // Defer autoGrow until attached to DOM (scrollHeight needs layout)
    requestAnimationFrame(() => autoGrow(ta));
    return block;
}

function autoGrow(ta) {
    ta.style.height = 'auto';
    const isFocused = document.activeElement === ta;
    const min = ta.classList.contains('is-secondary') ? 32 : 40;
    const target = Math.max(min, ta.scrollHeight);
    ta.style.height = (isFocused ? Math.max(target, 140) : Math.min(target, 90)) + 'px';
}

function debounceSave(id, field, value, indicator) {
    const key = id + '_' + field;
    clearTimeout(saveDebounceTimers[key]);
    if (indicator) { indicator.className = 'cz-save-indicator show-saving'; indicator.textContent = 'saving…'; }
    saveDebounceTimers[key] = setTimeout(() => saveField(id, field, value, indicator), 700);
}

function saveField(id, field, value, indicator) {
    if (indicator) { indicator.className = 'cz-save-indicator show-saving'; indicator.textContent = 'saving…'; }
    $.post('cruzz.php', { action: 'update', entity: ENTITY, id: id, field: field, value: value }, function(res) {
        if (res === 'success') {
            if (indicator) {
                indicator.className = 'cz-save-indicator show-saved';
                indicator.textContent = 'saved';
                setTimeout(() => { indicator.className = 'cz-save-indicator'; }, 1200);
            }
        } else {
            if (indicator) { indicator.className = 'cz-save-indicator show-error'; indicator.textContent = 'error'; }
            Toast.show('Error saving', 'error');
        }
    }).fail(function() {
        if (indicator) { indicator.className = 'cz-save-indicator show-error'; indicator.textContent = 'error'; }
        Toast.show('Network error saving', 'error');
    });
}

function createEntity() {
    $.post('cruzz.php', { action: 'add', entity: ENTITY }, function() {
        Toast.show('Added', 'success');
        curPage = 1;
        loadList(1);
    });
}

function ajaxReorder(direction) {
    $.post('/order_recalc.php?ajax=1', { entity: ENTITY, direction: direction, keepNonZero: 0 }, function(data) {
        if (data.success) { Toast.show(data.message, 'success'); loadList(curPage); }
        else { Toast.show(data.message, 'error'); }
    }, 'json');
}

function toggleDragSort() {
    sortableEnabled = !sortableEnabled;
    const btn = document.getElementById('toggleSortBtn');
    btn.classList.toggle('active', sortableEnabled);
    document.querySelectorAll('.cz-drag-handle').forEach(h => h.classList.toggle('enabled', sortableEnabled));
    if (sortableEnabled) enableSortable();
    else { const list = $('#rowList'); if (list.hasClass('ui-sortable')) list.sortable('destroy'); }
}

function enableSortable() {
    const list = $('#rowList');
    if (list.hasClass('ui-sortable')) list.sortable('destroy');
    list.sortable({
        handle: '.cz-drag-handle',
        update: function() {
            let o = [];
            $(this).find('.cz-row-card').each(function(i) { o.push({ id: $(this).data('id'), order: i }); });
            $.post('cruzz.php', { action: 'reorder', entity: ENTITY, order: o }, function() { Toast.show('Order saved', 'success'); });
        }
    });
}

// ── Map Run filter panel (optional, collapsed by default — not the primary browse mode) ──
// Resolves a map_run_id -> the entity rows whose flagged frames were generated
// under that run (restricted to the current entity type). Combines (AND) with
// the text search box. Mismatched entity type or a run with no frames => empty list.
let activeMapRunFilter = '';

function toggleMapRunPanel() {
    const panel = document.getElementById('mapRunPanel');
    const btn = document.getElementById('toggleMapRunBtn');
    panel.classList.toggle('open');
    btn.classList.toggle('active', panel.classList.contains('open'));
}

function applyMapRunFilter() {
    const val = document.getElementById('mapRunIdFilter').value.trim();
    if (!val) { clearMapRunFilter(); return; }
    activeMapRunFilter = val;
    curPage = 1;
    loadList(1);
}

function clearMapRunFilter() {
    activeMapRunFilter = '';
    document.getElementById('mapRunIdFilter').value = '';
    curPage = 1;
    loadList(1);
}

// ── Init ──
$(document).ready(function() {
    const saved = loadNav();
    if (saved && saved.entity === ENTITY) {
        rowsPerPage = saved.limit || 10;
        curPage = saved.page || 1;
    }
    document.getElementById('limitSelect').value = String(rowsPerPage);
    loadList(curPage);

    $(document).on('click', '.runBtn', function() {
        let id = $(this).data('id');
        $.post('scheduler_view.php', { action: 'run_now', id: id }, function(res) {
            if (res === 'success') Toast.show('Task scheduled to run now!', 'success');
            else Toast.show('Failed to trigger task', 'error');
        });
    });
});
</script>
<?php require_once "forge_tool.php"; ?>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>
