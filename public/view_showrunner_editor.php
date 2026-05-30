<?php
// public/view_showrunner_editor.php
// Manual Curation Editor for showrunner_analysis JSON — v2
//
// Editable fields:
//   JSON  : episode_concepts, narrative_engine, scene_hooks
//   TEXT  : production_notes, series_bible
//
// Save strategy per field type:
//   JSON fields → parsed, validated, merged back into blob as structured data
//   TEXT fields → saved as plain strings directly into blob (no JSON parse step)
//
// series_bible is also synced to the dedicated md_doc_analysis.series_bible column on save.
// All other blob fields are preserved untouched on every save.
// ---------------------------------------------------------------

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ----------------------------------------------------------------
// FIELD DEFINITIONS
// type: 'json' | 'text'
// group: 'json' | 'text'   (controls section group header in UI)
// ----------------------------------------------------------------
const EDITABLE_FIELDS = [
    'episode_concepts' => [
        'type'  => 'json',
        'label' => 'Episode Concepts',
        'icon'  => '🎬',
        'hint'  => 'Array of episode objects. Required fields per item: title, logline, narrative_function (array), layer, energy, conflict, key_scene. Optional: notes. Episode titles are indexed for cross-linking — use consistent proper noun spelling throughout.',
        'group' => 'json',
    ],
    'narrative_engine' => [
        'type'  => 'json',
        'label' => 'Narrative Engine',
        'icon'  => '⚙️',
        'hint'  => 'Single object. Typical fields: core_conflict, central_metaphor, philosophical_stakes, readiness_score.',
        'group' => 'json',
    ],
    'scene_hooks' => [
        'type'  => 'json',
        'label' => 'Scene Hooks',
        'icon'  => '🎥',
        'hint'  => 'Array of scene hook objects. Each typically has: title, description. Titles are indexed for cross-linking.',
        'group' => 'json',
    ],
    'production_notes' => [
        'type'  => 'text',
        'label' => 'Production Notes',
        'icon'  => '🎙️',
        'hint'  => 'Plain text. Director/showrunner vision statement for this location. The pipeline concatenates per-chunk notes here — revise to a single, non-redundant statement that captures the location\'s cinematic identity.',
        'group' => 'text',
    ],
    'series_bible' => [
        'type'  => 'text',
        'label' => 'Series Bible',
        'icon'  => '📖',
        'hint'  => 'Plain text. Narrative architecture summary. The pipeline auto-appends an episode sample list drawn from episode_concepts_all — after finalising episode_concepts, update this to reflect the revised episode set. Also synced to the series_bible column on save.',
        'group' => 'text',
    ],
];

