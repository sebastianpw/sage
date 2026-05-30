<?php
// public/view_json.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; 

$spw = \App\Core\SpwBase::getInstance();
$docId = $_GET['id'] ?? null;
$download = $_GET['download'] ?? false;
$filterCatId = $_GET['category_id'] ?? ''; 
$filterSort = $_GET['sort'] ?? 'created_desc';

// ==========================================
// 1. READ MODE (Single JSON File)
// ==========================================
if ($docId) {
    // Fetch Document
    $stmt = $pdo->prepare("SELECT * FROM json_files WHERE id = ?");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        $spw->renderLayout("<div class='alert alert-danger'>File not found (ID: $docId)</div>", "404 Not Found");
        exit;
    }

    // Download Logic
    if ($download) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9]+/', '_', strtolower($doc['name'])) . '.json"');
        echo $doc['content'];
        exit;
    }

    $pageTitle = htmlspecialchars($doc['name']);
    
    // Back Link Logic
    $backLink = "view_json.php";
    $queryParams = [];
    if ($filterCatId !== '') $queryParams['category_id'] = $filterCatId;
    if ($filterSort !== 'created_desc') $queryParams['sort'] = $filterSort;
    
    if (!empty($queryParams)) {
        $backLink .= '?' . http_build_query($queryParams);
    }

    $editLink = "edit_json.php?id=" . $docId;
    if (!empty($queryParams)) {
        $editLink .= "&" . http_build_query($queryParams);
    }

    // Pretty Print JSON for display
    $jsonContent = $doc['content'];
    $prettyJson = $jsonContent;
    try {
        $decoded = json_decode($jsonContent);
        if($decoded !== null) {
            $prettyJson = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    } catch(Exception $e) {}

    ob_start();
    ?>
    <!-- THEME INIT SCRIPT -->
    <script>
        (function() {
            try {
                var theme = localStorage.getItem('spw_theme');
                if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
                else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
            } catch (e) {}
        })();
    </script>

    <!-- Syntax Highlighter -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/atom-one-dark.min.css" id="hl-dark">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/atom-one-light.min.css" id="hl-light" disabled>

    <style>
        :root[data-theme="dark"] {
            --bg: #0d1117;
            --card: #161b22;
            --border: #30363d;
            --text: #c9d1d9;
            --text-muted: #8b949e;
            --accent: #58a6ff;
            --code-bg: #0d1117;
        }
        :root[data-theme="light"] {
            --bg: #ffffff;
            --card: #f6f8fa;
            --border: #d0d7de;
            --text: #24292f;
            --text-muted: #57606a;
            --accent: #0969da;
            --code-bg: #ffffff;
        }

        body { background-color: var(--bg) !important; color: var(--text) !important; transition: background-color 0.2s, color 0.2s; }
        
        .view-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .nav-bar { 
            margin-bottom: 20px; padding: 12px 15px; 
            background: var(--card); border-bottom: 1px solid var(--border); 
            border-radius: 6px; display: flex; justify-content: space-between; align-items: center; 
        }
        .nav-bar a { color: var(--accent); text-decoration: none; font-weight: 600; }
        .nav-bar a:hover { text-decoration: underline; }

        pre { margin: 0; padding: 15px; border-radius: 6px; border: 1px solid var(--border); background-color: var(--code-bg); overflow: auto; }
        code { font-family: 'Consolas', 'Monaco', monospace; font-size: 14px; line-height: 1.5; }
        
        .meta-box { margin-bottom: 20px; padding: 10px; background: var(--card); border: 1px solid var(--border); border-radius: 6px; color: var(--text-muted); font-size: 0.9rem; }
    </style>

    <div class="view-container">
        <div class="nav-bar">
            <a href="<?= $backLink ?>">&larr; JSON Index</a>
            <div>
                <button onclick="copyToClipboard()" style="cursor:pointer; background:none; border:none; font-size:1.2rem; margin-right:15px;" title="Copy to Clipboard">📋</button>
                <a href="?id=<?= $docId ?>&download=1" title="Download" style="margin-right:15px; font-size:1.2rem; text-decoration:none;">📥</a>
                <a href="<?= $editLink ?>" style="color: #fca326;" title="Edit">✏️ Edit</a>
            </div>
        </div>

        <?php if(!empty($doc['description'])): ?>
            <div class="meta-box">
                <strong>Description:</strong><br>
                <?= nl2br(htmlspecialchars($doc['description'])) ?>
            </div>
        <?php endif; ?>

        <pre><code class="language-json" id="jsonCode"><?= htmlspecialchars($prettyJson) ?></code></pre>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <script>
        // Theme Logic
        function updateTheme() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark' || 
                          (!document.documentElement.getAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
            
            document.getElementById('hl-dark').disabled = !isDark;
            document.getElementById('hl-light').disabled = isDark;
        }
        updateTheme();
        
        // Init Highlight
        hljs.highlightAll();

        function copyToClipboard() {
            const code = document.getElementById('jsonCode').innerText;
            navigator.clipboard.writeText(code).then(() => {
                alert("JSON copied to clipboard!");
            });
        }
    </script>

    <?php
    $content = ob_get_clean();
    $spw->renderLayout($content, $pageTitle);
    exit;
}

