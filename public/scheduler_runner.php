<?php
// public/scheduler_runner.php
// Control Deck v5: Auto-expand on manual log selection

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;

// --- 1. HANDLE AJAX ACTIONS ---

if (isset($_POST['action']) || isset($_GET['action'])) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action) {
        header('Content-Type: application/json');
        try {
            // A. Trigger a Wrapper
            if ($action === 'run_wrapper') {
                $wrapperId = (int)$_POST['id'];
                
                $stmt = $pdo->prepare("SELECT w.*, t.name as task_name FROM task_wrappers w JOIN scheduled_tasks t ON w.task_id = t.id WHERE w.id = :id");
                $stmt->execute(['id' => $wrapperId]);
                $wrapper = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$wrapper) throw new Exception("Wrapper not found");

                $upd = $pdo->prepare("UPDATE scheduled_tasks SET args = :args, run_now = 1 WHERE id = :tid");
                $upd->execute([
                    'args' => $wrapper['fixed_args'],
                    'tid'  => $wrapper['task_id']
                ]);

                echo json_encode(['ok' => true, 'msg' => "Queued: {$wrapper['name']}"]);
                exit;
            }

            // B. Save Wrapper
            if ($action === 'save_wrapper') {
                $id = (int)($_POST['wrapper_id'] ?? 0);
                $data = [
                    'task_id'       => (int)$_POST['task_id'],
                    'name'          => $_POST['name'],
                    'summary'       => $_POST['summary'],
                    'fixed_args'    => $_POST['fixed_args'],
                    'icon'          => $_POST['icon'] ?? '🚀',
                    'display_order' => (int)$_POST['display_order']
                ];

                if ($id > 0) {
                    $sql = "UPDATE task_wrappers SET task_id=:task_id, name=:name, summary=:summary, fixed_args=:fixed_args, icon=:icon, display_order=:display_order WHERE id=$id";
                    $pdo->prepare($sql)->execute($data);
                } else {
                    $sql = "INSERT INTO task_wrappers (task_id, name, summary, fixed_args, icon, display_order) VALUES (:task_id, :name, :summary, :fixed_args, :icon, :display_order)";
                    $pdo->prepare($sql)->execute($data);
                }
                echo json_encode(['ok' => true]);
                exit;
            }

            // C. Delete Wrapper
            if ($action === 'delete_wrapper') {
                $id = (int)$_POST['id'];
                $pdo->prepare("DELETE FROM task_wrappers WHERE id = ?")->execute([$id]);
                echo json_encode(['ok' => true]);
                exit;
            }

            // D. Get Wrapper
            if ($action === 'get_wrapper') {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("SELECT * FROM task_wrappers WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['ok' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
                exit;
            }

            // E. List Log Files
            if ($action === 'list_logs') {
                $logsDir = __DIR__ . '/../logs';
                $files = glob($logsDir . '/*.log');
                
                // Filter: No 'err', No '_exit.log'
                $files = array_filter($files, function($f) {
                    $base = basename($f);
                    if (stripos($base, 'err') !== false) return false;
                    if (stripos($base, '_exit.log') !== false) return false;
                    return true;
                });
                
                // Sort by time (newest first)
                usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
                
                $files = array_slice($files, 0, 50);

                $data = array_map(fn($f) => [
                    'name' => basename($f),
                    'ts'   => filemtime($f),
                    'time' => date('H:i:s', filemtime($f))
                ], $files);

                echo json_encode(['ok' => true, 'files' => $data]);
                exit;
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

// --- 2. INITIAL DATA ---
$wrappers = $pdo->query("
    SELECT w.*, t.name as original_task_name 
    FROM task_wrappers w 
    LEFT JOIN scheduled_tasks t ON w.task_id = t.id 
    ORDER BY w.display_order ASC, w.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$allTasks = $pdo->query("SELECT id, name, args FROM scheduled_tasks ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Control Deck</title>
<script>
(function() {
    try {
        var theme = localStorage.getItem('spw_theme');
        if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
    } catch (e) {}
})();
</script>
<?php echo SpwBase::getInstance()->getJquery(); ?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<style>
    :root {
        --console-bg: #0f172a;
        --console-text: #4ade80;
    }

    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 10px; max-width: 800px; margin: 0 auto; }

    .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .header-title h1 { margin: 0; font-size: 1.4rem; display: flex; align-items: center; gap: 8px; }

    /* --- ANIMATED CONSOLE --- */
    .console-wrapper {
        border-radius: 8px; overflow: hidden;
        border: 1px solid rgba(128,128,128,0.3);
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        display: flex; flex-direction: column;
        transition: box-shadow 0.3s;
    }
    
    .console-header {
        background: #1e293b; color: #fff;
        padding: 8px 10px; font-size: 0.85rem;
        display: flex; justify-content: space-between; align-items: center; gap: 10px;
        cursor: pointer; /* Indicates clickable */
        user-select: none;
    }
    .console-header:hover { background: #334155; }
    
    .log-box {
        background: var(--console-bg); color: var(--console-text);
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        white-space: pre-wrap; font-size: 0.8rem; line-height: 1.3;
        overflow-y: auto;
        
        /* Animation Props */
        height: 0; 
        padding: 0 10px; 
        opacity: 0;
        transition: height 0.3s ease, padding 0.3s ease, opacity 0.2s ease;
    }
    
    .log-box.expanded {
        height: 220px;
        padding: 10px;
        opacity: 1;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    
    /* Chevron Indicator */
    .toggle-icon { transition: transform 0.3s; display: inline-block; font-size: 0.8rem; margin-right: 5px; opacity: 0.7; }
    .console-wrapper.open .toggle-icon { transform: rotate(90deg); }

    /* Controls inside header */
    #logFileSelect {
        background: #334155; color: #fff; border: 1px solid #475569;
        padding: 2px 5px; border-radius: 4px; font-size: 0.8rem;
        max-width: 250px;
    }
    
    /* Wrapper List */
    .wrapper-list { display: flex; flex-direction: column; gap: 8px; }

    .task-row {
        background: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.3);
        border-radius: 8px; padding: 6px 10px;
        display: flex; align-items: center; gap: 10px;
        transition: transform 0.1s;
    }
    .task-row:hover { border-color: var(--accent); }

    .row-icon { font-size: 1.4rem; line-height: 1; flex-shrink: 0; }
    .row-info { flex: 1; min-width: 0; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .row-title { font-weight: 700; font-size: 1rem; color: var(--text); white-space: nowrap; }
    .row-pill { 
        background: rgba(var(--accent-rgb), 0.15); color: var(--text-muted); 
        border-radius: 12px; padding: 2px 8px; font-size: 0.75rem; 
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;
    }

    .row-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

    .btn-run {
        background: var(--btn); color: var(--text); border: none; border-radius: 5px;
        width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; cursor: pointer; transition: background 0.2s;
    }
    .btn-run:hover { background: var(--accent); color: #fff; }
    
    .btn-edit {
        background: transparent; border: none; color: var(--text-muted);
        font-size: 1rem; cursor: pointer; padding: 5px;
    }
    .btn-edit:hover { color: var(--text); }

    .add-row {
        border: 2px dashed rgba(var(--muted-border-rgb), 0.3);
        justify-content: center; color: var(--text-muted);
        cursor: pointer; padding: 10px; font-weight: bold;
    }

    /* Modal */
    .modal-overlay {
        position: fixed; top:0; left:0; width:100%; height:100%;
        background: rgba(0,0,0,0.7); z-index: 1000;
        display: none; justify-content: center; align-items: center;
    }
    .modal-content {
        background: var(--card); width: 90%; max-width: 450px;
        border-radius: 12px; padding: 20px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    }
    .form-group { margin-bottom: 12px; }
    .form-control { width: 100%; padding: 8px; border-radius: 6px; border: 1px solid rgba(128,128,128,0.3); background: var(--bg); color: var(--text); }
    .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px; }

    .pulse-green { animation: pulse-green 1.5s infinite; }
    @keyframes pulse-green { 0% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.4); } 70% { box-shadow: 0 0 0 6px rgba(74, 222, 128, 0); } 100% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0); } }
</style>
</head>
<body>

    <div class="header-bar">
        <div class="header-title"><h1>🚀 Control Deck</h1></div>
        <div style="padding-right:45px;"><a href="scheduler_view.php" class="btn btn-secondary btn-sm">Full Scheduler</a></div>
    </div>

    <!-- EXPANDABLE LOG CONSOLE -->
    <div class="console-wrapper" id="consoleWrapper">
        <div class="console-header" id="consoleHeader">
            <div style="display:flex; align-items:center; gap:8px; flex:1; min-width:0;">
                <span class="toggle-icon">▶</span>
                <select id="logFileSelect" onclick="event.stopPropagation()"></select>
            </div>
            <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;" onclick="event.stopPropagation()">
                <label style="font-size:0.75rem; cursor:pointer;">
                    <input type="checkbox" id="autoScroll" checked> Auto
                </label>
                <button class="btn btn-xs" id="refreshLogBtn">↺</button>
            </div>
        </div>
        <div class="log-box" id="logBox">Initializing...</div>
    </div>

    <!-- WRAPPER LIST -->
    <div class="wrapper-list" id="wrapperContainer">
        <?php foreach($wrappers as $w): ?>
        <div class="task-row" data-id="<?= $w['id'] ?>">
            <div class="row-icon"><?= htmlspecialchars($w['icon']) ?></div>
            <div class="row-info">
                <span class="row-title"><?= htmlspecialchars($w['name']) ?></span>
                <?php if(!empty($w['summary'])): ?>
                    <span class="row-pill"><?= htmlspecialchars($w['summary']) ?></span>
                <?php endif; ?>
            </div>
            <div class="row-actions">
                <button class="btn-run" onclick="runWrapper(<?= $w['id'] ?>, this)">▶</button>
                <button class="btn-edit" onclick="editWrapper(this)">✎</button>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="task-row add-row" onclick="openModal()">+ Add Preset</div>
    </div>

    <!-- MODAL -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-content">
            <h3 id="modalTitle" style="margin-top:0;">New Task Preset</h3>
            <form id="wrapperForm">
                <input type="hidden" name="action" value="save_wrapper">
                <input type="hidden" name="wrapper_id" id="inp_id" value="">
                
                <div class="form-group">
                    <label>Target Scheduler Task</label>
                    <select name="task_id" id="inp_task_id" class="form-control" required>
                        <?php foreach($allTasks as $t): ?>
                            <option value="<?= $t['id'] ?>" data-def-args="<?= htmlspecialchars($t['args']??'') ?>">
                                <?= htmlspecialchars($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;">
                        <label>Button Name</label>
                        <input type="text" name="name" id="inp_name" class="form-control" required>
                    </div>
                    <div class="form-group" style="width:60px;">
                        <label>Icon</label>
                        <input type="text" name="icon" id="inp_icon" class="form-control" value="🚀">
                    </div>
                </div>
                <div class="form-group">
                    <label>Summary (Pill Text)</label>
                    <input type="text" name="summary" id="inp_summary" class="form-control">
                </div>
                <div class="form-group">
                    <label>Fixed Arguments</label>
                    <textarea name="fixed_args" id="inp_args" class="form-control" rows="2" style="font-family:monospace;"></textarea>
                    <div style="text-align:right; font-size:0.7em;">
                        <a href="#" onclick="copyDefaultArgs(event)">Copy Default</a>
                    </div>
                </div>
                <div class="form-group">
                    <label>Order</label>
                    <input type="number" name="display_order" id="inp_order" class="form-control" value="0">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-danger btn-sm" id="deleteBtn" style="display:none;" onclick="deleteWrapper()">Delete</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast-container"></div>
    <script src="/js/toast.js"></script>
    <script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

    <script>
        // --- VIEW LOGIC ---
        const logBox = document.getElementById('logBox');
        const logSelect = document.getElementById('logFileSelect');
        const consoleWrapper = document.getElementById('consoleWrapper');
        const consoleHeader = document.getElementById('consoleHeader');

        // Toggle Expand
        consoleHeader.addEventListener('click', () => {
            logBox.classList.toggle('expanded');
            consoleWrapper.classList.toggle('open');
        });

        // Helper to force open
        function openConsole() {
            if (!logBox.classList.contains('expanded')) {
                logBox.classList.add('expanded');
                consoleWrapper.classList.add('open');
            }
        }

        // --- LOG LOGIC ---
        let currentFile = "";
        let pendingAutoSwitch = false; 

        function updateLogList(andFetchContent = false) {
            return fetch('?action=list_logs')
                .then(r => r.json())
                .then(res => {
                    if(!res.ok) return;

                    const html = res.files.length === 0 
                        ? '<option value="">(No logs)</option>' 
                        : res.files.map(f => `<option value="${f.name}">${f.name} (${f.time})</option>`).join('');

                    const prevFile = logSelect.value;
                    if(logSelect.innerHTML !== html) {
                        logSelect.innerHTML = html;
                        
                        const newestTaskLog = res.files.find(f => f.name.includes('task_run_') && f.name.includes('_out.log'));
                        
                        // 1. Waiting for switch after run
                        if (pendingAutoSwitch && newestTaskLog && newestTaskLog.name !== prevFile) {
                            logSelect.value = newestTaskLog.name;
                            pendingAutoSwitch = false; 
                            Toast.show('Log: ' + newestTaskLog.name, 'info');
                            fetchContent();
                        }
                        // 2. Initial Load
                        else if (!prevFile && res.files.length > 0) {
                            logSelect.selectedIndex = 0;
                            if(andFetchContent) fetchContent();
                        }
                        // 3. Keep selection
                        else if (prevFile && res.files.some(f => f.name === prevFile)) {
                            logSelect.value = prevFile;
                        }
                    }
                });
        }

        function fetchContent() {
            currentFile = logSelect.value;
            if(!currentFile) return;

            fetch('scheduler_log_fetch.php?file=' + encodeURIComponent(currentFile))
                .then(r => r.text())
                .then(txt => {
                    logBox.textContent = txt;
                    if(document.getElementById('autoScroll').checked) {
                        logBox.scrollTop = logBox.scrollHeight;
                    }
                });
        }

        // --- BUTTONS ---
        function runWrapper(id, btn) {
            const originalIcon = btn.innerHTML;
            btn.innerHTML = '⏳';
            btn.classList.add('pulse-green');

            // 1. Force Open Log
            openConsole();
            
            // 2. Watch for new file
            pendingAutoSwitch = true;

            const fd = new FormData();
            fd.append('action', 'run_wrapper');
            fd.append('id', id);

            fetch('scheduler_runner.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if(res.ok) {
                        Toast.show(res.msg, 'success');
                        setTimeout(() => updateLogList(true), 2500);
                    } else {
                        Toast.show(res.error, 'error');
                        pendingAutoSwitch = false;
                    }
                })
                .catch(() => { pendingAutoSwitch = false; })
                .finally(() => {
                    setTimeout(() => {
                        btn.innerHTML = originalIcon;
                        btn.classList.remove('pulse-green');
                    }, 500);
                });
        }

        // --- EVENTS ---
        
        // Auto-expand on manual selection
        logSelect.addEventListener('change', function() {
            openConsole();
            fetchContent();
        });

        document.getElementById('refreshLogBtn').onclick = fetchContent;
        setInterval(updateLogList, 4000); 
        setInterval(fetchContent, 2000);  
        updateLogList(true);

        // --- MODAL (CRUD) ---
        const modal = document.getElementById('editModal');
        function openModal(data = null) {
            document.getElementById('deleteBtn').style.display = data ? 'block' : 'none';
            document.getElementById('modalTitle').textContent = data ? 'Edit Preset' : 'New Preset';
            if(data) {
                document.getElementById('inp_id').value = data.id;
                document.getElementById('inp_task_id').value = data.task_id;
                document.getElementById('inp_name').value = data.name;
                document.getElementById('inp_summary').value = data.summary;
                document.getElementById('inp_args').value = data.fixed_args;
                document.getElementById('inp_icon').value = data.icon;
                document.getElementById('inp_order').value = data.display_order;
            } else {
                document.getElementById('wrapperForm').reset();
                document.getElementById('inp_id').value = 0;
            }
            modal.style.display = 'flex';
        }
        function closeModal() { modal.style.display = 'none'; }
        function editWrapper(btn) {
            const id = btn.closest('.task-row').dataset.id;
            const fd = new FormData(); fd.append('action', 'get_wrapper'); fd.append('id', id);
            fetch('scheduler_runner.php', {method:'POST', body:fd}).then(r=>r.json()).then(res=>{if(res.ok)openModal(res.data)});
        }
        document.getElementById('wrapperForm').onsubmit = function(e) {
            e.preventDefault();
            fetch('scheduler_runner.php', {method:'POST', body:new FormData(this)}).then(r=>r.json()).then(res=>{if(res.ok)location.reload()});
        };
        function deleteWrapper() {
            if(!confirm('Delete?')) return;
            const fd = new FormData(); fd.append('action', 'delete_wrapper'); fd.append('id', document.getElementById('inp_id').value);
            fetch('scheduler_runner.php', {method:'POST', body:fd}).then(r=>r.json()).then(res=>{if(res.ok)location.reload()});
        }
        function copyDefaultArgs(e) {
            e.preventDefault();
            const s = document.getElementById('inp_task_id');
            document.getElementById('inp_args').value = s.options[s.selectedIndex].getAttribute('data-def-args');
        }
        modal.onclick = function(e) { if(e.target === modal) closeModal(); }
    </script>
</body>
</html>
