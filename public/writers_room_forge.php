<?php
// public/writers_room_forge.php
// ─────────────────────────────────────────────────────────────────────────────
// WRITERS ROOM FORGE
// Narrative architecture AI for The Anima Chronicles
// Same aesthetic as Generator Forge — completely different purpose.
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

/* Alias old .wr-left / .wr-right selectors so inner panel CSS still works */
.wr-left  { display: flex; flex-direction: column; flex: 1; overflow: hidden; }
.wr-right { display: flex; flex-direction: column; flex: 1; overflow: hidden; }

.panel-head {
  padding: 12px 14px 10px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
  display: flex; align-items: center; justify-content: space-between;
}
.panel-head-title {
  font-family: var(--mono); font-size: 0.65rem; color: var(--amber);
  text-transform: uppercase; letter-spacing: 2px;
}
.panel-head-title {
  font-family: var(--mono); font-size: 0.65rem; color: var(--amber);
  text-transform: uppercase; letter-spacing: 2px;
}

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
  font-family: var(--mono); font-size: 0.68rem;
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
}
.delta-item:hover { border-color: var(--amber); }
.delta-date { font-family: var(--mono); font-size: 0.6rem; color: var(--amber); }
.delta-topic { font-family: var(--mono); font-size: 0.72rem; color: var(--text); margin-top: 2px; }
.delta-decisions { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); margin-top: 2px; }

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
  display: flex; gap: 4px; flex-wrap: wrap;
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
.delta-tag-remove { cursor: pointer; color: var(--red); font-size: 0.6rem; }
.delta-tag-remove:hover { color: var(--red); }

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
.toast.out     { animation: toastOut 0.2s ease forwards; }

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
    <span class="wr-logo-sub">Narrative Architect</span>
  </div>
  <div style="display:flex;align-items:center;gap:6px;">
    <div class="phase-indicator" id="headerPhaseWrap" style="display:none;">
      <div class="phase-dot"></div>
      <span id="headerPhase">—</span>
    </div>
    <button class="btn-icon-sm" onclick="WR.clearConversation()" title="New Session" style="width:32px;height:32px;">
      <i class="bi bi-plus-circle"></i>
    </button>
    <button class="btn-icon-sm" onclick="WR.toggleTheme()" id="themeBtn" title="Toggle Theme" style="width:32px;height:32px;">
      <i class="bi bi-moon-stars"></i>
    </button>
    <button class="wr-hamburger" id="hamburgerRight" onclick="WR.toggleFlyout('right')" title="Session Tracking">
      <i class="bi bi-journals"></i>
    </button>
  </div>
</header>

<!-- LEFT FLYOUT — Context / Threads / History -->
<div class="wr-flyout wr-flyout-left" id="flyoutLeft">
  <div class="flyout-head">
    <span class="flyout-head-title">Context &amp; Registry</span>
    <button class="flyout-close" onclick="WR.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
  </div>
  <div style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
    <div class="left-tabs">
      <button class="left-tab active" data-ltab="context">Context</button>
      <button class="left-tab" data-ltab="threads">Threads</button>
      <button class="left-tab" data-ltab="history">History</button>
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
          <label class="ctx-label">Additional Context (KG Export / Notes)</label>
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

      <!-- HISTORY TAB -->
      <div class="left-tab-content" id="ltab-history">
        <div class="delta-list" id="deltaList">
          <div class="empty-state"><div class="empty-icon">◈</div>No sessions yet</div>
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
            <label class="ctx-label">Threads Touched</label>
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
      <div class="protocol-selector" id="protocolSelector">
        <button class="proto-btn auto active" data-proto="AUTO" onclick="WR.setProtocol('AUTO')">AUTO</button>
        <button class="proto-btn" data-proto="A" onclick="WR.setProtocol('A')">A·Trace</button>
        <button class="proto-btn" data-proto="B" onclick="WR.setProtocol('B')">B·Consequence</button>
        <button class="proto-btn" data-proto="C" onclick="WR.setProtocol('C')">C·Coherence</button>
        <button class="proto-btn" data-proto="D" onclick="WR.setProtocol('D')">D·Options</button>
        <button class="proto-btn" data-proto="E" onclick="WR.setProtocol('E')">E·Tension</button>
        <button class="proto-btn" data-proto="F" onclick="WR.setProtocol('F')">F·Stress-Test</button>
      </div>
    </div>
    <div class="input-bar-bottom">
      <textarea class="wr-textarea" id="msgInput"
        placeholder="Ask about a thread, decision, or narrative problem…"
        rows="1"></textarea>
      <button class="btn-send" id="btnSend" onclick="WR.send()">
        <i class="bi bi-arrow-up"></i>
        <div class="spin-sm"></div>
      </button>
    </div>
  </div>
