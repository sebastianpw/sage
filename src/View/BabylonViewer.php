<?php
namespace App\View;

class BabylonViewer
{
    protected $projectRoot;
    protected $publicUrlBase; // base URL to public files, e.g. '/'
    protected $modelsDirRel;  // relative to project root, e.g. '/public/models'

    public function __construct(string $projectRoot, string $modelsDirRel = '/public/models', string $publicUrlBase = '/')
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->modelsDirRel = $modelsDirRel;
        $this->publicUrlBase = rtrim($publicUrlBase, '/');
    }

    /**
     * Render full page HTML for the viewer
     */
    public function render(): void
    {
        // Minimal page — you can expand layout to match your app
        ?>
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width,initial-scale=0.6">
            <title>3D Viewer — Babylon.js</title>
            <style>
                html,body { height:100%; margin:0; }
                #viewerContainer { width:100%; height:80vh; background:#fff; }
                #viewerToolbar { padding:30px;margin-bottom:50px;  background:#fafafa; display:flex; gap:10px; align-items:center; }
                #modelSelect { min-width:200px; }
		.btn { padding:6px 10px; border-radius:4px; border:1px solid #ccc; background:#fff; cursor:pointer; }

#viewerContainer {
    width: 100%;
    max-width: 1000px;     /* optional limit */
    margin: 0 auto;
    aspect-ratio: 1 / 1;   /* forces square */
    background: #fff;
    position: relative;
    height: 350px;
    width: 350px;
}
#renderCanvas { width:350px; height:350px; display:block; }


html, body { background: #fff; }

/* Apply inversion only when the theme is dark 
html[data-theme="dark"],
:root[data-theme="dark"],
body[data-theme="dark"] {
  filter: invert(1) hue-rotate(180deg) contrast(1) saturate(1);
  transition: filter 240ms ease;
}

html[data-theme="dark"] #viewerContainer, .noinvert {
  filter: invert(1) hue-rotate(180deg) contrast(1) saturate(1);
}
*/


/*
body {
  filter: invert(1) hue-rotate(180deg) contrast(1) saturate(1);
  
  transition: filter 240ms ease;
}

#viewerContainer, .noinvert {
     
    filter: invert(1) hue-rotate(180deg) contrast(1) saturate(1);

    }
*/

	    </style>

        
        
       
        

        
        </head>
        <body>
        <div id="viewerToolbar">
	    <select id="modelSelect"><option>Loading models…</option></select>


<button id="downloadSnapshotBtn" class="btn">📸 Download JPG</button>
<button id="uploadSnapshotBtn" class="btn">💾 Save snapshot to server</button>


<script>
document.getElementById('downloadSnapshotBtn').addEventListener('click', function(){
    captureCanvasAndDownload();
});


// === wire upload button (paste near the download button handler or after window.myViewer = viewer) ===
(function(){
  const btn = document.getElementById('uploadSnapshotBtn');
  if (!btn) return;

  btn.addEventListener('click', async function () {
    try {
      // disable & show progress
      btn.disabled = true;
      const origText = btn.textContent;
      btn.textContent = 'Saving…';

      // call the helper exposed by js/babylon-viewer.js
      // adjust endpointPath / quality / filenameHint if you want
      const result = await window.captureCanvasAndUpload({
        endpointPath: '/save_snapshot.php',
        quality: 0.85,
        filenameHint: 'babylon_snapshot'
      });

      console.log('Snapshot saved', result);
      // visual feedback — replace with your Toast.show if available
      alert('Snapshot saved: ' + (result.url || 'saved (no URL)'));
    } catch (err) {
      console.error('Snapshot upload failed', err);
      alert('Snapshot upload failed: ' + (err && err.message ? err.message : err));
    } finally {
      // restore
      btn.disabled = false;
      btn.textContent = '💾 Save snapshot to server';
    }
  });
})();
</script>





<button class="btn" onclick="setDesktopView()">Desktop View</button>
<button class="btn" onclick="setMobileView()">Mobile View</button>

<script>
function setDesktopView() {
  document.querySelector('meta[name=viewport]')
	  .setAttribute('content', 'width=1000');
  
  $('#renderCanvas').width(800);                          $('#renderCanvas').height(800);
  $('#renderCanvas').width(800);
  $('#renderCanvas').height(800);
}

function setMobileView() {
  document.querySelector('meta[name=viewport]')
	  .setAttribute('content', 'width=device-width, initial-scale=1');

  $('#renderCanvas').width(350);
  $('#renderCanvas').height(350);
  $('#renderCanvas').width(350);
  $('#renderCanvas').height(350);

}
</script>



	  <!--  <button id="reloadBtn" class="btn">Reload list</button>
-->


            <label><input id="autorotate" type="checkbox"> auto-rotate</label>
            <button id="zoomFitBtn" class="btn">Fit</button>
        </div>










<!-- NAVIGATION CONTROL BAR (place directly under #viewerToolbar) -->
<style>
/* small self-contained styles for nav controls */
#navControls {
  max-width: 1000px;
  margin: 8px auto 18px;
  background: #fff;
  border-radius: 10px;
  border: 1px solid #e0e0e0;
  padding: 12px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.03);
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  align-items: center;
  font-family: Arial, sans-serif;
  font-size: 13px;
}

#navControls .group {
  display:flex;
  flex-direction:column;
  gap:8px;
}

