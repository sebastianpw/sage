<?php
namespace App\Gallery;

/**
 * CharacterDetails - Load and render character detail information
 *
 * - id, name, description always visible
 * - other fields collapsed by default (state persisted per-character)
 * - frames always visible (permanent 3-column grid)
 * - PhotoSwipe lightbox
 * - gear menu uses same API as FrameDetails and opens ImageEditorModal
 */
class CharacterDetails
{
    public $characterId;
    public $name;
    public $role;
    public $description;
    public $age_background;
    public $motivations;
    public $hooks_arc_potential;
    public $desc_abbr;
    public $created_at;
    public $updated_at;
    public $frames = [];
    public $error = null;

    private $mysqli;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function load(int $characterId): bool
    {
        $this->characterId = $characterId;

        $stmt = $this->mysqli->prepare("
            SELECT id, name, role, description, age_background, motivations, hooks_arc_potential, desc_abbr, created_at, updated_at
            FROM characters WHERE id = ?
        ");
        $stmt->bind_param('i', $characterId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $this->error = "Character #{$characterId} not found.";
            return false;
        }
        $row = $result->fetch_assoc();
        $this->name = $row['name'];
        $this->role = $row['role'];
        $this->description = $row['description'];
        $this->age_background = $row['age_background'];
        $this->motivations = $row['motivations'];
        $this->hooks_arc_potential = $row['hooks_arc_potential'];
        $this->desc_abbr = $row['desc_abbr'];
        $this->created_at = $row['created_at'];
        $this->updated_at = $row['updated_at'];

        // frames
        $stmt = $this->mysqli->prepare("
            SELECT f.id, f.name, f.filename, f.prompt, f.created_at, f.entity_type, f.entity_id
            FROM frames f
            INNER JOIN frames_2_characters f2c ON f.id = f2c.from_id
            WHERE f2c.to_id = ?
            ORDER BY f.id DESC
        ");
        $stmt->bind_param('i', $characterId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $this->frames[] = $r;
        }

        return true;
    }

    public function renderContent(): string
    {
        ob_start();
        $id = (int)$this->characterId;
        $name = $this->name ?: "Character #{$id}";
        $role = $this->role;
        ?>
<div class="character-details-container" data-character-id="<?= $id ?>">
    <div class="character-header">
        <h1 class="character-name"><?= htmlspecialchars($name) ?></h1>
        <?php if ($role): ?><div class="character-role"><?= htmlspecialchars($role) ?></div><?php endif; ?>
        <div class="character-topline" style="margin-top:8px;color:#888;font-size:13px;">
            <span>ID: <?= $id ?></span>
        </div>
    </div>

    <?php if ($this->description): ?>
    <div class="character-description">
        <h3>Description</h3>
        <p><?= nl2br(htmlspecialchars($this->description)) ?></p>
    </div>
    <?php endif; ?>

    <div class="character-section">
        <button type="button" class="fold-toggle" data-section="details" aria-expanded="false">
            <span class="fold-indicator">+</span> Details
        </button>
        <div class="fold-content fold-details" style="display:none;">
            <div class="character-info-grid">
                <?php if ($this->desc_abbr): ?>
                <div class="info-section">
                    <h3>Quick Description</h3>
                    <p><?= nl2br(htmlspecialchars($this->desc_abbr)) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($this->age_background): ?>
                <div class="info-section">
                    <h3>Age & Background</h3>
                    <p><?= nl2br(htmlspecialchars($this->age_background)) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($this->motivations): ?>
                <div class="info-section">
                    <h3>Motivations</h3>
                    <p><?= nl2br(htmlspecialchars($this->motivations)) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($this->hooks_arc_potential): ?>
                <div class="info-section">
                    <h3>Hooks & Arc Potential</h3>
                    <p><?= nl2br(htmlspecialchars($this->hooks_arc_potential)) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="character-frames-section">
        <h2>Character Frames (<?= count($this->frames) ?>)</h2>
        <?php if (empty($this->frames)): ?>
            <p class="no-frames">No frames found for this character yet.</p>
        <?php else: ?>
            <div class="frames-grid pswp-gallery">
                <?php foreach ($this->frames as $frame): 
                    $frameId = (int)$frame['id'];
                    $frameName = htmlspecialchars($frame['name'] ?? '');
                    $frameFile = '/' . ltrim($frame['filename'] ?? '', '/');
                ?>
                <div class="frame-item img-wrapper" data-frame-id="<?= $frameId ?>" data-entity="characters" data-entity-id="<?= $id ?>">
                    <a href="<?= htmlspecialchars($frameFile) ?>"
                       class="pswp-gallery-item"
                       data-pswp-src="<?= htmlspecialchars($frameFile) ?>"
                       data-pswp-width="1024"
                       data-pswp-height="1024"
                       title="<?= $frameName ?>">
                        <img src="<?= htmlspecialchars($frameFile) ?>" alt="<?= $frameName ?>" loading="lazy">
                    </a>

                    <div class="frame-overlay">
                        <div class="frame-name"><?= $frameName ?></div>
                        <span class="gear-icon" title="Options">⚙</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="character-meta">
        <?php if (!empty($this->created_at)): ?><div class="meta-item"><strong>Created:</strong> <?= date('M j, Y g:i A', strtotime($this->created_at)) ?></div><?php endif; ?>
        <?php if (!empty($this->updated_at)): ?><div class="meta-item"><strong>Updated:</strong> <?= date('M j, Y g:i A', strtotime($this->updated_at)) ?></div><?php endif; ?>
    </div>
</div>

<!-- Video modal -->
<div id="videoModal" style="display:none;">
  <div class="video-modal-inner" role="dialog" aria-modal="true">
    <button id="videoClose" aria-label="Close video">✕</button>
    <video id="videoPlayer" playsinline controls preload="metadata" style="max-width:100%; width:100%; height:auto;" tabindex="0"></video>
  </div>
</div>

<!-- Image Edit Modal (the new editor is expected in /js/image_editor_modal.js) -->
<div id="imageEditModal" class="modal" style="display:none;">
  <div class="modal-content" style="max-width:90%; width:1000px; padding:10px;">
    <button class="close" id="imageEditClose" style="position:absolute; right:10px; top:10px; font-size:18px;">&times;</button>
    <h3 id="imageEditTitle" style="margin-top:0">Edit Image</h3>
    <div id="imageEditShell"></div>
  </div>
</div>

<style>
/* minimal CSS, keep same appearance as your app */
.character-details-container { max-width:1200px; margin:0 auto; padding:20px; color:#ccc; }
.character-header { border-bottom:2px solid #444; padding-bottom:15px; margin-bottom:12px; }
.character-header h1 { margin:0; color:#fff; font-size:32px; display:inline-block; }
.character-role { color:#87CEEB; font-size:18px; font-weight:500; }
.character-description { background:#111; padding:14px; border-radius:6px; margin:12px 0; border-left:3px solid #87CEEB; }
.character-info-grid { display:grid; grid-template-columns:1fr; gap:20px; margin-bottom:20px; }
.info-section { background:#1a1a1a; padding:16px; border-radius:4px; border-left:3px solid #87CEEB; }
.fold-toggle { background:#111; color:#fff; border:1px solid #2b2b2b; padding:8px 12px; border-radius:6px; font-weight:600; cursor:pointer; display:flex; gap:10px; align-items:center; margin:10px 0; }
.fold-toggle .fold-indicator { display:inline-block; width:18px; text-align:center; font-weight:700; }
.character-frames-section { margin:24px 0; }
.frames-grid { display:grid; grid-template-columns: repeat(3,1fr) !important; gap:12px; }
.frame-item { position:relative; aspect-ratio:1; min-width:84px; overflow:hidden; border-radius:4px; background:#222; }
.frame-item img { width:100%; height:100%; object-fit:cover; transition:transform .3s; display:block; }
.frame-item:hover img { transform:scale(1.05); }
.frame-overlay { position:absolute; bottom:0; left:0; right:0; background:linear-gradient(to top, rgba(0,0,0,0.85), transparent); padding:10px; display:flex; align-items:center; justify-content:space-between; gap:8px; }
.frame-name { color:#fff; font-size:13px; font-weight:500; }
.gear-icon { font-size:1.2em; color:#fff; cursor:pointer; padding:6px; }
.character-meta { margin-top:20px; padding-top:10px; border-top:1px solid #444; display:flex; gap:20px; font-size:14px; color:#888; }



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

<!-- includes (PhotoSwipe + image editor + toast + gearmenu) -->
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
function initializeCharacterDetailsScripts(root = document) {
    const container = (root === document) ? document.querySelector('.character-details-container') : root.querySelector('.character-details-container');
    if (!container) return;

    const charId = container.getAttribute('data-character-id');
    const storageKey = `spw_character_fold_state:${charId}`;

    function getState() {
        try {
            const raw = localStorage.getItem(storageKey);
            return raw ? JSON.parse(raw) : { details: false };
        } catch(e) { return { details: false }; }
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

    // PhotoSwipe init
    try {
        if (window.characterPhotoswipeLightbox) {
            try { window.characterPhotoswipeLightbox.destroy(); } catch(e){}
            window.characterPhotoswipeLightbox = null;
        }
        window.characterPhotoswipeLightbox = new PhotoSwipeLightbox({
            gallery: '.character-details-container .pswp-gallery',
            children: 'a.pswp-gallery-item',
            pswpModule: PhotoSwipe,
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });
        window.characterPhotoswipeLightbox.init();
    } catch(e){ console.warn('PhotoSwipe init failed', e); }

    // Gearmenu: align with FrameDetails implementation and ImageEditorModal usage
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
        // If jQuery or gearmenu is missing, warn once
        if (!initializeCharacterDetailsScripts._warnedGear) {
            console.warn('gearmenu or jQuery not available - gear menus not attached.');
            initializeCharacterDetailsScripts._warnedGear = true;
        }
    }

    // Video modal handlers (delegated)
    $(document).off('click.charVideo').on('click.charVideo', '.play-overlay, a.play-video', function(e){
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
    $(document).off('click.charVideoClose').on('click.charVideoClose', '#videoClose', function(e){ e.preventDefault(); e.stopPropagation(); closeVideoModal(); });
    $(document).off('click.charVideoOverlay').on('click.charVideoOverlay', '#videoModal', function(e){ if (e.target && e.target.id === 'videoModal') closeVideoModal(); });
    $(document).off('click.charVideoInner').on('click.charVideoInner', '#videoModal .video-modal-inner', function(e){ e.stopPropagation(); });

} // end initializeCharacterDetailsScripts

// auto-init on full page loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() { initializeCharacterDetailsScripts(document); });
} else {
    initializeCharacterDetailsScripts(document);
}
</script>

<?php
        return ob_get_clean();
    }
}