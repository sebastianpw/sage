<?php                                                   require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
?>
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
	margin: 0;
        padding: 0;
    }

    .controls {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        flex-wrap: wrap;
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
        max-width: 600px;
        height: auto;
        border: 1px solid #ccc;
        margin-bottom: 10px;
        display: flex;
        justify-content: center;
        align-items: center;
        background: #f0f0f0;
        padding: 10px;
    }

    #viewer-container img {
        max-width: 100%;
        max-height: 600px;
        border-radius: 4px;
    }

    #license-info {
        padding: 10px;
        border: 1px solid #ccc;
        background: #f9f9f9;
        font-size: 14px;
        max-width: 600px;
    }
</style>
</head>
<body>

<div style="position: relative;">
    <div style="position: absolute;">
        <a href="/dashboard.php" 
           title="Dashboard" 
           style="text-decoration: none; font-size: 24px; display: inline-block; position: absolute; top: 10px; left: 10px; z-index: 999;">
            &#x1F5C3;
        </a>
        <h2 style="margin: 0; padding: 0 0 20px 0; position: absolute; top: 10px; left: 50px;">
            Posemaniacs
        </h2>          
    </div>
</div>

<div style="position: absolute; top: 100px; padding:20px;">

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

<div id="viewer-container">Select a pose to preview</div>

<div style="margin-bottom:10px;">
    <button id="upload-btn">Upload Snapshot to Server</button>
</div>

<div id="license-info"></div>




</div>




<script>
const categorySelect = document.getElementById('category');
const pageInput = document.getElementById('page');
const loadBtn = document.getElementById('load-category');
const resultsDiv = document.getElementById('results');
const viewerContainer = document.getElementById('viewer-container');
const uploadBtn = document.getElementById('upload-btn');
const licenseInfo = document.getElementById('license-info');

let selectedPose = null;

// Load category poses
async function loadCategory() {
    const tag = categorySelect.value;
    const page = parseInt(pageInput.value) || 1;
    resultsDiv.innerHTML = 'Loading...';
    licenseInfo.textContent = '';
    selectedPose = null;
    viewerContainer.innerHTML = 'Select a pose to preview';

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

// Select pose: display thumbnail in viewer
function selectPose(pose, divElem) {
    document.querySelectorAll('.pose-item').forEach(d => d.classList.remove('selected'));
    divElem.classList.add('selected');
    selectedPose = pose;

    // Show the thumbnail image
    viewerContainer.innerHTML = `<img src="${pose.thumb}" alt="${pose.title}">`;

    // License info placeholder
    licenseInfo.textContent = `Pose ID: ${pose.id} — Title: ${pose.title}`;
}

// Upload snapshot to server (uses thumbnail image)
uploadBtn.addEventListener('click', async () => {
    if (!selectedPose) {
        alert('Please select a pose first.');
        return;
    }

    try {
        // Fetch image as base64
        const imgResp = await fetch(selectedPose.thumb);
        const blob = await imgResp.blob();
        const reader = new FileReader();
        reader.onloadend = async () => {
            const base64Data = reader.result;
            const filenameHint = selectedPose.title || 'pose';
            const uploadResp = await fetch('/save_snapshot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image: base64Data, filename: filenameHint })
            });
            const json = await uploadResp.json();
            if (json.success) {
                alert(`Snapshot uploaded successfully: ${json.filename}`);
            } else {
                alert(`Upload failed: ${json.error || 'Unknown error'}`);
            }
        };
        reader.readAsDataURL(blob);
    } catch (err) {
        alert('Upload failed: ' + err.message);
    }
});

loadBtn.addEventListener('click', loadCategory);
loadCategory();
</script>

</body>
</html>
