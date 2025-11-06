<?php
// entity_form.php - Universal Entity CRUD with AI Generator
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;

define('ID_NAME_GEN', '9bf6de291765e2ced28589de857a9f0b');
define('ID_DESC_GEN', 'e76db8f464c7e35851685a0dbc8f3da8');

$em = $spw->getEntityManager();
// assume bootstrap.php already started session; if not, uncomment next line
// session_start();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    die('Not authenticated');
}

// --- IMPORTANT: detect edit mode only from GET (URL) as requested
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

// Load sketch templates if entity type is sketches
if ($entityType === 'sketches') {
    $conn = $em->getConnection();
    $templatesStmt = $conn->prepare("SELECT * FROM sketch_templates WHERE entity_type = 'sketches' AND active = 1 ORDER BY name");
    $templatesResult = $templatesStmt->executeQuery();
    $sketchTemplates = $templatesResult->fetchAllAssociative();
}

// Load entity if editing (based only on GET param)
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

// Helper: check whether request is JSON/AJAX
function is_json_request(): bool {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return (stripos($contentType, 'application/json') !== false)
        || (stripos($accept, 'application/json') !== false)
        || (strtolower($xhr) === 'xmlhttprequest');
}

// Handle form submission (supports both regular POST form and JSON AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If JSON, decode payload into $payload, else use $_POST
    $payload = null;
    if (is_json_request()) {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true) ?: [];
    } else {
        $payload = $_POST;
    }

    $action = $payload['action'] ?? null;

    if ($action === 'save') {
        // Determine if this POST intends an update: prefer posted entity_id param (from form or AJAX) for update
        $postEntityId = isset($payload['entity_id']) && (int)$payload['entity_id'] > 0 ? (int)$payload['entity_id'] : null;
        $isPostEdit = ($postEntityId !== null);

        $name = trim($payload['name'] ?? '');
        $description = trim($payload['description'] ?? '');
        $order = (int)($payload['order'] ?? 0);
        $regenerateImages = isset($payload['regenerate_images']) && ($payload['regenerate_images'] == 1 || $payload['regenerate_images'] === true) ? 1 : 0;
        // For JSON requests the checkboxes might come as booleans; accept both forms
        $img2img = isset($payload['img2img']) && ($payload['img2img'] == 1 || $payload['img2img'] === true) ? 1 : 0;
        $cnmap = isset($payload['cnmap']) && ($payload['cnmap'] == 1 || $payload['cnmap'] === true) ? 1 : 0;

        if (empty($name)) {
            $errors[] = 'Name is required';
        }

        if (empty($errors)) {
            $conn = $em->getConnection();

            if ($isPostEdit) {
                // UPDATE existing row
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

                // If JSON/AJAX request, respond with JSON and stop rendering page
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
                    // Non-AJAX fallback: reload entity in page so form shows updated data
                    $success = 'Entity updated successfully';
                    $stmt = $conn->prepare("SELECT * FROM {$entityType} WHERE id = ?");
                    $stmt->bindValue(1, $postEntityId);
                    $result = $stmt->executeQuery();
                    $entity = $result->fetchAssociative();
                }
            } else {
                // INSERT new row
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
                    // Non-AJAX fallback: keep behaviour similar to before (load created entity into edit view)
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
            // Validation errors
            if (is_json_request()) {
                header('Content-Type: application/json', true, 400);
                echo json_encode([
                    'ok' => false,
                    'errors' => $errors
                ]);
                exit;
            }
            // else: continue to page rendering where $errors will be shown
        }
    }
}

