<?php
namespace App\UI\Modules;

class MotionEditorModule
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(['animatic_id' => 0, 'api_endpoint' => 'motion_api.php'], $config);
    }

    public function render(): string
    {
        $animaticId = $this->config['animatic_id'];
        $apiEndpoint = $this->config['api_endpoint'];

        $html = <<<HTML
<div id="motion-editor-container" style="position: absolute; top: 0; left: 0; width: 100vw; height: 100vh; background: #000; overflow: hidden; margin: 0; padding: 0;">
    <div id="info" style="position:absolute; top:10px; width:100%; text-align:center; color:white; pointer-events:none; text-shadow:1px 1px 2px black; font-size:0.9rem; opacity:0.8; z-index:10;">
        SAGE Motion v6.4 (Camera Controls Restored)
    </div>
    <div id="loading" style="position:absolute; top:50%; width:100%; text-align:center; color:#00ff00; font-family:monospace; transform:translateY(-50%); pointer-events:none; transition:opacity 0.5s;">
        Loading Scene...
    </div>
    
    <!-- Recording Indicator -->
    <div id="rec-indicator" style="position:absolute; top:20px; left:20px; color:white; font-weight:bold; font-family:monospace; display:none; align-items:center; z-index:1002; background:rgba(255,0,0,0.6); padding:8px 12px; border-radius:4px; pointer-events:none;">
        <div id="rec-dot" style="width:12px; height:12px; background-color:white; border-radius:50%; margin-right:8px; animation:blink 1s infinite;"></div>
        <span id="rec-time">REC 00:00</span>
    </div>
    
    <!-- Replay Status Overlay -->
    <div id="replay-overlay" style="position:absolute; bottom:20px; left:20px; color:#00ffcc; font-family:monospace; display:none; font-size:1.2rem; text-shadow:0 0 5px #000;">
        ► REPLAYING
    </div>

    <!-- GUI Container -->
    <div id="gui-container" style="position:absolute; top:20px; right:20px; z-index:1001;"></div>
</div>

<style>
    body { margin: 0; overflow: hidden; }
    canvas { display: block; }
    
    /* Transparent GUI Config (80% Visible) */
    .lil-gui { 
        --background-color: rgba(10, 10, 10, 0.8);
        --text-color: #eee;
        --title-background-color: rgba(30, 30, 30, 0.8);
        --widget-color: rgba(60, 60, 60, 0.8);
        --hover-color: rgba(80, 80, 80, 0.8);
        --focus-color: rgba(100, 100, 100, 0.8);
        --number-color: #2cc9ff;
        --string-color: #a2db3c;
    }
    .lil-gui .title { cursor: move !important; }
    
    #motion-video-source { position: absolute; top: -9999px; left: -9999px; width: 1px; height: 1px; }
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

<script type="module">
    import * as THREE from 'three';
    import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
    import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
    import { AfterimagePass } from 'three/addons/postprocessing/AfterimagePass.js';
    import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
    import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
    import GUI from 'lil-gui';

    const CONFIG = {
        animaticId: {$animaticId},
        api: "{$apiEndpoint}"
    };

    // --- PARAMETERS ---
    
    const ENV_PARAMS = {
        scrollSpeed: 4.0, 
        blurStrength: 0.90, 
        bgBrightness: 1.0, 
        bgTint: '#ffffff', 
        
        ambientInt: 2.0, 
        dirInt: 2.0,
        fogNear: 200, 
        fogFar: 500, 
        fogColor: '#000000',
    };

    const TEX_PARAMS = { repeatX: 4, repeatY: 2, offsetX: 0.5, offsetY: 0.0 };
    
    const CAM_PARAMS = {
        presetId: null, 
        // Position
        camX: 0, camY: 45, camZ: 15,
        // Target (Looking At)
        targetX: 0, targetY: 0, targetZ: 0,
        
        zoom: 60, // Field of View
        
        enablePan: false, 
        enableRotate: true, 
        enableZoom: true,
        
        autoRotate: false, 
        autoRotateSpeed: 2.0,
        
        minPolar: 0, maxPolar: 1.25, 
        minAzimuth: -0.78, maxAzimuth: 0.78, 
        minDist: 20, maxDist: 80, 
        damping: 0.05
    };

    const ACTOR_PARAMS = {
        targetLayerId: null, 
        targetRole: 'plane', 
        dragMode: false,
        
        // Transform
        posX: 0, posY: 45, posZ: 0,
        scale: 1.0, 
        zIndex: 0, 
        depthOffset: 0.0, // Parallax Offset
        realHeight: 5.0,  
        
        // Rotation
        rotX: -1.57, rotY: 0.0, rotZ: 0.0,
        
        // Physics
        swaySpeed: 1.0, 
        swayAmp: 0.0, 
        vibration: 0.0, 
        emissiveInt: 0.0
    };
    
    const SYS_PARAMS = {
        recMode: 'video_data',
        selectedTake: null
    };

    // --- ENGINE VARS ---
    let camera, scene, renderer, composer, afterimagePass, controls;
    let hemiLight, dirLight;
    let clock = new THREE.Clock();
    let gui, raycaster = new THREE.Raycaster(), mouse = new THREE.Vector2();
    
    let layersMap = {}; 
    let currentSetupId = null;
    let cameraPresets = {}; 
    
    let activeActorMesh = null;
    let isDraggingActor = false;
    let dragPlane = new THREE.Plane();

    let mediaRecorder, recordedChunks = [], isRecording = false, recStartTime, recInterval, animationId;
    let telemetryData = [], isReplaying = false, replayData = null, replayStartTime = 0;

    const cylinderRadius = 40; 
    const cylinderLength = 160; 

    const container = document.getElementById('motion-editor-container');
    const loadingEl = document.getElementById('loading');

    // Persistence Keys
    const LS_GUI_POS = 'sage_motion_gui_pos';
    const LS_GUI_FOLDERS = 'sage_motion_gui_folders';

    init();

    function init() {
        scene = new THREE.Scene();
        scene.fog = new THREE.Fog(ENV_PARAMS.fogColor, ENV_PARAMS.fogNear, ENV_PARAMS.fogFar);
        
        hemiLight = new THREE.HemisphereLight(0xffffff, 0x444444, ENV_PARAMS.ambientInt);
        hemiLight.position.set(0, 200, 0);
        scene.add(hemiLight);
        dirLight = new THREE.DirectionalLight(0xffffff, ENV_PARAMS.dirInt);
        dirLight.position.set(0, 200, 100);
        scene.add(dirLight);

        camera = new THREE.PerspectiveCamera(CAM_PARAMS.zoom, window.innerWidth / window.innerHeight, 0.1, 1000);
        camera.position.set(CAM_PARAMS.camX, CAM_PARAMS.camY, CAM_PARAMS.camZ);
        camera.lookAt(CAM_PARAMS.targetX, CAM_PARAMS.targetY, CAM_PARAMS.targetZ);

        renderer = new THREE.WebGLRenderer({ antialias: false, preserveDrawingBuffer: true });
        renderer.setPixelRatio(window.devicePixelRatio);
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        container.appendChild(renderer.domElement);
        
        composer = new EffectComposer(renderer);
        composer.addPass(new RenderPass(scene, camera));
        afterimagePass = new AfterimagePass();
        afterimagePass.uniforms['damp'].value = ENV_PARAMS.blurStrength;
        composer.addPass(afterimagePass);
        
        controls = new OrbitControls(camera, renderer.domElement);
        // Important: Set initial target immediately so it doesn't default to 0,0,0 if loaded
        controls.target.set(CAM_PARAMS.targetX, CAM_PARAMS.targetY, CAM_PARAMS.targetZ);
        applyCameraSettings();
        
        window.addEventListener('resize', onWindowResize);
        container.addEventListener('pointerdown', onPointerDown);
        container.addEventListener('pointermove', onPointerMove);
        container.addEventListener('pointerup', onPointerUp);
        window.addEventListener('beforeunload', saveGuiState); // Save UI state on exit
        
        loadSetupData();
    }

    // --- INTERACTION ---
    function onPointerDown(event) {
        if (!ACTOR_PARAMS.dragMode || !ACTOR_PARAMS.targetLayerId) return;
        
        mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
        mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;
        raycaster.setFromCamera(mouse, camera);

        const l = layersMap[ACTOR_PARAMS.targetLayerId];
        if (l && l.mesh) {
            const intersects = raycaster.intersectObject(l.mesh, true);
            if (intersects.length > 0) {
                isDraggingActor = true;
                controls.enabled = false; 
                
                const normal = new THREE.Vector3();
                camera.getWorldDirection(normal);
                dragPlane.setFromNormalAndCoplanarPoint(normal, l.mesh.position);
            }
        }
    }

    function onPointerMove(event) {
        if (!isDraggingActor) return;
        
        mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
        mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;
        raycaster.setFromCamera(mouse, camera);

        const targetPoint = new THREE.Vector3();
        if (raycaster.ray.intersectPlane(dragPlane, targetPoint)) {
            const l = layersMap[ACTOR_PARAMS.targetLayerId];
            if(l) {
                // Update Mesh
                l.mesh.position.copy(targetPoint);
                // Update UI params
                ACTOR_PARAMS.posX = targetPoint.x;
                ACTOR_PARAMS.posY = targetPoint.y;
                ACTOR_PARAMS.posZ = targetPoint.z;
                
                // Update Config
                l.config.posX = targetPoint.x;
                l.config.posY = targetPoint.y;
                l.config.posZ = targetPoint.z;
            }
        }
    }

    function onPointerUp() {
        if (isDraggingActor) {
            isDraggingActor = false;
            controls.enabled = true; 
            if(gui) gui.controllersRecursive().forEach(c => c.updateDisplay());
        }
    }

    function applyCameraSettings() {
        camera.fov = CAM_PARAMS.zoom;
        camera.updateProjectionMatrix();
        
        controls.enablePan = CAM_PARAMS.enablePan;
        controls.enableRotate = CAM_PARAMS.enableRotate;
        controls.enableZoom = CAM_PARAMS.enableZoom;
        controls.autoRotate = CAM_PARAMS.autoRotate;
        controls.autoRotateSpeed = CAM_PARAMS.autoRotateSpeed;
        
        controls.minPolarAngle = CAM_PARAMS.minPolar;
        controls.maxPolarAngle = CAM_PARAMS.maxPolar;
        
        controls.minAzimuthAngle = (CAM_PARAMS.minAzimuth < -10) ? -Infinity : CAM_PARAMS.minAzimuth;
        controls.maxAzimuthAngle = (CAM_PARAMS.maxAzimuth > 10) ? Infinity : CAM_PARAMS.maxAzimuth;
        
        controls.minDistance = CAM_PARAMS.minDist;
        controls.maxDistance = CAM_PARAMS.maxDist;
        controls.enableDamping = true;
        controls.dampingFactor = CAM_PARAMS.damping;
        
        // If not animating, snap camera to manual position
        if(!controls.autoRotate && !isReplaying) {
             camera.position.set(CAM_PARAMS.camX, CAM_PARAMS.camY, CAM_PARAMS.camZ);
             controls.target.set(CAM_PARAMS.targetX, CAM_PARAMS.targetY, CAM_PARAMS.targetZ);
        }
        controls.update();
    }

    function loadSetupData() {
        if(loadingEl) loadingEl.innerText = "Building Stage...";
        fetch(CONFIG.api + '?action=load_setup&animatic_id=' + CONFIG.animaticId)
        .then(r => r.json())
        .then(data => {
            if(!data.success) throw new Error(data.message);
            
            const env = JSON.parse(data.setup.environment_config || '{}');
            for(let k in env.params) { if(ENV_PARAMS.hasOwnProperty(k)) ENV_PARAMS[k] = env.params[k]; }
            
            // Load Camera Params (Including Target)
            if(env.camParams) {
                Object.assign(CAM_PARAMS, env.camParams);
                if(CAM_PARAMS.targetX === undefined) CAM_PARAMS.targetX = 0;
                if(CAM_PARAMS.targetY === undefined) CAM_PARAMS.targetY = 0;
                if(CAM_PARAMS.targetZ === undefined) CAM_PARAMS.targetZ = 0;
            }
            
            if(env.texParams) Object.assign(TEX_PARAMS, env.texParams);
            
            updateLighting(); updateFog(); applyCameraSettings();
            
            afterimagePass.uniforms['damp'].value = ENV_PARAMS.blurStrength;

            currentSetupId = data.setup.id;
            data.layers.forEach(l => createLayerObject(l));
            
            setupGUI();

            if(data.layers.length > 0) {
                let firstId = data.layers[0].id;
                const actor = data.layers.find(l => l.role === 'plane' || l.role === 'model3d');
                if(actor) firstId = actor.id;
                
                ACTOR_PARAMS.targetLayerId = firstId;
                syncUIParamsToLayer(firstId);
            }

            animate();
            if(loadingEl) loadingEl.style.opacity = 0;
        })
        .catch(e => {
            console.error(e);
            if(loadingEl) { loadingEl.innerText = "Error: " + e.message; loadingEl.style.color = "red"; }
        });
    }

    function createLayerObject(layer) {
        if(layersMap[layer.id]) {
            scene.remove(layersMap[layer.id].mesh);
            if(layersMap[layer.id].videoEl) layersMap[layer.id].videoEl.remove();
        }

        const config = JSON.parse(layer.layer_config || '{}');
        const isVideo = !!layer.video_url;
        const isImage = !!layer.frame_filename;
        const isMesh  = !!layer.mesh_filename;
        
        if(!isVideo && !isImage && !isMesh) return;

        const meshGroup = new THREE.Group();
        
        // Base Position Logic
        if (config.posX !== undefined) {
             meshGroup.position.set(config.posX, config.posY, config.posZ);
        } else {
             // Default init height
             const zSep = (layer.z_index || 0) * 0.2; 
             const baseHeight = cylinderRadius + 3 + zSep + (config.depthOffset || 0.0);
             if (layer.role !== 'background') meshGroup.position.y = baseHeight;
        }

        let videoEl = null;
        let texture = null;

        if (layer.role === 'model3d' && isMesh) {
            const loader = new GLTFLoader();
            loader.load('/' + layer.mesh_filename, (gltf) => {
                const model = gltf.scene;
                
                model.traverse((child) => {
                    if (child.isMesh && child.material) {
                        child.material.metalness = 0.1;
                        child.material.roughness = 0.5;
                        child.material.emissive = new THREE.Color(0xffffff);
                        child.material.emissiveIntensity = config.emissiveInt || 0.0;
                    }
                });

                const box = new THREE.Box3().setFromObject(model);
                const size = new THREE.Vector3(); box.getSize(size);
                const center = new THREE.Vector3(); box.getCenter(center);
                model.position.sub(center); 
                
                const maxDim = Math.max(size.x, size.y, size.z);
                const scaleBase = (maxDim > 0) ? (5.0 / maxDim) : 1.0;
                
                meshGroup.userData.baseScale = new THREE.Vector3(scaleBase, scaleBase, scaleBase);
                const factor = config.scaleFactor || 1.0;
                meshGroup.scale.setScalar(scaleBase * factor);
                
                meshGroup.add(model);
            });
            meshGroup.rotation.y = Math.PI; 
        }
        else if (layer.role === 'background') {
            const texLoader = new THREE.TextureLoader();
            const url = layer.frame_filename ? '/' + layer.frame_filename : 'background.jpg';
            texture = texLoader.load(url);
            texture.colorSpace = THREE.SRGBColorSpace;
            texture.wrapS = THREE.RepeatWrapping; 
            texture.wrapT = THREE.RepeatWrapping;
            texture.repeat.set(TEX_PARAMS.repeatX, TEX_PARAMS.repeatY);
            texture.offset.set(TEX_PARAMS.offsetX, TEX_PARAMS.offsetY);

            const geo = new THREE.CylinderGeometry(cylinderRadius, cylinderRadius, cylinderLength, 64, 1, true);
            const mat = new THREE.MeshBasicMaterial({ map: texture, side: THREE.DoubleSide, color: 0xffffff });
            const cylinder = new THREE.Mesh(geo, mat);
            cylinder.rotation.z = Math.PI / 2; 
            
            const c = new THREE.Color(ENV_PARAMS.bgTint);
            c.multiplyScalar(ENV_PARAMS.bgBrightness);
            cylinder.material.color.copy(c);
            
            meshGroup.position.set(0, 0, 0);
            meshGroup.add(cylinder);
        } 
        else {
            if(isVideo) {
                videoEl = document.createElement('video');
                videoEl.src = '/' + layer.video_url;
                videoEl.loop = true; videoEl.muted = true; videoEl.playsInline = true; videoEl.crossOrigin = 'anonymous';
                videoEl.style.position = 'absolute'; videoEl.style.top = '-9999px';
                document.body.appendChild(videoEl);
                videoEl.play().catch(()=>{});
                texture = new THREE.VideoTexture(videoEl);
                texture.minFilter = THREE.LinearFilter;
            } else {
                texture = new THREE.TextureLoader().load('/' + layer.frame_filename);
            }
            texture.colorSpace = THREE.SRGBColorSpace;
            const geo = new THREE.PlaneGeometry(1, 1);
            const mat = new THREE.MeshBasicMaterial({ 
                map: texture, 
                transparent: true, 
                side: THREE.DoubleSide, 
                alphaTest: 0.5, 
                depthTest: true, 
                depthWrite: true 
            });
            const plane = new THREE.Mesh(geo, mat);
            meshGroup.add(plane);

            meshGroup.rotation.x = -Math.PI / 2;
            meshGroup.rotation.z = Math.PI; 
            
            if(isVideo) {
                videoEl.addEventListener('loadedmetadata', function() {
                    if(this.videoHeight) {
                        const aspect = this.videoWidth / this.videoHeight;
                        const base = new THREE.Vector3(5 * aspect, 5, 1);
                        meshGroup.userData.baseScale = base.clone();
                        const factor = config.scaleFactor || 1.0; 
                        meshGroup.scale.copy(base).multiplyScalar(factor);
                    }
                });
            } else {
                meshGroup.userData.baseScale = new THREE.Vector3(5,5,1);
                const factor = config.scaleFactor || 1.0;
                meshGroup.scale.set(5*factor, 5*factor, 1); 
            }
        }

        if (config.rotX !== undefined) meshGroup.rotation.x = config.rotX;
        if (config.rotY !== undefined) meshGroup.rotation.y = config.rotY;
        if (config.rotZ !== undefined) meshGroup.rotation.z = config.rotZ;

        const zIdx = config.zIndex !== undefined ? config.zIndex : (layer.z_index || 0);
        meshGroup.renderOrder = 999 + zIdx;
        
        // Depth Offset
        const depthOffset = config.depthOffset || 0.0;
        if (layer.role !== 'background') {
            if(config.posX === undefined) {
                 meshGroup.position.y += (zIdx * 0.2) + depthOffset;
            }
        }

        scene.add(meshGroup);

        layersMap[layer.id] = {
            id: layer.id, mesh: meshGroup, role: layer.role, config: config,
            videoEl: videoEl, texture: texture,
            video_url: layer.video_url, frame_filename: layer.frame_filename, mesh_filename: layer.mesh_filename
        };
    }

    function setupGUI() {
        if(gui) gui.destroy();
        gui = new GUI({ title: 'Motion Director', container: document.getElementById('gui-container'), width: 320 });
        
        // --- 1. CAMERA & CONTROLS ---
        const fCam = gui.addFolder('Camera & Controls');
        
        // Presets Logic
        const presetObj = { loadPreset: 'Spaceship' }; 
        const presetCtrl = fCam.add(presetObj, 'loadPreset', {}).name('Preset');
        
        fetch(CONFIG.api + '?action=list_camera_presets').then(r=>r.json()).then(d=>{
            const opts = {};
            if(d.presets) {
                d.presets.forEach(p => { opts[p.name] = p.id; cameraPresets[p.id] = JSON.parse(p.config); });
                presetCtrl.options(opts);
            }
        });
        
        const rowP = { 
            load: () => {
                const pid = presetObj.loadPreset;
                if(cameraPresets[pid]) {
                    Object.assign(CAM_PARAMS, cameraPresets[pid]);
                    CAM_PARAMS.presetId = pid;
                    applyCameraSettings();
                    gui.controllersRecursive().forEach(c=>c.updateDisplay());
                }
            },
            saveNew: () => {
                const name = prompt("Name for Camera Preset:", "My Rig");
                if(name) {
                    const fd = new FormData(); fd.append('action', 'save_camera_preset'); fd.append('name', name); fd.append('config', JSON.stringify(CAM_PARAMS));
                    fetch(CONFIG.api, {method:'POST', body:fd}).then(r=>r.json()).then(d=> alert(d.success?"Saved! Refresh.":"Error"));
                }
            },
            update: () => {
                if(!CAM_PARAMS.presetId) { alert("Load a preset first."); return; }
                if(confirm("Update current preset?")) {
                    const fd = new FormData(); fd.append('action', 'update_camera_preset'); fd.append('id', CAM_PARAMS.presetId); fd.append('config', JSON.stringify(CAM_PARAMS));
                    fetch(CONFIG.api, {method:'POST', body:fd}).then(r=>r.json()).then(d=> alert(d.success?"Updated!":"Error"));
                }
            }
        };
        
        fCam.add(rowP, 'load').name('Load Preset');
        fCam.add(rowP, 'saveNew').name('Save New');
        fCam.add(rowP, 'update').name('Update Current');

        // RESTORED: Manual Position Sliders with .listen() for real-time feedback
        fCam.add(CAM_PARAMS, 'camX', -100, 100).name('Pos X').onChange(applyCameraSettings).listen();
        fCam.add(CAM_PARAMS, 'camY', 0, 200).name('Pos Y (Alt)').onChange(applyCameraSettings).listen();
        fCam.add(CAM_PARAMS, 'camZ', -100, 100).name('Pos Z').onChange(applyCameraSettings).listen();
        
        fCam.add(CAM_PARAMS, 'zoom', 10, 120).name('FOV (Zoom)').onChange(applyCameraSettings).listen();
        
        fCam.add(CAM_PARAMS, 'enablePan').name('Allow Pan').onChange(applyCameraSettings);
        fCam.add(CAM_PARAMS, 'enableRotate').name('Allow Rotate').onChange(applyCameraSettings);
        fCam.add(CAM_PARAMS, 'enableZoom').name('Allow Zoom').onChange(applyCameraSettings);
        fCam.add(CAM_PARAMS, 'autoRotate').name('Auto Spin').onChange(applyCameraSettings);
        
        const fCamAdv = fCam.addFolder('Constraints');
        fCamAdv.add(CAM_PARAMS, 'minPolar', 0, 3.14).onChange(applyCameraSettings);
        fCamAdv.add(CAM_PARAMS, 'maxPolar', 0, 3.14).onChange(applyCameraSettings);
        fCamAdv.add(CAM_PARAMS, 'minDist', 10, 100).onChange(applyCameraSettings);
        fCamAdv.add(CAM_PARAMS, 'maxDist', 50, 500).onChange(applyCameraSettings);
        fCamAdv.close();

        // --- 2. GLOBAL ENVIRONMENT ---
        const fEnv = gui.addFolder('Global Environment');
        fEnv.add(ENV_PARAMS, 'scrollSpeed', -20, 20).name('Scroll Speed');
        fEnv.add(ENV_PARAMS, 'blurStrength', 0, 0.99).name('Blur Strength').onChange(v => afterimagePass.uniforms['damp'].value = v);
        fEnv.add(ENV_PARAMS, 'ambientInt', 0, 5).name('Ambient Light').onChange(updateLighting);
        fEnv.add(ENV_PARAMS, 'dirInt', 0, 5).name('Sun Light').onChange(updateLighting);
        fEnv.addColor(ENV_PARAMS, 'fogColor').name('Fog Color').onChange(updateFog);
        
        const fBg = fEnv.addFolder('Background Texture');
        fBg.add(ENV_PARAMS, 'bgBrightness', 0, 1).onChange(updateBgColor);
        fBg.addColor(ENV_PARAMS, 'bgTint').onChange(updateBgColor);
        fBg.add(TEX_PARAMS, 'repeatX', 1, 10).onChange(updateTextureParams);
        fBg.add(TEX_PARAMS, 'repeatY', 1, 10).onChange(updateTextureParams);
        fBg.add(TEX_PARAMS, 'offsetX', 0, 1).onChange(updateTextureParams);
        fBg.add(TEX_PARAMS, 'offsetY', 0, 1).onChange(updateTextureParams);

        // --- 3. ACTOR CONTROL ---
        const fActor = gui.addFolder('Active Actor');
        const layerOptions = {};
        for(let id in layersMap) { layerOptions['Layer ' + id] = parseInt(id); }
        fActor.add(ACTOR_PARAMS, 'targetLayerId', layerOptions).name('Select').onChange(id => syncUIParamsToLayer(id));
        fActor.add(ACTOR_PARAMS, 'targetRole', ['background', 'plane', 'model3d']).name('Role').onChange(newRole => changeLayerRole(ACTOR_PARAMS.targetLayerId, newRole));
        fActor.add(ACTOR_PARAMS, 'dragMode').name('🖐 Drag & Drop Mode');

        const fATrans = fActor.addFolder('Transform');
        fATrans.add(ACTOR_PARAMS, 'posX', -200, 200).name('X').onChange(updateTargetTransform).listen();
        fATrans.add(ACTOR_PARAMS, 'posY', -50, 200).name('Y (Height)').onChange(updateTargetTransform).listen();
        fATrans.add(ACTOR_PARAMS, 'posZ', -200, 200).name('Z').onChange(updateTargetTransform).listen();
        fATrans.add(ACTOR_PARAMS, 'scale', 0.1, 5.0).name('Scale').onChange(updateTargetTransform);
        
        fATrans.add(ACTOR_PARAMS, 'zIndex', 0, 50, 1).name('Z-Order (Sort)').onChange(updateTargetTransform);
        fATrans.add(ACTOR_PARAMS, 'depthOffset', -50, 50).name('Parallax (Offset)').onChange(updateTargetTransform);
        
        fATrans.add(ACTOR_PARAMS, 'rotX', -3.14, 3.14).name('Pitch').onChange(updateTargetTransform);
        fATrans.add(ACTOR_PARAMS, 'rotY', -3.14, 3.14).name('Yaw').onChange(updateTargetTransform);
        fATrans.add(ACTOR_PARAMS, 'rotZ', -3.14, 3.14).name('Roll').onChange(updateTargetTransform);

        const fAPhys = fActor.addFolder('Physics & Material');
        fAPhys.add(ACTOR_PARAMS, 'swayAmp', 0, 20).name('Sway Amp').onChange(updateTargetConfig);
        fAPhys.add(ACTOR_PARAMS, 'swaySpeed', 0, 5).name('Sway Spd').onChange(updateTargetConfig);
        fAPhys.add(ACTOR_PARAMS, 'vibration', 0, 1).name('Vibration').onChange(updateTargetConfig);
        fAPhys.add(ACTOR_PARAMS, 'emissiveInt', 0, 2).name('Self-Glow').onChange(updateTargetConfig);

        // --- 4. SYSTEM ---
        const fSys = gui.addFolder('System');
        const replaySel = fSys.add(SYS_PARAMS, 'selectedTake', {}).name('Load Replay');
        fetch(CONFIG.api + '?action=list_takes&animatic_id=' + CONFIG.animaticId)
        .then(r=>r.json()).then(d => {
            if(d.takes) {
                const opts = {};
                d.takes.forEach(t => opts[t.id + ': ' + t.name] = t.id);
                replaySel.options(opts);
            }
        });
        fSys.add({ play: playTake }, 'play').name('► Play Take');
        fSys.add({ stop: stopReplay }, 'stop').name('■ Stop Replay');
        
        fSys.add(SYS_PARAMS, 'recMode', { 'Video + Data': 'video_data', 'Data Only': 'data_only' }).name('Rec Mode');
        fSys.add({ toggle: toggleRecording }, 'toggle').name('🔴 Record');
        fSys.add({ save: saveSetup }, 'save').name('💾 Save Scenario');
        fSys.open();

        makeGuiDraggable();
        // Restore Folder State
        const folderStates = JSON.parse(localStorage.getItem('sage_motion_gui_folders') || '{}');
        gui.folders.forEach(f => {
            if(folderStates[f._title] === true) f.close();
            if(folderStates[f._title] === false) f.open();
        });
    }

    // --- HELPERS ---
    function updateLighting() {
        if(hemiLight) hemiLight.intensity = ENV_PARAMS.ambientInt;
        if(dirLight) dirLight.intensity = ENV_PARAMS.dirInt;
    }
    
    function updateFog() {
        if(scene.fog) { 
            scene.fog.near = ENV_PARAMS.fogNear; 
            scene.fog.far = ENV_PARAMS.fogFar; 
            scene.fog.color.set(ENV_PARAMS.fogColor); 
            renderer.setClearColor(ENV_PARAMS.fogColor); 
        }
    }

    function syncUIParamsToLayer(id) {
        if(!gui) return;
        const l = layersMap[id];
        if(!l) return;
        
        ACTOR_PARAMS.targetRole = l.role;
        
        // Position
        ACTOR_PARAMS.posX = l.config.posX !== undefined ? l.config.posX : l.mesh.position.x;
        ACTOR_PARAMS.posY = l.config.posY !== undefined ? l.config.posY : l.mesh.position.y;
        ACTOR_PARAMS.posZ = l.config.posZ !== undefined ? l.config.posZ : l.mesh.position.z;
        
        // Transform
        ACTOR_PARAMS.scale = l.config.scaleFactor !== undefined ? l.config.scaleFactor : 1.0;
        ACTOR_PARAMS.zIndex = l.config.zIndex !== undefined ? l.config.zIndex : 0;
        ACTOR_PARAMS.depthOffset = l.config.depthOffset !== undefined ? l.config.depthOffset : 0.0;
        
        // Rotation
        ACTOR_PARAMS.rotX = l.config.rotX !== undefined ? l.config.rotX : l.mesh.rotation.x;
        ACTOR_PARAMS.rotY = l.config.rotY !== undefined ? l.config.rotY : l.mesh.rotation.y;
        ACTOR_PARAMS.rotZ = l.config.rotZ !== undefined ? l.config.rotZ : l.mesh.rotation.z;
        
        // Physics
        ACTOR_PARAMS.swayAmp = l.config.swayAmp !== undefined ? l.config.swayAmp : 0;
        ACTOR_PARAMS.swaySpeed = l.config.swaySpeed !== undefined ? l.config.swaySpeed : 1;
        ACTOR_PARAMS.vibration = l.config.vibration !== undefined ? l.config.vibration : 0.0;
        ACTOR_PARAMS.emissiveInt = l.config.emissiveInt !== undefined ? l.config.emissiveInt : 0.0;

        gui.controllersRecursive().forEach(c => c.updateDisplay());
    }

    function updateTargetTransform() {
        const l = layersMap[ACTOR_PARAMS.targetLayerId];
        if(!l) return;
        
        // Scale
        if(l.mesh.userData.baseScale) {
            l.mesh.scale.copy(l.mesh.userData.baseScale).multiplyScalar(ACTOR_PARAMS.scale);
        } else {
            l.mesh.scale.setScalar(ACTOR_PARAMS.scale);
        }
        l.config.scaleFactor = ACTOR_PARAMS.scale;
        
        // Z-Index & Order
        l.mesh.renderOrder = 999 + ACTOR_PARAMS.zIndex;
        l.config.zIndex = ACTOR_PARAMS.zIndex;
        l.config.depthOffset = ACTOR_PARAMS.depthOffset;
        
        // Height Calculation (if not manually positioned via drag)
        if (l.role !== 'background') {
            if(!ACTOR_PARAMS.dragMode) {
                 const zSep = ACTOR_PARAMS.zIndex * 0.2;
                 const baseH = cylinderRadius + 3 + zSep + ACTOR_PARAMS.depthOffset;
                 ACTOR_PARAMS.posY = baseH; // Update Y param to reflect calc
            }
        }
        
        // Position
        l.mesh.position.set(ACTOR_PARAMS.posX, ACTOR_PARAMS.posY, ACTOR_PARAMS.posZ);
        l.config.posX = ACTOR_PARAMS.posX; 
        l.config.posY = ACTOR_PARAMS.posY; 
        l.config.posZ = ACTOR_PARAMS.posZ;

        // Rotation
        l.config.rotX = ACTOR_PARAMS.rotX; 
        l.config.rotY = ACTOR_PARAMS.rotY; 
        l.config.rotZ = ACTOR_PARAMS.rotZ;
        l.mesh.rotation.x = ACTOR_PARAMS.rotX; 
        l.mesh.rotation.y = ACTOR_PARAMS.rotY; 
        l.mesh.rotation.z = ACTOR_PARAMS.rotZ;
    }

    function updateTargetConfig() {
        const l = layersMap[ACTOR_PARAMS.targetLayerId];
        if(!l) return;
        l.config.swayAmp = ACTOR_PARAMS.swayAmp;
        l.config.swaySpeed = ACTOR_PARAMS.swaySpeed;
        l.config.vibration = ACTOR_PARAMS.vibration;
        l.config.emissiveInt = ACTOR_PARAMS.emissiveInt;
        
        if(l.role === 'model3d') {
            l.mesh.traverse(child => { 
                if(child.isMesh && child.material) child.material.emissiveIntensity = ACTOR_PARAMS.emissiveInt; 
            });
        }
    }

    function changeLayerRole(id, newRole) {
        const l = layersMap[id];
        if(!l) return;
        l.role = newRole;
        createLayerObject({
            id: l.id, role: newRole, layer_config: JSON.stringify(l.config),
            video_url: l.video_url, frame_filename: l.frame_filename, mesh_filename: l.mesh_filename,
            z_index: l.config.zIndex
        });
        if(newRole === 'background') { updateBgColor(); updateTextureParams(); }
    }

    function updateBgColor() {
        for(let id in layersMap) {
            if(layersMap[id].role === 'background') {
                const c = new THREE.Color(ENV_PARAMS.bgTint);
                c.multiplyScalar(ENV_PARAMS.bgBrightness);
                if(layersMap[id].mesh.children[0]) layersMap[id].mesh.children[0].material.color.copy(c);
            }
        }
    }
    
    function updateTextureParams() {
        for(let id in layersMap) {
            if(layersMap[id].role === 'background' && layersMap[id].texture) {
                const t = layersMap[id].texture;
                t.repeat.set(TEX_PARAMS.repeatX, TEX_PARAMS.repeatY);
                t.offset.set(TEX_PARAMS.offsetX, TEX_PARAMS.offsetY);
            }
        }
    }

    function animate() {
        animationId = requestAnimationFrame(animate);
        const time = clock.getElapsedTime();
        
        // Update Camera Position in GUI loop if moving
        if(controls.enabled && !isReplaying) {
             CAM_PARAMS.camX = camera.position.x;
             CAM_PARAMS.camY = camera.position.y;
             CAM_PARAMS.camZ = camera.position.z;
        }

        // REPLAY
        if (isReplaying && replayData) {
            const elapsed = (Date.now() - replayStartTime) / 1000;
            const frame = replayData.find(f => Math.abs(f.time - elapsed) < 0.05); 
            if (frame) {
                if(frame.cam) {
                     controls.object.position.setLength(frame.cam.d);
                }
                for (let id in frame.layers) {
                    if (layersMap[id]) {
                        const dat = frame.layers[id];
                        layersMap[id].mesh.position.set(dat.x, dat.y, dat.z);
                        layersMap[id].mesh.rotation.set(dat.rx, dat.ry, dat.rz);
                    }
                }
                if (elapsed > replayData[replayData.length-1].time) stopReplay();
            }
            controls.update();
            composer.render();
            return;
        }

        // RECORDING
        if (isRecording) {
            const frameTime = (Date.now() - recStartTime) / 1000;
            const frameState = {
                time: frameTime,
                cam: { r: controls.getAzimuthalAngle(), p: controls.getPolarAngle(), d: controls.getDistance() },
                layers: {}
            };
            for(let id in layersMap) {
                const l = layersMap[id];
                frameState.layers[id] = {
                    x: l.mesh.position.x, y: l.mesh.position.y, z: l.mesh.position.z,
                    rx: l.mesh.rotation.x, ry: l.mesh.rotation.y, rz: l.mesh.rotation.z
                };
            }
            telemetryData.push(frameState);
        }

        // PHYSICS
        for(let id in layersMap) {
            const l = layersMap[id];
            const m = l.mesh;
            const c = l.config;

            if (l.role === 'background') {
                m.rotation.x -= ENV_PARAMS.scrollSpeed * 0.01;
            } 
            else if (l.role === 'plane' || l.role === 'model3d') {
                const isTarget = (id == ACTOR_PARAMS.targetLayerId);
                const swayAmp = isTarget ? ACTOR_PARAMS.swayAmp : (c.swayAmp||0);
                const swaySpeed = isTarget ? ACTOR_PARAMS.swaySpeed : (c.swaySpeed||1);
                const vib = isTarget ? ACTOR_PARAMS.vibration : (c.vibration||0);
                
                // Base Pos (Saved or Dragged)
                const bx = (isTarget) ? ACTOR_PARAMS.posX : (c.posX !== undefined ? c.posX : m.position.x);
                const by = (isTarget) ? ACTOR_PARAMS.posY : (c.posY !== undefined ? c.posY : m.position.y);
                const bz = (isTarget) ? ACTOR_PARAMS.posZ : (c.posZ !== undefined ? c.posZ : m.position.z);
                
                const baseX = (isTarget) ? ACTOR_PARAMS.rotX : (c.rotX !== undefined ? c.rotX : m.rotation.x);
                const baseY = (isTarget) ? ACTOR_PARAMS.rotY : (c.rotY !== undefined ? c.rotY : 0);
                const baseZ = (isTarget) ? ACTOR_PARAMS.rotZ : (c.rotZ !== undefined ? c.rotZ : 0);

                const xSway = Math.sin(time * swaySpeed) * swayAmp;
                const vibration = Math.sin(time * 60) * vib; 
                
                // Apply Physics to Base
                m.position.x = bx + xSway;
                m.position.y = by; 
                m.position.z = bz + vibration; 
                
                m.rotation.x = baseX;
                m.rotation.z = baseZ;
                
                if (swayAmp > 0) m.rotation.y = baseY + (-Math.sin(time * swaySpeed) * 0.3);
                else m.rotation.y = baseY;
            }
        }

        controls.update();
        composer.render();
    }

    // --- GUI SAVE STATE ---
    function saveGuiState() {
        const states = {};
        if(gui) gui.folders.forEach(f => states[f._title] = f._closed);
        localStorage.setItem('sage_motion_gui_folders', JSON.stringify(states));
    }

    function saveSetup() {
        // Capture Camera State into CAM_PARAMS before saving
        CAM_PARAMS.camX = camera.position.x;
        CAM_PARAMS.camY = camera.position.y;
        CAM_PARAMS.camZ = camera.position.z;
        CAM_PARAMS.targetX = controls.target.x;
        CAM_PARAMS.targetY = controls.target.y;
        CAM_PARAMS.targetZ = controls.target.z;

        const envConfig = { params: ENV_PARAMS, camParams: CAM_PARAMS, texParams: TEX_PARAMS };
        const layersUpdate = [];
        for(let id in layersMap) {
            layersUpdate.push({ id: id, role: layersMap[id].role, config: layersMap[id].config });
        }
        const fd = new FormData();
        fd.append('action', 'save_setup');
        fd.append('setup_id', currentSetupId);
        fd.append('environment_config', JSON.stringify(envConfig));
        fd.append('layers_config', JSON.stringify(layersUpdate));
        fetch(CONFIG.api, { method: 'POST', body: fd })
        .then(r=>r.json()).then(d=> alert(d.success ? "Saved!" : "Error"));
        
        saveGuiState();
    }

    function toggleRecording() {
        if(!isRecording) {
            startRecording();
            isRecording = true;
            recController.name('⬛ Stop & Save');
        } else {
            stopRecording();
            isRecording = false;
            recController.name('🔴 Record');
        }
    }
    function startRecording() {
        recordedChunks = [];
        telemetryData = []; 
        if(SYS_PARAMS.recMode === 'data_only') {
            document.getElementById('rec-indicator').style.display = 'flex';
            document.getElementById('rec-indicator').style.background = 'rgba(0,0,255,0.6)';
            recStartTime = Date.now();
            recInterval = setInterval(updateTimer, 1000);
            isRecording = true;
            return;
        }
        renderer.setPixelRatio(1);
        renderer.setSize(window.innerWidth, window.innerHeight);
        composer.setSize(window.innerWidth, window.innerHeight);
        const stream = renderer.domElement.captureStream(30);
        let options = { mimeType: 'video/webm' };
        if (MediaRecorder.isTypeSupported('video/webm;codecs=vp9')) options.mimeType = 'video/webm;codecs=vp9';
        else if (MediaRecorder.isTypeSupported('video/mp4')) options.mimeType = 'video/mp4';
        mediaRecorder = new MediaRecorder(stream, options);
        mediaRecorder.ondataavailable = e => { if (e.data.size > 0) recordedChunks.push(e.data); };
        mediaRecorder.onstop = saveRecording;
        mediaRecorder.start();
        document.getElementById('rec-indicator').style.display = 'flex';
        recStartTime = Date.now();
        recInterval = setInterval(updateTimer, 1000);
        isRecording = true;
    }
    function updateTimer() {
        const diff = Math.floor((Date.now() - recStartTime)/1000);
        document.getElementById('rec-time').innerText = `REC \${Math.floor(diff/60)}:\${(diff%60).toString().padStart(2,'0')}`;
    }
    function stopRecording() {
        clearInterval(recInterval);
        document.getElementById('rec-indicator').style.display = 'none';
        if(SYS_PARAMS.recMode === 'data_only') saveRecording();
        else mediaRecorder.stop();
    }
    function saveRecording() {
        if(animationId) cancelAnimationFrame(animationId);
        const fd = new FormData();
        fd.append('action', 'save_video');
        fd.append('animatic_id', CONFIG.animaticId);
        fd.append('mode', SYS_PARAMS.recMode);
        if(telemetryData.length > 0) fd.append('telemetry', JSON.stringify(telemetryData));
        if(SYS_PARAMS.recMode !== 'data_only') {
            const blob = new Blob(recordedChunks, {type: mediaRecorder.mimeType});
            fd.append('video', blob);
        }
        const info = document.getElementById('info');
        info.innerText = "UPLOADING..."; info.style.color = "yellow";
        fetch(CONFIG.api, {method:'POST', body:fd})
        .then(r=>r.json())
        .then(d => { if(d.success) alert("Saved Video #" + d.video_id); else throw new Error(d.message); })
        .catch(e => alert("Error: " + e.message))
        .finally(() => { 
            info.innerText = "SAGE Motion v6.4"; info.style.color = "white"; 
            animate();
            renderer.setPixelRatio(window.devicePixelRatio);
            renderer.setSize(window.innerWidth, window.innerHeight);
            composer.setSize(window.innerWidth, window.innerHeight);
        });
    }
    function onWindowResize() {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
        composer.setSize(window.innerWidth, window.innerHeight);
    }
    function makeGuiDraggable() {
        const c = document.getElementById('gui-container');
        let isD=false, sX, sY, iL, iT;
        // Restore pos
        const savedPos = JSON.parse(localStorage.getItem('sage_motion_gui_pos') || 'null');
        if(savedPos) { c.style.left = savedPos.left; c.style.top = savedPos.top; c.style.right='auto'; }

        const down = e => { 
            if(!e.target.closest('.title')) return;
            isD=true; sX=e.clientX||e.touches[0].clientX; sY=e.clientY||e.touches[0].clientY;
            const r=c.getBoundingClientRect(); iL=r.left; iT=r.top; c.style.right='auto';
        };
        const move = e => {
            if(!isD) return;
            const x=e.clientX||(e.touches?e.touches[0].clientX:0), y=e.clientY||(e.touches?e.touches[0].clientY:0);
            c.style.left=`\${iL+(x-sX)}px`; c.style.top=`\${iT+(y-sY)}px`;
            if(e.cancelable) e.preventDefault();
        };
        const up = () => {
            if(isD) {
                isD=false;
                localStorage.setItem('sage_motion_gui_pos', JSON.stringify({left:c.style.left, top:c.style.top}));
            }
        };
        c.addEventListener('mousedown', down); window.addEventListener('mousemove', move); window.addEventListener('mouseup', up);
        c.addEventListener('touchstart', down, {passive:false}); window.addEventListener('touchmove', move, {passive:false}); window.addEventListener('touchend', up);
    }
    
    // --- REPLAY LOGIC ---
    function playTake() {
        if(!SYS_PARAMS.selectedTake) { alert("Select a take first."); return; }
        fetch(CONFIG.api + '?action=load_take&take_id=' + SYS_PARAMS.selectedTake)
        .then(r => r.json())
        .then(data => {
            if(!data.success) { alert(data.message); return; }
            replayData = data.telemetry;
            isReplaying = true;
            replayStartTime = Date.now();
            document.getElementById('replay-overlay').style.display = 'block';
        });
    }

    function stopReplay() {
        isReplaying = false;
        replayData = null;
        document.getElementById('replay-overlay').style.display = 'none';
        controls.reset();
        camera.position.set(CAM_PARAMS.camX, CAM_PARAMS.camY, CAM_PARAMS.camZ);
        controls.target.set(CAM_PARAMS.targetX, CAM_PARAMS.targetY, CAM_PARAMS.targetZ);
        camera.lookAt(controls.target);
    }
</script>
HTML;
        return $html;
    }
}