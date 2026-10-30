<?php
// ============================================================================
// File: public/kg_manager.php
// AI Knowledge Graph Manager UI
// ============================================================================
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=0.85, viewport-fit=cover">
<title>AI KG Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<script>
(function() {
  try {
    var t = localStorage.getItem('spw_theme');
    if (t) document.documentElement.setAttribute('data-theme', t);
    else document.documentElement.setAttribute('data-theme', 'dark');
  } catch(e) {}
})();
</script>

<style>
:root {
  --bg:           #080b10;
  --surface:      #0e1319;
  --card:         #111820;
  --card-hover:   #151f2b;
  --border:       #1c2535;
  --border-glow:  #2a3a52;
  --text:         #c8d4e8;
  --text-dim:     #5a6a80;
  --text-bright:  #e8f0ff;
  --teal:         #2dd4bf;
  --teal-dim:     rgba(45, 212, 191, 0.1);
  --teal-glow:    rgba(45, 212, 191, 0.4);
  --amber:        #f5a623;
  --amber-dim:    rgba(245, 166, 35, 0.12);
  --green:        #4ade80;
  --red:          #f87171;
  --red-dim:      rgba(248, 113, 113, 0.1);
  --purple:       #a78bfa;
  --purple-dim:   rgba(167, 139, 250, 0.1);
  --mono:         'Space Mono', monospace;
  --sans:         'Syne', system-ui, sans-serif;
  --radius:       6px;
  --radius-lg:    10px;
}
:root[data-theme="light"] {
  --bg:           #f4f6fa;
  --surface:      #ffffff;
  --card:         #ffffff;
  --card-hover:   #f8fafc;
  --border:       #d0d8e8;
  --border-glow:  #9ca3af;
  --text:         #1a2233;
  --text-dim:     #7a8aaa;
  --text-bright:  #000000;
  --purple:       #7c3aed;
  --purple-dim:   rgba(124, 58, 237, 0.1);
}

* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--sans); font-size: 14px; line-height: 1.5; overflow: hidden; }
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-thumb { background: var(--text-dim); border-radius: 4px; }

.layout { display: flex; flex-direction: column; height: 100dvh; overflow: hidden; }
.header { height: 52px; flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; background: var(--surface); border-bottom: 1px solid var(--border); z-index: 100; }
.logo { display: flex; align-items: center; gap: 10px; font-family: var(--mono); font-size: 0.85rem; font-weight: 700; color: var(--purple); text-transform: uppercase; letter-spacing: 2px; }
.logo-icon { width: 28px; height: 28px; background: var(--purple-dim); border: 1px solid var(--purple); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; }
.btn-icon { width: 36px; height: 36px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.btn-icon:hover { border-color: var(--purple); color: var(--purple); background: var(--purple-dim); }

/* Flyouts */
.flyout { position: fixed; top: 52px; bottom: 0; width: min(340px, 88vw); background: var(--surface); border-color: var(--border); z-index: 500; display: flex; flex-direction: column; overflow: hidden; transition: transform 0.28s cubic-bezier(0.25, 1, 0.5, 1); box-shadow: 0 0 40px rgba(0,0,0,0.6); }
.flyout-left { left: 0; border-right: 1px solid var(--border); transform: translateX(-100%); }
.flyout-right { right: 0; border-left: 1px solid var(--border); transform: translateX(100%); width: min(520px, 94vw); }
.flyout.open { transform: translateX(0); }
.flyout-backdrop { position: fixed; inset: 52px 0 0 0; background: rgba(0,0,0,0.5); z-index: 499; display: none; backdrop-filter: blur(2px); }
.flyout-backdrop.visible { display: block; }
.flyout-head { padding: 12px 14px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.flyout-title { font-family: var(--mono); font-size: 0.72rem; color: var(--purple); text-transform: uppercase; letter-spacing: 2px; }
.flyout-body { flex: 1; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; min-height: 0; }

/* ── 3-Split Independent Scroll Panes for Right Flyout ── */
#flyoutRight .flyout-body {
  overflow: hidden;
  padding: 8px;
  gap: 8px;
}
.sidebar-pane {
  flex: 1 1 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 8px 10px;
  overflow: hidden;
}
.sidebar-pane-title {
  font-family: var(--mono);
  font-size: 0.65rem;
  color: var(--text-dim);
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 6px;
  flex-shrink: 0;
}
.sidebar-pane-scroll {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
  -webkit-overflow-scrolling: touch;
}

/* Main Chat Structure */
.main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.chat-window { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 16px; }
.chat-empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-dim); text-align: center; gap: 8px; }
.chat-empty-state .empty-icon { font-size: 40px; color: var(--purple); margin-bottom: 6px; }

