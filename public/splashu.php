<?php
// public/splashu.php
// ─────────────────────────────────────────────────────────────────────────────
// SPLASHU — Interactive Writers' Room (Cloned from Wrish UI layout & behavior)
// Sits alongside splash.php (pipeline-oriented UI). Splashu provides a
// step-by-step chat/export-mode conversational flow mapped directly to a
// Splash template's configured role chain, exactly like Wrish's battle plan
// workflow.
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
<title>Splashu — Writers' Room Interactive</title>
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
   SPLASHU — Design System (Cloned from Wrish UI, Pink/Fuchsia Accent)
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
  --green-dim:    rgba(34,211,160,0.1);
  --red:          #f05060;
  --red-dim:      rgba(240,80,96,0.1);
  --blue:         #4da6ff;
  --blue-dim:     rgba(77,166,255,0.1);
  --pink:         #d946ef;
  --pink-dim:     rgba(217,70,239,0.1);
  --pink-mid:     rgba(217,70,239,0.18);
  --pink-glow:    rgba(217,70,239,0.45);
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
  --green:        #059669;
  --green-dim:    rgba(5,150,105,0.1);
  --red:          #dc2626;
  --red-dim:      rgba(220,38,38,0.1);
  --blue:         #2563eb;
  --blue-dim:     rgba(37,99,235,0.1);
  --pink:         #c026d3;
  --pink-dim:     rgba(192,38,211,0.1);
  --pink-mid:     rgba(192,38,211,0.18);
  --pink-glow:    rgba(192,38,211,0.4);
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

.spu-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }

/* ── HEADER ── */
.spu-header {
  height: 52px; flex-shrink: 0; display: flex; align-items: center; justify-content: space-between;
  padding: 0 16px; background: var(--surface); border-bottom: 1px solid var(--border); position: relative; z-index: 100;
}
.spu-logo {
  display: flex; align-items: center; gap: 10px; font-family: var(--mono); font-size: 0.82rem; font-weight: 700;
  color: var(--pink); letter-spacing: 2px; text-transform: uppercase;
}
.spu-logo-icon {
  width: 28px; height: 28px; background: var(--pink-mid); border: 1px solid var(--pink-glow);
  border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.phase-indicator {
  font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim); padding: 4px 10px; background: var(--card);
  border: 1px solid var(--border); border-radius: var(--radius); display: flex; align-items: center; gap: 6px;
}
.phase-indicator .phase-dot {
  width: 6px; height: 6px; border-radius: 50%; background: var(--pink); animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }

.btn-icon-sm {
  width: 32px; height: 32px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card);
  color: var(--text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.btn-icon-sm:hover { border-color: var(--pink); color: var(--pink); background: var(--pink-dim); }

/* ── FLYOUTS ── */
.spu-flyout {
  position: fixed; top: 52px; bottom: 0; width: min(300px, 88vw); background: var(--surface); border-color: var(--border); z-index: 500;
  display: flex; flex-direction: column; overflow: hidden; transition: transform 0.28s cubic-bezier(0.25, 1, 0.5, 1); box-shadow: 0 0 40px rgba(0,0,0,0.6);
}
.spu-flyout-left  { left: 0; border-right: 1px solid var(--border); transform: translateX(-100%); }
.spu-flyout-right { right: 0; border-left: 1px solid var(--border); transform: translateX(100%); }
.spu-flyout.open  { transform: translateX(0); }

.spu-flyout-backdrop {
  position: fixed; inset: 52px 0 0 0; background: rgba(0,0,0,0.45); z-index: 499; display: none; backdrop-filter: blur(2px);
}
.spu-flyout-backdrop.visible { display: block; }

.spu-hamburger {
  display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: var(--radius);
  border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; transition: all 0.15s;
  flex-shrink: 0; font-size: 16px; position: relative;
}
.spu-hamburger:hover, .spu-hamburger.open { border-color: var(--pink); color: var(--pink); background: var(--pink-dim); }

.flyout-head { padding: 12px 14px 10px; border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; }
.flyout-head-title { font-family: var(--mono); font-size: 0.65rem; color: var(--pink); text-transform: uppercase; letter-spacing: 2px; }
.flyout-close {
  width: 24px; height: 24px; border-radius: 4px; border: 1px solid var(--border); background: transparent; color: var(--text-dim);
  cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; transition: all 0.15s;
}
.flyout-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }

.left-tabs { display: flex; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.left-tab {
  flex: 1; padding: 8px 4px; font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); text-align: center;
  cursor: pointer; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid transparent; transition: all 0.15s;
  background: none; border-top: none; border-left: none; border-right: none;
}
.left-tab.active { color: var(--pink); border-bottom-color: var(--pink); }
.left-tab:hover:not(.active) { color: var(--text); }

.left-tab-body { flex: 1; overflow: hidden; position: relative; }
.left-tab-content { position: absolute; inset: 0; overflow-y: auto; display: none; padding: 12px; }
.left-tab-content.active { display: block; }

