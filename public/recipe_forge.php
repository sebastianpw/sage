<?php
// public/recipe_forge.php
// ─────────────────────────────────────────────────────────────────────────────
// RECIPE FORGE
// World-class recipe management UI based on the Forge design system.
// Includes full search across ALL recipes, ingredients viewer, rerun command
// copy, and delete — with the API onboard in the same file.
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }

// ─────────────────────────────────────────────────────────────────────────────
// ONBOARD API
// Handled before any output so we can emit clean JSON and exit.
// ─────────────────────────────────────────────────────────────────────────────
if (isset($_GET['api']) || isset($_POST['api'])) {
    header('Content-Type: application/json');
    $pdoSys = $spw->getSysPDO();

    $raw    = file_get_contents('php://input');
    $req    = json_decode($raw, true) ?: [];
    $action = $req['action'] ?? $_GET['action'] ?? '';

    function rf_json($ok, $data = null, $error = null) {
        echo json_encode(['ok' => $ok, 'data' => $data, 'error' => $error]);
        exit;
    }

    try {
        switch ($action) {

            // ── list_recipes ──────────────────────────────────────────────
            // Returns ALL recipes (no LIMIT) with ingredient count.
            // Ingredients text is aggregated here in SQL so the client gets
            // everything it needs for full-text search in a single round-trip.
            case 'list_recipes':
                $sql = "
                    SELECT
                        r.id,
                        r.output_filename,
                        r.rerun_command,
                        r.created_at,
                        rg.name  AS group_name,
                        (SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = r.id) AS ingredient_count,
                        (SELECT GROUP_CONCAT(source_filename ORDER BY display_order ASC SEPARATOR ' ')
                         FROM recipe_ingredients WHERE recipe_id = r.id) AS ingredients_text
                    FROM recipes r
                    JOIN recipe_groups rg ON r.recipe_group_id = rg.id
                    ORDER BY r.created_at DESC
                ";
                $stmt    = $pdoSys->query($sql);
                $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                rf_json(true, $recipes);
                break;

            // ── get_recipe_details ────────────────────────────────────────
            case 'get_recipe_details':
                $id = (int)($req['id'] ?? $_GET['id'] ?? 0);
                if (!$id) throw new Exception('Missing Recipe ID');

                $sql  = "
                    SELECT ri.source_filename, ris.content_hash
                    FROM recipe_ingredients ri
                    JOIN recipe_ingredient_snapshots ris ON ri.snapshot_id = ris.id
                    WHERE ri.recipe_id = ?
                    ORDER BY ri.display_order ASC
                ";
                $stmt = $pdoSys->prepare($sql);
                $stmt->execute([$id]);
                $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                rf_json(true, $ingredients);
                break;

            // ── delete_recipe ─────────────────────────────────────────────
            case 'delete_recipe':
                $id = (int)($req['id'] ?? 0);
                if (!$id) throw new Exception('Missing Recipe ID');
                $stmt = $pdoSys->prepare("DELETE FROM recipes WHERE id = ?");
                $stmt->execute([$id]);
                rf_json(true, null);
                break;

            default:
                throw new Exception('Unknown action: ' . htmlspecialchars($action));
        }
    } catch (Exception $e) {
        http_response_code(400);
        rf_json(false, null, $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// HTML OUTPUT
// ─────────────────────────────────────────────────────────────────────────────
$viewportScale = !empty($_GET['embed']) ? '1.0' : '0.9';
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= $viewportScale ?>, viewport-fit=cover">
<title>Recipe Forge</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark')  document.documentElement.setAttribute('data-theme', 'dark');
      else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
    } catch (e) {}
  })();
</script>

<style>
/* ═══════════════════════════════════════════════════════════════════════════
   FORGE — Design System (mirrors scheduler_forge.php exactly)
═══════════════════════════════════════════════════════════════════════════ */
:root {
    --bg:           #080b10;
    --surface:      #0e1319;
    --card:         #111820;
    --card-hover:   #141e28;
    --border:       #1c2535;
    --border-glow:  #2a3a52;
    --text:         #c8d4e8;
    --text-dim:     #5a6a80;
    --text-bright:  #e8f0ff;
    --amber:        #f5a623;
    --amber-dim:    rgba(245, 166, 35, 0.08);
    --amber-mid:    rgba(245, 166, 35, 0.15);
    --amber-glow:   rgba(245, 166, 35, 0.4);
    --green:        #22d3a0;
    --green-dim:    rgba(34, 211, 160, 0.1);
    --red:          #f05060;
    --red-dim:      rgba(240, 80, 96, 0.1);
    --blue:         #4da6ff;
    --blue-dim:     rgba(77, 166, 255, 0.1);
    --mono:         'Space Mono', 'Fira Mono', monospace;
    --sans:         'Syne', system-ui, sans-serif;
    --radius:       6px;
    --radius-lg:    10px;
}

