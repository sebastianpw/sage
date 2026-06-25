<?php
// public/view_db_migration_admin.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();
$fileLogger = $spw->getFileLogger();

$pageTitle = "Database Migration Manager";
ob_start();

// Get available databases
$databases = [];
try {
    $stmt = $pdo->query("SHOW DATABASES");
    while ($row = $stmt->fetch(PDO::FETCH_COLUMN)) {
        if (!in_array($row, ['information_schema', 'mysql', 'performance_schema', 'sys'])) {
            $databases[] = $row;
        }
    }
} catch (Exception $e) {
    if ($fileLogger) {
        $fileLogger->error(['Failed to list databases' => $e->getMessage()]);
    }
}

// Current configured database
$currentDb = $spw->getDbName();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
/* ── BEAP Inspired Theme & Variables ── */
:root,[data-theme="dark"]{
    --bp-bg:#080b10;--bp-surface:#0e1319;--bp-card:#111820;--bp-border:#1c2535;
    --bp-text:#c8d4e8;--bp-dim:#5a6a80;--bp-amber:#f5a623;--bp-teal:#3ab5c8;
    --bp-purple:#9b72e0;--bp-green:#3ab87f;--bp-red:#f66;
}
[data-theme="light"]{
    --bp-bg:#f4f6fa;--bp-surface:#fff;--bp-card:#fff;--bp-border:#d0d8e8;
    --bp-text:#1a2233;--bp-dim:#7a8aaa;--bp-amber:#c8880a;--bp-teal:#1a8090;
    --bp-purple:#7040c0;--bp-green:#1a8060;--bp-red:#d44;
}
body{background:var(--bp-bg);color:var(--bp-text);font-family:'Syne',system-ui,sans-serif;margin:0;padding:0;}

.bp-nav{display:flex;align-items:center;gap:10px;padding:10px 16px;background:rgba(0,0,0,.6);border-bottom:1px solid var(--bp-border);position:sticky;top:0;z-index:100;backdrop-filter:blur(6px);flex-wrap:wrap;}
[data-theme="light"] .bp-nav{background:rgba(244,246,250,.92);}
.bp-nav-title{font-family:'Space Mono',monospace;font-size:.85rem;font-weight:bold;color:var(--bp-purple);letter-spacing:1px;text-transform:uppercase;margin-right:auto;}

