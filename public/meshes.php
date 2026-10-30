<?php
// public/meshes.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$requestedMeshId = (int)($_GET['mesh_id'] ?? ($_GET['id'] ?? 0));
$animaticId      = (int)($_GET['animatic_id'] ?? 0);
$sketchId        = (int)($_GET['sketch_id'] ?? 0);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>3D Mesh Viewer — SAGE Babylon</title>
    <?= $eruda ?? '' ?>
    <style>
        :root {
            --bg-dark: #0f1015;
            --surface: rgba(22, 24, 32, 0.85);
            --surface-border: rgba(255, 255, 255, 0.1);
            --accent: #2c7be5;
            --accent-hover: #1a68d1;
            --text-main: #f0f3f8;
            --text-muted: #8c96a8;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            height: 100dvh;
            overflow: hidden;
            background: radial-gradient(circle at 50% 35%, #232733 0%, #0d0e12 100%);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--text-main);
            touch-action: none;
        }

        #viewerContainer {
            position: absolute;
            inset: 0;
            width: 100vw;
            height: 100dvh;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #renderCanvas {
            width: 100%;
            height: 100%;
            display: block;
            touch-action: none;
            outline: none;
        }

        #viewerToolbar {
            position: fixed;
            top: 10px;
            left: 10px;
            right: 10px;
            z-index: 50;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: var(--surface);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid var(--surface-border);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45);
            overflow-x: auto;
            scrollbar-width: none;
        }
        #viewerToolbar::-webkit-scrollbar { display: none; }

        #modelSelect {
            flex: 1;
            min-width: 160px;
            max-width: 320px;
            padding: 7px 10px;
            background: rgba(0, 0, 0, 0.4);
            color: var(--text-main);
            border: 1px solid var(--surface-border);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            outline: none;
        }
        #modelSelect option { background: #161820; color: #fff; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--surface-border);
            border-radius: 8px;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.15s ease;
        }
        .btn:active { transform: scale(0.96); background: rgba(255, 255, 255, 0.16); }
        .btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn-primary:active { background: var(--accent-hover); }

        .toolbar-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            white-space: nowrap;
            cursor: pointer;
            padding: 0 4px;
        }
        .toolbar-label input { accent-color: var(--accent); cursor: pointer; }

        #navDrawerWrapper {
            position: fixed;
            bottom: 10px;
            left: 10px;
            right: 10px;
            z-index: 60;
            display: flex;
            flex-direction: column;
            align-items: center;
            pointer-events: none;
        }

        #drawerToggleBtn {
            pointer-events: auto;
            margin-bottom: 6px;
            padding: 6px 16px;
            background: var(--surface);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--surface-border);
            border-radius: 20px;
            color: var(--text-main);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        #navControls {
            pointer-events: auto;
            width: 100%;
            max-width: 900px;
            max-height: 52vh;
            overflow-y: auto;
            background: var(--surface);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--surface-border);
            border-radius: 16px;
            padding: 14px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.6);
            display: none;
            flex-direction: column;
            gap: 12px;
        }
        #navControls.open { display: flex; }

        .control-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
            width: 100%;
        }

        .group {
            background: rgba(0, 0, 0, 0.25);
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .group label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
        .group input[type="range"] {
            width: 100%;
            height: 5px;
            accent-color: var(--accent);
            border-radius: 3px;
            background: rgba(255,255,255,0.15);
        }

        .numeric {
            width: 70px;
            padding: 5px 8px;
            background: rgba(0,0,0,0.5);
            border: 1px solid var(--surface-border);
            border-radius: 6px;
            color: #fff;
            font-size: 12px;
            text-align: center;
        }

        .btn-row { display: flex; gap: 6px; flex-wrap: wrap; }
        .small-btn {
            padding: 6px 10px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--surface-border);
            border-radius: 6px;
            color: var(--text-main);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            touch-action: manipulation;
        }
        .small-btn:active { background: rgba(255,255,255,0.2); }

        .wheels-container {
            display: flex;
            gap: 12px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            padding: 6px 0;
        }
        .spin-wheel {
            position: relative;
            width: var(--size);
            height: var(--size);
            border-radius: 50%;
            background: radial-gradient(circle, #202430 0%, #12141a 100%);
            box-shadow: inset 0 1px 2px rgba(255,255,255,0.15), 0 4px 12px rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            user-select: none;
            -webkit-user-select: none;
        }
        .spin-wheel .wheel-label {
            position: absolute;
            bottom: 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }
        .spin-wheel .wheel-knob {
            width: 36%;
            height: 36%;
            background: linear-gradient(135deg, #3d4558, #262b37);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 50%;
            box-shadow: 0 2px 6px rgba(0,0,0,0.4);
            transform: translate(0,0);
            transition: transform 0.05s linear;
            touch-action: none;
        }

        .viewport-helper-btns { display: none; }
    </style>
</head>
<body>

<div id="viewerContainer" class="noinvert">
    <canvas id="renderCanvas" touch-action="none"></canvas>
</div>

<!-- Floating Top Bar -->
<div id="viewerToolbar">
    <select id="modelSelect"><option>Loading meshes…</option></select>

    <button id="uploadSnapshotBtn" class="btn btn-primary" title="Save Frame to Database (1024x1536)">💾 Save Frame</button>
    <button id="zoomFitBtn" class="btn">Fit</button>

    <label class="toolbar-label"><input id="twoFingerPan" type="checkbox" checked> 2-Finger Move</label>
    <label class="toolbar-label"><input id="autorotate" type="checkbox"> Spin</label>

    <?php if ($animaticId > 0): ?>
        <a href="/view_motion_editor.php?animatic_id=<?= $animaticId ?>" class="btn btn-primary" style="margin-left:auto; text-decoration:none;">🎬 Motion Editor</a>
    <?php endif; ?>

    <div class="viewport-helper-btns">
        <button class="btn" onclick="setDesktopView()">Desktop</button>
        <button class="btn" onclick="setMobileView()">Mobile</button>
    </div>
</div>

<!-- Floating Bottom Controls Drawer -->
<div id="navDrawerWrapper">
    <button id="drawerToggleBtn" type="button">🎛️ Camera Controls & Wheels ▲</button>

    <div id="navControls" aria-label="Navigation controls">
        <div class="control-grid">
            <div class="group">
                <label>Orbit (Azimuth / Elevation)</label>
                <input id="orbitAlpha" type="range" min="-6.283" max="6.283" step="0.01" value="1.57">
                <div style="display:flex; gap:6px; align-items:center;">
                    <input id="orbitAlphaVal" class="numeric" type="number" step="0.01">
                    <span style="font-size:11px; color:var(--text-muted);">α (Yaw)</span>
                </div>

                <input id="orbitBeta" type="range" min="0.01" max="3.12" step="0.01" value="1.25">
                <div style="display:flex; gap:6px; align-items:center;">
                    <input id="orbitBetaVal" class="numeric" type="number" step="0.01">
                    <span style="font-size:11px; color:var(--text-muted);">β (Pitch)</span>
                </div>
            </div>

            <div class="group">
                <label>Distance & Speed</label>
                <input id="orbitRadius" type="range" min="0.1" max="50" step="0.1" value="3">
                <div style="display:flex; gap:6px; align-items:center;">
                    <input id="orbitRadiusVal" class="numeric" type="number" step="0.1">
                    <span style="font-size:11px; color:var(--text-muted);">Radius</span>
                </div>

                <label style="margin-top:4px;">Sensitivity</label>
                <input id="orbitSpeed" type="range" min="0.1" max="5" step="0.1" value="1">
                <div class="btn-row" style="margin-top:4px;">
                    <button class="small-btn" id="presetFront">Front</button>
                    <button class="small-btn" id="presetSide">Side</button>
                    <button class="small-btn" id="presetTop">Top</button>
                    <button class="small-btn" id="resetView">Reset</button>
                </div>
            </div>

            <div class="group">
                <label>Pan & Nudge</label>
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <div id="arrowPad" style="display:grid; grid-template-columns: 36px 36px 36px; gap:4px; justify-items:center;">
                        <button class="small-btn" id="nav_up">▲</button>
                        <button class="small-btn" id="nav_forward">⤒</button>
                        <button class="small-btn" id="nav_zoom_in">＋</button>

                        <button class="small-btn" id="nav_left">◀</button>
                        <button class="small-btn" id="nav_center">•</button>
                        <button class="small-btn" id="nav_right">▶</button>

                        <button class="small-btn" id="nav_down">▼</button>
                        <button class="small-btn" id="nav_back">⤓</button>
                        <button class="small-btn" id="nav_zoom_out">－</button>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:4px;">
                        <div style="display:flex; gap:4px;">
                            <button class="small-btn" id="btn_forward">Fwd</button>
                            <button class="small-btn" id="btn_back">Back</button>
                        </div>
                        <div style="display:flex; gap:4px;">
                            <button class="small-btn" id="btn_up">Up</button>
                            <button class="small-btn" id="btn_down">Down</button>
                        </div>
                    </div>
                </div>

                <div style="margin-top:4px;">
                    <label>Pan Sensitivity</label>
                    <input id="panSensitivity" type="range" min="0.1" max="5" step="0.1" value="1">
                </div>
            </div>

            <div class="group">
                <label>Target Coordinates (X, Y, Z)</label>
                <div style="display:flex; gap:6px; align-items:center;">
                    <input id="targetX" class="numeric" type="number" step="0.1" placeholder="X">
                    <input id="targetY" class="numeric" type="number" step="0.1" placeholder="Y">
                    <input id="targetZ" class="numeric" type="number" step="0.1" placeholder="Z">
                    <button class="small-btn" id="applyTarget">Apply</button>
                </div>
            </div>
        </div>

        <div class="group">
            <label style="text-align:center;">Virtual Flight Wheels (Drag from center)</label>
            <div class="wheels-container">
                <div id="wheelYaw" class="spin-wheel" style="--size:82px; touch-action:none;">
                    <div class="wheel-label">Yaw</div>
                    <div class="wheel-knob"></div>
                </div>
                <div id="wheelPitch" class="spin-wheel" style="--size:82px; touch-action:none;">
                    <div class="wheel-label">Pitch</div>
                    <div class="wheel-knob"></div>
                </div>
                <div id="wheelRoll" class="spin-wheel" style="--size:82px; touch-action:none;">
                    <div class="wheel-label">Roll</div>
                    <div class="wheel-knob"></div>
                </div>
                <div id="wheelZoom" class="spin-wheel" style="--size:82px; touch-action:none;">
                    <div class="wheel-label">Zoom</div>
                    <div class="wheel-knob"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<script src="https://cdn.babylonjs.com/babylon.js"></script>
<script src="https://cdn.babylonjs.com/loaders/babylon.glTF2FileLoader.js"></script>
<script src="/js/babylon-viewer.js"></script>

<script>
const INITIAL_CONFIG = {
    requestedMeshId: "<?= $requestedMeshId ?: '' ?>",
    animaticId: "<?= $animaticId ?: '' ?>",
    sketchId: "<?= $sketchId ?: '' ?>"
};

(function(){
    const toggleBtn = document.getElementById('drawerToggleBtn');
    const drawer = document.getElementById('navControls');
    if (toggleBtn && drawer) {
        toggleBtn.addEventListener('click', function(e){
            e.stopPropagation();
            const isOpen = drawer.classList.toggle('open');
            toggleBtn.innerHTML = isOpen ? '🎛️ Hide Controls ▼' : '🎛️ Camera Controls & Wheels ▲';
        });
    }
})();

function setDesktopView() {
    window.dispatchEvent(new Event('resize'));
}

function setMobileView() {
    window.dispatchEvent(new Event('resize'));
}

/**
 * Camera Touch Calibration:
 * 1. Tames pinch zoom sensitivity (pinchPrecision = 90 eliminates hypersensitive jumps)
 * 2. Connects 2-finger pan toggle checkbox
 */
function applyCameraTuning() {
    const viewer = window.myViewer;
    if (!viewer || !viewer.camera) return;

    // Default was 12; on high-DPI Android Chrome 90 provides smooth, natural pinch zoom
    viewer.camera.pinchPrecision = 90;
    viewer.camera.wheelPrecision = 40;
    viewer.camera.lowerRadiusLimit = 0.05;

    const twoFingerCheck = document.getElementById('twoFingerPan');
    const allowPan = twoFingerCheck ? twoFingerCheck.checked : true;

    if (allowPan) {
        viewer.camera.panningSensibility = 1200; // Smooth 2-finger pan
        if (viewer.camera.inputs && viewer.camera.inputs.attached && viewer.camera.inputs.attached.pointers) {
            viewer.camera.inputs.attached.pointers.multiTouchPanning = true;
            viewer.camera.inputs.attached.pointers.multiTouchPanAndPinch = true;
        }
    } else {
        viewer.camera.panningSensibility = 0; // Pure in-place pinch zoom
        if (viewer.camera.inputs && viewer.camera.inputs.attached && viewer.camera.inputs.attached.pointers) {
            viewer.camera.inputs.attached.pointers.multiTouchPanning = false;
            viewer.camera.inputs.attached.pointers.multiTouchPanAndPinch = false;
        }
    }
}

/**
 * High-Resolution 1024x1536 Offscreen Render Capture & SAGE Frames Registration
 */
async function captureHighResPortraitFrame(width = 1024, height = 1536) {
    const viewer = window.myViewer;
    if (!viewer || !viewer.camera) {
        throw new Error("Viewer or camera not ready.");
    }
    const scene  = viewer.camera.getScene();
    const engine = scene ? scene.getEngine() : null;

    const activeMeshId = document.getElementById('modelSelect')?.value || INITIAL_CONFIG.requestedMeshId;
    let saveEndpoint = '/meshes_api.php?action=save_snapshot&mesh_id=' + encodeURIComponent(activeMeshId);
    if (INITIAL_CONFIG.animaticId) saveEndpoint += '&animatic_id=' + INITIAL_CONFIG.animaticId;
    if (INITIAL_CONFIG.sketchId)   saveEndpoint += '&entity_type=sketches&entity_id=' + INITIAL_CONFIG.sketchId;

    if (typeof BABYLON.Tools.CreateScreenshotUsingRenderTarget === 'function' && engine) {
        return new Promise((resolve, reject) => {
            BABYLON.Tools.CreateScreenshotUsingRenderTarget(
                engine,
                viewer.camera,
                { width: width, height: height },
                async function(dataUrl) {
                    try {
                        const fd = new FormData();
                        fd.append('data', dataUrl);

                        const resp = await fetch(saveEndpoint, { method: 'POST', body: fd });
                        const resJson = await resp.json();
                        if (!resp.ok || !resJson.success) {
                            throw new Error(resJson.error || resJson.message || 'Upload failed');
                        }
                        resolve(resJson);
                    } catch(err) {
                        reject(err);
                    }
                },
                'image/jpeg',
                4
            );
        });
    } else {
        return await window.captureCanvasAndUpload({
            endpointPath: saveEndpoint,
            quality: 0.92,
            filenameHint: 'mesh_frame'
        });
    }
}

// SAGE Save Frame Handler (1024x1536 Portrait)
(function(){
    const btn = document.getElementById('uploadSnapshotBtn');
    if (!btn) return;
    btn.addEventListener('click', async function () {
        try {
            btn.disabled = true;
            btn.textContent = 'Rendering 1024×1536…';

            const result = await captureHighResPortraitFrame(1024, 1536);

            const msg = result.message || ('Saved as SAGE Frame #' + result.frame_id);
            if (typeof Toast !== 'undefined' && Toast.show) {
                Toast.show(msg, 'success');
            } else {
                alert(msg);
            }
        } catch (err) {
            console.error('Snapshot upload failed', err);
            alert('Snapshot save failed: ' + (err && err.message ? err.message : err));
        } finally {
            btn.disabled = false;
            btn.textContent = '💾 Save Frame';
        }
    });
})();

(function(){
    let listEndpoint = '/meshes_api.php?action=list';
    if (INITIAL_CONFIG.animaticId) listEndpoint += '&animatic_id=' + INITIAL_CONFIG.animaticId;
    if (INITIAL_CONFIG.sketchId)   listEndpoint += '&sketch_id=' + INITIAL_CONFIG.sketchId;

    var viewer = new BabylonViewerJS({
        canvasId: 'renderCanvas',
        modelsListEndpoint: listEndpoint,
        modelEndpoint: '/meshes_api.php'
    });
    window.myViewer = viewer;

    /* ===== Mobile controls: endless wheels + arrow pad ===== */
    (function(){
        const v = window.myViewer;
        if (!v || !v.camera) {
            setTimeout(() => { if (window.myViewer) initControls(window.myViewer); }, 200);
        } else {
            initControls(v);
        }

        function initControls(viewer) {
            applyCameraTuning();

            const CONFIG = {
                maxYawSpeed: 3.0,
                maxPitchSpeed: 2.2,
                maxRollSpeed: 2.8,
                maxZoomSpeed: 4.0,
                panPixelsPerSec: 300,
                dollyPerSec: 1.8,
                forwardStep: 0.6,
                upStep: 0.6
            };

            let yawRate = 0, pitchRate = 0, rollRate = 0;
            let panXRate = 0, panYRate = 0, dollyRate = 0;
            let lastFrameTime = performance.now();
            let rafId = null;

            function clampBeta(b) { return Math.max(0.01, Math.min(3.13, b)); }

            function updateFrame(now) {
                const dt = Math.max(0.001, (now - lastFrameTime) / 1000);
                lastFrameTime = now;

                try {
                    if (yawRate !== 0) viewer.camera.alpha += yawRate * dt;
                    if (pitchRate !== 0) viewer.camera.beta = clampBeta(viewer.camera.beta + pitchRate * dt);
                    if (dollyRate !== 0) viewer.camera.radius = Math.max(0.01, viewer.camera.radius + dollyRate * dt);

                    if (rollRate !== 0) {
                        try {
                            const angle = rollRate * dt;
                            const forward = viewer.camera.target.subtract(viewer.camera.position).normalize();
                            const rot = BABYLON.Matrix.RotationAxis(forward, angle);
                            const newUp = BABYLON.Vector3.TransformCoordinates(viewer.camera.upVector, rot);
                            viewer.camera.upVector = newUp.normalize();
                        } catch (e) { console.warn('roll apply failed', e); }
                    }

                    if (panXRate !== 0 || panYRate !== 0) {
                        const canvas = viewer.canvas;
                        const height = canvas.clientHeight || canvas.height || 1;
                        const distance = Math.max(0.0001, viewer.camera.radius);
                        const worldHeight = 2 * distance * Math.tan(viewer.camera.fov / 2);
                        const worldPerPixel = worldHeight / Math.max(1, height);

                        const dxWorld = -panXRate * dt * worldPerPixel;
                        const dyWorld = panYRate * dt * worldPerPixel;

                        const forward = viewer.camera.target.subtract(viewer.camera.position).normalize();
                        const right = BABYLON.Vector3.Cross(forward, viewer.camera.upVector).normalize();
                        const up = BABYLON.Vector3.Cross(right, forward).normalize();

                        const offset = right.scale(dxWorld).add(up.scale(dyWorld));
                        viewer.camera.setTarget(viewer.camera.target.add(offset));
                    }
                } catch(e) { console.warn('control update error', e); }

                rafId = requestAnimationFrame(updateFrame);
            }

            function ensureLoop() {
                if (!rafId) {
                    lastFrameTime = performance.now();
                    rafId = requestAnimationFrame(updateFrame);
                }
            }

            function maybeStopLoop() {
                if (yawRate === 0 && pitchRate === 0 && rollRate === 0 && panXRate === 0 && panYRate === 0 && dollyRate === 0) {
                    if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
                }
            }

            function makeWheel(container, axis) {
                const knob = container.querySelector('.wheel-knob');
                const rect = () => container.getBoundingClientRect();
                const radius = () => Math.min(rect().width, rect().height) * 0.45;
                let active = false;
                let pointerId = null;

                function setKnobPos(x, y) {
                    const r = radius();
                    const limitedX = Math.max(-r, Math.min(r, x));
                    const limitedY = Math.max(-r, Math.min(r, y));
                    knob.style.transform = `translate(${limitedX * 0.45}px, ${limitedY * 0.45}px)`;
                }
                function resetKnob() { knob.style.transform = 'translate(0,0)'; }

                function onPointerDown(ev) {
                    ev.preventDefault();
                    try { container.setPointerCapture(ev.pointerId); } catch(e){}
                    active = true;
                    pointerId = ev.pointerId;
                    const b = rect();
                    const dx = ev.clientX - (b.left + b.width/2);
                    const dy = ev.clientY - (b.top + b.height/2);
                    setKnobPos(dx, dy);
                    updateRateFromPos(dx, dy);
                    ensureLoop();
                }

                function onPointerMove(ev) {
                    if (!active || ev.pointerId !== pointerId) return;
                    const b = rect();
                    const dx = ev.clientX - (b.left + b.width/2);
                    const dy = ev.clientY - (b.top + b.height/2);
                    setKnobPos(dx, dy);
                    updateRateFromPos(dx, dy);
                }

                function onPointerUp(ev) {
                    if (!active || ev.pointerId !== pointerId) return;
                    active = false;
                    pointerId = null;
                    try { container.releasePointerCapture(ev.pointerId); } catch(e){}
                    resetKnob();

                    if (axis === 'yaw') yawRate = 0;
                    else if (axis === 'pitch') pitchRate = 0;
                    else if (axis === 'roll') rollRate = 0;
                    else if (axis === 'zoom') dollyRate = 0;

                    maybeStopLoop();
                }

                function updateRateFromPos(dx, dy) {
                    const r = radius();
                    if (r <= 0) return;
                    const distNorm = Math.min(1, Math.sqrt(dx*dx+dy*dy) / r);
                    if (axis === 'yaw') {
                        yawRate = (dx >= 0 ? 1 : -1) * distNorm * CONFIG.maxYawSpeed;
                    } else if (axis === 'pitch') {
                        pitchRate = (dy >= 0 ? 1 : -1) * distNorm * CONFIG.maxPitchSpeed * -1;
                    } else if (axis === 'roll') {
                        rollRate = (dx >= 0 ? 1 : -1) * distNorm * CONFIG.maxRollSpeed;
                    } else if (axis === 'zoom') {
                        dollyRate = (dy >= 0 ? 1 : -1) * distNorm * CONFIG.maxZoomSpeed;
                    }
                }

                container.addEventListener('pointerdown', onPointerDown);
                container.addEventListener('pointermove', onPointerMove);
                container.addEventListener('pointerup', onPointerUp);
                container.addEventListener('pointercancel', onPointerUp);
                container.addEventListener('lostpointercapture', onPointerUp);
            }

            const wheelYawEl = document.getElementById('wheelYaw');
            if (wheelYawEl) makeWheel(wheelYawEl, 'yaw');
            const wheelPitchEl = document.getElementById('wheelPitch');
            if (wheelPitchEl) makeWheel(wheelPitchEl, 'pitch');
            const wheelRollEl = document.getElementById('wheelRoll');
            if (wheelRollEl) makeWheel(wheelRollEl, 'roll');
            const wheelZoomEl = document.getElementById('wheelZoom');
            if (wheelZoomEl) makeWheel(wheelZoomEl, 'zoom');

            function wireHoldButton(id, onStart, onEnd) {
                const el = document.getElementById(id);
                if (!el) return;
                let pressed = false, pid = null;
                el.addEventListener('pointerdown', (e) => {
                    e.preventDefault();
                    el.setPointerCapture(e.pointerId);
                    pressed = true; pid = e.pointerId;
                    onStart(); ensureLoop();
                });
                const stopHandler = (e) => {
                    if (!pressed || e.pointerId !== pid) return;
                    pressed = false; onEnd(); maybeStopLoop();
                };
                el.addEventListener('pointerup', stopHandler);
                el.addEventListener('pointercancel', stopHandler);
                el.addEventListener('lostpointercapture', stopHandler);
            }

            wireHoldButton('nav_up', () => { panYRate = -CONFIG.panPixelsPerSec; }, () => { panYRate = 0; });
            wireHoldButton('nav_down', () => { panYRate = CONFIG.panPixelsPerSec; }, () => { panYRate = 0; });
            wireHoldButton('nav_left', () => { panXRate = -CONFIG.panPixelsPerSec; }, () => { panXRate = 0; });
            wireHoldButton('nav_right', () => { panXRate = CONFIG.panPixelsPerSec; }, () => { panXRate = 0; });

            const centerBtn = document.getElementById('nav_center');
            if (centerBtn) centerBtn.addEventListener('click', () => {
                viewer.setCameraTarget(viewer._initialCameraState ? viewer._initialCameraState.target : new BABYLON.Vector3(0,0,0), true);
            });

            wireHoldButton('nav_forward', () => { dollyRate = -CONFIG.dollyPerSec; }, () => { dollyRate = 0; });
            wireHoldButton('nav_back',    () => { dollyRate = CONFIG.dollyPerSec; },  () => { dollyRate = 0; });
            wireHoldButton('nav_zoom_in',  () => { dollyRate = -CONFIG.dollyPerSec; }, () => { dollyRate = 0; });
            wireHoldButton('nav_zoom_out', () => { dollyRate = CONFIG.dollyPerSec;  }, () => { dollyRate = 0; });

            const btnForward = document.getElementById('btn_forward');
            if (btnForward) btnForward.addEventListener('click', () => viewer.moveForward ? viewer.moveForward(CONFIG.forwardStep) : null);
            const btnBack = document.getElementById('btn_back');
            if (btnBack) btnBack.addEventListener('click', () => viewer.moveForward ? viewer.moveForward(-CONFIG.forwardStep) : null);
            const btnUp = document.getElementById('btn_up');
            if (btnUp) btnUp.addEventListener('click', () => viewer.moveUp ? viewer.moveUp(CONFIG.upStep) : null);
            const btnDown = document.getElementById('btn_down');
            if (btnDown) btnDown.addEventListener('click', () => viewer.moveUp ? viewer.moveUp(-CONFIG.upStep) : null);

            window.addEventListener('pagehide', () => {
                yawRate = pitchRate = panXRate = panYRate = dollyRate = 0;
                maybeStopLoop();
            });

            maybeStopLoop();
        }
    })();

    async function populate(){
        let list = [];
        try {
            const rawList = await viewer.fetchModelList();
            list = Array.isArray(rawList) ? rawList : (rawList && typeof rawList === 'object' ? [rawList] : []);

            const sel = document.getElementById('modelSelect');
            sel.innerHTML = '';
            list.forEach(function(m){
                var opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.name + ' (' + (m.size ? Math.round(m.size/1024) + 'KB' : '?') + ')';
                sel.appendChild(opt);
            });

            if(!list.length) {
                sel.innerHTML = '<option disabled>No meshes found in database</option>';
                return;
            }
        } catch(err) {
            console.error('Failed to populate dropdown:', err);
            alert('Failed to load mesh list from database.');
            return;
        }

        try {
            const sel = document.getElementById('modelSelect');
            let targetId = list[0].id;
            if (INITIAL_CONFIG.requestedMeshId) {
                const found = list.find(item => item.id == INITIAL_CONFIG.requestedMeshId || item.mesh_id == INITIAL_CONFIG.requestedMeshId);
                if (found) targetId = found.id;
            }
            sel.value = targetId;
            await viewer.loadModelById(targetId);
            applyCameraTuning();
        } catch(modelErr) {
            console.error('loadModelById error:', modelErr);
        }
    }

    document.getElementById('modelSelect').addEventListener('change', function(){ 
        viewer.loadModelById(this.value).then(applyCameraTuning).catch(console.error); 
    });
    document.getElementById('autorotate').addEventListener('change', function(){ viewer.setAutoRotate(this.checked); });
    document.getElementById('twoFingerPan')?.addEventListener('change', applyCameraTuning);
    document.getElementById('zoomFitBtn').addEventListener('click', function(){ viewer.zoomToFit(); });

    const orbitAlpha = document.getElementById('orbitAlpha');
    const orbitBeta  = document.getElementById('orbitBeta');
    const orbitRadius = document.getElementById('orbitRadius');
    const orbitAlphaVal = document.getElementById('orbitAlphaVal');
    const orbitBetaVal  = document.getElementById('orbitBetaVal');
    const orbitRadiusVal = document.getElementById('orbitRadiusVal');
    const orbitSpeed = document.getElementById('orbitSpeed');

    function syncOrbitUIFromCamera() {
        try {
            if (!viewer || !viewer.camera) return;
            if (orbitAlpha) { orbitAlpha.value = viewer.camera.alpha; orbitAlphaVal && (orbitAlphaVal.value = viewer.camera.alpha.toFixed(3)); }
            if (orbitBeta)  { orbitBeta.value = viewer.camera.beta;  orbitBetaVal && (orbitBetaVal.value = viewer.camera.beta.toFixed(3)); }
            if (orbitRadius){ orbitRadius.value = viewer.camera.radius; orbitRadiusVal && (orbitRadiusVal.value = viewer.camera.radius.toFixed(3)); }
        } catch(e){}
    }

    if (orbitAlpha) {
        orbitAlpha.addEventListener('input', function(){
            try { viewer.setCameraAlphaBetaRadius(parseFloat(this.value), viewer.camera.beta, viewer.camera.radius); } catch(e){}
            orbitAlphaVal && (orbitAlphaVal.value = parseFloat(this.value).toFixed(3));
        });
    }
    if (orbitBeta) {
        orbitBeta.addEventListener('input', function(){
            try { viewer.setCameraAlphaBetaRadius(viewer.camera.alpha, parseFloat(this.value), viewer.camera.radius); } catch(e){}
            orbitBetaVal && (orbitBetaVal.value = parseFloat(this.value).toFixed(3));
        });
    }
    if (orbitRadius) {
        orbitRadius.addEventListener('input', function(){
            try { viewer.setCameraAlphaBetaRadius(viewer.camera.alpha, viewer.camera.beta, parseFloat(this.value)); } catch(e){}
            orbitRadiusVal && (orbitRadiusVal.value = parseFloat(this.value).toFixed(3));
        });
    }

    if (orbitAlphaVal) orbitAlphaVal.addEventListener('change', function(){ try { viewer.setCameraAlphaBetaRadius(parseFloat(this.value||viewer.camera.alpha), viewer.camera.beta, viewer.camera.radius); syncOrbitUIFromCamera(); } catch(e){} });
    if (orbitBetaVal)  orbitBetaVal.addEventListener('change',  function(){ try { viewer.setCameraAlphaBetaRadius(viewer.camera.alpha, parseFloat(this.value||viewer.camera.beta), viewer.camera.radius); syncOrbitUIFromCamera(); } catch(e){} });
    if (orbitRadiusVal) orbitRadiusVal.addEventListener('change', function(){ try { viewer.setCameraAlphaBetaRadius(viewer.camera.alpha, viewer.camera.beta, parseFloat(this.value||viewer.camera.radius)); syncOrbitUIFromCamera(); } catch(e){} });

    if (orbitSpeed) {
        orbitSpeed.addEventListener('input', function(){
            try { if (typeof viewer.setOrbitSensitivity === 'function') viewer.setOrbitSensitivity(parseFloat(this.value)); } catch(e){}
        });
    }

    const presetFront = document.getElementById('presetFront');
    if (presetFront) presetFront.addEventListener('click', function(){
        try { viewer.setCameraAlphaBetaRadius(Math.PI/2, 1.2, viewer.camera.radius, true); } catch(e){}
        syncOrbitUIFromCamera();
    });
    const presetSide = document.getElementById('presetSide');
    if (presetSide) presetSide.addEventListener('click', function(){
        try { viewer.setCameraAlphaBetaRadius(0, 1.2, viewer.camera.radius, true); } catch(e){}
        syncOrbitUIFromCamera();
    });
    const presetTop = document.getElementById('presetTop');
    if (presetTop) presetTop.addEventListener('click', function(){
        try { viewer.setCameraAlphaBetaRadius(Math.PI/2, 0.5, viewer.camera.radius, true); } catch(e){}
        syncOrbitUIFromCamera();
    });
    const resetViewBtn = document.getElementById('resetView');
    if (resetViewBtn) resetViewBtn.addEventListener('click', function(){
        try { viewer.resetCamera(true); } catch(e){}
        setTimeout(syncOrbitUIFromCamera, 350);
    });

    const panSens = document.getElementById('panSensitivity');
    function safePanByPixels(dx, dy) {
        try {
            const sens = parseFloat(panSens && panSens.value ? panSens.value : 1.0);
            if (typeof viewer.panByPixels === 'function') viewer.panByPixels(dx, dy, sens);
        } catch(e){}
    }

    ['nav_up','nav_down','nav_left','nav_right'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', function(){
            const step = 40;
            if (id === 'nav_up') safePanByPixels(0, -step);
            if (id === 'nav_down') safePanByPixels(0, step);
            if (id === 'nav_left') safePanByPixels(-step, 0);
            if (id === 'nav_right') safePanByPixels(step, 0);
            syncOrbitUIFromCamera();
        });
    });

    const applyTargetBtn = document.getElementById('applyTarget');
    if (applyTargetBtn) {
        applyTargetBtn.addEventListener('click', function(){
            try {
                const x = parseFloat(document.getElementById('targetX')?.value || viewer.camera.target.x);
                const y = parseFloat(document.getElementById('targetY')?.value || viewer.camera.target.y);
                const z = parseFloat(document.getElementById('targetZ')?.value || viewer.camera.target.z);
                viewer.setCameraTarget({x,y,z}, true);
                setTimeout(syncOrbitUIFromCamera, 350);
            } catch(e){}
        });
    }

    setTimeout(syncOrbitUIFromCamera, 250);
    populate();
    setInterval(syncOrbitUIFromCamera, 600);
    viewer.setOrbitSensitivity(parseFloat(orbitSpeed.value || 1.0));
})();
</script>

</body>
</html>