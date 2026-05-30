<?php
// public/paradigm_b.php
require_once __DIR__ . '/bootstrap.php';
$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "Spotting Editor";
ob_start();
?>

<!--
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
-->

<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link href="https://vjs.zencdn.net/8.5.2/video-js.css" rel="stylesheet" />

<style>
/* Forge Design Tokens */
:root {
    --forge-bg:          #080b10;
    --forge-surface:     #0e1319;
    --forge-card:        #111820;
    --forge-border:      #1c2535;
    --forge-text:        #c8d4e8;
    --forge-text-dim:    #5a6a80;
    --forge-amber:       #f5a623;
    --forge-red:         #f05060;
    --forge-green:       #22d3a0;
    --mono: 'Space Mono', 'DM Mono', 'Fira Mono', monospace;
    --forge-radius: 6px;
}

[data-theme="light"], html[data-theme="light"] {
    --forge-bg:          #f6f8fa;
    --forge-surface:     #e1e4e8;
    --forge-card:        #ffffff;
    --forge-border:      #d1d5db;
    --forge-text:        #111827;
    --forge-text-dim:    #4b5563;
    --forge-amber:       #d97706;
    --forge-red:         #dc2626;
    --forge-green:       #059669;
}

/* Base resets for a strict full-screen flex app */
html, body {
    margin: 0; padding: 0;
    background: var(--forge-bg); color: var(--forge-text);
    font-family: var(--mono);
    height: 100%; height: 100dvh;
    overflow: hidden;
}

/* Hide SpwBase default header if it injects one */
header { display: none !important; }

/* --- CORE LAYOUT --- */
.app-container {
    display: flex; flex-direction: column;
    height: 100dvh; width: 100%;
    overflow: hidden;
}

/* --- FORGE HEADER (Drill-down) --- */
.forge-header-bar {
    flex-shrink: 0;
    display: flex; align-items: center; gap: 6px;
    padding: 8px 12px;
    background: var(--forge-surface);
    border-bottom: 2px solid var(--forge-amber);
    margin-left: 70px;
    overflow-x: auto;
    white-space: nowrap;
}
.forge-header-bar::-webkit-scrollbar { display: none; }

.forge-select {
    background: var(--forge-bg);
    border: 1px solid var(--forge-border);
    color: var(--forge-text);
    padding: 6px 8px;
    border-radius: var(--forge-radius);
    font-family: var(--mono);
    font-size: 0.75rem;
    max-width: 90px;
    text-overflow: ellipsis;
    transition: border-color 0.15s ease;
}
.forge-select:focus { outline: none; border-color: var(--forge-amber); }
.forge-select:disabled { opacity: 0.4; }
.forge-sep { color: var(--forge-text-dim); font-size: 0.8rem; }

/* Top Icons */
.top-icon-btn {
    width: 26px; height: 26px;
    border: none; background: transparent;
    color: var(--forge-text-dim);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 1.1rem;
    transition: color 0.15s;
    flex-shrink: 0;
}
.top-icon-btn:hover { color: var(--forge-amber); }
.top-icon-btn:disabled { opacity: 0.3; cursor: default; }
.top-icon-btn:disabled:hover { color: var(--forge-text-dim); }

/* --- VIDEO PLAYER (Sticky Top) --- */
.video-area {
    flex-shrink: 0; width: 100%; aspect-ratio: 16/9;
    background: #000; border-bottom: 1px solid var(--forge-border);
    position: relative; z-index: 10;
}
.video-js { width: 100%; height: 100%; }
.video-placeholder {
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    color: var(--forge-text-dim); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px;
}

/* --- SCRIPT AREA (Scrollable Bottom) --- */
.script-area {
    flex: 1;
    min-height: 0; /* CRITICAL for flexbox scroll containment */
    overflow-y: auto;
    overflow-x: hidden;
    background: var(--forge-bg);
    padding: 16px 12px 60vh; /* Massive bottom padding so last item can scroll to top */
    -webkit-overflow-scrolling: touch; /* Smooth momentum scroll on iOS/Android */
}
.script-area::-webkit-scrollbar { width: 4px; }
.script-area::-webkit-scrollbar-thumb { background: var(--forge-border); border-radius: 4px; }

/* --- SHOT BLOCK --- */
.shot-block {
    background: var(--forge-card);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    padding: 14px 12px;
    margin-bottom: 12px;
    transition: border-color 0.3s;
}
.shot-block.highlighted {
    border-color: var(--forge-amber);
    background: rgba(245,166,35,0.03);
}