@media (prefers-color-scheme: light) {
    :root {
        --bg: #f6f8fa; --surface: #e1e4e8; --card: #ffffff; --card-hover: #f3f4f6;
        --border: #d1d5db; --border-glow: #9ca3af; --text: #111827; --text-dim: #4b5563;
        --text-bright: #000000; --amber: #d97706; --amber-dim: rgba(217,119,6,0.1);
        --amber-mid: rgba(217,119,6,0.2); --amber-glow: rgba(217,119,6,0.4);
        --green: #059669; --green-dim: rgba(5,150,105,0.1);
        --red: #dc2626; --red-dim: rgba(220,38,38,0.1);
        --blue: #2563eb; --blue-dim: rgba(37,99,235,0.1);
    }
}
:root[data-theme="light"], html[data-theme="light"], body[data-theme="light"] {
    --bg: #f6f8fa; --surface: #e1e4e8; --card: #ffffff; --card-hover: #f3f4f6;
    --border: #d1d5db; --border-glow: #9ca3af; --text: #111827; --text-dim: #4b5563;
    --text-bright: #000000; --amber: #d97706; --amber-dim: rgba(217,119,6,0.1);
    --amber-mid: rgba(217,119,6,0.2); --amber-glow: rgba(217,119,6,0.4);
    --green: #059669; --green-dim: rgba(5,150,105,0.1);
    --red: #dc2626; --red-dim: rgba(220,38,38,0.1);
    --blue: #2563eb; --blue-dim: rgba(37,99,235,0.1);
}
:root[data-theme="dark"], html[data-theme="dark"], body[data-theme="dark"] {
    --bg: #080b10; --surface: #0e1319; --card: #111820; --card-hover: #141e28;
    --border: #1c2535; --border-glow: #2a3a52; --text: #c8d4e8; --text-dim: #5a6a80;
    --text-bright: #e8f0ff; --amber: #f5a623; --amber-dim: rgba(245,166,35,0.08);
    --amber-mid: rgba(245,166,35,0.15); --amber-glow: rgba(245,166,35,0.4);
    --green: #22d3a0; --green-dim: rgba(34,211,160,0.1);
    --red: #f05060; --red-dim: rgba(240,80,96,0.1);
    --blue: #4da6ff; --blue-dim: rgba(77,166,255,0.1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--sans); font-size: 14px; line-height: 1.5; -webkit-font-smoothing: antialiased; overflow: hidden; }

::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-dim); }

/* ── LAYOUT ── */
.forge-layout {
    display: grid;
    grid-template-rows: 52px 1fr;
    grid-template-columns: 320px 1fr;
    grid-template-areas: "header header" "sidebar main";
    height: 100vh;
    height: 100dvh;
    overflow: hidden;
}

/* ── HEADER ── */
.forge-header { grid-area: header; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; background: var(--surface); border-bottom: 1px solid var(--border); position: relative; z-index: 100; }
.forge-logo { display: flex; align-items: center; gap: 10px; font-family: var(--mono); font-size: 0.85rem; font-weight: 700; color: var(--amber); letter-spacing: 2px; text-transform: uppercase; }
.forge-logo-icon { width: 28px; height: 28px; background: var(--amber-mid); border: 1px solid var(--amber-glow); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 14px; }
.forge-header-right { display: flex; align-items: center; gap: 10px; }
.forge-header-stat { display: flex; align-items: center; font-family: var(--mono); font-size: 0.7rem; color: var(--text-dim); padding: 4px 10px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); }
.forge-header-stat span { color: var(--amber); margin-right: 4px; }

