<?php
// public/view_entity_downloader.php
// Entity JSON Downloader - Clean view for downloading specific entity types as JSON
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

$pageTitle = "Entity JSON Downloader 📦";
$filterCat = $_GET['category_id'] ?? '';

$where = ["da.id IS NOT NULL"];
$params = [];
if ($filterCat) { 
    $where[] = "d.category_id = :cat"; 
    $params['cat'] = $filterCat; 
}

$whereSql = implode(" AND ", $where);
$sql = "
    SELECT da.*, d.name as doc_name, c.name as category_name
    FROM md_doc_analysis da
    JOIN documentations d ON da.doc_id = d.id
    LEFT JOIN documentation_categories c ON d.category_id = c.id
    WHERE $whereSql
    ORDER BY d.updated_at DESC
    LIMIT 50
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$cats = $pdo->query("SELECT id, name FROM documentation_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<style>
html { font-size: 130% !important; }

:root {
    --fold-bg: rgba(0,0,0,0.02);
    --fold-border: rgba(0,0,0,0.08);
    --accent-subtle: rgba(139, 92, 246, 0.1);
}

.download-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(500px, 1fr)); 
    gap: 24px; 
    padding: 24px; 
}

.download-card { 
    background: var(--card); 
    border: 1px solid var(--border); 
    border-radius: 12px; 
    display: flex; 
    flex-direction: column; 
    box-shadow: var(--card-elevation); 
    transition: all 0.2s ease; 
    overflow: hidden;
}

.download-card:hover { 
    border-color: var(--accent); 
    transform: translateY(-2px); 
}

