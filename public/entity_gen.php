<?php
// entity_gen.php - Universal Entity CRUD with AI Generator (Theme-Aware)
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;

define('ID_NAME_GEN', '9bf6de291765e2ced28589de857a9f0b');
define('ID_DESC_GEN', '446437576e785bbf3d188624dd9794eb');

$em = $spw->getEntityManager();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    die('Not authenticated');
}

$entityType = $_GET['entity_type'] ?? $_POST['entity_type'] ?? null;
$entityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : null;
$isEdit = ($entityId > 0);

if (!$entityType) {
    die('Missing entity_type parameter');
}

$allowedTables = ['characters', 'animas', 'locations', 'artifacts', 'vehicles', 'backgrounds',
                  'sketches', 'generatives', 'blueprints', 'composites'];
if (!in_array($entityType, $allowedTables)) {
    die('Invalid entity type');
}

$errors = [];
$success = null;
$entity = null;
$previewImage = null;
$sketchTemplates = [];
$structuredInteractions = []; 

if ($entityType === 'sketches') {
    $conn = $em->getConnection();
    $templatesStmt = $conn->prepare("SELECT * FROM sketch_templates WHERE entity_type = 'sketches' AND active = 1 ORDER BY name");
    $templatesResult = $templatesStmt->executeQuery();
    $sketchTemplates = $templatesResult->fetchAllAssociative();

    // Fetch and structure interactions data for the UI
    $interactionsStmt = $conn->prepare("SELECT id, name, description, interaction_group, category, example_prompt FROM interactions WHERE active = 1 ORDER BY interaction_group, category, name");
    $interactionsResult = $interactionsStmt->executeQuery();
    $interactionsData = $interactionsResult->fetchAllAssociative();

    foreach ($interactionsData as $interaction) {
        $group = $interaction['interaction_group'];
        $category = !empty($interaction['category']) ? $interaction['category'] : 'General'; 
        if (!isset($structuredInteractions[$group])) {
            $structuredInteractions[$group] = [];
        }
        if (!isset($structuredInteractions[$group][$category])) {
            $structuredInteractions[$group][$category] = [];
        }
        $structuredInteractions[$group][$category][] = [
            'id' => $interaction['id'],
            'name' => $interaction['name'],
            'description' => $interaction['description'],
            'prompt' => $interaction['example_prompt']
        ];
    }
}

if ($isEdit) {
    $conn = $em->getConnection();
    $stmt = $conn->prepare("SELECT * FROM {$entityType} WHERE id = ?");
    $stmt->bindValue(1, $entityId);
    $result = $stmt->executeQuery();
    $entity = $result->fetchAssociative();

    if (!$entity) {
        die('Entity not found');
    }

    if (!empty($entity['img2img_frame_id'])) {
        $frameStmt = $conn->prepare("SELECT filename FROM frames WHERE id = ?");
        $frameStmt->bindValue(1, $entity['img2img_frame_id']);
        $frameResult = $frameStmt->executeQuery();
        $frame = $frameResult->fetchAssociative();
        if ($frame) {
            $previewImage = '/frames/' . $frame['filename'];
        }
    }
}

function is_json_request(): bool {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return (stripos($contentType, 'application/json') !== false)
        || (stripos($accept, 'application/json') !== false)
        || (strtolower($xhr) === 'xmlhttprequest');
}

/**
 * Helper to handle Generator Revisions
 */