.msg { display: flex; gap: 12px; }
.msg-user { flex-direction: row-reverse; }
.msg-avatar { width: 32px; height: 32px; border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; font-family: var(--mono); }
.msg-user .msg-avatar { background: var(--purple-dim); border: 1px solid var(--purple); color: var(--purple); }
.msg-ai .msg-avatar { background: var(--card); border: 1px solid var(--border); color: var(--text); }
.msg-bubble { max-width: calc(100% - 56px); padding: 12px 16px; border-radius: var(--radius-lg); font-size: 0.85rem; line-height: 1.6; }
.msg-user .msg-bubble { background: var(--purple-dim); border: 1px solid var(--purple); color: var(--text-bright); border-top-right-radius: 4px; }
.msg-ai .msg-bubble { background: var(--card); border: 1px solid var(--border); color: var(--text); border-top-left-radius: 4px; }

.msg-bubble-meta { display: flex; gap: 8px; align-items: center; margin-bottom: 6px; flex-wrap: wrap; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 4px; }
.msg-timestamp { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); }
.msg-actions { margin-left: auto; display: flex; align-items: center; gap: 4px; }
.msg-action-btn { background: none; border: none; color: var(--text-dim); cursor: pointer; font-size: 13px; padding: 2px 4px; transition: color 0.15s; }
.msg-action-btn:hover { color: var(--purple); }
.msg-action-btn.delete-btn:hover { color: var(--red); }

.ops-pill { display: inline-block; margin-top: 10px; padding: 6px 12px; background: rgba(167, 139, 250, 0.08); border: 1px solid rgba(167, 139, 250, 0.4); border-radius: 6px; font-family: var(--mono); font-size: 0.7rem; color: var(--purple); cursor: pointer; }
.ops-details { display: none; margin-top: 6px; padding-top: 6px; border-top: 1px dashed rgba(167, 139, 250, 0.4); font-size: 0.65rem; color: var(--text-bright); }
.ops-pill.open .ops-details { display: block; }

