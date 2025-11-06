<?php
// todo_prioritizer_ajax.php (Updated - Uses centralized AIProvider)

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// Add debug logging
$fileLogger->info(['AJAX Request received' => [
    'post_data' => $_POST,
    'session_user_id' => $_SESSION['user_id'] ?? 'not set',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
]]);

if (!isset($_SESSION['user_id'])) {
    $error = ['error' => 'Not logged in'];
    $fileLogger->error(['Authentication failed' => $error]);
    echo json_encode($error);
    exit;
}

use App\Core\TodoPrioritizer;
use App\Core\AIProvider;

try {
    $fileLogger->info(['Initializing TodoPrioritizer with AIProvider' => []]);
    
    // Get centralized AIProvider from SpwBase
    $aiProvider = $spw->getAIProvider();
    
    // Initialize the task prioritizer with AIProvider
    $taskPrioritizer = new TodoPrioritizer($aiProvider, $fileLogger);
    $fileLogger->info(['TodoPrioritizer initialized successfully' => []]);
    
    $action = $_POST['action'] ?? '';
    $fileLogger->info(['Processing action' => ['action' => $action]]);
    
    switch ($action) {
        case 'get_models':
            $fileLogger->info(['Getting available models' => []]);
            $result = [
                'models' => AIProvider::getModelCatalog(),
                'default_model' => AIProvider::getDefaultModel()
            ];
            break;
        case 'analyze_tasks':
            $model = $_POST['model'] ?? null;
            $fileLogger->info(['Starting task analysis' => ['model' => $model]]);
            $result = $taskPrioritizer->analyzeTasks($model);
            $fileLogger->info(['Task analysis completed' => ['result_keys' => array_keys($result)]]);
            break;
            
        case 'apply_suggestions':
            $suggestions = json_decode($_POST['suggestions'] ?? '[]', true);
            $fileLogger->info(['Applying suggestions' => ['suggestion_count' => count($suggestions)]]);
            $result = $taskPrioritizer->applyPriorityChanges($suggestions);
            break;
            
        case 'get_immediate':
            $fileLogger->info(['Getting immediate tasks' => []]);
            $result = [
                'tasks' => $taskPrioritizer->getImmediateTasks()
            ];
            $fileLogger->info(['Immediate tasks retrieved' => ['task_count' => count($result['tasks'])]]);
            break;
            
        case 'identify_blocking':
            $fileLogger->info(['Identifying blocking tasks' => []]);
            $result = $taskPrioritizer->identifyBlockingTasks();
            break;
            
        case 'suggest_next':
            $count = (int)($_POST['count'] ?? 5);
            $fileLogger->info(['Suggesting next tasks' => ['count' => $count]]);
            $result = $taskPrioritizer->suggestNextTasks($count);
            break;
            
        case 'test_connection':
            $fileLogger->info(['Testing basic connection' => []]);
            $result = [
                'status' => 'success',
                'message' => 'Connection working with centralized AIProvider',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            break;
            
        default:
            $result = ['error' => 'Unknown action: ' . $action];
            $fileLogger->warning(['Unknown action requested' => ['action' => $action]]);
    }
    
    $fileLogger->info(['Action completed successfully' => [
        'action' => $action,
        'result_status' => isset($result['error']) ? 'error' : 'success'
    ]]);
    
} catch (Exception $e) {
    $result = [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ];
    $fileLogger->error(['Exception in AJAX handler' => [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]]);
} catch (Error $e) {
    $result = [
        'error' => 'Fatal error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ];
    $fileLogger->error(['Fatal error in AJAX handler' => [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]]);
}

$fileLogger->info(['Sending JSON response' => ['response_keys' => array_keys($result)]]);

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);
exit;