function ensure_generator_revision($conn, $configId) {
    if (!$configId) return null;
    
    // 1. Fetch current config
    $stmt = $conn->prepare("SELECT * FROM generator_config WHERE config_id = ?");
    $stmt->bindValue(1, $configId);
    $res = $stmt->executeQuery();
    $row = $res->fetchAssociative();
    
    if (!$row) return null; // Config might have been deleted

    // 2. Prepare Snapshot Data
    $snapshot = [
        'system_role' => $row['system_role'],
        'instructions' => $row['instructions'],
        'parameters' => $row['parameters'],
        'output_schema' => $row['output_schema'],
        'oracle_config' => $row['oracle_config'],
        'model' => $row['model']
    ];
    $jsonSnapshot = json_encode($snapshot);
    $hash = md5($jsonSnapshot);

    // 3. Check History
    $hStmt = $conn->prepare("SELECT id FROM generator_config_history WHERE generator_config_id = ? AND config_hash = ?");
    $hStmt->bindValue(1, $row['id']);
    $hStmt->bindValue(2, $hash);
    $hResult = $hStmt->executeQuery();
    $existing = $hResult->fetchOne();

    if ($existing) {
        return ['db_id' => $row['id'], 'history_id' => $existing];
    }

    // 4. Insert New Revision
    $iStmt = $conn->prepare("INSERT INTO generator_config_history (generator_config_id, config_hash, snapshot_data, created_at) VALUES (?, ?, ?, NOW())");
    $iStmt->bindValue(1, $row['id']);
    $iStmt->bindValue(2, $hash);
    $iStmt->bindValue(3, $jsonSnapshot);
    $iStmt->executeStatement();

    return ['db_id' => $row['id'], 'history_id' => $conn->lastInsertId()];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = null;
    if (is_json_request()) {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true) ?: [];
    } else {
        $payload = $_POST;
    }

    $action = $payload['action'] ?? null;

    if ($action === 'save') {
        $postEntityId = isset($payload['entity_id']) && (int)$payload['entity_id'] > 0 ? (int)$payload['entity_id'] : null;
        $isPostEdit = ($postEntityId !== null);

        $name = trim($payload['name'] ?? '');
        $description = trim($payload['description'] ?? '');
        $order = (int)($payload['order'] ?? 0);
        $regenerateImages = isset($payload['regenerate_images']) && ($payload['regenerate_images'] == 1 || $payload['regenerate_images'] === true) ? 1 : 0;
        $img2img = isset($payload['img2img']) && ($payload['img2img'] == 1 || $payload['img2img'] === true) ? 1 : 0;
        $cnmap = isset($payload['cnmap']) && ($payload['cnmap'] == 1 || $payload['cnmap'] === true) ? 1 : 0;

        // Meta Capture
        $metaNameGenId = $payload['meta_gen_name_id'] ?? null;
        $metaDescGenId = $payload['meta_gen_desc_id'] ?? null;
        $metaTplId     = !empty($payload['meta_template_id']) ? (int)$payload['meta_template_id'] : null;
        $metaIntId     = !empty($payload['meta_interaction_id']) ? (int)$payload['meta_interaction_id'] : null;

        if (empty($name)) {
            $errors[] = 'Name is required';
        }

        if (empty($errors)) {
            $conn = $em->getConnection();

            if ($isPostEdit) {
                // Update Logic
                $sql = "UPDATE {$entityType} SET 
                        name = ?, 
                        description = ?, 
                        `order` = ?, 
                        regenerate_images = ?,
                        img2img = ?,
                        cnmap = ?,
                        updated_at = NOW()
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(1, $name);
                $stmt->bindValue(2, $description);
                $stmt->bindValue(3, $order);
                $stmt->bindValue(4, $regenerateImages);
                $stmt->bindValue(5, $img2img);
                $stmt->bindValue(6, $cnmap);
                $stmt->bindValue(7, $postEntityId);
                $stmt->executeStatement();
                
                // Note: We generally don't create meta entries on edits of existing rows
                // unless we want to track every save. 
                // The prompt says "create a new table... which shall have a single row for every new sketches row from now on".
                // So we skip meta logic for updates.

                if (is_json_request()) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'ok' => true,
                        'created' => false,
                        'id' => $postEntityId,
                        'message' => 'Entity updated successfully'
                    ]);
                    exit;
                } else {
                    $success = 'Entity updated successfully';
                    $stmt = $conn->prepare("SELECT * FROM {$entityType} WHERE id = ?");
                    $stmt->bindValue(1, $postEntityId);
                    $result = $stmt->executeQuery();
                    $entity = $result->fetchAssociative();
                }
            } else {
                // Create Logic
                $sql = "INSERT INTO {$entityType} 
                        (name, description, `order`, regenerate_images, img2img, cnmap, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(1, $name);
                $stmt->bindValue(2, $description);
                $stmt->bindValue(3, $order);
                $stmt->bindValue(4, $regenerateImages);
                $stmt->bindValue(5, $img2img);
                $stmt->bindValue(6, $cnmap);
                $stmt->executeStatement();

                $newId = (int)$conn->lastInsertId();

                // --- META SKETCHES LOGIC ---
                if ($entityType === 'sketches') {
                    try {
                        $nameRev = ensure_generator_revision($conn, $metaNameGenId);
                        $descRev = ensure_generator_revision($conn, $metaDescGenId);

                        $metaSql = "INSERT INTO meta_sketches 
                            (sketch_id, desc_gen_config_id, desc_gen_history_id, name_gen_config_id, name_gen_history_id, sketch_template_id, interaction_id, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                        
                        $mStmt = $conn->prepare($metaSql);
                        $mStmt->bindValue(1, $newId);
                        $mStmt->bindValue(2, $descRev ? $descRev['db_id'] : null);
                        $mStmt->bindValue(3, $descRev ? $descRev['history_id'] : null);
                        $mStmt->bindValue(4, $nameRev ? $nameRev['db_id'] : null);
                        $mStmt->bindValue(5, $nameRev ? $nameRev['history_id'] : null);
                        $mStmt->bindValue(6, $metaTplId);
                        $mStmt->bindValue(7, $metaIntId);
                        $mStmt->executeStatement();

                    } catch (\Exception $e) {
                        // Silently fail on meta insert to not block the main flow, 
                        // or log error if logger available. 
                        // error_log("Meta Insert Error: " . $e->getMessage());
                    }
                }
                // ---------------------------

                if (is_json_request()) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'ok' => true,
                        'created' => true,
                        'id' => $newId,
                        'message' => 'Entity created successfully'
                    ]);
                    exit;
                } else {
                    $entityId = $newId;
                    $isEdit = true;
                    $success = 'Entity created successfully';
                    $stmt = $conn->prepare("SELECT * FROM {$entityType} WHERE id = ?");
                    $stmt->bindValue(1, $entityId);
                    $result = $stmt->executeQuery();
                    $entity = $result->fetchAssociative();
                }
            }
        } else {
            if (is_json_request()) {
                header('Content-Type: application/json', true, 400);
                echo json_encode([
                    'ok' => false,
                    'errors' => $errors
                ]);
                exit;
            }
        }
    }
}

$repo = $em->getRepository(App\Entity\GeneratorConfig::class);
$generators = [];

