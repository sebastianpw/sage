<?php
// public/chatty.php
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
<title>Chatty — General Chat</title>
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
/* ═══════════════════════════════════════════════════════════════════════════
   CHATTY — Design System
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
  --green:        #22d3a0;
  --red:          #f05060;
  --red-dim:      rgba(240,80,96,0.1);
  --blue:         #4da6ff;
  --blue-dim:     rgba(77,166,255,0.1);
  --violet:       #a78bfa;
  --violet-dim:   rgba(167,139,250,0.1);
  --violet-mid:   rgba(167,139,250,0.18);
  --violet-glow:  rgba(167,139,250,0.45);
  --mono:         'Space Mono', 'Fira Mono', monospace;
  --sans:         'Syne', system-ui, sans-serif;
  --radius:       6px;
  --radius-lg:    10px;
}

:root[data-theme="light"], html[data-theme="light"] {
  --bg:           #f6f8fa;
  --surface:      #e1e4e8;
  --card:         #ffffff;
  --border:       #d1d5db;
  --border-glow:  #9ca3af;
  --text:         #111827;
  --text-dim:     #4b5563;
  --text-bright:  #000000;
  --amber:        #d97706;
  --green:        #059669;
  --red:          #dc2626;
  --red-dim:      rgba(220,38,38,0.1);
  --blue:         #2563eb;
  --blue-dim:     rgba(37,99,235,0.1);
  --violet:       #7c3aed;
  --violet-dim:   rgba(124,58,237,0.1);
  --violet-mid:   rgba(124,58,237,0.18);
  --violet-glow:  rgba(124,58,237,0.4);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
  height: 100%; background: var(--bg); color: var(--text);
  font-family: var(--sans); font-size: 14px; line-height: 1.5;
  -webkit-font-smoothing: antialiased; overflow: hidden;
}

::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-dim); }

/* ── LAYOUT ─────────────────────────────────────────────────────────────── */
.wi-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }

/* ── HEADER ─────────────────────────────────────────────────────────────── */
.wi-header {
  height: 52px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 16px; background: var(--surface); border-bottom: 1px solid var(--border);
  position: relative; z-index: 100;
}
.wi-logo {
  display: flex; align-items: center; gap: 10px;
  font-family: var(--mono); font-size: 0.82rem; font-weight: 700;
  color: var(--violet); letter-spacing: 2px; text-transform: uppercase;
}
.wi-logo-icon {
  width: 28px; height: 28px; background: var(--violet-mid);
  border: 1px solid var(--violet-glow); border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center; font-size: 14px;
}

.btn-icon-sm {
  width: 32px; height: 32px; border-radius: var(--radius);
  border: 1px solid var(--border); background: var(--card);
  color: var(--text-dim); cursor: pointer; transition: all 0.15s;
  display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.btn-icon-sm:hover { border-color: var(--violet); color: var(--violet); background: var(--violet-dim); }

/* ── FLYOUTS ────────────────────────────────────────────────────────────── */
.wi-flyout {
  position: fixed; top: 52px; bottom: 0; width: min(300px, 88vw);
  background: var(--surface); border-color: var(--border); z-index: 500;
  display: flex; flex-direction: column; overflow: hidden;
  transition: transform 0.28s cubic-bezier(0.25, 1, 0.5, 1);
  box-shadow: 0 0 40px rgba(0,0,0,0.6);
}
.wi-flyout-left  { left: 0;  border-right: 1px solid var(--border); transform: translateX(-100%); }
.wi-flyout.open  { transform: translateX(0); }

.wi-flyout-backdrop {
  position: fixed; inset: 52px 0 0 0; background: rgba(0,0,0,0.45);
  z-index: 499; display: none; backdrop-filter: blur(2px);
}
.wi-flyout-backdrop.visible { display: block; }

.wi-hamburger {
  display: flex; align-items: center; justify-content: center;
  width: 36px; height: 36px; border-radius: var(--radius);
  border: 1px solid var(--border); background: var(--card);
  color: var(--text-dim); cursor: pointer; transition: all 0.15s;
  flex-shrink: 0; font-size: 16px; position: relative;
}
.wi-hamburger:hover, .wi-hamburger.open { border-color: var(--violet); color: var(--violet); background: var(--violet-dim); }

.flyout-head {
  padding: 12px 14px 10px; border-bottom: 1px solid var(--border); flex-shrink: 0;
  display: flex; align-items: center; justify-content: space-between;
}
.flyout-head-title {
  font-family: var(--mono); font-size: 0.65rem; color: var(--violet);
  text-transform: uppercase; letter-spacing: 2px;
}
.flyout-close {
  width: 24px; height: 24px; border-radius: 4px; border: 1px solid var(--border);
  background: transparent; color: var(--text-dim); cursor: pointer; font-size: 12px;
  display: flex; align-items: center; justify-content: center; transition: all 0.15s;
}
.flyout-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }

.left-tabs { display: flex; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.left-tab {
  flex: 1; padding: 8px 4px; font-family: var(--mono); font-size: 0.65rem;
  color: var(--text-dim); text-align: center; cursor: pointer;
  text-transform: uppercase; letter-spacing: 1px;
  border-bottom: 2px solid transparent; transition: all 0.15s;
  background: none; border-top: none; border-left: none; border-right: none;
}
.left-tab.active { color: var(--violet); border-bottom-color: var(--violet); }
.left-tab:hover:not(.active) { color: var(--text); }

.left-tab-body { flex: 1; overflow: hidden; position: relative; }
.left-tab-content { position: absolute; inset: 0; overflow-y: auto; display: none; padding: 12px; }
.left-tab-content.active { display: block; }

/* ── Context / form fields ─────────────────────────────────────────────── */
.ctx-group { margin-bottom: 12px; }
.ctx-label {
  font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim);
  text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 5px; display: block;
}
.ctx-textarea {
  width: 100%; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text); font-family: var(--mono);
  font-size: 0.73rem; line-height: 1.5; padding: 8px 10px; resize: vertical;
  min-height: 60px; transition: border-color 0.15s;
}
.ctx-textarea:focus { outline: none; border-color: var(--violet); }
.ctx-textarea.tall { min-height: 100px; }