// ----------------------------------------------------------------
// AJAX SAVE
// POST body: { doc_id, field, field_type, value }
// Returns:   { ok: bool, error?: string }
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);

    $docId     = isset($body['doc_id'])     ? (int)$body['doc_id']     : 0;
    $field     = isset($body['field'])      ? trim($body['field'])      : '';
    $value     = $body['value']             ?? null;
    $fieldType = isset($body['field_type']) ? trim($body['field_type']) : 'json';

    if (!$docId || !$field || !array_key_exists($field, EDITABLE_FIELDS)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid parameters.']);
        exit;
    }

    // Load current blob
    $stmt = $pdo->prepare("SELECT showrunner_analysis FROM md_doc_analysis WHERE doc_id = ? LIMIT 1");
    $stmt->execute([$docId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'Document not found in md_doc_analysis.']);
        exit;
    }

    $blob = json_decode($row['showrunner_analysis'] ?? '{}', true) ?? [];

    if ($fieldType === 'json') {
        $jsonStr = is_string($value) ? $value : json_encode($value);
        $parsed  = json_decode($jsonStr, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
            exit;
        }
        $blob[$field] = $parsed;
    } else {
        // Plain text — store directly as string
        $blob[$field] = is_string($value) ? $value : (string)$value;
    }

    $newBlob = json_encode($blob, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($newBlob === false) {
        echo json_encode(['ok' => false, 'error' => 'Re-encode failed: ' . json_last_error_msg()]);
        exit;
    }

    try {
        if ($field === 'series_bible') {
            // Also sync to the dedicated column
            $upd = $pdo->prepare("
                UPDATE md_doc_analysis
                SET showrunner_analysis = ?, series_bible = ?, analyzed_at = NOW()
                WHERE doc_id = ?
            ");
            $upd->execute([$newBlob, $blob['series_bible'], $docId]);
        } else {
            $upd = $pdo->prepare("
                UPDATE md_doc_analysis
                SET showrunner_analysis = ?, analyzed_at = NOW()
                WHERE doc_id = ?
            ");
            $upd->execute([$newBlob, $docId]);
        }
        echo json_encode(['ok' => true, 'rows' => $upd->rowCount()]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ----------------------------------------------------------------
// AJAX LOAD
// GET ?load_doc=123  →  { ok, doc_id, doc_name, fields, preserved_keys }
// ----------------------------------------------------------------
if (isset($_GET['load_doc'])) {
    header('Content-Type: application/json');
    $docId = (int)$_GET['load_doc'];

    $stmt = $pdo->prepare("
        SELECT da.doc_id, d.name AS doc_name, da.showrunner_analysis
        FROM md_doc_analysis da
        JOIN documentations d ON da.doc_id = d.id
        WHERE da.doc_id = ?
        LIMIT 1
    ");
    $stmt->execute([$docId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'Not found']);
        exit;
    }

    $blob = json_decode($row['showrunner_analysis'] ?? '{}', true) ?? [];

    $fields = [];
    foreach (EDITABLE_FIELDS as $key => $def) {
        $val = $blob[$key] ?? null;

        if ($def['type'] === 'json') {
            $current = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $count   = is_array($val) ? count($val) : ($val !== null ? 1 : 0);
            $fields[$key] = [
                'type'    => 'json',
                'label'   => $def['label'],
                'icon'    => $def['icon'],
                'hint'    => $def['hint'],
                'group'   => $def['group'],
                'current' => $current,
                'exists'  => $val !== null,
                'count'   => $count,
            ];
        } else {
            // Flatten if pipeline stored this as an array of chunk-note objects
            if (is_array($val)) {
                $parts = [];
                foreach ($val as $item) {
                    if (is_array($item) && isset($item['note'])) $parts[] = $item['note'];
                    elseif (is_string($item)) $parts[] = $item;
                    else $parts[] = json_encode($item, JSON_UNESCAPED_UNICODE);
                }
                $current = implode("\n\n---\n\n", $parts);
            } else {
                $current = (string)($val ?? '');
            }
            $fields[$key] = [
                'type'    => 'text',
                'label'   => $def['label'],
                'icon'    => $def['icon'],
                'hint'    => $def['hint'],
                'group'   => $def['group'],
                'current' => $current,
                'exists'  => $val !== null,
                'count'   => null,
            ];
        }
    }

    $allKeys       = array_keys($blob);
    $preservedKeys = array_values(array_diff($allKeys, array_keys(EDITABLE_FIELDS)));

    echo json_encode([
        'ok'             => true,
        'doc_id'         => $docId,
        'doc_name'       => $row['doc_name'],
        'fields'         => $fields,
        'preserved_keys' => $preservedKeys,
    ]);
    exit;
}

// ----------------------------------------------------------------
// PAGE LOAD — fetch doc list for sidebar
// ----------------------------------------------------------------
$filterCat = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$whereSql  = $filterCat ? "AND d.category_id = :cat" : "";
$params    = $filterCat ? ['cat' => $filterCat] : [];

$docsSql = "
    SELECT da.doc_id, d.name AS doc_name, c.name AS cat_name,
           da.narrative_utility, da.analyzed_at,
           JSON_LENGTH(JSON_EXTRACT(da.showrunner_analysis, '$.episode_concepts')) AS ep_count
    FROM md_doc_analysis da
    JOIN documentations d ON da.doc_id = d.id
    LEFT JOIN documentation_categories c ON d.category_id = c.id
    WHERE da.showrunner_analysis IS NOT NULL
    $whereSql
    ORDER BY c.name ASC, d.name ASC
";
$stmt = $pdo->prepare($docsSql);
$stmt->execute($params);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cats = $pdo->query("SELECT id, name FROM documentation_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// ----------------------------------------------------------------
// HTML
// ----------------------------------------------------------------
ob_start();
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:ital,wght@0,400;0,600;1,400&family=Syne:wght@400;700;800&display=swap" rel="stylesheet">

<style>
/* ── DESIGN: Utilitarian editorial. Terminal-meets-broadsheet. ── */
:root {
    --ed-bg:        #0e0f11;
    --ed-surface:   #161719;
    --ed-border:    #2a2c31;
    --ed-border-hi: #3f4148;
    --ed-text:      #d4d6da;
    --ed-muted:     #6b6f7a;
    --ed-accent:    #e8c547;
    --ed-accent2:   #5b9bd5;
    --ed-green:     #4caf7d;
    --ed-red:       #e05c5c;
    --ed-orange:    #d4845a;
    --ed-mono:      'IBM Plex Mono', monospace;
    --ed-sans:      'Syne', sans-serif;
    --r:            6px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    background: var(--ed-bg);
    color: var(--ed-text);
    font-family: var(--ed-mono);
    font-size: 13px;
    line-height: 1.6;
    min-height: 100vh;
}

/* ── SHELL ── */
#editor-shell {
    display: grid;
    grid-template-columns: 280px 1fr;
    grid-template-rows: 52px 1fr;
    height: 100vh;
    overflow: hidden;
}

/* ── TOPBAR ── */
#topbar {
    grid-column: 1 / -1;
    background: var(--ed-surface);
    border-bottom: 2px solid var(--ed-accent);
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 0 20px;
}
.wordmark {
    font-family: var(--ed-sans);
    font-weight: 800;
    font-size: 15px;
    color: var(--ed-accent);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    white-space: nowrap;
    flex-shrink: 0;
}
.topbar-sep { width: 1px; height: 24px; background: var(--ed-border); flex-shrink: 0; }

#cat-filter {
    background: var(--ed-bg);
    border: 1px solid var(--ed-border);
    color: var(--ed-text);
    font-family: var(--ed-mono);
    font-size: 12px;
    padding: 5px 10px;
    border-radius: var(--r);
    cursor: pointer;
    flex-shrink: 0;
}
#cat-filter:focus { outline: none; border-color: var(--ed-accent); }

#topbar-label {
    font-size: 12px;
    color: var(--ed-muted);
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
#topbar-label span { color: var(--ed-text); font-weight: 600; }

#save-all-btn {
    display: none;
    background: var(--ed-accent);
    color: #0e0f11;
    border: none;
    font-family: var(--ed-sans);
    font-weight: 700;
    font-size: 12px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 7px 18px;
    border-radius: var(--r);
    cursor: pointer;
    transition: opacity 0.15s, transform 0.1s;
    flex-shrink: 0;
}
#save-all-btn:hover  { opacity: 0.85; transform: translateY(-1px); }
#save-all-btn.saving { opacity: 0.5; pointer-events: none; }

/* ── SIDEBAR ── */
#sidebar {
    background: var(--ed-surface);
    border-right: 1px solid var(--ed-border);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}
.sidebar-hdr {
    padding: 12px 16px 8px;
    font-family: var(--ed-sans);
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ed-muted);
    border-bottom: 1px solid var(--ed-border);
    position: sticky;
    top: 0;
    background: var(--ed-surface);
    z-index: 1;
}
.sidebar-cat {
    padding: 10px 16px 3px;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--ed-muted);
    border-top: 1px solid var(--ed-border);
}
.doc-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: background 0.1s, border-color 0.1s;
    user-select: none;
}
.doc-item:hover { background: rgba(255,255,255,0.03); }
.doc-item.active {
    background: rgba(232,197,71,0.07);
    border-left-color: var(--ed-accent);
}
.doc-item .doc-name {
    flex: 1;
    font-size: 12px;
    color: var(--ed-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
}
.doc-item.active .doc-name { color: var(--ed-accent); }
.ep-badge {
    font-size: 10px;
    color: var(--ed-muted);
    background: rgba(255,255,255,0.05);
    padding: 1px 6px;
    border-radius: 3px;
    flex-shrink: 0;
}

/* ── MAIN ── */
#editor-main {
    overflow-y: auto;
    background: var(--ed-bg);
    display: flex;
    flex-direction: column;
}

#empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
    color: var(--ed-muted);
    padding: 60px 40px;
    text-align: center;
}
#empty-state .big-icon { font-size: 52px; opacity: 0.22; }
#empty-state p         { max-width: 360px; line-height: 1.7; }
#empty-state p.sub     { font-size: 11px; opacity: 0.65; margin-top: -4px; }

#doc-header {
    display: none;
    padding: 18px 28px 14px;
    border-bottom: 1px solid var(--ed-border);
    background: var(--ed-surface);
    flex-shrink: 0;
}
#doc-header h1 {
    font-family: var(--ed-sans);
    font-weight: 800;
    font-size: 17px;
    color: var(--ed-text);
    margin-bottom: 8px;
}
.dh-meta {
    display: flex;
    align-items: center;
    gap: 22px;
    flex-wrap: wrap;
    font-size: 11px;
    color: var(--ed-muted);
}
.dh-meta span { color: var(--ed-text); }
#preserved-keys {
    margin-top: 8px;
    font-size: 10px;
    color: var(--ed-muted);
    opacity: 0.65;
    font-style: italic;
    line-height: 1.6;
}

#sections-container {
    display: none;
    flex: 1;
    padding: 24px 28px 48px;
    flex-direction: column;
    gap: 10px;
}
#sections-container.visible { display: flex; }