/* Input Bar & Files */
.input-bar { padding: 12px 16px; border-top: 1px solid var(--border); background: var(--surface); flex-shrink: 0; }
.attached-files-container { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 8px; }
.file-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--border); font-family: var(--mono); font-size: 0.72rem; color: var(--text); }
.file-chip button { background: none; border: none; color: var(--text-dim); cursor: pointer; padding: 0; }
.file-chip button:hover { color: var(--red); }
.input-row { display: flex; gap: 8px; align-items: flex-end; }
.chat-textarea { flex: 1; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text-bright); font-family: var(--sans); font-size: 0.9rem; padding: 10px 14px; resize: none; min-height: 44px; max-height: 160px; overflow-y: auto; }
.chat-textarea:focus { outline: none; border-color: var(--purple); }
.btn-send { width: 44px; height: 44px; border-radius: var(--radius); background: var(--purple); color: #000; border: none; cursor: pointer; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: all 0.15s; }
.btn-send:hover:not(:disabled) { filter: brightness(1.15); }
.btn-send:disabled { opacity: 0.4; cursor: not-allowed; }

/* Settings & List Items */
.ctx-group { margin-bottom: 14px; }
.ctx-label { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; display: block; }
.ctx-input, .ctx-textarea, .ctx-select { width: 100%; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.75rem; padding: 8px 10px; }
.ctx-textarea { resize: vertical; min-height: 90px; }
.ctx-input:focus, .ctx-textarea:focus, .ctx-select:focus { outline: none; border-color: var(--purple); }
.btn-primary { padding: 9px 16px; background: var(--purple); color: #000; border: none; border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.75rem; font-weight: bold; text-transform: uppercase; width: 100%; transition: all 0.15s; }
.btn-primary:hover { filter: brightness(1.15); }

.list-item { padding: 8px 10px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 8px; cursor: pointer; position: relative; transition: all 0.15s; }
.list-item:hover { border-color: var(--purple); }
.list-item.active { border-color: var(--purple); background: var(--purple-dim); }
.item-meta { font-family: var(--mono); font-size: 0.65rem; color: var(--purple); margin-bottom: 2px; }
.item-title { font-size: 0.8rem; font-weight: bold; color: var(--text); padding-right: 56px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.btn-del, .btn-rename { position: absolute; top: 8px; width: 22px; height: 22px; background: transparent; border: none; color: var(--text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; border-radius: 4px; }
.btn-del { right: 6px; }
.btn-del:hover { color: var(--red); background: var(--red-dim); }
.btn-rename { right: 30px; }
.btn-rename:hover { color: var(--purple); background: var(--purple-dim); }

/* Tree & Working Set specific */
.kg-tree-container { padding: 2px 0; }
.kg-tree-node { display: flex; align-items: center; gap: 6px; padding: 4px 6px; cursor: pointer; user-select: none; font-size: 0.82rem; border-radius: 4px; margin: 1px 0; transition: background 0.1s; position: relative; }
.kg-tree-node:hover { background: rgba(167,139,250,0.08); }
.kg-node-toggle { width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.65rem; color: var(--text-dim); cursor: pointer; border-radius: 3px; transition: transform 0.15s; }
.kg-node-toggle.open { transform: rotate(90deg); }
.kg-node-icon { font-size: 0.85rem; flex-shrink: 0; opacity: 0.8; }
.kg-node-label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kg-tree-children { display: none; }
.kg-tree-children.open { display: block; }
.is-folder > .kg-node-label { font-weight: 600; color: var(--text); }
.is-node > .kg-node-label { color: var(--text-dim); }

.node-actions { display: none; position: absolute; right: 6px; gap: 4px; }
.kg-tree-node:hover .node-actions { display: flex; }
.tree-btn { background: var(--surface); border: 1px solid var(--border); border-radius: 4px; color: var(--text-dim); cursor: pointer; font-size: 11px; padding: 2px 6px; font-family: var(--mono); }
.tree-btn:hover { border-color: var(--purple); color: var(--purple); }

.mg-type-pill { font-size: 0.6rem; padding: 2px 6px; border-radius: 6px; text-transform: uppercase; font-family: var(--mono); font-weight: 700; border: 1px solid var(--border); background: var(--surface); }

/* Reservations & Working Set Cards */
.res-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 8px 10px; margin-bottom: 6px; font-family: var(--mono); font-size: 0.75rem; }
.res-card:hover { border-color: var(--border-glow); }
.res-title { font-weight: 700; color: var(--amber); margin-bottom: 2px; text-transform: uppercase; font-size: 0.72rem; }

/* Typing loader */
.typing { display: flex; gap: 4px; align-items: center; height: 16px; }
.typing span { width: 6px; height: 6px; background: var(--purple); border-radius: 50%; animation: bounce 1.4s infinite ease-in-out both; }
.typing span:nth-child(1) { animation-delay: -0.32s; }
.typing span:nth-child(2) { animation-delay: -0.16s; }
@keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }

/* Toast */
.toast-container { position: fixed; bottom: 80px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 8px; }
.toast { padding: 9px 15px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--purple); font-family: var(--mono); font-size: 0.75rem; color: var(--text); box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
.toast.error { border-color: var(--red); color: var(--red); }
.toast.info { border-color: var(--purple); color: var(--purple); }

/* Modals */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: none; align-items: center; justify-content: center; z-index: 99998; padding: 14px; backdrop-filter: blur(2px); }
.modal-overlay.open { display: flex; }
.modal { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); width: 100%; padding: 0; display: flex; flex-direction: column; overflow: hidden; }
</style>
</head>
<body>