#navControls label { font-size:12px; color:#333; }
#navControls input[type="range"] { width:100%; }
#navControls .btn-row { display:flex; gap:8px; flex-wrap:wrap; }
#navControls .small-btn { padding:6px 8px; border-radius:6px; border:1px solid #ccc; background:#f7f7f7; cursor:pointer; }
#navControls .numeric { width:80px; padding:5px; border:1px solid #ccc; border-radius:4px; }
#navControls .pan-grid { display:grid; grid-template-columns: 40px 40px 40px; justify-content:center; gap:4px; align-items:center; }
#navControls .pan-grid button { width:40px; height:32px; }
</style>

<div id="navControls" aria-label="Navigation controls">
  <div class="group">
    <label>Orbit (azimuth / elevation)</label>
    <input id="orbitAlpha" type="range" min="-6.283" max="6.283" step="0.01" value="1.57">
    <div style="display:flex; gap:8px; align-items:center;">
      <input id="orbitAlphaVal" class="numeric" type="number" step="0.01" style="width:120px">
      <span>α (left/right)</span>
    </div>

    <input id="orbitBeta" type="range" min="0.01" max="3.12" step="0.01" value="1.25">
    <div style="display:flex; gap:8px; align-items:center;">
      <input id="orbitBetaVal" class="numeric" type="number" step="0.01" style="width:120px">
      <span>β (up/down)</span>
    </div>
  </div>

  <div class="group">
    <label>Distance / Zoom</label>
    <input id="orbitRadius" type="range" min="0.1" max="50" step="0.1" value="3">
    <div style="display:flex; gap:8px; align-items:center;">
      <input id="orbitRadiusVal" class="numeric" type="number" step="0.1" style="width:120px">
      <span>Radius</span>
    </div>

    <label style="margin-top:6px">Orbit speed (sensitivity)</label>
    <input id="orbitSpeed" type="range" min="0.1" max="5" step="0.1" value="1">
    <div style="display:flex; gap:8px; align-items:center;">
      <button class="small-btn" id="presetFront">Front</button>
      <button class="small-btn" id="presetSide">Side</button>
      <button class="small-btn" id="presetTop">Top</button>
      <button class="small-btn" id="resetView">Reset</button>
    </div>
  </div>

  <div class="group" style="grid-column: span 2;">
    <label>Pan (move target) — small world-space nudges</label>
    <div style="display:flex; gap:10px; align-items:center;">







<!-- MOBILE-FRIENDLY NAV: arrow pad + forward/back + spin wheels -->
<div style="display:flex; gap:12px; align-items:center; width:100%;">
  <!-- Arrow pad (pans target in view plane) -->
  <div id="arrowPad" style="display:grid; grid-template-columns: 48px 48px 48px; gap:6px; justify-items:center;">
    <button class="small-btn" id="nav_up" aria-label="pan up">▲</button>
    <button class="small-btn" id="nav_forward" aria-label="forward">⤒</button>
    <button class="small-btn" id="nav_zoom_in" aria-label="zoom in">＋</button>

    <button class="small-btn" id="nav_left" aria-label="pan left">◀</button>
    <button class="small-btn" id="nav_center" aria-label="center">•</button>
    <button class="small-btn" id="nav_right" aria-label="pan right">▶</button>

    <button class="small-btn" id="nav_down" aria-label="pan down">▼</button>
    <button class="small-btn" id="nav_back" aria-label="back">⤓</button>
    <button class="small-btn" id="nav_zoom_out" aria-label="zoom out">－</button>
  </div>

  <!-- Forward/Back / Up/Down block -->
  <div style="display:flex; flex-direction:column; gap:6px;">
    <div style="display:flex; gap:6px;">
      <button class="small-btn" id="btn_forward">Forward</button>
      <button class="small-btn" id="btn_back">Back</button>
    </div>
    <div style="display:flex; gap:6px;">
      <button class="small-btn" id="btn_up">Up</button>
      <button class="small-btn" id="btn_down">Down</button>
    </div>
  </div>







  <div style="margin-left:12px;">                           <label style="display:block">Pan sensitivity</label>
        <input id="panSensitivity" type="range" min="0.1" max="5" step="0.1" value="1" style="width:220px">
      </div>

      <div style="margin-left:auto;">
        <label style="display:block">Target (X Y Z)</label>
        <input id="targetX" class="numeric" type="number" step="0.1" placeholder="x">
        <input id="targetY" class="numeric" type="number" step="0.1" placeholder="y">
        <input id="targetZ" class="numeric" type="number" step="0.1" placeholder="z">
        <button class="small-btn" id="applyTarget">Apply</button>
      </div>









