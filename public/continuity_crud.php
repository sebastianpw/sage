<?php
// public/continuity_crud.php
// Continuity Entity CRUD, Mobile-First UI, Multi-Format Ingestion, & Continuity Reverse-Export Tool
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

// ── CONFIGURABLE EXPORT COLUMNS CONSTANT ──────────────────────────────────
if (!defined('CONTINUITY_EXPORT_COLUMNS')) {
    define('CONTINUITY_EXPORT_COLUMNS', ['id', 'name', 'description', 'img2img', 'img2img_frame_id']);
}

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// Ensure continuity_entities table exists
$pdo->exec("
CREATE TABLE IF NOT EXISTS continuity_entities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tag_name VARCHAR(50) UNIQUE NOT NULL,
    canonical_name VARCHAR(100) DEFAULT NULL,
    entity_type VARCHAR(50) DEFAULT 'character',
    description TEXT NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

try {
    $pdo->exec("ALTER TABLE continuity_entities ADD COLUMN canonical_name VARCHAR(100) DEFAULT NULL AFTER tag_name");
} catch (Exception $e) { /* Column already exists */ }

try {
    $pdo->exec("ALTER TABLE continuity_entities ADD COLUMN img2img_frame_id INT DEFAULT NULL AFTER active");
} catch (Exception $e) { /* Column already exists */ }

// Ensure sketch_frames table exists
$pdo->exec("
CREATE TABLE IF NOT EXISTS sketch_frames (
  sketch_id INT(11) NOT NULL,
  frame_id INT(11) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (sketch_id, frame_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// ── AJAX BACKEND ACTIONS ───────────────────────────────────────────────────
if (isset($_REQUEST['action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['action'];

    // 1. Fetch Continuity Entities
    if ($action === 'fetch') {
        $search = trim($_POST['search'] ?? '');
        $typeFilter = trim($_POST['type_filter'] ?? '');
        $prefixFilter = trim($_POST['prefix_filter'] ?? '');
        $page = max(1, (int)($_POST['page'] ?? 1));
        $limit = max(1, (int)($_POST['limit'] ?? 15));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = "(tag_name LIKE :search OR canonical_name LIKE :search OR description LIKE :search)";
            $params['search'] = "%$search%";
        }
        if ($typeFilter !== '') {
            $where[] = "entity_type = :etype";
            $params['etype'] = $typeFilter;
        }
        if ($prefixFilter !== '') {
            $where[] = "tag_name LIKE :prefix_pattern";
            $params['prefix_pattern'] = $prefixFilter . "_%";
        }

        $clause = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";
        
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM continuity_entities $clause");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM continuity_entities $clause ORDER BY id DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode([
            'ok' => true,
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'totalRows' => $totalRows,
            'totalPages' => max(1, ceil($totalRows / $limit)),
            'currentPage' => $page
        ]);
        exit;
    }

    // 2. Single Field Inline Update
    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $field = $_POST['field'];
        $value = $_POST['value'];
        $allowed = ['tag_name', 'canonical_name', 'entity_type', 'description', 'active', 'img2img_frame_id'];

        if (!in_array($field, $allowed, true)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid field']);
            exit;
        }

        if ($field === 'tag_name') {
            $value = strtoupper(preg_replace('/[^a-zA-Z0-9_]/', '', $value));
        }

        if ($field === 'img2img_frame_id' && $value === '') {
            $value = null;
        }

        $stmt = $pdo->prepare("UPDATE continuity_entities SET `$field` = :val WHERE id = :id");
        $stmt->execute(['val' => $value, 'id' => $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // 3. Add Single Blank Continuity Entity
    if ($action === 'add') {
        $uniqueTag = "NEW_TAG_" . time();
        $stmt = $pdo->prepare("INSERT INTO continuity_entities (tag_name, canonical_name, entity_type, description) VALUES (:tag, 'New Entity', 'character', '')");
        $stmt->execute(['tag' => $uniqueTag]);
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    // 4. Delete Continuity Entity Tag
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM continuity_entities WHERE id = :id");
        $stmt->execute(['id' => $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    
       // 5. Ingest AI Sketches JSON & Expand (Supports Dual-Payload Formats)
    if ($action === 'ingest_sketches') {
        $rawSketchesJson = $_POST['sketches_json'] ?? '';
        $inheritFrames = filter_var($_POST['inherit_frames'] ?? 'true', FILTER_VALIDATE_BOOLEAN); // <-- NEW CHECKBOX CAPTURE
        $data = json_decode($rawSketchesJson, true);

        if (!is_array($data)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid Sketches JSON syntax']);
            exit;
        }

        require_once PROJECT_ROOT . '/src/Service/ContinuityEngine.php';
        $engine = new \App\Service\ContinuityEngine();

        try {
            $stats = $engine->ingestPayload($data, $inheritFrames); // <-- PASSED TO ENGINE
            $insertedCount = $stats['sketches_inserted'];
            $ingestedTagCount = $stats['tags_inserted'];

            $tagMsg = $ingestedTagCount > 0 ? " ($ingestedTagCount new entity tags saved)" : "";
            echo json_encode([
                'ok' => true,
                'message' => "Successfully ingested $insertedCount sketch(es)$tagMsg into `sketches` table!"
            ]);
            exit;
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    
    // ── SEARCH ENDPOINTS FOR EXPORT TOOL ──────────────────────────────────
    if ($action === 'search_export_sketches') {
        $q = trim($_POST['q'] ?? '');
        $stmt = is_numeric($q) 
            ? $pdo->prepare("SELECT id, name FROM sketches WHERE id = ? OR name LIKE ? ORDER BY id DESC LIMIT 20")
            : $pdo->prepare("SELECT id, name FROM sketches WHERE name LIKE ? ORDER BY id DESC LIMIT 20");
        $stmt->execute(is_numeric($q) ? [(int)$q, "%$q%"] : ["%$q%"]);
        echo json_encode(['ok' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'search_export_mapruns') {
        $q = trim($_POST['q'] ?? '');
        $stmt = is_numeric($q)
            ? $pdo->prepare("SELECT id, note, created_at FROM map_runs WHERE entity_type='sketches' AND (id = ? OR note LIKE ?) ORDER BY id DESC LIMIT 20")
            : $pdo->prepare("SELECT id, note, created_at FROM map_runs WHERE entity_type='sketches' AND (note LIKE ? OR id LIKE ?) ORDER BY id DESC LIMIT 20");
        $stmt->execute(is_numeric($q) ? [(int)$q, "%$q%"] : ["%$q%", "%$q%"]);
        echo json_encode(['ok' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'search_export_sequences') {
        $q = trim($_POST['q'] ?? '');
        $stmt = is_numeric($q)
            ? $pdo->prepare("SELECT id, name FROM narrative_sequences WHERE id = ? OR name LIKE ? ORDER BY id DESC LIMIT 20")
            : $pdo->prepare("SELECT id, name FROM narrative_sequences WHERE name LIKE ? ORDER BY id DESC LIMIT 20");
        $stmt->execute(is_numeric($q) ? [(int)$q, "%$q%"] : ["%$q%"]);
        echo json_encode(['ok' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── REVERSE CONTINUITY EXPORT ENDPOINT ────────────────────────────────
    if ($action === 'generate_continuity_export') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $sketchIds = array_map('intval', $input['sketch_ids'] ?? []);
        $ranges = $input['ranges'] ?? [];
        $mapRunIds = array_map('intval', $input['map_run_ids'] ?? []);
        $sequenceIds = array_map('intval', $input['sequence_ids'] ?? []);

        $finalSketchIds = [];
        foreach ($sketchIds as $sid) if ($sid > 0) $finalSketchIds[$sid] = true;

        foreach ($ranges as $r) {
            $st = (int)($r['start'] ?? 0);
            $en = (int)($r['end'] ?? 0);
            if ($st > 0 && $en >= $st) {
                $stmt = $pdo->prepare("SELECT id FROM sketches WHERE id BETWEEN ? AND ?");
                $stmt->execute([$st, $en]);
                while ($id = $stmt->fetchColumn()) $finalSketchIds[(int)$id] = true;
            }
        }

        foreach ($mapRunIds as $mId) {
            if ($mId > 0) {
                $stmt = $pdo->prepare("SELECT DISTINCT entity_id FROM frames WHERE map_run_id = ? AND entity_type = 'sketches'");
                $stmt->execute([$mId]);
                while ($id = $stmt->fetchColumn()) if ($id) $finalSketchIds[(int)$id] = true;

                $stmt2 = $pdo->prepare("SELECT DISTINCT f2s.to_id FROM frames f JOIN frames_2_sketches f2s ON f2s.from_id = f.id WHERE f.map_run_id = ?");
                $stmt2->execute([$mId]);
                while ($id = $stmt2->fetchColumn()) if ($id) $finalSketchIds[(int)$id] = true;
            }
        }

        foreach ($sequenceIds as $seqId) {
            if ($seqId > 0) {
                $stmtSeq = $pdo->prepare("SELECT sequence_data FROM narrative_sequences WHERE id = ?");
                $stmtSeq->execute([$seqId]);
                $seqJson = $stmtSeq->fetchColumn();
                if ($seqJson) {
                    $seqData = json_decode($seqJson, true);
                    if (is_array($seqData)) {
                        array_walk_recursive($seqData, function($val, $key) use (&$finalSketchIds) {
                            if (($key === 'entity_id' || $key === 'sketch_id' || $key === 'id') && is_numeric($val)) {
                                $finalSketchIds[(int)$val] = true;
                            }
                        });
                    }
                }
            }
        }

        $allSketchIds = array_keys($finalSketchIds);
        sort($allSketchIds);

        if (empty($allSketchIds)) {
            echo json_encode(['ok' => false, 'error' => 'No matching sketches found for export criteria']);
            exit;
        }

        $cols = CONTINUITY_EXPORT_COLUMNS;
        if (!in_array('id', $cols, true)) $cols[] = 'id';
        if (!in_array('name', $cols, true)) $cols[] = 'name';
        if (!in_array('description_raw', $cols, true)) $cols[] = 'description_raw';
        if (!in_array('description', $cols, true)) $cols[] = 'description';

        $colsSql = implode(', ', array_map(fn($c) => "`$c`", array_unique($cols)));
        $inSk = implode(',', array_fill(0, count($allSketchIds), '?'));
        
        $stmtSk = $pdo->prepare("SELECT $colsSql FROM sketches WHERE id IN ($inSk) ORDER BY id ASC");
        $stmtSk->execute($allSketchIds);
        $rawRows = $stmtSk->fetchAll(PDO::FETCH_ASSOC);

        $usedTagsMap = [];
        $exportedSketches = [];

        foreach ($rawRows as $row) {
            $item = [];
            $exportDesc = !empty($row['description_raw']) ? $row['description_raw'] : ($row['description'] ?? '');

            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $exportDesc, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $tagMatched) {
                    $usedTagsMap[strtoupper($tagMatched)] = true;
                }
            }

            foreach (CONTINUITY_EXPORT_COLUMNS as $colKey) {
                if ($colKey === 'description') {
                    $item['description'] = $exportDesc;
                } else {
                    $item[$colKey] = $row[$colKey] ?? null;
                }
            }
            $exportedSketches[] = $item;
        }

        $usedEntities = [];
        if (!empty($usedTagsMap)) {
            $tagsList = array_keys($usedTagsMap);
            $inTags = implode(',', array_fill(0, count($tagsList), '?'));
            $stmtEnt = $pdo->prepare("SELECT tag_name, canonical_name, entity_type, description FROM continuity_entities WHERE UPPER(tag_name) IN ($inTags)");
            $stmtEnt->execute($tagsList);
            foreach ($stmtEnt->fetchAll(PDO::FETCH_ASSOC) as $e) {
                $usedEntities[strtoupper($e['tag_name'])] = [
                    'canonical_name' => $e['canonical_name'],
                    'entity_type'    => $e['entity_type'],
                    'description'    => $e['description']
                ];
            }
        }

        $exportPayload = [
            'export_meta' => [
                'generated_at'   => date('c'),
                'total_sketches' => count($exportedSketches),
                'used_tags_count'=> count($usedEntities),
                'used_tags'      => array_keys($usedEntities)
            ],
            'used_entities' => $usedEntities,
            'sketches'      => $exportedSketches
        ];

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="continuity_sketches_export_' . date('Ymd_His') . '.json"');
        echo json_encode($exportPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    exit;
}

// Auto-discover distinct Series/Season prefixes
$prefixRows = $pdo->query("
    SELECT DISTINCT SUBSTRING_INDEX(tag_name, '_', 1) AS prefix 
    FROM continuity_entities 
    WHERE tag_name LIKE '%\\_%' 
    ORDER BY prefix ASC
")->fetchAll(PDO::FETCH_COLUMN);

$typeRows = $pdo->query("SELECT DISTINCT entity_type FROM continuity_entities ORDER BY entity_type ASC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Continuity & Sketch Manager</title>

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

<style>
:root {
    --bg: #0a0a0f;
    --card: #111118;
    --border: #1e1e2e;
    --text: #e2e2f0;
    --text-muted: #555570;
    --purple: #8b5cf6;
    --purple-dim: rgba(139, 92, 246, 0.1);
    --amber: #f59e0b;
    --teal: #14b8a6;
    --red: #ef4444;
    --code-bg: #050508;
    --code-text: #a6e22e;
}

[data-theme="light"] {
    --bg: #f4f4f8;
    --card: #ffffff;
    --border: #d0d0e0;
    --text: #1a2233;
    --text-muted: #777788;
    --purple: #7c3aed;
    --purple-dim: rgba(124, 58, 237, 0.08);
    --amber: #d97706;
    --teal: #0d9488;
    --red: #dc2626;
    --code-bg: #f8f8fc;
    --code-text: #116b00;
}

*, *::before, *::after { box-sizing: border-box; }
body {
    margin: 0; background: var(--bg); color: var(--text);
    font-family: 'DM Mono', 'Fira Mono', monospace;
    height: 100vh; height: 100dvh; display: flex; flex-direction: column; overflow: hidden;
    transition: background 0.15s, color 0.15s;
}

/* ── Mobile-First Header Layout ── */
.cz-header {
    padding: 8px 12px; background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; flex-direction: column; gap: 8px; flex-shrink: 0;
}
.cz-header-top {
    display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;
}
.cz-title { font-size: 0.9rem; font-weight: 700; color: var(--purple); display: flex; align-items: center; gap: 6px; }
.cz-actions { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }

.cz-btn {
    padding: 6px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 700;
    border: 1px solid var(--border); background: transparent; color: var(--text-muted);
    cursor: pointer; transition: all 0.15s; font-family: inherit; display: inline-flex; align-items: center; gap: 5px;
    white-space: nowrap;
}
.cz-btn:hover { border-color: var(--purple); color: var(--purple); background: var(--purple-dim); }
.cz-btn.primary { border-color: var(--purple); color: #fff; background: var(--purple); }
.cz-btn.teal { border-color: var(--teal); color: var(--teal); }
.cz-btn.teal:hover { background: rgba(20,184,166,0.1); }

/* ── Touch-Scrollable Prefix Pills Bar ── */
.cz-pills-row {
    display: flex; align-items: center; gap: 6px; overflow-x: auto; 
    padding-bottom: 2px; scrollbar-width: none; -webkit-overflow-scrolling: touch;
}
.cz-pills-row::-webkit-scrollbar { display: none; }
.cz-pill {
    padding: 4px 10px; border-radius: 12px; font-size: 0.68rem; font-weight: 700;
    border: 1px solid var(--border); background: var(--bg); color: var(--text-muted);
    cursor: pointer; transition: all 0.15s; font-family: inherit; flex-shrink: 0;
}
.cz-pill:hover { border-color: var(--teal); color: var(--teal); }
.cz-pill.active { border-color: var(--teal); background: var(--teal); color: #000; }

.cz-filter-line {
    display: flex; gap: 6px; padding: 8px 12px; background: var(--card);
    border-bottom: 1px solid var(--border); align-items: center; flex-shrink: 0;
}
.cz-search-input, .cz-select {
    padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border);
    background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.8rem;
}

.cz-list { flex: 1; overflow-y: auto; padding: 12px; }
.cz-card {
    background: var(--card); border: 1px solid var(--border); border-radius: 8px;
    padding: 10px 12px; margin-bottom: 10px; display: flex; flex-direction: column; gap: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.cz-card-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.cz-tag-input {
    font-weight: 700; color: var(--amber); border: 1px solid transparent;
    background: transparent; font-family: inherit; font-size: 0.85rem; padding: 4px 6px; border-radius: 4px;
}
.cz-tag-input:focus { outline: none; border-color: var(--amber); background: var(--bg); }

.cz-cname-input {
    font-weight: 600; color: var(--text); border: 1px solid transparent;
    background: transparent; font-family: inherit; font-size: 0.85rem; padding: 4px 6px; border-radius: 4px; width: 130px;
}
.cz-cname-input:focus { outline: none; border-color: var(--purple); background: var(--bg); }

.cz-type-select { font-size: 0.72rem; color: var(--teal); background: var(--bg); border: 1px solid var(--border); border-radius: 4px; padding: 2px 6px; }

.cz-desc-textarea {
    width: 100%; min-height: 40px; resize: none; overflow: hidden;
    background: rgba(0,0,0,0.03); border: 1px solid var(--border); border-radius: 5px;
    color: var(--text); font-family: inherit; font-size: 0.8rem; line-height: 1.4; padding: 8px;
    transition: all 0.15s;
}
.cz-desc-textarea:focus { outline: none; border-color: var(--purple); background: var(--bg); min-height: 100px; }

.cz-pagination-footer {
    padding: 8px 12px; background: var(--card); border-top: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    flex-shrink: 0; font-size: 0.72rem; flex-wrap: wrap;
}
.cz-page-info { color: var(--text-muted); font-size: 0.68rem; white-space: nowrap; }
.cz-pagination-controls { display: flex; align-items: center; gap: 4px; }
.pg-btn {
    padding: 4px 10px; background: transparent; border: 1px solid var(--border);
    color: var(--text-muted); border-radius: 4px; cursor: pointer; font-family: inherit;
    display: inline-flex; align-items: center; justify-content: center;
}
.pg-btn:hover:not(:disabled) { border-color: var(--purple); color: var(--purple); }
.pg-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.pg-input {
    width: 48px; text-align: center; background: var(--bg); border: 1px solid var(--border);
    color: var(--purple); border-radius: 4px; font-family: inherit; font-size: 0.75rem;
    font-weight: 700; padding: 4px 0; -moz-appearance: textfield;
}

.cz-modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.75); display: none;
    align-items: center; justify-content: center; z-index: 1000; padding: 12px;
}
.cz-modal {
    background: var(--card); border: 1px solid var(--border); border-radius: 8px;
    width: 100%; max-width: 750px; max-height: 90vh; padding: 16px; display: flex; flex-direction: column; gap: 10px; overflow-y: auto;
}
.cz-json-textarea {
    width: 100%; background: var(--code-bg); border: 1px solid var(--border);
    border-radius: 4px; font-family: inherit; font-size: 0.75rem; padding: 8px; resize: vertical;
}

.ex-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.ex-chip {
    padding: 3px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 700;
    border: 1px solid var(--teal); background: rgba(20,184,166,0.1); color: var(--teal);
    display: inline-flex; align-items: center; gap: 4px;
}
.ex-chip-remove { cursor: pointer; opacity: 0.7; }
.ex-chip-remove:hover { opacity: 1; color: var(--red); }
.ex-ac-wrap { position: relative; margin-bottom: 8px; }
.ex-ac-dropdown {
    position: absolute; top: 100%; left: 0; right: 0; z-index: 100; background: var(--card);
    border: 1px solid var(--border); border-radius: 0 0 4px 4px; max-height: 160px; overflow-y: auto; display: none;
}
.ex-ac-item { padding: 6px 10px; font-size: 0.75rem; cursor: pointer; border-bottom: 1px solid var(--border); }
.ex-ac-item:hover { background: var(--purple-dim); color: var(--purple); }
</style>
</head>
<body>

<!-- Header Top Area -->
<div class="cz-header">
    <div class="cz-header-top">
        <div class="cz-title">🏷️ CONTINUITY REGISTRY</div>
        <div class="cz-actions">
            <button class="cz-btn" title="Toggle Theme" onclick="toggleTheme()"><i class="bi bi-brightness-high"></i></button>
            <button class="cz-btn teal" onclick="openExportModal()"><i class="bi bi-download"></i> Reverse Export</button>
            <button class="cz-btn primary" onclick="openSketchIngestModal()"><i class="bi bi-file-earmark-arrow-up"></i> Drop AI Sketches JSON</button>
            <button class="cz-btn" onclick="addEntity()"><i class="bi bi-plus-lg"></i> Add</button>
        </div>
    </div>

    <!-- Scrollable Prefix Bar -->
    <div class="cz-pills-row">
        <button class="cz-pill active" data-prefix="" onclick="selectPrefix('')">ALL PREFIXES</button>
        <?php foreach ($prefixRows as $p): ?>
            <button class="cz-pill" data-prefix="<?php echo htmlspecialchars($p); ?>" onclick="selectPrefix('<?php echo htmlspecialchars($p); ?>')">
                <?php echo htmlspecialchars($p); ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- Search & Filter Area -->
<div class="cz-filter-line">
    <input type="text" id="searchInput" class="cz-search-input" placeholder="Search tags, names, descriptions..." style="flex:1; min-width:140px;" onkeydown="if(event.key==='Enter') loadList(1)">
    <select id="typeFilter" class="cz-select" onchange="loadList(1)">
        <option value="">All Types</option>
        <?php foreach ($typeRows as $t): ?>
            <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars(ucfirst($t)); ?></option>
        <?php endforeach; ?>
    </select>
    <button class="cz-btn" onclick="loadList(1)"><i class="bi bi-search"></i></button>
</div>

<!-- Card List Area -->
<div class="cz-list" id="rowList">Loading...</div>

<!-- Pagination Footer -->
<div class="cz-pagination-footer">
    <div class="cz-page-info" id="pageInfo">0 tags</div>
    <div class="cz-pagination-controls">
        <button class="pg-btn" id="pgPrev" onclick="changePage(-1)"><i class="bi bi-chevron-left"></i></button>
        <input type="number" class="pg-input" id="pgInput" value="1" min="1" onchange="jumpToPage()" onkeydown="if(event.key==='Enter'){jumpToPage();}">
        <span style="color:var(--text-muted); font-size:0.7rem;" id="pgTotal">/ 1</span>
        <button class="pg-btn" id="pgNext" onclick="changePage(1)"><i class="bi bi-chevron-right"></i></button>
    </div>
</div>

<!-- Sketch Continuity Ingestion Modal -->
<div class="cz-modal-overlay" id="sketchIngestModal">
    <div class="cz-modal">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:8px;">
            <span style="font-size:0.9rem; font-weight:700; color:var(--purple);">🚀 INGEST AI SKETCHES WITH CONTINUITY</span>
            <i class="bi bi-x-lg" style="cursor:pointer; color:var(--text-muted);" onclick="closeSketchIngestModal()"></i>
        </div>

        <div>
            <label style="font-size:0.68rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; display:block; margin-bottom:4px;">
                AI Sketches Payload JSON (Supports flat arrays or payloads with "continuity_tags_new" + "sketches"):
            </label>
            <textarea id="sketchJsonInput" class="cz-json-textarea" style="height:230px; color:var(--code-text);" 
                placeholder='{\n  "continuity_tags_new": {\n    "DP1_SKY_CARRIER": "an enormous extraterrestrial Architect sky carrier..."\n  },\n  "sketches": [\n    {\n      "name": "DP2 EP01 SCN01_The_Birthday_Candle",\n      "description": "Anime comic book panel scene... {DP1_SKY_CARRIER} passes overhead..."\n    }\n  ]\n}' 
                oninput="validateSketchJSON()"></textarea>
            <div id="sketchJsonStatus" style="font-size:0.7rem; font-weight:700; padding:4px 8px; margin-top:4px; border-radius:4px; background:rgba(239,68,68,0.15); color:var(--red); border:1px solid var(--red);">
                Waiting for JSON input...
            </div>
            
            <div style="margin-top:12px; padding:8px; background:var(--card); border:1px solid var(--border); border-radius:6px;">
                <label style="display:flex; align-items:center; gap:8px; font-size:0.75rem; color:var(--text); cursor:pointer; font-weight:700; margin:0;">
                    <input type="checkbox" id="chkInheritFrames" checked style="width:16px; height:16px; accent-color:var(--purple);">
                    Auto-inherit character reference frames from {TAGS}
                </label>
                <div style="font-size:0.65rem; color:var(--text-muted); margin-top:4px; padding-left:24px;">
                    Uncheck to use a single global sketch reference (saves API costs). Sketch-level img2img IDs will still apply.
                </div>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:8px;">
            <button class="cz-btn" onclick="closeSketchIngestModal()">Cancel</button>
            <button class="cz-btn primary" id="btnSubmitSketchIngest" onclick="submitSketchIngest()" disabled>
                <i class="bi bi-magic"></i> Expand & Ingest Sketches
            </button>
        </div>
    </div>
</div>

<!-- Reverse Continuity Export Overlay Modal -->
<div class="cz-modal-overlay" id="exportModal">
    <div class="cz-modal">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:8px;">
            <span style="font-size:0.9rem; font-weight:700; color:var(--teal);">📤 REVERSE CONTINUITY SKETCH EXPORT</span>
            <i class="bi bi-x-lg" style="cursor:pointer; color:var(--text-muted);" onclick="closeExportModal()"></i>
        </div>

        <p style="font-size:0.72rem; color:var(--text-muted); margin:0;">
            Export sketches while preserving unexpanded <code>{TAGS}</code> and listing all referenced entity definitions at the top of the JSON payload.
        </p>

        <!-- 1. Specific Sketches Autocomplete & Ranges -->
        <div style="border:1px solid var(--border); border-radius:6px; padding:10px; background:var(--bg);">
            <div style="font-size:0.75rem; font-weight:700; color:var(--purple); margin-bottom:6px;"><i class="bi bi-images"></i> 1. Specific Sketches & ID Ranges</div>
            <div class="ex-ac-wrap">
                <input type="text" id="exSketchSearch" class="cz-search-input" style="width:100%;" placeholder="Search sketch by ID or Name..." oninput="searchExportSketches(this.value)">
                <div id="exSketchAc" class="ex-ac-dropdown"></div>
            </div>
            <div id="exSketchChips" class="ex-chips"></div>

            <div style="display:flex; gap:6px; align-items:center; margin-top:8px; flex-wrap:wrap;">
                <input type="number" id="exRangeStart" class="cz-search-input" style="width:90px;" placeholder="Start ID">
                <input type="number" id="exRangeEnd" class="cz-search-input" style="width:90px;" placeholder="End ID">
                <button class="cz-btn" onclick="addExportRange()">+ Range</button>
            </div>
            <div id="exRangeChips" class="ex-chips"></div>
        </div>

        <!-- 2. Map Runs -->
        <div style="border:1px solid var(--border); border-radius:6px; padding:10px; background:var(--bg);">
            <div style="font-size:0.75rem; font-weight:700; color:var(--amber); margin-bottom:6px;"><i class="bi bi-diagram-3"></i> 2. Map Runs (Sketches)</div>
            <div class="ex-ac-wrap">
                <input type="text" id="exMapRunSearch" class="cz-search-input" style="width:100%;" placeholder="Search map run by ID or Note..." oninput="searchExportMapRuns(this.value)">
                <div id="exMapRunAc" class="ex-ac-dropdown"></div>
            </div>
            <div id="exMapRunChips" class="ex-chips"></div>
        </div>

        <!-- 3. Narrative Sequences -->
        <div style="border:1px solid var(--border); border-radius:6px; padding:10px; background:var(--bg);">
            <div style="font-size:0.75rem; font-weight:700; color:var(--teal); margin-bottom:6px;"><i class="bi bi-collection-play"></i> 3. Narrative Sequences</div>
            <div class="ex-ac-wrap">
                <input type="text" id="exSeqSearch" class="cz-search-input" style="width:100%;" placeholder="Search narrative sequence by ID or Name..." oninput="searchExportSequences(this.value)">
                <div id="exSeqAc" class="ex-ac-dropdown"></div>
            </div>
            <div id="exSeqChips" class="ex-chips"></div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:8px;">
            <button class="cz-btn" onclick="closeExportModal()">Cancel</button>
            <button class="cz-btn primary" id="btnDownloadExport" onclick="downloadContinuityExport()">
                <i class="bi bi-download"></i> Download Continuity JSON
            </button>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;
let selectedPrefix = '';

// Export Tool State
let exSketches = [];
let exRanges = [];
let exMapRuns = [];
let exSequences = [];
let acTimeout = null;

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('spw_theme', next); } catch(e) {}
}

function selectPrefix(prefix) {
    selectedPrefix = prefix;
    $('.cz-pill').removeClass('active');
    $(`.cz-pill[data-prefix="${prefix}"]`).addClass('active');
    loadList(1);
}

function loadList(page = 1) {
    currentPage = page;
    const search = $('#searchInput').val();
    const typeFilter = $('#typeFilter').val();

    $.post('continuity_crud.php', { 
        action: 'fetch', 
        search: search, 
        type_filter: typeFilter, 
        prefix_filter: selectedPrefix,
        page: page, 
        limit: 15 
    }, function(res) {
        if (!res.ok) return;
        const container = $('#rowList').empty();
        
        updatePaginationUI(res);

        if (res.rows.length === 0) {
            container.append('<div style="text-align:center; padding:40px; color:var(--text-muted);">No matching entity tags found.</div>');
            return;
        }

        res.rows.forEach(row => {
            const card = $(`
                <div class="cz-card" data-id="${row.id}">
                    <div class="cz-card-head">
                        <span style="font-size:0.7rem; color:var(--text-muted);">#${row.id}</span>
                        <input type="text" class="cz-tag-input" value="{${row.tag_name}}" title="Replacement Tag" onblur="updateField(${row.id}, 'tag_name', this.value)">
                        <input type="text" class="cz-cname-input" value="${esc(row.canonical_name || '')}" placeholder="Canonical Name..." title="Canonical Display Name" onblur="updateField(${row.id}, 'canonical_name', this.value)">
                        <select class="cz-type-select" onchange="updateField(${row.id}, 'entity_type', this.value)">
                            <option value="character" ${row.entity_type==='character'?'selected':''}>Character</option>
                            <option value="location" ${row.entity_type==='location'?'selected':''}>Location</option>
                            <option value="style" ${row.entity_type==='style'?'selected':''}>Style</option>
                            <option value="artifact" ${row.entity_type==='artifact'?'selected':''}>Artifact</option>
                            <option value="vehicle" ${row.entity_type==='vehicle'?'selected':''}>Vehicle</option>
                        </select>
                        <input type="number" class="cz-cname-input" style="width:70px; color:var(--teal);" value="${row.img2img_frame_id || ''}" placeholder="Frame ID" title="Reference img2img_frame_id for Sketch ingestion" onblur="updateField(${row.id}, 'img2img_frame_id', this.value)">
                        <div style="flex:1;"></div>
                        <button class="cz-btn" style="border-color:var(--red); color:var(--red);" onclick="deleteEntity(${row.id})"><i class="bi bi-trash"></i></button>
                    </div>
                    <textarea class="cz-desc-textarea" oninput="autoGrow(this)" onblur="updateField(${row.id}, 'description', this.value)" placeholder="Description...">${row.description || ''}</textarea>
                </div>
            `);
            container.append(card);
            autoGrow(card.find('textarea')[0]);
        });
    });
}

function updatePaginationUI(res) {
    currentPage = res.currentPage;
    totalPages = res.totalPages;
    $('#pgInput').val(currentPage).attr('max', totalPages);
    $('#pgTotal').text('/ ' + totalPages);
    $('#pageInfo').text(`${res.totalRows} tag(s) • page ${currentPage} of ${totalPages}`);
    $('#pgPrev').prop('disabled', currentPage <= 1);
    $('#pgNext').prop('disabled', currentPage >= totalPages);
}

function changePage(delta) {
    const target = currentPage + delta;
    if (target >= 1 && target <= totalPages) {
        loadList(target);
    }
}

function jumpToPage() {
    let val = parseInt($('#pgInput').val(), 10);
    if (isNaN(val) || val < 1) val = 1;
    if (val > totalPages) val = totalPages;
    $('#pgInput').val(val);
    loadList(val);
}

function esc(s) { return s ? s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }

function updateField(id, field, value) {
    if (field === 'tag_name') value = value.replace(/[{}]/g, '');
    $.post('continuity_crud.php', { action: 'update', id: id, field: field, value: value }, function(res) {
        if (res.ok) Toast.show('Saved', 'success');
        else Toast.show(res.error || 'Error saving', 'error');
    });
}

function addEntity() {
    $.post('continuity_crud.php', { action: 'add' }, function(res) {
        if (res.ok) { Toast.show('Entity tag created', 'success'); loadList(1); }
    });
}

function deleteEntity(id) {
    if (!confirm('Delete this entity tag?')) return;
    $.post('continuity_crud.php', { action: 'delete', id: id }, function(res) {
        if (res.ok) { Toast.show('Deleted', 'success'); loadList(currentPage); }
    });
}

function autoGrow(ta) {
    if (!ta) return;
    ta.style.height = 'auto';
    ta.style.height = Math.max(40, ta.scrollHeight) + 'px';
}

function openSketchIngestModal() { $('#sketchIngestModal').css('display', 'flex'); }
function closeSketchIngestModal() { $('#sketchIngestModal').hide(); }

// ── Smart Multi-Format JSON Validator ─────────────────────────────────────
function validateSketchJSON() {
    const rawSketches = $('#sketchJsonInput').val().trim();
    const status = $('#sketchJsonStatus');
    const submitBtn = $('#btnSubmitSketchIngest');

    if (!rawSketches) {
        status.css({ background: 'rgba(239,68,68,0.15)', color: 'var(--red)', borderColor: 'var(--red)' })
              .text('Waiting for Sketches JSON input...');
        submitBtn.prop('disabled', true);
        return;
    }

    try {
        const parsed = JSON.parse(rawSketches);
        
        let sketchCount = 0;
        let tagCount = 0;

        // Detect new continuity tags block if present
        const newTags = parsed.continuity_tags_new || parsed.continuity_tags || parsed.new_tags;
        if (newTags && typeof newTags === 'object') {
            tagCount = Object.keys(newTags).length;
        }

        // Detect sketch items
        if (Array.isArray(parsed.sketches)) {
            sketchCount = parsed.sketches.length;
        } else if (Array.isArray(parsed)) {
            sketchCount = parsed.length;
        } else if (parsed.name || parsed.description) {
            sketchCount = 1;
        }

        let msg = `✓ Valid JSON structure detected (${sketchCount} sketch item/s`;
        if (tagCount > 0) msg += `, ${tagCount} new continuity tag/s to auto-ingest`;
        msg += `)`;

        status.css({ background: 'rgba(20, 184, 166, 0.15)', color: 'var(--teal)', borderColor: 'var(--teal)' })
              .text(msg);
        submitBtn.prop('disabled', false);

    } catch (e) {
        status.css({ background: 'rgba(239,68,68,0.15)', color: 'var(--red)', borderColor: 'var(--red)' })
              .text(`✕ Syntax Error: ${e.message}`);
        submitBtn.prop('disabled', true);
    }
}

function submitSketchIngest() {
    const rawSketches = $('#sketchJsonInput').val().trim();
    const inheritFrames = $('#chkInheritFrames').is(':checked');
    Toast.show('Expanding tags via Python Continuity Engine...', 'info');

    $.post('continuity_crud.php', {
        action: 'ingest_sketches',
        sketches_json: rawSketches,
        inherit_frames: inheritFrames
    }, function(res) {
        if (res.ok) {
            Toast.show(res.message, 'success');
            closeSketchIngestModal();
            $('#sketchJsonInput').val('');
            validateSketchJSON();
            loadList(1);
        } else {
            Toast.show(res.error || 'Ingestion failed', 'error');
        }
    }, 'json').fail(function() {
        Toast.show('Server communication error', 'error');
    });
}

// ── REVERSE EXPORT TOOL JS LOGIC ──────────────────────────────────────────
function openExportModal() { $('#exportModal').css('display', 'flex'); }
function closeExportModal() { $('#exportModal').hide(); }

function searchExportSketches(q) {
    clearTimeout(acTimeout);
    const ac = $('#exSketchAc');
    if (!q.trim()) { ac.hide(); return; }
    acTimeout = setTimeout(() => {
        $.post('continuity_crud.php', { action: 'search_export_sketches', q: q }, function(res) {
            if (!res.ok || !res.results.length) { ac.hide(); return; }
            ac.empty().show();
            res.results.forEach(r => {
                ac.append(`<div class="ex-ac-item" onclick="addExSketch(${r.id}, '${esc(r.name)}')">#${r.id} ${esc(r.name)}</div>`);
            });
        }, 'json');
    }, 200);
}

function addExSketch(id, name) {
    if (!exSketches.find(s => s.id === id)) {
        exSketches.push({ id, name });
        renderExChips();
    }
    $('#exSketchSearch').val('');
    $('#exSketchAc').hide();
}

function removeExSketch(id) {
    exSketches = exSketches.filter(s => s.id !== id);
    renderExChips();
}

function addExportRange() {
    const st = parseInt($('#exRangeStart').val(), 10);
    const en = parseInt($('#exRangeEnd').val(), 10);
    if (st && en && en >= st) {
        exRanges.push({ start: st, end: en });
        renderExChips();
        $('#exRangeStart').val(''); $('#exRangeEnd').val('');
    } else {
        Toast.show('Invalid ID Range', 'error');
    }
}

function removeExRange(idx) {
    exRanges.splice(idx, 1);
    renderExChips();
}

function searchExportMapRuns(q) {
    clearTimeout(acTimeout);
    const ac = $('#exMapRunAc');
    if (!q.trim()) { ac.hide(); return; }
    acTimeout = setTimeout(() => {
        $.post('continuity_crud.php', { action: 'search_export_mapruns', q: q }, function(res) {
            if (!res.ok || !res.results.length) { ac.hide(); return; }
            ac.empty().show();
            res.results.forEach(r => {
                const label = r.note ? r.note : (r.created_at || 'Run #' + r.id);
                ac.append(`<div class="ex-ac-item" onclick="addExMapRun(${r.id}, '${esc(label)}')">#${r.id} ${esc(label)}</div>`);
            });
        }, 'json');
    }, 200);
}

function addExMapRun(id, note) {
    if (!exMapRuns.find(m => m.id === id)) {
        exMapRuns.push({ id, note });
        renderExChips();
    }
    $('#exMapRunSearch').val(''); $('#exMapRunAc').hide();
}

function removeExMapRun(id) {
    exMapRuns = exMapRuns.filter(m => m.id !== id);
    renderExChips();
}

function searchExportSequences(q) {
    clearTimeout(acTimeout);
    const ac = $('#exSeqAc');
    if (!q.trim()) { ac.hide(); return; }
    acTimeout = setTimeout(() => {
        $.post('continuity_crud.php', { action: 'search_export_sequences', q: q }, function(res) {
            if (!res.ok || !res.results.length) { ac.hide(); return; }
            ac.empty().show();
            res.results.forEach(r => {
                ac.append(`<div class="ex-ac-item" onclick="addExSeq(${r.id}, '${esc(r.name)}')">#${r.id} ${esc(r.name)}</div>`);
            });
        }, 'json');
    }, 200);
}

function addExSeq(id, name) {
    if (!exSequences.find(s => s.id === id)) {
        exSequences.push({ id, name });
        renderExChips();
    }
    $('#exSeqSearch').val(''); $('#exSeqAc').hide();
}

function removeExSeq(id) {
    exSequences = exSequences.filter(s => s.id !== id);
    renderExChips();
}

function renderExChips() {
    const c1 = $('#exSketchChips').empty();
    exSketches.forEach(s => {
        c1.append(`<span class="ex-chip">#${s.id} ${esc(s.name)} <i class="bi bi-x ex-chip-remove" onclick="removeExSketch(${s.id})"></i></span>`);
    });

    const c2 = $('#exRangeChips').empty();
    exRanges.forEach((r, idx) => {
        c2.append(`<span class="ex-chip">Range ${r.start}-${r.end} <i class="bi bi-x ex-chip-remove" onclick="removeExRange(${idx})"></i></span>`);
    });

    const c3 = $('#exMapRunChips').empty();
    exMapRuns.forEach(m => {
        c3.append(`<span class="ex-chip">Run #${m.id} ${esc(m.note)} <i class="bi bi-x ex-chip-remove" onclick="removeExMapRun(${m.id})"></i></span>`);
    });

    const c4 = $('#exSeqChips').empty();
    exSequences.forEach(s => {
        c4.append(`<span class="ex-chip">Seq #${s.id} ${esc(s.name)} <i class="bi bi-x ex-chip-remove" onclick="removeExSeq(${s.id})"></i></span>`);
    });
}

function downloadContinuityExport() {
    const payload = {
        sketch_ids: exSketches.map(s => s.id),
        ranges: exRanges,
        map_run_ids: exMapRuns.map(m => m.id),
        sequence_ids: exSequences.map(s => s.id)
    };

    if (!payload.sketch_ids.length && !payload.ranges.length && !payload.map_run_ids.length && !payload.sequence_ids.length) {
        Toast.show('Please select at least one sketch source to export', 'error');
        return;
    }

    Toast.show('Generating Reverse Continuity JSON...', 'info');

    fetch('continuity_crud.php?action=generate_continuity_export', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => {
        if (!res.ok) throw new Error('Export failed');
        return res.blob();
    })
    .then(blob => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `continuity_sketches_export_${Date.now()}.json`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        Toast.show('Export downloaded successfully!', 'success');
        closeExportModal();
    })
    .catch(err => {
        Toast.show(err.message || 'Error generating export', 'error');
    });
}

$(document).ready(() => loadList(1));
</script>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>