.ctx-group { margin-bottom: 12px; }
.ctx-label { font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 5px; display: block; }
.ctx-textarea, .ctx-input, .ctx-select {
  width: 100%; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius);
  color: var(--text); font-family: var(--mono); font-size: 0.73rem; padding: 7px 10px; transition: border-color 0.15s;
}
.ctx-textarea { line-height: 1.5; resize: vertical; min-height: 60px; }
.ctx-textarea.tall { min-height: 100px; }
.ctx-textarea:focus, .ctx-input:focus, .ctx-select:focus { outline: none; border-color: var(--pink); }
.ctx-select {
  appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 10px center; padding-right: 28px; cursor: pointer;
}

.item-delete-btn {
  position: absolute; right: 6px; top: 6px; width: 20px; height: 20px; border-radius: 4px; border: 1px solid transparent;
  background: transparent; color: var(--text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 10px; transition: all 0.15s;
}
.item-delete-btn:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }

.delta-list { display: flex; flex-direction: column; gap: 4px; }
.delta-item {
  padding: 6px 10px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius);
  cursor: pointer; transition: all 0.15s; display: flex; flex-direction: column; position: relative;
}
.delta-item:hover, .delta-item.active-chat { border-color: var(--pink); background: var(--pink-dim); }
.delta-date { font-family: var(--mono); font-size: 0.6rem; color: var(--pink); }
.delta-topic { font-family: var(--mono); font-size: 0.72rem; color: var(--text); margin-top: 2px; padding-right: 48px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.empty-state { text-align: center; padding: 24px 12px; color: var(--text-dim); font-family: var(--mono); font-size: 0.75rem; }
.empty-icon { font-size: 28px; opacity: 0.2; margin-bottom: 6px; }

/* ── MAIN AREA ── */
.spu-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: var(--bg); min-height: 0; }
.spu-conversation { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 16px; }

.spu-welcome { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center; gap: 20px; padding: 40px; }
.spu-welcome-sigil { font-size: 52px; opacity: 0.15; line-height: 1; font-family: var(--mono); }
.spu-welcome-title { font-family: var(--mono); font-size: 1rem; color: var(--pink); text-transform: uppercase; letter-spacing: 3px; }
.spu-welcome-body { font-size: 0.85rem; color: var(--text-dim); line-height: 1.7; max-width: 480px; }
.spu-welcome-rooms { display: flex; flex-direction: column; gap: 8px; width: 100%; max-width: 360px; }

.room-select-card {
  padding: 12px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius);
  cursor: pointer; text-align: left; transition: all 0.15s; font-family: var(--mono);
}
.room-select-card:hover { border-color: var(--pink); background: var(--pink-dim); }
.room-select-title { font-size: 0.8rem; font-weight: bold; color: var(--text-bright); }
.room-select-meta { font-size: 0.65rem; color: var(--text-dim); margin-top: 4px; }

.msg { display: flex; gap: 12px; animation: msgIn 0.25s ease; }
@keyframes msgIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
.msg-user { flex-direction: row-reverse; }

.msg-avatar {
  width: 32px; height: 32px; border-radius: var(--radius); display: flex; align-items: center; justify-content: center;
  font-size: 14px; flex-shrink: 0; font-family: var(--mono);
}
.msg-avatar.user-av { background: var(--pink-dim); border: 1px solid var(--pink-glow); color: var(--pink); }
.msg-avatar.ai-av    { background: var(--blue-dim); border: 1px solid var(--blue); color: var(--blue); }

.msg-bubble { max-width: calc(100% - 56px); padding: 12px 16px; border-radius: var(--radius-lg); font-size: 0.85rem; line-height: 1.7; }
.msg-user .msg-bubble { background: var(--pink-dim); border: 1px solid var(--pink-glow); color: var(--text-bright); border-top-right-radius: 3px; }
.msg-ai .msg-bubble   { background: var(--card); border: 1px solid var(--border); color: var(--text); border-top-left-radius: 3px; }
.msg-bubble-meta { display: flex; gap: 8px; align-items: center; margin-bottom: 6px; flex-wrap: wrap; }
.msg-protocol-badge {
  font-family: var(--mono); font-size: 0.6rem; padding: 1px 6px; border-radius: 3px;
  background: var(--pink-dim); border: 1px solid var(--pink); color: var(--pink); text-transform: uppercase; letter-spacing: 1px;
}
.msg-timestamp { font-family: var(--mono); font-size: 0.6rem; color: var(--text-dim); }

.ai-section-body { font-size: 0.84rem; color: var(--text); line-height: 1.75; }

.msg-loading .msg-bubble { display: flex; align-items: center; gap: 10px; padding: 14px 16px; }
.typing-dots span {
  display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--blue); margin: 0 2px; animation: typing 1.2s ease-in-out infinite;
}
.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing { 0%,80%,100% { transform: translateY(0); opacity: 0.4; } 40% { transform: translateY(-6px); opacity: 1; } }

