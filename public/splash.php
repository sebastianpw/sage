<?php
// public/splash.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Splash — Writers' Room</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script>
(function() {
  try {
    var t = localStorage.getItem('spw_theme');
    if (t) document.documentElement.setAttribute('data-theme', t);
  } catch(e) {}
})();
</script>
<style>
/* Splash Specific Accent (Pink/Fuchsia) */
:root {
  --bg: #080b10; --surface: #0e1319; --card: #111820; --card-hover: #141e28;
  --border: #1c2535; --border-glow: #2a3a52; --text: #c8d4e8; --text-dim: #5a6a80; --text-bright: #e8f0ff;
  --amber: #f5a623; --green: #22d3a0; --red: #f05060;
  --pink: #d946ef; --pink-dim: rgba(217, 70, 239, 0.1); --pink-mid: rgba(217, 70, 239, 0.18); --pink-glow: rgba(217, 70, 239, 0.45);
  --mono: 'Space Mono', 'Fira Mono', monospace; --sans: 'Syne', system-ui, sans-serif;
  --radius: 6px; --radius-lg: 10px;
}
:root[data-theme="light"], html[data-theme="light"] {
  --bg: #f6f8fa; --surface: #e1e4e8; --card: #ffffff; --card-hover: #f3f4f6;
  --border: #d1d5db; --border-glow: #9ca3af; --text: #111827; --text-dim: #4b5563; --text-bright: #000000;
  --amber: #d97706; --green: #059669; --red: #dc2626;
  --pink: #c026d3; --pink-dim: rgba(192, 38, 211, 0.1); --pink-mid: rgba(192, 38, 211, 0.18); --pink-glow: rgba(192, 38, 211, 0.4);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--sans); font-size: 14px; overflow: hidden; }
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 4px; }

.sp-layout { display: flex; flex-direction: column; height: 100vh; }
.sp-header { height: 52px; flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; background: var(--surface); border-bottom: 1px solid var(--border); z-index: 100; }
.sp-logo { display: flex; align-items: center; gap: 10px; font-family: var(--mono); font-size: 0.82rem; font-weight: 700; color: var(--pink); letter-spacing: 2px; text-transform: uppercase; }
.sp-logo-icon { width: 28px; height: 28px; background: var(--pink-mid); border: 1px solid var(--pink-glow); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 14px; }
.btn-icon-sm { width: 32px; height: 32px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.btn-icon-sm:hover { border-color: var(--pink); color: var(--pink); background: var(--pink-dim); }
.sp-hamburger { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; flex-shrink: 0; font-size: 16px; }
.sp-hamburger:hover, .sp-hamburger.open { border-color: var(--pink); color: var(--pink); background: var(--pink-dim); }

.sp-flyout { position: fixed; top: 52px; bottom: 0; width: min(300px, 88vw); background: var(--surface); border-right: 1px solid var(--border); z-index: 500; display: flex; flex-direction: column; overflow: hidden; transition: transform 0.28s cubic-bezier(0.25, 1, 0.5, 1); left: 0; transform: translateX(-100%); }
.sp-flyout.open { transform: translateX(0); }
.sp-flyout-backdrop { position: fixed; inset: 52px 0 0 0; background: rgba(0,0,0,0.45); z-index: 499; display: none; backdrop-filter: blur(2px); }
.sp-flyout-backdrop.visible { display: block; }
.flyout-head { padding: 12px 14px 10px; border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; }
.flyout-head-title { font-family: var(--mono); font-size: 0.65rem; color: var(--pink); text-transform: uppercase; letter-spacing: 2px; }

.left-tabs { display: flex; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.left-tab { flex: 1; padding: 8px 4px; font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); text-align: center; cursor: pointer; text-transform: uppercase; border-bottom: 2px solid transparent; background: none; border-top: none; border-left: none; border-right: none; }
.left-tab.active { color: var(--pink); border-bottom-color: var(--pink); }
.left-tab-body { flex: 1; overflow: hidden; position: relative; }
.left-tab-content { position: absolute; inset: 0; overflow-y: auto; display: none; padding: 12px; }
.left-tab-content.active { display: block; }

.ctx-group { margin-bottom: 12px; }
.ctx-label { font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim); text-transform: uppercase; margin-bottom: 5px; display: block; }
.ctx-textarea, .ctx-input, .ctx-select { width: 100%; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.73rem; padding: 7px 10px; }
.ctx-textarea { min-height: 60px; resize: vertical; line-height: 1.5; }
.ctx-textarea:focus, .ctx-input:focus, .ctx-select:focus { outline: none; border-color: var(--pink); }
.ctx-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; padding-right: 28px; }

