<?php
namespace App\Gallery;
abstract class AbstractGallery {

	public const URL_PREFIX = ""; //"https://sebastianpw.github.io/sg_showcase_01";
	public const SHOW_GEARMENU = true;

    protected \App\Core\SpwBase $spw;
    protected \mysqli $mysqli;
    protected array $filters = [];
    protected int $page = 1;
    protected int $limit = 12;
    protected int $offset = 0;
    protected bool $gridOn = false;
    protected string $albumClass = 'album';
    protected array $filterOptions = [];

    public function __construct() {
        $this->spw = \App\Core\SpwBase::getInstance();

        $this->mysqli = $this->spw->getMysqli();
        $this->page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $this->limit = $this->getLimit();
        $this->offset = ($this->page - 1) * $this->limit;
        // default: grid on unless explicit grid=0
        $this->gridOn = !isset($_GET['grid']) || $_GET['grid'] !== '0';
        $this->albumClass = $this->gridOn ? 'album grid' : 'album';
        $this->filters = $this->getFiltersFromRequest();
        $this->filterOptions = $this->getFilterOptions();
    }

    abstract protected function getFiltersFromRequest(): array;
    abstract protected function getFilterOptions(): array;
    abstract protected function getWhereClause(): string;
    abstract protected function getBaseQuery(): string;
    abstract protected function getCaptionFields(): array;
    abstract protected function getGalleryEntity(): string;
    abstract protected function getGalleryTitle(): string;
    abstract protected function getToggleButtonLeft(): int;

    protected function getLimit(): int {
        return 12;
    }

    protected function getOrderBy(): string {
        return 'frame_id DESC'; // default
    }

    protected function fetchDistinct(string $column): array {
        $res = $this->mysqli->query("SELECT DISTINCT $column FROM v_gallery_" . $this->getGalleryEntity() . " ORDER BY $column ASC");
        $arr = [];
        while ($row = $res->fetch_assoc()) {
            $arr[] = $row[$column];
        }
        return $arr;
    }




// single item renderer (supports video with same-basename poster fallback)
protected function renderItem(array $row): string {
    // make sure load_root variables are available
    if (!isset($GLOBALS['PROJECT_ROOT']) || !isset($GLOBALS['FRAMES_ROOT'])) {
    }

    $fields = $this->getCaptionFields();
    // raw filename from DB (may be relative path like "frames_xxx/frame0001234.mp4")
    $fileRelRaw = $row['filename'] ?? '';
    $fileRelRaw = ltrim($fileRelRaw, "/");
    $fileRelEsc = htmlspecialchars($fileRelRaw);

    // compute pathinfo
    $pi = pathinfo($fileRelRaw);
    $dirname = $pi['dirname'] ?? '';
    $basename = $pi['filename'] ?? '';
    $extension = strtolower($pi['extension'] ?? '');

    $videoExts = ['mp4','webm','mov','ogg','m4v'];
    $isVideoCandidate = in_array($extension, $videoExts, true);

    // build candidate physical paths (prefer public path /public/<rel>)
    $publicBase = '';
    if (!empty(PROJECT_ROOT)) $publicBase = rtrim(PROJECT_ROOT, '/') . '/public/';

    $videoPhysical = $publicBase . $fileRelRaw;
    $videoExists = $isVideoCandidate && file_exists($videoPhysical);

    // Poster candidates with same basename
    $posterRel = null;
    $posterPhysical = null;
    $imgExtCandidates = ['jpg','jpeg','png','webp'];
    foreach ($imgExtCandidates as $iext) {
        $candRel = ($dirname !== '' && $dirname !== '.') ? ($dirname . '/' . $basename . '.' . $iext) : ($basename . '.' . $iext);
        $candPhys = $publicBase . $candRel;
        if (file_exists($candPhys)) {
            $posterRel = $candRel;
            $posterPhysical = $candPhys;
            break;
        }
    }

    // fallback logic:
    // - if DB says video but video file missing, and a poster exists => treat as image
    // - if DB says video and video exists, use poster if available, else a tiny inline SVG poster
    $isVideo = false;
    $imgSrcWeb = null; // web path for <img src="...">
    $linkHref = '/' . $fileRelRaw; // default link

    if ($isVideoCandidate && $videoExists) {
        $isVideo = true;
        // poster precedence: found posterRel -> use it, else small inline svg placeholder
        if ($posterRel) {
            $imgSrcWeb = '/' . ltrim($posterRel, '/');
        } else {
            // small inline SVG as data-uri poster
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360"><rect width="100%" height="100%" fill="#111"/><text x="50%" y="50%" fill="#8f8" font-size="20" font-family="sans-serif" text-anchor="middle" dy="0.35em">Video</text></svg>';
            $imgSrcWeb = 'data:image/svg+xml;utf8,' . rawurlencode($svg);
        }
        $linkHref = '/' . $fileRelRaw; // link to the video file (web path)
    } else {
        // not a usable video: prefer to show whatever image exists (posterRel) or fallback to DB filename itself
        if ($posterRel) {
            $imgSrcWeb = '/' . ltrim($posterRel, '/');
        } else {
            // If DB filename is an image extension, show that
            if (in_array($extension, ['jpg','jpeg','png','webp'])) {
                $imgSrcWeb = '/' . ltrim($fileRelRaw, '/');
            } else {
                // last resort: show DB filename (may be invalid but that's the previous behaviour)
                $imgSrcWeb = '/' . ltrim($fileRelRaw, '/');
            }
        }
        $isVideo = false;
        $linkHref = '/' . ltrim($imgSrcWeb, '/');
    }

    // sanitize again for output
    $imgSrcEsc = htmlspecialchars($imgSrcWeb);

    $prompt   = htmlspecialchars($row['prompt'] ?? '');
    $entity = $this->getGalleryEntity();
    $entityId = (int)($row['entity_id'] ?? 0);
    $frameId = (int)($row['frame_id'] ?? 0);

    $captionParts = [];
    if (!empty($row['prompt'])) {
        $captionParts[] = $prompt;
    }
    foreach ($fields as $label => $field) {
        if (!empty($row[$field])) {
            $captionParts[] = '<strong>' . htmlspecialchars($label) . ':</strong> ' . htmlspecialchars($row[$field]);
        }
    }
    $captionHtml = implode('<br>', $captionParts);

    ob_start();
    ?>
    <div class="img-wrapper" style="position: relative;" data-entity="<?= $entity ?>" data-entity-id="<?= $entityId ?>" data-frame-id="<?= $frameId ?>">
        <?php if ($isVideo): ?>
            <!-- video case: show poster image and a play overlay; anchor stores data-video -->
            <a href="<?= self::URL_PREFIX . htmlspecialchars($linkHref) ?>" class="play-video" data-video="<?= htmlspecialchars($linkHref) ?>" data-video-type="<?= htmlspecialchars($extension) ?>" title="<?= htmlspecialchars(strip_tags($captionHtml)) ?>">
                <img src="<?= self::URL_PREFIX . $imgSrcEsc ?>" alt="">
            </a>
            <div class="play-overlay" title="Play video" data-video="<?= self::URL_PREFIX . htmlspecialchars($linkHref) ?>">►</div>
        <?php else: ?>
            <!-- regular image with PhotoSwipe -->
            <a href="<?= htmlspecialchars($linkHref) ?>" 
               class="pswp-gallery-item" 
               data-pswp-src="<?= self::URL_PREFIX . htmlspecialchars($linkHref) ?>"
               data-pswp-width="1024" 
               data-pswp-height="1024"
               title="<?= htmlspecialchars(strip_tags($captionHtml)) ?>">
                <img src="<?= self::URL_PREFIX . $imgSrcEsc ?>" alt="">
            </a>
        <?php endif; ?>

	<!-- Gear icon -->
<?php if (static::SHOW_GEARMENU): ?><span class="gear-icon">&#9881;</span><?php endif; ?>

        <!-- Existing caption -->
        <div class="caption" style="max-height: 50px;">
            <button class="show-full-text" title="View full description">📖</button>
            <?= $prompt ?><br>
            <?php foreach ($fields as $label => $field): ?>
                <strong><?= htmlspecialchars($label) ?>:</strong> <?= htmlspecialchars($row[$field] ?? '') ?><br>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}