.group-label {
    font-family: var(--ed-sans);
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ed-muted);
    padding: 20px 0 6px;
    border-bottom: 1px solid var(--ed-border);
    margin-bottom: 4px;
}
.group-label:first-child { padding-top: 0; }

/* ── SECTION PANEL ── */
.section-panel {
    border: 1px solid var(--ed-border);
    border-radius: var(--r);
    background: var(--ed-surface);
    overflow: hidden;
    transition: border-color 0.2s;
}
.section-panel:focus-within { border-color: var(--ed-border-hi); }
.section-panel.dirty { border-color: var(--ed-accent2); }
.section-panel.saved { border-color: var(--ed-green); }
.section-panel.error { border-color: var(--ed-red); }

/* Subtle warm tint for text panels */
.section-panel.is-text .section-header {
    background: rgba(212,132,90,0.03);
}

.section-header {
    padding: 11px 16px;
    background: rgba(255,255,255,0.02);
    border-bottom: 1px solid var(--ed-border);
    display: flex;
    align-items: center;
    gap: 10px;
    user-select: none;
    flex-wrap: wrap;
    row-gap: 6px;
}
.section-icon  { font-size: 15px; flex-shrink: 0; }
.section-label {
    font-family: var(--ed-sans);
    font-weight: 700;
    font-size: 13px;
    color: var(--ed-text);
    flex: 1;
    min-width: 120px;
}
.type-badge {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    padding: 2px 7px;
    border-radius: 3px;
    flex-shrink: 0;
}
.badge-json { color: var(--ed-accent2); background: rgba(91,155,213,0.1); border: 1px solid rgba(91,155,213,0.2); }
.badge-text { color: var(--ed-orange);  background: rgba(212,132,90,0.1);  border: 1px solid rgba(212,132,90,0.2); }

.section-count {
    font-size: 11px;
    color: var(--ed-muted);
    background: rgba(255,255,255,0.05);
    padding: 2px 8px;
    border-radius: 3px;
    flex-shrink: 0;
}
.section-status {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 3px;
    transition: all 0.25s;
    flex-shrink: 0;
}
.status-idle   { color: var(--ed-muted);   background: transparent; }
.status-dirty  { color: var(--ed-accent2); background: rgba(91,155,213,0.1); }
.status-saving { color: var(--ed-accent);  background: rgba(232,197,71,0.1); animation: blink 0.7s infinite; }
.status-saved  { color: var(--ed-green);   background: rgba(76,175,125,0.1); }
.status-error  { color: var(--ed-red);     background: rgba(224,92,92,0.1); }

.save-section-btn {
    display: none;
    background: var(--ed-accent2);
    color: #fff;
    border: none;
    font-family: var(--ed-mono);
    font-size: 11px;
    font-weight: 600;
    padding: 5px 13px;
    border-radius: var(--r);
    cursor: pointer;
    transition: opacity 0.15s, transform 0.1s;
    flex-shrink: 0;
    white-space: nowrap;
}
.save-section-btn:hover { opacity: 0.85; transform: translateY(-1px); }

.section-hint {
    padding: 7px 16px;
    font-size: 11px;
    color: var(--ed-muted);
    background: rgba(255,255,255,0.01);
    border-bottom: 1px solid var(--ed-border);
    font-style: italic;
    line-height: 1.55;
}

.section-body { position: relative; }

.section-textarea {
    width: 100%;
    min-height: 280px;
    background: transparent;
    border: none;
    color: var(--ed-text);
    font-family: var(--ed-mono);
    font-size: 12px;
    line-height: 1.75;
    padding: 16px;
    resize: vertical;
    outline: none;
    tab-size: 2;
}
.section-textarea.is-text-area {
    font-size: 13px;
    line-height: 1.85;
    min-height: 200px;
}
.section-textarea::placeholder { color: var(--ed-muted); opacity: 0.45; }
.section-textarea:focus { background: rgba(255,255,255,0.008); }

