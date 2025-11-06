<?php
namespace App\Gallery;

/**
 * EntityDetails - Generic loader & renderer for simple "entity" tables.
 *
 * Works for: locations, backgrounds, sketches, artifacts, vehicles, spawns, generatives, ...
 * - id, name, description always visible
 * - other fields collapsed (state persisted per entity:id)
 * - frames always visible and use PhotoSwipe + gear menu + ImageEditorModal
 */
class EntityDetails
{
    private $mysqli;
    public $entity;
    public $id;
    public $data = [];
    public $fields = [];
    public $frames = [];
    public $error = null;

    protected static $allowedEntities = [
        'locations', 'backgrounds', 'sketches', 'artifacts',
        'vehicles', 'spawns', 'generatives'
    ];

    public function __construct($mysqli) { $this->mysqli = $mysqli; }

    public function load(string $entity, int $id): bool
    {
        $this->entity = $entity;
        $this->id = $id;

        if (!in_array($entity, self::$allowedEntities, true)) {
            $this->error = "Entity '{$entity}' is not allowed.";
            return false;
        }

        $stmt = $this->mysqli->prepare("
            SELECT COUNT(*) as cnt
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
        ");
        $stmt->bind_param('s', $entity);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if ((int)$row['cnt'] === 0) { $this->error = "Entity table '{$entity}' does not exist."; return false; }

        $sql = "SELECT * FROM `{$entity}` WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) { $this->error = ucfirst($entity) . " #{$id} not found."; return false; }

        $this->data = $result->fetch_assoc();
        $this->fields = array_keys($this->data);

        // frames via mapping table
        $mapTable = 'frames_2_' . $entity;
        $stmt = $this->mysqli->prepare("
            SELECT COUNT(*) as cnt
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
        ");
        $stmt->bind_param('s', $mapTable);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();

        if ((int)$row['cnt'] > 0) {
            $sql = "
                SELECT f.id, f.name, f.filename, f.prompt, f.created_at, f.entity_type, f.entity_id
                FROM frames f
                INNER JOIN `{$mapTable}` f2 ON f.id = f2.from_id
                WHERE f2.to_id = ?
                ORDER BY f.id DESC
            ";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($fr = $res->fetch_assoc()) $this->frames[] = $fr;
        } else {
            $this->frames = [];
        }

        return true;
    }

    public function renderContent(): string
    {
        ob_start();
        $entity = htmlspecialchars($this->entity);
        $id = (int)$this->id;
        $name = htmlspecialchars($this->data['name'] ?? ($this->entity . " #{$id}"));
        $description = $this->data['description'] ?? null;
        ?>
<div class="entity-details-container" data-entity="<?= $entity ?>" data-id="<?= $id ?>">
    <div class="entity-header">
        <h1 class="entity-name"><?= $name ?></h1>
        <div class="entity-topline" style="margin-top:8px;color:#888;font-size:13px;">
            <span>ID: <?= $id ?></span>
            <?php if (!empty($this->data['order'])): ?><span style="margin-left:8px;">Order: <?= (int)$this->data['order'] ?></span><?php endif; ?>
        </div>
    </div>

    <?php if ($description): ?>
    <div class="entity-description">
        <h3>Description</h3>
        <p><?= nl2br(htmlspecialchars($description)) ?></p>
    </div>
    <?php endif; ?>

    <div class="entity-section">
        <button type="button" class="fold-toggle" data-section="fields" aria-expanded="false">
            <span class="fold-indicator">+</span> Details
        </button>
        <div class="fold-content fold-fields" style="display:none;">
            <div class="entity-info-grid">
                <?php
                $showed = [];
                foreach (['desc_abbr','description_short'] as $f) {
                    if (!empty($this->data[$f]) && $f !== 'description') {
                        echo '<div class="info-section"><h3>'.htmlspecialchars(ucwords(str_replace('_',' ',$f))).'</h3><p>'.nl2br(htmlspecialchars($this->data[$f])).'</p></div>';
                        $showed[] = $f;
                    }
                }
                foreach ($this->fields as $field) {
                    if (in_array($field, $showed, true)) continue;
                    if (in_array($field, ['id','name','description','filename','img2img_frame_filename','cnmap_frame_filename'], true)) continue;
                    $val = $this->data[$field];
                    if ($val === null || $val === '') continue;
                    echo '<div class="info-section"><h3>'.htmlspecialchars(ucwords(str_replace('_',' ',$field))).'</h3><p>'.nl2br(htmlspecialchars((string)$val)).'</p></div>';
                }
                ?>
            </div>
        </div>
    </div>

    <div class="entity-frames-section">
        <h2>Frames (<?= count($this->frames) ?>)</h2>
        <?php if (empty($this->frames)): ?>
            <p class="no-frames">No frames found for this entity.</p>
        <?php else: ?>
            <div class="frames-grid pswp-gallery">
                <?php foreach ($this->frames as $frame):
                    $fId = (int)$frame['id'];
                    $fName = htmlspecialchars($frame['name'] ?? '');
                    $fFile = '/' . ltrim($frame['filename'] ?? '', '/');
                ?>
                <div class="frame-item img-wrapper" data-frame-id="<?= $fId ?>" data-entity="<?= $entity ?>" data-entity-id="<?= $id ?>">
                    <a href="<?= htmlspecialchars($fFile) ?>" class="pswp-gallery-item" data-pswp-src="<?= htmlspecialchars($fFile) ?>" data-pswp-width="1024" data-pswp-height="1024" title="<?= $fName ?>">
                        <img src="<?= htmlspecialchars($fFile) ?>" alt="<?= $fName ?>" loading="lazy">
                    </a>
                    <div class="frame-overlay">
                        <div class="frame-name"><?= $fName ?></div>
                        <span class="gear-icon" title="Options">⚙</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="entity-meta">
        <?php if (!empty($this->data['created_at'])): ?><div class="meta-item"><strong>Created:</strong> <?= date('M j, Y g:i A', strtotime($this->data['created_at'])) ?></div><?php endif; ?>
        <?php if (!empty($this->data['updated_at'])): ?><div class="meta-item"><strong>Updated:</strong> <?= date('M j, Y g:i A', strtotime($this->data['updated_at'])) ?></div><?php endif; ?>
    </div>
</div>

<!-- Video modal -->
<div id="videoModal" style="display:none;">
  <div class="video-modal-inner" role="dialog" aria-modal="true">
    <button id="videoClose" aria-label="Close video">✕</button>
    <video id="videoPlayer" playsinline controls preload="metadata" style="max-width:100%; width:100%; height:auto;" tabindex="0"></video>
  </div>
</div>

<!-- Image Edit Modal (new editor) -->
<div id="imageEditModal" class="modal" style="display:none;">
  <div class="modal-content" style="max-width:90%; width:1000px; padding:10px;">
    <button class="close" id="imageEditClose" style="position:absolute; right:10px; top:10px; font-size:18px;">&times;</button>
    <h3 id="imageEditTitle" style="margin-top:0">Edit Image</h3>
    <div id="imageEditShell"></div>
  </div>
</div>

<style>
.entity-details-container { max-width:1200px; margin:0 auto; padding:20px; color:#ccc; }
.entity-header { border-bottom:2px solid #444; padding-bottom:12px; margin-bottom:12px; }
.entity-name { color:#fff; font-size:28px; display:inline-block; }
.entity-description { background:#111; padding:14px; border-radius:6px; margin:12px 0; border-left:3px solid #87CEEB; }
.entity-info-grid { display:grid; grid-template-columns:1fr; gap:12px; margin-top:12px; }
.info-section { background:#0f0f0f; padding:12px; border-radius:4px; border-left:3px solid #87CEEB; }
.fold-toggle { background:#111; color:#fff; border:1px solid #2b2b2b; padding:8px 12px; border-radius:6px; font-weight:600; cursor:pointer; display:flex; gap:10px; align-items:center; margin:10px 0; }
.frames-grid { display:grid; grid-template-columns: repeat(3,1fr) !important; gap:12px; }
.frame-item { position:relative; aspect-ratio:1; min-width:84px; overflow:hidden; border-radius:4px; background:#222; }
.frame-item img { width:100%; height:100%; object-fit:cover; display:block; }
.frame-overlay { position:absolute; bottom:0; left:0; right:0; background:linear-gradient(to top, rgba(0,0,0,0.85), transparent); padding:10px; display:flex; align-items:center; justify-content:space-between; gap:8px; }
.gear-icon { font-size:1.2em; color:#fff; cursor:pointer; padding:6px; }
.entity-meta { margin-top:18px; padding-top:10px; border-top:1px solid #444; display:flex; gap:20px; font-size:14px; color:#888; }


.sb-menu {
    color: #000;
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

<!-- includes -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.umd.js"></script>
<?php else: ?>
<link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
<script src="/vendor/photoswipe/photoswipe.umd.js"></script>
<script src="/vendor/photoswipe/photoswipe-lightbox.umd.js"></script>
<?php endif; ?>

<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<link rel="stylesheet" href="/css/gallery_gearicon_menu.css">
<script src="/js/gallery_gearicon_menu.js"></script>
 <script src="js/gear_menu_globals.js"></script>  
<script src="/js/image_editor_modal.js"></script>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" />
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-cropper@1.0.1/dist/jquery-cropper.min.js"></script>
<?php else: ?>
<link rel="stylesheet" href="/vendor/cropper/cropper.min.css" />
<script src="/vendor/cropper/cropper.min.js"></script>
<script src="/vendor/cropper/jquery-cropper.min.js"></script>
<?php endif; ?>

<script>
function initializeEntityDetailsScripts(root = document) {
    const container = (root === document) ? document.querySelector('.entity-details-container') : root.querySelector('.entity-details-container');
    if (!container) return;

    const entity = container.getAttribute('data-entity');
    const id = container.getAttribute('data-id');
    const storageKey = `spw_entity_fold_state:${entity}:${id}`;

    function getState() {
        try { const raw = localStorage.getItem(storageKey); return raw ? JSON.parse(raw) : { fields: false }; } catch(e) { return { fields: false }; }
    }
    function setState(s) { try { localStorage.setItem(storageKey, JSON.stringify(s)); } catch(e){} }

    const state = getState();
    container.querySelectorAll('.fold-toggle').forEach(btn => {
        const section = btn.getAttribute('data-section');
        const content = container.querySelector('.fold-' + section);
        const indicator = btn.querySelector('.fold-indicator');
        const expanded = !!state[section];
        btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (indicator) indicator.textContent = expanded ? '−' : '+';
        if (content) content.style.display = expanded ? '' : 'none';
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        newBtn.addEventListener('click', function() {
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            const newExpanded = !isExpanded;
            const contentEl = container.querySelector('.fold-' + this.getAttribute('data-section'));
            const ind = this.querySelector('.fold-indicator');
            this.setAttribute('aria-expanded', newExpanded ? 'true' : 'false');
            if (ind) ind.textContent = newExpanded ? '−' : '+';
            if (contentEl) contentEl.style.display = newExpanded ? '' : 'none';
            const cur = getState();
            cur[this.getAttribute('data-section')] = newExpanded;
            setState(cur);
        });
    });

    // PhotoSwipe
    try {
        if (window.entityPhotoswipeLightbox) {
            try { window.entityPhotoswipeLightbox.destroy(); } catch(e){}
            window.entityPhotoswipeLightbox = null;
        }
        window.entityPhotoswipeLightbox = new PhotoSwipeLightbox({
            gallery: '.entity-details-container .pswp-gallery',
            children: 'a.pswp-gallery-item',
            pswpModule: PhotoSwipe,
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });
        window.entityPhotoswipeLightbox.init();
    } catch(e){ console.warn('PhotoSwipe init failed', e); }

    // Gearmenu - FrameDetails style
    if (window.jQuery && typeof $.fn.gearmenu !== 'undefined') {
        const $container = $(container);
        $container.find('.gear-icon').each(function(){
            $(this).off('click.gearmenu');
            $(this).gearmenu([
                {
                    label: '⚡ Import to Generative',
                    onClick: function() {
                        const $w = $(this).closest('.img-wrapper');
                        window.importGenerative($w.data('entity'), $w.data('entity-id'), $w.data('frame-id'));
                    }
                },
                {
                    label: '🎬 Add to Storyboard',
                    onClick: function() {
                        const $w = $(this).closest('.img-wrapper');
                        const frameId = $w.data('frame-id');
                        if (frameId) window.selectStoryboard(frameId, $(this));
                    }
                },
                {
                    label: '✏️ Edit Image',
                    onClick: function() {
                        const $w = $(this).closest('.img-wrapper');
                        const src = $w.find('img').attr('src');
                        if (typeof ImageEditorModal !== 'undefined') {
                            ImageEditorModal.open({
                                entity: $w.data('entity'),
                                entityId: $w.data('entity-id'),
                                frameId: $w.data('frame-id'),
                                src: src
                            });
                        } else {
                            Toast.show('Image editor not available', 'error');
                        }
                    }
                },
                {
                    label: '🧩 Assign to Composite',
                    onClick: function() {
                        const $w = $(this).closest('.img-wrapper');
                        window.assignToComposite($w.data('entity'), $w.data('entity-id'), $w.data('frame-id'));
                    }
                },
                {
                    label: '☠️ Import to CNetMap',
                    onClick: function() {
                        const $w = $(this).closest('.img-wrapper');
                        window.importControlNetMap($w.data('entity'), $w.data('entity-id'), $w.data('frame-id'));
                    }
                },
                {
                    label: '🌌 Use Prompt Matrix',
                    onClick: function() {
                        const $w = $(this).closest('.img-wrapper');
                        window.usePromptMatrix($w.data('entity'), $w.data('entity-id'), $w.data('frame-id'));
                    }
                },
                {
                    label: '🗑️ Delete Frame',
                    onClick: function() {
                        const $w = $(this).closest('.img-wrapper');
                        if (confirm('Delete this frame?')) {
                            window.deleteFrame($w.data('entity'), $w.data('entity-id'), $w.data('frame-id'));
                        }
                    }
                }
            ]);
        });
    } else {
        if (!initializeEntityDetailsScripts._warnedGear) {
            console.warn('gearmenu or jQuery not available - gear menus not attached.');
            initializeEntityDetailsScripts._warnedGear = true;
        }
    }

    // Video modal bindings (same as CharacterDetails)
    $(document).off('click.entityVideo').on('click.entityVideo', '.play-overlay, a.play-video', function(e){
        e.preventDefault(); e.stopPropagation();
        const $el = $(this);
        let url = $el.data('video') || $el.attr('href');
        if (!url) return;
        if (url.charAt(0) !== '/' && !url.startsWith('http')) url = '/' + url;
        let type = ($el.data('video-type') || (url.split('.').pop() || 'mp4')).toLowerCase();
        let poster = null;
        const $wrapper = $el.closest('.img-wrapper');
        if ($wrapper && $wrapper.find('img').length) poster = $wrapper.find('img').first().attr('src');
        openVideoModal(url, type, poster);
    });

    function openVideoModal(url, type, poster) {
        const $modal = $('#videoModal');
        const video = document.getElementById('videoPlayer');
        try { video.pause(); } catch(e){}
        while (video.firstChild) video.removeChild(video.firstChild);
        const source = document.createElement('source');
        source.src = url;
        if (type && type.indexOf('webm') !== -1) source.type = 'video/webm';
        else if (type && type.indexOf('ogg') !== -1) source.type = 'video/ogg';
        else source.type = 'video/mp4';
        video.appendChild(source);
        try { if (poster) video.setAttribute('poster', poster.charAt(0)==='/'?poster:('/'+poster)); else video.removeAttribute('poster'); } catch(e){}
        try { video.load(); video.currentTime = 0; video.play().catch(()=>{}); } catch(e){}
        $modal.fadeIn(160);
        setTimeout(()=>{ try{ video.focus(); }catch(e){} }, 200);
    }
    function closeVideoModal() {
        const video = document.getElementById('videoPlayer');
        try { video.pause(); video.removeAttribute('src'); while (video.firstChild) video.removeChild(video.firstChild); video.load && video.load(); } catch(e){}
        $('#videoModal').fadeOut(120);
    }
    $(document).off('click.entityVideoClose').on('click.entityVideoClose', '#videoClose', function(e){ e.preventDefault(); e.stopPropagation(); closeVideoModal(); });
    $(document).off('click.entityVideoOverlay').on('click.entityVideoOverlay', '#videoModal', function(e){ if (e.target && e.target.id === 'videoModal') closeVideoModal(); });
    $(document).off('click.entityVideoInner').on('click.entityVideoInner', '#videoModal .video-modal-inner', function(e){ e.stopPropagation(); });
} // end initializeEntityDetailsScripts

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() { initializeEntityDetailsScripts(document); });
} else {
    initializeEntityDetailsScripts(document);
}
</script>

<?php
        return ob_get_clean();
    }
}