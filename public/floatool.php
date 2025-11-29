<?php
// floatool.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
$dbname = $spw->getDbName();

// Fetch active generators for the user
use App\Entity\GeneratorConfig;
$em = $spw->getEntityManager();
$userId = $_SESSION['user_id'] ?? null;

$repo = $em->getRepository(App\Entity\GeneratorConfig::class);
$generators = [];

if ($userId) {
    // 1. Start building the query
    $qb = $repo->createQueryBuilder('g'); // 'g' is an alias for GeneratorConfig

    // 2. Join the displayAreas relationship and give it an alias 'da'
    $qb->join('g.displayAreas', 'da')

       // 3. Add WHERE clauses for the conditions
       ->where('g.userId = :userId')
       ->andWhere('g.active = :isActive')
       ->andWhere('da.areaKey = :areaKey') // Filter on the JOINED table's column

       // 4. Set the parameters to prevent SQL injection
       ->setParameter('userId', $userId)
       ->setParameter('isActive', true)
       ->setParameter('areaKey', 'floatool') // The area you're looking for

       // 5. Set the ordering
       ->orderBy('g.title', 'ASC');

    // 6. Execute the query and get the results
    $generators = $qb->getQuery()->getResult();
}


// --- DYNAMIC LAB MENU ---

// Master list of entity icons
$entityIcons = [
    'characters'      => '🦸',
//    'character_poses' => '🤸',
    'animas'          => '🐾',
    'locations'       => '🗺️',
    'backgrounds'     => '🏞️',
    'artifacts'       => '🏺',
    'vehicles'        => '🛸',
//    'scene_parts'     => '🎬',
//    'controlnet_maps' => '☠️',
//    'spawns'          => '🌱',
    'generatives'     => '⚡',
    'sketches'        => '🪄',
//    'prompt_matrix_blueprints' => '🌌',
    'composites'      => '🧩',
//    'pastebin'        => '📋',
//    'sage_todos'      => '🎫',
//    'meta_entities'   => '📦'
];

// Dynamically build the lab menu entities from the master list
$labEntities = [];
foreach ($entityIcons as $type => $icon) {
    // Create a user-friendly name from the entity type string
    $name = ucwords(str_replace('_', ' ', $type));
    $labEntities[] = [
        'type' => $type,
        'name' => $name,
        'icon' => $icon,
    ];
}
?>

<!-- Floating Toolbar -->
<div id="floatool" class="floatool collapsed" aria-hidden="false">
    <div class="floatool-handle">☰</div>
    <div class="floatool-buttons">
        <button data-action="open-dashboard">🧭</button>
        <button data-action="open-database">🛢️</button>
        <button data-action="open-styles">🎨</button>
        <button data-action="open-regen">♻️</button>
        <button data-action="open-chat">💬</button>
        <button data-action="open-generators">⚗️</button>
        <button data-action="open-lab">💫</button>
        <button data-action="open-other">🌀</button>
        <button data-action="open-logs">📓️</button>
    </div>
</div>

<!-- Gear flyout menu -->
<div id="floatoolGearMenu" class="floatool-gear-menu" style="display:none;">
    <a class="floatool-menu-header" href="scheduler_view.php">🌀 Scheduler</a>
    <a href="#" class="scheduler" data-id="10" onclick="runScheduler(this)">🌀 run ⚡ now</a>
    <a href="#" class="scheduler" data-id="15" onclick="runScheduler(this)">🌀 run 🪄 now</a>
    <a href="#" class="scheduler" data-id="23" onclick="runScheduler(this)">🌀 run 🌌 now</a>
    <a href="#" class="scheduler" data-id="24" onclick="runScheduler(this)">🌀 run 🧩 now</a>
    <a href="#" class="scheduler" data-id="20" onclick="runScheduler(this)">🌀 run ☠️ now</a>
    <a href="#" class="scheduler" data-id="11" onclick="runScheduler(this)">🌀 run 🦸 now</a>
    <a href="#" class="scheduler" data-id="19" onclick="runScheduler(this)">🌀 run 🤸 now</a>
    <a href="#" class="scheduler" data-id="12" onclick="runScheduler(this)">🌀 run 🐾 now</a>
    <a href="#" class="scheduler" data-id="13" onclick="runScheduler(this)">🌀 run 🗺️ now</a>
    <a href="#" class="scheduler" data-id="16" onclick="runScheduler(this)">🌀 run 🏞️ now</a>
    <a href="#" class="scheduler" data-id="18" onclick="runScheduler(this)">🌀 run 🏺 now</a>
    <a href="#" class="scheduler" data-id="17" onclick="runScheduler(this)">🌀 run 🛸 now</a>
    <a href="#" class="scheduler" data-id="" onclick="runScheduler(this)">🌀 run 🎬 now</a>
