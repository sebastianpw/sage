<?php
// public/paradigm_b_dash.php
require_once __DIR__ . '/bootstrap.php';
$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("No Dialogue ID provided.");

// Fetch Dialogue Info
$stmt = $pdo->prepare("SELECT * FROM audio_dialogue_lines WHERE id = ?");
$stmt->execute([$id]);
$line = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$line) die("Dialogue line not found.");

// Fetch Generated Audios
$stmt = $pdo->prepare("
    SELECT a.* 
    FROM audios a 
    JOIN audios_2_audio_dialogue_lines map ON a.id = map.from_id 
    WHERE map.to_id = ? 
    ORDER BY a.created_at DESC
");
$stmt->execute([$id]);
$audios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch voice identities for quick select — pull example audio URL via pinned xmpl table
$voicesRaw = $pdo->query("
    SELECT avi.id, avi.name,
           (
               SELECT a.filename
               FROM audio_voice_identity_xmpl x
               JOIN audios_2_audio_dialogue_lines a2d ON a2d.to_id = x.dialogue_line_id
               JOIN audios a ON a.id = a2d.from_id
               WHERE x.voice_identity_id = avi.id
               ORDER BY a.created_at DESC
               LIMIT 1
           ) as example_url
    FROM audio_voice_identity avi
    ORDER BY avi.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Build JS-safe voices map (only those with examples)
$voiceExamples = [];
foreach ($voicesRaw as $v) {
    if ($v['example_url']) {
        $voiceExamples[$v['id']] = $v['example_url'];
    }
}
$voices = $voicesRaw;

// Determine active audio id: explicit setting OR latest (first in DESC order)
$activeAudioId = $line['active_audio_id'] ?? null;
if (!$activeAudioId && !empty($audios)) {
    $activeAudioId = $audios[0]['id']; // latest by default (matches load_script logic)
}

$pageTitle = "Dialogue Dashboard #" . $id;
ob_start();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $pageTitle ?></title>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<!-- Inline Theme Manager to prevent flashing inside iframe -->
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
    --accent: #6c63ff;
    --green: #22d3a0;
    --amber: #f5a623;
    --red: #f05060;
}
[data-theme="light"], html[data-theme="light"] {
    --bg: #f4f4f8;
    --card: #ffffff;
    --border: #d0d0e0;
    --text: #1a1a2e;
    --muted: #888899;
    --accent: #6c63ff;
    --green: #059669;
    --amber: #d97706;
    --red: #dc2626;
}

* { box-sizing: border-box; }
body { background: var(--bg); color: var(--text); font-family: 'DM Mono', monospace; padding: 16px; margin: 0; }

.dash-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 12px; }
.dash-title { font-size: 1.2rem; font-weight: 700; color: var(--accent); margin: 0 0 4px 0; }
.dash-sub { font-size: 0.8rem; color: var(--muted); }

