<?php
// public/paradigm_b_shot_audio.php
require_once __DIR__ . '/bootstrap.php';
$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$shotId = (int)($_GET['shot_id'] ?? 0);
if (!$shotId) die("No Shot ID provided.");

// Fetch shot info
$stmt = $pdo->prepare("SELECT id, name, audio_notes FROM editorial_shots WHERE id = ?");
$stmt->execute([$shotId]);
$shot = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$shot) die("Shot not found.");

// Audio type config: [table, junction_table, label]
$audioTypes = [
    'ambiences'  => ['audio_ambiences',  'editorial_shots_2_audio_ambiences',  'Ambiences'],
    'cues'       => ['audio_cues',        'editorial_shots_2_audio_cues',        'Music Cues'],
    'foleys'     => ['audio_foleys',      'editorial_shots_2_audio_foleys',      'Foley'],
    'fxsounds'   => ['audio_fxsounds',    'editorial_shots_2_audio_fxsounds',    'FX Sounds'],
    'themes'     => ['audio_themes',      'editorial_shots_2_audio_themes',      'Themes'],
];

// Fetch existing refs per type, including the latest generated audio url
$existingRefs = [];
foreach ($audioTypes as $key => [$table, $junction, $label]) {
    $audioJunction = "audios_2_" . $table;
    $sql = "
        SELECT e.id, e.name,
               (SELECT a.filename 
                FROM audios a 
                JOIN `$audioJunction` a2t ON a.id = a2t.from_id 
                WHERE a2t.to_id = e.id 
                ORDER BY a.created_at DESC LIMIT 1) as latest_audio_url
        FROM `$table` e 
        JOIN `$junction` j ON e.id = j.to_id 
        WHERE j.from_id = ? 
        ORDER BY e.name ASC
    ";
    $s = $pdo->prepare($sql);
    $s->execute([$shotId]);
    $existingRefs[$key] = $s->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Shot Audio — #<?= $shotId ?></title>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<script>
(function(){
    try {
        var t = localStorage.getItem('spw_theme');
        if(t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);
    } catch(e) {}
})();
</script>
<style>
:root {
    --bg: #07070d;
    --card: #0f0f1a;
    --border: #1a1a2e;
    --text: #d4d4e8;
    --muted: #4a4a6a;
    --accent: #f5a623;
    --green: #22d3a0;
    --red: #f05060;
    --radius: 6px;
    --mono: 'Space Mono', 'DM Mono', monospace;
}
[data-theme="light"], html[data-theme="light"] {
    --bg: #f4f4f8;
    --card: #ffffff;
    --border: #d0d0e0;
    --text: #1a1a2e;
    --muted: #888899;
    --accent: #d97706;
    --green: #059669;
    --red: #dc2626;
}

* { box-sizing: border-box; }
body {
    background: var(--bg); color: var(--text);
    font-family: var(--mono); margin: 0; padding: 12px 14px 40px;
    font-size: 0.82rem;
}

.page-header {
    border-bottom: 2px solid var(--accent);
    padding-bottom: 10px; margin-bottom: 16px;
    display: flex; align-items: center; gap: 8px;
}
.page-header h1 {
    margin: 0; font-size: 0.95rem; font-weight: 700; color: var(--accent);
}
.page-header .shot-sub { color: var(--muted); font-size: 0.72rem; margin-top: 2px; }

/* Global notes */
.notes-wrap { margin-bottom: 18px; }
.notes-label {
    font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;
    color: var(--muted); margin-bottom: 6px;
}
.notes-textarea {
    width: 100%; background: var(--card); border: 1px solid var(--border);
    color: var(--text); font-family: var(--mono); font-size: 0.82rem;
    padding: 8px 10px; border-radius: var(--radius); resize: none;
    line-height: 1.4; transition: border-color 0.2s, min-height 0.2s ease;
    min-height: 2.8em; /* ~2 lines */
    overflow: hidden;
}
.notes-textarea:focus {
    outline: none; border-color: var(--accent);
    min-height: 7em; /* expand on focus */
}

/* Audio type sections */
.audio-section { margin-bottom: 18px; }
.section-header {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 8px;
}
.section-title {
    font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;
    color: var(--muted); flex: 1;
}
.section-count {
    font-size: 0.65rem; color: var(--muted);
    background: rgba(128,128,128,0.1); border-radius: 10px;
    padding: 1px 7px;
}

/* Pill list */
.ref-pills {
    display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px;
    min-height: 0;
}
.ref-pill {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--card); border: 1px solid var(--border);
    border-radius: 20px; padding: 3px 10px 3px 10px;
    font-size: 0.72rem; color: var(--text);
    transition: border-color 0.15s;
}
.ref-pill:hover { border-color: var(--border); }
.ref-pill-play {
    background: none; border: none; color: var(--green);
    cursor: pointer; padding: 0 0 0 4px; display: flex; align-items: center;
    font-size: 0.85rem; transition: color 0.15s; line-height: 1;
}
.ref-pill-play:hover { color: var(--accent); }
.ref-pill-play.playing { color: var(--accent); }
.ref-pill-remove {
    background: none; border: none; color: var(--muted);
    cursor: pointer; padding: 0 0 0 2px; display: flex; align-items: center;
    font-size: 0.75rem; transition: color 0.15s; line-height: 1; margin-left: 2px;
}
.ref-pill-remove:hover { color: var(--red); }

