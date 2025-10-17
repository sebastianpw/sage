<?php
// generator_admin.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$pdo = $pdo ?? $spw->getPDO(); // env_locals provides $pdo
$errors = [];
$success = null;

// Helper: generate random session_id string (32 hex chars)
function genSessionId(): string {
    return bin2hex(random_bytes(16));
}

// Handle actions
$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action === 'create') {
    $session_id_str = trim($_POST['session_id_str'] ?? '');
    $title = trim($_POST['title'] ?? 'Generator');
    $model = trim($_POST['model'] ?? 'openai');
    $type = 'generator';
    $message_json = trim($_POST['message_json'] ?? '');

    if ($session_id_str === '') {
        $session_id_str = genSessionId();
    }

    if ($message_json === '') {
        $errors[] = "Message JSON must not be empty.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO chat_session (session_id, user_id, title, created_at, model, `type`) VALUES (:session_id, :user_id, :title, NOW(), :model, :type)");
            $stmt->execute([
                ':session_id' => $session_id_str,
                ':user_id' => $_SESSION['user_id'],
                ':title' => $title,
                ':model' => $model,
                ':type' => $type,
            ]);
            $sessionPk = (int)$pdo->lastInsertId();

            $stmt2 = $pdo->prepare("INSERT INTO chat_message (session_id, role, content, created_at) VALUES (:sid, :role, :content, NOW())");
            $stmt2->execute([
                ':sid' => $sessionPk,
                ':role' => 'system',
                ':content' => $message_json,
            ]);

            $pdo->commit();
            $success = "Generator session created (id={$sessionPk}, session_id={$session_id_str})";
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $errors[] = "DB error: " . $e->getMessage();
        }
    }
} elseif ($action === 'delete' && !empty($_POST['id'])) {
    $id = (int)$_POST['id'];
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("DELETE FROM chat_message WHERE session_id = :id");
        $st->execute([':id' => $id]);
        $st2 = $pdo->prepare("DELETE FROM chat_session WHERE id = :id");
        $st2->execute([':id' => $id]);
        $pdo->commit();
        $success = "Deleted generator session id {$id}";
    } catch (\Throwable $e) {
        $pdo->rollBack();
        $errors[] = "Delete failed: " . $e->getMessage();
    }
} elseif ($action === 'update' && !empty($_POST['id'])) {
    $id = (int)$_POST['id'];
    $title = trim($_POST['title'] ?? '');
    $model = trim($_POST['model'] ?? 'openai');
    $session_id_str = trim($_POST['session_id_str'] ?? '');
    $message_json = trim($_POST['message_json'] ?? '');

    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("UPDATE chat_session SET session_id = :sidstr, title = :title, model = :model WHERE id = :id");
        $st->execute([
            ':sidstr' => $session_id_str,
            ':title' => $title,
            ':model' => $model,
            ':id' => $id,
        ]);

        // update first system message (assumes first message is instruction)
        $stm = $pdo->prepare("SELECT id FROM chat_message WHERE session_id = :sid ORDER BY created_at ASC LIMIT 1");
        $stm->execute([':sid' => $id]);
        $first = $stm->fetchColumn();
        if ($first) {
            $stu = $pdo->prepare("UPDATE chat_message SET content = :content WHERE id = :mid");
            $stu->execute([':content' => $message_json, ':mid' => $first]);
        } else {
            // create if none
            $sti = $pdo->prepare("INSERT INTO chat_message (session_id, role, content, created_at) VALUES (:sid, 'system', :content, NOW())");
            $sti->execute([':sid' => $id, ':content' => $message_json]);
        }

        $pdo->commit();
        $success = "Updated session id {$id}";
    } catch (\Throwable $e) {
        $pdo->rollBack();
        $errors[] = "Update failed: " . $e->getMessage();
    }
}

// Fetch generator sessions
$rows = $pdo->prepare("SELECT id, session_id, user_id, title, created_at, model FROM chat_session WHERE `type` = 'generator' ORDER BY created_at DESC");
$rows->execute();
$sessions = $rows->fetchAll(PDO::FETCH_ASSOC);

