<?php
// public/beap.php
// Beat Extraction & Artboard Pipeline (BEAP)
// Scene → Beats + Shot Intents → Panels → New Narrative Sequence

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// Determine entry view:
//   ?view=beats&session=N  — beat editor for an existing session
//   ?view=session_list     — list of past sessions (default)
//   (no params)            — sketch picker / sequence picker
$view      = $_GET['view'] ?? '';
$sessionId = isset($_GET['session']) ? (int)$_GET['session'] : 0;

// ─── Helper ──────────────────────────────────────────────────────────────────
function beapThumb(array $row): string {
    foreach (['thumb', 'thumbnail', 'image', 'image_url', 'image_path', 'file_path', 'path', 'src', 'url', 'filename', 'file_name'] as $key) {
        if (!empty($row[$key]) && is_string($row[$key])) {
            $v = $row[$key];
            if (strpos($v, 'http') === 0 || strpos($v, 'view_frame.php') !== false) return $v;
            $parts = array_map('rawurlencode', explode('/', ltrim($v, '/')));
            return '/' . implode('/', $parts);
        }
    }
    return '';
}

// ─────────────────────────────────────────────────────────────────────────────
// SHARED CSS & HEAD
// ─────────────────────────────────────────────────────────────────────────────
ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css">

<style>
:root,[data-theme="dark"]{
    --bp-bg:#080b10;--bp-surface:#0e1319;--bp-card:#111820;--bp-border:#1c2535;
    --bp-text:#c8d4e8;--bp-dim:#5a6a80;--bp-amber:#f5a623;--bp-teal:#3ab5c8;
    --bp-purple:#9b72e0;--bp-green:#3ab87f;
}
[data-theme="light"]{
    --bp-bg:#f4f6fa;--bp-surface:#fff;--bp-card:#fff;--bp-border:#d0d8e8;
    --bp-text:#1a2233;--bp-dim:#7a8aaa;--bp-amber:#c8880a;--bp-teal:#1a8090;
    --bp-purple:#7040c0;--bp-green:#1a8060;
}
body{background:var(--bp-bg);color:var(--bp-text);font-family:'Syne',system-ui,sans-serif;margin:0;padding:0;}

/* ── NAV ── */
.bp-nav{display:flex;align-items:center;gap:10px;padding:10px 16px;background:rgba(0,0,0,.6);border-bottom:1px solid var(--bp-border);position:sticky;top:0;z-index:100;backdrop-filter:blur(6px);flex-wrap:wrap;}
[data-theme="light"] .bp-nav{background:rgba(244,246,250,.92);}
.bp-nav-title{font-family:'Space Mono',monospace;font-size:.85rem;font-weight:bold;color:var(--bp-purple);letter-spacing:1px;text-transform:uppercase;margin-right:auto;}

/* ── BUTTONS ── */
.bp-btn{padding:7px 14px;border-radius:4px;border:1px solid;font-family:'Space Mono',monospace;font-size:.75rem;cursor:pointer;transition:all .15s;white-space:nowrap;background:var(--bp-card);color:var(--bp-dim);border-color:var(--bp-border);}
.bp-btn:hover{color:var(--bp-teal);border-color:var(--bp-teal);}
.bp-btn-teal{border-color:var(--bp-teal);background:var(--bp-teal);color:#000;font-weight:bold;}
.bp-btn-teal:hover{filter:brightness(1.1);}
.bp-btn-amber{border-color:var(--bp-amber);background:var(--bp-amber);color:#000;font-weight:bold;}
.bp-btn-amber:hover{filter:brightness(1.1);}
.bp-btn-purple{border-color:var(--bp-purple);background:var(--bp-purple);color:#fff;font-weight:bold;}
.bp-btn-purple:hover{filter:brightness(1.1);}
.bp-btn-sm{padding:4px 8px;font-size:.65rem;}
.bp-btn:disabled{opacity:.45;cursor:not-allowed;}

/* ── INPUT ── */
.bp-input{width:100%;box-sizing:border-box;background:var(--bp-card);color:var(--bp-text);border:1px solid var(--bp-border);border-radius:4px;padding:8px 12px;font-family:'Syne',sans-serif;font-size:.85rem;outline:none;transition:border-color .2s;}
.bp-input:focus{border-color:var(--bp-teal);}
textarea.bp-input{resize:vertical;min-height:80px;line-height:1.5;}

/* ── WORKSPACE ── */
.bp-workspace{max-width:860px;margin:0 auto;padding:24px 15px 100px;}

/* ── LIST CARDS ── */
.bp-list-card{display:flex;align-items:center;background:var(--bp-card);border:1px solid var(--bp-border);border-radius:6px;overflow:hidden;transition:border-color .2s;margin-bottom:8px;}
.bp-list-card:hover{border-color:var(--bp-teal);}
.bp-list-card-body{flex:1;padding:12px 14px;min-width:0;}
.bp-list-card-title{font-family:'Space Mono',monospace;font-size:.85rem;font-weight:bold;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.bp-list-card-meta{font-size:.72rem;color:var(--bp-dim);margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.bp-list-card-actions{display:flex;border-left:1px solid var(--bp-border);align-self:stretch;}
.bp-list-card-action{background:transparent;border:none;border-right:1px solid var(--bp-border);padding:0 12px;cursor:pointer;color:var(--bp-dim);font-size:.9rem;transition:all .2s;}
.bp-list-card-action:last-child{border-right:none;}
.bp-list-card-action:hover{color:var(--bp-teal);background:rgba(58,181,200,.07);}
.bp-list-card-action.danger:hover{color:#ff4444;background:rgba(255,68,68,.07);}

/* ── FILTER / PAGINATION BAR ── */
.bp-filter-bar{display:flex;gap:10px;align-items:center;background:var(--bp-surface);padding:10px 12px;border:1px solid var(--bp-border);border-radius:6px;margin-bottom:16px;flex-wrap:wrap;}
.bp-pager{display:flex;align-items:center;gap:6px;margin-left:auto;}
.bp-pager-input{width:46px;text-align:center;padding:4px;background:var(--bp-card);color:var(--bp-text);border:1px solid var(--bp-border);border-radius:4px;font-family:'Space Mono',monospace;font-size:.75rem;}

/* ── DROPDOWN ── */
.bp-dd-wrap{position:relative;flex:1;min-width:200px;}
.bp-dropdown{border:1px solid var(--bp-border);border-radius:4px;background:var(--bp-card);max-height:220px;overflow-y:auto;display:none;position:absolute;z-index:200;width:100%;left:0;top:42px;box-shadow:0 4px 15px rgba(0,0,0,.35);overscroll-behavior:contain;}
.bp-dropdown.open{display:block;}
.bp-dd-item{padding:8px 10px;font-size:.75rem;cursor:pointer;border-bottom:1px solid rgba(255,255,255,.03);color:var(--bp-text);display:flex;justify-content:space-between;align-items:center;}
.bp-dd-item:hover{background:rgba(58,181,200,.1);color:var(--bp-teal);}
.bp-dd-item .dd-sub{font-size:.65rem;color:var(--bp-dim);max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-left:8px;}

/* ── SECTION TABS ── */
.bp-tabs{display:flex;gap:0;border-bottom:2px solid var(--bp-border);margin-bottom:20px;}
.bp-tab{padding:8px 16px;font-family:'Space Mono',monospace;font-size:.72rem;text-transform:uppercase;letter-spacing:1px;cursor:pointer;color:var(--bp-dim);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;}
.bp-tab.active{color:var(--bp-purple);border-bottom-color:var(--bp-purple);}
.bp-tab:hover:not(.active){color:var(--bp-text);}
.bp-tab-pane{display:none;}
.bp-tab-pane.active{display:block;}

/* ── BEAT CARDS ── */
.beat-card{background:var(--bp-card);border:1px solid var(--bp-border);border-radius:6px;padding:14px 14px 14px 16px;margin-bottom:10px;transition:border-color .2s;position:relative;}
.beat-card:hover{border-color:rgba(155,114,224,.4);}
.beat-card.has-panels{border-color:rgba(58,184,127,.35);}
.beat-num{font-family:'Space Mono',monospace;font-size:.65rem;color:var(--bp-purple);letter-spacing:2px;text-transform:uppercase;margin-bottom:6px;}
.beat-text-row{display:flex;gap:10px;align-items:flex-start;}
.beat-text-col{flex:1;min-width:0;}
.beat-field-label{font-family:'Space Mono',monospace;font-size:.6rem;text-transform:uppercase;letter-spacing:1px;color:var(--bp-dim);margin-bottom:4px;}
.beat-text-display{font-size:.85rem;line-height:1.5;color:var(--bp-text);cursor:pointer;min-height:1.4em;}
.beat-text-display:hover{color:var(--bp-amber);}
.beat-shot-display{font-size:.78rem;line-height:1.4;color:var(--bp-dim);cursor:pointer;}
.beat-shot-display:hover{color:var(--bp-teal);}
.beat-actions{display:flex;gap:6px;margin-top:10px;flex-wrap:wrap;}

/* ── PANEL CHIPS inside a beat ── */
.panel-list{display:flex;flex-direction:column;gap:6px;margin-top:10px;padding-top:10px;border-top:1px solid var(--bp-border);}
.panel-chip{background:rgba(58,184,127,.07);border:1px solid rgba(58,184,127,.25);border-radius:4px;padding:8px 10px;font-size:.75rem;}
.panel-chip-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;}
.panel-chip-layout{font-family:'Space Mono',monospace;font-size:.6rem;color:var(--bp-green);text-transform:uppercase;letter-spacing:1px;}
.panel-chip-action{font-size:.65rem;color:var(--bp-dim);}
.panel-chip-prompt{color:var(--bp-text);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}

/* ── INLINE EDITOR ── */
.beat-inline-editor{display:none;flex-direction:column;gap:8px;margin-top:10px;padding-top:10px;border-top:1px solid var(--bp-border);}
.beat-inline-editor.open{display:flex;}

/* ── MODAL ── */
.bp-modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:300000;display:none;align-items:center;justify-content:center;backdrop-filter:blur(2px);}
.bp-modal-backdrop.active{display:flex;}
.bp-modal{width:100%;max-width:480px;background:var(--bp-surface);border:1px solid var(--bp-border);border-radius:8px;padding:20px;box-shadow:0 10px 40px rgba(0,0,0,.5);margin:16px;}
.bp-modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
.bp-modal-title{font-family:'Space Mono',monospace;font-size:.9rem;font-weight:bold;text-transform:uppercase;letter-spacing:1px;}
.bp-modal-close{background:transparent;border:none;color:var(--bp-dim);cursor:pointer;font-size:1.2rem;}

/* ── SKETCH PREVIEW ── */
.bp-sketch-preview{display:flex;gap:14px;align-items:flex-start;background:var(--bp-surface);border:1px solid var(--bp-border);border-radius:6px;padding:12px;margin-bottom:20px;}
.bp-preview-thumb{width:90px;height:90px;flex-shrink:0;border-radius:4px;overflow:hidden;background:#000;border:1px solid var(--bp-border);}
.bp-preview-thumb img{width:100%;height:100%;object-fit:cover;display:block;}
.bp-preview-info{flex:1;min-width:0;}
.bp-preview-title{font-size:.95rem;font-weight:bold;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.bp-preview-desc{font-size:.78rem;color:var(--bp-dim);line-height:1.4;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}

/* ── DEPTH TOGGLE ── */
.depth-toggle{display:flex;gap:0;border:1px solid var(--bp-border);border-radius:4px;overflow:hidden;}
.depth-btn{flex:1;padding:7px 10px;font-family:'Space Mono',monospace;font-size:.68rem;text-transform:uppercase;letter-spacing:1px;cursor:pointer;background:var(--bp-card);color:var(--bp-dim);border:none;transition:all .15s;}
.depth-btn.active{background:var(--bp-purple);color:#fff;font-weight:bold;}

/* ── STATUS BADGE ── */
.bp-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-family:'Space Mono',monospace;font-size:.6rem;text-transform:uppercase;letter-spacing:1px;font-weight:bold;}
.bp-badge-pending{background:rgba(90,106,128,.2);color:var(--bp-dim);}
.bp-badge-beats{background:rgba(155,114,224,.2);color:var(--bp-purple);}
.bp-badge-panels{background:rgba(58,184,127,.2);color:var(--bp-green);}
.bp-badge-exported{background:rgba(245,166,35,.2);color:var(--bp-amber);}

/* ── EMPTY STATE ── */
.bp-empty{text-align:center;padding:40px 20px;color:var(--bp-dim);font-size:.85rem;border:2px dashed rgba(58,181,200,.2);border-radius:6px;margin:20px 0;}
.bp-empty-icon{font-size:2rem;margin-bottom:8px;}
.bp-empty-title{font-family:'Space Mono',monospace;font-size:.8rem;color:var(--bp-teal);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;}

/* ── SPINNER ── */
.bp-spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:bpspin .6s linear infinite;vertical-align:middle;margin-right:5px;}
@keyframes bpspin{to{transform:rotate(360deg)}}

/* ── CHAR TAGS on beat card ── */
.beat-char-tags{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px;}
.beat-char-tag{background:rgba(155,114,224,.15);border:1px solid rgba(155,114,224,.3);border-radius:12px;padding:2px 8px;font-size:.62rem;color:var(--bp-purple);font-family:'Space Mono',monospace;}

/* ── CHARACTER PICKER MODAL ── */
.char-modal{width:100%;max-width:500px;max-height:85vh;background:var(--bp-surface);border:1px solid var(--bp-border);border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,.5);margin:16px;display:flex;flex-direction:column;overflow:hidden;}
.char-modal-body{flex:1;overflow-y:auto;padding:10px 16px;}
.char-search-wrap{padding:10px 16px 0;flex-shrink:0;}
.char-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.04);cursor:pointer;}
.char-item:last-child{border-bottom:none;}
.char-item input[type=checkbox]{width:16px;height:16px;accent-color:var(--bp-purple);flex-shrink:0;cursor:pointer;}
.char-item-name{font-size:.82rem;font-weight:bold;color:var(--bp-text);}
.char-item-desc{font-size:.68rem;color:var(--bp-dim);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px;}
.char-modal-footer{padding:12px 16px;border-top:1px solid var(--bp-border);display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;flex-shrink:0;}
.char-count-label{font-family:'Space Mono',monospace;font-size:.68rem;color:var(--bp-dim);}

/* ── SEQ SKETCH THUMB ── */
.seq-skt-thumb{width:56px;height:56px;flex-shrink:0;border-radius:4px;overflow:hidden;background:var(--bp-bg);border:1px solid var(--bp-border);cursor:pointer;}
.seq-skt-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:filter .15s;}
.seq-skt-thumb:hover img{filter:brightness(1.15);}
.seq-skt-no-img{width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--bp-dim);font-size:.6rem;}

