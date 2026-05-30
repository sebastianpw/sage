<?php
// forge_tool.php
// ─────────────────────────────────────────────────────────────────────────────
// FORGE TOOL — Eruda-style floating dev console for SAGE AI
// Drop-in component: include anywhere with   require 'forge_tool.php'; 
// Structure mirrors Eruda: draggable FAB icon → bottom-sheet modal with tabs.
// Tabs: Clipboard | Shortcuts | Settings
// Settings tab: dynamically configure shortcut icons and add custom iframe tabs.
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/bootstrap.php';
global $pdo;

// ── SETTINGS PERSISTENCE (MariaDB server-side read, JS writes via API) ────────────
$forgeToolSettings = [];
try {
    $stmt = $pdo->query("SELECT settings_json FROM forge_tool_settings ORDER BY id DESC LIMIT 1");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $forgeToolSettings = json_decode($row['settings_json'], true) ?? [];
    }
} catch (\Throwable $e) {
    // Table might not exist yet, fail silently to defaults
}

// ── INLINE API HANDLER ────────────────────────────────────────────────────
if (isset($_GET['forge_tool_api'])) {
    // CRITICAL: Clear any HTML output from the parent page before sending JSON
    while (ob_get_level()) ob_end_clean();
    
    header('Content-Type: application/json');
    $action = $_GET['forge_tool_api'];
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($action === 'save_settings') {
        try {
            $json = json_encode($input);
            $stmt = $pdo->query("SELECT id FROM forge_tool_settings ORDER BY id DESC LIMIT 1");
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $update = $pdo->prepare("UPDATE forge_tool_settings SET settings_json = ? WHERE id = ?");
                $update->execute([$json, $row['id']]);
            } else {
                $insert = $pdo->prepare("INSERT INTO forge_tool_settings (settings_json) VALUES (?)");
                $insert->execute([$json]);
            }
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    if ($action === 'get_settings') {
        echo json_encode(['ok' => true, 'data' => $forgeToolSettings]);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}
?>
<?php /* ── CLIPBOARD API HANDLER — only runs when loaded directly ── */ ?>
<?php if (isset($_REQUEST['api_action'])): ?>
<?php /* Delegate to clipboard_manager.php if needed — handled by that file */ ?>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════════════════
     FORGE TOOL STYLES
══════════════════════════════════════════════════════════════════════════ -->
<style id="forge-tool-styles">
/* ── CSS VARIABLES ─────────────────────────────────────────────────── */
:root {
    --ft-bg:          #080c12;
    --ft-surface:     #0d1219;
    --ft-card:        #111820;
    --ft-border:      #1c2636;
    --ft-border-hi:   #2a3a52;
    --ft-text:        #c8d4e8;
    --ft-text-dim:    #4a5a70;
    --ft-text-hi:     #e8f2ff;
    --ft-amber:       #f5a623;
    --ft-amber-dim:   rgba(245,166,35,0.08);
    --ft-amber-glow:  rgba(245,166,35,0.35);
    --ft-teal:        #14b8a6;
    --ft-teal-dim:    rgba(20,184,166,0.1);
    --ft-purple:      #8b5cf6;
    --ft-purple-dim:  rgba(139,92,246,0.1);
    --ft-red:         #f05060;
    --ft-red-dim:     rgba(240,80,96,0.1);
    --ft-green:       #22d3a0;
    --ft-green-dim:   rgba(34,211,160,0.08);
    --ft-mono:        'Space Mono', 'DM Mono', 'Fira Mono', monospace;
    --ft-sans:        'Syne', 'DM Sans', system-ui, sans-serif;
    --ft-radius:      8px;
    --ft-radius-lg:   14px;
    --ft-shadow:      0 8px 40px rgba(0,0,0,0.7), 0 2px 8px rgba(0,0,0,0.4);
    --ft-panel-h:     72vh;
    --ft-fab-size:    48px;
    --ft-handle-h:    44px;
    --ft-tab-h:       44px;
    --ft-z:           2147483000; /* just below Eruda's z */
}

/* ── LIGHT THEME OVERRIDES ─────────────────────────────────────────── */
[data-theme="light"] {
    --ft-bg:          #f0f4fa;
    --ft-surface:     #e6eaf2;
    --ft-card:        #ffffff;
    --ft-border:      #c8d0de;
    --ft-border-hi:   #a0aec0;
    --ft-text:        #2d3748;
    --ft-text-dim:    #718096;
    --ft-text-hi:     #1a202c;
    --ft-amber:       #d97706;
    --ft-amber-dim:   rgba(217,119,6,0.08);
    --ft-amber-glow:  rgba(217,119,6,0.30);
    --ft-teal:        #0d9488;
    --ft-teal-dim:    rgba(13,148,136,0.10);
    --ft-purple:      #7c3aed;
    --ft-purple-dim:  rgba(124,58,237,0.10);
    --ft-red:         #dc2626;
    --ft-red-dim:     rgba(220,38,38,0.08);
    --ft-green:       #059669;
    --ft-green-dim:   rgba(5,150,105,0.08);
    --ft-shadow:      0 8px 40px rgba(0,0,0,0.15), 0 2px 8px rgba(0,0,0,0.08);
}

/* ── FAB BUTTON ────────────────────────────────────────────────────── */
#forge-tool-fab {
    position: fixed;
    width: var(--ft-fab-size);
    height: var(--ft-fab-size);
    border-radius: 50%;
    background: var(--ft-card);
    border: 1.5px solid var(--ft-border-hi);
    box-shadow: 0 4px 20px rgba(0,0,0,0.6), 0 0 0 0 var(--ft-amber-glow);
    cursor: grab;
    z-index: var(--ft-z);
    display: flex; align-items: center; justify-content: center;
    user-select: none;
    -webkit-user-select: none;
    touch-action: none;
    transition: box-shadow 0.2s, border-color 0.2s, transform 0.15s;
    bottom: 80px;
    right: 16px;
    /* Layered glow on border */
    --ft-pulse-anim: none;
    animation: var(--ft-pulse-anim);
}
#forge-tool-fab:active { cursor: grabbing; }
#forge-tool-fab:hover {
    border-color: var(--ft-amber);
    box-shadow: 0 4px 20px rgba(0,0,0,0.6), 0 0 16px var(--ft-amber-glow);
    transform: scale(1.06);
}
#forge-tool-fab.panel-open {
    border-color: var(--ft-amber);
    box-shadow: 0 4px 24px rgba(0,0,0,0.7), 0 0 20px var(--ft-amber-glow);
    animation: ftPulse 2.4s ease-in-out infinite;
}
.ft-fab-inner {
    width: 28px; height: 28px;
    position: relative;
    display: flex; align-items: center; justify-content: center;
}
.ft-fab-icon {
    font-size: 18px; line-height: 1;
    transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), opacity 0.2s;
    position: absolute;
}
.ft-fab-icon.closed { opacity: 1; transform: scale(1) rotate(0deg); }
.ft-fab-icon.opened { opacity: 0; transform: scale(0.4) rotate(180deg); }
#forge-tool-fab.panel-open .ft-fab-icon.closed { opacity: 0; transform: scale(0.4) rotate(-180deg); }
#forge-tool-fab.panel-open .ft-fab-icon.opened { opacity: 1; transform: scale(1) rotate(0deg); }
/* Notch badge */
.ft-fab-badge {
    position: absolute;
    top: -3px; right: -4px;
    min-width: 16px; height: 16px; padding: 0 4px;
    background: var(--ft-amber);
    border-radius: 8px; border: 2px solid var(--ft-bg);
    font-family: var(--ft-mono); font-size: 9px; font-weight: 700;
    color: #000; display: none;
    align-items: center; justify-content: center; line-height: 1;
}
.ft-fab-badge.visible { display: flex; }

/* ── PANEL (bottom sheet) ──────────────────────────────────────────── */
#forge-tool-panel {
    position: fixed;
    left: 0; right: 0;
    bottom: 0;
    height: var(--ft-panel-h);
    max-height: 92dvh;
    background: var(--ft-bg);
    border-top: 1.5px solid var(--ft-border-hi);
    border-radius: var(--ft-radius-lg) var(--ft-radius-lg) 0 0;
    z-index: calc(var(--ft-z) - 1);
    display: flex; flex-direction: column;
    box-shadow: 0 -8px 60px rgba(0,0,0,0.8);
    transform: translateY(100%);
    transition: transform 0.32s cubic-bezier(0.32,0.72,0,1);
    overflow: hidden;
    /* Safe area */
    padding-bottom: env(safe-area-inset-bottom, 0px);
}
#forge-tool-panel.open {
    transform: translateY(0);
}
/* Resize handle strip */
.ft-panel-resize {
    flex-shrink: 0;
    height: var(--ft-handle-h);
    display: flex; align-items: center; justify-content: center;
    cursor: ns-resize;
    gap: 0;
    border-bottom: 1px solid var(--ft-border);
    background: var(--ft-surface);
    position: relative;
    touch-action: none;
}
.ft-panel-resize-bar {
    width: 40px; height: 4px;
    background: var(--ft-border-hi);
    border-radius: 2px;
    transition: background 0.15s, width 0.15s;
}
.ft-panel-resize:hover .ft-panel-resize-bar,
.ft-panel-resize.dragging .ft-panel-resize-bar {
    background: var(--ft-amber); width: 56px;
}
.ft-panel-title-row {
    position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
    display: flex; align-items: center; gap: 8px;
    font-family: var(--ft-mono); font-size: 0.7rem; font-weight: 700;
    color: var(--ft-amber); letter-spacing: 2px; text-transform: uppercase;
    pointer-events: none;
}
.ft-panel-title-icon { font-size: 16px; }
.ft-panel-close {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    width: 28px; height: 28px; border-radius: 6px;
    border: 1px solid var(--ft-border); background: transparent;
    color: var(--ft-text-dim); cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 14px;
    transition: all 0.15s;
}
.ft-panel-close:hover { border-color: var(--ft-red); color: var(--ft-red); background: var(--ft-red-dim); }

