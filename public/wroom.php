<?php
// public/wroom.php
// ─────────────────────────────────────────────────────────────────────────────
// WRITERS ROOM FORGE
// Narrative architecture AI for The Anima Chronicles
// ─────────────────────────────────────────────────────────────────────────────
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
<title>Writers Room Forge — The Anima Chronicles</title>
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
   WRITERS ROOM FORGE — Design System
   Aesthetic: Industrial Alchemist (matching Generator Forge)
   Accent: Amber (#f5a623) — same system, different soul
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
  --amber-dim:    rgba(245,166,35,0.08);
  --amber-mid:    rgba(245,166,35,0.15);
  --amber-glow:   rgba(245,166,35,0.4);
  --green:        #22d3a0;
  --green-dim:    rgba(34,211,160,0.1);
  --red:          #f05060;
  --red-dim:      rgba(240,80,96,0.1);
  --blue:         #4da6ff;
  --blue-dim:     rgba(77,166,255,0.1);
  --violet:       #a78bfa;
  --violet-dim:   rgba(167,139,250,0.1);
  --mono:         'Space Mono', 'Fira Mono', monospace;
  --sans:         'Syne', system-ui, sans-serif;
  --radius:       6px;
  --radius-lg:    10px;
}

:root[data-theme="light"], html[data-theme="light"] {
  --bg:           #f6f8fa;
  --surface:      #e1e4e8;
  --card:         #ffffff;
  --card-hover:   #f3f4f6;
  --border:       #d1d5db;
  --border-glow:  #9ca3af;
  --text:         #111827;
  --text-dim:     #4b5563;
  --text-bright:  #000000;
  --amber:        #d97706;
  --amber-dim:    rgba(217,119,6,0.1);
  --amber-mid:    rgba(217,119,6,0.2);
  --amber-glow:   rgba(217,119,6,0.4);
  --green:        #059669;
  --green-dim:    rgba(5,150,105,0.1);
  --red:          #dc2626;
  --red-dim:      rgba(220,38,38,0.1);
  --blue:         #2563eb;
  --blue-dim:     rgba(37,99,235,0.1);
  --violet:       #7c3aed;
  --violet-dim:   rgba(124,58,237,0.1);
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

/* ── LAYOUT — full viewport, single column, sidebars are flyouts ─────────── */
.wr-layout {
  display: flex;
  flex-direction: column;
  height: 100vh; height: 100dvh;
  overflow: hidden;
}

/* ── HEADER ─────────────────────────────────────────────────────────────── */
.wr-header {
  height: 52px;
  flex-shrink: 0;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 16px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  position: relative; z-index: 100;
}
.wr-logo {
  display: flex; align-items: center; gap: 10px;
  font-family: var(--mono); font-size: 0.82rem; font-weight: 700;
  color: var(--amber); letter-spacing: 2px; text-transform: uppercase;
}
.wr-logo-icon {
  width: 28px; height: 28px; background: var(--amber-mid);
  border: 1px solid var(--amber-glow); border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.wr-logo-sub {
  font-size: 0.6rem; color: var(--text-dim); letter-spacing: 1px;
  font-weight: 400; margin-left: 4px; font-style: italic;
}
.wr-header-center {
  display: flex; align-items: center; gap: 8px;
}
.wr-header-right {
  display: flex; align-items: center; gap: 8px;
}
.phase-indicator {
  font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim);
  padding: 4px 10px; background: var(--card);
  border: 1px solid var(--border); border-radius: var(--radius);
  display: flex; align-items: center; gap: 6px;
}
.phase-indicator .phase-dot {
  width: 6px; height: 6px; border-radius: 50%; background: var(--green);
  animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot {
  0%,100% { opacity: 1; }
  50% { opacity: 0.3; }
}

.btn-icon-sm {
  width: 32px; height: 32px; border-radius: var(--radius);
  border: 1px solid var(--border); background: var(--card);
  color: var(--text-dim); cursor: pointer; transition: all 0.15s;
  display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.btn-icon-sm:hover { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }
.btn-icon-sm.active { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }
.btn-icon-danger:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }

/* ── FLYOUT SIDEBARS ────────────────────────────────────────────────────── */
/* Shared flyout base */
.wr-flyout {
  position: fixed;
  top: 52px; /* below header */
  bottom: 0;
  width: min(300px, 88vw);
  background: var(--surface);
  border-color: var(--border);
  z-index: 500;
  display: flex; flex-direction: column;
  overflow: hidden;
  transition: transform 0.28s cubic-bezier(0.25, 1, 0.5, 1);
  box-shadow: 0 0 40px rgba(0,0,0,0.6);
}
.wr-flyout-left {
  left: 0;
  border-right: 1px solid var(--border);
  transform: translateX(-100%);
}
.wr-flyout-right {
  right: 0;
  border-left: 1px solid var(--border);
  transform: translateX(100%);
}
.wr-flyout.open {
  transform: translateX(0);
}

/* Flyout overlay backdrop */
.wr-flyout-backdrop {
  position: fixed; inset: 52px 0 0 0;
  background: rgba(0,0,0,0.45);
  z-index: 499;
  display: none;
  backdrop-filter: blur(2px);
}
.wr-flyout-backdrop.visible { display: block; }

/* Hamburger buttons — left and right in header */
.wr-hamburger {
  display: flex; align-items: center; justify-content: center;
  width: 36px; height: 36px; border-radius: var(--radius);
  border: 1px solid var(--border); background: var(--card);
  color: var(--text-dim); cursor: pointer; transition: all 0.15s;
  flex-shrink: 0; font-size: 16px;
  position: relative;
}
.wr-hamburger:hover { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }
.wr-hamburger.open  { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }

/* Flyout inner structure */
.flyout-head {
  padding: 12px 14px 10px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
  display: flex; align-items: center; justify-content: space-between;
}
.flyout-head-title {
  font-family: var(--mono); font-size: 0.65rem; color: var(--amber);
  text-transform: uppercase; letter-spacing: 2px;
}
.flyout-close {
  width: 24px; height: 24px; border-radius: 4px;
  border: 1px solid var(--border); background: transparent;
  color: var(--text-dim); cursor: pointer; font-size: 12px;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.15s;
}
.flyout-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }

/* Section tabs inside flyout panels */
.left-tabs {
  display: flex; border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.left-tab {
  flex: 1; padding: 8px 4px; font-family: var(--mono); font-size: 0.65rem;
  color: var(--text-dim); text-align: center; cursor: pointer;
  text-transform: uppercase; letter-spacing: 1px;
  border-bottom: 2px solid transparent; transition: all 0.15s;
  background: none; border-top: none; border-left: none; border-right: none;
}
.left-tab.active { color: var(--amber); border-bottom-color: var(--amber); }
.left-tab:hover:not(.active) { color: var(--text); }

.left-tab-body { flex: 1; overflow: hidden; position: relative; }
.left-tab-content { position: absolute; inset: 0; overflow-y: auto; display: none; padding: 12px; }
.left-tab-content.active { display: block; }

/* ── Context fields ─────────────────────────────────────────────────────── */
.ctx-group { margin-bottom: 12px; }
.ctx-label {
  font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim);
  text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 5px;
  display: block;
}
.ctx-textarea {
  width: 100%; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text); font-family: var(--mono);
  font-size: 0.73rem; line-height: 1.5; padding: 8px 10px; resize: vertical;
  min-height: 60px; transition: border-color 0.15s;
}
.ctx-textarea:focus { outline: none; border-color: var(--amber); }
.ctx-textarea.tall { min-height: 100px; }
.ctx-textarea.xtall { min-height: 160px; }

.ctx-input {
  width: 100%; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text); font-family: var(--mono);
  font-size: 0.73rem; padding: 7px 10px; transition: border-color 0.15s;
}
.ctx-input:focus { outline: none; border-color: var(--amber); }

.ctx-select {
  width: 100%; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text); font-family: var(--mono);
  font-size: 0.73rem; padding: 7px 10px; cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 10px center; padding-right: 28px;
}
.ctx-select:focus { outline: none; border-color: var(--amber); }

/* Thread registry pills */
.thread-list { display: flex; flex-direction: column; gap: 4px; }
.thread-item {
  display: flex; align-items: center; gap: 6px; padding: 5px 8px;
  background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); cursor: pointer; transition: all 0.15s;
  font-family: var(--mono); font-size: 0.68rem; position: relative;
}
.thread-item:hover { border-color: var(--amber); background: var(--amber-dim); }
.thread-item.selected { border-color: var(--amber); background: var(--amber-dim); color: var(--amber); }
.thread-id { color: var(--text-dim); font-size: 0.6rem; min-width: 38px; }
.thread-name { flex: 1; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.thread-type-dot {
  width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0;
}
.thread-type-dot.cosmic   { background: var(--violet); }
.thread-type-dot.civil    { background: var(--blue); }
.thread-type-dot.char     { background: var(--green); }
.thread-type-dot.theme    { background: var(--amber); }
.thread-type-dot.reveal   { background: var(--red); }

/* New Edit Button for Thread Pills */
.thread-edit-btn {
  background: transparent; border: none; color: var(--text-dim);
  cursor: pointer; padding: 2px 5px; font-size: 11px;
  border-radius: 3px; transition: all 0.15s; display: flex; align-items: center; justify-content: center;
}
.thread-edit-btn:hover { color: var(--amber); background: rgba(245,166,35,0.15); }

.thread-filter-row {
  display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 8px;
}
.tfilter {
  padding: 2px 7px; border-radius: 10px; font-family: var(--mono);
  font-size: 0.6rem; border: 1px solid var(--border); background: transparent;
  color: var(--text-dim); cursor: pointer; transition: all 0.12s;
}
.tfilter.active { background: var(--amber-dim); border-color: var(--amber); color: var(--amber); }

.thread-search {
  width: 100%; margin-bottom: 8px; background: var(--card);
  border: 1px solid var(--border); border-radius: var(--radius);
  color: var(--text); font-family: var(--mono); font-size: 0.72rem;
  padding: 6px 10px; transition: border-color 0.15s;
}
.thread-search:focus { outline: none; border-color: var(--amber); }

/* Session delta display */
.delta-list { display: flex; flex-direction: column; gap: 4px; }
.delta-item {
  padding: 6px 10px; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); cursor: pointer; transition: all 0.15s;
  display: flex; flex-direction: column; position: relative;
}
.delta-item:hover { border-color: var(--amber); }
.delta-item.active-chat { border-color: var(--amber); background: var(--amber-dim); }
.delta-date { font-family: var(--mono); font-size: 0.6rem; color: var(--amber); }
.delta-topic { font-family: var(--mono); font-size: 0.72rem; color: var(--text); margin-top: 2px; padding-right: 24px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.delta-decisions { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); margin-top: 2px; }

.item-delete-btn {
  position: absolute; right: 8px; top: 8px; width: 22px; height: 22px;
  border-radius: 4px; border: 1px solid transparent; background: transparent;
  color: var(--text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: all 0.15s; font-size: 11px;
}
.item-delete-btn:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }

/* ── MAIN AREA ──────────────────────────────────────────────────────────── */
.wr-main {
  flex: 1;
  display: flex; flex-direction: column;
  overflow: hidden; background: var(--bg);
  min-height: 0;
}

/* Conversation viewport */
.wr-conversation {
  flex: 1; overflow-y: auto; padding: 20px;
  display: flex; flex-direction: column; gap: 16px;
}

/* Welcome state */
.wr-welcome {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  height: 100%; text-align: center; gap: 20px; padding: 40px;
}
.wr-welcome-sigil {
  font-size: 52px; opacity: 0.15; line-height: 1;
  font-family: var(--mono);
}
.wr-welcome-title {
  font-family: var(--mono); font-size: 1rem; color: var(--amber);
  text-transform: uppercase; letter-spacing: 3px;
}
.wr-welcome-body {
  font-size: 0.85rem; color: var(--text-dim); line-height: 1.7;
  max-width: 480px;
}
.wr-welcome-protocols {
  display: flex; flex-wrap: wrap; gap: 6px; justify-content: center;
  max-width: 500px;
}
.proto-pill {
  padding: 4px 10px; border-radius: 20px; border: 1px solid var(--border);
  font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim);
  cursor: pointer; transition: all 0.15s;
}
.proto-pill:hover { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }

/* Message bubbles */
.msg {
  display: flex; gap: 12px; animation: msgIn 0.25s ease;
}
@keyframes msgIn {
  from { opacity: 0; transform: translateY(6px); }
  to   { opacity: 1; transform: translateY(0); }
}
.msg-user { flex-direction: row-reverse; }

.msg-avatar {
  width: 32px; height: 32px; border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; flex-shrink: 0; font-family: var(--mono);
}
.msg-avatar.user-av {
  background: var(--amber-dim); border: 1px solid var(--amber-glow);
  color: var(--amber);
}
.msg-avatar.ai-av {
  background: var(--blue-dim); border: 1px solid var(--blue);
  color: var(--blue);
}

