<?php
// public/rapid_gen.php - Rapid Showcase Generator
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;

// --- ID Constants for Generators (Fallbacks) ---
define('ID_NAME_GEN', '9bf6de291765e2ced28589de857a9f0b'); 
define('ID_DESC_GEN', '446437576e785bbf3d188624dd9794eb');

$em = $spw->getEntityManager();
$conn = $em->getConnection();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) { die('Not authenticated'); }

// --- AJAX HANDLERS FOR RAPID MODE ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // 1. Get Next Job
    if ($_GET['action'] === 'get_next_job') {
        // Exclude archived entries
        $sql = "SELECT * FROM rapid_showcase WHERE is_generated = 0 AND is_archived = 0 ORDER BY id ASC LIMIT 1";
        $job = $conn->fetchAssociative($sql);
        
        if ($job) {
            echo json_encode(['ok' => true, 'job' => $job]);
        } else {
            echo json_encode(['ok' => false, 'message' => 'No more pending active scenarios.']);
        }
        exit;
    }

    // 2. Mark Job Complete
    if ($_GET['action'] === 'mark_complete' && isset($_POST['rapid_id'], $_POST['sketch_id'])) {
        $rId = (int)$_POST['rapid_id'];
        $sId = (int)$_POST['sketch_id'];
        
        $upd = "UPDATE rapid_showcase SET is_generated = 1, created_sketch_id = ? WHERE id = ?";
        $stmt = $conn->prepare($upd);
        $stmt->bindValue(1, $sId);
        $stmt->bindValue(2, $rId);
        $stmt->executeStatement();
        
        echo json_encode(['ok' => true]);
        exit;
    }
}

// Fetch Generators
$repo = $em->getRepository(App\Entity\GeneratorConfig::class);
$generators = [];
if ($userId) {
    $qb = $repo->createQueryBuilder('g')
       ->where('g.userId = :userId OR g.isPublic = :isPublic')
       ->andWhere('g.active = :isActive')
       ->setParameter('userId', $userId)
       ->setParameter('isPublic', true)
       ->setParameter('isActive', true)
       ->orderBy('g.title', 'ASC');
    $generators = $qb->getQuery()->getResult();
}