.validation-bar {
    padding: 5px 16px;
    font-size: 11px;
    border-top: 1px solid var(--ed-border);
    min-height: 26px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}
.val-ok   { color: var(--ed-green); }
.val-err  { color: var(--ed-red); }
.val-info { color: var(--ed-muted); }

.section-tools {
    padding: 7px 16px;
    border-top: 1px solid var(--ed-border);
    display: flex;
    gap: 7px;
    align-items: center;
    background: rgba(0,0,0,0.1);
    flex-wrap: wrap;
}
.tool-btn {
    background: transparent;
    border: 1px solid var(--ed-border);
    color: var(--ed-muted);
    font-family: var(--ed-mono);
    font-size: 10px;
    padding: 3px 10px;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
}
.tool-btn:hover { border-color: var(--ed-border-hi); color: var(--ed-text); }

.char-counter {
    margin-left: auto;
    font-size: 10px;
    color: var(--ed-muted);
}

/* Toast */
#loader {
    display: none;
    position: fixed;
    bottom: 22px;
    right: 26px;
    background: var(--ed-surface);
    border: 1px solid var(--ed-border);
    color: var(--ed-text);
    font-size: 12px;
    padding: 10px 18px;
    border-radius: var(--r);
    box-shadow: 0 6px 28px rgba(0,0,0,0.55);
    z-index: 9999;
    animation: slideUp 0.2s ease;
    pointer-events: none;
}
#loader.visible { display: block; }

@keyframes blink   { 0%,100%{opacity:1} 50%{opacity:.35} }
@keyframes slideUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
</style>

