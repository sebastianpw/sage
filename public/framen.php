<?php
// public/framen.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=0.7, maximum-scale=1, user-scalable=0">
<title>Framen</title>

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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<!-- PhotoSwipe -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe-lightbox.umd.min.js"></script>

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
html, body { height: 100%; margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; overflow: hidden; }
.layout { display: flex; flex-direction: column; height: 100dvh; }

.header { flex-shrink: 0; padding: 0 12px; height: 50px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.title { font-weight: 700; color: var(--purple); font-size: 1.1rem; display: flex; align-items: center; gap: 8px; }

/* Pagination row */
.cz-pagination-row { display: flex; align-items: center; justify-content: space-between; padding: 6px 12px; border-bottom: 1px solid var(--border); background: var(--card); flex-shrink: 0; }
.cz-page-info { font-size: 0.7rem; color: var(--text-muted); }
.cz-pagination { display: flex; align-items: center; gap: 4px; }
.pg-btn { width: 28px; height: 28px; background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 3px; cursor: pointer; display:flex; align-items:center; justify-content:center; }
.pg-btn:hover { border-color: var(--purple); color: var(--purple); }
.pg-input { width: 40px; text-align: center; background: var(--bg); border: 1px solid var(--border); color: var(--purple); border-radius: 3px; font-size: 0.75rem; font-weight: bold; }
.pg-total { font-size: 0.7rem; color: var(--text-muted); padding: 0 4px; }

/* Panels */
.panel-list { flex-shrink: 0; overflow-y: auto; padding: 10px; background: var(--bg); border-bottom: 1px solid var(--border); max-height: 48vh; }
.panel-grid { flex: 1; overflow-y: auto; padding: 10px; background: var(--bg); position: relative; min-height: 0; }

/* Card */
.card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 8px; padding: 8px 10px; cursor: pointer; transition: border-color 0.2s, box-shadow 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
.card:hover { border-color: rgba(139, 92, 246, 0.5); }
.card.active { border-color: var(--teal); box-shadow: 0 0 0 1px var(--teal); }

.card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; gap: 8px; flex-wrap: wrap; }
.card-head-left { display: flex; align-items: center; gap: 8px; }
.card-head-right { display: flex; align-items: center; gap: 6px; }

.card-id { font-size: 0.75rem; font-weight: bold; color: var(--text); font-family: monospace; }
.card-entity { font-size: 0.7rem; color: var(--teal); background: rgba(20,184,166,0.1); padding: 2px 6px; border-radius: 4px; font-weight: bold; }

.card-body { display: flex; gap: 10px; align-items: flex-start; }
.card-thumb { width: 52px; height: 52px; border-radius: 4px; object-fit: cover; border: 1px solid var(--border); background: #111; flex-shrink: 0; }
.card-desc { flex: 1; min-width: 0; }
.cz-desc-textarea { width: 100%; resize: none; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 5px; color: var(--text); padding: 5px 7px; font-size: 0.72rem; min-height: 38px; font-family: inherit; transition: 0.2s; line-height: 1.4; }
.cz-desc-textarea:focus { border-color: var(--amber); outline: none; min-height: 70px; background: var(--bg); }

.btn-action { width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border); background: transparent; color: var(--text-muted); border-radius: 4px; cursor: pointer; font-size: 0.75rem; flex-shrink: 0; }
.btn-action:hover { border-color: var(--purple); color: var(--purple); }
.btn-action.delete:hover { border-color: var(--red); color: var(--red); }

.regen-wrap { display: flex; align-items: center; gap: 4px; font-size: 0.65rem; color: var(--text-muted); padding: 2px 5px; border-radius: 4px; border: 1px solid var(--border); cursor: pointer; user-select: none; flex-shrink: 0; }
.regen-wrap:hover { border-color: var(--amber); }
.regen-wrap input { accent-color: var(--amber); transform: scale(1.05); margin: 0; cursor: pointer; }

/* Mid toolbar */
.eh-mid-panel {
    flex-shrink: 0; background: var(--card); border-bottom: 1px solid var(--border);
    padding: 6px 12px; display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.btn-cb-manage {
    padding: 5px 10px; border-radius: 4px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.7rem; font-weight: 700; cursor: pointer; display: inline-flex;
    align-items: center; gap: 5px; transition: all 0.15s; text-transform: uppercase;
}
.btn-cb-manage:hover:not(:disabled) { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }
.btn-cb-manage:disabled { opacity: 0.35; cursor: not-allowed; border-color: var(--border); }
.mid-info { font-size: 0.7rem; color: var(--text-muted); }

/* Grid styling (cloned from enhanimaticism) */
.frames-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; padding-bottom: 20px; }
@media (min-width: 600px) {
    .frames-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
}
.f-card { aspect-ratio: 1; background: #111; border: 2px solid var(--border); border-radius: 4px; position: relative; overflow: hidden; }
.f-card.selected { border-color: var(--text); box-shadow: 0 0 0 1px var(--text); }
.f-link { display: block; width: 100%; height: calc(100% - 24px); overflow: hidden; cursor: zoom-in; }
.f-link img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.2s; }
.f-link:hover img { transform: scale(1.03); }

