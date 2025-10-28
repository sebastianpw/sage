<?php require_once __DIR__ . '/bootstrap.php'; require __DIR__ . '/env_locals.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Sketchfab</title>
<link rel="stylesheet" href="css/form.css">
<style>
    .file-list { list-style: none; padding: 0; }
    .file-list li { margin-bottom: 0.3em; }
    .result.success { color: #1a7f37; font-weight: 600; }
    .result.error { color: #b42318; font-weight: 600; }
    #sketchfab-container { width: 640px; height: 480px; border: 1px solid #ccc; margin-top: 20px; }
    #controls { margin-top: 10px; }
    #results { margin-top: 20px; max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; width: 300px; }
    .model-item { cursor: pointer; padding: 5px; border-bottom: 1px solid #eee; }
    .model-item:hover { background: #f0f0f0; }
    button { margin-right: 8px; }
</style>
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- Sketchfab Viewer API (via CDN) -->
    <script src="https://static.sketchfab.com/api/sketchfab-viewer-1.12.1.js"></script>
<?php else: ?>
    <!-- Sketchfab Viewer API (via local copy) -->
    <script src="/vendor/sketchfab/sketchfab-viewer-1.12.1.js"></script>
<?php endif; ?>
<?php echo $eruda; ?>
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
            Sketchfab
        </h2>          
    </div>
</div>

<div style="position: absolute; top: 100px;">
    <!-- Search -->
    <input type="text" id="search-query" placeholder="Search models...">
    <button id="btn-search">Search</button>

    <!-- Results -->
    <div id="results"></div>
    <div>
	<button id="prev-page">Prev</button>
        <span id="page-info" style="display:none;"></span>
        <button id="next-page">Next</button>
    </div>




<!-- === add this HTML where your viewer is (replace the old viewer area) === -->
<!-- Viewer -->
<div id="sketchfab-container"></div>

<!-- Model info box (new) -->
<div id="model-info" aria-live="polite" style="width:640px; margin-top:8px; padding:8px; border:1px solid #ddd; background:#fafafa; font-size:14px;">
  <strong>Model info:</strong>
  <div id="model-info-content" style="margin-top:6px; color:#333;">
    <div><em>No model loaded</em></div>
  </div>
</div>

<!-- Controls: single unified upload button -->
<div id="controls">
    <button id="btn-upload">Upload Snapshot</button>
</div>



</div>

<script>
/* State */
let api = null;
let currentModelUid = null; // used for filename hint
let currentModelName = null;
let currentPage = 1;
const perPage = 20;
let lastQuery = "";

/* Search */
async function searchModels(query, page = 1) {
  const offset = (page - 1) * perPage;
  const url = `https://api.sketchfab.com/v3/search?type=models&q=${encodeURIComponent(query)}&downloadable=true&count=${perPage}&offset=${offset}`;
  const res = await fetch(url);
  if (!res.ok) throw new Error('Search request failed: ' + res.status);
  return await res.json();
}

/*
async function renderResults(query, page = 1) {
  lastQuery = query;
  currentPage = page;
  try {
    const data = await searchModels(query, page);
    const resultsDiv = document.getElementById('results');
    resultsDiv.innerHTML = "";
    data.results.forEach(m => {
      const div = document.createElement('div');
      div.className = "model-item";
      div.textContent = m.name + " (by " + m.user.displayName + ")";
      div.onclick = () => loadSketchfab(m.uid, m.name);
      resultsDiv.appendChild(div);
    });
    document.getElementById('page-info').textContent = `Page ${page}`;
  } catch (err) {
    console.error('Search error', err);
    alert('Search failed: ' + err.message);
  }
}
 */

/* STATE (keep your existing globals) */

/* --- Fetch model metadata from Sketchfab Data API and render into #model-info --- */
async function fetchModelInfo(uid) {
  const infoDiv = document.getElementById('model-info-content');
  infoDiv.innerHTML = '<div>Loading model metadata…</div>';

  try {
    const url = `https://api.sketchfab.com/v3/models/${encodeURIComponent(uid)}`;
    const res = await fetch(url);
    if (!res.ok) {
      throw new Error('Metadata request failed: ' + res.status);
    }
    const json = await res.json();

    // Safely extract author and license info (be defensive for different response shapes)
    const user = json.user || {};
    const authorName = user.displayName || user.username || 'Unknown author';
    const authorUsername = user.username || null;
    const authorProfile = authorUsername ? `https://sketchfab.com/${encodeURIComponent(authorUsername)}` : (user.url || '#');

    // License: try a few likely places
    let licenseName = 'Unknown license';
    let licenseUrl = null;
    if (json.license && typeof json.license === 'object') {
      licenseName = json.license.name || json.license.id || licenseName;
      licenseUrl = json.license.url || json.license_url || null;
    } else if (json.licenseName) {
      licenseName = json.licenseName;
      licenseUrl = json.license_url || null;
    } else if (json.license) {
      licenseName = json.license;
    }

    // Model page link
    const modelPage = `https://sketchfab.com/models/${encodeURIComponent(uid)}`;

    // Build markup
    const markup = `
      <div><strong>Model:</strong> <a href="${modelPage}" target="_blank" rel="noopener">${escapeHtml(json.name || uid)}</a></div>
      <div><strong>Author:</strong> ${authorProfile && authorProfile !== '#' ? `<a href="${authorProfile}" target="_blank" rel="noopener">${escapeHtml(authorName)}</a>` : escapeHtml(authorName)}</div>
      <div><strong>License:</strong> ${licenseUrl ? `<a href="${escapeHtml(licenseUrl)}" target="_blank" rel="noopener">${escapeHtml(licenseName)}</a>` : escapeHtml(licenseName)}</div>
      ${json.isDownloadable ? `<div style="margin-top:6px;color:#1a7f37;"><strong>Downloadable:</strong> yes</div>` : ''}
    `;
    infoDiv.innerHTML = markup;

  } catch (err) {
    console.error('fetchModelInfo error', err);
    infoDiv.innerHTML = `<div style="color:#b42318;">Failed to load model info: ${escapeHtml(err.message || String(err))}</div>`;
  }
}

/* escape helper to avoid HTML injection */
function escapeHtml(str) {
  if (!str && str !== 0) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/* --- Updated loadSketchfab: call fetchModelInfo after viewerready --- */
function loadSketchfab(uid, name = '') {
  const container = document.getElementById('sketchfab-container');
  container.innerHTML = "";

  // reset model info quickly
  document.getElementById('model-info-content').innerHTML = '<div>Loading model...</div>';

  const iframe = document.createElement('iframe');
  iframe.width = "640";
  iframe.height = "480";
  iframe.frameBorder = "0";
  iframe.allow = "autoplay; fullscreen; vr";
  container.appendChild(iframe);

  const client = new Sketchfab(iframe);

  client.init(uid, {
    success: function(_api) {
      api = _api;
      currentModelUid = uid;
      currentModelName = name || '';
      api.start();
      api.addEventListener('viewerready', function() {
        console.log("Viewer ready for", uid);

        // fetch and render metadata once viewer is ready
        fetchModelInfo(uid).catch(e => {
          console.error('fetchModelInfo failed', e);
        });
      });
    },
    error: function() {
      console.error("Sketchfab API error");
      alert("Failed to initialize Sketchfab viewer");
      // clear model info on failure
      document.getElementById('model-info-content').innerHTML = '<div style="color:#b42318;">Viewer failed to initialize</div>';
    }
  });
}




/* Load Sketchfab into viewer and keep reference to api + uid *
function loadSketchfab(uid, name = '') {
  const container = document.getElementById('sketchfab-container');
  container.innerHTML = "";

  const iframe = document.createElement('iframe');
  iframe.width = "640";
  iframe.height = "480";
  iframe.frameBorder = "0";
  iframe.allow = "autoplay; fullscreen; vr";
  container.appendChild(iframe);

  const client = new Sketchfab(iframe);

  client.init(uid, {
    success: function(_api) {
      api = _api;
      currentModelUid = uid;
      currentModelName = name || '';
      api.start();
      api.addEventListener('viewerready', function() {
        console.log("Viewer ready for", uid);
      });
    },
    error: function() {
      console.error("Sketchfab API error");
      alert("Failed to initialize Sketchfab viewer");
    }
  });
}
 */

/* Unified upload button:
   - takes screenshot via api.getScreenShot
   - sends JSON { image: dataURI, filename: hint } to /save_snapshot.php
*/
document.getElementById('btn-upload').addEventListener('click', async function () {
  if (!api) { alert('Load a model first (search -> click result)'); return; }

  // disable button to prevent duplicate clicks
  const btn = this;
  btn.disabled = true;
  const originalText = btn.textContent;
  btn.textContent = 'Taking snapshot...';

  try {
    // take screenshot (you can change dimensions or mime if desired)
    const dataUri = await new Promise((resolve, reject) => {
      // request a reasonably sized image (use png or jpeg)
      api.getScreenShot(1280, 720, 'image/png', function(err, result) {
        if (err) return reject(err);
        resolve(result);
      });
    });

    btn.textContent = 'Uploading...';

    // build filename hint: modelUid + sanitized model name (short)
    let hint = currentModelUid || 'snapshot';
    if (currentModelName) {
      // sanitize: keep alnum, dash, underscore
      const sanitized = currentModelName.replace(/[^a-zA-Z0-9_\-]/g, '_').slice(0, 30);
      hint = `${hint}-${sanitized}`;
    }

    // send JSON to your PHP endpoint
    const res = await fetch('/save_snapshot.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        image: dataUri,
        filename: hint
      })
    });

    const json = await res.json();
    if (res.ok && json.success) {
      alert('Upload success!\nSaved: ' + json.filename + '\nURL: ' + json.url);
    } else {
      console.error('Server error response', json);
      alert('Upload failed: ' + (json.error || res.statusText || 'unknown error'));
    }

  } catch (err) {
    console.error('Snapshot/upload error', err);
    alert('Snapshot/upload failed: ' + (err.message || err));
  } finally {
    btn.disabled = false;
    btn.textContent = originalText;
  }
});
/*
/* UI hooks for search + pagination 
document.getElementById('btn-search').onclick = () => {
  const q = document.getElementById('search-query').value.trim();
  if (!q) { alert('Enter a search term'); return; }
  renderResults(q, 1);
};
document.getElementById('prev-page').onclick = () => {
  if (currentPage > 1) renderResults(lastQuery, currentPage - 1);
};
document.getElementById('next-page').onclick = () => {
  renderResults(lastQuery, currentPage + 1);
};
*/



/* --- Paste/replace the old search + pagination code with this block --- */


let lastSearchData = null; // stores last returned JSON (contains next/previous)
let lastSearchUrl = null;

function buildSearchUrl(query, page = 1) {
  const offset = (page - 1) * perPage;
  return `https://api.sketchfab.com/v3/search?type=models&q=${encodeURIComponent(query)}&downloadable=true&count=${perPage}&offset=${offset}`;
}

async function fetchSearchUrl(url) {
  const res = await fetch(url);
  if (!res.ok) throw new Error('Search request failed: ' + res.status);
  const data = await res.json();
  lastSearchUrl = url;
  return data;
}

async function renderFromUrl(url) {
  try {
    const data = await fetchSearchUrl(url);
    lastSearchData = data;

    const resultsDiv = document.getElementById('results');
    resultsDiv.innerHTML = "";

    (data.results || []).forEach(m => {
      const div = document.createElement('div');
      div.className = "model-item";
      div.textContent = m.name + " (by " + m.user.displayName + ")";
      div.onclick = () => loadSketchfab(m.uid, m.name);
      resultsDiv.appendChild(div);
    });

    // compute page from offset query param (fallback to 1)
    try {
      const parsed = new URL(url);
      const offset = Number(parsed.searchParams.get('offset') || 0);
      currentPage = Math.floor(offset / perPage) + 1;
    } catch (e) {
      currentPage = currentPage || 1;
    }

    // update UI
    document.getElementById('page-info').textContent = `Page ${currentPage}`;
    document.getElementById('prev-page').disabled = !data.previous;
    document.getElementById('next-page').disabled = !data.next;

    // update lastQuery from URL if present
    try {
      lastQuery = new URL(url).searchParams.get('q') || lastQuery;
    } catch (e) { /* ignore */ }

  } catch (err) {
    console.error('Search error', err);
    alert('Search failed: ' + (err.message || err));
  }
}

async function renderResults(query, page = 1) {
  lastQuery = query;
  const url = buildSearchUrl(query, page);
  await renderFromUrl(url);
}

/* Prev / Next handlers that follow cursor links when available */
document.getElementById('prev-page').onclick = async () => {
  if (lastSearchData && lastSearchData.previous) {
    await renderFromUrl(lastSearchData.previous);
  } else if (currentPage > 1) {
    await renderResults(lastQuery, currentPage - 1);
  }
};

document.getElementById('next-page').onclick = async () => {
  if (lastSearchData && lastSearchData.next) {
    await renderFromUrl(lastSearchData.next);
  } else {
    await renderResults(lastQuery, currentPage + 1);
  }
};

/* Search button (unchanged) */
document.getElementById('btn-search').onclick = () => {
  const q = document.getElementById('search-query').value.trim();
  if (!q) { alert('Enter a search term'); return; }
  renderResults(q, 1);
};

/* --- keep your other functions (loadSketchfab, upload button, etc.) as-is --- */
</script>
</body>
</html>
