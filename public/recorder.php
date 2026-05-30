<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

date_default_timezone_set('UTC');

/**
 * Storyboard Recorder Wrapper
 *
 * - Loads a local HTML view into an iframe
 * - Records using:
 *   1) Native screen/tab capture if available
 *   2) DOM rasterization fallback using html2canvas + canvas.captureStream
 * - Uploads the recording back to this same PHP file
 * - Converts WebM -> MP4 by invoking a shell script with `sh`
 * - Returns a download link for the MP4
 *
 * Fallback note:
 * - Same-origin only.
 * - The fallback renders the iframe's live document body directly, not the wrapper.
 * - A temporary zoom is applied during capture so mobile layouts look less flat.
 */

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function clean_view_name(?string $view): string
{
    $view = trim((string)$view);
    if ($view === '') {
        return 'storyboard_72.html';
    }

    $parts = parse_url($view);
    $path = is_array($parts) ? (string)($parts['path'] ?? '') : $view;
    $query = is_array($parts) ? (string)($parts['query'] ?? '') : '';

    $path = str_replace('\\', '/', $path);
    $path = ltrim($path, '/');
    $path = str_replace('..', '', $path);
    $path = preg_replace('/[^A-Za-z0-9._\/\-]/', '', $path) ?? '';

    if ($path === '') {
        $path = 'storyboard_72.html';
    }

    if ($query !== '') {
        $query = preg_replace('/[^A-Za-z0-9._~\-\[\]\/&=%+,;:@]/', '', $query) ?? '';
    }

    return $query !== '' ? $path . '?' . $query : $path;
}

function view_title_base(string $view): string
{
    $path = parse_url($view, PHP_URL_PATH);
    $path = is_string($path) && $path !== '' ? $path : $view;
    return pathinfo($path, PATHINFO_FILENAME);
}

function work_dir(): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'storyboard_recorder';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function job_path(string $id): string
{
    return work_dir() . DIRECTORY_SEPARATOR . $id . '.json';
}

function safe_unlink(?string $path): void
{
    if ($path && is_file($path)) {
        @unlink($path);
    }
}

function script_path(string $relative): string
{
    $root = \App\Core\SpwBase::getInstance()->getProjectPath();
    return rtrim($root, '/\\') . '/' . ltrim($relative, '/');
}

$action = $_REQUEST['action'] ?? '';

