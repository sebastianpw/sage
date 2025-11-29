<?php
// enhanced_todo_with_crud.php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $name = trim($_POST['name']);
                $description = trim($_POST['description'] ?? '');
                $order = (int)($_POST['order'] ?? 100);
                
                if (empty($name)) {
                    throw new Exception('Task name is required');
                }
                
                $stmt = $pdoSys->prepare("INSERT INTO sage_todos (name, description, `order`, status, created_at, updated_at) VALUES (?, ?, ?, 'pending', NOW(), NOW())");
                $stmt->execute([$name, $description, $order]);
                
                $newId = $pdoSys->lastInsertId();
                
                // Fetch the newly created task
                $stmt = $pdoSys->prepare("SELECT * FROM sage_todos WHERE id = ?");
                $stmt->execute([$newId]);
                $newTask = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Task created successfully',
                    'task' => $newTask
                ]);
                exit;
                
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
                
                // Fetch the updated task
                $stmt = $pdoSys->prepare("SELECT * FROM sage_todos WHERE id = ?");
                $stmt->execute([$id]);
                $updatedTask = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Task updated successfully',
                    'task' => $updatedTask
                ]);
                exit;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                $stmt = $pdoSys->prepare("DELETE FROM sage_todos WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Task deleted successfully',
                    'id' => $id
                ]);
                exit;
                
            case 'bulk_reorder':
                $orders = json_decode($_POST['orders'], true);
                if ($orders) {
                    $pdoSys->beginTransaction();
                    foreach ($orders as $item) {
                        $stmt = $pdoSys->prepare("UPDATE sage_todos SET `order` = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$item['order'], $item['id']]);
                    }
                    $pdoSys->commit();
                    echo json_encode(['success' => true, 'message' => 'Order saved successfully']);
                    exit;
                }
                break;
                
            case 'get_stats':
                $stmt = $pdoSys->query("SELECT * FROM sage_todos ORDER BY `order` ASC");
                $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stats = ['immediate' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
                foreach ($todos as $todo) {
                    $priority = getPriorityLevel($todo['order']);
                    $stats[$priority]++;
                }
                $stats['total'] = count($todos);
                
                echo json_encode([
                    'success' => true,
                    'stats' => $stats
                ]);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
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


<?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<?php else: ?>
    <script src="/vendor/sortable/Sortable.min.js"></script>
<?php endif; ?>

    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/todo.css">
    
    <style>
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: #4CAF50;
            color: white;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 10000;
            animation: slideIn 0.3s ease-out;
        }
        
        .toast-notification.error {
            background: #f44336;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .task-card.updating {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body>
<?php 
//require "floatool.php"; 
echo $eruda; 
?>

    <div class="dashboard">



<div class="header" style="margin-bottom: 0;">
    <h2 style="padding-bottom: 5px;">🎫 SAGE TODOs</h2>
    
    <!-- ADD THIS THEME TOGGLE BUTTON -->
    <button id="themeToggle" class="btn" title="Toggle Theme" style="margin-left: auto;">🌙</button>

    <select id="modelSelect" class="form-control">
        <option value="">Loading models...</option>
    </select>
    <button class="btn btn-sm" id="analyzeBtn" onclick="analyzeTasks()">
        <span class="btn-text">🧠 AI</span>
    </button>
            <button class="btn btn-sm" onclick="openCreateModal()">
                <span class="btn-text">➕ New</span>
            </button>
            <button class="btn btn-sm" onclick="toggleSortMode()">
                <span class="btn-text" id="sortModeText">🔄 +Drag</span>
            </button>
            <button class="btn btn-sm" onclick="saveSortOrder()">
                <span class="btn-text">💾 Save</span>
            </button>
        </div>

        <div class="ai-controls" style="display: none;">
            <h3>🤖 AI-Powered Task Management</h3>
            <button class="btn" id="analyzeBtn" onclick="analyzeTasks()">
                <span class="btn-text">🧠 Analyze All Tasks</span>
            </button>
        </div>

        <div id="analysisPanel" class="analysis-panel">
            <h4>AI Analysis Results</h4>
            <div id="analysisContent"></div>
        </div>

        <div class="filters" style="font-size: 0.5em;">
            <input style="margin-bottom: 10px;" type="text" class="form-control" id="searchBox" placeholder="🔍 Search TODOs...">
            
            <div class="filter-group">
                <label>Priority:</label>
                <button class="filter-btn active" data-filter="priority" data-value="all">All</button>
                <button class="filter-btn" data-filter="priority" data-value="immediate">Immediate</button>
                <button class="filter-btn" data-filter="priority" data-value="high">High</button>
                <button class="filter-btn" data-filter="priority" data-value="medium">Medium</button>
                <button class="filter-btn" data-filter="priority" data-value="low">Low</button>
            </div>
            
            <div class="filter-group" style="display:none;">
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

        <div class="last-updated" id="lastUpdated">
            Last updated: <?= date('Y-m-d H:i:s') ?>
        </div>
        
        <div class="stats-grid" id="statsGrid">
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
            <form id="taskForm">
                <input type="hidden" id="taskId" name="id">
                <input type="hidden" id="taskAction" name="action" value="create">
                
                <div class="form-group">
                    <label for="taskName">Task Name *</label>
                    <input type="text" id="taskName" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="taskDescription">Description</label>
                    <textarea id="taskDescription" name="description"class="form-control"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="taskOrder">Priority Order</label>
                    <input type="number" id="taskOrder" name="order" value="100" min="1"class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="taskStatus">Status</label>
                    <select id="taskStatus" name="status"class="form-control">
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
let taskData = <?= json_encode($processedTodos) ?>;

// Toast notification function
function showToast(message, isError = false) {
    const toast = document.createElement('div');
    toast.className = 'toast-notification' + (isError ? ' error' : '');
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease-out reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Update last updated timestamp
function updateLastUpdated() {
    const now = new Date();
    const formatted = now.getFullYear() + '-' + 
        String(now.getMonth() + 1).padStart(2, '0') + '-' + 
        String(now.getDate()).padStart(2, '0') + ' ' + 
        String(now.getHours()).padStart(2, '0') + ':' + 
        String(now.getMinutes()).padStart(2, '0') + ':' + 
        String(now.getSeconds()).padStart(2, '0');
    document.getElementById('lastUpdated').textContent = 'Last updated: ' + formatted;
}

// Update statistics
function updateStats() {
    $.ajax({
        url: '',
        method: 'POST',
        data: { action: 'get_stats' },
        dataType: 'json',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function(response) {
            if (response.success) {
                const stats = response.stats;
                const statsHtml = `
                    <div class="stat-card">
                        <div class="stat-number immediate">${stats.immediate}</div>
                        <div class="stat-label">Immediate (1-10)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number high">${stats.high}</div>
                        <div class="stat-label">High (11-25)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number medium">${stats.medium}</div>
                        <div class="stat-label">Medium (26-50)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number low">${stats.low}</div>
                        <div class="stat-label">Low (51+)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">${stats.total}</div>
                        <div class="stat-label">Total Tasks</div>
                    </div>
                `;
                document.getElementById('statsGrid').innerHTML = statsHtml;
            }
        }
    });
}

// Helper functions for priority and area
function getPriorityLevel(order) {
    if (order <= 10) return 'immediate';
    if (order <= 25) return 'high';
    if (order <= 50) return 'medium';
    return 'low';
}

function guessArea(name, description = '') {
    const text = (name + ' ' + description).toLowerCase();
    
    if (text.includes('ui') || text.includes('interface') || text.includes('button') || text.includes('menu')) return 'UI';
    if (text.includes('gallery')) return 'Gallery';
    if (text.includes('generate') || text.includes('generation') || text.includes('prompt') || text.includes('seed')) return 'Generation';
    if (text.includes('model') || text.includes('img2img') || text.includes('sd')) return 'Models';
    if (text.includes('pose') || text.includes('skeleton') || text.includes('openpose')) return 'Pose/Skeleton';
    if (text.includes('database') || text.includes('sql') || text.includes('table') || text.includes('crud')) return 'DB';
    if (text.includes('scheduler') || text.includes('script') || text.includes('shell')) return 'Scheduler';
    if (text.includes('gear')) return 'GearMenu';
    if (text.includes('workflow')) return 'Workflow';
    if (text.includes('bug') || text.includes('fix') || text.includes('error')) return 'Bugs';
    if (text.includes('character') || text.includes('logo') || text.includes('theatrical')) return 'Assets';
    if (text.includes('langchain') || text.includes('3d') || text.includes('sketchfab')) return 'Integrations';
    
    return 'General';
}

// Create task card HTML
function createTaskCardHTML(task) {
    const priority = getPriorityLevel(task.order);
    const area = guessArea(task.title, task.description);
    const descriptionTruncated = task.description.length > 200 ? 
        task.description.substring(0, 200) + '...' : task.description;
    
    return `
        <div class="task-card ${priority}" 
             data-id="${task.id}"
             data-priority="${priority}" 
             data-area="${area}"
             data-order="${task.order}"
             data-search="${(task.title + ' ' + task.description).toLowerCase()}">
            
            <div class="task-header">
                <div class="task-title">
                    <span class="drag-handle">⋮⋮</span>
                    ${escapeHtml(task.title)}
                    ${task.regenerate_images > 0 ? '<span class="regen-indicator">REGEN</span>' : ''}
                </div>
                <div class="task-actions">
                    <button class="btn secondary" onclick="editTask(${task.id})">✏️</button>
                    <button class="btn" onclick="deleteTask(${task.id})">🗑️</button>
                </div>
            </div>
            
            ${task.description ? `
                <div class="task-description">
                    ${escapeHtml(descriptionTruncated)}
                </div>
            ` : ''}
            
            <div class="task-meta">
                <span class="task-badge priority-${priority}">${priority.charAt(0).toUpperCase() + priority.slice(1)}</span>
                <span class="task-badge area-badge">${area}</span>
                <span class="task-badge order-badge">Order: ${task.order}</span>
            </div>
        </div>
    `;
}

// HTML escape function
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Insert task in correct order position
function insertTaskInOrder(task) {
    const taskGrid = document.getElementById('taskGrid');
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = createTaskCardHTML(task);
    const newCard = tempDiv.firstElementChild;
    
    // Get all current cards
    const allCards = Array.from(taskGrid.querySelectorAll('.task-card'));
    
    // If grid is empty, just append
    if (allCards.length === 0) {
        taskGrid.appendChild(newCard);
        filterTasks();
        return;
    }
    
    // Find the correct position based on order value
    let inserted = false;
    for (let i = 0; i < allCards.length; i++) {
        const cardOrder = parseInt(allCards[i].dataset.order);
        if (task.order < cardOrder) {
            taskGrid.insertBefore(newCard, allCards[i]);
            inserted = true;
            break;
        }
    }
    
    // If not inserted yet, append at the end
    if (!inserted) {
        taskGrid.appendChild(newCard);
    }
    
    // Apply filters to show/hide appropriately
    filterTasks();
}

// Filter tasks
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

// Toggle sort mode
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

// Update order after drag
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

// Save sort order
function saveSortOrder() {
    if (!sortMode) {
        showToast('Enable drag & drop mode first to reorder tasks', true);
        return;
    }

    const allCards = Array.from(document.querySelectorAll('.task-card'));
    const visibleCards = allCards.filter(card => card.style.display !== 'none');

    const orders = visibleCards.map((card, index) => ({
        id: parseInt(card.dataset.id),
        order: index + 1
    }));

    $.ajax({
        url: '',
        method: 'POST',
        data: {
            action: 'bulk_reorder',
            orders: JSON.stringify(orders)
        },
        dataType: 'json',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function(response) {
            if (response.success) {
                showToast('Task order saved successfully!');
                updateLastUpdated();
                updateStats();
                
                // Update taskData array
                orders.forEach(orderItem => {
                    const task = taskData.find(t => t.id === orderItem.id);
                    if (task) {
                        task.order = orderItem.order;
                    }
                });
            } else {
                showToast('Failed to save order: ' + (response.error || 'Unknown error'), true);
            }
        },
        error: function(xhr) {
            console.error('Save order failed:', xhr.responseText);
            showToast('Failed to save order. Check console for details.', true);
        }
    });
}

// Open create modal
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Add New Task';
    document.getElementById('taskAction').value = 'create';
    document.getElementById('taskForm').reset();
    document.getElementById('taskId').value = '';
    document.getElementById('taskOrder').value = Math.max(...taskData.map(t => t.order), 0) + 1;
    document.getElementById('taskModal').style.display = 'block';
}

// Edit task
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

// Delete task
function deleteTask(id) {
    const task = taskData.find(t => t.id == id);
    if (!task) return;

    if (confirm('Are you sure you want to delete "' + task.title + '"?')) {
        const card = document.querySelector(`[data-id="${id}"]`);
        if (card) card.classList.add('updating');
        
        $.ajax({
            url: '',
            method: 'POST',
            data: {
                action: 'delete',
                id: id
            },
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(response) {
                if (response.success) {
                    // Remove from DOM
                    if (card) card.remove();
                    
                    // Remove from taskData
                    taskData = taskData.filter(t => t.id !== id);
                    
                    showToast('Task deleted successfully');
                    updateLastUpdated();
                    updateStats();
                } else {
                    if (card) card.classList.remove('updating');
                    showToast('Failed to delete task: ' + (response.error || 'Unknown error'), true);
                }
            },
            error: function(xhr) {
                if (card) card.classList.remove('updating');
                console.error('Delete failed:', xhr.responseText);
                showToast('Failed to delete task. Check console for details.', true);
            }
        });
    }
}

// Close modal
function closeModal() {
    document.getElementById('taskModal').style.display = 'none';
}

// Handle form submission
document.getElementById('taskForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {};
    formData.forEach((value, key) => data[key] = value);
    
    const isCreate = data.action === 'create';
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving...';
    
    $.ajax({
        url: '',
        method: 'POST',
        data: data,
        dataType: 'json',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function(response) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Task';
            
            if (response.success) {
                closeModal();
                showToast(response.message);
                updateLastUpdated();
                
                const task = response.task;
                const processedTask = {
                    id: task.id,
                    title: task.name,
                    description: task.description || '',
                    priority: getPriorityLevel(task.order),
                    area: guessArea(task.name, task.description),
                    order: task.order,
                    status: task.status,
                    created_at: task.created_at,
                    regenerate_images: task.regenerate_images || 0
                };
                
                if (isCreate) {
                    // Add new task to DOM in correct position
                    taskData.push(processedTask);
                    insertTaskInOrder(processedTask);
                } else {
                    // Update taskData first
                    const index = taskData.findIndex(t => t.id === task.id);
                    if (index !== -1) {
                        taskData[index] = processedTask;
                    }
                    
                    // Remove old card
                    const oldCard = document.querySelector(`[data-id="${task.id}"]`);
                    if (oldCard) {
                        oldCard.remove();
                    }
                    
                    // Insert updated card in correct position
                    insertTaskInOrder(processedTask);
                }
                
                updateStats();
                filterTasks();
            } else {
                showToast('Failed to save task: ' + (response.error || 'Unknown error'), true);
            }
        },
        error: function(xhr) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Task';
            console.error('Save failed:', xhr.responseText);
            showToast('Failed to save task. Check console for details.', true);
        }
    });
});