.msg-bubble {
  max-width: calc(100% - 56px); padding: 12px 16px;
  border-radius: var(--radius-lg); font-size: 0.85rem; line-height: 1.7;
}
.msg-user .msg-bubble {
  background: var(--amber-dim); border: 1px solid var(--amber-glow);
  color: var(--text-bright); border-top-right-radius: 3px;
}
.msg-ai .msg-bubble {
  background: var(--card); border: 1px solid var(--border);
  color: var(--text); border-top-left-radius: 3px;
}
.msg-bubble-meta {
  display: flex; gap: 8px; align-items: center; margin-bottom: 6px; flex-wrap: wrap;
}
.msg-protocol-badge {
  font-family: var(--mono); font-size: 0.6rem; padding: 1px 6px;
  border-radius: 3px; background: var(--violet-dim); border: 1px solid var(--violet);
  color: var(--violet); text-transform: uppercase; letter-spacing: 1px;
}
.msg-timestamp {
  font-family: var(--mono); font-size: 0.6rem; color: var(--text-dim);
}

/* AI response sections */
.ai-section { margin-bottom: 14px; }
.ai-section-label {
  font-family: var(--mono); font-size: 0.6rem; color: var(--amber);
  text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 6px;
  padding-bottom: 4px; border-bottom: 1px solid var(--border);
}
.ai-section-body {
  font-size: 0.84rem; color: var(--text); line-height: 1.75;
}
.ai-thread-ref {
  display: inline; font-family: var(--mono); font-size: 0.72rem;
  color: var(--amber); background: var(--amber-dim);
  padding: 1px 5px; border-radius: 3px; margin: 0 2px;
  border: 1px solid var(--amber-mid);
  cursor: pointer;
}
.ai-thread-ref:hover {
  border-color: var(--amber); background: var(--amber-glow);
}
.ai-tension {
  display: block; padding: 6px 10px; margin: 4px 0;
  background: var(--surface); border-left: 2px solid var(--red);
  font-family: var(--mono); font-size: 0.73rem; color: var(--text);
  border-radius: 0 var(--radius) var(--radius) 0;
}
.ai-verdict {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 12px; border-radius: 20px; font-family: var(--mono);
  font-size: 0.72rem; font-weight: 700; letter-spacing: 1px;
}
.ai-verdict.survives { background: var(--green-dim); border: 1px solid var(--green); color: var(--green); }
.ai-verdict.survives-costs { background: var(--amber-dim); border: 1px solid var(--amber); color: var(--amber); }
.ai-verdict.fails { background: var(--red-dim); border: 1px solid var(--red); color: var(--red); }

/* Loading bubble */
.msg-loading .msg-bubble {
  display: flex; align-items: center; gap: 10px; padding: 14px 16px;
}
.typing-dots span {
  display: inline-block; width: 6px; height: 6px; border-radius: 50%;
  background: var(--blue); margin: 0 2px;
  animation: typing 1.2s ease-in-out infinite;
}
.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing {
  0%,80%,100% { transform: translateY(0); opacity: 0.4; }
  40% { transform: translateY(-6px); opacity: 1; }
}

/* ── INPUT BAR ──────────────────────────────────────────────────────────── */
.wr-input-bar {
  padding: 12px 16px; border-top: 1px solid var(--border);
  background: var(--surface); flex-shrink: 0;
}
.input-bar-top {
  display: flex; gap: 8px; align-items: center; margin-bottom: 8px;
  flex-wrap: wrap;
}
.protocol-selector {
  display: flex; gap: 4px; flex-wrap: wrap; width: 100%;
}
.proto-btn {
  padding: 3px 8px; border-radius: 20px; border: 1px solid var(--border);
  font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim);
  cursor: pointer; background: transparent; transition: all 0.12s;
  letter-spacing: 0.5px;
}
.proto-btn:hover { border-color: var(--violet); color: var(--violet); background: var(--violet-dim); }
.proto-btn.active { border-color: var(--violet); color: var(--violet); background: var(--violet-dim); }
.proto-btn.auto { border-color: var(--border-glow); color: var(--text-dim); }

.input-bar-bottom {
  display: flex; gap: 8px; align-items: flex-end;
}
.wr-textarea {
  flex: 1; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text-bright);
  font-family: var(--sans); font-size: 0.88rem; padding: 10px 14px;
  resize: none; min-height: 44px; max-height: 200px;
  transition: border-color 0.15s; line-height: 1.5; overflow-y: auto;
}
.wr-textarea:focus { outline: none; border-color: var(--amber); }
.wr-textarea::placeholder { color: var(--text-dim); }

.btn-send {
  width: 44px; height: 44px; border-radius: var(--radius);
  background: var(--amber); color: #000; border: none;
  cursor: pointer; transition: all 0.15s; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 16px;
}
.btn-send:hover:not(:disabled) { filter: brightness(1.15); transform: translateY(-1px); }
.btn-send:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
.btn-send .spin-sm {
  width: 16px; height: 16px; border: 2px solid rgba(0,0,0,0.3);
  border-top-color: #000; border-radius: 50%;
  animation: spin 0.7s linear infinite; display: none;
}
.btn-send.loading i { display: none; }
.btn-send.loading .spin-sm { display: block; }

/* ── RIGHT PANEL inner (lives inside wr-flyout-right) ───────────────────── */

.right-tabs {
  display: flex; border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.right-tab {
  flex: 1; padding: 10px 4px; font-family: var(--mono); font-size: 0.62rem;
  color: var(--text-dim); text-align: center; cursor: pointer;
  text-transform: uppercase; letter-spacing: 1px;
  border-bottom: 2px solid transparent; transition: all 0.15s;
  background: none; border-top: none; border-left: none; border-right: none;
}
.right-tab.active { color: var(--amber); border-bottom-color: var(--amber); }
.right-tab:hover:not(.active) { color: var(--text); }

.right-body { flex: 1; overflow: hidden; position: relative; }
.right-tab-content { position: absolute; inset: 0; overflow-y: auto; padding: 12px; display: none; }
.right-tab-content.active { display: block; }

/* Session Delta editor */
.delta-form .ctx-group { margin-bottom: 10px; }
.delta-tag-list { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.delta-tag {
  padding: 2px 7px; border-radius: 3px; font-family: var(--mono); font-size: 0.65rem;
  background: var(--card); border: 1px solid var(--border); color: var(--text-dim);
  display: flex; align-items: center; gap: 4px;
}
.delta-tag-remove { cursor: pointer; color: var(--red); font-size: 0.6rem; margin-left:2px;}
.delta-tag-remove:hover { color: var(--red); filter:brightness(1.2); }

/* Failure mode checklist */
.failure-list { display: flex; flex-direction: column; gap: 6px; }
.failure-item {
  padding: 8px 10px; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); transition: all 0.15s;
}
.failure-item.flagged { border-color: var(--red); background: var(--red-dim); }
.failure-item.clear { border-color: var(--green); }
.failure-num { font-family: var(--mono); font-size: 0.6rem; color: var(--text-dim); }
.failure-title { font-family: var(--mono); font-size: 0.72rem; color: var(--text); margin-top: 2px; }
.failure-status {
  font-family: var(--mono); font-size: 0.6rem; margin-top: 2px;
}
.failure-status.flagged { color: var(--red); }
.failure-status.clear { color: var(--green); }
.failure-status.unknown { color: var(--text-dim); }

/* Chekhov debt tracker */
.chekhov-list { display: flex; flex-direction: column; gap: 5px; }
.chekhov-item {
  padding: 7px 10px; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); cursor: pointer; transition: all 0.15s;
}
.chekhov-item.unpaid { border-left: 2px solid var(--red); }
.chekhov-item.paid   { border-left: 2px solid var(--green); opacity: 0.6; }
.chekhov-thread { font-family: var(--mono); font-size: 0.6rem; color: var(--amber); }
.chekhov-desc { font-family: var(--mono); font-size: 0.7rem; color: var(--text); margin-top: 2px; }
.chekhov-ep   { font-family: var(--mono); font-size: 0.6rem; color: var(--text-dim); margin-top: 1px; }

/* ── MODALS ─────────────────────────────────────────────────────────────── */
.wr-modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.8);
  backdrop-filter: blur(3px); z-index: 9999;
  display: none; align-items: center; justify-content: center; padding: 16px;
}
.wr-modal-overlay.open { display: flex; }
.wr-modal {
  background: var(--surface); border: 1px solid var(--border-glow);
  border-radius: var(--radius-lg); width: 100%; max-width: 700px;
  max-height: 90vh; display: flex; flex-direction: column;
  box-shadow: 0 20px 60px rgba(0,0,0,0.7);
  animation: modalIn 0.2s ease;
}
.wr-modal-header {
  padding: 16px 20px; border-bottom: 1px solid var(--border);
  display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
}
.wr-modal-title {
  font-family: var(--mono); font-size: 0.78rem; color: var(--amber);
  text-transform: uppercase; letter-spacing: 1.5px;
}
.wr-modal-close {
  width: 26px; height: 26px; border-radius: 4px; border: 1px solid var(--border);
  background: transparent; color: var(--text-dim); cursor: pointer;
  display: flex; align-items: center; justify-content: center; font-size: 13px;
  transition: all 0.15s;
}
.wr-modal-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }
.wr-modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.wr-modal-footer {
  padding: 12px 20px; border-top: 1px solid var(--border);
  display: flex; justify-content: flex-end; gap: 8px; flex-shrink: 0;
  background: var(--bg);
}

.btn-primary {
  padding: 8px 18px; background: var(--amber); color: #000;
  border: none; border-radius: var(--radius); cursor: pointer;
  font-family: var(--mono); font-size: 0.76rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 1px; transition: all 0.15s;
}
.btn-primary:hover { filter: brightness(1.1); }
.btn-secondary {
  padding: 8px 18px; background: transparent; color: var(--text-dim);
  border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer;
  font-family: var(--mono); font-size: 0.76rem; transition: all 0.15s;
}
.btn-secondary:hover { border-color: var(--border-glow); color: var(--text); }

/* ── KG Picker Tree & Semantic Slice ── */
.wr-kg-tabs {
  display: flex; border-bottom: 1px solid var(--border); padding: 0 20px; flex-shrink: 0;
}
.wr-kg-tab {
  padding: 10px 16px; font-family: var(--mono); font-size: 0.75rem; font-weight: 600;
  color: var(--text-dim); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px;
  background: none; border-top: none; border-left: none; border-right: none; transition: all 0.15s;
}
.wr-kg-tab:hover { color: var(--text); }
.wr-kg-tab.active { color: var(--amber); border-bottom-color: var(--amber); }

.wr-kg-panes { flex: 1; overflow: hidden; display: flex; flex-direction: column; min-height: 0; }
.wr-kg-pane  { display: none; flex: 1; flex-direction: column; overflow: hidden; min-height: 0; }
.wr-kg-pane.active { display: flex; }

.wr-kg-picker-header {
  display: flex; align-items: center; gap: 8px; padding: 8px 14px;
  border-bottom: 1px solid var(--border); flex-shrink: 0; background: var(--surface);
}
.wr-kg-picker-header span {
  font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.04em; color: var(--text-dim); flex: 1;
}
.wr-kg-picker-select-all {
  background: none; border: none; color: var(--amber);
  font-size: 0.75rem; cursor: pointer; padding: 0; font-weight: 600; font-family: var(--sans);
}
.wr-kg-picker-select-all:hover { text-decoration: underline; }
.wr-kg-picker-tree-wrap {
  flex: 1; overflow-y: auto; padding: 4px 0; background: var(--bg);
}
.wr-kg-tree-node {
  display: flex; align-items: center; gap: 6px; padding: 5px 10px;
  cursor: pointer; user-select: none; font-size: 0.86rem; border-radius: 4px; margin: 1px 4px;
  transition: background 0.1s;
}
.wr-kg-tree-node:hover { background: var(--amber-dim); }
.wr-kg-tree-node input[type=checkbox] {
  width: 14px; height: 14px; accent-color: var(--amber); cursor: pointer; flex-shrink: 0; margin: 0;
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
.wr-kge-query-row { display: flex; gap: 8px; padding: 12px 20px; border-bottom: 1px solid var(--border); flex-shrink: 0;}
.wr-kge-query-input {
  flex: 1; padding: 9px 12px; background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text); font-family: var(--sans); font-size: 0.85rem;
}
.wr-kge-query-input:focus { outline: none; border-color: var(--amber); }
.wr-kge-n-select {
  padding: 9px 10px; background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text); font-family: var(--sans); font-size: 0.85rem;
}
.wr-kge-hits-area { flex: 1; overflow-y: auto; min-height: 0; background: var(--bg); }
.wr-kge-hits-empty {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  height: 100%; gap: 8px; color: var(--text-dim); font-size: 0.85rem; padding: 40px 20px; text-align: center;
}
.wr-kge-hit-row {
  display: flex; align-items: flex-start; padding: 10px 14px; border-bottom: 1px solid var(--border);
  gap: 10px; cursor: pointer; transition: background 0.12s;
}
.wr-kge-hit-row:hover { background: var(--amber-dim); }
.wr-kge-hit-row.selected { background: rgba(245,166,35,0.12); }
.wr-kge-hit-check { width: 14px; height: 14px; flex-shrink: 0; margin-top: 3px; accent-color: var(--amber); cursor: pointer; }
.wr-kge-hit-body { flex: 1; min-width: 0; }
.wr-kge-hit-name { font-weight: 600; font-size: 0.85rem; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; color: var(--text); }
.wr-kge-hit-excerpt { font-size: 0.75rem; color: var(--text-dim); margin-top: 3px; line-height: 1.4; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.wr-kge-hit-score { font-size: 0.72rem; color: var(--text-dim); font-family: var(--mono); flex-shrink: 0; padding-top: 3px; }
.wr-kge-score-bar { display: inline-block; height: 3px; border-radius: 2px; background: var(--amber); opacity: 0.5; vertical-align: middle; margin-left: 4px; flex-shrink: 0; }
.wr-kge-hits-header {
  padding: 8px 14px 6px; font-size: 0.75rem; font-weight: 700; color: var(--text-dim);
  display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border);
  position: sticky; top: 0; background: var(--card); z-index: 1; font-family: var(--mono); text-transform: uppercase;
}
.wr-kge-type-pill { font-size: 0.65rem; font-weight: 700; padding: 1px 6px; border-radius: 8px; white-space: nowrap; font-family: var(--mono); border: 1px solid var(--border-glow); color: var(--text-dim); }
.wr-kge-status-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
.wr-kge-dot-filled { background: var(--green); }
.wr-kge-dot-partial { background: var(--amber); }
.wr-kge-dot-stub { background: var(--text-dim); }
.wr-kge-dot-empty { background: var(--border); }
.wr-kge-loading-bar { height: 2px; background: var(--amber); position: absolute; top: 0; left: 0; animation: wr-kge-load 1.4s ease-in-out infinite; display: none; width: 100%; z-index: 10; }
@keyframes wr-kge-load { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }

