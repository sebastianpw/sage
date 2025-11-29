<?php
// public/view_db_migration_admin.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\DatabaseMigrationManager;
use App\Core\AIProvider;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();
$fileLogger = $spw->getFileLogger();
// Note: AIProvider is often part of SpwBase, but this is fine too.
$aiProvider = new AIProvider($fileLogger);

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
    $fileLogger->error('Failed to list databases: ' . $e->getMessage());
}

// Current configured database
$currentDb = $spw->getDbName();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<style>
    /* ----- CORE LAYOUT (Largely Unchanged) ----- */
    .admin-wrap { max-width:1200px; margin:0 auto; padding:18px; }
    .admin-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap; border-bottom:1px solid var(--border); padding-bottom:12px; }
    .admin-head h2 { margin:0; font-weight:600; font-size:1.15rem; color: var(--text); }

    /* ----- FORM & UTILITIES (Theme-Aware) ----- */
    /* .btn, .card, .form-control, and .badge classes are now inherited from base.css */
    .form-label { color: var(--text); font-weight: 500; margin-bottom: 6px; }
    .form-check { display:flex; align-items:center; gap:8px; }
    .form-check input[type="checkbox"] { width:18px; height:18px; cursor:pointer; }
    .grid-2 { display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:12px; }
    .small-muted { color: var(--text-muted); font-size:0.85rem; }

    /* ----- CUSTOM COMPONENTS (Refactored for Theming) ----- */
    .comparison-results { margin-top:16px; }
    .diff-section { margin-bottom:16px; }

    .diff-header {
        display:flex;
        justify-content:space-between;
        align-items:center;
        padding:8px 12px;
        background-color: var(--bg); /* Use theme variable */
        border: 1px solid var(--border);
        border-radius:6px;
        cursor:pointer;
        transition:background-color 0.15s;
    }
    .diff-header:hover { background-color: rgba(var(--muted-border-rgb), 0.2); } /* Use transparent overlay for hover */
    .diff-body {
        padding:12px;
        border:1px solid var(--border); /* Use theme variable */
        border-top:none;
        border-radius:0 0 6px 6px;
        display:none;
        background-color: var(--card); /* Body should be card color */
    }
    .diff-body.show { display:block; }

    .sql-preview {
        background-color: var(--bg); /* Use theme variable */
        padding:12px;
        border-radius:6px;
        font-family:monospace;
        font-size:0.85rem;
        white-space:pre-wrap;
        word-wrap:break-word;
        max-height:400px;
        overflow:auto;
        border: 1px solid var(--border);
        color: var(--text-muted);
    }

    .migration-step {
        padding:12px;
        border-left:3px solid var(--accent); /* Use theme variable */
        margin-bottom:8px;
        background-color: var(--card); /* Use theme variable */
        border-radius:4px;
        border: 1px solid var(--border);
        border-left-width: 3px;
    }
    .migration-step.safe { border-left-color: var(--green); }
    .migration-step.warning { border-left-color: var(--orange); }
    .migration-step.danger { border-left-color: var(--red); }

    .progress-bar {
        height:8px;
        background-color: var(--border); /* Use theme variable */
        border-radius:4px;
        overflow:hidden;
        margin:12px 0;
    }
    .progress-fill {
        height:100%;
        background-color: var(--accent); /* Use theme variable */
        transition:width 0.3s;
    }

    .loader-dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background-color: var(--blue-light-bg); /* Use theme variable */
        position: relative;
        box-shadow: inset 0 0 0 3px var(--blue-light-border); /* Use theme variable */
        animation: loader-scale 1s infinite;
        display:inline-block;
        margin-right:8px;
    }
    @keyframes loader-scale {
        0%, 100% { transform: scale(1); opacity: 0.6; }
        50% { transform: scale(1.4); opacity: 1; }
    }

    /* ----- RESPONSIVE (Unchanged) ----- */
    @media (max-width:700px) {
      .grid-2 { grid-template-columns:1fr; }
      .admin-head { gap:8px; }
    }

.card-header {
    padding: 8px 0;
}
.card {
    padding: 16px;
}
</style>