    // ------------------------------
    // render() — main output point
    // ------------------------------
    function render(): string {
	    require "entity_icons.php";

        // AJAX page fetch -> return JSON only
        if (isset($_GET['ajax_gallery']) && $_GET['ajax_gallery'] == '1') {
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = $this->limit;
            $offset = ($page - 1) * $limit;
            $where = $this->getWhereClause();
            $sql = "SELECT * FROM " . $this->getBaseQuery() . " $where ORDER BY " . $this->getOrderBy() . " LIMIT $limit OFFSET $offset";
            $result = $this->mysqli->query($sql);

            $itemsHtml = '';
            while ($row = $result->fetch_assoc()) {
                $itemsHtml .= $this->renderItem($row);
            }

            // Also compute totalPages
            $result_count = $this->mysqli->query("SELECT COUNT(*) AS total FROM " . $this->getBaseQuery() . " $where");
            $total_rows = $result_count->fetch_assoc()['total'];
            $total_pages = (int)ceil($total_rows / $this->limit);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'page' => $page,
                'itemsHtml' => $itemsHtml,
                'totalPages' => $total_pages
            ]);
            exit;
        }

        // Regular page rendering (initial full page)
        $where = $this->getWhereClause();

        // Count total rows
        $result_count = $this->mysqli->query("SELECT COUNT(*) AS total FROM " . $this->getBaseQuery() . " $where");
        $total_rows = $result_count->fetch_assoc()['total'];
        $total_pages = max(1, (int)ceil($total_rows / $this->limit));

        // Fetch current page
        $result = $this->mysqli->query("SELECT * FROM " . $this->getBaseQuery() . " $where ORDER BY " . $this->getOrderBy() . " LIMIT {$this->limit} OFFSET {$this->offset}");

        // Build initial items HTML (page 1 or current page)
        $itemsHtml = '';
        while ($row = $result->fetch_assoc()) {
            $itemsHtml .= $this->renderItem($row);
        }

        // Start HTML rendering
	ob_start();
        ?>
        <div class="album-container <?= $this->gridOn ? 'grid-view' : '' ?>">
            <div style="display: flex; align-items: center; margin-bottom: 20px; gap: 10px;">
                <a href="dashboard.php" title="Show Gallery" style="text-decoration: none; font-size: 24px; display: inline-block;">
                    🌅
		</a>  
		<?php 
	            $entity = $this->getGalleryEntity();
	            echo '<h2><a href="sql_crud_' . $entity . '.php">' . $entityIcons[$entity] . '</a></h2>';
                ?>
		<h2 style="margin: 0;"><?= $this->getGalleryTitle() ?></h2>

                <?php
                    echo '<h2 style="font-size: 2.6em; height: 50px; position: relative; top: -10px;  margin: 0 !important; vertical-align: top;"><a style="border: 0 !important; border-image-width: 0; color: #999;" href="' . $this->getGalleryUrl() . '">↻</a></h2>'; ?>
            </div>

            <form style="position: relative; height: 40px;" id="galleryFilterForm" class="gallery-header" method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <input type="hidden" name="grid" value="<?= $this->gridOn ? '1' : '0' ?>">
                <?php $this->renderFilters(); ?>
                <button style="position: absolute; top: 0; left: <?= $this->getToggleButtonLeft() ?>px;" id="toggleView" type="button"><?php echo $this->gridOn ? '⬜ Pic' : '⠿ Grid'; ?></button>

                <button type="button" id="prevStyle" style="position:absolute; top:0; left:<?= $this->getToggleButtonLeft() + 70 ?>px;">◀</button>
                <button type="button" id="nextStyle" style="position:absolute; top:0; left:<?= $this->getToggleButtonLeft() + 110 ?>px;">▶</button>
            </form>

            <!-- SWIPER: each slide represents one page -->
            <div class="swiper" id="<?= $this->getGalleryEntity() ?>Swiper" style="margin-top: 15px;">
                <div class="swiper-wrapper">
                    <?php
                    // Render the current page as the first slide for immediate content
                    $firstPageNum = $this->page;
                    ?>
                    <div class="swiper-slide" data-page="<?= $firstPageNum ?>" data-loaded="1">
                        <div class="slide-inner">
                            <div class="<?= htmlspecialchars($this->albumClass) ?> pswp-gallery">
                                <?= $itemsHtml ?>
                            </div>
                        </div>
                    </div>

                    <?php
                    // placeholder slides for other pages, lazy-loaded
                    for ($p = 1; $p <= $total_pages; $p++) {
                        if ($p == $firstPageNum) continue;
                        ?>
                        <div class="swiper-slide" data-page="<?= $p ?>" data-loaded="0">
                            <div class="slide-inner">
                                <div class="<?= htmlspecialchars($this->albumClass) ?> pswp-gallery">
                                    <div class="page-loading">Loading...</div>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <!-- pagination bullets & navigation -->
                <div class="swiper-pagination"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>

            <!-- hidden original footer (kept for compatibility but not displayed) -->
            <div class="gallery-footer" style="display:none;">
                <?php
                for ($p = 1; $p <= $total_pages; $p++) {
                    $active = ($p == $this->page) ? 'active' : '';
                    $url = $this->getPageUrl($p);
                    echo "<button class=\"$active\" onclick=\"window.location='$url'\">$p</button>";
                }
                ?>
            </div>

            <?= $this->renderJsCss() ?>
        </div>

        <div id="fullTextModal" class="modal">
          <div class="modal-content">
            <span class="close">&times;</span>
            <div id="modalText"></div>
          </div>
	</div>






<!-- Video modal -->
<!-- minimal video modal -->
<div id="videoModal" style="display:none;">
  <div class="video-modal-inner" role="dialog" aria-modal="true">
    <button id="videoClose" aria-label="Close video">✕</button>
    <video id="videoPlayer" playsinline controls preload="metadata" style="max-width:100%; width:100%; height:auto;" tabindex="0"></video>
  </div>
</div>







        <!-- Image Edit Modal (Cropper) -->
        <div id="imageEditModal" class="modal" style="display:none;">
          <div class="modal-content" style="max-width:90%; width:1000px; padding:10px;">
            <button class="close" id="imageEditClose" style="position:absolute; right:10px; top:10px; font-size:18px;">&times;</button>
            <h3 id="imageEditTitle" style="margin-top:0">Edit Image</h3>

            <div style="display:flex; gap:12px; align-items:flex-start;">
                <div style="flex:1; min-width:320px;">
                    <div style="width:100%; max-height:70vh; overflow:auto; text-align:center;">
                        <img id="imageEditImg" src="" alt="Edit" style="max-width:100%; display:block; margin:0 auto;">
                    </div>
                </div>

                <div style="width:360px;">
                    <div style="margin-bottom:8px;">
                        <label for="imageEditMode"><strong>Mode</strong></label>
                        <select id="imageEditMode" style="width:100%; margin-top:6px;">
                            <option value="crop">Crop</option>
                            <option value="greenscreen">Green area (greenscreen)</option>
                            <option value="placement">Placement</option>
                        </select>
                    </div>

                    <div style="margin-bottom:8px;">
                        <label><strong>Preview</strong></label>
                        <div id="imageEditPreview" style="border:1px solid #ccc; height:150px; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                            <img id="imageEditPreviewImg" src="" alt="Preview" style="max-width:100%; max-height:100%;">
                        </div>
                    </div>

                    <div style="margin-bottom:8px;">
                        <label><strong>Note</strong></label>
                        <textarea id="imageEditNote" style="width:100%; height:80px;" placeholder="Optional note"></textarea>
                    </div>

                    <div style="display:flex; gap:8px; margin-bottom:8px;">
                        <label style="display:flex; align-items:center; gap:6px;">
                            <input type="checkbox" id="imageEditApplyNow"> Apply immediately
                        </label>
                    </div>

                    <div style="display:flex; gap:8px;">
                        <button id="imageEditSaveBtn">Save Version</button>
                        <button id="imageEditSaveApplyBtn">Save & Apply</button>
                        <button id="imageEditCancelBtn">Cancel</button>
                    </div>
                </div>
            </div>
          </div>
        </div>

<script>
/*
  Global utilities and safe defaults:
  - window.importGenerative
  - window.deleteFrame
  - attachGearTo(context)
*/
window.importGenerative = window.importGenerative || (async function(entity, entityId, frameId){
    if (!entity || !entityId || !frameId) {
        Toast.show('Missing parameters. Import aborted.', 'error');
        return;
    }
    const ajaxUrl = `/import_entity_from_entity.php?ajax=1&source=${encodeURIComponent(entity)}&target=generatives&source_entity_id=${entityId}&frame_id=${frameId}&limit=1&copy_name_desc=1`;
    try {
        const resp = await fetch(ajaxUrl, { credentials: 'same-origin' });
        const text = await resp.text(); let data;
        try { data = JSON.parse(text); } catch(e) { Toast.show('Import failed: invalid response','error'); console.error(text); return; }
        if ((data.status && data.status === 'ok') || Array.isArray(data.result)) {
            const msg = Array.isArray(data.result) ? data.result.join("\n") : (data.message || 'Import triggered');
            Toast.show(`Import triggered for ${entity} #${entityId}: ${msg}`, 'info');
        } else {
            Toast.show(`Import failed for ${entity} #${entityId}`, 'error');
            console.warn('importGenerative: unexpected payload', data);
        }
    } catch (err) {
        Toast.show('Import failed', 'error'); console.error(err);
    }
});










/*
 * STORYBOARD INTEGRATION
 */


window._storyboardsCache = null;

window.loadStoryboards = async function() {
    if (window._storyboardsCache) return window._storyboardsCache;
    try {
        const resp = await fetch('/storyboards_v2_api.php?action=list', { credentials: 'same-origin' });
        const data = await resp.json();
        if (data.success) {
            window._storyboardsCache = data.data;
            return data.data;
        }
    } catch (err) {
        console.error('loadStoryboards error:', err);
    }
    return [];
};

window.importToStoryboard = async function(frameId, storyboardId) {
    if (!frameId || !storyboardId) {
        Toast.show('Missing parameters', 'error');
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append('storyboard_id', storyboardId);
    formData.append('frame_id', frameId);
    
    try {
        const resp = await fetch('/storyboard_import.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        });
        
        const data = await resp.json();
        
        if (data.success) {
            Toast.show(data.message || 'Frame imported to storyboard', 'success');
            window._storyboardsCache = null; // Clear cache
            return data;
        } else {
            Toast.show('Import failed: ' + (data.message || 'unknown error'), 'error');
        }
    } catch (err) {
        Toast.show('Import failed', 'error');
        console.error(err);
    }
};


/*
window.selectStoryboard = async function(frameId) {
    const storyboards = await window.loadStoryboards();
    
    if (storyboards.length === 0) {
        Toast.show('No storyboards available. Create one first.', 'warning');
        if (confirm('No storyboards found. Create one now?')) {
            window.open('/view_storyboards_v2.php', '_blank');
        }
        return;
    }
    
    let msg = 'Select a storyboard:\n\n';
    storyboards.forEach((sb, i) => {
        msg += `${i+1}. ${sb.name} (${sb.frame_count} frames)\n`;
    });
    msg += '\nEnter number (1-' + storyboards.length + '):';
    
    const choice = prompt(msg);
    if (!choice) return;
    
    const idx = parseInt(choice) - 1;
    if (idx >= 0 && idx < storyboards.length) {
        await window.importToStoryboard(frameId, storyboards[idx].id);
    } else {
        Toast.show('Invalid selection', 'error');
    }
};
 */



window.selectStoryboard = async function(frameId, $trigger) {
    const storyboards = await window.loadStoryboards();

    if (storyboards.length === 0) {
        Toast.show('No storyboards available', 'warning');
        return;
    }

    // Remove existing menus
    $('.sb-menu').remove();

    // Build menu
    const $menu = $('<div class="sb-menu"></div>');

    storyboards.forEach(sb => {
        const $item = $(`
            <div class="sb-menu-item" data-sb-id="${sb.id}">
                ${sb.name} <span style="color:#999">(${sb.frame_count})</span>
            </div>
        `);

        $item.on('click', async function(e) {
            e.stopPropagation();
            $('.sb-menu').remove();
            await window.importToStoryboard(frameId, sb.id);
        });

        $menu.append($item);
    });

    // Add separator and manage link
    $menu.append('<div class="sb-menu-sep"></div>');
    $menu.append(`
        <div class="sb-menu-item" onclick="window.open('/view_storyboards_v2.php','_blank')">
            📋 Manage Storyboards
        </div>
    `);

    // Position menu
    const pos = $trigger ? $trigger.offset() : { top: 100, left: 100 };
    $menu.css({
        position: 'absolute',
        top: pos.top + 30,
        left: pos.left,
        zIndex: 10000
    });

    $('body').append($menu);

    // Close on outside click
    setTimeout(() => {
        $(document).one('click', () => $('.sb-menu').remove());
    }, 100);
};















window.importControlNetMap = window.importControlNetMap || (async function(entity, entityId, frameId){
    if (!entity || !entityId || !frameId) {
        Toast.show('Missing parameters. Import aborted.', 'error');
        return;
    }
    const ajaxUrl = `/import_entity_from_entity.php?ajax=1&source=${encodeURIComponent(entity)}&target=controlnet_maps&source_entity_id=${entityId}&frame_id=${frameId}&limit=1&copy_name_desc=1`;
    try {
        const resp = await fetch(ajaxUrl, { credentials: 'same-origin' });
        const text = await resp.text(); 
        let data;
        try { 
            data = JSON.parse(text); 
        } catch(e) { 
            Toast.show('Import failed: invalid response','error'); 
            console.error(text); 
            return; 
        }
        if ((data.status && data.status === 'ok') || Array.isArray(data.result)) {
            const msg = Array.isArray(data.result) ? data.result.join("\n") : (data.message || 'Import triggered');
            Toast.show(`Import triggered for ${entity} #${entityId}: ${msg}`, 'info');
        } else {
            Toast.show(`Import failed for ${entity} #${entityId}`, 'error');
            console.warn('importControlNetMap: unexpected payload', data);
        }
    } catch (err) {
        Toast.show('Import failed', 'error'); 
        console.error(err);
    }
});





window.assignControlNetMap = window.assignControlNetMap || (async function(entity, entityId, frameId, targetEntity){
    // Only allow controlnet_maps
    if (entity !== 'controlnet_maps') {
        Toast.show('Only controlnet_maps can be assigned.', 'error');
        return;
    }

    if (!entityId || !frameId) {
        Toast.show('Missing entityId or frameId.', 'error');
        return;
    }

    if (!targetEntity || !['characters','generatives'].includes(targetEntity)) {
        Toast.show('Invalid target entity.', 'error');
        return;
    }

    // Redirect to your import form with prefilled parameters
    const hrefUrl = `/import_entity_from_entity.php?source=${encodeURIComponent(entity)}&source_entity_id=${encodeURIComponent(entityId)}&frame_id=${encodeURIComponent(frameId)}&target=${encodeURIComponent(targetEntity)}&copy_name_desc=0&controlnet=1`;
    window.location.href = hrefUrl;
});


window.assignToComposite = window.assignToComposite || (async function(entity, entityId, frameId){

    if (!entity || !entityId || !frameId) {
        Toast.show('Missing entity or entityId or frameId.', 'error');
        return;
    }

    /*
    if (!entity || !['characters','generatives'].includes(entity)) {
        Toast.show('Invalid source entity.', 'error');
        return;
    }
    */

    // Redirect to your import form with prefilled parameters
    const hrefUrl = `/import_entity_from_entity.php?source=${encodeURIComponent(entity)}&source_entity_id=${encodeURIComponent(entityId)}&frame_id=${encodeURIComponent(frameId)}&target=${encodeURIComponent('composites')}&copy_name_desc=0&composite=1`;
    window.location.href = hrefUrl;
});



window.usePromptMatrix = window.usePromptMatrix || (async function(entity, entityId, frameId){
    // Only allow controlnet_maps
    //if (entity !== 'controlnet_maps') {
    //    Toast.show('Only controlnet_maps can be assigned.', 'error');
    //    return;
    //}

    if (!entityId || !entity) {
        Toast.show('Missing entity or entityId.', 'error');
        return;
    }

    // Redirect to prompt matrix
    const hrefUrl = `/view_prompt_matrix.php?entity_type=${encodeURIComponent(entity)}&entity_id=${encodeURIComponent(entityId)}`;
    window.location.href = hrefUrl;
});



window.deleteFrame = window.deleteFrame || (async function(entity, entityId, frameId) {
    if (!entity || !entityId || !frameId) { alert("Missing parameters. Cannot delete frame."); return; }
    if (!confirm("Are you sure you want to delete this frame?")) return;
    try {
        const response = await fetch(`/delete_frames_from_entity.php?ajax=1&method=single&frame_id=${frameId}`, { method: 'POST', credentials:'same-origin' });
        const text = await response.text(); let result;
        try { result = JSON.parse(text); } catch(e){ throw new Error("Invalid server response: "+text); }
        if (result.status === "ok") {
            $(`.img-wrapper[data-frame-id="${frameId}"]`).remove();
            Toast.show('Frame deleted','success');
        } else {
            alert("Failed to delete frame: " + (result.message || 'unknown'));
        }
    } catch (err) {
        alert("Delete failed: " + (err.message || err));
        console.error(err);
    }
});

// Helper: attach gear menu to a DOM subtree (context can be a jQuery object or DOM element)
function attachGearTo(context) {
    const $root = context instanceof jQuery ? context : $(context);
    $root.find('.gear-icon').each(function(){
        try {
            $(this).off('click.gearmenu');
	    $(this).gearmenu([


<?php if ($this->getGalleryEntity() != 'controlnet_maps') { ?>		    
                
                
                {
                    label: 'Import2Generative',
                    onClick: function() {
                        const $wrapper = $(this).closest('.img-wrapper');
                        window.importGenerative($wrapper.data('entity'), $wrapper.data('entity-id'), $wrapper.data('frame-id'));
                    }
	    },





		    /*

                {
                    label: '🎬 Add to Storyboard',
                    onClick: function() {
                        const $wrapper = $(this).closest('.img-wrapper');
                        const frameId = $wrapper.data('frame-id');
                        if (frameId) {
                            window.selectStoryboard(frameId);
                        } else {
                            Toast.show('Frame ID not found', 'error');
                        }
                    }
                },
                
		     */



{
    label: '🎬 Add to Storyboard',
    onClick: function() {
        const $wrapper = $(this).closest('.img-wrapper');
        const frameId = $wrapper.data('frame-id');
        if (frameId) {
            // Pass $(this) as trigger for positioning
            window.selectStoryboard(frameId, $(this));
        }
    }
},



{
    label: '✏️ Edit Image',
    onClick: function() {
        const $w = $(this).closest('.img-wrapper');
        ImageEditorModal.open({
            entity: $w.data('entity'),
            entityId: $w.data('entity-id'),
            frameId: $w.data('frame-id'),
            src: $w.find('img').attr('src')
        });
    }
},



                
                {
                    label: 'Assign2Composite',
                    onClick: function() {
                        const $wrapper = $(this).closest('.img-wrapper');
                        window.assignToComposite($wrapper.data('entity'), $wrapper.data('entity-id'), $wrapper.data('frame-id'));
                    }
	        },



{
    label: 'Import2ControlNetMap',
    onClick: function() {
        const $wrapper = $(this).closest('.img-wrapper');
        window.importControlNetMap($wrapper.data('entity'), $wrapper.data('entity-id'), $wrapper.data('frame-id'));
    }
},
{
                    label: 'Use Prompt Matrix',
                    onClick: function() {
                        const $wrapper = $(this).closest('.img-wrapper');
                        window.usePromptMatrix($wrapper.data('entity'), $wrapper.data('entity-id'), $wrapper.data('frame-id'));
                    }
	        },
            
            
<?php } ?>


<?php if ($this->getGalleryEntity() == 'controlnet_maps') { ?>

{
        label: 'Assign2Character',
        onClick: function() {
            const $wrapper = $(this).closest('.img-wrapper');
            window.assignControlNetMap($wrapper.data('entity'), $wrapper.data('entity-id'), $wrapper.data('frame-id'), 'characters');
        }
    },
    {
        label: 'Assign2Generative',
        onClick: function() {
            const $wrapper = $(this).closest('.img-wrapper');
            window.assignControlNetMap($wrapper.data('entity'), $wrapper.data('entity-id'), $wrapper.data('frame-id'), 'generatives');
        }
},

<?php } ?>












                {
                    label: 'Edit / Coordinates',
                    onClick: function() {
                        const $wrapper = $(this).closest('.img-wrapper');
                        const entity = $wrapper.data('entity');
                        const entityId = $wrapper.data('entity-id');
                        const frameId = $wrapper.data('frame-id');
                        const imgSrc = $wrapper.find('img').attr('src');
                        if (typeof window.openImageEditor === 'function') {
                            window.openImageEditor({ entity, entityId, frameId, src: imgSrc });
                        } else {
                            Toast.show('Image editor not available', 'error');
                        }
                    }
                },
                {
                    label: 'Delete Frame',
                    onClick: function() {
                        const $wrapper = $(this).closest('.img-wrapper');
                        window.deleteFrame($wrapper.data('entity'), $wrapper.data('entity-id'), $wrapper.data('frame-id'));
                    }
                }
            ]);
        } catch(e) {
            console.warn('attachGearTo: gearmenu attach error', e);
        }
    });
}

/* ----------------- SWIPER, AJAX load + reattach handlers ----------------- */
(function(){
    const galleryEntity = <?= json_encode($this->getGalleryEntity()) ?>;
    const swiperContainer = '#' + galleryEntity + 'Swiper';
    let swiper = null;
    let photoswipeLightbox = null;

    const albumClassRaw = <?= json_encode(trim($this->albumClass)) ?>;
    const albumSelector = albumClassRaw.split(/\s+/).map(s => '.' + s).join('');

    function buildAjaxUrl(page) {
        const params = new URLSearchParams(new FormData(document.getElementById('galleryFilterForm')));
        params.set('ajax_gallery', '1');
        params.set('page', page);
        params.set('entity', galleryEntity);
        return window.location.pathname.replace(/[^\/]+$/, '') + 'ajax_gallery.php?' + params.toString();
    }

    function initPhotoSwipe() {
        if (photoswipeLightbox) {
            try { photoswipeLightbox.destroy(); } catch(e){}
        }
        
        photoswipeLightbox = new PhotoSwipeLightbox({
            gallery: '.pswp-gallery',
            children: 'a.pswp-gallery-item',
	    pswpModule: PhotoSwipe,
	    initialZoomLevel: 'fit',
            secondaryZoomLevel: 1,
            paddingFn: (viewportSize) => {
                return {
                    //top: 30, bottom: 30, left: 70, right: 70
                };
            }
        });
        
        photoswipeLightbox.init();
    }

    function loadPage(page, slideEl) {
        if (!slideEl) return;
        if (slideEl.getAttribute('data-loaded') === '1') return;
        const loadingEl = slideEl.querySelector('.page-loading');
        if (loadingEl) loadingEl.innerText = 'Loading...';
        fetch(buildAjaxUrl(page), { credentials: 'same-origin' })
        .then(r => r.text())
        .then(text => {
            let json;
            try { json = JSON.parse(text); } catch (e) {
                console.error('Gallery AJAX expected JSON but got:', text);
                if (loadingEl) loadingEl.innerText='Invalid response';
                throw e;
            }
            let albumDiv = null;
            try { albumDiv = slideEl.querySelector(albumSelector); } catch (e) { console.warn('album selector invalid', e); }
            albumDiv = albumDiv || slideEl.querySelector('.slide-inner') || slideEl;
            try { albumDiv.innerHTML = json.itemsHtml; } catch (e) {
                console.error('Failed to inject itemsHtml into albumDiv', albumDiv, e);
                if (loadingEl) loadingEl.innerText = 'Injection failed';
                throw e;
            }
            slideEl.setAttribute('data-loaded', '1');
            
            // Re-initialize PhotoSwipe for newly loaded content
            try { 
		    if (photoswipeLightbox) {
		    // TODO: SPW80 I have manually removed the following line as it would cause the lightbox to trigger when swiping to the next page with SwiperJs - and removing this line doesnt seem to hreak any functionality of the PhotoSwipe even on the new swiped pages that are dynamically loaded using ajax
                    //photoswipeLightbox.loadAndOpen(0);
                }
            } catch(e){ console.warn('PhotoSwipe reinit warning', e); }
            
            try { attachGearTo(slideEl); } catch(e){}
            $(slideEl).find('.show-full-text').off('click').on('click', function(e){
                e.stopPropagation();
                let fullHtml = $(this).parent().clone();
                fullHtml.find('.show-full-text').remove();
                $('#modalText').html(fullHtml.html());
                $('#fullTextModal').fadeIn(200);
            });
            $(slideEl).find('.mapRunSelect').each(function() {
                const select = $(this);
                const entityId = select.data('entity-id');
                $.post(window.location.pathname, { action: 'fetchMapRuns', entity_id: entityId }, function(runData) {
                    try {
                        const data = JSON.parse(runData);
                        select.empty();
                        data.forEach(run => {
                            select.append(`<option value="${run.id}" ${run.is_active ? 'selected' : ''}>${run.id} - ${run.note ? run.note : run.created_at}</option>`);
                        });
                    } catch(e){}
                });
            });
        })
        .catch(err => {
            console.error('Gallery AJAX error', err);
            try { if (slideEl.querySelector('.page-loading')) slideEl.querySelector('.page-loading').innerText = 'Failed to load page.'; } catch(e){}
        });
    }

    function initSwiper() {
        if (swiper) { swiper.update(); return; }
        swiper = new Swiper(swiperContainer, {
            direction: 'horizontal',
            slidesPerView: 1,
            spaceBetween: 0,
            pagination: { el: '.swiper-pagination', clickable: true },
            navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
            grabCursor: true,
            on: {
                slideChange: function() {
                    const slideEl = this.slides[this.activeIndex];
                    const pageAttr = parseInt(slideEl.dataset.page || (this.activeIndex + 1), 10);
                    loadPage(pageAttr, slideEl);
                }
            }
        });

        // Preload adjacent slides
        swiper.on('slideChangeTransitionEnd', function() {
            const idx = swiper.activeIndex;
            [idx-1, idx+1].forEach(i => {
                const s = swiper.slides[i];
                if (s) {
                    const p = parseInt(s.dataset.page || i+1, 10);
                    loadPage(p, s);
                }
            });
        });
    }

    $(document).ready(function(){
        initSwiper();
        initPhotoSwipe();

        // attach gear to initial content
        attachGearTo(document);



// delegated handler for the full-text modal (covers initial and AJAX-injected items)
$(document).on('click', '.show-full-text', function(e){
    e.stopPropagation();
    const $caption = $(this).closest('.caption');
    if ($caption.length === 0) return;
    const fullHtml = $caption.clone();
    fullHtml.find('.show-full-text').remove();
    $('#modalText').html(fullHtml.html());
    $('#fullTextModal').fadeIn(200);
});


        // prev/next style buttons
        const styleSelect = $('select[name="style"]');
        const styles = styleSelect.find('option').map((i, el) => $(el).val()).get();
        $('#prevStyle').off('click').on('click', () => {
            let idx = styles.indexOf(styleSelect.val());
            if(idx === -1) idx = 0;
            idx = (idx - 1 + styles.length) % styles.length;
            styleSelect.val(styles[idx]).trigger('change');
        });
        $('#nextStyle').off('click').on('click', () => {
            let idx = styles.indexOf(styleSelect.val());
            if(idx === -1) idx = 0;
            idx = (idx + 1) % styles.length;
            styleSelect.val(styles[idx]).trigger('change');
        });










// Robust Grid / Pic toggle (replace older handler)
$('#toggleView').off('click.gridToggle').on('click.gridToggle', function(e){
    e.preventDefault();

    // container and button
    const $container = $('.album-container').first(); // target first gallery instance
    const $btn = $(this);

    // derive current state: prefer stored window var, else read DOM / hidden input
    if (typeof window._galleryGridOn === 'undefined') {
        // try hidden input
        const hidden = $('#galleryFilterForm input[name="grid"]').val();
        window._galleryGridOn = (hidden === undefined) ? <?= $this->gridOn ? 'true' : 'false' ?> : (hidden === '1');
    }

    // toggle state boolean
    window._galleryGridOn = !window._galleryGridOn;

    // Apply visual classes to both outer container and any existing .album nodes
    if (window._galleryGridOn) {
        $container.addClass('grid-view');
        // ensure any album elements also have the grid classes for backward compatibility
        $('.album').addClass('grid');
        $btn.text('⬜ Pic');
        $btn.attr('aria-pressed', 'true');
    } else {
        $container.removeClass('grid-view');
        $('.album').removeClass('grid');
        $btn.text('⠿ Grid');
        $btn.attr('aria-pressed', 'false');
    }

    // update hidden input so AJAX requests keep the same state
    $('#galleryFilterForm input[name="grid"]').val(window._galleryGridOn ? '1' : '0');

    // update URL param so paging/AJAX keeps the same state
    try {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('grid', window._galleryGridOn ? '1' : '0');
        window.history.replaceState({}, '', currentUrl.toString());
    } catch(e) {
        // ignore: some environments may not support URL (very old browsers)
    }
});











// video modal logic — robust & safe
(function(){
  function openVideoModal(url, type, poster) {
    const $modal = $('#videoModal');
    const video = document.getElementById('videoPlayer');

    // cleanup previous sources
    try {
      video.pause();
    } catch(e){}
    while (video.firstChild) video.removeChild(video.firstChild);

    // create source element
    const source = document.createElement('source');
    source.src = url;
    if (type && type.toLowerCase().indexOf('webm') !== -1) source.type = 'video/webm';
    else if (type && type.toLowerCase().indexOf('ogg') !== -1) source.type = 'video/ogg';
    else source.type = 'video/mp4';

    video.appendChild(source);

    // set poster if provided
    try {
      if (poster) {
        video.setAttribute('poster', poster.charAt(0) === '/' ? poster : '/' + poster);
      } else {
        video.removeAttribute('poster');
      }
    } catch(e){}

    // load and try play
    try {
      video.load();
      video.currentTime = 0;
      video.play().catch(()=>{ /* autoplay may be blocked */ });
    } catch(e) {
      console.warn('video play failed', e);
    }

    // show modal
    $modal.fadeIn(160);
    // focus the player for keyboard support
    setTimeout(() => { try { video.focus(); } catch(e){} }, 200);
  }

  function closeVideoModal() {
    const video = document.getElementById('videoPlayer');
    try {
      video.pause();
      video.removeAttribute('src');
      while (video.firstChild) video.removeChild(video.firstChild);
      video.load && video.load();
    } catch(e){}
    $('#videoModal').fadeOut(120);
  }

  // Delegated click handler for poster or link
  $(document).on('click', '.play-overlay, a.play-video', function(e){
    e.preventDefault();
    e.stopPropagation();

    const $el = $(this);
    let url = $el.data('video') || $el.attr('href');
    if (!url) return;

    // normalize url
    if (url.charAt(0) !== '/' && !url.startsWith('http')) url = '/' + url;

    // type & poster
    let type = ($el.data('video-type') || (url.split('.').pop() || 'mp4')).toLowerCase();
    // try to pick poster from thumbnail inside wrapper
    let poster = null;
    const $wrapper = $el.closest('.img-wrapper');
    if ($wrapper && $wrapper.find('img').length) poster = $wrapper.find('img').first().attr('src');

    openVideoModal(url, type, poster);
  });

  // close modal by clicking the close button or the overlay itself
  $(document).on('click', '#videoClose', function(e){
    e.preventDefault(); e.stopPropagation();
    closeVideoModal();
  });
  // close when clicking overlay background (not the inner content)
  $(document).on('click', '#videoModal', function(e){
    if (e.target && e.target.id === 'videoModal') closeVideoModal();
  });
  // prevent click inside inner box from bubbling to overlay
  $(document).on('click', '#videoModal .video-modal-inner', function(e){
    e.stopPropagation();
  });

})();



















        $('#fullTextModal .close, #fullTextModal').off('click').on('click', function(e){
            if(e.target !== this) return;
            $('#fullTextModal').fadeOut(200);
        });
    });
})();
</script>




<!-- Image editor scripts and logic -->

<script src="/js/image_editor_modal.js"></script>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- CropperJS CSS via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" />
<?php else: ?>
    <!-- CropperJS CSS via local copy -->
    <link rel="stylesheet" href="/vendor/cropper/cropper.min.css" />
<?php endif; ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- CropperJS JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>
<?php else: ?>
    <!-- CropperJS JS via local copy -->
    <script src="/vendor/cropper/cropper.min.js"></script>
<?php endif; ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- jQuery-Cropper via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/jquery-cropper@1.0.1/dist/jquery-cropper.min.js"></script>
<?php else: ?>
    <!-- jQuery-Cropper via local copy -->
    <script src="/vendor/cropper/jquery-cropper.min.js"></script>
<?php endif; ?>





<script>
/* Image editor modal logic */
(function(){
    let cropper = null;
    let current = { entity: null, entityId: null, frameId: null, src: null, naturalWidth: null, naturalHeight: null };

    function openModal() { $('#imageEditModal').fadeIn(160); }
    function closeModal() {
        try { if (cropper) { $('#imageEditImg').cropper('destroy'); cropper = null; } } catch(e){}
        $('#imageEditImg').attr('src','');
        $('#imageEditPreviewImg').attr('src','');
        $('#imageEditModal').fadeOut(120);
    }

    window.openImageEditor = function(opts) {
        current.entity = opts.entity;
        current.entityId = opts.entityId;
        current.frameId = opts.frameId;
        current.src = opts.src;
        $('#imageEditTitle').text('Edit: ' + (opts.frameId ? ('frame #' + opts.frameId) : opts.src));
        $('#imageEditImg').attr('src', opts.src);
        $('#imageEditPreviewImg').attr('src', opts.src);
        $('#imageEditNote').val('');
        $('#imageEditApplyNow').prop('checked', false);
        $('#imageEditMode').val('crop');

        $('#imageEditImg').off('load').on('load', function(){
            try { $('#imageEditImg').cropper('destroy'); } catch(e){}
            $('#imageEditImg').cropper({
                viewMode: 1,
                autoCropArea: 0.6,
                movable: true,
                zoomable: true,
                scalable: false,
                cropBoxResizable: true,
                ready: function(){
                    current.naturalWidth = this.naturalWidth || this.naturalWidth;
                    current.naturalHeight = this.naturalHeight || this.naturalHeight;
                },
                crop: function(e) {
                    try {
                        const canvas = $('#imageEditImg').cropper('getCroppedCanvas', { width: 300, height: 300 });
                        if (canvas) { $('#imageEditPreviewImg').attr('src', canvas.toDataURL()); }
                    } catch(e){}
                }
            });
            cropper = $('#imageEditImg').data('cropper') || $('#imageEditImg');
        });

        openModal();
    };

    // wire modal buttons
    $(document).on('click', '#imageEditClose, #imageEditCancelBtn', function(e){
        e.preventDefault(); closeModal();
    });

    // Save (without apply) and Save & Apply
    $(document).on('click', '#imageEditSaveBtn, #imageEditSaveApplyBtn', async function(e){
        e.preventDefault();
        const doApply = $(this).attr('id') === 'imageEditSaveApplyBtn' || $('#imageEditApplyNow').is(':checked');
        if (!current.frameId || !current.entity) { Toast.show('Missing frame info', 'error'); return; }
        if (!$('#imageEditImg').attr('src')) { Toast.show('No image loaded', 'error'); return; }

        let cropData = null;
        try {
            const data = $('#imageEditImg').cropper('getData', true);
            cropData = {
                x: Math.round(data.x || 0),
                y: Math.round(data.y || 0),
                width: Math.round(data.width || 0),
                height: Math.round(data.height || 0),
                rotate: data.rotate || 0,
                scaleX: data.scaleX || 1,
                scaleY: data.scaleY || 1,
                imageNaturalWidth: $('#imageEditImg')[0].naturalWidth || current.naturalWidth || null,
                imageNaturalHeight: $('#imageEditImg')[0].naturalHeight || current.naturalHeight || null
            };
        } catch (err) {
            console.warn('cropper getData fail', err);
            cropData = { x:0, y:0, width:0, height:0, imageNaturalWidth: null, imageNaturalHeight: null };
        }

        const payload = {
            entity: current.entity,
            frame_id: current.frameId,
            entity_id: current.entityId,
            coords: cropData,
            mode: $('#imageEditMode').val() || 'crop',
            tool: 'jquery-cropper',
            note: $('#imageEditNote').val() || '',
            apply_immediately: doApply ? 1 : 0
        };

        try {
            const resp = await fetch('/save_image_edit.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const text = await resp.text();
            let json;
            try { json = JSON.parse(text); } catch(e) {
                Toast.show('Save failed: invalid response', 'error'); console.error('save_image_edit invalid json', text); return;
            }
            if (!json.success) {
                Toast.show('Save failed: ' + (json.message || 'unknown'), 'error');
                console.warn('save_image_edit error payload', json);
                return;
            }

            // success - update gallery image src in DOM for this frame
            const derived = json.derived_filename || json.filename || json.new_filename || null;
            if (derived) {
                const $wrapper = $(`.img-wrapper[data-frame-id="${current.frameId}"]`);
                const newSrc = (derived.charAt(0) === '/' ? derived : ('/' + derived)) + '?v=' + Date.now();
                $wrapper.find('img').each(function(){
                    $(this).attr('src', newSrc);
                });
            }

            Toast.show('Version created', 'info');
            closeModal();
            $(document).trigger('imageEdit.created', [json, current]);

        } catch (err) {
            console.error('save_image_edit fetch error', err);
            Toast.show('Save failed', 'error');
        }
    });

    // close modal when clicking overlay
    $(document).on('click', '#imageEditModal', function(e){
        if (e.target.id === 'imageEditModal') closeModal();
    });

})();
</script>

<style>
/* default album layout (list/stacked) */
.album { display: flex; flex-direction: column; gap: 20px; }

body {
    width: 600px;
    max-width: 600px;
}

.album-container {
    max-width: 100%;
}

/* grid applied by toggling outer container so lazy-loaded slides pick it up */
.album-container.grid-view .album {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
}
.album-container.grid-view .album .img-wrapper img { width: 100%; display:block; }

/* backward-compatible existing album.grid */
.album.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-bottom: 200px; }
.album.grid .img-wrapper img { width: 100%; }

/* Modal styles (kept) */
#fullTextModal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width:100%; height:100%; overflow:auto; background-color: rgba(0,0,0,0.5); }
.modal-content { position: relative; background-color:#fff; margin: 20% auto; padding:30px; border-radius:6px; max-width:80%; max-height:70%; overflow-y:auto; box-shadow:0 4px 20px rgba(0,0,0,0.3); }
.modal-content .close { position:absolute; top:10px; right:10px; font-size:20px; font-weight:bold; color:#333; background:none; border:none; cursor:pointer; z-index:1000; }

/* Image edit modal specific */
#imageEditModal { display:none; position: fixed; z-index: 10000; left: 0; top: 0; width:100%; height:100%; overflow:auto; background-color: rgba(0,0,0,0.6); }
#imageEditModal .modal-content { margin: 3% auto; background:#fff; border-radius:6px; padding:18px; box-shadow:0 8px 40px rgba(0,0,0,0.5); max-height:85vh; overflow:auto; }
#imageEditImg { max-width:100%; display:block; }

/* play overlay centered on the thumbnail */
.img-wrapper { position: relative; }
.play-overlay {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  z-index: 30;
  background: rgba(0,0,0,0.45);
  color: #fff;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 20px;
  cursor: pointer;
  pointer-events: auto;
  box-shadow: 0 6px 18px rgba(0,0,0,0.4);
}
.img-wrapper img { display:block; max-width:100%; }

/* Video modal */
#videoModal {
  display: none;
  position: fixed;
  z-index: 12000;
  left: 0; top: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.78);
  align-items: center;
  justify-content: center;
  padding: 20px;
  box-sizing: border-box;
}
#videoModal .video-modal-inner {
  position: relative;
  width: 100%;
  max-width: 1100px;
  margin: 0 auto;
}
#videoModal #videoClose {
  position: absolute;
  right: 8px;
  top: 8px;
  z-index: 3;
  background: rgba(0,0,0,0.45);
  color: #fff;
  border: none;
  font-size: 18px;
  padding: 6px 10px;
  border-radius: 6px;
  cursor: pointer;
}
#videoModal video { display:block; width:100%; height:auto; background:#000; border-radius:6px; }

.swiper-button-prev {
background-image: url(arrow-circle-left.svg) !important;
background-repeat: no-repeat;
background-size: 100% auto;
background-position: center;
}

.swiper-button-next {
background-image: url(arrow-circle-right.svg) !important;
background-repeat: no-repeat;
background-size: 100% auto;
background-position: center;
}

.swiper-button-next::after {
display: none;
}

.swiper-button-prev::after {
display: none;
}

.swiper-button-prev,
.swiper-button-next {
  filter: opacity(0.9);
}

.swiper-button-prev,
.swiper-button-next {
  width: 40px;   /* a bit slimmer than 60px */
  height: 40px;
  border-radius: 50%;
  background-color: rgba(0, 0, 0, 0.2); /* lighter transparent black */
  border: 2px solid rgba(0, 255, 255, 0.4); /* semi-transparent cyan border */
  box-shadow: 0 0 8px rgba(0, 255, 255, 0.3),
              0 0 16px rgba(0, 255, 255, 0.2),
              0 0 24px rgba(0, 255, 255, 0.1); /* softer transparent glow */
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.swiper-button-prev:hover,
.swiper-button-next:hover {
  transform: scale(1.1);
  box-shadow: 0 0 12px rgba(0, 255, 255, 0.4),
              0 0 24px rgba(0, 255, 255, 0.3),
              0 0 36px rgba(0, 255, 255, 0.2); /* a bit brighter on hover */
}

.swiper-button-prev::after,
.swiper-button-next::after {
  display: none;
}

.gear-icon {
    font-size: 2em !important;
}

#floatool {
    font-size:3em !important;
    padding: 6px !important;
}


.sb-menu {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: 200px;
    max-height: 400px;
    overflow-y: auto;
    font-size: 14px;
}

.sb-menu-item {
    padding: 10px 16px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
}

.sb-menu-item:hover {
    background: #f5f5f5;
}

.sb-menu-item:last-child {
    border-bottom: none;
}

.sb-menu-sep {
    height: 1px;
    background: #e0e0e0;
    margin: 4px 0;
}



</style>

<?php
	    $renderResult = ob_get_clean();
            return $renderResult . $this->renderSequel();
    }




