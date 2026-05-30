<?php
// public/edit_json.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; 

$spw = \App\Core\SpwBase::getInstance();

$docId = $_GET['id'] ?? null;
$filterCatId = $_GET['category_id'] ?? '';
$filterSort = $_GET['sort'] ?? ''; 

$pageTitle = $docId ? "Edit JSON File" : "New JSON File";

// Fetch categories
$cats = $pdo->query("SELECT id, name FROM json_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Build Link Params
$linkParams = [];
if ($filterCatId !== '') $linkParams['category_id'] = $filterCatId;
if ($filterSort !== '') $linkParams['sort'] = $filterSort;

// Generate Back Link
$backLink = "view_json.php";
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

<!-- LIBRARY LOAD (JSON EDITOR) -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/jsoneditor/10.0.1/jsoneditor.min.css" rel="stylesheet" type="text/css">

<style>
    /* --- CSS VARIABLES & THEME SETUP --- */
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
        
        /* JSON Editor Specific Dark Overrides */
        --je-bg: #1e252e;
        --je-color: #c9d1d9;
        --je-border: #30363d;
        --je-menu-bg: #161b22;
        --je-menu-active: #30363d;
        --je-value-string: #a5d6ff;
        --je-value-number: #79c0ff;
        --je-key: #c9d1d9;
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

        /* JSON Editor Specific Light Overrides */
        --je-bg: #ffffff;
        --je-color: #24292f;
        --je-border: #d0d7de;
        --je-menu-bg: #f6f8fa;
        --je-menu-active: #e1e4e8;
        --je-value-string: #0a3069;
        --je-value-number: #cf222e;
        --je-key: #24292f;
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
    .btn-meta { background: var(--card); border: 1px solid var(--border); color: var(--text); }
    .btn-meta:hover { border-color: var(--accent); color: var(--accent); }
    .btn-cancel { background: var(--card); border: 1px solid var(--border); color: var(--text-muted); }
    .btn-add { background: var(--card); color: var(--text-muted); border: 1px solid var(--border); }
    .btn-add:hover { color: var(--text); border-color: var(--text); }
    
    /* JSON Editor Container override */
    #editor { flex-grow: 1; height: 100%; border-radius: 4px; overflow: hidden; border: 1px solid var(--border); }
    
    /* --- JSON EDITOR THEME OVERRIDES --- */
    div.jsoneditor { border: none !important; background-color: var(--je-bg); color: var(--je-color); }
    div.jsoneditor-menu { background-color: var(--je-menu-bg); border-bottom: 1px solid var(--je-border); color: var(--je-color); }
    
    /* FIX: Invert Icon Buttons in Dark Mode (Format, Sort, Undo, etc), but NOT the text button (modes) */
    :root[data-theme="dark"] div.jsoneditor-menu > button:not(.jsoneditor-modes) { filter: invert(1) brightness(1.5); }
    
    /* Tree Colors */
    div.jsoneditor-field, div.jsoneditor td, div.jsoneditor th, div.jsoneditor-readonly { color: var(--je-key) !important; }
    div.jsoneditor-value.jsoneditor-string { color: var(--je-value-string) !important; }
    div.jsoneditor-value.jsoneditor-number { color: var(--je-value-number) !important; }
    div.jsoneditor-field:focus, div.jsoneditor-value:focus, div.jsoneditor-field.jsoneditor-highlight, div.jsoneditor-value.jsoneditor-highlight { background-color: var(--je-menu-active); border-radius: 2px; }
    
    /* Code/Text Mode Colors (Ace Editor overrides) */
    div.jsoneditor-text, pre.ace_editor { color: var(--text) !important; background-color: var(--je-bg) !important; }
    .ace_gutter { background: var(--je-menu-bg) !important; color: var(--text-muted) !important; }
    .ace_cursor { color: var(--accent) !important; }
    .ace_marker-layer .ace_active-line { background: var(--je-menu-active) !important; }
    
    /* Status bar */
    div.jsoneditor-statusbar { background-color: var(--je-menu-bg); border-top: 1px solid var(--je-border); color: var(--text-muted); }

    /* Dropdown Menus */
    div.jsoneditor-contextmenu { background-color: var(--card); border: 1px solid var(--border); }
    div.jsoneditor-contextmenu .jsoneditor-menu li button { color: var(--text); background: transparent; }
    div.jsoneditor-contextmenu .jsoneditor-menu li button:hover { background-color: var(--je-menu-active); color: var(--text); }
    div.jsoneditor-contextmenu .jsoneditor-menu li button.jsoneditor-selected { background-color: var(--accent); color: #fff; }

    /* =========================================
       RESPONSIVE MOBILE FIXES 
       ========================================= */
    @media screen and (max-width: 768px) {
        /* Turn the menu into a flex container so items wrap instead of disappearing */
        div.jsoneditor-menu {
            display: flex;
            flex-wrap: wrap;
            height: auto !important; /* Override fixed height */
            min-height: 35px;
            padding: 4px;
            gap: 2px;
            overflow: visible !important;
        }

        /* Adjust buttons to fit */
        div.jsoneditor-menu > button {
            position: relative;
            flex: 0 0 auto;
        }
        
        /* FORCE MODE SWITCHER VISIBILITY */
        /* The library usually positions this absolute right:0, which breaks on mobile. */
        button.jsoneditor-modes {
            display: inline-block !important;
            position: relative !important;
            top: auto !important;
            right: auto !important;
            float: none !important;
            margin-left: auto !important; /* Push to the right in flex layout */
            opacity: 1 !important;
            color: var(--text) !important;
        }

        /* Adjust Search Box to flow naturally or take full width */
        div.jsoneditor-search {
            position: relative !important;
            top: auto !important;
            right: auto !important;
            margin: 2px;
            flex-grow: 1;
            min-width: 120px;
        }
        div.jsoneditor-search div.jsoneditor-frame {
            background: var(--bg);
            border: 1px solid var(--border);
        }
    }

    /* Modal Styles */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--overlay-bg); display: none; justify-content: center; align-items: center; z-index: 5000; backdrop-filter: blur(2px); }
    .modal-content { background-color: var(--modal-bg); color: var(--text); width: 85%; max-width: 900px; height: 80vh; border-radius: 8px; border: 1px solid var(--border); display: flex; flex-direction: column; padding: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.5); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
    .modal-header h3 { margin: 0; font-size: 1.2rem; }
    .modal-body { flex-grow: 1; display: flex; flex-direction: column; gap: 15px; overflow: hidden; }
    .modal-footer { display: flex; justify-content: flex-end; margin-top: 15px; padding-top: 10px; border-top: 1px solid var(--border); }
    
    .modal-label { display: block; font-size: 0.9rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    
    #metaDescription { 
        flex-grow: 1; width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 4px; 
        padding: 10px; font-family: 'Courier New', monospace; font-size: 0.95rem; resize: none; line-height: 1.5;
    }
    #metaDescription:focus { outline: none; border-color: var(--accent); }
    
    #toast { visibility: hidden; min-width: 250px; background-color: var(--card); color: var(--text); text-align: center; border-radius: 4px; padding: 16px; position: fixed; z-index: 9999; bottom: 30px; right: 30px; box-shadow: var(--card-elevation); border-left: 5px solid var(--green); border: 1px solid var(--border); }
    #toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }
    @keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
    @keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }
</style>

<div class="editor-container">
    <div class="toolbar-wrapper">
        <div class="toolbar">
            <a href="<?= $backLink ?>" class="btn btn-cancel" title="Back to Index">↩️</a>
            <input type="text" id="docName" placeholder="JSON Filename" value="">
            
            <button class="btn btn-meta" id="btnOpenMeta" title="Edit Description">✏️</button>

            <button class="btn btn-save" id="btnSave" title="Save JSON">💾</button>
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
            <h3>File Settings</h3>
            <button class="btn btn-sm btn-cancel" id="btnCloseMeta">✕</button>
        </div>
        <div class="modal-body">
            <div style="flex-grow: 1; display: flex; flex-direction: column;">
                <span class="modal-label">Internal Description (Not part of the JSON content):</span>
                <textarea id="metaDescription" placeholder="Enter notes or description here..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-save btn-sm" id="btnMetaDone">Done</button>
        </div>
    </div>
</div>

<div id="toast">Saved Successfully!</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jsoneditor/10.0.1/jsoneditor.min.js"></script>

<script>
    const docId = <?= json_encode($docId) ?>;
    const preselectedCat = <?= json_encode($filterCatId) ?>;
    
    // in-memory description (source of truth unless user confirms change)
    let descriptionContent = ""; 

    // set to true when user confirms via Done in modal
    let metaConfirmed = false;

    function isDarkTheme() {
        const root = document.documentElement;
        if (root.getAttribute('data-theme') === 'dark') return true;
        if (root.getAttribute('data-theme') === 'light') return false;
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    // --- MAIN EDITOR ---
    const container = document.getElementById("editor");
    
    // Config allowing switching modes
    const options = {
        mode: 'tree',
        modes: ['code', 'text', 'tree', 'view'], 
        onModeChange: function (newMode, oldMode) {
            console.log('Mode switched from', oldMode, 'to', newMode);
        },
        onError: function (err) {
            alert(err.toString());
        }
    };
    
    const editor = new JSONEditor(container, options);

    // --- PREPARE MODAL ELEMENTS (early so we can initialize from fetched data) ---
    const modal = document.getElementById('metaModal');
    const descTextarea = document.getElementById('metaDescription');

    // --- DATA LOADING ---
    if (docId) {
        fetch(`api_json.php?action=get&id=${docId}`)
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

                    // Initialize the modal textarea from the loaded description
                    try {
                        if (descTextarea) descTextarea.value = descriptionContent;
                    } catch (e) {
                        console.warn("Could not set metaDescription value on load:", e);
                    }

                    // Set JSON content
                    try {
                        const jsonContent = JSON.parse(data.content || "{}");
                        editor.set(jsonContent);
                    } catch(e) {
                        console.warn("Invalid JSON stored, switching to code mode");
                        editor.setMode('code');
                        try { editor.setText(data.content || ""); } catch (ie) { /* ignore */ }
                    }
                    
                    if(editor.getMode() === 'tree') editor.expandAll();

                } else { 
                    alert("Error loading file: " + res.message); 
                }
            })
            .catch(err => { 
                console.error("Fetch error:", err); 
                alert("Failed to load file."); 
            });
    } else {
        // Initial State for New File
        editor.set({});
        // initialize meta textarea for new files too
        if (descTextarea) descTextarea.value = '';
        if(preselectedCat) {
             const sel = document.getElementById('docCategory');
             if(sel.querySelector(`option[value="${preselectedCat}"]`)) sel.value = preselectedCat;
        }
    }

    // --- MODAL LOGIC ---
    document.getElementById('btnOpenMeta').addEventListener('click', () => {
        // Populate textarea from in-memory state when opening modal
        if (descTextarea) descTextarea.value = descriptionContent;
        // Show modal
        modal.style.display = 'flex';
        // Focus textarea
        setTimeout(() => descTextarea.focus(), 100);
    });

    // Done (explicit confirm) -> mark metaConfirmed and update in-memory description (can be empty)
    document.getElementById('btnMetaDone').addEventListener('click', () => {
        if (descTextarea) {
            descriptionContent = descTextarea.value;
        } else {
            descriptionContent = "";
        }
        metaConfirmed = true;
        modal.style.display = 'none';
    });

    // Close/cancel (do not confirm changes)
    document.getElementById('btnCloseMeta').addEventListener('click', () => {
        // simply hide modal, do not modify descriptionContent or metaConfirmed
        modal.style.display = 'none';
    });

    // Overlay click behaves like cancel (do not confirm)
    modal.addEventListener('click', (e) => { if(e.target === modal) modal.style.display = 'none'; });
    
    // --- CATEGORY ADD ---
    document.getElementById('btnAddCategory').addEventListener('click', () => {
        const newName = prompt("Enter new category name:");
        if(newName && newName.trim() !== "") {
            fetch('api_json.php?action=create_category', { 
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
        // If user explicitly confirmed via Done, descriptionContent already reflects desired value (including empty).
        // If user never confirmed (metaConfirmed === false), we must NOT overwrite stored description with an empty textarea.
        // So we only use descTextarea value when metaConfirmed is true.
        if (metaConfirmed) {
            // descriptionContent already updated on Done
        } else {
            // keep descriptionContent as-is (loaded from server) — do nothing
        }

        // Get content safely in any mode
        let contentStr = "{}";
        try {
            if (typeof editor.getText === 'function') {
                contentStr = editor.getText();
            } else {
                contentStr = JSON.stringify(editor.get(), null, 2);
            }
        } catch(e) {
            alert("Could not retrieve JSON content.");
            return;
        }
        
        const btn = document.getElementById('btnSave'); 
        const originalContent = btn.innerHTML; 
        btn.innerHTML = '⏳'; 
        btn.disabled = true;
        
        const payload = { 
            id: docId, 
            name: document.getElementById('docName').value, 
            content: contentStr, 
            category_id: document.getElementById('docCategory').value || 1,
            description: descriptionContent
        };

        fetch('api_json.php?action=save', { 
            method: 'POST', 
            body: JSON.stringify(payload) 
        })
        .then(r => r.json())
        .then(res => {
            btn.innerHTML = originalContent; 
            btn.disabled = false;
            if(res.status === 'success') {
                showToast("File saved!");
                // After successful save, reset metaConfirmed (not strictly required, descriptionContent now represents server state)
                metaConfirmed = false;
                if(!docId && res.id) {
                    window.location.href = `edit_json.php?id=${res.id}`;
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