/* ── SIDEBAR ── */
.forge-sidebar { grid-area: sidebar; background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }
.sidebar-search { padding: 12px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.sidebar-search-input { width: 100%; padding: 8px 10px 8px 32px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.8rem; transition: border-color 0.2s; }
.sidebar-search-input:focus { outline: none; border-color: var(--amber); }
.sidebar-search-wrap { position: relative; }
.sidebar-search-wrap::before { content: '⌕'; position: absolute; left: 8px; top: 50%; transform: translateY(-50%); color: var(--text-dim); font-size: 16px; pointer-events: none; z-index: 1; }
.sidebar-list { flex: 1; overflow-y: auto; padding: 8px; }
.sidebar-count { padding: 4px 12px 8px; font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim); flex-shrink: 0; }

.recipe-card { padding: 8px 10px 8px 12px; border-radius: var(--radius); border: 1px solid transparent; cursor: pointer; transition: all 0.15s; margin-bottom: 3px; position: relative; background: transparent; display: flex; align-items: center; gap: 8px; }
.recipe-card:hover { background: var(--card); border-color: var(--border); }
.recipe-card.active { background: var(--amber-dim); border-color: var(--amber); }
.recipe-card.active .recipe-card-title { color: var(--amber); }
.recipe-card-indicator { position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 2px; height: 0; background: var(--amber); border-radius: 0 2px 2px 0; transition: height 0.2s; }
.recipe-card.active .recipe-card-indicator { height: 60%; }
.recipe-card-body { flex: 1; min-width: 0; }
.recipe-card-title { font-family: var(--sans); font-weight: 600; font-size: 0.85rem; color: var(--text-bright); line-height: 1.3; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; transition: color 0.15s; }
.recipe-card-meta { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }

.gen-badge { font-family: var(--mono); font-size: 0.65rem; padding: 1px 5px; border-radius: 3px; border: 1px solid; }
.gen-badge.model { border-color: var(--border-glow); color: var(--text-dim); background: var(--card); }
.gen-badge.db { border-color: var(--blue); color: var(--blue); background: var(--blue-dim); }

.recipe-card-delete { flex-shrink: 0; width: 28px; height: 28px; border-radius: var(--radius); border: 1px solid transparent; background: transparent; color: var(--text-dim); display: flex; align-items: center; justify-content: center; font-size: 13px; cursor: pointer; transition: all 0.15s; opacity: 0; }
.recipe-card:hover .recipe-card-delete { opacity: 1; }
.recipe-card-delete:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); opacity: 1; }
.sidebar-loading { text-align: center; padding: 40px 20px; color: var(--text-dim); font-family: var(--mono); font-size: 0.8rem; }
.sidebar-loading-spinner { width: 28px; height: 28px; margin: 0 auto 12px; border: 3px solid rgba(245,166,35,0.15); border-top-color: var(--amber); border-radius: 50%; animation: spin 0.75s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── MAIN ── */
.forge-main { grid-area: main; display: flex; flex-direction: column; overflow: hidden; background: var(--bg); }
.forge-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; padding: 40px; color: var(--text-dim); }
.forge-empty-icon { font-size: 48px; opacity: 0.3; filter: grayscale(1); }
.forge-empty-title { font-family: var(--mono); font-size: 1rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 2px; }
.forge-empty-sub { font-family: var(--mono); font-size: 0.75rem; color: var(--text-dim); opacity: 0.7; }

/* ── WORKSPACE ── */
.forge-workspace { flex: 1; display: flex; flex-direction: column; overflow: hidden; display: none; }
.workspace-header { padding: 16px 20px; border-bottom: 1px solid var(--border); background: var(--surface); flex-shrink: 0; display: flex; align-items: flex-start; gap: 12px; }
.workspace-title-block { flex: 1; min-width: 0; }
.workspace-title { font-family: var(--sans); font-size: 1.1rem; font-weight: 700; color: var(--text-bright); line-height: 1.2; margin-bottom: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.workspace-meta { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.workspace-header-actions { display: flex; gap: 6px; flex-shrink: 0; align-items: center; }

/* ── DETAIL PANEL (full-width, scrollable) ── */
.detail-panel { flex: 1; overflow-y: auto; padding: 20px; }

.panel-label { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 14px; display: flex; align-items: center; gap: 6px; }
.panel-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* Info grid */
.info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 24px; }
.info-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px; }
.info-card-label { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
.info-card-value { font-family: var(--mono); font-size: 0.85rem; color: var(--text-bright); word-break: break-all; }

/* Ingredients table */
.ingredients-table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; margin-bottom: 24px; }
.forge-table { width: 100%; border-collapse: collapse; font-family: var(--mono); font-size: 0.75rem; text-align: left; }
.forge-table th { padding: 8px 12px; border-bottom: 1px solid var(--border); color: var(--text-dim); font-weight: normal; text-transform: uppercase; letter-spacing: 1px; background: var(--surface); }
.forge-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); color: var(--text); }
.forge-table tr:last-child td { border-bottom: none; }
.forge-table tr:hover td { background: var(--card-hover); }