.bp-btn{padding:7px 14px;border-radius:4px;border:1px solid;font-family:'Space Mono',monospace;font-size:.75rem;cursor:pointer;transition:all .15s;white-space:nowrap;background:var(--bp-card);color:var(--bp-dim);border-color:var(--bp-border);}
.bp-btn:hover:not(:disabled){color:var(--bp-teal);border-color:var(--bp-teal);}
.bp-btn-teal{border-color:var(--bp-teal);background:var(--bp-teal);color:#000;font-weight:bold;}
.bp-btn-teal:hover:not(:disabled){filter:brightness(1.1);}
.bp-btn-amber{border-color:var(--bp-amber);background:var(--bp-amber);color:#000;font-weight:bold;}
.bp-btn-amber:hover:not(:disabled){filter:brightness(1.1);}
.bp-btn-purple{border-color:var(--bp-purple);background:var(--bp-purple);color:#fff;font-weight:bold;}
.bp-btn-purple:hover:not(:disabled){filter:brightness(1.1);}
.bp-btn-sm{padding:4px 8px;font-size:.65rem;}
.bp-btn:disabled{opacity:.45;cursor:not-allowed;}

.bp-input{width:100%;box-sizing:border-box;background:var(--bp-card);color:var(--bp-text);border:1px solid var(--bp-border);border-radius:4px;padding:8px 12px;font-family:'Syne',sans-serif;font-size:.85rem;outline:none;transition:border-color .2s;}
.bp-input:focus{border-color:var(--bp-teal);}

.bp-workspace{max-width:960px;margin:0 auto;padding:24px 15px 100px;}

.bp-tabs{display:flex;gap:0;border-bottom:2px solid var(--bp-border);margin-bottom:20px;}
.bp-tab{padding:8px 16px;font-family:'Space Mono',monospace;font-size:.72rem;text-transform:uppercase;letter-spacing:1px;cursor:pointer;color:var(--bp-dim);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;}
.bp-tab.active{color:var(--bp-purple);border-bottom-color:var(--bp-purple);}
.bp-tab:hover:not(.active){color:var(--bp-text);}
.bp-tab-pane{display:none;}
.bp-tab-pane.active{display:block;}

.bp-card{background:var(--bp-surface);border:1px solid var(--bp-border);border-radius:6px;padding:14px;margin-bottom:20px;display:flex;flex-direction:column;gap:12px;}
.bp-card-header{font-family:'Space Mono',monospace;font-size:.85rem;font-weight:bold;color:var(--bp-text);margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;}
.bp-card-header-badge { font-size: 0.7rem; color: var(--bp-purple); background: rgba(155,114,224,0.15); padding: 2px 8px; border-radius: 12px; }

.field-label{font-family:'Space Mono',monospace;font-size:.65rem;text-transform:uppercase;letter-spacing:1px;color:var(--bp-dim);margin-bottom:4px;}

/* ── Custom UI for Migration Tool ── */
.diff-section { margin-bottom:12px; border:1px solid var(--bp-border); border-radius:4px; overflow:hidden;}
.diff-header {
    display:flex; justify-content:space-between; align-items:center;
    padding:8px 12px; background:var(--bp-card); cursor:pointer;
    font-size:.85rem; font-weight:bold; transition: background 0.2s;
}
.diff-header:hover { background: rgba(58,181,200,.07); }
.diff-body { padding:12px; background:var(--bp-surface); display:none; border-top:1px solid var(--bp-border); font-size:.8rem; }
.diff-body.show { display:block; }

.sql-preview {
    background:var(--bp-bg); padding:12px; border-radius:4px; font-family:monospace; font-size:.75rem;
    white-space:pre-wrap; word-wrap:break-word; max-height:400px; overflow:auto;
    border: 1px solid var(--bp-border); color: var(--bp-dim); margin-top:8px;
}

.migration-step { padding:12px; border-left:3px solid var(--bp-teal); margin-bottom:8px; background:var(--bp-card); border-radius:0 4px 4px 0; border:1px solid var(--bp-border); border-left-width:3px; }
.migration-step.safe { border-left-color: var(--bp-green); }
.migration-step.warning { border-left-color: var(--bp-amber); }
.migration-step.danger { border-left-color: var(--bp-red); }

.bp-spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:bpspin .6s linear infinite;vertical-align:middle;margin-right:5px;}
@keyframes bpspin{to{transform:rotate(360deg)}}
</style>

<div class="bp-nav" style="padding-left:70px;">
    <span class="bp-nav-title">💾 Database Migration Manager</span>
</div>

<div class="bp-workspace">
    <p style="font-size:0.85rem; color:var(--bp-dim); margin-bottom: 20px;">
        Synchronize schemas between parallel database instances or manage GitHub rollout SQL baselines.
        Current database: <strong style="color:var(--bp-text);"><?= htmlspecialchars($currentDb) ?></strong>
    </p>

    <div class="bp-tabs">
        <div class="bp-tab active" onclick="switchTab('parallel')">Parallel Sync</div>
        <div class="bp-tab" onclick="switchTab('full')">Full Rollout SQL</div>
        <div class="bp-tab" onclick="switchTab('update')">Update Rollout SQL</div>
    </div>

    <!-- ==================== PARALLEL SYNC ==================== -->
    <div class="bp-tab-pane active" id="pane-parallel">
        <div class="bp-card">
            <div class="bp-card-header">Select Databases</div>
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px;">
                    <div class="field-label">Source (Leading)</div>
                    <select id="sourceDb" class="bp-input">
                        <option value="">-- Select Source --</option>
                        <?php foreach ($databases as $db): ?>
                            <option value="<?= htmlspecialchars($db) ?>" <?= $db === $currentDb ? 'selected' : '' ?>>
                                <?= htmlspecialchars($db) ?><?= $db === $currentDb ? ' (current)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1; min-width:200px;">
                    <div class="field-label">Target (To Update)</div>
                    <select id="targetDb" class="bp-input">
                        <option value="">-- Select Target --</option>
                        <?php foreach ($databases as $db): ?>
                            <option value="<?= htmlspecialchars($db) ?>">
                                <?= htmlspecialchars($db) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="margin-top:8px;">
                <button id="btnAnalyze" class="bp-btn bp-btn-teal" onclick="analyzeParallelSync()">Analyze Differences</button>
                <button class="bp-btn" onclick="resetParallel()">Reset</button>
            </div>
        </div>

        <div id="parallelResults" class="bp-card" style="display:none;">
            <div class="bp-card-header">
                Comparison Results
                <span id="analysisStats" class="bp-card-header-badge"></span>
            </div>
            <div id="analysisContent"></div>
            <div style="margin-top:12px;">
                <button id="btnGenerateSql" class="bp-btn bp-btn-purple" onclick="generateMigrationSql()">Generate Migration SQL</button>
            </div>
        </div>

        <div id="migrationPlan" class="bp-card" style="display:none;">
            <div class="bp-card-header">
                Migration Plan
                <span id="planStats" class="bp-card-header-badge"></span>
            </div>
            <div id="migrationSteps"></div>
            
            <div style="margin-top:12px; display:flex; gap:12px; align-items:center;">
                <label style="font-size:0.85rem; display:flex; align-items:center; gap:6px; cursor:pointer;">
                    <input type="checkbox" id="dryRun" checked style="accent-color:var(--bp-purple); width:16px; height:16px;">
                    Dry run (preview only, don't execute)
                </label>
            </div>
            
            <div style="margin-top:12px; display:flex; gap:8px;">
                <button id="btnExecute" class="bp-btn bp-btn-amber" onclick="executeMigration()">Execute Migration</button>
                <button id="btnDownloadSql" class="bp-btn" onclick="downloadParallelSql()"><i class="bi bi-download"></i> Download SQL</button>
            </div>
        </div>

        <div id="executionResults" class="bp-card" style="display:none;">
            <div class="bp-card-header">Execution Results</div>
            <div id="executionContent"></div>
        </div>
    </div>

    <!-- ==================== FULL ROLLOUT SQL ==================== -->
    <div class="bp-tab-pane" id="pane-full">
        <div class="bp-card">
            <div class="bp-card-header">Generate Baseline SQL</div>
            <p style="font-size:0.8rem; color:var(--bp-dim); margin-top:0;">Generate a complete database structure SQL script suitable for an initial GitHub rollout. Data is omitted.</p>
            <div>
                <div class="field-label">Target Database</div>
                <select id="fullRolloutDb" class="bp-input" style="max-width:300px;">
                    <option value="">-- Select Database --</option>
                    <?php foreach ($databases as $db): ?>
                        <option value="<?= htmlspecialchars($db) ?>" <?= $db === $currentDb ? 'selected' : '' ?>>
                            <?= htmlspecialchars($db) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-top:12px;">
                <button id="btnGenFull" class="bp-btn bp-btn-teal" onclick="generateFullRollout()">Generate Full SQL</button>
            </div>
        </div>

        <div id="fullRolloutResults" class="bp-card" style="display:none;">
            <div class="bp-card-header">Generated SQL</div>
            <textarea id="fullRolloutText" class="bp-input" style="height:350px; font-family:monospace; font-size:0.75rem; white-space:pre; resize:vertical;" readonly></textarea>
            <div style="margin-top:12px;">
                <button class="bp-btn bp-btn-purple" onclick="downloadFullRollout()"><i class="bi bi-download"></i> Download SQL File</button>
            </div>
        </div>
    </div>


    <!-- ==================== UPDATE ROLLOUT SQL ==================== -->
    <div class="bp-tab-pane" id="pane-update">
        <div class="bp-card">
            <div class="bp-card-header">Incremental Update SQL</div>
            <p style="font-size:0.8rem; color:var(--bp-dim); margin-top:0;">Compare your staging database (baseline) against a live database (new changes) to generate an incremental patch.</p>
            
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px;">
                    <div class="field-label">Source Database (Live / New changes)</div>
                    <select id="updateLiveDb" class="bp-input">
                        <option value="">-- Select Source Database --</option>
                        <?php foreach ($databases as $db): ?>
                            <option value="<?= htmlspecialchars($db) ?>" <?= $db === $currentDb ? 'selected' : '' ?>>
                                <?= htmlspecialchars($db) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1; min-width:200px;">
                    <div class="field-label">Target Database (Staging / Baseline)</div>
                    <select id="updateBaselineDb" class="bp-input">
                        <option value="">-- Select Target Database --</option>
                        <?php foreach ($databases as $db): ?>
                            <option value="<?= htmlspecialchars($db) ?>">
                                <?= htmlspecialchars($db) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div style="margin-top:12px;">
                <button id="btnGenUpdate" class="bp-btn bp-btn-teal" onclick="generateUpdateRollout()">Compare & Generate Update</button>
            </div>
        </div>

        <div id="updateRolloutResults" class="bp-card" style="display:none;">
            <div class="bp-card-header">Update SQL Generated</div>
            <textarea id="updateRolloutText" class="bp-input" style="height:350px; font-family:monospace; font-size:0.75rem; white-space:pre; resize:vertical;" readonly></textarea>
            <div style="margin-top:12px;">
                <button class="bp-btn bp-btn-purple" onclick="downloadUpdateRollout()"><i class="bi bi-download"></i> Download Update SQL</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>
<script>
// ── TAB SWITCHING ────────────────────────────────────────────────────────
function switchTab(tab) {
    document.querySelectorAll('.bp-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.bp-tab-pane').forEach(p => p.classList.remove('active'));
    
    const tabHeaders = document.querySelectorAll('.bp-tab');
    if (tab === 'parallel') tabHeaders[0].classList.add('active');
    else if (tab === 'full') tabHeaders[1].classList.add('active');
    else if (tab === 'update') tabHeaders[2].classList.add('active');

    document.getElementById('pane-' + tab).classList.add('active');
}

// ── UTILITIES ────────────────────────────────────────────────────────────
function downloadBlob(text, filename) {
    const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

// ── PARALLEL SYNC LOGIC ──────────────────────────────────────────────────
let currentComparison = null;
let currentStatements = null;

function resetParallel() {
    currentComparison = null;
    currentStatements = null;
    document.getElementById('parallelResults').style.display = 'none';
    document.getElementById('migrationPlan').style.display = 'none';
    document.getElementById('executionResults').style.display = 'none';
}

function analyzeParallelSync() {
    const sourceDb = document.getElementById('sourceDb').value;
    const targetDb = document.getElementById('targetDb').value;
    if (!sourceDb || !targetDb) return Toast.show('Select both source and target databases', 'warn');
    if (sourceDb === targetDb) return Toast.show('Source and target must differ', 'warn');

    const btn = document.getElementById('btnAnalyze');
    const origHTML = btn.innerHTML;
    btn.innerHTML = '<span class="bp-spinner"></span>Analyzing...';
    btn.disabled = true;

    fetch('db_migration_api.php?action=compare_schemas', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ source_db: sourceDb, target_db: targetDb })
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = origHTML;
        btn.disabled = false;
        if (data.status === 'ok') {
            currentComparison = data;
            displayComparisonResults(data);
        } else {
            Toast.show('Analysis failed: ' + (data.message || 'Error'), 'error');
        }
    })
    .catch(err => {
        btn.innerHTML = origHTML; btn.disabled = false;
        Toast.show('Network error during analysis', 'error');
    });
}

function displayComparisonResults(data) {
    document.getElementById('parallelResults').style.display = 'flex';
    document.getElementById('migrationPlan').style.display = 'none';
    document.getElementById('executionResults').style.display = 'none';
    
    const diffs = data.differences || {};
    const total = (diffs.missing_tables?.length||0) + (diffs.missing_views?.length||0) + 
                  (diffs.altered_views?.length||0) + (diffs.missing_columns?.length||0) + 
                  (diffs.column_changes?.length||0) + (diffs.missing_indexes?.length||0) + 
                  (diffs.missing_constraints?.length||0);
    
    document.getElementById('analysisStats').textContent = total + ' changes';
    const content = document.getElementById('analysisContent');
    
    if (total === 0) {
        content.innerHTML = '<div style="color:var(--bp-dim); font-size:0.85rem;">Databases are completely in sync!</div>';
        document.getElementById('btnGenerateSql').style.display = 'none';
        return;
    }
    
    document.getElementById('btnGenerateSql').style.display = '';

    let html = '';
    if (diffs.missing_tables?.length > 0) html += makeDiffSec('Missing Tables', diffs.missing_tables.length, diffs.missing_tables.map(t => `<div>• ${esc(t)}</div>`).join(''));
    if (diffs.missing_views?.length > 0) html += makeDiffSec('Missing Views', diffs.missing_views.length, diffs.missing_views.map(v => `<div>• ${esc(v)}</div>`).join(''));
    if (diffs.altered_views?.length > 0) html += makeDiffSec('Altered Views', diffs.altered_views.length, diffs.altered_views.map(v => `<div>• ${esc(v.view)}</div>`).join(''));
    if (diffs.missing_columns?.length > 0) html += makeDiffSec('Missing Columns', diffs.missing_columns.length, diffs.missing_columns.map(c => `<div>• ${esc(c.table)}: ${esc(c.column.COLUMN_NAME)} (${esc(c.column.COLUMN_TYPE)})</div>`).join(''));
    if (diffs.column_changes?.length > 0) html += makeDiffSec('Modified Columns', diffs.column_changes.length, diffs.column_changes.map(c => `<div>• ${esc(c.table)}.${esc(c.column)}</div>`).join(''));
    if (diffs.missing_indexes?.length > 0) html += makeDiffSec('Missing Indexes', diffs.missing_indexes.length, diffs.missing_indexes.map(i => `<div>• ${esc(i.table)}: ${esc(i.index.Key_name)}</div>`).join(''));
    if (diffs.missing_constraints?.length > 0) html += makeDiffSec('Missing Foreign Keys', diffs.missing_constraints.length, diffs.missing_constraints.map(fk => `<div>• ${esc(fk.table)}: ${esc(fk.constraint.CONSTRAINT_NAME)}</div>`).join(''));

    content.innerHTML = html;
}

function makeDiffSec(title, count, inner) {
    return `
      <div class="diff-section">
        <div class="diff-header" onclick="this.nextElementSibling.classList.toggle('show')">
          <span>${esc(title)} <span class="bp-card-header-badge" style="margin-left:8px;">${count}</span></span>
          <span style="color:var(--bp-dim);">▼</span>
        </div>
        <div class="diff-body">${inner}</div>
      </div>
    `;
}

function generateMigrationSql() {
    if (!currentComparison) return;
    const btn = document.getElementById('btnGenerateSql');
    const origHTML = btn.innerHTML;
    btn.innerHTML = '<span class="bp-spinner"></span>Generating...';
    btn.disabled = true;

    fetch('db_migration_api.php?action=generate_migration', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ comparison: currentComparison })
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = origHTML; btn.disabled = false;
        if (data.status === 'ok') {
            currentStatements = data.statements;
            displayMigrationPlan();
            document.getElementById('migrationPlan').scrollIntoView({ behavior: 'smooth' });
        } else {
            Toast.show('Failed: ' + (data.message || 'Error'), 'error');
        }
    })
    .catch(err => { btn.innerHTML = origHTML; btn.disabled = false; Toast.show('Network error', 'error'); });
}

