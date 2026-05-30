<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$entityIcons = [
    'characters'               => '🦸',
    'character_poses'          => '🤸',
    'character_expressions'    => '🧑‍🎤',
    'animas'                   => '🐾',
    'locations'                => '🗺️',
    'backgrounds'              => '🏞️',
    'artifacts'                => '🏺',
    'vehicles'                 => '🛸',
    'scene_parts'              => '🎬',
    'controlnet_maps'          => '☠️',
    'spawns'                   => '🌱',
    'generatives'              => '⚡',
    'sketches'                 => '🪄',
    'prompt_matrix_blueprints' => '🌌',
    'composites'               => '🧩',
    'animatics'                => '🎥',
    'pastebin'                 => '📋',
    'sage_todos'               => '🎫',
    'meta_entities'            => '📦',
];

$allowedTables = array_keys($entityIcons);

// ── Helper: escape a value for SQL output ────────────────────────────────────
function sqlQuoteValue($v): string {
    if ($v === null) return 'NULL';
    if (is_int($v) || is_float($v)) return (string)$v;
    $v = str_replace(
        ['\\',   "\0",  "\n",  "\r",  "'",    '"',    "\x1a"],
        ['\\\\', '\\0', '\\n', '\\r', "\\'",  '\\"',  '\\Z'],
        $v
    );
    return "'" . $v . "'";
}

function sqlHeader(string $entity, int $rowCount, ?int $fromId, ?int $toId, string $mode): array {
    $nowUtc = gmdate('Y-m-d H:i:s');
    $lines  = [
        "-- SAGE AI — SQL Export ({$mode})",
        "-- Table   : `{$entity}`",
        "-- Exported: {$nowUtc} UTC",
    ];
    if ($fromId !== null || $toId !== null) {
        $lines[] = "-- ID range: " . ($fromId ?? '*') . ' - ' . ($toId ?? '*');
    }
    $lines[] = "-- Rows    : {$rowCount}";
    $lines[] = "";
    $lines[] = "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;";
    $lines[] = "/*!40101 SET NAMES utf8mb4 */;";
    $lines[] = "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;";
    $lines[] = "/*!40103 SET TIME_ZONE='+00:00' */;";
    $lines[] = "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;";
    $lines[] = "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;";
    $lines[] = "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;";
    $lines[] = "";
    return $lines;
}

function sqlFooter(): array {
    return [
        "",
        "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;",
        "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;",
        "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;",
        "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;",
    ];
}

// ── AJAX: column list for a given entity (called by JS fetch) ─────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'columns' && isset($_GET['entity'])) {
    $entity = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['entity']);
    header('Content-Type: application/json');
    if (!in_array($entity, $allowedTables, true)) {
        echo json_encode(['columns' => []]);
        exit;
    }
    $cols = array_column(
        $pdo->query("SHOW COLUMNS FROM `{$entity}`")->fetchAll(PDO::FETCH_ASSOC),
        'Field'
    );
    // id is always the WHERE — never a SET target
    $cols = array_values(array_filter($cols, fn($c) => $c !== 'id'));
    echo json_encode(['columns' => $cols]);
    exit;
}