.card-header { 
    padding: 14px 18px; 
    border-bottom: 1px solid var(--border); 
    background: var(--fold-bg); 
    cursor: pointer; 
    user-select: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header:hover { 
    background: rgba(0,0,0,0.04); 
}

.toggle-icon { 
    font-size: 0.8rem; 
    color: var(--text-muted); 
    transition: transform 0.2s;
    margin-right: 10px;
}

.download-card.collapsed .toggle-icon { 
    transform: rotate(-90deg); 
}

.download-card.collapsed .card-body { 
    display: none; 
}

.card-body { 
    padding: 18px; 
    display: flex; 
    flex-direction: column; 
    gap: 12px; 
}

.entity-section {
    margin-bottom: 8px;
    border: 1px solid var(--fold-border);
    border-radius: 8px;
    overflow: hidden;
}

.entity-header {
    padding: 10px 14px;
    background: rgba(0,0,0,0.015);
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.entity-count {
    background: var(--accent);
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
}

.download-btn {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%);
    border: 1px solid rgba(139, 92, 246, 0.3);
    color: var(--accent);
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.download-btn:hover {
    background: rgba(139, 92, 246, 0.15);
    transform: translateY(-1px);
}

.header-main-page {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
    background: var(--card);
}

.page-title {
    font-size: 1.4rem;
    font-weight: 800;
}

.filter-form {
    display: flex;
    gap: 10px;
    flex: 1;
}

select {
    padding: 10px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--card);
    color: var(--text);
}

button[type="submit"] {
    background: var(--accent);
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}
</style>

<div class="header-main-page">
    <div class="page-title">📦 Entity JSON Downloader</div>
    <form method="GET" class="filter-form">
        <select name="category_id">
            <option value="">All Categories</option>
            <?php foreach($cats as $c): ?>
                <option value="<?= h($c['id']) ?>" <?= $filterCat == $c['id'] ? 'selected' : '' ?>>
                    <?= h($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filter View</button>
    </form>
</div>

<div class="download-grid">
    <?php foreach($rows as $row):
        $docId = $row['doc_id'];
        $entities = json_decode($row['entities'] ?? '{}', true) ?? [];
        $showrunner = json_decode($row['showrunner_analysis'] ?? '{}', true) ?? [];
        
        // Hoist nested data
        $hoistData = function($parent, $key) {
            if (!isset($parent[$key]) || !is_array($parent[$key])) return $parent;
            $inner = $parent[$key];
            unset($parent[$key]);
            foreach ($inner as $k => $v) {
                if (isset($parent[$k]) && is_array($parent[$k]) && is_array($v)) {
                    $parent[$k] = array_merge($parent[$k], $v);
                } else {
                    $parent[$k] = $v;
                }
            }
            return $parent;
        };

        $entities = $hoistData($entities, 'extraction');
        $entities = $hoistData($entities, 'entities');
        $showrunner = $hoistData($showrunner, 'chunk_analysis');
        
        // Build main categories
        $worldData = [];
        $storyData = [];
        
        // World entities
        if (!empty($entities)) {
            foreach ($entities as $key => $val) {
                if (is_array($val) && !empty($val)) {
                    $worldData[$key] = $val;
                }
            }
        }
        
        // Story entities
        if (!empty($showrunner['episode_concepts'])) $storyData['episodes'] = $showrunner['episode_concepts'];
        if (!empty($showrunner['narrative_engines_all'])) $storyData['narrative_engine'] = $showrunner['narrative_engines_all'];
        if (!empty($showrunner['visual_keywords'])) $storyData['visual_keywords'] = $showrunner['visual_keywords'];
        if (!empty($showrunner['scene_hooks'])) $storyData['scene_hooks'] = $showrunner['scene_hooks'];
    ?>
    <div class="download-card" id="card-<?= h($docId) ?>">
        <div class="card-header" onclick="toggleCard(<?= h($docId) ?>)">
            <div style="display:flex; align-items:center;">
                <div class="toggle-icon">▼</div>
                <div>
                    <div style="font-size:1.1rem; font-weight:700;"><?= h($row['doc_name']) ?></div>
                    <div style="font-size:0.8rem; color:var(--text-muted);"><?= h($row['category_name'] ?? 'General') ?></div>
                </div>
            </div>
        </div>
        
        <div class="card-body">
            <?php if (!empty($storyData)): ?>
                <div style="font-weight:700; font-size:0.9rem; color:var(--accent); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">
                    🎬 Story Elements
                </div>
                <?php foreach ($storyData as $cat => $items): ?>
                    <div class="entity-section">
                        <div class="entity-header">
                            <span><?= h(ucfirst(str_replace('_', ' ', $cat))) ?></span>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <span class="entity-count"><?= count($items) ?></span>
                                <button class="download-btn" onclick="downloadEntityJSON('<?= h($docId) ?>', 'story', '<?= h($cat) ?>', event)">
                                    ⬇️ JSON
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($worldData)): ?>
                <div style="font-weight:700; font-size:0.9rem; color:var(--world-color, #3b82f6); margin:16px 0 8px 0; text-transform:uppercase; letter-spacing:0.05em;">
                    🌍 World Elements
                </div>
                <?php foreach ($worldData as $cat => $items): ?>
                    <div class="entity-section">
                        <div class="entity-header">
                            <span><?= h(ucfirst(str_replace('_', ' ', $cat))) ?></span>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <span class="entity-count"><?= count($items) ?></span>
                                <button class="download-btn" onclick="downloadEntityJSON('<?= h($docId) ?>', 'world', '<?= h($cat) ?>', event)">
                                    ⬇️ JSON
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <script type="application/json" id="payload-<?= h($docId) ?>">
            <?= json_encode(['world' => $worldData, 'story' => $storyData], JSON_UNESCAPED_UNICODE) ?>
        </script>
    </div>
    <?php endforeach; ?>
</div>

<script>
function toggleCard(docId) {
    const card = document.getElementById('card-' + docId);
    if (!card) return;
    card.classList.toggle('collapsed');
}

function downloadEntityJSON(docId, type, category, event) {
    event.stopPropagation();
    
    const payloadEl = document.getElementById('payload-' + docId);
    if (!payloadEl) {
        alert('Data not found');
        return;
    }
    
    try {
        const data = JSON.parse(payloadEl.textContent);
        const categoryData = data[type] && data[type][category] ? data[type][category] : null;
        
        if (!categoryData) {
            alert('Category data not found');
            return;
        }
        
        const json = JSON.stringify(categoryData, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${docId}_${type}_${category}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    } catch (e) {
        console.error('Download failed:', e);
        alert('Download failed: ' + e.message);
    }
}

// Restore collapse state
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.download-card').forEach(card => {
        const id = card.id.replace('card-', '');
        const state = localStorage.getItem('dl_card_' + id);
        if (state === 'closed') {
            card.classList.add('collapsed');
        }
    });
});

// Save collapse state
function toggleCard(docId) {
    const card = document.getElementById('card-' + docId);
    if (!card) return;
    card.classList.toggle('collapsed');
    const state = card.classList.contains('collapsed') ? 'closed' : 'open';
    localStorage.setItem('dl_card_' + docId, state);
}
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/gallery.php');