// Show loading
function showLoading(buttonId) {
    const btn = document.getElementById(buttonId);
    const textSpan = btn.querySelector('.btn-text');
    const originalText = textSpan.textContent;
    textSpan.innerHTML = '<span class="loading">⟳</span> ...';
    btn.disabled = true;
    return originalText;
}

// Hide loading
function hideLoading(buttonId, originalText) {
    const btn = document.getElementById(buttonId);
    const textSpan = btn.querySelector('.btn-text');
    textSpan.textContent = originalText;
    btn.disabled = false;
}

// Show analysis panel
function showAnalysisPanel(content) {
    const panel = document.getElementById('analysisPanel');
    const contentDiv = document.getElementById('analysisContent');
    contentDiv.innerHTML = content;
    panel.style.display = 'block';
    panel.scrollIntoView({ behavior: 'smooth' });
}

// Make AJAX request
function makeAjaxRequest(action, extraData = {}) {
    console.log('Making AJAX request:', { action, extraData });

    const postData = { action, ...extraData };

    return $.ajax({
        url: 'todo_prioritizer_ajax.php',
        method: 'POST',
        data: postData,
        dataType: 'json',
        timeout: 300000
    });
}

// Analyze tasks
function analyzeTasks() {
    const originalText = showLoading('analyzeBtn');
    const selectedModel = document.getElementById('modelSelect').value;
    
    makeAjaxRequest('analyze_tasks', { model: selectedModel })
        .then(function(response) {
            hideLoading('analyzeBtn', originalText);

            if (response.error) {
                showToast('Error: ' + response.error, true);
                return;
            }

            let html = '<div class="suggestion-item">';
            html += '<h5>AI Analysis:</h5>';
            html += '<p>' + (response.analysis || 'Analysis completed') + '</p>';
            html += '</div>';

            if (response.suggestions && response.suggestions.length > 0) {
                html += '<h5>Suggested Priority Changes:</h5>';
                html += '<div class="suggestion-item">';
                html += '<label><input type="checkbox" id="checkAllSuggestions" onchange="toggleAllSuggestions(this.checked)"> <strong>Check All</strong></label>';
                html += '</div>';
                
                response.suggestions.forEach(function(suggestion, index) {
                    // Store suggestion in a global array instead of in the DOM
                    if (!window.aiSuggestions) window.aiSuggestions = [];
                    window.aiSuggestions[index] = suggestion;
                    
                    html += '<div class="suggestion-item">';
                    html += '<label>';
                    html += '<input type="checkbox" class="suggestion-checkbox" data-suggestion-index="' + index + '" checked> ';
                    html += '<strong>Task ID ' + suggestion.id + '</strong><br>';
                    html += '</label>';
                    html += 'Current Order: ' + suggestion.current_order + ' → Suggested: ' + suggestion.new_order + '<br>';
                    html += '<em>' + suggestion.reason + '</em>';
                    html += '</div>';
                });

                html += '<button class="btn" onclick="applySelectedSuggestions()">Apply Selected Suggestions</button>';
            }

            showAnalysisPanel(html);
        })
        .catch(function(error) {
            hideLoading('analyzeBtn', originalText);
            showToast('Analysis failed: ' + error.message, true);
        });
}

