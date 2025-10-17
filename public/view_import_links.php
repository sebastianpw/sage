<?php
// view_import_links.php

require "error_reporting.php";
require "eruda_var.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Import Tabs";
ob_start();
?>

<div class="view-container" style="padding:12px;">
<a style="font-size: 1.3em;" href="dashboard.php" class="back-link" title="Dashboard">🔮</a>
  <h2>Import Tabs (Paste links)</h2>

  <form id="importForm" onsubmit="return false;">
    <textarea id="rawInput" rows="8" placeholder="Paste your link list here (Chrome export lines or URLs)..." style="width:100%; box-sizing:border-box; font-family:monospace;"></textarea>

    <div class="controls" style="margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      <label class="small" style="font-size:0.9em; color:#666;">
        Parent ID:
        <input type="number" id="parentId" value="999" style="width:96px; margin-left:6px;">
      </label>

      <label class="small" style="font-size:0.9em; color:#666;">
        <input type="checkbox" id="forceLocal"> Force local parse (skip LLM)
      </label>

      <button id="analyzeBtn" type="button" style="padding:8px 12px;">Analyze</button>
      <button id="applyBtn" type="button" disabled style="padding:8px 12px;">Apply Import</button>

      <span id="status" class="small" style="font-size:0.9em; color:#666;"></span>
    </div>
  </form>

  <div id="preview" style="margin-top:12px; background:#fff; padding:12px; border-radius:6px; box-shadow:0 1px 0 rgba(0,0,0,0.04);"></div>
</div>

<!-- Inline script uses the app's jQuery -->
<?= $spw->getJquery(); ?>

<script>
function escapeHtml(s){ return $('<div/>').text(s).html(); }

$('#analyzeBtn').on('click', function(){
  const raw = $('#rawInput').val();
  if (!raw || !raw.trim()) { alert('Paste something first.'); return; }
  $('#status').text('Analyzing...');
  $('#preview').html('Analyzing — please wait...');
  $.post('import_links.php', {
      action: 'analyze',
      raw: raw,
      parent_id: $('#parentId').val(),
      force_local_parse: $('#forceLocal').is(':checked') ? '1' : '0'
    })
    .done(function(res){
      if (!res.ok) {
        $('#preview').html('<div style="color:red">Error: ' + escapeHtml(res.error || 'unknown') + '</div>');
        $('#status').text('');
        return;
      }
      // Build editable preview table
      const items = res.items || [];
      let html = '';
      if (items.length === 0) {
        html = '<div class="small">No items detected.</div>';
      } else {
        html += '<table style="width:100%; border-collapse:collapse;"><thead><tr><th style="width:40px">Add</th><th style="width:60px">Pos</th><th>Name</th><th>Href</th></tr></thead><tbody>';
        items.forEach((it, idx) => {
          const name = it.name || '';
          const href = it.href || '';
          const pos = (typeof it.suggested_position !== 'undefined') ? it.suggested_position : idx;
          html += '<tr data-idx="'+idx+'">';
          html += '<td><input type="checkbox" class="include" checked></td>';
          html += '<td><input type="number" class="pos" value="'+escapeHtml(pos)+'" style="width:60px"></td>';
          html += '<td><input type="text" class="name" value="'+escapeHtml(name)+'" style="width:100%"></td>';
          html += '<td><input type="text" class="href" value="'+escapeHtml(href)+'" style="width:100%"></td>';
          html += '</tr>';
        });
        html += '</tbody></table>';
      }

      if (res.skipped && res.skipped.length) {
        html += '<div style="margin-top:12px"><strong>Skipped:</strong><ul>';
        res.skipped.forEach(s => {
          html += '<li>' + escapeHtml((s.line || '') + ' — ' + (s.reason || '')) + '</li>';
        });
        html += '</ul></div>';
      }

      $('#preview').html(html);
      $('#applyBtn').prop('disabled', items.length === 0).data('raw_llm', res.raw_llm || null);
      $('#status').text('Analyzed ' + (items.length) + ' items.');
    })
    .fail(function(xhr){
      $('#preview').html('<div style="color:red">Request failed</div>');
      $('#status').text('');
    });
});

$('#applyBtn').on('click', function(){
  const rows = $('#preview tbody tr');
  if (!rows.length) { alert('Nothing to apply.'); return; }

  const items = [];
  rows.each(function(){
    const $r = $(this);
    const include = $r.find('.include').is(':checked');
    if (!include) return;
    const name = $r.find('.name').val().trim();
    const href = $r.find('.href').val().trim();
    const pos = parseInt($r.find('.pos').val(), 10) || 0;
    if (!href) return;
    items.push({ name: name, href: href, level: 2, parent_id: parseInt($('#parentId').val(),10) || 999, suggested_position: pos });
  });

  if (!items.length) { alert('No items selected for insertion.'); return; }

  if (!confirm('Insert ' + items.length + ' items into DB?')) return;

  $('#applyBtn').prop('disabled', true).text('Applying...');
  $('#status').text('Applying...');

  $.post('import_links.php', { action: 'apply', items: JSON.stringify(items), table: 'pages_dashboard' })
    .done(function(res){
      if (!res.ok) {
        alert('Import failed: ' + (res.error || 'unknown'));
        $('#applyBtn').prop('disabled', false).text('Apply Import');
        $('#status').text('');
        return;
      }
      alert('Inserted: ' + (res.inserted || 0));
      location.reload();
    })
    .fail(function(xhr){
      alert('Server error during import');
      $('#applyBtn').prop('disabled', false).text('Apply Import');
      $('#status').text('');
    });
});
</script>

<?php
// capture buffer and render inside your app layout, appending eruda
$content = ob_get_clean();
$content .= $eruda;
$spw->renderLayout($content, $pageTitle);
