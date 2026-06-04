<?php
// public/plush.php
// PLot Us Story Highlights (PLUSH) — Story scene highlight block editor
// Cloned from cinemagic_editor.php — uses plush_* tables only, no cinemagic dependencies

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';







use App\UI\Modules\ModuleRegistry;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$storyId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editLang = $_GET['lang'] ?? 'en';

// Global System Languages Fetch (reuse system_languages table — shared platform resource)
$allLanguages = [];
try {
    $allLanguages = $pdo->query("SELECT * FROM system_languages ORDER BY is_main DESC, code ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ── Story list (no id given) ──────────────────────────────────────────────────
if (!$storyId) {
    $stories = $pdo->query("SELECT id, title, created_at FROM plush_stories ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    ob_start();
    ?>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <div style="max-width:700px;margin:60px auto;padding:20px;">
        <h2 style="font-family:'Space Mono',monospace;color:var(--accent);">&#128221; PLUSH — Plot Us Story Highlights</h2>
        <p style="color:var(--text-muted);font-size:.85rem;">Select a story to edit its highlight blocks:</p>

        <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
            <button id="newStoryBtn" style="padding:8px 18px;border-radius:4px;border:1px solid var(--accent);background:var(--accent);color:#000;font-family:'Space Mono',monospace;font-size:.75rem;font-weight:bold;cursor:pointer;">+ New Story</button>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px;">
        <?php foreach ($stories as $s): ?>
            <div style="display:flex;align-items:center;background:var(--card);border:1px solid var(--border);border-radius:6px;overflow:hidden;transition:border-color .2s;"
                 onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
                <a href="?id=<?= $s['id'] ?>"
                   style="display:flex;justify-content:space-between;flex:1;padding:10px 14px;text-decoration:none;color:var(--text);font-family:'Space Mono',monospace;font-size:.85rem;">
                    <span>#<?= $s['id'] ?> — <?= htmlspecialchars($s['title']) ?></span>
                    <span style="color:var(--text-muted);"><?= date('Y-m-d', strtotime($s['created_at'])) ?></span>
                </a>
                
                <button onclick="deleteStory(<?= $s['id'] ?>, <?= htmlspecialchars(json_encode($s['title']), ENT_QUOTES, 'UTF-8') ?>)"
        title="Delete story"
        style="flex-shrink:0;width:38px;height:38px;border:none;border-left:1px solid var(--border);background:transparent;color:#f05060;cursor:pointer;font-size:.9rem;display:flex;align-items:center;justify-content:center;transition:background .15s;"
        onmouseover="this.style.background='rgba(240,80,96,.12)'" onmouseout="this.style.background='transparent'">
    <i class="bi bi-trash"></i>
</button>
                
                <?php /*
                <button onclick="deleteStory(<?= $s['id'] ?>, <?= json_encode($s['title']) ?>)"
                        title="Delete story"
                        style="flex-shrink:0;width:38px;height:38px;border:none;border-left:1px solid var(--border);background:transparent;color:#f05060;cursor:pointer;font-size:.9rem;display:flex;align-items:center;justify-content:center;transition:background .15s;"
                        onmouseover="this.style.background='rgba(240,80,96,.12)'" onmouseout="this.style.background='transparent'">
                    <i class="bi bi-trash"></i>
                </button>
                */ ?>
                
                
            </div>
        <?php endforeach; ?>
        <?php if (empty($stories)): ?>
            <div style="color:var(--text-muted);font-family:'Space Mono',monospace;font-size:.8rem;padding:20px;text-align:center;">No stories yet. Create one above.</div>
        <?php endif; ?>
        </div>
    </div>

    <!-- New Story Modal -->
    <div id="newStoryBackdrop" style="position:fixed;inset:0;background:rgba(0,0,0,0.72);z-index:300000;display:none;align-items:center;justify-content:center;backdrop-filter:blur(2px);">
        <div style="width:100%;max-width:440px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:20px;box-shadow:0 10px 40px rgba(0,0,0,0.5);margin:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <div style="font-family:'Space Mono',monospace;font-size:.85rem;color:var(--accent);text-transform:uppercase;letter-spacing:1px;">New Story</div>
                <button onclick="document.getElementById('newStoryBackdrop').style.display='none'" style="background:transparent;border:none;color:var(--text-muted);cursor:pointer;font-size:1.2rem;">&#10005;</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <div>
                    <label style="display:block;font-family:'Space Mono',monospace;font-size:.7rem;color:var(--text-muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">Title</label>
                    <input type="text" id="newStoryTitle" placeholder="Story title…"
                           style="width:100%;box-sizing:border-box;background:var(--surface,var(--card));color:var(--text);border:1px solid var(--border);border-radius:4px;padding:8px 10px;font-family:'Space Mono',monospace;font-size:.8rem;">
                </div>
                <div>
                    <label style="display:block;font-family:'Space Mono',monospace;font-size:.7rem;color:var(--text-muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">Description (optional)</label>
                    <textarea id="newStoryDesc" rows="3" placeholder="Brief synopsis…"
                              style="width:100%;box-sizing:border-box;background:var(--surface,var(--card));color:var(--text);border:1px solid var(--border);border-radius:4px;padding:8px 10px;font-family:'Space Mono',monospace;font-size:.8rem;resize:vertical;"></textarea>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                <button onclick="document.getElementById('newStoryBackdrop').style.display='none'" style="padding:7px 16px;border-radius:4px;border:1px solid var(--border);background:transparent;color:var(--text-muted);font-family:'Space Mono',monospace;font-size:.75rem;cursor:pointer;">Cancel</button>
                <button onclick="createStory()" style="padding:7px 16px;border-radius:4px;border:1px solid var(--accent);background:var(--accent);color:#000;font-family:'Space Mono',monospace;font-size:.75rem;font-weight:bold;cursor:pointer;">Create</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="/js/toast.js"></script>
    <script>
    document.getElementById('newStoryBtn').addEventListener('click', function() {
        document.getElementById('newStoryBackdrop').style.display = 'flex';
        setTimeout(function(){ document.getElementById('newStoryTitle').focus(); }, 50);
    });
    document.getElementById('newStoryBackdrop').addEventListener('mousedown', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
    function createStory() {
        var title = document.getElementById('newStoryTitle').value.trim();
        var desc  = document.getElementById('newStoryDesc').value.trim();
        if (!title) { Toast.show('Title is required.', 'warn'); return; }
        var fd = new URLSearchParams();
        fd.append('action', 'create_story');
        fd.append('title', title);
        fd.append('description', desc);
        fetch('plush_api.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    window.location.href = '?id=' + res.story_id;
                } else {
                    Toast.show(res.message || 'Create failed.', 'error');
                }
            });
    }
    function deleteStory(storyId, title) {
        if (!confirm('Delete story "' + title + '" and ALL its scenes, groups, blocks and entity references?\n\nThis cannot be undone.')) return;
        var fd = new URLSearchParams();
        fd.append('action', 'delete_story');
        fd.append('story_id', storyId);
        fetch('plush_api.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    Toast.show('Story deleted.', 'info');
                    setTimeout(function() { window.location.reload(); }, 700);
                } else {
                    Toast.show(res.message || 'Delete failed.', 'error');
                }
            });
    }
    </script>
    <?php
    $content = ob_get_clean();
    $spw->renderLayout($content, 'Select Story — PLUSH Editor', $spw->getProjectPath() . '/templates/curation.php');
    exit;
}

// ── Load story ────────────────────────────────────────────────────────────────
$storyStmt = $pdo->prepare("SELECT * FROM plush_stories WHERE id = ?");
$storyStmt->execute([$storyId]);
$story = $storyStmt->fetch(PDO::FETCH_ASSOC);
if (!$story) die("<div style='padding:40px;color:red;'>Story #$storyId not found.</div>");

// ── Load scenes for this story ────────────────────────────────────────────────
$scenesStmt = $pdo->prepare("SELECT * FROM plush_scenes WHERE story_id = ? ORDER BY scene_order ASC, id ASC");
$scenesStmt->execute([$storyId]);
$scenes = $scenesStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Load highlight blocks for all scenes ─────────────────────────────────────
$sceneIds = array_column($scenes, 'id');
$highlightsByScene = [];
$groupsByScene     = [];

if (!empty($sceneIds)) {
    $inClause = implode(',', array_fill(0, count($sceneIds), '?'));

    // Load all highlight blocks (en + editLang)
    $stmtH = $pdo->prepare(
        "SELECT * FROM plush_highlight_blocks
         WHERE scene_id IN ($inClause) AND language_code IN ('en', ?)
         ORDER BY group_id ASC, display_order ASC, id ASC"
    );
    $stmtH->execute([...$sceneIds, $editLang]);
    $allBlocks = $stmtH->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allBlocks as $b) {
        $sid = (int)$b['scene_id'];
        if ($b['language_code'] === 'en') {
            $highlightsByScene[$sid][] = $b;
        } else {
            // keyed by [group_id][display_order] for translation lookup
            $highlightsByScene['_tr'][$sid][$b['group_id']][$b['display_order']] = $b;
        }
    }

    // Fetch entity tags for all EN blocks
    $blockIds = array_column($allBlocks, 'id');
    $entitiesByBlock = [];
    if (!empty($blockIds)) {
        $inB = implode(',', array_fill(0, count($blockIds), '?'));
        $stmtE = $pdo->prepare("SELECT * FROM plush_highlight_block_entities WHERE block_id IN ($inB) ORDER BY entity_type ASC");
        $stmtE->execute($blockIds);
        foreach ($stmtE->fetchAll(PDO::FETCH_ASSOC) as $tag) {
            $entitiesByBlock[(int)$tag['block_id']][] = $tag;
        }
    }

    // Load groups
    $stmtG = $pdo->prepare("SELECT * FROM plush_highlight_groups WHERE scene_id IN ($inClause) ORDER BY group_order ASC, id ASC");
    $stmtG->execute($sceneIds);
    foreach ($stmtG->fetchAll(PDO::FETCH_ASSOC) as $g) {
        $groupsByScene[(int)$g['scene_id']][] = $g;
    }
}

