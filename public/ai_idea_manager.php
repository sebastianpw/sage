<?php
// ============================================================================
// File: public/ai_idea_manager.php
// ============================================================================
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>AI Idea Manager</title>
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
  --border:       #1c2535;
  --text:         #c8d4e8;
  --text-dim:     #5a6a80;
  --text-bright:  #e8f0ff;
  --teal:         #2dd4bf;
  --teal-dim:     rgba(45, 212, 191, 0.1);
  --teal-glow:    rgba(45, 212, 191, 0.4);
  --amber:        #f5a623;
  --green:        #4ade80;
  --red:          #f87171;
  --mono:         'Space Mono', 'Fira Mono', monospace;
  --sans:         'Syne', system-ui, sans-serif;
  --radius:       6px;
  --radius-lg:    10px;
}
:root[data-theme="light"] {
  --bg:           #f4f6fa;
  --surface:      #ffffff;
  --card:         #ffffff;
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

.flyout { position: fixed; top: 52px; bottom: 0; width: min(320px, 88vw); background: var(--surface); border-color: var(--border); z-index: 500; display: flex; flex-direction: column; overflow: hidden; transition: transform 0.3s cubic-bezier(0.25, 1, 0.5, 1); box-shadow: 0 0 40px rgba(0,0,0,0.6); }
.flyout-left { left: 0; border-right: 1px solid var(--border); transform: translateX(-100%); }
.flyout-right { right: 0; border-left: 1px solid var(--border); transform: translateX(100%); }
.flyout.open { transform: translateX(0); }
.flyout-backdrop { position: fixed; inset: 52px 0 0 0; background: rgba(0,0,0,0.5); z-index: 499; display: none; backdrop-filter: blur(2px); }
.flyout-backdrop.visible { display: block; }
.flyout-head { padding: 12px 14px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.flyout-title { font-family: var(--mono); font-size: 0.7rem; color: var(--teal); text-transform: uppercase; letter-spacing: 2px; }
.flyout-body { flex: 1; overflow-y: auto; padding: 12px; }

.flyout-right { width: min(100vw, 720px); }

.flyout-tabs { display: flex; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.flyout-tab { flex: 1; padding: 10px 4px; font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim); text-align: center; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid transparent; transition: all 0.15s; background: none; border-top: none; border-left: none; border-right: none; }
.flyout-tab.active { color: var(--teal); border-bottom-color: var(--teal); }
.flyout-tab:hover:not(.active) { color: var(--text); }
.flyout-tab-panel { display: none; }
.flyout-tab-panel.active { display: block; }

/* Dashboard search */
.search-wrap { position: relative; margin-bottom: 10px; display: flex; align-items: center; }
.search-wrap .search-icon { position: absolute; left: 11px; color: var(--text-dim); font-size: 14px; pointer-events: none; }
.search-wrap input { width: 100%; padding-left: 34px; padding-right: 36px; }
.search-clear { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 26px; height: 26px; background: transparent; border: none; color: var(--text-dim); cursor: pointer; display: none; align-items: center; justify-content: center; font-size: 12px; border-radius: 4px; }
.search-clear:hover { color: var(--red); background: rgba(248,113,113,0.1); }
.search-meta { font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim); margin-bottom: 8px; display: none; }
.search-meta strong { color: var(--teal); font-weight: 700; }

.dash-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; }
.dash-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 14px; display: flex; flex-direction: column; gap: 8px; }
.dash-card-title { font-family: var(--mono); font-size: 0.9rem; color: var(--amber); font-weight: 700; word-break: break-word; }
.dash-card-desc { font-size: 0.75rem; color: var(--text-dim); min-height: 32px; line-height: 1.4; word-break: break-word; }
.dash-card-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 8px; flex-wrap: wrap; gap: 6px; }
.dash-card-count { font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim); }

.ctx-group { margin-bottom: 14px; }
.ctx-label { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; display: block; }
.ctx-input, .ctx-textarea, .ctx-select { width: 100%; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.75rem; padding: 8px 10px; transition: border-color 0.15s; }
.ctx-textarea { resize: vertical; min-height: 80px; }
.ctx-input:focus, .ctx-textarea:focus, .ctx-select:focus { outline: none; border-color: var(--teal); }
.btn-primary { padding: 8px 16px; background: var(--teal); color: #000; border: none; border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.75rem; font-weight: bold; text-transform: uppercase; width: 100%; transition: all 0.15s; }
.btn-primary:hover { filter: brightness(1.15); }

.list-item { padding: 8px 10px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 8px; cursor: pointer; position: relative; transition: all 0.15s; }
.list-item:hover { border-color: var(--teal); }
.list-item.active { border-color: var(--teal); background: var(--teal-dim); }
.item-meta { font-family: var(--mono); font-size: 0.65rem; color: var(--teal); margin-bottom: 2px; }
.item-title { font-size: 0.8rem; font-weight: bold; color: var(--text); padding-right: 56px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.btn-del { position: absolute; right: 6px; top: 8px; width: 22px; height: 22px; background: transparent; border: none; color: var(--text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; border-radius: 4px; }
.btn-del:hover { color: var(--red); background: rgba(248,113,113,0.1); }
.btn-rename { position: absolute; right: 30px; top: 8px; width: 22px; height: 22px; background: transparent; border: none; color: var(--text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; border-radius: 4px; }
.btn-rename:hover { color: var(--teal); background: var(--teal-dim); }

.cluster-block { margin-bottom: 16px; }
.cluster-head { font-family: var(--mono); font-size: 0.75rem; color: var(--text-bright); border-bottom: 1px solid var(--border); padding-bottom: 4px; margin-bottom: 8px; }
.idea-card { padding: 8px; background: var(--card); border: 1px solid var(--border); border-radius: 4px; margin-bottom: 6px; }
.idea-card-header { display: flex; justify-content: space-between; align-items: flex-start; }
.idea-title { font-size: 0.75rem; font-weight: bold; color: var(--text); flex: 1; }
.idea-status { font-family: var(--mono); font-size: 0.55rem; padding: 2px 6px; border-radius: 10px; text-transform: uppercase; font-weight: bold; }
.status-yes { background: rgba(74,222,128,0.15); color: var(--green); border: 1px solid var(--green); }
.status-no { background: rgba(248,113,113,0.15); color: var(--red); border: 1px solid var(--red); }
.status-unassigned { background: var(--border); color: var(--text-dim); }
.idea-desc { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); margin-top: 4px; }
.idea-actions { display: flex; gap: 6px; margin-top: 6px; }
.idea-btn { font-size: 0.65rem; background: transparent; border: 1px solid var(--border); color: var(--text-dim); border-radius: 4px; padding: 3px 8px; cursor: pointer; font-family: var(--mono); transition: all 0.15s; }
.idea-btn:hover { border-color: var(--teal); color: var(--teal); }
.idea-btn.active-yes { background: var(--green); color: #000; border-color: var(--green); }
.idea-btn.active-no { background: var(--red); color: #fff; border-color: var(--red); }
.idea-btn.active-un { background: var(--text-dim); color: var(--bg); border-color: var(--text-dim); }
.idea-btn.ai-has-log { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }

/* Search match highlighting */
.idea-title .hl, .idea-desc .hl, .dash-card-desc .hl, .aim-item-title .hl, .aim-item-desc .hl { background: rgba(245,166,35,0.22); color: var(--amber); border-radius: 2px; padding: 0 1px; }

.main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.chat-window { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 16px; }
.msg { display: flex; gap: 12px; }
.msg-user { flex-direction: row-reverse; }
.msg-avatar { width: 32px; height: 32px; border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; font-family: var(--mono); }
.msg-user .msg-avatar { background: var(--teal-dim); border: 1px solid var(--teal-glow); color: var(--teal); }
.msg-ai .msg-avatar { background: var(--card); border: 1px solid var(--border); color: var(--text); }
.msg-bubble { max-width: calc(100% - 56px); padding: 12px 16px; border-radius: var(--radius-lg); font-size: 0.85rem; line-height: 1.6; }
.msg-user .msg-bubble { background: var(--teal-dim); border: 1px solid var(--teal-glow); color: var(--text-bright); border-top-right-radius: 4px; }
.msg-ai .msg-bubble { background: var(--card); border: 1px solid var(--border); color: var(--text); border-top-left-radius: 4px; }

.ops-pill { display: inline-block; margin-top: 10px; padding: 6px 12px; background: rgba(45, 212, 191, 0.08); border: 1px solid var(--teal-glow); border-radius: 6px; font-family: var(--mono); font-size: 0.7rem; color: var(--teal); cursor: pointer; }
.ops-details { display: none; margin-top: 6px; padding-top: 6px; border-top: 1px dashed var(--teal-glow); font-size: 0.65rem; color: var(--text-bright); }
.ops-pill.open .ops-details { display: block; }

.input-bar { padding: 12px 16px; border-top: 1px solid var(--border); background: var(--surface); flex-shrink: 0; }
.input-row { display: flex; gap: 10px; align-items: flex-end; }
.chat-textarea { flex: 1; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text-bright); font-family: var(--sans); font-size: 0.9rem; padding: 10px 14px; resize: none; min-height: 44px; max-height: 150px; overflow-y: auto; }
.chat-textarea:focus { outline: none; border-color: var(--teal); }
.btn-send { width: 44px; height: 44px; border-radius: var(--radius); background: var(--teal); color: #000; border: none; cursor: pointer; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: all 0.15s; }
.btn-send:hover:not(:disabled) { filter: brightness(1.2); }
.btn-send:disabled { opacity: 0.5; cursor: not-allowed; }

.typing { display: flex; gap: 4px; align-items: center; height: 20px; }
.typing span { width: 6px; height: 6px; background: var(--teal); border-radius: 50%; animation: bounce 1.4s infinite ease-in-out both; }
.typing span:nth-child(1) { animation-delay: -0.32s; }
.typing span:nth-child(2) { animation-delay: -0.16s; }
@keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }

.toast-container { position: fixed; bottom: 80px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 8px; }
.toast { padding: 10px 16px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--teal); font-family: var(--mono); font-size: 0.75rem; color: var(--text); box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
.toast.error { border-color: var(--red); color: var(--red); }

.aim-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.75); display: none; align-items: center; justify-content: center; z-index: 99998; padding: 12px; backdrop-filter: blur(3px); }
.aim-modal-overlay.open { display: flex; }
.aim-modal { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); width: 100%; max-width: 640px; max-height: 90vh; display: flex; flex-direction: column; gap: 12px; padding: 18px; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.7); }
.aim-modal-header { display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap; }
.aim-modal-title { font-family: var(--mono); font-size: 0.85rem; color: var(--teal); text-transform: uppercase; letter-spacing: 1.5px; }
.aim-input, .aim-textarea, .aim-select { width: 100%; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.8rem; padding: 8px 10px; }
.aim-textarea { resize: vertical; min-height: 80px; }
.aim-input:focus, .aim-textarea:focus, .aim-select:focus { outline: none; border-color: var(--teal); }

.aim-item-row { display: flex; flex-direction: column; gap: 6px; padding: 10px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); margin-bottom: 6px; }
.aim-item-title { font-weight: 700; font-size: 0.85rem; flex: 1; min-width: 0; word-break: break-word; }
.aim-item-desc { font-family: var(--mono); font-size: 0.72rem; color: var(--text-dim); line-height: 1.4; word-break: break-word; }
.aim-item-actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }

.aim-editable { cursor: text; border-radius: 4px; padding: 2px 4px; margin: -2px -4px; transition: background 0.15s; min-height: 1.2em; }
.aim-editable:hover { background: rgba(45, 212, 191, 0.06); }
.aim-editable:focus { outline: none; background: var(--surface); box-shadow: 0 0 0 1px var(--teal); }
.aim-item-desc:empty::before, .q-desc:empty::before { content: attr(data-placeholder); color: var(--text-dim); font-style: italic; opacity: 0.5; }

.q-progress { font-family: var(--mono); font-size: 0.75rem; color: var(--text-dim); }
.q-cluster-badge { font-family: var(--mono); font-size: 0.68rem; color: var(--teal); background: var(--surface); border: 1px solid var(--border); padding: 2px 8px; border-radius: 12px; margin-left: 8px; }
.q-title { font-size: 1.2rem; color: var(--amber); line-height: 1.3; margin: 8px 0; font-weight: 700; word-break: break-word; }
.q-desc { font-size: 0.9rem; line-height: 1.5; min-height: 50px; word-break: break-word; }
.q-actions { display: flex; justify-content: center; gap: 12px; margin-top: 12px; flex-wrap: wrap; }
.q-actions .idea-btn { font-size: 0.75rem; padding: 6px 14px; }
.q-nav { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 12px; margin-top: 12px; gap: 8px; flex-wrap: wrap; }