// Helper: pretty default JSON for new entries
$defaultJSON = json_encode([
    "system" => [
        "role" => "Zungenbrecher Oracle",
        "instructions" => [
            "You are an expert Zungenbrecher generator (tongue-twister generator) in German and English.",
            "Generate creative, linguistically challenging, and fun tongue-twisters.",
            "Always produce output in JSON format with the structure defined below.",
            "Do not provide explanations, only the JSON output."
        ]
    ],
    "parameters" => new stdClass()
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generator admin</title>
    <style>
        :root{
            --bg:#f7f8fb;
            --card:#ffffff;
            --muted:#6b7280;
            --accent:#111827;
            --accent-2:#2563eb;
            --danger:#ef4444;
            --radius:10px;
            --glass: rgba(255,255,255,0.6);
            --mono: ui-monospace, SFMono-Regular, Menlo, Monaco, "Roboto Mono", "Courier New", monospace;
            --gap:14px;
        }

        *{box-sizing:border-box}
        body{
            margin:0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            background:linear-gradient(180deg,#f3f6f9,var(--bg));
            color:#111827;
            padding:18px;
            -webkit-font-smoothing:antialiased;
        }

        header{
            display:flex;
            gap:12px;
            align-items:center;
            justify-content:space-between;
            margin-bottom:18px;
        }
        header h1{
            font-size:18px;
            margin:0;
            letter-spacing:-0.2px;
        }
        header .meta{
            color:var(--muted);
            font-size:13px;
        }

        .container{
            display:grid;
            grid-template-columns: 1fr;
            gap:18px;
            max-width:1100px;
            margin:0 auto;
        }

        /* Card */
        .card{
            background:var(--card);
            border-radius:var(--radius);
            padding:16px;
            box-shadow: 0 6px 18px rgba(16,24,40,0.06);
            border:1px solid rgba(15,23,42,0.04);
        }

        .notice { padding:10px 12px; border-radius:8px; font-size:14px; margin-bottom:10px; }
        .ok{ background: rgba(16,185,129,0.08); color:#065f46; border:1px solid rgba(16,185,129,0.12); }
        .err{ background: rgba(239,68,68,0.06); color:var(--danger); border:1px solid rgba(239,68,68,0.08); }

        form.inline-row{
            display:grid;
            grid-template-columns: 1fr auto;
            gap:10px;
            align-items:start;
        }

        label{ display:block; font-size:13px; color:var(--muted); margin-bottom:6px; }
        input[type=text], select {
            width:100%;
            padding:10px 12px;
            border-radius:8px;
            border:1px solid #e6e9ee;
            background: linear-gradient(180deg,#fff,#fbfdff);
            font-size:14px;
        }
        textarea{
            width:100%;
            min-height:220px;
            padding:12px;
            border-radius:8px;
            border:1px solid #e6e9ee;
            resize:vertical;
            font-family: var(--mono);
            font-size:13px;
            background:#fafbff;
        }
        .muted{ color:var(--muted); font-size:13px; margin-bottom:8px; }

        .btn {
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:10px 12px;
            background:var(--accent-2);
            color:#fff;
            border-radius:8px;
            border:0;
            cursor:pointer;
            font-size:14px;
            text-decoration:none;
        }
        .btn.secondary{
            background:transparent;
            color:var(--accent);
            border:1px solid #e6e9ee;
        }
        .btn.ghost{ background:transparent; color:var(--accent-2); border:1px dashed rgba(37,99,235,0.12); }
        .btn.danger{ background:var(--danger); color:#fff; }

        /* Table: responsive stack */
        .table-wrap { overflow:hidden; border-radius:10px; border:1px solid rgba(15,23,42,0.04); }
        table.admin-table{ width:100%; border-collapse:collapse; min-width:400px;}
        thead th{
            text-align:left;
            padding:12px 14px;
            font-size:13px;
            color:var(--muted);
            background:linear-gradient(180deg, rgba(0,0,0,0.01), transparent);
            border-bottom:1px solid #eef2f7;
        }
        tbody td{
            padding:12px 14px;
            border-bottom:1px solid #f1f5f9;
            vertical-align:middle;
            font-size:14px;
            color:var(--accent);
        }
        td.actions{ white-space:nowrap; }

        .small-muted{ color:var(--muted); font-size:13px; }

        /* mobile: collapse rows into cards */
        @media (max-width:820px){
            table.admin-table{ border:none; min-width:unset; }
            thead{ display:none; }
            tbody tr{
                display:block;
                margin:10px;
                box-shadow:none;
                border-radius:10px;
                background:linear-gradient(180deg,#fff,#fcfeff);
                border:1px solid rgba(15,23,42,0.03);
            }
            tbody td{
                display:flex;
                justify-content:space-between;
                align-items:center;
                padding:12px;
                border-bottom:0;
                font-size:14px;
            }
            tbody td[data-label]:before{
                content: attr(data-label) ": ";
                color:var(--muted);
                margin-right:8px;
                font-weight:600;
            }
            .row-actions{ display:flex; gap:8px; align-items:center; }
        }

        /* desktop tweaks */
        @media (min-width:821px){
            .form-grid{
                display:grid;
                grid-template-columns: 1fr 360px;
                gap:16px;
                align-items:start;
            }
            .form-actions{ display:flex; gap:8px; justify-content:flex-end; align-items:center; }
        }

        footer{ color:var(--muted); font-size:13px; text-align:center; margin-top:18px; }
        a.small-link{ color:var(--accent-2); text-decoration:none; font-size:13px; }
        code.sid { background:#f3f4f6; padding:4px 8px; border-radius:6px; font-family:var(--mono); font-size:12px; color:#0f172a; }
    </style>
</head>
<body>
<header>
    <div>
        <h1>Generator Admin (JSON-driven)</h1>
        <div class="meta">Create, edit and run JSON-driven generator sessions</div>
    </div>
    <div class="meta">Logged in as user #<?=htmlspecialchars($_SESSION['user_id'] ?? 'guest')?></div>
</header>

<div class="container">

    <div class="card">
        <?php if ($success): ?>
            <div class="notice ok"><?=htmlspecialchars($success)?></div>
        <?php endif; ?>
        <?php if ($errors): foreach ($errors as $er): ?>
            <div class="notice err"><?=htmlspecialchars($er)?></div>
        <?php endforeach; endif; ?>

        <h2 style="margin:0 0 10px 0; font-size:15px;">Create new generator session</h2>

        <form method="post" class="form-grid" onsubmit="return confirmCreate(this);">
            <div>
                <input type="hidden" name="action" value="create">

                <label>session_id (leave empty to auto-generate)</label>
                <input type="text" name="session_id_str" placeholder="auto-generated if empty">

                <div style="height:12px"></div>

                <label>Title</label>
                <input type="text" name="title" value="New Generator">

                <div style="height:12px"></div>

                <label>Model</label>
                <input type="text" name="model" value="openai">
            </div>

            <div>
                <label>Instruction JSON (system message)</label>
                <textarea name="message_json" id="message_json"><?php echo htmlspecialchars($defaultJSON); ?></textarea>

                <div style="display:flex; gap:8px; margin-top:10px; justify-content:flex-end;">
                    <button type="button" class="btn secondary" onclick="document.getElementById('message_json').value = `<?=str_replace("`","\\`",htmlspecialchars($defaultJSON))?>`;document.getElementById('message_json').focus();">Reset</button>
                    <button type="submit" class="btn">Create generator session</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin:0 0 10px 0; font-size:15px;">Existing generator sessions</h2>

        <div class="table-wrap" role="region" aria-label="Generator sessions">
            <table class="admin-table" role="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>session_id</th>
                        <th>title</th>
                        <th>model</th>
                        <th>created_at</th>
                        <th>actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sessions as $s): ?>
                    <tr>
                        <td data-label="ID"><?=htmlspecialchars($s['id'])?></td>
                        <td data-label="session_id">
                            <code class="sid" id="sid-<?=htmlspecialchars($s['id'])?>"><?=htmlspecialchars($s['session_id'])?></code>
                            <button onclick="copySid('sid-<?=htmlspecialchars($s['id'])?>')" title="Copy session id" style="margin-left:8px" class="btn secondary" type="button">Copy</button>
                        </td>
                        <td data-label="title"><?=htmlspecialchars($s['title'] ?: '-');?></td>
                        <td data-label="model"><?=htmlspecialchars($s['model'] ?: '-');?></td>
                        <td data-label="created_at"><span class="small-muted"><?=htmlspecialchars($s['created_at'])?></span></td>
                        <td class="actions" data-label="actions">
                            <div class="row-actions">
                                <a class="btn ghost" target="_blank" href="/generate_ajax.php?sessionId=<?=urlencode($s['session_id'])?>">Run</a>
                                <a class="btn secondary" href="?action=edit&id=<?=urlencode($s['id'])?>">Edit</a>

                                <form method="post" style="display:inline" onsubmit="return confirm('Delete session?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?=htmlspecialchars($s['id'])?>">
                                    <button type="submit" class="btn danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($sessions) === 0): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--muted); padding:28px;">No generator sessions yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    // Edit view
    if (($editId = (int)($_GET['id'] ?? 0)) > 0) {
        $stmt = $pdo->prepare("SELECT id, session_id, title, model FROM chat_session WHERE id = :id AND `type` = 'generator' LIMIT 1");
        $stmt->execute([':id' => $editId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $stm2 = $pdo->prepare("SELECT content FROM chat_message WHERE session_id = :sid ORDER BY created_at ASC LIMIT 1");
            $stm2->execute([':sid' => $row['id']]);
            $firstMsg = $stm2->fetchColumn();
            ?>
            <div class="card" id="edit-card">
                <h2 style="margin:0 0 10px 0; font-size:15px;">Edit session #<?=htmlspecialchars($row['id'])?></h2>
                <form method="post">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?=htmlspecialchars($row['id'])?>">
                    <label>session_id string</label>
                    <input type="text" name="session_id_str" value="<?=htmlspecialchars($row['session_id'])?>">

                    <div style="height:12px"></div>

                    <label>Title</label>
                    <input type="text" name="title" value="<?=htmlspecialchars($row['title'])?>">

                    <div style="height:12px"></div>

                    <label>Model</label>
                    <input type="text" name="model" value="<?=htmlspecialchars($row['model'])?>">

                    <div style="height:12px"></div>

                    <label>Instruction JSON (system message)</label>
                    <textarea name="message_json" id="edit_message_json"><?=htmlspecialchars($firstMsg)?></textarea>

                    <div style="display:flex; gap:8px; margin-top:10px;">
                        <button type="submit" class="btn">Save</button>
                        <a class="btn secondary" href="generator_admin.php">Close</a>
                    </div>
                </form>
            </div>
            <?php
        } else {
            echo "<div class='card'><p>No such generator session.</p></div>";
        }
    }
    ?>

    <footer class="card" style="text-align:center;">
        <div class="small-muted">Minimalist admin • Responsive • Mobile friendly</div>
        <div style="margin-top:6px"><a class="small-link" href="/chat.php">Back to Chat</a></div>
    </footer>
</div>

<script>
    // copy session id
    function copySid(elemId){
        try{
            const el = document.getElementById(elemId);
            if(!el) return;
            const text = el.textContent.trim();
            navigator.clipboard.writeText(text).then(function(){
                const btn = el.nextElementSibling;
                const old = btn.innerHTML;
                btn.innerHTML = 'Copied';
                setTimeout(()=> btn.innerHTML = old, 1200);
            });
        }catch(e){
            console.warn('copy failed', e);
            alert('Copy failed — please select and copy manually.');
        }
    }

    // confirm create: validate JSON roughly
    function confirmCreate(form){
        const ta = form.querySelector('textarea[name="message_json"]');
        if(!ta) return true;
        const val = ta.value.trim();
        if(val === ''){
            alert('Instruction JSON must not be empty.');
            return false;
        }
        // quick sanity: try to parse
        try{
            JSON.parse(val);
            return true;
        }catch(e){
            if(!confirm('Instruction JSON is not valid JSON. Create anyway?')) return false;
            return true;
        }
    }

    // auto-resize textareas
    (function(){
        function autosize(el){
            el.style.height = 'auto';
            el.style.height = (el.scrollHeight + 4) + 'px';
        }
        document.querySelectorAll('textarea').forEach(function(t){
            autosize(t);
            t.addEventListener('input',function(){ autosize(t); });
        });
    })();

    // small enhancement: show/hide edit card on open (smooth scroll)
    (function(){
        const editCard = document.getElementById('edit-card');
        if(editCard){
            editCard.scrollIntoView({behavior:'smooth', block:'center'});
        }
    })();
</script>
</body>
</html>