<div class="layout">
  <div class="flyout-backdrop" id="flyoutBackdrop"></div>

  <!-- Header -->
  <header class="header">
    <button class="btn-icon" onclick="window.agentChat.toggleFlyout('left')">
      <i class="bi bi-layout-sidebar"></i>
    </button>
    <div class="logo">
      <div class="logo-icon"><i class="bi bi-diagram-3-fill"></i></div> 
      <span>KG Agent</span>
    </div>
    <div style="display:flex; gap:8px;">
      <button class="btn-icon" onclick="window.agentChat.downloadChat()" title="Download Chat Logs"><i class="bi bi-download"></i></button>
      <button class="btn-icon" onclick="window.agentChat.newSession()" title="New Project"><i class="bi bi-plus-lg"></i></button>
      <button class="btn-icon" onclick="window.agentChat.toggleFlyout('right')" title="View Graph Explorer"><i class="bi bi-diagram-2"></i></button>
    </div>
  </header>

  <!-- Left Flyout: Sessions & Settings -->
  <div class="flyout flyout-left" id="flyoutLeft">
    <div class="flyout-head">
      <span class="flyout-title">Project Setup</span>
      <button class="btn-icon" style="width:28px;height:28px;" onclick="window.agentChat.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="flyout-body">
      <div id="sessionNameBanner" style="margin-bottom:12px; padding:8px 10px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); font-family:var(--mono); font-size:0.7rem; color:var(--purple);"></div>

      <div class="ctx-group">
        <label class="ctx-label">Directives & Global Lore</label>
        <textarea id="setLore" class="ctx-textarea" placeholder="Overall world rules, structural graph constraints..."></textarea>
      </div>

      <div class="ctx-group">
        <label class="ctx-label">AI Model</label>
        <select id="setModel" class="ctx-select"></select>
      </div>

      <button class="btn-primary" onclick="window.agentChat.saveSettings()">Save Configuration</button>

      <div style="margin: 24px 0 10px; font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; border-bottom:1px solid var(--border); padding-bottom:4px;">Sessions</div>
      <div id="sessionsList"></div>
    </div>
  </div>

  <!-- Right Flyout: Independent 33% 3-Split Panes -->
  <div class="flyout flyout-right" id="flyoutRight">
    <div class="flyout-head">
      <span class="flyout-title">Graph Explorer</span>
      <button class="btn-icon" style="width:28px;height:28px;" onclick="window.agentChat.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="flyout-body">
      
      <!-- Pane 1: Session Working Set (33%) -->
      <div class="sidebar-pane">
        <div class="sidebar-pane-title">Session Working Set</div>
        <div class="sidebar-pane-scroll" id="workingSetList"></div>
      </div>

      <!-- Pane 2: ID Reservations (33%) -->
      <div class="sidebar-pane">
        <div class="sidebar-pane-title">ID Reservations</div>
        <div class="sidebar-pane-scroll" id="reservationsList"></div>
      </div>

      <!-- Pane 3: Graph Tree (33%) -->
      <div class="sidebar-pane">
        <div class="sidebar-pane-title" style="display:flex; justify-content:space-between; align-items:center; gap:6px;">
          <span>Graph Nodes</span>
          <input type="text" class="ctx-input" style="padding: 2px 6px; font-size: 0.7rem; width: 140px; height: 22px;" placeholder="Search tree..." oninput="filterKgTree(this.value)">
        </div>
        <div class="sidebar-pane-scroll kg-tree-container" id="kgTreeWrap"></div>
      </div>
      
    </div>
  </div>

  <!-- Main Chat Window -->
  <main class="main">
    <div class="chat-window" id="chatWindow"></div>
    
    <div class="input-bar">
      <div class="attached-files-container" id="attachedFilesContainer" style="display:none;"></div>
      <div class="input-row">
        <button class="btn-icon" onclick="document.getElementById('fileInput').click()" title="Attach Reference Context">
          <i class="bi bi-paperclip"></i>
        </button>
        <input type="file" id="fileInput" multiple accept=".txt,.json,.md,.sql,.csv,.xml,text/*" style="display:none;">

        <textarea id="chatInput" class="chat-textarea" placeholder="e.g. Reserve an ID block for the Emberveil faction and create 5 key locations..." rows="1"></textarea>
        
        <button id="btnSend" class="btn-send"><i class="bi bi-arrow-up"></i></button>
      </div>
    </div>
  </main>
</div>

<!-- Entity Details Iframe Modal (matches Cruzz functionality) -->
<div class="modal-overlay" id="kgDetailsModal" onclick="if(event.target===this){closeIframeModal('kgDetailsModal');}">
  <div class="modal" style="max-width: 90vw; height: 90vh;">
    <div style="display:flex; justify-content:space-between; align-items:center; padding: 10px 16px; border-bottom: 1px solid var(--border); background: var(--surface); flex-shrink: 0;">
      <span style="font-family:var(--mono); font-size:0.8rem; color:var(--purple); font-weight:700;">ENTITY DETAILS</span>
      <button class="btn-icon" style="width:26px;height:26px;" onclick="closeIframeModal('kgDetailsModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <iframe id="kgDetailsIframe" src="" style="flex: 1; width: 100%; border: none; background: var(--bg);"></iframe>
  </div>
</div>