.q-ai-toggle-wrap { text-align: center; margin-top: 12px; }
.q-ai-toggle { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: transparent; border: 1px dashed var(--border); color: var(--text-dim); border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.7rem; transition: all 0.15s; }
.q-ai-toggle:hover, .q-ai-toggle.open, .q-ai-toggle.ai-has-log { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); border-style: solid; }
.q-ai-panel { display: none; margin-top: 10px; padding: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); }
.q-ai-panel.open { display: block; }
.q-ai-log { max-height: 260px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; margin-bottom: 8px; }
.q-ai-log:empty { display: none; margin-bottom: 0; }
.q-ai-msg { padding: 8px 10px; border-radius: var(--radius); font-size: 0.75rem; line-height: 1.5; word-break: break-word; }
.q-ai-msg.user { background: var(--teal-dim); border: 1px solid var(--teal-glow); color: var(--text-bright); align-self: flex-end; max-width: 85%; }
.q-ai-msg.ai { background: var(--card); border: 1px solid var(--border); color: var(--text); align-self: flex-start; max-width: 95%; }
.q-ai-msg.err { background: rgba(248,113,113,0.08); border: 1px solid var(--red); color: var(--red); align-self: flex-start; max-width: 95%; }
.q-ai-input-row { display: flex; gap: 6px; align-items: flex-end; }
.q-ai-input { flex: 1; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--sans); font-size: 0.8rem; padding: 8px 10px; resize: none; min-height: 36px; max-height: 120px; }
.q-ai-input:focus { outline: none; border-color: var(--teal); }
.q-ai-send { width: 36px; height: 36px; background: var(--teal); color: #000; border: none; border-radius: var(--radius); cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.q-ai-send:hover:not(:disabled) { filter: brightness(1.15); }
.q-ai-send:disabled { opacity: 0.5; cursor: not-allowed; }

.seg-group { display: inline-flex; background: var(--bg); border: 1px solid var(--border); border-radius: 20px; padding: 2px; gap: 2px; flex-wrap: wrap; }
.seg-btn { padding: 4px 10px; border-radius: 16px; font-size: 0.68rem; font-weight: 700; border: none; background: transparent; color: var(--text-dim); cursor: pointer; transition: all 0.15s; font-family: var(--mono); }
.seg-btn.active { background: var(--teal); color: #000; }
.seg-btn.active-yes { background: var(--green); color: #000; }
.seg-btn.active-no { background: var(--red); color: #fff; }
.seg-btn.active-amber { background: var(--amber); color: #000; }

.scope-note { font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; display: block; }

/* Cluster multi-select chips */
.cluster-chip-row { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.cluster-chip { padding: 4px 10px; border-radius: 16px; font-family: var(--mono); font-size: 0.65rem; font-weight: 700; border: 1px solid var(--border); background: transparent; color: var(--text-dim); cursor: pointer; transition: all 0.15s; }
.cluster-chip:hover { border-color: var(--teal); color: var(--teal); }
.cluster-chip.selected { background: var(--teal); color: #000; border-color: var(--teal); }
.cluster-chip.uncat { border-color: rgba(245,166,35,0.4); color: var(--amber); }
.cluster-chip.uncat.selected { background: var(--amber); color: #000; border-color: var(--amber); }

/* Stats block */
.stats-total { font-family: var(--mono); font-size: 1.5rem; color: var(--text-bright); font-weight: 700; line-height: 1.2; }
.stats-total small { font-size: 0.7rem; color: var(--text-dim); font-weight: 400; margin-left: 4px; }
.stats-section-label { font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-top: 10px; margin-bottom: 4px; }
.stats-row { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px dashed var(--border); font-family: var(--mono); font-size: 0.72rem; }
.stats-row:last-child { border-bottom: none; }
.stats-row .stats-key { color: var(--text); }
.stats-row .stats-val { color: var(--text-bright); font-weight: 700; }
.stats-row .stats-sub { color: var(--text-dim); font-size: 0.65rem; }

/* ── KG Picker Tree & Semantic Slice (AIM) ── */
.wr-kg-tabs {
  display: flex; border-bottom: 1px solid var(--border); padding: 0 16px; flex-shrink: 0;
}
.wr-kg-tab {
  padding: 10px 14px; font-family: var(--mono); font-size: 0.72rem; font-weight: 600;
  color: var(--text-dim); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px;
  background: none; border-top: none; border-left: none; border-right: none; transition: all 0.15s;
}
.wr-kg-tab:hover { color: var(--text); }
.wr-kg-tab.active { color: var(--teal); border-bottom-color: var(--teal); }

.wr-kg-panes { flex: 1; overflow: hidden; display: flex; flex-direction: column; min-height: 0; }
.wr-kg-pane  { display: none; flex: 1; flex-direction: column; overflow: hidden; min-height: 0; }
.wr-kg-pane.active { display: flex; }

.wr-kg-picker-header {
  display: flex; align-items: center; gap: 8px; padding: 8px 14px;
  border-bottom: 1px solid var(--border); flex-shrink: 0; background: var(--surface);
}
.wr-kg-picker-header span {
  font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.04em; color: var(--text-dim); flex: 1;
}
.wr-kg-picker-select-all {
  background: none; border: none; color: var(--teal);
  font-size: 0.72rem; cursor: pointer; padding: 0; font-weight: 600; font-family: var(--sans);
}
.wr-kg-picker-select-all:hover { text-decoration: underline; }
.wr-kg-picker-tree-wrap {
  flex: 1; overflow-y: auto; padding: 4px 0; background: var(--bg);
}
.wr-kg-tree-node {
  display: flex; align-items: center; gap: 6px; padding: 5px 10px;
  cursor: pointer; user-select: none; font-size: 0.82rem; border-radius: 4px; margin: 1px 4px;
  transition: background 0.1s;
}
.wr-kg-tree-node:hover { background: var(--teal-dim); }
.wr-kg-tree-node input[type=checkbox] {
  width: 14px; height: 14px; accent-color: var(--teal); cursor: pointer; flex-shrink: 0; margin: 0;
}
.wr-kg-tree-node input[type=checkbox]:indeterminate { opacity: 0.7; }
.wr-kg-node-toggle {
  width: 16px; height: 16px; display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; font-size: 0.65rem; color: var(--text-dim); cursor: pointer;
  border-radius: 3px; transition: background 0.1s, transform 0.15s;
}
.wr-kg-node-toggle:hover { background: var(--border); }
.wr-kg-node-toggle.open { transform: rotate(90deg); }
.wr-kg-node-icon { font-size: 0.85rem; flex-shrink: 0; opacity: 0.75; }
.wr-kg-node-label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text); }
.wr-kg-tree-node.is-folder > .wr-kg-node-label { font-weight: 600; color: var(--text); }
.wr-kg-tree-node.is-node > .wr-kg-node-label { color: var(--text-dim); }
.wr-kg-tree-children { display: none; }
.wr-kg-tree-children.open { display: block; }

/* Semantic Slice Styles */
.wr-kge-query-row { display: flex; gap: 8px; padding: 10px 14px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.wr-kge-query-input {
  flex: 1; padding: 7px 10px; background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text); font-family: var(--sans); font-size: 0.82rem;
}
.wr-kge-query-input:focus { outline: none; border-color: var(--teal); }
.wr-kge-n-select {
  padding: 7px 8px; background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text); font-family: var(--sans); font-size: 0.8rem;
}
.wr-kge-hits-area { flex: 1; overflow-y: auto; min-height: 0; background: var(--bg); }
.wr-kge-hits-empty {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  height: 100%; gap: 8px; color: var(--text-dim); font-size: 0.8rem; padding: 30px 16px; text-align: center;
}
.wr-kge-hit-row {
  display: flex; align-items: flex-start; padding: 8px 12px; border-bottom: 1px solid var(--border);
  gap: 10px; cursor: pointer; transition: background 0.12s;
}
.wr-kge-hit-row:hover { background: var(--teal-dim); }
.wr-kge-hit-row.selected { background: rgba(45, 212, 191, 0.12); }
.wr-kge-hit-check { width: 14px; height: 14px; flex-shrink: 0; margin-top: 3px; accent-color: var(--teal); cursor: pointer; }
.wr-kge-hit-body { flex: 1; min-width: 0; }
.wr-kge-hit-name { font-weight: 600; font-size: 0.82rem; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; color: var(--text); }
.wr-kge-hit-excerpt { font-size: 0.72rem; color: var(--text-dim); margin-top: 3px; line-height: 1.4; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.wr-kge-hit-score { font-size: 0.68rem; color: var(--text-dim); font-family: var(--mono); flex-shrink: 0; padding-top: 3px; }
.wr-kge-score-bar { display: inline-block; height: 3px; border-radius: 2px; background: var(--teal); opacity: 0.5; vertical-align: middle; margin-left: 4px; flex-shrink: 0; }
.wr-kge-hits-header {
  padding: 8px 12px 6px; font-size: 0.7rem; font-weight: 700; color: var(--text-dim);
  display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border);
  position: sticky; top: 0; background: var(--card); z-index: 1; font-family: var(--mono); text-transform: uppercase;
}
.wr-kge-type-pill { font-size: 0.62rem; font-weight: 700; padding: 1px 5px; border-radius: 6px; white-space: nowrap; font-family: var(--mono); border: 1px solid var(--border); color: var(--text-dim); }
.wr-kge-status-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; margin-top: 6px; }
.wr-kge-dot-filled { background: var(--green); }
.wr-kge-dot-partial { background: var(--amber); }
.wr-kge-dot-stub { background: var(--text-dim); }
.wr-kge-dot-empty { background: var(--border); }
.wr-kge-loading-bar { height: 2px; background: var(--teal); position: absolute; top: 0; left: 0; animation: wr-kge-load 1.4s ease-in-out infinite; display: none; width: 100%; z-index: 10; }
@keyframes wr-kge-load { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
</style>
</head>
<body>

<div class="layout">
  <div class="flyout-backdrop" id="backdrop" onclick="App.closeFlyouts()"></div>

  <header class="header">
    <button class="btn-icon" onclick="App.toggleFlyout('left')"><i class="bi bi-layout-sidebar"></i></button>
    <div class="logo">
      <div class="logo-icon"><i class="bi bi-lightbulb-fill"></i></div> AI Idea Manager
    </div>
    <div style="display:flex; gap:8px;">
      <button class="btn-icon" onclick="App.newSession()" title="New Project"><i class="bi bi-plus-lg"></i></button>
      <button class="btn-icon" onclick="App.toggleFlyout('right')"><i class="bi bi-folder-fill"></i></button>
    </div>
  </header>

  <!-- Left Flyout -->
  <div class="flyout flyout-left" id="flyoutLeft">
    <div class="flyout-head">
      <span class="flyout-title">Project Scope</span>
      <button class="btn-icon" style="width:28px;height:28px;" onclick="App.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="flyout-body">
      <div id="leftScopeBanner" style="margin-bottom:12px; padding:8px 10px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); display:none;">
        <i class="bi bi-folder2-open"></i> <span id="leftScopeName"></span>
      </div>

      <div class="ctx-group">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
          <label class="ctx-label" style="margin-bottom:0;">Story Lore & Context (this project)</label>
          <button type="button" class="btn-icon" style="width:26px;height:26px;font-size:13px;" onclick="App.openKgPickerModal()" title="Select KG Nodes">
            <i class="bi bi-diagram-3"></i>
          </button>
        </div>
        <textarea id="setLore" class="ctx-textarea" style="min-height:150px;" placeholder="Describe your universe, rules, and general lore for THIS project..."></textarea>
      </div>
      <div class="ctx-group">
        <label class="ctx-label">AI Model (global preference)</label>
        <select id="setModel" class="ctx-select"></select>
      </div>
      <button class="btn-primary" onclick="App.saveSettings()">Save Configuration</button>

      <div style="margin: 24px 0 12px; font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; border-bottom:1px solid var(--border); padding-bottom:4px;">Projects</div>
      <div id="sessionsList"></div>
    </div>
  </div>

  <!-- Right Flyout -->
  <div class="flyout flyout-right" id="flyoutRight">
    <div class="flyout-head">
      <span class="flyout-title">Idea Portfolio</span>
      <button class="btn-icon" style="width:28px;height:28px;" onclick="App.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="flyout-tabs">
      <button class="flyout-tab active" data-aim-tab="dashboard" onclick="App.setRightTab('dashboard')">Dashboard</button>
      <button class="flyout-tab" data-aim-tab="maintenance" onclick="App.setRightTab('maintenance')">Maintenance</button>
    </div>
    <div class="flyout-body">

      <div class="flyout-tab-panel active" id="aim-panel-dashboard">

        <!-- Live search (filters items across the whole project) -->
        <div class="search-wrap">
          <i class="bi bi-search search-icon"></i>
          <input type="text" id="dashSearch" class="aim-input" placeholder="Search ideas — #id, name, or content..." oninput="App.onDashSearch(this.value)">
          <button class="search-clear" id="dashSearchClear" onclick="App.clearDashSearch()" title="Clear search"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="search-meta" id="dashSearchMeta"></div>

        <div style="display:flex; gap:6px; margin-bottom:10px; flex-wrap:wrap; align-items:center;">
          <button class="idea-btn" id="dashViewCards" onclick="App.setDashView('cards')">Cards</button>
          <button class="idea-btn" id="dashViewDetailed" onclick="App.setDashView('detailed')">Detailed</button>
          <button class="idea-btn" onclick="App.openGlobalQuestionnaire()" title="Review all unanswered ideas across clusters"><i class="bi bi-lightning-charge-fill"></i> Global Questionnaire</button>
          <button class="idea-btn" style="margin-left:auto; border-color:var(--teal); color:var(--teal);" onclick="App.openClusterModal(0)"><i class="bi bi-plus-lg"></i> New Cluster</button>
        </div>
        <div id="dashboardCards" class="dash-grid"></div>
        <div id="portfolioView" style="display:none;"></div>
      </div>

      <div class="flyout-tab-panel" id="aim-panel-maintenance">

        <!-- STATS -->
        <div class="dash-card" style="margin-bottom:12px;">
          <div class="dash-card-title" style="color: var(--teal);">Stats</div>
          <div class="dash-card-desc">Live snapshot of this project's ideas. Filter below — the numbers reflect the current selection.</div>

          <div class="seg-group" style="margin-top:8px;">
            <button type="button" class="seg-btn active" id="statsChipAll" onclick="App.statsClickAll()">All</button>
            <button type="button" class="seg-btn active-yes" id="statsChipYes" onclick="App.statsToggleStatus('yes')">Yes</button>
            <button type="button" class="seg-btn active-no" id="statsChipNo" onclick="App.statsToggleStatus('no')">No</button>
            <button type="button" class="seg-btn active-amber" id="statsChipUn" onclick="App.statsToggleStatus('unassigned')">Unassigned</button>
          </div>

          <div class="scope-note" style="margin-top:10px;">Clusters</div>
          <div id="statsClusterChips" class="cluster-chip-row"></div>

          <div id="statsDisplay" style="margin-top:12px;"></div>
        </div>

        <div class="dash-card" style="margin-bottom:12px;">
          <div class="dash-card-title">Ingest JSON</div>
          <div class="dash-card-desc">Paste JSON or upload a file. Accepts clustered or flat shape.</div>
          <textarea id="ingestJsonInput" class="aim-textarea" style="margin-top:6px;" placeholder='[{"cluster_name":"My Cluster","cluster_description":"...","items":[{"title":"Idea","description":"..."}]}]'></textarea>
          <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:6px;">
            <button class="idea-btn" style="border-color:var(--teal); color:var(--teal);" onclick="App.submitIngest()">Ingest Pasted</button>
            <button class="idea-btn" onclick="document.getElementById('ingestFileInput').click()">Upload File</button>
            <button class="idea-btn" onclick="App.downloadInstructions()">Download Instructions</button>
            <input type="file" id="ingestFileInput" accept=".json,application/json" style="display:none;" onchange="App.handleFileUpload(this)">
          </div>
        </div>

        <div class="dash-card" style="margin-bottom:12px;">
          <div class="dash-card-title" style="color: var(--teal);">Communicate State to AI</div>
          <div class="dash-card-desc">Send a snapshot of the current idea state into the main chat. Useful after manual edits or purges so the AI's mental model matches the database.</div>

          <div class="seg-group" style="margin-top:6px;">
            <button type="button" class="seg-btn active" id="syncChipAll" onclick="App.syncClickAll()">All</button>
            <button type="button" class="seg-btn active-yes" id="syncChipYes" onclick="App.syncToggleStatus('yes')">Yes</button>
            <button type="button" class="seg-btn active-no" id="syncChipNo" onclick="App.syncToggleStatus('no')">No</button>
            <button type="button" class="seg-btn active-amber" id="syncChipUn" onclick="App.syncToggleStatus('unassigned')">Unassigned</button>
          </div>

          <div class="scope-note" style="margin-top:10px;">Clusters</div>
          <div id="syncClusterChips" class="cluster-chip-row"></div>

          <label style="display:flex; align-items:center; gap:8px; margin-top:10px; font-family:var(--mono); font-size:0.72rem; color:var(--text-dim); cursor:pointer; user-select:none;">
            <input type="checkbox" id="syncIncludeContent" style="accent-color:var(--teal); width:16px; height:16px; cursor:pointer;">
            Include idea content (titles + descriptions)
          </label>
          <button class="idea-btn" style="margin-top:10px; border-color:var(--teal); color:var(--teal);" onclick="App.syncStateToAI()"><i class="bi bi-arrow-up-right-circle"></i> Send State to AI</button>
        </div>

        <div class="dash-card" style="margin-bottom:12px;">
          <div class="dash-card-title">Export JSON</div>
          <div class="dash-card-desc">Filter by status and scope, then download.</div>
          <div class="seg-group" style="margin-top:6px;">
            <button type="button" class="seg-btn active" id="expChipAll" onclick="App.expClickAll()">All</button>
            <button type="button" class="seg-btn active-yes" id="expChipYes" onclick="App.expToggleStatus('yes')">Yes</button>
            <button type="button" class="seg-btn active-no" id="expChipNo" onclick="App.expToggleStatus('no')">No</button>
            <button type="button" class="seg-btn active-amber" id="expChipUn" onclick="App.expToggleStatus('unassigned')">Unassigned</button>
          </div>

          <div class="scope-note" style="margin-top:10px;">Clusters</div>
          <div id="exportClusterChips" class="cluster-chip-row"></div>

          <button class="idea-btn" style="margin-top:10px; border-color:var(--teal); color:var(--teal);" onclick="App.submitExport()"><i class="bi bi-download"></i> Download Export</button>
        </div>

        <div class="dash-card" style="margin-bottom:12px;">
          <div class="dash-card-title" style="color: var(--amber);">Purge Ideas</div>
          <div class="dash-card-desc">Delete ideas matching status + cluster scope in this project.</div>
          <div class="seg-group" style="margin-top:6px;">
            <button type="button" class="seg-btn" id="purChipAll" onclick="App.purClickAll()">All</button>
            <button type="button" class="seg-btn active-yes" id="purChipYes" onclick="App.purToggleStatus('yes')">Yes</button>
            <button type="button" class="seg-btn active-no" id="purChipNo" onclick="App.purToggleStatus('no')">No</button>
            <button type="button" class="seg-btn" id="purChipUn" onclick="App.purToggleStatus('unassigned')">Unassigned</button>
          </div>

          <div class="scope-note" style="margin-top:10px;">Clusters</div>
          <div id="purgeClusterChips" class="cluster-chip-row"></div>

          <button class="idea-btn" style="margin-top:10px; border-color:var(--red); color:var(--red);" onclick="App.confirmBatchDelete()"><i class="bi bi-trash3"></i> Purge Matching</button>
        </div>

        <div class="dash-card">
          <div class="dash-card-title" style="color: var(--red);">Full Truncate (this project)</div>
          <div class="dash-card-desc">Empty this project's tables entirely. Cannot be undone.</div>
          <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:6px;">
            <button class="idea-btn" style="border-color:var(--red); color:var(--red);" onclick="App.confirmTruncate('ideas')">Truncate Ideas</button>
            <button class="idea-btn" style="border-color:var(--red); color:var(--red);" onclick="App.confirmTruncate('clusters')">Truncate Clusters</button>
            <button class="idea-btn" style="border-color:var(--red); color:#fff; background:var(--red);" onclick="App.confirmTruncate('both')">Truncate Both</button>
          </div>
        </div>

      </div>
    </div>
  </div>

  <main class="main">
    <div class="chat-window" id="chatWindow"></div>
    <div class="input-bar">
      <div class="input-row">
        <textarea id="chatInput" class="chat-textarea" placeholder="Ask the AI to brainstorm, cluster ideas, or organize the portfolio..." rows="1"></textarea>
        <button id="btnSend" class="btn-send" onclick="App.sendMessage()"><i class="bi bi-arrow-up"></i></button>
      </div>
    </div>
  </main>
</div>

<!-- Cluster Edit/Create -->
<div class="aim-modal-overlay" id="clusterModal">
  <div class="aim-modal" style="max-width:480px;">
    <div class="aim-modal-header">
      <div class="aim-modal-title" id="clusterModalTitle">New Cluster</div>
      <button class="btn-icon" style="width:28px;height:28px;" onclick="App.closeModal('clusterModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <input type="hidden" id="clusterModalId" value="0">
    <input type="text" id="clusterModalName" class="aim-input" placeholder="Cluster Name">
    <textarea id="clusterModalDesc" class="aim-textarea" placeholder="Description..."></textarea>
    <div style="display:flex; justify-content:flex-end; gap:8px;">
      <button class="idea-btn" onclick="App.closeModal('clusterModal')">Cancel</button>
      <button class="idea-btn" style="border-color:var(--teal); color:var(--teal);" onclick="App.saveCluster()">Save</button>
    </div>
  </div>
</div>

<!-- Items List -->
<div class="aim-modal-overlay" id="itemsModal">
  <div class="aim-modal" style="max-width:800px;">
    <div class="aim-modal-header">
      <div class="aim-modal-title" id="itemsModalTitle">Items</div>
      <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap; width:100%;">
        <div class="search-wrap" style="flex:1; min-width:180px; margin-bottom:0;">
          <i class="bi bi-search search-icon"></i>
          <input type="text" id="itemsModalSearch" class="aim-input" placeholder="Filter these items — #id, name, content..." oninput="App.onItemsModalSearch(this.value)">
          <button class="search-clear" id="itemsModalSearchClear" onclick="App.clearItemsModalSearch()" title="Clear"><i class="bi bi-x-lg"></i></button>
        </div>
        <select id="itemsModalFilter" class="aim-select" style="width:auto; padding:4px 8px; font-size:0.72rem;" onchange="App.renderItemsList()">
          <option value="all">All</option>
          <option value="yes">Yes</option>
          <option value="no">No</option>
          <option value="unassigned">Unanswered</option>
        </select>
        <button class="idea-btn" style="border-color:var(--green); color:var(--green);" onclick="App.createNewItem()" title="New Item"><i class="bi bi-plus-lg"></i></button>
        <button class="idea-btn" style="border-color:var(--teal); color:var(--teal);" onclick="App.openQuestionnaireFromModal()" title="Run Questionnaire"><i class="bi bi-play-fill"></i> Questionnaire</button>
        <button class="btn-icon" style="width:28px;height:28px;" onclick="App.closeModal('itemsModal')"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <div id="itemsModalList" style="display:flex; flex-direction:column; gap:6px; overflow-y:auto; max-height: 70vh;"></div>
  </div>
</div>

<!-- Questionnaire -->
<div class="aim-modal-overlay" id="questModal">
  <div class="aim-modal" style="max-width:640px;">
    <div class="aim-modal-header">
      <div>
        <span class="q-progress" id="qProgress">Item 1 of X</span>
        <span class="q-cluster-badge" id="qClusterBadge"></span>
      </div>
      <button class="btn-icon" style="width:28px;height:28px;" onclick="App.closeModal('questModal')"><i class="bi bi-x-lg"></i></button>
    </div>

    <h2 class="q-title aim-editable" id="qTitle" contenteditable="true"
        onkeydown="App.onTitleKeydown(event, this)"
        onblur="App.saveQField('title')"></h2>
    <div class="q-desc aim-editable" id="qDesc" contenteditable="true" data-placeholder="Add description..."
        onblur="App.saveQField('description')"></div>

    <select id="qClusterSelect" class="aim-select" onchange="App.qAssignCluster(this.value)"></select>

    <div class="q-actions">
      <button class="idea-btn" id="qBtnUn" onclick="App.qSetStatus('unassigned')">Revoke</button>
      <button class="idea-btn" id="qBtnNo" style="border-color:var(--red); color:var(--red);" onclick="App.qSetStatus('no')"><i class="bi bi-x-circle"></i> NO</button>
      <button class="idea-btn" id="qBtnYes" style="border-color:var(--green); color:var(--green);" onclick="App.qSetStatus('yes')"><i class="bi bi-check-circle"></i> YES</button>
    </div>

    <div class="q-ai-toggle-wrap">
      <button class="q-ai-toggle" id="qAiToggle" onclick="App.qToggleAi()"><i class="bi bi-robot"></i> Ask AI about this idea</button>
    </div>
    <div class="q-ai-panel" id="qAiPanel">
      <div class="q-ai-log" id="qAiLog"></div>
      <div class="q-ai-input-row">
        <textarea class="q-ai-input" id="qAiInput" placeholder="e.g. make this more epic, or reconcile with idea #17..." rows="1"></textarea>
        <button class="q-ai-send" id="qAiSend" onclick="App.qSendAi()" title="Send to AI"><i class="bi bi-arrow-up"></i></button>
      </div>
    </div>

    <div class="q-nav">
      <button class="idea-btn" onclick="App.qNavigate(-1)"><i class="bi bi-chevron-left"></i> Prev</button>
      <span style="font-size:0.65rem; color:var(--text-dim);">Keys: Y=Yes, N=No, ←/→=Nav</span>
      <button class="idea-btn" onclick="App.qNavigate(1)">Next <i class="bi bi-chevron-right"></i></button>
    </div>
  </div>
</div>

<!-- KG Picker Modal (Tree & Semantic Slice) -->
<div class="aim-modal-overlay" id="kgPickerModal">
  <div class="aim-modal" style="max-width:640px; height:85vh; padding:0; gap:0; overflow:hidden;">
    <div class="aim-modal-header" style="padding:14px 16px; border-bottom:1px solid var(--border); flex-shrink:0;">
      <div class="aim-modal-title"><i class="bi bi-diagram-3"></i> Select KG Context</div>
      <button class="btn-icon" style="width:28px;height:28px;" onclick="App.closeModal('kgPickerModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    
    <div style="display:flex; flex-direction:column; flex:1; overflow:hidden; position:relative; min-height:0;">
      <div class="wr-kge-loading-bar" id="wrKgSemanticLoader"></div>

      <div style="padding:10px 16px; font-size:0.75rem; color:var(--text-dim); border-bottom:1px solid var(--border); background:var(--surface); flex-shrink:0;">
        Select nodes to fetch lore content and/or edges, injecting them cleanly into your session context.
      </div>
      
      <!-- Tabs -->
      <div class="wr-kg-tabs">
        <button class="wr-kg-tab active" id="wr-kg-tab-tree" onclick="App.kgSetTab('tree')"><i class="bi bi-diagram-3"></i> Graph Tree</button>
        <button class="wr-kg-tab" id="wr-kg-tab-semantic" onclick="App.kgSetTab('semantic')">🧠 Semantic Slice</button>
      </div>

      <div class="wr-kg-panes">
        <!-- Pane: Tree -->
        <div class="wr-kg-pane active" id="wr-kg-pane-tree">
          <div class="wr-kg-picker-header">
              <span>Select nodes &amp; folders</span>
              <button class="wr-kg-picker-select-all" onclick="App.kgPickerToggleAll()">Select all</button>
          </div>
          <div class="wr-kg-picker-tree-wrap" id="kgPickerTreeWrap">
              <div style="padding:20px;text-align:center;color:var(--text-dim);">Loading tree…</div>
          </div>
        </div>

        <!-- Pane: Semantic -->
        <div class="wr-kg-pane" id="wr-kg-pane-semantic">
          <div class="wr-kge-query-row">
            <input type="text" class="wr-kge-query-input" id="wrKgSemanticQuery" placeholder="Describe the lore or context you need..." onkeydown="if(event.key==='Enter') App.kgRunSemanticQuery()">
            <select class="wr-kge-n-select" id="wrKgSemanticN">
              <option value="10">Top 10</option>
              <option value="20" selected>Top 20</option>
              <option value="35">Top 35</option>
              <option value="50">Top 50</option>
            </select>
            <button class="idea-btn" style="border-color:var(--teal); color:var(--teal); font-weight:bold;" onclick="App.kgRunSemanticQuery()" id="wrKgSemanticBtn">Search</button>
          </div>
          <div class="wr-kge-hits-area" id="wrKgSemanticHits">
            <div class="wr-kge-hits-empty">
                <span style="font-size:2rem; opacity:0.3;">🧠</span>
                <span>Search the graph semantically</span>
                <span style="font-size:0.75rem; max-width:300px;">The AI will rank all graph nodes by relevance to your query.</span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Options -->
      <div style="padding:10px 16px; border-top:1px solid var(--border); display:flex; gap:16px; background:var(--surface); flex-shrink:0;">
          <label style="display:flex; align-items:center; gap:6px; font-size:0.75rem; cursor:pointer;">
              <input type="checkbox" id="kgOptContent" style="accent-color:var(--teal);"> Include lore content
          </label>
          <label style="display:flex; align-items:center; gap:6px; font-size:0.75rem; cursor:pointer;">
              <input type="checkbox" id="kgOptEdges" checked style="accent-color:var(--teal);"> Include edges
          </label>
      </div>
    </div>
    
    <div style="padding:12px 16px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; align-items:center; gap:8px; flex-shrink:0; background:var(--bg);">
      <span id="kgPickerCount" style="margin-right:auto; font-size:0.75rem; color:var(--text-dim);"></span>
      <button class="idea-btn" onclick="App.closeModal('kgPickerModal')">Cancel</button>
      <button class="idea-btn" style="border-color:var(--teal); color:var(--teal); font-weight:bold;" onclick="App.injectKgContext()" id="btnInjectKg">
        <i class="bi bi-box-arrow-in-down"></i> Inject Context
      </button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
window.App = (function() {
  const LS_SESSION_KEY = 'aim_last_session';
  const AIM_KG_PICKER_OPEN_KEY = 'aim_kg_picker_open_folders';

  let state = {
    sessionId: null,
    model: 'openai',
    sessions: [],
    portfolio: [],
    isRequesting: false,
    kgSelectedNodes: [],
    kgWithContent: false,
    kgWithEdges: true
  };

  let rightTab = 'dashboard';
  let dashView = 'cards';
  let dashSearchQuery = '';
  let itemsModalSearchQuery = '';
  let currentItemsClusterId = null;
  let currentItems = [];
  let qIndex = 0;
  let qAiLogs = {};
  let itemsAiOpenSet = new Set();

  let statsSelectedStatuses = ['yes','no','unassigned'];
  let selectedExportStatuses = ['yes','no','unassigned'];
  let purgeSelectedStatuses = ['yes','no'];
  let syncSelectedStatuses = ['yes','no','unassigned'];

  let clusterSelection = {
    stats:  { mode: 'all', keys: new Set() },
    export: { mode: 'all', keys: new Set() },
    purge:  { mode: 'all', keys: new Set() },
    sync:   { mode: 'all', keys: new Set() }
  };

  // KG Picker State
  let _kgActiveTab = 'tree';
  let _kgPickerRaw = [];
  let _kgPickerChecked = new Set();
  let _kgSemanticHits = [];
  let _kgSemanticSelectedIds = new Set();

  function getAllClusterKeys() {
    const keys = [];
    state.portfolio.forEach(c => {
      if (!c.id) keys.push('uncat');
      else keys.push(String(c.id));
    });
    return keys;
  }

  function isClusterKeySelected(section, key) {
    const s = clusterSelection[section];
    if (s.mode === 'all') return true;
    return s.keys.has(key);
  }

  // ── Session ID persistence ──
  function setSessionId(id) {
    state.sessionId = id || null;
    try {
      if (id) localStorage.setItem(LS_SESSION_KEY, id);
      else localStorage.removeItem(LS_SESSION_KEY);
    } catch(e) {}
    updateScopeBanner();
  }
  function getPersistedSessionId() {
    try { return localStorage.getItem(LS_SESSION_KEY); } catch(e) { return null; }
  }

  function updateScopeBanner() {
    const banner = document.getElementById('leftScopeBanner');
    const nameEl = document.getElementById('leftScopeName');
    if (!banner || !nameEl) return;
    if (state.sessionId) {
      const s = state.sessions.find(x => x.id === state.sessionId);
      nameEl.textContent = s ? (s.title || 'Untitled Project') : 'Loaded Project';
      banner.style.display = 'block';
    } else {
      banner.style.display = 'none';
    }
  }

  async function api(payload) {
    const enriched = Object.assign({}, payload);
    if (!('session_id' in enriched)) enriched.session_id = state.sessionId;
    const res = await fetch('aim_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(enriched)
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Unknown API Error');
    return data;
  }

  function applyResponse(data) {
    if (data.session_id && data.session_id !== state.sessionId) setSessionId(data.session_id);
    if (data.sessions) { state.sessions = data.sessions; renderSessions(); updateScopeBanner(); }
    if (data.portfolio) {
      state.portfolio = data.portfolio;
      renderPortfolio();
      renderDashboardCards();
      renderAllClusterChips();
      renderStatsDisplay();
    }
  }

  async function init() {
    const lastId = getPersistedSessionId();
    await loadState(lastId);

    document.getElementById('chatInput').addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 150) + 'px';
    });

    document.querySelectorAll('.aim-modal-overlay').forEach(o => {
      o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
    });

    setDashView('cards');
    updateStatsChips();
    updateExpChips();
    updatePurgeChips();
    updateSyncChips();

    document.addEventListener('keydown', e => {
      if (!document.getElementById('questModal').classList.contains('open')) return;
      const t = e.target;
      if (t && (t.isContentEditable || t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT')) return;
      if (e.key === 'y' || e.key === 'Y' || e.key === '1') { e.preventDefault(); qSetStatus('yes'); }
      else if (e.key === 'n' || e.key === 'N' || e.key === '2') { e.preventDefault(); qSetStatus('no'); }
      else if (e.key === 'ArrowRight') { e.preventDefault(); qNavigate(1); }
      else if (e.key === 'ArrowLeft') { e.preventDefault(); qNavigate(-1); }
    });

    wireBubbleCopy();
  }

  function wireBubbleCopy() {
    const chatWin = document.getElementById('chatWindow');
    if (!chatWin) return;
    let lastCopyTs = 0;
    let lastTapTime = 0;
    let touchStartX = 0, touchStartY = 0;

    function tryCopyBubble(target) {
      const bubble = target && target.closest && target.closest('.msg-bubble');
      if (!bubble) return;
      const now = Date.now();
      if (now - lastCopyTs < 500) return;
      lastCopyTs = now;
      const text = (bubble.innerText || bubble.textContent || '').trim();
      if (!text) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text)
          .then(() => toast('Copied to clipboard', 'success', 1500))
          .catch(() => toast('Copy failed', 'error'));
      } else {
        toast('Copy not supported', 'error');
      }
    }

    chatWin.addEventListener('dblclick', e => tryCopyBubble(e.target));

    chatWin.addEventListener('touchstart', e => {
      if (!e.touches || !e.touches[0]) return;
      touchStartX = e.touches[0].clientX;
      touchStartY = e.touches[0].clientY;
    }, { passive: true });

    chatWin.addEventListener('touchend', e => {
      const t = e.changedTouches && e.changedTouches[0];
      if (!t) return;
      const dx = Math.abs(t.clientX - touchStartX);
      const dy = Math.abs(t.clientY - touchStartY);
      if (dx > 10 || dy > 10) return;
      const now = Date.now();
      if (now - lastTapTime < 400) {
        tryCopyBubble(t.target);
      }
      lastTapTime = now;
    }, { passive: true });
  }

  function renderModelSelect(catalog, selectedModel) {
    const sel = document.getElementById('setModel');
    if (!sel || !catalog) return;
    sel.innerHTML = '';
    
    for (const [groupName, models] of Object.entries(catalog)) {
      if (!Array.isArray(models) || !models.length) continue;
      const grp = document.createElement('optgroup');
      grp.label = groupName;
      models.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.name || m.id;
        if (m.id === selectedModel) opt.selected = true;
        grp.appendChild(opt);
      });
      sel.appendChild(grp);
    }

    if (selectedModel) {
      sel.value = selectedModel;
    }
  }

  async function loadState(sessionId = null) {
    try {
      const data = await api({ action: 'load_state', session_id: sessionId });
      setSessionId(data.session_id);
      state.sessions = data.sessions;
      state.portfolio = data.portfolio;

      if (data.model_catalog) {
        renderModelSelect(data.model_catalog, data.settings ? data.settings.model : state.model);
      }

      if (data.settings) {
        state.model = data.settings.model || 'openai';
        document.getElementById('setLore').value = data.settings.lore || '';
        document.getElementById('setModel').value = state.model;
        state.kgSelectedNodes = data.settings.kg_selected_nodes || [];
      }

      renderSessions();
      renderPortfolio();
      renderDashboardCards();
      renderAllClusterChips();
      renderStatsDisplay();
      renderChat(data.chat);
      buildQuestionnaireLogsFromChat(data.chat);
      updateScopeBanner();
    } catch (e) {
      toast(e.message, 'error');
    }
  }

  function renderSessions() {
    const list = document.getElementById('sessionsList');
    if (!state.sessions.length) {
      list.innerHTML = '<div style="color:var(--text-dim);font-size:0.75rem;">No projects yet. Start typing to create one.</div>';
      return;
    }
    list.innerHTML = state.sessions.map(s => `
      <div class="list-item ${s.id === state.sessionId ? 'active' : ''}" onclick="App.switchSession('${s.id}')">
        <div class="item-meta">${new Date(s.updated_at).toLocaleDateString()}</div>
        <div class="item-title">${escapeHtml(s.title || 'Untitled Project')}</div>
        <button class="btn-rename" onclick="event.stopPropagation(); App.renameSession('${s.id}')" title="Rename"><i class="bi bi-pencil"></i></button>
        <button class="btn-del" onclick="App.deleteSession(event, '${s.id}')" title="Delete"><i class="bi bi-trash"></i></button>
      </div>
    `).join('');
  }

  // ══════════════════════════════════════════════════════════════════════
  // LIVE SEARCH (Dashboard)
  // ══════════════════════════════════════════════════════════════════════

  function highlightMatches(text) {
    if (text === null || text === undefined) return '';
    const s = String(text);
    if (!dashSearchQuery) return escapeHtml(s);
    const q = dashSearchQuery;
    const lower = s.toLowerCase();
    let result = '';
    let cursor = 0;
    let idx;
    while ((idx = lower.indexOf(q, cursor)) !== -1) {
      result += escapeHtml(s.slice(cursor, idx));
      result += '<span class="hl">' + escapeHtml(s.slice(idx, idx + q.length)) + '</span>';
      cursor = idx + q.length;
    }
    result += escapeHtml(s.slice(cursor));
    return result;
  }

  function ideaMatchesSearch(idea) {
    if (!dashSearchQuery) return true;
    const q = dashSearchQuery;
    const qNoHash = q.startsWith('#') ? q.slice(1) : q;

    if (qNoHash.length && String(idea.id).includes(qNoHash)) return true;
    if ((idea.title || '').toLowerCase().includes(q)) return true;
    if ((idea.description || '').toLowerCase().includes(q)) return true;
    return false;
  }

  function getFilteredPortfolio() {
    if (!dashSearchQuery) return state.portfolio;
    const out = [];
    state.portfolio.forEach(c => {
      const matches = (c.ideas || []).filter(ideaMatchesSearch);
      if (matches.length) {
        out.push(Object.assign({}, c, { ideas: matches }));
      }
    });
    return out;
  }

  function onDashSearch(q) {
    dashSearchQuery = (q || '').trim().toLowerCase();
    const clearBtn = document.getElementById('dashSearchClear');
    if (clearBtn) clearBtn.style.display = dashSearchQuery ? 'flex' : 'none';
    updateSearchMeta();
    renderDashboardCards();
    renderPortfolio();
  }

  function clearDashSearch() {
    const inp = document.getElementById('dashSearch');
    if (inp) inp.value = '';
    onDashSearch('');
  }

  function updateSearchMeta() {
    const meta = document.getElementById('dashSearchMeta');
    if (!meta) return;
    if (!dashSearchQuery) { meta.style.display = 'none'; meta.innerHTML = ''; return; }
    const count = getFilteredPortfolio().reduce((sum, c) => sum + (c.ideas || []).length, 0);
    meta.style.display = 'block';
    meta.innerHTML = `<strong>${count}</strong> match${count === 1 ? '' : 'es'} for "${escapeHtml(dashSearchQuery)}"`;
  }

  function renderDashboardCards() {
    const grid = document.getElementById('dashboardCards');
    if (!grid) return;

    if (!state.sessionId) {
      grid.innerHTML = '<div style="grid-column:1/-1; color:var(--text-dim); font-size:0.8rem; text-align:center; padding:24px; line-height:1.6;">No project loaded.<br>Start a new chat, or select an existing project from the sidebar.</div>';
      return;
    }

    const portfolio = getFilteredPortfolio();

    if (dashSearchQuery && !portfolio.length) {
      grid.innerHTML = `<div style="grid-column:1/-1; color:var(--text-dim); font-size:0.8rem; text-align:center; padding:24px;">No ideas match "<strong style="color:var(--amber);">${escapeHtml(dashSearchQuery)}</strong>".</div>`;
      return;
    }

    let html = '';
    portfolio.forEach(c => {
      const ideas = c.ideas || [];
      const unanswered = ideas.filter(i => i.status === 'unassigned').length;

      if (!c.id) {
        html += `
          <div class="dash-card" style="border-color: var(--amber);">
            <div class="dash-card-title" style="color: var(--amber);">📌 Uncategorized</div>
            <div class="dash-card-desc">Items without a cluster.</div>
            <div class="dash-card-footer">
              <span class="dash-card-count">${ideas.length} ${dashSearchQuery ? 'match' + (ideas.length === 1 ? '' : 'es') : 'items'} · ${unanswered} unanswered</span>
              <button class="idea-btn" onclick="App.openItemsModal('uncat')">Manage</button>
            </div>
          </div>
        `;
      } else {
        html += `
          <div class="dash-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:6px;">
              <div class="dash-card-title">${highlightMatches(c.name)}</div>
              <button class="idea-btn" onclick="App.openClusterModal(${c.id})" title="Edit Cluster"><i class="bi bi-pencil"></i></button>
            </div>
            <div class="dash-card-desc">${escapeHtml(c.description || 'No description.')}</div>
            <div class="dash-card-footer">
              <span class="dash-card-count">${ideas.length} ${dashSearchQuery ? 'match' + (ideas.length === 1 ? '' : 'es') : 'items'} · ${unanswered} unanswered</span>
              <button class="idea-btn" onclick="App.openItemsModal(${c.id})">Manage</button>
            </div>
          </div>
        `;
      }
    });

    grid.innerHTML = html || '<div style="grid-column:1/-1; color:var(--text-dim);font-size:0.75rem;text-align:center;padding:20px;">No clusters yet in this project. Create one or ask the AI.</div>';
  }

  function renderPortfolio() {
    const view = document.getElementById('portfolioView');
    if (!state.sessionId) {
      view.innerHTML = '<div style="color:var(--text-dim);font-size:0.75rem;text-align:center;margin-top:20px;">No project loaded.</div>';
      return;
    }

    const portfolio = getFilteredPortfolio();

    if (dashSearchQuery && !portfolio.length) {
      view.innerHTML = `<div style="color:var(--text-dim);font-size:0.8rem;text-align:center;margin-top:20px;">No ideas match "<strong style="color:var(--amber);">${escapeHtml(dashSearchQuery)}</strong>".</div>`;
      return;
    }

    if (!portfolio.length) {
      view.innerHTML = '<div style="color:var(--text-dim);font-size:0.75rem;text-align:center;margin-top:20px;">This project is empty.<br>Ask the AI to create a cluster.</div>';
      return;
    }

    let html = '';
    portfolio.forEach(c => {
      const cNameHtml = c.id ? highlightMatches(c.name) : escapeHtml(c.name);
      html += `<div class="cluster-block">
        <div class="cluster-head"><i class="bi bi-layers"></i> ${cNameHtml} ${c.id ? `(ID: ${c.id})` : ''}</div>
        <div style="font-size:0.65rem; color:var(--text-dim); margin-bottom:8px;">${escapeHtml(c.description)}</div>
      `;
      if (!c.ideas || !c.ideas.length) {
        html += `<div style="font-size:0.65rem; color:var(--text-dim); font-style:italic;">No ideas yet.</div>`;
      } else {
        c.ideas.forEach(i => {
          html += `
            <div class="idea-card">
              <div class="idea-card-header">
                <div class="idea-title">#${i.id} ${highlightMatches(i.title)}</div>
                <div class="idea-status status-${i.status}">${i.status}</div>
              </div>
              <div class="idea-desc">${highlightMatches(i.description)}</div>
              <div class="idea-actions">
                <button class="idea-btn" onclick="App.injectCommand('Please update idea #${i.id} to status YES')">Approve</button>
                <button class="idea-btn" onclick="App.injectCommand('Please rewrite the description for idea #${i.id} to be more epic.')">Rewrite</button>
                <button class="idea-btn" onclick="App.injectCommand('Delete idea #${i.id}')"><i class="bi bi-trash"></i></button>
              </div>
            </div>
          `;
        });
      }
      html += `</div>`;
    });
    view.innerHTML = html;
  }

  function renderChat(messages) {
    const win = document.getElementById('chatWindow');
    win.innerHTML = '';
    if (!messages || !messages.length) {
      win.innerHTML = `
        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-dim); text-align:center;">
          <div style="font-size:40px; margin-bottom:10px; color:var(--teal);"><i class="bi bi-cpu"></i></div>
          <h3 style="font-family:var(--mono); color:var(--text);">AI Idea Manager</h3>
          <p style="font-size:0.8rem; max-width:400px; margin-top:10px;">Each chat is its own project — its own lore, clusters, and ideas. Start typing to begin a new one, or pick an existing project from the sidebar.</p>
        </div>
      `;
      return;
    }

    messages.forEach(m => appendMessage(m.role, m.content, m.operations_log));
    win.scrollTop = win.scrollHeight;
  }

  function appendMessage(role, content, opsLog = null) {
    const win = document.getElementById('chatWindow');
    const msg = document.createElement('div');
    msg.className = `msg msg-${role}`;

    let opsHtml = '';
    if (opsLog) {
      try {
        const ops = typeof opsLog === 'string' ? JSON.parse(opsLog) : opsLog;
        if (ops.length) {
          opsHtml = `
            <div class="ops-pill" onclick="this.classList.toggle('open')">
              <i class="bi bi-database-check"></i> Executed ${ops.length} DB Operations
              <div class="ops-details">
                ${ops.map(o => `<div>- ${escapeHtml(o)}</div>`).join('')}
              </div>
            </div>
          `;
        }
      } catch(e) {}
    }

    msg.innerHTML = `
      <div class="msg-avatar">${role === 'user' ? '<i class="bi bi-person"></i>' : '<i class="bi bi-robot"></i>'}</div>
      <div class="msg-bubble">
        ${formatAIContent(content)}
        ${opsHtml}
      </div>
    `;

    if (win.children.length === 1 && win.children[0].innerHTML.includes('AI Idea Manager')) {
        win.innerHTML = '';
    }
    win.appendChild(msg);
    win.scrollTop = win.scrollHeight;
  }

  function formatAIContent(text) {
    if(!text) return '';
    let f = escapeHtml(text).replace(/\n/g, '<br>');
    f = f.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    return f;
  }

  // ══════════════════════════════════════════════════════════════════════
  // INLINE AI LOGS
  // ══════════════════════════════════════════════════════════════════════
  function findItemInPortfolio(id) {
    for (const c of state.portfolio) {
      if (!c.ideas) continue;
      for (const i of c.ideas) {
        if (String(i.id) === String(id)) return i;
      }
    }
    return null;
  }

  function buildQuestionnaireLogsFromChat(messages) {
    qAiLogs = {};
    if (!Array.isArray(messages)) return;
    for (let i = 0; i < messages.length; i++) {
      const m = messages[i];
      if (m.role !== 'user' || !m.content) continue;
      const match = String(m.content).match(/^\[Questionnaire[^\]]*#(\d+)/);
      if (!match) continue;
      const itemId = parseInt(match[1], 10);
      if (!itemId) continue;

      let userText = '';
      const idx = String(m.content).indexOf('USER REQUEST: ');
      if (idx !== -1) userText = String(m.content).substring(idx + 'USER REQUEST: '.length).trim();
      else userText = String(m.content);

      qAiLogs[itemId] = qAiLogs[itemId] || [];
      qAiLogs[itemId].push({ role: 'user', text: userText });

      if (i + 1 < messages.length && messages[i + 1].role === 'ai') {
        let aiText = messages[i + 1].content || '';
        let opsCount = 0;
        const opsLog = messages[i + 1].operations_log;
        if (opsLog) {
          try {
            const parsed = JSON.parse(opsLog);
            if (Array.isArray(parsed)) opsCount = parsed.length;
          } catch(e) {}
        }
        if (opsCount) aiText += `\n\n— ${opsCount} DB operation${opsCount > 1 ? 's' : ''} executed`;
        qAiLogs[itemId].push({ role: 'ai', text: aiText || '(no reply)' });
      }
    }
  }

  function renderAiLogInto(el, itemId) {
    if (!el) return;
    const entries = qAiLogs[itemId] || [];
    el.innerHTML = entries.map(e => {
      if (e.role === 'pending') {
        return `<div class="q-ai-msg ai" style="display:flex; align-items:center; gap:8px;">
          <span class="typing"><span></span><span></span><span></span></span>
          <span style="font-family:var(--mono); font-size:0.7rem; color:var(--text-dim);">AI is thinking…</span>
        </div>`;
      }
      const cls = e.role === 'user' ? 'q-ai-msg user' : (e.isError ? 'q-ai-msg err' : 'q-ai-msg ai');
      return `<div class="${cls}">${escapeHtml(e.text).replace(/\n/g,'<br>')}</div>`;
    }).join('');
    el.scrollTop = el.scrollHeight;
  }

  function refreshLogsForItem(itemId) {
    const has = !!(qAiLogs[itemId] && qAiLogs[itemId].length);

    if (currentItems[qIndex] && String(currentItems[qIndex].id) === String(itemId)) {
      const qLog = document.getElementById('qAiLog');
      if (qLog) renderAiLogInto(qLog, itemId);
      const toggle = document.getElementById('qAiToggle');
      if (toggle) toggle.classList.toggle('ai-has-log', has);
    }

    const itemLog = document.getElementById('item-ai-log-' + itemId);
    if (itemLog) renderAiLogInto(itemLog, itemId);

    const aiBtn = document.querySelector(`button[data-ai-btn="${itemId}"]`);
    if (aiBtn) aiBtn.classList.toggle('ai-has-log', has);
  }

  async function sendAiForItem(item, text) {
    if (!item || !item.id) throw new Error('Missing item');
    qAiLogs[item.id] = qAiLogs[item.id] || [];
    qAiLogs[item.id].push({ role: 'user', text });
    qAiLogs[item.id].push({ role: 'pending' });
    refreshLogsForItem(item.id);

    try {
      const scoped = `[Questionnaire — regarding idea #${item.id}: "${item.title}"]\n${item.description || '(no description)'}\n\nUSER REQUEST: ${text}`;
      const data = await api({ action: 'send_message', session_id: state.sessionId, message: scoped });

      applyResponse(data);

      const replyText = data.ai_text || '(no reply)';
      const opsCount = data.operations_log ? JSON.parse(data.operations_log).length : 0;
      const suffix = opsCount ? `\n\n— ${opsCount} DB operation${opsCount > 1 ? 's' : ''} executed` : '';

      qAiLogs[item.id] = qAiLogs[item.id].filter(e => e.role !== 'pending');
      qAiLogs[item.id].push({ role: 'ai', text: replyText + suffix });
      refreshLogsForItem(item.id);

      refreshCurrentQItemFromPortfolio();
      refreshItemRowInItemsModal(item.id);
    } catch (e) {
      qAiLogs[item.id] = qAiLogs[item.id].filter(e => e.role !== 'pending');
      qAiLogs[item.id].push({ role: 'ai', text: 'Error: ' + e.message, isError: true });
      refreshLogsForItem(item.id);
      throw e;
    }
  }

  function refreshItemRowInItemsModal(id) {
    const item = findItemInPortfolio(id);
    if (!item) return;
    const row = document.getElementById('aim-item-' + id);
    if (!row) return;

    const titleEl = row.querySelector('.aim-item-title');
    if (titleEl && titleEl.textContent !== (item.title || '')) {
      titleEl.textContent = item.title || '';
      titleEl.dataset.orig = item.title || '';
    }
    const descEl = row.querySelector('.aim-item-desc');
    if (descEl) {
      const newDesc = item.description || '';
      if (descEl.textContent !== newDesc) {
        descEl.textContent = newDesc;
        descEl.dataset.orig = newDesc;
      }
    }
    const sel = row.querySelector('.aim-item-actions select');
    if (sel && sel.value !== String(item.cluster_id || '')) {
      sel.value = item.cluster_id || '';
    }
    const statusBtns = row.querySelectorAll('button[data-status-btn]');
    statusBtns.forEach(b => {
      const s = b.getAttribute('data-status-btn');
      b.classList.toggle('active-yes', s === 'yes' && item.status === 'yes');
      b.classList.toggle('active-no', s === 'no' && item.status === 'no');
      b.classList.toggle('active-un', s === 'unassigned' && item.status === 'unassigned');
    });

    const aiBtn = row.querySelector('button[data-ai-btn]');
    if (aiBtn) {
      const has = qAiLogs[id] && qAiLogs[id].length > 0;
      aiBtn.classList.toggle('ai-has-log', !!has);
    }
  }

  async function sendMessage() {
    if (state.isRequesting) return;
    const input = document.getElementById('chatInput');
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    input.style.height = 'auto';
    await sendRawMessage(text);
  }

  async function sendRawMessage(text) {
    if (state.isRequesting) return;
    if (!text) return;
    appendMessage('user', text);

    const win = document.getElementById('chatWindow');
    const typing = document.createElement('div');
    typing.className = 'msg msg-ai';
    typing.id = 'typingIndicator';
    typing.innerHTML = `<div class="msg-avatar"><i class="bi bi-robot"></i></div><div class="msg-bubble"><div class="typing"><span></span><span></span><span></span></div></div>`;
    win.appendChild(typing);
    win.scrollTop = win.scrollHeight;

    state.isRequesting = true;
    document.getElementById('btnSend').disabled = true;

    try {
      const data = await api({ action: 'send_message', session_id: state.sessionId, message: text });
      document.getElementById('typingIndicator').remove();
      applyResponse(data);
      appendMessage('ai', data.ai_text, data.operations_log);
    } catch(e) {
      document.getElementById('typingIndicator').remove();
      toast(e.message, 'error');
    } finally {
      state.isRequesting = false;
      document.getElementById('btnSend').disabled = false;
    }
  }

  async function saveSettings() {
    const lore = document.getElementById('setLore').value.trim();
    const model = document.getElementById('setModel').value;
    try {
      const data = await api({ action: 'save_settings', lore, model, kg_selected_nodes: state.kgSelectedNodes });
      state.model = model;
      applyResponse(data);
      toast('Configuration saved.', 'success');
    } catch(e) {
      toast(e.message, 'error');
    }
  }

  function switchSession(id) {
    if(id === state.sessionId) return;
    closeFlyouts();
    loadState(id);
  }

  async function renameSession(id) {
    const s = state.sessions.find(x => x.id === id);
    const currentTitle = s ? (s.title || '') : '';
    const newTitle = prompt('Rename project:', currentTitle);
    if (!newTitle || newTitle === currentTitle) return;
    try {
      const data = await api({ action: 'rename_session', session_id: id, title: newTitle });
      state.sessions = data.sessions;
      renderSessions();
      updateScopeBanner();
      toast('Renamed', 'success');
    } catch(e) { toast(e.message, 'error'); }
  }

  async function deleteSession(e, id) {
    e.stopPropagation();
    if(!confirm('Delete this project? All its chats, clusters and ideas will be permanently removed.')) return;
    try {
      const data = await api({ action: 'delete_session', session_id: id });
      state.sessions = data.sessions;
      if (state.sessionId === id) {
        setSessionId(null);
        state.portfolio = [];
        state.kgSelectedNodes = [];
        document.getElementById('setLore').value = '';
        renderPortfolio();
        renderDashboardCards();
        renderAllClusterChips();
        renderStatsDisplay();
        renderChat([]);
        qAiLogs = {};
      }
      renderSessions();
      updateScopeBanner();
      toast('Project deleted.', 'success');
    } catch(e) { toast(e.message, 'error'); }
  }

  function newSession() {
    setSessionId(null);
    state.portfolio = [];
    state.kgSelectedNodes = [];
    _kgPickerChecked.clear();
    _kgSemanticSelectedIds.clear();
    _kgSemanticHits = [];
    qAiLogs = {};
    itemsAiOpenSet.clear();
    clearDashSearch();
    clearItemsModalSearch();
    document.getElementById('setLore').value = '';
    renderPortfolio();
    renderDashboardCards();
    renderAllClusterChips();
    renderStatsDisplay();
    renderChat([]);
    renderSessions();
    closeFlyouts();
  }

  // ══════════════════════════════════════════════════════════════════════
  // RIGHT FLYOUT
  // ══════════════════════════════════════════════════════════════════════
  function setRightTab(tab) {
    rightTab = tab;
    document.querySelectorAll('#flyoutRight .flyout-tab').forEach(t => t.classList.toggle('active', t.dataset.aimTab === tab));
    document.querySelectorAll('#flyoutRight .flyout-tab-panel').forEach(p => p.classList.toggle('active', p.id === 'aim-panel-' + tab));
  }

  function setDashView(view) {
    dashView = view;
    const cards = document.getElementById('dashboardCards');
    const detailed = document.getElementById('portfolioView');
    const btnCards = document.getElementById('dashViewCards');
    const btnDetailed = document.getElementById('dashViewDetailed');
    if (!cards || !detailed) return;

    cards.style.display = view === 'cards' ? 'grid' : 'none';
    detailed.style.display = view === 'detailed' ? 'block' : 'none';

    if (btnCards) {
      btnCards.style.borderColor = view === 'cards' ? 'var(--teal)' : '';
      btnCards.style.color = view === 'cards' ? 'var(--teal)' : '';
    }
    if (btnDetailed) {
      btnDetailed.style.borderColor = view === 'detailed' ? 'var(--teal)' : '';
      btnDetailed.style.color = view === 'detailed' ? 'var(--teal)' : '';
    }
  }

  // ══════════════════════════════════════════════════════════════════════
  // CLUSTER MULTI-SELECT CHIPS
  // ══════════════════════════════════════════════════════════════════════
  function renderAllClusterChips() {
    ['stats','export','purge','sync'].forEach(renderClusterChips);
  }

  function renderClusterChips(section) {
    const container = document.getElementById(section + 'ClusterChips');
    if (!container) return;

    const allSelected = clusterSelection[section].mode === 'all';
    let html = `<button type="button" class="cluster-chip${allSelected ? ' selected' : ''}" onclick="App.clusterChipAll('${section}')">All</button>`;

    state.portfolio.forEach(c => {
      const key = c.id ? String(c.id) : 'uncat';
      const label = c.id ? c.name : '📌 Uncategorized';
      const isSelected = isClusterKeySelected(section, key);
      const cls = `cluster-chip${isSelected ? ' selected' : ''}${!c.id ? ' uncat' : ''}`;
      html += `<button type="button" class="${cls}" onclick="App.clusterChipToggle('${section}', '${escapeAttr(key)}')">${escapeHtml(label)}</button>`;
    });

    container.innerHTML = html;
  }

  function clusterChipAll(section) {
    clusterSelection[section].mode = 'all';
    clusterSelection[section].keys = new Set();
    renderClusterChips(section);
    if (section === 'stats') renderStatsDisplay();
  }

  function clusterChipToggle(section, key) {
    const s = clusterSelection[section];
    const allKeys = getAllClusterKeys();

    if (s.mode === 'all') {
      s.mode = 'some';
      s.keys = new Set([key]);
    } else {
      if (s.keys.has(key)) {
        s.keys.delete(key);
        if (s.keys.size === 0) { s.mode = 'all'; s.keys = new Set(); }
      } else {
        s.keys.add(key);
        if (s.keys.size >= allKeys.length) { s.mode = 'all'; s.keys = new Set(); }
      }
    }

    renderClusterChips(section);
    if (section === 'stats') renderStatsDisplay();
  }

  function getSelectedClusterKeys(section) {
    const s = clusterSelection[section];
    if (s.mode === 'all') return ['all'];
    return Array.from(s.keys);
  }

  // ══════════════════════════════════════════════════════════════════════
  // STATS
  // ══════════════════════════════════════════════════════════════════════
  function statsClickAll() {
    statsSelectedStatuses = ['yes','no','unassigned'];
    updateStatsChips();
    renderStatsDisplay();
  }

  function statsToggleStatus(status) {
    const isAll = statsSelectedStatuses.length === 3;
    if (isAll) {
      statsSelectedStatuses = [status];
    } else {
      const idx = statsSelectedStatuses.indexOf(status);
      if (idx >= 0) {
        if (statsSelectedStatuses.length > 1) statsSelectedStatuses.splice(idx, 1);
        else statsSelectedStatuses = ['yes','no','unassigned'];
      } else {
        statsSelectedStatuses.push(status);
      }
    }
    updateStatsChips();
    renderStatsDisplay();
  }

  function updateStatsChips() {
    const hasYes = statsSelectedStatuses.includes('yes');
    const hasNo = statsSelectedStatuses.includes('no');
    const hasUn = statsSelectedStatuses.includes('unassigned');
    const isAll = hasYes && hasNo && hasUn;
    const set = (id, cls) => { const el = document.getElementById(id); if (el) el.className = 'seg-btn' + (cls ? ' ' + cls : ''); };
    set('statsChipAll', isAll ? 'active' : '');
    set('statsChipYes', hasYes ? 'active-yes' : '');
    set('statsChipNo', hasNo ? 'active-no' : '');
    set('statsChipUn', hasUn ? 'active-amber' : '');
  }

  function renderStatsDisplay() {
    const el = document.getElementById('statsDisplay');
    if (!el) return;

    if (!state.sessionId) {
      el.innerHTML = '<div style="font-family:var(--mono); font-size:0.72rem; color:var(--text-dim); text-align:center; padding:8px;">No project loaded.</div>';
      return;
    }

    const orderedStatuses = ['yes','no','unassigned'].filter(s => statsSelectedStatuses.includes(s));
    const selectedClusterKeys = clusterSelection.stats.mode === 'all' ? null : clusterSelection.stats.keys;

    const perCluster = [];
    let grandTotal = 0;
    const grandByStatus = { yes: 0, no: 0, unassigned: 0 };

    state.portfolio.forEach(c => {
      const key = c.id ? String(c.id) : 'uncat';
      if (selectedClusterKeys && !selectedClusterKeys.has(key)) return;

      const matching = (c.ideas || []).filter(i => orderedStatuses.includes(i.status));
      if (!matching.length) return;

      const byStatus = { yes: [], no: [], unassigned: [] };
      matching.forEach(i => { byStatus[i.status].push(i); });

      perCluster.push({
        key,
        name: c.id ? c.name : '📌 Uncategorized',
        count: matching.length,
        byStatus
      });

      grandTotal += matching.length;
      orderedStatuses.forEach(s => { grandByStatus[s] += byStatus[s].length; });
    });

    if (grandTotal === 0) {
      el.innerHTML = '<div style="font-family:var(--mono); font-size:0.72rem; color:var(--text-dim); text-align:center; padding:8px;">No ideas match the current filter.</div>';
      return;
    }

    let html = '';
    html += `<div class="stats-total">${grandTotal}<small>idea${grandTotal === 1 ? '' : 's'}</small></div>`;

    html += '<div class="stats-section-label">By Status</div>';
    orderedStatuses.forEach(s => {
      const label = s === 'unassigned' ? 'UNASSIGNED' : s.toUpperCase();
      const color = s === 'yes' ? 'var(--green)' : (s === 'no' ? 'var(--red)' : 'var(--text-dim)');
      html += `<div class="stats-row"><span class="stats-key" style="color:${color};">${label}</span><span class="stats-val">${grandByStatus[s]}</span></div>`;
    });

    html += '<div class="stats-section-label">By Cluster</div>';
    perCluster.forEach(pc => {
      const parts = [];
      orderedStatuses.forEach(s => {
        if (pc.byStatus[s].length) parts.push(`${s === 'unassigned' ? 'Un' : s[0].toUpperCase()}:${pc.byStatus[s].length}`);
      });
      html += `<div class="stats-row">
        <span class="stats-key">${escapeHtml(pc.name)} <span class="stats-sub">(${parts.join(', ')})</span></span>
        <span class="stats-val">${pc.count}</span>
      </div>`;
    });

    el.innerHTML = html;
  }

  // ══════════════════════════════════════════════════════════════════════
  // MODALS
  // ══════════════════════════════════════════════════════════════════════
  function openModal(id) { document.getElementById(id).classList.add('open'); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }

  function openClusterModal(id) {
    document.getElementById('clusterModalId').value = id || 0;
    if (id > 0) {
      const c = state.portfolio.find(x => String(x.id) === String(id));
      document.getElementById('clusterModalTitle').textContent = 'Edit Cluster';
      document.getElementById('clusterModalName').value = c ? c.name : '';
      document.getElementById('clusterModalDesc').value = c ? (c.description || '') : '';
    } else {
      document.getElementById('clusterModalTitle').textContent = 'New Cluster';
      document.getElementById('clusterModalName').value = '';
      document.getElementById('clusterModalDesc').value = '';
    }
    openModal('clusterModal');
    setTimeout(() => document.getElementById('clusterModalName').focus(), 100);
  }

  async function saveCluster() {
    const id = parseInt(document.getElementById('clusterModalId').value) || 0;
    const name = document.getElementById('clusterModalName').value.trim();
    const description = document.getElementById('clusterModalDesc').value.trim();
    if (!name) { toast('Name required', 'error'); return; }
    try {
      const data = await api({ action: 'save_cluster', id, name, description });
      applyResponse(data);
      closeModal('clusterModal');
      toast('Cluster saved', 'success');
    } catch(e) { toast(e.message, 'error'); }
  }

  function openItemsModal(clusterKey) {
    currentItemsClusterId = clusterKey;
    itemsAiOpenSet.clear();

    itemsModalSearchQuery = dashSearchQuery;
    const searchInp = document.getElementById('itemsModalSearch');
    if (searchInp) searchInp.value = dashSearchQuery;
    const searchClear = document.getElementById('itemsModalSearchClear');
    if (searchClear) searchClear.style.display = dashSearchQuery ? 'flex' : 'none';

    let title = 'Items';
    if (clusterKey === 'uncat') title = '📌 Uncategorized';
    else if (clusterKey === 'all') title = 'All Items';
    else {
      const c = state.portfolio.find(x => String(x.id) === String(clusterKey));
      if (c) title = c.name;
    }
    document.getElementById('itemsModalTitle').textContent = title;
    document.getElementById('itemsModalFilter').value = 'all';
    openModal('itemsModal');
    renderItemsList();
  }

  function onItemsModalSearch(q) {
    itemsModalSearchQuery = (q || '').trim().toLowerCase();
    const clearBtn = document.getElementById('itemsModalSearchClear');
    if (clearBtn) clearBtn.style.display = itemsModalSearchQuery ? 'flex' : 'none';
    renderItemsList();
  }

  function clearItemsModalSearch() {
    const inp = document.getElementById('itemsModalSearch');
    if (inp) inp.value = '';
    onItemsModalSearch('');
  }

  function itemMatchesModalSearch(item) {
    if (!itemsModalSearchQuery) return true;
    const q = itemsModalSearchQuery;
    const qNoHash = q.startsWith('#') ? q.slice(1) : q;

    if (qNoHash.length && String(item.id).includes(qNoHash)) return true;
    if ((item.title || '').toLowerCase().includes(q)) return true;
    if ((item.description || '').toLowerCase().includes(q)) return true;
    return false;
  }

  function highlightItemsModalMatches(text) {
    if (text === null || text === undefined) return '';
    const s = String(text);
    if (!itemsModalSearchQuery) return escapeHtml(s);
    const q = itemsModalSearchQuery;
    const lower = s.toLowerCase();
    let result = '';
    let cursor = 0;
    let idx;
    while ((idx = lower.indexOf(q, cursor)) !== -1) {
      result += escapeHtml(s.slice(cursor, idx));
      result += '<span class="hl">' + escapeHtml(s.slice(idx, idx + q.length)) + '</span>';
      cursor = idx + q.length;
    }
    result += escapeHtml(s.slice(cursor));
    return result;
  }

  function renderItemsList(focusId = null) {
    const filter = document.getElementById('itemsModalFilter').value;
    const list = document.getElementById('itemsModalList');

    let items = [];
    if (currentItemsClusterId === 'all') {
      state.portfolio.forEach(c => {
        (c.ideas || []).forEach(i => items.push(Object.assign({}, i, { cluster_name: c.name, cluster_id: c.id })));
      });
    } else if (currentItemsClusterId === 'uncat' || currentItemsClusterId === null) {
      const uncat = state.portfolio.find(x => !x.id);
      items = uncat ? (uncat.ideas || []).map(i => Object.assign({}, i, { cluster_name: null, cluster_id: null })) : [];
    } else {
      const c = state.portfolio.find(x => String(x.id) === String(currentItemsClusterId));
      items = c ? (c.ideas || []).map(i => Object.assign({}, i, { cluster_name: c.name, cluster_id: c.id })) : [];
    }

    if (filter !== 'all') items = items.filter(i => i.status === filter);
    if (itemsModalSearchQuery) items = items.filter(itemMatchesModalSearch);
    currentItems = items;

    if (!items.length) {
      const msg = itemsModalSearchQuery
        ? `No items match "<strong style="color:var(--amber);">${escapeHtml(itemsModalSearchQuery)}</strong>".`
        : 'No items found.';
      list.innerHTML = `<div style="padding:20px; text-align:center; color:var(--text-dim); font-size:0.75rem;">${msg}</div>`;
      return;
    }

    let clusterOpts = '<option value="">-- Unassigned --</option>';
    state.portfolio.forEach(c => {
      if (!c.id) return;
      clusterOpts += `<option value="${c.id}">${escapeHtml(c.name)}</option>`;
    });

    list.innerHTML = items.map(item => {
      const selectedOpts = clusterOpts.replace(`value="${item.cluster_id || ''}"`, `value="${item.cluster_id || ''}" selected`);
      const isOpen = itemsAiOpenSet.has(item.id) ? ' open' : '';
      const hasLog = (qAiLogs[item.id] && qAiLogs[item.id].length) ? ' ai-has-log' : '';
      return `
        <div class="aim-item-row" id="aim-item-${item.id}">
          <div style="display:flex; align-items:flex-start; gap:6px; min-width:0;">
            <span style="font-family:var(--mono); font-size:0.7rem; color:var(--teal); flex-shrink:0; padding-top:2px;">#${item.id}</span>
            <div class="aim-item-title aim-editable" contenteditable="true"
                 data-id="${item.id}" data-field="title" data-orig="${escapeAttr(item.title)}"
                 onkeydown="App.onTitleKeydown(event, this)"
                 onblur="App.saveItemField(this)"
                 style="flex:1;">${highlightItemsModalMatches(item.title)}</div>
          </div>
          <div class="aim-item-desc aim-editable" contenteditable="true"
               data-id="${item.id}" data-field="description" data-orig="${escapeAttr(item.description || '')}"
               data-placeholder="Add description..."
               onblur="App.saveItemField(this)">${highlightItemsModalMatches(item.description || '')}</div>
          <div class="aim-item-actions">
            <button class="idea-btn ${item.status === 'yes' ? 'active-yes' : ''}" data-status-btn="yes" onclick="App.updateItemStatus(${item.id}, 'yes')">Yes</button>
            <button class="idea-btn ${item.status === 'no' ? 'active-no' : ''}" data-status-btn="no" onclick="App.updateItemStatus(${item.id}, 'no')">No</button>
            <button class="idea-btn ${item.status === 'unassigned' ? 'active-un' : ''}" data-status-btn="unassigned" onclick="App.updateItemStatus(${item.id}, 'unassigned')">Revoke</button>
            <select class="aim-select" style="width:auto; max-width:220px; margin-left:auto; padding:4px 6px; font-size:0.7rem;" onchange="App.updateItemCluster(${item.id}, this.value)">
              ${selectedOpts}
            </select>
            <button class="idea-btn${hasLog}" data-ai-btn="${item.id}" onclick="App.itemsAiToggle(${item.id})" title="Ask AI about this idea"><i class="bi bi-robot"></i> AI</button>
            <button class="idea-btn" title="Remove from cluster" onclick="App.updateItemCluster(${item.id}, '')"><i class="bi bi-box-arrow-right"></i></button>
          </div>
          <div class="q-ai-panel${isOpen}" id="item-ai-panel-${item.id}" style="margin-top:8px;">
            <div class="q-ai-log" id="item-ai-log-${item.id}"></div>
            <div class="q-ai-input-row">
              <textarea class="q-ai-input" id="item-ai-input-${item.id}" placeholder="e.g. make this more epic, or reconcile with idea #17..." rows="1"></textarea>
              <button class="q-ai-send" id="item-ai-send-${item.id}" onclick="App.itemsAiSend(${item.id})" title="Send to AI"><i class="bi bi-arrow-up"></i></button>
            </div>
          </div>
        </div>
      `;
    }).join('');

    items.forEach(item => {
      const logEl = document.getElementById('item-ai-log-' + item.id);
      if (logEl) renderAiLogInto(logEl, item.id);
    });

    if (focusId) {
      const el = document.getElementById('aim-item-' + focusId);
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const titleEl = el.querySelector('.aim-item-title');
        if (titleEl) {
          titleEl.focus();
          const range = document.createRange();
          range.selectNodeContents(titleEl);
          const sel = window.getSelection();
          sel.removeAllRanges();
          sel.addRange(range);
        }
      }
    }
  }

  function itemsAiToggle(id) {
    const panel = document.getElementById('item-ai-panel-' + id);
    if (!panel) return;
    if (itemsAiOpenSet.has(id)) {
      itemsAiOpenSet.delete(id);
      panel.classList.remove('open');
    } else {
      itemsAiOpenSet.add(id);
      panel.classList.add('open');
      const logEl = document.getElementById('item-ai-log-' + id);
      renderAiLogInto(logEl, id);
      setTimeout(() => { const inp = document.getElementById('item-ai-input-' + id); if (inp) inp.focus(); }, 100);
    }
  }

  async function itemsAiSend(id) {
    const inputEl = document.getElementById('item-ai-input-' + id);
    const sendBtn = document.getElementById('item-ai-send-' + id);
    if (!inputEl) return;
    const text = inputEl.value.trim();
    if (!text) return;

    const item = findItemInPortfolio(id);
    if (!item) { toast('Item not found', 'error'); return; }

    inputEl.value = '';
    inputEl.style.height = 'auto';
    if (sendBtn) sendBtn.disabled = true;

    try {
      await sendAiForItem(item, text);
    } catch(e) {
      toast(e.message, 'error');
    } finally {
      if (sendBtn) sendBtn.disabled = false;
    }
  }

  async function createNewItem() {
    const cId = (currentItemsClusterId === 'uncat' || currentItemsClusterId === null || currentItemsClusterId === 'all') ? '' : currentItemsClusterId;
    try {
      const data = await api({ action: 'create_item', cluster_id: cId, title: 'New Idea', description: '' });
      applyResponse(data);
      document.getElementById('itemsModalFilter').value = 'all';
      renderItemsList(data.id);
      toast('Item created', 'success');
    } catch(e) { toast(e.message, 'error'); }
  }

  async function updateItemStatus(id, status) {
    try {
      const data = await api({ action: 'update_item', id, status });
      applyResponse(data);
      if (document.getElementById('itemsModal').classList.contains('open')) {
        const it = currentItems.find(x => String(x.id) === String(id));
        if (it) it.status = status;
        refreshItemRowInItemsModal(id);
      }
    } catch(e) { toast(e.message, 'error'); }
  }

  async function updateItemCluster(id, clusterId) {
    try {
      const data = await api({ action: 'update_item', id, cluster_id: clusterId });
      applyResponse(data);
      if (document.getElementById('itemsModal').classList.contains('open')) renderItemsList();
      toast('Moved', 'success');
    } catch(e) { toast(e.message, 'error'); }
  }

  function onTitleKeydown(e, el) {
    if (e.key === 'Enter') { e.preventDefault(); el.blur(); }
  }

  async function saveItemField(el) {
    const id = el.dataset.id;
    const field = el.dataset.field;
    const orig = el.dataset.orig || '';
    const current = el.textContent.trim();

    if (current === orig) return;
    if (field === 'title' && current === '') {
      el.textContent = orig;
      toast('Title cannot be empty', 'error');
      return;
    }

    const payload = { action: 'update_item', id: parseInt(id) };
    payload[field] = current;

    try {
      const data = await api(payload);
      el.dataset.orig = current;
      applyResponse(data);
      const it = currentItems.find(x => String(x.id) === String(id));
      if (it) it[field] = current;
      toast('Saved', 'success');
    } catch(e) {
      el.textContent = orig;
      toast(e.message, 'error');
    }
  }

  // ══════════════════════════════════════════════════════════════════════
  // QUESTIONNAIRE
  // ══════════════════════════════════════════════════════════════════════
  function openQuestionnaireFromModal() {
    if (!currentItems.length) { toast('No items to review', 'error'); return; }
    qIndex = 0;
    openModal('questModal');
    renderQuestionnaire();
  }

  function openGlobalQuestionnaire() {
    let all = [];
    state.portfolio.forEach(c => {
      (c.ideas || []).forEach(i => {
        if (i.status === 'unassigned') {
          all.push(Object.assign({}, i, { cluster_name: c.id ? c.name : null, cluster_id: c.id || null }));
        }
      });
    });
    if (!all.length) { toast('All ideas are already answered! 🎉', 'success'); return; }
    currentItems = all;
    qIndex = 0;
    openModal('questModal');
    renderQuestionnaire();
  }

  function renderQuestionnaire() {
    const item = currentItems[qIndex];
    if (!item) return;

    document.getElementById('qProgress').textContent = `Item ${qIndex + 1} of ${currentItems.length}`;
    const cName = item.cluster_name || (item.cluster_id ? 'Assigned' : '📌 Uncategorized');
    document.getElementById('qClusterBadge').textContent = cName;

    const titleEl = document.getElementById('qTitle');
    titleEl.textContent = item.title || '';

    const descEl = document.getElementById('qDesc');
    descEl.textContent = item.description || '';

    const sel = document.getElementById('qClusterSelect');
    let optsHtml = '<option value="">-- Unassigned --</option>';
    state.portfolio.forEach(c => {
      if (!c.id) return;
      optsHtml += `<option value="${c.id}">${escapeHtml(c.name)}</option>`;
    });
    sel.innerHTML = optsHtml;
    sel.value = item.cluster_id || '';

    updateQStatusButtons(item.status);

    const panel = document.getElementById('qAiPanel');
    if (panel) panel.classList.remove('open');
    const toggle = document.getElementById('qAiToggle');
    if (toggle) {
      toggle.classList.remove('open');
      toggle.classList.toggle('ai-has-log', !!(qAiLogs[item.id] && qAiLogs[item.id].length));
    }
    const aiInput = document.getElementById('qAiInput');
    if (aiInput) { aiInput.value = ''; aiInput.style.height = 'auto'; }

    renderQAiLog();
  }

  function updateQStatusButtons(status) {
    const y = document.getElementById('qBtnYes');
    const n = document.getElementById('qBtnNo');
    const u = document.getElementById('qBtnUn');
    if (!y || !n || !u) return;
    y.classList.remove('active-yes');
    n.classList.remove('active-no');
    u.classList.remove('active-un');
    if (status === 'yes') y.classList.add('active-yes');
    else if (status === 'no') n.classList.add('active-no');
    else u.classList.add('active-un');
  }

  function qNavigate(delta) {
    qIndex += delta;
    if (qIndex >= currentItems.length) { qIndex = currentItems.length - 1; toast('End of list', 'info'); return; }
    if (qIndex < 0) { qIndex = 0; return; }
    renderQuestionnaire();
  }

  async function qSetStatus(status) {
    const item = currentItems[qIndex];
    if (!item) return;
    try {
      const data = await api({ action: 'update_item', id: item.id, status });
      item.status = status;
      applyResponse(data);
      updateQStatusButtons(status);
      if (status !== 'unassigned') setTimeout(() => qNavigate(1), 250);
    } catch(e) { toast(e.message, 'error'); }
  }

  async function qAssignCluster(clusterId) {
    const item = currentItems[qIndex];
    if (!item) return;
    try {
      const data = await api({ action: 'update_item', id: item.id, cluster_id: clusterId });
      item.cluster_id = clusterId ? parseInt(clusterId) : null;
      const c = state.portfolio.find(x => String(x.id) === String(clusterId));
      item.cluster_name = c ? c.name : null;
      applyResponse(data);
      document.getElementById('qClusterBadge').textContent = item.cluster_name || '📌 Uncategorized';
      toast('Cluster assigned', 'success');
    } catch(e) { toast(e.message, 'error'); }
  }

  async function saveQField(field) {
    const item = currentItems[qIndex];
    if (!item) return;
    const el = field === 'title' ? document.getElementById('qTitle') : document.getElementById('qDesc');
    const orig = item[field] || '';
    const current = el.textContent.trim();
    if (current === orig) return;
    if (field === 'title' && current === '') {
      el.textContent = orig;
      toast('Title cannot be empty', 'error');
      return;
    }

    const payload = { action: 'update_item', id: item.id };
    payload[field] = current;

    try {
      const data = await api(payload);
      item[field] = current;
      applyResponse(data);
      if (document.getElementById('itemsModal').classList.contains('open')) renderItemsList();
      toast('Saved', 'success');
    } catch(e) {
      el.textContent = orig;
      toast(e.message, 'error');
    }
  }

  function qToggleAi() {
    const panel = document.getElementById('qAiPanel');
    const toggle = document.getElementById('qAiToggle');
    if (!panel || !toggle) return;
    const opening = !panel.classList.contains('open');
    panel.classList.toggle('open', opening);
    toggle.classList.toggle('open', opening);
    if (opening) {
      renderQAiLog();
      setTimeout(() => { const i = document.getElementById('qAiInput'); if (i) i.focus(); }, 100);
    }
  }

  function renderQAiLog() {
    const item = currentItems[qIndex];
    if (!item) return;
    renderAiLogInto(document.getElementById('qAiLog'), item.id);
  }

  async function qSendAi() {
    const item = currentItems[qIndex];
    if (!item) return;
    const input = document.getElementById('qAiInput');
    const text = input.value.trim();
    if (!text) return;

    input.value = '';
    input.style.height = 'auto';

    const sendBtn = document.getElementById('qAiSend');
    if (sendBtn) sendBtn.disabled = true;

    try {
      await sendAiForItem(item, text);
    } catch(e) {
      toast(e.message, 'error');
    } finally {
      if (sendBtn) sendBtn.disabled = false;
    }
  }

  function refreshCurrentQItemFromPortfolio() {
    const cur = currentItems[qIndex];
    if (!cur) return;
    const found = findItemInPortfolio(cur.id);
    if (!found) return;

    Object.assign(currentItems[qIndex], {
      title: found.title,
      description: found.description,
      status: found.status,
      cluster_id: found.cluster_id,
      cluster_name: found.cluster_name || null
    });

    const titleEl = document.getElementById('qTitle');
    if (titleEl && titleEl.textContent !== found.title) titleEl.textContent = found.title;
    const descEl = document.getElementById('qDesc');
    if (descEl && descEl.textContent !== (found.description || '')) descEl.textContent = found.description || '';
    const sel = document.getElementById('qClusterSelect');
    if (sel) sel.value = found.cluster_id || '';
    updateQStatusButtons(found.status);
  }

  // ══════════════════════════════════════════════════════════════════════
  // MAINTENANCE
  // ══════════════════════════════════════════════════════════════════════
  async function submitIngest() {
    const json = document.getElementById('ingestJsonInput').value;
    if (!json.trim()) { toast('Paste JSON first', 'error'); return; }
    try {
      const data = await api({ action: 'ingest_json', json_data: json });
      applyResponse(data);
      document.getElementById('ingestJsonInput').value = '';
      toast(data.message || 'Ingested', 'success');
    } catch(e) { toast(e.message, 'error'); }
  }

  function handleFileUpload(input) {
    const file = input.files && input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = async function(e) {
      try {
        const data = await api({ action: 'ingest_json', json_data: e.target.result });
        applyResponse(data);
        toast(data.message || 'Ingested', 'success');
      } catch(err) { toast(err.message, 'error'); }
      input.value = '';
    };
    reader.readAsText(file);
  }

  async function downloadInstructions() {
    try {
      const data = await api({ action: 'download_instructions' });
      downloadJsonBlob(data.data, data.filename || 'aim_ingest_instructions.json');
    } catch(e) { toast(e.message, 'error'); }
  }

  function downloadJsonBlob(obj, filename) {
    const blob = new Blob([JSON.stringify(obj, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  function expToggleStatus(status) {
    const isAll = selectedExportStatuses.length === 3;
    if (isAll) {
      selectedExportStatuses = [status];
    } else {
      const idx = selectedExportStatuses.indexOf(status);
      if (idx >= 0) {
        if (selectedExportStatuses.length > 1) selectedExportStatuses.splice(idx, 1);
        else selectedExportStatuses = ['yes','no','unassigned'];
      } else {
        selectedExportStatuses.push(status);
      }
    }
    updateExpChips();
  }

  function expClickAll() {
    selectedExportStatuses = ['yes','no','unassigned'];
    updateExpChips();
  }

  function updateExpChips() {
    const hasYes = selectedExportStatuses.includes('yes');
    const hasNo = selectedExportStatuses.includes('no');
    const hasUn = selectedExportStatuses.includes('unassigned');
    const isAll = hasYes && hasNo && hasUn;
    const set = (id, cls) => { const el = document.getElementById(id); if (el) el.className = 'seg-btn' + (cls ? ' ' + cls : ''); };
    set('expChipAll', isAll ? 'active' : '');
    set('expChipYes', hasYes ? 'active-yes' : '');
    set('expChipNo', hasNo ? 'active-no' : '');
    set('expChipUn', hasUn ? 'active-amber' : '');
  }

  async function submitExport() {
    if (!state.sessionId) { toast('No project loaded', 'error'); return; }

    let clusters;
    if (clusterSelection.export.mode === 'all') {
      clusters = ['unassigned'];
      state.portfolio.forEach(c => { if (c.id) clusters.push(c.id); });
    } else {
      clusters = Array.from(clusterSelection.export.keys).map(k => k === 'uncat' ? 'unassigned' : k);
    }

    if (!clusters.length) { toast('Select at least one cluster', 'error'); return; }

    try {
      const data = await api({ action: 'export_json', clusters, statuses: selectedExportStatuses });
      downloadJsonBlob(data.data, data.filename || 'aim_export.json');
      toast('Export downloaded', 'success');
    } catch(e) { toast(e.message, 'error'); }
  }

  function purToggleStatus(status) {
    const isAll = purgeSelectedStatuses.length === 3;
    if (isAll) {
      purgeSelectedStatuses = [status];
    } else {
      const idx = purgeSelectedStatuses.indexOf(status);
      if (idx >= 0) {
        if (purgeSelectedStatuses.length > 1) purgeSelectedStatuses.splice(idx, 1);
        else purgeSelectedStatuses = ['yes','no','unassigned'];
      } else {
        purgeSelectedStatuses.push(status);
      }
    }
    updatePurgeChips();
  }

  function purClickAll() {
    purgeSelectedStatuses = ['yes','no','unassigned'];
    updatePurgeChips();
  }

  function updatePurgeChips() {
    const hasYes = purgeSelectedStatuses.includes('yes');
    const hasNo = purgeSelectedStatuses.includes('no');
    const hasUn = purgeSelectedStatuses.includes('unassigned');
    const isAll = hasYes && hasNo && hasUn;
    const set = (id, cls) => { const el = document.getElementById(id); if (el) el.className = 'seg-btn' + (cls ? ' ' + cls : ''); };
    set('purChipAll', isAll ? 'active' : '');
    set('purChipYes', hasYes ? 'active-yes' : '');
    set('purChipNo', hasNo ? 'active-no' : '');
    set('purChipUn', hasUn ? 'active-amber' : '');
  }

  async function confirmBatchDelete() {
    if (!purgeSelectedStatuses.length) { toast('Select at least one status', 'error'); return; }

    let clusters;
    if (clusterSelection.purge.mode === 'all') {
      clusters = ['all'];
    } else {
      clusters = Array.from(clusterSelection.purge.keys).map(k => k === 'uncat' ? 'unassigned' : k);
      if (!clusters.length) { toast('Select at least one cluster', 'error'); return; }
    }

    const scopeLabel = clusterSelection.purge.mode === 'all'
      ? 'All Clusters & Uncategorized'
      : clusters.map(c => c === 'unassigned' ? '📌 Uncategorized' : ('#' + c)).join(', ');
    const statusLabels = purgeSelectedStatuses.map(s => s.toUpperCase()).join(' + ');

    if (!confirm(`Permanently DELETE all ideas marked [${statusLabels}] in [${scopeLabel}]?\n\nThis cannot be undone.`)) return;

    try {
      const data = await api({ action: 'delete_ideas_batch', statuses: purgeSelectedStatuses, clusters });
      applyResponse(data);
      toast(data.message || 'Deleted', 'success');
      if (document.getElementById('itemsModal').classList.contains('open')) renderItemsList();
    } catch(e) { toast(e.message, 'error'); }
  }

  async function confirmTruncate(target) {
    const msg = target === 'ideas' ? 'Delete ALL ideas in this project? Clusters will remain.'
      : target === 'clusters' ? 'Delete ALL clusters in this project? Ideas become unassigned.'
      : 'WARNING: Erase ALL clusters AND ALL ideas in this project?';
    if (!confirm(msg)) return;
    try {
      const data = await api({ action: 'truncate_table', target });
      applyResponse(data);
      toast(data.message || 'Truncated', 'success');
    } catch(e) { toast(e.message, 'error'); }
  }

  function syncToggleStatus(status) {
    const isAll = syncSelectedStatuses.length === 3;
    if (isAll) {
      syncSelectedStatuses = [status];
    } else {
      const idx = syncSelectedStatuses.indexOf(status);
      if (idx >= 0) {
        if (syncSelectedStatuses.length > 1) syncSelectedStatuses.splice(idx, 1);
        else syncSelectedStatuses = ['yes','no','unassigned'];
      } else {
        syncSelectedStatuses.push(status);
      }
    }
    updateSyncChips();
  }

  function syncClickAll() {
    syncSelectedStatuses = ['yes','no','unassigned'];
    updateSyncChips();
  }

  function updateSyncChips() {
    const hasYes = syncSelectedStatuses.includes('yes');
    const hasNo = syncSelectedStatuses.includes('no');
    const hasUn = syncSelectedStatuses.includes('unassigned');
    const isAll = hasYes && hasNo && hasUn;
    const set = (id, cls) => { const el = document.getElementById(id); if (el) el.className = 'seg-btn' + (cls ? ' ' + cls : ''); };
    set('syncChipAll', isAll ? 'active' : '');
    set('syncChipYes', hasYes ? 'active-yes' : '');
    set('syncChipNo', hasNo ? 'active-no' : '');
    set('syncChipUn', hasUn ? 'active-amber' : '');
  }

  async function syncStateToAI() {
    if (state.isRequesting) { toast('Please wait for the current request to finish', 'error'); return; }

    const includeContent = document.getElementById('syncIncludeContent').checked;

    const orderedStatuses = ['yes','no','unassigned'].filter(s => syncSelectedStatuses.includes(s));
    const isAllStatuses = orderedStatuses.length === 3;

    const isAllScope = clusterSelection.sync.mode === 'all';
    let targetClusters = [];
    let scopeLabel = '';
    if (isAllScope) {
      targetClusters = state.portfolio.slice();
      scopeLabel = 'All clusters & Uncategorized';
    } else {
      const keys = clusterSelection.sync.keys;
      targetClusters = state.portfolio.filter(c => {
        const key = c.id ? String(c.id) : 'uncat';
        return keys.has(key);
      });
      const names = targetClusters.map(c => c.id ? c.name : '📌 Uncategorized');
      scopeLabel = names.length ? names.join(', ') : '(no clusters selected)';
    }

    if (!targetClusters.length) { toast('Select at least one cluster', 'error'); return; }

    const statusesLabel = isAllStatuses
      ? 'YES, NO, UNASSIGNED (all statuses)'
      : orderedStatuses.map(s => s.toUpperCase()).join(', ');

    const lines = [];
    lines.push('[State Sync — current state of this project\'s idea database]');
    lines.push('');
    lines.push(`Scope: ${scopeLabel}`);
    lines.push(`Statuses included: ${statusesLabel}`);
    lines.push('');

    if (isAllStatuses && isAllScope) {
      lines.push('IMPORTANT: This is truly EVERY idea in the database, and no others exist.');
      lines.push('Any idea you remember from earlier that is not listed here has been deleted or purged.');
      lines.push('');
    }

    let totalCount = 0;
    const clusterSections = [];

    targetClusters.forEach(c => {
      const matching = (c.ideas || []).filter(i => orderedStatuses.includes(i.status));
      if (!matching.length) return;

      totalCount += matching.length;

      const byStatus = { yes: [], no: [], unassigned: [] };
      matching.forEach(i => { byStatus[i.status].push(i); });

      const cName = c.id ? c.name : 'Uncategorized';
      const cLines = [`── Cluster "${cName}" (${matching.length} idea${matching.length === 1 ? '' : 's'}) ──`];

      orderedStatuses.forEach(st => {
        const bucket = byStatus[st];
        if (!bucket.length) return;
        if (includeContent) {
          cLines.push(`  ${st.toUpperCase()} (${bucket.length}):`);
          bucket.forEach(i => {
            cLines.push(`    #${i.id} ${i.title || '(untitled)'}`);
            if (i.description) cLines.push(`        ${i.description}`);
          });
        } else {
          const ids = bucket.map(i => '#' + i.id).join(', ');
          cLines.push(`  ${st.toUpperCase()} (${bucket.length}): ${ids}`);
        }
      });

      clusterSections.push(cLines.join('\n'));
    });

    if (clusterSections.length) {
      lines.push(clusterSections.join('\n\n'));
      lines.push('');
    }

    lines.push(`Total ideas in this snapshot: ${totalCount}`);
    lines.push('[End State Sync]');

    const message = lines.join('\n');

    closeFlyouts();
    await sendRawMessage(message);
  }

  // ══════════════════════════════════════════════════════════════════════
  // KG PICKER / CONTEXT INTEGRATION (Per Chat Session)
  // ══════════════════════════════════════════════════════════════════════
  function kgSetTab(tab) {
    _kgActiveTab = tab;
    const tabTree = document.getElementById('wr-kg-tab-tree');
    const tabSem = document.getElementById('wr-kg-tab-semantic');
    const paneTree = document.getElementById('wr-kg-pane-tree');
    const paneSem = document.getElementById('wr-kg-pane-semantic');
    if (tabTree) tabTree.classList.toggle('active', tab === 'tree');
    if (tabSem) tabSem.classList.toggle('active', tab === 'semantic');
    if (paneTree) paneTree.classList.toggle('active', tab === 'tree');
    if (paneSem) paneSem.classList.toggle('active', tab === 'semantic');
    kgUpdatePickerCount();
    if (tab === 'semantic') {
      setTimeout(() => { const inp = document.getElementById('wrKgSemanticQuery'); if (inp) inp.focus(); }, 100);
    }
  }

  async function kgRunSemanticQuery() {
    const query = document.getElementById('wrKgSemanticQuery').value.trim();
    if (!query) return;

    const nResults = parseInt(document.getElementById('wrKgSemanticN').value);
    const loadingBar = document.getElementById('wrKgSemanticLoader');
    const searchBtn = document.getElementById('wrKgSemanticBtn');
    const hitsArea = document.getElementById('wrKgSemanticHits');

    if (loadingBar) loadingBar.style.display = 'block';
    if (searchBtn) { searchBtn.disabled = true; searchBtn.textContent = '...'; }

    _kgSemanticHits = [];
    _kgSemanticSelectedIds = new Set();
    hitsArea.innerHTML = '';

    try {
      const res = await fetch('/kg_api.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({action: 'semantic_query', query, n_results: nResults})
      });
      const data = await res.json();

      if (!data.ok) throw new Error(data.error || 'Search failed');

      _kgSemanticHits = data.hits || [];
      if (!_kgSemanticHits.length) {
          hitsArea.innerHTML = `<div class="wr-kge-hits-empty"><span>No matches found.</span></div>`;
          return;
      }

      _kgSemanticHits.filter(h => h.score > 0.35).forEach(h => _kgSemanticSelectedIds.add(h.node_id));
      kgRenderSemanticHits();
      kgUpdatePickerCount();

    } catch(e) {
      hitsArea.innerHTML = `<div class="wr-kge-hits-empty"><span style="color:var(--red);">Error: ${escapeHtml(e.message)}</span></div>`;
      toast('Search error: ' + e.message, 'error');
    } finally {
      if (loadingBar) loadingBar.style.display = 'none';
      if (searchBtn) { searchBtn.disabled = false; searchBtn.textContent = 'Search'; }
    }
  }

  function kgRenderSemanticHits() {
    const area = document.getElementById('wrKgSemanticHits');
    if (!area) return;
    if (!_kgSemanticHits.length) { area.innerHTML = ''; return; }

    const maxScore = _kgSemanticHits[0]?.score || 1;
    const header = `
        <div class="wr-kge-hits-header">
            <span style="flex:1;">${_kgSemanticHits.length} node(s) ranked by relevance</span>
            <button class="wr-kg-picker-select-all" onclick="App.kgToggleAllSemantic()">Toggle all</button>
        </div>`;

    const rows = _kgSemanticHits.map(hit => {
        const checked = _kgSemanticSelectedIds.has(hit.node_id) ? 'checked' : '';
        const selClass = _kgSemanticSelectedIds.has(hit.node_id) ? 'selected' : '';
        const barW = Math.max(8, Math.round((hit.score / maxScore) * 60));
        const typeKey = (hit.node_type || 'note').toLowerCase();
        const dotClass = `wr-kge-dot-${hit.content_status}`;
        const excerpt = escapeHtml(hit.excerpt || '').replace(/^Node:[^\n]*\n?Type:[^\n]*\n?/, '').trim();

        return `
        <div class="wr-kge-hit-row ${selClass}" onclick="App.kgToggleSemanticHit(${hit.node_id}, this)">
            <input type="checkbox" class="wr-kge-hit-check" ${checked} onclick="event.stopPropagation(); App.kgToggleSemanticHit(${hit.node_id}, this.closest('.wr-kge-hit-row'))">
            <span class="wr-kge-status-dot ${dotClass}" title="${hit.content_status}"></span>
            <div class="wr-kge-hit-body">
                <div class="wr-kge-hit-name">
                    ${escapeHtml(hit.name)}
                    <span class="wr-kge-type-pill">${typeKey}</span>
                    ${hit.category_name ? `<span style="font-size:0.7rem;color:var(--text-dim);font-weight:400">${escapeHtml(hit.category_name)}</span>` : ''}
                    <span class="wr-kge-score-bar" style="width:${barW}px"></span>
                </div>
                ${excerpt ? `<div class="wr-kge-hit-excerpt">${excerpt}</div>` : ''}
            </div>
            <span class="wr-kge-hit-score">${(hit.score * 100).toFixed(0)}%</span>
        </div>`;
    }).join('');

    area.innerHTML = header + rows;
  }

  function kgToggleSemanticHit(nodeId, rowEl) {
    if (_kgSemanticSelectedIds.has(nodeId)) {
        _kgSemanticSelectedIds.delete(nodeId);
        rowEl.classList.remove('selected');
        const chk = rowEl.querySelector('input[type=checkbox]');
        if (chk) chk.checked = false;
    } else {
        _kgSemanticSelectedIds.add(nodeId);
        rowEl.classList.add('selected');
        const chk = rowEl.querySelector('input[type=checkbox]');
        if (chk) chk.checked = true;
    }
    kgUpdatePickerCount();
  }

  function kgToggleAllSemantic() {
    const allSelected = _kgSemanticHits.every(h => _kgSemanticSelectedIds.has(h.node_id));
    if (allSelected) { _kgSemanticSelectedIds.clear(); }
    else { _kgSemanticHits.forEach(h => _kgSemanticSelectedIds.add(h.node_id)); }
    kgRenderSemanticHits();
    kgUpdatePickerCount();
  }

  function kgPickerSaveOpenState() {
    const open = [];
    document.querySelectorAll('#kgPickerTreeWrap .wr-kg-tree-children.open').forEach(el => {
        open.push(el.id.replace('aim-kg-kids-', ''));
    });
    try { localStorage.setItem(AIM_KG_PICKER_OPEN_KEY, JSON.stringify(open)); } catch(e) {}
  }

  function kgPickerLoadOpenState() {
    try {
        const raw = localStorage.getItem(AIM_KG_PICKER_OPEN_KEY);
        if (raw) return new Set(JSON.parse(raw));
    } catch(e) {}
    return new Set();
  }

  function kgNodeIcon(type) {
    const map = { relationship:'🔗', character:'👤', location:'📍', event:'📅', concept:'💡', arc:'🌀', episode:'🎬', note:'📝' };
    return map[type] || '📝';
  }

  function openKgPickerModal() {
    const optContent = document.getElementById('kgOptContent');
    const optEdges = document.getElementById('kgOptEdges');
    if (optContent) optContent.checked = !!state.kgWithContent;
    if (optEdges) optEdges.checked = state.kgWithEdges !== false;

    openModal('kgPickerModal');
    kgSetTab('tree');

    if (_kgPickerRaw.length === 0) {
      kgLoadPickerTree();
    } else {
      kgRestoreSelection();
      kgRenderPickerTree();
      kgUpdatePickerCount();
    }
  }

  function kgRestoreSelection() {
    _kgPickerChecked.clear();
    const sel = state.kgSelectedNodes || [];
    sel.forEach(dbId => {
      _kgPickerChecked.add('n_' + dbId);
    });
  }

  function kgLoadPickerTree() {
    const wrap = document.getElementById('kgPickerTreeWrap');
    if (!wrap) return;
    wrap.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-dim);">Loading tree…</div>';
    fetch('/kg_api.php?action=fetch_tree')
      .then(r => r.json())
      .then(res => {
        if (!res.ok) { wrap.innerHTML = '<div style="padding:20px;text-align:center;color:var(--red);">Failed to load tree.</div>'; return; }
        _kgPickerRaw = res.tree;
        kgRestoreSelection();
        kgRenderPickerTree();
        kgUpdatePickerCount();
      })
      .catch(() => { wrap.innerHTML = '<div style="padding:20px;text-align:center;color:var(--red);">Error loading tree.</div>'; });
  }

  function kgRenderPickerTree() {
    const wrap = document.getElementById('kgPickerTreeWrap');
    if (!wrap) return;
    const childMap = {};
    _kgPickerRaw.forEach(n => {
      const p = n.parent || '#';
      if (!childMap[p]) childMap[p] = [];
      childMap[p].push(n);
    });
    const openSet = kgPickerLoadOpenState();
    wrap.innerHTML = kgBuildPickerLevel('#', childMap, 0, openSet);
    _kgPickerRaw.filter(n => n.type === 'node').forEach(n => {
      if (_kgPickerChecked.has(n.id)) {
        kgPickerSyncAncestors(n.id);
      }
    });
  }

  function kgBuildPickerLevel(parentId, childMap, depth, openSet) {
    const children = childMap[parentId] || [];
    if (!children.length) return '';
    const indent = depth * 14;
    let html = '';
    children.forEach(node => {
      const isFolder = node.type === 'folder';
      const jsId     = node.id;
      const checked  = _kgPickerChecked.has(jsId);
      const hasKids  = !!(childMap[jsId] && childMap[jsId].length);
      const icon     = isFolder ? '📁' : kgNodeIcon(node.data && node.data.node_type ? node.data.node_type : 'note');
      const isOpen   = openSet.has(jsId);

      const toggleBtn = (isFolder && hasKids)
        ? `<span class="wr-kg-node-toggle ${isOpen ? 'open' : ''}" onclick="App.kgPickerToggleFolder('${jsId}', this)">▶</span>`
        : `<span style="width:16px;display:inline-block;flex-shrink:0;"></span>`;

      html += `
      <div class="wr-kg-tree-node ${isFolder ? 'is-folder' : 'is-node'}"
           style="padding-left:${10 + indent}px;"
           data-jid="${jsId}">
          ${toggleBtn}
          <input type="checkbox" ${checked ? 'checked' : ''}
                 onchange="App.kgPickerCheck('${jsId}', this.checked)">
          <span class="wr-kg-node-icon">${icon}</span>
          <span class="wr-kg-node-label">${escapeHtml(node.text)}</span>
      </div>`;

      if (hasKids) {
          html += `<div class="wr-kg-tree-children ${isOpen ? 'open' : ''}" id="aim-kg-kids-${jsId}">`;
          html += kgBuildPickerLevel(jsId, childMap, depth + 1, openSet);
          html += `</div>`;
      }
    });
    return html;
  }

  function kgPickerToggleFolder(jsId, btn) {
    const kids = document.getElementById('aim-kg-kids-' + jsId);
    if (!kids) return;
    kids.classList.toggle('open');
    btn.classList.toggle('open');
    kgPickerSaveOpenState();
  }

  function kgPickerCheck(jsId, checked) {
    const ids = kgPickerDescendants(jsId);
    ids.forEach(id => {
      if (checked) _kgPickerChecked.add(id);
      else         _kgPickerChecked.delete(id);
    });
    ids.forEach(id => {
      const el = document.querySelector(`.wr-kg-tree-node[data-jid="${id}"] input[type=checkbox]`);
      if (el) { el.checked = checked; el.indeterminate = false; }
    });
    kgPickerSyncAncestors(jsId);
    kgUpdatePickerCount();
  }

  function kgPickerDescendants(jsId) {
    const result = [jsId];
    const queue  = [jsId];
    while (queue.length) {
      const cur = queue.shift();
      _kgPickerRaw.filter(n => n.parent === cur).forEach(n => {
        result.push(n.id);
        queue.push(n.id);
      });
    }
    return result;
  }

  function kgPickerHasAnyChecked(jsId) {
    if (_kgPickerChecked.has(jsId)) return true;
    return _kgPickerRaw
      .filter(n => n.parent === jsId)
      .some(child => kgPickerHasAnyChecked(child.id));
  }

  function kgPickerSyncAncestors(jsId) {
    const node = _kgPickerRaw.find(n => n.id === jsId);
    if (!node || !node.parent || node.parent === '#') return;
    const parentJid = node.parent;
    const siblings  = _kgPickerRaw.filter(n => n.parent === parentJid);
    if (!siblings.length) return;
    const allChecked  = siblings.every(s => _kgPickerChecked.has(s.id));
    const anyChecked  = siblings.some(s => kgPickerHasAnyChecked(s.id));
    const el = document.querySelector(`.wr-kg-tree-node[data-jid="${parentJid}"] input[type=checkbox]`);

    if (allChecked) {
      _kgPickerChecked.add(parentJid);
      if (el) { el.checked = true; el.indeterminate = false; }
    } else if (anyChecked) {
      _kgPickerChecked.delete(parentJid);
      if (el) { el.checked = false; el.indeterminate = true; }
    } else {
      _kgPickerChecked.delete(parentJid);
      if (el) { el.checked = false; el.indeterminate = false; }
    }
    kgPickerSyncAncestors(parentJid);
  }

  function kgPickerToggleAll() {
    const allChecked = _kgPickerRaw.every(n => _kgPickerChecked.has(n.id));
    if (allChecked) {
      _kgPickerChecked.clear();
    } else {
      _kgPickerRaw.forEach(n => _kgPickerChecked.add(n.id));
    }
    kgRenderPickerTree();
    kgUpdatePickerCount();
  }

  function kgUpdatePickerCount() {
    const el = document.getElementById('kgPickerCount');
    if (!el) return;
    if (_kgActiveTab === 'tree') {
      const nodeCount = _kgPickerRaw.filter(n => n.type === 'node' && _kgPickerChecked.has(n.id)).length;
      const total = _kgPickerRaw.filter(n => n.type === 'node').length;
      el.textContent = nodeCount === total ? `All ${total} nodes selected` : `${nodeCount} of ${total} nodes selected`;
    } else {
      const n = _kgSemanticSelectedIds.size;
      el.textContent = n ? `${n} node(s) selected (Semantic)` : 'No nodes selected';
    }
  }

  async function injectKgContext() {
    let dbIds = [];
    if (_kgActiveTab === 'tree') {
        dbIds = _kgPickerRaw.filter(n => n.type === 'node' && _kgPickerChecked.has(n.id)).map(n => n.data.db_id);
    } else {
        dbIds = Array.from(_kgSemanticSelectedIds);
    }

    const withContent = document.getElementById('kgOptContent').checked;
    const withEdges = document.getElementById('kgOptEdges').checked;

    if (_kgActiveTab === 'tree') {
        state.kgSelectedNodes = dbIds;
    }
    state.kgWithContent = withContent;
    state.kgWithEdges = withEdges;

    if (dbIds.length === 0) {
      toast('No nodes selected', 'error'); return;
    }

    const btn = document.getElementById('btnInjectKg');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass"></i> Fetching...';

    try {
      const res = await fetch('/kg_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'focused_snapshot', node_ids: dbIds, with_content: withContent, with_edges: withEdges })
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed to fetch snapshot');

      const kgMarkerStart = "═══ KNOWLEDGE GRAPH CONTEXT ═══";
      const kgMarkerEnd   = "═══ END KG CONTEXT ═══";
      const newBlock = kgMarkerStart + "\n" + JSON.stringify(data.snapshot, null, 2) + "\n" + kgMarkerEnd;

      let currentText = document.getElementById('setLore').value.trim();
      const regex = new RegExp(kgMarkerStart + "[\\s\\S]*?" + kgMarkerEnd, "g");

      if (regex.test(currentText)) {
          currentText = currentText.replace(regex, newBlock);
      } else {
          currentText = currentText ? currentText + "\n\n" + newBlock : newBlock;
      }

      document.getElementById('setLore').value = currentText;
      await saveSettings();
      closeModal('kgPickerModal');
      toast('KG Context Injected ✓', 'success');
    } catch(e) {
      toast('Error: ' + e.message, 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-box-arrow-in-down"></i> Inject Context';
    }
  }

  // --- UTILS ---
  function toggleFlyout(side) {
    const l = document.getElementById('flyoutLeft');
    const r = document.getElementById('flyoutRight');
    const b = document.getElementById('backdrop');
    if (side === 'left') { r.classList.remove('open'); l.classList.toggle('open'); }
    else { l.classList.remove('open'); r.classList.toggle('open'); }
    if (l.classList.contains('open') || r.classList.contains('open')) b.classList.add('visible');
    else b.classList.remove('visible');
  }

  function closeFlyouts() {
    document.getElementById('flyoutLeft').classList.remove('open');
    document.getElementById('flyoutRight').classList.remove('open');
    document.getElementById('backdrop').classList.remove('visible');
  }

  function injectCommand(cmd) {
    const input = document.getElementById('chatInput');
    input.value = cmd;
    closeFlyouts();
    input.focus();
  }

  function escapeHtml(s) {
    if(!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function escapeAttr(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function toast(msg, type='info', duration=3000) {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = msg;
    c.appendChild(t);
    setTimeout(() => { t.style.opacity='0'; setTimeout(()=>t.remove(),300); }, duration);
  }

  return {
    init,
    toggleFlyout, closeFlyouts, sendMessage, saveSettings, switchSession, deleteSession, newSession, injectCommand,
    renameSession,
    setRightTab, setDashView,
    // search
    onDashSearch, clearDashSearch,
    onItemsModalSearch, clearItemsModalSearch,
    // cluster multi-select chips
    clusterChipAll, clusterChipToggle,
    // stats
    statsToggleStatus, statsClickAll,
    // items / cluster
    openClusterModal, saveCluster, openItemsModal, renderItemsList, createNewItem,
    updateItemStatus, updateItemCluster, saveItemField, onTitleKeydown,
    itemsAiToggle, itemsAiSend,
    // questionnaire
    openQuestionnaireFromModal, openGlobalQuestionnaire, qNavigate, qSetStatus, qAssignCluster, saveQField,
    qToggleAi, qSendAi,
    // maintenance
    submitIngest, handleFileUpload, downloadInstructions, submitExport,
    expToggleStatus, expClickAll, purToggleStatus, purClickAll,
    syncToggleStatus, syncClickAll, syncStateToAI,
    confirmBatchDelete, confirmTruncate,
    closeModal,
    // KG Picker / Context addition
    openKgPickerModal,
    kgSetTab,
    kgRunSemanticQuery,
    kgToggleSemanticHit,
    kgToggleAllSemantic,
    kgPickerToggleAll,
    kgPickerToggleFolder,
    kgPickerCheck,
    injectKgContext
  };
})();

document.addEventListener('DOMContentLoaded', window.App.init);
</script>
</body>
</html>