/* --- SHOT HEADER ROW --- */
.shot-header-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 12px;
}
.shot-collapse-btn {
    width: 22px; height: 22px;
    border: none; background: transparent;
    color: var(--forge-text-dim);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 0.75rem;
    padding: 0; flex-shrink: 0;
    transition: color 0.2s, transform 0.2s;
}
.shot-collapse-btn:hover { color: var(--forge-amber); }
.shot-collapse-btn.collapsed { transform: rotate(-90deg); }
.shot-heading {
    font-weight: 700; font-size: 0.7rem; color: var(--forge-text-dim);
    letter-spacing: 1px; text-transform: uppercase;
    cursor: pointer; transition: color 0.2s;
    flex: 1; min-width: 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.shot-heading:hover { color: var(--forge-amber); }

.shot-video-details-btn {
    width: 26px; height: 26px;
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    background: var(--forge-surface);
    color: var(--forge-text-dim);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 0.75rem;
    flex-shrink: 0;
    transition: all 0.15s;
}
.shot-video-details-btn:hover { border-color: var(--forge-amber); color: var(--forge-amber); }
.shot-video-details-btn:disabled { opacity: 0.3; cursor: default; }
.shot-video-details-btn:disabled:hover { border-color: var(--forge-border); color: var(--forge-text-dim); }

.shot-audio-btn {
    width: 26px; height: 26px;
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    background: var(--forge-surface);
    color: var(--forge-text-dim);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 0.75rem;
    flex-shrink: 0;
    transition: all 0.15s;
}
.shot-audio-btn:hover { border-color: var(--forge-amber); color: var(--forge-amber); }
.shot-import-btn {
    width: 26px; height: 26px;
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    background: var(--forge-surface);
    color: var(--forge-text-dim);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 0.75rem;
    flex-shrink: 0;
    transition: all 0.15s;
}
.shot-import-btn:hover { border-color: var(--forge-amber); color: var(--forge-amber); }

/* NEW: DAW button — small waveform icon */
.shot-daw-btn {
    width: 26px; height: 26px;
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    background: var(--forge-surface);
    color: var(--forge-text-dim);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 0.75rem;
    flex-shrink: 0;
    transition: all 0.15s;
}
.shot-daw-btn:hover { border-color: var(--forge-amber); color: var(--forge-amber); }

.shot-play-all-btn {
    width: 26px; height: 26px;
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    background: var(--forge-surface);
    color: var(--forge-green);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 0.75rem;
    flex-shrink: 0;
    transition: all 0.15s;
}
.shot-play-all-btn:hover { border-color: var(--forge-green); }
.shot-play-all-btn.playing { border-color: var(--forge-green); background: rgba(34,211,160,0.1); }

/* Collapsible dialogue container */
.shot-dialogues-wrap {
    overflow: hidden;
    transition: max-height 0.25s ease;
}
.shot-dialogues-wrap.collapsed {
    max-height: 0 !important;
}

/* --- DIALOGUE BLOCK --- */
.dialogue-block { margin-bottom: 16px; position: relative; }
.char-name {
    text-align: center; font-weight: 700; font-size: 0.75rem;
    color: var(--forge-amber); text-transform: uppercase; margin-bottom: 4px;
}
.dialogue-text {
    width: 85%; margin: 0 auto; display: block;
    background: transparent; border: 1px solid transparent; color: var(--forge-text);
    font-family: inherit; font-size: 0.85rem; line-height: 1.4; text-align: center;
    resize: none; overflow: hidden; padding: 4px; border-radius: var(--forge-radius);
    transition: border-color 0.2s, background 0.2s;
}
.dialogue-text:focus {
    outline: none;
    border-color: var(--forge-border);
    background: rgba(255,255,255,0.02);
}

/* --- LINE ACTIONS --- */
.line-actions {
    position: absolute; right: 0; top: 50%; transform: translateY(-50%);
    display: flex; flex-direction: column; gap: 4px;
    opacity: 0.3; transition: opacity 0.2s;
}
.dialogue-block:hover .line-actions,
.dialogue-text:focus ~ .line-actions { opacity: 1; }
@media (hover: none) { .line-actions { opacity: 1; } } /* Always visible on mobile touch */

.act-btn {
    width: 26px; height: 26px; border-radius: var(--forge-radius);
    border: 1px solid var(--forge-border);
    background: var(--forge-surface); color: var(--forge-text-dim);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 0.75rem; transition: all 0.15s;
}
.act-btn:hover { border-color: var(--forge-amber); color: var(--forge-amber); }
.act-btn.play-btn { color: var(--forge-green); border-color: var(--forge-border); }
.act-btn.play-btn:hover { border-color: var(--forge-green); }
.act-btn.play-btn.playing { border-color: var(--forge-green); background: rgba(34,211,160,0.1); }
.act-btn.del-btn { color: var(--forge-red); border-color: transparent; }
.act-btn.del-btn:hover { border-color: var(--forge-red); }

/* --- DRAG HANDLE --- */
.drag-handle {
    position: absolute; left: 0; top: 50%; transform: translateY(-50%);
    width: 18px; display: flex; align-items: center; justify-content: center;
    color: var(--forge-text-dim); font-size: 0.75rem;
    cursor: grab; opacity: 0.3; transition: opacity 0.2s;
    touch-action: none; /* Required for pointer events on mobile */
    user-select: none;
}
.dialogue-block:hover .drag-handle { opacity: 1; }
@media (hover: none) { .drag-handle { opacity: 0.5; } }
.drag-handle:active { cursor: grabbing; }

.dialogue-block.drag-over-top { border-top: 2px solid var(--forge-amber); }
.dialogue-block.drag-over-bottom { border-bottom: 2px solid var(--forge-amber); }
.dialogue-block.dragging { opacity: 0.4; }

/* Shift content right to make room for handle */
.dialogue-block { padding-left: 20px; }

/* --- ADD BUTTON --- */
.add-line-btn {
    background: transparent;
    border: 1px dashed var(--forge-text-dim);
    color: var(--forge-text-dim);
    padding: 6px 12px; border-radius: var(--forge-radius);
    font-family: inherit; font-size: 0.7rem;
    cursor: pointer; display: block; margin: 0 auto;
    transition: all 0.2s;
}
.add-line-btn:hover { border-color: var(--forge-amber); color: var(--forge-amber); }

.state-msg {
    text-align: center; color: var(--forge-text-dim);
    margin-top: 40px; font-size: 0.75rem;
    text-transform: uppercase; letter-spacing: 1px;
}
</style>

<div class="app-container">

    <!-- FORGE NAV (Drill Down) -->
    <div class="forge-header-bar">
        <select id="epSelect" class="forge-select" onchange="handleEpChange(this.value)">
            <option value="">Episode...</option>
        </select>
        <span class="forge-sep">›</span>
        <select id="seqSelect" class="forge-select" onchange="handleSeqChange(this.value)" disabled>
            <option value="">Sequence...</option>
        </select>
        <span class="forge-sep">›</span>
        <select id="scnSelect" class="forge-select" onchange="handleScnChange(this.value)" disabled>
            <option value="">Scene...</option>
        </select>

        <div style="margin-left: auto; display: flex; gap: 8px; padding-right: 6px;">
            <button class="top-icon-btn" id="navBackBtn" title="Back to Scene" onclick="navToScene()" disabled><i class="bi bi-arrow-left-circle"></i></button>
            <button class="top-icon-btn" title="Refresh Page" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
    </div>

    <!-- PLAYER -->
    <div class="video-area">
        <div class="video-placeholder" id="vidPlaceholder">No Shot Selected</div>
        <video id="mainPlayer" class="video-js vjs-default-skin vjs-big-play-centered" style="display:none;" controls playsinline preload="auto"></video>
    </div>

    <!-- SCRIPT -->
    <div class="script-area" id="scriptArea">
        <div class="state-msg">Select a hierarchy to begin spotting dialogue.</div>
    </div>
</div>

<script src="https://vjs.zencdn.net/8.5.2/video.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/howler@2.2.4/dist/howler.min.js"></script>
<script src="/js/toast.js"></script>
<script>
let player = null;
let currentHowl = null;
let scriptTimer = null;

// Play-all state
let playAllQueue = [];
let playAllIndex = 0;
let playAllShotId = null;
let playAllActive = false;
let currentSinglePlayBtn = null;

// localStorage key prefix for collapse states
const COLLAPSE_KEY_PREFIX = 'paradigm_b_shot_collapsed_';

function getShotCollapseState(shotId) {
    try { return localStorage.getItem(COLLAPSE_KEY_PREFIX + shotId) === '1'; } catch(e) { return false; }
}
function setShotCollapseState(shotId, collapsed) {
    try { localStorage.setItem(COLLAPSE_KEY_PREFIX + shotId, collapsed ? '1' : '0'); } catch(e) {}
}

// Global exposure for iframe modals to easily force a reload
window.refreshScriptArea = function() {
    const scnId = document.getElementById('scnSelect').value;
    if (scnId) loadScript(scnId);
};

// Init / Deep Linking Check
document.addEventListener('DOMContentLoaded', async () => {
    const urlParams = new URLSearchParams(window.location.search);
    const deepShotId = urlParams.get('shot_id');
    const deepSceneId = urlParams.get('scene_id');

    if (deepShotId) {
        await resolveDeepLink(deepShotId);
    } else if (deepSceneId) {
        await resolveSceneDeepLink(deepSceneId);
    } else {
        await loadEpisodes();
    }
});

// --- ASYNC API FETCHERS ---

async function apiFetch(action, params = {}) {
    const url = new URL('paradigm_b_api.php', window.location.origin);
    url.searchParams.append('action', action);
    for (const [k, v] of Object.entries(params)) url.searchParams.append(k, v);
    const res = await fetch(url);
    return await res.json();
}

async function loadEpisodes() {
    const res = await apiFetch('list_episodes');
    const sel = document.getElementById('epSelect');
    sel.innerHTML = '<option value="">Episode...</option>';
    if (res.success) {
        res.data.forEach(ep => {
            sel.innerHTML += `<option value="${ep.id}">Ep ${ep.number}: ${esc(ep.name)}</option>`;
        });
    }
}

async function loadSequences(epId) {
    const sel = document.getElementById('seqSelect');
    sel.innerHTML = '<option value="">Sequence...</option>';
    sel.disabled = true;
    if (!epId) return;

    const res = await apiFetch('list_sequences', { episode_id: epId });
    if (res.success) {
        res.data.forEach(sq => {
            sel.innerHTML += `<option value="${sq.id}">${esc(sq.name)}</option>`;
        });
        sel.disabled = false;
    }
}

async function loadScenes(seqId) {
    const sel = document.getElementById('scnSelect');
    sel.innerHTML = '<option value="">Scene...</option>';
    sel.disabled = true;
    if (!seqId) return;

    const res = await apiFetch('list_scenes', { sequence_id: seqId });
    if (res.success) {
        res.data.forEach(sc => {
            sel.innerHTML += `<option value="${sc.id}">${esc(sc.name)}</option>`;
        });
        sel.disabled = false;
    }
}

// --- UI HANDLERS ---

async function handleEpChange(epId) {
    document.getElementById('scnSelect').innerHTML = '<option value="">Scene...</option>';
    document.getElementById('scnSelect').disabled = true;
    document.getElementById('navBackBtn').disabled = true;
    document.getElementById('scriptArea').innerHTML = '<div class="state-msg">Select a sequence...</div>';
    await loadSequences(epId);
}

async function handleSeqChange(seqId) {
    document.getElementById('scriptArea').innerHTML = '<div class="state-msg">Select a scene...</div>';
    document.getElementById('navBackBtn').disabled = true;
    await loadScenes(seqId);
}

function handleScnChange(sceneId) {
    const navBtn = document.getElementById('navBackBtn');
    if (sceneId) {
        navBtn.disabled = false;
        loadScript(sceneId);
    } else {
        navBtn.disabled = true;
    }
}

function navToScene() {
    const scnId = document.getElementById('scnSelect').value;
    if (scnId) {
        window.location.href = 'view_editorial_shot.php?scene_id=' + scnId;
    }
}

// --- DEEP LINKING ---

async function resolveDeepLink(shotId) {
    document.getElementById('scriptArea').innerHTML = '<div class="state-msg">Resolving context...</div>';
    const res = await apiFetch('get_shot_context', { shot_id: shotId });

    if (res.success && res.data) {
        const ctx = res.data;

        await loadEpisodes();
        document.getElementById('epSelect').value = ctx.episode_id;

        await loadSequences(ctx.episode_id);
        document.getElementById('seqSelect').value = ctx.sequence_id;

        await loadScenes(ctx.sequence_id);
        document.getElementById('scnSelect').value = ctx.scene_id;
        document.getElementById('navBackBtn').disabled = false;

        await loadScript(ctx.scene_id, shotId);
    } else {
        Toast.show("Shot context not found.", "error");
        await loadEpisodes();
    }
}

async function resolveSceneDeepLink(sceneId) {
    document.getElementById('scriptArea').innerHTML = '<div class="state-msg">Resolving context...</div>';
    const res = await apiFetch('get_scene_context', { scene_id: sceneId });

    if (res.success && res.data) {
        const ctx = res.data;

        await loadEpisodes();
        document.getElementById('epSelect').value = ctx.episode_id;

        await loadSequences(ctx.episode_id);
        document.getElementById('seqSelect').value = ctx.sequence_id;

        await loadScenes(ctx.sequence_id);
        document.getElementById('scnSelect').value = ctx.scene_id;
        document.getElementById('navBackBtn').disabled = false;

        await loadScript(ctx.scene_id);
    } else {
        Toast.show("Scene context not found.", "error");
        await loadEpisodes();
    }
}

// --- SCRIPT & DIALOGUE LOGIC ---

async function loadScript(sceneId, scrollToShotId = null) {
    document.getElementById('scriptArea').innerHTML = '<div class="state-msg"><div class="spinner"></div>Loading script...</div>';
    const res = await apiFetch('load_script', { scene_id: sceneId });

    if (res.success) {
        renderScript(res.data);
        if (scrollToShotId) {
            setTimeout(() => {
                const target = document.getElementById('shot-block-' + scrollToShotId);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    target.classList.add('highlighted');
                    setTimeout(() => target.classList.remove('highlighted'), 2000);
                    const heading = target.querySelector('.shot-heading');
                    if (heading) heading.click(); // Auto-load video
                }
            }, 300);
        }
    }
}

