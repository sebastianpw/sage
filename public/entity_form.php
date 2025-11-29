<?php
// public/entity_form.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;
use App\UI\Modules\ModuleRegistry;

$spw = SpwBase::getInstance();
$mysqli = $spw->getMysqli();

// Get parameters
$entityType = $_GET['entity_type'] ?? '';
$entityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;
$redirectUrl = $_GET['redirect_url'] ?? "gallery_{$entityType}_nu.php";

// Validate entity type (security: only allow alphanumeric and underscores)
if (!preg_match('/^[a-z_]+$/', $entityType)) {
    die('Invalid entity type.');
}

// Check if table exists
$tableCheck = $mysqli->query("SHOW TABLES LIKE '{$entityType}'");
if ($tableCheck->num_rows === 0) {
    die("Entity table '{$entityType}' does not exist.");
}

// Initialize notification
$notification = '';
$notificationType = 'success';

// Get column metadata for proper type handling
$columnInfo = [];
$result = $mysqli->query("SHOW FULL COLUMNS FROM `{$entityType}`");
while ($col = $result->fetch_assoc()) {
    $columnInfo[$col['Field']] = $col;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update' && $entityId > 0) {
        // Build UPDATE query dynamically
        $updates = [];
        $types = '';
        $values = [];
        
        foreach ($_POST as $key => $value) {
            if ($key === 'action' || $key === 'id') continue;
            
            // Skip if column doesn't exist (security)
            if (!isset($columnInfo[$key])) continue;
            
            $colInfo = $columnInfo[$key];
            $colType = strtolower($colInfo['Type']);
            $isNullable = ($colInfo['Null'] === 'YES');
            
            $updates[] = "`{$key}` = ?";
            
            // Handle empty values for nullable fields
            if ($value === '' && $isNullable) {
                $values[] = null;
                $types .= 's'; // NULL can use string type
                continue;
            }
            
            // Integer fields
            if (preg_match('/^(tiny|small|medium|big)?int/', $colType)) {
                if ($value === '') { $value = 0; }
                $types .= 'i';
                $values[] = (int)$value;
            }
            // Float/Decimal fields
            elseif (preg_match('/^(float|double|decimal)/', $colType)) {
                if ($value === '') { $value = $isNullable ? null : 0.0; }
                $types .= 'd';
                $values[] = $value === null ? null : (float)$value;
            }
            // All other fields (text, varchar, etc.)
            else {
                $types .= 's';
                $values[] = $value;
            }
        }
        
        if (!empty($updates)) {
            $sql = "UPDATE `{$entityType}` SET " . implode(', ', $updates) . " WHERE id = ?";
            $types .= 'i';
            $values[] = $entityId;
            
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$values);
                if ($stmt->execute()) {
                    $notification = "Entity updated successfully!";
                    $notificationType = 'success';
                } else {
                    $notification = "Error updating entity: " . $stmt->error;
                    $notificationType = 'error';
                }
                $stmt->close();
            } else {
                $notification = "Error preparing statement: " . $mysqli->error;
                $notificationType = 'error';
            }
        }
    }
}

// Fetch entity data
$entity = null;
if ($entityId > 0) {
    $stmt = $mysqli->prepare("SELECT * FROM `{$entityType}` WHERE id = ?");
    $stmt->bind_param('i', $entityId);
    $stmt->execute();
    $result = $stmt->get_result();
    $entity = $result->fetch_assoc();
    $stmt->close();
    
    if (!$entity) {
        die("Entity #{$entityId} not found in {$entityType} table.");
    }
} else {
    die("No entity ID provided.");
}

// Fetch associated frames
$frames = [];
$frameStmt = $mysqli->prepare("SELECT id, name, filename FROM frames WHERE entity_type = ? AND entity_id = ? ORDER BY id DESC");
if ($frameStmt) {
    $frameStmt->bind_param('si', $entityType, $entityId);
    $frameStmt->execute();
    $frameResult = $frameStmt->get_result();
    while ($row = $frameResult->fetch_assoc()) {
        $frames[] = $row;
    }
    $frameStmt->close();
}