/* Thread editor modal */
.thread-form-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
}

/* ── TOAST ──────────────────────────────────────────────────────────────── */
.toast-container {
  position: fixed; bottom: 20px; right: 20px; z-index: 99999;
  display: flex; flex-direction: column; gap: 6px; pointer-events: none;
}
.toast {
  padding: 8px 14px; border-radius: var(--radius);
  background: var(--card); border: 1px solid var(--border);
  font-family: var(--mono); font-size: 0.76rem; color: var(--text);
  box-shadow: 0 4px 20px rgba(0,0,0,0.5);
  animation: toastIn 0.2s ease; pointer-events: all; cursor: pointer;
  display: flex; gap: 6px; align-items: center; max-width: 300px;
}
.toast.success { border-color: var(--green); }
.toast.error   { border-color: var(--red); color: var(--red); }
.toast.info    { border-color: var(--amber); }
.toast.out     { animation: toastOut 0.2s forwards; }

/* ── UTILITY ────────────────────────────────────────────────────────────── */
.sep { height: 1px; background: var(--border); margin: 10px 0; }
.hint { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); margin-top: 3px; }
.badge-inline {
  display: inline-flex; align-items: center;
  font-family: var(--mono); font-size: 0.62rem; padding: 1px 5px;
  border-radius: 3px; border: 1px solid;
}
.badge-cosmic { border-color: var(--violet); color: var(--violet); background: var(--violet-dim); }
.badge-civil  { border-color: var(--blue);   color: var(--blue);   background: var(--blue-dim);  }
.badge-char   { border-color: var(--green);  color: var(--green);  background: var(--green-dim); }
.badge-theme  { border-color: var(--amber);  color: var(--amber);  background: var(--amber-dim); }
.badge-reveal { border-color: var(--red);    color: var(--red);    background: var(--red-dim);   }

.empty-state {
  text-align: center; padding: 24px 12px;
  color: var(--text-dim); font-family: var(--mono); font-size: 0.75rem;
}
.empty-icon { font-size: 28px; opacity: 0.2; margin-bottom: 6px; }

/* ── KEYFRAMES ──────────────────────────────────────────────────────────── */
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes toastIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
@keyframes toastOut { to { opacity: 0; transform: translateY(8px); } }
@keyframes modalIn { from { opacity: 0; transform: scale(0.96) translateY(-8px); } to { opacity: 1; transform: none; } }
</style>
</head>
<body>

<div class="wr-layout">

<!-- ═══════════════════════════════════════════════════════════════════════
     HEADER
════════════════════════════════════════════════════════════════════════ -->
<!-- BACKDROP -->
<div class="wr-flyout-backdrop" id="flyoutBackdrop" onclick="WR.closeFlyouts()"></div>

<!-- HEADER -->
<header class="wr-header">
  <button class="wr-hamburger" id="hamburgerLeft" onclick="WR.toggleFlyout('left')" title="Context &amp; Threads">
    <i class="bi bi-layout-text-sidebar"></i>
  </button>
  <div class="wr-logo" style="flex:1; justify-content:center;">
    <div class="wr-logo-icon">◈</div>
    <span>Writers Room</span>
    <span style="display:none;" class="wr-logo-sub">Narrative Architect</span>
  </div>
  <div style="display:flex;align-items:center;gap:6px;">
    <div class="phase-indicator" id="headerPhaseWrap" style="display:none;">
      <div class="phase-dot"></div>
      <span id="headerPhase">—</span>
    </div>
    <button class="btn-icon-sm" onclick="WR.clearConversation()" title="New Session" style="width:32px;height:32px;">
      <i class="bi bi-plus-circle"></i>
    </button>
    <button class="btn-icon-sm" onclick="WR.toggleTheme()" id="themeBtn" title="Toggle Theme" style="display:none;width:32px;height:32px;">
      <i class="bi bi-moon-stars"></i>
    </button>
    <button class="wr-hamburger" id="hamburgerRight" onclick="WR.toggleFlyout('right')" title="Session Tracking">
      <i class="bi bi-journals"></i>
    </button>
  </div>
</header>

<!-- LEFT FLYOUT — Context / Threads / Deltas / Chats -->
<div class="wr-flyout wr-flyout-left" id="flyoutLeft">
  <div class="flyout-head">
    <span class="flyout-head-title">Context &amp; Registry</span>
    <button class="flyout-close" onclick="WR.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
  </div>
  <div style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
    <div class="left-tabs">
      <button class="left-tab active" data-ltab="context">Context</button>
      <button class="left-tab" data-ltab="threads">Threads</button>
      <button class="left-tab" data-ltab="deltas">Deltas</button>
      <button class="left-tab" data-ltab="chats">Chats</button>
    </div>
    <div class="left-tab-body">

      <!-- CONTEXT TAB -->
      <div class="left-tab-content active" id="ltab-context">
        <div class="ctx-group">
          <label class="ctx-label">Story Phase</label>
          <select class="ctx-select" id="ctxPhase">
            <option value="S1">Season 1 — The Wake (E1–12)</option>
            <option value="S2">Season 2 — The Depths (E13–24)</option>
            <option value="S3" selected>Season 3 — The Ascent (E25–36)</option>
            <option value="S4">Season 4 — The War (E37–48)</option>
            <option value="S5">Season 5 — The Horizon (E49–60)</option>
          </select>
        </div>
        <div class="ctx-group">
          <label class="ctx-label">Current Status</label>
          <textarea class="ctx-textarea tall" id="ctxStatus"
            placeholder="What has happened so far. What pressure is building…"></textarea>
        </div>
        <div class="ctx-group">
          <label class="ctx-label">Today's Focus Question</label>
          <textarea class="ctx-textarea" id="ctxFocus"
            placeholder="The specific thread, decision, or coherence question…"></textarea>
        </div>
        <div class="ctx-group">
          <label class="ctx-label">Registry Version</label>
          <input class="ctx-input" type="text" id="ctxRegistryVer" value="v0.1" placeholder="v0.1">
        </div>
        <div class="sep"></div>
        <div class="ctx-group">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
            <label class="ctx-label" style="margin-bottom:0;">Additional Context / Notes</label>
            <button class="btn-icon-sm" style="width:24px;height:24px;font-size:12px;" onclick="WR.openKgPickerModal()" title="Select KG Nodes">
              <i class="bi bi-diagram-3"></i>
            </button>
          </div>
          <textarea class="ctx-textarea xtall" id="ctxExtra"
            placeholder="Paste KG node content, episode notes, character details…"></textarea>
        </div>
        <div class="sep"></div>
        <button class="btn-primary" style="width:100%;font-size:0.72rem;" onclick="WR.openSessionModal()">
          <i class="bi bi-sliders"></i> Session Settings
        </button>
      </div>

      <!-- THREADS TAB -->
      <div class="left-tab-content" id="ltab-threads">
        <input type="text" class="thread-search" id="threadSearch" placeholder="Search threads…">
        <div class="thread-filter-row" id="threadFilters">
          <button class="tfilter active" data-ttype="">All</button>
          <button class="tfilter" data-ttype="cosmic">Cosmic</button>
          <button class="tfilter" data-ttype="civil">Civil</button>
          <button class="tfilter" data-ttype="char">Char</button>
          <button class="tfilter" data-ttype="theme">Theme</button>
          <button class="tfilter" data-ttype="reveal">Reveal</button>
        </div>
        <div class="thread-list" id="threadList"></div>
        <div style="margin-top:10px;">
          <button class="btn-secondary" style="width:100%;font-size:0.7rem;" onclick="WR.openThreadModal()">
            <i class="bi bi-plus-lg"></i> Add Thread
          </button>
        </div>
      </div>

      <!-- DELTAS TAB -->
      <div class="left-tab-content" id="ltab-deltas">
        <div class="delta-list" id="deltaList">
          <div class="empty-state"><div class="empty-icon">◈</div>No session deltas yet</div>
        </div>
      </div>

      <!-- CHATS TAB -->
      <div class="left-tab-content" id="ltab-chats">
        <div class="delta-list" id="chatSessionsList">
          <div class="empty-state"><div class="empty-icon">◈</div>No past chats</div>
        </div>
      </div>

    </div><!-- /left-tab-body -->
  </div>
</div><!-- /flyoutLeft -->

<!-- RIGHT FLYOUT — Session Delta / Failure Modes / Chekhov -->
<div class="wr-flyout wr-flyout-right" id="flyoutRight">
  <div class="flyout-head">
    <span class="flyout-head-title">Session Tracking</span>
    <button class="flyout-close" onclick="WR.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
  </div>
  <div style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
    <div class="right-tabs">
      <button class="right-tab active" data-rtab="delta">Delta</button>
      <button class="right-tab" data-rtab="failures">Failures</button>
      <button class="right-tab" data-rtab="chekhov">Chekhov</button>
    </div>
    <div class="right-body">

      <!-- SESSION DELTA TAB -->
      <div class="right-tab-content active" id="rtab-delta">
        <div style="font-family:var(--mono);font-size:0.62rem;color:var(--amber);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:10px;">
          Current Session Delta
        </div>
        <div class="delta-form">
          <div class="ctx-group">
            <label class="ctx-label">Decisions Made</label>
            <div class="delta-tag-list" id="deltaDecisions"></div>
            <input class="ctx-input" type="text" id="deltaDecisionInput"
              placeholder="Add decision… (Enter)" style="margin-top:5px;font-size:0.72rem;">
          </div>
          <div class="ctx-group">
            <label class="ctx-label">Decisions Deferred</label>
            <div class="delta-tag-list" id="deltaDeferred"></div>
            <input class="ctx-input" type="text" id="deltaDeferredInput"
              placeholder="Add deferred… (Enter)" style="margin-top:5px;font-size:0.72rem;">
          </div>
          <div class="ctx-group">
            <label class="ctx-label">Threads Touched / Proposed</label>
            <div class="delta-tag-list" id="deltaThreads"></div>
          </div>
          <div class="ctx-group">
            <label class="ctx-label">Registry Updates Needed</label>
            <textarea class="ctx-textarea" id="deltaUpdates"
              placeholder="Edits needed in Thread Registry…" style="min-height:60px;"></textarea>
          </div>
          <div class="sep"></div>
          <button class="btn-primary" style="width:100%;font-size:0.72rem;" onclick="WR.saveDelta()">
            <i class="bi bi-floppy"></i> Save Session Delta
          </button>
        </div>
      </div>

      <!-- FAILURE MODES TAB -->
      <div class="right-tab-content" id="rtab-failures">
        <div style="font-family:var(--mono);font-size:0.62rem;color:var(--amber);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:10px;">
          Failure-Mode Checklist
        </div>
        <div class="failure-list" id="failureList"></div>
      </div>

      <!-- CHEKHOV DEBT TAB -->
      <div class="right-tab-content" id="rtab-chekhov">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
          <div style="font-family:var(--mono);font-size:0.62rem;color:var(--amber);letter-spacing:1.5px;text-transform:uppercase;">
            Chekhov Debts
          </div>
          <button class="btn-icon-sm" style="width:24px;height:24px;font-size:12px;" onclick="WR.addChekhovDebt()">
            <i class="bi bi-plus-lg"></i>
          </button>
        </div>
        <div class="chekhov-list" id="chekhovList"></div>
      </div>

    </div><!-- /right-body -->
  </div>
</div><!-- /flyoutRight -->

