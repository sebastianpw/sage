<?php
// ============================================================================
// File: public/sketches_manager.php
// AI Agent Sketches Manager UI
// ============================================================================
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { 
    header('Location: /login.php'); 
    exit; 
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=0.85, viewport-fit=cover">
<title>AI Sketches Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
:root {
  --bg:           #080b10;
  --surface:      #0e1319;
  --card:         #111820;
  --card-hover:   #151f2b;
  --border:       #1c2535;
  --text:         #c8d4e8;
  --text-dim:     #5a6a80;
  --text-bright:  #e8f0ff;
  --teal:         #2dd4bf;
  --teal-dim:     rgba(45, 212, 191, 0.1);
  --teal-glow:    rgba(45, 212, 191, 0.4);
  --amber:        #f5a623;
  --amber-dim:    rgba(245, 166, 35, 0.12);
  --green:        #4ade80;
  --green-dim:    rgba(74, 222, 128, 0.12);
  --red:          #f87171;
  --red-dim:      rgba(248, 113, 113, 0.1);
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
  --text:         #1a2233;
  --text-dim:     #7a8aaa;
  --text-bright:  #000000;
  --teal:         #0d9488;
  --teal-dim:     rgba(13, 148, 136, 0.1);
  --teal-glow:    rgba(13, 148, 136, 0.4);
}

* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--sans); font-size: 14px; line-height: 1.5; overflow: hidden; }
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-thumb { background: var(--text-dim); border-radius: 4px; }

.layout { display: flex; flex-direction: column; height: 100dvh; overflow: hidden; }
.header { height: 52px; flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; background: var(--surface); border-bottom: 1px solid var(--border); z-index: 100; }
.logo { display: flex; align-items: center; gap: 10px; font-family: var(--mono); font-size: 0.85rem; font-weight: 700; color: var(--teal); text-transform: uppercase; letter-spacing: 2px; }
.logo-icon { width: 28px; height: 28px; background: var(--teal-dim); border: 1px solid var(--teal-glow); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; }
.btn-icon { width: 36px; height: 36px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.btn-icon:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }

/* Flyouts */
.flyout { position: fixed; top: 52px; bottom: 0; width: min(340px, 88vw); background: var(--surface); border-color: var(--border); z-index: 500; display: flex; flex-direction: column; overflow: hidden; transition: transform 0.28s cubic-bezier(0.25, 1, 0.5, 1); box-shadow: 0 0 40px rgba(0,0,0,0.6); }
.flyout-left { left: 0; border-right: 1px solid var(--border); transform: translateX(-100%); }
.flyout-right { right: 0; border-left: 1px solid var(--border); transform: translateX(100%); width: min(600px, 92vw); }
.flyout.open { transform: translateX(0); }
.flyout-backdrop { position: fixed; inset: 52px 0 0 0; background: rgba(0,0,0,0.5); z-index: 499; display: none; backdrop-filter: blur(2px); }
.flyout-backdrop.visible { display: block; }
.flyout-head { padding: 12px 14px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.flyout-title { font-family: var(--mono); font-size: 0.72rem; color: var(--teal); text-transform: uppercase; letter-spacing: 2px; }
.flyout-body { flex: 1; overflow-y: auto; padding: 14px; }

/* Main Chat Structure */
.main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.chat-window { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 16px; }
.chat-empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-dim); text-align: center; gap: 8px; }
.chat-empty-state .empty-icon { font-size: 40px; color: var(--teal); margin-bottom: 6px; }

.msg { display: flex; gap: 12px; }
.msg-user { flex-direction: row-reverse; }
.msg-avatar { width: 32px; height: 32px; border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; font-family: var(--mono); }
.msg-user .msg-avatar { background: var(--teal-dim); border: 1px solid var(--teal-glow); color: var(--teal); }
.msg-ai .msg-avatar { background: var(--card); border: 1px solid var(--border); color: var(--text); }
.msg-bubble { max-width: calc(100% - 56px); padding: 12px 16px; border-radius: var(--radius-lg); font-size: 0.85rem; line-height: 1.6; }
.msg-user .msg-bubble { background: var(--teal-dim); border: 1px solid var(--teal-glow); color: var(--text-bright); border-top-right-radius: 4px; }
.msg-ai .msg-bubble { background: var(--card); border: 1px solid var(--border); color: var(--text); border-top-left-radius: 4px; }