/* PhotoSwipe over everything */
.pswp{z-index:400000!important;}
</style>

<?php
// ─────────────────────────────────────────────────────────────────────────────
// VIEW: BEAT EDITOR (session active)
// ─────────────────────────────────────────────────────────────────────────────
if ($view === 'beats' && $sessionId) {
    // Load session from DB
    $sessStmt = $pdo->prepare("SELECT * FROM beap_sessions WHERE id = ?");
    $sessStmt->execute([$sessionId]);
    $sess = $sessStmt->fetch(PDO::FETCH_ASSOC);
    if (!$sess) {
        echo '<div style="padding:40px;color:red;">Session #' . $sessionId . ' not found.</div>';
        $content = ob_get_clean();
        $spw->renderLayout($content, 'BEAP — Session Not Found', $spw->getProjectPath() . '/templates/curation.php');
        exit;
    }

    // Load beats
    $beatStmt = $pdo->prepare(
        "SELECT id, beat_order, beat_text, shot_intent, panel_data, status
         FROM beap_beats WHERE session_id = ? ORDER BY beat_order ASC, id ASC"
    );
    $beatStmt->execute([$sessionId]);
    $beats = $beatStmt->fetchAll(PDO::FETCH_ASSOC);

    // Load generator configs
    $gcStmt = $pdo->query(
        "SELECT id, title, config_id FROM generator_config WHERE active = 1 ORDER BY list_order ASC, title ASC"
    );
    $genConfigs = $gcStmt->fetchAll(PDO::FETCH_ASSOC);

    // Find defaults
    $defaultBeatGenId  = 0;
    $defaultPanelGenId = 0;
    foreach ($genConfigs as $gc) {
        if ($gc['config_id'] === 'beap_beat_extractor_v1')  $defaultBeatGenId  = (int)$gc['id'];
        if ($gc['config_id'] === 'beap_panel_composer_v1') $defaultPanelGenId = (int)$gc['id'];
    }

    $pageTitle = 'BEAP — ' . htmlspecialchars($sess['sketch_name']);
    ?>

<div class="bp-nav" style="padding-left:70px;">
    <a href="beap.php" class="bp-btn">← Sessions</a>
    <span class="bp-nav-title">🎬 BEAP — <?= htmlspecialchars($sess['sketch_name']) ?></span>
    <span class="bp-badge <?= [
        'beats_pending' => 'bp-badge-pending',
        'beats_done'    => 'bp-badge-beats',
        'panels_done'   => 'bp-badge-panels',
        'exported'      => 'bp-badge-exported',
    ][$sess['status']] ?? 'bp-badge-pending' ?>"><?= htmlspecialchars($sess['status']) ?></span>
    <?php if ($sess['narseq_id']): ?>
    <a href="narseq.php?id=<?= (int)$sess['narseq_id'] ?>" class="bp-btn bp-btn-amber bp-btn-sm" target="_blank">
        <i class="bi bi-film"></i> Sequence #<?= (int)$sess['narseq_id'] ?>
    </a>
    <?php endif; ?>
</div>