<div id="editor-shell">

    <!-- TOPBAR -->
    <div id="topbar">
        <div class="wordmark">Showrunner&nbsp;Editor</div>
        <div class="topbar-sep"></div>
        <select id="cat-filter" onchange="filterDocs(this.value)">
            <option value="0">All categories</option>
            <?php foreach ($cats as $c): ?>
                <option value="<?= h($c['id']) ?>" <?= $filterCat == $c['id'] ? 'selected' : '' ?>>
                    <?= h($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div id="topbar-label">Select a document →</div>
        <button id="save-all-btn" onclick="saveAll()">↑ Save All Modified</button>
    </div>

    <!-- SIDEBAR -->
    <div id="sidebar">
        <div class="sidebar-hdr">Documents (<?= count($docs) ?>)</div>
        <?php
        $grouped = [];
        foreach ($docs as $doc) {
            $grouped[$doc['cat_name'] ?? '—'][] = $doc;
        }
        ksort($grouped);
        foreach ($grouped as $catName => $catDocs):
        ?>
        <div class="sidebar-cat"><?= h($catName) ?></div>
        <?php foreach ($catDocs as $doc): ?>
        <div class="doc-item"
             id="sidebar-item-<?= h($doc['doc_id']) ?>"
             onclick="loadDoc(<?= (int)$doc['doc_id'] ?>)"
             title="<?= h($doc['doc_name']) ?>">
            <div class="doc-name"><?= h($doc['doc_name']) ?></div>
            <?php if ($doc['ep_count'] !== null): ?>
            <div class="ep-badge"><?= (int)$doc['ep_count'] ?>ep</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </div>

    <!-- MAIN EDITOR AREA -->
    <div id="editor-main">

        <div id="empty-state">
            <div class="big-icon">📂</div>
            <p>Select a document from the sidebar to begin editing its curated sections.</p>
            <p class="sub">
                JSON: episode_concepts · narrative_engine · scene_hooks<br>
                Text: production_notes · series_bible
            </p>
        </div>

        <div id="doc-header">
            <h1 id="dh-name">—</h1>
            <div class="dh-meta">
                <div>doc_id:&nbsp;<span id="dh-id">—</span></div>
                <div>loaded:&nbsp;<span id="dh-time">—</span></div>
            </div>
            <div id="preserved-keys"></div>
        </div>

        <div id="sections-container"></div>

    </div>
</div>

<div id="loader"></div>

<script>
'use strict';

// ── STATE ──
let currentDocId = null;
let dirtyFlags   = {};   // key → bool
let originalVals = {};   // key → string (as loaded)
let fieldTypes   = {};   // key → 'json' | 'text'

// Labels map baked in from PHP for save button restoration
const FIELD_LABELS = <?= json_encode(
    array_map(fn($f) => $f['label'], EDITABLE_FIELDS),
    JSON_UNESCAPED_UNICODE
) ?>;

// ── TOAST ──
let _tt = null;
function toast(msg, dur) {
    const el = document.getElementById('loader');
    el.textContent = msg;
    el.classList.add('visible');
    clearTimeout(_tt);
    _tt = setTimeout(() => el.classList.remove('visible'), dur || 2800);
}

// ── CATEGORY FILTER ──
function filterDocs(catId) {
    const u = new URL(window.location.href);
    u.searchParams.set('category_id', catId);
    window.location.href = u.toString();
}

// ── LOAD DOC ──
async function loadDoc(docId) {
    if (currentDocId === docId) return;

    if (Object.values(dirtyFlags).some(Boolean)) {
        if (!confirm('You have unsaved changes. Discard and load new document?')) return;
    }

    document.querySelectorAll('.doc-item').forEach(el => el.classList.remove('active'));
    const sideItem = document.getElementById('sidebar-item-' + docId);
    if (sideItem) sideItem.classList.add('active');

    toast('Loading…');

    try {
        const res  = await fetch('?load_doc=' + docId, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        if (!data.ok) { toast('❌ ' + (data.error || 'Load failed')); return; }

        currentDocId = docId;
        dirtyFlags   = {};
        originalVals = {};
        fieldTypes   = {};

        renderDocHeader(data);
        renderFields(data);

        document.getElementById('topbar-label').innerHTML =
            'Editing: <span>' + escHtml(data.doc_name) + '</span>';
        document.getElementById('save-all-btn').style.display = 'none';

        toast('✓ Loaded');
    } catch (e) {
        toast('❌ Network error');
        console.error(e);
    }
}

// ── RENDER DOC HEADER ──
function renderDocHeader(data) {
    document.getElementById('empty-state').style.display = 'none';
    document.getElementById('doc-header').style.display = 'block';
    document.getElementById('dh-name').textContent = data.doc_name;
    document.getElementById('dh-id').textContent   = data.doc_id;
    document.getElementById('dh-time').textContent = new Date().toLocaleTimeString();

    const pk = document.getElementById('preserved-keys');
    pk.textContent = data.preserved_keys && data.preserved_keys.length
        ? 'Preserved (read-only): ' + data.preserved_keys.join(', ')
        : '';
}

// ── RENDER FIELDS ──
function renderFields(data) {
    const container = document.getElementById('sections-container');
    container.innerHTML = '';
    container.classList.add('visible');

    let lastGroup = null;
    Object.entries(data.fields).forEach(([key, fld]) => {
        if (fld.group !== lastGroup) {
            const gl = document.createElement('div');
            gl.className = 'group-label';
            gl.textContent = fld.group === 'json' ? '▸ JSON Fields' : '▸ Plain-Text Fields';
            container.appendChild(gl);
            lastGroup = fld.group;
        }
        originalVals[key] = fld.current || '';
        fieldTypes[key]   = fld.type;
        container.appendChild(buildPanel(key, fld));
    });
}

// ── BUILD PANEL ──
function buildPanel(key, fld) {
    const isJson = fld.type === 'json';

    const panel = document.createElement('div');
    panel.className = 'section-panel' + (isJson ? '' : ' is-text');
    panel.id = 'panel-' + key;

    // Header
    const hdr = document.createElement('div');
    hdr.className = 'section-header';
    hdr.innerHTML = `
        <span class="section-icon">${fld.icon}</span>
        <span class="section-label">${escHtml(fld.label)}</span>
        <span class="type-badge ${isJson ? 'badge-json' : 'badge-text'}">${isJson ? 'JSON' : 'Text'}</span>
        ${isJson ? `<span class="section-count" id="count-${key}">${fld.exists ? fld.count + (fld.count !== 1 ? ' items' : ' item') : 'null'}</span>` : ''}
        <span class="section-status status-idle" id="status-${key}">Unchanged</span>
        <button class="save-section-btn" id="save-btn-${key}" onclick="saveField('${key}')">
            ↑ Save ${escHtml(fld.label)}
        </button>
    `;
    panel.appendChild(hdr);

    // Hint
    const hint = document.createElement('div');
    hint.className = 'section-hint';
    hint.textContent = fld.hint;
    panel.appendChild(hint);

    // Textarea
    const body = document.createElement('div');
    body.className = 'section-body';
    const ta = document.createElement('textarea');
    ta.className = 'section-textarea' + (isJson ? '' : ' is-text-area');
    ta.id = 'ta-' + key;
    ta.spellcheck = !isJson;
    ta.autocomplete = 'off';
    ta.placeholder = isJson ? 'null' : '(empty)';
    ta.value = fld.current || '';
    body.appendChild(ta);
    panel.appendChild(body);

    // Validation bar (JSON only)
    if (isJson) {
        const vb = document.createElement('div');
        vb.className = 'validation-bar val-info';
        vb.id = 'val-' + key;
        vb.textContent = '—';
        panel.appendChild(vb);
    }

    // Tools
    const tools = document.createElement('div');
    tools.className = 'section-tools';
    if (isJson) {
        tools.innerHTML = `
            <button class="tool-btn" onclick="formatJson('${key}')">⎄ Pretty-print</button>
            <button class="tool-btn" onclick="minifyJson('${key}')">⤒ Minify</button>
            <button class="tool-btn" onclick="resetField('${key}')">↩ Reset</button>
            <button class="tool-btn" onclick="copyField('${key}')">⎘ Copy</button>
        `;
    } else {
        tools.innerHTML = `
            <button class="tool-btn" onclick="resetField('${key}')">↩ Reset</button>
            <button class="tool-btn" onclick="copyField('${key}')">⎘ Copy</button>
            <span class="char-counter" id="chars-${key}"></span>
        `;
        // Initial char count
        setTimeout(() => updateCharCount(key), 0);
    }
    panel.appendChild(tools);

    // Wire change listener
    ta.addEventListener('input', () => onFieldChange(key));
    if (isJson) {
        ta.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                e.preventDefault();
                const s = ta.selectionStart, end = ta.selectionEnd;
                ta.value = ta.value.substring(0, s) + '  ' + ta.value.substring(end);
                ta.selectionStart = ta.selectionEnd = s + 2;
                onFieldChange(key);
            }
        });
    }

    return panel;
}

