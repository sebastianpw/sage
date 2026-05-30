<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <title>Three.js Motion - Live Recording Fixed</title>
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

        /* Recording Indicator */
        #rec-indicator {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            font-weight: bold;
            font-family: monospace;
            display: none; /* Hidden by default */
            align-items: center;
            z-index: 1002;
            background: rgba(255, 0, 0, 0.6);
            padding: 8px 12px;
            border-radius: 4px;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
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

    <div id="info">Drag: Hold Title | Collapse: Click Title</div>
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

        // --- CONFIGURATION ---
        const params = {
            scrollSpeed: 4.0,
            swaySpeed: 1.0,
            swayAmp: 0.0,
            vibration: 0.1,
            blurStrength: 0.90,
            shipScale: 1.0,
            altitude: 45
        };

        const texParams = {
            repeatX: 4, repeatY: 2, offsetX: 0.5, offsetY: 0.0
        };

        // --- RECORDING VARIABLES ---
        let mediaRecorder;
        let recordedChunks = [];
        let isRecording = false;
        let recStartTime;
        let recInterval;
        let recController; 

        const system = {
            savePreset: function() {
                const payload = { params: params, texParams: texParams };
                // Using a generic alert for demo purposes if PHP isn't there
                console.log("Saving JSON payload:", JSON.stringify(payload));
                alert("Check console for JSON payload (Server save requires PHP)");
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
                    updateTextureParams();
                })
                .catch(() => console.log("No preset found."));
            },

            // --- TOGGLE LOGIC ---
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

        // --- VARIABLES ---
        let camera, scene, renderer, composer, afterimagePass, controls;
        let shipMesh, cylinderMesh, bgTexture, gui;
        let clock = new THREE.Clock();
        let baseShipScale = new THREE.Vector3(1, 1, 1);
        
        const cylinderRadius = 40;
        const cylinderLength = 160; 

        init();
        animate();
        makeGuiDraggable(); 
        system.loadPreset(); 

        function init() {
            const container = document.createElement('div');
            document.body.appendChild(container);

            scene = new THREE.Scene();
            scene.fog = new THREE.Fog(0x000000, 40, 100); 

            camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 500);
            camera.position.set(0, params.altitude, 15);
            camera.lookAt(0, 0, 0);

            // Important: preserveDrawingBuffer: true is required for recording
            renderer = new THREE.WebGLRenderer({ antialias: false, preserveDrawingBuffer: true });
            renderer.setPixelRatio(window.devicePixelRatio);
            renderer.setSize(window.innerWidth, window.innerHeight);
            container.appendChild(renderer.domElement);

            const loadMan = new THREE.LoadingManager(() => {
                document.getElementById('loading').style.opacity = 0;
            });
            const texLoader = new THREE.TextureLoader(loadMan);

            bgTexture = texLoader.load('background.jpg'); // Ensure this image exists
            bgTexture.colorSpace = THREE.SRGBColorSpace;
            bgTexture.wrapS = THREE.RepeatWrapping;
            bgTexture.wrapT = THREE.RepeatWrapping;
            updateTextureParams();

            const shipTexture = texLoader.load('spaceship.png', (tex) => { // Ensure this image exists
                const aspect = tex.image.width / tex.image.height;
                baseShipScale.set(5 * aspect, 5, 1);
                if(shipMesh) shipMesh.scale.copy(baseShipScale).multiplyScalar(params.shipScale);
            });
            shipTexture.colorSpace = THREE.SRGBColorSpace;

            const cylGeo = new THREE.CylinderGeometry(cylinderRadius, cylinderRadius, cylinderLength, 64, 1, true);
            const cylMat = new THREE.MeshBasicMaterial({ map: bgTexture, side: THREE.DoubleSide });
            cylinderMesh = new THREE.Mesh(cylGeo, cylMat);
            cylinderMesh.rotation.z = Math.PI / 2; 
            scene.add(cylinderMesh);

            const shipGeo = new THREE.PlaneGeometry(1, 1);
            const shipMat = new THREE.MeshBasicMaterial({
                map: shipTexture, transparent: true, side: THREE.DoubleSide, alphaTest: 0.1
            });
            shipMesh = new THREE.Mesh(shipGeo, shipMat);
            shipMesh.position.y = cylinderRadius + 3; 
            shipMesh.rotation.x = -Math.PI / 2; 
            shipMesh.rotation.z = Math.PI; 
            scene.add(shipMesh);

            composer = new EffectComposer(renderer);
            composer.addPass(new RenderPass(scene, camera));
            afterimagePass = new AfterimagePass();
            afterimagePass.uniforms['damp'].value = params.blurStrength;
            composer.addPass(afterimagePass);

            controls = new OrbitControls(camera, renderer.domElement);
            controls.enablePan = false;
            controls.enableDamping = true;
            controls.minPolarAngle = 0;             
            controls.maxPolarAngle = Math.PI / 2.5; 
            controls.minAzimuthAngle = -Math.PI / 4; 
            controls.maxAzimuthAngle = Math.PI / 4;  
            controls.minDistance = 20;
            controls.maxDistance = 80;

            setupGUI();
            window.addEventListener('resize', onWindowResize);
        }

        function setupGUI() {
            gui = new GUI({ title: 'Flight Controls', autoPlace: false, width: 300 });
            document.getElementById('gui-container').appendChild(gui.domElement);

            const f1 = gui.addFolder('Motion & Ship');
            f1.add(params, 'scrollSpeed', 0, 10).name('Ground Speed');
            f1.add(params, 'swayAmp', 0, 20).name('Sway Range');
            f1.add(params, 'swaySpeed', 0, 5).name('Sway Speed');
            f1.add(params, 'vibration', 0, 1).name('Engine Jitter');
            f1.add(params, 'shipScale', 0.1, 3.0).name('Scale').onChange(v => { 
                if(shipMesh) shipMesh.scale.copy(baseShipScale).multiplyScalar(v); 
            });
            f1.add(params, 'blurStrength', 0, 0.99).name('Blur').onChange(v => afterimagePass.uniforms['damp'].value = v);

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

        // --- FIXED RECORDING LOGIC ---
        function startRecording() {
            try {
                recordedChunks = [];
                const canvas = document.querySelector('canvas');
                
                if (!canvas || !canvas.captureStream) {
                    alert("Your browser does not support Canvas Capture. Try Chrome or Firefox.");
                    return false;
                }

                // 1. Lower pixel ratio to 1 for performance/stability during record
                renderer.setPixelRatio(1);
                renderer.setSize(window.innerWidth, window.innerHeight);
                composer.setSize(window.innerWidth, window.innerHeight);

                // 2. Capture Stream (30 FPS)
                const stream = canvas.captureStream(30);

                // 3. Robust MimeType Detection
                // This was the likely point of failure. We now ask the browser what it wants.
                let options = undefined;
                if (MediaRecorder.isTypeSupported('video/webm;codecs=vp9')) {
                    options = { mimeType: 'video/webm;codecs=vp9' };
                } else if (MediaRecorder.isTypeSupported('video/webm')) {
                    options = { mimeType: 'video/webm' };
                } else if (MediaRecorder.isTypeSupported('video/mp4')) {
                    options = { mimeType: 'video/mp4' }; // Safari often prefers this
                }

                // 4. Initialize Recorder
                mediaRecorder = new MediaRecorder(stream, options);

                mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) recordedChunks.push(event.data);
                };

                mediaRecorder.onstop = saveRecording;
                
                // Start
                mediaRecorder.start();

                // UI Updates
                document.getElementById('rec-indicator').style.display = 'flex';
                recStartTime = Date.now();
                updateTimer();
                recInterval = setInterval(updateTimer, 1000);
                
                return true;

            } catch (err) {
                alert("Recording Error: " + err.message);
                console.error(err);
                // Restore resolution if failed
                renderer.setPixelRatio(window.devicePixelRatio);
                renderer.setSize(window.innerWidth, window.innerHeight);
                return false;
            }
        }

        function stopRecording() {
            if(!mediaRecorder) return;
            mediaRecorder.stop(); // This triggers onstop -> saveRecording
            
            clearInterval(recInterval);
            document.getElementById('rec-indicator').style.display = 'none';

            // Restore High Res
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
            const blob = new Blob(recordedChunks, {
                type: mediaRecorder.mimeType || 'video/webm'
            });
            
            const formData = new FormData();
            formData.append('video', blob);
            formData.append('format', mediaRecorder.mimeType);

            const infoDiv = document.getElementById('info');
            const origText = infoDiv.innerText;
            infoDiv.innerText = "PROCESSING VIDEO...";
            infoDiv.style.color = "yellow";

            // Attempt upload to PHP
            fetch('save_motion_video.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if(response.ok) return response.text();
                throw new Error('Upload Failed / No Server');
            })
            .then(filename => {
                alert("Saved to Server:\n" + filename);
            })
            .catch(err => {
                console.warn("Server upload failed, switching to local download.", err);
                
                // --- FALLBACK: DOWNLOAD LOCALLY ---
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                
                // Guess extension based on mimeType
                let ext = 'webm';
                if(mediaRecorder.mimeType.includes('mp4')) ext = 'mp4';
                
                a.download = 'capture_' + Date.now() + '.' + ext;
                document.body.appendChild(a);
                a.click();
                
                setTimeout(() => {
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                }, 100);
            })
            .finally(() => {
                infoDiv.innerText = origText;
                infoDiv.style.color = "white";
            });
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

            if(cylinderMesh) cylinderMesh.rotation.x -= params.scrollSpeed * 0.01;

            if(shipMesh) {
                const xSway = Math.sin(time * params.swaySpeed) * params.swayAmp;
                const vibration = Math.sin(time * 60) * params.vibration; 
                shipMesh.position.x = xSway;
                shipMesh.position.z = vibration; 
                if (params.swayAmp > 0) shipMesh.rotation.y = -Math.sin(time * params.swaySpeed) * 0.3;
                else shipMesh.rotation.y = 0;
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