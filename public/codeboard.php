<?php
// codeboard.php - Enhanced with delete and export functionality
require_once __DIR__ . '/error_reporting.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getSysPDO();
$aiProvider = new \App\Core\AIProvider($spw->getFileLogger());
$rateLimiter = new \App\Core\ModelRateLimiter();
$ci = new \App\Core\CodeIntelligence($spw, $aiProvider, $rateLimiter);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Handle actions
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$message = '';
$messageType = 'info';

if ($action === 'delete' && isset($_GET['file_id'])) {
    try {
        $ci->deleteFile((int)$_GET['file_id']);
        $message = 'File deleted successfully';
        $messageType = 'success';
        header('Location: codeboard.php');
        exit;
    } catch (Exception $e) {
        $message = 'Delete failed: ' . $e->getMessage();
        $messageType = 'error';
    }
}

if ($action === 'export_json' && isset($_GET['file_id'])) {
    $data = $ci->exportFileAsJson((int)$_GET['file_id']);
    if ($data) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="export_' . basename($data['file']['path']) . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if ($action === 'bulk_export' && isset($_POST['file_ids'])) {
    $fileIds = array_map('intval', (array)$_POST['file_ids']);
    $data = $ci->exportFilesAsJson($fileIds);
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="bulk_export_' . date('Y-m-d_His') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'bulk_delete' && isset($_POST['file_ids'])) {
    $fileIds = array_map('intval', (array)$_POST['file_ids']);
    try {
        $deleted = $ci->deleteFiles($fileIds);
        $message = "Deleted {$deleted} file(s)";
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Bulk delete failed: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Fetch files
$fileId = isset($_GET['file_id']) ? (int)$_GET['file_id'] : null;
$search = $_GET['q'] ?? '';

$sql = 'SELECT id, path, file_hash, chunk_count, last_analyzed_at, created_at FROM code_files';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE path LIKE ?';
    $params[] = "%$search%";
}
$sql .= ' ORDER BY last_analyzed_at DESC, created_at DESC LIMIT 1000';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// File details if requested
$file = null;
$classes = [];
if ($fileId) {
    $stmt = $pdo->prepare('SELECT * FROM code_files WHERE id = ?');
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file) {
        $stmt = $pdo->prepare('SELECT * FROM code_classes WHERE file_id = ? ORDER BY id');
        $stmt->execute([$fileId]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Build tree
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
<title>SAGE · Code Intelligence</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0f1724;--card:#0b1220;--muted:#94a3b8;--accent:#60a5fa;--glass:rgba(255,255,255,0.03);--danger:#ef4444;--success:#10b981}
body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:0;background:linear-gradient(180deg,#071026 0%,#071a2b 100%);color:#e6eef8}
a{color:var(--accent);text-decoration:none}
a:hover{text-decoration:underline}
.header{padding:18px 24px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,0.03)}
.logo{font-weight:700;color:var(--accent);font-size:18px}
.container{display:flex;height:calc(100vh - 64px)}
.sidebar{width:400px;background:var(--card);padding:12px;border-right:1px solid rgba(255,255,255,0.03);overflow:auto}
.content{flex:1;padding:18px;overflow:auto}
.content h2{font-size:22px;margin:0 0 8px 0;font-weight:600}
.content h3{font-size:18px;margin:0 0 12px 0;font-weight:600;color:var(--muted)}
.input{flex:1;padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,0.04);background:var(--glass);color:inherit}
.btn{background:var(--accent);color:#04263b;padding:8px 12px;border-radius:8px;border:none;cursor:pointer;font-size:13px;font-weight:600}
.btn-danger{background:var(--danger);color:white}
.btn-success{background:var(--success);color:white}
.btn-sm{padding:6px 10px;font-size:12px}
.file-row{padding:10px;border-radius:8px;margin-bottom:8px;background:linear-gradient(180deg,rgba(255,255,255,0.01),transparent);cursor:pointer;display:flex;align-items:center;gap:8px}
.file-row:hover{background:var(--glass)}
.file-row small{color:var(--muted)}
.card{background:var(--card);padding:16px;border-radius:12px;margin-bottom:12px}
.method{display:inline-block;background:rgba(255,255,255,0.03);padding:6px 10px;border-radius:8px;margin:6px 6px 0 0}
.small{font-size:13px;color:var(--muted)}
.tree ul{list-style:none;padding-left:14px;margin:6px 0}
.tree li{margin:4px 0}
.tree summary{cursor:pointer;padding:4px 8px;border-radius:4px;font-weight:600;display:inline-block}
.tree summary:hover{background:var(--glass)}
.tree details>summary{list-style-type:'📁 '}
.tree details[open]>summary{list-style-type:'📂 '}
.tree .file-row{padding-left:24px;margin-bottom:0;padding-top:6px;padding-bottom:6px}
.tree .file-row a{font-weight:400}
.message{padding:12px;border-radius:8px;margin-bottom:12px}
.message.success{background:rgba(16,185,129,0.1);border-left:4px solid var(--success)}
.message.error{background:rgba(239,68,68,0.1);border-left:4px solid var(--danger)}
.export-panel{background:linear-gradient(180deg,rgba(96,165,250,0.08),rgba(96,165,250,0.02));padding:16px;border-radius:12px;margin-bottom:12px}
.checkbox{width:18px;height:18px;cursor:pointer}
.actions-bar{display:flex;gap:8px;margin-bottom:12px;padding:12px;background:var(--card);border-radius:8px;align-items:center;flex-wrap:wrap}
.kv{display:flex;gap:12px;margin:8px 0}
.k{color:var(--muted);min-width:120px}
.v{color:#e6eef8}
</style>
</head>
<body>
<div class="header">
  <div class="logo">SAGE · Code Intelligence</div>
  <div style="flex:1"></div>
  <div class="small">Project: <?php echo h(basename($spw->getProjectPath())); ?></div>
</div>

<div class="container">
  <aside class="sidebar">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <div style="font-weight:700">Files (<?php echo count($files); ?>)</div>

<?php /*
      <button class="btn btn-sm" onclick="toggleExportMode()">Export Mode</button>
 */ ?>

<button class="btn btn-sm" onclick="window.location.href='codeboard_export_view.php'">
  Export Mode
</button>

    </div>

    <form method="get" action="">
      <div style="display:flex;gap:8px;margin-bottom:12px">
        <input name="q" value="<?php echo h($search); ?>" class="input" placeholder="Filter files">
        <button class="btn" type="submit">Filter</button>
      </div>
    </form>

    <form id="bulkForm" method="post" style="display:none">
      <input type="hidden" name="action" id="bulkAction">
      <div class="actions-bar">
        <label><input type="checkbox" id="selectAll" class="checkbox"> Select All</label>
        <button type="button" class="btn btn-success btn-sm" onclick="bulkExport()">Export Selected</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()">Delete Selected</button>
        <button type="button" class="btn btn-sm" onclick="toggleExportMode()">Cancel</button>
        <span class="small" id="selectedCount">0 selected</span>
      </div>
    </form>

    <?php if ($message): ?>
      <div class="message <?php echo $messageType; ?>"><?php echo h($message); ?></div>
    <?php endif; ?>

    <div class="tree" id="fileTree">
      <?php
      // Updated renderTree: include stable data-path attributes, do NOT set default open
      function renderTree($node, $path = '', $level = 0) {
          $dirs = array_filter($node, fn($key) => $key !== '_files', ARRAY_FILTER_USE_KEY);
          ksort($dirs);

          foreach ($dirs as $name => $children) {
              $newPath = $path ? "$path/$name" : $name;
              echo '<li><details data-path="' . h($newPath) . '">';
              echo '<summary data-path="' . h($newPath) . '">' . h($name) . '</summary>';
              echo '<ul>';
              renderTree($children, $newPath, $level + 1);
              echo '</ul></details></li>';
          }

          if (isset($node['_files'])) {
              $files = $node['_files'];
              ksort($files);
              foreach ($files as $name => $f) {
                  echo '<li><div class="file-row">';
                  echo '<input type="checkbox" class="checkbox file-checkbox" name="file_ids[]" value="' . (int)$f['id'] . '" style="display:none">';
                  echo '<a href="?file_id=' . (int)$f['id'] . '" class="file-link" data-file-id="' . (int)$f['id'] . '">' . h($name) . '</a>';
                  echo '<div class="small">Last: ' . h(substr($f['last_analyzed_at'] ?? '', 0, 10)) . '</div>';
                  echo '</div></li>';
              }
          }
      }
      echo '<ul>';
      renderTree($tree);
      echo '</ul>';
      ?>
    </div>
  </aside>

  <main class="content" id="mainContent">
    <?php if (!$file): ?>
      <div class="card">
        <h2>Code Intelligence Dashboard</h2>
        <p class="small">Select a file from the left panel to view its analysis, classes, and methods. Use Export Mode to bulk export files with folder structure preserved.</p>
      </div>

      <div class="export-panel">
        <h3>Export Features</h3>
        <ul style="margin:0;padding-left:20px">
          <li class="small" style="margin:8px 0">Click "Export Mode" to enable checkboxes for bulk selection</li>
          <li class="small" style="margin:8px 0">Select multiple files and export to JSON with folder structure</li>
          <li class="small" style="margin:8px 0">Exported JSON includes: file paths, file types, class names, method lists, interfaces, and references</li>
          <li class="small" style="margin:8px 0">Share exported structure with team members or AI assistants for code analysis</li>
        </ul>
      </div>

      <div class="card">
        <h3>Quick Actions</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button style="display: none;" class="btn" onclick="location.href='analyze_code.php'">Re-analyze All</button>
          <button class="btn" onclick="location.reload()">Refresh</button>
        </div>
      </div>

    <?php else: ?>

      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
          <div style="flex:1">
            <h2><?php echo h(basename($file['path'])); ?></h2>
            <div class="small"><?php echo h($file['path']); ?></div>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn btn-success btn-sm" href="?action=export_json&file_id=<?php echo $fileId; ?>">Export JSON</a>
            <a style="display: none;" class="btn btn-sm" href="/analyze_code.php?file=<?php echo urlencode($file['path']); ?>">Re-analyze</a>
            <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $fileId; ?>)">Delete</button>
          </div>
        </div>

        <div class="kv"><div class="k">File Hash</div><div class="v"><?php echo h($file['file_hash']); ?></div></div>
        <div class="kv"><div class="k">Last Analyzed</div><div class="v"><?php echo h($file['last_analyzed_at'] ?? 'Never'); ?></div></div>
        <div class="kv"><div class="k">Chunks Processed</div><div class="v"><?php echo (int)$file['chunk_count']; ?></div></div>
      </div>

      <?php if (!empty($classes)): ?>
      <div class="card">
        <h3>Classes & Modules (<?php echo count($classes); ?>)</h3>
        <?php foreach ($classes as $c): ?>
          <div style="margin-bottom:16px;padding:14px;background:linear-gradient(180deg,rgba(255,255,255,0.02),transparent);border-radius:8px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
              <div>
                <div style="font-weight:700;font-size:16px"><?php echo h($c['class_name'] ?? '(Module)'); ?></div>
                <?php if ($c['extends_class']): ?>
                  <div class="small">extends: <?php echo h($c['extends_class']); ?></div>
                <?php endif; ?>
              </div>
              <div class="small">
                <?php 
                $methods = json_decode($c['methods'] ?? '[]', true);
                echo count($methods) . ' methods';
                ?>
              </div>
            </div>

            <?php 
            $interfaces = json_decode($c['interfaces'] ?? '[]', true);
            if (!empty($interfaces)): 
            ?>
              <div style="margin-bottom:8px">
                <span class="small" style="color:var(--muted)">Implements: </span>
                <?php foreach ($interfaces as $iface): ?>
                  <span class="method"><?php echo h($iface); ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($methods)): ?>
              <div style="margin-bottom:8px">
                <span class="small" style="color:var(--muted);display:block;margin-bottom:4px">Methods:</span>
                <?php foreach ($methods as $m): ?>
                  <span class="method"><?php echo h($m); ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if ($c['summary']): ?>
              <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.03)">
                <div class="small" style="color:var(--muted);margin-bottom:4px">Summary:</div>
                <div class="small" style="line-height:1.6"><?php echo nl2br(h($c['summary'])); ?></div>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="card">
        <h3>Classes & Modules</h3>
        <div class="small">No classes or modules found in this file.</div>
      </div>
      <?php endif; ?>

      <?php
      // Fetch raw analysis logs
      $stmt = $pdo->prepare('SELECT * FROM code_analysis_log WHERE file_id = ? ORDER BY chunk_index');
      $stmt->execute([$fileId]);
      $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
      ?>

      <div class="card">
        <h3>Analysis Logs (Raw AI Responses)</h3>
        <?php if (empty($logs)): ?>
          <div class="small">No log entries found</div>
        <?php else: ?>
          <?php foreach ($logs as $log): ?>
            <div style="margin-bottom:12px;padding:12px;border-radius:8px;background:#03131a">
              <div class="small" style="margin-bottom:8px">
                Chunk: <?php echo (int)$log['chunk_index']; ?> · 
                Tokens: <?php echo (int)$log['tokens_estimate']; ?> · 
                Provider: <?php echo h($log['provider']); ?> · 
                At: <?php echo h($log['created_at']); ?>
              </div>
              <div style="margin-top:8px">
                <?php
                  $raw = $log['raw_response'];
                  // Try to pretty-print JSON if possible
                  $maybe = null;
                  if (preg_match('/\{(?:[^{}]*|(?R))*\}/s', $raw, $m)) {
                      $maybe = @json_decode($m[0], true);
                  }
                  if (is_array($maybe)) {
                      echo '<pre style="background:#071422;padding:12px;border-radius:8px;overflow:auto;color:#cfe9ff;margin:0;font-size:12px;line-height:1.5">' . h(json_encode($maybe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '</pre>';
                  } else {
                      echo '<pre style="background:#071422;padding:12px;border-radius:8px;overflow:auto;color:#cfe9ff;margin:0;font-size:12px;line-height:1.5">' . h($raw) . '</pre>';
                  }
                ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    <?php endif; ?>

  </main>
</div>

<script>
let exportMode = false;

// localStorage key for tree state (versioned)
const TREE_STATE_KEY = 'codeboard_tree_state_v1';

/**
 * Save tree state by reading each <details> inside .tree and using the
 * summary's data-path attribute as the unique key.
 */
function saveTreeState() {
  try {
    const state = {};
    document.querySelectorAll('.tree details').forEach(detail => {
      const summary = detail.querySelector(':scope > summary');
      const path = summary?.dataset?.path?.trim();
      if (path) state[path] = !!detail.open;
    });
    localStorage.setItem(TREE_STATE_KEY, JSON.stringify(state));
  } catch (e) {
    console.warn('Failed to save tree state to localStorage:', e);
  }
}

/**
 * Restore tree state from localStorage (if present).
 */
function restoreTreeState() {
  try {
    const raw = localStorage.getItem(TREE_STATE_KEY);
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
    console.warn('Failed to restore tree state from localStorage:', e);
  }
}

// Immediately restore (ensures restore runs even if DOMContentLoaded already fired)
restoreTreeState();

// Also restore again on DOMContentLoaded (safe no-op if already restored)
document.addEventListener('DOMContentLoaded', function() {
  restoreTreeState();

  // Ensure file links save tree state before navigating away
  document.querySelectorAll('.file-link').forEach(link => {
    link.addEventListener('click', function (ev) {
      // Save synchronously — localStorage.setItem is synchronous in main thread
      saveTreeState();
      // navigation proceeds
    });
  });
});

// Save state when any details toggle occurs inside .tree
document.addEventListener('toggle', function (e) {
  if (e.target instanceof HTMLElement && e.target.closest('.tree')) {
    saveTreeState();
  }
}, true);

// Save before navigation/unload to make sure state is persisted
window.addEventListener('beforeunload', saveTreeState);

function toggleExportMode() {
  exportMode = !exportMode;
  const checkboxes = document.querySelectorAll('.file-checkbox');
  const bulkForm = document.getElementById('bulkForm');

  checkboxes.forEach(cb => {
    cb.style.display = exportMode ? 'block' : 'none';
    cb.checked = false;
  });

  bulkForm.style.display = exportMode ? 'block' : 'none';
  updateSelectedCount();
}

function updateSelectedCount() {
  const checked = document.querySelectorAll('.file-checkbox:checked').length;
  document.getElementById('selectedCount').textContent = checked + ' selected';
}

document.addEventListener('change', function(e) {
  if (e.target && e.target.classList && e.target.classList.contains('file-checkbox')) {
    updateSelectedCount();
  }
});

document.getElementById('selectAll')?.addEventListener('change', function() {
  const checkboxes = document.querySelectorAll('.file-checkbox');
  checkboxes.forEach(cb => cb.checked = this.checked);
  updateSelectedCount();
});

function bulkExport() {
  const checked = document.querySelectorAll('.file-checkbox:checked');
  if (checked.length === 0) {
    alert('Please select at least one file to export');
    return;
  }
  document.getElementById('bulkAction').value = 'bulk_export';
  document.getElementById('bulkForm').submit();
}

function bulkDelete() {
  const checked = document.querySelectorAll('.file-checkbox:checked');
  if (checked.length === 0) {
    alert('Please select at least one file to delete');
    return;
  }
  if (!confirm(`Delete ${checked.length} file(s)? This action cannot be undone.`)) {
    return;
  }
  document.getElementById('bulkAction').value = 'bulk_delete';
  document.getElementById('bulkForm').submit();
}

function confirmDelete(fileId) {
  if (confirm('Delete this file and all its analysis data? This action cannot be undone.')) {
    location.href = '?action=delete&file_id=' + fileId;
  }
}

// Prevent form submission on file link clicks in export mode
document.addEventListener('click', function(e) {
  if (exportMode && e.target.classList.contains('file-link')) {
    e.preventDefault();
    const row = e.target.closest('.file-row');
    const checkbox = row?.querySelector('.file-checkbox');
    if (checkbox) {
      checkbox.checked = !checkbox.checked;
      updateSelectedCount();
    }
  }
});
</script>
</body>
</html>
