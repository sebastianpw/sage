<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <title>Three.js Motion Blur - Image Loader</title>
    <style>
        body {
            margin: 0;
            overflow: hidden; /* Prevent scrolling on mobile */
            background-color: #000;
            font-family: sans-serif;
            touch-action: none; /* Disables default touch gestures to allow 3D control */
        }
        canvas {
            display: block;
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
            opacity: 0.7;
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
    </style>
    
    <!-- Import Map for Three.js and Addons -->
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

    <div id="info">Touch to Rotate | UI for Speed/Blur</div>
    <div id="loading">Loading Assets...</div>

    <script type="module">
        import * as THREE from 'three';
        import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
        import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
        import { AfterimagePass } from 'three/addons/postprocessing/AfterimagePass.js';
        import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
        import GUI from 'lil-gui';

        // --- CONFIGURATION ---
        const params = {
            speed: 3.0,
            blurStrength: 0.92, // High default for visible trail
            shipScale: 1.0,
            autoMove: true
        };

        // --- GLOBAL VARIABLES ---
        let camera, scene, renderer, composer, afterimagePass;
        let shipMesh, bgMesh;
        let clock = new THREE.Clock();
        let controls;
        let baseShipScale = new THREE.Vector3(1, 1, 1);

        init();
        animate();

        function init() {
            const container = document.createElement('div');
            document.body.appendChild(container);

            // 1. SCENE & CAMERA
            scene = new THREE.Scene();
            // Fog blends the edges of the background plane into black
            scene.fog = new THREE.Fog(0x000000, 10, 60);

            camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
            camera.position.z = 20;

            // 2. RENDERER
            renderer = new THREE.WebGLRenderer({ antialias: false });
            renderer.setPixelRatio(window.devicePixelRatio);
            renderer.setSize(window.innerWidth, window.innerHeight);
            container.appendChild(renderer.domElement);

            // 3. LOAD TEXTURES
            const loadingManager = new THREE.LoadingManager();
            loadingManager.onLoad = function ( ) {
                document.getElementById('loading').style.opacity = 0;
            };

            const textureLoader = new THREE.TextureLoader(loadingManager);

            // Load Background
            const bgTexture = textureLoader.load('background.jpg');
            bgTexture.colorSpace = THREE.SRGBColorSpace;
            
            // Load Spaceship (PNG with Alpha)
            const shipTexture = textureLoader.load('spaceship.png', (tex) => {
                // Auto-adjust aspect ratio so the ship doesn't look squashed
                const aspect = tex.image.width / tex.image.height;
                // Assuming base height of 5 units
                shipMesh.scale.set(5 * aspect, 5, 1); 
                baseShipScale.set(5 * aspect, 5, 1); // Store base for UI scaling
                params.shipScale = 1.0; // Reset UI
            });
            shipTexture.colorSpace = THREE.SRGBColorSpace;

            // 4. BACKGROUND LAYER
            const bgGeometry = new THREE.PlaneGeometry(120, 120);
            const bgMaterial = new THREE.MeshBasicMaterial({ 
                map: bgTexture,
                side: THREE.DoubleSide
            });
            bgMesh = new THREE.Mesh(bgGeometry, bgMaterial);
            bgMesh.position.z = -15; // Push background back
            scene.add(bgMesh);

            // 5. PROTAGONIST (SPACESHIP) LAYER
            // Geometry is 1x1 initially, scaled by texture load callback above
            const shipGeometry = new THREE.PlaneGeometry(1, 1);
            const shipMaterial = new THREE.MeshBasicMaterial({
                map: shipTexture,
                transparent: true,
                alphaTest: 0.1, // Helps with sorting transparent PNGs
                side: THREE.DoubleSide,
            });
            shipMesh = new THREE.Mesh(shipGeometry, shipMaterial);
            scene.add(shipMesh);

            // 6. POST-PROCESSING (Motion Blur)
            composer = new EffectComposer(renderer);
            const renderPass = new RenderPass(scene, camera);
            composer.addPass(renderPass);

            afterimagePass = new AfterimagePass();
            afterimagePass.uniforms['damp'].value = params.blurStrength;
            composer.addPass(afterimagePass);

            // 7. CONTROLS
            controls = new OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;
            controls.dampingFactor = 0.05;
            controls.enablePan = false;
            controls.minDistance = 10;
            controls.maxDistance = 50;

            // 8. UI OVERLAY
            const gui = new GUI({ title: 'Settings' });
            
            gui.add(params, 'speed', 0, 15).name('Speed');
            
            gui.add(params, 'blurStrength', 0.0, 0.99).name('Blur Trail')
               .onChange((value) => {
                   afterimagePass.uniforms['damp'].value = value;
               });

            gui.add(params, 'shipScale', 0.1, 3.0).name('Scale')
               .onChange((value) => {
                   // Scale relative to the loaded image aspect ratio
                   if(shipMesh) {
                       shipMesh.scale.copy(baseShipScale).multiplyScalar(value);
                   }
               });
            
            gui.add(params, 'autoMove').name('Animate Ship');

            window.addEventListener('resize', onWindowResize);
        }

        function onWindowResize() {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
            composer.setSize(window.innerWidth, window.innerHeight);
        }

        function animate() {
            requestAnimationFrame(animate);

            const delta = clock.getDelta(); // time since last frame
            const totalTime = clock.getElapsedTime();

            // --- MOVEMENT LOGIC ---
            if (params.autoMove && shipMesh) {
                const speed = params.speed;
                
                // Figure-8 motion path
                // x = sin(t), y = sin(t*2)
                const x = Math.sin(totalTime * speed * 0.5) * 15;
                const y = Math.sin(totalTime * speed) * 8;
                
                // Calculate next position to determine rotation
                const nextX = Math.sin((totalTime + 0.1) * speed * 0.5) * 15;
                const nextY = Math.sin((totalTime + 0.1) * speed) * 8;
                
                shipMesh.position.set(x, y, 0);

                // Simple "Look at" logic for 2D sprite
                // Calculate angle: atan2(dy, dx)
                const angle = Math.atan2(nextY - y, nextX - x);
                // Offset by -PI/2 because standard sprites usually point UP or RIGHT. 
                // Adjust this (- Math.PI / 2) if your PNG points Up. If it points Right, remove it.
                shipMesh.rotation.z = angle - (Math.PI / 2); // Assuming spaceship.png points UP
            }

            // Parallax effect on background
            if(bgMesh) {
                bgMesh.rotation.z = totalTime * 0.02;
            }

            controls.update();
            composer.render();
        }
    </script>
</body>
</html>