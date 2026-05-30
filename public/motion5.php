--- START OF FILE Paste January 20, 2026 - 8:30PM ---

<?php
// --- SERVER-SIDE CACHE BUSTING ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$charFile = 'character.webm';
$bgFile = 'background.jpg';
$charVer = file_exists($charFile) ? filemtime($charFile) : time();
$bgVer = file_exists($bgFile) ? filemtime($bgFile) : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <title>Three.js Motion - Studio v24 (Live Rec Edition)</title>
    <style>
        body { margin: 0; overflow: hidden; background-color: #000; font-family: sans-serif; touch-action: none; }
        #info { position: absolute; top: 10px; width: 100%; text-align: center; color: white; pointer-events: none; text-shadow: 1px 1px 2px black; font-size: 0.9rem; opacity: 0.8; z-index: 10; }
        #loading { position: absolute; top: 50%; width: 100%; text-align: center; color: #00ff00; font-family: monospace; transform: translateY(-50%); pointer-events: none; transition: opacity 0.5s; }
        
        #gui-container { position: absolute; top: 20px; right: 20px; z-index: 1001; user-select: none; -webkit-user-select: none; }
        .lil-gui .title { cursor: move !important; }

        /* RECORDING INDICATORS */
        .indicator {
            position: absolute; top: 20px; left: 20px;
            color: white; font-weight: bold; font-family: monospace;
            display: none; align-items: center; z-index: 1002;
            padding: 8px 12px; border-radius: 4px; pointer-events: auto;
        }
        #rec-indicator { background: rgba(255, 0, 0, 0.6); pointer-events: none; }
        #rec-dot { width: 12px; height: 12px; background-color: white; border-radius: 50%; margin-right: 8px; animation: blink 1s infinite; }
        @keyframes blink { 50% { opacity: 0; } }

        #error-log {
            display: none; position: absolute; bottom: 0; left: 0; width: 100%; height: 150px;
            background: rgba(50, 0, 0, 0.9); color: #ffaaaa; font-family: monospace; font-size: 12px;
            padding: 10px; overflow-y: scroll; z-index: 9999;
        }
    </style>
    
    <script type="importmap">
    {
        "imports": {
            "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
            "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/",
            "lil-gui": "https://unpkg.com/lil-gui@0.19.1/dist/lil-gui.esm.min.js"
        }
    }
    </script>