<!-- Mini Graph Iframe Modal -->
<div class="modal-overlay" id="miniGraphModal" onclick="if(event.target===this){closeIframeModal('miniGraphModal');}">
  <div class="modal" style="max-width: 90vw; height: 90vh;">
    <div style="display:flex; justify-content:space-between; align-items:center; padding: 10px 16px; border-bottom: 1px solid var(--border); background: var(--surface); flex-shrink: 0;">
      <span style="font-family:var(--mono); font-size:0.8rem; color:var(--purple); font-weight:700;">MINI GRAPH</span>
      <button class="btn-icon" style="width:26px;height:26px;" onclick="closeIframeModal('miniGraphModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <iframe id="miniGraphIframe" src="" style="flex: 1; width: 100%; border: none; background: var(--bg);"></iframe>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<!-- Universal Reusable Engine -->
<script src="/js/AgentChatCore.js"></script>

<script>
let kgRawTree = [];
let kgTreeFilter = '';

window.agentChat = new AgentChatCore({
    modulePrefix: 'kg',
    apiEndpoint: 'kg_manager_api.php',
    onStateChange: (state) => {
        // Redraw Working Set and Reservations instantly upon DB changes
        if (state && state.session_nodes) renderWorkingSet(state.session_nodes);
        if (state && state.reservations) renderReservations(state.reservations);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    window.agentChat.init();
    loadKgTree(); // Initial load for tree sidebar
});

// ── Tree & Reservations Domain Logic ─────────────────────────────────────────

async function loadKgTree() {
    try {
        const res = await fetch('kg_manager_api.php?action=fetch_tree');
        const data = await res.json();
        if (data.success) {
            kgRawTree = data.tree || [];
            renderTree();
        }
    } catch(e) {}
}

function renderWorkingSet(nodes) {
    const list = document.getElementById('workingSetList');
    if (!nodes || !nodes.length) {
        list.innerHTML = '<div style="color:var(--text-dim);font-style:italic;font-size:0.75rem;">No nodes in current session.</div>';
        return;
    }
    list.innerHTML = nodes.map(n => `
        <div class="res-card" style="display:flex; justify-content:space-between; align-items:center;">
            <div style="flex:1; min-width:0; display:flex; align-items:center; gap:6px;">
                <span class="mg-type-pill" style="color:var(--text-dim);">${n.node_type}</span>
                <strong style="color:var(--text-bright); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">#${n.id} ${window.agentChat.escHtml(n.name)}</strong>
            </div>
            <div style="display:flex; gap:4px; flex-shrink:0;">
                <button class="tree-btn" onclick="openMiniGraph(${n.id})" title="Mini Graph">🔮</button>
                <button class="tree-btn" onclick="openEntityDetails(${n.id})" title="Edit">🕸️</button>
                <button class="tree-btn" style="color:var(--red); border-color:var(--red-dim);" onclick="unlinkNode(${n.id})" title="Unlink from session">✖</button>
            </div>
        </div>
    `).join('');
}

function renderReservations(resList) {
    const list = document.getElementById('reservationsList');
    if (!resList || !resList.length) {
        list.innerHTML = '<div style="color:var(--text-dim);font-style:italic;font-size:0.75rem;">No active reservations.</div>';
        return;
    }
    list.innerHTML = resList.map(r => `
        <div class="res-card">
            <div class="res-title">${window.agentChat.escHtml(r.project_name)}</div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span>${r.start_id} - ${r.end_id}</span>
                <span class="mg-type-pill" style="border-color:var(--purple); color:var(--purple); background:var(--purple-dim);">${r.target_table}</span>
            </div>
        </div>
    `).join('');
}

async function unlinkNode(id) {
    if (!confirm(`Unlink node #${id} from this session working set?`)) return;
    try {
        const data = await window.agentChat.api({ action: 'unlink_node', id: id });
        if (data.data_state) {
            renderWorkingSet(data.data_state.session_nodes);
        }
        window.agentChat.toast('Unlinked', 'info');
    } catch(e) {
        window.agentChat.toast(e.message, 'error');
    }
}

function filterKgTree(val) {
    kgTreeFilter = val.trim().toLowerCase();
    renderTree();
}

function getIcon(type) {
    const map = { relationship:'🔗', character:'👤', location:'📍', event:'📅', concept:'💡', arc:'🌀', episode:'🎬', note:'📝' };
    return map[type] || '📝';
}

function renderTree() {
    const wrap = document.getElementById('kgTreeWrap');
    if (!kgRawTree.length) { wrap.innerHTML = '<div style="color:var(--text-dim);font-size:0.75rem;padding:8px;">Graph is empty.</div>'; return; }

    const childMap = {};
    kgRawTree.forEach(n => {
        const p = n.parent || '#';
        if (!childMap[p]) childMap[p] = [];
        childMap[p].push(n);
    });

    let matchingJsIds = null;
    if (kgTreeFilter) {
        matchingJsIds = new Set();
        kgRawTree.forEach(n => {
            const rawText = n.text.replace(/<[^>]*>?/gm, ''); 
            if (n.type === 'node' && rawText.toLowerCase().includes(kgTreeFilter)) {
                matchingJsIds.add(n.id);
                let cur = n;
                while (cur.parent && cur.parent !== '#') {
                    matchingJsIds.add(cur.parent);
                    cur = kgRawTree.find(x => x.id === cur.parent) || { parent: '#' };
                }
            }
        });
    }

    function buildLevel(parentId, depth) {
        const children = (childMap[parentId] || []).filter(n => !matchingJsIds || matchingJsIds.has(n.id));
        if (!children.length) return '';
        let html = '';
        children.forEach(node => {
            const isFolder = node.type === 'folder';
            const jsId = node.id;
            const hasKids = !!(childMap[jsId] && childMap[jsId].filter(c => !matchingJsIds || matchingJsIds.has(c.id)).length);
            const icon = isFolder ? '📁' : getIcon(node.data && node.data.node_type ? node.data.node_type : 'note');
            const toggleBtn = (isFolder && hasKids) ? `<span class="kg-node-toggle" data-jid="${jsId}" onclick="toggleFolder(this)">▶</span>` : `<span style="width:16px;display:inline-block;flex-shrink:0;"></span>`;
            const dbId = (node.data && node.data.db_id) ? node.data.db_id : '';

            let actionsHtml = '';
            if (!isFolder && dbId) {
                actionsHtml = `
                    <div class="node-actions">
                        <button class="tree-btn" onclick="event.stopPropagation(); openMiniGraph(${dbId})">🔮</button>
                        <button class="tree-btn" onclick="event.stopPropagation(); openEntityDetails(${dbId})">🕸️</button>
                    </div>
                `;
            }

            html += `
            <div class="kg-tree-node ${isFolder ? 'is-folder' : 'is-node'}" style="padding-left:${4 + (depth * 12)}px;">
                ${toggleBtn} 
                <span class="kg-node-icon">${icon}</span> 
                <span class="kg-node-label">${node.text}</span>
                ${actionsHtml}
            </div>`;

            if (isFolder && hasKids) {
                html += `<div class="kg-tree-children" id="kg-kids-${jsId}">${buildLevel(jsId, depth + 1)}</div>`;
            }
        });
        return html;
    }

    wrap.innerHTML = buildLevel('#', 0);
    
    // Auto-open all if filtering, else keep root open
    if (kgTreeFilter) {
        wrap.querySelectorAll('.kg-tree-children').forEach(el => el.classList.add('open'));
        wrap.querySelectorAll('.kg-node-toggle').forEach(el => el.classList.add('open'));
    } else {
        const firstKids = wrap.querySelector('.kg-tree-children');
        if (firstKids) firstKids.classList.add('open');
        const firstTog = wrap.querySelector('.kg-node-toggle');
        if (firstTog) firstTog.classList.add('open');
    }
}

function toggleFolder(btn) {
    const jsId = btn.dataset.jid;
    const kids = document.getElementById('kg-kids-' + jsId);
    if (!kids) return;
    kids.classList.toggle('open');
    btn.classList.toggle('open');
}

function openEntityDetails(id) {
    const modal = document.getElementById('kgDetailsModal');
    const iframe = document.getElementById('kgDetailsIframe');
    iframe.src = `entity_form.php?entity_type=kg_nodes&entity_id=${id}`;
    modal.classList.add('open');
}

function openMiniGraph(id) {
    const modal = document.getElementById('miniGraphModal');
    const iframe = document.getElementById('miniGraphIframe');
    iframe.src = `mini_graph.php?graph=kg&node_id=${id}`;
    modal.classList.add('open');
}

function closeIframeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.querySelector(`#${id} iframe`).src = '';
    loadKgTree(); 
}
</script>

<?php echo $eruda ?? ''; ?>

</body>
</html>