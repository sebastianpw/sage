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

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.0/font/bootstrap-icons.css">

<!-- LIBRARY LOAD (CSS) -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css" />
    <link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/theme/toastui-editor-dark.min.css" id="tui-dark-theme" disabled />
<?php else: ?>
    <link rel="stylesheet" href="/vendor/toastui-editor/toastui-editor.min.css" />
    <link rel="stylesheet" href="/vendor/toastui-editor/toastui-editor-dark.min.css" id="tui-dark-theme" disabled />
<?php endif; ?>

<style>
    :root {
        --bg: #f6f8fa;
        --card: #ffffff;
        --border: #d0d7de;
        --text: #24292f;
        --text-muted: #57606a;
        --accent: #0969da;
        --green: #238636;
        --red: #da3633;
        --orange: #f59e0b;
        --overlay-bg: rgba(0,0,0,0.55);
        --focus-ring: rgba(9, 105, 218, 0.15);
    }
    :root[data-theme="dark"] {
        --bg: #0d1117;
        --card: #161b22;
        --border: #30363d;
        --text: #c9d1d9;
        --text-muted: #8b949e;
        --accent: #58a6ff;
        --green: #238636;
        --red: #da3633;
        --orange: #f59e0b;
        --overlay-bg: rgba(0,0,0,0.75);
        --focus-ring: rgba(88, 166, 255, 0.2);
    }
    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) {
            --bg: #0d1117;
            --card: #161b22;
            --border: #30363d;
            --text: #c9d1d9;
            --text-muted: #8b949e;
            --accent: #58a6ff;
            --green: #238636;
            --red: #da3633;
            --orange: #f59e0b;
            --overlay-bg: rgba(0,0,0,0.75);
            --focus-ring: rgba(88, 166, 255, 0.2);
        }
    }

    * { box-sizing: border-box; }
    body {
        margin: 0;
        background-color: var(--bg) !important;
        color: var(--text) !important;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        transition: background-color 0.2s, color 0.2s;
    }

    .editor-container {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 20px);
        max-height: 94vh;
        padding: 8px 10px;
        gap: 8px;
    }
    
    .toolbar-wrapper {
        background: var(--card);
        border-radius: 8px;
        border: 1px solid var(--border);
        padding: 8px 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
    }
    .subheader {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        padding: 8px 2px 2px 2px;
        border-top: 1px solid var(--border);
        margin-top: 8px;
        font-size: 0.85rem;
    }

    .title-input {
        background: var(--bg);
        border: 1px solid var(--border);
        color: var(--text);
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 0.95rem;
        font-weight: 600;
        flex: 1 1 180px;
        min-width: 0;
        height: 34px;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .title-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--focus-ring);
    }

    .field-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-muted);
        margin-bottom: 5px;
    }
    .input-control {
        width: 100%;
        padding: 7px 10px;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--bg);
        color: var(--text);
        font-size: 0.88rem;
        font-family: inherit;
        box-sizing: border-box;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .input-control:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--focus-ring);
    }
    select.input-control {
        cursor: pointer;
    }

    .btn {
        padding: 6px 11px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        text-decoration: none;
        white-space: nowrap;
        transition: background 0.15s, border-color 0.15s, color 0.15s, opacity 0.15s, transform 0.1s;
        height: 34px;
        flex: 0 0 auto;
    }
    .btn:active { transform: scale(0.97); }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-primary:hover { filter: brightness(1.08); }
    .btn-save { background: var(--green); color: #fff; }
    .btn-save:hover { filter: brightness(1.08); }
    .btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text); }
    .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
    .btn-sm { padding: 4px 8px; font-size: 0.78rem; height: 28px; }

    #editor {
        flex: 1;
        min-height: 0;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
    }
    
    /* Modals */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: var(--overlay-bg);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9000;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        padding: 12px;
    }
    .modal-content {
        background-color: var(--card);
        color: var(--text);
        width: 100%;
        max-width: 680px;
        max-height: 85vh;
        border-radius: 10px;
        border: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        padding: 18px 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        border-bottom: 1px solid var(--border);
        padding-bottom: 10px;
    }
    .modal-header h3 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .modal-body {
        flex: 1;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 12px;
        min-height: 0;
    }
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 14px;
        padding-top: 10px;
        border-top: 1px solid var(--border);
    }

    textarea.input-control {
        resize: vertical;
        font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
        line-height: 1.45;
    }

    #toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 99999;
        background: var(--card);
        color: var(--text);
        border: 1px solid var(--border);
        border-left: 4px solid var(--green);
        border-radius: 6px;
        padding: 10px 16px;
        font-size: 0.88rem;
        display: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }

    @media (max-width: 600px) {
        .editor-container { padding: 6px; }
        .toolbar-wrapper { padding: 6px 8px; }
        .title-input { flex: 1 1 140px; font-size: 0.9rem; }
        .btn { padding: 5px 9px; font-size: 0.8rem; }
    }
</style>

