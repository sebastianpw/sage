<?php
// view_multiplane.php
require "error_reporting.php";
require "eruda_var.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();

global $pdo;
if (!isset($pdo)) { $pdo = $spw->db; }

// 1. Validate Input
$compositeId = isset($_GET['composite_id']) ? (int)$_GET['composite_id'] : 0;
if ($compositeId === 0) die("Error: No composite_id provided.");

// 2. Fetch Composite Info
$stmtComp = $pdo->prepare("SELECT * FROM composites WHERE id = ?");
$stmtComp->execute([$compositeId]);
$composite = $stmtComp->fetch(PDO::FETCH_ASSOC);
if (!$composite) die("Error: Composite not found.");

$pageTitle = "Multiplane: " . htmlspecialchars($composite['name']);

// 3. Fetch Assigned Frames
$sqlFrames = "
    SELECT f.id, f.filename, f.name 
    FROM composite_frames cf
    JOIN frames f ON cf.frame_id = f.id
    WHERE cf.composite_id = ?
    ORDER BY cf.created_at ASC
";
$stmtFrames = $pdo->prepare($sqlFrames);
$stmtFrames->execute([$compositeId]);
$frames = $stmtFrames->fetchAll(PDO::FETCH_ASSOC);

// 4. Fetch Latest Arrangement
$sqlArr = "SELECT * FROM multiplane_arrangements WHERE composite_id = ? ORDER BY updated_at DESC LIMIT 1";
$stmtArr = $pdo->prepare($sqlArr);
$stmtArr->execute([$compositeId]);
$latestArrangement = $stmtArr->fetch(PDO::FETCH_ASSOC);

$jsFrames = json_encode($frames);
$jsInitialConfig = $latestArrangement ? $latestArrangement['layer_config'] : 'null';
$jsInitialArrangementId = $latestArrangement ? $latestArrangement['id'] : 'null';

ob_start();
?>

<!-- Viewport -->
<meta name="viewport" content="width=device-width, initial-scale=0.5, maximum-scale=1.0, user-scalable=yes">

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
<?php else: ?>
  <link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css" />
<?php endif; ?>

<script src="https://unpkg.com/konva@9.3.3/konva.min.js"></script>

<link rel="stylesheet" href="/css/base.css" />