// --- fetch img2img / cnmap frames (if referenced on the entity)
$specialFrames = []; // keys: 'img2img', 'cnmap'
if (!empty($entity['img2img_frame_id'])) {
    $fid = (int)$entity['img2img_frame_id'];
    $stmt = $mysqli->prepare("SELECT id, name, filename FROM frames WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $fid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $specialFrames['img2img'] = $row;
        }
        $stmt->close();
    }
}
if (!empty($entity['cnmap_frame_id'])) {
    $fid = (int)$entity['cnmap_frame_id'];
    $stmt = $mysqli->prepare("SELECT id, name, filename FROM frames WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $fid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $specialFrames['cnmap'] = $row;
        }
        $stmt->close();
    }
}

// --- fetch composite frames when editing a composite entity
$compositeFrames = [];
if ($entityType === 'composites') {
    $stmt = $mysqli->prepare("
        SELECT f.id, f.name, f.filename
        FROM composite_frames cf
        JOIN frames f ON f.id = cf.frame_id
        WHERE cf.composite_id = ?
        ORDER BY cf.created_at DESC, f.id DESC
    ");
    if ($stmt) {
        $stmt->bind_param('i', $entityId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $compositeFrames[] = $row;
        }
        $stmt->close();
    }
}


// Get column metadata from database
$columns = [];
$result = $mysqli->query("SHOW FULL COLUMNS FROM `{$entityType}`");
while ($col = $result->fetch_assoc()) {
    $columns[] = $col;
}

// Determine form field types and properties
function getFieldConfig($column) {
    $config = [
        'name' => $column['Field'],
        'type' => 'text',
        'required' => $column['Null'] === 'NO' && $column['Default'] === null && $column['Extra'] !== 'auto_increment',
        'readonly' => false,
        'hidden' => false,
        'label' => ucwords(str_replace('_', ' ', $column['Field'])),
        'help' => $column['Comment'] ?: '',
        'rows' => 3,
        'nullable' => $column['Null'] === 'YES',
        'placeholder' => '',
    ];
    
    if ($column['Extra'] === 'auto_increment' || $column['Key'] === 'PRI') {
        $config['readonly'] = true;
        $config['type'] = 'number';
    }
    if (in_array($column['Field'], ['created_at', 'updated_at'])) {
        $config['readonly'] = true;
        $config['type'] = 'datetime-local';
    }
    if (stripos($column['Type'], 'tinyint(1)') !== false) {
        $config['type'] = 'checkbox';
    }
    if (preg_match('/^int\(/', $column['Type'])) {
        $config['type'] = 'number';
        if ($config['nullable']) { $config['placeholder'] = 'Leave empty for NULL'; }
    }
    if (stripos($column['Type'], 'text') !== false || stripos($column['Type'], 'longtext') !== false) {
        $config['type'] = 'textarea';
        $config['rows'] = 5;
    }
    if (preg_match('/varchar\((\d+)\)/', $column['Type'], $matches)) {
        if ((int)$matches[1] > 200) {
            $config['type'] = 'textarea';
            $config['rows'] = 3;
        }
    }
    if (stripos($column['Type'], 'date') !== false) {
        $config['type'] = 'datetime-local';
    }
    
    return $config;
}

// Build field configs
$fields = array_map('getFieldConfig', $columns);

// Organize fields into sections for better UX
$alwaysVisibleFields = [];
$collapsibleFields = [];
$systemFields = [];
$alwaysVisibleNames = ['name', 'description', 'regenerate_images'];

foreach ($fields as $field) {
    if (in_array($field['name'], ['id', 'created_at', 'updated_at'])) {
        $systemFields[] = $field; // System fields will go into the collapsible part
    } elseif (in_array($field['name'], $alwaysVisibleNames) && isset($entity[$field['name']])) {
        $alwaysVisibleFields[] = $field;
    } else {
        $collapsibleFields[] = $field; // Everything else is collapsible
    }
}

// Configure modules for the frame grid
$registry = ModuleRegistry::getInstance();

$gearMenu = $registry->create('gear_menu', ['position' => 'top-right', 'icon' => '&#9881;', 'icon_size' => '1.5em', 'show_for_entities' => [$entityType]]);
$gearMenu->addAction($entityType, ['label' => 'View Frame Details', 'icon' => '👁️', 'callback' => 'window.location.href = "view_frame.php?frame_id=" + frameId;']);
$gearMenu->addAction($entityType, ['label' => 'Edit Image', 'icon' => '🖌️', 'callback' => 'const $w = $(wrapper); if (typeof ImageEditorModal !== "undefined") { ImageEditorModal.open({ entity: entity, entityId: entityId, frameId: frameId, src: $w.find("img").attr("src") }); }']);
$gearMenu->addAction($entityType, [
    'label' => 'View Frame Chain',
    'icon' => '🔗', // A chain link icon
    'callback' => 'window.showFrameChainInModal(frameId);'
]);


$gearMenu->addAction($entityType, ['label' => 'Import to Generative', 'icon' => '⚡', 'callback' => 'window.importGenerative(entity, entityId, frameId);']);

$gearMenu->addAction($entityType, ['label' => 'Add to Storyboard', 'icon' => '🎬', 'callback' => 'window.selectStoryboard(frameId, $(wrapper));']);
$gearMenu->addAction($entityType, ['label' => 'Assign to Composite', 'icon' => '🧩', 'callback' => 'window.assignToComposite(entity, entityId, frameId);']);
$gearMenu->addAction($entityType, ['label' => 'Import to ControlNet Map', 'icon' => '🎛️', 'callback' => 'window.importControlNetMap(entity, entityId, frameId);']);
$gearMenu->addAction($entityType, ['label' => 'Use Prompt Matrix', 'icon' => '🌐', 'callback' => 'window.usePromptMatrix(entity, entityId, frameId);']);
$gearMenu->addAction($entityType, ['label' => 'Delete Frame', 'icon' => '🗑️', 'callback' => 'if (confirm("Delete this frame?")) { window.deleteFrame(entity, entityId, frameId, wrapper); }']);


// Configure image editor module
$imageEditor = $registry->create('image_editor', [
    'modes' => ['mask', 'crop'],
    'show_transform_tab' => true,
    'show_filters_tab' => true, // This is a legacy setting now, but we leave it for consistency
    'enable_rotate' => true,
    'enable_resize' => true,
    'preset_filters' => [
        'grayscale', 'vintage', 'sepia', 'clarendon',
        'gingham', 'moon', 'lark', 'reyes', 'juno', 'slumber'
    ], // 'sharpen' has been removed
]);


$pageTitle = "Edit " . ucfirst($entityType) . ": " . ($entity['name'] ?? "ID {$entityId}");
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.7">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script>
    (function() {
        try {
            var theme = localStorage.getItem('spw_theme');
            if (theme === 'dark' || theme === 'light') { document.documentElement.setAttribute('data-theme', theme); }
        } catch (e) {}
    })();
    </script>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/toast.css">
    <?php if (SpwBase::CDN_USAGE): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
    <?php else: ?>
    <link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
    <?php endif; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/js/toast.js"></script>
    <script src="/js/gear_menu_globals.js"></script>
    
    <?php if (SpwBase::CDN_USAGE): ?>
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.umd.js"></script>
    <?php else: ?>
    <script src="/vendor/photoswipe/photoswipe.umd.js"></script>
    <script src="/vendor/photoswipe/photoswipe-lightbox.umd.js"></script>
    <?php endif; ?>
    
    <style>
        .field-section { margin-bottom: 10px; }
        .field-section-header { font-size: 16px; font-weight: 600; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid rgba(var(--muted-border-rgb), 0.2); color: var(--text); }
        .form-group { margin-bottom: 10px !important; }
        .form-group-inline { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 640px) { .form-group-inline { grid-template-columns: 1fr; } }
        .readonly-field { background-color: rgba(0, 0, 0, 0.05); cursor: not-allowed; }
        .form-help { font-size: 12px; color: var(--text-muted); margin-top: 4px; font-style: italic; display:none; }
label[for="field_regenerate_images"] {
}
        
        /* Styles for Frame Grid */
        .frames-grid { display: grid; grid-template-columns: repeat(3, 1fr) !important; gap: 16px; }
        .frame-item { position: relative; z-index: 1; aspect-ratio: 1 / 1; overflow: hidden; border-radius: 8px; background-color: var(--card); box-shadow: var(--card-elevation); border: 1px solid rgba(var(--muted-border-rgb), 0.1); }
        .frame-item:hover { z-index: 10; overflow: visible; }
        .frame-item img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; display: block; }
        .frame-item:hover img { transform: scale(1.05); }
        .frame-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(to top, rgba(0,0,0,0.8), transparent); padding: 12px; display: flex; align-items: center; justify-content: space-between; gap: 8px; pointer-events: none; }
        .frame-name { color: #fff; font-size: 14px; font-weight: 500; text-shadow: 1px 1px 2px rgba(0,0,0,0.7); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .frame-item .gear-icon-wrapper { pointer-events: auto; }
        
        
       /* allow popup menus to overflow the grid and not be clipped */
.field-section,
.frames-grid,
.special-grid,
#pswp-composite-gallery {
  overflow: visible !important;
}

