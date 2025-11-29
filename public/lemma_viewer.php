<?php
require_once __DIR__ . '/bootstrap.php';
require      __DIR__ . '/env_locals.php';

require_once PROJECT_ROOT . '/src/Dictionary/DictionaryManager.php';

use App\Dictionary\DictionaryManager;

$dictManager = new DictionaryManager($pdo);

// Pagination settings
$perPage = 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Filters
$search = $_GET['search'] ?? '';
$dictId = isset($_GET['dict_id']) ? (int)$_GET['dict_id'] : null;

// Handle lemma edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_lemma_dicts' && isset($_POST['lemma_id'])) {
        $lemmaId = (int)$_POST['lemma_id'];
        $dictionaryIds = $_POST['dictionary_ids'] ?? [];
        $dictionaryIds = array_map('intval', $dictionaryIds);
        
        $success = $dictManager->updateLemmaDictionaries($lemmaId, $dictionaryIds);
        
        echo json_encode(['success' => $success]);
        exit;
    }
    
    if ($_POST['action'] === 'delete_lemma' && isset($_POST['lemma_id'])) {
        $lemmaId = (int)$_POST['lemma_id'];
        $success = $dictManager->deleteLemma($lemmaId);
        
        echo json_encode(['success' => $success]);
        exit;
    }
}

// Fetch data
$lemmas = $dictManager->searchLemmas($search, $dictId, $perPage, $offset);
$totalLemmas = $dictManager->countLemmas($search, $dictId);
$totalPages = ceil($totalLemmas / $perPage);

// Get all dictionaries for filter
$allDictionaries = $dictManager->getAllDictionaries();