/* Add row */
.add-row {
    display: flex; align-items: center; gap: 6px; position: relative;
}
.search-input {
    flex: 1; background: var(--card); border: 1px solid var(--border);
    color: var(--text); font-family: var(--mono); font-size: 0.78rem;
    padding: 6px 10px; border-radius: var(--radius);
    transition: border-color 0.2s;
}
.search-input:focus { outline: none; border-color: var(--accent); }
.add-btn {
    width: 30px; height: 30px; flex-shrink: 0;
    background: var(--card); border: 1px solid var(--accent);
    color: var(--accent); border-radius: var(--radius);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 1rem; transition: background 0.15s;
}
.add-btn:hover { background: rgba(245,166,35,0.1); }
.add-btn:disabled { opacity: 0.3; cursor: default; }

/* Dropdown suggestions */
.suggestions {
    position: absolute; top: 100%; left: 0; right: 36px;
    background: var(--card); border: 1px solid var(--accent);
    border-top: none; border-radius: 0 0 var(--radius) var(--radius);
    z-index: 100; max-height: 180px; overflow-y: auto;
    display: none;
}
.suggestions.open { display: block; }
.suggestion-item {
    padding: 7px 10px; cursor: pointer; font-size: 0.78rem;
    border-bottom: 1px solid var(--border); transition: background 0.1s;
}
.suggestion-item:last-child { border-bottom: none; }
.suggestion-item:hover, .suggestion-item.active { background: rgba(245,166,35,0.1); color: var(--accent); }
.suggestion-item.empty { color: var(--muted); cursor: default; font-style: italic; }

/* Save indicator */
.save-indicator {
    position: fixed; top: 10px; right: 12px;
    font-size: 0.65rem; color: var(--green);
    opacity: 0; transition: opacity 0.3s;
    text-transform: uppercase; letter-spacing: 1px;
}
.save-indicator.show { opacity: 1; }
</style>
</head>
<body>

<div class="save-indicator" id="saveIndicator">Saved</div>

<div class="page-header">
    <div>
        <h1>Shot Audio — #<?= $shotId ?></h1>
        <div class="shot-sub"><?= htmlspecialchars($shot['name']) ?></div>
    </div>
</div>

<!-- Global Notes -->
<div class="notes-wrap">
    <div class="notes-label">Audio Notes</div>
    <textarea class="notes-textarea" id="audioNotes" placeholder="Global audio notes for this shot..."><?= htmlspecialchars($shot['audio_notes'] ?? '') ?></textarea>
</div>