/* ── TAB BAR ───────────────────────────────────────────────────────── */
.ft-tab-bar {
    flex-shrink: 0;
    height: var(--ft-tab-h);
    display: flex; align-items: stretch;
    background: var(--ft-surface);
    border-bottom: 1px solid var(--ft-border);
    overflow-x: auto; overflow-y: hidden;
    scrollbar-width: none; -ms-overflow-style: none;
    -webkit-overflow-scrolling: touch;
}
.ft-tab-bar::-webkit-scrollbar { display: none; }
.ft-tab {
    flex-shrink: 0;
    display: flex; align-items: center; gap: 6px;
    padding: 0 16px;
    font-family: var(--ft-mono); font-size: 0.7rem; font-weight: 700;
    letter-spacing: 0.8px; text-transform: uppercase;
    color: var(--ft-text-dim);
    cursor: pointer; border: none; background: transparent;
    border-bottom: 2px solid transparent;
    white-space: nowrap;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
    position: relative;
}
.ft-tab:hover { color: var(--ft-text); background: rgba(255,255,255,0.03); }
.ft-tab.active {
    color: var(--ft-amber);
    border-bottom-color: var(--ft-amber);
    background: var(--ft-amber-dim);
}
.ft-tab-icon { font-size: 14px; }
.ft-tab-close {
    width: 16px; height: 16px; border-radius: 3px;
    border: none; background: transparent;
    color: var(--ft-text-dim); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 10px; padding: 0; margin-left: 2px;
    transition: color 0.12s, background 0.12s;
}
.ft-tab-close:hover { color: var(--ft-red); background: var(--ft-red-dim); }
.ft-tab-add {
    flex-shrink: 0; width: 44px;
    display: flex; align-items: center; justify-content: center;
    color: var(--ft-text-dim); cursor: pointer; font-size: 18px;
    transition: color 0.15s;
    background: transparent; border: none;
    border-left: 1px solid var(--ft-border);
}
.ft-tab-add:hover { color: var(--ft-teal); }

/* ── TAB CONTENT ───────────────────────────────────────────────────── */
.ft-tab-content {
    flex: 1; overflow: hidden;
    display: none;
    flex-direction: column;
}
.ft-tab-content.active { display: flex; }
/* iframe tabs */
.ft-iframe-content {
    flex: 1; overflow: hidden;
    display: none; flex-direction: column;
}
.ft-iframe-content.active { display: flex; }
.ft-iframe-content iframe {
    flex: 1; width: 100%; border: none;
    background: var(--ft-bg);
}

/* ── CLIPBOARD TAB ─────────────────────────────────────────────────── */
.ft-cb-layout {
    display: flex; flex-direction: column;
    height: 100%; overflow: hidden;
}
.ft-cb-add-row {
    flex-shrink: 0; padding: 10px 14px;
    background: var(--ft-surface);
    border-bottom: 1px solid var(--ft-border);
    display: flex; flex-direction: column; gap: 6px;
}
.ft-cb-input-row { display: flex; gap: 6px; }
.ft-cb-input {
    flex: 1; padding: 8px 10px; border-radius: 6px;
    border: 1px solid var(--ft-border);
    background: rgba(0,0,0,0.4); color: var(--ft-text);
    font-family: var(--ft-mono); font-size: 0.78rem; min-width: 0;
    transition: border-color 0.15s;
    -webkit-appearance: none;
}
.ft-cb-input:focus { outline: none; border-color: var(--ft-teal); }
.ft-cb-input.label { width: 90px; flex: none; }
.ft-cb-add-btn {
    flex-shrink: 0; padding: 8px 14px; border-radius: 6px;
    border: none; background: var(--ft-teal); color: #000;
    font-family: var(--ft-mono); font-size: 0.72rem; font-weight: 700;
    cursor: pointer; display: flex; align-items: center; gap: 4px;
    white-space: nowrap; transition: filter 0.15s;
}
.ft-cb-add-btn:hover { filter: brightness(1.1); }

.ft-cb-list-wrap { flex: 1; overflow-y: auto; padding: 8px; }
.ft-cb-list { display: flex; flex-direction: column; gap: 5px; list-style: none; margin: 0; padding: 0; }

/* Clipboard item */
.ft-cb-item {
    background: var(--ft-card);
    border: 1px solid var(--ft-border);
    border-radius: 6px; padding: 8px 10px;
    display: flex; align-items: flex-start; gap: 8px;
    transition: border-color 0.15s;
}
.ft-cb-item.pinned { border-color: var(--ft-amber); }
.ft-cb-item.sortable-ghost { opacity: 0.3; }
.ft-cb-item.sortable-chosen { border-color: var(--ft-teal); box-shadow: 0 0 0 1px var(--ft-teal); }

.ft-drag-handle {
    flex-shrink: 0; cursor: grab; color: var(--ft-text-dim);
    font-size: 16px; padding-top: 2px; touch-action: none;
    line-height: 1;
}
.ft-drag-handle:active { cursor: grabbing; }
.ft-cb-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.ft-cb-label-row { display: flex; align-items: center; gap: 6px; }
.ft-cb-label {
    font-size: 0.6rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.8px; color: var(--ft-text-dim);
}
.ft-cb-label.has-label { color: var(--ft-amber); }
.ft-cb-pin-badge { font-size: 0.55rem; color: var(--ft-amber); font-weight: 700; }
.ft-cb-text {
    font-size: 0.74rem; color: var(--ft-text); line-height: 1.4;
    word-break: break-word; cursor: pointer;
    padding: 2px 4px; border-radius: 3px;
    border: 1px solid transparent; transition: border-color 0.12s;
}
.ft-cb-text:hover { border-color: var(--ft-border); }
.ft-cb-text-edit {
    display: none; width: 100%; padding: 4px 7px; border-radius: 3px;
    border: 1px solid var(--ft-teal); background: rgba(0,0,0,0.4);
    color: var(--ft-text); font-family: var(--ft-mono); font-size: 0.74rem;
    resize: none; line-height: 1.4; -webkit-appearance: none;
}
.ft-cb-text-edit.active { display: block; }
.ft-cb-text.editing { display: none; }
.ft-cb-label-input {
    display: none; width: 100%; padding: 3px 7px; border-radius: 3px;
    border: 1px solid var(--ft-border); background: rgba(0,0,0,0.4);
    color: var(--ft-text-dim); font-family: var(--ft-mono); font-size: 0.65rem;
    -webkit-appearance: none;
}
.ft-cb-label-input.active { display: block; }

.ft-cb-actions { display: flex; gap: 4px; flex-shrink: 0; align-items: flex-start; }
.ft-ia {
    width: 28px; height: 28px; border-radius: 4px;
    border: 1px solid var(--ft-border); background: transparent;
    color: var(--ft-text-dim); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; transition: all 0.12s; flex-shrink: 0;
    -webkit-tap-highlight-color: transparent;
}
.ft-ia:hover { color: var(--ft-text); border-color: var(--ft-border-hi); }
.ft-ia.pin:hover, .ft-ia.pin.on { color: var(--ft-amber); border-color: var(--ft-amber); background: var(--ft-amber-dim); }
.ft-ia.edit:hover, .ft-ia.edit.on { color: var(--ft-teal); border-color: var(--ft-teal); background: var(--ft-teal-dim); }
.ft-ia.del:hover { color: var(--ft-red); border-color: var(--ft-red); background: var(--ft-red-dim); }
.ft-ia.save { display: none; color: var(--ft-teal); border-color: var(--ft-teal); }
.ft-ia.save.on { display: flex; }
.ft-ia.copy:hover { color: var(--ft-green); border-color: var(--ft-green); background: var(--ft-green-dim); }

.ft-cb-empty {
    display: flex; align-items: center; justify-content: center;
    height: 100px; color: var(--ft-text-dim);
    font-family: var(--ft-mono); font-size: 0.72rem; font-style: italic;
}

