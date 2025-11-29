<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Sketchfab</title>

<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark') document.documentElement.setAttribute('data-theme','dark');
      else if (theme === 'light') document.documentElement.setAttribute('data-theme','light');
    } catch(e){}
  })();
</script>
<script src="/js/theme-manager.js"></script>

<!-- base styles -->
<link rel="stylesheet" href="/css/base.css">

<!-- small layout helpers that use base.css variables -->
<style>
  :root{
    --viewer-max-width: 960px;
    --viewer-default-height: 540px;
  }

  body { padding: 0; margin: 0; }

  .page {
    padding: 20px;
  }

  .page-head {
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom: 18px;
  }

  .toolbar {
    display:flex;
    gap:12px;
    align-items:center;
    flex-wrap:wrap;
  }

  .card { 
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    padding: 16px;
  }

  .panel {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
  }

  .left-col { flex: 1 1 320px; min-width:280px; max-width: 520px; }
  .right-col { flex: 1 1 360px; min-width:260px; max-width: var(--viewer-max-width); }

  /* results list */
  #results { 
    max-height: 420px;
    overflow: auto;
    border-radius: var(--radius-sm);
    background: var(--card-weak);
    border: 1px solid rgba(0,0,0,0.04);
    padding: 8px;
  }

  .model-item {
    padding: 8px;
    border-bottom: 1px solid rgba(0,0,0,0.04);
    cursor: pointer;
    font-size: 14px;
    color: var(--accent);
  }
  .model-item:last-child { border-bottom: none; }
  .model-item:hover { background: rgba(0,0,0,0.03); }

  /* viewer */
  #sketchfab-container {
    width: 100%;
    max-width: var(--viewer-max-width);
    height: var(--viewer-default-height);
    background: var(--viewer-bg, transparent);
    border-radius: var(--radius);
    border: 1px solid rgba(0,0,0,0.06);
    overflow: hidden;
  }

  .model-info {
    margin-top: 10px;
    padding: 10px;
    border-radius: var(--radius-sm);
    background: var(--card-weak);
    border: 1px solid rgba(0,0,0,0.04);
    font-size: 14px;
    color: var(--accent);
  }

  .controls {
    margin-top: 10px;
  }

  /* small helpers */
  .muted { color: var(--muted); font-size: 13px; }
  .vis-hidden { display:none; }

  /* notification fallback styling (uses base variables) */
  .notification { padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; }
  .notification-success { background: rgba(16,185,129,0.08); color: var(--success); border: 1px solid rgba(16,185,129,0.12); }
  .notification-error   { background: rgba(244,63,94,0.06); color: var(--error); border: 1px solid rgba(244,63,94,0.08); }

  /* form elements */
  .form-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .form-row .form-control { flex: 1 1 auto; min-width:120px; }

  /* responsive adjustments */
  @media (max-width: 880px) {
    .panel { flex-direction: column; }
    #sketchfab-container { height: 360px; }
  }
</style>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <script src="https://static.sketchfab.com/api/sketchfab-viewer-1.12.1.js"></script>
<?php else: ?>
  <script src="/vendor/sketchfab/sketchfab-viewer-1.12.1.js"></script>
<?php endif; ?>

<!-- toast (used instead of alert) -->
<script src="/js/toast.js"></script>

<?php echo $eruda; ?>
</head>
<body>

<div class="page">
  <div class="page-head" style="margin-left: 45px;">
    <a style="display:none;" class="btn btn-ghost" href="/dashboard.php" title="Dashboard">&#x1F5C3;</a>
    <div style="flex:1">
      <h1 style="margin:0; margin-top:4px;font-size:1.15rem">Sketchfab</h1>
      <div class="muted">Search and load Sketchfab models, take snapshots and upload them to the server.</div>
    </div>
  </div>

  <div class="panel">
    <div class="left-col">
      <div class="card">
        <div class="toolbar" role="toolbar" aria-label="Search controls">
          <div style="flex:1;">
            <label class="small-muted" for="search-query">Search models</label>
            <input id="search-query" class="form-control" type="search" placeholder="Enter search term (e.g. car, character)">
          </div>
          <div style="display:flex;align-items:flex-end;gap:8px">
            <button id="btn-search" class="btn btn-primary">Search</button>
          </div>
        </div>

        <div id="results" aria-live="polite" class="muted">
          <!-- results will render here -->
          <div class="muted">No results yet — enter a search term and press Search.</div>
        </div>

        <div class="form-row" style="margin-top:10px; justify-content:flex-end;">
          <button id="prev-page" class="btn">Prev</button>
          <div id="page-info" class="muted vis-hidden" aria-hidden="true"></div>
          <button id="next-page" class="btn">Next</button>
        </div>
      </div>

      <div style="height:12px"></div>

      <div class="card">
        <h3 style="margin-top:0">Controls</h3>
        <div class="controls">
          <button id="btn-upload" class="btn btn-primary">Upload Snapshot</button>
        </div>
        <div id="upload-status" style="margin-top:10px" aria-live="polite"></div>
      </div>
    </div>

    <div class="right-col">
      <div class="card">
        <div id="sketchfab-container" aria-label="Sketchfab viewer">
          <!-- iframe will be injected here -->
          <div class="muted" style="padding:12px">No model loaded</div>
        </div>

        <div class="model-info" id="model-info" role="region" aria-live="polite" aria-atomic="true">
          <strong>Model info</strong>
          <div id="model-info-content" style="margin-top:8px" class="muted"><em>No model loaded</em></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