/* allow the menu to escape the frame item */
.frame-item {
  overflow: visible !important;
}

/* ensure the image visually stays clipped (rounded corners) */
.frame-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  border-radius: 8px; /* keep same radius as .frame-item */
}

/* make sure gear menu has very high stacking order */
.gear-menu, .gear-popup, .gear-menu-popup {
  position: absolute;
  z-index: 99999 !important;
}

        /* NEW: Styles for Collapsible Section */
        .collapsible-toggle {
            background-color: var(--card);
            color: var(--text);
            border: 1px solid rgba(var(--muted-border-rgb), 0.2);
            padding: 10px 16px;
            width: 100%;
            text-align: left;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            gap: 12px;
            align-items: center;
            font-size: 15px;
            transition: background-color 0.2s;
        }
        .collapsible-toggle:hover {
            background-color: rgba(var(--muted-border-rgb), 0.1);
        }
        .collapsible-toggle .toggle-indicator {
            display: inline-block;
            width: 20px;
            text-align: center;
            font-weight: 700;
            font-size: 1.2em;
            transition: transform 0.2s ease-in-out;
        }
        .collapsible-toggle[aria-expanded="true"] .toggle-indicator {
            transform: rotate(45deg);
        }
        .collapsible-content {
            padding-top: 24px;
            margin-top: -1px; /* Overlap border */
            border-top: 1px solid rgba(var(--muted-border-rgb), 0.2);
        }
        
        
        /* Special source images grid (forced 3 columns to match frames-grid) */
