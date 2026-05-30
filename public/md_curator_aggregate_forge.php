<?php
// public/md_curator_aggregate_forge.php
// Mobile-first JSON builder for MD Curator Aggregate
// Writes config for:
//   php public/cli_md_curator_aggregate_json.php --config=md_curator_job.json

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$em = $spw->getEntityManager();
$conn = $em->getConnection();

function esc($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function getCategories($conn): array {
    $rows = $conn->fetchAllAssociative("SELECT id, name FROM documentation_categories ORDER BY name ASC");
    return $rows ?: [];
}

function buildDefaults(): array {
    return [
        'targetCategoryId' => 0,
        'limit' => 100,
    ];
}

if (isset($_GET['action']) && $_GET['action'] === 'meta') {
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'ok' => true,
        'categories' => getCategories($conn),
        'defaults' => buildDefaults(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_json') {
    header('Content-Type: application/json; charset=utf-8');

    $json = (string)($_POST['config_json'] ?? '');
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload.']);
        exit;
    }

    $dir = __DIR__ . '/../var/md_curator_configs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $name = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', trim((string)($_POST['name'] ?? 'md_curator_job')));
    $file = $dir . '/' . $name . '_' . date('Ymd_His') . '.json';

    $ok = @file_put_contents(
        $file,
        json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    if ($ok === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write config file.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'saved' => true,
        'path' => $file,
        'runner' => 'php public/cli_md_curator_aggregate_json.php --config=' . $file,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$categories = getCategories($conn);
$defaults = buildDefaults();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>MD Curator Aggregate Forge</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{
    --bg:#080b10;
    --surface:#0e1319;
    --card:#111820;
    --card-hover:#141e28;
    --border:#1c2535;
    --border-glow:#2a3a52;
    --text:#c8d4e8;
    --text-dim:#5a6a80;
    --text-bright:#e8f0ff;
    --amber:#f5a623;
    --amber-dim:rgba(245,166,35,0.08);
    --amber-mid:rgba(245,166,35,0.15);
    --green:#22d3a0;
    --red:#f05060;
    --mono:'Space Mono',monospace;
    --sans:'Syne',system-ui,sans-serif;
    --radius:10px;
    --radius-sm:8px;
    --shadow:0 10px 30px rgba(0,0,0,0.35);
}
*{box-sizing:border-box}
html,body{height:100%;margin:0;background:var(--bg);color:var(--text);font-family:var(--sans);overflow:hidden}
body{
    display:grid;
    grid-template-rows:56px 1fr auto;
    min-height:100%;
}
header{
    display:flex;align-items:center;justify-content:space-between;gap:10px;
    padding:0 14px;
    background:var(--surface);
    border-bottom:1px solid var(--border);
    position:sticky;top:0;z-index:20;
}
.brand{
    display:flex;align-items:center;gap:10px;
    font-family:var(--mono);
    font-weight:700;
    letter-spacing:1.4px;
    text-transform:uppercase;
    font-size:12px;
    color:var(--amber);
    min-width:0;
}
.brand i{font-size:18px;flex:0 0 auto}
.brand span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.header-actions{display:flex;align-items:center;gap:8px;flex:0 0 auto}
.icon-btn, .text-btn{
    appearance:none;border:none;border-radius:var(--radius-sm);cursor:pointer;
    font-family:var(--mono);font-size:12px;
    display:inline-flex;align-items:center;justify-content:center;gap:6px;
    text-decoration:none;
}
.icon-btn{
    width:40px;height:40px;
    background:var(--card);
    color:var(--text-dim);
    border:1px solid var(--border);
}
.icon-btn:active, .text-btn:active{transform:translateY(1px)}
.icon-btn:hover{border-color:var(--amber);color:var(--amber);background:var(--amber-dim)}
.text-btn{
    min-height:40px;
    padding:0 12px;
    background:var(--card);
    color:var(--text);
    border:1px solid var(--border);
    white-space:nowrap;
}
.text-btn.primary{background:var(--amber);border-color:transparent;color:#000;font-weight:700}
.text-btn.success{background:var(--green);border-color:transparent;color:#000;font-weight:700}
.text-btn.secondary{background:transparent}
.text-btn:hover{border-color:var(--border-glow)}

.main{
    overflow:auto;
    padding:12px;
    -webkit-overflow-scrolling:touch;
}
.shell{
    display:grid;
    grid-template-columns:1fr;
    gap:12px;
    max-width:1120px;
    margin:0 auto;
    padding-bottom:100px;
}
.card{
    background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
    border:1px solid var(--border);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    overflow:hidden;
}
.card-header{
    display:flex;align-items:center;justify-content:space-between;gap:10px;
    padding:12px 14px;
    background:rgba(255,255,255,0.02);
    border-bottom:1px solid var(--border);
}
.card-title{
    display:flex;align-items:center;gap:8px;
    font-family:var(--mono);
    font-size:12px;
    font-weight:700;
    letter-spacing:1px;
    text-transform:uppercase;
    color:var(--text-bright);
    min-width:0;
}
.card-title i{color:var(--amber)}
.card-sub{
    font-family:var(--mono);
    font-size:11px;
    color:var(--text-dim);
    white-space:nowrap;
}
.card-body{padding:14px}

details.section{
    border:1px solid var(--border);
    border-radius:var(--radius);
    background:var(--surface);
    overflow:hidden;
    margin-bottom:12px;
}
details.section[open]{box-shadow:var(--shadow)}
summary.section-head{
    list-style:none;
    cursor:pointer;
    padding:14px;
    display:flex;align-items:center;justify-content:space-between;gap:10px;
    user-select:none;
}
summary.section-head::-webkit-details-marker{display:none}
.section-head-left{display:flex;align-items:center;gap:10px;min-width:0}
.section-num{
    width:28px;height:28px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;
    background:var(--amber-mid);
    border:1px solid var(--amber);
    color:var(--amber);
    font-family:var(--mono);
    font-size:12px;
    flex:0 0 auto;
}
.section-label{
    display:flex;flex-direction:column;min-width:0;
}
.section-label strong{
    font-size:13px;
    font-family:var(--mono);
    text-transform:uppercase;
    letter-spacing:1px;
    color:var(--text-bright);
}
.section-label span{
    font-size:11px;
    color:var(--text-dim);
    margin-top:2px;
    line-height:1.35;
}
.section-chevron{
    color:var(--text-dim);
    font-size:16px;
    flex:0 0 auto;
}
.section-content{padding:0 14px 14px}

.field{margin-top:12px}
.field:first-child{margin-top:0}
.label{
    display:block;
    font-family:var(--mono);
    font-size:10px;
    letter-spacing:1.2px;
    text-transform:uppercase;
    color:var(--text-dim);
    margin:0 0 6px 2px;
}
input[type=text],input[type=number],select,textarea{
    width:100%;
    padding:13px 12px;
    background:var(--card);
    border:1px solid var(--border);
    border-radius:var(--radius-sm);
    color:var(--text);
    font-family:var(--mono);
    font-size:14px;
    outline:none;
}
input[type=text]:focus,input[type=number]:focus,select:focus,textarea:focus{
    border-color:var(--amber);
    background:var(--card-hover);
    box-shadow:0 0 0 3px rgba(245,166,35,0.12);
}
textarea{
    min-height:92px;
    resize:vertical;
    line-height:1.45;
}
select{
    appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat:no-repeat;
    background-position:right 12px center;
    padding-right:34px;
}
.help{
    margin-top:6px;
    font-size:11px;
    line-height:1.4;
    color:var(--text-dim);
}
.inline-row{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
}
.stats-grid{
    display:grid;
    grid-template-columns:1fr;
    gap:10px;
}
.stat{
    border:1px solid var(--border);
    background:var(--card);
    border-radius:var(--radius-sm);
    padding:11px 12px;
}
.stat-top{
    display:flex;align-items:flex-start;justify-content:space-between;gap:10px;
}
.stat-name{
    font-family:var(--mono);
    font-size:13px;
    color:var(--text-bright);
    word-break:break-word;
}
.stat-badge{
    font-family:var(--mono);
    font-size:10px;
    padding:2px 6px;
    border-radius:999px;
    border:1px solid var(--border-glow);
    color:var(--text-dim);
    flex:0 0 auto;
}
.stat-meta{
    margin-top:8px;
    font-family:var(--mono);
    font-size:11px;
    color:var(--text-dim);
    line-height:1.5;
}
.preview{
    background:#050a05;
    color:#4ade80;
    border:1px solid #123018;
    border-radius:var(--radius-sm);
    padding:12px;
    font-family:var(--mono);
    font-size:12px;
    line-height:1.55;
    white-space:pre-wrap;
    word-break:break-word;
    min-height:220px;
    max-height:320px;
    overflow:auto;
}
.command{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:var(--radius-sm);
    padding:12px;
    font-family:var(--mono);
    font-size:12px;
    white-space:pre-wrap;
    word-break:break-word;
    color:var(--text);
}
.bottom-bar{
    position:sticky;
    bottom:0;
    z-index:30;
    background:rgba(14,19,25,0.97);
    backdrop-filter:blur(12px);
    border-top:1px solid var(--border);
    padding:10px 12px calc(10px + env(safe-area-inset-bottom));
}
.bottom-wrap{
    max-width:1120px;
    margin:0 auto;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
}
.bottom-wrap .text-btn{width:100%}
.bottom-wrap .wide{grid-column:1 / -1}
.toast-wrap{
    position:fixed;
    right:12px;
    bottom:88px;
    z-index:9999;
    display:flex;
    flex-direction:column;
    gap:8px;
    pointer-events:none;
}
.toast{
    max-width:min(360px, calc(100vw - 24px));
    pointer-events:auto;
    border:1px solid var(--border);
    background:var(--card);
    color:var(--text);
    border-radius:var(--radius-sm);
    padding:10px 12px;
    font-family:var(--mono);
    font-size:12px;
    box-shadow:var(--shadow);
}
@media (min-width:900px){
    .main{padding:16px}
    .shell{grid-template-columns:1.05fr 0.95fr}
    .desktop-span{grid-column:1 / -1}
    .stats-grid{grid-template-columns:1fr 1fr}
    .preview{min-height:360px;max-height:520px}
    .bottom-wrap{grid-template-columns:repeat(4, minmax(0,1fr))}
    .bottom-wrap .wide{grid-column:auto}
}
@media (prefers-color-scheme: light){
    :root{
        --bg:#f6f8fa;
        --surface:#ffffff;
        --card:#ffffff;
        --card-hover:#f3f4f6;
        --border:#d1d5db;
        --border-glow:#9ca3af;
        --text:#111827;
        --text-dim:#4b5563;
        --text-bright:#000000;
        --amber:#d97706;
        --amber-dim:rgba(217,119,6,0.1);
        --amber-mid:rgba(217,119,6,0.18);
        --green:#059669;
        --red:#dc2626;
    }
}
</style>
</head>
<body>
<header>
    <div class="brand">
        <i class="bi bi-diagram-3"></i>
        <span>MD Curator Aggregate Forge</span>
    </div>
    <div class="header-actions">
        <a class="icon-btn" href="/dashboard.php" title="Dashboard"><i class="bi bi-house"></i></a>
        <button class="icon-btn" type="button" id="btnRefresh" title="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
</header>

<main class="main">
    <div class="shell">

        <div class="card desktop-span">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-phone"></i> Mobile-first control panel</div>
                <div class="card-sub" id="scopeSummaryShort">Ready</div>
            </div>
            <div class="card-body">
                <div class="help">
                    Build a JSON preset for the assembler. The runner processes unlocked documents only and keeps the AG graph logic intact.
                </div>
            </div>
        </div>

        <details class="section" open>
            <summary class="section-head">
                <div class="section-head-left">
                    <div class="section-num">1</div>
                    <div class="section-label">
                        <strong>Scope</strong>
                        <span>Choose category and limit.</span>
                    </div>
                </div>
                <div class="section-chevron"><i class="bi bi-chevron-down"></i></div>
            </summary>
            <div class="section-content">
                <div class="field">
                    <div class="label">Documentation Category</div>
                    <select id="targetCategoryId">
                        <option value="0">Process EVERYTHING</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"><?= esc($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <div class="label">Limit</div>
                    <input type="number" id="limit" min="1" step="1" value="<?= (int)$defaults['limit'] ?>">
                </div>
            </div>
        </details>

        <details class="section">
            <summary class="section-head">
                <div class="section-head-left">
                    <div class="section-num">2</div>
                    <div class="section-label">
                        <strong>Live JSON preview</strong>
                        <span>This is what the CLI runner consumes.</span>
                    </div>
                </div>
                <div class="section-chevron"><i class="bi bi-chevron-down"></i></div>
            </summary>
            <div class="section-content">
                <div class="preview" id="jsonOut"></div>
            </div>
        </details>

        <details class="section">
            <summary class="section-head">
                <div class="section-head-left">
                    <div class="section-num">3</div>
                    <div class="section-label">
                        <strong>Command</strong>
                        <span>Use the saved JSON file with the runner.</span>
                    </div>
                </div>
                <div class="section-chevron"><i class="bi bi-chevron-down"></i></div>
            </summary>
            <div class="section-content">
                <div class="stats-grid">
                    <div class="stat">
                        <div class="stat-top">
                            <div class="stat-name">CLI command</div>
                            <div class="stat-badge">runner</div>
                        </div>
                        <div class="stat-meta">
                            <div class="command" id="cmdOut">php public/cli_md_curator_aggregate_json.php --config=&lt;file.json&gt;</div>
                        </div>
                    </div>
                    <div class="stat">
                        <div class="stat-top">
                            <div class="stat-name">Current selection</div>
                            <div class="stat-badge" id="scopeBadge">—</div>
                        </div>
                        <div class="stat-meta" id="scopeSummary">Loading…</div>
                    </div>
                </div>
            </div>
        </details>

        <details class="section">
            <summary class="section-head">
                <div class="section-head-left">
                    <div class="section-num">4</div>
                    <div class="section-label">
                        <strong>Categories</strong>
                        <span>Browse available documentation categories.</span>
                    </div>
                </div>
                <div class="section-chevron"><i class="bi bi-chevron-down"></i></div>
            </summary>
            <div class="section-content">
                <div class="stats-grid" id="catStats"></div>
            </div>
        </details>

    </div>
</main>

<div class="bottom-bar">
    <div class="bottom-wrap">
        <button class="text-btn secondary wide" type="button" id="btnCopy">
            <i class="bi bi-clipboard"></i> Copy JSON
        </button>
        <button class="text-btn secondary wide" type="button" id="btnDownload">
            <i class="bi bi-download"></i> Download
        </button>
        <button class="text-btn success wide" type="button" id="btnSave">
            <i class="bi bi-save"></i> Save Preset
        </button>
        <button class="text-btn primary wide" type="button" id="btnRefreshMeta">
            <i class="bi bi-arrow-repeat"></i> Refresh Meta
        </button>
    </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const initialDefaults = <?= json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const initialCategories = <?= json_encode($categories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

let initialized = false;
let currentCategories = initialCategories || [];

function esc(s) {
    const d = document.createElement('div');
    d.textContent = String(s ?? '');
    return d.innerHTML;
}

function toast(msg, duration = 3200) {
    const wrap = document.getElementById('toastWrap');
    const el = document.createElement('div');
    el.className = 'toast';
    el.innerHTML = msg;
    wrap.appendChild(el);
    setTimeout(() => el.remove(), duration);
}

function getPayload() {
    return {
        targetCategoryId: parseInt(document.getElementById('targetCategoryId').value || '0', 10),
        limit: parseInt(document.getElementById('limit').value || '100', 10)
    };
}

function updateJson() {
    const payload = getPayload();
    document.getElementById('jsonOut').textContent = JSON.stringify(payload, null, 2);

    const catSelect = document.getElementById('targetCategoryId');
    const catText = catSelect.selectedOptions[0]?.text || 'Process EVERYTHING';

    document.getElementById('scopeBadge').textContent = payload.targetCategoryId === 0 ? 'all' : String(payload.targetCategoryId);
    document.getElementById('scopeSummaryShort').textContent = payload.targetCategoryId === 0 ? 'Process EVERYTHING' : catText;
    document.getElementById('scopeSummary').innerHTML =
        `<span class="ok">Category:</span> ${esc(catText)}<br>` +
        `<span class="ok">Limit:</span> ${payload.limit}`;
}

function renderCategories(list) {
    currentCategories = list || [];
    const select = document.getElementById('targetCategoryId');
    const prevValue = select.value;

    select.innerHTML = `<option value="0">Process EVERYTHING</option>` + currentCategories.map(c =>
        `<option value="${esc(c.id)}">${esc(c.name)}</option>`
    ).join('');

    if (prevValue && [...select.options].some(o => o.value === prevValue)) {
        select.value = prevValue;
    } else if (!initialized) {
        select.value = String(initialDefaults.targetCategoryId ?? 0);
    }

    const box = document.getElementById('catStats');
    box.innerHTML = currentCategories.map(c => `
        <div class="stat">
            <div class="stat-top">
                <div class="stat-name">${esc(c.name)}</div>
                <div class="stat-badge">#${esc(c.id)}</div>
            </div>
        </div>
    `).join('');
}

async function savePreset() {
    const name = prompt('Preset name:', 'md_curator_job');
    if (name === null) return;

    const body = new FormData();
    body.append('action', 'save_json');
    body.append('name', name);
    body.append('config_json', JSON.stringify(getPayload(), null, 2));

    const res = await fetch(location.pathname, { method: 'POST', body });
    const data = await res.json();

    if (!data.ok) {
        toast(data.error || 'Save failed');
        return;
    }

    document.getElementById('cmdOut').textContent = data.runner;
    toast(`Saved preset: ${esc(data.path)}`);
}

function downloadJson() {
    const blob = new Blob([JSON.stringify(getPayload(), null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'md_curator_job.json';
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

async function copyJson() {
    try {
        await navigator.clipboard.writeText(JSON.stringify(getPayload(), null, 2));
        toast('JSON copied to clipboard.');
    } catch (e) {
        toast('Clipboard copy failed.');
    }
}

async function loadMeta() {
    const res = await fetch('?action=meta');
    const data = await res.json();
    if (!data.ok) return;

    currentCategories = data.categories || [];
    renderCategories(currentCategories);

    if (!initialized && data.defaults) {
        document.getElementById('limit').value = String(data.defaults.limit ?? 100);
    }

    initialized = true;
    updateJson();
}

function bindEvents() {
    document.getElementById('targetCategoryId').addEventListener('change', updateJson);
    document.getElementById('limit').addEventListener('input', updateJson);

    document.getElementById('btnCopy').addEventListener('click', copyJson);
    document.getElementById('btnDownload').addEventListener('click', downloadJson);
    document.getElementById('btnSave').addEventListener('click', savePreset);
    document.getElementById('btnRefresh').addEventListener('click', loadMeta);
    document.getElementById('btnRefreshMeta').addEventListener('click', loadMeta);
}

document.addEventListener('DOMContentLoaded', async () => {
    renderCategories(currentCategories);
    bindEvents();
    await loadMeta();
});
</script>
</body>
</html>
<?php