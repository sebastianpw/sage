<?php
// public/wtf.php
// ─────────────────────────────────────────────────────────────────────────────
// WTF — Work The Flow
// Deterministic workflow orchestration control panel. UI shell cloned from
// wrish.php (same Forge design language) but restructured for orchestration
// rather than conversation: left flyout = Workflows & Runs, main area =
// unified CLI-like status monitor and live activity feed.
//
// This file and wtf_api.php know NOTHING about wrish, image generation, or
// any other SAGE module — see src/Wtf/HandlerRegistry.php for the extension
// point where tool-specific behavior is plugged in later.
//
// ── CHANGES IN THIS VERSION ─────────────────────────────────────────────────
// 1. THE UI NO LONGER TRIGGERS advance_run AUTOMATICALLY, EVER.
//    Previously a 2-second timer polled get_run/get_run_events AND called
//    advance_run in a loop whenever a run's status was 'running' — regardless
//    of whether the run was auto_advance or manual. That made the frontend a
//    second, uncontrolled driver of step execution, racing against the
//    scheduler heartbeat (which now exclusively drives auto_advance runs
//    forward — see App\Wtf\WorkflowExecutor::advanceAllAutoRuns() and
//    App\Core\TaskRunnerScheduler::advanceWtfRuns()).
//
//    Now:
//      - For an AUTO-ADVANCE run that's 'running', the UI polls get_run /
//        get_run_events on a timer PURELY TO REFRESH THE DISPLAY. It never
//        calls advance_run itself. The backend heartbeat is doing the actual
//        advancing; the UI is just watching.
//      - For a MANUAL (non-auto-advance) run, there is no polling loop at
//        all. The only way a step ever advances is the human clicking
//        "Proceed", which calls advance_run exactly once, exactly on click.
//
// 2. The "Auto-Advance" checkbox on Start Run now defaults to CHECKED.
//
// 3. Header gained two buttons cloned from Scheduler Forge: a Logs viewer
//    and a Global Locks manager, wired to the small set of read-only
//    passthrough actions added to wtf_api.php (list_log_files,
//    fetch_log_file, get_global_locks, release_lock, cleanup_locks,
//    scheduler_heartbeat). Same visual behavior as scheduler_forge.php's
//    modals (auto-refresh toggle, file picker, tail-follow), just reachable
//    from WTF directly so you don't need to leave this screen to check on
//    what the scheduler is doing with a task a workflow step triggered.
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
<title>WTF — Work The Flow</title>
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
window.WTF_USER_ID = <?= json_encode($userId ?? 1) ?>;
</script>

<style>
/* ═══════════════════════════════════════════════════════════════════════════
   WTF — Design System
   Same Forge design language as wrish/wroom, but with a teal/cyan accent so
   the orchestration layer reads as visually distinct from wroom's amber
   (authoring) and wrish's violet (invention) at a glance.
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
  --green:        #22d3a0;
  --green-dim:    rgba(34,211,160,0.1);
  --red:          #f05060;
  --red-dim:      rgba(240,80,96,0.1);
  --blue:         #4da6ff;
  --blue-dim:     rgba(77,166,255,0.1);
  --teal:         #2dd4bf;
  --teal-dim:     rgba(45,212,191,0.1);
  --teal-mid:     rgba(45,212,191,0.18);
  --teal-glow:    rgba(45,212,191,0.45);
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
  --green:        #059669;
  --green-dim:    rgba(5,150,105,0.1);
  --red:          #dc2626;
  --red-dim:      rgba(220,38,38,0.1);
  --blue:         #2563eb;
  --blue-dim:     rgba(37,99,235,0.1);
  --teal:         #0d9488;
  --teal-dim:     rgba(13,148,136,0.1);
  --teal-mid:     rgba(13,148,136,0.18);
  --teal-glow:    rgba(13,148,136,0.4);
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
.wf-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }

/* ── HEADER ─────────────────────────────────────────────────────────────── */
.wf-header {
  height: 52px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 16px; background: var(--surface); border-bottom: 1px solid var(--border);
  position: relative; z-index: 100;
}
.wf-logo {
  display: flex; align-items: center; gap: 10px;
  font-family: var(--mono); font-size: 0.82rem; font-weight: 700;
  color: var(--teal); letter-spacing: 2px; text-transform: uppercase;
}
.wf-logo-icon {
  width: 28px; height: 28px; background: var(--teal-mid);
  border: 1px solid var(--teal-glow); border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center; font-size: 13px;
}
.phase-indicator {
  font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim);
  padding: 4px 10px; background: var(--card);
  border: 1px solid var(--border); border-radius: var(--radius);
  display: flex; align-items: center; gap: 6px;
}
.phase-indicator .phase-dot {
  width: 6px; height: 6px; border-radius: 50%; background: var(--teal);
  animation: pulse-dot 2s ease-in-out infinite;
}
.phase-indicator.status-paused .phase-dot { background: var(--amber); animation: none; }
.phase-indicator.status-failed .phase-dot { background: var(--red); animation: none; }
.phase-indicator.status-done .phase-dot { background: var(--green); animation: none; }
@keyframes pulse-dot { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }

.btn-icon-sm {
  width: 32px; height: 32px; border-radius: var(--radius);
  border: 1px solid var(--border); background: var(--card);
  color: var(--text-dim); cursor: pointer; transition: all 0.15s;
  display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.btn-icon-sm:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }

/* ── FLYOUTS ────────────────────────────────────────────────────────────── */
.wf-flyout {
  position: fixed; top: 52px; bottom: 0; width: min(300px, 88vw);
  background: var(--surface); border-color: var(--border); z-index: 500;
  display: flex; flex-direction: column; overflow: hidden;
  transition: transform 0.28s cubic-bezier(0.25, 1, 0.5, 1);
  box-shadow: 0 0 40px rgba(0,0,0,0.6);
}
.wf-flyout-left  { left: 0;  border-right: 1px solid var(--border); transform: translateX(-100%); }
.wf-flyout.open  { transform: translateX(0); }

.wf-flyout-backdrop {
  position: fixed; inset: 52px 0 0 0; background: rgba(0,0,0,0.45);
  z-index: 499; display: none; backdrop-filter: blur(2px);
}
.wf-flyout-backdrop.visible { display: block; }

.wf-hamburger {
  display: flex; align-items: center; justify-content: center;
  width: 36px; height: 36px; border-radius: var(--radius);
  border: 1px solid var(--border); background: var(--card);
  color: var(--text-dim); cursor: pointer; transition: all 0.15s;
  flex-shrink: 0; font-size: 16px; position: relative;
}
.wf-hamburger:hover, .wf-hamburger.open { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }

.flyout-head {
  padding: 12px 14px 10px; border-bottom: 1px solid var(--border); flex-shrink: 0;
  display: flex; align-items: center; justify-content: space-between;
}
.flyout-head-title {
  font-family: var(--mono); font-size: 0.65rem; color: var(--teal);
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
.left-tab.active { color: var(--teal); border-bottom-color: var(--teal); }
.left-tab:hover:not(.active) { color: var(--text); }

.left-tab-body { flex: 1; overflow: hidden; position: relative; }
.left-tab-content { position: absolute; inset: 0; overflow-y: auto; display: none; padding: 12px; }
.left-tab-content.active { display: block; }

/* ── Shared form fields ─────────────────────────────────────────────────── */
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
.ctx-textarea:focus { outline: none; border-color: var(--teal); }
.ctx-textarea.tall { min-height: 100px; }

.ctx-input {
  width: 100%; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text); font-family: var(--mono);
  font-size: 0.73rem; padding: 7px 10px; transition: border-color 0.15s;
}
.ctx-input:focus { outline: none; border-color: var(--teal); }

.ctx-select {
  width: 100%; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text); font-family: var(--mono);
  font-size: 0.73rem; padding: 7px 10px; cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 10px center; padding-right: 28px;
}
.ctx-select:focus { outline: none; border-color: var(--teal); }
.ctx-select:disabled { opacity: 0.5; cursor: not-allowed; }

.empty-state { text-align: center; padding: 24px 12px; color: var(--text-dim); font-family: var(--mono); font-size: 0.75rem; }
.empty-icon { font-size: 28px; opacity: 0.2; margin-bottom: 6px; }

/* Workflow / run list items */
.wf-item {
  display: flex; align-items: flex-start; gap: 8px; padding: 8px 10px;
  background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); cursor: pointer; transition: all 0.15s;
  font-family: var(--mono); font-size: 0.7rem; position: relative; margin-bottom: 6px;
}
.wf-item:hover { border-color: var(--teal); background: var(--teal-dim); }
.wf-item.active-run { border-color: var(--teal); background: var(--teal-dim); }
.wf-item-body { flex: 1; min-width: 0; }
.wf-item-title { color: var(--text); font-weight: 700; padding-right: 20px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wf-item-meta { color: var(--text-dim); margin-top: 3px; font-size: 0.63rem; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.status-chip {
  font-size: 0.58rem; padding: 1px 6px; border-radius: 10px; border: 1px solid var(--border-glow);
  text-transform: uppercase; letter-spacing: 0.5px;
}
.status-chip.pending  { color: var(--text-dim); }
.status-chip.running  { color: var(--teal); border-color: var(--teal); }
.status-chip.paused   { color: var(--amber); border-color: var(--amber); }
.status-chip.done     { color: var(--green); border-color: var(--green); }
.status-chip.failed   { color: var(--red); border-color: var(--red); }
.status-chip.cancelled{ color: var(--text-dim); text-decoration: line-through; }
.status-chip.active   { color: var(--teal); border-color: var(--teal); }
.status-chip.skipped  { color: var(--text-dim); text-decoration: line-through; }

.item-delete-btn {
  position: absolute; right: 6px; top: 6px; width: 20px; height: 20px;
  border-radius: 4px; border: 1px solid transparent; background: transparent;
  color: var(--text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: all 0.15s; font-size: 10px;
}
.item-delete-btn:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }

/* ── MAIN AREA & CLI VIEW ─────────────────────────────────────────────── */
.wf-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: var(--bg); min-height: 0; }
.wf-feed { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 10px; }

.wf-welcome {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  height: 100%; text-align: center; gap: 20px; padding: 40px;
}
.wf-welcome-sigil { font-size: 52px; opacity: 0.15; line-height: 1; font-family: var(--mono); }
.wf-welcome-title { font-family: var(--mono); font-size: 1rem; color: var(--teal); text-transform: uppercase; letter-spacing: 3px; }
.wf-welcome-body { font-size: 0.85rem; color: var(--text-dim); line-height: 1.7; max-width: 480px; }
.wf-welcome-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }

/* CLI View Styles */
.cli-view { padding: 0; font-family: var(--mono); font-size: 0.75rem; color: var(--text); }
.cli-run-header { margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }
.cli-step { margin-bottom: 24px; border-left: 2px solid var(--border); padding-left: 14px; position: relative; }
.cli-step::before { content: ''; position: absolute; left: -6px; top: 0; width: 10px; height: 10px; border-radius: 50%; background: var(--surface); border: 2px solid var(--border); }
.cli-step.status-active { border-color: var(--teal); }
.cli-step.status-active::before { border-color: var(--teal); background: var(--teal); box-shadow: 0 0 8px var(--teal); }
.cli-step.status-done { border-color: var(--green); }
.cli-step.status-done::before { border-color: var(--green); background: var(--bg); }
.cli-step.status-failed { border-color: var(--red); }
.cli-step.status-failed::before { border-color: var(--red); background: var(--red); }
.cli-step.status-skipped { border-color: var(--text-dim); border-style: dashed; }
.cli-step.status-skipped::before { border-color: var(--text-dim); background: var(--bg); }

.cli-header { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-weight: bold; flex-wrap: wrap; }
.cli-num { color: var(--teal); font-size: 0.8rem; }
.cli-title { color: var(--text-bright); font-family: var(--sans); font-size: 0.95rem; }
.cli-handler { color: var(--text-dim); font-size: 0.7rem; }
.cli-status { margin-left: auto; font-size: 0.65rem; text-transform: uppercase; padding: 2px 8px; border-radius: 12px; border: 1px solid var(--border); }
.cli-status.active { color: var(--teal); border-color: var(--teal); background: var(--teal-dim); }
.cli-status.done { color: var(--green); border-color: var(--green); background: var(--green-dim); }
.cli-status.failed { color: var(--red); border-color: var(--red); background: var(--red-dim); }
.cli-status.pending { color: var(--text-dim); }

.cli-events { margin-bottom: 12px; display: flex; flex-direction: column; gap: 6px; }
.cli-event { display: flex; gap: 10px; color: var(--text-dim); line-height: 1.5; font-size: 0.75rem; }
.cli-event .ts { color: var(--border-glow); width: 65px; flex-shrink: 0; }
.cli-event .msg { flex: 1; word-break: break-word; }
.cli-event.type-step_done .msg { color: var(--green); }
.cli-event.type-step_failed .msg { color: var(--red); }
.cli-event.type-run_started .msg { color: var(--teal); }