.special-grid { display: grid; grid-template-columns: repeat(3, 1fr) !important; gap: 16px; margin-top: 8px; }
.special-grid .frame-item { aspect-ratio: 1 / 1; border-radius: 8px; overflow: hidden; background-color: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.1); box-shadow: var(--card-elevation); }
.special-grid .frame-item img { width:100%; height:100%; object-fit:cover; display:block; transition: transform 0.25s; }
.special-grid .frame-item:hover img { transform: scale(1.03); }
.special-grid .frame-item.empty { visibility: hidden; pointer-events: none; }
        
    </style>
</head>
<body>
    <?php
    echo $gearMenu->render();
    echo $imageEditor->render();
    ?>
    <div class="container">
        <div class="header" style="display:none;">
        </div>

        <?php if ($notification): ?>
            <div class="notification notification-<?= $notificationType ?>"><?= htmlspecialchars($notification) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <div style="position: absolute; right: 50px;" class="badge badge-blue">
                <?php echo $entityType; ?>
            </div>
            <form style="margin-top: 10px;"  action="entity_form.php?entity_type=<?= urlencode($entityType) ?>&entity_id=<?= $entityId ?>&redirect_url=<?= urlencode($redirectUrl) ?>" method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= $entityId ?>">

                <?php if (!empty($alwaysVisibleFields)): ?>
                <div class="field-section">
                    <div class="field-section-header" style="display:none;">
                        <a href="<?= htmlspecialchars($redirectUrl) ?>" class="btn btn-secondary">&larr; Back</a>
                    </div>
                    <?php foreach ($alwaysVisibleFields as $field): 
                        $value = $entity[$field['name']] ?? '';
                        $fieldId = 'field_' . $field['name'];
                    ?>
                        <div class="form-group">
                            <label for="<?= $fieldId ?>" class="form-label">
                                <?= htmlspecialchars($field['label']) ?>
                                <?php if ($field['required']): ?><span style="color: var(--red);">*</span><?php endif; ?>
                            </label>
                            
                            <?php if ($field['type'] === 'textarea'): ?>
                                <textarea id="<?= $fieldId ?>" name="<?= htmlspecialchars($field['name']) ?>" class="form-control <?= $field['readonly'] ? 'readonly-field' : '' ?>" rows="<?= $field['rows'] ?>" <?= $field['required'] ? 'required' : '' ?> <?= $field['readonly'] ? 'readonly' : '' ?>><?= htmlspecialchars($value) ?></textarea>
                            <?php elseif ($field['type'] === 'checkbox'): ?>
                                <div class="form-check">
                                    <input type="checkbox" id="<?= $fieldId ?>" name="<?= htmlspecialchars($field['name']) ?>" class="form-check-input" value="1" <?= $value ? 'checked' : '' ?> <?= $field['readonly'] ? 'disabled' : '' ?>>
                                </div>
                            <?php else: ?>
                                <input type="<?= htmlspecialchars($field['type']) ?>" id="<?= $fieldId ?>" name="<?= htmlspecialchars($field['name']) ?>" class="form-control <?= $field['readonly'] ? 'readonly-field' : '' ?>" value="<?= htmlspecialchars($value) ?>" <?= $field['placeholder'] ? 'placeholder="' . htmlspecialchars($field['placeholder']) . '"' : '' ?> <?= $field['required'] ? 'required' : '' ?> <?= $field['readonly'] ? 'readonly' : '' ?>>
                            <?php endif; ?>
                            
                            <?php if ($field['help']): ?><div class="form-help"><?= htmlspecialchars($field['help']) ?></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($collapsibleFields) || !empty($systemFields)): ?>
                <div class="field-section">
                     <button type="button" class="collapsible-toggle" id="details-toggle" aria-expanded="false" aria-controls="details-content">
                        <span class="toggle-indicator">+</span>
                        <span class="toggle-text">Show Advanced &amp; System Details</span>
                    </button>
                    <div class="collapsible-content" id="details-content" style="display: none;">
                        <?php if (!empty($collapsibleFields)): ?>
                            <div class="field-section-header">Advanced Settings</div>
                            <?php foreach ($collapsibleFields as $field): 
                                $value = $entity[$field['name']] ?? ''; $fieldId = 'field_' . $field['name']; ?>
                                <div class="form-group">
                                    <label for="<?= $fieldId ?>" class="form-label"><?= htmlspecialchars($field['label']) ?><?php if ($field['required']): ?><span style="color: var(--red);">*</span><?php endif; ?></label>
                                    <?php if ($field['type'] === 'textarea'): ?>
                                        <textarea id="<?= $fieldId ?>" name="<?= htmlspecialchars($field['name']) ?>" class="form-control <?= $field['readonly'] ? 'readonly-field' : '' ?>" rows="<?= $field['rows'] ?>" <?= $field['required'] ? 'required' : '' ?> <?= $field['readonly'] ? 'readonly' : '' ?>><?= htmlspecialchars($value) ?></textarea>
                                    <?php elseif ($field['type'] === 'checkbox'): ?>
                                        <div class="form-check">
                                            <input type="checkbox" id="<?= $fieldId ?>" name="<?= htmlspecialchars($field['name']) ?>" class="form-check-input" value="1" <?= $value ? 'checked' : '' ?> <?= $field['readonly'] ? 'disabled' : '' ?>>
                                            <label for="<?= $fieldId ?>"><?= htmlspecialchars($field['help']) ?></label>
                                        </div>
                                    <?php else: ?>
                                        <input type="<?= htmlspecialchars($field['type']) ?>" id="<?= $fieldId ?>" name="<?= htmlspecialchars($field['name']) ?>" class="form-control <?= $field['readonly'] ? 'readonly-field' : '' ?>" value="<?= htmlspecialchars($value) ?>" <?= $field['placeholder'] ? 'placeholder="' . htmlspecialchars($field['placeholder']) . '"' : '' ?> <?= $field['required'] ? 'required' : '' ?> <?= $field['readonly'] ? 'readonly' : '' ?>>
                                    <?php endif; ?>
                                    <?php if ($field['help']): ?><div class="form-help"><?= htmlspecialchars($field['help']) ?></div><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (!empty($systemFields)): ?>
                            <div class="field-section-header" style="margin-top: 32px;">System Information (Read-Only)</div>
                            <div class="form-group-inline">
                                <?php foreach ($systemFields as $field): 
                                    $value = $entity[$field['name']] ?? ''; $fieldId = 'field_' . $field['name'];
                                    if (in_array($field['name'], ['created_at', 'updated_at']) && $value) { $value = date('Y-m-d H:i:s', strtotime($value)); }
                                ?>
                                    <div class="form-group"><label for="<?= $fieldId ?>" class="form-label"><?= htmlspecialchars($field['label']) ?></label><input type="text" id="<?= $fieldId ?>" class="form-control readonly-field" value="<?= htmlspecialchars($value) ?>" readonly></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-actions" style="margin-top: 10px;">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="<?= htmlspecialchars($redirectUrl) ?>" class="btn btn-secondary">Back</a>
                </div>
            </form>
        </div>
        
        
        
        
        