<div class="bp-workspace">

    <!-- ── Sketch info card ── -->
    <div class="bp-sketch-preview" id="sketchPreviewCard">
        <div class="bp-preview-thumb" id="previewThumbWrap">
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--bp-dim);font-size:.7rem;">…</div>
        </div>
        <div class="bp-preview-info">
            <div class="bp-preview-title"><?= htmlspecialchars($sess['sketch_name']) ?> <span style="color:var(--bp-dim);font-size:.7rem;">#<?= (int)$sess['sketch_id'] ?></span></div>
            <div class="bp-preview-desc" id="previewDesc"><?= htmlspecialchars($sess['sketch_desc'] ?? 'No description.') ?></div>
        </div>
    </div>

    <!-- ── Controls ── -->
    <div style="background:var(--bp-surface);border:1px solid var(--bp-border);border-radius:6px;padding:14px;margin-bottom:20px;display:flex;flex-direction:column;gap:12px;">

        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <div style="flex:1;min-width:160px;">
                <div class="beat-field-label" style="margin-bottom:6px;">Beat Extractor</div>
                <select id="beatGenSelect" class="bp-input" style="padding:6px 10px;">
                    <?php foreach ($genConfigs as $gc): ?>
                    <option value="<?= $gc['id'] ?>" <?= (int)$gc['id'] === $defaultBeatGenId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($gc['title']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1;min-width:160px;">
                <div class="beat-field-label" style="margin-bottom:6px;">Panel Composer</div>
                <select id="panelGenSelect" class="bp-input" style="padding:6px 10px;">
                    <?php foreach ($genConfigs as $gc): ?>
                    <option value="<?= $gc['id'] ?>" <?= (int)$gc['id'] === $defaultPanelGenId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($gc['title']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1;min-width:160px;">
                <div class="beat-field-label" style="margin-bottom:6px;">Continuity Model</div>
                <select id="continuityGenSelect" class="bp-input" style="padding:6px 10px;">
                    <?php foreach ($genConfigs as $gc): ?>
                    <option value="<?= $gc['id'] ?>" <?= $gc['config_id'] === 'e2a4d0f6c1b84f2c9a7d1e5b3f6a8c10' ? 'selected' : '' ?>>
                        <?= htmlspecialchars($gc['title']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <div>
                <div class="beat-field-label" style="margin-bottom:6px;">Depth</div>
                <div class="depth-toggle">
                    <button class="depth-btn <?= $sess['depth'] === 'short'  ? 'active' : '' ?>" onclick="setDepth('short')">Short</button>
                    <button class="depth-btn <?= $sess['depth'] === 'normal' ? 'active' : '' ?>" onclick="setDepth('normal')">Normal</button>
                    <button class="depth-btn <?= $sess['depth'] === 'epic'   ? 'active' : '' ?>" onclick="setDepth('epic')">Epic</button>
                </div>
            </div>
            <div style="margin-left:auto;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                <button class="bp-btn bp-btn-purple" id="extractBeatsBtn" onclick="extractBeats()">
                    <i class="bi bi-lightning-charge"></i> <?= empty($beats) ? 'Extract Beats' : 'Re-Extract Beats' ?>
                </button>
                <button class="bp-btn bp-btn-teal" onclick="paneliseAll()" <?= empty($beats) ? 'disabled' : '' ?> id="paneliseAllBtn">
                    <i class="bi bi-grid-3x3-gap"></i> Panelise All
                </button>
                <button class="bp-btn bp-btn-amber" onclick="openExportModal()" <?= empty($beats) ? 'disabled' : '' ?> id="exportBtn">
                    <i class="bi bi-box-arrow-up"></i> Export
                </button>
            </div>
        </div>
    </div>

    <!-- ── Beat list ── -->
    <div id="beatList">
    <?php if (empty($beats)): ?>
        <div class="bp-empty">
            <div class="bp-empty-icon">🎞</div>
            <div class="bp-empty-title">No Beats Yet</div>
            <div>Click <strong>Extract Beats</strong> above to run the first AI pass.</div>
        </div>
    <?php else: ?>
        <?php foreach ($beats as $b): ?>
        <?php
            $panels    = $b['panel_data'] ? json_decode($b['panel_data'], true) : null;
            $hasPanels = !empty($panels);
        ?>
        <div class="beat-card <?= $hasPanels ? 'has-panels' : '' ?>" id="beat-card-<?= $b['id'] ?>" data-beat-id="<?= $b['id'] ?>">
            <div class="beat-num">Beat <?= (int)$b['beat_order'] + 1 ?></div>
            <div class="beat-text-row">
                <div class="beat-text-col">
                    <div class="beat-field-label">Beat</div>
                    <div class="beat-text-display" onclick="openBeatEditor(<?= $b['id'] ?>)"><?= htmlspecialchars($b['beat_text']) ?></div>
                </div>
                <div class="beat-text-col">
                    <div class="beat-field-label">Shot Intent</div>
                    <div class="beat-shot-display" onclick="openBeatEditor(<?= $b['id'] ?>)"><?= htmlspecialchars($b['shot_intent'] ?? '') ?></div>
                </div>
            </div>

            <!-- Inline editor -->
            <div class="beat-inline-editor" id="editor-<?= $b['id'] ?>">
                <div>
                    <div class="beat-field-label">Beat Text</div>
                    <textarea class="bp-input" id="bt-<?= $b['id'] ?>" style="min-height:60px;"><?= htmlspecialchars($b['beat_text']) ?></textarea>
                </div>
                <div>
                    <div class="beat-field-label">Shot Intent</div>
                    <textarea class="bp-input" id="si-<?= $b['id'] ?>" style="min-height:50px;"><?= htmlspecialchars($b['shot_intent'] ?? '') ?></textarea>
                </div>
                <div style="display:flex;gap:8px;">
                    <button class="bp-btn bp-btn-teal bp-btn-sm" onclick="saveBeat(<?= $b['id'] ?>)">Save</button>
                    <button class="bp-btn bp-btn-sm" onclick="closeBeatEditor(<?= $b['id'] ?>)">Cancel</button>
                </div>
            </div>

            <!-- Panel data -->
            <?php if ($hasPanels): ?>
            <div class="panel-list" id="panels-<?= $b['id'] ?>">
                <?php foreach ($panels as $pi => $panel): ?>
                <div class="panel-chip">
                    <div class="panel-chip-header">
                        <span class="panel-chip-layout">Panel <?= $pi + 1 ?> · <?= htmlspecialchars($panel['layout_hint'] ?? '') ?></span>
                        <span class="panel-chip-action"><?= htmlspecialchars($panel['action_note'] ?? '') ?></span>
                    </div>
                    <div class="panel-chip-prompt"><?= htmlspecialchars($panel['panel_prompt'] ?? '') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="panel-list" id="panels-<?= $b['id'] ?>" style="display:none;"></div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="beat-actions">
                <button class="bp-btn bp-btn-sm" onclick="openBeatEditor(<?= $b['id'] ?>)"><i class="bi bi-pencil"></i> Edit</button>
                <button class="bp-btn bp-btn-sm" onclick="paneliseBeat(<?= $b['id'] ?>)" id="pbtn-<?= $b['id'] ?>">
                    <i class="bi bi-grid-3x3-gap"></i> <?= $hasPanels ? 'Re-Panelise' : 'Panelise' ?>
                </button>
                <button class="bp-btn bp-btn-sm" onclick="openCharModal(<?= $b['id'] ?>)" id="cbtn-<?= $b['id'] ?>" title="Assign characters &amp; run continuity">
                    <i class="bi bi-person-badge"></i> Continuity
                </button>
                <button class="bp-btn bp-btn-sm" onclick="deleteBeat(<?= $b['id'] ?>)" style="margin-left:auto;color:#f66;border-color:rgba(255,102,102,.3);" title="Delete this beat">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <!-- Assigned character tags (populated by JS after continuity) -->
            <div class="beat-char-tags" id="char-tags-<?= $b['id'] ?>"></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>

<!-- Character Continuity Modal -->
<div class="bp-modal-backdrop" id="charModal" onmousedown="if(event.target===this)closeCharModal()">
    <div class="char-modal">
        <div class="bp-modal-header" style="padding:14px 16px 0;flex-shrink:0;">
            <div class="bp-modal-title" style="color:var(--bp-purple);"><i class="bi bi-person-badge"></i> Character Continuity</div>
            <button class="bp-modal-close" onclick="closeCharModal()">✕</button>
        </div>
        <p style="padding:4px 16px 0;font-size:.78rem;color:var(--bp-dim);margin:0;flex-shrink:0;">
            Check characters appearing in this beat. An AI call will rewrite the beat text with their exact details while preserving shot intent.
        </p>
        <div class="char-search-wrap">
            <input type="text" id="charSearchInput" class="bp-input" placeholder="Filter characters…" oninput="filterChars(this.value)">
        </div>
        <input type="hidden" id="charModalBeatId">
        <div class="char-modal-body" id="charModalBody">
            <div style="text-align:center;padding:20px;color:var(--bp-dim);">Loading…</div>
        </div>
        <div class="char-modal-footer">
            <span class="char-count-label" id="charCountLabel">0 selected</span>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button class="bp-btn" onclick="closeCharModal()">Cancel</button>
                <button class="bp-btn bp-btn-purple" onclick="runContinuity()" id="runContinuityBtn">
                    <i class="bi bi-stars"></i> Run Continuity
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="bp-modal-backdrop" id="exportModal" onmousedown="if(event.target===this)closeExportModal()">
    <div class="bp-modal">
        <div class="bp-modal-header">
            <div class="bp-modal-title" style="color:var(--bp-amber);"><i class="bi bi-box-arrow-up"></i> Export to Sequence</div>
            <button class="bp-modal-close" onclick="closeExportModal()">✕</button>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <p style="font-size:.82rem;color:var(--bp-dim);margin:0;">
                Each panelised beat's panels will become individual sketch rows inside a new narrative sequence.
                Only beats with panels are exported.
            </p>
            <div>
                <label class="beat-field-label" style="display:block;margin-bottom:5px;">Sequence Name</label>
                <input type="text" id="exportSeqName" class="bp-input"
                       value="BEAP — <?= htmlspecialchars($sess['sketch_name']) ?>">
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
            <button class="bp-btn" onclick="closeExportModal()">Cancel</button>
            <button class="bp-btn bp-btn-amber" onclick="submitExport()">Create Sequence</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>
<script>
const SESSION_ID = <?= $sessionId ?>;
let currentDepth = <?= json_encode($sess['depth']) ?>;

// ── Load sketch preview frame ─────────────────────────────────────────────
(function loadPreview() {
    fetch('beap_api.php?action=get_sketch&sketch_id=<?= (int)$sess['sketch_id'] ?>')
        .then(r => r.json()).then(res => {
            if (res.success && res.sketch.frames.length) {
                const f = res.sketch.frames[0];
                document.getElementById('previewThumbWrap').innerHTML =
                    `<img src="${f.filename}" loading="lazy" style="width:100%;height:100%;object-fit:cover;">`;
            }
        }).catch(() => {});
})();

// ── Depth toggle ──────────────────────────────────────────────────────────
function setDepth(d) {
    currentDepth = d;
    document.querySelectorAll('.depth-btn').forEach(b => {
        b.classList.toggle('active', b.textContent.toLowerCase().trim() === d);
    });
    const fd = new URLSearchParams();
    fd.append('action', 'update_session_depth');
    fd.append('session_id', SESSION_ID);
    fd.append('depth', d);
    fetch('beap_api.php', { method: 'POST', body: fd }).then(r => r.json())
        .then(res => { if (!res.success) Toast.show('Depth save failed', 'error'); });
}

// ── Beat editor ───────────────────────────────────────────────────────────
function openBeatEditor(beatId) {
    document.getElementById('editor-' + beatId).classList.add('open');
}
function closeBeatEditor(beatId) {
    document.getElementById('editor-' + beatId).classList.remove('open');
}
function saveBeat(beatId) {
    const bt = document.getElementById('bt-' + beatId).value.trim();
    const si = document.getElementById('si-' + beatId).value.trim();
    const fd = new URLSearchParams();
    fd.append('action', 'update_beat');
    fd.append('beat_id', beatId);
    fd.append('beat_text', bt);
    fd.append('shot_intent', si);
    fetch('beap_api.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
        if (res.success) {
            // Update display
            const card = document.getElementById('beat-card-' + beatId);
            card.querySelector('.beat-text-display').textContent = bt;
            card.querySelector('.beat-shot-display').textContent = si;
            closeBeatEditor(beatId);
            Toast.show('Beat saved.', 'success');
        } else {
            Toast.show(res.message || 'Save failed', 'error');
        }
    });
}

// ── Extract beats ─────────────────────────────────────────────────────────
function extractBeats() {
    const btn = document.getElementById('extractBeatsBtn');
    const origHTML = btn.innerHTML;
    btn.innerHTML = '<span class="bp-spinner"></span>Extracting…';
    btn.disabled = true;

    const genId = document.getElementById('beatGenSelect').value;
    const fd = new URLSearchParams();
    fd.append('action', 'extract_beats');
    fd.append('session_id', SESSION_ID);
    fd.append('generator_config_id', genId);

    fetch('beap_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.innerHTML = origHTML;
            btn.disabled = false;
            if (!res.success) { Toast.show(res.message || 'Extraction failed', 'error'); return; }
            renderBeatList(res.beats);
            document.getElementById('paneliseAllBtn').disabled = false;
            document.getElementById('exportBtn').disabled = false;
            Toast.show(`${res.beats.length} beats extracted.`, 'success');
        })
        .catch(() => {
            btn.innerHTML = origHTML;
            btn.disabled = false;
            Toast.show('Network error', 'error');
        });
}

// ── Panelise single beat ──────────────────────────────────────────────────
function paneliseBeat(beatId) {
    const btn = document.getElementById('pbtn-' + beatId);
    const origHTML = btn.innerHTML;
    btn.innerHTML = '<span class="bp-spinner"></span>…';
    btn.disabled = true;

    const genId = document.getElementById('panelGenSelect').value;
    const fd = new URLSearchParams();
    fd.append('action', 'panelise_beat');
    fd.append('beat_id', beatId);
    fd.append('generator_config_id', genId);

    fetch('beap_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.innerHTML = '<i class="bi bi-grid-3x3-gap"></i> Re-Panelise';
            btn.disabled = false;
            if (!res.success) { Toast.show(res.message || 'Panelise failed', 'error'); return; }
            renderBeatPanels(beatId, res.panels);
            Toast.show('Beat panelised.', 'success');
        })
        .catch(() => {
            btn.innerHTML = origHTML;
            btn.disabled = false;
            Toast.show('Network error', 'error');
        });
}