.cli-result { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px; margin-top: 8px; color: var(--text); white-space: pre-wrap; font-family: var(--sans); font-size: 0.85rem; line-height: 1.6; }
.cli-actions { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }

.spin-sm {
  display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(45,212,191,0.3);
  border-top-color: var(--teal); border-radius: 50%; animation: spin 0.7s linear infinite; vertical-align: middle; margin-right: 4px;
}

/* ── INPUT BAR — Note / Start Run ─────────────────────────────────────── */
.wf-input-bar { padding: 12px 16px; border-top: 1px solid var(--border); background: var(--surface); flex-shrink: 0; }
.input-bar-bottom { display: flex; gap: 8px; align-items: flex-end; }
.wf-textarea {
  flex: 1; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text-bright);
  font-family: var(--sans); font-size: 0.88rem; padding: 10px 14px;
  resize: none; min-height: 44px; max-height: 160px;
  transition: border-color 0.15s; line-height: 1.5; overflow-y: auto;
}
.wf-textarea:focus { outline: none; border-color: var(--teal); }
.wf-textarea::placeholder { color: var(--text-dim); }
.wf-textarea:disabled { opacity: 0.5; }

.btn-send {
  width: 44px; height: 44px; border-radius: var(--radius);
  background: var(--teal); color: #000; border: none;
  cursor: pointer; transition: all 0.15s; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 16px;
}
.btn-send:hover:not(:disabled) { filter: brightness(1.15); transform: translateY(-1px); }
.btn-send:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

/* ── MODALS ─────────────────────────────────────────────────────────────── */
.wf-modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(3px);
  z-index: 9999; display: none; align-items: center; justify-content: center; padding: 16px;
}
.wf-modal-overlay.open { display: flex; }
.wf-modal {
  background: var(--surface); border: 1px solid var(--border-glow);
  border-radius: var(--radius-lg); width: 100%; max-width: 640px; max-height: 90vh;
  display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.7); animation: modalIn 0.2s ease;
}
.wf-modal.wide { max-width: 900px; }
.wf-modal.tall { height: 85vh; max-height: 85vh; }
.wf-modal-header {
  padding: 16px 20px; border-bottom: 1px solid var(--border);
  display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
  flex-wrap: wrap; gap: 10px;
}
.wf-modal-title { font-family: var(--mono); font-size: 0.78rem; color: var(--teal); text-transform: uppercase; letter-spacing: 1.5px; display: flex; align-items: center; gap: 8px; }
.wf-modal-close {
  width: 26px; height: 26px; border-radius: 4px; border: 1px solid var(--border);
  background: transparent; color: var(--text-dim); cursor: pointer;
  display: flex; align-items: center; justify-content: center; font-size: 13px; transition: all 0.15s;
}
.wf-modal-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }
.wf-modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.wf-modal-body.flush { padding: 0; overflow: hidden; display: flex; flex-direction: column; }
.wf-modal-footer {
  padding: 12px 20px; border-top: 1px solid var(--border);
  display: flex; justify-content: flex-end; gap: 8px; flex-shrink: 0; background: var(--bg);
}

.btn-forge-primary {
  padding: 8px 18px; background: var(--teal); color: #000; border: none;
  border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.76rem;
  font-weight: 700; text-transform: uppercase; letter-spacing: 1px; transition: all 0.15s;
}
.btn-forge-primary:hover { filter: brightness(1.1); }
.btn-forge-secondary {
  padding: 8px 18px; background: transparent; color: var(--text-dim); border: 1px solid var(--border);
  border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.76rem; transition: all 0.15s;
}
.btn-forge-secondary:hover { border-color: var(--border-glow); color: var(--text); }
.btn-forge-secondary.danger:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }
.btn-forge-secondary.warn:hover { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }
.btn-forge-danger {
  padding: 6px 14px; background: var(--red-dim); color: var(--red); border: 1px solid var(--red);
  border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.72rem; transition: all 0.15s;
}
.btn-forge-danger:hover { background: var(--red); color: #fff; }

/* Step editor rows inside the workflow-builder modal */
.step-editor-row {
  background: var(--card); border: 1px solid var(--border); border-radius: var(--radius);
  padding: 10px; margin-bottom: 8px;
}
.step-editor-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px; }
.step-editor-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.step-editor-num { font-family: var(--mono); font-size: 0.65rem; color: var(--teal); }
.step-move-btn, .step-remove-btn {
  width: 22px; height: 22px; border-radius: 4px; border: 1px solid var(--border);
  background: transparent; color: var(--text-dim); cursor: pointer; font-size: 11px;
  display: flex; align-items: center; justify-content: center; transition: all 0.15s;
}
.step-move-btn:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }
.step-remove-btn:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }

.sep { height: 1px; background: var(--border); margin: 10px 0; }
.hint { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); margin-top: 3px; }

/* Cloned scheduler tables (Logs / Locks modals) */
.table-responsive { width: 100%; overflow: auto; max-height: 100%; }
.forge-table { width: 100%; border-collapse: collapse; font-family: var(--mono); font-size: 0.75rem; text-align: left; }
.forge-table th { padding: 8px 12px; border-bottom: 1px solid var(--border); color: var(--text-dim); font-weight: normal; text-transform: uppercase; letter-spacing: 1px; }
.forge-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); color: var(--text); }
.forge-table tr:hover td { background: var(--card-hover); }

/* ── TOAST ──────────────────────────────────────────────────────────────── */
.toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 6px; pointer-events: none; }
.toast {
  padding: 8px 14px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--border);
  font-family: var(--mono); font-size: 0.76rem; color: var(--text); box-shadow: 0 4px 20px rgba(0,0,0,0.5);
  animation: toastIn 0.2s ease; pointer-events: all; cursor: pointer; display: flex; gap: 6px; align-items: center; max-width: 300px;
}
.toast.success { border-color: var(--green); }
.toast.error   { border-color: var(--red); color: var(--red); }
.toast.info    { border-color: var(--teal); }
.toast.out     { animation: toastOut 0.2s forwards; }

@keyframes spin { to { transform: rotate(360deg); } }
@keyframes toastIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
@keyframes toastOut { to { opacity: 0; transform: translateY(8px); } }
@keyframes modalIn { from { opacity: 0; transform: scale(0.96) translateY(-8px); } to { opacity: 1; transform: none; } }

@media (max-width: 900px) {
    .workspace-body { grid-template-columns: 1fr; grid-template-rows: auto auto 1fr; }
    .generate-bar { grid-row: 2; border-right: none; }
    .result-panel { grid-row: 3; grid-column: 1; border-top: 1px solid var(--border); }
    .forge-layout { grid-template-columns: 1fr; grid-template-rows: 52px 200px 1fr; grid-template-areas: "header" "sidebar" "main"; }
    .forge-sidebar { border-right: none; border-bottom: 1px solid var(--border); }
}

input[type=number]::-webkit-inner-spin-button, 
input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }

/* ── PAGINATION BAR ── */
.pg-bar { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 4px 2px; margin-top: 4px; font-family: var(--mono); }
.pg-btn { width: 26px; height: 26px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; flex-shrink: 0; }
.pg-btn:hover:not(:disabled) { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }
.pg-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.pg-input { width: 40px; height: 26px; text-align: center; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.72rem; padding: 0 2px; }
.pg-input:focus { outline: none; border-color: var(--teal); }
.pg-of { font-size: 0.68rem; color: var(--text-dim); white-space: nowrap; }
</style>
</head>
<body>
<div class="wf-layout">

<div class="wf-flyout-backdrop" id="flyoutBackdrop" onclick="WF.closeFlyouts()"></div>

<header class="wf-header">
  <button class="wf-hamburger" id="hamburgerLeft" onclick="WF.toggleFlyout('left')" title="Workflows &amp; Runs">
    <i class="bi bi-layout-text-sidebar"></i>
  </button>
  <div class="wf-logo" style="flex:1; justify-content:center;">
    <div class="wf-logo-icon">⚙</div>
    <span>WTF</span>
  </div>
  <div style="display:flex;align-items:center;gap:6px;">
    <div class="phase-indicator" id="headerPhaseWrap" style="display:none;">
      <div class="phase-dot"></div>
      <span id="headerPhase">—</span>
    </div>
    <div class="phase-indicator" id="heartbeatWrap" title="Scheduler heartbeat">
      <div class="phase-dot" id="heartbeatDot" style="background:var(--red);"></div>
      <span>scheduler</span>
    </div>
    <button class="btn-icon-sm" onclick="WF.openLogsModal()" title="Scheduler Logs">
      <i class="bi bi-terminal"></i>
    </button>
    <button class="btn-icon-sm" onclick="WF.openLocksModal()" title="Global Locks">
      <i class="bi bi-lock-fill"></i>
    </button>
    <button class="btn-icon-sm" onclick="WF.openNewWorkflowModal()" title="New Workflow">
      <i class="bi bi-plus-circle"></i>
    </button>
    <button class="btn-icon-sm" onclick="WF.toggleTheme()" id="themeBtn" title="Toggle Theme" style="display:none;">
      <i class="bi bi-moon-stars"></i>
    </button>
  </div>
</header>

<!-- LEFT FLYOUT — Workflows / Runs -->
<div class="wf-flyout wf-flyout-left" id="flyoutLeft">
  <div class="flyout-head">
    <span class="flyout-head-title">Workflows &amp; Runs</span>
    <button class="flyout-close" onclick="WF.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
  </div>
  <div style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
    <div class="left-tabs">
      <button class="left-tab active" data-ltab="runs">Runs</button>
      <button class="left-tab" data-ltab="workflows">Workflows</button>
    </div>
    <div class="left-tab-body">

      <!-- RUNS TAB -->
      <div class="left-tab-content active" id="ltab-runs">
        <div id="runsList">
          <div class="empty-state"><div class="empty-icon">⚙</div>No runs yet</div>
        </div>
        <div class="pg-bar" id="runsListPager" style="display:none;">
          <button class="pg-btn" id="runsListPagerPrev"><i class="bi bi-chevron-left"></i></button>
          <input type="text" class="pg-input" id="runsListPagerInput" inputmode="numeric">
          <span class="pg-of">/ <span id="runsListPagerTotal">1</span></span>
          <button class="pg-btn" id="runsListPagerNext"><i class="bi bi-chevron-right"></i></button>
        </div>
      </div>

      <!-- WORKFLOWS TAB -->
      <div class="left-tab-content" id="ltab-workflows">
        <div id="workflowsList">
          <div class="empty-state"><div class="empty-icon">⚙</div>No workflows yet</div>
        </div>
        <div class="pg-bar" id="workflowsListPager" style="display:none;">
          <button class="pg-btn" id="workflowsListPagerPrev"><i class="bi bi-chevron-left"></i></button>
          <input type="text" class="pg-input" id="workflowsListPagerInput" inputmode="numeric">
          <span class="pg-of">/ <span id="workflowsListPagerTotal">1</span></span>
          <button class="pg-btn" id="workflowsListPagerNext"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div style="margin-top:10px;">
          <button class="btn-forge-secondary" style="width:100%;font-size:0.7rem;" onclick="WF.openNewWorkflowModal()">
            <i class="bi bi-plus-lg"></i> New Workflow
          </button>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- MAIN -->
<main class="wf-main">
  <div class="wf-feed" id="feed">
    <div class="wf-welcome" id="welcomeState">
      <div class="wf-welcome-sigil">⚙</div>
      <div class="wf-welcome-title">WTF — Work The Flow</div>
      <div class="wf-welcome-body">
        A deterministic orchestration control panel. Define a workflow as an ordered
        list of steps, start a run, and track/execute each step here — with full
        human gating, retries, and an audit trail. WTF has no built-in dependency on
        any other SAGE tool; step behavior is entirely pluggable.
      </div>
      <div class="wf-welcome-actions">
        <button class="btn-forge-primary" onclick="WF.openNewWorkflowModal()"><i class="bi bi-plus-lg"></i> New Workflow</button>
      </div>
    </div>
  </div>

  <div class="wf-input-bar">
    <div class="input-bar-bottom">
      <textarea class="wf-textarea" id="noteInput"
        placeholder="Add a note to this run's activity log…"
        rows="1" disabled></textarea>
      <button class="btn-send" id="btnAddNote" onclick="WF.addNote()" disabled title="Add note">
        <i class="bi bi-chat-left-text"></i>
      </button>
    </div>
  </div>
</main>

</div><!-- /wf-layout -->