// ── CHANGE HANDLER ──
function onFieldChange(key) {
    const isJson  = fieldTypes[key] === 'json';
    const ta      = document.getElementById('ta-' + key);
    const trimmed = ta.value.trim();
    let isValid   = true;

    if (isJson) {
        const vb = document.getElementById('val-' + key);
        if (trimmed === '' || trimmed === 'null') {
            vb.textContent = 'null — will set field to null';
            vb.className   = 'validation-bar val-info';
        } else {
            try {
                const p = JSON.parse(trimmed);
                const typ = Array.isArray(p) ? `array [${p.length}]` : typeof p;
                vb.textContent = '✓ Valid JSON — ' + typ;
                vb.className   = 'validation-bar val-ok';
                const cEl = document.getElementById('count-' + key);
                if (cEl) cEl.textContent = Array.isArray(p)
                    ? p.length + (p.length !== 1 ? ' items' : ' item')
                    : 'object';
            } catch (e) {
                vb.textContent = '✗ ' + e.message;
                vb.className   = 'validation-bar val-err';
                isValid = false;
            }
        }
    } else {
        updateCharCount(key);
    }

    const isDirty = trimmed !== (originalVals[key] || '').trim();
    dirtyFlags[key] = isDirty && isValid;

    setStatus(key, isDirty ? (isValid ? 'dirty' : 'error') : 'idle');

    const saveBtn = document.getElementById('save-btn-' + key);
    if (saveBtn) saveBtn.style.display = (isDirty && isValid) ? 'inline-block' : 'none';

    document.getElementById('save-all-btn').style.display =
        Object.values(dirtyFlags).some(Boolean) ? 'inline-block' : 'none';
}

function updateCharCount(key) {
    const el = document.getElementById('chars-' + key);
    const ta = document.getElementById('ta-' + key);
    if (el && ta) el.textContent = ta.value.length.toLocaleString() + ' chars';
}

// ── STATUS ──
function setStatus(key, state) {
    const el    = document.getElementById('status-' + key);
    const panel = document.getElementById('panel-' + key);
    if (!el || !panel) return;

    panel.classList.remove('dirty','saved','error');
    el.className = 'section-status';

    const map = {
        idle:   ['Unchanged', 'status-idle',    null],
        dirty:  ['Modified',  'status-dirty',   'dirty'],
        saving: ['Saving…',   'status-saving',  null],
        saved:  ['Saved ✓',   'status-saved',   'saved'],
        error:  ['JSON Error','status-error',   'error'],
    };
    const [text, cls, panelCls] = map[state] || map.idle;
    el.textContent = text;
    el.classList.add(cls);
    if (panelCls) panel.classList.add(panelCls);
}