</div>
</div>
</div>







  <!-- The endless spin wheels (joystick-like) -->
  <div style="display:flex; gap:10px; align-items:center; margin-left:auto;">





<div id="wheelYaw" class="spin-wheel" style="--size:90px; touch-action:none;">
  <div class="wheel-label">Yaw</div>
  <div class="wheel-knob"></div>
</div>

<div id="wheelPitch" class="spin-wheel" style="--size:90px; touch-action:none;">
  <div class="wheel-label">Pitch</div>
  <div class="wheel-knob"></div>
</div>

<div id="wheelRoll" class="spin-wheel" style="--size:90px; touch-action:none;">
  <div class="wheel-label">Roll</div>
  <div class="wheel-knob"></div>
</div>

<div id="wheelZoom" class="spin-wheel" style="--size:90px; touch-action:none;">
  <div class="wheel-label">Zoom</div>
  <div class="wheel-knob"></div>
</div>


</div>









</div>

<style>
/* wheel CSS — small and self-contained */
.spin-wheel {
  position: relative;
  width: var(--size);
  height: var(--size);
  border-radius: 50%;
  background: linear-gradient(180deg, #f5f5f5, #eaeaea);
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.6), 0 1px 4px rgba(0,0,0,0.06);
  border: 1px solid #ddd;
  display:flex;
  align-items:center;
  justify-content:center;
  user-select: none;
  -webkit-user-select:none;
}
.spin-wheel .wheel-label {
  position:absolute; bottom:6px; left:6px; font-size:11px; color:#444;
}
.spin-wheel .wheel-knob {
  width:36%;
  height:36%;
  background:#cfcfcf;
  border-radius:50%;
  box-shadow: 0 2px 4px rgba(0,0,0,0.15);
  transform: translate(0,0);
  transition: transform 0.06s linear;
  touch-action: none;
}
.small-btn { font-size:13px; }
</style>

















<!--
      <div style="margin-left:12px;">
        <label style="display:block">Pan sensitivity</label>
        <input id="panSensitivity" type="range" min="0.1" max="5" step="0.1" value="1" style="width:220px">
      </div>

      <div style="margin-left:auto;">
        <label style="display:block">Target (X Y Z)</label>
        <input id="targetX" class="numeric" type="number" step="0.1" placeholder="x">
        <input id="targetY" class="numeric" type="number" step="0.1" placeholder="y">
        <input id="targetZ" class="numeric" type="number" step="0.1" placeholder="z">
        <button class="small-btn" id="applyTarget">Apply</button>
      </div>
-->



    </div>
  </div>
</div>









        <div id="viewerContainer" class="noinvert">
            <canvas id="renderCanvas" touch-action="none" style="width:100%; height:100%;"></canvas>
        </div>





<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>                                             <link rel="stylesheet" href="/css/toast.css">           <script src="/js/toast.js"></script>

<!-- Babylon (classic global builds) -->
<script src="https://cdn.babylonjs.com/babylon.js"></script>
<script src="https://cdn.babylonjs.com/loaders/babylon.glTF2FileLoader.js"></script>

<!-- Our viewer script (non-module) -->
<script src="/js/babylon-viewer.js"></script>