/* ── INPUT BAR ── */
.spu-input-bar { padding: 12px 16px; border-top: 1px solid var(--border); background: var(--surface); flex-shrink: 0; }
.input-bar-top { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; flex-wrap: wrap; }
.protocol-selector { display: flex; gap: 4px; flex-wrap: wrap; width: 100%; justify-content: flex-end; }
.proto-btn {
  padding: 3px 8px; border-radius: 20px; border: 1px solid var(--border); font-family: var(--mono); font-size: 0.62rem;
  color: var(--text-dim); cursor: pointer; background: transparent; transition: all 0.12s; letter-spacing: 0.5px;
}
.proto-btn:hover, .proto-btn.active { border-color: var(--pink); color: var(--pink); background: var(--pink-dim); }

.input-bar-bottom { display: flex; gap: 8px; align-items: flex-end; }
.spu-textarea {
  flex: 1; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text-bright);
  font-family: var(--sans); font-size: 0.88rem; padding: 10px 14px; resize: none; min-height: 44px; max-height: 200px;
  transition: border-color 0.15s; line-height: 1.5; overflow-y: auto;
}
.spu-textarea:focus { outline: none; border-color: var(--pink); }
.spu-textarea::placeholder { color: var(--text-dim); }

.btn-send {
  width: 44px; height: 44px; border-radius: var(--radius); background: var(--pink); color: #fff; border: none;
  cursor: pointer; transition: all 0.15s; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 16px;
}
.btn-send:hover:not(:disabled) { filter: brightness(1.15); transform: translateY(-1px); }
.btn-send:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
.btn-send .spin-sm {
  width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%;
  animation: spin 0.7s linear infinite; display: none;
}
.btn-send.loading i { display: none; }
.btn-send.loading .spin-sm { display: block; }

/* ── RIGHT PANEL — Step Stepper ── */
.plan-header { padding: 12px 14px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.plan-title { font-family: var(--mono); font-size: 0.78rem; color: var(--pink); font-weight: 700; }
.plan-premise { font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim); margin-top: 4px; line-height: 1.5; }
.plan-empty { padding: 24px 16px; text-align: center; }

.step-list { flex: 1; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 6px; }
.step-item { padding: 9px 11px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); position: relative; }
.step-item.status-pending { opacity: 0.55; }
.step-item.status-active   { border-color: var(--pink); background: var(--pink-dim); }
.step-item.status-done     { border-left: 3px solid var(--green); cursor: pointer; transition: all 0.15s; }
.step-item.status-done:hover { border-color: var(--pink); }
.step-num { font-family: var(--mono); font-size: 0.6rem; color: var(--text-dim); display: inline-block; width: 18px; }
.step-title { font-family: var(--sans); font-size: 0.78rem; font-weight: 700; color: var(--text); display: inline; }
.step-purpose { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); margin-top: 3px; line-height: 1.4; }
.step-result { font-family: var(--mono); font-size: 0.65rem; color: var(--text); margin-top: 5px; padding-top: 5px; border-top: 1px dashed var(--border); line-height: 1.5; }
.step-status-icon { font-size: 0.7rem; margin-right: 4px; }
.step-status-icon.done { color: var(--green); }
.step-status-icon.active { color: var(--pink); }
.step-status-icon.pending { color: var(--text-dim); }
.plan-actions { padding: 10px 14px; border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; }

/* ── MODALS & TOAST ── */
.spu-modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(3px); z-index: 9999;
  display: none; align-items: center; justify-content: center; padding: 16px;
}
.spu-modal-overlay.open { display: flex; }
.spu-modal {
  background: var(--surface); border: 1px solid var(--border-glow); border-radius: var(--radius-lg);
  width: 100%; max-width: 640px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.7); animation: modalIn 0.2s ease;
}
.spu-modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.spu-modal-title { font-family: var(--mono); font-size: 0.78rem; color: var(--pink); text-transform: uppercase; letter-spacing: 1.5px; }
.spu-modal-close {
  width: 26px; height: 26px; border-radius: 4px; border: 1px solid var(--border); background: transparent;
  color: var(--text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: all 0.15s;
}
.spu-modal-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }
.spu-modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.spu-modal-footer { padding: 12px 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; flex-shrink: 0; background: var(--bg); }

.btn-primary {
  padding: 8px 18px; background: var(--pink); color: #fff; border: none; border-radius: var(--radius);
  cursor: pointer; font-family: var(--mono); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; transition: all 0.15s;
}
.btn-primary:hover { filter: brightness(1.1); }
.btn-secondary {
  padding: 8px 18px; background: transparent; color: var(--text-dim); border: 1px solid var(--border);
  border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.76rem; transition: all 0.15s;
}
.btn-secondary:hover { border-color: var(--border-glow); color: var(--text); }

.toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 6px; pointer-events: none; }
.toast {
  padding: 8px 14px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--border);
  font-family: var(--mono); font-size: 0.76rem; color: var(--text); box-shadow: 0 4px 20px rgba(0,0,0,0.5);
  animation: toastIn 0.2s ease; pointer-events: all; cursor: pointer; display: flex; gap: 6px; align-items: center; max-width: 300px;
}
.toast.success { border-color: var(--green); }
.toast.error   { border-color: var(--red); color: var(--red); }
.toast.info    { border-color: var(--pink); }
.toast.out     { animation: toastOut 0.2s forwards; }

.sep { height: 1px; background: var(--border); margin: 10px 0; }
.hint { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); margin-top: 3px; }

