<?php
// view_prompt_matrix.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Prompt Matrix";
ob_start();

// read GET params
$entityType = isset($_GET['entity_type']) ? (string)$_GET['entity_type'] : '';
$entityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;
$currentMatrixId = isset($_GET['matrix_id']) ? (int)$_GET['matrix_id'] : 0;

// basic entity_type validation (allow letters, numbers, underscore)
if ($entityType === '' || !preg_match('/^[a-z0-9_]+$/i', $entityType)) {
    echo "<div class='notice'>Missing or invalid entity_type in URL</div>";
    require "floatool.php";
    $content = ob_get_clean();
    $content .= $eruda;
    $spw->renderLayout($content, $pageTitle);
    exit;
}

if ($entityId <= 0) {
    echo "<div class='notice'>Missing entity_id in URL</div>";
    require "floatool.php";
    $content = ob_get_clean();
    $content .= $eruda;
    $spw->renderLayout($content, $pageTitle);
    exit;
}

// fetch the entity (safe table name usage)
$stmt = $pdo->prepare("SELECT id, description FROM `" . $entityType . "` WHERE id = ?");
$stmt->execute([$entityId]);
$entity = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$entity) {
    echo "<div class='notice'>Prompt entity not found</div>";
    $content = ob_get_clean();
    require "floatool.php";
    $content .= $eruda;
    $spw->renderLayout($content, $pageTitle);
    exit;
}

