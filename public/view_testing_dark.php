<?php
// public/view_testing.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();
$pageTitle = "Dark Theme Testing Interface";
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="css/toast.css">
    <style>
        /* === CORE STYLES FROM posts_admin.php === */
        :root { --bg: #0d1117; --card: #161b22; --border: #30363d; --text: #c9d1d9; --text-muted: #8b949e; --accent: #3b82f6; --green: #238636; --red: #da3633; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg); color: var(--text); font-size: 14px; }
        
        /* === UPDATED STYLES WITH HIGHER SPECIFICITY === */
        /* By prefixing every rule with .container, we override any default styles from the layout */
        .container { max-width: 1200px; margin: 24px auto; padding: 0 16px; }
        .container .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .container .header h1 { margin: 0; font-size: 24px; }
        .container .btn { display: inline-block; padding: 8px 16px; font-size: 14px; font-weight: 500; line-height: 20px; white-space: nowrap; vertical-align: middle; cursor: pointer; user-select: none; border: 1px solid var(--border); border-radius: 6px; text-decoration: none; transition: all 0.2s cubic-bezier(0.3, 0, 0.5, 1); }
        .container .btn-primary { color: white; background-color: var(--green); border-color: rgba(240, 246, 252, 0.1); }
        .container .btn-primary:hover { background-color: #2ea043; }
        .container .btn-secondary { color: var(--text); background-color: #21262d; border-color: rgba(240, 246, 252, 0.1); }
        .container .btn-secondary:hover { background-color: #30363d; }
        .container .btn-sm { padding: 5px 10px; font-size: 12px; }
        .container .btn-danger { color: #f85149; background: transparent; border: 1px solid transparent; }
        .container .btn-danger:hover { background-color: rgba(218, 54, 51, 0.1); border-color: var(--border); color: var(--red); }
        
        .container .small-muted { color: var(--text-muted); font-size: 13px; margin-top: 8px; }
        .container .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 6px; margin-bottom: 24px; }
        .container .card-header { padding: 12px 16px; border-bottom: 1px solid var(--border); font-weight: 600; font-size: 16px; }
        .container .card-body { padding: 16px; }
        .container .grid { display:grid; gap: 24px; }
        .container .grid-2 { grid-template-columns:repeat(auto-fit, minmax(350px, 1fr)); }
        .container .grid-3 { grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); }
        .container .form-group { margin-bottom: 16px; }
        .container .form-label { display: block; font-weight: 500; margin-bottom: 8px; color: var(--text-muted); }
        .container .form-control { width: 100%; box-sizing: border-box; padding: 8px 12px; background-color: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 6px; font-size: 14px; transition: all 0.2s ease; }
        .container .form-control:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); }
        .container .responsive-table { width: 100%; border-collapse: collapse; }
        .container .responsive-table th, .container .responsive-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border); }
        .container .responsive-table th { font-weight: 600; color: var(--text-muted); }
        .container .responsive-table tr { background-color: var(--card); }
        .container .responsive-table tr:nth-child(even) { background-color: #1a2029; }
        .container .demo-item { background: var(--bg); padding:16px; border-radius:6px; border-left: 4px solid var(--accent); }
        .container .demo-image { width:100%; height:120px; background-color: #21262d; border-radius:4px; display:flex; align-items:center; justify-content:center; color: var(--text-muted); font-weight:600; }
        
        /* Modal styles are NOT prefixed, as the modal exists outside the .container div */
        .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.65); display:none; align-items:center; justify-content:center; z-index:1000; padding:16px; }
        .modal-card { width:100%; max-width:600px; background:var(--card); border: 1px solid var(--border); border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,0.5); display:flex; flex-direction:column; max-height:90vh; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid var(--border); }
        .modal-header h2 { margin: 0; font-size: 18px; }
        .modal-body { padding:16px; overflow:auto; }
        .modal-footer { padding:12px 16px; border-top:1px solid var(--border); display:flex; gap:8px; justify-content:flex-end; }
        /* Style buttons inside the modal explicitly to match */
        .modal-card .btn { border-color: var(--border); }
        .modal-card .btn-primary { color: white; background-color: var(--green); border-color: rgba(240, 246, 252, 0.1); }
        .modal-card .btn-primary:hover { background-color: #2ea043; }
        .modal-card .btn-secondary { color: var(--text); background-color: #21262d; border-color: rgba(240, 246, 252, 0.1); }
        .modal-card .btn-secondary:hover { background-color: #30363d; }

        /* Toast styles for dark theme */
        #toast-container .toast { background-color: #21262d; color: var(--text); border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.4); }
        #toast-container .toast.success { border-left-color: var(--green); }
        #toast-container .toast.error { border-left-color: var(--red); }
        #toast-container .toast.info { border-left-color: var(--accent); }

        @media (max-width: 768px) {
            .container .responsive-table thead { display: none; }
            .container .responsive-table, .container .responsive-table tbody, .container .responsive-table tr, .container .responsive-table td { display: block; width: 100%; }
            .container .responsive-table tr { margin-bottom: 16px; border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
            .container .responsive-table td { border: none; padding-left: 50%; position: relative; }
            .container .responsive-table td:before { content: attr(data-label); position: absolute; left: 16px; width: 40%; padding-right: 10px; font-weight: 600; color: var(--text-muted); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Dark Theme Testing Interface</h1>
            <div style="display:flex;gap:8px;align-items:center;">
                <button class="btn btn-secondary btn-sm" onclick="showDemoModal()">Open Demo Modal</button>
                <button class="btn btn-primary btn-sm" onclick="showSuccessToast()">Test Toast</button>
            </div>
        </div>
        <p class="small-muted" style="margin-bottom: 24px;">This is a testing interface with the same styling as the admin panel. Use this to test styles, layouts, and basic interactions.</p>
        
        <div class="grid grid-2">
            <div class="card">
                <div class="card-header">Form Elements</div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Text Input</label>
                        <input type="text" class="form-control" placeholder="Enter some text...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Select Dropdown</label>
                        <select class="form-control">
                            <option>Option 1</option>
                            <option>Option 2</option>
                            <option>Option 3</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Textarea</label>
                        <textarea class="form-control" rows="3" placeholder="Multi-line text..."></textarea>
                    </div>
                    <button class="btn btn-primary">Submit Form</button>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Action Buttons</div>
                <div class="card-body">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
                        <button class="btn btn-primary">Primary (Success)</button>
                        <button class="btn btn-secondary">Secondary</button>
                        <button class="btn btn-danger">Danger</button>
                    </div>
                    <div class="demo-item">
                        <strong>Interactive Element</strong>
                        <p class="small-muted" style="margin:8px 0 0 0;">Hover over buttons to see hover states. Click to test interactions.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Demo Data Table</div>
            <div class="card-body" style="overflow-x:auto;">
                <table class="responsive-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td data-label="ID">1</td>
                            <td data-label="Name" style="color: var(--text);"><strong>Test Item Alpha</strong></td>
                            <td data-label="Description">This is a sample description for testing table styles</td>
                            <td data-label="Status"><span style="color:#3fb950;">Active</span></td>
                            <td data-label="Actions">
                                <button class="btn btn-sm btn-secondary">Edit</button>
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </td>
                        </tr>
                        <tr>
                            <td data-label="ID">2</td>
                            <td data-label="Name" style="color: var(--text);"><strong>Test Item Beta</strong></td>
                            <td data-label="Description">Another sample item to demonstrate table layout</td>
                            <td data-label="Status"><span style="color:var(--text-muted);">Inactive</span></td>
                            <td data-label="Actions">
                                <button class="btn btn-sm btn-secondary">Edit</button>
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="demoModal" class="modal-overlay" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="modal-card" role="document">
            <div class="modal-header">
                <h2>Demo Modal</h2>
                <button class="btn btn-sm btn-secondary" onclick="hideDemoModal()">Close</button>
            </div>
            <div class="modal-body">
                <p>This is a demo modal with the same styling as the admin interface.</p>
                <div class="form-group">
                    <label class="form-label">Modal Input Field</label>
                    <input type="text" class="form-control" placeholder="Type something...">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="hideDemoModal()">Cancel</button>
                <button class="btn btn-primary" onclick="handleModalAction()">Confirm Action</button>
            </div>
        </div>
    </div>

    <script src="js/toast.js"></script>
    <script>
    (function(){
        // The JavaScript does not need to be changed.
        const demoModal = document.getElementById('demoModal');
        window.showDemoModal = function() { demoModal.style.display = 'flex'; document.documentElement.style.overflow = 'hidden'; };
        window.hideDemoModal = function() { demoModal.style.display = 'none'; document.documentElement.style.overflow = ''; };
        window.handleModalAction = function() { if (typeof Toast !== 'undefined' && Toast.show) { Toast.show('Modal action confirmed!', 'success'); } hideDemoModal(); };
        window.showSuccessToast = function() { if (typeof Toast !== 'undefined' && Toast.show) { Toast.show('This is a success toast message!', 'success'); } };
        document.addEventListener('click', function(ev) {
            const el = ev.target.closest('button');
            if (!el || !el.closest('tr')) return;
            const row = el.closest('tr');
            const name = row.querySelector('td[data-label="Name"] strong').textContent;
            if (el.matches('.btn-secondary') && el.textContent === 'Edit') { if (typeof Toast !== 'undefined' && Toast.show) Toast.show('Editing: ' + name, 'info'); }
            if (el.matches('.btn-danger') && el.textContent === 'Delete') { if (confirm('Are you sure you want to delete "' + name + '"?')) { if (typeof Toast !== 'undefined' && Toast.show) Toast.show('Deleted: ' + name, 'error'); } }
        });
        demoModal.addEventListener('click', function(e) { if (e.target === demoModal) hideDemoModal(); });
    })();
    </script>
</body>
</html>
<?php
require "floatool.php";
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle);

