<?php
// public/view_recipes.php
require_once __DIR__ . '/bootstrap.php';

$pageTitle = "Recipe Management";
ob_start();
?>

<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">

<style>
/* Admin wrapper - consistent with other admin pages */
.admin-wrap { max-width: 1100px; margin: 0 auto; padding: 18px; color: var(--text); }
.admin-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.admin-head h2 { margin: 0; font-weight: 600; font-size: 1.3rem; color: var(--text); }

.recipe-list-container {
    background: var(--card);
    border: 1px solid rgba(var(--muted-border-rgb), 0.08);
    border-radius: 8px;
    padding: 12px;
    box-shadow: var(--card-elevation);
}

.recipe-item {
    background: var(--bg);
    border: 1px solid rgba(var(--muted-border-rgb), 0.12);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    gap: 16px;
}
.recipe-item:last-child { margin-bottom: 0; }
.recipe-item:hover { border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.08); }

.recipe-info { flex: 1; min-width: 0; }
.recipe-name { font-weight: 600; font-size: 1rem; color: var(--text); margin-bottom: 6px; }
.recipe-meta { font-size: 0.85rem; color: var(--text-muted); line-height: 1.6; }
.recipe-meta span { margin-right: 12px; display: inline-block; }
.recipe-meta strong { color: var(--text); font-weight: 600; }

.recipe-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.empty-state { 
    text-align: center; 
    padding: 60px 20px; 
    color: var(--text-muted); 
    font-size: 0.95rem;
}
.empty-state::before {
    content: "📦";
    display: block;
    font-size: 3rem;
    margin-bottom: 16px;
    opacity: 0.5;
}

/* Loading state */
.loading-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}
.loading-spinner {
    width: 40px;
    height: 40px;
    margin: 0 auto 16px;
    border: 4px solid rgba(var(--muted-border-rgb), 0.2);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Modal styles - improved version */
.modal-overlay { 
    position: fixed; 
    inset: 0; 
    background: rgba(0,0,0,0.45); 
    display: none; 
    align-items: center; 
    justify-content: center; 
    z-index: 120000; 
    padding: 12px; 
}
.modal-overlay.active { display: flex; }

.modal-card { 
    width: 100%; 
    max-width: 700px; 
    background: var(--card); 
    border-radius: 10px; 
    box-shadow: 0 8px 30px rgba(2,6,23,0.35); 
    display: flex; 
    flex-direction: column; 
    max-height: 90vh; 
    border: 1px solid rgba(var(--muted-border-rgb),0.06); 
}

.modal-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    padding: 16px 20px; 
    border-bottom: 1px solid rgba(var(--muted-border-rgb),0.08); 
}
.modal-header h3 { 
    margin: 0; 
    font-size: 1.1rem; 
    font-weight: 600; 
    color: var(--text); 
}

.modal-body { 
    padding: 20px; 
    overflow-y: auto; 
    color: var(--text); 
}

.modal-footer { 
    padding: 12px 20px; 
    border-top: 1px solid rgba(var(--muted-border-rgb),0.08); 
    background: var(--bg); 
    display: flex; 
    justify-content: flex-end; 
    gap: 8px; 
}

/* Improved textarea in modal */
.modal-body textarea { 
    width: 100%; 
    min-height: 200px; 
    font-family: ui-monospace, monospace; 
    font-size: 0.85rem; 
    background: var(--bg); 
    color: var(--text); 
    border: 1px solid rgba(var(--muted-border-rgb),0.12); 
    border-radius: 6px; 
    padding: 12px;
    transition: border-color 0.15s ease;
}
.modal-body textarea:focus {
    outline: none;
    border-color: var(--accent);
}

/* Improved list styling */
.modal-body ul { 
    list-style: none; 
    padding: 0; 
    margin: 0; 
}
.modal-body li { 
    padding: 12px; 
    border-bottom: 1px solid rgba(var(--muted-border-rgb),0.06); 
    font-family: monospace; 
    font-size: 0.9rem;
    transition: background 0.15s ease;
}
.modal-body li:hover {
    background: rgba(var(--muted-border-rgb),0.03);
}
.modal-body li:last-child { border-bottom: none; }
.modal-body li strong { 
    color: var(--accent); 
    margin-right: 8px;
    font-weight: 600;
}