<?php if (!empty($specialFrames)): ?>
    <div class="field-section">
        <div class="field-section-header">Source Images (img2img / CN Map)</div>
        <div class="special-grid pswp-gallery" id="pswp-special-gallery">
            <?php foreach (['img2img', 'cnmap'] as $k):
                if (!isset($specialFrames[$k])) continue;
                $f = $specialFrames[$k];
                $frameId = (int)$f['id'];
                $frameName = htmlspecialchars($f['name'] ?: ucfirst($k));
                $frameFile = '/' . ltrim($f['filename'] ?? '', '/');
                $badge = $k === 'img2img' ? 'IMG2IMG' : 'CNMAP';
            ?>
            <div class="frame-item img-wrapper" data-frame-id="<?= $frameId ?>" data-entity="<?= htmlspecialchars($entityType) ?>" data-entity-id="<?= $entityId ?>" data-type="<?= $k ?>">
                <a href="<?= htmlspecialchars($frameFile) ?>" class="pswp-gallery-item" data-pswp-src="<?= htmlspecialchars($frameFile) ?>" data-pswp-width="1024" data-pswp-height="1024" title="<?= $frameName ?>">
                    <img src="<?= htmlspecialchars($frameFile) ?>" alt="<?= $frameName ?>" loading="lazy">
                </a>
                <div class="frame-overlay">
                    <div class="frame-name"><?= $frameName ?> <small style="opacity:.85">— <?= $badge ?></small></div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php
            // keep 3 columns visually consistent by adding invisible placeholders
            $count = count($specialFrames);
            for ($i = 0; $i < max(0, 3 - $count); $i++): ?>
                <div class="frame-item empty" aria-hidden="true"></div>
            <?php endfor; ?>
        </div>
    </div>