function displayMigrationPlan() {
    const plan = document.getElementById('migrationPlan');
    plan.style.display = 'flex';
    document.getElementById('planStats').textContent = currentStatements.length + ' steps';
    
    let html = '';
    currentStatements.forEach((stmt, idx) => {
        const cls = stmt.safe ? 'safe' : (stmt.warning ? 'warning' : 'danger');
        html += `
        <div class="migration-step ${cls}">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <strong style="font-size:0.85rem;">${idx+1}. ${esc(stmt.type)}</strong>
                <span class="bp-card-header-badge" style="background:${stmt.safe?'rgba(58,184,127,0.15)':'rgba(245,166,35,0.15)'}; color:${stmt.safe?'var(--bp-green)':'var(--bp-amber)'};">${stmt.safe?'Safe':'Caution'}</span>
            </div>
            <div style="font-size:0.75rem; color:var(--bp-dim); margin:4px 0;">Table: <strong style="color:var(--bp-text);">${esc(stmt.table||'N/A')}</strong></div>
            ${stmt.warning ? `<div style="font-size:0.75rem; color:var(--bp-amber); margin-bottom:4px;">⚠️ ${esc(stmt.warning)}</div>` : ''}
            <div class="sql-preview">${esc(stmt.sql)}</div>
        </div>`;
    });
    document.getElementById('migrationSteps').innerHTML = html;
}

