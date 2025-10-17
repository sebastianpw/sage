<?php
// export_view.php - Dedicated export interface with folder tree and preview
require_once __DIR__ . '/error_reporting.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getSysPDO();
$aiProvider = new \App\Core\AIProvider($spw->getFileLogger());
$rateLimiter = new \App\Core\ModelRateLimiter();
$ci = new \App\Core\CodeIntelligence($spw, $aiProvider, $rateLimiter);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Handle export action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    $fileIds = array_map('intval', $_POST['file_ids'] ?? []);
    if (!empty($fileIds)) {
        $data = $ci->exportFilesAsJson($fileIds);
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="code_export_' . date('Y-m-d_His') . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// Fetch all files
$sql = 'SELECT id, path, file_hash, chunk_count, last_analyzed_at FROM code_files ORDER BY path';
$stmt = $pdo->prepare($sql);
$stmt->execute();
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build hierarchical tree
$tree = [];
foreach ($files as $f) {
    $parts = explode('/', $f['path']);
    $node =& $tree;
    $lastPartIndex = count($parts) - 1;
    foreach ($parts as $idx => $p) {
        if ($idx === $lastPartIndex) {
            if (!isset($node['_files'])) $node['_files'] = [];
            $node['_files'][$p] = $f;
        } else {
            if (!isset($node[$p])) $node[$p] = [];
            $node =& $node[$p];
        }
    }
    unset($node);
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=0.3">
<title>Export Code Structure · SAGE</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0f1724;--card:#0b1220;--muted:#94a3b8;--accent:#60a5fa;--glass:rgba(255,255,255,0.03);--success:#10b981}
*{box-sizing:border-box}
body{font-family:Inter,system-ui,sans-serif;margin:0;background:linear-gradient(180deg,#071026,#071a2b);color:#e6eef8;min-height:100vh}
.header{padding:20px 24px;background:var(--card);border-bottom:1px solid rgba(255,255,255,0.05);display:flex;justify-content:space-between;align-items:center}
.logo{font-size:20px;font-weight:700;color:var(--accent)}
.container{display:flex;height:calc(100vh - 80px)}
.sidebar{width:450px;background:var(--card);padding:20px;overflow:auto;border-right:1px solid rgba(255,255,255,0.05)}
.main{flex:1;padding:24px;overflow:auto}
.btn{background:var(--accent);color:#04263b;padding:10px 18px;border-radius:8px;border:none;cursor:pointer;font-weight:600;font-size:14px}
.btn:hover{opacity:0.9}
.btn-success{background:var(--success);color:white}
.card{background:var(--card);padding:20px;border-radius:12px;margin-bottom:16px}
h2{margin:0 0 12px 0;font-size:20px;font-weight:600}
h3{margin:0 0 10px 0;font-size:16px;font-weight:600;color:var(--muted)}
.small{font-size:13px;color:var(--muted)}
.tree{margin-top:16px}
.tree ul{list-style:none;padding-left:20px;margin:4px 0}
.tree li{margin:6px 0}
.tree details>summary{cursor:pointer;padding:6px 8px;border-radius:6px;font-weight:600;list-style-type:'📁 ';user-select:none}
.tree details[open]>summary{list-style-type:'📂 '}
.tree details>summary:hover{background:var(--glass)}
.file-item{display:flex;align-items:center;gap:10px;padding:8px;border-radius:6px;margin:4px 0}
.file-item:hover{background:var(--glass)}
.file-item label{display:flex;align-items:center;gap:8px;cursor:pointer;flex:1}
.checkbox{width:18px;height:18px;cursor:pointer;accent-color:var(--accent)}
.file-icon{color:var(--muted);font-size:12px}
.stats{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0}
.stat-box{background:linear-gradient(135deg,rgba(96,165,250,0.1),rgba(96,165,250,0.02));padding:16px;border-radius:8px;border-left:3px solid var(--accent)}
.stat-label{font-size:12px;color:var(--muted);margin-bottom:4px}
.stat-value{font-size:24px;font-weight:700;color:var(--accent)}
.preview{background:#0a1929;padding:16px;border-radius:8px;overflow:auto;max-height:400px}
.preview pre{margin:0;font-size:12px;line-height:1.6;color:#94a3b8}
.actions{position:sticky;bottom:0;background:var(--card);padding:16px;border-top:1px solid rgba(255,255,255,0.05);display:flex;gap:12px;margin:-20px;margin-top:16px}
.badge{display:inline-block;background:rgba(96,165,250,0.2);color:var(--accent);padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600}
</style>
</head>
<body>

<div class="header">
  <div class="logo">📦 Export Code Structure</div>
  <div style="display:flex;gap:12px">
    <a href="codeboard.php" class="btn">← Back to Dashboard</a>
  </div>
</div>

<div class="container">
  <aside class="sidebar">
    <h2>Select Files to Export</h2>
    <p class="small">Choose files and folders to include in your JSON export. The export preserves folder structure and includes all code intelligence data.</p>

    <div style="display:flex;gap:8px;margin:16px 0">
      <button class="btn" onclick="selectAll()">Select All</button>
      <button class="btn" onclick="deselectAll()">Deselect All</button>
      <button class="btn" onclick="expandAll()">Expand All</button>
    </div>

    <form id="exportForm" method="post">
      <div class="tree">
        <?php
        function renderExportTree($node, $path = '') {
            $dirs = array_filter($node, fn($key) => $key !== '_files', ARRAY_FILTER_USE_KEY);
            ksort($dirs);

            foreach ($dirs as $name => $children) {
                $newPath = $path ? "$path/$name" : $name;
                // do NOT set default open here
                echo '<li><details data-path="' . h($newPath) . '"><summary data-path="' . h($newPath) . '">' . h($name) . '</summary><ul>';
                renderExportTree($children, $newPath);
                echo '</ul></details></li>';
            }

            if (isset($node['_files'])) {
                $files = $node['_files'];
                ksort($files);
                foreach ($files as $name => $f) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $icon = match($ext) {
                        'php' => '🐘',
                        'js' => '📜',
                        'sh' => '⚙️',
                        default => '📄'
                    };
                    echo '<li><div class="file-item">';
                    echo '<label>';
                    echo '<input type="checkbox" name="file_ids[]" value="' . (int)$f['id'] . '" class="checkbox file-checkbox" data-path="' . h($f['path']) . '">';
                    echo '<span class="file-icon">' . $icon . '</span>';
                    echo '<span>' . h($name) . '</span>';
                    echo '</label>';
                    echo '<span class="badge">' . (int)$f['chunk_count'] . ' chunks</span>';
                    echo '</div></li>';
                }
            }
        }
        echo '<ul>';
        renderExportTree($tree);
        echo '</ul>';
        ?>
      </div>

      <div class="actions">
        <button type="submit" name="export" class="btn btn-success" id="exportBtn">Export Selected (0)</button>
        <button type="button" class="btn" onclick="previewExport()">Preview JSON</button>
      </div>
    </form>
  </aside>

  <main class="main">
    <div class="card">
      <h2>Export Information</h2>
      
      <div class="stats">
        <div class="stat-box">
          <div class="stat-label">Total Files</div>
          <div class="stat-value"><?php echo count($files); ?></div>
        </div>
        <div class="stat-box">
          <div class="stat-label">Selected</div>
          <div class="stat-value" id="selectedCount">0</div>
        </div>
      </div>

      <h3>What's Included in the Export?</h3>
      <ul class="small" style="line-height:1.8;margin:0;padding-left:20px">
        <li><strong>File Structure:</strong> Complete folder hierarchy preserved</li>
        <li><strong>File Metadata:</strong> Paths, file types, hashes, analysis timestamps</li>
        <li><strong>Classes & Modules:</strong> Class names, extends, interfaces</li>
        <li><strong>Methods:</strong> All method names and function lists</li>
        <li><strong>References:</strong> Class inheritance and interface implementations</li>
        <li><strong>Summaries:</strong> AI-generated code summaries</li>
        <li><strong>Analysis Data:</strong> Chunk counts and processing metadata</li>
      </ul>
    </div>

    <div class="card">
      <h3>Use Cases</h3>
      <ul class="small" style="line-height:1.8;margin:0;padding-left:20px">
        <li>📤 Share code structure with team members</li>
        <li>🤖 Provide context to AI assistants (Claude, GPT, etc.)</li>
        <li>📊 Generate documentation from exported structure</li>
        <li>🔍 Analyze dependencies and relationships</li>
        <li>💾 Backup code intelligence data</li>
        <li>🔄 Import into other tools for further analysis</li>
      </ul>
    </div>

    <div class="card" id="previewCard" style="display:none">
      <h3>JSON Preview</h3>
      <div class="preview">
        <pre id="previewContent">Select files and click "Preview JSON" to see export structure</pre>
      </div>
    </div>
  </main>
</div>

<script>
const EXPORT_TREE_KEY = 'codeboard_export_tree_state_v1';

function saveExportTreeState() {
  try {
    const state = {};
    document.querySelectorAll('.tree details').forEach(detail => {
      const summary = detail.querySelector(':scope > summary');
      const path = summary?.dataset?.path?.trim();
      if (path) state[path] = !!detail.open;
    });
    localStorage.setItem(EXPORT_TREE_KEY, JSON.stringify(state));
  } catch (e) {
    console.warn('Failed to save export tree state:', e);
  }
}

function restoreExportTreeState() {
  try {
    const raw = localStorage.getItem(EXPORT_TREE_KEY);
    if (!raw) return;
    const state = JSON.parse(raw);
    document.querySelectorAll('.tree details').forEach(detail => {
      const summary = detail.querySelector(':scope > summary');
      const path = summary?.dataset?.path?.trim();
      if (path && state.hasOwnProperty(path)) {
        detail.open = !!state[path];
      }
    });
  } catch (e) {
    console.warn('Failed to restore export tree state:', e);
  }
}

// immediate restore (handles cases where DOMContentLoaded already fired)
restoreExportTreeState();

document.addEventListener('DOMContentLoaded', function () {
  // restore again (safe)
  restoreExportTreeState();

  // folder summary shift-click: select all child files
  document.querySelectorAll('details > summary').forEach(summary => {
    summary.addEventListener('click', function(e) {
      if (e.shiftKey) {
        e.preventDefault();
        const details = this.parentElement;
        const checkboxes = details.querySelectorAll('.file-checkbox');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
        updateCount();
      }
    });
  });

  // save toggles
  document.addEventListener('toggle', function (e) {
    if (e.target instanceof HTMLElement && e.target.closest('.tree')) {
      saveExportTreeState();
    }
  }, true);

  // save before leaving
  window.addEventListener('beforeunload', saveExportTreeState);

  // wire checkboxes to counter
  document.querySelectorAll('.file-checkbox').forEach(cb => {
    cb.addEventListener('change', updateCount);
  });

  // ensure file link clicks also persist state before navigation
  document.querySelectorAll('.file-item a').forEach(a => {
    a.addEventListener('click', function () {
      saveExportTreeState();
    });
  });

  updateCount();
});

function updateCount() {
  const checked = document.querySelectorAll('.file-checkbox:checked').length;
  document.getElementById('selectedCount').textContent = checked;
  document.getElementById('exportBtn').textContent = `Export Selected (${checked})`;
}

function selectAll() {
  document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = true);
  updateCount();
  saveExportTreeState();
}

function deselectAll() {
  document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = false);
  updateCount();
  saveExportTreeState();
}

function expandAll() {
  document.querySelectorAll('details').forEach(d => d.open = true);
  // persist expanded state
  saveExportTreeState();
}

async function previewExport() {
  const checked = Array.from(document.querySelectorAll('.file-checkbox:checked'));
  if (checked.length === 0) {
    alert('Please select at least one file');
    return;
  }

  const fileIds = checked.map(cb => parseInt(cb.value));
  const paths = checked.map(cb => cb.dataset.path);

  const preview = {
    export_date: new Date().toISOString(),
    total_files: fileIds.length,
    files: paths,
    note: 'This is a preview. The actual export includes full class data, methods, and summaries.'
  };

  document.getElementById('previewContent').textContent = JSON.stringify(preview, null, 2);
  document.getElementById('previewCard').style.display = 'block';
  document.getElementById('previewCard').scrollIntoView({ behavior: 'smooth' });
}
</script>
</body>
</html>
