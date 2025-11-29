<?php
// public/view_testing_modular.php - Demo of modular system
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\UI\Modules\ModuleRegistry;
use App\UI\Modules\GearMenuModule;
use App\UI\Modules\ImageEditorModule;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// Get module registry
$registry = ModuleRegistry::getInstance();

// Configure gear menu module
$gearMenu = $registry->create('gear_menu', [
    'position' => 'top-right',
    'icon' => '&#9881;',
    'icon_size' => '2em',
    'global_actions' => true,
]);

// Add custom actions for different entity types
$gearMenu->addAction('generatives', [
    'label' => 'Import to Generative',
    'icon' => '⚡',
    'callback' => 'window.importGenerative(entity, entityId, frameId);'
]);

$gearMenu->addAction('generatives', [
    'label' => 'Edit Image',
    'icon' => '✏️',
    'callback' => <<<'JS'
const $w = $(wrapper);
ImageEditorModal.open({
    entity: entity,
    entityId: entityId,
    frameId: frameId,
    src: $w.find('img').attr('src')
});
JS
]);

$gearMenu->addAction('generatives', [
    'label' => 'Add to Storyboard',
    'icon' => '🎬',
    'callback' => 'window.selectStoryboard(frameId, $(wrapper));'
]);

// Configure image editor module
$imageEditor = $registry->create('image_editor', [
    'modes' => ['mask', 'crop'],
    'show_transform_tab' => true,
    'show_filters_tab' => true,
    'enable_rotate' => true,
    'enable_resize' => true,
    'preset_filters' => ['grayscale', 'blur', 'sharpen', 'vintage'],
]);

$pageTitle = "Modular UI Testing";
ob_start();
?>

<link rel="stylesheet" href="css/toast.css">
<link rel="stylesheet" href="css/storyboard_submenu.css">
<script src="js/toast.js"></script>
<script src="js/gear_menu_globals.js"></script>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>
<?php else: ?>
    <link rel="stylesheet" href="/vendor/cropper/cropper.min.css" />
    <script src="/vendor/cropper/cropper.min.js"></script>
<?php endif; ?>