function renderScript(shots) {
    const area = document.getElementById('scriptArea');
    area.innerHTML = '';

    if (shots.length === 0) {
        area.innerHTML = '<div class="state-msg">No shots found in this scene.</div>';
        return;
    }

    shots.forEach(shot => {
        const block = document.createElement('div');
        block.className = 'shot-block';
        block.id = 'shot-block-' + shot.id;

        // --- Shot Header Row ---
        const headerRow = document.createElement('div');
        headerRow.className = 'shot-header-row';

        // Collapse toggle button
        const collapseBtn = document.createElement('button');
        collapseBtn.className = 'shot-collapse-btn';
        collapseBtn.innerHTML = '<i class="bi bi-chevron-down"></i>';
        collapseBtn.title = 'Collapse/expand';
        headerRow.appendChild(collapseBtn);

        // Shot heading (click loads video)
        const hd = document.createElement('div');
        hd.className = 'shot-heading';
        hd.textContent = `SHOT ${shot.id} — ${shot.name}`;
        hd.title = "Click to load video";
        hd.onclick = () => loadVideo(shot.video_url);
        headerRow.appendChild(hd);

        // Video Details button
        const vidDetailsBtn = document.createElement('button');
        vidDetailsBtn.className = 'shot-video-details-btn';
        vidDetailsBtn.innerHTML = '<i class="bi bi-film"></i>';
        vidDetailsBtn.title = 'Shot Video Details';
        if (shot.video_id) {
            vidDetailsBtn.onclick = () => {
                if (window.showVideoDetailsModal) {
                    window.showVideoDetailsModal(shot.video_id);
                }
            };
        } else {
            vidDetailsBtn.disabled = true;
        }
        headerRow.appendChild(vidDetailsBtn);

        // Shot Audio button (opens shot audio reference modal)
        const audioBtn = document.createElement('button');
        audioBtn.className = 'shot-audio-btn';
        audioBtn.innerHTML = '<i class="bi bi-music-note-list"></i>';
        audioBtn.title = 'Shot Audio References';
        audioBtn.onclick = () => {
            if (window.showIframeModal) {
                window.showIframeModal('/paradigm_b_shot_audio.php?shot_id=' + shot.id, 'Shot Audio — #' + shot.id);
            }
        };
        headerRow.appendChild(audioBtn);

        // Import JSON button
        const importBtn = document.createElement('button');
        importBtn.className = 'shot-import-btn';
        importBtn.innerHTML = '<i class="bi bi-box-arrow-in-down"></i>';
        importBtn.title = 'Import Dialogue JSON';
        importBtn.onclick = () => {
            if (window.showIframeModal) {
                window.showIframeModal('/paradigm_b_import.php?shot_id=' + shot.id, 'Import Dialogue JSON');
            }
        };
        headerRow.appendChild(importBtn);

        // DAW button (opens DAW modal for this shot)
        const dawBtn = document.createElement('button');
        dawBtn.className = 'shot-daw-btn';
        dawBtn.innerHTML = '<i class="bi bi-soundwave"></i>';
        dawBtn.title = 'Open in DAW';
        dawBtn.onclick = () => {
            const sceneId = document.getElementById('scnSelect').value;
            if (window.showFullscreenIframeModal) {
                window.showFullscreenIframeModal(
                    '/daw/index.php?scene_id=' + sceneId + '&shot_id=' + shot.id,
                    'DAW — Shot #' + shot.id
                );
            }
        };
        headerRow.appendChild(dawBtn);

        // Play All button
        const playAllBtn = document.createElement('button');
        playAllBtn.className = 'shot-play-all-btn';
        playAllBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
        playAllBtn.title = 'Play all dialogue lines';
        playAllBtn.dataset.shotId = shot.id;
        headerRow.appendChild(playAllBtn);

        block.appendChild(headerRow);

        // --- Collapsible wrapper ---
        const dialoguesWrap = document.createElement('div');
        dialoguesWrap.className = 'shot-dialogues-wrap';

        // Dialogue Lines Container (inside wrap)
        const dlContainer = document.createElement('div');
        shot.dialogues.forEach(line => {
            dlContainer.appendChild(createDialogueElement(line));
        });
        dialoguesWrap.appendChild(dlContainer);
        initDragSort(dlContainer, shot.id);

        // Add Line Button (inside wrap)
        const addBtn = document.createElement('button');
        addBtn.className = 'add-line-btn';
        addBtn.textContent = '+ Add Dialogue';
        addBtn.onclick = () => addLine(shot.id, dlContainer);
        dialoguesWrap.appendChild(addBtn);

        block.appendChild(dialoguesWrap);

        // --- Collapse state init ---
        const isCollapsed = getShotCollapseState(shot.id);
        if (isCollapsed) {
            collapseBtn.classList.add('collapsed');
            dialoguesWrap.classList.add('collapsed');
            dialoguesWrap.style.maxHeight = '0px';
        } else {
            dialoguesWrap.style.maxHeight = 'none';
        }

        // Collapse toggle handler
        collapseBtn.addEventListener('click', () => {
            const nowCollapsed = !collapseBtn.classList.contains('collapsed');
            collapseBtn.classList.toggle('collapsed', nowCollapsed);
            if (nowCollapsed) {
                // Collapsing: set explicit height then animate to 0
                dialoguesWrap.style.maxHeight = dialoguesWrap.scrollHeight + 'px';
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        dialoguesWrap.classList.add('collapsed');
                        dialoguesWrap.style.maxHeight = '0px';
                    });
                });
            } else {
                // Expanding: remove class, set height
                dialoguesWrap.classList.remove('collapsed');
                dialoguesWrap.style.maxHeight = dialoguesWrap.scrollHeight + 'px';
                // After transition, set to 'none' so textareas can resize freely
                dialoguesWrap.addEventListener('transitionend', () => {
                    if (!collapseBtn.classList.contains('collapsed')) {
                        dialoguesWrap.style.maxHeight = 'none';
                    }
                }, { once: true });
            }
            setShotCollapseState(shot.id, nowCollapsed);
        });

        // Play All handler
        playAllBtn.addEventListener('click', () => {
            if (playAllBtn.classList.contains('playing')) {
                stopAnyAudio();
            } else {
                startPlayAll(shot.id, dlContainer, playAllBtn);
            }
        });

        area.appendChild(block);
    });
}