.ctx-input {
  width: 100%; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text); font-family: var(--mono);
  font-size: 0.73rem; padding: 7px 10px; transition: border-color 0.15s;
}
.ctx-input:focus { outline: none; border-color: var(--violet); }

.ctx-select {
  width: 100%; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text); font-family: var(--mono);
  font-size: 0.73rem; padding: 7px 10px; cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 10px center; padding-right: 28px;
}
.ctx-select:focus { outline: none; border-color: var(--violet); }

.item-delete-btn {
  position: absolute; right: 6px; top: 6px; width: 20px; height: 20px;
  border-radius: 4px; border: 1px solid transparent; background: transparent;
  color: var(--text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: all 0.15s; font-size: 10px;
}
.item-delete-btn:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }

.delta-list { display: flex; flex-direction: column; gap: 4px; }
.delta-item {
  padding: 6px 10px; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); cursor: pointer; transition: all 0.15s;
  display: flex; flex-direction: column; position: relative;
}
.delta-item:hover { border-color: var(--violet); }
.delta-item.active-chat { border-color: var(--violet); background: var(--violet-dim); }
.delta-date { font-family: var(--mono); font-size: 0.6rem; color: var(--violet); }
.delta-topic { font-family: var(--mono); font-size: 0.72rem; color: var(--text); margin-top: 2px; padding-right: 48px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.empty-state { text-align: center; padding: 24px 12px; color: var(--text-dim); font-family: var(--mono); font-size: 0.75rem; }
.empty-icon { font-size: 28px; opacity: 0.2; margin-bottom: 6px; }

/* ── MAIN AREA ──────────────────────────────────────────────────────────── */
.wi-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: var(--bg); min-height: 0; }
.wi-conversation { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 16px; }

.wi-welcome {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  height: 100%; text-align: center; gap: 20px; padding: 40px;
}
.wi-welcome-sigil { font-size: 52px; opacity: 0.15; line-height: 1; font-family: var(--mono); }
.wi-welcome-title { font-family: var(--mono); font-size: 1rem; color: var(--violet); text-transform: uppercase; letter-spacing: 3px; }
.wi-welcome-body { font-size: 0.85rem; color: var(--text-dim); line-height: 1.7; max-width: 480px; }

.msg { display: flex; gap: 12px; animation: msgIn 0.25s ease; }
@keyframes msgIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
.msg-user { flex-direction: row-reverse; }

.msg-avatar {
  width: 32px; height: 32px; border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; flex-shrink: 0; font-family: var(--mono); font-weight: bold;
}
.msg-avatar.user-av { background: var(--violet-dim); border: 1px solid var(--violet-glow); color: var(--violet); }
.msg-avatar.ai-av    { background: var(--blue-dim);   border: 1px solid var(--blue);        color: var(--blue); }

.msg-bubble { max-width: calc(100% - 56px); padding: 12px 16px; border-radius: var(--radius-lg); font-size: 0.85rem; line-height: 1.7; }
.msg-user .msg-bubble { background: var(--violet-dim); border: 1px solid var(--violet-glow); color: var(--text-bright); border-top-right-radius: 3px; }
.msg-ai .msg-bubble   { background: var(--card); border: 1px solid var(--border); color: var(--text); border-top-left-radius: 3px; }
.msg-bubble-meta { display: flex; gap: 8px; align-items: center; margin-bottom: 6px; flex-wrap: wrap; }
.msg-protocol-badge {
  font-family: var(--mono); font-size: 0.6rem; padding: 1px 6px; border-radius: 3px;
  background: var(--violet-dim); border: 1px solid var(--violet); color: var(--violet);
  text-transform: uppercase; letter-spacing: 1px;
}
.msg-timestamp { font-family: var(--mono); font-size: 0.6rem; color: var(--text-dim); }

.msg-action-btn {
  background: none; border: none; color: var(--text-dim); cursor: pointer; font-size: 13px; 
  padding: 2px 4px; transition: color 0.15s; display: flex; align-items: center; justify-content: center;
}
.msg-action-btn:hover { color: var(--violet); }
.msg-action-btn.delete-btn:hover { color: var(--red); }

.ai-section-body { font-size: 0.84rem; color: var(--text); line-height: 1.75; }

.msg-loading .msg-bubble { display: flex; align-items: center; gap: 10px; padding: 14px 16px; }
.typing-dots span {
  display: inline-block; width: 6px; height: 6px; border-radius: 50%;
  background: var(--blue); margin: 0 2px; animation: typing 1.2s ease-in-out infinite;
}
.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing { 0%,80%,100% { transform: translateY(0); opacity: 0.4; } 40% { transform: translateY(-6px); opacity: 1; } }

/* ── INPUT BAR ──────────────────────────────────────────────────────────── */
.wi-input-bar { padding: 12px 16px; border-top: 1px solid var(--border); background: var(--surface); flex-shrink: 0; display: flex; flex-direction: column; }
.input-bar-bottom { display: flex; gap: 8px; align-items: flex-end; }
.wi-textarea {
  flex: 1; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text-bright);
  font-family: var(--sans); font-size: 0.88rem; padding: 10px 14px;
  resize: none; min-height: 44px; max-height: 200px;
  transition: border-color 0.15s; line-height: 1.5; overflow-y: auto;
}
.wi-textarea:focus { outline: none; border-color: var(--violet); }
.wi-textarea::placeholder { color: var(--text-dim); }

.btn-send {
  width: 44px; height: 44px; border-radius: var(--radius);
  background: var(--violet); color: #000; border: none;
  cursor: pointer; transition: all 0.15s; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 16px;
}
.btn-send:hover:not(:disabled) { filter: brightness(1.15); transform: translateY(-1px); }
.btn-send:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
.btn-send .spin-sm {
  width: 16px; height: 16px; border: 2px solid rgba(0,0,0,0.3);
  border-top-color: #000; border-radius: 50%; animation: spin 0.7s linear infinite; display: none;
}
.btn-send.loading i { display: none; }
.btn-send.loading .spin-sm { display: block; }

.btn-icon {
  width: 44px; height: 44px; border-radius: var(--radius);
  background: var(--surface); color: var(--text-dim); border: 1px solid var(--border);
  cursor: pointer; transition: all 0.15s; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 18px;
}
.btn-icon:hover { color: var(--violet); border-color: var(--violet); }