/* Info text */
.small-muted { 
    color: var(--text-muted); 
    font-size: 0.85rem; 
    margin: 0 0 12px 0;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .admin-head { flex-direction: column; align-items: flex-start; }
    .recipe-item { flex-direction: column; align-items: flex-start; }
    .recipe-actions { width: 100%; }
    .recipe-actions .btn { flex: 1; }
    .recipe-meta span { display: block; margin: 4px 0; }
    .modal-card { max-height: 95vh; border-radius: 10px 10px 0 0; }
}

@media (max-width: 480px) {
    .admin-wrap { padding: 12px; }
    .recipe-item { padding: 12px; }
    .modal-overlay { padding: 0; }
    .modal-card { border-radius: 10px 10px 0 0; max-height: 100vh; }
}
</style>

<div class="admin-wrap">
    <div style="margin-left: 45px;" class="admin-head">
        <h2>Recipes</h2>
        <a style="display:none;" href="view_video_admin.php" class="btn btn-sm btn-outline-secondary">Back to Video Admin</a>
    </div>

    <div class="recipe-list-container" id="recipeListContainer">
        <div class="loading-state">
            <div class="loading-spinner"></div>
            <p>Loading recipes...</p>
        </div>
    </div>
</div>

<!-- Ingredients Modal -->
<div id="ingredientsModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3>📋 Recipe Ingredients</h3>
            <button class="btn btn-sm btn-outline-secondary modal-close-btn">Close</button>
        </div>
        <div class="modal-body">
            <ul id="ingredientsList"></ul>
        </div>
    </div>
</div>

<!-- Rerun Command Modal -->
<div id="rerunModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3>🔄 Rerun Command</h3>
            <button class="btn btn-sm btn-outline-secondary modal-close-btn">Close</button>
        </div>
        <div class="modal-body">
            <p class="small-muted">Copy this command and run it from your project root.</p>
            <textarea id="rerunCommandText" readonly></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-sm btn-outline-secondary modal-close-btn">Cancel</button>
            <button class="btn btn-sm btn-success" id="copyCommandBtn">📋 Copy to Clipboard</button>
        </div>
    </div>
</div>