/**
 * Default implementation: nothing.
 * Concrete galleries can override this to inject final content.
 */
protected function renderSequel(): string {
    return '';
}



    protected function renderFilters(): void {
        foreach ($this->filterOptions as $name => $options) {
            $left = $options['left'] ?? 0;
            // selected handling
            $selectedVal = $this->filters[$name] ?? 'all';
            echo "<select name=\"{$name}\" style=\"width:50px; position: absolute; top:0; left: {$left}px;\" onchange=\"document.getElementById('galleryFilterForm').submit();\">";
            echo "<option value=\"all\" " . ($selectedVal === 'all' ? 'selected' : '') . ">All {$options['label']}</option>";
            foreach ($options['values'] as $val) {
                $valEsc = htmlspecialchars($val);
                $sel = ($selectedVal === $val) ? 'selected' : '';
                echo "<option value=\"$valEsc\" $sel>$valEsc</option>";
            }
            echo "</select>";
        }
    }

    protected function getPageUrl(int $page): string {
        $params = $this->filters;
        $params['page'] = $page;
        $params['grid'] = $this->gridOn ? '1' : '0';
        $qs = http_build_query($params);
        return "?" . $qs;
    }

    protected function getGalleryUrl() {
	return 'gallery_' . $this->getGalleryEntity() . '.php';
    }

    protected function renderJsCss(): string {
        ob_start();
?>




        <link rel="stylesheet" href="/css/toast.css">
        <script src="/js/toast.js"></script>

        <link rel="stylesheet" href="css/gallery_gearicon_menu.css">
        <script src="js/gallery_gearicon_menu.js"></script>





        <!-- PhotoSwipe v5 CSS & JS -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- PhotoSwipe CSS via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<?php else: ?>
    <!-- PhotoSwipe CSS via local copy -->
    <link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
<?php endif; ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- PhotoSwipe JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.umd.js"></script>
<?php else: ?>
    <!-- PhotoSwipe JS via local copy -->
    <script src="/vendor/photoswipe/photoswipe.umd.js"></script>
    <script src="/vendor/photoswipe/photoswipe-lightbox.umd.js"></script>
<?php endif; ?>






<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- Swiper via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<?php else: ?>
    <!-- Swiper via local copy -->
    <link rel="stylesheet" href="/vendor/swiper/swiper-bundle.min.css" />
    <script src="/vendor/swiper/swiper-bundle.min.js"></script>
<?php endif; ?>






        <div id="toast-container"></div>
<?php
	require $this->spw->getPublicPath() .  "/floatool.php";
        return ob_get_clean();
    }
}
?>
