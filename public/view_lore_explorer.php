<?php
// public/view_lore_explorer.php
// SAGE Lore Explorer + Showrunner V9.3 Integration
// Uses embedded view_curated_docs.php for perfect rendering parity
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$pageTitle = "Lore Explorer 🧭";

// Load available collections
$collectionsStmt = $pdo->query("SELECT name, type FROM chroma_collections ORDER BY name ASC");
$collections = $collectionsStmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<style>
    /* Explorer Specific Styles */
    :root {
        --story-color: #8b5cf6;
        --world-color: #3b82f6;
        --curator-color: #10b981;
        --draft-color: #f59e0b;
        --fold-bg: rgba(0,0,0,0.02);
        --fold-border: rgba(0,0,0,0.08);
        --accent-subtle: rgba(139, 92, 246, 0.1);
        --card-hover: translateY(-2px);
    }
    
    html { font-size: 110%; } 
    body { background: var(--bg); color: var(--text); padding-bottom: 100px; }

    /* SEARCH HEADER */
    .search-header {
        position: sticky; top: 0; z-index: 100;
        background: var(--card); border-bottom: 1px solid var(--border);
        padding: 15px 20px;
        display: flex; gap: 15px; align-items: center; justify-content: center;
        flex-wrap: wrap; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    
    .search-container {
        display: flex; align-items: center; background: var(--bg);
        border: 2px solid var(--border); border-radius: 50px;
        padding: 5px 15px; width: 100%; max-width: 800px;
        flex: 1 1 300px; 
        transition: border-color 0.2s;
    }
    .search-container:focus-within { border-color: var(--accent); }
    
    .search-icon { font-size: 1.2rem; color: var(--text-muted); margin-right: 10px; }
    .search-input {
        border: none; background: transparent; color: var(--text);
        font-size: 1.1rem; flex: 1; padding: 10px; outline: none; min-width: 0;
    }

    .header-actions { display: flex; gap: 10px; flex-shrink: 0; flex-wrap: wrap; }
    .action-btn {
        background: var(--bg); border: 1px solid var(--border); color: var(--text);
        padding: 8px 16px; border-radius: 20px; cursor: pointer; white-space: nowrap; transition: 0.2s;
        font-size: 0.9rem; font-weight: 500;
    }
    .action-btn:hover { background: var(--card); border-color: var(--accent); }
    .action-btn.active { background: var(--accent); color: white; border-color: var(--accent); }

    /* Collection Select */
    .collection-select {
        background: var(--bg); border: 1px solid var(--border); color: var(--text);
        padding: 8px 12px; border-radius: 20px; font-size: 0.9rem; font-weight: 500;
        cursor: pointer; transition: 0.2s; min-width: 150px;
    }
    .collection-select:hover { border-color: var(--accent); }
    .collection-select:focus { outline: none; border-color: var(--accent); }

    /* RESULTS GRID */
    #explorer-content { padding: 20px; max-width: 1600px; margin: 0 auto; }
    .lore-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
    
    .hit-card {
        background: var(--card); border: 1px solid var(--border); border-radius: 12px;
        overflow: hidden; transition: transform 0.2s; position: relative;
        display: flex; flex-direction: column; cursor: pointer;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .hit-card:hover { transform: translateY(-3px); border-color: var(--accent); box-shadow: var(--card-elevation); }
    
    .hit-header { padding: 10px 15px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; }
    .type-character { background: rgba(59, 130, 246, 0.1); color: var(--world-color); }
    .type-episode { background: rgba(139, 92, 246, 0.1); color: var(--story-color); }
    .type-overview { background: rgba(16, 185, 129, 0.1); color: var(--curator-color); }
    .type-location { background: rgba(245, 159, 11, 0.1); color: var(--draft-color); }
    
    .hit-body { padding: 15px; flex: 1; display:flex; flex-direction:column; gap:8px; }
    .hit-title { font-size: 1.2rem; font-weight: 800; color: var(--text); }
    .hit-doc-ref { font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }
    .hit-snippet { font-family: 'Courier New', monospace; font-size: 0.85rem; line-height: 1.5; color: var(--text); opacity: 0.9; background: rgba(0,0,0,0.03); padding: 10px; border-radius: 6px; border: 1px dashed var(--border); }
    
    .hit-footer { padding: 8px 15px; background: rgba(0,0,0,0.02); border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: var(--text-muted); }

    /* PAGINATION */
    .pagination-container { text-align: center; padding: 40px; }
    .load-more-btn {
        padding: 12px 40px; background: var(--card); border: 1px solid var(--border);
        border-radius: 30px; cursor: pointer; font-size: 1.1rem; color: var(--text);
        box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: all 0.2s;
    }
    .load-more-btn:hover { border-color: var(--accent); transform: translateY(-2px); color: var(--accent); }

    /* MODAL (IFRAME CONTAINER) */
    .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); z-index: 2000; animation: fadeIn 0.2s; }
    .modal-window { 
        display: none; position: fixed; top: 2vh; bottom: 2vh; left: 50%; transform: translateX(-50%);
        width: 95%; max-width: 1400px; background: var(--card); 
        border-radius: 12px; box-shadow: 0 25px 60px rgba(0,0,0,0.5); z-index: 2001; 
        flex-direction: column; overflow: hidden; border: 1px solid var(--border); animation: slideUp 0.3s;
    }
    .modal-close-btn {
        position: absolute; top: -3px; right: -3px; z-index: 3000;
        background: var(--card); border: 1px solid var(--border); color: var(--text);
        width: 40px; height: 40px; border-radius: 50%; cursor: pointer;
        font-size: 1.5rem; display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    .modal-close-btn:hover { background: var(--accent); color:white; }

    .modal-speak-btn {
        position: absolute; top: 18px; right: 84px; z-index: 3000;
        background: var(--card); border: 1px solid var(--border); color: var(--text);
        padding: 8px 12px; border-radius: 8px; cursor: pointer; font-size: 0.95rem;
        display: flex; align-items: center; gap: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        transition: all 0.2s;
    }
    .modal-speak-btn:hover { background: var(--accent); color:white; }
    .modal-speak-btn.speaking { background: #10b981; color: white; }
    
    /* TTS Status Indicator */
    .tts-status {
        display: none;
        position: absolute;
        top: 18px;
        right: 230px;
        z-index: 3000;
        background: var(--card);
        border: 1px solid var(--border);
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 0.85rem;
        color: var(--text-muted);
        align-items: center;
        gap: 8px;
    }
    
    .tts-loader {
        width: 16px;
        height: 16px;
        border: 2px solid var(--border);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    #docFrame { width: 100%; height: 100%; border: none; background: var(--bg); }

    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
    @keyframes slideUp { from { transform: translate(-50%, 30px); opacity:0; } to { transform: translate(-50%, 0); opacity:1; } }

    #tts-float-widget {
        display: none !important;
    }

    /* ══════════════════════════════════════════════
       LORE EXPORT MODAL STYLES
       ══════════════════════════════════════════════ */
    .le-modal-bg {
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.65);
        backdrop-filter: blur(3px);
        display: none; align-items: center; justify-content: center;
        z-index: 3100;
    }
    .le-modal-bg.open { display: flex; }

    .le-modal {
        width: min(800px, 96vw);
        max-height: 90vh;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 24px 64px rgba(0,0,0,0.45);
    }

    .le-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
        display: flex; align-items: center; gap: 10px;
        flex-shrink: 0;
    }
    .le-header h3 { margin: 0; font-size: 1rem; flex: 1; }
    .le-close {
        background: none; border: none; cursor: pointer;
        color: var(--text-muted); font-size: 1.3rem;
        padding: 2px 6px; border-radius: 4px;
        transition: color 0.15s, background 0.15s;
    }
    .le-close:hover { color: var(--text); background: var(--bg); }

    /* Tabs */
    .le-tabs {
        display: flex;
        border-bottom: 1px solid var(--border);
        padding: 0 20px;
        flex-shrink: 0;
    }
    .le-tab {
        padding: 10px 16px;
        font-size: 0.85rem; font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
        border-bottom: 2px solid transparent;
        margin-bottom: -1px;
        transition: color 0.15s, border-color 0.15s;
        background: none;
        border-top: none; border-left: none; border-right: none;
        white-space: nowrap;
    }
    .le-tab:hover { color: var(--text); }
    .le-tab.active { color: var(--accent); border-bottom-color: var(--accent); }

    /* Panes */
    .le-panes { flex: 1; overflow: hidden; display: flex; flex-direction: column; min-height: 0; }
    .le-pane  { display: none; flex: 1; flex-direction: column; overflow: hidden; min-height: 0; }
    .le-pane.active { display: flex; }

    /* Full export pane */
    .le-full-body {
        padding: 24px 20px;
        display: flex; flex-direction: column; gap: 16px;
        overflow-y: auto;
    }
    .le-option-row {
        display: flex; align-items: center; gap: 10px;
        padding: 14px 16px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.88rem;
    }
    .le-option-row label { flex: 1; cursor: pointer; }
    .le-option-row input[type=checkbox] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--accent); }
    .le-desc {
        font-size: 0.78rem; color: var(--text-muted); line-height: 1.5;
        padding: 0 4px;
    }
    .le-full-footer {
        padding: 14px 20px;
        border-top: 1px solid var(--border);
        display: flex; gap: 8px; justify-content: flex-end;
        flex-shrink: 0;
    }

    /* Semantic pane */
    .le-sem-top {
        padding: 16px 20px 12px;
        border-bottom: 1px solid var(--border);
        flex-shrink: 0;
        display: flex; flex-direction: column; gap: 10px;
    }
    .le-query-row { display: flex; gap: 8px; }
    .le-query-input {
        flex: 1;
        padding: 9px 12px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 6px;
        color: var(--text);
        font-size: 0.9rem;
        transition: border-color 0.15s;
    }
    .le-query-input:focus { outline: none; border-color: var(--accent); }
    .le-query-input::placeholder { color: var(--text-muted); opacity: 0.7; }
    .le-n-select {
        padding: 9px 10px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 6px;
        color: var(--text);
        font-size: 0.85rem;
        min-width: 80px;
    }
    .le-col-select {
        padding: 9px 10px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 6px;
        color: var(--text);
        font-size: 0.85rem;
        min-width: 130px;
    }

    .le-hits-area {
        flex: 1;
        overflow-y: auto;
        min-height: 0;
    }
    .le-hits-empty {
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        height: 100%; gap: 8px;
        color: var(--text-muted); font-size: 0.9rem;
        padding: 40px 20px; text-align: center;
    }
    .le-hits-empty .hint { font-size: 0.78rem; opacity: 0.65; max-width: 340px; line-height: 1.5; }

    .le-hit-row {
        display: flex; align-items: flex-start;
        padding: 10px 14px;
        border-bottom: 1px solid var(--border);
        gap: 10px;
        transition: background 0.12s;
        cursor: pointer;
    }
    .le-hit-row:hover { background: rgba(59,130,246,0.04); }
    .le-hit-row.selected { background: rgba(59,130,246,0.08); }
    .le-hit-check {
        width: 16px; height: 16px; flex-shrink: 0;
        margin-top: 3px; cursor: pointer; accent-color: var(--accent);
    }
    .le-hit-body { flex: 1; min-width: 0; }
    .le-hit-name {
        font-weight: 600; font-size: 0.88rem;
        display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
    }
    .le-hit-summary {
        font-size: 0.78rem; color: var(--text-muted);
        margin-top: 3px; line-height: 1.45;
        overflow: hidden; display: -webkit-box;
        -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    }
    .le-hit-score {
        font-size: 0.72rem; color: var(--text-muted);
        font-family: ui-monospace, monospace;
        flex-shrink: 0; padding-top: 3px;
    }

    .le-score-bar {
        display: inline-block;
        height: 3px; border-radius: 2px;
        background: var(--accent); opacity: 0.5;
        vertical-align: middle; margin-left: 4px;
        flex-shrink: 0;
    }

    .le-type-pill {
        font-size: 0.68rem; font-weight: 700;
        padding: 1px 6px; border-radius: 8px; white-space: nowrap;
    }
    .le-pill-character  { background:rgba(59,130,246,.12);  color:var(--accent); border:1px solid rgba(59,130,246,.25); }
    .le-pill-episode    { background:rgba(139,92,246,.12);  color:#8b5cf6;       border:1px solid rgba(139,92,246,.25); }
    .le-pill-location   { background:rgba(16,185,129,.12);  color:#10b981;       border:1px solid rgba(16,185,129,.25); }
    .le-pill-overview   { background:rgba(245,158,11,.12);  color:#f59e0b;       border:1px solid rgba(245,158,11,.25); }
    .le-pill-general    { background:rgba(100,116,139,.12); color:var(--text-muted); border:1px solid var(--border); }

    .le-status-dot {
        display: inline-block; width: 7px; height: 7px;
        border-radius: 50%; flex-shrink: 0; margin-top: 5px;
    }
    .le-dot-filled  { background: #238636; }
    .le-dot-partial { background: #f59e0b; }
    .le-dot-stub    { background: #6b7280; }
    .le-dot-empty   { background: var(--border); }

    .le-sem-footer {
        padding: 12px 20px;
        border-top: 1px solid var(--border);
        display: flex; align-items: center; gap: 10px;
        flex-shrink: 0; flex-wrap: wrap;
    }
    .le-sel-count { font-size: 0.82rem; color: var(--text-muted); flex: 1; }
    .le-content-toggle {
        display: flex; align-items: center; gap: 6px;
        font-size: 0.82rem; color: var(--text-muted); cursor: pointer;
    }
    .le-content-toggle input { accent-color: var(--accent); cursor: pointer; }

    .le-loading-bar {
        height: 2px; background: var(--accent);
        position: absolute; top: 0; left: 0;
        animation: le-load 1.4s ease-in-out infinite;
        display: none;
    }
    @keyframes le-load {
        0%   { width: 0;   left: 0; }
        50%  { width: 60%; left: 20%; }
        100% { width: 0;   left: 100%; }
    }
    .le-hits-header {
        padding: 8px 14px 6px;
        font-size: 0.75rem; font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase; letter-spacing: 0.04em;
        display: flex; align-items: center; gap: 8px;
        border-bottom: 1px solid var(--border);
        position: sticky; top: 0; background: var(--card); z-index: 1;
    }
    .le-select-all-btn {
        background: none; border: none; color: var(--accent);
        font-size: 0.75rem; cursor: pointer; padding: 0;
        font-weight: 600; margin-left: auto;
    }
    .le-select-all-btn:hover { text-decoration: underline; }

    /* btn helpers (mirror KG) */
    .le-btn {
        padding: 7px 14px; border-radius: 6px; border: none; cursor: pointer;
        font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 5px;
        white-space: nowrap; transition: opacity 0.15s;
    }
    .le-btn-primary { background: var(--accent); color: #fff; }
    .le-btn-primary:hover { opacity: 0.88; }
    .le-btn-ghost {
        background: transparent; border: 1px solid var(--border); color: var(--text);
    }
    .le-btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
    .le-btn:disabled { opacity: 0.45; cursor: not-allowed; }
</style>

<div class="search-header">
    <div class="search-container">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" class="search-input" placeholder="Search Lore (e.g. 'history of magic', 'who is the traitor?')...">
    </div>
    
    <div class="header-actions">
        <select id="collectionSelect" class="collection-select" onchange="runSearch(true)">
            <option value="">— collection —</option>
            <?php foreach ($collections as $col): ?>
                <option value="<?= htmlspecialchars($col['name']) ?>">
                    <?= htmlspecialchars($col['name']) ?> (<?= htmlspecialchars($col['type']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button class="action-btn" onclick="openLoreExportModal()" title="Semantic Export — export a focused slice of the lore as JSON">&#x1F4E4; Export</button>
    </div>
</div>

<div id="explorer-content">
    <div style="text-align:center; padding:60px; color:var(--text-muted);">
        <h3>Enter a query to explore the Series Bible</h3>
        <p>Deep Search across Characters, Episodes, Locations, and Lore.</p>
    </div>
</div>

<div class="pagination-container">
    <button id="loadMoreBtn" class="load-more-btn" onclick="loadNextPage()" style="display:none;">Load More Results</button>
</div>

<!-- IFRAME MODAL -->
<div class="modal-backdrop" id="modalBackdrop"></div>
<div class="modal-window" id="modalWindow">
    <button class="modal-close-btn" onclick="closeModal()">&times;</button>

    <!-- TTS Status Indicator -->
    <div id="ttsStatus" class="tts-status">
        <div class="tts-loader"></div>
        <span>Generating audio...</span>
    </div>

    <!-- Speak selection button (parent UI). Clicking will request selection from iframe. -->
    <button id="modalSpeakBtn" class="modal-speak-btn" title="Speak selected or marked text">🔊 Speak selection</button>

    <iframe id="docFrame" src="about:blank"></iframe>
</div>

<!-- Hidden audio player for TTS -->
<audio id="ttsAudioPlayer" style="display:none;"></audio>

<!-- ═══════════════════════════════════════════════
     LORE EXPORT MODAL
     ═══════════════════════════════════════════════ -->
<div class="le-modal-bg" id="le-modal-bg">
    <div class="le-modal">

        <div class="le-header">
            <h3>&#x1F4E4; Export Lore Context</h3>
            <button class="le-close" onclick="closeLoreExportModal()">&#x2715;</button>
        </div>

        <div class="le-panes">

            <!-- ── Semantic Slice (only mode) ── -->
            <div class="le-pane active" id="le-pane-semantic" style="position:relative;">
                <div class="le-loading-bar" id="le-loading-bar"></div>

                <div class="le-sem-top">
                    <p class="le-desc" style="margin:0;">
                        Describe the context you need in plain language. The lore will be searched
                        semantically and the most relevant entities pre-selected for export.
                    </p>
                    <div class="le-query-row">
                        <input
                            type="text"
                            class="le-query-input"
                            id="le-query-input"
                            placeholder="e.g. Eve's transformation into Noctura and the Crown Arc…"
                            onkeydown="if(event.key==='Enter') leRunQuery()"
                        >
                        <select class="le-n-select" id="le-n-select" title="Max results">
                            <option value="10">Top 10</option>
                            <option value="20" selected>Top 20</option>
                            <option value="35">Top 35</option>
                            <option value="50">Top 50</option>
                        </select>
                        <select class="le-col-select" id="le-col-select" title="Collection">
                            <option value="">— select collection —</option>
                            <?php foreach ($collections as $col): if ($col['type'] !== 'text') continue; ?>
                                <option value="<?= htmlspecialchars($col['name']) ?>">
                                    <?= htmlspecialchars($col['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="le-btn le-btn-primary" onclick="leRunQuery()" id="le-search-btn">
                            Search
                        </button>
                    </div>
                </div>

                <div class="le-hits-area" id="le-hits-area">
                    <div class="le-hits-empty" id="le-hits-empty">
                        <span style="font-size:2rem;">🧠</span>
                        <span>Describe your context need above</span>
                        <span class="hint">
                            Semantic search ranks lore entities by relevance to your query
                            and pre-selects the most useful ones for export.
                        </span>
                    </div>
                </div>

                <div class="le-sem-footer">
                    <span class="le-sel-count" id="le-sel-count"></span>
                    <label class="le-content-toggle">
                        <input type="checkbox" id="le-sem-with-content">
                        Include lore content
                    </label>
                    <button class="le-btn le-btn-ghost" onclick="closeLoreExportModal()">Cancel</button>
                    <button class="le-btn le-btn-primary" id="le-export-sel-btn"
                            onclick="leDoFocusedExport()" disabled>
                        &#x1F4E5; Export Selected
                    </button>
                </div>
            </div>

        </div><!-- /le-panes -->
    </div><!-- /le-modal -->
</div><!-- /le-modal-bg -->

<?= $eruda ?? '' ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
let searchDebounce;
let currentPage = 1;
let currentResults = [];
let currentAudio = null;

// --- SEARCH LOGIC ---
$('#searchInput').on('input', function() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => runSearch(true), 400);
});

function runSearch(reset = true) {
    const query = $('#searchInput').val().trim();
    const collection = $('#collectionSelect').val();
    
    if (!query) return;

    if (reset) {
        currentPage = 1;
        $('#explorer-content').css('opacity', '0.5');
    }

    $.post('lore_explorer_api.php', {
        action: 'search',
        query: query,
        page: currentPage,
        collection: collection
    }, function(res) {
        $('#explorer-content').css('opacity', '1');
        if (res.ok) {
            if (reset) {
                currentResults = res.items;
                $('#explorer-content').html('');
            } else {
                currentResults = currentResults.concat(res.items);
            }
            
            renderResults(res.items);
            
            if (res.items.length < 20) $('#loadMoreBtn').hide();
            else $('#loadMoreBtn').show();
            
        } else {
            Toast.show('Search failed', 'error');
        }
    }, 'json');
}

function loadNextPage() {
    currentPage++;
    runSearch(false);
}

function renderResults(items) {
    if ((!items || items.length === 0) && currentPage === 1) {
        $('#explorer-content').html('<div style="text-align:center; padding:40px;">No matches found.</div>');
        return;
    }

    let html = '';
    items.forEach(item => {
        let typeClass = 'type-overview';
        if (item.match_type === 'character') typeClass = 'type-character';
        if (item.match_type === 'episode') typeClass = 'type-episode';
        if (item.match_type === 'location') typeClass = 'type-location';

        const simPercent = Math.round(item.relevance * 100);

        // Escape for onclick
        const safeEntity = item.match_entity.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        
        html += `
        <div class="hit-card" onclick="openDoc(${item.doc_id}, '${item.match_type}', '${safeEntity}')">
            <div class="hit-header ${typeClass}">
                <span>${item.match_type}</span>
                <span>${simPercent}% Match</span>
            </div>
            <div class="hit-body">
                <div class="hit-title">${item.match_entity}</div>
                <div class="hit-doc-ref">
                    <span>📄 ${item.title}</span>
                    <span style="opacity:0.6;">• ${item.category}</span>
                </div>
                <div class="hit-snippet">${escapeHtml(item.snippet)}</div>
            </div>
            <div class="hit-footer">
                <span>Click for Full Context</span>
                <span>ID: ${item.doc_id}</span>
            </div>
        </div>`;
    });
    
    if (currentPage === 1) {
        $('#explorer-content').html('<div class="lore-grid">' + html + '</div>');
    } else {
        $('.lore-grid').append(html);
    }
}

function openDoc(docId, matchType, matchEntity) {
    $('#modalBackdrop').show();
    $('#modalWindow').css('display', 'flex');
    
    const url = `view_curated_docs.php?doc_id=${docId}&embed=1&focus_type=${matchType}&focus_entity=${encodeURIComponent(matchEntity)}`;
    
    $('#docFrame').attr('src', url);
}

function closeModal() {
    $('#modalBackdrop').hide();
    $('#modalWindow').hide();
    $('#docFrame').attr('src', 'about:blank');
    stopTTS();
}

function escapeHtml(text) { return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;"); }

window.addEventListener('keydown', e => { if(e.key === 'Escape') { closeModal(); closeLoreExportModal(); } });


/* ---------------------------
   TTS Integration
   ---------------------------
*/

async function speakWithInlineTTS(text) {
    if (!text || !text.trim()) {
        Toast.show('No text selected', 'warn');
        return;
    }

    const speakBtn = $('#modalSpeakBtn');
    const ttsStatus = $('#ttsStatus');
    const audioPlayer = document.getElementById('ttsAudioPlayer');
    
    stopTTS();
    
    speakBtn.addClass('speaking').html('⏸️ Generating...');
    ttsStatus.show();
    ttsStatus.find('span').text('Generating audio...');

    try {
        const response = await fetch('/api_tts_inline.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text: text, model: 'en_US-libritts_r-medium' })
        });

        const data = await response.json();

        if (data.status === 'success' && data.url) {
            ttsStatus.find('span').text('Playing...');
            audioPlayer.src = data.url;
            currentAudio = audioPlayer;
            
            audioPlayer.play().catch(e => {
                Toast.show("Autoplay blocked - click to play", "warn");
                ttsStatus.hide();
                speakBtn.removeClass('speaking').html('🔊 Speak selection');
            });
            
            speakBtn.html('⏹️ Stop');
            audioPlayer.onended = resetTtsUI;
            audioPlayer.onerror = function() { Toast.show('Audio playback error', 'error'); resetTtsUI(); };
        } else {
            throw new Error(data.message || 'Unknown error');
        }

    } catch (error) {
        Toast.show('Error generating audio: ' + error.message, 'error');
        resetTtsUI();
    }
}

function stopTTS() {
    const audioPlayer = document.getElementById('ttsAudioPlayer');
    if (audioPlayer && !audioPlayer.paused) { audioPlayer.pause(); audioPlayer.currentTime = 0; }
    currentAudio = null;
    resetTtsUI();
}

function resetTtsUI() {
    $('#modalSpeakBtn').removeClass('speaking').html('🔊 Speak selection');
    $('#ttsStatus').hide();
}

function requestIframeSelection() {
    const iframe = document.getElementById('docFrame');
    if (!iframe || !iframe.contentWindow) { Toast.show('Document not loaded', 'error'); return; }
    if (currentAudio && !currentAudio.paused) { stopTTS(); return; }

    try {
        const win = iframe.contentWindow;
        let selText = '';
        if (win && typeof win.getSelection === 'function') {
            try { selText = win.getSelection().toString().trim(); } catch(e) {}
        }
        if (selText) { speakWithInlineTTS(selText); return; }
        try {
            const marks = win.document.querySelectorAll('.marked, .highlight, [data-spw-marked="1"]');
            if (marks && marks.length) {
                const text = Array.from(marks).map(n => n.textContent.trim()).filter(Boolean).join('\n\n');
                if (text) { speakWithInlineTTS(text); return; }
            }
        } catch(err) {}
    } catch(err) {}

    try {
        iframe.contentWindow.postMessage({ type: 'spw_get_selection_request', requestId: Date.now() }, '*');
    } catch(err) {
        Toast.show('Unable to request selection from document', 'error');
    }
}

window.addEventListener('message', (ev) => {
    const msg = ev.data || {};
    if (msg.type === 'spw_selection') {
        const text = (msg.text || '').trim();
        if (!text) { Toast.show('No text in document selection', 'warn'); return; }
        speakWithInlineTTS(text);
    }
}, false);

$(document).ready(function() {
    $('#modalSpeakBtn').on('click', function(e) { e.stopPropagation(); requestIframeSelection(); });
});


/* ═══════════════════════════════════════════════
   LORE EXPORT MODAL
   ═══════════════════════════════════════════════ */

let leCurrentHits = [];
let leSelectedIds = new Set();

function openLoreExportModal() {
    // Pre-fill from main search if values are present
    const mainQuery      = document.getElementById('searchInput').value.trim();
    const mainCollection = document.getElementById('collectionSelect').value;

    if (mainQuery) {
        document.getElementById('le-query-input').value = mainQuery;
    }
    if (mainCollection) {
        document.getElementById('le-col-select').value = mainCollection;
    }

    document.getElementById('le-modal-bg').classList.add('open');
    // Focus the query input so the user can refine or just hit Search
    setTimeout(() => document.getElementById('le-query-input').focus(), 80);
}

function closeLoreExportModal() {
    document.getElementById('le-modal-bg').classList.remove('open');
}

document.getElementById('le-modal-bg').addEventListener('click', function(e) {
    if (e.target === this) closeLoreExportModal();
});

// ── Semantic Query ───────────────────────────────
async function leRunQuery() {
    const query      = document.getElementById('le-query-input').value.trim();
    const collection = document.getElementById('le-col-select').value;

    if (!query) {
        Toast.show('Please enter a search query.', 'warning');
        return;
    }
    if (!collection) {
        Toast.show('Please select a collection before searching.', 'warning');
        document.getElementById('le-col-select').focus();
        return;
    }

    const nResults   = parseInt(document.getElementById('le-n-select').value);
    const loadingBar = document.getElementById('le-loading-bar');
    const searchBtn  = document.getElementById('le-search-btn');
    const hitsArea   = document.getElementById('le-hits-area');

    loadingBar.style.display = 'block';
    searchBtn.disabled = true;
    searchBtn.textContent = '…';

    leCurrentHits = [];
    leSelectedIds = new Set();
    hitsArea.innerHTML = '';

    try {
        const body = new URLSearchParams({
            action:     'lore_semantic_query',
            query:      query,
            n_results:  nResults,
            collection: collection,
        });

        const res  = await fetch('lore_explorer_api.php', { method: 'POST', body });
        const data = await res.json();

        if (!data.ok) {
            hitsArea.innerHTML = `<div class="le-hits-empty">
                <span style="font-size:2rem">⚠️</span>
                <span>Search failed</span>
                <span class="hint">${leEsc(data.error || 'Unknown error')}</span>
            </div>`;
            Toast.show('Search failed: ' + (data.error || ''), 'error');
            return;
        }

        leCurrentHits = data.hits || [];

        if (!leCurrentHits.length) {
            hitsArea.innerHTML = `<div class="le-hits-empty">
                <span style="font-size:2rem">🔍</span>
                <span>No matching documents found</span>
                <span class="hint">Try rephrasing your query or searching all collections.</span>
            </div>`;
            return;
        }

        // Auto-select hits scoring above threshold
        leSelectedIds = new Set(
            leCurrentHits.filter(h => h.score > 0.35).map(h => h.doc_id)
        );

        leRenderHits(leCurrentHits);
        leUpdateSelCount();

    } catch(e) {
        hitsArea.innerHTML = `<div class="le-hits-empty">
            <span style="font-size:2rem">⚠️</span>
            <span>Request error</span>
            <span class="hint">${leEsc(e.message)}</span>
        </div>`;
        Toast.show('Search error: ' + e.message, 'error');
    } finally {
        loadingBar.style.display = 'none';
        searchBtn.disabled = false;
        searchBtn.textContent = 'Search';
    }
}

function leRenderHits(hits) {
    const area = document.getElementById('le-hits-area');
    if (!hits.length) { area.innerHTML = ''; return; }

    const maxScore = hits[0]?.score || 1;

    const header = `<div class="le-hits-header">
        <span>${hits.length} document${hits.length !== 1 ? 's' : ''} ranked by relevance</span>
        <button class="le-select-all-btn" onclick="leToggleAll()">Toggle all</button>
    </div>`;

    const rows = hits.map(hit => {
        const checked  = leSelectedIds.has(hit.doc_id) ? 'checked' : '';
        const selClass = leSelectedIds.has(hit.doc_id) ? 'selected' : '';
        const barW     = Math.max(8, Math.round((hit.score / maxScore) * 60));
        const typeKey  = (hit.match_type || 'general').toLowerCase();
        const dotClass = `le-dot-${hit.content_status}`;
        const summaryText = leEsc(hit.summary || hit.excerpt || '').replace(/^(Node|Entity|Type):[^\n]*\n?/g, '').trim();

        return `<div class="le-hit-row ${selClass}" data-doc-id="${hit.doc_id}"
                     onclick="leToggleHit(${hit.doc_id}, this)">
            <input type="checkbox" class="le-hit-check" ${checked}
                   onclick="event.stopPropagation(); leToggleHit(${hit.doc_id}, this.closest('.le-hit-row'))">
            <span class="le-status-dot ${dotClass}" title="${hit.content_status}"></span>
            <div class="le-hit-body">
                <div class="le-hit-name">
                    ${leEsc(hit.name)}
                    <span class="le-type-pill le-pill-${typeKey}">${leEsc(typeKey)}</span>
                    ${hit.category_name ? `<span style="font-size:0.72rem;color:var(--text-muted);font-weight:400">${leEsc(hit.category_name)}</span>` : ''}
                    <span class="le-score-bar" style="width:${barW}px"></span>
                </div>
                ${summaryText ? `<div class="le-hit-summary">${summaryText}</div>` : ''}
            </div>
            <span class="le-hit-score">${(hit.score * 100).toFixed(0)}%</span>
        </div>`;
    }).join('');

    area.innerHTML = header + rows;
}

function leToggleHit(docId, rowEl) {
    if (leSelectedIds.has(docId)) {
        leSelectedIds.delete(docId);
        rowEl.classList.remove('selected');
        rowEl.querySelector('input[type=checkbox]').checked = false;
    } else {
        leSelectedIds.add(docId);
        rowEl.classList.add('selected');
        rowEl.querySelector('input[type=checkbox]').checked = true;
    }
    leUpdateSelCount();
}

function leToggleAll() {
    const allSelected = leCurrentHits.every(h => leSelectedIds.has(h.doc_id));
    if (allSelected) {
        leSelectedIds.clear();
    } else {
        leCurrentHits.forEach(h => leSelectedIds.add(h.doc_id));
    }
    leRenderHits(leCurrentHits);
    leUpdateSelCount();
}

function leUpdateSelCount() {
    const n   = leSelectedIds.size;
    const btn = document.getElementById('le-export-sel-btn');
    document.getElementById('le-sel-count').textContent =
        n ? `${n} document${n !== 1 ? 's' : ''} selected` : 'No documents selected';
    btn.disabled = n === 0;
}

// ── Focused Export ───────────────────────────────
async function leDoFocusedExport() {
    if (!leSelectedIds.size) return;

    const withContent = document.getElementById('le-sem-with-content').checked;
    const btn         = document.getElementById('le-export-sel-btn');
    btn.disabled      = true;
    btn.textContent   = '⏳ Building…';

    try {
        // Send the full hit objects for selected docs so the API can extract
        // only the matched entities rather than dumping entire documents.
        // leCurrentHits has { doc_id, match_type, match_entity, ... } per hit.
        const selectedHits = leCurrentHits.filter(h => leSelectedIds.has(h.doc_id));

        const payload = {
            action:      'lore_focused_export',
            with_content: withContent ? '1' : '0',
            // Array of {doc_id, match_type, match_entity} — one entry per matched entity
            matched_hits: selectedHits.map(h => ({
                doc_id:       h.doc_id,
                match_type:   h.match_type,
                match_entity: h.match_entity,
            })),
        };

        const res  = await fetch('lore_explorer_api.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const data = await res.json();

        if (!data.ok) throw new Error(data.error || 'Export failed');

        const suffix = withContent ? '_content' : '';
        leTriggerDownload(data.snapshot, `lore_semantic${suffix}_${leDate()}.json`);
        Toast.show(`Exported ${leSelectedIds.size} document${leSelectedIds.size !== 1 ? 's' : ''} ✓`, 'success');
        closeLoreExportModal();

    } catch(e) {
        Toast.show('Export failed: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '&#x1F4E5; Export Selected';
        leUpdateSelCount();
    }
}

// ── Helpers ──────────────────────────────────────
function leTriggerDownload(obj, filename) {
    const blob = new Blob([JSON.stringify(obj, null, 2)], {type: 'application/json'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = filename; a.click();
    URL.revokeObjectURL(url);
}

function leDate() {
    return new Date().toISOString().slice(0, 10);
}

function leEsc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle,
$spw->getProjectPath() . '/templates/gallery.php'
);
?>