@keyframes spin { to { transform: rotate(360deg); } }
@keyframes toastIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
@keyframes toastOut { to { opacity: 0; transform: translateY(8px); } }
@keyframes modalIn { from { opacity: 0; transform: scale(0.96) translateY(-8px); } to { opacity: 1; transform: none; } }

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

<div class="spu-layout">
<div class="spu-flyout-backdrop" id="flyoutBackdrop" onclick="SPU.closeFlyouts()"></div>

<header class="spu-header">
  <button class="spu-hamburger" id="hamburgerLeft" onclick="SPU.toggleFlyout('left')" title="Chats &amp; Settings">
    <i class="bi bi-layout-text-sidebar"></i>
  </button>
  <div class="spu-logo" style="flex:1; justify-content:center;">
    <div class="spu-logo-icon">🪶</div>
    <span>Splashu</span>
  </div>
  <div style="display:flex;align-items:center;gap:6px;">
    <div class="phase-indicator" id="headerPhaseWrap" style="display:none;">
      <div class="phase-dot"></div>
      <span id="headerPhase">—</span>
    </div>
    <button class="btn-icon-sm" onclick="SPU.clearConversation()" title="New Session" style="width:32px;height:32px;">
      <i class="bi bi-plus-circle"></i>
    </button>
    <button class="btn-icon-sm" onclick="SPU.toggleTheme()" id="themeBtn" title="Toggle Theme" style="display:none;width:32px;height:32px;">
      <i class="bi bi-moon-stars"></i>
    </button>
    <button class="spu-hamburger" id="hamburgerRight" onclick="SPU.toggleFlyout('right')" title="Writing Steps">
      <i class="bi bi-list-check"></i>
    </button>
  </div>
</header>

<!-- LEFT FLYOUT — Chats / Settings -->
<div class="spu-flyout spu-flyout-left" id="flyoutLeft">
  <div class="flyout-head">
    <span class="flyout-head-title">Sessions</span>
    <button class="flyout-close" onclick="SPU.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
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
          <div class="empty-state"><div class="empty-icon">🪶</div>No past sessions</div>
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
          <label class="ctx-label">Auto-Advance Steps</label>
          <select class="ctx-select" id="setAutoAdvance">
            <option value="0" selected>No — wait for me between steps</option>
            <option value="1">Yes — AI may continue on its own</option>
          </select>
        </div>
        <div class="ctx-group">
          <label class="ctx-label">Overwrite Done State</label>
          <select class="ctx-select" id="setOverwriteDone">
            <option value="0" selected>No — Stop when room chain is complete</option>
            <option value="1">Yes — Allow manual continuation past chain end</option>
          </select>
        </div>
        <div class="ctx-group">
          <label class="ctx-label">Source Lore / Plot</label>
          <textarea class="ctx-textarea tall" id="setSourceText" placeholder="The original lore or plot fed into the writing room…"></textarea>
        </div>
        <div class="sep"></div>
        <button class="btn-primary" style="width:100%;font-size:0.72rem;" onclick="SPU.saveSettings()">
          <i class="bi bi-floppy"></i> Save Settings
        </button>
      </div>
    </div>
  </div>
</div>

<!-- RIGHT FLYOUT — Role Stepper -->
<div class="spu-flyout spu-flyout-right" id="flyoutRight">
  <div class="flyout-head">
    <span class="flyout-head-title">Writers' Room Chain</span>
    <button class="flyout-close" onclick="SPU.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
  </div>
  <div style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
    <div id="planHeaderWrap"></div>
    <div class="step-list" id="stepList">
      <div class="plan-empty empty-state">
        <div class="empty-icon">🪶</div>
        No room selected.<br>Select a room template to begin.
      </div>
    </div>
    <div class="plan-actions" id="planActions" style="display:none;">
      <button class="btn-primary" id="btnNextStepFlyout" style="width:100%;font-size:0.72rem;" onclick="SPU.nextStep()">
        <i class="bi bi-play-fill"></i> <span id="nextStepFlyoutLabel">Next Role</span>
      </button>
    </div>
  </div>
</div>

<!-- MAIN -->
<main class="spu-main">
  <div class="spu-conversation" id="conversation">
    <div class="spu-welcome" id="welcomeState">
      <div class="spu-welcome-sigil">🪶</div>
      <div class="spu-welcome-title">Splashu — Writers' Room</div>
      <div class="spu-welcome-body">
        Select a preconfigured Splash writing room template to orchestrate your chain of AI roles interactively, step by step.
      </div>
      <div class="spu-welcome-rooms" id="welcomeRoomsList">
        <div class="empty-state">Loading room templates...</div>
      </div>
    </div>
  </div>

  <div class="spu-input-bar">
    <div class="input-bar-top">
      <div class="protocol-selector">
        <button class="proto-btn" id="btnDownloadResult" onclick="SPU.downloadResult()" style="display:none; border-color:var(--green); color:var(--green); margin-right:8px;">
          <i class="bi bi-download"></i> Download Script
        </button>
        <button class="proto-btn" id="btnModeToggle" onclick="SPU.toggleOfflineMode()" style="border-color:var(--pink); color:var(--pink);">
          <i class="bi bi-cloud-check"></i> API Mode
        </button>
      </div>
    </div>
    <div class="input-bar-bottom">
      <textarea class="spu-textarea" id="msgInput" placeholder="Optional — add instructions for the NEXT role before running" rows="1"></textarea>
      <button class="btn-send" id="btnInject" onclick="SPU.injectAiResponse()" style="display:none; background:var(--blue); color:#fff; font-size:18px;" title="Inject pasted AI response for current step">
        <i class="bi bi-robot"></i>
      </button>
      <button class="btn-send" id="btnNextStepMain" onclick="SPU.nextStep()" title="Run next role">
        <i class="bi bi-play-fill" id="nextStepIcon"></i>
        <div class="spin-sm"></div>
      </button>
    </div>
  </div>