// ── Panelise all (sequential to keep rolling context accurate) ────────────
async function paneliseAll() {
    const cards = Array.from(document.querySelectorAll('.beat-card'));
    if (!cards.length) return Toast.show('No beats to panelise', 'warn');
    const btn = document.getElementById('paneliseAllBtn');
    btn.disabled = true;
    let done = 0;
    for (const card of cards) {
        const beatId = card.dataset.beatId;
        btn.innerHTML = `<span class="bp-spinner"></span>Panelising ${done + 1}/${cards.length}…`;
        await paneliseBeatAsync(beatId);
        done++;
    }
    btn.innerHTML = '<i class="bi bi-grid-3x3-gap"></i> Panelise All';
    btn.disabled = false;
    Toast.show('All beats panelised!', 'success');
}

function paneliseBeatAsync(beatId) {
    const genId = document.getElementById('panelGenSelect').value;
    const fd = new URLSearchParams();
    fd.append('action', 'panelise_beat');
    fd.append('beat_id', beatId);
    fd.append('generator_config_id', genId);
    return fetch('beap_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) renderBeatPanels(beatId, res.panels);
        });
}

// ── Render helpers ────────────────────────────────────────────────────────
function renderBeatPanels(beatId, panels) {
    const card = document.getElementById('beat-card-' + beatId);
    if (!card) return;
    card.classList.add('has-panels');
    const panelList = document.getElementById('panels-' + beatId);
    panelList.style.display = '';
    panelList.innerHTML = panels.map((p, i) => `
        <div class="panel-chip">
            <div class="panel-chip-header">
                <span class="panel-chip-layout">Panel ${i + 1} · ${escHtml(p.layout_hint || '')}</span>
                <span class="panel-chip-action">${escHtml(p.action_note || '')}</span>
            </div>
            <div class="panel-chip-prompt">${escHtml(p.panel_prompt || '')}</div>
        </div>
    `).join('');
    const pbtn = document.getElementById('pbtn-' + beatId);
    if (pbtn) pbtn.innerHTML = '<i class="bi bi-grid-3x3-gap"></i> Re-Panelise';
}