.btn-edit { background: var(--card); border: 1px solid var(--border); color: var(--text); padding: 6px 12px; border-radius: 4px; font-family: inherit; font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.btn-edit:hover { border-color: var(--accent); color: var(--accent); }

/* Quick Edit Panel */
.quick-edit-panel {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 6px; padding: 12px 14px; margin-bottom: 16px;
}
.qe-title {
    font-size: 0.68rem; text-transform: uppercase; letter-spacing: 1px;
    color: var(--muted); margin-bottom: 10px;
}
.qe-row {
    display: flex; align-items: center; gap: 10px; margin-bottom: 8px;
}
.qe-row:last-child { margin-bottom: 0; }
.qe-label {
    font-size: 0.7rem; color: var(--muted);
    min-width: 80px; flex-shrink: 0;
}
.qe-select {
    flex: 1; background: var(--bg); border: 1px solid var(--border);
    color: var(--text); font-family: inherit; font-size: 0.78rem;
    padding: 5px 8px; border-radius: 4px; transition: border-color 0.2s;
}
.qe-select:focus { outline: none; border-color: var(--accent); }
.qe-search-wrap { flex: 1; position: relative; display: flex; align-items: center; gap: 6px; }
.qe-search {
    flex: 1; background: var(--bg); border: 1px solid var(--border);
    color: var(--text); font-family: inherit; font-size: 0.78rem;
    padding: 5px 8px; border-radius: 4px; transition: border-color 0.2s;
}
.qe-search:focus { outline: none; border-color: var(--accent); }
.qe-current-val {
    font-size: 0.7rem; color: var(--accent); min-width: 60px; text-align: right;
    flex-shrink: 0;
}
.qe-suggestions {
    position: absolute; top: 100%; left: 0; right: 0;
    background: var(--card); border: 1px solid var(--accent);
    border-top: none; border-radius: 0 0 4px 4px;
    z-index: 100; max-height: 160px; overflow-y: auto;
    display: none;
}
.qe-suggestions.open { display: block; }
.qe-suggestion-item {
    padding: 6px 10px; cursor: pointer; font-size: 0.75rem;
    border-bottom: 1px solid var(--border); transition: background 0.1s;
}
.qe-suggestion-item:last-child { border-bottom: none; }
.qe-suggestion-item:hover, .qe-suggestion-item.active { background: rgba(108,99,255,0.1); color: var(--accent); }
.qe-suggestion-item.empty { color: var(--muted); cursor: default; font-style: italic; }
.qe-clear-btn {
    background: none; border: none; color: var(--muted); cursor: pointer;
    padding: 0; font-size: 0.8rem; display: flex; align-items: center;
    flex-shrink: 0; transition: color 0.15s;
}
.qe-clear-btn:hover { color: var(--red); }
.qe-checkbox-row { display: flex; align-items: center; gap: 8px; }
.qe-checkbox { width: 16px; height: 16px; accent-color: var(--amber); cursor: pointer; }
.qe-checkbox-label { font-size: 0.78rem; cursor: pointer; }
.qe-save-indicator {
    font-size: 0.65rem; color: var(--green); text-transform: uppercase;
    letter-spacing: 1px; opacity: 0; transition: opacity 0.3s; margin-left: auto;
}
.qe-save-indicator.show { opacity: 1; }

/* Voice Preview Player */
.voice-preview-btn {
    width: 30px; height: 30px; border-radius: 50%;
    border: 1px solid var(--accent); background: transparent;
    color: var(--accent); display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 0.75rem; flex-shrink: 0;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
    position: relative;
}
.voice-preview-btn:hover { background: rgba(108,99,255,0.15); }
.voice-preview-btn.playing {
    border-color: var(--green); color: var(--green);
    background: rgba(34,211,160,0.12);
    animation: vp-pulse 1.4s ease-in-out infinite;
}
.voice-preview-btn.no-example {
    border-color: var(--border); color: var(--muted);
    cursor: default; opacity: 0.45;
}
.voice-preview-btn.loading {
    border-color: var(--amber); color: var(--amber);
    cursor: default;
}
@keyframes vp-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(34,211,160,0.35); }
    50%       { box-shadow: 0 0 0 5px rgba(34,211,160,0); }
}

/* Mini waveform bars shown while playing */
.vp-bars {
    display: none; align-items: flex-end; gap: 2px; height: 14px;
}
.voice-preview-btn.playing .vp-bars { display: flex; }
.voice-preview-btn.playing .vp-icon { display: none; }
.vp-bar {
    width: 3px; background: var(--green); border-radius: 1px;
    animation: vp-bar-bounce 0.8s ease-in-out infinite;
}
.vp-bar:nth-child(1) { animation-delay: 0s;    height: 6px; }
.vp-bar:nth-child(2) { animation-delay: 0.15s; height: 10px; }
.vp-bar:nth-child(3) { animation-delay: 0.3s;  height: 7px; }
@keyframes vp-bar-bounce {
    0%, 100% { transform: scaleY(0.4); }
    50%       { transform: scaleY(1); }
}

/* Tooltip on preview button */
.voice-preview-btn[title] { position: relative; }

.text-box { background: rgba(128,128,128,0.05); border: 1px solid var(--border); padding: 16px; border-radius: 6px; margin-bottom: 20px; font-size: 0.95rem; line-height: 1.5; font-style: italic; text-align: center; }

.section-title { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 10px; }