// --- AUDIO PLAYBACK MANAGEMENT ---

function stopAnyAudio() {
    if (currentHowl) { 
        currentHowl.stop(); 
        currentHowl = null; 
    }
    
    // Clear Play-All UI state
    playAllActive = false;
    playAllQueue = [];
    playAllIndex = 0;
    if (window._playAllBtn) {
        window._playAllBtn.classList.remove('playing');
        window._playAllBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
        window._playAllBtn = null;
    }
    
    // Clear Single Play UI state
    if (currentSinglePlayBtn) {
        currentSinglePlayBtn.classList.remove('playing');
        currentSinglePlayBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
        currentSinglePlayBtn = null;
    }
}

function startPlayAll(shotId, dlContainer, btn) {
    // Stop any existing audio globally
    stopAnyAudio();

    // Collect all audio URLs from play buttons in this container, in DOM order
    const playBtns = dlContainer.querySelectorAll('.act-btn.play-btn');
    const urls = [];
    playBtns.forEach(pb => {
        if (pb._audioUrl) urls.push(pb._audioUrl);
    });

    if (urls.length === 0) {
        Toast.show('No audio lines to play in this shot.', 'error');
        return;
    }

    playAllQueue = urls;
    playAllIndex = 0;
    playAllShotId = shotId;
    playAllActive = true;
    
    btn.classList.add('playing');
    btn.innerHTML = '<i class="bi bi-pause-fill"></i>'; // Turn into Pause/Stop UI
    
    // Store btn reference for cleanup
    btn._isPlayAllBtn = true;
    window._playAllBtn = btn;

    playNextInQueue();
}

