<?php
// public/edit_md.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; 

$spw = \App\Core\SpwBase::getInstance();

$docId = $_GET['id'] ?? null;
$filterCatId = $_GET['category_id'] ?? '';
$filterSort = $_GET['sort'] ?? ''; 

$pageTitle = $docId ? "Edit Document" : "New Document";

$cats = $pdo->query("SELECT id, name FROM documentation_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Build Link Params
$linkParams = [];
if ($filterCatId !== '') $linkParams['category_id'] = $filterCatId;
if ($filterSort !== '') $linkParams['sort'] = $filterSort;

// Generate Back Link
$backLink = "view_md.php";
if (!empty($linkParams)) {
    $backLink .= "?" . http_build_query($linkParams);
}

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

<!-- LIBRARY LOAD (CSS) -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css" />
    <link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/theme/toastui-editor-dark.min.css" id="tui-dark-theme" disabled />
<?php else: ?>
    <link rel="stylesheet" href="/vendor/toastui-editor/toastui-editor.min.css" />
    <link rel="stylesheet" href="/vendor/toastui-editor/toastui-editor-dark.min.css" id="tui-dark-theme" disabled />
<?php endif; ?>

<style>
    /* CSS PATCH for Dark Mode override */
    :root[data-theme="dark"] {
        --bg: #0d1117;
        --card: #161b22;
        --border: #30363d;
        --text: #c9d1d9;
        --text-muted: #8b949e;
        --accent: #3b82f6;
        --green: #238636;
        --red: #da3633;
        --orange: #f59e0b;
        --overlay-bg: rgba(0,0,0,0.85);
        --card-elevation: 0 6px 18px rgba(0,0,0,0.6);
        --modal-bg: #1e252e;
    }
    :root[data-theme="light"] {
        --bg: #f6f8fa;
        --card: #ffffff;
        --border: #d0d7de;
        --text: #24292f;
        --text-muted: #57606a;
        --accent: #0969da;
        --green: #2da44e;
        --red: #cf222e;
        --orange: #bf8700;
        --overlay-bg: rgba(0,0,0,0.5);
        --card-elevation: 0 4px 12px rgba(0,0,0,0.15);
        --modal-bg: #ffffff;
    }

    body { background-color: var(--bg) !important; color: var(--text) !important; transition: background-color 0.2s, color 0.2s; } 
    .editor-container { display: flex; flex-direction: column; height: 90vh; padding: 10px; gap: 0px; }
    
    .toolbar-wrapper { background: var(--card); border-radius: 6px; border: 1px solid var(--border); margin-bottom: 10px; padding: 5px; box-shadow: var(--card-elevation); }
    .toolbar { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; padding: 5px; }
    .subheader { display: flex; align-items: center; gap: 10px; padding: 5px 10px 8px 10px; border-top: 1px solid var(--border); margin-top: 5px; font-size: 0.9rem; }

    .toolbar input { background: var(--bg); border: 1px solid var(--border); color: var(--text); padding: 6px 8px; border-radius: 4px; font-size: 1.1em; flex: 1 1 100px; min-width: 0; }
    .subheader select, .modal-select { background: var(--bg); border: 1px solid var(--border); color: var(--text); padding: 4px 8px; border-radius: 4px; min-width: 200px; }

    .btn { cursor: pointer; padding: 6px 10px; border-radius: 4px; border: none; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; flex: 0 0 auto; line-height: 1; height: 36px; }
    .btn-sm { padding: 4px 10px; font-size: 0.9rem; height: auto; }
    
    .btn-save { background: var(--green); color: white; }
    .btn-save:hover { filter: brightness(0.9); }
    .btn-read { background: var(--accent); color: white; }
    .btn-meta { background: var(--card); border: 1px solid var(--border); color: var(--text); }
    .btn-meta:hover { border-color: var(--accent); color: var(--accent); }
    .btn-cancel { background: var(--card); border: 1px solid var(--border); color: var(--text-muted); }
    .btn-add { background: var(--card); color: var(--text-muted); border: 1px solid var(--border); }
    .btn-add:hover { color: var(--text); border-color: var(--text); }
    
    #editor { flex-grow: 1; background: white; border-radius: 4px; overflow: hidden; }
    
    #toast { visibility: hidden; min-width: 250px; background-color: var(--card); color: var(--text); text-align: center; border-radius: 4px; padding: 16px; position: fixed; z-index: 9999; bottom: 30px; right: 30px; box-shadow: var(--card-elevation); border-left: 5px solid var(--green); border: 1px solid var(--border); }
    #toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }
    @keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
    @keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }

    /* Modal Styles */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--overlay-bg); display: none; justify-content: center; align-items: center; z-index: 5000; backdrop-filter: blur(2px); }
    .modal-content { background-color: var(--modal-bg); color: var(--text); width: 85%; max-width: 900px; height: 80vh; border-radius: 8px; border: 1px solid var(--border); display: flex; flex-direction: column; padding: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.5); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
    .modal-header h3 { margin: 0; font-size: 1.2rem; }
    .modal-body { flex-grow: 1; display: flex; flex-direction: column; gap: 15px; overflow: hidden; }
    .modal-footer { display: flex; justify-content: flex-end; margin-top: 15px; padding-top: 10px; border-top: 1px solid var(--border); }
    
    .modal-label { display: block; font-size: 0.9rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    
    /* Simple textarea for description */
    #metaDescription { 
        flex-grow: 1; 
        width: 100%; 
        background: var(--bg); 
        color: var(--text); 
        border: 1px solid var(--border); 
        border-radius: 4px; 
        padding: 10px; 
        font-family: 'Courier New', monospace; 
        font-size: 0.95rem; 
        resize: none;
        line-height: 1.5;
        min-height: 160px;
    }
    #metaDescription:focus {
        outline: none;
        border-color: var(--accent);
    }

    /* Short description (smaller) */
    #metaDescShort {
        width: 100%;
        background: var(--bg);
        color: var(--text);
        border: 1px solid var(--border);
        border-radius: 4px;
        padding: 8px;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        resize: none;
        line-height: 1.4;
        min-height: 70px;
    }
    #metaDescShort:focus { outline: none; border-color: var(--accent); }

    /* Keywords input */
    #metaKeywords {
        width: 100%;
        background: var(--bg);
        color: var(--text);
        border: 1px solid var(--border);
        border-radius: 4px;
        padding: 8px;
        font-size: 0.95rem;
    }
    #metaKeywords:focus { outline: none; border-color: var(--accent); }
