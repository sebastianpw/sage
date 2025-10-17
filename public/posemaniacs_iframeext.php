<?php require_once __DIR__ . '/bootstrap.php'; require __DIR__ . '/env_locals.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Posemaniacs Viewer</title>
<link rel="stylesheet" href="css/form.css">
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
    }

    .controls {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }

    select, input[type=number] {
        padding: 5px 8px;
        font-size: 14px;
    }

    button {
        padding: 6px 12px;
        font-size: 14px;
        cursor: pointer;
        background-color: #007bff;
        color: white;
        border: 1px solid #0056b3;
        border-radius: 4px;
        transition: background-color 0.2s;
    }

    button:hover {
        background-color: #0056b3;
    }

    #results {
        border: 1px solid #ccc;
        max-height: 300px;
        overflow-y: auto;
        padding: 5px;
        margin-bottom: 15px;
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }

    .pose-item {
        width: 80px;
        text-align: center;
        cursor: pointer;
    }

    .pose-item img {
        width: 100%;
        border: 2px solid transparent;
        border-radius: 4px;
        transition: border 0.2s;
    }

    .pose-item.selected img {
        border-color: #007bff;
    }

    #viewer-container {
        width: 100%;
        height: 600px;
        border: 1px solid #ccc;
        margin-bottom: 10px;
        position: relative;
    }

    #license-info {
        padding: 10px;
        border: 1px solid #ccc;
        background: #f9f9f9;
        font-size: 14px;
    }
</style>
</head>
<body>

<div class="controls">
    <label for="category">Category:</label>
    <select id="category">
        <option value="standing">Standing</option>
        <option value="lying">Lying</option>
        <option value="sitting">Sitting</option>
        <option value="fighting">Fighting</option>
        <option value="sexy">Sexy</option>
        <option value="squatting">Squatting</option>
        <option value="walking">Walking</option>
        <option value="running">Running</option>
        <option value="jumping">Jumping</option>
        <option value="kicking">Kicking</option>
        <option value="punching">Punching</option>
        <option value="leaning">Leaning</option>
        <option value="relax">Relax</option>
        <option value="cool">Cool</option>
        <option value="sad">Sad</option>
        <option value="throwing">Throwing</option>
        <option value="yoga">Yoga</option>
    </select>

    <label for="page">Page:</label>
    <input type="number" id="page" min="1" value="1">

    <button id="load-category">Load</button>
</div>

<div id="results"></div>

<div id="viewer-container"></div>

<div style="margin-bottom:10px;">
    <button id="upload-btn">Upload Snapshot to Server</button>
</div>

<div id="license-info"></div>

<script>
const categorySelect = document.getElementById('category');
const pageInput = document.getElementById('page');
const loadBtn = document.getElementById('load-category');
const resultsDiv = document.getElementById('results');
const viewerContainer = document.getElementById('viewer-container');
const uploadBtn = document.getElementById('upload-btn');
const licenseInfo = document.getElementById('license-info');

let selectedPose = null;
let viewerInstance = null;

// Load category function
async function loadCategory() {
    const tag = categorySelect.value;
    const page = parseInt(pageInput.value) || 1;
    resultsDiv.innerHTML = 'Loading...';
    licenseInfo.textContent = '';
    selectedPose = null;

    try {
        const resp = await fetch(`/posemaniacs_search.php?tag=${encodeURIComponent(tag)}&page=${page}`);
        const data = await resp.json();
        resultsDiv.innerHTML = '';
        if (!data.success) throw new Error(data.error || 'Failed to fetch');

        data.results.forEach(pose => {
            const div = document.createElement('div');
            div.className = 'pose-item';
            div.innerHTML = `<img src="${pose.thumb}" title="${pose.title}">`;
            div.addEventListener('click', () => selectPose(pose, div));
            resultsDiv.appendChild(div);
        });

    } catch (err) {
        resultsDiv.innerHTML = 'Error: ' + err.message;
    }
}

// Select a pose
function selectPose(pose, divElem) {
    document.querySelectorAll('.pose-item').forEach(d => d.classList.remove('selected'));
    divElem.classList.add('selected');
    selectedPose = pose;
    renderPose(pose);
}

// Render pose (here you would instantiate your 3D viewer / iframe)
function renderPose(pose) {
    viewerContainer.innerHTML = `<iframe src="${pose.link}" style="width:100%;height:100%;border:none;"></iframe>`;
    // Example license info (adjust if Posemaniacs exposes)
    licenseInfo.textContent = `Pose ID: ${pose.id} — Title: ${pose.title}`;
}

// Upload snapshot placeholder
uploadBtn.addEventListener('click', async () => {
    if (!selectedPose) {
        alert('Please select a pose first.');
        return;
    }
    // For now, just alert (replace with snapshot logic)
    alert('Uploading snapshot for pose: ' + selectedPose.title);
});

loadBtn.addEventListener('click', loadCategory);

// Initial load
loadCategory();
</script>

</body>
</html>