// ── SAVE ONE FIELD ──
async function saveField(key) {
    if (!currentDocId) return;

    const isJson  = fieldTypes[key] === 'json';
    const ta      = document.getElementById('ta-' + key);
    const trimmed = ta.value.trim();
    let sendValue;

    if (isJson) {
        if (trimmed === '' || trimmed === 'null') {
            sendValue = null;
        } else {
            try {
                sendValue = JSON.stringify(JSON.parse(trimmed));
            } catch (e) {
                toast('❌ Invalid JSON — cannot save');
                return;
            }
        }
    } else {
        sendValue = ta.value;  // preserve exact text including whitespace
    }

    setStatus(key, 'saving');
    const saveBtn = document.getElementById('save-btn-' + key);
    if (saveBtn) { saveBtn.textContent = 'Saving…'; saveBtn.disabled = true; }

    try {
        const res  = await fetch(window.location.pathname, {
            method:  'POST',
            headers: {
                'Content-Type':     'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                doc_id:     currentDocId,
                field:      key,
                field_type: isJson ? 'json' : 'text',
                value:      sendValue,
            }),
        });
        const data = await res.json();

        if (data.ok) {
            originalVals[key] = trimmed;
            dirtyFlags[key]   = false;
            setStatus(key, 'saved');
            toast('✓ ' + (FIELD_LABELS[key] || key) + ' saved');
            setTimeout(() => { if (!dirtyFlags[key]) setStatus(key, 'idle'); }, 3000);
        } else {
            setStatus(key, 'error');
            toast('❌ ' + (data.error || 'Save failed'));
        }
    } catch (e) {
        setStatus(key, 'error');
        toast('❌ Network error');
        console.error(e);
    } finally {
        if (saveBtn) {
            saveBtn.textContent = '↑ Save ' + (FIELD_LABELS[key] || key);
            saveBtn.disabled    = false;
        }
        document.getElementById('save-all-btn').style.display =
            Object.values(dirtyFlags).some(Boolean) ? 'inline-block' : 'none';
    }
}

// ── SAVE ALL ──
async function saveAll() {
    const keys = Object.keys(dirtyFlags).filter(k => dirtyFlags[k]);
    if (!keys.length) return;
    const btn = document.getElementById('save-all-btn');
    btn.classList.add('saving');
    btn.textContent = 'Saving…';
    for (const k of keys) await saveField(k);
    btn.classList.remove('saving');
    btn.textContent = '↑ Save All Modified';
}

// ── TOOLS ──
function formatJson(key) {
    const ta = document.getElementById('ta-' + key);
    try { ta.value = JSON.stringify(JSON.parse(ta.value.trim()), null, 2); onFieldChange(key); }
    catch (e) { toast('❌ Cannot format: invalid JSON'); }
}
function minifyJson(key) {
    const ta = document.getElementById('ta-' + key);
    try { ta.value = JSON.stringify(JSON.parse(ta.value.trim())); onFieldChange(key); }
    catch (e) { toast('❌ Cannot minify: invalid JSON'); }
}
function resetField(key) {
    document.getElementById('ta-' + key).value = originalVals[key] || '';
    onFieldChange(key);
}
async function copyField(key) {
    const ta = document.getElementById('ta-' + key);
    try {
        await navigator.clipboard.writeText(ta.value);
        toast('⎘ Copied to clipboard');
    } catch (e) {
        ta.select(); document.execCommand('copy'); toast('⎘ Copied (fallback)');
    }
}

// ── UTILS ──
function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── UNLOAD GUARD ──
window.addEventListener('beforeunload', (e) => {
    if (Object.values(dirtyFlags).some(Boolean)) {
        e.preventDefault(); e.returnValue = 'You have unsaved changes.';
    }
});

// ── AUTO-LOAD from ?doc_id= ──
const _autoDoc = new URLSearchParams(window.location.search).get('doc_id');
if (_autoDoc) window.addEventListener('DOMContentLoaded', () => loadDoc(parseInt(_autoDoc, 10)));
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout(
    $content,
    'Showrunner Editor',
    $spw->getProjectPath() . '/templates/gallery.php'
);
