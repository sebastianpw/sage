<?php
// public/lore_to_sketch_generator_forge_mobile.php
// Mobile-first Forge-style JSON builder for Lore -> Sketch generation
// Designed for Android Chrome:
// - single-column layout
// - sticky action bar
// - collapsible sections
// - large touch targets
// - live JSON preview
// - exports JSON for the CLI runner

require_once __DIR__ . '/bootstrap.php';

$em   = $spw->getEntityManager();
$conn = $em->getConnection();

function normalizeTags($tags): array {
    if (is_string($tags)) {
        $tags = array_map('trim', explode(',', $tags));
    }
    if (!is_array($tags)) return [];
    $tags = array_values(array_filter(array_map('trim', $tags), fn($v) => $v !== ''));
    return array_values(array_unique($tags));
}

function payloadFromPost(array $post): array {
    return [
        'doc_id' => (int)($post['doc_id'] ?? 0),
        'group_key' => trim((string)($post['group_key'] ?? '')),
        'offset' => (int)($post['offset'] ?? 0),
        'amount' => ($post['amount'] ?? '') === '' ? null : (int)$post['amount'],
        'generator_config_id' => (string)($post['generator_config_id'] ?? ''),
        'tags' => normalizeTags($post['tags'] ?? []),
        'confirm' => !empty($post['confirm']),
        'delay_us' => (int)($post['delay_us'] ?? 500000),
        'dry_run' => !empty($post['dry_run']),
    ];
}

function loadDocGroups($spw, int $docId): array {
    $loreService = new \App\Service\LoreAccessService($spw->getPdo());
    $loreService->loadDoc($docId);

    $categories = ['characters', 'locations', 'factions', 'artifacts', 'episodes', 'scene_hooks'];
    $storyEngine = $loreService->getStoryEngine();
    $available = [];

    foreach ($categories as $cat) {
        $count = 0;
        if (in_array($cat, ['episodes', 'scene_hooks'], true)) {
            $count = count($storyEngine[$cat] ?? []);
        } else {
            $count = count($loreService->queryEntities($cat));
        }

        if ($count > 0) {
            $available[] = [
                'key' => $cat,
                'count' => $count
            ];
        }
    }

    return $available;
}