<script src="js/toast.js"></script>
<script>
(function() {
    let recipes = [];
    const recipeListContainer = document.getElementById('recipeListContainer');
    
    // Modals
    const ingredientsModal = document.getElementById('ingredientsModal');
    const rerunModal = document.getElementById('rerunModal');
    const copyCommandBtn = document.getElementById('copyCommandBtn');

    // --- Helper Functions ---
    function showToast(msg, type) {
        if (typeof Toast !== 'undefined' && Toast.show) {
            Toast.show(msg, type);
        } else {
            console.log(`[toast-${type || 'info'}]`, msg);
        }
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr + 'Z'); // Assume UTC
        return date.toLocaleString(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short'
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // --- Data Loading & Rendering ---
    function loadRecipes() {
        fetch('recipe_api.php?action=list_recipes')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') {
                    recipes = data.recipes;
                    renderRecipes();
                } else {
                    showToast(data.message || 'Failed to load recipes', 'error');
                    recipeListContainer.innerHTML = '<div class="empty-state">Failed to load recipes</div>';
                }
            })
            .catch(err => {
                showToast('Network error while loading recipes.', 'error');
                recipeListContainer.innerHTML = '<div class="empty-state">Network error - please try again</div>';
            });
    }
    
    function renderRecipes() {
        if (!recipes.length) {
            recipeListContainer.innerHTML = '<div class="empty-state">No recipes found.<br>Run <code>dumpcode.sh</code> to create one!</div>';
            return;
        }

        recipeListContainer.innerHTML = recipes.map(recipe => `
            <div class="recipe-item" data-recipe-id="${recipe.id}" data-rerun-command="${escapeHtml(recipe.rerun_command)}">
                <div class="recipe-info">
                    <div class="recipe-name">${escapeHtml(recipe.group_name)}</div>
                    <div class="recipe-meta">
                        <span><strong>File:</strong> ${escapeHtml(recipe.output_filename)}</span>
                        <span><strong>Ingredients:</strong> ${recipe.ingredient_count}</span>
                        <span><strong>Created:</strong> ${formatDate(recipe.created_at)}</span>
                    </div>
                </div>
                <div class="recipe-actions">
                    <button class="btn btn-sm btn-outline-primary ingredients-btn">📋 Ingredients</button>
                    <button class="btn btn-sm btn-outline-secondary rerun-btn">🔄 Rerun</button>
                    <button class="btn btn-sm btn-outline-danger delete-btn">🗑️ Delete</button>
                </div>
            </div>
        `).join('');
    }

    // --- Event Delegation ---
    document.addEventListener('click', function(e) {
        const target = e.target;
        const recipeItem = target.closest('.recipe-item');

        // Modal close buttons
        if (target.matches('.modal-close-btn')) {
            closeAllModals();
        }

        if (!recipeItem) return;
        
        const recipeId = recipeItem.dataset.recipeId;

        // Show Ingredients
        if (target.matches('.ingredients-btn')) {
            target.disabled = true;
            const originalText = target.textContent;
            target.textContent = 'Loading...';
            
            fetch(`recipe_api.php?action=get_recipe_details&id=${recipeId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ok') {
                        const list = document.getElementById('ingredientsList');
                        list.innerHTML = data.ingredients.map(ing => {
                           const isDb = ing.source_filename.startsWith('db:');
                           const icon = isDb ? '🗄️' : '📄';
                           const label = isDb ? 'Database' : 'File';
                           const filename = ing.source_filename.replace('db:', '');
                           return `<li><strong>${icon} ${label}:</strong> ${escapeHtml(filename)}</li>`;
                        }).join('');
                        openModal(ingredientsModal);
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(err => {
                    showToast('Failed to load ingredients', 'error');
                })
                .finally(() => {
                    target.disabled = false;
                    target.textContent = originalText;
                });
        }
        
        // Show Rerun Command
        if (target.matches('.rerun-btn')) {
            const command = recipeItem.dataset.rerunCommand;
            document.getElementById('rerunCommandText').value = command;
            openModal(rerunModal);
        }

        // Delete Recipe
        if (target.matches('.delete-btn')) {
            if (confirm('Are you sure you want to delete this recipe record? This cannot be undone.')) {
                target.disabled = true;
                const originalText = target.textContent;
                target.textContent = 'Deleting...';
                
                fetch('recipe_api.php?action=delete_recipe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: recipeId })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ok') {
                        showToast('Recipe deleted successfully', 'success');
                        recipeItem.style.transition = 'opacity 0.3s, transform 0.3s';
                        recipeItem.style.opacity = '0';
                        recipeItem.style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            recipeItem.remove();
                            if (document.querySelectorAll('.recipe-item').length === 0) {
                                renderRecipes();
                            }
                        }, 300);
                    } else {
                        showToast(data.message, 'error');
                        target.disabled = false;
                        target.textContent = originalText;
                    }
                })
                .catch(err => {
                    showToast('Failed to delete recipe', 'error');
                    target.disabled = false;
                    target.textContent = originalText;
                });
            }
        }
    });

    // Copy to Clipboard button
    copyCommandBtn.addEventListener('click', function() {
        const commandText = document.getElementById('rerunCommandText');
        commandText.select();
        
        navigator.clipboard.writeText(commandText.value).then(() => {
            showToast('Command copied to clipboard!', 'success');
            this.textContent = '✓ Copied!';
            setTimeout(() => { 
                this.textContent = '📋 Copy to Clipboard'; 
            }, 2000);
        }).catch(err => {
            // Fallback for older browsers
            try {
                document.execCommand('copy');
                showToast('Command copied to clipboard!', 'success');
            } catch (e) {
                showToast('Failed to copy command', 'error');
            }
        });
    });

    // Modal helpers
    function openModal(modal) {
        modal.classList.add('active');
    }

    function closeAllModals() {
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.classList.remove('active');
        });
    }

    // Close modals on overlay click
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeAllModals();
            }
        });
    });

    // Close modals on ESC key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });

    // Initial Load
    loadRecipes();

})();
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>