</main>

</div><!-- /spu-layout -->

<!-- MODAL: REPLACE LAST -->
<div class="spu-modal-overlay" id="replaceLastModal">
  <div class="spu-modal">
    <div class="spu-modal-header">
      <div class="spu-modal-title">Replace Last Response</div>
      <button class="spu-modal-close" onclick="SPU.closeModal('replaceLastModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="spu-modal-body">
      <div class="ctx-group">
        <label class="ctx-label">New content for the most recent role</label>
        <textarea class="ctx-textarea tall" id="replaceLastInput" style="min-height:220px;"></textarea>
      </div>
    </div>
    <div class="spu-modal-footer">
      <button class="btn-secondary" onclick="SPU.closeModal('replaceLastModal')">Cancel</button>
      <button class="btn-primary" onclick="SPU.confirmReplaceLast()">Replace</button>
    </div>
  </div>
</div>

<!-- MODAL: RENAME CHAT -->
<div class="spu-modal-overlay" id="renameChatModal">
  <div class="spu-modal" style="max-width:400px;">
    <div class="spu-modal-header">
      <div class="spu-modal-title">Rename Session</div>
      <button class="spu-modal-close" onclick="SPU.closeModal('renameChatModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="spu-modal-body">
      <input type="hidden" id="renameChatId" value="">
      <div class="ctx-group">
        <label class="ctx-label">Session Name</label>
        <input class="ctx-input" type="text" id="renameChatTitle" placeholder="e.g. Episode 1 Run">
      </div>
    </div>
    <div class="spu-modal-footer">
      <button class="btn-secondary" onclick="SPU.closeModal('renameChatModal')">Cancel</button>
      <button class="btn-primary" onclick="SPU.saveRenameChat()">Save</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