// ==========================================
// 2. INDEX MODE (List Files)
// ==========================================

$cats = $pdo->query("SELECT id, name FROM json_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Sort Logic
$orderByClause = "d.created_at DESC";
switch ($filterSort) {
    case 'created_asc': $orderByClause = "d.created_at ASC"; break;
    case 'updated_desc': $orderByClause = "d.updated_at DESC"; break;
    case 'name_asc': $orderByClause = "d.name ASC"; break;
    case 'name_desc': $orderByClause = "d.name DESC"; break;
    case 'created_desc': default: $orderByClause = "d.created_at DESC"; break;
}

// Query
$query = "SELECT d.id, d.name, d.updated_at, d.description, c.name as category, c.id as cat_id 
          FROM json_files d 
          LEFT JOIN json_categories c ON d.category_id = c.id 
          WHERE d.is_active = 1 
          ORDER BY d.category_id ASC, " . $orderByClause;

$stmt = $pdo->query($query);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouping
$grouped = [];
foreach($docs as $d) {
    $cId = $d['cat_id'] ?: 0;
    $cName = $d['category'] ?: 'Uncategorized';
    if(!isset($grouped[$cId])) {
        $grouped[$cId] = ['name' => $cName, 'docs' => []];
    }
    $grouped[$cId]['docs'][] = $d;
}

$newLink = "edit_json.php";
if ($filterCatId !== '') $newLink .= '?category_id=' . $filterCatId;

$pageTitle = "JSON Files Index";
ob_start();
?>
<!-- THEME INIT SCRIPT -->
<script>
    (function() {
        try {
            var theme = localStorage.getItem('spw_theme');
            if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
            else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
        } catch (e) {}
    })();
</script>

<style>
    :root[data-theme="dark"] {
        --bg: #0d1117;
        --card: #161b22;
        --border: #30363d;
        --text: #c9d1d9;
        --text-muted: #8b949e;
        --accent: #58a6ff;
        --green: #238636;
    }
    :root[data-theme="light"] {
        --bg: #ffffff;
        --card: #f6f8fa;
        --border: #d0d7de;
        --text: #24292f;
        --text-muted: #57606a;
        --accent: #0969da;
        --green: #2da44e;
    }

    body { background-color: var(--bg) !important; color: var(--text) !important; padding: 20px; transition: background-color 0.2s, color 0.2s; }
    .container { max-width: 900px; margin: 0 auto; }
    
    .header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom:10px; }
    
    .btn-create { background: var(--green); color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
    .btn-create:hover { filter: brightness(0.9); }

    .filters-area { background: var(--card); border: 1px solid var(--border); border-radius: 6px; padding: 10px; margin-bottom: 20px; }
    .filters-area input, .filters-area select { width: 100%; padding: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 4px; box-sizing: border-box; }
    .filters-area input { margin-bottom: 10px; }
    
    .cat-group { margin-bottom: 25px; }
    .cat-title { font-size: 1.1em; color: var(--text-muted); margin-bottom: 10px; border-bottom: 1px solid var(--border); padding-bottom: 4px; }
    
    .doc-item { background: var(--card); margin-bottom: 6px; padding: 10px 12px; border-radius: 6px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
    .doc-link { font-size: 16px; font-weight: 600; color: var(--accent); text-decoration: none; display: flex; align-items: center; gap: 8px; flex: 1; }
    .doc-link:hover { text-decoration: underline; }
    
    .meta { font-size: 12px; color: var(--text-muted); display: flex; gap: 10px; align-items: center; }
    .icon-btn { text-decoration: none; font-size: 16px; border: none; background: none; cursor: pointer; padding: 0 2px; filter: grayscale(100%); transition: all 0.2s; }
    .icon-btn:hover { filter: grayscale(0%); transform: scale(1.1); }

    .doc-link { min-width: 0; }
.doc-name { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    
</style>

<div class="container">
    <div class="header-row">
        <h2 style="margin:0;">JSON Files</h2>
        <a href="<?= $newLink ?>" class="btn-create"><span>+</span> New JSON</a>
    </div>

    <!-- Filter Area -->
    <div class="filters-area">
        <input id="docSearch" placeholder="Filter files..." value="">
        <div style="display:flex; gap:10px;">
            <select id="catFilter">
                <option value="">All Categories</option>
                <?php foreach($cats as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($filterCatId == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
                <option value="0" <?= ($filterCatId === '0') ? 'selected' : '' ?>>Uncategorized</option>
            </select>
            <select id="sortFilter">
                <option value="created_desc" <?= $filterSort === 'created_desc' ? 'selected' : '' ?>>📅 Created (Newest)</option>
                <option value="created_asc" <?= $filterSort === 'created_asc' ? 'selected' : '' ?>>📅 Created (Oldest)</option>
                <option value="updated_desc" <?= $filterSort === 'updated_desc' ? 'selected' : '' ?>>📝 Updated</option>
                <option value="name_asc" <?= $filterSort === 'name_asc' ? 'selected' : '' ?>>🔤 Name (A-Z)</option>
            </select>
        </div>
    </div>

    <div id="docsContainer">
        <?php if(empty($grouped)): ?>
            <div style="padding: 20px; text-align: center; color: var(--text-muted);">No JSON files found.</div>
        <?php else: ?>
            <?php foreach($grouped as $catId => $group): ?>
                <div class="cat-group" data-cat-id="<?= $catId ?>">
                    <div class="cat-title"><?php echo htmlspecialchars($group['name']); ?></div>
                    <ul style="list-style:none; padding:0;">
                        <?php foreach($group['docs'] as $item): 
                            $itemLink = "?id=" . $item['id'];
                            if ($filterCatId !== '') $itemLink .= "&category_id=" . $filterCatId;
                        ?>
                            <li class="doc-item" id="row-<?= $item['id'] ?>">
                                <a href="<?= $itemLink ?>" class="doc-link">
                                    <span>📄</span>
                                    
                                    
                                    
                                    
                                    
                                    <span class="doc-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                    
                                    
                                    
                                    
                                    
                                    
                                    
                                    
                                    
                                </a>
                                <div class="meta">
                                    <span style="opacity:0.6;"><?= date('M d', strtotime($item['updated_at'])) ?></span>
                                    <a href="?id=<?= $item['id'] ?>&download=1" class="icon-btn" title="Download">📥</a>
                                    <a href="edit_json.php?id=<?= $item['id'] ?>" class="icon-btn" title="Edit">✏️</a>
                                    <button onclick="deleteDoc(<?= $item['id'] ?>)" class="icon-btn" title="Delete">🗑️</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Client-side filtering logic matching view_md.php
    const searchInput = document.getElementById('docSearch');
    const catSelect = document.getElementById('catFilter');
    const sortSelect = document.getElementById('sortFilter');

    function renderList() {
        const cat = catSelect.value;
        const txt = searchInput.value.toLowerCase().trim();
        
        document.querySelectorAll('.cat-group').forEach(group => {
            const groupCatId = group.getAttribute('data-cat-id');
            const isCatMatch = (cat === "") || (groupCatId === cat);
            
            if (!isCatMatch) { group.style.display = 'none'; return; }

            let visibleCount = 0;
            group.querySelectorAll('.doc-item').forEach(item => {
                const name = item.querySelector('.doc-link span:nth-child(2)').textContent.toLowerCase();
                if (name.includes(txt)) {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            group.style.display = (visibleCount > 0) ? 'block' : 'none';
        });

        // Update URL
        const url = new URL(window.location);
        if (cat) url.searchParams.set('category_id', cat); else url.searchParams.delete('category_id');
        window.history.replaceState({}, '', url);
        
        // Update Links
        document.querySelectorAll('a.btn-create').forEach(a => {
            let u = new URL(a.href, window.location.origin);
            if(cat) u.searchParams.set('category_id', cat); else u.searchParams.delete('category_id');
            a.href = u.toString();
        });
    }

    catSelect.addEventListener('change', renderList);
    searchInput.addEventListener('input', renderList);
    sortSelect.addEventListener('change', function() {
        const url = new URL(window.location);
        url.searchParams.set('sort', this.value);
        window.location.href = url.toString();
    });

    // Initial Filter Apply
    if(searchInput.value.trim() !== '') renderList();

    function deleteDoc(id) {
        if(!confirm('Are you sure you want to delete this file?')) return;
        fetch('api_json.php?action=delete', { method: 'POST', body: JSON.stringify({id: id}) })
        .then(r => r.json()).then(res => { 
            if(res.status === 'success') { 
                const row = document.getElementById('row-' + id);
                if(row) { row.style.opacity = '0'; setTimeout(() => row.remove(), 400); } 
            } else { alert('Error: ' + res.message); } 
        });
    }
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);