.msg-bubble-meta { display: flex; gap: 8px; align-items: center; margin-bottom: 6px; flex-wrap: wrap; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 4px; }
.msg-timestamp { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); }
.msg-actions { margin-left: auto; display: flex; align-items: center; gap: 4px; }
.msg-action-btn { background: none; border: none; color: var(--text-dim); cursor: pointer; font-size: 13px; padding: 2px 4px; transition: color 0.15s; }
.msg-action-btn:hover { color: var(--teal); }
.msg-action-btn.delete-btn:hover { color: var(--red); }

.ops-pill { display: inline-block; margin-top: 10px; padding: 6px 12px; background: rgba(45, 212, 191, 0.08); border: 1px solid var(--teal-glow); border-radius: 6px; font-family: var(--mono); font-size: 0.7rem; color: var(--teal); cursor: pointer; }
.ops-details { display: none; margin-top: 6px; padding-top: 6px; border-top: 1px dashed var(--teal-glow); font-size: 0.65rem; color: var(--text-bright); }
.ops-pill.open .ops-details { display: block; }

/* Input Bar & Files */
.input-bar { padding: 12px 16px; border-top: 1px solid var(--border); background: var(--surface); flex-shrink: 0; }
.attached-files-container { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 8px; }
.file-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--border); font-family: var(--mono); font-size: 0.72rem; color: var(--text); }
.file-chip button { background: none; border: none; color: var(--text-dim); cursor: pointer; padding: 0; }
.file-chip button:hover { color: var(--red); }
.input-row { display: flex; gap: 8px; align-items: flex-end; }
.chat-textarea { flex: 1; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text-bright); font-family: var(--sans); font-size: 0.9rem; padding: 10px 14px; resize: none; min-height: 44px; max-height: 160px; overflow-y: auto; }
.chat-textarea:focus { outline: none; border-color: var(--teal); }
.btn-send { width: 44px; height: 44px; border-radius: var(--radius); background: var(--teal); color: #000; border: none; cursor: pointer; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: all 0.15s; }
.btn-send:hover:not(:disabled) { filter: brightness(1.15); }
.btn-send:disabled { opacity: 0.4; cursor: not-allowed; }

/* Settings & List Items */
.ctx-group { margin-bottom: 14px; }
.ctx-label { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; display: block; }
.ctx-input, .ctx-textarea, .ctx-select { width: 100%; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.75rem; padding: 8px 10px; }
.ctx-textarea { resize: vertical; min-height: 90px; }
.ctx-input:focus, .ctx-textarea:focus, .ctx-select:focus { outline: none; border-color: var(--teal); }
.btn-primary { padding: 9px 16px; background: var(--teal); color: #000; border: none; border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.75rem; font-weight: bold; text-transform: uppercase; width: 100%; transition: all 0.15s; }
.btn-primary:hover { filter: brightness(1.15); }

.list-item { padding: 8px 10px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 8px; cursor: pointer; position: relative; transition: all 0.15s; }
.list-item:hover { border-color: var(--teal); }
.list-item.active { border-color: var(--teal); background: var(--teal-dim); }
.item-meta { font-family: var(--mono); font-size: 0.65rem; color: var(--teal); margin-bottom: 2px; }
.item-title { font-size: 0.8rem; font-weight: bold; color: var(--text); padding-right: 56px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.btn-del, .btn-rename { position: absolute; top: 8px; width: 22px; height: 22px; background: transparent; border: none; color: var(--text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; border-radius: 4px; }
.btn-del { right: 6px; }
.btn-del:hover { color: var(--red); background: var(--red-dim); }
.btn-rename { right: 30px; }
.btn-rename:hover { color: var(--teal); background: var(--teal-dim); }

/* Sketches Specific Grid */
.sketches-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 10px; }
.sketch-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px; display: flex; flex-direction: column; gap: 6px; position: relative; }
.sketch-card:hover { border-color: var(--teal); }
.sketch-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 6px; }
.sketch-name { font-weight: 700; font-size: 0.85rem; color: var(--amber); word-break: break-word; }

/* Expandable Textarea (Limited to ~3 lines initially) */
.sketch-textarea {
  width: 100%; font-family: var(--mono); font-size: 0.72rem;
  background: var(--surface); padding: 8px; border-radius: 4px;
  border: 1px solid var(--border); color: var(--text); line-height: 1.45;
  resize: none; height: calc((1.45em * 3) + 16px);
  overflow: hidden; transition: all 0.2s ease-in-out;
}
.sketch-textarea:focus {
  height: 140px; overflow-y: auto; outline: none;
  border-color: var(--teal); box-shadow: 0 0 0 1px var(--teal);
  position: relative; z-index: 10;
}

.sketch-actions { display: flex; gap: 6px; justify-content: space-between; align-items: center; margin-top: 6px; border-top: 1px dashed var(--border); padding-top: 6px; }
.regen-toggle-btn { font-family: var(--mono); font-size: 0.65rem; padding: 3px 8px; border-radius: 12px; border: 1px solid var(--border); background: transparent; cursor: pointer; color: var(--text-dim); }
.regen-toggle-btn.active { background: var(--green-dim); border-color: var(--green); color: var(--green); font-weight: bold; }

/* Typing loader */
.typing { display: flex; gap: 4px; align-items: center; height: 16px; }
.typing span { width: 6px; height: 6px; background: var(--teal); border-radius: 50%; animation: bounce 1.4s infinite ease-in-out both; }
.typing span:nth-child(1) { animation-delay: -0.32s; }
.typing span:nth-child(2) { animation-delay: -0.16s; }
@keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }

/* Toast */
.toast-container { position: fixed; bottom: 80px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 8px; }
.toast { padding: 9px 15px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--teal); font-family: var(--mono); font-size: 0.75rem; color: var(--text); box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
.toast.error { border-color: var(--red); color: var(--red); }
.toast.info { border-color: var(--teal); color: var(--teal); }

/* Modals */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: none; align-items: center; justify-content: center; z-index: 99998; padding: 14px; backdrop-filter: blur(2px); }
.modal-overlay.open { display: flex; }
.modal { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); width: 100%; max-width: 440px; padding: 18px; display: flex; flex-direction: column; gap: 12px; max-height: 90vh; }
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
      <div class="logo-icon"><i class="bi bi-palette-fill"></i></div> 
      <span>Sketches Agent</span>
    </div>
    <div style="display:flex; gap:8px;">
      <button class="btn-icon" onclick="window.agentChat.downloadChat()" title="Download Chat Logs"><i class="bi bi-download"></i></button>
      <button class="btn-icon" onclick="window.agentChat.newSession()" title="New Project"><i class="bi bi-plus-lg"></i></button>
      <button class="btn-icon" onclick="window.agentChat.toggleFlyout('right')" title="View Sketches Queue"><i class="bi bi-grid-fill"></i></button>
    </div>
  </header>

  <!-- Left Flyout: Sessions & Settings -->
  <div class="flyout flyout-left" id="flyoutLeft">
    <div class="flyout-head">
      <span class="flyout-title">Project Setup</span>
      <button class="btn-icon" style="width:28px;height:28px;" onclick="window.agentChat.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="flyout-body">
      <div id="sessionNameBanner" style="margin-bottom:12px; padding:8px 10px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); font-family:var(--mono); font-size:0.7rem; color:var(--teal);"></div>

      <div class="ctx-group">
        <label class="ctx-label">Directives & Visual Lore</label>
        <textarea id="setLore" class="ctx-textarea" placeholder="Overall world style, visual aesthetics, lighting rules, negative constraints..."></textarea>
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

  <!-- Right Flyout: Sketches Grid / Working Set -->
  <div class="flyout flyout-right" id="flyoutRight">
    <div class="flyout-head">
      <span class="flyout-title">Session Sketches</span>
      <div style="display:flex; gap:6px; align-items:center;">
        <button class="btn-icon" style="width:28px;height:28px;font-size:13px;" onclick="triggerScheduler()" title="Trigger Scheduler (Task #15)"><i class="bi bi-play-circle-fill" style="color:var(--green)"></i></button>
        <button class="btn-icon" style="width:28px;height:28px;" onclick="window.agentChat.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <div class="flyout-body">
      <div style="display:flex; gap:8px; margin-bottom:12px;">
        <input type="text" id="sketchSearch" class="ctx-input" placeholder="Search prompts or names..." oninput="filterSketches(this.value)">
        <button class="btn-icon" onclick="openManualSketchModal()" title="Add Manually"><i class="bi bi-plus-lg"></i></button>
      </div>
      <div id="sketchesGrid" class="sketches-grid"></div>
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

        <textarea id="chatInput" class="chat-textarea" placeholder="e.g. Find all sketches about Emberveil and link them..." rows="1"></textarea>
        
        <button id="btnSend" class="btn-send"><i class="bi bi-arrow-up"></i></button>
      </div>
    </div>
  </main>