<!-- NEW/EDIT WORKFLOW MODAL -->
<div class="wf-modal-overlay" id="workflowModal">
  <div class="wf-modal">
    <div class="wf-modal-header">
      <div class="wf-modal-title" id="workflowModalTitle">New Workflow</div>
      <div style="display:flex; gap:8px; align-items:center; flex:1; justify-content:flex-end;">
          <button class="btn-forge-secondary" style="border-color:var(--teal); color:var(--teal);" onclick="WF.openWiringModal()">
              <i class="bi bi-bezier2"></i> Visual Wiring
          </button>
          <button class="wf-modal-close" onclick="WF.closeModal('workflowModal')"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <div class="wf-modal-body">
      <input type="hidden" id="editWorkflowId" value="">
      <div class="ctx-group">
        <label class="ctx-label">Title</label>
        <input class="ctx-input" type="text" id="editWorkflowTitle" placeholder="e.g. Episode Compile &amp; Publish">
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Description</label>
        <textarea class="ctx-textarea" id="editWorkflowDescription" placeholder="What this workflow does…"></textarea>
      </div>
      <div class="sep"></div>
      <div class="ctx-group">
        <label class="ctx-label">Steps (executed in order)</label>
        <div id="stepEditorList"></div>
        <button class="btn-forge-secondary" style="width:100%;font-size:0.7rem;margin-top:4px;" onclick="WF.addStepEditorRow()">
          <i class="bi bi-plus-lg"></i> Add Step
        </button>
      </div>
      <div class="hint">"manual" steps wait for you to mark them done. "noop_delay" auto-completes instantly — useful as a placeholder while designing a workflow. More handlers (e.g. wrish, image generation) get added over time without changing this form.</div>
    </div>
    <div class="wf-modal-footer">
      <button class="btn-forge-secondary" id="btnDeleteWorkflow" style="display:none; margin-right:auto;" onclick="WF.deleteWorkflow()">
        <i class="bi bi-trash"></i> Delete
      </button>
      <button class="btn-forge-secondary" onclick="WF.closeModal('workflowModal')">Cancel</button>
      <button class="btn-forge-primary" onclick="WF.saveWorkflow()">Save Workflow</button>
    </div>
  </div>
</div>

<!-- START RUN MODAL -->
<div class="wf-modal-overlay" id="startRunModal">
  <div class="wf-modal">
    <div class="wf-modal-header">
      <div class="wf-modal-title">Start Run</div>
      <button class="wf-modal-close" onclick="WF.closeModal('startRunModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="wf-modal-body">
      <input type="hidden" id="startRunWorkflowId" value="">
      <div class="ctx-group">
        <label class="ctx-label">Run Title (optional)</label>
        <input class="ctx-input" type="text" id="startRunTitle" placeholder="Defaults to workflow title + timestamp">
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Initial Context (optional JSON)</label>
        <textarea class="ctx-textarea tall" id="startRunContext" placeholder='{"episode_key": "EP29"}'></textarea>
        <div class="hint">Free-form key/value data handlers may read from and write back to during the run.</div>
      </div>
      <div class="ctx-group" style="margin-top:12px;">
        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-family:var(--mono); font-size:0.75rem; color:var(--text);">
            <input type="checkbox" id="startRunAutoAdvance" checked> Auto-Advance (scheduler heartbeat drives this run automatically)
        </label>
        <div class="hint">Unchecked = manual mode: nothing advances until you click "Proceed" yourself.</div>
      </div>
    </div>
    <div class="wf-modal-footer">
      <button class="btn-forge-secondary" onclick="WF.closeModal('startRunModal')">Cancel</button>
      <button class="btn-forge-primary" onclick="WF.confirmStartRun()">Start Run</button>
    </div>
  </div>
</div>

<!-- CLONED: SCHEDULER LOGS MODAL -->
<div class="wf-modal-overlay" id="logsModal">
  <div class="wf-modal wide tall">
    <div class="wf-modal-header">
      <div class="wf-modal-title"><i class="bi bi-terminal"></i> Scheduler Logs</div>
      <div style="display:flex; gap:8px; align-items:center; flex:1; justify-content:flex-end; flex-wrap:wrap;">
        <select id="logFileSelect" class="ctx-select" style="max-width:280px;" onchange="WF.changeLogFile()"></select>
        <button class="btn-icon-sm" id="btnToggleLogRefresh" onclick="WF.toggleLogRefresh()" title="Toggle Auto-Refresh">
          <i class="bi bi-pause-fill"></i>
        </button>
        <button class="wf-modal-close" onclick="WF.closeLogsModal()"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <div class="wf-modal-body flush" style="background:#050505;">
      <pre id="logContentBox" style="margin:0; padding:15px; flex:1; overflow-y:auto; color:#0f0; font-family:var(--mono); font-size:13px; white-space:pre-wrap; word-break:break-all;">Loading...</pre>
    </div>
  </div>
</div>

<!-- CLONED: GLOBAL LOCKS MODAL -->
<div class="wf-modal-overlay" id="locksModal">
  <div class="wf-modal wide">
    <div class="wf-modal-header">
      <div class="wf-modal-title"><i class="bi bi-lock-fill"></i> Global Lock Manager</div>
      <div style="display:flex; gap:8px;">
        <button class="btn-forge-secondary" onclick="WF.cleanupLocks()">Cleanup Expired</button>
        <button class="wf-modal-close" onclick="WF.closeModal('locksModal')"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <div class="wf-modal-body flush">
      <div class="table-responsive" style="flex:1;">
        <table class="forge-table">
          <thead>
            <tr>
              <th>Task</th>
              <th>Lock Key</th>
              <th>Age</th>
              <th>TTL</th>
              <th>PID</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="locksTableBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>








<style>
.wire-workspace {
    position: relative; flex: 1; overflow-y: auto; overflow-x: hidden;
    background: var(--bg); padding: 20px 10px;
}
.wire-canvas {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    pointer-events: none; z-index: 10;
}
.wire-path {
    fill: none; stroke: var(--teal); stroke-width: 3px;
    stroke-dasharray: 6; animation: flowDash 1s linear infinite;
}
@keyframes flowDash { to { stroke-dashoffset: -12; } }

.wire-node {
    background: var(--card); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 12px; margin-bottom: 20px;
    position: relative; z-index: 20; box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}
.wire-node-title { font-family: var(--sans); font-weight: 700; color: var(--text-bright); font-size: 0.85rem; text-align: center; margin-bottom: 10px; }
.wire-ports { display: flex; justify-content: space-between; position: relative; }

.wire-port-group { display: flex; flex-direction: column; gap: 10px; }
.wire-port-group.right { align-items: flex-end; }

.wire-port-wrap { display: flex; align-items: center; gap: 6px; cursor: pointer; }
.wire-port-label { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; }

.wire-port {
    width: 16px; height: 16px; border-radius: 50%; background: var(--surface);
    border: 2px solid var(--text-dim); transition: all 0.2s; position: relative;
}
/* Left side (Inputs) */
.wire-port-wrap.in { flex-direction: row; }
.wire-port.in:hover { border-color: var(--amber); box-shadow: 0 0 8px var(--amber-glow); }
.wire-port.in.connected { background: var(--teal); border-color: var(--teal); }

/* Right side (Outputs) */
.wire-port-wrap.out { flex-direction: row-reverse; }
.wire-port.out:hover { border-color: var(--teal); box-shadow: 0 0 8px var(--teal-glow); }
.wire-port.out.active { background: var(--teal); border-color: var(--teal); box-shadow: 0 0 12px var(--teal); transform: scale(1.2); }
.wire-port.out.connected { border-color: var(--teal); }

.wire-help { text-align: center; font-family: var(--mono); font-size: 0.7rem; color: var(--text-dim); margin-bottom: 15px; }

/* Wiring Manifest List */
.wire-manifest {
    border-top: 1px solid var(--border);
    background: var(--surface);
    padding: 12px;
    max-height: 180px;
    overflow-y: auto;
    flex-shrink: 0;
}
.wire-manifest-empty {
    color: var(--text-dim); font-family: var(--mono); font-size: 0.7rem; 
    text-align: center; padding: 10px;
}
.wire-manifest-title {
    font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); 
    text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;
}
.wire-manifest-item {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    padding: 6px 8px; background: var(--card); border: 1px solid var(--border); 
    border-radius: 4px; margin-bottom: 6px;
}
.wire-var-badge {
    font-family: var(--mono); color: var(--amber); background: var(--amber-dim); 
    border: 1px solid var(--amber-glow); padding: 2px 6px; border-radius: 4px; 
    font-size: 0.7rem; font-weight: bold;
}
.wire-node-badge {
    font-family: var(--sans); color: var(--text-bright); font-size: 0.75rem; 
    font-weight: 600; display: flex; align-items: center; gap: 4px;
}
.wire-node-type {
    font-family: var(--mono); color: var(--text-dim); font-size: 0.65rem; 
    font-weight: normal;
}


</style>

<div class="wf-modal-overlay" id="wiringModal">
  <div class="wf-modal tall wide">
    <div class="wf-modal-header">
      <div class="wf-modal-title"><i class="bi bi-bezier2"></i> Visual Wiring</div>
      <button class="wf-modal-close" onclick="WF.closeModal('wiringModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="wf-modal-body flush" style="display:flex; flex-direction:column;">
      <div class="wire-help" style="margin-top:10px;">
          Tap an <strong>Output</strong> (right) to select it, then tap an <strong>Input</strong> (left) to connect.<br>
          Tap a connected Input to disconnect it.
      </div>
      <div class="wire-workspace" id="wireWorkspace">
          <svg class="wire-canvas" id="wireCanvas"></svg>
          <div id="wireNodesContainer"></div>
      </div>
      <!-- NEW: MANIFEST CONTAINER -->
      <div class="wire-manifest" id="wireManifest"></div>
    </div>
    <div class="wf-modal-footer">
      <button class="btn-forge-secondary" onclick="WF.closeModal('wiringModal')">Cancel</button>
      <button class="btn-forge-primary" onclick="WF.saveWiring()">Apply Wiring</button>
    </div>
  </div>
</div>