</div>

<!-- Generator flyout menu -->
<div id="floatoolGeneratorMenu" class="floatool-gear-menu" style="display:none;">
    <a class="floatool-menu-header" href="#">⚗️ Quick Generators</a>
    <?php if (empty($generators)): ?>
        <a class="floatool-menu-item-disabled" href="#">No active generators</a>
        <a href="generator_admin.php">+ Create Generator</a>
    <?php else: ?>
        <?php foreach ($generators as $gen): ?>
            <a href="#"
               class="generator-quick-run"
               data-config-id="<?=htmlspecialchars($gen->getConfigId())?>"
               data-title="<?=htmlspecialchars($gen->getTitle())?>"
               onclick="openGeneratorDialog(this); return false;">
                <?=htmlspecialchars($gen->getTitle())?>
            </a>
        <?php endforeach; ?>
        <a class="floatool-menu-separator-top" href="generator_admin.php">🤖 Generator Admin</a>
    <?php endif; ?>
</div>

<!-- Lab flyout menu -->
<div id="floatoolLabMenu" class="floatool-gear-menu" style="display:none;">
    <a class="floatool-menu-header" href="#">💫 Rapid Create</a>
    <?php foreach ($labEntities as $entity): ?>
        <a href="#"
           class="lab-entity-link"
           data-entity-type="<?= htmlspecialchars($entity['type']) ?>"
           onclick="openEntityForm(this); return false;">
            <?= htmlspecialchars($entity['icon']) ?> <?= htmlspecialchars($entity['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Generator Dialog Modal -->
<div id="generatorDialogOverlay" class="generator-dialog-overlay" style="display:none;">
    <div class="generator-dialog">
        <div class="generator-dialog-header">
            <h3 id="generatorDialogTitle">Generator</h3>
            <button class="generator-dialog-close" onclick="closeGeneratorDialog()">✖</button>
        </div>
        <div class="generator-dialog-body">
            <div id="generatorDialogParams" class="generator-form-params"></div>
            <div class="generator-dialog-actions">
                <button class="btn-secondary" onclick="closeGeneratorDialog()">Cancel</button>
                <button class="btn-primary" onclick="runGenerator()">⚗️ Generate</button>
            </div>
        </div>
        <div id="generatorDialogResult" class="generator-dialog-result" style="display:none;">
            <div class="result-header">
                <strong>Result:</strong>
                <div class="result-actions">
                    <button class="btn-copy" onclick="copyGeneratorResult()">📋 Copy</button>
                    <button class="btn-fill" onclick="fillTargetField()" style="display:none;">✏️ Fill Field</button>
                </div>
            </div>
            <pre id="generatorResultContent"></pre>
        </div>
        <div id="generatorDialogLoading" class="generator-loading" style="display:none;">
            <div class="spinner"></div>
            <p>Generating...</p>
        </div>
    </div>
</div>


<style>
    /*
      Theme-aware floatool styles (Final Version)
      This CSS now "follows" the data-theme set by the main application.
    */

    :root {
        /* Define shared font stacks */
        --font-stack: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        --emoji-fonts: "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";

        /*
           RULE 1: Light theme is the default fallback.
           These variables are used if no other rule matches.
        */
        --float-bg: #ffffff;
        --float-border: #d1d5db;
        --float-text: #111827;
        --float-muted: #6b7280;
        --float-hover: #f3f4f6;
        --float-shadow: 0 2px 8px rgba(0,0,0,0.12);
        --float-btn-bg: #f8f9fa;
        --accent: #2563eb;
        --float-overlay-bg: rgba(20, 20, 20, 0.7);
    }

    /*
       RULE 2: Explicit Dark Mode.
       This is the most important rule. If the <html> tag has data-theme="dark"
       (set by your dashboard's head script), these variables will be used.
       This has higher specificity than :root, so it wins.
    */
    html[data-theme="dark"] {
        --float-bg: #0f1724;
        --float-border: #1f2937;
        --float-text: #cbd5e1;
        --float-muted: #94a3b8;
        --float-hover: #111827;
        --float-shadow: 0 6px 18px rgba(0,0,0,0.6);
        --float-btn-bg: #0b1220;
        --accent: #3b82f6;
        --float-overlay-bg: rgba(0, 0, 0, 0.8);
    }

    /*
       RULE 3: System Preference Dark Mode.
       This rule applies ONLY if the user's OS is dark AND no explicit
       data-theme has been set. This covers the initial visit before
       a user has toggled the theme.
    */
    @media (prefers-color-scheme: dark) {
      :root:not([data-theme]) {
        --float-bg: #0f1724;
        --float-border: #1f2937;
        --float-text: #cbd5e1;
        --float-muted: #94a3b8;
        --float-hover: #111827;
        --float-shadow: 0 6px 18px rgba(0,0,0,0.6);
        --float-btn-bg: #0b1220;
        --accent: #3b82f6;
        --float-overlay-bg: rgba(0, 0, 0, 0.8);
      }
    }

    #floatool {
        opacity: 0.8;
        position: fixed; bottom: 20px; right: 20px; background: var(--float-bg); border: 1px solid var(--float-border); border-radius: 12px; box-shadow: var(--float-shadow); z-index: 9999; cursor: grab; transition: width 0.18s ease, height 0.18s ease, background .12s ease, border-color .12s ease; overflow: hidden; font-size: 180%; color: var(--float-text); font-family: var(--emoji-fonts), var(--font-stack); -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;
    }
    .floatool.collapsed { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
    .floatool.expanded { width: auto; height: auto; padding: 6px; display: flex; align-items: center; gap: 6px; }
    .floatool-handle { cursor: pointer; user-select: none; transition: transform 0.18s ease; font-size: 18px; line-height: 1; }
    .floatool.collapsed .floatool-handle { cursor: pointer; }
    .floatool.expanded .floatool-handle { transform: rotate(90deg); cursor: pointer; }
    .floatool.collapsed .floatool-buttons { display: none; }
    .floatool.expanded .floatool-buttons { display: flex; flex-wrap: wrap; }
    .floatool button { font-size: 0.9em; border: none; background: var(--float-btn-bg); padding: 4px; border-radius: 4px; cursor: pointer; transition: background 0.12s, color 0.12s, transform 0.12s; margin: 0; color: var(--float-text); font-family: var(--emoji-fonts), var(--font-stack); display: inline-flex; align-items: center; justify-content: center; line-height: 1; min-width: 36px; min-height: 36px; }
    .floatool button:hover { background: var(--float-hover); transform: translateY(-1px); }
    .floatool-gear-menu { position: fixed; display: flex; flex-direction: column; gap: 6px; z-index: 10000; padding: 6px 0; background: var(--float-bg); border: 1px solid var(--float-border); border-radius: 10px; box-shadow: var(--float-shadow); max-height: 70vh; overflow-y: auto; }
    .floatool-gear-menu a { padding: 10px 14px; text-decoration: none; color: var(--float-text); font-weight: 700; white-space: nowrap; transition: background 0.12s; font-size: 14px; font-family: var(--font-stack); }
    .floatool-gear-menu a:hover { background: var(--float-hover); }
    .floatool-menu-header { border-bottom: 1px solid var(--float-border); font-weight: bold; pointer-events: none; }
    .floatool-menu-item-disabled { color: var(--float-muted) !important; font-style: italic; pointer-events: none; }
    .floatool-menu-separator-top { border-top: 1px solid var(--float-border); margin-top: 4px; }
    
    /* Generator Dialog Modal - Improved Styling */
    .generator-dialog-overlay { 
        position: fixed; 
        inset: 0; 
        background: var(--float-overlay-bg); 
        z-index: 10001; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        padding: 12px; 
    }
    
    .generator-dialog { 
        background: var(--float-bg); 
        border-radius: 10px; 
        max-width: 600px; 
        width: 100%; 
        max-height: 90vh; 
        overflow-y: auto; 
        box-shadow: 0 8px 30px rgba(2,6,23,0.35); 
        color: var(--float-text); 
        font-family: var(--font-stack); 
        border: 1px solid rgba(var(--muted-border-rgb), 0.06);
    }
    
    .generator-dialog-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        padding: 16px 20px; 
        border-bottom: 1px solid var(--float-border); 
        position: sticky; 
        top: 0; 
        background: var(--float-bg); 
        z-index: 1; 
    }
    
    .generator-dialog-header h3 { 
        margin: 0; 
        font-size: 1.1rem; 
        font-weight: 600;
        overflow: hidden; 
        text-overflow: ellipsis; 
        white-space: nowrap; 
        flex: 1; 
        padding-right: 10px; 
        font-family: var(--font-stack); 
    }
    
    .generator-dialog-close { 
        background: none; 
        border: none; 
        font-size: 22px; 
        cursor: pointer; 
        color: var(--float-muted); 
        padding: 0 8px; 
        flex-shrink: 0; 
        transition: color 0.15s;
    }
    .generator-dialog-close:hover { color: var(--float-text); }
    
    .generator-dialog-body { padding: 20px; }
    
    /* Improved form styling matching generator admin */
    .generator-form-params { display: grid; gap: 16px; margin-bottom: 20px; }
    
    .form-group { margin-bottom: 0; }
    
    .generator-form-params label { 
        display: block; 
        margin-bottom: 6px; 
        font-weight: 600; 
        font-size: 0.85rem; 
        color: var(--float-muted); 
    }
    
    .generator-form-params input, 
    .generator-form-params select, 
    .generator-form-params textarea { 
        width: 100%; 
        padding: 10px 12px; 
        border: 1px solid rgba(var(--muted-border-rgb), 0.12); 
        border-radius: 6px; 
        font-size: 0.9rem; 
        font-family: var(--font-stack); 
        background: var(--float-btn-bg); 
        color: var(--float-text); 
        transition: border-color 0.15s ease;
    }
    
    .generator-form-params input:focus,
    .generator-form-params select:focus,
    .generator-form-params textarea:focus {
        outline: none;
        border-color: var(--accent);
    }
    
    .generator-form-params textarea { 
        min-height: 100px; 
        resize: vertical; 
        font-family: ui-monospace, monospace; 
        font-size: 0.85rem; 
    }
    
    .generator-dialog-actions { 
        display: flex; 
        gap: 10px; 
        justify-content: flex-end; 
        flex-wrap: wrap; 
    }
    
    .btn-primary, .btn-secondary, .btn-copy, .btn-fill { 
        padding: 10px 16px; 
        border-radius: 6px; 
        border: none; 
        cursor: pointer; 
        font-weight: 600; 
        font-size: 0.9rem; 
        touch-action: manipulation; 
        font-family: var(--font-stack); 
        transition: all 0.15s ease;
    }
    
    .btn-primary { 
        background: var(--accent); 
        color: white; 
        flex: 1; 
        min-width: 120px; 
    }
    .btn-primary:hover { filter: brightness(1.1); }
    
    .btn-secondary { 
        background: var(--float-btn-bg); 
        color: var(--float-text); 
        flex: 1; 
        min-width: 100px; 
        border: 1px solid rgba(var(--muted-border-rgb), 0.12);
    }
    .btn-secondary:hover { filter: brightness(0.98); }
    
    .btn-copy, .btn-fill { 
        background: #10b981; 
        color: white; 
        padding: 8px 12px; 
        font-size: 0.85rem; 
    }
    .btn-copy:hover, .btn-fill:hover { background: #059669; }
    .btn-fill { background: #f59e0b; }
    .btn-fill:hover { background: #d97706; }
    
    .generator-dialog-result { 
        padding: 20px; 
        border-top: 1px solid var(--float-border); 
        background: var(--float-bg); 
    }
    
    .result-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 12px; 
        gap: 10px; 
        flex-wrap: wrap; 
    }
    
    .result-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    
    .generator-dialog-result pre { 
        background: var(--float-btn-bg); 
        padding: 12px; 
        border-radius: 6px; 
        border: 1px solid rgba(var(--muted-border-rgb), 0.12); 
        max-height: 300px; 
        overflow: auto; 
        font-size: 0.8rem; 
        margin: 0; 
        word-break: break-word; 
        white-space: pre-wrap; 
        color: var(--float-text); 
        font-family: ui-monospace, monospace; 
    }
    
    .generator-loading { padding: 40px; text-align: center; color: var(--float-muted); }
    .spinner { 
        width: 40px; 
        height: 40px; 
        margin: 0 auto 16px; 
        border: 4px solid rgba(var(--muted-border-rgb), 0.2); 
        border-top-color: var(--accent); 
        border-radius: 50%; 
        animation: spin 0.8s linear infinite; 
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    
    @media (max-width: 768px) { 
        #floatool { bottom: 15px; right: 15px; font-size: 150%; } 
        .floatool.expanded { flex-wrap: wrap; max-width: calc(100vw - 30px); } 
        .floatool-gear-menu { left: 50% !important; transform: translateX(-50%); max-width: 90vw; bottom: 70px; top: auto !important; } 
        .generator-dialog { max-height: 95vh; margin: 0; border-radius: 10px 10px 0 0; } 
        .generator-dialog-header h3 { font-size: 1rem; } 
        .generator-dialog-body { padding: 16px; } 
        .generator-dialog-actions { flex-direction: column-reverse; } 
        .btn-primary, .btn-secondary { width: 100%; min-width: 0; } 
    }
    
    @media (max-width: 480px) { 
        .generator-dialog-overlay { padding: 0; } 
        .generator-dialog { border-radius: 10px 10px 0 0; max-height: 100vh; } 
        .floatool-gear-menu a { padding: 12px 16px; font-size: 16px; } 
    }
</style>


<!-- The rest of your scripts remain unchanged -->

<script>
(function() {
    const floatool = document.getElementById('floatool');
    const handle = floatool.querySelector('.floatool-handle');
    const gearMenu = document.getElementById('floatoolGearMenu');
    const generatorMenu = document.getElementById('floatoolGeneratorMenu');
    const labMenu = document.getElementById('floatoolLabMenu');
    const mobileGenButton = document.getElementById('mobileGeneratorButton');
    let isDragging = false, offsetX, offsetY;
    let currentTargetField = null;
    let focusTimeout = null;

    const savedPos = localStorage.getItem('spw-floatool-pos');
    if(savedPos){
        try {
            const {left, top, right, bottom} = JSON.parse(savedPos);
            if(left) floatool.style.left = left;
            if(top) floatool.style.top = top;
            if(right) floatool.style.right = right;
            if(bottom) floatool.style.bottom = bottom;
        } catch(e){}
    }

    const savedCollapsed = localStorage.getItem('spw-floatool-collapsed');
    if(savedCollapsed !== null){
        if(savedCollapsed === '1'){
            floatool.classList.add('collapsed');
            floatool.classList.remove('expanded');
        } else {
            floatool.classList.add('expanded');
            floatool.classList.remove('collapsed');
        }
    }

    handle.addEventListener('mousedown', startDrag);
    handle.addEventListener('touchstart', startDrag, { passive: false });

    function startDrag(e){
        e.preventDefault();
        isDragging = false;
        const rect = floatool.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        offsetX = clientX - rect.left;
        offsetY = clientY - rect.top;
        window.floatoolDragStart = { x: clientX, y: clientY, time: Date.now() };
    }

    document.addEventListener('mousemove', drag);
    document.addEventListener('touchmove', drag, { passive: false });

    function drag(e){
        if(!window.floatoolDragStart) return;
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        const dx = Math.abs(clientX - window.floatoolDragStart.x);
        const dy = Math.abs(clientY - window.floatoolDragStart.y);
        if (dx > 5 || dy > 5) {
            isDragging = true;
            floatool.style.cursor = "grabbing";
        }
        if(!isDragging) return;
        e.preventDefault();
        const x = clientX - offsetX;
        const y = clientY - offsetY;
        floatool.style.left = x + "px";
        floatool.style.top = y + "px";
        floatool.style.right = "auto";
        floatool.style.bottom = "auto";
        if(gearMenu.style.display === 'flex') updateGearMenuPosition();
        if(generatorMenu.style.display === 'flex') updateGeneratorMenuPosition();
        if(labMenu.style.display === 'flex') updateLabMenuPosition();
    }

    document.addEventListener('mouseup', endDrag);
    document.addEventListener('touchend', endDrag);

    function endDrag(e){
        if(!window.floatoolDragStart) return;
        const wasDragging = isDragging;
        const dragTime = Date.now() - window.floatoolDragStart.time;
        if (!wasDragging && dragTime < 300) {
            toggleFloatool();
        }
        if(wasDragging) {
            floatool.style.cursor = "grab";
            localStorage.setItem('spw-floatool-pos', JSON.stringify({
                left: floatool.style.left,
                top: floatool.style.top,
                right: floatool.style.right,
                bottom: floatool.style.bottom
            }));
        }
        isDragging = false;
        window.floatoolDragStart = null;
    }

    function toggleFloatool() {
        const isCollapsed = floatool.classList.toggle('collapsed');
        floatool.classList.toggle('expanded', !isCollapsed);
        localStorage.setItem('spw-floatool-collapsed', isCollapsed ? '1' : '0');
    }

    floatool.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const action = btn.getAttribute('data-action');
            if(action==='open-dashboard')
                window.location.href='dashboard.php';
            else if(action==='open-database')
                window.location.href="/dbtool.php"
            else if(action==='open-regen')
                /*window.location.href="regenerate_frames_set.php"*/
                window.showRegenerateFramesModal();
            else if(action==='open-styles') window.toggleStylesModal?.();
            else if(action==='open-logs') window.toggleLogsModal?.();
            else if(action==='open-chat')
                window.location.href='chat.php';
            else if(action==='open-generators') {
                e.stopPropagation();
                gearMenu.style.display = 'none';
                labMenu.style.display = 'none';
                if(generatorMenu.style.display === 'flex'){
                    generatorMenu.style.display = 'none';
                } else {
                    updateGeneratorMenuPosition();
                    generatorMenu.style.display = 'flex';
                }
            }
            else if(action==='open-lab') {
                e.stopPropagation();
                gearMenu.style.display = 'none';
                generatorMenu.style.display = 'none';
                if(labMenu.style.display === 'flex'){
                    labMenu.style.display = 'none';
                } else {
                    updateLabMenuPosition();
                    labMenu.style.display = 'flex';
                }
            }
            else if(action==='open-other') {
                e.stopPropagation();
                generatorMenu.style.display = 'none';
                labMenu.style.display = 'none';
                if(gearMenu.style.display === 'flex'){
                    gearMenu.style.display = 'none';
                } else {
                    updateGearMenuPosition();
                    gearMenu.style.display = 'flex';
                }
            }
        });
    });

    document.addEventListener('click', () => {
        gearMenu.style.display='none';
        generatorMenu.style.display='none';
        labMenu.style.display='none';
    });
    document.addEventListener('touchstart', (e) => {
        if (!e.target.closest('.floatool-gear-menu') && !e.target.closest('#floatool')) {
            gearMenu.style.display='none';
            generatorMenu.style.display='none';
            labMenu.style.display='none';
        }
    });
    gearMenu.addEventListener('click', e=>e.stopPropagation());
    generatorMenu.addEventListener('click', e=>e.stopPropagation());
    labMenu.addEventListener('click', e=>e.stopPropagation());
    gearMenu.addEventListener('touchstart', e=>e.stopPropagation());
    generatorMenu.addEventListener('touchstart', e=>e.stopPropagation());
    labMenu.addEventListener('touchstart', e=>e.stopPropagation());

    function updateGearMenuPosition(){
        const rect = floatool.getBoundingClientRect();
        if (window.innerWidth <= 768) {
            gearMenu.style.left = '50%';
            gearMenu.style.transform = 'translateX(-50%)';
            gearMenu.style.bottom = '70px';
            gearMenu.style.top = 'auto';
        } else {
            gearMenu.style.left = (rect.left + 100) + "px";
            gearMenu.style.top  = (rect.top - gearMenu.offsetHeight - 390) + "px";
            gearMenu.style.transform = 'none';
        }
    }

    function updateGeneratorMenuPosition(){
        const rect = floatool.getBoundingClientRect();
        if (window.innerWidth <= 768) {
            generatorMenu.style.left = '50%';
            generatorMenu.style.transform = 'translateX(-50%)';
            generatorMenu.style.bottom = '70px';
            generatorMenu.style.top = 'auto';
        } else {
            generatorMenu.style.left = (rect.left + 100) + "px";
            generatorMenu.style.top  = (rect.top - 20) + "px";
            generatorMenu.style.transform = 'none';
        }
    }

    function updateLabMenuPosition(){
        const rect = floatool.getBoundingClientRect();
        if (window.innerWidth <= 768) {
            labMenu.style.left = '50%';
            labMenu.style.transform = 'translateX(-50%)';
            labMenu.style.bottom = '70px';
            labMenu.style.top = 'auto';
        } else {
            labMenu.style.left = (rect.left + 100) + "px";
            labMenu.style.top  = (rect.top - 20) + "px";
            labMenu.style.transform = 'none';
        }
    }

    window.openEntityForm = function(element) {
        const entityType = element.getAttribute('data-entity-type');
        if (!entityType) {
            console.error('No data-entity-type attribute found on the element.');
            return;
        }
        const url = `/entity_gen.php?entity_type=${encodeURIComponent(entityType)}`;
        labMenu.style.display = 'none';
        window.location.href = url;
    };

    document.addEventListener('focusin', (e) => {
        const target = e.target;
        if (target.matches('input[type="text"], textarea, input:not([type="button"]):not([type="submit"]):not([type="checkbox"]):not([type="radio"])')) {
            currentTargetField = target;
            clearTimeout(focusTimeout);
            focusTimeout = setTimeout(() => {
                if (mobileGenButton) mobileGenButton.style.display = 'block';
            }, 300);
        }
    });

    document.addEventListener('focusout', (e) => {
        clearTimeout(focusTimeout);
        focusTimeout = setTimeout(() => {
            if (document.activeElement !== currentTargetField) {
                if (mobileGenButton) mobileGenButton.style.display = 'none';
            }
        }, 200);
    });

    window.showMobileGeneratorMenu = function() {
        updateGeneratorMenuPosition();
        generatorMenu.style.display = 'flex';
    };

    window.openGeneratorDialog = function(element) {
        generatorMenu.style.display = 'none';
        const configId = element.getAttribute('data-config-id');
        const title = element.getAttribute('data-title');
        showGeneratorDialog(configId, title);
    };

    window.closeGeneratorDialog = function() {
        document.getElementById('generatorDialogOverlay').style.display = 'none';
        document.getElementById('generatorDialogResult').style.display = 'none';
        document.getElementById('generatorDialogLoading').style.display = 'none';
        const fillBtn = document.querySelector('.btn-fill');
        if (fillBtn) fillBtn.style.display = 'none';
    };

    window.currentGeneratorConfig = null;

    function showGeneratorDialog(configId, title, targetField = null) {
        const overlay = document.getElementById('generatorDialogOverlay');
        const titleEl = document.getElementById('generatorDialogTitle');
        const paramsEl = document.getElementById('generatorDialogParams');

        titleEl.textContent = title;
        paramsEl.innerHTML = '<p style="color: var(--float-muted); text-align: center; padding: 20px;">Loading parameters...</p>';

        overlay.style.display = 'flex';

        if (!targetField && currentTargetField) {
            targetField = currentTargetField;
        }

        fetch(`/api/generate.php?config_id=${encodeURIComponent(configId)}&_info=1`)
            .then(r => r.json())
            .then(data => {
                if (data.config && data.config.parameters) {
                    window.currentGeneratorConfig = { configId, targetField };
                    renderParameters(data.config.parameters);

                    const fillBtn = document.querySelector('.btn-fill');
                    if (targetField && fillBtn) {
                        fillBtn.style.display = 'inline-block';
                    }
                } else {
                    paramsEl.innerHTML = '<p style="color:var(--float-muted); text-align: center; padding: 20px;">No parameters defined</p>';
                    window.currentGeneratorConfig = { configId, targetField };
                }
            })
            .catch(err => {
                paramsEl.innerHTML = '<p style="color:red; text-align: center; padding: 20px;">Error loading parameters</p>';
            });
    }

    function renderParameters(params) {
        const paramsEl = document.getElementById('generatorDialogParams');
        paramsEl.innerHTML = '';

        for (const [key, def] of Object.entries(params)) {
            const formGroup = document.createElement('div');
            formGroup.className = 'form-group';

            const label = document.createElement('label');
            label.textContent = def.label || key;
            formGroup.appendChild(label);

            let input;
            if (def.type === 'string' && def.enum) {
                input = document.createElement('select');
                input.name = key;
                def.enum.forEach(val => {
                    const opt = document.createElement('option');
                    opt.value = val;
                    opt.textContent = val;
                    if (val === def.default) opt.selected = true;
                    input.appendChild(opt);
                });
            } else if (def.type === 'string' && def.multiline) {
                input = document.createElement('textarea');
                input.name = key;
                input.value = def.default || '';
            } else if (def.type === 'integer' || def.type === 'number') {
                input = document.createElement('input');
                input.type = 'number';
                input.name = key;
                input.value = def.default || 0;
            } else {
                input = document.createElement('input');
                input.type = 'text';
                input.name = key;
                input.value = def.default || '';
            }

            formGroup.appendChild(input);
            paramsEl.appendChild(formGroup);
        }
    }

    window.runGenerator = function() {
        if (!window.currentGeneratorConfig) return;

        const { configId } = window.currentGeneratorConfig;
        const paramsEl = document.getElementById('generatorDialogParams');
        const inputs = paramsEl.querySelectorAll('input, select, textarea');

        const params = { config_id: configId };
        inputs.forEach(input => {
            params[input.name] = input.value;
        });

        document.getElementById('generatorDialogResult').style.display = 'none';
        document.getElementById('generatorDialogLoading').style.display = 'block';

        fetch('/api/generate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(params)
        })
        .then(r => r.json())
        .then(result => {
            document.getElementById('generatorDialogLoading').style.display = 'none';
            document.getElementById('generatorDialogResult').style.display = 'block';

            const resultContent = document.getElementById('generatorResultContent');
            if (result.ok && result.data) {
                resultContent.textContent = JSON.stringify(result.data, null, 2);
                window.currentGeneratorResult = result.data;
            } else {
                resultContent.textContent = 'Error: ' + (result.error || 'Unknown error');
                window.currentGeneratorResult = null;
            }
        })
        .catch(err => {
            document.getElementById('generatorDialogLoading').style.display = 'none';
            alert('Request failed: ' + err.message);
        });
    };

    window.fillTargetField = function() {
        if (!window.currentGeneratorConfig || !window.currentGeneratorConfig.targetField || !window.currentGeneratorResult) {
            alert('No target field or result available');
            return;
        }

        const field = window.currentGeneratorConfig.targetField;
        const firstValue = Object.values(window.currentGeneratorResult)[0];

        if (typeof firstValue === 'string') {
            field.value = firstValue;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
            alert('Field filled!');
        } else {
            alert('Result is not a simple string value');
        }
    };

    window.copyGeneratorResult = function() {
        const content = document.getElementById('generatorResultContent').textContent;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(content).then(() => {
                alert('Copied to clipboard!');
            }).catch(() => {
                fallbackCopy(content);
            });
        } else {
            fallbackCopy(content);
        }
    };

    function fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            alert('Copied to clipboard!');
        } catch (err) {
            alert('Failed to copy');
        }
        document.body.removeChild(textarea);
    }
})();
</script>