<!-- MAIN — Conversation (always full width) -->
<main class="wr-main">
  <div class="wr-conversation" id="conversation">
    <div class="wr-welcome" id="welcomeState">
      <div class="wr-welcome-sigil">◈</div>
      <div class="wr-welcome-title">Writers Room Forge</div>
      <div class="wr-welcome-body">
        A narrative architect for <em>The Anima Chronicles</em>. Not a generator —
        a thinking partner for story structure, thread tracing, consequence mapping,
        and tension auditing across 60 episodes.
      </div>
      <div class="wr-welcome-protocols">
        <button class="proto-pill" onclick="WR.quickProtocol('A')">◈ A — Trace Thread</button>
        <button class="proto-pill" onclick="WR.quickProtocol('B')">◈ B — Map Consequences</button>
        <button class="proto-pill" onclick="WR.quickProtocol('C')">◈ C — Test Coherence</button>
        <button class="proto-pill" onclick="WR.quickProtocol('D')">◈ D — Structural Options</button>
        <button class="proto-pill" onclick="WR.quickProtocol('E')">◈ E — Tension Audit</button>
        <button class="proto-pill" onclick="WR.quickProtocol('F')">◈ F — Stress-Test Decision</button>
      </div>
    </div>
  </div>

  <!-- INPUT BAR -->
  <div class="wr-input-bar">
    <div class="input-bar-top">
      <div class="protocol-selector" id="protocolSelector" style="flex:1;">
        <button class="proto-btn auto active" data-proto="AUTO" onclick="WR.setProtocol('AUTO')">AUTO</button>
        <button class="proto-btn" data-proto="A" onclick="WR.setProtocol('A')">A·Trace</button>
        <button class="proto-btn" data-proto="B" onclick="WR.setProtocol('B')">B·Consequence</button>
        <button class="proto-btn" data-proto="C" onclick="WR.setProtocol('C')">C·Coherence</button>
        <button class="proto-btn" data-proto="D" onclick="WR.setProtocol('D')">D·Options</button>
        <button class="proto-btn" data-proto="E" onclick="WR.setProtocol('E')">E·Tension</button>
        <button class="proto-btn" data-proto="F" onclick="WR.setProtocol('F')">F·Stress-Test</button>
        
        <!-- NEW AUTODRAFT CHECKBOX -->
        <label style="display:flex; align-items:center; gap:4px; margin-left:8px; font-size:0.65rem; color:var(--amber); cursor:pointer; font-family:var(--mono); border: 1px solid var(--amber-glow); padding: 2px 8px; border-radius: 12px; background: var(--amber-dim);" title="Appends instructions for the AI to answer its own open questions and output THE FINAL BEAT">
          <input type="checkbox" id="chkAutoDraft" onchange="WR.toggleAutoDraftState(this.checked)" style="accent-color:var(--amber); margin:0; width:12px; height:12px;"> ⚡ Auto-Draft
        </label>
        
        <button class="proto-btn" id="btnModeToggle" onclick="WR.toggleOfflineMode()" style="margin-left:auto; border-color:var(--amber); color:var(--amber);">
          <i class="bi bi-cloud-check"></i> API Mode
        </button>
      </div>
    </div>
    <div class="input-bar-bottom">
      <textarea class="wr-textarea" id="msgInput"
        placeholder="Ask about a thread, decision, or narrative problem…"
        rows="1"></textarea>
        
      <button class="btn-send" id="btnInject" onclick="WR.injectAiResponse()" style="display:none; background:var(--blue); color:#fff; font-size: 18px;" title="Inject AI Response (Paste in text area first)">
        <i class="bi bi-robot"></i>
      </button>

      <button class="btn-send" id="btnSend" onclick="WR.send()">
        <i class="bi bi-arrow-up" id="sendIcon"></i>
        <div class="spin-sm"></div>
      </button>
    </div>
  </div>
</main>


</div><!-- /wr-layout -->

<!-- ═══════════════════════════════════════════════════════════════════════
     KG PICKER MODAL (With Semantic Slice)
════════════════════════════════════════════════════════════════════════ -->
<div class="wr-modal-overlay" id="kgPickerModal">
  <div class="wr-modal" style="max-width:640px; height:85vh;">
    <div class="wr-modal-header">
      <div class="wr-modal-title"><i class="bi bi-diagram-3"></i> Select KG Context</div>
      <button class="wr-modal-close" onclick="WR.closeModal('kgPickerModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    
    <div class="wr-modal-body" style="display:flex; flex-direction:column; padding:0; overflow:hidden; position:relative;">
      <!-- Loading bar for semantic -->
      <div class="wr-kge-loading-bar" id="wrKgSemanticLoader"></div>

      <div style="padding:12px 20px; font-size:0.8rem; color:var(--text-dim); border-bottom:1px solid var(--border); background:var(--surface); flex-shrink:0;">
        Select nodes to automatically fetch their lore content and/or edges, injecting them cleanly into your active context block.
      </div>
      
      <!-- Tabs -->
      <div class="wr-kg-tabs">
        <button class="wr-kg-tab active" id="wr-kg-tab-tree" onclick="WR.kgSetTab('tree')"><i class="bi bi-diagram-3"></i> Graph Tree</button>
        <button class="wr-kg-tab" id="wr-kg-tab-semantic" onclick="WR.kgSetTab('semantic')">🧠 Semantic Slice</button>
      </div>

      <div class="wr-kg-panes">
        <!-- Pane: Tree -->
        <div class="wr-kg-pane active" id="wr-kg-pane-tree">
          <div class="wr-kg-picker-header">
              <span>Select nodes &amp; folders</span>
              <button class="wr-kg-picker-select-all" onclick="WR.kgPickerToggleAll()">Select all</button>
          </div>
          <div class="wr-kg-picker-tree-wrap" id="kgPickerTreeWrap">
              <div style="padding:20px;text-align:center;color:var(--text-dim);">Loading tree…</div>
          </div>
        </div>

        <!-- Pane: Semantic -->
        <div class="wr-kg-pane" id="wr-kg-pane-semantic">
          <div class="wr-kge-query-row">
            <input type="text" class="wr-kge-query-input" id="wrKgSemanticQuery" placeholder="Describe the lore or context you need..." onkeydown="if(event.key==='Enter') WR.kgRunSemanticQuery()">
            <select class="wr-kge-n-select" id="wrKgSemanticN">
              <option value="10">Top 10</option>
              <option value="20" selected>Top 20</option>
              <option value="35">Top 35</option>
              <option value="50">Top 50</option>
            </select>
            <button class="btn-primary" onclick="WR.kgRunSemanticQuery()" id="wrKgSemanticBtn">Search</button>
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
      <div style="padding:12px 20px; border-top:1px solid var(--border); display:flex; gap:16px; background:var(--surface); flex-shrink:0;">
          <label style="display:flex; align-items:center; gap:6px; font-size:0.8rem; cursor:pointer;">
              <input type="checkbox" id="kgOptContent"> Include lore content
          </label>
          <label style="display:flex; align-items:center; gap:6px; font-size:0.8rem; cursor:pointer;">
              <input type="checkbox" id="kgOptEdges" checked> Include edges
          </label>
      </div>
    </div>
    
    <div class="wr-modal-footer">
      <span id="kgPickerCount" style="margin-right:auto; font-size:0.8rem; color:var(--text-dim); padding-top:6px;"></span>
      <button class="btn-secondary" onclick="WR.closeModal('kgPickerModal')">Cancel</button>
      <button class="btn-primary" onclick="WR.injectKgContext()" id="btnInjectKg">
        <i class="bi bi-box-arrow-in-down"></i> Inject Context
      </button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     THREAD MODAL
════════════════════════════════════════════════════════════════════════ -->
<div class="wr-modal-overlay" id="threadModal">
  <div class="wr-modal">
    <div class="wr-modal-header">
      <div class="wr-modal-title" id="threadModalTitle">Add Thread to Registry</div>
      <button class="wr-modal-close" onclick="WR.closeModal('threadModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="wr-modal-body">
      <input type="hidden" id="editThreadIdx" value="">
      <div class="thread-form-grid">
        <div class="ctx-group">
          <label class="ctx-label">Thread ID</label>
          <input class="ctx-input" type="text" id="editThreadId" placeholder="T-001">
        </div>
        <div class="ctx-group">
          <label class="ctx-label">Type</label>
          <select class="ctx-select" id="editThreadType">
            <option value="cosmic">Cosmic</option>
            <option value="civil">Civilizational</option>
            <option value="char">Character</option>
            <option value="theme">Thematic</option>
            <option value="reveal">Revelation</option>
          </select>
        </div>
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Thread Name</label>
        <input class="ctx-input" type="text" id="editThreadName" placeholder="The Echo Ships">
      </div>
      <div class="thread-form-grid">
        <div class="ctx-group">
          <label class="ctx-label">Active Seasons</label>
          <input class="ctx-input" type="text" id="editThreadSeasons" placeholder="2,3,4,5">
        </div>
        <div class="ctx-group">
          <label class="ctx-label">Status</label>
          <select class="ctx-select" id="editThreadStatus">
            <option value="DORMANT">DORMANT</option>
            <option value="SETUP">SETUP</option>
            <option value="ACTIVE" selected>ACTIVE</option>
            <option value="ESCALATING">ESCALATING</option>
            <option value="CRISIS">CRISIS</option>
            <option value="RESOLVING">RESOLVING</option>
            <option value="CLOSED">CLOSED</option>
          </select>
        </div>
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Thematic Axis</label>
        <input class="ctx-input" type="text" id="editThreadAxis" placeholder="ASK_COSMIC, WITNESS">
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Tensions Held Open</label>
        <textarea class="ctx-textarea" id="editThreadTensions"
          placeholder="One per line. What dramatic ambiguity is this thread holding open?"></textarea>
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Open Questions</label>
        <textarea class="ctx-textarea" id="editThreadQuestions"
          placeholder="One per line. What the showrunner has not yet decided."></textarea>
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Chekhov Debts (setups that must pay off)</label>
        <textarea class="ctx-textarea" id="editThreadChekhov"
          placeholder="One per line. Setup placed + deadline episode."></textarea>
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Connections (typed edges) / Context</label>
        <textarea class="ctx-textarea tall" id="editThreadConnections"
          placeholder="MIRRORS T-002 — Singer is cosmic Ask; Echo Ships temporal Ask&#10;DEPENDS_ON T-014 — Fold needs Gate Array infrastructure&#10;FEEDS T-021 — Kai likely fold initiator"></textarea>
      </div>
    </div>
    <div class="wr-modal-footer">
      <button class="btn-secondary" id="btnDeleteThread" style="display:none; margin-right:auto;" onclick="WR.deleteThread()">
        <i class="bi bi-trash"></i> Remove
      </button>
      <button class="btn-secondary" id="btnRemoveFromDelta" style="display:none; margin-right:auto;" onclick="WR.removeDraftThread()">
        <i class="bi bi-x-circle"></i> Remove from Delta
      </button>
      <button class="btn-secondary" onclick="WR.closeModal('threadModal')">Cancel</button>
      <button class="btn-primary" onclick="WR.saveThread()">Save Thread</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     SESSION SETTINGS MODAL
════════════════════════════════════════════════════════════════════════ -->
<div class="wr-modal-overlay" id="sessionModal">
  <div class="wr-modal" style="max-width:560px;">
    <div class="wr-modal-header">
      <div class="wr-modal-title">Session Settings</div>
      <button class="wr-modal-close" onclick="WR.closeModal('sessionModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="wr-modal-body">
      <div class="ctx-group">
        <label class="ctx-label">AI Model</label>
        <div style="font-family:var(--mono); font-size:0.75rem; color:var(--text-dim); padding:8px 10px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius);">
          Managed by Generator Forge (<span style="color:var(--amber);">wroom_architect_v1</span>)
        </div>
        <div class="hint">To change the model, edit the configuration in Generator Forge.</div>
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Response Depth</label>
        <select class="ctx-select" id="sessionDepth">
          <option value="brief">Brief — key findings only</option>
          <option value="standard" selected>Standard — full protocol output</option>
          <option value="deep">Deep — maximum detail, all sub-questions</option>
        </select>
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Include Failure-Mode Check in every response</label>
        <select class="ctx-select" id="sessionAutoFailure">
          <option value="yes">Yes — always append</option>
          <option value="no" selected>No — only when Protocol F</option>
        </select>
      </div>
      <div class="sep"></div>
      <div class="ctx-group">
        <label class="ctx-label">Session Topic (for Delta)</label>
        <input class="ctx-input" type="text" id="sessionTopic" placeholder="Echo Ships + Temporal Fold architecture">
      </div>
    </div>
    <div class="wr-modal-footer">
      <button class="btn-secondary" onclick="WR.closeModal('sessionModal')">Cancel</button>
      <button class="btn-primary" onclick="WR.saveSettings()">Apply Settings</button>
    </div>
  </div>
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<!-- ═══════════════════════════════════════════════════════════════════════
     JAVASCRIPT — Writers Room Engine
