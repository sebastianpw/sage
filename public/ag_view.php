<?php
// public/ag_view.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "Automatic Graph Editor";

// ── AJAX: fetch_visuals (mirrors kg_api.php action=fetch_visuals) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_visuals') {
    header('Content-Type: application/json');
    try {
        $em       = $spw->getEntityManager();
        $conn     = $em->getConnection();
        $nodeName = trim($_POST['entity_name'] ?? '');
        $docId    = (int)($_POST['doc_id'] ?? 0);
        if (!$nodeName) { echo json_encode(['ok' => false, 'error' => 'Missing entity_name']); exit; }

        // Find latest sketch generated for this entity (same query as rapid_lore_api_processor)
        $historyRow = $conn->fetchAssociative(
            "SELECT slh.sketch_id, s.name, s.description
               FROM sketch_lore_history slh
               JOIN sketches s ON slh.sketch_id = s.id
              WHERE slh.entity_name = ?
              ORDER BY slh.id DESC LIMIT 1",
            [$nodeName]
        );

        if (!$historyRow) { echo json_encode(['ok' => true, 'sketch' => null]); exit; }

        $sketchId = $historyRow['sketch_id'];
        $frames   = [];
        try {
            $frames = $conn->fetchAllAssociative(
                "SELECT f.id, f.filename
                   FROM frames f
                  WHERE (f.entity_type = 'sketches' AND f.entity_id = ?)
                     OR f.id IN (SELECT from_id FROM frames_2_sketches WHERE to_id = ?)
                  ORDER BY f.id DESC LIMIT 60",
                [$sketchId, $sketchId]
            );
        } catch (\Exception $e) {
            $frames = $conn->fetchAllAssociative(
                "SELECT f.id, f.filename FROM frames f
                  WHERE f.entity_type = 'sketches' AND f.entity_id = ?
                  ORDER BY f.id DESC LIMIT 60",
                [$sketchId]
            );
        }

        // Mini-graph node IDs (same as rapid_lore)
        $agNodeId = 0;
        $kgNodeId = 0;
        if ($docId && $nodeName) {
            try { $agNodeId = (int)$conn->fetchOne("SELECT id FROM ag_nodes WHERE doc_id=? AND LOWER(name)=LOWER(?) AND status='active' LIMIT 1", [$docId, $nodeName]); } catch(\Exception $e){}
        }
        try { $kgNodeId = (int)$conn->fetchOne("SELECT id FROM kg_nodes WHERE LOWER(name)=LOWER(?) AND status='active' LIMIT 1", [$nodeName]); } catch(\Exception $e){}

        echo json_encode([
            'ok'         => true,
            'sketch'     => [
                'id'          => $sketchId,
                'name'        => $historyRow['name'],
                'description' => $historyRow['description'],
                'frames'      => $frames,
            ],
            'ag_node_id' => $agNodeId,
            'kg_node_id' => $kgNodeId,
        ]);
    } catch (\Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

ob_start();
?>
<script>
(function() {
    try {
        var t = localStorage.getItem('spw_theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    } catch(e) {}
})();
</script>

<!-- jsTree -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<!-- Toast UI Editor -->
<link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css"/>
<link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/theme/toastui-editor-dark.min.css" id="tui-dark-theme" disabled/>
<script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>

<!-- Swiper (for gallery modal) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<style>
:root {
    --bg:#f6f8fa; --card:#ffffff; --border:#d0d7de;
    --text:#24292f; --text-muted:#57606a; --accent:#0969da;
    --green:#238636; --red:#da3633; --orange:#f59e0b;
    --sidebar-w:320px;
}
:root[data-theme="dark"] {
    --bg:#0d1117; --card:#161b22; --border:#30363d;
    --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff;
    --green:#238636; --red:#da3633; --orange:#f59e0b;
    --card-elevation:0 6px 18px rgba(2,6,23,.4);
}
@media (prefers-color-scheme:dark) {
    :root:not([data-theme="light"]) {
        --bg:#0d1117; --card:#161b22; --border:#30363d;
        --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff;
    }
}

* { box-sizing: border-box; }
body { margin:0; background:var(--bg); color:var(--text); font-family:system-ui,sans-serif; height:100vh; overflow:hidden; }

.ag-layout { display: flex; height: 100vh; flex-direction: column; }
.ag-topbar { height: 52px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 0 12px; gap: 10px; flex-shrink: 0; position: relative; z-index: 10; }
.ag-topbar-left { margin-left: 64px; display: flex; align-items: center; gap: 8px; }
.ag-topbar h2 { margin:0; font-size:1rem; }
.ag-topbar-right { margin-left:auto; display:flex; gap:6px; align-items:center; }
.ag-doc-selector { padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 0.85rem; min-width: 150px; font-weight: 600; }

.ag-hamburger { position: fixed; top: 10px; left: 70px; z-index: 1100; width: 38px; height: 38px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,.12); transition: background 0.2s; }
.ag-hamburger:hover { background: var(--bg); }
.ag-hamburger span { display: block; width: 20px; height: 2px; background: var(--text); border-radius: 2px; transition: all 0.25s; }
.ag-hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.ag-hamburger.open span:nth-child(2) { opacity: 0; }
.ag-hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

.ag-flyout-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 1050; display: none; pointer-events: none; }
.ag-flyout-overlay.open { display: block; pointer-events: auto; }

.ag-sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: min(320px, 88vw); background: var(--card); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; z-index: 1060; transform: translateX(-110%); transition: transform 0.27s cubic-bezier(0.4,0,0.2,1); box-shadow: 4px 0 20px rgba(0,0,0,.18); }
.ag-sidebar.open { transform: translateX(0); }

.ag-sidebar-header { padding: 12px 14px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; flex-shrink: 0; min-height: 52px; }
.ag-sidebar-header h2 { margin:0; font-size:1rem; flex:1; }
.ag-sidebar-actions { padding: 8px 10px; border-bottom: 1px solid var(--border); display: flex; gap: 6px; flex-wrap: wrap; flex-shrink: 0; align-items: center; }
.ag-tree-wrap { flex: 1; overflow-y: auto; padding: 8px; }

.ag-dnd-toggle { display: inline-flex; align-items: center; gap: 4px; font-size: 0.75rem; color: var(--text-muted); cursor: pointer; user-select: none; padding: 4px 6px; border-radius: 5px; border: 1px solid var(--border); background: transparent; margin-left: auto; white-space: nowrap; transition: border-color 0.15s, color 0.15s; }
.ag-dnd-toggle:hover { border-color: var(--accent); color: var(--accent); }
.ag-dnd-toggle input[type="checkbox"] { accent-color: var(--accent); cursor: pointer; margin: 0; width: 13px; height: 13px; }

.ag-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; min-height: 0; }
.ag-main-header { padding: 10px 16px; border-bottom: 1px solid var(--border); background: var(--card); display: flex; align-items: center; gap: 10px; flex-shrink: 0; min-height: 52px; }
.ag-main-header h3 { margin:0; font-size:1rem; flex:1; }

.ag-empty { flex:1; display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:1.1rem; flex-direction: column; gap: 10px; }
.ag-empty .hint { font-size:0.85rem; opacity:0.7; }

.ag-editor-wrap { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0; }
.ag-meta-bar { padding: 8px 14px; border-bottom: 1px solid var(--border); background: var(--bg); display: flex; gap: 10px; align-items: center; flex-wrap: wrap; flex-shrink: 0; }
.ag-meta-bar input, .ag-meta-bar select { background: var(--card); border: 1px solid var(--border); color: var(--text); padding: 5px 8px; border-radius: 5px; font-size: 0.85rem; }
.ag-meta-bar input[type="text"] { flex:1; min-width:120px; }

#ag-editor { flex: 1; min-height: 0; }

