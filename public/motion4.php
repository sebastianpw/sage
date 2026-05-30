<?php
// --- SERVER-SIDE CACHE BUSTING ---
// This forces the browser to check if the file changed every time you load the page.

// 1. Prevent caching of this PHP page itself
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 2. Get the last modification time of the assets
// If the file exists, we use its timestamp. If not, we use current time.
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
    <title>Three.js Motion - Cache Fixed</title>
    <style>
        body {
            margin: 0;
            overflow: hidden;
            background-color: #000;
            font-family: sans-serif;
            touch-action: none;
        }
        #info {
            position: absolute;
            top: 10px;
            width: 100%;
            text-align: center;
            color: white;
            pointer-events: none;
            text-shadow: 1px 1px 2px black;
            font-size: 0.9rem;
            opacity: 0.8;
            z-index: 10;
        }
        #loading {
            position: absolute;
            top: 50%;
            width: 100%;
            text-align: center;
            color: #00ff00;
            font-family: monospace;
            transform: translateY(-50%);
            pointer-events: none;
            transition: opacity 0.5s;
        }
        
        #gui-container {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1001;
            user-select: none;
            -webkit-user-select: none;
        }
        .lil-gui .title { cursor: move !important; }

        #rec-indicator {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            font-weight: bold;
            font-family: monospace;
            display: none; 
            align-items: center;
            z-index: 1002;
            background: rgba(255, 0, 0, 0.6);
            padding: 8px 12px;
            border-radius: 4px;
            pointer-events: none;
        }
        #rec-dot {
            width: 12px;
            height: 12px;
            background-color: white;
            border-radius: 50%;
            margin-right: 8px;
            animation: blink 1s infinite;
        }
        @keyframes blink { 50% { opacity: 0; } }
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

    <div id="info">System: Cache-Busting Active (Timestamp: <?php echo $charVer; ?>)</div>
    <div id="loading">Loading Assets...</div>
    
    <div id="rec-indicator">
        <div id="rec-dot"></div>
        <span id="rec-time">REC 00:00</span>
    </div>

    <div id="gui-container"></div>

    <script type="module">
        import * as THREE from 'three';
        import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
        import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
        import { AfterimagePass } from 'three/addons/postprocessing/AfterimagePass.js';
        import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
        import GUI from 'lil-gui';

        // --- INJECT PHP VERSIONS INTO JS ---
        const ASSET_VERSIONS = {
            char: "<?php echo $charVer; ?>",
            bg: "<?php echo $bgVer; ?>"
        };

        // --- CONFIGURATION ---
        const params = {
            // Motion
            speedGear: 'Walk (0-1)', 
            scrollSpeed: 0.5,        
            reverseScroll: false,
            swaySpeed: 1.0,
            swayAmp: 0.0,
            vibration: 0.0,
            blurStrength: 0.85,
            
            // Assets
            shipScale: 1.0,
            altitude: 20, 
            videoSpeed: 1.0,
            
            // Character Orientation
            rotX: -1.57, rotY: 0.0, rotZ: 0.0,

            // Background Orientation & Position
            bgRotX: 0.0, bgRotY: 0.0, bgRotZ: 0.0,
            bgDistance: 0.0,

            // Atmosphere
            bgBrightness: 1.0,
            bgTint: '#ffffff'
        };

        const texParams = {
            repeatX: 4, repeatY: 2, offsetX: 0.5, offsetY: 0.0
        };

        const gearMap = {
            'Walk (0-1)': 1.0,
            'Run (0-5)': 5.0,
            'Fly (0-20)': 20.0,
            'Warp (0-100)': 100.0
        };

        // --- RECORDING VARIABLES ---
        let mediaRecorder;
        let recordedChunks = [];
        let isRecording = false;
        let recStartTime;
        let recInterval;
        let recController;
        let scrollSpeedController; 

        const system = {
            savePreset: function() {
                const payload = { params: params, texParams: texParams };
                fetch('save_motion_controls.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                })
                .then(r => r.ok ? alert("Controls Saved") : alert("Error saving controls"))
                .catch(e => console.error(e));
            },
            
            loadPreset: function() {
                fetch('flight_controls.json?t=' + Date.now())
                .then(r => { if(!r.ok) throw new Error(); return r.json(); })
                .then(data => {
                    if(data.params) Object.assign(params, data.params);
                    if(data.texParams) Object.assign(texParams, data.texParams);
                    
                    if(gui) gui.controllersRecursive().forEach(c => c.updateDisplay());
                    
                    if(shipMesh) shipMesh.scale.copy(baseShipScale).multiplyScalar(params.shipScale);
                    if(afterimagePass) afterimagePass.uniforms['damp'].value = params.blurStrength;
                    if(videoEl) videoEl.playbackRate = params.videoSpeed;
                    
                    camera.position.y = params.altitude;
                    camera.lookAt(0,0,0);
                    
                    updateTextureParams();
                    updateBgColor();
                    updateSpeedGear(); 
                })
                .catch(() => console.log("No preset found."));
            },

            toggleRecording: function() {
                if(!isRecording) {
                    const success = startRecording();
                    if(success) {
                        isRecording = true;
                        if(recController) recController.name('⬛ Stop & Save');
                    }
                } else {
                    stopRecording();
                    isRecording = false;
                    if(recController) recController.name('🔴 Start Recording');
                }
            }
        };

        let camera, scene, renderer, composer, afterimagePass, controls;
        let shipMesh, cylinderMesh, bgGroup, bgTexture, gui;
        let videoEl; 
        let clock = new THREE.Clock();
        let baseShipScale = new THREE.Vector3(1, 1, 1);
        
        // Massive Radius for flatter world
        const cylinderRadius = 800;
        const cylinderLength = 2000; 

        init();
        animate();
        makeGuiDraggable(); 
        system.loadPreset(); 

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

            const loadMan = new THREE.LoadingManager(() => {
                document.getElementById('loading').style.opacity = 0;
            });
            const texLoader = new THREE.TextureLoader(loadMan);

            // 1. BACKGROUND (PHP Versioned)
            bgTexture = texLoader.load('background.jpg?v=' + ASSET_VERSIONS.bg);
            bgTexture.colorSpace = THREE.SRGBColorSpace;
            bgTexture.wrapS = THREE.RepeatWrapping;
            bgTexture.wrapT = THREE.RepeatWrapping;
            updateTextureParams();

            // 2. VIDEO SPRITE (PHP Versioned)
            videoEl = document.createElement('video');
            
            // This is the key fix: We use the server-side file timestamp
            videoEl.src = 'character.webm?v=' + ASSET_VERSIONS.char; 
            
            videoEl.loop = true;
            videoEl.muted = true; 
            videoEl.playsInline = true; 
            videoEl.crossOrigin = 'anonymous';

            // ATTACH TO DOM (Hidden)
            videoEl.style.position = 'absolute';
            videoEl.style.top = '-9999px';
            videoEl.style.left = '-9999px';
            videoEl.style.width = '1px';
            videoEl.style.height = '1px';
            document.body.appendChild(videoEl); 

            videoEl.play();

            const videoTexture = new THREE.VideoTexture(videoEl);
            videoTexture.colorSpace = THREE.SRGBColorSpace;
            videoTexture.minFilter = THREE.LinearFilter;
            videoTexture.magFilter = THREE.LinearFilter;

            videoEl.addEventListener('loadedmetadata', function() {
                const aspect = this.videoWidth / this.videoHeight;
                baseShipScale.set(5 * aspect, 5, 1);
                if(shipMesh) shipMesh.scale.copy(baseShipScale).multiplyScalar(params.shipScale);
            });

            // 3. BACKGROUND GROUP
            bgGroup = new THREE.Group();
            scene.add(bgGroup);

            const cylGeo = new THREE.CylinderGeometry(cylinderRadius, cylinderRadius, cylinderLength, 128, 1, true);
            
            const cylMat = new THREE.MeshBasicMaterial({ 
                map: bgTexture, 
                side: THREE.DoubleSide,
                color: 0xffffff 
            });
            
            cylinderMesh = new THREE.Mesh(cylGeo, cylMat);
            cylinderMesh.rotation.z = Math.PI / 2; 
            cylinderMesh.position.y = -cylinderRadius; 
            
            bgGroup.add(cylinderMesh);

            // 4. CHARACTER
            const shipGeo = new THREE.PlaneGeometry(1, 1);
            const shipMat = new THREE.MeshBasicMaterial({
                map: videoTexture,
                transparent: true, 
                side: THREE.DoubleSide,
                alphaTest: 0.1,
                depthTest: false 
            });
            shipMesh = new THREE.Mesh(shipGeo, shipMat);
            shipMesh.position.set(0, 0, 0); 
            shipMesh.renderOrder = 999; 

            shipMesh.rotation.x = params.rotX;
            shipMesh.rotation.y = params.rotY;
            shipMesh.rotation.z = params.rotZ;

            scene.add(shipMesh);

            // Post Processing
            composer = new EffectComposer(renderer);
            composer.addPass(new RenderPass(scene, camera));
            afterimagePass = new AfterimagePass();
            afterimagePass.uniforms['damp'].value = params.blurStrength;
            composer.addPass(afterimagePass);

            controls = new OrbitControls(camera, renderer.domElement);
            controls.enablePan = false;
            controls.enableDamping = true;
            controls.minDistance = 10;
            controls.maxDistance = 200;
            
            setupGUI();
            updateBgColor(); 
            window.addEventListener('resize', onWindowResize);
        }

        function setupGUI() {
            gui = new GUI({ title: 'Production Studio', autoPlace: false, width: 300 });
            document.getElementById('gui-container').appendChild(gui.domElement);

            const f1 = gui.addFolder('Motion & Direction');
            f1.add(params, 'speedGear', Object.keys(gearMap)).name('Speed Gear')
              .onChange(updateSpeedGear);
            scrollSpeedController = f1.add(params, 'scrollSpeed', 0, 1).name('Ground Speed');
            f1.add(params, 'reverseScroll').name('Reverse Scroll');
            f1.add(params, 'videoSpeed', 0, 4.0).name('Anim Speed')
                .onChange(v => { if(videoEl) videoEl.playbackRate = v; });
            f1.add(params, 'swayAmp', 0, 20).name('Sway Range');
            f1.add(params, 'swaySpeed', 0, 5).name('Sway Speed');
            f1.add(params, 'vibration', 0, 1).name('Vibration');
            f1.add(params, 'shipScale', 0.1, 3.0).name('Scale').onChange(v => { 
                if(shipMesh) shipMesh.scale.copy(baseShipScale).multiplyScalar(v); 
            });
            f1.add(params, 'blurStrength', 0, 0.99).name('Blur').onChange(v => afterimagePass.uniforms['damp'].value = v);

            const fAtm = gui.addFolder('Atmosphere');
            fAtm.add(params, 'bgBrightness', 0.0, 1.0).name('Brightness').onChange(updateBgColor);
            fAtm.addColor(params, 'bgTint').name('Tint Color').onChange(updateBgColor);
            fAtm.open();

            const fRot = gui.addFolder('Character Orientation');
            fRot.add(params, 'rotX', -3.14, 3.14).name('Rot X (Pitch)');
            fRot.add(params, 'rotY', -3.14, 3.14).name('Rot Y (Yaw)');
            fRot.add(params, 'rotZ', -3.14, 3.14).name('Rot Z (Roll)');
            fRot.close();

            const fBg = gui.addFolder('Background Config');
            fBg.add(params, 'bgRotX', -3.14, 3.14).name('World X');
            fBg.add(params, 'bgRotY', -3.14, 3.14).name('World Y');
            fBg.add(params, 'bgRotZ', -3.14, 3.14).name('World Z');
            fBg.add(params, 'bgDistance', 0, 200).name('Push BG Back');
            fBg.add(params, 'altitude', 0, 200).name('Cam Height').onChange(v => {
                camera.position.y = v;
                camera.lookAt(0,0,0);
            });
            fBg.close();

            const f2 = gui.addFolder('Texture Mapping');
            f2.add(texParams, 'repeatX', 1, 10).name('Zoom X').onChange(updateTextureParams);
            f2.add(texParams, 'repeatY', 1, 10).name('Zoom Y').onChange(updateTextureParams);
            f2.add(texParams, 'offsetX', 0, 1).name('Offset X').onChange(updateTextureParams);
            f2.add(texParams, 'offsetY', 0, 1).name('Offset Y').onChange(updateTextureParams);
            f2.close();

            const f3 = gui.addFolder('Live Recording');
            recController = f3.add(system, 'toggleRecording').name('🔴 Start Recording');
            f3.open();

            const f4 = gui.addFolder('System');
            f4.add(system, 'savePreset').name('💾 Save Controls');
            f4.add(system, 'loadPreset').name('📂 Load Controls');
            f4.close();
        }

        // --- NEW HELPERS ---
        function updateSpeedGear() {
            const max = gearMap[params.speedGear];
            if(scrollSpeedController) {
                // Update the controller min/max without resetting value if possible
                scrollSpeedController.max(max);
                scrollSpeedController.min(0);
                scrollSpeedController.updateDisplay();
            }
        }

        function updateBgColor() {
            if(cylinderMesh && cylinderMesh.material) {
                // 1. Create color from Tint
                const color = new THREE.Color(params.bgTint);
                // 2. Multiply by Brightness (Dimmer)
                color.multiplyScalar(params.bgBrightness);
                // 3. Apply
                cylinderMesh.material.color.copy(color);
            }
        }

        // --- STANDARD FUNCTIONS ---
        function startRecording() {
            try {
                recordedChunks = [];
                const canvas = document.querySelector('canvas');
                if (!canvas || !canvas.captureStream) {
                    alert("Browser doesn't support captureStream"); return false;
                }
                renderer.setPixelRatio(1);
                renderer.setSize(window.innerWidth, window.innerHeight);
                composer.setSize(window.innerWidth, window.innerHeight);
                const stream = canvas.captureStream(30);
                
                let options = undefined;
                if (MediaRecorder.isTypeSupported('video/webm;codecs=vp9')) options = { mimeType: 'video/webm;codecs=vp9' };
                else if (MediaRecorder.isTypeSupported('video/webm')) options = { mimeType: 'video/webm' };
                else if (MediaRecorder.isTypeSupported('video/mp4')) options = { mimeType: 'video/mp4' };

                mediaRecorder = new MediaRecorder(stream, options);
                mediaRecorder.ondataavailable = e => { if (e.data.size > 0) recordedChunks.push(e.data); };
                mediaRecorder.onstop = saveRecording;
                mediaRecorder.start();

                document.getElementById('rec-indicator').style.display = 'flex';
                recStartTime = Date.now();
                updateTimer();
                recInterval = setInterval(updateTimer, 1000);
                return true;
            } catch (err) {
                alert("Recording Error: " + err.message);
                renderer.setPixelRatio(window.devicePixelRatio);
                renderer.setSize(window.innerWidth, window.innerHeight);
                return false;
            }
        }

        function stopRecording() {
            if(!mediaRecorder) return;
            mediaRecorder.stop();
            clearInterval(recInterval);
            document.getElementById('rec-indicator').style.display = 'none';
            renderer.setPixelRatio(window.devicePixelRatio);
            renderer.setSize(window.innerWidth, window.innerHeight);
            composer.setSize(window.innerWidth, window.innerHeight);
        }

        function updateTimer() {
            const diff = Math.floor((Date.now() - recStartTime) / 1000);
            const m = Math.floor(diff / 60).toString().padStart(2, '0');
            const s = (diff % 60).toString().padStart(2, '0');
            document.getElementById('rec-time').innerText = `REC ${m}:${s}`;
        }

        function saveRecording() {
            const blob = new Blob(recordedChunks, { type: mediaRecorder.mimeType || 'video/webm' });
            const formData = new FormData();
            formData.append('video', blob);
            formData.append('format', mediaRecorder.mimeType);

            const infoDiv = document.getElementById('info');
            const origText = infoDiv.innerText;
            infoDiv.innerText = "UPLOADING...";
            infoDiv.style.color = "yellow";

            fetch('save_motion_video.php', { method: 'POST', body: formData })
            .then(r => { if(r.ok) return r.text(); throw new Error('Upload Failed'); })
            .then(filename => alert("Saved:\n" + filename))
            .catch(err => {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                let ext = mediaRecorder.mimeType.includes('mp4') ? 'mp4' : 'webm';
                a.download = 'capture_' + Date.now() + '.' + ext;
                document.body.appendChild(a);
                a.click();
                setTimeout(() => { document.body.removeChild(a); URL.revokeObjectURL(url); }, 100);
            })
            .finally(() => { infoDiv.innerText = origText; infoDiv.style.color = "white"; });
        }

        function updateTextureParams() {
            if(bgTexture) {
                bgTexture.repeat.set(texParams.repeatX, texParams.repeatY);
                bgTexture.offset.set(texParams.offsetX, texParams.offsetY);
            }
        }

        function onWindowResize() {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
            composer.setSize(window.innerWidth, window.innerHeight);
        }

        function animate() {
            requestAnimationFrame(animate);
            const time = clock.getElapsedTime();

            if(bgGroup) {
                bgGroup.rotation.x = params.bgRotX;
                bgGroup.rotation.y = params.bgRotY;
                bgGroup.rotation.z = params.bgRotZ;
            }

            if(cylinderMesh) {
                const dir = params.reverseScroll ? 1 : -1;
                cylinderMesh.rotation.x += params.scrollSpeed * 0.001 * dir;
                cylinderMesh.position.y = -cylinderRadius - params.bgDistance; 
            }

            if(shipMesh) {
                const xSway = Math.sin(time * params.swaySpeed) * params.swayAmp;
                const vibration = Math.sin(time * 60) * params.vibration; 
                shipMesh.position.x = xSway;
                shipMesh.position.z = vibration; 
                shipMesh.rotation.x = params.rotX;
                let bankingOffset = 0;
                if (params.swayAmp > 0) bankingOffset = -Math.sin(time * params.swaySpeed) * 0.3;
                shipMesh.rotation.y = params.rotY + bankingOffset;
                shipMesh.rotation.z = params.rotZ;
            }

            controls.update();
            composer.render();
        }

        function makeGuiDraggable() {
            const container = document.getElementById('gui-container');
            let isDragging = false, hasMoved = false;
            let startX, startY, initialLeft, initialTop;

            const onDown = (e) => {
                if(!e.target.closest('.title')) return;
                isDragging = true; hasMoved = false;
                const clientX = e.clientX || e.touches[0].clientX;
                const clientY = e.clientY || e.touches[0].clientY;
                startX = clientX; startY = clientY;
                const rect = container.getBoundingClientRect();
                initialLeft = rect.left; initialTop = rect.top;
            };

            const onMove = (e) => {
                if (!isDragging) return;
                const clientX = e.clientX || (e.touches ? e.touches[0].clientX : 0);
                const clientY = e.clientY || (e.touches ? e.touches[0].clientY : 0);
                if(Math.abs(clientX - startX) < 5 && Math.abs(clientY - startY) < 5) return;
                hasMoved = true;
                container.style.right = 'auto';
                container.style.left = `${initialLeft + (clientX - startX)}px`;
                container.style.top = `${initialTop + (clientY - startY)}px`;
                if(e.cancelable) e.preventDefault();
            };

            const onUp = () => isDragging = false;
            const onClick = (e) => { if (hasMoved) { e.stopImmediatePropagation(); e.stopPropagation(); } };

            container.addEventListener('mousedown', onDown);
            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp);
            container.addEventListener('touchstart', onDown, {passive: false});
            window.addEventListener('touchmove', onMove, {passive: false});
            window.addEventListener('touchend', onUp);
            container.addEventListener('click', onClick, { capture: true });
        }
    </script>
</body>
</html>