function executeMigration() {
    if (!currentStatements || currentStatements.length === 0) return;
    const dryRun = document.getElementById('dryRun').checked;
    if (!dryRun && !confirm('This will modify the target database. Are you absolutely sure?')) return;
    
    const btn = document.getElementById('btnExecute');
    const origHTML = btn.innerHTML;
    btn.innerHTML = '<span class="bp-spinner"></span>Executing...';
    btn.disabled = true;

    fetch('db_migration_api.php?action=execute_migration', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ 
            statements: currentStatements, 
            dry_run: dryRun, 
            target_db: currentComparison.target_db 
        })
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = origHTML; btn.disabled = false;
        if (data.status === 'ok') {
            displayExecutionResults(data, dryRun);
            Toast.show(dryRun ? 'Dry run finished safely' : 'Migration executed successfully', dryRun ? 'info' : 'success');
        } else {
            Toast.show('Execution failed: ' + (data.message || 'Error'), 'error');
        }
    })
    .catch(err => { btn.innerHTML = origHTML; btn.disabled = false; Toast.show('Network error', 'error'); });
}

function displayExecutionResults(data, dryRun) {
    const card = document.getElementById('executionResults');
    card.style.display = 'flex';
    const r = data.results || {};
    const ex = r.executed || [];
    const fa = r.failed || [];
    
    let html = `<div style="display:flex; gap:12px; font-size:0.85rem; margin-bottom:12px; align-items:center;">
        <span class="bp-card-header-badge" style="background:${r.success?'rgba(58,184,127,0.15)':'rgba(246,102,102,0.15)'}; color:${r.success?'var(--bp-green)':'var(--bp-red)'}; font-size:0.8rem; padding:4px 10px;">${r.success?'✓ Success':'✗ Failed'}</span>
        ${dryRun ? `<span class="bp-card-header-badge" style="background:rgba(58,181,200,0.15); color:var(--bp-teal); font-size:0.8rem; padding:4px 10px;">Dry Run Mode</span>` : ''}
        <span style="color:var(--bp-dim);">${ex.length} executed, ${fa.length} failed.</span>
    </div>`;
    
    if (fa.length > 0) {
        html += makeDiffSec('Failed Statements', fa.length, fa.map(f => `
            <div class="migration-step danger">
                <strong>${esc(f.statement.type)}</strong>
                <div style="color:var(--bp-red); margin:4px 0; font-size:0.75rem;">${esc(f.error)}</div>
                <div class="sql-preview">${esc(f.statement.sql)}</div>
            </div>`).join(''));
    }
    document.getElementById('executionContent').innerHTML = html;
    card.scrollIntoView({ behavior: 'smooth' });
}