<script>
(function(){
    // instantiate viewer and export to window.myViewer
    var viewer = new BabylonViewerJS({
        canvasId: 'renderCanvas',
        modelsListEndpoint: '/babylon_models.php',
        modelEndpoint: '/babylon_model.php'
    });
    window.myViewer = viewer; // global accessor for other helpers / high-res capture











/* ===== Mobile controls: endless wheels + arrow pad =====
   Paste this block right after `window.myViewer = viewer;`
*/
(function(){
  const viewer = window.myViewer;
  if (!viewer || !viewer.camera) {
    // If viewer not ready yet, try again shortly
    setTimeout(() => { if (window.myViewer) initControls(window.myViewer); else console.warn('viewer not ready for mobile controls'); }, 200);
  } else {
    initControls(viewer);
  }

  function initControls(viewer) {




/*
    const CONFIG = {
      maxYawSpeed: 3.0,       // radians per second max for yaw wheel
      maxPitchSpeed: 2.2,     // radians per second max for pitch wheel
      panPixelsPerSec: 300,   // how many screen pixels per second map to a world pan
      dollyPerSec: 1.8,       // radius units per second for zoom in/out via arrows
      forwardStep: 0.6,       // forward/back single click step (world units)
      upStep: 0.6             // vertical move per click
    };
 */



const CONFIG = {
  maxYawSpeed: 3.0,       // radians/sec for yaw
  maxPitchSpeed: 2.2,     // radians/sec for pitch
  maxRollSpeed: 2.8,      // radians/sec for roll (camera upVector rotation)
  maxZoomSpeed: 4.0,      // radius units/sec for zoom wheel
  panPixelsPerSec: 300,
  dollyPerSec: 1.8,
  forwardStep: 0.6,
  upStep: 0.6
};






    // state
    let yawRate = 0;    // radians/sec
    let pitchRate = 0;  // radians/sec
    let rollRate = 0;  // radians/sec
    let panXRate = 0;   // screen pixels/sec (positive -> pan right)
    let panYRate = 0;   // screen pixels/sec (positive -> pan down)
    let dollyRate = 0;  // radius units/sec (+ out, - in)

    let lastFrameTime = performance.now();
    let rafId = null;

    // utility clamps
    function clampBeta(b) { return Math.max(0.01, Math.min(3.13, b)); }

    // Apply continuous updates each frame
    function updateFrame(now) {
      const dt = Math.max(0.001, (now - lastFrameTime) / 1000);
      lastFrameTime = now;

      try {
        // yaw/pitch: apply directly (no animation) for snappy feel
        if (yawRate !== 0) {
          viewer.camera.alpha += yawRate * dt;
        }
        if (pitchRate !== 0) {
          viewer.camera.beta = clampBeta(viewer.camera.beta + pitchRate * dt);
        }

        // dolly
        if (dollyRate !== 0) {
          viewer.camera.radius = Math.max(0.01, viewer.camera.radius + dollyRate * dt);
	}



// roll: rotate camera.upVector about camera forward axis
if (rollRate !== 0) {
  try {
    const angle = rollRate * dt; // radians this frame
    // forward vector (camera looking direction)
    const forward = viewer.camera.target.subtract(viewer.camera.position).normalize();
    // create rotation matrix around forward axis
    const rot = BABYLON.Matrix.RotationAxis(forward, angle);
    // transform upVector and normalize
    const newUp = BABYLON.Vector3.TransformCoordinates(viewer.camera.upVector, rot);
    viewer.camera.upVector = newUp.normalize();
  } catch (e) {
    console.warn('roll apply failed', e);
  }
}






        // pan: convert pan pixels/sec to a world offset per frame
        if (panXRate !== 0 || panYRate !== 0) {
          // world per pixel approximation (use radius + fov)
          const canvas = viewer.canvas;
          const height = canvas.clientHeight || canvas.height || 1;
          const distance = Math.max(0.0001, viewer.camera.radius);
          const worldHeight = 2 * distance * Math.tan(viewer.camera.fov / 2);
          const worldPerPixel = worldHeight / Math.max(1, height);

          // compute offset in world coordinates using camera right/up
          const dxWorld = -panXRate * dt * worldPerPixel; // negative because pan right should move target right visually
          const dyWorld = panYRate * dt * worldPerPixel;

          const forward = viewer.camera.target.subtract(viewer.camera.position).normalize();
          const right = BABYLON.Vector3.Cross(forward, viewer.camera.upVector).normalize();
          const up = BABYLON.Vector3.Cross(right, forward).normalize();

          const offset = right.scale(dxWorld).add(up.scale(dyWorld));
          viewer.camera.setTarget(viewer.camera.target.add(offset));
        }
      } catch(e) {
        console.warn('control update error', e);
      }

      rafId = requestAnimationFrame(updateFrame);
    }

    // start RAF loop if not running
    function ensureLoop() {
      if (!rafId) {
        lastFrameTime = performance.now();
        rafId = requestAnimationFrame(updateFrame);
      }
    }

    // stop RAF if nothing to do
    function maybeStopLoop() {
  if (yawRate === 0 && pitchRate === 0 && rollRate === 0 && panXRate === 0 && panYRate === 0 && dollyRate === 0) {
    if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
  }
}




















// makeWheel (updated to handle 'zoom' axis)
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
  function resetKnob() {
    knob.style.transform = 'translate(0,0)';
  }

  function onPointerDown(ev) {
    ev.preventDefault();
    try { container.setPointerCapture(ev.pointerId); } catch(e){}
    active = true;
    pointerId = ev.pointerId;
    const b = rect();
    const cx = b.left + b.width/2;
    const cy = b.top + b.height/2;
    const dx = ev.clientX - cx;
    const dy = ev.clientY - cy;
    setKnobPos(dx, dy);
    updateRateFromPos(dx, dy);
    ensureLoop();
  }

  function onPointerMove(ev) {
    if (!active || ev.pointerId !== pointerId) return;
    const b = rect();
    const cx = b.left + b.width/2;
    const cy = b.top + b.height/2;
    const dx = ev.clientX - cx;
    const dy = ev.clientY - cy;
    setKnobPos(dx, dy);
    updateRateFromPos(dx, dy);
  }

  function onPointerUp(ev) {
    if (!active || ev.pointerId !== pointerId) return;
    active = false;
    pointerId = null;
    try { container.releasePointerCapture(ev.pointerId); } catch(e){}
    resetKnob();
    // stop rates for this axis

if (axis === 'yaw') yawRate = 0;
else if (axis === 'pitch') pitchRate = 0;
else if (axis === 'roll') rollRate = 0;
else if (axis === 'zoom') dollyRate = 0;

    maybeStopLoop();
  }















  function updateRateFromPos(dx, dy) {
    const r = radius();
    if (r <= 0) return;
    // normalized distance 0..1
    const distNorm = Math.min(1, Math.sqrt(dx*dx+dy*dy) / (r));
    if (axis === 'yaw') {
      const sign = dx >= 0 ? 1 : -1;
      yawRate = sign * distNorm * CONFIG.maxYawSpeed;
    } else if (axis === 'pitch') {
      // vertical dy controls pitch (drag up = negative dy -> look up)
      const sign = dy >= 0 ? 1 : -1;
      pitchRate = sign * distNorm * CONFIG.maxPitchSpeed * -1;

} else if (axis === 'roll') {
  // Use horizontal dx for roll (drag right -> positive roll, left -> negative)
  const sign = dx >= 0 ? 1 : -1;
  const intensity = distNorm;
  rollRate = sign * intensity * CONFIG.maxRollSpeed;



    } else if (axis === 'zoom') {
      // For zoom: use vertical displacement; drag up to zoom in (negative dy -> zoom in -> negative dollyRate)
      // Map distNorm * sign to dollyRate where negative => reduce radius (zoom in)
      const sign = dy >= 0 ? 1 : -1; // dy positive = pointer below center = zoom out
      dollyRate = sign * distNorm * CONFIG.maxZoomSpeed;
      // if you prefer horizontal drag for zoom: replace 'dy' with 'dx' and adjust sign
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

const wheelRollEl = document.getElementById('wheelRoll');    // <-- NEW
if (wheelRollEl) makeWheel(wheelRollEl, 'roll');

const wheelZoomEl = document.getElementById('wheelZoom');
if (wheelZoomEl) makeWheel(wheelZoomEl, 'zoom');








    /* -----------------------
       Spin wheel implementation (joystick-style)
       - container: element (circle)
       - axis: 'yaw' or 'pitch'
       ----------------------- *
    function makeWheel(container, axis) {
      const knob = container.querySelector('.wheel-knob');
      const rect = () => container.getBoundingClientRect();
      const radius = () => Math.min(rect().width, rect().height) * 0.45;
      let active = false;
      let pointerId = null;

      function setKnobPos(x, y) {
        // x,y relative to center (pixels)
        const r = radius();
        const limitedX = Math.max(-r, Math.min(r, x));
        const limitedY = Math.max(-r, Math.min(r, y));
        knob.style.transform = `translate(${limitedX * 0.45}px, ${limitedY * 0.45}px)`; // slight movement
      }
      function resetKnob() {
        knob.style.transform = 'translate(0,0)';
      }

      function onPointerDown(ev) {
        ev.preventDefault();
        container.setPointerCapture(ev.pointerId);
        active = true;
        pointerId = ev.pointerId;
        const b = rect();
        const cx = b.left + b.width/2;
        const cy = b.top + b.height/2;
        const dx = ev.clientX - cx;
        const dy = ev.clientY - cy;
        setKnobPos(dx, dy);
        updateRateFromPos(dx, dy);
        ensureLoop();
      }

      function onPointerMove(ev) {
        if (!active || ev.pointerId !== pointerId) return;
        const b = rect();
        const cx = b.left + b.width/2;
        const cy = b.top + b.height/2;
        const dx = ev.clientX - cx;
        const dy = ev.clientY - cy;
        setKnobPos(dx, dy);
        updateRateFromPos(dx, dy);
      }

      function onPointerUp(ev) {
        if (!active || ev.pointerId !== pointerId) return;
        active = false;
        pointerId = null;
        try { container.releasePointerCapture(ev.pointerId); } catch(e){}
        resetKnob();
        // stop rates for this axis
        if (axis === 'yaw') yawRate = 0;
        else pitchRate = 0;
        maybeStopLoop();
      }

      function updateRateFromPos(dx, dy) {
        const r = radius();
        if (r <= 0) return;
        // normalized distance 0..1
        const distNorm = Math.min(1, Math.sqrt(dx*dx+dy*dy) / (r));
        if (axis === 'yaw') {
          // use horizontal dx for yaw; sign indicates direction
          const sign = dx >= 0 ? 1 : -1;
          yawRate = sign * distNorm * CONFIG.maxYawSpeed;
        } else {
          // pitch: vertical dy controls pitch: dy negative -> up, positive -> down
          const sign = dy >= 0 ? 1 : -1;
          // invert so dragging up tilts up (negative dy -> negative pitchRate)
          pitchRate = sign * distNorm * CONFIG.maxPitchSpeed * -1;
        }
      }

      container.addEventListener('pointerdown', onPointerDown);
      container.addEventListener('pointermove', onPointerMove);
      container.addEventListener('pointerup', onPointerUp);
      container.addEventListener('pointercancel', onPointerUp);
      container.addEventListener('lostpointercapture', onPointerUp);
    }

    // Create wheels
    const wheelYawEl = document.getElementById('wheelYaw');
    const wheelPitchEl = document.getElementById('wheelPitch');
    if (wheelYawEl) makeWheel(wheelYawEl, 'yaw');
    if (wheelPitchEl) makeWheel(wheelPitchEl, 'pitch');
     */












    /* -----------------------
       Arrow pad: continuous pan/zoom while pressed
       ----------------------- */
    function wireHoldButton(id, onStart, onEnd) {
      const el = document.getElementById(id);
      if (!el) return;
      let pressed = false, pid = null;
      el.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        el.setPointerCapture(e.pointerId);
        pressed = true;
        pid = e.pointerId;
        onStart();
        ensureLoop();
      });
      el.addEventListener('pointerup', (e) => {
        if (!pressed || e.pointerId !== pid) return;
        pressed = false;
        onEnd();
        maybeStopLoop();
      });
      el.addEventListener('pointercancel', (e) => {
        if (!pressed) return;
        pressed = false;
        onEnd();
        maybeStopLoop();
      });
      el.addEventListener('lostpointercapture', (e) => {
        if (!pressed) return;
        pressed = false;
        onEnd();
        maybeStopLoop();
      });
    }

    // pan up (screen coordinates: pan up means negative panY)
    wireHoldButton('nav_up', () => { panYRate = -CONFIG.panPixelsPerSec; }, () => { panYRate = 0; });
    wireHoldButton('nav_down', () => { panYRate = CONFIG.panPixelsPerSec; }, () => { panYRate = 0; });
    wireHoldButton('nav_left', () => { panXRate = -CONFIG.panPixelsPerSec; }, () => { panXRate = 0; });
    wireHoldButton('nav_right', () => { panXRate = CONFIG.panPixelsPerSec; }, () => { panXRate = 0; });
    // center: reset target
    const centerBtn = document.getElementById('nav_center');
    if (centerBtn) centerBtn.addEventListener('click', () => { viewer.setCameraTarget(viewer._initialCameraState ? viewer._initialCameraState.target : new BABYLON.Vector3(0,0,0), true); });

    // forward/back = dolly in/out
    wireHoldButton('nav_forward', () => { dollyRate = -CONFIG.dollyPerSec; }, () => { dollyRate = 0; });
    wireHoldButton('nav_back',    () => { dollyRate = CONFIG.dollyPerSec; },  () => { dollyRate = 0; });

    // zoom in/out (alternative)
    wireHoldButton('nav_zoom_in',  () => { dollyRate = -CONFIG.dollyPerSec; }, () => { dollyRate = 0; });
    wireHoldButton('nav_zoom_out', () => { dollyRate = CONFIG.dollyPerSec;  }, () => { dollyRate = 0; });

    // discrete forward/back/up/down buttons (single click nudge)
    const btnForward = document.getElementById('btn_forward');
    if (btnForward) btnForward.addEventListener('click', () => viewer.moveForward ? viewer.moveForward(CONFIG.forwardStep) : viewer._translateCamera ? viewer._translateCamera(new BABYLON.Vector3(0,0,-CONFIG.forwardStep)) : null);
    const btnBack = document.getElementById('btn_back');
    if (btnBack) btnBack.addEventListener('click', () => viewer.moveForward ? viewer.moveForward(-CONFIG.forwardStep) : viewer._translateCamera ? viewer._translateCamera(new BABYLON.Vector3(0,0,CONFIG.forwardStep)) : null);
    const btnUp = document.getElementById('btn_up');
    if (btnUp) btnUp.addEventListener('click', () => viewer.moveUp ? viewer.moveUp(CONFIG.upStep) : viewer._translateCamera ? viewer._translateCamera(new BABYLON.Vector3(0,CONFIG.upStep,0)) : null);
    const btnDown = document.getElementById('btn_down');
    if (btnDown) btnDown.addEventListener('click', () => viewer.moveUp ? viewer.moveUp(-CONFIG.upStep) : viewer._translateCamera ? viewer._translateCamera(new BABYLON.Vector3(0,-CONFIG.upStep,0)) : null);

    // Stop everything and restore knob visuals on page hide
    window.addEventListener('pagehide', () => {
      yawRate = pitchRate = panXRate = panYRate = dollyRate = 0;
      maybeStopLoop();
    });

    // ensure loop maybe started initially (no)
    maybeStopLoop();
    console.log('mobile nav controls initialised');
  } // end initControls
})();













    async function populate(){
        try {
            const list = await viewer.fetchModelList();
            const sel = document.getElementById('modelSelect');
            sel.innerHTML = '';
            list.forEach(function(m){
                var opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.name + ' (' + (m.size ? Math.round(m.size/1024) + 'KB' : '?') + ')';
                sel.appendChild(opt);
            });
            if(list.length) {
                sel.value = list[0].id;
                await viewer.loadModelById(list[0].id);
            } else {
                sel.innerHTML = '<option disabled>No models found</option>';
            }
        } catch(err) {
            console.error('populate error', err);
            alert('Failed to load model list; check console/network.');
        }
    }

    // UI wiring (existing controls)
    document.getElementById('modelSelect').addEventListener('change', function(){ viewer.loadModelById(this.value).catch(console.error); });
    document.getElementById('autorotate').addEventListener('change', function(){ viewer.setAutoRotate(this.checked); });
    document.getElementById('zoomFitBtn').addEventListener('click', function(){ viewer.zoomToFit(); });

























    
// NAV CONTROLS wiring (robust — won't throw if DOM nodes are missing)
// Orbit sliders and numeric inputs
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
    } catch(e){
        console.warn('syncOrbitUIFromCamera failed', e);
    }
}