function renderBeatList(beats) {
    const list = document.getElementById('beatList');
    if (!beats.length) {
        list.innerHTML = `<div class="bp-empty"><div class="bp-empty-icon">🎞</div><div class="bp-empty-title">No Beats</div></div>`;
        return;
    }
    list.innerHTML = beats.map((b, i) => `
        <div class="beat-card" id="beat-card-${b.id}" data-beat-id="${b.id}">
            <div class="beat-num">Beat ${i + 1}</div>
            <div class="beat-text-row">
                <div class="beat-text-col">
                    <div class="beat-field-label">Beat</div>
                    <div class="beat-text-display" onclick="openBeatEditor(${b.id})">${escHtml(b.beat_text)}</div>
                </div>
                <div class="beat-text-col">
                    <div class="beat-field-label">Shot Intent</div>
                    <div class="beat-shot-display" onclick="openBeatEditor(${b.id})">${escHtml(b.shot_intent || '')}</div>
                </div>
            </div>
            <div class="beat-inline-editor" id="editor-${b.id}">
                <div>
                    <div class="beat-field-label">Beat Text</div>
                    <textarea class="bp-input" id="bt-${b.id}" style="min-height:60px;">${escHtml(b.beat_text)}</textarea>
                </div>
                <div>
                    <div class="beat-field-label">Shot Intent</div>
                    <textarea class="bp-input" id="si-${b.id}" style="min-height:50px;">${escHtml(b.shot_intent || '')}</textarea>
                </div>
                <div style="display:flex;gap:8px;">
                    <button class="bp-btn bp-btn-teal bp-btn-sm" onclick="saveBeat(${b.id})">Save</button>
                    <button class="bp-btn bp-btn-sm" onclick="closeBeatEditor(${b.id})">Cancel</button>
                </div>
            </div>
            <div class="panel-list" id="panels-${b.id}" style="display:none;"></div>
            <div class="beat-actions">
                <button class="bp-btn bp-btn-sm" onclick="openBeatEditor(${b.id})"><i class="bi bi-pencil"></i> Edit</button>
                <button class="bp-btn bp-btn-sm" onclick="paneliseBeat(${b.id})" id="pbtn-${b.id}">
                    <i class="bi bi-grid-3x3-gap"></i> Panelise
                </button>
                <button class="bp-btn bp-btn-sm" onclick="openCharModal(${b.id})" id="cbtn-${b.id}" title="Assign characters &amp; run continuity">
                    <i class="bi bi-person-badge"></i> Continuity
                </button>
                <button class="bp-btn bp-btn-sm" onclick="deleteBeat(${b.id})" style="margin-left:auto;color:#f66;border-color:rgba(255,102,102,.3);" title="Delete this beat">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <div class="beat-char-tags" id="char-tags-${b.id}"></div>
        </div>
    `).join('');
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

// ── Export modal ──────────────────────────────────────────────────────────
function openExportModal() {
    document.getElementById('exportModal').classList.add('active');
    setTimeout(() => document.getElementById('exportSeqName').focus(), 50);
}
function closeExportModal() {
    document.getElementById('exportModal').classList.remove('active');
}
function submitExport() {
    const seqName = document.getElementById('exportSeqName').value.trim();
    if (!seqName) return Toast.show('Sequence name required', 'warn');

    const fd = new URLSearchParams();
    fd.append('action', 'export_to_sequence');
    fd.append('session_id', SESSION_ID);
    fd.append('seq_name', seqName);

    fetch('beap_api.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.success) {
                Toast.show(`Exported ${res.sketch_count} panels to sequence #${res.narseq_id}`, 'success');
                closeExportModal();
                // Update nav badge link after short delay
                setTimeout(() => {
                    const nav = document.querySelector('.bp-nav');
                    const existing = nav.querySelector('a[href*="narseq.php"]');
                    if (!existing) {
                        const a = document.createElement('a');
                        a.href = `narseq.php?id=${res.narseq_id}`;
                        a.className = 'bp-btn bp-btn-amber bp-btn-sm';
                        a.target = '_blank';
                        a.innerHTML = `<i class="bi bi-film"></i> Sequence #${res.narseq_id}`;
                        nav.appendChild(a);
                    }
                }, 600);
            } else {
                Toast.show(res.message || 'Export failed', 'error');
            }
        }).catch(() => Toast.show('Network error', 'error'));
}

// ── Delete single beat ────────────────────────────────────────────────────
function deleteBeat(beatId) {
    if (!confirm('Delete this beat? This cannot be undone.')) return;
    const fd = new URLSearchParams();
    fd.append('action', 'delete_beat');
    fd.append('beat_id', beatId);
    fetch('beap_api.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.success) {
                const card = document.getElementById('beat-card-' + beatId);
                if (card) {
                    card.style.transition = 'all .25s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(.97)';
                    setTimeout(() => {
                        card.remove();
                        reindexBeatNumbers();
                    }, 250);
                }
                Toast.show('Beat deleted.', 'success');
            } else {
                Toast.show(res.message || 'Delete failed', 'error');
            }
        }).catch(() => Toast.show('Network error', 'error'));
}

function reindexBeatNumbers() {
    document.querySelectorAll('.beat-card').forEach((card, i) => {
        const num = card.querySelector('.beat-num');
        if (num) num.textContent = 'Beat ' + (i + 1);
    });
}

// ── Character picker modal ────────────────────────────────────────────────
let allChars = [];       // full character list loaded once
let charModalBeatId = 0;
let selectedCharIds = new Set();

function openCharModal(beatId) {
    charModalBeatId = beatId;
    selectedCharIds = new Set();
    document.getElementById('charModalBeatId').value = beatId;
    document.getElementById('charSearchInput').value = '';
    document.getElementById('charCountLabel').textContent = '0 selected';
    document.getElementById('charModal').classList.add('active');

    if (allChars.length === 0) {
        // Load once
        fetch('beap_api.php?action=get_characters')
            .then(r => r.json()).then(res => {
                allChars = res.data || [];
                renderCharList('');
            });
    } else {
        renderCharList('');
    }
}

function closeCharModal() {
    document.getElementById('charModal').classList.remove('active');
}

function filterChars(q) {
    renderCharList(q.toLowerCase().trim());
}

function renderCharList(q) {
    const body = document.getElementById('charModalBody');
    const filtered = q
        ? allChars.filter(c => c.name.toLowerCase().includes(q) || c.desc.toLowerCase().includes(q))
        : allChars;

    if (!filtered.length) {
        body.innerHTML = '<div style="text-align:center;padding:20px;color:var(--bp-dim);font-size:.82rem;">No characters found.</div>';
        return;
    }
    body.innerHTML = filtered.map(c => `
        <label class="char-item">
            <input type="checkbox" value="${c.id}" ${selectedCharIds.has(c.id) ? 'checked' : ''}
                   onchange="toggleChar(${c.id}, this.checked)">
            <div style="min-width:0;">
                <div class="char-item-name">${escHtml(c.name)}</div>
                <div class="char-item-desc">${escHtml(c.desc)}</div>
            </div>
        </label>
    `).join('');
}

function toggleChar(id, checked) {
    if (checked) selectedCharIds.add(id);
    else selectedCharIds.delete(id);
    document.getElementById('charCountLabel').textContent = selectedCharIds.size + ' selected';
}

function runContinuity() {
    if (!selectedCharIds.size) return Toast.show('Select at least one character', 'warn');
    const btn = document.getElementById('runContinuityBtn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="bp-spinner"></span>Rewriting…';
    btn.disabled = true;

    const genId = document.getElementById('continuityGenSelect').value;
    const fd = new URLSearchParams();
    fd.append('action', 'continuity_beat');
    fd.append('beat_id', charModalBeatId);
    fd.append('generator_config_id', genId);
    selectedCharIds.forEach(id => fd.append('character_ids[]', id));

    fetch('beap_api.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            btn.innerHTML = orig;
            btn.disabled = false;
            if (!res.success) { Toast.show(res.message || 'Continuity failed', 'error'); return; }

            // Update the beat text display + textarea
            const card = document.getElementById('beat-card-' + charModalBeatId);
            if (card) {
                const disp = card.querySelector('.beat-text-display');
                if (disp) disp.textContent = res.new_beat_text;
                const ta = document.getElementById('bt-' + charModalBeatId);
                if (ta) ta.value = res.new_beat_text;
                // Flash border green briefly
                card.style.transition = 'border-color .2s';
                card.style.borderColor = 'var(--bp-green)';
                setTimeout(() => { card.style.borderColor = ''; }, 1200);

                // Show character tags
                const tagsWrap = document.getElementById('char-tags-' + charModalBeatId);
                if (tagsWrap) {
                    const names = Array.from(selectedCharIds).map(id => {
                        const c = allChars.find(x => x.id === id);
                        return c ? c.name : '';
                    }).filter(Boolean);
                    tagsWrap.innerHTML = names.map(n => `<span class="beat-char-tag">${escHtml(n)}</span>`).join('');
                }
            }
            closeCharModal();
            Toast.show('Beat rewritten with character continuity.', 'success');
        }).catch(() => {
            btn.innerHTML = orig;
            btn.disabled = false;
            Toast.show('Network error', 'error');
        });
}
</script>
    <?php