/* ── SHORTCUTS TAB ─────────────────────────────────────────────────── */
.ft-shortcuts-layout {
    display: flex; flex-direction: column; height: 100%; overflow: hidden;
}
.ft-shortcuts-grid-wrap { flex: 1; overflow-y: auto; padding: 14px; }
.ft-shortcuts-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}
@media (min-width: 480px) { .ft-shortcuts-grid { grid-template-columns: repeat(5, 1fr); } }

.ft-shortcut-btn {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 6px; padding: 12px 6px;
    background: var(--ft-card); border: 1px solid var(--ft-border);
    border-radius: var(--ft-radius); cursor: pointer;
    transition: all 0.15s; text-decoration: none;
    -webkit-tap-highlight-color: transparent;
    min-height: 70px;
    position: relative; overflow: hidden;
}
.ft-shortcut-btn::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 50% 0%, var(--ft-amber-dim), transparent 70%);
    opacity: 0; transition: opacity 0.2s;
}
.ft-shortcut-btn:hover { border-color: var(--ft-amber); transform: translateY(-2px); }
.ft-shortcut-btn:hover::before { opacity: 1; }
.ft-shortcut-btn:active { transform: scale(0.95); }
.ft-shortcut-icon { font-size: 22px; line-height: 1; }
.ft-shortcut-label {
    font-family: var(--ft-mono); font-size: 0.55rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.8px;
    color: var(--ft-text-dim); text-align: center; line-height: 1.2;
    word-break: break-word;
}
.ft-shortcut-btn:hover .ft-shortcut-label { color: var(--ft-amber); }

/* Shortcut / Fullscreen modal overlay */
.ft-shortcut-modal-overlay {
    position: fixed; inset: 0;
    background: var(--ft-bg); /* Full solid background */
    z-index: calc(var(--ft-z) + 10);
    display: flex; align-items: center; justify-content: center;
    opacity: 0; pointer-events: none;
    transition: opacity 0.2s;
    padding: 0; /* Removing padding to maximize space */
}
.ft-shortcut-modal-overlay.open { opacity: 1; pointer-events: all; }
.ft-shortcut-modal-box {
    width: 100%; height: 100%;
    background: var(--ft-bg);
    display: flex; flex-direction: column;
    transform: scale(0.98); transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1);
    position: relative;
}
.ft-shortcut-modal-overlay.open .ft-shortcut-modal-box { transform: scale(1); }
.ft-shortcut-modal-body {
    flex: 1; overflow: hidden; padding: 0; height: 100%;
}
.ft-shortcut-modal-body iframe {
    width: 100%; height: 100%; border: none; display: block;
    background: var(--ft-bg);
}

/* Floating Modal Buttons */
.ft-sm-floating-btn {
    position: absolute; top: 12px; z-index: 100;
    height: 36px; border-radius: 8px;
    background: rgba(17,24,32,0.6); backdrop-filter: blur(8px);
    border: 1px solid var(--ft-border-hi); color: var(--ft-text-hi);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.15s; text-decoration: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.5);
}
.ft-sm-floating-btn:hover { background: var(--ft-surface); }
.ft-sm-close { right: 12px; width: 36px; font-size: 16px; }
.ft-sm-close:hover { color: var(--ft-red); border-color: var(--ft-red); background: var(--ft-red-dim); }
.ft-sm-open { right: 56px; padding: 0 12px; font-family: var(--ft-mono); font-size: 11px; font-weight: 700; color: var(--ft-teal); }
.ft-sm-open:hover { border-color: var(--ft-teal); background: var(--ft-teal-dim); }

/* ── SETTINGS TAB ──────────────────────────────────────────────────── */
.ft-settings-layout {
    display: flex; flex-direction: column;
    height: 100%; overflow: hidden;
}
.ft-settings-scroll { flex: 1; overflow-y: auto; padding: 14px 14px 24px; }
.ft-settings-section {
    margin-bottom: 24px;
}
.ft-settings-section-title {
    font-family: var(--ft-mono); font-size: 0.62rem; font-weight: 700;
    color: var(--ft-amber); text-transform: uppercase; letter-spacing: 2px;
    margin-bottom: 12px; display: flex; align-items: center; gap: 8px;
}
.ft-settings-section-title::after {
    content: ''; flex: 1; height: 1px; background: var(--ft-border);
}

