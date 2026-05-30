<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <title>Three.js Cylinder Flight</title>
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

    <div id="info">Top-Down Flight | Cylinder Terrain Simulation</div>
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
            scrollSpeed: 2.0,     // Speed of the ground
            swaySpeed: 1.5,       // Speed of ship sway
            blurStrength: 0.90,   // Trail intensity
            shipScale: 1.0,
            altitude: 45          // Camera height
        };

        // --- VARIABLES ---
        let camera, scene, renderer, composer, afterimagePass, controls;
        let shipMesh, cylinderMesh;
        let clock = new THREE.Clock();
        let baseShipScale = new THREE.Vector3(1, 1, 1);
        
        // Dimensions
        const cylinderRadius = 40;
        const cylinderLength = 120;

        init();
        animate();

        function init() {
            const container = document.createElement('div');
            document.body.appendChild(container);

            // 1. SCENE
            scene = new THREE.Scene();
            scene.fog = new THREE.Fog(0x000000, 30, 90); // Hides the ends of the cylinder in darkness

            // 2. CAMERA
            // Positioned high up, looking down
            camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 500);
            camera.position.set(0, params.altitude, 10);
            camera.lookAt(0, 0, 0);

            // 3. RENDERER
            renderer = new THREE.WebGLRenderer({ antialias: false });
            renderer.setPixelRatio(window.devicePixelRatio);
            renderer.setSize(window.innerWidth, window.innerHeight);
            container.appendChild(renderer.domElement);

            // 4. LOAD ASSETS
            const loadingManager = new THREE.LoadingManager(() => {
                document.getElementById('loading').style.opacity = 0;
            });
            const textureLoader = new THREE.TextureLoader(loadingManager);

            // -- Load Background (Cylinder Surface) --
            const bgTexture = textureLoader.load('background.jpg');
            bgTexture.colorSpace = THREE.SRGBColorSpace;
            // IMPORTANT: Wrap texture for infinite scrolling
            bgTexture.wrapS = THREE.RepeatWrapping;
            bgTexture.wrapT = THREE.RepeatWrapping;
            // Repeat texture 4 times around the cylinder, 1 time along length
            bgTexture.repeat.set(4, 2); 

            // -- Load Ship --
            const shipTexture = textureLoader.load('spaceship.png', (tex) => {
                const aspect = tex.image.width / tex.image.height;
                // Set base size
                baseShipScale.set(5 * aspect, 5, 1);
                if(shipMesh) shipMesh.scale.copy(baseShipScale);
            });
            shipTexture.colorSpace = THREE.SRGBColorSpace;

            // 5. CREATE GEOMETRY

            // -- The Cylinder (World) --
            // RadiusTop, RadiusBottom, Height, RadialSegments, HeightSegments, OpenEnded
            const cylGeo = new THREE.CylinderGeometry(cylinderRadius, cylinderRadius, cylinderLength, 64, 1, true);
            const cylMat = new THREE.MeshBasicMaterial({ 
                map: bgTexture, 
                side: THREE.DoubleSide 
            });
            cylinderMesh = new THREE.Mesh(cylGeo, cylMat);
            
            // Rotate cylinder 90 deg so it lies on its side (like a rolling pin)
            // Axis of rotation becomes Z-axis relative to world, but locally it's Y.
            // Let's align it along the X-axis for easier scrolling calculations? 
            // Actually, let's keep it vertical in geometry but rotate the Mesh on Z.
            cylinderMesh.rotation.z = Math.PI / 2; 
            scene.add(cylinderMesh);

            // -- The Spaceship --
            const shipGeo = new THREE.PlaneGeometry(1, 1);
            const shipMat = new THREE.MeshBasicMaterial({
                map: shipTexture,
                transparent: true,
                side: THREE.DoubleSide,
                alphaTest: 0.1
            });
            shipMesh = new THREE.Mesh(shipGeo, shipMat);
            
            // Position ship slightly above the "top" of the cylinder
            // Cylinder radius is 40. Top is at Y=40.
            shipMesh.position.y = cylinderRadius + 2; 
            
            // Rotate ship to lie flat parallel to the ground
            shipMesh.rotation.x = -Math.PI / 2; 
            // Point the ship towards -Z (Into the screen/up) or +X?
            // Let's assume ship flies along the Z axis (Screen Up).
            // We need to rotate the Z rotation to point "Up" relative to the camera view.
            shipMesh.rotation.z = Math.PI; // Adjust based on your PNG orientation

            scene.add(shipMesh);

            // 6. POST-PROCESSING (Motion Blur)
            composer = new EffectComposer(renderer);
            composer.addPass(new RenderPass(scene, camera));

            afterimagePass = new AfterimagePass();
            afterimagePass.uniforms['damp'].value = params.blurStrength;
            composer.addPass(afterimagePass);

            // 7. CONTROLS
            controls = new OrbitControls(camera, renderer.domElement);
            controls.enablePan = false;
            controls.enableDamping = true;
            
            // RESTRICT CAMERA: 
            // Don't let the user rotate under the world or see the cylinder ends too much
            controls.minPolarAngle = 0;             // Top down
            controls.maxPolarAngle = Math.PI / 2.5; // Don't go below ~45 degrees
            controls.minAzimuthAngle = -Math.PI / 4; // Limit left rotation
            controls.maxAzimuthAngle = Math.PI / 4;  // Limit right rotation
            controls.minDistance = 20;
            controls.maxDistance = 80;

            // 8. GUI
            const gui = new GUI({ title: 'Cylinder Flight' });
            gui.add(params, 'scrollSpeed', 0, 10).name('Ground Speed');
            gui.add(params, 'swaySpeed', 0, 5).name('Sway Speed');
            gui.add(params, 'blurStrength', 0, 0.99).name('Motion Blur')
               .onChange(v => afterimagePass.uniforms['damp'].value = v);
            gui.add(params, 'shipScale', 0.1, 3.0).name('Ship Scale')
               .onChange(v => shipMesh.scale.copy(baseShipScale).multiplyScalar(v));

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

            const time = clock.getElapsedTime();
            const delta = clock.getDelta();

            // 1. ROTATE CYLINDER (Simulate forward flight)
            // Since we rotated the mesh 90deg on Z, the cylinder's local "Y" axis is now world Horizontal.
            // We rotate around the cylinder's axis.
            cylinderMesh.rotation.x -= params.scrollSpeed * 0.01;

            // 2. ANIMATE SHIP
            if(shipMesh) {
                // Sway left and right (X axis)
                const swayRange = 10;
                shipMesh.position.x = Math.sin(time * params.swaySpeed) * swayRange;
                
                // Bank (Roll) the ship slightly when moving left/right
                // Base rotation is -PI/2 on X. We add Z rotation for banking.
                // Note: The specific rotation axis depends on your PNG orientation.
                // Assuming standard "pointing up" PNG:
                shipMesh.rotation.y = -Math.sin(time * params.swaySpeed) * 0.3; // Bank
            }

            controls.update();
            composer.render();
        }
    </script>
</body>
</html>