// ─────────────────────────────────────────────────────────────────────────────
// VIEW: SKETCH PICKER (default entry — pick a sketch or sequence → sketch)
// ─────────────────────────────────────────────────────────────────────────────
} else {
    $pageTitle = 'BEAP — Beat & Artboard Pipeline';
    ?>

<div class="bp-nav" style="padding-left:70px;">
    <span class="bp-nav-title">🎬 BEAP</span>
    <a href="beap.php?view=session_list" class="bp-btn bp-btn-sm"><i class="bi bi-clock-history"></i> Past Sessions</a>
</div>

<div class="bp-workspace">

    <div style="text-align:center;margin-bottom:28px;">
        <div style="font-family:'Space Mono',monospace;font-size:.7rem;color:var(--bp-dim);text-transform:uppercase;letter-spacing:2px;">
            Scene → Beats → Panels → Sequence
        </div>
    </div>

    <!-- ── Tab switcher: All Sketches / By Sequence ── -->
    <div class="bp-tabs">
        <div class="bp-tab active" id="tab-all-sketches" onclick="switchPickerTab('all')">All Sketches</div>
        <div class="bp-tab" id="tab-by-seq" onclick="switchPickerTab('seq')">By Sequence</div>
    </div>

    <!-- ── ALL SKETCHES tab ── -->
    <div class="bp-tab-pane active" id="pane-all">
        <div class="bp-filter-bar">
            <div class="bp-dd-wrap">
                <input type="text" id="sketchSearch" class="bp-input" placeholder="Search sketches by ID, name or description…"
                       oninput="debounceSketchSearch(this.value)" autocomplete="off">
                <div id="sketchDrop" class="bp-dropdown"></div>
            </div>
            <div class="bp-pager">
                <button class="bp-btn bp-btn-sm" onclick="changeSketchPage(-1)">«</button>
                <input type="number" id="sketchPageInput" class="bp-pager-input" value="1" min="1"
                       onchange="goSketchPage(this.value)">
                <span id="sketchTotalPages" style="font-size:.72rem;color:var(--bp-dim);">of 1</span>
                <button class="bp-btn bp-btn-sm" onclick="changeSketchPage(1)">»</button>
            </div>
        </div>
        <div id="sketchListContainer">
            <div style="text-align:center;padding:20px;color:var(--bp-dim);font-size:.85rem;">Loading…</div>
        </div>
    </div>

    <!-- ── BY SEQUENCE tab ── -->
    <div class="bp-tab-pane" id="pane-seq">
        <div id="seqListView">
            <div class="bp-filter-bar">
                <div class="bp-dd-wrap">
                    <input type="text" id="seqSearch" class="bp-input" placeholder="Search sequences…"
                           oninput="debounceSeqSearch(this.value)" autocomplete="off">
                    <div id="seqDrop" class="bp-dropdown"></div>
                </div>
                <select id="seqCatFilter" class="bp-input" style="flex:1;min-width:130px;max-width:190px;padding:8px 10px;" onchange="seqPage=1;loadSeqList()">
                    <option value="">All Categories</option>
                </select>
                <div class="bp-pager">
                    <button class="bp-btn bp-btn-sm" onclick="changeSeqPage(-1)">«</button>
                    <input type="number" id="seqPageInput" class="bp-pager-input" value="1" min="1"
                           onchange="goSeqPage(this.value)">
                    <span id="seqTotalPages" style="font-size:.72rem;color:var(--bp-dim);">of 1</span>
                    <button class="bp-btn bp-btn-sm" onclick="changeSeqPage(1)">»</button>
                </div>
            </div>
            <div id="seqListContainer">
                <div style="text-align:center;padding:20px;color:var(--bp-dim);font-size:.85rem;">Loading…</div>
            </div>
        </div>
        <div id="seqSketchView" style="display:none;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                <button class="bp-btn bp-btn-sm" onclick="backToSeqList()">← Sequences</button>
                <span id="seqSketchViewTitle" style="font-family:'Space Mono',monospace;font-size:.72rem;color:var(--bp-teal);text-transform:uppercase;letter-spacing:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></span>
            </div>
            <div id="seqSketchList"></div>
        </div>
    </div>
</div>

<!-- New Session Modal (depth picker + confirm) -->
<div class="bp-modal-backdrop" id="newSessionModal" onmousedown="if(event.target===this)closeNewSessionModal()">
    <div class="bp-modal">
        <div class="bp-modal-header">
            <div class="bp-modal-title" style="color:var(--bp-purple);">🎬 New BEAP Session</div>
            <button class="bp-modal-close" onclick="closeNewSessionModal()">✕</button>
        </div>
        <input type="hidden" id="nsSketchId">
        <div style="display:flex;flex-direction:column;gap:14px;">
            <div class="bp-sketch-preview" id="nsPreviewCard">
                <div class="bp-preview-thumb" id="nsThumbWrap">
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--bp-dim);font-size:.7rem;">…</div>
                </div>
                <div class="bp-preview-info">
                    <div class="bp-preview-title" id="nsSketchName">—</div>
                    <div class="bp-preview-desc" id="nsSketchDesc">—</div>
                </div>
            </div>
            <div>
                <div class="beat-field-label" style="margin-bottom:6px;">Pacing Depth</div>
                <div class="depth-toggle">
                    <button class="depth-btn" id="nsDepthShort"  onclick="nsSetDepth('short')">Short</button>
                    <button class="depth-btn active" id="nsDepthNormal" onclick="nsSetDepth('normal')">Normal</button>
                    <button class="depth-btn" id="nsDepthEpic"  onclick="nsSetDepth('epic')">Epic</button>
                </div>
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
            <button class="bp-btn" onclick="closeNewSessionModal()">Cancel</button>
            <button class="bp-btn bp-btn-purple" onclick="submitNewSession()">Start Session</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>
<script>
// ── State ─────────────────────────────────────────────────────────────────
let sketchPage = 1, sketchTotalPgs = 1;
let seqPage    = 1, seqTotalPgs    = 1;
let sketchSearchTimer, seqSearchTimer;
let nsDepth = 'normal';

// ── Tab switching ──────────────────────────────────────────────────────────
function switchPickerTab(tab) {
    document.querySelectorAll('.bp-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.bp-tab-pane').forEach(p => p.classList.remove('active'));
    if (tab === 'all') {
        document.getElementById('tab-all-sketches').classList.add('active');
        document.getElementById('pane-all').classList.add('active');
        loadSketchList();
    } else {
        document.getElementById('tab-by-seq').classList.add('active');
        document.getElementById('pane-seq').classList.add('active');
        // Always start at the sequence list, not a previous sketch drill-down
        document.getElementById('seqSketchView').style.display = 'none';
        document.getElementById('seqListView').style.display = '';
        // Load categories once (select will have only the default option until then)
        if (document.getElementById('seqCatFilter').options.length === 1) {
            loadSeqCategories();
        }
        loadSeqList();
    }
}

// ── Sketch list ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadSketchList();
});