// wire slider -> camera if element exists
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

// numeric inputs -> camera (on change)
if (orbitAlphaVal) orbitAlphaVal.addEventListener('change', function(){ try { viewer.setCameraAlphaBetaRadius(parseFloat(this.value||viewer.camera.alpha), viewer.camera.beta, viewer.camera.radius); syncOrbitUIFromCamera(); } catch(e){} });
if (orbitBetaVal)  orbitBetaVal.addEventListener('change',  function(){ try { viewer.setCameraAlphaBetaRadius(viewer.camera.alpha, parseFloat(this.value||viewer.camera.beta), viewer.camera.radius); syncOrbitUIFromCamera(); } catch(e){} });
if (orbitRadiusVal) orbitRadiusVal.addEventListener('change', function(){ try { viewer.setCameraAlphaBetaRadius(viewer.camera.alpha, viewer.camera.beta, parseFloat(this.value||viewer.camera.radius)); syncOrbitUIFromCamera(); } catch(e){} });

// orbit speed slider => camera sensitivity
if (orbitSpeed) {
    orbitSpeed.addEventListener('input', function(){
        try { if (typeof viewer.setOrbitSensitivity === 'function') viewer.setOrbitSensitivity(parseFloat(this.value)); } catch(e){}
    });
}

// Presets and reset (guarded)
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

