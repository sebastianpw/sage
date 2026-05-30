<?php
// public/rapid_import.php - MD Importer for Rapid Showcase
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$em = $spw->getEntityManager();
$conn = $em->getConnection();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) { die('Not authenticated'); }

// --- LOAD INSTRUCTIONS FROM FILE ---
$instructionFile = __DIR__ . '/rapid.json';
$instructionContent = file_exists($instructionFile) 
    ? file_get_contents($instructionFile) 
    : "{\n  \"error\": \"rapid.json not found in public directory.\"\n}";

// --- AJAX HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['md_file'])) {
    header('Content-Type: application/json');
    
    $file = $_FILES['md_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'File upload failed error code: ' . $file['error']]);
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'md' && $ext !== 'txt') {
        echo json_encode(['ok' => false, 'error' => 'Only .md or .txt files allowed']);
        exit;
    }

    $content = file_get_contents($file['tmp_name']);
    $stats = ['processed' => 0, 'inserted' => 0, 'updated' => 0, 'errors' => 0, 'log' => []];

    // 1. Fetch all available generators for smart mapping
    $genRows = $conn->fetchAllAssociative("SELECT config_id, title FROM generator_config WHERE active=1");
    $genMap = []; // Title -> ConfigID
    foreach($genRows as $row) {
        // Normalize key for fuzzy matching (lowercase, no spaces)
        $key = strtolower(preg_replace('/[^a-z0-9]/i', '', $row['title']));
        $genMap[$key] = $row['config_id'];
    }
    
    // Default Fallback Generator ID (NuSketch Desc Gen)
    $fallbackGenId = '446437576e785bbf3d188624dd9794eb'; 

    // 2. Parse Logic
    $sections = preg_split('/^##\s+(.+)$/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    
    // Loop sections
    for ($i = 1; $i < count($sections); $i += 2) {
        $categoryRaw = trim($sections[$i]);
        // Remove numbering if present (e.g. "1. NOVA TERRA" -> "NOVA TERRA")
        $category = preg_replace('/^\d+\.\s+/', '', $categoryRaw);
        $block = $sections[$i+1];

        // A. Determine Generator
        $currentGenId = $fallbackGenId; // Start with default
        $genNameFound = "Default";

        if (preg_match('/\*\*Generator\*\*:\s*`?([^`\n]+)`?/i', $block, $m)) {
            $rawGenName = trim($m[1]);
            $lookupKey = strtolower(preg_replace('/[^a-z0-9]/i', '', $rawGenName));
            
            // Exact/Fuzzy Map Search
            if (isset($genMap[$lookupKey])) {
                $currentGenId = $genMap[$lookupKey];
                $genNameFound = $rawGenName . " (Matched)";
            } else {
                // Try partial match
                foreach ($genMap as $k => $id) {
                    if (str_contains($k, $lookupKey) || str_contains($lookupKey, $k)) {
                        $currentGenId = $id;
                        $genNameFound = $rawGenName . " (Fuzzy Match)";
                        break;
                    }
                }
            }
        }

        // B. Parse Scenarios
        // Regex: ### [CODE]: [Title] ... ```[content]```
        $pattern = '/###\s*([A-Z0-9-_]+):\s*(.*?)\s*\n.*?```(.*?)```/s';
        
        if (preg_match_all($pattern, $block, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $refCode = trim($match[1]);
                $title = trim($match[2]);
                $promptRaw = trim($match[3]);
                $cleanPrompt = preg_replace('/\s+/', ' ', $promptRaw); // Flatten newlines

                $stats['processed']++;

                try {
                    // MODIFIED: ALWAYS INSERT (Never Update)
                    // We skip checking for existing $refCode to allow duplicates/new entries.
                    
                    $ins = $conn->prepare("INSERT INTO rapid_showcase (reference_code, title, category, description_prompt, generator_config_id, is_generated, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
                    $ins->bindValue(1, $refCode);
                    $ins->bindValue(2, $title);
                    $ins->bindValue(3, $category);
                    $ins->bindValue(4, $cleanPrompt);
                    $ins->bindValue(5, $currentGenId);
                    $ins->executeStatement();
                    
                    $stats['inserted']++;
                    $stats['log'][] = "[NEW] $refCode ($category)";

                } catch (Exception $ex) {
                    $stats['errors']++;
                    $stats['log'][] = "[ERR] $refCode: " . $ex->getMessage();
                }
            }
        }
    }

    echo json_encode(['ok' => true, 'stats' => $stats]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapid MD Importer</title>
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
        .container { max-width: 800px; margin: 0 auto; padding-top: 20px; }
        
        /* Condensed Upload Zone */
        .upload-zone {
            border: 2px dashed rgba(128,128,128,0.4);
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            background: rgba(128,128,128,0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .upload-zone:hover, .upload-zone.dragover {
            border-color: var(--accent);
            background: rgba(var(--accent-rgb, 59,130,246), 0.1);
        }

        .upload-icon { font-size: 32px; display: block; margin-bottom: 8px; }
        
        .log-console {
            background: #0f172a;
            color: #4ade80;
            font-family: monospace;
            padding: 12px;
            border-radius: 6px;
            height: 250px;
            overflow-y: auto;
            font-size: 12px;
            line-height: 1.4;
            border: 1px solid rgba(255,255,255,0.1);
            display: none; /* Hidden until upload */
        }

        .stats-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 13px;
            font-weight: bold;
            display: none;
        }
        
        .stat-item { padding: 4px 8px; border-radius: 4px; background: var(--card); border: 1px solid rgba(128,128,128,0.2); }
        .stat-new { color: var(--green); }
        .stat-upd { color: var(--accent); }
        .stat-err { color: var(--red); }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap:10px; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .actions-group { display: flex; gap: 8px; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            display: none; justify-content: center; align-items: center;
            z-index: 1000;
        }
        .modal-card {
            background: var(--card);
            padding: 24px;
            border-radius: 12px;
            width: 90%; max-width: 700px;
            max-height: 85vh;
            display: flex; flex-direction: column;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            border: 1px solid rgba(128,128,128,0.2);
        }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
        .modal-header h3 { margin:0; }
        .modal-close { cursor: pointer; font-size: 1.5rem; line-height: 1; }
        
        pre.json-box {
            background: #1e1e1e;
            color: #dcdcdc;
            padding: 12px;
            border-radius: 6px;
            overflow: auto;
            font-size: 12px;
            flex-grow: 1;
            white-space: pre-wrap;
            border: 1px solid #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📥 Import Scenarios</h1>
            <div class="actions-group">
                <button class="btn btn-secondary" onclick="openInstructionModal()">📜 Instructions</button>
                <a href="rapid_gen.php" class="btn btn-primary">Generator</a>
                <a href="rapid_config.php" class="btn btn-secondary">Config</a>
            </div>
        </div>

        <div class="card" style="padding:20px; background:var(--card); border-radius:8px; border:1px solid rgba(128,128,128,0.15);">
            <p style="color:var(--text-muted); margin-bottom:15px; font-size:0.9rem;">
                Drag & Drop a Markdown file to parse scenarios into the <code>rapid_showcase</code> table. 
                <strong style="color:var(--accent);">Always creates new entries (No Overwrites).</strong>
            </p>

            <form id="uploadForm">
                <input type="file" id="fileInput" name="md_file" accept=".md,.txt" style="display:none">
                
                <div class="upload-zone" id="dropZone">
                    <span class="upload-icon">📄</span>
                    <strong>Click to Browse</strong> or Drag MD File here
                </div>
            </form>

            <div id="statsBar" class="stats-bar">
                <div class="stat-item stat-new">New: <span id="countNew">0</span></div>
                <!-- Updated will likely remain 0 now, but kept for compatibility -->
                <div class="stat-item stat-upd" style="display:none;">Updated: <span id="countUpd">0</span></div>
                <div class="stat-item stat-err">Errors: <span id="countErr">0</span></div>
            </div>

            <div id="importLog" class="log-console"></div>
        </div>
    </div>

    <!-- Instruction Modal -->
    <div id="instructionModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>🤖 AI Instruction Protocol</h3>
                <span class="modal-close" onclick="closeInstructionModal()">×</span>
            </div>
            <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom:10px;">
                Copy the JSON below and provide it to an LLM (ChatGPT, Claude, etc.) to generate perfectly formatted Markdown files for this importer.
            </p>
            
            <!-- Dynamically Loaded Content from rapid.json -->
            <pre class="json-box" id="jsonInstruction"><?php echo htmlspecialchars($instructionContent); ?></pre>
            
            <div style="text-align:right; margin-top:15px;">
                <button class="btn btn-primary" onclick="copyInstruction()">📋 Copy JSON</button>
            </div>
        </div>
    </div>

    <!-- Sage Home Button -->
    <script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const logConsole = document.getElementById('importLog');
        const statsBar = document.getElementById('statsBar');
        const modal = document.getElementById('instructionModal');

        function openInstructionModal() { modal.style.display = 'flex'; }
        function closeInstructionModal() { modal.style.display = 'none'; }
        
        function copyInstruction() {
            const text = document.getElementById('jsonInstruction').textContent;
            navigator.clipboard.writeText(text).then(() => {
                const btn = document.querySelector('.modal-card .btn-primary');
                const orig = btn.textContent;
                btn.textContent = "Copied!";
                setTimeout(() => btn.textContent = orig, 1500);
            });
        }

        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target == modal) closeInstructionModal();
        }

        // Click to browse
        dropZone.addEventListener('click', () => fileInput.click());

        // Drag & Drop
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if(e.dataTransfer.files.length) {
                handleUpload(e.dataTransfer.files[0]);
            }
        });

        // File Input Change
        fileInput.addEventListener('change', (e) => {
            if(fileInput.files.length) handleUpload(fileInput.files[0]);
        });

        function log(msg) {
            logConsole.innerHTML += `<div>${msg}</div>`;
            logConsole.scrollTop = logConsole.scrollHeight;
        }

        async function handleUpload(file) {
            // UI Reset
            dropZone.innerHTML = '<span class="upload-icon">⏳</span>Processing...';
            logConsole.style.display = 'block';
            logConsole.innerHTML = '<div>Starting upload...</div>';
            statsBar.style.display = 'flex';
            
            const formData = new FormData();
            formData.append('md_file', file);

            try {
                const res = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();

                if (data.ok) {
                    dropZone.innerHTML = '<span class="upload-icon">✅</span>Done! Drag another?';
                    document.getElementById('countNew').textContent = data.stats.inserted;
                    document.getElementById('countUpd').textContent = data.stats.updated;
                    document.getElementById('countErr').textContent = data.stats.errors;

                    log(`<strong>Results:</strong> ${data.stats.inserted} New.`);
                    log('-----------------------------------');
                    data.stats.log.forEach(line => log(line));
                } else {
                    dropZone.innerHTML = '<span class="upload-icon">⚠️</span>Error';
                    log(`<span style="color:var(--red)">Upload Error: ${data.error}</span>`);
                }

            } catch (e) {
                dropZone.innerHTML = '<span class="upload-icon">❌</span>Crash';
                log(`<span style="color:var(--red)">System Error: ${e.message}</span>`);
            }
        }
    </script>
</body>
</html>