function loadSketchList() {
    const search = document.getElementById('sketchSearch').value.trim();
    const cont   = document.getElementById('sketchListContainer');
    cont.innerHTML = '<div style="text-align:center;padding:20px;color:var(--bp-dim);font-size:.85rem;">Loading…</div>';

    fetch(`beap_api.php?action=get_sketches_list&page=${sketchPage}&search=${encodeURIComponent(search)}`)
        .then(r => r.json()).then(res => {
            if (!res.success) { cont.innerHTML = '<div style="padding:16px;color:#f44;">Error</div>'; return; }
            sketchTotalPgs = res.meta.total_pages;
            document.getElementById('sketchTotalPages').textContent = 'of ' + sketchTotalPgs;
            document.getElementById('sketchPageInput').value = res.meta.page;
            sketchPage = res.meta.page;

            if (!res.data.length) {
                cont.innerHTML = '<div class="bp-empty"><div class="bp-empty-icon">🔍</div><div>No sketches found.</div></div>';
                return;
            }
            cont.innerHTML = res.data.map(s => `
                <div class="bp-list-card">
                    <div class="bp-list-card-body" onclick="openNewSessionModal(${s.id})" style="cursor:pointer;">
                        <div class="bp-list-card-title">#${s.id} — ${escHtml(s.name)}</div>
                        <div class="bp-list-card-meta">${escHtml(s.desc)}</div>
                    </div>
                    <div class="bp-list-card-actions">
                        <button class="bp-list-card-action" onclick="openNewSessionModal(${s.id})" title="Start BEAP Session">
                            <i class="bi bi-play-fill" style="color:var(--bp-purple);"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        }).catch(() => { cont.innerHTML = '<div style="padding:16px;color:#f44;">Network error</div>'; });
}

function debounceSketchSearch(v) {
    clearTimeout(sketchSearchTimer);
    const drop = document.getElementById('sketchDrop');
    sketchSearchTimer = setTimeout(() => {
        sketchPage = 1;
        loadSketchList();
        if (!v.trim()) { drop.classList.remove('open'); return; }
        drop.innerHTML = '<div class="bp-dd-item" style="color:var(--bp-dim);">Searching…</div>';
        drop.classList.add('open');
        fetch(`beap_api.php?action=search_sketches&q=${encodeURIComponent(v)}`)
            .then(r => r.json()).then(res => {
                if (!res.data || !res.data.length) {
                    drop.innerHTML = '<div class="bp-dd-item" style="color:var(--bp-dim);">No quick proposals</div>';
                    return;
                }
                drop.innerHTML = res.data.map(item => `
                    <div class="bp-dd-item" onclick="openNewSessionModal(${item.id})">
                        <span>${escHtml(item.label)}</span>
                        <span class="dd-sub">${escHtml(item.desc)}</span>
                    </div>
                `).join('');
            });
    }, 320);
}

document.addEventListener('click', e => {
    const drop = document.getElementById('sketchDrop');
    if (drop && !e.target.closest('#sketchSearch') && !e.target.closest('#sketchDrop'))
        drop.classList.remove('open');
    const sdrop = document.getElementById('seqDrop');
    if (sdrop && !e.target.closest('#seqSearch') && !e.target.closest('#seqDrop'))
        sdrop.classList.remove('open');
});

function goSketchPage(p) {
    p = parseInt(p); if (isNaN(p) || p < 1) p = 1;
    if (p > sketchTotalPgs) p = sketchTotalPgs;
    sketchPage = p; document.getElementById('sketchPageInput').value = p; loadSketchList();
}
function changeSketchPage(d) { goSketchPage(sketchPage + d); }

// ── Sequence list ──────────────────────────────────────────────────────────
function loadSeqCategories() {
    fetch('beap_api.php?action=get_sequence_categories')
        .then(r => r.json()).then(res => {
            if (!res.success || !res.data || !res.data.length) return;
            const sel = document.getElementById('seqCatFilter');
            res.data.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                sel.appendChild(opt);
            });
        }).catch(() => {});
}

function loadSeqList() {
    const search = document.getElementById('seqSearch').value.trim();
    const catId  = document.getElementById('seqCatFilter').value;
    const cont   = document.getElementById('seqListContainer');
    cont.innerHTML = '<div style="text-align:center;padding:20px;color:var(--bp-dim);font-size:.85rem;">Loading…</div>';

    fetch(`beap_api.php?action=get_sequences_list&page=${seqPage}&search=${encodeURIComponent(search)}&category_id=${catId}`)
        .then(r => r.json()).then(res => {
            if (!res.success) { cont.innerHTML = '<div style="padding:16px;color:#f44;">Error</div>'; return; }
            seqTotalPgs = res.meta.total_pages;
            document.getElementById('seqTotalPages').textContent = 'of ' + seqTotalPgs;
            document.getElementById('seqPageInput').value = res.meta.page;
            seqPage = res.meta.page;

            if (!res.data.length) {
                cont.innerHTML = '<div class="bp-empty"><div class="bp-empty-icon">🎬</div><div>No sequences found.</div></div>';
                return;
            }
            cont.innerHTML = res.data.map(s => `
                <div class="bp-list-card">
                    <div class="bp-list-card-body" onclick="loadSeqSketches(${s.id}, '${escHtml(s.name)}')" style="cursor:pointer;">
                        <div class="bp-list-card-title">#${s.id} — ${escHtml(s.name)}</div>
                        <div class="bp-list-card-meta">${s.skt_count} sketches · ${s.created_at}</div>
                    </div>
                    <div class="bp-list-card-actions">
                        <button class="bp-list-card-action" onclick="loadSeqSketches(${s.id}, '${escHtml(s.name)}')" title="Browse sketches">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        }).catch(() => { cont.innerHTML = '<div style="padding:16px;color:#f44;">Network error</div>'; });
}

function debounceSeqSearch(v) {
    clearTimeout(seqSearchTimer);
    const drop = document.getElementById('seqDrop');
    seqSearchTimer = setTimeout(() => {
        seqPage = 1;
        loadSeqList();
        if (!v.trim()) { drop.classList.remove('open'); return; }
        drop.innerHTML = '<div class="bp-dd-item" style="color:var(--bp-dim);">Searching…</div>';
        drop.classList.add('open');
        fetch(`beap_api.php?action=get_sequences_list&page=1&search=${encodeURIComponent(v)}`)
            .then(r => r.json()).then(res => {
                if (!res.data || !res.data.length) {
                    drop.innerHTML = '<div class="bp-dd-item" style="color:var(--bp-dim);">No proposals</div>'; return;
                }
                drop.innerHTML = res.data.map(s => `
                    <div class="bp-dd-item" onclick="loadSeqSketches(${s.id}, '${escHtml(s.name)}')">
                        <span>#${s.id} ${escHtml(s.name)}</span>
                        <span class="dd-sub">${s.skt_count} skts</span>
                    </div>
                `).join('');
            });
    }, 320);
}

function goSeqPage(p) {
    p = parseInt(p); if (isNaN(p) || p < 1) p = 1;
    if (p > seqTotalPgs) p = seqTotalPgs;
    seqPage = p; document.getElementById('seqPageInput').value = p; loadSeqList();
}
function changeSeqPage(d) { goSeqPage(seqPage + d); }

function loadSeqSketches(seqId, seqName) {
    // Hide the sequence list, show the sketch view
    document.getElementById('seqListView').style.display = 'none';
    const sketchView = document.getElementById('seqSketchView');
    sketchView.style.display = '';
    document.getElementById('seqSketchViewTitle').textContent = seqName;

    const list = document.getElementById('seqSketchList');
    list.innerHTML = '<div style="text-align:center;padding:16px;color:var(--bp-dim);font-size:.82rem;">Loading…</div>';

    fetch(`beap_api.php?action=get_sequence_sketches&seq_id=${seqId}`)
        .then(r => r.json()).then(res => {
            if (!res.success || !res.data.length) {
                list.innerHTML = '<div class="bp-empty"><div>No sketches in this sequence.</div></div>';
                return;
            }
            list.innerHTML = `<div class="beap-seq-pswp-gallery">${res.data.map(s => {
                const hasThumb = s.thumb && s.thumb !== '';
                const thumbHtml = hasThumb
                    ? `<a href="${s.thumb}" class="beap-seq-pswp-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                           <img src="${s.thumb}" loading="lazy" onload="this.parentElement.dataset.pswpWidth=this.naturalWidth;this.parentElement.dataset.pswpHeight=this.naturalHeight;">
                       </a>`
                    : `<div class="seq-skt-no-img"><i class="bi bi-image" style="font-size:1.2rem;"></i></div>`;
                return `<div class="bp-list-card" style="margin-bottom:6px;">
                    <div class="seq-skt-thumb">${thumbHtml}</div>
                    <div class="bp-list-card-body" onclick="openNewSessionModal(${s.id})" style="cursor:pointer;">
                        <div class="bp-list-card-title">#${s.id} — ${escHtml(s.name)}</div>
                        <div class="bp-list-card-meta">${escHtml(s.desc)}</div>
                    </div>
                    <div class="bp-list-card-actions">
                        <button class="bp-list-card-action" onclick="openNewSessionModal(${s.id})" title="Start BEAP Session">
                            <i class="bi bi-play-fill" style="color:var(--bp-purple);"></i>
                        </button>
                    </div>
                </div>`;
            }).join('')}</div>`;
            // Init PhotoSwipe for the seq sketch thumbs
            if (window._beapSeqLightbox) { window._beapSeqLightbox.destroy(); }
            import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js').then(m => {
                window._beapSeqLightbox = new m.default({
                    gallery: '.beap-seq-pswp-gallery',
                    children: 'a.beap-seq-pswp-item',
                    pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
                    initialZoomLevel: 'fit',
                    secondaryZoomLevel: 1,
                });
                window._beapSeqLightbox.init();
            });
        }).catch(() => { list.innerHTML = '<div style="padding:16px;color:#f44;">Network error</div>'; });
}

function backToSeqList() {
    document.getElementById('seqSketchView').style.display = 'none';
    document.getElementById('seqListView').style.display = '';
    document.getElementById('seqSketchList').innerHTML = '';
    if (window._beapSeqLightbox) { window._beapSeqLightbox.destroy(); window._beapSeqLightbox = null; }
}

// ── New Session Modal ──────────────────────────────────────────────────────
function openNewSessionModal(sketchId) {
    document.getElementById('nsSketchId').value = sketchId;
    document.getElementById('nsSketchName').textContent = '…';
    document.getElementById('nsSketchDesc').textContent = '…';
    document.getElementById('nsThumbWrap').innerHTML =
        '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--bp-dim);font-size:.7rem;">…</div>';
    nsDepth = 'normal';
    ['Short','Normal','Epic'].forEach(d => {
        document.getElementById('nsDepth' + d).classList.toggle('active', d === 'Normal');
    });
    document.getElementById('newSessionModal').classList.add('active');

    // Load sketch detail
    fetch(`beap_api.php?action=get_sketch&sketch_id=${sketchId}`)
        .then(r => r.json()).then(res => {
            if (!res.success) return;
            const sk = res.sketch;
            document.getElementById('nsSketchName').textContent = `#${sk.id} — ${sk.name}`;
            document.getElementById('nsSketchDesc').textContent =
                (sk.description || 'No description.').substring(0, 200);
            if (sk.frames.length) {
                document.getElementById('nsThumbWrap').innerHTML =
                    `<img src="${sk.frames[0].filename}" loading="lazy" style="width:100%;height:100%;object-fit:cover;">`;
            }
        });
}

function closeNewSessionModal() {
    document.getElementById('newSessionModal').classList.remove('active');
}

function nsSetDepth(d) {
    nsDepth = d;
    ['Short','Normal','Epic'].forEach(k => {
        document.getElementById('nsDepth' + k).classList.toggle('active', k.toLowerCase() === d);
    });
}

function submitNewSession() {
    const sketchId = document.getElementById('nsSketchId').value;
    if (!sketchId) return Toast.show('No sketch selected', 'warn');

    const fd = new URLSearchParams();
    fd.append('action', 'create_session');
    fd.append('sketch_id', sketchId);
    fd.append('depth', nsDepth);

    fetch('beap_api.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.success) {
                window.location.href = `beap.php?view=beats&session=${res.session_id}`;
            } else {
                Toast.show(res.message || 'Create failed', 'error');
            }
        }).catch(() => Toast.show('Network error', 'error'));
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
</script>
    <?php
}