/* Setting row */
.ft-setting-row {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; background: var(--ft-card);
    border: 1px solid var(--ft-border); border-radius: 6px;
    margin-bottom: 6px; transition: border-color 0.15s;
}
.ft-setting-row:hover { border-color: var(--ft-border-hi); }
.ft-setting-label {
    flex: 1; font-family: var(--ft-mono); font-size: 0.75rem;
    color: var(--ft-text); min-width: 0;
}
.ft-setting-sub {
    font-size: 0.6rem; color: var(--ft-text-dim); margin-top: 2px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.ft-setting-toggle {
    flex-shrink: 0; width: 38px; height: 22px; border-radius: 11px;
    border: 1px solid var(--ft-border); background: var(--ft-bg);
    cursor: pointer; position: relative; transition: all 0.2s;
    -webkit-tap-highlight-color: transparent;
}
.ft-setting-toggle::after {
    content: ''; position: absolute;
    width: 16px; height: 16px; border-radius: 50%;
    background: var(--ft-text-dim);
    top: 2px; left: 2px; transition: all 0.2s;
}
.ft-setting-toggle.on { background: var(--ft-teal); border-color: var(--ft-teal); }
.ft-setting-toggle.on::after { left: 18px; background: #000; }

/* Shortcut list (editable) */
.ft-shortcut-list { display: flex; flex-direction: column; gap: 6px; }
.ft-shortcut-row {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 12px; background: var(--ft-card);
    border: 1px solid var(--ft-border); border-radius: 6px;
}
.ft-shortcut-row .ft-drag-handle { font-size: 14px; color: var(--ft-border-hi); }
.ft-shortcut-row-icon { font-size: 18px; flex-shrink: 0; }
.ft-shortcut-row-label {
    flex: 1; font-family: var(--ft-mono); font-size: 0.72rem; color: var(--ft-text);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.ft-shortcut-row-url {
    font-size: 0.62rem; color: var(--ft-text-dim);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.ft-shortcut-row-visible {
    flex-shrink: 0; width: 28px; height: 28px; border-radius: 4px;
    border: 1px solid var(--ft-border); background: transparent;
    color: var(--ft-text-dim); cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 13px;
    transition: all 0.12s;
}
.ft-shortcut-row-visible.on { color: var(--ft-teal); border-color: var(--ft-teal); background: var(--ft-teal-dim); }

.ft-shortcut-row-edit, .ft-custom-tab-edit, .ft-shortcut-row-del, .ft-custom-tab-del {
    flex-shrink: 0; width: 28px; height: 28px; border-radius: 4px;
    border: 1px solid var(--ft-border); background: transparent;
    color: var(--ft-text-dim); cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 13px;
    transition: all 0.12s;
}
.ft-shortcut-row-edit:hover, .ft-custom-tab-edit:hover { color: var(--ft-teal); border-color: var(--ft-teal); background: var(--ft-teal-dim); }
.ft-shortcut-row-del:hover, .ft-custom-tab-del:hover { color: var(--ft-red); border-color: var(--ft-red); background: var(--ft-red-dim); }

/* Add shortcut form */
.ft-add-shortcut-form {
    display: flex; flex-direction: column; gap: 8px;
    padding: 12px; background: var(--ft-card);
    border: 1px solid var(--ft-border); border-radius: 6px;
    margin-top: 10px;
}
.ft-add-form-row { display: flex; gap: 6px; }
.ft-add-input {
    flex: 1; padding: 7px 10px; border-radius: 5px;
    border: 1px solid var(--ft-border); background: rgba(0,0,0,0.4);
    color: var(--ft-text); font-family: var(--ft-mono); font-size: 0.75rem;
    -webkit-appearance: none; transition: border-color 0.15s;
}
.ft-add-input:focus { outline: none; border-color: var(--ft-teal); }
.ft-add-input.icon { width: 54px; flex: none; text-align: center; font-size: 1.1rem; }
.ft-add-btn {
    flex-shrink: 0; padding: 7px 14px; border-radius: 5px;
    border: none; background: var(--ft-teal); color: #000;
    font-family: var(--ft-mono); font-size: 0.72rem; font-weight: 700;
    cursor: pointer; white-space: nowrap;
    display: flex; align-items: center; gap: 4px; transition: filter 0.15s;
}
.ft-add-btn:hover { filter: brightness(1.1); }

/* Custom tabs list */
.ft-custom-tabs-list { display: flex; flex-direction: column; gap: 6px; }
.ft-custom-tab-row {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 12px; background: var(--ft-card);
    border: 1px solid var(--ft-border); border-radius: 6px;
}
.ft-custom-tab-icon { font-size: 16px; flex-shrink: 0; }
.ft-custom-tab-info { flex: 1; min-width: 0; }
.ft-custom-tab-name { font-family: var(--ft-mono); font-size: 0.72rem; color: var(--ft-text); }
.ft-custom-tab-url { font-size: 0.6rem; color: var(--ft-text-dim); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Settings footer */
.ft-settings-footer {
    flex-shrink: 0; padding: 12px 14px;
    border-top: 1px solid var(--ft-border);
    background: var(--ft-surface);
    display: flex; gap: 8px; justify-content: flex-end;
}
.ft-settings-save-btn {
    padding: 9px 20px; border-radius: 6px;
    border: none; background: var(--ft-amber); color: #000;
    font-family: var(--ft-mono); font-size: 0.78rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.8px;
    cursor: pointer; transition: filter 0.15s;
    display: flex; align-items: center; gap: 6px;
}
.ft-settings-save-btn:hover { filter: brightness(1.12); }

/* ── TOAST ──────────────────────────────────────────────────────────── */
.ft-toast {
    position: fixed;
    bottom: calc(var(--ft-panel-h) + 12px);
    left: 50%; transform: translateX(-50%) translateY(0);
    background: var(--ft-teal); color: #000;
    padding: 7px 18px; border-radius: 20px;
    font-family: var(--ft-mono); font-size: 0.72rem; font-weight: 700;
    pointer-events: none; opacity: 0;
    transition: opacity 0.2s, transform 0.2s;
    z-index: calc(var(--ft-z) + 5);
    white-space: nowrap;
    box-shadow: 0 4px 20px rgba(0,0,0,0.5);
}
.ft-toast.err { background: var(--ft-red); color: #fff; }
.ft-toast.show { opacity: 1; }

/* ── ANIMATIONS ──────────────────────────────────────────────────── */
@keyframes ftPulse {
    0%, 100% { box-shadow: 0 4px 20px rgba(0,0,0,0.6), 0 0 0 0 var(--ft-amber-glow); }
    50%       { box-shadow: 0 4px 20px rgba(0,0,0,0.6), 0 0 0 8px rgba(245,166,35,0); }
}
@keyframes ftSlideIn {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.ft-cb-item { animation: ftSlideIn 0.18s ease both; }

/* ── SCROLLBAR (panel internals) ─────────────────────────────────── */
.ft-cb-list-wrap::-webkit-scrollbar,
.ft-settings-scroll::-webkit-scrollbar,
.ft-shortcuts-grid-wrap::-webkit-scrollbar { width: 3px; }
.ft-cb-list-wrap::-webkit-scrollbar-thumb,
.ft-settings-scroll::-webkit-scrollbar-thumb,
.ft-shortcuts-grid-wrap::-webkit-scrollbar-thumb { background: var(--ft-border-hi); border-radius: 3px; }
</style>

<!-- ══════════════════════════════════════════════════════════════════════════
     SORTABLE.JS (lazy-loaded)
══════════════════════════════════════════════════════════════════════════ -->
<script>
(function(){
    if (window.Sortable) return;
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
    document.head.appendChild(s);
})();
</script>

<!-- ══════════════════════════════════════════════════════════════════════════
     HTML STRUCTURE
══════════════════════════════════════════════════════════════════════════ -->

<!-- FAB -->
<div id="forge-tool-fab" aria-label="Forge Tool" role="button" tabindex="0">
    <div class="ft-fab-inner">
        <span class="ft-fab-icon closed">⚗</span>
        <span class="ft-fab-icon opened">✕</span>
    </div>
    <div class="ft-fab-badge" id="ft-fab-badge">0</div>
</div>

<!-- PANEL -->
<div id="forge-tool-panel" role="dialog" aria-label="Forge Tool Panel">

    <!-- Resize handle -->
    <div class="ft-panel-resize" id="ft-resize-handle" title="Drag to resize">
        <div class="ft-panel-title-row">
            <span class="ft-panel-title-icon">⚗</span>
            <span>FORGE TOOL</span>
        </div>
        <div class="ft-panel-resize-bar"></div>
        <button style="display:none;" class="ft-panel-close" id="ft-panel-close" aria-label="Close">✕</button>
    </div>

    <!-- Tab bar -->
    <div class="ft-tab-bar" id="ft-tab-bar" role="tablist">
        <button class="ft-tab active" data-tab="clipboard" role="tab">
            <span class="ft-tab-icon">📋</span> Clipboard
        </button>
        <button class="ft-tab" data-tab="shortcuts" role="tab">
            <span class="ft-tab-icon">⚡</span> Shortcuts
        </button>
        <button class="ft-tab" data-tab="settings" role="tab">
            <span class="ft-tab-icon">⚙</span> Settings
        </button>
        <!-- Dynamic tabs injected here by JS -->
    </div>

    <!-- ── CLIPBOARD TAB ── -->
    <div class="ft-tab-content active" id="ft-tab-clipboard" role="tabpanel">
        <div class="ft-cb-layout">
            <div class="ft-cb-add-row">
                <div class="ft-cb-input-row">
                    <input class="ft-cb-input label" id="ft-cb-new-label" placeholder="Label…" maxlength="120" autocomplete="off">
                    <button class="ft-cb-add-btn" onclick="ForgeTool.cb.add()">+ Add</button>
                </div>
                <input class="ft-cb-input" id="ft-cb-new-content" placeholder="Paste or type your clip here…" maxlength="4000" autocomplete="off"
                       onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();ForgeTool.cb.add();}">
            </div>
            <div class="ft-cb-list-wrap">
                <ul class="ft-cb-list" id="ft-cb-list">
                    <li class="ft-cb-empty">Loading…</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ── SHORTCUTS TAB ── -->
    <div class="ft-tab-content" id="ft-tab-shortcuts" role="tabpanel">
        <div class="ft-shortcuts-layout">
            <div class="ft-shortcuts-grid-wrap">
                <div class="ft-shortcuts-grid" id="ft-shortcuts-grid">
                    <!-- populated by JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- ── SETTINGS TAB ── -->
    <div class="ft-tab-content" id="ft-tab-settings" role="tabpanel">
        <div class="ft-settings-layout">
            <div class="ft-settings-scroll" id="ft-settings-scroll">

                <!-- Section: Shortcuts Manager -->
                <div class="ft-settings-section">
                    <div class="ft-settings-section-title">⚡ Shortcuts</div>

                    <!-- Add shortcut form -->
                    <div class="ft-add-shortcut-form" id="ft-add-shortcut-form">
                        <div class="ft-add-form-row">
                            <input type="text" class="ft-add-input icon" id="ft-sc-new-icon" placeholder="⚗" maxlength="4" title="Emoji icon">
                            <input type="text" class="ft-add-input" id="ft-sc-new-label" placeholder="Label…" maxlength="40">
                            <button class="ft-add-btn" id="ft-sc-add-btn" onclick="ForgeTool.settings.addShortcut()">+ Add</button>
                        </div>
                        <div class="ft-add-form-row">
                            <input type="text" class="ft-add-input" id="ft-sc-new-url" placeholder="URL or javascript:… (leave blank for modal)" maxlength="500">
                        </div>
                        <div class="ft-add-form-row" style="gap:6px; align-items:center; flex-wrap:wrap;">
                            <label style="font-family:var(--ft-mono);font-size:0.65rem;color:var(--ft-text-dim);display:flex;align-items:center;gap:5px;">
                                <input type="checkbox" id="ft-sc-new-modal" style="accent-color:var(--ft-teal);"> Open in Modal
                            </label>
                            <label style="font-family:var(--ft-mono);font-size:0.65rem;color:var(--ft-text-dim);display:flex;align-items:center;gap:5px;">
                                <input type="checkbox" id="ft-sc-new-fullscreen" style="accent-color:var(--ft-amber);"> Fullscreen Modal
                            </label>
                        </div>
                    </div>

                    <div class="ft-shortcut-list" id="ft-shortcut-list">
                        <!-- populated by JS -->
                    </div>
                </div>

                <!-- Section: Custom Iframe Tabs -->
                <div class="ft-settings-section">
                    <div class="ft-settings-section-title">🗂 Custom Tabs</div>
                    <p style="font-size:0.7rem; color:var(--ft-text-dim); font-family:var(--ft-mono); margin-bottom:10px; line-height:1.5;">
                        Add tabs that load any view in an iframe inside this panel.
                    </p>
                    <div class="ft-add-shortcut-form">
                        <div class="ft-add-form-row">
                            <input type="text" class="ft-add-input icon" id="ft-tab-new-icon" placeholder="🗂" maxlength="4">
                            <input type="text" class="ft-add-input" id="ft-tab-new-label" placeholder="Tab name…" maxlength="30">
                            <button class="ft-add-btn" id="ft-tab-add-btn" onclick="ForgeTool.settings.addCustomTab()">+ Tab</button>
                        </div>
                        <div class="ft-add-form-row">
                            <input type="text" class="ft-add-input" id="ft-tab-new-url" placeholder="/view.php?… (iframe src)" maxlength="500">
                        </div>
                    </div>
                    <div class="ft-custom-tabs-list" id="ft-custom-tabs-list" style="margin-top:10px;">
                        <!-- populated by JS -->
                    </div>
                </div>

                <!-- Section: Panel Height -->
                <div class="ft-settings-section">
                    <div class="ft-settings-section-title">📐 Panel Height</div>
                    <div class="ft-setting-row">
                        <div class="ft-setting-label">
                            Default Height
                            <div class="ft-setting-sub" id="ft-height-display">72%</div>
                        </div>
                        <input type="range" id="ft-height-slider" min="30" max="95" value="72"
                               style="flex:1; max-width:120px; accent-color:var(--ft-amber);"
                               oninput="ForgeTool.settings.onHeightSlider(this.value)">
                    </div>
                </div>

                <!-- Section: Clipboard -->
                <div class="ft-settings-section">
                    <div class="ft-settings-section-title">📋 Clipboard</div>
                    <div class="ft-setting-row">
                        <div class="ft-setting-label">
                            View Area (Scope)
                            <div class="ft-setting-sub" id="ft-cb-area-display">global</div>
                        </div>
                        <input type="text" class="ft-add-input" id="ft-cb-area-input"
                               placeholder="e.g. enhanimatics"
                               style="max-width:140px; flex:none;"
                               value="global">
                    </div>
                    <div class="ft-setting-row" style="margin-top:6px;">
                        <div class="ft-setting-label">
                            Open Clipboard Manager
                            <div class="ft-setting-sub">Full standalone view</div>
                        </div>
                        <a href="clipboard_manager.php?view_area=global" target="_blank"
                           style="padding:6px 12px; border-radius:5px; background:var(--ft-teal); color:#000;
                                  font-family:var(--ft-mono); font-size:0.65rem; font-weight:700; text-decoration:none;">
                            Open ↗
                        </a>
                    </div>
                </div>

            </div>
            <div class="ft-settings-footer">
                <button class="ft-settings-save-btn" onclick="ForgeTool.settings.save()">
                    💾 Save Settings
                </button>
            </div>
        </div>
    </div>

    <!-- Dynamic iframe tabs are injected here as siblings by JS -->

</div><!-- /forge-tool-panel -->

<!-- Fullscreen shortcut modal overlay -->
<div class="ft-shortcut-modal-overlay" id="ft-shortcut-modal" onclick="ForgeTool.shortcutModal.closeOnBg(event)">
    <div class="ft-shortcut-modal-box">
        <a id="ft-sm-open-link" href="#" target="_blank" class="ft-sm-floating-btn ft-sm-open" title="Open Full View">&gt;&gt;</a>
        <button class="ft-sm-floating-btn ft-sm-close" onclick="ForgeTool.shortcutModal.close()" title="Close">✕</button>
        <div class="ft-shortcut-modal-body">
            <iframe id="ft-sm-iframe" src="" loading="lazy" allow="clipboard-read; clipboard-write"></iframe>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="ft-toast" id="ft-toast"></div>

<!-- ══════════════════════════════════════════════════════════════════════════
     FORGE TOOL JS
══════════════════════════════════════════════════════════════════════════ -->
<script>
(function() {
'use strict';

// ═══════════════════════════════════════════════════════════════════════
// ForgeTool — Eruda-style floating console for SAGE AI
// ═══════════════════════════════════════════════════════════════════════
window.ForgeTool = window.ForgeTool || {};
const FT = window.ForgeTool;

// ── CONSTANTS / STATE ────────────────────────────────────────────────
// ── CONSTANTS / STATE ────────────────────────────────────────────────
const CB_URL    = '/clipboard_manager.php';
// Fix API URL so it doesn't break if the page already has query parameters
const API_URL   = window.location.pathname + '?forge_tool_api=';
const LS_PREFIX = 'ft_';

let _panelOpen   = false;
let _activeTab   = localStorage.getItem(LS_PREFIX + 'active_tab') || 'clipboard';
let _cbItems     = [];
let _cbArea      = localStorage.getItem(LS_PREFIX + 'cb_area') || 'global';
let _cbSortable  = null;
let _scSortable  = null;
let _panelH      = parseInt(localStorage.getItem(LS_PREFIX + 'panel_h') || '72');

// Settings (persisted via direct PHP injection)
let _settings = <?php echo json_encode((object)$forgeToolSettings); ?>;
if (!_settings.shortcuts) _settings.shortcuts = [];
if (!_settings.custom_tabs) _settings.custom_tabs = [];
if (!_settings.panel_height) _settings.panel_height = 72;
if (!_settings.cb_area) _settings.cb_area = 'global';

// Default shortcuts (floatool tools)
const DEFAULT_SHORTCUTS = [
    { icon:'🌀', label:'Control Deck',  url:'/scheduler_runner.php',    modal:true,  fullscreen:false, visible:true },
    { icon:'🧭', label:'Dashboard',    url:'/dashboard.php',           modal:false, fullscreen:false, visible:true },
    { icon:'⚗',  label:'Gen Forge',    url:'/generator_forge.php',     modal:true,  fullscreen:false, visible:true },
    { icon:'🗺️',  label:'Enhanimatics', url:'/enhanimaticism.php',      modal:false, fullscreen:false, visible:true },
    { icon:'🛹',  label:'Boards',       url:'/boards_view.php',         modal:false, fullscreen:false, visible:true },
    { icon:'🛢️',  label:'DB Tool',      url:'/dbtool.php',              modal:true,  fullscreen:false, visible:true },
    { icon:'🎨',  label:'Styles',       url:'/styles_toggle.php',       modal:true,  fullscreen:false, visible:true },
    { icon:'📋',  label:'Clipboard',    url:'/clipboard_manager.php',   modal:true,  fullscreen:false, visible:true },
    { icon:'📊',  label:'KG View',      url:'/kg_view.php',             modal:false, fullscreen:false, visible:true },
    { icon:'🪄',  label:'Fuzz Forge',   url:'/fuzz_forge.php',          modal:false, fullscreen:false, visible:true },
    { icon:'📅',  label:'Scheduler',    url:'/view_queue.php',          modal:false, fullscreen:false, visible:true },
    { icon:'🎬',  label:'Video Rev',    url:'/view_vidbat_review.php',   modal:false, fullscreen:false, visible:true },
    { icon:'🎞',  label:'Storyboards',  url:'/view_storyboards.php',    modal:false, fullscreen:false, visible:true },
];

// ── TOAST ────────────────────────────────────────────────────────────
let _toastTimer;
function toast(msg, err) {
    const el = document.getElementById('ft-toast');
    el.textContent = msg;
    el.className = 'ft-toast' + (err ? ' err' : '') + ' show';
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => el.classList.remove('show'), 2000);
}

// ── UTILS ────────────────────────────────────────────────────────────
function esc(s) {
    if (!s) return '';
    return s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function ls(key, val) {
    if (val === undefined) return localStorage.getItem(LS_PREFIX + key);
    localStorage.setItem(LS_PREFIX + key, val);
}

// ── SETTINGS LOAD/SAVE ───────────────────────────────────────────────
async function loadSettings() {
    // Data is already injected directly into _settings via PHP on page load!

    // If no shortcuts saved yet, use defaults
    if (!_settings.shortcuts || !_settings.shortcuts.length) {
        _settings.shortcuts = JSON.parse(JSON.stringify(DEFAULT_SHORTCUTS));
    }

    // Apply saved panel height
    if (_settings.panel_height) {
        _panelH = _settings.panel_height;
        applyPanelHeight(_panelH);
    }
    // Apply saved cb_area
    if (_settings.cb_area) {
        _cbArea = _settings.cb_area;
    }
}

async function saveSettings() {
    try {
        // Read current height slider
        const hsl = document.getElementById('ft-height-slider');
        if (hsl) _settings.panel_height = parseInt(hsl.value);
        // Read cb area
        const cbInput = document.getElementById('ft-cb-area-input');
        if (cbInput) {
            _settings.cb_area = cbInput.value.trim() || 'global';
            _cbArea = _settings.cb_area;
            ls('cb_area', _cbArea);
        }

        await fetch(API_URL + 'save_settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(_settings),
        });
        toast('Settings saved ✓');
        renderCustomTabs();
        renderShortcutsGrid();
    } catch(e) {
        toast('Save failed', true);
    }
}

// ── PANEL OPEN/CLOSE ─────────────────────────────────────────────────
function openPanel() {
    _panelOpen = true;
    document.getElementById('forge-tool-panel').classList.add('open');
    document.getElementById('forge-tool-fab').classList.add('panel-open');
    // Restore last active tab
    switchTab(_activeTab);
    // Load clipboard if on clipboard tab
    if (_activeTab === 'clipboard') FT.cb.load();
}
function closePanel() {
    _panelOpen = false;
    document.getElementById('forge-tool-panel').classList.remove('open');
    document.getElementById('forge-tool-fab').classList.remove('panel-open');
}
function togglePanel() {
    _panelOpen ? closePanel() : openPanel();
}

// ── TAB SWITCHING ────────────────────────────────────────────────────
function switchTab(tab) {
    _activeTab = tab;
    ls('active_tab', tab);
    // Update tab buttons
    document.querySelectorAll('#ft-tab-bar .ft-tab').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    // Update tab content panels
    document.querySelectorAll('#forge-tool-panel > .ft-tab-content').forEach(el => {
        el.classList.toggle('active', el.id === 'ft-tab-' + tab);
    });
    // Handle iframe tabs
    document.querySelectorAll('#forge-tool-panel > .ft-iframe-content').forEach(el => {
        const isActive = el.dataset.tab === tab;
        el.classList.toggle('active', isActive);
        // Lazy-load iframe
        if (isActive) {
            const iframe = el.querySelector('iframe');
            if (iframe && !iframe.getAttribute('src') && iframe.dataset.src) {
                iframe.setAttribute('src', iframe.dataset.src);
            }
        }
    });
}

// ── PANEL RESIZE (drag handle) ───────────────────────────────────────
function applyPanelHeight(pct) {
    const panel = document.getElementById('forge-tool-panel');
    panel.style.setProperty('--ft-panel-h', pct + 'vh');
    const display = document.getElementById('ft-height-display');
    if (display) display.textContent = pct + '%';
    const slider = document.getElementById('ft-height-slider');
    if (slider) slider.value = pct;
    // Move toast position
    const toast = document.getElementById('ft-toast');
    if (toast) toast.style.bottom = 'calc(' + pct + 'vh + 12px)';
}

function initResizeHandle() {
    const handle = document.getElementById('ft-resize-handle');
    let startY, startH, dragging = false;

    function onStart(e) {
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        startY = clientY;
        startH = parseInt(getComputedStyle(document.getElementById('forge-tool-panel'))
                    .getPropertyValue('--ft-panel-h')) || _panelH;
        dragging = true;
        handle.classList.add('dragging');
        document.addEventListener('mousemove', onMove);
        document.addEventListener('touchmove', onMove, { passive: false });
        document.addEventListener('mouseup', onEnd);
        document.addEventListener('touchend', onEnd);
        e.preventDefault();
    }
    function onMove(e) {
        if (!dragging) return;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        const dy = startY - clientY; // drag up = increase height
        const newH = Math.min(95, Math.max(25, startH + (dy / window.innerHeight) * 100));
        applyPanelHeight(Math.round(newH));
        e.preventDefault();
    }
    function onEnd() {
        dragging = false;
        handle.classList.remove('dragging');
        // Save
        const panel = document.getElementById('forge-tool-panel');
        const h = parseInt(panel.style.getPropertyValue('--ft-panel-h') || _panelH);
        _settings.panel_height = h;
        ls('panel_h', h);
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('touchmove', onMove);
        document.removeEventListener('mouseup', onEnd);
        document.removeEventListener('touchend', onEnd);
    }

    handle.addEventListener('mousedown', onStart);
    handle.addEventListener('touchstart', onStart, { passive: false });
}

// ── FAB DRAG ─────────────────────────────────────────────────────────
function initFabDrag() {
    const fab = document.getElementById('forge-tool-fab');
    let startX, startY, fabX, fabY, dragging = false;
    let dragStart;

    function getPos() {
        const rect = fab.getBoundingClientRect();
        return { x: rect.left, y: rect.top };
    }

    function onStart(e) {
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        startX = clientX; startY = clientY;
        const pos = getPos();
        fabX = pos.x; fabY = pos.y;
        dragging = false;
        dragStart = { x: clientX, y: clientY, time: Date.now() };
        document.addEventListener('mousemove', onMove);
        document.addEventListener('touchmove', onMove, { passive: false });
        document.addEventListener('mouseup', onEnd);
        document.addEventListener('touchend', onEnd);
        e.preventDefault();
    }
    function onMove(e) {
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        const dx = clientX - startX, dy = clientY - startY;
        if (!dragging && (Math.abs(dx) > 6 || Math.abs(dy) > 6)) {
            dragging = true;
            fab.style.cursor = 'grabbing';
        }
        if (!dragging) return;
        e.preventDefault();
        const newX = Math.max(0, Math.min(window.innerWidth  - fab.offsetWidth,  fabX + dx));
        const newY = Math.max(0, Math.min(window.innerHeight - fab.offsetHeight, fabY + dy));
        fab.style.left   = newX + 'px';
        fab.style.top    = newY + 'px';
        fab.style.right  = 'auto';
        fab.style.bottom = 'auto';
    }
    function onEnd(e) {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('touchmove', onMove);
        document.removeEventListener('mouseup', onEnd);
        document.removeEventListener('touchend', onEnd);
        fab.style.cursor = 'grab';
        if (!dragging && dragStart && (Date.now() - dragStart.time) < 350) {
            togglePanel();
        }
        if (dragging) {
            ls('fab_left', fab.style.left);
            ls('fab_top',  fab.style.top);
            ls('fab_right', fab.style.right);
            ls('fab_bottom', fab.style.bottom);
        }
        dragging = false;
        dragStart = null;
    }
    fab.addEventListener('mousedown', onStart);
    fab.addEventListener('touchstart', onStart, { passive: false });

    // Restore position
    const savedL = ls('fab_left'), savedT = ls('fab_top');
    if (savedL && savedT) {
        fab.style.left   = savedL;
        fab.style.top    = savedT;
        fab.style.right  = 'auto';
        fab.style.bottom = 'auto';
    }
}

// ── CLIPBOARD ────────────────────────────────────────────────────────
FT.cb = {
    async load() {
        const list = document.getElementById('ft-cb-list');
        list.innerHTML = '<li class="ft-cb-empty">Loading…</li>';
        try {
            const r = await fetch(CB_URL + '?api_action=cb_get&view_area=' + encodeURIComponent(_cbArea));
            const d = await r.json();
            if (d.status !== 'success') throw new Error(d.message);
            _cbItems = d.data;
            this.render();
            updateFabBadge();
        } catch(e) {
            list.innerHTML = '<li class="ft-cb-empty" style="color:var(--ft-red);">Load failed</li>';
        }
    },

    render() {
        const list = document.getElementById('ft-cb-list');
        list.innerHTML = '';
        if (!_cbItems.length) {
            list.innerHTML = '<li class="ft-cb-empty">No items — add one above</li>';
            return;
        }
        _cbItems.forEach(item => {
            const li = document.createElement('li');
            li.className = 'ft-cb-item' + (parseInt(item.pinned) ? ' pinned' : '');
            li.dataset.id = item.id;
            const hasLabel = item.label && item.label.trim();
            
            li.innerHTML = `
                <div class="ft-drag-handle" title="Drag to reorder">⣿</div>
                <div class="ft-cb-body">
                    <div class="ft-cb-label-row">
                        <span class="ft-cb-label${hasLabel ? ' has-label' : ''}">${hasLabel ? esc(item.label) : 'unlabelled'}</span>
                        ${parseInt(item.pinned) ? '<span class="ft-cb-pin-badge">📌</span>' : ''}
                    </div>
                    <div class="ft-cb-text" onclick="ForgeTool.cb.startEdit(${item.id})">${esc(item.content)}</div>
                    <textarea class="ft-cb-text-edit" rows="2"
                              onblur="ForgeTool.cb.cancelEdit(${item.id})"
                              onkeydown="ForgeTool.cb.editKey(event,${item.id})">${esc(item.content)}</textarea>
                    <input type="text" class="ft-cb-label-input" value="${esc(item.label)}" placeholder="Label…" maxlength="120"
                           onblur="ForgeTool.cb.cancelEdit(${item.id})"
                           onkeydown="ForgeTool.cb.editKey(event,${item.id})">
                </div>
                <div class="ft-cb-actions">
                    <button class="ft-ia copy ft-copy-action" data-id="${item.id}" title="Copy to clipboard">📋</button>
                    <button class="ft-ia pin${parseInt(item.pinned) ? ' on' : ''}" title="Pin/Unpin"
                            onclick="ForgeTool.cb.pin(${item.id})">${parseInt(item.pinned) ? '📌' : '📍'}</button>
                    <button class="ft-ia edit" title="Edit" onclick="ForgeTool.cb.startEdit(${item.id})">✏️</button>
                    <button class="ft-ia save" id="ft-save-${item.id}" title="Save" onclick="ForgeTool.cb.saveEdit(${item.id})">✓</button>
                    <button class="ft-ia del" title="Delete" onclick="ForgeTool.cb.del(${item.id})">🗑</button>
                </div>`;
            list.appendChild(li);
        });
        this.initSortable();
    },

    initSortable() {
        if (_cbSortable) _cbSortable.destroy();
        const list = document.getElementById('ft-cb-list');
        if (!window.Sortable) return;
        _cbSortable = Sortable.create(list, {
            handle: '.ft-drag-handle',
            animation: 140,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd: () => this.saveOrder(),
        });
    },

    async saveOrder() {
        const lis = document.querySelectorAll('#ft-cb-list .ft-cb-item');
        const orders = Array.from(lis).map((li, i) => ({ id: parseInt(li.dataset.id), sort_order: i }));
        await this.api('cb_reorder', { view_area: _cbArea, orders });
    },

    async add() {
        const content = document.getElementById('ft-cb-new-content').value.trim();
        const label   = document.getElementById('ft-cb-new-label').value.trim();
        if (!content) { document.getElementById('ft-cb-new-content').focus(); return; }
        const r = await this.api('cb_add', { content, label, view_area: _cbArea });
        if (r.status !== 'success') { toast(r.message || 'Error', true); return; }
        document.getElementById('ft-cb-new-content').value = '';
        document.getElementById('ft-cb-new-label').value   = '';
        toast('Added ✓');
        await this.load();
    },

    startEdit(id) {
        const li = document.querySelector(`.ft-cb-item[data-id="${id}"]`);
        if (!li) return;
        li.querySelector('.ft-cb-text').classList.add('editing');
        li.querySelector('.ft-cb-text-edit').classList.add('active');
        li.querySelector('.ft-cb-label-input').classList.add('active');
        li.querySelector('.ft-ia.edit').classList.add('on');
        const saveBtn = document.getElementById('ft-save-' + id);
        if (saveBtn) saveBtn.classList.add('on');
        const ta = li.querySelector('.ft-cb-text-edit');
        ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length);
    },
    cancelEdit(id) {
        setTimeout(() => {
            const li = document.querySelector(`.ft-cb-item[data-id="${id}"]`);
            if (!li) return;
            const active = document.activeElement;
            if (active && active.closest(`.ft-cb-item[data-id="${id}"]`)) return;
            li.querySelector('.ft-cb-text').classList.remove('editing');
            li.querySelector('.ft-cb-text-edit').classList.remove('active');
            li.querySelector('.ft-cb-label-input').classList.remove('active');
            li.querySelector('.ft-ia.edit').classList.remove('on');
            const saveBtn = document.getElementById('ft-save-' + id);
            if (saveBtn) saveBtn.classList.remove('on');
        }, 150);
    },
    editKey(e, id) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.saveEdit(id); }
        if (e.key === 'Escape') { this.cancelEdit(id); }
    },
    async saveEdit(id) {
        const li = document.querySelector(`.ft-cb-item[data-id="${id}"]`);
        if (!li) return;
        const content = li.querySelector('.ft-cb-text-edit').value.trim();
        const label   = li.querySelector('.ft-cb-label-input').value.trim();
        if (!content) return;
        const r = await this.api('cb_update', { id, content, label });
        if (r.status !== 'success') { toast(r.message || 'Error', true); return; }
        const item = _cbItems.find(i => i.id == id);
        if (item) { item.content = content; item.label = label; }
        toast('Saved ✓');
        this.render();
    },
    async pin(id) {
        const r = await this.api('cb_pin', { id, view_area: _cbArea });
        if (r.status !== 'success') { toast(r.message || 'Error', true); return; }
        const item = _cbItems.find(i => i.id == id);
        if (item) item.pinned = r.pinned;
        _cbItems.sort((a,b) => parseInt(b.pinned) - parseInt(a.pinned));
        toast(r.pinned ? 'Pinned 📌' : 'Unpinned');
        this.render();
        updateFabBadge();
    },
    async del(id) {
        if (!confirm('Delete this item?')) return;
        const r = await this.api('cb_delete', { id });
        if (r.status !== 'success') { toast(r.message || 'Error', true); return; }
        _cbItems = _cbItems.filter(i => i.id != id);
        toast('Deleted');
        this.render();
        updateFabBadge();
    },
    async api(action, body) {
        const url = CB_URL + '?api_action=' + action + '&view_area=' + encodeURIComponent(_cbArea);
        const res = await fetch(url, {
            method: body ? 'POST' : 'GET',
            headers: body ? { 'Content-Type': 'application/json' } : {},
            body: body ? JSON.stringify(body) : undefined,
        });
        return res.json();
    },
};

function updateFabBadge() {
    const badge = document.getElementById('ft-fab-badge');
    const count = _cbItems.length;
    if (count > 0) { badge.textContent = count > 99 ? '99+' : count; badge.classList.add('visible'); }
    else { badge.classList.remove('visible'); }
}

// ── SHORTCUTS ────────────────────────────────────────────────────────
function renderShortcutsGrid() {
    const grid = document.getElementById('ft-shortcuts-grid');
    grid.innerHTML = '';
    const visible = _settings.shortcuts.filter(s => s.visible !== false);
    if (!visible.length) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:30px;color:var(--ft-text-dim);font-family:var(--ft-mono);font-size:0.72rem;font-style:italic;">No shortcuts visible.<br>Enable them in Settings.</div>';
        return;
    }
    visible.forEach(sc => {
        const btn = document.createElement('div');
        btn.className = 'ft-shortcut-btn';
        btn.innerHTML = `<div class="ft-shortcut-icon">${sc.icon || '⚗'}</div><div class="ft-shortcut-label">${esc(sc.label)}</div>`;
        btn.title = sc.url || sc.label;
        btn.addEventListener('click', () => shortcutAction(sc));
        grid.appendChild(btn);
    });
}

function shortcutAction(sc) {
    if (!sc.url) return;
    if (sc.fullscreen || sc.modal) {
        FT.shortcutModal.open(sc);
    } else {
        window.location.href = sc.url;
    }
}

// Shortcut modal
FT.shortcutModal = {
    open(sc) {
        const overlay = document.getElementById('ft-shortcut-modal');
        const iframe  = document.getElementById('ft-sm-iframe');
        const link    = document.getElementById('ft-sm-open-link');
        
        link.href         = sc.url || '#';
        iframe.src        = sc.url || '';
        
        overlay.classList.add('open');
    },
    close() {
        const overlay = document.getElementById('ft-shortcut-modal');
        const iframe  = document.getElementById('ft-sm-iframe');
        overlay.classList.remove('open');
        setTimeout(() => { iframe.src = ''; }, 300);
    },
    closeOnBg(e) {
        if (e.target === document.getElementById('ft-shortcut-modal')) this.close();
    },
};

// ── SETTINGS ─────────────────────────────────────────────────────────
FT.settings = {
    render() {
        this.renderShortcutList();
        this.renderCustomTabsList();
        const hsl = document.getElementById('ft-height-slider');
        if (hsl) hsl.value = _settings.panel_height || 72;
        applyPanelHeight(_settings.panel_height || 72);
        const cbInput = document.getElementById('ft-cb-area-input');
        if (cbInput) cbInput.value = _settings.cb_area || 'global';
    },

    renderShortcutList() {
        const list = document.getElementById('ft-shortcut-list');
        list.innerHTML = '';
        (_settings.shortcuts || []).forEach((sc, i) => {
            const row = document.createElement('div');
            row.className = 'ft-shortcut-row';
            row.dataset.idx = i;
            row.innerHTML = `
                <div class="ft-drag-handle" style="font-size:13px;color:var(--ft-border-hi);">⣿</div>
                <div class="ft-shortcut-row-icon">${sc.icon || '⚗'}</div>
                <div style="flex:1;min-width:0;">
                    <div class="ft-shortcut-row-label">${esc(sc.label)}</div>
                    <div class="ft-shortcut-row-url">${esc(sc.url || '')}</div>
                </div>
                <button class="ft-shortcut-row-visible${sc.visible !== false ? ' on' : ''}"
                        title="${sc.visible !== false ? 'Visible' : 'Hidden'}"
                        onclick="ForgeTool.settings.toggleVisible(${i})">
                    ${sc.visible !== false ? '👁' : '🙈'}
                </button>
                <button class="ft-shortcut-row-edit" title="Edit" onclick="ForgeTool.settings.editShortcut(${i})">✏️</button>
                <button class="ft-shortcut-row-del" title="Remove" onclick="ForgeTool.settings.removeShortcut(${i})">🗑</button>`;
            list.appendChild(row);
        });
        // Init sortable on shortcut list
        if (_scSortable) _scSortable.destroy();
        if (window.Sortable) {
            _scSortable = Sortable.create(list, {
                handle: '.ft-drag-handle',
                animation: 120,
                onEnd: () => {
                    const rows = list.querySelectorAll('.ft-shortcut-row');
                    const reordered = Array.from(rows).map(r => _settings.shortcuts[parseInt(r.dataset.idx)]);
                    _settings.shortcuts = reordered;
                    this.renderShortcutList();
                },
            });
        }
    },

    toggleVisible(i) {
        _settings.shortcuts[i].visible = !(_settings.shortcuts[i].visible !== false);
        this.renderShortcutList();
    },
    removeShortcut(i) {
        _settings.shortcuts.splice(i, 1);
        this.renderShortcutList();
    },
    editShortcut(i) {
        const sc = _settings.shortcuts[i];
        document.getElementById('ft-sc-new-icon').value = sc.icon || '';
        document.getElementById('ft-sc-new-label').value = sc.label || '';
        document.getElementById('ft-sc-new-url').value = sc.url || '';
        document.getElementById('ft-sc-new-modal').checked = !!sc.modal;
        document.getElementById('ft-sc-new-fullscreen').checked = !!sc.fullscreen;
        
        const btn = document.getElementById('ft-sc-add-btn');
        btn.textContent = '✓ Save';
        btn.dataset.editIdx = i;
    },
    addShortcut() {
        const icon      = document.getElementById('ft-sc-new-icon').value.trim() || '⚗';
        const label     = document.getElementById('ft-sc-new-label').value.trim();
        const url       = document.getElementById('ft-sc-new-url').value.trim();
        const modal     = document.getElementById('ft-sc-new-modal').checked;
        const full      = document.getElementById('ft-sc-new-fullscreen').checked;
        if (!label) { toast('Label required', true); return; }
        
        const btn = document.getElementById('ft-sc-add-btn');
        const editIdx = btn.dataset.editIdx;
        
        if (editIdx !== undefined && editIdx !== "") {
            _settings.shortcuts[editIdx] = { ..._settings.shortcuts[editIdx], icon, label, url, modal: modal || full, fullscreen: full };
            btn.textContent = '+ Add';
            btn.dataset.editIdx = "";
            toast('Shortcut updated ✓');
        } else {
            _settings.shortcuts.push({ icon, label, url, modal: modal || full, fullscreen: full, visible: true });
            toast('Shortcut added ✓');
        }

        document.getElementById('ft-sc-new-icon').value  = '';
        document.getElementById('ft-sc-new-label').value = '';
        document.getElementById('ft-sc-new-url').value   = '';
        document.getElementById('ft-sc-new-modal').checked      = false;
        document.getElementById('ft-sc-new-fullscreen').checked = false;
        this.renderShortcutList();
        renderShortcutsGrid();
    },

    renderCustomTabsList() {
        const list = document.getElementById('ft-custom-tabs-list');
        list.innerHTML = '';
        (_settings.custom_tabs || []).forEach((tab, i) => {
            const row = document.createElement('div');
            row.className = 'ft-custom-tab-row';
            row.innerHTML = `
                <div class="ft-custom-tab-icon">${tab.icon || '🗂'}</div>
                <div class="ft-custom-tab-info">
                    <div class="ft-custom-tab-name">${esc(tab.label)}</div>
                    <div class="ft-custom-tab-url">${esc(tab.url)}</div>
                </div>
                <button class="ft-custom-tab-edit" title="Edit" onclick="ForgeTool.settings.editCustomTab(${i})">✏️</button>
                <button class="ft-custom-tab-del" title="Remove" onclick="ForgeTool.settings.removeCustomTab(${i})">🗑</button>`;
            list.appendChild(row);
        });
    },
    editCustomTab(i) {
        const tab = _settings.custom_tabs[i];
        document.getElementById('ft-tab-new-icon').value = tab.icon || '';
        document.getElementById('ft-tab-new-label').value = tab.label || '';
        document.getElementById('ft-tab-new-url').value = tab.url || '';
        
        const btn = document.getElementById('ft-tab-add-btn');
        btn.textContent = '✓ Save';
        btn.dataset.editIdx = i;
    },
    addCustomTab() {
        const icon  = document.getElementById('ft-tab-new-icon').value.trim() || '🗂';
        const label = document.getElementById('ft-tab-new-label').value.trim();
        const url   = document.getElementById('ft-tab-new-url').value.trim();
        if (!label || !url) { toast('Label and URL required', true); return; }
        if (!_settings.custom_tabs) _settings.custom_tabs = [];
        
        const btn = document.getElementById('ft-tab-add-btn');
        const editIdx = btn.dataset.editIdx;
        
        if (editIdx !== undefined && editIdx !== "") {
            _settings.custom_tabs[editIdx] = { ..._settings.custom_tabs[editIdx], icon, label, url };
            btn.textContent = '+ Tab';
            btn.dataset.editIdx = "";
            toast('Tab updated — save settings to apply ✓');
        } else {
            _settings.custom_tabs.push({ icon, label, url });
            toast('Tab added — save settings to apply ✓');
        }

        document.getElementById('ft-tab-new-icon').value  = '';
        document.getElementById('ft-tab-new-label').value = '';
        document.getElementById('ft-tab-new-url').value   = '';
        this.renderCustomTabsList();
    },
    removeCustomTab(i) {
        _settings.custom_tabs.splice(i, 1);
        this.renderCustomTabsList();
    },
    onHeightSlider(val) {
        applyPanelHeight(parseInt(val));
    },
    save: saveSettings,
};

// ── RENDER DYNAMIC TABS (from settings.custom_tabs) ──────────────────
function renderCustomTabs() {
    const panel  = document.getElementById('forge-tool-panel');
    const tabBar = document.getElementById('ft-tab-bar');

    // Remove old dynamic tabs
    panel.querySelectorAll('.ft-iframe-content').forEach(el => el.remove());
    tabBar.querySelectorAll('.ft-tab[data-custom]').forEach(el => el.remove());

    (_settings.custom_tabs || []).forEach((tab, i) => {
        const tabKey = 'custom_' + i;
        // Tab button
        const btn = document.createElement('button');
        btn.className = 'ft-tab';
        btn.dataset.tab = tabKey;
        btn.dataset.custom = '1';
        btn.innerHTML = `<span class="ft-tab-icon">${tab.icon || '🗂'}</span> ${esc(tab.label)} <button class="ft-tab-close" onclick="event.stopPropagation(); ForgeTool.removeCustomTab(${i})" title="Close tab">✕</button>`;
        btn.addEventListener('click', () => switchTab(tabKey));
        tabBar.appendChild(btn);

        // Content panel (iframe, lazy-loaded)
        const content = document.createElement('div');
        content.className = 'ft-iframe-content';
        content.dataset.tab = tabKey;
        // Notice the empty src="" is removed so the browser doesn't auto-resolve it
        content.innerHTML = `<iframe data-src="${esc(tab.url)}" allowfullscreen allow="clipboard-read; clipboard-write"></iframe>`;
        panel.appendChild(content);
    });
}

FT.removeCustomTab = function(i) {
    if (!confirm('Remove this custom tab?')) return;
    _settings.custom_tabs.splice(i, 1);
    renderCustomTabs();
    FT.settings.renderCustomTabsList();
    switchTab('clipboard');
};

// ── INIT ─────────────────────────────────────────────────────────────
async function init() {
    // Wire tab bar click
    document.getElementById('ft-tab-bar').addEventListener('click', e => {
        const btn = e.target.closest('.ft-tab');
        if (btn && !e.target.classList.contains('ft-tab-close')) {
            switchTab(btn.dataset.tab);
        }
    });

    // Wire up Pure Event Delegation for Copy to Clipboard
    document.getElementById('ft-cb-list').addEventListener('click', e => {
        const copyBtn = e.target.closest('.ft-copy-action');
        if (copyBtn) {
            e.preventDefault();
            const id = copyBtn.dataset.id;
            const item = _cbItems.find(i => i.id == id);
            
            if (item && item.content) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(item.content).then(function() {
                        toast('Copied! ✓');
                    }).catch(function(err) {
                        toast('Copy failed', true);
                    });
                } else {
                    toast('Clipboard API unavailable', true);
                }
            }
        }
    });

    // Close button
    document.getElementById('ft-panel-close').addEventListener('click', closePanel);

    // Escape key
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            if (document.getElementById('ft-shortcut-modal').classList.contains('open')) {
                FT.shortcutModal.close();
            } else if (_panelOpen) {
                closePanel();
            }
        }
    });

    // Load settings
    await loadSettings();

    // Apply saved height
    applyPanelHeight(_panelH);

    // Init drag/resize
    initFabDrag();
    initResizeHandle();

    // Render dynamic tabs from settings
    renderCustomTabs();

    // Render shortcuts grid
    renderShortcutsGrid();

    // Render settings UI
    FT.settings.render();

    // Listen for clipboard updates from iframes/parent
    window.addEventListener('message', e => {
        if (e.data && e.data.type === 'clipboard_updated') {
            if (_activeTab === 'clipboard') FT.cb.load();
        }
    });
}

// Wait for DOM + SortableJS
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    // SortableJS might not be loaded yet
    function waitSortable() {
        if (window.Sortable) { init(); }
        else { setTimeout(waitSortable, 50); }
    }
    waitSortable();
}

})();
</script>