<script>
(function(){
    function createModal(id, iframeSrc){
        let modal = $('#' + id);
        if(modal.length) return modal;

        modal = $(`
            <div id="${id}" style="position:fixed;top:0;left:0;width:100%;height:100%;
                background:rgba(0,0,0,0.85);z-index:9999;display:flex;justify-content:center;align-items:center;">
                <div style="width:80%;height:80%;background:#111;padding:20px;position:relative;display:flex;flex-direction:column;">
                    <button class="close-btn" style="position:absolute;top:10px;right:10px;
                        background:#b42318;color:#fff;border:none;padding:5px 10px;cursor:pointer;">✖ Close</button>
                    <iframe src="${iframeSrc}" frameborder="0" style="flex:1;width:100%;background:#000;"></iframe>
                </div>
            </div>
        `).hide();

        modal.find('.close-btn').click(()=>modal.fadeOut());
        $('body').append(modal);
        return modal;
    }

    window.toggleStylesModal = function(){
        const modal = createModal('floatool-styles-modal', 'styles_toggle.php');
        modal.fadeToggle();
    };

    window.toggleLogsModal = function(){
        const modal = createModal('floatool-logs-modal', 'view_scheduler_log.php');
        modal.fadeToggle();
    };
})();

$(document).ready(function() {
    window.runScheduler = function(el) {
        const $btn = $(el);
        const id = $btn.data('id');

        console.log("Run scheduler clicked, id=", id);
        $('#floatoolGearMenu').hide();

        $.post('scheduler_view.php', { action: 'run_now', id: id }, function(res) {
            if (res === 'success') {
                Toast.show('Task scheduled to run now!', 'success');
            } else {
                Toast.show('Failed to trigger task', 'error');
            }
        });
    };
});
</script>

<?php
require __DIR__ . '/modal_frame_details.php';
?>