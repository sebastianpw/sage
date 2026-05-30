<?php
// public/rapid_scenes.php - Cinematic Pre-Visualization Engine (DB Config Mode)
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;

$em = $spw->getEntityManager();
$conn = $em->getConnection();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) { die('Not authenticated'); }

// --- WORD BLOOM GENERATOR ---
function getWordBloom($count = 5) {
$words = [
'iridescent', 'subliminal', 'chrome', 'petrichor', 'visceral', 'hallowed', 'decay',
'fractal', 'obsidian', 'gossamer', 'kinetic', 'void', 'resonance', 'labyrinth',
'zenith', 'eclipse', 'nebula', 'tectonic', 'silence', 'static', 'velocity', 'prism',
'chitinous', 'luminal', 'entropy', 'stasis', 'shatter', 'weave', 'pulse', 'ancient',
'brutalist', 'ether', 'phantom', 'circuitry', 'haze', 'glitch', 'sacred', 'profane'
];
shuffle($words);
return implode(', ', array_slice($words, 0, $count));
}

// --- AJAX HANDLERS ---
if (isset($_GET['action'])) {
header('Content-Type: application/json');

// 1. Get Next Scene Job
if ($_GET['action'] === 'get_next_job') {
// We fetch the next non-generated item
// Crucial: It must have a generator_config_id set via the Config page!
$sql = "SELECT * FROM rapid_showcase WHERE is_generated = 0 AND is_archived = 0 ORDER BY reference_code ASC LIMIT 1";
$job = $conn->fetchAssociative($sql);

if ($job) {
// Inject Word Bloom on the fly
$job['bloom'] = getWordBloom(6);

// Fetch Generator Title for UI display
$genTitle = 'Unknown / Default';
if (!empty($job['generator_config_id'])) {
$chk = $conn->fetchAssociative("SELECT title FROM generator_config WHERE config_id = ?", [$job['generator_config_id']]);
if ($chk) $genTitle = $chk['title'];
}

$job['generator_title'] = $genTitle;

echo json_encode(['ok' => true, 'job' => $job]);
} else {
echo json_encode(['ok' => false, 'message' => 'All scenes visualized.']);
}
exit;
}

// 2. Mark Complete & Save
if ($_GET['action'] === 'mark_complete' && isset($_POST['rapid_id'])) {
$rId = (int)$_POST['rapid_id'];
$name = $_POST['name'] ?? 'Scene';
$desc = $_POST['description'] ?? '';

// Save as Sketch
$sql = "INSERT INTO sketches (name, description, `order`, created_at, updated_at) VALUES (?, ?, 0, NOW(), NOW())";
$stmt = $conn->prepare($sql);
$stmt->bindValue(1, $name);
$stmt->bindValue(2, $desc);
$stmt->executeStatement();
$sId = $conn->lastInsertId();

// Update Queue
$upd = "UPDATE rapid_showcase SET is_generated = 1, created_sketch_id = ? WHERE id = ?";
$stmt = $conn->prepare($upd);
$stmt->bindValue(1, $sId);
$stmt->bindValue(2, $rId);
$stmt->executeStatement();

echo json_encode(['ok' => true]);
exit;
}
}
?>

<!DOCTYPE html>

<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Anima Chronicles - Pre-Viz</title>


<?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
<link rel="stylesheet" href="css/base.css">