.f-label {
    position: absolute; bottom: 0; left: 0; right: 0; height: 24px;
    background: rgba(20,20,25,0.95); padding: 0 6px; font-size: 0.65rem; color: #aaa;
    border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;
    user-select: none; z-index: 2;
}
.f-card.selected .f-label { background: rgba(255,255,255,0.1); color: #fff; border-top-color: #fff; }
.f-select-trigger {
    width: 18px; height: 18px; border: 1px solid #555; border-radius: 3px;
    display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5);
    font-size: 0; cursor: pointer;
}
.f-card.selected .f-select-trigger { background: #fff; border-color: #fff; color: #000; font-size: 10px; font-weight: 900; }
.f-card.selected .f-select-trigger::after { content: '✓'; }

.f-card.source { border-color: var(--teal); }
.f-card.source::after { content: 'SOURCE'; position: absolute; top: 0; left: 0; background: var(--teal); color: #000; font-size: 0.55rem; font-weight: bold; padding: 2px 5px; border-bottom-right-radius: 4px; z-index: 5; }
.f-card.extra { border-color: var(--purple); }
.f-card.extra::after { content: 'EXTRA'; position: absolute; top: 0; left: 0; background: var(--purple); color: #fff; font-size: 0.55rem; font-weight: bold; padding: 2px 5px; border-bottom-right-radius: 4px; z-index: 5; }

.f-view-btn { position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; opacity: 0; transition: 0.2s; font-size: 12px; }
.f-card:hover .f-view-btn { opacity: 1; }
.f-view-btn:hover { background: #fff; color: #000; }

/* Footer */
.eh-footer {
    flex-shrink: 0; padding: 10px 16px; 
    padding-bottom: max(10px, env(safe-area-inset-bottom));
    background: var(--card); border-top: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    z-index: 10; position: relative;
}
.ft-summary { font-size: 0.75rem; color: var(--text-muted); }
.ft-actions { display: flex; gap: 6px; align-items: center; }
.btn-remove-extra {
    padding: 8px 16px; border-radius: 4px; border: none; font-size: 0.75rem;
    font-weight: 700; text-transform: uppercase; cursor: pointer; font-family: inherit;
    background: var(--red); color: #fff; transition: opacity 0.15s;
}
.btn-remove-extra:disabled { opacity: 0.4; cursor: not-allowed; background: var(--border) !important; color: #888 !important; }

.state-msg { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); font-size: 0.8rem; gap: 8px; }
.spinner { width: 20px; height: 20px; border: 2px solid var(--border); border-top-color: var(--text); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* View Frame Modal */
.view-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 100000; display: none; align-items: center; justify-content: center; }
.view-modal.active { display: flex; }
.view-modal-content { width: 95vw; height: 95vh; background: #000; position: relative; border: 1px solid var(--border); box-shadow: 0 0 30px rgba(0,0,0,0.5); }
.view-close { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: rgba(0,0,0,0.8); color: #fff; border: 1px solid #444; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; z-index: 200; transition: all 0.2s; }
.view-close:hover { background: #fff; color: #000; }
iframe.frame-viewer { width: 100%; height: 100%; border: none; }

/* ── ADD FRAMES MODAL (Directly cloned from enhanimaticism) ── */
.compose-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.85);
    z-index: 200000; display: none; align-items: flex-end; justify-content: center;
}
.compose-modal-backdrop.active { display: flex; }
.compose-modal {
    width: 100%; max-width: 520px;
    background: var(--card); border: 1px solid var(--border);
    border-bottom: none; border-radius: 14px 14px 0 0;
    padding: 0 0 max(16px, env(safe-area-inset-bottom));
    font-family: 'DM Mono', 'Fira Mono', monospace;
    max-height: 88vh; display: flex; flex-direction: column;
    box-shadow: 0 -8px 40px rgba(0,0,0,0.6);
    animation: slideUp 0.22s ease;
}
@keyframes slideUp { from { transform: translateY(60px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.cm-handle { text-align: center; padding: 10px 0 4px; cursor: pointer; }
.cm-handle-bar { display: inline-block; width: 40px; height: 4px; background: var(--border); border-radius: 2px; }
.cm-header {
    padding: 6px 16px 10px; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.cm-title { font-size: 0.8rem; font-weight: 700; color: var(--teal); text-transform: uppercase; letter-spacing: 1px; }
.cm-close-btn { background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 4px; width: 26px; height: 26px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.cm-close-btn:hover { color: var(--text); border-color: var(--text); }
.cm-body { overflow-y: auto; padding: 0 16px; flex: 1; }
.cm-footer { padding: 12px 16px 0; flex-shrink: 0; display: flex; gap: 8px; }
.cm-use-btn {
    flex: 1; padding: 13px; border-radius: 5px; border: none;
    background: var(--teal); color: #000; font-family: inherit;
    font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
    cursor: pointer; transition: filter 0.15s;
}
.cm-use-btn:hover { filter: brightness(1.12); }
.cm-clear-btn {
    padding: 13px 16px; border-radius: 5px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.75rem; cursor: pointer; transition: all 0.15s;
}
.cm-clear-btn:hover { border-color: var(--red); color: var(--red); }

.af-tabs { display: flex; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.af-tab {
    flex: 1; padding: 8px 10px; font-size: 0.65rem; font-weight: 700; text-align: center;
    text-transform: uppercase; letter-spacing: 0.5px; cursor: pointer;
    color: var(--text-muted); border-bottom: 2px solid transparent; transition: 0.15s;
}
.af-tab.active { color: var(--teal); border-bottom-color: var(--teal); }
.af-tab-content { display: none; flex: 1; flex-direction: column; overflow: hidden; }
.af-tab-content.active { display: flex; }

.af-browse-filters {
    flex-shrink: 0; padding: 8px 12px; display: flex; flex-direction: column; gap: 6px;
    border-bottom: 1px solid var(--border);
}
.af-browse-row { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.af-area-btn {
    padding: 3px 8px; border-radius: 20px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.6rem; cursor: pointer; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.15s;
}
.af-area-btn.active { border-color: var(--teal); color: #000; background: var(--teal); }

.af-select-wrap { flex: 1; min-width: 0; position: relative; }
.af-search-input {
    width: 100%; padding: 5px 24px 5px 8px; border-radius: 4px; border: 1px solid var(--border);
    background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.72rem; outline: none;
}
.af-search-input:focus { border-color: var(--teal); }
.af-search-clear {
    position: absolute; right: 5px; top: 50%; transform: translateY(-50%);
    background: transparent; border: none; color: var(--text-muted); cursor: pointer;
    font-size: 11px; padding: 2px; display: none;
}
.af-search-clear.visible { display: block; }
.af-dropdown {
    position: absolute; top: calc(100% + 2px); left: 0; right: 0; z-index: 99999;
    background: var(--card); border: 1px solid var(--border); border-radius: 4px;
    max-height: 160px; overflow-y: auto; display: none;
    box-shadow: 0 4px 16px rgba(0,0,0,0.6);
}
.af-dropdown.open { display: block; }
.af-option {
    padding: 7px 10px; font-size: 0.7rem; cursor: pointer; color: var(--text);
    border-bottom: 1px solid rgba(255,255,255,0.04); display: flex; justify-content: space-between;
}
.af-option:hover { background: var(--teal-dim); }
.af-option .opt-id { font-size: 0.6rem; color: var(--text-muted); }

.af-browse-grid-wrap { flex: 1; overflow-y: auto; padding: 8px; }
.af-browse-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; }
.af-frame-card {
    aspect-ratio: 1; border-radius: 3px; overflow: hidden; cursor: pointer;
    border: 2px solid transparent; position: relative; background: #111;
    transition: border-color 0.12s;
}
.af-frame-card:hover { border-color: var(--teal); }
.af-frame-card.selected { border-color: var(--text); }
.af-frame-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
.af-frame-card .af-fid {
    position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.7);
    font-size: 0.55rem; color: #aaa; padding: 2px 4px; text-align: center;
}
.af-frame-card.selected .af-fid { background: rgba(255,255,255,0.15); color: #fff; }
.af-browse-state { padding: 20px; text-align: center; font-size: 0.72rem; color: var(--text-muted); font-style: italic; }

.prompt-input {
    padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border);
    background: rgba(0,0,0,0.3); color: var(--text); font-family: inherit; font-size: 0.85rem;
}
.btn-compose {
    padding: 6px 10px; border-radius: 4px;
    border: 1px solid var(--teal); background: var(--teal-dim); color: var(--teal);
    font-family: inherit; font-size: 0.7rem; font-weight: 700; cursor: pointer;
    text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 4px;
}
.btn-remove-frame {
    width: 26px; height: 26px; border-radius: 4px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 13px;
    transition: all 0.12s; flex-shrink: 0;
}
.btn-remove-frame:hover { color: var(--red); border-color: var(--red); background: rgba(239, 68, 68, 0.12); }
</style>
</head>
<body>
<div class="layout">
    <!-- Header -->
    <div class="header">
        <div class="title"><i class="bi bi-stars"></i> Framen</div>
        <div style="font-size: 0.75rem; color: var(--text-muted);">Frame Enhancements</div>
    </div>

    <!-- Pagination -->
    <div class="cz-pagination-row">
        <div class="cz-page-info" id="pageInfo">Loading...</div>
        <div class="cz-pagination">
            <button class="pg-btn" onclick="changePage(-1)"><i class="bi bi-chevron-left"></i></button>
            <input type="number" class="pg-input" id="pgInput" value="1" onchange="jumpToPage()" onkeydown="if(event.key==='Enter') jumpToPage()">
            <span class="pg-total" id="pgTotal">/ 1</span>
            <button class="pg-btn" onclick="changePage(1)"><i class="bi bi-chevron-right"></i></button>
        </div>
    </div>

    <!-- 1. Upper Panel: Enhancements List (3 items per page) -->
    <div class="panel-list" id="enhList">
        <div class="state-msg"><div class="spinner"></div><div>Loading enhancements...</div></div>
    </div>

    <!-- 2. Mid Toolbar: +Frames -->
    <div class="eh-mid-panel">
        <button class="btn-cb-manage" id="btnAddFrames" onclick="openAddFramesModal()" disabled title="Add Additional Reference Frames">
            <i class="bi bi-images"></i> +Frames
        </button>
        <div class="mid-info" id="midInfo">Select an enhancement above</div>
    </div>

    <!-- 3. Lower Panel: Image Grid -->
    <div class="panel-grid">
        <div class="state-msg" id="gridState"><div>Select an enhancement above</div></div>
        <div class="frames-grid pswp-gallery" id="framesGrid" style="display:none;"></div>
    </div>

    <!-- 4. Footer -->
    <div class="eh-footer">
        <div class="ft-summary" id="footerSummary">0 selected</div>
        <div class="ft-actions">
            <button class="btn-remove-extra" id="btnRemoveExtra" disabled onclick="removeSelectedExtraFrames()">Remove</button>
        </div>
    </div>
</div>

<!-- Frame viewer Modal -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>

<!-- ADD FRAMES MODAL -->
<div class="compose-modal-backdrop" id="addFramesBackdrop" onmousedown="onAddFramesBackdropClick(event)">
    <div class="compose-modal" id="addFramesModal">
        <div class="cm-handle" onclick="closeAddFramesModal(false)"><div class="cm-handle-bar"></div></div>
        <div class="cm-header">
            <div class="cm-title"><i class="bi bi-images"></i> Additional Reference Frames</div>
            <button type="button" class="cm-close-btn" onclick="closeAddFramesModal(false)"><i class="bi bi-x"></i></button>
        </div>

        <div class="af-tabs">
            <div class="af-tab active" id="afTab-id" onclick="switchAfTab('id')">By Frame ID</div>
            <div class="af-tab" id="afTab-browse" onclick="switchAfTab('browse')">Browse Variants</div>
        </div>

        <div class="af-tab-content active" id="afContent-id">
            <div class="cm-body" style="padding:16px;">
                <p style="font-size:0.75rem; color:var(--text-muted); margin-top:0;">Add frames by entering their ID directly.</p>
                <div style="display:flex; gap:8px; margin-bottom:16px; align-items:center;">
                    <input type="number" id="addFrameIdInput" class="prompt-input" placeholder="Frame ID..." style="max-width:120px; flex:none;"
                           onkeydown="if(event.key==='Enter'){event.preventDefault();addReferenceFrame();}">
                    <button type="button" class="btn-compose" onclick="addReferenceFrame()"><i class="bi bi-plus-lg"></i> Add Frame</button>
                </div>
                <div id="addFramesList" style="display:flex; flex-direction:column; gap:8px;"></div>
                <div style="height:8px;"></div>
            </div>
        </div>

        <div class="af-tab-content" id="afContent-browse">
            <div class="af-browse-filters">
                <div class="af-browse-row">
                    <button type="button" class="af-area-btn active" id="afAreaBtn-poses" onclick="afSetArea('poses')">Poses</button>
                    <button type="button" class="af-area-btn" id="afAreaBtn-expressions" onclick="afSetArea('expressions')">Expressions</button>
                    <button type="button" class="af-area-btn" id="afAreaBtn-anima_poses" onclick="afSetArea('anima_poses')">Anima</button>
                </div>
                <div class="af-browse-row">
                    <div class="af-select-wrap">
                        <input type="text" class="af-search-input" id="afCharInput" placeholder="Character…" autocomplete="off"
                               oninput="afOnCharSearch()" onfocus="afOnCharFocus()" onblur="afOnCharBlur()"
                               onkeydown="return afHandleCharKeydown(event)">
                        <button type="button" class="af-search-clear" id="afCharClear" onclick="afClearChar()"><i class="bi bi-x"></i></button>
                        <div class="af-dropdown" id="afCharDropdown"></div>
                    </div>
                    <div class="af-select-wrap">
                        <input type="text" class="af-search-input" id="afVariantInput" placeholder="All variants…" autocomplete="off"
                               oninput="afOnVariantSearch()" onfocus="afOnVariantFocus()" onblur="afOnVariantBlur()"
                               onkeydown="return afHandleVariantKeydown(event)">
                        <button type="button" class="af-search-clear" id="afVariantClear" onclick="afClearVariant()"><i class="bi bi-x"></i></button>
                        <div class="af-dropdown" id="afVariantDropdown"></div>
                    </div>
                </div>
            </div>
            <div class="af-browse-grid-wrap">
                <div class="af-browse-state" id="afBrowseState">Select a character to browse frames</div>
                <div class="af-browse-grid" id="afBrowseGrid" style="display:none;"></div>
            </div>
        </div>

        <div class="cm-footer" style="padding-bottom:16px;">
            <button type="button" class="cm-clear-btn" onclick="closeAddFramesModal(false)">Cancel</button>
            <button type="button" class="cm-use-btn" onclick="closeAddFramesModal(true)">Confirm</button>
        </div>
    </div>
</div>

<script>
let curPage = 1;
let totalPages = 1;
let limit = 3; // 3 items per page so the list area fits comfortably without scrolling
let currentId = null;
let saveTimers = {};
let selectedExtraFrameIds = new Set();

function loadList(page) {
    curPage = page;
    $('#enhList').html('<div class="state-msg"><div class="spinner"></div><div>Loading enhancements...</div></div>');
    $.post('framen_api.php', { action: 'fetch_enhancements', page: page, limit: limit }, function(res) {
        if(res.status === 'success') {
            totalPages = res.totalPages;
            $('#pgInput').val(curPage);
            $('#pgTotal').text('/ ' + totalPages);
            $('#pageInfo').text(`${res.totalRows} total`);
            
            renderList(res.rows);
        }
    }, 'json');
}

function renderList(rows) {
    const list = $('#enhList');
    list.empty();
    if(!rows.length) {
        list.html('<div class="state-msg">No enhancements found</div>');
        return;
    }
    rows.forEach(r => {
        const thumb = r.source_filename 
            ? `<img src="${r.source_filename}" class="card-thumb">` 
            : `<div class="card-thumb" style="display:flex;align-items:center;justify-content:center;font-size:0.6rem;color:#555;">No Img</div>`;
        const isRegen = parseInt(r.regenerate_images) === 1 ? 'checked' : '';
        
        const card = $(`
            <div class="card" data-id="${r.id}" onclick="selectEnhancement(${r.id}, this)">
                <div class="card-head">
                    <div class="card-head-left">
                        <span class="card-id">#${r.id}</span>
                        <span class="card-entity">${r.entity_type} #${r.entity_id}</span>
                    </div>
                    <div class="card-head-right">
                        <label class="regen-wrap" onclick="event.stopPropagation()">
                            <input type="checkbox" onchange="updateField(${r.id}, 'regenerate_images', this.checked ? 1 : 0)" ${isRegen}> Regen
                        </label>
                        <button class="btn-action delete" onclick="deleteEnhancement(event, ${r.id})" title="Delete"><i class="bi bi-trash3"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    ${thumb}
                    <div class="card-desc">
                        <textarea class="cz-desc-textarea" placeholder="Description...">${esc(r.description || '')}</textarea>
                    </div>
                </div>
            </div>
        `);
        
        if (currentId === r.id) {
            card.addClass('active');
        }
        
        card.find('textarea').on('click', e => e.stopPropagation());
        card.find('textarea').on('input', function() {
            autoGrow(this);
            debounceSave(r.id, 'description', $(this).val());
        }).each(function() { autoGrow(this); });
        
        list.append(card);
    });
}

function autoGrow(el) {
    el.style.height = 'auto';
    const h = Math.max(38, el.scrollHeight);
    el.style.height = h + 'px';
}

function debounceSave(id, field, value) {
    clearTimeout(saveTimers[id+'_'+field]);
    saveTimers[id+'_'+field] = setTimeout(() => updateField(id, field, value), 700);
}

function updateField(id, field, value) {
    $.post('framen_api.php', { action: 'update_enhancement', id, field, value }, function(res) {
        if(res.status === 'error') {
            Toast.show('Error saving', 'error');
        }
    }, 'json');
}

function deleteEnhancement(e, id) {
    e.stopPropagation();
    if(!confirm('Delete enhancement #' + id + '?')) return;
    $.post('framen_api.php', { action: 'delete_enhancement', id }, function(res) {
        if(res.status === 'success') {
            Toast.show('Deleted', 'success');
            loadList(curPage);
            if(currentId === id) {
                currentId = null;
                $('#btnAddFrames').prop('disabled', true);
                $('#midInfo').text('Select an enhancement above');
                $('#framesGrid').hide().empty();
                $('#gridState').show().html('<div>Select an enhancement above</div>');
                selectedExtraFrameIds.clear();
                updateExtraSummary();
            }
        }
    }, 'json');
}

function selectEnhancement(id, el) {
    currentId = id;
    $('.card').removeClass('active');
    if (el) $(el).addClass('active');
    else $(`.card[data-id="${id}"]`).addClass('active');

    $('#btnAddFrames').prop('disabled', false);
    $('#midInfo').html(`Enhancement <strong>#${id}</strong> active`);
    
    selectedExtraFrameIds.clear();
    updateExtraSummary();

    $('#framesGrid').hide();
    $('#gridState').show().html('<div class="spinner"></div><div>Loading frames...</div>');
    
    $.post('framen_api.php', { action: 'get_frames', id }, function(res) {
        if(res.status === 'success') {
            renderGrid(res.data);
        } else {
            $('#gridState').html('<div>Error loading frames</div>');
        }
    }, 'json');
}

function renderGrid(frames) {
    $('#gridState').hide();
    const grid = $('#framesGrid').empty().show();
    
    if(!frames.length) {
        grid.hide();
        $('#gridState').show().html('<div>No frames found</div>');
        return;
    }
    
    frames.forEach(f => {
        const isSource = f.relation === 'source';
        const isExtra  = f.relation === 'extra';
        const isSelected = selectedExtraFrameIds.has(f.frame_id);

        let cardClass = '';
        if (isSource) cardClass = 'source';
        else if (isExtra) cardClass = 'extra' + (isSelected ? ' selected' : '');

        const triggerHtml = isExtra ? '<div class="f-select-trigger"></div>' : '';
        const tagLabel = isSource ? '<span style="color:var(--teal);margin-right:4px;">SRC</span>' : (isExtra ? '<span style="color:var(--purple);margin-right:4px;">EXT</span>' : '');

        const card = $(`
            <div class="f-card ${cardClass}" data-fid="${f.frame_id}">
                <a href="${f.filename}" class="f-link" data-pswp-width="1024" data-pswp-height="1024">
                    <img src="${f.filename}" onload="this.parentNode.dataset.pswpWidth=this.naturalWidth; this.parentNode.dataset.pswpHeight=this.naturalHeight;">
                </a>
                <div class="f-view-btn" onclick="openFrameModal(${f.frame_id}, event)"><i class="bi bi-arrows-fullscreen"></i></div>
                <div class="f-label">
                    <span>${tagLabel}#${f.frame_id}</span>
                    ${triggerHtml}
                </div>
            </div>
        `);

        if (isExtra) {
            card.find('.f-label').css('cursor', 'pointer').on('click', function(e) {
                e.preventDefault();
                toggleExtraFrame(f.frame_id, card);
            });
        }

        grid.append(card);
    });
    
    if (typeof PhotoSwipeLightbox !== 'undefined') {
        new PhotoSwipeLightbox({ gallery: '#framesGrid', children: 'a.f-link', pswpModule: PhotoSwipe }).init();
    }
}

function toggleExtraFrame(fid, card) {
    if (selectedExtraFrameIds.has(fid)) {
        selectedExtraFrameIds.delete(fid);
        card.removeClass('selected');
    } else {
        selectedExtraFrameIds.add(fid);
        card.addClass('selected');
    }
    updateExtraSummary();
}

function updateExtraSummary() {
    const count = selectedExtraFrameIds.size;
    $('#footerSummary').text(`${count} selected`);
    $('#btnRemoveExtra').prop('disabled', count === 0);
}

function removeSelectedExtraFrames() {
    if (!currentId || selectedExtraFrameIds.size === 0) return;
    if (!confirm(`Remove ${selectedExtraFrameIds.size} extra frames from enhancement #${currentId}?`)) return;

    $('#btnRemoveExtra').prop('disabled', true).text('…');
    $.post('framen_api.php', {
        action: 'remove_extra_frames',
        id: currentId,
        frame_ids: Array.from(selectedExtraFrameIds)
    }, function(res) {
        $('#btnRemoveExtra').text('Remove');
        if (res.status === 'success') {
            Toast.show(`Removed ${res.count} extra frame(s)`, 'success');
            selectedExtraFrameIds.clear();
            updateExtraSummary();
            selectEnhancement(currentId);
        } else {
            Toast.show(res.message || 'Error removing frames', 'error');
            updateExtraSummary();
        }
    }, 'json');
}

function changePage(d) {
    const p = curPage + d;
    if(p >= 1 && p <= totalPages) loadList(p);
}
function jumpToPage() {
    let p = parseInt($('#pgInput').val());
    if(p >= 1 && p <= totalPages) loadList(p);
}

function openFrameModal(id, e) {
    if(e) { e.preventDefault(); e.stopPropagation(); }
    document.getElementById('frameViewer').src = `view_frame.php?frame_id=${id}&view=modal`;
    document.getElementById('viewModal').classList.add('active');
}

function closeFrameModal() {
    document.getElementById('viewModal').classList.remove('active');
    setTimeout(() => {
        document.getElementById('frameViewer').src = '';
    }, 200);
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeFrameModal();
        if (document.getElementById('addFramesBackdrop').classList.contains('active')) closeAddFramesModal(false);
    }
});

function esc(s) {
    return s ? s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : '';
}

// ══════════════════════════════════════════════════════
// ADD FRAMES MODAL LOGIC (Cloned from enhanimaticism)
// ══════════════════════════════════════════════════════
let tempReferenceFrames = [];
let afArea = 'poses';
let afCharId = null;
let afVariantId = null;
let afCharDebounce = null;
let afVariantDebounce = null;

function switchAfTab(tab) {
    ['id','browse'].forEach(t => {
        document.getElementById('afTab-' + t).classList.toggle('active', t === tab);
        document.getElementById('afContent-' + t).classList.toggle('active', t === tab);
    });
}

function openAddFramesModal() {
    if (!currentId) return;
    tempReferenceFrames = [];
    document.getElementById('addFrameIdInput').value = '';
    renderAddFramesList();
    renderAfBrowseGrid([]);
    document.getElementById('addFramesBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
    switchAfTab('id');
}

function closeAddFramesModal(save) {
    if (save && tempReferenceFrames.length > 0 && currentId) {
        const frameIds = tempReferenceFrames.map(f => f.id);
        $.post('framen_api.php', {
            action: 'add_extra_frames',
            id: currentId,
            frame_ids: frameIds
        }, function(res) {
            if (res.status === 'success') {
                Toast.show(`Added ${res.count} extra frame(s)`, 'success');
                selectEnhancement(currentId);
            } else {
                Toast.show(res.message || 'Error adding frames', 'error');
            }
        }, 'json');
    }
    document.getElementById('addFramesBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

function onAddFramesBackdropClick(e) {
    if (e.target === document.getElementById('addFramesBackdrop')) closeAddFramesModal(false);
}

function addReferenceFrame() {
    const input = document.getElementById('addFrameIdInput');
    const fid = parseInt(input.value);
    if (!fid || isNaN(fid)) return;
    if (tempReferenceFrames.find(f => f.id === fid)) { input.value = ''; return; }
    fetch(`framen_api.php?action=get_single_frame&frame_id=${fid}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                tempReferenceFrames.push({ id: res.data.id, filename: res.data.filename });
                renderAddFramesList(); input.value = '';
            } else { Toast.show('Frame not found', 'error'); }
        });
}

function removeReferenceFrame(fid) {
    tempReferenceFrames = tempReferenceFrames.filter(f => f.id !== fid);
    renderAddFramesList();
    const card = document.querySelector(`.af-frame-card[data-fid="${fid}"]`);
    if (card) card.classList.remove('selected');
}

function renderAddFramesList() {
    const list = document.getElementById('addFramesList');
    list.innerHTML = '';
    if (!tempReferenceFrames.length) {
        list.innerHTML = '<div style="font-size:0.7rem; color:var(--text-muted); font-style:italic;">No additional frames chosen yet.</div>';
        return;
    }
    tempReferenceFrames.forEach(f => {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex; align-items:center; gap:10px; background:rgba(255,255,255,0.05); padding:6px; border-radius:4px; border:1px solid var(--border);';
        row.innerHTML = `<img src="${f.filename}" style="width:40px;height:40px;object-fit:cover;border-radius:3px;"><div style="flex:1;font-size:0.75rem;color:var(--text);">Frame #${f.id}</div><button type="button" class="btn-remove-frame" onclick="removeReferenceFrame(${f.id})" title="Remove"><i class="bi bi-trash3"></i></button>`;
        list.appendChild(row);
    });
}

function afSetArea(area) {
    afArea = area;
    ['poses','expressions','anima_poses'].forEach(a => {
        document.getElementById('afAreaBtn-' + a).classList.toggle('active', a === area);
    });
    afClearVariant();
    afLoadGrid();
}

function afHandleCharKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault(); e.stopPropagation();
        const dd = document.getElementById('afCharDropdown');
        if (dd.classList.contains('open')) {
            const firstOpt = dd.querySelector('.af-option:not(.no-res)');
            if (firstOpt && firstOpt.onmousedown) firstOpt.onmousedown(e);
        }
        return false;
    }
    return true;
}

function afHandleVariantKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault(); e.stopPropagation();
        const dd = document.getElementById('afVariantDropdown');
        if (dd.classList.contains('open')) {
            const firstOpt = dd.querySelector('.af-option:not(.no-res)');
            if (firstOpt && firstOpt.onmousedown) firstOpt.onmousedown(e);
        }
        return false;
    }
    return true;
}

function afOnCharSearch()  { clearTimeout(afCharDebounce); afCharDebounce = setTimeout(afFetchChars, 250); }
function afOnCharFocus()   { afFetchChars(); }
function afOnCharBlur()    { setTimeout(() => afCloseDropdown('afCharDropdown'), 180); }

function afFetchChars() {
    const q = document.getElementById('afCharInput').value.trim();
    document.getElementById('afCharClear').classList.toggle('visible', q.length > 0);
    const dd = document.getElementById('afCharDropdown');

    if (q.length > 0 && !dd.classList.contains('open')) {
        dd.innerHTML = '<div class="af-option no-res" style="color:var(--text-muted);font-style:italic;" onmousedown="event.preventDefault(); event.stopPropagation();">Searching…</div>';
        dd.classList.add('open');
    }

    fetch('framen_api.php?action=af_search_characters&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') return;
            afRenderDropdown('afCharDropdown', res.data, item => {
                afCharId = item.id;
                document.getElementById('afCharInput').value = item.name;
                document.getElementById('afCharClear').classList.add('visible');
                afCloseDropdown('afCharDropdown');
                afLoadGrid();
            });
        });
}

function afClearChar() {
    afCharId = null;
    document.getElementById('afCharInput').value = '';
    document.getElementById('afCharClear').classList.remove('visible');
    afClearVariant();
    afLoadGrid();
}

function afOnVariantSearch()  { clearTimeout(afVariantDebounce); afVariantDebounce = setTimeout(afFetchVariants, 250); }
function afOnVariantFocus()   { afFetchVariants(); }
function afOnVariantBlur()    { setTimeout(() => afCloseDropdown('afVariantDropdown'), 180); }

function afFetchVariants() {
    const q = document.getElementById('afVariantInput').value.trim();
    document.getElementById('afVariantClear').classList.toggle('visible', q.length > 0);
    const dd = document.getElementById('afVariantDropdown');

    if (q.length > 0 && !dd.classList.contains('open')) {
        dd.innerHTML = '<div class="af-option no-res" style="color:var(--text-muted);font-style:italic;" onmousedown="event.preventDefault(); event.stopPropagation();">Searching…</div>';
        dd.classList.add('open');
    }

    fetch('framen_api.php?action=af_search_variants&area=' + encodeURIComponent(afArea) + '&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') return;
            afRenderDropdown('afVariantDropdown', res.data, item => {
                afVariantId = item.id;
                document.getElementById('afVariantInput').value = item.name;
                document.getElementById('afVariantClear').classList.add('visible');
                afCloseDropdown('afVariantDropdown');
                afLoadGrid();
            });
        });
}

function afClearVariant() {
    afVariantId = null;
    document.getElementById('afVariantInput').value = '';
    document.getElementById('afVariantClear').classList.remove('visible');
    afLoadGrid();
}

function afRenderDropdown(dropId, items, onSelect) {
    const dd = document.getElementById(dropId);
    dd.innerHTML = '';
    if (!items.length) {
        dd.innerHTML = '<div class="af-option no-res" style="color:var(--text-muted);font-style:italic;" onmousedown="event.preventDefault(); event.stopPropagation();">No results</div>';
    } else {
        items.slice(0, 100).forEach(item => {
            const el = document.createElement('div');
            el.className = 'af-option';
            el.innerHTML = `<span>${esc(item.name)}</span><span class="opt-id">#${item.id}</span>`;
            el.onmousedown = e => {
                e.preventDefault();
                e.stopPropagation();
                setTimeout(() => onSelect(item), 0);
            };
            dd.appendChild(el);
        });
    }
    dd.classList.add('open');
}

function afCloseDropdown(id) { document.getElementById(id).classList.remove('open'); }

function afLoadGrid() {
    if (!afCharId) {
        document.getElementById('afBrowseState').textContent = 'Select a character to browse frames';
        document.getElementById('afBrowseState').style.display = 'block';
        document.getElementById('afBrowseGrid').style.display = 'none';
        return;
    }

    document.getElementById('afBrowseState').textContent = 'Loading…';
    document.getElementById('afBrowseState').style.display = 'block';
    document.getElementById('afBrowseGrid').style.display = 'none';

    const params = new URLSearchParams({ action: 'af_get_variant_frames', area: afArea, char_id: afCharId });
    if (afVariantId) params.set('variant_id', afVariantId);

    fetch('framen_api.php?' + params.toString())
        .then(r => r.json())
        .then(res => {
            document.getElementById('afBrowseState').style.display = 'none';
            renderAfBrowseGrid(res.frames || []);
        })
        .catch(() => {
            document.getElementById('afBrowseState').textContent = 'Error loading frames';
            document.getElementById('afBrowseState').style.display = 'block';
        });
}

function renderAfBrowseGrid(frames) {
    const grid = document.getElementById('afBrowseGrid');
    grid.innerHTML = '';

    if (!frames.length) {
        document.getElementById('afBrowseState').textContent = 'No frames found for this selection';
        document.getElementById('afBrowseState').style.display = 'block';
        grid.style.display = 'none';
        return;
    }

    document.getElementById('afBrowseState').style.display = 'none';
    grid.style.display = 'grid';

    const selectedIds = new Set(tempReferenceFrames.map(f => f.id));

    frames.forEach(f => {
        const card = document.createElement('div');
        card.className = 'af-frame-card' + (selectedIds.has(f.frame_id) ? ' selected' : '');
        card.dataset.fid = f.frame_id;
        card.innerHTML = `<img src="${esc(f.filename)}" loading="lazy"><div class="af-fid">#${f.frame_id}</div>`;
        card.onclick = () => afToggleFrame(f.frame_id, f.filename, card);
        grid.appendChild(card);
    });
}

function afToggleFrame(fid, filename, card) {
    const idx = tempReferenceFrames.findIndex(f => f.id === fid);
    if (idx > -1) {
        tempReferenceFrames.splice(idx, 1);
        card.classList.remove('selected');
    } else {
        tempReferenceFrames.push({ id: fid, filename: filename });
        card.classList.add('selected');
    }
    renderAddFramesList();
}

$(document).ready(() => loadList(1));
</script>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>