<?php
// public/narrative_sequencer.php
// Showrunner V9 - Narrative Sequencer (Compact Cards & Fixed Layout)
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$pageTitle = "Narrative Sequencer 🎬";

// --- 1. HANDLE REQUESTS (Save/Load) ---
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

// --- 2. DATA HARVESTING ---

// Context Docs
$docsRaw = $pdo->query("
    SELECT d.id, d.name, da.entities, da.thematics, da.narrative_utility 
    FROM documentations d 
    JOIN md_doc_analysis da ON d.id = da.doc_id 
    WHERE da.narrative_utility > 0
    ORDER BY da.narrative_utility DESC
")->fetchAll(PDO::FETCH_ASSOC);

$contextDocs = [];
foreach ($docsRaw as $d) {
    $tags = [];
    $ent = json_decode($d['entities'], true) ?? [];
    $them = json_decode($d['thematics'], true) ?? [];

    if (isset($ent['characters'])) foreach($ent['characters'] as $c) $tags[] = $c['name'];
    if (isset($ent['locations'])) foreach($ent['locations'] as $l) $tags[] = $l['name'];
    if (isset($them['mood'])) $tags[] = $them['mood'];
    
    $contextDocs[] = [
        'id' => $d['id'],
        'name' => $d['name'],
        'tags' => array_unique(array_filter($tags))
    ];
}

// Library Sketches
$sketchesRaw = $pdo->query("
    SELECT s.id, s.name, s.description, s.created_at, sa.overall_quality, sa.entities, sa.thematics, sa.classification, sa.scoring, sa.recommendations,
           (SELECT filename FROM frames WHERE entity_type='sketches' AND entity_id=s.id ORDER BY id DESC LIMIT 1) as thumb
    FROM sketches s
    JOIN sketch_analysis sa ON s.id = sa.sketch_id
    WHERE sa.overall_quality > 0
    ORDER BY s.created_at DESC 
    LIMIT 300
")->fetchAll(PDO::FETCH_ASSOC);

$library = [];
foreach($sketchesRaw as $s) {
    $searchable = strtolower($s['name'] . ' ' . $s['description']);
    $ent = json_decode($s['entities'], true);
    if($ent) $searchable .= ' ' . strtolower(json_encode($ent));
    
    // Build Curation Object for Modal
    $curation = [
        'id' => $s['id'],
        'name' => $s['name'],
        'description' => $s['description'],
        'created_at' => $s['created_at'],
        'score' => (float)$s['overall_quality'],
        'class' => json_decode($s['classification'], true),
        'score_breakdown' => json_decode($s['scoring'], true),
        'entities' => json_decode($s['entities'], true),
        'themes' => json_decode($s['thematics'], true),
        'recs' => json_decode($s['recommendations'], true),
        'show' => json_decode($s['showrunner_analysis'] ?? '{}', true) 
    ];

    $library[] = [
        'id' => $s['id'],
        'name' => $s['name'],
        'desc' => $s['description'],
        'thumb' => $s['thumb'] ?? '/placeholder.png',
        'quality' => (float)$s['overall_quality'],
        'search_blob' => $searchable,
        'curation' => $curation 
    ];
}

$sequences = $pdo->query("SELECT * FROM narrative_sequences ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<!-- Dependencies -->
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<style>
    :root {
        --film-bg: #050505;
        --highlight: #8b5cf6;
        --accent-glow: rgba(139, 92, 246, 0.4);
        --handle-color: #f59e0b;
    }
    
    html, body { overflow: hidden; height: 100%; margin:0; }

    /* Layout */
    .sequencer-layout { display: flex; flex-direction: column; height: 100vh; width: 100vw; background: var(--bg); overflow: hidden; }

    /* Top: Timeline (30%) */
    .timeline-area { flex: 0 0 30%; background: var(--film-bg); border-bottom: 4px solid var(--border); display: flex; flex-direction: column; position: relative; box-shadow: 0 5px 20px rgba(0,0,0,0.5); z-index: 10; }
    .timeline-header { padding: 10px 15px; background: rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); height: 50px; }
    .timeline-track-container { flex: 1; overflow-x: auto; overflow-y: hidden; display: flex; align-items: center; padding: 0 15px; background-image: linear-gradient(90deg, transparent 50%, rgba(255,255,255,0.05) 50%), linear-gradient(transparent 50%, rgba(0,0,0,0.5) 50%); background-size: 20px 100%, 100% 4px; }
    .film-strip-list { display: flex; gap: 8px; height: 100%; align-items: center; min-width: 100%; padding: 10px 0; }

    /* Frame Styles */
    .film-frame { height: 110px; aspect-ratio: 16/9; background: #000; border: 2px solid #444; border-radius: 6px; flex-shrink: 0; position: relative; cursor: grab; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: transform 0.2s; }
    .film-frame:active { cursor: grabbing; transform: scale(0.95); }
    .film-frame img { width: 100%; height: 100%; object-fit: cover; opacity: 1; }
    .remove-frame { position: absolute; top: 4px; right: 4px; background: rgba(220, 38, 38, 0.9); color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.5); }
    .frame-ord { position: absolute; bottom: 4px; left: 4px; background: rgba(0,0,0,0.7); color: white; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-family: monospace; }

    /* Middle: Controls */
    .control-strip { padding: 10px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; gap: 10px; overflow-x: auto; align-items: center; flex-shrink: 0; height: 60px; }
    .context-select { padding: 8px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text); flex:1; max-width: 200px; }
    .btn-icon { padding: 8px 12px; border-radius: 6px; cursor: pointer; border: 1px solid var(--border); background: var(--bg); display: flex; align-items: center; gap: 6px; white-space: nowrap; font-size: 0.9rem; }

    /* Bottom: Library */
    .library-area { flex: 1; background: var(--bg); overflow: hidden; position: relative; display: flex; flex-direction: column; }
    .lib-swiper { width: 100%; height: 100%; padding: 20px 0; }
    
    /* FIX: Height auto prevents stretching */
    .swiper-slide { width: 280px; height: auto; display: flex; flex-direction: column; justify-content: center; transition: transform 0.3s; align-self: flex-start; }
    
    /* Library Card (Compact) */
    .lib-card { 
        background: var(--card); border: 1px solid var(--border); border-radius: 10px; 
        overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
        display: flex; flex-direction: column; position: relative; 
        height: auto; /* FIX: Content determines height */
    }
    .lib-card.match { border-color: var(--highlight); box-shadow: 0 0 15px var(--accent-glow); }
    .lib-thumb { width: 100%; aspect-ratio: 16/9; background: #000; position: relative; flex-shrink: 0; }
    .lib-thumb img { width: 100%; height: 100%; object-fit: cover; }
    
    .drag-handle { position: absolute; top: 10px; right: 10px; background: var(--handle-color); color: #fff; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; cursor: grab; box-shadow: 0 4px 8px rgba(0,0,0,0.4); z-index: 20; border: 2px solid rgba(255,255,255,0.2); }
    .drag-handle:active { cursor: grabbing; transform: scale(0.9); background: #d97706; }
    
    /* Compact Meta */
    .lib-meta { padding: 10px; display: block; }
    .lib-title { font-weight: 700; font-size: 0.9rem; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color:var(--text); }
    .lib-id { font-size: 0.7rem; color: var(--text-muted); font-family:monospace; margin-bottom: 0; }
    
    /* Actions tight against content */
    .lib-actions { display: flex; justify-content: space-between; align-items: center; padding-top: 8px; border-top: 1px solid var(--border); gap: 8px; margin-top: 8px; }
    .action-btn { flex: 1; font-size: 0.8rem; padding: 6px 0; border-radius: 6px; border: 1px solid var(--border); cursor: pointer; display: flex; align-items: center; justify-content:center; gap: 6px; background: var(--bg); color: var(--text); transition: background 0.2s; }
    .action-btn:hover { background: var(--accent-subtle); }
    .action-btn.green { border-color: rgba(16, 185, 129, 0.3); color: #10b981; background: rgba(16, 185, 129, 0.05); }

    /* Player Styles */
    .player-modal { display: none; position: fixed; inset: 0; background: #000; z-index: 3000; }
    .player-close { position: absolute; top: 20px; right: 20px; color: #fff; font-size: 2.5rem; z-index: 3005; cursor: pointer; opacity: 0.8; text-shadow: 0 2px 5px #000; }
    .player-swiper .swiper-slide { width: 100%; height: 100%; background: #000; display: flex; justify-content: center; align-items: center; position: relative; }
    .player-img { max-width: 100%; max-height: 100%; object-fit: contain; }
    
    /* Floating Player Controls */
    .player-controls { 
        position: absolute; bottom: 40px; left: 50%; transform: translateX(-50%);
        display: flex; gap: 15px; z-index: 3002;
    }
    .player-btn {
        background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.3);
        padding: 10px 20px; border-radius: 30px; cursor: pointer; backdrop-filter: blur(6px);
        font-weight: 700; display: flex; gap: 8px; align-items: center; transition: all 0.2s;
        font-size: 0.9rem;
    }
    .player-btn:hover { background: rgba(255,255,255,0.1); transform: scale(1.05); border-color: #fff; }
    .player-btn.green { border-color: rgba(16, 185, 129, 0.6); color: #6ee7b7; background: rgba(6, 78, 59, 0.6); }

    /* Swiper Nav */
    .swiper-button-next, .swiper-button-prev { color: var(--highlight); text-shadow: 0 2px 4px #000; }

    /* Modals (Overlay Z-Index > Player) */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 4000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
    .modal-content { background: var(--card); width: 90%; max-width: 600px; max-height: 80vh; overflow-y: auto; padding: 25px; border-radius: 12px; position: relative; border: 1px solid var(--border); box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
    .modal-close { position: absolute; top: 15px; right: 15px; font-size: 1.8rem; cursor: pointer; color: var(--text-muted); line-height: 1; }
    
    .modal-row { margin-bottom: 12px; border-bottom: 1px dashed var(--border); padding-bottom: 8px; display: flex; }
    .modal-label { width: 100px; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); flex-shrink: 0; }
    .pill { display: inline-block; padding: 2px 8px; background: rgba(0,0,0,0.05); border-radius: 12px; font-size: 0.8rem; margin: 2px; }
    .pill-theme { color: #8b5cf6; background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.2); }
    .pill-char { color: #f59e0b; background: rgba(245,159,11,0.1); border: 1px solid rgba(245,159,11,0.2); }
    .pill-func { color: #7c3aed; background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.2); }
    .score-badge { font-weight: 800; font-size: 1.2rem; padding: 4px 10px; border-radius: 6px; color: #fff; }
    .score-high { background: #10b981; } .score-mid { background: #f59e0b; } .score-low { background: #ef4444; }
</style>

<div class="sequencer-layout">

    <!-- 1. TIMELINE AREA -->
    <div class="timeline-area">
        <div class="timeline-header">
            <input type="text" id="seqName" value="Untitled Sequence" style="background:transparent; border:none; color:var(--text); font-weight:700; font-size:1rem; width:150px;">
            <div style="display:flex; gap:10px;">
                <button class="btn-icon" onclick="playSequence()">▶ Play</button>
                <button class="btn-icon" onclick="saveSequence()">💾 Save</button>
                <select id="loadSelect" style="width:20px; opacity:0; position:absolute; right:10px;">
                    <option value="">Load...</option>
                    <?php foreach($sequences as $seq): ?>
                        <option value="<?= $seq['id'] ?>" data-json='<?= $seq['sequence_data'] ?>' data-name="<?= htmlspecialchars($seq['name']) ?>"><?= htmlspecialchars($seq['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-icon" onclick="document.getElementById('loadSelect').showPicker()">📂</button>
            </div>
        </div>
        <div class="timeline-track-container">
            <div class="film-strip-list" id="timelineSortable">
                <div style="color:rgba(255,255,255,0.2); font-size:0.9rem; padding:0 20px; pointer-events:none;" id="emptyMsg">Drag items here</div>
            </div>
        </div>
    </div>

    <!-- 2. CONTROL STRIP -->
    <div class="control-strip">
        <select id="contextDoc" class="context-select">
            <option value="">-- No Context (All) --</option>
            <?php foreach($contextDocs as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <span id="matchBadge" style="font-size:0.8rem; color:var(--highlight); display:none;">Context Active</span>
    </div>

    <!-- 3. LIBRARY AREA -->
    <div class="library-area">
        <div class="swiper lib-swiper">
            <div class="swiper-wrapper" id="libWrapper"></div>
            <div class="swiper-scrollbar"></div>
        </div>
    </div>
</div>

<!-- ANALYSIS MODAL -->
<div id="curation-modal" class="modal-overlay">
    <div class="modal-content">
        <span class="modal-close" onclick="$('#curation-modal').hide()">&times;</span>
        <div id="curation-modal-body"></div>
    </div>
</div>

<!-- DESCRIPTION MODAL -->
<div id="desc-modal" class="modal-overlay">
    <div class="modal-content" style="max-width:500px;">
        <span class="modal-close" onclick="$('#desc-modal').hide()">&times;</span>
        <h3 id="desc-title" style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;"></h3>
        <div id="desc-body" style="font-family:serif; font-size:1.1rem; line-height:1.6; white-space:pre-wrap;"></div>
    </div>
</div>

<!-- PLAYER MODAL -->
<div id="playerModal" class="player-modal">
    <div class="player-close" onclick="closePlayer()">✕</div>
    <div class="swiper player-swiper">
        <div class="swiper-wrapper" id="playerSlides"></div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-pagination"></div>
    </div>
</div>

<script>
    // GLOBAL DATA
    const libraryData = <?= json_encode($library) ?>;
    const contextData = <?= json_encode($contextDocs) ?>;
    let currentSeqId = null;
    let libSwiper = null;
    let playerSwiper = null;

    document.addEventListener('DOMContentLoaded', () => {
        renderLibrary(libraryData);

        // Timeline Sortable
        new Sortable(document.getElementById('timelineSortable'), {
            group: 'shared', animation: 150, direction: 'horizontal',
            onAdd: function (evt) {
                const item = evt.item;
                const d = item.dataset;
                item.className = 'film-frame';
                // Persist Data for Player
                item.dataset.id = d.id;
                item.dataset.name = d.name;
                item.dataset.desc = d.desc;
                
                item.innerHTML = `<img src="${d.thumb}"><div class="remove-frame" onclick="this.parentElement.remove(); updateOrd();">✕</div><div class="frame-ord"></div>`;
                item.style.width = ''; item.style.transform = '';
                document.getElementById('emptyMsg').style.display = 'none';
                updateOrd();
            },
            onUpdate: updateOrd
        });

        document.getElementById('contextDoc').addEventListener('change', applyContext);
        document.getElementById('loadSelect').addEventListener('change', loadSequence);
    });

    function updateOrd() { document.querySelectorAll('.frame-ord').forEach((el, i) => el.innerText = i + 1); }

    function renderLibrary(items) {
        const wrapper = document.getElementById('libWrapper');
        wrapper.innerHTML = '';

        items.forEach(s => {
            const slide = document.createElement('div');
            slide.className = 'swiper-slide';
            // Data for cloning
            slide.dataset.id = s.id; slide.dataset.thumb = s.thumb; slide.dataset.name = s.name; slide.dataset.desc = s.desc;

            // CLEAN CARD: Removed description text, tight layout
            slide.innerHTML = `
                <div class="lib-card ${s.isMatch ? 'match' : ''}">
                    <div class="lib-thumb">
                        <img src="${s.thumb}" loading="lazy">
                        <div class="drag-handle">✋</div>
                    </div>
                    <div class="lib-meta">
                        <div>
                            <div class="lib-title">${s.name}</div>
                            <div class="lib-id">#${s.id}</div>
                        </div>
                        <div class="lib-actions">
                            <button class="action-btn" onclick="openDesc(${s.id})">📖 Read</button>
                            <button class="action-btn green" onclick="openAnalysis(${s.id})">🕵️ Analysis</button>
                        </div>
                    </div>
                </div>
            `;
            wrapper.appendChild(slide);
        });

        if (libSwiper) libSwiper.destroy();
        libSwiper = new Swiper('.lib-swiper', {
            slidesPerView: 'auto', spaceBetween: 20, centeredSlides: true,
            scrollbar: { el: '.swiper-scrollbar' }, freeMode: true, mousewheel: true
        });

        new Sortable(wrapper, {
            group: { name: 'shared', pull: 'clone', put: false }, sort: false, handle: '.drag-handle',
            onClone: function(evt) {
                const s = evt.item;
                evt.clone.dataset.id = s.dataset.id; 
                evt.clone.dataset.thumb = s.dataset.thumb;
                evt.clone.dataset.name = s.dataset.name;
                evt.clone.dataset.desc = s.dataset.desc;
            }
        });
    }

    function applyContext() {
        const docId = document.getElementById('contextDoc').value;
        if(!docId) { document.getElementById('matchBadge').style.display = 'none'; renderLibrary(libraryData); return; }
        
        const doc = contextData.find(d => d.id == docId);
        const keywords = doc.tags.map(t => t.toLowerCase());
        let ranked = libraryData.map(item => {
            let score = 0; keywords.forEach(k => { if (item.search_blob.includes(k)) score++; });
            return { ...item, score, isMatch: score > 0 };
        });
        ranked.sort((a, b) => b.score - a.score || b.quality - a.quality);
        document.getElementById('matchBadge').style.display = 'inline';
        renderLibrary(ranked);
    }

    // --- MODAL OPENERS ---
    window.openAnalysis = function(id) {
        const item = libraryData.find(x => x.id == id);
        if(!item || !item.curation) return;
        const data = item.curation;
        const body = document.getElementById('curation-modal-body');
        const scoreClass = data.score >= 8 ? 'score-high' : (data.score >= 5 ? 'score-mid' : 'score-low');

        let html = `
            <div style="margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:15px;">
                <div style="display:flex; justify-content:space-between; align-items:start;">
                    <div>
                        <h2 style="margin:0; font-size:1.4em;">${data.name}</h2>
                        <div style="font-size:0.85em; color:var(--text-muted); margin-top:4px;">
                            #${data.id} &bull; ${new Date(data.created_at).toLocaleString()}
                        </div>
                    </div>
                    <div class="score-badge ${scoreClass}">${data.score}</div>
                </div>
            </div>`;

        if(data.class) {
            html += `<div class="modal-row"><span class="modal-label">Class</span><div>`;
            if(data.class.narrative_function) html += `<span class="pill pill-func">${data.class.narrative_function}</span> `;
            if(data.class.emotional_tone) html += `<span class="pill">${data.class.emotional_tone}</span>`;
            html += `</div></div>`;
        }
        if(data.themes && data.themes.primary_themes) {
            html += `<div class="modal-row"><span class="modal-label">Themes</span><div>`;
            let themes = Array.isArray(data.themes.primary_themes) ? data.themes.primary_themes : [data.themes.primary_themes];
            themes.forEach(t => html += `<span class="pill pill-theme">${t}</span> `);
            html += `</div></div>`;
        }
        if(data.entities && data.entities.characters) {
            html += `<div class="modal-row"><span class="modal-label">Cast</span><div>`;
            data.entities.characters.forEach(c => html += `<span class="pill pill-char">👤 ${c}</span> `);
            html += `</div></div>`;
        }
        if(data.recs && data.recs.potential_use) {
             html += `<div style="margin:15px 0; background:rgba(245,159,11,0.1); padding:12px; border-radius:6px; border:1px dashed rgba(245,159,11,0.4);">
                        <span class="modal-label" style="color:#d97706; margin-bottom:5px; display:block;">💡 Potential Use</span>
                        <div style="font-size:0.95em;">${data.recs.potential_use}</div>
                      </div>`;
        }
        body.innerHTML = html;
        $('#curation-modal').css('display', 'flex');
    };

    window.openDesc = function(id) {
        const item = libraryData.find(x => x.id == id);
        if(!item) return;
        document.getElementById('desc-title').innerText = item.name;
        document.getElementById('desc-body').innerText = item.desc;
        $('#desc-modal').css('display', 'flex');
    };

    // --- SAVE / LOAD ---
    function saveSequence() {
        const frames = document.querySelectorAll('#timelineSortable .film-frame');
        if (frames.length === 0) { Toast.show("Timeline empty", "error"); return; }
        const ids = Array.from(frames).map(el => el.dataset.id);
        const formData = new FormData();
        formData.append('action', 'save_sequence');
        formData.append('name', document.getElementById('seqName').value);
        formData.append('sketch_ids', JSON.stringify(ids));
        formData.append('linked_doc_id', document.getElementById('contextDoc').value);
        if(currentSeqId) formData.append('sequence_id', currentSeqId);
        fetch('', { method: 'POST', body: formData }).then(r => r.json()).then(d => {
            if(d.status === 'success') { Toast.show("Saved!"); currentSeqId = d.id; }
            else Toast.show(d.message, "error");
        });
    }

    function loadSequence() {
        const opt = this.options[this.selectedIndex];
        if(!opt.value) return;
        const ids = JSON.parse(opt.dataset.json);
        document.getElementById('seqName').value = opt.dataset.name;
        currentSeqId = opt.value;
        const track = document.getElementById('timelineSortable'); track.innerHTML = '';
        ids.forEach(id => {
            const raw = libraryData.find(x => x.id == id);
            if(raw) {
                const el = document.createElement('div'); el.className = 'film-frame'; 
                // Restore data
                el.dataset.id = raw.id; el.dataset.name = raw.name; el.dataset.desc = raw.desc;
                el.innerHTML = `<img src="${raw.thumb}"><div class="remove-frame" onclick="this.parentElement.remove(); updateOrd();">✕</div><div class="frame-ord"></div>`;
                track.appendChild(el);
            }
        });
        document.getElementById('emptyMsg').style.display = 'none'; updateOrd();
    }

    // --- PLAYER ---
    function playSequence() {
        const frames = document.querySelectorAll('#timelineSortable .film-frame');
        if(frames.length === 0) return;
        const wrap = document.getElementById('playerSlides'); wrap.innerHTML = '';
        
        frames.forEach(el => {
            const src = el.querySelector('img').src;
            const id = el.dataset.id;
            
            // Clean view + Floating Control Group with BOTH Buttons
            wrap.innerHTML += `
                <div class="swiper-slide">
                    <img src="${src}" class="player-img">
                    <div class="player-controls">
                        <button class="player-btn" onclick="openDesc(${id})">📖 Info</button>
                        <button class="player-btn green" onclick="openAnalysis(${id})">🕵️ Analysis</button>
                    </div>
                </div>`;
        });
        
        document.getElementById('playerModal').style.display = 'block';
        if(playerSwiper) playerSwiper.destroy();
        playerSwiper = new Swiper('.player-swiper', { 
            navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
            keyboard: true
        });
    }
    
    function closePlayer() { document.getElementById('playerModal').style.display = 'none'; }
    window.addEventListener('click', e => { if (e.target.classList.contains('modal-overlay')) $(e.target).hide(); });
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');