.sp-item { display: flex; align-items: flex-start; gap: 8px; padding: 8px 10px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.7rem; margin-bottom: 6px; }
.sp-item:hover, .sp-item.active { border-color: var(--pink); background: var(--pink-dim); }
.sp-item-title { color: var(--text); font-weight: 700; }
.sp-item-meta { color: var(--text-dim); margin-top: 3px; font-size: 0.63rem; display: flex; gap: 6px; align-items: center; }
.status-chip { font-size: 0.58rem; padding: 1px 6px; border-radius: 10px; border: 1px solid var(--border-glow); text-transform: uppercase; }
.status-chip.running { color: var(--amber); border-color: var(--amber); }
.status-chip.done { color: var(--green); border-color: var(--green); }
.status-chip.failed { color: var(--red); border-color: var(--red); }

.sp-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: var(--bg); }
.sp-feed { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 16px; }
.empty-state { text-align: center; padding: 40px; color: var(--text-dim); font-family: var(--mono); }

.role-chain { display: flex; flex-direction: column; gap: 16px; }
.role-item { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; }
.role-item.active { border-color: var(--pink); box-shadow: 0 0 10px var(--pink-dim); }
.role-item.done { border-left: 3px solid var(--green); }
.role-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-family: var(--mono); font-size: 0.8rem; }
.role-name { color: var(--pink); font-weight: bold; text-transform: uppercase; }
.role-text-box { background: var(--surface); padding: 12px; border: 1px solid var(--border); border-radius: 4px; font-family: var(--mono); font-size: 0.75rem; white-space: pre-wrap; max-height: 250px; overflow-y: auto; color: var(--text-bright); }

