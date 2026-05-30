<?php
/**
 * AI-Powered Search Component (theme-aware)
 * Updated: Fuzz, KG, Sequences, Docs, Vector categories with sub-options
 * Layout Fix: Wraps sub-options and results in a single absolute dropdown container
 */

global $spw, $pdo, $pdoSys;
if (!isset($spw)) {
    require_once __DIR__ . '/bootstrap.php';
    require __DIR__ . '/env_locals.php';
}
?>
<style>
.ai-search-container {
    position: relative;
    max-width: 700px;
    margin: 3px auto;
    margin-bottom: 3px;
    font-family: var(--font-stack, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
}

.ai-search-wrapper {
    position: relative;
    display: flex;
    gap: 0;
    border: 1px solid var(--border, #e0e0e0);
    border-radius: 4px;
    background: var(--card, #fff);
    transition: border-color 0.2s ease, box-shadow .12s ease;
    z-index: 1001; /* Keeps input above the dropdown visually if needed */
}

.ai-search-wrapper:focus-within {
    border-color: var(--accent, #87CEEB);
    box-shadow: 0 0 0 3px rgba(59,130,246,0.08);
}

.ai-search-category {
    padding: 12px 12px 12px 16px;
    font-size: 14px;
    border: none;
    border-right: 1px solid var(--border, #e0e0e0);
    background: var(--card, #f9f9f9);
    color: var(--text, #333);
    cursor: pointer;
    outline: none;
    border-radius: 4px 0 0 4px;
    font-weight: 500;
    min-width: 120px;
}
.ai-search-category:hover { background: var(--hover, #f0f0f0); }

.ai-search-input-container { flex: 1; position: relative; display:flex; align-items:center; }
.ai-search-input {
    width: 100%;
    padding: 12px 16px;
    font-size: 15px;
    border: none;
    background: transparent;
    color: var(--text, #333);
    outline: none;
}
.ai-search-input::placeholder { color: var(--text-muted, #999); }

.ai-search-button {
    padding: 0 16px;
    border: none;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease;
    border-radius: 0 4px 4px 0;
}
.ai-search-button:hover:not(:disabled) { background: var(--hover, #f9f9f9); }
.ai-search-button:disabled { cursor: not-allowed; opacity: 0.5; }

.ai-search-icon { width:20px; height:20px; }
.ai-search-icon svg { width:100%; height:100%; stroke: var(--text-muted, #666); fill: none; stroke-width:2; stroke-linecap:round; }
.ai-search-button:hover:not(:disabled) .ai-search-icon svg { stroke: var(--accent, #87CEEB); }
.ai-close-icon svg { stroke: #ef4444 !important; }

.ai-search-loading { display:none; }
.ai-search-loading.active { display:block; }
.ai-search-spinner {
    width:20px; height:20px;
    border:2px solid var(--border, #e0e0e0);
    border-top-color: var(--accent, #87CEEB);
    border-radius:50%;
    animation: ai-search-spin 0.8s linear infinite;
}
@keyframes ai-search-spin { to { transform: rotate(360deg); } }

/* ── Dropdown Area (Wraps Subopts + Results) ── */
.ai-search-dropdown-area {
    position: absolute; /* Appended to body via JS */
    z-index: 1500; /* High enough to escape headers, but below modals (2000+) */
    background: var(--card, #fff);
    border: 1px solid var(--border, #e0e0e0);
    border-radius: 4px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
    display: none;
    flex-direction: column;
    max-height: 60vh; /* Safely fits mobile viewport + keyboard */
    overflow-y: auto; /* Entire dropdown area scrolls */
}
.ai-search-dropdown-area.active { display: flex; }

/* ── Sub-option row ── */
.ai-search-subopts {
    display: none;
    gap: 6px;
    padding: 8px 12px;
    background: var(--bg, #f4f4f8);
    flex-wrap: wrap;
    align-items: center;
}
.ai-search-subopts.visible { display: flex; }

.ai-search-subopt-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-muted, #888);
    white-space: nowrap;
    flex-shrink: 0;
}

.ai-search-subselect {
    padding: 5px 8px;
    font-size: 12px;
    border: 1px solid var(--border, #e0e0e0);
    border-radius: 4px;
    background: var(--card, #fff);
    color: var(--text, #333);
    outline: none;
    cursor: pointer;
    flex: 1;
    min-width: 140px;
    max-width: 280px;
}
.ai-search-subselect:focus { border-color: var(--accent, #87CEEB); }
.ai-search-subselect:disabled { opacity: 0.45; cursor: not-allowed; }

/* ── Vector offline banner ── */
.ai-search-vector-banner {
    display: none;
    padding: 8px 12px;
    font-size: 12px;
    border-radius: 4px;
    margin-top: 4px;
    align-items: center;
    gap: 8px;
    width: 100%;
}
.ai-search-vector-banner.offline {
    display: flex;
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.3);
    color: var(--red, #ef4444);
}
.ai-search-vector-banner.checking {
    display: flex;
    background: rgba(245,158,11,0.08);
    border: 1px solid rgba(245,158,11,0.25);
    color: var(--amber, #f59e0b);
}
.ai-search-vector-banner.online {
    display: flex;
    background: rgba(20,184,166,0.08);
    border: 1px solid rgba(20,184,166,0.25);
    color: var(--teal, #14b8a6);
}
.ai-search-vector-banner-dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
    background: currentColor;
}
.ai-search-vector-banner.checking .ai-search-vector-banner-dot {
    animation: ai-search-spin 1s linear infinite;
    border: 2px solid currentColor;
    border-top-color: transparent;
    background: transparent;
    width: 9px; height: 9px;
}

/* ── Results dropdown ── */
.ai-search-results {
    display:none;
    background: transparent;
}
.ai-search-results.active { display:block; }

.ai-search-result-item {
    padding: 12px 16px;
    border-bottom: 1px solid rgba(0,0,0,0.04);
    cursor: pointer;
    transition: background 0.12s ease;
    display:flex;
    gap:12px;
    align-items:center;
    color: var(--text, #333);
    background: transparent;
}
.ai-search-result-item:last-child { border-bottom: none; }
.ai-search-result-item:hover { background: var(--hover, #f9f9f9); }

.ai-search-result-thumbnail {
    width:48px; height:48px; flex-shrink:0; border-radius:3px; overflow:hidden;
    background: var(--bg, #f0f0f0);
    border: 1px solid var(--border, #e0e0e0);
}
.ai-search-result-thumbnail img { width:100%; height:100%; object-fit:cover; }

.ai-search-result-content { flex:1; min-width:0; }
.ai-search-result-title { font-size:14px; font-weight:500; color:var(--text, #333); margin-bottom:4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ai-search-result-meta { font-size:12px; color:var(--text-muted, #666); display:flex; align-items:center; gap:8px; flex-wrap: wrap; }
.ai-search-result-table { display:inline-block; padding:2px 6px; background: var(--card, #f0f0f0); border-radius:3px; font-size:11px; color: var(--text-muted, #555); }
.ai-search-result-score { display:inline-block; padding:2px 6px; background: rgba(139,92,246,0.12); border-radius:3px; font-size:11px; color: var(--purple, #8b5cf6); }

/* Per-result open-full-view button */
.ais-result-full-btn {
    flex-shrink: 0;
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 4px;
    border: 1px solid var(--border, #e0e0e0);
    background: transparent;
    color: var(--text-muted, #888);
    font-size: 13px;
    text-decoration: none;
    transition: background 0.12s, color 0.12s, border-color 0.12s;
    opacity: 0;
    transition: opacity 0.15s, background 0.12s, color 0.12s;
}
.ai-search-result-item:hover .ais-result-full-btn { opacity: 1; }
.ais-result-full-btn:hover {
    background: var(--accent, #87CEEB);
    border-color: var(--accent, #87CEEB);
    color: #000;
}

.ai-search-no-results { padding:20px 16px; text-align:center; color:var(--text-muted, #999); font-size:14px; }
.ai-search-error { padding:12px 16px; background: var(--card, #fff5f5); color: var(--red, #c53030); font-size:13px; border-left: 3px solid var(--red, #ef4444); }
.ai-search-thinking { padding:12px 16px; color:var(--text-muted, #666); font-size:13px; display:flex; align-items:center; gap:8px; }
.ai-search-thinking-dot { width:4px; height:4px; background: var(--accent, #87CEEB); border-radius:50%; animation: ai-search-thinking 1.4s ease-in-out infinite; }
@keyframes ai-search-thinking { 0%,60%,100%{ opacity:0.3; transform:scale(0.8);} 30%{opacity:1; transform:scale(1.1);} }

/* ── Result iframe modal ── */
.ais-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.92);
    z-index: 100000; display: none; align-items: center; justify-content: center;
}
.ais-modal-backdrop.active { display: flex; }
.ais-modal-content {
    width: 95vw; height: 95vh;
    background: #000;
    position: relative;
    border: 1px solid var(--border, #333);
    box-shadow: 0 0 40px rgba(0,0,0,0.6);
    border-radius: 4px;
    overflow: hidden;
}
.ais-modal-toolbar {
    position: absolute; top: 10px; right: 10px;
    display: flex; gap: 6px; z-index: 200;
}
.ais-modal-btn {
    width: 32px; height: 32px;
    background: rgba(0,0,0,0.75);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 4px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 15px;
    transition: background 0.15s, border-color 0.15s;
    text-decoration: none;
}
.ais-modal-btn:hover { background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.5); color: #fff; }
.ais-modal-btn.close-btn:hover { background: rgba(239,68,68,0.7); border-color: transparent; }
.ais-modal-iframe { width: 100%; height: 100%; border: none; display: block; }
.ais-modal-loading {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 10px; color: var(--text-muted, #888); font-size: 13px;
    background: var(--bg, #0a0a0f);
    pointer-events: none;
}
.ais-modal-loading.hidden { display: none; }

/* ── Dark Theme Fallback Overrides ── */
[data-theme="dark"] .ai-search-wrapper,
[data-theme="dark"] .ai-search-dropdown-area,
[data-theme="dark"] .ai-search-subselect,
[data-theme="dark"] .ai-search-category {
    background: var(--card, #1e1e1e);
    color: var(--text, #eee);
}
[data-theme="dark"] .ai-search-category:hover,
[data-theme="dark"] .ai-search-button:hover:not(:disabled),
[data-theme="dark"] .ai-search-result-item:hover {
    background: var(--hover, #2d2d30) !important;
}
[data-theme="dark"] .ai-search-subopts {
    background: var(--bg, #121212);
    border-color: var(--border, #333);
}
[data-theme="dark"] .ai-search-result-thumbnail {
    background: var(--bg, #121212);
    border-color: var(--border, #333);
}
[data-theme="dark"] .ai-search-result-table {
    background: var(--bg, #2a2a2a);
    color: var(--text-muted, #ccc);
}
/* Ensure native dropdown options turn dark */
[data-theme="dark"] .ai-search-subselect option,
[data-theme="dark"] .ai-search-category option,
[data-theme="dark"] .ai-search-category optgroup {
    background: var(--card, #1e1e1e);
    color: var(--text, #eee);
}
</style>

<div class="ai-search-container">
    <div class="ai-search-wrapper">
        <select class="ai-search-category" id="aiSearchCategory">
            <option value="general">General</option>
            <option value="frames">Frames</option>
            <option value="sketches">Sketches</option>
            <optgroup label="─ Knowledge ─">
                <option value="docs">Docs</option>
                <option value="kg">KG Nodes</option>
                <option value="sequences">Sequences</option>
                <option value="fuzz">Fuzz</option>
            </optgroup>
            <optgroup label="─ Semantic ─">
                <option value="vector">Vector</option>
            </optgroup>
            <optgroup label="─ Entities ─">
                <option value="characters">Characters</option>
                <option value="locations">Locations</option>
                <option value="backgrounds">Backgrounds</option>
                <option value="artifacts">Artifacts</option>
                <option value="vehicles">Vehicles</option>
                <option value="storyboards">Storyboards</option>
                <option value="todos">Todos</option>
            </optgroup>
            <optgroup label="─ System ─">
                <option value="chat">Chat</option>
                <option value="code">Code</option>
            </optgroup>
        </select>

        <div class="ai-search-input-container">
            <input
                type="text"
                class="ai-search-input"
                id="aiSearchInput"
                placeholder="Enter your search query..."
                autocomplete="off"
            >
        </div>

        <button class="ai-search-button" id="aiSearchButton" type="button">
            <div class="ai-search-icon" id="aiSearchIcon">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M22 2L11 13"></path>
                    <path d="M22 2L15 22L11 13L2 9L22 2Z"></path>
                </svg>
            </div>
            <div class="ai-search-icon ai-close-icon" id="aiCloseIcon" style="display:none;">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M18 6L6 18M6 6l12 12"></path>
                </svg>
            </div>
            <div class="ai-search-loading" id="aiSearchLoading">
                <div class="ai-search-spinner"></div>
            </div>
        </button>
    </div>

    <!-- Dropdown Area encapsulating subopts + results -->
    <div class="ai-search-dropdown-area" id="aiSearchDropdownArea">
        
        <!-- Sub-options row (shown for vector) -->
        <div class="ai-search-subopts" id="aiSearchSubopts">
            <!-- VECTOR sub-options: collection picker + offline banner -->
            <div id="aiSubVector" style="display:none; width:100%;">
                <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                    <span class="ai-search-subopt-label">Collection:</span>
                    <select class="ai-search-subselect" id="aiVectorCollection" disabled>
                        <option value="">Loading collections…</option>
                    </select>
                    <span class="ai-search-subopt-label" id="aiVectorTypeLabel" style="display:none;"></span>
                </div>
                <div class="ai-search-vector-banner checking" id="aiVectorBanner">
                    <div class="ai-search-vector-banner-dot"></div>
                    <span id="aiVectorBannerText">Checking Vector DB connection…</span>
                </div>
            </div>
        </div>

        <!-- Search Results -->
        <div class="ai-search-results" id="aiSearchResults"></div>
        
    </div>
</div>

<!-- AI Search result modal (iframe) -->
<div class="ais-modal-backdrop" id="aisModalBackdrop">
    <div class="ais-modal-content">
        <div class="ais-modal-loading" id="aisModalLoading">
            <div class="ai-search-spinner"></div>
            <span>Loading…</span>
        </div>
        <div class="ais-modal-toolbar">
            <a class="ais-modal-btn" id="aisModalFullLink" href="#" target="_blank" title="Open full view">
                <i class="bi bi-box-arrow-up-right"></i>
            </a>
            <button class="ais-modal-btn close-btn" onclick="aisCloseModal()" title="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <iframe class="ais-modal-iframe" id="aisModalIframe" src="" onload="document.getElementById('aisModalLoading').classList.add('hidden')"></iframe>
    </div>
</div>

<script>
(function() {
    const searchInput    = document.getElementById('aiSearchInput');
    const searchButton   = document.getElementById('aiSearchButton');
    const searchCategory = document.getElementById('aiSearchCategory');
    const dropdownArea   = document.getElementById('aiSearchDropdownArea');
    const searchResults  = document.getElementById('aiSearchResults');
    const searchIcon     = document.getElementById('aiSearchIcon');
    const closeIcon      = document.getElementById('aiCloseIcon');
    const searchLoading  = document.getElementById('aiSearchLoading');
    const suboptsRow     = document.getElementById('aiSearchSubopts');
    const subVector      = document.getElementById('aiSubVector');
    const vectorColSel   = document.getElementById('aiVectorCollection');
    const vectorBanner   = document.getElementById('aiVectorBanner');
    const vectorBannerTxt= document.getElementById('aiVectorBannerText');
    const vectorTypeLabel= document.getElementById('aiVectorTypeLabel');

    let currentRequest   = null;
    let vectorOnline     = false;
    let collectionsLoaded = false;
    let isShowingResults = false;

    // ── Dropdown Visibility Manager ──────────────────────────────────────
    function updateDropdownVisibility() {
        const hasSubopts = suboptsRow.classList.contains('visible');
        const hasResults = searchResults.classList.contains('active');
        
        if (hasSubopts || hasResults) {
            // Move to body to bypass any overflow:hidden mobile headers
            if (dropdownArea.parentNode !== document.body) {
                document.body.appendChild(dropdownArea);
            }
            
            const wrapper = searchInput.closest('.ai-search-wrapper');
            const rect = wrapper.getBoundingClientRect();
            
            // Calculate position relative to the entire document
            dropdownArea.style.top = (window.scrollY + rect.bottom + 4) + 'px';
            dropdownArea.style.left = (window.scrollX + rect.left) + 'px';
            dropdownArea.style.width = rect.width + 'px';
            dropdownArea.classList.add('active');
        } else {
            dropdownArea.classList.remove('active');
        }
        
        // Add a separator line if both are actively showing
        if (hasSubopts && hasResults) {
            suboptsRow.style.borderBottom = '1px solid var(--border, #e0e0e0)';
        } else {
            suboptsRow.style.borderBottom = 'none';
        }
    }

    // Keep position synced if viewport changes (like mobile keyboard opening)
    window.addEventListener('resize', function() {
        if (dropdownArea.classList.contains('active')) updateDropdownVisibility();
    });

    // ── Toggle UI Button States ──────────────────────────────────────────
    function setCloseMode() {
        isShowingResults = true;
        searchIcon.style.display = 'none';
        closeIcon.style.display = 'block';
    }

    function setSearchMode() {
        isShowingResults = false;
        searchIcon.style.display = 'block';
        closeIcon.style.display = 'none';
        searchResults.classList.remove('active');
        updateDropdownVisibility();
    }

    // ── Category change ──────────────────────────────────────────────────
    searchCategory.addEventListener('change', function() {
        const cat = this.value;
        suboptsRow.classList.remove('visible');
        subVector.style.display = 'none';
        searchButton.disabled = false;
        setSearchMode();

        if (cat === 'vector') {
            suboptsRow.classList.add('visible');
            subVector.style.display = 'block';
            searchButton.disabled = true; // disabled until online + collection chosen
            if (!collectionsLoaded) loadVectorCollections();
            else pingVectorDb(); // re-ping on revisit
        }
        updateDropdownVisibility();

        // Update placeholder hint
        const hints = {
            docs: 'Search documentation content…',
            fuzz: 'Search fuzz candidates…',
            kg: 'Search knowledge graph nodes…',
            sequences: 'Search narrative sequences…',
            vector: 'Describe what you\'re looking for semantically…',
            general: 'Enter your search query…'
        };
        searchInput.placeholder = hints[cat] || 'Enter your search query…';
    });

    // ── Load Vector collections from endpoint ────────────────────────────
    function loadVectorCollections() {
        vectorColSel.disabled = true;
        vectorColSel.innerHTML = '<option value="">Loading collections…</option>';
        fetch('ai_search_endpoint.php?action=chroma_collections')
            .then(r => r.json())
            .then(res => {
                vectorColSel.innerHTML = '<option value="">— pick a collection —</option>';
                if (res.collections && res.collections.length) {
                    // Group by type
                    const groups = { text: [], image: [] };
                    res.collections.forEach(c => {
                        (groups[c.type] || groups.text).push(c);
                    });
                    ['text', 'image'].forEach(type => {
                        if (!groups[type].length) return;
                        const grp = document.createElement('optgroup');
                        grp.label = type === 'text' ? '— Text collections —' : '— Image collections —';
                        groups[type].forEach(c => {
                            const o = document.createElement('option');
                            o.value = c.name;
                            o.dataset.type = c.type;
                            o.textContent = c.description ? c.name + ' — ' + c.description.substring(0, 50) : c.name;
                            grp.appendChild(o);
                        });
                        vectorColSel.appendChild(grp);
                    });
                    collectionsLoaded = true;
                }
                pingVectorDb();
            })
            .catch(() => {
                vectorColSel.innerHTML = '<option value="">Error loading collections</option>';
                setVectorBanner('offline');
            });
    }

    // ── Ping Chroma ──────────────────────────────────────────────────────
    function pingVectorDb() {
        setVectorBanner('checking');
        searchButton.disabled = true;
        fetch('ai_search_endpoint.php?action=chroma_ping')
            .then(r => r.json())
            .then(res => {
                if (res.online) {
                    vectorOnline = true;
                    setVectorBanner('online');
                    vectorColSel.disabled = false;
                    updateVectorButtonState();
                } else {
                    vectorOnline = false;
                    setVectorBanner('offline', res.message || 'Vector database is offline.');
                    vectorColSel.disabled = true;
                    searchButton.disabled = true;
                }
            })
            .catch(() => {
                vectorOnline = false;
                setVectorBanner('offline', 'Could not reach Vector database. Check your tablet connection.');
                vectorColSel.disabled = true;
                searchButton.disabled = true;
            });
    }

    function setVectorBanner(state, msg) {
        vectorBanner.className = 'ai-search-vector-banner ' + state;
        const msgs = {
            checking: 'Checking Vector DB connection…',
            online: 'Vector database is online. Select a collection and search.',
            offline: msg || 'Vector database is currently offline. Try again when your tablet is connected.'
        };
        vectorBannerTxt.textContent = msgs[state];
    }

    function updateVectorButtonState() {
        if (searchCategory.value !== 'vector') return;
        const hasCollection = vectorColSel.value && vectorColSel.value !== '';
        searchButton.disabled = !(vectorOnline && hasCollection);
    }

    vectorColSel.addEventListener('change', function() {
        // Show type badge
        const opt = this.options[this.selectedIndex];
        if (opt && opt.dataset.type) {
            vectorTypeLabel.textContent = opt.dataset.type === 'image' ? '🖼 Image embeddings' : '📝 Text embeddings';
            vectorTypeLabel.style.display = 'inline';
        } else {
            vectorTypeLabel.style.display = 'none';
        }
        updateVectorButtonState();
    });

    // ── Search trigger ───────────────────────────────────────────────────
    searchButton.addEventListener('click', function() {
        if (isShowingResults) {
            setSearchMode(); // Closes results and restores original icon
        } else {
            const query = searchInput.value.trim();
            if (query.length >= 2) performSearch(query);
        }
    });

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const query = this.value.trim();
            if (query.length >= 2) performSearch(query);
        } else if (e.key === 'Escape') {
            setSearchMode();
            this.blur();
        }
    });

    // Handle outside clicks to close the dropdown results
    document.addEventListener('click', function(e) {
        if (
            !e.target.closest('.ai-search-container') && 
            !e.target.closest('.ai-search-dropdown-area') && // <-- Prevents closing when clicking results!
            !e.target.closest('.ais-modal-backdrop') &&
            !e.target.closest('.pswp') // Keep open if they are viewing a PhotoSwipe gallery
        ) {
            if (isShowingResults) {
                setSearchMode(); 
            }
        }
    });

    // ── Core search ──────────────────────────────────────────────────────
    function performSearch(query) {
        if (currentRequest) currentRequest.abort();
        const category = searchCategory.value;

        // Build extended payload
        const payload = { query, category };

        if (category === 'vector') {
            if (!vectorOnline) { displayError('Vector database is offline.'); return; }
            payload.vector_collection = vectorColSel.value || null;
            if (!payload.vector_collection) { displayError('Please select a collection first.'); return; }
        }

        searchButton.disabled = true;
        searchIcon.style.display = 'none';
        closeIcon.style.display = 'none';
        searchLoading.classList.add('active');
        searchResults.innerHTML = `
            <div class="ai-search-thinking">
                <span>Searching${category === 'vector' ? ' semantically' : ''}…</span>
                <div class="ai-search-thinking-dots">
                    <span class="ai-search-thinking-dot"></span>
                    <span class="ai-search-thinking-dot"></span>
                    <span class="ai-search-thinking-dot"></span>
                </div>
            </div>
        `;
        searchResults.classList.add('active');
        updateDropdownVisibility();

        currentRequest = new XMLHttpRequest();
        currentRequest.open('POST', 'ai_search_endpoint.php', true);
        currentRequest.setRequestHeader('Content-Type', 'application/json');
        currentRequest.onload = function() {
            searchButton.disabled = (category === 'vector') ? !vectorOnline : false;
            if (category === 'vector') updateVectorButtonState();
            searchLoading.classList.remove('active');
            if (this.status === 200) {
                try { 
                    const response = JSON.parse(this.responseText); 
                    displayResults(response); 
                    setCloseMode(); // Reveal close button 
                } catch (e) { 
                    displayError('Failed to parse search results'); 
                    setSearchMode();
                }
            } else {
                displayError('Search request failed');
                setSearchMode();
            }
            currentRequest = null;
        };
        currentRequest.onerror = function() {
            searchButton.disabled = false;
            searchLoading.classList.remove('active');
            displayError('Network error occurred');
            setSearchMode();
            currentRequest = null;
        };
        currentRequest.send(JSON.stringify(payload));
    }

    // ── Display results ──────────────────────────────────────────────────
    function displayResults(response) {
        if (response.error) { displayError(response.error); return; }
        if (!response.results || response.results.length === 0) {
            searchResults.innerHTML = '<div class="ai-search-no-results">No results found</div>';
            searchResults.classList.add('active');
            updateDropdownVisibility();
            return;
        }
        let html = '';
        response.results.forEach(result => {
            let thumbnailHtml = '';
            if (result.thumbnail) {
                thumbnailHtml = `<div class="ai-search-result-thumbnail"><img src="${escHtml(result.thumbnail)}" alt="" loading="lazy"></div>`;
            }
            const scoreHtml = result.score != null
                ? `<span class="ai-search-result-score">~${Math.round(result.score * 100)}%</span>`
                : '';
            const resultJson = JSON.stringify(result).replace(/"/g, '&quot;');
            const fullUrl    = result.url ? escHtml(result.url) : '';
            html += `
                <div class="ai-search-result-item" style="padding-right:8px;">
                    <div style="flex:1;display:flex;gap:12px;align-items:center;cursor:pointer;min-width:0;" onclick="handleResultClick(${resultJson})">
                        ${thumbnailHtml}
                        <div class="ai-search-result-content">
                            <div class="ai-search-result-title">${escHtml(result.title)}</div>
                            <div class="ai-search-result-meta">
                                <span class="ai-search-result-table">${escHtml(result.table)}</span>
                                ${scoreHtml}
                                ${result.meta ? `<span>${escHtml(result.meta)}</span>` : ''}
                            </div>
                        </div>
                    </div>
                    ${fullUrl ? `<a href="${fullUrl}" target="_blank" class="ais-result-full-btn" title="Open full view" onclick="event.stopPropagation();"><i class="bi bi-box-arrow-up-right"></i></a>` : ''}
                </div>`;
        });
        searchResults.innerHTML = html;
        searchResults.classList.add('active');
        updateDropdownVisibility();
    }

    function displayError(message) {
        searchResults.innerHTML = `<div class="ai-search-error">${escHtml(message)}</div>`;
        searchResults.classList.add('active');
        updateDropdownVisibility();
    }

    function escHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text ?? '');
        return div.innerHTML;
    }

    // ── Modal open/close ─────────────────────────────────────────────────
    const aisBackdrop  = document.getElementById('aisModalBackdrop');
    const aisIframe    = document.getElementById('aisModalIframe');
    const aisFullLink  = document.getElementById('aisModalFullLink');
    const aisLoading   = document.getElementById('aisModalLoading');

    window.aisOpenModal = function(url) {
        aisLoading.classList.remove('hidden');
        aisIframe.src = '';           // reset first so onload fires reliably
        aisFullLink.href = url;
        // slight delay avoids flicker on same-url reloads
        setTimeout(() => { aisIframe.src = url; }, 30);
        aisBackdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    window.aisCloseModal = function() {
        aisBackdrop.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(() => { aisIframe.src = ''; }, 250);
    };

    // Close on backdrop click
    aisBackdrop.addEventListener('mousedown', function(e) {
        if (e.target === aisBackdrop) aisCloseModal();
    });

    // Close on Escape (merged with existing keydown if any)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && aisBackdrop.classList.contains('active')) {
            aisCloseModal();
        }
    });

    // ── Result click → open in modal ─────────────────────────────────────
    window.handleResultClick = function(result) {
        // Dropdown intentionally stays open behind the modal now.

        // Resolve the URL for this result
        let url = result.url || null;

        // Fallbacks for tables that might not have a url yet
        if (!url) {
            if (result.table === 'frames' && result.id) {
                url = `view_frame.php?frame_id=${result.id}&view=modal`;
            } else if (result.table === 'characters' && result.id) {
                url = `entity_form.php?entity_type=characters&entity_id=${result.id}`;
            } else if (result.table === 'sketches' && result.id) {
                url = `entity_form.php?entity_type=sketches&entity_id=${result.id}`;
            } else if (result.table === 'kg_nodes' && result.id) {
                url = `kg_view.php?node_id=${result.id}`;
            }
        }

        if (!url) {
            console.log('No URL for result:', result);
            return;
        }

        // If the page already has the shared frameDetailsModal (e.g. enhanimaticism),
        // reuse it for frame results to stay consistent — otherwise use our own modal.
        if (result.table === 'frames' && result.id && typeof showFrameDetailsModal === 'function') {
            showFrameDetailsModal(result.id);
            return;
        }

        aisOpenModal(url);
    };
})();
</script>