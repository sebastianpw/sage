<?php
// tabs.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; // provides $pdo, $mysqli, etc.
?>

  <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
  <?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
  <?php else: ?>
    <script src="/vendor/sortable/Sortable.min.js"></script>
  <?php endif; ?>

  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body { }
    h1 { margin-bottom: 12px; }

    ul#tree {
	max-width: 900px;
	padding: 0;
        margin-top: 0 !important;
    }

    ul#tree, ul#tree ul { list-style-type: none; padding-left: 0; margin: 6px 0; }
    #tree > li {
      margin: 8px 0;
      padding: 14px 30px 14px 30px; /* room for toggle on left + handle on right */
      border-radius: 6px;
      background: #f0f0f0;
      box-shadow: 0 0 0 1px rgba(0,0,0,0.02) inset;
      position: relative;
    }
    li { margin: 3px 0; }

    /* Toggle - fixed top-left, single .arrow element (no ::before) */
    .toggle {
      position: absolute;
      left: 10px;
      top: 4px;                 /* pinned near the top of the li */
      width: 34px;
      height: 34px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 20;
      user-select: none;
      border-radius: 6px;
      -webkit-tap-highlight-color: rgba(0,0,0,0);
    }
    .toggle .arrow {
      transition: transform .15s ease;
      font-size: 1.3em;
      color: #0056b3;
      pointer-events: none;
    }
    .open > .toggle .arrow { transform: rotate(90deg); }

    /* Category text pushed right so toggle never overlaps */
    .category {
      display: inline-block;
      margin-left: 10px; /* ensures text starts to the right of the toggle */
      font-weight: 700;
      color: #0056b3;
      user-select: none;
      padding-right: 10px;
    }

    /* Editing state */
    .category.editing {
      outline: 2px solid rgba(0,86,179,0.12);
      background: #fff7e6;
      padding: 2px 6px;
      border-radius: 4px;
    }

    .nested { display: none; margin-top: 6px; }
    .open > .nested { display: block; }

    a { text-decoration: none; color: #333; }
    a:hover { color: #007bff; text-decoration: underline; }

    .drag-handle {
      position: absolute; right: 10px; top: 22px;
      transform: translateY(-50%);
      cursor: move; color: #999; font-size: 14px;
      user-select: none; z-index: 10;
    }

    .sortable-ghost { opacity: 0.4; }

    /* small success / error hint */
    .save-hint {
      display:inline-block;
      margin-left:8px;
      font-size:0.9em;
      color: #2b8a3e;
      opacity: 0;
      transition: opacity .35s ease;
    }
    .save-hint.show { opacity: 1; }

    @media (max-width:600px){ body { } }
  </style>


  <?php
  // Fetch level 1 items with their children (level 2).
  $stmt1 = $pdoSys->prepare("SELECT id, name, href, position FROM pages_dashboard WHERE level = 1 ORDER BY position ASC, id ASC");
  $stmt1->execute();
  $tops = $stmt1->fetchAll(PDO::FETCH_ASSOC);

  $childStmt = $pdoSys->prepare("SELECT id, name, href, position, parent_id FROM pages_dashboard WHERE level = 2 AND parent_id = :pid ORDER BY position ASC, id ASC");
  ?>


  <ul id="tree" aria-label="Tab archive">
    <?php foreach ($tops as $top):
      $tid = (int)$top['id'];
      $tname = htmlspecialchars($top['name'] ?? '');
    ?>
      <li data-id="<?= $tid ?>" role="group" aria-labelledby="cat-<?= $tid ?>">
        <span class="toggle" title="Toggle" aria-controls="cat-<?= $tid ?>"><span class="arrow">▶</span></span>

        <!-- level 1 title: double-click to edit -->
        <span id="cat-<?= $tid ?>" class="category" data-id="<?= $tid ?>" tabindex="0" title="Double-click to edit"><?= $tname ?></span>
        <span class="save-hint" aria-hidden="true">Saved</span>

        <span class="drag-handle" title="Drag to reorder" aria-hidden="true">☰</span>

        <ul class="nested" role="list">
          <?php
          $childStmt->execute([':pid' => $tid]);
          $children = $childStmt->fetchAll(PDO::FETCH_ASSOC);
          foreach ($children as $c):
            $cid = (int)$c['id'];
            $cname = htmlspecialchars($c['name'] ?? '');
            $chref = trim((string)$c['href']);
            if ($chref !== ''):
              echo '<li data-id="'.$cid.'"><a href="'.htmlspecialchars($chref).'" target="_blank" rel="noopener noreferrer">'.$cname.'</a></li>';
            else:
              echo '<li data-id="'.$cid.'"><span>'.$cname.'</span></li>';
            endif;
          endforeach;
          ?>
        </ul>
      </li>
    <?php endforeach; ?>
  </ul>

<script>
$(function() {
  const STATE_KEY = "tabTreeState_v1";
  const AJAX_URL = 'tabs_ajax.php';

  // restore open/closed state from localStorage (UI-only)
  let savedState = {};
  try { savedState = JSON.parse(localStorage.getItem(STATE_KEY) || "{}"); } catch(e){ savedState = {}; }
  $('#tree > li[data-id]').each(function(){
    const id = $(this).data('id') + '';
    if (savedState[id]) $(this).addClass('open');
  });

  // toggle
  $('#tree').on('click', '.toggle', function(e) {
    const $li = $(this).closest('li[data-id]');
    const id = $li.data('id') + '';
    $li.toggleClass('open');
    savedState[id] = $li.hasClass('open');
    try { localStorage.setItem(STATE_KEY, JSON.stringify(savedState)); } catch(e){}
    e.stopPropagation();
  });

  // Clicking category toggles (keeps previous behavior)
  $('#tree').on('click', '.category', function(e) {
    // if editing, ignore clicks
    if ($(this).hasClass('editing')) return;
    $(this).siblings('.toggle').trigger('click');
  });

  $('#tree').on('click', 'a', function(e){ e.stopPropagation(); });

  // helper: build nested map to send to server
  function buildNestedMap() {
    const nested = [];
    $('#tree > li[data-id]').each(function(){
      const parentId = parseInt($(this).data('id'), 10);
      $(this).find('.nested > li[data-id]').each(function(pos){
        nested.push({
          id: parseInt($(this).data('id'), 10),
          parent_id: parentId,
          position: parseInt(pos, 10)
        });
      });
    });
    return nested;
  }

  // helper: send top order
  function sendTopOrder(orderArray) {
    return $.post(AJAX_URL, { action: 'save_top_order', order: JSON.stringify(orderArray) });
  }

  // helper: send nested mapping
  function sendNested(nestedArray) {
    return $.post(AJAX_URL, { action: 'save_nested', nested: JSON.stringify(nestedArray) });
  }

  // helper: update title (level 1)
  function sendUpdateTitle(id, title) {
    return $.post(AJAX_URL, { action: 'update_title', id: id, title: title });
  }

  // Sortable: top-level categories
  Sortable.create(document.getElementById('tree'), {
    animation: 150,
    handle: '.drag-handle',
    draggable: 'li[data-id]',
    ghostClass: 'sortable-ghost',
    onEnd: function() {
      // collect order
      const order = [];
      $('#tree > li[data-id]').each(function(i){
        order.push(parseInt($(this).data('id'), 10));
      });
      // update UI local order
      try { localStorage.setItem('tabTreeOrder_v1', JSON.stringify(order)); } catch(e){}
      // send to server: update positions for level=1
      sendTopOrder(order)
        .fail(function(xhr){ console.warn('save_top_order failed', xhr); })
        .done(function(resp){ /* optional success handling */ });

      // Also update nested mapping (positions/parent)
      const nested = buildNestedMap();
      if (nested.length) {
        sendNested(nested).fail(function(xhr){ console.warn('save_nested after top failed', xhr); });
      }
    }
  });

  // Sortable: nested lists (allow moving across categories)
  function initNestedSortables() {
    document.querySelectorAll('#tree > li .nested').forEach((ul) => {
      if (ul._sortableInstance) return;
      ul._sortableInstance = Sortable.create(ul, {
        group: 'nested',
        animation: 120,
        draggable: 'li[data-id]',
        ghostClass: 'sortable-ghost',
        onAdd: function () { // moved across lists
          const nested = buildNestedMap();
          sendNested(nested).fail(function(xhr){ console.warn('save_nested (onAdd) failed', xhr); });
        },
        onUpdate: function () { // reordered within same list
          const nested = buildNestedMap();
          sendNested(nested).fail(function(xhr){ console.warn('save_nested (onUpdate) failed', xhr); });
        },
        onRemove: function () { /* handled by onAdd of target */ }
      });
    });
  }
  initNestedSortables();

  // Inline edit: double-click to edit level-1 category names.
  // Single click still toggles; double-click enables editing.
  $('#tree').on('dblclick', '.category', function(e) {
    const $span = $(this);
    if ($span.hasClass('editing')) return;
    const id = $span.data('id') || $span.attr('data-id') || $span.attr('id')?.replace('cat-', '') ;
    const oldText = $span.text();
    $span.addClass('editing');
    $span.attr('contenteditable', 'true');
    $span.focus();

    // select all text
    if (window.getSelection && document.createRange) {
      const range = document.createRange();
      range.selectNodeContents($span.get(0));
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
    }

    // when Enter pressed, blur (save)
    $span.off('keydown.inlineedit').on('keydown.inlineedit', function(ev){
      if (ev.key === 'Enter') {
        ev.preventDefault();
        $span.blur();
      }
      // Escape: cancel
      if (ev.key === 'Escape') {
        ev.preventDefault();
        $span.text(oldText);
        $span.blur();
      }
    });

    // on blur - save or revert
    $span.off('blur.inlineedit').on('blur.inlineedit', function(){
      const newText = $span.text().trim();
      $span.removeAttr('contenteditable').removeClass('editing');
      $span.off('.inlineedit');
      if (newText === '') {
        // do not allow empty names
        $span.text(oldText);
        showHint($span, 'Name cannot be empty', true);
        return;
      }
      if (newText === oldText) {
        // no change
        return;
      }

      // Optimistic UI already changed. Send update.
      sendUpdateTitle(id, newText)
        .done(function(resp){
          if (resp && resp.ok) {
            showHint($span, 'Saved', false);
          } else {
            // server rejected
            $span.text(oldText);
            showHint($span, resp && resp.error ? resp.error : 'Save failed', true);
          }
        })
        .fail(function(xhr){
          $span.text(oldText);
          showHint($span, 'Save failed', true);
        });
    });
  });

  // small hint function next to category (shows for 1.5s)
  function showHint($categorySpan, text, isError) {
    const $hint = $categorySpan.siblings('.save-hint');
    if ($hint.length === 0) return;
    $hint.text(text).toggleClass('show', true);
    $hint.css('color', isError ? '#c62828' : '#2b8a3e');
    setTimeout(function(){ $hint.removeClass('show'); }, 1500);
  }

  // keyboard-accessible handle (prevent it from stealing focus)
  $(document).on('keydown', '.drag-handle', function(e){ e.stopPropagation(); });
});
</script>


<div style="margin-top: 150px;"> </div>