/* globals: Sketchfab, Toast */
(function(){
  // safe Toast wrapper
  function toast(msg, type='info'){
    try {
      if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
        Toast.show(msg, type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'));
      } else {
        // fallback
        console.log('[toast]', type, msg);
        alert(msg);
      }
    } catch (e) {
      console.log('[toast fallback]', e);
      alert(msg);
    }
  }

  let api = null;
  let currentModelUid = null;
  let currentModelName = null;
  let currentPage = 1;
  const perPage = 20;
  let lastQuery = "";
  let lastSearchData = null;

  function buildSearchUrl(query, page = 1){
    const offset = (page - 1) * perPage;
    return `https://api.sketchfab.com/v3/search?type=models&q=${encodeURIComponent(query)}&downloadable=true&count=${perPage}&offset=${offset}`;
  }

  async function fetchSearchUrl(url){
    const res = await fetch(url);
    if (!res.ok) throw new Error('Search request failed: ' + res.status);
    const data = await res.json();
    lastSearchData = data;
    return data;
  }

  async function renderFromUrl(url){
    try {
      const data = await fetchSearchUrl(url);
      const resultsDiv = document.getElementById('results');
      resultsDiv.innerHTML = '';
      (data.results || []).forEach(m => {
        const div = document.createElement('div');
        div.className = 'model-item';
        div.textContent = (m.name || m.uid) + ' — ' + (m.user?.displayName || 'unknown');
        div.tabIndex = 0;
        div.onclick = () => loadSketchfab(m.uid, m.name);
        div.onkeypress = (e) => { if (e.key === 'Enter') loadSketchfab(m.uid, m.name); };
        resultsDiv.appendChild(div);
      });

      // page computation
      try {
        const parsed = new URL(url);
        const offset = Number(parsed.searchParams.get('offset') || 0);
        currentPage = Math.floor(offset / perPage) + 1;
      } catch (e) { currentPage = 1; }

      document.getElementById('page-info').textContent = `Page ${currentPage}`;
      document.getElementById('page-info').classList.remove('vis-hidden');
      document.getElementById('prev-page').disabled = !data.previous;
      document.getElementById('next-page').disabled = !data.next;
      lastQuery = (new URL(url)).searchParams.get('q') || lastQuery;

    } catch (err) {
      console.error('Search error', err);
      toast('Search failed: ' + (err.message || err), 'error');
    }
  }

  async function renderResults(query, page = 1){
    lastQuery = query;
    const url = buildSearchUrl(query, page);
    await renderFromUrl(url);
  }

  // fetch model metadata and render in model-info
  async function fetchModelInfo(uid) {
    const infoDiv = document.getElementById('model-info-content');
    infoDiv.innerHTML = '<div>Loading model metadata…</div>';
    try {
      const url = `https://api.sketchfab.com/v3/models/${encodeURIComponent(uid)}`;
      const res = await fetch(url);
      if (!res.ok) throw new Error('Metadata request failed: ' + res.status);
      const json = await res.json();

      const user = json.user || {};
      const authorName = user.displayName || user.username || 'Unknown author';
      const authorUsername = user.username || null;
      const authorProfile = authorUsername ? `https://sketchfab.com/${encodeURIComponent(authorUsername)}` : (user.url || '#');

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

      const modelPage = `https://sketchfab.com/models/${encodeURIComponent(uid)}`;

      const downloadableHtml = json.isDownloadable ? `<div style="margin-top:6px;color:var(--success);"><strong>Downloadable:</strong> yes</div>` : '';

      const markup =
        `<div><strong>Model:</strong> <a href="${modelPage}" target="_blank" rel="noopener">${escapeHtml(json.name || uid)}</a></div>` +
        `<div><strong>Author:</strong> ${authorProfile && authorProfile !== '#' ? `<a href="${authorProfile}" target="_blank" rel="noopener">${escapeHtml(authorName)}</a>` : escapeHtml(authorName)}</div>` +
        `<div><strong>License:</strong> ${licenseUrl ? `<a href="${escapeHtml(licenseUrl)}" target="_blank" rel="noopener">${escapeHtml(licenseName)}</a>` : escapeHtml(licenseName)}</div>` +
        downloadableHtml;

      infoDiv.innerHTML = markup;

    } catch (err) {
      console.error('fetchModelInfo error', err);
      const infoDiv = document.getElementById('model-info-content');
      infoDiv.innerHTML = `<div style="color:var(--error);">Failed to load model info: ${escapeHtml(err.message || String(err))}</div>`;
    }
  }

  function escapeHtml(str) {
    if (!str && str !== 0) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  // Initialize Sketchfab viewer into container and fetch metadata when ready
  function loadSketchfab(uid, name='') {
    const container = document.getElementById('sketchfab-container');
    container.innerHTML = '';

    document.getElementById('model-info-content').innerHTML = '<div>Loading model...</div>';

    const iframe = document.createElement('iframe');
    iframe.width = '100%';
    iframe.height = '100%';
    iframe.style.minHeight = '320px';
    iframe.frameBorder = '0';
    iframe.allow = 'autoplay; fullscreen; vr';
    container.appendChild(iframe);

    const client = new Sketchfab(iframe);

    client.init(uid, {
      success: function(_api) {
        api = _api;
        currentModelUid = uid;
        currentModelName = name || '';
        api.start();
        api.addEventListener('viewerready', function() {
          console.log('Viewer ready for', uid);
          fetchModelInfo(uid).catch(e => {
            console.error('fetchModelInfo failed', e);
          });
        });
      },
      error: function() {
        console.error('Sketchfab API error');
        toast('Failed to initialize Sketchfab viewer', 'error');
        document.getElementById('model-info-content').innerHTML = '<div style="color:var(--error);">Viewer failed to initialize</div>';
      }
    });
  }

  // Upload snapshot button: take screenshot via api.getScreenShot and POST JSON to /save_snapshot.php
  document.getElementById('btn-upload').addEventListener('click', async function () {
    const btn = this;
    if (!api) { toast('Load a model first (search -> click result)', 'error'); return; }

    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = 'Taking snapshot…';

    try {
      const dataUri = await new Promise((resolve, reject) => {
        api.getScreenShot(1280, 720, 'image/png', function(err, result) {
          if (err) return reject(err);
          resolve(result);
        });
      });

      btn.textContent = 'Uploading…';

      let hint = currentModelUid || 'snapshot';
      if (currentModelName) {
        const sanitized = currentModelName.replace(/[^a-zA-Z0-9_\-]/g, '_').slice(0, 30);
        hint = `${hint}-${sanitized}`;
      }

      const res = await fetch('/save_snapshot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ image: dataUri, filename: hint })
      });

      const json = await res.json();
      if (res.ok && json.success) {
        toast('Upload success — saved: ' + (json.filename || 'file'), 'success');
        // show server response in upload-status area
        const us = document.getElementById('upload-status');
        us.innerHTML = `<div class="notification notification-success">Saved: <a href="${json.url}" target="_blank" rel="noopener">${escapeHtml(json.filename || json.url)}</a></div>`;
      } else {
        console.error('Server error response', json);
        toast('Upload failed: ' + (json.error || res.statusText || 'unknown'), 'error');
        const us = document.getElementById('upload-status');
        us.innerHTML = `<div class="notification notification-error">Upload failed: ${escapeHtml(json.error || res.statusText || 'unknown')}</div>`;
      }

    } catch (err) {
      console.error('Snapshot/upload error', err);
      toast('Snapshot/upload failed: ' + (err.message || err), 'error');
      const us = document.getElementById('upload-status');
      us.innerHTML = `<div class="notification notification-error">Snapshot/upload failed: ${escapeHtml(err.message || String(err))}</div>`;
    } finally {
      btn.disabled = false;
      btn.textContent = originalText;
    }
  });

  // Pagination handlers: prefer cursor links from API response when available
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

  // Search button
  document.getElementById('btn-search').onclick = () => {
    const q = document.getElementById('search-query').value.trim();
    if (!q) { toast('Enter a search term', 'error'); return; }
    renderResults(q, 1);
  };

  // Enter key on search input triggers search
  document.getElementById('search-query').addEventListener('keydown', function(e){
    if (e.key === 'Enter') {
      e.preventDefault();
      document.getElementById('btn-search').click();
    }
  });

})();
</script>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

</body>
</html>