// Standard Entity Saving Logic
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $payload = $_POST;
    
    $name = trim($payload['name'] ?? '');
    $description = trim($payload['description'] ?? '');
    
    if (empty($name)) $errors[] = 'Name is required';

    if (empty($errors)) {
        $sql = "INSERT INTO sketches 
                (name, description, `order`, created_at, updated_at) 
                VALUES (?, ?, 0, NOW(), NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $name);
        $stmt->bindValue(2, $description);
        $stmt->executeStatement();

        $newId = (int)$conn->lastInsertId();

        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'created' => true,
            'id' => $newId,
            'message' => 'Sketch created successfully'
        ]);
        exit;
    } else {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['ok' => false, 'errors' => $errors]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapid Showcase Generator</title>
    
    <script>
    (function() {
        try {
            var theme = localStorage.getItem('spw_theme');
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else if (theme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        } catch (e) {}
    })();
    </script>

    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
    <link rel="stylesheet" href="css/base.css">
    
    <style>
        .container { max-width: 900px; margin: 0 auto; padding-top: 20px; }
        
        /* Dashboard Card styling */
        .rapid-dashboard {
            background: rgba(var(--accent-rgb, 59, 130, 246), 0.08);
            border: 1px solid rgba(var(--accent-rgb, 59, 130, 246), 0.25);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 10px;
            transition: all 0.3s ease;
        }
        
        .status-idle { background: var(--text-muted); color: var(--bg); opacity: 0.5; }
        .status-running { background: var(--accent); color: white; box-shadow: 0 0 10px rgba(var(--accent-rgb), 0.4); }

        .log-console {
            background: #0f172a; /* Always dark for contrast */
            color: #4ade80;       /* Terminal green */
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            padding: 12px;
            border-radius: 8px;
            height: 160px;
            overflow-y: auto;
            font-size: 12px;
            line-height: 1.5;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .form-section {
            background: var(--card);
            padding: 24px;
            border-radius: 12px;
            border: 1px solid rgba(128,128,128,0.15);
            box-shadow: var(--card-elevation, 0 2px 4px rgba(0,0,0,0.05));
        }

        .form-group { margin-bottom: 20px; }
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: var(--text); 
            font-size: 0.9rem; 
        }
        
        input[type="text"], textarea, select { 
            width: 100%; 
            padding: 12px; 
            border-radius: 8px; 
            border: 1px solid rgba(128,128,128,0.3); 
            background: var(--bg); 
            color: var(--text); 
            font-family: inherit;
            transition: border-color 0.2s;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(var(--accent-rgb), 0.1);
        }

        textarea.context-box { 
            min-height: 120px; 
            background: rgba(128,128,128,0.05); 
            color: var(--text-muted); 
            font-family: monospace;
            font-size: 13px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        /* Mobile tweaks */
        @media (max-width: 600px) {
            .dashboard-header { flex-direction: column; align-items: stretch; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header with Home Button -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <h1 style="margin:0; font-size:1.5rem;">🚀 Rapid Showcase</h1>
            </div>
            <div class="header-actions">
                <a href="rapid_import.php" class="btn btn-secondary">📥 Import</a>
                <a href="rapid_config.php" class="btn btn-secondary">⚙️ Config</a>
            </div>
        </div>

        <!-- DASHBOARD -->
        <div class="rapid-dashboard">
            <div class="dashboard-header">
                <div style="display:flex; align-items:center; flex-wrap:wrap;">
                    <button id="startRapidBtn" class="btn btn-primary" onclick="toggleRapidLoop()">
                        ▶️ Start Sequence
                    </button>
                    <span id="jobStatus" class="status-badge status-idle">Idle</span>
                </div>
                <div style="font-size:14px; font-weight:600; color:var(--text);">
                    Remaining Pending Jobs: <span id="jobsCount" style="color:var(--accent);">...</span>
                </div>
            </div>
            
            <div id="rapidLog" class="log-console">
                <div>[System] Ready to process active scenarios...</div>
            </div>
        </div>

        <!-- PREVIEW FORM -->
        <div class="form-section">
            <form id="entityForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" id="rapid_ref" name="rapid_ref" value="">
                <input type="hidden" id="rapid_id" value="">

                <div class="form-group">
                    <label>Active Generator (Auto-selected from Config)</label>
                    <select id="descGeneratorSelect" class="generator-select" disabled style="opacity:0.7;">
                        <option value="">-- Manual / Default --</option>
                        <?php foreach ($generators as $gen): ?>
                            <option value="<?= $gen->getConfigId() ?>">
                                <?= htmlspecialchars($gen->getTitle()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Current Scenario Context</label>
                    <textarea id="scenario_prompt" class="context-box" readonly></textarea>
                </div>

                <div class="form-group">
                    <label>Generated Name</label>
                    <input type="text" id="name" name="name" required placeholder="Waiting for generation...">
                </div>

                <div class="form-group">
                    <label>Generated Description</label>
                    <textarea id="description" name="description" required style="min-height:180px;" placeholder="Waiting for generation..."></textarea>
                </div>
            </form>
        </div>
    </div>

    <!-- Sage Home Button Script -->
    <script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

    <script>
    let isRunning = false;
    let loopTimer = null;

    function log(msg) {
        const c = document.getElementById('rapidLog');
        const time = new Date().toLocaleTimeString([], { hour12: false });
        c.innerHTML += `<div><span style="opacity:0.5">[${time}]</span> ${msg}</div>`;
        c.scrollTop = c.scrollHeight;
    }

    function toggleRapidLoop() {
        isRunning = !isRunning;
        const btn = document.getElementById('startRapidBtn');
        const badge = document.getElementById('jobStatus');
        
        if (isRunning) {
            btn.innerHTML = '⏸ Stop Sequence';
            btn.classList.replace('btn-primary', 'btn-danger');
            badge.textContent = 'RUNNING';
            badge.classList.replace('status-idle', 'status-running');
            processNextJob();
        } else {
            btn.innerHTML = '▶️ Start Sequence';
            btn.classList.replace('btn-danger', 'btn-primary');
            badge.textContent = 'PAUSED';
            badge.classList.replace('status-running', 'status-idle');
            clearTimeout(loopTimer);
        }
    }

    async function processNextJob() {
        if (!isRunning) return;

        try {
            // 1. Fetch Job
            log('🔍 Fetching next scenario...');
            const res = await fetch('?action=get_next_job');
            const data = await res.json();

            if (!data.ok || !data.job) {
                log('✨ <strong>Queue empty!</strong> All active scenarios processed.');
                document.getElementById('jobsCount').textContent = '0';
                toggleRapidLoop();
                return;
            }

            const job = data.job;
            log(`📂 Loaded: <strong>${job.reference_code}</strong>`);
            
            // 2. Setup Form
            document.getElementById('rapid_ref').value = job.reference_code;
            document.getElementById('rapid_id').value = job.id;
            
            const fullContext = `TITLE: ${job.title}\nCATEGORY: ${job.category}\n\nSCENARIO:\n${job.description_prompt}`;
            document.getElementById('scenario_prompt').value = fullContext;

            // 3. Select Generator
            const genSelect = document.getElementById('descGeneratorSelect');
            if (job.generator_config_id) {
                genSelect.value = job.generator_config_id;
                if (genSelect.selectedIndex === -1) {
                    log(`⚠️ Assigned Gen ID not found. Using default.`);
                    genSelect.selectedIndex = 1; 
                }
            } else {
                genSelect.selectedIndex = 1; 
            }

            // 4. Generate Description
            log('🧠 Generating Description...');
            const desc = await runAiGeneration(genSelect.value, fullContext, 'description');
            document.getElementById('description').value = desc;

            // 5. Generate Name
            log('🏷️ Generating Name...');
            // Try to find Name generator
            let nameGenId = '9bf6de291765e2ced28589de857a9f0b'; // Fallback
            for(let opt of genSelect.options) {
                if(opt.text.toLowerCase().includes("name gen")) nameGenId = opt.value;
            }
            
            const name = await runAiGeneration(nameGenId, desc, 'name'); 
            // Prepend Reference code WITHOUT Brackets
            document.getElementById('name').value = `${job.reference_code}: ${name.replace(/^"/, '').replace(/"$/, '')}`;

            // 6. Save
            log('💾 Saving Sketch...');
            await saveForm(job.id);

            // 7. Loop
            if (isRunning) {
                log('⏳ Cooling down (2s)...');
                loopTimer = setTimeout(processNextJob, 2000);
            }

        } catch (e) {
            log(`❌ Error: ${e.message}`);
            toggleRapidLoop();
        }
    }

    async function runAiGeneration(configId, contextText, fieldType) {
        const params = {
            config_id: configId,
            entity_type: 'sketches', 
            entity_name: contextText, 
            random_seed: Math.floor(Math.random() * 1000000)
        };

        const res = await fetch('/api/generate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(params)
        });
        
        const json = await res.json();
        if (!json.ok) throw new Error(json.error || 'AI Gen Failed');

        if (json.data.description) return json.data.description;
        if (json.data.name) return json.data.name;
        if (json.data.text) return json.data.text;
        if (typeof json.data === 'string') return json.data;
        return JSON.stringify(json.data);
    }

    async function saveForm(rapidId) {
        const form = document.getElementById('entityForm');
        const formData = new FormData(form);
        
        // 1. Save Entity
        const res = await fetch('rapid_gen.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (!json.ok) throw new Error(json.errors ? json.errors.join(', ') : 'Save failed');
        
        const newSketchId = json.id;
        
        // 2. Mark Complete
        const markParams = new FormData();
        markParams.append('rapid_id', rapidId);
        markParams.append('sketch_id', newSketchId);
        
        await fetch('?action=mark_complete', { method: 'POST', body: markParams });
        log(`✅ Created Sketch #${newSketchId}`);
    }
    </script>
</body>
</html>