function downloadParallelSql() {
    if (!currentStatements || !currentStatements.length) return;
    const txt = currentStatements.map(s => `-- ${s.type} on ${s.table||'?'}\n${s.sql}\n`).join('\n');
    downloadBlob(txt, `migration_${currentComparison.source_db}_to_${currentComparison.target_db}.sql`);
}

// ── FULL ROLLOUT SQL LOGIC ───────────────────────────────────────────────
let currentFullSql = '';

function generateFullRollout() {
    const db = document.getElementById('fullRolloutDb').value;
    if (!db) return Toast.show('Select a database first', 'warn');
    
    const btn = document.getElementById('btnGenFull');
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="bp-spinner"></span>Generating...';
    btn.disabled = true;

    fetch('db_migration_api.php?action=generate_full_rollout_sql', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ db_name: db })
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = orig; btn.disabled = false;
        if (data.status === 'ok') {
            currentFullSql = data.sql;
            document.getElementById('fullRolloutText').value = currentFullSql;
            document.getElementById('fullRolloutResults').style.display = 'flex';
            Toast.show('Full SQL generated successfully', 'success');
        } else {
            Toast.show('Error: ' + data.message, 'error');
        }
    })
    .catch(() => { btn.innerHTML = orig; btn.disabled = false; Toast.show('Network error', 'error'); });
}