// ─────────────────────────────────────────────────────────────────────────────
// VIEW: SESSION LIST
// ─────────────────────────────────────────────────────────────────────────────
if ($view === 'session_list') {
    // Render a minimal sessions list page
    ob_clean(); ob_start();
    ?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
/* reuse same vars but keep file self-contained */
:root,[data-theme="dark"]{--bp-bg:#080b10;--bp-surface:#0e1319;--bp-card:#111820;--bp-border:#1c2535;--bp-text:#c8d4e8;--bp-dim:#5a6a80;--bp-amber:#f5a623;--bp-teal:#3ab5c8;--bp-purple:#9b72e0;--bp-green:#3ab87f;}
[data-theme="light"]{--bp-bg:#f4f6fa;--bp-surface:#fff;--bp-card:#fff;--bp-border:#d0d8e8;--bp-text:#1a2233;--bp-dim:#7a8aaa;--bp-amber:#c8880a;--bp-teal:#1a8090;--bp-purple:#7040c0;--bp-green:#1a8060;}
body{background:var(--bp-bg);color:var(--bp-text);font-family:'Syne',system-ui,sans-serif;margin:0;}
.bp-nav{display:flex;align-items:center;gap:10px;padding:10px 16px;background:rgba(0,0,0,.6);border-bottom:1px solid var(--bp-border);position:sticky;top:0;z-index:100;backdrop-filter:blur(6px);flex-wrap:wrap;}
[data-theme="light"] .bp-nav{background:rgba(244,246,250,.92);}
.bp-nav-title{font-family:'Space Mono',monospace;font-size:.85rem;font-weight:bold;color:var(--bp-purple);letter-spacing:1px;text-transform:uppercase;margin-right:auto;}
.bp-btn{padding:7px 14px;border-radius:4px;border:1px solid var(--bp-border);font-family:'Space Mono',monospace;font-size:.75rem;cursor:pointer;transition:all .15s;background:var(--bp-card);color:var(--bp-dim);text-decoration:none;display:inline-block;}
.bp-btn:hover{color:var(--bp-teal);border-color:var(--bp-teal);}
.bp-btn-sm{padding:4px 8px;font-size:.65rem;}
.bp-btn-purple{border-color:var(--bp-purple);background:var(--bp-purple);color:#fff;font-weight:bold;}
.bp-workspace{max-width:860px;margin:0 auto;padding:24px 15px 100px;}
.bp-filter-bar{display:flex;gap:10px;align-items:center;background:var(--bp-surface);padding:10px 12px;border:1px solid var(--bp-border);border-radius:6px;margin-bottom:16px;flex-wrap:wrap;}
.bp-pager{display:flex;align-items:center;gap:6px;margin-left:auto;}
.bp-pager-input{width:46px;text-align:center;padding:4px;background:var(--bp-card);color:var(--bp-text);border:1px solid var(--bp-border);border-radius:4px;font-family:'Space Mono',monospace;font-size:.75rem;}
.bp-input{width:100%;box-sizing:border-box;background:var(--bp-card);color:var(--bp-text);border:1px solid var(--bp-border);border-radius:4px;padding:8px 12px;font-family:'Syne',sans-serif;font-size:.85rem;outline:none;}
.bp-input:focus{border-color:var(--bp-teal);}
.bp-list-card{display:flex;align-items:center;background:var(--bp-card);border:1px solid var(--bp-border);border-radius:6px;overflow:hidden;transition:border-color .2s;margin-bottom:8px;}
.bp-list-card:hover{border-color:var(--bp-teal);}
.bp-list-card-body{flex:1;padding:12px 14px;min-width:0;}
.bp-list-card-title{font-family:'Space Mono',monospace;font-size:.85rem;font-weight:bold;}
.bp-list-card-meta{font-size:.72rem;color:var(--bp-dim);margin-top:3px;}
.bp-list-card-actions{display:flex;border-left:1px solid var(--bp-border);align-self:stretch;}
.bp-list-card-action{background:transparent;border:none;border-right:1px solid var(--bp-border);padding:0 12px;cursor:pointer;color:var(--bp-dim);font-size:.9rem;transition:all .2s;}
.bp-list-card-action:last-child{border-right:none;}
.bp-list-card-action:hover{color:var(--bp-teal);background:rgba(58,181,200,.07);}
.bp-list-card-action.danger:hover{color:#ff4444;background:rgba(255,68,68,.07);}
.bp-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-family:'Space Mono',monospace;font-size:.6rem;text-transform:uppercase;letter-spacing:1px;font-weight:bold;}
.bp-badge-pending{background:rgba(90,106,128,.2);color:var(--bp-dim);}
.bp-badge-beats{background:rgba(155,114,224,.2);color:var(--bp-purple);}
.bp-badge-panels{background:rgba(58,184,127,.2);color:var(--bp-green);}
.bp-badge-exported{background:rgba(245,166,35,.2);color:var(--bp-amber);}
</style>
<div class="bp-nav" style="padding-left:70px;">
    <a href="beap.php" class="bp-btn">← New Session</a>
    <span class="bp-nav-title">🎬 BEAP — Past Sessions</span>
</div>
<div class="bp-workspace">
    <div class="bp-filter-bar">
        <input type="text" id="sessSearch" class="bp-input" style="flex:1;min-width:200px;" placeholder="Search sessions by name or ID…" oninput="debounceSessSearch(this.value)">
        <div class="bp-pager">
            <button class="bp-btn bp-btn-sm" onclick="changeSessPage(-1)">«</button>
            <input type="number" id="sessPageInput" class="bp-pager-input" value="1" min="1" onchange="goSessPage(this.value)">
            <span id="sessTotalPages" style="font-size:.72rem;color:var(--bp-dim);">of 1</span>
            <button class="bp-btn bp-btn-sm" onclick="changeSessPage(1)">»</button>
        </div>
    </div>
    <div id="sessListContainer">
        <div style="text-align:center;padding:20px;color:var(--bp-dim);">Loading…</div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>
<script>
let sessPage = 1, sessTotalPgs = 1, sessSearchTimer;
document.addEventListener('DOMContentLoaded', loadSessList);

function loadSessList() {
    const search = document.getElementById('sessSearch').value.trim();
    const cont   = document.getElementById('sessListContainer');
    cont.innerHTML = '<div style="text-align:center;padding:20px;color:var(--bp-dim);font-size:.85rem;">Loading…</div>';
    fetch(`beap_api.php?action=get_sessions_list&page=${sessPage}&search=${encodeURIComponent(search)}`)
        .then(r => r.json()).then(res => {
            if (!res.success) { cont.innerHTML = '<div style="padding:16px;color:#f44;">Error</div>'; return; }
            sessTotalPgs = res.meta.total_pages;
            document.getElementById('sessTotalPages').textContent = 'of ' + sessTotalPgs;
            document.getElementById('sessPageInput').value = res.meta.page;
            if (!res.data.length) { cont.innerHTML = '<div style="text-align:center;padding:30px;color:var(--bp-dim);">No sessions yet.</div>'; return; }
            const badgeMap = {
                beats_pending:'bp-badge-pending',beats_done:'bp-badge-beats',
                panels_done:'bp-badge-panels',exported:'bp-badge-exported'
            };
            cont.innerHTML = res.data.map(s => `
                <div class="bp-list-card">
                    <div class="bp-list-card-body" onclick="location.href='beap.php?view=beats&session=${s.id}'" style="cursor:pointer;">
                        <div class="bp-list-card-title">
                            #${s.id} — ${escHtml(s.sketch_name)}
                            <span class="bp-badge ${badgeMap[s.status]||'bp-badge-pending'}" style="margin-left:8px;">${escHtml(s.status)}</span>
                        </div>
                        <div class="bp-list-card-meta">
                            Sketch #${s.sketch_id} · ${s.beat_count} beats · Depth: ${s.depth} · ${s.created_at}
                            ${s.narseq_id ? ' · <a href="narseq.php?id='+s.narseq_id+'" target="_blank" style="color:var(--bp-amber);">Seq #'+s.narseq_id+'</a>' : ''}
                        </div>
                    </div>
                    <div class="bp-list-card-actions">
                        <button class="bp-list-card-action" onclick="location.href='beap.php?view=beats&session=${s.id}'" title="Open"><i class="bi bi-pencil"></i></button>
                        <button class="bp-list-card-action danger" onclick="deleteSession(${s.id})" title="Delete"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            `).join('');
        }).catch(() => { cont.innerHTML = '<div style="padding:16px;color:#f44;">Network error</div>'; });
}

function debounceSessSearch(v) {
    clearTimeout(sessSearchTimer);
    sessSearchTimer = setTimeout(() => { sessPage = 1; loadSessList(); }, 320);
}
function goSessPage(p) {
    p = parseInt(p); if (isNaN(p) || p < 1) p = 1;
    if (p > sessTotalPgs) p = sessTotalPgs;
    sessPage = p; document.getElementById('sessPageInput').value = p; loadSessList();
}
function changeSessPage(d) { goSessPage(sessPage + d); }

function deleteSession(id) {
    if (!confirm('Delete this BEAP session and all its beats?')) return;
    const fd = new URLSearchParams();
    fd.append('action', 'delete_session');
    fd.append('session_id', id);
    fetch('beap_api.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
        if (res.success) { Toast.show('Session deleted.', 'success'); loadSessList(); }
        else Toast.show(res.message || 'Delete failed', 'error');
    });
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
</script>
    <?php
    $content = ob_get_clean();
    $spw->renderLayout($content, 'BEAP — Past Sessions', $spw->getProjectPath() . '/templates/curation.php');
    exit;
}

// ── Finalize output ──────────────────────────────────────────────────────────
echo $eruda ?? '';
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
