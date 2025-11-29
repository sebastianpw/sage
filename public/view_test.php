<?php
// This file is a static UI component showcase.
// No business logic, session, or database connection is needed.
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>UI Component Showcase (Dark / Light Theme)</title>
    <script>
    (function() {
        try {
        var theme = localStorage.getItem('spw_theme');
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else if (theme === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        }
        // If no theme is set, we do nothing and let the CSS media query handle it.
        } catch (e) {
        // Fails gracefully
        }
    })();
    </script>
    <link rel="stylesheet" href="/css/base.css">

</head>
<body>
    <div class="container">
        <div class="header">
            <h1>UI Component Showcase</h1>
            <div class="flex-gap">
                <span class="badge badge-green">v1.0</span>
                <span class="badge badge-gray">Theme: auto</span>
            </div>
        </div>

        <!-- rest of markup is identical to your original file -->
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
                            <hr style="border-color: rgba(var(--muted-border-rgb), 0.12); margin: 16px 0;">
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
           --------------------------- */
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
        function openModal() { modalOverlay.classList.add('active'); }
        function closeModal() { modalOverlay.classList.remove('active'); }
        if (openModalBtn) openModalBtn.addEventListener('click', openModal);
        closeModalBtns.forEach(btn => { if (btn) btn.addEventListener('click', closeModal); });
        if (modalOverlay) {
            modalOverlay.addEventListener('click', function(event) {
                if (event.target === modalOverlay) closeModal();
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
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                toast.classList.remove('show');
                toast.addEventListener('transitionend', () => toast.remove());
            }, 3000);
        }
        if (showToastSuccessBtn) showToastSuccessBtn.addEventListener('click', () => showToast('Action completed successfully!', 'success'));
        if (showToastErrorBtn) showToastErrorBtn.addEventListener('click', () => showToast('Failed to complete action.', 'error'));

    });
    </script>
</body>
</html>