<style>
    .container { max-width: 1100px; margin: 0 auto; padding-top: 20px; }
    
    /* Cinematic Dashboard */
    .scene-dashboard {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
        border: 1px solid #4338ca;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        color: #e0e7ff;
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    }
    
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        padding-bottom: 15px;
        margin-bottom: 15px;
    }

    .scene-info {
        display: grid;
        grid-template-columns: 1fr 2fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .info-box {
        background: rgba(0,0,0,0.3);
        padding: 10px;
        border-radius: 6px;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .info-label { font-size: 0.75rem; text-transform: uppercase; opacity: 0.7; letter-spacing: 1px; }
    .info-value { font-size: 1.1rem; font-weight: 600; color: #a5b4fc; }

    .log-console {
        background: #000;
        color: #34d399;
        font-family: 'Courier New', monospace;
        padding: 15px;
        border-radius: 8px;
        height: 200px;
        overflow-y: auto;
        border: 1px solid #059669;
        font-size: 13px;
    }

    .preview-area {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    @media(max-width: 768px) {
        .preview-area { grid-template-columns: 1fr; }
        .scene-info { grid-template-columns: 1fr; }
    }

    textarea {
        width: 100%;
        background: #1e293b;
        color: #f8fafc;
        border: 1px solid #475569;
        padding: 10px;
        border-radius: 6px;
        font-family: sans-serif;
        min-height: 300px;
    }

    .bloom-tags {
        margin-top: 5px;
        font-size: 0.9rem;
        color: #f472b6;
        font-style: italic;
    }
    
    h3 { color: var(--text); border-bottom: 2px solid var(--accent); padding-bottom: 8px; margin-bottom: 15px; }
</style>
</head>
<body>
<div class="container">


<div class="dashboard-header" style="margin-bottom:20px; border:none;">
        <h1>🎬 Anima Chronicles: Pre-Viz</h1>
        <div>
            <a href="rapid_config.php" class="btn btn-secondary">⚙️ Config</a>
            <a href="dashboard.php" class="btn btn-secondary">Exit</a>
        </div>
    </div>

    <!-- CONTROL PANEL -->
    <div class="scene-dashboard">
        <div class="dashboard-header">
            <div>
                <button id="startBtn" class="btn btn-primary" onclick="toggleSequence()">▶️ Start Production</button>
                <span id="statusBadge" style="margin-left:15px; font-weight:bold; color:#6366f1;">WAITING</span>
            </div>
            <div>
                Active Generator: <span id="activeGenName" style="color:#fbbf24;">Waiting...</span>
            </div>
        </div>

        <div class="scene-info">
            <div class="info-box">
                <div class="info-label">Reference</div>
                <div id="dispRef" class="info-value">--</div>
            </div>
            <div class="info-box">
                <div class="info-label">Scene Title</div>
                <div id="dispTitle" class="info-value">--</div>
            </div>
            <div class="info-box">
                <div class="info-label">Category</div>
                <div id="dispCat" class="info-value">--</div>
            </div>
        </div>

        <div id="console" class="log-console">
            > System initialized. Ready to visualize story arc...
        </div>
    </div>

    <!-- DATA FORM -->
    <div class="preview-area">
        <div>
            <h3>1. Context Input (The Prompt)</h3>
            <textarea id="prompt_input" readonly></textarea>
            <div id="bloomDisplay" class="bloom-tags"></div>
        </div>
        <div>
            <h3>2. AI Output (Description)</h3>
            <form id="saveForm">
                <input type="hidden" id="rapid_id" name="rapid_id">
                <input type="hidden" id="final_name" name="name">
                <textarea id="ai_output" name="description" placeholder="AI Generation will appear here..."></textarea>
            </form>
        </div>
    </div>

</div>

<script>
let running = false;
let timer = null;
// Fallback if DB is empty
const FALLBACK_GEN_ID = '9bf6de291765e2ced28589de857a9f0b'; 

function log(msg) {
    const c = document.getElementById('console');
    c.innerHTML += `\n> ${msg}`;
    c.scrollTop = c.scrollHeight;
}

function toggleSequence() {
    running = !running;
    const btn = document.getElementById('startBtn');
    if(running) {
        btn.innerText = "⏸ Pause Production";
        btn.classList.replace('btn-primary', 'btn-danger');
        document.getElementById('statusBadge').innerText = "PRODUCTION ACTIVE";
        processNext();
    } else {
        btn.innerText = "▶️ Start Production";
        btn.classList.replace('btn-danger', 'btn-primary');
        document.getElementById('statusBadge').innerText = "PAUSED";
        clearTimeout(timer);
    }
}

async function processNext() {
    if(!running) return;

    try {
        // 1. Fetch Job
        log("Fetching next scene...");
        const res = await fetch('?action=get_next_job');
        const data = await res.json();

        if(!data.ok || !data.job) {
            log("🏁 SEQUENCE COMPLETE. No more scenes to generate.");
            toggleSequence();
            return;
        }

        const job = data.job;
        log(`Loaded: ${job.reference_code}`);

        // 2. Validate Generator
        let configId = job.generator_config_id;
        let genName = job.generator_title;

        if (!configId) {
            log(`⚠️ Warning: No Generator ID assigned for this category. Using Fallback.`);
            configId = FALLBACK_GEN_ID;
            genName = "Fallback System";
        }

        // Update UI
        document.getElementById('dispRef').innerText = job.reference_code;
        document.getElementById('dispTitle').innerText = job.title;
        document.getElementById('dispCat').innerText = job.category;
        document.getElementById('activeGenName').innerText = genName;
        
        document.getElementById('rapid_id').value = job.id;
        document.getElementById('final_name').value = `${job.reference_code}: ${job.title}`;

        // 3. Construct Prompt
        const bloom = job.bloom || "shadow, light, echo";
        document.getElementById('bloomDisplay').innerText = "Word Bloom: " + bloom;

        const fullPrompt = `

SCENE CONTEXT: ${job.reference_code} - ${job.title}
CATEGORY: ${job.category}
WORD BLOOM SEED: ${bloom}

NARRATIVE ACTION:
${job.description_prompt}

INSTRUCTIONS:
Generate a visually rich, cinematic description of this scene.
Focus on lighting, atmosphere, and the specific visual manifestation of Anima powers.
Integrate the characters visually (clothing, aura, companions) into the environment.
Output ONLY the visual description text.
`.trim();


document.getElementById('prompt_input').value = fullPrompt;

        // 4. Call AI
        log(`Requesting visualization...`);
        const aiResult = await runAi(configId, fullPrompt);
        document.getElementById('ai_output').value = aiResult;

        // 5. Save
        log("Saving generated scene...");
        const formData = new FormData(document.getElementById('saveForm'));
        await fetch('?action=mark_complete', { method:'POST', body:formData });

        log("✅ Scene Complete. Cooldown 2s...");
        
        if(running) {
            timer = setTimeout(processNext, 2000);
        }

    } catch(e) {
        log("❌ ERROR: " + e.message);
        running = false;
        document.getElementById('statusBadge').innerText = "ERROR STOP";
    }
}

async function runAi(configId, prompt) {
    const params = {
        config_id: configId,
        entity_type: 'sketches',
        entity_name: prompt, // Pass context as the "trigger"
        random_seed: Math.floor(Math.random() * 999999)
    };

    const res = await fetch('/api/generate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(params)
    });
    
    const json = await res.json();
    if(!json.ok) throw new Error(json.error || "AI Generation failed");
    
    let raw = json.data;

    // PARSING LOGIC: Handle JSON string returns vs Object returns
    if (typeof raw === 'string') {
        // Check if it looks like JSON
        if (raw.trim().startsWith('{') || raw.trim().startsWith('[')) {
            try {
                raw = JSON.parse(raw);
            } catch (e) {
                // It's just text, return it
                return raw;
            }
        } else {
            return raw;
        }
    }

    // If we have an object, look for the best field
    if (typeof raw === 'object' && raw !== null) {
        if (raw.description) return raw.description;
        if (raw.text) return raw.text;
        if (raw.content) return raw.content;
        if (raw.scene) return raw.scene;
        
        // Fallback for Name Generators used by mistake
        if (raw.name) {
            return `[SYSTEM NOTE: Generated Title]: ${raw.name}\n\n(It seems a Name Generator was used. Please check Config.)`;
        }
        
        return JSON.stringify(raw, null, 2);
    }

    return String(raw);
}
</script>
</body>
</html>
