// public/daw/js/daw-history.js
// Undo / Redo history for structural DAW actions.
// Excluded from history: volume, mute, solo, zoom, scroll, plugin-chain params.
// Included: add track, remove track, add clip, remove clip, move clip, replace clip audio.
// History is in-memory only — resets on page reload / project load.

'use strict';

const HISTORY = {
    stack:   [],   // array of { undo: fn, redo: fn, label: string }
    cursor:  -1,   // points at the last applied entry
    maxSize: 100,
};

/**
 * Push a reversible action onto the history stack.
 * Discards any entries above the current cursor (branch cut on new action).
 * @param {string}   label   Human-readable name shown in UI
 * @param {Function} undoFn  Called on undo — must be synchronous
 * @param {Function} redoFn  Called on redo (also what just happened)
 */
function historyPush(label, undoFn, redoFn) {
    // Discard redo branch
    if (HISTORY.cursor < HISTORY.stack.length - 1) {
        HISTORY.stack.splice(HISTORY.cursor + 1);
    }
    HISTORY.stack.push({ label, undo: undoFn, redo: redoFn });
    if (HISTORY.stack.length > HISTORY.maxSize) HISTORY.stack.shift();
    HISTORY.cursor = HISTORY.stack.length - 1;
    _syncHistoryUI();
}

function historyUndo() {
    if (HISTORY.cursor < 0) { Toast.show('Nothing to undo', 'info'); return; }
    const entry = HISTORY.stack[HISTORY.cursor];
    HISTORY._skipPush = true;
    try { entry.undo(); } finally { HISTORY._skipPush = false; }
    HISTORY.cursor--;
    _syncHistoryUI();
    Toast.show('↩ Undo: ' + entry.label, 'info');
}

function historyRedo() {
    if (HISTORY.cursor >= HISTORY.stack.length - 1) { Toast.show('Nothing to redo', 'info'); return; }
    HISTORY.cursor++;
    const entry = HISTORY.stack[HISTORY.cursor];
    HISTORY._skipPush = true;
    try { entry.redo(); } finally { HISTORY._skipPush = false; }
    _syncHistoryUI();
    Toast.show('↪ Redo: ' + entry.label, 'info');
}

/** Reset history — called on dawClearAll and deserializeDawState */
function historyReset() {
    HISTORY.stack  = [];
    HISTORY.cursor = -1;
    _syncHistoryUI();
}

function _syncHistoryUI() {
    const u = document.getElementById('mbBtnUndo');
    const r = document.getElementById('mbBtnRedo');
    if (u) u.classList.toggle('active', HISTORY.cursor >= 0);
    if (r) r.classList.toggle('active', HISTORY.cursor < HISTORY.stack.length - 1);
}

/** Guard: don't record history when undo/redo replay is running */
function _historyGuard() { return !!HISTORY._skipPush; }