// PAN controls — map to the new nav_* buttons (guarded)
const panSens = document.getElementById('panSensitivity');

function safePanByPixels(dx, dy) {
    try {
        const sens = parseFloat(panSens && panSens.value ? panSens.value : 1.0);
        if (typeof viewer.panByPixels === 'function') viewer.panByPixels(dx, dy, sens);
    } catch(e){ console.warn('safePanByPixels error', e); }
}

// single discrete pan click bindings (use the nav_ ids)
['nav_up','nav_down','nav_left','nav_right'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('click', function(){
        // small step in pixels
        const step = 40;
        if (id === 'nav_up') safePanByPixels(0, -step);
        if (id === 'nav_down') safePanByPixels(0, step);
        if (id === 'nav_left') safePanByPixels(-step, 0);
        if (id === 'nav_right') safePanByPixels(step, 0);
        syncOrbitUIFromCamera();
    });
});

// reset center button (nav_center)
const centerBtn = document.getElementById('nav_center');
if (centerBtn) centerBtn.addEventListener('click', function(){
    try {
        if (viewer._initialCameraState && viewer._initialCameraState.target) viewer.setCameraTarget(viewer._initialCameraState.target, true);
        else viewer.setCameraTarget({x:0,y:0,z:0}, true);
    } catch(e){ console.warn('centerBtn error', e); }
    setTimeout(syncOrbitUIFromCamera, 350);
});

