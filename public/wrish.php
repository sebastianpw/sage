<?php
// public/wrish.php
// ─────────────────────────────────────────────────────────────────────────────
// WRISH — Writers Wish
// Autonomous AI narrative brainstorming/invention engine.
// Sits alongside WROOM (human-authoring detail layer). Wrish plans and
// executes multi-step, pool-fueled season invention with human gating.
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
<title>Wrish — Writers Wish</title>
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
   WRISH — Design System
   Same Forge design language as wroom (Industrial Alchemist), amber accent,
   but with a violet secondary accent to visually distinguish "invention
   layer" from wroom's amber "authoring layer" at a glance.
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
.phase-indicator {
  font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim);
  padding: 4px 10px; background: var(--card);
  border: 1px solid var(--border); border-radius: var(--radius);
  display: flex; align-items: center; gap: 6px;
}
.phase-indicator .phase-dot {
  width: 6px; height: 6px; border-radius: 50%; background: var(--violet);
  animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }

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
.wi-flyout-right { right: 0; border-left: 1px solid var(--border);  transform: translateX(100%); }
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

/* ── Context / form fields (shared look) ───────────────────────────────── */
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
.wi-welcome-protocols { display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; max-width: 500px; }
.proto-pill {
  padding: 4px 10px; border-radius: 20px; border: 1px solid var(--border);
  font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim);
  cursor: pointer; transition: all 0.15s;
}
.proto-pill:hover { border-color: var(--violet); color: var(--violet); background: var(--violet-dim); }

.msg { display: flex; gap: 12px; animation: msgIn 0.25s ease; }
@keyframes msgIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
.msg-user { flex-direction: row-reverse; }

.msg-avatar {
  width: 32px; height: 32px; border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; flex-shrink: 0; font-family: var(--mono);
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

.ai-section-body { font-size: 0.84rem; color: var(--text); line-height: 1.75; }
.ai-tension {
  display: block; padding: 6px 10px; margin: 4px 0; background: var(--surface);
  border-left: 2px solid var(--violet); font-family: var(--mono); font-size: 0.73rem;
  color: var(--text); border-radius: 0 var(--radius) var(--radius) 0;
}
.ai-step-block {
  display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px;
  font-family: var(--mono); font-size: 0.72rem; font-weight: 700; letter-spacing: 1px;
  background: var(--violet-dim); border: 1px solid var(--violet); color: var(--violet);
}

.msg-loading .msg-bubble { display: flex; align-items: center; gap: 10px; padding: 14px 16px; }
.typing-dots span {
  display: inline-block; width: 6px; height: 6px; border-radius: 50%;
  background: var(--blue); margin: 0 2px; animation: typing 1.2s ease-in-out infinite;
}
.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing { 0%,80%,100% { transform: translateY(0); opacity: 0.4; } 40% { transform: translateY(-6px); opacity: 1; } }

/* ── INPUT BAR ──────────────────────────────────────────────────────────── */
.wi-input-bar { padding: 12px 16px; border-top: 1px solid var(--border); background: var(--surface); flex-shrink: 0; }
.input-bar-top { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; flex-wrap: wrap; }
.protocol-selector { display: flex; gap: 4px; flex-wrap: wrap; width: 100%; }
.proto-btn {
  padding: 3px 8px; border-radius: 20px; border: 1px solid var(--border);
  font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim);
  cursor: pointer; background: transparent; transition: all 0.12s; letter-spacing: 0.5px;
}
.proto-btn:hover, .proto-btn.active { border-color: var(--violet); color: var(--violet); background: var(--violet-dim); }

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

/* ── RIGHT PANEL — Battle Plan Stepper ─────────────────────────────────── */
.plan-header {
  padding: 12px 14px; border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.plan-title { font-family: var(--mono); font-size: 0.78rem; color: var(--violet); font-weight: 700; }
.plan-premise { font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim); margin-top: 4px; line-height: 1.5; }
.plan-empty { padding: 24px 16px; text-align: center; }

.step-list { flex: 1; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 6px; }
.step-item {
  padding: 9px 11px; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); position: relative;
}
.step-item.status-pending { opacity: 0.55; }
.step-item.status-active   { border-color: var(--violet); background: var(--violet-dim); }
.step-item.status-done     { border-left: 3px solid var(--green); cursor: pointer; transition: all 0.15s; }
.step-item.status-done:hover { border-color: var(--violet); }
.step-item.status-skipped  { opacity: 0.4; text-decoration: line-through; }
.step-num {
  font-family: var(--mono); font-size: 0.6rem; color: var(--text-dim);
  display: inline-block; width: 18px;
}
.step-title { font-family: var(--sans); font-size: 0.78rem; font-weight: 700; color: var(--text); display: inline; }
.step-purpose { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); margin-top: 3px; line-height: 1.4; }
.step-result { font-family: var(--mono); font-size: 0.65rem; color: var(--text); margin-top: 5px; padding-top: 5px; border-top: 1px dashed var(--border); line-height: 1.5; }
.step-status-icon { font-size: 0.7rem; margin-right: 4px; }
.step-status-icon.done { color: var(--green); }
.step-status-icon.active { color: var(--violet); }
.step-status-icon.pending { color: var(--text-dim); }
.step-status-icon.skipped { color: var(--text-dim); }

.plan-actions { padding: 10px 14px; border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; }

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