function playNextInQueue() {
    if (!playAllActive || playAllIndex >= playAllQueue.length) {
        stopAnyAudio();
        return;
    }
    const url = playAllQueue[playAllIndex];
    playAllIndex++;
    if (currentHowl) currentHowl.stop();
    
    currentHowl = new Howl({
        src: [url],
        html5: true,
        onend: () => {
            if (playAllActive) playNextInQueue();
        },
        onloaderror: () => {
            if (playAllActive) playNextInQueue();
        }
    });
    currentHowl.play();
}

function playAudio(url, btn) {
    stopAnyAudio();
    if(!url) return;
    
    // Bind to the current single button
    currentSinglePlayBtn = btn;
    btn.classList.add('playing');
    btn.innerHTML = '<i class="bi bi-pause-fill"></i>';

    currentHowl = new Howl({ 
        src: [url], 
        html5: true,
        onend: () => stopAnyAudio(),
        onloaderror: () => stopAnyAudio()
    });
    currentHowl.play();
}


function createDialogueElement(line) {
    const d = document.createElement('div');
    d.className = 'dialogue-block';
    d.draggable = true;
    d.dataset.dialogueId = line.dialogue_id;

    // Drag Handle
    const handle = document.createElement('div');
    handle.className = 'drag-handle';
    handle.innerHTML = '<i class="bi bi-grip-vertical"></i>';
    handle.title = "Drag to reorder";
    d.appendChild(handle);

    // Character Name (Visual placeholder for now)
    const char = document.createElement('div');
    char.className = 'char-name';
    char.textContent = line.character_id ? `CHAR ${line.character_id}` : 'UNASSIGNED';
    d.appendChild(char);

    // Editable Text
    const txt = document.createElement('textarea');
    txt.className = 'dialogue-text';
    txt.value = line.text_line || '';
    txt.placeholder = 'Type dialogue here...';
    txt.oninput = function() {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
        debounceSave(line.dialogue_id, this.value);
    };
    // Initialize height after DOM insertion
    setTimeout(() => { txt.style.height = 'auto'; txt.style.height = txt.scrollHeight + 'px'; }, 10);
    d.appendChild(txt);

    // Actions
    const acts = document.createElement('div');
    acts.className = 'line-actions';

    if (line.latest_audio_url) {
        const play = document.createElement('button');
        play.className = 'act-btn play-btn';
        play.innerHTML = '<i class="bi bi-play-fill"></i>';
        play.title = "Play Audio";
        // Store URL on element for play-all access
        play._audioUrl = line.latest_audio_url;
        play.onclick = () => {
            if (play.classList.contains('playing')) {
                stopAnyAudio();
            } else {
                playAudio(line.latest_audio_url, play);
            }
        };
        acts.appendChild(play);
    }

    const edit = document.createElement('button');
    edit.className = 'act-btn';
    edit.innerHTML = '🎬';
    edit.title = "Macro Dashboard";
    edit.onclick = () => {
        if (window.showIframeModal) {
            window.showIframeModal('/paradigm_b_dash.php?id=' + line.dialogue_id, 'Dialogue Editor');
        }
    };
    acts.appendChild(edit);

    const del = document.createElement('button');
    del.className = 'act-btn del-btn';
    del.innerHTML = '<i class="bi bi-trash"></i>';
    del.title = "Delete Line";
    del.onclick = () => deleteLine(line.dialogue_id, d);
    acts.appendChild(del);

    d.appendChild(acts);
    return d;
}