/* File Attachments */
.attached-files-container {
    display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px;
}
.file-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 8px; border-radius: var(--radius);
    background: var(--card); border: 1px solid var(--border);
    font-family: var(--mono); font-size: 0.75rem; color: var(--text);
}
.file-chip button {
    background: none; border: none; color: var(--text-dim);
    cursor: pointer; padding: 0; font-size: 0.9rem; margin-left: 2px;
}
.file-chip button:hover { color: var(--red); }

/* ── MODALS ─────────────────────────────────────────────────────────────── */
.wi-modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(3px);
  z-index: 9999; display: none; align-items: center; justify-content: center; padding: 16px;
}
.wi-modal-overlay.open { display: flex; }
.wi-modal {
  background: var(--surface); border: 1px solid var(--border-glow);
  border-radius: var(--radius-lg); width: 100%; max-width: 640px; max-height: 90vh;
  display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.7); animation: modalIn 0.2s ease;
}
.wi-modal-header {
  padding: 16px 20px; border-bottom: 1px solid var(--border);
  display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
}
.wi-modal-title { font-family: var(--mono); font-size: 0.78rem; color: var(--violet); text-transform: uppercase; letter-spacing: 1.5px; }
.wi-modal-close {
  width: 26px; height: 26px; border-radius: 4px; border: 1px solid var(--border);
  background: transparent; color: var(--text-dim); cursor: pointer;
  display: flex; align-items: center; justify-content: center; font-size: 13px; transition: all 0.15s;
}
.wi-modal-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }
.wi-modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.wi-modal-footer {
  padding: 12px 20px; border-top: 1px solid var(--border);
  display: flex; justify-content: flex-end; gap: 8px; flex-shrink: 0; background: var(--bg);
}

.btn-primary {
  padding: 8px 18px; background: var(--violet); color: #000; border: none;
  border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.76rem;
  font-weight: 700; text-transform: uppercase; letter-spacing: 1px; transition: all 0.15s;
}
.btn-primary:hover { filter: brightness(1.1); }
.btn-secondary {
  padding: 8px 18px; background: transparent; color: var(--text-dim); border: 1px solid var(--border);
  border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.76rem; transition: all 0.15s;
}
.btn-secondary:hover { border-color: var(--border-glow); color: var(--text); }

/* ── TOAST ──────────────────────────────────────────────────────────────── */
.toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 6px; pointer-events: none; }
.toast {
  padding: 8px 14px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--border);
  font-family: var(--mono); font-size: 0.76rem; color: var(--text); box-shadow: 0 4px 20px rgba(0,0,0,0.5);
  animation: toastIn 0.2s ease; pointer-events: all; cursor: pointer; display: flex; gap: 6px; align-items: center; max-width: 300px;
}
.toast.success { border-color: var(--green); }
.toast.error   { border-color: var(--red); color: var(--red); }
.toast.info    { border-color: var(--violet); }
.toast.out     { animation: toastOut 0.2s forwards; }

.sep { height: 1px; background: var(--border); margin: 10px 0; }

@keyframes spin { to { transform: rotate(360deg); } }
@keyframes toastIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
@keyframes toastOut { to { opacity: 0; transform: translateY(8px); } }
@keyframes modalIn { from { opacity: 0; transform: scale(0.96) translateY(-8px); } to { opacity: 1; transform: none; } }

/* ── PAGINATION BAR ── */
.pg-bar { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 4px 2px; margin-top: 4px; font-family: var(--mono); }
.pg-btn { width: 26px; height: 26px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; flex-shrink: 0; }
.pg-btn:hover:not(:disabled) { border-color: var(--violet); color: var(--violet); background: var(--violet-dim); }
.pg-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.pg-input { width: 40px; height: 26px; text-align: center; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.72rem; padding: 0 2px; }
.pg-input:focus { outline: none; border-color: var(--violet); }
.pg-of { font-size: 0.68rem; color: var(--text-dim); white-space: nowrap; }
</style>
</head>
<body>

<div class="wi-layout">

<div class="wi-flyout-backdrop" id="flyoutBackdrop" onclick="Chatty.closeFlyouts()"></div>

<header class="wi-header">
  <button class="wi-hamburger" id="hamburgerLeft" onclick="Chatty.toggleFlyout('left')" title="Chats &amp; Settings">
    <i class="bi bi-layout-text-sidebar"></i>
  </button>
  <div class="wi-logo" style="flex:1; justify-content:center;">
    <div class="wi-logo-icon">💬</div>
    <span>Chatty</span>
  </div>
  <div style="display:flex;align-items:center;gap:6px;">
    <!-- DOWNLOAD CHAT BUTTON -->
    <button class="btn-icon-sm" onclick="Chatty.downloadChat()" title="Download Chat" style="width:32px;height:32px;">
      <i class="bi bi-download"></i>
    </button>
    <button class="btn-icon-sm" onclick="Chatty.clearConversation()" title="New Chat" style="width:32px;height:32px;">
      <i class="bi bi-plus-circle"></i>
    </button>
    <button class="btn-icon-sm" onclick="Chatty.toggleTheme()" id="themeBtn" title="Toggle Theme" style="display:none;width:32px;height:32px;">
      <i class="bi bi-moon-stars"></i>
    </button>
  </div>
</header>