.thread-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

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
.hint { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); margin-top: 3px; }

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

<div class="wi-flyout-backdrop" id="flyoutBackdrop" onclick="WI.closeFlyouts()"></div>

<header class="wi-header">
  <button class="wi-hamburger" id="hamburgerLeft" onclick="WI.toggleFlyout('left')" title="Chats &amp; Settings">
    <i class="bi bi-layout-text-sidebar"></i>
  </button>
  <div class="wi-logo" style="flex:1; justify-content:center;">
    <div class="wi-logo-icon">✦</div>
    <span>Wrish</span>
  </div>
  <div style="display:flex;align-items:center;gap:6px;">
    <div class="phase-indicator" id="headerPhaseWrap" style="display:none;">
      <div class="phase-dot"></div>
      <span id="headerPhase">—</span>
    </div>
    <button class="btn-icon-sm" onclick="WI.clearConversation()" title="New Session" style="width:32px;height:32px;">
      <i class="bi bi-plus-circle"></i>
    </button>
    <button class="btn-icon-sm" onclick="WI.toggleTheme()" id="themeBtn" title="Toggle Theme" style="display:none;width:32px;height:32px;">
      <i class="bi bi-moon-stars"></i>
    </button>
    <button class="wi-hamburger" id="hamburgerRight" onclick="WI.toggleFlyout('right')" title="Battle Plan">
      <i class="bi bi-list-check"></i>
    </button>
  </div>
</header>

<!-- LEFT FLYOUT — Chats / Settings -->
<div class="wi-flyout wi-flyout-left" id="flyoutLeft">
  <div class="flyout-head">
    <span class="flyout-head-title">Sessions</span>
    <button class="flyout-close" onclick="WI.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
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
          <div class="empty-state"><div class="empty-icon">✦</div>No past sessions</div>
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
          <label class="ctx-label">Response Depth</label>
          <select class="ctx-select" id="setDepth">
            <option value="brief">Brief — key beats only</option>
            <option value="standard" selected>Standard — full step output</option>
            <option value="deep">Deep — maximum invention detail</option>
          </select>
        </div>
        <div class="ctx-group">
          <label class="ctx-label">Friction Intensity</label>
          <select class="ctx-select" id="setFriction">
            <option value="low">Low — rare, high-leverage rule-breaks only</option>
            <option value="medium" selected>Medium — a few deliberate breaks per step</option>
            <option value="high">High — actively hunt for breakable patterns</option>
          </select>
        </div>
        <div class="ctx-group">
          <label class="ctx-label">Auto-Advance Steps</label>
          <select class="ctx-select" id="setAutoAdvance">
            <option value="0" selected>No — wait for me between steps</option>
            <option value="1">Yes — AI may continue on its own, still clearly labeled</option>
          </select>
        </div>
        <div class="ctx-group">
          <label class="ctx-label">Overwrite Done State</label>
          <select class="ctx-select" id="setOverwriteDone">
            <option value="0" selected>No — Stop when plan is complete</option>
            <option value="1">Yes — Allow manual continuation past plan end</option>
          </select>
        </div>
        <div class="ctx-group">
          <label class="ctx-label">Session Topic</label>
          <input class="ctx-input" type="text" id="setSessionTopic" placeholder="Season Three battle plan">
        </div>
        <div class="sep"></div>
        <div class="ctx-group">
          <label class="ctx-label">Additional Context / Notes</label>
          <textarea class="ctx-textarea tall" id="setContextExtra"
            placeholder="Canon facts, prior season summary, constraints…"></textarea>
        </div>
        <div class="sep"></div>
        <button class="btn-primary" style="width:100%;font-size:0.72rem;" onclick="WI.saveSettings()">
          <i class="bi bi-floppy"></i> Save Settings
        </button>
      </div>

    </div>
  </div>
</div>

<!-- RIGHT FLYOUT — Battle Plan Stepper -->
<div class="wi-flyout wi-flyout-right" id="flyoutRight">
  <div class="flyout-head">
    <span class="flyout-head-title">Battle Plan</span>
    <button class="flyout-close" onclick="WI.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
  </div>
  <div style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
    <div id="planHeaderWrap"></div>
    <div class="step-list" id="stepList">
      <div class="plan-empty empty-state">
        <div class="empty-icon">✦</div>
        No battle plan yet.<br>Ask Wrish to plan a season to begin.
      </div>
    </div>
    <div class="plan-actions" id="planActions" style="display:none;">
      <button class="btn-primary" id="btnNextStepFlyout" style="width:100%;font-size:0.72rem;" onclick="WI.nextStep()">
        <i class="bi bi-play-fill"></i> <span id="nextStepFlyoutLabel">Next Step</span>
      </button>
    </div>
  </div>
</div>