function debounceSave(dialogueId, text) {
    clearTimeout(scriptTimer);
    scriptTimer = setTimeout(() => {
        const fd = new URLSearchParams();
        fd.append('action', 'update_line');
        fd.append('dialogue_id', dialogueId);
        fd.append('text', text);
        fetch('paradigm_b_api.php', { method: 'POST', body: fd });
    }, 500);
}

function addLine(shotId, container) {
    const fd = new URLSearchParams();
    fd.append('action', 'add_line');
    fd.append('shot_id', shotId);

    fetch('paradigm_b_api.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.success) {
                const el = createDialogueElement({ dialogue_id: res.dialogue_id, text_line: '', character_id: 0, latest_audio_url: null });
                container.appendChild(el);                
                const txt = el.querySelector('textarea');
                if (txt) txt.focus();
                // Re-expand max-height so collapse animation works correctly after add
                const wrap = container.closest('.shot-dialogues-wrap');
                if (wrap && !wrap.classList.contains('collapsed')) {
                    wrap.style.maxHeight = 'none';
                }
            } else {
                Toast.show("Failed to add line.", "error");
            }
        });
}

function deleteLine(dialogueId, element) {
    if(!confirm("Remove this dialogue line?")) return;
    const fd = new URLSearchParams();
    fd.append('action', 'delete_line');
    fd.append('dialogue_id', dialogueId);
    fetch('paradigm_b_api.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if(res.success) {
                element.remove();
            } else {
                Toast.show("Failed to delete.", "error");
            }
        });
}