<div class="editor-container">
    <div class="toolbar-wrapper">
        <div class="toolbar">
            <a href="<?= $backLink ?>" class="btn btn-ghost" style="margin-left: 40px;" title="Back to Index"><i class="bi bi-arrow-left"></i></a>
            <input style="flex: 0 0 120px !important;" type="text" id="docName" class="title-input" placeholder="Document Title" value="">
            
            <button class="btn btn-ghost" id="btnOpenMeta" title="Edit Summary & Collection"><i class="bi bi-sliders"></i></button>
            <button class="btn btn-ghost" id="btnExportPdf" title="Export to PDF"><i class="bi bi-file-earmark-pdf"></i></button>
            
            <button class="btn btn-save" id="btnSave" title="Save Document"><i class="bi bi-floppy"></i></button>
            <?php 
                if($docId): 
                    $readLink = "view_md.php?id=$docId"; 
                    if (!empty($linkParams)) $readLink .= "&" . http_build_query($linkParams);
            ?>
                <a href="<?= $readLink ?>" class="btn btn-primary" target="_self" title="Open Read Mode"><i class="bi bi-eye"></i></a>
            <?php endif; ?>
        </div>
        <div class="subheader">
            <span class="field-label" style="margin-bottom:0; display:inline-flex; align-items:center;">Category:</span>
            <select id="docCategory" class="input-control" style="width: auto; min-width: 180px; height: 30px; padding: 3px 8px; font-size: 0.82rem;">
                <?php foreach($cats as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-ghost" id="btnAddCategory" title="Create New Category"><i class="bi bi-plus-lg"></i></button>
        </div>
    </div>
    <div id="editor"></div>
</div>

<!-- META MODAL -->
<div class="modal-overlay" id="metaModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="bi bi-sliders" style="color:var(--accent);"></i> Document Summary & Settings</h3>
            <button class="btn btn-sm btn-ghost" id="btnCloseMeta"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <div>
                <label class="field-label" for="metaCollection">Target Chroma Collection (for Parser):</label>
                <select id="metaCollection" class="input-control">
                    <option value="">-- No Collection --</option>
                </select>
            </div>

            <div>
                <label class="field-label" for="metaDescription">Summary / Description (Markdown):</label>
                <textarea id="metaDescription" class="input-control" style="min-height: 140px;" placeholder="Enter description in Markdown format..."></textarea>
            </div>

            <div>
                <label class="field-label" for="metaDescShort">Short Description (Brief summary):</label>
                <textarea id="metaDescShort" class="input-control" style="min-height: 65px;" placeholder="Short summary for lists, previews..."></textarea>
            </div>

            <div>
                <label class="field-label" for="metaKeywords">Keywords (comma separated):</label>
                <input id="metaKeywords" class="input-control" placeholder="keyword1, keyword2, ..." />
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-save btn-sm" id="btnMetaDone">Done</button>
        </div>
    </div>
</div>

<!-- PDF MODAL -->
<div class="modal-overlay" id="pdfModal">
    <div class="modal-content" style="height: auto; max-width: 420px;">
        <div class="modal-header">
            <h3><i class="bi bi-file-earmark-pdf" style="color:var(--accent);"></i> Export to PDF</h3>
            <button class="btn btn-sm btn-ghost" id="btnClosePdf"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <div>
                <label class="field-label" for="pdfPageSize">Page Size:</label>
                <select id="pdfPageSize" class="input-control">
                    <option value="A4">A4</option>
                </select>
            </div>
            <div>
                <label class="field-label" for="pdfOrientation">Orientation:</label>
                <select id="pdfOrientation" class="input-control">
                    <option value="portrait">Portrait</option>
                    <option value="landscape">Landscape</option>
                </select>
            </div>
            <div>
                <label class="field-label" for="pdfFontFamily">Font Family:</label>
                <select id="pdfFontFamily" class="input-control">
                    <option value="Helvetica">Helvetica (Default)</option>
                    <option value="Lora">Lora (Serif)</option>
                    <option value="Cinzel">Cinzel (Display)</option>
                    <option value="Bangers">Bangers (Comic)</option>
                    <option value="Times-Roman">Times New Roman</option>
                    <option value="Courier">Courier</option>
                </select>
            </div>
            <div>
                <label class="field-label" for="pdfFontSize">Font Size:</label>
                <input type="number" id="pdfFontSize" class="input-control" value="16" min="6" max="72">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-save btn-sm" id="btnPdfExportDone"><i class="bi bi-download"></i> Export</button>
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
                    while (sel.options.length > 1) {
                        sel.remove(1);
                    }
                    
                    res.data.forEach(col => {
                        const opt = document.createElement('option');
                        opt.value = col.name;
                        opt.text = `${col.name} (${col.type})`;
                        sel.add(opt);
                    });
                    
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
                    
                    document.getElementById('docName').value = data.name || '';
                    
                    if(data.category_id) {
                        const sel = document.getElementById('docCategory');
                        if(sel.querySelector(`option[value="${data.category_id}"]`)) {
                            sel.value = data.category_id;
                        }
                    }
                    
                    descriptionContent = data.description ?? "";
                    shortDescriptionContent = data.desc_short ?? "";
                    keywordsContent = data.keywords ?? "";
                    selectedCollectionName = data.target_collection ?? "";
                    
                    try {
                        if (descTextarea) descTextarea.value = descriptionContent;
                        if (shortDescTextarea) shortDescTextarea.value = shortDescriptionContent;
                        if (keywordsInput) keywordsInput.value = keywordsContent;
                    } catch (e) {
                        console.warn("Could not set meta modal values on load:", e);
                    }
                    
                    const metaSel = document.getElementById('metaCollection');
                    if(metaSel && selectedCollectionName) {
                        metaSel.value = selectedCollectionName;
                    }

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
        if (descTextarea) descTextarea.value = descriptionContent;
        if (shortDescTextarea) shortDescTextarea.value = shortDescriptionContent;
        if (keywordsInput) keywordsInput.value = keywordsContent;
        if (collectionSelect) collectionSelect.value = selectedCollectionName || "";
        
        modal.style.display = 'flex';
        setTimeout(() => descTextarea.focus(), 100);
    });

    document.getElementById('btnMetaDone').addEventListener('click', () => {
        descriptionContent = descTextarea ? descTextarea.value : "";
        shortDescriptionContent = shortDescTextarea ? shortDescTextarea.value : "";
        keywordsContent = keywordsInput ? keywordsInput.value : "";

        if (collectionSelect) {
            selectedCollectionName = collectionSelect.value;
        }
        metaConfirmed = true;
        modal.style.display = 'none';
    });

    function closeMeta() {
        modal.style.display = 'none';
    }

    document.getElementById('btnCloseMeta').addEventListener('click', closeMeta);
    
    modal.addEventListener('click', (e) => {
        if(e.target === modal) {
            closeMeta();
        }
    });
    
    // --- PDF EXPORT LOGIC ---
    const pdfModal = document.getElementById('pdfModal');
    const btnExportPdf = document.getElementById('btnExportPdf');
    
    if (btnExportPdf) {
        btnExportPdf.addEventListener('click', () => {
            pdfModal.style.display = 'flex';
        });
    }
    
    document.getElementById('btnClosePdf').addEventListener('click', () => {
        pdfModal.style.display = 'none';
    });
    
    pdfModal.addEventListener('click', (e) => {
        if(e.target === pdfModal) {
            pdfModal.style.display = 'none';
        }
    });
    
    document.getElementById('btnPdfExportDone').addEventListener('click', () => {
        const mdText = editor.getMarkdown();
        const pageSize = document.getElementById('pdfPageSize').value;
        const orientation = document.getElementById('pdfOrientation').value;
        const fontFamily = document.getElementById('pdfFontFamily').value;
        const fontSize = document.getElementById('pdfFontSize').value;
        const docName = document.getElementById('docName').value || 'Untitled';
        
        const btn = document.getElementById('btnPdfExportDone');
        const origContent = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Exporting...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('markdown_text', mdText);
        formData.append('page_size', pageSize);
        formData.append('orientation', orientation);
        formData.append('font_family', fontFamily);
        formData.append('font_size', fontSize);
        formData.append('title', docName);

        fetch('api_md.php?action=export_pdf', {
            method: 'POST',
            body: formData
        })
        .then(async (response) => {
            if (!response.ok) {
                const errText = await response.text();
                throw new Error(errText || `Server returned HTTP ${response.status}`);
            }

            let filename = (docName.trim().replace(/[^a-zA-Z0-9_\- ]/g, '').replace(/\s+/g, '_') || 'document') + '.pdf';
            const disposition = response.headers.get('Content-Disposition');
            if (disposition && disposition.indexOf('filename=') !== -1) {
                const match = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
                if (match && match[1]) {
                    filename = match[1].replace(/['"]/g, '').trim();
                }
            }

            const blob = await response.blob();
            return { blob, filename };
        })
        .then(({ blob, filename }) => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            showToast("PDF exported successfully!");
            pdfModal.style.display = 'none';
        })
        .catch(err => {
            console.error('PDF export failed:', err);
            alert("PDF Export failed: " + err.message);
        })
        .finally(() => {
            btn.innerHTML = origContent;
            btn.disabled = false;
        });
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
        if (collectionSelect) selectedCollectionName = collectionSelect.value;

        const btn = document.getElementById('btnSave'); 
        const originalContent = btn.innerHTML; 
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i>'; 
        btn.disabled = true;
        
        const payload = { 
            id: docId, 
            name: document.getElementById('docName').value, 
            content: editor.getMarkdown(), 
            category_id: document.getElementById('docCategory').value || 1,
            description: descriptionContent,
            desc_short: shortDescriptionContent,
            keywords: keywordsContent,
            target_collection: selectedCollectionName || null
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

    let toastTimer;
    function showToast(msg, isError = false) {
        const el = document.getElementById('toast');
        if (!el) return;
        el.textContent = msg;
        el.style.borderLeftColor = isError ? 'var(--red)' : 'var(--green)';
        el.style.display = 'block';
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { el.style.display = 'none'; }, 2800);
    }
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