// Generator configs
$repo = $em->getRepository(GeneratorConfig::class);
$generators = $repo->findBy(['userId' => $userId, 'active' => true], ['title' => 'ASC']);
$entityDisplayName = ucfirst(rtrim($entityType, 's'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Edit' : 'Create' ?> <?= htmlspecialchars($entityDisplayName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5.3.7/dist/photoswipe.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            padding: 24px;
            margin-bottom: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        h1 {
            font-size: 24px;
            color: #1f2937;
        }
        .entity-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .notice {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .notice.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .notice.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }
        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.2s;
            font-family: inherit;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #667eea;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary[disabled] {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        .btn-generator {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            width: 100%;
            margin-bottom: 12px;
        }
        .btn-generator:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
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
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 2px solid #86efac;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
        }
        .generator-panel h3 {
            font-size: 16px;
            color: #065f46;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .generator-select {
            width: 100%;
            padding: 10px;
            border: 2px solid #86efac;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 12px;
            background: white;
        }
        .template-preview {
            background: white;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 13px;
            color: #6b7280;
            border-left: 3px solid #86efac;
        }

        /* simple toast */
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
            background: linear-gradient(135deg,#10b981,#059669);
        }
        .toast.error {
            background: linear-gradient(135deg,#ef4444,#b91c1c);
        }

        @media (max-width: 768px) {
            body {
                padding: 12px;
            }
            .card {
                padding: 16px;
                border-radius: 12px;
            }
            h1 {
                font-size: 20px;
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
                <h1><?= $isEdit ? 'Edit' : 'Create' ?> <?= htmlspecialchars($entityDisplayName) ?></h1>
                <span class="entity-badge"><?= htmlspecialchars($entityType) ?></span>
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

                <div class="form-group">
                    <label for="name">Name *</label>
                    
                    <?php if (!empty($generators)): ?>
                    <div class="generator-panel">
                        <h3>⚗️ Generate Name</h3>
                        <select class="generator-select" id="nameGeneratorSelect">
                            <option value="">-- Select Name Generator --</option>

<?php foreach ($generators as $gen): ?>
    <option 
        value="<?= htmlspecialchars($gen->getConfigId()) ?>"
        data-title="<?= htmlspecialchars($gen->getTitle()) ?>"
        <?= ($gen->getConfigId() === ID_NAME_GEN ? ' selected' : '') ?>
    >
        <?= htmlspecialchars($gen->getTitle()) ?>
    </option>
<?php endforeach; ?>

                        </select>
                        <button type="button" class="btn btn-generator" onclick="generateField('name')">
                            ⚗️ Generate Name
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
                    <label for="order">Display Order</label>
                    <input type="number" 
                           id="order" 
                           name="order" 
                           value="<?= htmlspecialchars($entity['order'] ?? 0) ?>">
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
                        <p style="font-size:13px;color:#6b7280;margin-top:8px;">
                            💡 Selected template will be included as context when generating description below
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    
                    <?php /*
                    
                    <?php if ($entityType === 'sketches' && !empty($sketchTemplates)): ?>
                    <div class="generator-panel">
                        <h3>🎬 Sketch Template</h3>
                        <select class="generator-select" id="sketchTemplateSelect" onchange="previewTemplate()">
                            <option value="">-- Select Sketch Template --</option>
                            <?php foreach ($sketchTemplates as $tpl): ?>
                                <option value="<?= htmlspecialchars($tpl['id']) ?>"
                                        data-prompt="<?= htmlspecialchars($tpl['example_prompt']) ?>"
                                        data-idea="<?= htmlspecialchars($tpl['core_idea']) ?>">
                                    <?= htmlspecialchars($tpl['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="templatePreview" class="template-preview" style="display:none;"></div>
                        <button type="button" class="btn btn-generator" onclick="applyTemplate()">
                            🎬 Apply Template
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    */ ?>
                    
                    
                    
                    <?php if (!empty($generators)): ?>
                    <div class="generator-panel">
                        <h3>⚗️ AI Generator</h3>
                        <select class="generator-select" id="descGeneratorSelect">
                            <option value="">-- Select Generator --</option>

<?php foreach ($generators as $gen): ?>
    <option 
        value="<?= htmlspecialchars($gen->getConfigId()) ?>"
        data-title="<?= htmlspecialchars($gen->getTitle()) ?>"
        <?= ($gen->getConfigId() === ID_DESC_GEN ? ' selected' : '') ?>
    >
        <?= htmlspecialchars($gen->getTitle()) ?>
    </option>
<?php endforeach; ?>

                        </select>
                        <button type="button" class="btn btn-generator" onclick="generateField('description')">
                            ⚗️ Generate Description
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

                    <div class="checkbox-group">
                        <input type="checkbox" 
                               id="img2img" 
                               name="img2img" 
                               value="1"
                               <?= !empty($entity['img2img']) ? 'checked' : '' ?>>
                        <label for="img2img">Enable img2img</label>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" 
                               id="cnmap" 
                               name="cnmap" 
                               value="1"
                               <?= !empty($entity['cnmap']) ? 'checked' : '' ?>>
                        <label for="cnmap">Enable ControlNet Map</label>
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
        // Page flags from PHP
        const PAGE_IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;
        const PAGE_ENTITY_ID = <?= json_encode($entityId) ?>;
        const PAGE_ENTITY_TYPE = <?= json_encode($entityType) ?>;

        if (document.querySelector('.preview-image')) {
            const lightbox = new PhotoSwipeLightbox({
                gallery: 'body',
                children: '.preview-image',
                pswpModule: PhotoSwipe
            });
            lightbox.init();
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

        function applyTemplate() {
            const select = document.getElementById('sketchTemplateSelect');
            const option = select.options[select.selectedIndex];
            
            if (!option || !option.value) {
                alert('Please select a template first');
                return;
            }

            const prompt = option.getAttribute('data-prompt');
            const descField = document.getElementById('description');
            descField.value = prompt;
            descField.dispatchEvent(new Event('input', { bubbles: true }));
        }

        async function generateField(fieldName) {
            const selectId = fieldName === 'name' ? 'nameGeneratorSelect' : 'descGeneratorSelect';
            const select = document.getElementById(selectId);
            const configId = select.value;
            
            if (!configId) {
                alert('Please select a generator first');
                return;
            }

            const targetField = document.getElementById(fieldName);
            const btn = event.target;
            
            btn.disabled = true;
            btn.textContent = '⚗️ Generating...';

            try {
                const infoResponse = await fetch(`/api/generate.php?config_id=${encodeURIComponent(configId)}&_info=1`);
                const infoData = await infoResponse.json();
                
                if (!infoData.ok) {
                    throw new Error('Failed to load generator info');
                }

                
                
                
                
                
                // Start with the base name from the input field
                let finalEntityName = '';

                if (fieldName === 'name') {
                    // we do not want to add existing nane for new name request actually but how bout desc?
                    finalEntityName = document.getElementById('description').value || '';
                } else {
                    finalEntityName = document.getElementById('name').value || '';
                }
                
                // Find the template select element
                const sketchTemplateSelect = document.getElementById('sketchTemplateSelect');
                
                // Check if the dropdown exists and a valid template is selected
                if (sketchTemplateSelect && sketchTemplateSelect.value) {
                    // Get the currently selected <option> ELEMENT
                    const selectedOption = sketchTemplateSelect.options[sketchTemplateSelect.selectedIndex];
                    
                    // Get the 'data-prompt' attribute from that element
                    const templatePrompt = selectedOption.getAttribute('data-prompt');
                    
                    // If the prompt exists, append it. The trim() is good practice.
                    if (templatePrompt) {
                        finalEntityName += ` (${templatePrompt.trim()})`;
                    }
                }

                
                
                
                const params = {
                    config_id: configId,
                    entity_type: PAGE_ENTITY_TYPE,
                    //entity_name: document.getElementById('name').value || 'unnamed',
                    entity_name: finalEntityName,
                    random_seed: Math.floor(Math.random() * 1000000)
                };

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
                    alert('Generation failed: ' + (result.error || 'Unknown error'));
                }
            } catch (err) {
                alert('Request failed: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.textContent = fieldName === 'name' ? '⚗️ Generate Name' : '⚗️ Generate Description';
            }
        }

        // --- Toast helpers
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

        // --- AJAX submit handling for rapid-create
        (function() {
            const form = document.getElementById('entityForm');
            const submitBtn = document.getElementById('submitBtn');

            form.addEventListener('submit', async function(ev) {
                ev.preventDefault();

                // Build payload object - include checkboxes as 1/0
                const formData = new FormData(form);
                const payload = {};
                for (const [k,v] of formData.entries()) {
                    payload[k] = v;
                }

                // checkboxes: if not present will be undefined -> set to 0
                payload.regenerate_images = document.getElementById('regenerate_images')?.checked ? 1 : 0;
                payload.img2img = document.getElementById('img2img')?.checked ? 1 : 0;
                payload.cnmap = document.getElementById('cnmap')?.checked ? 1 : 0;

                // If page is in edit mode (URL had entity_id), include entity_id to perform update
                if (PAGE_IS_EDIT && PAGE_ENTITY_ID) {
                    payload.entity_id = PAGE_ENTITY_ID;
                }

                // Action already in hidden input, but ensure present
                payload.action = 'save';

                // Disable submit while request in flight
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
                            // Created new entity -> clear form for rapid next creation
                            form.reset();
                            // remove any hidden entity_id if present (we are in create flow so shouldn't be)
                            const hid = document.getElementById('entity_id');
                            if (hid) hid.remove();
                            showToast('Created — ID ' + data.id, 'success', 2000);
                            // focus name field for quick entry
                            document.getElementById('name').focus();
                        } else {
                            // Updated existing entity (edit mode). Keep form as-is and show success toast.
                            showToast('Updated — ID ' + data.id, 'success', 1500);
                            // Optional: update PAGE_ENTITY_ID if returned different (normally not)
                        }
                    } else {
                        // error response
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
<?php
require "floatool.php";
echo $eruda;
?>
</body>
</html>