window.SPU = (function() {
  'use strict';

  function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  let _templates         = [];
  let _plan              = null;
  let _conversation      = [];
  let _chatSessions      = [];
  let _chatSessionId     = generateUUID();
  let _chatSessionTitle  = 'New Session';
  let _activeRunId       = null;
  let _settings = {
    autoAdvance: false,
    sourceText: '',
    offlineMode: false,
    overwriteDone: false
  };
  let _isLoading         = false;
  let _nextTurnKind      = 'select_room';
  let _chatSessionsPg    = { page: 1, limit: 12, total_pages: 1 };

  async function api(payload, method) {
    method = method || 'POST';
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    let url = '/splashu_api.php';
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

    prevBtn.addEventListener('click', () => { if (_chatSessionsPg.page > 1) loader(_chatSessionsPg.page - 1); });
    nextBtn.addEventListener('click', () => { if (_chatSessionsPg.page < _chatSessionsPg.total_pages) loader(_chatSessionsPg.page + 1); });
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

  async function refreshNextTurnState() {
    try {
      const data = await api({ action: 'next_turn_info', chat_session_id: _chatSessionId }, 'GET');
      _nextTurnKind = data.turn.kind;
    } catch (e) {
      _nextTurnKind = 'select_room';
    }
    updateNextStepButtons();
  }

  function updateNextStepButtons() {
    const labels = { select_room: 'Select Room', step: 'Next Role', done: 'Room Chain Complete' };
    const label = labels[_nextTurnKind] || 'Next Role';

    const flyoutLabel = document.getElementById('nextStepFlyoutLabel');
    if (flyoutLabel) flyoutLabel.textContent = label;
    const flyoutBtn = document.getElementById('btnNextStepFlyout');
    if (flyoutBtn) flyoutBtn.disabled = (_nextTurnKind === 'done' || _nextTurnKind === 'select_room');

    const mainBtn = document.getElementById('btnNextStepMain');
    if (mainBtn) {
      mainBtn.title = label;
      mainBtn.disabled = (_nextTurnKind === 'done' || _nextTurnKind === 'select_room');
    }
    const dlBtn = document.getElementById('btnDownloadResult');
    if (dlBtn) dlBtn.style.display = (_nextTurnKind === 'done') ? 'inline-flex' : 'none';
  }

  async function selectRoomAndStart(templateId) {
    _settings.sourceText = document.getElementById('setSourceText').value.trim();
    if (_isLoading) return;
    document.getElementById('welcomeState').style.display = 'none';

    _isLoading = true;
    setMainButtonLoading(true);
    const loadingEl = addLoadingBubble();

    try {
      const data = await api({
        action: 'select_room',
        chat_session_id: _chatSessionId,
        template_id: parseInt(templateId),
        settings: _settings
      });
      loadingEl.remove();
      applyTurnResult(data, 'Select Room: Template #' + templateId);
    } catch (e) {
      loadingEl.remove();
      toast('Error: ' + e.message, 'error');
    } finally {
      _isLoading = false;
      setMainButtonLoading(false);
      await refreshNextTurnState();
    }
  }

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
    if (data.turn_type === 'room_selected') {
      toast('Writers\' room loaded into stepper', 'success');
    } else if (data.turn_type === 'done') {
      toast('Room chain complete.', 'success');
    }
    if (data.run) {
      _activeRunId = data.run.id;
    }
    renderChatSessionsList();

    if (_conversation.length === 1 || (_conversation.length === 2 && userDisplayText)) {
      _chatSessionTitle = (userDisplayText || 'Splashu Session').substring(0, 40);
      if (!_chatSessions.find(c => c.id === _chatSessionId)) {
        _chatSessions.unshift({ id: _chatSessionId, title: _chatSessionTitle, updated_at: new Date().toISOString() });
      }
    }
  }

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
      a.download = `splashu_prompt_${new Date().toISOString().replace(/[:.]/g,'-')}.txt`;
      a.click();
      URL.revokeObjectURL(url);

      toast('Full package downloaded. Paste response back with the robot icon when ready.', 'info', 5000);
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
      a.download = `splashu_script_${_chatSessionId}.txt`;
      a.click();
      URL.revokeObjectURL(url);
      toast('Script downloaded', 'success');
    } catch (e) {
      toast('Error: ' + e.message, 'error');
    }
  }

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
        toast('Response recorded for this role. Click Next Role when ready.', 'success', 4500);
      } catch (e) {
        toast('Error: ' + e.message, 'error');
      }
    })();
  }

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

  function renderUserMsg(msg) {
    const conv = document.getElementById('conversation');
    const displayText = msg.display_content || msg.content || '';
    if (!displayText) return;

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
    const rendered = renderAiContent(msg.content);

    const replaceBtn = _settings.offlineMode
      ? `<button onclick="SPU.openReplaceLastModal()" style="background:none; border:none; color:var(--amber); cursor:pointer; font-size:11px; padding:0 4px;" title="Replace this response"><i class="bi bi-pencil-square"></i></button>`
      : '';

    msgNode.innerHTML = `
      <div class="msg-avatar ai-av">🪶</div>
      <div class="msg-bubble" style="max-width:calc(100% - 56px);">
        <div class="msg-bubble-meta">
          <span class="msg-protocol-badge">Splashu</span>
          <span class="msg-timestamp">${formatTime(msg.ts)}</span>
          <span style="margin-left:auto; display:flex; align-items:center;">
            ${replaceBtn}
            <button onclick="SPU.copyMsg(this)" data-content="${escAttr(msg.content)}"
              style="background:none; border:none; color:var(--text-dim); cursor:pointer; font-size:11px; padding:0 4px;" title="Copy">
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
      return `</div><div class="ai-section"><div style="font-family:var(--mono);font-size:0.6rem;color:var(--pink);text-transform:uppercase;letter-spacing:1.5px;margin:10px 0 6px;padding-bottom:4px;border-bottom:1px solid var(--border);">${title}</div><div class="ai-section-body">`;
    });
    const withBold = withHeaders.replace(/\*\*(.+?)\*\*/g, '<strong style="color:var(--text-bright)">$1</strong>');
    const final = withBold.replace(/\n/g, '<br>');
    return `<div class="ai-section-body">${final}</div>`;
  }

  function addLoadingBubble() {
    const conv = document.getElementById('conversation');
    const msgNode = document.createElement('div');
    msgNode.className = 'msg msg-ai msg-loading';
    msgNode.innerHTML = `
      <div class="msg-avatar ai-av">🪶</div>
      <div class="msg-bubble">
        <div class="typing-dots"><span></span><span></span><span></span></div>
        <span style="font-family:var(--mono); font-size:0.68rem; color:var(--text-dim);">Writing…</span>
      </div>`;
    conv.appendChild(msgNode);
    conv.scrollTop = conv.scrollHeight;
    return msgNode;
  }

  function renderPlan() {
    const headerWrap = document.getElementById('planHeaderWrap');
    const stepList = document.getElementById('stepList');
    const actions = document.getElementById('planActions');

    document.getElementById('headerPhaseWrap').style.display = 'flex';

    if (!_plan) {
      headerWrap.innerHTML = '';
      stepList.innerHTML = `<div class="plan-empty empty-state"><div class="empty-icon">🪶</div>No room selected.<br>Select a room template to begin.</div>`;
      actions.style.display = 'none';
      document.getElementById('headerPhase').textContent = 'No active room';
      return;
    }

    headerWrap.innerHTML = `
      <div class="plan-header">
        <div class="plan-title">${escHtml(_plan.title || 'Writers Room')}</div>
        ${_plan.premise ? `<div class="plan-premise">${escHtml(_plan.premise)}</div>` : ''}
      </div>`;

    const icons = { pending: 'bi-circle', active: 'bi-play-circle-fill', done: 'bi-check-circle-fill' };

    stepList.innerHTML = _plan.steps.map(s => `
      <div class="step-item status-${s.status}" ${s.status === 'done' ? `onclick="SPU.scrollToStep(${s.step_order})"` : ''}>
        <span class="step-num">${s.step_order}.</span>
        <i class="bi ${icons[s.status] || 'bi-circle'} step-status-icon ${s.status}"></i>
        <span class="step-title">${escHtml(s.title)}</span>
        ${s.result_summary ? `<div class="step-result">${escHtml(s.result_summary.substring(0, 240))}${s.result_summary.length > 240 ? '…' : ''}</div>` : ''}
      </div>`).join('');

    actions.style.display = 'flex';
    const activeStep = _plan.steps.find(s => s.status === 'active');
    document.getElementById('headerPhase').textContent =
      activeStep ? `Role ${activeStep.step_order}: ${activeStep.title}` : (_plan.title);
  }

  function scrollToStep(stepOrder) {
    if (!_plan) return;
    const step = _plan.steps.find(s => s.step_order === stepOrder);
    if (!step || step.status !== 'done' || !step.result_summary) return;
    
    const convNode = document.getElementById('conversation');
    const aiNodes = convNode.querySelectorAll('.msg-ai');
    const aiMessages = _conversation.filter(m => m.role === 'assistant');
    
    let msgIndex = aiMessages.findIndex(m => m.content === step.result_summary);
    if (msgIndex === -1 && stepOrder < aiMessages.length) msgIndex = stepOrder;
    if (msgIndex === -1 && stepOrder === 1 && aiMessages.length > 0) msgIndex = 0;
    
    if (msgIndex !== -1 && aiNodes[msgIndex]) {
      const targetNode = aiNodes[msgIndex];
      targetNode.scrollIntoView({ behavior: 'smooth', block: 'start' });
      if (window.innerWidth <= 768) closeFlyouts();
    }
  }

  async function loadTemplatesList() {
    try {
      const data = await api({ action: 'list_templates' }, 'GET');
      _templates = data.templates || [];
      const roomsList = document.getElementById('welcomeRoomsList');
      if (_templates.length === 0) {
        roomsList.innerHTML = `<div class="empty-state">No Splash room templates found. Create one in Splash first.</div>`;
        return;
      }
      roomsList.innerHTML = _templates.map(t => `
        <button class="room-select-card" onclick="SPU.selectRoomAndStart(${t.id})">
          <div class="room-select-title">${escHtml(t.name)}</div>
          <div class="room-select-meta">${(t.roles || []).length} role(s) configured</div>
        </button>`).join('');
    } catch (e) {
      toast('Error loading templates: ' + e.message, 'error');
    }
  }

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
      list.innerHTML = `<div class="empty-state"><div class="empty-icon">🪶</div>No past sessions</div>`;
      renderPager('chatSessionsList', _chatSessionsPg);
      return;
    }
    list.innerHTML = _chatSessions.map(c => `
      <div class="delta-item ${c.id === _chatSessionId ? 'active-chat' : ''}" onclick="SPU.loadChat('${c.id}')">
        <div class="delta-date">${new Date(c.updated_at).toLocaleDateString()}</div>
        <div class="delta-topic">${escHtml(c.title || 'Session')}</div>
        <button class="item-delete-btn" style="right: 30px;" onclick="SPU.openRenameChatModal(event, '${c.id}', '${escAttr(c.title || '')}')" title="Rename Session">
          <i class="bi bi-pencil"></i>
        </button>
        <button class="item-delete-btn" onclick="SPU.deleteChat(event, '${c.id}')" title="Delete Session">
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
        document.getElementById('setAutoAdvance').value = _settings.autoAdvance ? '1' : '0';
        document.getElementById('setOverwriteDone').value = _settings.overwriteDone ? '1' : '0';
        document.getElementById('setSourceText').value = _settings.sourceText || '';
      }

      const conv = document.getElementById('conversation');
      [...conv.children].forEach(el => { if (!el.classList.contains('spu-welcome')) el.remove(); });

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
    if (!confirm('Permanently delete this session transcript and room chain?')) return;
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
      msgInput.placeholder = "Optional — extra context for the exported prompt";
    } else {
      btn.innerHTML = `<i class="bi bi-cloud-check"></i> API Mode`;
      btn.style.borderColor = 'var(--pink)'; btn.style.color = 'var(--pink)'; btn.style.background = 'transparent';
      nextIcon.className = 'bi bi-play-fill';
      btnInject.style.display = 'none';
      msgInput.placeholder = "Optional — add instructions for the NEXT role before running";
    }
    const conv = document.getElementById('conversation');
    [...conv.children].forEach(el => { if (!el.classList.contains('spu-welcome')) el.remove(); });
    _conversation.forEach(msg => {
      if (msg.role === 'user') renderUserMsg(msg);
      else renderAiMsg(msg);
    });
  }

  function toggleOfflineMode() { setOfflineMode(!_settings.offlineMode); persistSettings(); }

  async function saveSettings() {
    _settings.autoAdvance   = document.getElementById('setAutoAdvance').value === '1';
    _settings.overwriteDone = document.getElementById('setOverwriteDone').value === '1';
    _settings.sourceText    = document.getElementById('setSourceText').value.trim();
    await persistSettings();
    toast('Settings saved', 'success');

    try {
      const data = await api({ action: 'load_chat', session_id: _chatSessionId }, 'GET');
      _plan = data.plan || null;
      renderPlan();
      await refreshNextTurnState();
    } catch (e) {
      renderPlan();
    }
  }

  function clearConversation(force) {
    if (!force && _conversation.length > 0 && !confirm('Start a new blank session?')) return;
    _conversation = [];
    _plan = null;
    _activeRunId = null;
    _chatSessionId = generateUUID();
    _chatSessionTitle = 'New Session';

    const conv = document.getElementById('conversation');
    [...conv.children].forEach(el => { if (!el.classList.contains('spu-welcome')) el.remove(); });

    document.getElementById('welcomeState').style.display = 'flex';
    renderPlan();
    setLeftTab('chats');
    renderChatSessionsList();
    loadTemplatesList();
    refreshNextTurnState();
    toast('New session started', 'info');
  }

  function toggleFlyout(side) {
    const openingLeft = side === 'left';
    const openingRight = side === 'right';
    const leftEl = document.getElementById('flyoutLeft');
    const rightEl = document.getElementById('flyoutRight');
    const backdrop = document.getElementById('flyoutBackdrop');
    const hLeft = document.getElementById('hamburgerLeft');
    const hRight = document.getElementById('hamburgerRight');

    const leftOpen = leftEl.classList.contains('open');
    const rightOpen = rightEl.classList.contains('open');

    if ((openingLeft && leftOpen) || (openingRight && rightOpen)) { closeFlyouts(); return; }

    leftEl.classList.remove('open'); rightEl.classList.remove('open');
    hLeft.classList.remove('open'); hRight.classList.remove('open');

    if (openingLeft)  { leftEl.classList.add('open'); hLeft.classList.add('open'); }
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
    const icons = { success: '✓', error: '✕', info: '🪶' };
    el.innerHTML = `<span>${icons[type]||'🪶'}</span> ${escHtml(msg)}`;
    el.onclick = () => { el.classList.add('out'); setTimeout(()=>el.remove(), 250); };
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => { el.classList.add('out'); setTimeout(()=>el.remove(), 250); }, duration);
  }

  function formatTime(ts) { return new Date(ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
  function escHtml(s) { if (s === null || s === undefined) return ''; const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }
  function escAttr(s) { return escHtml(s).replace(/"/g, '&quot;'); }
  function autoResizeTextarea(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 200) + 'px'; }

  async function persistSettings() {
    try {
      await api({ action: 'save_settings', chat_session_id: _chatSessionId, settings: _settings });
    } catch (e) {
      console.error('Splashu Settings Persist Error:', e);
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
          document.getElementById('setAutoAdvance').value = _settings.autoAdvance ? '1' : '0';
          document.getElementById('setOverwriteDone').value = _settings.overwriteDone ? '1' : '0';
          document.getElementById('setSourceText').value = _settings.sourceText || '';
        }
        if (data.state.plan) _plan = data.state.plan;
        if (data.state.conversation && data.state.conversation.length > 0) {
          _conversation = data.state.conversation;
          const conv = document.getElementById('conversation');
          [...conv.children].forEach(el => { if (!el.classList.contains('spu-welcome')) el.remove(); });
          document.getElementById('welcomeState').style.display = 'none';
          _conversation.forEach(msg => {
            if (msg.role === 'user') renderUserMsg(msg);
            else renderAiMsg(msg);
          });
          conv.scrollTop = conv.scrollHeight;
        }
        renderChatSessionsList();
        renderPlan();
      }
      await refreshNextTurnState();
      await loadChatSessions(1);
      await loadTemplatesList();
    } catch(e) { console.error('Splashu Load Persisted Error:', e); }
  }

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

    document.querySelectorAll('.spu-modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        closeFlyouts();
        document.querySelectorAll('.spu-modal-overlay.open').forEach(m => m.classList.remove('open'));
      }
    });

    const theme = document.documentElement.getAttribute('data-theme');
    document.getElementById('themeBtn').querySelector('i').className = theme === 'light' ? 'bi bi-sun' : 'bi bi-moon-stars';
  }

  return {
    init, nextStep, toggleFlyout, closeFlyouts, saveSettings, clearConversation,
    loadChat, deleteChat, renderChatSessionsList, copyMsg, toggleTheme, closeModal, toast,
    toggleOfflineMode, injectAiResponse, openReplaceLastModal, confirmReplaceLast,
    openRenameChatModal, saveRenameChat, downloadResult, scrollToStep, selectRoomAndStart
  };
})();

document.addEventListener('DOMContentLoaded', function() {
  if (typeof window.SPU !== 'undefined' && window.SPU.init) {
    window.SPU.init();
  }
});
</script>
</body>
</html>