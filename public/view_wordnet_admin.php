<?php
// public/view_wordnet_admin.php
require_once __DIR__ . '/bootstrap.php';
require_once PROJECT_ROOT . '/src/Service/WordnetApi.php';

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "WordNet";
ob_start();
?>
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">
<style>
/* reuse many styles from view_sketch_templates_admin.php */
.admin-wrap { max-width: 1100px; margin: 0 auto; padding: 18px; }
.search-bar { display:flex; gap:8px; margin-bottom:12px; align-items:center; }
.search-bar input[type="text"] { padding:8px 10px; border-radius:6px; border:1px solid #ddd; width: 320px;}
.card { background:var(--card); padding:12px; border-radius:8px; }
.result-list { margin-top:12px; }
.result-item { padding:10px; border-bottom:1px solid rgba(0,0,0,0.06); display:flex; justify-content:space-between; gap:12px;}
.result-item .meta { color:var(--text-muted); font-size:0.9rem; }
.small { font-size:0.9rem; color: var(--text-muted); }
.modal { display:none; position:fixed; inset:0; z-index:120000; align-items:center; justify-content:center; background:rgba(0,0,0,0.45); }
.modal.active { display:flex; }
.modal-card { width:100%; max-width:900px; max-height:90vh; overflow:auto; background:var(--card); border-radius:8px; padding:16px; }
.btn { padding:6px 10px; border-radius:6px; border:1px solid rgba(0,0,0,0.08); background:transparent; cursor:pointer; }
.btn.primary { background:var(--accent); color:#fff; border:none; }
.badge { padding:4px 8px; border-radius:6px; background:#efefef; font-size:0.85rem; }
.json-pre { font-family: ui-monospace, monospace; white-space:pre-wrap; word-break:break-all; background:var(--card); padding:10px; border-radius:6px; border:1px solid #eee; }
.setup-prompt { background-color: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.4); border-radius: 8px; padding: 16px; margin-top: 12px; }
.setup-prompt-title { font-weight: 600; color: #d97706; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
.setup-prompt-text { color: var(--text-muted); font-size: 14px; line-height: 1.6; }
</style>

<div class="admin-wrap">
    <div style="display:flex; align-items:center; justify-content:space-between;">
        <h2>WordNet</h2>
        <div><button class="btn" onclick="window.location.href='view_wordnet_admin.php'">Refresh</button></div>
    </div>

    <div class="card">
        <div class="search-bar">
            <input id="q" type="text" placeholder="Search lemmas (e.g. run, happy, bank)..." />
            <button class="btn primary" onclick="doSearch()">Search</button>
            <button class="btn" onclick="document.getElementById('q').value=''; doSearch()">Clear</button>
            <div style="margin-left:auto">
                <input id="limit" type="number" value="50" style="width:80px; padding:6px; border-radius:6px; border:1px solid #ddd;"> results
            </div>
        </div>

        <div class="result-list" id="results"></div>
    </div>
</div>

<!-- Modal: Synset details -->
<div id="synModal" class="modal">
  <div class="modal-card">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <h3 id="synTitle">Synset</h3>
      <div><button class="btn" onclick="closeSynModal()">Close</button></div>
    </div>
    <div id="synBody" style="margin-top:12px;"></div>
  </div>
</div>

<script>
const API = '/wordnet_api.php';

function showToast(msg) {
  if (typeof Toast !== 'undefined' && Toast.show) { Toast.show(msg); } else { console.log(msg); }
}

async function doSearch() {
  const q = document.getElementById('q').value.trim();
  const limit = parseInt(document.getElementById('limit').value || 50, 10);
  const resultsDiv = document.getElementById('results');
  if (!q) { resultsDiv.innerHTML = '<div class="small">Enter a search term.</div>'; return; }
  
  resultsDiv.innerHTML = '<div class="small">Searching...</div>';
  const res = await fetch(`${API}?action=search&q=${encodeURIComponent(q)}&limit=${limit}`);
  const payload = await res.json();
  
  // NEW: Check for the specific "table doesn't exist" error, even if status is "ok"
  if (payload.data && typeof payload.data.detail === 'string' && payload.data.detail.includes("Table") && payload.data.detail.includes("doesn't exist")) {
    resultsDiv.innerHTML = `
      <div class="setup-prompt">
        <div class="setup-prompt-title">
          <span>⚠️</span>
          <span>Database Not Initialized</span>
        </div>
        <div class="setup-prompt-text">
          It looks like the WordNet database tables have not been created yet.
          <br>Have you performed the initial, one-time data import?
          <br><br>
          <a href="view_wordnet_initial_import.php" class="btn primary" style="text-decoration: none;">
            Go to Initial Import Page
          </a>
        </div>
      </div>
    `;
    return; // Stop further processing
  }

  if (payload.status !== 'ok') { 
    showToast(payload.message || 'Search failed'); 
    resultsDiv.innerHTML = `<div class="small" style="color:var(--red);">Error: ${escapeHtml(payload.message || 'Unknown error')}</div>`;
    return; 
  }

  const rows = payload.data.results || payload.data;
  const html = [];
  if (!rows || rows.length === 0) {
    html.push('<div class="small">No results.</div>');
  } else {
    rows.forEach(lemma => {
      html.push(`<div class="result-item"><div><strong>${escapeHtml(lemma)}</strong><div class="meta small">Lemma</div></div>
        <div style="display:flex; gap:8px;">
          <button class="btn" onclick="loadLemma('${encodeURIComponent(lemma)}')">View Senses</button>
          <button class="btn" onclick="searchSynsets('${encodeURIComponent(lemma)}')">Synsets</button>
        </div></div>`);
    });
  }
  resultsDiv.innerHTML = html.join('');
}

async function loadLemma(encodedLemma) {
  const lemma = decodeURIComponent(encodedLemma);
  const resp = await fetch(API + '?action=lemma', { method: 'POST', body: JSON.stringify({ q: lemma }) });
  const payload = await resp.json();
  if (payload.status !== 'ok') { showToast(payload.message || 'Failed'); return; }
  const rows = payload.data;
  // display senses
  let html = `<h4>Lemma: ${escapeHtml(lemma)}</h4>`;
  if (!rows || rows.length === 0) html += '<div class="small">No senses found.</div>';
  else {
    for (const r of rows) {
      html += `<div style="margin:8px 0; padding:8px; border-radius:6px; border:1px solid #eee;">
        <div style="display:flex; justify-content:space-between; gap:12px;">
          <div><strong>synset ${r.synsetid}</strong> <span class="badge">${r.pos || ''}</span></div>
          <div style="min-width:160px; text-align:right;"><button class="btn" onclick="openSynset(${r.synsetid})">Open synset</button></div>
        </div>
        <div class="small" style="margin-top:8px;">${escapeHtml(r.definition||'')}</div>
        <div class="small" style="margin-top:6px; color:#666;">samples: ${escapeHtml(r.sampleset||'')}</div>
      </div>`;
    }
  }
  document.getElementById('synBody').innerHTML = html;
  openSynModal();
}

async function openSynset(synsetid) {
  const resp = await fetch(API + '?action=synset', { method:'POST', body: JSON.stringify({ id: synsetid }) });
  const payload = await resp.json();
  if (payload.status !== 'ok') { showToast(payload.message||'failed'); return; }
  const data = payload.data;
  let html = `<h4>Synset ${data.synsetid} <span class="badge">${escapeHtml(data.pos)}</span></h4>
    <div style="margin-top:8px;"><strong>Definition</strong><div class="small">${escapeHtml(data.definition||'')}</div></div>
    <div style="margin-top:8px;"><strong>Synonyms</strong><div class="small">${(data.synonyms||[]).map(s=>escapeHtml(s)).join(', ')}</div></div>
    <div style="margin-top:8px;"><button class="btn" onclick="loadHypernyms(${data.synsetid})">Show Hypernyms</button> <button class="btn" onclick="viewRaw(${JSON.stringify(data).replace(/"/g,'&quot;')})">Raw</button></div>
    <div id="hypernyms" style="margin-top:12px;"></div>`;
  document.getElementById('synBody').innerHTML = html;
  openSynModal();
}

async function loadHypernyms(synsetid) {
  const resp = await fetch(API + '?action=hypernyms', { method:'POST', body: JSON.stringify({ id: synsetid }) });
  const payload = await resp.json();
  if (payload.status !== 'ok') { showToast(payload.message||'failed'); return; }
  const list = payload.data.hypernyms || [];
  const out = '<div style="margin-top:8px;"><strong>Hypernyms</strong><div class="small">' + (list.length ? list.map(h=>`<div>• <strong>${h.synsetid}</strong> — ${escapeHtml(h.definition||'')}</div>`).join('') : 'None') + '</div></div>';
  document.getElementById('hypernyms').innerHTML = out;
}

async function searchSynsets(lemma) {
  // opens lemma view which shows synsets
  loadLemma(encodeURIComponent(lemma));
}

function openSynModal() { document.getElementById('synModal').classList.add('active'); }
function closeSynModal() { document.getElementById('synModal').classList.remove('active'); }
function viewRaw(json) { document.getElementById('synBody').innerHTML += `<div class="json-pre">${escapeHtml(JSON.stringify(json,null,2))}</div>`; }
function escapeHtml(text){ if(text===null||text===undefined) return ''; return String(text).replace(/[&<>"'`]/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;', '`':'&#96;'}[m]; }); }

// auto-run if q param present
(function(){
  const params = new URLSearchParams(location.search);
  if (params.get('q')) {
    document.getElementById('q').value = params.get('q');
    doSearch();
  }
})();
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>