<style>
    body, html { margin: 0; padding: 0; width: 100%; height: 100%; background-color: #2c2c2c; overflow: hidden; font-family: sans-serif; }
    #viewport-wrapper { width: 100vw; height: 100vh; display: flex; justify-content: center; align-items: center; overflow: auto; }
    #canvas-container {
        width: 1024px; height: 1024px; background-color: #fff;
        background-image: conic-gradient(#ccc 90deg, #fff 90deg 180deg, #ccc 180deg 270deg, #fff 270deg);
        background-size: 40px 40px; box-shadow: 0 0 20px rgba(0,0,0,0.5); transform-origin: center center;
    }
    #gear-btn {
        position: fixed; top: 20px; right: 20px; width: 50px; height: 50px;
        background: var(--accent, #007bff); color: white; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 24px; cursor: pointer; z-index: 1000; box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        transition: transform 0.2s;
    }
    #gear-btn:hover { transform: rotate(90deg); }
    .context-menu {
        display: none; position: absolute; background: white; border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2); z-index: 1001; overflow: hidden; min-width: 220px;
    }
    .context-menu-item {
        padding: 12px 20px; cursor: pointer; font-size: 14px; color: #333;
        border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px;
    }
    .context-menu-item:hover { background: #f0f0f0; }
    #layer-controls {
        display: none; position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
        background: rgba(0,0,0,0.8); padding: 10px 20px; border-radius: 30px; z-index: 900; gap: 15px;
    }
    #layer-controls button { background: transparent; border: none; color: white; font-size: 18px; cursor: pointer; padding: 5px; }
    #layer-controls button:hover { color: var(--accent, #007bff); }
    .list-overlay {
        display: none; position: fixed; top:0; left:0; right:0; bottom:0;
        background: rgba(0,0,0,0.8); z-index: 2000; justify-content: center; align-items: center;
    }
    .list-content { background: white; width: 90%; max-width: 400px; border-radius: 8px; padding: 20px; max-height: 80vh; overflow-y: auto; }
    .list-item { padding: 10px; border-bottom: 1px solid #eee; cursor: pointer; display: flex; justify-content: space-between; }
    .list-item:hover { background: #f9f9f9; }
    #status-toast {
        display: none; position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
        background: #333; color: white; padding: 10px 20px; border-radius: 4px; z-index: 3000;
    }
    #loading-overlay {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); z-index: 4000; align-items: center; justify-content: center; color: white; flex-direction: column;
    }
    
    /* Settings Forms Styles */
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 12px; color: #555; }
    .form-control { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
    .btn-save { background: #28a745; color: white; border: none; padding: 10px; width: 100%; border-radius: 4px; cursor: pointer; margin-top: 10px; }
    .btn-save:hover { background: #218838; }
    .btn-action { background: #007bff; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 11px; margin-top:5px; }
</style>

<div id="viewport-wrapper"><div id="canvas-container"></div></div>
<div id="status-toast"></div>
<div id="loading-overlay"><i class="fa fa-spinner fa-spin fa-3x"></i><br>Processing...</div>
<div id="gear-btn"><i class="fa fa-cog"></i></div>

<!-- Layer Controls -->
<div id="layer-controls">
    <button id="btn-layer-up"><i class="fa fa-arrow-up"></i></button>
    <button id="btn-layer-down"><i class="fa fa-arrow-down"></i></button>
    <button id="btn-layer-top"><i class="fa fa-angles-up"></i></button>
    <button id="btn-layer-bottom"><i class="fa fa-angles-down"></i></button>
    <span style="border-right:1px solid #666"></span>
    <button id="btn-layer-reset"><i class="fa fa-compress"></i></button>
    <span style="border-right:1px solid #666"></span>
    <button id="btn-layer-settings" title="Video Layer Settings"><i class="fa fa-sliders"></i></button>
</div>

<!-- Load Arrangement Modal -->
<div id="load-modal" class="list-overlay">
    <div class="list-content">
        <h3>Load Arrangement</h3>
        <div id="arrangement-list">Loading...</div>
        <button class="btn btn-secondary" style="width:100%; margin-top:10px;" onclick="$('#load-modal').hide()">Cancel</button>
    </div>
</div>

<!-- Global Video Settings Modal -->
<div id="video-settings-modal" class="list-overlay">
    <div class="list-content">
        <h3>Multiplane Video Settings</h3>
        <form id="video-settings-form" onsubmit="return false;">
            
            <div class="form-group" style="background: #e8f5e9; padding: 10px; border-radius: 4px;">
                <label>Parallax Focal Distance (m)</label>
                <input type="number" step="0.1" id="vs-focal-dist" class="form-control" value="10.0">
                <small style="color:#444;">Distance where movement matches camera speed.</small>
            </div>

            <div class="form-group" style="background: #e3f2fd; padding: 10px; border-radius: 4px; margin-top: 5px;">
                <label>Scene View Height (m)</label>
                <input type="number" step="0.1" id="vs-frustum-height" class="form-control" value="10.0">
                <small style="color:#444;">Vertical size of the world visible at the Focal Distance.</small>
            </div>

            <div class="form-group" style="background: #fff3e0; padding: 10px; border-radius: 4px; margin-top: 5px;">
                <label>Scale Reference (m) (Fallback)</label>
                <input type="number" step="0.1" id="vs-scale-ref" class="form-control" value="10.0">
                <small style="color:#666;">Used if Real Height is not set.</small>
            </div>

            <div class="form-group">
                <label>Camera Zoom (Magnification)</label>
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label style="font-size:10px;">Start (1.0 = 100%)</label>
                        <input type="number" step="0.01" id="vs-zoom-start" class="form-control" value="1.0">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:10px;">End</label>
                        <input type="number" step="0.01" id="vs-zoom-end" class="form-control" value="1.05">
                    </div>
                </div>
                <small style="color:#800; font-weight:bold;">Do NOT set to 0 (Invisible).</small>
            </div>

            <div class="form-group">
                <label>Camera Movement (Pixels)</label>
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label style="font-size:10px;">Move X (Pan)</label>
                        <input type="number" id="vs-move-x" class="form-control" value="100">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:10px;">Move Y (Tilt)</label>
                        <input type="number" id="vs-move-y" class="form-control" value="0">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label>Total Frames</label>
                        <input type="number" id="vs-frames" class="form-control" value="60">
                    </div>
                    <div style="flex:1;">
                        <label>FPS</label>
                        <input type="number" id="vs-fps" class="form-control" value="30">
                    </div>
                </div>
            </div>

            <button class="btn-save" id="btn-save-video-settings">Save Video Settings</button>
        </form>
        <button class="btn btn-secondary" style="width:100%; margin-top:10px;" onclick="$('#video-settings-modal').hide()">Cancel</button>
    </div>
</div>

<!-- Layer Settings Modal -->
<div id="layer-settings-modal" class="list-overlay">
    <div class="list-content">
        <h3>Layer Video Attributes</h3>
        <p style="font-size:12px; color:#666; margin-bottom:15px;">Physics calculation.</p>
        
        <form id="layer-settings-form" onsubmit="return false;">
            <input type="hidden" id="ls-frame-id">
            
            <div class="form-group" style="background: #f0f8ff; padding: 10px; border-radius: 4px; border: 1px solid #cce5ff;">
                <label><i class="fa fa-ruler-horizontal"></i> Layer Distance (meters)</label>
                <input type="number" step="0.1" id="ls-distance" class="form-control" value="10.0">
                
                <label style="margin-top:10px;"><i class="fa fa-ruler-vertical"></i> Real Height (meters)</label>
                <input type="number" step="0.01" id="ls-real-height" class="form-control" placeholder="e.g. 1.8 for Human">
                
                <button type="button" id="btn-calc-scale" class="btn-action">
                    <i class="fa fa-calculator"></i> Calculate & Apply Physics
                </button>
            </div>

            <div class="form-group">
                <label>Parallax Speed</label>
                <input type="number" step="0.001" id="ls-speed" class="form-control" value="1.0">
                <small style="color:#888;">Auto-calculated from Distance.</small>
            </div>
            
            <div class="form-group">
                <label>Z-Index (Order Override)</label>
                <input type="number" id="ls-zindex" class="form-control" value="0">
            </div>
            
            <button class="btn-save" id="btn-save-layer-settings">Save Layer Attributes</button>
        </form>
        <button class="btn btn-secondary" style="width:100%; margin-top:10px;" onclick="$('#layer-settings-modal').hide()">Cancel</button>
    </div>
</div>

<?= $spw->getJquery() ?>

<script>
$(document).ready(function() {
    if (typeof Konva === 'undefined') { alert("Konva JS not loaded."); return; }

    const STAGE_W = 1024, STAGE_H = 1024;
    const COMPOSITE_ID = <?php echo $compositeId; ?>;
    const FRAMES = <?php echo $jsFrames; ?>; 
    let activeArrId = <?php echo $jsInitialArrangementId; ?>;
    let loadedConfig = <?php echo $jsInitialConfig; ?>; 
    
    // Default Globals
    let GLOBAL_FOCAL_DIST = 10.0;
    let GLOBAL_SCALE_REF = 10.0;
    let GLOBAL_FRUSTUM_HEIGHT = 10.0;

    const stage = new Konva.Stage({ container: 'canvas-container', width: STAGE_W, height: STAGE_H });
    const layer = new Konva.Layer();
    stage.add(layer);
    const tr = new Konva.Transformer({ nodes: [], keepRatio: true, boundBoxFunc: (o, n) => (n.width < 5 || n.height < 5) ? o : n });
    layer.add(tr);

    function loadGlobalSettings() {
        $.get('multiplane_api.php', { action:'get_video_settings', composite_id: COMPOSITE_ID }, r => {
            if(r.success) {
                GLOBAL_FOCAL_DIST = parseFloat(r.data.focal_distance) || 10.0;
                GLOBAL_SCALE_REF = parseFloat(r.data.scale_reference) || 10.0;
                GLOBAL_FRUSTUM_HEIGHT = parseFloat(r.data.frustum_height) || 10.0;
            }
        }, 'json');
    }
    loadGlobalSettings();

    function getNeutralConfig() {
        let c = {};
        FRAMES.forEach((f, i) => { c[f.id] = { x: 100+(i*30), y: 100+(i*30), scaleX: 1, scaleY: 1, rotation: 0, zIndex: i }; });
        return c;
    }

    function loadImages(config) {
        layer.find('.layer-image').forEach(n => n.destroy());
        tr.nodes([]);
        const useConfig = config || getNeutralConfig();
        let loaded = 0;
        
        FRAMES.forEach(f => {
            const img = new Image();
            img.src = '/' + f.filename;
            img.onload = () => {
                const c = useConfig[f.id] || { x: 50, y: 50, scaleX: 1, scaleY: 1, rotation: 0 };
                const kImg = new Konva.Image({
                    x: c.x, y: c.y, scaleX: c.scaleX, scaleY: c.scaleY, rotation: c.rotation,
                    image: img, width: img.width, height: img.height, draggable: true,
                    name: 'layer-image', id: 'frame_'+f.id, frameId: f.id, filename: f.filename
                });
                kImg.on('mouseover', () => document.body.style.cursor='pointer');
                kImg.on('mouseout', () => document.body.style.cursor='default');
                layer.add(kImg);
                loaded++;
                if(loaded === FRAMES.length) {
                    const imgs = layer.find('.layer-image');
                    imgs.sort((a,b) => {
                        const za = (useConfig[a.attrs.frameId]?.zIndex ?? 0);
                        const zb = (useConfig[b.attrs.frameId]?.zIndex ?? 0);
                        return za - zb;
                    });
                    imgs.forEach((im, idx) => im.zIndex(idx));
                    tr.moveToTop();
                }
            };
        });
    }

    loadImages(loadedConfig);

    stage.on('click tap', e => {
        if(e.target === stage) { tr.nodes([]); $('#layer-controls').fadeOut(); return; }
        if(!e.target.hasName('layer-image')) return;
        tr.nodes([e.target]);
        $('#layer-controls').css('display','flex').hide().fadeIn();
    });

    const getSel = () => tr.nodes()[0];
    $('#btn-layer-up').click(() => { if(getSel()) { getSel().moveUp(); tr.moveToTop(); }});
    $('#btn-layer-down').click(() => { if(getSel()) getSel().moveDown(); });
    $('#btn-layer-top').click(() => { if(getSel()) { getSel().moveToTop(); tr.moveToTop(); }});
    $('#btn-layer-bottom').click(() => { if(getSel()) getSel().moveToBottom(); });
    $('#btn-layer-reset').click(() => { if(getSel()) { getSel().scale({x:1, y:1}); getSel().rotation(0); }});
    
    // --- LAYER SETTINGS ---
    $('#btn-layer-settings').click(() => {
        const sel = getSel();
        if(!sel) return;
        const fid = sel.attrs.frameId;
        
        $('#layer-settings-form')[0].reset();
        $('#ls-frame-id').val(fid);
        $('#ls-zindex').val(sel.zIndex());

        $.get('multiplane_api.php', { action:'get_layer_settings', composite_id: COMPOSITE_ID, frame_id: fid }, r => {
            if(r.success) {
                $('#ls-speed').val(r.data.speed);
                $('#ls-distance').val(r.data.distance || GLOBAL_FOCAL_DIST);
                $('#ls-real-height').val(r.data.real_height || '');
            }
            $('#layer-settings-modal').css('display','flex');
        }, 'json');
    });

    $('#ls-distance').on('input', function() {
        const dist = parseFloat($(this).val());
        if(dist && dist > 0) {
            const speed = GLOBAL_FOCAL_DIST / dist;
            $('#ls-speed').val(speed.toFixed(4));
        }
    });

    $('#btn-calc-scale').click(function() {
        const dist = parseFloat($('#ls-distance').val());
        const realH = parseFloat($('#ls-real-height').val());
        const sel = getSel();
        if(!sel || !dist || dist <= 0) return;
        
        let newScale = 1.0;
        if(realH && realH > 0) {
            const frustumAtDist = GLOBAL_FRUSTUM_HEIGHT * (dist / GLOBAL_FOCAL_DIST);
            const fractionOfScreen = realH / frustumAtDist;
            const nativeH = sel.height(); 
            const targetPxH = fractionOfScreen * STAGE_H;
            newScale = targetPxH / nativeH;
            showToast("Scale calibrated: " + realH + "m");
        } else {
            newScale = GLOBAL_SCALE_REF / dist;
            showToast("Scale calibrated (Fallback)");
        }
        sel.scaleX(newScale); sel.scaleY(newScale);
        layer.batchDraw();
    });

    $('#btn-save-layer-settings').click(() => {
        const fid = $('#ls-frame-id').val();
        const newZ = parseInt($('#ls-zindex').val());

        $.post('multiplane_api.php', { 
            action: 'save_layer_settings', composite_id: COMPOSITE_ID, frame_id: fid,
            speed: $('#ls-speed').val(), z_index: newZ,
            distance: $('#ls-distance').val(), real_height: $('#ls-real-height').val()
        }, r => {
            if(r.success) {
                const sel = layer.findOne(node => node.attrs.frameId == fid);
                if(sel) { sel.zIndex(newZ); tr.forceUpdate(); layer.batchDraw(); }
                showToast("Layer attributes saved!");
                $('#layer-settings-modal').hide();
            } else { alert(r.message); }
        }, 'json');
    });

    // --- VIDEO SETTINGS (Fix Logic: Treat 0 as 1.0) ---
    function openVideoSettings() {
        $.get('multiplane_api.php', { action:'get_video_settings', composite_id: COMPOSITE_ID }, r => {
            if(r.success) {
                $('#vs-frames').val(r.data.frames);
                $('#vs-fps').val(r.data.fps);
                $('#vs-move-x').val(r.data.move_x);
                $('#vs-move-y').val(r.data.move_y);
                
                // IF DB HAS 0 (CRASH), LOAD 1.0 (SAFE)
                $('#vs-zoom-start').val((parseFloat(r.data.zoom_start) || 1.0));
                $('#vs-zoom-end').val((parseFloat(r.data.zoom_end) || 1.0));
                
                $('#vs-focal-dist').val(r.data.focal_distance || 10.0);
                $('#vs-scale-ref').val(r.data.scale_reference || 10.0);
                $('#vs-frustum-height').val(r.data.frustum_height || 10.0);
                
                GLOBAL_FOCAL_DIST = parseFloat(r.data.focal_distance) || 10.0;
                GLOBAL_SCALE_REF = parseFloat(r.data.scale_reference) || 10.0;
                GLOBAL_FRUSTUM_HEIGHT = parseFloat(r.data.frustum_height) || 10.0;
                
                $('#video-settings-modal').css('display','flex');
            } else { alert("Failed to load settings."); }
        }, 'json');
    }

    $('#btn-save-video-settings').click(() => {
        const data = {
            action: 'save_video_settings', composite_id: COMPOSITE_ID,
            frames: $('#vs-frames').val(), fps: $('#vs-fps').val(),
            move_x: $('#vs-move-x').val(), move_y: $('#vs-move-y').val(),
            zoom_start: $('#vs-zoom-start').val(), zoom_end: $('#vs-zoom-end').val(),
            focal_distance: $('#vs-focal-dist').val(), scale_reference: $('#vs-scale-ref').val(),
            frustum_height: $('#vs-frustum-height').val()
        };
        $.post('multiplane_api.php', data, r => {
            if(r.success) {
                $('#video-settings-modal').hide(); // Close First
                GLOBAL_FOCAL_DIST = parseFloat(data.focal_distance);
                GLOBAL_SCALE_REF = parseFloat(data.scale_reference);
                GLOBAL_FRUSTUM_HEIGHT = parseFloat(data.frustum_height);
                showToast("Video settings saved!");
            } else { alert(r.message); }
        }, 'json');
    });

    function fitStage() {
        const c = document.querySelector('#viewport-wrapper');
        if(!c) return;
        const s = Math.min(c.offsetWidth/STAGE_W, c.offsetHeight/STAGE_H) * 0.95;
        document.querySelector('#canvas-container').style.transform = `scale(${s})`;
    }
    window.addEventListener('resize', fitStage);
    setTimeout(fitStage, 200);

    // API & Menu
    function showToast(m) { $('#status-toast').text(m).fadeIn().delay(2000).fadeOut(); }
    
    function getConfig() {
        let c = {};
        layer.find('.layer-image').forEach(n => {
            c[n.attrs.frameId] = { x: Math.round(n.x()), y: Math.round(n.y()), scaleX: n.scaleX(), scaleY: n.scaleY(), rotation: n.rotation(), zIndex: n.zIndex() };
        });
        return c;
    }
    
    function getRenderPayload() {
        let arr = [];
        layer.find('.layer-image').forEach(n => {
            arr.push({
                filename: n.attrs.filename, x: n.x(), y: n.y(),
                width: n.width(), height: n.height(), 
                scaleX: n.scaleX(), scaleY: n.scaleY(),
                rotation: n.rotation(), zIndex: n.zIndex()
            });
        });
        return arr;
    }

    const $gear = $('#gear-btn');
    let $menu = null;
    
    $gear.click(function(e) {
        e.stopPropagation();
        if($menu) { closeMenu(); return; }
        
        $menu = $('<div class="context-menu"></div>');
        
        const iNew = $('<div class="context-menu-item"><i class="fa fa-file"></i> New Arrangement</div>').click(() => {
            if(confirm("Discard changes?")) { activeArrId=null; loadImages(null); showToast("Reset neutral"); closeMenu(); }
        });

        // SAVE
        const iSave = $('<div class="context-menu-item"><i class="fa fa-save"></i> Save Arrangement</div>').click(() => {
            const cfg = getConfig();
            $.post('multiplane_api.php', { action:'save', id:activeArrId, composite_id:COMPOSITE_ID, name:'Arrangement', config:JSON.stringify(cfg) }, r => {
                if(r.success) { activeArrId=r.id; showToast("Saved!"); } else alert(r.message);
            }, 'json');
            closeMenu();
        });

        // COPY
        const iCopy = $('<div class="context-menu-item"><i class="fa fa-copy"></i> Copy Arrangement</div>').click(() => {
            closeMenu();
            let name = prompt("Name for the copy:", "Arrangement Copy");
            if(name === null) return;
            const cfg = getConfig();
            $.post('multiplane_api.php', { action:'save', id: null, composite_id:COMPOSITE_ID, name:name, config:JSON.stringify(cfg) }, r => {
                if(r.success) { activeArrId = r.id; showToast("Arrangement Copied!"); } else alert(r.message);
            }, 'json');
        });

        // LOAD
        const iLoad = $('<div class="context-menu-item"><i class="fa fa-folder-open"></i> Load Arrangement</div>').click(() => {
            closeMenu();
            $('#load-modal').css('display','flex');
            $('#arrangement-list').html('Loading...');
            $.get('multiplane_api.php', { action:'list', composite_id:COMPOSITE_ID }, r => {
                const l = $('#arrangement-list').empty();
                if(!r.data || !r.data.length) l.html('No saves.');
                else r.data.forEach(d => {
                    const row = $(`<div class="list-item"><div><strong>${d.name}</strong><br><small>${new Date(d.updated_at).toLocaleString()}</small></div></div>`);
                    row.click(() => {
                        $.get('multiplane_api.php', { action:'load', id:d.id }, ld => {
                            if(ld.success) { activeArrId=ld.data.id; loadedConfig=JSON.parse(ld.data.layer_config); loadImages(loadedConfig); showToast("Loaded"); }
                            $('#load-modal').hide();
                        }, 'json');
                    });
                    l.append(row);
                });
            }, 'json');
        });
        
        // VIDEO SETTINGS
        const iSettings = $('<div class="context-menu-item" style="border-top:1px solid #eee"><i class="fa fa-video"></i> Video Settings</div>').click(() => {
            closeMenu();
            openVideoSettings();
        });

        const iReset = $('<div class="context-menu-item"><i class="fa fa-rotate-left"></i> Reset View</div>').click(() => {
            loadImages(activeArrId ? loadedConfig : null);
            showToast("Reset");
            closeMenu();
        });
        
        // EXPORT
        const iExport = $('<div class="context-menu-item" style="border-top:2px solid #eee; color: #d32f2f"><i class="fa fa-image"></i> Export to New Frame</div>').click(() => {
            closeMenu();
            if(!confirm("Render this arrangement to a new high-quality Frame?")) return;
            
            $('#loading-overlay').css('display','flex');
            const payload = getRenderPayload();
            
            $.post('multiplane_api.php', {
                action: 'export_render',
                composite_id: COMPOSITE_ID,
                layers: JSON.stringify(payload)
            }, function(res) {
                $('#loading-overlay').hide();
                if(res.success) {
                    showToast("Rendered! Frame ID: " + res.frame_id);
                } else {
                    alert("Render failed: " + res.message);
                }
            }, 'json').fail(function(xhr) {
                $('#loading-overlay').hide();
                alert("Server Error: " + xhr.responseText);
            });
        });

        $menu.append(iNew, iSave, iCopy, iLoad, iSettings, iReset, iExport);
        $('body').append($menu);
        const r = this.getBoundingClientRect();
        $menu.css({ top: r.bottom+10, left: r.right-220 }).show();
    });

    function closeMenu() { if($menu) { $menu.remove(); $menu=null; } }
    $(document).on('click', e => { if($menu && !$(e.target).closest('#gear-btn').length && !$(e.target).closest('.context-menu').length) closeMenu(); });

});
</script>

<?php 
$content = ob_get_clean();
$content .= $eruda ?? '';
$spw->renderLayout($content, $pageTitle);
?>