// fetch matrices for this entity
$stmt = $pdo->prepare("
  SELECT id, additions_count, total_combinations, created_at, updated_at
  FROM prompt_matrix
  WHERE entity_type = ? AND entity_id = ?
  ORDER BY created_at DESC
");
$stmt->execute([$entityType, $entityId]);
$matrices = $stmt->fetchAll(PDO::FETCH_ASSOC);
$matricesJson = json_encode($matrices, JSON_UNESCAPED_UNICODE);
$currentMatrixId = (int)$currentMatrixId;
?>

<style>
/* Minimal mobile-first styles */
body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; margin:0; padding:0; color:#111; background:#fff; }
.container { padding:12px; max-width:980px; margin:0 auto; }
.header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px; }
.h1 { font-size:1.1rem; font-weight:600; }
.grid { display:grid; grid-template-columns: 1fr; gap:12px; }
@media(min-width:880px){ .grid { grid-template-columns: 320px 1fr; } }
.card { border:1px solid #eee; padding:10px; border-radius:8px; background:#fff; box-shadow:0 1px 0 rgba(0,0,0,0.02); }
.small { font-size:0.9rem; color:#666; }
.slot-title { font-weight:600; margin-bottom:6px; }
.variants { display:flex; flex-wrap:wrap; gap:8px; }
.variant { padding:6px 8px; border-radius:18px; background:#f6f6f6; border:1px solid #e9e9e9; cursor:pointer; font-size:0.9rem; }
.variant.active { background:#111; color:#fff; border-color: #111; }
.btn { display:inline-block; padding:8px 12px; border-radius:6px; background:#111; color:#fff; text-decoration:none; cursor:pointer; }
.btn.ghost { background:transparent; color:#111; border:1px solid #ddd; }
.row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.input { padding:8px; border-radius:6px; border:1px solid #ddd; width:100%; box-sizing:border-box; }
.small-note { font-size:0.85rem; color:#666; margin-top:6px; }
.preview-table { width:100%; border-collapse:collapse; margin-top:8px; }
.preview-table th, .preview-table td { border-bottom:1px solid #eee; padding:8px; text-align:left; font-size:0.95rem; }
.pager { display:flex; gap:8px; margin-top:8px; }
.notice { padding:12px; background:#fff0d9; border:1px solid #ffdca8; border-radius:8px; margin-bottom:10px; }
.footer-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:12px; }
.matrix-selector { min-width:220px; margin-right:8px; }
@media(max-width:880px){ .matrix-selector { width:100%; margin-bottom:8px; } }
</style>

<div class="container">
  <div class="header">
    <div>
      <div class="h1">Prompt Ideation — #<?=htmlspecialchars($entity['id'])?></div>
      <div class="small"><?=htmlspecialchars(substr($entity['description'] ?? '',0,200))?></div>
    </div>
    <div class="row" style="align-items:center;">
      <select id="matrixSelector" class="input matrix-selector">
        <option value="0">+ Create new matrix</option>
        <?php foreach($matrices as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= ((int)$m['id'] === $currentMatrixId) ? 'selected' : '' ?>>
            Matrix #<?= (int)$m['id'] ?> — <?= (int)$m['total_combinations'] ?> combos — <?= htmlspecialchars(substr($m['created_at'],0,16)) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <button id="btnPreview" class="btn">Preview (dry-run)</button>
      <button id="btnCreateMatrix" class="btn">Create Matrix</button>
      <button id="btnUpdateMatrix" class="btn ghost" style="display:none;">Update Matrix</button>

      <!-- Import button and small status -->
      <button id="btnImportBlueprints" class="btn ghost" style="display:none; margin-left:6px;">Import → Blueprints</button>
      <span id="importStatus" class="small-note" style="margin-left:8px; display:inline-block;"></span>
    </div>
  </div>

  <div class="grid">
    <!-- left: additions & quick-create -->
    <div class="card" id="leftPanel">
      <div class="slot-title">Available additions</div>
      <div id="additionsList" class="small-note">Loading…</div>

      <hr/>

      <div class="slot-title">Quick add an addition</div>
      <div class="small-note">Create a new addition and attach to slot (it will be selected automatically).</div>
      <div style="margin-top:8px;">
        <select id="newSlot" class="input" style="margin-bottom:8px;">
          <option value="1">Slot 1</option>
          <option value="2">Slot 2</option>
          <option value="3">Slot 3</option>
          <option value="4">Slot 4</option>
          <option value="5">Slot 5</option>
        </select>
        <input id="newText" class="input" placeholder="New addition text (e.g. 'sunset')"/>
        <div style="margin-top:8px" class="row">
          <button id="btnAdd" class="btn">Add & select</button>
          <button id="btnAddFast" class="btn ghost">Add (no select)</button>
        </div>
      </div>
    </div>

    <!-- right: selection, preview and paging -->
    <div class="card" id="rightPanel">
      <div class="slot-title">Selected variants</div>
      <div id="selectedInfo" class="small-note">No selections yet</div>

      <hr/>

      <div class="slot-title">Preview (page)</div>
      <div class="small-note" id="previewMeta">No preview</div>

      <table class="preview-table" id="previewTable" style="display:none;">
        <thead><tr><th>#</th><th>Prompt</th></tr></thead>
        <tbody></tbody>
      </table>

      <div class="pager" id="pager" style="display:none;">
        <button id="prevPage" class="btn ghost">Prev</button>
        <div id="pageInfo" class="small-note" style="align-self:center;"></div>
        <button id="nextPage" class="btn ghost">Next</button>
      </div>

      <div class="footer-actions">
        <label class="small-note" style="align-self:center; margin-right:auto;">
          Page size:
          <select id="pageSize">
            <option>10</option>
            <option>25</option>
            <option>50</option>
          </select>
        </label>
        <label class="small-note" style="align-self:center; margin-right:8px;">
          Dry-run:
          <input type="checkbox" id="checkboxDry" checked />
        </label>
      </div>
    </div>
  </div>
</div>

<?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
<script>
const entityType = <?= json_encode($entityType) ?>;
const entityId = <?= json_encode($entityId) ?>;
let availableMatrices = <?= $matricesJson ?>;
let currentMatrixId = <?= json_encode($currentMatrixId) ?>;
let additionsBySlot = {};   // { slot: [ {id, slot, description} ] }
let selected = {};          // { slot: Set(addition_id_or_temp) }
let previewState = { page: 1, pageSize: 10, total: 0 };

// update visibility for Import button based on currentMatrixId
function updateImportButtonVisibility() {
  if (currentMatrixId && currentMatrixId > 0) {
    $("#btnImportBlueprints").show();
    $("#btnUpdateMatrix").show();
  } else {
    $("#btnImportBlueprints").hide();
    $("#btnUpdateMatrix").hide();
    $("#importStatus").text('');
  }
}

// fetchAdditions now accepts optional matrixId and asks server for preselected mapping
function fetchAdditions(matrixId = 0) {
  $("#additionsList").text('Loading…');
  const params = { entity_type: entityType, entity_id: entityId };
  if (matrixId && Number.isInteger(matrixId) && matrixId>0) params.matrix_id = matrixId;

  $.getJSON("additions_get.php", params)
    .done(function(resp){
      if (!resp.success) { $("#additionsList").text(resp.error || 'Error'); return; }
      additionsBySlot = resp.data || {};
      // initialize selected from returned selected mapping if any
      selected = {};
      if (resp.selected) {
        for (const s in resp.selected) {
          if (!resp.selected.hasOwnProperty(s)) continue;
          const ids = resp.selected[s];
          selected[s] = new Set(ids.map(x => Number(x)));
        }
      }
      renderAdditions();

      // show/hide update & import buttons depending on matrixId
      currentMatrixId = matrixId && matrixId>0 ? matrixId : 0;
      updateImportButtonVisibility();
    }).fail(function(){ $("#additionsList").text('Server error'); });
}

function renderAdditions() {
  const $c = $("<div/>");
  const slots = Object.keys(additionsBySlot).sort((a,b)=>a-b);
  if (slots.length === 0) {
    $c.append("<div class='small-note'>No additions available. Use the Quick add box to create.</div>");
  }
  for (const slot of slots) {
    const arr = additionsBySlot[slot];
    const $slot = $("<div style='margin-bottom:10px' />");
    $slot.append("<div class='slot-title'>Slot "+slot+"</div>");
    const $variants = $("<div class='variants'></div>");
    for (const v of arr) {
      const id = v.id;
      const $btn = $(`<div class='variant' data-id='${id}' data-slot='${slot}'>${escapeHtml(v.description)}</div>`);
      if (selected[slot] && selected[slot].has(id)) $btn.addClass('active');
      $btn.on('click', function(){
        toggleSelection(slot, id, v.description);
        $(this).toggleClass('active');
      });
      $variants.append($btn);
    }
    $slot.append($variants);
    $c.append($slot);
  }
  $("#additionsList").empty().append($c);
  updateSelectedInfo();
}

function toggleSelection(slot, id, text) {
  if (!selected[slot]) selected[slot] = new Set();
  if (selected[slot].has(id)) {
    selected[slot].delete(id);
  } else {
    selected[slot].add(id);
  }
  updateSelectedInfo();
}

function updateSelectedInfo() {
  const slots = Object.keys(selected).filter(s=>selected[s] && selected[s].size>0).sort((a,b)=>a-b);
  if (slots.length === 0) {
    $("#selectedInfo").text("No selections yet");
    return;
  }
  const parts = [];
  for (const slot of slots) {
    const ids = Array.from(selected[slot]);
    const texts = ids.map(id => {
      if (id < 0) return "[free] " + id;
      const arr = additionsBySlot[slot] || [];
      const found = arr.find(x=>x.id==id);
      return found ? found.description : ("#"+id);
    });
    parts.push("Slot "+slot+": " + texts.join(", "));
  }
  $("#selectedInfo").text(parts.join(" | "));
}

// quick-add handlers
$("#btnAdd, #btnAddFast").on('click', function(e){
  e.preventDefault();
  const text = $("#newText").val().trim();
  const slot = parseInt($("#newSlot").val(),10) || 1;
  if (!text) { alert("Please enter text"); return; }
  const fast = $(this).attr('id') === 'btnAddFast';
  $.post("addition_create.php", { entity_type: entityType, entity_id: entityId, slot: slot, description: text })
    .done(function(res){
      if (!res || !res.success) { alert(res && res.error ? res.error : "Server error"); return; }
      // refresh list (respect currentMatrixId)
      fetchAdditions(currentMatrixId || 0);
      $("#newText").val("");
      if (!fast) {
        const newid = res.id;
        if (!selected[slot]) selected[slot] = new Set();
        selected[slot].add(newid);
        updateSelectedInfo();
        setTimeout(renderAdditions, 200);
      }
    }).fail(function(){ alert("Server error"); });
});

// preview / pagination
function doPreview(page = 1) {
  const pageSize = parseInt($("#pageSize").val(),10) || 10;
  const selectedObj = {};
  for (const s in selected) {
    if (selected[s] && selected[s].size>0) selectedObj[s] = Array.from(selected[s]);
  }
  if (Object.keys(selectedObj).length === 0) {
    alert("No variants selected");
    return;
  }
  $("#previewMeta").text("Loading preview…");
  $("#previewTable").hide();
  $("#pager").hide();

  $.ajax({
    url: "matrix_preview.php",
    method: "POST",
    dataType: "json",
    data: {
      entity_type: entityType,
      entity_id: entityId,
      selected: JSON.stringify(selectedObj),
      page: page,
      page_size: pageSize
    }
  }).done(function(resp){
    if (!resp.success) { alert(resp.error); $("#previewMeta").text('Error'); return; }
    previewState.page = resp.page;
    previewState.pageSize = resp.page_size;
    previewState.total = resp.total;
    $("#previewMeta").text("Showing page "+resp.page+" of "+Math.ceil(resp.total/resp.page_size) + " — total combinations: " + resp.total);
    const $tbody = $("#previewTable tbody").empty();
    resp.rows.forEach(function(r, idx){
      $tbody.append("<tr><td>"+( (resp.page-1)*resp.page_size + idx + 1 )+"</td><td><code>"+escapeHtml(r.prompt)+"</code></td></tr>");
    });
    $("#previewTable").show();
    $("#pager").show();
    $("#pageInfo").text("Page "+resp.page+" / "+Math.max(1, Math.ceil(resp.total/resp.page_size)));
  }).fail(function(resp){ console.log(resp); alert("Server error"); $("#previewMeta").text("Server error"); });
}

$("#btnPreview").on('click', function(){ doPreview(1); });
$("#prevPage").on('click', function(){
  if (previewState.page > 1) doPreview(previewState.page - 1);
});
$("#nextPage").on('click', function(){
  const last = Math.max(1, Math.ceil(previewState.total / previewState.pageSize));
  if (previewState.page < last) doPreview(previewState.page + 1);
});

// create matrix (existing endpoint)
$("#btnCreateMatrix").on('click', function(){
  if (!confirm("Create matrix from current selections? This will persist the matrix and snapshot.")) return;
  const selectedObj = {};
  for (const s in selected) {
    if (selected[s] && selected[s].size>0) selectedObj[s] = Array.from(selected[s]);
  }
  $.ajax({
    url: "matrix_create.php",
    method: "POST",
    dataType: "json",
    data: {
      entity_type: entityType,
      entity_id: entityId,
      selected: JSON.stringify(selectedObj)
    }
  }).done(function(resp){
    if (!resp.success) { alert(resp.error); return; }
    alert("Matrix created id="+resp.matrix_id+" total combinations="+resp.total_combinations);
    // reload page to show new matrix in selector
    location.reload();
  }).fail(function(resp){ console.log(resp); alert("Server error"); });
});

// update existing matrix
$("#btnUpdateMatrix").on('click', function(){
  if (!currentMatrixId || currentMatrixId <= 0) { alert("No matrix selected to update"); return; }
  if (!confirm("Update matrix "+currentMatrixId+" with current selections? This will replace the matrix mapping.")) return;

  const selectedObj = {};
  for (const s in selected) {
    if (selected[s] && selected[s].size>0) selectedObj[s] = Array.from(selected[s]);
  }
  $.ajax({
    url: "matrix_update.php",
    method: "POST",
    dataType: "json",
    data: {
      matrix_id: currentMatrixId,
      selected: JSON.stringify(selectedObj)
    }
  }).done(function(resp){
    if (!resp.success) { alert(resp.error); return; }
    alert("Matrix updated id="+resp.matrix_id+" total combinations="+resp.total_combinations);
    location.reload();
  }).fail(function(resp){ console.log(resp); alert("Server error"); });
});

// import to blueprints
$("#btnImportBlueprints").on('click', function(){
  if (!currentMatrixId || currentMatrixId <= 0) { alert("No matrix selected to import"); return; }
  if (!confirm("Import matrix " + currentMatrixId + " into blueprints? This will create all combinations now.")) return;

  // UI lock
  $("#btnImportBlueprints, #btnCreateMatrix, #btnUpdateMatrix, #btnPreview").prop('disabled', true);
  $("#importStatus").text('Importing… please wait');

  $.ajax({
    url: "matrix2blueprints.php",
    method: "POST",
    dataType: "json",
    data: { matrix_id: currentMatrixId },
    timeout: 120000 // long timeout for big imports
  }).done(function(resp){
    if (!resp || !resp.success) {
      const err = (resp && resp.error) ? resp.error : "Unknown server error";
      $("#importStatus").text('Error: ' + err);
      alert("Import failed: " + err);
    } else {
      $("#importStatus").text("Imported: " + resp.total_combinations + " entries.");
      alert("Import finished — created " + resp.total_combinations + " blueprint entries.");
      // optionally reload to refresh lists
      // location.reload();
    }
  }).fail(function(jqxhr, status, err){
    let msg = err || status || "Server error";
    try {
      const txt = jqxhr.responseText;
      const j = JSON.parse(txt);
      if (j && j.error) msg = j.error;
    } catch(e) { /* ignore */ }
    $("#importStatus").text('Error: ' + msg);
    alert("Import failed: " + msg);
  }).always(function(){
    $("#btnImportBlueprints, #btnCreateMatrix, #btnUpdateMatrix, #btnPreview").prop('disabled', false);
    setTimeout(() => updateImportButtonVisibility(), 200);
  });
});

// matrix selector changed
$("#matrixSelector").on('change', function(){
  const mid = parseInt($(this).val(), 10) || 0;
  currentMatrixId = mid;
  fetchAdditions(currentMatrixId);
});

// HTML escape helper
function escapeHtml(s){ if (s==null) return ''; return String(s).replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); }

$(function(){
  if (currentMatrixId && currentMatrixId > 0) fetchAdditions(currentMatrixId);
  else fetchAdditions(0);
  // ensure import button state updated on load
  updateImportButtonVisibility();
});
</script>

<?php
require "floatool.php";
$content = ob_get_clean();
$content .= $eruda;
$spw->renderLayout($content, $pageTitle);