<?php endif; ?>
        
        
<?php if (!empty($compositeFrames)): ?>
    <div class="field-section">
        <div class="field-section-header">Composite Frames (<?= count($compositeFrames) ?>)</div>
        <div class="frames-grid pswp-gallery" id="pswp-composite-gallery">
            <?php foreach ($compositeFrames as $f):
                $frameId = (int)$f['id'];
                $frameName = htmlspecialchars($f['name'] ?: 'Frame ' . $frameId);
                $frameFile = '/' . ltrim($f['filename'] ?? '', '/');
            ?>
            <div class="frame-item img-wrapper" data-frame-id="<?= $frameId ?>" data-entity="<?= htmlspecialchars($entityType) ?>" data-entity-id="<?= $entityId ?>" data-type="composite">
                <a href="<?= htmlspecialchars($frameFile) ?>" class="pswp-gallery-item" data-pswp-src="<?= htmlspecialchars($frameFile) ?>" data-pswp-width="1024" data-pswp-height="1024" title="<?= $frameName ?>">
                    <img src="<?= htmlspecialchars($frameFile) ?>" alt="<?= $frameName ?>" loading="lazy">
                </a>
                <div class="frame-overlay">
                    <div class="frame-name"><?= $frameName ?> <small style="opacity:.85">— composite</small></div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php
            // keep layout consistent with 3 columns by adding invisible placeholders
            $count = count($compositeFrames);
            for ($i = 0; $i < max(0, 3 - $count); $i++): ?>
                <div class="frame-item empty" aria-hidden="true"></div>
            <?php endfor; ?>
        </div>
    </div>