function getDocPreviewData($conn): array {
    $docs = $conn->fetchAllAssociative("
        SELECT d.id, d.name, d.keywords, c.name as cat_name 
        FROM documentations d 
        JOIN md_doc_analysis da ON d.id = da.doc_id 
        LEFT JOIN documentation_categories c ON d.category_id = c.id
        WHERE d.is_active = 1
        ORDER BY d.updated_at DESC
    ");

    return $docs ?: [];
}

if (isset($_GET['action']) && $_GET['action'] === 'meta') {
    header('Content-Type: application/json; charset=utf-8');

    $docId = (int)($_GET['doc_id'] ?? 0);
    $docs = getDocPreviewData($conn);

    $groups = [];
    if ($docId > 0) {
        try {
            $groups = loadDocGroups($spw, $docId);
        } catch (Throwable $e) {
            $groups = [];
        }
    }

    $generatorConfigs = $conn->fetchAllAssociative(
        "SELECT config_id, title FROM generator_config WHERE active = 1 ORDER BY title ASC"
    );

    echo json_encode([
        'ok' => true,
        'docs' => $docs,
        'groups' => $groups,
        'configs' => $generatorConfigs,
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

    $dir = __DIR__ . '/../var/lore_sketch_configs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $name = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', trim((string)($_POST['name'] ?? 'lore_sketch_job')));
    $file = $dir . '/' . $name . '_' . date('Ymd_His') . '.json';

    $ok = @file_put_contents($file, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if ($ok === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write config file.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'saved' => true,
        'path' => $file,
        'runner' => 'php public/cli_lore_to_sketch_generator_json.php --config=' . $file,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$docs = getDocPreviewData($conn);
$generatorConfigs = $conn->fetchAllAssociative(
    "SELECT config_id, title FROM generator_config WHERE active = 1 ORDER BY title ASC"
);

$defaultPayload = [
    'doc_id' => $docs[0]['id'] ?? 0,
    'group_key' => '',
    'offset' => 0,
    'amount' => '',
    'generator_config_id' => $generatorConfigs[0]['config_id'] ?? '',
    'tags' => '',
    'confirm' => false,
    'delay_us' => 500000,
    'dry_run' => false,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Lore Sketch JSON Forge</title>
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
    --amber-glow:rgba(245,166,35,0.35);
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
    border:1px solid var(--amber-glow);
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
.pill-row{
    display:flex;flex-wrap:wrap;gap:8px;
}
.pill{
    display:flex;align-items:center;gap:8px;
    padding:11px 12px;
    background:var(--card);
    border:1px solid var(--border);
    border-radius:999px;
    font-family:var(--mono);
    font-size:12px;
    min-height:42px;
}
.pill input{margin:0}

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

.hidden{display:none !important}

@media (min-width:900px){
    .main{padding:16px}
    .shell{grid-template-columns:1.05fr 0.95fr}
    .desktop-span{grid-column:1 / -1}
    .stats-grid{grid-template-columns:1fr 1fr}
    .preview{min-height:360px;max-height:520px}
    .bottom-wrap{
        grid-template-columns:repeat(4, minmax(0,1fr));
    }
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
        --amber-glow:rgba(217,119,6,0.28);
        --green:#059669;
        --red:#dc2626;
    }
}
</style>
</head>
<body>
<header>
    <div class="brand">
        <i class="bi bi-journal-text"></i>
        <span>Lore Sketch JSON Forge</span>
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
                    Build a JSON preset here, then feed it into the CLI runner. The layout is optimized for Android Chrome and touch input.
                </div>
            </div>
        </div>

        <details class="section" open>
            <summary class="section-head">
                <div class="section-head-left">
                    <div class="section-num">1</div>
                    <div class="section-label">
                        <strong>Document</strong>
                        <span>Choose the lore document that provides context.</span>
                    </div>
                </div>
                <div class="section-chevron"><i class="bi bi-chevron-down"></i></div>
            </summary>
            <div class="section-content">
                <div class="field">
                    <div class="label">Lore Document</div>
                    <select id="doc_id"></select>
                </div>

                <div class="field">
                    <div class="label">Document Keywords</div>
                    <div class="command" id="docKeywordsBox">—</div>
                    <div class="help">If no tags are entered, the runner falls back to these keywords.</div>
                </div>
            </div>
        </details>

        <details class="section" open>
            <summary class="section-head">
                <div class="section-head-left">
                    <div class="section-num">2</div>
                    <div class="section-label">
                        <strong>Entity Group and Range</strong>
                        <span>Select the entity family, offset, and amount.</span>
                    </div>
                </div>
                <div class="section-chevron"><i class="bi bi-chevron-down"></i></div>
            </summary>
            <div class="section-content">
                <div class="field">
                    <div class="label">Entity Group</div>
                    <select id="group_key"></select>
                </div>

                <div class="inline-row">
                    <div class="field">
                        <div class="label">Offset</div>
                        <input type="number" id="offset" min="0" step="1" value="0">
                    </div>
                    <div class="field">
                        <div class="label">Amount</div>
                        <input type="text" id="amount" placeholder="blank = all remaining">
                    </div>
                </div>

                <div class="field">
                    <div class="label">Delay in microseconds</div>
                    <input type="number" id="delay_us" min="0" step="1000" value="500000">
                </div>

                <div class="field">
                    <div class="pill-row">
                        <label class="pill"><input type="checkbox" id="confirm"> Store confirm=true in JSON</label>
                        <label class="pill"><input type="checkbox" id="dry_run"> Dry run</label>
                    </div>
                </div>
            </div>
        </details>

        <details class="section" open>
            <summary class="section-head">
                <div class="section-head-left">
                    <div class="section-num">3</div>
                    <div class="section-label">
                        <strong>Generator and Tags</strong>
                        <span>Select the generator config and attach tags.</span>
                    </div>
                </div>
                <div class="section-chevron"><i class="bi bi-chevron-down"></i></div>
            </summary>
            <div class="section-content">
                <div class="field">
                    <div class="label">Generator Config</div>
                    <select id="generator_config_id"></select>
                </div>

                <div class="field">
                    <div class="label">Tags</div>
                    <input type="text" id="tags" placeholder="comma-separated tags">
                    <div class="help">Leave blank to use the document keywords automatically.</div>
                </div>
            </div>
        </details>

        <details class="section">
            <summary class="section-head">
                <div class="section-head-left">
                    <div class="section-num">4</div>
                    <div class="section-label">
                        <strong>Live JSON Preview</strong>
                        <span>This is what the CLI runner will consume.</span>
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
                    <div class="section-num">5</div>
                    <div class="section-label">
                        <strong>Scope Preview</strong>
                        <span>Available groups update when the document changes.</span>
                    </div>
                </div>
                <div class="section-chevron"><i class="bi bi-chevron-down"></i></div>
            </summary>
            <div class="section-content">
                <div class="stats-grid">
                    <div class="stat">
                        <div class="stat-top">
                            <div class="stat-name">Current Scope</div>
                            <div class="stat-badge" id="scopeBadge">—</div>
                        </div>
                        <div class="stat-meta" id="scopeSummary">Loading…</div>
                    </div>
                    <div class="stat">
                        <div class="stat-top">
                            <div class="stat-name">Command</div>
                            <div class="stat-badge">CLI</div>
                        </div>
                        <div class="stat-meta">
                            <div class="command" id="cmdOut">php public/cli_lore_to_sketch_generator_json.php --config=&lt;file.json&gt;</div>
                        </div>
                    </div>
                </div>

                <div style="height:10px"></div>

                <div class="stat">
                    <div class="stat-top">
                        <div class="stat-name">Documents</div>
                        <div class="stat-badge" id="docCountBadge">—</div>
                    </div>
                    <div class="stat-meta">
                        <div id="docStats" class="stats-grid"></div>
                    </div>
                </div>

                <div style="height:10px"></div>

                <div class="stat">
                    <div class="stat-top">
                        <div class="stat-name">Entity Groups</div>
                        <div class="stat-badge" id="groupCountBadge">—</div>
                    </div>
                    <div class="stat-meta">
                        <div id="groupStats" class="stats-grid"></div>
                    </div>
                </div>
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
const initialPayload = <?= json_encode($defaultPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

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
    const amountRaw = document.getElementById('amount').value.trim();
    const tags = document.getElementById('tags').value
        .split(',')
        .map(s => s.trim())
        .filter(Boolean);

    return {
        doc_id: parseInt(document.getElementById('doc_id').value || '0', 10),
        group_key: document.getElementById('group_key').value || '',
        offset: parseInt(document.getElementById('offset').value || '0', 10),
        amount: amountRaw === '' ? null : parseInt(amountRaw, 10),
        generator_config_id: document.getElementById('generator_config_id').value || '',
        tags: tags,
        confirm: document.getElementById('confirm').checked,
        delay_us: parseInt(document.getElementById('delay_us').value || '500000', 10),
        dry_run: document.getElementById('dry_run').checked
    };
}

function updateJson() {
    const payload = getPayload();
    document.getElementById('jsonOut').textContent = JSON.stringify(payload, null, 2);

    const docText = document.getElementById('doc_id').selectedOptions[0]?.text || '';
    const groupText = document.getElementById('group_key').value || '—';
    const tagText = payload.tags.length ? payload.tags.join(', ') : 'document keywords';
    const amountText = payload.amount === null ? 'all remaining' : payload.amount;

    document.getElementById('scopeBadge').textContent = groupText;
    document.getElementById('scopeSummaryShort').textContent = groupText;

    document.getElementById('scopeSummary').innerHTML =
        `<span class="ok">Document:</span> ${esc(docText)}<br>` +
        `<span class="ok">Group:</span> ${esc(groupText)}<br>` +
        `<span class="ok">Offset:</span> ${payload.offset}<br>` +
        `<span class="ok">Amount:</span> ${amountText}<br>` +
        `<span class="ok">Tags:</span> ${esc(tagText)}`;

    document.getElementById('cmdOut').textContent =
        'php public/cli_lore_to_sketch_generator_json.php --config=/path/to/generated.json';
}

async function loadMeta() {
    const docId = parseInt(document.getElementById('doc_id').value || '0', 10);
    const res = await fetch(`?action=meta&doc_id=${docId}`);
    const data = await res.json();
    if (!data.ok) return;

    const docBox = document.getElementById('docStats');
    const groupBox = document.getElementById('groupStats');

    document.getElementById('docCountBadge').textContent = `${data.docs.length}`;
    document.getElementById('groupCountBadge').textContent = `${data.groups.length}`;

    docBox.innerHTML = data.docs.map(d => `
        <div class="stat">
            <div class="stat-top">
                <div class="stat-name">${esc(d.name)}</div>
                <div class="stat-badge">#${d.id}</div>
            </div>
            <div class="stat-meta">
                ${(d.cat_name || 'Uncategorized')}<br>
                <span class="ok">keywords:</span> ${esc(d.keywords || '—')}
            </div>
        </div>
    `).join('');

    const groupSelect = document.getElementById('group_key');
    const previous = groupSelect.value;
    groupSelect.innerHTML = data.groups.map(g =>
        `<option value="${esc(g.key)}">${esc(g.key)} (${g.count})</option>`
    ).join('');

    if (previous && [...groupSelect.options].some(o => o.value === previous)) {
        groupSelect.value = previous;
    } else if (groupSelect.options.length > 0) {
        groupSelect.selectedIndex = 0;
    }

    groupBox.innerHTML = data.groups.map(g => `
        <div class="stat">
            <div class="stat-top">
                <div class="stat-name">${esc(g.key)}</div>
                <div class="stat-badge">${g.count}</div>
            </div>
            <div class="stat-meta">Available in selected document.</div>
        </div>
    `).join('');

    const doc = data.docs.find(d => parseInt(d.id, 10) === docId) || data.docs[0];
    document.getElementById('docKeywordsBox').textContent = doc?.keywords || '—';

    updateJson();
}

function fillDocSelect(docs) {
    const select = document.getElementById('doc_id');
    select.innerHTML = docs.map(d =>
        `<option value="${esc(d.id)}">${esc(d.name)}${d.cat_name ? ' — ' + esc(d.cat_name) : ''}</option>`
    ).join('');
}

function fillGeneratorSelect(configs) {
    const select = document.getElementById('generator_config_id');
    select.innerHTML = configs.map(c =>
        `<option value="${esc(c.config_id)}">${esc(c.title)}</option>`
    ).join('');
}

async function savePreset() {
    const name = prompt('Preset name:', 'lore_sketch_job');
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
    a.download = 'lore_sketch_job.json';
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

function bindEvents() {
    document.getElementById('doc_id').addEventListener('change', async () => {
        await loadMeta();
    });

    ['group_key','offset','amount','generator_config_id','tags','delay_us']
        .forEach(id => {
            document.getElementById(id).addEventListener('input', updateJson);
            document.getElementById(id).addEventListener('change', updateJson);
        });

    document.getElementById('confirm').addEventListener('change', updateJson);
    document.getElementById('dry_run').addEventListener('change', updateJson);

    document.getElementById('btnCopy').addEventListener('click', copyJson);
    document.getElementById('btnDownload').addEventListener('click', downloadJson);
    document.getElementById('btnSave').addEventListener('click', savePreset);
    document.getElementById('btnRefresh').addEventListener('click', loadMeta);
    document.getElementById('btnRefreshMeta').addEventListener('click', loadMeta);
}

async function init() {
    const meta = await fetch('?action=meta&doc_id=' + encodeURIComponent(initialPayload.doc_id || 0));
    const data = await meta.json();

    if (data.ok) {
        fillDocSelect(data.docs || []);
        fillGeneratorSelect(data.configs || []);

        if (initialPayload.doc_id) {
            document.getElementById('doc_id').value = String(initialPayload.doc_id);
        }
        if (initialPayload.generator_config_id) {
            document.getElementById('generator_config_id').value = String(initialPayload.generator_config_id);
        }
    }

    document.getElementById('offset').value = initialPayload.offset;
    document.getElementById('amount').value = initialPayload.amount === null ? '' : initialPayload.amount;
    document.getElementById('tags').value = Array.isArray(initialPayload.tags) ? initialPayload.tags.join(', ') : '';
    document.getElementById('confirm').checked = !!initialPayload.confirm;
    document.getElementById('delay_us').value = initialPayload.delay_us;
    document.getElementById('dry_run').checked = !!initialPayload.dry_run;

    bindEvents();
    await loadMeta();
    updateJson();
}

document.addEventListener('DOMContentLoaded', init);
</script>
</body>
</html>
<?php