<!-- Audio Type Sections -->
<?php foreach ($audioTypes as $key => [$table, $junction, $label]): ?>
<div class="audio-section" id="section-<?= $key ?>">
    <div class="section-header">
        <div class="section-title"><?= $label ?></div>
        <div class="section-count" id="count-<?= $key ?>"><?= count($existingRefs[$key]) ?></div>
    </div>
    <div class="ref-pills" id="pills-<?= $key ?>">
        <?php foreach ($existingRefs[$key] as $ref): ?>
        <span class="ref-pill" data-id="<?= $ref['id'] ?>">
            <?= htmlspecialchars($ref['name']) ?>
            <?php if (!empty($ref['latest_audio_url'])): ?>
                <button class="ref-pill-play" onclick="playAudio('<?= htmlspecialchars($ref['latest_audio_url']) ?>', this)" title="Play"><i class="bi bi-play-fill"></i></button>
            <?php endif; ?>
            <button class="ref-pill-remove" onclick="removeRef('<?= $key ?>', <?= $ref['id'] ?>, this.closest('.ref-pill'))" title="Remove"><i class="bi bi-x"></i></button>
        </span>
        <?php endforeach; ?>
    </div>
    <div class="add-row">
        <input type="text" class="search-input" id="search-<?= $key ?>" placeholder="Search <?= strtolower($label) ?>..." autocomplete="off"
            oninput="onSearchInput('<?= $key ?>', this.value)"
            onkeydown="onSearchKey(event, '<?= $key ?>')"
            onfocus="onSearchFocus('<?= $key ?>')"
            onblur="onSearchBlur('<?= $key ?>')">
        <div class="suggestions" id="suggestions-<?= $key ?>"></div>
        <button class="add-btn" id="addbtn-<?= $key ?>" title="Add selected" onclick="addSelected('<?= $key ?>')" disabled>
            <i class="bi bi-plus"></i>
        </button>
    </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/howler@2.2.4/dist/howler.min.js"></script>
<script>
const SHOT_ID = <?= $shotId ?>;
let searchTimers = {};
let selectedItem = {}; // { key: {id, name, url} }
let highlightIndex = {}; // { key: int }

let currentHowl = null;
let currentPlayBtn = null;

// ---- Audio Playback ----
function playAudio(url, btn) {
    if (currentHowl) { 
        currentHowl.stop(); 
        currentHowl = null; 
    }
    if (currentPlayBtn) {
        currentPlayBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
        currentPlayBtn.classList.remove('playing');
    }
    
    // Toggle stop
    if (currentPlayBtn === btn) {
        currentPlayBtn = null;
        return;
    }
    if (!url) return;
    
    currentPlayBtn = btn;
    btn.innerHTML = '<i class="bi bi-pause-fill"></i>';
    btn.classList.add('playing');

    currentHowl = new Howl({ 
        src: [url], 
        html5: true,
        onend: () => stopAudioUI(),
        onloaderror: () => stopAudioUI()
    });
    currentHowl.play();
}

function stopAudioUI() {
    if (currentPlayBtn) {
        currentPlayBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
        currentPlayBtn.classList.remove('playing');
        currentPlayBtn = null;
    }
}

// ---- Notes autosave ----
let notesTimer = null;
document.getElementById('audioNotes').addEventListener('input', function() {
    clearTimeout(notesTimer);
    notesTimer = setTimeout(() => saveNotes(this.value), 600);
});

async function saveNotes(val) {
    const fd = new URLSearchParams();
    fd.append('action', 'save_shot_audio_notes');
    fd.append('shot_id', SHOT_ID);
    fd.append('notes', val);
    await fetch('paradigm_b_api.php', { method: 'POST', body: fd });
    flashSaved();
}

function flashSaved() {
    const el = document.getElementById('saveIndicator');
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 1200);
}

// ---- Search ----
function onSearchInput(key, val) {
    selectedItem[key] = null;
    document.getElementById('addbtn-' + key).disabled = true;
    clearTimeout(searchTimers[key]);
    if (!val.trim()) { closeSuggestions(key); return; }
    searchTimers[key] = setTimeout(() => doSearch(key, val), 220);
}

async function doSearch(key, q) {
    const res = await fetch('paradigm_b_api.php?action=search_audio_entity&type=' + key + '&q=' + encodeURIComponent(q) + '&shot_id=' + SHOT_ID);
    const data = await res.json();
    renderSuggestions(key, data.data || []);
}

function renderSuggestions(key, items) {
    const el = document.getElementById('suggestions-' + key);
    highlightIndex[key] = -1;
    if (!items.length) {
        el.innerHTML = '<div class="suggestion-item empty">No results</div>';
    } else {
        el.innerHTML = items.map((item, i) =>
            `<div class="suggestion-item" data-id="${item.id}" data-name="${escAttr(item.name)}" data-url="${escAttr(item.latest_audio_url || '')}" onmousedown="pickItem('${key}', ${item.id}, '${escJs(item.name)}', '${escJs(item.latest_audio_url || '')}')">${escHtml(item.name)}</div>`
        ).join('');
    }
    el.classList.add('open');
}