if ($userId) {
    $qb = $repo->createQueryBuilder('g');
    $qb->join('g.displayAreas', 'da')
       ->where('g.userId = :userId')
       ->andWhere('g.active = :isActive')
       ->andWhere('da.areaKey = :areaKey')
       ->setParameter('userId', $userId)
       ->setParameter('isActive', true)
       ->setParameter('areaKey', 'rapidcreate')
       ->orderBy('g.title', 'ASC');
    $generators = $qb->getQuery()->getResult();
}

require __DIR__ . '/entity_icons.php';

$entityTypes = [
    'characters'      => 'Character',
    'character_poses' => 'Character Pose',
    'animas'          => 'Anima',
    'locations'       => 'Location',
    'backgrounds'     => 'Background',
    'artifacts'       => 'Artifact',
    'vehicles'        => 'Vehicle',
    'scene_parts'     => 'Scene Part',
    'controlnet_maps' => 'Controlnet Map',
    'spawns'          => 'Spawn',
    'generatives'     => 'Generative',
    'sketches'        => 'Sketch',
    'prompt_matrix_blueprints' => 'Prompt Matrix Blueprint',
    'composites'      => 'Composite',
];

$entityDisplayName = $entityTypes[$entityType] ?? ucfirst(rtrim($entityType, 's'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Edit' : 'Create' ?> <?= htmlspecialchars($entityDisplayName) ?></title>
    
    <script>
      (function() {
        try {
          var theme = localStorage.getItem('spw_theme');
          if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
          } else if (theme === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
          }
        } catch (e) {}
      })();
    </script>

    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
    
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5.3.7/dist/photoswipe.css">
    
    <style>
        /* [Existing styles unchanged] */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--bg);
            min-height: 100vh;
            padding: 20px;
            color: var(--text);
        }
        
        /* Entity Type Selector */
        .entity-type-selector {
            background: rgba(var(--accent-rgb, 59, 130, 246), 0.1);
            border: 2px solid rgba(var(--accent-rgb, 59, 130, 246), 0.3);
            border-radius: 30px;
            padding: 8px 36px 8px 16px;
            font-size: 14px;
            font-weight: 600;
            color: var(--accent);
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            transition: all 0.2s;
            min-width: 200px;
            border: none;
        }
        
        .entity-type-selector:hover {
            background: rgba(var(--accent-rgb, 59, 130, 246), 0.15);
            border-color: var(--accent);
            transform: translateY(-1px);
        }
        
        .entity-type-selector:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(var(--accent-rgb, 59, 130, 246), 0.15);
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .card {
            background: var(--card);
            border-radius: 16px;
            box-shadow: var(--card-elevation);
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid rgba(var(--muted-border-rgb), 0.06);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap; 
            gap: 12px;
        }
        
        /* Automation Dashboard Styles */
        .auto-controls-container {
            flex-basis: 100%; 
            margin-top: 15px;
            padding: 12px;
            background: rgba(99, 102, 241, 0.08); 
            border: 1px dashed rgba(99, 102, 241, 0.4);
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            transition: background 0.3s;
        }

        .btn-auto-toggle {
            background: #6366f1; 
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        .btn-auto-toggle:hover { background: #4f46e5; }
        
        .btn-auto-toggle.active { 
            background: #ef4444; 
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.4);
        }
        
        .btn-auto-toggle.active::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            border: 2px solid white;
            border-radius: 6px;
            animation: pulse-border 1.5s infinite;
        }

        .auto-opt {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text);
            cursor: pointer;
            user-select: none;
        }

        .auto-opt input[type="checkbox"] {
            width: 16px; 
            height: 16px;
            accent-color: #6366f1;
            cursor: pointer;
        }

        .auto-counter-badge {
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid rgba(99, 102, 241, 0.2);
            white-space: nowrap;
        }
        
        [data-theme='dark'] .auto-counter-badge {
            background: rgba(99, 102, 241, 0.2);
            color: #818cf8;
        }

        .auto-controls-container.running {
            background: rgba(239, 68, 68, 0.05); 
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        .auto-controls-container.running .auto-opt {
            opacity: 0.6;
            pointer-events: none;
        }

        @keyframes pulse-border {
            0% { transform: scale(1); opacity: 1; }
            100% { transform: scale(1.05); opacity: 0; }
        }

        .status-step {
            font-size: 12px;
            color: var(--text-muted);
            margin-left: auto;
            font-weight: 600;
            animation: fadeIn 0.3s;
        }

        h1 {
            font-size: 24px;
            color: var(--text);
            font-weight: 600;
        }
        
        .notice {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .notice.success {
            background: rgba(35,134,54,0.12);
            color: var(--green);
            border: 1px solid rgba(35,134,54,0.2);
        }
        
        .notice.error {
            background: rgba(218,54,51,0.12);
            color: var(--red);
            border: 1px solid rgba(218,54,51,0.2);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(var(--muted-border-rgb), 0.12);
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.2s;
            font-family: inherit;
            background: var(--bg);
            color: var(--text);
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        textarea {
            min-height: 150px;
            resize: vertical;
            font-family: ui-monospace, monospace;
            font-size: 14px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--accent);
            color: white;
        }
        
        .btn-primary[disabled] {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-primary:hover:not([disabled]) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        .btn-secondary {
            background: var(--card);
            color: var(--text);
            border: 1px solid rgba(var(--muted-border-rgb), 0.12);
        }
        
        .btn-secondary:hover {
            background: rgba(var(--muted-border-rgb), 0.03);
        }
        
        .btn-generator {
            background: var(--green);
            color: white;
            width: 100%;
            margin-bottom: 12px;
        }
        
        .btn-generator:hover:not([disabled]) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(35, 134, 54, 0.4);
        }
        
        .actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            flex-wrap: wrap;
        }
        
        .preview-image {
            margin-top: 12px;
            cursor: pointer;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: inline-block;
        }
        
        .preview-image img {
            display: block;
            width: 200px;
            height: 200px;
            object-fit: cover;
        }
        
        .generator-panel {
            background: rgba(35,134,54,0.08);
            border: 2px solid rgba(35,134,54,0.2);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
        }
        
        .generator-panel h3 {
            font-size: 16px;
            color: var(--green);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        
        .generator-select {
            width: 100%;
            padding: 10px;
            border: 2px solid rgba(35,134,54,0.2);
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 12px;
            background: var(--bg);
            color: var(--text);
        }
        
        .template-preview {
            background: var(--bg);
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 13px;
            color: var(--text-muted);
            border-left: 3px solid rgba(35,134,54,0.5);
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
        }
        
        .toast {
            background: rgba(0,0,0,0.85);
            color: white;
            padding: 10px 14px;
            margin-bottom: 8px;
            border-radius: 8px;
            min-width: 180px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            font-weight: 600;
        }
        
        .toast.success {
            background: var(--green);
        }
        
        .toast.error {
            background: var(--red);
        }

        @media (max-width: 768px) {
            body {
                padding: 12px;
            }
            .entity-type-selector {
                min-width: auto;
                width: 100%;
            }
            .card {
                padding: 16px;
                border-radius: 12px;
            }
            h1 {
                font-size: 20px;
            }
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            .auto-controls-container {
                flex-direction: column;
                align-items: stretch;
            }
            .status-step {
                margin-left: 0;
                margin-top: 5px;
            }
            .actions {
                flex-direction: column;
            }
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                    <!-- Entity Type Selector -->
                    <select class="entity-type-selector" id="entityTypeSwitch" onchange="handleEntityTypeChange(this.value)">
                        <?php foreach ($allowedTables as $type): 
                            $icon = $entityIcons[$type] ?? '📦';
                            $label = $entityTypes[$type] ?? ucfirst(rtrim($type, 's'));
                        ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= $type === $entityType ? 'selected' : '' ?>>
                                <?= $icon ?> <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Automation Dashboard -->
                <div class="auto-controls-container" id="autoControlsArea">
                    <button type="button" id="autoPilotBtn" class="btn-auto-toggle" onclick="toggleAutomation()">
                        <span id="autoPilotIcon">▶️</span> 
                        <span id="autoPilotText">Start Auto Pilot</span>
                    </button>

                    <label class="auto-opt">
                        <input type="checkbox" id="autoUseTemplates" checked>
                        Allow Sketch Templates
                    </label>

                    <label class="auto-opt">
                        <input type="checkbox" id="autoUseInteractions" checked>
                        Allow Interactions
                    </label>

                    <span class="auto-counter-badge">
                        Created: <b id="autoCounterVal">0</b>
                    </span>

                    <span id="autoStatus" class="status-step"></span>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="notice success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php foreach ($errors as $error): ?>
                <div class="notice error"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <form method="POST" id="entityForm" novalidate>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="entity_type" value="<?= htmlspecialchars($entityType) ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="entity_id" id="entity_id" value="<?= htmlspecialchars($entityId) ?>">
                <?php endif; ?>

                <!-- META HIDDEN FIELDS -->
                <input type="hidden" name="meta_gen_name_id" id="meta_gen_name_id" value="">
                <input type="hidden" name="meta_gen_desc_id" id="meta_gen_desc_id" value="">
                <input type="hidden" name="meta_template_id" id="meta_template_id" value="">
                <input type="hidden" name="meta_interaction_id" id="meta_interaction_id" value="">

                <div class="form-group">
                    <label for="name">Name *</label>
                    
                    <?php if (!empty($generators)): ?>
                    <div class="generator-panel">
                        <h3>⚡️ Generate Name</h3>
                        <select class="generator-select" id="nameGeneratorSelect">
                            <option value="">-- Select Name Generator --</option>
                            <?php foreach ($generators as $gen): ?>
                                <option 
                                    value="<?= htmlspecialchars($gen->getConfigId()) ?>"
                                    data-title="<?= htmlspecialchars($gen->getTitle()) ?>"
                                    <?= ($gen->getConfigId() === ID_NAME_GEN ? ' selected' : '') ?>>
                                    <?= htmlspecialchars($gen->getTitle()) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-generator" onclick="generateField('name')">
                            ⚡️ Generate Name
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="<?= htmlspecialchars($entity['name'] ?? '') ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="description">Description / Prompt</label>
                    
                    <?php if ($entityType === 'sketches' && !empty($sketchTemplates)): ?>
                    <div class="generator-panel">
                        <h3>🎬 Sketch Template Context</h3>
                        <select class="generator-select" id="sketchTemplateSelect" onchange="previewTemplate()">
                            <option value="">-- Select Template (Optional) --</option>
                            <?php foreach ($sketchTemplates as $tpl): ?>
                                <option value="<?= htmlspecialchars($tpl['id']) ?>"
                                        data-prompt="<?= htmlspecialchars($tpl['example_prompt']) ?>"
                                        data-idea="<?= htmlspecialchars($tpl['core_idea']) ?>">
                                    <?= htmlspecialchars($tpl['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="templatePreview" class="template-preview" style="display:none;"></div>
                        <p style="font-size:13px;color:var(--text-muted);margin-top:8px;">
                            💡 Selected template will be included as context when generating description below
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php // Interaction Context UI ?>
                    <?php if ($entityType === 'sketches' && !empty($structuredInteractions)): ?>
                    <div class="generator-panel">
                        <h3>🤝 Interaction Context</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 12px;">
                            <div>
                                <label for="interactionGroupSelect" style="font-size:13px;font-weight:500;margin-bottom:4px;">1. Group</label>
                                <select class="generator-select" id="interactionGroupSelect" style="margin-bottom:0;">
                                    <option value="">-- Select Group --</option>
                                </select>
                            </div>
                            <div>
                                <label for="interactionCategorySelect" style="font-size:13px;font-weight:500;margin-bottom:4px;">2. Category</label>
                                <select class="generator-select" id="interactionCategorySelect" style="margin-bottom:0;" disabled>
                                    <option value="">-- Select Category --</option>
                                </select>
                            </div>
                            <div>
                                <label for="interactionSelect" style="font-size:13px;font-weight:500;margin-bottom:4px;">3. Interaction</label>
                                <select class="generator-select" id="interactionSelect" style="margin-bottom:0;" disabled>
                                    <option value="">-- Select Interaction --</option>
                                </select>
                            </div>
                        </div>
                        <div id="interactionPreview" class="template-preview" style="display:none;"></div>
                        <p style="font-size:13px;color:var(--text-muted);margin-top:8px;">
                            💡 Selected interaction will be included as context when generating description.
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($generators)): ?>
                    <div class="generator-panel">
                        <h3>⚡️ AI Generator</h3>
                        <select class="generator-select" id="descGeneratorSelect">
                            <option value="">-- Select Generator --</option>
                            <?php foreach ($generators as $gen): ?>
                                <option 
                                    value="<?= htmlspecialchars($gen->getConfigId()) ?>"
                                    data-title="<?= htmlspecialchars($gen->getTitle()) ?>"
                                    <?= ($gen->getConfigId() === ID_DESC_GEN ? ' selected' : '') ?>>
                                    <?= htmlspecialchars($gen->getTitle()) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-generator" onclick="generateField('description')">
                            ⚡️ Generate Description
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <textarea id="description" 
                              name="description" 
                              placeholder="Enter description or use generator above..."><?= htmlspecialchars($entity['description'] ?? '') ?></textarea>
                </div>

                <?php if ($previewImage): ?>
                <div class="form-group">
                    <label>Current Image Preview</label>
                    <a href="<?= htmlspecialchars($previewImage) ?>" 
                       class="preview-image"
                       data-pswp-width="1024" 
                       data-pswp-height="1024">
                        <img src="<?= htmlspecialchars($previewImage) ?>" 
                             alt="Preview">
                    </a>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" 
                               id="regenerate_images" 
                               name="regenerate_images" 
                               value="1"
                               <?= !empty($entity['regenerate_images']) ? 'checked' : '' ?>>
                        <label for="regenerate_images">Regenerate Images</label>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" id="submitBtn" class="btn btn-primary">
                        💾 <?= $isEdit ? 'Update' : 'Create' ?> <?= htmlspecialchars($entityDisplayName) ?>
                    </button>
                    <a href="javascript:history.back()" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5.3.7/dist/umd/photoswipe.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5.3.7/dist/umd/photoswipe-lightbox.umd.min.js"></script>
    
    <script>
        const PAGE_IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;
        const PAGE_ENTITY_ID = <?= json_encode($entityId) ?>;
        const PAGE_ENTITY_TYPE = <?= json_encode($entityType) ?>;
        const STRUCTURED_INTERACTIONS = <?= json_encode($structuredInteractions) ?>;

        function handleEntityTypeChange(newType) {
            if (newType === PAGE_ENTITY_TYPE) return;
            const url = new URL(window.location.href);
            url.searchParams.set('entity_type', newType);
            url.searchParams.delete('entity_id');
            window.location.href = url.toString();
        }

        if (document.querySelector('.preview-image')) {
            const lightbox = new PhotoSwipeLightbox({
                gallery: 'body',
                children: '.preview-image',
                pswpModule: PhotoSwipe
            });
            lightbox.init();
        }

        function initializeInteractionUI() {
            const groupSelect = document.getElementById('interactionGroupSelect');
            if (!groupSelect) return;

            const categorySelect = document.getElementById('interactionCategorySelect');
            const interactionSelect = document.getElementById('interactionSelect');
            const previewBox = document.getElementById('interactionPreview');

            function resetSelect(select, defaultText) {
                select.innerHTML = `<option value="">-- ${defaultText} --</option>`;
                select.disabled = true;
                select.selectedIndex = 0;
            }

            function populateGroups() {
                resetSelect(categorySelect, 'Select Category');
                resetSelect(interactionSelect, 'Select Interaction');
                previewBox.style.display = 'none';

                const groups = Object.keys(STRUCTURED_INTERACTIONS);
                groups.sort().forEach(group => {
                    const option = document.createElement('option');
                    option.value = group;
                    option.textContent = group;
                    groupSelect.appendChild(option);
                });
            }

            function populateCategories() {
                const selectedGroup = groupSelect.value;
                resetSelect(categorySelect, 'Select Category');
                resetSelect(interactionSelect, 'Select Interaction');
                previewBox.style.display = 'none';
                
                if (!selectedGroup) return;

                const categories = Object.keys(STRUCTURED_INTERACTIONS[selectedGroup]);
                if (categories.length > 0) {
                    categories.sort().forEach(category => {
                        const option = document.createElement('option');
                        option.value = category;
                        option.textContent = category;
                        categorySelect.appendChild(option);
                    });
                    categorySelect.disabled = false;
                }
            }

            function populateInteractions() {
                const selectedGroup = groupSelect.value;
                const selectedCategory = categorySelect.value;
                resetSelect(interactionSelect, 'Select Interaction');
                previewBox.style.display = 'none';

                if (!selectedGroup || !selectedCategory) return;
                
                const interactions = STRUCTURED_INTERACTIONS[selectedGroup][selectedCategory];
                if (interactions && interactions.length > 0) {
                    interactions.forEach(interaction => {
                        const option = document.createElement('option');
                        option.value = interaction.id;
                        option.textContent = interaction.name;
                        option.dataset.prompt = interaction.prompt;
                        option.dataset.description = interaction.description;
                        interactionSelect.appendChild(option);
                    });
                    interactionSelect.disabled = false;
                }
            }

            function previewInteraction() {
                const selectedOption = interactionSelect.options[interactionSelect.selectedIndex];
                if (selectedOption && selectedOption.value) {
                    const desc = selectedOption.dataset.description;
                    const prompt = selectedOption.dataset.prompt;
                    previewBox.innerHTML = `<strong>Description:</strong> ${desc}<br><strong>Prompt Snippet:</strong> ${prompt}`;
                    previewBox.style.display = 'block';
                } else {
                    previewBox.style.display = 'none';
                }
            }

            groupSelect.addEventListener('change', populateCategories);
            categorySelect.addEventListener('change', populateInteractions);
            interactionSelect.addEventListener('change', previewInteraction);

            populateGroups();
        }
        
        if (PAGE_ENTITY_TYPE === 'sketches') {
            initializeInteractionUI();
        }

        function previewTemplate() {
            const select = document.getElementById('sketchTemplateSelect');
            const option = select.options[select.selectedIndex];
            const preview = document.getElementById('templatePreview');
            
            if (option && option.value) {
                const idea = option.getAttribute('data-idea');
                const prompt = option.getAttribute('data-prompt');
                preview.innerHTML = `<strong>Concept:</strong> ${idea}<br><strong>Prompt:</strong> ${prompt}`;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }

        async function generateField(fieldName) {
            const selectId = fieldName === 'name' ? 'nameGeneratorSelect' : 'descGeneratorSelect';
            const select = document.getElementById(selectId);
            const configId = select.value;
            
            const isAuto = typeof isAutomating !== 'undefined' && isAutomating;

            if (!configId) {
                const msg = 'Please select a generator first';
                isAuto ? console.warn(msg) : alert(msg);
                return;
            }

            // --- Robust Button Finder ---
            let btn = null;
            if (typeof event !== 'undefined' && event && event.target) {
                btn = event.target;
            }
            if (!btn) {
                const selector = fieldName === 'name' 
                    ? 'button[onclick*="generateField(\'name\')"]' 
                    : 'button[onclick*="generateField(\'description\')"]';
                btn = document.querySelector(selector);
            }

            const targetField = document.getElementById(fieldName);
            
            let originalText = '';
            if (btn) {
                originalText = btn.textContent;
                btn.disabled = true;
                btn.textContent = '⚡️ Generating...';
            }

            try {
                const infoResponse = await fetch(`/api/generate.php?config_id=${encodeURIComponent(configId)}&_info=1`);
                const infoData = await infoResponse.json();
                
                if (!infoData.ok) throw new Error('Failed to load generator info');

                let finalEntityName = '';
                if (fieldName === 'name') {
                    finalEntityName = document.getElementById('description').value || '';
                } else {
                    finalEntityName = document.getElementById('name').value || '';
                }
                
                // --- META CAPTURE LOGIC START ---
                // Store the Generator ID
                if (fieldName === 'name') {
                    document.getElementById('meta_gen_name_id').value = configId;
                } else {
                    document.getElementById('meta_gen_desc_id').value = configId;
                    
                    // Capture Template ID
                    const sketchTemplateSelect = document.getElementById('sketchTemplateSelect');
                    if (sketchTemplateSelect && sketchTemplateSelect.value) {
                        document.getElementById('meta_template_id').value = sketchTemplateSelect.value;
                    } else {
                         document.getElementById('meta_template_id').value = '';
                    }

                    // Capture Interaction ID
                    const interactionSelect = document.getElementById('interactionSelect');
                    if (interactionSelect && interactionSelect.value) {
                        document.getElementById('meta_interaction_id').value = interactionSelect.value;
                    } else {
                         document.getElementById('meta_interaction_id').value = '';
                    }
                }
                // --- META CAPTURE LOGIC END ---

                const sketchTemplateSelect = document.getElementById('sketchTemplateSelect');
                if (sketchTemplateSelect && sketchTemplateSelect.value) {
                    const selectedOption = sketchTemplateSelect.options[sketchTemplateSelect.selectedIndex];
                    const templatePrompt = selectedOption.getAttribute('data-prompt');
                    if (templatePrompt) {
                        finalEntityName += ` (${templatePrompt.trim()})`;
                    }
                }

                const interactionSelect = document.getElementById('interactionSelect');
                if (interactionSelect && interactionSelect.value) {
                    const selectedOption = interactionSelect.options[interactionSelect.selectedIndex];
                    const interactionPrompt = selectedOption.getAttribute('data-prompt');
                    if (interactionPrompt) {
                        finalEntityName += ` (Interaction: ${interactionPrompt.trim()})`;
                    }
                }

                const params = {
                    config_id: configId,
                    entity_type: PAGE_ENTITY_TYPE,
                    entity_name: finalEntityName,
                    random_seed: Math.floor(Math.random() * 1000000)
                };

                if (infoData.config.uses_oracle) {
                    params.random_oracle_seed = Math.floor(Math.random() * 10000000);
                }

                if (infoData.config.parameters) {
                    for (const [key, def] of Object.entries(infoData.config.parameters)) {
                        if (!params[key] && def.default !== undefined) {
                            params[key] = def.default;
                        }
                    }
                }

                const response = await fetch('/api/generate.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(params)
                });

                const result = await response.json();

                if (result.ok && result.data) {
                    let generatedText = '';
                    
                    if (typeof result.data === 'string') {
                        generatedText = result.data;
                    } else if (result.data.description) {
                        generatedText = result.data.description;
                    } else if (result.data.name) {
                        generatedText = result.data.name;
                    } else if (result.data.prompt) {
                        generatedText = result.data.prompt;
                    } else if (result.data.text) {
                        generatedText = result.data.text;
                    } else {
                        const firstValue = Object.values(result.data)[0];
                        generatedText = typeof firstValue === 'string' ? firstValue : JSON.stringify(result.data, null, 2);
                    }

                    targetField.value = generatedText;
                    targetField.dispatchEvent(new Event('input', { bubbles: true }));
                } else {
                    throw new Error(result.error || 'Unknown error');
                }
            } catch (err) {
                const errMsg = 'Request failed: ' + err.message;
                isAuto ? console.error(errMsg) : alert(errMsg);
                throw err;
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            }
        }

        function showToast(message, type = 'success', timeout = 3000) {
            const container = document.getElementById('toastContainer');
            const t = document.createElement('div');
            t.className = 'toast ' + (type === 'error' ? 'error' : 'success');
            t.textContent = message;
            container.appendChild(t);
            setTimeout(() => {
                t.style.transition = 'opacity 300ms';
                t.style.opacity = '0';
                setTimeout(() => t.remove(), 350);
            }, timeout);
        }

        // AJAX submit handling
        (function() {
            const form = document.getElementById('entityForm');
            const submitBtn = document.getElementById('submitBtn');

            form.addEventListener('submit', async function(ev) {
                ev.preventDefault();

                const formData = new FormData(form);
                const payload = {};
                for (const [k,v] of formData.entries()) {
                    payload[k] = v;
                }

                payload.regenerate_images = document.getElementById('regenerate_images')?.checked ? 1 : 0;
                payload.img2img = document.getElementById('img2img')?.checked ? 1 : 0;
                payload.cnmap = document.getElementById('cnmap')?.checked ? 1 : 0;

                if (PAGE_IS_EDIT && PAGE_ENTITY_ID) {
                    payload.entity_id = PAGE_ENTITY_ID;
                }

                payload.action = 'save';

                submitBtn.disabled = true;
                const originalText = submitBtn.textContent;
                submitBtn.textContent = PAGE_IS_EDIT ? 'Saving...' : 'Creating...';

                try {
                    const res = await fetch(window.location.pathname + window.location.search.split('&').filter(Boolean).join('&'), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify(payload)
                    });

                    const isJson = res.headers.get('content-type') && res.headers.get('content-type').includes('application/json');
                    if (!isJson) throw new Error('Server did not return JSON');

                    const data = await res.json();

                    if (res.ok && data.ok) {
                        if (data.created) {
                            form.reset();
                            const hid = document.getElementById('entity_id');
                            if (hid) hid.remove();
                            // Reset Meta Fields on successful create
                            document.getElementById('meta_gen_name_id').value = '';
                            document.getElementById('meta_gen_desc_id').value = '';
                            document.getElementById('meta_template_id').value = '';
                            document.getElementById('meta_interaction_id').value = '';
                            
                            showToast('Created ✓ ID ' + data.id, 'success', 2000);
                            document.getElementById('name').focus();
                        } else {
                            showToast('Updated ✓ ID ' + data.id, 'success', 1500);
                        }
                    } else {
                        const errText = (data && (data.error || (data.errors && data.errors.join ? data.errors.join(', ') : JSON.stringify(data.errors)))) || 'Unknown error';
                        showToast('Error: ' + errText, 'error', 4000);
                    }
                } catch (err) {
                    showToast('Request failed: ' + err.message, 'error', 5000);
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            });
        })();
    </script>
    
    <!-- AUTOMATION SUITE -->
    <script>
    // [Existing Automation Script unchanged]
    let isAutomating = false;
    let automationLoopId = null;
    let sessionAutoCount = 0; 

    const AUTO_CONFIG = {
        chanceForSketchTemplate: 0.85, 
        chanceForInteraction: 0.60,    
        delayBetweenSteps: 800,        
        delayAfterSave: 2500           
    };

    (function() {
        const nameSelect = document.getElementById('nameGeneratorSelect');
        const descSelect = document.getElementById('descGeneratorSelect');

        function saveGenState() {
            if(nameSelect) localStorage.setItem('spw_gen_name_pref', nameSelect.value);
            if(descSelect) localStorage.setItem('spw_gen_desc_pref', descSelect.value);
        }

        function restoreGenState() {
            const savedName = localStorage.getItem('spw_gen_name_pref');
            const savedDesc = localStorage.getItem('spw_gen_desc_pref');
            if(nameSelect && savedName) nameSelect.value = savedName;
            if(descSelect && savedDesc) descSelect.value = savedDesc;
        }

        if(nameSelect) nameSelect.addEventListener('change', saveGenState);
        if(descSelect) descSelect.addEventListener('change', saveGenState);
        restoreGenState();

        const originalReset = HTMLFormElement.prototype.reset;
        HTMLFormElement.prototype.reset = function() {
            saveGenState();
            originalReset.call(this);
            restoreGenState();
        };
    })();

    function setStatus(msg) {
        if (!isAutomating) return;
        const status = document.getElementById('autoStatus');
        status.textContent = msg ? `⚙️ ${msg}...` : '';
    }

    function toggleAutomation() {
        isAutomating = !isAutomating;
        
        const btn = document.getElementById('autoPilotBtn');
        const icon = document.getElementById('autoPilotIcon');
        const text = document.getElementById('autoPilotText');
        const status = document.getElementById('autoStatus');
        const container = document.getElementById('autoControlsArea');
        
        const chkTemplates = document.getElementById('autoUseTemplates');
        const chkInteractions = document.getElementById('autoUseInteractions');

        if (isAutomating) {
            btn.classList.add('active');
            container.classList.add('running');
            icon.textContent = '⏸';
            text.textContent = 'Stop Auto';
            
            chkTemplates.disabled = true;
            chkInteractions.disabled = true;

            sessionAutoCount = 0;
            document.getElementById('autoCounterVal').textContent = sessionAutoCount;

            runAutomationCycle();
        } else {
            btn.classList.remove('active');
            container.classList.remove('running');
            icon.textContent = '▶️';
            text.textContent = 'Start Auto Pilot';
            status.textContent = '';
            
            chkTemplates.disabled = false;
            chkInteractions.disabled = false;

            clearTimeout(automationLoopId);
        }
    }

    function pickRandom(selectId) {
        const select = document.getElementById(selectId);
        if (!select || select.disabled) return null;

        const options = Array.from(select.options).filter(o => o.value !== "");
        if (options.length === 0) return null;

        const randomOpt = options[Math.floor(Math.random() * options.length)];
        select.value = randomOpt.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        return randomOpt.value;
    }

    const wait = (ms) => new Promise(resolve => setTimeout(resolve, ms));

    async function runAutomationCycle() {
        if (!isAutomating) return;

        setStatus('Randomizing Context');
        
        const allowTemplates = document.getElementById('autoUseTemplates').checked;
        const allowInteractions = document.getElementById('autoUseInteractions').checked;

        if (allowTemplates && Math.random() < AUTO_CONFIG.chanceForSketchTemplate) {
            pickRandom('sketchTemplateSelect');
        } else {
            const sel = document.getElementById('sketchTemplateSelect');
            if(sel) { sel.value = ""; sel.dispatchEvent(new Event('change')); }
        }

        if (PAGE_ENTITY_TYPE === 'sketches') {
            const groupSel = document.getElementById('interactionGroupSelect');
            if(groupSel) { groupSel.value = ""; groupSel.dispatchEvent(new Event('change')); }
            
            await wait(200);

            if (allowInteractions && Math.random() < AUTO_CONFIG.chanceForInteraction) {
                pickRandom('interactionGroupSelect');
                await wait(300); 
                pickRandom('interactionCategorySelect');
                await wait(300); 
                pickRandom('interactionSelect');
            }
        }

        await wait(AUTO_CONFIG.delayBetweenSteps);
        if (!isAutomating) return;

        setStatus('Generating Description');
        const descGen = document.getElementById('descGeneratorSelect');
        if (descGen && descGen.value === "" && descGen.options.length > 1) {
             descGen.selectedIndex = 1;
        }

        try {
            await generateField('description');
        } catch(e) {
            console.error("Auto Gen Error:", e);
            toggleAutomation(); 
            return;
        }

        await wait(AUTO_CONFIG.delayBetweenSteps);
        if (!isAutomating) return;

        setStatus('Generating Name');
        const nameGen = document.getElementById('nameGeneratorSelect');
        if (nameGen && nameGen.value === "" && nameGen.options.length > 1) {
             nameGen.selectedIndex = 1;
        }

        try {
            await generateField('name');
        } catch(e) {
            console.error("Auto Gen Error:", e);
        }

        await wait(AUTO_CONFIG.delayBetweenSteps);
        if (!isAutomating) return;

        setStatus('Saving Entity');
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.click();

        await new Promise(resolve => {
            const checker = setInterval(() => {
                if (!submitBtn.disabled) {
                    clearInterval(checker);
                    resolve();
                }
            }, 100);
        });

        if (isAutomating) {
            sessionAutoCount++;
            document.getElementById('autoCounterVal').textContent = sessionAutoCount;
        }

        if (!isAutomating) return;

        setStatus('Cooling down');
        automationLoopId = setTimeout(() => {
            if(isAutomating) runAutomationCycle();
        }, AUTO_CONFIG.delayAfterSave);
    }
    </script>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

<?php 
    require_once "forge_tool.php";
    echo $eruda; 
?>

</body>
</html>