<?php
// public/skeletjson.php
// JSON Skeleton Exporter (view-only)
// ----------------------------------------------------
// UPDATES:
// 1. Fixed Layout: Prism box is now the single scrollable element (flex fixes).
// 2. Fixed Logic: buildSkeleton now scans ALL array items and merges their keys
//    so no optional fields are omitted from the export.
//
// Dependencies: public/bootstrap.php, public/env_locals.php
// ------------------------------------------------------------------

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

// ═══════════════════════════════════════════════════════
// API HANDLER (read-only)
// ═══════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_REQUEST['api_action'];

    try {
        if ($action === 'get_analyses') {
            $limit  = (int)($_GET['limit'] ?? 20);
            $offset = (int)($_GET['offset'] ?? 0);
            $search = trim($_GET['search'] ?? '');

            $where = "1=1";
            $params = [];
            if ($search !== '') {
                $where = "(d.name LIKE :s OR m.id = :idsearch OR m.doc_id = :docidsearch)";
                $params[':s'] = "%{$search}%";
                $params[':idsearch'] = intval($search);
                $params[':docidsearch'] = intval($search);
            }

            $countSql = "SELECT COUNT(*) FROM md_doc_analysis m JOIN documentations d ON m.doc_id = d.id WHERE $where";
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();

            $sql = "SELECT m.id AS md_id, m.doc_id, d.name AS doc_name, m.analyzed_at,
                           CHAR_LENGTH(COALESCE(m.showrunner_analysis, '')) AS json_len
                    FROM md_doc_analysis m
                    JOIN documentations d ON m.doc_id = d.id
                    WHERE $where
                    ORDER BY m.id DESC
                    LIMIT :lim OFFSET :off";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status'=>'success', 'data'=>$rows, 'total'=>$total]);
            exit;
        }

        if ($action === 'get_analysis') {
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($id <= 0) throw new Exception("Invalid id");

            $sql = "SELECT m.id AS md_id, m.doc_id, d.name AS doc_name, m.showrunner_analysis
                    FROM md_doc_analysis m
                    JOIN documentations d ON m.doc_id = d.id
                    WHERE m.id = :id LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception("Not found");

            echo json_encode(['status'=>'success', 'data'=>$row]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
        exit;
    }

    exit;
}

// ═══════════════════════════════════════════════════════
// Page (UI)
// ═══════════════════════════════════════════════════════

$pageTitle = 'JSON Skeleton Export';
ob_start();
?>
<!-- Dependencies -->
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs/themes/prism-tomorrow.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs/plugins/toolbar/prism-toolbar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs/plugins/copy-to-clipboard/prism-copy-to-clipboard.css">
<?php else: ?>
    <link rel="stylesheet" href="/vendor/prismjs/themes/prism-tomorrow.min.css">
    <link rel="stylesheet" href="/vendor/prismjs/plugins/toolbar/prism-toolbar.css">
    <link rel="stylesheet" href="/vendor/prismjs/plugins/copy-to-clipboard/prism-copy-to-clipboard.css">
<?php endif; ?>

<style>
  *, *::before, *::after { box-sizing: border-box; }
  :root{
    --bg:#0a0a0f; --card:#111118; --border:#1e1e2e; --text:#e2e2f0; --muted:#9aa0ac; --accent:#f59e0b;
  }
  html,body{height:100%; margin:0; background:var(--bg); color:var(--text); font-family:'DM Mono','Fira Mono',monospace; }

  /* Flex Layout */
  .eh-layout { display:flex; flex-direction:column; height:100vh; min-height:100vh; }
  .eh-header { flex:0 0 auto; padding:10px 16px; background:var(--card); border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
  .eh-title{ font-weight:700; color:var(--accent); letter-spacing:0.6px; }

  .eh-top-panel{ flex:0 0 auto; background: rgba(0,0,0,0.12); border-bottom:1px solid var(--border); }
  .mr-controls-row{ display:flex; gap:8px; align-items:center; padding:10px 12px; background:var(--card); }
  .mr-search-input{ flex:1; padding:8px 12px; border-radius:6px; border:1px solid var(--border); background:#05060a; color:var(--text); }
  .mr-list-scroll{ max-height:240px; overflow:auto; padding:6px 0; }
  .mr-item{ display:flex; gap:12px; align-items:center; padding:10px 12px; border-bottom:1px solid var(--border); cursor:pointer; }
  .mr-item.active{ background: rgba(245,158,11,0.06); border-left:3px solid var(--accent); padding-left:9px; }

  /* Grid Area & Right Panel */
  .eh-grid-area{ flex:1 1 auto; display:flex; gap:12px; padding:12px; overflow: hidden; min-height:0; background:#000; }
  .right-panel{ flex:1 1 auto; display:flex; flex-direction:column; gap:12px; padding:14px; border-radius:8px; background:var(--card); border:1px solid var(--border); min-height:0; overflow: hidden; }

  .controls{ flex:0 0 auto; display:flex; justify-content:space-between; align-items:center; gap:8px; }
  .action-btn{ padding:6px 10px; border-radius:6px; border:1px solid var(--border); background:transparent; color:var(--muted); cursor:pointer; font-weight:700; }
  .action-btn.primary{ border-color:var(--accent); color:var(--accent); }

  /* Compatibility textarea (hidden) */
  textarea#resultText{ position:absolute !important; left:-9999px !important; top:auto !important; width:1px !important; height:1px !important; overflow:hidden !important; opacity:0 !important; pointer-events:none !important; }

  /* Prism Layout Fixes */
  /* The toolbar wrapper must not break flex flow */
  div.code-toolbar {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }
  
  pre.prism-box{
    margin:0 !important;
    padding:12px;
    border-radius:8px;
    border:1px solid var(--border);
    background: transparent;
    flex:1 1 auto;
    min-height:0;
    overflow:auto; /* Single scrollbar */
    white-space: pre;
    height: 100%;
  }
  pre.prism-box code{ color:var(--text) !important; font-family:inherit; font-size:0.9rem; }

  .eh-footer{ flex:0 0 auto; padding:10px 16px; background:var(--card); border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
  .state-msg{ color:var(--muted); font-size:0.9rem; }

  @keyframes spin{ to{ transform:rotate(360deg); } }
</style>

<div class="eh-layout">
  <div class="eh-header">
    <div class="eh-title">&#128196; JSON SKELETON EXPORT <span style="font-size:.8rem;opacity:.6;margin-left:10px;">// md_doc_analysis</span></div>
  </div>

  <div class="eh-top-panel">
    <div class="mr-controls-row">
      <input id="mrSearch" class="mr-search-input" placeholder="Search doc name or md id..." oninput="debounceSearch()">
      <div style="display:flex;gap:8px">
        <button class="action-btn" onclick="toggleAll(false)">None</button>
        <button class="action-btn primary" onclick="toggleAll(true)">All</button>
      </div>
      <div id="mrSummary" style="color:var(--muted);margin-left:12px">0 total</div>
    </div>
    <div id="mrList" class="mr-list-scroll"><div style="padding:12px;color:var(--muted)">Loading analyses...</div></div>
  </div>

  <div class="eh-grid-area">
    <div class="right-panel">
      <div class="controls">
        <div style="display:flex;gap:8px;">
          <button id="exportBtn" class="action-btn primary" onclick="exportSkeletons()" disabled>Export Skeleton</button>
          <button id="copyBtn" class="action-btn" onclick="copyResult()" disabled>Copy</button>
          <button class="action-btn" onclick="clearResult()">Clear</button>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <div id="selCount" style="color:var(--muted)">0 selected</div>
          <button class="action-btn" onclick="downloadCurrent()">Download</button>
        </div>
      </div>

      <!-- Hidden textarea for copy fallback -->
      <textarea id="resultText" readonly aria-hidden="true"></textarea>

      <!-- Prism Code Block -->
      <pre class="prism-box"><code id="resultCode" class="language-json"></code></pre>
    </div>
  </div>

  <div class="eh-footer">
    <div class="state-msg">Scanning arrays fully to capture all optional keys.</div>
    <div style="color:var(--muted)">Use Copy / Download for the payload.</div>
  </div>
</div>

<!-- Prism JS -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <script src="https://cdn.jsdelivr.net/npm/prismjs/prism.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/prismjs/components/prism-json.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/prismjs/plugins/toolbar/prism-toolbar.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/prismjs/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js"></script>
<?php else: ?>
  <script src="/vendor/prismjs/prism.js"></script>
  <script src="/vendor/prismjs/components/prism-json.min.js"></script>
  <script src="/vendor/prismjs/plugins/toolbar/prism-toolbar.min.js"></script>
  <script src="/vendor/prismjs/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js"></script>
<?php endif; ?>

<script>
let analyses = [];
let selected = new Set();
let curPage = 1;
let perPage = 30;
let total = 0;
let debounceTimer = null;

document.addEventListener('DOMContentLoaded', () => {
  loadAnalyses(1);
});

function debounceSearch(){
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(()=>loadAnalyses(1), 300);
}

async function loadAnalyses(page=1){
  curPage = page;
  const search = document.getElementById('mrSearch').value.trim();
  const list = document.getElementById('mrList');
  list.innerHTML = '<div style="padding:12px;color:var(--muted)"><span style="display:inline-block;margin-right:8px;width:14px;height:14px;border:2px solid rgba(245,158,11,0.12);border-top-color:var(--accent);border-radius:50%;animation:spin 0.8s linear infinite;"></span>Loading analyses...</div>';
  try {
    const resp = await fetch(`?api_action=get_analyses&limit=${perPage}&offset=${(page-1)*perPage}&search=${encodeURIComponent(search)}`);
    const j = await resp.json();
    if (!j || j.status !== 'success') throw new Error(j && j.message ? j.message : 'Failed to load');
    analyses = j.data || [];
    total = j.total || 0;
    renderAnalyses();
  } catch (err){
    console.error(err);
    list.innerHTML = `<div style="padding:12px;color:var(--muted)">Error loading analyses: ${esc(err.message||err)}</div>`;
  }
}

function renderAnalyses(){
  const list = document.getElementById('mrList');
  list.innerHTML = '';
  document.getElementById('mrSummary').textContent = `${total} total`;
  if (!analyses || analyses.length === 0) {
    list.innerHTML = '<div style="padding:12px;color:var(--muted)">No analyses found.</div>';
    return;
  }
  analyses.forEach(a=>{
    const el = document.createElement('div');
    el.className = 'mr-item';
    el.dataset.mdId = a.md_id;
    const cbWrap = document.createElement('div');
    cbWrap.style.marginRight = '8px';
    const cb = document.createElement('input');
    cb.type = 'checkbox';
    cb.id = 'cb-' + a.md_id;
    cb.dataset.mdId = a.md_id;
    cb.onchange = (e)=>{
      const id = parseInt(e.target.dataset.mdId,10);
      if (e.target.checked) selected.add(id); else selected.delete(id);
      refreshSelectionUI();
      if (e.target.checked) el.classList.add('active'); else el.classList.remove('active');
    };
    cbWrap.appendChild(cb);
    const idDiv = document.createElement('div'); idDiv.style.minWidth = '48px'; idDiv.style.fontWeight='700'; idDiv.textContent = `#${a.md_id}`;
    const note = document.createElement('div'); note.style.flex='1'; note.textContent = a.doc_name || '—';
    const meta = document.createElement('div'); meta.style.minWidth='120px'; meta.style.textAlign='right'; meta.style.color='var(--muted)'; meta.textContent = `${a.analyzed_at? a.analyzed_at.substring(0,10):'—'} • ${formatBytes(a.json_len||0)}`;
    el.addEventListener('click', (ev)=>{ if (ev.target && ev.target.tagName==='INPUT') return; cb.checked = !cb.checked; cb.dispatchEvent(new Event('change')); });
    el.appendChild(cbWrap); el.appendChild(idDiv); el.appendChild(note); el.appendChild(meta);
    list.appendChild(el);
  });
  refreshSelectionUI();
}

function toggleAll(select){
  selected.clear();
  document.querySelectorAll('#mrList input[type="checkbox"]').forEach(cb=>{
    cb.checked = !!select;
    const id = parseInt(cb.dataset.mdId,10);
    if (select) selected.add(id); else selected.delete(id);
    const row = cb.closest('.mr-item');
    if (row) { if (select) row.classList.add('active'); else row.classList.remove('active'); }
  });
  refreshSelectionUI();
}

function refreshSelectionUI(){
  document.getElementById('exportBtn').disabled = selected.size === 0;
  document.getElementById('selCount').textContent = `${selected.size} selected`;
  const codeContent = document.getElementById('resultCode').textContent || '';
  document.getElementById('copyBtn').disabled = !(codeContent && codeContent.trim().length > 0);
}

function esc(s){ return s ? s.toString().replace(/</g,'&lt;').replace(/>/g,'&gt;') : ''; }
function formatBytes(n){ if(!n) return '0 B'; const kb=1024; if(n<kb) return n+' B'; if(n<kb*kb) return Math.round(n/kb)+' KB'; return Math.round(n/(kb*kb))+' MB'; }

// ═══════════════════════════════════════════════════════
// LOGIC UPDATE: Deep Merge Skeleton Builder
// ═══════════════════════════════════════════════════════

// Helper: Merges value B into A recursively to ensure no keys are missing.
// If both are arrays, it combines them to ensure the scanner sees all children.
// If one is object and other is not, prefers object.
function mergeStructures(a, b) {
    // Handling nulls/undefined
    if (a === null || a === undefined) return b;
    if (b === null || b === undefined) return a;
    
    // Check types
    const isArrA = Array.isArray(a);
    const isArrB = Array.isArray(b);
    const isObjA = typeof a === 'object';
    const isObjB = typeof b === 'object';

    // 1. If both are arrays: concatenate. 
    // This ensures that if Item1=[{x:1}] and Item2=[{y:2}], the merged array is [{x:1},{y:2}].
    // Then buildSkeleton will iterate this merged array and merge x and y.
    if (isArrA && isArrB) {
        return a.concat(b);
    }

    // 2. If mixed array/object or primitive/object: Prefer the Object structure.
    // If we have [1, {a:1}], we want to capture the object structure.
    if (isObjA !== isObjB) return isObjA ? a : b;
    
    // 3. If both are Primitives (and types match or don't): keep A.
    if (!isObjA) return a;

    // 4. Both are Objects (and not arrays). Merge keys recursively.
    const out = { ...a };
    for (const k in b) {
        if (Object.prototype.hasOwnProperty.call(b, k)) {
            if (!Object.prototype.hasOwnProperty.call(out, k)) {
                out[k] = b[k]; // New key
            } else {
                // Key exists in both, merge deeper
                out[k] = mergeStructures(out[k], b[k]);
            }
        }
    }
    return out;
}

function buildSkeleton(value) {
  // 1. Arrays
  if (Array.isArray(value)) {
    if (value.length === 0) return [];
    
    // Scan ALL items in the array to build a representative "Super Object".
    // This fixes the issue where keys in later items were omitted.
    let merged = null;
    
    for (const item of value) {
        merged = mergeStructures(merged, item);
    }
    
    // If the array contained only nulls, merged is null
    if (merged === null) return [];
    
    // Recursive call on the merged super-structure
    return [ buildSkeleton(merged) ];
  }

  // 2. Objects
  if (value !== null && typeof value === 'object') {
    const out = {};
    // Iterate keys and build skeleton for values
    // Since we already merged in the Array step, this represents the union of keys.
    const keys = Object.keys(value).sort(); // sort for consistent output
    for (const k of keys) {
        out[k] = buildSkeleton(value[k]);
    }
    return out;
  }

  // 3. Primitives
  if (value === null) return "__NULL__";
  if (typeof value === 'string') return "__STRING__";
  if (typeof value === 'boolean') return "__BOOL__";
  if (typeof value === 'number') return Number.isInteger(value) ? "__INT__" : "__FLOAT__";
  
  return "__UNKNOWN__";
}

async function exportSkeletons(){
  if (selected.size === 0) return;
  const ids = Array.from(selected);
  const resultObj = {};
  const exportBtn = document.getElementById('exportBtn');
  const origLabel = exportBtn.textContent;
  exportBtn.disabled = true; exportBtn.textContent = 'Working...';
  Toast.show(`Processing ${ids.length} item(s)...`, 'info');

  // Small delay to let UI render the "Working" state
  await new Promise(r => setTimeout(r, 50));

  for (let i=0;i<ids.length;i++){
    const id = ids[i];
    try {
      const res = await fetch(`?api_action=get_analysis&id=${encodeURIComponent(id)}`);
      const j = await res.json();
      if (j.status !== 'success') { Toast.show(`Error fetching id ${id}`,'error'); continue; }
      const row = j.data;
      const raw = row.showrunner_analysis;
      const label = row.doc_name + ` (md.${row.md_id})`;
      
      if (!raw || raw.trim() === '') { 
          resultObj[label] = "__EMPTY__"; 
          continue; 
      }
      
      let parsed;
      try { 
          parsed = JSON.parse(raw); 
      } catch(e) { 
          resultObj[label] = { __INVALID_JSON__: raw.substring(0,300) + (raw.length>300? '...' : '') }; 
          continue; 
      }
      
      // Build the skeleton using the new robust logic
      resultObj[label] = buildSkeleton(parsed);
      
    } catch (err) {
      console.error('fetch error', err);
      Toast.show(`Network error for id ${id}`, 'error');
    }
  }

  const pretty = JSON.stringify(resultObj, null, 2);
  
  // Update textarea (hidden)
  document.getElementById('resultText').value = pretty;
  
  // Update Prism
  const codeEl = document.getElementById('resultCode');
  codeEl.textContent = pretty;
  
  if (window.Prism && typeof Prism.highlightElement === 'function') {
      Prism.highlightElement(codeEl);
  }

  document.getElementById('copyBtn').disabled = false;
  exportBtn.disabled = false; exportBtn.textContent = origLabel;
  Toast.show('Skeletons generated!', 'success');
  refreshSelectionUI();
}

async function copyResult(){
  const codeEl = document.getElementById('resultCode');
  const text = (codeEl && codeEl.textContent && codeEl.textContent.trim()) ? codeEl.textContent : document.getElementById('resultText').value;
  if (!text || !text.trim()) { Toast.show('Nothing to copy','error'); return; }
  try {
    if (navigator.clipboard && navigator.clipboard.writeText) await navigator.clipboard.writeText(text);
    else { const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); }
    Toast.show('Copied to clipboard','success');
  } catch (e) { console.error(e); Toast.show('Copy failed','error'); }
}

function clearResult(){
  document.getElementById('resultText').value = '';
  const codeEl = document.getElementById('resultCode');
  codeEl.textContent = '';
  if (window.Prism && typeof Prism.highlightElement === 'function') Prism.highlightElement(codeEl);
  document.getElementById('copyBtn').disabled = true;
}

function downloadCurrent(){
  const codeEl = document.getElementById('resultCode');
  const content = (codeEl && codeEl.textContent && codeEl.textContent.trim()) ? codeEl.textContent : document.getElementById('resultText').value;
  if (!content || !content.trim()) { Toast.show('Nothing to download','error'); return; }
  const blob = new Blob([content], { type: 'application/json;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a'); a.href = url;
  const ts = new Date().toISOString().replace(/[:\.]/g,'-');
  a.download = `skeletons_${ts}.json`;
  document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
}
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>