function pickItem(key, id, name, url) {
    selectedItem[key] = { id, name, url };
    document.getElementById('search-' + key).value = name;
    document.getElementById('addbtn-' + key).disabled = false;
    closeSuggestions(key);
}

function closeSuggestions(key) {
    document.getElementById('suggestions-' + key).classList.remove('open');
}

function onSearchFocus(key) {
    const val = document.getElementById('search-' + key).value;
    if (val.trim()) doSearch(key, val);
}

function onSearchBlur(key) {
    // small delay so mousedown on suggestion fires first
    setTimeout(() => closeSuggestions(key), 150);
}

function onSearchKey(e, key) {
    const el = document.getElementById('suggestions-' + key);
    const items = el.querySelectorAll('.suggestion-item:not(.empty)');
    if (!items.length) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlightIndex[key] = Math.min((highlightIndex[key] ?? -1) + 1, items.length - 1);
        updateHighlight(key, items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlightIndex[key] = Math.max((highlightIndex[key] ?? 0) - 1, 0);
        updateHighlight(key, items);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const idx = highlightIndex[key] ?? -1;
        if (idx >= 0 && items[idx]) {
            const url = items[idx].dataset.url || '';
            pickItem(key, items[idx].dataset.id, items[idx].dataset.name, url);
        } else if (selectedItem[key]) {
            addSelected(key);
        }
    } else if (e.key === 'Escape') {
        closeSuggestions(key);
    }
}

function updateHighlight(key, items) {
    items.forEach((el, i) => el.classList.toggle('active', i === highlightIndex[key]));
}

// ---- Add / Remove refs ----
async function addSelected(key) {
    const sel = selectedItem[key];
    if (!sel) return;

    // Check if already added
    const pills = document.getElementById('pills-' + key);
    if (pills.querySelector(`.ref-pill[data-id="${sel.id}"]`)) {
        document.getElementById('search-' + key).value = '';
        selectedItem[key] = null;
        document.getElementById('addbtn-' + key).disabled = true;
        return;
    }

    const fd = new URLSearchParams();
    fd.append('action', 'add_shot_audio_ref');
    fd.append('shot_id', SHOT_ID);
    fd.append('type', key);
    fd.append('entity_id', sel.id);
    const res = await fetch('paradigm_b_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        // Append pill
        const pill = document.createElement('span');
        pill.className = 'ref-pill';
        pill.dataset.id = sel.id;
        
        let playBtnHtml = '';
        if (sel.url) {
            playBtnHtml = `<button class="ref-pill-play" onclick="playAudio('${escJs(sel.url)}', this)" title="Play"><i class="bi bi-play-fill"></i></button>`;
        }
        
        pill.innerHTML = escHtml(sel.name) + playBtnHtml + ` <button class="ref-pill-remove" title="Remove"><i class="bi bi-x"></i></button>`;
        pill.querySelector('.ref-pill-remove').addEventListener('click', () => removeRef(key, sel.id, pill));
        pills.appendChild(pill);
        updateCount(key);
        flashSaved();
    }
    document.getElementById('search-' + key).value = '';
    selectedItem[key] = null;
    document.getElementById('addbtn-' + key).disabled = true;
}

async function removeRef(key, entityId, pillEl) {
    const fd = new URLSearchParams();
    fd.append('action', 'remove_shot_audio_ref');
    fd.append('shot_id', SHOT_ID);
    fd.append('type', key);
    fd.append('entity_id', entityId);
    const res = await fetch('paradigm_b_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        pillEl.remove();
        updateCount(key);
        flashSaved();
    }
}

function updateCount(key) {
    const count = document.getElementById('pills-' + key).querySelectorAll('.ref-pill').length;
    document.getElementById('count-' + key).textContent = count;
}

// ---- Helpers ----
function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escAttr(s) { return String(s).replace(/"/g,'&quot;'); }
function escJs(s) { return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }
</script>
</body>
</html>


