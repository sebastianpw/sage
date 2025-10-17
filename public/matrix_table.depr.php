<?php
// matrix_table.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Entity Matrices";
ob_start();

// read GET params
$entityType = isset($_GET['entity_type']) ? (string)$_GET['entity_type'] : '';
$entityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;

if ($entityId <= 0 || !$entityType) {
    echo "<div class='notice'>Missing entity_type/entity_id in URL</div>";
    require "floatool.php";
    $content = ob_get_clean();
    $content .= $eruda;
    $spw->renderLayout($content, $pageTitle);
    exit;
}

// fetch entity description
$stmt = $pdo->prepare("SELECT id, description FROM `" . $entityType . "` WHERE id = ?");
$stmt->execute([$entityId]);
$entity = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$entity) {
    echo "<div class='notice'>Entity not found</div>";
    $content = ob_get_clean();
    require "floatool.php";
    $content .= $eruda;
    $spw->renderLayout($content, $pageTitle);
    exit;
}

// fetch matrices for this entity
$stmt = $pdo->prepare("
    SELECT id, additions_snapshot, additions_count, total_combinations, created_at, updated_at
    FROM prompt_matrix
    WHERE entity_type = ? AND entity_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$entityType, $entityId]);
$matrices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// pass entity info to JS
$jsEntityType = json_encode($entityType);
$jsEntityId = json_encode($entityId);
?>

<style>
body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; margin:0; padding:0; color:#111; background:#fff; }
.container { padding:12px; max-width:1100px; margin:0 auto; }
.h1 { font-size:1.2rem; font-weight:600; margin-bottom:12px; }
.table { width:100%; border-collapse:collapse; margin-top:12px; }
.table th, .table td { border-bottom:1px solid #eee; padding:8px; text-align:left; font-size:0.95rem; vertical-align:top; }
.table th { background:#f8f8f8; }
.btn { display:inline-block; padding:6px 10px; border-radius:6px; background:#111; color:#fff; text-decoration:none; cursor:pointer; font-size:0.85rem; margin-right:6px; }
.btn.ghost { background:transparent; color:#111; border:1px solid #ddd; }
.small-note { font-size:0.85rem; color:#666; }
pre { white-space:pre-wrap; word-wrap:break-word; max-height:120px; overflow:auto; margin:0; padding:0; }
@media(max-width:880px){ .table th:nth-child(3), .table td:nth-child(3) { display:none; } }
.actions { display:flex; gap:6px; flex-wrap:wrap; }
</style>

<div class="container">
    <div class="h1">Matrices for entity #<?=htmlspecialchars($entity['id'])?> — <?=htmlspecialchars(substr($entity['description'] ?? '',0,200))?></div>

    <?php if(empty($matrices)): ?>
        <div class="notice">No matrices found for this entity.</div>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Matrix ID</th>
                <th>Created / Updated</th>
                <th class="snapshot-col">Additions snapshot</th>
                <th>Total combinations</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($matrices as $m): ?>
            <tr>
                <td><?=htmlspecialchars($m['id'])?></td>
                <td>
                    <div class="small-note">Created: <?=$m['created_at']?></div>
                    <div class="small-note">Updated: <?=$m['updated_at']?></div>
                </td>
                <td class="snapshot-col">
                    <pre><?=htmlspecialchars(substr($m['additions_snapshot'],0,300))?><?=strlen($m['additions_snapshot'])>300?'…':''?></pre>
                </td>
                <td><?=htmlspecialchars($m['total_combinations'])?></td>
                <td>
                    <div class="actions">
                        <button class="btn btnPreview" data-matrix="<?=htmlspecialchars($m['id'])?>">Preview</button>
                        <button class="btn btnImport" data-matrix="<?=htmlspecialchars($m['id'])?>">Import</button>
                        <button class="btn btnEdit" data-matrix="<?=htmlspecialchars($m['id'])?>">Edit</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
const entityType = <?= $jsEntityType ?>;
const entityId = <?= $jsEntityId ?>;

$(function(){
    $(".btnPreview").on("click", function(){
        const matrixId = $(this).data("matrix");
        // simple preview: open view_prompt_matrix in preview mode (no edit)
        const url = "view_prompt_matrix.php?entity_type=" + encodeURIComponent(entityType)
            + "&entity_id=" + encodeURIComponent(entityId)
            + "&matrix_id=" + encodeURIComponent(matrixId);
        // open in new tab to keep matrix table accessible
        window.open(url, "_blank");
    });

    $(".btnImport").on("click", function(){
        const matrixId = $(this).data("matrix");
        if(!confirm("Import matrix "+matrixId+" into blueprints?")) return;

        $.post("matrix2blueprints.php", { matrix_id: matrixId })
        .done(function(resp){
            if(!resp || !resp.success){
                alert(resp && resp.error ? resp.error : "Server error");
                return;
            }
            alert("Matrix imported! Created " + resp.total_combinations + " blueprint entries.");
        })
        .fail(function(){ alert("Server error"); });
    });

    $(".btnEdit").on("click", function(){
        const matrixId = $(this).data("matrix");
        // navigate to view_prompt_matrix.php with matrix preselected for editing
        const url = "view_prompt_matrix.php?entity_type=" + encodeURIComponent(entityType)
            + "&entity_id=" + encodeURIComponent(entityId)
            + "&matrix_id=" + encodeURIComponent(matrixId);
        // replace location to keep navigation flow
        window.location.href = url;
    });
});
</script>

<?php
require "floatool.php";
$content = ob_get_clean();
$content .= $eruda;
$spw->renderLayout($content, $pageTitle);
