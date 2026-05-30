<?php
// public/narrative_sequencer.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$pageTitle = "Narrative Sequencer 🎬";

// 1. Handle Saving/Updating via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        if ($_POST['action'] === 'save_sequence') {
            $name = $_POST['name'] ?? 'Untitled Sequence';
            $desc = $_POST['description'] ?? '';
            $ids = json_decode($_POST['sketch_ids'] ?? '[]', true);
            $docId = !empty($_POST['linked_doc_id']) ? $_POST['linked_doc_id'] : null;
            $seqId = !empty($_POST['sequence_id']) ? $_POST['sequence_id'] : null;

            if ($seqId) {
                $stmt = $pdo->prepare("UPDATE narrative_sequences SET name=?, description=?, sequence_data=?, linked_doc_id=? WHERE id=?");
                $stmt->execute([$name, $desc, json_encode($ids), $docId, $seqId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO narrative_sequences (name, description, sequence_data, linked_doc_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $desc, json_encode($ids), $docId]);
                $seqId = $pdo->lastInsertId();
            }
            echo json_encode(['status' => 'success', 'id' => $seqId]);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// 2. Fetch Data
// Fetch Lore Docs for Context Selector
$docs = $pdo->query("
    SELECT d.id, d.name, da.entities, da.thematics, da.narrative_utility 
    FROM documentations d 
    JOIN md_doc_analysis da ON d.id = da.doc_id 
    ORDER BY da.narrative_utility DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Sketches with Analysis
// We limit to top 500 for performance, or remove limit if you have a beefy client
$sketches = $pdo->query("
    SELECT s.id, s.name, s.description, sa.overall_quality, sa.entities, sa.thematics, sa.classification,
           (SELECT filename FROM frames WHERE entity_type='sketches' AND entity_id=s.id ORDER BY id DESC LIMIT 1) as thumb
    FROM sketches s
    JOIN sketch_analysis sa ON s.id = sa.sketch_id
    WHERE sa.overall_quality > 0
    ORDER BY sa.overall_quality DESC
    LIMIT 500
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Existing Sequences
$sequences = $pdo->query("SELECT * FROM narrative_sequences ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<!-- Dependencies -->
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>
<!-- SortableJS for Drag and Drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<!-- Swiper for Playback -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<style>
    :root {
        --film-bg: #1a1a1a;
        --film-border: #333;
        --highlight: #8b5cf6;
    }

    /* Layout Grid */
    .sequencer-layout {
        display: grid;
        grid-template-columns: 350px 1fr;
        grid-template-rows: auto 1fr;
        height: calc(100vh - 80px); /* Adjust based on your header */
        gap: 0;
        overflow: hidden;
    }

    /* LEFT SIDEBAR: Source Material */
    .library-panel {
        grid-row: 1 / -1;
        background: var(--card);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .library-header { padding: 15px; border-bottom: 1px solid var(--border); background: var(--bg); }
    .library-scroll { overflow-y: auto; flex: 1; padding: 10px; }
    
    .sketch-item {
        display: flex; gap: 10px; background: var(--bg);
        border: 1px solid var(--border); border-radius: 6px;
        margin-bottom: 8px; padding: 6px; cursor: grab;
        transition: all 0.2s;
    }
    .sketch-item:hover { border-color: var(--highlight); transform: translateX(2px); }
    .sketch-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; background: #000; }
    .sketch-info { flex: 1; overflow: hidden; }
    .sketch-title { font-size: 0.85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sketch-tags { font-size: 0.7rem; color: var(--text-muted); margin-top: 4px; display:flex; gap:4px; flex-wrap:wrap;}
    .match-pill { background: rgba(139, 92, 246, 0.2); color: #a78bfa; padding: 1px 4px; border-radius: 3px; }

    /* RIGHT TOP: The Timeline (Film Strip) */
    .timeline-panel {
        background: #0f0f0f;
        padding: 20px;
        border-bottom: 1px solid var(--border);
        overflow-x: auto;
        display: flex;
        flex-direction: column;
    }
    .timeline-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; color: #fff; }
    .film-strip-container {
        flex: 1;
        background: #1a1a1a;
        border: 2px dashed #333;
        border-radius: 8px;
        padding: 10px;
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
        min-height: 140px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    /* Items inside the timeline */
    .film-frame {
        width: 120px;
        height: 100px;
        background: #000;
        border: 1px solid #444;
        border-radius: 4px;
        flex-shrink: 0;
        position: relative;
        cursor: grab;
        overflow: hidden;
    }
    .film-frame img { width: 100%; height: 100%; object-fit: cover; opacity: 0.8; }
    .film-frame:hover img { opacity: 1; }
    .film-frame .remove-btn {
        position: absolute; top: 2px; right: 2px;
        background: rgba(0,0,0,0.7); color: #ef4444;
        border: none; border-radius: 50%; width: 20px; height: 20px;
        cursor: pointer; display: none; align-items: center; justify-content: center; font-size: 12px;
    }
    .film-frame:hover .remove-btn { display: flex; }
    .film-counter {
        position: absolute; bottom: 2px; left: 2px;
        background: rgba(0,0,0,0.7); color: white;
        font-size: 0.7rem; padding: 1px 4px; border-radius: 3px;
    }

    /* RIGHT BOTTOM: Context & Controls */
    .context-panel {
        padding: 20px;
        background: var(--bg);
        overflow-y: auto;
    }
    
    .context-selector { margin-bottom: 20px; padding: 15px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; }
    
    .tag-cloud { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .tag-chip { font-size: 0.75rem; padding: 4px 8px; border-radius: 12px; background: rgba(255,255,255,0.05); color: var(--text-muted); border: 1px solid transparent; }
    .tag-chip.active { background: rgba(139, 92, 246, 0.2); color: #a78bfa; border-color: rgba(139, 92, 246, 0.4); }

    /* PLAYER MODAL */
    .player-modal { display: none; position: fixed; inset: 0; background: #000; z-index: 2000; }
    .player-close { position: absolute; top: 20px; right: 20px; color: #fff; font-size: 2rem; z-index: 2002; cursor: pointer; }
    .swiper-slide { display: flex; justify-content: center; align-items: center; background: #000; }
    .slide-content { max-width: 100%; max-height: 100vh; position: relative; }
    .slide-content img { max-width: 100%; max-height: 80vh; object-fit: contain; box-shadow: 0 0 50px rgba(0,0,0,0.5); }
    .slide-caption { 
        position: absolute; bottom: -60px; left: 0; width: 100%; 
        color: #fff; text-align: center; font-family: monospace; padding: 10px;
    }
</style>

<div class="sequencer-layout">
    
    <!-- LEFT: Library -->
    <div class="library-panel">
        <div class="library-header">
            <h3 style="margin:0; font-size:1rem; text-transform:uppercase; letter-spacing:1px;">Media Pool</h3>
            <input type="text" id="librarySearch" placeholder="Search sketches..." style="width:100%; margin-top:10px; padding:6px; background:var(--bg); border:1px solid var(--border); color:var(--text);">
            <div style="display:flex; justify-content:space-between; margin-top:8px; font-size:0.75rem; color:var(--text-muted);">
                <span id="poolCount">0 items</span>
                <span id="matchIndicator" style="display:none; color:var(--highlight);">Context Active</span>
            </div>
        </div>
        <div class="library-scroll" id="sketchLibrary">
            <!-- Sketches Injected Here -->
        </div>
    </div>

    <!-- RIGHT: Workspace -->
    <div class="workspace-wrapper" style="display:flex; flex-direction:column; height:100%; overflow:hidden;">
        
        <!-- TOP: Timeline -->
        <div class="timeline-panel">
            <div class="timeline-header">
                <div style="display:flex; align-items:center; gap:10px;">
                    <input type="text" id="seqName" value="New Sequence" style="background:transparent; border:none; color:#fff; font-size:1.5rem; font-weight:700; width:300px;">
                    <select id="loadSeqSelect" style="background:#333; color:#aaa; border:none; padding:4px;">
                        <option value="">Load Sequence...</option>
                        <?php foreach($sequences as $seq): ?>
                            <option value="<?= $seq['id'] ?>" data-json='<?= $seq['sequence_data'] ?>' data-doc="<?= $seq['linked_doc_id'] ?>" data-desc="<?= htmlspecialchars($seq['description']) ?>"><?= htmlspecialchars($seq['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="btn" onclick="playSequence()" style="background:var(--accent); color:#fff;">▶ Play</button>
                    <button class="btn" onclick="saveSequence()" style="background:#333; color:#fff;">💾 Save</button>
                    <button class="btn" onclick="clearTimeline()" style="background:#333; color:#ef4444;">✕ Clear</button>
                </div>
            </div>
            
            <div class="film-strip-container" id="timelineList">
                <!-- Drop Zone -->
            </div>
            <div style="margin-top:5px; color:#666; font-size:0.8rem; font-family:monospace;">DRAG CLIPS HERE • SORT HORIZONTALLY</div>
        </div>

        <!-- BOTTOM: Context -->
        <div class="context-panel">
            <div class="context-selector">
                <label style="font-weight:700; text-transform:uppercase; font-size:0.8rem; color:var(--accent);">Narrative Context (Lore)</label>
                <select id="loreContext" style="width:100%; padding:8px; margin-top:5px; background:var(--bg); color:var(--text); border:1px solid var(--border);">
                    <option value="">-- No Context (Show All) --</option>
                    <?php foreach($docs as $doc): ?>
                        <option value="<?= $doc['id'] ?>"><?= htmlspecialchars($doc['name']) ?> (Utility: <?= $doc['narrative_utility'] ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div id="activeContextTags" class="tag-cloud"></div>
            </div>

            <textarea id="seqDesc" placeholder="Director's Notes / Voiceover Script..." style="width:100%; height:100px; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:10px;"></textarea>
        </div>
    </div>
</div>

<!-- PLAYER MODAL -->
<div class="player-modal" id="playerModal">
    <div class="player-close" onclick="closePlayer()">×</div>
    <div class="swiper mySwiper" style="width:100%; height:100%;">
        <div class="swiper-wrapper" id="playerSlides">
            <!-- Slides injected via JS -->
        </div>
        <div class="swiper-button-next" style="color:var(--accent);"></div>
        <div class="swiper-button-prev" style="color:var(--accent);"></div>
        <div class="swiper-pagination"></div>
    </div>
</div>

<!-- PAYLOADS -->
<script>
    const sketches = <?= json_encode($sketches) ?>;
    const docs = <?= json_encode($docs) ?>;
    let currentSeqId = null;

    // --- INITIALIZATION ---
    document.addEventListener('DOMContentLoaded', () => {
        renderLibrary(sketches);
        
        // Init Drag and Drop
        // Library (Source)
        new Sortable(document.getElementById('sketchLibrary'), {
            group: { name: 'shared', pull: 'clone', put: false }, // Clone on drag
            sort: false,
            animation: 150
        });

        // Timeline (Target)
        new Sortable(document.getElementById('timelineList'), {
            group: 'shared',
            animation: 150,
            direction: 'horizontal',
            onAdd: function (evt) {
                // Transform the dragged item into a film strip frame
                const item = evt.item;
                const id = item.dataset.id;
                const thumb = item.dataset.thumb;
                
                // Replace the cloned element's HTML with the film frame HTML
                item.className = 'film-frame';
                item.innerHTML = `
                    <img src="${thumb}">
                    <div class="film-counter"></div>
                    <button class="remove-btn" onclick="this.parentElement.remove(); updateTimelineOrder();">×</button>
                `;
                item.removeAttribute('style'); // Remove sortable inline styles
                updateTimelineOrder();
            },
            onUpdate: function () {
                updateTimelineOrder();
            }
        });

        // Context Change Listener
        document.getElementById('loreContext').addEventListener('change', (e) => {
            applyContext(e.target.value);
        });

        // Search Listener
        document.getElementById('librarySearch').addEventListener('input', (e) => {
            filterLibrary(e.target.value);
        });
        
        // Load Listener
        document.getElementById('loadSeqSelect').addEventListener('change', function() {
            if(this.value) loadSequence(this.value);
        });
    });

    // --- LOGIC ---

    function updateTimelineOrder() {
        const frames = document.querySelectorAll('#timelineList .film-frame');
        frames.forEach((el, index) => {
            el.querySelector('.film-counter').innerText = (index + 1);
        });
    }

    function renderLibrary(items) {
        const container = document.getElementById('sketchLibrary');
        container.innerHTML = '';
        document.getElementById('poolCount').innerText = items.length + ' items';

        items.forEach(s => {
            const el = document.createElement('div');
            el.className = 'sketch-item';
            el.dataset.id = s.id;
            el.dataset.thumb = s.thumb ? s.thumb : '/placeholder.png';
            el.dataset.desc = s.description;
            el.dataset.name = s.name;
            
            // Calc match score visual if context is active
            let matchHtml = '';
            if(s.matchScore && s.matchScore > 0) {
                matchHtml = `<span class="match-pill">★ ${s.matchScore}</span>`;
            }

            el.innerHTML = `
                <img src="${el.dataset.thumb}" class="sketch-thumb" loading="lazy">
                <div class="sketch-info">
                    <div class="sketch-title">${matchHtml} ${s.name}</div>
                    <div class="sketch-tags">ID: ${s.id} • Qual: ${parseFloat(s.overall_quality).toFixed(1)}</div>
                </div>
            `;
            container.appendChild(el);
        });
    }

    function applyContext(docId) {
        if (!docId) {
            document.getElementById('activeContextTags').innerHTML = '';
            document.getElementById('matchIndicator').style.display = 'none';
            renderLibrary(sketches); // Reset
            return;
        }

        const doc = docs.find(d => d.id == docId);
        if (!doc) return;

        // Parse Context Tags
        let contextTags = [];
        try {
            const ent = JSON.parse(doc.entities);
            const them = JSON.parse(doc.thematics);
            
            // Extract meaningful keywords
            if(ent.characters) contextTags.push(...ent.characters);
            if(ent.locations) contextTags.push(...ent.locations);
            if(them.mood) contextTags.push(them.mood);
            if(them.themes) contextTags.push(...them.themes);
        } catch(e) {}

        // Clean tags
        contextTags = contextTags.map(t => typeof t === 'string' ? t.toLowerCase() : '').filter(t => t.length > 2);
        
        // Render Tags
        const tagCloud = document.getElementById('activeContextTags');
        tagCloud.innerHTML = contextTags.map(t => `<span class="tag-chip active">${t}</span>`).slice(0, 15).join('') + (contextTags.length > 15 ? '...' : '');
        document.getElementById('matchIndicator').style.display = 'inline';
        document.getElementById('matchIndicator').innerText = `Filtering by "${doc.name}"`;

        // Score Sketches
        const scored = sketches.map(s => {
            let score = 0;
            // Cheap search in JSON strings
            const hay = (JSON.stringify(s.entities) + " " + JSON.stringify(s.thematics) + " " + s.description).toLowerCase();
            
            contextTags.forEach(tag => {
                if(hay.includes(tag)) score++;
            });
            
            return { ...s, matchScore: score };
        });

        // Sort by Score descending
        scored.sort((a, b) => b.matchScore - a.matchScore);
        
        renderLibrary(scored);
    }

    function filterLibrary(query) {
        if(!query) {
            // If context active, re-apply context sort, else raw list
            const docId = document.getElementById('loreContext').value;
            applyContext(docId);
            return;
        }
        
        const lower = query.toLowerCase();
        const filtered = sketches.filter(s => s.name.toLowerCase().includes(lower) || s.description.toLowerCase().includes(lower));
        renderLibrary(filtered);
    }

    function getSequenceIds() {
        const ids = [];
        document.querySelectorAll('#timelineList .film-frame').forEach(el => {
            ids.push(el.dataset.id);
        });
        return ids;
    }

    function saveSequence() {
        const ids = getSequenceIds();
        if(ids.length === 0) { showToast("Timeline empty!", "error"); return; }
        
        const name = document.getElementById('seqName').value;
        const desc = document.getElementById('seqDesc').value;
        const docId = document.getElementById('loreContext').value;

        const formData = new FormData();
        formData.append('action', 'save_sequence');
        formData.append('name', name);
        formData.append('description', desc);
        formData.append('sketch_ids', JSON.stringify(ids));
        formData.append('linked_doc_id', docId);
        if(currentSeqId) formData.append('sequence_id', currentSeqId);

        fetch('', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') {
                showToast("Sequence Saved!");
                currentSeqId = data.id;
            } else {
                showToast("Error: " + data.message, "error");
            }
        });
    }

    function loadSequence(seqId) {
        const opt = document.querySelector(`#loadSeqSelect option[value="${seqId}"]`);
        if(!opt) return;
        
        const ids = JSON.parse(opt.dataset.json);
        const docId = opt.dataset.doc;
        const desc = opt.dataset.desc;
        const name = opt.text;

        currentSeqId = seqId;
        document.getElementById('seqName').value = name;
        document.getElementById('seqDesc').value = desc;
        document.getElementById('loreContext').value = docId || "";
        applyContext(docId); // Trigger sorting based on saved context

        const timeline = document.getElementById('timelineList');
        timeline.innerHTML = '';
        
        ids.forEach(id => {
            const raw = sketches.find(s => s.id == id);
            if(raw) {
                const el = document.createElement('div');
                el.className = 'film-frame';
                el.dataset.id = raw.id;
                el.dataset.thumb = raw.thumb;
                el.dataset.desc = raw.description;
                el.dataset.name = raw.name;
                el.innerHTML = `
                    <img src="${raw.thumb}">
                    <div class="film-counter"></div>
                    <button class="remove-btn" onclick="this.parentElement.remove(); updateTimelineOrder();">×</button>
                `;
                timeline.appendChild(el);
            }
        });
        updateTimelineOrder();
    }

    function clearTimeline() {
        if(confirm("Clear timeline?")) {
            document.getElementById('timelineList').innerHTML = '';
            currentSeqId = null;
            document.getElementById('seqName').value = "New Sequence";
            document.getElementById('seqDesc').value = "";
        }
    }

    // --- PLAYER ---
    let swiper = null;

    function playSequence() {
        const frames = document.querySelectorAll('#timelineList .film-frame');
        if(frames.length === 0) return;

        const wrapper = document.getElementById('playerSlides');
        wrapper.innerHTML = '';

        frames.forEach(f => {
            const img = f.querySelector('img').src;
            const desc = f.dataset.desc || f.dataset.name;
            
            const slide = document.createElement('div');
            slide.className = 'swiper-slide';
            slide.innerHTML = `
                <div class="slide-content">
                    <img src="${img}">
                    <div class="slide-caption">${desc}</div>
                </div>
            `;
            wrapper.appendChild(slide);
        });

        document.getElementById('playerModal').style.display = 'block';

        if(swiper) swiper.destroy();
        swiper = new Swiper(".mySwiper", {
            pagination: { el: ".swiper-pagination", type: "progressbar" },
            navigation: { nextEl: ".swiper-button-next", prevEl: ".swiper-button-prev" },
            keyboard: true,
            effect: "fade",
            fadeEffect: { crossFade: true }
        });
    }

    function closePlayer() {
        document.getElementById('playerModal').style.display = 'none';
    }

    window.addEventListener('keydown', (e) => {
        if(e.key === 'Escape') closePlayer();
    });

</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');