// --- MEDIA CONTROLS ---

function loadVideo(url) {
    if (!url) { Toast.show('No video for this shot', 'error'); return; }
    document.getElementById('vidPlaceholder').style.display = 'none';
    const pEl = document.getElementById('mainPlayer');
    pEl.style.display = 'block';
    if (!player) player = videojs('mainPlayer');
    player.src({ type: 'video/mp4', src: '/' + url.replace(/^\//, '') });
    player.play().catch(()=>{});
}

// --- DRAG & DROP REORDER ---

function initDragSort(container, shotId) {
    let dragSrc = null;

    container.addEventListener('dragstart', e => {
        const block = e.target.closest('.dialogue-block');
        if (!block) return;
        dragSrc = block;
        block.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    container.addEventListener('dragend', e => {
        const block = e.target.closest('.dialogue-block');
        if (block) block.classList.remove('dragging');
        container.querySelectorAll('.dialogue-block').forEach(b => {
            b.classList.remove('drag-over-top', 'drag-over-bottom');
        });
        dragSrc = null;
        persistSortOrder(container, shotId);
    });

    container.addEventListener('dragover', e => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const block = e.target.closest('.dialogue-block');
        if (!block || block === dragSrc) return;
        container.querySelectorAll('.dialogue-block').forEach(b => {
            b.classList.remove('drag-over-top', 'drag-over-bottom');
        });
        const rect = block.getBoundingClientRect();
        const mid = rect.top + rect.height / 2;
        block.classList.add(e.clientY < mid ? 'drag-over-top' : 'drag-over-bottom');
    });

    container.addEventListener('dragleave', e => {
        const block = e.target.closest('.dialogue-block');
        if (block) block.classList.remove('drag-over-top', 'drag-over-bottom');
    });

    container.addEventListener('drop', e => {
        e.preventDefault();
        if (!dragSrc) return;
        const block = e.target.closest('.dialogue-block');
        if (!block || block === dragSrc) return;
        const rect = block.getBoundingClientRect();
        const mid = rect.top + rect.height / 2;
        if (e.clientY < mid) {
            container.insertBefore(dragSrc, block);
        } else {
            container.insertBefore(dragSrc, block.nextSibling);
        }
    });

    // Touch support via pointer events
    let pointerDragSrc = null;
    let pointerClone = null;
    let pointerOffsetY = 0;

    container.addEventListener('pointerdown', e => {
        const handle = e.target.closest('.drag-handle');
        if (!handle) return;
        const block = handle.closest('.dialogue-block');
        if (!block) return;
        pointerDragSrc = block;
        const rect = block.getBoundingClientRect();
        pointerOffsetY = e.clientY - rect.top;

        pointerClone = block.cloneNode(true);
        pointerClone.style.cssText = `position:fixed;left:${rect.left}px;top:${rect.top}px;width:${rect.width}px;opacity:0.7;pointer-events:none;z-index:9999;background:var(--forge-card);border:1px solid var(--forge-amber);border-radius:var(--forge-radius);`;
        document.body.appendChild(pointerClone);
        block.classList.add('dragging');
        e.preventDefault();
    }, { passive: false });

    document.addEventListener('pointermove', e => {
        if (!pointerDragSrc || !pointerClone) return;
        const y = e.clientY - pointerOffsetY;
        pointerClone.style.top = y + 'px';

        container.querySelectorAll('.dialogue-block').forEach(b => {
            b.classList.remove('drag-over-top', 'drag-over-bottom');
        });
        const target = document.elementFromPoint(e.clientX, e.clientY + pointerOffsetY / 2);
        const block = target ? target.closest('.dialogue-block') : null;
        if (block && block !== pointerDragSrc && block.closest('[data-shot-id]') === container.closest('[data-shot-id]') || block && block !== pointerDragSrc && block.parentNode === container) {
            const rect = block.getBoundingClientRect();
            block.classList.add(e.clientY < rect.top + rect.height / 2 ? 'drag-over-top' : 'drag-over-bottom');
        }
    });

    document.addEventListener('pointerup', e => {
        if (!pointerDragSrc) return;
        if (pointerClone) { pointerClone.remove(); pointerClone = null; }
        pointerDragSrc.classList.remove('dragging');
        container.querySelectorAll('.dialogue-block').forEach(b => {
            b.classList.remove('drag-over-top', 'drag-over-bottom');
        });

        const target = document.elementFromPoint(e.clientX, e.clientY);
        const block = target ? target.closest('.dialogue-block') : null;
        if (block && block !== pointerDragSrc && block.parentNode === container) {
            const rect = block.getBoundingClientRect();
            if (e.clientY < rect.top + rect.height / 2) {
                container.insertBefore(pointerDragSrc, block);
            } else {
                container.insertBefore(pointerDragSrc, block.nextSibling);
            }
        }

        persistSortOrder(container, shotId);
        pointerDragSrc = null;
    });
}

function persistSortOrder(container, shotId) {
    const ids = Array.from(container.querySelectorAll('.dialogue-block'))
        .map(b => b.dataset.dialogueId)
        .filter(Boolean);
    if (!ids.length) return;
    const fd = new URLSearchParams();
    fd.append('action', 'reorder_lines');
    fd.append('shot_id', shotId);
    fd.append('order', ids.join(','));
    fetch('paradigm_b_api.php', { method: 'POST', body: fd });
}

function esc(s) {
    return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : '';
}
</script>

<?php
// Include modal_frame_details.php to access robust superior iframe modaling including nested entity form CRUD calls
require_once __DIR__ . '/modal_frame_details.php';
// Pass content directly, ensuring no SpwBase header is generated
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