</div>

<!-- Manual Add Sketch Modal -->
<div class="modal-overlay" id="manualSketchModal" onclick="if(event.target===this) closeModal('manualSketchModal')">
  <div class="modal">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <span style="font-family:var(--mono); font-size:0.8rem; color:var(--teal); font-weight:700;">NEW SKETCH</span>
      <button class="btn-icon" style="width:26px;height:26px;" onclick="closeModal('manualSketchModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <input type="text" id="mSketchName" class="ctx-input" placeholder="Name (e.g. asteroid_belt_approach)">
    <textarea id="mSketchPrompt" class="ctx-textarea" placeholder="Generation Prompt (description)..." style="min-height:120px;"></textarea>
    <label style="display:flex; align-items:center; gap:8px; font-family:var(--mono); font-size:0.75rem; color:var(--text-dim); cursor:pointer;">
      <input type="checkbox" id="mSketchRegen" checked style="accent-color:var(--teal); width:16px; height:16px;">
      Flag for generation immediately (`regenerate_images = 1`)
    </label>
    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:8px;">
      <button class="btn-icon" style="width:auto; padding:6px 12px; font-size:0.75rem;" onclick="closeModal('manualSketchModal')">Cancel</button>
      <button class="btn-primary" style="width:auto; padding:6px 16px;" onclick="saveManualSketch()">Save Sketch</button>
    </div>
  </div>
</div>

<!-- Entity Details Iframe Modal (matches Cruzz functionality) -->
<div class="modal-overlay" id="sketchDetailsModal" onclick="if(event.target===this){closeModal('sketchDetailsModal'); document.getElementById('sketchDetailsIframe').src='';}">
  <div class="modal" style="max-width: 90vw; height: 90vh; padding: 0; display: flex; flex-direction: column;">
    <div style="display:flex; justify-content:space-between; align-items:center; padding: 10px 16px; border-bottom: 1px solid var(--border); background: var(--surface); flex-shrink: 0;">
      <span style="font-family:var(--mono); font-size:0.8rem; color:var(--teal); font-weight:700;">ENTITY DETAILS</span>
      <button class="btn-icon" style="width:26px;height:26px;" onclick="closeModal('sketchDetailsModal'); document.getElementById('sketchDetailsIframe').src='';"><i class="bi bi-x-lg"></i></button>
    </div>
    <iframe id="sketchDetailsIframe" src="" style="flex: 1; width: 100%; border: none; background: var(--bg);"></iframe>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<!-- Universal Reusable Engine -->
<script src="/js/AgentChatCore.js"></script>

<script>
let currentSketches = [];
let searchQuery = '';

window.agentChat = new AgentChatCore({
    modulePrefix: 'sketches',
    apiEndpoint: 'sketches_api.php',
    onStateChange: (sketches) => {
        currentSketches = sketches || [];
        renderSketchesGrid();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    window.agentChat.init();
});

// ── Sketch Domain Logic ──────────────────────────────────────────────────
function renderSketchesGrid() {
    const grid = document.getElementById('sketchesGrid');
    if (!grid) return;

    let items = currentSketches;
    if (searchQuery) {
        const q = searchQuery.toLowerCase();
        items = items.filter(s => 
            (s.name && s.name.toLowerCase().includes(q)) || 
            (s.description && s.description.toLowerCase().includes(q))
        );
    }

    if (!items.length) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--text-dim);font-size:0.75rem;padding:24px;">No sketches in this session. Ask the AI to brainstorm scene prompts or search existing ones!</div>';
        return;
    }

    grid.innerHTML = items.map(s => {
        const isRegen = parseInt(s.regenerate_images) === 1;
        return `
        <div class="sketch-card">
            <div class="sketch-header">
                <div class="sketch-name">#${s.id} ${window.agentChat.escHtml(s.name)}</div>
                <button class="btn-del" onclick="unlinkSketch(${s.id})" title="Remove from Session"><i class="bi bi-x-circle"></i></button>
            </div>
            
            <textarea class="sketch-textarea" data-id="${s.id}" data-orig="${window.agentChat.escAttr(s.description || '')}" onblur="saveSketchDesc(this)" placeholder="Sketch description...">${window.agentChat.escHtml(s.description || '')}</textarea>
            
            <div class="sketch-actions">
                <button class="regen-toggle-btn ${isRegen ? 'active' : ''}" onclick="toggleRegen(${s.id}, ${isRegen ? 0 : 1})">
                    ${isRegen ? '<i class="bi bi-check-circle-fill"></i> Queued' : 'Queue Generation'}
                </button>
                <div style="display:flex; gap:4px;">
                    <button class="btn-icon" style="width:26px;height:26px;font-size:12px;" onclick="openSketchDetails(${s.id})" title="Edit Full Entity">🕸️</button>
                    <button class="btn-icon" style="width:26px;height:26px;font-size:12px;" onclick="window.agentChat.sendMessage('Please refine sketch #${s.id} to be more cinematic.')" title="Refine via AI">
                      <i class="bi bi-stars"></i>
                    </button>
                </div>
            </div>
        </div>
        `;
    }).join('');
}