// If viewing a specific dictionary, get its info
$currentDict = null;
if ($dictId) {
    $currentDict = $dictManager->getDictionaryById($dictId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.6">
    <title>Lemma Viewer</title>
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
    <link rel="stylesheet" href="/css/base.css">
    <style>
        .filters { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; align-items: center; }
        .filters .form-control { max-width: 300px; }
        .lemma-table { width: 100%; }
        .lemma-table td { vertical-align: middle; }
        .lemma-word { font-family: 'Courier New', monospace; font-size: 16px; font-weight: 600; }
        .frequency-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; background: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.3); }
        .pagination { display: flex; gap: 8px; justify-content: center; margin-top: 24px; }
        .pagination a, .pagination span { padding: 8px 12px; border-radius: 6px; background: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.3); text-decoration: none; color: var(--text); }
        .pagination .current { background: var(--accent); color: white; border-color: var(--accent); }
        .pagination a:hover { background: rgba(59, 130, 246, 0.1); }
        .dict-tags { display: flex; gap: 6px; flex-wrap: wrap; }
        .dict-tag { padding: 4px 8px; border-radius: 4px; font-size: 12px; background: rgba(59, 130, 246, 0.1); color: var(--accent); }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: var(--card); margin: 10% auto; padding: 24px; border: 1px solid rgba(var(--muted-border-rgb), 0.3); border-radius: 8px; width: 90%; max-width: 500px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close { font-size: 28px; font-weight: bold; cursor: pointer; }
        .dict-checkbox-list { max-height: 300px; overflow-y: auto; }
        .dict-checkbox-list label { display: block; padding: 8px; margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Lemma Viewer
                <?php if ($currentDict): ?>
                    <small style="font-weight: normal; font-size: 16px; color: var(--text-muted);">
                        — <?php echo htmlspecialchars($currentDict['title']); ?>
                    </small>
                <?php endif; ?>
            </h1>
            <div class="flex-gap">
                <a href="dictionaries_admin.php" class="btn btn-secondary">&larr; Back to Admin</a>
            </div>
        </div>

        <div class="filters">
            <form method="get" style="display: contents;">
                <input type="text" 
                       name="search" 
                       placeholder="Search lemmas..." 
                       class="form-control" 
                       value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="dict_id" class="form-control" onchange="this.form.submit()">
                    <option value="">All Dictionaries</option>
                    <?php foreach ($allDictionaries as $dict): ?>
                        <option value="<?php echo $dict['id']; ?>" 
                                <?php echo ($dictId == $dict['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dict['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="btn btn-primary">Filter</button>
                
                <?php if ($search || $dictId): ?>
                    <a href="lemma_viewer.php" class="btn btn-secondary">Clear Filters</a>
                <?php endif; ?>
            </form>
            
            <div style="margin-left: auto; color: var(--text-muted);">
                Showing <?php echo number_format(count($lemmas)); ?> of <?php echo number_format($totalLemmas); ?> lemmas
            </div>
        </div>

        <?php if (empty($lemmas)): ?>
            <div class="empty-state">
                <p>No lemmas found.</p>
            </div>
        <?php else: ?>
            <table class="posts-table lemma-table">
                <thead>
                    <tr>
                        <th>Lemma</th>
                        <th>Language</th>
                        <th>Part of Speech</th>
                        <th>Frequency</th>
                        <th>Dictionaries</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lemmas as $lemma): ?>
                        <tr>
                            <td data-label="Lemma">
                                <span class="lemma-word"><?php echo htmlspecialchars($lemma['lemma']); ?></span>
                            </td>
                            <td data-label="Language">
                                <span class="post-type-badge"><?php echo strtoupper($lemma['language_code']); ?></span>
                            </td>
                            <td data-label="POS">
                                <?php echo htmlspecialchars($lemma['pos'] ?? '—'); ?>
                            </td>
                            <td data-label="Frequency">
                                <span class="frequency-badge"><?php echo number_format($lemma['total_frequency'] ?? 0); ?></span>
                            </td>
                            <td data-label="Dictionaries">
                                <?php
                                $dictIds = $lemma['dictionary_ids'] ? explode(',', $lemma['dictionary_ids']) : [];
                                if (!empty($dictIds)):
                                    $dictNames = array_filter(
                                        array_map(function($id) use ($allDictionaries) {
                                            foreach ($allDictionaries as $d) {
                                                if ($d['id'] == $id) return $d['title'];
                                            }
                                            return null;
                                        }, $dictIds)
                                    );
                                ?>
                                    <div class="dict-tags">
                                        <?php foreach ($dictNames as $name): ?>
                                            <span class="dict-tag"><?php echo htmlspecialchars($name); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="opacity: 0.5;">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions" class="action-cell">
                                <div class="flex-gap">
                                    <button class="btn btn-sm btn-secondary edit-lemma-btn" 
                                            data-lemma-id="<?php echo $lemma['id']; ?>"
                                            data-lemma-word="<?php echo htmlspecialchars($lemma['lemma']); ?>"
                                            data-dict-ids="<?php echo htmlspecialchars($lemma['dictionary_ids'] ?? ''); ?>">
                                        Edit Dicts
                                    </button>
<button class="btn btn-sm btn-info enrich-lemma-btn"
        data-lemma="<?php echo htmlspecialchars($lemma['lemma']); ?>">
    Enrich
</button>
                                    <button class="btn btn-sm btn-danger delete-lemma-btn"
                                            data-lemma-id="<?php echo $lemma['id']; ?>"
                                            data-lemma-word="<?php echo htmlspecialchars($lemma['lemma']); ?>">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $dictId ? '&dict_id=' . $dictId : ''; ?>">&laquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $dictId ? '&dict_id=' . $dictId : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $dictId ? '&dict_id=' . $dictId : ''; ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Edit Lemma Dictionaries</h3>
                <span class="close">&times;</span>
            </div>
            <form id="editLemmaForm">
                <input type="hidden" id="editLemmaId" name="lemma_id">
                <div class="form-group">
                    <label>Select Dictionaries:</label>
                    <div class="dict-checkbox-list">
                        <?php foreach ($allDictionaries as $dict): ?>
                            <label>
                                <input type="checkbox" 
                                       name="dictionary_ids[]" 
                                       value="<?php echo $dict['id']; ?>">
                                <?php echo htmlspecialchars($dict['title']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toastContainer" class="toast-container"></div>

    <script>
    (function() {
        const modal = document.getElementById('editModal');
        const modalTitle = document.getElementById('modalTitle');
        const editForm = document.getElementById('editLemmaForm');
        const editLemmaId = document.getElementById('editLemmaId');
        const closeButtons = document.querySelectorAll('.close, .close-modal');

        // Edit button handlers
        document.querySelectorAll('.edit-lemma-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const lemmaId = this.dataset.lemmaId;
                const lemmaWord = this.dataset.lemmaWord;
                const dictIds = this.dataset.dictIds ? this.dataset.dictIds.split(',') : [];
                
                modalTitle.textContent = `Edit Dictionaries for "${lemmaWord}"`;
                editLemmaId.value = lemmaId;
                
                // Check appropriate boxes
                document.querySelectorAll('input[name="dictionary_ids[]"]').forEach(checkbox => {
                    checkbox.checked = dictIds.includes(checkbox.value);
                });
                
                modal.style.display = 'block';
            });
        });

        // Delete button handlers
        document.querySelectorAll('.delete-lemma-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const lemmaId = this.dataset.lemmaId;
                const lemmaWord = this.dataset.lemmaWord;
                
                if (!confirm(`Are you sure you want to delete the lemma "${lemmaWord}"? This will remove all dictionary mappings.`)) {
                    return;
                }
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete_lemma');
                    formData.append('lemma_id', lemmaId);
                    
                    const response = await fetch(window.location.pathname, {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast('Lemma deleted successfully', 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showToast('Failed to delete lemma', 'error');
                    }
                } catch (error) {
                    showToast('Error: ' + error.message, 'error');
                }
            });
        });

        // Close modal handlers
        closeButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        });

        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Form submission
        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(editForm);
            formData.append('action', 'update_lemma_dicts');
            
            try {
                const response = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    showToast('Dictionary associations updated successfully', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Failed to update associations', 'error');
                }
            } catch (error) {
                showToast('Error: ' + error.message, 'error');
            }
        });

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                toast.classList.remove('show');
                toast.addEventListener('transitionend', () => toast.remove());
            }, 3000);
        }
    })();
    </script>
