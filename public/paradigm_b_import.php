<?php
// public/paradigm_b_import.php
require_once __DIR__ . '/bootstrap.php';

$shotId = (int)($_GET['shot_id'] ?? 0);
if (!$shotId) die("No Shot ID provided.");

?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Import Dialogue Array</title>
<link rel="stylesheet" href="/css/base.css">
<style>
:root {
    --bg: #07070d; --card: #0f0f1a; --border: #1a1a2e; 
    --text: #d4d4e8; --muted: #4a4a6a; --accent: #6c63ff; 
    --green: #22d3a0; --red: #f05060;
}
[data-theme="light"], html[data-theme="light"] {
    --bg: #f4f4f8; --card: #ffffff; --border: #d0d0e0; 
    --text: #1a1a2e; --muted: #888899;
}
body { background: var(--bg); color: var(--text); font-family: 'DM Mono', monospace; padding: 20px; margin: 0; }
h2 { font-size: 1.2rem; color: var(--accent); margin-top: 0; }
.desc { font-size: 0.8rem; color: var(--muted); margin-bottom: 15px; line-height: 1.4; }
code { background: rgba(128,128,128,0.2); padding: 2px 4px; border-radius: 3px; font-size: 0.75rem; }
textarea { 
    width: 100%; height: 350px; background: var(--card); color: var(--text); 
    border: 1px solid var(--border); padding: 12px; font-family: inherit; 
    border-radius: 6px; resize: vertical; box-sizing: border-box;
    font-size: 0.85rem;
}
textarea:focus { outline: none; border-color: var(--accent); }
.btn { 
    background: var(--card); color: var(--text); border: 1px solid var(--border); 
    padding: 8px 16px; border-radius: 4px; font-family: inherit; cursor: pointer; 
    transition: all 0.2s; margin-top: 15px;
}
.btn:hover { border-color: var(--accent); color: var(--accent); }
#msg { margin-top: 15px; font-size: 0.85rem; }
.error { color: var(--red); }
.success { color: var(--green); }
</style>
</head>
<body>

<h2>Import Dialogue to Shot #<?= $shotId ?></h2>
<div class="desc">
    Paste a JSON array of objects. Missing fields will gracefully default or turn null.<br>
    <strong>Schema:</strong> <code>name</code> (str), <code>description</code> (str), <code>character_id</code> (int), <code>audio_voice_identity_id</code> (int), <code>pitch_shift</code> (int).
</div>

<textarea id="jsonInput" placeholder="[
  {
    &quot;name&quot;: &quot;Line X&quot;,
    &quot;description&quot;: &quot;Your dialogue text here...&quot;,
    &quot;character_id&quot;: 1,
    &quot;audio_voice_identity_id&quot;: 5,
    &quot;pitch_shift&quot;: 0
  }
]"></textarea>

<button class="btn" onclick="doImport()">Import JSON</button>
<button class="btn" style="margin-left: 10px;" onclick="loadSchemaExample()">Example JSON</button>
<div id="msg"></div>

<script>
function loadSchemaExample() {
    const example = [
        {
            "name": "Line 1",
            "description": "This is the spoken dialogue text.",
            "character_id": 1,
            "audio_voice_identity_id": 5,
            "pitch_shift": 0
        },
        {
            "name": "Line 2 (Minimal Example)",
            "description": "Only providing description is fine too. Other fields will default to null/empty."
        }
    ];
    document.getElementById('jsonInput').value = JSON.stringify(example, null, 2);
    const msg = document.getElementById('msg');
    msg.textContent = 'Example loaded. All fields are optional.';
    msg.className = '';
}

function doImport() {
    const val = document.getElementById('jsonInput').value.trim();
    const msg = document.getElementById('msg');
    
    if (!val) { 
        msg.textContent = 'Please paste JSON data first.'; 
        msg.className = 'error'; 
        return; 
    }
    
    try {
        const parsed = JSON.parse(val);
        if (!Array.isArray(parsed)) throw new Error("Root element must be a JSON array '[...]'");
    } catch(e) {
        msg.textContent = "Invalid JSON: " + e.message;
        msg.className = 'error';
        return;
    }

    msg.textContent = 'Importing...';
    msg.className = '';

    const fd = new URLSearchParams();
    fd.append('action', 'import_lines');
    fd.append('shot_id', <?= $shotId ?>);
    fd.append('json_data', val);

    fetch('paradigm_b_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Import successful! Refreshing parent...';
                msg.className = 'success';
                setTimeout(() => {
                    if (window.parent && window.parent.refreshScriptArea) {
                        window.parent.refreshScriptArea(); // Request parent scene reload
                    }
                    if (window.parent && window.parent.closeModal) {
                        window.parent.closeModal(); // Close ourselves (modal_frame_details standard)
                    } else if (window.parent && window.parent.closeVideoModal) {
                        window.parent.closeVideoModal(); // Close ourselves (fallback alias)
                    }
                }, 800);
            } else {
                msg.textContent = 'Server Error: ' + res.message;
                msg.className = 'error';
            }
        })
        .catch(err => {
            msg.textContent = 'Network error during import.';
            msg.className = 'error';
        });
}
</script>
</body>
</html>