<?php endif; ?>



        

        <?php if (!empty($frames)): ?>
        <div class="field-section">
            <div class="field-section-header">Frames (<?= count($frames) ?>)</div>
            <div class="frames-grid pswp-gallery" id="pswp-gallery">
                <?php foreach ($frames as $frame):
                    $frameId = (int)$frame['id']; $frameName = htmlspecialchars($frame['name'] ?? 'Frame ' . $frameId); $frameFile = '/' . ltrim($frame['filename'] ?? '', '/');
                ?>
                <div class="frame-item img-wrapper" data-frame-id="<?= $frameId ?>" data-entity="<?= htmlspecialchars($entityType) ?>" data-entity-id="<?= $entityId ?>">
                    <a href="<?= htmlspecialchars($frameFile) ?>" class="pswp-gallery-item" data-pswp-src="<?= htmlspecialchars($frameFile) ?>" data-pswp-width="1024" data-pswp-height="1024" title="<?= $frameName ?>">
                        <img src="<?= htmlspecialchars($frameFile) ?>" alt="<?= $frameName ?>" loading="lazy">
                    </a>
                    <div class="frame-overlay"><div class="frame-name"><?= $frameName ?></div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <script>
    $(document).ready(function() {
        // --- NEW: Collapsible Section Logic ---
        const $toggleBtn = $('#details-toggle');
        const $content = $('#details-content');
        const storageKey = `spw_entity_form_collapse_state:<?= $entityType ?>:<?= $entityId ?>`;

        // Function to update the button's appearance
        function updateToggleState(isExpanded) {
            $toggleBtn.attr('aria-expanded', isExpanded);
            $toggleBtn.find('.toggle-indicator').text(isExpanded ? '−' : '+');
            $toggleBtn.find('.toggle-text').text(isExpanded ? 'Hide Advanced & System Details' : 'Show Advanced & System Details');
            if(isExpanded) {
                $toggleBtn.find('.toggle-indicator').css('transform', 'rotate(0deg)');
            }
        }

        // Set initial state from localStorage
        try {
            const savedState = localStorage.getItem(storageKey);
            if (savedState === 'expanded') {
                $content.show();
                updateToggleState(true);
            } else {
                updateToggleState(false);
            }
        } catch (e) { /* ignore */ }
        
        // Handle click event
        $toggleBtn.on('click', function() {
            const isExpanded = $(this).attr('aria-expanded') === 'true';
            const newState = !isExpanded;
            
            $content.slideToggle(250);
            updateToggleState(newState);
            
            try {
                localStorage.setItem(storageKey, newState ? 'expanded' : 'collapsed');
            } catch (e) { /* ignore */ }
        });

        
        
        
        
        /*
        // Initialize Gear Menu
        if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
            const grid = document.querySelector('.frames-grid');
            if (grid) { window.GearMenu.attach(grid); }
        }
        */
        
        
        

        // Initialize PhotoSwipe
        try {
            const lightbox = new PhotoSwipeLightbox({ gallery: '#pswp-gallery', children: '.pswp-gallery-item', pswpModule: PhotoSwipe });
            lightbox.init();
        } catch(e) { console.warn('PhotoSwipe could not be initialized.', e); }

        // Enhance deleteFrame for instant UI feedback
        if (typeof window.deleteFrame === 'function') {
            const originalDeleteFrame = window.deleteFrame;
            window.deleteFrame = function(entity, entityId, frameId, wrapper) {
                originalDeleteFrame(entity, entityId, frameId);
                $(document).one('toast-show', function(e, message, type) {
                    if (type === 'success' && wrapper) {
                        $(wrapper).fadeOut(400, function() { $(this).remove(); });
                    }
                });
            };
        }
        
        
        // initialize gearmenu on special grid if present
        /*
const specialGrid = document.querySelector('.special-grid');
if (specialGrid && window.GearMenu && typeof window.GearMenu.attach === 'function') {
    window.GearMenu.attach(specialGrid);
}
*/


// PhotoSwipe for the special gallery (separate instance)
try {
    const specialLightbox = new PhotoSwipeLightbox({ gallery: '#pswp-special-gallery', children: '.pswp-gallery-item', pswpModule: PhotoSwipe });
    specialLightbox.init();
} catch(e) { console.warn('PhotoSwipe (special gallery) init failed', e); }
        
        
        
        
        
        
       // composite grid: GearMenu attach + PhotoSwipe lightbox
const compositeGrid = document.querySelector('#pswp-composite-gallery');
if (compositeGrid) {
    /*
    if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
        window.GearMenu.attach(compositeGrid);
    }
    */
    try {
        const compositeLightbox = new PhotoSwipeLightbox({ gallery: '#pswp-composite-gallery', children: '.pswp-gallery-item', pswpModule: PhotoSwipe });
        compositeLightbox.init();
    } catch (e) { console.warn('PhotoSwipe (composite gallery) init failed', e); }
}


        
        
        
        
       // Initialize Gear Menu on all relevant grids
if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
    // Attach to every frames-grid (covers composite + frames)
    document.querySelectorAll('.frames-grid').forEach(function(grid) {
        try { window.GearMenu.attach(grid); } catch (e) { console.warn('GearMenu.attach failed for frames-grid', e); }
    });

    // Attach to special grid (if present)
    document.querySelectorAll('.special-grid').forEach(function(sg) {
        try { window.GearMenu.attach(sg); } catch (e) { console.warn('GearMenu.attach failed for special-grid', e); }
    });

    // If you still want to explicitly attach composite/gallery by id, it's harmless but optional
    const compositeGallery = document.querySelector('#pswp-composite-gallery');
    if (compositeGallery) {
        try { window.GearMenu.attach(compositeGallery); } catch (e) { /* already attached or failed */ }
    }
}
        
        
        
        
        
    });
    </script>

<?php echo $eruda; ?>
</body>
</html>
