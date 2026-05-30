// public/ved/js/ved-history.js
// Undo / Redo history for structural VED actions.
// Excluded: volume, mute, zoom, scroll.
// Included: add track, remove track, add clip, remove clip, move clip, split clip.

'use strict';

const HISTORY = {
    stack:   [],
    cursor:  -1,
    maxSize: 100,
};

function historyPush(label, undoFn, redoFn) {
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

function _historyGuard() { return !!HISTORY._skipPush; }