<script>
(function(){
    async function fetchWordnet(action, params) {
        const url = new URL('/wordnet_proxy.php', window.location.origin);
        url.searchParams.set('action', action);
        for (const k in params) url.searchParams.set(k, params[k]);
        const resp = await fetch(url.toString());
        return resp.json();
    }

    // Create modal
    const enrichModal = document.createElement('div');
    enrichModal.id = 'enrichModal';
    enrichModal.className = 'modal';
    enrichModal.innerHTML = `<div class="modal-content" style="max-width:800px;">
        <div class="modal-header"><h3 id="enrichTitle">WordNet</h3><span class="close" id="enrichClose">&times;</span></div>
        <div id="enrichBody" style="max-height:60vh; overflow:auto; padding:12px;"></div>
    </div>`;
    document.body.appendChild(enrichModal);
    document.getElementById('enrichClose').addEventListener('click', ()=>enrichModal.style.display='none');

    document.querySelectorAll('.enrich-lemma-btn').forEach(btn => {
        btn.addEventListener('click', async function(){
            const lemma = this.dataset.lemma;
            this.disabled = true;
            const old = this.textContent;
            this.textContent = 'Loading…';

            try {
                const resp = await fetch(`/wordnet_proxy.php?action=lemma&lemma=${encodeURIComponent(lemma)}`);
                const j = await resp.json();
                if (j.status !== 'ok') throw new Error(j.message || 'Failed');

                // j.data is an array of senses (from pyapi dict)
                const senses = j.data || [];
                const body = document.getElementById('enrichBody');
                body.innerHTML = '';

                if (senses.length === 0) {
                    body.innerHTML = `<p>No WordNet data found for <strong>${lemma}</strong>.</p>`;
                } else {
                    const list = senses.map((s, idx) => {
                        const syns = (s.sampleset || '') ? s.sampleset : '';
                        const def = s.definition ? `<div style="margin-bottom:8px;"><strong>Definition:</strong> ${escapeHtml(s.definition)}</div>` : '';
                        // provide a link to fetch synset details or hypernyms
                        return `<div style="padding:10px;border-bottom:1px solid rgba(0,0,0,0.06);">
                            <div style="font-weight:700">${escapeHtml(s.lemma ?? lemma)} <small style="opacity:0.7">(${s.pos ?? '—'})</small></div>
                            ${def}
                            <div style="margin-top:6px;">
                                <button class="btn btn-sm btn-outline-secondary" data-synsetid="${s.synsetid}" onclick="fetchSynsetDetails(event, ${s.synsetid})">Synset ${s.synsetid}</button>
                                <button class="btn btn-sm btn-outline-primary" data-synsetid="${s.synsetid}" onclick="fetchHypernyms(event, ${s.synsetid})">Hypernyms</button>
                            </div>
                        </div>`;
                    }).join('');
                    body.innerHTML = `<h4>WordNet results for "${escapeHtml(lemma)}"</h4>` + list;
                }

                document.getElementById('enrichTitle').textContent = `WordNet: ${lemma}`;
                enrichModal.style.display = 'block';
            } catch (err) {
                alert('WordNet fetch error: ' + err.message);
            } finally {
                this.disabled = false;
                this.textContent = old;
            }
        });
    });

    // helper functions exposed to modal buttons
    window.fetchSynsetDetails = async function(ev, synsetid) {
        ev.stopPropagation();
        const b = document.getElementById('enrichBody');
        b.innerHTML = '<p>Loading synset...</p>';
        try {
            const resp = await fetch(`/wordnet_proxy.php?action=synset&synsetid=${synsetid}`);
            const j = await resp.json();
            if (j.status !== 'ok') throw new Error(j.message || 'Failed');
            const d = j.data;
            let html = `<h4>Synset ${synsetid}</h4>`;
            html += `<p><strong>Definition:</strong> ${escapeHtml(d.definition ?? '')}</p>`;
            if (d.synonyms && d.synonyms.length) {
                html += `<p><strong>Synonyms:</strong> ` + d.synonyms.map(s=>escapeHtml(s)).join(', ') + `</p>`;
            }
            b.innerHTML = html;
        } catch (err) {
            b.innerHTML = '<p>Error loading synset: ' + err.message + '</p>';
        }
    };

    window.fetchHypernyms = async function(ev, synsetid) {
        ev.stopPropagation();
        const b = document.getElementById('enrichBody');
        b.innerHTML = '<p>Loading hypernyms...</p>';
        try {
            const resp = await fetch(`/wordnet_proxy.php?action=hypernyms&synsetid=${synsetid}`);
            const j = await resp.json();
            if (j.status !== 'ok') throw new Error(j.message || 'Failed');
            const items = j.data && j.data.hypernyms ? j.data.hypernyms : (j.data || []);
            let html = `<h4>Hypernyms of ${synsetid}</h4>`;
            if (items.length === 0) html += '<p>No hypernyms found.</p>';
            else html += `<ul>${items.map(it=>`<li>${escapeHtml(it.definition ?? it.synsetid)}</li>`).join('')}</ul>`;
            b.innerHTML = html;
        } catch (err) {
            b.innerHTML = '<p>Error loading hypernyms: ' + err.message + '</p>';
        }
    };

    function escapeHtml(s) {
        if (!s) return '';
        return String(s).replace(/[&<>"'\/]/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;'}[c];
        });
    }

    // small style for modal (reuse your .modal/.modal-content CSS)
    const style = document.createElement('style');
    style.textContent = `
    #enrichModal.modal { display:none; position:fixed; z-index:120000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
    #enrichModal .modal-content { background:var(--card); margin:5% auto; padding:12px; border-radius:8px; width:90%; max-width:900px;}
    #enrichModal .modal-header { display:flex; justify-content:space-between; align-items:center; padding-bottom:8px; border-bottom:1px solid rgba(0,0,0,0.06); }
    #enrichModal .close { cursor:pointer; font-size:24px; font-weight:700; }
    `;
    document.head.appendChild(style);

    // close modal on outside click
    window.addEventListener('click', function(e){ const m = document.getElementById('enrichModal'); if (e.target === m) m.style.display='none'; });
})();
</script>

    <script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

<?php echo $eruda; ?>

</body>
</html>
