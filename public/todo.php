<?php
// enhanced_todo_with_crud.php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// Handle CRUD operations
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create':
                    $name = trim($_POST['name']);
                    $description = trim($_POST['description'] ?? '');
                    $order = (int)($_POST['order'] ?? 100);
                    
                    if (empty($name)) {
                        throw new Exception('Task name is required');
                    }
                    
                    $stmt = $pdoSys->prepare("INSERT INTO sage_todos (name, description, `order`, status, created_at, updated_at) VALUES (?, ?, ?, 'pending', NOW(), NOW())");
                    $stmt->execute([$name, $description, $order]);
                    
                    $_SESSION['message'] = 'Task created successfully';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                    break;
                    
                case 'update':
                    $id = (int)$_POST['id'];
                    $name = trim($_POST['name']);
                    $description = trim($_POST['description'] ?? '');
                    $order = (int)($_POST['order'] ?? 100);
                    $status = $_POST['status'] ?? 'pending';
                    
                    if (empty($name)) {
                        throw new Exception('Task name is required');
                    }
                    
                    $stmt = $pdoSys->prepare("UPDATE sage_todos SET name = ?, description = ?, `order` = ?, status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$name, $description, $order, $status, $id]);
                    
                    $_SESSION['message'] = 'Task updated successfully';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                    break;
                    
                case 'delete':
                    $id = (int)$_POST['id'];
                    
                    $stmt = $pdoSys->prepare("DELETE FROM sage_todos WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $_SESSION['message'] = 'Task deleted successfully';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                    break;
                    
                case 'bulk_reorder':
                    $orders = json_decode($_POST['orders'], true);
                    if ($orders) {
                        $pdoSys->beginTransaction();
                        foreach ($orders as $item) {
                            $stmt = $pdoSys->prepare("UPDATE sage_todos SET `order` = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$item['order'], $item['id']]);
                        }
                        $pdoSys->commit();
                        echo json_encode(['success' => true]);
                        exit;
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Function to categorize priority based on order value
function getPriorityLevel($order) {
    if ($order <= 10) return 'immediate';
    if ($order <= 25) return 'high';
    if ($order <= 50) return 'medium';
    return 'low';
}

// Function to guess area from task name
function guessArea($name, $description = '') {
    $text = strtolower($name . ' ' . $description);
    
    if (strpos($text, 'ui') !== false || strpos($text, 'interface') !== false || strpos($text, 'button') !== false || strpos($text, 'menu') !== false) return 'UI';
    if (strpos($text, 'gallery') !== false) return 'Gallery';
    if (strpos($text, 'generate') !== false || strpos($text, 'generation') !== false || strpos($text, 'prompt') !== false || strpos($text, 'seed') !== false) return 'Generation';
    if (strpos($text, 'model') !== false || strpos($text, 'img2img') !== false || strpos($text, 'sd') !== false) return 'Models';
    if (strpos($text, 'pose') !== false || strpos($text, 'skeleton') !== false || strpos($text, 'openpose') !== false) return 'Pose/Skeleton';
    if (strpos($text, 'database') !== false || strpos($text, 'sql') !== false || strpos($text, 'table') !== false || strpos($text, 'crud') !== false) return 'DB';
    if (strpos($text, 'scheduler') !== false || strpos($text, 'script') !== false || strpos($text, 'shell') !== false) return 'Scheduler';
    if (strpos($text, 'gear') !== false) return 'GearMenu';
    if (strpos($text, 'workflow') !== false) return 'Workflow';
    if (strpos($text, 'bug') !== false || strpos($text, 'fix') !== false || strpos($text, 'error') !== false) return 'Bugs';
    if (strpos($text, 'character') !== false || strpos($text, 'logo') !== false || strpos($text, 'theatrical') !== false) return 'Assets';
    if (strpos($text, 'langchain') !== false || strpos($text, '3d') !== false || strpos($text, 'sketchfab') !== false) return 'Integrations';
    
    return 'General';
}

try {
    // Fetch all todos
    $stmt = $pdoSys->query("SELECT * FROM sage_todos ORDER BY `order` ASC");
    $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process data
    $stats = ['immediate' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
    $processedTodos = [];
    
    foreach ($todos as $todo) {
        $priority = getPriorityLevel($todo['order']);
        $area = guessArea($todo['name'], $todo['description'] ?? '');
        
        $stats[$priority]++;
        
        $processedTodos[] = [
            'id' => $todo['id'],
            'title' => $todo['name'],
            'description' => $todo['description'] ?? '',
            'priority' => $priority,
            'area' => $area,
            'order' => $todo['order'],
            'status' => $todo['status'],
            'created_at' => $todo['created_at'],
            'regenerate_images' => $todo['regenerate_images']
        ];
    }
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Task Dashboard with CRUD</title>


<?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>


<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- SortableJS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<?php else: ?>
    <!-- SortableJS via local copy -->
    <script src="/vendor/sortable/Sortable.min.js"></script>
<?php endif; ?>



    <link rel="stylesheet" href="/css/todo.css">
</head>
<body>
<?php require "floatool.php"; echo $eruda; ?>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="message success">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <?php unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="message error">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="dashboard">
        <div class="header">
            <a href="dashboard.php" class="back-link" title="Dashboard">🔮</a>
            <h2 style="padding-bottom: 5px;">🎫 SAGE TODOs</h2>
            <button class="btn" id="analyzeBtn" onclick="analyzeTasks()">
                <span class="btn-text">🧠 Analyze</span>
            </button>
            <button class="btn success" onclick="openCreateModal()">
                <span class="btn-text">➕ Add New</span>
            </button>
            <button class="btn success" onclick="toggleSortMode()">
                <span class="btn-text" id="sortModeText">🔄 +Drag</span>
            </button>
            <button class="btn secondary" onclick="saveSortOrder()">
                <span class="btn-text">💾 Save</span>
            </button>
        </div>

        <!--
        <div class="crud-controls">
            <button class="btn success" onclick="openCreateModal()">
                <span class="btn-text">➕ Add New</span>
            </button>
            <button class="btn success" onclick="toggleSortMode()">
                <span class="btn-text" id="sortModeText">🔄 Enable Drag & Drop</span>
            </button>
            <button class="btn secondary" onclick="saveSortOrder()">
                <span class="btn-text">💾 Save Order</span>
            </button>
        </div>
        -->

        <div class="ai-controls" style="display: none;">
            <h3>🤖 AI-Powered Task Management</h3>
            <button class="btn" id="analyzeBtn" onclick="analyzeTasks()">
                <span class="btn-text">🧠 Analyze All Tasks</span>
            </button>
            <button style="display:none;" class="btn secondary" id="blockingBtn" onclick="findBlockingTasks()">
                <span class="btn-text">🚧 Find Blocking Tasks</span>
            </button>
            <button style="display:none;" class="btn secondary" id="nextTasksBtn" onclick="suggestNextTasks()">
                <span class="btn-text">📋 Suggest Next 5 Tasks</span>
            </button>
            <button style="display:none;" class="btn secondary" id="immediateBtn" onclick="showImmediate()">
                <span class="btn-text">🚨 Show Critical Tasks</span>
            </button>
        </div>

        <div id="analysisPanel" class="analysis-panel">
            <h4>AI Analysis Results</h4>
            <div id="analysisContent"></div>
        </div>



        <div class="filters" style="font-size: 0.5em;">
            <input type="text" class="search-box" id="searchBox" placeholder="🔍 Search TODOs...">
            
            <div class="filter-group">
                <label>Priority:</label>
                <button class="filter-btn active" data-filter="priority" data-value="all">All</button>
                <button class="filter-btn" data-filter="priority" data-value="immediate">Immediate</button>
                <button class="filter-btn" data-filter="priority" data-value="high">High</button>
                <button class="filter-btn" data-filter="priority" data-value="medium">Medium</button>
                <button class="filter-btn" data-filter="priority" data-value="low">Low</button>
            </div>
            
            <div class="filter-group">
                <label>Area:</label>
                <button class="filter-btn active" data-filter="area" data-value="all">All Areas</button>
                <button class="filter-btn" data-filter="area" data-value="UI">UI</button>
                <button class="filter-btn" data-filter="area" data-value="Gallery">Gallery</button>
                <button class="filter-btn" data-filter="area" data-value="Generation">Generation</button>
                <button class="filter-btn" data-filter="area" data-value="Scheduler">Scheduler</button>
                <button class="filter-btn" data-filter="area" data-value="Bugs">🐛 Bugs</button>
            </div>
        </div>

        <div class="tasks-container">
            <div class="task-grid sortable-list" id="taskGrid">
                <?php foreach ($processedTodos as $todo): ?>
                <div class="task-card <?= $todo['priority'] ?>" 
                     data-id="<?= $todo['id'] ?>"
                     data-priority="<?= $todo['priority'] ?>" 
                     data-area="<?= $todo['area'] ?>"
                     data-order="<?= $todo['order'] ?>"
                     data-search="<?= strtolower($todo['title'] . ' ' . $todo['description']) ?>">
                    
                    <div class="task-header">
                        <div class="task-title">
                            <span class="drag-handle">⋮⋮</span>
                            <?= htmlspecialchars($todo['title']) ?>
                            <?php if ($todo['regenerate_images'] > 0): ?>
                                <span class="regen-indicator">REGEN</span>
                            <?php endif; ?>
                        </div>
                        <div class="task-actions">
                            <button class="btn secondary" onclick="editTask(<?= $todo['id'] ?>)">✏️</button>
                            <button class="btn" onclick="deleteTask(<?= $todo['id'] ?>)">🗑️</button>
                        </div>
                    </div>
                    
                    <?php if (!empty($todo['description'])): ?>
                        <div class="task-description">
                            <?= htmlspecialchars(substr($todo['description'], 0, 200)) ?>
                            <?= strlen($todo['description']) > 200 ? '...' : '' ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="task-meta">
                        <span class="task-badge priority-<?= $todo['priority'] ?>"><?= ucfirst($todo['priority']) ?></span>
                        <span class="task-badge area-badge"><?= $todo['area'] ?></span>
                        <span class="task-badge order-badge">Order: <?= $todo['order'] ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="last-updated">
            Last updated: <?= date('Y-m-d H:i:s') ?>
        </div>
        
        
                <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number immediate"><?= $stats['immediate'] ?></div>
                <div class="stat-label">Immediate (1-10)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number high"><?= $stats['high'] ?></div>
                <div class="stat-label">High (11-25)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number medium"><?= $stats['medium'] ?></div>
                <div class="stat-label">Medium (26-50)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number low"><?= $stats['low'] ?></div>
                <div class="stat-label">Low (51+)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($todos) ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
        </div>
        
        
    </div>

    <!-- Create/Edit Modal -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Task</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="taskForm" method="POST">
                <input type="hidden" id="taskId" name="id">
                <input type="hidden" id="taskAction" name="action" value="create">
                
                <div class="form-group">
                    <label for="taskName">Task Name *</label>
                    <input type="text" id="taskName" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="taskDescription">Description</label>
                    <textarea id="taskDescription" name="description"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="taskOrder">Priority Order</label>
                    <input type="number" id="taskOrder" name="order" value="100" min="1">
                </div>
                
                <div class="form-group">
                    <label for="taskStatus">Status</label>
                    <select id="taskStatus" name="status">
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn success">Save Task</button>
                    <button type="button" class="btn secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>


<script>
        let currentFilters = { priority: 'all', area: 'all' };
        let searchQuery = '';
        let sortMode = false;
        let sortable = null;

        // Task data for editing
        const taskData = <?= json_encode($processedTodos) ?>;

        function filterTasks() {
            const cards = document.querySelectorAll('.task-card');

            cards.forEach(card => {
                const priority = card.dataset.priority;
                const area = card.dataset.area;
                const searchText = card.dataset.search;

                const priorityMatch = currentFilters.priority === 'all' || priority === currentFilters.priority;
                const areaMatch = currentFilters.area === 'all' || area === currentFilters.area;
                const searchMatch = searchQuery === '' || searchText.includes(searchQuery.toLowerCase());

                if (priorityMatch && areaMatch && searchMatch) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function toggleSortMode() {
            sortMode = !sortMode;
            const button = document.getElementById('sortModeText');
            const taskGrid = document.getElementById('taskGrid');

            if (sortMode) {
                button.textContent = '🔒 -Drag';
                taskGrid.classList.add('sortable-enabled');

                sortable = Sortable.create(taskGrid, {
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    handle: '.drag-handle',
                    onEnd: function(evt) {
                        console.log('Item moved from', evt.oldIndex, 'to', evt.newIndex);
                        updateOrderAfterDrag();
                    }
                });
            } else {
                button.textContent = '🔄 +Drag';
                taskGrid.classList.remove('sortable-enabled');
                if (sortable) {
                    sortable.destroy();
                    sortable = null;
                }
            }
        }

        function updateOrderAfterDrag() {
            const cards = Array.from(document.querySelectorAll('.task-card:not([style*="display: none"])'));
            cards.forEach((card, index) => {
                card.dataset.order = index + 1;
                const orderBadge = card.querySelector('.order-badge');
                if (orderBadge) {
                    orderBadge.textContent = 'Order: ' + (index + 1);
                }
            });
        }

        function saveSortOrder() {
            if (!sortMode) {
                alert('Enable drag & drop mode first to reorder tasks');
                return;
            }

            // Get ALL visible cards in their current order
            const allCards = Array.from(document.querySelectorAll('.task-card'));
            const visibleCards = allCards.filter(card => card.style.display !== 'none');

            // Create the new order array with the dragged items
            const orders = visibleCards.map((card, index) => ({
                id: parseInt(card.dataset.id),
                order: index + 1
            }));

            console.log('Saving order:', orders);

            $.post('', {
                action: 'bulk_reorder',
                orders: JSON.stringify(orders)
            }, function(response) {
                if (response.success) {
                    alert('Task order saved successfully!');
                    location.reload();
                } else {
                    alert('Failed to save order: ' + (response.message || 'Unknown error'));
                }
            }, 'json').fail(function(xhr) {
                console.error('Save order failed:', xhr.responseText);
                alert('Failed to save order. Check console for details.');
            });
        }

        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Add New Task';
            document.getElementById('taskAction').value = 'create';
            document.getElementById('taskForm').reset();
            document.getElementById('taskId').value = '';
            document.getElementById('taskOrder').value = Math.max(...taskData.map(t => t.order)) + 1;
            document.getElementById('taskModal').style.display = 'block';
        }

        function editTask(id) {
            const task = taskData.find(t => t.id == id);
            if (!task) return;

            document.getElementById('modalTitle').textContent = 'Edit Task';
            document.getElementById('taskAction').value = 'update';
            document.getElementById('taskId').value = task.id;
            document.getElementById('taskName').value = task.title;
            document.getElementById('taskDescription').value = task.description;
            document.getElementById('taskOrder').value = task.order;
            document.getElementById('taskStatus').value = task.status;
            document.getElementById('taskModal').style.display = 'block';
        }

        function deleteTask(id) {
            const task = taskData.find(t => t.id == id);
            if (!task) return;

            if (confirm('Are you sure you want to delete "' + task.title + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function closeModal() {
            document.getElementById('taskModal').style.display = 'none';
        }

        function showLoading(buttonId) {
            const btn = document.getElementById(buttonId);
            const textSpan = btn.querySelector('.btn-text');
            const originalText = textSpan.textContent;
            textSpan.innerHTML = '<span class="loading">⟳</span> Processing...';
            btn.disabled = true;
            return originalText;
        }

        function hideLoading(buttonId, originalText) {
            const btn = document.getElementById(buttonId);
            const textSpan = btn.querySelector('.btn-text');
            textSpan.textContent = originalText;
            btn.disabled = false;
        }

        function showAnalysisPanel(content) {
            const panel = document.getElementById('analysisPanel');
            const contentDiv = document.getElementById('analysisContent');
            contentDiv.innerHTML = content;
            panel.style.display = 'block';
            panel.scrollIntoView({ behavior: 'smooth' });
        }

        // Enhanced AJAX request function with debugging
        function makeAjaxRequest(action, extraData = {}) {
            console.log('Making AJAX request:', { action, extraData });

            const postData = { action, ...extraData };
            console.log('POST data:', postData);

            return $.ajax({
                url: 'todo_prioritizer_ajax.php',
                method: 'POST',
                data: postData,
                dataType: 'json',
                timeout: 30000, // 30 second timeout
                beforeSend: function(xhr) {
                    console.log('AJAX request starting...');
                }
            }).done(function(response, textStatus, xhr) {
                console.log('AJAX success:', {
                    response,
                    textStatus,
                    status: xhr.status,
                    headers: xhr.getAllResponseHeaders()
                });
                return response;
            }).fail(function(xhr, textStatus, errorThrown) {
                console.error('AJAX failed:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    textStatus,
                    errorThrown,
                    headers: xhr.getAllResponseHeaders()
                });

                // Try to parse error response
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    console.error('Parsed error response:', errorResponse);
                } catch (e) {
                    console.error('Could not parse error response as JSON');
                }

                throw new Error(`AJAX failed: ${textStatus} - ${errorThrown}`);
            });
        }

        function testConnection() {
            console.log('Testing connection...');

            makeAjaxRequest('test_connection')
                .then(function(response) {
                    console.log('Connection test successful:', response);
                    alert('Connection test successful! ' + response.message);
                })
                .catch(function(error) {
                    console.error('Connection test failed:', error);
                    alert('Connection test failed: ' + error.message);
                });
        }

        // AI Functions - keeping only the main analyze function
        function analyzeTasks() {
            const originalText = showLoading('analyzeBtn');
            console.log('Starting task analysis...');

            makeAjaxRequest('analyze_tasks')
                .then(function(response) {
                    console.log('Task analysis response:', response);
                    hideLoading('analyzeBtn', originalText);

                    if (response.error) {
                        console.error('Analysis error:', response.error);
                        alert('Error: ' + response.error);
                        return;
                    }

                    let html = '<div class="suggestion-item">';
                    html += '<h5>AI Analysis:</h5>';
                    html += '<p>' + (response.analysis || 'Analysis completed') + '</p>';
                    html += '</div>';

                    if (response.suggestions && response.suggestions.length > 0) {
                        console.log('Found suggestions:', response.suggestions);
                        html += '<h5>Suggested Priority Changes:</h5>';
                        response.suggestions.forEach(function(suggestion) {
                            html += '<div class="suggestion-item">';
                            html += '<strong>Task ID ' + suggestion.id + '</strong><br>';
                            html += 'Current Order: ' + suggestion.current_order + ' → Suggested: ' + suggestion.new_order + '<br>';
                            html += '<em>' + suggestion.reason + '</em>';
                            html += '</div>';
                        });

                        html += '<button class="btn" onclick="applySuggestions(' + JSON.stringify(response.suggestions).replace(/"/g, '&quot;') + ')">Apply All Suggestions</button>';
                    } else {
                        console.log('No suggestions found');
                    }

                    showAnalysisPanel(html);
                })
                .catch(function(error) {
                    console.error('Analysis failed:', error);
                    hideLoading('analyzeBtn', originalText);
                    alert('Analysis failed: ' + error.message);
                });
        }

        function applySuggestions(suggestions) {
            if (!confirm('Apply all suggested priority changes? This will update your database.')) {
                return;
            }

            $.post('todo_prioritizer_ajax.php', {
                action: 'apply_suggestions',
                suggestions: JSON.stringify(suggestions)
            }, function(response) {
                if (response.error) {
                    alert('Error: ' + response.error);
                    return;
                }

                alert('Applied ' + response.applied + ' of ' + response.total + ' suggestions. Refreshing page...');
                location.reload();
            }, 'json').fail(function() {
                alert('Request failed. Please try again.');
            });
        }

        // Legacy AI functions using the original simple AJAX calls
        function findBlockingTasks() {
            const originalText = showLoading('blockingBtn');

            $.post('todo_prioritizer_ajax.php', {
                action: 'identify_blocking'
            }, function(response) {
                hideLoading('blockingBtn', originalText);

                if (response.error) {
                    alert('Error: ' + response.error);
                    return;
                }

                // Clear previous AI highlighting
                document.querySelectorAll('.task-card').forEach(card => {
                    card.classList.remove('ai-suggested');
                    const aiBadge = card.querySelector('.ai-badge');
                    if (aiBadge) aiBadge.remove();
                });

                let html = '<div class="suggestion-item blocking">';
                html += '<h5>Blocking Tasks Identified:</h5>';
                html += '<p>' + (response.reason || 'Analysis completed') + '</p>';

                if (response.blocking_tasks && response.blocking_tasks.length > 0) {
                    html += '<p>Blocking task IDs: ' + response.blocking_tasks.join(', ') + '</p>';

                    // Highlight blocking tasks
                    response.blocking_tasks.forEach(function(taskId) {
                        const card = document.querySelector('[data-id="' + taskId + '"]');
                        if (card) {
                            card.classList.add('ai-suggested');
                            const meta = card.querySelector('.task-meta');
                            const badge = document.createElement('span');
                            badge.className = 'task-badge ai-badge';
                            badge.textContent = 'BLOCKING';
                            meta.appendChild(badge);
                        }
                    });
                } else {
                    html += '<p>No blocking tasks identified.</p>';
                }

                html += '</div>';
                showAnalysisPanel(html);
            }, 'json').fail(function() {
                hideLoading('blockingBtn', originalText);
                alert('Request failed. Please try again.');
            });
        }

        function suggestNextTasks() {
            const originalText = showLoading('nextTasksBtn');

            $.post('todo_prioritizer_ajax.php', {
                action: 'suggest_next',
                count: 5
            }, function(response) {
                hideLoading('nextTasksBtn', originalText);

                if (response.error) {
                    alert('Error: ' + response.error);
                    return;
                }

                // Clear previous AI highlighting
                document.querySelectorAll('.task-card').forEach(card => {
                    card.classList.remove('ai-suggested');
                    const aiBadge = card.querySelector('.ai-badge');
                    if (aiBadge) aiBadge.remove();
                });

                let html = '<div class="suggestion-item">';
                html += '<h5>AI Recommended Next Tasks:</h5>';

                if (response.suggested_tasks && response.suggested_tasks.length > 0) {
                    response.suggested_tasks.forEach(function(task) {
                        html += '<div class="suggestion-item">';
                        html += '<strong>' + task.title + '</strong><br>';
                        html += '<em>' + task.reason + '</em>';
                        html += '</div>';

                        // Highlight suggested tasks
                        const card = document.querySelector('[data-id="' + task.id + '"]');
                        if (card) {
                            card.classList.add('ai-suggested');
                            const meta = card.querySelector('.task-meta');
                            const badge = document.createElement('span');
                            badge.className = 'task-badge ai-badge';
                            badge.textContent = 'SUGGESTED';
                            meta.appendChild(badge);
                        }
                    });
                } else {
                    html += '<p>No task suggestions available.</p>';
                }

                html += '</div>';
                showAnalysisPanel(html);
            }, 'json').fail(function() {
                hideLoading('nextTasksBtn', originalText);
                alert('Request failed. Please try again.');
            });
        }

        function showImmediate() {
            const originalText = showLoading('immediateBtn');

            $.post('todo_prioritizer_ajax.php', {
                action: 'get_immediate'
            }, function(response) {
                hideLoading('immediateBtn', originalText);

                if (response.error) {
                    alert('Error: ' + response.error);
                    return;
                }

                let html = '<div class="suggestion-item">';
                html += '<h5>Critical Tasks (Order 1-10):</h5>';

                if (response.tasks && response.tasks.length > 0) {
                    response.tasks.forEach(function(task) {
                        html += '<div class="suggestion-item">';
                        html += '<strong>Order ' + task.order + ': ' + task.name + '</strong><br>';
                        if (task.description) {
                            html += '<em>' + task.description.substring(0, 100) + '...</em>';
                        }
                        html += '</div>';
                    });
                } else {
                    html += '<p>No critical tasks found.</p>';
                }

                html += '</div>';
                showAnalysisPanel(html);
            }, 'json').fail(function() {
                hideLoading('immediateBtn', originalText);
                alert('Request failed. Please try again.');
            });
        }

        // Setup filters
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const filterType = btn.dataset.filter;
                const filterValue = btn.dataset.value;

                // Update active state
                document.querySelectorAll(`[data-filter="${filterType}"]`).forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                // Update filters
                currentFilters[filterType] = filterValue;
                filterTasks();
            });
        });

        // Setup search
        document.getElementById('searchBox').addEventListener('input', (e) => {
            searchQuery = e.target.value;
            filterTasks();
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('taskModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        // Add debugging and connection test functionality
        console.log('Debug JavaScript loaded successfully');

        // Test basic functionality on page load
        $(document).ready(function() {
            console.log('Page ready, task management system initialized');
            console.log('Available tasks:', taskData.length);
            // Uncomment the next line to test connection immediately on page load
            // testConnection();
        });
</script>


</body>
</html>

