<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generator Test Client</title>
    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --accent: #3b82f6;
            --success: #10b981;
            --danger: #ef4444;
            --border: #334155;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 24px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { font-size: 28px; margin: 0 0 8px 0; font-weight: 600; }
        .subtitle { color: var(--muted); margin: 0 0 32px 0; }
        .grid { display: grid; grid-template-columns: 400px 1fr; gap: 24px; }
        .card {
            background: var(--card);
            border-radius: 12px;
            padding: 24px;
            border: 1px solid var(--border);
        }
        .card h2 { margin: 0 0 16px 0; font-size: 18px; font-weight: 600; }
        label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--muted);
            margin-bottom: 6px;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 14px;
            margin-bottom: 16px;
            font-family: inherit;
        }
        textarea {
            min-height: 120px;
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 13px;
            resize: vertical;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .params-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        pre {
            background: var(--bg);
            padding: 16px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.6;
            border: 1px solid var(--border);
            margin: 0;
        }
        .response-tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .tab {
            padding: 10px 16px;
            background: transparent;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 2px solid transparent;
        }
        .tab.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 8px;
        }
        .badge.success { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .badge.error { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .stat {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        .stat:last-child { border-bottom: none; }
        .stat-label { color: var(--muted); }
        .stat-value { font-weight: 500; }
        @media (max-width: 1024px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🎯 Generator Test Client</h1>
    <p class="subtitle">Test your JSON-driven generators in real-time</p>

    <div class="grid">
        <div class="card">
            <h2>⚙️ Configuration</h2>
            
            <label>Generator Config ID</label>
            <input type="text" id="configId" placeholder="e.g., 3bb189480922c23f29d90e732a7286d0">

            <label>Model Override (optional)</label>
            <input type="text" id="model" placeholder="Leave empty to use config default">

            <h3 style="margin: 24px 0 12px 0; font-size: 16px;">Parameters</h3>
            <div id="params">
                <div class="params-grid">
                    <div>
                        <label>mode</label>
                        <select id="param_mode">
                            <option value="easy">easy</option>
                            <option value="medium" selected>medium</option>
                            <option value="extreme">extreme</option>
                        </select>
                    </div>
                    <div>
                        <label>language</label>
                        <select id="param_language">
                            <option value="german" selected>german</option>
                            <option value="english">english</option>
                        </select>
                    </div>
                </div>
                
                <label>firstLetter (optional)</label>
                <input type="text" id="param_firstLetter" maxlength="1" placeholder="S">
            </div>

            <h3 style="margin: 24px 0 12px 0; font-size: 16px;">AI Options</h3>
            <div class="params-grid">
                <div>
                    <label>temperature</label>
                    <input type="number" id="temperature" step="0.1" min="0" max="2" value="1">
                </div>
                <div>
                    <label>max_tokens</label>
                    <input type="number" id="maxTokens" min="100" max="4000" value="1000">
                </div>
            </div>

            <button class="btn" onclick="generate()">
                ▶️ Generate
            </button>
        </div>

        <div class="card">
            <h2>📊 Response</h2>
            
            <div id="loading" style="display:none; text-align:center; padding:40px; color:var(--muted)">
                <div style="font-size:24px;margin-bottom:8px">⏳</div>
                Generating...
            </div>

            <div id="result" style="display:none">
                <div class="response-tabs">
                    <button class="tab active" onclick="switchTab('data')">Data</button>
                    <button class="tab" onclick="switchTab('raw')">Raw</button>
                    <button class="tab" onclick="switchTab('decoded')">Decoded</button>
                    <button class="tab" onclick="switchTab('stats')">Stats</button>
                </div>

                <div id="tab-data" class="tab-content active">
                    <pre id="dataOutput"></pre>
                </div>
                <div id="tab-raw" class="tab-content">
                    <pre id="rawOutput"></pre>
                </div>
                <div id="tab-decoded" class="tab-content">
                    <pre id="decodedOutput"></pre>
                </div>
                <div id="tab-stats" class="tab-content">
                    <div id="statsOutput"></div>
                </div>
            </div>

            <div id="error" style="display:none; padding:16px; background:rgba(239,68,68,0.1); border:1px solid var(--danger); border-radius:8px; color:var(--danger)">
            </div>
        </div>
    </div>
</div>

<script>
async function generate() {
    const configId = document.getElementById('configId').value.trim();
    if (!configId) {
        alert('Please enter a Config ID');
        return;
    }

    const params = new URLSearchParams();
    params.set('config_id', configId);

    const model = document.getElementById('model').value.trim();
    if (model) params.set('model', model);

    const mode = document.getElementById('param_mode').value;
    if (mode) params.set('mode', mode);

    const language = document.getElementById('param_language').value;
    if (language) params.set('language', language);

    const firstLetter = document.getElementById('param_firstLetter').value.trim();
    if (firstLetter) params.set('firstLetter', firstLetter);

    const temperature = document.getElementById('temperature').value;
    if (temperature) params.set('temperature', temperature);

    const maxTokens = document.getElementById('maxTokens').value;
    if (maxTokens) params.set('max_tokens', maxTokens);

    document.getElementById('loading').style.display = 'block';
    document.getElementById('result').style.display = 'none';
    document.getElementById('error').style.display = 'none';

    try {
        const response = await fetch(`/api/generate.php?${params.toString()}`);
        const data = await response.json();

        document.getElementById('loading').style.display = 'none';

        if (!data.ok) {
            document.getElementById('error').textContent = data.error || 'Unknown error';
            document.getElementById('error').style.display = 'block';
            return;
        }

        document.getElementById('result').style.display = 'block';
        
        document.getElementById('dataOutput').textContent = JSON.stringify(data.data, null, 2);
        document.getElementById('rawOutput').textContent = data.raw_response;
        document.getElementById('decodedOutput').textContent = JSON.stringify(data.decoded, null, 2);
        
        const statsHtml = `
            <div class="stat">
                <span class="stat-label">Status</span>
                <span class="stat-value">
                    ${data.ok ? '<span class="badge success">Success</span>' : '<span class="badge error">Failed</span>'}
                </span>
            </div>
            <div class="stat">
                <span class="stat-label">Schema Valid</span>
                <span class="stat-value">${data.schema_valid ? '✅ Yes' : '❌ No'}</span>
            </div>
            <div class="stat">
                <span class="stat-label">Model</span>
                <span class="stat-value">${data.model}</span>
            </div>
            <div class="stat">
                <span class="stat-label">Elapsed Time</span>
                <span class="stat-value">${data.elapsed_ms}ms</span>
            </div>
            ${data.warnings && data.warnings.length > 0 ? `
            <div class="stat">
                <span class="stat-label">Warnings</span>
                <span class="stat-value">${data.warnings.length}</span>
            </div>
            ` : ''}
        `;
        document.getElementById('statsOutput').innerHTML = statsHtml;

    } catch (err) {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('error').textContent = 'Network error: ' + err.message;
        document.getElementById('error').style.display = 'block';
    }
}

function switchTab(name) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
}
</script>
</body>
</html>