</head>
<body>

    <div id="error-log"></div>
    <div id="info">Live Recording Mode</div>
    <div id="loading">Loading Assets...</div>
    
    <div id="rec-indicator" class="indicator"><div id="rec-dot"></div><span id="rec-time">REC 00:00</span></div>
    <div id="gui-container"></div>

    <script type="module">
        import * as THREE from 'three';
        import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
        import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
        import { AfterimagePass } from 'three/addons/postprocessing/AfterimagePass.js';
        import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
        import GUI from 'lil-gui';

        const ASSET_VERSIONS = { char: "<?php echo $charVer; ?>", bg: "<?php echo $bgVer; ?>" };

        // --- CONFIGURATION ---
        const params = {
            speedGear: 'Walk (0-1)', scrollSpeed: 0.5, reverseScroll: false,
            swaySpeed: 1.0, swayAmp: 0.0, vibration: 0.0, blurStrength: 0.85,
            shipScale: 1.0, altitude: 20, videoSpeed: 1.0,
            loopStart: 0.0, loopEnd: 10.0, 
            rotX: -1.57, rotY: 0.0, rotZ: 0.0,
            bgRotX: 0.0, bgRotY: 0.0, bgRotZ: 0.0, bgDistance: 0.0,
            bgBrightness: 1.0, bgTint: '#ffffff',
            recBitrate: 8.0 // Mbps
        };

        const texParams = { repeatX: 4, repeatY: 2, offsetX: 0.5, offsetY: 0.0 };
        const gearMap = { 'Walk (0-1)': 1.0, 'Run (0-5)': 5.0, 'Fly (0-20)': 20.0, 'Warp (0-100)': 100.0 };

        // --- VARIABLES ---
        let mediaRecorder, recordedChunks = [], isRecording = false, recStartTime, recInterval, recController;
        let scrollSpeedController, startCtrl, endCtrl;
        let camera, scene, renderer, composer, afterimagePass, controls;
        let shipMesh, cylinderMesh, bgGroup, bgTexture, gui;
        let videoEl, videoTexture; 
        let clock = new THREE.Clock();
        let baseShipScale = new THREE.Vector3(1, 1, 1);
        
        const cylinderRadius = 800; const cylinderLength = 2000; 

        // --- SYSTEM ---
        const system = {
            savePreset: function() {
                const payload = { params: params, texParams: texParams };
                fetch('save_motion_controls.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) })
                .then(r => r.ok ? alert("Saved") : alert("Error saving"))
                .catch(e => console.error(e));
            },
            loadPreset: function() {
                fetch('flight_controls.json?t=' + Date.now()).then(r => { if(!r.ok) throw new Error(); return r.json(); }).then(data => {
                    if(data.params) Object.assign(params, data.params);
                    if(data.texParams) Object.assign(texParams, data.texParams);
                    if(gui) gui.controllersRecursive().forEach(c => c.updateDisplay());
                    if(shipMesh) shipMesh.scale.copy(baseShipScale).multiplyScalar(params.shipScale);
                    if(afterimagePass) afterimagePass.uniforms['damp'].value = params.blurStrength;
                    if(videoEl) videoEl.playbackRate = params.videoSpeed;
                    camera.position.y = params.altitude; camera.lookAt(0,0,0);
                    updateTextureParams(); updateBgColor(); updateSpeedGear();
                }).catch(() => console.log("No preset found."));
            },
            toggleRecording: function() {
                if(!isRecording) {
                    const success = startLiveRecording();
                    if(success) { isRecording = true; if(recController) recController.name('⬛ Stop & Save'); }
                } else {
                    stopLiveRecording();
                    isRecording = false; if(recController) recController.name('🔴 Start Live Rec');
                }
            }
        };

        try { init(); animate(); makeGuiDraggable(); system.loadPreset(); } 
        catch(e) { logError("Init Crash", e); }

        function logError(ctx, e) {
            const box = document.getElementById('error-log');
            box.style.display = 'block';
            box.innerHTML += `<p><strong>${ctx}:</strong> ${e.message}</p>`;
            console.error(e);
        }

        function init() {
            const container = document.createElement('div');
            document.body.appendChild(container);

            scene = new THREE.Scene();
            scene.fog = new THREE.Fog(0x000000, 20, 300); 

            camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 2000);
            camera.position.set(0, params.altitude, 40);
            camera.lookAt(0, 0, 0);

            renderer = new THREE.WebGLRenderer({ antialias: false, preserveDrawingBuffer: true });
            renderer.setPixelRatio(window.devicePixelRatio);
            renderer.setSize(window.innerWidth, window.innerHeight);
            renderer.sortObjects = true;
            container.appendChild(renderer.domElement);

            const loadMan = new THREE.LoadingManager(() => { document.getElementById('loading').style.opacity = 0; });
            const texLoader = new THREE.TextureLoader(loadMan);

            bgTexture = texLoader.load('background.jpg?v=' + ASSET_VERSIONS.bg);
            bgTexture.colorSpace = THREE.SRGBColorSpace;
            bgTexture.wrapS = THREE.RepeatWrapping; bgTexture.wrapT = THREE.RepeatWrapping;
            updateTextureParams();

            videoEl = document.createElement('video');
            videoEl.src = 'character.webm?v=' + ASSET_VERSIONS.char; 
            videoEl.loop = true; videoEl.muted = true; videoEl.playsInline = true; videoEl.crossOrigin = 'anonymous';
            videoEl.preload = 'auto'; 
            
            // DOM ATTACH (Hidden)
            videoEl.style.position = 'absolute'; videoEl.style.top = '-9999px';
            document.body.appendChild(videoEl); 
            videoEl.play().catch(e => console.warn("Autoplay blocked", e));

            videoTexture = new THREE.VideoTexture(videoEl);
            videoTexture.colorSpace = THREE.SRGBColorSpace;
            videoTexture.minFilter = THREE.LinearFilter; videoTexture.magFilter = THREE.LinearFilter;

            videoEl.addEventListener('loadedmetadata', function() {
                const aspect = this.videoWidth / this.videoHeight;
                baseShipScale.set(5 * aspect, 5, 1);
                if(shipMesh) shipMesh.scale.copy(baseShipScale).multiplyScalar(params.shipScale);
                params.loopEnd = this.duration;
                if(startCtrl) startCtrl.max(this.duration).updateDisplay();
                if(endCtrl) { endCtrl.max(this.duration); endCtrl.setValue(this.duration); }
                if(gui) gui.controllersRecursive().forEach(c => c.updateDisplay());
            });

            bgGroup = new THREE.Group(); scene.add(bgGroup);
            const cylGeo = new THREE.CylinderGeometry(cylinderRadius, cylinderRadius, cylinderLength, 128, 1, true);
            const cylMat = new THREE.MeshBasicMaterial({ map: bgTexture, side: THREE.DoubleSide, color: 0xffffff });
            cylinderMesh = new THREE.Mesh(cylGeo, cylMat);
            cylinderMesh.rotation.z = Math.PI / 2; cylinderMesh.position.y = -cylinderRadius; 
            bgGroup.add(cylinderMesh);

            const shipGeo = new THREE.PlaneGeometry(1, 1);
            const shipMat = new THREE.MeshBasicMaterial({ map: videoTexture, transparent: true, side: THREE.DoubleSide, alphaTest: 0.1, depthTest: false });
            shipMesh = new THREE.Mesh(shipGeo, shipMat);
            shipMesh.renderOrder = 999; shipMesh.position.set(0,0,0);
            shipMesh.rotation.x = params.rotX;
            scene.add(shipMesh);

            composer = new EffectComposer(renderer);
            composer.addPass(new RenderPass(scene, camera));
            afterimagePass = new AfterimagePass();
            afterimagePass.uniforms['damp'].value = params.blurStrength;
            composer.addPass(afterimagePass);

            controls = new OrbitControls(camera, renderer.domElement);
            controls.enablePan = false; controls.enableDamping = true;
            controls.minDistance = 10; controls.maxDistance = 200;
            
            setupGUI(); updateBgColor(); window.addEventListener('resize', onWindowResize);
        }

        function setupGUI() {
            gui = new GUI({ title: 'Studio v24 (Live Rec)', autoPlace: false, width: 300 });
            document.getElementById('gui-container').appendChild(gui.domElement);

            const f1 = gui.addFolder('Motion & Direction');
            f1.add(params, 'speedGear', Object.keys(gearMap)).name('Speed Gear').onChange(updateSpeedGear);
            scrollSpeedController = f1.add(params, 'scrollSpeed', 0, 1).name('Ground Speed');
            f1.add(params, 'reverseScroll');
            f1.add(params, 'videoSpeed', 0, 4.0).name('Anim Speed').onChange(v => { if(videoEl) videoEl.playbackRate = v; });
            f1.add(params, 'swayAmp', 0, 20); f1.add(params, 'swaySpeed', 0, 5); f1.add(params, 'vibration', 0, 1);
            f1.add(params, 'shipScale', 0.1, 3.0).onChange(v => { if(shipMesh) shipMesh.scale.copy(baseShipScale).multiplyScalar(v); });
            f1.add(params, 'blurStrength', 0, 0.99).onChange(v => afterimagePass.uniforms['damp'].value = v);

            const fAnim = gui.addFolder('Animation Loop');
            startCtrl = fAnim.add(params, 'loopStart', 0, 10);
            endCtrl = fAnim.add(params, 'loopEnd', 0, 10);
            fAnim.close();

            const fAtm = gui.addFolder('Atmosphere');
            fAtm.add(params, 'bgBrightness', 0.0, 1.0).onChange(updateBgColor);
            fAtm.addColor(params, 'bgTint').onChange(updateBgColor);
            fAtm.close();

            const fBg = gui.addFolder('Background Config');
            fBg.add(params, 'bgRotX', -3.14, 3.14); fBg.add(params, 'bgRotY', -3.14, 3.14); fBg.add(params, 'bgRotZ', -3.14, 3.14);
            fBg.add(params, 'bgDistance', 0, 200);
            fBg.add(params, 'altitude', 0, 200).onChange(v => { camera.position.y = v; camera.lookAt(0,0,0); });
            fBg.close();

            const fLive = gui.addFolder('Live Recording Config');
            recController = fLive.add(system, 'toggleRecording').name('🔴 Start Live Rec');
            fLive.add(params, 'recBitrate', 1.0, 20.0).name('Bitrate (Mbps)');
            fLive.open();

            const fSys = gui.addFolder('System');
            fSys.add(system, 'savePreset').name('💾 Save'); fSys.add(system, 'loadPreset').name('📂 Load');
            fSys.close();
        }

        function updateScene(delta, elapsedTime) {
            if(bgGroup) { bgGroup.rotation.x = params.bgRotX; bgGroup.rotation.y = params.bgRotY; bgGroup.rotation.z = params.bgRotZ; }
            if(cylinderMesh) {
                const dir = params.reverseScroll ? 1 : -1;
                cylinderMesh.rotation.x += params.scrollSpeed * 0.001 * dir * delta * 60; 
                cylinderMesh.position.y = -cylinderRadius - params.bgDistance; 
            }
            if(shipMesh) {
                const xSway = Math.sin(elapsedTime * params.swaySpeed) * params.swayAmp;
                const vibration = Math.sin(elapsedTime * 60) * params.vibration; 
                shipMesh.position.x = xSway; shipMesh.position.z = vibration; 
                shipMesh.rotation.x = params.rotX;
                let bankingOffset = 0;
                if (params.swayAmp > 0) bankingOffset = -Math.sin(elapsedTime * params.swaySpeed) * 0.3;
                shipMesh.rotation.y = params.rotY + bankingOffset;
                shipMesh.rotation.z = params.rotZ;
            }
        }

        function animate() {
            requestAnimationFrame(animate);
            const delta = clock.getDelta();
            const elapsedTime = clock.getElapsedTime();

            if (videoEl && videoEl.duration > 0 && !videoEl.paused) {
                const start = Math.min(params.loopStart, params.loopEnd);
                const end = Math.max(params.loopStart, params.loopEnd);
                if (end > start) {
                    if (videoEl.currentTime >= end) videoEl.currentTime = start;
                }
            }
            updateScene(delta, elapsedTime);
            controls.update(); composer.render();
        }

        function uploadRenderedFile(blob) {
            const formData = new FormData();
            formData.append('video', blob); formData.append('format', 'video/webm');
            const infoDiv = document.getElementById('info');
            infoDiv.innerText = "UPLOADING..."; infoDiv.style.color = "yellow";
            fetch('save_motion_video.php', { method: 'POST', body: formData })
            .then(r => r.ok ? r.text() : Promise.reject('Upload Failed'))
            .then(f => alert("Render Saved:\n" + f))
            .catch(e => {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a'); a.href = url; a.download = 'live_rec.webm';
                document.body.appendChild(a); a.click();
                setTimeout(() => URL.revokeObjectURL(url), 100);
            })
            .finally(() => { infoDiv.innerText = "Live Recording Mode"; infoDiv.style.color = "white"; });
        }

        // --- ROBUST LIVE RECORDING ---
        function startLiveRecording() {
            try {
                const canvas = document.querySelector('canvas');
                if (!canvas.captureStream) return false;
                
                // Ensure high quality capture
                const stream = canvas.captureStream(60); // Request 60 FPS stream
                
                const bps = params.recBitrate * 1000000;
                let options = { mimeType: 'video/webm;codecs=vp9', videoBitsPerSecond: bps };
                if(!MediaRecorder.isTypeSupported(options.mimeType)) {
                    options = { mimeType: 'video/webm', videoBitsPerSecond: bps };
                }

                mediaRecorder = new MediaRecorder(stream, options);
                recordedChunks = [];
                mediaRecorder.ondataavailable = e => { if (e.data.size > 0) recordedChunks.push(e.data); };
                mediaRecorder.onstop = () => { 
                    const blob = new Blob(recordedChunks, { type: 'video/webm' }); 
                    uploadRenderedFile(blob); 
                };
                
                mediaRecorder.start();
                document.getElementById('rec-indicator').style.display = 'flex';
                recStartTime = Date.now(); updateTimer(); recInterval = setInterval(updateTimer, 1000);
                return true;
            } catch (e) { alert("Rec Error: " + e); return false; }
        }

        function stopLiveRecording() {
            if(!mediaRecorder) return; 
            mediaRecorder.stop(); 
            clearInterval(recInterval);
            document.getElementById('rec-indicator').style.display = 'none';
        }

        function updateSpeedGear() { const max = gearMap[params.speedGear]; if(scrollSpeedController) { scrollSpeedController.max(max); scrollSpeedController.min(0); scrollSpeedController.updateDisplay(); }}
        function updateBgColor() { if(cylinderMesh && cylinderMesh.material) { const color = new THREE.Color(params.bgTint); color.multiplyScalar(params.bgBrightness); cylinderMesh.material.color.copy(color); }}
        function updateTextureParams() { if(bgTexture) { bgTexture.repeat.set(texParams.repeatX, texParams.repeatY); bgTexture.offset.set(texParams.offsetX, texParams.offsetY); }}
        function updateTimer() { const diff = Math.floor((Date.now() - recStartTime) / 1000); const m = Math.floor(diff / 60).toString().padStart(2, '0'); const s = (diff % 60).toString().padStart(2, '0'); document.getElementById('rec-time').innerText = `REC ${m}:${s}`; }
        function onWindowResize() { camera.aspect = window.innerWidth / window.innerHeight; camera.updateProjectionMatrix(); renderer.setSize(window.innerWidth, window.innerHeight); composer.setSize(window.innerWidth, window.innerHeight); }
        function makeGuiDraggable() {
            const container = document.getElementById('gui-container');
            let isDragging = false, hasMoved = false; let startX, startY, initialLeft, initialTop;
            const onDown = (e) => { if(!e.target.closest('.title')) return; isDragging = true; hasMoved = false; const clientX = e.clientX || e.touches[0].clientX; const clientY = e.clientY || e.touches[0].clientY; startX = clientX; startY = clientY; const rect = container.getBoundingClientRect(); initialLeft = rect.left; initialTop = rect.top; };
            const onMove = (e) => { if (!isDragging) return; const clientX = e.clientX || (e.touches ? e.touches[0].clientX : 0); const clientY = e.clientY || (e.touches ? e.touches[0].clientY : 0); if(Math.abs(clientX - startX) < 5 && Math.abs(clientY - startY) < 5) return; hasMoved = true; container.style.right = 'auto'; container.style.left = `${initialLeft + (clientX - startX)}px`; container.style.top = `${initialTop + (clientY - startY)}px`; if(e.cancelable) e.preventDefault(); };
            const onUp = () => isDragging = false; const onClick = (e) => { if (hasMoved) { e.stopImmediatePropagation(); e.stopPropagation(); } };
            container.addEventListener('mousedown', onDown); window.addEventListener('mousemove', onMove); window.addEventListener('mouseup', onUp); container.addEventListener('touchstart', onDown, {passive: false}); window.addEventListener('touchmove', onMove, {passive: false}); window.addEventListener('touchend', onUp); container.addEventListener('click', onClick, { capture: true });
        }
    </script>
</body>
</html>