</style>

<div class="editor-container">
    <div class="toolbar-wrapper">
        <div class="toolbar">
            <a href="<?= $backLink ?>" class="btn btn-cancel" title="Back to Index">↩️</a>
            <input type="text" id="docName" placeholder="Document Title" value="">
            
            <button class="btn btn-meta" id="btnOpenMeta" title="Edit Summary & Collection">✏️</button>

            <button class="btn btn-save" id="btnSave" title="Save Document">💾</button>
            <?php 
                if($docId): 
                    $readLink = "view_md.php?id=$docId"; 
                    if (!empty($linkParams)) $readLink .= "&" . http_build_query($linkParams);
            ?>
                <a href="<?= $readLink ?>" class="btn btn-read" target="_self" title="Open Read Mode">👁️</a>
            <?php endif; ?>
        </div>
        <div class="subheader">
            <label for="docCategory" style="color:var(--text-muted);">Category:</label>
            <select id="docCategory">
                <?php foreach($cats as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-add" id="btnAddCategory" title="Create New Category">+</button>
        </div>
    </div>
    <div id="editor"></div>
</div>

<!-- META MODAL -->
<div class="modal-overlay" id="metaModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Document Summary & Settings</h3>
            <button class="btn btn-sm btn-cancel" id="btnCloseMeta">✕</button>
        </div>
        <div class="modal-body">
            <div>
                <span class="modal-label">Target Chroma Collection (for Parser):</span>
                <select id="metaCollection" class="modal-select" style="width: 100%;">
                    <option value="">-- No Collection --</option>
                </select>
            </div>

            <div style="display:flex; flex-direction:column; gap:10px;">
                <div>
                    <span class="modal-label">Summary / Description (Markdown):</span>
                    <textarea id="metaDescription" placeholder="Enter description in Markdown format..."></textarea>
                </div>

                <div>
                    <span class="modal-label">Short Description (Brief summary):</span>
                    <textarea id="metaDescShort" placeholder="Short summary for lists, previews..."></textarea>
                </div>

                <div>
                    <span class="modal-label">Keywords (comma separated):</span>
                    <input id="metaKeywords" placeholder="keyword1, keyword2, ..." />
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-save btn-sm" id="btnMetaDone">Done</button>
        </div>
    </div>
</div>

<div id="toast">Saved Successfully!</div>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>
<?php else: ?>
    <script src="/vendor/toastui-editor/toastui-editor-all.min.js"></script>
<?php endif; ?>

<script>
    const docId = <?= json_encode($docId) ?>;
    const preselectedCat = <?= json_encode($filterCatId) ?>;
    const preselectedSort = <?= json_encode($filterSort) ?>;
    
    // State for Meta - using collection NAME instead of ID
    let descriptionContent = ""; 
    let shortDescriptionContent = "";
    let keywordsContent = "";
    let selectedCollectionName = "";
    // metaConfirmed: true only after user clicks Done in modal (explicit confirm)
    let metaConfirmed = false;

    function isDarkTheme() {
        const root = document.documentElement;
        if (root.getAttribute('data-theme') === 'dark') return true;
        if (root.getAttribute('data-theme') === 'light') return false;
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    const darkLink = document.getElementById('tui-dark-theme');
    if (isDarkTheme()) { darkLink.removeAttribute('disabled'); }

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((m) => {
            if(m.type === 'attributes' && m.attributeName === 'data-theme') {
                if (isDarkTheme()) darkLink.removeAttribute('disabled');
                else darkLink.setAttribute('disabled', 'true');
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });

    // --- MAIN EDITOR ---
    const editor = new toastui.Editor({
        el: document.querySelector('#editor'),
        height: '100%',
        initialEditType: 'markdown',
        previewStyle: 'tab',
        hideModeSwitch: true, 
        theme: isDarkTheme() ? 'dark' : 'light',
        initialValue: '',
        usageStatistics: false,
        extendedAutolinks: false,
        referenceDefinition: false
    });
    
    // Paste fix
    if(editor.getMarkdown) {
        document.querySelector('#editor').addEventListener('paste', function(e) {
            if (e.clipboardData && e.clipboardData.getData) {
                const text = e.clipboardData.getData('text/plain');
                if (text) {
                    e.preventDefault(); e.stopPropagation(); editor.insertText(text);
                }
            }
        }, true);
    }

    // --- DATA & DOM ELEMENTS PREP ---
    // Modal elements referenced early so we can initialize them after fetching doc
    const modal = document.getElementById('metaModal');
    const descTextarea = document.getElementById('metaDescription');
    const shortDescTextarea = document.getElementById('metaDescShort');
    const keywordsInput = document.getElementById('metaKeywords');
    const collectionSelect = document.getElementById('metaCollection');

    // --- DATA LOADING ---
    function loadCollections() {
        fetch('api_md.php?action=get_chroma_collections')
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    const sel = document.getElementById('metaCollection');
                    // Clear existing options except the first
                    while (sel.options.length > 1) {
                        sel.remove(1);
                    }
                    
                    res.data.forEach(col => {
                        const opt = document.createElement('option');
                        opt.value = col.name; // Now using NAME as value
                        opt.text = `${col.name} (${col.type})`;
                        sel.add(opt);
                    });
                    
                    // Set saved value if exists
                    if(selectedCollectionName) {
                        sel.value = selectedCollectionName;
                    }
                }
            })
            .catch(err => console.error("Failed to load collections:", err));
    }

    // Load collections on startup
    loadCollections();

    // --- LOAD DOCUMENT ---
    if (docId) {
        fetch(`api_md.php?action=get&id=${docId}`)
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    const data = res.data;
                    
                    // Set title
                    document.getElementById('docName').value = data.name || '';
                    
                    // Set category
                    if(data.category_id) {
                        const sel = document.getElementById('docCategory');
                        if(sel.querySelector(`option[value="${data.category_id}"]`)) {
                            sel.value = data.category_id;
                        }
                    }
                    
                    // Store description, short description, keywords and collection NAME
                    descriptionContent = data.description ?? "";
                    shortDescriptionContent = data.desc_short ?? "";
                    keywordsContent = data.keywords ?? "";
                    selectedCollectionName = data.target_collection ?? "";
                    
                    console.log("Document loaded:", {
                        id: docId,
                        description: descriptionContent,
                        desc_short: shortDescriptionContent,
                        keywords: keywordsContent,
                        collection: selectedCollectionName
                    });
                    
                    // Ensure the modal fields are initialized (fixes truncation when modal never opened)
                    try {
                        if (descTextarea) descTextarea.value = descriptionContent;
                        if (shortDescTextarea) shortDescTextarea.value = shortDescriptionContent;
                        if (keywordsInput) keywordsInput.value = keywordsContent;
                    } catch (e) {
                        console.warn("Could not set meta modal values on load:", e);
                    }
                    
                    // Set collection dropdown if already populated
                    const metaSel = document.getElementById('metaCollection');
                    if(metaSel && selectedCollectionName) {
                        metaSel.value = selectedCollectionName;
                    }

                    // Set main editor content
                    setTimeout(() => { 
                        editor.setMarkdown(data.content || ''); 
                        editor.moveCursorToStart(); 
                    }, 50);
                } else { 
                    alert("Error loading document: " + res.message); 
                }
            })
            .catch(err => { 
                console.error("Fetch error:", err); 
                alert("Failed to load document."); 
            });
    } else {
        editor.setMarkdown('# New Document\nStart writing here...');
        // initialize meta textarea and short/keywords for new documents too
        if(descTextarea) descTextarea.value = '';
        if(shortDescTextarea) shortDescTextarea.value = '';
        if(keywordsInput) keywordsInput.value = '';
        if(preselectedCat) {
             const sel = document.getElementById('docCategory');
             if(sel.querySelector(`option[value="${preselectedCat}"]`)) sel.value = preselectedCat;
        }
    }

    // --- MODAL LOGIC ---
    document.getElementById('btnOpenMeta').addEventListener('click', () => {
        console.log("Opening modal - current state:", {
            description: descriptionContent,
            desc_short: shortDescriptionContent,
            keywords: keywordsContent,
            collection: selectedCollectionName
        });
        
        // Set modal fields value directly from in-memory state
        if (descTextarea) descTextarea.value = descriptionContent;
        if (shortDescTextarea) shortDescTextarea.value = shortDescriptionContent;
        if (keywordsInput) keywordsInput.value = keywordsContent;
        
        // Set collection select (by NAME)
        if (collectionSelect) collectionSelect.value = selectedCollectionName || "";
        
        // Show modal
        modal.style.display = 'flex';
        
        // Focus description textarea
        setTimeout(() => descTextarea.focus(), 100);
    });

    // Done (explicit confirm) -> update in-memory description, short description, keywords and mark metaConfirmed
    document.getElementById('btnMetaDone').addEventListener('click', () => {
        if (descTextarea) {
            descriptionContent = descTextarea.value;
        } else {
            descriptionContent = "";
        }
        if (shortDescTextarea) {
            shortDescriptionContent = shortDescTextarea.value;
        } else {
            shortDescriptionContent = "";
        }
        if (keywordsInput) {
            keywordsContent = keywordsInput.value;
        } else {
            keywordsContent = "";
        }

        // Also save selected collection from the modal (collection changes are immediate intent)
        if (collectionSelect) {
            selectedCollectionName = collectionSelect.value;
        }
        metaConfirmed = true;
        console.log("Modal Done - confirmed:", { descriptionContent, shortDescriptionContent, keywordsContent, selectedCollectionName });
        modal.style.display = 'none';
    });

    function closeMeta() {
        // Closing without Done = cancel; do not change in-memory values or metaConfirmed
        console.log("Modal closed/cancelled - no changes confirmed.");
        modal.style.display = 'none';
    }

    document.getElementById('btnCloseMeta').addEventListener('click', closeMeta);
    
    // Close on overlay click -> cancel (do not confirm)
    modal.addEventListener('click', (e) => {
        if(e.target === modal) {
            closeMeta();
        }
    });
    
    // --- CATEGORY ADD ---
    document.getElementById('btnAddCategory').addEventListener('click', () => {
        const newName = prompt("Enter new category name:");
        if(newName && newName.trim() !== "") {
            fetch('api_md.php?action=create_category', { 
                method: 'POST', 
                body: JSON.stringify({ name: newName.trim() }) 
            })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    const opt = document.createElement('option'); 
                    opt.value = res.id; 
                    opt.text = res.name;
                    const sel = document.getElementById('docCategory'); 
                    sel.add(opt); 
                    sel.value = res.id;
                    showToast("Category created!");
                } else { 
                    alert("Error: " + res.message); 
                }
            })
            .catch(e => alert("Request failed"));
        }
    });

    // --- SAVE ---
    document.getElementById('btnSave').addEventListener('click', () => {
        // For description/short/keywords: only apply change if user explicitly confirmed via Done (metaConfirmed === true).
        // If metaConfirmed is false, keep the loaded values (do nothing) — they were initialized on load.
        if (metaConfirmed) {
            // descriptionContent, shortDescriptionContent and keywordsContent already updated in Done handler (including empty strings)
        } else {
            // No confirmation -- keep existing values (do nothing)
        }

        // For collection, we will always read the current select value (user may have changed via modal before hitting Done).
        // If the collection was changed but Done wasn't pressed, we must ensure we don't accidentally capture it.
        // Current behavior: read collectionSelect regardless (consistent with previous behavior).
        if (collectionSelect) selectedCollectionName = collectionSelect.value;

        console.log("Saving document:", {
            id: docId,
            description: descriptionContent,
            desc_short: shortDescriptionContent,
            keywords: keywordsContent,
            collection: selectedCollectionName,
            metaConfirmed
        });

        const btn = document.getElementById('btnSave'); 
        const originalContent = btn.innerHTML; 
        btn.innerHTML = '⏳'; 
        btn.disabled = true;
        
        const payload = { 
            id: docId, 
            name: document.getElementById('docName').value, 
            content: editor.getMarkdown(), 
            category_id: document.getElementById('docCategory').value || 1,
            description: descriptionContent,
            desc_short: shortDescriptionContent,
            keywords: keywordsContent,
            target_collection: selectedCollectionName || null // Now sending NAME instead of ID
        };

        fetch('api_md.php?action=save', { 
            method: 'POST', 
            body: JSON.stringify(payload) 
        })
        .then(r => r.json())
        .then(res => {
            btn.innerHTML = originalContent; 
            btn.disabled = false;
            if(res.status === 'success') {
                showToast("Document saved!");
                // reset metaConfirmed after successful save (server state now matches in-memory state)
                metaConfirmed = false;
                if(!docId && res.id) {
                    let url = `edit_md.php?id=${res.id}`;
                    if(preselectedCat) url += `&category_id=${encodeURIComponent(preselectedCat)}`;
                    if(preselectedSort) url += `&sort=${encodeURIComponent(preselectedSort)}`;
                    window.location.href = url;
                }
            } else { 
                alert("Save Failed: " + res.message); 
            }
        })
        .catch(err => {
            btn.innerHTML = originalContent; 
            btn.disabled = false;
            console.error("Save error:", err);
            alert("Save request failed");
        });
    });

    function showToast(msg) {
        const x = document.getElementById("toast"); 
        if(msg) x.innerText = msg; 
        x.className = "show"; 
        setTimeout(() => { 
            x.className = x.className.replace("show", ""); 
        }, 3000);
    }
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);