.ag-bottom-drawer { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000; background: var(--card); border-top: 1px solid var(--border); box-shadow: 0 -4px 20px rgba(0,0,0,0.15); height: 44px; display: flex; flex-direction: column; overflow: hidden; transform: translateY(100%); transition: transform 0.35s cubic-bezier(0.2, 0.8, 0.2, 1), height 0.35s cubic-bezier(0.2, 0.8, 0.2, 1); }
:root[data-theme="dark"] .ag-bottom-drawer { box-shadow: 0 -4px 25px rgba(0,0,0,0.7); }
.ag-bottom-drawer.visible { transform: translateY(0); }
.ag-bottom-drawer.open { height: min(400px, 60vh); }

.ag-drawer-handle { height: 44px; min-height: 44px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: var(--card); border-bottom: 1px solid var(--border); position: relative; flex-shrink: 0; transition: background 0.15s; }
.ag-drawer-handle:hover { background: rgba(59,130,246,0.06); }
.ag-drawer-title { position: absolute; left: 16px; top: 0; bottom: 0; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: flex; align-items: center; gap: 8px; }
.ag-drawer-hamburger { display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 4px; width: 44px; height: 44px; }
.ag-drawer-hamburger span { display: block; width: 22px; height: 2px; background: var(--text-muted); border-radius: 2px; transition: transform 0.25s ease, opacity 0.25s ease; }
.ag-bottom-drawer.open .ag-drawer-hamburger span:nth-child(1) { transform: translateY(6px) rotate(45deg); }
.ag-bottom-drawer.open .ag-drawer-hamburger span:nth-child(2) { opacity: 0; }
.ag-bottom-drawer.open .ag-drawer-hamburger span:nth-child(3) { transform: translateY(-6px) rotate(-45deg); }

/* ── Gallery pill on drawer handle (mirrors kg_view) ── */
.ag-gallery-pill {
    display: none;
    align-items: center;
    gap: 5px;
    position: absolute;
    right: 56px;
    top: 50%;
    transform: translateY(-50%);
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    white-space: nowrap;
    cursor: pointer;
    background: rgba(6, 182, 212, 0.12);
    color: #06b6d4;
    border: 1px solid rgba(6, 182, 212, 0.35);
    transition: background 0.15s, border-color 0.15s;
    z-index: 2;
}
.ag-gallery-pill:hover { background: rgba(6, 182, 212, 0.22); border-color: #06b6d4; }
.ag-gallery-pill i { font-size: 0.85rem; }

/* ── Fullscreen Gallery Modal (mirrors kg_view) ── */
.ag-gallery-modal-bg {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.95);
    z-index: 9995;
    display: none;
    flex-direction: column;
    align-items: stretch;
    justify-content: stretch;
}
.ag-gallery-modal-bg.open { display: flex; }

.ag-gallery-modal-header {
    height: 52px;
    min-height: 52px;
    background: var(--card);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    padding: 0 16px;
    gap: 12px;
    flex-shrink: 0;
}
.ag-gallery-modal-title {
    flex: 1;
    font-size: 0.95rem;
    font-weight: 700;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--text);
}

/* Mini-graph pills in gallery header */
.ag-gallery-graph-pills {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}
.ag-gallery-graph-pill {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 8px;
    background: rgba(59,130,246,0.10); color: var(--accent);
    border: 1px solid rgba(59,130,246,0.25);
    cursor: pointer; white-space: nowrap;
    transition: background 0.15s;
    text-decoration: none;
}
.ag-gallery-graph-pill:hover { background: rgba(59,130,246,0.20); }

.ag-gallery-modal-close {
    background: none;
    border: 1px solid var(--border);
    color: var(--text-muted);
    border-radius: 6px;
    width: 34px; height: 34px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 1.2rem;
    line-height: 1;
    transition: color 0.15s, background 0.15s;
}
.ag-gallery-modal-close:hover { color: var(--text); background: var(--bg); }

.ag-gallery-modal-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 16px;
    gap: 12px;
    min-height: 0;
}

/* Square swiper filling available width (mirrors kg_view) */
#agGallerySwiper {
    width: 100%;
    max-width: min(100vw, calc(100vh - 140px));
    aspect-ratio: 1 / 1;
    flex-shrink: 0;
}
#agGallerySwiper .swiper-slide {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #000;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid var(--border);
    position: relative;
}
#agGallerySwiper .swiper-slide img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}
#agGallerySwiper .swiper-button-next,
#agGallerySwiper .swiper-button-prev {
    color: #fff;
    text-shadow: 0 2px 6px rgba(0,0,0,0.8);
}

/* Detail button on each slide — top-right (mirrors kg_view) */
.ag-gal-detail-btn {
    position: absolute;
    top: 8px; right: 8px;
    width: 32px; height: 32px;
    background: rgba(0,0,0,0.65);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 1rem;
    z-index: 10;
    opacity: 0;
    transition: opacity 0.2s, background 0.2s;
}
#agGallerySwiper .swiper-slide:hover .ag-gal-detail-btn { opacity: 1; }
.ag-gal-detail-btn:hover { background: rgba(255,255,255,0.15); }

.ag-gallery-desc {
    width: 100%;
    max-width: min(100vw, calc(100vh - 140px));
    font-size: 0.85rem;
    color: var(--text-muted);
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 8px 12px;
    resize: none;
    outline: none;
    height: 52px;
    flex-shrink: 0;
    font-family: inherit;
}

/* ── Frame detail modal (triggered from gallery, mirrors kg_view) ── */
.ag-frame-view-modal {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.96);
    z-index: 9999;
    display: none;
    align-items: center; justify-content: center;
}
.ag-frame-view-modal.active { display: flex; }
.ag-frame-view-content {
    width: 95vw; height: 95vh;
    background: #000;
    position: relative;
    border: 1px solid var(--border);
    box-shadow: 0 0 30px rgba(0,0,0,0.5);
}
.ag-frame-view-close {
    position: absolute; top: 10px; right: 10px;
    width: 32px; height: 32px;
    background: rgba(0,0,0,0.8); color: #fff;
    border: 1px solid #444; border-radius: 4px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 1.2rem; z-index: 200;
    transition: all 0.2s;
}
.ag-frame-view-close:hover { background: #fff; color: #000; }
iframe.ag-frame-viewer { width: 100%; height: 100%; border: none; }

/* ── Mini-graph iframe modal ── */
.ag-minigraph-modal-bg {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.82);
    backdrop-filter: blur(3px);
    z-index: 100001;
    display: none; align-items: center; justify-content: center;
}
.ag-minigraph-modal-bg.open { display: flex; }
.ag-minigraph-inner {
    width: 94vw; height: 92vh;
    background: var(--card); position: relative;
    border: 1px solid var(--border);
    border-radius: 10px; overflow: hidden;
    box-shadow: 0 24px 64px rgba(0,0,0,0.6);
}
.ag-minigraph-close {
    position: absolute; top: 10px; right: 10px;
    width: 32px; height: 32px;
    background: var(--card); color: var(--text);
    border: 1px solid var(--border); border-radius: 5px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 1.1rem; z-index: 10;
    transition: background 0.15s;
}
.ag-minigraph-close:hover { background: var(--bg); }
.ag-minigraph-iframe { width: 100%; height: 100%; border: none; }

.ag-drawer-content { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; background: var(--card); }

.ag-item-row { display: flex; align-items: center; padding: 10px 16px; border-bottom: 1px solid var(--border); font-size: 0.88rem; gap: 10px; transition: background 0.15s; }
.ag-item-row:hover { background: rgba(59,130,246,0.04); }
.ag-item-row:last-child { border-bottom: none; }