<style>
.testing-wrap { max-width:1100px; margin:0 auto; padding:18px; }
.testing-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap; }
.testing-head h2 { margin:0; font-weight:600; font-size:1.15rem; }
.btn { display:inline-block; padding:8px 10px; border-radius:6px; text-decoration:none; font-size:0.9rem; border:1px solid rgba(0,0,0,0.06); background:#fff; color:#222; cursor:pointer; }
.btn-primary { background:#0d6efd; color:#fff; border-color:#0d6efd; }
.btn-outline-primary { background:transparent; border:1px solid #0d6efd; color:#0d6efd; }
.btn-success { background:#198754; color:#fff; border-color:#198754; }
.btn-sm { padding:6px 8px; font-size:0.85rem; border-radius:6px; }
.card { background:#fff; border-radius:8px; border:1px solid rgba(0,0,0,0.06); margin-bottom:16px; }
.card-header { padding:12px 16px; border-bottom:1px solid rgba(0,0,0,0.06); font-weight:600; }
.card-body { padding:16px; }
.small-muted { color:#666; font-size:0.85rem; }
.grid { display:grid; gap:12px; }
.grid-3 { grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); }

.demo-image-item {
    position: relative;
    background: #f8f9fa;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.demo-image-item img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    display: block;
}

.demo-caption {
    padding: 10px;
    font-size: 0.9rem;
    background: white;
}

@media (max-width:700px) {
  .testing-head { gap:8px; }
  .grid-3 { grid-template-columns: 1fr; }
}
</style>

<?php
// Render modules
echo $gearMenu->render();
echo $imageEditor->render();
?>

<div class="testing-wrap">
  <div class="testing-head">
    <h2>Modular UI Testing - Gear Menu & Image Editor</h2>
    <div style="display:flex;gap:8px;align-items:center;">
      <button class="btn btn-primary btn-sm" onclick="testImageEditor()">Test Image Editor</button>
      <button class="btn btn-success btn-sm" onclick="showSuccessToast()">Test Toast</button>
    </div>
  </div>

  <p class="small-muted">This demonstrates the new modular system. Each component (GearMenu, ImageEditor) is self-contained and can be plugged into any view.</p>

  <!-- Demo Gallery Grid with Gear Icons -->
  <div class="card">
    <div class="card-header">Demo Gallery - Hover over images to see gear menu</div>
    <div class="card-body">
      <div class="grid grid-3" id="demoGallery">
        <!-- Demo items will be populated here -->
      </div>
    </div>
  </div>

  <!-- Architecture Notes -->
  <div class="card">
    <div class="card-header">Modular Architecture</div>
    <div class="card-body">
      <h4 style="margin-top:0;">Key Components:</h4>
      <ul>
        <li><strong>GearMenuModule</strong> - Standalone gear menu system with configurable actions</li>
        <li><strong>ImageEditorModule</strong> - Complete image editor modal with PyAPI integration</li>
        <li><strong>ModuleRegistry</strong> - Central registry for managing all UI modules</li>
      </ul>
      
      <h4>Benefits:</h4>
      <ul>
        <li>✅ No dependencies on AbstractGallery</li>
        <li>✅ Each module is self-contained (CSS + JS + HTML)</li>
        <li>✅ Plug-and-play into any view</li>
        <li>✅ Configurable through constructor options</li>
        <li>✅ Can be extended with custom actions</li>
      </ul>
      
      <h4>Usage Example:</h4>
      <pre style="background:#f8f9fa; padding:12px; border-radius:6px; overflow:auto;"><code><?php echo htmlspecialchars(<<<'CODE'
use App\UI\Modules\ModuleRegistry;

$registry = ModuleRegistry::getInstance();

// Create gear menu with custom config
$gearMenu = $registry->create('gear_menu', [
    'position' => 'top-right',
    'icon' => '&#9881;',
]);

// Add custom action
$gearMenu->addAction('generatives', [
    'label' => '✏️ Edit Image',
    'callback' => 'ImageEditorModal.open({...});'
]);

// Render in view
echo $gearMenu->render();
CODE
); ?></code></pre>
    </div>
  </div>
</div>

<script>
(function() {
    'use strict';
    
    // Create demo gallery items
    function populateDemoGallery() {
        const gallery = document.getElementById('demoGallery');
        if (!gallery) return;
        
        const demoItems = [
            { id: 1, entity: 'generatives', entityId: 101, frameId: 1001, url: 'https://picsum.photos/400/300?random=1' },
            { id: 2, entity: 'generatives', entityId: 102, frameId: 1002, url: 'https://picsum.photos/400/300?random=2' },
            { id: 3, entity: 'generatives', entityId: 103, frameId: 1003, url: 'https://picsum.photos/400/300?random=3' },
            { id: 4, entity: 'generatives', entityId: 104, frameId: 1004, url: 'https://picsum.photos/400/300?random=4' },
            { id: 5, entity: 'generatives', entityId: 105, frameId: 1005, url: 'https://picsum.photos/400/300?random=5' },
            { id: 6, entity: 'generatives', entityId: 106, frameId: 1006, url: 'https://picsum.photos/400/300?random=6' },
        ];
        
        demoItems.forEach(item => {
            const wrapper = document.createElement('div');
            wrapper.className = 'demo-image-item';
            wrapper.dataset.entity = item.entity;
            wrapper.dataset.entityId = item.entityId;
            wrapper.dataset.frameId = item.frameId;
            
            // Note: Don't add gear-icon here - the GearMenu module will inject it
            wrapper.innerHTML = `
                <img src="${item.url}" alt="Demo ${item.id}">
                <div class="demo-caption">
                    <strong>Demo Item ${item.id}</strong><br>
                    <span style="font-size:0.85rem;color:#666;">Frame ID: ${item.frameId}</span>
                </div>
            `;
            
            gallery.appendChild(wrapper);
        });
        
        // Attach gear menus
        if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
            window.GearMenu.attach(gallery);
        }
    }
    
    window.testImageEditor = function() {
        if (typeof ImageEditorModal === 'undefined') {
            Toast.show('ImageEditorModal not loaded', 'error');
            return;
        }
        
        ImageEditorModal.open({
            entity: 'generatives',
            entityId: 999,
            frameId: 9999,
            src: 'https://picsum.photos/800/600?random=test'
        });
    };
    
    window.showSuccessToast = function() {
        if (typeof Toast !== 'undefined' && Toast.show) {
            Toast.show('Modular system working!', 'success');
        }
    };
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', populateDemoGallery);
    } else {
        populateDemoGallery();
    }
})();
</script>

<?php
require "floatool.php";
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle);