/* --------------------------------------------------------------------------
   AJAX: save upload, convert to MP4 via shell script
---------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_recording') {
    if (!isset($_FILES['recording']) || !is_array($_FILES['recording'])) {
        json_response(['ok' => false, 'error' => 'No recording uploaded.'], 400);
    }

    $view = clean_view_name($_POST['view'] ?? 'storyboard_72.html');
    $titleBase = view_title_base($view);
    $jobId = bin2hex(random_bytes(12));

    $tmpWebm = work_dir() . DIRECTORY_SEPARATOR . $jobId . '.webm';
    $tmpMp4  = work_dir() . DIRECTORY_SEPARATOR . $jobId . '.mp4';
    $metaFile = job_path($jobId);

    $upload = $_FILES['recording'];

    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_response(['ok' => false, 'error' => 'Upload error: ' . (string)($upload['error'] ?? 'unknown')], 400);
    }

    if (!is_uploaded_file($upload['tmp_name'])) {
        json_response(['ok' => false, 'error' => 'Invalid uploaded file.'], 400);
    }

    if (!@move_uploaded_file($upload['tmp_name'], $tmpWebm)) {
        json_response(['ok' => false, 'error' => 'Could not store the uploaded recording.'], 500);
    }

    $convertScript = script_path('bash/recording_to_mp4.sh');

    if (!is_file($convertScript)) {
        safe_unlink($tmpWebm);
        json_response([
            'ok' => false,
            'error' => 'Missing conversion script: bash/recording_to_mp4.sh'
        ], 500);
    }

    $cmd = 'sh ' . escapeshellarg($convertScript) . ' '
         . escapeshellarg($tmpWebm) . ' '
         . escapeshellarg($tmpMp4);

    $output = [];
    $code = 0;
    @exec($cmd . ' 2>&1', $output, $code);

    if ($code !== 0 || !is_file($tmpMp4) || filesize($tmpMp4) < 1000) {
        safe_unlink($tmpWebm);
        safe_unlink($tmpMp4);
        json_response([
            'ok' => false,
            'error' => 'MP4 conversion failed.',
            'details' => implode("\n", $output),
        ], 500);
    }

    file_put_contents($metaFile, json_encode([
        'id'         => $jobId,
        'view'       => $view,
        'titleBase'  => $titleBase,
        'webm'       => $tmpWebm,
        'mp4'        => $tmpMp4,
        'created_at' => time(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    json_response([
        'ok' => true,
        'download_url' => strtok($_SERVER['REQUEST_URI'], '?') . '?action=download&id=' . rawurlencode($jobId),
        'filename' => $titleBase . '_' . date('Ymd_His') . '.mp4',
    ]);
}

/* --------------------------------------------------------------------------
   Download MP4
---------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'download') {
    $id = preg_replace('/[^a-f0-9]/i', '', (string)($_GET['id'] ?? ''));
    if ($id === '') {
        http_response_code(400);
        echo 'Missing job id.';
        exit;
    }

    $metaFile = job_path($id);
    if (!is_file($metaFile)) {
        http_response_code(404);
        echo 'Recording job not found.';
        exit;
    }

    $meta = json_decode((string)file_get_contents($metaFile), true);
    if (!is_array($meta) || empty($meta['mp4']) || !is_file((string)$meta['mp4'])) {
        http_response_code(404);
        echo 'MP4 file not found.';
        exit;
    }

    $view = clean_view_name((string)($meta['view'] ?? 'storyboard_72.html'));
    $titleBase = view_title_base($view);
    $filename = $titleBase . '_' . date('Ymd_His', (int)($meta['created_at'] ?? time())) . '.mp4';

    header('Content-Type: video/mp4');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '_', $filename) . '"');
    header('Content-Length: ' . filesize((string)$meta['mp4']));
    header('X-Content-Type-Options: nosniff');

    readfile((string)$meta['mp4']);
    exit;
}

$view = clean_view_name($_GET['view'] ?? 'storyboard_72.html');
$scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$iframeSrc = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080') . '/' . ltrim($view, '/');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Storyboard Recorder</title>

    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

    <style>
        :root {
            --panel-bg: rgba(18, 18, 20, 0.88);
            --panel-border: rgba(255,255,255,0.14);
            --text: #f1f1f1;
            --muted: #b6b6b6;
            --accent: #46d17b;
            --danger: #ff5a67;
            --warning: #ffca57;
            --shadow: 0 10px 35px rgba(0,0,0,.35);
            --radius: 14px;
        }

        html, body {
            margin: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #111;
            color: var(--text);
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        }

        #stage {
            position: fixed;
            inset: 0;
            background: #111;
        }

        #storyFrame {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
            background: #fff;
        }

        #recPanel {
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 99999;
            width: 380px;
            max-width: calc(100vw - 32px);
            background: var(--panel-bg);
            color: var(--text);
            border: 1px solid var(--panel-border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
            user-select: none;
        }

        #recHeader {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            cursor: move;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        #recHeaderTitle {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }

        #recHeaderTitle strong {
            font-size: 14px;
            font-weight: 700;
        }

        #recHeaderTitle span {
            font-size: 12px;
            color: var(--muted);
        }

        #toggleBtn {
            appearance: none;
            border: 0;
            background: transparent;
            color: var(--text);
            font-size: 18px;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 8px;
        }

        #toggleBtn:hover {
            background: rgba(255,255,255,0.08);
        }

        #recBody {
            padding: 12px;
            display: grid;
            gap: 10px;
        }

        #recPanel.collapsed #recBody {
            display: none;
        }

        #recPanel.collapsed {
            width: 260px;
        }

        .fieldRow,
        .sliderRow {
            display: grid;
            gap: 6px;
        }

        .fieldRow label,
        .sliderRow label {
            font-size: 12px;
            color: var(--muted);
        }

        .sliderRow label {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .fieldRow input,
        .sliderRow input[type="range"] {
            width: 100%;
            box-sizing: border-box;
        }

        .fieldRow input {
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.06);
            color: var(--text);
            padding: 10px 11px;
            border-radius: 10px;
            outline: none;
            font-size: 14px;
        }

        .fieldRow input:focus {
            border-color: rgba(70, 209, 123, 0.7);
            box-shadow: 0 0 0 3px rgba(70, 209, 123, 0.15);
        }

        .buttonRow {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            appearance: none;
            border: 0;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            color: #0e0e10;
            background: #e8e8e8;
        }

        .btn:hover { filter: brightness(1.05); }
        .btn:disabled { opacity: .45; cursor: not-allowed; }

        .btn.primary { background: var(--accent); }
        .btn.danger { background: var(--danger); color: white; }
        .btn.warn { background: var(--warning); }

        #statusLine {
            font-size: 12px;
            color: var(--muted);
            min-height: 1.2em;
            white-space: pre-wrap;
        }

        #statusLine.ok { color: var(--accent); }
        #statusLine.err { color: var(--danger); }
        #statusLine.warn { color: var(--warning); }

        #miniBadge {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 99999;
            display: none;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: rgba(18,18,20,0.88);
            color: var(--text);
            border: 1px solid var(--panel-border);
            border-radius: 999px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
            font-size: 12px;
        }

        #miniDot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: var(--danger);
            box-shadow: 0 0 0 6px rgba(255,90,103,0.15);
        }

        #miniStop {
            appearance: none;
            border: 0;
            border-radius: 999px;
            padding: 6px 10px;
            cursor: pointer;
            font-weight: 700;
            background: var(--danger);
            color: white;
        }

        #miniTimer {
            font-variant-numeric: tabular-nums;
            color: var(--muted);
        }

        .hint {
            font-size: 11px;
            color: var(--muted);
            line-height: 1.35;
        }

        #hiddenDownload {
            display: none;
        }

        #fallbackCanvas {
            position: fixed;
            left: -99999px;
            top: -99999px;
            width: 1px;
            height: 1px;
            opacity: 0;
            pointer-events: none;
        }
    </style>
</head>
<body>
<div id="stage">
    <iframe
        id="storyFrame"
        src="<?= htmlspecialchars($iframeSrc, ENT_QUOTES, 'UTF-8') ?>"
        allow="autoplay; fullscreen; clipboard-read; clipboard-write"
    ></iframe>
</div>

<div id="recPanel" class="collapsed">
    <div id="recHeader" title="Drag to move. Tap to collapse/expand.">
        <div id="recHeaderTitle">
            <strong>Storyboard Recorder</strong>
            <span id="recHeaderSub">view: <?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <button id="toggleBtn" type="button" aria-label="Expand or collapse">▾</button>
    </div>

    <div id="recBody">
        <div class="fieldRow">
            <label for="viewName">View file</label>
            <input id="viewName" type="text" value="<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?>" placeholder="storyboard_72.html">
        </div>

        <div class="sliderRow">
            <label for="captureZoom">
                <span>Capture zoom</span>
                <span id="captureZoomValue">1.18×</span>
            </label>
            <input id="captureZoom" type="range" min="1" max="1.6" step="0.01" value="1.18">
        </div>

        <div class="sliderRow">
            <label for="captureScale">
                <span>Capture scale</span>
                <span id="captureScaleValue">2.00×</span>
            </label>
            <input id="captureScale" type="range" min="1" max="3" step="0.05" value="2">
        </div>

        <div class="buttonRow">
            <button id="loadBtn" class="btn warn" type="button">Load view</button>
            <button id="recBtn" class="btn primary" type="button">Rec</button>
            <button id="stopBtn" class="btn danger" type="button" disabled>Stop</button>
        </div>

        <div id="statusLine">Ready.</div>

        <div class="hint">
            Native capture is used when available. On Android when it is missing, the fallback snapshots the live iframe body directly.
        </div>
    </div>
</div>

<div id="miniBadge">
    <div id="miniDot"></div>
    <div>REC <span id="miniTimer">00:00</span></div>
    <button id="miniStop" type="button">Stop</button>
</div>

<canvas id="fallbackCanvas"></canvas>
<a id="hiddenDownload" href="#" download></a>

<script>
(() => {
    const iframe = document.getElementById('storyFrame');
    const recPanel = document.getElementById('recPanel');
    const recHeader = document.getElementById('recHeader');
    const toggleBtn = document.getElementById('toggleBtn');
    const viewName = document.getElementById('viewName');
    const captureZoom = document.getElementById('captureZoom');
    const captureScale = document.getElementById('captureScale');
    const captureZoomValue = document.getElementById('captureZoomValue');
    const captureScaleValue = document.getElementById('captureScaleValue');
    const loadBtn = document.getElementById('loadBtn');
    const recBtn = document.getElementById('recBtn');
    const stopBtn = document.getElementById('stopBtn');
    const statusLine = document.getElementById('statusLine');
    const miniBadge = document.getElementById('miniBadge');
    const miniStop = document.getElementById('miniStop');
    const miniTimer = document.getElementById('miniTimer');
    const hiddenDownload = document.getElementById('hiddenDownload');
    const headerSub = document.getElementById('recHeaderSub');
    const fallbackCanvas = document.getElementById('fallbackCanvas');

    let captureStream = null;
    let mediaRecorder = null;
    let recordChunks = [];
    let recorderTimer = null;
    let startAt = 0;

    let dragMode = false;
    let dragMoved = false;
    let dragX = 0;
    let dragY = 0;
    let panelX = 16;
    let panelY = 16;

    let renderBusy = false;
    let renderTimer = null;
    const fallbackCtx = fallbackCanvas.getContext('2d', { alpha: false });

    function sanitizeView(v) {
        v = String(v || '').trim();
        if (!v) return 'storyboard_72.html';

        const hashIndex = v.indexOf('#');
        if (hashIndex !== -1) {
            v = v.slice(0, hashIndex);
        }

        let query = '';
        const queryIndex = v.indexOf('?');
        if (queryIndex !== -1) {
            query = v.slice(queryIndex + 1);
            v = v.slice(0, queryIndex);
        }

        v = v.replace(/\\/g, '/').replace(/^\//, '').replace(/\.\./g, '');
        v = v.replace(/[^A-Za-z0-9._\\/-]/g, '');
        if (!v) v = 'storyboard_72.html';

        if (query) {
            query = query.replace(/[^A-Za-z0-9._~\-\[\]\/&=%+,;:@]/g, '');
            if (query) {
                v += '?' + query;
            }
        }

        return v;
    }

    function setStatus(msg, cls = '') {
        statusLine.className = cls;
        statusLine.textContent = msg;
    }

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function formatElapsed(ms) {
        const total = Math.max(0, Math.floor(ms / 1000));
        const m = Math.floor(total / 60);
        const s = total % 60;
        return `${pad(m)}:${pad(s)}`;
    }

    function updateTimer() {
        if (!startAt) return;
        miniTimer.textContent = formatElapsed(Date.now() - startAt);
    }

    function stopTimer() {
        if (recorderTimer) {
            clearInterval(recorderTimer);
            recorderTimer = null;
        }
        startAt = 0;
        miniTimer.textContent = '00:00';
    }

    function applyPanelPos() {
        recPanel.style.left = panelX + 'px';
        recPanel.style.top = panelY + 'px';
    }

    function clampPanel() {
        const rect = recPanel.getBoundingClientRect();
        panelX = Math.max(8, Math.min(panelX, window.innerWidth - rect.width - 8));
        panelY = Math.max(8, Math.min(panelY, window.innerHeight - rect.height - 8));
        applyPanelPos();
    }

    function toggleCollapsed(force) {
        const collapsed = typeof force === 'boolean' ? force : !recPanel.classList.contains('collapsed');
        recPanel.classList.toggle('collapsed', collapsed);
        toggleBtn.textContent = collapsed ? '▾' : '▴';
        clampPanel();
    }

    function updateZoomLabels() {
        captureZoomValue.textContent = parseFloat(captureZoom.value).toFixed(2) + '×';
        captureScaleValue.textContent = parseFloat(captureScale.value).toFixed(2) + '×';
    }

    function loadView() {
        const clean = sanitizeView(viewName.value);
        viewName.value = clean;
        headerSub.textContent = 'view: ' + clean;
        iframe.src = `${location.origin}/${clean}`;
        setStatus(`Loaded ${iframe.src}`, 'ok');
    }

    function pickRecorderMimeType() {
        const candidates = [
            'video/webm;codecs=vp9,opus',
            'video/webm;codecs=vp8,opus',
            'video/webm'
        ];

        for (const t of candidates) {
            if (window.MediaRecorder && MediaRecorder.isTypeSupported(t)) {
                return t;
            }
        }
        return '';
    }

    function cleanupAfterStop() {
        stopTimer();
        miniBadge.style.display = 'none';
        recBtn.disabled = false;
        stopBtn.disabled = true;

        if (renderTimer) {
            clearInterval(renderTimer);
            renderTimer = null;
        }

        if (captureStream) {
            try {
                captureStream.getTracks().forEach(t => t.stop());
            } catch (_) {}
            captureStream = null;
        }

        mediaRecorder = null;
    }

    async function uploadRecording(blob) {
        setStatus('Uploading recording for MP4 conversion...', 'warn');

        const fd = new FormData();
        fd.append('action', 'save_recording');
        fd.append('view', sanitizeView(viewName.value));
        fd.append('recording', blob, 'storyboard_recording.webm');

        const resp = await fetch(location.pathname + location.search, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        });

        const data = await resp.json();

        if (!resp.ok || !data.ok) {
            throw new Error(data.error || 'Unknown server error');
        }

        setStatus('MP4 ready. Starting download...', 'ok');
        hiddenDownload.href = data.download_url;
        hiddenDownload.download = data.filename || '';
        hiddenDownload.click();
    }

    function startMediaRecorder(stream) {
        const mimeType = pickRecorderMimeType();
        recordChunks = [];

        mediaRecorder = mimeType
            ? new MediaRecorder(stream, { mimeType })
            : new MediaRecorder(stream);

        mediaRecorder.ondataavailable = (ev) => {
            if (ev.data && ev.data.size > 0) {
                recordChunks.push(ev.data);
            }
        };

        mediaRecorder.onerror = (ev) => {
            setStatus('Recorder error: ' + (ev.error?.message || ev.message || 'unknown'), 'err');
        };

        mediaRecorder.onstop = async () => {
            try {
                const blob = new Blob(recordChunks, { type: 'video/webm' });
                await uploadRecording(blob);
            } catch (err) {
                setStatus('Upload/convert failed: ' + err.message, 'err');
            } finally {
                cleanupAfterStop();
            }
        };

        mediaRecorder.start(1000);
        startAt = Date.now();
        recorderTimer = setInterval(updateTimer, 250);
        recBtn.disabled = true;
        stopBtn.disabled = false;
        miniBadge.style.display = 'flex';
    }

    async function startNativeScreenCapture() {
        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getDisplayMedia !== 'function') {
            throw new Error('navigator.mediaDevices.getDisplayMedia is not available in this browser.');
        }

        captureStream = await navigator.mediaDevices.getDisplayMedia({
            video: { frameRate: 30, cursor: 'always' },
            audio: true
        });

        captureStream.getVideoTracks().forEach(track => {
            track.onended = () => {
                if (mediaRecorder && mediaRecorder.state === 'recording') {
                    mediaRecorder.stop();
                }
            };
        });

        startMediaRecorder(captureStream);
        setStatus('Native capture started.', 'ok');
    }

    function ensureFallbackCanvasSize(scale) {
        const rect = iframe.getBoundingClientRect();
        const w = Math.max(2, Math.round(rect.width * scale));
        const h = Math.max(2, Math.round(rect.height * scale));

        if (fallbackCanvas.width !== w || fallbackCanvas.height !== h) {
            fallbackCanvas.width = w;
            fallbackCanvas.height = h;
        }

        return { width: w, height: h };
    }

    async function renderIframeBodyToFallbackCanvas() {
        if (renderBusy) return;
        renderBusy = true;

        try {
            const doc = iframe.contentDocument;
            const win = iframe.contentWindow;

            if (!doc || !win || !doc.body) {
                throw new Error('Iframe document is not accessible yet.');
            }

            if (typeof window.html2canvas !== 'function') {
                throw new Error('html2canvas failed to load.');
            }

            const scale = parseFloat(captureScale.value || '2');
            const zoom = parseFloat(captureZoom.value || '1.18');
            const { width, height } = ensureFallbackCanvasSize(scale);

            const body = doc.body;
            const html = doc.documentElement;

            const prevBodyZoom = body.style.zoom;
            const prevBodyWidth = body.style.width;
            const prevBodyMinHeight = body.style.minHeight;
            const prevBodyOverflow = body.style.overflow;
            const prevHtmlOverflow = html.style.overflow;

            // Temporary zoom so the resulting capture is less flat and more mobile-like.
            body.style.zoom = String(zoom);
            body.style.width = (100 / zoom) + '%';
            body.style.minHeight = '100vh';
            body.style.overflow = 'hidden';
            html.style.overflow = 'hidden';

            // Let layout settle before snapshotting.
            await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));

            const snapshot = await html2canvas(body, {
                backgroundColor: '#111111',
                scale: scale,
                useCORS: true,
                allowTaint: false,
                logging: false,
                windowWidth: Math.max(1, Math.round(win.innerWidth || doc.documentElement.clientWidth || iframe.clientWidth || width)),
                windowHeight: Math.max(1, Math.round(win.innerHeight || doc.documentElement.clientHeight || iframe.clientHeight || height)),
                scrollX: 0,
                scrollY: 0,
                x: 0,
                y: 0,
                width: Math.max(1, Math.round(win.innerWidth || doc.documentElement.clientWidth || iframe.clientWidth || width)),
                height: Math.max(1, Math.round(win.innerHeight || doc.documentElement.clientHeight || iframe.clientHeight || height)),
                foreignObjectRendering: false
            });

            fallbackCtx.clearRect(0, 0, width, height);
            fallbackCtx.drawImage(snapshot, 0, 0, width, height);

            body.style.zoom = prevBodyZoom;
            body.style.width = prevBodyWidth;
            body.style.minHeight = prevBodyMinHeight;
            body.style.overflow = prevBodyOverflow;
            html.style.overflow = prevHtmlOverflow;
        } finally {
            renderBusy = false;
        }
    }

    async function startDomFallbackCapture() {
        if (typeof fallbackCanvas.captureStream !== 'function') {
            throw new Error('canvas.captureStream() is not available in this browser.');
        }

        await renderIframeBodyToFallbackCanvas();
        captureStream = fallbackCanvas.captureStream(15);

        renderTimer = setInterval(() => {
            renderIframeBodyToFallbackCanvas().catch(err => {
                setStatus('DOM fallback failed: ' + err.message, 'err');
                if (mediaRecorder && mediaRecorder.state === 'recording') {
                    mediaRecorder.stop();
                }
            });
        }, 1000 / 12);

        startMediaRecorder(captureStream);
        setStatus('DOM fallback recording started.', 'ok');
    }

    async function startRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            return;
        }

        recordChunks = [];

        try {
            if (navigator.mediaDevices && typeof navigator.mediaDevices.getDisplayMedia === 'function') {
                await startNativeScreenCapture();
            } else {
                await startDomFallbackCapture();
            }
        } catch (err) {
            try {
                setStatus('Native capture unavailable, trying DOM fallback...', 'warn');
                await startDomFallbackCapture();
            } catch (fallbackErr) {
                setStatus(
                    'Recording failed: ' + err.message + ' | Fallback failed: ' + fallbackErr.message,
                    'err'
                );
                cleanupAfterStop();
            }
        }
    }

    function stopRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
        } else {
            setStatus('Nothing is currently recording.', 'warn');
        }
    }

    // Dragging / collapse
    recHeader.addEventListener('pointerdown', (e) => {
        if (e.target === toggleBtn) return;
        dragMode = true;
        dragMoved = false;
        const rect = recPanel.getBoundingClientRect();
        dragX = e.clientX - rect.left;
        dragY = e.clientY - rect.top;
        recHeader.setPointerCapture(e.pointerId);
    });

    recHeader.addEventListener('pointermove', (e) => {
        if (!dragMode) return;
        dragMoved = true;
        panelX = Math.max(8, Math.min(e.clientX - dragX, window.innerWidth - recPanel.offsetWidth - 8));
        panelY = Math.max(8, Math.min(e.clientY - dragY, window.innerHeight - recPanel.offsetHeight - 8));
        applyPanelPos();
    });

    recHeader.addEventListener('pointerup', () => {
        if (!dragMode) return;
        dragMode = false;
        if (!dragMoved) {
            toggleCollapsed();
        }
    });

    recHeader.addEventListener('pointercancel', () => {
        dragMode = false;
    });

    toggleBtn.addEventListener('click', () => toggleCollapsed());
    loadBtn.addEventListener('click', loadView);
    recBtn.addEventListener('click', startRecording);
    stopBtn.addEventListener('click', stopRecording);
    miniStop.addEventListener('click', stopRecording);

    viewName.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            loadView();
        }
    });

    captureZoom.addEventListener('input', updateZoomLabels);
    captureScale.addEventListener('input', updateZoomLabels);

    window.addEventListener('resize', clampPanel);

    updateZoomLabels();
    applyPanelPos();
    toggleCollapsed(true);
    loadView();
})();
</script>
</body>
</html>