.audio-list { display: flex; flex-direction: column; gap: 8px; }
.audio-card {
    background: var(--card); border: 1px solid var(--border); padding: 10px 12px;
    border-radius: 6px; display: flex; align-items: center; gap: 12px;
    transition: border-color 0.2s;
}
.audio-card:hover { border-color: var(--accent); }
.audio-card.is-active-take {
    border-color: var(--green);
    background: rgba(34, 211, 160, 0.05);
}
.audio-btn { width: 36px; height: 36px; border-radius: 50%; border: 1px solid var(--accent); background: transparent; color: var(--accent); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem; flex-shrink: 0; }
.audio-btn:active { background: rgba(108,99,255,0.1); }
.audio-meta { flex: 1; min-width: 0; }
.audio-name { font-size: 0.85rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.audio-sub { font-size: 0.65rem; color: var(--muted); margin-top: 2px; }

/* Active take toggle */
.take-toggle {
    display: flex; align-items: center; gap: 6px;
    flex-shrink: 0;
}
.take-toggle input[type="radio"] {
    display: none;
}
.take-toggle label {
    display: flex; align-items: center; justify-content: center;
    width: 28px; height: 28px;
    border-radius: 50%;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--muted);
    cursor: pointer;
    font-size: 0.7rem;
    transition: all 0.15s;
    user-select: none;
}
.take-toggle label:hover { border-color: var(--green); color: var(--green); }
.take-toggle input[type="radio"]:checked + label {
    border-color: var(--green);
    background: rgba(34,211,160,0.15);
    color: var(--green);
}
.take-active-badge {
    font-size: 0.6rem; color: var(--green); text-transform: uppercase;
    letter-spacing: 0.5px; margin-top: 2px; display: none;
}
.audio-card.is-active-take .take-active-badge { display: block; }
</style>
</head>
<body>

<div class="dash-header">
    <div>
        <h1 class="dash-title">Line #<?= $id ?></h1>
        <div class="dash-sub">Character: <?= $line['character_id'] ?: 'Unassigned' ?> | Voice: <?= $line['audio_voice_identity_id'] ?: 'Default' ?></div>
    </div>
    <button class="btn-edit" onclick="openEntityEditor()">
        <i class="bi bi-pencil"></i> Full Edit
    </button>
</div>