// Toggle all suggestions
function toggleAllSuggestions(checked) {
    document.querySelectorAll('.suggestion-checkbox').forEach(function(checkbox) {
        checkbox.checked = checked;
    });
}

// Apply selected suggestions
function applySelectedSuggestions() {
    const selectedSuggestions = [];
    document.querySelectorAll('.suggestion-checkbox:checked').forEach(function(checkbox) {
        const index = parseInt(checkbox.dataset.suggestionIndex);
        if (window.aiSuggestions && window.aiSuggestions[index]) {
            selectedSuggestions.push(window.aiSuggestions[index]);
        }
    });
    
    if (selectedSuggestions.length === 0) {
        showToast('Please select at least one suggestion to apply.', true);
        return;
    }
    
    if (!confirm('Apply ' + selectedSuggestions.length + ' selected priority changes? This will update your database.')) {
        return;
    }

    $.post('todo_prioritizer_ajax.php', {
        action: 'apply_suggestions',
        suggestions: JSON.stringify(selectedSuggestions)
    }, function(response) {
        if (response.error) {
            showToast('Error: ' + response.error, true);
            return;
        }

        showToast('Applied ' + response.applied + ' of ' + response.total + ' suggestions. Reloading...');
        setTimeout(() => location.reload(), 1500);
    }, 'json').fail(function() {
        showToast('Request failed. Please try again.', true);
    });
}