<div class="toast-container" id="toastContainer"></div>
<script>
window.WF = (function() {
  'use strict';

  // ── State ─────────────────────────────────────────────────────────────
  let _workflows        = [];
  let _runs             = [];
  let _wrishSessions    = [];
  let _sqlishTemplates  = [];
  let _splashTemplates  = [];
  let _schedTasks       = [];
  let _schedWrappers    = [];
  let _currentRun       = null;   
  let _events           = [];
  let _pollTimer        = null;
  let _isBusy           = false;

  // Pagination state — one entry per paginated list container id.
  let _pg = {
    runsList: { page: 1, limit: 10, total_pages: 1 },
    workflowsList: { page: 1, limit: 10, total_pages: 1 }
  };

  // Logs modal state
  let _logRefreshInterval = null;
  let _logFileListInterval = null;
  let _logAutoRefresh = true;
  let _currentLogFile = '';

  const HANDLER_LABELS = {
    manual: 'Manual (human-gated)',
    noop_delay: 'Noop / Placeholder (auto-completes)',
    scheduler_task: 'Scheduler Task (runs a Scheduler Forge task, waits for it)',
    wrish_run: 'Wrish Run (executes a Wrish session step)',
    sqlish_run: 'Sqlish Run (Structured SQL Extraction)',
    splash_run: 'Splash Run (Writers Room)',
    sub_workflow: 'Sub-Workflow (Run another WTF Workflow)',
    continuity_ingest: 'Continuity Ingest (Extract tags & sketches to DB)'
  };

  const HANDLER_CONFIG_HINTS = {
    scheduler_task: '{"scheduled_task_id": 42, "wait_mode": "trigger_new"}\n\n"scheduled_task_id" = id from Scheduler Forge.\n"wait_mode": "trigger_new" (default, sets run_now=1) or "watch_latest" (just watches, doesn\'t trigger).',
    
    
    wrish_run: '{\n  "wrish_session_id": "your-session-uuid",\n  "wrish_user_id": 1,\n  "initial_message": "Optional initial prompt",\n  "max_steps": 100,\n  "source_text_context_key": "worldbuilding_lore",\n  "writeback_context_key": "wrish_plot_output"\n}',
    
    
    
    
    sqlish_run: '{\n  "template_id": 1,\n  "source_text_context_key": "wrish_plot_output"\n}',
    splash_run: '{\n  "template_id": 1,\n  "source_text_context_key": "wrish_plot_output",\n  "writeback_context_key": "splash_script_output"\n}',
    sub_workflow: '{\n  "workflow_id": 12,\n  "input_mapping": {"child_key": "parent_key"},\n  "output_mapping": {"parent_key": "child_key"}\n}',
    continuity_ingest: '{\n  "source_text_context_key": "splash_output",\n  "writeback_context_key": "ingest_result"\n}'
  };

  
  
  
   // Generates a string like "Wrish Step (14:05:22)"
  function getAutoLabel(handlerKey) {
      const typeStr = HANDLER_LABELS[handlerKey] ? HANDLER_LABELS[handlerKey].split(' ')[0] : handlerKey;
      const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
      return `${typeStr} Step (${timeStr})`;
  }
  
   // ── VISUAL WIRING ENGINE ──────────────────────────────────────────────
  let _wireNodes = [];
  let _activeOutPort = null;
  let _connections = []; // Array of { fromId: 'out_step0', toId: 'in_step1', key: 'wire_step0_out' }

  function openWiringModal() {
      const rows = document.querySelectorAll('#stepEditorList .step-editor-row');
      if (rows.length === 0) { toast('Add steps before wiring.', 'error'); return; }

      _wireNodes = [];
      _connections = [];
      _activeOutPort = null;

      const container = document.getElementById('wireNodesContainer');
      container.innerHTML = '';

      // 1. Parse current form state into Nodes
      rows.forEach((row, index) => {
          const label = row.querySelector('.step-field-label').value.trim() || `Step ${index + 1}`;
          const handler = row.querySelector('.step-field-handler').value;
          
          let hasIn = false; let hasOut = false;
          let inVal = ''; let outVal = '';
          let inSelector = ''; let outSelector = '';

          // Determine which ports this handler has
          if (['wrish_run', 'sqlish_run', 'splash_run', 'continuity_ingest'].includes(handler)) {
              hasIn = true;
              if (handler === 'wrish_run') inSelector = '.wrish-source-key';
              if (handler === 'sqlish_run') inSelector = '.sqlish-source-key';
              if (handler === 'splash_run') inSelector = '.splash-source-key';
              if (handler === 'continuity_ingest') inSelector = '.continuity-source-key';
              inVal = row.querySelector(inSelector).value.trim();
          }

          if (['wrish_run', 'splash_run', 'continuity_ingest'].includes(handler)) {
              hasOut = true;
              if (handler === 'wrish_run') outSelector = '.wrish-writeback-key';
              if (handler === 'splash_run') outSelector = '.splash-writeback-key';
              if (handler === 'continuity_ingest') outSelector = '.continuity-writeback-key';
              outVal = row.querySelector(outSelector).value.trim();
          }

          _wireNodes.push({
              index: index,
              label: label,
              handler: handler,
              hasIn: hasIn, hasOut: hasOut,
              inVal: inVal, outVal: outVal,
              inSelector: inSelector, outSelector: outSelector,
              rowEl: row // Keep ref to original row for saving later
          });
      });

      // 2. Render HTML Nodes
      _wireNodes.forEach(node => {
          const inId = `wire_in_${node.index}`;
          const outId = `wire_out_${node.index}`;
          
          let html = `<div class="wire-node" id="wire_node_${node.index}">`;
          html += `<div class="wire-node-title">${node.index + 1}. ${escHtml(node.label)}</div>`;
          html += `<div class="wire-ports">`;
          
          html += `<div class="wire-port-group left">`;
          if (node.hasIn) {
              html += `<div class="wire-port-wrap in" onclick="WF.handlePortTap('in', ${node.index}, '${inId}')">
                         <div class="wire-port in" id="${inId}"></div>
                         <div class="wire-port-label">Source</div>
                       </div>`;
          }
          html += `</div>`;

          html += `<div class="wire-port-group right">`;
          if (node.hasOut) {
              html += `<div class="wire-port-wrap out" onclick="WF.handlePortTap('out', ${node.index}, '${outId}')">
                         <div class="wire-port out" id="${outId}"></div>
                         <div class="wire-port-label">Writeback</div>
                       </div>`;
          }
          html += `</div></div></div>`;
          
          container.insertAdjacentHTML('beforeend', html);
      });

      // 3. Infer existing connections based on matching keys
      _wireNodes.forEach(inNode => {
          if (inNode.hasIn && inNode.inVal !== '') {
              const outNode = _wireNodes.find(n => n.hasOut && n.outVal === inNode.inVal);
              if (outNode) {
                  _connections.push({
                      fromId: `wire_out_${outNode.index}`,
                      toId: `wire_in_${inNode.index}`,
                      key: inNode.inVal
                  });
              }
          }
      });

      document.getElementById('wiringModal').classList.add('open');
      
      // Delay initial draw slightly so modal CSS transitions finish and bounding boxes are accurate
      setTimeout(drawLines, 50);
      document.getElementById('wireWorkspace').addEventListener('scroll', drawLines);
      window.addEventListener('resize', drawLines);
  }

  function handlePortTap(type, nodeIndex, portId) {
      if (type === 'out') {
          // Select Output
          if (_activeOutPort) document.getElementById(_activeOutPort).classList.remove('active');
          if (_activeOutPort === portId) {
              _activeOutPort = null; // Toggle off
          } else {
              _activeOutPort = portId;
              document.getElementById(portId).classList.add('active');
          }
      } 
      else if (type === 'in') {
          // Check if already connected -> Disconnect
          const existingConnIndex = _connections.findIndex(c => c.toId === portId);
          if (existingConnIndex !== -1) {
              _connections.splice(existingConnIndex, 1);
              drawLines();
              if (!_activeOutPort) return; // Disconnected, stop here unless we are connecting a new one
          }

          // If we have an active output, Connect!
          if (_activeOutPort) {
              // Prevent connecting to self
              const fromIndex = parseInt(_activeOutPort.split('_').pop());
              if (fromIndex === nodeIndex) {
                  toast("Can't connect a step to itself.", "error");
                  return;
              }

              // Generate a stable key for this link
              const linkKey = `wire_step${fromIndex}_out`;
              
              _connections.push({
                  fromId: _activeOutPort,
                  toId: portId,
                  key: linkKey
              });

              document.getElementById(_activeOutPort).classList.remove('active');
              _activeOutPort = null;
              drawLines();
          }
      }
  }

  function drawLines() {
      const canvas = document.getElementById('wireCanvas');
      const workspace = document.getElementById('wireWorkspace');
      const wsRect = workspace.getBoundingClientRect();
      
      // Match SVG size to inner scroll height
      canvas.style.height = workspace.scrollHeight + 'px';
      
      let svgHtml = '';

      // Reset dot states
      document.querySelectorAll('.wire-port').forEach(el => el.classList.remove('connected'));

      _connections.forEach(conn => {
          const fromEl = document.getElementById(conn.fromId);
          const toEl = document.getElementById(conn.toId);
          if (!fromEl || !toEl) return;

          fromEl.classList.add('connected');
          toEl.classList.add('connected');

          const fromRect = fromEl.getBoundingClientRect();
          const toRect = toEl.getBoundingClientRect();

          // Calculate center points relative to the scrollable workspace
          const x1 = (fromRect.left + (fromRect.width / 2)) - wsRect.left + workspace.scrollLeft;
          const y1 = (fromRect.top + (fromRect.height / 2)) - wsRect.top + workspace.scrollTop;
          const x2 = (toRect.left + (toRect.width / 2)) - wsRect.left + workspace.scrollLeft;
          const y2 = (toRect.top + (toRect.height / 2)) - wsRect.top + workspace.scrollTop;

          // Bezier curve control points for elegant 'S' curve
          const offset = Math.max(80, Math.abs(x2 - x1) / 2);
          const path = `M ${x1} ${y1} C ${x1 + offset} ${y1}, ${x2 - offset} ${y2}, ${x2} ${y2}`;

          svgHtml += `<path class="wire-path" d="${path}"></path>`;
      });

      canvas.innerHTML = svgHtml;
      
      renderWiringManifest(); 
  }
  
  
  
  
  
 function renderWiringManifest() {
      const container = document.getElementById('wireManifest');
      if (!container) return;

      if (_connections.length === 0) {
          container.innerHTML = '<div class="wire-manifest-empty">No connections wired yet.</div>';
          return;
      }

      let html = '<div class="wire-manifest-title">Active Connections</div>';
      
      _connections.forEach(conn => {
          // Extract indices from the HTML IDs
          const fromIndex = parseInt(conn.fromId.split('_').pop());
          const toIndex = parseInt(conn.toId.split('_').pop());
          const fromNode = _wireNodes[fromIndex];
          const toNode = _wireNodes[toIndex];
          
          // Get clean handler names (e.g. "wrish_run" -> "Wrish")
          const fromType = HANDLER_LABELS[fromNode.handler] ? HANDLER_LABELS[fromNode.handler].split(' ')[0] : fromNode.handler;
          const toType = HANDLER_LABELS[toNode.handler] ? HANDLER_LABELS[toNode.handler].split(' ')[0] : toNode.handler;

          html += `
          <div class="wire-manifest-item">
              <span class="wire-var-badge">${escHtml(conn.key)}</span>
              <span class="wire-node-badge">
                  <span class="wire-node-type">[Step ${fromIndex + 1}: ${fromType}]</span>
                  ${escHtml(fromNode.label)}
              </span>
              <i class="bi bi-arrow-right" style="color:var(--teal); font-weight:bold;"></i>
              <span class="wire-node-badge">
                  <span class="wire-node-type">[Step ${toIndex + 1}: ${toType}]</span>
                  ${escHtml(toNode.label)}
              </span>
          </div>
          `;
      });
      
      container.innerHTML = html;
  }
  
  
  

  function saveWiring() {
      // 1. Clear out keys that are managed by the visual editor
      _wireNodes.forEach(node => {
          if (node.hasIn && node.inVal.startsWith('wire_')) {
              node.rowEl.querySelector(node.inSelector).value = '';
          }
          if (node.hasOut && node.outVal.startsWith('wire_')) {
              node.rowEl.querySelector(node.outSelector).value = '';
          }
      });

      // 2. Apply connections to the form fields
      _connections.forEach(conn => {
          const fromIndex = parseInt(conn.fromId.split('_').pop());
          const toIndex = parseInt(conn.toId.split('_').pop());
          
          const fromNode = _wireNodes[fromIndex];
          const toNode = _wireNodes[toIndex];

          fromNode.rowEl.querySelector(fromNode.outSelector).value = conn.key;
          toNode.rowEl.querySelector(toNode.inSelector).value = conn.key;
          
          // Trigger the 'input' event so the underlying JSON syncs
          fromNode.rowEl.querySelector(fromNode.outSelector).dispatchEvent(new Event('input'));
          toNode.rowEl.querySelector(toNode.inSelector).dispatchEvent(new Event('input'));
      });

      toast("Wiring applied!", "success");
      closeModal('wiringModal');
  }
  
  
  
  
  // ── API helper ───────────────────────────────────────────────────────
  async function api(payload, method) {
    method = method || 'POST';
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    let url = '/wtf_api.php';
    if (method === 'GET') {
      const qs = new URLSearchParams(payload).toString();
      url += '?' + qs;
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

  // ── Init / load ──────────────────────────────────────────────────────
  async function init() {
    bindStaticEvents();
    bindPager('runsList', p => loadRuns(p));
    bindPager('workflowsList', p => loadWorkflows(p));
    await Promise.all([
        loadWrishSessions(), 
        loadSchedulerRefs(), 
        loadSqlishTemplates(),
        loadSplashTemplates(),
        loadWorkflows(), 
        loadRuns()
    ]);

    const theme = document.documentElement.getAttribute('data-theme');
    document.getElementById('themeBtn').querySelector('i').className = theme === 'light' ? 'bi bi-sun' : 'bi bi-moon-stars';

    checkHeartbeat();
    setInterval(checkHeartbeat, 10000);

    // If there's a running/paused run, auto-open it. Opening a run never
    // triggers advance_run — see openRun()/startOrStopDisplayPolling() below.
    const active = _runs.find(r => r.status === 'running' || r.status === 'paused');
    if (active) await openRun(active.id);
  }

  function bindStaticEvents() {
    document.querySelectorAll('.left-tab').forEach(btn => {
      btn.addEventListener('click', () => setLeftTab(btn.dataset.ltab));
    });

    const noteInput = document.getElementById('noteInput');
    noteInput.addEventListener('input', () => autoResizeTextarea(noteInput));
    noteInput.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); addNote(); }
    });

    document.querySelectorAll('.wf-modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', e => {
        if (e.target === overlay) {
          overlay.classList.remove('open');
          if (overlay.id === 'logsModal') stopLogIntervals();
        }
      });
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        closeFlyouts();
        document.querySelectorAll('.wf-modal-overlay.open').forEach(m => {
          m.classList.remove('open');
          if (m.id === 'logsModal') stopLogIntervals();
        });
      }
    });
  }

  async function loadWrishSessions() {
      try {
          const res = await fetch('/wtf_api.php?action=list_wrish_sessions');
          const data = await res.json();
          if (data.success) _wrishSessions = data.sessions || [];
      } catch (e) {}
  }

  async function loadSchedulerRefs() {
      try {
          const res = await fetch('/wtf_api.php?action=list_scheduler_refs');
          const data = await res.json();
          if (data.success) {
              _schedTasks = data.tasks || [];
              _schedWrappers = data.wrappers || [];
          }
      } catch (e) {}
  }

  async function loadSqlishTemplates() {
      try {
          const data = await api({action: 'list_sqlish_templates'}, 'GET');
          if (data.success) _sqlishTemplates = data.templates || [];
      } catch (e) {}
  }
  
  async function loadSplashTemplates() {
      try {
          const data = await api({action: 'list_splash_templates'}, 'GET');
          if (data.success) _splashTemplates = data.templates || [];
      } catch (e) {}
  }

  // ── Scheduler heartbeat indicator (header) ──────────────────────────
  async function checkHeartbeat() {
    try {
      const data = await api({ action: 'scheduler_heartbeat' }, 'GET');
      const dot = document.getElementById('heartbeatDot');
      if (!data.last_seen) { dot.style.background = 'var(--red)'; return; }
      const lastSeen = new Date(data.last_seen + ' Z');
      const serverNow = new Date(data.server_time + ' Z');
      const diffSec = (serverNow - lastSeen) / 1000;
      dot.style.background = diffSec <= 15 ? 'var(--green)' : 'var(--red)';
    } catch (e) {
      document.getElementById('heartbeatDot').style.background = 'var(--red)';
    }
  }

  // ── Workflows list / CRUD ────────────────────────────────────────────
  async function loadWorkflows(page) {
    page = page || _pg.workflowsList.page || 1;
    try {
      const data = await api({ action: 'list_workflows', page, limit: _pg.workflowsList.limit });
      _workflows = data.workflows || [];
      _pg.workflowsList = { page: data.page || page, limit: data.limit || _pg.workflowsList.limit, total_pages: data.total_pages || 1 };
      renderWorkflowsList();
    } catch (e) { toast('Error loading workflows: ' + e.message, 'error'); }
  }

  function renderWorkflowsList() {
    const list = document.getElementById('workflowsList');
    if (_workflows.length === 0) {
      list.innerHTML = `<div class="empty-state"><div class="empty-icon">⚙</div>No workflows yet</div>`;
      renderPager('workflowsList');
      return;
    }
    list.innerHTML = _workflows.map(w => `
      <div class="wf-item" onclick="WF.openStartRunModal(${w.id})">
        <div class="wf-item-body">
          <div class="wf-item-title">${escHtml(w.title)}</div>
          <div class="wf-item-meta">
            <span>${(w.steps || []).length} step${(w.steps||[]).length === 1 ? '' : 's'}</span>
            <span>·</span>
            <span style="text-decoration:underline;cursor:pointer;margin-left:auto;" onclick="event.stopPropagation();WF.openEditWorkflowModal(${w.id})">edit</span>
            <span style="text-decoration:underline;cursor:pointer;margin-left:8px;" onclick="event.stopPropagation();WF.cloneWorkflow(${w.id})">clone</span>
          </div>
        </div>
      </div>`).join('');
    renderPager('workflowsList');
  }

  async function cloneWorkflow(id) {
    if(!confirm('Clone this workflow?')) return;
    try {
      await api({ action: 'clone_workflow', id: parseInt(id) });
      toast('Workflow cloned', 'success');
      await loadWorkflows(_pg.workflowsList.page);
    } catch (e) { toast('Error: ' + e.message, 'error'); }
  }

  function openNewWorkflowModal() {
    document.getElementById('workflowModalTitle').textContent = 'New Workflow';
    document.getElementById('editWorkflowId').value = '';
    document.getElementById('editWorkflowTitle').value = '';
    document.getElementById('editWorkflowDescription').value = '';
    document.getElementById('btnDeleteWorkflow').style.display = 'none';
    document.getElementById('stepEditorList').innerHTML = '';
    addStepEditorRow();
    document.getElementById('workflowModal').classList.add('open');
  }

  function openEditWorkflowModal(id) {
    const w = _workflows.find(x => x.id === id);
    if (!w) return;
    document.getElementById('workflowModalTitle').textContent = 'Edit Workflow';
    document.getElementById('editWorkflowId').value = w.id;
    document.getElementById('editWorkflowTitle').value = w.title || '';
    document.getElementById('editWorkflowDescription').value = w.description || '';
    document.getElementById('btnDeleteWorkflow').style.display = 'inline-flex';

    const stepList = document.getElementById('stepEditorList');
    stepList.innerHTML = '';
    (w.steps || []).forEach(s => addStepEditorRow(s));
    if ((w.steps || []).length === 0) addStepEditorRow();

    document.getElementById('workflowModal').classList.add('open');
  }

  function addStepEditorRow(step) {
    step = step || { key: '', label: '', handler_key: 'manual', config: {} };
    
    // Auto-fill label if empty
    let currentLabel = step.label || '';
    if (!currentLabel) {
        currentLabel = getAutoLabel(step.handler_key);
    }
    const list = document.getElementById('stepEditorList');
    const idx = list.children.length;
    const row = document.createElement('div');
    row.className = 'step-editor-row';
    row.dataset.stepRow = '1';

    const handlerOptions = Object.keys(HANDLER_LABELS).map(k =>
      `<option value="${k}" ${step.handler_key === k ? 'selected' : ''}>${HANDLER_LABELS[k]}</option>`
    ).join('');

    let isWrish = step.handler_key === 'wrish_run';
    let isScheduler = step.handler_key === 'scheduler_task';
    let isSqlish = step.handler_key === 'sqlish_run';
    let isSplash = step.handler_key === 'splash_run';
    let isSubWf = step.handler_key === 'sub_workflow';
    let isContinuity = step.handler_key === 'continuity_ingest';
    let configJsonStr = Object.keys(step.config||{}).length ? JSON.stringify(step.config, null, 2) : '';

    row.innerHTML = `
      <div class="step-editor-head">
        <span class="step-editor-num">Step ${idx + 1}</span>
        <div style="display:flex; gap:4px; align-items:center;">
          <button type="button" class="step-move-btn" onclick="WF.moveStepEditorRowUp(this)" title="Move Step Up"><i class="bi bi-arrow-up"></i></button>
          <button type="button" class="step-move-btn" onclick="WF.moveStepEditorRowDown(this)" title="Move Step Down"><i class="bi bi-arrow-down"></i></button>
          <button type="button" class="step-remove-btn" onclick="WF.removeStepEditorRow(this)" title="Remove Step"><i class="bi bi-x"></i></button>
        </div>
      </div>
      <div class="step-editor-grid">
        <div class="ctx-group" style="margin-bottom:0;">
          <label class="ctx-label">Label</label>
          <input class="ctx-input step-field-label" type="text" value="${escAttr(currentLabel)}" placeholder="e.g. Review draft">
        </div>
        <div class="ctx-group" style="margin-bottom:0;">
          <label class="ctx-label">Handler</label>
          <select class="ctx-select step-field-handler">${handlerOptions}</select>
        </div>
      </div>
      <div class="ctx-group" style="margin-bottom:0;">
        <label class="ctx-label">Key (optional, stable identifier)</label>
        <input class="ctx-input step-field-key" type="text" value="${escAttr(step.key || '')}" placeholder="e.g. review_draft">
      </div>
      
      <div class="wrish-config-area" style="display: ${isWrish ? 'block' : 'none'}; padding: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); margin-top: 8px;">
          <div class="ctx-group">
            <label class="ctx-label">Wrish Session</label>
            <div style="display:flex; gap:8px;">
                <select class="ctx-select wrish-session-select">
                    <option value="">-- Create New Session --</option>
                    ${_wrishSessions.map(s => `<option value="${s.session_id}">${escHtml(s.title || s.session_id)}</option>`).join('')}
                </select>
                <button type="button" class="btn-forge-secondary" style="padding: 4px 10px;" onclick="WF.generateWrishSession(this)">New UUID</button>
            </div>
            <input type="text" class="ctx-input wrish-session-id" placeholder="Session ID" style="margin-top:6px;" value="${escAttr((step.config||{}).wrish_session_id || '')}">
          </div>
          <div class="ctx-group">
            <label class="ctx-label">Initial Message</label>
            <textarea class="ctx-textarea wrish-initial-message" placeholder="Optional initial prompt...">${escHtml((step.config||{}).initial_message || '')}</textarea>
          </div>
          
          
          
          <div class="ctx-group">
            <label class="ctx-label">Max Steps</label>
            <input type="number" class="ctx-input wrish-max-steps" placeholder="100" value="${(step.config||{}).max_steps || ''}">
          </div>
          <div class="ctx-group">
            <label class="ctx-label">Source Text Context Key (Optional)</label>
            <input type="text" class="ctx-input wrish-source-key" placeholder="e.g. worldbuilding_lore" value="${escAttr((step.config||{}).source_text_context_key || '')}">
            <div class="hint">Passes output from a prior step into this Wrish session's context.</div>
          </div>
          <div class="ctx-group" style="margin-bottom:0;">
            <label class="ctx-label">Writeback Context Key (Optional)</label>
            <input type="text" class="ctx-input wrish-writeback-key" placeholder="e.g. wrish_plot_output" value="${escAttr((step.config||{}).writeback_context_key || '')}">
            <div class="hint">Saves the compiled plot back to WTF context when run finishes.</div>
          </div>
          
          
      </div>

      <div class="scheduler-config-area" style="display: ${isScheduler ? 'block' : 'none'}; padding: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); margin-top: 8px;">
          <div class="ctx-group">
              <label class="ctx-label">Load Preset (Optional)</label>
              <select class="ctx-select sched-preset-select">
                  <option value="">-- Select Preset --</option>
                  ${_schedWrappers.map(w => `<option value="${w.id}">${escHtml(w.name)}</option>`).join('')}
              </select>
          </div>
          <div class="ctx-group">
              <label class="ctx-label">Target Task</label>
              <select class="ctx-select sched-task-select">
                  <option value="">-- Select Task --</option>
                  ${_schedTasks.map(t => `<option value="${t.id}" ${step.config.scheduled_task_id == t.id ? 'selected' : ''}>${escHtml(t.name)}</option>`).join('')}
              </select>
          </div>

          <div class="ctx-group">
              <label class="ctx-label">Arguments Override</label>
              <input type="text" class="ctx-input sched-args" placeholder="e.g. --ids={{sqlish_out.ids}}" value="${escAttr(step.config.args !== undefined ? step.config.args : '')}">
              <div class="hint">Use <code>{{context_key.subkey}}</code> to dynamically inject outputs from prior steps! Arrays are converted to comma-separated strings automatically.</div>
          </div>
          <div class="ctx-group" style="margin-bottom:0;">
              <label class="ctx-label">Wait Mode</label>
              <select class="ctx-select sched-wait-mode">
                  <option value="trigger_new" ${(step.config||{}).wait_mode !== 'watch_latest' ? 'selected' : ''}>Trigger New & Wait</option>
                  <option value="watch_latest" ${(step.config||{}).wait_mode === 'watch_latest' ? 'selected' : ''}>Watch Latest Run (Don't Trigger)</option>
              </select>
          </div>
      </div>

      <div class="sqlish-config-area" style="display: ${isSqlish ? 'block' : 'none'}; padding: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); margin-top: 8px;">
          <div class="ctx-group">
              <label class="ctx-label">Target Template</label>
              <select class="ctx-select sqlish-template-select">
                  <option value="">-- Select Sqlish Template --</option>
                  ${_sqlishTemplates.map(t => `<option value="${t.id}" ${step.config.template_id == t.id ? 'selected' : ''}>${escHtml(t.name)} (${t.operation_type})</option>`).join('')}
              </select>
          </div>
          <div class="ctx-group" style="margin-bottom:0;">
              <label class="ctx-label">Source Text Context Key (Optional)</label>
              <input type="text" class="ctx-input sqlish-source-key" placeholder="e.g. wrish_plot_output" value="${escAttr((step.config||{}).source_text_context_key || '')}">
              <div class="hint">The WTF context key holding the prose for the AI to parse. Leave blank if the template requires no parsing text.</div>
          </div>
      </div>
      
      <div class="splash-config-area" style="display: ${isSplash ? 'block' : 'none'}; padding: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); margin-top: 8px;">
          <div class="ctx-group">
              <label class="ctx-label">Splash Template</label>
              <select class="ctx-select splash-template-select">
                  <option value="">-- Select Splash Template --</option>
                  ${_splashTemplates.map(t => `<option value="${t.id}" ${step.config.template_id == t.id ? 'selected' : ''}>${escHtml(t.name)}</option>`).join('')}
              </select>
          </div>
          <div class="ctx-group">
              <label class="ctx-label">Source Text Context Key (Optional)</label>
              <input type="text" class="ctx-input splash-source-key" placeholder="e.g. wrish_plot_output" value="${escAttr((step.config||{}).source_text_context_key || '')}">
              <div class="hint">The WTF context key holding the prose to feed into the Writers' Room.</div>
          </div>
          <div class="ctx-group" style="margin-bottom:0;">
              <label class="ctx-label">Writeback Context Key (Optional)</label>
              <input type="text" class="ctx-input splash-writeback-key" placeholder="e.g. splash_script_output" value="${escAttr((step.config||{}).writeback_context_key || '')}">
              <div class="hint">Saves the final script back to WTF context for downstream steps (e.g. Sqlish).</div>
          </div>
      </div>
      
      <div class="subwf-config-area" style="display: ${isSubWf ? 'block' : 'none'}; padding: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); margin-top: 8px;">
          <div class="ctx-group">
              <label class="ctx-label">Target Workflow</label>
              <select class="ctx-select subwf-target-select">
                  <option value="">-- Select Workflow to Run --</option>
                  ${_workflows.map(w => `<option value="${w.id}" ${step.config.workflow_id == w.id ? 'selected' : ''}>${escHtml(w.title)}</option>`).join('')}
              </select>
          </div>
          <div class="ctx-group">
              <label class="ctx-label">Input Mapping (JSON)</label>
              <textarea class="ctx-textarea subwf-input-mapping" placeholder='{"child_context_key": "parent_context_key"}'>${escHtml(step.config.input_mapping ? JSON.stringify(step.config.input_mapping) : '')}</textarea>
              <div class="hint">Injects variables from this workflow into the child's starting context.</div>
          </div>
          <div class="ctx-group" style="margin-bottom:0;">
              <label class="ctx-label">Output Mapping (JSON)</label>
              <textarea class="ctx-textarea subwf-output-mapping" placeholder='{"parent_context_key": "child_context_key"}'>${escHtml(step.config.output_mapping ? JSON.stringify(step.config.output_mapping) : '')}</textarea>
              <div class="hint">Extracts variables from the child's final context back into this workflow.</div>
          </div>
      </div>
      
<div class="continuity-config-area" style="display: ${isContinuity ? 'block' : 'none'}; padding: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); margin-top: 8px;">
          <div class="ctx-group" style="margin-bottom:0;">
              <label class="ctx-label">Source Text Context Key (Required)</label>
              <input type="text" class="ctx-input continuity-source-key" placeholder="e.g. splash_script_output" value="${escAttr((step.config||{}).source_text_context_key || '')}">
              <div class="hint">The WTF context key holding the JSON payload containing 'continuity_tags_new' and/or 'sketches'.</div>
          </div>
          <div class="ctx-group" style="margin-bottom:0; margin-top:12px;">
              <label class="ctx-label">Writeback Context Key (Optional)</label>
              <input type="text" class="ctx-input continuity-writeback-key" placeholder="e.g. ingest_result" value="${escAttr((step.config||{}).writeback_context_key || '')}">
              <div class="hint">Saves <code>{min_id, max_id, count, ids, has_new_tags, processed_tags}</code> back to WTF context for downstream steps.</div>
          </div>
      </div>
      
      <div class="json-config-area" style="display: ${(!isWrish && !isScheduler && !isSqlish && !isSplash && !isSubWf && !isContinuity) ? 'block' : 'none'}; margin-top:8px;">
        <label class="ctx-label">Config (optional JSON — e.g. {"prompt": "..."})</label>
        <textarea class="ctx-textarea step-field-config" style="min-height:44px;" placeholder="${escAttr(HANDLER_CONFIG_HINTS[step.handler_key] || '')}">${escHtml(configJsonStr)}</textarea>
      </div>
    `;
    list.appendChild(row);

    const handlerSelect = row.querySelector('.step-field-handler');
    const jsonArea = row.querySelector('.json-config-area');
    const wrishArea = row.querySelector('.wrish-config-area');
    const schedArea = row.querySelector('.scheduler-config-area');
    const sqlishArea = row.querySelector('.sqlish-config-area');
    const splashArea = row.querySelector('.splash-config-area');
    const subwfArea = row.querySelector('.subwf-config-area');
    const continuityArea = row.querySelector('.continuity-config-area');
    const continuitySrcKey = row.querySelector('.continuity-source-key');
    const continuityWritebackKey = row.querySelector('.continuity-writeback-key');
    const configField = row.querySelector('.step-field-config');
    
    // Wrish
    const wrishSessId = row.querySelector('.wrish-session-id');
    const wrishSessSel = row.querySelector('.wrish-session-select');
    const wrishInitMsg = row.querySelector('.wrish-initial-message');
    const wrishMaxSteps = row.querySelector('.wrish-max-steps');
    const wrishSrcKey = row.querySelector('.wrish-source-key');
    const wrishWritebackKey = row.querySelector('.wrish-writeback-key');
    
    // Scheduler
    const schedPreset = row.querySelector('.sched-preset-select');
    const schedTask = row.querySelector('.sched-task-select');
    const schedArgs = row.querySelector('.sched-args');
    const schedWait = row.querySelector('.sched-wait-mode');

    // Sqlish
    const sqlishTpl = row.querySelector('.sqlish-template-select');
    const sqlishSrcKey = row.querySelector('.sqlish-source-key');
    
    // Splash
    const splashTpl = row.querySelector('.splash-template-select');
    const splashSrcKey = row.querySelector('.splash-source-key');
    const splashWritebackKey = row.querySelector('.splash-writeback-key');
    
    // Sub Workflow
    const subwfTarget = row.querySelector('.subwf-target-select');
    const subwfInMap = row.querySelector('.subwf-input-mapping');
    const subwfOutMap = row.querySelector('.subwf-output-mapping');

    handlerSelect.addEventListener('change', () => {
        
        
       // Auto-update the label if it's empty OR if it looks like an auto-generated one 
        // (ends with a time in parentheses). Leaves custom human-typed labels alone!
        const labelInput = row.querySelector('.step-field-label');
        if (!labelInput.value.trim() || /Step \(\d{2}:\d{2}:\d{2}\)$/.test(labelInput.value.trim())) {
            labelInput.value = getAutoLabel(handlerSelect.value);
        }
        
        
        
        wrishArea.style.display = 'none';
        schedArea.style.display = 'none';
        sqlishArea.style.display = 'none';
        splashArea.style.display = 'none';
        subwfArea.style.display = 'none';
        continuityArea.style.display = 'none'; 
        jsonArea.style.display = 'none';

        if (handlerSelect.value === 'wrish_run') {
            wrishArea.style.display = 'block';
            syncWrishToJson();
        } else if (handlerSelect.value === 'scheduler_task') {
            schedArea.style.display = 'block';
            syncSchedToJson();
        } else if (handlerSelect.value === 'sqlish_run') {
            sqlishArea.style.display = 'block';
            syncSqlishToJson();
        } else if (handlerSelect.value === 'splash_run') {
            splashArea.style.display = 'block';
            syncSplashToJson();
        } else if (handlerSelect.value === 'sub_workflow') {
            subwfArea.style.display = 'block';
            syncSubWfToJson();
        } else if (handlerSelect.value === 'continuity_ingest') {
            continuityArea.style.display = 'block';
            syncContinuityToJson();
        } else {
            jsonArea.style.display = 'block';
            configField.placeholder = HANDLER_CONFIG_HINTS[handlerSelect.value] || '';
        }
    });

        function syncWrishToJson() {
        if (handlerSelect.value !== 'wrish_run') return;
        const cfg = {
            wrish_session_id: wrishSessId.value.trim(),
            wrish_user_id: window.WTF_USER_ID,
            initial_message: wrishInitMsg.value.trim()
        };
        if (wrishMaxSteps.value) cfg.max_steps = parseInt(wrishMaxSteps.value, 10);
        if (wrishSrcKey.value.trim() !== '') cfg.source_text_context_key = wrishSrcKey.value.trim();
        if (wrishWritebackKey.value.trim() !== '') cfg.writeback_context_key = wrishWritebackKey.value.trim();
        configField.value = JSON.stringify(cfg, null, 2);
    }

    

    function syncSchedToJson() {
        if (handlerSelect.value !== 'scheduler_task') return;
        const cfg = {
            scheduled_task_id: parseInt(schedTask.value, 10) || 0,
            wait_mode: schedWait.value
        };
        // Allow empty string to override task args to blank
        if (schedArgs.value !== '') {
            cfg.args = schedArgs.value.trim();
        }
        configField.value = JSON.stringify(cfg, null, 2);
    }

    function syncSqlishToJson() {
        if (handlerSelect.value !== 'sqlish_run') return;
        const cfg = {
            template_id: parseInt(sqlishTpl.value, 10) || 0,
        };
        if (sqlishSrcKey.value.trim() !== '') {
            cfg.source_text_context_key = sqlishSrcKey.value.trim();
        }
        configField.value = JSON.stringify(cfg, null, 2);
    }
    
    function syncSplashToJson() {
        if (handlerSelect.value !== 'splash_run') return;
        const cfg = {
            template_id: parseInt(splashTpl.value, 10) || 0,
        };
        if (splashSrcKey.value.trim() !== '') {
            cfg.source_text_context_key = splashSrcKey.value.trim();
        }
        if (splashWritebackKey.value.trim() !== '') {
            cfg.writeback_context_key = splashWritebackKey.value.trim();
        }
        configField.value = JSON.stringify(cfg, null, 2);
    }
    
    
   function syncContinuityToJson() {
        if (handlerSelect.value !== 'continuity_ingest') return;
        const cfg = {};
        if (continuitySrcKey.value.trim() !== '') {
            cfg.source_text_context_key = continuitySrcKey.value.trim();
        }
        if (continuityWritebackKey.value.trim() !== '') {
            cfg.writeback_context_key = continuityWritebackKey.value.trim();
        }
        configField.value = JSON.stringify(cfg, null, 2);
    }

    continuitySrcKey.addEventListener('input', syncContinuityToJson);
    continuityWritebackKey.addEventListener('input', syncContinuityToJson);
    
    continuitySrcKey.addEventListener('input', syncContinuityToJson);
    

    function syncSubWfToJson() {
        if (handlerSelect.value !== 'sub_workflow') return;
        const cfg = { workflow_id: parseInt(subwfTarget.value, 10) || 0 };
        try { if (subwfInMap.value.trim() !== '') cfg.input_mapping = JSON.parse(subwfInMap.value.trim()); } catch(e){}
        try { if (subwfOutMap.value.trim() !== '') cfg.output_mapping = JSON.parse(subwfOutMap.value.trim()); } catch(e){}
        configField.value = JSON.stringify(cfg, null, 2);
    }

    wrishSessId.addEventListener('input', syncWrishToJson);
    wrishInitMsg.addEventListener('input', syncWrishToJson);
    wrishMaxSteps.addEventListener('input', syncWrishToJson);
    wrishSrcKey.addEventListener('input', syncWrishToJson);
    wrishWritebackKey.addEventListener('input', syncWrishToJson);
    
    
    
    wrishSessSel.addEventListener('change', () => {
        if (wrishSessSel.value) {
            wrishSessId.value = wrishSessSel.value;
            syncWrishToJson();
        }
    });

    schedTask.addEventListener('change', syncSchedToJson);
    schedArgs.addEventListener('input', syncSchedToJson);
    schedWait.addEventListener('change', syncSchedToJson);

    schedPreset.addEventListener('change', () => {
        const wId = parseInt(schedPreset.value, 10);
        const w = _schedWrappers.find(x => x.id === wId);
        if (w) {
            schedTask.value = w.task_id;
            schedArgs.value = w.fixed_args || '';
            syncSchedToJson();
        }
    });

    sqlishTpl.addEventListener('change', syncSqlishToJson);
    sqlishSrcKey.addEventListener('input', syncSqlishToJson);
    
    splashTpl.addEventListener('change', syncSplashToJson);
    splashSrcKey.addEventListener('input', syncSplashToJson);
    splashWritebackKey.addEventListener('input', syncSplashToJson);
    
    subwfTarget.addEventListener('change', syncSubWfToJson);
    subwfInMap.addEventListener('input', syncSubWfToJson);
    subwfOutMap.addEventListener('input', syncSubWfToJson);

    renumberStepEditorRows();
  }

  function removeStepEditorRow(btn) {
    const row = btn.closest('.step-editor-row');
    row.remove();
    renumberStepEditorRows();
  }

  function moveStepEditorRowUp(btn) {
    const row = btn.closest('.step-editor-row');
    const prev = row.previousElementSibling;
    if (prev && prev.classList.contains('step-editor-row')) {
      row.parentNode.insertBefore(row, prev);
      renumberStepEditorRows();
    }
  }

  function moveStepEditorRowDown(btn) {
    const row = btn.closest('.step-editor-row');
    const next = row.nextElementSibling;
    if (next && next.classList.contains('step-editor-row')) {
      row.parentNode.insertBefore(next, row);
      renumberStepEditorRows();
    }
  }

  function renumberStepEditorRows() {
    document.querySelectorAll('#stepEditorList .step-editor-row').forEach((row, i) => {
      row.querySelector('.step-editor-num').textContent = 'Step ' + (i + 1);
    });
  }

  async function saveWorkflow() {
    const id = document.getElementById('editWorkflowId').value;
    const title = document.getElementById('editWorkflowTitle').value.trim();
    if (!title) { toast('Title is required', 'error'); return; }

    const rows = document.querySelectorAll('#stepEditorList .step-editor-row');
    if (rows.length === 0) { toast('Add at least one step', 'error'); return; }

    const steps = [];
    for (const row of rows) {
      const label = row.querySelector('.step-field-label').value.trim();
      const handler_key = row.querySelector('.step-field-handler').value;
      const key = row.querySelector('.step-field-key').value.trim();
      const configRaw = row.querySelector('.step-field-config').value.trim();
      let config = {};
      if (configRaw) {
        try { config = JSON.parse(configRaw); }
        catch (e) { toast('Invalid JSON in step config: ' + e.message, 'error'); return; }
      }
      if (!label) { toast('Every step needs a label', 'error'); return; }
      steps.push({ key: key || null, label, handler_key, config });
    }

    const workflow = {
      id: id ? parseInt(id) : null,
      title,
      description: document.getElementById('editWorkflowDescription').value.trim(),
      steps
    };

    try {
      const data = await api({ action: 'save_workflow', workflow });
      closeModal('workflowModal');
      toast('Workflow saved', 'success');
      await loadWorkflows(_pg.workflowsList.page);
    } catch (e) {
      toast('Error: ' + e.message, 'error');
    }
  }

  async function deleteWorkflow() {
    const id = document.getElementById('editWorkflowId').value;
    if (!id) return;
    if (!confirm('Delete this workflow? Existing runs from it are kept for history.')) return;
    try {
      await api({ action: 'delete_workflow', id: parseInt(id) });
      closeModal('workflowModal');
      toast('Workflow deleted', 'info');
      await loadWorkflows(1);
    } catch (e) { toast('Error: ' + e.message, 'error'); }
  }

  // ── Start run ────────────────────────────────────────────────────────
  function openStartRunModal(workflowId) {
    document.getElementById('startRunWorkflowId').value = workflowId;
    document.getElementById('startRunTitle').value = '';
    document.getElementById('startRunContext').value = '';
    // Default to CHECKED — most workflows are expected to be scheduler-driven;
    // uncheck explicitly for a manual, human-gated run.
    document.getElementById('startRunAutoAdvance').checked = true;
    document.getElementById('startRunModal').classList.add('open');
  }

  async function confirmStartRun() {
    const workflowId = parseInt(document.getElementById('startRunWorkflowId').value);
    const title = document.getElementById('startRunTitle').value.trim();
    const contextRaw = document.getElementById('startRunContext').value.trim();
    const autoAdvance = document.getElementById('startRunAutoAdvance').checked;
    
    let context = {};
    if (contextRaw) {
      try { context = JSON.parse(contextRaw); }
      catch (e) { toast('Invalid JSON in initial context: ' + e.message, 'error'); return; }
    }

    try {
      const data = await api({ action: 'start_run', workflow_id: workflowId, title: title || null, context, auto_advance: autoAdvance });
      closeModal('startRunModal');
      toast('Run started', 'success');
      await loadRuns(1);
      await openRun(data.run.id);
      setLeftTab('runs');
    } catch (e) {
      toast('Error: ' + e.message, 'error');
    }
  }

  // ── Runs list ────────────────────────────────────────────────────────
  async function loadRuns(page) {
    page = page || _pg.runsList.page || 1;
    try {
      const data = await api({ action: 'list_runs', page, limit: _pg.runsList.limit });
      _runs = data.runs || [];
      _pg.runsList = { page: data.page || page, limit: data.limit || _pg.runsList.limit, total_pages: data.total_pages || 1 };
      renderRunsList();
    } catch (e) { toast('Error loading runs: ' + e.message, 'error'); }
  }

  function renderRunsList() {
    const list = document.getElementById('runsList');
    if (_runs.length === 0) {
      list.innerHTML = `<div class="empty-state"><div class="empty-icon">⚙</div>No runs yet</div>`;
      renderPager('runsList');
      return;
    }
    list.innerHTML = _runs.map(r => `
      <div class="wf-item ${_currentRun && _currentRun.id === r.id ? 'active-run' : ''}" onclick="WF.openRun(${r.id})">
        <div class="wf-item-body">
          <div class="wf-item-title">${escHtml(r.title || r.workflow_title)}</div>
          <div class="wf-item-meta">
            <span class="status-chip ${r.status}">${r.status}</span>
            <span>${escHtml(r.workflow_title)}</span>
            ${r.auto_advance == 1 ? '<span title="Scheduler-driven"><i class="bi bi-robot"></i></span>' : '<span title="Manual"><i class="bi bi-hand-index"></i></span>'}
            <span style="text-decoration:underline;cursor:pointer;margin-left:auto;" onclick="event.stopPropagation();WF.exportRun(${r.id})">export</span>
          </div>
        </div>
      </div>`).join('');
    renderPager('runsList');
  }

  async function exportRun(id) {
      try {
          const data = await api({ action: 'export_run', id: parseInt(id) }, 'GET');
          const blob = new Blob([JSON.stringify(data.export, null, 2)], { type: 'application/json' });
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `wtf_run_${id}_export.json`;
          a.click();
          URL.revokeObjectURL(url);
          toast('Run exported', 'success');
      } catch (e) { toast('Error: ' + e.message, 'error'); }
  }

  // ── Open / render a run ──────────────────────────────────────────────
  // NOTE: opening a run NEVER calls advance_run. It only reads current state
  // (get_run / get_run_events) and, for auto-advance runs still 'running',
  // starts a READ-ONLY display-refresh timer (see startOrStopDisplayPolling).
  // For manual runs, no timer is started at all — the human is the only
  // thing that can move a manual run forward, via the "Proceed" button,
  // which calls advance_run exactly once per click.
  async function openRun(runId) {
    try {
      const [runData, eventsData] = await Promise.all([
        api({ action: 'get_run', id: runId }, 'GET'),
        api({ action: 'get_run_events', id: runId }, 'GET')
      ]);
      _currentRun = runData.run;
      _events = eventsData.events || [];

      renderCLI();
      renderHeaderPhase();
      renderRunsList();
      closeFlyouts();
      updateInputBarState();

      startOrStopDisplayPolling();
    } catch (e) {
      toast('Error loading run: ' + e.message, 'error');
    }
  }

  function startOrStopDisplayPolling() {
    if (_pollTimer) { clearInterval(_pollTimer); _pollTimer = null; }
    // Only ever poll for auto-advance runs that are still running — and only
    // to refresh the read-only display. This never calls advance_run. The
    // scheduler heartbeat (backend) is exclusively responsible for actually
    // advancing an auto-advance run's steps.
    if (_currentRun && _currentRun.status === 'running' && _currentRun.auto_advance) {
      _pollTimer = setInterval(async () => {
        if (!_currentRun || _isBusy) return;
        try {
          const [runData, eventsData] = await Promise.all([
            api({ action: 'get_run', id: _currentRun.id }, 'GET'),
            api({ action: 'get_run_events', id: _currentRun.id }, 'GET')
          ]);
          _currentRun = runData.run;
          _events = eventsData.events || [];
          renderCLI();
          renderHeaderPhase();
          if (!(_currentRun.status === 'running' && _currentRun.auto_advance)) startOrStopDisplayPolling();
        } catch (e) { /* silent — transient poll errors shouldn't spam toasts */ }
      }, 2000);
    }
  }

  // ── CLI View Rendering ───────────────────────────────────────────────
  function renderCLI() {
    const feed = document.getElementById('feed');
    if (!_currentRun) {
      feed.innerHTML = `
        <div class="wf-welcome" id="welcomeState">
          <div class="wf-welcome-sigil">⚙</div>
          <div class="wf-welcome-title">WTF — Work The Flow</div>
          <div class="wf-welcome-body">
            A deterministic orchestration control panel. Define a workflow as an ordered
            list of steps, start a run, and track/execute each step here — with full
            human gating, retries, and an audit trail.
          </div>
          <div class="wf-welcome-actions">
            <button class="btn-forge-primary" onclick="WF.openNewWorkflowModal()"><i class="bi bi-plus-lg"></i> New Workflow</button>
          </div>
        </div>
      `;
      return;
    }

    let html = `<div class="cli-view">`;
    html += `
      <div class="cli-run-header">
        <div style="font-size:1.2rem; color:var(--teal); font-weight:bold; font-family:var(--sans); margin-bottom: 6px;">${escHtml(_currentRun.title)}</div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <span class="cli-status ${_currentRun.status}">${_currentRun.status}</span>
            ${_currentRun.auto_advance
              ? `<span class="cli-status active" style="border-color:var(--amber); color:var(--amber); background:var(--amber-dim);"><i class="bi bi-robot"></i> AUTO-ADVANCE (scheduler-driven)</span>`
              : `<span class="cli-status pending" style="border-color:var(--text-dim);"><i class="bi bi-hand-index"></i> MANUAL (human-gated)</span>`}
        </div>
        <div class="cli-actions" style="margin-top: 12px;">
    `;

    const s = _currentRun.status;
    if ((s === 'running' || s === 'pending') && !_currentRun.auto_advance) {
      // "Proceed" is only offered for manual runs — auto-advance runs are
      // driven exclusively by the scheduler heartbeat, so there is nothing
      // for a human "Proceed" click to usefully do on those (it would just
      // race the next heartbeat tick).
      html += `<button class="btn-forge-secondary" onclick="WF.advanceRun()"><i class="bi bi-play-fill"></i> Proceed</button>`;
    }
    if (s === 'running' || s === 'pending') {
      html += `<button class="btn-forge-secondary warn" onclick="WF.pauseRun()"><i class="bi bi-pause-fill"></i> Pause</button>`;
    } else if (s === 'paused') {
      html += `<button class="btn-forge-secondary" onclick="WF.resumeRun()"><i class="bi bi-play-fill"></i> Resume</button>`;
    }
    if (s === 'running' || s === 'pending' || s === 'paused') {
      html += `<button class="btn-forge-secondary danger" onclick="WF.cancelRun()"><i class="bi bi-x-circle"></i> Cancel Run</button>`;
    }
    html += `</div></div>`;

    // Run-level events
    const runEvents = _events.filter(e => !e.step_id);
    if (runEvents.length > 0) {
      html += `
      <div class="cli-step status-done" style="border-color:var(--border-glow);">
        <div class="cli-header">
          <span class="cli-title">Run Log</span>
        </div>
        <div class="cli-events">
      `;
      html += runEvents.map(ev => `
        <div class="cli-event type-${ev.event_type}">
          <span class="ts">${formatTime(ev.ts)}</span>
          <span class="msg">[${ev.event_type.replace(/_/g,' ')}] ${escHtml(ev.message)}</span>
        </div>
      `).join('');
      html += `</div></div>`;
    }

    // Steps
    _currentRun.steps.forEach((step, i) => {
        const stepEvents = _events.filter(e => e.step_id === step.id);
        const isActive = step.status === 'active';
        const isDone = step.status === 'done';
        const isFailed = step.status === 'failed';
        
        let statusHtml = step.status;
        if (isActive) statusHtml = `<span class="spin-sm"></span> ACTIVE`;

        html += `
        <div class="cli-step status-${step.status}">
          <div class="cli-header">
            <span class="cli-num">[Step ${i+1}]</span>
            <span class="cli-title">${escHtml(step.label)}</span>
            <span class="cli-handler">(${escHtml(step.handler_key)})</span>
            <span class="cli-status ${step.status}">${statusHtml}</span>
          </div>
          <div class="cli-events">
        `;

        html += stepEvents.map(ev => `
          <div class="cli-event type-${ev.event_type}">
            <span class="ts">${formatTime(ev.ts)}</span>
            <span class="msg">${escHtml(ev.message)}</span>
          </div>
        `).join('');

        if (step.error_text) {
             html += `<div class="cli-result" style="color:var(--red); border-color:var(--red-dim);"><i class="bi bi-exclamation-triangle"></i> ${escHtml(step.error_text)}</div>`;
        } else if (step.result_summary) {
             html += `<div class="cli-result">${escHtml(step.result_summary)}</div>`;
        }

        html += `</div>`; // end events

        // Actions
        if (isActive) {
            html += `<div class="cli-actions">`;
            if (step.handler_key === 'manual') {
                html += `
                    <button class="btn-forge-primary" onclick="WF.completeStep(${step.id})"><i class="bi bi-check-lg"></i> Mark Done</button>
                    <button class="btn-forge-secondary danger" onclick="WF.skipStep(${step.id})"><i class="bi bi-skip-forward"></i> Skip</button>
                `;
            } else {
                if (!_currentRun.auto_advance) {
                    html += `<button class="btn-forge-primary" onclick="WF.advanceRun()"><i class="bi bi-play-fill"></i> Run Step</button>`;
                }
                html += `<button class="btn-forge-secondary danger" onclick="WF.skipStep(${step.id})"><i class="bi bi-skip-forward"></i> Skip</button>`;
            }
            html += `</div>`;
        } else if (isFailed) {
            html += `<div class="cli-actions">
                <button class="btn-forge-primary" onclick="WF.retryStep(${step.id})"><i class="bi bi-arrow-repeat"></i> Retry</button>
                <button class="btn-forge-secondary danger" onclick="WF.skipStep(${step.id})"><i class="bi bi-skip-forward"></i> Skip</button>
            </div>`;
        }

        html += `</div>`; // end step
    });

    html += `</div>`; // end cli-view

    const isAtBottom = feed.scrollHeight - feed.scrollTop <= feed.clientHeight + 50;
    feed.innerHTML = html;
    if (isAtBottom || _currentRun.status === 'running') {
        feed.scrollTop = feed.scrollHeight;
    }
  }

  function updateInputBarState() {
    const noteInput = document.getElementById('noteInput');
    const btnAddNote = document.getElementById('btnAddNote');
    if (!_currentRun) {
      noteInput.disabled = true;
      btnAddNote.disabled = true;
    } else {
      noteInput.disabled = false;
      btnAddNote.disabled = false;
    }
  }

  function renderHeaderPhase() {
    const wrap = document.getElementById('headerPhaseWrap');
    const label = document.getElementById('headerPhase');
    if (!_currentRun) { wrap.style.display = 'none'; return; }

    wrap.style.display = 'flex';
    wrap.className = 'phase-indicator status-' + _currentRun.status;

    const activeStep = _currentRun.steps.find(s => s.status === 'active');
    label.textContent = activeStep ? activeStep.label : (_currentRun.status === 'done' ? 'Complete' : _currentRun.status);
  }

  // ── Step / run actions ───────────────────────────────────────────────
  async function withBusy(fn) {
    if (_isBusy) return;
    _isBusy = true;
    try { await fn(); }
    catch (e) { toast('Error: ' + e.message, 'error'); }
    finally { _isBusy = false; }
  }

  // advanceRun() is ONLY ever invoked by an explicit human click — the
  // "Proceed" / "Run Step" buttons, which are themselves only rendered for
  // manual (auto_advance=0) runs. See renderCLI() above. There is no timer
  // anywhere in this file that calls this function.
  function advanceRun() {
    withBusy(async () => {
      const data = await api({ action: 'advance_run', run_id: _currentRun.id });
      _currentRun = data.run;
      await refreshEventsAndRender();
    });
  }

  function completeStep(stepId) {
    withBusy(async () => {
      const note = prompt('Optional note for this step:', '') || '';
      const data = await api({ action: 'complete_step', run_id: _currentRun.id, step_id: stepId, note });
      _currentRun = data.run;
      await refreshEventsAndRender();
      toast('Step marked done', 'success');
    });
  }

  function skipStep(stepId) {
    withBusy(async () => {
      if (!confirm('Skip this step?')) return;
      const data = await api({ action: 'skip_step', run_id: _currentRun.id, step_id: stepId });
      _currentRun = data.run;
      await refreshEventsAndRender();
      toast('Step skipped', 'info');
    });
  }

  function retryStep(stepId) {
    withBusy(async () => {
      const data = await api({ action: 'retry_step', run_id: _currentRun.id, step_id: stepId });
      _currentRun = data.run;
      await refreshEventsAndRender();
    });
  }

  function pauseRun() {
    withBusy(async () => {
      const data = await api({ action: 'pause_run', run_id: _currentRun.id });
      _currentRun = data.run;
      await refreshEventsAndRender();
      toast('Run paused', 'info');
    });
  }

  function resumeRun() {
    withBusy(async () => {
      const data = await api({ action: 'resume_run', run_id: _currentRun.id });
      _currentRun = data.run;
      await refreshEventsAndRender();
      toast('Run resumed', 'success');
    });
  }

  function cancelRun() {
    withBusy(async () => {
      if (!confirm('Cancel this run? This cannot be undone.')) return;
      const data = await api({ action: 'cancel_run', run_id: _currentRun.id });
      _currentRun = data.run;
      await refreshEventsAndRender();
      toast('Run cancelled', 'info');
    });
  }

  async function addNote() {
    const input = document.getElementById('noteInput');
    const note = input.value.trim();
    if (!note || !_currentRun) return;
    input.value = '';
    autoResizeTextarea(input);
    try {
      await api({ action: 'add_note', run_id: _currentRun.id, note });
      await refreshEventsAndRender();
    } catch (e) { toast('Error: ' + e.message, 'error'); }
  }

  async function refreshEventsAndRender() {
    const eventsData = await api({ action: 'get_run_events', id: _currentRun.id }, 'GET');
    _events = eventsData.events || [];
    renderCLI();
    renderHeaderPhase();
    renderRunsList();
    updateInputBarState();
    await loadRuns(_pg.runsList.page);
    startOrStopDisplayPolling();
  }

  // ── Cloned Scheduler Forge: Logs modal ──────────────────────────────
  async function openLogsModal() {
    document.getElementById('logsModal').classList.add('open');
    await fetchLogFiles();
    await refreshLogContent(true);
    if (_logAutoRefresh) startLogIntervals();
  }

  function closeLogsModal() {
    document.getElementById('logsModal').classList.remove('open');
    stopLogIntervals();
  }

  function startLogIntervals() {
    if (!_logRefreshInterval) _logRefreshInterval = setInterval(refreshLogContent, 2000);
    if (!_logFileListInterval) _logFileListInterval = setInterval(fetchLogFiles, 10000);
  }

  function stopLogIntervals() {
    if (_logRefreshInterval) { clearInterval(_logRefreshInterval); _logRefreshInterval = null; }
    if (_logFileListInterval) { clearInterval(_logFileListInterval); _logFileListInterval = null; }
  }

  async function fetchLogFiles() {
    try {
      const data = await api({ action: 'list_log_files' }, 'GET');
      const files = data.files || [];
      const sel = document.getElementById('logFileSelect');
      if (files.length > 0) {
        const currentVal = sel.value;
        sel.innerHTML = files.map(f => `<option value="${escAttr(f)}">${escHtml(f)}</option>`).join('');
        if (currentVal && files.includes(currentVal)) {
          sel.value = currentVal;
        } else if (!_currentLogFile || !files.includes(_currentLogFile)) {
          _currentLogFile = files[0];
          sel.value = _currentLogFile;
        }
      } else {
        sel.innerHTML = '<option value="">No logs found</option>';
        _currentLogFile = '';
      }
    } catch (e) {}
  }

  async function refreshLogContent(forceScroll) {
    if (!_currentLogFile) return;
    try {
      const data = await api({ action: 'fetch_log_file', file: _currentLogFile }, 'GET');
      const box = document.getElementById('logContentBox');
      const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 20;
      box.textContent = data.content || '';
      if (_logAutoRefresh && (nearBottom || forceScroll)) {
        box.scrollTop = box.scrollHeight;
      }
    } catch (e) {}
  }

  function changeLogFile() {
    _currentLogFile = document.getElementById('logFileSelect').value;
    document.getElementById('logContentBox').textContent = 'Loading...';
    refreshLogContent(true);
  }

  function toggleLogRefresh() {
    _logAutoRefresh = !_logAutoRefresh;
    const btn = document.getElementById('btnToggleLogRefresh');
    if (_logAutoRefresh) {
      btn.innerHTML = '<i class="bi bi-pause-fill"></i>';
      startLogIntervals();
      refreshLogContent(true);
    } else {
      btn.innerHTML = '<i class="bi bi-play-fill"></i>';
      stopLogIntervals();
    }
  }
  
  // ── Global Queue Viewer (Iframe integration) ──

  function openQueueModal() {
    if (window.showIframeModal) {
      window.showIframeModal('/view_queue.php?embed=1', 'Loading Map Run Queue...');
    }
  }

  async function releaseLock(id, isGlobal = false) {
    if (!confirm('Force release this lock?')) return;
    const r = await api({ action: 'release_lock', lock_id: id });
    if (r.success) {
      toast('Lock released', 'success');
      openLocksModal();
    } else {
      toast('Release failed', 'error');
    }
  }

  async function cleanupLocks() {
    try {
      const data = await api({ action: 'cleanup_locks' });
      toast(`Cleaned up ${data.count} expired locks`, 'success');
      openLocksModal();
    } catch (e) { toast('Error: ' + e.message, 'error'); }
  }

  async function openLocksModal() {
    const data = await api({ action: 'get_global_locks' }, 'GET');
    const tbody = document.getElementById('locksTableBody');
    const locks = data.locks || [];
    if (locks.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-dim); padding:30px;">All clear. No active locks found.</td></tr>';
    } else {
      tbody.innerHTML = locks.map(l => `
        <tr>
          <td><strong>${escHtml(l.task_name || ('#' + l.task_id))}</strong><br><small>#${l.task_id}</small></td>
          <td><span style="font-family:var(--mono); font-size:0.7rem; background:var(--bg); padding:2px 4px; border-radius:3px;">${escHtml(l.lock_key)}</span></td>
          <td>${l.age_seconds}s</td>
          <td>${l.ttl_seconds > 0 ? l.ttl_seconds + 's' : '<span style="color:var(--red);">Expired</span>'}</td>
          <td>${l.pid || '-'}</td>
          <td><button class="btn-forge-danger" onclick="WF.releaseLock(${l.id})">Release</button></td>
        </tr>`).join('');
    }
    document.getElementById('locksModal').classList.add('open');
  }

  // ── Flyouts / tabs ───────────────────────────────────────────────────
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
  function toggleTheme() {
    const html = document.documentElement;
    const curr = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', curr);
    try { localStorage.setItem('spw_theme', curr); } catch(e) {}
    document.getElementById('themeBtn').querySelector('i').className = curr === 'dark' ? 'bi bi-moon-stars' : 'bi bi-sun';
  }

  function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    if (id === 'logsModal') stopLogIntervals();
  }

  function toast(msg, type, duration) {
    type = type || 'info';
    duration = duration || 2800;
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    const icons = { success: '✓', error: '✕', info: '⚙' };
    el.innerHTML = `<span>${icons[type]||'⚙'}</span> ${escHtml(msg)}`;
    el.onclick = () => { el.classList.add('out'); setTimeout(()=>el.remove(), 250); };
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => { el.classList.add('out'); setTimeout(()=>el.remove(), 250); }, duration);
  }

  function formatTime(ts) { return new Date(ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
  function escHtml(s) { if (s === null || s === undefined) return ''; const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }
  function escAttr(s) { return escHtml(s).replace(/"/g, '&quot;'); }
  function autoResizeTextarea(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 160) + 'px'; }

  function generateWrishSession(btn) {
      const row = btn.closest('.step-editor-row');
      const input = row.querySelector('.wrish-session-id');
      input.value = 'wrish-' + Math.random().toString(36).substring(2, 10) + '-' + Date.now();
      input.dispatchEvent(new Event('input'));
  }

  return {
    init,
    openNewWorkflowModal, openEditWorkflowModal, cloneWorkflow, addStepEditorRow, removeStepEditorRow,
    moveStepEditorRowUp, moveStepEditorRowDown,
    saveWorkflow, deleteWorkflow,
    openStartRunModal, confirmStartRun,
    openRun, exportRun, advanceRun, completeStep, skipStep, retryStep,
    pauseRun, resumeRun, cancelRun, addNote,
    toggleFlyout, closeFlyouts, toggleTheme, closeModal,
    generateWrishSession,
    openLogsModal, closeLogsModal, changeLogFile, toggleLogRefresh,
    openLocksModal, releaseLock, cleanupLocks, openWiringModal, handlePortTap, saveWiring
  };
})();

document.addEventListener('DOMContentLoaded', function() {
  if (typeof window.WF !== 'undefined' && window.WF.init) {
    window.WF.init();
  } else {
    console.error('WF engine failed to initialize.');
  }
});
</script>
<?php if (file_exists(__DIR__ . '/js/sage-home-button.js')): ?>
<!--
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
-->
<?php endif; ?>


<?php echo $eruda ?? ''; ?>


</body>
</html>