.sp-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 16px; }
.sp-modal-overlay.open { display: flex; }
.sp-modal { background: var(--surface); border: 1px solid var(--border-glow); border-radius: var(--radius-lg); width: 100%; max-width: 700px; max-height: 90vh; display: flex; flex-direction: column; }
.sp-modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.sp-modal-title { font-family: var(--mono); font-size: 0.78rem; color: var(--pink); text-transform: uppercase; letter-spacing: 1.5px; }
.sp-modal-close { width: 26px; height: 26px; border-radius: 4px; border: 1px solid var(--border); background: transparent; color: var(--text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; }
.sp-modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.sp-modal-footer { padding: 12px 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; }

.btn-primary { padding: 8px 18px; background: var(--pink); color: #fff; border: none; border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; }
.btn-primary:hover { filter: brightness(1.1); }
.btn-secondary { padding: 8px 18px; background: transparent; color: var(--text-dim); border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.76rem; }

.role-editor-row { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 10px; margin-bottom: 8px; position: relative; }
.role-editor-head { display: flex; justify-content: space-between; margin-bottom: 8px; }
.role-move-btn, .role-remove-btn {
  width: 24px; height: 24px; border-radius: 4px; border: 1px solid var(--border);
  background: transparent; color: var(--text-dim); cursor: pointer; font-size: 11px;
  display: flex; align-items: center; justify-content: center; transition: all 0.15s; flex-shrink: 0;
}
.role-move-btn:hover { border-color: var(--pink); color: var(--pink); background: var(--pink-dim); }
.role-remove-btn:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }




/* ── PAGINATION BAR ── */
.pg-bar { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 4px 2px; margin-top: 4px; font-family: var(--mono); }
.pg-btn { width: 26px; height: 26px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; flex-shrink: 0; }
.pg-btn:hover:not(:disabled) { border-color: var(--pink); color: var(--pink); background: var(--pink-dim); }
.pg-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.pg-input { width: 40px; height: 26px; text-align: center; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.72rem; padding: 0 2px; }
.pg-input:focus { outline: none; border-color: var(--pink); }
.pg-of { font-size: 0.68rem; color: var(--text-dim); white-space: nowrap; }
</style>
</head>
<body>
<div class="sp-layout">
<div class="sp-flyout-backdrop" id="flyoutBackdrop" onclick="SP.closeFlyouts()"></div>

<header class="sp-header">
  <button class="sp-hamburger" id="hamburgerLeft" onclick="SP.toggleFlyout()">
    <i class="bi bi-layout-text-sidebar"></i>
  </button>
  <div class="sp-logo">
    <div class="sp-logo-icon">🪶</div>
    <span>Splash</span>
  </div>
  <div style="display:flex;align-items:center;gap:6px;">
    <button class="btn-icon-sm" onclick="SP.openNewTemplateModal()" title="New Room"><i class="bi bi-plus-circle"></i></button>
    <a href="/wtf.php" class="btn-icon-sm" title="WTF Engine"><i class="bi bi-gear"></i></a>
  </div>
</header>

<div class="sp-flyout" id="flyoutLeft">
  <div class="flyout-head">
    <span class="flyout-head-title">Writers' Room</span>
    <button class="flyout-close" onclick="SP.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
  </div>
  <div style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
    <div class="left-tabs">
      <button class="left-tab active" data-ltab="runs">Runs</button>
      <button class="left-tab" data-ltab="templates">Rooms</button>
    </div>
    <div class="left-tab-body">
      <div class="left-tab-content active" id="ltab-runs">
        <div id="runsList"></div>
        <div class="pg-bar" id="runsListPager" style="display:none;">
          <button class="pg-btn" id="runsListPagerPrev"><i class="bi bi-chevron-left"></i></button>
          <input type="text" class="pg-input" id="runsListPagerInput" inputmode="numeric">
          <span class="pg-of">/ <span id="runsListPagerTotal">1</span></span>
          <button class="pg-btn" id="runsListPagerNext"><i class="bi bi-chevron-right"></i></button>
        </div>
      </div>
      <div class="left-tab-content" id="ltab-templates">
        <div id="templatesList"></div>
        <div class="pg-bar" id="templatesListPager" style="display:none;">
          <button class="pg-btn" id="templatesListPagerPrev"><i class="bi bi-chevron-left"></i></button>
          <input type="text" class="pg-input" id="templatesListPagerInput" inputmode="numeric">
          <span class="pg-of">/ <span id="templatesListPagerTotal">1</span></span>
          <button class="pg-btn" id="templatesListPagerNext"><i class="bi bi-chevron-right"></i></button>
        </div>
        <button class="btn-secondary" style="width:100%;font-size:0.7rem;margin-top:10px;" onclick="SP.openNewTemplateModal()">+ New Room</button>
      </div>
    </div>
  </div>
</div>

<main class="sp-main">
  <div class="sp-feed" id="feed">
    <div class="empty-state">
      <div style="font-size:48px;opacity:0.2;margin-bottom:10px;">🪶</div>
      <div style="color:var(--pink);font-weight:bold;text-transform:uppercase;letter-spacing:2px;">Splash — Writers' Room</div>
      <p style="margin-top:10px;">Configure a chain of AI roles to transform raw lore into polished scripts.</p>
    </div>
  </div>
</main>
</div>

<!-- MODAL: TEMPLATE -->
<div class="sp-modal-overlay" id="templateModal">
  <div class="sp-modal">
    <div class="sp-modal-header">
      <div class="sp-modal-title" id="templateModalTitle">New Room</div>
      <button class="sp-modal-close" onclick="SP.closeModal('templateModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="sp-modal-body">
      <input type="hidden" id="tplId">
      <div class="ctx-group">
        <label class="ctx-label">Room Name</label>
        <input class="ctx-input" type="text" id="tplName" placeholder="e.g. Solo Claude Writer">
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Description</label>
        <textarea class="ctx-textarea" id="tplDesc" style="min-height:40px;"></textarea>
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Writing Chain (Roles in order)</label>
        <div id="roleEditorList"></div>
        <button class="btn-secondary" style="width:100%;font-size:0.7rem;margin-top:4px;" onclick="SP.addRoleRow()">+ Add Role</button>
      </div>
    </div>
    <div class="sp-modal-footer">
      <button class="btn-secondary" style="border-color:var(--red);color:var(--red);margin-right:auto;display:none;" id="btnDeleteTpl" onclick="SP.deleteTemplate()">Delete</button>
      <button class="btn-secondary" onclick="SP.closeModal('templateModal')">Cancel</button>
      <button class="btn-primary" onclick="SP.saveTemplate()">Save Room</button>
    </div>
  </div>
</div>

<!-- MODAL: START RUN -->
<div class="sp-modal-overlay" id="startRunModal">
  <div class="sp-modal">
    <div class="sp-modal-header">
      <div class="sp-modal-title">Manual Execution</div>
      <button class="sp-modal-close" onclick="SP.closeModal('startRunModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="sp-modal-body">
      <input type="hidden" id="startRunTplId">
      <div class="ctx-group">
        <label class="ctx-label">Source Lore / Plot</label>
        <textarea class="ctx-textarea" id="startRunSource" style="height:200px;"></textarea>
      </div>
    </div>
    <div class="sp-modal-footer">
      <button class="btn-secondary" onclick="SP.closeModal('startRunModal')">Cancel</button>
      <button class="btn-primary" onclick="SP.confirmStartRun()">Start Writing</button>
    </div>
  </div>
</div>

<script>
window.SP = (function() {
  let _generators = [], _templates = [], _runs = [], _currentRun = null, _pollTimer = null;

  // Pagination state — one entry per paginated list container id.
  let _pg = {
    runsList: { page: 1, limit: 12, total_pages: 1 },
    templatesList: { page: 1, limit: 12, total_pages: 1 }
  };

  async function api(payload) {
    const res = await fetch('/splash_api.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if(data.error) throw new Error(data.error);
    return data;
  }

  // ── Shared pagination bar wiring ──────────────────────────────────────
  // Wires up the prev/next buttons + editable page-index input for a given
  // list id (e.g. "runsList"), calling `loader(page)` whenever the page
  // should change. Reflects state from _pg[listId] into the bar's controls.
  function bindPager(listId, loader) {
    const prevBtn = document.getElementById(listId + 'PagerPrev');
    const nextBtn = document.getElementById(listId + 'PagerNext');
    const input = document.getElementById(listId + 'PagerInput');
    if (!prevBtn || !nextBtn || !input) return;

    prevBtn.addEventListener('click', () => {
      const st = _pg[listId];
      if (st.page > 1) loader(st.page - 1);
    });
    nextBtn.addEventListener('click', () => {
      const st = _pg[listId];
      if (st.page < st.total_pages) loader(st.page + 1);
    });
    input.addEventListener('change', () => {
      const st = _pg[listId];
      let p = parseInt(input.value, 10);
      if (!Number.isFinite(p) || p < 1) p = 1;
      if (p > st.total_pages) p = st.total_pages;
      loader(p);
    });
    input.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); input.blur(); input.dispatchEvent(new Event('change')); }
    });
  }

  function renderPager(listId) {
    const st = _pg[listId];
    const bar = document.getElementById(listId + 'Pager');
    const input = document.getElementById(listId + 'PagerInput');
    const total = document.getElementById(listId + 'PagerTotal');
    const prevBtn = document.getElementById(listId + 'PagerPrev');
    const nextBtn = document.getElementById(listId + 'PagerNext');
    if (!bar) return;

    bar.style.display = st.total_pages > 1 ? 'flex' : 'none';
    input.value = st.page;
    total.textContent = st.total_pages;
    prevBtn.disabled = st.page <= 1;
    nextBtn.disabled = st.page >= st.total_pages;
  }

  async function init() {
    bindEvents();
    bindPager('runsList', p => loadRuns(p));
    bindPager('templatesList', p => loadTemplates(p));
    try {
      const g = await api({action:'list_generators'}); _generators = g.generators || [];
      await loadTemplates();
      await loadRuns();
    } catch(e) { console.error(e); }
  }

  function bindEvents() {
    document.querySelectorAll('.left-tab').forEach(btn => btn.addEventListener('click', () => setLeftTab(btn.dataset.ltab)));
    document.querySelectorAll('.sp-modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }));
  }

  async function loadTemplates(page) {
    page = page || _pg.templatesList.page || 1;
    const d = await api({action:'list_templates', page, limit: _pg.templatesList.limit});
    _templates = d.templates || [];
    _pg.templatesList = { page: d.page || page, limit: d.limit || _pg.templatesList.limit, total_pages: d.total_pages || 1 };
    const list = document.getElementById('templatesList');
    list.innerHTML = _templates.map(t => `<div class="sp-item" onclick="SP.openStartRunModal(${t.id})"><div class="sp-item-body"><div class="sp-item-title">${esc(t.name)}</div><div class="sp-item-meta"><span style="text-decoration:underline;cursor:pointer;margin-left:auto;" onclick="event.stopPropagation();SP.openEditModal(${t.id})">edit</span></div></div></div>`).join('');
    renderPager('templatesList');
  }

  async function loadRuns(page) {
    page = page || _pg.runsList.page || 1;
    const d = await api({action:'list_runs', page, limit: _pg.runsList.limit});
    _runs = d.runs || [];
    _pg.runsList = { page: d.page || page, limit: d.limit || _pg.runsList.limit, total_pages: d.total_pages || 1 };
    const list = document.getElementById('runsList');
    list.innerHTML = _runs.map(r => `<div class="sp-item ${(_currentRun&&_currentRun.id===r.id)?'active':''}" onclick="SP.openRun(${r.id})"><div class="sp-item-body"><div class="sp-item-title">${esc(r.title)}</div><div class="sp-item-meta"><span class="status-chip ${r.status}">${r.status}</span><span>${esc(r.template_name)}</span></div></div></div>`).join('');
    renderPager('runsList');
  }

  function addRoleRow(role = {}) {
    const list = document.getElementById('roleEditorList');
    const div = document.createElement('div'); div.className = 'role-editor-row';
    const genOpts = '<option value="">-- Select AI --</option>' + _generators.map(g => `<option value="${g.config_id}" ${role.generator_config_id===g.config_id?'selected':''}>${esc(g.title)}</option>`).join('');
    div.innerHTML = `
      <div class="role-editor-head" style="align-items:center; gap:6px;">
        <input type="text" class="ctx-input role-name" placeholder="Role Name (e.g. Drafter)" value="${esc(role.role_name||'')}" style="flex:1;">
        <select class="ctx-select role-gen" style="flex:1;">${genOpts}</select>
        <div style="display:flex; gap:3px; align-items:center;">
          <button type="button" class="role-move-btn" onclick="SP.moveRoleRowUp(this)" title="Move Up"><i class="bi bi-arrow-up"></i></button>
          <button type="button" class="role-move-btn" onclick="SP.moveRoleRowDown(this)" title="Move Down"><i class="bi bi-arrow-down"></i></button>
          <button type="button" class="role-remove-btn" onclick="this.closest('.role-editor-row').remove()" title="Remove Role"><i class="bi bi-x"></i></button>
        </div>
      </div>
      <textarea class="ctx-textarea role-prompt" placeholder="Instructions for this role...">${esc(role.prompt_instructions||'')}</textarea>
    `;
    list.appendChild(div);
  }

  function openNewTemplateModal() {
    document.getElementById('tplId').value = ''; document.getElementById('tplName').value = ''; document.getElementById('tplDesc').value = '';
    document.getElementById('roleEditorList').innerHTML = ''; document.getElementById('btnDeleteTpl').style.display='none';
    addRoleRow(); document.getElementById('templateModal').classList.add('open');
  }

  function openEditModal(id) {
    const t = _templates.find(x => x.id === id); if(!t) return;
    document.getElementById('tplId').value = t.id; document.getElementById('tplName').value = t.name; document.getElementById('tplDesc').value = t.description||'';
    document.getElementById('roleEditorList').innerHTML = '';
    (t.roles||[]).forEach(r => addRoleRow(r));
    document.getElementById('btnDeleteTpl').style.display='block';
    document.getElementById('templateModal').classList.add('open');
  }

  async function saveTemplate() {
    const id = document.getElementById('tplId').value;
    const name = document.getElementById('tplName').value;
    const desc = document.getElementById('tplDesc').value;
    const roles = [];
    document.querySelectorAll('.role-editor-row').forEach(r => {
      roles.push({ role_name: r.querySelector('.role-name').value, generator_config_id: r.querySelector('.role-gen').value, prompt_instructions: r.querySelector('.role-prompt').value });
    });
    await api({action:'save_template', template: {id: id?parseInt(id):null, name, description: desc, roles}});
    closeModal('templateModal'); loadTemplates(_pg.templatesList.page);
  }

  async function deleteTemplate() {
    if(!confirm('Delete?')) return;
    await api({action:'delete_template', id: parseInt(document.getElementById('tplId').value)});
    closeModal('templateModal'); loadTemplates(_pg.templatesList.page);
  }

  function openStartRunModal(tplId) {
    document.getElementById('startRunTplId').value = tplId; document.getElementById('startRunSource').value = '';
    document.getElementById('startRunModal').classList.add('open');
  }

  async function confirmStartRun() {
    const d = await api({action:'start_run', template_id: parseInt(document.getElementById('startRunTplId').value), source_text: document.getElementById('startRunSource').value });
    closeModal('startRunModal'); loadRuns(1); openRun(d.run.id); setLeftTab('runs');
  }

  async function openRun(id) {
    const d = await api({action:'get_run', id}); _currentRun = d.run; renderRun(); loadRuns(_pg.runsList.page); closeFlyouts();
    if(_currentRun.status === 'running') pollRun();
  }

  function pollRun() {
    if(_pollTimer) clearTimeout(_pollTimer);
    _pollTimer = setTimeout(async () => {
      const d = await api({action:'run_next_role', run_id: _currentRun.id});
      _currentRun = d.run; renderRun(); loadRuns(_pg.runsList.page);
      if(_currentRun.status === 'running') pollRun();
    }, 2000); // Trigger the next loop
  }

  function renderRun() {
    if(!_currentRun) return;
    let html = `<div class="run-header"><div class="run-title">${esc(_currentRun.title)} <span class="status-chip ${_currentRun.status}">${_currentRun.status}</span></div></div>`;
    html += `<div class="role-chain">`;
    html += `<div class="role-item"><div class="role-header">Source Lore</div><div class="role-text-box">${esc(_currentRun.source_text)}</div></div>`;
    
    (_currentRun.roles||[]).forEach(r => {
      html += `<div class="role-item ${r.status==='active'?'active':''} ${r.status==='done'?'done':''}">
        <div class="role-header"><span class="role-name">${esc(r.role_name)}</span> <span class="status-chip ${r.status}">${r.status}</span></div>`;
      if(r.output_text) html += `<div class="role-text-box" style="border-color:var(--pink);">${esc(r.output_text)}</div>`;
      if(r.error_text) html += `<div style="color:var(--red);margin-top:8px;">${esc(r.error_text)}</div>`;
      html += `</div>`;
    });
    html += `</div>`;
    document.getElementById('feed').innerHTML = html;
  }

  function toggleFlyout() { const e = document.getElementById('flyoutLeft'); e.classList.contains('open') ? closeFlyouts() : (e.classList.add('open'), document.getElementById('flyoutBackdrop').classList.add('visible')); }
  function closeFlyouts() { document.getElementById('flyoutLeft').classList.remove('open'); document.getElementById('flyoutBackdrop').classList.remove('visible'); }
  function setLeftTab(t) { document.querySelectorAll('.left-tab').forEach(b=>b.classList.toggle('active', b.dataset.ltab===t)); document.querySelectorAll('.left-tab-content').forEach(c=>c.classList.toggle('active', c.id==='ltab-'+t)); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }
  function esc(s) { if(!s) return ''; const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

  function moveRoleRowUp(btn) {
    const row = btn.closest('.role-editor-row');
    const prev = row.previousElementSibling;
    if (prev && prev.classList.contains('role-editor-row')) {
      row.parentNode.insertBefore(row, prev);
    }
  }

  function moveRoleRowDown(btn) {
    const row = btn.closest('.role-editor-row');
    const next = row.nextElementSibling;
    if (next && next.classList.contains('role-editor-row')) {
      row.parentNode.insertBefore(next, row);
    }
  }

  return { init, toggleFlyout, closeFlyouts, closeModal, openNewTemplateModal, openEditModal, saveTemplate, deleteTemplate, openStartRunModal, confirmStartRun, openRun, addRoleRow, moveRoleRowUp, moveRoleRowDown };
  

  
  
})();
document.addEventListener('DOMContentLoaded', () => { if(window.SP) window.SP.init(); });
</script>
</body>
</html>