/* Rerun command block */
.rerun-block { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 24px; overflow: hidden; }
.rerun-block-header { padding: 10px 14px; background: var(--surface); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.rerun-block-label { font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; }
.rerun-command-pre { padding: 14px; font-family: var(--mono); font-size: 0.78rem; color: var(--green); white-space: pre-wrap; word-break: break-all; line-height: 1.7; }

/* Buttons */
.btn-forge-primary { padding: 8px 18px; background: var(--amber); color: #000; border: none; border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; transition: all 0.15s; }
.btn-forge-primary:hover { filter: brightness(1.1); }
.btn-forge-secondary { padding: 8px 18px; background: transparent; color: var(--text-dim); border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.78rem; transition: all 0.15s; }
.btn-forge-secondary:hover { border-color: var(--border-glow); color: var(--text); }
.btn-forge-danger { padding: 8px 18px; background: var(--red-dim); color: var(--red); border: 1px solid var(--red); border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.78rem; transition: all 0.15s; }
.btn-forge-danger:hover { background: var(--red); color: #fff; }
.btn-icon-sm { width: 36px; height: 36px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 15px; text-decoration: none; }
.btn-icon-sm:hover { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }

/* ── TOAST ── */
.forge-toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
.forge-toast { padding: 10px 16px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--border); font-family: var(--mono); font-size: 0.8rem; color: var(--text); box-shadow: 0 4px 20px rgba(0,0,0,0.5); animation: toastIn 0.25s ease; pointer-events: all; cursor: pointer; max-width: 320px; display: flex; align-items: center; gap: 8px; }
.forge-toast.success { border-color: var(--green); }
.forge-toast.error   { border-color: var(--red); color: var(--red); }
.forge-toast.info    { border-color: var(--amber); }
.forge-toast.out     { animation: toastOut 0.25s ease forwards; }

@keyframes toastIn  { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes toastOut { to   { opacity: 0; transform: translateY(10px); } }
@keyframes modalIn  { from { opacity: 0; transform: scale(0.96) translateY(-10px); } to { opacity: 1; transform: none; } }

/* ── MOBILE ── */
@media (max-width: 900px) {
    .forge-layout { grid-template-columns: 1fr; grid-template-rows: 52px 200px 1fr; grid-template-areas: "header" "sidebar" "main"; }
    .forge-sidebar { border-right: none; border-bottom: 1px solid var(--border); }
    .info-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 480px) {
    .info-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="forge-layout">

    <!-- ── HEADER ── -->
    <header class="forge-header">
        <div class="forge-logo">
            <div class="forge-logo-icon"><i class="bi bi-journal-code"></i></div>
            Recipe Forge
        </div>
        <div class="forge-header-right">
            <div class="forge-header-stat" title="Total recipes loaded">
                <span id="statTotal">—</span> recipes
            </div>
            <div class="forge-header-stat" id="statFiltered" style="display:none;" title="Filtered count">
                <span id="statFilteredCount">—</span> shown
            </div>
            <a href="/dashboard.php" class="btn-icon-sm" title="Back to Dashboard" style="text-decoration:none;">
                <i class="bi bi-house"></i>
            </a>
        </div>
    </header>

    <!-- ── SIDEBAR ── -->
    <aside class="forge-sidebar">
        <div class="sidebar-search">
            <div class="sidebar-search-wrap">
                <input
                    type="text"
                    class="sidebar-search-input"
                    id="sidebarSearch"
                    placeholder="Search all recipes…"
                    autocomplete="off"
                >
            </div>
        </div>
        <div class="sidebar-count" id="sidebarCount"></div>
        <div class="sidebar-list" id="sidebarList">
            <div class="sidebar-loading">
                <div class="sidebar-loading-spinner"></div>
                Loading recipes…
            </div>
        </div>
    </aside>

    <!-- ── MAIN ── -->
    <main class="forge-main" id="forgeMain">

        <div class="forge-empty" id="forgeEmpty">
            <div class="forge-empty-icon"><i class="bi bi-journal-code"></i></div>
            <div class="forge-empty-title">Select a Recipe</div>
            <div class="forge-empty-sub">Choose from the sidebar to inspect</div>
        </div>

        <div class="forge-workspace" id="forgeWorkspace">

            <div class="detail-panel" id="detailPanel">
                <!-- populated by JS -->
            </div>

        </div><!-- /forge-workspace -->
    </main>

</div><!-- /forge-layout -->

<!-- ── TOAST CONTAINER ── -->
<div class="forge-toast-container" id="toastContainer"></div>

<script>
const RecipeForge = (() => {
    'use strict';

    const API = 'recipe_forge.php?api=1';

    let _recipes   = [];   // full dataset (ALL records)
    let _filtered  = [];   // current search results
    let _current   = null; // selected recipe object
    let _searchTimeout = null;

    // ── helpers ──────────────────────────────────────────────────────────────

    async function api(action, data = {}) {
        const res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...data })
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    function toast(msg, type = 'info', duration = 3000) {
        const el = document.createElement('div');
        el.className = `forge-toast ${type}`;
        const icons = { success: '✓', error: '✕', info: '◆' };
        el.innerHTML = `<span style="font-size:12px;">${icons[type] || '◆'}</span> ${msg}`;
        el.onclick = () => dismiss(el);
        document.getElementById('toastContainer').appendChild(el);
        function dismiss(e) { e.classList.add('out'); setTimeout(() => e.remove(), 300); }
        setTimeout(() => dismiss(el), duration);
    }

    function esc(str) {
        if (str == null) return '';
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    function fmtDate(str) {
        if (!str) return '—';
        return new Date(str + 'Z').toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    }

    // ── init ─────────────────────────────────────────────────────────────────

    async function init() {
        bindEvents();
        await loadRecipes();
    }

    // ── load ALL recipes in one shot ─────────────────────────────────────────
    // ingredients_text is returned by the server already aggregated, so there
    // are zero extra round-trips and no artificial 100-record cap.

    async function loadRecipes() {
        try {
            const r = await api('list_recipes');
            if (!r.ok) throw new Error(r.error || 'Failed to load');
            _recipes  = r.data || [];
            _filtered = _recipes;
            document.getElementById('statTotal').textContent = _recipes.length;
            renderSidebar();
        } catch (e) {
            document.getElementById('sidebarList').innerHTML =
                `<div class="sidebar-empty"><i class="bi bi-exclamation-triangle" style="font-size:2rem; margin-bottom:8px; display:block;"></i>Failed to load recipes</div>`;
            toast(e.message, 'error');
        }
    }

    // ── sidebar render ────────────────────────────────────────────────────────

    function renderSidebar() {
        const list = document.getElementById('sidebarList');
        const countEl = document.getElementById('sidebarCount');
        const statFiltered = document.getElementById('statFiltered');
        const statFilteredCount = document.getElementById('statFilteredCount');

        const searchTerm = document.getElementById('sidebarSearch').value.trim();

        if (searchTerm) {
            statFiltered.style.display = 'flex';
            statFilteredCount.textContent = _filtered.length;
        } else {
            statFiltered.style.display = 'none';
        }

        countEl.textContent = searchTerm
            ? `${_filtered.length} of ${_recipes.length} recipes`
            : `${_recipes.length} recipes`;

        if (_filtered.length === 0) {
            list.innerHTML = `<div class="sidebar-empty">
                <div style="font-size:2rem; margin-bottom:8px;"><i class="bi bi-search"></i></div>
                No recipes match
            </div>`;
            return;
        }

        list.innerHTML = _filtered.map(r => {
            const isActive = _current && _current.id === r.id;
            const ingCount = parseInt(r.ingredient_count) || 0;

            return `
            <div class="recipe-card${isActive ? ' active' : ''}" data-id="${r.id}">
                <div class="recipe-card-indicator"></div>
                <div class="recipe-card-body">
                    <div class="recipe-card-title">${esc(r.group_name)}</div>
                    <div class="recipe-card-meta">
                        <span class="gen-badge model">${ingCount} ing</span>
                        <span class="gen-badge model">${esc(r.output_filename)}</span>
                    </div>
                </div>
                <button class="recipe-card-delete" data-id="${r.id}" title="Delete recipe" onclick="event.stopPropagation();">
                    <i class="bi bi-trash"></i>
                </button>
            </div>`;
        }).join('');
    }

    // ── search — pure JS, zero extra fetches ─────────────────────────────────

    function applySearch(term) {
        const t = term.toLowerCase().trim();
        if (!t) {
            _filtered = _recipes;
        } else {
            _filtered = _recipes.filter(r => {
                return (
                    (r.group_name      || '').toLowerCase().includes(t) ||
                    (r.output_filename || '').toLowerCase().includes(t) ||
                    (r.ingredients_text|| '').toLowerCase().includes(t)
                );
            });
        }
        renderSidebar();

        // If current selection got filtered out, keep showing it (don't reset workspace)
    }

    // ── select recipe ─────────────────────────────────────────────────────────

    async function selectRecipe(id) {
        id = parseInt(id);
        const recipe = _recipes.find(r => parseInt(r.id) === id);
        if (!recipe) return;

        _current = recipe;
        renderSidebar(); // re-render to show active state

        document.getElementById('forgeEmpty').style.display = 'none';
        document.getElementById('forgeWorkspace').style.display = 'flex';

        // Render skeleton detail while fetching ingredients
        renderDetail(recipe, null);

        // Fetch full ingredients list
        try {
            const r = await api('get_recipe_details', { id });
            if (r.ok) {
                renderDetail(recipe, r.data);
            } else {
                toast(r.error || 'Failed to load ingredients', 'error');
            }
        } catch (e) {
            toast('Network error loading ingredients', 'error');
        }
    }

    // ── detail panel ──────────────────────────────────────────────────────────

    function renderDetail(recipe, ingredients) {
        const panel = document.getElementById('detailPanel');

        // ── Info cards
        const infoCards = `
        <div class="panel-label">Recipe Info</div>
        <div class="info-grid">
            <div class="info-card">
                <div class="info-card-label">Output File</div>
                <div class="info-card-value">${esc(recipe.output_filename)}</div>
            </div>
            <div class="info-card">
                <div class="info-card-label">Created</div>
                <div class="info-card-value">${fmtDate(recipe.created_at)}</div>
            </div>
            <div class="info-card">
                <div class="info-card-label">Ingredients</div>
                <div class="info-card-value" style="color:var(--amber);">${esc(recipe.ingredient_count)}</div>
            </div>
            <div class="info-card">
                <div class="info-card-label">Recipe ID</div>
                <div class="info-card-value" style="color:var(--text-dim);">#${esc(recipe.id)}</div>
            </div>
        </div>`;

        // ── Ingredients table
        let ingredientsHtml;
        if (ingredients === null) {
            // Loading state
            ingredientsHtml = `
            <div class="panel-label">Ingredients</div>
            <div class="ingredients-table-wrap">
                <div style="padding:30px; text-align:center; font-family:var(--mono); font-size:0.8rem; color:var(--text-dim);">
                    <div class="sidebar-loading-spinner" style="margin:0 auto 12px;"></div>
                    Loading ingredients…
                </div>
            </div>`;
        } else if (ingredients.length === 0) {
            ingredientsHtml = `
            <div class="panel-label">Ingredients</div>
            <div class="ingredients-table-wrap">
                <div style="padding:30px; text-align:center; font-family:var(--mono); font-size:0.8rem; color:var(--text-dim);">No ingredients found</div>
            </div>`;
        } else {
            const rows = ingredients.map((ing, i) => {
                const isDb   = ing.source_filename.startsWith('db:');
                const icon   = isDb ? '<i class="bi bi-database" style="color:var(--blue);"></i>' : '<i class="bi bi-file-earmark-code" style="color:var(--green);"></i>';
                const label  = isDb ? `<span class="gen-badge db">DB</span>` : `<span class="gen-badge model">FILE</span>`;
                const fname  = esc(ing.source_filename.replace('db:', ''));
                const hash   = ing.content_hash ? `<span style="color:var(--text-dim); font-size:0.65rem;">${esc(ing.content_hash.substring(0,8))}…</span>` : '';
                return `<tr>
                    <td style="color:var(--text-dim); width:36px;">${i + 1}</td>
                    <td style="width:44px;">${icon} ${label}</td>
                    <td style="word-break:break-all;">${fname}</td>
                    <td style="width:80px; text-align:right;">${hash}</td>
                </tr>`;
            }).join('');

            ingredientsHtml = `
            <div class="panel-label">Ingredients <span style="color:var(--amber); margin-left:4px;">(${ingredients.length})</span></div>
            <div class="ingredients-table-wrap">
                <table class="forge-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Source</th>
                            <th style="text-align:right;">Hash</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
        }

        // ── Rerun command block
        const rerunHtml = `
        <div class="panel-label">Rerun Command</div>
        <div class="rerun-block">
            <div class="rerun-block-header">
                <span class="rerun-block-label">Run from project root</span>
                <button class="btn-forge-secondary" id="inlineCopyBtn" style="padding:4px 10px; font-size:0.72rem; display:flex; align-items:center; gap:6px;">
                    <i class="bi bi-clipboard"></i> Copy
                </button>
            </div>
            <pre class="rerun-command-pre" id="rerunCommandPre">${esc(recipe.rerun_command)}</pre>
        </div>`;

        panel.innerHTML = rerunHtml + ingredientsHtml + (ingredients !== null ? infoCards : '');

        // Bind inline copy button (re-bound each render)
        const inlineCopyBtn = document.getElementById('inlineCopyBtn');
        if (inlineCopyBtn) {
            inlineCopyBtn.addEventListener('click', () => copyRerun(recipe.rerun_command, inlineCopyBtn));
        }
    }

    // ── actions ───────────────────────────────────────────────────────────────

    function copyRerun(cmd, btn) {
        const targetBtn = btn || document.getElementById('btnCopyRerun');
        navigator.clipboard.writeText(cmd).then(() => {
            toast('Rerun command copied!', 'success');
            if (targetBtn) {
                const orig = targetBtn.innerHTML;
                targetBtn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
                setTimeout(() => { targetBtn.innerHTML = orig; }, 2000);
            }
        }).catch(() => {
            // Fallback
            const ta = document.createElement('textarea');
            ta.value = cmd;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); toast('Rerun command copied!', 'success'); }
            catch (e) { toast('Failed to copy', 'error'); }
            document.body.removeChild(ta);
        });
    }

    async function deleteRecipe(id) {
        id = id ? parseInt(id) : (_current ? _current.id : null);
        if (!id) return;
        const recipe = _recipes.find(r => r.id === id);
        const name = recipe ? recipe.group_name : `#${id}`;
        if (!confirm(`Delete recipe "${name}"? This cannot be undone.`)) return;

        try {
            const r = await api('delete_recipe', { id });
            if (!r.ok) throw new Error(r.error || 'Delete failed');

            toast('Recipe deleted', 'success');

            _recipes  = _recipes.filter(r => r.id !== id);
            _filtered = _filtered.filter(r => r.id !== id);

            if (_current && _current.id === id) {
                _current = null;
                document.getElementById('forgeEmpty').style.display = 'flex';
                document.getElementById('forgeWorkspace').style.display = 'none';
            }

            document.getElementById('statTotal').textContent = _recipes.length;
            renderSidebar();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    // ── events ────────────────────────────────────────────────────────────────

    function bindEvents() {
        // Sidebar click delegation — card selects, delete button deletes
        document.getElementById('sidebarList').addEventListener('click', e => {
            const delBtn = e.target.closest('.recipe-card-delete');
            if (delBtn) { deleteRecipe(delBtn.dataset.id); return; }
            const card = e.target.closest('.recipe-card');
            if (card) selectRecipe(card.dataset.id);
        });

        // Search with debounce
        document.getElementById('sidebarSearch').addEventListener('input', e => {
            clearTimeout(_searchTimeout);
            _searchTimeout = setTimeout(() => applySearch(e.target.value), 150);
        });
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', () => RecipeForge.init());
</script>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
?>