// ── Load collections (equiv. of cinemagics) ───────────────────────────────────
$currentCollections = [];
try {
    $stmtCC = $pdo->prepare(
        "SELECT c.id, c.title, cs.sort_order, cs.arc_label
         FROM plush_collections c
         JOIN plush_collections_2_stories cs ON cs.collection_id = c.id
         WHERE cs.story_id = ?
         ORDER BY c.title ASC"
    );
    $stmtCC->execute([$storyId]);
    $currentCollections = $stmtCC->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$allCollections = [];
try {
    $allCollections = $pdo->query("SELECT id, title FROM plush_collections ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$pageTitle = "PLUSH: " . htmlspecialchars($story['title']);
ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
:root, [data-theme="dark"] {
    --pl-bg:          #080b10;
    --pl-surface:     #0e1319;
    --pl-card:        #111820;
    --pl-border:      #1c2535;
    --pl-text:        #c8d4e8;
    --pl-text-dim:    #5a6a80;
    --pl-amber:       #f5a623;
    --pl-red:         #f05060;
    --pl-green:       #4caf80;
    --pl-teal:        #3ab5c8;
    --pl-anim:        #f43f5e;
    --pl-sket:        #a855f7;
    --pl-kg:          #3b82f6;
    --pl-group-bg:    rgba(58,181,200,0.05);
    --pl-group-border:#1e3040;
}
[data-theme="light"] {
    --pl-bg:          #f4f6fa;
    --pl-surface:     #ffffff;
    --pl-card:        #ffffff;
    --pl-border:      #d0d8e8;
    --pl-text:        #1a2233;
    --pl-text-dim:    #7a8aaa;
    --pl-amber:       #c8880a;
    --pl-red:         #d03040;
    --pl-green:       #2e8a58;
    --pl-teal:        #1a8090;
    --pl-anim:        #e11d48;
    --pl-sket:        #9333ea;
    --pl-kg:          #2563eb;
    --pl-group-bg:    rgba(26,128,144,0.04);
    --pl-group-border:#c0d8e0;
}


body { background: var(--pl-bg); color: var(--pl-text); font-family: 'Syne', system-ui, sans-serif; margin: 0; padding: 0; transition: background .2s, color .2s; }

/* ── Nav ──────────────────────────────────────────────────────────────────── */
.pl-nav { display:flex; align-items:center; gap:10px; padding:10px 16px; background:rgba(0,0,0,.6); border-bottom:1px solid var(--pl-border); position:sticky; top:0; z-index:100; backdrop-filter:blur(6px); flex-wrap:wrap; }
[data-theme="light"] .pl-nav { background:rgba(244,246,250,.92); }
.pl-nav-title { font-family:'Space Mono',monospace; font-size:.8rem; color:var(--pl-text); flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; }
.pl-nav-link { font-family:'Space Mono',monospace; font-size:.7rem; padding:6px 12px; border:1px solid var(--pl-border); border-radius:4px; color:var(--pl-text-dim); text-decoration:none; transition:all .2s; background:var(--pl-surface); cursor:pointer; white-space:nowrap; }
.pl-nav-link:hover { color:var(--pl-amber); border-color:var(--pl-amber); }
.pl-nav-link.primary { color:#000; background:var(--pl-amber); border-color:var(--pl-amber); font-weight:bold; }

/* ── Nav icon buttons (collapse-all, export-all) ─────────────────────────── */
.pl-nav-icon-btn {
    width:32px; height:32px; border-radius:4px;
    border:1px solid var(--pl-border); background:var(--pl-surface);
    color:var(--pl-text-dim); font-size:.9rem;
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; transition:all .2s; flex-shrink:0;
}
.pl-nav-icon-btn:hover { border-color:var(--pl-amber); color:var(--pl-amber); background:rgba(245,166,35,.08); }
.pl-nav-icon-btn.active { border-color:var(--pl-teal); color:var(--pl-teal); background:rgba(58,181,200,.1); }

/* ── Workspace ──────────────────────────────────────────────────────────────── */
.workspace { max-width:900px; margin:0 auto; padding:30px 15px 100px; }
.lang-not-en .drag-handle,
.lang-not-en .add-element-btn,
.lang-not-en .add-group-btn,
.lang-not-en .del-btn,
.lang-not-en .del-group-btn,
.lang-not-en .group-drag-handle,
.lang-not-en .entity-add-row,
.lang-not-en .chip-remove { display:none !important; }

/* ── Scene Block ─────────────────────────────────────────────────────────── */
.scene-block { background:var(--pl-card); border:1px solid var(--pl-border); border-radius:6px; padding:16px; margin-bottom:30px; box-shadow:0 4px 20px rgba(0,0,0,.5); }
[data-theme="light"] .scene-block { box-shadow:0 2px 10px rgba(0,0,0,.08); }

.scene-header { display:flex; gap:14px; margin-bottom:15px; border-bottom:1px solid var(--pl-border); padding-bottom:14px; align-items:center; }
.scene-meta { flex:1; }
.scene-num { font-family:'Space Mono',monospace; font-size:.65rem; color:var(--pl-amber); letter-spacing:2px; text-transform:uppercase; margin-bottom:4px; }
.scene-title { font-size:1.05rem; font-weight:bold; margin:0 0 4px; color:var(--pl-text); }
.scene-synopsis { font-size:.8rem; color:var(--pl-text-dim); line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }

/* ── Wrapping Groups ───────────────────────────────────────────────────────── */
.groups-wrap { display:flex; flex-direction:column; gap:16px; }

.highlight-group { border:1px solid var(--pl-group-border); border-radius:5px; background:var(--pl-group-bg); padding:12px 12px 8px; position:relative; }
.highlight-group.is-default { border-color:transparent; background:transparent; padding:0; }

.group-header { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.highlight-group.is-default .group-header { display:none; }

.group-drag-handle { color:var(--pl-text-dim); cursor:grab; font-size:.9rem; opacity:.4; touch-action:none; user-select:none; transition:opacity .2s; padding:2px 4px; }
.highlight-group:hover .group-drag-handle { opacity:1; }
.group-drag-handle:active { cursor:grabbing; }

.group-label-input { flex:1; background:transparent; border:none; border-bottom:1px solid var(--pl-border); color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:.7rem; padding:2px 4px; outline:none; transition:border-color .2s; min-width:0; }
.group-label-input:focus { border-color:var(--pl-teal); color:var(--pl-text); }
.group-label-input::placeholder { opacity:.4; }

.del-group-btn { width:24px; height:24px; border-radius:3px; border:1px solid transparent; background:transparent; color:var(--pl-red); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:.75rem; opacity:.5; transition:all .15s; padding:0; }
.del-group-btn:hover { border-color:var(--pl-red); background:rgba(240,80,96,.1); opacity:1; }

/* ── Overlay Blocks inside groups ────────────────────────────────────────── */
.overlay-list { display:flex; flex-direction:column; gap:10px; }

.overlay-block { position:relative; padding-left:28px; padding-right:40px; transition:transform .2s, opacity .2s; }

.overlay-text { width:100%; box-sizing:border-box; background:var(--pl-surface); border:1px solid var(--pl-border); color:var(--pl-text); font-family:'Syne',system-ui,sans-serif; font-size:.95rem; line-height:1.55; padding:10px 12px; border-radius:4px; resize:none; overflow:hidden; transition:border-color .2s, background .2s; }
.overlay-text:focus { outline:none; border-color:var(--pl-amber); background:var(--pl-card); }
.highlight-group:not(.is-default) .overlay-text:focus { border-color:var(--pl-teal); }

.drag-handle { position:absolute; left:0; top:50%; transform:translateY(-50%); width:28px; height:100%; display:flex; align-items:center; justify-content:center; color:var(--pl-text-dim); font-size:1rem; cursor:grab; opacity:.3; transition:opacity .2s; touch-action:none; user-select:none; }
.overlay-block:hover .drag-handle { opacity:1; }
.drag-handle:active { cursor:grabbing; }

.action-btns { position:absolute; right:0; top:50%; transform:translateY(-50%); display:flex; flex-direction:column; gap:5px; opacity:0; transition:opacity .2s; }
.overlay-block:hover .action-btns,
.overlay-text:focus ~ .action-btns { opacity:1; }
@media (hover:none) { .action-btns { opacity:1; } }

.del-btn { width:28px; height:28px; border-radius:4px; border:1px solid transparent; background:transparent; color:var(--pl-red); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:.85rem; transition:all .15s; }
.del-btn:hover { border-color:var(--pl-red); background:rgba(240,80,96,.1); }
.color-btn { width:28px; height:28px; border-radius:4px; border:1px solid transparent; background:transparent; color:var(--pl-text-dim); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:.85rem; transition:all .15s; }
.color-btn:hover { border-color:var(--pl-amber); color:var(--pl-amber); background:rgba(245,166,35,.1); }

/* ── Per-block export button ─────────────────────────────────────────────── */
.export-block-btn { width:28px; height:28px; border-radius:4px; border:1px solid transparent; background:transparent; color:var(--pl-text-dim); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:.75rem; transition:all .15s; }
.export-block-btn:hover { border-color:var(--pl-teal); color:var(--pl-teal); background:rgba(58,181,200,.1); }

.overlay-block.drag-over-top    { border-top:2px solid var(--pl-amber); padding-top:2px; }
.overlay-block.drag-over-bottom { border-bottom:2px solid var(--pl-amber); padding-bottom:2px; }
.overlay-block.dragging         { opacity:.4; }

/* ── Collapsed block state ───────────────────────────────────────────────── */
.overlay-block.pl-collapsed .overlay-text { display:none; }
.overlay-block.pl-collapsed .beat-entity-chips { display:none; }
.overlay-block.pl-collapsed .entity-add-row { display:none; }
.overlay-block.pl-collapsed .inline-add-row { display:none; }

.pl-collapsed-preview {
    display:none;
    padding:7px 12px;
    background:var(--pl-surface);
    border:1px solid var(--pl-border);
    border-radius:4px;
    font-family:'Syne',system-ui,sans-serif;
    font-size:.85rem;
    color:var(--pl-text-dim);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    cursor:default;
    user-select:none;
    transition:border-color .2s;
}
.overlay-block.pl-collapsed .pl-collapsed-preview { display:block; }

/* Keep block-ref and action-btns visible when collapsed */
.overlay-block.pl-collapsed .block-ref { opacity:.7; }
.overlay-block.pl-collapsed .action-btns { opacity:1; }
.overlay-block.pl-collapsed .drag-handle { opacity:.5; }

/* ── Entity Tags integration ─────────────────────────────────────────────── */
.beat-entity-chips { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }
.beat-entity-chip { display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; border-radius: 12px; font-size: .7rem; font-family: 'Space Mono', monospace; border: 1px solid; }



.chip-char { background: rgba(251,146,60,.1); border-color: var(--pl-amber); color: var(--pl-amber); }
.chip-fact { background: rgba(74,222,128,.1); border-color: var(--pl-green); color: var(--pl-green); }
.chip-loc  { background: rgba(96,165,250,.1); border-color: var(--pl-teal);  color: var(--pl-teal);  }
.chip-anim { background: rgba(244,63,94,.1);  border-color: var(--pl-anim);  color: var(--pl-anim);  }

.chip-sket { background: rgba(168,85,247,.1); border-color: var(--pl-sket);  color: var(--pl-sket);  }
.chip-kg   { background: rgba(59,130,246,.1); border-color: var(--pl-kg);    color: var(--pl-kg);    }
.chip-remove { background: none; border: none; cursor: pointer; color: inherit; opacity: .6; font-size: .75rem; padding: 0; line-height: 1; transition: opacity .15s; }




.chip-remove:hover { opacity: 1; }

.entity-add-row { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-top: 8px; }
.entity-type-tabs { display: flex; gap: 4px; }
.entity-type-tab { padding: 3px 8px; border-radius: 10px; border: 1px solid var(--pl-border); background: transparent; color: var(--pl-text-dim); font-family: 'Space Mono', monospace; font-size: .65rem; cursor: pointer; transition: all .15s; text-transform: uppercase; letter-spacing: 1px; }
.entity-type-tab.active-char { border-color: var(--pl-amber); color: var(--pl-amber); background: rgba(251,146,60,.1); }
.entity-type-tab.active-fact { border-color: var(--pl-green); color: var(--pl-green); background: rgba(74,222,128,.1); }
.entity-type-tab.active-loc  { border-color: var(--pl-teal);  color: var(--pl-teal);  background: rgba(96,165,250,.1);  }
.entity-type-tab.active-anim { border-color: var(--pl-anim);  color: var(--pl-anim);  background: rgba(244,63,94,.1);  }



.entity-type-tab.active-sket { border-color: var(--pl-sket);  color: var(--pl-sket);  background: rgba(168,85,247,.1); }
.entity-type-tab.active-kg   { border-color: var(--pl-kg);    color: var(--pl-kg);    background: rgba(59,130,246,.1); }




.entity-search-wrap { position: relative; flex: 1; min-width: 140px; }
.entity-search-input { width: 100%; background: var(--pl-surface); color: var(--pl-text); border: 1px solid var(--pl-border); border-radius: 4px; padding: 6px 10px; font-family: 'Syne', sans-serif; font-size: .8rem; box-sizing: border-box; }
.entity-search-input:focus { outline: none; border-color: var(--pl-amber); }
.entity-autocomplete { position: absolute; top: 100%; left: 0; right: 0; z-index: 10; background: var(--pl-card); border: 1px solid var(--pl-border); border-top: none; border-radius: 0 0 4px 4px; max-height: 160px; overflow-y: auto; }
.entity-ac-item { padding: 7px 10px; font-size: .8rem; cursor: pointer; transition: background .1s; }
.entity-ac-item:hover { background: rgba(245,166,35,.1); color: var(--pl-amber); }

/* ── Inline + add buttons (after every element AND group) ────────────────── */
.inline-add-row { display:flex; align-items:center; gap:6px; margin:8px 0 2px; opacity:.35; transition:opacity .2s; }
.inline-add-row:hover { opacity:1; }
.inline-add-row .add-divider { flex:1; height:1px; background:var(--pl-border); }
.inline-add-btn { display:flex; align-items:center; gap:4px; padding:3px 10px; border-radius:12px; border:1px dashed var(--pl-border); background:transparent; color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:.65rem; cursor:pointer; transition:all .2s; white-space:nowrap; }
.inline-add-btn.block { color:var(--pl-text-dim); }
.inline-add-btn.group { color:var(--pl-teal); border-color:var(--pl-teal); opacity:.6; }
.inline-add-btn:hover { opacity:1; border-color:var(--pl-amber); color:var(--pl-amber); }
.inline-add-btn.group:hover { border-color:var(--pl-teal); color:var(--pl-teal); }

/* ── Scene-level add buttons ─────────────────────────────────────────────── */
.scene-add-row { display:flex; gap:8px; flex-wrap:wrap; margin-top:14px; padding-top:12px; border-top:1px solid var(--pl-border); }
.add-scene-btn { padding:8px 16px; border-radius:4px; border:1px dashed var(--pl-border); background:transparent; color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:.7rem; cursor:pointer; transition:all .2s; text-transform:uppercase; }
.add-scene-btn:hover { border-color:var(--pl-amber); color:var(--pl-amber); background:rgba(245,166,35,.04); }

/* ── Global add-scene row ────────────────────────────────────────────────── */
.add-story-scene-wrap { max-width:900px; margin:0 auto 40px; padding:0 15px; }
.add-story-scene-btn { width:100%; padding:12px; border-radius:5px; border:1px dashed var(--pl-border); background:transparent; color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:.75rem; cursor:pointer; transition:all .2s; text-transform:uppercase; letter-spacing:1px; }
.add-story-scene-btn:hover { border-color:var(--pl-amber); color:var(--pl-amber); background:rgba(245,166,35,.04); }

/* ── Collections Panel (equiv. cinemagic panel) ──────────────────────────── */
.collection-panel { max-width:900px; margin:0 auto; padding:0 15px 24px; }
.collection-section { background:var(--pl-card); border:1px solid var(--pl-border); border-radius:6px; padding:16px; margin-bottom:20px; }
.collection-section-title { font-family:'Space Mono',monospace; font-size:.7rem; text-transform:uppercase; letter-spacing:2px; color:var(--pl-amber); margin:0; display:flex; align-items:center; gap:8px; cursor:pointer; user-select:none; }
.collection-section-title .pl-chevron { margin-left:auto; font-size:.6rem; transition:transform .25s cubic-bezier(.4,0,.2,1); opacity:.6; }
.collection-section.collapsed .pl-chevron { transform:rotate(-90deg); }
.pl-collapsible { overflow:hidden; max-height:600px; transition:max-height .3s cubic-bezier(.4,0,.2,1), opacity .25s ease; opacity:1; margin-top:14px; }
.collection-section.collapsed .pl-collapsible { max-height:0; opacity:0; }

.collection-badge { display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border:1px solid var(--pl-border); border-radius:4px; background:var(--pl-surface); font-family:'Space Mono',monospace; font-size:.75rem; color:var(--pl-text); margin:0 6px 6px 0; }
.collection-badge .pl-remove { background:none; border:none; color:var(--pl-red); cursor:pointer; padding:0; font-size:.85rem; line-height:1; opacity:.6; transition:opacity .15s; }
.collection-badge .pl-remove:hover { opacity:1; }
.collection-badge .pl-arc-label { font-size:.65rem; color:var(--pl-text-dim); border-left:1px solid var(--pl-border); padding-left:8px; margin-left:2px; }

.pl-assign-row { display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; margin-top:12px; }
.pl-assign-row select, .pl-assign-row input[type="text"] { background:var(--pl-surface); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:7px 10px; font-family:'Space Mono',monospace; font-size:.75rem; flex:1; min-width:120px; }
.pl-assign-row select:focus, .pl-assign-row input[type="text"]:focus { outline:none; border-color:var(--pl-amber); }
.pl-btn { padding:7px 14px; border-radius:4px; border:1px solid; font-family:'Space Mono',monospace; font-size:.75rem; cursor:pointer; transition:all .15s; white-space:nowrap; }
.pl-btn-primary { border-color:var(--pl-amber); background:var(--pl-amber); color:#000; font-weight:bold; }
.pl-btn-primary:hover { filter:brightness(1.1); }
.pl-btn-secondary { border-color:var(--pl-border); background:var(--pl-surface); color:var(--pl-text-dim); }
.pl-btn-secondary:hover { border-color:var(--pl-amber); color:var(--pl-amber); }
.pl-new-form { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; align-items:flex-end; }
.pl-new-form input[type="text"], .pl-new-form textarea { background:var(--pl-surface); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:7px 10px; font-family:'Space Mono',monospace; font-size:.75rem; flex:1; min-width:120px; }
.pl-new-form input[type="text"]:focus, .pl-new-form textarea:focus { outline:none; border-color:var(--pl-amber); }
.pl-empty { font-size:.8rem; color:var(--pl-text-dim); font-style:italic; }
.pl-divider { border:none; border-top:1px solid var(--pl-border); margin:14px 0; }

/* ── Modals ──────────────────────────────────────────────────────── */
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:300000; display:none; align-items:center; justify-content:center; backdrop-filter:blur(2px); }
.modal-backdrop.active { display:flex; }
.modal-box { width:100%; max-width:400px; background:var(--pl-surface); border:1px solid var(--pl-border); border-radius:8px; padding:20px; box-shadow:0 10px 40px rgba(0,0,0,.5); margin:16px; }
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
.modal-title { font-size:1rem; font-weight:bold; font-family:'Space Mono',monospace; color:var(--pl-amber); text-transform:uppercase; letter-spacing:1px; }
.modal-close { background:transparent; border:none; color:var(--pl-text-dim); cursor:pointer; font-size:1.2rem; }

.lang-row { display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:var(--pl-card); border:1px solid var(--pl-border); border-radius:4px; }
.block-ref { position:absolute; top:6px; right:45px; font-family:'Space Mono',monospace; font-size:.6rem; color:var(--pl-text-dim); opacity:.5; pointer-events:none; user-select:none; z-index:5; padding:0 4px; border-radius:3px; letter-spacing:1px; }

/* ── Scene filter autocomplete ───────────────────────────────────────────── */
.pl-scene-filter-wrap { position:relative; }
#sceneFilterAc {
    display:none; position:absolute; top:100%; left:0; right:0; z-index:9999;
    background:var(--pl-card); border:1px solid var(--pl-border);
    border-top:none; border-radius:0 0 4px 4px;
    max-height:220px; overflow-y:auto;
    box-shadow:0 8px 24px rgba(0,0,0,.55);
    min-width:180px;
}
#sceneFilterAc .sfac-item {
    padding:7px 10px; font-size:.75rem; cursor:pointer;
    font-family:'Space Mono',monospace; color:var(--pl-text);
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    transition:background .1s;
}
#sceneFilterAc .sfac-item:hover,
#sceneFilterAc .sfac-item.sfac-active { background:rgba(245,166,35,.1); color:var(--pl-amber); }
#sceneFilterAc .sfac-num { color:var(--pl-text-dim); font-size:.6rem; margin-right:5px; }
</style>


<!-- ── Nav ─────────────────────────────────────────────────────────────────── -->
<div class="pl-nav">
    <a href="plush.php" class="pl-nav-link">&#9664; Stories</a>
    <span class="pl-nav-title">PLUSH: <?= htmlspecialchars($story['title']) ?></span>

    <!-- Scene filter: hides non-matching scene-blocks; export respects visible only -->
    <div class="pl-scene-filter-wrap">
        <input type="search" id="sceneFilterInput" placeholder="Filter scenes…"
               autocomplete="off"
               oninput="filterScenes(this.value);sfAcSearch(this.value)"
               onfocus="this.style.borderColor='var(--pl-amber)';sfAcSearch(this.value)"
               onblur="this.style.borderColor='var(--pl-border)';setTimeout(sfAcHide,200)"
               onkeydown="sfAcKeydown(event)"
               style="background:var(--pl-card);color:var(--pl-text);border:1px solid var(--pl-border);border-radius:4px;padding:5px 8px;font-family:'Space Mono',monospace;font-size:.7rem;width:120px;outline:none;transition:border-color .2s;">
        <div id="sceneFilterAc"></div>
    </div>

    <!-- CHANGE 1: language select shows code only -->
    <select id="editor-lang-select" onchange="window.location.href='?id=<?= $storyId ?>&lang='+this.value" class="pl-nav-link" style="appearance:auto;background:var(--pl-card);">
        <?php foreach ($allLanguages as $l): ?>
            <option value="<?= $l['code'] ?>" <?= $l['code'] === $editLang ? 'selected' : '' ?>><?= strtoupper($l['code']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="pl-nav-link" onclick="openLangModal()" title="System Languages">
        <i class="bi bi-globe"></i>
    </button>
    <!-- CHANGE 2: Collapse All toggle button -->
    <button class="pl-nav-icon-btn" id="collapseAllBtn" onclick="toggleCollapseAll()" title="Collapse / Expand all blocks">
        <i class="bi bi-arrows-collapse" id="collapseAllIcon"></i>
    </button>
    <!-- CHANGE 3: Export All button -->
    <button class="pl-nav-icon-btn" id="exportAllBtn" onclick="exportAll()" title="Export all blocks">
        <i class="bi bi-download"></i>
    </button>
</div>

<!-- ── Collections Assignment Panel ────────────────────────────────────────── -->
<div class="collection-panel">
    <div class="collection-section">
        <h3 class="collection-section-title" id="col-section-title">&#128218; Story Collections <span class="pl-chevron">&#9660;</span></h3>

        <div class="pl-collapsible" id="col-collapsible">
            <div id="col-badges-wrap">
                <?php if (empty($currentCollections)): ?>
                    <span class="pl-empty" id="col-empty-msg">Not assigned to any collection yet.</span>
                <?php else: ?>
                    <?php foreach ($currentCollections as $col): ?>
                        <span class="collection-badge" data-collection-id="<?= $col['id'] ?>">
                            <span>#<?= $col['id'] ?> <?= htmlspecialchars($col['title']) ?></span>
                            <?php if (!empty($col['arc_label'])): ?>
                                <span class="pl-arc-label"><?= htmlspecialchars($col['arc_label']) ?></span>
                            <?php endif; ?>
                            <button class="pl-remove" data-collection-id="<?= $col['id'] ?>">&#10005;</button>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <hr class="pl-divider">

            <div class="pl-assign-row" id="col-assign-row"<?= empty($allCollections) ? ' style="display:none"' : '' ?>>
                <select id="col-select">
                    <option value="">— Select Collection —</option>
                    <?php foreach ($allCollections as $col): ?>
                        <option value="<?= $col['id'] ?>"><?= htmlspecialchars($col['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="col-label-input" placeholder="Arc label (optional)" style="max-width:180px;">
                <button class="pl-btn pl-btn-primary" id="col-assign-btn">+ Assign</button>
            </div>

            <hr class="pl-divider">

            <div style="font-family:'Space Mono',monospace;font-size:.7rem;color:var(--pl-text-dim);margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">Create New Collection</div>
            <div class="pl-new-form">
                <input type="text" id="col-new-title" placeholder="Collection title…" style="flex:2;">
                <input type="text" id="col-new-desc"  placeholder="Description (optional)">
                <button class="pl-btn pl-btn-secondary" id="col-create-btn">Create &amp; Assign</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Editor Workspace ─────────────────────────────────────────────────────── -->
<div class="workspace <?= $editLang !== 'en' ? 'lang-not-en' : '' ?>" id="plush-workspace">

    <?php if (empty($scenes)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--pl-text-dim);font-family:'Space Mono',monospace;font-size:.85rem;">
            No scenes yet.<br>
            <button class="add-story-scene-btn" style="margin-top:20px;max-width:300px;" data-action="add-scene">+ Add First Scene</button>
        </div>
    <?php else: ?>
        <?php foreach ($scenes as $sIdx => $scene):
            $sceneId    = (int)$scene['id'];
            $enBlocks   = $highlightsByScene[$sceneId] ?? [];
            $trBlocks   = $highlightsByScene['_tr'][$sceneId] ?? [];
            $groups     = $groupsByScene[$sceneId] ?? [];

            // Build a map: group_id -> [blocks]
            $blocksByGroup = [];
            foreach ($enBlocks as $b) {
                $gid = $b['group_id'] ?? 0;
                $blocksByGroup[$gid][] = $b;
            }

            // Default group (group_id = 0 or NULL means ungrouped)
            $ungroupedBlocks = $blocksByGroup[0] ?? $blocksByGroup[''] ?? [];
        ?>
        <div class="scene-block" data-scene-id="<?= $sceneId ?>">
            <div class="scene-header">
                <div class="scene-meta">
                    <div class="scene-num">Scene <?= sprintf('%02d', $sIdx + 1) ?> &bull; #<?= $sceneId ?></div>
                    <h3 class="scene-title"><?= htmlspecialchars($scene['title']) ?></h3>
                    <?php if (!empty($scene['synopsis'])): ?>
                        <div class="scene-synopsis"><?= htmlspecialchars($scene['synopsis']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Groups wrap -->
            <div class="groups-wrap" data-scene-id="<?= $sceneId ?>">

                <!-- Ungrouped / default group -->
                <div class="highlight-group is-default" data-group-id="0" data-scene-id="<?= $sceneId ?>">
                    <div class="overlay-list">
                        <?php foreach ($ungroupedBlocks as $b):
                            $blockId     = $b['id'];
                            $dispOrder   = $b['display_order'];
                            $textVal     = $b['text_content'];
                            if ($editLang !== 'en') {
                                $tr = $trBlocks[0][$dispOrder] ?? null;
                                $textVal = $tr ? $tr['text_content'] : '';
                            }
                            // Preview: first 50 chars
                            $previewText = mb_strlen($textVal) > 50 ? mb_substr($textVal, 0, 50) . '…' : ($textVal ?: '(empty)');
                        ?>
                            <div class="overlay-block" data-block-id="<?= $blockId ?>" data-display-order="<?= $dispOrder ?>" data-group-id="0" data-text-content="<?= htmlspecialchars($textVal) ?>">
                                <div class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>
                                <span class="block-ref">[<?= $sceneId ?> . 0 . <span class="ref-order"><?= $dispOrder ?></span>]</span>
                                <!-- CHANGE 4: collapsed preview div -->
                                <div class="pl-collapsed-preview" title="<?= htmlspecialchars($textVal) ?>"><?= htmlspecialchars($previewText) ?></div>
                                <?php $bgColor = !empty($b['bg_color']) ? 'background-color:' . htmlspecialchars($b['bg_color']) . ' !important;' : ''; ?>
                                <textarea class="overlay-text" style="<?= $bgColor ?>" placeholder="<?= $editLang === 'en' ? 'Type highlight text…' : htmlspecialchars($b['text_content']) ?>"><?= htmlspecialchars($textVal) ?></textarea>
                                <div class="action-btns">
                                    <button class="color-btn" title="Set Color"><i class="bi bi-palette"></i></button>
                                    <!-- CHANGE 4: per-block export button -->
                                    <button class="export-block-btn" title="Export this block"><i class="bi bi-download"></i></button>
                                    <button class="del-btn" title="Delete Block"><i class="bi bi-trash"></i></button>
                                </div>
                                
                                <?php
                                $bTags = $entitiesByBlock[$blockId] ?? [];
                                $chipsHtml = '';
                                foreach ($bTags as $tag) {
                                    $cls = $tag['entity_type'] === 'characters' ? 'chip-char' : ($tag['entity_type'] === 'factions' ? 'chip-fact' : ($tag['entity_type'] === 'locations' ? 'chip-loc' : ($tag['entity_type'] === 'animas' ? 'chip-anim' : ($tag['entity_type'] === 'sketches' ? 'chip-sket' : 'chip-kg'))));

                                    
                                    
                                    
                                    $lbl = htmlspecialchars($tag['entity_label']);
                                    $chipsHtml .= "<span class=\"beat-entity-chip $cls\" data-tag-id=\"{$tag['id']}\" data-entity-type=\"{$tag['entity_type']}\" data-entity-id=\"{$tag['entity_id']}\" data-entity-label=\"{$lbl}\">
                                    
                                    
                                    
                                        <span class=\"chip-label\" style=\"cursor:pointer;\" onclick=\"openEntityModal('".htmlspecialchars($tag['entity_type'])."','{$tag['entity_id']}','".addslashes($tag['entity_label'])."')\">{$lbl}</span>
                                   
                                   
                                        <!--
                                        <span class=\"chip-label\" style=\"cursor:pointer;\" onclick=\"openEntityModal('".htmlspecialchars($tag['entity_type'])."','{$tag['entity_id']}','{$lbl}')\">{$lbl}</span>
                                        -->
                                        
                                        
                                        
                                        <button class=\"chip-remove\" onclick=\"removeBlockEntityTag({$tag['id']},{$blockId})\" title=\"Remove\"><i class=\"bi bi-x\"></i></button>
                                    </span>";
                                }
                                ?>
                                <div class="beat-entity-chips" id="chips-<?= $blockId ?>"><?= $chipsHtml ?></div>
                                
                                <div class="entity-add-row">
                                    <div class="entity-type-tabs">
                                        <button class="entity-type-tab active-char" id="tab-char-<?= $blockId ?>" onclick="setEntityTab(<?= $blockId ?>,'characters')">Char</button>
                                        <button class="entity-type-tab" id="tab-fact-<?= $blockId ?>" onclick="setEntityTab(<?= $blockId ?>,'factions')">Fac</button>
                                        <button class="entity-type-tab" id="tab-loc-<?= $blockId ?>"  onclick="setEntityTab(<?= $blockId ?>,'locations')">Loc</button>
                                        <button class="entity-type-tab" id="tab-anim-<?= $blockId ?>" onclick="setEntityTab(<?= $blockId ?>,'animas')">Anim</button>
                                        
                                        
                                        
                                        <button class="entity-type-tab" id="tab-sket-<?= $blockId ?>" onclick="setEntityTab(<?= $blockId ?>,'sketches')">Sket</button>
                                        <button class="entity-type-tab" id="tab-kg-<?= $blockId ?>" onclick="setEntityTab(<?= $blockId ?>,'kg_nodes')">KG</button>
                                    </div>
                                    
                                    
                                    
                                    
                                    <div class="entity-search-wrap">
                                        <input class="entity-search-input" type="text" placeholder="Search entity references…" id="esearch-<?= $blockId ?>"
                                               oninput="entitySearchInput(<?= $blockId ?>,this.value)" onfocus="entitySearchInput(<?= $blockId ?>,this.value)"
                                               onblur="setTimeout(()=>hideAC(<?= $blockId ?>),200)">
                                        <div class="entity-autocomplete" id="eac-<?= $blockId ?>" style="display:none;"></div>
                                    </div>
                                </div>
                                
                            </div>
                            <!-- Inline + add row after every element -->
                            <div class="inline-add-row add-element-btn" data-scene-id="<?= $sceneId ?>" data-group-id="0" data-after-block-id="<?= $blockId ?>">
                                <div class="add-divider"></div>
                                <button class="inline-add-btn block" title="Add text block here"><i class="bi bi-plus"></i> block</button>
                                <button class="inline-add-btn group" title="Add group here"><i class="bi bi-folder-plus"></i> group</button>
                                <div class="add-divider"></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($ungroupedBlocks)): ?>
                            <!-- Inline add row even when list is empty -->
                            <div class="inline-add-row add-element-btn" data-scene-id="<?= $sceneId ?>" data-group-id="0" data-after-block-id="0">
                                <div class="add-divider"></div>
                                <button class="inline-add-btn block"><i class="bi bi-plus"></i> block</button>
                                <button class="inline-add-btn group"><i class="bi bi-folder-plus"></i> group</button>
                                <div class="add-divider"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Named groups -->
                <?php foreach ($groups as $group):
                    $gid       = (int)$group['id'];
                    $gBlocks   = $blocksByGroup[$gid] ?? [];
                ?>
                <div class="highlight-group" data-group-id="<?= $gid ?>" data-scene-id="<?= $sceneId ?>">
                    <div class="group-header">
                        <div class="group-drag-handle" title="Drag group"><i class="bi bi-grip-vertical"></i></div>
                        <input class="group-label-input" type="text" value="<?= htmlspecialchars($group['label'] ?? '') ?>" placeholder="Group label (optional)…" data-group-id="<?= $gid ?>">
                        <button class="del-group-btn" data-group-id="<?= $gid ?>" title="Delete group"><i class="bi bi-trash"></i></button>
                    </div>
                    <div class="overlay-list">
                        <?php foreach ($gBlocks as $b):
                            $blockId   = $b['id'];
                            $dispOrder = $b['display_order'];
                            $textVal   = $b['text_content'];
                            if ($editLang !== 'en') {
                                $tr = $trBlocks[$gid][$dispOrder] ?? null;
                                $textVal = $tr ? $tr['text_content'] : '';
                            }
                            // Preview: first 50 chars
                            $previewText = mb_strlen($textVal) > 50 ? mb_substr($textVal, 0, 50) . '…' : ($textVal ?: '(empty)');
                        ?>
                            <div class="overlay-block" data-block-id="<?= $blockId ?>" data-display-order="<?= $dispOrder ?>" data-group-id="<?= $gid ?>" data-text-content="<?= htmlspecialchars($textVal) ?>">
                                <div class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>
                                <span class="block-ref">[<?= $sceneId ?> . <?= $gid ?> . <span class="ref-order"><?= $dispOrder ?></span>]</span>
                                <!-- CHANGE 4: collapsed preview div -->
                                <div class="pl-collapsed-preview" title="<?= htmlspecialchars($textVal) ?>"><?= htmlspecialchars($previewText) ?></div>
                                <?php $bgColor = !empty($b['bg_color']) ? 'background-color:' . htmlspecialchars($b['bg_color']) . ' !important;' : ''; ?>
                                <textarea class="overlay-text" style="<?= $bgColor ?>" placeholder="<?= $editLang === 'en' ? 'Type highlight text…' : htmlspecialchars($b['text_content']) ?>"><?= htmlspecialchars($textVal) ?></textarea>
                                <div class="action-btns">
                                    <button class="color-btn" title="Set Color"><i class="bi bi-palette"></i></button>
                                    <!-- CHANGE 4: per-block export button -->
                                    <button class="export-block-btn" title="Export this block"><i class="bi bi-download"></i></button>
                                    <button class="del-btn" title="Delete Block"><i class="bi bi-trash"></i></button>
                                </div>

                               <?php
                                $bTags = $entitiesByBlock[$blockId] ?? [];
                                $chipsHtml = '';
                                foreach ($bTags as $tag) {
                                    $cls = $tag['entity_type'] === 'characters' ? 'chip-char' : ($tag['entity_type'] === 'factions' ? 'chip-fact' : ($tag['entity_type'] === 'locations' ? 'chip-loc' : ($tag['entity_type'] === 'animas' ? 'chip-anim' : ($tag['entity_type'] === 'sketches' ? 'chip-sket' : 'chip-kg'))));

                                    
                                    $lbl = htmlspecialchars($tag['entity_label']);
                                    $chipsHtml .= "<span class=\"beat-entity-chip $cls\" data-tag-id=\"{$tag['id']}\" data-entity-type=\"{$tag['entity_type']}\" data-entity-id=\"{$tag['entity_id']}\" data-entity-label=\"{$lbl}\">
                                    
                                    
                                        <span class=\"chip-label\" style=\"cursor:pointer;\" onclick=\"openEntityModal('".htmlspecialchars($tag['entity_type'])."','{$tag['entity_id']}','".addslashes($tag['entity_label'])."')\">{$lbl}</span>
                                    
                                    
                                        <!--
                                        <span class=\"chip-label\" style=\"cursor:pointer;\" onclick=\"openEntityModal('".htmlspecialchars($tag['entity_type'])."','{$tag['entity_id']}','{$lbl}')\">{$lbl}</span>
                                        -->
                                        
                                        
                                        <button class=\"chip-remove\" onclick=\"removeBlockEntityTag({$tag['id']},{$blockId})\" title=\"Remove\"><i class=\"bi bi-x\"></i></button>
                                    </span>";
                                }
                                ?>
                                <div class="beat-entity-chips" id="chips-<?= $blockId ?>"><?= $chipsHtml ?></div>
                                
                                <div class="entity-add-row">
                                    <div class="entity-type-tabs">
                                        <button class="entity-type-tab active-char" id="tab-char-<?= $blockId ?>" onclick="setEntityTab(<?= $blockId ?>,'characters')">Char</button>
                                        <button class="entity-type-tab" id="tab-fact-<?= $blockId ?>" onclick="setEntityTab(<?= $blockId ?>,'factions')">Fac</button>
                                        <button class="entity-type-tab" id="tab-loc-<?= $blockId ?>"  onclick="setEntityTab(<?= $blockId ?>,'locations')">Loc</button>
                                        <button class="entity-type-tab" id="tab-anim-<?= $blockId ?>" onclick="setEntityTab(<?= $blockId ?>,'animas')">Anim</button>
                                        
                                        
                                       <button class="entity-type-tab" id="tab-sket-<?= $blockId ?>" onclick="setEntityTab(<?= $blockId ?>,'sketches')">Sket</button>
                                        <button class="entity-type-tab" id="tab-kg-<?= $blockId ?>" onclick="setEntityTab(<?= $blockId ?>,'kg_nodes')">KG</button>
                                    </div>
                                    
                                    
                                    
                                    
                                    <div class="entity-search-wrap">
                                        <input class="entity-search-input" type="text" placeholder="Search entity references…" id="esearch-<?= $blockId ?>"
                                               oninput="entitySearchInput(<?= $blockId ?>,this.value)" onfocus="entitySearchInput(<?= $blockId ?>,this.value)"
                                               onblur="setTimeout(()=>hideAC(<?= $blockId ?>),200)">
                                        <div class="entity-autocomplete" id="eac-<?= $blockId ?>" style="display:none;"></div>
                                    </div>
                                </div>

                            </div>
                            <!-- Inline + add row after every element inside group -->
                            <div class="inline-add-row add-element-btn" data-scene-id="<?= $sceneId ?>" data-group-id="<?= $gid ?>" data-after-block-id="<?= $blockId ?>">
                                <div class="add-divider"></div>
                                <button class="inline-add-btn block"><i class="bi bi-plus"></i> block</button>
                                <div class="add-divider"></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($gBlocks)): ?>
                            <div class="inline-add-row add-element-btn" data-scene-id="<?= $sceneId ?>" data-group-id="<?= $gid ?>" data-after-block-id="0">
                                <div class="add-divider"></div>
                                <button class="inline-add-btn block"><i class="bi bi-plus"></i> block</button>
                                <div class="add-divider"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Inline add row after each named group (in default zone) -->
                <div class="inline-add-row add-group-btn" data-scene-id="<?= $sceneId ?>" data-after-group-id="<?= $gid ?>">
                    <div class="add-divider"></div>
                    <button class="inline-add-btn group"><i class="bi bi-folder-plus"></i> group</button>
                    <div class="add-divider"></div>
                </div>
                <?php endforeach; ?>

                <!-- Final add row if no named groups or after last group -->
                <?php if (empty($groups)): ?>
                    <div class="inline-add-row add-group-btn" data-scene-id="<?= $sceneId ?>" data-after-group-id="0">
                        <div class="add-divider"></div>
                        <button class="inline-add-btn group"><i class="bi bi-folder-plus"></i> new group</button>
                        <div class="add-divider"></div>
                    </div>
                <?php endif; ?>

            </div><!-- /.groups-wrap -->
        </div><!-- /.scene-block -->

        <?php endforeach; ?>
    <?php endif; ?>

</div><!-- /#plush-workspace -->

<!-- Add Scene button at bottom -->
<div class="add-story-scene-wrap">
    <button class="add-story-scene-btn" data-action="add-scene">+ Add New Scene</button>
</div>

<!-- ── Language Manager Modal ─────────────────────────────────────────────── -->
<div class="modal-backdrop" id="langModalBackdrop" onmousedown="if(event.target===this)closeLangModal()">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">System Languages</div>
            <button class="modal-close" onclick="closeLangModal()">&#10005;</button>
        </div>
        <div style="display:flex;gap:8px;margin-bottom:16px;">
            <input type="text" id="lang-code" placeholder="Code" maxlength="2" style="width:60px;text-transform:lowercase;background:var(--pl-card);color:var(--pl-text);border:1px solid var(--pl-border);border-radius:4px;padding:6px;font-family:'Space Mono',monospace;">
            <input type="text" id="lang-name" placeholder="Language Name" style="flex:1;background:var(--pl-card);color:var(--pl-text);border:1px solid var(--pl-border);border-radius:4px;padding:6px;font-family:'Syne',sans-serif;">
            <button class="pl-btn pl-btn-primary" onclick="saveLanguage()">Save</button>
        </div>
        <div id="lang-list" style="display:flex;flex-direction:column;gap:8px;max-height:300px;overflow-y:auto;"></div>
    </div>
</div>

<!-- ── Entity Details Modal ─────────────────────────────────────────────── -->
<div class="modal-backdrop" id="entity-modal-backdrop" onmousedown="if(event.target===this)closeEntityModal()">
    <div class="modal-box" style="max-width:700px; height:85vh; display:flex; flex-direction:column; padding:0; overflow:hidden;">
        <div class="modal-header" style="padding:10px 14px; border-bottom:1px solid var(--pl-border); margin-bottom:0; background:var(--pl-surface);">
            <span class="modal-title" id="entityModalTitle">Entity Details</span>
            <button class="modal-close" onclick="closeEntityModal()">&#10005;</button>
        </div>
        <iframe id="entity-iframe" src="about:blank" style="flex:1; border:none; width:100%; background:var(--pl-card);"></iframe>
    </div>
</div>

<!-- Color Picker Popup -->
<div id="colorPickerPopup" style="display:none; position:absolute; z-index:300000; background:var(--pl-card); border:1px solid var(--pl-border); border-radius:6px; padding:8px; box-shadow:0 4px 12px rgba(0,0,0,0.4); gap:6px; flex-wrap:wrap; width:110px;">
    <button onclick="setBlockColor('rgba(255, 99, 132, 0.15)')" style="width:24px;height:24px;border-radius:50%;border:1px solid var(--pl-border);background:rgba(255, 99, 132, 0.3);cursor:pointer;"></button>
    <button onclick="setBlockColor('rgba(54, 162, 235, 0.15)')" style="width:24px;height:24px;border-radius:50%;border:1px solid var(--pl-border);background:rgba(54, 162, 235, 0.3);cursor:pointer;"></button>
    <button onclick="setBlockColor('rgba(75, 192, 192, 0.15)')" style="width:24px;height:24px;border-radius:50%;border:1px solid var(--pl-border);background:rgba(75, 192, 192, 0.3);cursor:pointer;"></button>
    <button onclick="setBlockColor('rgba(255, 206, 86, 0.15)')" style="width:24px;height:24px;border-radius:50%;border:1px solid var(--pl-border);background:rgba(255, 206, 86, 0.3);cursor:pointer;"></button>
    <button onclick="setBlockColor('rgba(153, 102, 255, 0.15)')" style="width:24px;height:24px;border-radius:50%;border:1px solid var(--pl-border);background:rgba(153, 102, 255, 0.3);cursor:pointer;"></button>
    <button onclick="setBlockColor('rgba(255, 159, 64, 0.15)')" style="width:24px;height:24px;border-radius:50%;border:1px solid var(--pl-border);background:rgba(255, 159, 64, 0.3);cursor:pointer;"></button>
    <button onclick="setBlockColor('')" title="Reset Color" style="width:24px;height:24px;border-radius:50%;border:1px dashed var(--pl-text-dim);background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--pl-text-dim);"><i class="bi bi-x-lg"></i></button>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>

<script>
(function() {
'use strict';

const STORY_ID  = <?= $storyId ?>;
const EDIT_LANG = <?= json_encode($editLang) ?>;

// ── Helpers ──────────────────────────────────────────────────────────────────
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function autoResize(ta) {
    ta.style.height = 'auto';
    ta.style.height = ta.scrollHeight + 'px';
}

// ── Collections collapse state ───────────────────────────────────────────────
const COL_STORAGE_KEY = 'sage_plush_col_collapsed';
const colSection = document.querySelector('.collection-section');
const colTitle   = document.getElementById('col-section-title');
try { if (localStorage.getItem(COL_STORAGE_KEY) === '1') colSection.classList.add('collapsed'); } catch(e) {}
colTitle.addEventListener('click', () => {
    const c = !colSection.classList.contains('collapsed');
    colSection.classList.toggle('collapsed', c);
    try { localStorage.setItem(COL_STORAGE_KEY, c ? '1' : '0'); } catch(e) {}
});

// ── Collections logic ────────────────────────────────────────────────────────
function refreshColEmpty() {
    const wrap = document.getElementById('col-badges-wrap');
    let emptyEl = document.getElementById('col-empty-msg');
    const hasBadges = wrap.querySelectorAll('.collection-badge').length > 0;
    if (hasBadges && emptyEl) { emptyEl.remove(); return; }
    if (!hasBadges && !emptyEl) {
        emptyEl = document.createElement('span');
        emptyEl.className = 'pl-empty'; emptyEl.id = 'col-empty-msg';
        emptyEl.textContent = 'Not assigned to any collection yet.';
        wrap.appendChild(emptyEl);
    }
}
function addColBadge(id, title, label) {
    const wrap = document.getElementById('col-badges-wrap');
    if (wrap.querySelector(`.collection-badge[data-collection-id="${id}"]`)) return;
    const span = document.createElement('span');
    span.className = 'collection-badge'; span.dataset.collectionId = id;
    span.innerHTML = `<span>#${id} ${escHtml(title)}</span>${label ? `<span class="pl-arc-label">${escHtml(label)}</span>` : ''}<button class="pl-remove" data-collection-id="${id}">&#10005;</button>`;
    wrap.appendChild(span);
    bindRemoveBadge(span.querySelector('.pl-remove'));
    refreshColEmpty();
}
function bindRemoveBadge(btn) {
    btn.addEventListener('click', () => {
        const colId = parseInt(btn.dataset.collectionId, 10);
        const fd = new URLSearchParams();
        fd.append('action', 'remove_from_collection');
        fd.append('collection_id', colId);
        fd.append('story_id', STORY_ID);
        fetch('plush_api.php', { method:'POST', body:fd })
            .then(r=>r.json()).then(res => {
                if (res.success) {
                    document.querySelector(`.collection-badge[data-collection-id="${colId}"]`)?.remove();
                    refreshColEmpty();
                    Toast.show('Removed from collection.', 'info');
                } else { Toast.show(res.message || 'Remove failed.', 'error'); }
            });
    });
}
document.querySelectorAll('.pl-remove').forEach(bindRemoveBadge);

document.getElementById('col-assign-btn').addEventListener('click', () => {
    const colId = parseInt(document.getElementById('col-select').value, 10);
    const label = document.getElementById('col-label-input').value.trim();
    if (!colId) { Toast.show('Please select a collection.', 'warn'); return; }
    const title = document.getElementById('col-select').selectedOptions[0]?.text || '';
    const fd = new URLSearchParams();
    fd.append('action', 'assign_to_collection');
    fd.append('collection_id', colId); fd.append('story_id', STORY_ID); fd.append('arc_label', label);
    fetch('plush_api.php', { method:'POST', body:fd })
        .then(r=>r.json()).then(res => {
            if (res.success) {
                addColBadge(colId, title, label);
                document.getElementById('col-label-input').value = '';
                Toast.show('Assigned to collection!', 'success');
            } else { Toast.show(res.message || 'Assignment failed.', 'error'); }
        });
});

document.getElementById('col-create-btn').addEventListener('click', () => {
    const title = document.getElementById('col-new-title').value.trim();
    const desc  = document.getElementById('col-new-desc').value.trim();
    if (!title) { Toast.show('Enter a title for the new collection.', 'warn'); return; }
    const fd = new URLSearchParams();
    fd.append('action', 'create_collection'); fd.append('title', title); fd.append('description', desc);
    fetch('plush_api.php', { method:'POST', body:fd })
        .then(r=>r.json()).then(res => {
            if (!res.success) { Toast.show(res.message || 'Create failed.', 'error'); return; }
            const newId = res.collection_id;
            const fd2 = new URLSearchParams();
            fd2.append('action', 'assign_to_collection');
            fd2.append('collection_id', newId); fd2.append('story_id', STORY_ID);
            return fetch('plush_api.php', { method:'POST', body:fd2 })
                .then(r2=>r2.json()).then(res2 => {
                    if (res2.success) {
                        addColBadge(newId, title, '');
                        const sel = document.getElementById('col-select');
                        if (sel) { const opt = document.createElement('option'); opt.value = newId; opt.text = title; sel.appendChild(opt); }
                        document.getElementById('col-assign-row').style.display = '';
                        document.getElementById('col-new-title').value = '';
                        document.getElementById('col-new-desc').value = '';
                        Toast.show('Collection created and assigned!', 'success');
                    }
                });
        });
});

// ── Save timer ───────────────────────────────────────────────────────────────
let saveTimer = null;
function debounceSave(blockId, sceneId, groupId, displayOrder, text) {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
        const fd = new URLSearchParams();
        fd.append('action', 'update_block');
        fd.append('block_id', blockId); fd.append('scene_id', sceneId);
        fd.append('group_id', groupId); fd.append('display_order', displayOrder);
        fd.append('lang', EDIT_LANG); fd.append('text', text);
        fetch('plush_api.php', { method:'POST', body:fd });
    }, 500);
}

// ── Color Picker Logic ────────────────────────────────────────────────────────
let activeColorBlockId = null;
let activeColorElement = null;

window.openColorPicker = function(e, blockId, blockElement) {
    if (EDIT_LANG !== 'en') { Toast.show('Change colors in English first.', 'warn'); return; }
    activeColorBlockId = blockId;
    activeColorElement = blockElement;
    
    const popup = document.getElementById('colorPickerPopup');
    const rect = e.currentTarget.getBoundingClientRect();
    
    popup.style.display = 'flex';
    popup.style.top = (rect.top + window.scrollY - 10) + 'px';
    popup.style.left = (rect.left + window.scrollX - popup.offsetWidth - 10) + 'px';
};

document.addEventListener('mousedown', e => {
    const popup = document.getElementById('colorPickerPopup');
    if (popup && popup.style.display === 'flex' && !popup.contains(e.target) && !e.target.closest('.color-btn')) {
        popup.style.display = 'none';
    }
});

window.setBlockColor = function(color) {
    if (!activeColorBlockId || !activeColorElement) return;
    
    const ta = activeColorElement.querySelector('.overlay-text');
    if (color) {
        ta.style.setProperty('background-color', color, 'important');
    } else {
        ta.style.backgroundColor = '';
    }
    
    const fd = new URLSearchParams();
    fd.append('action', 'update_block_color');
    fd.append('block_id', activeColorBlockId);
    fd.append('color', color);
    fetch('plush_api.php', { method: 'POST', body: fd });
    
    document.getElementById('colorPickerPopup').style.display = 'none';
};

// ── Scene filter autocomplete ─────────────────────────────────────────────
// Data embedded server-side: no extra fetch needed.
const SCENE_LIST = <?= json_encode(array_map(fn($s) => [
    'id'    => (int)$s['id'],
    'title' => $s['title'],
    'num'   => 'Scene ' . sprintf('%02d', array_search($s, $scenes) + 1),
], $scenes), JSON_UNESCAPED_UNICODE) ?>;

let sfAcIdx = -1;

function sfAcHide() {
    const ac = document.getElementById('sceneFilterAc');
    if (ac) { ac.style.display = 'none'; ac.innerHTML = ''; }
    sfAcIdx = -1;
}

window.sfAcSearch = function(q) {
    const ac = document.getElementById('sceneFilterAc');
    const term = (q || '').trim().toLowerCase();
    const matches = term
        ? SCENE_LIST.filter(s => s.title.toLowerCase().includes(term) || s.num.toLowerCase().includes(term) || String(s.id).includes(term))
        : SCENE_LIST;
    if (!matches.length) { sfAcHide(); return; }
    sfAcIdx = -1;
    ac.innerHTML = matches.slice(0, 20).map((s, i) =>
        `<div class="sfac-item" data-title="${escHtml(s.title)}" data-idx="${i}">
            <span class="sfac-num">${escHtml(s.num)} #${s.id}</span>${escHtml(s.title)}
        </div>`
    ).join('');
    ac.querySelectorAll('.sfac-item').forEach(item => {
        item.addEventListener('pointerdown', () => sfAcPick(item.dataset.title));
    });
    ac.style.display = 'block';
};

function sfAcPick(title) {
    const inp = document.getElementById('sceneFilterInput');
    inp.value = title;
    sfAcHide();
    filterScenes(title);
}

window.sfAcKeydown = function(e) {
    const ac = document.getElementById('sceneFilterAc');
    const items = ac.querySelectorAll('.sfac-item');
    if (!items.length) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        sfAcIdx = Math.min(sfAcIdx + 1, items.length - 1);
        items.forEach((el, i) => el.classList.toggle('sfac-active', i === sfAcIdx));
        items[sfAcIdx]?.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        sfAcIdx = Math.max(sfAcIdx - 1, 0);
        items.forEach((el, i) => el.classList.toggle('sfac-active', i === sfAcIdx));
        items[sfAcIdx]?.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
        if (sfAcIdx >= 0 && items[sfAcIdx]) {
            e.preventDefault();
            sfAcPick(items[sfAcIdx].dataset.title);
        }
    } else if (e.key === 'Escape') {
        sfAcHide();
    }
};

// ── Scene filter ──────────────────────────────────────────────────────────────
window.filterScenes = function(q) {
    const term = q.trim().toLowerCase();
    document.querySelectorAll('#plush-workspace .scene-block').forEach(sb => {
        if (!term) {
            sb.style.display = '';
            return;
        }
        const title = sb.querySelector('.scene-title')?.textContent.toLowerCase() || '';
        const num   = sb.querySelector('.scene-num')?.textContent.toLowerCase() || '';
        sb.style.display = (title.includes(term) || num.includes(term)) ? '' : 'none';
    });
};

// ── CHANGE 5: Collapse All toggle ────────────────────────────────────────────
let allCollapsed = false;

window.toggleCollapseAll = function() {
    allCollapsed = !allCollapsed;
    const blocks = document.querySelectorAll('#plush-workspace .overlay-block');
    blocks.forEach(block => {
        if (allCollapsed) {
            block.classList.add('pl-collapsed');
            // Update preview text from current textarea value
            const ta = block.querySelector('.overlay-text');
            const preview = block.querySelector('.pl-collapsed-preview');
            if (ta && preview) {
                const txt = ta.value || '';
                const short = txt.length > 50 ? txt.slice(0, 50) + '…' : (txt || '(empty)');
                preview.textContent = short;
                preview.title = txt;
            }
        } else {
            block.classList.remove('pl-collapsed');
        }
    });
    const btn  = document.getElementById('collapseAllBtn');
    const icon = document.getElementById('collapseAllIcon');
    btn.classList.toggle('active', allCollapsed);
    icon.className = allCollapsed ? 'bi bi-arrows-expand' : 'bi bi-arrows-collapse';
};

// ── CHANGE 6: Export helpers ──────────────────────────────────────────────────

// Collect block data from DOM for a single block element
function collectBlockData(blockEl) {
    const blockId    = blockEl.dataset.blockId;
    const groupId    = blockEl.dataset.groupId;
    const dispOrder  = blockEl.dataset.displayOrder;
    const sceneBlock = blockEl.closest('.scene-block');
    const sceneId    = sceneBlock ? sceneBlock.dataset.sceneId : '';
    const sceneTitleEl = sceneBlock ? sceneBlock.querySelector('.scene-title') : null;
    const sceneTitle = sceneTitleEl ? sceneTitleEl.textContent.trim() : '';
    const ta = blockEl.querySelector('.overlay-text');
    const text = ta ? ta.value : (blockEl.dataset.textContent || '');

    // Collect entity chips grouped by table (entity_type)
    const entityMap = {};
    blockEl.querySelectorAll('.beat-entity-chip').forEach(chip => {
        const type  = chip.dataset.entityType  || '';
        const id    = chip.dataset.entityId    || '';
        const label = chip.dataset.entityLabel || chip.querySelector('.chip-label')?.textContent.trim() || '';
        if (!type) return;
        if (!entityMap[type]) entityMap[type] = [];
        entityMap[type].push({ id: id ? parseInt(id, 10) : null, name: label });
    });

    return {
        block_id:      blockId,
        scene_id:      sceneId,
        scene_title:   sceneTitle,
        group_id:      groupId,
        display_order: dispOrder,
        text:          text,
        entities:      entityMap,
    };
}

// Trigger a browser download of text content as a file
function triggerDownload(filename, content) {
    const blob = new Blob([content], { type: 'application/json;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 1000);
}

// Export a single block (called from per-block button)
window.exportBlock = function(blockEl) {
    const data = collectBlockData(blockEl);
    const filename = 'plush_block_' + (data.block_id || 'x') + '_scene_' + (data.scene_id || 'x') + '.json';
    triggerDownload(filename, JSON.stringify(data, null, 2));
    Toast.show('Block exported.', 'success');
};

// Export all blocks (scoped to visible scene-blocks when filter is active)
window.exportAll = function() {
    const visibleBlocks = [];
    document.querySelectorAll('#plush-workspace .scene-block').forEach(sb => {
        if (sb.style.display === 'none') return;
        sb.querySelectorAll('.overlay-block').forEach(b => visibleBlocks.push(b));
    });
    if (!visibleBlocks.length) { Toast.show('No blocks to export.', 'warn'); return; }
    const allData = [];
    visibleBlocks.forEach(blockEl => allData.push(collectBlockData(blockEl)));
    const storyTitle = <?= json_encode($story['title']) ?>;
    const filterVal  = document.getElementById('sceneFilterInput')?.value.trim() || '';
    const payload = {
        story_id:    STORY_ID,
        story_title: storyTitle,
        lang:        EDIT_LANG,
        filter:      filterVal || null,
        exported_at: new Date().toISOString(),
        blocks:      allData,
    };
    const suffix   = filterVal ? '_filtered' : '';
    const filename = 'plush_story_' + STORY_ID + suffix + '_export.json';
    triggerDownload(filename, JSON.stringify(payload, null, 2));
    Toast.show('Exported ' + allData.length + ' block(s).', 'success');
};

// ── Entity Tagging Logic ──────────────────────────────────────────────────────
const blockEntityTypes = {};

window.setEntityTab = function(blockId, type) {
    blockEntityTypes[blockId] = type;
['char','fact','loc','anim','sket','kg'].forEach(t => {
        const tab = document.getElementById(`tab-${t}-${blockId}`);
        if (!tab) return;
        const tType = t === 'char' ? 'characters' : t === 'fact' ? 'factions' : t === 'loc' ? 'locations' : t === 'anim' ? 'animas' : t === 'sket' ? 'sketches' : 'kg_nodes';



        
        
        
        
        tab.className = 'entity-type-tab' + (tType === type ? ` active-${t}` : '');
    });
    const inp = document.getElementById('esearch-' + blockId);
    if (inp) { inp.value = ''; inp.focus(); }
};

let beatEditorSearchTimer = null;
window.entitySearchInput = function(blockId, q) {
    clearTimeout(beatEditorSearchTimer);
    const type = blockEntityTypes[blockId] || 'characters';
    beatEditorSearchTimer = setTimeout(() => {
        fetch(`plush_api.php?action=search_entities&entity_type=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}`)
            .then(r=>r.json()).then(res => {
                if (!res.success || !res.results.length) { hideAC(blockId); return; }
                const ac = document.getElementById('eac-' + blockId);
                ac.innerHTML = res.results.map(r =>
                    `<div class="entity-ac-item" data-entity-id="${r.id}" data-entity-name="${escHtml(r.name)}">
                        <span style="color:var(--pl-text-dim);font-family:'Space Mono',monospace;font-size:.65rem;margin-right:6px;">#${r.id}</span>${escHtml(r.name)}
                    </div>`
                ).join('');
                // Bind via pointerdown after inserting to DOM — avoids inline string escaping issues
                ac.querySelectorAll('.entity-ac-item').forEach(item => {
                    item.addEventListener('pointerdown', () => addEntityTag(blockId, item.dataset.entityId, item.dataset.entityName));
                });
                ac.style.display = 'block';
            });
    }, 280);
};


window.hideAC = function(blockId) {
    const ac = document.getElementById('eac-' + blockId);
    if (ac) ac.style.display = 'none';
};

window.addEntityTag = function(blockId, entityId, entityLabel) {
    const type = blockEntityTypes[blockId] || 'characters';
    hideAC(blockId);
    const inp = document.getElementById('esearch-' + blockId);
    if (inp) inp.value = '';

    const fd = new URLSearchParams();
    fd.append('action', 'add_block_entity');
    fd.append('block_id', blockId); fd.append('entity_type', type); fd.append('entity_id', entityId);
    fetch('plush_api.php', { method:'POST', body:fd }).then(r=>r.json()).then(res => {
        if (!res.success) { Toast.show(res.message || 'Tag failed', 'error'); return; }
        fetch(`plush_api.php?action=get_block_entities&block_id=${blockId}`).then(r2=>r2.json()).then(res2 => {
            if (!res2.success) return;
            const chipsEl = document.getElementById('chips-' + blockId);
            if (!chipsEl) return;
            chipsEl.innerHTML = res2.entities.map(tag => {
                const cls = tag.entity_type === 'characters' ? 'chip-char' : tag.entity_type === 'factions' ? 'chip-fact' : tag.entity_type === 'locations' ? 'chip-loc' : tag.entity_type === 'animas' ? 'chip-anim' : tag.entity_type === 'sketches' ? 'chip-sket' : 'chip-kg';


                
                
                
                return `<span class="beat-entity-chip ${cls}" data-tag-id="${tag.id}" data-entity-type="${escHtml(tag.entity_type)}" data-entity-id="${tag.entity_id}" data-entity-label="${escHtml(tag.entity_label)}">
                
                
                
                <span class="chip-label" style="cursor:pointer;" onclick="openEntityModal('${escHtml(tag.entity_type)}','${tag.entity_id}','${escHtml(tag.entity_label.replace(/'/g, "\\'"))}')">${escHtml(tag.entity_label)}</span>
                
                
                <!--
                    <span class="chip-label" style="cursor:pointer;" onclick="openEntityModal('${escHtml(tag.entity_type)}','${tag.entity_id}','${escHtml(tag.entity_label).replace(/'/g, "\\'")}')">${escHtml(tag.entity_label)}</span>
                -->
                    
                    
                    
                    <button class="chip-remove" onclick="removeBlockEntityTag(${tag.id},${blockId})" title="Remove"><i class="bi bi-x"></i></button>
                </span>`;
            }).join('');
            Toast.show('Tagged: ' + entityLabel, 'success');
        });
    });
};

window.removeBlockEntityTag = function(tagId, blockId) {
    if (!confirm('Remove this entity reference?')) return;

    const fd = new URLSearchParams();
    fd.append('action', 'remove_block_entity'); fd.append('id', tagId);
    fetch('plush_api.php', { method:'POST', body:fd }).then(r=>r.json()).then(res => {
        if (res.success) {
            const chip = document.querySelector(`.beat-entity-chip[data-tag-id="${tagId}"]`);
            if (chip) chip.remove();
            Toast.show('Tag removed', 'info');
        } else { Toast.show(res.message || 'Remove failed', 'error'); }
    });
};

window.openEntityModal = function(entityType, entityId, label) {
    const url = `entity_form.php?entity_type=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}&view=modal`;
    document.getElementById('entity-iframe').src = url;
    document.getElementById('entityModalTitle').textContent = label + ' — ' + entityType;
    document.getElementById('entity-modal-backdrop').classList.add('active');
};
window.closeEntityModal = function() {
    document.getElementById('entity-modal-backdrop').classList.remove('active');
    document.getElementById('entity-iframe').src = 'about:blank';
};


// ── Create overlay block element ─────────────────────────────────────────────
function createBlockElement(blockId, displayOrder, groupId, sceneId, textContent) {
    const div = document.createElement('div');
    div.className = 'overlay-block';
    div.draggable = true;
    div.dataset.blockId      = blockId;
    div.dataset.displayOrder = displayOrder;
    div.dataset.groupId      = groupId;
    div.dataset.textContent  = textContent || '';
    div.innerHTML = `
        <div class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>
        <span class="block-ref">[${sceneId} . ${groupId} . <span class="ref-order">${displayOrder}</span>]</span>
        <div class="pl-collapsed-preview"></div>
        <textarea class="overlay-text" placeholder="Type highlight text…"></textarea>
        <div class="action-btns">
            <button class="color-btn" title="Set Color"><i class="bi bi-palette"></i></button>
            <button class="export-block-btn" title="Export this block"><i class="bi bi-download"></i></button>
            <button class="del-btn" title="Delete Block"><i class="bi bi-trash"></i></button>
        </div>
        <div class="beat-entity-chips" id="chips-${blockId}"></div>
        <div class="entity-add-row">
            <div class="entity-type-tabs">
                <button class="entity-type-tab active-char" id="tab-char-${blockId}" onclick="setEntityTab(${blockId},'characters')">Char</button>
                <button class="entity-type-tab" id="tab-fact-${blockId}" onclick="setEntityTab(${blockId},'factions')">Fac</button>
                <button class="entity-type-tab" id="tab-loc-${blockId}"  onclick="setEntityTab(${blockId},'locations')">Loc</button>
                <button class="entity-type-tab" id="tab-anim-${blockId}" onclick="setEntityTab(${blockId},'animas')">Anim</button>
                
                <button class="entity-type-tab" id="tab-sket-${blockId}" onclick="setEntityTab(${blockId},'sketches')">Sket</button>
                <button class="entity-type-tab" id="tab-kg-${blockId}" onclick="setEntityTab(${blockId},'kg_nodes')">KG</button>
            </div>
            
            
            
            <div class="entity-search-wrap">
                <input class="entity-search-input" type="text" placeholder="Search entity references…" id="esearch-${blockId}" oninput="entitySearchInput(${blockId},this.value)" onfocus="entitySearchInput(${blockId},this.value)" onblur="setTimeout(()=>hideAC(${blockId}),200)">
                <div class="entity-autocomplete" id="eac-${blockId}" style="display:none;"></div>
            </div>
        </div>`;
    const ta = div.querySelector('textarea');
    ta.value = textContent || '';
    ta.addEventListener('input', function() {
        autoResize(this);
        // Keep data-text-content in sync for export
        div.dataset.textContent = this.value;
        debounceSave(blockId, sceneId, groupId, displayOrder, this.value);
    });
    div.querySelector('.del-btn').addEventListener('click', () => deleteBlock(blockId, div, sceneId, groupId));
    div.querySelector('.color-btn').addEventListener('click', (e) => openColorPicker(e, blockId, div));
    div.querySelector('.export-block-btn').addEventListener('click', () => exportBlock(div));
    return div;
}

// Create the inline add row that follows a block
function createInlineAddRow(sceneId, groupId, afterBlockId, insideGroup) {
    const div = document.createElement('div');
    div.className = 'inline-add-row add-element-btn';
    div.dataset.sceneId      = sceneId;
    div.dataset.groupId      = groupId;
    div.dataset.afterBlockId = afterBlockId;
    if (insideGroup) {
        div.innerHTML = `<div class="add-divider"></div><button class="inline-add-btn block"><i class="bi bi-plus"></i> block</button><div class="add-divider"></div>`;
    } else {
        div.innerHTML = `<div class="add-divider"></div><button class="inline-add-btn block"><i class="bi bi-plus"></i> block</button><button class="inline-add-btn group"><i class="bi bi-folder-plus"></i> group</button><div class="add-divider"></div>`;
    }
    bindInlineAddRow(div);
    return div;
}

// ── Add block (AJAX) ─────────────────────────────────────────────────────────
function addBlock(sceneId, groupId, afterBlockId, insertBeforeEl) {
    if (EDIT_LANG !== 'en') { Toast.show('Add blocks in English first.', 'warn'); return; }
    const fd = new URLSearchParams();
    fd.append('action', 'add_block');
    fd.append('scene_id', sceneId); fd.append('group_id', groupId || 0);
    fetch('plush_api.php', { method:'POST', body:fd })
        .then(r=>r.json()).then(res => {
            if (!res.success) { Toast.show('Failed to add block.', 'error'); return; }
            const el = createBlockElement(res.block_id, res.display_order, groupId || 0, sceneId, '');
            const addRow = createInlineAddRow(sceneId, groupId || 0, res.block_id, (groupId > 0));
            // If currently collapsed, new block inherits collapsed state
            if (allCollapsed) el.classList.add('pl-collapsed');
            if (insertBeforeEl && insertBeforeEl.parentNode) {
                const container = insertBeforeEl.parentNode;
                container.insertBefore(el, insertBeforeEl);
                container.insertBefore(addRow, insertBeforeEl);
                persistBlockOrder(container, sceneId, groupId);
            } else {
                const group = document.querySelector(`.highlight-group[data-group-id="${groupId || 0}"][data-scene-id="${sceneId}"]`);
                if (group) { 
                    const ol = group.querySelector('.overlay-list'); 
                    if (ol) { 
                        ol.appendChild(el); 
                        ol.appendChild(addRow); 
                        persistBlockOrder(ol, sceneId, groupId);
                    } 
                }
            }
            setTimeout(() => { autoResize(el.querySelector('textarea')); el.querySelector('textarea').focus(); }, 30);
        });
}

// ── Delete block ──────────────────────────────────────────────────────────────
function deleteBlock(blockId, element, sceneId, groupId) {
    if (EDIT_LANG !== 'en') { Toast.show('Delete blocks in English first.', 'warn'); return; }
    if (!confirm('Delete this highlight block?')) return;
    const container = element.parentNode;
    const fd = new URLSearchParams();
    fd.append('action', 'delete_block'); fd.append('block_id', blockId);
    fetch('plush_api.php', { method:'POST', body:fd })
        .then(r=>r.json()).then(res => {
            if (res.success) {
                const nextSibling = element.nextElementSibling;
                if (nextSibling && nextSibling.classList.contains('inline-add-row')) nextSibling.remove();
                element.style.opacity = '0';
                setTimeout(() => {
                    element.remove();
                    persistBlockOrder(container, sceneId, groupId);
                }, 200);
            } else { Toast.show('Failed to delete.', 'error'); }
        });
}

// ── Add group ────────────────────────────────────────────────────────────────
function addGroup(sceneId, afterGroupId, insertBeforeEl) {
    if (EDIT_LANG !== 'en') { Toast.show('Add groups in English first.', 'warn'); return; }
    const fd = new URLSearchParams();
    fd.append('action', 'add_group'); fd.append('scene_id', sceneId);
    fetch('plush_api.php', { method:'POST', body:fd })
        .then(r=>r.json()).then(res => {
            if (!res.success) { Toast.show('Failed to add group.', 'error'); return; }
            const groupEl = createGroupElement(res.group_id, sceneId, '', []);
            const afterRow = createAfterGroupAddRow(sceneId, res.group_id);
            const wrap = document.querySelector(`.groups-wrap[data-scene-id="${sceneId}"]`);
            if (insertBeforeEl && insertBeforeEl.parentNode) {
                insertBeforeEl.parentNode.insertBefore(groupEl, insertBeforeEl);
                insertBeforeEl.parentNode.insertBefore(afterRow, insertBeforeEl);
            } else if (wrap) {
                wrap.appendChild(groupEl);
                wrap.appendChild(afterRow);
            }
            const labelInput = groupEl.querySelector('.group-label-input');
            if (labelInput) labelInput.focus();
        });
}

function createAfterGroupAddRow(sceneId, afterGroupId) {
    const div = document.createElement('div');
    div.className = 'inline-add-row add-group-btn';
    div.dataset.sceneId      = sceneId;
    div.dataset.afterGroupId = afterGroupId;
    div.innerHTML = `<div class="add-divider"></div><button class="inline-add-btn group"><i class="bi bi-folder-plus"></i> group</button><div class="add-divider"></div>`;
    bindInlineAddRow(div);
    return div;
}

function createGroupElement(groupId, sceneId, label, blocks) {
    const div = document.createElement('div');
    div.className = 'highlight-group';
    div.dataset.groupId = groupId; div.dataset.sceneId = sceneId;
    div.innerHTML = `
        <div class="group-header">
            <div class="group-drag-handle" title="Drag group"><i class="bi bi-grip-vertical"></i></div>
            <input class="group-label-input" type="text" value="${escHtml(label)}" placeholder="Group label (optional)…" data-group-id="${groupId}">
            <button class="del-group-btn" data-group-id="${groupId}" title="Delete group"><i class="bi bi-trash"></i></button>
        </div>
        <div class="overlay-list"></div>`;
    const ol = div.querySelector('.overlay-list');
    const addRow = createInlineAddRow(sceneId, groupId, 0, true);
    ol.appendChild(addRow);
    bindGroupElement(div, sceneId);
    return div;
}

// ── Delete group ──────────────────────────────────────────────────────────────
function deleteGroup(groupId, groupEl) {
    if (EDIT_LANG !== 'en') { Toast.show('Delete groups in English first.', 'warn'); return; }
    if (!confirm('Delete this group and all its blocks?')) return;
    const fd = new URLSearchParams();
    fd.append('action', 'delete_group'); fd.append('group_id', groupId);
    fetch('plush_api.php', { method:'POST', body:fd })
        .then(r=>r.json()).then(res => {
            if (res.success) {
                const nextSibling = groupEl.nextElementSibling;
                if (nextSibling && nextSibling.classList.contains('inline-add-row')) nextSibling.remove();
                groupEl.style.opacity = '0';
                setTimeout(() => groupEl.remove(), 200);
            } else { Toast.show('Failed to delete group.', 'error'); }
        });
}

// ── Save group label ──────────────────────────────────────────────────────────
let groupLabelTimer = null;
function saveGroupLabel(groupId, label) {
    clearTimeout(groupLabelTimer);
    groupLabelTimer = setTimeout(() => {
        const fd = new URLSearchParams();
        fd.append('action', 'update_group_label'); fd.append('group_id', groupId); fd.append('label', label);
        fetch('plush_api.php', { method:'POST', body:fd });
    }, 600);
}

// ── Bind inline add row buttons ───────────────────────────────────────────────
function bindInlineAddRow(row) {
    const sceneId  = parseInt(row.dataset.sceneId, 10);
    const groupId  = parseInt(row.dataset.groupId || 0, 10);
    const blockBtn = row.querySelector('.inline-add-btn.block');
    const groupBtn = row.querySelector('.inline-add-btn.group');
    if (blockBtn) blockBtn.addEventListener('click', () => addBlock(sceneId, groupId, row.dataset.afterBlockId, row));
    if (groupBtn) groupBtn.addEventListener('click', () => addGroup(sceneId, row.dataset.afterGroupId || 0, row));
}

// Bind add-group rows
function bindAddGroupRow(row) {
    const sceneId = parseInt(row.dataset.sceneId, 10);
    const btn = row.querySelector('.inline-add-btn.group');
    if (btn) btn.addEventListener('click', () => addGroup(sceneId, row.dataset.afterGroupId || 0, row));
}

// ── Bind group element ────────────────────────────────────────────────────────
function bindGroupElement(div, sceneId) {
    const groupId = parseInt(div.dataset.groupId, 10);
    const delBtn  = div.querySelector('.del-group-btn');
    const labelIn = div.querySelector('.group-label-input');
    if (delBtn)  delBtn.addEventListener('click', () => deleteGroup(groupId, div));
    if (labelIn) labelIn.addEventListener('input', () => saveGroupLabel(groupId, labelIn.value));
    initDragSort(div.querySelector('.overlay-list'), sceneId, groupId);
}

// ── Bind existing overlay blocks ──────────────────────────────────────────────
document.querySelectorAll('.overlay-block').forEach(el => {
    const blockId    = el.dataset.blockId;
    const dispOrder  = el.dataset.displayOrder;
    const groupId    = parseInt(el.dataset.groupId || 0, 10);
    const sceneBlock = el.closest('.scene-block');
    const sceneId    = sceneBlock ? parseInt(sceneBlock.dataset.sceneId, 10) : 0;
    const ta         = el.querySelector('textarea');
    setTimeout(() => autoResize(ta), 50);
    ta.addEventListener('input', function() {
        autoResize(this);
        // Keep data-text-content in sync for export
        el.dataset.textContent = this.value;
        debounceSave(blockId, sceneId, groupId, dispOrder, this.value);
    });
    el.querySelector('.del-btn').addEventListener('click', () => deleteBlock(blockId, el, sceneId, groupId));
    const cb = el.querySelector('.color-btn');
    if (cb) cb.addEventListener('click', (e) => openColorPicker(e, blockId, el));
    const eb = el.querySelector('.export-block-btn');
    if (eb) eb.addEventListener('click', () => exportBlock(el));
});


// Bind all existing inline-add-rows
document.querySelectorAll('.inline-add-row.add-element-btn').forEach(bindInlineAddRow);
document.querySelectorAll('.inline-add-row.add-group-btn').forEach(bindAddGroupRow);

// Bind existing group elements
document.querySelectorAll('.highlight-group:not(.is-default)').forEach(div => {
    const sceneId = parseInt(div.dataset.sceneId, 10);
    bindGroupElement(div, sceneId);
});

// ── Add scene ─────────────────────────────────────────────────────────────────
document.querySelectorAll('[data-action="add-scene"]').forEach(btn => {
    btn.addEventListener('click', () => {
        const title = prompt('New scene title:');
        if (!title) return;
        const fd = new URLSearchParams();
        fd.append('action', 'add_scene'); fd.append('story_id', STORY_ID); fd.append('title', title);
        fetch('plush_api.php', { method:'POST', body:fd })
            .then(r=>r.json()).then(res => {
                if (res.success) { window.location.reload(); }
                else { Toast.show(res.message || 'Failed to add scene.', 'error'); }
            });
    });
});

// ── Drag & Drop (blocks within an overlay-list) ───────────────────────────────
function initDragSort(container, sceneId, groupId) {
    if (!container) return;
    if (EDIT_LANG !== 'en') return;
    let dragSrc = null;

    container.addEventListener('dragstart', e => {
        const block = e.target.closest('.overlay-block');
        if (!block) return;
        dragSrc = block; block.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });
    container.addEventListener('dragend', e => {
        const block = e.target.closest('.overlay-block');
        if (block) block.classList.remove('dragging');
        container.querySelectorAll('.overlay-block').forEach(b => b.classList.remove('drag-over-top','drag-over-bottom'));
        dragSrc = null;
        persistBlockOrder(container, sceneId, groupId);
    });
    container.addEventListener('dragover', e => {
        e.preventDefault(); e.dataTransfer.dropEffect = 'move';
        const block = e.target.closest('.overlay-block');
        if (!block || block === dragSrc) return;
        container.querySelectorAll('.overlay-block').forEach(b => b.classList.remove('drag-over-top','drag-over-bottom'));
        const rect = block.getBoundingClientRect();
        block.classList.add(e.clientY < rect.top + rect.height / 2 ? 'drag-over-top' : 'drag-over-bottom');
    });
    container.addEventListener('drop', e => {
        e.preventDefault(); if (!dragSrc) return;
        const block = e.target.closest('.overlay-block');
        if (!block || block === dragSrc) return;
        const rect = block.getBoundingClientRect();
        container.insertBefore(dragSrc, e.clientY < rect.top + rect.height / 2 ? block : block.nextSibling);
    });

    // Touch/pointer drag
    let pSrc = null, pClone = null, pOffY = 0;
    container.addEventListener('pointerdown', e => {
        const handle = e.target.closest('.drag-handle');
        if (!handle) return;
        const block = handle.closest('.overlay-block'); if (!block) return;
        pSrc = block;
        const rect = block.getBoundingClientRect(); pOffY = e.clientY - rect.top;
        pClone = block.cloneNode(true);
        pClone.style.cssText = `position:fixed;left:${rect.left}px;top:${rect.top}px;width:${rect.width}px;opacity:.8;pointer-events:none;z-index:9999;background:var(--pl-surface);border:1px solid var(--pl-amber);box-shadow:0 10px 30px rgba(0,0,0,.8);`;
        document.body.appendChild(pClone); block.classList.add('dragging'); e.preventDefault();
    }, { passive: false });
    document.addEventListener('pointermove', e => {
        if (!pSrc || !pClone) return;
        pClone.style.top = (e.clientY - pOffY) + 'px';
        container.querySelectorAll('.overlay-block').forEach(b => b.classList.remove('drag-over-top','drag-over-bottom'));
        const target = document.elementFromPoint(e.clientX, e.clientY);
        const block  = target ? target.closest('.overlay-block') : null;
        if (block && block !== pSrc && block.parentNode === container) {
            block.classList.add(e.clientY < block.getBoundingClientRect().top + block.getBoundingClientRect().height / 2 ? 'drag-over-top' : 'drag-over-bottom');
        }
    });
    document.addEventListener('pointerup', e => {
        if (!pSrc) return;
        if (pClone) { pClone.remove(); pClone = null; }
        pSrc.classList.remove('dragging');
        container.querySelectorAll('.overlay-block').forEach(b => b.classList.remove('drag-over-top','drag-over-bottom'));
        const target = document.elementFromPoint(e.clientX, e.clientY);
        const block  = target ? target.closest('.overlay-block') : null;
        if (block && block !== pSrc && block.parentNode === container) {
            const rect = block.getBoundingClientRect();
            container.insertBefore(pSrc, e.clientY < rect.top + rect.height / 2 ? block : block.nextSibling);
        }
        persistBlockOrder(container, sceneId, groupId);
        pSrc = null;
    });
}

function persistBlockOrder(container, sceneId, groupId) {
    const ids = [];
    container.querySelectorAll('.overlay-block').forEach((b, i) => {
        const bId = b.dataset.blockId;
        if (bId) {
            ids.push(bId);
            b.dataset.displayOrder = i;
            const ref = b.querySelector('.ref-order');
            if (ref) ref.textContent = i;
        }
    });
    
    if (!ids.length) return;
    const fd = new URLSearchParams();
    fd.append('action', 'reorder_blocks'); fd.append('scene_id', sceneId);
    fd.append('group_id', groupId || 0); fd.append('order', ids.join(','));
    fetch('plush_api.php', { method:'POST', body:fd });
}

// Init drag-sort on all existing overlay lists
document.querySelectorAll('.highlight-group').forEach(gEl => {
    const sceneId = parseInt(gEl.dataset.sceneId, 10);
    const groupId = parseInt(gEl.dataset.groupId || 0, 10);
    initDragSort(gEl.querySelector('.overlay-list'), sceneId, groupId);
});

// ── Language Modal ────────────────────────────────────────────────────────────
window.openLangModal = function() {
    document.getElementById('langModalBackdrop').classList.add('active');
    loadLanguages();
};
window.closeLangModal = function() {
    document.getElementById('langModalBackdrop').classList.remove('active');
};
function loadLanguages() {
    fetch('plush_api.php?action=get_languages')
        .then(r=>r.json()).then(res => {
            if (res.success) {
                document.getElementById('lang-list').innerHTML = res.languages.map(l => `
                    <div class="lang-row">
                        <div><strong style="color:var(--pl-amber);text-transform:uppercase;margin-right:8px;">${l.code}</strong> ${escHtml(l.name)}</div>
                        ${l.is_main == 1 ? '<span style="font-size:10px;color:var(--pl-text-dim);font-weight:bold;">MAIN</span>' : `<button class="del-btn" style="position:static;width:28px;height:28px;opacity:1;" onclick="deleteLanguage('${l.code}')"><i class="bi bi-trash"></i></button>`}
                    </div>`).join('');
            }
        });
}
window.saveLanguage = function() {
    const code = document.getElementById('lang-code').value.trim();
    const name = document.getElementById('lang-name').value.trim();
    if (code.length !== 2 || !name) { Toast.show('Invalid 2-letter code or name', 'warn'); return; }
    const fd = new URLSearchParams();
    fd.append('action','save_language'); fd.append('code',code); fd.append('name',name);
    fetch('plush_api.php', {method:'POST',body:fd}).then(r=>r.json()).then(res => {
        if (res.success) {
            document.getElementById('lang-code').value = '';
            document.getElementById('lang-name').value = '';
            loadLanguages(); Toast.show('Language saved','success');
            setTimeout(()=>window.location.reload(), 1000);
        } else { Toast.show(res.error||'Failed','error'); }
    });
};
window.deleteLanguage = function(code) {
    if (!confirm('Delete this system language?')) return;
    const fd = new URLSearchParams();
    fd.append('action','delete_language'); fd.append('code',code);
    fetch('plush_api.php', {method:'POST',body:fd}).then(r=>r.json()).then(res => {
        if (res.success) { loadLanguages(); Toast.show('Language deleted','info'); setTimeout(()=>window.location.reload(),1000); }
    });
};

})();
</script>

<?php


//echo $eruda ?? "";


$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
