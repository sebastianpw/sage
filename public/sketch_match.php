<?php
// public/sketch_match.php
// SAGE Sketch Match: Drive Lore Discovery via Visual Assets
// Features: Robust Registry-based Selection, Visual Vector Search, TTS Integration, Deep Sketch Inspection, Pagination Input (restored)
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$pageTitle = "Sketch Match 🎨";

// Load available collections for the right-side filter
$collectionsStmt = $pdo->query("SELECT name, type FROM chroma_collections WHERE type='text' ORDER BY name ASC");
$collections = $collectionsStmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<!-- Swiper for Header -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<style>
    :root {
        --story-color: #8b5cf6;
        --world-color: #3b82f6;
        --curator-color: #10b981;
        --draft-color: #f59e0b;
        --fold-bg: rgba(0,0,0,0.02);
        --accent-subtle: rgba(139, 92, 246, 0.1);
        --selected-border: #f43f5e; /* Pink/Red for selection highlight */
    }
    html { font-size: 110%; } 
    body { background: var(--bg); color: var(--text); padding-bottom: 100px; }

    /* --- 1. SKETCH HEADER --- */
    .sketch-header {
        position: sticky; top: 0; z-index: 100;
        background: var(--card); border-bottom: 4px solid var(--border);
        display: flex; flex-direction: column;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .sketch-controls {
        padding: 10px 20px; border-bottom: 1px solid var(--border);
        display: flex; justify-content: space-between; align-items: center;
        background: rgba(0,0,0,0.02);
    }
    .sketch-search {
        display: flex; gap: 10px; align-items: center; flex: 1; max-width: 600px;
    }
    .sketch-input {
        background: var(--bg); border: 1px solid var(--border); color: var(--text);
        padding: 8px 12px; border-radius: 4px; flex: 1; font-size: 0.9rem;
    }
    
    /* Swiper Container */
    .sketch-carousel {
        padding: 15px 0; width: 100%; overflow: hidden;
        background: var(--bg);
    }
    .swiper-slide {
        width: 180px; height: auto;
        transition: transform 0.2s;
    }
    
    /* Sketch Card */
    .s-card {
        background: #000; border: 2px solid transparent; border-radius: 8px;
        overflow: hidden; position: relative; cursor: pointer;
        display: flex; flex-direction: column; transition: all 0.2s;
    }
    .s-card:hover { transform: translateY(-3px); border-color: var(--accent); }
    
    /* SELECTION STATE */
    .s-card.selected { 
        border-color: var(--selected-border); 
        box-shadow: 0 0 15px rgba(244, 63, 94, 0.4); 
        transform: scale(1.05); 
        z-index: 10; 
    }
    .s-card.selected .s-meta {
        background: linear-gradient(to top, rgba(244, 63, 94, 0.9), transparent);
    }
    
    .s-thumb { width: 100%; aspect-ratio: 16/9; object-fit: cover; opacity: 0.9; }
    .s-meta {
        position: absolute; bottom: 0; left: 0; right: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
        padding: 20px 8px 6px 8px; color: #fff; text-shadow: 0 1px 2px #000;
        transition: background 0.3s;
    }
    .s-title { font-size: 0.8rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .s-id { font-size: 0.65rem; opacity: 0.8; font-family: monospace; }
    
    .sketch-pagination {
        display: flex; gap: 10px; align-items: center; font-size: 0.8rem; color: var(--text-muted);
    }
    
    /* Pagination Input Styles */
    .pg-btn { background: var(--card); border: 1px solid var(--border); color: var(--text); cursor: pointer; padding: 4px 10px; border-radius: 4px; }
    .pg-btn:hover:not(:disabled) { background: var(--accent); color: white; border-color: var(--accent); }
    .pg-btn:disabled { opacity: 0.3; cursor: not-allowed; }

    .pg-input-wrapper { display: flex; align-items: center; gap: 5px; background: var(--bg); padding: 2px 8px; border-radius: 20px; border: 1px solid var(--border); }
    .pg-input { 
        width: 45px; background: transparent; border: none; color: var(--text); 
        font-weight: 700; text-align: center; font-size: 0.9rem; padding: 2px 0; 
        border-bottom: 1px solid transparent;
    }
    .pg-input:focus { outline: none; border-bottom-color: var(--accent); }
    .pg-input::-webkit-outer-spin-button, .pg-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

    /* --- 2. QUERY BAR --- */
    .query-bar {
        background: var(--fold-bg); padding: 10px 20px; border-bottom: 1px solid var(--border);
        display: flex; gap: 15px; align-items: center; justify-content: center;
        font-size: 0.9rem; color: var(--text-muted); flex-wrap: wrap;
    }
    .active-query { color: var(--selected-border); font-weight: 700; font-family: monospace; max-width: 50%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    
    /* View Detail Button */
    .view-detail-btn {
        background: rgba(244, 63, 94, 0.1); border: 1px solid rgba(244, 63, 94, 0.3); color: var(--selected-border);
        padding: 4px 12px; border-radius: 4px; font-size: 0.85rem; cursor: pointer; display: none; /* Hidden until selected */
        align-items: center; gap: 6px; font-weight: 700; transition: all 0.2s;
        text-transform: uppercase; letter-spacing: 0.5px;
    }
    .view-detail-btn:hover { background: rgba(244, 63, 94, 0.2); transform: translateY(-1px); }

    /* Collection Select */
    .collection-select {
        background: var(--bg); border: 1px solid var(--border); color: var(--text);
        padding: 6px 12px; border-radius: 4px; font-size: 0.85rem; cursor: pointer;
    }

    /* --- 3. LORE RESULTS --- */
    #explorer-content { padding: 30px; max-width: 1600px; margin: 0 auto; }
    .lore-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
    
    .hit-card {
        background: var(--card); border: 1px solid var(--border); border-radius: 12px;
        overflow: hidden; transition: transform 0.2s; position: relative;
        display: flex; flex-direction: column; cursor: pointer;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .hit-card:hover { transform: translateY(-3px); border-color: var(--accent); box-shadow: var(--card-elevation); }
    
    .hit-header { padding: 10px 15px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; }
    .type-character { background: rgba(59, 130, 246, 0.1); color: var(--world-color); }
    .type-episode { background: rgba(139, 92, 246, 0.1); color: var(--story-color); }
    .type-overview { background: rgba(16, 185, 129, 0.1); color: var(--curator-color); }
    .type-location { background: rgba(245, 159, 11, 0.1); color: var(--draft-color); }
    
    .hit-body { padding: 15px; flex: 1; display:flex; flex-direction:column; gap:8px; }
    .hit-title { font-size: 1.2rem; font-weight: 800; color: var(--text); }
    .hit-doc-ref { font-size: 0.85rem; color: var(--text-muted); }
    .hit-snippet { font-family: 'Courier New', monospace; font-size: 0.85rem; line-height: 1.5; color: var(--text); opacity: 0.9; background: rgba(0,0,0,0.03); padding: 10px; border-radius: 6px; }
    
    /* MODAL */
    .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); z-index: 2000; animation: fadeIn 0.2s; }
    .modal-window { display: none; position: fixed; top: 2vh; bottom: 2vh; left: 50%; transform: translateX(-50%); width: 95%; max-width: 1400px; background: var(--card); border-radius: 12px; z-index: 2001; flex-direction: column; overflow: hidden; animation: slideUp 0.3s; }
    .modal-close-btn { position: absolute; top: -3px; right: -3px; z-index: 3000; background: var(--card); border: 1px solid var(--border); color: var(--text); width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; }
    .iframe-container { width: 100%; height: 100%; border: none; background: var(--bg); }
    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
    @keyframes slideUp { from { transform: translate(-50%, 30px); opacity:0; } to { transform: translate(-50%, 0); opacity:1; } }

    /* TTS */
    .modal-speak-btn {
        position: absolute; top: 15px; right: 84px; z-index: 3000;
        background: var(--card); border: 1px solid var(--border); color: var(--text);
        padding: 8px 12px; border-radius: 8px; cursor: pointer; font-size: 0.95rem;
        display: flex; align-items: center; gap: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        transition: all 0.2s;
    }
    .modal-speak-btn:hover { background: var(--accent); color:white; }
    .modal-speak-btn.speaking { background: #10b981; color: white; }
    
    .tts-status {
        display: none; position: absolute; top: 15px; right: 230px; z-index: 3000;
        background: var(--card); border: 1px solid var(--border);
        padding: 8px 12px; border-radius: 8px; font-size: 0.85rem; color: var(--text-muted);
        align-items: center; gap: 8px;
    }
    .tts-loader { width: 16px; height: 16px; border: 2px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

<!-- 1. HEADER: Sketch Gallery -->
<div class="sketch-header">
    <div class="sketch-controls">
        <div class="sketch-search">
            <span style="font-size:1.2rem;">🎨</span>
            <input type="text" id="sketchFilter" class="sketch-input" placeholder="Filter sketches (ID, Name)...">
        </div>
        <!-- Pagination UI (restored with input) -->
        <div class="sketch-pagination">
            <button class="pg-btn" id="btnPrev" onclick="changePage(-1)" title="Previous Page">←</button>
            <div class="pg-input-wrapper">
                <span>Page</span>
                <input type="number" id="pageInput" class="pg-input" value="1" onchange="jumpToPage(this.value)">
                <span id="pageTotalLabel">of ...</span>
            </div>
            <button class="pg-btn" id="btnNext" onclick="changePage(1)" title="Next Page">→</button>
        </div>
    </div>
    
    <div class="swiper sketch-carousel">
        <div class="swiper-wrapper" id="sketchWrapper">
            <!-- Sketches injected via JS -->
            <div style="padding:20px; color:#666;">Loading sketches...</div>
        </div>
        <div class="swiper-scrollbar"></div>
    </div>
    
    <!-- Active Query Bar -->
    <div class="query-bar">
        <span>Active Query:</span>
        <span id="activeQueryDisplay" class="active-query">None selected</span>
        
        <!-- THE NEW BUTTON -->
        <button id="viewSketchBtn" class="view-detail-btn" onclick="openSketchDetail()">👁️ View Sketch</button>
        
        <div style="flex:1"></div>
        <select id="collectionSelect" class="collection-select" onchange="performLoreSearch()">
            <option value="">All Collections</option>
            <?php foreach ($collections as $col): ?>
                <option value="<?= htmlspecialchars($col['name']) ?>">
                    <?= htmlspecialchars($col['name']) ?> (<?= htmlspecialchars($col['type']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- 2. BODY: Lore Results -->
<div id="explorer-content">
    <div style="text-align:center; padding:60px; color:var(--text-muted);">
        <h3>Select a sketch above to find matching Lore</h3>
        <p>The sketch's metadata will be used as a semantic vector query.</p>
    </div>
</div>

<!-- 3. DOC MODAL -->
<div class="modal-backdrop" id="docModalBackdrop"></div>
<div class="modal-window" id="docModalWindow">
    <button class="modal-close-btn" onclick="closeDocModal()">&times;</button>
    <div id="ttsStatus" class="tts-status"><div class="tts-loader"></div><span>Generating audio...</span></div>
    <button id="modalSpeakBtn" class="modal-speak-btn" title="Speak selected or marked text">🔊 Speak selection</button>
    <iframe id="docFrame" class="iframe-container" src="about:blank"></iframe>
</div>

<!-- 4. SKETCH DETAIL MODAL -->
<div class="modal-backdrop" id="sketchModalBackdrop"></div>
<div class="modal-window" id="sketchModalWindow">
    <button class="modal-close-btn" onclick="closeSketchModal()">&times;</button>
    <iframe id="sketchFrame" class="iframe-container" src="about:blank"></iframe>
</div>

<!-- Hidden audio player for TTS -->
<audio id="ttsAudioPlayer" style="display:none;"></audio>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
    let sketchPage = 1;
    let totalSketchPages = 1;
    let sketchSwiper = null;
    let searchDebounce;
    let activeSketchData = null; 
    let activeSketchId = null; // Track ID for the View Button
    let sketchRegistry = {}; 
    let currentAudio = null;

    // --- SKETCH LOGIC ---
    function loadSketches(page = 1) {
        sketchPage = page;
        const query = $('#sketchFilter').val();
        
        // UI Feedback
        $('#sketchWrapper').css('opacity', '0.5');

        $.post('sketch_match_api.php', {
            action: 'fetch_sketches',
            page: sketchPage,
            query: query
        }, function(res) {
            $('#sketchWrapper').css('opacity', '1');
            if(res.ok) {
                // Safely update total pages if provided by API
                if(res.meta && res.meta.total_pages) {
                    totalSketchPages = res.meta.total_pages;
                } else {
                    totalSketchPages = Math.max(1, totalSketchPages);
                }

                renderSketchSlides(res.items);
                updatePaginationUI();
            }
        }, 'json');
    }

    function updatePaginationUI() {
        $('#pageInput').val(sketchPage);
        $('#pageTotalLabel').text(`of ${totalSketchPages}`);
        $('#btnPrev').prop('disabled', sketchPage <= 1);
        $('#btnNext').prop('disabled', sketchPage >= totalSketchPages);
    }

    // Navigation Helpers
    window.changePage = function(delta) {
        const target = sketchPage + delta;
        if(target >= 1 && target <= totalSketchPages) {
            loadSketches(target);
        }
    };

    window.jumpToPage = function(val) {
        let p = parseInt(val);
        if(isNaN(p)) p = 1;
        if(p < 1) p = 1;
        if(p > totalSketchPages) p = totalSketchPages;
        loadSketches(p);
    };

    function renderSketchSlides(items) {
        const wrapper = $('#sketchWrapper');
        wrapper.empty();
        sketchRegistry = {}; 
        
        if(!items || items.length === 0) {
            wrapper.html('<div style="padding:20px;">No sketches found.</div>');
            return;
        }

        items.forEach(item => {
            sketchRegistry[item.id] = item;
            
            const html = `
                <div class="swiper-slide">
                    <div class="s-card" id="sketch-${item.id}" onclick="selectSketch(${item.id})">
                        <img src="${item.thumb}" class="s-thumb" loading="lazy">
                        <div class="s-meta">
                            <div class="s-title">${escapeHtml(item.name)}</div>
                            <div class="s-id">#${item.id}</div>
                        </div>
                    </div>
                </div>`;
            wrapper.append(html);
        });

        if(sketchSwiper) sketchSwiper.destroy();
        sketchSwiper = new Swiper('.sketch-carousel', {
            slidesPerView: 'auto',
            spaceBetween: 15,
            freeMode: true,
            observer: true,
            observeParents: true,
            scrollbar: { el: '.swiper-scrollbar', hide: true },
            slidesOffsetBefore: 20, slidesOffsetAfter: 20
        });
    }

    // --- SELECTION LOGIC ---
    window.selectSketch = function(id) {
        const data = sketchRegistry[id];
        if(!data) { console.error("Sketch data not found for ID", id); return; }

        activeSketchData = data;
        activeSketchId = id; // Store ID for the View Button
        
        // UI Updates
        $('.s-card').removeClass('selected');
        $(`#sketch-${data.id}`).addClass('selected');
        
        const shortName = data.name.length > 50 ? data.name.substring(0, 47)+'...' : data.name;
        $('#activeQueryDisplay').text(`[#${data.id}] ${shortName}`);
        
        // Show View Button
        $('#viewSketchBtn').fadeIn();
        
        // Trigger Search
        performLoreSearch();
    };

    // --- SKETCH DETAIL MODAL ---
    window.openSketchDetail = function() {
        if(!activeSketchId) return;
        $('#sketchModalBackdrop').show();
        $('#sketchModalWindow').css('display', 'flex');
        
        // Load entity_form in iframe
        const url = `entity_form.php?entity_type=sketches&entity_id=${activeSketchId}&view=modal`;
        $('#sketchFrame').attr('src', url);
    };

    window.closeSketchModal = function() {
        $('#sketchModalBackdrop').hide();
        $('#sketchModalWindow').hide();
        $('#sketchFrame').attr('src', 'about:blank');
    };

    // --- LORE LOGIC ---
    window.performLoreSearch = function() {
        if(!activeSketchData) return;
        $('#explorer-content').css('opacity', '0.5');
        const collection = $('#collectionSelect').val();
        
        $.post('sketch_match_api.php', {
            action: 'search_lore',
            query: activeSketchData.vector_text,
            collection: collection,
            page: 1
        }, function(res) {
            $('#explorer-content').css('opacity', '1');
            if(res.ok) { renderLoreResults(res.items); }
        }, 'json');
    };

    function renderLoreResults(items) {
        const container = $('#explorer-content');
        if(!items || items.length === 0) {
            container.html('<div style="text-align:center; padding:50px;">No Lore matches found for this sketch.</div>');
            return;
        }

        let html = '<div class="lore-grid">';
        
        items.forEach(item => {
            let typeClass = 'type-overview';
            if (item.match_type === 'character') typeClass = 'type-character';
            if (item.match_type === 'episode') typeClass = 'type-episode';
            if (item.match_type === 'location') typeClass = 'type-location';

            const simPercent = Math.round(item.relevance * 100);
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

        html += '</div>';
        container.html(html);
    }

    // --- DOC MODAL UTILS ---
    window.openDoc = function(docId, matchType, matchEntity) {
        $('#docModalBackdrop').show();
        $('#docModalWindow').css('display', 'flex');
        const url = `view_curated_docs.php?doc_id=${docId}&embed=1&focus_type=${matchType}&focus_entity=${encodeURIComponent(matchEntity)}`;
        $('#docFrame').attr('src', url);
    };

    window.closeDocModal = function() {
        $('#docModalBackdrop').hide();
        $('#docModalWindow').hide();
        $('#docFrame').attr('src', 'about:blank');
        stopTTS();
    };
    
    // --- INPUT HANDLERS ---
    $('#sketchFilter').on('input', function() {
        sketchPage = 1;
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => loadSketches(1), 400);
    });

    // Bind Enter key on page input
    $('#pageInput').on('keypress', function(e) {
        if(e.which === 13) {
            jumpToPage($(this).val());
        }
    });

    function escapeHtml(text) { return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;"); }
    window.addEventListener('keydown', e => { 
        if(e.key === 'Escape') {
            closeDocModal();
            closeSketchModal();
        }
    });

    // --- TTS Logic ---
    async function speakWithInlineTTS(text) {
        if (!text || !text.trim()) { Toast.show('No text selected', 'warn'); return; }
        const speakBtn = $('#modalSpeakBtn');
        const ttsStatus = $('#ttsStatus');
        const audioPlayer = document.getElementById('ttsAudioPlayer');
        stopTTS();
        
        speakBtn.addClass('speaking').html('⏸️ Generating...');
        ttsStatus.show(); ttsStatus.find('span').text('Generating audio...');

        try {
            const response = await fetch('api_tts_inline.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text: text, model: 'en_US-libritts_r-medium' })
            });
            const data = await response.json();
            if (data.status === 'success' && data.url) {
                ttsStatus.find('span').text('Playing...');
                audioPlayer.src = data.url;
                currentAudio = audioPlayer;
                audioPlayer.play().catch(e => {
                    Toast.show("Autoplay blocked - click to play", "warn");
                    ttsStatus.hide(); speakBtn.removeClass('speaking').html('🔊 Speak selection');
                });
                speakBtn.html('⏹️ Stop');
                audioPlayer.onended = resetTtsUI;
                audioPlayer.onerror = () => { Toast.show('Audio playback error', 'error'); resetTtsUI(); };
            } else { throw new Error(data.message || 'Unknown error'); }
        } catch (error) {
            console.error('TTS Error:', error);
            Toast.show('Error generating audio', 'error');
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
        if (!iframe || !iframe.contentWindow) return;
        if (currentAudio && !currentAudio.paused) { stopTTS(); return; }
        
        try {
            const win = iframe.contentWindow;
            let selText = win.getSelection ? win.getSelection().toString().trim() : '';
            if(!selText && win.document) {
                const marks = win.document.querySelectorAll('.marked, .highlight, [data-spw-marked="1"]');
                if (marks && marks.length) {
                    selText = Array.from(marks).map(n => n.textContent.trim()).filter(Boolean).join('\n\n');
                }
            }
            if(selText) { speakWithInlineTTS(selText); return; }
            iframe.contentWindow.postMessage({ type: 'spw_get_selection_request', requestId: Date.now() }, '*');
        } catch (err) { 
             iframe.contentWindow.postMessage({ type: 'spw_get_selection_request', requestId: Date.now() }, '*');
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
    
    // Init
    loadSketches(1);

</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle,
    $spw->getProjectPath() . '/templates/gallery.php'
);
?>