// ── POST: generate and stream SQL file ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['entity'])) {

    $entity      = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['entity']);
    $mode        = ($_POST['mode'] ?? 'insert') === 'update' ? 'update' : 'insert';
    $fromId      = isset($_POST['from_id']) && $_POST['from_id'] !== '' ? (int)$_POST['from_id'] : null;
    $toId        = isset($_POST['to_id'])   && $_POST['to_id']   !== '' ? (int)$_POST['to_id']   : null;
    $transaction = !empty($_POST['wrap_transaction']);
    $createTable = !empty($_POST['include_create']);

    $updateCols = [];
    if ($mode === 'update' && !empty($_POST['update_cols']) && is_array($_POST['update_cols'])) {
        foreach ($_POST['update_cols'] as $c) {
            $c = preg_replace('/[^a-zA-Z0-9_]/', '', $c);
            if ($c !== '' && $c !== 'id') $updateCols[] = $c;
        }
        $updateCols = array_unique($updateCols);
    }

    if (!in_array($entity, $allowedTables, true)) die('Invalid entity.');
    if ($mode === 'update' && empty($updateCols))  die('UPDATE mode requires at least one column.');

    // Fetch all column names
    $allCols = array_column(
        $pdo->query("SHOW COLUMNS FROM `{$entity}`")->fetchAll(PDO::FETCH_ASSOC),
        'Field'
    );

    // Validate update columns exist
    if ($mode === 'update') {
        foreach ($updateCols as $uc) {
            if (!in_array($uc, $allCols, true)) die("Column `{$uc}` does not exist in `{$entity}`.");
        }
    }

    // Fetch rows
    $where  = [];
    $params = [];
    if ($fromId !== null) { $where[] = 'id >= ?'; $params[] = $fromId; }
    if ($toId   !== null) { $where[] = 'id <= ?'; $params[] = $toId;   }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    if ($mode === 'update') {
        $selectCols = '`id`, `' . implode('`, `', $updateCols) . '`';
    } else {
        $selectCols = '*';
    }

    $stmt = $pdo->prepare("SELECT {$selectCols} FROM `{$entity}` {$whereSql} ORDER BY id ASC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build SQL
    $lines = sqlHeader($entity, count($rows), $fromId, $toId, strtoupper($mode));

    if ($mode === 'insert' && $createTable) {
        $ctRow   = $pdo->query("SHOW CREATE TABLE `{$entity}`")->fetch(PDO::FETCH_NUM);
        $lines[] = "DROP TABLE IF EXISTS `{$entity}`;";
        $lines[] = $ctRow[1] . ";";
        $lines[] = "";
    }

    if ($transaction) { $lines[] = "START TRANSACTION;"; $lines[] = ""; }

    if (count($rows) === 0) {
        $lines[] = "-- No rows matched the given criteria.";
    } elseif ($mode === 'insert') {
        $colList    = '`' . implode('`, `', $allCols) . '`';
        $lines[]    = "INSERT INTO `{$entity}` ({$colList}) VALUES";
        $valueParts = [];
        foreach ($rows as $row) {
            $vals         = array_map('sqlQuoteValue', array_values($row));
            $valueParts[] = '  (' . implode(', ', $vals) . ')';
        }
        $lines[] = implode(",\n", $valueParts) . ";";
    } else {
        // One UPDATE per row
        foreach ($rows as $row) {
            $setParts = [];
            foreach ($updateCols as $col) {
                $setParts[] = "`{$col}` = " . sqlQuoteValue($row[$col] ?? null);
            }
            // Indent multi-column SET nicely
            $setClause = count($setParts) === 1
                ? $setParts[0]
                : "\n    " . implode(",\n    ", $setParts);
            $lines[]   = "UPDATE `{$entity}` SET {$setClause} WHERE `id` = " . (int)$row['id'] . ";";
        }
    }

    if ($transaction) { $lines[] = ""; $lines[] = "COMMIT;"; }
    foreach (sqlFooter() as $l) $lines[] = $l;

    $sql      = implode("\n", $lines);
    $suffix   = $mode === 'update'
        ? '_update_' . implode('-', $updateCols)
        : '_insert';
    $filename = $entity . $suffix . '_' . date('Ymd_His') . '.sql';

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql));
    echo $sql;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SQL Export — SAGE AI</title>
    <script>
    (function() {
        try {
            var t = localStorage.getItem('spw_theme');
            if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);
        } catch(e) {}
    })();
    </script>
    <link rel="stylesheet" href="/css/base.css">
    <style>
        .export-wrap {
            max-width: 680px;
            margin: 0 auto;
            padding: 24px 16px 56px;
        }
        .export-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }
        .export-header h1 { font-size: 1.4rem; font-weight: 700; margin: 0; }

        /* ── Tabs ────────────────────────────────────────────────────────── */
        .mode-tabs {
            display: flex;
            border-bottom: 2px solid var(--border-color, rgba(128,128,128,0.2));
            margin-bottom: 28px;
        }
        .mode-tab {
            padding: 10px 22px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            background: none;
            color: inherit;
            opacity: 0.5;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: opacity 0.15s, border-color 0.15s;
            white-space: nowrap;
            -webkit-tap-highlight-color: transparent;
        }
        .mode-tab:hover  { opacity: 0.8; }
        .mode-tab.active { opacity: 1; border-bottom-color: var(--accent, #6c8ebf); }

        .tab-panel         { display: none; }
        .tab-panel.active  { display: block; }

        /* ── Entity grid ─────────────────────────────────────────────────── */
        .entity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(118px, 1fr));
            gap: 8px;
            margin-bottom: 8px;
        }
        .entity-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 10px 6px;
            border: 2px solid transparent;
            border-radius: 10px;
            background: var(--card-bg, rgba(255,255,255,0.04));
            cursor: pointer;
            font-size: 0.74rem;
            font-weight: 500;
            transition: border-color 0.15s, background 0.15s;
            word-break: break-word;
            text-align: center;
            color: inherit;
            -webkit-tap-highlight-color: transparent;
        }
        .entity-btn .icon { font-size: 1.35rem; line-height: 1; }
        .entity-btn:hover  { border-color: var(--accent, #6c8ebf); background: var(--card-hover-bg, rgba(255,255,255,0.08)); }
        .entity-btn.active { border-color: var(--accent, #6c8ebf); background: var(--accent-subtle, rgba(108,142,191,0.18)); }

        /* ── Range row ───────────────────────────────────────────────────── */
        .range-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .range-row .form-group { margin-bottom: 0; }

        /* ── Options ─────────────────────────────────────────────────────── */
        .options-col { display: flex; flex-direction: column; gap: 10px; }

        /* ── Column picker ───────────────────────────────────────────────── */
        .col-picker-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 10px; }
        .col-picker-row  { display: flex; gap: 8px; align-items: center; }
        .col-picker-row select { flex: 1; min-width: 0; }
        .btn-remove-col {
            flex-shrink: 0;
            width: 34px; height: 34px;
            padding: 0;
            font-size: 1rem;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-add-col { display: inline-flex; align-items: center; gap: 6px; font-size: 0.82rem; }

        /* ── Hints ───────────────────────────────────────────────────────── */
        .field-hint-error {
            display: none;
            font-size: 0.82rem;
            color: var(--danger, #e05555);
            margin-top: 6px;
        }

        /* ── Footer ──────────────────────────────────────────────────────── */
        .export-footer {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 28px;
        }
        .selected-label { font-size: 0.82rem; opacity: 0.55; }

        .section + .section { margin-top: 4px; }
    </style>
</head>
<body>
<div class="export-wrap">

    <div class="export-header">
        <h1>🗄️ SQL Export</h1>
        <span class="badge badge-blue">SAGE AI</span>
    </div>

    <!-- ── Mode tabs ──────────────────────────────────────────────────────── -->
    <div class="mode-tabs" role="tablist">
        <button class="mode-tab active" role="tab" data-tab="insert" type="button">⬇️ INSERT Export</button>
        <button class="mode-tab"        role="tab" data-tab="update" type="button">✏️ UPDATE Export</button>
    </div>

    <form method="POST" action="" id="exportForm" autocomplete="off">
        <input type="hidden" name="mode"   id="modeInput"   value="insert">
        <input type="hidden" name="entity" id="entityInput" value="">

        <!-- ── 1: Entity (shared) ─────────────────────────────────────── -->
        <div class="section">
            <h2 class="section-header">1 · Select Entity</h2>
            <div class="entity-grid" id="entityGrid">
                <?php foreach ($entityIcons as $table => $icon): ?>
                <button type="button" class="entity-btn" data-entity="<?= htmlspecialchars($table) ?>">
                    <span class="icon"><?= $icon ?></span>
                    <span><?= htmlspecialchars($table) ?></span>
                </button>
                <?php endforeach; ?>
            </div>
            <div class="field-hint-error" id="noEntityHint">⚠ Please select an entity.</div>
        </div>

        <!-- ── 2: ID Range (shared) ───────────────────────────────────── -->
        <div class="section">
            <h2 class="section-header">2 · ID Range
                <span style="font-weight:400;font-size:0.82rem;opacity:0.5">(leave blank = all rows)</span>
            </h2>
            <div class="range-row">
                <div class="form-group">
                    <label class="form-label" for="from_id">From ID</label>
                    <input type="number" id="from_id" name="from_id" class="form-control" placeholder="e.g. 1" min="1">
                </div>
                <div class="form-group">
                    <label class="form-label" for="to_id">To ID</label>
                    <input type="number" id="to_id" name="to_id" class="form-control" placeholder="e.g. 500" min="1">
                </div>
            </div>
        </div>

        <!-- ══ INSERT panel ════════════════════════════════════════════════ -->
        <div class="tab-panel active" id="panel-insert">
            <div class="section">
                <h2 class="section-header">3 · Options</h2>
                <div class="options-col">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="wrap_tx_insert" value="1">
                        <label for="wrap_tx_insert">Wrap in transaction
                            <span style="opacity:0.45;font-size:0.78rem">(START TRANSACTION … COMMIT)</span>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="include_create" name="include_create" value="1">
                        <label for="include_create">Include CREATE TABLE
                            <span style="opacity:0.45;font-size:0.78rem">(+ DROP TABLE IF EXISTS)</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ UPDATE panel ════════════════════════════════════════════════ -->
        <div class="tab-panel" id="panel-update">
            <div class="section">
                <h2 class="section-header">3 · Columns to UPDATE</h2>
                <p style="font-size:0.82rem;opacity:0.55;margin:0 0 12px">
                    Select an entity first — columns load automatically.<br>
                    Each row becomes: <code>UPDATE … SET col = val WHERE id = ?</code>
                </p>
                <div class="col-picker-list" id="colPickerList">
                    <div id="colPickerPlaceholder" style="font-size:0.8rem;opacity:0.45;padding:2px 0">
                        Select an entity above to load its columns.
                    </div>
                </div>
                <button type="button" class="btn btn-secondary btn-sm btn-add-col" id="btnAddCol" disabled>
                    ＋ Add column
                </button>
                <div class="field-hint-error" id="noColHint">⚠ Add at least one column to update.</div>
            </div>
            <div class="section">
                <h2 class="section-header">4 · Options</h2>
                <div class="options-col">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="wrap_tx_update" value="1">
                        <label for="wrap_tx_update">Wrap in transaction
                            <span style="opacity:0.45;font-size:0.78rem">(START TRANSACTION … COMMIT)</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actual hidden wrap_transaction input — synced by JS -->
        <input type="hidden" name="wrap_transaction" id="wrapTxHidden" value="0">

        <!-- ── Submit ─────────────────────────────────────────────────── -->
        <div class="export-footer">
            <button type="submit" class="btn btn-primary">⬇️ Download SQL</button>
            <span class="selected-label" id="selectedLabel">No entity selected</span>
        </div>
    </form>
</div>

<div id="toastContainer" class="toast-container"></div>

<script>
(function () {
    'use strict';

    /* ── state ──────────────────────────────────────────────────────────── */
    let currentMode   = 'insert';
    let currentEntity = '';
    let entityColumns = [];

    /* ── refs ───────────────────────────────────────────────────────────── */
    const modeInput         = document.getElementById('modeInput');
    const entityInput       = document.getElementById('entityInput');
    const selectedLabel     = document.getElementById('selectedLabel');
    const noEntityHint      = document.getElementById('noEntityHint');
    const noColHint         = document.getElementById('noColHint');
    const exportForm        = document.getElementById('exportForm');
    const entityBtns        = document.querySelectorAll('.entity-btn');
    const colPickerList     = document.getElementById('colPickerList');
    const colPickerPH       = document.getElementById('colPickerPlaceholder');
    const btnAddCol         = document.getElementById('btnAddCol');
    const tabs              = document.querySelectorAll('.mode-tab');
    const panels            = document.querySelectorAll('.tab-panel');
    const wrapTxInsert      = document.getElementById('wrap_tx_insert');
    const wrapTxUpdate      = document.getElementById('wrap_tx_update');
    const wrapTxHidden      = document.getElementById('wrapTxHidden');
    const toastContainer    = document.getElementById('toastContainer');

    /* ── sync the hidden wrap_transaction value ─────────────────────────── */
    function syncTx() {
        const active = currentMode === 'insert' ? wrapTxInsert : wrapTxUpdate;
        wrapTxHidden.value = active.checked ? '1' : '0';
    }
    wrapTxInsert.addEventListener('change', function() {
        wrapTxUpdate.checked = this.checked; syncTx();
    });
    wrapTxUpdate.addEventListener('change', function() {
        wrapTxInsert.checked = this.checked; syncTx();
    });

    /* ── tab switching ──────────────────────────────────────────────────── */
    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            currentMode = this.dataset.tab;
            modeInput.value = currentMode;
            tabs.forEach(t   => t.classList.toggle('active', t === this));
            panels.forEach(p => p.classList.toggle('active', p.id === 'panel-' + currentMode));
        });
    });

    /* ── entity selection ───────────────────────────────────────────────── */
    entityBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const name = this.dataset.entity;
            currentEntity = name;
            entityInput.value = name;
            selectedLabel.textContent = 'Selected: ' + name;
            noEntityHint.style.display = 'none';
            entityBtns.forEach(b => b.classList.toggle('active', b === this));
            loadColumns(name);
        });
    });

    /* ── AJAX column loader ─────────────────────────────────────────────── */
    function loadColumns(entity) {
        btnAddCol.disabled = true;
        entityColumns = [];
        // Clear existing rows, show loading
        Array.from(colPickerList.querySelectorAll('.col-picker-row')).forEach(r => r.remove());
        if (colPickerPH) {
            colPickerPH.textContent = 'Loading columns…';
            colPickerPH.style.display = '';
        }

        fetch('?ajax=columns&entity=' + encodeURIComponent(entity))
            .then(r => r.json())
            .then(data => {
                entityColumns = data.columns || [];
                if (colPickerPH) colPickerPH.style.display = 'none';
                if (entityColumns.length > 0) {
                    btnAddCol.disabled = false;
                    addColRow(); // start with one selector
                } else {
                    if (colPickerPH) {
                        colPickerPH.textContent = 'No columns found.';
                        colPickerPH.style.display = '';
                    }
                }
            })
            .catch(() => {
                if (colPickerPH) {
                    colPickerPH.style.color = 'var(--danger, #e05)';
                    colPickerPH.textContent = 'Failed to load columns.';
                    colPickerPH.style.display = '';
                }
            });
    }

    /* ── add a column picker row ────────────────────────────────────────── */
    function addColRow() {
        const row = document.createElement('div');
        row.className = 'col-picker-row';

        const sel = document.createElement('select');
        sel.className = 'form-control';
        sel.name = 'update_cols[]';
        entityColumns.forEach(col => {
            const opt = document.createElement('option');
            opt.value = col;
            opt.textContent = col;
            sel.appendChild(opt);
        });

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-danger btn-sm btn-remove-col';
        removeBtn.innerHTML = '✕';
        removeBtn.title = 'Remove';
        removeBtn.addEventListener('click', function () {
            const rows = colPickerList.querySelectorAll('.col-picker-row');
            if (rows.length > 1) {
                row.remove();
            } else {
                showToast('At least one column is required.', 'error');
            }
        });

        row.appendChild(sel);
        row.appendChild(removeBtn);
        colPickerList.appendChild(row);
        noColHint.style.display = 'none';
    }

    btnAddCol.addEventListener('click', addColRow);

    /* ── form validation ────────────────────────────────────────────────── */
    exportForm.addEventListener('submit', function (e) {
        syncTx();
        let valid = true;

        if (!currentEntity) {
            noEntityHint.style.display = 'block';
            noEntityHint.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            valid = false;
        }

        if (currentMode === 'update') {
            const colRows = colPickerList.querySelectorAll('.col-picker-row');
            if (colRows.length === 0) {
                noColHint.style.display = 'block';
                noColHint.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                valid = false;
            }
        }

        if (!valid) e.preventDefault();
    });

    /* ── toast ──────────────────────────────────────────────────────────── */
    function showToast(msg, type) {
        const t = document.createElement('div');
        t.className = 'toast toast-' + (type || 'success');
        t.textContent = msg;
        toastContainer.appendChild(t);
        setTimeout(() => t.classList.add('show'), 10);
        setTimeout(() => {
            t.classList.remove('show');
            t.addEventListener('transitionend', () => t.remove(), { once: true });
        }, 3000);
    }

})();
</script>
</body>
</html>