<div class="admin-wrap">
  <div class="admin-head">
    <h2>Database Migration Manager</h2>
    <div style="display:flex;gap:8px;align-items:center;">
      <a class="btn btn-secondary btn-sm" href="view_db_migration_history.php">View History</a>
    </div>
  </div>

  <p class="small-muted">
    Synchronize schemas between parallel database instances or apply version updates.
    Current database: <strong><?= htmlspecialchars($currentDb) ?></strong>
  </p>

  <!-- Migration Type Selection -->
  <div class="card">
    <div class="card-header">Migration Configuration</div>
    
    <div class="form-group">
      <label class="form-label">Migration Type:</label>
      <select id="migrationType" class="form-control">
        <option value="parallel_sync">Parallel Instance Sync</option>
        <option value="version_update">Version Update (Git Tag)</option>
      </select>
      <p class="small-muted" style="margin-top:4px;">
        <strong>Parallel Sync:</strong> Copy schema from one instance to another<br>
        <strong>Version Update:</strong> Apply migrations from a specific version tag
      </p>
    </div>

    <!-- Parallel Sync Config -->
    <div id="parallelSyncConfig" class="grid-2">
      <div class="form-group">
        <label class="form-label">Source Database (Leading):</label>
        <select id="sourceDb" class="form-control">
          <option value="">-- Select Source --</option>
          <?php foreach ($databases as $db): ?>
            <option value="<?= htmlspecialchars($db) ?>" <?= $db === $currentDb ? 'selected' : '' ?>>
              <?= htmlspecialchars($db) ?><?= $db === $currentDb ? ' (current)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="small-muted" style="margin-top:4px;">
          The database with the most up-to-date schema
        </p>
      </div>

      <div class="form-group">
        <label class="form-label">Target Database (To Update):</label>
        <select id="targetDb" class="form-control">
          <option value="">-- Select Target --</option>
          <?php foreach ($databases as $db): ?>
            <option value="<?= htmlspecialchars($db) ?>">
              <?= htmlspecialchars($db) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="small-muted" style="margin-top:4px;">
          The database to be updated
        </p>
      </div>
    </div>

    <!-- Version Update Config -->
    <div id="versionUpdateConfig" style="display:none;">
      <div class="form-group">
        <label class="form-label">Target Database:</label>
        <select id="versionTargetDb" class="form-control">
          <option value="">-- Select Database --</option>
          <?php foreach ($databases as $db): ?>
            <option value="<?= htmlspecialchars($db) ?>">
              <?= htmlspecialchars($db) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">From Version:</label>
        <input type="text" id="fromVersion" class="form-control" placeholder="e.g., v0.3" />
        <p class="small-muted" style="margin-top:4px;">
          Current version of the database
        </p>
      </div>

      <div class="form-group">
        <label class="form-label">To Version:</label>
        <input type="text" id="toVersion" class="form-control" placeholder="e.g., v0.4" />
        <p class="small-muted" style="margin-top:4px;">
          Target version to upgrade to
        </p>
      </div>
    </div>

    <!-- Options -->
    <div class="form-group" style="margin-top:16px;">
      <div class="form-check">
        <input type="checkbox" id="useAI" checked />
        <label for="useAI" class="form-label" style="margin:0;">Use AI validation</label>
      </div>
      <p class="small-muted" style="margin-left:26px;">
        AI will review migration SQL for safety and potential issues
      </p>
    </div>

    <div class="form-check">
      <input type="checkbox" id="createBackup" checked />
      <label for="createBackup" class="form-label" style="margin:0;">Create backup before migration</label>
    </div>

    <div style="margin-top:16px; display:flex; gap:8px;">
      <button id="btnAnalyze" class="btn btn-accent">
        Analyze Differences
      </button>
      <button id="btnReset" class="btn btn-secondary">
        Reset
      </button>
    </div>
  </div>

  <!-- Analysis Results -->
  <div id="analysisResults" class="card" style="display:none;">
    <div class="card-header">
      Schema Comparison Results
      <span id="analysisStats" class="badge badge-blue" style="float:right;"></span>
    </div>
    
    <div id="analysisContent"></div>

    <div style="margin-top:16px; display:flex; gap:8px; align-items:center;">
      <button id="btnGenerateSql" class="btn btn-accent">
        Generate Migration SQL
      </button>
      <span id="analysisWarning" class="small-muted" style="color:var(--red);"></span>
    </div>
  </div>

  <!-- Migration Plan -->
  <div id="migrationPlan" class="card" style="display:none;">
    <div class="card-header">
      Migration Plan
      <span id="planStats" class="badge badge-blue" style="float:right;"></span>
    </div>

    <div id="aiValidation" class="notification notification-warning" style="display:none; margin-bottom:16px;">
      <strong>AI Validation Results:</strong>
      <div id="aiValidationContent" class="small-muted" style="margin-top:8px;"></div>
    </div>

    <div id="migrationSteps"></div>

    <div style="margin-top:16px;">
      <div class="form-check" style="margin-bottom:12px;">
        <input type="checkbox" id="dryRun" checked />
        <label for="dryRun" class="form-label" style="margin:0;">Dry run (preview only, don't execute)</label>
      </div>

      <div style="display:flex; gap:8px; align-items:center;">
        <button id="btnExecute" class="btn btn-primary">
          Execute Migration
        </button>
        <button id="btnDownloadSql" class="btn btn-secondary">
          Download SQL
        </button>
        <div id="executionStatus" style="display:none;">
          <div class="loader-dot"></div>
          <span class="small-muted">Executing migration...</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Execution Results -->
  <div id="executionResults" class="card" style="display:none;">
    <div class="card-header">Migration Execution Results</div>
    <div id="executionContent"></div>
  </div>

</div>

<script src="js/toast.js"></script>
<script>
(function(){
  // All JS is untouched as it uses IDs, which were not changed.
  // This ensures functionality remains exactly the same.

  function showToast(msg, type) {
    if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
      const mapType = (type === 'danger' || type === 'error') ? 'error' : (type === 'warning' ? 'info' : (type || 'info'));
      Toast.show(msg, mapType);
    } else {
      console.log('[toast]', msg);
    }
  }

  const migrationType = document.getElementById('migrationType');
  const parallelSyncConfig = document.getElementById('parallelSyncConfig');
  const versionUpdateConfig = document.getElementById('versionUpdateConfig');
  const btnAnalyze = document.getElementById('btnAnalyze');
  const btnReset = document.getElementById('btnReset');
  const btnGenerateSql = document.getElementById('btnGenerateSql');
  const btnExecute = document.getElementById('btnExecute');
  const btnDownloadSql = document.getElementById('btnDownloadSql');

  let currentComparison = null;
  let currentStatements = null;

  migrationType.addEventListener('change', function() {
    if (this.value === 'parallel_sync') {
      parallelSyncConfig.style.display = 'grid';
      versionUpdateConfig.style.display = 'none';
    } else {
      parallelSyncConfig.style.display = 'none';
      versionUpdateConfig.style.display = 'block';
    }
    resetAnalysis();
  });

  btnAnalyze.addEventListener('click', function() {
    const type = migrationType.value;
    if (type === 'parallel_sync') {
      const sourceDb = document.getElementById('sourceDb').value;
      const targetDb = document.getElementById('targetDb').value;
      if (!sourceDb || !targetDb) {
        showToast('Please select both source and target databases', 'warning');
        return;
      }
      if (sourceDb === targetDb) {
        showToast('Source and target must be different databases', 'warning');
        return;
      }
      analyzeParallelSync(sourceDb, targetDb);
    } else {
      const targetDb = document.getElementById('versionTargetDb').value;
      const fromVersion = document.getElementById('fromVersion').value.trim();
      const toVersion = document.getElementById('toVersion').value.trim();
      if (!targetDb || !fromVersion || !toVersion) {
        showToast('Please fill in all version update fields', 'warning');
        return;
      }
      analyzeVersionUpdate(targetDb, fromVersion, toVersion);
    }
  });

  btnReset.addEventListener('click', resetAnalysis);
  btnGenerateSql.addEventListener('click', generateMigrationSql);
  btnExecute.addEventListener('click', executeMigration);
  btnDownloadSql.addEventListener('click', downloadSql);

  function analyzeParallelSync(sourceDb, targetDb) {
    btnAnalyze.disabled = true;
    btnAnalyze.innerHTML = '<div class="loader-dot" style="margin-right:4px;"></div>Analyzing...';
    fetch('db_migration_api.php?action=compare_schemas', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ source_db: sourceDb, target_db: targetDb })
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'ok') {
        currentComparison = data;
        displayComparisonResults(data);
      } else {
        showToast('Analysis failed: ' + (data.message || 'unknown error'), 'error');
      }
    })
    .catch(err => { console.error(err); showToast('Network error during analysis', 'error'); })
    .finally(() => { btnAnalyze.disabled = false; btnAnalyze.textContent = 'Analyze Differences'; });
  }

  function analyzeVersionUpdate(targetDb, fromVersion, toVersion) {
    btnAnalyze.disabled = true;
    btnAnalyze.innerHTML = '<div class="loader-dot" style="margin-right:4px;"></div>Analyzing...';
    fetch('db_migration_api.php?action=version_diff', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ target_db: targetDb, from_version: fromVersion, to_version: toVersion })
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'ok') {
        currentComparison = data;
        displayComparisonResults(data);
      } else {
        showToast('Version analysis failed: ' + (data.message || 'unknown error'), 'error');
      }
    })
    .catch(err => { console.error(err); showToast('Network error during version analysis', 'error'); })
    .finally(() => { btnAnalyze.disabled = false; btnAnalyze.textContent = 'Analyze Differences'; });
  }

  function displayComparisonResults(data) {
    const resultsCard = document.getElementById('analysisResults');
    const content = document.getElementById('analysisContent');
    const stats = document.getElementById('analysisStats');
    const warning = document.getElementById('analysisWarning');
    resultsCard.style.display = 'block';
    const diffs = data.differences || {};
    const totalChanges = (diffs.missing_tables?.length || 0) + 
                         (diffs.missing_columns?.length || 0) + 
                         (diffs.column_changes?.length || 0) + 
                         (diffs.missing_indexes?.length || 0) + 
                         (diffs.missing_constraints?.length || 0) +
                         (diffs.missing_views?.length || 0) +
                         (diffs.altered_views?.length || 0);

    stats.textContent = totalChanges + ' changes detected';
    if (totalChanges === 0) {
      content.innerHTML = '<p class="small-muted">No schema differences found. Databases are in sync.</p>';
      warning.textContent = '';
      btnGenerateSql.disabled = true;
      return;
    }
    btnGenerateSql.disabled = false;
    warning.textContent = '';
    let html = '';
    if (diffs.missing_tables && diffs.missing_tables.length > 0) {
      html += createDiffSection('Missing Tables', diffs.missing_tables.length, 'red', diffs.missing_tables.map(t => `<div>• ${escapeHtml(t)}</div>`).join(''));
    }
    if (diffs.missing_views && diffs.missing_views.length > 0) {
        html += createDiffSection('Missing Views', diffs.missing_views.length, 'green', diffs.missing_views.map(v => `<div>• ${escapeHtml(v)}</div>`).join(''));
    }
    if (diffs.altered_views && diffs.altered_views.length > 0) {
        html += createDiffSection('Altered Views', diffs.altered_views.length, 'gray', diffs.altered_views.map(v => `<div>• <strong>${escapeHtml(v.view)}</strong>: Definition has changed</div>`).join(''));
    }
    if (diffs.missing_columns && diffs.missing_columns.length > 0) {
      html += createDiffSection('Missing Columns', diffs.missing_columns.length, 'gray', diffs.missing_columns.map(c => `<div>• <strong>${escapeHtml(c.table)}</strong>: ${escapeHtml(c.column.COLUMN_NAME)} (${escapeHtml(c.column.COLUMN_TYPE)})</div>`).join(''));
    }
    if (diffs.column_changes && diffs.column_changes.length > 0) {
      html += createDiffSection('Modified Columns', diffs.column_changes.length, 'gray', diffs.column_changes.map(c => `<div>• <strong>${escapeHtml(c.table)}.${escapeHtml(c.column)}</strong>: Type change detected</div>`).join(''));
    }
    if (diffs.missing_indexes && diffs.missing_indexes.length > 0) {
      html += createDiffSection('Missing Indexes', diffs.missing_indexes.length, 'blue', diffs.missing_indexes.map(i => `<div>• <strong>${escapeHtml(i.table)}</strong>: ${escapeHtml(i.index.Key_name)}</div>`).join(''));
    }
    if (diffs.missing_constraints && diffs.missing_constraints.length > 0) {
      html += createDiffSection('Missing Foreign Keys', diffs.missing_constraints.length, 'blue', diffs.missing_constraints.map(fk => `<div>• <strong>${escapeHtml(fk.table)}</strong>: ${escapeHtml(fk.constraint.CONSTRAINT_NAME)}</div>`).join(''));
    }
    content.innerHTML = html;
    document.querySelectorAll('.diff-header').forEach(header => {
      header.addEventListener('click', function() { this.nextElementSibling.classList.toggle('show'); });
    });
  }

  function createDiffSection(title, count, type, content) {
    return `
      <div class="diff-section">
        <div class="diff-header">
          <span><strong>${escapeHtml(title)}</strong> <span class="badge badge-${type}">${count}</span></span>
          <span>▼</span>
        </div>
        <div class="diff-body">${content}</div>
      </div>
    `;
  }

  function generateMigrationSql() {
    if (!currentComparison) return;
    btnGenerateSql.disabled = true;
    btnGenerateSql.innerHTML = '<div class="loader-dot" style="margin-right:4px;"></div>Generating...';
    const useAI = document.getElementById('useAI').checked;
    fetch('db_migration_api.php?action=generate_migration', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ comparison: currentComparison, use_ai: useAI })
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'ok') {
        currentStatements = data.statements;
        displayMigrationPlan(data);
      } else {
        showToast('Failed to generate migration: ' + (data.message || 'unknown error'), 'error');
      }
    })
    .catch(err => { console.error(err); showToast('Network error generating migration', 'error'); })
    .finally(() => { btnGenerateSql.disabled = false; btnGenerateSql.textContent = 'Generate Migration SQL'; });
  }

  function displayMigrationPlan(data) {
    const planCard = document.getElementById('migrationPlan');
    const stepsDiv = document.getElementById('migrationSteps');
    const statsSpan = document.getElementById('planStats');
    const aiValidation = document.getElementById('aiValidation');
    const aiContent = document.getElementById('aiValidationContent');
    planCard.style.display = 'block';
    const statements = data.statements || [];
    statsSpan.textContent = statements.length + ' statements';
    if (data.ai_feedback && data.ai_feedback.length > 0) {
      aiValidation.style.display = 'block';
      aiContent.innerHTML = data.ai_feedback.map(f =>
        `<div style="margin-bottom:8px;">
          <span class="badge badge-${f.risk === 'high' ? 'red' : f.risk === 'medium' ? 'gray' : 'blue'}">
            ${escapeHtml(f.risk || 'info')} risk
          </span>
          <div style="margin-top:4px;">${escapeHtml(f.response || '').substring(0, 500)}...</div>
        </div>`
      ).join('');
    } else {
      aiValidation.style.display = 'none';
    }
    let html = '';
    statements.forEach((stmt, idx) => {
      const safeClass = stmt.safe ? 'safe' : stmt.warning ? 'warning' : 'danger';
      html += `
        <div class="migration-step ${safeClass}">
          <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
            <strong>${idx + 1}. ${escapeHtml(stmt.type)}</strong>
            <span class="badge badge-${stmt.safe ? 'green' : 'gray'}">
              ${stmt.safe ? 'Safe' : 'Caution'}
            </span>
          </div>
          <div class="small-muted" style="margin-bottom:8px;">
            Table: <strong>${escapeHtml(stmt.table || 'N/A')}</strong>
            ${stmt.column ? ' | Column: <strong>' + escapeHtml(stmt.column) + '</strong>' : ''}
            ${stmt.warning ? ' | ⚠️ ' + escapeHtml(stmt.warning) : ''}
          </div>
          <div class="sql-preview">${escapeHtml(stmt.sql)}</div>
          ${stmt.reversible ? '<div class="small-muted" style="margin-top:4px;">✓ Reversible</div>' : ''}
        </div>`;
    });
    stepsDiv.innerHTML = html || '<p class="small-muted">No migration steps generated.</p>';
    planCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function executeMigration() {
    if (!currentStatements || currentStatements.length === 0) {
      showToast('No migration plan to execute', 'warning');
      return;
    }
    const dryRun = document.getElementById('dryRun').checked;
    const createBackup = document.getElementById('createBackup').checked;
    if (!dryRun && !confirm('This will modify the target database. Continue?')) { return; }
    btnExecute.disabled = true;
    document.getElementById('executionStatus').style.display = 'flex';
    fetch('db_migration_api.php?action=execute_migration', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ statements: currentStatements, dry_run: dryRun, create_backup: createBackup, target_db: currentComparison.target_db })
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'ok') {
        displayExecutionResults(data);
        showToast(dryRun ? 'Dry run completed - no changes made' : 'Migration executed successfully', dryRun ? 'info' : 'success');
      } else {
        showToast('Migration execution failed: ' + (data.message || 'unknown error'), 'error');
      }
    })
    .catch(err => { console.error(err); showToast('Network error during migration', 'error'); })
    .finally(() => { btnExecute.disabled = false; document.getElementById('executionStatus').style.display = 'none'; });
  }

  function displayExecutionResults(data) {
    const resultsCard = document.getElementById('executionResults');
    const content = document.getElementById('executionContent');
    resultsCard.style.display = 'block';
    const results = data.results || {};
    const executed = results.executed || [];
    const failed = results.failed || [];
    const dryRun = document.getElementById('dryRun').checked;
    let html = `
      <div style="margin-bottom:16px;">
        <span class="badge badge-${results.success ? 'green' : 'red'}">
          ${results.success ? '✓ Success' : '✗ Failed'}
        </span>
        ${dryRun ? '<span class="badge badge-blue" style="margin-left:8px;">Dry Run</span>' : ''}
      </div>
      <div style="margin-bottom:16px;">
        <strong>Executed:</strong> ${executed.length} statements<br>
        <strong>Failed:</strong> ${failed.length} statements
      </div>`;
    if (executed.length > 0) {
      html += '<div class="diff-section"><div class="diff-header"><strong>Executed Statements</strong> <span class="badge badge-green">' + executed.length + '</span></div>';
      html += '<div class="diff-body show">';
      executed.forEach(ex => {
        html += `<div class="migration-step safe" style="margin-bottom:8px;">
          <strong>${escapeHtml(ex.statement.type)}</strong>
          <div class="sql-preview" style="margin-top:4px;">${escapeHtml(ex.statement.sql)}</div>
        </div>`;
      });
      html += '</div></div>';
    }
    if (failed.length > 0) {
      html += '<div class="diff-section"><div class="diff-header"><strong>Failed Statements</strong> <span class="badge badge-red">' + failed.length + '</span></div>';
      html += '<div class="diff-body show">';
      failed.forEach(fail => {
        html += `<div class="migration-step danger" style="margin-bottom:8px;">
          <strong>${escapeHtml(fail.statement.type)}</strong>
          <div class="small-muted" style="color:var(--red); margin:4px 0;">Error: ${escapeHtml(fail.error)}</div>
          <div class="sql-preview" style="margin-top:4px;">${escapeHtml(fail.statement.sql)}</div>
        </div>`;
      });
      html += '</div></div>';
    }
    if (data.backup_info) {
      html += `<div class="notification notification-success" style="margin-top:16px;">
        <strong>Backup Created:</strong><br>
        <span class="small-muted">${escapeHtml(data.backup_info.file || 'N/A')}</span>
      </div>`;
    }
    content.innerHTML = html;
    resultsCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function downloadSql() {
    if (!currentStatements || currentStatements.length === 0) {
      showToast('No SQL to download', 'warning');
      return;
    }
    const sqlText = currentStatements.map((stmt, idx) => {
      return `-- Statement ${idx + 1}: ${stmt.type}\n-- Table: ${stmt.table || 'N/A'}\n` +
             (stmt.warning ? `-- WARNING: ${stmt.warning}\n` : '') + `${stmt.sql}\n\n` +
             (stmt.rollback ? `-- Rollback:\n-- ${stmt.rollback}\n\n` : '');
    }).join('');
    const blob = new Blob([sqlText], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-').substring(0, 19);
    const sourceDb = document.getElementById('sourceDb').value || 'unknown';
    const targetDb = document.getElementById('targetDb').value || 'unknown';
    a.download = `migration_${sourceDb}_to_${targetDb}_${timestamp}.sql`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showToast('SQL file downloaded', 'success');
  }

  function resetAnalysis() {
    currentComparison = null;
    currentStatements = null;
    document.getElementById('analysisResults').style.display = 'none';
    document.getElementById('migrationPlan').style.display = 'none';
    document.getElementById('executionResults').style.display = 'none';
    btnGenerateSql.disabled = true;
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

})();
</script>
<?php
//require "floatool.php";
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle);
?>