<!-- LEFT FLYOUT — Chats / Settings -->
<div class="wi-flyout wi-flyout-left" id="flyoutLeft">
  <div class="flyout-head">
    <span class="flyout-head-title">Menu</span>
    <button class="flyout-close" onclick="Chatty.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
  </div>
  <div style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
    <div class="left-tabs">
      <button class="left-tab active" data-ltab="chats">Chats</button>
      <button class="left-tab" data-ltab="settings">Settings</button>
    </div>
    <div class="left-tab-body">

      <!-- CHATS TAB -->
      <div class="left-tab-content active" id="ltab-chats">
        <div class="delta-list" id="chatSessionsList">
          <div class="empty-state"><div class="empty-icon">💬</div>No past chats</div>
        </div>
        <div class="pg-bar" id="chatSessionsListPager" style="display:none;">
          <button class="pg-btn" id="chatSessionsListPagerPrev"><i class="bi bi-chevron-left"></i></button>
          <input type="text" class="pg-input" id="chatSessionsListPagerInput" inputmode="numeric">
          <span class="pg-of">/ <span id="chatSessionsListPagerTotal">1</span></span>
          <button class="pg-btn" id="chatSessionsListPagerNext"><i class="bi bi-chevron-right"></i></button>
        </div>
      </div>

      <!-- SETTINGS TAB -->
      <div class="left-tab-content" id="ltab-settings">
        <div class="ctx-group">
          <label class="ctx-label">Chat Model</label>
          <select class="ctx-select" id="setModel"></select>
        </div>
        <div class="ctx-group">
          <label class="ctx-label">System Prompt</label>
          <textarea class="ctx-textarea tall" id="setSystemPrompt"
            placeholder="You are a helpful and detailed AI assistant..."></textarea>
        </div>
        <div class="sep"></div>
        <div class="ctx-group" style="display:flex; align-items:center; gap:8px;">
          <input type="checkbox" id="setMuteNotifs" style="accent-color:var(--violet);">
          <label class="ctx-label" style="margin:0; cursor:pointer;" for="setMuteNotifs">Mute Dashboard Notifications</label>
        </div>
        <div class="ctx-group" style="margin-bottom:16px;">
          <button class="btn-secondary" style="width:100%;font-size:0.72rem;" onclick="Chatty.manualMarkAllAsRead()">
            <i class="bi bi-check2-all"></i> Mark All as Read
          </button>
        </div>
        <div class="sep"></div>
        <button class="btn-primary" style="width:100%;font-size:0.72rem;" onclick="Chatty.saveSettings()">
          <i class="bi bi-floppy"></i> Save Settings
        </button>
      </div>

    </div>
  </div>
</div>

<!-- MAIN -->
<main class="wi-main">
  <div class="wi-conversation" id="conversation">
    <div class="wi-welcome" id="welcomeState">
      <div class="wi-welcome-sigil">💬</div>
      <div class="wi-welcome-title">Chatty</div>
      <div class="wi-welcome-body">
        General-purpose autonomous chat engine. Type a message or attach text files to begin.
      </div>
    </div>
  </div>

  <div class="wi-input-bar">
    <div class="attached-files-container" id="attachedFilesContainer" style="display:none;"></div>
    
    <div class="input-bar-bottom">
      <button class="btn-icon" onclick="document.getElementById('fileInput').click()" title="Attach Text Files">
        <i class="bi bi-paperclip"></i>
      </button>
      <input type="file" id="fileInput" multiple accept=".txt,.json,.md,.sql,.csv,.log,.xml,text/*" style="display:none;">

      <textarea class="wi-textarea" id="msgInput" placeholder="Type a message..." rows="1"></textarea>

      <button class="btn-send" id="btnSendMain" onclick="Chatty.sendMessage()" title="Send">
        <i class="bi bi-send-fill" id="sendIcon"></i>
        <div class="spin-sm"></div>
      </button>
    </div>
  </div>
</main>

</div><!-- /wi-layout -->

<!-- RENAME CHAT MODAL -->
<div class="wi-modal-overlay" id="renameChatModal">
  <div class="wi-modal" style="max-width:400px;">
    <div class="wi-modal-header">
      <div class="wi-modal-title">Rename Session</div>
      <button class="wi-modal-close" onclick="Chatty.closeModal('renameChatModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="wi-modal-body">
      <input type="hidden" id="renameChatId" value="">
      <div class="ctx-group">
        <label class="ctx-label">Session Name</label>
        <input class="ctx-input" type="text" id="renameChatTitle" placeholder="e.g. Chat #1">
      </div>
    </div>
    <div class="wi-modal-footer">
      <button class="btn-secondary" onclick="Chatty.closeModal('renameChatModal')">Cancel</button>
      <button class="btn-primary" onclick="Chatty.saveRenameChat()">Save</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