// Load models
function loadModels() {
    makeAjaxRequest('get_models')
        .then(function(response) {
            const select = document.getElementById('modelSelect');
            select.innerHTML = '';
            
            if (response.models) {
                Object.keys(response.models).forEach(function(groupName) {
                    const optgroup = document.createElement('optgroup');
                    optgroup.label = groupName;
                    
                    response.models[groupName].forEach(function(model) {
                        const option = document.createElement('option');
                        option.value = model.id;
                        option.textContent = model.name;
                        
                        if (model.id === response.default_model) {
                            option.selected = true;
                        }
                        
                        optgroup.appendChild(option);
                    });
                    
                    select.appendChild(optgroup);
                });
            }
        })
        .catch(function(error) {
            console.error('Failed to load models:', error);
            document.getElementById('modelSelect').innerHTML = '<option value="">Error loading models</option>';
        });
}

// Setup filters
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const filterType = btn.dataset.filter;
        const filterValue = btn.dataset.value;

        document.querySelectorAll(`[data-filter="${filterType}"]`).forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

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

// Initialize on page load
$(document).ready(function() {
    console.log('Page ready, task management system initialized');
    console.log('Available tasks:', taskData.length);
    loadModels();
});
</script>


<script>
window.addEventListener('DOMContentLoaded', () => {
    const preloadId = <?= (int)($_GET['id'] ?? 0) ?>;
    if (preloadId > 0) {
        editTask(preloadId);
    }
});
</script>


<script src="/js/theme-manager.js"></script>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

</body>
</html>
