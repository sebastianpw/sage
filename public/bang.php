<?php
/**
 * bang.php
 * BANG! — Comic Panel Composer
 * Speech Bubbles, FX Text, Panel Layout on Konva Canvas
 * Forge UI • Space Mono + Syne • Amber Accents
 */
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
global $pdo;

$compositeId = isset($_GET['composite_id']) ? (int)$_GET['composite_id'] : 0;
$canvasId    = isset($_GET['canvas_id'])    ? (int)$_GET['canvas_id']    : 0;

// ── Resolve composite ─────────────────────────────────────────────────────────
$composite = null;
if ($compositeId) {
    $stmt = $pdo->prepare("SELECT * FROM composites WHERE id = ?");
    $stmt->execute([$compositeId]);
    $composite = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ── If no composite_id, show the composite picker ────────────────────────────

// ── If no composite_id, show the composite picker ────────────────────────────
if (!$composite) {
    ob_start();
    ?>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
    :root, [data-theme="dark"] {
        --bg: #080a0f; --surface: #0d1017; --card: #111620; --border: #1b2333;
        --text: #c8d4e8; --muted: #5a6a80; --amber: #f5a623; --amber-glow: rgba(245,166,35,0.12);
    }
    [data-theme="light"] {
        --bg: #f4f6fa; --surface: #fff; --card: #fff; --border: #d0d8e8;
        --text: #1a2233; --muted: #7a8aaa; --amber: #c8880a; --amber-glow: rgba(200,136,10,0.08);
    }
    body { background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif; margin: 0; }
    .picker-wrap { max-width: 720px; margin: 60px auto; padding: 20px; }
    .picker-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 14px; }
    .picker-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.4rem; color: var(--text); display: flex; align-items: center; gap: 10px; }
    .picker-badge { background: var(--amber); color: #000; font-family: 'Space Mono', monospace; font-size: 0.65rem; font-weight: 700; padding: 3px 8px; border-radius: 4px; letter-spacing: 1px; text-transform: uppercase; }
    
    .picker-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
    .picker-tab { background: transparent; border: none; color: var(--muted); font-family: 'Space Mono', monospace; font-size: 0.85rem; font-weight: 700; cursor: pointer; padding: 6px 12px; transition: 0.2s; border-radius: 4px; text-transform: uppercase; }
    .picker-tab:hover { color: var(--text); background: var(--bg-hover); }
    .picker-tab.active { color: var(--amber); background: var(--amber-glow); }
    .tab-pane { display: none; }
    .tab-pane.active { display: block; }

    .search-row { display: flex; gap: 10px; margin-bottom: 20px; }
    .search-input { flex: 1; background: var(--card); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px 14px; font-size: 0.9rem; outline: none; transition: border-color 0.2s; }
    .search-input:focus { border-color: var(--amber); }
    .composite-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; }
    .comp-card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; cursor: pointer; transition: border-color 0.15s, transform 0.15s; text-decoration: none; display: flex; flex-direction: column; }
    .comp-card:hover { border-color: var(--amber); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.4); }
    .comp-card-id { padding: 10px 12px 0; font-family: 'Space Mono', monospace; font-size: 0.6rem; color: var(--amber); letter-spacing: 1.5px; }
    .comp-card-name { padding: 6px 12px 14px; font-family: 'Syne', sans-serif; font-weight: 700; font-size: 0.9rem; color: var(--text); line-height: 1.3; }
    .pagination { display: flex; gap: 10px; align-items: center; justify-content: center; margin-top: 24px; }
    .pg-btn { background: var(--card); border: 1px solid var(--border); color: var(--muted); padding: 7px 14px; border-radius: 4px; font-family: 'Space Mono', monospace; font-size: 0.7rem; cursor: pointer; transition: 0.15s; }
    .pg-btn:hover:not(:disabled) { border-color: var(--amber); color: var(--amber); }
    .pg-btn:disabled { opacity: 0.3; cursor: default; }
    
   
    .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 300000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
    .modal-backdrop.active { display: flex; }
    
    </style>

    <div class="picker-wrap">
        <div class="picker-header">
            <div class="picker-title">
                <span>💥</span>
                BANG! Panel Composer
                <span class="picker-badge">Select Source</span>
            </div>
        </div>

        <div class="picker-tabs">
            <button class="picker-tab active" onclick="switchTab('composites')">Composites</button>
            <button class="picker-tab" onclick="switchTab('sequences')">Narrative Sequences</button>
        </div>

        <!-- Composites Tab -->
        <div id="tab-composites" class="tab-pane active">
            <p style="color:var(--muted); font-size:0.85rem; margin-bottom:20px;">
                Select an existing Composite to use as the image container for a new or existing panel strip.
            </p>
            <div class="search-row">
                <input type="text" class="search-input" id="compSearch" placeholder="Search composites by name or ID…" oninput="debounceSearch()" autofocus>
            </div>
            <div id="compGrid" class="composite-grid"></div>
            <div class="pagination" id="pagination" style="display:none;">
                <button class="pg-btn" id="pgPrev" onclick="loadComposites(currentPage - 1)">← Prev</button>
                <span style="font-size:0.75rem; color:var(--muted);" id="pgLabel">Page 1 of 1</span>
                <button class="pg-btn" id="pgNext" onclick="loadComposites(currentPage + 1)">Next →</button>
            </div>
        </div>

        <!-- Narrative Sequences Tab -->
        <div id="tab-sequences" class="tab-pane">
            <p style="color:var(--muted); font-size:0.85rem; margin-bottom:20px;">
                Import a narrative sequence to automatically create a new composite containing all its frames.
            </p>
            <div class="search-row">
                <input type="text" class="search-input" id="seqSearch" placeholder="Search sequences by name or ID…" oninput="debounceSeqSearch()">
            </div>
            <div id="seqGrid" class="composite-grid"></div>
            <div class="pagination" id="seqPagination" style="display:none;">
                <button class="pg-btn" id="seqPgPrev" onclick="loadSequences(currentSeqPage - 1)">← Prev</button>
                <span style="font-size:0.75rem; color:var(--muted);" id="seqPgLabel">Page 1 of 1</span>
                <button class="pg-btn" id="seqPgNext" onclick="loadSequences(currentSeqPage + 1)">Next →</button>
            </div>
        </div>
        
        
        
    </div>
    
    
   
    <!-- Sequence Import Modal -->
    <div class="modal-backdrop" id="importSeqModal" onmousedown="if(event.target===this) closeImportSeqModal()">
        <div class="modal-box" style="max-width:400px; background:var(--card); border:1px solid var(--border); border-radius:8px; padding:20px; margin:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <div style="font-family:'Space Mono',monospace; font-weight:bold; color:var(--amber);">IMPORT SEQUENCE</div>
                <button onclick="closeImportSeqModal()" style="background:none; border:none; color:var(--muted); cursor:pointer; font-size:1.2rem;">✕</button>
            </div>
            
            <div id="importSeqMeta" style="margin-bottom:15px; font-size:0.9rem; color:var(--text); line-height:1.4;"></div>
            
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-bottom:15px; font-family:'Space Mono',monospace; font-size:0.8rem; color:var(--text);">
                <input type="checkbox" id="importOptGrid" checked onchange="document.getElementById('importGridConfig').style.display = this.checked ? 'block' : 'none'">
                Arrange in Panel Grid
            </label>
            
            <div id="importGridConfig" style="background:var(--bg); padding:12px; border-radius:6px; border:1px solid var(--border);">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                    <div><div style="font-size:0.65rem; color:var(--muted); text-transform:uppercase; margin-bottom:4px;">Rows</div><input type="number" id="importGridR" class="search-input" value="3" min="1" style="width:100%;"></div>
                    <div><div style="font-size:0.65rem; color:var(--muted); text-transform:uppercase; margin-bottom:4px;">Cols</div><input type="number" id="importGridC" class="search-input" value="3" min="1" style="width:100%;"></div>
                </div>
                <div><div style="font-size:0.65rem; color:var(--muted); text-transform:uppercase; margin-bottom:4px;">Gutter (px)</div><input type="number" id="importGridG" class="search-input" value="10" min="0" style="width:100%;"></div>
            </div>
            
            <div style="margin-top:16px; border-top:1px solid var(--border); padding-top:14px;">
                <button onclick="executeSequenceImport()" style="background:var(--amber); color:#000; border:none; border-radius:4px; padding:10px; width:100%; font-family:'Space Mono',monospace; font-weight:bold; cursor:pointer; text-transform:uppercase;">Import & Generate</button>
            </div>
        </div>
    </div>
    

    <script>
    function switchTab(tab) {
        document.querySelectorAll('.picker-tab').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        document.querySelector(`.picker-tab[onclick="switchTab('${tab}')"]`).classList.add('active');
        document.getElementById(`tab-${tab}`).classList.add('active');
        
        if (tab === 'sequences' && currentSeqPage === 1 && document.getElementById('seqGrid').innerHTML === '') {
            loadSequences(1);
        }
    }

    // -- Composites Logic --
    let currentPage = 1, totalPages = 1, searchTimer = null;
    function debounceSearch() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadComposites(1), 300);
    }
    async function loadComposites(page) {
        currentPage = page;
        const q = document.getElementById('compSearch').value.trim();
        const res = await fetch(`bang_api.php?action=list_composites&q=${encodeURIComponent(q)}&page=${page}`).then(r => r.json());
        if (!res.success) return;

        totalPages = res.pages;
        const grid = document.getElementById('compGrid');

        if (!res.composites.length) {
            grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:40px; color:var(--muted); font-size:0.85rem;">No composites found.</div>';
        } else {
            grid.innerHTML = res.composites.map(c => `
                <a href="bang.php?composite_id=${c.id}" class="comp-card">
                    <div class="comp-card-id">#${c.id}</div>
                    <div class="comp-card-name">${escHtml(c.name)}</div>
                </a>
            `).join('');
        }

        const pg = document.getElementById('pagination');
        pg.style.display = totalPages > 1 ? 'flex' : 'none';
        document.getElementById('pgLabel').textContent = `Page ${page} of ${totalPages}`;
        document.getElementById('pgPrev').disabled = page <= 1;
        document.getElementById('pgNext').disabled = page >= totalPages;
    }

    // -- Sequences Logic --
    let currentSeqPage = 1, totalSeqPages = 1, seqSearchTimer = null;
    function debounceSeqSearch() {
        clearTimeout(seqSearchTimer);
        seqSearchTimer = setTimeout(() => loadSequences(1), 300);
    }
    async function loadSequences(page) {
        currentSeqPage = page;
        const q = document.getElementById('seqSearch').value.trim();
        const res = await fetch(`bang_api.php?action=list_narrative_sequences&q=${encodeURIComponent(q)}&page=${page}`).then(r => r.json());
        if (!res.success) return;

        totalSeqPages = res.pages;
        const grid = document.getElementById('seqGrid');

        if (!res.sequences.length) {
            grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:40px; color:var(--muted); font-size:0.85rem;">No sequences found.</div>';
        } else {
            grid.innerHTML = res.sequences.map(s => `
               <div class="comp-card" onclick="openImportSeqModal(${s.id}, '${escAttr(s.name)}', ${s.frame_count})">

                    <div class="comp-card-id">#${s.id}</div>
                    <div class="comp-card-name">${escHtml(s.name)}</div>
                    <div style="padding:0 12px 14px; font-size:0.7rem; color:var(--amber); font-weight:bold;">+ Import to BANG!</div>
                </div>
            `).join('');
        }

        const pg = document.getElementById('seqPagination');
        pg.style.display = totalSeqPages > 1 ? 'flex' : 'none';
        document.getElementById('seqPgLabel').textContent = `Page ${page} of ${totalSeqPages}`;
        document.getElementById('seqPgPrev').disabled = page <= 1;
        document.getElementById('seqPgNext').disabled = page >= totalSeqPages;
    }


    let activeImportSeqId = null;

    function openImportSeqModal(id, name, frameCount) {
        activeImportSeqId = id;
        document.getElementById('importSeqMeta').innerHTML = `<b>#${id} — ${name}</b><br><span style="color:var(--muted); font-size:0.8rem;">Contains ${frameCount} frames</span>`;
        
        // Auto-suggest grid dimensions based on frame count
        const rc = Math.ceil(Math.sqrt(frameCount));
        document.getElementById('importGridR').value = Math.max(1, Math.ceil(frameCount / rc)) || 3;
        document.getElementById('importGridC').value = Math.max(1, rc) || 3;
        
        document.getElementById('importSeqModal').classList.add('active');
    }

    function closeImportSeqModal() {
        document.getElementById('importSeqModal').classList.remove('active');
        activeImportSeqId = null;
    }

    async function executeSequenceImport() {
        if (!activeImportSeqId) return;
        const fd = new FormData();
        fd.append('action', 'import_narrative_sequence');
        fd.append('sequence_id', activeImportSeqId);
        
        document.body.style.cursor = 'wait';
        try {
            const r = await fetch('bang_api.php', { method: 'POST', body: fd });
            const res = await r.json();
            if (res.success) {
                let url = `bang.php?composite_id=${res.composite_id}`;
                if (document.getElementById('importOptGrid').checked) {
                    url += `&grid=1&r=${document.getElementById('importGridR').value}&c=${document.getElementById('importGridC').value}&g=${document.getElementById('importGridG').value}&seq_id=${activeImportSeqId}`;
                }
                window.location.href = url;
            } else {
                alert(res.message || 'Import failed');
                document.body.style.cursor = 'default';
            }
        } catch(e) {
            alert('Network error during import');
            document.body.style.cursor = 'default';
        }
    }

    function escHtml(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function escAttr(s) { return String(s ?? '').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
    
    // Init composites on load
    loadComposites(1);
    </script>
    <?php
    $content = ob_get_clean();
    $spw->renderLayout($content . $eruda, 'BANG! — Select Source', $spw->getProjectPath() . '/templates/curation.php');
    exit;
}

// ── Resolve or create canvas ──────────────────────────────────────────────────
$canvas      = null;
$arrangement = null;

if (!$canvasId && $compositeId) {
    $stmtLatest = $pdo->prepare("SELECT id FROM bang_canvases WHERE composite_id = ? ORDER BY updated_at DESC LIMIT 1");
    $stmtLatest->execute([$compositeId]);
    $latestId = $stmtLatest->fetchColumn();
    if ($latestId) {
        $canvasId = (int)$latestId;
    }
}

if ($canvasId) {
    $stmt = $pdo->prepare("SELECT * FROM bang_canvases WHERE id = ? AND composite_id = ?");
    $stmt->execute([$canvasId, $compositeId]);
    $canvas = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($canvas) {
        $aStmt = $pdo->prepare("SELECT * FROM bang_arrangements WHERE canvas_id = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1");
        $aStmt->execute([$canvasId]);
        $arrangement = $aStmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Fetch composite frames
$fStmt = $pdo->prepare("SELECT f.id, f.filename, f.name FROM composite_frames cf JOIN frames f ON cf.frame_id = f.id WHERE cf.composite_id = ? ORDER BY cf.created_at ASC");
$fStmt->execute([$compositeId]);
$compositeFrames = $fStmt->fetchAll(PDO::FETCH_ASSOC);




// If importing a sequence grid, resolve the exact ordered frames from the JSON array
$importedFrames = [];
$importSeqId = isset($_GET['seq_id']) ? (int)$_GET['seq_id'] : 0;
if ($importSeqId) {
    $stmt = $pdo->prepare("SELECT sequence_data FROM narrative_sequences WHERE id = ?");
    $stmt->execute([$importSeqId]);
    $seqDataStr = $stmt->fetchColumn();
    if ($seqDataStr) {
        $seqData = json_decode($seqDataStr, true) ?: [];
        $fallbackStmt = $pdo->prepare("SELECT f.id, f.filename, f.name FROM frames f JOIN frames_2_sketches fs ON f.id = fs.from_id WHERE fs.to_id = ? ORDER BY f.id DESC LIMIT 1");
        $frameStmt = $pdo->prepare("SELECT id, filename, name FROM frames WHERE id = ?");
        foreach ($seqData as $item) {
            $fId = is_array($item) ? (!empty($item['frame_id']) ? (int)$item['frame_id'] : null) : null;
            $sId = is_array($item) ? (!empty($item['sketch_id']) ? (int)$item['sketch_id'] : null) : (int)$item;
            $frameRow = null;
            if ($fId) { $frameStmt->execute([$fId]); $frameRow = $frameStmt->fetch(PDO::FETCH_ASSOC); }
            if (!$frameRow && $sId) { $fallbackStmt->execute([$sId]); $frameRow = $fallbackStmt->fetch(PDO::FETCH_ASSOC); }
            if ($frameRow) $importedFrames[] = $frameRow;
        }
    }
}

// Fetch fonts
$fontRows = $pdo->query("SELECT * FROM bang_fonts WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$googleFontUrls = array_filter(array_column($fontRows, 'google_url'));
$googleFontUrls = array_unique(array_values($googleFontUrls));

// Existing canvases for this composite
$existingCanvases = $pdo->prepare("SELECT id, name, canvas_width, canvas_height, updated_at FROM bang_canvases WHERE composite_id = ? ORDER BY updated_at DESC");
$existingCanvases->execute([$compositeId]);
$existingCanvases = $existingCanvases->fetchAll(PDO::FETCH_ASSOC);


// Safely encode JSON
$jsCanvas       = json_encode($canvas);
$jsArrangement  = $arrangement ? json_encode($arrangement['scene_json']) : 'null';
$jsArrId        = $arrangement ? $arrangement['id'] : 'null';
$jsCanvasId     = $canvasId    ? $canvasId : 'null';
$jsFrames       = json_encode($compositeFrames);
$jsFonts        = json_encode($fontRows);
$jsImportedFrames = json_encode($importedFrames);

$pageTitle = "BANG! — " . htmlspecialchars($composite['name']);

ob_start();
?>

<meta name="viewport" id="bangViewportMeta" content="width=device-width, initial-scale=0.5, maximum-scale=2.0, user-scalable=yes">

<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<?php foreach ($googleFontUrls as $url): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($url) ?>">
<?php endforeach; ?>
<link href="https://fonts.googleapis.com/css2?family=Bangers&family=Permanent+Marker&family=Oswald:wght@600;700&family=Cinzel:wght@400;700&family=Space+Mono:wght@400;700&family=Lora:wght@400;500&display=swap" rel="stylesheet">
<script src="https://unpkg.com/konva@9.3.3/konva.min.js"></script>

<style>
/* ── FORGE UI Variables ──────────────────────────────────────────────────── */
:root, [data-theme="dark"] {
    --bg-void:    #06080d;
    --bg-base:    #0b0e16;
    --bg-raised:  #0f1420;
    --bg-float:   #151b28;
    --bg-hover:   #1a2030;
    --border:     rgba(255,255,255,0.06);
    --border-mid: rgba(255,255,255,0.11);
    --amber:      #f5a623;
    --amber-dim:  #7c4a00;
    --amber-glow: rgba(245,166,35,0.12);
    --green:      #22c55e;
    --red:        #ef4444;
    --blue:       #3b82f6;
    --teal:       #3ab5c8;
    --text:       #c8d4e8;
    --muted:      #5a6a80;
    --dim:        #2e3a4e;
    --font-mono:  'Space Mono', monospace;
    --font-disp:  'Syne', sans-serif;
    --font-body:  'DM Sans', sans-serif;
    --r:   6px;
    --r-lg: 10px;
}
[data-theme="light"] {
    --bg-void:    #eef0f5; --bg-base: #ffffff; --bg-raised: #f7f8fb;
    --bg-float:   #ffffff; --bg-hover: #eef0f5;
    --border:     rgba(0,0,0,0.07); --border-mid: rgba(0,0,0,0.12);
    --text:       #0d1017; --muted: #7a8aaa; --dim: #c0c8d8;
    --amber-glow: rgba(200,136,10,0.06);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; }
body { font-family: var(--font-body); background: var(--bg-void); color: var(--text); height: 100vh; overflow: hidden; }

/* ── Layout ──────────────────────────────────────────────────────────────── */
.bang-layout { display: flex; flex-direction: column; height: 100vh; }

/* ── Topbar ─────────────────────────────────────────────────────────────── */
.topbar {
    height: 52px; background: var(--bg-base); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px; padding: 0 14px; flex-shrink: 0; z-index: 100;
}
.topbar-brand { font-family: var(--font-disp); font-weight: 800; font-size: 1.1rem; color: var(--amber); letter-spacing: 1px; text-decoration: none; display: flex; align-items: center; gap: 8px; }
.topbar-brand span.badge { background: var(--amber); color: #000; font-size: 0.55rem; font-family: var(--font-mono); padding: 2px 6px; border-radius: 3px; letter-spacing: 1px; }
.topbar-sep { width: 1px; height: 28px; background: var(--border); }
.topbar-label { font-family: var(--font-mono); font-size: 0.65rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }
.topbar-right { margin-left: auto; display: flex; gap: 6px; align-items: center; }
.btn { display: inline-flex; align-items: center; gap: 5px; padding: 7px 12px; border-radius: var(--r); font-family: var(--font-mono); font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid; cursor: pointer; transition: 0.15s; white-space: nowrap; text-decoration: none; }
.btn:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-primary   { background: var(--amber); color: #000; border-color: var(--amber); }
.btn-primary:hover:not(:disabled) { filter: brightness(1.12); }
.btn-secondary { background: transparent; color: var(--muted); border-color: var(--border); }
.btn-secondary:hover:not(:disabled) { border-color: var(--amber); color: var(--amber); background: var(--amber-glow); }
.btn-icon { width: 32px; height: 32px; justify-content: center; padding: 0; border-radius: var(--r); background: transparent; border: 1px solid var(--border); color: var(--muted); }
.btn-icon:hover { border-color: var(--amber); color: var(--amber); }
.btn-icon svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 1.5; }

/* ── Floating Toolbox (Bottom) ──────────────────────────────────────────── */
.floating-toolbox {
    position: fixed; bottom: 10px; left: 50%; transform: translateX(-50%);
    background: rgba(11, 14, 22, 0.95); backdrop-filter: blur(10px); border: 1px solid var(--border);
    border-radius: 24px; display: flex; gap: 4px; padding: 6px 12px;
    z-index: 2000; box-shadow: 0 10px 40px rgba(0,0,0,0.6); overflow-x: auto;
    max-width: 96vw;
}
.tool-btn {
    width: 44px; height: 44px; border-radius: var(--r); background: transparent;
    border: 1px solid transparent; color: var(--muted); cursor: pointer; display: flex;
    flex-direction: column; align-items: center; justify-content: center; gap: 3px;
    transition: all 0.15s; font-size: 1.2rem; flex-shrink: 0;
}
.tool-btn:hover { background: var(--bg-hover); color: var(--text); border-color: var(--border); }
.tool-btn.active { background: var(--amber-glow); color: var(--amber); border-color: var(--amber); }
.tool-btn .tool-label { font-family: var(--font-mono); font-size: 0.45rem; line-height: 1; color: inherit; letter-spacing: 0.5px; }
.tool-sep { width: 1px; height: 30px; margin: 7px 4px; background: var(--border); flex-shrink: 0; }

/* ── Viewport ───────────────────────────────────────────────────────────── */
.stage-viewport {
    position: absolute; top: 52px; bottom: 0; left: 0; right: 0;
    overflow: auto; background: var(--bg-void); display: flex;
    align-items: flex-start; justify-content: center;
    padding: 54px 24px 24px 24px;
}
#canvas-container {
    background: #000;
    box-shadow: 0 0 0 1px rgba(255,255,255,0.08), 0 8px 40px rgba(0,0,0,0.7);
    transform-origin: top center;
    flex-shrink: 0; margin-bottom: 80px; 
}

/* ── Floating Properties Modal ──────────────────────────────────────────── */
.floating-props {
    position: fixed; top: 80px; right: 20px; width: 320px;
    background: var(--bg-base); border: 1px solid var(--border);
    border-radius: var(--r-lg); display: none; flex-direction: column;
    box-shadow: 0 10px 50px rgba(0,0,0,0.8); z-index: 2500;
    max-height: calc(100vh - 160px);
}
.floating-props.active { display: flex; }
.props-header {
    padding: 12px 14px; border-bottom: 1px solid var(--border); flex-shrink: 0;
    font-family: var(--font-mono); font-size: 0.65rem; color: var(--amber); letter-spacing: 1.5px; text-transform: uppercase;
    display: flex; align-items: center; justify-content: space-between;
    cursor: grab; user-select: none; -webkit-user-select: none;
    touch-action: none;
}
.props-header:active { cursor: grabbing; }
.props-body { flex: 1; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 14px; }
.prop-group { display: flex; flex-direction: column; gap: 6px; }
.prop-label { font-family: var(--font-mono); font-size: 0.6rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }

.prop-input { background: var(--bg-float); border: 1px solid var(--border); border-radius: var(--r); padding: 8px 10px; color: var(--text); font-size: 16px; outline: none; width: 100%; transition: border-color 0.15s; }
.prop-input:focus { border-color: var(--amber); }
.prop-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.prop-select { background: var(--bg-float); border: 1px solid var(--border); border-radius: var(--r); padding: 8px 10px; color: var(--text); font-size: 0.85rem; outline: none; width: 100%; }
.prop-select:focus { border-color: var(--amber); }
.prop-textarea { background: var(--bg-float); border: 1px solid var(--border); border-radius: var(--r); padding: 8px 10px; color: var(--text); font-size: 16px; outline: none; width: 100%; min-height: 80px; resize: vertical; font-family: var(--font-body); line-height: 1.5; }
.prop-textarea:focus { border-color: var(--amber); }

.prop-divider { height: 1px; background: var(--border); }
.prop-color-row { display: flex; gap: 8px; align-items: center; }
.prop-color-swatch { width: 36px; height: 36px; border-radius: var(--r); border: 1px solid var(--border); cursor: pointer; flex-shrink: 0; }
.prop-section-title { font-family: var(--font-mono); font-size: 0.6rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1.5px; padding-bottom: 4px; border-bottom: 1px solid var(--border); }

/* No selection state */
#noSelectionMsg { text-align: center; padding: 30px 14px; color: var(--muted); font-size: 0.8rem; line-height: 1.8; }

/* ── Layer list ──────────────────────────────────────── */
.layers-section { flex-shrink: 0; border-top: 1px solid var(--border); }
.layers-header { padding: 10px 14px; font-family: var(--font-mono); font-size: 0.6rem; color: var(--amber); text-transform: uppercase; letter-spacing: 1px; display: flex; justify-content: space-between; align-items: center; }
.layers-list { max-height: 220px; overflow-y: auto; }
.layer-item { display: flex; align-items: center; gap: 8px; padding: 7px 14px; border-bottom: 1px solid var(--border); cursor: pointer; transition: 0.12s; font-size: 0.78rem; }
.layer-item:hover { background: var(--bg-hover); }
.layer-item.active { background: var(--amber-glow); color: var(--amber); }
.layer-item-icon { font-size: 0.9rem; flex-shrink: 0; }
.layer-item-name { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.layer-item-del { background: transparent; border: none; color: var(--muted); cursor: pointer; font-size: 0.9rem; padding: 2px 4px; border-radius: 3px; transition: 0.12s; }
.layer-item-del:hover { color: var(--text); }
.layer-item-del.del-red:hover { color: var(--red); }

/* ── Status bar ──────────────────────────────────────────────────── */
.status-bar {
    position: fixed; top: 52px; right: 0;
    height: 48px; background: rgba(11, 14, 22, 0.82); backdrop-filter: blur(8px);
    display: flex; align-items: center; padding: 0 20px; gap: 20px;
    border-bottom-left-radius: 16px; z-index: 500; box-shadow: -2px 2px 12px rgba(0,0,0,0.35);
}
.status-item { font-family: var(--font-mono); font-size: 0.78rem; color: var(--muted); display: flex; align-items: center; gap: 6px; }
.status-item span { color: var(--text); }
.status-bar button { font-family: var(--font-mono); font-size: 0.75rem !important; padding: 3px 6px; border-radius: 4px; transition: color 0.15s; }
.status-bar #statusZoom { font-size: 0.8rem; color: var(--amber); min-width: 42px; text-align: center; }

/* ── Modals ──────────────────────────────────────────────────────────────── */
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 300000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.modal-backdrop.active { display: flex; }
.modal-box { width: 100%; max-width: 500px; background: var(--bg-raised); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px; margin: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.6); }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.modal-title { font-family: var(--font-mono); font-size: 0.8rem; font-weight: 700; color: var(--amber); text-transform: uppercase; letter-spacing: 1px; }
.modal-close { background: transparent; border: none; color: var(--muted); cursor: pointer; font-size: 1.2rem; line-height: 1; }
.form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
.form-label { font-family: var(--font-mono); font-size: 0.6rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }

/* ── Arrangement sidebar ─────────────────────────────────────────────────── */
.arr-list { overflow-y: auto; flex: 1; padding: 10px 0; display: flex; flex-direction: column; gap: 6px; }
.arr-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: var(--bg-float); border: 1px solid var(--border); border-radius: var(--r); cursor: pointer; transition: 0.12s; }
.arr-item:hover { border-color: var(--amber); }
.arr-item.active { background: var(--amber-glow); border-color: var(--amber); }
.arr-item-name { flex: 1; font-size: 0.82rem; color: var(--text); }
.arr-item-meta { font-family: var(--font-mono); font-size: 0.6rem; color: var(--muted); }

/* ── Full Filter Forge Modal UI ────────────────────────────────────────── */
.compose-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 300000; display: none; align-items: flex-end; justify-content: center; }
.compose-modal-backdrop.active { display: flex; }
.compose-modal { width: 100%; max-width: 800px; background: var(--bg-raised); border: 1px solid var(--border); border-bottom: none; border-radius: 14px 14px 0 0; box-shadow: 0 -8px 40px rgba(0,0,0,0.6); animation: slideUp 0.22s ease; height: 65vh; max-height: 85vh; resize: vertical; overflow: hidden; display: flex; flex-direction: column; }
.cm-handle { text-align: center; padding: 10px 0 4px; cursor: pointer; flex-shrink:0; }
.cm-handle-bar { display: inline-block; width: 40px; height: 4px; background: var(--border); border-radius: 2px; }
.cm-header { padding: 6px 16px 10px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.cm-title { font-size: 0.9rem; font-weight: 700; color: var(--teal); text-transform: uppercase; letter-spacing: 1px; font-family:'Space Mono', monospace; }
.cm-close-btn { background: transparent; border: 1px solid var(--border); color: var(--muted); border-radius: 4px; width: 26px; height: 26px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.cm-close-btn:hover { color: var(--text); border-color: var(--text); }
.forge-filters-bar { padding: 6px 12px; display: flex; gap: 6px; align-items: center; border-bottom: 1px solid var(--border); overflow-x: auto; flex-shrink:0; min-height:34px; scrollbar-width:none; }
.forge-filters-bar::-webkit-scrollbar { display:none; }
.forge-pill { background: rgba(58,181,200,0.15); border: 1px solid rgba(58,181,200,0.3); color: var(--teal); padding: 3px 8px; border-radius: 20px; font-size: 0.65rem; display: flex; align-items: center; gap: 6px; font-weight: bold; white-space:nowrap; }
.forge-pill-close { cursor: pointer; font-size: 0.8rem; opacity: 0.7; }
.forge-pill-close:hover { opacity: 1; color: #ef4444; }
.cm-body { display: flex; flex: 1; min-height: 0; padding: 0; }
.forge-sidebar { width: 120px; border-right: 1px solid var(--border); padding: 8px 6px; display: flex; flex-direction: column; gap: 4px; overflow-y: auto; flex-shrink: 0; }
.forge-sidebar-btn { width: 100%; padding: 8px; background: transparent; border: none; color: var(--muted); text-align: left; cursor: pointer; border-radius: 6px; font-weight: 600; font-size: 0.75rem; transition: all 0.15s; }
.forge-sidebar-btn:hover { background: rgba(255,255,255,0.05); color: var(--text); }
.forge-sidebar-btn.active { background: rgba(58,181,200,0.15); color: var(--teal); }
.forge-content { flex: 1; padding: 12px; overflow-y: auto; position: relative; }
.forge-tab-pane { display: none; flex-direction: column; gap: 8px; }
.forge-tab-pane.active { display: flex; }
.ff-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--muted); letter-spacing: 1px; }
.ff-dropdown { border: 1px solid var(--border); border-radius: 4px; background: var(--card); max-height: 140px; overflow-y: auto; display: none; }
.ff-dropdown.open { display: block; }
.ff-dropdown-item { padding: 8px 10px; font-size: 0.75rem; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text); display: flex; justify-content: space-between; align-items:center; }
.ff-dropdown-item:hover { background: rgba(58,181,200,0.1); }
.ff-result-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.ff-result-card { border: 2px solid var(--border); border-radius: 5px; background: var(--card); overflow: hidden; position: relative; aspect-ratio: 1; transition: border-color 0.15s; cursor: pointer; }
.ff-result-card:hover { border-color: var(--teal); }
.ff-result-card img { width: 100%; height: 100%; object-fit: cover; pointer-events: none; }
.ff-result-label { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.75); color: #fff; font-size: 0.6rem; padding: 3px 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; pointer-events: none; font-family: var(--font-mono); }
.forge-result-empty { grid-column: 1 / -1; text-align: center; padding: 30px; color: var(--muted); font-size: 0.8rem; }

/* ── Canvas settings modal override ────────────────────────────────────── */
.canvas-new-btn { background: var(--amber); color: #000; border: none; border-radius: var(--r); padding: 10px; width: 100%; font-family: var(--font-mono); font-size: 0.7rem; font-weight: 700; cursor: pointer; letter-spacing: 1px; text-transform: uppercase; margin-top: 4px; transition: 0.15s; }
.canvas-new-btn:hover { filter: brightness(1.12); }
.existing-canvas-list { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; max-height: 200px; overflow-y: auto; }
.existing-canvas-item { display: flex; align-items: center; gap: 10px; padding: 8px 10px; background: var(--bg-float); border: 1px solid var(--border); border-radius: var(--r); cursor: pointer; transition: 0.12s; text-decoration: none; }
.existing-canvas-item:hover { border-color: var(--amber); }

/* ── Toast ───────────────────────────────────────────────────────────────── */
#toast-container { position: fixed; bottom: 90px; left: 50%; transform: translateX(-50%); z-index: 9999999; display: flex; flex-direction: column; gap: 8px; pointer-events: none; width: max-content; max-width: 90vw; }
.toast { background: var(--bg-float); border: 1px solid var(--border-mid); border-radius: var(--r); padding: 10px 18px; font-size: 0.8rem; color: var(--text); box-shadow: 0 8px 24px rgba(0,0,0,0.5); animation: toastIn 0.2s ease; white-space: nowrap; }
.toast.success { border-left: 3px solid var(--green); }
.toast.error   { border-left: 3px solid var(--red); }
.toast.info    { border-left: 3px solid var(--teal); }
@keyframes toastIn { from { transform: translateY(10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>

<!-- ── Main Layout ──────────────────────────────────────────────────────────── -->
<div class="bang-layout">

    <!-- Topbar -->
    <header class="topbar">
        <a href="bang.php?composite_id=<?= $compositeId ?>" class="topbar-brand">
            💥 <span>BANG!</span>
            <span class="badge">Comic Composer</span>
        </a>
        <div class="topbar-sep"></div>
        <div class="topbar-label"><?= htmlspecialchars($composite['name']) ?></div>

        <div class="topbar-right">
            <button class="btn btn-secondary" id="btnUndo" onclick="undo()" title="Undo (Ctrl+Z)" disabled>
                <i class="bi bi-arrow-counterclockwise"></i>
            </button>
            <button class="btn btn-secondary" id="btnRedo" onclick="redo()" title="Redo (Ctrl+Y)" disabled>
                <i class="bi bi-arrow-clockwise"></i>
            </button>
            <div class="topbar-sep" style="height:20px; margin:0 4px;"></div>

            <button class="btn btn-secondary" onclick="openForgeModal()" title="Add Frame from Library">
                <i class="bi bi-grid"></i> Frames
            </button>
            <button class="btn btn-secondary" onclick="openArrangements()" title="Arrangements">
                <i class="bi bi-layers"></i> Saves
            </button>
            <button class="btn btn-secondary" onclick="openCanvasSettings()" title="Canvas Settings">
                <svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2z"/><path d="M12 8v4l3 3"/></svg>
                Canvas
            </button>
            <button class="btn btn-secondary" onclick="saveArrangement()" title="Save">
                <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Save
            </button>
            <button class="btn btn-primary" onclick="exportRender()" title="Export PNG">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export PNG
            </button>
        </div>
    </header>

    <!-- Stage Viewport -->
    <div class="stage-viewport" id="stageViewport">
        <div id="canvas-container"></div>
    </div>

    <!-- Floating Toolbox (Bottom) -->
    <div class="floating-toolbox" id="toolbox">
        <button class="tool-btn active" id="tool-select" onclick="setTool('select')" title="Select / Move (V)">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="1.5"><path d="M5 3l14 9-7 2-3 7-4-18z"/></svg>
            <span class="tool-label">Select</span>
        </button>
        <div class="tool-sep"></div>
        <button class="tool-btn" id="tool-image" onclick="openForgeModal()" title="Add Image from library">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            <span class="tool-label">Image</span>
        </button>
        <button class="tool-btn" id="tool-balloon" onclick="addBalloon('classic_oval')" title="Dialogue Balloon">
            🗨
            <span class="tool-label">Balloon</span>
        </button>
        <div class="tool-sep"></div>
        <button class="tool-btn" id="tool-sfx" onclick="addSFX()" title="Sound Effect Text">
            ⚡
            <span class="tool-label">SFX</span>
        </button>
        <button class="tool-btn" id="tool-caption" onclick="addCaption()" title="Caption Box">
            📝
            <span class="tool-label">Caption</span>
        </button>
        <div class="tool-sep"></div>
        <button class="tool-btn" id="tool-rect" onclick="addPanel()" title="Panel Rectangle">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="1"/></svg>
            <span class="tool-label">Panel</span>
        </button>
        <button class="tool-btn" id="tool-speed" onclick="addSpeedLines()" title="Speed Lines">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="1.5"><line x1="12" y1="12" x2="2" y2="2"/><line x1="12" y1="12" x2="22" y2="2"/><line x1="12" y1="12" x2="22" y2="22"/><line x1="12" y1="12" x2="2" y2="22"/><line x1="12" y1="12" x2="12" y2="1"/><line x1="12" y1="12" x2="23" y2="12"/><line x1="12" y1="12" x2="12" y2="23"/><line x1="12" y1="12" x2="1" y2="12"/></svg>
            <span class="tool-label">Speed</span>
        </button>
        <button class="tool-btn" id="tool-impact" onclick="addImpactFrame()" title="Impact Frame">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="1.5"><polygon points="12,2 15,9 22,9 17,14 19,21 12,17 5,21 7,14 2,9 9,9"/></svg>
            <span class="tool-label">Impact</span>
        </button>
        <div class="tool-sep"></div>
        <button class="tool-btn" id="tool-presets" onclick="openPresetsModal()" title="Layout Presets">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="1.5"><rect x="2" y="2" width="9" height="9"/><rect x="13" y="2" width="9" height="9"/><rect x="2" y="13" width="9" height="9"/><rect x="13" y="13" width="9" height="9"/></svg>
            <span class="tool-label">Presets</span>
        </button>
        <button class="tool-btn" id="tool-delete" onclick="deleteSelected()" title="Delete selected (Del)">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="1.5"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            <span class="tool-label">Delete</span>
        </button>
        <div class="tool-sep"></div>
        <button class="tool-btn" onclick="toggleProps()" title="Toggle Properties Panel">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="1.5"><path d="M4 21v-7m0-4V3m8 18v-9m0-4V3m8 18v-5m0-4V3M1 14h6m2-6h6m2 8h6"/></svg>
            <span class="tool-label">Props</span>
        </button>
    </div>

    <!-- Floating Properties Panel -->
    <div class="floating-props" id="propsPanel" style="display:none;">
        <div class="props-header" id="propsHeaderDrag">
            Properties (Drag me)
            <button class="btn-icon" onclick="toggleProps()" style="width:24px;height:24px;">
                ✕
            </button>
        </div>
        <div class="props-body" id="propsBody">
            <div id="noSelectionMsg">
                <div style="font-size:1.8rem; margin-bottom:8px;">🖱</div>
                <div>Select an element<br>to edit its properties.</div>
                <div style="margin-top:12px; font-size:0.7rem; opacity:0.6;">Or use the tools below<br>to add new elements.</div>
            </div>
        </div>

        <!-- Layer List -->
        <div class="layers-section">
            <div class="layers-header">
                Layers <span id="layerCount" style="font-size:0.6rem; color:var(--muted); margin-left:4px;">0</span>
                <label style="margin-left:auto; display:flex; align-items:center; gap:4px; font-size:0.6rem; color:var(--text); cursor:pointer; text-transform:none;">
                    <input type="checkbox" id="keepRatioCheck" checked onchange="tr.keepRatio(this.checked); uiLayer.batchDraw(); saveState();"> Keep Ratio
                </label>
            </div>
            <div class="layers-list" id="layersList"></div>
        </div>
    </div>

    <!-- Status Bar (Top Right) -->
    <div class="status-bar">
        <div class="status-item"><i class="bi bi-arrows-fullscreen"></i> <span id="statusSize">1024 × 1448</span></div>
        <div class="status-item"><i class="bi bi-layers"></i> <span id="statusElemCount">0</span></div>
        <div class="status-item" id="statusSelected" style="display:none;"><i class="bi bi-cursor"></i> <span id="statusSelectedName">—</span></div>
        <div class="status-item" style="margin-left:10px;">
            <button style="background:transparent;border:none;color:var(--muted);cursor:pointer;font-family:var(--font-mono);font-size:0.6rem;" onclick="zoomIn()">＋ Zoom</button>
            <span id="statusZoom" style="color:var(--text); margin:0 4px;">100%</span>
            <button style="background:transparent;border:none;color:var(--muted);cursor:pointer;font-family:var(--font-mono);font-size:0.6rem;" onclick="zoomOut()">－</button>
            <button style="background:transparent;border:none;color:var(--muted);cursor:pointer;font-family:var(--font-mono);font-size:0.6rem; margin-left:6px;" onclick="zoomFit()">Fit</button>
        </div>
    </div>

</div><!-- /.bang-layout -->
<div id="toast-container"></div>


<!-- ── Canvas Settings Modal ─────────────────────────────────────────────── -->
<div class="modal-backdrop" id="canvasModal" onmousedown="if(event.target===this) closeCanvasSettings()">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <div class="modal-title">⚙ Canvas Settings</div>
            <button class="modal-close" onclick="closeCanvasSettings()">✕</button>
        </div>

        <div class="form-group">
            <label class="form-label">Strip Name</label>
            <input type="text" class="prop-input" id="csName" placeholder="Untitled Panel Strip">
        </div>
        <div class="prop-row">
            <div class="form-group">
                <label class="form-label">Width (px)</label>
                <input type="number" class="prop-input" id="csWidth" value="1024">
            </div>
            <div class="form-group">
                <label class="form-label">Height (px)</label>
                <input type="number" class="prop-input" id="csHeight" value="1448">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Background Color</label>
            <div class="prop-color-row">
                <input type="color" id="csBgColorPicker" value="#000000" style="width:36px;height:36px;border:1px solid var(--border);border-radius:var(--r);cursor:pointer;padding:2px;" oninput="document.getElementById('csBgColor').value=this.value">
                <input type="text" class="prop-input" id="csBgColor" value="#000000" placeholder="#000000" oninput="document.getElementById('csBgColorPicker').value=this.value">
            </div>
        </div>

        <div style="margin-top:8px; padding-top:14px; border-top:1px solid var(--border);">
            <button class="canvas-new-btn" onclick="applyCanvasSettings()">Apply to Canvas</button>
        </div>

        <?php if (!empty($existingCanvases)): ?>
        <div style="margin-top:14px; padding-top:12px; border-top:1px solid var(--border);">
            <div class="form-label" style="margin-bottom:8px;">Switch to Existing Strip</div>
            <div class="existing-canvas-list">
                <?php foreach ($existingCanvases as $ec): ?>
                <a href="bang.php?composite_id=<?= $compositeId ?>&canvas_id=<?= $ec['id'] ?>" class="existing-canvas-item">
                    <span style="font-family:var(--font-mono);font-size:0.6rem;color:var(--amber);">#<?= $ec['id'] ?></span>
                    <span style="flex:1;font-size:0.82rem;"><?= htmlspecialchars($ec['name']) ?></span>
                    <span style="font-size:0.6rem;color:var(--muted);"><?= $ec['canvas_width'] ?>×<?= $ec['canvas_height'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Arrangements Modal ────────────────────────────────────────────────── -->
<div class="modal-backdrop" id="arrModal" onmousedown="if(event.target===this) closeArrangements()">
    <div class="modal-box" style="max-width:380px;">
        <div class="modal-header">
            <div class="modal-title">🗂 Saved Arrangements</div>
            <button class="modal-close" onclick="closeArrangements()">✕</button>
        </div>
        <div class="arr-list" id="arrList" style="max-height:300px; overflow-y:auto;"></div>
        <div style="display:flex;gap:8px;margin-top:14px;border-top:1px solid var(--border);padding-top:14px;">
            <input type="text" class="prop-input" id="arrNewName" placeholder="New arrangement name…" style="flex:1;">
            <button class="btn btn-primary" onclick="saveArrangementAs()">Save As</button>
        </div>
    </div>
</div>

<!-- ── Layout Presets Modal ──────────────────────────────────────────────── -->
<div class="modal-backdrop" id="presetsModal" onmousedown="if(event.target===this) closePresetsModal()">
    <div class="modal-box" style="max-width:400px;">
        <div class="modal-header">
            <div class="modal-title">⊞ Layout Presets</div>
            <button class="modal-close" onclick="closePresetsModal()">✕</button>
        </div>

        <div class="form-group">
            <label class="form-label">Preset</label>
            <select class="prop-select" id="presetSelect" onchange="_renderPresetParams(this.value)" style="font-size:16px;">
                <option value="regular_grid">Regular Grid</option>
                <option value="pyramid">Pyramid</option>
                <option value="anchor_panel">Anchor Panel</option>
                <option value="mosaic_insert">Mosaic Insert</option>
                <option value="vertical_stack">Vertical Stack</option>
                <option value="diagonal_cascade" selected>↗ Diagonal Cascade</option>
            </select>
        </div>

        <div id="presetParamsBody" style="display:flex;flex-direction:column;gap:10px;margin-top:4px;"></div>

        <div style="margin-top:16px; border-top:1px solid var(--border); padding-top:14px;">
            <button class="canvas-new-btn" onclick="applyPresetLayout()">Apply Preset</button>
        </div>
    </div>
</div>

<!-- ── Full Filter Forge Modal UI ─────────────────────────────────────────── -->
<div class="compose-modal-backdrop" id="ffBackdrop" onmousedown="if(event.target===this)closeForgeModal()">
    <div class="compose-modal" id="ffModal">
        <div class="cm-handle" onclick="closeForgeModal()"><div class="cm-handle-bar"></div></div>
        <div class="cm-header">
            <div class="cm-title"><i class="bi bi-funnel"></i> Filter Forge</div>
            <button class="cm-close-btn" onclick="closeForgeModal()">✕</button>
        </div>
        
        <!-- Active Filters Bar -->
        <div class="forge-filters-bar" id="ffActiveFilters">
            <div style="font-size:0.7rem; color:var(--muted); font-style:italic;">No active filters.</div>
        </div>

        <div class="cm-body">
            <!-- Sidebar Tabs -->
            <div class="forge-sidebar">
                <button class="forge-sidebar-btn active" data-tab="fuzz" onclick="switchForgeTab('fuzz')">🧩 Fuzz</button>
                <button class="forge-sidebar-btn" data-tab="doc" onclick="switchForgeTab('doc')">📜 Doc</button>
                <button class="forge-sidebar-btn" data-tab="kg" onclick="switchForgeTab('kg')">🌳 KG</button>
                <button class="forge-sidebar-btn" data-tab="seq" onclick="switchForgeTab('seq')">🎬 Seq</button>
                <button class="forge-sidebar-btn" data-tab="storyboard" onclick="switchForgeTab('storyboard')">🖼️ Board</button>
                <button class="forge-sidebar-btn" data-tab="map_run" onclick="switchForgeTab('map_run')">🗺️ Run</button>
                <button class="forge-sidebar-btn" data-tab="vector" onclick="switchForgeTab('vector')">🔍 Semantic</button>
                <button class="forge-sidebar-btn" data-tab="id" onclick="switchForgeTab('id')">🔢 ID/Text</button>
                <hr style="border-color:var(--border); margin:4px 0;">
                <button class="forge-sidebar-btn" data-tab="results" onclick="switchForgeTab('results')" style="color:var(--amber); font-weight:bold;">▶ Results</button>
            </div>

            <!-- Content Area -->
            <div class="forge-content">
                <!-- FUZZ -->
                <div class="forge-tab-pane active" id="pane-fuzz">
                    <label class="ff-label">Fuzz Concept</label>
                    <input type="text" id="ffSearch-fuzz" class="prop-input" placeholder="Search fuzz..." oninput="ffDebounceSearch('fuzz', this.value)">
                    <div class="ff-dropdown" id="ffDrop-fuzz"></div>
                </div>
                <!-- DOC -->
                <div class="forge-tab-pane" id="pane-doc">
                    <label class="ff-label">Lore Document</label>
                    <input type="text" id="ffSearch-doc" class="prop-input" placeholder="Search docs..." oninput="ffDebounceSearch('doc', this.value)">
                    <div class="ff-dropdown" id="ffDrop-doc"></div>
                </div>
                <!-- KG -->
                <div class="forge-tab-pane" id="pane-kg">
                    <label class="ff-label">KG Node</label>
                    <input type="text" id="ffSearch-kg" class="prop-input" placeholder="Search KG nodes..." oninput="ffDebounceSearch('kg', this.value)">
                    <div class="ff-dropdown" id="ffDrop-kg"></div>
                </div>
                <!-- SEQ -->
                <div class="forge-tab-pane" id="pane-seq">
                    <label class="ff-label">Narrative Sequence</label>
                    <input type="text" id="ffSearch-seq" class="prop-input" placeholder="Search sequences..." oninput="ffDebounceSearch('seq', this.value)">
                    <div class="ff-dropdown" id="ffDrop-seq"></div>
                </div>
                <!-- STORYBOARD -->
                <div class="forge-tab-pane" id="pane-storyboard">
                    <label class="ff-label">Storyboard</label>
                    <input type="text" id="ffSearch-storyboard" class="prop-input" placeholder="Search storyboards..." onfocus="ffDebounceSearch('storyboard', this.value)" oninput="ffDebounceSearch('storyboard', this.value)">
                    <div class="ff-dropdown" id="ffDrop-storyboard"></div>
                </div>
                <!-- MAP RUN -->
                <div class="forge-tab-pane" id="pane-map_run">
                    <label class="ff-label">Map Run</label>
                    <input type="text" id="ffSearch-map_run" class="prop-input" placeholder="Search map runs..." oninput="ffDebounceSearch('map_run', this.value)">
                    <div class="ff-dropdown" id="ffDrop-map_run"></div>
                </div>
                <!-- VECTOR -->
                <div class="forge-tab-pane" id="pane-vector">
                    <label class="ff-label">Semantic / Vector Search</label>
                    <textarea id="ffSearch-vector" class="prop-textarea" style="height:80px; resize:none; margin-bottom:8px;" placeholder="Describe visually..."></textarea>
                    <button class="btn btn-primary" style="width:100%; justify-content:center;" onclick="ffApplyVector()">Apply Semantic</button>
                </div>
                <!-- TEXT/ID -->
                <div class="forge-tab-pane" id="pane-id">
                    <label class="ff-label">Text Search</label>
                    <input type="text" id="ffSearch-text" class="prop-input" placeholder="Name or description...">
                    <label class="ff-label" style="margin-top:12px;">Sketch ID</label>
                    <input type="number" id="ffSearch-sketchId" class="prop-input" placeholder="e.g. 1042">
                    <label class="ff-label" style="margin-top:12px;">Frame ID</label>
                    <input type="number" id="ffSearch-frameId" class="prop-input" placeholder="e.g. 5503">
                    <button class="btn btn-primary" style="margin-top:12px; width:100%; justify-content:center;" onclick="ffApplyTextId()">Apply Text/ID</button>
                </div>
                <!-- RESULTS (3x3 Grid) -->
                <div class="forge-tab-pane" id="pane-results">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span style="font-size:0.75rem; color:var(--muted);" id="ffResultMeta">Results will appear here.</span>
                        <button class="btn btn-secondary" style="padding:4px 8px; font-size:0.65rem;" onclick="runForgeSearch(ffCurrentPage)">↻ Refresh</button>
                    </div>
                    <!-- Fixed 3x3 Grid Layout -->
                    <div class="ff-result-grid" id="ffResultGrid"></div>
                    
                    <div id="ffPagination" style="display:none; justify-content:space-between; align-items:center; margin-top:12px;">
                        <button class="btn btn-secondary" id="ffPrevBtn" onclick="runForgeSearch(ffCurrentPage - 1)">« Prev</button>
                        <span style="font-size:0.75rem; color:var(--muted);" id="ffPageLabel">Page 1</span>
                        <button class="btn btn-secondary" id="ffNextBtn" onclick="runForgeSearch(ffCurrentPage + 1)">Next »</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── JavaScript ────────────────────────────────────────────────────────── -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
'use strict';

// ── Constants ────────────────────────────────────────────────────────────────
const COMPOSITE_ID = <?= $compositeId ?>;
const FONTS = <?= $jsFonts ?>;
const COMPOSITE_FRAMES = <?= $jsFrames ?>;

let CANVAS_ID   = <?= $jsCanvasId  ?> || null;
let ARR_ID      = <?= $jsArrId     ?> || null;
let CANVAS_W    = <?= $canvas ? (int)$canvas['canvas_width']  : 1024  ?>;
let CANVAS_H    = <?= $canvas ? (int)$canvas['canvas_height'] : 1448 ?>;
let BG_COLOR    = '<?= $canvas ? htmlspecialchars($canvas['bg_color'] ?? '#000000') : '#000000' ?>';
let CANVAS_NAME = '<?= $canvas ? htmlspecialchars($canvas['name'] ?? '') : '' ?>';

let currentZoom = 1.0;
let selectedNode = null;
let elementCounter = 0;
let assigningToPanel = null;

// ── History & Undo/Redo System ───────────────────────────────────────────────
let historyStack = [];
let historyIndex = -1;
let isRestoring = false;
let stateTimer = null;

function saveState() {
    if (isRestoring) return;
    const currentState = getSceneJSON();
    if (historyIndex >= 0 && historyStack[historyIndex] === currentState) return;
    
    historyStack = historyStack.slice(0, historyIndex + 1);
    historyStack.push(currentState);
    historyIndex++;
    updateUndoRedoUI();
}

function debounceSaveState() {
    clearTimeout(stateTimer);
    stateTimer = setTimeout(saveState, 400);
}

function undo() {
    if (isRestoring) return;
    if (historyIndex > 0) {
        historyIndex--;
        updateUndoRedoUI();
        restoreState(historyStack[historyIndex]);
    }
}

function redo() {
    if (isRestoring) return;
    if (historyIndex < historyStack.length - 1) {
        historyIndex++;
        updateUndoRedoUI();
        restoreState(historyStack[historyIndex]);
    }
}

function restoreState(json) {
    deselect();
    loadSceneJSON(json, true); 
}

function updateUndoRedoUI() {
    document.getElementById('btnUndo').disabled = (historyIndex <= 0);
    document.getElementById('btnRedo').disabled = (historyIndex >= historyStack.length - 1);
}

document.getElementById('propsBody').addEventListener('input', (e) => {
    if (e.target.matches('input, textarea, select')) debounceSaveState();
});
document.getElementById('propsBody').addEventListener('change', (e) => {
    if (e.target.matches('input, textarea, select')) saveState();
});

// ── Floating Props Drag Logic (smooth, buttery) ──────────────────────────────
const pPanel = document.getElementById('propsPanel');
const pHeader = document.getElementById('propsHeaderDrag');
let pDragging = false, pX = 0, pY = 0;
let pRafId = null, pTargetLeft = 0, pTargetTop = 0;

function pAnimateDrag() {
    const rect = pPanel.getBoundingClientRect();
    const curLeft = parseFloat(pPanel.style.left) || rect.left;
    const curTop  = parseFloat(pPanel.style.top)  || rect.top;
    const dx = pTargetLeft - curLeft;
    const dy = pTargetTop  - curTop;
    const nextLeft = Math.abs(dx) < 0.5 ? pTargetLeft : curLeft + dx * 0.35;
    const nextTop  = Math.abs(dy) < 0.5 ? pTargetTop  : curTop  + dy * 0.35;
    pPanel.style.left = nextLeft + 'px';
    pPanel.style.top  = nextTop  + 'px';
    if (pDragging || Math.abs(dx) > 0.5 || Math.abs(dy) > 0.5) {
        pRafId = requestAnimationFrame(pAnimateDrag);
    } else {
        pRafId = null;
    }
}

pHeader.addEventListener('pointerdown', e => {
    pDragging = true;
    pHeader.style.cursor = 'grabbing';
    const rect = pPanel.getBoundingClientRect();
    pX = e.clientX - rect.left;
    pY = e.clientY - rect.top;
    pPanel.style.left      = rect.left + 'px';
    pPanel.style.top       = rect.top  + 'px';
    pPanel.style.right     = 'auto';
    pPanel.style.bottom    = 'auto';
    pPanel.style.transform = 'none';
    pTargetLeft = rect.left;
    pTargetTop  = rect.top;
    pHeader.setPointerCapture(e.pointerId);
    if (!pRafId) pRafId = requestAnimationFrame(pAnimateDrag);
});

pHeader.addEventListener('pointermove', e => {
    if (!pDragging) return;
    const vw = window.innerWidth, vh = window.innerHeight;
    const pw = pPanel.offsetWidth,  ph = pPanel.offsetHeight;
    pTargetLeft = Math.max(0, Math.min(vw - pw, e.clientX - pX));
    pTargetTop  = Math.max(0, Math.min(vh - ph, e.clientY - pY));
});

pHeader.addEventListener('pointerup', e => {
    pDragging = false;
    pHeader.style.cursor = 'move';
    pHeader.releasePointerCapture(e.pointerId);
});

// ── Konva Stage ──────────────────────────────────────────────────────────────
const stage = new Konva.Stage({
    container: 'canvas-container',
    width: CANVAS_W,
    height: CANVAS_H,
});

const bgLayer      = new Konva.Layer();
const contentLayer = new Konva.Layer();
const uiLayer      = new Konva.Layer();
stage.add(bgLayer, contentLayer, uiLayer);

// Background rect
const bgRect = new Konva.Rect({
    x: 0, y: 0, width: CANVAS_W, height: CANVAS_H,
    fill: BG_COLOR, listening: false,
});
bgLayer.add(bgRect);
bgLayer.draw();

// Transformer 
const tr = new Konva.Transformer({
    nodes: [],
    keepRatio: true,
    anchorSize: 24, 
    boundBoxFunc: (oldBox, newBox) => {
        if (Math.abs(newBox.width) < 10 || Math.abs(newBox.height) < 10) return oldBox;
        return newBox;
    },
});
uiLayer.add(tr);

// Fire save state on any drag or transform ends inside the stage
contentLayer.on('dragend', (e) => {
    if (e.target.hasName('element') || e.target.hasName('innerImage')) saveState();
});
tr.on('transformend', () => saveState());

// Group Locked Elements Drag Syncing
contentLayer.on('dragstart', (e) => {
    const node = e.target;
    if (node.hasName('element') && node.getAttr('isLocked')) {
        const lockedNodes = contentLayer.find('.element').filter(n => n.getAttr('isLocked'));
        lockedNodes.forEach(n => {
            n.setAttr('dragStartX', n.x());
            n.setAttr('dragStartY', n.y());
        });
    }
});

contentLayer.on('dragmove', (e) => {
    const node = e.target;
    if (node.hasName('element') && node.getAttr('isLocked')) {
        const dx = node.x() - node.getAttr('dragStartX');
        const dy = node.y() - node.getAttr('dragStartY');
        
        const lockedNodes = contentLayer.find('.element').filter(n => n.getAttr('isLocked') && n !== node);
        lockedNodes.forEach(n => {
            n.x(n.getAttr('dragStartX') + dx);
            n.y(n.getAttr('dragStartY') + dy);
        });
    }
});

// Smart Caption Reflow Listener
contentLayer.on('transform', (e) => {
    const node = e.target;
    if (node.getAttr('elemType') === 'caption' && node.getAttr('reflowText')) {
        const w = Math.max(50, node.width() * node.scaleX());
        node.scaleX(1);
        node.scaleY(1);
        node.width(w);
        node.setAttr('captionW', w);
        applyCaptionPadding(node);
    }
});

stage.on('click tap', e => {
    if (e.target === stage || e.target === bgRect) {
        deselect();
    }
});

function deselect() {
    tr.nodes([]);
    selectedNode = null;
    uiLayer.batchDraw();
    showNoSelection();
    updateStatus();
    renderLayerList();
    window.restoreViewportZoom && window.restoreViewportZoom();
}

// ── Zoom ─────────────────────────────────────────────────────────────────────
function applyZoom(z) {
    currentZoom = Math.max(0.1, Math.min(3, z));
    document.getElementById('canvas-container').style.transform = `scale(${currentZoom})`;
    document.getElementById('statusZoom').textContent = Math.round(currentZoom * 100) + '%';
}
function zoomIn()  { applyZoom(currentZoom + 0.1); }
function zoomOut() { applyZoom(currentZoom - 0.1); }
function zoomFit() {
    const vp = document.getElementById('stageViewport');
    const z  = Math.min((vp.clientWidth - 48) / CANVAS_W, (vp.clientHeight - 48) / CANVAS_H);
    applyZoom(z);
}

// ── Canvas resize helper ─────────────────────────────────────────────────────
function resizeCanvas(w, h, bg) {
    CANVAS_W = w; CANVAS_H = h; BG_COLOR = bg;
    stage.width(w); stage.height(h);
    bgRect.width(w); bgRect.height(h); bgRect.fill(bg);
    bgLayer.batchDraw();
    document.getElementById('statusSize').textContent = `${w} × ${h}`;
    zoomFit();
    saveState();
}

// ── Layer helpers ─────────────────────────────────────────────────────────────
function getAllElements() {
    return contentLayer.find('.element');
}

function renderLayerList() {
    const list = document.getElementById('layersList');
    const els  = getAllElements().slice().reverse();
    document.getElementById('layerCount').textContent = els.length;
    document.getElementById('statusElemCount').textContent = els.length;

    if (!els.length) {
        list.innerHTML = '<div style="padding:12px 14px; font-size:0.75rem; color:var(--muted); font-style:italic;">No elements yet.</div>';
        return;
    }

    list.innerHTML = els.map(el => {
        const isActive = el === selectedNode;
        const isLocked = el.getAttr('isLocked');
        const type = el.getAttr('elemType') || 'image';
        // Map icon based on type. (Legacy gitaigo or diagonal mapped appropriately)
        const icon = { image:'🖼', balloon:'🗨', shout:'💢', thought:'💭', whisper:'🗯', sfx:'⚡', caption:'📝', panel:'▭', speed_lines:'≡', impact_frame:'✸', gitaigo:'〜', diagonal_panel:'╱' }[type] || '▫';
        const name = el.getAttr('elemName') || type || 'Element';
        return `<div class="layer-item ${isActive ? 'active' : ''}" onclick="selectNodeById('${el.id()}')">
            <span class="layer-item-icon">${icon}</span>
            <span class="layer-item-name" style="opacity:${isLocked ? 0.6 : 1};">${escHtml(name)}</span>
            <button class="layer-item-del" onclick="event.stopPropagation(); toggleLock('${el.id()}')" title="Lock/Unlock">${isLocked ? '🔒' : '🔓'}</button>
            <button class="layer-item-del del-red" onclick="event.stopPropagation(); deleteById('${el.id()}')" title="Delete">✕</button>
        </div>`;
    }).join('');
}

function toggleLock(id) {
    const node = contentLayer.findOne('#' + id);
    if (!node) return;
    const locked = !node.getAttr('isLocked');
    node.setAttr('isLocked', locked);
    
    if (selectedNode === node) {
        tr.resizeEnabled(!locked);
        tr.rotateEnabled(!locked);
        renderPropertiesFor(node);
    }
    
    uiLayer.batchDraw();
    saveState();
    renderLayerList();
}

function selectNodeById(id) {
    const node = contentLayer.findOne('#' + id);
    if (node) selectNode(node);
}

function deleteById(id) {
    const node = contentLayer.findOne('#' + id);
    if (node) { node.destroy(); deselect(); contentLayer.batchDraw(); renderLayerList(); saveState(); }
}

function deleteSelected() {
    if (selectedNode) { selectedNode.destroy(); deselect(); contentLayer.batchDraw(); renderLayerList(); saveState(); }
}

// ── Select a node ────────────────────────────────────────────────────────────
function selectNode(node) {
    let targetNode = node;
    while(targetNode && !targetNode.hasName('element')) {
        targetNode = targetNode.parent;
    }
    if (!targetNode) targetNode = node;

    selectedNode = targetNode;
    tr.nodes([targetNode]);
    
    const locked = targetNode.getAttr('isLocked');
    tr.resizeEnabled(!locked);
    tr.rotateEnabled(!locked);
    
    uiLayer.batchDraw();
    
    const p = document.getElementById('propsPanel');
    if (p.style.display === 'none' || p.style.display === '') {
        p.style.display = 'flex';
    }
    window.lockViewportZoom && window.lockViewportZoom();
    renderPropertiesFor(targetNode);
    renderLayerList();
    const name = targetNode.getAttr('elemName') || targetNode.getAttr('elemType') || 'Element';
    document.getElementById('statusSelected').style.display = '';
    document.getElementById('statusSelectedName').textContent = name;
}
// ── Unique ID helper ─────────────────────────────────────────────────────────
function uid() { return 'el_' + (++elementCounter) + '_' + Date.now(); }

// ── CSS shorthand padding parser ─────────────────────────────────────────────
function parsePadding(str) {
    const parts = String(str || '12').trim().split(/\s+/).map(Number).filter(n => !isNaN(n));
    if (parts.length === 1) return { top: parts[0], right: parts[0], bottom: parts[0], left: parts[0] };
    if (parts.length === 2) return { top: parts[0], right: parts[1], bottom: parts[0], left: parts[1] };
    if (parts.length === 3) return { top: parts[0], right: parts[1], bottom: parts[2], left: parts[1] };
    return { top: parts[0], right: parts[1], bottom: parts[2], left: parts[3] };
}

// ── Add Image Element ─────────────────────────────────────────────────────────
function addImageElement(frameId, filename, name, pos = null) {
    if (!filename) { openForgeModal(); return; }

    const absUrl = filename.startsWith('/') ? filename : '/' + filename;
    const imgEl  = new Image();
    imgEl.crossOrigin = 'anonymous';
    imgEl.src = absUrl;
    imgEl.onload = () => {
        const scale = (CANVAS_W * 0.5) / imgEl.width;
        const kImg = new Konva.Image({
            id: uid(),
            x: pos ? pos.x : CANVAS_W * 0.1,
            y: pos ? pos.y : 100,
            image: imgEl,
            width: imgEl.width,
            height: imgEl.height,
            scaleX: scale, scaleY: scale,
            draggable: true,
            name: 'element',
        });
        kImg.setAttr('elemType', 'image');
        kImg.setAttr('elemName', name || 'Frame');
        kImg.setAttr('frameId', frameId || 0);
        kImg.setAttr('filename', absUrl);
        kImg.setAttr('isLocked', false);
        kImg.on('click tap', () => selectNode(kImg));
        contentLayer.add(kImg);
        contentLayer.batchDraw();
        selectNode(kImg);
        renderLayerList();
        toast('Image added', 'success');
        saveState();
    };
    imgEl.onerror = () => toast('Could not load image', 'error');
}

// ── Add Balloon ───────────────────────────────────────────────────────────────
function addBalloon(shape) {
    const cx = CANVAS_W / 2 - 120;
    const cy = 200;
    const w  = 240, h = 100;

    const group = new Konva.Group({
        id: uid(), x: cx, y: cy, draggable: true, name: 'element',
    });
    group.setAttr('elemType', 'balloon');
    group.setAttr('elemName', 'Balloon');
    group.setAttr('balloonShape', shape || 'classic_oval');
    group.setAttr('fillColor', '#F5F5F0');
    group.setAttr('strokeColor', '#222222');
    group.setAttr('strokeWidth', 2);
    group.setAttr('tailX', w / 2);
    group.setAttr('tailY', h + 30);
    group.setAttr('balloonW', w);
    group.setAttr('balloonH', h);
    group.setAttr('isLocked', false);

    let initPad = '12';
    if(shape === 'shout_burst' || shape === 'fierce_scream') initPad = '20';
    if(shape === 'thought_cloud') initPad = '16';
    group.setAttr('balloonPadding', initPad);

    const balloon = _buildBalloonShape(shape, w, h, '#F5F5F0', '#222222', 2, w/2, h+30);
    group.add(balloon);

    const _pad = parsePadding(initPad);
    const kText = new Konva.Text({
        x: _pad.left, y: _pad.top,
        width:  Math.max(20, w - _pad.left - _pad.right),
        height: Math.max(10, h - _pad.top - _pad.bottom),
        text: 'Dialogue goes here…',
        fontSize: 16, fontFamily: 'Bangers', fill: '#111111',
        align: 'center', verticalAlign: 'middle', lineHeight: 1.3, wrap: 'word',
        listening: false,
    });
    group.add(kText);
    kText.moveToTop();
    group.setAttr('kTextRef', kText);

    group.on('click tap', () => selectNode(group));
    contentLayer.add(group);
    contentLayer.batchDraw();
    selectNode(group);
    renderLayerList();
    saveState();
}

function _buildBalloonShape(shape, w, h, fill, stroke, sw, tailX, tailY) {
    let shapeFn;
    if (shape === 'classic_oval' || shape === 'oval') {
        shapeFn = (ctx) => {
            ctx.beginPath(); ctx.ellipse(w/2, h/2, w/2, h/2, 0, 0, Math.PI*2); ctx.closePath();
            ctx.moveTo(w/2 - 14, h - sw); ctx.lineTo(tailX, tailY); ctx.lineTo(w/2 + 14, h - sw); ctx.closePath();
        };
    } else if (shape === 'modern_box') {
        shapeFn = (ctx) => {
            const r = 16;
            ctx.beginPath();
            if(ctx.roundRect) ctx.roundRect(0, 0, w, h, r); else ctx.rect(0, 0, w, h);
            ctx.moveTo(w/2 - 20, h - sw); ctx.lineTo(tailX, tailY); ctx.lineTo(w/2, h - sw); ctx.closePath();
        };
    } else if (shape === 'shout_burst' || shape === 'shout') {
        shapeFn = (ctx) => {
            const pts = 16; const cx = w/2, cy = h/2;
            const r1 = Math.min(w,h)/2 + 15; const r2 = r1 * 0.65;
            const sx = w/h;
            ctx.beginPath();
            for (let i = 0; i < pts * 2; i++) {
                const angle = (i * Math.PI) / pts - Math.PI / 2;
                const r = i % 2 === 0 ? r1 : r2;
                if (i === 0) ctx.moveTo(cx + r * Math.cos(angle) * sx, cy + r * Math.sin(angle));
                else ctx.lineTo(cx + r * Math.cos(angle) * sx, cy + r * Math.sin(angle));
            }
            ctx.closePath();
        };
    } else if (shape === 'fierce_scream') {
        shapeFn = (ctx) => {
            const pts = 18; const cx = w/2, cy = h/2;
            const rbOut = Math.min(w,h)/2 + 25; const rbIn = rbOut * 0.5;
            const sx = w/h;
            ctx.beginPath();
            for (let i = 0; i < pts * 2; i++) {
                const angle = (i * Math.PI) / pts - Math.PI / 2;
                const noise = Math.sin(i * 1234.5) * 15;
                const r = i % 2 === 0 ? rbOut + noise : rbIn - Math.abs(noise);
                if (i === 0) ctx.moveTo(cx + r * Math.cos(angle) * sx, cy + r * Math.sin(angle));
                else ctx.lineTo(cx + r * Math.cos(angle) * sx, cy + r * Math.sin(angle));
            }
            ctx.closePath();
        };
    } else if (shape === 'thought_cloud' || shape === 'thought') {
        shapeFn = (ctx) => {
            const pts = 120; const bumps = 11;
            const cx = w/2, cy = h/2; const rx = w/2 * 0.85, ry = h/2 * 0.85;
            const bulge = 0.22;
            ctx.beginPath();
            for (let i = 0; i <= pts; i++) {
                const angle = (i / pts) * Math.PI * 2;
                const mod = 1 + bulge * Math.abs(Math.sin(bumps * angle / 2));
                if (i === 0) ctx.moveTo(cx + (rx * mod) * Math.cos(angle), cy + (ry * mod) * Math.sin(angle));
                else ctx.lineTo(cx + (rx * mod) * Math.cos(angle), cy + (ry * mod) * Math.sin(angle));
            }
            ctx.closePath();
            // Dots 
            ctx.moveTo(w/2 - 20 + 10, h/2 + 15); ctx.arc(w/2 - 20, h/2 + 15, 10, 0, Math.PI*2);
            ctx.moveTo(w/2 - 5 + 6, h/2 + 35);   ctx.arc(w/2 - 5, h/2 + 35, 6, 0, Math.PI*2);
            ctx.moveTo(w/2 - 20 + 3, h/2 + 50);  ctx.arc(w/2 - 20, h/2 + 50, 3, 0, Math.PI*2);
        };
    } else if (shape === 'whisper_dash' || shape === 'whisper') {
        shapeFn = (ctx) => {
            ctx.beginPath(); ctx.ellipse(w/2, h/2, w/2, h/2, 0, 0, Math.PI*2); ctx.closePath();
            ctx.moveTo(w/2 - 14, h - sw); ctx.lineTo(tailX, tailY); ctx.lineTo(w/2 + 14, h - sw); ctx.closePath();
        };
    } else if (shape === 'scifi_hex') {
        shapeFn = (ctx) => {
            const cut = 20; const hw = w/2, hh = h/2; const cx = w/2, cy = h/2;
            ctx.beginPath();
            ctx.moveTo(cx - hw + cut, cy - hh); ctx.lineTo(cx + hw - cut, cy - hh);
            ctx.lineTo(cx + hw, cy - hh + cut); ctx.lineTo(cx + hw, cy + hh - cut);
            ctx.lineTo(cx + hw - cut, cy + hh); ctx.lineTo(cx + 15, cy + hh);
            ctx.lineTo(tailX, tailY);           ctx.lineTo(cx - 5, cy + hh);
            ctx.lineTo(cx - hw + cut, cy + hh); ctx.lineTo(cx - hw, cy + hh - cut);
            ctx.lineTo(cx - hw, cy - hh + cut); ctx.closePath();
        };
    } else if (shape === 'creepy_wobbly') {
        shapeFn = (ctx) => {
            const pts = 60; const freq = 8, amp = 6;
            const cx = w/2, cy = h/2, rx = w/2, ry = h/2;
            ctx.beginPath();
            for (let i = 0; i < pts; i++) {
                const angle = (i / pts) * Math.PI * 2;
                const wave = Math.sin(angle * freq) * amp;
                const px = cx + (rx + wave) * Math.cos(angle);
                const py = cy + (ry + wave) * Math.sin(angle);
                if (i === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
            }
            ctx.closePath();
            ctx.moveTo(cx - 10, cy + ry);
            ctx.quadraticCurveTo(tailX, tailY - 20, tailX, tailY);
            ctx.quadraticCurveTo(tailX + 20, tailY - 20, cx + 20, cy + ry);
        };
    }

    return new Konva.Shape({
        width: w, height: h, fill: fill, stroke: stroke, strokeWidth: sw,
        sceneFunc: (ctx, node) => { 
            shapeFn(ctx); 
            if (shape === 'whisper_dash' || shape === 'whisper') {
                ctx.setLineDash([8, 6]);
                ctx.fillStrokeShape(node);
                ctx.setLineDash([]);
            } else {
                ctx.fillStrokeShape(node); 
            }
        },
    });
}

function applyBalloonPadding(group) {
    const g = (group && group.getAttr) ? group : selectedNode;
    if (!g) return;
    const kt = g.getAttr('kTextRef');
    if (!kt) return;
    const pad = parsePadding(g.getAttr('balloonPadding') || '12');
    const bw  = g.getAttr('balloonW') || 240;
    const bh  = g.getAttr('balloonH') || 100;
    kt.setAttrs({
        x:             pad.left,
        y:             pad.top,
        width:         Math.max(20, bw - pad.left - pad.right),
        height:        Math.max(10, bh - pad.top  - pad.bottom),
        verticalAlign: 'middle',
    });
    kt.moveToTop();
    contentLayer.draw();
}

function rebuildBalloonShape(group) {
    const children = group.getChildren();
    if (children[0]) children[0].destroy();
    const shape = group.getAttr('balloonShape') || 'classic_oval';
    const w = group.getAttr('balloonW') || 240;
    const h = group.getAttr('balloonH') || 100;
    const fill = group.getAttr('fillColor') || '#F5F5F0';
    const stroke = group.getAttr('strokeColor') || '#222222';
    const sw = group.getAttr('strokeWidth') || 2;
    const tx = group.getAttr('tailX') || w/2;
    const ty = group.getAttr('tailY') || h+30;
    const newShape = _buildBalloonShape(shape, w, h, fill, stroke, sw, tx, ty);
    group.add(newShape);
    newShape.moveToBottom();
    contentLayer.batchDraw();
}

// ── Add SFX (Unified with Gitaigo) ───────────────────────────────────────────
function addSFX() {
    const kText = new Konva.Text({
        id: uid(), x: CANVAS_W / 2 - 120, y: 160,
        text: 'KRAAK!', fontSize: 64, fontFamily: 'Impact', fill: '#FFFFFF',
        rotation: -8, draggable: true, name: 'element',
    });
    kText.setAttr('elemType', 'sfx');
    kText.setAttr('elemName', 'SFX / Gitaigo');
    kText.setAttr('isLocked', false);
    kText.setAttr('blurPx', 0);
    kText.filters([Konva.Filters.Blur]);
    kText.blurRadius(0);
    kText.on('click tap', () => selectNode(kText));
    contentLayer.add(kText); contentLayer.batchDraw();
    selectNode(kText); renderLayerList(); saveState();
}

// ── Add Caption ───────────────────────────────────────────────────────────────
function applyCaptionPadding(group) {
    const g = group || selectedNode;
    if (!g) return;
    const txt = g.getAttr('kTextRef');
    const bg = g.getChildren()[0]; 
    if (!txt || !bg) return;
    
    const pad = parsePadding(g.getAttr('captionPadding') || '12 14');
    const w = g.getAttr('captionW') || CANVAS_W;
    
    txt.setAttrs({
        x: pad.left,
        y: pad.top,
        width: Math.max(20, w - pad.left - pad.right)
    });
    
    const newH = Math.max(20, txt.height() + pad.top + pad.bottom);
    
    bg.setAttrs({
        width: w,
        height: newH
    });
    
    g.width(w);
    g.height(newH);
    
    contentLayer.batchDraw();
}

function addCaption() {
    const w = CANVAS_W;
    const group = new Konva.Group({ id: uid(), x: 0, y: 0, width: w, height: 42, draggable: true, name: 'element' });
    group.setAttr('elemType', 'caption'); 
    group.setAttr('elemName', 'Caption'); 
    group.setAttr('fillColor', '#1A0A00'); 
    group.setAttr('isLocked', false); 
    group.setAttr('captionW', w);
    group.setAttr('captionPadding', '12 14');
    group.setAttr('reflowText', false);
    
    const bg = new Konva.Rect({ x: 0, y: 0, width: w, height: 42, fill: '#1A0A00' });
    const txt = new Konva.Text({
        x: 14, y: 12, width: w - 28, text: 'Somewhere in the Anima Plane...',
        fontSize: 15, fontFamily: 'Lora', fill: '#D4A017', align: 'left',
    });
    group.add(bg, txt); 
    group.setAttr('kTextRef', txt);
    
    applyCaptionPadding(group);
    
    group.on('click tap', () => selectNode(group));
    contentLayer.add(group); contentLayer.batchDraw();
    selectNode(group); renderLayerList(); saveState();
}

// ── Unified Panel (Rectangular & Diagonal) ───────────────────────────────────
function addPanel(opts = {}) {
    const x = opts.x !== undefined ? opts.x : 20;
    const y = opts.y !== undefined ? opts.y : 20;
    const w = opts.width || (CANVAS_W - 40);
    const h = opts.height || 400;

    const group = new Konva.Group({
        id: uid(), x: x, y: y, width: w, height: h,
        draggable: true, name: 'element'
    });
    
    group.setAttr('elemType', 'panel');
    group.setAttr('elemName', 'Panel');
    group.setAttr('panelType', opts.panelType || 'rectangular'); // rectangular | diagonal
    group.setAttr('anglLeftDeg', opts.angle_left_deg !== undefined ? opts.angle_left_deg : 8);
    group.setAttr('anglRightDeg', opts.angle_right_deg !== undefined ? opts.angle_right_deg : 8);
    group.setAttr('strokeColor', '#888888');
    group.setAttr('strokeWidth', 3);
    group.setAttr('fillColor', 'transparent');
    group.setAttr('isLocked', false);
    group.setAttr('borderStyle', opts.border_style || 'solid');
    
    group.setAttr('innerImageFilename', '');
    group.setAttr('innerImageFrameId', null);
    group.setAttr('innerImageX', 0);
    group.setAttr('innerImageY', 0);
    group.setAttr('innerImageScale', 1.0);

    const bg = new Konva.Shape({ fill: group.getAttr('fillColor'), name: 'panelBg' });
    const clipGroup = new Konva.Group({ name: 'clipGroup' });
    const stroke = new Konva.Shape({ stroke: group.getAttr('strokeColor'), strokeWidth: group.getAttr('strokeWidth'), fillEnabled: false, name: 'panelStroke' });

    group.add(bg, clipGroup, stroke);
    updatePanelShapes(group);
    
    group.on('click tap', () => selectNode(group));
    contentLayer.add(group);
    contentLayer.batchDraw();
    
    if (!opts.noSelect) {
        selectNode(group);
        renderLayerList();
        saveState();
    }
    return group;
}

function updatePanelShapes(group) {
    const w = group.width() || group.getAttr('dpW') || 400;
    const h = group.height() || group.getAttr('dpH') || 300;
    
    // Ensure Konva group dimensions are up to date
    group.width(w);
    group.height(h);
    
    const pType = group.getAttr('panelType') || 'rectangular';
    const al = pType === 'diagonal' ? (group.getAttr('anglLeftDeg') || 0) : 0;
    const ar = pType === 'diagonal' ? (group.getAttr('anglRightDeg') || 0) : 0;
    
    const offL = Math.tan(al * Math.PI / 180) * h;
    const offR = Math.tan(ar * Math.PI / 180) * h;
    
    const pts = [
        offL, 0,
        w + offR, 0,
        w, h,
        0, h
    ];
    
    const bg = group.findOne('.panelBg');
    const clip = group.findOne('.clipGroup');
    const stroke = group.findOne('.panelStroke');
    
    const fillC = group.getAttr('fillColor');
    
    if (bg) {
        bg.sceneFunc((ctx, shape) => {
            ctx.beginPath();
            ctx.moveTo(pts[0], pts[1]);
            ctx.lineTo(pts[2], pts[3]);
            ctx.lineTo(pts[4], pts[5]);
            ctx.lineTo(pts[6], pts[7]);
            ctx.closePath();
            if (fillC !== 'transparent' && fillC !== 'none' && fillC !== '') {
                ctx.fillStyle = fillC;
                ctx.fill();
            }
        });
    }
    if (clip) {
        clip.clipFunc((ctx) => {
            ctx.beginPath();
            ctx.moveTo(pts[0], pts[1]);
            ctx.lineTo(pts[2], pts[3]);
            ctx.lineTo(pts[4], pts[5]);
            ctx.lineTo(pts[6], pts[7]);
            ctx.closePath();
        });
    }
    if (stroke) {
        stroke.sceneFunc((ctx, shape) => {
            ctx.strokeStyle = group.getAttr('strokeColor') || '#888888';
            ctx.lineWidth = group.getAttr('strokeWidth') || 3;
            _applyBorderStyleStroke(ctx, pts, group.getAttr('borderStyle') || 'solid', group.getAttr('strokeWidth') || 3);
        });
    }
}

function _applyBorderStyleStroke(ctx, pts, style, sw) {
    const corners = [];
    for (let i = 0; i < pts.length; i += 2) corners.push({ x: pts[i], y: pts[i + 1] });
    corners.push(corners[0]);

    if (style === 'borderless') return;

    ctx.beginPath();
    ctx.setLineDash([]);

    if (style === 'solid') {
        for (let i = 0; i < corners.length - 1; i++) {
            if (i === 0) ctx.moveTo(corners[i].x, corners[i].y);
            else ctx.lineTo(corners[i].x, corners[i].y);
        }
        ctx.closePath();
        ctx.stroke();
        return;
    }

    if (style === 'dashed') {
        ctx.setLineDash([14, 7]);
        for (let i = 0; i < corners.length - 1; i++) {
            if (i === 0) ctx.moveTo(corners[i].x, corners[i].y);
            else ctx.lineTo(corners[i].x, corners[i].y);
        }
        ctx.closePath();
        ctx.stroke();
        ctx.setLineDash([]);
        return;
    }

    for (let seg = 0; seg < corners.length - 1; seg++) {
        const p1 = corners[seg], p2 = corners[seg + 1];
        const dx = p2.x - p1.x, dy = p2.y - p1.y;
        const len = Math.sqrt(dx * dx + dy * dy);
        if (len < 1) continue;
        const ux = dx / len, uy = dy / len;
        const nx = -uy, ny = ux;

        const steps = Math.ceil(len / 3);
        ctx.beginPath();
        for (let s = 0; s <= steps; s++) {
            const t = s / steps;
            let ox = 0, oy = 0;

            if (style === 'wavy') {
                const amp = sw * 2;
                const wave = Math.sin(t * len * 0.08 * Math.PI * 2) * amp;
                ox = nx * wave; oy = ny * wave;
            } else if (style === 'jagged') {
                const spaceInterval = 20;
                const spike = sw * 3;
                const phase = Math.floor(t * len / spaceInterval);
                ox = nx * (phase % 2 === 0 ? spike : -spike) * (t % (spaceInterval / len) < 0.5 ? 1 : -1);
                oy = ny * (phase % 2 === 0 ? spike : -spike) * (t % (spaceInterval / len) < 0.5 ? 1 : -1);
            } else if (style === 'organic') {
                const noise = Math.sin(t * len * 0.15 + seg * 1.7) * sw * 1.8
                            + Math.sin(t * len * 0.31 + seg * 3.1) * sw * 0.9;
                ox = nx * noise; oy = ny * noise;
            } else if (style === 'chain') {
                const linkLen = 16;
                const localT  = (t * len) % linkLen / linkLen;
                const bump    = Math.abs(Math.sin(localT * Math.PI)) * sw * 1.5;
                ox = nx * bump; oy = ny * bump;
            } else if (style === 'water_ripple') {
                const amp = sw * 1.5;
                const wave = Math.sin(t * len * 0.06 * Math.PI * 2 + Math.PI * 0.25) * amp;
                ox = nx * wave; oy = ny * wave;
            }

            const px = p1.x + ux * t * len + ox;
            const py = p1.y + uy * t * len + oy;
            if (s === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
        }
        ctx.stroke();
    }
}


// ── Speed Lines ───────────────────────────────────────────────────────────────
function addSpeedLines() {
    const group = new Konva.Group({
        id: uid(), x: CANVAS_W / 2, y: 200,
        draggable: true, name: 'element',
    });
    group.setAttr('elemType', 'speed_lines');
    group.setAttr('elemName', 'Speed Lines');
    group.setAttr('slMode',        'radial');
    group.setAttr('lineCount',     48);
    group.setAttr('innerRadius',   60);
    group.setAttr('outerRadius',   Math.min(CANVAS_W, CANVAS_H) * 0.4);
    group.setAttr('angleStartDeg', 0);
    group.setAttr('angleSpanDeg',  360);
    group.setAttr('lineColor',     '#000000');
    group.setAttr('lineWidth',     1.5);
    group.setAttr('taper',         true);
    group.setAttr('isLocked', false);

    const shape = _buildSpeedLinesShape(group);
    group.add(shape);
    group.on('click tap', () => selectNode(group));

    contentLayer.add(group);
    contentLayer.batchDraw();
    selectNode(group);
    renderLayerList();
    saveState();
}

function _buildSpeedLinesShape(group) {
    return new Konva.Shape({
        name: 'slShape',
        sceneFunc: (ctx, shapeNode) => {
            const mode    = group.getAttr('slMode')        || 'radial';
            const count   = group.getAttr('lineCount')     || 48;
            const inner   = group.getAttr('innerRadius')   || 60;
            const outer   = group.getAttr('outerRadius')   || 300;
            const start   = group.getAttr('angleStartDeg') || 0;
            const span    = group.getAttr('angleSpanDeg')  || 360;
            const color   = group.getAttr('lineColor')     || '#000000';
            const lw      = group.getAttr('lineWidth')     || 1.5;
            const taper   = group.getAttr('taper')         !== false;

            ctx.strokeStyle = color;
            ctx.setLineDash([]);

            if (mode === 'radial' || mode === 'sector') {
                const totalSpan = (mode === 'sector') ? span : 360;
                for (let i = 0; i < count; i++) {
                    const angleDeg = start + (totalSpan / count) * i;
                    const rad      = angleDeg * Math.PI / 180;
                    const x1 = inner * Math.cos(rad);
                    const y1 = inner * Math.sin(rad);
                    const x2 = outer * Math.cos(rad);
                    const y2 = outer * Math.sin(rad);
                    ctx.lineWidth = taper ? Math.max(0.5, lw * 1.8) : lw;
                    ctx.beginPath();
                    ctx.moveTo(x1, y1);
                    ctx.lineTo(x2, y2);
                    ctx.stroke();
                }
            } else if (mode === 'directional') {
                const spacing = (outer * 2) / count;
                for (let i = 0; i < count; i++) {
                    const px = -outer + i * spacing;
                    ctx.lineWidth = lw;
                    ctx.beginPath();
                    ctx.moveTo(px, -outer);
                    ctx.lineTo(px,  outer);
                    ctx.stroke();
                }
            }

            ctx.beginPath();
            ctx.rect(-outer, -outer, outer * 2, outer * 2);
            ctx.fillStyle = 'transparent';
            ctx.fill();
        },
        hitFunc: (ctx, shapeNode) => {
            const outer = group.getAttr('outerRadius') || 300;
            ctx.beginPath();
            ctx.rect(-outer, -outer, outer * 2, outer * 2);
            ctx.fillStrokeShape(shapeNode);
        },
    });
}

function rebuildSpeedLinesShape(group) {
    const old = group.findOne('.slShape');
    if (old) old.destroy();
    group.add(_buildSpeedLinesShape(group));
    contentLayer.batchDraw();
}

// ── Impact Frame ──────────────────────────────────────────────────────────────
function addImpactFrame() {
    const w = CANVAS_W - 40, h = 380;
    const group = new Konva.Group({
        id: uid(), x: 20, y: 20,
        draggable: true, name: 'element',
    });
    group.setAttr('elemType',       'impact_frame');
    group.setAttr('elemName',       'Impact Frame');
    group.setAttr('ifW',            w);
    group.setAttr('ifH',            h);
    group.setAttr('impactStyle',    'starburst');
    group.setAttr('colorPrimary',   '#FFFFFF');
    group.setAttr('colorSecondary', '#FFE066');
    group.setAttr('spikeCount',     20);
    group.setAttr('isLocked', false);

    const shape = _buildImpactFrameShape(group);
    group.add(shape);
    group.on('click tap', () => selectNode(group));

    contentLayer.add(group);
    contentLayer.batchDraw();
    selectNode(group);
    renderLayerList();
    saveState();
}

function _buildImpactFrameShape(group) {
    return new Konva.Shape({
        name: 'ifShape',
        sceneFunc: (ctx, shapeNode) => {
            const w  = group.getAttr('ifW')           || 400;
            const h  = group.getAttr('ifH')           || 300;
            const st = group.getAttr('impactStyle')   || 'starburst';
            const c1 = group.getAttr('colorPrimary')  || '#FFFFFF';
            const c2 = group.getAttr('colorSecondary')|| '#FFE066';
            const spikes = group.getAttr('spikeCount')|| 20;
            const cx = w / 2, cy = h / 2;

            ctx.save();

            if (st === 'starburst') {
                const rOuter = Math.sqrt(cx*cx + cy*cy) + 20;
                const rInner = rOuter * 0.55;
                const sx = w / h;
                ctx.beginPath();
                for (let i = 0; i < spikes * 2; i++) {
                    const angle = (i * Math.PI) / spikes;
                    const r     = i % 2 === 0 ? rOuter : rInner;
                    const px    = cx + r * Math.cos(angle) * sx;
                    const py    = cy + r * Math.sin(angle);
                    if (i === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
                }
                ctx.closePath();
                ctx.fillStyle = c1;
                ctx.fill();

            } else if (st === 'manga_radial') {
                const rMax = Math.sqrt(cx*cx + cy*cy) + 30;
                ctx.strokeStyle = c1;
                ctx.lineWidth   = 1.5;
                for (let i = 0; i < 120; i++) {
                    const angle = (i / 120) * 2 * Math.PI;
                    ctx.beginPath();
                    ctx.moveTo(cx, cy);
                    ctx.lineTo(cx + rMax * Math.cos(angle), cy + rMax * Math.sin(angle));
                    ctx.stroke();
                }

            } else if (st === 'halftone_burst') {
                const rings = 8;
                const maxR  = Math.min(cx, cy);
                for (let r = 0; r < rings; r++) {
                    const ratio   = 1 - r / rings;
                    const radius  = maxR * (r + 1) / rings;
                    const dotCount= Math.floor(6 + r * 4);
                    const alpha   = ratio * 0.9;
                    ctx.fillStyle = c1.startsWith('#')
                        ? `rgba(${parseInt(c1.slice(1,3),16)},${parseInt(c1.slice(3,5),16)},${parseInt(c1.slice(5,7),16)},${alpha})`
                        : c1;
                    for (let d = 0; d < dotCount; d++) {
                        const a  = (d / dotCount) * 2 * Math.PI;
                        const dx = cx + radius * Math.cos(a);
                        const dy = cy + radius * Math.sin(a);
                        const dotR = maxR / rings * ratio * 0.5;
                        ctx.beginPath();
                        ctx.arc(dx, dy, dotR, 0, Math.PI * 2);
                        ctx.fill();
                    }
                }

            } else if (st === 'energy_wave') {
                for (let ring = 6; ring >= 1; ring--) {
                    const ratio  = ring / 6;
                    const rx     = cx * ratio * 1.1;
                    const ry     = cy * ratio * 1.1;
                    const alpha  = (1 - ratio) * 0.8 + 0.1;
                    ctx.strokeStyle = c1.startsWith('#')
                        ? `rgba(${parseInt(c1.slice(1,3),16)},${parseInt(c1.slice(3,5),16)},${parseInt(c1.slice(5,7),16)},${alpha})`
                        : c1;
                    ctx.lineWidth = 2;
                    ctx.beginPath();
                    for (let i = 0; i <= 60; i++) {
                        const angle = (i / 60) * 2 * Math.PI;
                        const noise = Math.sin(angle * 7) * 8 * ratio;
                        const px = cx + (rx + noise) * Math.cos(angle);
                        const py = cy + (ry + noise) * Math.sin(angle);
                        if (i === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
                    }
                    ctx.closePath();
                    ctx.stroke();
                }
            }

            ctx.restore();

            ctx.beginPath();
            ctx.rect(0, 0, w, h);
            ctx.fillStyle = 'transparent';
            ctx.fill();
        },
        hitFunc: (ctx, shapeNode) => {
            ctx.beginPath();
            ctx.rect(0, 0, group.getAttr('ifW') || 400, group.getAttr('ifH') || 300);
            ctx.fillStrokeShape(shapeNode);
        },
    });
}

function rebuildImpactFrameShape(group) {
    const old = group.findOne('.ifShape');
    if (old) old.destroy();
    group.add(_buildImpactFrameShape(group));
    contentLayer.batchDraw();
}

// ── Layout Presets ────────────────────────────────────────────────────────────
function openPresetsModal() {
    document.getElementById('presetsModal').classList.add('active');
    _renderPresetParams(document.getElementById('presetSelect').value);
}
function closePresetsModal() {
    document.getElementById('presetsModal').classList.remove('active');
}

function _renderPresetParams(preset) {
    const body = document.getElementById('presetParamsBody');
    const fieldHTML = (label, id, type, value, extra = '') =>
        `<div class="form-group"><label class="form-label">${label}</label>
         <input type="${type}" class="prop-input" id="pp_${id}" value="${value}" ${extra}></div>`;

    let html = '';
    if (preset === 'regular_grid') {
        html = fieldHTML('Rows', 'rows', 'number', 2) +
               fieldHTML('Columns', 'cols', 'number', 2) +
               fieldHTML('Gutter (px)', 'gutter', 'number', 10);
    } else if (preset === 'pyramid') {
        html = `<div class="form-group"><label class="form-label">Direction</label>
                <select class="prop-select" id="pp_dir"><option value="funnel">Funnel (wide→narrow)</option><option value="expand">Expand (narrow→wide)</option></select></div>`+
               fieldHTML('Tier Count', 'tiers', 'number', 3, 'min=2 max=5') +
               fieldHTML('Gutter (px)', 'gutter', 'number', 10);
    } else if (preset === 'anchor_panel') {
        html = `<div class="form-group"><label class="form-label">Anchor Position</label>
                <select class="prop-select" id="pp_pos"><option value="top">Top</option><option value="center">Center</option><option value="bottom">Bottom</option></select></div>`+
               fieldHTML('Anchor Size Ratio', 'ratio', 'number', 0.6, 'min=0.3 max=0.85 step=0.05') +
               fieldHTML('Satellite Panels', 'satellites', 'number', 2, 'min=1 max=4') +
               fieldHTML('Gutter (px)', 'gutter', 'number', 10);
    } else if (preset === 'mosaic_insert') {
        html = fieldHTML('Insert Count', 'inserts', 'number', 2, 'min=1 max=3') +
               fieldHTML('Insert Size Ratio', 'ratio', 'number', 0.3, 'min=0.15 max=0.45 step=0.05') +
               fieldHTML('Gutter (px)', 'gutter', 'number', 8);
    } else if (preset === 'vertical_stack') {
        html = fieldHTML('Column Count', 'cols', 'number', 2, 'min=2 max=4') +
               fieldHTML('Gutter (px)', 'gutter', 'number', 10) +
               `<div class="form-group"><label class="form-label">Panel Type</label>
                <select class="prop-select" id="pp_ptype"><option value="rectangular">Rectangular</option><option value="diagonal">Diagonal</option></select></div>`;
    } else if (preset === 'diagonal_cascade') {
        html = fieldHTML('Panel Count', 'count', 'number', 4, 'min=3 max=6') +
               fieldHTML('Angle (°)', 'angle', 'number', 10, 'min=3 max=30') +
               `<div class="form-group"><label class="form-label">Overlap Mode</label>
                <select class="prop-select" id="pp_overlap"><option value="gap">Gap (clean gutters)</option><option value="stagger">Stagger (overlapping)</option></select></div>`+
               fieldHTML('Gutter (px)', 'gutter', 'number', 12);
    }
    body.innerHTML = html;
}

function applyPresetLayout() {
    const preset = document.getElementById('presetSelect').value;
    const g = (id, fallback) => {
        const el = document.getElementById('pp_' + id);
        return el ? (el.tagName === 'SELECT' ? el.value : parseFloat(el.value) || fallback) : fallback;
    };

    const confirmMsg = 'This will add new panels to the canvas. Existing elements are kept. Continue?';
    if (!confirm(confirmMsg)) return;

    const gutter = g('gutter', 10);

    if (preset === 'regular_grid') {
        const rows = g('rows', 2), cols = g('cols', 2);
        _presetRegularGrid(rows, cols, gutter);

    } else if (preset === 'pyramid') {
        const tiers = g('tiers', 3), dir = g('dir', 'funnel');
        _presetPyramid(tiers, dir, gutter);

    } else if (preset === 'anchor_panel') {
        const pos = g('pos', 'top'), ratio = g('ratio', 0.6), sats = g('satellites', 2);
        _presetAnchorPanel(pos, ratio, parseInt(sats), gutter);

    } else if (preset === 'mosaic_insert') {
        const inserts = g('inserts', 2), ratio = g('ratio', 0.3);
        _presetMosaicInsert(parseInt(inserts), ratio, gutter);

    } else if (preset === 'vertical_stack') {
        const cols = g('cols', 2), ptype = g('ptype', 'rectangular');
        _presetVerticalStack(parseInt(cols), ptype, gutter);

    } else if (preset === 'diagonal_cascade') {
        const count   = g('count', 4);
        const angle   = g('angle', 10);
        const overlap = g('overlap', 'gap');
        _presetDiagonalCascade(parseInt(count), angle, overlap, gutter);
    }

    closePresetsModal();
    renderLayerList();
    saveState();
    toast('Preset applied', 'success');
}

function _presetRegularGrid(rows, cols, gutter) {
    const cw = (CANVAS_W - (cols + 1) * gutter) / cols;
    const ch = (CANVAS_H - (rows + 1) * gutter) / rows;
    for (let r = 0; r < rows; r++) {
        for (let c = 0; c < cols; c++) {
            addPanel({ x: gutter + c * (cw + gutter), y: gutter + r * (ch + gutter), width: cw, height: ch, noSelect: true });
        }
    }
}

function _presetPyramid(tiers, dir, gutter) {
    const tierH = (CANVAS_H - (tiers + 1) * gutter) / tiers;
    const counts = dir === 'funnel'
        ? Array.from({ length: tiers }, (_, i) => i + 1)
        : Array.from({ length: tiers }, (_, i) => tiers - i);
    counts.forEach((cols, row) => {
        const panW = (CANVAS_W - (cols + 1) * gutter) / cols;
        for (let c = 0; c < cols; c++) {
            addPanel({ x: gutter + c * (panW + gutter), y: gutter + row * (tierH + gutter), width: panW, height: tierH, noSelect: true });
        }
    });
}

function _presetAnchorPanel(pos, ratio, satCount, gutter) {
    const anchorH = Math.round(CANVAS_H * ratio);
    const satH    = CANVAS_H - anchorH - gutter * 3;
    const satW    = (CANVAS_W - (satCount + 1) * gutter) / satCount;

    if (pos === 'top') {
        addPanel({ x: gutter, y: gutter, width: CANVAS_W - gutter * 2, height: anchorH, noSelect: true });
        for (let i = 0; i < satCount; i++) {
            addPanel({ x: gutter + i * (satW + gutter), y: gutter * 2 + anchorH, width: satW, height: satH, noSelect: true });
        }
    } else if (pos === 'bottom') {
        for (let i = 0; i < satCount; i++) {
            addPanel({ x: gutter + i * (satW + gutter), y: gutter, width: satW, height: satH, noSelect: true });
        }
        addPanel({ x: gutter, y: gutter * 2 + satH, width: CANVAS_W - gutter * 2, height: anchorH, noSelect: true });
    } else { 
        const topH = Math.round((CANVAS_H - anchorH - gutter * 4) / 2);
        for (let i = 0; i < satCount; i++) {
            addPanel({ x: gutter + i * (satW + gutter), y: gutter, width: satW, height: topH, noSelect: true });
        }
        addPanel({ x: gutter, y: gutter * 2 + topH, width: CANVAS_W - gutter * 2, height: anchorH, noSelect: true });
        for (let i = 0; i < satCount; i++) {
            addPanel({ x: gutter + i * (satW + gutter), y: gutter * 3 + topH + anchorH, width: satW, height: topH, noSelect: true });
        }
    }
}

function _presetMosaicInsert(insertCount, ratio, gutter) {
    const bg = addPanel({ x: 0, y: 0, width: CANVAS_W, height: CANVAS_H, noSelect: true });
    bg.moveToBottom();

    const iw = Math.round(CANVAS_W * ratio);
    const ih = Math.round(CANVAS_H * ratio);
    const positions = [
        { x: gutter, y: gutter },
        { x: CANVAS_W - iw - gutter, y: gutter },
        { x: gutter, y: CANVAS_H - ih - gutter },
        { x: CANVAS_W - iw - gutter, y: CANVAS_H - ih - gutter },
    ];
    for (let i = 0; i < Math.min(insertCount, positions.length); i++) {
        const p = positions[i];
        addPanel({ x: p.x, y: p.y, width: iw, height: ih, noSelect: true });
    }
}

function _presetVerticalStack(colCount, ptype, gutter) {
    const cw = (CANVAS_W - (colCount + 1) * gutter) / colCount;
    const ch = CANVAS_H - gutter * 2;
    for (let c = 0; c < colCount; c++) {
        const x = gutter + c * (cw + gutter);
        if (ptype === 'diagonal') {
            const sign = c % 2 === 0 ? 1 : -1;
            addPanel({ panelType: 'diagonal', x, y: gutter, width: cw, height: ch, angle_left_deg: sign * 5, angle_right_deg: sign * 5, noSelect: true });
        } else {
            addPanel({ x, y: gutter, width: cw, height: ch, noSelect: true });
        }
    }
}

function _presetDiagonalCascade(panelCount, angleDeg, overlapMode, gutter) {
    const tierH = (CANVAS_H - (panelCount + 1) * gutter) / panelCount;
    for (let i = 0; i < panelCount; i++) {
        const y = gutter + i * (tierH + (overlapMode === 'stagger' ? tierH * 0.08 : gutter));
        const xOffset = Math.round(Math.tan(angleDeg * Math.PI / 180) * tierH * i * 0.5);
        addPanel({
            panelType:       'diagonal',
            x:               Math.max(0, -xOffset),
            y,
            width:           CANVAS_W + Math.abs(xOffset),
            height:          tierH,
            angle_left_deg:  angleDeg,
            angle_right_deg: angleDeg,
            noSelect:        true,
        });
    }
}

function splitPanelGrid(node, rows, cols, gutter) {
    if (rows < 1) rows = 1; if (cols < 1) cols = 1;
    if (rows === 1 && cols === 1) return;

    const w = node.width();
    const h = node.height();
    const sx = node.scaleX();
    const sy = node.scaleY();
    
    const trueW = w * sx;
    const trueH = h * sy;

    const cellW = (trueW - (cols - 1) * gutter) / cols;
    const cellH = (trueH - (rows - 1) * gutter) / rows;

    const origRot = node.rotation();
    const startX = node.x();
    const startY = node.y();
    
    const fill = node.getAttr('fillColor');
    const strokeColor = node.getAttr('strokeColor');
    const strokeWidth = node.getAttr('strokeWidth');

    const rad = origRot * Math.PI / 180;
    const cosA = Math.cos(rad);
    const sinA = Math.sin(rad);

    for(let r=0; r<rows; r++) {
        for(let c=0; c<cols; c++) {
            const localX = c * (cellW + gutter);
            const localY = r * (cellH + gutter);

            const rotX = localX * cosA - localY * sinA;
            const rotY = localX * sinA + localY * cosA;

            const newPanel = addPanel({
                x: startX + rotX,
                y: startY + rotY,
                width: cellW,
                height: cellH,
                noSelect: true
            });
            newPanel.rotation(origRot);
            newPanel.scaleX(1);
            newPanel.scaleY(1);
            newPanel.setAttr('fillColor', fill);
            newPanel.setAttr('strokeColor', strokeColor);
            newPanel.setAttr('strokeWidth', strokeWidth);
            
            newPanel.setAttr('isLocked', true);
            newPanel.draggable(true);
            
            updatePanelShapes(newPanel);
        }
    }
    node.destroy();
    deselect();
    contentLayer.batchDraw();
    renderLayerList();
    saveState();
    toast('Grid split successfully', 'success');
}

function togglePanelImageDrag(group, enable) {
    group.draggable(!enable); 
    const img = group.findOne('.innerImage');
    if (img) img.draggable(enable);
}

function updatePanelImageTransform(group, key, value) {
    if (key === 'scale') group.setAttr('innerImageScale', value);
    if (key === 'x') group.setAttr('innerImageX', value);
    if (key === 'y') group.setAttr('innerImageY', value);
    
    const img = group.findOne('.innerImage');
    if (img) {
        if (key === 'scale') { img.scaleX(value); img.scaleY(value); }
        if (key === 'x') img.x(value);
        if (key === 'y') img.y(value);
        contentLayer.batchDraw();
    }
}

function clearPanelImage(group) {
    group.setAttr('innerImageFilename', '');
    group.setAttr('innerImageFrameId', null);
    const img = group.findOne('.innerImage');
    if (img) img.destroy();
    contentLayer.batchDraw();
    renderPropertiesFor(group);
    saveState();
}

function showNoSelection() {
    document.getElementById('propsBody').innerHTML = `
        <div id="noSelectionMsg">
            <div style="font-size:1.8rem; margin-bottom:8px;">🖱</div>
            <div>Select an element<br>to edit its properties.</div>
            <div style="margin-top:12px; font-size:0.7rem; opacity:0.6;">Or use the tools below<br>to add new elements.</div>
        </div>`;
}

function renderPropertiesFor(node) {
    const type   = node.getAttr('elemType');
    const isLocked = node.getAttr('isLocked');
    const body   = document.getElementById('propsBody');
    const fontOptions = FONTS.map(f => `<option value="${escHtml(f.font_key)}">${escHtml(f.name)}</option>`).join('');

    let lockHeader = `
        <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-float); padding:8px; border-radius:var(--r); border:1px solid ${isLocked ? 'var(--amber)' : 'var(--border)'}; margin-bottom:12px;">
            <span style="font-size:0.75rem; font-family:var(--font-mono); color:${isLocked ? 'var(--amber)' : 'var(--text)'};">
                ${isLocked ? '🔒 GRID LINKED' : '🔓 INDEPENDENT'}
            </span>
            <button class="btn btn-secondary" style="padding:4px 10px; font-size:0.65rem;" onclick="toggleLock('${node.id()}')">
                ${isLocked ? 'Unlink' : 'Link'}
            </button>
        </div>
    `;

    if (type === 'image') {
        body.innerHTML = lockHeader + `
            <div class="prop-section-title">Image Layer</div>
            <div class="prop-group">
                <label class="prop-label">Position X / Y</label>
                <div class="prop-row">
                    <input type="number" class="prop-input" id="px" value="${Math.round(node.x())}" oninput="node && node.x(+this.value); contentLayer.batchDraw()" ${isLocked?'disabled':''}>
                    <input type="number" class="prop-input" id="py" value="${Math.round(node.y())}" oninput="node && node.y(+this.value); contentLayer.batchDraw()" ${isLocked?'disabled':''}>
                </div>
            </div>
            <div class="prop-group">
                <label class="prop-label">Scale X / Y</label>
                <div class="prop-row">
                    <input type="number" step="0.01" class="prop-input" id="psx" value="${node.scaleX().toFixed(3)}" oninput="node && node.scaleX(+this.value); contentLayer.batchDraw()" ${isLocked?'disabled':''}>
                    <input type="number" step="0.01" class="prop-input" id="psy" value="${node.scaleY().toFixed(3)}" oninput="node && node.scaleY(+this.value); contentLayer.batchDraw()" ${isLocked?'disabled':''}>
                </div>
            </div>
            <div class="prop-group">
                <label class="prop-label">Rotation (°)</label>
                <input type="number" step="0.5" class="prop-input" value="${node.rotation().toFixed(1)}" oninput="node && node.rotation(+this.value); contentLayer.batchDraw()" ${isLocked?'disabled':''}>
            </div>
            <div class="prop-group">
                <label class="prop-label">Opacity</label>
                <input type="range" min="0" max="1" step="0.01" value="${node.opacity()}" oninput="node && node.opacity(+this.value); contentLayer.batchDraw()">
            </div>
            <div class="prop-group">
                <label class="prop-label">Layer Order</label>
                <div class="prop-row">
                    <button class="btn btn-secondary" onclick="selectedNode && selectedNode.moveUp(); contentLayer.batchDraw(); renderLayerList(); saveState();">▲ Up</button>
                    <button class="btn btn-secondary" onclick="selectedNode && selectedNode.moveDown(); contentLayer.batchDraw(); renderLayerList(); saveState();">▼ Down</button>
                </div>
            </div>`;
    } else if (['balloon','shout','thought','whisper'].includes(type)) {
        const kText  = node.getAttr('kTextRef');
        const w = node.getAttr('balloonW') || 240;
        const h = node.getAttr('balloonH') || 100;
        const shape = node.getAttr('balloonShape') || 'classic_oval';
        
        body.innerHTML = lockHeader + `
            <div class="prop-section-title">Speech Balloon</div>
            <div class="prop-group">
                <label class="prop-label">Shape Style</label>
                <select class="prop-select" onchange="if(selectedNode){ selectedNode.setAttr('balloonShape', this.value); rebuildBalloonShape(selectedNode); renderPropertiesFor(selectedNode); saveState(); }">
                    <option value="classic_oval" ${shape === 'classic_oval' || shape === 'oval' ? 'selected' : ''}>🗨 Classic Oval</option>
                    <option value="modern_box" ${shape === 'modern_box' ? 'selected' : ''}>▭ Modern Box</option>
                    <option value="shout_burst" ${shape === 'shout_burst' || shape === 'shout' ? 'selected' : ''}>💥 Action Burst</option>
                    <option value="fierce_scream" ${shape === 'fierce_scream' ? 'selected' : ''}>😱 Fierce Scream</option>
                    <option value="thought_cloud" ${shape === 'thought_cloud' || shape === 'thought' ? 'selected' : ''}>💭 Fluffy Thought</option>
                    <option value="whisper_dash" ${shape === 'whisper_dash' || shape === 'whisper' ? 'selected' : ''}>🗯 Dashed Whisper</option>
                    <option value="scifi_hex" ${shape === 'scifi_hex' ? 'selected' : ''}>🤖 Sci-Fi Chamfer</option>
                    <option value="creepy_wobbly" ${shape === 'creepy_wobbly' ? 'selected' : ''}>👻 Creepy Wobbly</option>
                </select>
            </div>
            <div class="prop-group">
                <label class="prop-label">Dialogue Text</label>
                <textarea class="prop-textarea" id="bText" oninput="if(selectedNode && selectedNode.getAttr('kTextRef')) { selectedNode.getAttr('kTextRef').text(this.value); contentLayer.batchDraw(); }">${kText ? escHtml(kText.text()) : ''}</textarea>
            </div>
            <div class="prop-group">
                <label class="prop-label">Font</label>
                <select class="prop-select" onchange="if(selectedNode && selectedNode.getAttr('kTextRef')) { selectedNode.getAttr('kTextRef').fontFamily(this.value); contentLayer.batchDraw(); }">
                    ${fontOptions}
                </select>
            </div>
            <div class="prop-group">
                <label class="prop-label">Font Size</label>
                <input type="number" class="prop-input" value="${kText ? kText.fontSize() : 16}" oninput="if(selectedNode && selectedNode.getAttr('kTextRef')) { selectedNode.getAttr('kTextRef').fontSize(+this.value); contentLayer.batchDraw(); }">
            </div>
            <div class="prop-group">
                <label class="prop-label">Text Color</label>
                <input type="color" class="prop-color-swatch" value="${kText ? kText.fill() : '#111111'}" oninput="if(selectedNode && selectedNode.getAttr('kTextRef')) { selectedNode.getAttr('kTextRef').fill(this.value); contentLayer.batchDraw(); }">
            </div>
            <div class="prop-group">
                <label class="prop-label">Balloon Fill</label>
                <input type="color" class="prop-color-swatch" value="${node.getAttr('fillColor')||'#F5F5F0'}" oninput="if(selectedNode){ selectedNode.setAttr('fillColor', this.value); rebuildBalloonShape(selectedNode); }">
            </div>
            <div class="prop-group">
                <label class="prop-label">Balloon Stroke</label>
                <div class="prop-row">
                    <input type="color" class="prop-color-swatch" value="${node.getAttr('strokeColor')||'#222222'}" oninput="if(selectedNode){ selectedNode.setAttr('strokeColor',this.value); rebuildBalloonShape(selectedNode); }">
                    <input type="number" class="prop-input" value="${node.getAttr('strokeWidth')||2}" oninput="if(selectedNode){ selectedNode.setAttr('strokeWidth',+this.value); rebuildBalloonShape(selectedNode); }">
                </div>
            </div>
            <div class="prop-group">
                <label class="prop-label">Size W / H</label>
                <div class="prop-row">
                    <input type="number" class="prop-input" value="${w}" oninput="if(selectedNode){ selectedNode.setAttr('balloonW',+this.value); rebuildBalloonShape(selectedNode); applyBalloonPadding(selectedNode); }" ${isLocked?'disabled':''}>
                    <input type="number" class="prop-input" value="${h}" oninput="if(selectedNode){ selectedNode.setAttr('balloonH',+this.value); rebuildBalloonShape(selectedNode); applyBalloonPadding(selectedNode); }" ${isLocked?'disabled':''}>
                </div>
            </div>
            <div class="prop-group">
                <label class="prop-label">Layer Order</label>
                <div class="prop-row">
                    <button class="btn btn-secondary" onclick="selectedNode && selectedNode.moveUp(); contentLayer.batchDraw(); renderLayerList(); saveState();">▲ Up</button>
                    <button class="btn btn-secondary" onclick="selectedNode && selectedNode.moveDown(); contentLayer.batchDraw(); renderLayerList(); saveState();">▼ Down</button>
                </div>
            </div>
            <div class="prop-group">
                <label class="prop-label">Text Padding (CSS notation)</label>
                <input type="text" class="prop-input" id="bPadding"
                    value="${escHtml(node.getAttr('balloonPadding') || '12')}"
                    placeholder="e.g. 12  or  8 16  or  10 12 16 12"
                    oninput="if(selectedNode){ selectedNode.setAttr('balloonPadding', this.value); applyBalloonPadding(selectedNode); }">
                <div style="font-size:0.6rem; color:var(--muted); margin-top:3px; line-height:1.5;">
                    CSS shorthand: 1 value = all sides<br>
                    2 values = top/bottom left/right<br>
                    4 values = top right bottom left
                </div>
            </div>`;
    } else if (type === 'sfx') {
        body.innerHTML = lockHeader + `
            <div class="prop-section-title">Sound Effect / Felt Text</div>
            <div class="prop-group">
                <label class="prop-label">Text</label>
                <input type="text" class="prop-input" value="${escHtml(node.text())}" oninput="if(selectedNode) { selectedNode.text(this.value); if(selectedNode.getAttr('blurPx')>0) { selectedNode.clearCache(); selectedNode.cache(); } contentLayer.batchDraw(); }">
            </div>
            <div class="prop-group">
                <label class="prop-label">Font Family</label>
                <select class="prop-select" onchange="if(selectedNode) { selectedNode.fontFamily(this.value); if(selectedNode.getAttr('blurPx')>0) { selectedNode.clearCache(); selectedNode.cache(); } contentLayer.batchDraw(); }">
                    ${fontOptions}
                </select>
            </div>
            <div class="prop-group">
                <label class="prop-label">Font Size</label>
                <input type="number" class="prop-input" value="${node.fontSize()}" oninput="if(selectedNode) { selectedNode.fontSize(+this.value); if(selectedNode.getAttr('blurPx')>0) { selectedNode.clearCache(); selectedNode.cache(); } contentLayer.batchDraw(); }">
            </div>
            <div class="prop-group">
                <label class="prop-label">Color</label>
                <input type="color" class="prop-color-swatch" value="${node.fill()}" oninput="if(selectedNode) { selectedNode.fill(this.value); if(selectedNode.getAttr('blurPx')>0) { selectedNode.clearCache(); selectedNode.cache(); } contentLayer.batchDraw(); }">
            </div>
            <div class="prop-group">
                <label class="prop-label">Blur (px) — Felt Sensation</label>
                <input type="range" min="0" max="10" step="0.5" class="prop-input" value="${node.getAttr('blurPx')||0}"
                    oninput="if(selectedNode){ const v = +this.value; selectedNode.setAttr('blurPx', v); selectedNode.blurRadius(v); if(v>0){ selectedNode.clearCache(); selectedNode.cache(); } else { selectedNode.clearCache(); } contentLayer.batchDraw(); }">
            </div>
            <div class="prop-group">
                <label class="prop-label">Rotation (°)</label>
                <input type="number" step="0.5" class="prop-input" value="${node.rotation().toFixed(1)}" oninput="selectedNode && selectedNode.rotation(+this.value); contentLayer.batchDraw();" ${isLocked?'disabled':''}>
            </div>
            <div class="prop-group">
                <label class="prop-label">Opacity</label>
                <input type="range" min="0" max="1" step="0.05" class="prop-input" value="${node.opacity()}" oninput="selectedNode && selectedNode.opacity(+this.value); contentLayer.batchDraw();">
            </div>`;
    } else if (type === 'caption') {
        const kText = node.getAttr('kTextRef');
        const isReflow = node.getAttr('reflowText');
        body.innerHTML = lockHeader + `
            <div class="prop-section-title">Caption Box</div>
            
            <div class="prop-group" style="margin-bottom:8px; padding:6px; background:var(--bg-hover); border-radius:var(--r); border:1px solid var(--border);">
                <label style="font-size:0.75rem; display:flex; align-items:center; gap:6px; cursor:pointer; color:var(--text);">
                    <input type="checkbox" ${isReflow ? 'checked' : ''} onchange="if(selectedNode){ selectedNode.setAttr('reflowText', this.checked); saveState(); }"> 
                    Scale Box Only (Reflow Text)
                </label>
                <div style="font-size:0.6rem; color:var(--muted); margin-top:4px;">When checked, dragging the box resize handles changes the box width instead of stretching the text.</div>
            </div>

            <div class="prop-group">
                <label class="prop-label">Box Width (px)</label>
                <input type="number" class="prop-input" value="${Math.round(node.getAttr('captionW') || node.width())}" oninput="if(selectedNode){ selectedNode.setAttr('captionW', +this.value); applyCaptionPadding(selectedNode); }" ${isLocked?'disabled':''}>
            </div>

            <div class="prop-group">
                <label class="prop-label">Caption Text</label>
                <textarea class="prop-textarea" oninput="if(selectedNode && selectedNode.getAttr('kTextRef')) { selectedNode.getAttr('kTextRef').text(this.value); applyCaptionPadding(selectedNode); }">${kText ? escHtml(kText.text()) : ''}</textarea>
            </div>
            <div class="prop-group">
                <label class="prop-label">Font Family</label>
                <select class="prop-select" onchange="if(selectedNode && selectedNode.getAttr('kTextRef')) { selectedNode.getAttr('kTextRef').fontFamily(this.value); applyCaptionPadding(selectedNode); }">
                    ${fontOptions}
                </select>
            </div>
            <div class="prop-group">
                <label class="prop-label">Font Size</label>
                <input type="number" class="prop-input" value="${kText ? kText.fontSize() : 15}" oninput="if(selectedNode && selectedNode.getAttr('kTextRef')) { selectedNode.getAttr('kTextRef').fontSize(+this.value); applyCaptionPadding(selectedNode); }">
            </div>

            <div class="prop-group">
                <label class="prop-label">Text Padding (CSS notation)</label>
                <input type="text" class="prop-input"
                    value="${escHtml(node.getAttr('captionPadding') || '12 14')}"
                    placeholder="12 14"
                    oninput="if(selectedNode){ selectedNode.setAttr('captionPadding', this.value); applyCaptionPadding(selectedNode); }">
            </div>

            <div class="prop-group">
                <label class="prop-label">Text Color</label>
                <input type="color" class="prop-color-swatch" value="${kText ? kText.fill() : '#D4A017'}" oninput="if(selectedNode && selectedNode.getAttr('kTextRef')) { selectedNode.getAttr('kTextRef').fill(this.value); contentLayer.batchDraw(); }">
            </div>
            <div class="prop-group">
                <label class="prop-label">Background Fill</label>
                <input type="color" class="prop-color-swatch" value="${node.getAttr('fillColor')||'#1A0A00'}" oninput="if(selectedNode){ selectedNode.setAttr('fillColor',this.value); if(selectedNode.getChildren()[0]) selectedNode.getChildren()[0].fill(this.value); contentLayer.batchDraw(); }">
            </div>`;
    } else if (type === 'panel') {
        const pType = node.getAttr('panelType') || 'rectangular';
        const bsOptions = ['solid','dashed','wavy','jagged','organic','chain','water_ripple','borderless']
            .map(s => `<option value="${s}" ${(node.getAttr('borderStyle')||'solid')===s?'selected':''}>${s}</option>`).join('');

        body.innerHTML = lockHeader + `
            <div class="prop-section-title">Panel Border</div>
            <div class="prop-group">
                <label class="prop-label">Panel Shape</label>
                <select class="prop-select" onchange="if(selectedNode){ selectedNode.setAttr('panelType', this.value); updatePanelShapes(selectedNode); contentLayer.batchDraw(); renderPropertiesFor(selectedNode); saveState(); }">
                    <option value="rectangular" ${pType === 'rectangular' ? 'selected' : ''}>Rectangular</option>
                    <option value="diagonal" ${pType === 'diagonal' ? 'selected' : ''}>Diagonal</option>
                </select>
            </div>
            
            ${pType === 'diagonal' ? `
            <div class="prop-group">
                <label class="prop-label">Angle Left / Right (°)</label>
                <div class="prop-row">
                    <input type="range" min="-30" max="30" step="1" class="prop-input"
                        value="${node.getAttr('anglLeftDeg')||0}"
                        oninput="if(selectedNode){ selectedNode.setAttr('anglLeftDeg',+this.value); updatePanelShapes(selectedNode); contentLayer.batchDraw(); }">
                    <input type="range" min="-30" max="30" step="1" class="prop-input"
                        value="${node.getAttr('anglRightDeg')||0}"
                        oninput="if(selectedNode){ selectedNode.setAttr('anglRightDeg',+this.value); updatePanelShapes(selectedNode); contentLayer.batchDraw(); }">
                </div>
                <div class="prop-row" style="font-size:0.65rem; color:var(--muted); margin-top:2px; font-family:var(--font-mono);">
                    <span>L: ${node.getAttr('anglLeftDeg')||0}°</span>
                    <span>R: ${node.getAttr('anglRightDeg')||0}°</span>
                </div>
            </div>
            ` : ''}

            <div class="prop-group">
                <label class="prop-label">Position X / Y</label>
                <div class="prop-row">
                    <input type="number" class="prop-input" value="${Math.round(node.x())}" oninput="selectedNode && selectedNode.x(+this.value); contentLayer.batchDraw()" ${isLocked?'disabled':''}>
                    <input type="number" class="prop-input" value="${Math.round(node.y())}" oninput="selectedNode && selectedNode.y(+this.value); contentLayer.batchDraw()" ${isLocked?'disabled':''}>
                </div>
            </div>
            <div class="prop-group">
                <label class="prop-label">Width / Height</label>
                <div class="prop-row">
                    <input type="number" class="prop-input" value="${Math.round(node.width())}" oninput="selectedNode && selectedNode.width(+this.value); updatePanelShapes(selectedNode); contentLayer.batchDraw()" ${isLocked?'disabled':''}>
                    <input type="number" class="prop-input" value="${Math.round(node.height())}" oninput="selectedNode && selectedNode.height(+this.value); updatePanelShapes(selectedNode); contentLayer.batchDraw()" ${isLocked?'disabled':''}>
                </div>
            </div>
            <div class="prop-group">
                <label class="prop-label">Border Style</label>
                <select class="prop-select" onchange="if(selectedNode){ selectedNode.setAttr('borderStyle', this.value); updatePanelShapes(selectedNode); contentLayer.batchDraw(); saveState(); }">
                    ${bsOptions}
                </select>
            </div>
            <div class="prop-group">
                <label class="prop-label">Border Color</label>
                <div class="prop-row">
                    <input type="color" class="prop-color-swatch" value="${node.getAttr('strokeColor')}" oninput="selectedNode && selectedNode.setAttr('strokeColor', this.value); updatePanelShapes(selectedNode); contentLayer.batchDraw()">
                    <input type="number" class="prop-input" value="${node.getAttr('strokeWidth')}" oninput="selectedNode && selectedNode.setAttr('strokeWidth', +this.value); updatePanelShapes(selectedNode); contentLayer.batchDraw()">
                </div>
            </div>
            <div class="prop-group">
                <label class="prop-label">Fill (transparent = none)</label>
                <input type="color" class="prop-color-swatch" value="${node.getAttr('fillColor')}" oninput="selectedNode && selectedNode.setAttr('fillColor', this.value); updatePanelShapes(selectedNode); contentLayer.batchDraw()">
            </div>
            
            ${pType !== 'diagonal' ? `
            <div class="prop-divider" style="margin-top:10px;"></div>
            <div class="prop-section-title">Auto-Grid Division</div>
            <div class="prop-row">
                <div class="form-group"><label class="prop-label">Rows</label><input type="number" id="gridRows" class="prop-input" value="2" min="1"></div>
                <div class="form-group"><label class="prop-label">Cols</label><input type="number" id="gridCols" class="prop-input" value="2" min="1"></div>
            </div>
            <div class="form-group">
                <label class="prop-label">Gutter (px)</label><input type="number" id="gridGutter" class="prop-input" value="10" min="0">
            </div>
            <button class="btn btn-primary" style="width:100%; justify-content:center;" onclick="splitPanelGrid(selectedNode, +document.getElementById('gridRows').value, +document.getElementById('gridCols').value, +document.getElementById('gridGutter').value)">Split Panel Grid</button>
            ` : ''}

            <div class="prop-divider" style="margin-top:10px;"></div>
            <div class="prop-section-title">Inner Image</div>
            <button class="btn btn-secondary" style="width:100%; justify-content:center; margin-bottom:8px;" onclick="assigningToPanel = selectedNode.id(); openForgeModal();">Assign Source Frame</button>
            
            ${node.getAttr('innerImageFilename') ? `
            <div class="prop-group">
                <label class="prop-label">Modify Inner Mask</label>
                <label style="font-size:0.75rem; display:flex; align-items:center; gap:6px; cursor:pointer;">
                    <input type="checkbox" onchange="togglePanelImageDrag(selectedNode, this.checked)"> Unlock Touch Dragging
                </label>
            </div>
            <div class="prop-group">
                <label class="prop-label">Image Scale</label>
                <input type="range" min="0.1" max="5" step="0.01" value="${node.getAttr('innerImageScale')}" oninput="updatePanelImageTransform(selectedNode, 'scale', +this.value)">
            </div>
            <div class="prop-group">
                <label class="prop-label">Pan Mask X / Y</label>
                <div class="prop-row">
                    <input type="number" class="prop-input" value="${Math.round(node.getAttr('innerImageX'))}" oninput="updatePanelImageTransform(selectedNode, 'x', +this.value)">
                    <input type="number" class="prop-input" value="${Math.round(node.getAttr('innerImageY'))}" oninput="updatePanelImageTransform(selectedNode, 'y', +this.value)">
                </div>
            </div>
            <button class="btn btn-secondary" style="width:100%; justify-content:center; color:var(--red);" onclick="clearPanelImage(selectedNode)">Remove Image</button>
            ` : ''}
        `;
    } else if (type === 'speed_lines') {
        const modeOpts = ['radial','directional','sector'].map(m =>
            `<option value="${m}" ${(node.getAttr('slMode')||'radial')===m?'selected':''}>${m}</option>`).join('');
        body.innerHTML = lockHeader + `
            <div class="prop-section-title">Speed Lines</div>
            <div class="prop-group"><label class="prop-label">Mode</label>
                <select class="prop-select" onchange="if(selectedNode){ selectedNode.setAttr('slMode',this.value); rebuildSpeedLinesShape(selectedNode); saveState(); }">${modeOpts}</select>
            </div>
            <div class="prop-group"><label class="prop-label">Line Count</label>
                <input type="range" min="8" max="128" step="4" class="prop-input" value="${node.getAttr('lineCount')||48}"
                    oninput="if(selectedNode){ selectedNode.setAttr('lineCount',+this.value); rebuildSpeedLinesShape(selectedNode); }">
            </div>
            <div class="prop-group"><label class="prop-label">Inner / Outer Radius</label>
                <div class="prop-row">
                    <input type="number" class="prop-input" value="${node.getAttr('innerRadius')||60}"
                        oninput="if(selectedNode){ selectedNode.setAttr('innerRadius',+this.value); rebuildSpeedLinesShape(selectedNode); }">
                    <input type="number" class="prop-input" value="${node.getAttr('outerRadius')||300}"
                        oninput="if(selectedNode){ selectedNode.setAttr('outerRadius',+this.value); rebuildSpeedLinesShape(selectedNode); }">
                </div>
            </div>
            <div class="prop-group"><label class="prop-label">Color</label>
                <input type="color" class="prop-color-swatch" value="${node.getAttr('lineColor')||'#000000'}"
                    oninput="if(selectedNode){ selectedNode.setAttr('lineColor',this.value); rebuildSpeedLinesShape(selectedNode); }">
            </div>
            <div class="prop-group">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.8rem;cursor:pointer;">
                    <input type="checkbox" ${node.getAttr('taper')!==false?'checked':''} onchange="if(selectedNode){ selectedNode.setAttr('taper',this.checked); rebuildSpeedLinesShape(selectedNode); saveState(); }"> Taper lines
                </label>
            </div>
            <div class="prop-group"><label class="prop-label">Opacity</label>
                <input type="range" min="0" max="1" step="0.05" class="prop-input" value="${node.opacity()}"
                    oninput="if(selectedNode){ selectedNode.opacity(+this.value); contentLayer.batchDraw(); }">
            </div>`;

    } else if (type === 'impact_frame') {
        const styleOpts = ['starburst','manga_radial','halftone_burst','energy_wave'].map(s =>
            `<option value="${s}" ${(node.getAttr('impactStyle')||'starburst')===s?'selected':''}>${s.replace(/_/g,' ')}</option>`).join('');
        body.innerHTML = lockHeader + `
            <div class="prop-section-title">Impact Frame</div>
            <div class="prop-group"><label class="prop-label">Style</label>
                <select class="prop-select" onchange="if(selectedNode){ selectedNode.setAttr('impactStyle',this.value); rebuildImpactFrameShape(selectedNode); saveState(); }">${styleOpts}</select>
            </div>
            <div class="prop-group"><label class="prop-label">Width / Height</label>
                <div class="prop-row">
                    <input type="number" class="prop-input" value="${node.getAttr('ifW')||CANVAS_W}"
                        oninput="if(selectedNode){ selectedNode.setAttr('ifW',+this.value); rebuildImpactFrameShape(selectedNode); }">
                    <input type="number" class="prop-input" value="${node.getAttr('ifH')||380}"
                        oninput="if(selectedNode){ selectedNode.setAttr('ifH',+this.value); rebuildImpactFrameShape(selectedNode); }">
                </div>
            </div>
            <div class="prop-group"><label class="prop-label">Primary Color</label>
                <input type="color" class="prop-color-swatch" value="${node.getAttr('colorPrimary')||'#FFFFFF'}"
                    oninput="if(selectedNode){ selectedNode.setAttr('colorPrimary',this.value); rebuildImpactFrameShape(selectedNode); }">
            </div>
            <div class="prop-group"><label class="prop-label">Secondary Color</label>
                <input type="color" class="prop-color-swatch" value="${node.getAttr('colorSecondary')||'#FFE066'}"
                    oninput="if(selectedNode){ selectedNode.setAttr('colorSecondary',this.value); rebuildImpactFrameShape(selectedNode); }">
            </div>
            <div class="prop-group"><label class="prop-label">Spike Count (Starburst)</label>
                <input type="range" min="6" max="40" step="2" class="prop-input" value="${node.getAttr('spikeCount')||20}"
                    oninput="if(selectedNode){ selectedNode.setAttr('spikeCount',+this.value); rebuildImpactFrameShape(selectedNode); }">
            </div>
            <div class="prop-group"><label class="prop-label">Opacity</label>
                <input type="range" min="0" max="1" step="0.05" class="prop-input" value="${node.opacity()}"
                    oninput="if(selectedNode){ selectedNode.opacity(+this.value); contentLayer.batchDraw(); }">
            </div>
            <div class="prop-group"><label class="prop-label">Layer Order</label>
                <div class="prop-row">
                    <button class="btn btn-secondary" onclick="selectedNode && selectedNode.moveUp(); contentLayer.batchDraw(); renderLayerList(); saveState();">▲ Up</button>
                    <button class="btn btn-secondary" onclick="selectedNode && selectedNode.moveDown(); contentLayer.batchDraw(); renderLayerList(); saveState();">▼ Down</button>
                </div>
            </div>`;
    }
}

function updateStatus() {
    const count = getAllElements().length;
    document.getElementById('statusElemCount').textContent = count;
    if (!selectedNode) document.getElementById('statusSelected').style.display = 'none';
}

function setTool(t) {
    document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tool-' + t)?.classList.add('active');
}

function toggleProps() {
    const p = document.getElementById('propsPanel');
    if (p.style.display === 'none' || p.style.display === '') {
        p.style.display = 'flex';
        window.lockViewportZoom && window.lockViewportZoom();
    } else {
        p.style.display = 'none';
        window.restoreViewportZoom && window.restoreViewportZoom();
    }
}

function getSceneJSON() {
    const elements = [];
    getAllElements().forEach(node => {
        const type = node.getAttr('elemType');
        const base = {
            uid:      node.id(),
            type:     type,
            elem_name: node.getAttr('elemName') || type,
            x:        Math.round(node.x()),
            y:        Math.round(node.y()),
            rotation: node.rotation(),
            scaleX:   node.scaleX(),
            scaleY:   node.scaleY(),
            opacity:  node.opacity(),
            z_index:  node.zIndex(),
            isLocked: node.getAttr('isLocked') || false,
        };

        if (type === 'image') {
            Object.assign(base, {
                filename:  node.getAttr('filename'),
                frame_id:  node.getAttr('frameId'),
                img_width: node.width(),
                img_height:node.height(),
            });
        } else if (['balloon','shout','thought','whisper'].includes(type)) {
            const kt = node.getAttr('kTextRef');
            Object.assign(base, {
                balloon_shape:   node.getAttr('balloonShape'),
                balloon_w:       node.getAttr('balloonW'),
                balloon_h:       node.getAttr('balloonH'),
                balloon_padding: node.getAttr('balloonPadding') || '12',
                fill_color:      node.getAttr('fillColor'),
                stroke_color:    node.getAttr('strokeColor'),
                stroke_width:    node.getAttr('strokeWidth'),
                tail_x:          node.getAttr('tailX'),
                tail_y:          node.getAttr('tailY'),
                text:            kt ? kt.text() : '',
                font_family:     kt ? kt.fontFamily() : 'Bangers',
                font_size:       kt ? kt.fontSize() : 16,
                text_color:      kt ? kt.fill() : '#111111',
            });
        } else if (type === 'sfx') {
            Object.assign(base, {
                text:        node.text(),
                font_family: node.fontFamily(),
                font_size:   node.fontSize(),
                text_color:  node.fill(),
                blur_px:     node.getAttr('blurPx') || 0,
            });
        } else if (type === 'caption') {
            const kt = node.getAttr('kTextRef');
            Object.assign(base, {
                text:            kt ? kt.text() : '',
                font_family:     kt ? kt.fontFamily() : 'Lora',
                font_size:       kt ? kt.fontSize() : 15,
                text_color:      kt ? kt.fill() : '#D4A017',
                fill_color:      node.getAttr('fillColor'),
                width:           node.getAttr('captionW') || node.width(),
                caption_padding: node.getAttr('captionPadding') || '12 14',
                reflowText:      node.getAttr('reflowText') || false,
            });
        } else if (type === 'panel') {
            Object.assign(base, {
                panel_type:         node.getAttr('panelType') || 'rectangular',
                angle_left_deg:     node.getAttr('anglLeftDeg') || 0,
                angle_right_deg:    node.getAttr('anglRightDeg') || 0,
                width:              node.width(),
                height:             node.height(),
                stroke_color:       node.getAttr('strokeColor'),
                stroke_width:       node.getAttr('strokeWidth'),
                fill_color:         node.getAttr('fillColor'),
                border_style:       node.getAttr('borderStyle') || 'solid',
                innerImageFilename: node.getAttr('innerImageFilename'),
                innerImageFrameId:  node.getAttr('innerImageFrameId'),
                innerImageX:        node.getAttr('innerImageX'),
                innerImageY:        node.getAttr('innerImageY'),
                innerImageScale:    node.getAttr('innerImageScale')
            });
        } else if (type === 'speed_lines') {
            Object.assign(base, {
                mode:            node.getAttr('slMode')        || 'radial',
                line_count:      node.getAttr('lineCount')     || 48,
                inner_radius:    node.getAttr('innerRadius')   || 60,
                outer_radius:    node.getAttr('outerRadius')   || 300,
                angle_start_deg: node.getAttr('angleStartDeg') || 0,
                angle_span_deg:  node.getAttr('angleSpanDeg')  || 360,
                line_color:      node.getAttr('lineColor')     || '#000000',
                line_width:      node.getAttr('lineWidth')     || 1.5,
                taper:           node.getAttr('taper') !== false,
            });
        } else if (type === 'impact_frame') {
            Object.assign(base, {
                width:           node.getAttr('ifW')            || CANVAS_W,
                height:          node.getAttr('ifH')            || 380,
                impact_style:    node.getAttr('impactStyle')    || 'starburst',
                color_primary:   node.getAttr('colorPrimary')   || '#FFFFFF',
                color_secondary: node.getAttr('colorSecondary') || '#FFE066',
                spike_count:     node.getAttr('spikeCount')     || 20,
            });
        }
        elements.push(base);
    });

    return JSON.stringify({
        canvas_width:  CANVAS_W,
        canvas_height: CANVAS_H,
        bg_color:      BG_COLOR,
        elements,
    });
}

function loadSceneJSON(json, skipSave = false) {
    if (!json) return;
    let scene;
    try { scene = JSON.parse(json); } catch (e) { console.error("Error parsing Scene JSON", e); return; }

    const wasRestoring = isRestoring;
    isRestoring = true;

    getAllElements().forEach(n => n.destroy());
    contentLayer.batchDraw();

    const els = scene.elements || [];
    els.forEach(el => {
        let type = el.type;
        const _isLocked = el.isLocked || false;

        // Migrations
        if (type === 'diagonal_panel') type = 'panel';
        if (type === 'gitaigo') type = 'sfx';

        if (type === 'image') {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.src = el.filename;
            img.onload = () => {
                const kImg = new Konva.Image({
                    id: el.uid || uid(), name: 'element',
                    x: el.x, y: el.y, image: img,
                    width: el.img_width, height: el.img_height,
                    scaleX: el.scaleX || 1, scaleY: el.scaleY || 1,
                    rotation: el.rotation || 0, opacity: el.opacity ?? 1,
                    draggable: true,
                });
                kImg.setAttr('elemType', 'image');
                kImg.setAttr('elemName', el.elem_name || 'Frame');
                kImg.setAttr('frameId',  el.frame_id);
                kImg.setAttr('filename', el.filename);
                kImg.setAttr('isLocked', _isLocked);
                kImg.on('click tap', () => selectNode(kImg));
                contentLayer.add(kImg);
                contentLayer.batchDraw();
                renderLayerList();
            };
        } else if (['balloon','shout','thought','whisper'].includes(type)) {
            const group = new Konva.Group({
                id: el.uid || uid(), name: 'element',
                x: el.x, y: el.y, rotation: el.rotation || 0,
                scaleX: el.scaleX || 1, scaleY: el.scaleY || 1,
                draggable: true,
            });
            group.setAttr('elemType', type);
            group.setAttr('elemName', el.elem_name || 'Balloon');
            group.setAttr('balloonShape', el.balloon_shape || 'classic_oval');
            group.setAttr('balloonW',     el.balloon_w || 240);
            group.setAttr('balloonH',     el.balloon_h || 100);
            group.setAttr('fillColor',    el.fill_color || '#F5F5F0');
            group.setAttr('strokeColor',  el.stroke_color || '#222222');
            group.setAttr('strokeWidth',  el.stroke_width || 2);
            group.setAttr('tailX',        el.tail_x);
            group.setAttr('tailY',        el.tail_y);
            group.setAttr('isLocked',     _isLocked);

            group.setAttr('balloonPadding', el.balloon_padding || '12');
            const shape = _buildBalloonShape(el.balloon_shape||'classic_oval', el.balloon_w||240, el.balloon_h||100, el.fill_color||'#F5F5F0', el.stroke_color||'#222', el.stroke_width||2, el.tail_x, el.tail_y);
            const _lpad = parsePadding(el.balloon_padding || '12');
            const _lw   = el.balloon_w || 240;
            const _lh   = el.balloon_h || 100;
            const kText = new Konva.Text({
                x: _lpad.left, y: _lpad.top,
                width:  Math.max(20, _lw - _lpad.left - _lpad.right),
                height: Math.max(10, _lh - _lpad.top  - _lpad.bottom),
                text: el.text||'', fontSize: el.font_size||16,
                fontFamily: el.font_family||'Bangers', fill: el.text_color||'#111111',
                align: 'center', verticalAlign: 'middle', lineHeight: 1.3, wrap: 'word'
            });
            group.add(shape, kText);
            kText.moveToTop();
            group.setAttr('kTextRef', kText);
            group.on('click tap', () => selectNode(group));
            contentLayer.add(group);
            contentLayer.batchDraw();
            renderLayerList();
        } else if (type === 'sfx') {
            const kText = new Konva.Text({ id: el.uid||uid(), name:'element', x:el.x, y:el.y, text:el.text||'SFX', fontSize:el.font_size||64, fontFamily:el.font_family||'Impact', fill:el.text_color||'#FFFFFF', opacity: el.opacity??1, rotation:el.rotation||0, draggable:true });
            kText.setAttr('elemType','sfx'); kText.setAttr('elemName', el.elem_name || 'SFX'); kText.setAttr('isLocked', _isLocked);
            const bPx = el.blur_px || 0;
            kText.setAttr('blurPx', bPx);
            if (bPx > 0) {
                kText.cache();
                kText.filters([Konva.Filters.Blur]);
                kText.blurRadius(bPx);
            }
            kText.on('click tap', () => selectNode(kText));
            contentLayer.add(kText); contentLayer.batchDraw(); renderLayerList();
        } else if (type === 'caption') {
            const w = el.captionW || el.width || CANVAS_W;
            const group = new Konva.Group({ id:el.uid||uid(), name:'element', x:el.x, y:el.y, width: w, height: el.height || 42, scaleX:el.scaleX||1, scaleY:el.scaleY||1, rotation:el.rotation||0, draggable:true });
            group.setAttr('elemType','caption'); 
            group.setAttr('elemName','Caption'); 
            group.setAttr('fillColor', el.fill_color||'#1A0A00'); 
            group.setAttr('isLocked', _isLocked); 
            group.setAttr('captionW', w);
            group.setAttr('captionPadding', el.caption_padding || '12 14');
            group.setAttr('reflowText', el.reflowText || false);
            
            const pad = parsePadding(group.getAttr('captionPadding'));
            
            const bg = new Konva.Rect({ x:0, y:0, width:w, height:el.height || 42, fill:el.fill_color||'#1A0A00' });
            const txt = new Konva.Text({ x:pad.left, y:pad.top, width:w-pad.left-pad.right, text:el.text||'', fontSize:el.font_size||15, fontFamily:el.font_family||'Lora', fill:el.text_color||'#D4A017', align:'left' });
            group.add(bg,txt); 
            group.setAttr('kTextRef',txt);
            
            const newH = txt.height() + pad.top + pad.bottom;
            bg.height(newH);
            group.height(newH);
            
            group.on('click tap', () => selectNode(group));
            contentLayer.add(group); contentLayer.batchDraw(); renderLayerList();
        } else if (type === 'panel') {
            // Legacy diagonal mapping if loaded from older JSON
            const pType = el.type === 'diagonal_panel' ? 'diagonal' : (el.panel_type || 'rectangular');
            
            const group = new Konva.Group({ id: el.uid || uid(), name: 'element', x: el.x || el.dpX || 0, y: el.y || el.dpY || 0, width: el.width || el.dpW || CANVAS_W-40, height: el.height || el.dpH || 400, rotation: el.rotation || 0, scaleX: el.scaleX || 1, scaleY: el.scaleY || 1, opacity: el.opacity ?? 1, draggable: true });
            group.setAttr('elemType', 'panel');
            group.setAttr('elemName', el.elem_name || 'Panel');
            group.setAttr('panelType', pType);
            group.setAttr('anglLeftDeg', el.angle_left_deg || 0);
            group.setAttr('anglRightDeg', el.angle_right_deg || 0);
            group.setAttr('fillColor', el.fill_color || 'transparent');
            group.setAttr('strokeColor', el.stroke_color || '#888888');
            group.setAttr('strokeWidth', el.stroke_width || 2);
            group.setAttr('borderStyle', el.border_style || 'solid');
            group.setAttr('isLocked', _isLocked);
            
            group.setAttr('innerImageFilename', el.innerImageFilename || '');
            group.setAttr('innerImageFrameId', el.innerImageFrameId || null);
            group.setAttr('innerImageX', el.innerImageX || 0);
            group.setAttr('innerImageY', el.innerImageY || 0);
            group.setAttr('innerImageScale', el.innerImageScale || 1.0);

            const bg = new Konva.Shape({ fill: group.getAttr('fillColor'), name: 'panelBg' });
            const clipGroup = new Konva.Group({ name: 'clipGroup' });
            const stroke = new Konva.Shape({ stroke: group.getAttr('strokeColor'), strokeWidth: group.getAttr('strokeWidth'), fillEnabled: false, name: 'panelStroke' });

            group.add(bg, clipGroup, stroke);
            updatePanelShapes(group);

            if (el.innerImageFilename) {
                const imgObj = new Image();
                imgObj.crossOrigin = 'anonymous';
                imgObj.src = el.innerImageFilename;
                imgObj.onload = () => {
                    const kImg = new Konva.Image({
                        image: imgObj,
                        x: group.getAttr('innerImageX'),
                        y: group.getAttr('innerImageY'),
                        scaleX: group.getAttr('innerImageScale'),
                        scaleY: group.getAttr('innerImageScale'),
                        name: 'innerImage',
                        draggable: false 
                    });
                    kImg.on('dragmove', () => {
                        group.setAttr('innerImageX', kImg.x());
                        group.setAttr('innerImageY', kImg.y());
                        if (selectedNode === group) renderPropertiesFor(group);
                    });
                    clipGroup.add(kImg);
                    contentLayer.batchDraw();
                };
            }
            
            group.on('click tap', () => selectNode(group));
            contentLayer.add(group);

        } else if (type === 'speed_lines') {
            const group = new Konva.Group({ id: el.uid || uid(), x: el.x||0, y: el.y||0, rotation: el.rotation||0, scaleX: el.scaleX||1, scaleY: el.scaleY||1, opacity: el.opacity??1, draggable: true, name:'element' });
            group.setAttr('elemType', 'speed_lines');
            group.setAttr('elemName', el.elem_name || 'Speed Lines');
            group.setAttr('slMode',        el.mode         || 'radial');
            group.setAttr('lineCount',     el.line_count   || 48);
            group.setAttr('innerRadius',   el.inner_radius || 60);
            group.setAttr('outerRadius',   el.outer_radius || 300);
            group.setAttr('angleStartDeg', el.angle_start_deg || 0);
            group.setAttr('angleSpanDeg',  el.angle_span_deg  || 360);
            group.setAttr('lineColor',     el.line_color   || '#000000');
            group.setAttr('lineWidth',     el.line_width   || 1.5);
            group.setAttr('taper',         el.taper !== false);
            group.setAttr('isLocked',      el.isLocked || false);
            group.add(_buildSpeedLinesShape(group));
            group.on('click tap', () => selectNode(group));
            contentLayer.add(group);
            renderLayerList();

        } else if (type === 'impact_frame') {
            const group = new Konva.Group({ id: el.uid||uid(), x: el.x||0, y: el.y||0, rotation: el.rotation||0, scaleX: el.scaleX||1, scaleY: el.scaleY||1, opacity: el.opacity??1, draggable: true, name:'element' });
            group.setAttr('elemType', 'impact_frame');
            group.setAttr('elemName', el.elem_name || 'Impact Frame');
            group.setAttr('ifW',            el.width           || CANVAS_W);
            group.setAttr('ifH',            el.height          || 380);
            group.setAttr('impactStyle',    el.impact_style    || 'starburst');
            group.setAttr('colorPrimary',   el.color_primary   || '#FFFFFF');
            group.setAttr('colorSecondary', el.color_secondary || '#FFE066');
            group.setAttr('spikeCount',     el.spike_count     || 20);
            group.setAttr('isLocked',       el.isLocked || false);
            group.add(_buildImpactFrameShape(group));
            group.on('click tap', () => selectNode(group));
            contentLayer.add(group);
            renderLayerList();
        }
    });

    setTimeout(() => {
        const nodes = contentLayer.find('.element');
        const zMap = {};
        (scene.elements || []).forEach(el => { if (el.uid) zMap[el.uid] = el.z_index || 0; });
        const sorted = nodes.slice().sort((a, b) => (zMap[a.id()] || 0) - (zMap[b.id()] || 0));
        sorted.forEach(n => n.moveToTop());
        contentLayer.batchDraw();
        renderLayerList();
        
        isRestoring = wasRestoring;
        if (!skipSave && !isRestoring) saveState();
    }, 350);
}

// ── Save / Load ───────────────────────────────────────────────────────────────
async function saveArrangement() {
    if (!CANVAS_ID) {
        const res = await api('bang_api.php', {
            action: 'create_canvas', composite_id: COMPOSITE_ID,
            name: CANVAS_NAME || 'Panel Strip', canvas_width: CANVAS_W, canvas_height: CANVAS_H, bg_color: BG_COLOR,
        });
        if (!res.success) return toast(res.message || 'Canvas create failed', 'error');
        CANVAS_ID = res.canvas_id;
        ARR_ID    = res.arrangement_id;
        history.replaceState(null, '', `bang.php?composite_id=${COMPOSITE_ID}&canvas_id=${CANVAS_ID}`);
    }

    const res = await api('bang_api.php', {
        action: 'save_arrangement', canvas_id: CANVAS_ID, composite_id: COMPOSITE_ID,
        arrangement_id: ARR_ID || '', name: 'Draft', scene_json: getSceneJSON(),
    });
    if (res.success) { ARR_ID = res.arrangement_id; toast('Saved!', 'success'); } 
    else toast(res.message || 'Save failed', 'error');
}

async function saveArrangementAs() {
    const name = document.getElementById('arrNewName').value.trim();
    if (!name) return toast('Enter a name', 'error');
    if (!CANVAS_ID) return toast('Save the canvas first (use Save button)', 'error');

    const res = await api('bang_api.php', {
        action: 'save_arrangement', canvas_id: CANVAS_ID, composite_id: COMPOSITE_ID,
        arrangement_id: '', name, scene_json: getSceneJSON(),
    });
    if (res.success) { ARR_ID = res.arrangement_id; toast('Saved as: ' + name, 'success'); loadArrangementList(); }
    else toast(res.message || 'Failed', 'error');
}

async function loadArrangementList() {
    const res = await fetch(`bang_api.php?action=list_arrangements&composite_id=${COMPOSITE_ID}`).then(r=>r.json());
    if (!res.success) return;
    document.getElementById('arrList').innerHTML = res.arrangements.map(a => `
        <div class="arr-item ${a.id == ARR_ID ? 'active' : ''}" onclick="loadArrangement(${a.id})">
            <div style="flex:1;">
                <div class="arr-item-name">${escHtml(a.name)}</div>
                <div class="arr-item-meta">Strip: ${escHtml(a.canvas_name)} • ${a.updated_at?.split('T')[0] || ''}</div>
            </div>
        </div>
    `).join('');
}

async function loadArrangement(id) {
    const res = await fetch(`bang_api.php?action=load_arrangement&arrangement_id=${id}`).then(r=>r.json());
    if (res.success) {
        ARR_ID = id;
        CANVAS_ID = res.canvas.id;
        CANVAS_NAME = res.canvas.name;
        CANVAS_W = parseInt(res.canvas.canvas_width);
        CANVAS_H = parseInt(res.canvas.canvas_height);
        BG_COLOR = res.canvas.bg_color;
        
        resizeCanvas(CANVAS_W, CANVAS_H, BG_COLOR);
        loadSceneJSON(res.arrangement.scene_json);
        
        history.replaceState(null, '', `bang.php?composite_id=${COMPOSITE_ID}&canvas_id=${CANVAS_ID}`);
        toast('Arrangement loaded', 'info');
        closeArrangements();
    } else toast(res.message || 'Load failed', 'error');
}

function openArrangements() { document.getElementById('arrModal').classList.add('active'); loadArrangementList(); }
function closeArrangements() { document.getElementById('arrModal').classList.remove('active'); }

// ── Export ────────────────────────────────────────────────────────────────────
async function exportRender() {
    if (!CANVAS_ID) { toast('Save the arrangement first before exporting.', 'error'); return; }
    const pyapiUrl = localStorage.getItem('sage_pyapi_url') || 'http://127.0.0.1:8009';

    const sceneJson = getSceneJSON();
    toast('Sending to PyAPI…', 'info');

    const res = await api('bang_api.php', {
        action:        'export_render',
        canvas_id:     CANVAS_ID,
        composite_id:  COMPOSITE_ID,
        arrangement_id: ARR_ID || '',
        scene_json:    sceneJson,
        canvas_width:  CANVAS_W,
        canvas_height: CANVAS_H,
        bg_color:      BG_COLOR,
        pyapi_url:     pyapiUrl,
    });

    if (res.success) toast(`Rendered! Frame #${res.frame_id}`, 'success');
    else toast('Render failed: ' + (res.message || 'Unknown error'), 'error');
}

// ── Canvas Settings ───────────────────────────────────────────────────────────
function openCanvasSettings() {
    document.getElementById('csName').value   = CANVAS_NAME || '';
    document.getElementById('csWidth').value  = CANVAS_W;
    document.getElementById('csHeight').value = CANVAS_H;
    document.getElementById('csBgColor').value = BG_COLOR;
    document.getElementById('csBgColorPicker').value = BG_COLOR;
    document.getElementById('canvasModal').classList.add('active');
}
function closeCanvasSettings() { document.getElementById('canvasModal').classList.remove('active'); }

async function applyCanvasSettings() {
    const name  = document.getElementById('csName').value.trim() || 'Panel Strip';
    const w     = Math.max(100, parseInt(document.getElementById('csWidth').value)  || 1024);
    const h     = Math.max(100, parseInt(document.getElementById('csHeight').value) || 1448);
    const bg    = document.getElementById('csBgColor').value || '#000000';
    CANVAS_NAME = name; BG_COLOR    = bg;
    resizeCanvas(w, h, bg);

    if (CANVAS_ID) {
        await api('bang_api.php', { action:'update_canvas_settings', canvas_id:CANVAS_ID, name, canvas_width:w, canvas_height:h, bg_color:bg });
        toast('Canvas settings saved', 'success');
    } else toast('Canvas settings applied (save to persist)', 'info');
    closeCanvasSettings();
}

// ── Filter Forge Modal (NarSeq Logic) ─────────────────────────────────────────
let ffState = {
    fuzz: null, doc: null, kg: null, seq: null, storyboard: null, map_run: null,
    vectorText: '', textSearch: '', sketchId: '', frameId: ''
};
let ffCurrentPage = 1;
let ffTotalPages = 1;
let ffDebounceTimer;

function openForgeModal() {
    document.getElementById('ffBackdrop').classList.add('active');
    runForgeSearch(1);
}
function closeForgeModal() { document.getElementById('ffBackdrop').classList.remove('active'); assigningToPanel = null; }

function switchForgeTab(tabId) {
    document.querySelectorAll('.forge-sidebar-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.forge-sidebar-btn[data-tab="${tabId}"]`).classList.add('active');
    document.querySelectorAll('.forge-tab-pane').forEach(p => p.classList.remove('active'));
    document.getElementById(`pane-${tabId}`).classList.add('active');
    if (tabId === 'results') runForgeSearch(1);
}

function ffDebounceSearch(slot, q) {
    clearTimeout(ffDebounceTimer);
    ffDebounceTimer = setTimeout(() => {
        const dd = document.getElementById(`ffDrop-${slot}`);
        if (!q && slot !== 'storyboard') { dd.classList.remove('open'); return; }
        dd.innerHTML = '<div style="padding:8px 10px;font-size:0.75rem;color:var(--muted);">Searching...</div>';
        dd.classList.add('open');
        
        let url = `filter_forge_api.php?action=list_filter_options&mode=${slot}&q=${encodeURIComponent(q || '')}&entity_type=sketches`;
        if (slot === 'storyboard') url = `narseq_api.php?action=list_storyboards&q=${encodeURIComponent(q || '')}`;
        
        fetch(url)
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success' || !res.data || !res.data.length) {
                    dd.innerHTML = '<div style="padding:8px 10px;font-size:0.75rem;color:var(--muted);">No results</div>';
                    return;
                }
                dd.innerHTML = res.data.map(item => {
                    const safe = JSON.stringify(item).replace(/"/g, '&quot;');
                    return `<div class="ff-dropdown-item" onclick="ffSelectItem('${slot}', ${safe})">
                        <span>${item.label}</span>
                        <span style="font-size:0.65rem; color:var(--muted); margin-left:8px;">${item.meta||''}</span>
                    </div>`;
                }).join('');
            }).catch(err => {
                dd.innerHTML = '<div style="padding:8px 10px;font-size:0.75rem;color:#ef4444;">Error searching</div>';
            });
    }, 300);
}

function ffSelectItem(slot, item) {
    ffState[slot] = item;
    document.getElementById(`ffSearch-${slot}`).value = '';
    document.getElementById(`ffDrop-${slot}`).classList.remove('open');
    renderActiveFilters();
    switchForgeTab('results');
}

function ffApplyVector() {
    ffState.vectorText = document.getElementById('ffSearch-vector').value.trim();
    renderActiveFilters(); switchForgeTab('results');
}

function ffApplyTextId() {
    ffState.textSearch = document.getElementById('ffSearch-text').value.trim();
    ffState.sketchId = document.getElementById('ffSearch-sketchId').value.trim();
    ffState.frameId = document.getElementById('ffSearch-frameId').value.trim();
    renderActiveFilters(); switchForgeTab('results');
}

function removeFfFilter(key) {
    if (['vectorText', 'textSearch', 'sketchId', 'frameId'].includes(key)) {
        ffState[key] = '';
        const el = document.getElementById(`ffSearch-${key.replace('Text','-text').replace('Search','-text').replace('vectorText','-vector').replace('sketchId','-sketchId').replace('frameId','-frameId')}`);
        if (el) el.value = '';
    } else ffState[key] = null;
    renderActiveFilters(); runForgeSearch(1);
}

function renderActiveFilters() {
    const bar = document.getElementById('ffActiveFilters');
    bar.innerHTML = '';
    const labels = { fuzz: 'Fuzz', doc: 'Doc', kg: 'KG', seq: 'Seq', storyboard: 'Board', map_run: 'Run', vectorText: 'Semantic', textSearch: 'Text', sketchId: 'Sketch', frameId: 'Frame' };
    
    let hasAny = false;
    for (const [k, v] of Object.entries(ffState)) {
        if (v && (typeof v === 'object' ? v.id : v.toString().length > 0)) {
            hasAny = true;
            const display = typeof v === 'object' ? v.label : v;
            bar.innerHTML += `<div class="forge-pill">${labels[k]}: ${display} <span class="forge-pill-close" onclick="removeFfFilter('${k}')">×</span></div>`;
        }
    }
    if (!hasAny) bar.innerHTML = '<div style="font-size:0.7rem; color:var(--muted); font-style:italic;">No active filters.</div>';
}


function assignImageToPanel(group, frameId, filename) {
    group.setAttr('innerImageFrameId', frameId);
    group.setAttr('innerImageFilename', filename);
    group.setAttr('innerImageX', 0);
    group.setAttr('innerImageY', 0);
    group.setAttr('innerImageScale', 1.0);
    
    const clipGroup = group.findOne('.clipGroup') || group; 
    const oldImg = group.findOne('.innerImage');
    if (oldImg) oldImg.destroy();

    const imgObj = new Image();
    imgObj.crossOrigin = 'anonymous';
    imgObj.src = filename;
    imgObj.onload = () => {
        const pW = group.width() || group.getAttr('dpW') || 400;
        const pH = group.height() || group.getAttr('dpH') || 300;
        
        // "Cover" scaling: scale to fill the panel without distorting
        const scaleX = pW / imgObj.width;
        const scaleY = pH / imgObj.height;
        const scale = Math.max(scaleX, scaleY);
        
        group.setAttr('innerImageScale', scale);

        const kImg = new Konva.Image({
            image: imgObj,
            x: 0, y: 0,
            scaleX: scale, scaleY: scale,
            name: 'innerImage',
            draggable: false
        });
        kImg.on('dragmove', () => {
            group.setAttr('innerImageX', kImg.x());
            group.setAttr('innerImageY', kImg.y());
            if (selectedNode === group) renderPropertiesFor(group);
        });
        
        clipGroup.add(kImg);
        contentLayer.batchDraw();
        if (selectedNode === group) renderPropertiesFor(group);
        saveState();
    };
}




function handleForgeImageSelect(frameId, filename, name) {
    if (assigningToPanel) {
        const group = contentLayer.findOne('#' + assigningToPanel);
        if (group && (group.getAttr('elemType') === 'panel' || group.getAttr('elemType') === 'diagonal_panel')) {
            assignImageToPanel(group, frameId, filename);
        }
        assigningToPanel = null;
        closeForgeModal();
    } else {
        addImageElement(frameId, filename, name);
        closeForgeModal();
    }
}


/*
function handleForgeImageSelect(frameId, filename, name) {
    if (assigningToPanel) {
        const group = contentLayer.findOne('#' + assigningToPanel);
        if (group && group.getAttr('elemType') === 'panel') {
            group.setAttr('innerImageFrameId', frameId);
            group.setAttr('innerImageFilename', filename);
            group.setAttr('innerImageX', 0);
            group.setAttr('innerImageY', 0);
            group.setAttr('innerImageScale', 1.0);
            
            const clipGroup = group.findOne('.clipGroup') || group; 
            const oldImg = group.findOne('.innerImage');
            if (oldImg) oldImg.destroy();

            const imgObj = new Image();
            imgObj.crossOrigin = 'anonymous';
            imgObj.src = filename;
            imgObj.onload = () => {
                const scale = group.width() ? (group.width() / imgObj.width) : 1;
                group.setAttr('innerImageScale', scale);

                const kImg = new Konva.Image({
                    image: imgObj,
                    x: 0, y: 0,
                    scaleX: scale, scaleY: scale,
                    name: 'innerImage',
                    draggable: false
                });
                kImg.on('dragmove', () => {
                    group.setAttr('innerImageX', kImg.x());
                    group.setAttr('innerImageY', kImg.y());
                    if (selectedNode === group) renderPropertiesFor(group);
                });
                
                clipGroup.add(kImg);

                contentLayer.batchDraw();
                if (selectedNode === group) renderPropertiesFor(group);
                saveState();
            };
        }
        assigningToPanel = null;
        closeForgeModal();
    } else {
        addImageElement(frameId, filename, name);
        closeForgeModal();
    }
}
*/




function runForgeSearch(page) {
    ffCurrentPage = page;
    const p = new URLSearchParams();
    p.set('action', 'list_frames');
    p.set('entity_type', 'sketches');
    p.set('filter_mode', 'intersection');
    p.set('per_page', '9');
    p.set('page', page);

    let hasFilter = false;
    let useNarseqApi = false;

    if (ffState.fuzz) { p.set('fuzz_id', ffState.fuzz.id); hasFilter = true; }
    if (ffState.doc) { p.set('doc_id', ffState.doc.id); hasFilter = true; }
    if (ffState.kg) { p.set('kg_node_id', ffState.kg.id); hasFilter = true; }
    if (ffState.seq) { p.set('seq_id', ffState.seq.id); hasFilter = true; }
    if (ffState.storyboard) { p.set('storyboard_id', ffState.storyboard.id); hasFilter = true; useNarseqApi = true; }
    if (ffState.map_run) { p.set('map_run_id', ffState.map_run.id); hasFilter = true; }
    if (ffState.vectorText) { p.set('vector_text', ffState.vectorText); hasFilter = true; }
    if (ffState.textSearch) { p.set('search', ffState.textSearch); hasFilter = true; }
    if (ffState.sketchId) { p.set('entity_id', ffState.sketchId); hasFilter = true; }
    if (ffState.frameId) { p.set('frame_id', ffState.frameId); hasFilter = true; }

    if (!hasFilter) { p.set('sort', 'newest'); p.set('sort_by', 'id'); p.set('sort_order', 'desc'); }
    if (useNarseqApi) p.set('action', 'list_storyboard_frames');

    const grid = document.getElementById('ffResultGrid');
    grid.innerHTML = '<div class="forge-result-empty">Searching Forge...</div>';
    document.getElementById('ffPagination').style.display = 'none';
    document.getElementById('ffResultMeta').textContent = 'Searching...';

    const endpoint = useNarseqApi ? 'narseq_api.php?' : 'filter_forge_api.php?';

    fetch(endpoint + p.toString())
        .then(async r => { if (!r.ok) throw new Error(); return r.json(); })
        .then(res => {
            if (res.status !== 'success') { grid.innerHTML = `<div class="forge-result-empty">Error: ${res.message}</div>`; return; }
            ffTotalPages = res.meta.pages;
            document.getElementById('ffResultMeta').textContent = `Found ${res.meta.total} matches.`;
            
            if (!res.data.length) { grid.innerHTML = '<div class="forge-result-empty">No results found.</div>'; return; }
            
            grid.innerHTML = res.data.map(row => `
                <div class="ff-result-card" onclick="handleForgeImageSelect(${row.frame_id}, '${escAttr(row.filename)}', '${escAttr(row.entity_name || row.frame_name)}')">
                    <img src="${row.filename}" loading="lazy">
                    <div class="ff-result-label">${row.entity_name || row.frame_name || ''}</div>
                </div>
            `).join('');

            if (ffTotalPages > 1) {
                document.getElementById('ffPagination').style.display = 'flex';
                document.getElementById('ffPageLabel').innerHTML = `Pg <input type="number" value="${page}" min="1" max="${ffTotalPages}" style="width:40px; background:var(--bg-float); color:var(--text); border:1px solid var(--border); border-radius:4px; text-align:center; padding:2px; font-size:0.75rem; font-family:var(--font-mono);" onchange="if(this.value) runForgeSearch(parseInt(this.value))"> of ${ffTotalPages}`;
                document.getElementById('ffPrevBtn').disabled = (page <= 1);
                document.getElementById('ffNextBtn').disabled = (page >= ffTotalPages);
            }
        }).catch(err => {
            grid.innerHTML = '<div class="forge-result-empty">Network error.</div>';
        });
}

// ── Prevent mobile auto-zoom on input focus (without disabling pinch zoom) ──
(function() {
    const vmeta = document.getElementById('bangViewportMeta');
    if (!vmeta) return;
    const originalContent = vmeta.getAttribute('content');
    const noZoomContent   = originalContent.replace(/,?\s*user-scalable=[^,]*/i, '')
                                           .replace(/,?\s*maximum-scale=[^,]*/i, '')
                          + ', maximum-scale=1, user-scalable=no';

    window.lockViewportZoom    = () => vmeta.setAttribute('content', noZoomContent);
    window.restoreViewportZoom = () => vmeta.setAttribute('content', originalContent);
})();

// ── Keyboard shortcuts ────────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (e.key === 'Delete' || e.key === 'Backspace') deleteSelected();
    if (e.key === 'Escape') deselect();
    if ((e.ctrlKey || e.metaKey)) {
        if (e.key.toLowerCase() === 's') { e.preventDefault(); saveArrangement(); }
        if (e.key.toLowerCase() === 'z') {
            e.preventDefault();
            if (e.shiftKey) redo(); else undo();
        }
        if (e.key.toLowerCase() === 'y') { e.preventDefault(); redo(); }
    }
});

// ── Robust API Fetch Helper ───────────────────────────────────────────────────
async function api(url, data) {
    try {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));
        const r = await fetch(url, { method: 'POST', body: fd });
        const text = await r.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error("API Parse Error:", text);
            return { success: false, message: 'Server output error (check console)' };
        }
    } catch (err) {
        console.error("Fetch Network Error:", err);
        return { success: false, message: 'Network communication failed' };
    }
}

function toast(msg, type = 'info') {
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = msg;
    document.getElementById('toast-container').appendChild(el);
    setTimeout(() => el.remove(), 3000);
}

function escHtml(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escAttr(s) { return String(s ?? '').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('statusSize').textContent = `${CANVAS_W} × ${CANVAS_H}`;

    const IMPORTED_FRAMES = <?= $jsImportedFrames ?> || [];
    
    // Load scene from DB or Cascade-Auto-Load from Composite Frames
    const initialSceneStr = <?= $jsArrangement ?>;
    const urlParams = new URLSearchParams(window.location.search);
    const autoGrid = urlParams.get('grid') === '1';

    if (initialSceneStr) {
        loadSceneJSON(initialSceneStr, true);
    } else if (IMPORTED_FRAMES.length > 0 || (COMPOSITE_FRAMES && COMPOSITE_FRAMES.length > 0)) {
        
        const framesToUse = IMPORTED_FRAMES.length > 0 ? IMPORTED_FRAMES : COMPOSITE_FRAMES;
        
        if (autoGrid) {
            const rows = parseInt(urlParams.get('r')) || 3;
            const cols = parseInt(urlParams.get('c')) || 3;
            const gutter = parseInt(urlParams.get('g')) || 10;
            
            const cw = (CANVAS_W - (cols + 1) * gutter) / cols;
            const ch = (CANVAS_H - (rows + 1) * gutter) / rows;
            
            framesToUse.forEach((f, i) => {
                const r = Math.floor(i / cols);
                const c = i % cols;
                const x = gutter + c * (cw + gutter);
                const y = gutter + r * (ch + gutter);
                
                const panel = addPanel({ x, y, width: cw, height: ch, noSelect: true });
                assignImageToPanel(panel, f.id, f.filename);
            });
        } else {
            COMPOSITE_FRAMES.forEach((f, i) => {
                addImageElement(f.id, f.filename, f.name, { x: 50 + (i*40), y: 50 + (i*40) });
            });
        }
    }

    document.getElementById('csName').value   = CANVAS_NAME;
    document.getElementById('csWidth').value  = CANVAS_W;
    document.getElementById('csHeight').value = CANVAS_H;

    zoomFit();
    renderLayerList();
    updateStatus();
    
    // Set initial snapshot state
    setTimeout(saveState, 500);
});
</script>

<?php
$content = ob_get_clean();
$content .= $eruda ?? '';
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