// forward/back/up/down buttons (discrete nudges)
const btnForward = document.getElementById('btn_forward');
if (btnForward) btnForward.addEventListener('click', function(){
    try { if (typeof viewer.moveForward === 'function') viewer.moveForward(0.6); else viewer.changeRadiusBy ? viewer.changeRadiusBy(-0.6) : null; } catch(e){}
});
const btnBack = document.getElementById('btn_back');
if (btnBack) btnBack.addEventListener('click', function(){
    try { if (typeof viewer.moveForward === 'function') viewer.moveForward(-0.6); else viewer.changeRadiusBy ? viewer.changeRadiusBy(0.6) : null; } catch(e){}
});
const btnUp = document.getElementById('btn_up');
if (btnUp) btnUp.addEventListener('click', function(){
    try { if (typeof viewer.moveUp === 'function') viewer.moveUp(0.6); else viewer.setCameraTarget ? viewer.setCameraTarget(viewer.camera.target.add(new BABYLON.Vector3(0,0.6,0)), true) : null; } catch(e){}
});
const btnDown = document.getElementById('btn_down');
if (btnDown) btnDown.addEventListener('click', function(){
    try { if (typeof viewer.moveUp === 'function') viewer.moveUp(-0.6); else viewer.setCameraTarget ? viewer.setCameraTarget(viewer.camera.target.add(new BABYLON.Vector3(0,-0.6,0)), true) : null; } catch(e){}
});