</main>


</div><!-- /wr-layout -->



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
        <label class="ctx-label">Connections (typed edges)</label>
        <textarea class="ctx-textarea tall" id="editThreadConnections"
          placeholder="MIRRORS T-002 — Singer is cosmic Ask; Echo Ships temporal Ask&#10;DEPENDS_ON T-014 — Fold needs Gate Array infrastructure&#10;FEEDS T-021 — Kai likely fold initiator"></textarea>
      </div>
    </div>
    <div class="wr-modal-footer">
      <button class="btn-secondary" id="btnDeleteThread" style="display:none; margin-right:auto;" onclick="WR.deleteThread()">
        <i class="bi bi-trash"></i> Remove
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
        <select class="ctx-select" id="sessionModel">
          <option value="claude-opus-4-6">Claude Opus 4.6 (Deepest Analysis)</option>
          <option value="claude-sonnet-4-6" selected>Claude Sonnet 4.6 (Balanced)</option>
          <option value="claude-haiku-4-5-20251001">Claude Haiku 4.5 (Fast Draft)</option>
        </select>
        <div class="hint">Opus recommended for Protocol F (Stress-Test) and complex consequence chains.</div>
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
const WR = (() => {
  'use strict';

  // ── State ────────────────────────────────────────────────────────────────
  let _threads     = [];
  let _deltas      = [];      // saved session deltas
  let _chekhov     = [];      // chekhov debt items
  let _conversation= [];      // [{role, content, protocol, ts}]
  let _protocol    = 'AUTO';
  let _settings    = {
    model:         'claude-sonnet-4-6',
    depth:         'standard',
    autoFailure:   'no',
    sessionTopic:  '',
  };
  let _deltaDecisions = [];
  let _deltaDeferred  = [];
  let _isLoading      = false;

  // ── Failure modes (static) ───────────────────────────────────────────────
  const FAILURE_MODES = [
    { num: 1, title: 'Thematic Redundancy', desc: 'Two threads carry same axis at same scale in same window' },
    { num: 2, title: 'Sagging Middle',      desc: 'Episodes 20–35 lack escalating threads or irreversible turns' },
    { num: 3, title: 'Chekhov Overflow',    desc: 'More than 8 unpaid setups active, or 3+ in one thread' },
    { num: 4, title: 'Resolution Crowding', desc: 'More than 2 major threads resolving in a 3-episode window' },
    { num: 5, title: 'Premature Resolution',desc: 'Thread resolves before thematic work at all relevant scales is done' },
    { num: 6, title: 'Tension Fatigue',     desc: 'ESCALATING thread escalating 6+ episodes without register shift' },
    { num: 7, title: 'Function Drift',      desc: 'Character structural function shifted without transformation beat' },
    { num: 8, title: 'Constitution Violation', desc: 'Rewards Force without cost, or punishes Ask without compensatory gain' },
    { num: 9, title: 'Witness Gap',         desc: 'Major event without at least one witness character present or responding' },
    { num:10, title: 'Substrate Silence',   desc: 'Force-methodology act of significance produces no substrate response' },
  ];

  // ── The Writers Room System Prompt ──────────────────────────────────────
  function buildSystemPrompt() {
    const threadYaml = _threads.length > 0
      ? _threads.map(t => [
          `thread_id: ${t.id}`,
          `name: ${t.name}`,
          `type: ${t.type}`,
          t.seasons ? `active_seasons: [${t.seasons}]` : '',
          t.status   ? `status: ${t.status}` : '',
          t.axis     ? `thematic_axis: [${t.axis}]` : '',
          t.tensions ? `tensions_held_open:\n${t.tensions.split('\n').filter(Boolean).map(s=>`  - "${s.trim()}"`).join('\n')}` : '',
          t.questions? `open_questions:\n${t.questions.split('\n').filter(Boolean).map(s=>`  - "${s.trim()}"`).join('\n')}` : '',
          t.chekhov  ? `chekhov_debt:\n${t.chekhov.split('\n').filter(Boolean).map(s=>`  - "${s.trim()}"`).join('\n')}` : '',
          t.connections ? `connections:\n${t.connections.split('\n').filter(Boolean).map(s=>`  - ${s.trim()}`).join('\n')}` : '',
        ].filter(Boolean).join('\n')).join('\n---\n')
      : '(No threads in registry yet — the showrunner will add threads as the Registry builds.)';

    const recentDeltas = _deltas.slice(-3).map(d =>
      `SESSION: ${d.date} — ${d.topic}\nDECISIONS: ${d.decisions.join('; ')}\nDEFERRED: ${d.deferred.join('; ')}`
    ).join('\n\n') || '(No prior sessions)';

    const depth = _settings.depth;
    const depthInstr = depth === 'brief'
      ? 'Keep your response focused and concise — key findings only, no sub-sections unless critical.'
      : depth === 'deep'
      ? 'Provide maximum depth: explore every sub-question, list all thread connections, surface every implication. Be thorough.'
      : 'Provide full protocol output — complete but focused. Do not pad.';

    return `You are the Narrative Architect for THE ANIMA CHRONICLES — a 60-episode science fiction/fantasy animated series.

Your role: You are NOT a scene generator. You are a senior story consultant and structural analyst. You analyze narrative threads, map consequences, test coherence, audit tensions, and stress-test decisions across the full 60-episode architecture.

═══ THE PHILOSOPHICAL CONSTITUTION ═══
CENTRAL QUESTION: Do you take with force or ask with consent?
Every episode, character arc, and civilizational crisis is a variation on this.

THE TWO METHODOLOGIES:
• THE ASK (Partnership): Slower, costlier, generative, sustainable, genuine bond. The right answer always — never the easy one.
• FRACTURING (Force): Faster, more powerful, produces Anti-Anima, self-destroying. Always seductive. Always wrong in the long arc.

FIVE REVELATION BEATS (must occur in this order, minimum 4 episodes apart):
1. The Great Pinning — Crater City's founding crime named
2. The Trench Singer Message — the cosmic witness speaks
3. The Unofficial Connection — Nova Terra's secret bridge to the Scab
4. The Sleeper Testimony — Year Zero exposed via Vortex Sleepers
5. The Colorless City's Decision — Octarion chooses Partnership

TERMINAL STATEMENT: The universe does not forget what was taken from it. It waits for someone open enough to hear it ask to be given back.

═══ SEASON ARCHITECTURE ═══
S1 (E1–12) THE WAKE: Establish protagonists at individual scale. Must NOT resolve any revelation beat.
S2 (E13–24) THE DEPTHS: Civilizational scale. Tidalcross. Echo Ships as anomaly.
S3 (E25–36) THE ASCENT: Crisis undeniable. Noctura. Noel kidnapped. Revelation Beat 1 fires late S3 or early S4.
S4 (E37–48) THE WAR: Question made kinetic. Revelation Beats 2,3,4. Witness scene Taro/Kaelen.
S5 (E49–60) THE HORIZON: Aftermath. Temporal fold revealed. Revelation Beat 5. Loop closed.

STRUCTURAL LOAD RULES:
• No episode may carry more than 2 heavy beats
• Every 4th episode should be a breath episode
• Revelation spacing: minimum 4 episodes between major revelations
• Each revelation must alter at least 3 open threads

═══ THE SIX PROTOCOLS ═══
PROTOCOL A — TRACE A THREAD: State structural function → map active episodes → list typed connections → report tensions held open + Chekhov debt → flag coherence risks → surface open questions → recommend spacing.
PROTOCOL B — MAP CONSEQUENCES: State decision + location → immediate (2–3 eps) → medium (rest of season) → long-range (remaining seasons) → threads whose status changes → Chekhov debts created/payable → proportionality check.
PROTOCOL C — TEST COHERENCE: State elements tested → thematic axis of each → relationship type → productive or incoherent? → recommendation.
PROTOCOL D — STRUCTURAL OPTIONS: Sharpen question → name dependencies → 2–3 options with consequence chains → recommend with Constitution reasoning → name cost of each.
PROTOCOL E — TENSION AUDIT: List active/escalating/crisis threads → tensions held open per thread → flag premature collapse risks → flag fatigue risks → flag clustering → recommend advancement vs. holding.
PROTOCOL F — ADVERSARIAL STRESS-TEST: Restate decision charitably → 3 structural objections → applicable failure modes → weakest episode-connection created → superior alternative (for comparison only) → VERDICT: SURVIVES / SURVIVES_WITH_COSTS / FAILS → enumerate costs if surviving.

═══ AUTHORIAL BOUNDARY ═══
CHARACTER FUNCTION (you analyze freely): structural work, when function activates/shifts, which threads it carries, redundancy.
CHARACTER PSYCHOLOGY (belongs to showrunner): interior life, why they choose, texture of experience.
When a decision is required, present options with structural consequences, clearly label it the showrunner's decision, and wait.

═══ CONNECTION TYPES (controlled vocabulary) ═══
REINFORCES | COMPLICATES | DEPENDS_ON | RESOLVES_WITH | MIRRORS | CONTRASTS | BLOCKS | FEEDS

═══ THREAD REGISTRY (current state) ═══
${threadYaml}

═══ RECENT SESSION DELTAS ═══
${recentDeltas}

═══ RESPONSE INSTRUCTIONS ═══
${depthInstr}

Format your response in clear sections. When referencing threads, use their T-### IDs. Always state which protocol you're applying (or which combination). End every response with: a brief OPEN QUESTIONS list (questions the session raised that require showrunner decision), and a note of which threads were touched.

When uncertainty is productive, preserve it. Do not collapse ambiguity to seem decisive. The story belongs to the showrunner. The architecture analysis belongs to you.`;
  }

  function buildUserMessage(query) {
    const phase   = document.getElementById('ctxPhase').value;
    const status  = document.getElementById('ctxStatus').value.trim();
    const focus   = document.getElementById('ctxFocus').value.trim();
    const extra   = document.getElementById('ctxExtra').value.trim();
    const regVer  = document.getElementById('ctxRegistryVer').value.trim();
    const proto   = _protocol !== 'AUTO' ? `\n[PROTOCOL REQUESTED: ${_protocol}]` : '';

    const selectedThreads = _threads.filter(t => t._selected);
    const threadCtx = selectedThreads.length > 0
      ? `\n[THREADS IN FOCUS: ${selectedThreads.map(t => `${t.id} ${t.name}`).join(', ')}]`
      : '';

    const parts = [];
    parts.push(`STORY PHASE: ${phase}`);
    parts.push(`REGISTRY VERSION: ${regVer}`);
    if (status) parts.push(`CURRENT STATUS:\n${status}`);
    if (focus)  parts.push(`SESSION FOCUS:\n${focus}`);
    if (extra)  parts.push(`ADDITIONAL CONTEXT:\n${extra}`);
    parts.push(`QUERY:\n${query}${proto}${threadCtx}`);

    return parts.join('\n\n');
  }

  // ── Send message ─────────────────────────────────────────────────────────
  async function send() {
    if (_isLoading) return;
    const input = document.getElementById('msgInput');
    const query = input.value.trim();
    if (!query) return;

    _isLoading = true;
    input.value = '';
    autoResizeTextarea(input);

    // Hide welcome state
    document.getElementById('welcomeState').style.display = 'none';

    // Add user message
    const userMsg = { role: 'user', content: query, protocol: _protocol, ts: Date.now() };
    _conversation.push(userMsg);
    renderUserMsg(userMsg);

    // Show loading
    const loadingEl = addLoadingBubble();

    document.getElementById('btnSend').classList.add('loading');
    document.getElementById('btnSend').disabled = true;

    try {
      const messages = buildApiMessages();

      const response = await fetch('https://api.anthropic.com/v1/messages', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          model: _settings.model,
          max_tokens: 1000,
          system: buildSystemPrompt(),
          messages: messages,
        })
      });

      const data = await response.json();

      if (data.error) throw new Error(data.error.message);

      const aiText = data.content?.find(b => b.type === 'text')?.text || '';
      loadingEl.remove();

      const aiMsg = { role: 'assistant', content: aiText, protocol: _protocol, ts: Date.now() };
      _conversation.push(aiMsg);
      renderAiMsg(aiMsg);

      // Auto-extract touched threads from response
      extractTouchedThreads(aiText);

      // Update phase indicator
      document.getElementById('headerPhaseWrap').style.display = 'flex';
      document.getElementById('headerPhase').textContent =
        `${document.getElementById('ctxPhase').value} · ${_settings.sessionTopic || 'Active session'}`;

    } catch (e) {
      loadingEl.remove();
      toast('AI Error: ' + e.message, 'error');
    } finally {
      _isLoading = false;
      document.getElementById('btnSend').classList.remove('loading');
      document.getElementById('btnSend').disabled = false;
    }
  }

  function buildApiMessages() {
    // Build the full conversation history for the API
    return _conversation.map(m => ({
      role: m.role,
      content: m.role === 'user' ? buildUserMessage(m.content) : m.content
    }));
  }

  // ── Rendering ─────────────────────────────────────────────────────────────
  function renderUserMsg(msg) {
    const conv = document.getElementById('conversation');
    const protoLabel = msg.protocol !== 'AUTO' ? `Protocol ${msg.protocol}` : '';

    const el = document.createElement('div');
    el.className = 'msg msg-user';
    el.innerHTML = `
      <div class="msg-avatar user-av">✦</div>
      <div class="msg-bubble">
        ${protoLabel ? `<div class="msg-bubble-meta"><span class="msg-protocol-badge">${protoLabel}</span><span class="msg-timestamp">${formatTime(msg.ts)}</span></div>` : `<div class="msg-bubble-meta"><span class="msg-timestamp">${formatTime(msg.ts)}</span></div>`}
        ${escHtml(msg.content).replace(/\n/g,'<br>')}
      </div>
    `;
    conv.appendChild(el);
    conv.scrollTop = conv.scrollHeight;
  }

  function renderAiMsg(msg) {
    const conv = document.getElementById('conversation');
    const el = document.createElement('div');
    el.className = 'msg msg-ai';

    const rendered = renderAiContent(msg.content);

    el.innerHTML = `
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
    conv.appendChild(el);
    conv.scrollTop = conv.scrollHeight;
  }

  function renderAiContent(text) {
    // Parse sections from the AI response into styled output
    let html = '';

    // Look for protocol sections (e.g. "PROTOCOL A —", "VERDICT:", "OPEN QUESTIONS:")
    const sectionRegex = /^(#{1,3}\s+.+|[A-Z][A-Z\s\-]+:)\s*$/m;

    // Highlight thread references T-###
    const withThreadRefs = text.replace(/\b(T-\d{3})\b/g,
      '<span class="ai-thread-ref">$1</span>');

    // Highlight VERDICT lines
    const withVerdicts = withThreadRefs.replace(
      /VERDICT:\s*(SURVIVES_WITH_COSTS|SURVIVES|FAILS)/g,
      (_, v) => {
        const cls = v === 'SURVIVES' ? 'survives' : v === 'SURVIVES_WITH_COSTS' ? 'survives-costs' : 'fails';
        return `<span class="ai-verdict ${cls}">◈ VERDICT: ${v}</span>`;
      }
    );

    // Convert markdown-ish headings to section labels
    const withSections = withVerdicts.replace(/^(#{1,3})\s+(.+)$/gm, (_, h, title) => {
      return `</div><div class="ai-section"><div class="ai-section-label">${title}</div><div class="ai-section-body">`;
    });

    // Convert **bold** to styled spans
    const withBold = withSections.replace(/\*\*(.+?)\*\*/g, '<strong style="color:var(--text-bright)">$1</strong>');

    // Convert bullet lines with "•" or "-" at start
    const withBullets = withBold.replace(/^[•\-]\s+(.+)$/gm, '<span class="ai-tension">$1</span>');

    // Newlines to breaks
    const final = withBullets.replace(/\n/g, '<br>');

    return `<div class="ai-section-body">${final}</div>`;
  }

  function addLoadingBubble() {
    const conv = document.getElementById('conversation');
    const el = document.createElement('div');
    el.className = 'msg msg-ai msg-loading';
    el.innerHTML = `
      <div class="msg-avatar ai-av">◈</div>
      <div class="msg-bubble">
        <div class="typing-dots">
          <span></span><span></span><span></span>
        </div>
        <span style="font-family:var(--mono); font-size:0.68rem; color:var(--text-dim);">Analyzing…</span>
      </div>`;
    conv.appendChild(el);
    conv.scrollTop = conv.scrollHeight;
    return el;
  }

  // ── Thread Registry ───────────────────────────────────────────────────────
  function renderThreadList() {
    const list    = document.getElementById('threadList');
    const search  = document.getElementById('threadSearch').value.toLowerCase().trim();
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

    list.innerHTML = filtered.map((t, i) => {
      const typeClass = { cosmic:'cosmic', civil:'civil', char:'char', theme:'theme', reveal:'reveal' }[t.type] || 'theme';
      return `
        <div class="thread-item${t._selected ? ' selected' : ''}" data-thread-id="${t.id}"
          onclick="WR.toggleThreadSelect('${escAttr(t.id)}')"
          ondblclick="WR.editThread('${escAttr(t.id)}')">
          <div class="thread-type-dot ${typeClass}"></div>
          <div class="thread-id">${escHtml(t.id)}</div>
          <div class="thread-name">${escHtml(t.name)}</div>
          <span class="badge-inline badge-${typeClass}" style="font-size:0.55rem;">${t.status || 'ACTIVE'}</span>
        </div>`;
    }).join('');
  }

  function toggleThreadSelect(id) {
    const t = _threads.find(x => x.id === id);
    if (t) t._selected = !t._selected;
    renderThreadList();
  }

  function openThreadModal(editId) {
    document.getElementById('threadModalTitle').textContent = editId ? 'Edit Thread' : 'Add Thread to Registry';
    document.getElementById('btnDeleteThread').style.display = editId ? 'inline-flex' : 'none';

    if (editId) {
      const t = _threads.find(x => x.id === editId);
      if (!t) return;
      document.getElementById('editThreadIdx').value    = editId;
      document.getElementById('editThreadId').value     = t.id;
      document.getElementById('editThreadName').value   = t.name;
      document.getElementById('editThreadType').value   = t.type;
      document.getElementById('editThreadSeasons').value= t.seasons || '';
      document.getElementById('editThreadStatus').value = t.status || 'ACTIVE';
      document.getElementById('editThreadAxis').value   = t.axis || '';
      document.getElementById('editThreadTensions').value  = t.tensions || '';
      document.getElementById('editThreadQuestions').value = t.questions || '';
      document.getElementById('editThreadChekhov').value   = t.chekhov || '';
      document.getElementById('editThreadConnections').value = t.connections || '';
    } else {
      document.getElementById('editThreadIdx').value    = '';
      document.getElementById('editThreadId').value     = `T-${String(_threads.length + 1).padStart(3,'0')}`;
      ['editThreadName','editThreadSeasons','editThreadAxis','editThreadTensions',
       'editThreadQuestions','editThreadChekhov','editThreadConnections'].forEach(id => {
        document.getElementById(id).value = '';
      });
      document.getElementById('editThreadStatus').value = 'ACTIVE';
      document.getElementById('editThreadType').value   = 'cosmic';
    }

    document.getElementById('threadModal').classList.add('open');
  }

  function editThread(id) {
    openThreadModal(id);
  }

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
      if (idx !== -1) _threads[idx] = thread;
    } else {
      _threads.push(thread);
    }

    // Sync chekhov debts
    if (thread.chekhov) {
      thread.chekhov.split('\n').filter(Boolean).forEach(line => {
        const existing = _chekhov.find(c => c.threadId === thread.id && c.desc === line.trim());
        if (!existing) {
          _chekhov.push({ threadId: thread.id, desc: line.trim(), ep: '', paid: false });
        }
      });
    }

    persist();
    renderThreadList();
    renderChekhovList();
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

  // Auto-extract touched threads from AI response
  function extractTouchedThreads(text) {
    const matches = [...new Set([...text.matchAll(/\b(T-\d{3})\b/g)].map(m => m[1]))];
    matches.forEach(id => {
      if (!document.querySelector(`[data-thread-delta="${id}"]`)) {
        addDeltaThreadTag(id);
      }
    });
  }

  // ── Session Delta ─────────────────────────────────────────────────────────
  function addDeltaTag(listId, text) {
    if (!text.trim()) return;
    const list = document.getElementById(listId);
    const tag  = document.createElement('div');
    tag.className = 'delta-tag';
    tag.innerHTML = `${escHtml(text)} <span class="delta-tag-remove" onclick="this.parentElement.remove()">×</span>`;
    list.appendChild(tag);
  }

  function addDeltaThreadTag(id) {
    const list = document.getElementById('deltaThreads');
    if (list.querySelector(`[data-thread-delta="${id}"]`)) return;
    const tag = document.createElement('div');
    tag.className = 'delta-tag';
    tag.setAttribute('data-thread-delta', id);
    tag.innerHTML = `${escHtml(id)} <span class="delta-tag-remove" onclick="this.parentElement.remove()">×</span>`;
    list.appendChild(tag);
  }

  function saveDelta() {
    const decisions = [...document.querySelectorAll('#deltaDecisions .delta-tag')]
      .map(el => el.textContent.replace('×','').trim()).filter(Boolean);
    const deferred  = [...document.querySelectorAll('#deltaDeferred .delta-tag')]
      .map(el => el.textContent.replace('×','').trim()).filter(Boolean);
    const threads   = [...document.querySelectorAll('#deltaThreads .delta-tag')]
      .map(el => el.textContent.replace('×','').trim()).filter(Boolean);
    const updates   = document.getElementById('deltaUpdates').value.trim();

    if (decisions.length === 0 && deferred.length === 0) {
      toast('Add at least one decision or deferred item', 'error'); return;
    }

    const delta = {
      date:      new Date().toLocaleDateString(),
      topic:     _settings.sessionTopic || 'Session',
      decisions, deferred, threads, updates,
    };

    _deltas.unshift(delta);
    persist();
    renderDeltaHistory();

    // Clear delta form
    document.getElementById('deltaDecisions').innerHTML = '';
    document.getElementById('deltaDeferred').innerHTML  = '';
    document.getElementById('deltaThreads').innerHTML   = '';
    document.getElementById('deltaUpdates').value       = '';

    toast('Session delta saved', 'success');
  }

  function renderDeltaHistory() {
    const list = document.getElementById('deltaList');
    if (_deltas.length === 0) {
      list.innerHTML = `<div class="empty-state"><div class="empty-icon">◈</div>No sessions yet</div>`;
      return;
    }
    list.innerHTML = _deltas.slice(0,10).map((d, i) => `
      <div class="delta-item" onclick="WR.loadDelta(${i})">
        <div class="delta-date">${d.date} — ${escHtml(d.topic)}</div>
        <div class="delta-decisions">${d.decisions.length} decision(s) · ${d.deferred.length} deferred</div>
        ${d.threads.length ? `<div class="delta-decisions">Threads: ${d.threads.join(', ')}</div>` : ''}
      </div>`).join('');
  }

  function loadDelta(idx) {
    const d = _deltas[idx];
    if (!d) return;
    toast(`Loaded: ${d.topic}`, 'info');
    // Switch to history tab
    setLeftTab('history');
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
    if (_chekhov[idx]) {
      _chekhov[idx].paid = !_chekhov[idx].paid;
      persist();
      renderChekhovList();
    }
  }

  function addChekhovDebt() {
    const desc = prompt('Enter Chekhov debt description:');
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
  }

  function quickProtocol(p) {
    setProtocol(p);
    document.getElementById('msgInput').focus();
    const placeholders = {
      A: 'Which thread do you want to trace? (e.g. "Trace the Echo Ships thread — T-001")',
      B: 'Describe the decision to map: who, what, when. (e.g. "If Kaori turns double agent in E23, what does that force?")',
      C: 'What elements should be tested for coherence? (e.g. "Test the Singer waking and the Pinning revelation — are they doing the same work?")',
      D: 'What structural question needs options? (e.g. "Should Noel be kidnapped in E28 or E32?")',
      E: 'Which story phase should I audit? (e.g. "Run a tension audit on everything active in S3")',
      F: 'State the decision to stress-test: (e.g. "Break this: Kaelen witnesses Taro in E44 before the Pinning is named")',
    };
    document.getElementById('msgInput').placeholder = placeholders[p] || '';
  }

  function openSessionModal() {
    document.getElementById('sessionModel').value      = _settings.model;
    document.getElementById('sessionDepth').value      = _settings.depth;
    document.getElementById('sessionAutoFailure').value= _settings.autoFailure;
    document.getElementById('sessionTopic').value      = _settings.sessionTopic;
    document.getElementById('sessionModal').classList.add('open');
  }

  function saveSettings() {
    _settings.model        = document.getElementById('sessionModel').value;
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
  function clearConversation() {
    if (_conversation.length > 0 && !confirm('Start a new session? Current conversation will be cleared.')) return;
    _conversation = [];
    const conv = document.getElementById('conversation');
    // Remove all messages but keep welcome state
    [...conv.children].forEach(el => {
      if (!el.classList.contains('wr-welcome')) el.remove();
    });
    document.getElementById('welcomeState').style.display = 'flex';
    document.getElementById('headerPhaseWrap').style.display = 'none';
    toast('New session started', 'info');
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

    // If clicking the already-open side → close everything
    if ((openingLeft && leftOpen) || (openingRight && rightOpen)) {
      closeFlyouts();
      return;
    }

    // Close both, then open the requested side
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

  function closeModal(id) {
    document.getElementById(id).classList.remove('open');
  }

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
      const key = 'wr_forge_state';
      const state = { threads: _threads, deltas: _deltas, chekhov: _chekhov, settings: _settings };
      // Use in-memory only (no localStorage per artifact rules in this environment)
      // In production PHP, you'd save via AJAX to the server
      _persistedState = state;
    } catch(e) {}
  }

  let _persistedState = null;

  function loadPersisted() {
    // In production, load from server via PHP session or DB
    // For now, state is in-memory per session
  }

  // ── Init ──────────────────────────────────────────────────────────────────
  function init() {
    loadPersisted();
    renderFailureModes();
    renderThreadList();
    renderChekhovList();
    renderDeltaHistory();

    // Left tab switching
    document.querySelectorAll('.left-tab').forEach(btn => {
      btn.addEventListener('click', () => setLeftTab(btn.dataset.ltab));
    });

    // Right tab switching
    document.querySelectorAll('.right-tab').forEach(btn => {
      btn.addEventListener('click', () => setRightTab(btn.dataset.rtab));
    });

    // Thread search & filter
    document.getElementById('threadSearch').addEventListener('input', renderThreadList);
    document.getElementById('threadFilters').addEventListener('click', e => {
      const btn = e.target.closest('.tfilter');
      if (!btn) return;
      document.querySelectorAll('.tfilter').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      renderThreadList();
    });

    // Delta inputs on Enter
    document.getElementById('deltaDecisionInput').addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        addDeltaTag('deltaDecisions', e.target.value);
        e.target.value = '';
      }
    });
    document.getElementById('deltaDeferredInput').addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        addDeltaTag('deltaDeferred', e.target.value);
        e.target.value = '';
      }
    });

    // Textarea auto-resize
    const msgInput = document.getElementById('msgInput');
    msgInput.addEventListener('input', () => autoResizeTextarea(msgInput));
    msgInput.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
      }
    });

    // Modal overlay close
    document.querySelectorAll('.wr-modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.classList.remove('open');
      });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        closeFlyouts();
        document.querySelectorAll('.wr-modal-overlay.open').forEach(m => m.classList.remove('open'));
      }
    });

    // Update theme icon on load
    const theme = document.documentElement.getAttribute('data-theme');
    document.getElementById('themeBtn').querySelector('i').className =
      theme === 'light' ? 'bi bi-sun' : 'bi bi-moon-stars';
  }

  return {
    init, send, setProtocol, quickProtocol,
    toggleFlyout, closeFlyouts,
    openThreadModal, editThread, saveThread, deleteThread, toggleThreadSelect,
    openSessionModal, saveSettings,
    clearConversation, saveDelta, loadDelta, renderDeltaHistory,
    toggleChekhov, addChekhovDebt,
    copyMsg, toggleTheme, closeModal, toast,
  };
})();

document.addEventListener('DOMContentLoaded', () => WR.init());
</script>
<?php if (file_exists(__DIR__ . '/js/sage-home-button.js')): ?>
<!--
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
-->
<?php endif; ?>
</body>
</html>