function filterSketches(q) {
    searchQuery = q.trim();
    renderSketchesGrid();
}

async function saveSketchDesc(el) {
    const id = el.getAttribute('data-id');
    const orig = el.getAttribute('data-orig');
    const current = el.value.trim();
    
    if (current === orig) return; // No change made

    try {
        await window.agentChat.api({ action: 'update_sketch_desc', id: id, description: current });
        el.setAttribute('data-orig', current);
        const item = currentSketches.find(x => x.id == id);
        if (item) item.description = current;
        window.agentChat.toast('Description saved', 'success');
    } catch (e) {
        el.value = orig;
        window.agentChat.toast(e.message, 'error');
    }
}

async function toggleRegen(id, nextVal) {
    try {
        await window.agentChat.api({ action: 'toggle_regen', id, value: nextVal });
        const item = currentSketches.find(x => x.id == id);
        if (item) item.regenerate_images = nextVal;
        renderSketchesGrid();
        window.agentChat.toast(nextVal ? 'Flagged for generation' : 'Unflagged', 'info');
    } catch (e) {
        window.agentChat.toast(e.message, 'error');
    }
}

async function triggerScheduler() {
    try {
        const res = await window.agentChat.api({ action: 'run_scheduler' });
        window.agentChat.toast(res.message || 'Scheduler triggered (Task #15)', 'success');
    } catch (e) {
        window.agentChat.toast(e.message, 'error');
    }
}

async function unlinkSketch(id) {
    if (!confirm(`Unlink sketch #${id} from this session? (It will not be deleted globally)`)) return;
    try {
        const res = await window.agentChat.api({ action: 'unlink_sketch', id });
        if (res.data_state) {
            currentSketches = res.data_state;
            renderSketchesGrid();
        }
        window.agentChat.toast('Sketch unlinked', 'info');
    } catch (e) {
        window.agentChat.toast(e.message, 'error');
    }
}

function openManualSketchModal() {
    document.getElementById('mSketchName').value = '';
    document.getElementById('mSketchPrompt').value = '';
    document.getElementById('mSketchRegen').checked = true;
    document.getElementById('manualSketchModal').classList.add('open');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

async function saveManualSketch() {
    const name = document.getElementById('mSketchName').value.trim();
    const description = document.getElementById('mSketchPrompt').value.trim();
    const regenerate_images = document.getElementById('mSketchRegen').checked ? 1 : 0;

    if (!name) {
        window.agentChat.toast('Name is required', 'error');
        return;
    }

    try {
        const res = await window.agentChat.api({
            action: 'save_sketch',
            name,
            description,
            regenerate_images
        });
        closeModal('manualSketchModal');
        if (res.data_state) {
            currentSketches = res.data_state;
            renderSketchesGrid();
        }
        window.agentChat.toast('Sketch created and queued', 'success');
    } catch (e) {
        window.agentChat.toast(e.message, 'error');
    }
}

// Exactly mirrors how cruzz.php handles the 🕸️ button
function openSketchDetails(id) {
    const modal = document.getElementById('sketchDetailsModal');
    const iframe = document.getElementById('sketchDetailsIframe');
    iframe.src = `entity_form.php?entity_type=sketches&entity_id=${id}`;
    modal.classList.add('open');
}
</script>

<?php echo $eruda ?? ''; ?>

</body>
</html>