<!-- MAIN -->
<main class="wi-main">
  <div class="wi-conversation" id="conversation">
    <div class="wi-welcome" id="welcomeState">
      <div class="wi-welcome-sigil">✦</div>
      <div class="wi-welcome-title">Wrish — Writers Wish</div>
      <div class="wi-welcome-body">
        An autonomous invention engine. The first step always plans a full battle
        plan; every step after that writes one focused piece of it, deterministically,
        in order — fueled by pools of vocabulary, imagery, tone, and deliberate rule-breaks.
      </div>
      <div class="wi-welcome-protocols">
        <button class="proto-pill" onclick="WI.nextStep()">✦ Begin — Plan a Season</button>
      </div>
    </div>
  </div>

  <div class="wi-input-bar">
    <div class="input-bar-top">
      <div class="protocol-selector" style="flex:1; justify-content:flex-end;">
        <button class="proto-btn" id="btnDownloadResult" onclick="WI.downloadResult()" style="display:none; border-color:var(--green); color:var(--green); margin-right:8px;">
          <i class="bi bi-download"></i> Download Result
        </button>
        <button class="proto-btn" id="btnModeToggle" onclick="WI.toggleOfflineMode()" style="border-color:var(--violet); color:var(--violet);">
          <i class="bi bi-cloud-check"></i> API Mode
        </button>
      </div>
    </div>

    <div class="input-bar-bottom">
      <textarea class="wi-textarea" id="msgInput"
        placeholder="Optional — add anything you want considered for the NEXT step, then click Next Step"
        rows="1"></textarea>

      <button class="btn-send" id="btnInject" onclick="WI.injectAiResponse()" style="display:none; background:var(--blue); color:#fff; font-size: 18px;" title="Inject pasted AI response for the current step">
        <i class="bi bi-robot"></i>
      </button>

      <button class="btn-send" id="btnNextStepMain" onclick="WI.nextStep()" title="Run the next deterministic step">
        <i class="bi bi-play-fill" id="nextStepIcon"></i>
        <div class="spin-sm"></div>
      </button>
    </div>
  </div>
</main>

</div><!-- /wi-layout -->

<!-- EXPORT MODE: correct the most recent pasted response, if it was
     wrong. Only ever touches the tip of the conversation — nothing that
     has already advanced plan/step state can be edited this way. -->
<div class="wi-modal-overlay" id="replaceLastModal">
  <div class="wi-modal">
    <div class="wi-modal-header">
      <div class="wi-modal-title">Replace Last Response</div>
      <button class="wi-modal-close" onclick="WI.closeModal('replaceLastModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="wi-modal-body">
      <div class="ctx-group">
        <label class="ctx-label">New content for the most recent step</label>
        <textarea class="ctx-textarea tall" id="replaceLastInput" style="min-height:220px;"></textarea>
      </div>
      <div class="hint">This replaces the last pasted response in place — nothing else about the run changes.</div>
    </div>
    <div class="wi-modal-footer">
      <button class="btn-secondary" onclick="WI.closeModal('replaceLastModal')">Cancel</button>
      <button class="btn-primary" onclick="WI.confirmReplaceLast()">Replace</button>
    </div>
  </div>
</div>

<!-- RENAME CHAT MODAL -->
<div class="wi-modal-overlay" id="renameChatModal">
  <div class="wi-modal" style="max-width:400px;">
    <div class="wi-modal-header">
      <div class="wi-modal-title">Rename Session</div>
      <button class="wi-modal-close" onclick="WI.closeModal('renameChatModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="wi-modal-body">
      <input type="hidden" id="renameChatId" value="">
      <div class="ctx-group">
        <label class="ctx-label">Session Name</label>
        <input class="ctx-input" type="text" id="renameChatTitle" placeholder="e.g. Episode 3">
      </div>
    </div>
    <div class="wi-modal-footer">
      <button class="btn-secondary" onclick="WI.closeModal('renameChatModal')">Cancel</button>
      <button class="btn-primary" onclick="WI.saveRenameChat()">Save</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
