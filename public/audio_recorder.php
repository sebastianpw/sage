<?php
/**
 * Audio Recorder & Saver
 * Replicates LinguaRecorder UI.
 * Handles wav2wav=1 (Update Entity Source) and wav2wav=0 (Create New Map Run Result).
 * 
 * Local Vendor Path: /vendor/LinguaRecorder-master/src/LinguaRecorder.js
 */

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// --- 1. API / POST HANDLER ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['audio_blob'])) {
    header('Content-Type: application/json');

    try {
        $entityType = $_POST['entity_type'] ?? '';
        $entityId   = (int)($_POST['entity_id'] ?? 0);
        $wav2wav    = (int)($_POST['wav2wav'] ?? 1); 

        if (!$entityType || !$entityId) {
            throw new Exception("Missing entity_type or entity_id");
        }

        // 1. Generate Filename using DB Counter
        $pdo->beginTransaction();
        $pdo->exec("UPDATE audio_counter SET next_audio = LAST_INSERT_ID(next_audio + 1)");
        $countId = $pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
        $pdo->commit();

        $baseName = "audio" . str_pad($countId, 7, '0', STR_PAD_LEFT);
        $fileName = $baseName . ".wav";
        $relPath  = "/audios/" . $fileName;
        $absPath  = __DIR__ . "/audios/" . $fileName;

        if (!is_dir(__DIR__ . "/audios")) {
            mkdir(__DIR__ . "/audios", 0777, true);
        }

        // 2. Save File
        if (!move_uploaded_file($_FILES['audio_blob']['tmp_name'], $absPath)) {
            throw new Exception("Failed to save audio file to disk.");
        }

        // 3. Database Operations
        $audioId = 0;

        if ($wav2wav === 1) {
            // --- CASE: WAV2WAV = 1 (Source Audio) ---
            $stmt = $pdo->prepare("INSERT INTO audios (name, filename, entity_type, entity_id, created_at) VALUES (:name, :file, :type, :eid, NOW())");
            $stmt->execute([
                'name' => $baseName . " (Source)",
                'file' => $relPath,
                'type' => $entityType,
                'eid'  => $entityId
            ]);
            $audioId = $pdo->lastInsertId();

            $updateSql = "UPDATE `$entityType` SET wav2wav = 1, wav2wav_audio_id = :aid, wav2wav_audio_filename = :file WHERE id = :eid";
            $stmtUpd = $pdo->prepare($updateSql);
            $stmtUpd->execute(['aid' => $audioId, 'file' => $relPath, 'eid' => $entityId]);

        } else {
            // --- CASE: WAV2WAV = 0 (Result Audio) ---
            // A. Create Map Run
            $stmtMR = $pdo->prepare("INSERT INTO map_runs (entity_type, note, created_at) VALUES (:type, 'Manual Recording', NOW())");
            $stmtMR->execute(['type' => $entityType]);
            $mapRunId = $pdo->lastInsertId();

            // B. Insert Audio
            $stmt = $pdo->prepare("INSERT INTO audios (name, filename, entity_type, entity_id, map_run_id, created_at) VALUES (:name, :file, :type, :eid, :mrid, NOW())");
            $stmt->execute([
                'name' => $baseName,
                'file' => $relPath,
                'type' => $entityType,
                'eid'  => $entityId,
                'mrid' => $mapRunId
            ]);
            $audioId = $pdo->lastInsertId();

            // C. Insert into Mapping Table
            $mapTable = "audios_2_" . $entityType;
            // Check if table exists first? Usually safe to assume if naming convention holds.
            $stmtMapLink = $pdo->prepare("INSERT INTO `$mapTable` (from_id, to_id) VALUES (:aid, :eid)");
            $stmtMapLink->execute(['aid' => $audioId, 'eid' => $entityId]);
        }

        echo json_encode(['status' => 'ok', 'filename' => $relPath, 'id' => $audioId]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --- 2. VIEW RENDERER --------------------------------------------------------

$entityType = $_GET['entity_type'] ?? '';
$entityId   = (int)($_GET['entity_id'] ?? 0);
$wav2wav    = (int)($_GET['wav2wav'] ?? 1);

if (!$entityType || !$entityId) {
    die("Error: Missing entity_type or entity_id parameters.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audio Recorder</title>
    
    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/toast.css">
    <script src="/js/toast.js"></script>

    <style>
        body { background: var(--bg); color: var(--text); padding: 20px; font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .recorder-wrapper { width: 100%; max-width: 500px; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 30px; box-shadow: var(--card-elevation); text-align: center; }
        canvas#visualizer { width: 100%; height: 100px; background: #111; border-radius: 6px; margin-bottom: 25px; border: 1px solid var(--border); }
        .controls { display: flex; justify-content: center; gap: 15px; margin-bottom: 25px; }
        .control-btn { width: 60px; height: 60px; border-radius: 50%; border: 2px solid var(--border); background: var(--bg); color: var(--text); font-size: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .control-btn:disabled { opacity: 0.3; cursor: not-allowed; }
        .control-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); transform: scale(1.1); }
        #btn-record.recording { background: var(--red); color: white; border-color: var(--red); animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); } 70% { box-shadow: 0 0 0 15px rgba(220, 53, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }
        #btn-save { width: 100%; padding: 12px; border-radius: 8px; font-weight: 600; background: var(--green); color: white; border: none; font-size: 1rem; cursor: pointer; margin-top: 10px; }
        #btn-save:disabled { background: var(--border); }
        .log-box { margin-top: 20px; text-align: left; font-family: monospace; font-size: 0.8rem; color: var(--text-muted); background: rgba(0,0,0,0.2); padding: 10px; border-radius: 4px; height: 100px; overflow-y: auto; border: 1px solid var(--border); }
    </style>
</head>
<body>
<div class="recorder-wrapper">
    <h2 style="margin-top:0; margin-bottom: 5px;">🎙️ Recorder</h2>
    <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom: 25px;">
        <?php echo ucfirst(str_replace('_',' ',$entityType)); ?> #<?php echo $entityId; ?>
        <span class="badge" style="margin-left:5px; font-size:0.7em; background:var(--border);">mode: <?php echo $wav2wav ? 'Source' : 'Result'; ?></span>
    </p>
    <canvas id="visualizer"></canvas>
    <div class="audio-preview" style="margin-bottom: 20px; display: none;">
        <audio id="player" controls style="width: 100%; border-radius: 30px;"></audio>
    </div>
    <div class="controls">
        <button id="btn-record" class="control-btn" title="Record">●</button>
        <button id="btn-pause" class="control-btn" title="Pause" disabled>⏸</button>
        <button id="btn-stop" class="control-btn" title="Stop" disabled>■</button>
    </div>
    <button id="btn-save" disabled>💾 Save Recording</button>
    <div class="log-box" id="console">Initializing...</div>
    <div style="margin-top:20px;">
        <button id="btn-cancel" class="btn btn-sm btn-outline-secondary">← Cancel & Return</button>
    </div>
</div>
<?php echo $eruda; ?>
<script type="module">
    import { LinguaRecorder } from '/vendor/LinguaRecorder-master/src/LinguaRecorder.js';

    const ENTITY_TYPE = '<?php echo $entityType; ?>';
    const ENTITY_ID   = <?php echo $entityId; ?>;
    const WAV2WAV     = <?php echo $wav2wav; ?>;

    const btnRecord = document.getElementById('btn-record');
    const btnPause  = document.getElementById('btn-pause');
    const btnStop   = document.getElementById('btn-stop');
    const btnSave   = document.getElementById('btn-save');
    const btnCancel = document.getElementById('btn-cancel');
    const player    = document.getElementById('player');
    const previewDiv = document.querySelector('.audio-preview');
    const logger    = document.getElementById('console');
    const canvas    = document.getElementById('visualizer');
    const canvasCtx = canvas.getContext("2d");

    let audioBlob = null;
    let isVisualizing = false;

    function log(msg) {
        const line = document.createElement('div');
        line.textContent = `> ${msg}`;
        logger.prepend(line);
    }
    
    // --- Helper to Close ---
    function closeMe() {
        if (window.parent && window.parent.closeAudioModal) {
            window.parent.closeAudioModal();
        } else {
            window.history.back();
        }
    }

    const recorder = new LinguaRecorder();

    // --- Visualization ---
    canvas.width = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;
    function animateViz() {
        if (!isVisualizing) return;
        requestAnimationFrame(animateViz);
        const w = canvas.width;
        const h = canvas.height;
        const ctx = canvasCtx;
        ctx.fillStyle = '#111';
        ctx.fillRect(0, 0, w, h);
        ctx.lineWidth = 2;
        ctx.strokeStyle = '#3b82f6';
        ctx.beginPath();
        const sliceWidth = w * 1.0 / 50;
        let x = 0;
        for (let i = 0; i < 50; i++) {
            const v = (Math.random() * 0.6) + 0.2; 
            const y = v * h / 2;
            if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            x += sliceWidth;
        }
        ctx.lineTo(canvas.width, canvas.height / 2);
        ctx.stroke();
    }

    // --- Events ---
    recorder.on('ready', () => log('Ready.'));
    recorder.on('started', () => {
        log('Recording...');
        btnRecord.classList.add('recording');
        btnRecord.disabled = true;
        btnPause.disabled = false;
        btnStop.disabled = false;
        btnSave.disabled = true;
        previewDiv.style.display = 'none';
        isVisualizing = true;
        animateViz();
    });
    recorder.on('paused', () => {
        log('Paused.');
        btnRecord.classList.remove('recording');
        btnPause.textContent = '●';
        isVisualizing = false;
    });
    recorder.on('stopped', (record) => {
        log('Stopped.');
        btnRecord.classList.remove('recording');
        btnRecord.disabled = false;
        btnPause.disabled = true;
        btnStop.disabled = true;
        isVisualizing = false;
        audioBlob = record.getBlob(); 
        const url = URL.createObjectURL(audioBlob);
        player.src = url;
        previewDiv.style.display = 'block';
        btnSave.disabled = false;
        log(`Captured ${record.getDuration().toFixed(2)}s audio.`);
    });

    // --- Actions ---
    btnRecord.onclick = () => recorder.start();
    
    btnPause.onclick = () => {
        if (recorder.state === 'recording') {
            recorder.pause();
            btnPause.textContent = '▶';
        } else {
            recorder.resume();
            btnPause.textContent = '⏸';
        }
    };

    btnStop.onclick = () => recorder.stop();
    btnCancel.onclick = () => closeMe();

    btnSave.onclick = () => {
        if (!audioBlob) return;
        btnSave.disabled = true;
        btnSave.textContent = 'Saving...';
        
        const formData = new FormData();
        formData.append('audio_blob', audioBlob, 'rec.wav');
        formData.append('entity_type', ENTITY_TYPE);
        formData.append('entity_id', ENTITY_ID);
        formData.append('wav2wav', WAV2WAV);

        fetch(window.location.href, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'ok') {
                log('Saved!');
                Toast.show('Saved successfully', 'success');
                btnSave.textContent = 'Saved ✓';
                setTimeout(() => closeMe(), 1000); // Close modal after 1s
            } else {
                throw new Error(data.message);
            }
        })
        .catch(e => {
            log('Error: ' + e.message);
            Toast.show('Save Failed', 'error');
            btnSave.disabled = false;
            btnSave.textContent = '💾 Save Recording';
        });
    };
</script>
</body>
</html>