window.Chatty = (function() {
  'use strict';

  function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  // ── Pollinations API Integration ─────────────────────────────────────────
  const PollinationsAPI = (() => {
      let _models = [];
      async function getModels() {
          if (_models.length > 0) return _models;
          try {
              const r = await fetch('https://gen.pollinations.ai/text/models');
              const d = await r.json();
              _models = d.map(x => x.name);
          } catch(e) {
              console.error('Model fetch failed', e);
              _models = ['openai', 'claude', 'llama'];
          }
          return _models;
      }
      return { getModels };
  })();

  async function populateModelSelect() {
      const sel = document.getElementById('setModel');
      if (!sel) return;
      let models = await PollinationsAPI.getModels();
      sel.innerHTML = '';
      models.forEach(m => {
          const opt = document.createElement('option');
          opt.value = m;
          opt.textContent = m;
          sel.appendChild(opt);
      });
      if (_settings.model) sel.value = _settings.model;
  }

  // ── State ─────────────────────────────────────────────────────────────
  let _conversation      = [];
  let _chatSessions      = [];
  let _chatSessionId     = generateUUID();
  let _chatSessionTitle  = 'New Chat';
  let _settings = {
    model: 'openai',
    system_prompt: '',
    mute_notifications: false
  };
  let _isLoading = false;
  let attachedFiles = [];

  let _chatSessionsPg = { page: 1, limit: 12, total_pages: 1 };
  
  // Background polling state
  let _isPolling = false;
  let _pollTimer = null;
  
  let _globalPollTimer = null;
  let _muteNotifs = false;
  let _isChattyVisible = (window.self === window.top);

  // ── API helper ───────────────────────────────────────────────────────
  async function api(payload, method = 'POST') {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    let url = '/chatty_api.php';
    if (method === 'GET') {
      url += '?' + new URLSearchParams(payload).toString();
    } else {
      opts.body = JSON.stringify(payload);
    }
    const res = await fetch(url, opts);
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    return data;
  }

  function bindPager(listId, loader) {
    const prevBtn = document.getElementById(listId + 'PagerPrev');
    const nextBtn = document.getElementById(listId + 'PagerNext');
    const input = document.getElementById(listId + 'PagerInput');
    if (!prevBtn || !nextBtn || !input) return;

    prevBtn.addEventListener('click', () => {
      if (_chatSessionsPg.page > 1) loader(_chatSessionsPg.page - 1);
    });
    nextBtn.addEventListener('click', () => {
      if (_chatSessionsPg.page < _chatSessionsPg.total_pages) loader(_chatSessionsPg.page + 1);
    });
    input.addEventListener('change', () => {
      let p = parseInt(input.value, 10);
      if (!Number.isFinite(p) || p < 1) p = 1;
      if (p > _chatSessionsPg.total_pages) p = _chatSessionsPg.total_pages;
      loader(p);
    });
    input.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); input.blur(); input.dispatchEvent(new Event('change')); }
    });
  }

  function renderPager(listId, state) {
    const bar = document.getElementById(listId + 'Pager');
    const input = document.getElementById(listId + 'PagerInput');
    const total = document.getElementById(listId + 'PagerTotal');
    const prevBtn = document.getElementById(listId + 'PagerPrev');
    const nextBtn = document.getElementById(listId + 'PagerNext');
    if (!bar) return;

    bar.style.display = state.total_pages > 1 ? 'flex' : 'none';
    input.value = state.page;
    total.textContent = state.total_pages;
    prevBtn.disabled = state.page <= 1;
    nextBtn.disabled = state.page >= state.total_pages;
  }

  // ── Unread Badge Notifications ──────────────────────────────────────────
  async function markAllAsRead() {
    if (_muteNotifs) return;
    try {
        await api({ action: 'mark_read' }, 'GET');
        if (window.self !== window.top) {
            window.parent.postMessage({ type: 'CHATTY_UNREAD', count: 0 }, '*');
        }
    } catch(e) {}
  }

  async function manualMarkAllAsRead() {
      await markAllAsRead();
      toast('All messages marked as read', 'success');
  }

  async function checkUnreadGlobal() {
      if (_muteNotifs) return;
      try {
          const res = await api({ action: 'check_unread' }, 'GET');
          if (res.unread > 0) {
              if (_isChattyVisible) {
                  markAllAsRead();
              } else {
                  if (window.self !== window.top) {
                      window.parent.postMessage({ type: 'CHATTY_UNREAD', count: res.unread }, '*');
                  }
              }
          }
      } catch(e) {}
  }

  // ── Polling & Core Actions ────────────────────────────────────────────────────────
  function startPolling(sessionId, originalMsgCount) {
    if (_pollTimer) clearInterval(_pollTimer);
    _isPolling = true;
    _pollTimer = setInterval(async () => {
        if (_chatSessionId !== sessionId) {
            clearInterval(_pollTimer);
            _pollTimer = null;
            _isPolling = false;
            return;
        }
        await loadChat(sessionId, true);
    }, 3000);
  }

  function redrawConversation() {
      const conv = document.getElementById('conversation');
      [...conv.children].forEach(el => { if (!el.classList.contains('wi-welcome')) el.remove(); });
      
      if (_conversation.length === 0) {
          document.getElementById('welcomeState').style.display = 'flex';
      } else {
          document.getElementById('welcomeState').style.display = 'none';
          _conversation.forEach(msg => {
            if (msg.role === 'user') renderUserMsg(msg);
            else renderAiMsg(msg);
          });
      }
      conv.scrollTop = conv.scrollHeight;
  }

  async function sendMessage() {
    if (_isLoading || _isPolling) return;
    const input = document.getElementById('msgInput');
    let text = input.value.trim();

    if (!text && attachedFiles.length === 0) return;

    if (attachedFiles.length > 0) {
        text += (text ? "\n\n" : "") + attachedFiles.map(f => `=== FILE: ${f.name} ===\n${f.content}\n====================`).join("\n\n");
    }

    input.value = '';
    autoResizeTextarea(input);
    attachedFiles = [];
    renderAttachedFiles();
    
    document.getElementById('welcomeState').style.display = 'none';

    _isLoading = true;
    setMainButtonLoading(true);

    const ts = Date.now();
    renderUserMsg({ role: 'user', content: text, ts: ts }); // Temporary visual without actions
    const loadingEl = addLoadingBubble();

    try {
      const data = await api({
        action: 'send_message',
        chat_session_id: _chatSessionId,
        message: text,
        settings: _settings
      });

      _conversation.push({ id: data.user_msg_id, role: 'user', content: text, ts: ts });

      if (_conversation.length === 1 && !_chatSessions.find(x => x.id === _chatSessionId)) {
          const titleRaw = text.replace(/=== FILE:.*?===.*?====================/gs, '').trim();
          _chatSessionTitle = (titleRaw || 'File Upload').substring(0, 40);
          _chatSessions.unshift({ id: _chatSessionId, title: _chatSessionTitle, updated_at: new Date().toISOString() });
          renderChatSessionsList();
      }

      startPolling(_chatSessionId, _conversation.length);
      
    } catch (e) {
      toast('Error: ' + e.message, 'error');
      loadingEl.remove();
      _isLoading = false;
      setMainButtonLoading(false);
      await loadChat(_chatSessionId, true); // Sync DB state on error
    }
  }

  function setMainButtonLoading(loading) {
    const btn = document.getElementById('btnSendMain');
    if (!btn) return;
    btn.classList.toggle('loading', loading);
    btn.disabled = loading;
  }

  async function branchChat(msgId) {
    if (!confirm('Branch a new chat session from this message?')) return;
    const newSessionId = generateUUID();
    try {
        await api({ action: 'branch_chat', msg_id: msgId, old_session_id: _chatSessionId, new_session_id: newSessionId });
        toast('Chat branched!', 'success');
        await loadChatSessions(1);
        await loadChat(newSessionId);
    } catch(e) {
        toast('Error: ' + e.message, 'error');
    }
  }

  async function deleteMsg(msgId) {
    if (!confirm('Permanently delete this message?')) return;
    try {
        await api({ action: 'delete_msg', msg_id: msgId, chat_session_id: _chatSessionId });
        _conversation = _conversation.filter(m => String(m.id) !== String(msgId));
        redrawConversation();
        toast('Message deleted', 'info');
    } catch(e) {
        toast('Error: ' + e.message, 'error');
    }
  }

  // ── Download Export ─────────────────────────────────────────────────────
  async function downloadChat() {
    if (_conversation.length === 0) {
      toast('Chat is empty', 'error');
      return;
    }
    try {
      const data = await api({ action: 'export_chat', chat_session_id: _chatSessionId }, 'GET');
      const blob = new Blob([data.export_text], { type: 'text/plain;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `chatty_export_${_chatSessionId}.txt`;
      a.click();
      URL.revokeObjectURL(url);
      toast('Chat downloaded', 'success');
    } catch (e) {
      toast('Error: ' + e.message, 'error');
    }
  }

  // ── File Attachments ──────────────────────────────────────────────────
  function attachFile(file) {
      if (file.size > 5 * 1024 * 1024) { 
          toast(`File ${file.name} is too large. Max 5MB.`, 'error');
          return;
      }
      const reader = new FileReader();
      reader.onload = (e) => {
          attachedFiles.push({ name: file.name, content: e.target.result });
          renderAttachedFiles();
      };
      reader.onerror = () => toast(`Failed to read ${file.name}`, 'error');
      reader.readAsText(file);
  }

  function renderAttachedFiles() {
      const container = document.getElementById('attachedFilesContainer');
      if (attachedFiles.length === 0) {
          container.style.display = 'none';
          return;
      }
      container.style.display = 'flex';
      container.innerHTML = attachedFiles.map((f, i) => `
          <div class="file-chip">
              <i class="bi bi-file-text"></i> ${escHtml(f.name)}
              <button onclick="Chatty.removeFile(${i})"><i class="bi bi-x"></i></button>
          </div>
      `).join('');
  }

  function removeFile(i) {
      attachedFiles.splice(i, 1);
      renderAttachedFiles();
  }

  // ── Rendering ────────────────────────────────────────────────────────────
  function getActionHtml(msg) {
    if (!msg.id) return '';
    return `
      <span style="margin-left:auto; display:flex; align-items:center; gap: 4px;">
        <button onclick="Chatty.copyMsg(this)" data-content="${escAttr(msg.content)}" class="msg-action-btn" title="Copy text">
          <i class="bi bi-clipboard"></i>
        </button>
        <button onclick="Chatty.branchChat('${msg.id}')" class="msg-action-btn" title="Branch from here">
          <i class="bi bi-diagram-2"></i>
        </button>
        <div style="width:1px; height:12px; background:var(--border-glow); margin:0 6px;"></div>
        <button onclick="Chatty.deleteMsg('${msg.id}')" class="msg-action-btn delete-btn" title="Delete message">
          <i class="bi bi-trash"></i>
        </button>
      </span>
    `;
  }

  function renderUserMsg(msg) {
    const conv = document.getElementById('conversation');
    let rawText = msg.display_content || msg.content || '';
    let htmlText = escHtml(rawText);
    
    htmlText = htmlText.replace(/=== FILE: (.*?) ===[\s\S]*?====================/g, '<div class="file-chip" style="margin: 4px 0;"><i class="bi bi-file-text"></i> $1 (attached)</div>');
    htmlText = htmlText.replace(/\n/g, '<br>');

    const msgNode = document.createElement('div');
    msgNode.className = 'msg msg-user';
    msgNode.innerHTML = `
      <div class="msg-avatar user-av">U</div>
      <div class="msg-bubble">
        <div class="msg-bubble-meta">
           <span class="msg-timestamp">${formatTime(msg.ts)}</span>
           ${getActionHtml(msg)}
        </div>
        ${htmlText}
      </div>
    `;
    conv.appendChild(msgNode);
    conv.scrollTop = conv.scrollHeight;
  }

  function renderAiMsg(msg) {
    const conv = document.getElementById('conversation');
    const msgNode = document.createElement('div');
    msgNode.className = 'msg msg-ai';
    const rendered = renderAiContent(msg.content);

    msgNode.innerHTML = `
      <div class="msg-avatar ai-av">AI</div>
      <div class="msg-bubble" style="max-width:calc(100% - 56px);">
        <div class="msg-bubble-meta">
          <span class="msg-protocol-badge">Chatty</span>
          <span class="msg-timestamp">${formatTime(msg.ts)}</span>
          ${getActionHtml(msg)}
        </div>
        ${rendered}
      </div>
    `;
    conv.appendChild(msgNode);
    conv.scrollTop = conv.scrollHeight;
  }

  function renderAiContent(text) {
    let html = escHtml(text);
    html = html.replace(/```([\s\S]*?)```/g, function(m, p1) {
        return '<pre style="background:var(--bg);padding:10px;border-radius:var(--radius);overflow-x:auto;"><code>' + p1 + '</code></pre>';
    });
    html = html.replace(/`([^`\n]+)`/g, '<code style="background:var(--bg);padding:2px 4px;border-radius:4px;">$1</code>');
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong style="color:var(--text-bright)">$1</strong>');
    html = html.replace(/^(#{1,6})\s+(.+)$/gm, '<div style="font-family:var(--mono);color:var(--violet);margin:10px 0 6px;font-weight:bold;">$2</div>');
    html = html.replace(/^[•\-]\s+(.+)$/gm, '<div style="margin-left: 10px; border-left: 2px solid var(--violet); padding-left: 8px;">$1</div>');
    
    let parts = html.split(/(<pre[\s\S]*?<\/pre>)/);
    for(let i=0; i<parts.length; i++) {
        if(!parts[i].startsWith('<pre')) {
            parts[i] = parts[i].replace(/\n/g, '<br>');
        }
    }
    return `<div class="ai-section-body">${parts.join('')}</div>`;
  }

  function addLoadingBubble() {
    if (document.querySelector('.msg-loading')) return document.querySelector('.msg-loading');
    const conv = document.getElementById('conversation');
    const msgNode = document.createElement('div');
    msgNode.className = 'msg msg-ai msg-loading';
    msgNode.innerHTML = `
      <div class="msg-avatar ai-av">AI</div>
      <div class="msg-bubble">
        <div class="typing-dots"><span></span><span></span><span></span></div>
      </div>`;
    conv.appendChild(msgNode);
    conv.scrollTop = conv.scrollHeight;
    return msgNode;
  }

  // ── Chat Sessions ────────────────────────────────────────────────────
  async function loadChatSessions(page) {
    page = page || _chatSessionsPg.page || 1;
    try {
      const data = await api({ action: 'list_chat_sessions', page, limit: _chatSessionsPg.limit }, 'GET');
      _chatSessions = data.chat_sessions || [];
      _chatSessionsPg = { page: data.page || page, limit: data.limit || _chatSessionsPg.limit, total_pages: data.total_pages || 1 };
      renderChatSessionsList();
    } catch (e) {
      toast('Error: ' + e.message, 'error');
    }
  }

  function renderChatSessionsList() {
    const list = document.getElementById('chatSessionsList');
    if (_chatSessions.length === 0) {
      list.innerHTML = `<div class="empty-state"><div class="empty-icon">💬</div>No past chats</div>`;
      renderPager('chatSessionsList', _chatSessionsPg);
      return;
    }
    list.innerHTML = _chatSessions.map(c => `
      <div class="delta-item ${c.id === _chatSessionId ? 'active-chat' : ''}" onclick="Chatty.loadChat('${c.id}')">
        <div class="delta-date">${new Date(c.updated_at).toLocaleDateString()}</div>
        <div class="delta-topic">${escHtml(c.title || 'Chat')}</div>
        <button class="item-delete-btn" style="right: 30px;" onclick="Chatty.openRenameChatModal(event, '${c.id}', '${escAttr(c.title || '')}')" title="Rename Session">
          <i class="bi bi-pencil"></i>
        </button>
        <button class="item-delete-btn" onclick="Chatty.deleteChat(event, '${c.id}')" title="Delete Session">
          <i class="bi bi-trash"></i>
        </button>
      </div>`).join('');
    renderPager('chatSessionsList', _chatSessionsPg);
  }

  function openRenameChatModal(e, id, currentTitle) {
    e.stopPropagation();
    document.getElementById('renameChatId').value = id;
    document.getElementById('renameChatTitle').value = currentTitle;
    document.getElementById('renameChatModal').classList.add('open');
  }

  async function saveRenameChat() {
    const id = document.getElementById('renameChatId').value;
    const title = document.getElementById('renameChatTitle').value.trim();
    if (!id || !title) return;
    try {
      await api({ action: 'rename_chat', session_id: id, title });
      const c = _chatSessions.find(x => x.id === id);
      if (c) c.title = title;
      if (_chatSessionId === id) _chatSessionTitle = title;
      renderChatSessionsList();
      closeModal('renameChatModal');
      toast('Session renamed', 'success');
    } catch (e) {
      toast('Error: ' + e.message, 'error');
    }
  }

  async function loadChat(id, silent = false) {
    if (!silent && id === _chatSessionId && _conversation.length > 0) return;
    try {
      const data = await api({ action: 'load_chat', session_id: id }, 'GET');
      
      if (silent && _chatSessionId === id && data.conversation) {
          if (data.conversation.length === _conversation.length) {
              return; // No new messages to refresh, prevent DOM thrashing
          }
      }
      
      _chatSessionId = id;
      const sess = _chatSessions.find(c => c.id === id);
      _chatSessionTitle = sess ? sess.title : 'Loaded Session';
      _conversation = data.conversation || [];

      if (data.settings) {
        _settings = Object.assign(_settings, data.settings);
        const sel = document.getElementById('setModel');
        if (sel && sel.options.length > 0) sel.value = _settings.model || 'openai';
        document.getElementById('setSystemPrompt').value = _settings.system_prompt || '';
      }

      redrawConversation();
      
      if (_isPolling && silent) {
          const lastMsg = _conversation[_conversation.length - 1];
          if (lastMsg && lastMsg.role === 'user') {
              addLoadingBubble(); // Re-add bubble because redrawConversation cleared it
          } else {
              clearInterval(_pollTimer);
              _pollTimer = null;
              _isPolling = false;
              _isLoading = false;
              setMainButtonLoading(false);
          }
      }

      if (_isChattyVisible) {
          markAllAsRead();
      } else {
          checkUnreadGlobal(); // pop the notification badge instantly to Sage
      }

      renderChatSessionsList();
      if (!silent) closeFlyouts();
      if (!silent) toast('Chat loaded', 'info');
      
      // Auto resume polling if loaded chat is waiting for an AI reply
      if (!silent && _conversation.length > 0) {
          const lastMsg = _conversation[_conversation.length - 1];
          if (lastMsg.role === 'user') {
              addLoadingBubble();
              _isLoading = true;
              setMainButtonLoading(true);
              startPolling(_chatSessionId, _conversation.length);
          } else {
              if (_pollTimer) clearInterval(_pollTimer);
              _isPolling = false;
              _isLoading = false;
              setMainButtonLoading(false);
          }
      }
    } catch (e) {
      if (!silent) toast('Error: ' + e.message, 'error');
    }
  }

  async function deleteChat(e, id) {
    e.stopPropagation();
    if (!confirm('Permanently delete this chat?')) return;
    try {
      await api({ action: 'delete_chat', session_id: id });
      _chatSessions = _chatSessions.filter(c => c.id !== id);
      if (_chatSessionId === id) clearConversation(true);
      else renderChatSessionsList();
      toast('Chat deleted', 'success');
    } catch (e) {
      toast('Error: ' + e.message, 'error');
    }
  }

  // ── Settings ─────────────────────────────────────────────────
  async function saveSettings() {
    _settings.model = document.getElementById('setModel').value;
    _settings.system_prompt = document.getElementById('setSystemPrompt').value.trim();
    _settings.mute_notifications = document.getElementById('setMuteNotifs').checked;
    _muteNotifs = _settings.mute_notifications;

    try {
      await api({ action: 'save_settings', chat_session_id: _chatSessionId, settings: _settings });
      toast('Settings saved', 'success');
      
      if (_muteNotifs && window.self !== window.top) {
          window.parent.postMessage({ type: 'CHATTY_UNREAD', count: 0 }, '*');
      }
    } catch (e) {
      toast('Error saving settings', 'error');
    }
  }

  // ── Conversation management ─────────────────────────────────────────
  function clearConversation(force) {
    if (!force && _conversation.length > 0 &&
        !confirm('Start a new blank chat? Your current chat will be saved in the "Chats" tab.')) return;

    if (_pollTimer) clearInterval(_pollTimer);
    _isPolling = false;
    _isLoading = false;
    setMainButtonLoading(false);

    _conversation = [];
    _chatSessionId = generateUUID();
    _chatSessionTitle = 'New Chat';
    redrawConversation();
    setLeftTab('chats');
    renderChatSessionsList();
    toast('New blank chat started', 'info');
  }

  // ── Flyouts ──────────────────────────────────────────────────────────
  function toggleFlyout(side) {
    const openingLeft  = side === 'left';
    const leftEl       = document.getElementById('flyoutLeft');
    const backdrop     = document.getElementById('flyoutBackdrop');
    const hLeft        = document.getElementById('hamburgerLeft');

    const leftOpen  = leftEl.classList.contains('open');

    if (openingLeft && leftOpen) { closeFlyouts(); return; }

    leftEl.classList.remove('open');
    hLeft.classList.remove('open');

    if (openingLeft)  { leftEl.classList.add('open');  hLeft.classList.add('open'); }
    backdrop.classList.add('visible');
  }

  function closeFlyouts() {
    document.getElementById('flyoutLeft').classList.remove('open');
    document.getElementById('flyoutBackdrop').classList.remove('visible');
    document.getElementById('hamburgerLeft').classList.remove('open');
  }

  function setLeftTab(tab) {
    document.querySelectorAll('.left-tab').forEach(t => t.classList.toggle('active', t.dataset.ltab === tab));
    document.querySelectorAll('.left-tab-content').forEach(c => c.classList.toggle('active', c.id === `ltab-${tab}`));
  }

  // ── Utilities ────────────────────────────────────────────────────────
  function copyMsg(btn) {
    const text = btn.getAttribute('data-content');
    navigator.clipboard?.writeText(text).then(() => toast('Copied', 'success', 1500)).catch(() => {});
  }

  function toggleTheme() {
    const html = document.documentElement;
    const curr = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', curr);
    try { localStorage.setItem('spw_theme', curr); } catch(e) {}
    document.getElementById('themeBtn').querySelector('i').className = curr === 'dark' ? 'bi bi-moon-stars' : 'bi bi-sun';
  }

  function closeModal(id) { document.getElementById(id).classList.remove('open'); }

  function toast(msg, type, duration) {
    type = type || 'info';
    duration = duration || 2800;
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    const icons = { success: '✓', error: '✕', info: '✦' };
    el.innerHTML = `<span>${icons[type]||'💬'}</span> ${escHtml(msg)}`;
    el.onclick = () => { el.classList.add('out'); setTimeout(()=>el.remove(), 250); };
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => { el.classList.add('out'); setTimeout(()=>el.remove(), 250); }, duration);
  }

  function formatTime(ts) { return new Date(ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
  function escHtml(s) { if (s === null || s === undefined) return ''; const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }
  function escAttr(s) { return escHtml(s).replace(/"/g, '&quot;'); }
  function autoResizeTextarea(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 200) + 'px'; }

  // ── Init ─────────────────────────────────────────────────────────────
  async function init() {
    await populateModelSelect();
      
    // Iframe Visibility Setup
    window.addEventListener('message', e => {
        if (!e.data) return;
        if (e.data.type === 'SAGE_TAB_VISIBLE') {
            _isChattyVisible = true;
            markAllAsRead();
        } else if (e.data.type === 'SAGE_TAB_HIDDEN') {
            _isChattyVisible = false;
        }
    });

    if (window.self !== window.top) {
        window.parent.postMessage({ type: 'CHATTY_READY' }, '*');
    }

    try {
      const data = await api({ action: 'load_state' }, 'GET');
      if (data.success && data.state) {
        if (data.state.chat_session_id) _chatSessionId = data.state.chat_session_id;

        if (data.state.chat_sessions) {
          _chatSessions = data.state.chat_sessions;
          const cur = _chatSessions.find(c => c.id === _chatSessionId);
          if (cur) _chatSessionTitle = cur.title;
        }

        if (data.state.settings) {
          _settings = Object.assign(_settings, data.state.settings);
          const sel = document.getElementById('setModel');
          if (sel && sel.options.length > 0) sel.value = _settings.model || 'openai';
          document.getElementById('setSystemPrompt').value = _settings.system_prompt || '';
          
          const muteChk = document.getElementById('setMuteNotifs');
          if (muteChk) muteChk.checked = _settings.mute_notifications;
          _muteNotifs = _settings.mute_notifications;
        }

        if (data.state.conversation && data.state.conversation.length > 0) {
          _conversation = data.state.conversation;
          redrawConversation();
          
          // Resume polling if the browser was closed mid-generation
          const lastMsg = _conversation[_conversation.length - 1];
          if (lastMsg.role === 'user') {
              addLoadingBubble();
              _isLoading = true;
              setMainButtonLoading(true);
              startPolling(_chatSessionId, _conversation.length);
          }
        }
      }
      await loadChatSessions(1);
    } catch(e) { console.error('Chatty Load Error:', e); }

    bindPager('chatSessionsList', p => loadChatSessions(p));

    document.querySelectorAll('.left-tab').forEach(btn => {
      btn.addEventListener('click', () => setLeftTab(btn.dataset.ltab));
    });

    const msgInput = document.getElementById('msgInput');
    msgInput.addEventListener('input', () => autoResizeTextarea(msgInput));
    
    // Allow 'Enter' to create new lines naturally.
    // Only intercept Ctrl+Enter (or Cmd+Enter on Mac) as a quick-send shortcut for desktop users.
    msgInput.addEventListener('keydown', e => {
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { 
          e.preventDefault(); 
          sendMessage(); 
      }
    });
      
    document.getElementById('fileInput').addEventListener('change', (e) => {
        for (let f of e.target.files) attachFile(f);
        e.target.value = '';
    });

    document.querySelectorAll('.wi-modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        closeFlyouts();
        document.querySelectorAll('.wi-modal-overlay.open').forEach(m => m.classList.remove('open'));
      }
    });

    const theme = document.documentElement.getAttribute('data-theme');
    document.getElementById('themeBtn').querySelector('i').className = theme === 'light' ? 'bi bi-sun' : 'bi bi-moon-stars';
    
    
  }

  return {
    init, sendMessage, toggleFlyout, closeFlyouts,
    saveSettings, clearConversation,
    loadChat, deleteChat, renderChatSessionsList,
    copyMsg, toggleTheme, closeModal, toast,
    openRenameChatModal, saveRenameChat,
    removeFile, downloadChat, branchChat, deleteMsg, manualMarkAllAsRead
  };
})();

document.addEventListener('DOMContentLoaded', function() {
  if (typeof window.Chatty !== 'undefined' && window.Chatty.init) {
    window.Chatty.init();
  }
});
</script>
</body>
</html>