window.WI = (function() {
  'use strict';

  function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  // ── State ─────────────────────────────────────────────────────────────
  // Note: no more "protocol" selector state — turn kind (plan vs. step) is
  // fully deterministic now, decided server-side by WrishEngine from
  // conversation/plan position, never chosen by the human or inferred from
  // AI prose. See WrishEngine::determineNextTurn().
  let _pools            = [];
  let _plan              = null;   // { id, title, premise, status, steps: [...] }
  let _conversation      = [];
  let _chatSessions      = [];
  let _chatSessionId     = generateUUID();
  let _chatSessionTitle  = 'New Session';
  let _activeRunId       = null;
  let _settings = {
    depth: 'standard',
    frictionIntensity: 'medium',
    autoAdvance: false, // off by default, always — must be explicitly enabled
    sessionTopic: '',
    contextExtra: '',
    offlineMode: false,
    overwriteDone: false
  };
  let _isLoading = false;
  let _nextTurnKind = 'plan'; // 'plan' | 'step' | 'done' — kept in sync with server via next_turn_info

  // Pagination state for the Chats list.
  let _chatSessionsPg = { page: 1, limit: 12, total_pages: 1 };

  // ── API helper ───────────────────────────────────────────────────────
  async function api(payload, method) {
    method = method || 'POST';
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    let url = '/wrish_api.php';
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

  // ── Shared pagination bar wiring ──────────────────────────────────────
  // Wires up the prev/next buttons + editable page-index input for a given
  // list id (e.g. "chatSessionsList"), calling `loader(page)` whenever the
  // page should change.
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

  // ── Determine + reflect next-turn state ─────────────────────────────
  // Called after every commit and on load, so both the flyout button and
  // the main-input-area button always agree with the server's
  // deterministic view of what's next — never guessed client-side.
  async function refreshNextTurnState() {
    try {
      const data = await api({ action: 'next_turn_info', chat_session_id: _chatSessionId }, 'GET');
      _nextTurnKind = data.turn.kind;
    } catch (e) {
      _nextTurnKind = 'plan';
    }
    updateNextStepButtons();
  }

  function updateNextStepButtons() {
    const labels = { plan: 'Plan a Season', step: 'Next Step', done: 'Plan Complete' };
    const label = labels[_nextTurnKind] || 'Next Step';

    const flyoutLabel = document.getElementById('nextStepFlyoutLabel');
    if (flyoutLabel) flyoutLabel.textContent = label;
    const flyoutBtn = document.getElementById('btnNextStepFlyout');
    if (flyoutBtn) flyoutBtn.disabled = (_nextTurnKind === 'done');

    const mainBtn = document.getElementById('btnNextStepMain');
    if (mainBtn) {
      mainBtn.title = label;
      mainBtn.disabled = (_nextTurnKind === 'done');
    }
    
    const dlBtn = document.getElementById('btnDownloadResult');
    if (dlBtn) dlBtn.style.display = (_nextTurnKind === 'done') ? 'inline-flex' : 'none';
  }

  // ── The single entry point for advancing the deterministic flow ─────
  // Used by BOTH the flyout's Next Step button and the main input area's
  // Next Step button — same action, two places, per explicit request. In
  // API Mode this makes the live call; in Export Mode this builds and
  // downloads the next prompt instead (the human pastes the result back
  // via the robot/inject button).
  async function nextStep() {
    if (_isLoading) return;
    document.getElementById('welcomeState').style.display = 'none';

    if (_settings.offlineMode) {
      await exportCurrentStepPrompt();
      return;
    }

    const input = document.getElementById('msgInput');
    const userInput = input.value.trim();
    input.value = '';
    autoResizeTextarea(input);

    _isLoading = true;
    setMainButtonLoading(true);

    const loadingEl = addLoadingBubble();

    try {
      const data = await api({
        action: 'run_step',
        chat_session_id: _chatSessionId,
        message: userInput || null,
        settings: _settings,
        run_id: _activeRunId || null
      });
      loadingEl.remove();
      applyTurnResult(data, userInput);
    } catch (e) {
      loadingEl.remove();
      toast('Error: ' + e.message, 'error');
    } finally {
      _isLoading = false;
      setMainButtonLoading(false);
      await refreshNextTurnState();

      // auto_advance: if enabled and there's still a pending step, chain
      // automatically. Off by default always — this only ever runs when
      // the human has explicitly turned it on in Settings.
      if (_settings.autoAdvance && !_settings.offlineMode && _nextTurnKind === 'step' && !_isLoading) {
        nextStep();
      }
    }
  }

  function setMainButtonLoading(loading) {
    const btn = document.getElementById('btnNextStepMain');
    if (!btn) return;
    btn.classList.toggle('loading', loading);
    btn.disabled = loading;
  }

  function applyTurnResult(data, userDisplayText) {
    if (userDisplayText) {
      renderUserMsg({ role: 'user', display_content: userDisplayText, ts: Date.now() });
    }
    const aiMsg = { role: 'assistant', content: data.ai_text || '', ts: Date.now() };
    _conversation.push(aiMsg);
    renderAiMsg(aiMsg);

    if (data.plan) {
      _plan = data.plan;
      renderPlan();
    }
    if (data.turn_type === 'plan_created') {
      toast('Battle plan created and loaded into stepper', 'success');
    } else if (data.turn_type === 'done') {
      toast('Battle plan complete — no more steps.', 'success');
    }
    if (data.run) {
      _activeRunId = data.run.id;
      renderRunStatus(data.run);
    }
    renderChatSessionsList();

    if (_conversation.length === 1 || (_conversation.length === 2 && userDisplayText)) {
      _chatSessionTitle = (userDisplayText || 'Wrish Session').substring(0, 40);
      if (!_chatSessions.find(c => c.id === _chatSessionId)) {
        _chatSessions.unshift({ id: _chatSessionId, title: _chatSessionTitle, updated_at: new Date().toISOString() });
      }
    }
  }

  // ── Export Mode ──────────────────────────────────────────────────────
  // Building and downloading the next step's prompt is now the SAME
  // "Next Step" action as API Mode — no separate button, no separate
  // mental model. The difference is purely: API Mode calls the AI live;
  // Export Mode hands you a file to run externally.
  async function exportCurrentStepPrompt() {
    const input = document.getElementById('msgInput');
    const userInput = input.value.trim();

    try {
      const data = await api({
        action: 'export_step_prompt',
        chat_session_id: _chatSessionId,
        message: userInput || null,
        settings: _settings
      });

      const blob = new Blob([data.full_context], { type: 'text/plain' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `wrish_step_prompt_${new Date().toISOString().replace(/[:.]/g,'-')}.txt`;
      a.click();
      URL.revokeObjectURL(url);

      toast('Full package downloaded (context + thread + next prompt). Paste the response back in with the robot icon when ready.', 'info', 5000);
    } catch (e) {
      toast('Error: ' + e.message, 'error');
    }
  }
  
  async function downloadResult() {
    try {
      const data = await api({ action: 'export_full_result', chat_session_id: _chatSessionId }, 'GET');
      const blob = new Blob([data.export_text], { type: 'text/plain' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `wrish_result_${_chatSessionId}.txt`;
      a.click();
      URL.revokeObjectURL(url);
      toast('Result downloaded', 'success');
    } catch (e) {
      toast('Error: ' + e.message, 'error');
    }
  }

  // Pastes a response for the CURRENT deterministic step (whichever one
  // determineNextTurn() says is next) — zero API calls. The pasted content
  // stays fully editable in place (via Replace Last Response) until the
  // human clicks Next Step again, which commits it and advances.
  function injectAiResponse() {
    const input = document.getElementById('msgInput');
    const text = input.value.trim();
    if (!text) { toast('Paste the AI response into the text area first.', 'error'); return; }
    input.value = '';
    autoResizeTextarea(input);
    document.getElementById('welcomeState').style.display = 'none';

    (async () => {
      try {
        const data = await api({
          action: 'inject_step',
          chat_session_id: _chatSessionId,
          injected_ai_text: text,
          settings: _settings,
          run_id: _activeRunId || null
        });
        applyTurnResult(data, null);
        await refreshNextTurnState();
        toast('Response recorded for this step. Click Next Step when ready to continue.', 'success', 4500);
      } catch (e) {
        toast('Error: ' + e.message, 'error');
      }
    })();
  }

  // ── Replace Last Response (Export Mode correction) ──────────────────
  function openReplaceLastModal() {
    const lastAiMsg = [..._conversation].reverse().find(m => m.role === 'assistant');
    document.getElementById('replaceLastInput').value = lastAiMsg ? lastAiMsg.content : '';
    document.getElementById('replaceLastModal').classList.add('open');
  }

  async function confirmReplaceLast() {
    const newText = document.getElementById('replaceLastInput').value.trim();
    if (!newText) { toast('Enter replacement content first.', 'error'); return; }

    try {
      await api({ action: 'replace_last_response', chat_session_id: _chatSessionId, new_text: newText });
      closeModal('replaceLastModal');

      // Update in-memory + re-render the last assistant bubble in place.
      const lastAiMsg = [..._conversation].reverse().find(m => m.role === 'assistant');
      if (lastAiMsg) {
        lastAiMsg.content = newText;
        const nodes = document.querySelectorAll('.msg-ai');
        const lastNode = nodes[nodes.length - 1];
        if (lastNode) {
          const rendered = renderAiContent(newText);
          const bubble = lastNode.querySelector('.ai-section-body')?.parentElement;
          if (bubble) {
            const meta = bubble.querySelector('.msg-bubble-meta').outerHTML;
            bubble.innerHTML = meta + rendered;
          }
        }
      }
      toast('Replaced', 'success');
    } catch (e) {
      toast('Error: ' + e.message, 'error');
    }
  }

  // ── Rendering ────────────────────────────────────────────────────────────
  function renderUserMsg(msg) {
    const conv = document.getElementById('conversation');
    const displayText = msg.display_content || msg.content || '';
    if (!displayText) return; // no user-facing text to show (e.g. auto-advance with no input box text)

    const msgNode = document.createElement('div');
    msgNode.className = 'msg msg-user';
    msgNode.innerHTML = `
      <div class="msg-avatar user-av">✎</div>
      <div class="msg-bubble">
        <div class="msg-bubble-meta"><span class="msg-timestamp">${formatTime(msg.ts)}</span></div>
        ${escHtml(displayText).replace(/\n/g,'<br>')}
      </div>
    `;
    conv.appendChild(msgNode);
    conv.scrollTop = conv.scrollHeight;
  }

  function renderAiMsg(msg) {
    const conv = document.getElementById('conversation');
    const msgNode = document.createElement('div');
    msgNode.className = 'msg msg-ai';
    msgNode.dataset.msgId = msg.id || '';
    const rendered = renderAiContent(msg.content);

    const replaceBtn = _settings.offlineMode
      ? `<button onclick="WI.openReplaceLastModal()" style="background:none; border:none; color:var(--amber); cursor:pointer; font-size:11px; padding:0 4px;" title="Replace this response (Export Mode correction)"><i class="bi bi-pencil-square"></i></button>`
      : '';

    msgNode.innerHTML = `
      <div class="msg-avatar ai-av">✦</div>
      <div class="msg-bubble" style="max-width:calc(100% - 56px);">
        <div class="msg-bubble-meta">
          <span class="msg-protocol-badge">Wrish</span>
          <span class="msg-timestamp">${formatTime(msg.ts)}</span>
          <span style="margin-left:auto; display:flex; align-items:center;">
            ${replaceBtn}
            <button onclick="WI.copyMsg(this)" data-content="${escAttr(msg.content)}"
              style="background:none; border:none; color:var(--text-dim); cursor:pointer; font-size:11px; padding:0 4px;" title="Copy response">
              <i class="bi bi-clipboard"></i>
            </button>
          </span>
        </div>
        ${rendered}
      </div>
    `;
    conv.appendChild(msgNode);
    conv.scrollTop = conv.scrollHeight;
  }

  function renderAiContent(text) {
    const withHeaders = text.replace(/^(#{1,3})\s+(.+)$/gm, (_, h, title) => {
      return `</div><div class="ai-section"><div style="font-family:var(--mono);font-size:0.6rem;color:var(--violet);text-transform:uppercase;letter-spacing:1.5px;margin:10px 0 6px;padding-bottom:4px;border-bottom:1px solid var(--border);">${title}</div><div class="ai-section-body">`;
    });
    const withBold    = withHeaders.replace(/\*\*(.+?)\*\*/g, '<strong style="color:var(--text-bright)">$1</strong>');
    const withBullets = withBold.replace(/^[•\-]\s+(.+)$/gm, '<span class="ai-tension">$1</span>');
    const final       = withBullets.replace(/\n/g, '<br>');
    return `<div class="ai-section-body">${final}</div>`;
  }

  function addLoadingBubble() {
    const conv = document.getElementById('conversation');
    const msgNode = document.createElement('div');
    msgNode.className = 'msg msg-ai msg-loading';
    msgNode.innerHTML = `
      <div class="msg-avatar ai-av">✦</div>
      <div class="msg-bubble">
        <div class="typing-dots"><span></span><span></span><span></span></div>
        <span style="font-family:var(--mono); font-size:0.68rem; color:var(--text-dim);">Inventing…</span>
      </div>`;
    conv.appendChild(msgNode);
    conv.scrollTop = conv.scrollHeight;
    return msgNode;
  }

  // ── Battle Plan rendering ─────────────────────────────────────────────
  function renderPlan() {
    const headerWrap = document.getElementById('planHeaderWrap');
    const stepList   = document.getElementById('stepList');
    const actions    = document.getElementById('planActions');

    document.getElementById('headerPhaseWrap').style.display = 'flex';

    if (!_plan) {
      headerWrap.innerHTML = '';
      stepList.innerHTML = `<div class="plan-empty empty-state"><div class="empty-icon">✦</div>No battle plan yet.<br>Click Next Step to begin.</div>`;
      actions.style.display = 'flex';
      document.getElementById('headerPhase').textContent = _settings.sessionTopic || 'No active plan';
      return;
    }

    headerWrap.innerHTML = `
      <div class="plan-header">
        <div class="plan-title">${escHtml(_plan.title || 'Battle Plan')}</div>
        ${_plan.premise ? `<div class="plan-premise">${escHtml(_plan.premise)}</div>` : ''}
      </div>`;

    const icons = { pending: 'bi-circle', active: 'bi-play-circle-fill', done: 'bi-check-circle-fill', skipped: 'bi-slash-circle' };

    stepList.innerHTML = _plan.steps.map(s => `
      <div class="step-item status-${s.status}" ${s.status === 'done' ? `onclick="WI.scrollToStep(${s.step_order})"` : ''}>
        <span class="step-num">${s.step_order}.</span>
        <i class="bi ${icons[s.status] || 'bi-circle'} step-status-icon ${s.status}"></i>
        <span class="step-title">${escHtml(s.title)}</span>
        ${s.result_summary ? `<div class="step-result">${escHtml(s.result_summary.substring(0, 240))}${s.result_summary.length > 240 ? '…' : ''}</div>` : ''}
      </div>`).join('');

    actions.style.display = 'flex';
    const activeStep = _plan.steps.find(s => s.status === 'active');
    document.getElementById('headerPhase').textContent =
      activeStep ? `Step ${activeStep.step_order}: ${activeStep.title}` : (_settings.sessionTopic || _plan.title);
  }

  function renderRunStatus(run) {
    if (!run) return;
    const phaseEl = document.getElementById('headerPhase');
    if (!phaseEl) return;
    const base = phaseEl.textContent || '';
    const suffix = ` · Run: ${run.steps_completed}/${run.max_steps}${run.status !== 'running' ? ' (' + run.status + ')' : ''}`;
    if (!base.includes('· Run:')) {
      phaseEl.textContent = base + suffix;
    }
  }

  // ── Step Navigation ──────────────────────────────────────────────────
  function scrollToStep(stepOrder) {
    if (!_plan) return;
    const step = _plan.steps.find(s => s.step_order === stepOrder);
    if (!step || step.status !== 'done' || !step.result_summary) return;
    
    const convNode = document.getElementById('conversation');
    const aiNodes = convNode.querySelectorAll('.msg-ai');
    const aiMessages = _conversation.filter(m => m.role === 'assistant');
    
    // Strategy 1: Safest path — match the exact stored response content
    let msgIndex = aiMessages.findIndex(m => m.content === step.result_summary);
    
    // Strategy 2: If modified by human replacement, fallback to linear assumption
    if (msgIndex === -1 && stepOrder < aiMessages.length) {
      msgIndex = stepOrder;
    }
    
    // Strategy 3: Single-step unparsed-plan fallback case (Step 1 is the Battle Plan message itself)
    if (msgIndex === -1 && stepOrder === 1 && aiMessages.length > 0) {
      msgIndex = 0;
    }
    
    if (msgIndex !== -1 && aiNodes[msgIndex]) {
      const targetNode = aiNodes[msgIndex];
      targetNode.scrollIntoView({ behavior: 'smooth', block: 'start' });
      
      const bubble = targetNode.querySelector('.msg-bubble');
      if (bubble) {
        const oldTrans = bubble.style.transition;
        const oldBox = bubble.style.boxShadow;
        bubble.style.transition = 'box-shadow 0.3s ease';
        bubble.style.boxShadow = '0 0 0 2px var(--violet), 0 0 20px var(--violet-glow)';
        setTimeout(() => {
          bubble.style.boxShadow = oldBox;
          setTimeout(() => { bubble.style.transition = oldTrans; }, 300);
        }, 1500);
      }
      
      // Essential for mobile devices: the flyout's dark overlay completely
      // blocks visibility of the scrolled main window, so we must close it.
      if (window.innerWidth <= 768) {
        closeFlyouts();
      }
    }
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
      list.innerHTML = `<div class="empty-state"><div class="empty-icon">✦</div>No past sessions</div>`;
      renderPager('chatSessionsList', _chatSessionsPg);
      return;
    }
    list.innerHTML = _chatSessions.map(c => `
      <div class="delta-item ${c.id === _chatSessionId ? 'active-chat' : ''}" onclick="WI.loadChat('${c.id}')">
        <div class="delta-date">${new Date(c.updated_at).toLocaleDateString()}</div>
        <div class="delta-topic">${escHtml(c.title || 'Session')}</div>
        <button class="item-delete-btn" style="right: 30px;" onclick="WI.openRenameChatModal(event, '${c.id}', '${escAttr(c.title || '')}')" title="Rename Session">
          <i class="bi bi-pencil"></i>
        </button>
        <button class="item-delete-btn" onclick="WI.deleteChat(event, '${c.id}')" title="Delete Session">
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

  async function loadChat(id) {
    if (id === _chatSessionId) return;
    try {
      const data = await api({ action: 'load_chat', session_id: id }, 'GET');
      _chatSessionId = id;
      const sess = _chatSessions.find(c => c.id === id);
      _chatSessionTitle = sess ? sess.title : 'Loaded Session';
      _conversation = data.conversation || [];
      _plan = data.plan || null;
      _activeRunId = data.active_run ? data.active_run.id : null;

      if (data.settings) {
        _settings = Object.assign(_settings, data.settings);
        setOfflineMode(!!_settings.offlineMode);
        document.getElementById('setDepth').value = _settings.depth;
        document.getElementById('setFriction').value = _settings.frictionIntensity;
        document.getElementById('setAutoAdvance').value = _settings.autoAdvance ? '1' : '0';
        document.getElementById('setSessionTopic').value = _settings.sessionTopic || '';
        document.getElementById('setContextExtra').value = _settings.contextExtra || '';
        document.getElementById('setOverwriteDone').value = _settings.overwriteDone ? '1' : '0';
      }

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
      renderPlan();
      if (data.active_run) renderRunStatus(data.active_run);
      renderChatSessionsList();
      closeFlyouts();
      await refreshNextTurnState();
      toast('Session loaded', 'info');
    } catch (e) {
      toast('Error: ' + e.message, 'error');
    }
  }

  async function deleteChat(e, id) {
    e.stopPropagation();
    if (!confirm('Permanently delete this session transcript and its battle plan?')) return;
    try {
      await api({ action: 'delete_chat', session_id: id });
      _chatSessions = _chatSessions.filter(c => c.id !== id);
      if (_chatSessionId === id) clearConversation(true);
      else renderChatSessionsList();
      toast('Session deleted', 'success');
    } catch (e) {
      toast('Error: ' + e.message, 'error');
    }
  }

  // ── Settings / Mode ─────────────────────────────────────────────────
  function setOfflineMode(isOffline) {
    _settings.offlineMode = isOffline;
    const btn = document.getElementById('btnModeToggle');
    const nextIcon = document.getElementById('nextStepIcon');
    const btnInject = document.getElementById('btnInject');
    const msgInput = document.getElementById('msgInput');

    if (isOffline) {
      btn.innerHTML = `<i class="bi bi-file-earmark-arrow-down"></i> Export Mode`;
      btn.style.borderColor = 'var(--blue)'; btn.style.color = 'var(--blue)'; btn.style.background = 'var(--blue-dim)';
      nextIcon.className = 'bi bi-download';
      btnInject.style.display = 'flex';
      msgInput.placeholder = "Optional — extra context for the next step's exported prompt";
    } else {
      btn.innerHTML = `<i class="bi bi-cloud-check"></i> API Mode`;
      btn.style.borderColor = 'var(--violet)'; btn.style.color = 'var(--violet)'; btn.style.background = 'transparent';
      nextIcon.className = 'bi bi-play-fill';
      btnInject.style.display = 'none';
      msgInput.placeholder = "Optional — add anything you want considered for the NEXT step, then click Next Step";
    }
    // Re-render existing AI messages so the Replace button appears/disappears
    // consistently with the current mode.
    const conv = document.getElementById('conversation');
    [...conv.children].forEach(el => { if (!el.classList.contains('wi-welcome')) el.remove(); });
    _conversation.forEach(msg => {
      if (msg.role === 'user') renderUserMsg(msg);
      else renderAiMsg(msg);
    });
  }

  function toggleOfflineMode() { setOfflineMode(!_settings.offlineMode); persistSettings(); }

  async function saveSettings() {
    _settings.depth             = document.getElementById('setDepth').value;
    _settings.frictionIntensity = document.getElementById('setFriction').value;
    _settings.autoAdvance       = document.getElementById('setAutoAdvance').value === '1';
    _settings.overwriteDone     = document.getElementById('setOverwriteDone').value === '1';
    _settings.sessionTopic      = document.getElementById('setSessionTopic').value.trim();
    _settings.contextExtra      = document.getElementById('setContextExtra').value.trim();
    await persistSettings();
    toast('Settings saved', 'success');
    
    // Reload silently to capture cleanup of dangling continuation steps
    try {
      const data = await api({ action: 'load_chat', session_id: _chatSessionId }, 'GET');
      _plan = data.plan || null;
      if (data.active_run) _activeRunId = data.active_run.id;
      renderPlan();
      if (data.active_run) renderRunStatus(data.active_run);
      await refreshNextTurnState();
    } catch (e) {
      renderPlan();
    }
  }

  // ── Conversation management ─────────────────────────────────────────
  function clearConversation(force) {
    if (!force && _conversation.length > 0 &&
        !confirm('Start a new blank session? Your current chat will be saved in the "Chats" tab.')) return;

    _conversation = [];
    _plan = null;
    _activeRunId = null;
    _chatSessionId = generateUUID();
    _chatSessionTitle = 'New Session';

    const conv = document.getElementById('conversation');
    [...conv.children].forEach(el => { if (!el.classList.contains('wi-welcome')) el.remove(); });

    document.getElementById('welcomeState').style.display = 'flex';
    renderPlan();
    setLeftTab('chats');
    renderChatSessionsList();
    refreshNextTurnState();
    toast('New blank session started', 'info');
  }

  // ── Flyouts ──────────────────────────────────────────────────────────
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

    if ((openingLeft && leftOpen) || (openingRight && rightOpen)) { closeFlyouts(); return; }

    leftEl.classList.remove('open'); rightEl.classList.remove('open');
    hLeft.classList.remove('open'); hRight.classList.remove('open');

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
    el.innerHTML = `<span>${icons[type]||'✦'}</span> ${escHtml(msg)}`;
    el.onclick = () => { el.classList.add('out'); setTimeout(()=>el.remove(), 250); };
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => { el.classList.add('out'); setTimeout(()=>el.remove(), 250); }, duration);
  }

  function formatTime(ts) { return new Date(ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
  function escHtml(s) { if (s === null || s === undefined) return ''; const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }
  function escAttr(s) { return escHtml(s).replace(/"/g, '&quot;'); }
  function autoResizeTextarea(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 200) + 'px'; }

  // ── Settings persistence ─────────────────────────────────────────────
  async function persistSettings() {
    try {
      await api({ action: 'save_settings', chat_session_id: _chatSessionId, settings: _settings });
    } catch (e) {
      console.error('Wrish Settings Persist Error:', e);
    }
  }

  async function loadPersisted() {
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
          setOfflineMode(!!_settings.offlineMode);
          document.getElementById('setDepth').value = _settings.depth;
          document.getElementById('setFriction').value = _settings.frictionIntensity;
          document.getElementById('setAutoAdvance').value = _settings.autoAdvance ? '1' : '0';
          document.getElementById('setSessionTopic').value = _settings.sessionTopic || '';
          document.getElementById('setContextExtra').value = _settings.contextExtra || '';
          document.getElementById('setOverwriteDone').value = _settings.overwriteDone ? '1' : '0';
        }

        if (data.state.plan) _plan = data.state.plan;
        if (data.state.active_run) _activeRunId = data.state.active_run.id;

        if (data.state.conversation && data.state.conversation.length > 0) {
          _conversation = data.state.conversation;
          const conv = document.getElementById('conversation');
          [...conv.children].forEach(el => { if (!el.classList.contains('wi-welcome')) el.remove(); });
          document.getElementById('welcomeState').style.display = 'none';
          _conversation.forEach(msg => {
            if (msg.role === 'user') renderUserMsg(msg);
            else renderAiMsg(msg);
          });
          conv.scrollTop = conv.scrollHeight;
        }

        renderChatSessionsList();
        renderPlan();
        if (data.state.active_run) renderRunStatus(data.state.active_run);
      }
      await refreshNextTurnState();
      // Refresh the chats list from the paginated endpoint so pager state
      // (page/limit/total_pages) is correctly established on first load,
      // rather than relying solely on load_state's unpaginated snapshot.
      await loadChatSessions(1);
    } catch(e) { console.error('Wrish Load Persisted Error:', e); }
  }

  // ── Init ─────────────────────────────────────────────────────────────
  function init() {
    loadPersisted();
    bindPager('chatSessionsList', p => loadChatSessions(p));

    document.querySelectorAll('.left-tab').forEach(btn => {
      btn.addEventListener('click', () => setLeftTab(btn.dataset.ltab));
    });

    const msgInput = document.getElementById('msgInput');
    msgInput.addEventListener('input', () => autoResizeTextarea(msgInput));
    msgInput.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); nextStep(); }
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
    init, nextStep, toggleFlyout, closeFlyouts,
    saveSettings, clearConversation,
    loadChat, deleteChat, renderChatSessionsList,
    copyMsg, toggleTheme, closeModal, toast,
    toggleOfflineMode, injectAiResponse,
    openReplaceLastModal, confirmReplaceLast,
    openRenameChatModal, saveRenameChat, downloadResult,
    scrollToStep
  };
})();

document.addEventListener('DOMContentLoaded', function() {
  if (typeof window.WI !== 'undefined' && window.WI.init) {
    window.WI.init();
  } else {
    console.error('WI engine failed to initialize.');
  }
});
</script>
<?php if (file_exists(__DIR__ . '/js/sage-home-button.js')): ?>
<!--
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
-->
<?php endif; ?>

</body>
</html>