function downloadFullRollout() {
    const db = document.getElementById('fullRolloutDb').value;
    if (currentFullSql) downloadBlob(currentFullSql, `full_rollout_${db}.sql`);
}

// ── UPDATE ROLLOUT SQL LOGIC ─────────────────────────────────────────────
let currentUpdateSql = '';

function generateUpdateRollout() {
    const liveDb = document.getElementById('updateLiveDb').value;
    const baselineDb = document.getElementById('updateBaselineDb').value;
    
    if (!liveDb || !baselineDb) return Toast.show('Source and Target databases are required', 'warn');
    if (liveDb === baselineDb) return Toast.show('Source and Target must be different', 'warn');
    
    const btn = document.getElementById('btnGenUpdate');
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="bp-spinner"></span>Reading Baseline...';
    btn.disabled = true;

    // Step 1: Generate Full SQL for the baseline (Staging) DB
    fetch('db_migration_api.php?action=generate_full_rollout_sql', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ db_name: baselineDb })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status !== 'ok') throw new Error(data.message || 'Failed to read baseline DB');
        
        btn.innerHTML = '<span class="bp-spinner"></span>Comparing...';
        
        // Step 2: Pass that generated baseline to compare against the Live DB
        return fetch('db_migration_api.php?action=generate_update_rollout_sql', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ live_db: liveDb, baseline_sql: data.sql })
        }).then(r => r.json());
    })
    .then(data => {
        btn.innerHTML = orig; btn.disabled = false;
        if (data.status === 'ok') {
            currentUpdateSql = data.full_sql;
            document.getElementById('updateRolloutText').value = currentUpdateSql;
            document.getElementById('updateRolloutResults').style.display = 'flex';
            Toast.show('Update SQL generated successfully', 'success');
        } else {
            Toast.show('Error: ' + data.message, 'error');
        }
    })
    .catch(err => { 
        btn.innerHTML = orig; btn.disabled = false; 
        Toast.show(err.message || 'Network error', 'error'); 
    });
}


function downloadUpdateRollout() {
    const liveDb = document.getElementById('updateLiveDb').value;
    if (currentUpdateSql) downloadBlob(currentUpdateSql, `update_rollout_${liveDb}.sql`);
}

</script>
<?php
//require_once "forge_tool.php";
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>