════════════════════════════════════════════════════════════════════════ -->
<script>
window.WR = (function() {
  'use strict';

  // ── Helpers ───────────────────────────────────────────────────────────────
  function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  // ── State ─────────────────────────────────────────────────────────────────
  let _threads          = [];
  let _deltas           = [];
  let _chekhov          = [];
  let _conversation     = [];
  let _chatSessions     = [];
  let _chatSessionId    = generateUUID();
  let _chatSessionTitle = 'New Session';
  let _protocol         = 'AUTO';
  let _settings         = {
    depth:         'standard',
    autoFailure:   'no',
    sessionTopic:  '',
    offlineMode:   false,
    autoDraft:     false,
    kgSelectedNodes: [],
    kgWithContent: false,
    kgWithEdges: true,
    draftDelta: { decisions: [], deferred: [], threads: [], updates: '' }
  };
  let _isLoading        = false;
  
  // KG Picker State
  let _kgActiveTab      = 'tree';
  let _kgPickerRaw      = [];
  let _kgPickerChecked  = new Set();
  
  // Semantic State
  let _kgSemanticHits = [];
  let _kgSemanticSelectedIds = new Set();

  // ── Failure modes (static) ────────────────────────────────────────────────
  const FAILURE_MODES = [
    { num: 1, title: 'Thematic Redundancy',    desc: 'Two threads carry same axis at same scale in same window' },
    { num: 2, title: 'Sagging Middle',          desc: 'Episodes 20–35 lack escalating threads or irreversible turns' },
    { num: 3, title: 'Chekhov Overflow',        desc: 'More than 8 unpaid setups active, or 3+ in one thread' },
    { num: 4, title: 'Resolution Crowding',     desc: 'More than 2 major threads resolving in a 3-episode window' },
    { num: 5, title: 'Premature Resolution',    desc: 'Thread resolves before thematic work at all relevant scales is done' },
    { num: 6, title: 'Tension Fatigue',         desc: 'ESCALATING thread escalating 6+ episodes without register shift' },
    { num: 7, title: 'Function Drift',          desc: 'Character structural function shifted without transformation beat' },
    { num: 8, title: 'Constitution Violation',  desc: 'Rewards Force without cost, or punishes Ask without compensatory gain' },
    { num: 9, title: 'Witness Gap',             desc: 'Major event without at least one witness character present or responding' },
    { num:10, title: 'Substrate Silence',       desc: 'Force-methodology act of significance produces no substrate response' },
  ];

  // ── Context snapshot utilities ────────────────────────────────────────────

  /**
   * Serialize a context snapshot to a stable string for equality comparison.
   * Only compares the fields that matter for the AI prompt (not _selected etc.)
   */
  function serializeSnapshot(snap) {
    if (!snap) return '';
    return JSON.stringify({
      phase:   snap.phase   || '',
      status:  snap.status  || '',
      focus:   snap.focus   || '',
      regVer:  snap.regVer  || '',
      extra:   snap.extra   || '',
      threads: snap.threads ? [...snap.threads].sort() : []
    });
  }

  /**
   * Find the conversation index of the most recent user message that has
   * a real (non-ref) context_snapshot. Returns -1 if none found.
   */
  function findLastFullSnapshotIndex() {
    for (let i = _conversation.length - 1; i >= 0; i--) {
      const msg = _conversation[i];
      if (msg.role === 'user' && msg.context_snapshot && !msg.context_snapshot.ref) {
        return i;
      }
    }
    return -1;
  }

  /**
   * Resolve a context_snapshot that may be a ref marker { ref: N } back to
   * the full snapshot object it references (by conversation index N).
   * Returns null if unresolvable.
   */
  function resolveSnapshot(snapshot) {
    if (!snapshot) return null;
    if (typeof snapshot.ref === 'number') {
      const refMsg = _conversation[snapshot.ref];
      if (refMsg && refMsg.context_snapshot) {
        return resolveSnapshot(refMsg.context_snapshot); // handles chained refs safely
      }
      return null;
    }
    return snapshot;
  }

  // ── Context / API message builders ───────────────────────────────────────
  
  function buildApiMessages() {
    return _conversation.map((m, idx) => {
      if (m.role === 'assistant') return { role: m.role, content: m.content };
      
      const rawSnap = m.context_snapshot;

      // If this step has a ref marker, emit a compact note instead of the full block
      if (rawSnap && typeof rawSnap.ref === 'number') {
        const protoStr = m.protocol && m.protocol !== 'AUTO' ? `\n[PROTOCOL REQUESTED: ${m.protocol}]` : '';
        return {
          role: 'user',
          content: `[CONTEXT: unchanged from step ${rawSnap.ref}]\n\nQUERY:\n${m.content}${protoStr}`
        };
      }

      if (rawSnap) {
        const parts = [];
        if (rawSnap.phase) parts.push(`STORY PHASE: ${rawSnap.phase}`);
        if (rawSnap.regVer) parts.push(`REGISTRY VERSION: ${rawSnap.regVer}`);
        if (rawSnap.status) parts.push(`CURRENT STATUS:\n${rawSnap.status}`);
        if (rawSnap.focus)  parts.push(`SESSION FOCUS:\n${rawSnap.focus}`);
        if (rawSnap.extra)  parts.push(`ADDITIONAL CONTEXT:\n${rawSnap.extra}`);
        
        const protoStr = m.protocol && m.protocol !== 'AUTO' ? `\n[PROTOCOL REQUESTED: ${m.protocol}]` : '';
        const threadCtx = rawSnap.threads && rawSnap.threads.length > 0 ? `\n[THREADS IN FOCUS: ${rawSnap.threads.join(', ')}]` : '';
        
        parts.push(`QUERY:\n${m.content}${protoStr}${threadCtx}`);
        return { role: 'user', content: parts.join('\n\n') };
      } else {
        // Fallback for old messages without any snapshot
        const protoStr = m.protocol && m.protocol !== 'AUTO' ? `\n[PROTOCOL REQUESTED: ${m.protocol}]` : '';
        return { role: 'user', content: `QUERY:\n${m.content}${protoStr}` };
      }
    });
  }

  // ── Send message ──────────────────────────────────────────────────────────
  async function send() {
    if (_isLoading) return;
    const input = document.getElementById('msgInput');
    let query = input.value.trim();
    if (!query) return;

    if (_settings.autoDraft) {
        query += "\n\n[EXECUTION OVERRIDE]: Based on the structural analysis above (or the context provided), step into the role of the Showrunner. Resolve any OPEN QUESTIONS with the most dramatically compelling, canon-compliant choices. Finally, output 'THE FINAL BEAT'—a highly polished, sensory 3-5 sentence narrative description of the scene, ready to be injected into the master outline.";
    }

    _isLoading = true;
    input.value = '';
    autoResizeTextarea(input);

    document.getElementById('welcomeState').style.display = 'none';

    if (_conversation.length === 0) {
      _chatSessionTitle = query.length > 40 ? query.substring(0, 40) + '...' : query;
      if (!_chatSessions.find(c => c.id === _chatSessionId)) {
        _chatSessions.unshift({ id: _chatSessionId, title: _chatSessionTitle, updated_at: new Date().toISOString() });
        renderChatSessionsList();
      }
    }

    // Build the current context snapshot
    const currentContextSnapshot = {
      phase: document.getElementById('ctxPhase').value,
      status: document.getElementById('ctxStatus').value.trim(),
      focus: document.getElementById('ctxFocus').value.trim(),
      regVer: document.getElementById('ctxRegistryVer').value.trim(),
      extra: document.getElementById('ctxExtra').value.trim(),
      threads: _threads.filter(t => t._selected).map(t => `${t.id} ${t.name}`)
    };

    // ── Context deduplication ──────────────────────────────────────────────
    // Find the last user message that stored a full snapshot and compare.
    // If context is unchanged, store a lightweight ref instead of the full copy.
    const lastFullIdx = findLastFullSnapshotIndex();
    let snapshotToStore;
    if (lastFullIdx !== -1) {
      const lastSnap = _conversation[lastFullIdx].context_snapshot;
      if (serializeSnapshot(lastSnap) === serializeSnapshot(currentContextSnapshot)) {
        // Context is identical — store a reference to that step index
        snapshotToStore = { ref: lastFullIdx };
      } else {
        // Context changed — store the full new snapshot
        snapshotToStore = currentContextSnapshot;
      }
    } else {
      // No previous snapshot exists — always store the full snapshot
      snapshotToStore = currentContextSnapshot;
    }
    // ── End deduplication ─────────────────────────────────────────────────

    const userMsg = { 
      role: 'user', 
      content: query, 
      protocol: _protocol, 
      ts: Date.now(),
      context_snapshot: snapshotToStore
    };
    _conversation.push(userMsg);
    renderUserMsg(userMsg);
    persist();

    const loadingEl = addLoadingBubble();
    document.getElementById('btnSend').classList.add('loading');
    document.getElementById('btnSend').disabled = true;

    try {
      const messages = buildApiMessages();
      const action = _settings.offlineMode ? 'export_prompt' : 'generate';

      const payload = {
        action: action,
        depth: _settings.depth,
        threads: _threads,
        deltas: _deltas.slice(-3),
        conversation: messages
      };

      const response = await fetch('/wroom_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await response.json();
      if (data.error) throw new Error(data.error);

      if (_settings.offlineMode) {
          let textFormat = data.messages.map(m => `=== ${m.role.toUpperCase()} ===\n${m.content}`).join('\n\n');
          const blob = new Blob([textFormat], { type: 'text/plain' });
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `wroom_prompt_${new Date().toISOString().replace(/[:.]/g,'-')}.txt`;
          a.click();
          URL.revokeObjectURL(url);
          
          loadingEl.remove();
          toast('Prompt exported. Paste AI response and click Robot icon.', 'info', 4000);
      } else {
          const aiText = data.raw_response || '';
          loadingEl.remove();

          const aiMsg = { role: 'assistant', content: aiText, protocol: _protocol, ts: Date.now() };
          _conversation.push(aiMsg);
          renderAiMsg(aiMsg);

          extractTouchedThreads(aiText);

          document.getElementById('headerPhaseWrap').style.display = 'flex';
          document.getElementById('headerPhase').textContent =
            `${document.getElementById('ctxPhase').value} · ${_settings.sessionTopic || 'Active session'}`;

          persist();
      }
    } catch (e) {
      loadingEl.remove();
      toast('Error: ' + e.message, 'error');
    } finally {
      _isLoading = false;
      document.getElementById('btnSend').classList.remove('loading');
      document.getElementById('btnSend').disabled = false;
    }
  }

  function injectAiResponse() {
      const input = document.getElementById('msgInput');
      const text = input.value.trim();
      if (!text) {
          toast('Paste the AI response into the text area first.', 'error');
          return;
      }
      
      input.value = '';
      autoResizeTextarea(input);
      
      const aiMsg = { role: 'assistant', content: text, protocol: _protocol, ts: Date.now() };
      _conversation.push(aiMsg);
      renderAiMsg(aiMsg);

      extractTouchedThreads(text);

      document.getElementById('headerPhaseWrap').style.display = 'flex';
      document.getElementById('headerPhase').textContent =
        `${document.getElementById('ctxPhase').value} · ${_settings.sessionTopic || 'Active session'}`;

      persist();
      toast('Response injected successfully', 'success');
  }

  // ── Rendering ─────────────────────────────────────────────────────────────
// ── Rendering ─────────────────────────────────────────────────────────────
  function renderUserMsg(msg) {
    const conv = document.getElementById('conversation');
    const protoLabel = msg.protocol && msg.protocol !== 'AUTO' ? `Protocol ${msg.protocol}` : '';

    const msgNode = document.createElement('div');
    msgNode.className = 'msg msg-user';
    msgNode.innerHTML = `
      <div class="msg-avatar user-av">✦</div>
      <div class="msg-bubble">
        ${protoLabel
          ? `<div class="msg-bubble-meta"><span class="msg-protocol-badge">${protoLabel}</span><span class="msg-timestamp">${formatTime(msg.ts)}</span></div>`
          : `<div class="msg-bubble-meta"><span class="msg-timestamp">${formatTime(msg.ts)}</span></div>`}
        ${escHtml(msg.content).replace(/\n/g,'<br>')}
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
      <div class="msg-avatar ai-av">◈</div>
      <div class="msg-bubble" style="max-width:calc(100% - 56px);">
        <div class="msg-bubble-meta">
          <span class="msg-protocol-badge">Architect</span>
          <span class="msg-timestamp">${formatTime(msg.ts)}</span>
          <button onclick="WR.copyMsg(this)" data-content="${escAttr(msg.content)}"
            style="margin-left:auto; background:none; border:none; color:var(--text-dim); cursor:pointer; font-size:11px; padding:0 4px;" title="Copy response">
            <i class="bi bi-clipboard"></i>
          </button>
        </div>
        ${rendered}
      </div>
    `;
    conv.appendChild(msgNode);
    conv.scrollTop = conv.scrollHeight;
  }

  function renderAiContent(text) {
    const withThreadRefs = text.replace(/\b(T-\d{3})\b/g, '<span class="ai-thread-ref" onclick="WR.openThreadModal(\'$1\', false)">$1</span>');
    const withVerdicts = withThreadRefs.replace(
      /VERDICT:\s*(SURVIVES_WITH_COSTS|SURVIVES|FAILS)/g,
      (_, v) => {
        const cls = v === 'SURVIVES' ? 'survives' : v === 'SURVIVES_WITH_COSTS' ? 'survives-costs' : 'fails';
        return `<span class="ai-verdict ${cls}">◈ VERDICT: ${v}</span>`;
      }
    );
    const withSections = withVerdicts.replace(/^(#{1,3})\s+(.+)$/gm, (_, h, title) => {
      return `</div><div class="ai-section"><div class="ai-section-label">${title}</div><div class="ai-section-body">`;
    });
    const withBold    = withSections.replace(/\*\*(.+?)\*\*/g, '<strong style="color:var(--text-bright)">$1</strong>');
    const withBullets = withBold.replace(/^[•\-]\s+(.+)$/gm, '<span class="ai-tension">$1</span>');
    const final       = withBullets.replace(/\n/g, '<br>');
    return `<div class="ai-section-body">${final}</div>`;
  }

  function addLoadingBubble() {
    const conv = document.getElementById('conversation');
    const msgNode = document.createElement('div');
    msgNode.className = 'msg msg-ai msg-loading';
    msgNode.innerHTML = `
      <div class="msg-avatar ai-av">◈</div>
      <div class="msg-bubble">
        <div class="typing-dots"><span></span><span></span><span></span></div>
        <span style="font-family:var(--mono); font-size:0.68rem; color:var(--text-dim);">Analyzing…</span>
      </div>`;
    conv.appendChild(msgNode);
    conv.scrollTop = conv.scrollHeight;
    return msgNode;
  }

  // ── Thread Registry ───────────────────────────────────────────────────────
  function renderThreadList() {
    const list       = document.getElementById('threadList');
    const search     = document.getElementById('threadSearch').value.toLowerCase().trim();
    const typeFilter = document.querySelector('.tfilter.active')?.dataset.ttype || '';

    const filtered = _threads.filter(t => {
      const matchType   = !typeFilter || t.type === typeFilter;
      const matchSearch = !search || t.name.toLowerCase().includes(search) || t.id.toLowerCase().includes(search);
      return matchType && matchSearch;
    });

    if (filtered.length === 0) {
      list.innerHTML = `<div class="empty-state"><div class="empty-icon">◈</div>${search ? 'No matches' : 'No threads yet'}</div>`;
      return;
    }

    list.innerHTML = filtered.map(t => {
      const typeClass = { cosmic:'cosmic', civil:'civil', char:'char', theme:'theme', reveal:'reveal' }[t.type] || 'theme';
      return `
        <div class="thread-item${t._selected ? ' selected' : ''}" data-thread-id="${t.id}"
          onclick="WR.toggleThreadSelect('${escAttr(t.id)}')">
          <div class="thread-type-dot ${typeClass}"></div>
          <div class="thread-id">${escHtml(t.id)}</div>
          <div class="thread-name">${escHtml(t.name)}</div>
          <span class="badge-inline badge-${typeClass}" style="font-size:0.55rem;">${t.status || 'ACTIVE'}</span>
          <button class="thread-edit-btn" onclick="event.stopPropagation(); WR.openThreadModal('${escAttr(t.id)}', false)" title="Edit Thread">
            <i class="bi bi-pencil"></i>
          </button>
        </div>`;
    }).join('');
  }

  function toggleThreadSelect(id) {
    const t = _threads.find(x => x.id === id);
    if (t) t._selected = !t._selected;
    persist();
    renderThreadList();
  }

  function openThreadModal(editId, fromDelta = false) {
    document.getElementById('threadModalTitle').textContent = editId ? 'Edit Thread' : 'Add Thread to Registry';
    
    document.getElementById('btnDeleteThread').style.display = (editId && !fromDelta) ? 'inline-flex' : 'none';
    document.getElementById('btnRemoveFromDelta').style.display = fromDelta ? 'inline-flex' : 'none';

    if (editId) {
      const t = _threads.find(x => x.id === editId);
      document.getElementById('editThreadIdx').value = editId;

      if (t) {
        document.getElementById('editThreadId').value        = t.id;
        document.getElementById('editThreadName').value      = t.name;
        document.getElementById('editThreadType').value      = t.type;
        document.getElementById('editThreadSeasons').value   = t.seasons || '';
        document.getElementById('editThreadStatus').value    = t.status || 'ACTIVE';
        document.getElementById('editThreadAxis').value      = t.axis || '';
        document.getElementById('editThreadTensions').value  = t.tensions || '';
        document.getElementById('editThreadQuestions').value = t.questions || '';
        document.getElementById('editThreadChekhov').value   = t.chekhov || '';
        document.getElementById('editThreadConnections').value = t.connections || '';
      } else {
        // It's a proposed thread from draft delta
        const draftT = _settings.draftDelta.threads.find(x => x.id === editId);
        document.getElementById('editThreadId').value  = editId;
        document.getElementById('editThreadName').value = draftT ? draftT.name : '';
        ['editThreadSeasons','editThreadAxis','editThreadTensions','editThreadQuestions','editThreadChekhov','editThreadConnections'].forEach(id => {
          document.getElementById(id).value = '';
        });
        document.getElementById('editThreadStatus').value = 'SETUP';
        document.getElementById('editThreadType').value   = 'cosmic';
        if (draftT && draftT.line) {
          document.getElementById('editThreadConnections').value = "PROPOSAL SOURCE:\n" + draftT.line;
        }
      }
    } else {
      document.getElementById('editThreadIdx').value = '';
      document.getElementById('editThreadId').value  = `T-${String(_threads.length + 1).padStart(3,'0')}`;
      ['editThreadName','editThreadSeasons','editThreadAxis','editThreadTensions',
       'editThreadQuestions','editThreadChekhov','editThreadConnections'].forEach(id => {
        document.getElementById(id).value = '';
      });
      document.getElementById('editThreadStatus').value = 'ACTIVE';
      document.getElementById('editThreadType').value   = 'cosmic';
    }

    document.getElementById('threadModal').classList.add('open');
  }

  function editThread(id) { openThreadModal(id, false); }

  function saveThread() {
    const editId = document.getElementById('editThreadIdx').value;
    const thread = {
      id:          document.getElementById('editThreadId').value.trim().toUpperCase(),
      name:        document.getElementById('editThreadName').value.trim(),
      type:        document.getElementById('editThreadType').value,
      seasons:     document.getElementById('editThreadSeasons').value.trim(),
      status:      document.getElementById('editThreadStatus').value,
      axis:        document.getElementById('editThreadAxis').value.trim(),
      tensions:    document.getElementById('editThreadTensions').value.trim(),
      questions:   document.getElementById('editThreadQuestions').value.trim(),
      chekhov:     document.getElementById('editThreadChekhov').value.trim(),
      connections: document.getElementById('editThreadConnections').value.trim(),
      _selected:   false,
    };

    if (!thread.id || !thread.name) { toast('Thread ID and Name are required', 'error'); return; }

    if (editId) {
      const idx = _threads.findIndex(x => x.id === editId);
      if (idx !== -1) { thread._selected = _threads[idx]._selected; _threads[idx] = thread; }
      else { _threads.push(thread); } // was a draft promotion
    } else {
      _threads.push(thread);
    }

    if (thread.chekhov) {
      thread.chekhov.split('\n').filter(Boolean).forEach(line => {
        const existing = _chekhov.find(c => c.threadId === thread.id && c.desc === line.trim());
        if (!existing) _chekhov.push({ threadId: thread.id, desc: line.trim(), ep: '', paid: false });
      });
    }
    
    // Also ensure it is noted in the current delta if created during a session
    if (!_settings.draftDelta.threads.find(t => t.id === thread.id)) {
      _settings.draftDelta.threads.push({ id: thread.id, name: thread.name, line: '' });
    }

    persist();
    renderThreadList();
    renderChekhovList();
    renderDraftDelta();
    closeModal('threadModal');
    toast('Thread saved', 'success');
  }

  function deleteThread() {
    const editId = document.getElementById('editThreadIdx').value;
    if (!editId) return;
    if (!confirm(`Remove thread ${editId}?`)) return;
    _threads = _threads.filter(t => t.id !== editId);
    persist();
    renderThreadList();
    closeModal('threadModal');
    toast('Thread removed', 'info');
  }

  // ── Session Delta State Management ────────────────────────────────────────

  function extractTouchedThreads(text) {
    const lines = text.split('\n');
    let changed = false;
    
    lines.forEach(line => {
      const match = line.match(/\b(T-\d{3})\b/);
      if (match) {
        const id = match[1];
        if (!_settings.draftDelta.threads.find(t => t.id === id)) {
          let name = '';
          // look for `T-001: The Name` or `| T-001 | The Name |`
          const nameMatch = line.match(new RegExp(id + "[\\s\\:\\-]+([^\\|\\[\\]]+)"));
          if (nameMatch) {
             name = nameMatch[1].trim().replace(/[*_]/g, ''); 
          }
          _settings.draftDelta.threads.push({ id, name, line });
          changed = true;
        }
      }
    });
    
    if (changed) {
      renderDraftDelta();
      persist();
    }
  }

  function renderDraftDelta() {
    const dList = document.getElementById('deltaDecisions');
    dList.innerHTML = _settings.draftDelta.decisions.map((text, i) =>
      `<div class="delta-tag">${escHtml(text)} <span class="delta-tag-remove" onclick="WR.removeDraftDecision(${i})">×</span></div>`
    ).join('');

    const defList = document.getElementById('deltaDeferred');
    defList.innerHTML = _settings.draftDelta.deferred.map((text, i) =>
      `<div class="delta-tag">${escHtml(text)} <span class="delta-tag-remove" onclick="WR.removeDraftDeferred(${i})">×</span></div>`
    ).join('');

    const tList = document.getElementById('deltaThreads');
    tList.innerHTML = _settings.draftDelta.threads.map(t => {
      const isRegistered = _threads.some(x => x.id === t.id);
      const bgStyle = isRegistered ? '' : 'background:var(--amber-dim); border-color:var(--amber); color:var(--amber);';
      return `<div class="delta-tag" style="cursor:pointer; ${bgStyle}" onclick="WR.openThreadModal('${t.id}', true)" title="Click to view/edit/promote">
        <i class="bi bi-bezier2"></i> ${escHtml(t.id)} ${t.name ? '— ' + escHtml(t.name) : ''}
      </div>`;
    }).join('');

    document.getElementById('deltaUpdates').value = _settings.draftDelta.updates || '';
  }

  function removeDraftDecision(i) { _settings.draftDelta.decisions.splice(i, 1); renderDraftDelta(); persist(); }
  function removeDraftDeferred(i) { _settings.draftDelta.deferred.splice(i, 1); renderDraftDelta(); persist(); }
  function removeDraftThread() {
    const id = document.getElementById('editThreadIdx').value;
    _settings.draftDelta.threads = _settings.draftDelta.threads.filter(t => t.id !== id);
    renderDraftDelta();
    persist();
    closeModal('threadModal');
    toast('Removed from Delta', 'info');
  }

  function saveDelta() {
    const { decisions, deferred, threads, updates } = _settings.draftDelta;
    if (decisions.length === 0 && deferred.length === 0 && threads.length === 0) {
      toast('Add at least one decision, deferred item, or thread', 'error'); return;
    }

    const delta = {
      date:      new Date().toLocaleDateString(),
      topic:     _settings.sessionTopic || 'Session',
      decisions: [...decisions], 
      deferred:  [...deferred], 
      threads:   threads.map(t => ({ id: t.id, name: t.name, line: t.line })), // Preserve full object
      updates:   updates,
    };

    _deltas.unshift(delta);
    
    // Clear draft
    _settings.draftDelta = { decisions: [], deferred: [], threads: [], updates: '' };
    
    persist();
    renderDeltaHistory();
    renderDraftDelta();

    toast('Session delta saved', 'success');
  }

  function renderDeltaHistory() {
    const list = document.getElementById('deltaList');
    if (_deltas.length === 0) {
      list.innerHTML = `<div class="empty-state"><div class="empty-icon">◈</div>No session deltas yet</div>`;
      return;
    }
    list.innerHTML = _deltas.slice(0, 10).map((d, i) => {
      // Map correctly handles both old string IDs and new {id, name} objects
      const threadStr = d.threads && d.threads.length ? d.threads.map(t => typeof t === 'string' ? t : t.id).join(', ') : '';
      return `
      <div class="delta-item" onclick="WR.loadDelta(${i})">
        <div class="delta-date">${d.date} — ${escHtml(d.topic)}</div>
        <div class="delta-decisions">${d.decisions.length} decision(s) · ${d.deferred.length} deferred</div>
        ${threadStr ? `<div class="delta-decisions">Threads: ${threadStr}</div>` : ''}
        <button class="item-delete-btn" onclick="WR.deleteDelta(event, ${i})" title="Delete Delta">
          <i class="bi bi-trash"></i>
        </button>
      </div>`;
    }).join('');
  }

  function loadDelta(idx) {
    const d = _deltas[idx];
    if (!d) return;

    const currentDraft = _settings.draftDelta;
    const hasDraftContent = currentDraft.decisions.length > 0 || currentDraft.deferred.length > 0 || currentDraft.threads.length > 0 || currentDraft.updates.trim() !== '';

    if (hasDraftContent) {
      if (!confirm('Loading this past delta will overwrite your current unsaved delta draft. Continue?')) return;
    }

    const restoredThreads = (d.threads || []).map(tItem => {
      if (typeof tItem === 'string') {
          // Old format (backward compatibility)
          const regT = _threads.find(x => x.id === tItem);
          return { id: tItem, name: regT ? regT.name : '', line: '' };
      } else {
          // New format
          return { id: tItem.id, name: tItem.name || '', line: tItem.line || '' };
      }
    });

    _settings.draftDelta = {
      decisions: [...(d.decisions || [])],
      deferred: [...(d.deferred || [])],
      threads: restoredThreads,
      updates: d.updates || ''
    };

    document.getElementById('sessionTopic').value = d.topic || '';
    _settings.sessionTopic = d.topic || '';

    renderDraftDelta();
    persist();

    // Open right flyout to show the restored draft
    toggleFlyout('right');
    setRightTab('delta');

    toast(`Loaded Delta: ${d.topic}`, 'success');
  }
  
  function deleteDelta(e, idx) {
    e.stopPropagation();
    if (!confirm('Permanently delete this session delta history record?')) return;
    _deltas.splice(idx, 1);
    persist();
    renderDeltaHistory();
    toast('Delta deleted', 'info');
  }

  // ── Chat Sessions ─────────────────────────────────────────────────────────
  function renderChatSessionsList() {
    const list = document.getElementById('chatSessionsList');
    if (_chatSessions.length === 0) {
      list.innerHTML = `<div class="empty-state"><div class="empty-icon">◈</div>No past chats</div>`;
      return;
    }
    list.innerHTML = _chatSessions.map(c => `
      <div class="delta-item ${c.id === _chatSessionId ? 'active-chat' : ''}" onclick="WR.loadChat('${c.id}')">
        <div class="delta-date">${new Date(c.updated_at).toLocaleDateString()}</div>
        <div class="delta-topic">${escHtml(c.title || 'Session')}</div>
        <button class="item-delete-btn" onclick="WR.deleteChat(event, '${c.id}')" title="Delete Chat">
          <i class="bi bi-trash"></i>
        </button>
      </div>`).join('');
  }

  async function loadChat(id) {
    if (id === _chatSessionId) return;

    const res  = await fetch(`/wroom_api.php?action=load_chat&session_id=${id}`);
    const data = await res.json();
    if (data.success) {
      _chatSessionId = id;
      const sess = _chatSessions.find(c => c.id === id);
      _chatSessionTitle = sess ? sess.title : 'Loaded Session';
      _conversation = data.conversation || [];

      const conv = document.getElementById('conversation');
      [...conv.children].forEach(el => { if (!el.classList.contains('wr-welcome')) el.remove(); });

      if (_conversation.length === 0) {
        document.getElementById('welcomeState').style.display = 'flex';
      } else {
        document.getElementById('welcomeState').style.display = 'none';
        _conversation.forEach(msg => {
          if (msg.role === 'user') renderUserMsg(msg);
          else renderAiMsg(msg);
        });
      }
      renderChatSessionsList();
      closeFlyouts();
      toast('Chat loaded', 'info');
    }
  }

  async function deleteChat(e, id) {
    e.stopPropagation();
    if (!confirm('Permanently delete this chat transcript?')) return;

    const res  = await fetch('/wroom_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete_chat', session_id: id })
    });
    const data = await res.json();
    if (data.success) {
      _chatSessions = _chatSessions.filter(c => c.id !== id);
      if (_chatSessionId === id) {
        clearConversation(true);
      } else {
        renderChatSessionsList();
      }
      toast('Chat deleted', 'success');
    }
  }

  // ── Failure Modes ─────────────────────────────────────────────────────────
  function renderFailureModes() {
    const list = document.getElementById('failureList');
    list.innerHTML = FAILURE_MODES.map(m => `
      <div class="failure-item" id="failure-${m.num}">
        <div class="failure-num">#${m.num}</div>
        <div class="failure-title">${m.title}</div>
        <div class="failure-status unknown" id="failure-status-${m.num}">— unchecked</div>
      </div>`).join('');
  }

  // ── Chekhov Debts ─────────────────────────────────────────────────────────
  function renderChekhovList() {
    const list = document.getElementById('chekhovList');
    if (_chekhov.length === 0) {
      list.innerHTML = `<div class="empty-state"><div class="empty-icon">⚑</div>No debts tracked</div>`;
      return;
    }
    list.innerHTML = _chekhov.map((c, i) => `
      <div class="chekhov-item ${c.paid ? 'paid' : 'unpaid'}" onclick="WR.toggleChekhov(${i})">
        <div class="chekhov-thread">${escHtml(c.threadId)}</div>
        <div class="chekhov-desc">${escHtml(c.desc)}</div>
        ${c.ep ? `<div class="chekhov-ep">→ ${escHtml(c.ep)}</div>` : ''}
        <div class="chekhov-ep" style="color:${c.paid ? 'var(--green)' : 'var(--red)'}">
          ${c.paid ? '✓ PAID' : '○ UNPAID'}
        </div>
      </div>`).join('');
  }

  function toggleChekhov(idx) {
    if (_chekhov[idx]) { _chekhov[idx].paid = !_chekhov[idx].paid; persist(); renderChekhovList(); }
  }

  function addChekhovDebt() {
    const desc     = prompt('Enter Chekhov debt description:');
    if (!desc) return;
    const threadId = prompt('Thread ID (e.g. T-001):') || '?';
    const ep       = prompt('Deadline episode (e.g. S5E08, or leave blank):') || '';
    _chekhov.push({ threadId, desc, ep, paid: false });
    persist();
    renderChekhovList();
    toast('Chekhov debt added', 'success');
  }

  // ── Protocol & Settings ───────────────────────────────────────────────────
  function setProtocol(p) {
    _protocol = p;
    document.querySelectorAll('.proto-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.proto === p);
    });
    
    if (!_settings.offlineMode) {
      const placeholders = {
        A: 'Which thread do you want to trace? (e.g. "Trace the Echo Ships thread — T-001")',
        B: 'Describe the decision to map: who, what, when. (e.g. "If Kaori turns double agent in E23, what does that force?")',
        C: 'What elements should be tested for coherence? (e.g. "Test the Singer waking and the Pinning revelation — are they doing the same work?")',
        D: 'What structural question needs options? (e.g. "Should Noel be kidnapped in E28 or E32?")',
        E: 'Which story phase should I audit? (e.g. "Run a tension audit on everything active in S3")',
        F: 'State the decision to stress-test: (e.g. "Break this: Kaelen witnesses Taro in E44 before the Pinning is named")',
      };
      document.getElementById('msgInput').placeholder = placeholders[p] || 'Ask about a thread, decision, or narrative problem…';
    }
  }

  function quickProtocol(p) {
    setProtocol(p);
    document.getElementById('msgInput').focus();
  }
  
  function setOfflineMode(isOffline) {
    _settings.offlineMode = isOffline;
    const btn = document.getElementById('btnModeToggle');
    const sendIcon = document.getElementById('sendIcon');
    const btnInject = document.getElementById('btnInject');
    
    if (isOffline) {
      btn.innerHTML = `<i class="bi bi-file-earmark-arrow-down"></i> Export Mode`;
      btn.style.borderColor = 'var(--blue)';
      btn.style.color = 'var(--blue)';
      btn.style.background = 'var(--blue-dim)';
      sendIcon.className = 'bi bi-download';
      btnInject.style.display = 'flex';
      document.getElementById('msgInput').placeholder = "Type prompt to Export, or paste AI response and click Robot icon...";
    } else {
      btn.innerHTML = `<i class="bi bi-cloud-check"></i> API Mode`;
      btn.style.borderColor = 'var(--amber)';
      btn.style.color = 'var(--amber)';
      btn.style.background = 'transparent';
      sendIcon.className = 'bi bi-arrow-up';
      btnInject.style.display = 'none';
      setProtocol(_protocol); 
    }
  }

  function toggleOfflineMode() {
    setOfflineMode(!_settings.offlineMode);
    persist();
  }
  
  function toggleAutoDraftState(isChecked) {
    _settings.autoDraft = isChecked;
    persist();
  }

  function openSessionModal() {
    document.getElementById('sessionDepth').value       = _settings.depth;
    document.getElementById('sessionAutoFailure').value = _settings.autoFailure;
    document.getElementById('sessionTopic').value       = _settings.sessionTopic;
    document.getElementById('sessionModal').classList.add('open');
  }

  function saveSettings() {
    _settings.depth        = document.getElementById('sessionDepth').value;
    _settings.autoFailure  = document.getElementById('sessionAutoFailure').value;
    _settings.sessionTopic = document.getElementById('sessionTopic').value.trim();
    persist();
    closeModal('sessionModal');
    toast('Settings saved', 'success');
    document.getElementById('headerPhaseWrap').style.display = 'flex';
    document.getElementById('headerPhase').textContent =
      `${document.getElementById('ctxPhase').value} · ${_settings.sessionTopic || 'Active session'}`;
  }

  // ── Conversation management ───────────────────────────────────────────────
  function clearConversation(force) {
    if (!force && _conversation.length > 0 &&
        !confirm('Start a new blank session? Your current chat will be saved in the "Chats" tab.')) return;

    _conversation     = [];
    _chatSessionId    = generateUUID();
    _chatSessionTitle = 'New Session';

    const conv = document.getElementById('conversation');
    [...conv.children].forEach(el => { if (!el.classList.contains('wr-welcome')) el.remove(); });

    document.getElementById('welcomeState').style.display  = 'flex';
    document.getElementById('headerPhaseWrap').style.display = 'none';

    setLeftTab('chats');
    renderChatSessionsList();
    persist();
    toast('New blank session started', 'info');
  }

  // ── Flyout sidebar control ────────────────────────────────────────────────
  function toggleFlyout(side) {
    const openingLeft  = side === 'left';
    const openingRight = side === 'right';
    const leftEl       = document.getElementById('flyoutLeft');
    const rightEl      = document.getElementById('flyoutRight');
    const backdrop     = document.getElementById('flyoutBackdrop');
    const hLeft        = document.getElementById('hamburgerLeft');
    const hRight       = document.getElementById('hamburgerRight');

    const leftOpen  = leftEl.classList.contains('open');
    const rightOpen = rightEl.classList.contains('open');

    if ((openingLeft && leftOpen) || (openingRight && rightOpen)) {
      closeFlyouts(); return;
    }

    leftEl.classList.remove('open');
    rightEl.classList.remove('open');
    hLeft.classList.remove('open');
    hRight.classList.remove('open');

    if (openingLeft)  { leftEl.classList.add('open');  hLeft.classList.add('open'); }
    if (openingRight) { rightEl.classList.add('open'); hRight.classList.add('open'); }

    backdrop.classList.add('visible');
  }

  function closeFlyouts() {
    document.getElementById('flyoutLeft').classList.remove('open');
    document.getElementById('flyoutRight').classList.remove('open');
    document.getElementById('flyoutBackdrop').classList.remove('visible');
    document.getElementById('hamburgerLeft').classList.remove('open');
    document.getElementById('hamburgerRight').classList.remove('open');
  }

  function setLeftTab(tab) {
    document.querySelectorAll('.left-tab').forEach(t => t.classList.toggle('active', t.dataset.ltab === tab));
    document.querySelectorAll('.left-tab-content').forEach(c => c.classList.toggle('active', c.id === `ltab-${tab}`));
  }

  function setRightTab(tab) {
    document.querySelectorAll('.right-tab').forEach(t => t.classList.toggle('active', t.dataset.rtab === tab));
    document.querySelectorAll('.right-tab-content').forEach(c => c.classList.toggle('active', c.id === `rtab-${tab}`));
  }

  // ── KG Picker Tabs & Semantic Query ──────────────────────────────────────────
  
  function kgSetTab(tab) {
    _kgActiveTab = tab;
    document.getElementById('wr-kg-tab-tree').classList.toggle('active', tab === 'tree');
    document.getElementById('wr-kg-tab-semantic').classList.toggle('active', tab === 'semantic');
    document.getElementById('wr-kg-pane-tree').classList.toggle('active', tab === 'tree');
    document.getElementById('wr-kg-pane-semantic').classList.toggle('active', tab === 'semantic');
    kgUpdatePickerCount();
    if (tab === 'semantic') {
      setTimeout(() => document.getElementById('wrKgSemanticQuery').focus(), 100);
    }
  }

  async function kgRunSemanticQuery() {
    const query = document.getElementById('wrKgSemanticQuery').value.trim();
    if (!query) return;

    const nResults = parseInt(document.getElementById('wrKgSemanticN').value);
    const loadingBar = document.getElementById('wrKgSemanticLoader');
    const searchBtn = document.getElementById('wrKgSemanticBtn');
    const hitsArea = document.getElementById('wrKgSemanticHits');

    loadingBar.style.display = 'block';
    searchBtn.disabled = true;
    searchBtn.textContent = '...';

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

      // Auto-select top hits (e.g., score > 0.35)
      _kgSemanticHits.filter(h => h.score > 0.35).forEach(h => _kgSemanticSelectedIds.add(h.node_id));
      kgRenderSemanticHits();
      kgUpdatePickerCount();

    } catch(e) {
      hitsArea.innerHTML = `<div class="wr-kge-hits-empty"><span style="color:var(--red);">Error: ${escHtml(e.message)}</span></div>`;
      toast('Search error: ' + e.message, 'error');
    } finally {
      loadingBar.style.display = 'none';
      searchBtn.disabled = false;
      searchBtn.textContent = 'Search';
    }
  }

  function kgRenderSemanticHits() {
    const area = document.getElementById('wrKgSemanticHits');
    if (!_kgSemanticHits.length) { area.innerHTML = ''; return; }

    const maxScore = _kgSemanticHits[0]?.score || 1;
    const header = `
        <div class="wr-kge-hits-header">
            <span style="flex:1;">${_kgSemanticHits.length} node(s) ranked by relevance</span>
            <button class="wr-kg-picker-select-all" onclick="WR.kgToggleAllSemantic()">Toggle all</button>
        </div>`;

    const rows = _kgSemanticHits.map(hit => {
        const checked = _kgSemanticSelectedIds.has(hit.node_id) ? 'checked' : '';
        const selClass = _kgSemanticSelectedIds.has(hit.node_id) ? 'selected' : '';
        const barW = Math.max(8, Math.round((hit.score / maxScore) * 60));
        const typeKey = (hit.node_type || 'note').toLowerCase();
        const dotClass = `wr-kge-dot-${hit.content_status}`;
        const excerpt = escHtml(hit.excerpt || '').replace(/^Node:[^\n]*\n?Type:[^\n]*\n?/, '').trim();

        return `
        <div class="wr-kge-hit-row ${selClass}" onclick="WR.kgToggleSemanticHit(${hit.node_id}, this)">
            <input type="checkbox" class="wr-kge-hit-check" ${checked} onclick="event.stopPropagation(); WR.kgToggleSemanticHit(${hit.node_id}, this.closest('.wr-kge-hit-row'))">
            <span class="wr-kge-status-dot ${dotClass}" title="${hit.content_status}"></span>
            <div class="wr-kge-hit-body">
                <div class="wr-kge-hit-name">
                    ${escHtml(hit.name)}
                    <span class="wr-kge-type-pill">${typeKey}</span>
                    ${hit.category_name ? `<span style="font-size:0.7rem;color:var(--text-dim);font-weight:400">${escHtml(hit.category_name)}</span>` : ''}
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
        rowEl.querySelector('input[type=checkbox]').checked = false;
    } else {
        _kgSemanticSelectedIds.add(nodeId);
        rowEl.classList.add('selected');
        rowEl.querySelector('input[type=checkbox]').checked = true;
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

  // ── KG Picker Tree Logic (Adapted from kg_view.php) ───────────────────────
  const WR_KGE_PICKER_OPEN_KEY = 'wr_kg_picker_open_folders';

  function kgPickerSaveOpenState() {
    const open = [];
    document.querySelectorAll('#kgPickerTreeWrap .wr-kg-tree-children.open').forEach(el => {
        open.push(el.id.replace('kg-kids-', ''));
    });
    try { localStorage.setItem(WR_KGE_PICKER_OPEN_KEY, JSON.stringify(open)); } catch(e) {}
  }

  function kgPickerLoadOpenState() {
    try {
        const raw = localStorage.getItem(WR_KGE_PICKER_OPEN_KEY);
        if (raw) return new Set(JSON.parse(raw));
    } catch(e) {}
    return new Set(); // Default closed
  }

  function kgNodeIcon(type) {
    const map = { relationship:'🔗', character:'👤', location:'📍', event:'📅', concept:'💡', arc:'🌀', episode:'🎬', note:'📝' };
    return map[type] || '📝';
  }

  function openKgPickerModal() {
    document.getElementById('kgOptContent').checked = !!_settings.kgWithContent;
    document.getElementById('kgOptEdges').checked = _settings.kgWithEdges !== false;
    document.getElementById('kgPickerModal').classList.add('open');
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
    const sel = _settings.kgSelectedNodes || [];
    sel.forEach(dbId => {
      _kgPickerChecked.add('n_' + dbId);
    });
  }

  function kgLoadPickerTree() {
    const wrap = document.getElementById('kgPickerTreeWrap');
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
        ? `<span class="wr-kg-node-toggle ${isOpen ? 'open' : ''}" onclick="WR.kgPickerToggleFolder('${jsId}', this)">▶</span>`
        : `<span style="width:16px;display:inline-block;flex-shrink:0;"></span>`;

      html += `
      <div class="wr-kg-tree-node ${isFolder ? 'is-folder' : 'is-node'}"
           style="padding-left:${10 + indent}px;"
           data-jid="${jsId}">
          ${toggleBtn}
          <input type="checkbox" ${checked ? 'checked' : ''}
                 onchange="WR.kgPickerCheck('${jsId}', this.checked)">
          <span class="wr-kg-node-icon">${icon}</span>
          <span class="wr-kg-node-label">${escHtml(node.text)}</span>
      </div>`;

      if (hasKids) {
          html += `<div class="wr-kg-tree-children ${isOpen ? 'open' : ''}" id="kg-kids-${jsId}">`;
          html += kgBuildPickerLevel(jsId, childMap, depth + 1, openSet);
          html += `</div>`;
      }
    });
    return html;
  }

  function kgPickerToggleFolder(jsId, btn) {
    const kids = document.getElementById('kg-kids-' + jsId);
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
    
    // Only save selection state to settings if pulling from the tree. 
    // Semantic pulls are ephemeral by nature.
    if (_kgActiveTab === 'tree') {
        _settings.kgSelectedNodes = dbIds;
    }
    _settings.kgWithContent = withContent;
    _settings.kgWithEdges = withEdges;
    
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
      
      let currentText = document.getElementById('ctxExtra').value.trim();
      const regex = new RegExp(kgMarkerStart + "[\\s\\S]*?" + kgMarkerEnd, "g");
      
      if (regex.test(currentText)) {
          currentText = currentText.replace(regex, newBlock);
      } else {
          currentText = currentText ? currentText + "\n\n" + newBlock : newBlock;
      }
      
      document.getElementById('ctxExtra').value = currentText;
      persist();
      closeModal('kgPickerModal');
      toast('KG Context Injected ✓', 'success');
    } catch(e) {
      toast('Error: ' + e.message, 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-box-arrow-in-down"></i> Inject Context';
    }
  }

  // ── Utilities ─────────────────────────────────────────────────────────────
  function copyMsg(btn) {
    const text = btn.getAttribute('data-content');
    navigator.clipboard?.writeText(text).then(() => toast('Copied', 'success', 1500)).catch(() => {});
  }

  function toggleTheme() {
    const html = document.documentElement;
    const curr = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', curr);
    try { localStorage.setItem('spw_theme', curr); } catch(e) {}
    document.getElementById('themeBtn').querySelector('i').className =
      curr === 'dark' ? 'bi bi-moon-stars' : 'bi bi-sun';
  }

  function closeModal(id) { document.getElementById(id).classList.remove('open'); }

  function toast(msg, type = 'info', duration = 2800) {
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    const icons = { success: '✓', error: '✕', info: '◈' };
    el.innerHTML = `<span>${icons[type]||'◈'}</span> ${escHtml(msg)}`;
    el.onclick = () => { el.classList.add('out'); setTimeout(()=>el.remove(), 250); };
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => { el.classList.add('out'); setTimeout(()=>el.remove(), 250); }, duration);
  }

  function formatTime(ts) {
    return new Date(ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function escHtml(s) {
    if (s === null || s === undefined) return '';
    const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML;
  }
  function escAttr(s) { return escHtml(s).replace(/"/g, '&quot;'); }

  function autoResizeTextarea(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 200) + 'px';
  }

  // ── Persistence ───────────────────────────────────────────────────────────
  function persist() {
    try {
      const context = {
        phase:       document.getElementById('ctxPhase').value,
        status:      document.getElementById('ctxStatus').value,
        focus:       document.getElementById('ctxFocus').value,
        registryVer: document.getElementById('ctxRegistryVer').value,
        extra:       document.getElementById('ctxExtra').value
      };

      const payload = {
        action:             'save_state',
        chat_session_id:    _chatSessionId,
        chat_session_title: _chatSessionTitle,
        threads:            _threads,
        deltas:             _deltas,
        chekhov:            _chekhov,
        settings:           _settings,
        conversation:       _conversation,
        context:            context
      };

      fetch('/wroom_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).catch(e => console.error('WR Persist API Error:', e));

    } catch(e) {
      console.error('WR Persist Prep Error:', e);
    }
  }

  async function loadPersisted() {
    try {
      const res  = await fetch('/wroom_api.php?action=load_state');
      const data = await res.json();
      if (data.success && data.state) {

        if (data.state.chat_session_id) {
          _chatSessionId = data.state.chat_session_id;
        } else {
          _chatSessionId = generateUUID();
        }

        if (data.state.chat_sessions) {
          _chatSessions = data.state.chat_sessions;
          const cur = _chatSessions.find(c => c.id === _chatSessionId);
          if (cur) _chatSessionTitle = cur.title;
        }

        if (data.state.threads)  _threads  = data.state.threads;
        if (data.state.deltas)   _deltas   = data.state.deltas;
        if (data.state.chekhov)  _chekhov  = data.state.chekhov;
        
        if (data.state.settings) {
            _settings = Object.assign(_settings, data.state.settings);
            setOfflineMode(!!_settings.offlineMode);
            if (typeof _settings.kgWithContent === 'undefined') _settings.kgWithContent = false;
            if (typeof _settings.kgWithEdges === 'undefined') _settings.kgWithEdges = true;
            if (typeof _settings.autoDraft === 'undefined') _settings.autoDraft = false;
            if (!_settings.kgSelectedNodes) _settings.kgSelectedNodes = [];
            if (!_settings.draftDelta) _settings.draftDelta = { decisions: [], deferred: [], threads: [], updates: '' };
            
            const chk = document.getElementById('chkAutoDraft');
            if (chk) chk.checked = _settings.autoDraft;
        }

        if (data.state.context) {
          const ctx = data.state.context;
          if (ctx.phase)                  document.getElementById('ctxPhase').value       = ctx.phase;
          if (ctx.status      !== undefined) document.getElementById('ctxStatus').value    = ctx.status;
          if (ctx.focus       !== undefined) document.getElementById('ctxFocus').value     = ctx.focus;
          if (ctx.registryVer !== undefined) document.getElementById('ctxRegistryVer').value = ctx.registryVer;
          if (ctx.extra       !== undefined) document.getElementById('ctxExtra').value     = ctx.extra;
        }

        if (data.state.conversation && data.state.conversation.length > 0) {
          _conversation = data.state.conversation;
          const conv = document.getElementById('conversation');
          [...conv.children].forEach(el => { if (!el.classList.contains('wr-welcome')) el.remove(); });
          document.getElementById('welcomeState').style.display = 'none';
          _conversation.forEach(msg => {
            if (msg.role === 'user') renderUserMsg(msg);
            else renderAiMsg(msg);
          });
          conv.scrollTop = conv.scrollHeight;
        }

        renderThreadList();
        renderChekhovList();
        renderDeltaHistory();
        renderChatSessionsList();
        renderDraftDelta();

        document.getElementById('headerPhaseWrap').style.display = 'flex';
        document.getElementById('headerPhase').textContent =
          `${document.getElementById('ctxPhase').value} · ${_settings.sessionTopic || 'Active session'}`;
      }
    } catch(e) {
      console.error('WR Load Persisted Error:', e);
    }
  }

  // ── Init ──────────────────────────────────────────────────────────────────
  function init() {
    loadPersisted();
    renderFailureModes();
    renderThreadList();
    renderChekhovList();
    renderDeltaHistory();

    ['ctxPhase', 'ctxStatus', 'ctxFocus', 'ctxRegistryVer', 'ctxExtra'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('change', persist);
    });
    
    document.getElementById('deltaUpdates').addEventListener('input', e => {
      _settings.draftDelta.updates = e.target.value;
    });
    document.getElementById('deltaUpdates').addEventListener('change', persist);

    document.querySelectorAll('.left-tab').forEach(btn => {
      btn.addEventListener('click', () => setLeftTab(btn.dataset.ltab));
    });

    document.querySelectorAll('.right-tab').forEach(btn => {
      btn.addEventListener('click', () => setRightTab(btn.dataset.rtab));
    });

    document.getElementById('threadSearch').addEventListener('input', renderThreadList);
    document.getElementById('threadFilters').addEventListener('click', e => {
      const btn = e.target.closest('.tfilter');
      if (!btn) return;
      document.querySelectorAll('.tfilter').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      renderThreadList();
    });

    document.getElementById('deltaDecisionInput').addEventListener('keydown', e => {
      if (e.key === 'Enter') { 
        const val = e.target.value.trim();
        if(val) {
          _settings.draftDelta.decisions.push(val);
          e.target.value = '';
          renderDraftDelta();
          persist();
        }
      }
    });
    document.getElementById('deltaDeferredInput').addEventListener('keydown', e => {
      if (e.key === 'Enter') { 
        const val = e.target.value.trim();
        if(val) {
          _settings.draftDelta.deferred.push(val);
          e.target.value = '';
          renderDraftDelta();
          persist();
        }
      }
    });

    const msgInput = document.getElementById('msgInput');
    msgInput.addEventListener('input', () => autoResizeTextarea(msgInput));
    msgInput.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
    });

    document.querySelectorAll('.wr-modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        closeFlyouts();
        document.querySelectorAll('.wr-modal-overlay.open').forEach(m => m.classList.remove('open'));
      }
    });

    const theme = document.documentElement.getAttribute('data-theme');
    document.getElementById('themeBtn').querySelector('i').className =
      theme === 'light' ? 'bi bi-sun' : 'bi bi-moon-stars';
  }

  // ── Public API ────────────────────────────────────────────────────────────
  return {
    init,
    send,
    setProtocol,
    quickProtocol,
    toggleFlyout,
    closeFlyouts,
    openThreadModal,
    editThread,
    saveThread,
    deleteThread,
    toggleThreadSelect,
    openSessionModal,
    saveSettings,
    clearConversation,
    saveDelta,
    loadDelta,
    deleteDelta,
    renderDeltaHistory,
    loadChat,
    deleteChat,
    renderChatSessionsList,
    toggleChekhov,
    addChekhovDebt,
    copyMsg,
    toggleTheme,
    closeModal,
    toast,
    toggleOfflineMode,
    toggleAutoDraftState,
    injectAiResponse,
    
    // KG Export Picker Exposed API
    openKgPickerModal,
    kgPickerToggleFolder,
    kgPickerCheck,
    kgPickerToggleAll,
    injectKgContext,
    
    // Semantic Context API
    kgSetTab,
    kgRunSemanticQuery,
    kgToggleSemanticHit,
    kgToggleAllSemantic,
    
    // Draft Delta Exports
    removeDraftDecision,
    removeDraftDeferred,
    removeDraftThread
  };
})();

document.addEventListener('DOMContentLoaded', function() {
  if (typeof window.WR !== 'undefined' && window.WR.init) {
    window.WR.init();
  } else {
    console.error('WR engine failed to initialize.');
  }
});
</script>
<?php if (file_exists(__DIR__ . '/js/sage-home-button.js')): ?>
<!--
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
-->
<?php endif; ?>


<?php // echo $eruda; ?>


</body>
</html>
