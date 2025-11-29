<?php
// This file is a static UI component showcase.
// No business logic, session, or database connection is needed.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UI Component Showcase (Dark Theme)</title>
    <style>
        /* --- CORE STYLES FROM posts_admin.php --- */
        :root {
            --bg: #0d1117;
            --card: #161b22;
            --border: #30363d;
            --text: #c9d1d9;
            --text-muted: #8b949e;
            --accent: #3b82f6; /* Blue */
            --green: #238636;
            --red: #da3633;
            --orange: #f59e0b; /* Warning */
            --blue-light-bg: rgba(56, 139, 253, 0.1);
            --blue-light-text: #79c0ff;
            --blue-light-border: rgba(59,130,246,0.3);
        }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            font-size: 14px;
            line-height: 1.5;
        }
        .container {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 16px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 16px;
        }
        .header h1 { margin: 0; font-size: 24px; }

        /* --- UI COMPONENT STYLES (EXPANDED) --- */

        /* Utility */
        .section { margin-bottom: 40px; }
        .section-header { margin-bottom: 16px; font-size: 20px; font-weight: 600; color: var(--text); }
        .flex-gap { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            line-height: 20px;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            user-select: none;
            border: 1px solid var(--border);
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.3, 0, 0.5, 1);
        }
        .btn:disabled { cursor: not-allowed; opacity: 0.6; }
        .btn-primary { color: white; background-color: var(--green); border-color: rgba(240, 246, 252, 0.1); }
        .btn-primary:hover:not(:disabled) { background-color: #2ea043; }
        .btn-secondary { color: var(--text); background-color: #21262d; border-color: rgba(240, 246, 252, 0.1); }
        .btn-secondary:hover:not(:disabled) { background-color: #30363d; }
        .btn-accent { color: white; background-color: var(--accent); border-color: rgba(240, 246, 252, 0.1); }
        .btn-accent:hover:not(:disabled) { background-color: #2563eb; }
        .btn-danger { color: #f85149; background: transparent; border: 1px solid transparent; }
        .btn-danger:hover:not(:disabled) { background-color: rgba(218, 54, 51, 0.1); border-color: var(--border); color: var(--red); }
        .btn-sm { padding: 5px 10px; font-size: 12px; }

        /* Notifications / Alerts */
        .notification { padding: 16px; margin-bottom: 16px; border: 1px solid var(--border); border-radius: 6px; }
        .notification-info { background-color: var(--blue-light-bg); color: var(--blue-light-text); border-color: var(--blue-light-border); }
        .notification-success { background-color: rgba(35, 134, 54, 0.15); color: #3fb950; border-color: rgba(35, 134, 54, 0.4); }
        .notification-warning { background-color: rgba(245, 159, 11, 0.15); color: #f59e0b; border-color: rgba(245, 159, 11, 0.4); }
        .notification-error { background-color: rgba(218, 54, 51, 0.1); color: #f85149; border-color: rgba(218, 54, 51, 0.3); }

        /* Table (desktop/tablet) */
        .posts-table { width: 100%; border-collapse: collapse; }
        .posts-table th, .posts-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border); }
        .posts-table th { font-weight: 600; color: var(--text-muted); }
        .posts-table tr { background-color: var(--card); }
        .posts-table tr:hover { background-color: #1f252e; }
        .posts-table td .post-title { font-weight: 600; color: var(--text); }
        .posts-table td .post-slug { font-family: monospace; font-size: 12px; color: var(--text-muted); }
        .empty-state { text-align: center; padding: 40px; border: 1px dashed var(--border); border-radius: 6px; background-color: var(--card); }

        /* Badges */
        .badge { display: inline-block; padding: 2px 8px; font-size: 11px; font-weight: 600; border-radius: 99px; border: 1px solid transparent; }
        .badge-blue { background-color: var(--blue-light-bg); color: var(--blue-light-text); border-color: var(--blue-light-border); }
        .badge-green { background-color: rgba(35, 134, 54, 0.15); color: #3fb950; border-color: rgba(35, 134, 54, 0.4); }
        .badge-red { background-color: rgba(218, 54, 51, 0.1); color: #f85149; border-color: rgba(218, 54, 51, 0.3); }
        .badge-gray { background-color: #21262d; color: var(--text-muted); border-color: var(--border); }
        
        /* Form Elements */
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-weight: 500; margin-bottom: 6px; color: var(--text); }
        .form-control {
            display: block;
            width: 100%;
            padding: 8px 12px;
            font-size: 14px;
            line-height: 20px;
            color: var(--text);
            background-color: var(--bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            box-sizing: border-box; /* Important for width: 100% */
        }
        .form-control:focus { border-color: var(--accent); outline: 0; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); }
        textarea.form-control { min-height: 120px; font-family: inherit; }
        .form-check { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .form-check-input { width: 16px; height: 16px; }

        /* Card */
        .card { background-color: var(--card); border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
        .card-header, .card-body, .card-footer { padding: 16px; }
        .card-header { border-bottom: 1px solid var(--border); font-size: 16px; font-weight: 600; }
        .card-footer { border-top: 1px solid var(--border); background-color: #11151c; }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(0, 0, 0, 0.7);
            display: none; /* Hidden by default */
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            animation: modal-fade-in 0.3s ease;
        }
        .modal-header { padding: 16px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); }
        .modal-title { font-size: 18px; font-weight: 600; margin: 0; }
        .modal-body { padding: 16px; overflow-y: auto; }
        .modal-footer { padding: 12px 16px; text-align: right; border-top: 1px solid var(--border); background-color: var(--bg); }
        .close-button { background: none; border: none; font-size: 24px; color: var(--text-muted); cursor: pointer; line-height: 1; padding: 0; }
        .close-button:hover { color: var(--text); }
        @keyframes modal-fade-in { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }

        /* Toast Notifications */
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 1050; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            padding: 12px 16px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
            color: var(--text);
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.4s ease-in-out;
            min-width: 250px;
        }
        .toast.show { opacity: 1; transform: translateX(0); }
        .toast-success { background-color: #21262d; border: 1px solid var(--green); }
        .toast-error { background-color: #21262d; border: 1px solid var(--red); }

        /* -----------------
           RESPONSIVE: TABLE -> CARD on small screens
           ----------------- */
        /* Add a modifier class to the table so we only transform tables we want */
        .posts-table--responsive { /* no-op desktop defaults */ }

        @media (max-width: 720px) {
            /* Hide native table headers on small screens */
            .posts-table--responsive { border: 0; }
            .posts-table--responsive thead { display: none; }

            /* Make each row a 'card' */
            .posts-table--responsive tbody tr {
                display: block;
                margin-bottom: 12px;
                border: 1px solid var(--border);
                border-radius: 8px;
                background-color: var(--card);
                padding: 12px;
                box-shadow: 0 6px 18px rgba(2,6,23,0.4);
            }

            /* Each cell becomes a horizontal row inside the card:
               label (from data-label) on the left, value on the right */
            .posts-table--responsive tbody td {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 8px 0;
                border: none;
                gap: 12px;
                flex-wrap: nowrap;
            }

            /* label styling (uses data-label attr) */
            .posts-table--responsive tbody td::before {
                content: attr(data-label);
                color: var(--text-muted);
                font-weight: 600;
                margin-right: 12px;
                flex: 0 0 36%;
                max-width: 36%;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            /* Value area (the cell's actual content) */
            .posts-table--responsive tbody td > * {
                flex: 1 1 auto;
            }

            /* Make sure first column's title/slug still looks good */
            .posts-table--responsive tbody td:first-child::before {
                /* shorter label for space, but still visible */
                min-width: 30%;
            }
            .posts-table--responsive tbody td .post-title {
                display: block;
                font-size: 15px;
                line-height: 1.2;
            }
            .posts-table--responsive tbody td .post-slug {
                display: block;
                margin-top: 6px;
                color: var(--text-muted);
            }

            /* Actions row - keep buttons aligned right */
            .posts-table--responsive tbody td.action-cell {
                padding-top: 6px;
            }
            .posts-table--responsive tbody td.action-cell .flex-gap {
                justify-content: flex-end;
                gap: 8px;
            }

            /* Badges and dates: allow them to wrap nicely */
            .posts-table--responsive tbody td .badge { margin-left: 6px; }

            /* Slightly reduce padding to save space on very small phones */
            @media (max-width: 360px) {
                .posts-table--responsive tbody td::before { flex-basis: 40%; max-width: 40%; }
                .posts-table--responsive tbody td .post-title { font-size: 14px; }
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>UI Component Showcase</h1>
            <div class="flex-gap">
                <span class="badge badge-green">v1.0</span>
                <span class="badge badge-gray">Dark Theme</span>
            </div>
        </div>
        
        <!-- SECTION: Buttons -->
        <div class="section">
            <h2 class="section-header">Buttons</h2>
            <div class="card">
                <div class="card-body flex-gap">
                    <button class="btn btn-primary">Primary Action</button>
                    <button class="btn btn-secondary">Secondary Action</button>
                    <button class="btn btn-accent">Accent Action</button>
                    <button class="btn btn-danger">Delete Action</button>
                    <button class="btn btn-secondary" disabled>Disabled</button>
                    <button class="btn btn-primary btn-sm">Small Button</button>
                </div>
            </div>
        </div>

        <!-- SECTION: Alerts & Notifications -->
        <div class="section">
            <h2 class="section-header">Alerts / Notifications</h2>
            <div class="notification notification-info">
                <strong>Heads up!</strong> This is an informational message.
            </div>
            <div class="notification notification-success">
                <strong>Success!</strong> Your changes have been saved.
            </div>
             <div class="notification notification-warning">
                <strong>Warning!</strong> Your session is about to expire.
            </div>
            <div class="notification notification-error">
                <strong>Error!</strong> Could not connect to the server.
            </div>
        </div>

        <!-- SECTION: Modals & Toasts -->
        <div class="section">
            <h2 class="section-header">Modals & Toasts</h2>
             <div class="card">
                <div class="card-body flex-gap">
                    <button id="openModalBtn" class="btn btn-secondary">Open Modal</button>
                    <button id="showToastSuccessBtn" class="btn btn-secondary">Show Success Toast</button>
                    <button id="showToastErrorBtn" class="btn btn-secondary">Show Error Toast</button>
                </div>
            </div>
        </div>
        
        <!-- SECTION: Tables -->
        <div class="section">
             <h2 class="section-header">Data Table</h2>
             <!-- added posts-table--responsive to enable the responsive card view on mobile -->
             <table class="posts-table posts-table--responsive" role="table" aria-label="Posts table">
                <thead>
                    <tr>
                        <th>Title / Slug</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="post-title">SAGE v0.3 - Swipe, Search & Style</div>
                            <div class="post-slug">sage-v0-3-swipe</div>
                        </td>
                        <td><span class="badge badge-blue">Update Log</span></td>
                        <td><span class="badge badge-green">Published</span></td>
                        <td>Mar 15, 2024 10:30</td>
                        <td class="action-cell">
                            <div class="flex-gap">
                                <button class="btn btn-sm btn-secondary">Edit</button>
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="post-title">Introducing Frame Generation</div>
                            <div class="post-slug">intro-frame-gen</div>
                        </td>
                        <td><span class="badge badge-blue">Feature</span></td>
                        <td><span class="badge badge-green">Published</span></td>
                        <td>Feb 28, 2024 14:00</td>
                        <td class="action-cell">
                            <div class="flex-gap">
                                <button class="btn btn-sm btn-secondary">Edit</button>
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </div>
                        </td>
                    </tr>
                     <tr>
                        <td>
                            <div class="post-title">New Blog Post Draft</div>
                            <div class="post-slug">new-blog-post-draft</div>
                        </td>
                        <td><span class="badge badge-blue">Blog</span></td>
                        <td><span class="badge badge-gray">Draft</span></td>
                        <td>Mar 18, 2024 09:15</td>
                        <td class="action-cell">
                            <div class="flex-gap">
                                <button class="btn btn-sm btn-secondary">Edit</button>
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h2 class="section-header">Empty State</h2>
            <div class="empty-state">
                <p>No items found.</p>
                <button class="btn btn-primary">Create Your First Item</button>
            </div>
        </div>
        
        <!-- SECTION: Forms -->
        <div class="section">
            <h2 class="section-header">Form Elements</h2>
            <div class="card">
                <form onsubmit="event.preventDefault(); alert('Form submitted!');">
                    <div class="card-body">
                         <div class="form-group">
                            <label for="textInput" class="form-label">Text Input</label>
                            <input type="text" id="textInput" class="form-control" placeholder="Enter title here...">
                        </div>
                        <div class="form-group">
                            <label for="selectInput" class="form-label">Select Dropdown</label>
                            <select id="selectInput" class="form-control">
                                <option>Option 1</option>
                                <option>Option 2</option>
                                <option>Option 3</option>
                            </select>
                        </div>
                         <div class="form-group">
                            <label for="textareaInput" class="form-label">Textarea</label>
                            <textarea id="textareaInput" class="form-control" placeholder="Enter content..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Checkboxes & Radios</label>
                             <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="check1" checked>
                                <label for="check1">Enable feature A</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="check2">
                                <label for="check2">Enable feature B</label>
                            </div>
                            <hr style="border-color: var(--border); margin: 16px 0;">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="radioGroup" id="radio1" checked>
                                <label for="radio1">Choose option X</label>
                            </div>
                             <div class="form-check">
                                <input class="form-check-input" type="radio" name="radioGroup" id="radio2">
                                <label for="radio2">Choose option Y</label>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer flex-gap">
                         <button type="submit" class="btn btn-primary">Save Changes</button>
                         <button type="reset" class="btn btn-secondary">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <!-- MODAL MARKUP (hidden by default) -->
    <div id="demoModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Action</h3>
                <button id="closeModalBtn" class="close-button">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to perform this action? This can be a confirmation message, a form, or any other content.</p>
                <p>Once completed, this action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button id="closeModalBtnSecondary" class="btn btn-secondary">Cancel</button>
                <button class="btn btn-danger">Confirm & Proceed</button>
            </div>
        </div>
    </div>
    
    <!-- TOAST CONTAINER -->
    <div id="toastContainer" class="toast-container"></div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {

        /* ---------------------------
           RESPONSIVE TABLE -> CARD
           ---------------------------
           This small helper copies the table header text into each corresponding
           <td>'s data-label attribute. The CSS then uses data-label for the left-hand
           label shown on mobile card rows. This avoids duplicate markup.
        */
        (function populateDataLabels() {
            const responsiveTables = document.querySelectorAll('.posts-table--responsive');
            responsiveTables.forEach(table => {
                const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    cells.forEach((cell, i) => {
                        const label = headers[i] || '';
                        cell.setAttribute('data-label', label);
                    });
                });
            });
        })();

        // --- Modal Logic ---
        const modalOverlay = document.getElementById('demoModal');
        const openModalBtn = document.getElementById('openModalBtn');
        const closeModalBtns = [
            document.getElementById('closeModalBtn'),
            document.getElementById('closeModalBtnSecondary')
        ];

        function openModal() {
            modalOverlay.classList.add('active');
        }

        function closeModal() {
            modalOverlay.classList.remove('active');
        }

        if (openModalBtn) {
            openModalBtn.addEventListener('click', openModal);
        }

        closeModalBtns.forEach(btn => {
            if (btn) btn.addEventListener('click', closeModal);
        });

        // Close modal if clicking on the overlay
        if (modalOverlay) {
            modalOverlay.addEventListener('click', function(event) {
                if (event.target === modalOverlay) {
                    closeModal();
                }
            });
        }
        
        // --- Toast Logic ---
        const toastContainer = document.getElementById('toastContainer');
        const showToastSuccessBtn = document.getElementById('showToastSuccessBtn');
        const showToastErrorBtn = document.getElementById('showToastErrorBtn');

        function showToast(message, type = 'success') {
            if (!toastContainer) return;
            
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            
            toastContainer.appendChild(toast);

            // Animate in
            setTimeout(() => {
                toast.classList.add('show');
            }, 10); // small delay to allow CSS transition

            // Animate out and remove after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                toast.addEventListener('transitionend', () => {
                    toast.remove();
                });
            }, 3000);
        }

        if (showToastSuccessBtn) {
            showToastSuccessBtn.addEventListener('click', () => {
                showToast('Action completed successfully!', 'success');
            });
        }

        if (showToastErrorBtn) {
            showToastErrorBtn.addEventListener('click', () => {
                showToast('Failed to complete action.', 'error');
            });
        }
    });
    </script>
</body>
</html>