.ag-item-type-pill { display: inline-flex; align-items: center; gap: 4px; font-size: 0.72rem; font-weight: 700; padding: 2px 7px; border-radius: 10px; white-space: nowrap; flex-shrink: 0; min-width: 80px; justify-content: center; }
.ag-pill-ag_node    { background: rgba(139,92,246,0.12); color: #8b5cf6; border: 1px solid rgba(139,92,246,0.25); }
.ag-pill-character  { background: rgba(59,130,246,0.12); color: var(--accent); border: 1px solid rgba(59,130,246,0.25); }
.ag-pill-location   { background: rgba(16,185,129,0.12); color: #10b981; border: 1px solid rgba(16,185,129,0.25); }
.ag-pill-md_doc     { background: rgba(245,158,11,0.12); color: #f59e0b; border: 1px solid rgba(245,158,11,0.25); }
.ag-pill-episode    { background: rgba(239,68,68,0.12);  color: #ef4444; border: 1px solid rgba(239,68,68,0.25); }
.ag-pill-anima      { background: rgba(6,182,212,0.12);  color: #06b6d4; border: 1px solid rgba(6,182,212,0.25); }
.ag-pill-board      { background: rgba(236,72,153,0.12); color: #ec4899; border: 1px solid rgba(236,72,153,0.25); }
.ag-pill-other      { background: rgba(100,116,139,0.12);color:var(--text-muted); border:1px solid var(--border); }
.ag-pill-incoming { background: rgba(16,185,129,0.08); color: #10b981; border: 1px solid rgba(16,185,129,0.2); font-size: 0.68rem; padding: 1px 5px; border-radius: 8px; flex-shrink: 0; }

.ag-item-label-btn { flex: 1; background: none; border: none; color: var(--accent); cursor: pointer; text-align: left; font-size: 0.88rem; padding: 0; font-weight: 600; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; transition: color 0.15s; }
.ag-item-label-btn:hover { color: var(--text); text-decoration: underline; }
.ag-item-label-btn.no-link { color: var(--text); cursor: default; font-weight: normal; }
.ag-item-label-btn.no-link:hover { text-decoration: none; }

.ag-item-rel { font-size: 0.75rem; color: var(--text-muted); background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 2px 8px; white-space: nowrap; flex-shrink: 0; }

.ag-item-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.ag-item-ext-btn, .ag-item-remove, .ag-item-edit { background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 4px 6px; border-radius: 4px; font-size: 0.9rem; line-height: 1; transition: color 0.15s, background 0.15s; }
.ag-item-ext-btn:hover  { color: var(--accent); background: rgba(59,130,246,0.1); }
.ag-item-edit:hover     { color: var(--orange);  background: rgba(245,158,11,0.1); }
.ag-item-remove:hover   { color: var(--red);     background: rgba(218,54,51,0.1); }

.btn { padding: 6px 11px; border-radius:6px; border:none; cursor:pointer; font-weight:600; font-size:0.85rem; display:inline-flex; align-items:center; gap:5px; text-decoration:none; white-space:nowrap; }
.btn-primary { background:var(--accent); color:#fff; }
.btn-green { background:var(--green); color:#fff; }
.btn-ghost { background:transparent; border:1px solid var(--border); color:var(--text); }
.btn-ghost:hover { border-color:var(--accent); color:var(--accent); }
.btn-danger { background:transparent; border:1px solid var(--border); color:var(--red); }
.btn-sm { padding:4px 8px; font-size:0.78rem; }
.btn:disabled { opacity:0.5; cursor:not-allowed; }

.jstree-default .jstree-hovered { background:rgba(59,130,246,.1)!important; color:var(--text)!important; }
.jstree-default .jstree-clicked { background:rgba(59,130,246,.2)!important; color:var(--accent)!important; }
.jstree-default .jstree-anchor { line-height:28px; height:28px; font-size:0.92rem; }
.jstree-default .jstree-icon { width:28px; height:28px; line-height:28px; }

.ag-modal-bg { position:fixed; inset:0; background:rgba(0,0,0,.55); display:none; align-items:center; justify-content:center; z-index:9999; }
.ag-modal { background:var(--card); border:1px solid var(--border); border-radius:10px; padding:20px; width:360px; max-width:94vw; box-shadow:0 10px 30px rgba(0,0,0,.3); }
.ag-modal h3 { margin:0 0 14px 0; font-size:1rem; }
.ag-modal input, .ag-modal select, .ag-modal textarea { width:100%; padding:8px 10px; border-radius:6px; border:1px solid var(--border); background:var(--bg); color:var(--text); font-size:0.9rem; margin-bottom:10px; }
.ag-modal textarea { resize:vertical; min-height:70px; }
.ag-modal .actions { display:flex; gap:8px; justify-content:flex-end; }

#ag-toast { position:fixed; bottom:24px; right:24px; z-index:99999; background:var(--card); color:var(--text); border:1px solid var(--border); border-left:4px solid var(--green); border-radius:6px; padding:12px 18px; font-size:0.9rem; display:none; box-shadow:0 4px 12px rgba(0,0,0,.2); }
.node-type-badge { font-size:0.72rem; padding:2px 7px; border-radius:10px; background:rgba(59,130,246,.12); color:var(--accent); border:1px solid rgba(59,130,246,.25); font-weight:600; }

.age-modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(3px); display: none; align-items: center; justify-content: center; z-index: 9990; }
.age-modal-bg.open { display: flex; }
.age-modal { width: min(780px, 96vw); max-height: 90vh; background: var(--card); border: 1px solid var(--border); border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 24px 64px rgba(0,0,0,0.45); }
.age-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.age-header h3 { margin: 0; font-size: 1rem; flex: 1; display: flex; align-items: center; gap: 8px; }
.age-close { background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 1.3rem; line-height: 1; padding: 2px 6px; border-radius: 4px; transition: color 0.15s, background 0.15s; }
.age-close:hover { color: var(--text); background: var(--bg); }
.age-tabs { display: flex; border-bottom: 1px solid var(--border); padding: 0 20px; flex-shrink: 0; gap: 0; }
.age-tab { padding: 10px 16px; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: color 0.15s, border-color 0.15s; background: none; border-top: none; border-left: none; border-right: none; white-space: nowrap; }
.age-tab:hover { color: var(--text); }
.age-tab.active { color: var(--accent); border-bottom-color: var(--accent); }
.age-panes { flex: 1; overflow: hidden; display: flex; flex-direction: column; min-height: 0; }
.age-pane  { display: none; flex: 1; flex-direction: column; overflow: hidden; min-height: 0; }
.age-pane.active { display: flex; }
.age-full-body { padding: 24px 20px; display: flex; flex-direction: column; gap: 16px; overflow-y: auto; }
.age-option-row { display: flex; align-items: center; gap: 10px; padding: 14px 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; font-size: 0.88rem; }
.age-option-row label { flex: 1; cursor: pointer; }
.age-option-row input[type=checkbox] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--accent); }
.age-desc { font-size: 0.78rem; color: var(--text-muted); line-height: 1.5; padding: 0 4px; }
.age-full-footer { padding: 14px 20px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; flex-shrink: 0; }
.age-sem-top { padding: 16px 20px 12px; border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; flex-direction: column; gap: 10px; }
.age-query-row { display: flex; gap: 8px; }
.age-query-input { flex: 1; padding: 9px 12px; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font-size: 0.9rem; transition: border-color 0.15s; }
.age-query-input:focus { outline: none; border-color: var(--accent); }
.age-n-select { padding: 9px 10px; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font-size: 0.85rem; min-width: 80px; }
.age-hits-area { flex: 1; overflow-y: auto; min-height: 0; }
.age-hits-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; gap: 8px; color: var(--text-muted); font-size: 0.9rem; padding: 40px 20px; text-align: center; }
.age-hit-row { display: flex; align-items: flex-start; padding: 10px 14px; border-bottom: 1px solid var(--border); gap: 10px; transition: background 0.12s; cursor: pointer; }
.age-hit-row:hover { background: rgba(59,130,246,0.04); }
.age-hit-row.selected { background: rgba(59,130,246,0.08); }
.age-hit-check { width: 16px; height: 16px; flex-shrink: 0; margin-top: 3px; cursor: pointer; accent-color: var(--accent); }
.age-hit-body { flex: 1; min-width: 0; }
.age-hit-name { font-weight: 600; font-size: 0.88rem; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.age-hit-excerpt { font-size: 0.78rem; color: var(--text-muted); margin-top: 3px; line-height: 1.45; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.age-type-pill { font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 8px; white-space: nowrap; }
.age-pill-character    { background:rgba(59,130,246,.12);  color:var(--accent); border:1px solid rgba(59,130,246,.25); }
.age-pill-location     { background:rgba(16,185,129,.12);  color:#10b981;       border:1px solid rgba(16,185,129,.25); }
.age-pill-concept      { background:rgba(245,158,11,.12);  color:#f59e0b;       border:1px solid rgba(245,158,11,.25); }
.age-status-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
.age-dot-filled  { background: var(--green); }
.age-dot-partial { background: var(--orange); }
.age-dot-stub    { background: #6b7280; }
.age-dot-empty   { background: var(--border); }
.age-sem-footer { padding: 12px 20px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 10px; flex-shrink: 0; flex-wrap: wrap; }
.age-sel-count { font-size: 0.82rem; color: var(--text-muted); flex: 1; }
</style>

<button class="ag-hamburger" id="ag-hamburger" onclick="toggleSidebar()" title="Toggle navigation">
    <span></span><span></span><span></span>
</button>

<button style="display:none;" id="ag-export-btn" onclick="openExportModal()" title="Export JSON Snapshot"
    style="position:fixed; top:10px; left:116px; z-index:1100;
           width:38px; height:38px; border-radius:8px;
           background:var(--card); border:1px solid var(--border);
           display:flex; align-items:center; justify-content:center;
           cursor:pointer; box-shadow:0 2px 6px rgba(0,0,0,.12);
           font-size:1.1rem; transition:background 0.2s;">
    &#x1F4E4;
</button>

<div class="ag-flyout-overlay" id="ag-flyout-overlay" onclick="closeSidebar()"></div>

<div class="ag-sidebar" id="ag-sidebar">
    <div class="ag-sidebar-header">
        <i class="bi bi-diagram-3-fill" style="color:var(--accent);font-size:1.2rem;"></i>
        <h2> </h2>
        <button class="btn btn-ghost btn-sm" onclick="closeSidebar()" title="Close">&#x2715;</button>
    </div>

    <div class="ag-sidebar-actions">
        <button class="btn btn-ghost btn-sm" onclick="showModal('modalFolder')" title="New Folder">
            <i class="bi bi-folder-plus"></i> Folder
        </button>
        <button class="btn btn-ghost btn-sm" onclick="showModal('modalNode')" title="New Node">
            <i class="bi bi-plus-circle"></i> Node
        </button>
        <button class="btn btn-ghost btn-sm" id="btnEditSelected" onclick="editSelected()" title="Edit selected" disabled style="opacity:0.4;">
            <i class="bi bi-pencil"></i>
        </button>
        <button class="btn btn-ghost btn-sm" onclick="refreshTree()" title="Refresh">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <label class="ag-dnd-toggle" title="Enable or disable drag &amp; drop reordering">
            <input type="checkbox" id="chkDnd" onchange="toggleDnd(this.checked)">
            <i class="bi bi-arrows-move"></i> DnD
        </label>
    </div>

    <div style="padding:6px 10px; border-bottom:1px solid var(--border); flex-shrink:0;">
        <input type="text" id="treeSearch" placeholder="Search nodes..."
            style="width:100%; padding:5px 8px; border-radius:5px; border:1px solid var(--border);
                   background:var(--bg); color:var(--text); font-size:0.83rem;">
    </div>

    <div class="ag-tree-wrap" id="ag-tree">Loading...</div>
</div>

<div class="ag-layout">
    <div class="ag-topbar">
        <div class="ag-topbar-left">
            <i class="bi bi-diagram-3-fill" style="color:var(--accent);"></i>
            <h2 id="ag-topbar-title">AG View</h2>
            <select id="docSelector" class="ag-doc-selector" onchange="switchDoc(this.value)">
                <option value="">-- Select Document --</option>
            </select>
            <button style="display:none;" class="btn btn-ghost btn-sm" onclick="window.location.href='ag_graph.php?doc_id=' + document.getElementById('docSelector').value" style="margin-left:5px;"><i class="bi bi-share"></i> Visualizer</button>
        </div>
        <div class="ag-topbar-right" style="display:none;">
            <button class="btn btn-ghost btn-sm" id="btnTheme" title="Toggle theme">&#x1F319;</button>
        </div>
    </div>

    <div class="ag-main">
        <div class="ag-empty" id="ag-empty-state">
            <i class="bi bi-diagram-3" style="font-size:3rem; opacity:0.3;"></i>
            <span>Select a document and a node from the tree</span>
            <span class="hint">or create a new one with the + Node button</span>
        </div>

        <div id="ag-node-view" style="display:none; flex:1; flex-direction:column; overflow:hidden; min-height:0; padding-bottom: 44px;">
            <div class="ag-main-header">
                <h4 style="font-size:10px;" id="ag-node-title">—</h4>
                <span class="node-type-badge" id="ag-node-type-badge"></span>
                <div style="display:flex; gap:6px; margin-left:auto;">
                    <button class="btn btn-ghost btn-sm" onclick="showAddItemModal()" title="Link entity">
                        <i class="bi bi-link-45deg"></i> Link
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteCurrentNode()" title="Archive node">
                        <i class="bi bi-trash"></i>
                    </button>
                    <button class="btn btn-green" id="btnSaveNode" onclick="saveNode()">
                        <i class="bi bi-floppy"></i> Save
                    </button>
                </div>
            </div>

            <div class="ag-editor-wrap">
                <div class="ag-meta-bar">
                    <span id="nodeIdDisplay" title="Node ID" style="font-family: ui-monospace, SFMono-Regular, monospace; color: var(--text-muted); font-size: 0.85rem; padding: 5px 8px; background: var(--bg); border: 1px solid var(--border); border-radius: 5px;"></span>
                    <input type="text" id="nodeNameInput" placeholder="Node name…" style="font-weight:600; font-size:0.95rem;">
                    <select id="nodeTypeSelect">
                        <option value="note">📝 Note</option>
                        <option value="relationship">🔗 Relationship</option>
                        <option value="character">👤 Character</option>
                        <option value="location">📍 Location</option>
                        <option value="event">📅 Event</option>
                        <option value="concept">💡 Concept</option>
                        <option value="arc">🌀 Arc</option>
                        <option value="episode">🎬 Episode</option>
                    </select>
                    <input type="text" id="nodeKeywords" placeholder="keywords, comma, separated" style="max-width:220px;">
                    <select id="nodeCategorySelect" style="max-width:180px;">
                        <option value="">— No folder —</option>
                    </select>
                </div>

                <div id="ag-editor"></div>
            </div>
        </div>
    </div>
</div>

<!-- BOTTOM DRAWER -->
<div class="ag-bottom-drawer" id="ag-bottom-drawer">
    <div class="ag-drawer-handle" onclick="toggleBottomDrawer()">
        <div class="ag-drawer-title">
            <i class="bi bi-link-45deg" style="font-size:1.1rem;"></i> Linked Entities
            <span id="ag-items-count" style="opacity:0.6;"></span>
        </div>
        <!-- ── Gallery pill (mirrors kg_view) ── -->
        <button id="ag-gallery-pill"
                class="ag-gallery-pill"
                title="View visual gallery"
                onclick="event.stopPropagation(); openGalleryModal();">
            <i class="bi bi-images"></i> Gallery
        </button>
        <div class="ag-drawer-hamburger"><span></span><span></span><span></span></div>
    </div>
    <div class="ag-drawer-content">
        <div id="ag-items-list"></div>
    </div>
</div>

<!-- ═══════ FULLSCREEN GALLERY MODAL ═══════ -->
<div class="ag-gallery-modal-bg" id="ag-gallery-modal-bg">
    <div class="ag-gallery-modal-header">
        <span class="ag-gallery-modal-title" id="ag-gallery-modal-title">Visual Gallery</span>
        <div class="ag-gallery-graph-pills" id="ag-gallery-graph-pills"></div>
        <button class="ag-gallery-modal-close" onclick="closeGalleryModal()" title="Close">&#x2715;</button>
    </div>
    <div class="ag-gallery-modal-body">
        <div class="swiper" id="agGallerySwiper">
            <div class="swiper-wrapper" id="agGallerySwiperWrapper"></div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
        <textarea class="ag-gallery-desc" id="agGalleryDesc" readonly placeholder="Sketch description…"></textarea>
    </div>
</div>

<!-- ═══════ FRAME DETAIL MODAL (triggered from gallery) ═══════ -->
<div class="ag-frame-view-modal" id="ag-frame-view-modal">
    <div class="ag-frame-view-content">
        <div class="ag-frame-view-close" onclick="closeAgFrameModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="ag-frame-viewer" class="ag-frame-viewer" src=""></iframe>
    </div>
</div>

<!-- ═══════ MINI-GRAPH IFRAME MODAL ═══════ -->
<div class="ag-minigraph-modal-bg" id="ag-minigraph-modal-bg">
    <div class="ag-minigraph-inner">
        <button class="ag-minigraph-close" onclick="closeAgMiniGraph()" title="Close">&#x2715;</button>
        <iframe class="ag-minigraph-iframe" id="ag-minigraph-iframe" src=""></iframe>
    </div>
</div>

<!-- MODALS -->
<div class="ag-modal-bg" id="modalEditFolder">
    <div class="ag-modal">
        <h3><i class="bi bi-pencil"></i> Edit Folder</h3>
        <input type="hidden" id="editFolderId">
        <label>Name</label><input type="text" id="editFolderName">
        <div class="actions">
            <button class="btn btn-ghost" onclick="hideModal('modalEditFolder')">Cancel</button>
            <button class="btn btn-primary" onclick="saveEditFolder()">Save</button>
        </div>
    </div>
</div>

<div class="ag-modal-bg" id="modalFolder">
    <div class="ag-modal">
        <h3><i class="bi bi-folder-plus"></i> New Folder</h3>
        <label>Name</label><input type="text" id="folderName">
        <label>Parent folder</label><select id="folderParent"><option value="">— Root —</option></select>
        <div class="actions">
            <button class="btn btn-ghost" onclick="hideModal('modalFolder')">Cancel</button>
            <button class="btn btn-primary" onclick="createFolder()">Create</button>
        </div>
    </div>
</div>

<div class="ag-modal-bg" id="modalNode">
    <div class="ag-modal">
        <h3><i class="bi bi-plus-circle"></i> New Node</h3>
        <label>Name</label><input type="text" id="newNodeName">
        <label>Type</label>
        <select id="newNodeType">
            <option value="note">📝 Note</option>
            <option value="character">👤 Character</option>
            <option value="location">📍 Location</option>
        </select>
        <label>Folder</label><select id="newNodeCategory"><option value="">— Root —</option></select>
        <div class="actions">
            <button class="btn btn-ghost" onclick="hideModal('modalNode')">Cancel</button>
            <button class="btn btn-primary" onclick="createNode()">Create</button>
        </div>
    </div>
</div>

<div class="ag-modal-bg" id="modalAddItem">
    <div class="ag-modal">
        <h3 id="modalAddItemTitle"><i class="bi bi-link-45deg"></i> Link Entity</h3>
        <input type="hidden" id="editingItemId" value="">
        <label>Entity type</label>
        <select id="itemType"><option value="ag_node">AG Node</option><option value="character">Character</option><option value="location">Location</option><option value="other">Other</option></select>
        <label>ID (optional)</label><input type="number" id="itemEntityId">
        <label>Label</label><input type="text" id="itemLabel">
        <label>Relationship</label><input type="text" id="itemRelationship">
        <label>Note</label><textarea id="itemNote"></textarea>
        <div class="actions">
            <button class="btn btn-ghost" onclick="closeAddItemModal()">Cancel</button>
            <button class="btn btn-primary" id="modalAddItemSaveBtn" onclick="saveLinkedItem()">Link</button>
        </div>
    </div>
</div>

<!-- EXPORT MODAL -->
<div class="age-modal-bg" id="age-modal-bg">
    <div class="age-modal">
        <div class="age-header">
            <h3>&#x1F4E4; Export Graph (Current Document)</h3>
            <button class="age-close" onclick="closeExportModal()">&#x2715;</button>
        </div>
        <div class="age-tabs">
            <button class="age-tab active" id="age-tab-full" onclick="ageSetTab('full')">Full Graph</button>
            <button class="age-tab" id="age-tab-semantic" onclick="ageSetTab('semantic')">&#x1F9E0; Semantic Search</button>
        </div>
        <div class="age-panes">
            <div class="age-pane active" id="age-pane-full">
                <div class="age-full-body">
                    <p class="age-desc">Exports the current document's isolated AG nodes and edges.</p>
                    <div class="age-option-row">
                        <input type="checkbox" id="age-full-with-content">
                        <label for="age-full-with-content"><strong>Include lore content</strong></label>
                    </div>
                </div>
                <div class="age-full-footer">
                    <button class="btn btn-ghost" onclick="closeExportModal()">Cancel</button>
                    <button class="btn btn-primary" id="age-full-export-btn" onclick="ageDoFullExport()">Download</button>
                </div>
            </div>
            <div class="age-pane" id="age-pane-semantic">
                <div class="age-sem-top">
                    <div class="age-query-row">
                        <input type="text" class="age-query-input" id="age-query-input" placeholder="Search nodes in this document...">
                        <button class="btn btn-primary" onclick="ageRunQuery()">Search</button>
                    </div>
                </div>
                <div class="age-hits-area" id="age-hits-area"></div>
                <div class="age-sem-footer">
                    <button class="btn btn-primary" id="age-export-sel-btn" onclick="ageDoFocusedExport()" disabled>Export Selected</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="ag-toast"></div>

<script>
// ═══════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════
let currentDocId = null;
let currentNodeId = null;
let selectedTreeItem = null;
let editor = null;
let treeInitialized = false;
let allCategories = [];

// Gallery state (mirrors kg_view)
let agCurrentGalleryData = null;
let agGallerySwiper      = null;

// ═══════════════════════════════════════════════
// INIT DOC SELECTOR
// ═══════════════════════════════════════════════
function initDocs() {
    $.get('ag_api.php?action=fetch_docs', res => {
        if (!res.ok) return;
        const sel = document.getElementById('docSelector');
        res.docs.forEach(d => {
            const o = document.createElement('option');
            o.value = d.id; o.text = `[${d.id}] ${d.name}`;
            sel.add(o);
        });
        const params = new URLSearchParams(window.location.search);
        if (params.has('doc_id')) {
            sel.value = params.get('doc_id');
            currentDocId = parseInt(sel.value);
            if (currentDocId) initTree();
        }
    }, 'json');
}

function switchDoc(docId) {
    if (!docId) return;
    window.location.href = `ag_view.php?doc_id=${docId}`;
}

// ═══════════════════════════════════════════════
// UI TOGGLES
// ═══════════════════════════════════════════════
function toggleSidebar() {
    const s = document.getElementById('ag-sidebar');
    s.classList.toggle('open');
    document.getElementById('ag-flyout-overlay').classList.toggle('open');
    document.getElementById('ag-hamburger').classList.toggle('open');
}
function closeSidebar() {
    document.getElementById('ag-sidebar').classList.remove('open');
    document.getElementById('ag-flyout-overlay').classList.remove('open');
    document.getElementById('ag-hamburger').classList.remove('open');
}
function toggleBottomDrawer() {
    document.getElementById('ag-bottom-drawer').classList.toggle('open');
}

// ═══════════════════════════════════════════════
// THEME
// ═══════════════════════════════════════════════
function isDark() {
    const t = document.documentElement.getAttribute('data-theme');
    if (t === 'dark') return true;
    if (t === 'light') return false;
    return window.matchMedia('(prefers-color-scheme:dark)').matches;
}
function applyTheme() {
    const dark = isDark();
    document.getElementById('tui-dark-theme')[dark ? 'removeAttribute' : 'setAttribute']('disabled', 'true');
    document.getElementById('btnTheme').textContent = dark ? '☀️' : '🌙';
}
document.getElementById('btnTheme').addEventListener('click', () => {
    const next = isDark() ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('spw_theme', next); } catch(e) {}
    applyTheme();
    if (editor) { editor.destroy(); initEditor(currentNodeId ? '' : ''); }
});
applyTheme();
new MutationObserver(() => applyTheme()).observe(document.documentElement, {attributes:true});

// ═══════════════════════════════════════════════
// EDITOR
// ═══════════════════════════════════════════════
function initEditor(initialMd = '') {
    const el = document.getElementById('ag-editor');
    if (editor) { try { editor.destroy(); } catch(e) {} editor = null; }
    editor = new toastui.Editor({
        el, height: '100%', initialEditType: 'markdown', previewStyle: 'tab',
        hideModeSwitch: true,
        theme: isDark() ? 'dark' : 'light',
        initialValue: initialMd,
        usageStatistics: false,
        autofocus: false
    });
}

// ═══════════════════════════════════════════════
// TREE  —  with deep-link support via ready.jstree
// ═══════════════════════════════════════════════
function initTree() {
    if (!currentDocId) return;
    $('#ag-tree').jstree({
        core: {
            data: {
                url: `ag_api.php?action=fetch_tree&doc_id=${currentDocId}`,
                dataType: 'json',
                dataFilter: raw => { const j = JSON.parse(raw); return j.ok ? JSON.stringify(j.tree) : '[]'; }
            },
            themes: { name: 'default', dots: true, icons: true },
            check_callback: true,
        },
        plugins: ['search', 'types', 'contextmenu'],
        types: { folder: { icon: 'bi bi-folder2' }, node: { icon: 'bi bi-journal-text' } }
    })
    .on('ready.jstree', () => {
        const params = new URLSearchParams(window.location.search);
        const nodeId = parseInt(params.get('node_id') || '0');
        if (nodeId) setTimeout(() => loadNode(nodeId), 150);
    })
    .on('select_node.jstree', (e, data) => {
        if (!data.event) return;
        selectedTreeItem = { db_id: data.node.data.db_id, type: data.node.type };
        const editBtn = document.getElementById('btnEditSelected');
        if (editBtn) { editBtn.disabled = false; editBtn.style.opacity = '1'; }
        if (data.node.type === 'node') { loadNode(data.node.data.db_id); closeSidebar(); }
    });
    treeInitialized = true;
    loadCategories();

    let st;
    document.getElementById('treeSearch').addEventListener('input', e => {
        clearTimeout(st);
        st = setTimeout(() => $('#ag-tree').jstree(true).search(e.target.value), 250);
    });
}

function refreshTree() {
    if ($('#ag-tree').jstree(true)) $('#ag-tree').jstree('refresh');
    else initTree();
}

// ═══════════════════════════════════════════════
// CATEGORIES
// ═══════════════════════════════════════════════
function loadCategories() {
    $.get(`ag_api.php?action=fetch_tree&doc_id=${currentDocId}`, res => {
        if (!res.ok) return;
        allCategories = res.tree.filter(n => n.type === 'folder').map(n => ({ id: n.data.db_id, name: n.text }));
        ['folderParent', 'newNodeCategory', 'nodeCategorySelect'].forEach(sid => {
            const sel = document.getElementById(sid); if (!sel) return;
            const prev = sel.value;
            while (sel.options.length > 1) sel.remove(1);
            allCategories.forEach(c => { const o = document.createElement('option'); o.value = c.id; o.text = c.name; sel.add(o); });
            sel.value = prev;
        });
    }, 'json');
}

// ═══════════════════════════════════════════════
// LOAD NODE
// ═══════════════════════════════════════════════
function loadNode(id) {
    if (!currentDocId) return;
    currentNodeId = id;
    $.get(`ag_api.php?action=get_node&doc_id=${currentDocId}&id=${id}`, res => {
        if (!res.ok) { toast('Failed to load node', 'error'); return; }
        const n = res.node;
        document.getElementById('ag-empty-state').style.display = 'none';
        document.getElementById('ag-node-view').style.display = 'flex';
        document.getElementById('ag-bottom-drawer').classList.add('visible');
        document.getElementById('ag-bottom-drawer').classList.remove('open');

        document.getElementById('ag-node-title').textContent = n.name;
        document.getElementById('ag-topbar-title').textContent = n.name;
        document.getElementById('ag-node-type-badge').textContent = n.node_type;
        document.getElementById('nodeIdDisplay').textContent = n.id;
        document.getElementById('nodeNameInput').value = n.name;
        document.getElementById('nodeTypeSelect').value = n.node_type;
        document.getElementById('nodeCategorySelect').value = n.category_id || '';

        initEditor(n.content || '');
        renderLinkedItems(n.items || []);

        // ── Fetch visuals and show/hide gallery pill (mirrors kg_view) ──
        fetchNodeVisuals(n.name);
    }, 'json');
}

function showEmptyState() {
    currentNodeId = null;
    document.getElementById('ag-empty-state').style.display = 'flex';
    document.getElementById('ag-node-view').style.display = 'none';
    document.getElementById('ag-bottom-drawer').classList.remove('visible');
    document.getElementById('ag-bottom-drawer').classList.remove('open');
    document.getElementById('ag-gallery-pill').style.display = 'none';
}

// ═══════════════════════════════════════════════
// GALLERY — fetch visuals, store, open modal
// (mirrors kg_view pattern exactly)
// ═══════════════════════════════════════════════
function fetchNodeVisuals(nodeName) {
    agCurrentGalleryData = null;
    document.getElementById('ag-gallery-pill').style.display = 'none';

    $.post('ag_view.php', { action: 'fetch_visuals', entity_name: nodeName, doc_id: currentDocId || 0 }, function(res) {
        if (res.ok && res.sketch && res.sketch.frames && res.sketch.frames.length > 0) {
            agCurrentGalleryData = res;
            document.getElementById('ag-gallery-pill').style.display = 'inline-flex';

            // Pre-build graph pills (stored in DOM ready for openGalleryModal)
            let pillsHtml = '';
            if (res.kg_node_id > 0) {
                const kgUrl = `mini_graph.php?graph=kg&node_id=${res.kg_node_id}`;
                pillsHtml += `<a class="ag-gallery-graph-pill" href="${kgUrl}" target="_blank">🔮 KG</a>
                              <button class="ag-gallery-graph-pill" onclick="openAgMiniGraph('${escHtmlAttr(kgUrl)}')">⤢ modal</button>`;
            }
            if (res.ag_node_id > 0) {
                const agUrl = `mini_graph.php?graph=ag&doc_id=${currentDocId}&node_id=${res.ag_node_id}`;
                if (pillsHtml) pillsHtml += '<span style="color:var(--border); margin:0 2px;">|</span>';
                pillsHtml += `<a class="ag-gallery-graph-pill" href="${agUrl}" target="_blank">📜 AG</a>
                              <button class="ag-gallery-graph-pill" onclick="openAgMiniGraph('${escHtmlAttr(agUrl)}')">⤢ modal</button>`;
            }
            document.getElementById('ag-gallery-graph-pills').innerHTML = pillsHtml;
        }
    }, 'json').fail(() => {});
}

function openGalleryModal() {
    if (!agCurrentGalleryData || !agCurrentGalleryData.sketch) return;

    const sketch   = agCurrentGalleryData.sketch;
    const nodeName = document.getElementById('ag-node-title').textContent || 'Visual Gallery';

    document.getElementById('ag-gallery-modal-title').textContent =
        '🖼️ ' + (sketch.name || nodeName);
    document.getElementById('agGalleryDesc').value = sketch.description || '';

    // Build slides
    const wrapper = document.getElementById('agGallerySwiperWrapper');
    wrapper.innerHTML = '';
    sketch.frames.forEach(f => {
        const slide = document.createElement('div');
        slide.className = 'swiper-slide';
        slide.innerHTML = `
            <img src="${escHtmlAttr(f.filename)}" loading="lazy" alt="">
            <button class="ag-gal-detail-btn"
                    onclick="event.stopPropagation(); openAgFrameModal(${parseInt(f.id)})"
                    title="Open detail view">
                <i class="bi bi-arrows-fullscreen"></i>
            </button>`;
        wrapper.appendChild(slide);
    });

    document.getElementById('ag-gallery-modal-bg').classList.add('open');

    // Init swiper after modal is visible (mirrors kg_view)
    if (agGallerySwiper) { agGallerySwiper.destroy(true, true); agGallerySwiper = null; }
    setTimeout(() => {
        agGallerySwiper = new Swiper('#agGallerySwiper', {
            slidesPerView: 1,
            spaceBetween: 0,
            navigation: {
                nextEl: '#agGallerySwiper .swiper-button-next',
                prevEl: '#agGallerySwiper .swiper-button-prev',
            },
        });
    }, 50);
}

function closeGalleryModal() {
    document.getElementById('ag-gallery-modal-bg').classList.remove('open');
    if (agGallerySwiper) { agGallerySwiper.destroy(true, true); agGallerySwiper = null; }
}

// ═══════════════════════════════════════════════
// FRAME DETAIL MODAL
// ═══════════════════════════════════════════════
function openAgFrameModal(frameId) {
    document.getElementById('ag-frame-viewer').src = `view_frame.php?frame_id=${frameId}&view=modal`;
    document.getElementById('ag-frame-view-modal').classList.add('active');
}

function closeAgFrameModal() {
    document.getElementById('ag-frame-view-modal').classList.remove('active');
    setTimeout(() => { document.getElementById('ag-frame-viewer').src = ''; }, 200);
}

// ═══════════════════════════════════════════════
// MINI-GRAPH MODAL
// ═══════════════════════════════════════════════
function openAgMiniGraph(url) {
    document.getElementById('ag-minigraph-iframe').src = url;
    document.getElementById('ag-minigraph-modal-bg').classList.add('open');
}

function closeAgMiniGraph() {
    document.getElementById('ag-minigraph-modal-bg').classList.remove('open');
    setTimeout(() => { document.getElementById('ag-minigraph-iframe').src = ''; }, 200);
}

// ═══════════════════════════════════════════════
// LINKED ITEMS — RENDER
// ═══════════════════════════════════════════════
function renderLinkedItems(items) {
    const list  = document.getElementById('ag-items-list');
    const count = document.getElementById('ag-items-count');
    count.textContent = items.length ? '(' + items.length + ')' : '';

    if (!items.length) {
        list.innerHTML = `<div style="padding:20px; color:var(--text-muted); font-size:0.85rem; font-style:italic; text-align:center;">No linked entities yet.</div>`;
        return;
    }
    list.innerHTML = items.map(item => {
        const isIncoming = item.direction === 'incoming';
        const linkId    = isIncoming ? item.node_id : item.item_id;
        const linkLabel = escHtml(isIncoming ? (item.source_node_name || item.item_label) : (item.item_label || '—'));
        const dirBadge  = isIncoming ? `<span class="ag-pill-incoming">← from</span>` : '';
        const typeClass = item.item_type || 'ag_node';
        const relBadge  = item.relationship ? `<span class="ag-item-rel">${escHtml(item.relationship)}</span>` : '';

        const primaryBtn = (linkId)
            ? `<button class="ag-item-label-btn" onclick="loadNode(${parseInt(linkId)})">${linkLabel}</button>`
            : `<span class="ag-item-label-btn no-link">${linkLabel}</span>`;

        const removeBtn = !isIncoming
            ? `<button class="ag-item-remove" onclick="removeLinkedItem(${item.id})" title="Remove link"><i class="bi bi-trash"></i></button>`
            : '';

        return `<div class="ag-item-row" data-item-id="${item.id}">
            <span class="ag-item-type-pill ag-pill-${typeClass}">${escHtml(typeClass)}</span>
            ${dirBadge}${primaryBtn}${relBadge}
            <div class="ag-item-actions">${removeBtn}</div>
        </div>`;
    }).join('');
}

// ═══════════════════════════════════════════════
// SAVE / DELETE NODE
// ═══════════════════════════════════════════════
function saveNode() {
    if (!currentNodeId || !currentDocId) return;
    const btn = document.getElementById('btnSaveNode');
    btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass"></i> Saving…';
    $.post('ag_api.php', {
        action: 'save_node', doc_id: currentDocId, id: currentNodeId,
        name:        document.getElementById('nodeNameInput').value,
        content:     editor ? editor.getMarkdown() : '',
        node_type:   document.getElementById('nodeTypeSelect').value,
        category_id: document.getElementById('nodeCategorySelect').value || null
    }, res => {
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-floppy"></i> Save';
        if (res.ok) { toast('Saved ✓'); refreshTree(); }
        else toast('Save failed: ' + (res.error || ''), 'error');
    }, 'json').fail(() => {
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-floppy"></i> Save';
        toast('Network error', 'error');
    });
}

function deleteCurrentNode() {
    if (!currentNodeId || !currentDocId) return;
    if (!confirm('Archive this node?')) return;
    $.post('ag_api.php', { action: 'delete_node', doc_id: currentDocId, id: currentNodeId }, res => {
        if (res.ok) { refreshTree(); showEmptyState(); toast('Archived'); }
        else toast('Error', 'error');
    }, 'json');
}

// ═══════════════════════════════════════════════
// LINKED ITEMS — ADD / REMOVE
// ═══════════════════════════════════════════════
function showAddItemModal() {
    document.getElementById('editingItemId').value = '';
    document.getElementById('modalAddItemTitle').innerHTML = '<i class="bi bi-link-45deg"></i> Link Entity';
    document.getElementById('modalAddItemSaveBtn').textContent = 'Link';
    document.getElementById('itemType').value = 'ag_node';
    document.getElementById('itemEntityId').value = '';
    document.getElementById('itemLabel').value = '';
    document.getElementById('itemRelationship').value = '';
    document.getElementById('itemNote').value = '';
    showModal('modalAddItem');
}
function closeAddItemModal() { hideModal('modalAddItem'); document.getElementById('editingItemId').value = ''; }

function saveLinkedItem() {
    $.post('ag_api.php', {
        action: 'add_item', doc_id: currentDocId, node_id: currentNodeId,
        item_type:    document.getElementById('itemType').value,
        item_id:      document.getElementById('itemEntityId').value || null,
        item_label:   document.getElementById('itemLabel').value,
        relationship: document.getElementById('itemRelationship').value,
        note:         document.getElementById('itemNote').value
    }, res => {
        if (res.ok) { closeAddItemModal(); loadNode(currentNodeId); toast('Linked ✓'); document.getElementById('ag-bottom-drawer').classList.add('open'); }
        else toast('Error: ' + res.error, 'error');
    }, 'json');
}

function removeLinkedItem(id) {
    $.post('ag_api.php', { action: 'remove_item', doc_id: currentDocId, id }, res => {
        if (res.ok) { loadNode(currentNodeId); toast('Removed'); }
        else toast('Error', 'error');
    }, 'json');
}

// ═══════════════════════════════════════════════
// CREATE FOLDER / NODE
// ═══════════════════════════════════════════════
function createFolder() {
    const name     = document.getElementById('folderName').value.trim();
    const parentId = document.getElementById('folderParent').value || null;
    if (!name) return;
    $.post('ag_api.php', { action: 'create_category', doc_id: currentDocId, name, parent_id: parentId }, res => {
        if (res.ok) { hideModal('modalFolder'); document.getElementById('folderName').value = ''; refreshTree(); toast('Folder created'); }
        else toast('Error: ' + res.error, 'error');
    }, 'json');
}

function createNode() {
    const name = document.getElementById('newNodeName').value.trim();
    if (!name) return;
    $.post('ag_api.php', {
        action: 'create_node', doc_id: currentDocId,
        name,
        node_type:   document.getElementById('newNodeType').value,
        category_id: document.getElementById('newNodeCategory').value || null
    }, res => {
        if (res.ok) { hideModal('modalNode'); document.getElementById('newNodeName').value = ''; refreshTree(); toast('Node created'); setTimeout(() => loadNode(res.id), 400); }
        else toast('Error: ' + res.error, 'error');
    }, 'json');
}

// ═══════════════════════════════════════════════
// EDIT SELECTED TREE ITEM
// ═══════════════════════════════════════════════
function editSelected() {
    if (!selectedTreeItem) return;
    if (selectedTreeItem.type === 'folder') {
        document.getElementById('editFolderName').value = '';
        document.getElementById('editFolderId').value   = selectedTreeItem.db_id;
        showModal('modalEditFolder');
    } else {
        if (currentNodeId !== selectedTreeItem.db_id) { loadNode(selectedTreeItem.db_id); closeSidebar(); }
        else { closeSidebar(); document.getElementById('nodeNameInput').focus(); }
    }
}

function saveEditFolder() {
    const id   = parseInt(document.getElementById('editFolderId').value);
    const name = document.getElementById('editFolderName').value.trim();
    if (!id || !name) return;
    $.post('ag_api.php', { action: 'rename_category', doc_id: currentDocId, id, name }, res => {
        if (res.ok) { hideModal('modalEditFolder'); refreshTree(); toast('Folder renamed'); }
        else toast('Error', 'error');
    }, 'json');
}

// ═══════════════════════════════════════════════
// MODAL HELPERS
// ═══════════════════════════════════════════════
function showModal(id) { document.getElementById(id).style.display = 'flex'; }
function hideModal(id) { document.getElementById(id).style.display = 'none'; }

document.querySelectorAll('.ag-modal-bg').forEach(bg => {
    bg.addEventListener('click', e => {
        if (e.target === bg) { bg.style.display = 'none'; document.getElementById('editingItemId').value = ''; }
    });
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.ag-modal-bg').forEach(b => b.style.display = 'none');
        document.getElementById('editingItemId').value = '';
        closeExportModal();
        closeGalleryModal();
        closeAgFrameModal();
        closeAgMiniGraph();
    }
});

document.getElementById('ag-gallery-modal-bg').addEventListener('click', function(e) {
    if (e.target === this) closeGalleryModal();
});
document.getElementById('ag-minigraph-modal-bg').addEventListener('click', function(e) {
    if (e.target === this) closeAgMiniGraph();
});

// ═══════════════════════════════════════════════
// TOAST & UTILS
// ═══════════════════════════════════════════════
let toastTimer;
function toast(msg, type = 'success') {
    const el = document.getElementById('ag-toast');
    el.textContent = msg;
    el.style.borderLeftColor = type === 'error' ? 'var(--red)' : 'var(--green)';
    el.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.style.display = 'none', 2800);
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function escHtmlAttr(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ═══════════════════════════════════════════════
// EXPORT MODAL
// ═══════════════════════════════════════════════
let ageSelectedIds = new Set();

function openExportModal() { if (!currentDocId) return; document.getElementById('age-modal-bg').classList.add('open'); ageSetTab('full'); }
function closeExportModal() { document.getElementById('age-modal-bg').classList.remove('open'); }

document.getElementById('age-modal-bg').addEventListener('click', function(e) { if (e.target === this) closeExportModal(); });

function ageSetTab(tab) {
    document.getElementById('age-pane-full').classList.toggle('active', tab === 'full');
    document.getElementById('age-pane-semantic').classList.toggle('active', tab === 'semantic');
    document.getElementById('age-tab-full').classList.toggle('active', tab === 'full');
    document.getElementById('age-tab-semantic').classList.toggle('active', tab === 'semantic');
}

function ageDoFullExport() {
    const withContent = document.getElementById('age-full-with-content').checked;
    const btn = document.getElementById('age-full-export-btn');
    btn.disabled = true; btn.textContent = '⏳ Building…';
    fetch(`ag_api.php?action=export_snapshot&doc_id=${currentDocId}`)
        .then(r => r.json())
        .then(res => {
            if (res.ok) { kgeTriggerDownload(res.snapshot, `ag_export_${currentDocId}_${kgeDate()}.json`); toast('Exported ✓'); closeExportModal(); }
            else toast('Export failed', 'error');
        })
        .catch(() => toast('Export failed', 'error'))
        .finally(() => { btn.disabled = false; btn.textContent = 'Download'; });
}

function ageRunQuery() {
    const q = document.getElementById('age-query-input').value.trim();
    if (!q) return;
    fetch('ag_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'semantic_query', doc_id: currentDocId, query: q }) })
        .then(r => r.json())
        .then(res => {
            ageSelectedIds = new Set(res.hits.map(h => h.node_id));
            document.getElementById('age-hits-area').innerHTML = res.hits.length
                ? res.hits.map(h => `<div class="age-hit-row selected" data-id="${h.node_id}">
                    <div class="age-hit-body"><b>${escHtml(h.name)}</b> <span class="age-type-pill age-pill-character">${escHtml(h.node_type)}</span><div class="age-hit-excerpt">${escHtml(h.excerpt)}</div></div>
                  </div>`).join('')
                : '<div class="age-hits-empty"><span>No matches found</span></div>';
            document.getElementById('age-export-sel-btn').disabled = res.hits.length === 0;
        });
}

function ageDoFocusedExport() {
    if (!ageSelectedIds.size) return;
    fetch('ag_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'focused_snapshot', doc_id: currentDocId, node_ids: Array.from(ageSelectedIds), with_content: true }) })
        .then(r => r.json())
        .then(res => {
            if (res.ok) { kgeTriggerDownload(res.snapshot, `ag_semantic_${currentDocId}_${kgeDate()}.json`); toast('Exported ✓'); closeExportModal(); }
            else toast('Export failed', 'error');
        });
}

function kgeTriggerDownload(obj, filename) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([JSON.stringify(obj, null, 2)], { type: 'application/json' }));
    a.download = filename; a.click();
}
function kgeDate() { return new Date().toISOString().slice(0, 10); }

// ═══════════════════════════════════════════════
// KEYBOARD SHORTCUTS
// ═══════════════════════════════════════════════
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); if (currentNodeId) saveNode(); }
});

// ═══════════════════════════════════════════════
// BOOT
// ═══════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    initDocs();
    initEditor();
});
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>