// applyTarget input (guard)
const applyTargetBtn = document.getElementById('applyTarget');
if (applyTargetBtn) {
    applyTargetBtn.addEventListener('click', function(){
        try {
            const x = parseFloat(document.getElementById('targetX')?.value || viewer.camera.target.x);
            const y = parseFloat(document.getElementById('targetY')?.value || viewer.camera.target.y);
            const z = parseFloat(document.getElementById('targetZ')?.value || viewer.camera.target.z);
            viewer.setCameraTarget({x,y,z}, true);
            setTimeout(syncOrbitUIFromCamera, 350);
        } catch(e){ console.warn('applyTarget click error', e); }
    });
}

// Make sure UI initial sync is called after camera is created
setTimeout(syncOrbitUIFromCamera, 250);









    // initialize
    populate();
    // keep UI updated occasionally (useful if camera is auto-rotating)
    setInterval(syncOrbitUIFromCamera, 600);

    // keep the orbit speed in the camera at start
    viewer.setOrbitSensitivity(parseFloat(orbitSpeed.value || 1.0));
})();
</script>











<?php /*
<!-- initialization (classic inline script) -->
<script>
(function(){
    // instantiate viewer
    var viewer = new BabylonViewerJS({
        canvasId: 'renderCanvas',
        modelsListEndpoint: '/babylon_models.php',
        modelEndpoint: '/babylon_model.php'
    });

    async function populate(){
        try {
            const list = await viewer.fetchModelList();
            const sel = document.getElementById('modelSelect');
            sel.innerHTML = '';
            list.forEach(function(m){
                var opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.name + ' (' + (m.size ? Math.round(m.size/1024) + 'KB' : '?') + ')';
                sel.appendChild(opt);
            });
            if(list.length) {
                sel.value = list[0].id;
                await viewer.loadModelById(list[0].id);
            } else {
                sel.innerHTML = '<option disabled>No models found</option>';
            }
        } catch(err) {
            console.error('populate error', err);
            alert('Failed to load model list; check console/network.');
        }
    }

//    document.getElementById('reloadBtn').addEventListener('click', populate);
    document.getElementById('modelSelect').addEventListener('change', function(){ viewer.loadModelById(this.value).catch(console.error); });
    document.getElementById('autorotate').addEventListener('change', function(){ viewer.setAutoRotate(this.checked); });
    document.getElementById('zoomFitBtn').addEventListener('click', function(){ viewer.zoomToFit(); });

    populate();













    // after viewer is created...
document.getElementById('uploadSnapshotBtn').addEventListener('click', async function(){
    try {
        // show user some quick visual feedback (replace with your Toast if available)
        this.disabled = true;
        this.textContent = 'Saving…';

        const result = await captureCanvasAndUpload({
            endpointPath: '/save_snapshot.php', // change if your endpoint location differs (e.g. '/api/save_snapshot.php')
            quality: 0.85,
            filenameHint: 'babylon_snapshot' // optional
        });

        // success
        console.log('Snapshot saved', result);
        alert('Snapshot saved: ' + result.url); // or use Toast.show(result.url, 'success')
    } catch (err) {
        console.error('Snapshot upload failed', err);
        alert('Snapshot upload failed: ' + (err.message || err));
    } finally {
        this.disabled = false;
        this.textContent = '💾 Save snapshot to server';
    }
});











})();
</script>
 */ ?>


        </body>
        </html>
        <?php
    }

    /**
     * Utility — returns filesystem path of models directory.
     */
    public function getModelsDirPath(): string
    {
        return $this->projectRoot . $this->modelsDirRel;
    }
}