<!-- Quick Edit Panel -->
<div class="quick-edit-panel">
    <div class="qe-title" style="display:flex;align-items:center;">
        Quick Edit
        <span class="qe-save-indicator" id="qeSaveIndicator">Saved</span>
    </div>

    <!-- character_id: free search -->
    <div class="qe-row">
        <div class="qe-label">Character</div>
        <div class="qe-search-wrap">
            <input type="text" class="qe-search" id="charSearch" placeholder="Search characters..."
                autocomplete="off"
                oninput="onCharSearch(this.value)"
                onfocus="onCharFocus()"
                onblur="onCharBlur()"
                onkeydown="onCharKey(event)">
            <div class="qe-suggestions" id="charSuggestions"></div>
            <button class="qe-clear-btn" onclick="clearChar()" title="Clear"><i class="bi bi-x"></i></button>
        </div>
        <div class="qe-current-val" id="charCurrentVal"><?= $line['character_id'] ? '#'.$line['character_id'] : '—' ?></div>
    </div>

    <!-- audio_voice_identity_id: select + preview button -->
    <div class="qe-row">
        <div class="qe-label">Voice</div>
        <select class="qe-select" id="voiceSelect" onchange="onVoiceChange(this.value)">
            <option value="">— None —</option>
            <?php foreach ($voices as $v): ?>
            <option value="<?= $v['id'] ?>"<?= ($line['audio_voice_identity_id'] == $v['id']) ? ' selected' : '' ?>><?= htmlspecialchars($v['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="voice-preview-btn" id="voicePreviewBtn"
                onclick="toggleVoicePreview()"
                title="Preview voice sample">
            <span class="vp-icon"><i class="bi bi-headphones"></i></span>
            <span class="vp-bars">
                <span class="vp-bar"></span>
                <span class="vp-bar"></span>
                <span class="vp-bar"></span>
            </span>
        </button>
    </div>

    <!-- regenerate_audios: checkbox -->
    <div class="qe-row">
        <div class="qe-label">Regen</div>
        <div class="qe-checkbox-row">
            <input type="checkbox" class="qe-checkbox" id="regenCheck"
                <?= $line['regenerate_audios'] ? 'checked' : '' ?>
                onchange="saveQuick('regenerate_audios', this.checked ? 1 : 0)">
            <label for="regenCheck" class="qe-checkbox-label">regenerate_audios</label>
        </div>
    </div>
</div>

<div class="text-box">
    "<?= htmlspecialchars($line['description'] ?: '[No dialogue text]') ?>"
</div>

<div class="section-title">Generated Audio Renders (<?= count($audios) ?>)</div>

<div class="audio-list">
    <?php if (empty($audios)): ?>
        <div style="color:var(--muted); font-size:0.8rem; text-align:center; padding:20px;">No audio rendered yet.</div>
    <?php else: ?>
        <?php foreach ($audios as $a): ?>
            <?php $isActive = ($a['id'] == $activeAudioId); ?>
            <div class="audio-card<?= $isActive ? ' is-active-take' : '' ?>" id="audio-card-<?= $a['id'] ?>">
                <button class="audio-btn" onclick="playAudio('<?= htmlspecialchars($a['filename']) ?>')">
                    <i class="bi bi-play-fill"></i>
                </button>
                <div class="audio-meta">
                    <div class="audio-name"><?= htmlspecialchars($a['name'] ?: 'Render #'.$a['id']) ?></div>
                    <div class="audio-sub"><?= date('M d, H:i', strtotime($a['created_at'])) ?> • Model: <?= htmlspecialchars($a['rvc_model_name'] ?? 'N/A') ?></div>
                    <div class="take-active-badge">✓ Active Take</div>
                </div>
                <div class="take-toggle">
                    <input type="radio" name="active_take" id="take-<?= $a['id'] ?>" value="<?= $a['id'] ?>"<?= $isActive ? ' checked' : '' ?> onchange="setActiveTake(<?= $a['id'] ?>)">
                    <label for="take-<?= $a['id'] ?>" title="Set as active take">
                        <i class="bi bi-check2"></i>
                    </label>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/howler@2.2.4/dist/howler.min.js"></script>
<script>
let howl = null;
const dialogueId = <?= $id ?>;
let charSearchTimer = null;
let charHighlight = -1;
let selectedCharId = <?= $line['character_id'] ? (int)$line['character_id'] : 'null' ?>;

// Voice example map: voiceId -> example audio URL
const voiceExamples = <?= json_encode($voiceExamples) ?>;

// ---- Audio playback (dialogue renders) ----
function playAudio(url) {
    if (howl) howl.stop();
    howl = new Howl({ src: [url], html5: true });
    howl.play();
}

// ---- Voice Preview Player ----
let vpHowl = null;
let vpPlaying = false;

function getSelectedVoiceId() {
    const sel = document.getElementById('voiceSelect');
    return sel ? sel.value : '';
}

function updatePreviewBtnState() {
    const btn = document.getElementById('voicePreviewBtn');
    if (!btn) return;
    const voiceId = getSelectedVoiceId();

    btn.classList.remove('playing', 'no-example', 'loading');

    if (!voiceId || !voiceExamples[voiceId]) {
        btn.classList.add('no-example');
        btn.title = voiceId ? 'No example available for this voice' : 'Select a voice to preview';
        return;
    }
    if (vpPlaying) {
        btn.classList.add('playing');
        btn.title = 'Stop preview';
    } else {
        btn.title = 'Preview voice sample';
    }
}

function toggleVoicePreview() {
    const voiceId = getSelectedVoiceId();
    if (!voiceId || !voiceExamples[voiceId]) return;

    if (vpPlaying) {
        stopVoicePreview();
        return;
    }

    // Stop any dialogue playback
    if (howl) howl.stop();

    const btn = document.getElementById('voicePreviewBtn');
    btn.classList.add('loading');
    btn.title = 'Loading…';

    if (vpHowl) vpHowl.unload();

    vpHowl = new Howl({
        src: [voiceExamples[voiceId]],
        html5: true,
        onplay: function() {
            vpPlaying = true;
            updatePreviewBtnState();
        },
        onend: function() {
            vpPlaying = false;
            updatePreviewBtnState();
        },
        onstop: function() {
            vpPlaying = false;
            updatePreviewBtnState();
        },
        onloaderror: function() {
            vpPlaying = false;
            const b = document.getElementById('voicePreviewBtn');
            b.classList.remove('loading', 'playing');
            b.title = 'Failed to load sample';
        }
    });
    vpHowl.play();
}

function stopVoicePreview() {
    if (vpHowl) vpHowl.stop();
    vpPlaying = false;
    updatePreviewBtnState();
}

function onVoiceChange(value) {
    stopVoicePreview();
    saveQuick('audio_voice_identity_id', value);
    updatePreviewBtnState();
}

// Init preview button state on load
document.addEventListener('DOMContentLoaded', updatePreviewBtnState);

// ---- Active take ----
function setActiveTake(audioId) {
    document.querySelectorAll('.audio-card').forEach(card => card.classList.remove('is-active-take'));
    const activeCard = document.getElementById('audio-card-' + audioId);
    if (activeCard) activeCard.classList.add('is-active-take');

    const fd = new URLSearchParams();
    fd.append('action', 'set_active_take');
    fd.append('dialogue_id', dialogueId);
    fd.append('audio_id', audioId);
    fetch('paradigm_b_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => { if (!res.success) console.error('Failed to set active take:', res.message); });
}

// ---- Quick save ----
async function saveQuick(field, value) {
    const fd = new URLSearchParams();
    fd.append('action', 'update_dialogue_quick');
    fd.append('dialogue_id', dialogueId);
    fd.append(field, value);
    const res = await fetch('paradigm_b_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) flashQeSaved();
}

function flashQeSaved() {
    const el = document.getElementById('qeSaveIndicator');
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 1200);
}

// ---- Character search ----
function onCharSearch(val) {
    selectedCharId = null;
    document.getElementById('charCurrentVal').textContent = '—';
    clearTimeout(charSearchTimer);
    if (!val.trim()) { closeCharSuggestions(); return; }
    charSearchTimer = setTimeout(() => doCharSearch(val), 220);
}

async function doCharSearch(q) {
    // Search characters entity via generic entity search
    const res = await fetch('paradigm_b_api.php?action=search_entity&entity_type=characters&q=' + encodeURIComponent(q));
    const data = await res.json();
    renderCharSuggestions(data.data || []);
}

function renderCharSuggestions(items) {
    const el = document.getElementById('charSuggestions');
    charHighlight = -1;
    if (!items.length) {
        el.innerHTML = '<div class="qe-suggestion-item empty">No results</div>';
    } else {
        el.innerHTML = items.map(item =>
            `<div class="qe-suggestion-item" data-id="${item.id}" onmousedown="pickChar(${item.id}, '${escJs(item.name)}')">${escHtml(item.name)}</div>`
        ).join('');
    }
    el.classList.add('open');
}

function pickChar(id, name) {
    selectedCharId = id;
    document.getElementById('charSearch').value = name;
    document.getElementById('charCurrentVal').textContent = '#' + id;
    closeCharSuggestions();
    saveQuick('character_id', id);
}

function clearChar() {
    selectedCharId = null;
    document.getElementById('charSearch').value = '';
    document.getElementById('charCurrentVal').textContent = '—';
    closeCharSuggestions();
    saveQuick('character_id', '');
}

function closeCharSuggestions() {
    document.getElementById('charSuggestions').classList.remove('open');
}
function onCharFocus() {
    const val = document.getElementById('charSearch').value;
    if (val.trim()) doCharSearch(val);
}
function onCharBlur() {
    setTimeout(closeCharSuggestions, 150);
}
function onCharKey(e) {
    const el = document.getElementById('charSuggestions');
    const items = el.querySelectorAll('.qe-suggestion-item:not(.empty)');
    if (!items.length) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); charHighlight = Math.min(charHighlight + 1, items.length - 1); updateCharHighlight(items); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); charHighlight = Math.max(charHighlight - 1, 0); updateCharHighlight(items); }
    else if (e.key === 'Enter') { e.preventDefault(); if (charHighlight >= 0 && items[charHighlight]) { pickChar(items[charHighlight].dataset.id, items[charHighlight].textContent); } }
    else if (e.key === 'Escape') { closeCharSuggestions(); }
}
function updateCharHighlight(items) {
    items.forEach((el, i) => el.classList.toggle('active', i === charHighlight));
}

// ---- Entity editor ----
function openEntityEditor() {
    if (window.parent && typeof window.parent.showEntityFormInModal === 'function') {
        window.parent.showEntityFormInModal('audio_dialogue_lines', <?= $id ?>);
    } else {
        window.location.href = '/entity_form.php?entity_type=audio_dialogue_lines&entity_id=<?= $id ?>';
    }
}

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escJs(s) { return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }
</script>
</body>
</html>
<?php
echo ob_get_clean();
?>
