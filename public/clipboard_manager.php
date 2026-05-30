<?php
// public/clipboard_manager.php
// Persistent Clipboard CRUD — designed to be loaded inside ie-modal iframe
// Supports view_area scoping + pinning + drag-to-reorder
// API actions consumed by this page and by other views (fetch from parent page)
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';

$viewArea = trim($_REQUEST['view_area'] ?? 'global');
if (!preg_match('/^[a-z0-9_]{1,80}$/', $viewArea)) $viewArea = 'global';

// ─── API HANDLER ─────────────────────────────────────────────────────────────
if (isset($_REQUEST['api_action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['api_action'];

    try {
        // ── GET items for a view_area (pinned first, then sort_order) ──────────
        if ($action === 'cb_get') {
            $area = trim($_GET['view_area'] ?? 'global');
            if (!preg_match('/^[a-z0-9_]{1,80}$/', $area)) $area = 'global';

            $sql = "SELECT ci.id, ci.content, ci.label, ci.created_at,
                           COALESCE(cv.pinned, 0)      AS pinned,
                           COALESCE(cv.sort_order, 0)  AS sort_order,
                           cv.id                       AS vis_id
                    FROM clipboard_items ci
                    LEFT JOIN clipboard_visibility cv
                           ON cv.clipboard_item_id = ci.id
                          AND cv.view_area = :area
                    ORDER BY pinned DESC, sort_order ASC, ci.id DESC";
            $st = $pdo->prepare($sql);
            $st->execute([':area' => $area]);
            echo json_encode(['status' => 'success', 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // ── ADD new item (creates item + visibility row for the area) ──────────
        if ($action === 'cb_add') {
            $input   = json_decode(file_get_contents('php://input'), true);
            $content = trim($input['content'] ?? '');
            $label   = trim($input['label']   ?? '');
            $area    = trim($input['view_area'] ?? 'global');
            if (!preg_match('/^[a-z0-9_]{1,80}$/', $area)) $area = 'global';
            if ($content === '') throw new Exception('Content is required.');

            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO clipboard_items (content, label) VALUES (?, ?)")
                ->execute([$content, $label]);
            $itemId = $pdo->lastInsertId();

            // Get max sort_order for this area
            $maxOrder = (int)$pdo->query(
                "SELECT COALESCE(MAX(sort_order),0) FROM clipboard_visibility WHERE view_area = " . $pdo->quote($area)
            )->fetchColumn();

            $pdo->prepare("INSERT INTO clipboard_visibility (clipboard_item_id, view_area, pinned, sort_order) VALUES (?,?,0,?)")
                ->execute([$itemId, $area, $maxOrder + 1]);
            $pdo->commit();

            echo json_encode(['status' => 'success', 'id' => $itemId]);
            exit;
        }

        // ── UPDATE item content / label ───────────────────────────────────────
        if ($action === 'cb_update') {
            $input   = json_decode(file_get_contents('php://input'), true);
            $id      = (int)($input['id'] ?? 0);
            $content = trim($input['content'] ?? '');
            $label   = trim($input['label']   ?? '');
            if (!$id) throw new Exception('Invalid id.');
            if ($content === '') throw new Exception('Content is required.');

            $pdo->prepare("UPDATE clipboard_items SET content=?, label=?, updated_at=NOW() WHERE id=?")
                ->execute([$content, $label, $id]);
            echo json_encode(['status' => 'success']);
            exit;
        }

        // ── DELETE item (cascade removes visibility rows) ─────────────────────
        if ($action === 'cb_delete') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id    = (int)($input['id'] ?? 0);
            if (!$id) throw new Exception('Invalid id.');
            $pdo->prepare("DELETE FROM clipboard_items WHERE id=?")->execute([$id]);
            echo json_encode(['status' => 'success']);
            exit;
        }

        // ── TOGGLE PIN for item in a view_area ────────────────────────────────
        if ($action === 'cb_pin') {
            $input  = json_decode(file_get_contents('php://input'), true);
            $id     = (int)($input['id'] ?? 0);
            $area   = trim($input['view_area'] ?? 'global');
            if (!preg_match('/^[a-z0-9_]{1,80}$/', $area)) $area = 'global';
            if (!$id) throw new Exception('Invalid id.');

            // Upsert visibility row
            $pdo->prepare("INSERT INTO clipboard_visibility (clipboard_item_id, view_area, pinned, sort_order)
                           VALUES (?, ?, 1, 0)
                           ON DUPLICATE KEY UPDATE pinned = IF(pinned=1, 0, 1)")
                ->execute([$id, $area]);
            $pinned = (int)$pdo->query(
                "SELECT pinned FROM clipboard_visibility WHERE clipboard_item_id=$id AND view_area=" . $pdo->quote($area)
            )->fetchColumn();
            echo json_encode(['status' => 'success', 'pinned' => $pinned]);
            exit;
        }

        // ── REORDER — accepts [{id, sort_order},...] for a view_area ──────────
        if ($action === 'cb_reorder') {
            $input  = json_decode(file_get_contents('php://input'), true);
            $area   = trim($input['view_area'] ?? 'global');
            $orders = $input['orders'] ?? []; // [{id: X, sort_order: Y}, ...]
            if (!preg_match('/^[a-z0-9_]{1,80}$/', $area)) $area = 'global';

            $pdo->beginTransaction();
            $st = $pdo->prepare("UPDATE clipboard_visibility SET sort_order=? WHERE clipboard_item_id=? AND view_area=?");
            foreach ($orders as $row) {
                $st->execute([(int)$row['sort_order'], (int)$row['id'], $area]);
            }
            $pdo->commit();
            echo json_encode(['status' => 'success']);
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    exit;
}
// ─── PAGE RENDER ─────────────────────────────────────────────────────────────
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Clipboard Manager</title>
<script>
    (function() {
        try {
            var theme = localStorage.getItem('spw_theme');
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else if (theme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        } catch (e) {}
    })();
</script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<!-- SortableJS for drag-to-reorder -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<style>
:root {
    --bg:      #0a0a0f;
    --card:    #111118;
    --border:  #1e1e2e;
    --text:    #e2e2f0;
    --muted:   #555570;
    --purple:  #8b5cf6;
    --amber:   #f59e0b;
    --teal:    #14b8a6;
    --red:     #ef4444;
    --green:   #22d3a0;
    --teal-dim: rgba(20,184,166,0.12);
    --amber-dim: rgba(245,158,11,0.1);
    --red-dim:   rgba(239,68,68,0.12);
    --green-dim: rgba(34,211,160,0.08);
}
[data-theme="light"] {
    --bg:      #f4f4f8;
    --card:    #ffffff;
    --border:  #d1d1e0;
    --text:    #1a1a2e;
    --muted:   #8888a8;
    --purple:  #7c3aed;
    --amber:   #d97706;
    --teal:    #0d9488;
    --red:     #dc2626;
    --green:   #059669;
    --teal-dim: rgba(13,148,136,0.10);
    --amber-dim: rgba(217,119,6,0.10);
    --red-dim:   rgba(220,38,38,0.10);
    --green-dim: rgba(5,150,105,0.08);
}
*, *::before, *::after { box-sizing: border-box; }
html, body {
    margin: 0; padding: 0;
    background: var(--bg); color: var(--text);
    font-family: 'DM Mono', 'Fira Mono', monospace;
    font-size: 14px; height: 100%; overflow: hidden;
}
.cb-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; }

/* ── HEADER ── */
.cb-header {
    flex-shrink: 0; padding: 10px 14px; background: var(--card);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
}
.cb-title { font-size: 0.8rem; font-weight: 700; color: var(--teal); letter-spacing: 1px; flex: 1; }
.cb-area-badge { font-size: 0.6rem; padding: 2px 8px; border-radius: 10px; border: 1px solid var(--border); color: var(--muted); }

/* ── ADD FORM ── */
.cb-add-area {
    flex-shrink: 0; padding: 10px 14px; background: var(--card);
    border-bottom: 1px solid var(--border); display: flex; flex-direction: column; gap: 6px;
}
.cb-add-row { display: flex; gap: 6px; }
.cb-input {
    flex: 1; padding: 7px 10px; border-radius: 4px;
    border: 1px solid var(--border); background: rgba(0,0,0,0.3);
    color: var(--text); font-family: inherit; font-size: 0.8rem; min-width: 0;
}
[data-theme="light"] .cb-input {
    background: rgba(0,0,0,0.04);
}
.cb-input:focus { outline: none; border-color: var(--teal); }
.cb-input.label-input { width: 100px; flex: none; }
.cb-add-btn {
    padding: 7px 13px; border-radius: 4px; border: none;
    background: var(--teal); color: #000; font-family: inherit;
    font-size: 0.75rem; font-weight: 700; cursor: pointer; white-space: nowrap;
    flex-shrink: 0; display: flex; align-items: center; gap: 4px;
}
.cb-add-btn:hover { filter: brightness(1.1); }

/* ── LIST ── */
.cb-list-wrap { flex: 1; overflow-y: auto; padding: 8px; }
.cb-list { display: flex; flex-direction: column; gap: 6px; list-style: none; margin: 0; padding: 0; }

/* ── ITEM ── */
.cb-item {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 6px; padding: 8px 10px;
    display: flex; align-items: flex-start; gap: 8px;
    transition: border-color 0.15s;
    cursor: default;
}
.cb-item.is-pinned { border-color: var(--amber); }
.cb-item.sortable-ghost { opacity: 0.35; }
.cb-item.sortable-chosen { border-color: var(--teal); box-shadow: 0 0 0 1px var(--teal); }

.drag-handle {
    flex-shrink: 0; cursor: grab; color: var(--muted); font-size: 1rem;
    padding-top: 2px; touch-action: none;
}
.drag-handle:active { cursor: grabbing; }

.item-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.item-label-row { display: flex; align-items: center; gap: 6px; }
.item-label { font-size: 0.6rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.8px; color: var(--muted); }
.item-label.has-label { color: var(--amber); }
.pin-badge { font-size: 0.55rem; color: var(--amber); letter-spacing: 0.5px; font-weight: 700; }

/* Inline editing */
.item-content-view {
    font-size: 0.75rem; color: var(--text); line-height: 1.4;
    word-break: break-word; cursor: pointer;
    padding: 2px 4px; border-radius: 3px;
    border: 1px solid transparent;
    transition: border-color 0.12s;
}
.item-content-view:hover { border-color: var(--border); }
.item-content-edit {
    display: none; width: 100%; padding: 4px 7px; border-radius: 3px;
    border: 1px solid var(--teal); background: rgba(0,0,0,0.3);
    color: var(--text); font-family: inherit; font-size: 0.75rem;
    resize: none; line-height: 1.4;
}
[data-theme="light"] .item-content-edit {
    background: rgba(0,0,0,0.04);
}
.item-content-edit.active { display: block; }
.item-content-view.editing { display: none; }

.item-label-input {
    display: none; width: 100%; padding: 3px 7px; border-radius: 3px;
    border: 1px solid var(--border); background: rgba(0,0,0,0.3);
    color: var(--muted); font-family: inherit; font-size: 0.65rem; margin-top: 2px;
}
[data-theme="light"] .item-label-input {
    background: rgba(0,0,0,0.04);
}
.item-label-input.active { display: block; }

/* Item action buttons */
.item-actions { display: flex; gap: 4px; flex-shrink: 0; align-items: flex-start; }
.ia-btn {
    width: 26px; height: 26px; border-radius: 4px; border: 1px solid var(--border);
    background: transparent; color: var(--muted); cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 13px;
    transition: all 0.12s; flex-shrink: 0;
}
.ia-btn:hover { color: var(--text); border-color: var(--text); }
.ia-btn.copy-btn:hover { color: var(--green); border-color: var(--green); background: var(--green-dim); }
.ia-btn.pin-btn:hover, .ia-btn.pin-btn.active { color: var(--amber); border-color: var(--amber); background: var(--amber-dim); }
.ia-btn.edit-btn:hover, .ia-btn.edit-btn.active { color: var(--teal); border-color: var(--teal); background: var(--teal-dim); }
.ia-btn.del-btn:hover { color: var(--red); border-color: var(--red); background: var(--red-dim); }
.ia-btn.save-btn { display: none; color: var(--teal); border-color: var(--teal); }
.ia-btn.save-btn.active { display: flex; }

/* ── EMPTY STATE ── */
.cb-empty { display: flex; align-items: center; justify-content: center;
    height: 120px; color: var(--muted); font-size: 0.75rem; font-style: italic; }

/* ── FOOTER ── */
.cb-footer {
    flex-shrink: 0; padding: 8px 14px; background: var(--card);
    border-top: 1px solid var(--border); font-size: 0.65rem; color: var(--muted);
    display: flex; align-items: center; justify-content: space-between;
}
.cb-hint { opacity: 0.6; }

/* ── TOAST (minimal inline) ── */
.cb-toast {
    position: fixed; bottom: 70px; left: 50%; transform: translateX(-50%);
    background: var(--teal); color: #000; padding: 6px 16px; border-radius: 20px;
    font-size: 0.72rem; font-weight: 700; pointer-events: none;
    opacity: 0; transition: opacity 0.2s; z-index: 9999; white-space: nowrap;
}
.cb-toast.err { background: var(--red); color: #fff; }
.cb-toast.show { opacity: 1; }
</style>
</head>
<body>
<div class="cb-layout">

    <div class="cb-header">
        <div class="cb-title"><i class="bi bi-clipboard2-fill"></i> Clipboard</div>
        <span class="cb-area-badge" id="areaBadge"><?php echo htmlspecialchars($viewArea); ?></span>
    </div>

    <div class="cb-add-area">
        <div class="cb-add-row">
            <input class="cb-input label-input" id="newLabel" placeholder="Label (opt.)" maxlength="120">
            <button class="cb-add-btn" onclick="addItem()">
                <i class="bi bi-plus-lg"></i> Add
            </button>
        </div>
        <div class="cb-add-row">
            <input class="cb-input" id="newContent" placeholder="Paste or type your text here…" maxlength="4000"
                   onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();addItem();}">
        </div>
    </div>

    <div class="cb-list-wrap">
        <ul class="cb-list" id="cbList">
            <li class="cb-empty">Loading…</li>
        </ul>
    </div>

    <div class="cb-footer">
        <span class="cb-hint">Drag <i class="bi bi-grip-vertical"></i> to reorder · tap text to edit</span>
        <span id="cbCount">0 items</span>
    </div>

</div>
<div class="cb-toast" id="cbToast"></div>

<script>
const VIEW_AREA = <?php echo json_encode($viewArea); ?>;
let items = [];
let sortable = null;

// ── TOAST ──────────────────────────────────────────────
let toastTimer;
function toast(msg, isErr) {
    const el = document.getElementById('cbToast');
    el.textContent = msg;
    el.className = 'cb-toast' + (isErr ? ' err' : '') + ' show';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('show'), 2200);
}

// ── API HELPERS ────────────────────────────────────────
async function api(action, body) {
    const res = await fetch('?api_action=' + action + '&view_area=' + encodeURIComponent(VIEW_AREA), {
        method: body ? 'POST' : 'GET',
        headers: body ? { 'Content-Type': 'application/json' } : {},
        body: body ? JSON.stringify(body) : undefined
    });
    return res.json();
}

// ── LOAD ───────────────────────────────────────────────
async function loadItems() {
    const res = await fetch('?api_action=cb_get&view_area=' + encodeURIComponent(VIEW_AREA));
    const data = await res.json();
    if (data.status !== 'success') { toast('Load failed', true); return; }
    items = data.data;
    renderList();
    notifyParent();
}

// ── RENDER ─────────────────────────────────────────────
function renderList() {
    const list = document.getElementById('cbList');
    list.innerHTML = '';
    if (!items.length) {
        list.innerHTML = '<li class="cb-empty">No items yet — add one above</li>';
        document.getElementById('cbCount').textContent = '0 items';
        return;
    }
    document.getElementById('cbCount').textContent = items.length + ' item' + (items.length !== 1 ? 's' : '');

    items.forEach(item => {
        const li = document.createElement('li');
        li.className = 'cb-item' + (parseInt(item.pinned) ? ' is-pinned' : '');
        li.dataset.id = item.id;

        const hasLabel = item.label && item.label.trim();
        li.innerHTML = `
            <div class="drag-handle"><i class="bi bi-grip-vertical"></i></div>
            <div class="item-body">
                <div class="item-label-row">
                    <span class="item-label ${hasLabel ? 'has-label' : ''}">${hasLabel ? escHtml(item.label) : 'unlabelled'}</span>
                    ${parseInt(item.pinned) ? '<span class="pin-badge">📌 pinned</span>' : ''}
                </div>
                <div class="item-content-view" onclick="startEdit(${item.id})">${escHtml(item.content)}</div>
                <textarea class="item-content-edit" rows="2" onblur="cancelEdit(${item.id})" onkeydown="editKeydown(event,${item.id})">${escHtml(item.content)}</textarea>
                <input class="item-label-input" type="text" value="${escHtml(item.label)}" placeholder="Label…" maxlength="120"
                       onblur="cancelEdit(${item.id})" onkeydown="editKeydown(event,${item.id})">
            </div>
            <div class="item-actions">
                <button class="ia-btn copy-btn" data-id="${item.id}" title="Copy to clipboard">
                    <i class="bi bi-clipboard"></i>
                </button>
                <button class="ia-btn pin-btn ${parseInt(item.pinned) ? 'active' : ''}" title="${parseInt(item.pinned) ? 'Unpin' : 'Pin'}" onclick="togglePin(${item.id})">
                    <i class="bi bi-pin${parseInt(item.pinned) ? '-fill' : ''}"></i>
                </button>
                <button class="ia-btn edit-btn" title="Edit" onclick="startEdit(${item.id})">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="ia-btn save-btn" id="saveBtn_${item.id}" title="Save" onclick="saveEdit(${item.id})">
                    <i class="bi bi-check-lg"></i>
                </button>
                <button class="ia-btn del-btn" title="Delete" onclick="deleteItem(${item.id})">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>`;
        list.appendChild(li);
    });

    initSortable();
}

// ── SORTABLE ───────────────────────────────────────────
function initSortable() {
    if (sortable) sortable.destroy();
    sortable = Sortable.create(document.getElementById('cbList'), {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        onEnd: saveOrder
    });
}

async function saveOrder() {
    const lis = document.querySelectorAll('#cbList .cb-item');
    const orders = Array.from(lis).map((li, i) => ({ id: parseInt(li.dataset.id), sort_order: i }));
    await api('cb_reorder', { view_area: VIEW_AREA, orders });
    // Reorder local items array to match DOM
    const idxMap = {};
    orders.forEach((o, i) => { idxMap[o.id] = i; });
    items.sort((a, b) => (idxMap[a.id] ?? 99) - (idxMap[b.id] ?? 99));
    notifyParent();
}

// ── ADD ────────────────────────────────────────────────
async function addItem() {
    const content = document.getElementById('newContent').value.trim();
    const label   = document.getElementById('newLabel').value.trim();
    if (!content) { document.getElementById('newContent').focus(); return; }
    const res = await api('cb_add', { content, label, view_area: VIEW_AREA });
    if (res.status !== 'success') { toast(res.message || 'Error', true); return; }
    document.getElementById('newContent').value = '';
    document.getElementById('newLabel').value = '';
    toast('Added ✓');
    await loadItems();
}

// ── EDIT ───────────────────────────────────────────────
function startEdit(id) {
    const li = document.querySelector(`.cb-item[data-id="${id}"]`);
    if (!li) return;
    li.querySelector('.item-content-view').classList.add('editing');
    li.querySelector('.item-content-edit').classList.add('active');
    li.querySelector('.item-label-input').classList.add('active');
    li.querySelector('.edit-btn').classList.add('active');
    const saveBtn = document.getElementById('saveBtn_' + id);
    if (saveBtn) saveBtn.classList.add('active');
    const ta = li.querySelector('.item-content-edit');
    ta.focus();
    ta.setSelectionRange(ta.value.length, ta.value.length);
}

function cancelEdit(id) {
    // Small timeout so clicks on save btn don't lose focus before firing
    setTimeout(() => {
        const li = document.querySelector(`.cb-item[data-id="${id}"]`);
        if (!li) return;
        // Only cancel if save btn not focused
        const active = document.activeElement;
        if (active && (active.classList.contains('save-btn') || active.closest(`.cb-item[data-id="${id}"]`))) return;
        li.querySelector('.item-content-view').classList.remove('editing');
        li.querySelector('.item-content-edit').classList.remove('active');
        li.querySelector('.item-label-input').classList.remove('active');
        li.querySelector('.edit-btn').classList.remove('active');
        const saveBtn = document.getElementById('saveBtn_' + id);
        if (saveBtn) saveBtn.classList.remove('active');
    }, 150);
}

function editKeydown(e, id) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); saveEdit(id); }
    if (e.key === 'Escape') { cancelEdit(id); }
}

async function saveEdit(id) {
    const li = document.querySelector(`.cb-item[data-id="${id}"]`);
    if (!li) return;
    const content = li.querySelector('.item-content-edit').value.trim();
    const label   = li.querySelector('.item-label-input').value.trim();
    if (!content) return;
    const res = await api('cb_update', { id, content, label });
    if (res.status !== 'success') { toast(res.message || 'Error', true); return; }
    // Update local data
    const item = items.find(i => i.id == id);
    if (item) { item.content = content; item.label = label; }
    toast('Saved ✓');
    renderList();
    notifyParent();
}

// ── PIN ────────────────────────────────────────────────
async function togglePin(id) {
    const res = await api('cb_pin', { id, view_area: VIEW_AREA });
    if (res.status !== 'success') { toast(res.message || 'Error', true); return; }
    const item = items.find(i => i.id == id);
    if (item) item.pinned = res.pinned;
    // Re-sort: pinned first
    items.sort((a, b) => (parseInt(b.pinned) - parseInt(a.pinned)));
    toast(res.pinned ? 'Pinned 📌' : 'Unpinned');
    renderList();
    notifyParent();
}

// ── DELETE ─────────────────────────────────────────────
async function deleteItem(id) {
    if (!confirm('Delete this clipboard item?')) return;
    const res = await api('cb_delete', { id });
    if (res.status !== 'success') { toast(res.message || 'Error', true); return; }
    items = items.filter(i => i.id != id);
    toast('Deleted');
    renderList();
    notifyParent();
}

// ── NOTIFY PARENT (chips reload) ──────────────────────
function notifyParent() {
    try {
        window.parent.postMessage({
            type: 'clipboard_updated',
            view_area: VIEW_AREA,
            items: items.map(i => ({ id: i.id, content: i.content, label: i.label, pinned: parseInt(i.pinned) }))
        }, '*');
    } catch(e) {}
}

// ── ESCAPE HTML ───────────────────────────────────────
function escHtml(s) {
    if (!s) return '';
    return s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── EVENT DELEGATION (COPY) ────────────────────────────
document.getElementById('cbList').addEventListener('click', e => {
    const copyBtn = e.target.closest('.copy-btn');
    if (copyBtn) {
        e.preventDefault();
        const id = copyBtn.dataset.id;
        const item = items.find(i => i.id == id);
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

// ── INIT ──────────────────────────────────────────────
loadItems();
</script>
</body>
</html>
