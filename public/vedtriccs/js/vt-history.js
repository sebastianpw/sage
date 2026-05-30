// public/vedtriccs/js/vt-history.js
// Undo/Redo — VedTriccs Edition (VT_ namespace)

'use strict';

const VT_HISTORY = {
    stack:   [],
    cursor:  -1,
    maxSize: 100,
    _skipPush: false,
};

function vtHistoryPush(label, undoFn, redoFn) {
    if (VT_HISTORY._skipPush) return;
    if (VT_HISTORY.cursor < VT_HISTORY.stack.length - 1) {
        VT_HISTORY.stack.splice(VT_HISTORY.cursor + 1);
    }
    VT_HISTORY.stack.push({ label, undo: undoFn, redo: redoFn });
    if (VT_HISTORY.stack.length > VT_HISTORY.maxSize) VT_HISTORY.stack.shift();
    VT_HISTORY.cursor = VT_HISTORY.stack.length - 1;
    vtSyncHistoryUI();
}

function vtHistoryUndo() {
    if (VT_HISTORY.cursor < 0) { Toast.show('Nothing to undo', 'info'); return; }
    const entry = VT_HISTORY.stack[VT_HISTORY.cursor];
    VT_HISTORY._skipPush = true;
    try { entry.undo(); } finally { VT_HISTORY._skipPush = false; }
    VT_HISTORY.cursor--;
    vtSyncHistoryUI();
    Toast.show('↩ Undo: ' + entry.label, 'info');
}

function vtHistoryRedo() {
    if (VT_HISTORY.cursor >= VT_HISTORY.stack.length - 1) { Toast.show('Nothing to redo', 'info'); return; }
    VT_HISTORY.cursor++;
    const entry = VT_HISTORY.stack[VT_HISTORY.cursor];
    VT_HISTORY._skipPush = true;
    try { entry.redo(); } finally { VT_HISTORY._skipPush = false; }
    vtSyncHistoryUI();
    Toast.show('↪ Redo: ' + entry.label, 'info');
}

function vtHistoryReset() {
    VT_HISTORY.stack  = [];
    VT_HISTORY.cursor = -1;
    vtSyncHistoryUI();
}

function vtSyncHistoryUI() {
    document.getElementById('mbBtnUndo')?.classList.toggle('active', VT_HISTORY.cursor >= 0);
    document.getElementById('mbBtnRedo')?.classList.toggle('active', VT_HISTORY.cursor < VT_HISTORY.stack.length - 1);
}

function vtHistoryGuard() { return !!VT_HISTORY._skipPush; }
